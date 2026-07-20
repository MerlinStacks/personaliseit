<?php
/**
 * WooCommerce Blocks integration.
 *
 * - Registers a Store API extension so the Cart Block and Checkout Block
 *   receive personalisation data (preview_url + summary) per cart item.
 * - Enqueues the blocks-integration.js frontend script on cart/checkout pages.
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

class OC_Blocks_Integration {

	public function register(): void {
		add_action( 'woocommerce_blocks_loaded',  [ $this, 'register_store_api_extension' ] );
		add_action( 'wp_enqueue_scripts',         [ $this, 'enqueue_blocks_script' ] );

		if ( did_action( 'woocommerce_blocks_loaded' ) ) {
			$this->register_store_api_extension();
		}
	}

	// ── Store API extension ───────────────────────────────────────────────────

	public function register_store_api_extension(): void {
		if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
			return;
		}

		woocommerce_store_api_register_endpoint_data( [
			'endpoint'        => \Automattic\WooCommerce\StoreApi\Schemas\V1\CartItemSchema::IDENTIFIER,
			'namespace'       => 'overcustomise',
			'data_callback'   => [ $this, 'cart_item_data' ],
			'schema_callback' => [ $this, 'cart_item_schema' ],
			'schema_type'     => ARRAY_A,
		] );
	}

	/** Return OC extension data for a single cart item. */
	public function cart_item_data( array $cart_item ): array {
		return [
			'preview_url' => is_string( $cart_item['_oc_preview_url'] ?? null ) ? esc_url_raw( $cart_item['_oc_preview_url'] ) : '',
			'summary'     => $this->build_summary( $cart_item ),
		];
	}

	/** Build an array of {key, value} summary lines for a cart item. */
	private function build_summary( array $cart_item ): array {
		$customisation = $cart_item['_oc_customisation'] ?? null;
		if ( empty( $customisation ) || ! is_array( $customisation ) ) {
			return [];
		}

		if ( ! isset( $customisation['v'] ) || 2 !== (int) $customisation['v'] ) {
			return $this->build_legacy_summary( $customisation );
		}

		$design_id = (int) ( $customisation['designId'] ?? $cart_item['_oc_design_id'] ?? 0 );
		$layers    = is_array( $customisation['layers'] ?? null ) ? $customisation['layers'] : [];

		$layer_map = [];
		if ( $design_id ) {
			foreach ( OC_DB::get_design_layers( $design_id ) as $l ) {
				$layer_map[ (int) $l->id ] = $l;
			}
		}

		$lines = [];
		if ( is_scalar( $customisation['designVariantLabel'] ?? null ) && '' !== trim( (string) $customisation['designVariantLabel'] ) ) {
			$lines[] = [
				'key'   => __( 'Artwork Option', 'overcustomise' ),
				'value' => (string) $customisation['designVariantLabel'],
			];
		}

		foreach ( $layers as $layer_id => $layer_data ) {
			if ( ! is_array( $layer_data ) ) continue;

			$layer = $layer_map[ (int) $layer_id ] ?? null;
			$label = $layer
				? ( $layer->label ?: ucfirst( $layer->type ) )
				: ucfirst( is_scalar( $layer_data['type'] ?? null ) ? (string) $layer_data['type'] : 'Layer' );

			$value = $this->layer_value( $layer_data );
			if ( ! $value ) continue;

			$lines[] = [ 'key' => $label, 'value' => $value ];
		}

		return $lines;
	}

	/** Build summary lines for the legacy v1 cart payload. */
	private function build_legacy_summary( array $customisation ): array {
		$lines = [];
		foreach ( $customisation as $area_key => $area_data ) {
			if ( ! is_array( $area_data ) ) continue;

			$parts = [];
			if ( is_scalar( $area_data['text'] ?? null ) && '' !== trim( (string) $area_data['text'] ) ) {
				$parts[] = sanitize_textarea_field( (string) $area_data['text'] );
			}
			if ( ! empty( $area_data['artworkAttachmentId'] ) ) {
				$parts[] = __( 'Artwork attached', 'overcustomise' );
			}
			if ( empty( $parts ) ) continue;

			$lines[] = [
				'key'   => sprintf( __( 'Personalisation (%s)', 'overcustomise' ), ucwords( str_replace( '-', ' ', (string) $area_key ) ) ),
				'value' => implode( ' ', $parts ),
			];
		}

		return $lines;
	}

	private function layer_value( array $layer_data ): string {
		$type = is_scalar( $layer_data['type'] ?? null ) ? sanitize_key( (string) $layer_data['type'] ) : '';
		switch ( $type ) {
			case 'text':
			case 'textarea':
			case 'spotify':
				return is_scalar( $layer_data['value'] ?? null ) ? trim( sanitize_textarea_field( (string) $layer_data['value'] ) ) : '';
			case 'image':
			case 'clipmask':
				return ! empty( $layer_data['attachmentId'] )
					? __( 'Image uploaded', 'overcustomise' ) : '';
			case 'clipart':
				return ! empty( $layer_data['clipartId'] )
					? __( 'Clipart selected', 'overcustomise' ) : '';
			default:
				return '';
		}
	}

	/** JSON schema for the extension data. */
	public function cart_item_schema(): array {
		return [
			'preview_url' => [
				'description' => __( 'Personalised preview image URL', 'overcustomise' ),
				'type'        => 'string',
				'context'     => [ 'view', 'edit' ],
				'readonly'    => true,
			],
			'summary' => [
				'description' => __( 'Personalisation summary lines', 'overcustomise' ),
				'type'        => 'array',
				'context'     => [ 'view', 'edit' ],
				'readonly'    => true,
				'items'       => [
					'type'       => 'object',
					'properties' => [
						'key'   => [ 'type' => 'string', 'description' => 'Layer label' ],
						'value' => [ 'type' => 'string', 'description' => 'Customer input' ],
					],
				],
			],
		];
	}

	// ── Frontend script ───────────────────────────────────────────────────────

	public function enqueue_blocks_script(): void {
		if ( ( ! is_cart() && ! is_checkout() ) || ! OC_Cart::cart_has_customisation() ) {
			return;
		}

		// Only load when WC Blocks has registered the blocks-checkout package.
		if ( ! wp_script_is( 'wc-blocks-checkout', 'registered' ) ) {
			return;
		}

		$asset_file = OC_PATH . 'assets/build/frontend/blocks-integration.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = include $asset_file;

		wp_enqueue_script(
			'oc-blocks-integration',
			OC_ASSETS_URL . 'frontend/blocks-integration.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_register_style( 'oc-blocks-integration', false, [], OC_VERSION );
		wp_enqueue_style( 'oc-blocks-integration' );
		wp_add_inline_style( 'oc-blocks-integration', $this->get_css() );
	}

	private function get_css(): string {
		return '
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
		.oc-blocks-personalisation-summary {
			list-style: none;
			margin: 6px 0 0;
			padding: 0;
			font-size: 12px;
			line-height: 1.4;
			color: #555;
		}
		.oc-blocks-personalisation-summary li {
			margin: 2px 0;
		}
		.oc-has-personalisation .wc-block-components-product-image,
		.oc-has-personalisation .wc-block-cart-item__image,
		.oc-has-personalisation .wc-block-components-order-summary-item__image {
			display: none !important;
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
		';
	}
}
