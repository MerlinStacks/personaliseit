<?php
/**
 * Cart integration — stores customisation data, applies flat rate fee,
 * displays summary in cart, and persists to HPOS-compatible orders.
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

class OC_Cart {

	/** @var array<string,bool> Checkout rows where WooCommerce already rendered the thumbnail column. */
	private array $checkout_thumbnail_rendered = [];

	/** @var array<string,array{decoded:array,normalised:array}> Request-local validated submissions. */
	private static array $validated_submissions = [];

	public function register(): void {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_preview_styles' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_preview_modal' ] );

		// Store customisation in the cart item.
		add_filter( 'woocommerce_add_to_cart_validation', [ $this, 'validate_legacy_artwork' ], 20, 4 );
		add_filter( 'woocommerce_add_cart_item_data', [ $this, 'add_cart_item_data' ], 10, 3 );
		add_filter( 'woocommerce_store_api_add_to_cart_data', [ $this, 'store_api_add_to_cart_data' ], 10, 2 );

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
		$cart_has_customisation = self::cart_has_customisation();
		if ( ( is_cart() || is_checkout() ) && ! $cart_has_customisation ) {
			return;
		}
		if ( ! is_cart() && ! is_checkout() && ! is_account_page() && ! $cart_has_customisation ) {
			return;
		}

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
			.widget_shopping_cart .woocommerce-mini-cart-item,
			.woocommerce.widget_shopping_cart .woocommerce-mini-cart-item {
				position: relative;
				min-height: 92px;
				padding-left: 92px !important;
			}
			.widget_shopping_cart .woocommerce-mini-cart-item img.oc-cart-preview-thumb,
			.woocommerce.widget_shopping_cart .woocommerce-mini-cart-item img.oc-cart-preview-thumb {
				position: absolute;
				left: 0;
				top: 0;
				width: 80px !important;
				height: 80px !important;
				margin: 0 !important;
			}
		' );
	}

	/** Check whether global cart preview styles are needed for mini-cart rendering. */
	public static function cart_has_customisation(): bool {
		$cart = function_exists( 'WC' ) ? WC()->cart ?? null : null;
		if ( ! $cart ) {
			return false;
		}

		foreach ( $cart->get_cart() as $item ) {
			if ( ! empty( $item['_oc_customisation'] ) || ! empty( $item['_oc_preview_url'] ) ) {
				return true;
			}
		}

		return false;
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

	/** Reject legacy artwork IDs unless the upload is owned in the exact product context. */
	public function validate_legacy_artwork( bool $passed, int $product_id, int $quantity, int $variation_id = 0 ): bool {
		if ( ! $passed ) {
			return false;
		}

		if ( OC_DB::get_assignment_for_product( $product_id, $variation_id ) ) {
			return $passed;
		}
		$config = OC_DB::get_config_by_product( $product_id );
		if ( ! $config || ! (int) $config->active ) {
			return $passed;
		}

		$raw = isset( $_POST['_oc_customisation'] ) ? wp_unslash( $_POST['_oc_customisation'] ) : '';
		if ( ! is_string( $raw ) || '' === trim( $raw ) || strlen( $raw ) > 1024 * 1024 ) {
			wc_add_notice( __( 'Please complete your personalisation before adding to cart.', 'overcustomise' ), 'error' );
			return false;
		}

		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) || isset( $decoded['v'] ) ) {
			wc_add_notice( __( 'Invalid personalisation data. Please refresh and try again.', 'overcustomise' ), 'error' );
			return false;
		}

		$upload_token = is_string( $decoded['uploadToken'] ?? null ) ? $decoded['uploadToken'] : '';
		foreach ( $decoded as $area_data ) {
			if ( ! is_array( $area_data ) ) {
				continue;
			}

			$attachment_id = absint( $area_data['artworkAttachmentId'] ?? 0 );
			if ( $attachment_id && ! OC_Upload_Handler::legacy_attachment_is_accepted( $attachment_id, $product_id, $variation_id, $upload_token ) ) {
				wc_add_notice( __( 'The uploaded artwork is no longer valid for this product. Please upload it again.', 'overcustomise' ), 'error' );
				return false;
			}
		}

		return true;
	}

	public function add_cart_item_data( array $cart_item_data, int $product_id, int $variation_id ): array {
		$raw = self::submission_raw_from_request( $cart_item_data );
		unset( $cart_item_data['_oc_submission_raw'] );

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

		// ── v2 format: { v:2, designId, layers:{layerId:{type,...}} } ────────
		if ( array_key_exists( 'v', $decoded ) ) {
			if ( 2 !== $decoded['v'] ) {
				throw new \Exception( esc_html__( 'Unsupported personalisation data. Please refresh and try again.', 'overcustomise' ) );
			}
			$design_id = absint( $decoded['designId'] ?? 0 );
			if ( ! $design_id ) {
				throw new \Exception( esc_html__( 'No personalisation design was selected.', 'overcustomise' ) );
			}

			$assignment = OC_DB::get_assignment_for_product( $product_id, $variation_id );
			if ( ! $assignment || ! OC_DB::assignment_allows_design( $assignment, $design_id ) ) {
				throw new \Exception( esc_html__( 'The selected personalisation design is not available.', 'overcustomise' ) );
			}

			if ( ! isset( $decoded['layers'] ) || ! is_array( $decoded['layers'] ) ) {
				throw new \Exception( esc_html__( 'No personalisation layers were submitted.', 'overcustomise' ) );
			}

			$cache_key    = self::submission_key( $product_id, $variation_id, $raw );
			$validated    = self::$validated_submissions[ $cache_key ] ?? null;
			$upload_token = is_string( $decoded['uploadToken'] ?? null ) ? $decoded['uploadToken'] : '';
			$normalised   = is_array( $validated ) ? $validated['normalised'] : self::normalise_v2_layers(
				$product_id,
				$variation_id,
				$design_id,
				$decoded['layers'],
				$upload_token
			);
			if ( is_wp_error( $normalised ) ) {
				throw new \Exception( esc_html( $normalised->get_error_message() ) );
			}
			$design           = $normalised['design'];
			$sanitised_layers = $normalised['layers'];

			$cart_item_data['_oc_customisation'] = [
				'v'          => 2,
				'designId'   => $design_id,
				'layers'     => $sanitised_layers,
				'renderSpec' => OC_Render_Spec::build( $design_id, $sanitised_layers ),
			];
			$variant = self::design_variant_for_assignment( $assignment, $design_id );
			if ( $variant ) {
				$cart_item_data['_oc_customisation']['designVariant']      = $variant['id'];
				$cart_item_data['_oc_customisation']['designVariantLabel'] = $variant['label'];
			}
			$cart_item_data['_oc_design_id']     = $design_id;
			$cart_item_data['_oc_flat_rate']     = (float) $design->flat_rate;
			$cart_item_data['_oc_unique_key']    = md5( $raw . microtime() );

			// New clients include a small preview in the cart request, avoiding a
			// separate blocking REST request before WooCommerce can add the item.
			$preview_image = $decoded['previewImage'] ?? '';
			if ( is_string( $preview_image ) && '' !== $preview_image ) {
				$stored_preview = OC_Rest_API::store_cart_preview( $preview_image, $upload_token );
				if ( ! is_wp_error( $stored_preview ) ) {
					$cart_item_data['_oc_preview_url'] = $stored_preview;
				}
			}

			$preview_url = $decoded['previewUrl'] ?? '';
			if ( empty( $cart_item_data['_oc_preview_url'] ) && is_string( $preview_url ) && '' !== $preview_url ) {
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

		$sanitised   = [];
		$upload_token = is_string( $decoded['uploadToken'] ?? null ) ? $decoded['uploadToken'] : '';
		foreach ( $decoded as $area_key => $area_data ) {
			if ( ! is_array( $area_data ) ) continue;
			$safe_key = is_scalar( $area_key ) ? sanitize_key( (string) $area_key ) : '';
			if ( '' === $safe_key ) continue;
			$attachment_id = absint( $area_data['artworkAttachmentId'] ?? 0 );
			if ( $attachment_id && ! OC_Upload_Handler::legacy_attachment_is_accepted( $attachment_id, $product_id, $variation_id, $upload_token ) ) {
				wc_add_notice( __( 'The uploaded artwork is no longer valid for this product. Please upload it again.', 'overcustomise' ), 'error' );
				return $cart_item_data;
			}
			$sanitised[ $safe_key ] = [
				'text'               => is_scalar( $area_data['text'] ?? null ) ? sanitize_text_field( (string) $area_data['text'] ) : '',
				'fontId'             => absint( $area_data['fontId'] ?? 0 ),
				'color'              => sanitize_hex_color( is_string( $area_data['color'] ?? null ) ? $area_data['color'] : '#000000' ) ?: '#000000',
				'artworkAttachmentId' => $attachment_id,
			];
		}
		if ( empty( $sanitised ) ) return $cart_item_data;

		$cart_item_data['_oc_customisation'] = $sanitised;
		$cart_item_data['_oc_config_id']     = (int) $config->id;
		$cart_item_data['_oc_flat_rate']     = (float) $config->flat_rate;
		$cart_item_data['_oc_unique_key']    = md5( $raw . microtime() );
		return $cart_item_data;
	}

	/** Accept a v2 payload supplied through the WooCommerce Store API extensions object. */
	public function store_api_add_to_cart_data( array $add_to_cart_data, \WP_REST_Request $request ): array {
		$extensions    = $request->get_param( 'extensions' );
		$customisation = is_array( $extensions ) ? ( $extensions['overcustomise']['customisation'] ?? null ) : null;
		if ( is_array( $customisation ) ) {
			$customisation = wp_json_encode( $customisation );
		}
		if ( is_string( $customisation ) && '' !== trim( $customisation ) ) {
			$add_to_cart_data['cart_item_data'] = is_array( $add_to_cart_data['cart_item_data'] ?? null ) ? $add_to_cart_data['cart_item_data'] : [];
			$add_to_cart_data['cart_item_data']['_oc_submission_raw'] = $customisation;
		}

		return $add_to_cart_data;
	}

	/** Read the customisation from classic form data or Store API cart item data. */
	public static function submission_raw_from_request( array $cart_item_data = [] ): mixed {
		if ( isset( $cart_item_data['_oc_submission_raw'] ) ) {
			return $cart_item_data['_oc_submission_raw'];
		}

		return isset( $_POST['_oc_customisation'] ) ? wp_unslash( $_POST['_oc_customisation'] ) : '';
	}

	/** Validate and cache one exact v2 submission for the cart persistence filter. */
	public static function validate_v2_submission( int $product_id, int $variation_id, mixed $raw ): true|\WP_Error {
		if ( ! is_string( $raw ) || '' === trim( $raw ) || '{}' === trim( $raw ) ) {
			return new \WP_Error( 'missing_customisation', __( 'Please complete your personalisation before adding to cart.', 'overcustomise' ) );
		}
		if ( strlen( $raw ) > 1024 * 1024 ) {
			return new \WP_Error( 'customisation_too_large', __( 'This personalisation is too large to add safely. Please simplify the design or contact us for help.', 'overcustomise' ) );
		}

		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) || ! array_key_exists( 'v', $decoded ) || 2 !== $decoded['v'] ) {
			return new \WP_Error( 'invalid_customisation_version', __( 'Invalid or unsupported personalisation data. Please refresh and try again.', 'overcustomise' ) );
		}
		$design_id = absint( $decoded['designId'] ?? 0 );
		if ( ! $design_id || ! is_array( $decoded['layers'] ?? null ) ) {
			return new \WP_Error( 'invalid_customisation', __( 'Invalid personalisation data. Please refresh and try again.', 'overcustomise' ) );
		}

		$normalised = self::normalise_v2_layers(
			$product_id,
			$variation_id,
			$design_id,
			$decoded['layers'],
			is_string( $decoded['uploadToken'] ?? null ) ? $decoded['uploadToken'] : ''
		);
		if ( is_wp_error( $normalised ) ) {
			return $normalised;
		}

		self::$validated_submissions[ self::submission_key( $product_id, $variation_id, $raw ) ] = [
			'decoded'    => $decoded,
			'normalised' => $normalised,
		];
		return true;
	}

	private static function submission_key( int $product_id, int $variation_id, string $raw ): string {
		return $product_id . ':' . $variation_id . ':' . hash( 'sha256', $raw );
	}

	/**
	 * Authoritatively normalise v2 layer input against the assigned design.
	 *
	 * @return array{design:object,layers:array<int,array>}|\WP_Error
	 */
	public static function normalise_v2_layers( int $product_id, int $variation_id, int $design_id, array $raw_layers, string $upload_token = '' ): array|\WP_Error {
		$assignment = OC_DB::get_assignment_for_product( $product_id, $variation_id );
		if ( ! $assignment || ! OC_DB::assignment_allows_design( $assignment, $design_id ) ) {
			return new \WP_Error( 'invalid_design', __( 'Design is not assigned to this product.', 'overcustomise' ) );
		}

		$design = OC_DB::get_design( $design_id );
		if ( ! $design || ! (bool) $design->active ) {
			return new \WP_Error( 'invalid_design', __( 'Design not found or inactive.', 'overcustomise' ) );
		}

		$methods       = [];
		$design_layers = OC_DB::get_design_layers( $design_id );
		$raw_layers    = self::synchronise_linked_layer_inputs( $design_layers, $raw_layers );
		foreach ( OC_DB::get_design_print_areas( $design_id ) as $area ) {
			if ( isset( $area->visible ) && ! (bool) $area->visible ) {
				continue;
			}
			$methods[ absint( $area->id ?? 0 ) ] = sanitize_key( (string) ( $area->print_method ?? '' ) );
		}

		$active_font_ids = array_map( static fn( $font ) => (int) $font->id, OC_DB::get_fonts( true ) );
		$fallback_font_id = (int) ( $active_font_ids[0] ?? 0 );
		$normalised       = [];
		$valid_types      = [ 'text', 'textarea', 'image', 'clipmask', 'mask', 'spotify', 'lineart', 'clipart' ];

		foreach ( $design_layers as $layer ) {
			if ( isset( $layer->visible ) && ! (bool) $layer->visible ) {
				continue;
			}
			$layer_id = absint( $layer->id ?? 0 );
			$type     = sanitize_key( (string) ( $layer->type ?? '' ) );
			if ( ! $layer_id || ! in_array( $type, $valid_types, true ) ) {
				continue;
			}

			$settings = ! empty( $layer->settings ) ? json_decode( (string) $layer->settings, true ) : [];
			$settings = is_array( $settings ) ? $settings : [];
			$posted   = is_array( $raw_layers[ $layer_id ] ?? null ) ? $raw_layers[ $layer_id ] : [];
			$editable = ! empty( $layer->visible ) && empty( $layer->locked );
			$source   = $editable ? $posted : [];

			$default_value = is_scalar( $settings['default_text'] ?? null ) ? (string) $settings['default_text'] : '';
			$value         = is_scalar( $source['value'] ?? null ) ? (string) $source['value'] : $default_value;
			$value         = 'textarea' === $type && function_exists( 'sanitize_textarea_field' ) ? sanitize_textarea_field( $value ) : sanitize_text_field( $value );
			if ( 'spotify' === $type && '' !== trim( $value ) ) {
				$value = self::normalise_spotify_value( $value );
				if ( '' === $value ) {
					return new \WP_Error( 'invalid_spotify', sprintf( __( 'The Spotify link in "%s" is invalid.', 'overcustomise' ), $layer->label ?: ucfirst( $type ) ) );
				}
			}
			$char_limit    = absint( $settings['char_limit'] ?? 0 );
			if ( $char_limit && self::string_length_static( $value ) > $char_limit ) {
				return new \WP_Error( 'character_limit', sprintf( __( '"%1$s" exceeds the maximum of %2$d characters.', 'overcustomise' ), $layer->label ?: ucfirst( $type ), $char_limit ) );
			}
			if ( 'spotify' === $type && '' !== trim( $value ) && in_array( sanitize_key( (string) ( $source['spotifyStatus'] ?? '' ) ), [ 'invalid_format', 'playlist_private_or_invalid', 'invalid_or_unavailable', 'unreachable', 'rate_limited' ], true ) ) {
				return new \WP_Error( 'invalid_spotify', sprintf( __( 'The Spotify link in "%s" could not be validated.', 'overcustomise' ), $layer->label ?: ucfirst( $type ) ) );
			}

			$default_font = absint( $settings['default_font_id'] ?? 0 );
			$font_id      = absint( $source['fontId'] ?? $default_font );
			if ( ! $editable || ( array_key_exists( 'allow_font_change', $settings ) && empty( $settings['allow_font_change'] ) ) ) {
				$font_id = $default_font;
			}
			$font_groups = self::id_list( $settings['font_groups'] ?? [] );
			$allowed_fonts = $font_groups ? array_values( array_intersect( $active_font_ids, array_map( 'intval', OC_DB::get_font_ids_for_groups( $font_groups ) ) ) ) : $active_font_ids;
			if ( in_array( $type, [ 'text', 'textarea' ], true ) && ! in_array( $font_id, $allowed_fonts, true ) ) {
				$font_id = in_array( $default_font, $allowed_fonts, true ) ? $default_font : (int) ( $allowed_fonts[0] ?? $fallback_font_id );
			}

			$default_size = absint( $settings['default_font_size'] ?? 0 );
			$font_size    = absint( $source['fontSize'] ?? $default_size );
			if ( ! $editable || empty( $settings['allow_size_change'] ) ) {
				$font_size = $default_size;
			}

			$default_colour = sanitize_hex_color( (string) ( $settings['default_color'] ?? '#000000' ) ) ?: '#000000';
			$colour         = sanitize_hex_color( is_string( $source['colorHex'] ?? null ) ? $source['colorHex'] : $default_colour ) ?: $default_colour;
			if ( ! $editable || ( array_key_exists( 'allow_colour_change', $settings ) && empty( $settings['allow_colour_change'] ) ) ) {
				$colour = $default_colour;
			}
			$colour_groups = self::id_list( $settings['colour_groups'] ?? [] );
			if ( $colour_groups ) {
				$allowed_colours = array_values( array_filter( array_map( static fn( $item ) => sanitize_hex_color( (string) ( $item->hex ?? '' ) ), OC_DB::get_colours_for_groups( $colour_groups ) ) ) );
				$lower_colours   = array_map( 'strtolower', $allowed_colours );
				if ( ! in_array( strtolower( $colour ), $lower_colours, true ) ) {
					$colour = in_array( strtolower( $default_colour ), $lower_colours, true ) ? $default_colour : ( $allowed_colours[0] ?? '#000000' );
				}
			}

			$default_attachment = absint( $settings['default_attachment_id'] ?? 0 );
			$attachment_id      = in_array( $type, [ 'image', 'clipmask' ], true ) ? absint( $source['attachmentId'] ?? $default_attachment ) : 0;
			$source_attachment_id = in_array( $type, [ 'image', 'clipmask' ], true ) ? absint( $source['sourceAttachmentId'] ?? $attachment_id ) : 0;
			$can_image_change = ! array_key_exists( 'allow_image_change', $settings ) || ! empty( $settings['allow_image_change'] );
			if ( ! $editable || ! $can_image_change ) {
				$attachment_id        = $default_attachment;
				$source_attachment_id = $default_attachment;
			}
			if ( $attachment_id && $attachment_id === $default_attachment && ! OC_Upload_Handler::admin_default_attachment_is_valid( $attachment_id ) ) {
				$attachment_id = 0;
			}
			$attachment_context_layer_id = absint( $source['_oc_link_source_layer_id'] ?? $layer_id );
			if ( $attachment_id && $attachment_id !== $default_attachment
				&& ! OC_Upload_Handler::attachment_is_accepted( $attachment_id, $product_id, $variation_id, $design_id, $attachment_context_layer_id, $upload_token )
			) {
				return new \WP_Error( 'invalid_attachment', sprintf( __( 'The uploaded artwork for "%s" is not valid for this customisation.', 'overcustomise' ), $layer->label ?: ucfirst( $type ) ) );
			}
			if ( $source_attachment_id && $source_attachment_id !== $default_attachment
				&& ! OC_Upload_Handler::attachment_is_accepted( $source_attachment_id, $product_id, $variation_id, $design_id, $attachment_context_layer_id, $upload_token )
			) {
				return new \WP_Error( 'invalid_attachment', sprintf( __( 'The source artwork for "%s" is not valid for this customisation.', 'overcustomise' ), $layer->label ?: ucfirst( $type ) ) );
			}

			$filter_ids = self::id_list( $settings['image_filter_ids'] ?? [] );
			$default_filter = absint( $settings['default_image_filter_id'] ?? 0 );
			$filter_id      = absint( $source['imageFilterId'] ?? $default_filter );
			$can_filter_change = ! array_key_exists( 'allow_image_filter_change', $settings ) || ! empty( $settings['allow_image_filter_change'] );
			if ( ! in_array( $default_filter, $filter_ids, true ) ) $default_filter = 0;
			if ( ! $editable || ! $can_filter_change || ! in_array( $filter_id, $filter_ids, true ) ) $filter_id = $default_filter;
			$selected_filter = null;
			foreach ( OC_DB::get_image_filters( true ) as $candidate ) {
				if ( (int) $candidate->id === $filter_id ) { $selected_filter = $candidate; break; }
			}
			if ( $filter_id && ! $selected_filter ) {
				return new \WP_Error( 'invalid_image_filter', sprintf( __( 'The selected image filter for "%s" is no longer available.', 'overcustomise' ), $layer->label ?: ucfirst( $type ) ) );
			}
			if ( $filter_id && $selected_filter && 'ai' === (string) $selected_filter->filter_key ) {
				$generated_filter_id = absint( get_post_meta( $attachment_id, '_oc_ai_filter_id', true ) );
				$generated_source_id = absint( get_post_meta( $attachment_id, '_oc_ai_filter_source_id', true ) );
				if ( $generated_filter_id !== $filter_id || $generated_source_id !== $source_attachment_id ) {
					return new \WP_Error( 'ai_filter_required', sprintf( __( 'The AI filter for "%s" has not finished.', 'overcustomise' ), $layer->label ?: ucfirst( $type ) ) );
				}
			}

			$default_clipart = absint( $settings['default_clipart_id'] ?? 0 );
			$clipart_id      = 'clipart' === $type ? absint( $source['clipartId'] ?? $default_clipart ) : 0;
			if ( ! $editable || ( array_key_exists( 'allow_clipart_change', $settings ) && empty( $settings['allow_clipart_change'] ) ) ) {
				$clipart_id = $default_clipart;
			}
			$clipart_groups = $clipart_id === $default_clipart ? [] : self::id_list( $settings['clipart_groups'] ?? [] );
			$clipart = $clipart_id ? self::get_allowed_clipart( $clipart_id, $clipart_groups, $methods[ absint( $layer->area_id ?? 0 ) ] ?? '' ) : null;
			if ( $clipart_id && ! $clipart ) {
				return new \WP_Error( 'invalid_clipart', sprintf( __( 'Please choose an available clipart for "%s".', 'overcustomise' ), $layer->label ?: ucfirst( $type ) ) );
			}

			$filled = match ( $type ) {
				'text', 'textarea', 'spotify' => '' !== trim( $value ),
				'image', 'clipmask'            => $attachment_id > 0,
				'clipart'                      => $clipart_id > 0,
				default                        => true,
			};
			if ( $editable && ! empty( $settings['required'] ) && ! $filled ) {
				return new \WP_Error( 'required_layer', sprintf( __( 'Please complete "%s".', 'overcustomise' ), $layer->label ?: ucfirst( $type ) ) );
			}

			$preview_attachment_id = $attachment_id ? absint( get_post_meta( $attachment_id, '_oc_print_derivative_attachment_id', true ) ) : 0;
			$normalised[ $layer_id ] = [
				'type' => $type, 'value' => $value, 'fontId' => $font_id, 'fontSize' => $font_size,
				'colorHex' => $colour, 'attachmentId' => $attachment_id, 'sourceAttachmentId' => $source_attachment_id, 'imageFilterId' => $filter_id,
				'imageFilterKey' => $selected_filter ? sanitize_key( (string) $selected_filter->filter_key ) : '',
				'imageFilterValue' => $selected_filter ? (float) $selected_filter->value : 0.0,
				'previewAttachmentId' => $preview_attachment_id,
				'clipartId' => $clipart_id,
				'clipartUrl' => $clipart ? self::clipart_url( (string) $clipart->file_path ) : '',
				'clipartRecolourable' => $clipart && (bool) $clipart->colour_changeable && 'svg' === strtolower( (string) $clipart->file_type ),
			];
		}

		return $normalised ? [ 'design' => $design, 'layers' => $normalised ] : new \WP_Error( 'invalid_design', __( 'Design has no valid layers.', 'overcustomise' ) );
	}

	/** Copy the one rendered linked control to every server-confirmed group member. */
	private static function synchronise_linked_layer_inputs( array $layers, array $raw_layers ): array {
		$groups = [];
		foreach ( $layers as $layer ) {
			$settings = ! empty( $layer->settings ) ? json_decode( (string) $layer->settings, true ) : [];
			$settings = is_array( $settings ) ? $settings : [];
			$group    = sanitize_key( (string) ( $settings['link_group'] ?? '' ) );
			if ( '' !== $group && ! empty( $layer->visible ) && empty( $layer->locked ) ) {
				$groups[ (string) $layer->type . ':' . $group ][] = (int) $layer->id;
			}
		}

		foreach ( $groups as $layer_ids ) {
			if ( count( $layer_ids ) < 2 ) {
				continue;
			}
			$source_id   = 0;
			$source_data = [];
			foreach ( $layer_ids as $layer_id ) {
				$candidate = $raw_layers[ $layer_id ] ?? null;
				if ( is_array( $candidate ) && ! empty( array_filter( $candidate, static fn( $value ) => null !== $value && '' !== $value && 0 !== $value ) ) ) {
					$source_id   = $layer_id;
					$source_data = $candidate;
					break;
				}
			}
			if ( ! $source_id ) {
				continue;
			}
			$source_data['_oc_link_source_layer_id'] = $source_id;
			foreach ( $layer_ids as $layer_id ) {
				$raw_layers[ $layer_id ] = $source_data;
			}
		}

		return $raw_layers;
	}

	public static function normalise_spotify_value( string $value ): string {
		$value = trim( $value );
		if ( preg_match( '/^spotify:(track|album|artist|playlist|episode|show):([A-Za-z0-9]{1,128})$/i', $value, $matches ) ) {
			return sprintf( 'spotify:%s:%s', strtolower( $matches[1] ), $matches[2] );
		}

		$parts = wp_parse_url( $value );
		if ( ! is_array( $parts ) || ! in_array( strtolower( (string) ( $parts['host'] ?? '' ) ), [ 'open.spotify.com', 'play.spotify.com' ], true ) ) {
			return '';
		}
		$path = array_values( array_filter( explode( '/', trim( (string) ( $parts['path'] ?? '' ), '/' ) ) ) );
		if ( isset( $path[0], $path[1] ) && in_array( strtolower( $path[0] ), [ 'track', 'album', 'artist', 'playlist', 'episode', 'show' ], true ) && preg_match( '/^[A-Za-z0-9]{1,128}$/', $path[1] ) ) {
			return sprintf( 'spotify:%s:%s', strtolower( $path[0] ), $path[1] );
		}

		return '';
	}

	/** Return the authoritative stored variant ID and label for a selected design. */
	public static function design_variant_for_assignment( object $assignment, int $design_id ): ?array {
		if ( $design_id === (int) ( $assignment->design_id ?? 0 ) ) {
			$design = OC_DB::get_design( $design_id );
			return $design ? [ 'id' => 'design-' . $design_id, 'label' => sanitize_text_field( (string) $design->name ) ] : null;
		}

		$variants = json_decode( (string) ( $assignment->design_variants ?? '' ), true );
		foreach ( is_array( $variants ) ? $variants : [] as $variant ) {
			if ( $design_id === absint( $variant['designId'] ?? 0 ) ) {
				$design = OC_DB::get_design( $design_id );
				$label  = sanitize_text_field( (string) ( $variant['label'] ?? '' ) );
				return [
					'id'    => 'design-' . $design_id,
					'label' => $label ?: ( $design ? sanitize_text_field( (string) $design->name ) : '' ),
				];
			}
		}

		return null;
	}

	private static function id_list( mixed $value ): array {
		return is_array( $value ) ? array_values( array_filter( array_map( 'absint', $value ) ) ) : [];
	}

	private static function string_length_static( string $value ): int {
		return function_exists( 'mb_strlen' ) ? (int) mb_strlen( $value, 'UTF-8' ) : strlen( $value );
	}

	private static function get_allowed_clipart( int $clipart_id, array $group_ids, string $print_method ): ?object {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT id, file_path, file_type, colour_changeable, allowed_print_methods FROM {$wpdb->prefix}oc_clipart WHERE id = %d AND active = 1 LIMIT 1", $clipart_id ) );
		if ( ! $row ) return null;
		if ( $group_ids ) {
			$placeholders = implode( ',', array_fill( 0, count( $group_ids ), '%d' ) );
			$in_group = $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM {$wpdb->prefix}oc_clipart_group_items WHERE clipart_id = %d AND group_id IN ($placeholders) LIMIT 1", $clipart_id, ...$group_ids ) );
			if ( ! $in_group ) return null;
		}
		$methods = self::normalise_clipart_print_methods( (string) ( $row->allowed_print_methods ?? '' ) );
		return $methods && ! in_array( sanitize_key( $print_method ), $methods, true ) ? null : $row;
	}

	private static function clipart_url( string $path ): string {
		$uploads = wp_upload_dir();
		return rtrim( (string) ( $uploads['baseurl'] ?? '' ), '/' ) . '/overcustomise/clipart/' . basename( $path );
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

		return $item_data;
	}

	/** Replace the cart/checkout product thumbnail with the personalised preview. */
	public function cart_item_thumbnail( string $thumbnail, array $cart_item, string $cart_item_key ): string {
		if ( ! empty( $cart_item['_oc_preview_url'] ) ) {
			if ( is_checkout() && ! is_cart() ) {
				$this->checkout_thumbnail_rendered[ $cart_item_key ] = true;
			}

			return $this->preview_image_html( (string) $cart_item['_oc_preview_url'] );
		}
		return $thumbnail;
	}

	/** Add a preview image to classic checkout rows, where WooCommerce has no thumbnail column. */
	public function checkout_item_name_preview( string $product_name, array $cart_item, string $cart_item_key ): string {
		if ( ! is_checkout() || is_cart() || empty( $cart_item['_oc_preview_url'] ) ) {
			return $product_name;
		}
		if ( ! empty( $this->checkout_thumbnail_rendered[ $cart_item_key ] ) ) {
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
			(object) [
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

		$fees = [];

		foreach ( $cart->get_cart() as $cart_item ) {
			if ( empty( $cart_item['_oc_customisation'] ) ) {
				continue;
			}

			$rate     = (float) ( $cart_item['_oc_flat_rate'] ?? 0 );
			$quantity = (int) $cart_item['quantity'];
			$product  = $cart_item['data'] ?? null;
			$taxable  = $product instanceof \WC_Product && $product->is_taxable();
			$tax_class = $taxable ? (string) $product->get_tax_class() : '';
			$key       = ( $taxable ? 'taxable:' : 'exempt:' ) . $tax_class;
			if ( ! isset( $fees[ $key ] ) ) {
				$fees[ $key ] = [ 'amount' => 0.0, 'taxable' => $taxable, 'tax_class' => $tax_class ];
			}
			$fees[ $key ]['amount'] += $rate * $quantity;
		}

		$multiple_fee_groups = count( $fees ) > 1;
		foreach ( $fees as $fee ) {
			if ( $fee['amount'] <= 0 ) {
				continue;
			}
			$fee_name = __( 'Personalisation Fee', 'overcustomise' );
			if ( $multiple_fee_groups ) {
				$rate_label = $fee['taxable']
					? ( '' !== $fee['tax_class'] ? $fee['tax_class'] : __( 'standard rate', 'overcustomise' ) )
					: __( 'non-taxable', 'overcustomise' );
				$fee_name = sprintf(
					/* translators: %s: tax class for this fee group. */
					__( 'Personalisation Fee (%s)', 'overcustomise' ),
					$rate_label
				);
			}
			$cart->add_fee(
				$fee_name,
				$fee['amount'],
				$fee['taxable'],
				$fee['tax_class']
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

		echo '<div class="oc-order-item-meta" style="margin-top:10px;padding:12px;background:#fff;border:1px solid #dcdcde;border-radius:6px;font-size:12px;line-height:1.45;box-shadow:0 1px 2px rgba(0,0,0,0.04);max-width:560px;">';
		echo '<div style="display:flex;gap:14px;align-items:flex-start;">';

		// ── Preview image ─────────────────────────────────────────────────────
		if ( $preview_url ) {
			echo '<div style="flex:0 0 auto;">'
			   . '<a href="' . esc_url( $preview_url ) . '" class="thickbox" style="display:inline-block;">'
			   . '<img src="' . esc_url( $preview_url ) . '" alt="' . esc_attr__( 'Personalised preview', 'overcustomise' ) . '" '
			   . 'style="display:block;width:120px;height:120px;object-fit:contain;border:1px solid #dcdcde;border-radius:5px;background:#f6f7f7;cursor:zoom-in;" />'
			   . '</a>'
			   . '<div style="margin-top:4px;color:#646970;font-size:11px;text-align:center;">' . esc_html__( 'Preview', 'overcustomise' ) . '</div>'
			   . '</div>';
		}

		echo '<div style="flex:1 1 auto;min-width:0;">';
		echo '<div style="margin:0 0 8px;font-weight:600;color:#1d2327;">' . esc_html__( 'Customisation', 'overcustomise' ) . '</div>';

		if ( empty( $customisation ) || ! is_array( $customisation ) ) {
			$this->render_admin_print_files( $item_id, $print_files, true, $order );
			echo '</div></div>';
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
				if ( is_admin() && $this->is_fixed_clipart_layer( $layer, $layer_data ) ) continue;
				$label = $layer ? ( $layer->label ?: ucfirst( $layer->type ) ) : ucfirst( $layer_data['type'] ?? 'Layer' );
				$value = $this->layer_display_value( $layer_data, $layer );
				if ( ! $value ) continue;
				echo '<div style="display:grid;grid-template-columns:minmax(110px,38%) 1fr;gap:8px;align-items:start;margin:0 0 6px;">'
					. '<div style="color:#646970;font-weight:600;">' . esc_html( $label ) . '</div>'
					. '<div style="color:#1d2327;word-break:break-word;">' . $value . '</div>'
					. '</div>';
			}

			$this->render_admin_print_files( $item_id, $print_files, true, $order );
			echo '</div></div>';
			return;
		}

		// ── v1 / legacy format ────────────────────────────────────────────────
		foreach ( $customisation as $area_key => $area_data ) {
			if ( ! is_array( $area_data ) ) continue;
			$has_text    = ! empty( $area_data['text'] );
			$has_artwork = ! empty( $area_data['artworkAttachmentId'] );
			if ( ! $has_text && ! $has_artwork ) continue;

			echo '<div style="display:grid;grid-template-columns:minmax(110px,38%) 1fr;gap:8px;align-items:start;margin:0 0 6px;">'
				. '<div style="color:#646970;font-weight:600;">' . esc_html( ucwords( str_replace( '-', ' ', $area_key ) ) ) . '</div>'
				. '<div style="color:#1d2327;word-break:break-word;">';

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
				$attachment_id = absint( $area_data['artworkAttachmentId'] );
				$preview_id    = absint( get_post_meta( $attachment_id, '_oc_artwork_preview_attachment_id', true ) );
				$thumb         = $this->artwork_thumbnail_html( $preview_id ?: $attachment_id, 'vertical-align:middle;margin-left:6px;border:1px solid #ddd;border-radius:2px;' );
				echo $thumb ?: ' ' . esc_html__( '[Artwork]', 'overcustomise' );
			}
			echo '</div></div>';
		}

		$this->render_admin_print_files( $item_id, $print_files, true, $order );
		echo '</div></div>';
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
	private function layer_display_value( array $layer_data, ?object $layer = null ): string {
		switch ( $layer_data['type'] ?? '' ) {
			case 'text':
			case 'textarea':
			case 'spotify':
				$val = trim( $layer_data['value'] ?? '' );
				if ( ! $val ) {
					return '';
				}

				$html = esc_html( $val );
				if ( is_admin() && in_array( $layer_data['type'] ?? '', [ 'text', 'textarea' ], true ) && ! empty( $layer_data['fontId'] ) && $this->customer_can_change_layer_setting( $layer, 'allow_font_change' ) ) {
					global $wpdb;
					$font_name = (string) $wpdb->get_var( $wpdb->prepare(
						"SELECT name FROM {$wpdb->prefix}oc_fonts WHERE id = %d LIMIT 1",
						(int) $layer_data['fontId']
					) );
					if ( '' !== $font_name ) {
						$html .= ' &mdash; ' . esc_html( $font_name );
					}
				}
				if ( is_admin() && $this->customer_can_change_layer_setting( $layer, 'allow_colour_change' ) ) {
					$colour_html = $this->colour_display_value( $layer_data );
					if ( '' !== $colour_html ) {
						$html .= ' &mdash; ' . $colour_html;
					}
				}

				return $html;

			case 'image':
			case 'clipmask':
				if ( ! empty( $layer_data['attachmentId'] ) ) {
					$attachment_id = absint( $layer_data['previewAttachmentId'] ?? $layer_data['attachmentId'] );
					$thumb         = $this->artwork_thumbnail_html( $attachment_id, 'vertical-align:middle;border:1px solid #ddd;border-radius:2px;' );
					return $thumb ?: esc_html__( '[Image uploaded]', 'overcustomise' );
				}
				return '';

			case 'clipart':
				if ( empty( $layer_data['clipartId'] ) ) {
					return '';
				}

				$html = esc_html__( '[Clipart selected]', 'overcustomise' );
				$colour_html = is_admin() && $this->customer_can_change_layer_setting( $layer, 'allow_colour_change' ) ? $this->colour_display_value( $layer_data ) : '';
				return '' !== $colour_html ? $html . ' &mdash; ' . $colour_html : $html;

			case 'lineart':
				return is_admin() && $this->customer_can_change_layer_setting( $layer, 'allow_colour_change' ) ? $this->colour_display_value( $layer_data ) : '';

			default:
				return '';
		}
	}

	/** Render customer artwork through its signed endpoint instead of its uploads URL. */
	private function artwork_thumbnail_html( int $attachment_id, string $style ): string {
		$url = OC_Upload_Handler::attachment_access_url( $attachment_id );
		if ( '' === $url ) {
			return '';
		}

		return sprintf(
			'<img src="%s" alt="" width="48" height="48" loading="lazy" style="%s" />',
			esc_url( $url ),
			esc_attr( $style )
		);
	}

	/** Return an escaped colour swatch and hex value for order admin summaries. */
	private function colour_display_value( array $layer_data ): string {
		$colour = ! empty( $layer_data['colorHex'] ) && is_string( $layer_data['colorHex'] )
			? sanitize_hex_color( $layer_data['colorHex'] )
			: '';

		if ( '' === $colour ) {
			return '';
		}

		global $wpdb;
		$colour_name = (string) $wpdb->get_var( $wpdb->prepare(
			"SELECT name FROM {$wpdb->prefix}oc_colours WHERE LOWER(hex) = LOWER(%s) LIMIT 1",
			$colour
		) );

		return sprintf(
			'<span style="display:inline-block;width:10px;height:10px;background:%s;border:1px solid #ccc;vertical-align:middle;border-radius:2px;"></span> %s',
			esc_attr( $colour ),
			esc_html( '' !== $colour_name ? $colour_name : $colour )
		);
	}

	/** Layer settings default to customer-changeable unless explicitly disabled. */
	private function customer_can_change_layer_setting( ?object $layer, string $setting_key ): bool {
		if ( ! $layer || empty( $layer->settings ) ) {
			return true;
		}

		$settings = json_decode( (string) $layer->settings, true );
		if ( ! is_array( $settings ) || ! array_key_exists( $setting_key, $settings ) ) {
			return true;
		}

		return ! empty( $settings[ $setting_key ] );
	}

	/** Do not show fixed default clipart as customer-selected order data. */
	private function is_fixed_clipart_layer( ?object $layer, array $layer_data ): bool {
		if ( 'clipart' !== ( $layer_data['type'] ?? '' ) || empty( $layer_data['clipartId'] ) || ! $layer ) {
			return false;
		}

		$settings = ! empty( $layer->settings ) ? json_decode( (string) $layer->settings, true ) : [];
		if ( ! is_array( $settings ) ) {
			return false;
		}

		return array_key_exists( 'allow_clipart_change', $settings ) && empty( $settings['allow_clipart_change'] );
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
				echo '<div style="margin-top:10px;padding-top:10px;border-top:1px solid #dcdcde;">'
					. '<div style="margin:0 0 5px;font-weight:600;color:#1d2327;">' . esc_html__( 'Print Files', 'overcustomise' ) . '</div>'
					. '<div style="color:#646970;">' . esc_html__( 'No print files generated yet. They will be queued automatically when printable customisation data is available.', 'overcustomise' ) . '</div>'
					. '</div>';
			}
			return;
		}

		echo '<div style="margin-top:10px;padding-top:10px;border-top:1px solid #dcdcde;">';
		echo '<div style="margin:0 0 6px;font-weight:600;color:#1d2327;">' . esc_html__( 'Print Files', 'overcustomise' ) . '</div>';
		foreach ( $print_files as $file ) {
			$status_label = ucfirst( str_replace( '_', ' ', (string) $file->file_status ) );
			$queue_info   = OC_Print_Queue::instance()->get_status( (int) $file->id );
			$order_edit_url = $order instanceof WC_Order ? $order->get_edit_order_url() : admin_url( 'admin.php?page=wc-orders' );
			$regen_url    = add_query_arg(
				[
					'oc_regenerate' => (int) $file->id,
					'_wpnonce'      => wp_create_nonce( 'oc_regenerate_' . (int) $file->id ),
				],
				$order_edit_url
			);
			echo '<div style="margin-top:6px;padding:8px;background:#f6f7f7;border:1px solid #dcdcde;border-radius:4px;">';
			echo '<div style="display:flex;gap:8px;align-items:center;justify-content:space-between;flex-wrap:wrap;">';
			echo '<div><span style="font-weight:600;color:#1d2327;">' . esc_html( ucfirst( str_replace( '_', ' ', (string) $file->file_type ) ) ) . '</span> '
				. '<span style="display:inline-block;margin-left:4px;padding:1px 7px;border-radius:999px;background:#e7f5ea;color:#1b7e34;font-size:11px;font-weight:600;">' . esc_html( $status_label ) . '</span></div>';
			echo '<div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">';

			if ( ! empty( $queue_info['is_processing'] ) ) {
				echo '<em style="color:#9e6c00;">' . esc_html__( 'Processing now.', 'overcustomise' ) . '</em>';
			} elseif ( ! empty( $queue_info['has_failed_job'] ) ) {
				echo '<em style="color:#b32d2e;">' . esc_html__( 'Queue job failed.', 'overcustomise' ) . '</em>';
				if ( ! empty( $queue_info['error_message'] ) ) {
					echo '<div style="margin:3px 0 0 16px;color:#b32d2e;"><strong>' . esc_html__( 'Error:', 'overcustomise' ) . '</strong> '
						. esc_html( (string) $queue_info['error_message'] ) . '</div>';
				}
			} elseif ( ! empty( $queue_info['in_queue'] ) ) {
				echo '<em style="color:#666;">' . esc_html__( 'Waiting in queue.', 'overcustomise' ) . '</em>';
			}

			if ( 'files_ready' === $file->file_status && ! empty( $file->file_path ) && file_exists( $file->file_path ) ) {
				$download_url = add_query_arg(
					[
						'oc_download_file' => (int) $file->id,
						'_wpnonce'         => wp_create_nonce( 'oc_download_' . (int) $file->id ),
					],
					admin_url()
				);
				echo '<a href="' . esc_url( $download_url ) . '" class="button button-small">'
					. esc_html__( 'Download Print File', 'overcustomise' ) . '</a>';
				echo '<a href="' . esc_url( $regen_url ) . '" class="button button-small">'
					. esc_html__( 'Regenerate Print File', 'overcustomise' ) . '</a>';
			} elseif ( 'files_ready' === $file->file_status ) {
				echo '<em style="color:#888;">' . esc_html__( 'File missing on disk.', 'overcustomise' ) . '</em>';
				echo '<a href="' . esc_url( $regen_url ) . '" class="button button-small">'
					. esc_html__( 'Regenerate Print File', 'overcustomise' ) . '</a>';
			} elseif ( 'pending' === $file->file_status ) {
				echo '<em style="color:#666;">' . esc_html__( 'Queued automatically.', 'overcustomise' ) . '</em>';
				echo '<a href="' . esc_url( $regen_url ) . '" class="button button-small">'
					. esc_html__( 'Regenerate Print File', 'overcustomise' ) . '</a>';
			} elseif ( 'generating' === $file->file_status && empty( $queue_info['is_processing'] ) && empty( $queue_info['in_queue'] ) ) {
				echo '<em style="color:#b32d2e;">' . esc_html__( 'No active queue job.', 'overcustomise' ) . '</em>';
				echo '<a href="' . esc_url( $regen_url ) . '" class="button button-small">'
					. esc_html__( 'Regenerate Print File', 'overcustomise' ) . '</a>';
			}

			echo '</div></div>';
			echo '</div>';
		}
		echo '</div>';
	}

	private static function normalise_clipart_print_methods( string $raw ): array {
		if ( '' === trim( $raw ) ) {
			return [];
		}

		$decoded = json_decode( $raw, true );
		$methods = is_array( $decoded ) ? $decoded : explode( ',', $raw );
		$allowed = [ 'engraving', 'uv', 'embroidery', 'sublimation' ];

		return array_values( array_intersect( $allowed, array_map( 'sanitize_key', $methods ) ) );
	}
}
