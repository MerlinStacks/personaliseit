<?php
/**
 * Print File Generator — hooks into WooCommerce order creation and dispatches
 * to the appropriate method-specific generator for each print area.
 *
 * Flow per order:
 *  1. woocommerce_checkout_order_created fires after order is persisted.
 *  2. Iterate order items — skip items without _oc_customisation meta.
 *  3. For each print area, insert a 'generating' record in oc_print_files.
 *  4. Call the appropriate type-specific generator.
 *  5. Update the record: file_path + status.
 *  6. On failure: set status back to 'pending', log the error.
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

class OC_Print_Generator {
	public const OUTPUT_LOCK_CONTENTION_CODE     = 40901;
	private const ORDER_GENERATION_RETRY_HOOK    = 'oc_retry_order_print_generation';
	private const ORDER_GENERATION_RECOVERY_HOOK = 'oc_recover_order_print_generation';
	private const ORDER_GENERATION_PENDING_META  = '_oc_print_generation_pending';

	/** @var array<int,true> Orders deferred until request data has been persisted. */
	private array $deferred_order_ids = [];

	public function register(): void {
		add_action( 'init', [ OC_Print_Base::class, 'ensure_output_storage_protected' ] );

		// Primary: order created at checkout.
		add_action( 'woocommerce_checkout_order_created', [ $this, 'generate_for_order_safely' ], 20, 1 );

		// Admin/API orders receive line items after the order object is created.
		add_action( 'woocommerce_new_order', [ $this, 'defer_order_generation' ], 30, 2 );
		add_action( 'added_order_item_meta', [ $this, 'defer_for_customisation_meta' ], 10, 4 );
		add_action( 'shutdown', [ $this, 'generate_deferred_orders' ], 20 );
		add_action( self::ORDER_GENERATION_RETRY_HOOK, [ $this, 'retry_order_generation' ], 10, 1 );
		add_action( self::ORDER_GENERATION_RECOVERY_HOOK, [ $this, 'recover_pending_order_generation' ] );
		add_action( 'init', [ $this, 'ensure_order_generation_recovery_schedule' ] );

		// Admin handlers.
		add_action( 'admin_init', [ $this, 'handle_admin_download' ] );
		add_action( 'admin_init', [ $this, 'handle_admin_regenerate' ] );
		add_action( 'admin_init', [ $this, 'handle_admin_generate_missing' ] );
		add_action( 'admin_init', [ $this, 'handle_admin_process_queue' ] );
		add_action( 'admin_notices', [ $this, 'render_admin_notices' ] );
		add_action( 'wp_ajax_oc_serve_print_thumbnail', [ $this, 'handle_admin_thumbnail' ] );
	}

	// -------------------------------------------------------------------------
	// Generation
	// -------------------------------------------------------------------------

	/**
	 * Regenerate a single print file by its DB record ID.
	 * Called by the REST API regenerate-files endpoint.
	 *
	 * @param  int   $file_id  ID in oc_print_files.
	 * @return array{file_path:string,status:string,warning?:string}
	 * @throws \RuntimeException on failure.
	 */
	public function regenerate( int $file_id ): array {
		$record = OC_DB::get_print_file( $file_id );

		if ( ! $record ) {
			throw new \RuntimeException( "Print file record #{$file_id} not found." );
		}
		$queue_status = OC_Print_Queue::instance()->get_status( $file_id );
		if ( ! empty( $queue_status['in_queue'] ) || ! empty( $queue_status['is_processing'] ) || ! empty( $queue_status['has_failed_job'] ) ) {
			throw new \RuntimeException( __( 'This print file is managed by an active or failed queue job. Process or retry that queue job instead.', 'overcustomise' ) );
		}

		$order = wc_get_order( (int) $record->order_id );
		if ( ! $order instanceof \WC_Order ) {
			throw new \RuntimeException( "Order #{$record->order_id} not found." );
		}

		// Find the order item.
		$target_item = $order->get_item( (int) $record->order_item_id );

		if ( ! $target_item ) {
			throw new \RuntimeException( "Order item #{$record->order_item_id} not found in order." );
		}

		$customisation = $target_item->get_meta( '_oc_customisation', true );
		if ( ! is_array( $customisation ) ) {
			throw new \RuntimeException( 'No customisation data on order item.' );
		}

		// Prefer immutable file/spec snapshots; current design rows are legacy-only fallback.
		global $wpdb;
		$area_snapshot   = json_decode( (string) ( $record->area_snapshot ?? '' ), true );
		$area            = is_array( $area_snapshot ) && ! empty( $area_snapshot ) ? (object) $area_snapshot : null;
		$area_source     = (string) ( $record->area_source ?? 'unknown' );
		$is_v2_area      = 'design' === $area_source;
		$has_render_spec = array_key_exists( 'renderSpec', $customisation );
		$stored_spec     = is_array( $customisation['renderSpec'] ?? null ) ? $customisation['renderSpec'] : [];
		if ( $has_render_spec && empty( $stored_spec ) ) {
			throw new \RuntimeException( 'The order contains an invalid stored render specification.' );
		}
		$spec_area_data = $is_v2_area && ! empty( $stored_spec )
			? OC_Render_Spec::area_from_spec( $stored_spec, (int) $record->print_area_id )
			: [];
		if ( $is_v2_area && $has_render_spec && empty( $spec_area_data ) ) {
			throw new \RuntimeException( 'The print file area is not present in the authoritative stored render specification.' );
		}
		if ( ! $area && ! empty( $spec_area_data['renderSpecArea'] ) ) {
			$area = OC_Render_Spec::area_object( $spec_area_data['renderSpecArea'], absint( $stored_spec['designId'] ?? 0 ) );
		}

		if ( ! $area && ( 'legacy' === $area_source || ( 'design' === $area_source && ! $has_render_spec ) ) ) {
			$table = 'design' === $area_source ? 'oc_design_print_areas' : 'oc_print_areas';
			$area  = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}{$table} WHERE id = %d LIMIT 1",
					$record->print_area_id
				)
			);
		}

		if ( ! $area ) {
			throw new \RuntimeException( "Print area #{$record->print_area_id} has no unambiguous source or stored snapshot." );
		}

		$persisted_area_data = self::persisted_area_data( $record );
		if ( is_array( $persisted_area_data ) ) {
			$area_data = $persisted_area_data;
		} elseif ( $is_v2_area && ! empty( $spec_area_data ) ) {
			$area_data = $spec_area_data;
		} elseif ( $is_v2_area ) {
			$area_data = self::build_v2_area_data( (int) $area->design_id, (int) $area->id, $customisation );
		} else {
			$area_data = $customisation[ $area->area_key ] ?? null;
		}

		if ( ! is_array( $area_data ) ) {
			throw new \RuntimeException( "No customisation data for area '{$area->area_key}'." );
		}
		if ( $is_v2_area ) {
			$area = self::area_object_for_generation( $area, $area_data );
		}

		$combined_entries = self::persisted_combined_entries( $record );
		if ( empty( $combined_entries ) ) {
			$combined_entries = self::combined_entries_for_regeneration( $record, $customisation, $area, $is_v2_area );
		}
		if ( count( $combined_entries ) > 1 && self::supports_combined_print_file( (string) $area->print_method ) ) {
			return $this->regenerate_combined( $record, $order, $combined_entries );
		}

		// Update retention dates.
		$retention_days = (int) OC_Admin_Settings::get( 'file_retention_days' ) ?: 90;
		$now            = current_time( 'mysql', true );
		$expires_at     = gmdate( 'Y-m-d H:i:s', strtotime( "+{$retention_days} days", strtotime( $now ) ) );

		$warning = self::regeneration_snapshot_warning( $area, $area_data );
		$result  = self::with_output_lock(
			(int) $record->order_id,
			(int) $record->order_item_id,
			static function () use ( $record, $order, $area, $area_data, $file_id, $now, $expires_at ): array {
				return self::generate_with_backup(
					(string) ( $record->file_path ?? '' ),
					static fn (): array => self::generate_for_area( $order, (int) $record->order_item_id, $area, $area_data ),
					static function ( array &$result ) use ( $file_id, $now, $expires_at ): void {
						$result['file_path']      = self::finalise_generated_output( $result['file_path'], $file_id );
						$thumb_path               = self::maybe_generate_thumbnail( $result['file_path'] );
						$result['thumbnail_path'] = $thumb_path;
						if ( ! OC_DB::update_print_file(
							$file_id,
							[
								'file_path'      => $result['file_path'],
								'file_status'    => $result['status'],
								'thumbnail_path' => $thumb_path,
								'generated_at'   => $now,
								'expires_at'     => $expires_at,
							]
						) ) {
							throw new \RuntimeException( __( 'The regenerated print file could not be committed.', 'overcustomise' ) );
						}
					},
					[ (string) ( $record->thumbnail_path ?? '' ) ]
				);
			}
		);
		if ( '' !== $warning ) {
			$result['warning'] = $warning;
		}

		return $result;
	}

	/**
	 * @param array<int,array{area:object,area_data:array}> $combined_entries
	 * @return array{file_path:string,status:string}
	 */
	private function regenerate_combined( object $record, \WC_Order $order, array $combined_entries ): array {
		$area_ids = array_map(
			static fn ( array $entry ): int => (int) ( $entry['area']->id ?? 0 ),
			$combined_entries
		);
		$area_ids = array_values( array_filter( array_unique( $area_ids ) ) );

		$records = self::get_print_file_records_for_areas(
			(int) $record->order_id,
			(int) $record->order_item_id,
			$area_ids,
			(string) ( $record->area_source ?? 'unknown' ),
			(int) ( $record->row_index ?? 0 )
		);

		if ( count( $records ) !== count( $area_ids ) ) {
			throw new \RuntimeException( 'Not every combined print area has a matching file record.' );
		}

		$retention_days = (int) OC_Admin_Settings::get( 'file_retention_days' ) ?: 90;
		$now            = current_time( 'mysql', true );
		$expires_at     = gmdate( 'Y-m-d H:i:s', strtotime( "+{$retention_days} days", strtotime( $now ) ) );

		$result = self::with_output_lock(
			(int) $record->order_id,
			(int) $record->order_item_id,
			static function () use ( $record, $order, $combined_entries, $records, $now, $expires_at ): array {
				return self::generate_with_backup(
					(string) ( $record->file_path ?? '' ),
					static fn (): array => self::generate_for_areas( $order, (int) $record->order_item_id, (string) $record->file_type, $combined_entries ),
					static function ( array &$result ) use ( $records, $now, $expires_at ): void {
						$result['file_path']      = self::finalise_generated_output( $result['file_path'], (int) $records[0]->id );
						$thumb_path               = self::maybe_generate_thumbnail( $result['file_path'] );
						$result['thumbnail_path'] = $thumb_path;
						if ( ! OC_DB::update_print_files_atomically(
							array_map( static fn( object $print_file ): int => (int) $print_file->id, $records ),
							[
								'file_path'      => $result['file_path'],
								'file_status'    => $result['status'],
								'thumbnail_path' => $thumb_path,
								'generated_at'   => $now,
								'expires_at'     => $expires_at,
							]
						) ) {
							throw new \RuntimeException( __( 'The combined regenerated print files could not be committed.', 'overcustomise' ) );
						}
					},
					array_merge(
						array_map( static fn( object $print_file ): string => (string) ( $print_file->file_path ?? '' ), $records ),
						array_map( static fn( object $print_file ): string => (string) ( $print_file->thumbnail_path ?? '' ), $records )
					)
				);
			}
		);

		return $result;
	}

	/**
	 * Generate a thumbnail for a PDF print file.
	 *
	 * @param string $file_path Absolute path to the generated file.
	 * @return string|null      Absolute path to thumbnail, or null.
	 */
	private static function maybe_generate_thumbnail( string $file_path ): ?string {
		$ext = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );

		if ( 'pdf' !== $ext || ! file_exists( $file_path ) ) {
			return null;
		}

		$thumb_path = pathinfo( $file_path, PATHINFO_DIRNAME ) . '/'
			. pathinfo( $file_path, PATHINFO_FILENAME ) . '-thumb.png';

		if ( ! OC_Preview_Generator::from_pdf( $file_path, $thumb_path ) ) {
			return null;
		}
		@touch( $thumb_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		return $thumb_path;
	}

	/** Move a completed generator output to a stable identity-specific path. */
	public static function finalise_generated_output( string $file_path, int $print_file_id ): string {
		if ( $print_file_id <= 0 || ! is_file( $file_path ) || filesize( $file_path ) <= 0 ) {
			throw new \RuntimeException( 'Print generator did not produce a valid output file.' );
		}

		$directory = pathinfo( $file_path, PATHINFO_DIRNAME );
		$filename  = pathinfo( $file_path, PATHINFO_FILENAME );
		$extension = pathinfo( $file_path, PATHINFO_EXTENSION );
		$suffix    = '' !== $extension ? '.' . $extension : '';
		$target    = $directory . '/' . preg_replace( '/-f\d+$/', '', $filename ) . '-f' . $print_file_id . $suffix;

		if ( $target === $file_path ) {
			@touch( $target ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return $target;
		}

		if ( ! @rename( $file_path, $target ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			throw new \RuntimeException( 'Could not atomically install generated print output.' );
		}

		return $target;
	}

	/** Serialize driver writes for one order item because legacy drivers use position-based temporary names. */
	public static function with_output_lock( int $order_id, int $item_id, callable $generate ): array {
		global $wpdb;
		$lock_name    = 'oc_print_' . $order_id . '_' . $item_id;
		$wait_seconds = apply_filters( 'oc_print_output_lock_wait_seconds', 5, $order_id, $item_id );
		$wait_seconds = is_numeric( $wait_seconds ) ? max( 0, min( 30, (int) $wait_seconds ) ) : 5;
		$acquired     = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', $lock_name, $wait_seconds ) );
		if ( 1 !== $acquired ) {
			throw new \RuntimeException( 'Print output is currently locked by another worker.', self::OUTPUT_LOCK_CONTENTION_CODE );
		}

		try {
			return $generate();
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
		}
	}

	/** Remove uncommitted worker output only when no print-file row references it. */
	public static function remove_uncommitted_artifacts( array $paths ): void {
		global $wpdb;
		foreach ( array_unique( array_filter( $paths, 'is_string' ) ) as $path ) {
			$real = self::resolve_print_storage_path( $path );
			if ( null === $real ) {
				continue;
			}

			$references = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}oc_print_files WHERE file_path = %s OR thumbnail_path = %s",
					$path,
					$path
				)
			);
			if ( 0 === $references && ! @unlink( $real ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				OC_Logger::warning( 'Could not remove uncommitted print artifact: ' . basename( $real ) );
			}
		}
	}

	/** Preserve existing output artifacts until their replacements have been fully committed. */
	private static function generate_with_backup( string $old_path, callable $generate, ?callable $commit = null, array $related_paths = [] ): array {
		$paths   = array_values(
			array_unique(
				array_filter(
					array_merge( [ $old_path ], $related_paths ),
					static fn( mixed $path ): bool => is_string( $path ) && '' !== $path
				)
			)
		);
		$backups = [];
		foreach ( $paths as $path ) {
			if ( ! is_file( $path ) ) {
				continue;
			}

			$backup = $path . '.oc-backup-' . wp_generate_uuid4();
			if ( ! copy( $path, $backup ) ) {
				foreach ( $backups as $created_backup ) {
					@unlink( $created_backup ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				}
				throw new \RuntimeException( 'Could not protect the existing print output before regeneration.' );
			}
			$backups[ $path ] = $backup;
		}

		$result = null;
		try {
			$result = $generate();
			if ( ! is_array( $result ) || empty( $result['file_path'] ) || ! is_file( $result['file_path'] ) || filesize( $result['file_path'] ) <= 0 ) {
				throw new \RuntimeException( 'Print generator did not produce a valid replacement file.' );
			}

			if ( null !== $commit ) {
				$commit( $result );
				self::delete_superseded_artifacts( $paths, $result );
			}

			foreach ( $backups as $backup ) {
				if ( file_exists( $backup ) ) {
					@unlink( $backup ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				}
			}

			return $result;
		} catch ( \Throwable $e ) {
			foreach ( [ 'file_path', 'thumbnail_path' ] as $result_key ) {
				$result_path = is_array( $result ) ? (string) ( $result[ $result_key ] ?? '' ) : '';
				if ( '' !== $result_path && ! isset( $backups[ $result_path ] ) && is_file( $result_path ) ) {
					@unlink( $result_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				}
			}

			$restore_failed = false;
			foreach ( $backups as $path => $backup ) {
				if ( ! file_exists( $backup ) ) {
					continue;
				}
				$restored = @rename( $backup, $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				if ( ! $restored && @copy( $backup, $path ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
					@unlink( $backup ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
					$restored = true;
				}
				if ( ! $restored ) {
					$restore_failed = true;
				}
			}
			if ( $restore_failed ) {
				throw new \RuntimeException( 'Could not restore the previous print output after regeneration failed.', 0, $e );
			}
			throw $e;
		}
	}

	/** Delete committed predecessors only after no print-file row references them. */
	private static function delete_superseded_artifacts( array $paths, array $result ): void {
		$current_paths      = array_values(
			array_filter(
				[
					(string) ( $result['file_path'] ?? '' ),
					(string) ( $result['thumbnail_path'] ?? '' ),
				]
			)
		);
		$current_real_paths = [];
		foreach ( $current_paths as $current_path ) {
			$current_real = self::resolve_print_storage_path( $current_path );
			if ( null !== $current_real ) {
				$current_real_paths[ $current_real ] = true;
			}
		}

		global $wpdb;
		foreach ( $paths as $path ) {
			$real = self::resolve_print_storage_path( (string) $path );
			if ( null === $real || isset( $current_real_paths[ $real ] ) ) {
				continue;
			}

			try {
				$references = (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM {$wpdb->prefix}oc_print_files WHERE file_path = %s OR thumbnail_path = %s",
						(string) $path,
						(string) $path
					)
				);
				if ( 0 === $references && ! @unlink( $real ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
					OC_Logger::warning( 'Could not remove superseded print artifact: ' . basename( $real ) );
				}
			} catch ( \Throwable $e ) {
				OC_Logger::warning( 'Could not verify a superseded print artifact: ' . $e->getMessage() );
			}
		}
	}

	/** Generate print files for all customised items in a new order. */
	public function generate_for_order( \WC_Order $order ): void {
		if ( ! OC_DB::print_pipeline_available() ) {
			self::mark_order_generation_pending( $order );
			self::schedule_order_generation_retry( (int) $order->get_id() );
			return;
		}

		$retention_days = (int) OC_Admin_Settings::get( 'file_retention_days' ) ?: 90;
		$now            = current_time( 'mysql', true );
		$expires_at     = gmdate( 'Y-m-d H:i:s', strtotime( "+{$retention_days} days", strtotime( $now ) ) );

		$queued_count = 0;
		$failed_count = 0;
		$queue_ids    = [];

		foreach ( $order->get_items() as $item_id => $item ) {
			/** @var \WC_Order_Item_Product $item */
			$customisation = $item->get_meta( '_oc_customisation', true );

			if ( empty( $customisation ) || ! is_array( $customisation ) ) {
				continue;
			}

			// v2 (design system) ────────────────────────────────────────────
			if ( isset( $customisation['v'] ) && 2 === (int) $customisation['v'] ) {
				$design_id = (int) ( $customisation['designId'] ?? $item->get_meta( '_oc_design_id', true ) );
				if ( ! $design_id ) {
					continue;
				}

				$vdp_result = $this->queue_vdp_files( $order, (int) $item_id, $design_id, $customisation, $now, $expires_at );
				if ( is_array( $vdp_result ) ) {
					$queued_count += $vdp_result['queued'];
					$failed_count += $vdp_result['failed'];
					$queue_ids     = array_merge( $queue_ids, $vdp_result['queue_ids'] );
					continue;
				}

				$areas      = self::v2_print_areas( $design_id, $customisation );
				$print_jobs = [];
				foreach ( $areas as $entry ) {
					$area      = $entry['area'];
					$area_data = $entry['area_data'];
					if ( ! self::area_has_printable_data( $area_data ) ) {
						$order->add_order_note( sprintf( __( 'OverCustomise skipped print area "%s": no printable customer data was found.', 'overcustomise' ), $area->label ?: $area->area_key ) );
						continue;
					}
					$print_file_id = OC_DB::insert_print_file(
						[
							'order_id'      => $order->get_id(),
							'order_item_id' => (int) $item_id,
							'print_area_id' => (int) $area->id,
							'area_source'   => 'design',
							'row_index'     => 0,
							'row_key'       => '',
							'area_snapshot' => wp_json_encode( (array) $area ),
							'file_type'     => $area->print_method,
							'file_status'   => 'pending',
							'generated_at'  => $now,
							'expires_at'    => $expires_at,
						]
					);
					if ( ! $print_file_id ) {
						++$failed_count;
						continue;
					}
					if ( self::queue_job_exists_for_file( $print_file_id ) ) {
						continue;
					}

					$print_jobs[] = [
						'print_file_id' => $print_file_id,
						'area_id'       => (int) $area->id,
						'area_source'   => 'design',
						'row_index'     => 0,
						'area_snapshot' => (array) $area,
						'area_data'     => $area_data,
						'print_method'  => (string) $area->print_method,
					];
				}
				$grouped_result = self::enqueue_print_jobs_grouped( (int) $order->get_id(), (int) $item_id, $print_jobs );
				$queued_count  += $grouped_result['queued'];
				$failed_count  += $grouped_result['failed'];
				$queue_ids      = array_merge( $queue_ids, $grouped_result['queue_ids'] );
				continue;
			}

			// v1 (legacy) ────────────────────────────────────────────────────
			$product_id = (int) $item->get_product_id();
			$config     = OC_DB::get_config_by_product( $product_id );

			if ( ! $config ) {
				continue;
			}

			$areas      = OC_DB::get_print_areas( (int) $config->id );
			$print_jobs = [];

			foreach ( $areas as $area ) {
				$area_data = $customisation[ $area->area_key ] ?? null;

				if ( null === $area_data || ! is_array( $area_data ) ) {
					continue;
				}
				$print_file_id = OC_DB::insert_print_file(
					[
						'order_id'      => $order->get_id(),
						'order_item_id' => (int) $item_id,
						'print_area_id' => (int) $area->id,
						'area_source'   => 'legacy',
						'row_index'     => 0,
						'row_key'       => '',
						'area_snapshot' => wp_json_encode( (array) $area ),
						'file_type'     => $area->print_method,
						'file_status'   => 'pending',
						'generated_at'  => $now,
						'expires_at'    => $expires_at,
					]
				);
				if ( ! $print_file_id ) {
					++$failed_count;
					continue;
				}
				if ( self::queue_job_exists_for_file( $print_file_id ) ) {
					continue;
				}

				$print_jobs[] = [
					'print_file_id' => $print_file_id,
					'area_id'       => (int) $area->id,
					'area_source'   => 'legacy',
					'row_index'     => 0,
					'area_snapshot' => (array) $area,
					'area_data'     => $area_data,
					'print_method'  => (string) $area->print_method,
				];
			}
			$grouped_result = self::enqueue_print_jobs_grouped( (int) $order->get_id(), (int) $item_id, $print_jobs );
			$queued_count  += $grouped_result['queued'];
			$failed_count  += $grouped_result['failed'];
			$queue_ids      = array_merge( $queue_ids, $grouped_result['queue_ids'] );
		}
		if ( ! empty( $queue_ids ) || $failed_count > 0 ) {
			OC_Print_Queue::instance()->finalise_enqueue_batch( $order, $queue_ids );
		}

		if ( $queued_count > 0 ) {
			$order->add_order_note( sprintf( __( 'OverCustomise queued %d print file(s) for generation.', 'overcustomise' ), $queued_count ) );
		}
		if ( $failed_count > 0 ) {
			$order->add_order_note( sprintf( __( 'OverCustomise could not queue %d print file(s). Review the print queue and error log.', 'overcustomise' ), $failed_count ) );
			self::mark_order_generation_pending( $order );
			self::schedule_order_generation_retry( (int) $order->get_id() );
		} else {
			self::clear_order_generation_pending( $order );
		}

		unset( $this->deferred_order_ids[ (int) $order->get_id() ] );
	}

	/** Keep production failures from aborting checkout and retain a durable retry marker. */
	public function generate_for_order_safely( \WC_Order $order ): void {
		try {
			$this->generate_for_order( $order );
		} catch ( \Throwable $e ) {
			self::retain_failed_order_generation( $order, $e, 'Print generation failed' );
		}
	}

	/** Expand a valid VDP template into row-specific queue jobs, or return null for standard fallback. */
	private function queue_vdp_files( \WC_Order $order, int $item_id, int $design_id, array $customisation, string $now, string $expires_at ): ?array {
		$vdp = new OC_VDP();
		if ( ! $vdp->is_enabled( $design_id ) ) {
			return null;
		}

		$template = $vdp->get_template( $design_id );
		if ( ! is_array( $template ) || empty( $template['fields'] ) || empty( $template['csv_file_path'] ) || ! is_readable( $template['csv_file_path'] ) ) {
			$order->add_order_note( __( 'OverCustomise VDP was enabled but its template was unavailable. A standard single-output print job was queued instead.', 'overcustomise' ) );
			return null;
		}

		$csv_data = $vdp->parse_csv( (string) $template['csv_file_path'] );
		$headers  = is_array( $csv_data['headers'] ?? null ) ? $csv_data['headers'] : [];
		$rows     = is_array( $csv_data['rows'] ?? null ) ? $csv_data['rows'] : [];
		if ( ! empty( $csv_data['error'] ) || empty( $headers ) || empty( $rows ) || count( $template['fields'] ) !== count( $headers ) ) {
			OC_Logger::warning( 'VDP fallback for design #' . $design_id . ': ' . (string) ( $csv_data['error'] ?? 'template fields do not match CSV headers' ) );
			$order->add_order_note( __( 'OverCustomise VDP data was invalid or empty. A standard single-output print job was queued instead.', 'overcustomise' ) );
			return null;
		}

		$stored_spec = is_array( $customisation['renderSpec'] ?? null ) ? $customisation['renderSpec'] : null;
		if ( null === $stored_spec ) {
			$order->add_order_note( __( 'OverCustomise VDP requires the order-time render snapshot. A standard single-output print job was queued instead.', 'overcustomise' ) );
			return null;
		}
		$stored_design_id = absint( $stored_spec['designId'] ?? 0 );
		if ( $stored_design_id > 0 && $stored_design_id !== $design_id ) {
			$order->add_order_note( __( 'OverCustomise VDP render snapshot does not match the order design. A standard single-output print job was queued instead.', 'overcustomise' ) );
			return null;
		}
		try {
			$snapshot_areas = OC_Render_Spec::print_areas( $stored_spec );
		} catch ( \RuntimeException $e ) {
			OC_Logger::warning( 'VDP fallback for design #' . $design_id . ': ' . $e->getMessage() );
			return null;
		}
		if ( empty( $snapshot_areas ) ) {
			return [
				'queued'    => 0,
				'failed'    => 0,
				'queue_ids' => [],
			];
		}

		$layers_by_id = [];
		foreach ( $stored_spec['areas'] as $spec_area ) {
			foreach ( is_array( $spec_area['layers'] ?? null ) ? $spec_area['layers'] : [] as $spec_layer ) {
				if ( ! is_array( $spec_layer ) ) {
					continue;
				}
				$layer_id = absint( $spec_layer['id'] ?? 0 );
				if ( $layer_id <= 0 || isset( $layers_by_id[ $layer_id ] ) ) {
					$order->add_order_note( __( 'OverCustomise VDP mappings are ambiguous in the stored render snapshot. A standard single-output print job was queued instead.', 'overcustomise' ) );
					return null;
				}
				$layers_by_id[ $layer_id ] = (object) [
					'id'        => $layer_id,
					'type'      => (string) ( $spec_layer['type'] ?? '' ),
					'label'     => (string) ( $spec_layer['label'] ?? '' ),
					'locked'    => ! empty( $spec_layer['locked'] ),
					'has_input' => is_array( $spec_layer['input'] ?? null ),
					'settings'  => wp_json_encode( is_array( $spec_layer['settings'] ?? null ) ? $spec_layer['settings'] : [] ),
				];
			}
		}

		$header_lookup = array_fill_keys( $headers, true );
		$field_map     = [];
		$used_headers  = [];
		foreach ( $template['fields'] as $field ) {
			$layer_id   = absint( $field['layer_id'] ?? 0 );
			$field_name = sanitize_key( (string) ( $field['field_name'] ?? '' ) );
			$layer      = $layers_by_id[ $layer_id ] ?? null;
			if ( ! $layer || empty( $layer->has_input ) || ! isset( $header_lookup[ $field_name ] ) || isset( $field_map[ $layer_id ] ) || isset( $used_headers[ $field_name ] ) || ! in_array( (string) $layer->type, [ 'text', 'textarea', 'spotify' ], true ) || ! empty( $layer->locked ) ) {
				$order->add_order_note( __( 'OverCustomise VDP fields do not match editable layers in the stored render snapshot. A standard single-output print job was queued instead.', 'overcustomise' ) );
				return null;
			}
			$field_map[ $layer_id ]      = [
				'field' => $field_name,
				'layer' => $layer,
			];
			$used_headers[ $field_name ] = true;
		}

		$normalised_rows = [];
		foreach ( array_values( $rows ) as $row ) {
			$row_values = [];
			foreach ( $field_map as $layer_id => $mapping ) {
				$field_name = $mapping['field'];
				$merged     = $vdp->merge_values( (string) ( $row[ $field_name ] ?? '' ), $row );
				if ( preg_match( '/\{\{[a-z0-9_-]+\}\}/i', $merged ) ) {
					$order->add_order_note( __( 'OverCustomise VDP contains an unresolved field placeholder. A standard single-output print job was queued instead.', 'overcustomise' ) );
					return null;
				}
				$value = $vdp->normalise_layer_value( $mapping['layer'], $merged );
				if ( is_wp_error( $value ) ) {
					OC_Logger::warning( 'VDP fallback for design #' . $design_id . ': ' . $value->get_error_message() );
					$order->add_order_note( __( 'OverCustomise VDP contains a value that is invalid for its mapped layer. A standard single-output print job was queued instead.', 'overcustomise' ) );
					return null;
				}
				$row_values[ $layer_id ] = $value;
			}
			$normalised_rows[] = [
				'row'    => $row,
				'values' => $row_values,
			];
		}

		$queued    = 0;
		$failed    = 0;
		$queue_ids = [];
		foreach ( $normalised_rows as $row_offset => $row_data ) {
			$row_index = $row_offset + 1;
			$row_spec  = $stored_spec;
			$remaining = array_fill_keys( array_map( 'intval', array_keys( $row_data['values'] ) ), true );
			foreach ( $row_spec['areas'] as &$spec_area ) {
				if ( ! is_array( $spec_area['layers'] ?? null ) ) {
					continue;
				}
				foreach ( $spec_area['layers'] as &$spec_layer ) {
					if ( ! is_array( $spec_layer ) ) {
						continue;
					}
					$layer_id = absint( $spec_layer['id'] ?? 0 );
					if ( ! array_key_exists( $layer_id, $row_data['values'] ) ) {
						continue;
					}
					if ( ! is_array( $spec_layer['input'] ?? null ) ) {
						throw new \RuntimeException( 'A VDP mapping no longer targets a stored customer input.' );
					}
					$spec_layer['input']['value'] = $row_data['values'][ $layer_id ];
					unset( $remaining[ $layer_id ] );
				}
				unset( $spec_layer );
			}
			unset( $spec_area );
			if ( ! empty( $remaining ) ) {
				throw new \RuntimeException( 'A VDP mapping disappeared from the stored render snapshot.' );
			}

			$row_key = hash( 'sha256', (string) wp_json_encode( $row_data['row'] ) );
			foreach ( OC_Render_Spec::print_areas( $row_spec ) as $entry ) {
				$area      = $entry['area'];
				$area_data = $entry['area_data'];
				if ( ! self::area_has_printable_data( $area_data ) ) {
					$order->add_order_note( sprintf( __( 'OverCustomise skipped VDP row %1$d, print area "%2$s": no printable data was found.', 'overcustomise' ), $row_index, $area->label ?: $area->area_key ) );
					continue;
				}

				$print_file_id = OC_DB::insert_print_file(
					[
						'order_id'      => $order->get_id(),
						'order_item_id' => $item_id,
						'print_area_id' => (int) $area->id,
						'area_source'   => 'design',
						'row_index'     => $row_index,
						'row_key'       => $row_key,
						'area_snapshot' => wp_json_encode( (array) $area ),
						'file_type'     => $area->print_method,
						'file_status'   => 'pending',
						'generated_at'  => $now,
						'expires_at'    => $expires_at,
					]
				);
				if ( ! $print_file_id ) {
					++$failed;
					continue;
				}
				if ( self::queue_job_exists_for_file( $print_file_id ) ) {
					continue;
				}

				$queue_id = OC_Print_Queue::instance()->enqueue(
					(int) $order->get_id(),
					$item_id,
					(int) $area->id,
					$area_data,
					(string) $area->print_method,
					$print_file_id,
					'design',
					$row_index,
					true
				);
				if ( $queue_id > 0 ) {
					++$queued;
					$queue_ids[] = $queue_id;
				} else {
					++$failed;
				}
			}
		}

		return [
			'queued'    => $queued,
			'failed'    => $failed,
			'queue_ids' => $queue_ids,
		];
	}

	/** Generate print files from an order ID-based WooCommerce hook. */
	public function generate_for_order_id( int $order_id, $order = null ): void {
		if ( ! $order instanceof \WC_Order ) {
			$order = wc_get_order( $order_id );
		}

		if ( $order instanceof \WC_Order ) {
			$this->generate_for_order( $order );
		}
	}

	/** Retry idempotent order generation until the print schema is available. */
	public function retry_order_generation( int $order_id ): void {
		if ( ! OC_DB::print_pipeline_available() ) {
			self::schedule_order_generation_retry( $order_id );
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		try {
			$this->generate_for_order( $order );
		} catch ( \Throwable $e ) {
			self::retain_failed_order_generation( $order, $e, 'Deferred print generation failed' );
		}
	}

	/** Persist one deduplicated retry for an order whose schema is unavailable. */
	public static function schedule_order_generation_retry( int $order_id ): bool {
		if ( $order_id <= 0 ) {
			return false;
		}
		if ( function_exists( 'wc_get_order' ) ) {
			try {
				$order = wc_get_order( $order_id );
				if ( $order instanceof \WC_Order ) {
					self::mark_order_generation_pending( $order );
				}
			} catch ( \Throwable $e ) {
				OC_Logger::warning( sprintf( 'Could not persist the print-generation retry marker for order #%d: %s', $order_id, $e->getMessage() ) );
			}
		}

		$args   = [ $order_id ];
		$delay  = (int) apply_filters( 'oc_print_schema_retry_delay', 300, $order_id );
		$delay  = max( 60, min( DAY_IN_SECONDS, $delay ) );
		$run_at = time() + $delay;

		if ( function_exists( 'as_next_scheduled_action' ) && function_exists( 'as_schedule_single_action' ) ) {
			try {
				$scheduled = as_next_scheduled_action( self::ORDER_GENERATION_RETRY_HOOK, $args, 'overcustomise' );
				if ( false !== $scheduled ) {
					return true;
				}

				if ( 0 < (int) as_schedule_single_action( $run_at, self::ORDER_GENERATION_RETRY_HOOK, $args, 'overcustomise', true ) ) {
					return true;
				}
			} catch ( \Throwable $e ) {
				OC_Logger::warning( sprintf( 'Action Scheduler could not retain print generation for order #%d: %s', $order_id, $e->getMessage() ) );
			}
		}

		if ( wp_next_scheduled( self::ORDER_GENERATION_RETRY_HOOK, $args ) ) {
			return true;
		}

		return false !== wp_schedule_single_event( $run_at, self::ORDER_GENERATION_RETRY_HOOK, $args );
	}

	/** Ensure durable pending markers are swept even if an individual event was lost. */
	public function ensure_order_generation_recovery_schedule(): void {
		if ( ! wp_next_scheduled( self::ORDER_GENERATION_RECOVERY_HOOK ) ) {
			wp_schedule_event( time() + 300, 'hourly', self::ORDER_GENERATION_RECOVERY_HOOK );
		}
	}

	/** Retry a bounded HPOS-compatible batch of orders carrying durable pending markers. */
	public function recover_pending_order_generation(): void {
		if ( ! OC_DB::print_pipeline_available() || ! function_exists( 'wc_get_orders' ) ) {
			return;
		}

		$limit     = apply_filters( 'oc_print_generation_recovery_batch_size', 20 );
		$limit     = is_numeric( $limit ) ? max( 1, min( 100, (int) $limit ) ) : 20;
		$order_ids = wc_get_orders(
			[
				'limit'      => $limit,
				'return'     => 'ids',
				'orderby'    => 'date',
				'order'      => 'ASC',
				'meta_query' => [
					[
						'key'     => self::ORDER_GENERATION_PENDING_META,
						'compare' => 'EXISTS',
					],
				],
			]
		);
		foreach ( is_array( $order_ids ) ? $order_ids : [] as $order_id ) {
			$this->retry_order_generation( absint( $order_id ) );
		}
	}

	/** Persist the retry marker through WooCommerce's active order datastore. */
	private static function mark_order_generation_pending( \WC_Order $order ): void {
		if ( '' !== (string) $order->get_meta( self::ORDER_GENERATION_PENDING_META, true ) ) {
			return;
		}
		$order->update_meta_data( self::ORDER_GENERATION_PENDING_META, (string) time() );
		$order->save_meta_data();
	}

	/** Clear the durable retry marker only after schema-backed generation ran. */
	private static function clear_order_generation_pending( \WC_Order $order ): void {
		if ( '' === (string) $order->get_meta( self::ORDER_GENERATION_PENDING_META, true ) ) {
			return;
		}
		$order->delete_meta_data( self::ORDER_GENERATION_PENDING_META );
		$order->save_meta_data();
	}

	/** Preserve recovery state without allowing a secondary failure to escape checkout or cron. */
	private static function retain_failed_order_generation( \WC_Order $order, \Throwable $error, string $context ): void {
		$order_id = (int) $order->get_id();
		try {
			self::mark_order_generation_pending( $order );
		} catch ( \Throwable $marker_error ) {
			OC_Logger::error( sprintf( 'Could not persist the print-generation retry marker for order #%d: %s', $order_id, $marker_error->getMessage() ) );
		}

		try {
			if ( ! self::schedule_order_generation_retry( $order_id ) ) {
				OC_Logger::error( sprintf( 'Could not schedule deferred print generation for order #%d.', $order_id ) );
			}
		} catch ( \Throwable $schedule_error ) {
			OC_Logger::error( sprintf( 'Could not schedule deferred print generation for order #%d: %s', $order_id, $schedule_error->getMessage() ) );
		}

		OC_Logger::error( sprintf( '%s for order #%d and was deferred: %s', $context, $order_id, $error->getMessage() ) );
	}

	/** Queue an order for idempotent generation after all line-item writes finish. */
	public function defer_order_generation( int $order_id, $order = null ): void {
		if ( $order_id > 0 ) {
			$this->deferred_order_ids[ $order_id ] = true;
			if ( ! OC_DB::print_pipeline_available() ) {
				self::schedule_order_generation_retry( $order_id );
			}
		}
	}

	/** Defer initial inspection when customisation metadata is first added to an order item. */
	public function defer_for_customisation_meta( mixed $meta_id, int $item_id, string $meta_key, mixed $meta_value = null ): void {
		if ( '_oc_customisation' !== $meta_key || $item_id <= 0 || ! function_exists( 'wc_get_order_id_by_order_item_id' ) ) {
			return;
		}

		$this->defer_order_generation( absint( wc_get_order_id_by_order_item_id( $item_id ) ) );
	}

	/** Process deferred admin/API orders at shutdown. */
	public function generate_deferred_orders(): void {
		foreach ( array_keys( $this->deferred_order_ids ) as $order_id ) {
			$this->retry_order_generation( (int) $order_id );
		}
		$this->deferred_order_ids = [];
	}

	/** Return v2 production areas, treating an order's renderSpec as the area authority. */
	private static function v2_print_areas( int $design_id, array $customisation ): array {
		if ( array_key_exists( 'renderSpec', $customisation ) ) {
			if ( ! is_array( $customisation['renderSpec'] ) ) {
				throw new \RuntimeException( 'The order contains an invalid stored render specification.' );
			}
			$render_spec      = $customisation['renderSpec'];
			$stored_design_id = absint( $render_spec['designId'] ?? 0 );
			if ( $stored_design_id > 0 && $stored_design_id !== $design_id ) {
				throw new \RuntimeException( 'The stored render specification does not match the order design.' );
			}

			return OC_Render_Spec::print_areas( $render_spec );
		}

		// Historical v2 orders without a renderSpec retain the old reconstruction path.
		$layer_inputs = is_array( $customisation['layers'] ?? null ) ? $customisation['layers'] : [];
		$layer_inputs = self::normalise_v2_layer_font_inputs( $design_id, $layer_inputs );

		return OC_Render_Spec::print_areas( OC_Render_Spec::build( $design_id, $layer_inputs ) );
	}

	private static function area_has_printable_data( array $area_data ): bool {
		foreach ( [ 'text', 'artworkAttachmentId', 'artworkPath' ] as $key ) {
			if ( ! empty( $area_data[ $key ] ) ) {
				return true;
			}
		}
		foreach ( is_array( $area_data['layers'] ?? null ) ? $area_data['layers'] : [] as $layer ) {
			if ( ! is_array( $layer ) ) {
				continue;
			}
			$type  = (string) ( $layer['type'] ?? '' );
			$input = is_array( $layer['input'] ?? null ) ? $layer['input'] : [];
			if ( 'lineart' === $type ) {
				return true;
			}
			if ( in_array( $type, [ 'text', 'textarea', 'spotify' ], true ) && '' !== trim( (string) ( $input['value'] ?? '' ) ) ) {
				return true;
			}
			if ( in_array( $type, [ 'image', 'ai_image', 'clipmask' ], true ) && absint( $input['attachmentId'] ?? $layer['artworkAttachmentId'] ?? 0 ) > 0 ) {
				return true;
			}
			if ( 'clipart' === $type && ( absint( $input['clipartId'] ?? 0 ) > 0 || ! empty( $layer['artworkPath'] ) ) ) {
				return true;
			}
			if ( 'night_sky' === $type ) {
				$geometry = is_array( $input['nightSkyGeometry'] ?? null ) ? $input['nightSkyGeometry'] : [];
				if ( ! empty( $geometry['stars'] ) || ! empty( $geometry['segments'] ) ) {
					return true;
				}
			}
		}

		return false;
	}

	private static function print_file_exists( int $order_id, int $item_id, int $print_area_id, string $area_source, int $row_index ): bool {
		global $wpdb;
		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}oc_print_files WHERE order_id = %d AND order_item_id = %d AND print_area_id = %d AND area_source = %s AND row_index = %d LIMIT 1",
				$order_id,
				$item_id,
				$print_area_id,
				$area_source,
				$row_index
			)
		);
	}

	/** Return whether creation has already attached a queue job to this exact file row. */
	private static function queue_job_exists_for_file( int $print_file_id ): bool {
		global $wpdb;
		if ( $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}oc_print_queue WHERE print_file_id = %d LIMIT 1",
				$print_file_id
			)
		) ) {
			return true;
		}

		$record = OC_DB::get_print_file( $print_file_id );
		if ( ! $record ) {
			return false;
		}

		$payloads = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT area_data FROM {$wpdb->prefix}oc_print_queue
			 WHERE order_id = %d AND order_item_id = %d AND area_data LIKE %s",
				(int) $record->order_id,
				(int) $record->order_item_id,
				'%' . $wpdb->esc_like( '"__combined_print_areas"' ) . '%'
			)
		) ?: [];

		foreach ( $payloads as $payload ) {
			if ( self::combined_payload_contains_print_file_id( (string) $payload, $print_file_id ) ) {
				return true;
			}
		}

		return false;
	}

	/** Inspect only structured combined entries, never arbitrary customer text. */
	private static function combined_payload_contains_print_file_id( string $encoded, int $print_file_id ): bool {
		$payload = json_decode( $encoded, true );
		if ( ! is_array( $payload ) || ! self::is_combined_area_data( $payload ) ) {
			return false;
		}

		foreach ( $payload['__combined_print_areas'] as $entry ) {
			$value = is_array( $entry ) ? ( $entry['printFileId'] ?? null ) : null;
			if ( is_numeric( $value ) && (float) $value === (float) (int) $value && (int) $value === $print_file_id ) {
				return true;
			}
		}

		return false;
	}

	/** Read the immutable generation payload retained by this file's queue job. */
	private static function persisted_area_data( object $record ): ?array {
		global $wpdb;
		$jobs = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT area_data, print_file_id FROM {$wpdb->prefix}oc_print_queue
			 WHERE print_file_id = %d OR (order_id = %d AND order_item_id = %d)
			 ORDER BY (print_file_id = %d) DESC, id DESC",
				(int) $record->id,
				(int) $record->order_id,
				(int) $record->order_item_id,
				(int) $record->id
			)
		) ?: [];

		foreach ( $jobs as $job ) {
			$payload = json_decode( (string) $job->area_data, true );
			if ( ! is_array( $payload ) ) {
				continue;
			}

			if ( self::is_combined_area_data( $payload ) ) {
				foreach ( $payload['__combined_print_areas'] as $entry ) {
					if ( is_array( $entry ) && (int) ( $entry['printFileId'] ?? 0 ) === (int) $record->id && is_array( $entry['areaData'] ?? null ) ) {
						return $entry['areaData'];
					}
				}
			} elseif ( (int) $job->print_file_id === (int) $record->id ) {
				return $payload;
			}
		}

		return null;
	}

	/** Rebuild a combined job entirely from its retained payload and area snapshots. */
	private static function persisted_combined_entries( object $record ): array {
		if ( (int) ( $record->row_index ?? 0 ) > 0 || '' !== (string) ( $record->row_key ?? '' ) ) {
			return [];
		}

		global $wpdb;
		$jobs = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT area_data FROM {$wpdb->prefix}oc_print_queue
			 WHERE order_id = %d AND order_item_id = %d ORDER BY id DESC",
				(int) $record->order_id,
				(int) $record->order_item_id
			)
		) ?: [];

		foreach ( $jobs as $json ) {
			$payload = json_decode( (string) $json, true );
			if ( ! is_array( $payload ) || ! self::is_combined_area_data( $payload ) ) {
				continue;
			}

			$contains_record = false;
			$entries         = [];
			$seen_file_ids   = [];
			$seen_areas      = [];
			$valid           = true;
			foreach ( $payload['__combined_print_areas'] as $entry ) {
				if (
					! is_array( $entry )
					|| ! is_numeric( $entry['printFileId'] ?? null )
					|| ! is_numeric( $entry['areaId'] ?? null )
					|| ! is_numeric( $entry['rowIndex'] ?? null )
					|| ! is_array( $entry['areaData'] ?? null )
				) {
					$valid = false;
					break;
				}
				$file_id       = (int) ( $entry['printFileId'] ?? 0 );
				$area_id       = (int) ( $entry['areaId'] ?? 0 );
				$area_source   = (string) ( $entry['areaSource'] ?? '' );
				$row_index     = (int) ( $entry['rowIndex'] ?? -1 );
				$area_identity = $area_source . ':' . $area_id . ':' . $row_index;
				$file          = $file_id > 0 ? OC_DB::get_print_file( $file_id ) : null;
				if (
					! $file
					|| (float) $entry['printFileId'] !== (float) $file_id
					|| (float) $entry['areaId'] !== (float) $area_id
					|| (float) $entry['rowIndex'] !== (float) $row_index
					|| ! in_array( $area_source, [ 'design', 'legacy', 'unknown' ], true )
					|| isset( $seen_file_ids[ $file_id ] )
					|| isset( $seen_areas[ $area_identity ] )
					|| (int) ( $file->order_id ?? 0 ) !== (int) $record->order_id
					|| (int) ( $file->order_item_id ?? 0 ) !== (int) $record->order_item_id
					|| (int) ( $file->print_area_id ?? 0 ) !== $area_id
					|| (string) ( $file->area_source ?? '' ) !== $area_source
					|| (int) ( $file->row_index ?? -1 ) !== $row_index
					|| $area_source !== (string) ( $record->area_source ?? '' )
					|| $row_index !== (int) ( $record->row_index ?? -1 )
					|| sanitize_key( (string) ( $file->file_type ?? '' ) ) !== sanitize_key( (string) ( $record->file_type ?? '' ) )
				) {
					$valid = false;
					break;
				}
				$seen_file_ids[ $file_id ]    = true;
				$seen_areas[ $area_identity ] = true;
				$snapshot                     = json_decode( (string) ( $file->area_snapshot ?? '' ), true );
				if ( ! is_array( $snapshot ) || empty( $snapshot ) ) {
					$snapshot = is_array( $entry['areaSnapshot'] ?? null ) ? $entry['areaSnapshot'] : [];
				}
				if ( empty( $snapshot ) ) {
					$valid = false;
					break;
				}
				$contains_record = $contains_record || $file_id === (int) $record->id;
				$entries[]       = [
					'area'      => (object) $snapshot,
					'area_data' => $entry['areaData'],
				];
			}

			if ( $valid && $contains_record && count( $entries ) > 1 ) {
				return $entries;
			}
		}

		return [];
	}

	/**
	 * @return array<int,object>
	 */
	private static function get_print_file_records_for_areas( int $order_id, int $item_id, array $area_ids, string $area_source, int $row_index ): array {
		$area_ids = array_values( array_filter( array_map( 'absint', $area_ids ) ) );
		if ( empty( $area_ids ) ) {
			return [];
		}

		global $wpdb;
		$placeholders = implode( ',', array_fill( 0, count( $area_ids ), '%d' ) );
		$params       = array_merge( [ $order_id, $item_id, $area_source, $row_index ], $area_ids );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}oc_print_files WHERE order_id = %d AND order_item_id = %d AND area_source = %s AND row_index = %d AND print_area_id IN ({$placeholders}) ORDER BY id ASC",
				...$params
			)
		) ?: [];
	}

	/**
	 * @return array<int,array{area:object,area_data:array}>
	 */
	private static function combined_entries_for_regeneration( object $record, array $customisation, object $target_area, bool $is_v2_area ): array {
		if ( (int) ( $record->row_index ?? 0 ) > 0 || '' !== (string) ( $record->row_key ?? '' ) ) {
			return [];
		}

		$method = sanitize_key( (string) ( $target_area->print_method ?? $record->file_type ?? '' ) );
		if ( ! self::supports_combined_print_file( $method ) ) {
			return [];
		}

		$entries = [];
		if ( $is_v2_area ) {
			$design_id = (int) ( $target_area->design_id ?? $customisation['designId'] ?? 0 );
			if ( ! $design_id ) {
				return [];
			}

			$stored_spec  = is_array( $customisation['renderSpec'] ?? null ) ? $customisation['renderSpec'] : null;
			$area_entries = null !== $stored_spec
				? OC_Render_Spec::print_areas( $stored_spec )
				: self::v2_print_areas( $design_id, $customisation );
			foreach ( $area_entries as $entry ) {
				$area      = $entry['area'];
				$area_data = $entry['area_data'];
				if ( sanitize_key( (string) $area->print_method ) !== $method ) {
					continue;
				}

				if ( ! self::area_has_printable_data( $area_data ) ) {
					continue;
				}

				if ( ! self::print_file_exists( (int) $record->order_id, (int) $record->order_item_id, (int) $area->id, (string) $record->area_source, (int) $record->row_index ) ) {
					continue;
				}

				$entries[] = [
					'area'      => $area,
					'area_data' => $area_data,
				];
			}
			return $entries;
		}

		$config_id = (int) ( $target_area->config_id ?? 0 );
		if ( ! $config_id ) {
			return [];
		}

		foreach ( OC_DB::get_print_areas( $config_id ) as $area ) {
			if ( sanitize_key( (string) $area->print_method ) !== $method ) {
				continue;
			}

			$area_data = $customisation[ $area->area_key ] ?? null;
			if ( ! is_array( $area_data ) ) {
				continue;
			}

			if ( ! self::print_file_exists( (int) $record->order_id, (int) $record->order_item_id, (int) $area->id, (string) $record->area_source, (int) $record->row_index ) ) {
				continue;
			}

			$entries[] = [
				'area'      => $area,
				'area_data' => $area_data,
			];
		}

		return $entries;
	}

	/**
	 * Queue compatible same-method print areas together without changing the per-area DB records.
	 *
	 * @param array<int,array{print_file_id:int,area_id:int,area_source:string,row_index:int,area_snapshot:array,area_data:array,print_method:string}> $print_jobs
	 * @return array{queued:int,failed:int,queue_ids:array<int,int>}
	 */
	private static function enqueue_print_jobs_grouped( int $order_id, int $item_id, array $print_jobs ): array {
		$groups           = [];
		$invalid_file_ids = [];

		foreach ( $print_jobs as $job ) {
			$method = sanitize_key( (string) ( $job['print_method'] ?? '' ) );
			if ( '' === $method ) {
				$invalid_file_ids[] = absint( $job['print_file_id'] ?? 0 );
				continue;
			}

			$key = self::supports_combined_print_file( $method ) ? $method : $method . ':' . (int) ( $job['area_id'] ?? 0 );
			if ( empty( $groups[ $key ] ) ) {
				$groups[ $key ] = [];
			}
			$groups[ $key ][] = $job;
		}

		$queued    = 0;
		$failed    = count( array_filter( $invalid_file_ids ) );
		$queue_ids = [];
		if ( ! empty( $invalid_file_ids ) ) {
			OC_DB::fail_unqueued_print_files( $invalid_file_ids );
		}

		foreach ( $groups as $jobs ) {
			$first = $jobs[0] ?? null;
			if ( ! is_array( $first ) ) {
				continue;
			}

			$area_data = $first['area_data'];
			if ( count( $jobs ) > 1 ) {
				$area_data = [
					'__combined_print_areas' => array_map(
						static fn ( array $job ): array => [
							'printFileId'  => (int) $job['print_file_id'],
							'areaId'       => (int) $job['area_id'],
							'areaSource'   => (string) $job['area_source'],
							'rowIndex'     => (int) $job['row_index'],
							'areaSnapshot' => $job['area_snapshot'],
							'areaData'     => $job['area_data'],
						],
						$jobs
					),
				];
			}

			$queue_id = OC_Print_Queue::instance()->enqueue(
				$order_id,
				$item_id,
				(int) $first['area_id'],
				$area_data,
				(string) $first['print_method'],
				(int) $first['print_file_id'],
				(string) $first['area_source'],
				(int) $first['row_index'],
				true
			);
			if ( $queue_id > 0 ) {
				$queued     += count( $jobs );
				$queue_ids[] = $queue_id;
			} else {
				$failed += count( $jobs );
			}
		}

		return [
			'queued'    => $queued,
			'failed'    => $failed,
			'queue_ids' => $queue_ids,
		];
	}

	public static function supports_combined_print_file( string $print_method ): bool {
		return in_array( sanitize_key( $print_method ), [ 'uv', 'sublimation', 'engraving' ], true );
	}

	public static function is_combined_area_data( array $area_data ): bool {
		return ! empty( $area_data['__combined_print_areas'] ) && is_array( $area_data['__combined_print_areas'] );
	}

	private static function regeneration_snapshot_warning( object $area, array $area_data ): string {
		$print_method = sanitize_key( (string) ( $area->print_method ?? '' ) );
		if ( ! in_array( $print_method, [ 'uv', 'sublimation' ], true ) || ! empty( $area_data['layers'] ) ) {
			return '';
		}

		$snapshot = is_array( $area_data['snapshot'] ?? null ) ? $area_data['snapshot'] : [];
		$svg      = is_string( $snapshot['svg'] ?? null ) ? trim( (string) $snapshot['svg'] ) : '';
		if ( '' !== $svg && str_contains( $svg, '<svg' ) && ! preg_match( '/<image\b/i', $svg ) ) {
			return '';
		}

		return __( 'Warning: this order does not contain a usable browser-rendered vector snapshot for this print area. The regenerated UV/sublimation file used the older layer renderer and may not exactly match the customer preview, especially SVG placement or text sizing.', 'overcustomise' );
	}

	/**
	 * Collapse v2 per-layer customisation inputs for a single design print area
	 * into the v1 area_data shape the type-specific generators still consume.
	 *
	 * v2 shape: { layers: { layerId: {type, value, fontId, colorHex, attachmentId, ...} } }
	 * Output:   { text, fontId, color, artworkAttachmentId }
	 */
	public static function build_v2_area_data( int $design_id, int $area_id, array $customisation ): array {
		$stored_spec = is_array( $customisation['renderSpec'] ?? null ) ? $customisation['renderSpec'] : [];
		if ( ! empty( $stored_spec ) ) {
			return OC_Render_Spec::area_from_spec( $stored_spec, $area_id );
		}

		$layer_inputs = is_array( $customisation['layers'] ?? null ) ? $customisation['layers'] : [];
		$layer_inputs = self::normalise_v2_layer_font_inputs( $design_id, $layer_inputs );
		$render_spec  = OC_Render_Spec::build( $design_id, $layer_inputs );
		$area_data    = OC_Render_Spec::area_from_spec( $render_spec, $area_id );

		return ! empty( $area_data ) ? $area_data : [];
	}

	/** Use the order-time renderSpec area snapshot for generation, falling back to a historical row. */
	public static function area_object_for_generation( object $current_area, array $area_data ): object {
		$snapshot = is_array( $area_data['renderSpecArea'] ?? null ) ? $area_data['renderSpecArea'] : [];
		$bounds   = is_array( $snapshot['bounds'] ?? null ) ? $snapshot['bounds'] : [];

		if ( empty( $snapshot ) || empty( $bounds ) ) {
			return $current_area;
		}

		return OC_Render_Spec::area_object( $snapshot, absint( $current_area->design_id ?? 0 ) );
	}

	private static function normalise_v2_layer_font_inputs( int $design_id, array $layer_inputs ): array {
		$fallback_font_id = OC_DB::get_first_active_font_id();

		foreach ( OC_DB::get_design_layers( $design_id ) as $layer ) {
			$layer_id = (int) $layer->id;
			if ( ! $layer_id || ! in_array( (string) $layer->type, [ 'text', 'textarea' ], true ) ) {
				continue;
			}

			$settings = $layer->settings ? json_decode( (string) $layer->settings, true ) : [];
			if ( ! is_array( $settings ) ) {
				$settings = [];
			}

			if ( empty( $layer_inputs[ $layer_id ] ) || ! is_array( $layer_inputs[ $layer_id ] ) ) {
				$layer_inputs[ $layer_id ] = [];
			}

			if ( empty( $layer_inputs[ $layer_id ]['fontId'] ) ) {
				$layer_inputs[ $layer_id ]['fontId'] = absint( $settings['default_font_id'] ?? 0 ) ?: $fallback_font_id;
			}
		}

		return $layer_inputs;
	}

	/**
	 * Generate the print file for one area. Called from the queue processor
	 * or from the regenerate() method. Does NOT insert DB rows — caller handles
	 * oc_print_files state.
	 *
	 * @return array{file_path:string, status:string}
	 * @throws \RuntimeException
	 */
	public static function generate_for_area(
		\WC_Order $order,
		int $item_id,
		object $area,
		array $area_data
	): array {
		return match ( $area->print_method ) {
			'engraving'   => [
				'file_path' => OC_Print_Engraving::generate( $order, $item_id, $area, $area_data ),
				'status'    => 'files_ready',
			],
			'uv'          => [
				'file_path' => OC_Print_UV::generate( $order, $item_id, $area, $area_data ),
				'status'    => 'files_ready',
			],
			'sublimation' => [
				'file_path' => OC_Print_Sublimation::generate( $order, $item_id, $area, $area_data ),
				'status'    => 'files_ready',
			],
			'embroidery'  => OC_Print_Embroidery::generate( $order, $item_id, $area, $area_data ),
			default       => throw new \RuntimeException( "Unknown print method: {$area->print_method}" ),
		};
	}

	/**
	 * Generate one print file for multiple same-method print areas.
	 *
	 * @param array<int,array{area:object,area_data:array}> $areas
	 * @return array{file_path:string, status:string}
	 */
	public static function generate_for_areas( \WC_Order $order, int $item_id, string $print_method, array $areas ): array {
		return match ( sanitize_key( $print_method ) ) {
			'engraving'   => [
				'file_path' => OC_Print_Engraving::generate_combined( $order, $item_id, $areas ),
				'status'    => 'files_ready',
			],
			'uv'          => [
				'file_path' => OC_Print_UV::generate_combined( $order, $item_id, $areas ),
				'status'    => 'files_ready',
			],
			'sublimation' => [
				'file_path' => OC_Print_Sublimation::generate_combined( $order, $item_id, $areas ),
				'status'    => 'files_ready',
			],
			default       => throw new \RuntimeException( "Cannot combine print method: {$print_method}" ),
		};
	}

	// -------------------------------------------------------------------------
	// Admin GET handlers
	// -------------------------------------------------------------------------

	/**
	 * Handle the "Regenerate" link from the order metabox:
	 * ?oc_regenerate={id}&_wpnonce={nonce}
	 */
	public function handle_admin_regenerate(): void {
		if ( empty( $_GET['oc_regenerate'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'overcustomise' ), 403 );
		}

		$file_id = (int) $_GET['oc_regenerate'];

		if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'oc_regenerate_' . $file_id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'overcustomise' ), 403 );
		}

		$record = OC_DB::get_print_file( $file_id );
		if ( ! $record ) {
			wp_die( esc_html__( 'Print file record not found.', 'overcustomise' ), 404 );
		}

		$has_snapshot_warning = false;

		try {
			$result = $this->regenerate( $file_id );
			if ( ! empty( $result['warning'] ) ) {
				$has_snapshot_warning = true;
				$order                = wc_get_order( (int) $record->order_id );
				if ( $order instanceof \WC_Order ) {
					$order->add_order_note( (string) $result['warning'] );
				}
			}
		} catch ( \Throwable $e ) {
			OC_Logger::error( 'Admin regenerate failed for file #' . $file_id . ': ' . $e->getMessage() );
		}

		// Redirect back to the order edit screen.
		$order    = wc_get_order( (int) $record->order_id );
		$redirect = $order instanceof \WC_Order ? $order->get_edit_order_url() : admin_url( 'admin.php?page=wc-orders' );
		if ( $has_snapshot_warning ) {
			$redirect = add_query_arg( 'oc_regenerate_snapshot_warning', '1', $redirect );
		}
		wp_safe_redirect( $redirect );
		exit;
	}

	public function render_admin_notices(): void {
		if ( empty( $_GET['oc_regenerate_snapshot_warning'] ) || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
			esc_html__( 'OverCustomise regenerated this file without a usable browser-rendered vector snapshot. The file used the older layer renderer and may not exactly match the customer preview, especially SVG placement or text sizing.', 'overcustomise' )
		);
	}

	/** Handle the admin "Generate Print Files" link for orders missing file rows. */
	public function handle_admin_generate_missing(): void {
		if ( empty( $_GET['oc_generate_print_files'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'overcustomise' ), 403 );
		}

		$order_id = absint( $_GET['oc_generate_print_files'] );
		if ( ! $order_id || ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'oc_generate_print_files_' . $order_id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'overcustomise' ), 403 );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order ) {
			wp_die( esc_html__( 'Order not found.', 'overcustomise' ), 404 );
		}

		try {
			$this->generate_for_order( $order );
		} catch ( \Throwable $e ) {
			OC_Logger::error( 'Admin print-file generation failed for order #' . $order_id . ': ' . $e->getMessage() );
			$order->add_order_note( sprintf( __( 'OverCustomise print-file generation failed: %s', 'overcustomise' ), $e->getMessage() ) );
		}

		$redirect = wp_get_referer() ?: $order->get_edit_order_url();
		wp_safe_redirect( remove_query_arg( [ 'oc_generate_print_files', '_wpnonce' ], $redirect ) );
		exit;
	}

	/** Handle the admin "Process Print Queue" link for an order. */
	public function handle_admin_process_queue(): void {
		if ( empty( $_GET['oc_process_print_queue_order'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'overcustomise' ), 403 );
		}

		$order_id = absint( $_GET['oc_process_print_queue_order'] );
		if ( ! $order_id || ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'oc_process_print_queue_order_' . $order_id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'overcustomise' ), 403 );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order ) {
			wp_die( esc_html__( 'Order not found.', 'overcustomise' ), 404 );
		}

		global $wpdb;
		$jobs = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}oc_print_queue
			 WHERE order_id = %d
			 AND status IN ('pending', 'failed')
			 ORDER BY created_at ASC",
				$order_id
			)
		) ?: [];

		$processed = 0;
		foreach ( $jobs as $job ) {
			if ( 'failed' === $job->status ) {
				if ( ! OC_Print_Queue::instance()->retry_job( (int) $job->id ) ) {
					continue;
				}
			}
			OC_Print_Queue::instance()->process_one( (int) $job->id );
			++$processed;
		}

		$order->add_order_note( sprintf( __( 'OverCustomise manually processed %d print queue job(s).', 'overcustomise' ), $processed ) );

		$redirect = wp_get_referer() ?: $order->get_edit_order_url();
		wp_safe_redirect( remove_query_arg( [ 'oc_process_print_queue_order', '_wpnonce' ], $redirect ) );
		exit;
	}

	/**
	 * Serve a print file download when an admin navigates to
	 * ?oc_download_file={id}&_wpnonce={nonce}.
	 */
	public function handle_admin_download(): void {
		if ( empty( $_GET['oc_download_file'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'overcustomise' ), 403 );
		}

		$file_id = (int) $_GET['oc_download_file'];

		if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'oc_download_' . $file_id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'overcustomise' ), 403 );
		}

		$record = OC_DB::get_print_file( $file_id );

		$downloadable_statuses = [ 'files_ready', 'brief_ready', 'awaiting_dst_upload' ];
		if ( ! $record || ! in_array( (string) $record->file_status, $downloadable_statuses, true ) ) {
			wp_die( esc_html__( 'File not available.', 'overcustomise' ), 404 );
		}

		if ( empty( $record->file_path ) ) {
			wp_die( esc_html__( 'File not found on disk.', 'overcustomise' ), 404 );
		}

		$target_real = self::resolve_print_storage_path( (string) $record->file_path );
		if ( ! $target_real ) {
			wp_die( esc_html__( 'Invalid file path.', 'overcustomise' ), 400 );
		}

		$filename  = basename( $target_real );
		$mime_type = self::mime_for_extension( pathinfo( $filename, PATHINFO_EXTENSION ) );

		// RFC 6266: use a safe ASCII fallback plus filename* for any UTF-8 names.
		$ascii_fallback = preg_replace( '/[^\w\-.]+/', '_', $filename ) ?: 'download';
		$utf8_encoded   = rawurlencode( $filename );

		// Stream the file to the browser.
		header( 'Content-Type: ' . $mime_type );
		header(
			sprintf(
				'Content-Disposition: attachment; filename="%s"; filename*=UTF-8\'\'%s',
				$ascii_fallback,
				$utf8_encoded
			)
		);
		header( 'Content-Length: ' . filesize( $target_real ) );
		header( 'Cache-Control: private, no-cache, no-store, must-revalidate' );
		header( 'Pragma: no-cache' );
		header( 'X-Content-Type-Options: nosniff' );

		// Disable any output buffering to avoid memory issues with large files.
		while ( ob_get_level() ) {
			ob_end_clean();
		}

		readfile( $target_real ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
	}

	/** Stream a generated thumbnail to an authorised WooCommerce manager. */
	public function handle_admin_thumbnail(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'overcustomise' ), '', [ 'response' => 403 ] );
		}

		$file_id = absint( $_GET['file_id'] ?? 0 );
		$nonce   = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) );
		if ( $file_id <= 0 || ! wp_verify_nonce( $nonce, 'oc_thumbnail_' . $file_id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'overcustomise' ), '', [ 'response' => 403 ] );
		}

		$record = OC_DB::get_print_file( $file_id );
		$path   = $record && 'files_ready' === (string) $record->file_status
			? self::resolve_print_storage_path( (string) ( $record->thumbnail_path ?? '' ) )
			: null;
		$info   = $path ? @getimagesize( $path ) : false;
		$mime   = is_array( $info ) ? $info['mime'] : '';
		if ( ! $path || ! in_array( $mime, [ 'image/png', 'image/jpeg', 'image/webp' ], true ) ) {
			wp_die( esc_html__( 'Thumbnail not available.', 'overcustomise' ), '', [ 'response' => 404 ] );
		}

		header( 'Content-Type: ' . $mime );
		header( 'Content-Disposition: inline; filename="' . sanitize_file_name( basename( $path ) ) . '"' );
		header( 'Content-Length: ' . filesize( $path ) );
		header( 'Cache-Control: private, no-cache, no-store, must-revalidate' );
		header( 'X-Content-Type-Options: nosniff' );
		while ( ob_get_level() ) {
			ob_end_clean();
		}
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
	}

	/** Resolve a DB path only when it is a regular file under the print root. */
	private static function resolve_print_storage_path( string $path ): ?string {
		$uploads = wp_upload_dir();
		$base    = realpath( trailingslashit( (string) ( $uploads['basedir'] ?? '' ) ) . 'overcustomise/print-files' );
		$real    = '' !== $path ? realpath( $path ) : false;

		return $base && $real && is_file( $real ) && str_starts_with( $real, rtrim( $base, '/\\' ) . DIRECTORY_SEPARATOR ) ? $real : null;
	}

	/** Map file extension to MIME type for download headers. */
	private static function mime_for_extension( string $ext ): string {
		return match ( strtolower( $ext ) ) {
			'pdf'           => 'application/pdf',
			'eps'           => 'application/postscript',
			'dst', 'emb',
			'jef', 'vp3',
			'pes', 'xxx'    => 'application/octet-stream',
			'png'           => 'image/png',
			'jpg', 'jpeg'   => 'image/jpeg',
			default         => 'application/octet-stream',
		};
	}
}
