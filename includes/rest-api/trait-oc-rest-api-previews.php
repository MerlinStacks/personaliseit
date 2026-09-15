<?php
/**
 * Private preview storage, validation, and serving for OC_Rest_API.
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

/** Composed by OC_Rest_API with shared authentication, budgets, and path helpers. */
trait OC_Rest_API_Previews {

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

		$directory = OC_Upload_Handler::private_storage_path( 'previews', true );
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
			if ( false !== get_option( $preview_option, false ) ) {
				OC_Logger::error( 'Existing private preview metadata retained: unavailable storage must not rotate its signing secret.' );
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
				$random = bin2hex( random_bytes( 20 ) );
				$secret = bin2hex( random_bytes( 32 ) );
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

			$record  = [
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
		$mime       = is_array( $image_info ) ? $image_info['mime'] : '';
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

	/** Maintenance API only: migrate/resolve storage, never grant signed/order authorization. */
	public static function relocate_private_preview( string $id ): ?string {
		$record = self::private_preview_record( $id );
		return is_array( $record ) ? $record['path'] : null;
	}

	/** Return a validated private preview record and resolved path. */
	private static function private_preview_record( string $id, ?string $signature = null ): ?array {
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
		if ( null !== $signature && ! hash_equals( hash_hmac( 'sha256', $id, $record['secret'] ), $signature ) ) {
			return null;
		}

		$directory = OC_Upload_Handler::private_storage_path( 'previews', true );
		if ( null === $directory ) {
			OC_Storage_Upgrade::report( 'previews', 'Preview relocation/read blocked: current verified storage unavailable; metadata and source retained.' );
			return null;
		}
		$target = $directory . '/' . $record['file'];
		$path = OC_Storage_Upgrade::canonical_file( $target );
		if ( null === $path && ! file_exists( $target ) && ! is_link( $target ) ) {
			foreach ( OC_Storage_Upgrade::legacy_private_roots() as $old_root ) {
				$source = OC_Storage_Upgrade::known_private_file( $old_root . '/previews/' . $record['file'], 'previews' );
				if ( null === $source || filesize( $source ) !== $record['bytes'] ) {
					continue;
				}
				$info = @getimagesize( $source );
				if ( ! is_array( $info ) || $info['mime'] !== $record['mime'] ) {
					continue;
				}
				$path = OC_Storage_Upgrade::copy_known_private_file( $source, 'previews', $record['file'], self::MAX_PREVIEW_BYTES, $record['content_hash'] );
				if ( null !== $path ) {
					break;
				}
			}
		}
		$file_hash = null !== $path && is_file( $path ) && filesize( $path ) === $record['bytes'] ? hash_file( 'sha256', $path ) : false;
		if ( null === $path || ! is_file( $path ) || ! self::path_is_within( $path, $directory )
			|| filesize( $path ) !== (int) $record['bytes'] || ! is_string( $file_hash ) || ! hash_equals( $record['content_hash'], $file_hash )
			|| $raw !== get_option( self::PREVIEW_OPTION_PREFIX . $id, '' )
		) {
			OC_Storage_Upgrade::report( $directory, 'Preview unavailable or changed during relocation; existing metadata and source retained.' );
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
				'action'     => 'oc_serve_preview',
				'preview_id' => $id,
				'signature'  => hash_hmac( 'sha256', $id, $secret ),
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

		$record   = self::private_preview_record( $id, $signature );
		$expected = is_array( $record ) ? hash_hmac( 'sha256', $id, (string) $record['secret'] ) : '';
		return is_array( $record ) && hash_equals( $expected, $signature )
			? self::private_preview_url( $id, $record )
			: '';
	}

	/** Stream a signed private cart/order preview. */
	public static function serve_private_preview(): void {
		$id        = sanitize_text_field( wp_unslash( $_GET['preview_id'] ?? '' ) );
		$signature = sanitize_text_field( wp_unslash( $_GET['signature'] ?? '' ) );
		$record    = self::private_preview_record( $id, $signature );
		$expected  = is_array( $record ) ? hash_hmac( 'sha256', $id, (string) $record['secret'] ) : '';
		if ( ! is_array( $record ) || 64 !== strlen( $signature ) || ! hash_equals( $expected, $signature ) ) {
			wp_die( esc_html__( 'Preview is not available.', 'overcustomise' ), '', [ 'response' => 404 ] );
		}

		$image_info = @getimagesize( $record['path'] );
		if ( ! is_array( $image_info ) || $image_info['mime'] !== $record['mime'] ) {
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
}
