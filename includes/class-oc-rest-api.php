<?php
/**
 * REST API — /wp-json/overcustomise/v1/ endpoints.
 *
 * Endpoints:
 *   GET  /product-config/{product_id}   — fetch config + print areas + fonts for the customiser
 *   GET  /session-token                 — issue/reuse a no-store frontend request token
 *   POST /upload-artwork                — customer artwork upload
 *   GET  /fonts                         — list active fonts (public)
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

class OC_Rest_API {

	private const NAMESPACE = 'overcustomise/v1';
	private const PUBLIC_TOKEN_TTL = 21600; // 6 hours.
	private const AI_LOCK_TTL = 300;
	private const PREVIEW_LOCK_TTL = 60;
	private const MAX_PREVIEW_BYTES = 10485760;
	private const MAX_AI_RESULT_BYTES = 15728640;
	private const MAX_AI_PROMPT_BYTES = 16384;
	private const MAX_AI_FILTER_ATTEMPTS = 3;
	private const SPOTIFY_RESPONSE_BYTES = 524288;
	private const SPOTIFY_VALID_CACHE_TTL = 43200;
	private const SPOTIFY_INVALID_CACHE_TTL = 3600;
	private const PUBLIC_TOKEN_SESSION_KEY = 'oc_public_request_token';
	private const PREVIEW_OPTION_PREFIX = 'oc_private_preview_';

	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
		add_action( 'init', [ self::class, 'ensure_vdp_storage' ] );
		add_action( 'admin_post_oc_serve_preview', [ self::class, 'serve_private_preview' ] );
		add_action( 'admin_post_nopriv_oc_serve_preview', [ self::class, 'serve_private_preview' ] );
	}

	/** Ensure VDP values use private storage and migrate a bounded legacy batch. */
	public static function ensure_vdp_storage(): void {
		$legacy_directory = self::legacy_vdp_directory();
		if ( null !== $legacy_directory ) {
			self::protect_legacy_vdp_directory( $legacy_directory );
		}

		$directory = self::protected_vdp_directory();
		if ( null === $directory ) {
			OC_Logger::warning( 'Private VDP storage is unavailable.' );
			return;
		}

		self::migrate_legacy_vdp_files( $directory );
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

		// Reusable session-bound token for guest writes and cart validation.
		register_rest_route( self::NAMESPACE, '/session-token', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_session_token' ],
			'permission_callback' => '__return_true',
		] );

		// Artwork upload (customer).
		register_rest_route( self::NAMESPACE, '/upload-artwork', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'upload_artwork' ],
			'permission_callback' => [ $this, 'public_write_permission' ],
		] );

		// Authorise an owned upload for a matching layer on this product.
		register_rest_route( self::NAMESPACE, '/authorise-artwork-context', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'authorise_artwork_context' ],
			'permission_callback' => [ $this, 'public_write_permission' ],
		] );

		// Apply an AI prompt filter to previously uploaded artwork.
		register_rest_route( self::NAMESPACE, '/apply-image-filter', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'apply_image_filter' ],
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

	/** Issue or reuse a fixed-lifetime token bound to this WC session or client IP. */
	public static function issue_public_token(): string {
		$binding = self::current_request_binding();
		if ( null === $binding ) {
			OC_Logger::warning( 'A public request token could not be issued because no valid session or client IP was available.' );
			return '';
		}

		$current = self::token_for_binding( $binding );
		if ( '' !== $current && self::public_token_state( $current, $binding ) ) {
			return $current;
		}

		$lock_key = 'oc_token_issue_lock_' . hash( 'sha256', $binding['type'] . '|' . $binding['hash'] );
		$lock     = self::acquire_option_lock( $lock_key, 15 );
		if ( is_wp_error( $lock ) ) {
			// A concurrent token request may have completed after the first lookup.
			$current = self::token_for_binding( $binding );
			return '' !== $current && self::public_token_state( $current, $binding ) ? $current : '';
		}

		try {
			$current = self::token_for_binding( $binding );
			if ( '' !== $current && self::public_token_state( $current, $binding ) ) {
				return $current;
			}
			try {
				$token = bin2hex( random_bytes( 32 ) );
			} catch ( \Throwable $e ) {
				$token = wp_generate_password( 64, false, false );
			}
			if ( ! preg_match( '/^[A-Za-z0-9]{64}$/D', $token ) ) {
				OC_Logger::error( 'Secure public request token generation failed.' );
				return '';
			}

			$created = time();
			$state   = [
				'version'      => 2,
				'binding_type' => $binding['type'],
				'binding_hash' => $binding['hash'],
				'created_at'   => $created,
				'expires_at'   => $created + self::PUBLIC_TOKEN_TTL,
			];
			if ( ! set_transient( self::public_token_key( $token ), $state, self::PUBLIC_TOKEN_TTL ) ) {
				OC_Logger::error( 'Public request token state could not be persisted.' );
				return '';
			}
			if ( ! self::store_token_for_binding( $binding, $token ) ) {
				delete_transient( self::public_token_key( $token ) );
				OC_Logger::error( 'Public request token could not be associated with its request binding.' );
				return '';
			}

			return $token;
		} finally {
			self::delete_owned_option( $lock_key, (string) $lock );
		}
	}

	/** Return a public token response which intermediaries must never cache. */
	public function get_session_token( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		if ( ! self::request_origin_is_allowed() ) {
			return new \WP_Error( 'invalid_origin', __( 'Security verification failed.', 'overcustomise' ), [ 'status' => 403 ] );
		}
		try {
			$token = self::issue_public_token();
		} catch ( \Throwable $e ) {
			OC_Logger::error( 'Public request token issuance failed unexpectedly: ' . $e->getMessage() );
			$token = '';
		}
		if ( '' === $token ) {
			return new \WP_Error( 'token_unavailable', __( 'Security verification is temporarily unavailable.', 'overcustomise' ), [ 'status' => 503 ] );
		}
		$state = self::public_token_state( $token );
		if ( ! is_array( $state ) ) {
			return new \WP_Error( 'token_unavailable', __( 'Security verification is temporarily unavailable.', 'overcustomise' ), [ 'status' => 503 ] );
		}

		$response = new \WP_REST_Response(
			[
				'token'      => $token,
				'expires_in' => max( 1, $state['expires_at'] - time() ),
			],
			200
		);
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
		$response->header( 'Pragma', 'no-cache' );
		$response->header( 'Expires', '0' );
		$response->header( 'Vary', 'Cookie, Origin' );
		$response->header( 'X-Robots-Tag', 'noindex, nofollow' );
		return $response;
	}

	/** Shared permission callback for guest/frontend write endpoints. */
	public function public_write_permission( \WP_REST_Request $request ): bool|\WP_Error {
		return $this->verify_public_write_auth( $request );
	}

	/** Validate request auth for public write endpoints. */
	private function verify_public_write_auth( \WP_REST_Request $request ): bool|\WP_Error {
		if ( ! self::request_origin_is_allowed() ) {
			return new \WP_Error( 'invalid_origin', __( 'Security verification failed.', 'overcustomise' ), [ 'status' => 403 ] );
		}
		$nonce = (string) ( $request->get_header( 'X-WP-Nonce' ) ?: $request->get_param( '_wpnonce' ) );

		// Logged-in users authenticate via standard REST nonce.
		if ( is_user_logged_in() ) {
			if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
				return new \WP_Error( 'invalid_nonce', __( 'Security verification failed.', 'overcustomise' ), [ 'status' => 403 ] );
			}
			return true;
		}

		$token = (string) $request->get_header( 'X-OC-Token' );
		return self::verify_public_token( $token );
	}

	/** Reject browser requests that explicitly identify a different origin. */
	private static function request_origin_is_allowed(): bool {
		$origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? trim( (string) $_SERVER['HTTP_ORIGIN'] ) : '';
		if ( '' === $origin ) {
			return true;
		}
		$expected = wp_parse_url( home_url( '/' ) );
		$actual   = wp_parse_url( $origin );
		if ( ! is_array( $expected ) || ! is_array( $actual ) ) {
			return false;
		}
		$expected_scheme = strtolower( (string) ( $expected['scheme'] ?? '' ) );
		$actual_scheme   = strtolower( (string) ( $actual['scheme'] ?? '' ) );
		$expected_host   = strtolower( (string) ( $expected['host'] ?? '' ) );
		$actual_host     = strtolower( (string) ( $actual['host'] ?? '' ) );
		$expected_port   = (int) ( $expected['port'] ?? ( 'https' === $expected_scheme ? 443 : 80 ) );
		$actual_port     = (int) ( $actual['port'] ?? ( 'https' === $actual_scheme ? 443 : 80 ) );

		return in_array( $actual_scheme, [ 'http', 'https' ], true )
			&& $actual_scheme === $expected_scheme
			&& '' !== $actual_host
			&& $actual_host === $expected_host
			&& $actual_port === $expected_port;
	}

	/** Minimal public helper used by cart requests which cannot carry a REST nonce. */
	public static function validate_public_token( string $token ): bool {
		return true === self::verify_public_token( $token );
	}

	/** Return the current binding's live token without issuing a replacement. */
	public static function current_session_public_token(): string {
		$binding = self::current_request_binding();
		if ( null === $binding ) {
			return '';
		}

		$token = self::token_for_binding( $binding );
		return '' !== $token && self::public_token_state( $token, $binding ) ? $token : '';
	}

	/** Validate live token ownership of one authorised attachment/context tuple. */
	public static function public_token_owns_attachment( string $token, int $attachment_id, array $context ): bool {
		if ( $attachment_id <= 0 || ! self::validate_public_token( $token ) ) {
			return false;
		}

		$expected_context = array_values( array_map( 'intval', $context ) );
		$primary_context  = array_values( array_map( 'intval', (array) get_post_meta( $attachment_id, '_oc_artwork_context', true ) ) );
		$stored_hash      = (string) get_post_meta( $attachment_id, '_oc_artwork_token', true );

		return 4 === count( $expected_context )
			&& ( $expected_context === $primary_context || OC_Upload_Handler::attachment_context_is_authorised( $attachment_id, $expected_context ) )
			&& 64 === strlen( $stored_hash )
			&& hash_equals( $stored_hash, hash( 'sha256', $token ) );
	}

	/** Validate a fixed-lifetime token against its original binding. */
	private static function verify_public_token( string $token ): true|\WP_Error {
		if ( ! preg_match( '/^[A-Za-z0-9]{64}$/D', $token ) ) {
			return new \WP_Error( 'invalid_token', __( 'Security verification failed.', 'overcustomise' ), [ 'status' => 403 ] );
		}

		if ( ! self::public_token_state( $token ) ) {
			return new \WP_Error( 'invalid_token', __( 'Security verification failed.', 'overcustomise' ), [ 'status' => 403 ] );
		}
		return true;
	}

	/** Return validated token state, optionally against a previously resolved binding. */
	private static function public_token_state( string $token, ?array $binding = null ): ?array {
		if ( ! preg_match( '/^[A-Za-z0-9]{64}$/D', $token ) ) {
			return null;
		}

		$state = get_transient( self::public_token_key( $token ) );
		if ( ! is_array( $state )
			|| ! is_int( $state['version'] ?? null ) || 2 !== $state['version']
			|| ! in_array( $state['binding_type'] ?? '', [ 'session', 'ip' ], true )
			|| ! is_string( $state['binding_hash'] ?? null )
			|| 64 !== strlen( $state['binding_hash'] )
			|| ! is_int( $state['created_at'] ?? null ) || $state['created_at'] <= 0
			|| ! is_int( $state['expires_at'] ?? null ) || $state['expires_at'] <= time()
			|| $state['expires_at'] > $state['created_at'] + self::PUBLIC_TOKEN_TTL
		) {
			return null;
		}

		if ( null === $binding ) {
			if ( 'session' === $state['binding_type'] ) {
				$session_hash = self::wc_session_hash();
				$binding = '' !== $session_hash ? [ 'type' => 'session', 'hash' => $session_hash ] : null;
			} else {
				$ip = self::client_ip();
				$binding = '' !== $ip ? [ 'type' => 'ip', 'hash' => hash( 'sha256', $ip ) ] : null;
			}
		}
		if ( null === $binding
			|| $binding['type'] !== $state['binding_type']
			|| ! hash_equals( $state['binding_hash'], $binding['hash'] )
		) {
			return null;
		}

		return $state;
	}

	/** Build a safe transient key from a token value. */
	private static function public_token_key( string $token ): string {
		return 'oc_pubtok_' . hash( 'sha256', $token );
	}

	/** Return the strongest available request binding, preferring WC sessions. */
	private static function current_request_binding(): ?array {
		$session_hash = self::wc_session_hash();
		if ( '' !== $session_hash ) {
			return [ 'type' => 'session', 'hash' => $session_hash ];
		}

		$ip = self::client_ip();
		return '' !== $ip ? [ 'type' => 'ip', 'hash' => hash( 'sha256', $ip ) ] : null;
	}

	/** Hash the current WC customer/session identifier without exposing it. */
	private static function wc_session_hash(): string {
		$session = function_exists( 'WC' ) && WC() ? WC()->session ?? null : null;
		$id      = $session && method_exists( $session, 'get_customer_id' ) ? (string) $session->get_customer_id() : '';
		if ( '' === $id || strlen( $id ) > 256 ) {
			return '';
		}

		return hash_hmac( 'sha256', $id, wp_salt( 'auth' ) );
	}

	/** Read the token associated with a WC session or IP fallback. */
	private static function token_for_binding( array $binding ): string {
		if ( 'session' === $binding['type'] ) {
			$session = function_exists( 'WC' ) && WC() ? WC()->session ?? null : null;
			$value   = $session && method_exists( $session, 'get' ) ? $session->get( self::PUBLIC_TOKEN_SESSION_KEY, '' ) : '';
			return is_string( $value ) ? $value : '';
		}

		$value = get_transient( 'oc_pubmap_' . $binding['hash'] );
		return is_string( $value ) ? $value : '';
	}

	/** Persist the reusable token in the selected binding store. */
	private static function store_token_for_binding( array $binding, string $token ): bool {
		if ( 'session' === $binding['type'] ) {
			$session = function_exists( 'WC' ) && WC() ? WC()->session ?? null : null;
			if ( ! $session || ! method_exists( $session, 'set' ) || ! method_exists( $session, 'get' ) ) {
				return false;
			}
			$session->set( self::PUBLIC_TOKEN_SESSION_KEY, $token );
			return hash_equals( $token, (string) $session->get( self::PUBLIC_TOKEN_SESSION_KEY, '' ) );
		}

		return set_transient( 'oc_pubmap_' . $binding['hash'], $token, self::PUBLIC_TOKEN_TTL );
	}

	/** Get a canonical client IP, trusting forwarding data only from explicit proxies. */
	private static function client_ip(): string {
		$remote = self::canonical_ip( isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '' );
		if ( '' === $remote ) {
			return '';
		}

		$trusted = self::trusted_proxy_ranges();
		if ( ! self::ip_matches_any_range( $remote, $trusted ) ) {
			return $remote;
		}

		$forwarded = isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ? (string) $_SERVER['HTTP_X_FORWARDED_FOR'] : '';
		if ( '' !== trim( $forwarded ) ) {
			$chain = [];
			foreach ( explode( ',', $forwarded ) as $candidate ) {
				$ip = self::canonical_ip( trim( $candidate ) );
				if ( '' === $ip ) {
					return $remote;
				}
				$chain[] = $ip;
			}
			if ( $chain ) {
				$chain[] = $remote;
				for ( $i = count( $chain ) - 1; $i >= 0; $i-- ) {
					if ( ! self::ip_matches_any_range( $chain[ $i ], $trusted ) ) {
						return $chain[ $i ];
					}
				}
				return $chain[0];
			}
		}

		$real_ip = self::canonical_ip( isset( $_SERVER['HTTP_X_REAL_IP'] ) ? (string) $_SERVER['HTTP_X_REAL_IP'] : '' );
		return '' !== $real_ip ? $real_ip : $remote;
	}

	/** Canonicalise an IPv4 or IPv6 address using PHP's IP validator. */
	private static function canonical_ip( string $ip ): string {
		$validated = filter_var( trim( $ip ), FILTER_VALIDATE_IP );
		if ( false === $validated ) {
			return '';
		}

		$packed = @inet_pton( $validated ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		return false === $packed ? '' : (string) inet_ntop( $packed );
	}

	/** Return validated explicit trusted proxy IP/CIDR entries. */
	private static function trusted_proxy_ranges(): array {
		$configured = defined( 'OC_TRUSTED_PROXIES' ) ? OC_TRUSTED_PROXIES : [];
		$configured = apply_filters( 'oc_trusted_proxy_ips', $configured );
		if ( is_string( $configured ) ) {
			$configured = preg_split( '/[\s,]+/', $configured, -1, PREG_SPLIT_NO_EMPTY );
		}
		if ( ! is_array( $configured ) ) {
			return [];
		}

		$ranges = [];
		foreach ( $configured as $range ) {
			if ( ! is_string( $range ) || '' === trim( $range ) ) {
				return [];
			}
			$parts  = explode( '/', trim( $range ), 2 );
			$ip     = self::canonical_ip( $parts[0] );
			$packed = '' !== $ip ? @inet_pton( $ip ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$bits   = is_string( $packed ) ? 8 * strlen( $packed ) : 0;
			$prefix = 1 === count( $parts ) ? $bits : ( preg_match( '/^[0-9]+$/D', $parts[1] ) ? (int) $parts[1] : -1 );
			if ( false === $packed || $prefix < 0 || $prefix > $bits ) {
				return [];
			}
			$ranges[] = [ 'network' => $packed, 'prefix' => $prefix ];
		}

		return $ranges;
	}

	/** Match an IP against validated IPv4/IPv6 CIDR entries. */
	private static function ip_matches_any_range( string $ip, array $ranges ): bool {
		$packed = @inet_pton( $ip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( false === $packed ) {
			return false;
		}

		foreach ( $ranges as $range ) {
			$network = $range['network'] ?? '';
			$prefix  = (int) ( $range['prefix'] ?? -1 );
			if ( ! is_string( $network ) || strlen( $network ) !== strlen( $packed ) || $prefix < 0 ) {
				continue;
			}
			$bytes = intdiv( $prefix, 8 );
			$bits  = $prefix % 8;
			if ( $bytes > 0 && substr( $packed, 0, $bytes ) !== substr( $network, 0, $bytes ) ) {
				continue;
			}
			if ( 0 === $bits ) {
				return true;
			}
			$mask = ( 0xff << ( 8 - $bits ) ) & 0xff;
			if ( ( ord( $packed[ $bytes ] ) & $mask ) === ( ord( $network[ $bytes ] ) & $mask ) ) {
				return true;
			}
		}

		return false;
	}

	/** Read a bounded integer filter without coercing malformed values. */
	private static function filtered_limit( string $filter, int $default, int $minimum, int $maximum ): ?int {
		$value = apply_filters( $filter, $default );
		if ( ! is_int( $value ) && ! ( is_string( $value ) && preg_match( '/^[0-9]+$/D', $value ) ) ) {
			return null;
		}
		$value = (int) $value;
		return $value >= $minimum && $value <= $maximum ? $value : null;
	}

	/** Return the current fixed UTC hourly window. */
	private static function hourly_window(): array {
		$start = intdiv( time(), HOUR_IN_SECONDS ) * HOUR_IN_SECONDS;
		return [ $start, $start + HOUR_IN_SECONDS ];
	}

	/** Confirm wp_options can provide transactional row locks for budget updates. */
	private static function options_support_transactions(): bool {
		static $supported = null;
		if ( null !== $supported ) {
			return $supported;
		}

		global $wpdb;
		$engine = $wpdb->get_var( $wpdb->prepare(
			'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s LIMIT 1',
			$wpdb->options
		) );
		$supported = is_string( $engine ) && in_array( strtoupper( $engine ), [ 'INNODB', 'XTRADB' ], true );
		return $supported;
	}

	/** Map a logical security budget to a non-sensitive option key. */
	private static function budget_option_name( string $key ): string {
		return 'oc_budget_' . hash( 'sha256', $key );
	}

	/**
	 * Atomically reserve count/byte capacity across every supplied budget.
	 *
	 * @return array{items:array<string,array>}|\WP_Error
	 */
	private static function reserve_budgets( array $specs ): array|\WP_Error {
		if ( empty( $specs ) ) {
			return [ 'items' => [] ];
		}
		if ( ! self::options_support_transactions() ) {
			OC_Logger::error( 'Security budgets require a transactional wp_options table.' );
			return new \WP_Error( 'security_budget_unavailable', __( 'This request cannot be processed safely right now.', 'overcustomise' ), [ 'status' => 503 ] );
		}

		$prepared = [];
		foreach ( $specs as $spec ) {
			$key          = is_string( $spec['key'] ?? null ) ? $spec['key'] : '';
			$window_start = (int) ( $spec['window_start'] ?? 0 );
			$window_end   = (int) ( $spec['window_end'] ?? 0 );
			$count        = (int) ( $spec['count'] ?? 0 );
			$bytes        = (int) ( $spec['bytes'] ?? 0 );
			$count_limit  = (int) ( $spec['count_limit'] ?? 0 );
			$byte_limit   = (int) ( $spec['byte_limit'] ?? 0 );
			$option_name  = self::budget_option_name( $key );
			if ( '' === $key || $window_start <= 0 || $window_end <= time() || $window_end <= $window_start
				|| $count < 0 || $bytes < 0 || ( 0 === $count && 0 === $bytes )
				|| ( $count > 0 && $count_limit < $count ) || ( $bytes > 0 && $byte_limit < $bytes )
				|| isset( $prepared[ $option_name ] )
			) {
				OC_Logger::error( 'A malformed security budget reservation was rejected.' );
				return new \WP_Error( 'security_budget_unavailable', __( 'This request cannot be processed safely right now.', 'overcustomise' ), [ 'status' => 503 ] );
			}
			$prepared[ $option_name ] = [
				'option_name'  => $option_name,
				'window_start' => $window_start,
				'window_end'   => $window_end,
				'count'        => $count,
				'bytes'        => $bytes,
				'count_limit'  => $count_limit,
				'byte_limit'   => $byte_limit,
				'count_mode'   => (string) ( $spec['count_mode'] ?? 'none' ),
				'bytes_mode'   => (string) ( $spec['bytes_mode'] ?? 'none' ),
				'sliding_window' => ! empty( $spec['sliding_window'] ),
				'error_code'   => sanitize_key( (string) ( $spec['error_code'] ?? 'rate_limited' ) ),
				'error_message' => (string) ( $spec['error_message'] ?? __( 'The request limit has been reached. Please try again later.', 'overcustomise' ) ),
				'error_status' => (int) ( $spec['error_status'] ?? 429 ),
			];
		}
		ksort( $prepared, SORT_STRING );

		global $wpdb;
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			OC_Logger::error( 'Security budget transaction could not start: ' . $wpdb->last_error );
			return new \WP_Error( 'security_budget_unavailable', __( 'This request cannot be processed safely right now.', 'overcustomise' ), [ 'status' => 503 ] );
		}

		$inserted = [];
		$error    = null;
		try {
			foreach ( $prepared as $option_name => $spec ) {
				$result = $wpdb->query( $wpdb->prepare(
					"INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
					$option_name,
					'{}'
				) );
				if ( false === $result ) {
					throw new \RuntimeException( 'A security budget row could not be created: ' . $wpdb->last_error );
				}
				$inserted[ $option_name ] = 1 === $result;
			}

			foreach ( $prepared as $option_name => $spec ) {
				$raw = $wpdb->get_var( $wpdb->prepare(
					"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s FOR UPDATE",
					$option_name
				) );
				$state = is_string( $raw ) ? json_decode( $raw, true ) : null;
				if ( $inserted[ $option_name ] ) {
					$state = [
						'version'      => 1,
						'window_start' => $spec['window_start'],
						'window_end'   => $spec['window_end'],
						'count'        => 0,
						'bytes'        => 0,
						'sliding_window' => $spec['sliding_window'],
					];
				} elseif ( ! is_array( $state )
					|| ! is_int( $state['version'] ?? null ) || 1 !== $state['version']
					|| ! is_int( $state['window_start'] ?? null ) || $state['window_start'] <= 0
					|| ! is_int( $state['window_end'] ?? null ) || $state['window_end'] <= $state['window_start']
					|| ! is_int( $state['count'] ?? null ) || $state['count'] < 0
					|| ! is_int( $state['bytes'] ?? null ) || $state['bytes'] < 0
				) {
					throw new \RuntimeException( 'A persisted security budget row is malformed.' );
				} elseif ( (bool) ( $state['sliding_window'] ?? false ) !== $spec['sliding_window']
					|| (int) $state['window_end'] <= time()
				) {
					$state = [
						'version'      => 1,
						'window_start' => $spec['window_start'],
						'window_end'   => $spec['window_end'],
						'count'        => 0,
						'bytes'        => 0,
						'sliding_window' => $spec['sliding_window'],
					];
				} elseif ( ! $spec['sliding_window']
					&& ( (int) $state['window_start'] !== $spec['window_start'] || (int) $state['window_end'] !== $spec['window_end'] )
				) {
					throw new \RuntimeException( 'An active security budget has an unexpected time window.' );
				}

				$current_count = (int) $state['count'];
				$current_bytes = (int) $state['bytes'];
				if ( ( $spec['count'] > 0 && $current_count > $spec['count_limit'] - $spec['count'] )
					|| ( $spec['bytes'] > 0 && $current_bytes > $spec['byte_limit'] - $spec['bytes'] )
				) {
					$retry_window_end = $spec['sliding_window'] ? (int) $state['window_end'] : $spec['window_end'];
					$retry_after      = max( 1, $retry_window_end - time() );
					$message     = $spec['error_message'];
					if ( 'ai_quota_exceeded' === $spec['error_code'] ) {
						$message = self::ai_quota_retry_message( $retry_after );
					}
					$error = new \WP_Error(
						$spec['error_code'],
						$message,
						[
							'status'      => $spec['error_status'],
							'retry_after' => $retry_after,
						]
					);
					throw new \OverflowException( 'Security budget exhausted.' );
				}

				$state['count'] = $current_count + $spec['count'];
				$state['bytes'] = $current_bytes + $spec['bytes'];
				if ( $spec['sliding_window'] ) {
					$state['window_start'] = time();
					$state['window_end']   = time() + ( $spec['window_end'] - $spec['window_start'] );
				}
				$encoded        = wp_json_encode( $state );
				if ( ! is_string( $encoded ) ) {
					throw new \RuntimeException( 'A security budget row could not be encoded.' );
				}
				$updated = $wpdb->query( $wpdb->prepare(
					"UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s",
					$encoded,
					$option_name
				) );
				if ( 1 !== $updated ) {
					throw new \RuntimeException( 'A security budget row could not be reserved: ' . $wpdb->last_error );
				}
			}

			if ( false === $wpdb->query( 'COMMIT' ) ) {
				throw new \RuntimeException( 'A security budget transaction could not commit: ' . $wpdb->last_error );
			}
		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			if ( null === $error ) {
				OC_Logger::error( 'Security budget reservation failed: ' . $e->getMessage() );
				$error = new \WP_Error( 'security_budget_unavailable', __( 'This request cannot be processed safely right now.', 'overcustomise' ), [ 'status' => 503 ] );
			}
		}

		foreach ( array_keys( $prepared ) as $option_name ) {
			wp_cache_delete( $option_name, 'options' );
		}
		if ( is_wp_error( $error ) ) {
			return $error;
		}

		return [ 'items' => $prepared ];
	}

	/** Atomically release all or part of a prior budget reservation. */
	private static function reduce_budget_reservation( array $reservation, array $reductions ): bool {
		$items = is_array( $reservation['items'] ?? null ) ? $reservation['items'] : [];
		$work  = [];
		foreach ( $items as $option_name => $item ) {
			$reduce = $reductions[ $option_name ] ?? [];
			$count  = max( 0, (int) ( $reduce['count'] ?? 0 ) );
			$bytes  = max( 0, (int) ( $reduce['bytes'] ?? 0 ) );
			if ( $count > 0 || $bytes > 0 ) {
				$work[ $option_name ] = [
					'window_start' => (int) ( $item['window_start'] ?? 0 ),
					'count'        => min( $count, (int) ( $item['count'] ?? 0 ) ),
					'bytes'        => min( $bytes, (int) ( $item['bytes'] ?? 0 ) ),
				];
			}
		}
		if ( empty( $work ) ) {
			return true;
		}
		ksort( $work, SORT_STRING );

		global $wpdb;
		if ( ! self::options_support_transactions() || false === $wpdb->query( 'START TRANSACTION' ) ) {
			OC_Logger::error( 'Security budget release transaction could not start.' );
			return false;
		}

		try {
			foreach ( $work as $option_name => $reduce ) {
				$raw = $wpdb->get_var( $wpdb->prepare(
					"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s FOR UPDATE",
					$option_name
				) );
				$state = is_string( $raw ) ? json_decode( $raw, true ) : null;
				if ( ! is_array( $state ) || ! is_int( $state['version'] ?? null ) || 1 !== $state['version']
					|| ! is_int( $state['window_start'] ?? null ) || ! is_int( $state['count'] ?? null ) || ! is_int( $state['bytes'] ?? null )
					|| $state['count'] < 0 || $state['bytes'] < 0
				) {
					throw new \RuntimeException( 'A reserved security budget row is unavailable.' );
				}
				if ( (int) ( $state['window_start'] ?? 0 ) !== $reduce['window_start'] ) {
					continue;
				}
				$state['count'] = max( 0, (int) ( $state['count'] ?? 0 ) - $reduce['count'] );
				$state['bytes'] = max( 0, (int) ( $state['bytes'] ?? 0 ) - $reduce['bytes'] );
				$encoded        = wp_json_encode( $state );
				$updated        = is_string( $encoded ) ? $wpdb->query( $wpdb->prepare(
					"UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s",
					$encoded,
					$option_name
				) ) : false;
				if ( 1 !== $updated ) {
					throw new \RuntimeException( 'A security budget row could not be released: ' . $wpdb->last_error );
				}
			}
			if ( false === $wpdb->query( 'COMMIT' ) ) {
				throw new \RuntimeException( 'A security budget release could not commit: ' . $wpdb->last_error );
			}
		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			OC_Logger::error( 'Security budget release failed: ' . $e->getMessage() );
			return false;
		} finally {
			foreach ( array_keys( $work ) as $option_name ) {
				wp_cache_delete( $option_name, 'options' );
			}
		}

		return true;
	}

	/** Release an entire failed request reservation. */
	private static function release_budget_reservation( array $reservation ): bool {
		$reductions = [];
		foreach ( (array) ( $reservation['items'] ?? [] ) as $option_name => $item ) {
			$reductions[ $option_name ] = [
				'count' => (int) ( $item['count'] ?? 0 ),
				'bytes' => (int) ( $item['bytes'] ?? 0 ),
			];
		}
		return self::reduce_budget_reservation( $reservation, $reductions );
	}

	/** Ensure actual persisted usage cannot exceed a conservative reservation. */
	private static function reservation_covers_usage( array $reservation, int $attachment_count, int $bytes ): bool {
		foreach ( (array) ( $reservation['items'] ?? [] ) as $item ) {
			$needed_count = match ( $item['count_mode'] ?? 'none' ) {
				'request'     => 1,
				'attachments' => $attachment_count,
				default       => 0,
			};
			$needed_bytes = 'actual' === ( $item['bytes_mode'] ?? 'none' ) ? $bytes : 0;
			if ( $needed_count > (int) ( $item['count'] ?? 0 ) || $needed_bytes > (int) ( $item['bytes'] ?? 0 ) ) {
				return false;
			}
		}
		return true;
	}

	/** Retain actual usage and release the conservative remainder. */
	private static function finalise_budget_reservation( array $reservation, int $attachment_count, int $bytes ): bool {
		if ( ! self::reservation_covers_usage( $reservation, $attachment_count, $bytes ) ) {
			return false;
		}

		$reductions = [];
		foreach ( (array) ( $reservation['items'] ?? [] ) as $option_name => $item ) {
			$keep_count = match ( $item['count_mode'] ?? 'none' ) {
				'request'     => 1,
				'attachments' => $attachment_count,
				default       => 0,
			};
			$keep_bytes = 'actual' === ( $item['bytes_mode'] ?? 'none' ) ? $bytes : 0;
			$reductions[ $option_name ] = [
				'count' => max( 0, (int) ( $item['count'] ?? 0 ) - $keep_count ),
				'bytes' => max( 0, (int) ( $item['bytes'] ?? 0 ) - $keep_bytes ),
			];
		}
		return self::reduce_budget_reservation( $reservation, $reductions );
	}

	/** Build atomic per-IP/site/token storage budgets for uploads or AI results. */
	private static function upload_capacity_specs( int $bytes, int $attachment_count, string $token, ?array $token_state, bool $count_request ): array|\WP_Error {
		$ip = self::client_ip();
		if ( '' === $ip ) {
			return new \WP_Error( 'security_budget_unavailable', __( 'This request cannot be processed safely right now.', 'overcustomise' ), [ 'status' => 503 ] );
		}

		$ip_count_limit = self::filtered_limit( 'oc_artwork_upload_ip_hourly_limit', 60, 1, 10000 );
		$ip_byte_limit  = self::filtered_limit( 'oc_artwork_upload_ip_hourly_bytes', 1073741824, 1048576, 107374182400 );
		$site_byte_limit = self::filtered_limit( 'oc_artwork_upload_site_hourly_bytes', 10737418240, 1048576, 1099511627776 );
		$token_count_limit = self::filtered_limit( 'oc_public_token_attachment_limit', 50, 1, 1000 );
		$token_byte_limit  = self::filtered_limit( 'oc_public_token_attachment_bytes', 536870912, 1048576, 107374182400 );
		if ( null === $ip_count_limit || null === $ip_byte_limit || null === $site_byte_limit
			|| null === $token_count_limit || null === $token_byte_limit
		) {
			OC_Logger::error( 'Artwork security budget filters returned malformed limits.' );
			return new \WP_Error( 'security_budget_unavailable', __( 'Artwork uploads are temporarily unavailable.', 'overcustomise' ), [ 'status' => 503 ] );
		}

		[ $window_start, $window_end ] = self::hourly_window();
		$message = __( 'The artwork upload limit has been reached. Please try again later.', 'overcustomise' );
		$specs   = [
			[
				'key' => 'upload:ip:' . hash( 'sha256', $ip ), 'window_start' => $window_start, 'window_end' => $window_end,
				'count' => $count_request ? 1 : 0, 'bytes' => $bytes, 'count_limit' => $ip_count_limit, 'byte_limit' => $ip_byte_limit,
				'count_mode' => $count_request ? 'request' : 'none', 'bytes_mode' => 'actual', 'error_code' => 'upload_limit_reached', 'error_message' => $message,
			],
			[
				'key' => 'upload:site:' . (int) get_current_blog_id(), 'window_start' => $window_start, 'window_end' => $window_end,
				'count' => 0, 'bytes' => $bytes, 'count_limit' => 0, 'byte_limit' => $site_byte_limit,
				'count_mode' => 'none', 'bytes_mode' => 'actual', 'error_code' => 'upload_limit_reached', 'error_message' => $message,
			],
		];

		if ( '' !== $token ) {
			if ( ! is_array( $token_state ) ) {
				return new \WP_Error( 'invalid_token', __( 'Security verification failed.', 'overcustomise' ), [ 'status' => 403 ] );
			}
			$specs[] = [
				'key' => 'upload:token:' . hash( 'sha256', $token ),
				'window_start' => (int) $token_state['created_at'], 'window_end' => (int) $token_state['expires_at'],
				'count' => $attachment_count, 'bytes' => $bytes, 'count_limit' => $token_count_limit, 'byte_limit' => $token_byte_limit,
				'count_mode' => 'attachments', 'bytes_mode' => 'actual', 'error_code' => 'token_upload_limit_reached',
				'error_message' => __( 'This upload session has reached its artwork limit. Please refresh and try again.', 'overcustomise' ),
			];
		}

		return $specs;
	}

	/** Build atomic preview request/byte budgets. */
	private static function preview_budget_specs( int $bytes ): array|\WP_Error {
		$ip = self::client_ip();
		$ip_count_limit = self::filtered_limit( 'oc_preview_ip_hourly_limit', 30, 1, 10000 );
		$ip_byte_limit  = self::filtered_limit( 'oc_preview_ip_hourly_bytes', 314572800, 1048576, 107374182400 );
		$site_byte_limit = self::filtered_limit( 'oc_preview_site_hourly_bytes', 5368709120, 1048576, 1099511627776 );
		if ( '' === $ip || null === $ip_count_limit || null === $ip_byte_limit || null === $site_byte_limit ) {
			OC_Logger::error( 'Preview security budgets are unavailable or malformed.' );
			return new \WP_Error( 'security_budget_unavailable', __( 'Preview storage is temporarily unavailable.', 'overcustomise' ), [ 'status' => 503 ] );
		}

		[ $window_start, $window_end ] = self::hourly_window();
		$message = __( 'The preview upload limit has been reached. Please try again later.', 'overcustomise' );
		return [
			[
				'key' => 'preview:ip:' . hash( 'sha256', $ip ), 'window_start' => $window_start, 'window_end' => $window_end,
				'count' => 1, 'bytes' => $bytes, 'count_limit' => $ip_count_limit, 'byte_limit' => $ip_byte_limit,
				'count_mode' => 'request', 'bytes_mode' => 'actual', 'error_code' => 'preview_limit_reached', 'error_message' => $message,
			],
			[
				'key' => 'preview:site:' . (int) get_current_blog_id(), 'window_start' => $window_start, 'window_end' => $window_end,
				'count' => 0, 'bytes' => $bytes, 'count_limit' => 0, 'byte_limit' => $site_byte_limit,
				'count_mode' => 'none', 'bytes_mode' => 'actual', 'error_code' => 'preview_limit_reached', 'error_message' => $message,
			],
		];
	}

	/** Build all-or-none paid AI actor/IP/site quota rows. */
	private static function ai_quota_specs( string $actor ): array|\WP_Error {
		if ( current_user_can( 'manage_options' ) ) {
			return [];
		}

		$ip          = self::client_ip();
		$actor_limit = self::filtered_limit( 'oc_ai_filter_actor_hourly_limit', 5, 1, 100 );
		$ip_limit    = self::filtered_limit( 'oc_ai_filter_ip_hourly_limit', 10, 1, 500 );
		$site_limit  = self::filtered_limit( 'oc_ai_filter_site_hourly_limit', 100, 1, 10000 );
		if ( '' === $actor || '' === $ip || null === $actor_limit || null === $ip_limit || null === $site_limit ) {
			OC_Logger::error( 'AI quota configuration is unavailable or malformed.' );
			return new \WP_Error( 'ai_quota_unavailable', __( 'Image processing is temporarily unavailable.', 'overcustomise' ), [ 'status' => 503 ] );
		}

		$window_start = time();
		$window_end   = $window_start + 15 * MINUTE_IN_SECONDS;
		$message = __( 'The image processing limit has been reached. Please try again later.', 'overcustomise' );
		$base    = [
			'window_start' => $window_start, 'window_end' => $window_end, 'count' => 1, 'bytes' => 0, 'byte_limit' => 0,
			'sliding_window' => true,
			'count_mode' => 'request', 'bytes_mode' => 'none', 'error_code' => 'ai_quota_exceeded', 'error_message' => $message,
		];
		return [
			array_merge( $base, [ 'key' => 'ai:actor:' . hash( 'sha256', $actor ), 'count_limit' => $actor_limit ] ),
			array_merge( $base, [ 'key' => 'ai:ip:' . hash( 'sha256', $ip ), 'count_limit' => $ip_limit ] ),
			array_merge( $base, [ 'key' => 'ai:site:' . (int) get_current_blog_id(), 'count_limit' => $site_limit ] ),
		];
	}

	/** Format an AI preview quota reset as customer-friendly rounded-up minutes. */
	private static function ai_quota_retry_message( int $retry_after ): string {
		$minutes = max( 1, (int) ceil( max( 1, $retry_after ) / MINUTE_IN_SECONDS ) );
		return sprintf(
			_n(
				'You have reached the live photo preview limit. You can make more previews in %d minute.',
				'You have reached the live photo preview limit. You can make more previews in %d minutes.',
				$minutes,
				'overcustomise'
			),
			$minutes
		);
	}

	/** Reserve one atomic per-IP request count after cheap validation has passed. */
	private static function reserve_request_rate( string $scope, int $limit, string $message ): array|\WP_Error {
		$ip = self::client_ip();
		if ( '' === $ip || $limit <= 0 ) {
			return new \WP_Error( 'security_budget_unavailable', __( 'This request cannot be processed safely right now.', 'overcustomise' ), [ 'status' => 503 ] );
		}
		[ $window_start, $window_end ] = self::hourly_window();
		return self::reserve_budgets( [ [
			'key' => 'request:' . $scope . ':' . hash( 'sha256', $ip ), 'window_start' => $window_start, 'window_end' => $window_end,
			'count' => 1, 'bytes' => 0, 'count_limit' => $limit, 'byte_limit' => 0,
			'count_mode' => 'request', 'bytes_mode' => 'none', 'error_code' => 'rate_limited', 'error_message' => $message,
		] ] );
	}

	/** Resolve only published, catalog-visible product/variation contexts. */
	private static function public_product_context( int $product_id, int $variation_id = 0, bool $require_variation = false ): array|\WP_Error {
		$product = wc_get_product( $product_id );
		if ( ! $product || $product->is_type( 'variation' ) || ! self::product_is_publicly_visible( $product ) ) {
			return new \WP_Error( 'invalid_product', __( 'This product is not available for customisation.', 'overcustomise' ), [ 'status' => 404 ] );
		}

		$variation = null;
		if ( $variation_id > 0 ) {
			$variation = wc_get_product( $variation_id );
			if ( ! $variation || ! $variation->is_type( 'variation' )
				|| $product_id !== (int) $variation->get_parent_id()
				|| ! self::product_is_publicly_visible( $variation )
			) {
				return new \WP_Error( 'invalid_variation', __( 'The selected variation is not available for this product.', 'overcustomise' ), [ 'status' => 404 ] );
			}
		} elseif ( $require_variation && $product->is_type( 'variable' ) ) {
			return new \WP_Error( 'invalid_variation', __( 'Please select an available product variation.', 'overcustomise' ), [ 'status' => 400 ] );
		}

		return [ 'product' => $product, 'variation' => $variation ];
	}

	/** Require publish status and WooCommerce's public visibility decision. */
	private static function product_is_publicly_visible( object $product ): bool {
		if ( ! method_exists( $product, 'get_status' ) || 'publish' !== (string) $product->get_status() ) {
			return false;
		}
		if ( method_exists( $product, 'get_catalog_visibility' ) && 'hidden' === (string) $product->get_catalog_visibility() ) {
			return false;
		}
		if ( method_exists( $product, 'variation_is_visible' ) && $product->is_type( 'variation' ) && ! $product->variation_is_visible() ) {
			return false;
		}

		return method_exists( $product, 'is_visible' ) && (bool) $product->is_visible();
	}

	/** Resolve an active design that is assigned to an already public product context. */
	private static function active_assignment_design( int $product_id, int $variation_id, int $design_id ): array|\WP_Error {
		$product_context = self::public_product_context( $product_id, $variation_id, true );
		if ( is_wp_error( $product_context ) ) {
			return $product_context;
		}

		$assignment = OC_DB::get_assignment_for_product( $product_id, $variation_id );
		$design     = $design_id > 0 ? OC_DB::get_design( $design_id ) : null;
		if ( ! $assignment || ! OC_DB::assignment_allows_design( $assignment, $design_id ) || ! $design || ! (bool) $design->active ) {
			return new \WP_Error( 'invalid_design', __( 'This customisation design is not available.', 'overcustomise' ), [ 'status' => 404 ] );
		}

		return array_merge( $product_context, [ 'assignment' => $assignment, 'design' => $design ] );
	}

	/** Fetch a visible, editable layer whose containing area is also visible. */
	private static function public_design_layer( int $design_id, int $layer_id, array $eligible_types ): object {
		global $wpdb;
		$layer = $wpdb->get_row( $wpdb->prepare(
			"SELECT l.id, l.design_id, l.area_id, l.type, l.visible, l.locked, l.settings
			 FROM {$wpdb->prefix}oc_design_layers l
			 INNER JOIN {$wpdb->prefix}oc_design_print_areas a ON a.id = l.area_id AND a.design_id = l.design_id
			 INNER JOIN {$wpdb->prefix}oc_designs d ON d.id = l.design_id
			 WHERE l.id = %d AND l.design_id = %d AND l.visible = 1 AND l.locked = 0 AND a.visible = 1 AND d.active = 1
			 LIMIT 1",
			$layer_id,
			$design_id
		) );
		if ( ! $layer || ! in_array( (string) $layer->type, $eligible_types, true ) ) {
			return new \WP_Error( 'invalid_layer', __( 'This customisation layer is not available.', 'overcustomise' ), [ 'status' => 404 ] );
		}

		return $layer;
	}

	/** Confirm preloaded variant state cannot contain hidden areas or layers. */
	private static function design_state_is_fully_public( int $design_id ): bool {
		global $wpdb;
		$counts = $wpdb->get_row( $wpdb->prepare(
			"SELECT
				(SELECT COUNT(*) FROM {$wpdb->prefix}oc_design_print_areas WHERE design_id = %d) AS area_count,
				(SELECT COUNT(*) FROM {$wpdb->prefix}oc_design_print_areas WHERE design_id = %d AND visible = 1) AS visible_area_count,
				(SELECT COUNT(*) FROM {$wpdb->prefix}oc_design_layers WHERE design_id = %d) AS layer_count,
				(SELECT COUNT(*) FROM {$wpdb->prefix}oc_design_layers l
				 INNER JOIN {$wpdb->prefix}oc_design_print_areas a ON a.id = l.area_id AND a.design_id = l.design_id
				 WHERE l.design_id = %d AND l.visible = 1 AND a.visible = 1) AS visible_layer_count",
			$design_id,
			$design_id,
			$design_id,
			$design_id
		) );
		return $counts
			&& (int) $counts->area_count > 0
			&& (int) $counts->layer_count > 0
			&& (int) $counts->area_count === (int) $counts->visible_area_count
			&& (int) $counts->layer_count === (int) $counts->visible_layer_count;
	}

	/** Decode a layer settings JSON object, rejecting malformed or oversized data. */
	private static function decode_layer_settings( mixed $raw ): array|\WP_Error {
		if ( null === $raw || '' === $raw ) {
			return [];
		}
		if ( ! is_string( $raw ) || strlen( $raw ) > 65535 || ! str_starts_with( ltrim( $raw ), '{' ) ) {
			return new \WP_Error( 'invalid_layer_settings', __( 'This customisation layer is not configured correctly.', 'overcustomise' ), [ 'status' => 503 ] );
		}

		try {
			$settings = json_decode( $raw, true, 32, JSON_THROW_ON_ERROR );
		} catch ( \JsonException $e ) {
			OC_Logger::warning( 'A public request rejected malformed layer settings: ' . $e->getMessage() );
			return new \WP_Error( 'invalid_layer_settings', __( 'This customisation layer is not configured correctly.', 'overcustomise' ), [ 'status' => 503 ] );
		}
		if ( ! is_array( $settings ) ) {
			return new \WP_Error( 'invalid_layer_settings', __( 'This customisation layer is not configured correctly.', 'overcustomise' ), [ 'status' => 503 ] );
		}

		return $settings;
	}

	/** Strictly parse a positive integer setting. */
	private static function setting_positive_int( mixed $value, int $maximum ): ?int {
		if ( ! is_int( $value ) && ! ( is_string( $value ) && preg_match( '/^[0-9]+$/D', $value ) ) ) {
			return null;
		}
		$value = (int) $value;
		return $value > 0 && $value <= $maximum ? $value : null;
	}

	/** Strictly parse a stored boolean setting. */
	private static function setting_boolean( mixed $value ): ?bool {
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( in_array( $value, [ 0, '0' ], true ) ) {
			return false;
		}
		if ( in_array( $value, [ 1, '1' ], true ) ) {
			return true;
		}
		return null;
	}

	/** Validate a non-empty upload extension allowlist. */
	private static function upload_format_allowlist( mixed $formats ): ?array {
		if ( ! is_array( $formats ) || empty( $formats ) ) {
			return null;
		}

		$allowed = [ 'svg', 'pdf', 'eps', 'png', 'jpg', 'jpeg', 'webp', 'heic', 'heif' ];
		$output  = [];
		foreach ( $formats as $format ) {
			if ( ! is_string( $format ) ) {
				return null;
			}
			$format = ltrim( strtolower( trim( $format ) ), '.' );
			if ( ! in_array( $format, $allowed, true ) ) {
				return null;
			}
			$output[] = $format;
		}

		return array_values( array_unique( $output ) ) ?: null;
	}

	/** Build fail-closed upload overrides from global and layer settings. */
	private static function upload_layer_overrides( object $layer ): array|\WP_Error {
		$settings = self::decode_layer_settings( $layer->settings ?? null );
		if ( is_wp_error( $settings ) ) {
			return $settings;
		}

		$global_formats = self::upload_format_allowlist( OC_Admin_Settings::get( 'allowed_upload_formats' ) );
		$global_max     = self::setting_positive_int( OC_Admin_Settings::get( 'max_upload_size_mb' ), 100 );
		if ( null === $global_formats || null === $global_max ) {
			OC_Logger::error( 'Global artwork upload settings are empty or malformed.' );
			return new \WP_Error( 'invalid_upload_settings', __( 'Artwork uploads are temporarily unavailable.', 'overcustomise' ), [ 'status' => 503 ] );
		}

		$formats = $global_formats;
		if ( array_key_exists( 'formats', $settings ) ) {
			$layer_formats = self::upload_format_allowlist( $settings['formats'] );
			if ( null === $layer_formats ) {
				return new \WP_Error( 'invalid_upload_settings', __( 'Artwork uploads are not enabled for this layer.', 'overcustomise' ), [ 'status' => 400 ] );
			}
			$formats = array_values( array_intersect( $global_formats, $layer_formats ) );
			if ( empty( $formats ) ) {
				return new \WP_Error( 'invalid_upload_settings', __( 'Artwork uploads are not enabled for this layer.', 'overcustomise' ), [ 'status' => 400 ] );
			}
		}

		$max_size = $global_max;
		if ( array_key_exists( 'max_size_mb', $settings ) ) {
			$layer_max = self::setting_positive_int( $settings['max_size_mb'], 100 );
			if ( null === $layer_max ) {
				return new \WP_Error( 'invalid_upload_settings', __( 'This customisation layer is not configured correctly.', 'overcustomise' ), [ 'status' => 503 ] );
			}
			$max_size = min( $global_max, $layer_max );
		}

		$allow_change = array_key_exists( 'allow_image_change', $settings ) ? self::setting_boolean( $settings['allow_image_change'] ) : true;
		$remove_bg    = array_key_exists( 'remove_background', $settings ) ? self::setting_boolean( $settings['remove_background'] ) : false;
		if ( null === $allow_change || null === $remove_bg ) {
			return new \WP_Error( 'invalid_upload_settings', __( 'This customisation layer is not configured correctly.', 'overcustomise' ), [ 'status' => 503 ] );
		}
		if ( ! $allow_change ) {
			return new \WP_Error( 'image_change_locked', __( 'The image is fixed for this design.', 'overcustomise' ), [ 'status' => 400 ] );
		}

		return [ 'formats' => $formats, 'max_size_mb' => $max_size, 'remove_background' => $remove_bg ];
	}

	// -------------------------------------------------------------------------
	// Handlers
	// -------------------------------------------------------------------------

	/**
	 * Return the design ID (and active state) for a product / variation.
	 * Used by the frontend JS to detect design changes on variation switch.
	 */
	public function get_product_design( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$product_id = absint( $request->get_param( 'product_id' ) );
		$variant_id = (int) $request->get_param( 'variant_id' );

		$product_context = self::public_product_context( $product_id, $variant_id, false );
		if ( is_wp_error( $product_context ) ) {
			return $product_context;
		}

		try {
			$state = OC_Frontend::build_assignment_state( (int) $product_id, $variant_id );
		} catch ( \Throwable $e ) {
			OC_Logger::error( 'Public product design state could not be built: ' . $e->getMessage() );
			return new \WP_Error( 'design_unavailable', __( 'This customisation is temporarily unavailable.', 'overcustomise' ), [ 'status' => 503 ] );
		}
		if ( is_wp_error( $state ) ) {
			return $state;
		}
		if ( empty( $state['active'] ) ) {
			return rest_ensure_response( [ 'design_id' => 0, 'active' => false ] );
		}

		// OC_Frontend preloads alternate states. Retain only variants whose entire
		// preloaded payload is public, since hidden rows cannot be safely trimmed out
		// of their rendered panel HTML here.
		$assignment       = OC_DB::get_assignment_for_product( $product_id, $variant_id );
		$public_variants  = [];
		$public_state_ids = [];
		foreach ( is_array( $state['designVariants'] ?? null ) ? $state['designVariants'] : [] as $variant ) {
			$variant_design_id = absint( $variant['designId'] ?? 0 );
			$variant_state_id  = is_string( $variant['id'] ?? null ) ? $variant['id'] : '';
			$variant_design    = $variant_design_id ? OC_DB::get_design( $variant_design_id ) : null;
			if ( '' === $variant_state_id || ! $assignment || ! OC_DB::assignment_allows_design( $assignment, $variant_design_id )
				|| ! $variant_design || ! (bool) $variant_design->active || ! self::design_state_is_fully_public( $variant_design_id )
			) {
				continue;
			}
			$public_variants[]                     = $variant;
			$public_state_ids[ $variant_state_id ] = true;
		}
		$state['designVariants'] = $public_variants;
		if ( isset( $state['designVariantStates'] ) ) {
			$state['designVariantStates'] = array_intersect_key(
				is_array( $state['designVariantStates'] ) ? $state['designVariantStates'] : [],
				$public_state_ids
			);
		}

		return rest_ensure_response( $state );
	}

	/** Return the active product config, print areas, and font list for the customiser. */
	public function get_product_config( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$product_id = absint( $request->get_param( 'product_id' ) );
		$product     = self::public_product_context( $product_id );
		if ( is_wp_error( $product ) ) {
			return $product;
		}

		$config     = OC_DB::get_config_by_product( $product_id );

		if ( ! $config || ! $config->active ) {
			return new \WP_Error( 'not_found', __( 'No active customisation config for this product.', 'overcustomise' ), [ 'status' => 404 ] );
		}

		$areas           = OC_DB::get_print_areas( (int) $config->id );
		$allowed_formats = self::upload_format_allowlist( OC_Admin_Settings::get( 'allowed_upload_formats' ) );
		$max_upload_mb   = self::setting_positive_int( OC_Admin_Settings::get( 'max_upload_size_mb' ), 100 );
		if ( empty( $areas ) || null === $allowed_formats || null === $max_upload_mb ) {
			OC_Logger::warning( 'A public product config was rejected because its areas or upload settings are invalid.' );
			return new \WP_Error( 'invalid_config', __( 'This customisation is temporarily unavailable.', 'overcustomise' ), [ 'status' => 503 ] );
		}

		$areas_out = array_map( function( $area ) {
			$mockup_id     = absint( $area->mockup_attachment_id ?? 0 );
			$mockup_status = $mockup_id ? get_post_status( $mockup_id ) : false;
			$mockup_url    = $mockup_id && in_array( $mockup_status, [ 'inherit', 'publish' ], true )
				? wp_get_attachment_image_url( $mockup_id, 'full' )
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
			'allowed_formats'    => $allowed_formats,
			'max_upload_size_mb' => $max_upload_mb,
		] );
	}

	/** Return all active fonts. */
	public function get_fonts( \WP_REST_Request $request ): \WP_REST_Response {
		return rest_ensure_response( OC_Font_Registry::get_fonts_for_js() );
	}

	/** Handle customer artwork upload. */
	public function upload_artwork( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$auth = $this->verify_public_write_auth( $request );
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}

		$files = $request->get_file_params();
		if ( empty( $files['artwork'] ) || ! is_array( $files['artwork'] ) ) {
			return new \WP_Error( 'no_file', __( 'No file received.', 'overcustomise' ), [ 'status' => 400 ] );
		}

		$layer_id     = absint( $request->get_param( 'layer_id' ) );
		$design_id    = absint( $request->get_param( 'design_id' ) );
		$product_id   = absint( $request->get_param( 'product_id' ) );
		$variation_id = absint( $request->get_param( 'variation_id' ) );
		if ( ! $layer_id || ! $design_id || ! $product_id ) {
			return new \WP_Error( 'invalid_context', __( 'Product, design, and layer are required for artwork uploads.', 'overcustomise' ), [ 'status' => 400 ] );
		}

		$context = self::active_assignment_design( $product_id, $variation_id, $design_id );
		if ( is_wp_error( $context ) ) {
			return $context;
		}
		$layer = self::public_design_layer( $design_id, $layer_id, [ 'image', 'clipmask' ] );
		if ( is_wp_error( $layer ) ) {
			return $layer;
		}
		$layer_overrides = self::upload_layer_overrides( $layer );
		if ( is_wp_error( $layer_overrides ) ) {
			return $layer_overrides;
		}

		$token       = trim( (string) $request->get_header( 'X-OC-Token' ) );
		$token_state = '' !== $token ? self::public_token_state( $token ) : null;
		if ( '' !== $token && null === $token_state ) {
			if ( ! is_user_logged_in() ) {
				return new \WP_Error( 'invalid_token', __( 'Security verification failed.', 'overcustomise' ), [ 'status' => 403 ] );
			}
			$token = '';
		}

		try {
			$inspection = OC_Upload_Handler::inspect_upload( $files['artwork'], $layer_overrides );
		} catch ( \Throwable $e ) {
			OC_Logger::warning( 'Artwork upload validation failed: ' . $e->getMessage() );
			return new \WP_Error( 'upload_failed', __( 'The artwork file could not be accepted. Please check it and try again.', 'overcustomise' ), [ 'status' => 422 ] );
		}

		$specs = self::upload_capacity_specs(
			(int) $inspection['reservation_bytes'],
			(int) $inspection['attachment_count'],
			$token,
			$token_state,
			true
		);
		if ( is_wp_error( $specs ) ) {
			return $specs;
		}
		$reservation = self::reserve_budgets( $specs );
		if ( is_wp_error( $reservation ) ) {
			return $reservation;
		}

		try {
			$result = OC_Upload_Handler::process( $files['artwork'], $layer_overrides, [
				'product_id'   => $product_id,
				'variation_id' => $variation_id,
				'design_id'    => $design_id,
				'layer_id'     => $layer_id,
				'token_hash'   => $token ? hash( 'sha256', $token ) : '',
			] );
		} catch ( \Throwable $e ) {
			self::finalise_budget_reservation( $reservation, 0, 0 );
			OC_Logger::warning( 'Artwork upload failed: ' . $e->getMessage() );
			return new \WP_Error( 'upload_failed', __( 'The artwork file could not be processed. Please check it and try again.', 'overcustomise' ), [ 'status' => 422 ] );
		}

		$usage            = OC_Upload_Handler::result_attachment_usage( $result );
		$attachment_count = count( $usage );
		$actual_bytes     = array_sum( $usage );
		if ( empty( $usage )
			|| ! self::reservation_covers_usage( $reservation, $attachment_count, $actual_bytes )
			|| ( '' !== $token && ! self::validate_public_token( $token ) )
		) {
			OC_Upload_Handler::delete_result_attachments( $result );
			self::finalise_budget_reservation( $reservation, 0, 0 );
			OC_Logger::error( 'Artwork upload output exceeded its reservation or lost token ownership before commit.' );
			return new \WP_Error( 'upload_failed', __( 'The artwork file could not be retained safely. Please try again.', 'overcustomise' ), [ 'status' => 422 ] );
		}
		if ( ! self::finalise_budget_reservation( $reservation, $attachment_count, $actual_bytes ) ) {
			// The conservative reservation remains charged if reconciliation fails.
			OC_Logger::error( 'Artwork upload budget could not be reconciled to actual stored bytes.' );
		}

		return rest_ensure_response( $result );
	}

	/** Authorise an owned image for a matching link group in another product context. */
	public function authorise_artwork_context( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$auth = $this->verify_public_write_auth( $request );
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}

		$body                     = $request->get_json_params();
		$body                     = is_array( $body ) ? $body : [];
		$source_attachment_id     = absint( $body['source_attachment_id'] ?? 0 );
		$derivative_attachment_id = absint( $body['derivative_attachment_id'] ?? 0 );
		$product_id               = absint( $body['product_id'] ?? 0 );
		$variation_id             = absint( $body['variation_id'] ?? 0 );
		$design_id                = absint( $body['design_id'] ?? 0 );
		$layer_id                 = absint( $body['layer_id'] ?? 0 );
		if ( ! $source_attachment_id || ! $product_id || ! $design_id || ! $layer_id ) {
			return new \WP_Error( 'invalid_context', __( 'Image, product, design, and layer are required.', 'overcustomise' ), [ 'status' => 400 ] );
		}
		$limit = self::filtered_limit( 'oc_artwork_authorisation_ip_hourly_limit', 120, 1, 10000 );
		if ( null === $limit ) {
			return new \WP_Error( 'security_budget_unavailable', __( 'This request cannot be processed safely right now.', 'overcustomise' ), [ 'status' => 503 ] );
		}
		$reservation = self::reserve_request_rate( 'artwork-authorisation', $limit, __( 'Too many image reuse requests. Please try again later.', 'overcustomise' ) );
		if ( is_wp_error( $reservation ) ) {
			return $reservation;
		}

		$destination = self::active_assignment_design( $product_id, $variation_id, $design_id );
		if ( is_wp_error( $destination ) ) {
			return $destination;
		}
		$target_layer = self::public_design_layer( $design_id, $layer_id, [ 'image', 'clipmask' ] );
		if ( is_wp_error( $target_layer ) ) {
			return $target_layer;
		}
		$target_policy = self::upload_layer_overrides( $target_layer );
		if ( is_wp_error( $target_policy ) ) {
			return $target_policy;
		}

		$source_context = OC_Upload_Handler::attachment_primary_context( $source_attachment_id );
		$token          = trim( (string) $request->get_header( 'X-OC-Token' ) );
		if ( null === $source_context || $source_context[0] !== $product_id
			|| ! OC_Upload_Handler::attachment_is_accepted( $source_attachment_id, ...array_merge( $source_context, [ $token ] ) )
			|| ! OC_Upload_Handler::attachment_matches_upload_policy( $source_attachment_id, $target_policy, false )
		) {
			return new \WP_Error( 'invalid_attachment', __( 'The image is not valid for this customisation.', 'overcustomise' ), [ 'status' => 400 ] );
		}

		$source_assignment = self::active_assignment_design( $source_context[0], $source_context[1], $source_context[2] );
		$source_layer      = is_wp_error( $source_assignment ) ? $source_assignment : self::public_design_layer( $source_context[2], $source_context[3], [ 'image', 'clipmask' ] );
		if ( is_wp_error( $source_layer ) || (string) $source_layer->type !== (string) $target_layer->type ) {
			return new \WP_Error( 'invalid_link_group', __( 'This image cannot be shared with the selected design.', 'overcustomise' ), [ 'status' => 400 ] );
		}
		$source_settings = self::decode_layer_settings( $source_layer->settings ?? null );
		$target_settings = self::decode_layer_settings( $target_layer->settings ?? null );
		if ( is_wp_error( $source_settings ) || is_wp_error( $target_settings ) ) {
			return is_wp_error( $source_settings ) ? $source_settings : $target_settings;
		}
		$source_group = sanitize_key( (string) ( $source_settings['link_group'] ?? '' ) );
		$target_group = sanitize_key( (string) ( $target_settings['link_group'] ?? '' ) );
		if ( '' === $source_group || $source_group !== $target_group ) {
			return new \WP_Error( 'invalid_link_group', __( 'This image cannot be shared with the selected design.', 'overcustomise' ), [ 'status' => 400 ] );
		}
		$source_policy = self::upload_layer_overrides( $source_layer );
		if ( is_wp_error( $source_policy ) || (bool) $source_policy['remove_background'] !== (bool) $target_policy['remove_background'] ) {
			return new \WP_Error( 'incompatible_image_processing', __( 'This image must be uploaded separately for the selected design.', 'overcustomise' ), [ 'status' => 400 ] );
		}

		if ( $derivative_attachment_id && $derivative_attachment_id !== $source_attachment_id ) {
			$derivative_context = OC_Upload_Handler::attachment_primary_context( $derivative_attachment_id );
			$filter_id          = absint( get_post_meta( $derivative_attachment_id, '_oc_ai_filter_id', true ) );
			$allowed_filters    = array_values( array_filter( array_map( 'absint', (array) ( $target_settings['image_filter_ids'] ?? [] ) ) ) );
			$can_change_filter  = ! array_key_exists( 'allow_image_filter_change', $target_settings ) || true === self::setting_boolean( $target_settings['allow_image_filter_change'] );
			$default_filter_id  = absint( $target_settings['default_image_filter_id'] ?? 0 );
			$filter_permitted   = $can_change_filter || $default_filter_id === $filter_id;
			$active_ai_filter   = false;
			foreach ( OC_DB::get_image_filters( true ) as $filter ) {
				if ( (int) $filter->id === $filter_id && 'ai' === (string) $filter->filter_key ) {
					$active_ai_filter = true;
					break;
				}
			}
			if ( 'image' !== (string) $target_layer->type || null === $derivative_context || $derivative_context[0] !== $product_id
				|| ! OC_Upload_Handler::attachment_is_accepted( $derivative_attachment_id, ...array_merge( $derivative_context, [ $token ] ) )
				|| ! OC_Upload_Handler::attachment_matches_upload_policy( $derivative_attachment_id, $target_policy, false )
				|| 1 !== (int) get_post_meta( $derivative_attachment_id, '_oc_ai_filter', true )
				|| $source_attachment_id !== absint( get_post_meta( $derivative_attachment_id, '_oc_ai_filter_source_id', true ) )
				|| ! $filter_id || ! $filter_permitted || ! in_array( $filter_id, $allowed_filters, true ) || ! $active_ai_filter
			) {
				return new \WP_Error( 'invalid_attachment', __( 'The filtered image is not valid for the selected design.', 'overcustomise' ), [ 'status' => 400 ] );
			}
		}

		$target_context = [ $product_id, $variation_id, $design_id, $layer_id ];
		if ( ! OC_Upload_Handler::authorise_attachment_context( $source_attachment_id, $target_context ) ) {
			return new \WP_Error( 'authorisation_failed', __( 'The image could not be prepared for this design.', 'overcustomise' ), [ 'status' => 503 ] );
		}
		if ( $derivative_attachment_id && $derivative_attachment_id !== $source_attachment_id
			&& ! OC_Upload_Handler::authorise_attachment_context( $derivative_attachment_id, $target_context )
		) {
			return new \WP_Error( 'authorisation_failed', __( 'The filtered image could not be prepared for this design.', 'overcustomise' ), [ 'status' => 503 ] );
		}

		return rest_ensure_response( [
			'source_attachment_id'     => $source_attachment_id,
			'derivative_attachment_id' => $derivative_attachment_id,
			'context'                  => [ 'product_id' => $product_id, 'variation_id' => $variation_id, 'design_id' => $design_id, 'layer_id' => $layer_id ],
		] );
	}

	/** Apply an allowed AI filter and persist the result as owned artwork. */
	public function apply_image_filter( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$auth = $this->verify_public_write_auth( $request );
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}

		$body                 = $request->get_json_params();
		$body                 = is_array( $body ) ? $body : [];
		$source_attachment_id = absint( $body['source_attachment_id'] ?? 0 );
		$filter_id            = absint( $body['filter_id'] ?? 0 );
		$layer_id             = absint( $body['layer_id'] ?? 0 );
		$design_id            = absint( $body['design_id'] ?? 0 );
		$product_id           = absint( $body['product_id'] ?? 0 );
		$variation_id         = absint( $body['variation_id'] ?? 0 );
		if ( ! $source_attachment_id || ! $filter_id || ! $layer_id || ! $design_id || ! $product_id ) {
			return new \WP_Error( 'invalid_context', __( 'Image, filter, product, design, and layer are required.', 'overcustomise' ), [ 'status' => 400 ] );
		}

		$context = self::active_assignment_design( $product_id, $variation_id, $design_id );
		if ( is_wp_error( $context ) ) {
			return $context;
		}
		$layer = self::public_design_layer( $design_id, $layer_id, [ 'image' ] );
		if ( is_wp_error( $layer ) ) {
			return $layer;
		}
		$settings = self::decode_layer_settings( $layer->settings ?? null );
		if ( is_wp_error( $settings ) ) {
			return $settings;
		}
		if ( ! is_array( $settings['image_filter_ids'] ?? null ) || empty( $settings['image_filter_ids'] ) ) {
			return new \WP_Error( 'invalid_filter_settings', __( 'Image filters are not configured for this layer.', 'overcustomise' ), [ 'status' => 503 ] );
		}
		$allowed_ids = [];
		foreach ( $settings['image_filter_ids'] as $allowed_id ) {
			$allowed_id = self::setting_positive_int( $allowed_id, PHP_INT_MAX );
			if ( null === $allowed_id ) {
				return new \WP_Error( 'invalid_filter_settings', __( 'Image filters are not configured for this layer.', 'overcustomise' ), [ 'status' => 503 ] );
			}
			$allowed_ids[] = $allowed_id;
		}
		$allowed_ids = array_values( array_unique( $allowed_ids ) );
		$default_id  = 0;
		if ( array_key_exists( 'default_image_filter_id', $settings ) ) {
			$raw_default = $settings['default_image_filter_id'];
			if ( ! in_array( $raw_default, [ 0, '0' ], true ) ) {
				$default_id = self::setting_positive_int( $raw_default, PHP_INT_MAX );
				if ( null === $default_id ) {
					return new \WP_Error( 'invalid_filter_settings', __( 'Image filters are not configured for this layer.', 'overcustomise' ), [ 'status' => 503 ] );
				}
			}
		}
		$can_change = array_key_exists( 'allow_image_filter_change', $settings ) ? self::setting_boolean( $settings['allow_image_filter_change'] ) : true;
		if ( null === $can_change ) {
			return new \WP_Error( 'invalid_filter_settings', __( 'Image filters are not configured for this layer.', 'overcustomise' ), [ 'status' => 503 ] );
		}
		$effective_id = $can_change ? $filter_id : $default_id;
		if ( $effective_id !== $filter_id || ! in_array( $filter_id, $allowed_ids, true ) ) {
			return new \WP_Error( 'filter_not_allowed', __( 'This filter is not available for the selected design.', 'overcustomise' ), [ 'status' => 400 ] );
		}

		$filter = null;
		foreach ( OC_DB::get_image_filters( true ) as $candidate ) {
			if ( (int) $candidate->id === $filter_id ) {
				$filter = $candidate;
				break;
			}
		}
		$prompt = $filter ? trim( (string) ( $filter->prompt ?? '' ) ) : '';
		if ( ! $filter || 'ai' !== (string) $filter->filter_key || '' === $prompt || strlen( $prompt ) > self::MAX_AI_PROMPT_BYTES
			|| wp_check_invalid_utf8( $prompt, true ) !== $prompt
		) {
			return new \WP_Error( 'invalid_filter', __( 'This image effect is unavailable.', 'overcustomise' ), [ 'status' => 400 ] );
		}

		$api_key = OC_Admin_Settings::get_openrouter_api_key();
		$model   = trim( OC_Admin_Settings::get_openrouter_image_model() );
		if ( '' === $api_key || $api_key !== trim( $api_key ) || strlen( $api_key ) > 4096 || ! preg_match( '/^[A-Za-z0-9._:-]+$/D', $api_key )
			|| ! preg_match( '#^[A-Za-z0-9._:-]+/[A-Za-z0-9._:-]+$#D', $model ) || strlen( $model ) > 200
		) {
			OC_Logger::warning( 'AI image filtering was requested with unavailable or malformed provider configuration.' );
			return new \WP_Error( 'ai_unavailable', __( 'Image processing is temporarily unavailable.', 'overcustomise' ), [ 'status' => 503 ] );
		}

		$token       = trim( (string) $request->get_header( 'X-OC-Token' ) );
		$token_state = '' !== $token ? self::public_token_state( $token ) : null;
		if ( '' !== $token && null === $token_state ) {
			if ( ! is_user_logged_in() ) {
				return new \WP_Error( 'invalid_token', __( 'Security verification failed.', 'overcustomise' ), [ 'status' => 403 ] );
			}
			$token = '';
		}

		$default_attachment_id = 0;
		if ( array_key_exists( 'default_attachment_id', $settings ) && ! in_array( $settings['default_attachment_id'], [ 0, '0', '' ], true ) ) {
			$default_attachment_id = self::setting_positive_int( $settings['default_attachment_id'], PHP_INT_MAX );
			if ( null === $default_attachment_id ) {
				return new \WP_Error( 'invalid_filter_settings', __( 'Image filters are not configured for this layer.', 'overcustomise' ), [ 'status' => 503 ] );
			}
		}
		$source_is_default = $source_attachment_id === $default_attachment_id
			&& OC_Upload_Handler::admin_default_attachment_is_valid( $source_attachment_id )
			&& OC_Upload_Handler::ai_source_is_valid( $source_attachment_id, false );
		$source_is_owned = ! $source_is_default
			&& OC_Upload_Handler::attachment_is_accepted( $source_attachment_id, $product_id, $variation_id, $design_id, $layer_id, $token )
			&& OC_Upload_Handler::ai_source_is_valid( $source_attachment_id, true );
		if ( ! $source_is_default && ! $source_is_owned ) {
			return new \WP_Error( 'invalid_attachment', __( 'The source image is not valid for this customisation.', 'overcustomise' ), [ 'status' => 400 ] );
		}

		$actor = is_user_logged_in()
			? 'user:' . get_current_user_id()
			: (string) $token_state['binding_type'] . ':' . (string) $token_state['binding_hash'];
		$fingerprint = OC_Upload_Handler::attachment_fingerprint( $source_attachment_id );
		if ( '' === $fingerprint ) {
			return new \WP_Error( 'invalid_attachment', __( 'The source image could not be identified.', 'overcustomise' ), [ 'status' => 400 ] );
		}
		$group = hash( 'sha256', implode( '|', [
			$actor, $fingerprint, $product_id, $variation_id, $design_id, $layer_id, $filter_id,
		] ) );
		$attempt_key = 'oc_ai_filter_attempt_' . $group;
		$results = self::image_filter_results( $group, $source_attachment_id, $product_id, $variation_id, $design_id, $layer_id, $token );
		if ( ! empty( $body['list_only'] ) ) {
			$attempt_count = max( count( $results ), absint( get_transient( $attempt_key ) ) );
			return rest_ensure_response( self::image_filter_result_payload( $results, $attempt_count ) );
		}
		$quota_specs = self::ai_quota_specs( $actor );
		if ( is_wp_error( $quota_specs ) ) {
			return $quota_specs;
		}
		$storage_specs = self::upload_capacity_specs( self::MAX_AI_RESULT_BYTES, 1, $token, $token_state, false );
		if ( is_wp_error( $storage_specs ) ) {
			return $storage_specs;
		}

		$lock_key   = 'oc_ai_filter_lock_' . $group;
		$lock_owner = self::acquire_option_lock( $lock_key, self::AI_LOCK_TTL );
		if ( is_wp_error( $lock_owner ) ) {
			return new \WP_Error( 'ai_filter_in_progress', __( 'This image effect is already being applied.', 'overcustomise' ), [ 'status' => 409 ] );
		}

		try {
			$results       = self::image_filter_results( $group, $source_attachment_id, $product_id, $variation_id, $design_id, $layer_id, $token );
			$attempt_count = max( count( $results ), absint( get_transient( $attempt_key ) ) );
			if ( $attempt_count >= self::MAX_AI_FILTER_ATTEMPTS ) {
				return new \WP_Error(
					'ai_filter_attempt_limit',
					__( 'You have used both retries for this image effect.', 'overcustomise' ),
					array_merge( [ 'status' => 429 ], self::image_filter_result_payload( $results, $attempt_count ) )
				);
			}
			$storage_reservation = self::reserve_budgets( $storage_specs );
			if ( is_wp_error( $storage_reservation ) ) {
				return $storage_reservation;
			}
			$quota_reservation = self::reserve_budgets( $quota_specs );
			if ( is_wp_error( $quota_reservation ) ) {
				self::release_budget_reservation( $storage_reservation );
				return $quota_reservation;
			}
			$attempt = $attempt_count + 1;
			$retention_days = max( 1, (int) OC_Admin_Settings::get( 'artwork_retention_days' ) ?: 90 );
			if ( ! set_transient( $attempt_key, $attempt, $retention_days * DAY_IN_SECONDS ) ) {
				self::release_budget_reservation( $storage_reservation );
				self::release_budget_reservation( $quota_reservation );
				return new \WP_Error( 'ai_attempt_unavailable', __( 'Image processing is temporarily unavailable.', 'overcustomise' ), [ 'status' => 503 ] );
			}

			try {
				$generated = OC_AI_Image_Filter::generate( $source_attachment_id, $prompt );
			} catch ( \Throwable $e ) {
				self::release_budget_reservation( $storage_reservation );
				OC_Logger::error( 'AI image generation threw an exception after quota reservation: ' . $e->getMessage() );
				return new \WP_Error( 'ai_generation_failed', __( 'The image could not be processed. Please try again.', 'overcustomise' ), [ 'status' => 503 ] );
			}
			if ( is_wp_error( $generated ) ) {
				self::release_budget_reservation( $storage_reservation );
				OC_Logger::warning( 'AI image generation failed after quota reservation: ' . $generated->get_error_code() . ' - ' . $generated->get_error_message() );
				$status = match ( $generated->get_error_code() ) {
					'openrouter_rate_limited' => 429,
					'openrouter_unavailable'  => 503,
					default                   => 422,
				};
				$message = 429 === $status
					? __( 'Image processing is busy. Please try again shortly.', 'overcustomise' )
					: __( 'The image could not be processed. Please try again.', 'overcustomise' );
				return new \WP_Error( 'ai_generation_failed', $message, [ 'status' => $status ] );
			}

			try {
				$result = OC_Upload_Handler::save_generated_image(
					is_string( $generated['bytes'] ?? null ) ? $generated['bytes'] : '',
					is_string( $generated['mime'] ?? null ) ? $generated['mime'] : '',
					[
						'product_id' => $product_id, 'variation_id' => $variation_id, 'design_id' => $design_id,
						'layer_id' => $layer_id, 'token_hash' => $token ? hash( 'sha256', $token ) : '',
					],
					[
						'source_attachment_id' => $source_attachment_id, 'filter_id' => $filter_id,
						'attempt' => $attempt, 'group' => $group, 'model' => (string) ( $generated['model'] ?? $model ),
					],
					! empty( $filter->remove_background )
				);
			} catch ( \Throwable $e ) {
				self::release_budget_reservation( $storage_reservation );
				OC_Logger::error( 'AI-generated image storage threw an exception: ' . $e->getMessage() );
				return new \WP_Error( 'generated_image_save_failed', __( 'The generated image could not be retained safely.', 'overcustomise' ), [ 'status' => 422 ] );
			}
			if ( is_wp_error( $result ) ) {
				self::release_budget_reservation( $storage_reservation );
				OC_Logger::error( 'AI-generated image storage failed: ' . $result->get_error_code() . ' - ' . $result->get_error_message() );
				return new \WP_Error( 'generated_image_save_failed', __( 'The generated image could not be retained safely.', 'overcustomise' ), [ 'status' => 422 ] );
			}

			$usage        = OC_Upload_Handler::result_attachment_usage( $result );
			$actual_bytes = array_sum( $usage );
			if ( 1 !== count( $usage )
				|| ! self::reservation_covers_usage( $storage_reservation, 1, $actual_bytes )
				|| ( '' !== $token && ! self::validate_public_token( $token ) )
			) {
				OC_Upload_Handler::delete_result_attachments( $result );
				self::release_budget_reservation( $storage_reservation );
				OC_Logger::error( 'AI-generated image output exceeded its reservation or lost token ownership.' );
				return new \WP_Error( 'generated_image_save_failed', __( 'The generated image could not be retained safely.', 'overcustomise' ), [ 'status' => 422 ] );
			}
			if ( ! self::finalise_budget_reservation( $storage_reservation, 1, $actual_bytes ) ) {
				OC_Logger::error( 'AI-generated image storage budget could not be reconciled.' );
			}

			$result['source_attachment_id'] = $source_attachment_id;
			$result['filter_id']            = $filter_id;
			$result['attempt']              = $attempt;
			$result['attempt_limit']        = self::MAX_AI_FILTER_ATTEMPTS;
			$result['retries_remaining']    = self::MAX_AI_FILTER_ATTEMPTS - $attempt;
			return rest_ensure_response( $result );
		} finally {
			self::delete_owned_option( $lock_key, (string) $lock_owner );
		}
	}

	/** Return authorised generated results belonging to one source/filter group. */
	private static function image_filter_results( string $group, int $_source_attachment_id, int $product_id, int $variation_id, int $design_id, int $layer_id, string $token ): array {
		$ids = get_posts( [
			'post_type'      => 'attachment',
			'post_status'    => [ 'private', 'inherit' ],
			'posts_per_page' => self::MAX_AI_FILTER_ATTEMPTS,
			'fields'         => 'ids',
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'meta_key'       => '_oc_ai_filter_group',
			'meta_value'     => $group,
		] );
		$results = [];
		foreach ( array_map( 'absint', is_array( $ids ) ? $ids : [] ) as $attachment_id ) {
			if ( ! OC_Upload_Handler::attachment_is_accepted( $attachment_id, $product_id, $variation_id, $design_id, $layer_id, $token )
			) {
				continue;
			}
			$result_source_id  = absint( get_post_meta( $attachment_id, '_oc_ai_filter_source_id', true ) );
			$result_source_url = OC_Upload_Handler::attachment_access_url( $result_source_id );
			$url               = OC_Upload_Handler::attachment_access_url( $attachment_id );
			if ( ! $result_source_id || '' === $result_source_url || '' === $url ) {
				continue;
			}
			$results[] = [
				'attachment_id'        => $attachment_id,
				'preview_url'          => $url,
				'original_url'         => $url,
				'file_type'            => sanitize_key( (string) get_post_meta( $attachment_id, '_oc_artwork_type', true ) ),
				'attempt'              => absint( get_post_meta( $attachment_id, '_oc_ai_filter_attempt', true ) ),
				'source_attachment_id' => $result_source_id,
				'source_preview_url'   => $result_source_url,
			];
		}
		usort( $results, static fn ( array $a, array $b ): int => $a['attempt'] <=> $b['attempt'] );
		return $results;
	}

	/** Add attempt limits to a generated-result collection. */
	private static function image_filter_result_payload( array $results, int $attempt_count = 0 ): array {
		$attempts = 0;
		foreach ( $results as $result ) {
			$attempts = max( $attempts, absint( $result['attempt'] ?? 0 ) );
		}
		$attempts = max( $attempts, $attempt_count );
		return [
			'results'           => $results,
			'attempt_limit'     => self::MAX_AI_FILTER_ATTEMPTS,
			'retries_remaining' => max( 0, self::MAX_AI_FILTER_ATTEMPTS - $attempts ),
		];
	}

	/** Acquire an expiring DB option lock without deleting another request's lock. */
	private static function acquire_option_lock( string $option_name, int $ttl ): string|\WP_Error {
		$ttl = max( 1, min( 3600, $ttl ) );
		for ( $attempt = 0; $attempt < 2; $attempt++ ) {
			$owner = ( time() + $ttl ) . '|' . wp_generate_uuid4();
			if ( add_option( $option_name, $owner, '', false ) ) {
				return $owner;
			}

			$current = (string) get_option( $option_name, '' );
			$parts   = explode( '|', $current, 2 );
			if ( 2 !== count( $parts ) || ! preg_match( '/^[0-9]+$/D', $parts[0] ) || (int) $parts[0] > time() ) {
				return new \WP_Error( 'locked', __( 'This request is already being processed.', 'overcustomise' ), [ 'status' => 409 ] );
			}
			if ( ! self::delete_owned_option( $option_name, $current ) ) {
				return new \WP_Error( 'locked', __( 'This request is already being processed.', 'overcustomise' ), [ 'status' => 409 ] );
			}
		}

		return new \WP_Error( 'locked', __( 'This request is already being processed.', 'overcustomise' ), [ 'status' => 409 ] );
	}

	/** Delete a lock only when it still belongs to the expected request. */
	private static function delete_owned_option( string $option_name, string $expected_value ): bool {
		global $wpdb;

		$deleted = $wpdb->query( $wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s",
			$option_name,
			$expected_value
		) );
		if ( 1 !== $deleted ) {
			return false;
		}

		wp_cache_delete( $option_name, 'options' );
		wp_cache_delete( 'alloptions', 'options' );
		return true;
	}

	/** Save a base64 canvas snapshot for cart/order preview. */
	public function save_preview( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$auth = $this->verify_public_write_auth( $request );
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}

		$body = $request->get_json_params();
		$raw  = is_array( $body ) && isset( $body['image'] ) && is_string( $body['image'] ) ? $body['image'] : '';
		$url  = self::store_rate_limited_preview( $raw );
		if ( is_wp_error( $url ) ) {
			return $url;
		}

		return rest_ensure_response( [ 'url' => $url ] );
	}

	/**
	 * Save a preview supplied with a WooCommerce cart request.
	 *
	 * The cart request has no REST nonce, so inline previews use the same
	 * short-lived token and rate limit as the standalone preview endpoint.
	 */
	public static function store_cart_preview( string $raw, string $token ): string|\WP_Error {
		if ( ! self::validate_public_token( $token ) ) {
			return new \WP_Error( 'invalid_token', __( 'Security verification failed.', 'overcustomise' ), [ 'status' => 403 ] );
		}

		return self::store_rate_limited_preview( $raw );
	}

	/** Validate, deduplicate, reserve, and store a private preview. */
	private static function store_rate_limited_preview( string $raw ): string|\WP_Error {
		$decoded = self::decode_preview_image( $raw );
		if ( is_wp_error( $decoded ) ) {
			return $decoded;
		}

		$existing = self::private_preview_record( $decoded['id'] );
		if ( is_array( $existing ) && hash_equals( $existing['content_hash'], $decoded['content_hash'] ) ) {
			return self::private_preview_url( $decoded['id'], $existing );
		}

		$directory = OC_Upload_Handler::private_storage_path( 'previews' );
		if ( null === $directory ) {
			OC_Logger::error( 'Private preview storage is unavailable.' );
			return new \WP_Error( 'preview_storage_unavailable', __( 'Preview storage is temporarily unavailable.', 'overcustomise' ), [ 'status' => 503 ] );
		}

		$lock_key   = 'oc_preview_lock_' . $decoded['id'];
		$lock_owner = self::acquire_option_lock( $lock_key, self::PREVIEW_LOCK_TTL );
		if ( is_wp_error( $lock_owner ) ) {
			$existing = self::private_preview_record( $decoded['id'] );
			return is_array( $existing )
				? self::private_preview_url( $decoded['id'], $existing )
				: new \WP_Error( 'preview_in_progress', __( 'This preview is already being saved. Please try again.', 'overcustomise' ), [ 'status' => 409 ] );
		}

		try {
			$existing = self::private_preview_record( $decoded['id'] );
			if ( is_array( $existing ) && hash_equals( $existing['content_hash'], $decoded['content_hash'] ) ) {
				return self::private_preview_url( $decoded['id'], $existing );
			}
			$preview_option = self::PREVIEW_OPTION_PREFIX . $decoded['id'];
			if ( false !== get_option( $preview_option, false ) && ! delete_option( $preview_option ) ) {
				OC_Logger::error( 'Invalid private preview metadata could not be replaced safely.' );
				return new \WP_Error( 'preview_save_failed', __( 'The preview could not be saved safely.', 'overcustomise' ), [ 'status' => 503 ] );
			}

			$specs = self::preview_budget_specs( $decoded['bytes'] );
			if ( is_wp_error( $specs ) ) {
				return $specs;
			}
			$reservation = self::reserve_budgets( $specs );
			if ( is_wp_error( $reservation ) ) {
				return $reservation;
			}

			try {
				$random   = bin2hex( random_bytes( 20 ) );
				$secret   = bin2hex( random_bytes( 32 ) );
			} catch ( \Throwable $e ) {
				self::finalise_budget_reservation( $reservation, 0, 0 );
				OC_Logger::error( 'Secure private preview identifiers could not be generated.' );
				return new \WP_Error( 'preview_save_failed', __( 'The preview could not be saved safely.', 'overcustomise' ), [ 'status' => 503 ] );
			}
			$filename = 'preview-' . $random . '.' . $decoded['extension'];
			$path     = $directory . '/' . $filename;
			if ( ! self::atomic_private_write( $path, $decoded['data'] ) ) {
				self::finalise_budget_reservation( $reservation, 0, 0 );
				OC_Logger::error( 'A private preview file could not be written.' );
				return new \WP_Error( 'preview_save_failed', __( 'The preview could not be saved safely.', 'overcustomise' ), [ 'status' => 503 ] );
			}

			$record = [
				'version'      => 1,
				'file'         => $filename,
				'mime'         => $decoded['mime'],
				'bytes'        => $decoded['bytes'],
				'content_hash' => $decoded['content_hash'],
				'secret'       => $secret,
				'created_at'   => time(),
			];
			$encoded = wp_json_encode( $record );
			if ( ! is_string( $encoded ) || ! add_option( $preview_option, $encoded, '', false ) ) {
				@unlink( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				self::finalise_budget_reservation( $reservation, 0, 0 );
				OC_Logger::error( 'Private preview metadata could not be persisted.' );
				return new \WP_Error( 'preview_save_failed', __( 'The preview could not be saved safely.', 'overcustomise' ), [ 'status' => 503 ] );
			}

			return self::private_preview_url( $decoded['id'], $record );
		} finally {
			self::delete_owned_option( $lock_key, (string) $lock_owner );
		}
	}

	/** Decode and validate a bounded JPEG/PNG preview before any quota is consumed. */
	private static function decode_preview_image( string $raw ): array|\WP_Error {
		if ( preg_match( '#^data:image/(?:png|jpeg);base64,#i', $raw, $matches ) ) {
			$raw = substr( $raw, strlen( $matches[0] ) );
		} elseif ( str_starts_with( strtolower( $raw ), 'data:' ) || str_contains( $raw, ',' ) ) {
			return new \WP_Error( 'invalid_image', __( 'Invalid preview image data.', 'overcustomise' ), [ 'status' => 400 ] );
		}
		if ( '' === $raw ) {
			return new \WP_Error( 'invalid_image', __( 'Invalid preview image data.', 'overcustomise' ), [ 'status' => 400 ] );
		}
		if ( strlen( $raw ) > (int) ceil( self::MAX_PREVIEW_BYTES * 4 / 3 ) + 4 ) {
			return new \WP_Error( 'too_large', __( 'Preview image exceeds the size limit.', 'overcustomise' ), [ 'status' => 413 ] );
		}

		$data = base64_decode( $raw, true );
		if ( false === $data || strlen( $data ) < 100 || strlen( $data ) > self::MAX_PREVIEW_BYTES ) {
			return new \WP_Error( 'invalid_image', __( 'Invalid preview image data.', 'overcustomise' ), [ 'status' => 400 ] );
		}
		$image_info = @getimagesizefromstring( $data );
		$mime       = is_array( $image_info ) ? (string) ( $image_info['mime'] ?? '' ) : '';
		if ( ! in_array( $mime, [ 'image/jpeg', 'image/png' ], true ) ) {
			$last_error = error_get_last();
			OC_Logger::warning( 'Private preview validation failed: ' . ( $last_error['message'] ?? 'unsupported image data' ) );
			return new \WP_Error( 'invalid_image', __( 'Invalid preview image format.', 'overcustomise' ), [ 'status' => 400 ] );
		}
		$width  = (int) ( $image_info[0] ?? 0 );
		$height = (int) ( $image_info[1] ?? 0 );
		if ( $width <= 0 || $height <= 0 || $width > 12000 || $height > 12000 || $width * $height > 40000000 ) {
			return new \WP_Error( 'invalid_dimensions', __( 'Preview image dimensions exceed the safe limit.', 'overcustomise' ), [ 'status' => 413 ] );
		}

		$content_hash = hash( 'sha256', $data );
		return [
			'data'         => $data,
			'bytes'        => strlen( $data ),
			'mime'         => $mime,
			'extension'    => 'image/png' === $mime ? 'png' : 'jpg',
			'content_hash' => $content_hash,
			'id'           => substr( hash_hmac( 'sha256', $content_hash, wp_salt( 'nonce' ) ), 0, 40 ),
		];
	}

	/** Return a validated private preview record and resolved path. */
	private static function private_preview_record( string $id ): ?array {
		if ( ! preg_match( '/^[a-f0-9]{40}$/D', $id ) ) {
			return null;
		}
		$raw    = get_option( self::PREVIEW_OPTION_PREFIX . $id, '' );
		$record = is_string( $raw ) ? json_decode( $raw, true ) : null;
		if ( ! is_array( $record ) || ! is_int( $record['version'] ?? null ) || 1 !== $record['version']
			|| ! is_string( $record['file'] ?? null ) || ! preg_match( '/^preview-[a-f0-9]{40}\.(?:png|jpg)$/D', $record['file'] )
			|| ! in_array( $record['mime'] ?? '', [ 'image/png', 'image/jpeg' ], true )
			|| ! is_int( $record['bytes'] ?? null ) || $record['bytes'] < 100 || $record['bytes'] > self::MAX_PREVIEW_BYTES
			|| ! is_string( $record['content_hash'] ?? null ) || ! preg_match( '/^[a-f0-9]{64}$/D', $record['content_hash'] )
			|| ! is_string( $record['secret'] ?? null ) || ! preg_match( '/^[a-f0-9]{64}$/D', $record['secret'] )
		) {
			return null;
		}
		$expected_id = substr( hash_hmac( 'sha256', $record['content_hash'], wp_salt( 'nonce' ) ), 0, 40 );
		if ( ! hash_equals( $expected_id, $id ) ) {
			return null;
		}

		$directory = OC_Upload_Handler::private_storage_path( 'previews' );
		$path      = null !== $directory ? realpath( $directory . '/' . $record['file'] ) : false;
		$file_hash = false !== $path && is_file( $path ) ? hash_file( 'sha256', $path ) : false;
		if ( false === $path || ! is_file( $path ) || ! self::path_is_within( $path, $directory )
			|| filesize( $path ) !== (int) $record['bytes'] || ! is_string( $file_hash ) || ! hash_equals( $record['content_hash'], $file_hash )
		) {
			return null;
		}
		@touch( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$record['path'] = $path;
		return $record;
	}

	/** Return a stable signed controller URL which contains no storage filename. */
	private static function private_preview_url( string $id, array $record ): string {
		$secret = (string) ( $record['secret'] ?? '' );
		if ( ! preg_match( '/^[a-f0-9]{40}$/D', $id ) || ! preg_match( '/^[a-f0-9]{64}$/D', $secret ) ) {
			return '';
		}

		return add_query_arg(
			[
				'action'    => 'oc_serve_preview',
				'preview_id' => $id,
				'signature' => hash_hmac( 'sha256', $id, $secret ),
			],
			admin_url( 'admin-post.php' )
		);
	}

	/** Validate a signed private preview URL and return its canonical current form. */
	public static function validate_private_preview_url( string $preview_url ): string {
		$sanitised = esc_url_raw( $preview_url );
		$actual    = '' !== $sanitised ? wp_parse_url( $sanitised ) : false;
		$expected  = wp_parse_url( admin_url( 'admin-post.php' ) );
		if ( ! is_array( $actual ) || ! is_array( $expected )
			|| ! in_array( strtolower( (string) ( $actual['scheme'] ?? '' ) ), [ 'http', 'https' ], true )
			|| strtolower( (string) ( $actual['host'] ?? '' ) ) !== strtolower( (string) ( $expected['host'] ?? '' ) )
			|| (string) ( $actual['path'] ?? '' ) !== (string) ( $expected['path'] ?? '' )
			|| (int) ( $actual['port'] ?? 0 ) !== (int) ( $expected['port'] ?? 0 )
			|| isset( $actual['user'] ) || isset( $actual['pass'] ) || isset( $actual['fragment'] )
		) {
			return '';
		}

		wp_parse_str( (string) ( $actual['query'] ?? '' ), $query );
		$id        = is_string( $query['preview_id'] ?? null ) ? $query['preview_id'] : '';
		$signature = is_string( $query['signature'] ?? null ) ? $query['signature'] : '';
		if ( 'oc_serve_preview' !== ( $query['action'] ?? '' )
			|| ! preg_match( '/^[a-f0-9]{40}$/D', $id )
			|| ! preg_match( '/^[a-f0-9]{64}$/D', $signature )
		) {
			return '';
		}

		$record   = self::private_preview_record( $id );
		$expected = is_array( $record ) ? hash_hmac( 'sha256', $id, (string) $record['secret'] ) : '';
		return is_array( $record ) && hash_equals( $expected, $signature )
			? self::private_preview_url( $id, $record )
			: '';
	}

	/** Stream a signed private cart/order preview. */
	public static function serve_private_preview(): void {
		$id        = sanitize_text_field( wp_unslash( $_GET['preview_id'] ?? '' ) );
		$signature = sanitize_text_field( wp_unslash( $_GET['signature'] ?? '' ) );
		$record    = self::private_preview_record( $id );
		$expected  = is_array( $record ) ? hash_hmac( 'sha256', $id, (string) $record['secret'] ) : '';
		if ( ! is_array( $record ) || 64 !== strlen( $signature ) || ! hash_equals( $expected, $signature ) ) {
			wp_die( esc_html__( 'Preview is not available.', 'overcustomise' ), '', [ 'response' => 404 ] );
		}

		$image_info = @getimagesize( $record['path'] );
		if ( ! is_array( $image_info ) || (string) ( $image_info['mime'] ?? '' ) !== $record['mime'] ) {
			OC_Logger::warning( 'A signed private preview failed its serving-time MIME check.' );
			wp_die( esc_html__( 'Preview is not available.', 'overcustomise' ), '', [ 'response' => 404 ] );
		}

		status_header( 200 );
		header( 'Content-Type: ' . $record['mime'] );
		header( 'Content-Disposition: inline' );
		header( 'Content-Length: ' . (int) $record['bytes'] );
		header( 'Cache-Control: private, max-age=86400, immutable' );
		header( 'X-Content-Type-Options: nosniff' );
		// The storefront and WordPress admin may use different origins (for example,
		// www and non-www hosts), while still belonging to the same trusted site.
		header( 'Cross-Origin-Resource-Policy: same-site' );
		header( "Content-Security-Policy: default-src 'none'; sandbox" );
		while ( ob_get_level() ) {
			ob_end_clean();
		}
		readfile( $record['path'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
	}

	/** Atomically create one immutable private file. */
	private static function atomic_private_write( string $path, string $contents ): bool {
		if ( is_file( $path ) || '' === $contents ) {
			return false;
		}
		$tmp = dirname( $path ) . '/.preview-part-' . wp_generate_uuid4();
		$ok  = false;
		try {
			$ok = strlen( $contents ) === file_put_contents( $tmp, $contents, LOCK_EX )
				&& @chmod( $tmp, 0640 ) // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				&& @rename( $tmp, $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		} finally {
			if ( is_file( $tmp ) ) {
				@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
		}
		return $ok;
	}

	/** Validate a Spotify URL/URI and confirm it is publicly accessible. */
	public function validate_spotify( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$auth = $this->verify_public_write_auth( $request );
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}

		$result = self::validate_spotify_availability( (string) $request->get_param( 'url' ) );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	/** Validate Spotify format and public availability, reusing bounded server-side cache state. */
	public static function validate_spotify_availability( string $url, bool $reserve_rate_limit = true, bool $allow_remote = true ): array|\WP_Error {
		$url = trim( $url );
		if ( '' === $url ) {
			return [
				'valid'   => false,
				'reason'  => 'empty',
				'message' => __( 'Enter a Spotify link.', 'overcustomise' ),
			];
		}

		$parsed = self::parse_spotify_input( $url );
		if ( ! $parsed ) {
			return [
				'valid'   => false,
				'reason'  => 'invalid_format',
				'message' => __( 'Invalid Spotify link format.', 'overcustomise' ),
			];
		}

		$cache_key = 'oc_spotify_validation_' . hash( 'sha256', $parsed['spotify_uri'] );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) && isset( $cached['valid'], $cached['reason'] ) ) {
			if ( ! empty( $cached['valid'] ) ) {
				$cached = self::spotify_validation_with_proof( $cached, $parsed['spotify_uri'] );
			}
			return $cached;
		}
		if ( ! $allow_remote ) {
			return new \WP_Error( 'validation_required', __( 'Validate this Spotify link before adding the product to your cart.', 'overcustomise' ), [ 'status' => 409 ] );
		}

		$oembed_url = 'https://open.spotify.com/oembed?url=' . rawurlencode( $parsed['open_url'] );

		// Validate the oembed URL is exactly the expected Spotify domain.
		$parsed_oembed = wp_parse_url( $oembed_url );
		if ( ! is_array( $parsed_oembed ) || strtolower( $parsed_oembed['host'] ?? '' ) !== 'open.spotify.com' ) {
			return [
				'valid'   => false,
				'reason'  => 'invalid_format',
				'message' => __( 'Invalid Spotify link format.', 'overcustomise' ),
			];
		}

		if ( $reserve_rate_limit ) {
			$spotify_limit = self::filtered_limit( 'oc_spotify_validation_ip_hourly_limit', 120, 1, 10000 );
			if ( null === $spotify_limit ) {
				OC_Logger::error( 'Spotify validation rate configuration is malformed.' );
				return new \WP_Error( 'validation_unavailable', __( 'Could not validate Spotify right now. Please try again.', 'overcustomise' ), [ 'status' => 503 ] );
			}
			$rate_limit = self::reserve_request_rate(
				'spotify',
				$spotify_limit,
				__( 'Too many validations. Try again shortly.', 'overcustomise' )
			);
			if ( is_wp_error( $rate_limit ) ) {
				return $rate_limit;
			}
		}

		$response = wp_safe_remote_get( $oembed_url, [
			'timeout'             => 8,
			'redirection'         => 3,
			'limit_response_size' => self::SPOTIFY_RESPONSE_BYTES,
			'headers'             => [ 'Accept' => 'application/json' ],
		] );

		if ( is_wp_error( $response ) ) {
			OC_Logger::warning( 'Spotify validation request failed: ' . $response->get_error_message() );
			return new \WP_Error( 'unreachable', __( 'Could not validate Spotify right now. Please try again.', 'overcustomise' ), [ 'status' => 503 ] );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 === $status ) {
			$result = [
				'valid'      => true,
				'reason'     => 'ok',
				'spotifyUri' => $parsed['spotify_uri'],
				'openUrl'    => $parsed['open_url'],
			];
			$result = self::spotify_validation_with_proof( $result, $parsed['spotify_uri'], time() + self::SPOTIFY_VALID_CACHE_TTL );
			set_transient( $cache_key, $result, self::SPOTIFY_VALID_CACHE_TTL );
			return $result;
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

		$result = [
			'valid'   => false,
			'reason'  => $is_playlist ? 'playlist_private_or_invalid' : 'invalid_or_unavailable',
			'message' => $message,
		];
		set_transient( $cache_key, $result, self::SPOTIFY_INVALID_CACHE_TTL );
		return $result;
	}

	/** Confirm a recent server validation proof without an outbound request or cache lookup. */
	public static function verify_spotify_validation_proof( string $url, string $proof, int $expires ): bool {
		$parsed = self::parse_spotify_input( $url );
		if ( ! $parsed || $expires < time() || $expires > time() + self::SPOTIFY_VALID_CACHE_TTL || ! preg_match( '/^[a-f0-9]{64}$/D', $proof ) ) {
			return false;
		}
		$expected = hash_hmac( 'sha256', $parsed['spotify_uri'] . '|' . $expires, wp_salt( 'auth' ) );
		return hash_equals( $expected, $proof );
	}

	/** Attach a time-bound proof to one successful Spotify validation result. */
	private static function spotify_validation_with_proof( array $result, string $spotify_uri, int $expires = 0 ): array {
		$expires = $expires > time() ? $expires : time() + 600;
		$result['validationExpires'] = $expires;
		$result['validationProof']   = hash_hmac( 'sha256', $spotify_uri . '|' . $expires, wp_salt( 'auth' ) );
		return $result;
	}

	/**
	 * Parse a Spotify input into {type,id,spotify_uri,open_url}.
	 *
	 * @return array<string,string>|null
	 */
	private static function parse_spotify_input( string $raw ): ?array {
		$raw = trim( $raw );
		if ( '' === $raw || strlen( $raw ) > 2048 ) {
			return null;
		}

		if ( preg_match( '/^spotify:(track|album|artist|playlist|episode|show):([A-Za-z0-9]{1,128})$/i', $raw, $m ) ) {
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
		$id   = (string) $path_parts[ $type_index + 1 ];
		if ( ! preg_match( '/^[A-Za-z0-9]{1,128}$/D', $id ) ) {
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

		$max_bytes     = 5 * 1024 * 1024;
		$actual_size   = filesize( $files['csv']['tmp_name'] );
		$reported_size = (int) ( $files['csv']['size'] ?? 0 );
		if ( false === $actual_size || $actual_size <= 0 || $reported_size <= 0 || $reported_size !== (int) $actual_size ) {
			return new \WP_Error( 'invalid_csv', __( 'The CSV file is empty or unreadable.', 'overcustomise' ), [ 'status' => 422 ] );
		}
		if ( $actual_size > $max_bytes ) {
			return new \WP_Error( 'too_large', __( 'CSV file exceeds 5 MB.', 'overcustomise' ), [ 'status' => 413 ] );
		}

		$dir = self::protected_vdp_directory();
		if ( null === $dir ) {
			OC_Logger::error( 'Private VDP storage was unavailable during an upload.' );
			return new \WP_Error( 'storage_protection_failed', __( 'Private VDP storage is unavailable.', 'overcustomise' ), [ 'status' => 503 ] );
		}

		$filename = 'vdp-' . wp_generate_uuid4() . '.csv';
		$filepath = $dir . '/' . $filename;

		if ( false === move_uploaded_file( $files['csv']['tmp_name'], $filepath ) ) {
			return new \WP_Error( 'save_failed', __( 'Could not save CSV file.', 'overcustomise' ), [ 'status' => 500 ] );
		}
		if ( filesize( $filepath ) !== $actual_size || ! @chmod( $filepath, 0640 ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			@unlink( $filepath ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			OC_Logger::error( 'A staged VDP upload failed its private file verification.' );
			return new \WP_Error( 'save_failed', __( 'Could not save CSV file.', 'overcustomise' ), [ 'status' => 500 ] );
		}

		$old_filepath = '';
		$vdp          = new OC_VDP();
		$csv_data     = $vdp->parse_csv( $filepath );

		if ( ! empty( $csv_data['error'] ) || empty( $csv_data['headers'] ) || empty( $csv_data['rows'] ) ) {
			@unlink( $filepath );
			return new \WP_Error( 'invalid_csv', (string) ( $csv_data['error'] ?? __( 'CSV must contain at least one data row.', 'overcustomise' ) ), [ 'status' => 422 ] );
		}
		$all_layers = array_values( array_filter(
			OC_DB::get_design_layers( $design_id ),
			static fn( object $layer ): bool => ( ! isset( $layer->visible ) || (bool) $layer->visible ) && empty( $layer->locked ) && in_array( (string) $layer->type, [ 'text', 'textarea', 'spotify' ], true )
		) );
		if ( count( $csv_data['headers'] ) > count( $all_layers ) ) {
			self::delete_vdp_file( $filepath );
			return new \WP_Error( 'invalid_csv_fields', __( 'The CSV contains more fields than the design has editable variable layers.', 'overcustomise' ), [ 'status' => 422 ] );
		}
		foreach ( $csv_data['headers'] as $index => $header ) {
			foreach ( $csv_data['rows'] as $row ) {
				$value = $vdp->normalise_layer_value( $all_layers[ $index ], (string) ( $row[ $header ] ?? '' ) );
				if ( is_wp_error( $value ) ) {
					self::delete_vdp_file( $filepath );
					return new \WP_Error( 'invalid_csv_value', $value->get_error_message(), [ 'status' => 422 ] );
				}
			}
		}
		$layer_ids = array_values( array_map( static fn( object $layer ): int => (int) $layer->id, $all_layers ) );

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
			'template_id' => (int) $template->id,
			'fields'     => $csv_data['headers'],
			'row_count'  => count( $csv_data['rows'] ),
			'file_name'  => sanitize_file_name( basename( (string) $files['csv']['name'] ) ) ?: 'data.csv',
		] );
	}

	/** Return the VDP directory outside public uploads. */
	private static function protected_vdp_directory(): ?string {
		return OC_Upload_Handler::private_storage_path( 'vdp' );
	}

	/** Migrate at most 25 legacy public-upload VDP files per request. */
	private static function migrate_legacy_vdp_files( string $private_directory ): void {
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
			return;
		}
		$legacy_directory = trailingslashit( (string) $uploads['basedir'] ) . 'overcustomise/vdp';
		$legacy_real      = self::legacy_vdp_directory();
		if ( null === $legacy_real ) {
			return;
		}
		self::protect_legacy_vdp_directory( $legacy_real );

		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, csv_file_path FROM {$wpdb->prefix}oc_vdp_templates WHERE csv_file_path LIKE %s ORDER BY id ASC LIMIT 25",
			$wpdb->esc_like( rtrim( wp_normalize_path( $legacy_directory ), '/' ) ) . '/%'
		) ) ?: [];
		foreach ( $rows as $row ) {
			$source = realpath( (string) $row->csv_file_path );
			if ( false === $source || ! is_file( $source ) || ! self::path_is_within( $source, $legacy_real ) ) {
				OC_Logger::warning( 'A legacy VDP migration row referenced an unavailable or unsafe file.' );
				continue;
			}
			$destination = $private_directory . '/vdp-' . wp_generate_uuid4() . '.csv';
			if ( ! self::atomic_private_copy( $source, $destination ) ) {
				OC_Logger::warning( 'A legacy VDP file could not be copied into private storage.' );
				continue;
			}
			$updated = $wpdb->query( $wpdb->prepare(
				"UPDATE {$wpdb->prefix}oc_vdp_templates SET csv_file_path = %s WHERE id = %d AND csv_file_path = %s",
				$destination,
				(int) $row->id,
				(string) $row->csv_file_path
			) );
			if ( 1 !== $updated ) {
				@unlink( $destination ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				OC_Logger::warning( 'A legacy VDP migration lost its database update race.' );
				continue;
			}
			wp_delete_file( $source );
		}
	}

	/** Resolve the exact legacy VDP root only when it stays inside uploads. */
	private static function legacy_vdp_directory(): ?string {
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
			return null;
		}
		$uploads_real = realpath( (string) $uploads['basedir'] );
		$legacy_real  = realpath( trailingslashit( (string) $uploads['basedir'] ) . 'overcustomise/vdp' );
		if ( false === $uploads_real || false === $legacy_real || ! is_dir( $legacy_real ) || ! self::path_is_within( $legacy_real, $uploads_real ) ) {
			return null;
		}
		return $legacy_real;
	}

	/** Install best-effort deny rules while bounded VDP migration is in progress. */
	private static function protect_legacy_vdp_directory( string $directory ): bool {
		$files = [
			'.htaccess' => "Options -Indexes\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n",
			'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?><configuration><system.webServer><security><authorization><remove users=\"*\" roles=\"\" verbs=\"\"/><add accessType=\"Deny\" users=\"*\"/></authorization></security></system.webServer></configuration>\n",
			'index.php' => "<?php\nhttp_response_code( 404 );\nexit;\n",
		];
		foreach ( $files as $filename => $contents ) {
			$path = $directory . '/' . $filename;
			if ( is_file( $path ) && hash_equals( $contents, (string) file_get_contents( $path ) ) ) {
				continue;
			}
			if ( ! self::atomic_replace_file( $path, $contents, 0640 ) ) {
				OC_Logger::warning( 'Legacy VDP deny rules could not be installed while migration is pending.' );
				return false;
			}
		}
		return true;
	}

	/** Delete a VDP CSV only from the private or exact legacy VDP root. */
	public static function delete_vdp_file( string $filepath ): void {
		$real = realpath( $filepath );
		if ( false === $real || ! is_file( $real ) ) {
			return;
		}
		$private = self::protected_vdp_directory();
		if ( null !== $private && self::path_is_within( $real, $private ) ) {
			wp_delete_file( $real );
			return;
		}

		$uploads = wp_upload_dir();
		$uploads_real = empty( $uploads['error'] ) ? realpath( (string) ( $uploads['basedir'] ?? '' ) ) : false;
		$legacy       = false !== $uploads_real ? realpath( $uploads_real . '/overcustomise/vdp' ) : false;
		if ( false !== $uploads_real && false !== $legacy && self::path_is_within( $legacy, $uploads_real ) && self::path_is_within( $real, $legacy ) ) {
			wp_delete_file( $real );
		}
	}

	/** Atomically copy an existing file into private storage. */
	private static function atomic_private_copy( string $source, string $destination ): bool {
		if ( ! is_file( $source ) || is_file( $destination ) ) {
			return false;
		}
		$tmp = $destination . '.part-' . wp_generate_uuid4();
		$ok  = false;
		try {
			$source_size = filesize( $source );
			$ok = false !== $source_size && $source_size > 0
				&& copy( $source, $tmp )
				&& filesize( $tmp ) === $source_size
				&& @chmod( $tmp, 0640 ) // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				&& @rename( $tmp, $destination ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		} finally {
			if ( is_file( $tmp ) ) {
				@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
		}
		return $ok;
	}

	/** Atomically replace a small deny-rule file. */
	private static function atomic_replace_file( string $path, string $contents, int $mode ): bool {
		$tmp = dirname( $path ) . '/.' . basename( $path ) . '.part-' . wp_generate_uuid4();
		$ok  = false;
		try {
			$ok = strlen( $contents ) === file_put_contents( $tmp, $contents, LOCK_EX )
				&& @chmod( $tmp, $mode ) // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				&& @rename( $tmp, $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		} finally {
			if ( is_file( $tmp ) ) {
				@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
		}
		return $ok;
	}

	/** Compare canonical paths without permitting prefix collisions. */
	private static function path_is_within( string $path, string $base ): bool {
		$path = rtrim( wp_normalize_path( $path ), '/' );
		$base = rtrim( wp_normalize_path( $base ), '/' );
		if ( '' === $path || '' === $base ) {
			return false;
		}
		if ( str_starts_with( strtoupper( PHP_OS_FAMILY ), 'WINDOWS' ) ) {
			$path = strtolower( $path );
			$base = strtolower( $base );
		}
		return str_starts_with( $path, $base . '/' );
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
