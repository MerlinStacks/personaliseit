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

	public function enqueue( int $order_id, int $item_id, int $print_area_id, array $area_data, string $print_method ): int {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'oc_print_queue',
			[
				'order_id'      => $order_id,
				'order_item_id' => $item_id,
				'print_area_id' => $print_area_id,
				'area_data'     => wp_json_encode( $area_data ),
				'print_method'  => $print_method,
				'status'        => 'pending',
			],
			[ '%d', '%d', '%d', '%s', '%s', '%s' ]
		);

		$this->schedule_processing();

		return (int) $wpdb->insert_id;
	}

	/** Schedule a near-immediate queue run for newly inserted jobs. */
	public function schedule_processing(): void {
		if ( ! wp_next_scheduled( 'oc_process_print_queue_now' ) ) {
			wp_schedule_single_event( time() + 1, 'oc_process_print_queue_now' );
		}
	}

	public function process(): void {
		$this->reset_stale_processing_jobs();

		$jobs = OC_DB::get_pending_queue_jobs( self::BATCH_SIZE );

		foreach ( $jobs as $job ) {
			$this->process_one( (int) $job->id );
		}
	}

	public function process_one( int $job_id ): void {
		global $wpdb;
		$job = OC_DB::get_queue_job( $job_id );

		if ( ! $job ) {
			return;
		}

		if ( 'processing' === $job->status ) {
			return;
		}

		if ( 'failed' === $job->status && (int) $job->attempts >= self::MAX_ATTEMPTS ) {
			return;
		}

		OC_DB::update_queue_job( $job_id, [
			'status'       => 'processing',
			'attempts'     => (int) $job->attempts + 1,
			'processed_at' => current_time( 'mysql', true ),
		] );

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

			$area = $wpdb->get_row( $wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}oc_design_print_areas WHERE id = %d LIMIT 1",
				$job->print_area_id
			) );

			$is_v2_area = (bool) $area;

			if ( ! $area ) {
				$area = $wpdb->get_row( $wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}oc_print_areas WHERE id = %d LIMIT 1",
					$job->print_area_id
				) );
			}

			if ( ! $area ) {
				throw new \RuntimeException( "Print area #{$job->print_area_id} not found." );
			}

			if ( $is_v2_area ) {
				$area = OC_Print_Generator::area_object_for_generation( $area, $area_data );
			}

			$print_file = $wpdb->get_row( $wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}oc_print_files WHERE order_id = %d AND order_item_id = %d AND print_area_id = %d ORDER BY id DESC LIMIT 1",
				$job->order_id,
				$job->order_item_id,
				$job->print_area_id
			) );

			if ( ! $print_file ) {
				throw new \RuntimeException( 'No matching print file record found for queue job.' );
			}

			$retention_days = (int) OC_Admin_Settings::get( 'file_retention_days' ) ?: 90;
			$now            = current_time( 'mysql', true );
			$expires_at     = gmdate( 'Y-m-d H:i:s', strtotime( "+{$retention_days} days", strtotime( $now ) ) );

			OC_DB::update_print_file( (int) $print_file->id, [
				'file_status'  => 'generating',
				'generated_at' => $now,
				'expires_at'   => $expires_at,
				'file_path'    => null,
			] );

			$result = OC_Print_Generator::generate_for_area( $order, (int) $job->order_item_id, $area, $area_data );

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
				(int) $job->attempts + 1,
				$error_msg
			) );

			if ( (int) $job->attempts + 1 >= self::MAX_ATTEMPTS ) {
				OC_DB::update_queue_job( $job_id, [
					'status'        => 'failed',
					'error_message' => $error_msg,
					'processed_at'  => current_time( 'mysql', true ),
				] );

				$this->mark_job_print_files_pending( $job, $area_data );

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

				$this->mark_job_print_files_pending( $job, $area_data );
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

			$area_id = (int) $entry['areaId'];
			$area    = $this->get_print_area_for_job( $area_id );
			if ( ! $area ) {
				throw new \RuntimeException( "Print area #{$area_id} not found." );
			}

			if ( $this->is_v2_print_area( $area_id ) ) {
				$area = OC_Print_Generator::area_object_for_generation( $area, $entry['areaData'] );
			}

			$print_file = $this->get_print_file_for_area( (int) $job->order_id, (int) $job->order_item_id, $area_id );
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

		foreach ( $print_files as $print_file ) {
			OC_DB::update_print_file( (int) $print_file->id, [
				'file_status'  => 'generating',
				'generated_at' => $now,
				'expires_at'   => $expires_at,
				'file_path'    => null,
			] );
		}

		$result = OC_Print_Generator::generate_for_areas( $order, (int) $job->order_item_id, (string) $job->print_method, $areas );
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

	private function get_print_area_for_job( int $area_id ): ?object {
		global $wpdb;
		$area = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}oc_design_print_areas WHERE id = %d LIMIT 1",
			$area_id
		) );

		if ( $area ) {
			return $area;
		}

		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}oc_print_areas WHERE id = %d LIMIT 1",
			$area_id
		) ) ?: null;
	}

	private function is_v2_print_area( int $area_id ): bool {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$wpdb->prefix}oc_design_print_areas WHERE id = %d LIMIT 1",
			$area_id
		) );
	}

	private function get_print_file_for_area( int $order_id, int $item_id, int $area_id ): ?object {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}oc_print_files WHERE order_id = %d AND order_item_id = %d AND print_area_id = %d ORDER BY id DESC LIMIT 1",
			$order_id,
			$item_id,
			$area_id
		) ) ?: null;
	}

	private function mark_job_print_files_pending( object $job, mixed $area_data ): void {
		$area_ids = [ (int) $job->print_area_id ];
		if ( is_array( $area_data ) && OC_Print_Generator::is_combined_area_data( $area_data ) ) {
			$area_ids = [];
			foreach ( $area_data['__combined_print_areas'] as $entry ) {
				if ( is_array( $entry ) && ! empty( $entry['areaId'] ) ) {
					$area_ids[] = (int) $entry['areaId'];
				}
			}
		}

		foreach ( array_unique( $area_ids ) as $area_id ) {
			$print_file = $this->get_print_file_for_area( (int) $job->order_id, (int) $job->order_item_id, $area_id );
			if ( $print_file ) {
				OC_DB::update_print_file( (int) $print_file->id, [ 'file_status' => 'pending' ] );
			}
		}
	}

	/** Return true when an order has no pending or processing print jobs left. */
	private function order_print_queue_complete( int $order_id ): bool {
		global $wpdb;

		return 0 === (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}oc_print_queue WHERE order_id = %d AND status IN ('pending', 'processing')",
			$order_id
		) );
	}

	/** Return timed-out processing jobs to the queue so cron can recover after fatal errors. */
	public function reset_stale_processing_jobs(): int {
		global $wpdb;

		$cutoff = gmdate( 'Y-m-d H:i:s', time() - self::STALE_PROCESSING_SECONDS );
		$result = $wpdb->query( $wpdb->prepare(
			"UPDATE {$wpdb->prefix}oc_print_queue
			 SET status = 'pending', error_message = %s, processed_at = NULL
			 WHERE status = 'processing'
			 AND (processed_at IS NULL OR processed_at <= %s)",
			__( 'Job was reset after being stuck in processing.', 'overcustomise' ),
			$cutoff
		) );

		return false === $result ? 0 : (int) $result;
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
