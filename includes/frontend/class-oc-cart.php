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

	/** @var array<string,array<int,array<string,mixed>>> Per-fee order allocation metadata. */
	private array $fee_allocations = [];

	public function register(): void {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_preview_styles' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_preview_modal' ] );

		// Store customisation in the cart item.
		add_filter( 'woocommerce_add_to_cart_validation', [ $this, 'validate_legacy_artwork' ], 20, 6 );
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
		add_action( 'woocommerce_checkout_create_order_fee_item', [ $this, 'mark_personalisation_fee_item' ], 10, 4 );

		// Hide internal production data from WooCommerce's default item meta table.
		add_filter( 'woocommerce_hidden_order_itemmeta', [ $this, 'hidden_order_item_meta' ] );

		// Display preview + details in admin and frontend order pages.
		add_action( 'woocommerce_order_item_meta_end', [ $this, 'display_in_order' ], 10, 4 );
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
			.wc-block-components-product-image img[src*="/overcustomise/previews/"],
			.wc-block-mini-cart-items img[src*="/overcustomise/previews/"] {
				width: 80px;
				height: 80px;
				object-fit: contain !important;
				border: 1px solid #e0e0e0;
				border-radius: 4px;
				background: #fff;
			}
			.oc-checkout-preview-thumb {
				display: inline-flex;
				align-items: center;
				margin-right: 10px;
				vertical-align: middle;
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

	/** Validate legacy submissions with the same normaliser used for cart persistence. */
	public function validate_legacy_artwork( bool $passed, int $product_id, int $quantity, int $variation_id = 0, array $variations = [], array $cart_item_data = [] ): bool {
		if ( ! $passed ) {
			return false;
		}

		$assignment = OC_DB::get_assignment_for_product( $product_id, $variation_id );
		if ( $assignment && self::assignment_has_usable_design( $assignment ) ) {
			return $passed;
		}
		$config = OC_DB::get_config_by_product( $product_id );
		if ( ! $config || ! (int) $config->active ) {
			return $passed;
		}

		$result = self::validate_legacy_submission( $product_id, $variation_id, self::submission_raw_from_request( $cart_item_data ) );
		if ( is_wp_error( $result ) ) {
			wc_add_notice( $result->get_error_message(), 'error' );
			return false;
		}

		return $passed;
	}

	public function add_cart_item_data( array $cart_item_data, int $product_id, int $variation_id ): array {
		$raw = self::submission_raw_from_request( $cart_item_data );
		unset( $cart_item_data['_oc_submission_raw'] );

		if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
			if ( self::product_requires_personalisation( $product_id, $variation_id ) ) {
				throw new \Exception( esc_html__( 'Please complete your personalisation before adding to cart.', 'overcustomise' ) );
			}
			return $cart_item_data;
		}

		// Hard cap on payload size to prevent memory abuse via oversized JSON.
		if ( strlen( $raw ) > 1024 * 1024 ) {
			throw new \Exception( esc_html__( 'This personalisation is too large to add safely. Please simplify it or contact us for help.', 'overcustomise' ) );
		}

		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			throw new \Exception( esc_html__( 'Invalid personalisation data. Please refresh and try again.', 'overcustomise' ) );
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
		$cache_key  = self::submission_key( $product_id, $variation_id, $raw );
		$validated  = self::$validated_submissions[ $cache_key ] ?? null;
		$normalised = is_array( $validated ) ? $validated['normalised'] : self::normalise_legacy_submission( $product_id, $variation_id, $decoded );
		if ( is_wp_error( $normalised ) ) {
			throw new \Exception( esc_html( $normalised->get_error_message() ) );
		}
		$config = $normalised['config'];

		$cart_item_data['_oc_customisation'] = $normalised['areas'];
		$cart_item_data['_oc_config_id']     = (int) $config->id;
		$cart_item_data['_oc_flat_rate']     = (float) $config->flat_rate;
		$cart_item_data['_oc_unique_key']    = md5( $raw . microtime() );
		return $cart_item_data;
	}

	/** Accept a payload supplied through the Store API extension or cart_item_data object. */
	public function store_api_add_to_cart_data( array $add_to_cart_data, \WP_REST_Request $request ): array {
		$extensions = $request->get_param( 'extensions' );
		$oc_extension = is_array( $extensions ) && is_array( $extensions['overcustomise'] ?? null )
			? $extensions['overcustomise']
			: [];
		$customisation = $oc_extension['customisation'] ?? null;
		$request_item_data = $request->get_param( 'cart_item_data' );
		if ( null === $customisation && is_array( $request_item_data ) ) {
			$customisation = $request_item_data['_oc_customisation']
				?? ( is_array( $request_item_data['overcustomise'] ?? null ) ? ( $request_item_data['overcustomise']['customisation'] ?? null ) : null );
		}
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
		if ( is_array( $cart_item_data['cart_item_data'] ?? null ) && isset( $cart_item_data['cart_item_data']['_oc_submission_raw'] ) ) {
			return $cart_item_data['cart_item_data']['_oc_submission_raw'];
		}
		if ( isset( $cart_item_data['_oc_customisation'] ) && ( is_string( $cart_item_data['_oc_customisation'] ) || is_array( $cart_item_data['_oc_customisation'] ) ) ) {
			return is_array( $cart_item_data['_oc_customisation'] ) ? wp_json_encode( $cart_item_data['_oc_customisation'] ) : $cart_item_data['_oc_customisation'];
		}

		return isset( $_POST['_oc_customisation'] ) ? wp_unslash( $_POST['_oc_customisation'] ) : '';
	}

	/** Return whether an assignment has at least one active design with public input. */
	public static function assignment_has_usable_design( object $assignment ): bool {
		$design_ids = [ absint( $assignment->design_id ?? 0 ) ];
		$variants   = json_decode( is_scalar( $assignment->design_variants ?? null ) ? (string) $assignment->design_variants : '', true );
		foreach ( is_array( $variants ) ? $variants : [] as $variant ) {
			if ( is_array( $variant ) ) {
				$design_ids[] = absint( $variant['designId'] ?? 0 );
			}
		}

		foreach ( array_values( array_unique( array_filter( $design_ids ) ) ) as $design_id ) {
			$design = OC_DB::get_design( $design_id );
			if ( ! $design || ! (bool) $design->active ) {
				continue;
			}
			$visible_area_ids = [];
			foreach ( OC_DB::get_design_print_areas( $design_id ) as $area ) {
				if ( ! isset( $area->visible ) || (bool) $area->visible ) {
					$visible_area_ids[ absint( $area->id ?? 0 ) ] = true;
				}
			}
			foreach ( OC_DB::get_design_layers( $design_id ) as $layer ) {
				if ( ( ! isset( $layer->visible ) || (bool) $layer->visible ) && ! empty( $visible_area_ids[ absint( $layer->area_id ?? 0 ) ] ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/** Return whether persistence must fail closed when no payload is supplied. */
	private static function product_requires_personalisation( int $product_id, int $variation_id ): bool {
		$assignment = OC_DB::get_assignment_for_product( $product_id, $variation_id );
		if ( $assignment && self::assignment_has_usable_design( $assignment ) ) {
			return true;
		}

		$config = OC_DB::get_config_by_product( $product_id );
		return (bool) ( $config && (int) $config->active );
	}

	/** Validate and cache one exact legacy submission for the cart persistence filter. */
	public static function validate_legacy_submission( int $product_id, int $variation_id, mixed $raw ): true|\WP_Error {
		if ( ! is_string( $raw ) || '' === trim( $raw ) || '{}' === trim( $raw ) ) {
			return new \WP_Error( 'missing_customisation', __( 'Please complete your personalisation before adding to cart.', 'overcustomise' ) );
		}
		if ( strlen( $raw ) > 1024 * 1024 ) {
			return new \WP_Error( 'customisation_too_large', __( 'This personalisation is too large to add safely. Please simplify it or contact us for help.', 'overcustomise' ) );
		}

		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) || array_key_exists( 'v', $decoded ) ) {
			return new \WP_Error( 'invalid_customisation', __( 'Invalid personalisation data. Please refresh and try again.', 'overcustomise' ) );
		}

		$normalised = self::normalise_legacy_submission( $product_id, $variation_id, $decoded );
		if ( is_wp_error( $normalised ) ) {
			return $normalised;
		}
		self::$validated_submissions[ self::submission_key( $product_id, $variation_id, $raw ) ] = [
			'decoded'    => $decoded,
			'normalised' => $normalised,
		];
		return true;
	}

	/**
	 * Authoritatively normalise the retired product-config payload format.
	 *
	 * @return array{config:object,areas:array<string,array>}|\WP_Error
	 */
	public static function normalise_legacy_submission( int $product_id, int $variation_id, array $decoded ): array|\WP_Error {
		$config = OC_DB::get_config_by_product( $product_id );
		if ( ! $config || ! (int) $config->active ) {
			return new \WP_Error( 'invalid_config', __( 'Personalisation is not available for this product.', 'overcustomise' ) );
		}

		$configured = [];
		foreach ( OC_DB::get_print_areas( (int) $config->id ) as $area ) {
			$key = sanitize_key( is_scalar( $area->area_key ?? null ) ? (string) $area->area_key : '' );
			if ( '' === $key || isset( $configured[ $key ] ) ) {
				return new \WP_Error( 'invalid_config', __( 'This product has an invalid personalisation area configuration.', 'overcustomise' ) );
			}
			$configured[ $key ] = $area;
		}
		if ( ! $configured ) {
			return new \WP_Error( 'invalid_config', __( 'This product has no usable personalisation areas.', 'overcustomise' ) );
		}

		$submitted = [];
		foreach ( $decoded as $raw_key => $area_data ) {
			$key = is_scalar( $raw_key ) ? (string) $raw_key : '';
			if ( in_array( $key, [ 'uploadToken', 'previewImage', 'previewUrl' ], true ) ) {
				continue;
			}
			$safe_key = sanitize_key( $key );
			if ( '' === $safe_key || ! isset( $configured[ $safe_key ] ) || isset( $submitted[ $safe_key ] ) ) {
				return new \WP_Error( 'unknown_area', __( 'Invalid personalisation area. Please refresh and try again.', 'overcustomise' ) );
			}
			if ( ! is_array( $area_data ) ) {
				return new \WP_Error( 'invalid_area', __( 'Invalid personalisation data. Please refresh and try again.', 'overcustomise' ) );
			}
			$submitted[ $safe_key ] = $area_data;
		}

		$upload_token   = is_string( $decoded['uploadToken'] ?? null ) ? $decoded['uploadToken'] : '';
		$custom_type    = in_array( (string) ( $config->custom_type ?? '' ), [ 'text_only', 'photo_text' ], true ) ? (string) $config->custom_type : 'text_only';
		$requires_image = 'photo_text' === $custom_type;
		$font_ids       = array_map( static fn ( $font ): int => (int) $font->id, OC_DB::get_fonts( true ) );
		$fallback_font  = (int) ( $font_ids[0] ?? 0 );
		$normalised     = [];

		foreach ( $configured as $area_key => $area ) {
			$area_data = $submitted[ $area_key ] ?? null;
			if ( ! is_array( $area_data ) ) {
				return new \WP_Error( 'missing_area', sprintf( __( 'Please complete "%s".', 'overcustomise' ), $area->label ?: $area_key ) );
			}

			$text = is_scalar( $area_data['text'] ?? null ) ? sanitize_textarea_field( (string) $area_data['text'] ) : '';
			$attachment_id = is_scalar( $area_data['artworkAttachmentId'] ?? null ) ? absint( $area_data['artworkAttachmentId'] ) : 0;
			if ( '' === trim( $text ) ) {
				return new \WP_Error( 'required_text', sprintf( __( 'Please enter text for "%s".', 'overcustomise' ), $area->label ?: $area_key ) );
			}
			if ( $requires_image && ! $attachment_id ) {
				return new \WP_Error( 'required_artwork', sprintf( __( 'Please upload artwork for "%s".', 'overcustomise' ), $area->label ?: $area_key ) );
			}
			if ( $attachment_id && ! OC_Upload_Handler::legacy_attachment_is_accepted( $attachment_id, $product_id, $variation_id, $upload_token ) ) {
				return new \WP_Error( 'invalid_attachment', __( 'The uploaded artwork is no longer valid for this product. Please upload it again.', 'overcustomise' ) );
			}

			$font_id = is_scalar( $area_data['fontId'] ?? null ) ? absint( $area_data['fontId'] ) : 0;
			if ( ! in_array( $font_id, $font_ids, true ) ) {
				$font_id = $fallback_font;
			}
			$colour = sanitize_hex_color( is_string( $area_data['color'] ?? null ) ? $area_data['color'] : '#000000' ) ?: '#000000';
			$normalised[ $area_key ] = [
				'text'                 => $text,
				'fontId'               => $font_id,
				'color'                => $colour,
				'artworkAttachmentId' => $attachment_id,
			];
		}

		return $normalised ? [ 'config' => $config, 'areas' => $normalised ] : new \WP_Error( 'empty_customisation', __( 'Please complete your personalisation before adding to cart.', 'overcustomise' ) );
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

		$methods         = [];
		$visible_area_ids = [];
		foreach ( OC_DB::get_design_print_areas( $design_id ) as $area ) {
			if ( isset( $area->visible ) && ! (bool) $area->visible ) {
				continue;
			}
			$area_id = absint( $area->id ?? 0 );
			if ( ! $area_id ) {
				continue;
			}
			$visible_area_ids[ $area_id ] = true;
			$methods[ $area_id ] = sanitize_key( is_scalar( $area->print_method ?? null ) ? (string) $area->print_method : '' );
		}

		$all_design_layers  = OC_DB::get_design_layers( $design_id );
		$known_layer_ids    = [];
		$eligible_layer_ids = [];
		foreach ( $all_design_layers as $layer ) {
			$layer_id = absint( $layer->id ?? 0 );
			if ( ! $layer_id ) {
				continue;
			}
			$known_layer_ids[ $layer_id ] = true;
			if ( ( ! isset( $layer->visible ) || (bool) $layer->visible ) && ! empty( $visible_area_ids[ absint( $layer->area_id ?? 0 ) ] ) ) {
				$eligible_layer_ids[ $layer_id ] = true;
			}
		}
		$submitted_layer_ids = [];
		$canonical_raw_layers = [];
		foreach ( array_keys( $raw_layers ) as $raw_layer_id ) {
			$layer_id = is_scalar( $raw_layer_id ) ? absint( $raw_layer_id ) : 0;
			if ( ! $layer_id || empty( $known_layer_ids[ $layer_id ] ) || isset( $submitted_layer_ids[ $layer_id ] ) ) {
				return new \WP_Error( 'unknown_layer', __( 'Invalid personalisation layer. Please refresh and try again.', 'overcustomise' ) );
			}
			if ( empty( $eligible_layer_ids[ $layer_id ] ) ) {
				return new \WP_Error( 'hidden_layer', __( 'A submitted personalisation layer is no longer available. Please refresh and try again.', 'overcustomise' ) );
			}
			if ( ! is_array( $raw_layers[ $raw_layer_id ] ) ) {
				return new \WP_Error( 'invalid_layer', __( 'Invalid personalisation layer data. Please refresh and try again.', 'overcustomise' ) );
			}
			$submitted_layer_ids[ $layer_id ] = true;
			$canonical_raw_layers[ $layer_id ] = $raw_layers[ $raw_layer_id ];
		}
		$raw_layers = $canonical_raw_layers;
		$design_layers = array_values( array_filter(
			$all_design_layers,
			static fn ( $layer ): bool => ! empty( $eligible_layer_ids[ absint( $layer->id ?? 0 ) ] )
		) );
		if ( ! $visible_area_ids || ! $design_layers ) {
			return new \WP_Error( 'invalid_design', __( 'This design has no available personalisation areas.', 'overcustomise' ) );
		}
		$raw_layers = self::synchronise_linked_layer_inputs( $design_layers, $raw_layers );
		$raw_layers = self::synchronise_linked_layer_colours( $design_layers, $raw_layers );

		$active_fonts    = OC_DB::get_fonts( true );
		$active_font_ids = array_map( static fn( $font ) => (int) $font->id, $active_fonts );
		$font_names      = [];
		foreach ( $active_fonts as $font ) {
			$font_names[ (int) $font->id ] = sanitize_text_field( (string) $font->name );
		}
		$colour_names = [];
		foreach ( OC_DB::get_colours( true ) as $available_colour ) {
			$hex = sanitize_hex_color( (string) ( $available_colour->hex ?? '' ) );
			if ( $hex ) {
				$colour_names[ strtolower( $hex ) ] = sanitize_text_field( (string) ( $available_colour->name ?? '' ) );
			}
		}
		$fallback_font_id = (int) ( $active_font_ids[0] ?? 0 );
		$active_filters = [];
		foreach ( OC_DB::get_image_filters( true ) as $filter ) {
			$active_filters[ (int) $filter->id ] = $filter;
		}
		$normalised       = [];
		$valid_types      = [ 'text', 'textarea', 'image', 'clipmask', 'spotify', 'lineart', 'clipart' ];

		foreach ( $design_layers as $layer ) {
			if ( isset( $layer->visible ) && ! (bool) $layer->visible ) {
				continue;
			}
			$layer_id = absint( $layer->id ?? 0 );
			$type     = sanitize_key( (string) ( $layer->type ?? '' ) );
			if ( 'mask' === $type ) {
				continue;
			}
			if ( ! $layer_id || ! in_array( $type, $valid_types, true ) ) {
				continue;
			}

			$settings = self::normalise_layer_settings( $layer->settings ?? [], $type );
			$posted   = is_array( $raw_layers[ $layer_id ] ?? null ) ? $raw_layers[ $layer_id ] : [];
			$editable = ( ! isset( $layer->visible ) || (bool) $layer->visible ) && empty( $layer->locked );
			$source   = $editable ? $posted : [];

			$default_value = is_scalar( $settings['default_text'] ?? null ) ? (string) $settings['default_text'] : '';
			$value         = is_scalar( $source['value'] ?? null ) ? (string) $source['value'] : $default_value;
			$value         = 'textarea' === $type && function_exists( 'sanitize_textarea_field' ) ? sanitize_textarea_field( $value ) : sanitize_text_field( $value );
			if ( 'spotify' === $type && '' !== trim( $value ) ) {
				$value = self::normalise_spotify_value( $value );
				if ( '' === $value ) {
					return new \WP_Error( 'invalid_spotify', sprintf( __( 'The Spotify link in "%s" is invalid.', 'overcustomise' ), $layer->label ?: ucfirst( $type ) ) );
				}
				$spotify_validation = OC_Rest_API::validate_spotify_availability( $value, false, false );
				$spotify_proof      = is_scalar( $source['spotifyValidationProof'] ?? null ) ? (string) $source['spotifyValidationProof'] : '';
				$spotify_expires    = absint( $source['spotifyValidationExpires'] ?? 0 );
				$proof_valid        = OC_Rest_API::verify_spotify_validation_proof( $value, $spotify_proof, $spotify_expires );
				if ( ( is_wp_error( $spotify_validation ) || empty( $spotify_validation['valid'] ) ) && ! $proof_valid ) {
					return new \WP_Error( 'invalid_spotify', sprintf( __( 'The Spotify link in "%s" is unavailable or could not be validated.', 'overcustomise' ), $layer->label ?: ucfirst( $type ) ) );
				}
			}
			$char_limit      = absint( $settings['char_limit'] ?? 0 );
			$effective_limit = $char_limit ?: 10000;
			if ( self::string_length_static( $value ) > $effective_limit ) {
				return new \WP_Error( 'character_limit', sprintf( __( '"%1$s" exceeds the maximum of %2$d characters.', 'overcustomise' ), $layer->label ?: ucfirst( $type ), $effective_limit ) );
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

			$default_size = min( 1000, absint( $settings['default_font_size'] ?? 0 ) );
			$font_size    = min( 1000, absint( $source['fontSize'] ?? $default_size ) );
			if ( ! $editable || empty( $settings['allow_size_change'] ) ) {
				$font_size = $default_size;
			}
			$min_font_size = min( 1000, absint( $settings['min_font_size'] ?? 0 ) );
			$max_font_size = min( 1000, absint( $settings['max_font_size'] ?? 0 ) );
			if ( $max_font_size > 0 && $min_font_size > $max_font_size ) {
				$min_font_size = $max_font_size;
			}
			if ( $font_size > 0 ) {
				if ( $min_font_size > 0 ) $font_size = max( $min_font_size, $font_size );
				if ( $max_font_size > 0 ) $font_size = min( $max_font_size, $font_size );
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
			if ( $default_attachment && ( ! OC_Upload_Handler::admin_default_attachment_is_valid( $default_attachment ) || ! str_starts_with( (string) get_post_mime_type( $default_attachment ), 'image/' ) ) ) {
				$default_attachment = 0;
			}
			$attachment_id      = in_array( $type, [ 'image', 'clipmask' ], true ) ? absint( $source['attachmentId'] ?? $default_attachment ) : 0;
			$source_attachment_id = in_array( $type, [ 'image', 'clipmask' ], true ) ? absint( $source['sourceAttachmentId'] ?? $attachment_id ) : 0;
			$can_image_change = ! array_key_exists( 'allow_image_change', $settings ) || ! empty( $settings['allow_image_change'] );
			if ( ! $editable || ! $can_image_change ) {
				$attachment_id        = $default_attachment;
				$source_attachment_id = $default_attachment;
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

			$filter_ids = 'image' === $type ? array_values( array_intersect( self::id_list( $settings['image_filter_ids'] ?? [] ), array_keys( $active_filters ) ) ) : [];
			$default_filter = 'image' === $type ? absint( $settings['default_image_filter_id'] ?? 0 ) : 0;
			$filter_id      = absint( $source['imageFilterId'] ?? $default_filter );
			$can_filter_change = ! array_key_exists( 'allow_image_filter_change', $settings ) || ! empty( $settings['allow_image_filter_change'] );
			if ( ! in_array( $default_filter, $filter_ids, true ) ) $default_filter = 0;
			if ( ! $editable || ! $can_filter_change || ! in_array( $filter_id, $filter_ids, true ) ) $filter_id = $default_filter;
			$selected_filter = $filter_id ? ( $active_filters[ $filter_id ] ?? null ) : null;
			$image_crop = 'image' === $type && $editable && $can_image_change
				? max( 0, min( 100, absint( $source['imageCrop'] ?? 0 ) ) )
				: 0;
			if ( $filter_id && $selected_filter && 'ai' === (string) $selected_filter->filter_key ) {
				$generated_filter_id = absint( get_post_meta( $attachment_id, '_oc_ai_filter_id', true ) );
				$generated_source_id = absint( get_post_meta( $attachment_id, '_oc_ai_filter_source_id', true ) );
				if ( $generated_filter_id !== $filter_id || $generated_source_id !== $source_attachment_id ) {
					return new \WP_Error( 'ai_filter_required', sprintf( __( 'The image effect for "%s" is still processing.', 'overcustomise' ), $layer->label ?: ucfirst( $type ) ) );
				}
			}

			$default_clipart = absint( $settings['default_clipart_id'] ?? 0 );
			$has_posted_clipart = array_key_exists( 'clipartId', $source );
			$clipart_id      = 'clipart' === $type ? absint( $source['clipartId'] ?? $default_clipart ) : 0;
			$can_clipart_change = ! array_key_exists( 'allow_clipart_change', $settings ) || ! empty( $settings['allow_clipart_change'] );
			if ( ! $editable || ! $can_clipart_change ) {
				$clipart_id = $default_clipart;
			}
			$clipart_groups = self::id_list( $settings['clipart_groups'] ?? [] );
			$print_method   = $methods[ absint( $layer->area_id ?? 0 ) ] ?? '';
			$clipart        = $clipart_id ? self::get_allowed_clipart( $clipart_id, $clipart_groups, $print_method ) : null;
			if ( $clipart_id && ! $clipart ) {
				$using_default = ! $has_posted_clipart || $clipart_id === $default_clipart;
				if ( ! $editable || ! $can_clipart_change || ( $using_default && ! empty( $settings['required'] ) ) ) {
					$clipart = self::get_first_allowed_clipart( $clipart_groups, $print_method );
					$clipart_id = $clipart ? (int) $clipart->id : 0;
				} elseif ( $using_default ) {
					$clipart_id = 0;
				} else {
					return new \WP_Error( 'invalid_clipart', sprintf( __( 'Please choose an available clipart for "%s".', 'overcustomise' ), $layer->label ?: ucfirst( $type ) ) );
				}
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
				'type' => $type, 'value' => $value, 'fontId' => $font_id, 'fontName' => $font_names[ $font_id ] ?? '', 'fontSize' => $font_size,
				'colorHex' => $colour, 'colorName' => $colour_names[ strtolower( $colour ) ] ?? '', 'attachmentId' => $attachment_id, 'sourceAttachmentId' => $source_attachment_id, 'imageFilterId' => $filter_id, 'imageCrop' => $image_crop,
				'imageFilterKey' => $selected_filter ? sanitize_key( (string) $selected_filter->filter_key ) : '',
				'imageFilterValue' => $selected_filter ? (float) $selected_filter->value : 0.0,
				'previewAttachmentId' => $preview_attachment_id,
				'clipartId' => $clipart_id,
				'clipartUrl' => $clipart ? self::clipart_url( (string) $clipart->file_path ) : '',
				'clipartRecolourable' => $clipart && (bool) $clipart->colour_changeable && 'svg' === strtolower( (string) $clipart->file_type ),
			];
			if ( 'textarea' === $type ) {
				$rendered_lines = self::normalise_rendered_text_lines( $posted['renderedLines'] ?? null, $value );
				if ( null !== $rendered_lines ) {
					$normalised[ $layer_id ]['renderedLines'] = $rendered_lines;
				}
			}
		}

		$normalised = self::synchronise_normalised_linked_colours( $design_layers, $normalised );

		return $normalised ? [ 'design' => $design, 'layers' => $normalised ] : new \WP_Error( 'invalid_design', __( 'Design has no valid layers.', 'overcustomise' ) );
	}

	/** Retain browser-resolved textarea wrapping only when it contains the submitted text unchanged. */
	private static function normalise_rendered_text_lines( mixed $raw_lines, string $value ): ?array {
		if ( ! is_array( $raw_lines ) || empty( $raw_lines ) || count( $raw_lines ) > 200 ) {
			return null;
		}

		$lines = [];
		foreach ( $raw_lines as $line ) {
			if ( ! is_scalar( $line ) ) {
				return null;
			}
			$lines[] = sanitize_text_field( (string) $line );
		}

		$normalise = static function ( string $text ): string {
			$text = str_replace( [ "\r\n", "\r" ], "\n", $text );
			$text = preg_replace( '/\s+/u', ' ', trim( $text ) );

			return is_string( $text ) ? $text : '';
		};

		return $normalise( implode( "\n", $lines ) ) === $normalise( $value ) ? $lines : null;
	}

	/** Copy the one rendered linked control to every server-confirmed group member. */
	private static function synchronise_linked_layer_inputs( array $layers, array $raw_layers ): array {
		$groups = [];
		foreach ( $layers as $layer ) {
			$settings = self::normalise_layer_settings( $layer->settings ?? [], sanitize_key( (string) ( $layer->type ?? '' ) ) );
			$group    = sanitize_key( (string) ( $settings['link_group'] ?? '' ) );
			if ( '' !== $group && ( ! isset( $layer->visible ) || (bool) $layer->visible ) && empty( $layer->locked ) ) {
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
				$rendered_lines = $raw_layers[ $layer_id ]['renderedLines'] ?? null;
				$raw_layers[ $layer_id ] = $source_data;
				if ( is_array( $rendered_lines ) ) {
					$raw_layers[ $layer_id ]['renderedLines'] = $rendered_lines;
				}
			}
		}

		return $raw_layers;
	}

	/** Keep a colour selected on one control identical across colour-linked layer types. */
	private static function synchronise_linked_layer_colours( array $layers, array $raw_layers ): array {
		$groups = [];
		foreach ( $layers as $layer ) {
			$type = sanitize_key( (string) ( $layer->type ?? '' ) );
			if ( ! in_array( $type, [ 'text', 'textarea', 'image', 'clipart', 'lineart' ], true )
				|| ( isset( $layer->visible ) && ! (bool) $layer->visible )
				|| ! empty( $layer->locked )
			) {
				continue;
			}
			$settings = self::normalise_layer_settings( $layer->settings ?? [], $type );
			if ( 'image' === $type && empty( $settings['enable_image_colour'] ) ) {
				continue;
			}
			$group = $settings['colour_link_group'];
			if ( '' !== $group ) {
				$groups[ $group ][] = [ 'id' => (int) $layer->id, 'settings' => $settings ];
			}
		}

		foreach ( $groups as $members ) {
			if ( count( $members ) < 2 ) {
				continue;
			}
			$colour = '';
			foreach ( $members as $member ) {
				$candidate = $raw_layers[ $member['id'] ]['colorHex'] ?? '';
				if ( ! empty( $member['settings']['allow_colour_change'] ) && is_string( $candidate ) && sanitize_hex_color( $candidate ) ) {
					$colour = sanitize_hex_color( $candidate );
					break;
				}
			}
			if ( '' === $colour ) {
				$colour = $members[0]['settings']['default_color'];
			}
			foreach ( $members as $member ) {
				$raw_layers[ $member['id'] ] = is_array( $raw_layers[ $member['id'] ] ?? null ) ? $raw_layers[ $member['id'] ] : [];
				$raw_layers[ $member['id'] ]['colorHex'] = $colour;
				$raw_layers[ $member['id'] ]['_oc_linked_colour'] = true;
			}
		}

		return $raw_layers;
	}

	/** Apply the validated source colour after target defaults and restrictions are normalised. */
	private static function synchronise_normalised_linked_colours( array $layers, array $normalised ): array {
		$groups = [];
		foreach ( $layers as $layer ) {
			$type = sanitize_key( (string) ( $layer->type ?? '' ) );
			if ( ! isset( $normalised[ (int) $layer->id ] )
				|| ! in_array( $type, [ 'text', 'textarea', 'image', 'clipart', 'lineart' ], true )
				|| ! empty( $layer->locked )
			) {
				continue;
			}
			$settings = self::normalise_layer_settings( $layer->settings ?? [], $type );
			if ( 'image' === $type && empty( $settings['enable_image_colour'] ) ) {
				continue;
			}
			if ( '' !== $settings['colour_link_group'] ) {
				$groups[ $settings['colour_link_group'] ][] = [
					'id'       => (int) $layer->id,
					'editable' => ! empty( $settings['allow_colour_change'] ),
				];
			}
		}

		foreach ( $groups as $members ) {
			if ( count( $members ) < 2 ) {
				continue;
			}
			$source = $members[0];
			foreach ( $members as $member ) {
				if ( $member['editable'] ) {
					$source = $member;
					break;
				}
			}
			$colour = $normalised[ $source['id'] ]['colorHex'];
			foreach ( $members as $member ) {
				$normalised[ $member['id'] ]['colorHex'] = $colour;
			}
		}

		return $normalised;
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

		$variants = json_decode( is_scalar( $assignment->design_variants ?? null ) ? (string) $assignment->design_variants : '', true );
		foreach ( is_array( $variants ) ? $variants : [] as $variant ) {
			if ( ! is_array( $variant ) || $design_id !== absint( $variant['designId'] ?? 0 ) ) {
				continue;
			}
			$design = OC_DB::get_design( $design_id );
			$label  = sanitize_text_field( is_scalar( $variant['label'] ?? null ) ? (string) $variant['label'] : '' );
			return [
				'id'    => 'design-' . $design_id,
				'label' => $label ?: ( $design ? sanitize_text_field( (string) $design->name ) : '' ),
			];
		}

		return null;
	}

	/** Normalise every layer setting exposed to public state or cart validation. */
	public static function normalise_layer_settings( mixed $value, string $type = '' ): array {
		if ( is_string( $value ) ) {
			$value = json_decode( $value, true );
		}
		$value = is_array( $value ) ? $value : [];
		$type  = sanitize_key( $type );

		$string = static fn ( mixed $item, string $default = '' ): string => is_scalar( $item ) ? (string) $item : $default;
		$number = static fn ( mixed $item, int $default = 0 ): int => is_numeric( $item ) ? (int) $item : $default;
		$boolean = static function ( mixed $item, bool $default = false ): bool {
			if ( is_bool( $item ) ) {
				return $item;
			}
			if ( is_scalar( $item ) ) {
				$normalised = filter_var( $item, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
				return null === $normalised ? $default : $normalised;
			}
			return $default;
		};
		$alignment = sanitize_key( $string( $value['alignment'] ?? 'center', 'center' ) );
		$line_alignment = sanitize_key( $string( $value['line_alignment'] ?? 'top', 'top' ) );
		$mask_shape = sanitize_key( $string( $value['mask_shape'] ?? 'circle', 'circle' ) );
		$default_formats = match ( $type ) {
			'image'    => [ 'png', 'jpg', 'svg', 'webp' ],
			'clipmask' => [ 'png', 'jpg', 'webp' ],
			default    => [],
		};
		$formats = [];
		foreach ( is_array( $value['formats'] ?? null ) ? $value['formats'] : [] as $format ) {
			if ( is_scalar( $format ) ) {
				$formats[] = ltrim( sanitize_key( (string) $format ), '.' );
			}
		}
		$formats = array_values( array_unique( array_intersect( [ 'png', 'jpg', 'jpeg', 'svg', 'webp', 'pdf', 'eps' ], $formats ) ) );
		if ( ! array_key_exists( 'formats', $value ) || ! is_array( $value['formats'] ) ) {
			$formats = $default_formats;
		}
		$default_text = sanitize_textarea_field( $string( $value['default_text'] ?? '' ) );
		if ( self::string_length_static( $default_text ) > 10000 ) {
			$default_text = function_exists( 'mb_substr' ) ? mb_substr( $default_text, 0, 10000, 'UTF-8' ) : substr( $default_text, 0, 10000 );
		}
		$default_attachment_url = substr( esc_url_raw( $string( $value['default_attachment_url'] ?? '' ) ), 0, 2048 );
		$default_clipart_url    = substr( esc_url_raw( $string( $value['default_clipart_url'] ?? '' ) ), 0, 2048 );
		$link_group             = substr( sanitize_key( $string( $value['link_group'] ?? '' ) ), 0, 64 );
		$colour_link_group      = substr( sanitize_key( $string( $value['colour_link_group'] ?? '' ) ), 0, 64 );

		return [
			'default_text'                => $default_text,
			'char_limit'                  => max( 0, min( 10000, $number( $value['char_limit'] ?? 0 ) ) ),
			'alignment'                   => in_array( $alignment, [ 'left', 'center', 'right' ], true ) ? $alignment : 'center',
			'line_alignment'              => in_array( $line_alignment, [ 'top', 'center', 'bottom' ], true ) ? $line_alignment : 'top',
			'default_font_id'             => max( 0, $number( $value['default_font_id'] ?? 0 ) ),
			'default_font_size'           => max( 0, min( 1000, $number( $value['default_font_size'] ?? 0 ) ) ),
			'min_font_size'               => max( 0, min( 1000, $number( $value['min_font_size'] ?? 0 ) ) ),
			'max_font_size'               => max( 0, min( 1000, $number( $value['max_font_size'] ?? 0 ) ) ),
			'default_color'               => sanitize_hex_color( $string( $value['default_color'] ?? '#000000', '#000000' ) ) ?: '#000000',
			'font_groups'                 => self::id_list( $value['font_groups'] ?? [] ),
			'colour_groups'               => self::id_list( $value['colour_groups'] ?? [] ),
			'clipart_groups'              => self::id_list( $value['clipart_groups'] ?? [] ),
			'image_filter_ids'            => self::id_list( $value['image_filter_ids'] ?? [] ),
			'default_image_filter_id'     => max( 0, $number( $value['default_image_filter_id'] ?? 0 ) ),
			'enable_image_colour'         => $boolean( $value['enable_image_colour'] ?? false ),
			'default_attachment_id'       => max( 0, $number( $value['default_attachment_id'] ?? 0 ) ),
			'default_attachment_url'      => $default_attachment_url,
			'default_clipart_id'          => max( 0, $number( $value['default_clipart_id'] ?? 0 ) ),
			'default_clipart_url'         => $default_clipart_url,
			'default_clipart_recolourable' => $boolean( $value['default_clipart_recolourable'] ?? false ),
			'allow_font_change'           => $boolean( $value['allow_font_change'] ?? true, true ),
			'allow_colour_change'         => $boolean( $value['allow_colour_change'] ?? true, true ),
			'allow_size_change'           => $boolean( $value['allow_size_change'] ?? false ),
			'allow_image_change'          => $boolean( $value['allow_image_change'] ?? true, true ),
			'allow_image_filter_change'   => $boolean( $value['allow_image_filter_change'] ?? true, true ),
			'allow_clipart_change'        => $boolean( $value['allow_clipart_change'] ?? true, true ),
			'required'                    => $boolean( $value['required'] ?? false ),
			'formats'                     => $formats,
			'max_size_mb'                 => max( 1, min( 100, $number( $value['max_size_mb'] ?? 10, 10 ) ) ),
			'remove_background'           => $boolean( $value['remove_background'] ?? false ),
			'mask_shape'                  => in_array( $mask_shape, [ 'circle', 'square', 'rectangle' ], true ) ? $mask_shape : 'circle',
			'clipart_display'             => 'carousel' === sanitize_key( $string( $value['clipart_display'] ?? 'grid', 'grid' ) ) ? 'carousel' : 'grid',
			'link_group'                  => $link_group,
			'colour_link_group'           => $colour_link_group,
		];
	}

	private static function id_list( mixed $value ): array {
		$ids = [];
		foreach ( is_array( $value ) ? $value : [] as $item ) {
			if ( is_scalar( $item ) ) {
				$id = absint( $item );
				if ( $id ) $ids[] = $id;
			}
		}
		return array_values( array_unique( $ids ) );
	}

	private static function string_length_static( string $value ): int {
		return function_exists( 'mb_strlen' ) ? (int) mb_strlen( $value, 'UTF-8' ) : strlen( $value );
	}

	private static function get_allowed_clipart( int $clipart_id, array $group_ids, string $print_method ): ?object {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT id, file_path, file_type, colour_changeable, allowed_print_methods FROM {$wpdb->prefix}oc_clipart WHERE id = %d AND active = 1 LIMIT 1", $clipart_id ) );
		if ( ! $row || '' === self::clipart_url( (string) ( $row->file_path ?? '' ) ) ) return null;
		if ( $group_ids ) {
			$placeholders = implode( ',', array_fill( 0, count( $group_ids ), '%d' ) );
			$in_group = $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM {$wpdb->prefix}oc_clipart_group_items WHERE clipart_id = %d AND group_id IN ($placeholders) LIMIT 1", $clipart_id, ...$group_ids ) );
			if ( ! $in_group ) return null;
		}
		$methods = self::normalise_clipart_print_methods( (string) ( $row->allowed_print_methods ?? '' ) );
		return $methods && ! in_array( sanitize_key( $print_method ), $methods, true ) ? null : $row;
	}

	private static function get_first_allowed_clipart( array $group_ids, string $print_method ): ?object {
		global $wpdb;
		if ( $group_ids ) {
			$placeholders = implode( ',', array_fill( 0, count( $group_ids ), '%d' ) );
			$ids = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT c.id FROM {$wpdb->prefix}oc_clipart c JOIN {$wpdb->prefix}oc_clipart_group_items gi ON gi.clipart_id = c.id WHERE c.active = 1 AND gi.group_id IN ($placeholders) ORDER BY c.name ASC", ...$group_ids ) ) ?: [];
		} else {
			$ids = $wpdb->get_col( "SELECT id FROM {$wpdb->prefix}oc_clipart WHERE active = 1 ORDER BY name ASC" ) ?: [];
		}
		foreach ( array_map( 'absint', $ids ) as $clipart_id ) {
			$clipart = self::get_allowed_clipart( $clipart_id, $group_ids, $print_method );
			if ( $clipart ) {
				return $clipart;
			}
		}
		return null;
	}

	private static function clipart_url( string $path ): string {
		$uploads = wp_upload_dir();
		$base    = realpath( (string) ( $uploads['basedir'] ?? '' ) );
		$root    = realpath( trailingslashit( (string) ( $uploads['basedir'] ?? '' ) ) . 'overcustomise/clipart' );
		$real    = realpath( $path );
		$base_path = $base ? rtrim( wp_normalize_path( $base ), '/' ) : '';
		$root_path = $root ? rtrim( wp_normalize_path( $root ), '/' ) : '';
		$real_path = $real ? wp_normalize_path( $real ) : '';
		if ( '' === $base_path || '' === $root_path || '' === $real_path || ! is_file( $real )
			|| ! str_starts_with( $root_path, $base_path . '/' )
			|| ! str_starts_with( $real_path, $root_path . '/' )
		) {
			return '';
		}
		$relative = ltrim( substr( $real_path, strlen( $base_path ) ), '/' );
		return esc_url_raw( trailingslashit( (string) $uploads['baseurl'] ) . $relative );
	}

	/** Accept only a currently valid signed private preview URL. */
	private function validate_preview_url( string $preview_url ): string {
		return OC_Rest_API::validate_private_preview_url( $preview_url );
	}

	// -------------------------------------------------------------------------
	// Cart / checkout display
	// -------------------------------------------------------------------------

	public function display_item_data( array $item_data, array $cart_item ): array {
		$customisation = $cart_item['_oc_customisation'] ?? null;
		if ( empty( $customisation ) || ! is_array( $customisation ) ) {
			return $item_data;
		}

		// ── v2 format ────────────────────────────────────────────────────────────
		if ( isset( $customisation['v'] ) && 2 === (int) $customisation['v'] ) {
			$design_id = (int) ( $customisation['designId'] ?? $cart_item['_oc_design_id'] ?? 0 );
			$layers    = is_array( $customisation['layers'] ?? null ) ? $customisation['layers'] : [];

			if ( is_scalar( $customisation['designVariantLabel'] ?? null ) && '' !== trim( (string) $customisation['designVariantLabel'] ) ) {
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
				$type  = is_scalar( $layer_data['type'] ?? null ) ? sanitize_key( (string) $layer_data['type'] ) : '';
				$label = $layer ? ( $layer->label ?: ucfirst( (string) $layer->type ) ) : ucfirst( $type ?: __( 'Layer', 'overcustomise' ) );
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
			if ( is_scalar( $area_data['text'] ?? null ) && '' !== trim( (string) $area_data['text'] ) ) {
				$parts[] = esc_html( (string) $area_data['text'] );
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

		$fees                  = [];
		$this->fee_allocations = [];

		foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
			if ( empty( $cart_item['_oc_customisation'] ) ) {
				continue;
			}

			$raw_rate = is_numeric( $cart_item['_oc_flat_rate'] ?? null ) ? (float) $cart_item['_oc_flat_rate'] : 0.0;
			$rate     = is_finite( $raw_rate ) ? max( 0.0, min( 1000000.0, $raw_rate ) ) : 0.0;
			$quantity = max( 0, (int) ( $cart_item['quantity'] ?? 0 ) );
			$product  = $cart_item['data'] ?? null;
			$taxable  = $product instanceof \WC_Product && $product->is_taxable();
			$tax_class = $taxable ? (string) $product->get_tax_class() : '';
			$key       = ( $taxable ? 'taxable:' : 'exempt:' ) . $tax_class;
			if ( ! isset( $fees[ $key ] ) ) {
				$fees[ $key ] = [ 'amount' => 0.0, 'taxable' => $taxable, 'tax_class' => $tax_class, 'allocations' => [] ];
			}
			$line_fee = (float) wc_format_decimal( $rate * $quantity, wc_get_price_decimals() );
			$fees[ $key ]['amount'] += $line_fee;
			if ( $line_fee > 0 ) {
				$fees[ $key ]['allocations'][] = [
					'cart_item_key' => (string) $cart_item_key,
					'product_id'    => absint( $cart_item['product_id'] ?? 0 ),
					'variation_id'  => absint( $cart_item['variation_id'] ?? 0 ),
					'quantity'      => $quantity,
					'unit_amount'   => (string) wc_format_decimal( $rate, wc_get_price_decimals() ),
					'total_amount'  => (string) wc_format_decimal( $line_fee, wc_get_price_decimals() ),
				];
			}
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
			$this->fee_allocations[ $fee_name ] = $fee['allocations'];
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
		$raw_unit_fee = is_numeric( $values['_oc_flat_rate'] ?? null ) ? (float) $values['_oc_flat_rate'] : 0.0;
		$unit_fee = is_finite( $raw_unit_fee ) ? max( 0.0, min( 1000000.0, $raw_unit_fee ) ) : 0.0;
		$item->update_meta_data( '_oc_flat_rate', $unit_fee );
		$quantity = max( 0, (int) ( $values['quantity'] ?? $item->get_quantity() ) );
		$product  = $values['data'] ?? null;
		$item->update_meta_data( '_oc_cart_item_key', (string) $cart_item_key );
		$item->update_meta_data( '_oc_personalisation_fee_unit', wc_format_decimal( $unit_fee, wc_get_price_decimals() ) );
		$item->update_meta_data( '_oc_personalisation_fee_quantity', $quantity );
		$item->update_meta_data( '_oc_personalisation_fee_total', wc_format_decimal( $unit_fee * $quantity, wc_get_price_decimals() ) );
		$item->update_meta_data( '_oc_personalisation_fee_taxable', $product instanceof \WC_Product && $product->is_taxable() ? 'yes' : 'no' );
		$item->update_meta_data( '_oc_personalisation_fee_tax_class', $product instanceof \WC_Product && $product->is_taxable() ? (string) $product->get_tax_class() : '' );
		if ( ! empty( $values['_oc_preview_url'] ) ) {
			$item->update_meta_data( '_oc_preview_url', $values['_oc_preview_url'] );
		}
	}

	/** Mark plugin-created fee rows and retain their line-level allocation map. */
	public function mark_personalisation_fee_item( WC_Order_Item_Fee $item, string $fee_key, object $fee, WC_Order $order ): void {
		$name = is_scalar( $fee->name ?? null ) ? (string) $fee->name : '';
		if ( '' === $name || ! isset( $this->fee_allocations[ $name ] ) ) {
			return;
		}

		$item->update_meta_data( '_oc_personalisation_fee', 'yes' );
		$item->update_meta_data( '_oc_personalisation_fee_key', sanitize_key( $fee_key ) );
		$item->update_meta_data( '_oc_personalisation_fee_allocations', $this->fee_allocations[ $name ] );
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
			'_oc_cart_item_key',
			'_oc_personalisation_fee',
			'_oc_personalisation_fee_key',
			'_oc_personalisation_fee_unit',
			'_oc_personalisation_fee_quantity',
			'_oc_personalisation_fee_total',
			'_oc_personalisation_fee_taxable',
			'_oc_personalisation_fee_tax_class',
			'_oc_personalisation_fee_allocations',
			__( 'Preview Image', 'overcustomise' ),
			__( 'Personalisation Details', 'overcustomise' ),
		] ) ) );
	}

	// -------------------------------------------------------------------------
	// Admin order item display
	// -------------------------------------------------------------------------

	public function display_in_order( int $item_id, WC_Order_Item $item, WC_Order $order, bool $plain_text = false ): void {
		$customisation = $item->get_meta( '_oc_customisation', true );
		$preview_url   = $item->get_meta( '_oc_preview_url', true );
		$preview_url   = is_string( $preview_url ) ? $preview_url : '';
		if ( $plain_text ) {
			$this->display_plain_text_order_item( is_array( $customisation ) ? $customisation : [], $preview_url, $item );
			return;
		}
		$print_files   = is_admin() ? OC_DB::get_print_files_for_item( $item_id ) : [];

		if ( ( empty( $customisation ) || ! is_array( $customisation ) ) && empty( $preview_url ) && empty( $print_files ) ) {
			return;
		}

		echo '<div class="oc-order-item-meta" style="margin-top:10px;padding:12px;background:#fff;border:1px solid #dcdcde;border-radius:6px;font-size:12px;line-height:1.45;box-shadow:0 1px 2px rgba(0,0,0,0.04);max-width:560px;">';
		echo '<div style="display:flex;gap:14px;align-items:flex-start;">';

		// ── Preview image ─────────────────────────────────────────────────────
		if ( $preview_url ) {
			$preview_link_url = is_admin()
				? add_query_arg( 'TB_iframe', 'true', $preview_url )
				: $preview_url;
			echo '<div style="flex:0 0 auto;">'
			   . '<a href="' . esc_url( $preview_link_url ) . '" class="thickbox" style="display:inline-block;">'
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
			echo '</div></div></div>';
			return;
		}

		// ── v2 format ─────────────────────────────────────────────────────────
		if ( isset( $customisation['v'] ) && 2 === (int) $customisation['v'] ) {
			$design_id = (int) ( $customisation['designId'] ?? $item->get_meta( '_oc_design_id', true ) ?? 0 );
			$layers    = is_array( $customisation['layers'] ?? null ) ? $customisation['layers'] : [];

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
				$type  = is_scalar( $layer_data['type'] ?? null ) ? sanitize_key( (string) $layer_data['type'] ) : '';
				$label = $layer ? ( $layer->label ?: ucfirst( (string) $layer->type ) ) : ucfirst( $type ?: __( 'Layer', 'overcustomise' ) );
				$value = $this->layer_display_value( $layer_data, $layer );
				if ( ! $value ) continue;
				echo '<div style="display:grid;grid-template-columns:minmax(110px,38%) 1fr;gap:8px;align-items:start;margin:0 0 6px;">'
					. '<div style="color:#646970;font-weight:600;">' . esc_html( $label ) . '</div>'
					. '<div style="color:#1d2327;word-break:break-word;">' . $value . '</div>'
					. '</div>';
			}

			$this->render_admin_print_files( $item_id, $print_files, true, $order );
			echo '</div></div></div>';
			return;
		}

		// ── v1 / legacy format ────────────────────────────────────────────────
		foreach ( $customisation as $area_key => $area_data ) {
			if ( ! is_array( $area_data ) ) continue;
			$text        = is_scalar( $area_data['text'] ?? null ) ? (string) $area_data['text'] : '';
			$has_text    = '' !== trim( $text );
			$has_artwork = ! empty( $area_data['artworkAttachmentId'] );
			if ( ! $has_text && ! $has_artwork ) continue;

			echo '<div style="display:grid;grid-template-columns:minmax(110px,38%) 1fr;gap:8px;align-items:start;margin:0 0 6px;">'
				. '<div style="color:#646970;font-weight:600;">' . esc_html( ucwords( str_replace( '-', ' ', $area_key ) ) ) . '</div>'
				. '<div style="color:#1d2327;word-break:break-word;">';

			if ( $has_text ) {
				echo esc_html( $text );
				if ( ! empty( $area_data['fontId'] ) ) {
					global $wpdb;
					$font_name = (string) $wpdb->get_var( $wpdb->prepare(
						"SELECT name FROM {$wpdb->prefix}oc_fonts WHERE id = %d LIMIT 1",
						$area_data['fontId']
					) );
					if ( $font_name ) echo ' &mdash; ' . esc_html( $font_name );
				}
				$legacy_colour = is_string( $area_data['color'] ?? null ) ? sanitize_hex_color( $area_data['color'] ) : '';
				if ( $legacy_colour ) {
					printf(
						' &mdash; <span style="display:inline-block;width:12px;height:12px;background:%s;border:1px solid #ccc;vertical-align:middle;border-radius:2px;"></span> %s',
						esc_attr( $legacy_colour ),
						esc_html( $legacy_colour )
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
		echo '</div></div></div>';
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
		$type = is_scalar( $layer_data['type'] ?? null ) ? sanitize_key( (string) $layer_data['type'] ) : '';
		switch ( $type ) {
			case 'text':
			case 'textarea':
			case 'spotify':
				$val = is_scalar( $layer_data['value'] ?? null ) ? trim( (string) $layer_data['value'] ) : '';
				if ( ! $val ) {
					return '';
				}

				$html = esc_html( $val );
				if ( is_admin() && in_array( $type, [ 'text', 'textarea' ], true ) && ! empty( $layer_data['fontId'] ) && $this->customer_can_change_layer_setting( $layer, 'allow_font_change' ) ) {
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
		if ( ! $layer ) {
			return true;
		}

		$settings = self::normalise_layer_settings( $layer->settings ?? [], sanitize_key( (string) ( $layer->type ?? '' ) ) );
		if ( ! array_key_exists( $setting_key, $settings ) ) {
			return true;
		}

		return ! empty( $settings[ $setting_key ] );
	}

	/** Do not show fixed default clipart as customer-selected order data. */
	private function is_fixed_clipart_layer( ?object $layer, array $layer_data ): bool {
		if ( 'clipart' !== ( $layer_data['type'] ?? '' ) || empty( $layer_data['clipartId'] ) || ! $layer ) {
			return false;
		}

		$settings = self::normalise_layer_settings( $layer->settings ?? [], 'clipart' );

		return array_key_exists( 'allow_clipart_change', $settings ) && empty( $settings['allow_clipart_change'] );
	}

	/** Emit order customisation as actual plain text for plain-text emails. */
	private function display_plain_text_order_item( array $customisation, string $preview_url, WC_Order_Item $item ): void {
		if ( ! $customisation && '' === $preview_url ) {
			return;
		}

		$lines = [ __( 'Customisation:', 'overcustomise' ) ];
		if ( isset( $customisation['v'] ) && 2 === (int) $customisation['v'] ) {
			$design_id = absint( $customisation['designId'] ?? $item->get_meta( '_oc_design_id', true ) );
			$layer_map = [];
			foreach ( $design_id ? OC_DB::get_design_layers( $design_id ) : [] as $layer ) {
				$layer_map[ (int) $layer->id ] = $layer;
			}
			if ( is_scalar( $customisation['designVariantLabel'] ?? null ) && '' !== trim( (string) $customisation['designVariantLabel'] ) ) {
				$lines[] = __( 'Artwork Option', 'overcustomise' ) . ': ' . sanitize_text_field( (string) $customisation['designVariantLabel'] );
			}
			foreach ( is_array( $customisation['layers'] ?? null ) ? $customisation['layers'] : [] as $layer_id => $layer_data ) {
				if ( ! is_array( $layer_data ) ) {
					continue;
				}
				$layer = $layer_map[ absint( $layer_id ) ] ?? null;
				if ( $this->is_fixed_clipart_layer( $layer, $layer_data ) ) {
					continue;
				}
				$type  = sanitize_key( is_scalar( $layer_data['type'] ?? null ) ? (string) $layer_data['type'] : '' );
				$label = $layer ? ( (string) $layer->label ?: ucfirst( (string) $layer->type ) ) : ucfirst( $type ?: __( 'Layer', 'overcustomise' ) );
				$value = match ( $type ) {
					'text', 'textarea', 'spotify' => is_scalar( $layer_data['value'] ?? null ) ? sanitize_textarea_field( (string) $layer_data['value'] ) : '',
					'image', 'clipmask'            => ! empty( $layer_data['attachmentId'] ) ? __( 'Image uploaded', 'overcustomise' ) : '',
					'clipart'                      => ! empty( $layer_data['clipartId'] ) ? __( 'Clipart selected', 'overcustomise' ) : '',
					default                        => '',
				};
				if ( '' !== trim( $value ) ) {
					$lines[] = sanitize_text_field( $label ) . ': ' . $value;
				}
			}
		} else {
			foreach ( $customisation as $area_key => $area_data ) {
				if ( ! is_array( $area_data ) ) {
					continue;
				}
				$parts = [];
				if ( is_scalar( $area_data['text'] ?? null ) && '' !== trim( (string) $area_data['text'] ) ) {
					$parts[] = sanitize_textarea_field( (string) $area_data['text'] );
				}
				if ( ! empty( $area_data['artworkAttachmentId'] ) ) {
					$parts[] = __( 'Artwork attached', 'overcustomise' );
				}
				if ( $parts ) {
					$lines[] = ucwords( str_replace( '-', ' ', sanitize_key( (string) $area_key ) ) ) . ': ' . implode( '; ', $parts );
				}
			}
		}

		if ( '' !== $preview_url ) {
			$lines[] = __( 'Preview', 'overcustomise' ) . ': ' . esc_url_raw( $preview_url );
		}
		echo "\n" . implode( "\n", $lines ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plain-email values are sanitised above.
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
				$this->render_admin_regenerate_button( (int) $file->id );
			} elseif ( 'files_ready' === $file->file_status ) {
				echo '<em style="color:#888;">' . esc_html__( 'File missing on disk.', 'overcustomise' ) . '</em>';
				$this->render_admin_regenerate_button( (int) $file->id );
			} elseif ( 'pending' === $file->file_status ) {
				echo '<em style="color:#666;">' . esc_html__( 'Queued automatically.', 'overcustomise' ) . '</em>';
				$this->render_admin_regenerate_button( (int) $file->id );
			} elseif ( 'generating' === $file->file_status && empty( $queue_info['is_processing'] ) && empty( $queue_info['in_queue'] ) ) {
				echo '<em style="color:#b32d2e;">' . esc_html__( 'No active queue job.', 'overcustomise' ) . '</em>';
				$this->render_admin_regenerate_button( (int) $file->id );
			}

			echo '</div></div>';
			echo '</div>';
		}
		echo '</div>';
	}

	/** Render a POST button using the surrounding WooCommerce order form. */
	private function render_admin_regenerate_button( int $file_id ): void {
		$url = add_query_arg(
			[
				'action'         => 'oc_regenerate_print_file',
				'oc_print_nonce' => wp_create_nonce( 'oc_regenerate_' . $file_id ),
				'file_id'        => $file_id,
			],
			admin_url( 'admin-post.php' )
		);
		echo '<button type="submit" name="action" value="oc_regenerate_print_file"'
			. ' class="button button-small" formmethod="post" formaction="' . esc_url( $url ) . '" formnovalidate>'
			. esc_html__( 'Regenerate Print File', 'overcustomise' ) . '</button>';
	}

	private static function normalise_clipart_print_methods( string $raw ): array {
		if ( '' === trim( $raw ) ) {
			return [];
		}

		$decoded    = json_decode( $raw, true );
		$methods    = is_array( $decoded ) ? $decoded : explode( ',', $raw );
		$allowed    = [ 'engraving', 'uv', 'embroidery', 'sublimation' ];
		$normalised = [];
		foreach ( $methods as $method ) {
			if ( is_scalar( $method ) ) {
				$normalised[] = sanitize_key( (string) $method );
			}
		}

		return array_values( array_unique( array_intersect( $allowed, $normalised ) ) );
	}
}
