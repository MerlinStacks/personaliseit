<?php
/**
 * Integration tests for OC_Cart — verifies cart item data is stored and
 * retrieved correctly when a customised product is added to the cart.
 *
 * Requires: WordPress + WooCommerce test environment.
 *   WP_TESTS_DIR=/path/to/wp-tests vendor/bin/phpunit --testsuite Integration
 *
 * @package OverCustomise
 */

use PHPUnit\Framework\Attributes\Test;

class Test_Cart extends WC_Unit_Test_Case {

	/** @var WC_Product Simple product with a customisation config. */
	private WC_Product $product;

	/** @var int Config ID in oc_product_configs. */
	private int $config_id;

	public function setUp(): void {
		parent::setUp();

		// Create a simple WC product.
		$this->product = WC_Helper_Product::create_simple_product();

		// Insert a customisation config for it.
		global $wpdb;
		$wpdb->insert( $wpdb->prefix . 'oc_product_configs', [
			'product_id'  => $this->product->get_id(),
			'custom_type' => 'text_only',
			'flat_rate'   => 0.00,
			'active'      => 1,
		] );
		$this->config_id = (int) $wpdb->insert_id;

		// Register cart hooks.
		( new OC_Cart() )->register();
	}

	public function tearDown(): void {
		if ( function_exists( 'WC' ) && WC() && WC()->cart ) {
			WC()->cart->empty_cart();
		}
		if ( isset( $this->product ) ) {
			$this->product->delete( true );
		}
		parent::tearDown();
	}

	// ── add_cart_item_data ────────────────────────────────────────────────────

	#[Test]
	public function customisation_data_is_stored_in_cart_item(): void {
		// Simulate the hidden input posted with the add-to-cart form.
		$_POST['_oc_customisation'] = wp_json_encode( [
			'front' => [
				'text'                => 'Hello World',
				'fontId'              => 1,
				'color'               => '#ff0000',
				'artworkAttachmentId' => 0,
			],
		] );

		$cart_item_key = WC()->cart->add_to_cart( $this->product->get_id() );

		$this->assertNotFalse( $cart_item_key, 'Product should be added to cart.' );

		$cart_item = WC()->cart->get_cart_item( $cart_item_key );

		$this->assertArrayHasKey( '_oc_customisation', $cart_item );
		$this->assertIsArray( $cart_item['_oc_customisation'] );
		$this->assertArrayHasKey( 'front', $cart_item['_oc_customisation'] );
		$this->assertSame( 'Hello World', $cart_item['_oc_customisation']['front']['text'] );
	}

	#[Test]
	public function artwork_attachment_id_is_sanitised_to_int(): void {
		$_POST['_oc_customisation'] = wp_json_encode( [
			'front' => [
				'text'                => 'Test',
				'fontId'              => 1,
				'color'               => '#000000',
				'artworkAttachmentId' => '42',  // Sent as string.
			],
		] );

		$key       = WC()->cart->add_to_cart( $this->product->get_id() );
		$cart_item = WC()->cart->get_cart_item( $key );

		$attachment_id = $cart_item['_oc_customisation']['front']['artworkAttachmentId'] ?? null;
		$this->assertSame( 42, $attachment_id );
	}

	#[Test]
	public function malicious_text_input_is_stripped(): void {
		$_POST['_oc_customisation'] = wp_json_encode( [
			'front' => [
				'text'                => '<script>alert(1)</script>',
				'fontId'              => 0,
				'color'               => '#000000',
				'artworkAttachmentId' => 0,
			],
		] );

		$key  = WC()->cart->add_to_cart( $this->product->get_id() );
		$item = WC()->cart->get_cart_item( $key );
		$text = $item['_oc_customisation']['front']['text'] ?? '';

		$this->assertStringNotContainsString( '<script>', $text );
	}

	#[Test]
	public function invalid_hex_color_is_rejected(): void {
		$_POST['_oc_customisation'] = wp_json_encode( [
			'front' => [
				'text'                => 'Test',
				'fontId'              => 0,
				'color'               => 'javascript:alert(1)',  // Invalid.
				'artworkAttachmentId' => 0,
			],
		] );

		$key   = WC()->cart->add_to_cart( $this->product->get_id() );
		$item  = WC()->cart->get_cart_item( $key );
		$color = $item['_oc_customisation']['front']['color'] ?? '#000000';

		// Should be reset to black (default) since the value isn't a valid hex color.
		$this->assertSame( '#000000', $color );
	}

	// ── Order item persistence ────────────────────────────────────────────────

	#[Test]
	public function save_to_order_item_writes_customisation_meta(): void {
		$preview_url = 'http://example.org/wp-content/uploads/overcustomise/previews/preview-aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.png';
		$customisation = [
			'front' => [
				'text'                => 'Engraved',
				'fontId'              => 0,
				'color'               => '#000000',
				'artworkAttachmentId' => 0,
			],
		];

		// Build a cart item values array as WC would pass to the action.
		$values = [
			'_oc_customisation' => $customisation,
			'_oc_config_id'     => 1,
			'_oc_flat_rate'     => 0.0,
			'_oc_preview_url'   => $preview_url,
		];

		// Create an order and item to write to.
		$order = WC_Helper_Order::create_order();
		$items = $order->get_items();
		/** @var WC_Order_Item_Product $item */
		$item  = reset( $items );

		// Call the method directly.
		( new OC_Cart() )->save_to_order_item( $item, 'dummy-cart-key', $values, $order );
		$item->save();
		$order->save();

		// Re-fetch the item and check meta.
		$saved_item = $order->get_items();
		$saved_item = reset( $saved_item );
		$meta       = $saved_item->get_meta( '_oc_customisation', true );

		$this->assertIsArray( $meta );
		$this->assertArrayHasKey( 'front', $meta );
		$this->assertSame( 'Engraved', $meta['front']['text'] );
		$this->assertSame( $preview_url, $saved_item->get_meta( '_oc_preview_url', true ) );
		$this->assertSame( '', $saved_item->get_meta( 'Personalisation Details', true ) );
		$this->assertSame( '', $saved_item->get_meta( 'Preview Image', true ) );
	}

