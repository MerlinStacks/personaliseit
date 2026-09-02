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

require_once OC_PATH . 'includes/frontend/class-oc-frontend.php';

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
	public function location_lookup_route_is_registered_as_a_secured_write(): void {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/overcustomise/v1/location-lookup', $routes );

		$request = new WP_REST_Request( 'POST', '/overcustomise/v1/location-lookup' );
		$request->set_body_params( [ 'query' => 'London' ] );
		$this->assertSame( 403, rest_get_server()->dispatch( $request )->get_status() );
	}

	#[Test]
	public function location_lookup_reduces_valid_upstream_json_without_caching_it(): void {
		$requests = [];
		$mock     = static function ( $preempt, array $args, string $url ) use ( &$requests ): array|false {
			if ( ! str_starts_with( $url, 'https://nominatim.openstreetmap.org/search?' ) ) {
				return false;
			}
			$requests[] = [ $args, $url ];
			return [
				'response' => [ 'code' => 200 ],
				'body'     => wp_json_encode(
					[
						[
							'lat'          => '51.5074',
							'lon'          => '-0.1278',
							'display_name' => 'London, Greater London, England',
						],
					]
				),
			];
		};
		add_filter( 'pre_http_request', $mock, 10, 3 );
		try {
			$result = OC_Rest_API::find_location( 'London', false );
			$this->assertSame( 51.5074, $result['latitude'] );
			$this->assertSame( -0.1278, $result['longitude'] );
			$this->assertSame( 'London, Greater London, England', $result['displayName'] );
			$this->assertSame( 5, $requests[0][0]['timeout'] );
			$this->assertSame( 0, $requests[0][0]['redirection'] );
			$this->assertSame( 65536, $requests[0][0]['limit_response_size'] );
			$this->assertStringContainsString( 'q=London', $requests[0][1] );
		} finally {
			remove_filter( 'pre_http_request', $mock, 10 );
		}
	}

	#[Test]
	public function authorise_artwork_context_route_is_registered(): void {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/overcustomise/v1/authorise-artwork-context', $routes );
	}

	#[Test]
	public function ai_image_generation_route_is_registered_and_secured(): void {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/overcustomise/v1/generate-ai-image', $routes );

		$response = rest_get_server()->dispatch( new WP_REST_Request( 'POST', '/overcustomise/v1/generate-ai-image' ) );
		$this->assertSame( 403, $response->get_status() );
	}

	#[Test]
	public function product_design_route_accepts_a_distinct_design_id(): void {
		$routes = rest_get_server()->get_routes();
		$route  = $routes['/overcustomise/v1/product-design/(?P<product_id>\d+)'][0] ?? [];

		$this->assertArrayHasKey( 'design_id', $route['args'] ?? [] );
		$this->assertArrayHasKey( 'variant_id', $route['args'] ?? [] );
	}

	#[Test]
	public function product_design_rejects_an_unassigned_requested_design(): void {
		$product = new WC_Product_Simple();
		$product->set_name( 'Public customisable product' );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'visible' );
		$product->set_regular_price( '10' );
		$product_id = $product->save();

		$request = new WP_REST_Request( 'GET', "/overcustomise/v1/product-design/{$product_id}" );
		$request->set_query_params(
			[
				'variant_id' => 0,
				'design_id'  => 999999,
			]
		);
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 404, $response->get_status() );
		$this->assertSame( 'invalid_design', $response->get_data()['code'] );
	}

	#[Test]
	public function product_design_read_allows_public_initial_variation_fallback_without_weakening_write_context(): void {
		global $wpdb;

		$product = new WC_Product_Variable();
		$product->set_name( 'Variable customisable product' );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'visible' );
		$product_id = $product->save();

		$variation = new WC_Product_Variation();
		$variation->set_parent_id( $product_id );
		$variation->set_status( 'publish' );
		$variation->set_regular_price( '10' );
		$variation_id = $variation->save();
		WC_Product_Variable::sync( $product_id );

		$wpdb->insert(
			$wpdb->prefix . 'oc_designs',
			[
				'name'   => 'Fallback Design',
				'active' => 1,
			]
		);
		$design_id = (int) $wpdb->insert_id;
		$wpdb->insert(
			$wpdb->prefix . 'oc_product_assignments',
			[
				'product_id' => $product_id,
				'variant_id' => $variation_id,
				'design_id'  => $design_id,
			]
		);
		OC_Cache::flush_group();

		$read_method  = new ReflectionMethod( OC_Rest_API::class, 'public_read_assignment_context' );
		$read_context = $read_method->invoke( null, $product_id, 0 );
		$this->assertNotWPError( $read_context );
		$this->assertSame( $variation_id, (int) $read_context['assignment']->variant_id );

		$write_method  = new ReflectionMethod( OC_Rest_API::class, 'active_assignment_design' );
		$write_context = $write_method->invoke( null, $product_id, 0, $design_id );
		$this->assertWPError( $write_context );
		$this->assertSame( 'invalid_variation', $write_context->get_error_code() );
	}

	#[Test]
	public function frontend_design_context_excludes_hidden_areas_and_layers(): void {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'oc_designs',
			[
				'name'   => 'Hidden Auxiliary Rows',
				'active' => 1,
			]
		);
		$design_id = (int) $wpdb->insert_id;
		$wpdb->insert(
			$wpdb->prefix . 'oc_design_print_areas',
			[
				'design_id' => $design_id,
				'area_key'  => 'front',
				'label'     => 'Front',
				'visible'   => 1,
			]
		);
		$visible_area_id = (int) $wpdb->insert_id;
		$wpdb->insert(
			$wpdb->prefix . 'oc_design_print_areas',
			[
				'design_id' => $design_id,
				'area_key'  => 'internal',
				'label'     => 'Internal',
				'visible'   => 0,
			]
		);
		$hidden_area_id = (int) $wpdb->insert_id;

		$wpdb->insert(
			$wpdb->prefix . 'oc_design_layers',
			[
				'design_id' => $design_id,
				'area_id'   => $visible_area_id,
				'type'      => 'text',
				'label'     => 'Visible',
				'visible'   => 1,
			]
		);
		$visible_layer_id = (int) $wpdb->insert_id;
		$wpdb->insert(
			$wpdb->prefix . 'oc_design_layers',
			[
				'design_id' => $design_id,
				'area_id'   => $visible_area_id,
				'type'      => 'text',
				'label'     => 'Hidden layer',
				'visible'   => 0,
			]
		);
		$wpdb->insert(
			$wpdb->prefix . 'oc_design_layers',
			[
				'design_id' => $design_id,
				'area_id'   => $hidden_area_id,
				'type'      => 'text',
				'label'     => 'Hidden area layer',
				'visible'   => 1,
			]
		);
		OC_Cache::flush_group();

		$method  = new ReflectionMethod( OC_Frontend::class, 'get_usable_design_context' );
		$context = $method->invoke( new OC_Frontend(), $design_id );

		$this->assertSame( [ $visible_area_id ], array_map( static fn ( $area ): int => (int) $area->id, $context['areas'] ) );
		$this->assertSame( [ $visible_layer_id ], array_map( static fn ( $layer ): int => (int) $layer->id, $context['layers'] ) );
	}

	#[Test]
	public function cart_item_update_route_is_not_registered(): void {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayNotHasKey( '/overcustomise/v1/update-cart-item', $routes );
	}

	#[Test]
	public function regenerate_files_route_is_registered(): void {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/overcustomise/v1/regenerate-files', $routes );
	}

	#[Test]
	public function spotify_availability_validation_is_reused_from_server_cache(): void {
		$uri       = 'spotify:track:6rqhFgbbKwnb9MLmUQDhG6';
		$cache_key = 'oc_spotify_validation_' . hash( 'sha256', $uri );
		delete_transient( $cache_key );
		$requests = 0;
		$mock = static function ( $preempt, array $args, string $url ) use ( &$requests ): array|false {
			if ( ! str_starts_with( $url, 'https://open.spotify.com/oembed?' ) ) {
				return false;
			}
			$requests++;
			return [ 'response' => [ 'code' => 200 ], 'body' => '{}' ];
		};
		add_filter( 'pre_http_request', $mock, 10, 3 );
		try {
			$result = OC_Rest_API::validate_spotify_availability( $uri, false );
			$this->assertTrue( $result['valid'] );
			$this->assertTrue( OC_Rest_API::verify_spotify_validation_proof( $uri, $result['validationProof'], $result['validationExpires'] ) );
			delete_transient( $cache_key );
			$this->assertTrue( OC_Rest_API::verify_spotify_validation_proof( $uri, $result['validationProof'], $result['validationExpires'] ) );
			$this->assertFalse( OC_Rest_API::verify_spotify_validation_proof( $uri . 'x', $result['validationProof'], $result['validationExpires'] ) );
			$this->assertTrue( OC_Rest_API::validate_spotify_availability( $uri, false )['valid'] );
			$this->assertSame( 2, $requests );
		} finally {
			remove_filter( 'pre_http_request', $mock, 10 );
			delete_transient( $cache_key );
		}
	}

	#[Test]
	public function spotify_cache_only_validation_never_makes_an_outbound_request(): void {
		$uri       = 'spotify:track:6rqhFgbbKwnb9MLmUQDhG6';
		$cache_key = 'oc_spotify_validation_' . hash( 'sha256', $uri );
		delete_transient( $cache_key );
		$requests = 0;
		$mock = static function ( $preempt, array $args, string $url ) use ( &$requests ): false {
			$requests++;
			return false;
		};
		add_filter( 'pre_http_request', $mock, 10, 3 );
		try {
			$result = OC_Rest_API::validate_spotify_availability( $uri, false, false );
			$this->assertWPError( $result );
			$this->assertSame( 'validation_required', $result->get_error_code() );
			$this->assertSame( 0, $requests );
		} finally {
			remove_filter( 'pre_http_request', $mock, 10 );
		}
	}

	#[Test]
	public function vdp_file_deletion_accepts_private_and_legacy_roots_only(): void {
		$private = OC_Upload_Handler::private_storage_path( 'vdp' );
		$this->assertIsString( $private );
		$private_file = $private . '/vdp-test-private.csv';
		file_put_contents( $private_file, "name\nAda\n" );

		$uploads = wp_upload_dir();
		$legacy  = trailingslashit( $uploads['basedir'] ) . 'overcustomise/vdp';
		wp_mkdir_p( $legacy );
		$legacy_file = $legacy . '/vdp-test-legacy.csv';
		file_put_contents( $legacy_file, "name\nGrace\n" );
		$outside_file = wp_tempnam( 'vdp-test-outside.csv' );
		file_put_contents( $outside_file, "name\nLinus\n" );

		OC_Rest_API::delete_vdp_file( $private_file );
		OC_Rest_API::delete_vdp_file( $legacy_file );
		OC_Rest_API::delete_vdp_file( $outside_file );

		$this->assertFileDoesNotExist( $private_file );
		$this->assertFileDoesNotExist( $legacy_file );
		$this->assertFileExists( $outside_file );
		wp_delete_file( $outside_file );
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

	#[Test]
	public function artwork_context_authorisation_rejects_missing_authentication(): void {
		$request  = new WP_REST_Request( 'POST', '/overcustomise/v1/authorise-artwork-context' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 403, $response->get_status() );
	}

	#[Test]
	public function artwork_context_authorisation_requires_complete_context(): void {
		$token   = OC_Rest_API::issue_public_token();
		$request = new WP_REST_Request( 'POST', '/overcustomise/v1/authorise-artwork-context' );
		$request->set_header( 'X-OC-Token', $token );
		$request->set_body_params( [ 'source_attachment_id' => 1 ] );

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'invalid_context', $response->get_data()['code'] );
	}

	#[Test]
	public function ip_bound_upload_token_remains_valid_after_wc_session_initialises(): void {
		$previous_ip = $_SERVER['REMOTE_ADDR'] ?? null;
		$ip          = '203.0.113.10';
		$token       = str_repeat( 'a', 64 );
		$key         = 'oc_pubtok_' . hash( 'sha256', $token );
		$_SERVER['REMOTE_ADDR'] = $ip;
		set_transient( $key, [
			'version'      => 2,
			'binding_type' => 'ip',
			'binding_hash' => hash( 'sha256', $ip ),
			'created_at'   => time(),
			'expires_at'   => time() + HOUR_IN_SECONDS,
		], HOUR_IN_SECONDS );

		try {
			$this->assertNotNull( WC()->session );
			$this->assertTrue( OC_Rest_API::validate_public_token( $token ) );
		} finally {
			delete_transient( $key );
			if ( null === $previous_ip ) {
				unset( $_SERVER['REMOTE_ADDR'] );
			} else {
				$_SERVER['REMOTE_ADDR'] = $previous_ip;
			}
		}
	}

	#[Test]
	public function administrators_are_exempt_from_ai_generation_quotas(): void {
		wp_set_current_user( $this->admin_id );
		$method = new ReflectionMethod( OC_Rest_API::class, 'ai_quota_specs' );

		$this->assertSame( [], $method->invoke( null, 'user:' . $this->admin_id ) );
	}

	#[Test]
	public function customer_ai_quotas_use_a_fifteen_minute_rolling_window(): void {
		$previous_ip = $_SERVER['REMOTE_ADDR'] ?? null;
		$_SERVER['REMOTE_ADDR'] = '203.0.113.20';
		wp_set_current_user( 0 );
		$method = new ReflectionMethod( OC_Rest_API::class, 'ai_quota_specs' );
		$before = time();

		try {
			$specs = $method->invoke( null, 'ip:test-customer' );
			$this->assertIsArray( $specs );
			$this->assertCount( 3, $specs );
			foreach ( $specs as $spec ) {
				$this->assertTrue( $spec['sliding_window'] );
				$this->assertGreaterThanOrEqual( $before, $spec['window_start'] );
				$this->assertSame( 15 * MINUTE_IN_SECONDS, $spec['window_end'] - $spec['window_start'] );
			}
		} finally {
			if ( null === $previous_ip ) {
				unset( $_SERVER['REMOTE_ADDR'] );
			} else {
				$_SERVER['REMOTE_ADDR'] = $previous_ip;
			}
		}
	}

	#[Test]
	public function text_to_image_quotas_use_separate_hooks_and_key_namespaces(): void {
		$previous_ip            = $_SERVER['REMOTE_ADDR'] ?? null;
		$_SERVER['REMOTE_ADDR'] = '203.0.113.21';
		wp_set_current_user( 0 );
		$filter_method     = new ReflectionMethod( OC_Rest_API::class, 'ai_quota_specs' );
		$generation_method = new ReflectionMethod( OC_Rest_API::class, 'ai_generation_quota_specs' );
		$limits            = [
			'oc_ai_filter_actor_hourly_limit'     => 7,
			'oc_ai_filter_ip_hourly_limit'        => 8,
			'oc_ai_filter_site_hourly_limit'      => 9,
			'oc_ai_generation_actor_hourly_limit' => 3,
			'oc_ai_generation_ip_hourly_limit'    => 4,
			'oc_ai_generation_site_hourly_limit'  => 5,
		];
		$callbacks         = [];
		foreach ( $limits as $hook => $limit ) {
			$callbacks[ $hook ] = static fn (): int => $limit;
			add_filter( $hook, $callbacks[ $hook ] );
		}

		try {
			$filter_specs     = $filter_method->invoke( null, 'ip:test-customer' );
			$generation_specs = $generation_method->invoke( null, 'ip:test-customer' );
			$this->assertSame( [ 7, 8, 9 ], array_column( $filter_specs, 'count_limit' ) );
			$this->assertSame( [ 3, 4, 5 ], array_column( $generation_specs, 'count_limit' ) );
			$this->assertSame( [ 'ai:actor:', 'ai:ip:', 'ai:site:' ], array_map( static fn ( array $spec ): string => substr( $spec['key'], 0, strrpos( $spec['key'], ':' ) + 1 ), $filter_specs ) );
			$this->assertSame( [ 'ai-generation:actor:', 'ai-generation:ip:', 'ai-generation:site:' ], array_map( static fn ( array $spec ): string => substr( $spec['key'], 0, strrpos( $spec['key'], ':' ) + 1 ), $generation_specs ) );
			$this->assertSame( [], array_intersect( array_column( $filter_specs, 'key' ), array_column( $generation_specs, 'key' ) ) );
		} finally {
			foreach ( $callbacks as $hook => $callback ) {
				remove_filter( $hook, $callback );
			}
			if ( null === $previous_ip ) {
				unset( $_SERVER['REMOTE_ADDR'] );
			} else {
				$_SERVER['REMOTE_ADDR'] = $previous_ip;
			}
		}
	}

	#[Test]
	public function rolling_quota_expires_attempts_independently_at_the_exact_boundary(): void {
		$method = new ReflectionMethod( OC_Rest_API::class, 'normalise_sliding_budget_state' );
		$now    = 2000000000;
		$state  = [
			'version'        => 2,
			'window_type'    => 'sliding',
			'window_seconds' => 900,
			'buckets'        => [
				[ 'timestamp' => $now - 900, 'count' => 1, 'bytes' => 0 ],
				[ 'timestamp' => $now - 899, 'count' => 1, 'bytes' => 0 ],
				[ 'timestamp' => $now - 10, 'count' => 1, 'bytes' => 0 ],
			],
		];

		$result = $method->invoke( null, $state, 900, $now );
		$this->assertSame( [ $now - 899, $now - 10 ], array_column( $result['buckets'], 'timestamp' ) );
	}

	#[Test]
	public function rolling_quota_retry_uses_the_oldest_attempt_that_frees_capacity(): void {
		$method  = new ReflectionMethod( OC_Rest_API::class, 'sliding_budget_retry_after' );
		$now     = 2000000000;
		$buckets = [
			[ 'timestamp' => $now - 800, 'count' => 2, 'bytes' => 0 ],
			[ 'timestamp' => $now - 100, 'count' => 3, 'bytes' => 0 ],
		];
		$spec = [ 'count' => 1, 'bytes' => 0, 'count_limit' => 5, 'byte_limit' => 0 ];

		$this->assertSame( 100, $method->invoke( null, $buckets, $spec, $now, 900 ) );
	}

	#[Test]
	public function legacy_sliding_quota_migrates_without_extending_its_expiry(): void {
		$method = new ReflectionMethod( OC_Rest_API::class, 'normalise_sliding_budget_state' );
		$now    = 2000000000;
		$state  = [
			'version' => 1, 'window_start' => $now - 100, 'window_end' => $now + 800,
			'count' => 4, 'bytes' => 0, 'sliding_window' => true,
		];

		$result = $method->invoke( null, $state, 900, $now );
		$this->assertSame( 2, $result['version'] );
		$this->assertSame( $now - 100, $result['buckets'][0]['timestamp'] );
		$this->assertSame( 4, $result['buckets'][0]['count'] );
	}

	#[Test]
	public function ai_quota_message_reports_rounded_up_retry_minutes(): void {
		$method = new ReflectionMethod( OC_Rest_API::class, 'ai_quota_retry_message' );

		$this->assertSame(
			'You have reached the live photo preview limit. You can make more previews in 1 minute.',
			$method->invoke( null, 1 )
		);
		$this->assertSame(
			'You have reached the live photo preview limit. You can make more previews in 2 minutes.',
			$method->invoke( null, 61 )
		);
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
