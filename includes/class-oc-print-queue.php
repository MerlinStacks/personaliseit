<?php
defined( 'ABSPATH' ) || exit;

class OC_Print_Queue {

	private const MAX_ATTEMPTS = 3;
	private const RETRY_BACKOFF_SECONDS = 300;
	private const BATCH_SIZE = 5;
	private const STALE_PROCESSING_SECONDS = 900;
	private const DEFERRED_ENQUEUE_RECOVERY_SECONDS = 900;
	private const MAX_AREA_DATA_BYTES = 2 * 1024 * 1024;
	private const COMPLETE_MARKER_PREFIX = 'oc_print_generated_emitted_';
	private const FAILURE_MARKER_PREFIX = 'oc_print_failure_emitted_';

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

	public function enqueue( int $order_id, int $item_id, int $print_area_id, array $area_data, string $print_method, int $print_file_id = 0, string $area_source = 'unknown', int $row_index = 0, bool $defer_processing = false ): int {
		global $wpdb;
		if ( ! OC_DB::print_pipeline_available() ) {
			return 0;
		}

		$encoded = wp_json_encode( $area_data );
		if ( false === $encoded || JSON_ERROR_NONE !== json_last_error() ) {
			$this->handle_enqueue_failure(
				$order_id,
				$item_id,
				$print_area_id,
				$area_source,
				$row_index,
				$print_file_id,
				$area_data,
				'Queue payload JSON encoding failed: ' . json_last_error_msg()
			);
			return 0;
		}

		if ( strlen( $encoded ) > self::MAX_AREA_DATA_BYTES ) {
			$this->handle_enqueue_failure(
				$order_id,
				$item_id,
				$print_area_id,
				$area_source,
				$row_index,
				$print_file_id,
				$area_data,
				sprintf( 'Queue payload exceeds the %d-byte aggregate limit.', self::MAX_AREA_DATA_BYTES )
			);
			return 0;
		}

		$table = $wpdb->prefix . 'oc_print_queue';
		if ( $print_file_id > 0 ) {
			$existing = $wpdb->get_row( $wpdb->prepare(
				"SELECT id, status FROM {$table} WHERE print_file_id = %d LIMIT 1",
				$print_file_id
			) );
			if ( $existing ) {
				if ( ! $defer_processing && 'pending' === (string) $existing->status ) {
					$this->schedule_processing();
				}
				return (int) $existing->id;
			}
		}

		$result = $wpdb->insert(
			$table,
			[
				'print_file_id'  => $print_file_id > 0 ? $print_file_id : null,
				'order_id'       => $order_id,
				'order_item_id'  => $item_id,
				'print_area_id'  => $print_area_id,
				'area_source'    => $area_source,
				'row_index'      => $row_index,
				'area_data'      => $encoded,
				'print_method'   => $print_method,
				'status'         => 'pending',
				'processed_at'   => $defer_processing ? gmdate( 'Y-m-d H:i:s', time() + self::DEFERRED_ENQUEUE_RECOVERY_SECONDS ) : null,
			]
		);

		if ( false === $result ) {
			$db_error = (string) $wpdb->last_error;
			$existing = $print_file_id > 0 ? $wpdb->get_row( $wpdb->prepare(
				"SELECT id, status FROM {$table} WHERE print_file_id = %d LIMIT 1",
				$print_file_id
			) ) : null;
			if ( $existing ) {
				if ( ! $defer_processing && 'pending' === (string) $existing->status ) {
					$this->schedule_processing();
				}
				return (int) $existing->id;
			}

			$this->handle_enqueue_failure(
				$order_id,
				$item_id,
				$print_area_id,
				$area_source,
				$row_index,
				$print_file_id,
				$area_data,
				'Queue database insert failed' . ( '' !== $db_error ? ': ' . $db_error : '.' )
			);
			return 0;
		}

		$queue_id = (int) $wpdb->insert_id;
		if ( $queue_id <= 0 ) {
			$this->handle_enqueue_failure(
				$order_id,
				$item_id,
				$print_area_id,
				$area_source,
				$row_index,
				$print_file_id,
				$area_data,
				'Queue database insert did not return a row ID.'
			);
			return 0;
		}

		delete_option( self::COMPLETE_MARKER_PREFIX . $order_id );
		delete_option( self::FAILURE_MARKER_PREFIX . $order_id );
		if ( ! $defer_processing ) {
			$this->schedule_processing();
		}

		return $queue_id;
	}

