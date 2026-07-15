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
		$product_id = $request->get_param( 'product_id' );
		$variant_id = (int) $request->get_param( 'variant_id' );

		// Replicate the same priority logic as OC_Frontend::get_assignment().
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return new \WP_Error( 'not_found', 'Product not found.', [ 'status' => 404 ] );
		}

		$state = OC_Frontend::build_assignment_state( (int) $product_id, $variant_id );
		if ( is_wp_error( $state ) ) {
			return $state;
		}

		return rest_ensure_response( $state );
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
				'engraving_material' => isset( $area->engraving_material ) ? (string) $area->engraving_material : 'silver_metal',
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

		// Uploads are always scoped to an assigned product/design/layer context.
		$layer_overrides = [];
		$layer_id        = absint( $request->get_param( 'layer_id' ) );
		$design_id       = absint( $request->get_param( 'design_id' ) );
		$product_id      = absint( $request->get_param( 'product_id' ) );
		$variation_id    = absint( $request->get_param( 'variation_id' ) );
		if ( ! $layer_id || ! $design_id || ! $product_id ) {
			return new \WP_Error( 'invalid_context', __( 'Product, design, and layer are required for artwork uploads.', 'overcustomise' ), [ 'status' => 400 ] );
		}

		$product = wc_get_product( $product_id );
		if ( ! $product || $product->is_type( 'variation' ) ) {
			return new \WP_Error( 'invalid_product', __( 'Invalid product for artwork upload.', 'overcustomise' ), [ 'status' => 400 ] );
		}
		if ( $variation_id ) {
			$variation = wc_get_product( $variation_id );
			if ( ! $variation || ! $variation->is_type( 'variation' ) || $product_id !== (int) $variation->get_parent_id() ) {
				return new \WP_Error( 'invalid_variation', __( 'The selected variation is not valid for this product.', 'overcustomise' ), [ 'status' => 400 ] );
			}
		} elseif ( $product->is_type( 'variable' ) ) {
			return new \WP_Error( 'invalid_variation', __( 'Please select a product variation before uploading artwork.', 'overcustomise' ), [ 'status' => 400 ] );
		}

		if ( $layer_id ) {
			global $wpdb;
			$assignment = OC_DB::get_assignment_for_product( $product_id, $variation_id );
			if ( ! $assignment || ! OC_DB::assignment_allows_design( $assignment, $design_id ) ) {
				return new \WP_Error( 'invalid_design', __( 'Design is not assigned to this product.', 'overcustomise' ), [ 'status' => 400 ] );
			}

			$row = $wpdb->get_row( $wpdb->prepare(
				"SELECT type, settings FROM {$wpdb->prefix}oc_design_layers WHERE id = %d AND design_id = %d LIMIT 1",
				$layer_id,
				$design_id
			) );
			if ( ! $row ) {
				return new \WP_Error( 'invalid_layer', __( 'Invalid upload layer.', 'overcustomise' ), [ 'status' => 400 ] );
			}
			if ( ! in_array( (string) $row->type, [ 'image', 'clipmask' ], true ) ) {
				return new \WP_Error( 'invalid_layer', __( 'This layer does not accept artwork uploads.', 'overcustomise' ), [ 'status' => 400 ] );
			}
			if ( $row && $row->settings ) {
				$s = json_decode( $row->settings, true );
				if ( is_array( $s ) ) {
					$formats     = isset( $s['formats'] ) && is_array( $s['formats'] ) ? array_values( array_filter( $s['formats'] ) ) : [];
					$max_size_mb = isset( $s['max_size_mb'] ) ? (int) $s['max_size_mb'] : 0;
					$global_formats = array_map( 'strtolower', (array) OC_Admin_Settings::get( 'allowed_upload_formats' ) );
					$formats = $formats ? array_values( array_intersect( $global_formats, array_map( 'strtolower', $formats ) ) ) : $global_formats;
					$global_max = (int) OC_Admin_Settings::get( 'max_upload_size_mb' ) ?: 10;
					$layer_overrides = [
						'formats'           => $formats,
						'max_size_mb'       => $max_size_mb > 0 ? min( $global_max, $max_size_mb ) : $global_max,
						'remove_background' => ! empty( $s['remove_background'] ),
					];
				}
			}
		}

		try {
			$token = (string) ( $request->get_header( 'X-OC-Token' ) ?: $request->get_param( 'oc_token' ) );
			$result = OC_Upload_Handler::process( $files['artwork'], $layer_overrides, [
				'product_id'   => $product_id,
				'variation_id' => $variation_id,
				'design_id'    => $design_id,
				'layer_id'     => $layer_id,
				'token_hash'   => $token ? hash( 'sha256', $token ) : '',
			] );
			if ( $token ) {
				$key = self::public_token_key( $token );
				$ctx = get_transient( $key );
				if ( is_array( $ctx ) ) {
					$ctx['attachments'][ (int) $result['attachment_id'] ] = [ $product_id, $variation_id, $design_id, $layer_id ];
					set_transient( $key, $ctx, self::PUBLIC_TOKEN_TTL );
				}
			}
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
		$width  = (int) ( $image_info[0] ?? 0 );
		$height = (int) ( $image_info[1] ?? 0 );
		if ( $width <= 0 || $height <= 0 || $width > 12000 || $height > 12000 || $width * $height > 40000000 ) {
			return new \WP_Error( 'invalid_dimensions', __( 'Preview image dimensions exceed the safe limit.', 'overcustomise' ), [ 'status' => 413 ] );
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
			'warning'   => (string) ( $result['warning'] ?? '' ),
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

		$filename = 'vdp-' . $design_id . '-' . wp_generate_uuid4() . '.csv';
		$filepath = $dir . '/' . $filename;

		if ( false === move_uploaded_file( $files['csv']['tmp_name'], $filepath ) ) {
			return new \WP_Error( 'save_failed', __( 'Could not save CSV file.', 'overcustomise' ), [ 'status' => 500 ] );
		}

		$old_filepath = '';
		$vdp          = new OC_VDP();
		$csv_data = $vdp->parse_csv( $filepath );

		if ( empty( $csv_data['headers'] ) ) {
			@unlink( $filepath );
			return new \WP_Error( 'empty_csv', __( 'CSV file is empty or unreadable.', 'overcustomise' ), [ 'status' => 422 ] );
		}

		global $wpdb;
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			self::delete_vdp_file( $filepath );
			return new \WP_Error( 'db_error', __( 'Could not start the VDP update.', 'overcustomise' ), [ 'status' => 500 ] );
		}
		try {
			// Serialize replacements for this design, including its first template.
			$wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}oc_designs WHERE id = %d FOR UPDATE", $design_id ) );
			$old_template = OC_DB::get_vdp_template( $design_id );
			$old_filepath = $old_template ? (string) $old_template->csv_file_path : '';
			if ( ! OC_DB::delete_vdp_template( $design_id ) ) {
				throw new \RuntimeException( 'Could not remove the previous VDP template.' );
			}
			if ( ! OC_DB::upsert_vdp_template( [
				'design_id'     => $design_id,
				'csv_file_path' => $filepath,
				'active'        => 1,
			] ) ) {
				throw new \RuntimeException( 'Could not create the VDP template.' );
			}

			$template = OC_DB::get_vdp_template( $design_id );
			if ( ! $template ) {
				throw new \RuntimeException( 'Could not reload the VDP template.' );
			}

			$all_layers = OC_DB::get_design_layers( $design_id );
			$layer_ids  = array_values( array_map( static fn( $layer ) => (int) $layer->id, $all_layers ) );
			foreach ( $csv_data['headers'] as $index => $header ) {
				$inserted = OC_DB::insert_vdp_field( [
					'template_id' => (int) $template->id,
					'field_name'  => $header,
					'layer_id'    => $layer_ids[ $index ] ?? 0,
					'sort_order'  => $index,
				] );
				if ( $inserted <= 0 ) {
					throw new \RuntimeException( 'Could not save VDP fields.' );
				}
			}

			if ( false === $wpdb->query( 'COMMIT' ) ) {
				throw new \RuntimeException( 'Could not commit the VDP template.' );
			}
		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			self::delete_vdp_file( $filepath );
			OC_Logger::error( 'VDP replacement failed: ' . $e->getMessage() );
			return new \WP_Error( 'db_error', __( 'Could not save the VDP template.', 'overcustomise' ), [ 'status' => 500 ] );
		}

		if ( '' !== $old_filepath && $old_filepath !== $filepath ) {
			self::delete_vdp_file( $old_filepath );
		}

		return rest_ensure_response( [
			'success'    => true,
			'template_id'=> (int) $template->id,
			'fields'     => $csv_data['headers'],
			'row_count'  => count( $csv_data['rows'] ),
			'file_path'  => $filepath,
		] );
	}

	/** Delete a VDP CSV only when it resolves inside the plugin's VDP upload directory. */
	private static function delete_vdp_file( string $filepath ): void {
		$uploads = wp_upload_dir();
		$base    = realpath( (string) ( $uploads['basedir'] ?? '' ) . '/overcustomise/vdp' );
		$real    = realpath( $filepath );
		if ( $base && $real && str_starts_with( $real, rtrim( $base, '/\\' ) . DIRECTORY_SEPARATOR ) ) {
			wp_delete_file( $real );
		}
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

		$design_id  = absint( $body['designId'] );
		$raw_layers = $body['layers'];

		if ( ! is_array( $raw_layers ) || empty( $raw_layers ) ) {
			return new \WP_Error( 'invalid_data', __( 'No layers provided.', 'overcustomise' ), [ 'status' => 400 ] );
		}

		$product_id   = absint( $cart_item['product_id'] ?? 0 );
		$variation_id = absint( $cart_item['variation_id'] ?? 0 );
		$upload_token = (string) ( $request->get_header( 'X-OC-Token' ) ?: ( $body['uploadToken'] ?? '' ) );
		$allowed_existing_attachments = [];
		if ( isset( $customisation['v'], $customisation['designId'], $customisation['layers'] )
			&& 2 === (int) $customisation['v'] && $design_id === absint( $customisation['designId'] ) && is_array( $customisation['layers'] )
		) {
			foreach ( $customisation['layers'] as $layer_id => $layer_data ) {
				if ( is_array( $layer_data ) && ! empty( $layer_data['attachmentId'] ) ) {
					$allowed_existing_attachments[ absint( $layer_id ) ] = absint( $layer_data['attachmentId'] );
				}
			}
		}
		$normalised = OC_Cart::normalise_v2_layers( $product_id, $variation_id, $design_id, $raw_layers, $upload_token, $allowed_existing_attachments );
		if ( is_wp_error( $normalised ) ) {
			$normalised->add_data( [ 'status' => 400 ] );
			return $normalised;
		}
		$design           = $normalised['design'];
		$sanitised_layers = $normalised['layers'];

		$snapshots = OC_DB::sanitise_area_snapshots( is_array( $body['snapshots'] ?? null ) ? $body['snapshots'] : [] );

		$cart->cart_contents[ $cart_key ]['_oc_customisation'] = [
			'v'          => 2,
			'designId'   => $design_id,
			'layers'     => $sanitised_layers,
			'renderSpec' => OC_Render_Spec::build( $design_id, $sanitised_layers, $snapshots ),
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
				$path = wp_parse_url( $sanitized_url, PHP_URL_PATH );
				if ( is_string( $path ) && preg_match( '#/overcustomise/previews/(preview-[a-f0-9]{32}\.(?:png|jpe?g))$#i', $path, $matches ) ) {
					$cart->cart_contents[ $cart_key ]['_oc_preview_url'] = $baseurl . '/overcustomise/previews/' . $matches[1];
				}
			}
		}

		if ( method_exists( $cart, 'update_totals_after_cart_modification' ) ) {
			$cart->update_totals_after_cart_modification();
		}

		return rest_ensure_response( [ 'success' => true ] );
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
