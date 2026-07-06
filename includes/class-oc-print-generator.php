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

	public function register(): void {
		// Primary: order created at checkout.
		add_action( 'woocommerce_checkout_order_created', [ $this, 'generate_for_order' ], 20, 1 );

		// Fallback: catches orders created through admin/API flows that do not fire checkout hooks.
		add_action( 'woocommerce_new_order', [ $this, 'generate_for_order_id' ], 30, 2 );

		// Admin handlers.
		add_action( 'admin_init', [ $this, 'handle_admin_download' ] );
		add_action( 'admin_init', [ $this, 'handle_admin_regenerate' ] );
		add_action( 'admin_init', [ $this, 'handle_admin_generate_missing' ] );
		add_action( 'admin_init', [ $this, 'handle_admin_process_queue' ] );
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

		// Look up the print area — try the v2 design table first, fall back to legacy.
		global $wpdb;
		$area = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}oc_design_print_areas WHERE id = %d LIMIT 1",
			$record->print_area_id
		) );
		$is_v2_area = (bool) $area;

		if ( ! $area ) {
			$area = $wpdb->get_row( $wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}oc_print_areas WHERE id = %d LIMIT 1",
				$record->print_area_id
			) );
		}

		if ( ! $area ) {
			throw new \RuntimeException( "Print area #{$record->print_area_id} not found." );
		}

		if ( $is_v2_area ) {
			$area_data = self::build_v2_area_data( (int) $area->design_id, (int) $area->id, $customisation );
			$area      = self::area_object_for_generation( $area, $area_data );
		} else {
			$area_data = $customisation[ $area->area_key ] ?? null;
		}

		if ( ! is_array( $area_data ) ) {
			throw new \RuntimeException( "No customisation data for area '{$area->area_key}'." );
		}

		// Delete the old file if it still exists.
		if ( ! empty( $record->file_path ) && file_exists( $record->file_path ) ) {
			@unlink( $record->file_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		// Update retention dates.
		$retention_days = (int) OC_Admin_Settings::get( 'file_retention_days' ) ?: 90;
		$now            = current_time( 'mysql', true );
		$expires_at     = gmdate( 'Y-m-d H:i:s', strtotime( "+{$retention_days} days", strtotime( $now ) ) );

		OC_DB::update_print_file( $file_id, [
			'file_status'  => 'generating',
			'generated_at' => $now,
			'expires_at'   => $expires_at,
			'file_path'    => null,
		] );

		$result = self::generate_for_area( $order, (int) $record->order_item_id, $area, $area_data );

		$thumb_path = self::maybe_generate_thumbnail( $result['file_path'] );

		OC_DB::update_print_file( $file_id, [
			'file_path'      => $result['file_path'],
			'file_status'    => $result['status'],
			'thumbnail_path' => $thumb_path,
		] );

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

	/** Generate print files for all customised items in a new order. */
	public function generate_for_order( \WC_Order $order ): void {
		$retention_days = (int) OC_Admin_Settings::get( 'file_retention_days' ) ?: 90;
		$now            = current_time( 'mysql', true );
		$expires_at     = gmdate( 'Y-m-d H:i:s', strtotime( "+{$retention_days} days", strtotime( $now ) ) );

		$created_count = 0;

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

				// Check for active VDP template.
				$vdp = new OC_VDP();
				if ( $vdp->is_enabled( $design_id ) ) {
					$template = $vdp->get_template( $design_id );
					if ( is_array( $template ) && '' !== $template['csv_file_path'] && file_exists( $template['csv_file_path'] ) ) {
						$csv_data = $vdp->parse_csv( $template['csv_file_path'] );
						$rows     = $csv_data['rows'] ?? [];

						if ( ! empty( $rows ) ) {
							$areas = OC_DB::get_design_print_areas( $design_id );
							$layer_map = [];
							foreach ( $template['fields'] as $field ) {
								$layer_map[ (int) $field['layer_id'] ] = $field['field_name'];
							}

							foreach ( $rows as $row ) {
								foreach ( $areas as $area ) {
									$area_data = self::build_v2_area_data( $design_id, (int) $area->id, $customisation );
									$area      = self::area_object_for_generation( $area, $area_data );

									// Merge VDP field values into text layers.
									$layer_inputs = is_array( $customisation['layers'] ?? null ) ? $customisation['layers'] : [];
									$all_layers   = OC_DB::get_design_layers( $design_id );
									foreach ( $all_layers as $layer ) {
										if ( (int) $layer->area_id !== (int) $area->id ) {
											continue;
										}
										$layer_lid = (int) $layer->id;
										if ( isset( $layer_map[ $layer_lid ] ) && isset( $layer_inputs[ $layer_lid ] ) ) {
											$field_name = $layer_map[ $layer_lid ];
											if ( isset( $row[ $field_name ] ) ) {
												$merged_value = $vdp->merge_values( $row[ $field_name ], $row );
												$layer_inputs[ $layer_lid ]['value'] = $merged_value;
											}
										}
									}

									$merged_customisation = $customisation;
									$merged_customisation['layers'] = $layer_inputs;
									$merged_customisation['renderSpec'] = OC_Render_Spec::build( $design_id, $layer_inputs );
									$area_data = self::build_v2_area_data( $design_id, (int) $area->id, $merged_customisation );
									$area      = self::area_object_for_generation( $area, $area_data );

									if ( ! self::area_has_printable_data( $area_data ) ) {
										$order->add_order_note( sprintf( __( 'OverCustomise skipped print area "%s": no printable customer data was found.', 'overcustomise' ), $area->label ?: $area->area_key ) );
										continue;
									}
									if ( self::print_file_exists( (int) $order->get_id(), (int) $item_id, (int) $area->id ) ) {
										continue;
									}

									OC_DB::insert_print_file( [
										'order_id'      => $order->get_id(),
										'order_item_id' => (int) $item_id,
										'print_area_id' => (int) $area->id,
										'file_type'     => $area->print_method,
										'file_status'   => 'pending',
										'generated_at'  => $now,
										'expires_at'    => $expires_at,
									] );

									OC_Print_Queue::instance()->enqueue(
										(int) $order->get_id(),
										(int) $item_id,
										(int) $area->id,
										$area_data,
										(string) $area->print_method
									);
									$created_count++;
								}
							}
						}
						continue;
					}
				}

				$areas = OC_DB::get_design_print_areas( $design_id );
				foreach ( $areas as $area ) {
					$area_data = self::build_v2_area_data( $design_id, (int) $area->id, $customisation );
					$area      = self::area_object_for_generation( $area, $area_data );
					if ( ! self::area_has_printable_data( $area_data ) ) {
						$order->add_order_note( sprintf( __( 'OverCustomise skipped print area "%s": no printable customer data was found.', 'overcustomise' ), $area->label ?: $area->area_key ) );
						continue;
					}
					if ( self::print_file_exists( (int) $order->get_id(), (int) $item_id, (int) $area->id ) ) {
						continue;
					}

					OC_DB::insert_print_file( [
						'order_id'      => $order->get_id(),
						'order_item_id' => (int) $item_id,
						'print_area_id' => (int) $area->id,
						'file_type'     => $area->print_method,
						'file_status'   => 'pending',
						'generated_at'  => $now,
						'expires_at'    => $expires_at,
					] );

					OC_Print_Queue::instance()->enqueue(
						(int) $order->get_id(),
						(int) $item_id,
						(int) $area->id,
						$area_data,
						(string) $area->print_method
					);
					$created_count++;
				}
				continue;
			}

			// v1 (legacy) ────────────────────────────────────────────────────
			$product_id = (int) $item->get_product_id();
			$config     = OC_DB::get_config_by_product( $product_id );

			if ( ! $config ) {
				continue;
			}

			$areas = OC_DB::get_print_areas( (int) $config->id );

			foreach ( $areas as $area ) {
				$area_data = $customisation[ $area->area_key ] ?? null;

				if ( null === $area_data || ! is_array( $area_data ) ) {
					continue;
				}
				if ( self::print_file_exists( (int) $order->get_id(), (int) $item_id, (int) $area->id ) ) {
					continue;
				}

				OC_DB::insert_print_file( [
					'order_id'      => $order->get_id(),
					'order_item_id' => (int) $item_id,
					'print_area_id' => (int) $area->id,
					'file_type'     => $area->print_method,
					'file_status'   => 'pending',
					'generated_at'  => $now,
					'expires_at'    => $expires_at,
				] );

				OC_Print_Queue::instance()->enqueue(
					(int) $order->get_id(),
					(int) $item_id,
					(int) $area->id,
					$area_data,
					(string) $area->print_method
				);
				$created_count++;
			}
		}

		if ( $created_count > 0 ) {
			$order->add_order_note( sprintf( __( 'OverCustomise queued %d print file(s) for generation.', 'overcustomise' ), $created_count ) );
			$this->schedule_queue_processing();
		}
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

	private function schedule_queue_processing(): void {
		if ( ! wp_next_scheduled( 'oc_process_print_queue' ) ) {
			wp_schedule_single_event( time() + 1, 'oc_process_print_queue' );
		}
	}

	private static function area_has_printable_data( array $area_data ): bool {
		foreach ( [ 'text', 'artworkAttachmentId', 'artworkPath' ] as $key ) {
			if ( ! empty( $area_data[ $key ] ) ) {
				return true;
			}
		}

		return false;
	}

	private static function print_file_exists( int $order_id, int $item_id, int $print_area_id ): bool {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$wpdb->prefix}oc_print_files WHERE order_id = %d AND order_item_id = %d AND print_area_id = %d LIMIT 1",
			$order_id,
			$item_id,
			$print_area_id
		) );
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
			$stored_area_data = OC_Render_Spec::area_from_spec( $stored_spec, $area_id );
			if ( ! empty( $stored_area_data ) && self::area_has_printable_data( $stored_area_data ) ) {
				return $stored_area_data;
			}
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
		$area->canvas_rotation = (float) ( $bounds['rotation'] ?? $current_area->canvas_rotation ?? 0 );

		return $area;
	}

	private static function normalise_v2_layer_font_inputs( int $design_id, array $layer_inputs ): array {
		$fallback_font_id = self::first_active_font_id();

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

	private static function first_active_font_id(): int {
		$fonts = OC_DB::get_fonts( true );
		$first = is_array( $fonts ) && ! empty( $fonts ) ? reset( $fonts ) : null;

		return is_object( $first ) && ! empty( $first->id ) ? absint( $first->id ) : 0;
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

		try {
			$this->regenerate( $file_id );
		} catch ( \Throwable $e ) {
			OC_Logger::error( 'Admin regenerate failed for file #' . $file_id . ': ' . $e->getMessage() );
		}

		// Redirect back to the order edit screen.
		$redirect = admin_url( 'post.php?post=' . (int) $record->order_id . '&action=edit' );
		wp_safe_redirect( $redirect );
		exit;
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

		$redirect = wp_get_referer() ?: admin_url( 'post.php?post=' . $order_id . '&action=edit' );
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

		foreach ( $jobs as $job ) {
			if ( 'failed' === $job->status ) {
				OC_DB::update_queue_job( (int) $job->id, [
					'status'        => 'pending',
					'attempts'      => 0,
					'error_message' => null,
					'processed_at'  => null,
				] );
			}
			OC_Print_Queue::instance()->process_one( (int) $job->id );
		}

		$order->add_order_note( sprintf( __( 'OverCustomise manually processed %d print queue job(s).', 'overcustomise' ), count( $jobs ) ) );

		$redirect = wp_get_referer() ?: admin_url( 'post.php?post=' . $order_id . '&action=edit' );
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

		if ( ! $record || 'files_ready' !== $record->file_status ) {
			wp_die( esc_html__( 'File not available.', 'overcustomise' ), 404 );
		}

		if ( empty( $record->file_path ) || ! file_exists( $record->file_path ) ) {
			wp_die( esc_html__( 'File not found on disk.', 'overcustomise' ), 404 );
		}

		// Defence-in-depth: file path must resolve inside the uploads directory.
		$upload      = wp_upload_dir();
		$base_real   = realpath( $upload['basedir'] );
		$target_real = realpath( $record->file_path );
		if ( ! $base_real || ! $target_real || 0 !== strpos( $target_real, $base_real ) ) {
			wp_die( esc_html__( 'Invalid file path.', 'overcustomise' ), 400 );
		}

		$filename  = basename( $record->file_path );
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
		header( 'Content-Length: '      . filesize( $record->file_path ) );
		header( 'Cache-Control: private, no-cache, no-store, must-revalidate' );
		header( 'Pragma: no-cache' );
		header( 'X-Content-Type-Options: nosniff' );

		// Disable any output buffering to avoid memory issues with large files.
		while ( ob_get_level() ) {
			ob_end_clean();
		}

		readfile( $record->file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
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
