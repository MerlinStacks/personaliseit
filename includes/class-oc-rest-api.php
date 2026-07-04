<?php
/**
 * REST API — /wp-json/overcustomise/v1/ endpoints.
 *
 * Endpoints:
 *   GET  /product-config/{product_id}   — fetch config + print areas + fonts for the customiser
 *   POST /upload-artwork                — customer artwork upload
 *   GET  /fonts                         — list active fonts (public)
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

class OC_Rest_API {

	private const NAMESPACE = 'overcustomise/v1';
	private const PUBLIC_TOKEN_TTL = 21600; // 6 hours.

	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		// Product config + print areas for the frontend customiser.
		register_rest_route( self::NAMESPACE, '/product-config/(?P<product_id>\d+)', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_product_config' ],
			'permission_callback' => '__return_true', // Public — only returns active configs.
			'args'                => [
				'product_id' => [
					'validate_callback' => fn( $v ) => is_numeric( $v ) && $v > 0,
					'sanitize_callback' => 'absint',
				],
			],
		] );

		// Active fonts list (public — needed by frontend customiser).
		register_rest_route( self::NAMESPACE, '/fonts', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_fonts' ],
			'permission_callback' => '__return_true',
		] );

		// Artwork upload (customer).
		register_rest_route( self::NAMESPACE, '/upload-artwork', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'upload_artwork' ],
			'permission_callback' => [ $this, 'public_write_permission' ],
		] );

		// Design assignment for a specific product / variation (used by frontend JS).
		register_rest_route( self::NAMESPACE, '/product-design/(?P<product_id>\d+)', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_product_design' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'product_id' => [ 'validate_callback' => fn( $v ) => is_numeric( $v ) && $v > 0, 'sanitize_callback' => 'absint' ],
				'variant_id' => [ 'default' => 0, 'sanitize_callback' => 'absint' ],
			],
		] );

		// Save canvas snapshot as a JPEG for cart/order preview.
		register_rest_route( self::NAMESPACE, '/save-preview', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'save_preview' ],
			'permission_callback' => [ $this, 'public_write_permission' ],
		] );

		// Validate Spotify links and detect private/unavailable resources.
		register_rest_route( self::NAMESPACE, '/validate-spotify', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'validate_spotify' ],
			'permission_callback' => [ $this, 'public_write_permission' ],
		] );

		// Get cart item customisation data for editing.
		register_rest_route( self::NAMESPACE, '/cart-item-customisation/(?P<cart_key>[^/]+)', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_cart_item_customisation' ],
			'permission_callback' => '__return_true',
		] );

		// Update cart item customisation from edit mode.
		register_rest_route( self::NAMESPACE, '/update-cart-item', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'update_cart_item' ],
			'permission_callback' => [ $this, 'public_write_permission' ],
		] );

		// Admin: regenerate print files for an order item.
		register_rest_route( self::NAMESPACE, '/regenerate-files', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'regenerate_files' ],
			'permission_callback' => fn() => current_user_can( 'manage_woocommerce' ),
		] );

		// Admin: upload CSV for VDP.
		register_rest_route( self::NAMESPACE, '/vdp-upload-csv', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'upload_vdp_csv' ],
			'permission_callback' => fn() => current_user_can( 'manage_woocommerce' ),
		] );
	}

	/**
	 * Issue a short-lived token for guest write requests.
	 * Logged-in requests should continue using the standard wp_rest nonce.
	 */
	public static function issue_public_token(): string {
		$token = wp_generate_password( 48, false, false );
		$key   = self::public_token_key( $token );
		set_transient( $key, [
			'ip_hash' => hash( 'sha256', self::client_ip() ),
		], self::PUBLIC_TOKEN_TTL );
		return $token;
	}

	/** Shared permission callback for guest/frontend write endpoints. */
	public function public_write_permission( \WP_REST_Request $request ): bool|\WP_Error {
		return $this->verify_public_write_auth( $request );
	}

	/** Validate request auth for public write endpoints. */
	private function verify_public_write_auth( \WP_REST_Request $request ): bool|\WP_Error {
		$nonce = (string) ( $request->get_header( 'X-WP-Nonce' ) ?: $request->get_param( '_wpnonce' ) );

		// Logged-in users authenticate via standard REST nonce.
		if ( is_user_logged_in() ) {
			if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
				return new \WP_Error( 'invalid_nonce', __( 'Security verification failed.', 'overcustomise' ), [ 'status' => 403 ] );
			}
			return true;
		}

		// Guests authenticate with a short-lived OC token, scoped by client IP hash.
		$token = (string) ( $request->get_header( 'X-OC-Token' ) ?: $request->get_param( 'oc_token' ) );
		if ( '' === $token ) {
			return new \WP_Error( 'invalid_token', __( 'Security verification failed.', 'overcustomise' ), [ 'status' => 403 ] );
		}

		$key = self::public_token_key( $token );
		$ctx = get_transient( $key );
		if ( ! is_array( $ctx ) ) {
			return new \WP_Error( 'invalid_token', __( 'Security verification failed.', 'overcustomise' ), [ 'status' => 403 ] );
		}

		$expected_ip_hash = (string) ( $ctx['ip_hash'] ?? '' );
		if ( '' !== $expected_ip_hash && ! hash_equals( $expected_ip_hash, hash( 'sha256', self::client_ip() ) ) ) {
			return new \WP_Error( 'invalid_token', __( 'Security verification failed.', 'overcustomise' ), [ 'status' => 403 ] );
		}

		// Sliding expiry so active sessions don't churn tokens.
		set_transient( $key, $ctx, self::PUBLIC_TOKEN_TTL );
		return true;
	}

	/** Build a safe transient key from a token value. */
	private static function public_token_key( string $token ): string {
		return 'oc_pubtok_' . md5( $token );
	}

	/** Get a normalised client IP for lightweight request binding. */
	private static function client_ip(): string {
		$ip = '';
		
		// Check for proxy headers (only if configured to trust them).
		if ( defined( 'OC_TRUST_PROXY' ) && OC_TRUST_PROXY ) {
			$proxy_headers = [ 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP' ];
			foreach ( $proxy_headers as $header ) {
				if ( ! empty( $_SERVER[ $header ] ) ) {
					// Extract first IP from comma-separated list.
					$ips = explode( ',', (string) $_SERVER[ $header ] );
					$ip  = trim( $ips[0] );
					break;
				}
			}
		}
		
		if ( '' === $ip && isset( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = (string) $_SERVER['REMOTE_ADDR'];
		}
		
		return preg_replace( '/[^0-9a-f:.]/i', '', $ip ?: 'unknown' );
	}

	/**
	 * Apply per-IP transient rate limiting.
	 *
	 * @return \WP_Error|null
	 */
	private function enforce_rate_limit( string $prefix, int $max, string $message ): ?\WP_Error {
		$ip         = self::client_ip();
		$rate_key   = $prefix . hash( 'sha256', $ip );
		$rate_count = (int) get_transient( $rate_key );
		if ( $rate_count >= $max ) {
			return new \WP_Error( 'rate_limited', $message, [ 'status' => 429 ] );
		}
		set_transient( $rate_key, $rate_count + 1, HOUR_IN_SECONDS );
		return null;
	}

	// -------------------------------------------------------------------------
	// Handlers
	// -------------------------------------------------------------------------

	/**
	 * Return the design ID (and active state) for a product / variation.
	 * Used by the frontend JS to detect design changes on variation switch.
	 */
	public function get_product_design( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		global $wpdb;

		$product_id = $request->get_param( 'product_id' );
		$variant_id = (int) $request->get_param( 'variant_id' );

		// Replicate the same priority logic as OC_Frontend::get_assignment().
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return new \WP_Error( 'not_found', 'Product not found.', [ 'status' => 404 ] );
		}

		$parent_id = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product_id;
		$v_id      = $product->is_type( 'variation' ) ? $product_id : $variant_id;

		// 1. Variant-specific.
		$row = null;
		if ( $v_id > 0 ) {
			$row = $wpdb->get_row( $wpdb->prepare(
				"SELECT design_id FROM {$wpdb->prefix}oc_product_assignments
				 WHERE product_id = %d AND variant_id = %d LIMIT 1",
				$parent_id, $v_id
			) );
		}

		// 2. Parent-level.
		if ( ! $row ) {
			$row = $wpdb->get_row( $wpdb->prepare(
				"SELECT design_id FROM {$wpdb->prefix}oc_product_assignments
				 WHERE product_id = %d AND variant_id = 0 LIMIT 1",
				$parent_id
			) );
		}

		if ( ! $row ) {
			return rest_ensure_response( [ 'design_id' => 0, 'active' => false ] );
		}

		$design = OC_DB::get_design( (int) $row->design_id );

		return rest_ensure_response( [
			'design_id' => (int) $row->design_id,
			'active'    => $design ? (bool) $design->active : false,
		] );
	}

	/** Return the active product config, print areas, and font list for the customiser. */
	public function get_product_config( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$product_id = $request->get_param( 'product_id' );
		$config     = OC_DB::get_config_by_product( $product_id );

		if ( ! $config || ! $config->active ) {
			return new \WP_Error( 'not_found', __( 'No active customisation config for this product.', 'overcustomise' ), [ 'status' => 404 ] );
		}

		$areas = OC_DB::get_print_areas( (int) $config->id );

		$areas_out = array_map( function( $area ) {
			$mockup_url = $area->mockup_attachment_id
				? wp_get_attachment_image_url( (int) $area->mockup_attachment_id, 'full' )
				: '';

			return [
				'id'           => (int) $area->id,
				'area_key'     => $area->area_key,
				'label'        => $area->label,
				'print_method' => $area->print_method,
				'mockup_url'   => $mockup_url,
				'canvas'       => [
					'x' => (int) $area->canvas_x,
					'y' => (int) $area->canvas_y,
					'w' => (int) $area->canvas_w,
					'h' => (int) $area->canvas_h,
					'rotation' => isset( $area->canvas_rotation ) ? (int) $area->canvas_rotation : 0,
				],
			];
		}, $areas );

		return rest_ensure_response( [
			'config_id'          => (int) $config->id,
			'product_id'         => (int) $config->product_id,
			'custom_type'        => $config->custom_type,
			'flat_rate'          => (float) $config->flat_rate,
			'print_areas'        => array_values( $areas_out ),
			'fonts'              => OC_Font_Registry::get_fonts_for_js(),
			'allowed_formats'    => OC_Admin_Settings::get( 'allowed_upload_formats' ),
			'max_upload_size_mb' => (int) OC_Admin_Settings::get( 'max_upload_size_mb' ),
		] );
	}

	/** Return all active fonts. */
	public function get_fonts( \WP_REST_Request $request ): \WP_REST_Response {
		return rest_ensure_response( OC_Font_Registry::get_fonts_for_js() );
	}

	/** Handle customer artwork upload. */
	public function upload_artwork( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		// Verify standard WP REST nonce — same one WP itself uses for cookie auth,
		// so it stays consistent across logged-in/guest state and WC session changes.
		$auth = $this->verify_public_write_auth( $request );
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}

		$rate_limit = $this->enforce_rate_limit(
			'oc_artwork_rate_',
			60,
			__( 'Too many uploads. Try again later.', 'overcustomise' )
		);
		if ( is_wp_error( $rate_limit ) ) {
			return $rate_limit;
		}

		$files = $request->get_file_params();

		if ( empty( $files['artwork'] ) ) {
			return new \WP_Error( 'no_file', __( 'No file received.', 'overcustomise' ), [ 'status' => 400 ] );
		}

		// Optional per-layer override: if a layer_id is supplied, use that layer's
		// formats/max_size so the server enforces the same restriction as the UI.
		$layer_overrides = null;
		$layer_id        = absint( $request->get_param( 'layer_id' ) );
		if ( $layer_id ) {
			global $wpdb;
			$row = $wpdb->get_row( $wpdb->prepare(
				"SELECT settings FROM {$wpdb->prefix}oc_design_layers WHERE id = %d LIMIT 1",
				$layer_id
			) );
			if ( $row && $row->settings ) {
				$s = json_decode( $row->settings, true );
				if ( is_array( $s ) ) {
					$formats     = isset( $s['formats'] ) && is_array( $s['formats'] ) ? array_values( array_filter( $s['formats'] ) ) : [];
					$max_size_mb = isset( $s['max_size_mb'] ) ? (int) $s['max_size_mb'] : 0;
					$layer_overrides = [
						'formats'     => ! empty( $formats ) ? array_map( 'strtolower', $formats ) : null,
						'max_size_mb' => $max_size_mb > 0 ? $max_size_mb : null,
					];
				}
			}
		}

		try {
			$result = OC_Upload_Handler::process( $files['artwork'], $layer_overrides );
		} catch ( \RuntimeException $e ) {
			OC_Logger::warning( 'Artwork upload failed: ' . $e->getMessage() );
			return new \WP_Error( 'upload_failed', $e->getMessage(), [ 'status' => 422 ] );
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Save a base64 canvas snapshot as a JPEG for cart/order preview.
	 * Uses a content-hash filename so identical previews are deduplicated.
	 */
	public function save_preview( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$auth = $this->verify_public_write_auth( $request );
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}

		$rate_limit = $this->enforce_rate_limit(
			'oc_preview_rate_',
			30,
			__( 'Too many preview uploads. Try again later.', 'overcustomise' )
		);
		if ( is_wp_error( $rate_limit ) ) {
			return $rate_limit;
		}

		$body = $request->get_json_params();
		$raw  = is_array( $body ) && isset( $body['image'] ) && is_string( $body['image'] ) ? $body['image'] : '';

		// Strip data URI prefix (data:image/jpeg;base64,…).
		if ( '' !== $raw && str_contains( $raw, ',' ) ) {
			$raw = substr( $raw, strpos( $raw, ',' ) + 1 );
		}

		if ( '' === $raw ) {
			return new \WP_Error( 'invalid_image', __( 'Invalid image data.', 'overcustomise' ), [ 'status' => 400 ] );
		}

		// Cap decoded payload to 10 MB to prevent memory exhaustion.
		$max_bytes = 10 * 1024 * 1024;
		if ( strlen( $raw ) > (int) ceil( $max_bytes * 4 / 3 ) ) {
			return new \WP_Error( 'too_large', __( 'Preview image exceeds size limit.', 'overcustomise' ), [ 'status' => 413 ] );
		}

		$decoded = base64_decode( $raw, true );
		if ( false === $decoded || strlen( $decoded ) < 100 ) {
			return new \WP_Error( 'invalid_image', __( 'Invalid image data.', 'overcustomise' ), [ 'status' => 400 ] );
		}

		// Verify the decoded bytes are actually a JPEG or PNG image — not arbitrary binary.
		$image_info = @getimagesizefromstring( $decoded );
		if ( ! is_array( $image_info ) || empty( $image_info['mime'] )
			|| ! in_array( $image_info['mime'], [ 'image/jpeg', 'image/png' ], true )
		) {
			$err = error_get_last();
			OC_Logger::warning( 'Preview image validation failed: ' . ( $err['message'] ?? 'unknown error' ) );
			return new \WP_Error( 'invalid_image', __( 'Invalid image format.', 'overcustomise' ), [ 'status' => 400 ] );
		}

		$upload = wp_upload_dir();
		if ( ! empty( $upload['error'] ) ) {
			return new \WP_Error( 'upload_dir_error', (string) $upload['error'], [ 'status' => 500 ] );
		}
		$dir    = $upload['basedir'] . '/overcustomise/previews';
		if ( ! wp_mkdir_p( $dir ) ) {
			return new \WP_Error( 'mkdir_failed', __( 'Could not create preview directory.', 'overcustomise' ), [ 'status' => 500 ] );
		}

		$ext      = 'image/png' === $image_info['mime'] ? 'png' : 'jpg';
		$filename = 'preview-' . md5( $decoded ) . '.' . $ext;
		$filepath = $dir . '/' . $filename;

		if ( ! file_exists( $filepath ) ) {
			if ( false === file_put_contents( $filepath, $decoded ) ) {
				return new \WP_Error( 'save_failed', __( 'Could not save preview image.', 'overcustomise' ), [ 'status' => 500 ] );
			}
		}

		return rest_ensure_response( [
			'url' => $upload['baseurl'] . '/overcustomise/previews/' . $filename,
		] );
	}

	/** Validate a Spotify URL/URI and confirm it is publicly accessible. */
	public function validate_spotify( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$auth = $this->verify_public_write_auth( $request );
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}

		$rate_limit = $this->enforce_rate_limit(
			'oc_spotify_rate_',
			120,
			__( 'Too many validations. Try again shortly.', 'overcustomise' )
		);
		if ( is_wp_error( $rate_limit ) ) {
			return $rate_limit;
		}

		$url = trim( (string) $request->get_param( 'url' ) );
		if ( '' === $url ) {
			return rest_ensure_response( [
				'valid'   => false,
				'reason'  => 'empty',
				'message' => __( 'Enter a Spotify link.', 'overcustomise' ),
			] );
		}

		$parsed = $this->parse_spotify_input( $url );
		if ( ! $parsed ) {
			return rest_ensure_response( [
				'valid'   => false,
				'reason'  => 'invalid_format',
				'message' => __( 'Invalid Spotify link format.', 'overcustomise' ),
			] );
		}

		$oembed_url = 'https://open.spotify.com/oembed?url=' . rawurlencode( $parsed['open_url'] );
		
		// Validate the oembed URL is exactly the expected Spotify domain.
		$parsed_oembed = wp_parse_url( $oembed_url );
		if ( ! is_array( $parsed_oembed ) || strtolower( $parsed_oembed['host'] ?? '' ) !== 'open.spotify.com' ) {
			return rest_ensure_response( [
				'valid'   => false,
				'reason'  => 'invalid_format',
				'message' => __( 'Invalid Spotify link format.', 'overcustomise' ),
			] );
		}
		
		$response   = wp_remote_get( $oembed_url, [
			'timeout'     => 8,
			'redirection' => 3,
			'headers'     => [ 'Accept' => 'application/json' ],
		] );

		if ( is_wp_error( $response ) ) {
			return rest_ensure_response( [
				'valid'   => false,
				'reason'  => 'unreachable',
				'message' => __( 'Could not validate Spotify right now. Please try again.', 'overcustomise' ),
			] );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 === $status ) {
			return rest_ensure_response( [
				'valid'      => true,
				'reason'     => 'ok',
				'spotifyUri' => $parsed['spotify_uri'],
				'openUrl'    => $parsed['open_url'],
			] );
		}

		if ( 429 === $status ) {
			return new \WP_Error( 'rate_limited', __( 'Spotify validation is rate limited. Please try again shortly.', 'overcustomise' ), [ 'status' => 429 ] );
		}
		if ( $status >= 500 ) {
			return new \WP_Error( 'unreachable', __( 'Could not validate Spotify right now. Please try again.', 'overcustomise' ), [ 'status' => 503 ] );
		}

		$is_playlist = 'playlist' === $parsed['type'];
		$message     = $is_playlist
			? __( 'That playlist is invalid or private. Please use a public playlist link.', 'overcustomise' )
			: __( 'That Spotify link is invalid or unavailable.', 'overcustomise' );

		return rest_ensure_response( [
			'valid'   => false,
			'reason'  => $is_playlist ? 'playlist_private_or_invalid' : 'invalid_or_unavailable',
			'message' => $message,
		] );
	}

	/**
	 * Parse a Spotify input into {type,id,spotify_uri,open_url}.
	 *
	 * @return array<string,string>|null
	 */
	private function parse_spotify_input( string $raw ): ?array {
		$raw = trim( $raw );
		if ( '' === $raw ) {
			return null;
		}

		if ( preg_match( '/^spotify:(track|album|artist|playlist|episode|show):([A-Za-z0-9]+)$/i', $raw, $m ) ) {
			$type = strtolower( $m[1] );
			$id   = $m[2];
			return [
				'type'        => $type,
				'id'          => $id,
				'spotify_uri' => sprintf( 'spotify:%s:%s', $type, $id ),
				'open_url'    => sprintf( 'https://open.spotify.com/%s/%s', $type, $id ),
			];
		}

		$parts = wp_parse_url( $raw );
		if ( ! is_array( $parts ) ) {
			return null;
		}

		$host = strtolower( (string) ( $parts['host'] ?? '' ) );
		if ( ! in_array( $host, [ 'open.spotify.com', 'play.spotify.com' ], true ) ) {
			return null;
		}

		$path_parts = array_values( array_filter( explode( '/', (string) ( $parts['path'] ?? '' ) ) ) );
		$path_parts = array_values( array_filter( $path_parts, static fn( $p ) => ! preg_match( '/^intl-[a-z]{2}$/i', $p ) ) );

		$valid_types = [ 'track', 'album', 'artist', 'playlist', 'episode', 'show' ];
		$type_index  = -1;
		foreach ( $path_parts as $i => $p ) {
			if ( in_array( strtolower( $p ), $valid_types, true ) ) {
				$type_index = (int) $i;
				break;
			}
		}
		if ( $type_index < 0 || empty( $path_parts[ $type_index + 1 ] ) ) {
			return null;
		}

		$type = strtolower( $path_parts[ $type_index ] );
		$id   = preg_replace( '/[^A-Za-z0-9]/', '', (string) $path_parts[ $type_index + 1 ] );
		if ( '' === $id ) {
			return null;
		}

		return [
			'type'        => $type,
			'id'          => $id,
			'spotify_uri' => sprintf( 'spotify:%s:%s', $type, $id ),
			'open_url'    => sprintf( 'https://open.spotify.com/%s/%s', $type, $id ),
		];
	}

	/** Regenerate a single print file by its DB record ID. */
	public function regenerate_files( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		// CSRF protection on top of the capability check in permission_callback.
		$nonce = $request->get_header( 'X-WP-Nonce' ) ?: $request->get_header( 'X-OC-Nonce' );
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new \WP_Error( 'invalid_nonce', __( 'Security check failed.', 'overcustomise' ), [ 'status' => 403 ] );
		}

		$file_id = absint( $request->get_param( 'file_id' ) );
		if ( ! $file_id ) {
			return new \WP_Error( 'invalid_param', __( 'file_id required.', 'overcustomise' ), [ 'status' => 400 ] );
		}

		if ( ! OC_DB::get_print_file( $file_id ) ) {
			return new \WP_Error( 'not_found', __( 'Print file record not found.', 'overcustomise' ), [ 'status' => 404 ] );
		}

		try {
			$result = ( new OC_Print_Generator() )->regenerate( $file_id );
		} catch ( \Throwable $e ) {
			OC_Logger::error( 'regenerate_files REST: ' . $e->getMessage() );
			return new \WP_Error( 'generation_failed', $e->getMessage(), [ 'status' => 500 ] );
		}

		return rest_ensure_response( [
			'file_id'   => $file_id,
			'file_path' => basename( (string) ( $result['file_path'] ?? '' ) ),
			'status'    => $result['status'] ?? '',
		] );
	}

	/** Upload and register a CSV file for VDP on a design. */
	public function upload_vdp_csv( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$nonce = $request->get_header( 'X-WP-Nonce' ) ?: $request->get_header( 'X-OC-Nonce' );
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new \WP_Error( 'invalid_nonce', __( 'Security check failed.', 'overcustomise' ), [ 'status' => 403 ] );
		}

		$design_id = absint( $request->get_param( 'design_id' ) );
		if ( ! $design_id ) {
			return new \WP_Error( 'invalid_param', __( 'design_id required.', 'overcustomise' ), [ 'status' => 400 ] );
		}

		$design = OC_DB::get_design( $design_id );
		if ( ! $design || ! (bool) $design->active ) {
			return new \WP_Error( 'invalid_design', __( 'Design not found or inactive.', 'overcustomise' ), [ 'status' => 400 ] );
		}

		$files = $request->get_file_params();
		if ( empty( $files['csv'] ) || ! is_uploaded_file( $files['csv']['tmp_name'] ) ) {
			return new \WP_Error( 'no_file', __( 'No CSV file received.', 'overcustomise' ), [ 'status' => 400 ] );
		}

		if ( UPLOAD_ERR_OK !== (int) $files['csv']['error'] ) {
			return new \WP_Error( 'upload_failed', __( 'CSV upload failed.', 'overcustomise' ), [ 'status' => 422 ] );
		}

		$ext = strtolower( pathinfo( $files['csv']['name'], PATHINFO_EXTENSION ) );
		if ( 'csv' !== $ext ) {
			return new \WP_Error( 'invalid_type', __( 'Only CSV files are allowed.', 'overcustomise' ), [ 'status' => 400 ] );
		}

		$max_bytes = 5 * 1024 * 1024;
		if ( (int) $files['csv']['size'] > $max_bytes ) {
			return new \WP_Error( 'too_large', __( 'CSV file exceeds 5 MB.', 'overcustomise' ), [ 'status' => 413 ] );
		}

		$upload = wp_upload_dir();
		if ( ! empty( $upload['error'] ) ) {
			return new \WP_Error( 'upload_dir_error', (string) $upload['error'], [ 'status' => 500 ] );
		}
		$dir    = $upload['basedir'] . '/overcustomise/vdp';
		if ( ! wp_mkdir_p( $dir ) ) {
			return new \WP_Error( 'mkdir_failed', __( 'Could not create VDP directory.', 'overcustomise' ), [ 'status' => 500 ] );
		}

		$filename = 'vdp-' . $design_id . '-' . time() . '.csv';
		$filepath = $dir . '/' . $filename;

		if ( false === move_uploaded_file( $files['csv']['tmp_name'], $filepath ) ) {
			return new \WP_Error( 'save_failed', __( 'Could not save CSV file.', 'overcustomise' ), [ 'status' => 500 ] );
		}

		$vdp = new OC_VDP();
		$csv_data = $vdp->parse_csv( $filepath );

		if ( empty( $csv_data['headers'] ) ) {
			@unlink( $filepath );
			return new \WP_Error( 'empty_csv', __( 'CSV file is empty or unreadable.', 'overcustomise' ), [ 'status' => 422 ] );
		}

		// Delete existing template and fields for this design.
		OC_DB::delete_vdp_template( $design_id );

		// Upsert template.
		OC_DB::upsert_vdp_template( [
			'design_id'     => $design_id,
			'csv_file_path' => $filepath,
			'active'        => 1,
		] );

		$template = OC_DB::get_vdp_template( $design_id );
		if ( ! $template ) {
			@unlink( $filepath );
			return new \WP_Error( 'db_error', __( 'Could not create VDP template.', 'overcustomise' ), [ 'status' => 500 ] );
		}

		// Insert fields from CSV headers.
		$all_layers = OC_DB::get_design_layers( $design_id );
		$layer_ids  = array_values( array_map( static fn( $l ) => (int) $l->id, $all_layers ) );

		foreach ( $csv_data['headers'] as $index => $header ) {
			$layer_id = $layer_ids[ $index ] ?? 0;
			$inserted = OC_DB::insert_vdp_field( [
				'template_id' => (int) $template->id,
				'field_name'  => $header,
				'layer_id'    => $layer_id,
				'sort_order'  => $index,
			] );
			if ( $inserted <= 0 ) {
				OC_DB::delete_vdp_template( $design_id );
				@unlink( $filepath );
				return new \WP_Error( 'db_error', __( 'Could not save VDP fields.', 'overcustomise' ), [ 'status' => 500 ] );
			}
		}

		return rest_ensure_response( [
			'success'    => true,
			'template_id'=> (int) $template->id,
			'fields'     => $csv_data['headers'],
			'row_count'  => count( $csv_data['rows'] ),
			'file_path'  => $filepath,
		] );
	}

	/** Return customisation data for a cart item so it can be edited. */
	public function get_cart_item_customisation( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$cart_key = sanitize_text_field( $request->get_param( 'cart_key' ) );
		$cart     = WC()->cart ?? null;

		if ( ! $cart || ! isset( $cart->cart_contents[ $cart_key ] ) ) {
			return new \WP_Error( 'not_found', __( 'Cart item not found.', 'overcustomise' ), [ 'status' => 404 ] );
		}

		$cart_item = $cart->cart_contents[ $cart_key ];
		$customisation = $cart_item['_oc_customisation'] ?? null;

		if ( empty( $customisation ) ) {
			return new \WP_Error( 'no_customisation', __( 'No customisation data for this cart item.', 'overcustomise' ), [ 'status' => 404 ] );
		}

		return rest_ensure_response( [
			'customisation' => $customisation,
			'designId'      => (int) ( $cart_item['_oc_design_id'] ?? 0 ),
			'previewUrl'    => (string) ( $cart_item['_oc_preview_url'] ?? '' ),
		] );
	}

	/** Update a cart item's customisation data from the edit flow. */
	public function update_cart_item( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$auth = $this->verify_public_write_auth( $request );
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}

		$cart = WC()->cart ?? null;
		if ( ! $cart ) {
			return new \WP_Error( 'no_cart', __( 'Cart not available.', 'overcustomise' ), [ 'status' => 500 ] );
		}

		$cart_key = sanitize_text_field( $request->get_param( 'cart_key' ) );
		if ( '' === $cart_key || ! isset( $cart->cart_contents[ $cart_key ] ) ) {
			return new \WP_Error( 'not_found', __( 'Cart item not found.', 'overcustomise' ), [ 'status' => 404 ] );
		}

		$cart_item = $cart->cart_contents[ $cart_key ];
		$customisation = $cart_item['_oc_customisation'] ?? null;

		if ( empty( $customisation ) ) {
			return new \WP_Error( 'no_customisation', __( 'No customisation data for this cart item.', 'overcustomise' ), [ 'status' => 400 ] );
		}

		$body = $request->get_json_params();
		if ( ! is_array( $body ) || ! isset( $body['designId'] ) || ! isset( $body['layers'] ) ) {
			return new \WP_Error( 'invalid_data', __( 'Invalid customisation data.', 'overcustomise' ), [ 'status' => 400 ] );
		}

		$design_id = absint( $body['designId'] );
		$raw_layers = $body['layers'];

		if ( ! is_array( $raw_layers ) || empty( $raw_layers ) ) {
			return new \WP_Error( 'invalid_data', __( 'No layers provided.', 'overcustomise' ), [ 'status' => 400 ] );
		}

		$design = OC_DB::get_design( $design_id );
		if ( ! $design || ! (bool) $design->active ) {
			return new \WP_Error( 'invalid_design', __( 'Design not found or inactive.', 'overcustomise' ), [ 'status' => 400 ] );
		}

		$design_layers = [];
		foreach ( OC_DB::get_design_layers( $design_id ) as $layer ) {
			$layer_id = isset( $layer->id ) ? absint( $layer->id ) : 0;
			if ( ! $layer_id ) continue;
			$layer_type = isset( $layer->type ) ? sanitize_key( (string) $layer->type ) : '';
			if ( '' === $layer_type ) continue;
			$settings = $layer->settings ? json_decode( (string) $layer->settings, true ) : [];
			$design_layers[ $layer_id ] = [
				'type'     => $layer_type,
				'settings' => is_array( $settings ) ? $settings : [],
			];
		}

		if ( empty( $design_layers ) ) {
			return new \WP_Error( 'invalid_design', __( 'Design has no layers.', 'overcustomise' ), [ 'status' => 400 ] );
		}

		$valid_layer_types = [ 'text', 'textarea', 'image', 'spotify', 'lineart', 'clipart' ];

		$sanitised_layers = [];
		$fallback_font_id = $this->first_active_font_id();
		foreach ( $raw_layers as $layer_id => $layer_data ) {
			if ( ! is_array( $layer_data ) ) continue;

			$layer_key = absint( $layer_id );
			if ( ! $layer_key ) continue;
			if ( ! isset( $design_layers[ $layer_key ] ) ) continue;

			$type     = $design_layers[ $layer_key ]['type'];
			$settings = $design_layers[ $layer_key ]['settings'];
			if ( ! in_array( $type, $valid_layer_types, true ) ) continue;

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

			$sanitised_layers[ $layer_key ] = [
				'type'         => $type,
				'value'        => is_scalar( $layer_data['value'] ?? null ) ? sanitize_text_field( (string) $layer_data['value'] ) : '',
				'fontId'       => $font_id,
				'fontSize'     => $font_size,
				'colorHex'     => $color_hex,
				'attachmentId' => absint( $layer_data['attachmentId'] ?? 0 ),
				'clipartId'    => absint( $layer_data['clipartId'] ?? 0 ),
				'clipartUrl'   => is_string( $layer_data['clipartUrl'] ?? null ) ? esc_url_raw( $layer_data['clipartUrl'] ) : '',
			];
		}

		if ( empty( $sanitised_layers ) ) {
			return new \WP_Error( 'invalid_data', __( 'No valid layers provided.', 'overcustomise' ), [ 'status' => 400 ] );
		}

		$old_preview = (string) ( $cart_item['_oc_preview_url'] ?? '' );

		$cart->cart_contents[ $cart_key ]['_oc_customisation'] = [
			'v'          => 2,
			'designId'   => $design_id,
			'layers'     => $sanitised_layers,
			'renderSpec' => OC_Render_Spec::build( $design_id, $sanitised_layers ),
		];
		$cart->cart_contents[ $cart_key ]['_oc_design_id']     = $design_id;
		$cart->cart_contents[ $cart_key ]['_oc_flat_rate']     = (float) $design->flat_rate;
		$cart->cart_contents[ $cart_key ]['_oc_unique_key']    = md5( wp_json_encode( $sanitised_layers ) . microtime() );

		unset( $cart->cart_contents[ $cart_key ]['_oc_preview_url'] );

		$preview_url = $body['previewUrl'] ?? '';
		if ( is_string( $preview_url ) && '' !== $preview_url ) {
			$uploads = wp_upload_dir();
			$baseurl = isset( $uploads['baseurl'] ) ? rtrim( (string) $uploads['baseurl'], '/' ) : '';
			$sanitized_url = esc_url_raw( $preview_url );
			if ( '' !== $sanitized_url && '' !== $baseurl ) {
				$expected_prefix = $baseurl . '/overcustomise/previews/preview-';
				if ( 0 === strpos( $sanitized_url, $expected_prefix ) ) {
					$path = wp_parse_url( $sanitized_url, PHP_URL_PATH );
					if ( is_string( $path ) && preg_match( '#/overcustomise/previews/preview-[a-f0-9]{32}\.(?:png|jpe?g)$#i', $path ) ) {
						$cart->cart_contents[ $cart_key ]['_oc_preview_url'] = $sanitized_url;
					}
				}
			}
		}

		if ( method_exists( $cart, 'update_totals_after_cart_modification' ) ) {
			$cart->update_totals_after_cart_modification();
		}

		$new_preview = (string) ( $cart->cart_contents[ $cart_key ]['_oc_preview_url'] ?? '' );
		if ( $old_preview && $old_preview !== $new_preview ) {
			$path = wp_parse_url( $old_preview, PHP_URL_PATH );
			if ( is_string( $path ) && '' !== $path ) {
				$filename = basename( $path );
				$uploads  = wp_upload_dir();
				$basedir  = isset( $uploads['basedir'] ) ? (string) $uploads['basedir'] : '';
				if ( '' !== $filename && '' !== $basedir ) {
					$filepath = $basedir . '/overcustomise/previews/' . $filename;
					if ( file_exists( $filepath ) ) {
						@unlink( $filepath );
					}
				}
			}
		}

		return rest_ensure_response( [ 'success' => true ] );
	}

	private function first_active_font_id(): int {
		$fonts = OC_DB::get_fonts( true );
		$first = is_array( $fonts ) && ! empty( $fonts ) ? reset( $fonts ) : null;

		return is_object( $first ) && ! empty( $first->id ) ? absint( $first->id ) : 0;
	}
}
