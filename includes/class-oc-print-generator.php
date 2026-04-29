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
 *  5. Update the record: file_path + status (files_ready / awaiting_dst_upload).
 *  6. On failure: set status back to 'pending', log the error.
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

class OC_Print_Generator {

	public function register(): void {
		// Primary: order created at checkout.
		add_action( 'woocommerce_checkout_order_created', [ $this, 'generate_for_order' ], 20, 1 );

		// Admin handlers.
		add_action( 'admin_init', [ $this, 'handle_admin_download' ] );
		add_action( 'admin_init', [ $this, 'handle_admin_regenerate' ] );
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
		$target_item = null;
		foreach ( $order->get_items() as $item_id => $item ) {
			if ( (int) $item_id === (int) $record->order_item_id ) {
				$target_item = $item;
				break;
			}
		}

		if ( null === $target_item ) {
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

		$result = $this->dispatch( $order, (int) $record->order_item_id, $area, $area_data );

		OC_DB::update_print_file( $file_id, [
			'file_path'   => $result['file_path'],
			'file_status' => $result['status'],
		] );

		return $result;
	}

	/** Generate print files for all customised items in a new order. */
	public function generate_for_order( \WC_Order $order ): void {
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

				$areas = OC_DB::get_design_print_areas( $design_id );
				foreach ( $areas as $area ) {
					$area_data = self::build_v2_area_data( $design_id, (int) $area->id, $customisation );
					if ( empty( $area_data['text'] ) && empty( $area_data['artworkAttachmentId'] ) ) {
						continue;
					}
					$this->generate_for_area( $order, (int) $item_id, $area, $area_data );
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

				$this->generate_for_area( $order, (int) $item_id, $area, $area_data );
			}
		}
	}

	/**
	 * Collapse v2 per-layer customisation inputs for a single design print area
	 * into the v1 area_data shape the type-specific generators still consume.
	 *
	 * v2 shape: { layers: { layerId: {type, value, fontId, colorHex, attachmentId, ...} } }
	 * Output:   { text, fontId, color, artworkAttachmentId }
	 */
	private static function build_v2_area_data( int $design_id, int $area_id, array $customisation ): array {
		$layer_inputs = is_array( $customisation['layers'] ?? null ) ? $customisation['layers'] : [];
		$all_layers   = OC_DB::get_design_layers( $design_id );

		$text_parts   = [];
		$font_id      = 0;
		$color        = '';
		$attachment   = 0;
		$artwork_path = '';

		foreach ( $all_layers as $layer ) {
			if ( (int) $layer->area_id !== $area_id ) {
				continue;
			}
			$input = $layer_inputs[ (int) $layer->id ] ?? null;
			if ( ! is_array( $input ) ) {
				continue;
			}

			switch ( $layer->type ) {
				case 'text':
				case 'textarea':
				case 'spotify':
					$val = trim( (string) ( $input['value'] ?? '' ) );
					if ( '' !== $val ) {
						$text_parts[] = $val;
					}
					if ( ! $font_id && ! empty( $input['fontId'] ) ) {
						$font_id = (int) $input['fontId'];
					}
					if ( '' === $color && ! empty( $input['colorHex'] ) ) {
						$color = (string) $input['colorHex'];
					}
					break;

				case 'image':
				case 'clipart':
				case 'lineart':
					if ( ! $attachment && ! empty( $input['attachmentId'] ) ) {
						$attachment = (int) $input['attachmentId'];
					}
					if ( '' === $artwork_path && 'clipart' === $layer->type ) {
						$clipart_id  = (int) ( $input['clipartId'] ?? 0 );
						$clipart_url = is_string( $input['clipartUrl'] ?? null ) ? (string) $input['clipartUrl'] : '';
						$artwork_path = self::resolve_clipart_path( $clipart_id, $clipart_url );
					}
					break;
			}
		}

		return [
			'text'                => implode( "\n", $text_parts ),
			'fontId'              => $font_id,
			'color'               => '' !== $color ? $color : '#000000',
			'artworkAttachmentId' => $attachment,
			'artworkPath'         => $artwork_path,
		];
	}

	/**
	 * Resolve a clipart file path from clipart DB id or clipart URL.
	 */
	private static function resolve_clipart_path( int $clipart_id, string $clipart_url ): string {
		global $wpdb;

		if ( $clipart_id > 0 ) {
			$file_path = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT file_path FROM {$wpdb->prefix}oc_clipart WHERE id = %d LIMIT 1",
					$clipart_id
				)
			);
			if ( is_string( $file_path ) && '' !== $file_path ) {
				$real = realpath( $file_path );
				if ( $real && file_exists( $real ) ) {
					return $real;
				}
			}
		}

		if ( '' !== $clipart_url ) {
			$uploads = wp_upload_dir();
			$baseurl = isset( $uploads['baseurl'] ) ? rtrim( (string) $uploads['baseurl'], '/' ) : '';
			$basedir = isset( $uploads['basedir'] ) ? rtrim( (string) $uploads['basedir'], '/\\' ) : '';
			if ( '' !== $baseurl && '' !== $basedir && 0 === strpos( $clipart_url, $baseurl . '/' ) ) {
				$relative = ltrim( substr( $clipart_url, strlen( $baseurl ) ), '/' );
				$candidate = $basedir . '/' . $relative;
				$real = realpath( $candidate );
				if ( $real && file_exists( $real ) ) {
					return $real;
				}
			}
		}

		return '';
	}

	/** Generate (or attempt to generate) the print file for one area. */
	private function generate_for_area(
		\WC_Order $order,
		int $item_id,
		object $area,
		array $area_data
	): void {
		$retention_days = (int) OC_Admin_Settings::get( 'file_retention_days' ) ?: 90;
		$now            = current_time( 'mysql', true );
		$expires_at     = gmdate( 'Y-m-d H:i:s', strtotime( "+{$retention_days} days", strtotime( $now ) ) );

		// Reserve a DB row before generation so any failure can be recorded.
		$record_id = OC_DB::insert_print_file( [
			'order_id'      => $order->get_id(),
			'order_item_id' => $item_id,
			'print_area_id' => (int) $area->id,
			'file_type'     => $area->print_method,
			'file_status'   => 'generating',
			'generated_at'  => $now,
			'expires_at'    => $expires_at,
		] );

		if ( ! $record_id ) {
			OC_Logger::error( "OC_Print_Generator: could not insert print file record for item {$item_id}, area {$area->area_key}." );
			return;
		}

		try {
			[ 'file_path' => $file_path, 'status' => $status ] = $this->dispatch( $order, $item_id, $area, $area_data );

			OC_DB::update_print_file( $record_id, [
				'file_path'   => $file_path,
				'file_status' => $status,
			] );

			OC_Logger::info( "Print file generated: {$file_path} (status: {$status})" );

		} catch ( \Throwable $e ) {
			OC_Logger::error( sprintf(
				'Print file generation failed — order %d, item %d, area %s: %s',
				$order->get_id(),
				$item_id,
				$area->area_key,
				$e->getMessage()
			) );

			OC_DB::update_print_file( $record_id, [ 'file_status' => 'pending' ] );
		}
	}

	/**
	 * Dispatch to the correct type-specific generator.
	 *
	 * @return array{file_path:string, status:string}
	 * @throws \RuntimeException
	 */
	private function dispatch(
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
			'dst', 'emb',
			'jef', 'vp3',
			'pes', 'xxx'    => 'application/octet-stream',
			'png'           => 'image/png',
			'jpg', 'jpeg'   => 'image/jpeg',
			default         => 'application/octet-stream',
		};
	}
}