	#[Test]
	public function internal_order_item_meta_is_registered_as_hidden(): void {
		$hidden = ( new OC_Cart() )->hidden_order_item_meta( [] );

		$this->assertContains( '_oc_customisation', $hidden );
		$this->assertContains( '_oc_preview_url', $hidden );
		$this->assertContains( '_oc_flat_rate', $hidden );
		$this->assertContains( 'Preview Image', $hidden );
		$this->assertContains( 'Personalisation Details', $hidden );
	}

	#[Test]
	public function v2_customisation_only_accepts_layers_from_assigned_design(): void {
		global $wpdb;

		$wpdb->insert( $wpdb->prefix . 'oc_designs', [
			'name'        => 'Mug Design',
			'custom_type' => 'text_only',
			'flat_rate'   => 5.00,
			'active'      => 1,
		] );
		$design_id = (int) $wpdb->insert_id;

		$wpdb->insert( $wpdb->prefix . 'oc_design_layers', [
			'design_id'  => $design_id,
			'area_id'    => 0,
			'type'       => 'clipart',
			'label'      => 'Artwork',
			'sort_order' => 0,
		] );
		$layer_id = (int) $wpdb->insert_id;

		$_POST['_oc_customisation'] = wp_json_encode( [
			'v'        => 2,
			'designId' => $design_id,
			'layers'   => [
				$layer_id => [
					'type'       => 'text', // Should be replaced with DB-backed type.
					'value'      => 'Hello',
					'clipartId'  => 321,
					'clipartUrl' => 'https://example.com/clipart.svg',
				],
				999999 => [
					'type'  => 'text',
					'value' => 'Injected',
				],
			],
		] );

		$key  = WC()->cart->add_to_cart( $this->product->get_id() );
		$item = WC()->cart->get_cart_item( $key );

		$layers = $item['_oc_customisation']['layers'] ?? [];
		$this->assertArrayHasKey( $layer_id, $layers );
		$this->assertArrayNotHasKey( 999999, $layers );
		$this->assertSame( 'clipart', $layers[ $layer_id ]['type'] );
		$this->assertSame( $design_id, $item['_oc_customisation']['renderSpec']['designId'] ?? 0 );
		$this->assertArrayHasKey( 'areas', $item['_oc_customisation']['renderSpec'] ?? [] );
	}

	#[Test]
	public function v2_preview_url_must_be_plugin_generated_preview_path(): void {
		global $wpdb;

		$wpdb->insert( $wpdb->prefix . 'oc_designs', [
			'name'        => 'Tumbler Design',
			'custom_type' => 'text_only',
			'flat_rate'   => 5.00,
			'active'      => 1,
		] );
		$design_id = (int) $wpdb->insert_id;

		$wpdb->insert( $wpdb->prefix . 'oc_design_layers', [
			'design_id'  => $design_id,
			'area_id'    => 0,
			'type'       => 'text',
			'label'      => 'Name',
			'sort_order' => 0,
		] );
		$layer_id = (int) $wpdb->insert_id;

		$uploads      = wp_upload_dir();
		$allowed_url  = rtrim( (string) $uploads['baseurl'], '/' ) . '/overcustomise/previews/preview-aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.png';
		$disallowed   = 'https://attacker.example/fake-preview.png';

		$_POST['_oc_customisation'] = wp_json_encode( [
			'v'          => 2,
			'designId'   => $design_id,
			'previewUrl' => $allowed_url,
			'layers'     => [
				$layer_id => [
					'type'  => 'text',
					'value' => 'Alex',
				],
			],
		] );
		$key_allowed  = WC()->cart->add_to_cart( $this->product->get_id() );
		$item_allowed = WC()->cart->get_cart_item( $key_allowed );
		$this->assertSame( $allowed_url, $item_allowed['_oc_preview_url'] ?? '' );

		WC()->cart->empty_cart();

		$mismatched_scheme_url = set_url_scheme( $allowed_url, 'https' );
		if ( $mismatched_scheme_url === $allowed_url ) {
			$mismatched_scheme_url = set_url_scheme( $allowed_url, 'http' );
		}

		$_POST['_oc_customisation'] = wp_json_encode( [
			'v'          => 2,
			'designId'   => $design_id,
			'previewUrl' => $mismatched_scheme_url,
			'layers'     => [
				$layer_id => [
					'type'  => 'text',
					'value' => 'Alex',
				],
			],
		] );
		$key_normalised  = WC()->cart->add_to_cart( $this->product->get_id() );
		$item_normalised = WC()->cart->get_cart_item( $key_normalised );
		$this->assertSame( $allowed_url, $item_normalised['_oc_preview_url'] ?? '' );

		WC()->cart->empty_cart();

		$_POST['_oc_customisation'] = wp_json_encode( [
			'v'          => 2,
			'designId'   => $design_id,
			'previewUrl' => $disallowed,
			'layers'     => [
				$layer_id => [
					'type'  => 'text',
					'value' => 'Alex',
				],
			],
		] );
		$key_disallowed  = WC()->cart->add_to_cart( $this->product->get_id() );
		$item_disallowed = WC()->cart->get_cart_item( $key_disallowed );
		$this->assertArrayNotHasKey( '_oc_preview_url', $item_disallowed );
	}
}
