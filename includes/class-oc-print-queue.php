<?php
defined( 'ABSPATH' ) || exit;

class OC_Print_Queue {

	private const MAX_ATTEMPTS = 3;
	private const RETRY_BACKOFF_SECONDS = 300;
	private const BATCH_SIZE = 5;

	private static ?OC_Print_Queue $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function register(): void {
		add_action( 'oc_process_print_queue', [ $this, 'process' ] );
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

		return (int) $wpdb->insert_id;
	}

	public function process(): void {
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
			'processed_at' => null,
		] );

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

				$print_file = $wpdb->get_row( $wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}oc_print_files WHERE order_id = %d AND order_item_id = %d AND print_area_id = %d ORDER BY id DESC LIMIT 1",
					$job->order_id,
					$job->order_item_id,
					$job->print_area_id
				) );

				if ( $print_file ) {
					OC_DB::update_print_file( (int) $print_file->id, [ 'file_status' => 'pending' ] );
				}
			} else {
				$retry_at = gmdate( 'Y-m-d H:i:s', time() + self::RETRY_BACKOFF_SECONDS );
				OC_DB::update_queue_job( $job_id, [
					'status'        => 'pending',
					'error_message' => $error_msg,
					'processed_at'  => $retry_at,
				] );

				$print_file = $wpdb->get_row( $wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}oc_print_files WHERE order_id = %d AND order_item_id = %d AND print_area_id = %d ORDER BY id DESC LIMIT 1",
					$job->order_id,
					$job->order_item_id,
					$job->print_area_id
				) );
				if ( $print_file ) {
					OC_DB::update_print_file( (int) $print_file->id, [ 'file_status' => 'pending' ] );
				}
			}
		}
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
				if ( (int) $q->print_area_id === (int) $file->print_area_id ) {
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
}
