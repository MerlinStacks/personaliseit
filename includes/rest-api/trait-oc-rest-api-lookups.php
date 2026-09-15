<?php
/**
 * Spotify validation and location lookups for OC_Rest_API.
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

/** Composed by OC_Rest_API; shares its authentication, budgets, and constants. */
trait OC_Rest_API_Lookups {

	/** Validate a Spotify URL/URI and confirm it is publicly accessible. */
	public function validate_spotify( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$auth = $this->verify_public_write_auth( $request );
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}

		$result = self::validate_spotify_availability( (string) $request->get_param( 'url' ) );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	/** Proxy a strictly bounded place search without exposing the customer to Nominatim. */
	public function lookup_location( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$auth = $this->verify_public_write_auth( $request );
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}

		$results = self::find_locations( (string) $request->get_param( 'query' ) );
		if ( is_wp_error( $results ) ) {
			return $results;
		}

		$response = new \WP_REST_Response(
			[
				'results' => $results,
				'result'  => $results[0] ?? null,
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

	/** Validate a human-readable place query without silently altering it. */
	public static function validate_location_query( mixed $query ): bool {
		if ( ! is_string( $query ) || trim( $query ) !== $query ) {
			return false;
		}
		$length = strlen( $query );
		return $length >= 2
			&& $length <= 200
			&& 1 === preg_match( '//u', $query )
			&& 0 === preg_match( '/[\x00-\x1F\x7F]/u', $query );
	}

	/** Fetch one location for backwards-compatible callers and tests. */
	public static function find_location( string $query, bool $reserve_rate_limit = true ): array|null|\WP_Error {
		$results = self::find_locations( $query, $reserve_rate_limit, 1 );
		return is_wp_error( $results ) ? $results : ( $results[0] ?? null );
	}

	/** Fetch and reduce bounded location suggestions. */
	public static function find_locations( string $query, bool $reserve_rate_limit = true, int $limit = 6 ): array|\WP_Error {
		if ( ! self::validate_location_query( $query ) ) {
			return new \WP_Error( 'invalid_location_query', __( 'Enter a valid place name.', 'overcustomise' ), [ 'status' => 400 ] );
		}

		if ( $reserve_rate_limit ) {
			$hourly_limit = self::filtered_limit( 'oc_location_lookup_ip_hourly_limit', 120, 1, 1000 );
			if ( null === $hourly_limit ) {
				return new \WP_Error( 'lookup_unavailable', __( 'Place lookup is temporarily unavailable.', 'overcustomise' ), [ 'status' => 503 ] );
			}
			$reservation = self::reserve_request_rate( 'location-lookup', $hourly_limit, __( 'Too many place searches. Please try again later.', 'overcustomise' ) );
			if ( is_wp_error( $reservation ) ) {
				return $reservation;
			}
		}

		$limit        = max( 1, min( 6, $limit ) );
		$base_country = get_option( 'woocommerce_default_country', '' );
		$country      = is_string( $base_country ) ? strtolower( explode( ':', $base_country, 2 )[0] ) : '';
		$results      = [];
		if ( 1 === preg_match( '/^[a-z]{2}$/D', $country ) ) {
			$preferred = self::fetch_locations( $query, $limit, $country );
			if ( ! is_wp_error( $preferred ) ) {
				$results = $preferred;
			}
		}
		if ( count( $results ) >= $limit ) {
			return $results;
		}

		// Fetch a full bounded global page so overlapping preferred results do not consume fill slots.
		$global = self::fetch_locations( $query, $limit );
		if ( is_wp_error( $global ) ) {
			return $results ? $results : $global;
		}
		foreach ( $global as $result ) {
			if ( ! in_array( $result, $results, true ) ) {
				$results[] = $result;
			}
			if ( count( $results ) >= $limit ) {
				break;
			}
		}
		return $results;
	}

	/** Fetch one bounded country or worldwide page without reserving another request budget. */
	private static function fetch_locations( string $query, int $limit, string $country = '' ): array|\WP_Error {
		$args = [
			'format' => 'jsonv2',
			'limit'  => $limit,
			'q'      => $query,
		];
		if ( '' !== $country ) {
			$args['countrycodes'] = $country;
		}
		$url       = add_query_arg(
			$args,
			'https://nominatim.openstreetmap.org/search'
		);
		$site_host = (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		$response  = wp_safe_remote_get(
			$url,
			[
				'timeout'             => 5,
				'redirection'         => 0,
				'limit_response_size' => self::LOCATION_RESPONSE_BYTES,
				'user-agent'          => 'OverCustomise location lookup (' . $site_host . ')',
				'headers'             => [ 'Accept' => 'application/json' ],
			]
		);
		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'lookup_unavailable', __( 'Place lookup is temporarily unavailable.', 'overcustomise' ), [ 'status' => 503 ] );
		}
		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return new \WP_Error( 'lookup_unavailable', __( 'Place lookup is temporarily unavailable.', 'overcustomise' ), [ 'status' => 503 ] );
		}

		$body = wp_remote_retrieve_body( $response );
		if ( ! is_string( $body ) || strlen( $body ) > self::LOCATION_RESPONSE_BYTES ) {
			return new \WP_Error( 'invalid_lookup_response', __( 'Place lookup returned an invalid response.', 'overcustomise' ), [ 'status' => 502 ] );
		}
		$decoded = json_decode( $body, true );
		if ( ! is_array( $decoded ) || ! array_is_list( $decoded ) ) {
			return new \WP_Error( 'invalid_lookup_response', __( 'Place lookup returned an invalid response.', 'overcustomise' ), [ 'status' => 502 ] );
		}
		if ( empty( $decoded ) ) {
			return [];
		}

		$results = [];
		foreach ( array_slice( $decoded, 0, max( 1, min( 6, $limit ) ) ) as $item ) {
			$latitude     = is_array( $item ) && is_numeric( $item['lat'] ?? null ) ? (float) $item['lat'] : NAN;
			$longitude    = is_array( $item ) && is_numeric( $item['lon'] ?? null ) ? (float) $item['lon'] : NAN;
			$display_name = is_array( $item ) && is_string( $item['display_name'] ?? null ) ? trim( $item['display_name'] ) : '';
			if ( ! is_finite( $latitude ) || ! is_finite( $longitude ) || $latitude < -90 || $latitude > 90
				|| $longitude < -180 || $longitude > 180 || '' === $display_name || strlen( $display_name ) > 300
				|| 1 !== preg_match( '//u', $display_name ) || 1 === preg_match( '/[\x00-\x1F\x7F]/u', $display_name )
			) {
				continue;
			}
			$result = [
				'latitude'    => round( $latitude, 6 ),
				'longitude'   => round( $longitude, 6 ),
				'displayName' => $display_name,
			];
			if ( ! in_array( $result, $results, true ) ) {
				$results[] = $result;
			}
		}

		return $results;
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

		$response = wp_safe_remote_get(
			$oembed_url,
			[
				'timeout'             => 8,
				'redirection'         => 3,
				'limit_response_size' => self::SPOTIFY_RESPONSE_BYTES,
				'headers'             => [ 'Accept' => 'application/json' ],
			]
		);

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
		$expires                     = $expires > time() ? $expires : time() + 600;
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
}
