<?php
/**
 * Integration tests for OC_Rest_API — verifies route registration and
 * response shapes against the actual WordPress REST server.
 *
 * Requires: WordPress + WooCommerce test environment.
 *   WP_TESTS_DIR=/path/to/wp-tests vendor/bin/phpunit --testsuite Integration
 *
 * @package OverCustomise
 */

use PHPUnit\Framework\Attributes\Test;

class Test_REST_API extends WP_Test_REST_TestCase {

	/** @var int Admin user ID. */
	private int $admin_id;

	public function setUp(): void {
		parent::setUp();

		$this->admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );

		// Register plugin REST routes.
		( new OC_Rest_API() )->register_routes();
	}

	// ── Route registration ────────────────────────────────────────────────────

	#[Test]
	public function product_config_route_is_registered(): void {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/overcustomise/v1/product-config/(?P<product_id>\d+)', $routes );
	}

	#[Test]
	public function fonts_route_is_registered(): void {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/overcustomise/v1/fonts', $routes );
	}

	#[Test]
	public function upload_artwork_route_is_registered(): void {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/overcustomise/v1/upload-artwork', $routes );
	}

	#[Test]
	public function regenerate_files_route_is_registered(): void {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/overcustomise/v1/regenerate-files', $routes );
	}

	// ── /product-config/{id} ──────────────────────────────────────────────────

	#[Test]
	public function product_config_returns_404_for_unconfigured_product(): void {
		$request  = new WP_REST_Request( 'GET', '/overcustomise/v1/product-config/999999' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 404, $response->get_status() );
	}

	#[Test]
	public function product_config_returns_200_with_expected_shape(): void {
		global $wpdb;

		// Create a product and a config for it.
		$product_id = self::factory()->post->create( [ 'post_type' => 'product' ] );

		$wpdb->insert( $wpdb->prefix . 'oc_product_configs', [
			'product_id'  => $product_id,
			'custom_type' => 'text_only',
			'flat_rate'   => 3.50,
			'active'      => 1,
		] );
		$config_id = (int) $wpdb->insert_id;

		$wpdb->insert( $wpdb->prefix . 'oc_print_areas', [
			'config_id'    => $config_id,
			'area_key'     => 'front',
			'label'        => 'Front',
			'print_method' => 'uv',
			'sort_order'   => 0,
		] );

		$request  = new WP_REST_Request( 'GET', "/overcustomise/v1/product-config/{$product_id}" );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertArrayHasKey( 'config_id',   $data );
		$this->assertArrayHasKey( 'print_areas',  $data );
		$this->assertArrayHasKey( 'fonts',        $data );
		$this->assertArrayHasKey( 'custom_type',  $data );
		$this->assertSame( 'text_only', $data['custom_type'] );
		$this->assertEquals( 3.50, (float) $data['flat_rate'] );
		$this->assertNotEmpty( $data['print_areas'] );
		$this->assertArrayHasKey( 'area_key', $data['print_areas'][0] );
	}

	// ── /fonts ────────────────────────────────────────────────────────────────

	#[Test]
	public function fonts_endpoint_returns_200_with_array(): void {
		$request  = new WP_REST_Request( 'GET', '/overcustomise/v1/fonts' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertIsArray( $response->get_data() );
	}

	// ── /upload-artwork ───────────────────────────────────────────────────────

	#[Test]
	public function upload_artwork_rejects_missing_nonce(): void {
		$request  = new WP_REST_Request( 'POST', '/overcustomise/v1/upload-artwork' );
		$response = rest_get_server()->dispatch( $request );

		// Without a valid nonce the endpoint returns 403.
		$this->assertSame( 403, $response->get_status() );
	}

	#[Test]
	public function upload_artwork_rejects_when_no_file_sent(): void {
		$token = OC_Rest_API::issue_public_token();

		$request = new WP_REST_Request( 'POST', '/overcustomise/v1/upload-artwork' );
		$request->set_header( 'X-OC-Token', $token );
		// No file params set.

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 400, $response->get_status() );
	}

	#[Test]
	public function upload_artwork_requires_layer_context(): void {
		$token = OC_Rest_API::issue_public_token();
		$request = new WP_REST_Request( 'POST', '/overcustomise/v1/upload-artwork' );
		$request->set_header( 'X-OC-Token', $token );
		$request->set_file_params( [ 'artwork' => [ 'name' => 'x.png', 'tmp_name' => '/tmp/not-uploaded', 'size' => 1, 'error' => UPLOAD_ERR_OK ] ] );

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'invalid_context', $response->get_data()['code'] );
	}

	// ── /regenerate-files ─────────────────────────────────────────────────────

	#[Test]
	public function regenerate_files_requires_manage_woocommerce(): void {
		// Unauthenticated request.
		wp_set_current_user( 0 );

		$request  = new WP_REST_Request( 'POST', '/overcustomise/v1/regenerate-files' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 401, $response->get_status() );
	}

	#[Test]
	public function regenerate_files_returns_400_without_file_id(): void {
		wp_set_current_user( $this->admin_id );

		$request  = new WP_REST_Request( 'POST', '/overcustomise/v1/regenerate-files' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 400, $response->get_status() );
	}

	#[Test]
	public function regenerate_files_returns_404_for_missing_record(): void {
		wp_set_current_user( $this->admin_id );

		$request = new WP_REST_Request( 'POST', '/overcustomise/v1/regenerate-files' );
		$request->set_body_params( [ 'file_id' => 999999 ] );

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 404, $response->get_status() );
	}
}
