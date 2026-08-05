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

	/** @var int[] */
	private array $attachment_ids = [];

	/** @var array<string,true> */
	private array $preview_ids = [];

	public function setUp(): void {
		parent::setUp();
		unset( $_POST['_oc_customisation'] );
		wc_clear_notices();

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
		$wpdb->insert( $wpdb->prefix . 'oc_print_areas', [
			'config_id'    => $this->config_id,
			'area_key'     => 'front',
			'label'        => 'Front',
			'print_method' => 'uv',
			'sort_order'   => 0,
		] );

		// Register cart hooks.
		( new OC_Cart() )->register();
		OC_Upload_Handler::register();
	}

	public function tearDown(): void {
		unset( $_POST['_oc_customisation'] );
		if ( function_exists( 'WC' ) && WC() && WC()->cart ) {
			WC()->cart->empty_cart();
		}
		if ( isset( $this->product ) ) {
			$this->product->delete( true );
		}
		foreach ( $this->attachment_ids as $attachment_id ) {
			wp_delete_attachment( $attachment_id, true );
		}
		$preview_directory = OC_Upload_Handler::private_storage_path( 'previews' );
		foreach ( array_keys( $this->preview_ids ) as $preview_id ) {
			$record = json_decode( (string) get_option( 'oc_private_preview_' . $preview_id, '' ), true );
			if ( is_string( $preview_directory ) && is_array( $record ) && is_string( $record['file'] ?? null )
				&& preg_match( '/^preview-[a-f0-9]{40}\.(?:png|jpg)$/D', $record['file'] )
			) {
				wp_delete_file( $preview_directory . '/' . $record['file'] );
			}
			delete_option( 'oc_private_preview_' . $preview_id );
		}
		parent::tearDown();
	}

	private function create_artwork_attachment( array $context, string $token = '' ): int {
		$bytes     = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true );
		$directory = OC_Upload_Handler::private_storage_path( 'artwork' );
		$this->assertIsString( $directory );
		$file = $directory . '/artwork-' . strtolower( wp_generate_password( 40, false, false ) ) . '.png';
		$this->assertSame( strlen( $bytes ), file_put_contents( $file, $bytes, LOCK_EX ) );

		$attachment_id = wp_insert_attachment( [
			'post_mime_type' => 'image/png',
			'post_title'     => 'Cart artwork',
			'post_status'    => 'private',
		], $file, 0, true );
		$this->assertNotWPError( $attachment_id );
		$this->assertGreaterThan( 0, $attachment_id );
		$this->attachment_ids[] = $attachment_id;
		update_post_meta( $attachment_id, '_oc_artwork', 1 );
		update_post_meta( $attachment_id, '_oc_artwork_type', 'png' );
		update_post_meta( $attachment_id, '_oc_private_storage_version', 2 );
		update_post_meta( $attachment_id, '_oc_artwork_context', $context );
		update_post_meta( $attachment_id, '_oc_artwork_user_id', 0 );
		update_post_meta( $attachment_id, '_oc_artwork_token', '' !== $token ? hash( 'sha256', $token ) : '' );
		update_post_meta( $attachment_id, '_oc_artwork_owner_secret', wp_generate_password( 64, false, false ) );
		return $attachment_id;
	}

	/** Return a valid preview data URI whose payload is large enough for production validation. */
	private function create_preview_image(): string {
		$png = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true );
		$this->assertIsString( $png );
		$text_data = 'Test-ID' . "\0" . wp_generate_uuid4();
		$chunk     = 'tEXt' . $text_data;
		$png       = substr( $png, 0, -12 )
			. pack( 'N', strlen( $text_data ) )
			. $chunk
			. hex2bin( hash( 'crc32b', $chunk ) )
			. substr( $png, -12 );
		return 'data:image/png;base64,' . base64_encode( $png );
	}

	/** Track one private preview and return its persisted metadata for assertions. */
	private function remember_private_preview( string $url ): array {
		wp_parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );
		$preview_id = is_string( $query['preview_id'] ?? null ) ? $query['preview_id'] : '';
		$this->assertSame( 'oc_serve_preview', $query['action'] ?? '' );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{40}$/D', $preview_id );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/D', (string) ( $query['signature'] ?? '' ) );
		$record = json_decode( (string) get_option( 'oc_private_preview_' . $preview_id, '' ), true );
		$this->assertIsArray( $record );
		$this->preview_ids[ $preview_id ] = true;
		return $record;
	}

	/** Store and track one valid private preview. */
	private function store_private_preview( string $image ): string {
		$token = OC_Rest_API::issue_public_token();
		$this->assertNotSame( '', $token );
		$url = OC_Rest_API::store_cart_preview( $image, $token );
		$this->assertIsString( $url );
		$this->remember_private_preview( $url );
		return $url;
	}

	/** Return a signed-controller-shaped URL for display-only tests. */
	private function example_private_preview_url(): string {
		return add_query_arg(
			[
				'action'     => 'oc_serve_preview',
				'preview_id' => str_repeat( 'a', 40 ),
				'signature'  => str_repeat( 'b', 64 ),
			],
			admin_url( 'admin-post.php' )
		);
	}

	// ── add_cart_item_data ────────────────────────────────────────────────────

	#[Test]
	public function add_to_cart_validators_accept_string_variation_ids(): void {
		$this->assertFalse( ( new OC_Frontend() )->validate( false, $this->product->get_id(), 1, '' ) );
		$this->assertFalse( ( new OC_Cart() )->validate_legacy_artwork( false, $this->product->get_id(), 1, '' ) );
	}

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
	public function cart_customisation_detection_ignores_plain_products(): void {
		$plain_product = WC_Helper_Product::create_simple_product();
		try {
			$this->assertFalse( OC_Cart::cart_has_customisation() );

			$key = WC()->cart->add_to_cart( $plain_product->get_id() );
			$this->assertNotFalse( $key );
			$this->assertFalse( OC_Cart::cart_has_customisation() );

			WC()->cart->cart_contents[ $key ]['_oc_customisation'] = [
				'front' => [ 'text' => 'Custom' ],
			];
			$this->assertTrue( OC_Cart::cart_has_customisation() );
		} finally {
			$plain_product->delete( true );
		}
	}

	#[Test]
	public function legacy_artwork_attachment_id_without_context_rejects_add_to_cart_visibly(): void {
		$_POST['_oc_customisation'] = wp_json_encode( [
			'front' => [
				'text'                => 'Test',
				'fontId'              => 1,
				'color'               => '#000000',
				'artworkAttachmentId' => '42',  // Sent as string.
			],
		] );

		$key = WC()->cart->add_to_cart( $this->product->get_id() );

		$this->assertFalse( $key );
		$this->assertNotEmpty( wc_get_notices( 'error' ) );
	}

	#[Test]
	public function legacy_owned_artwork_attachment_is_preserved(): void {
		$token         = OC_Rest_API::issue_public_token();
		$this->assertNotSame( '', $token );
		$attachment_id = $this->create_artwork_attachment( [ $this->product->get_id(), 0, 0, 0 ], $token );
		$_POST['_oc_customisation'] = wp_json_encode( [
			'uploadToken' => $token,
			'front'       => [
				'text'                => 'Test',
				'fontId'              => 1,
				'color'               => '#000000',
				'artworkAttachmentId' => $attachment_id,
			],
		] );

		$key = WC()->cart->add_to_cart( $this->product->get_id() );
		$this->assertNotFalse( $key );
		$cart_item = WC()->cart->get_cart_item( $key );
		$this->assertSame( $attachment_id, $cart_item['_oc_customisation']['front']['artworkAttachmentId'] );
	}

	#[Test]
	public function deleting_private_artwork_removes_its_file(): void {
		$attachment_id = $this->create_artwork_attachment( [ $this->product->get_id(), 0, 0, 0 ] );
		$path          = get_attached_file( $attachment_id );
		$this->assertIsString( $path );
		$this->assertFileExists( $path );

		$this->assertNotFalse( wp_delete_attachment( $attachment_id, true ) );
		$this->assertFileDoesNotExist( $path );
		$this->attachment_ids = array_values( array_diff( $this->attachment_ids, [ $attachment_id ] ) );
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

		$key = WC()->cart->add_to_cart( $this->product->get_id() );
		$this->assertNotFalse( $key );
		$item = WC()->cart->get_cart_item( $key );
		$text = $item['_oc_customisation']['front']['text'] ?? '';

		$this->assertStringNotContainsString( '<script>', $text );
	}

	#[Test]
	public function customisation_item_data_does_not_contain_an_edit_link(): void {
		$_POST['_oc_customisation'] = wp_json_encode( [
			'front' => [
				'text'                => 'Edit me',
				'fontId'              => 0,
				'color'               => '#000000',
				'artworkAttachmentId' => 0,
			],
		] );

		$key = WC()->cart->add_to_cart( $this->product->get_id() );
		$this->assertNotFalse( $key );
		$cart_item = WC()->cart->get_cart_item( $key );
		$item_data = ( new OC_Cart() )->display_item_data( [], $cart_item );
		$values    = array_column( $item_data, 'value' );

		$this->assertEmpty(
			array_filter( $values, fn( $value ) => is_string( $value ) && str_contains( $value, 'oc_edit_cart_key=' . $key ) )
		);
	}

	#[Test]
	public function personalisation_fees_with_different_tax_classes_use_distinct_ids(): void {
		$first_product = WC_Helper_Product::create_simple_product();
		$second_product = WC_Helper_Product::create_simple_product();
		$first_product->set_tax_status( 'taxable' );
		$first_product->set_tax_class( '' );
		$first_product->save();
		$second_product->set_tax_status( 'taxable' );
		$second_product->set_tax_class( 'reduced-rate' );
		$second_product->save();

		try {
			$first_key  = WC()->cart->add_to_cart( $first_product->get_id() );
			$second_key = WC()->cart->add_to_cart( $second_product->get_id() );
			$this->assertNotFalse( $first_key );
			$this->assertNotFalse( $second_key );
			WC()->cart->cart_contents[ $first_key ]['_oc_customisation'] = [ 'front' => [ 'text' => 'One' ] ];
			WC()->cart->cart_contents[ $first_key ]['_oc_flat_rate'] = 2.0;
			WC()->cart->cart_contents[ $second_key ]['_oc_customisation'] = [ 'front' => [ 'text' => 'Two' ] ];
			WC()->cart->cart_contents[ $second_key ]['_oc_flat_rate'] = 3.0;
			WC()->cart->fees_api()->remove_all_fees();

			( new OC_Cart() )->add_flat_rate_fee( WC()->cart );
			$fees = WC()->cart->get_fees();

			$this->assertCount( 2, $fees );
			$this->assertCount( 2, array_unique( array_map( fn( $fee ) => $fee->name, $fees ) ) );
		} finally {
			$first_product->delete( true );
			$second_product->delete( true );
		}
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

		$key = WC()->cart->add_to_cart( $this->product->get_id() );
		$this->assertNotFalse( $key );
		$item  = WC()->cart->get_cart_item( $key );
		$color = $item['_oc_customisation']['front']['color'] ?? '#000000';

		// Should be reset to black (default) since the value isn't a valid hex color.
		$this->assertSame( '#000000', $color );
	}

	// ── Order item persistence ────────────────────────────────────────────────

	#[Test]
	public function save_to_order_item_writes_customisation_meta(): void {
		$preview_url = $this->example_private_preview_url();
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
	public function checkout_name_preview_is_not_added_when_thumbnail_column_already_rendered(): void {
		$cart        = new OC_Cart();
		$preview_url = $this->example_private_preview_url();
		$cart_item   = [ '_oc_preview_url' => $preview_url ];

		add_filter( 'woocommerce_is_checkout', '__return_true' );
		add_filter( 'woocommerce_is_cart', '__return_false' );

		try {
			$thumbnail = $cart->cart_item_thumbnail( '<img src="standard.jpg" />', $cart_item, 'cart-item-key' );
			$this->assertStringContainsString( $preview_url, $thumbnail );

			$product_name = $cart->checkout_item_name_preview( 'Pen - Various Colours - Purple', $cart_item, 'cart-item-key' );
			$this->assertSame( 'Pen - Various Colours - Purple', $product_name );
		} finally {
			remove_filter( 'woocommerce_is_checkout', '__return_true' );
			remove_filter( 'woocommerce_is_cart', '__return_false' );
		}
	}

	#[Test]
	public function checkout_name_preview_is_added_when_no_thumbnail_column_rendered(): void {
		$cart        = new OC_Cart();
		$preview_url = $this->example_private_preview_url();
		$cart_item   = [ '_oc_preview_url' => $preview_url ];

		add_filter( 'woocommerce_is_checkout', '__return_true' );
		add_filter( 'woocommerce_is_cart', '__return_false' );

		try {
			$product_name = $cart->checkout_item_name_preview( 'Pen - Various Colours - Purple', $cart_item, 'cart-item-key' );
			$this->assertStringContainsString( 'oc-checkout-preview-thumb', $product_name );
			$this->assertStringContainsString( $preview_url, $product_name );
		} finally {
			remove_filter( 'woocommerce_is_checkout', '__return_true' );
			remove_filter( 'woocommerce_is_cart', '__return_false' );
		}
	}

	#[Test]
	public function store_api_preview_image_uses_the_expected_object_shape(): void {
		$preview_url = $this->example_private_preview_url();
		$images      = ( new OC_Cart() )->store_api_cart_item_images(
			[],
			[ '_oc_preview_url' => $preview_url ],
			'cart-item-key'
		);

		$this->assertCount( 1, $images );
		$this->assertIsObject( $images[0] );
		$this->assertSame( $preview_url, $images[0]->src );
		$this->assertSame( $preview_url, $images[0]->thumbnail );
	}

	#[Test]
	public function linked_images_keep_the_attachment_ownership_layer(): void {
		$layers = [
			(object) [ 'id' => 11, 'type' => 'image', 'settings' => [ 'link_group' => 'photo' ], 'visible' => 1, 'locked' => 0 ],
			(object) [ 'id' => 22, 'type' => 'image', 'settings' => [ 'link_group' => 'photo' ], 'visible' => 1, 'locked' => 0 ],
		];
		$input = [
			'attachmentId'         => 123,
			'artworkContextLayerId' => 22,
		];
		$method = new ReflectionMethod( OC_Cart::class, 'synchronise_linked_layer_inputs' );
		$result = $method->invoke( null, $layers, [ 11 => $input, 22 => $input ] );

		$this->assertSame( 22, $result[11]['_oc_link_source_layer_id'] );
		$this->assertSame( 22, $result[22]['_oc_link_source_layer_id'] );

		$input['artworkContextLayerId'] = 999;
		$result = $method->invoke( null, $layers, [ 11 => $input, 22 => $input ] );
		$this->assertSame( 11, $result[11]['_oc_link_source_layer_id'] );

		$attachment_id = $this->create_artwork_attachment( [ $this->product->get_id(), 0, 101, 22 ] );
		$legacy_input  = [ 'attachmentId' => $attachment_id ];
		$result = $method->invoke( null, $layers, [ 11 => $legacy_input, 22 => $legacy_input ] );
		$this->assertSame( 22, $result[11]['_oc_link_source_layer_id'] );
	}

	#[Test]
	public function artwork_from_a_stale_variation_context_is_authorised_for_submission(): void {
		$user_id       = self::factory()->user->create();
		$product_id    = $this->product->get_id();
		$design_id     = 101;
		$layer_id      = 202;
		$attachment_id = $this->create_artwork_attachment( [ $product_id, 0, $design_id, $layer_id ] );
		update_post_meta( $attachment_id, '_oc_artwork_user_id', $user_id );
		wp_set_current_user( $user_id );

		try {
			$method = new ReflectionMethod( OC_Cart::class, 'attachment_is_accepted_for_submission' );
			$this->assertTrue( $method->invoke( null, $attachment_id, $product_id, 303, $design_id, $layer_id, '' ) );
			$this->assertTrue( OC_Upload_Handler::attachment_context_is_authorised(
				$attachment_id,
				[ $product_id, 303, $design_id, $layer_id ]
			) );
		} finally {
			wp_set_current_user( 0 );
		}
	}

	#[Test]
	public function v2_customisation_rejects_unknown_layers(): void {
		global $wpdb;

		$wpdb->insert( $wpdb->prefix . 'oc_designs', [
			'name'        => 'Mug Design',
			'custom_type' => 'text_only',
			'flat_rate'   => 5.00,
			'active'      => 1,
		] );
		$design_id = (int) $wpdb->insert_id;
		$wpdb->insert( $wpdb->prefix . 'oc_design_print_areas', [
			'design_id' => $design_id,
			'area_key'  => 'front',
			'label'     => 'Front',
		] );
		$area_id = (int) $wpdb->insert_id;

		$wpdb->insert( $wpdb->prefix . 'oc_design_layers', [
			'design_id'  => $design_id,
			'area_id'    => $area_id,
			'type'       => 'text',
			'label'      => 'Name',
			'sort_order' => 0,
		] );
		$layer_id = (int) $wpdb->insert_id;
		$wpdb->insert( $wpdb->prefix . 'oc_product_assignments', [
			'product_id' => $this->product->get_id(),
			'variant_id' => 0,
			'design_id'  => $design_id,
		] );
		OC_Cache::flush_group();

		$_POST['_oc_customisation'] = wp_json_encode( [
			'v'        => 2,
			'designId' => $design_id,
			'layers'   => [
				$layer_id => [
					'type'       => 'text',
					'value'      => 'Hello',
				],
				999999 => [
					'type'  => 'text',
					'value' => 'Injected',
				],
			],
		] );

		$key = WC()->cart->add_to_cart( $this->product->get_id() );

		$this->assertFalse( $key );
		$this->assertNotEmpty( wc_get_notices( 'error' ) );
	}

	#[Test]
	public function v2_spotify_layer_requires_server_confirmed_availability(): void {
		global $wpdb;
		$wpdb->insert( $wpdb->prefix . 'oc_designs', [
			'name'        => 'Spotify Design',
			'custom_type' => 'text_only',
			'active'      => 1,
		] );
		$design_id = (int) $wpdb->insert_id;
		$wpdb->insert( $wpdb->prefix . 'oc_design_print_areas', [
			'design_id' => $design_id,
			'area_key'  => 'front',
			'label'     => 'Front',
		] );
		$area_id = (int) $wpdb->insert_id;
		$wpdb->insert( $wpdb->prefix . 'oc_design_layers', [
			'design_id' => $design_id,
			'area_id'   => $area_id,
			'type'      => 'spotify',
			'label'     => 'Song',
			'visible'   => 1,
		] );
		$layer_id = (int) $wpdb->insert_id;
		$wpdb->insert( $wpdb->prefix . 'oc_product_assignments', [
			'product_id' => $this->product->get_id(),
			'variant_id' => 0,
			'design_id'  => $design_id,
		] );
		OC_Cache::flush_group();

		$uri       = 'spotify:track:6rqhFgbbKwnb9MLmUQDhG6';
		$cache_key = 'oc_spotify_validation_' . hash( 'sha256', $uri );
		set_transient( $cache_key, [ 'valid' => false, 'reason' => 'invalid_or_unavailable' ], HOUR_IN_SECONDS );
		try {
			$result = OC_Cart::normalise_v2_layers( $this->product->get_id(), 0, $design_id, [
				$layer_id => [
					'type'          => 'spotify',
					'value'         => $uri,
					'spotifyStatus' => 'ok',
				],
			] );
			$this->assertWPError( $result );
			$this->assertSame( 'invalid_spotify', $result->get_error_code() );
		} finally {
			delete_transient( $cache_key );
		}
	}

	#[Test]
	public function v2_preview_url_must_be_a_valid_signed_private_url(): void {
		global $wpdb;

		$wpdb->insert( $wpdb->prefix . 'oc_designs', [
			'name'        => 'Tumbler Design',
			'custom_type' => 'text_only',
			'flat_rate'   => 5.00,
			'active'      => 1,
		] );
		$design_id = (int) $wpdb->insert_id;
		$wpdb->insert( $wpdb->prefix . 'oc_design_print_areas', [
			'design_id' => $design_id,
			'area_key'  => 'front',
			'label'     => 'Front',
		] );
		$area_id = (int) $wpdb->insert_id;

		$wpdb->insert( $wpdb->prefix . 'oc_design_layers', [
			'design_id'  => $design_id,
			'area_id'    => $area_id,
			'type'       => 'text',
			'label'      => 'Name',
			'sort_order' => 0,
		] );
		$layer_id = (int) $wpdb->insert_id;
		$wpdb->insert( $wpdb->prefix . 'oc_product_assignments', [
			'product_id' => $this->product->get_id(),
			'variant_id' => 0,
			'design_id'  => $design_id,
		] );
		OC_Cache::flush_group();

		$allowed_url = $this->store_private_preview( $this->create_preview_image() );
		$disallowed  = add_query_arg( 'signature', str_repeat( '0', 64 ), $allowed_url );

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
		$this->assertNotFalse( $key_allowed );
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
		$this->assertNotFalse( $key_normalised );
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
		$this->assertNotFalse( $key_disallowed );
		$item_disallowed = WC()->cart->get_cart_item( $key_disallowed );
		$this->assertArrayNotHasKey( '_oc_preview_url', $item_disallowed );
	}

	#[Test]
	public function v2_inline_preview_is_saved_during_add_to_cart(): void {
		global $wpdb;

		$wpdb->insert( $wpdb->prefix . 'oc_designs', [
			'name'        => 'Inline Preview Design',
			'custom_type' => 'text_only',
			'flat_rate'   => 0,
			'active'      => 1,
		] );
		$design_id = (int) $wpdb->insert_id;

		$wpdb->insert( $wpdb->prefix . 'oc_design_print_areas', [
			'design_id' => $design_id,
			'area_key'  => 'front',
			'label'     => 'Front',
		] );
		$area_id = (int) $wpdb->insert_id;

		$wpdb->insert( $wpdb->prefix . 'oc_design_layers', [
			'design_id' => $design_id,
			'area_id'   => $area_id,
			'type'      => 'text',
			'label'     => 'Name',
		] );
		$layer_id = (int) $wpdb->insert_id;

		$wpdb->insert( $wpdb->prefix . 'oc_product_assignments', [
			'product_id' => $this->product->get_id(),
			'variant_id' => 0,
			'design_id'  => $design_id,
		] );
		OC_Cache::flush_group();

		$token = OC_Rest_API::issue_public_token();
		$image = $this->create_preview_image();

		$this->assertWPError( OC_Rest_API::store_cart_preview( $image, 'invalid-token' ) );

		$_POST['_oc_customisation'] = wp_json_encode( [
			'v'            => 2,
			'designId'     => $design_id,
			'uploadToken'  => $token,
			'previewImage' => $image,
			'layers'       => [
				$layer_id => [
					'type'  => 'text',
					'value' => 'Alex',
				],
			],
		] );

		$key = WC()->cart->add_to_cart( $this->product->get_id() );
		$this->assertNotFalse( $key );
		$item = WC()->cart->get_cart_item( $key );
		$url  = (string) ( $item['_oc_preview_url'] ?? '' );
		$record = $this->remember_private_preview( $url );
		$directory = OC_Upload_Handler::private_storage_path( 'previews' );
		$this->assertIsString( $directory );
		$this->assertFileExists( $directory . '/' . $record['file'] );
	}
}