	/** Release a checkout's exact staged jobs, then emit failure if none remain active. */
	public function finalise_enqueue_batch( \WC_Order $order, array $job_ids ): void {
		$released = OC_DB::release_deferred_queue_jobs( $job_ids );
		if ( $released > 0 ) {
			$this->schedule_processing();
		} elseif ( ! empty( $job_ids ) ) {
			OC_Logger::warning( sprintf( 'No staged print jobs could be released for order #%d; timed recovery remains scheduled.', (int) $order->get_id() ) );
		}
		$this->dispatch_order_outcome( $order );
	}

	/** Schedule a near-immediate queue run for newly inserted jobs. */
	public function schedule_processing(): bool {
		if ( ! wp_next_scheduled( 'oc_process_print_queue_now' ) ) {
			return false !== wp_schedule_single_event( time() + 1, 'oc_process_print_queue_now' );
		}

		return true;
	}

	public function process(): void {
		if ( ! OC_DB::print_pipeline_available() ) {
			return;
		}

		try {
			$this->reset_stale_processing_jobs();

			$jobs = OC_DB::get_pending_queue_jobs( self::BATCH_SIZE, self::MAX_ATTEMPTS );
			foreach ( $jobs as $job ) {
				$this->process_one( (int) $job->id );
			}
		} finally {
			if ( OC_DB::has_due_queue_jobs( self::MAX_ATTEMPTS ) ) {
				$this->schedule_processing();
			}
		}
	}

