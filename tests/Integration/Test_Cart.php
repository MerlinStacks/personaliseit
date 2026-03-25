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
		WC()->cart->empty_cart();
		$this->product->delete( true );
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
	}
}
