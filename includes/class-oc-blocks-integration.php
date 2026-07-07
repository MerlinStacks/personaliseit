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
			'preview_url' => $cart_item['_oc_preview_url'] ?? '',
			'summary'     => $this->build_summary( $cart_item ),
		];
	}

	/** Build an array of {key, value} summary lines for a cart item. */
	private function build_summary( array $cart_item ): array {
		$customisation = $cart_item['_oc_customisation'] ?? null;
		if ( empty( $customisation ) || ! is_array( $customisation ) ) {
			return [];
		}

		// v2 format only — v1 doesn't have layer labels available here.
		if ( ! isset( $customisation['v'] ) || 2 !== (int) $customisation['v'] ) {
			return [];
		}

		$design_id = (int) ( $customisation['designId'] ?? $cart_item['_oc_design_id'] ?? 0 );
		$layers    = $customisation['layers'] ?? [];

		$layer_map = [];
		if ( $design_id ) {
			foreach ( OC_DB::get_design_layers( $design_id ) as $l ) {
				$layer_map[ (int) $l->id ] = $l;
			}
		}

		$lines = [];
		foreach ( $layers as $layer_id => $layer_data ) {
			if ( ! is_array( $layer_data ) ) continue;

			$layer = $layer_map[ (int) $layer_id ] ?? null;
			$label = $layer
				? ( $layer->label ?: ucfirst( $layer->type ) )
				: ucfirst( $layer_data['type'] ?? 'Layer' );

			$value = $this->layer_value( $layer_data );
			if ( ! $value ) continue;

			$lines[] = [ 'key' => $label, 'value' => $value ];
		}

		return $lines;
	}

	private function layer_value( array $layer_data ): string {
		switch ( $layer_data['type'] ?? '' ) {
			case 'text':
			case 'textarea':
			case 'spotify':
				return trim( $layer_data['value'] ?? '' );
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
		if ( ! is_cart() && ! is_checkout() ) {
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
		.oc-has-personalised-preview .wc-block-components-product-image,
		.oc-has-personalised-preview .wc-block-cart-item__image,
		.oc-has-personalised-preview .wc-block-components-order-summary-item__image {
			display: none !important;
		}
		';
	}
}