	public function process_one( int $job_id ): void {
		if ( ! OC_DB::print_pipeline_available() ) {
			return;
		}

		$job = OC_DB::claim_queue_job( $job_id, self::MAX_ATTEMPTS );

		if ( ! $job ) {
			return;
		}

		$area      = null;
		$area_data = null;
		$order     = null;

		try {
			$area_data = json_decode( (string) $job->area_data, true );

			if ( ! is_array( $area_data ) || JSON_ERROR_NONE !== json_last_error() ) {
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
				$this->dispatch_order_outcome( $order );
				return;
			}

			$area_source = (string) ( $job->area_source ?? 'unknown' );
			$row_index   = (int) ( $job->row_index ?? 0 );
			$print_file  = ! empty( $job->print_file_id ) ? OC_DB::get_print_file( (int) $job->print_file_id ) : null;
			if ( ! $print_file ) {
				$print_file = $this->get_print_file_for_area( (int) $job->order_id, (int) $job->order_item_id, (int) $job->print_area_id, $area_source, $row_index );
			}
			if ( ! $print_file ) {
				throw new \RuntimeException( 'No matching print file record found for queue job.' );
			}
			$this->assert_print_file_identity( $print_file, $job, (int) $job->print_area_id, $area_source, $row_index );

			$area = $this->area_from_snapshot( $print_file );
			if ( ! $area ) {
				$area = $this->get_print_area_for_job( (int) $job->print_area_id, $area_source );
			}

			if ( ! $area ) {
				throw new \RuntimeException( "Print area #{$job->print_area_id} not found." );
			}

			if ( 'design' === $area_source || ( 'unknown' === $area_source && isset( $area->design_id ) ) ) {
				$area = OC_Print_Generator::area_object_for_generation( $area, $area_data );
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
			if ( empty( $result['file_path'] ) || ! $this->is_ready_file_status( (string) ( $result['status'] ?? '' ) ) ) {
				throw new \RuntimeException( 'Print generation returned an invalid completion result.' );
			}

			$thumb_path = null;
			$ext = strtolower( pathinfo( $result['file_path'], PATHINFO_EXTENSION ) );
			if ( 'pdf' === $ext && file_exists( $result['file_path'] ) ) {
				$thumb_path = pathinfo( $result['file_path'], PATHINFO_DIRNAME ) . '/'
					. pathinfo( $result['file_path'], PATHINFO_FILENAME ) . '-thumb.png';

				if ( ! OC_Preview_Generator::from_pdf( $result['file_path'], $thumb_path ) ) {
					$thumb_path = null;
				}
			}

			$committed = OC_DB::complete_queue_job( $job_id, (int) $job->attempts, [
				[
					'id'              => (int) $print_file->id,
					'expected_status' => (string) $print_file->file_status,
					'data'            => [
						'file_path'      => $result['file_path'],
						'file_status'    => $result['status'],
						'thumbnail_path' => $thumb_path,
						'generated_at'   => $now,
						'expires_at'     => $expires_at,
					],
				],
			] );
			if ( ! $committed ) {
				throw new \RuntimeException( 'Generated output could not be committed because the queue state changed.' );
			}

			OC_Logger::info( "Print file generated via queue: job #{$job_id}, file #{$print_file->id}" );

		} catch ( \Throwable $e ) {
			$terminal = $this->record_job_failure( $job, $area_data, $e );
			if ( $terminal ) {
				if ( ! $order instanceof \WC_Order ) {
					$order = wc_get_order( (int) $job->order_id );
				}
				if ( $order instanceof \WC_Order ) {
					if ( ! is_object( $area ) ) {
						$area = (object) [
							'area_key'     => '',
							'print_method' => (string) $job->print_method,
						];
					}
					$this->dispatch_action_safely( 'oc_print_file_failed', [ $order, (int) $job->order_item_id, $area, $e ] );
					$this->dispatch_order_outcome( $order );
				}
			}
			return;
		}

		$this->dispatch_order_outcome( $order );
	}

	private function process_combined( int $job_id, object $job, \WC_Order $order, array $area_data ): void {
		$entries = $area_data['__combined_print_areas'];
		if ( empty( $entries ) || ! is_array( $entries ) ) {
			throw new \RuntimeException( 'Invalid combined print area payload.' );
		}
		$this->validate_combined_entries( $entries, $job );

		$areas       = [];
		$print_files = [];
		foreach ( $entries as $entry ) {
			if ( ! is_array( $entry ) || empty( $entry['areaId'] ) || ! is_array( $entry['areaData'] ?? null ) ) {
				throw new \RuntimeException( 'Combined print job contains an invalid area entry.' );
			}

			$area_id     = (int) $entry['areaId'];
			$area_source = (string) $entry['areaSource'];
			$row_index   = (int) $entry['rowIndex'];
			$print_file  = OC_DB::get_print_file( (int) $entry['printFileId'] );
			if ( ! $print_file ) {
				throw new \RuntimeException( "No matching print file record found for combined print area #{$area_id}." );
			}
			$this->assert_print_file_identity( $print_file, $job, $area_id, $area_source, $row_index );

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

			$areas[] = [
				'area'      => $area,
				'area_data' => $entry['areaData'],
			];
			$print_files[ (int) $print_file->id ] = $print_file;
		}

		if ( empty( $areas ) || empty( $print_files ) ) {
			throw new \RuntimeException( 'Combined print job did not contain any usable print areas.' );
		}
		$print_files = array_values( $print_files );

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
		if ( empty( $result['file_path'] ) || ! $this->is_ready_file_status( (string) ( $result['status'] ?? '' ) ) ) {
			throw new \RuntimeException( 'Combined print generation returned an invalid completion result.' );
		}
		$thumb_path = null;
		$ext = strtolower( pathinfo( $result['file_path'], PATHINFO_EXTENSION ) );
		if ( 'pdf' === $ext && file_exists( $result['file_path'] ) ) {
			$thumb_path = pathinfo( $result['file_path'], PATHINFO_DIRNAME ) . '/'
				. pathinfo( $result['file_path'], PATHINFO_FILENAME ) . '-thumb.png';

			if ( ! OC_Preview_Generator::from_pdf( $result['file_path'], $thumb_path ) ) {
				$thumb_path = null;
			}
		}

		$file_updates = [];
		foreach ( $print_files as $print_file ) {
			$file_updates[] = [
				'id'              => (int) $print_file->id,
				'expected_status' => (string) $print_file->file_status,
				'data'            => [
					'file_path'      => $result['file_path'],
					'file_status'    => $result['status'],
					'thumbnail_path' => $thumb_path,
					'generated_at'   => $now,
					'expires_at'     => $expires_at,
				],
			];
		}

		if ( ! OC_DB::complete_queue_job( $job_id, (int) $job->attempts, $file_updates ) ) {
			throw new \RuntimeException( 'Combined generated output could not be committed because the queue state changed.' );
		}

		OC_Logger::info( "Combined print file generated via queue: job #{$job_id}" );
	}

	/** Reject malformed or aliased combined identities before loading any files. */
	private function validate_combined_entries( array $entries, object $job ): void {
		$seen_file_ids = [];
		$seen_areas    = [];
		$first_file_id = 0;

		foreach ( $entries as $entry ) {
			if (
				! is_array( $entry )
				|| ! is_numeric( $entry['printFileId'] ?? null )
				|| ! is_numeric( $entry['areaId'] ?? null )
				|| ! is_numeric( $entry['rowIndex'] ?? null )
				|| ! is_array( $entry['areaData'] ?? null )
			) {
				throw new \RuntimeException( 'Combined print job contains an invalid area entry.' );
			}

			$file_id     = (int) $entry['printFileId'];
			$area_id     = (int) $entry['areaId'];
			$row_index   = (int) $entry['rowIndex'];
			$area_source = (string) ( $entry['areaSource'] ?? '' );
			if (
				$file_id <= 0
				|| $area_id <= 0
				|| $row_index < 0
				|| (float) $entry['printFileId'] !== (float) $file_id
				|| (float) $entry['areaId'] !== (float) $area_id
				|| (float) $entry['rowIndex'] !== (float) $row_index
				|| ! in_array( $area_source, [ 'design', 'legacy', 'unknown' ], true )
			) {
				throw new \RuntimeException( 'Combined print job contains an invalid identity.' );
			}

			$area_identity = $area_source . ':' . $area_id . ':' . $row_index;
			if ( isset( $seen_file_ids[ $file_id ] ) || isset( $seen_areas[ $area_identity ] ) ) {
				throw new \RuntimeException( 'Combined print job contains duplicate file or area identities.' );
			}

			$seen_file_ids[ $file_id ]   = true;
			$seen_areas[ $area_identity ] = true;
			$first_file_id                = 0 === $first_file_id ? $file_id : $first_file_id;
		}

		if ( $first_file_id <= 0 || (int) ( $job->print_file_id ?? 0 ) !== $first_file_id ) {
			throw new \RuntimeException( 'Combined print job is not attached to its first file identity.' );
		}
	}

	/** Ensure a queue payload cannot redirect generation into another file row. */
	private function assert_print_file_identity( object $print_file, object $job, int $area_id, string $area_source, int $row_index ): void {
		if (
			(int) ( $print_file->order_id ?? 0 ) !== (int) $job->order_id
			|| (int) ( $print_file->order_item_id ?? 0 ) !== (int) $job->order_item_id
			|| (int) ( $print_file->print_area_id ?? 0 ) !== $area_id
			|| (string) ( $print_file->area_source ?? '' ) !== $area_source
			|| (int) ( $print_file->row_index ?? 0 ) !== $row_index
			|| sanitize_key( (string) ( $print_file->file_type ?? '' ) ) !== sanitize_key( (string) ( $job->print_method ?? '' ) )
		) {
			throw new \RuntimeException( sprintf( 'Print file #%d does not match its immutable queue identity.', (int) ( $print_file->id ?? 0 ) ) );
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

	/** Emit one durable order-level outcome after all relevant writes have committed. */
	private function dispatch_order_outcome( \WC_Order $order ): void {
		$order_id = (int) $order->get_id();
		$state    = OC_DB::get_order_print_pipeline_state( $order_id );
		if ( 'complete' === $state ) {
			if ( add_option( self::COMPLETE_MARKER_PREFIX . $order_id, current_time( 'mysql', true ), '', false ) ) {
				$this->dispatch_action_safely( 'oc_print_files_generated', [ $order ] );
			}
			return;
		}

		if ( 'partial_failure' === $state && add_option( self::FAILURE_MARKER_PREFIX . $order_id, current_time( 'mysql', true ), '', false ) ) {
			$this->dispatch_action_safely( 'oc_print_files_generation_failed', [ $order ] );
		}
	}

	/** Keep listener exceptions outside the queue state machine. */
	private function dispatch_action_safely( string $hook, array $args ): void {
		try {
			do_action( $hook, ...$args );
		} catch ( \Throwable $e ) {
			try {
				OC_Logger::error( sprintf( 'Print queue listener for %s threw: %s', $hook, $e->getMessage() ) );
			} catch ( \Throwable $logging_error ) {
				// Queue state is already committed; logging must not alter it.
			}
		}
	}

	/** Persist a retry or terminal failure only if this worker still owns the claim. */
	private function record_job_failure( object $job, ?array $area_data, \Throwable $exception ): bool {
		$error_message = $this->truncate_error_message( $exception->getMessage() );
		OC_Logger::error( sprintf(
			'Queue job #%d failed (attempt %d): %s',
			(int) $job->id,
			(int) $job->attempts,
			$error_message
		) );

		if ( (int) $job->attempts >= self::MAX_ATTEMPTS ) {
			$failed = OC_DB::fail_queue_job(
				(int) $job->id,
				'processing',
				(int) $job->attempts,
				$error_message,
				$this->get_job_print_file_ids( $job, $area_data )
			);
			if ( ! $failed ) {
				OC_Logger::warning( sprintf( 'Queue job #%d changed state before its terminal failure could be committed.', (int) $job->id ) );
			}
			return $failed;
		}

		$retry_at = gmdate( 'Y-m-d H:i:s', time() + self::RETRY_BACKOFF_SECONDS );
		$retried  = OC_DB::transition_queue_job(
			(int) $job->id,
			'processing',
			[
				'status'        => 'pending',
				'error_message' => $error_message,
				'processed_at'  => $retry_at,
			],
			(int) $job->attempts
		);
		if ( ! $retried ) {
			OC_Logger::warning( sprintf( 'Queue job #%d changed state before its retry could be committed.', (int) $job->id ) );
		}

		return false;
	}

	/** Explicitly retry a failed job, resetting both attempts and associated file rows. */
	public function retry_job( int $job_id ): bool {
		$job = OC_DB::get_queue_job( $job_id );
		if ( ! $job || 'failed' !== (string) $job->status ) {
			return false;
		}

		$area_data = json_decode( (string) $job->area_data, true );
		$file_ids  = $this->get_job_print_file_ids( $job, is_array( $area_data ) ? $area_data : null );
		if ( ! OC_DB::retry_failed_queue_job( $job_id, (int) $job->attempts, $file_ids ) ) {
			return false;
		}

		delete_option( self::COMPLETE_MARKER_PREFIX . (int) $job->order_id );
		delete_option( self::FAILURE_MARKER_PREFIX . (int) $job->order_id );
		$this->schedule_processing();
		return true;
	}

	/** Reconcile file rows when a queue payload cannot be encoded or inserted. */
	private function handle_enqueue_failure( int $order_id, int $item_id, int $area_id, string $area_source, int $row_index, int $print_file_id, array $area_data, string $message ): void {
		$file_ids = $this->print_file_ids_from_payload( $print_file_id, $area_data );
		if ( empty( $file_ids ) ) {
			$file = $this->get_print_file_for_area( $order_id, $item_id, $area_id, $area_source, $row_index );
			if ( $file ) {
				$file_ids[] = (int) $file->id;
			}
		}

		if ( ! empty( $file_ids ) && ! OC_DB::fail_unqueued_print_files( $file_ids ) ) {
			$message .= ' Associated print file rows could not be reconciled.';
		}
		OC_Logger::error( $message );
	}

	/** Return all file IDs represented by a queue payload, including combined jobs. */
	private function print_file_ids_from_payload( int $print_file_id, array $area_data ): array {
		$file_ids = $print_file_id > 0 ? [ $print_file_id ] : [];
		$entries  = $area_data['__combined_print_areas'] ?? [];
		if ( is_array( $entries ) ) {
			foreach ( $entries as $entry ) {
				if ( is_array( $entry ) && ! empty( $entry['printFileId'] ) ) {
					$file_ids[] = (int) $entry['printFileId'];
				}
			}
		}

		return array_values( array_unique( array_filter( $file_ids ) ) );
	}

	/** Resolve file IDs from both current and legacy queue rows. */
	private function get_job_print_file_ids( object $job, ?array $area_data = null ): array {
		if ( null === $area_data ) {
			$decoded   = json_decode( (string) ( $job->area_data ?? '' ), true );
			$area_data = is_array( $decoded ) ? $decoded : [];
		}

		$file_ids = [];
		if ( OC_Print_Generator::is_combined_area_data( $area_data ) ) {
			foreach ( $area_data['__combined_print_areas'] as $entry ) {
				if (
					! is_array( $entry )
					|| ! is_numeric( $entry['printFileId'] ?? null )
					|| ! is_numeric( $entry['areaId'] ?? null )
					|| ! is_numeric( $entry['rowIndex'] ?? null )
				) {
					continue;
				}

				$file_id     = (int) $entry['printFileId'];
				$area_id     = (int) $entry['areaId'];
				$row_index   = (int) $entry['rowIndex'];
				$area_source = (string) ( $entry['areaSource'] ?? '' );
				$file         = $file_id > 0 ? OC_DB::get_print_file( $file_id ) : null;
				if (
					! $file
					|| (float) $entry['printFileId'] !== (float) $file_id
					|| (float) $entry['areaId'] !== (float) $area_id
					|| (float) $entry['rowIndex'] !== (float) $row_index
					|| ! in_array( $area_source, [ 'design', 'legacy', 'unknown' ], true )
				) {
					continue;
				}

				try {
					$this->assert_print_file_identity( $file, $job, $area_id, $area_source, $row_index );
					$file_ids[] = $file_id;
				} catch ( \RuntimeException $e ) {
					continue;
				}
			}
		} else {
			$file_id = (int) ( $job->print_file_id ?? 0 );
			$file     = $file_id > 0 ? OC_DB::get_print_file( $file_id ) : null;
			if ( $file ) {
				try {
					$this->assert_print_file_identity(
						$file,
						$job,
						(int) $job->print_area_id,
						(string) ( $job->area_source ?? 'unknown' ),
						(int) ( $job->row_index ?? 0 )
					);
					$file_ids[] = $file_id;
				} catch ( \RuntimeException $e ) {
					$file_ids = [];
				}
			}
		}

		if ( empty( $file_ids ) ) {
			$file = $this->get_print_file_for_area(
				(int) $job->order_id,
				(int) $job->order_item_id,
				(int) $job->print_area_id,
				(string) ( $job->area_source ?? 'unknown' ),
				(int) ( $job->row_index ?? 0 )
			);
			if ( $file ) {
				try {
					$this->assert_print_file_identity(
						$file,
						$job,
						(int) $job->print_area_id,
						(string) ( $job->area_source ?? 'unknown' ),
						(int) ( $job->row_index ?? 0 )
					);
					$file_ids[] = (int) $file->id;
				} catch ( \RuntimeException $e ) {
					$file_ids = [];
				}
			}
		}

		return array_values( array_unique( $file_ids ) );
	}

	private function truncate_error_message( string $message ): string {
		if ( strlen( $message ) <= 60000 ) {
			return $message;
		}

		return function_exists( 'mb_strcut' ) ? mb_strcut( $message, 0, 60000, 'UTF-8' ) : substr( $message, 0, 60000 );
	}

	private function is_ready_file_status( string $status ): bool {
		return in_array( $status, [ 'brief_ready', 'awaiting_dst_upload', 'files_ready' ], true );
	}

	/** Recover timed-out jobs, failing those that have exhausted their attempts. */
	public function reset_stale_processing_jobs(): int {
		global $wpdb;

		$cutoff = gmdate( 'Y-m-d H:i:s', time() - self::STALE_PROCESSING_SECONDS );
		$now    = current_time( 'mysql', true );
		$exhausted = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}oc_print_queue
			 WHERE attempts >= %d AND (
				(status = 'processing' AND (processed_at IS NULL OR processed_at <= %s))
				OR (status = 'pending' AND (processed_at IS NULL OR processed_at <= %s))
			 )",
			self::MAX_ATTEMPTS,
			$cutoff,
			$now
		) ) ?: [];
		$updated = 0;
		foreach ( $exhausted as $job ) {
			$message = 'processing' === (string) $job->status
				? __( 'Job failed after being stuck in processing at the maximum attempt count.', 'overcustomise' )
				: __( 'Job failed after reaching the maximum attempt count.', 'overcustomise' );
			if ( ! OC_DB::fail_queue_job(
				(int) $job->id,
				(string) $job->status,
				(int) $job->attempts,
				$message,
				$this->get_job_print_file_ids( $job )
			) ) {
				continue;
			}
			$updated++;

			$order = wc_get_order( (int) $job->order_id );
			if ( $order instanceof \WC_Order ) {
				$area = (object) [
					'area_key'     => '',
					'print_method' => (string) $job->print_method,
				];
				$this->dispatch_action_safely( 'oc_print_file_failed', [ $order, (int) $job->order_item_id, $area, new \RuntimeException( $message ) ] );
				$this->dispatch_order_outcome( $order );
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
				$area_data     = json_decode( (string) ( $q->area_data ?? '' ), true );
				$combined      = is_array( $area_data ) && OC_Print_Generator::is_combined_area_data( $area_data );
				$contains_file = false;
				if ( $combined ) {
					foreach ( $area_data['__combined_print_areas'] as $entry ) {
						if ( is_array( $entry ) && (int) ( $entry['printFileId'] ?? 0 ) === (int) $file->id ) {
							$contains_file = true;
							break;
						}
					}
				} else {
					$contains_file = (int) ( $q->print_file_id ?? 0 ) === (int) $file->id;
				}
				if ( ! $contains_file ) {
					continue;
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
