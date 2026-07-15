<?php
defined( 'ABSPATH' ) || exit;

class OC_Print_Queue {

	private const MAX_ATTEMPTS = 3;
	private const RETRY_BACKOFF_SECONDS = 300;
	private const BATCH_SIZE = 5;
	private const STALE_PROCESSING_SECONDS = 900;

	private static ?OC_Print_Queue $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function register(): void {
		add_action( 'oc_process_print_queue', [ $this, 'process' ] );
		add_action( 'oc_process_print_queue_now', [ $this, 'process' ] );
	}

	public function enqueue( int $order_id, int $item_id, int $print_area_id, array $area_data, string $print_method, int $print_file_id = 0, string $area_source = 'unknown', int $row_index = 0 ): int {
		global $wpdb;
		if ( ! OC_DB::print_pipeline_available() ) {
			return 0;
		}

		$table = $wpdb->prefix . 'oc_print_queue';
		if ( $print_file_id > 0 ) {
			$wpdb->query( $wpdb->prepare(
				"INSERT IGNORE INTO {$table}
				 (print_file_id, order_id, order_item_id, print_area_id, area_source, row_index, area_data, print_method, status)
				 VALUES (%d, %d, %d, %d, %s, %d, %s, %s, 'pending')",
				$print_file_id,
				$order_id,
				$item_id,
				$print_area_id,
				$area_source,
				$row_index,
				wp_json_encode( $area_data ),
				$print_method
			) );
			$queue_id = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM {$table} WHERE print_file_id = %d LIMIT 1",
				$print_file_id
			) );
		} else {
			$wpdb->insert(
				$table,
				[
					'order_id'      => $order_id,
					'order_item_id' => $item_id,
					'print_area_id' => $print_area_id,
					'area_source'   => $area_source,
					'row_index'     => $row_index,
					'area_data'     => wp_json_encode( $area_data ),
					'print_method'  => $print_method,
					'status'        => 'pending',
				]
			);
			$queue_id = (int) $wpdb->insert_id;
		}

		$this->schedule_processing();

		return $queue_id;
	}

	/** Schedule a near-immediate queue run for newly inserted jobs. */
	public function schedule_processing(): void {
		if ( ! wp_next_scheduled( 'oc_process_print_queue_now' ) ) {
			wp_schedule_single_event( time() + 1, 'oc_process_print_queue_now' );
		}
	}

	public function process(): void {
		if ( ! OC_DB::print_pipeline_available() ) {
			return;
		}

		$this->reset_stale_processing_jobs();

		$jobs = OC_DB::get_pending_queue_jobs( self::BATCH_SIZE );

		foreach ( $jobs as $job ) {
			$this->process_one( (int) $job->id );
		}
	}

	public function process_one( int $job_id ): void {
		if ( ! OC_DB::print_pipeline_available() ) {
			return;
		}

		global $wpdb;
		$job = OC_DB::claim_queue_job( $job_id, self::MAX_ATTEMPTS );

		if ( ! $job ) {
			return;
		}

		$area      = null;
		$area_data = null;

		try {
			$area_data = json_decode( (string) $job->area_data, true );

			if ( ! is_array( $area_data ) ) {
				throw new \RuntimeException( 'Invalid area_data in queue job.' );
			}

			$order = wc_get_order( (int) $job->order_id );

			if ( ! $order instanceof \WC_Order ) {
				throw new \RuntimeException( "Order #{$job->order_id} not found." );
			}

			$target_item = $order->get_item( (int) $job->order_item_id );

			if ( ! $target_item ) {
				throw new \RuntimeException( "Order item #{$job->order_item_id} not found." );
			}

			if ( OC_Print_Generator::is_combined_area_data( $area_data ) ) {
				$this->process_combined( $job_id, $job, $order, $area_data );
				return;
			}

			$print_file = ! empty( $job->print_file_id ) ? OC_DB::get_print_file( (int) $job->print_file_id ) : null;
			$area       = $this->area_from_snapshot( $print_file );
			if ( ! $area ) {
				$area = $this->get_print_area_for_job( (int) $job->print_area_id, (string) $job->area_source );
			}

			if ( ! $area ) {
				throw new \RuntimeException( "Print area #{$job->print_area_id} not found." );
			}

			if ( 'design' === (string) $job->area_source || ( 'unknown' === (string) $job->area_source && isset( $area->design_id ) ) ) {
				$area = OC_Print_Generator::area_object_for_generation( $area, $area_data );
			}

			if ( ! $print_file ) {
				$print_file = $this->get_print_file_for_area( (int) $job->order_id, (int) $job->order_item_id, (int) $job->print_area_id, (string) $job->area_source, (int) $job->row_index );
			}

			if ( ! $print_file ) {
				throw new \RuntimeException( 'No matching print file record found for queue job.' );
			}

			$retention_days = (int) OC_Admin_Settings::get( 'file_retention_days' ) ?: 90;
			$now            = current_time( 'mysql', true );
			$expires_at     = gmdate( 'Y-m-d H:i:s', strtotime( "+{$retention_days} days", strtotime( $now ) ) );

			$result = OC_Print_Generator::with_output_lock(
				(int) $job->order_id,
				(int) $job->order_item_id,
				static function () use ( $order, $job, $area, $area_data, $print_file ): array {
					$result = OC_Print_Generator::generate_for_area( $order, (int) $job->order_item_id, $area, $area_data );
					$result['file_path'] = OC_Print_Generator::finalise_generated_output( $result['file_path'], (int) $print_file->id );
					return $result;
				}
			);

			$thumb_path = null;
			$ext = strtolower( pathinfo( $result['file_path'], PATHINFO_EXTENSION ) );
			if ( 'pdf' === $ext && file_exists( $result['file_path'] ) ) {
				$thumb_path = pathinfo( $result['file_path'], PATHINFO_DIRNAME ) . '/'
					. pathinfo( $result['file_path'], PATHINFO_FILENAME ) . '-thumb.png';

				if ( ! OC_Preview_Generator::from_pdf( $result['file_path'], $thumb_path ) ) {
					$thumb_path = null;
				}
			}

			OC_DB::update_print_file( (int) $print_file->id, [
				'file_path'      => $result['file_path'],
				'file_status'    => $result['status'],
				'thumbnail_path' => $thumb_path,
				'generated_at'   => $now,
				'expires_at'     => $expires_at,
			] );

			OC_DB::update_queue_job( $job_id, [
				'status'       => 'done',
				'processed_at' => current_time( 'mysql', true ),
			] );

			OC_Logger::info( "Print file generated via queue: job #{$job_id}, file #{$print_file->id}" );

			if ( $this->order_print_queue_complete( (int) $job->order_id ) ) {
				do_action( 'oc_print_files_generated', $order );
			}

		} catch ( \Throwable $e ) {
			$error_msg = $e->getMessage();

			OC_Logger::error( sprintf(
				'Queue job #%d failed (attempt %d): %s',
				$job_id,
				(int) $job->attempts,
				$error_msg
			) );

			if ( (int) $job->attempts >= self::MAX_ATTEMPTS ) {
				OC_DB::update_queue_job( $job_id, [
					'status'        => 'failed',
					'error_message' => $error_msg,
					'processed_at'  => current_time( 'mysql', true ),
				] );

				$order = wc_get_order( (int) $job->order_id );
				if ( $order instanceof \WC_Order ) {
					if ( ! is_object( $area ) ) {
						$area = (object) [
							'area_key'     => '',
							'print_method' => (string) $job->print_method,
						];
					}
					do_action( 'oc_print_file_failed', $order, (int) $job->order_item_id, $area, $e );
				}
			} else {
				$retry_at = gmdate( 'Y-m-d H:i:s', time() + self::RETRY_BACKOFF_SECONDS );
				OC_DB::update_queue_job( $job_id, [
					'status'        => 'pending',
					'error_message' => $error_msg,
					'processed_at'  => $retry_at,
				] );
			}
		}
	}

	private function process_combined( int $job_id, object $job, \WC_Order $order, array $area_data ): void {
		$entries = $area_data['__combined_print_areas'];
		if ( empty( $entries ) || ! is_array( $entries ) ) {
			throw new \RuntimeException( 'Invalid combined print area payload.' );
		}

		$areas       = [];
		$print_files = [];
		foreach ( $entries as $entry ) {
			if ( ! is_array( $entry ) || empty( $entry['areaId'] ) || ! is_array( $entry['areaData'] ?? null ) ) {
				continue;
			}

			$area_id     = (int) $entry['areaId'];
			$area_source = (string) ( $entry['areaSource'] ?? $job->area_source ?? 'unknown' );
			$print_file  = ! empty( $entry['printFileId'] ) ? OC_DB::get_print_file( (int) $entry['printFileId'] ) : null;
			$area        = $this->area_from_snapshot( $print_file );
			if ( ! $area ) {
				$area = $this->get_print_area_for_job( $area_id, $area_source );
			}
			if ( ! $area ) {
				throw new \RuntimeException( "Print area #{$area_id} not found." );
			}

			if ( 'design' === $area_source || ( 'unknown' === $area_source && isset( $area->design_id ) ) ) {
				$area = OC_Print_Generator::area_object_for_generation( $area, $entry['areaData'] );
			}

			if ( ! $print_file ) {
				$print_file = $this->get_print_file_for_area( (int) $job->order_id, (int) $job->order_item_id, $area_id, $area_source, (int) ( $entry['rowIndex'] ?? $job->row_index ?? 0 ) );
			}
			if ( ! $print_file ) {
				throw new \RuntimeException( "No matching print file record found for combined print area #{$area_id}." );
			}

			$areas[] = [
				'area'      => $area,
				'area_data' => $entry['areaData'],
			];
			$print_files[] = $print_file;
		}

		if ( empty( $areas ) || empty( $print_files ) ) {
			throw new \RuntimeException( 'Combined print job did not contain any usable print areas.' );
		}

		$retention_days = (int) OC_Admin_Settings::get( 'file_retention_days' ) ?: 90;
		$now            = current_time( 'mysql', true );
		$expires_at     = gmdate( 'Y-m-d H:i:s', strtotime( "+{$retention_days} days", strtotime( $now ) ) );

		$result = OC_Print_Generator::with_output_lock(
			(int) $job->order_id,
			(int) $job->order_item_id,
			static function () use ( $order, $job, $areas, $print_files ): array {
				$result = OC_Print_Generator::generate_for_areas( $order, (int) $job->order_item_id, (string) $job->print_method, $areas );
				$result['file_path'] = OC_Print_Generator::finalise_generated_output( $result['file_path'], (int) $print_files[0]->id );
				return $result;
			}
		);
		$thumb_path = null;
		$ext = strtolower( pathinfo( $result['file_path'], PATHINFO_EXTENSION ) );
		if ( 'pdf' === $ext && file_exists( $result['file_path'] ) ) {
			$thumb_path = pathinfo( $result['file_path'], PATHINFO_DIRNAME ) . '/'
				. pathinfo( $result['file_path'], PATHINFO_FILENAME ) . '-thumb.png';

			if ( ! OC_Preview_Generator::from_pdf( $result['file_path'], $thumb_path ) ) {
				$thumb_path = null;
			}
		}

		foreach ( $print_files as $print_file ) {
			OC_DB::update_print_file( (int) $print_file->id, [
				'file_path'      => $result['file_path'],
				'file_status'    => $result['status'],
				'thumbnail_path' => $thumb_path,
				'generated_at'   => $now,
				'expires_at'     => $expires_at,
			] );
		}

		OC_DB::update_queue_job( $job_id, [
			'status'       => 'done',
			'processed_at' => current_time( 'mysql', true ),
		] );

		OC_Logger::info( "Combined print file generated via queue: job #{$job_id}" );

		if ( $this->order_print_queue_complete( (int) $job->order_id ) ) {
			do_action( 'oc_print_files_generated', $order );
		}
	}

	private function get_print_area_for_job( int $area_id, string $area_source ): ?object {
		global $wpdb;
		if ( 'unknown' === $area_source ) {
			// Historical jobs resolved colliding IDs design-first. Preserve that
			// behaviour for rows whose source could not be established safely.
			foreach ( [ 'oc_design_print_areas', 'oc_print_areas' ] as $suffix ) {
				$area = $wpdb->get_row( $wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}{$suffix} WHERE id = %d LIMIT 1",
					$area_id
				) );
				if ( $area ) {
					return $area;
				}
			}
			return null;
		}

		$table = match ( $area_source ) {
			'design' => $wpdb->prefix . 'oc_design_print_areas',
			'legacy' => $wpdb->prefix . 'oc_print_areas',
			default  => '',
		};

		if ( '' === $table ) {
			return null;
		}

		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE id = %d LIMIT 1",
			$area_id
		) ) ?: null;
	}

	private function area_from_snapshot( ?object $print_file ): ?object {
		if ( ! $print_file || empty( $print_file->area_snapshot ) ) {
			return null;
		}

		$snapshot = json_decode( (string) $print_file->area_snapshot, true );
		return is_array( $snapshot ) && ! empty( $snapshot ) ? (object) $snapshot : null;
	}

	private function get_print_file_for_area( int $order_id, int $item_id, int $area_id, string $area_source, int $row_index ): ?object {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}oc_print_files WHERE order_id = %d AND order_item_id = %d AND print_area_id = %d AND area_source = %s AND row_index = %d ORDER BY id DESC LIMIT 1",
			$order_id,
			$item_id,
			$area_id,
			$area_source,
			$row_index
		) ) ?: null;
	}

	/** Return true when an order has no pending or processing print jobs left. */
	private function order_print_queue_complete( int $order_id ): bool {
		global $wpdb;

		return 0 === (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}oc_print_queue WHERE order_id = %d AND status IN ('pending', 'processing')",
			$order_id
		) );
	}

	/** Recover timed-out jobs, failing those that have exhausted their attempts. */
	public function reset_stale_processing_jobs(): int {
		global $wpdb;

		$cutoff = gmdate( 'Y-m-d H:i:s', time() - self::STALE_PROCESSING_SECONDS );
		$stale_at_limit = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}oc_print_queue
			 WHERE status = 'processing' AND attempts >= %d
			 AND (processed_at IS NULL OR processed_at <= %s)",
			self::MAX_ATTEMPTS,
			$cutoff
		) ) ?: [];
		$updated = 0;
		$message = __( 'Job failed after being stuck in processing at the maximum attempt count.', 'overcustomise' );
		foreach ( $stale_at_limit as $job ) {
			$result = $wpdb->query( $wpdb->prepare(
				"UPDATE {$wpdb->prefix}oc_print_queue
				 SET status = 'failed', error_message = %s, processed_at = %s
				 WHERE id = %d AND status = 'processing' AND attempts >= %d
				 AND (processed_at IS NULL OR processed_at <= %s)",
				$message,
				current_time( 'mysql', true ),
				(int) $job->id,
				self::MAX_ATTEMPTS,
				$cutoff
			) );
			if ( 1 !== $result ) {
				continue;
			}
			$updated++;

			$order = wc_get_order( (int) $job->order_id );
			if ( $order instanceof \WC_Order ) {
				$area = (object) [
					'area_key'     => '',
					'print_method' => (string) $job->print_method,
				];
				do_action( 'oc_print_file_failed', $order, (int) $job->order_item_id, $area, new \RuntimeException( $message ) );
			}
		}

		$result = $wpdb->query( $wpdb->prepare(
			"UPDATE {$wpdb->prefix}oc_print_queue
			 SET status = 'pending', error_message = %s, processed_at = NULL
			 WHERE status = 'processing'
			 AND attempts < %d
			 AND (processed_at IS NULL OR processed_at <= %s)",
			__( 'Job was reset after being stuck in processing.', 'overcustomise' ),
			self::MAX_ATTEMPTS,
			$cutoff
		) );

		return $updated + ( false === $result ? 0 : (int) $result );
	}

	public function get_status( int $file_id ): array {
		$file = OC_DB::get_print_file( $file_id );

		if ( ! $file ) {
			return [ 'found' => false ];
		}

		$queue_status = OC_DB::get_queue_status_for_item( (int) $file->order_item_id );

		$pending_count = 0;
		$processing    = false;
		$failed        = false;
		$error_message = '';

		if ( is_array( $queue_status ) ) {
			foreach ( $queue_status as $q ) {
				if ( ! empty( $q->print_file_id ) && (int) $q->print_file_id !== (int) $file->id ) {
					$area_data = json_decode( (string) ( $q->area_data ?? '' ), true );
					$contains_file = false;
					if ( is_array( $area_data ) && OC_Print_Generator::is_combined_area_data( $area_data ) ) {
						foreach ( $area_data['__combined_print_areas'] as $entry ) {
							if ( is_array( $entry ) && (int) ( $entry['printFileId'] ?? 0 ) === (int) $file->id ) {
								$contains_file = true;
								break;
							}
						}
					}
					if ( ! $contains_file ) {
						continue;
					}
				}
				if ( ! $this->queue_job_contains_print_area( $q, (int) $file->print_area_id ) ) {
					continue;
				}

					if ( 'pending' === $q->status ) {
						$pending_count++;
					} elseif ( 'processing' === $q->status ) {
						$processing = true;
					} elseif ( 'failed' === $q->status ) {
						$failed = true;
						if ( '' === $error_message && ! empty( $q->error_message ) ) {
							$error_message = (string) $q->error_message;
						}
					}
			}
		}

		return [
			'found'           => true,
			'file_status'     => $file->file_status,
			'in_queue'        => $pending_count > 0,
			'queue_position'  => $pending_count,
			'is_processing'   => $processing,
			'has_failed_job'  => $failed,
			'error_message'   => $error_message,
		];
	}

	private function queue_job_contains_print_area( object $job, int $print_area_id ): bool {
		if ( (int) $job->print_area_id === $print_area_id ) {
			return true;
		}

		$area_data = json_decode( (string) ( $job->area_data ?? '' ), true );
		if ( ! is_array( $area_data ) || ! OC_Print_Generator::is_combined_area_data( $area_data ) ) {
			return false;
		}

		foreach ( $area_data['__combined_print_areas'] as $entry ) {
			if ( is_array( $entry ) && (int) ( $entry['areaId'] ?? 0 ) === $print_area_id ) {
				return true;
			}
		}

		return false;
	}
}
