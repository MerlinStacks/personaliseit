<?php
/**
 * Public request authentication and token binding for OC_Rest_API.
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

/** Composed by OC_Rest_API; shares its constants and option-lock helpers. */
trait OC_Rest_API_Authentication {

	/** Issue or reuse a fixed-lifetime token bound to a private browser or WC session. */
	public static function issue_public_token(): string {
		$binding = self::current_request_binding();
		if ( null === $binding && ! headers_sent() ) {
			try {
				$secret = bin2hex( random_bytes( 32 ) );
				$cookie = $secret . '.' . hash_hmac( 'sha256', $secret, wp_salt( 'auth' ) );
				if ( setcookie( self::BROWSER_COOKIE, $cookie, [ 'expires' => time() + 30 * DAY_IN_SECONDS, 'path' => '/', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax' ] ) ) {
					$_COOKIE[ self::BROWSER_COOKIE ] = $cookie;
					$binding = self::current_request_binding();
				}
			} catch ( \Throwable $e ) {
				return '';
			}
		}
		if ( null === $binding ) {
			OC_Logger::warning( 'A public request token requires a private browser cookie or WC session.' );
			return '';
		}

		$current = self::token_for_binding( $binding );
		if ( '' !== $current && self::public_token_is_reusable( $current, $binding ) ) {
			return $current;
		}

		$lock_key = 'oc_token_issue_lock_' . hash( 'sha256', $binding['type'] . '|' . $binding['hash'] );
		$lock     = self::acquire_option_lock( $lock_key, 15 );
		if ( is_wp_error( $lock ) ) {
			// A concurrent token request may have completed after the first lookup.
			$current = self::token_for_binding( $binding );
			return '' !== $current && self::public_token_is_reusable( $current, $binding ) ? $current : '';
		}

		try {
			$current = self::token_for_binding( $binding );
			if ( '' !== $current && self::public_token_is_reusable( $current, $binding ) ) {
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

	/** Confirm a bound token has enough lifetime for a long-running write. */
	private static function public_token_is_reusable( string $token, array $binding ): bool {
		$state = self::public_token_state( $token, $binding );
		return is_array( $state ) && (int) $state['expires_at'] > time() + self::PUBLIC_TOKEN_REFRESH;
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
		$stored_browser   = (string) get_post_meta( $attachment_id, '_oc_artwork_browser', true );
		$browser          = self::browser_principal();

		return 4 === count( $expected_context )
			&& ( $expected_context === $primary_context || OC_Upload_Handler::attachment_context_is_authorised( $attachment_id, $expected_context ) )
			&& ( ( 64 === strlen( $stored_hash ) && hash_equals( $stored_hash, hash( 'sha256', $token ) ) )
				|| ( '' !== $browser && 64 === strlen( $stored_browser ) && hash_equals( $stored_browser, $browser ) ) );
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
			|| ! in_array( $state['binding_type'] ?? '', [ 'session', 'browser' ], true )
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
				$binding      = '' !== $session_hash ? [
					'type' => 'session',
					'hash' => $session_hash,
				] : null;
			} else {
				$binding = self::current_request_binding();
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

	/** Durable site-scoped principal, derived only from a correctly signed cookie. */
	public static function browser_principal(): string {
		$cookie = $_COOKIE[ self::BROWSER_COOKIE ] ?? '';
		if ( is_string( $cookie ) && preg_match( '/^([a-f0-9]{64})\.([a-f0-9]{64})$/D', $cookie, $parts )
			&& hash_equals( hash_hmac( 'sha256', $parts[1], wp_salt( 'auth' ) ), $parts[2] )
		) {
			return hash_hmac( 'sha256', 'oc-browser-owner|' . get_current_blog_id() . '|' . home_url( '/' ) . '|' . $parts[1], wp_salt( 'auth' ) );
		}
		return '';
	}

	/** Keep existing browser-token bindings stable; ownership uses the scoped principal. */
	private static function current_request_binding(): ?array {
		if ( '' !== self::browser_principal() ) {
			return [ 'type' => 'browser', 'hash' => hash( 'sha256', explode( '.', $_COOKIE[ self::BROWSER_COOKIE ] )[0] ) ];
		}
		$session_hash = self::wc_session_hash();
		if ( '' !== $session_hash ) {
			return [
				'type' => 'session',
				'hash' => $session_hash,
			];
		}

		// Legacy IP tokens cannot be upgraded: another guest may hold the same token.
		return null;
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

	/** Read the token associated with a WC session or private browser. */
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
}
