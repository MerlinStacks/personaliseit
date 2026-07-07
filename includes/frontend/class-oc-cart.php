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
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_preview_styles' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_preview_modal' ] );

		// Store customisation in the cart item.
		add_filter( 'woocommerce_add_cart_item_data', [ $this, 'add_cart_item_data' ], 10, 3 );

		// Display summary lines in cart and checkout.
		add_filter( 'woocommerce_get_item_data', [ $this, 'display_item_data' ], 10, 2 );

		// Replace product thumbnail with personalised preview in cart/checkout.
		add_filter( 'woocommerce_cart_item_thumbnail', [ $this, 'cart_item_thumbnail' ], 10, 3 );
		add_filter( 'woocommerce_cart_item_name', [ $this, 'checkout_item_name_preview' ], 10, 3 );
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

	/** Ensure personalised previews are contained rather than cropped across classic, Blocks, and Mini Cart. */
	public function enqueue_preview_styles(): void {
		wp_register_style( 'oc-cart-preview', false, [], OC_VERSION );
		wp_enqueue_style( 'oc-cart-preview' );
		wp_add_inline_style( 'oc-cart-preview', '
			.oc-cart-preview-thumb,
			.oc-checkout-preview-thumb img,
			.oc-blocks-line-preview img,
			.wc-block-components-product-image img[src*="/overcustomise/previews/"],
			.wc-block-mini-cart-items img[src*="/overcustomise/previews/"] {
				width: 80px;
				height: 80px;
				object-fit: contain !important;
				border: 1px solid #e0e0e0;
				border-radius: 4px;
				background: #fff;
			}
			.oc-checkout-preview-thumb,
			.oc-blocks-line-preview {
				display: inline-flex;
				align-items: center;
				margin-right: 10px;
				vertical-align: middle;
			}
			.oc-blocks-line-preview img {
				width: 64px;
				height: 64px;
			}
		' );
	}

	/** Load WordPress' image modal on WooCommerce order admin screens. */
	public function enqueue_admin_preview_modal(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen ) {
			return;
		}

		if ( 'shop_order' !== $screen->post_type && 'woocommerce_page_wc-orders' !== $screen->id ) {
			return;
		}

		add_thickbox();
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
		if ( strlen( $raw ) > 1024 * 1024 ) {
			return $cart_item_data;
		}

		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			return $cart_item_data;
		}

		$valid_layer_types = [ 'text', 'textarea', 'image', 'clipmask', 'spotify', 'lineart', 'clipart' ];

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
				$settings = $layer->settings ? json_decode( (string) $layer->settings, true ) : [];
				$design_layers[ $layer_id ] = [
					'type'     => $layer_type,
					'settings' => is_array( $settings ) ? $settings : [],
				];
			}
			if ( empty( $design_layers ) ) {
				return $cart_item_data;
			}

			// Reject if layers isn't actually an array.
			if ( ! isset( $decoded['layers'] ) || ! is_array( $decoded['layers'] ) ) {
				return $cart_item_data;
			}

			$sanitised_layers = [];
			$fallback_font_id = self::first_active_font_id();
			$posted_layer_inputs = [];
			if ( isset( $_POST['oc_layer_inputs'] ) && is_array( $_POST['oc_layer_inputs'] ) ) {
				$posted_layer_inputs = wp_unslash( $_POST['oc_layer_inputs'] );
			}
			foreach ( $decoded['layers'] as $layer_id => $layer_data ) {
				if ( ! is_array( $layer_data ) ) continue;

				$layer_key = absint( $layer_id );
				if ( ! $layer_key ) continue;
				if ( ! isset( $design_layers[ $layer_key ] ) ) continue;

				$type     = $design_layers[ $layer_key ]['type'];
				$settings = $design_layers[ $layer_key ]['settings'];
				if ( ! in_array( $type, $valid_layer_types, true ) ) {
					continue;
				}

				if ( isset( $posted_layer_inputs[ $layer_key ] ) && is_array( $posted_layer_inputs[ $layer_key ] ) ) {
					$posted_value = $posted_layer_inputs[ $layer_key ]['value'] ?? null;
					if ( is_scalar( $posted_value ) ) {
						$layer_data['value'] = (string) $posted_value;
					}
				}

				$font_id   = absint( $layer_data['fontId'] ?? 0 );
				$font_size = absint( $layer_data['fontSize'] ?? 0 );
				$color_hex = sanitize_hex_color( is_string( $layer_data['colorHex'] ?? null ) ? $layer_data['colorHex'] : '#000000' ) ?: '#000000';
				if ( in_array( $type, [ 'text', 'textarea' ], true ) ) {
					if ( ! $font_id ) {
						$font_id = absint( $settings['default_font_id'] ?? 0 ) ?: $fallback_font_id;
					}
					if ( array_key_exists( 'allow_font_change', $settings ) && empty( $settings['allow_font_change'] ) ) {
						$font_id = absint( $settings['default_font_id'] ?? 0 );
					}
					if ( empty( $settings['allow_size_change'] ) ) {
						$font_size = absint( $settings['default_font_size'] ?? 0 );
					}
					if ( array_key_exists( 'allow_colour_change', $settings ) && empty( $settings['allow_colour_change'] ) ) {
						$color_hex = sanitize_hex_color( (string) ( $settings['default_color'] ?? '#000000' ) ) ?: '#000000';
					}
				}

				$colour_group_ids = array_values( array_filter( array_map( 'absint', is_array( $settings['colour_groups'] ?? null ) ? $settings['colour_groups'] : [] ) ) );
				$should_restrict_colour = ! empty( $colour_group_ids ) && ( in_array( $type, [ 'lineart', 'clipart' ], true ) || ( in_array( $type, [ 'text', 'textarea' ], true ) && ( ! array_key_exists( 'allow_colour_change', $settings ) || ! empty( $settings['allow_colour_change'] ) ) ) );
				if ( $should_restrict_colour ) {
					$allowed_colours = OC_DB::get_colours_for_groups( $colour_group_ids );
					$allowed_hexes   = array_values( array_filter( array_map( fn( $colour ) => sanitize_hex_color( (string) ( $colour->hex ?? '' ) ), $allowed_colours ) ) );
					if ( empty( $allowed_hexes ) ) {
						$color_hex = '#000000';
					} elseif ( ! in_array( strtolower( $color_hex ), array_map( 'strtolower', $allowed_hexes ), true ) ) {
						$color_hex = $allowed_hexes[0];
					}
				}

				$sanitised_layers[ $layer_key ] = [
					'type'          => $type,
					'value'         => is_scalar( $layer_data['value'] ?? null ) ? sanitize_text_field( (string) $layer_data['value'] ) : '',
					'fontId'        => $font_id,
					'fontSize'      => $font_size,
					'colorHex'      => $color_hex,
					'attachmentId'  => absint( $layer_data['attachmentId'] ?? 0 ),
					'clipartId'     => absint( $layer_data['clipartId']    ?? 0 ),
					'clipartUrl'    => is_string( $layer_data['clipartUrl'] ?? null ) ? esc_url_raw( $layer_data['clipartUrl'] ) : '',
					'clipartRecolourable' => ! empty( $layer_data['clipartRecolourable'] ),
				];
			}

			if ( empty( $sanitised_layers ) ) return $cart_item_data;

			$snapshots = $this->sanitise_area_snapshots( is_array( $decoded['snapshots'] ?? null ) ? $decoded['snapshots'] : [] );

			$cart_item_data['_oc_customisation'] = [
				'v'          => 2,
				'designId'   => $design_id,
				'layers'     => $sanitised_layers,
				'renderSpec' => OC_Render_Spec::build( $design_id, $sanitised_layers, $snapshots ),
			];
			if ( is_string( $decoded['designVariant'] ?? null ) && '' !== $decoded['designVariant'] ) {
				$cart_item_data['_oc_customisation']['designVariant'] = sanitize_key( $decoded['designVariant'] );
			}
			if ( is_string( $decoded['designVariantLabel'] ?? null ) && '' !== $decoded['designVariantLabel'] ) {
				$cart_item_data['_oc_customisation']['designVariantLabel'] = sanitize_text_field( $decoded['designVariantLabel'] );
			}
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

	private static function first_active_font_id(): int {
		$fonts = OC_DB::get_fonts( true );
		$first = is_array( $fonts ) && ! empty( $fonts ) ? reset( $fonts ) : null;

		return is_object( $first ) && ! empty( $first->id ) ? absint( $first->id ) : 0;
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

		$path = wp_parse_url( $sanitised_url, PHP_URL_PATH );
		if ( ! is_string( $path ) || ! preg_match( '#/overcustomise/previews/(preview-[a-f0-9]{32}\.(?:png|jpe?g))$#i', $path, $matches ) ) {
			return '';
		}

		return $baseurl . '/overcustomise/previews/' . $matches[1];
	}

	/** Sanitise browser-captured per-area SVG snapshots before storing in cart/order meta. */
	private function sanitise_area_snapshots( array $snapshots ): array {
		$clean = [];
		foreach ( $snapshots as $area_key => $snapshot ) {
			if ( ! is_array( $snapshot ) || ! is_string( $snapshot['svg'] ?? null ) ) {
				continue;
			}

			$key = is_scalar( $area_key ) ? sanitize_key( (string) $area_key ) : '';
			if ( '' === $key || strlen( $snapshot['svg'] ) > 512 * 1024 ) {
				continue;
			}

			try {
				$svg = OC_SVG_Sanitiser::sanitise( $snapshot['svg'] );
			} catch ( \InvalidArgumentException $e ) {
				continue;
			}

			if ( '' === $svg ) {
				continue;
			}

			$clean[ $key ] = [
				'format' => sanitize_key( is_string( $snapshot['format'] ?? null ) ? $snapshot['format'] : 'fabric-svg-v1' ),
				'unit'   => sanitize_key( is_string( $snapshot['unit'] ?? null ) ? $snapshot['unit'] : 'mockup_px' ),
				'scale'  => isset( $snapshot['scale'] ) ? (float) $snapshot['scale'] : 1.0,
				'svg'    => $svg,
			];
		}

		return $clean;
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

			if ( ! empty( $customisation['designVariantLabel'] ) ) {
				$item_data[] = [
					'key'   => __( 'Artwork Option', 'overcustomise' ),
					'value' => esc_html( (string) $customisation['designVariantLabel'] ),
				];
			}

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
			return $this->preview_image_html( (string) $cart_item['_oc_preview_url'] );
		}
		return $thumbnail;
	}

	/** Add a preview image to classic checkout rows, where WooCommerce has no thumbnail column. */
	public function checkout_item_name_preview( string $product_name, array $cart_item, string $cart_item_key ): string {
		if ( ! is_checkout() || is_cart() || empty( $cart_item['_oc_preview_url'] ) ) {
			return $product_name;
		}

		return '<span class="oc-checkout-preview-thumb">' . $this->preview_image_html( (string) $cart_item['_oc_preview_url'] ) . '</span>' . $product_name;
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
			[
				'id'        => 0,
				'src'       => $preview_url,
				'full_src'  => $preview_url,
				'thumbnail' => $preview_url,
				'srcset'    => '',
				'sizes'     => '',
				'name'      => __( 'Personalised preview', 'overcustomise' ),
				'alt'       => __( 'Personalised preview', 'overcustomise' ),
			],
		];
	}

	private function preview_image_html( string $preview_url ): string {
		return '<img src="' . esc_url( $preview_url ) . '" class="oc-cart-preview-thumb" alt="' . esc_attr__( 'Personalised preview', 'overcustomise' ) . '" width="80" height="80" loading="lazy" style="width:80px;height:80px;object-fit:contain;border:1px solid #e0e0e0;border-radius:4px;margin-right:10px;vertical-align:middle;" />';
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
			__( 'Preview Image', 'overcustomise' ),
			__( 'Personalisation Details', 'overcustomise' ),
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
			echo '<a href="' . esc_url( $preview_url ) . '" class="thickbox" style="display:inline-block;margin-bottom:8px;">'
			   . '<img src="' . esc_url( $preview_url ) . '" alt="' . esc_attr__( 'Personalised preview', 'overcustomise' ) . '" '
			   . 'style="display:block;max-width:120px;max-height:120px;object-fit:contain;border:1px solid #e0e0e0;border-radius:3px;cursor:zoom-in;" />'
			   . '</a>';
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
			case 'clipmask':
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
			if ( $order instanceof WC_Order ) {
				( new OC_Print_Generator() )->generate_for_order( $order );
				$print_files = OC_DB::get_print_files_for_item( $item_id );
			}

			if ( ! empty( $print_files ) ) {
				$this->render_admin_print_files( $item_id, $print_files, $show_empty, $order );
				return;
			}

			if ( $show_empty ) {
				echo '<div style="margin-top:6px;"><strong>' . esc_html__( 'Print Files:', 'overcustomise' ) . '</strong> '
					. esc_html__( 'No print files generated yet. They will be queued automatically when printable customisation data is available.', 'overcustomise' )
					. '</div>';
			}
			return;
		}

		echo '<div style="margin-top:6px;"><strong>' . esc_html__( 'Print Files:', 'overcustomise' ) . '</strong></div>';
		foreach ( $print_files as $file ) {
			$status_label = ucfirst( str_replace( '_', ' ', (string) $file->file_status ) );
			$queue_info   = OC_Print_Queue::instance()->get_status( (int) $file->id );
			$regen_url    = add_query_arg(
				[
					'oc_regenerate' => (int) $file->id,
					'_wpnonce'      => wp_create_nonce( 'oc_regenerate_' . (int) $file->id ),
				],
				admin_url( 'post.php?post=' . ( $order instanceof WC_Order ? $order->get_id() : 0 ) . '&action=edit' )
			);
			echo '<div style="margin-top:3px;">';
			echo esc_html( ucfirst( str_replace( '_', ' ', (string) $file->file_type ) ) . ': ' . $status_label );

			if ( ! empty( $queue_info['is_processing'] ) ) {
				echo ' <em style="color:#9e6c00;">' . esc_html__( 'Processing now.', 'overcustomise' ) . '</em>';
			} elseif ( ! empty( $queue_info['has_failed_job'] ) ) {
				echo ' <em style="color:#b32d2e;">' . esc_html__( 'Queue job failed.', 'overcustomise' ) . '</em>';
				if ( ! empty( $queue_info['error_message'] ) ) {
					echo '<div style="margin:3px 0 0 16px;color:#b32d2e;"><strong>' . esc_html__( 'Error:', 'overcustomise' ) . '</strong> '
						. esc_html( (string) $queue_info['error_message'] ) . '</div>';
				}
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
				echo ' <a href="' . esc_url( $regen_url ) . '" class="button button-small" style="margin-left:6px;">'
					. esc_html__( 'Regenerate Print File', 'overcustomise' ) . '</a>';
			} elseif ( 'files_ready' === $file->file_status ) {
				echo ' <em style="color:#888;">' . esc_html__( 'File missing on disk.', 'overcustomise' ) . '</em>';
				echo ' <a href="' . esc_url( $regen_url ) . '" class="button button-small" style="margin-left:6px;">'
					. esc_html__( 'Regenerate Print File', 'overcustomise' ) . '</a>';
			} elseif ( 'pending' === $file->file_status ) {
				echo ' <em style="color:#666;">' . esc_html__( 'Queued automatically.', 'overcustomise' ) . '</em>';
				echo ' <a href="' . esc_url( $regen_url ) . '" class="button button-small" style="margin-left:6px;">'
					. esc_html__( 'Regenerate Print File', 'overcustomise' ) . '</a>';
			} elseif ( 'generating' === $file->file_status && empty( $queue_info['is_processing'] ) && empty( $queue_info['in_queue'] ) ) {
				echo ' <em style="color:#b32d2e;">' . esc_html__( 'No active queue job.', 'overcustomise' ) . '</em>';
				echo ' <a href="' . esc_url( $regen_url ) . '" class="button button-small" style="margin-left:6px;">'
					. esc_html__( 'Regenerate Print File', 'overcustomise' ) . '</a>';
			}

			echo '</div>';
		}
	}
}
