<?php
/**
 * Cart integration — stores customisation data, applies flat rate fee,
 * displays summary in cart, and persists to HPOS-compatible orders.
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

class OC_Cart {

	public function register(): void {
		// Store customisation in the cart item.
		add_filter( 'woocommerce_add_cart_item_data', [ $this, 'add_cart_item_data' ], 10, 3 );

		// Display summary lines in cart and checkout.
		add_filter( 'woocommerce_get_item_data', [ $this, 'display_item_data' ], 10, 2 );

		// Replace product thumbnail with personalised preview in cart/checkout.
		add_filter( 'woocommerce_cart_item_thumbnail', [ $this, 'cart_item_thumbnail' ], 10, 3 );
		add_filter( 'woocommerce_store_api_cart_item_images', [ $this, 'store_api_cart_item_images' ], 10, 3 );

		// Apply flat rate fee.
		add_action( 'woocommerce_cart_calculate_fees', [ $this, 'add_flat_rate_fee' ], 10 );

		// Persist to order item meta (HPOS-compatible).
		add_action( 'woocommerce_checkout_create_order_line_item', [ $this, 'save_to_order_item' ], 10, 4 );

		// Hide internal production data from WooCommerce's default item meta table.
		add_filter( 'woocommerce_hidden_order_itemmeta', [ $this, 'hidden_order_item_meta' ] );

		// Display preview + details in admin and frontend order pages.
		add_action( 'woocommerce_order_item_meta_end', [ $this, 'display_in_order' ], 10, 3 );
		add_action( 'woocommerce_before_order_itemmeta', [ $this, 'display_in_admin_order_item' ], 10, 3 );
	}

	// -------------------------------------------------------------------------
	// Add to cart
	// -------------------------------------------------------------------------

	public function add_cart_item_data( array $cart_item_data, int $product_id, int $variation_id ): array {
		$raw = isset( $_POST['_oc_customisation'] ) ? wp_unslash( $_POST['_oc_customisation'] ) : '';

		if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
			return $cart_item_data;
		}

		// Hard cap on payload size to prevent memory abuse via oversized JSON.
		if ( strlen( $raw ) > 256 * 1024 ) {
			return $cart_item_data;
		}

		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			return $cart_item_data;
		}

		$valid_layer_types = [ 'text', 'textarea', 'image', 'spotify', 'lineart', 'clipart' ];

		// ── v2 format: { v:2, designId, layers:{layerId:{type,...}} } ────────
		if ( isset( $decoded['v'] ) && 2 === (int) $decoded['v'] ) {
			$design_id = absint( $decoded['designId'] ?? 0 );
			if ( ! $design_id ) return $cart_item_data;

			$design = OC_DB::get_design( $design_id );
			if ( ! $design || ! (bool) $design->active ) return $cart_item_data;

			$design_layers = [];
			foreach ( OC_DB::get_design_layers( $design_id ) as $layer ) {
				$layer_id = isset( $layer->id ) ? absint( $layer->id ) : 0;
				if ( ! $layer_id ) {
					continue;
				}
				$layer_type = isset( $layer->type ) ? sanitize_key( (string) $layer->type ) : '';
				if ( '' === $layer_type ) {
					continue;
				}
				$design_layers[ $layer_id ] = $layer_type;
			}
			if ( empty( $design_layers ) ) {
				return $cart_item_data;
			}

			// Reject if layers isn't actually an array.
			if ( ! isset( $decoded['layers'] ) || ! is_array( $decoded['layers'] ) ) {
				return $cart_item_data;
			}

			$sanitised_layers = [];
			foreach ( $decoded['layers'] as $layer_id => $layer_data ) {
				if ( ! is_array( $layer_data ) ) continue;

				$layer_key = absint( $layer_id );
				if ( ! $layer_key ) continue;
				if ( ! isset( $design_layers[ $layer_key ] ) ) continue;

				$type = $design_layers[ $layer_key ];
				if ( ! in_array( $type, $valid_layer_types, true ) ) {
					continue;
				}

				$sanitised_layers[ $layer_key ] = [
					'type'          => $type,
					'value'         => is_scalar( $layer_data['value'] ?? null ) ? sanitize_text_field( (string) $layer_data['value'] ) : '',
					'fontId'        => absint( $layer_data['fontId']       ?? 0 ),
					'colorHex'      => sanitize_hex_color( is_string( $layer_data['colorHex'] ?? null ) ? $layer_data['colorHex'] : '#000000' ) ?: '#000000',
					'attachmentId'  => absint( $layer_data['attachmentId'] ?? 0 ),
					'clipartId'     => absint( $layer_data['clipartId']    ?? 0 ),
					'clipartUrl'    => is_string( $layer_data['clipartUrl'] ?? null ) ? esc_url_raw( $layer_data['clipartUrl'] ) : '',
				];
			}

			if ( empty( $sanitised_layers ) ) return $cart_item_data;

			$cart_item_data['_oc_customisation'] = [
				'v'                  => 2,
				'designId'           => $design_id,
				'engravingUndertone' => OC_DB::sanitize_engraving_undertone( (string) ( $decoded['engravingUndertone'] ?? 'warm' ) ),
				'layers'             => $sanitised_layers,
			];
			$cart_item_data['_oc_design_id']     = $design_id;
			$cart_item_data['_oc_flat_rate']     = (float) $design->flat_rate;
			$cart_item_data['_oc_unique_key']    = md5( $raw . microtime() );

			// Preview image URL (uploaded by JS before form submit).
			$preview_url = $decoded['previewUrl'] ?? '';
			if ( is_string( $preview_url ) && '' !== $preview_url ) {
				$safe_preview_url = $this->validate_preview_url( $preview_url );
				if ( '' !== $safe_preview_url ) {
					$cart_item_data['_oc_preview_url'] = $safe_preview_url;
				}
			}

			return $cart_item_data;
		}

		// ── Legacy v1 format (old oc_product_configs system) ─────────────────
		$config = OC_DB::get_config_by_product( $product_id );
		if ( ! $config || ! (int) $config->active ) return $cart_item_data;

		$sanitised = [];
		foreach ( $decoded as $area_key => $area_data ) {
			if ( ! is_array( $area_data ) ) continue;
			$safe_key = is_scalar( $area_key ) ? sanitize_key( (string) $area_key ) : '';
			if ( '' === $safe_key ) continue;
			$sanitised[ $safe_key ] = [
				'text'               => is_scalar( $area_data['text'] ?? null ) ? sanitize_text_field( (string) $area_data['text'] ) : '',
				'fontId'             => absint( $area_data['fontId'] ?? 0 ),
				'color'              => sanitize_hex_color( is_string( $area_data['color'] ?? null ) ? $area_data['color'] : '#000000' ) ?: '#000000',
				'artworkAttachmentId' => absint( $area_data['artworkAttachmentId'] ?? 0 ),
			];
		}
		if ( empty( $sanitised ) ) return $cart_item_data;

		$cart_item_data['_oc_customisation'] = $sanitised;
		$cart_item_data['_oc_config_id']     = (int) $config->id;
		$cart_item_data['_oc_flat_rate']     = (float) $config->flat_rate;
		$cart_item_data['_oc_unique_key']    = md5( $raw . microtime() );
		return $cart_item_data;
	}

	/** Ensure preview URLs point to plugin-generated preview files only. */
	private function validate_preview_url( string $preview_url ): string {
		$sanitised_url = esc_url_raw( $preview_url );
		if ( '' === $sanitised_url ) {
			return '';
		}

		$uploads = wp_upload_dir();
		$baseurl = isset( $uploads['baseurl'] ) ? rtrim( (string) $uploads['baseurl'], '/' ) : '';
		if ( '' === $baseurl ) {
			return '';
		}

		$expected_prefix = $baseurl . '/overcustomise/previews/preview-';
		if ( 0 !== strpos( $sanitised_url, $expected_prefix ) ) {
			return '';
		}

		$path = wp_parse_url( $sanitised_url, PHP_URL_PATH );
		if ( ! is_string( $path ) || ! preg_match( '#/overcustomise/previews/preview-[a-f0-9]{32}\.(?:png|jpe?g)$#i', $path ) ) {
			return '';
		}

		return $sanitised_url;
	}

	// -------------------------------------------------------------------------
	// Cart / checkout display
	// -------------------------------------------------------------------------

	public function display_item_data( array $item_data, array $cart_item ): array {
		$customisation = $cart_item['_oc_customisation'] ?? null;
		if ( empty( $customisation ) ) {
			return $item_data;
		}

		// ── v2 format ────────────────────────────────────────────────────────────
		if ( isset( $customisation['v'] ) && 2 === (int) $customisation['v'] ) {
			$design_id = (int) ( $customisation['designId'] ?? $cart_item['_oc_design_id'] ?? 0 );
			$layers    = $customisation['layers'] ?? [];

			// Build label map from design layers.
			$layer_map = [];
			if ( $design_id ) {
				foreach ( OC_DB::get_design_layers( $design_id ) as $l ) {
					$layer_map[ (int) $l->id ] = $l;
				}
			}

			foreach ( $layers as $layer_id => $layer_data ) {
				if ( ! is_array( $layer_data ) ) continue;
				$layer = $layer_map[ (int) $layer_id ] ?? null;
				$label = $layer ? ( $layer->label ?: ucfirst( $layer->type ) ) : ucfirst( $layer_data['type'] ?? 'Layer' );
				$value = $this->layer_display_value( $layer_data );
				if ( ! $value ) continue;
				$item_data[] = [ 'key' => $label, 'value' => $value ];
			}

			$cart_item_key = '';
			if ( function_exists( 'WC' ) && WC() && WC()->cart ) {
				foreach ( WC()->cart->get_cart() as $key => $ci ) {
					if ( isset( $ci['unique_key'] ) && isset( $cart_item['unique_key'] ) && $ci['unique_key'] === $cart_item['unique_key'] ) {
						$cart_item_key = $key;
						break;
					}
				}
			}
			if ( '' !== $cart_item_key && ! empty( $cart_item['product_id'] ) ) {
				$product_url = get_permalink( (int) $cart_item['product_id'] );
				if ( $product_url ) {
					$edit_url = add_query_arg( 'oc_edit_cart_key', $cart_item_key, $product_url );
					$item_data[] = [
						'key'   => '',
						'value' => '<a href="' . esc_url( $edit_url ) . '" class="oc-edit-customisation">' . esc_html__( '✏️ Edit Customisation', 'overcustomise' ) . '</a>',
					];
				}
			}

			return $item_data;
		}

		// ── v1 / legacy format ───────────────────────────────────────────────────
		foreach ( $customisation as $area_key => $area_data ) {
			if ( ! is_array( $area_data ) ) continue;

			$parts = [];
			if ( ! empty( $area_data['text'] ) ) {
				$parts[] = esc_html( $area_data['text'] );
				if ( ! empty( $area_data['fontId'] ) ) {
					global $wpdb;
					$font_name = $wpdb->get_var( $wpdb->prepare(
						"SELECT name FROM {$wpdb->prefix}oc_fonts WHERE id = %d LIMIT 1",
						$area_data['fontId']
					) );
					if ( $font_name ) $parts[] = '(' . esc_html( $font_name ) . ')';
				}
			}
			if ( ! empty( $area_data['artworkAttachmentId'] ) ) $parts[] = __( '[Artwork attached]', 'overcustomise' );
			if ( empty( $parts ) ) continue;

			$item_data[] = [
				'key'   => sprintf( __( 'Personalisation (%s)', 'overcustomise' ), ucwords( str_replace( '-', ' ', $area_key ) ) ),
				'value' => implode( ' ', $parts ),
			];
		}

		$cart_item_key = '';
		if ( function_exists( 'WC' ) && WC() && WC()->cart ) {
			foreach ( WC()->cart->get_cart() as $key => $ci ) {
				if ( isset( $ci['unique_key'] ) && isset( $cart_item['unique_key'] ) && $ci['unique_key'] === $cart_item['unique_key'] ) {
					$cart_item_key = $key;
					break;
				}
			}
		}
		if ( '' !== $cart_item_key && ! empty( $cart_item['product_id'] ) ) {
			$product_url = get_permalink( (int) $cart_item['product_id'] );
			if ( $product_url ) {
				$edit_url = add_query_arg( 'oc_edit_cart_key', $cart_item_key, $product_url );
				$item_data[] = [
					'key'   => '',
					'value' => '<a href="' . esc_url( $edit_url ) . '" class="oc-edit-customisation">' . esc_html__( '✏️ Edit Customisation', 'overcustomise' ) . '</a>',
				];
			}
		}

		return $item_data;
	}

	/** Replace the cart/checkout product thumbnail with the personalised preview. */
	public function cart_item_thumbnail( string $thumbnail, array $cart_item, string $cart_item_key ): string {
		if ( ! empty( $cart_item['_oc_preview_url'] ) ) {
			return '<img src="' . esc_url( $cart_item['_oc_preview_url'] ) . '" class="oc-cart-preview-thumb" alt="' . esc_attr__( 'Personalised preview', 'overcustomise' ) . '" />';
		}
		return $thumbnail;
	}

	/**
	 * Replace Cart/Checkout Blocks line-item image with the personalised preview.
	 *
	 * @param array  $product_images Existing Store API image objects.
	 * @param array  $cart_item      Raw cart item data.
	 * @param string $cart_item_key  Cart item key.
	 * @return array
	 */
	public function store_api_cart_item_images( array $product_images, array $cart_item, string $cart_item_key ): array {
		$preview = $cart_item['_oc_preview_url'] ?? '';
		if ( ! is_string( $preview ) || '' === $preview ) {
			return $product_images;
		}

		$preview_url = esc_url_raw( $preview );
		if ( '' === $preview_url ) {
			return $product_images;
		}

		return [
			(object) [
				'id'        => 0,
				'src'       => $preview_url,
				'thumbnail' => $preview_url,
				'srcset'    => '',
				'sizes'     => '',
				'name'      => __( 'Personalised preview', 'overcustomise' ),
				'alt'       => __( 'Personalised preview', 'overcustomise' ),
			],
		];
	}

	// -------------------------------------------------------------------------
	// Flat rate fee
	// -------------------------------------------------------------------------

	public function add_flat_rate_fee( WC_Cart $cart ): void {
		// Bail on non-AJAX admin screens to avoid double-counting.
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		$total_fee = 0.0;

		foreach ( $cart->get_cart() as $cart_item ) {
			if ( empty( $cart_item['_oc_customisation'] ) ) {
				continue;
			}

			$rate      = (float) ( $cart_item['_oc_flat_rate'] ?? 0 );
			$quantity  = (int) $cart_item['quantity'];
			$total_fee += $rate * $quantity;
		}

		if ( $total_fee > 0 ) {
			$cart->add_fee(
				__( 'Personalisation Fee', 'overcustomise' ),
				$total_fee,
				true // taxable — set to false if products are tax-exempt
			);
		}
	}

	// -------------------------------------------------------------------------
	// Order persistence (HPOS-compatible)
	// -------------------------------------------------------------------------

	public function save_to_order_item(
		WC_Order_Item_Product $item,
		string $cart_item_key,
		array $values,
		WC_Order $order
	): void {
		if ( empty( $values['_oc_customisation'] ) ) {
			return;
		}

		// update_meta_data() writes to HPOS order item tables automatically.
		$item->update_meta_data( '_oc_customisation', $values['_oc_customisation'] );
		$item->update_meta_data( '_oc_design_id',     $values['_oc_design_id']     ?? 0 );
		$item->update_meta_data( '_oc_config_id',     $values['_oc_config_id']     ?? 0 );
		$item->update_meta_data( '_oc_flat_rate',     $values['_oc_flat_rate']     ?? 0 );
		if ( ! empty( $values['_oc_preview_url'] ) ) {
			$item->update_meta_data( '_oc_preview_url', $values['_oc_preview_url'] );
			$item->update_meta_data(
				__( 'Preview Image', 'overcustomise' ),
				'<a href="' . esc_url( $values['_oc_preview_url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'View Preview Image', 'overcustomise' ) . '</a>'
			);
		}

		$details = $this->build_personalisation_details( $values['_oc_customisation'] );
		if ( '' !== $details ) {
			$item->update_meta_data( __( 'Personalisation Details', 'overcustomise' ), $details );
		}
	}

	/** Hide machine-readable metadata while leaving it available to print generation. */
	public function hidden_order_item_meta( array $hidden_meta ): array {
		return array_values( array_unique( array_merge( $hidden_meta, [
			'_oc_customisation',
			'_oc_design_id',
			'_oc_config_id',
			'_oc_flat_rate',
			'_oc_preview_url',
			'_oc_unique_key',
		] ) ) );
	}

	// -------------------------------------------------------------------------
	// Admin order item display
	// -------------------------------------------------------------------------

	public function display_in_order( int $item_id, WC_Order_Item $item, WC_Order $order ): void {
		$customisation = $item->get_meta( '_oc_customisation', true );
		$preview_url   = $item->get_meta( '_oc_preview_url', true );
		$print_files   = is_admin() ? OC_DB::get_print_files_for_item( $item_id ) : [];

		if ( ( empty( $customisation ) || ! is_array( $customisation ) ) && empty( $preview_url ) && empty( $print_files ) ) {
			return;
		}

		echo '<div class="oc-order-item-meta" style="margin-top:8px;padding:8px 10px;background:#f9f9f9;border-radius:4px;font-size:12px;line-height:1.5;">';

		// ── Preview image ─────────────────────────────────────────────────────
		if ( $preview_url ) {
			echo '<img src="' . esc_url( $preview_url ) . '" alt="' . esc_attr__( 'Personalised preview', 'overcustomise' ) . '" '
			   . 'style="display:block;max-width:120px;max-height:120px;object-fit:contain;margin-bottom:8px;border:1px solid #e0e0e0;border-radius:3px;" />';
		}

		if ( empty( $customisation ) || ! is_array( $customisation ) ) {
			$this->render_admin_print_files( $item_id, $print_files, true, $order );
			echo '</div>';
			return;
		}

		// ── v2 format ─────────────────────────────────────────────────────────
		if ( isset( $customisation['v'] ) && 2 === (int) $customisation['v'] ) {
			$design_id = (int) ( $customisation['designId'] ?? $item->get_meta( '_oc_design_id', true ) ?? 0 );
			$layers    = $customisation['layers'] ?? [];

			$layer_map = [];
			if ( $design_id ) {
				foreach ( OC_DB::get_design_layers( $design_id ) as $l ) {
					$layer_map[ (int) $l->id ] = $l;
				}
			}

			foreach ( $layers as $layer_id => $layer_data ) {
				if ( ! is_array( $layer_data ) ) continue;
				$layer = $layer_map[ (int) $layer_id ] ?? null;
				$label = $layer ? ( $layer->label ?: ucfirst( $layer->type ) ) : ucfirst( $layer_data['type'] ?? 'Layer' );
				$value = $this->layer_display_value( $layer_data );
				if ( ! $value ) continue;
				echo '<div><strong>' . esc_html( $label ) . ':</strong> ' . $value . '</div>';
			}

			$this->render_admin_print_files( $item_id, $print_files, true, $order );
			echo '</div>';
			return;
		}

		// ── v1 / legacy format ────────────────────────────────────────────────
		foreach ( $customisation as $area_key => $area_data ) {
			if ( ! is_array( $area_data ) ) continue;
			$has_text    = ! empty( $area_data['text'] );
			$has_artwork = ! empty( $area_data['artworkAttachmentId'] );
			if ( ! $has_text && ! $has_artwork ) continue;

			echo '<div><strong>' . esc_html( ucwords( str_replace( '-', ' ', $area_key ) ) ) . ':</strong> ';

			if ( $has_text ) {
				echo esc_html( $area_data['text'] );
				if ( ! empty( $area_data['fontId'] ) ) {
					global $wpdb;
					$font_name = (string) $wpdb->get_var( $wpdb->prepare(
						"SELECT name FROM {$wpdb->prefix}oc_fonts WHERE id = %d LIMIT 1",
						$area_data['fontId']
					) );
					if ( $font_name ) echo ' &mdash; ' . esc_html( $font_name );
				}
				if ( ! empty( $area_data['color'] ) ) {
					printf(
						' &mdash; <span style="display:inline-block;width:12px;height:12px;background:%s;border:1px solid #ccc;vertical-align:middle;border-radius:2px;"></span> %s',
						esc_attr( $area_data['color'] ),
						esc_html( $area_data['color'] )
					);
				}
			}
			if ( $has_artwork ) {
				$thumb = wp_get_attachment_image( (int) $area_data['artworkAttachmentId'], [ 48, 48 ], false,
					[ 'style' => 'vertical-align:middle;margin-left:6px;border:1px solid #ddd;border-radius:2px;' ] );
				echo $thumb ?: ' ' . esc_html__( '[Artwork]', 'overcustomise' );
			}
			echo '</div>';
		}

		$this->render_admin_print_files( $item_id, $print_files, true, $order );
		echo '</div>';
	}

	/** Display the same useful OverCustomise block in WooCommerce's admin item editor. */
	public function display_in_admin_order_item( int $item_id, WC_Order_Item $item, $product ): void {
		$order = method_exists( $item, 'get_order' ) ? $item->get_order() : null;
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$this->display_in_order( $item_id, $item, $order );
	}

	// ── Shared helpers ────────────────────────────────────────────────────────

	/**
	 * Return a safe display string for a single v2 layer input array.
	 * Returns empty string if there's nothing to show.
	 */
	private function layer_display_value( array $layer_data ): string {
		switch ( $layer_data['type'] ?? '' ) {
			case 'text':
			case 'textarea':
			case 'spotify':
				$val = trim( $layer_data['value'] ?? '' );
				return $val ? esc_html( $val ) : '';

			case 'image':
				if ( ! empty( $layer_data['attachmentId'] ) ) {
					$thumb = wp_get_attachment_image( (int) $layer_data['attachmentId'], [ 48, 48 ], false,
						[ 'style' => 'vertical-align:middle;border:1px solid #ddd;border-radius:2px;' ] );
					return $thumb ?: esc_html__( '[Image uploaded]', 'overcustomise' );
				}
				return '';

			case 'clipart':
				return ! empty( $layer_data['clipartId'] ) ? esc_html__( '[Clipart selected]', 'overcustomise' ) : '';

			default:
				return '';
		}
	}

	/** Build plain-text customer input details for WooCommerce's standard item meta table. */
	private function build_personalisation_details( array $customisation ): string {
		$lines = [];

		if ( isset( $customisation['v'] ) && 2 === (int) $customisation['v'] ) {
			$design_id = (int) ( $customisation['designId'] ?? 0 );
			$layers    = is_array( $customisation['layers'] ?? null ) ? $customisation['layers'] : [];
			$layer_map = [];

			if ( $design_id ) {
				foreach ( OC_DB::get_design_layers( $design_id ) as $layer ) {
					$layer_map[ (int) $layer->id ] = $layer;
				}
			}

			foreach ( $layers as $layer_id => $layer_data ) {
				if ( ! is_array( $layer_data ) ) {
					continue;
				}

				$value = $this->layer_plain_display_value( $layer_data );
				if ( '' === $value ) {
					continue;
				}

				$layer = $layer_map[ (int) $layer_id ] ?? null;
				$label = $layer ? ( $layer->label ?: ucfirst( $layer->type ) ) : ucfirst( $layer_data['type'] ?? __( 'Layer', 'overcustomise' ) );
				$lines[] = sprintf( '%s: %s', $label, $value );
			}

			return implode( "\n", $lines );
		}

		foreach ( $customisation as $area_key => $area_data ) {
			if ( ! is_array( $area_data ) ) {
				continue;
			}

			$parts = [];
			if ( ! empty( $area_data['text'] ) ) {
				$parts[] = (string) $area_data['text'];
			}
			if ( ! empty( $area_data['artworkAttachmentId'] ) ) {
				$parts[] = __( '[Artwork attached]', 'overcustomise' );
			}

			if ( empty( $parts ) ) {
				continue;
			}

			$lines[] = sprintf(
				'%s: %s',
				ucwords( str_replace( '-', ' ', (string) $area_key ) ),
				implode( ' ', $parts )
			);
		}

		return implode( "\n", $lines );
	}

	/** Return a plain-text display value for order item metadata. */
	private function layer_plain_display_value( array $layer_data ): string {
		switch ( $layer_data['type'] ?? '' ) {
			case 'text':
			case 'textarea':
			case 'spotify':
				return trim( (string) ( $layer_data['value'] ?? '' ) );

			case 'image':
				return ! empty( $layer_data['attachmentId'] ) ? __( '[Image uploaded]', 'overcustomise' ) : '';

			case 'clipart':
				return ! empty( $layer_data['clipartId'] ) ? __( '[Clipart selected]', 'overcustomise' ) : '';

			case 'lineart':
				return ! empty( $layer_data['colorHex'] ) ? sprintf(
					/* translators: %s: selected colour hex value. */
					__( 'Line art colour %s', 'overcustomise' ),
					(string) $layer_data['colorHex']
				) : '';

			default:
				return '';
		}
	}

	/**
	 * Show generated print file status/links on backend order item rows.
	 * Runs only in wp-admin to avoid exposing internal production files on frontend pages.
	 */
	private function render_admin_print_files( int $item_id, ?array $print_files = null, bool $show_empty = false, ?WC_Order $order = null ): void {
		if ( ! is_admin() ) {
			return;
		}

		$print_files = null === $print_files ? OC_DB::get_print_files_for_item( $item_id ) : $print_files;
		if ( empty( $print_files ) ) {
			if ( $show_empty ) {
				echo '<div style="margin-top:6px;"><strong>' . esc_html__( 'Print Files:', 'overcustomise' ) . '</strong> '
					. esc_html__( 'No print files generated yet.', 'overcustomise' );

				if ( $order instanceof WC_Order ) {
					$generate_url = add_query_arg(
						[
							'oc_generate_print_files' => $order->get_id(),
							'_wpnonce'                => wp_create_nonce( 'oc_generate_print_files_' . $order->get_id() ),
						],
						admin_url()
					);
					echo ' <a href="' . esc_url( $generate_url ) . '" class="button button-small" style="margin-left:6px;">'
						. esc_html__( 'Generate Print Files', 'overcustomise' ) . '</a>';
				}

				echo '</div>';
			}
			return;
		}

		echo '<div style="margin-top:6px;"><strong>' . esc_html__( 'Print Files:', 'overcustomise' ) . '</strong></div>';
		foreach ( $print_files as $file ) {
			$status_label = ucfirst( str_replace( '_', ' ', (string) $file->file_status ) );
			$queue_info   = OC_Print_Queue::instance()->get_status( (int) $file->id );
			echo '<div style="margin-top:3px;">';
			echo esc_html( ucfirst( str_replace( '_', ' ', (string) $file->file_type ) ) . ': ' . $status_label );

			if ( ! empty( $queue_info['is_processing'] ) ) {
				echo ' <em style="color:#9e6c00;">' . esc_html__( 'Processing now.', 'overcustomise' ) . '</em>';
			} elseif ( ! empty( $queue_info['has_failed_job'] ) ) {
				echo ' <em style="color:#b32d2e;">' . esc_html__( 'Queue job failed.', 'overcustomise' ) . '</em>';
			} elseif ( ! empty( $queue_info['in_queue'] ) ) {
				echo ' <em style="color:#666;">' . esc_html__( 'Waiting in queue.', 'overcustomise' ) . '</em>';
			}

			if ( 'files_ready' === $file->file_status && ! empty( $file->file_path ) && file_exists( $file->file_path ) ) {
				$download_url = add_query_arg(
					[
						'oc_download_file' => (int) $file->id,
						'_wpnonce'         => wp_create_nonce( 'oc_download_' . (int) $file->id ),
					],
					admin_url()
				);
				echo ' <a href="' . esc_url( $download_url ) . '" class="button button-small" style="margin-left:6px;">'
					. esc_html__( 'Download Print File', 'overcustomise' ) . '</a>';
			} elseif ( 'files_ready' === $file->file_status ) {
				echo ' <em style="color:#888;">' . esc_html__( 'File missing on disk.', 'overcustomise' ) . '</em>';
			} elseif ( $order instanceof WC_Order && ( ! empty( $queue_info['in_queue'] ) || ! empty( $queue_info['has_failed_job'] ) ) ) {
				$process_url = add_query_arg(
					[
						'oc_process_print_queue_order' => $order->get_id(),
						'_wpnonce'                     => wp_create_nonce( 'oc_process_print_queue_order_' . $order->get_id() ),
					],
					admin_url()
				);
				echo ' <a href="' . esc_url( $process_url ) . '" class="button button-small" style="margin-left:6px;">'
					. esc_html__( 'Process Print Queue', 'overcustomise' ) . '</a>';
			} elseif ( 'pending' === $file->file_status ) {
				$generate_url = add_query_arg(
					[
						'oc_regenerate' => (int) $file->id,
						'_wpnonce'      => wp_create_nonce( 'oc_regenerate_' . (int) $file->id ),
					],
					admin_url( 'post.php?post=' . ( $order instanceof WC_Order ? $order->get_id() : 0 ) . '&action=edit' )
				);
				echo ' <a href="' . esc_url( $generate_url ) . '" class="button button-small" style="margin-left:6px;">'
					. esc_html__( 'Generate Now', 'overcustomise' ) . '</a>';
			}

			echo '</div>';
		}
	}
}
