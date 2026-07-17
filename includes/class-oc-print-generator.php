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
	/** @var array<int,true> Orders deferred until request data has been persisted. */
	private array $deferred_order_ids = [];

	public function register(): void {
		add_action( 'init', [ OC_Print_Base::class, 'ensure_output_storage_protected' ] );

		// Primary: order created at checkout.
		add_action( 'woocommerce_checkout_order_created', [ $this, 'generate_for_order' ], 20, 1 );

		// Admin/API orders receive line items after woocommerce_new_order, so defer
		// fallback generation until the request has finished persisting item meta.
		add_action( 'woocommerce_new_order', [ $this, 'defer_order_generation' ], 30, 2 );
		add_action( 'woocommerce_update_order', [ $this, 'defer_order_generation' ], 30, 2 );
		add_action( 'shutdown', [ $this, 'generate_deferred_orders' ], 20 );

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
	 * @return array{file_path:string,status:string}
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

		// Prefer the immutable order-time snapshot, then use the explicitly named source table.
		global $wpdb;
		$area_snapshot = json_decode( (string) ( $record->area_snapshot ?? '' ), true );
		$area          = is_array( $area_snapshot ) && ! empty( $area_snapshot ) ? (object) $area_snapshot : null;
		$area_source   = (string) ( $record->area_source ?? 'unknown' );
		$is_v2_area    = 'design' === $area_source;

		if ( ! $area && in_array( $area_source, [ 'design', 'legacy' ], true ) ) {
			$table = 'design' === $area_source ? 'oc_design_print_areas' : 'oc_print_areas';
			$area  = $wpdb->get_row( $wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}{$table} WHERE id = %d LIMIT 1",
				$record->print_area_id
			) );
		}

		if ( ! $area ) {
			throw new \RuntimeException( "Print area #{$record->print_area_id} has no unambiguous source or stored snapshot." );
		}

		$persisted_area_data = self::persisted_area_data( $record );
		if ( is_array( $persisted_area_data ) ) {
			$area_data = $persisted_area_data;
			if ( $is_v2_area ) {
				$area = self::area_object_for_generation( $area, $area_data );
			}
		} elseif ( $is_v2_area ) {
			$area_data = self::build_v2_area_data( (int) $area->design_id, (int) $area->id, $customisation );
			$area      = self::area_object_for_generation( $area, $area_data );
		} else {
			$area_data = $customisation[ $area->area_key ] ?? null;
		}

		if ( ! is_array( $area_data ) ) {
			throw new \RuntimeException( "No customisation data for area '{$area->area_key}'." );
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
						$result['file_path'] = self::finalise_generated_output( $result['file_path'], $file_id );
						$thumb_path = self::maybe_generate_thumbnail( $result['file_path'] );
						$result['thumbnail_path'] = $thumb_path;
						if ( ! OC_DB::update_print_file( $file_id, [
							'file_path'      => $result['file_path'],
							'file_status'    => $result['status'],
							'thumbnail_path' => $thumb_path,
							'generated_at'   => $now,
							'expires_at'     => $expires_at,
						] ) ) {
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
						$result['file_path'] = self::finalise_generated_output( $result['file_path'], (int) $records[0]->id );
						$thumb_path = self::maybe_generate_thumbnail( $result['file_path'] );
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
		$lock_name = 'oc_print_' . $order_id . '_' . $item_id;
		$acquired  = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock_name ) );
		if ( 1 !== $acquired ) {
			throw new \RuntimeException( 'Could not acquire the print output lock.' );
		}

		try {
			return $generate();
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
		}
	}

	/** Preserve existing output artifacts until their replacements have been fully committed. */
	private static function generate_with_backup( string $old_path, callable $generate, ?callable $commit = null, array $related_paths = [] ): array {
		$paths   = array_values( array_unique( array_filter(
			array_merge( [ $old_path ], $related_paths ),
			static fn( mixed $path ): bool => is_string( $path ) && '' !== $path
		) ) );
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
		$current_paths = array_values( array_filter( [
			(string) ( $result['file_path'] ?? '' ),
			(string) ( $result['thumbnail_path'] ?? '' ),
		] ) );
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
				$references = (int) $wpdb->get_var( $wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}oc_print_files WHERE file_path = %s OR thumbnail_path = %s",
					(string) $path,
					(string) $path
				) );
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

				$areas      = OC_DB::get_design_print_areas( $design_id );
				$print_jobs = [];
				foreach ( $areas as $area ) {
					if ( isset( $area->visible ) && ! (bool) $area->visible ) {
						continue;
					}
					$area_data = self::build_v2_area_data( $design_id, (int) $area->id, $customisation );
					$area      = self::area_object_for_generation( $area, $area_data );
					if ( ! self::area_has_printable_data( $area_data ) ) {
						$order->add_order_note( sprintf( __( 'OverCustomise skipped print area "%s": no printable customer data was found.', 'overcustomise' ), $area->label ?: $area->area_key ) );
						continue;
					}
					$print_file_id = OC_DB::insert_print_file( [
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
					] );
					if ( ! $print_file_id ) {
						$failed_count++;
						continue;
					}
					if ( self::queue_job_exists_for_file( $print_file_id ) ) {
						continue;
					}

					$print_jobs[] = [
						'print_file_id' => $print_file_id,
						'area_id'      => (int) $area->id,
						'area_source'  => 'design',
						'row_index'    => 0,
						'area_snapshot' => (array) $area,
						'area_data'    => $area_data,
						'print_method' => (string) $area->print_method,
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
				$print_file_id = OC_DB::insert_print_file( [
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
				] );
				if ( ! $print_file_id ) {
					$failed_count++;
					continue;
				}
				if ( self::queue_job_exists_for_file( $print_file_id ) ) {
					continue;
				}

				$print_jobs[] = [
					'print_file_id' => $print_file_id,
					'area_id'      => (int) $area->id,
					'area_source'  => 'legacy',
					'row_index'    => 0,
					'area_snapshot' => (array) $area,
					'area_data'    => $area_data,
					'print_method' => (string) $area->print_method,
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

		$layers_by_id = [];
		foreach ( OC_DB::get_design_layers( $design_id ) as $layer ) {
			if ( ( isset( $layer->visible ) && ! (bool) $layer->visible ) || ! empty( $layer->locked ) ) {
				continue;
			}
			$layers_by_id[ (int) $layer->id ] = $layer;
		}

		$header_lookup = array_fill_keys( $headers, true );
		$field_map     = [];
		$used_headers  = [];
		foreach ( $template['fields'] as $field ) {
			$layer_id  = absint( $field['layer_id'] ?? 0 );
			$field_name = sanitize_key( (string) ( $field['field_name'] ?? '' ) );
			$layer      = $layers_by_id[ $layer_id ] ?? null;
			if ( ! $layer || ! isset( $header_lookup[ $field_name ] ) || isset( $field_map[ $layer_id ] ) || isset( $used_headers[ $field_name ] ) || ! in_array( (string) $layer->type, [ 'text', 'textarea', 'spotify' ], true ) ) {
				$order->add_order_note( __( 'OverCustomise VDP fields no longer match the visible design layers. A standard single-output print job was queued instead.', 'overcustomise' ) );
				return null;
			}
			$field_map[ $layer_id ]     = [ 'field' => $field_name, 'layer' => $layer ];
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
				$value      = $vdp->normalise_layer_value( $mapping['layer'], $merged );
				if ( is_wp_error( $value ) ) {
					OC_Logger::warning( 'VDP fallback for design #' . $design_id . ': ' . $value->get_error_message() );
					$order->add_order_note( __( 'OverCustomise VDP contains a value that is invalid for its mapped layer. A standard single-output print job was queued instead.', 'overcustomise' ) );
					return null;
				}
				$row_values[ $layer_id ] = $value;
			}
			$normalised_rows[] = [ 'row' => $row, 'values' => $row_values ];
		}

		$areas = array_values( array_filter(
			OC_DB::get_design_print_areas( $design_id ),
			static fn( object $area ): bool => ! isset( $area->visible ) || (bool) $area->visible
		) );
		if ( empty( $areas ) ) {
			return null;
		}

		$queued   = 0;
		$failed   = 0;
		$queue_ids = [];
		foreach ( $normalised_rows as $row_offset => $row_data ) {
			$row_index    = $row_offset + 1;
			$layer_inputs = is_array( $customisation['layers'] ?? null ) ? $customisation['layers'] : [];
			foreach ( $row_data['values'] as $layer_id => $value ) {
				$layer_inputs[ $layer_id ] = is_array( $layer_inputs[ $layer_id ] ?? null ) ? $layer_inputs[ $layer_id ] : [];
				$layer_inputs[ $layer_id ]['value'] = $value;
			}

			$merged_customisation               = $customisation;
			$merged_customisation['layers']     = $layer_inputs;
			$merged_customisation['renderSpec'] = OC_Render_Spec::build( $design_id, $layer_inputs );
			$row_key = hash( 'sha256', (string) wp_json_encode( $row_data['row'] ) );

			foreach ( $areas as $area_row ) {
				$area_data = self::build_v2_area_data( $design_id, (int) $area_row->id, $merged_customisation );
				$area      = self::area_object_for_generation( $area_row, $area_data );
				if ( ! self::area_has_printable_data( $area_data ) ) {
					$order->add_order_note( sprintf( __( 'OverCustomise skipped VDP row %1$d, print area "%2$s": no printable data was found.', 'overcustomise' ), $row_index, $area->label ?: $area->area_key ) );
					continue;
				}

				$print_file_id = OC_DB::insert_print_file( [
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
				] );
				if ( ! $print_file_id ) {
					$failed++;
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
					$queued++;
					$queue_ids[] = $queue_id;
				} else {
					$failed++;
				}
			}
		}

		return [ 'queued' => $queued, 'failed' => $failed, 'queue_ids' => $queue_ids ];
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

	/** Queue an order for idempotent generation after all line-item writes finish. */
	public function defer_order_generation( int $order_id, $order = null ): void {
		if ( $order_id > 0 ) {
			$this->deferred_order_ids[ $order_id ] = true;
		}
	}

	/** Process deferred admin/API orders at shutdown. */
	public function generate_deferred_orders(): void {
		foreach ( array_keys( $this->deferred_order_ids ) as $order_id ) {
			$this->generate_for_order_id( (int) $order_id );
		}
		$this->deferred_order_ids = [];
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
			if ( in_array( $type, [ 'image', 'clipmask' ], true ) && absint( $input['attachmentId'] ?? $layer['artworkAttachmentId'] ?? 0 ) > 0 ) {
				return true;
			}
			if ( 'clipart' === $type && ( absint( $input['clipartId'] ?? 0 ) > 0 || ! empty( $layer['artworkPath'] ) ) ) {
				return true;
			}
		}

		return false;
	}

	private static function print_file_exists( int $order_id, int $item_id, int $print_area_id, string $area_source, int $row_index ): bool {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$wpdb->prefix}oc_print_files WHERE order_id = %d AND order_item_id = %d AND print_area_id = %d AND area_source = %s AND row_index = %d LIMIT 1",
			$order_id,
			$item_id,
			$print_area_id,
			$area_source,
			$row_index
		) );
	}

	/** Return whether creation has already attached a queue job to this exact file row. */
	private static function queue_job_exists_for_file( int $print_file_id ): bool {
		global $wpdb;
		if ( $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$wpdb->prefix}oc_print_queue WHERE print_file_id = %d LIMIT 1",
			$print_file_id
		) ) ) {
			return true;
		}

		$record = OC_DB::get_print_file( $print_file_id );
		if ( ! $record ) {
			return false;
		}

		$payloads = $wpdb->get_col( $wpdb->prepare(
			"SELECT area_data FROM {$wpdb->prefix}oc_print_queue
			 WHERE order_id = %d AND order_item_id = %d AND area_data LIKE %s",
			(int) $record->order_id,
			(int) $record->order_item_id,
			'%' . $wpdb->esc_like( '"__combined_print_areas"' ) . '%'
		) ) ?: [];

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
		$jobs = $wpdb->get_results( $wpdb->prepare(
			"SELECT area_data, print_file_id FROM {$wpdb->prefix}oc_print_queue
			 WHERE print_file_id = %d OR (order_id = %d AND order_item_id = %d)
			 ORDER BY (print_file_id = %d) DESC, id DESC",
			(int) $record->id,
			(int) $record->order_id,
			(int) $record->order_item_id,
			(int) $record->id
		) ) ?: [];

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
		$jobs = $wpdb->get_col( $wpdb->prepare(
			"SELECT area_data FROM {$wpdb->prefix}oc_print_queue
			 WHERE order_id = %d AND order_item_id = %d ORDER BY id DESC",
			(int) $record->order_id,
			(int) $record->order_item_id
		) ) ?: [];

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
				$snapshot = json_decode( (string) ( $file->area_snapshot ?? '' ), true );
				if ( ! is_array( $snapshot ) || empty( $snapshot ) ) {
					$snapshot = is_array( $entry['areaSnapshot'] ?? null ) ? $entry['areaSnapshot'] : [];
				}
				if ( empty( $snapshot ) ) {
					$valid = false;
					break;
				}
				$contains_record = $contains_record || $file_id === (int) $record->id;
				$entries[] = [
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

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}oc_print_files WHERE order_id = %d AND order_item_id = %d AND area_source = %s AND row_index = %d AND print_area_id IN ({$placeholders}) ORDER BY id ASC",
			...$params
		) ) ?: [];
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

			foreach ( OC_DB::get_design_print_areas( $design_id ) as $area ) {
				if ( isset( $area->visible ) && ! (bool) $area->visible ) {
					continue;
				}
				if ( sanitize_key( (string) $area->print_method ) !== $method ) {
					continue;
				}

				$area_data = self::build_v2_area_data( $design_id, (int) $area->id, $customisation );
				if ( ! self::area_has_printable_data( $area_data ) ) {
					continue;
				}

				if ( ! self::print_file_exists( (int) $record->order_id, (int) $record->order_item_id, (int) $area->id, (string) $record->area_source, (int) $record->row_index ) ) {
					continue;
				}

				$entries[] = [
					'area'      => self::area_object_for_generation( $area, $area_data ),
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
							'printFileId' => (int) $job['print_file_id'],
							'areaId'      => (int) $job['area_id'],
							'areaSource'  => (string) $job['area_source'],
							'rowIndex'    => (int) $job['row_index'],
							'areaSnapshot' => $job['area_snapshot'],
							'areaData'    => $job['area_data'],
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
				$queued += count( $jobs );
				$queue_ids[] = $queue_id;
			} else {
				$failed += count( $jobs );
			}
		}

		return [ 'queued' => $queued, 'failed' => $failed, 'queue_ids' => $queue_ids ];
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

	/** Use the order-time renderSpec area snapshot for generation, falling back to the current DB row. */
	public static function area_object_for_generation( object $current_area, array $area_data ): object {
		$snapshot = is_array( $area_data['renderSpecArea'] ?? null ) ? $area_data['renderSpecArea'] : [];
		$bounds   = is_array( $snapshot['bounds'] ?? null ) ? $snapshot['bounds'] : [];

		if ( empty( $snapshot ) || empty( $bounds ) ) {
			return $current_area;
		}

		$area = clone $current_area;
		$area->id              = (int) ( $snapshot['id'] ?? $current_area->id ?? 0 );
		$area->area_key        = (string) ( $snapshot['areaKey'] ?? $current_area->area_key ?? '' );
		$area->label           = (string) ( $snapshot['label'] ?? $current_area->label ?? '' );
		$area->print_method    = (string) ( $snapshot['printMethod'] ?? $current_area->print_method ?? '' );
		$area->canvas_unit     = (string) ( $snapshot['unit'] ?? $current_area->canvas_unit ?? 'px' );
		$area->canvas_x        = (float) ( $bounds['x'] ?? $current_area->canvas_x ?? 0 );
		$area->canvas_y        = (float) ( $bounds['y'] ?? $current_area->canvas_y ?? 0 );
		$area->canvas_w        = (float) ( $bounds['w'] ?? $current_area->canvas_w ?? 1 );
		$area->canvas_h        = (float) ( $bounds['h'] ?? $current_area->canvas_h ?? 1 );
		$area->canvas_dpi      = (int) ( $bounds['dpi'] ?? $current_area->canvas_dpi ?? 300 );
		$area->canvas_rotation = (float) ( $bounds['rotation'] ?? $current_area->canvas_rotation ?? 0 );

		return $area;
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
				$order = wc_get_order( (int) $record->order_id );
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
		$jobs = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}oc_print_queue
			 WHERE order_id = %d
			 AND status IN ('pending', 'failed')
			 ORDER BY created_at ASC",
			$order_id
		) ) ?: [];

		$processed = 0;
		foreach ( $jobs as $job ) {
			if ( 'failed' === $job->status ) {
				if ( ! OC_Print_Queue::instance()->retry_job( (int) $job->id ) ) {
					continue;
				}
			}
			OC_Print_Queue::instance()->process_one( (int) $job->id );
			$processed++;
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
		header( 'Content-Type: '        . $mime_type );
		header( sprintf(
			'Content-Disposition: attachment; filename="%s"; filename*=UTF-8\'\'%s',
			$ascii_fallback,
			$utf8_encoded
		) );
		header( 'Content-Length: '      . filesize( $target_real ) );
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
		$mime   = is_array( $info ) ? (string) ( $info['mime'] ?? '' ) : '';
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
