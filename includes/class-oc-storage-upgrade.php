<?php
/** Verified storage identity shared by HTTP, CLI and retained-file consumers. */
defined( 'ABSPATH' ) || exit;

final class OC_Storage_Upgrade {

	private const TTL = 21600;
	private static bool $probed = false;
	private static array $reports = [];

	/** Diagnostics are deliberately not authorization evidence. */
	public static function reports(): array {
		return self::$reports;
	}

	public static function report( string $root, string $message ): bool {
		self::$reports[ $root ] = $message;
		return false;
	}

	/** Site and deployment configuration, excluding request-dependent DOCUMENT_ROOT. */
	private static function context( string $root, string $configuration ): array {
		$uploads = wp_upload_dir();
		clearstatcache( true, $root );
		$stat = @stat( $root );
		return [
			'v' => 1, 'site' => get_current_blog_id(), 'home' => home_url( '/' ), 'siteurl' => site_url( '/' ),
			'abspath' => wp_normalize_path( ABSPATH ), 'abspath_real' => realpath( ABSPATH ),
			'uploads' => $uploads['basedir'] ?? '', 'uploads_real' => ! empty( $uploads['basedir'] ) ? realpath( $uploads['basedir'] ) : false,
			'url' => $uploads['baseurl'] ?? '',
			'root' => $root, 'device' => $stat['dev'] ?? null, 'inode' => $stat['ino'] ?? null,
			'configuration' => $configuration,
			'deployment' => (string) apply_filters( 'oc_storage_verification_context', '' ),
		];
	}

	private static function key( array $context ): string {
		return 'oc_storage_evidence_' . hash( 'sha256', serialize( $context ) );
	}

	private static function evidence( array $context ): ?array {
		$record = get_option( self::key( $context ), [] );
		if ( ! is_array( $record ) || ! is_array( $record['data'] ?? null ) || ! is_string( $record['mac'] ?? null ) ) {
			return null;
		}
		$data = $record['data'];
		if ( ! hash_equals( hash_hmac( 'sha256', serialize( $data ), wp_salt( 'auth' ) ), $record['mac'] )
			|| ( $data['context'] ?? null ) !== $context || ! is_int( $data['at'] ?? null ) || ! is_int( $data['until'] ?? null )
			|| $data['at'] > time() || $data['until'] <= time() || $data['until'] > $data['at'] + self::TTL ) {
			return null;
		}
		return $data;
	}

	private static function remember( array $context, bool $ok, string $detail, int $ttl ): void {
		$data = [ 'context' => $context, 'at' => time(), 'until' => time() + $ttl, 'ok' => $ok, 'detail' => $detail ];
		update_option( self::key( $context ), [ 'data' => $data, 'mac' => hash_hmac( 'sha256', serialize( $data ), wp_salt( 'auth' ) ) ], false );
	}

	private static function revocation_key( string $root ): string {
		return 'oc_storage_root_revoked_' . hash( 'sha256', serialize( [ get_current_blog_id(), home_url( '/' ), wp_normalize_path( $root ), (string) apply_filters( 'oc_storage_verification_context', '' ) ] ) );
	}

	/** A denial is sticky across request/configuration changes, not a renewable permission. */
	public static function private_root_revoked( string $root ): bool {
		if ( null !== get_option( self::revocation_key( $root ), null ) ) {
			self::report( $root, 'Private-root evidence revoked by a known document-root contradiction. Correct routing and change the trusted deployment revision before revalidation.' );
			return true;
		}
		return false;
	}

	/** Persist the contradiction before returning, including callers' early overlap checks. */
	public static function revoke_private_root( string $root, string $configuration ): void {
		$reason = 'Private root overlaps the known document root; prior CLI evidence revoked.';
		self::remember( self::context( $root, 'private:' . $configuration ), false, $reason, self::TTL );
		$key = self::revocation_key( $root );
		if ( null === get_option( $key, null ) ) {
			$data = [ 'root' => $root, 'at' => time(), 'reason' => $reason ];
			$record = [ 'data' => $data, 'mac' => hash_hmac( 'sha256', serialize( $data ), wp_salt( 'auth' ) ) ];
			update_option( $key, $record, false );
			if ( $record !== self::stored_option( $key ) ) {
				OC_Logger::error( 'Private storage contradiction could not be persisted; stop CLI workers until storage evidence is revoked.' );
			}
		}
		self::report( $root, $reason );
	}

	/** Read mutable option state after a write. */
	private static function stored_option( string $key ): mixed {
		return get_option( $key, null );
	}

	/** Filesystem policy, not proof of HTTP denial; positive HTTP evidence is never required. */
	public static function private_root_verified( string $root, string $configuration, string|false $document_root ): bool {
		if ( false !== $document_root && ( '' === rtrim( wp_normalize_path( $document_root ), '/' ) || self::within( $root, $document_root ) || self::within( $document_root, $root ) ) ) {
			self::revoke_private_root( $root, $configuration );
			return false;
		}
		if ( self::private_root_revoked( $root ) ) {
			return false;
		}
		if ( false === $document_root ) {
			self::report( $root, 'Automatic storage is operational; direct HTTP protection has not been verified.' );
		}
		return true;
	}

	public static function within( string $path, string $root ): bool {
		$path = rtrim( wp_normalize_path( $path ), '/' );
		$root = rtrim( wp_normalize_path( $root ), '/' );
		return '' !== $root && ( $path === $root || str_starts_with( $path, $root . '/' ) );
	}

	/** Permit only the configured uploads-root alias, not aliases beneath it. */
	public static function canonical_file( string $path ): ?string {
		$path = wp_normalize_path( $path );
		if ( str_contains( $path, "\0" ) || preg_match( '#(?:^|/)\.{1,2}(?:/|$)#', $path ) ) {
			return null;
		}
		$real = realpath( $path );
		if ( false === $real || ! is_file( $real ) ) {
			return null;
		}
		$uploads = wp_upload_dir();
		$alias = rtrim( wp_normalize_path( (string) ( $uploads['basedir'] ?? '' ) ), '/' );
		$base = '' !== $alias ? realpath( $alias ) : false;
		if ( false !== $base && str_starts_with( $path, $alias . '/' ) ) {
			$path = wp_normalize_path( $base ) . substr( $path, strlen( $alias ) );
		}
		return wp_normalize_path( $real ) === $path ? $real : null;
	}

	/** Known spellings for reference queries only; callers must separately authorize the root. */
	public static function file_reference_paths( string $path ): array {
		$real = self::canonical_file( $path );
		if ( null === $real ) {
			return [];
		}
		$paths = [ $real ];
		$uploads = wp_upload_dir();
		$alias = rtrim( wp_normalize_path( (string) ( $uploads['basedir'] ?? '' ) ), '/' );
		$base = '' !== $alias ? realpath( $alias ) : false;
		if ( false !== $base && self::within( $real, $base ) ) {
			$paths[] = $alias . substr( wp_normalize_path( $real ), strlen( wp_normalize_path( $base ) ) );
		}
		return array_values( array_unique( $paths ) );
	}

	/** Exact known old private roots; consumers still validate protection and containment. */
	public static function legacy_private_roots(): array {
		$uploads = wp_upload_dir();
		$candidates = [ dirname( rtrim( ABSPATH, '/\\' ) ) . '/.overcustomise-private-' . substr( hash( 'sha256', wp_normalize_path( ABSPATH ) ), 0, 12 ) ];
		$token = get_option( 'oc_private_storage_token', '' );
		$base = ! empty( $uploads['basedir'] ) ? realpath( $uploads['basedir'] ) : false;
		if ( false !== $base && is_string( $token ) && preg_match( '/^[a-z0-9]{32}$/D', $token ) ) {
			$candidates[] = $base . '/.overcustomise-private-' . $token;
		}
		$roots = [];
		foreach ( $candidates as $candidate ) {
			$parent = realpath( dirname( $candidate ) );
			$real = realpath( $candidate );
			if ( false !== $parent && false !== $real && is_dir( $real ) && ! is_link( $candidate )
				&& wp_normalize_path( $real ) === wp_normalize_path( $parent . '/' . basename( $candidate ) ) ) {
				$roots[] = $real;
			}
		}
		return array_values( array_unique( $roots ) );
	}

	/** Migration source guard only; old roots are never automatically serving roots. */
	public static function known_private_file( string $path, string $subdirectory ): ?string {
		if ( ! in_array( $subdirectory, [ 'previews', 'vdp' ], true ) ) {
			return null;
		}
		$real = self::canonical_file( $path );
		if ( null === $real ) {
			return null;
		}
		foreach ( self::legacy_private_roots() as $root ) {
			$base = $root . '/' . $subdirectory;
			if ( realpath( $base ) === $base && self::within( $real, $base ) ) {
				return $real;
			}
		}
		return null;
	}

	/** Copy a known source into verified current storage, never overwrite or remove a source. */
	public static function copy_known_private_file( string $source, string $subdirectory, string $filename, int $limit, ?string $expected_hash = null ): ?string {
		$source = self::known_private_file( $source, $subdirectory );
		$pattern = 'previews' === $subdirectory ? '/^preview-[a-f0-9]{40}\.(?:png|jpg)$/D' : '/^vdp-relocated-[a-f0-9]{40}\.csv$/D';
		if ( null === $source || ! preg_match( $pattern, $filename ) || $limit <= 0 || $limit > 10 * 1024 * 1024 ) {
			return null;
		}
		$directory = OC_Upload_Handler::private_storage_path( $subdirectory, true );
		if ( null === $directory ) {
			self::report( $subdirectory, 'Relocation blocked: verified current private storage is unavailable; source retained.' );
			return null;
		}
		clearstatcache( true, $source );
		$size = filesize( $source );
		if ( false === $size || $size < 1 || $size > $limit ) {
			self::report( $directory, 'Relocation source is empty, unreadable or exceeds the bounded copy limit.' );
			return null;
		}
		$bytes = file_get_contents( $source, false, null, 0, $limit + 1 );
		$hash = is_string( $bytes ) ? hash( 'sha256', $bytes ) : '';
		if ( ! is_string( $bytes ) || strlen( $bytes ) !== $size || ( null !== $expected_hash && ! hash_equals( $expected_hash, $hash ) ) ) {
			self::report( $directory, 'Relocation source hash did not match; source retained.' );
			return null;
		}
		$target = $directory . '/' . $filename;
		if ( file_exists( $target ) || is_link( $target ) ) {
			return self::canonical_file( $target ) === $target && filesize( $target ) === $size && hash_file( 'sha256', $target ) === $hash ? $target : null;
		}
		$tmp = $directory . '/.oc-relocation-' . bin2hex( random_bytes( 20 ) );
		try {
			if ( file_put_contents( $tmp, $bytes, LOCK_EX ) !== $size || filesize( $tmp ) !== $size || hash_file( 'sha256', $tmp ) !== $hash
				|| ! chmod( $tmp, 0640 ) ) {
				self::report( $directory, 'Relocation copy verification failed; source retained.' );
				return null;
			}
			// Same-filesystem hard-link publication is atomic and cannot overwrite a racing writer.
			if ( ! @link( $tmp, $target ) && ( self::canonical_file( $target ) !== $target || filesize( $target ) !== $size || hash_file( 'sha256', $target ) !== $hash ) ) {
				self::report( $directory, 'Atomic no-overwrite relocation publication failed; source retained.' );
				return null;
			}
			self::report( $directory, 'Private copy published; old source retained pending reference-safe cleanup and any required HTTP denial/purge.' );
			return $target;
		} finally {
			if ( is_file( $tmp ) ) {
				@unlink( $tmp );
			}
		}
	}

	/** Optional operator/advisory check, never a runtime storage prerequisite or deny-file proof. */
	public static function public_subtree_verified( string $root ): bool {
		$real = realpath( $root );
		if ( false === $real || ! is_dir( $real ) || wp_normalize_path( $real ) !== rtrim( wp_normalize_path( $root ), '/' ) ) {
			return false;
		}
		$uploads = wp_upload_dir();
		$base = ! empty( $uploads['basedir'] ) ? realpath( $uploads['basedir'] ) : false;
		if ( false === $base || ! self::within( $root, $base ) || $root === $base ) {
			return false;
		}
		if ( true === apply_filters( 'oc_private_storage_web_protected', false, $root ) ) {
			return true;
		}
		foreach ( self::legacy_private_roots() as $parent ) {
			if ( self::within( $parent, $base ) && self::within( $root, $parent ) ) {
				$root = $parent;
				break;
			}
		}
		// A false/non-boolean result is not evidence. The operator may also disable probes.
		if ( true === apply_filters( 'oc_private_storage_web_protected', false, $root ) ) {
			return true;
		}
		$url = rtrim( (string) ( $uploads['baseurl'] ?? '' ), '/' );
		$relative = substr( wp_normalize_path( $root ), strlen( wp_normalize_path( $base ) ) + 1 );
		$root_url = $url . '/' . implode( '/', array_map( 'rawurlencode', explode( '/', $relative ) ) );
		$context = self::context( $root, 'public-advisory-v2:' . $root_url );
		if ( true !== apply_filters( 'oc_storage_automatic_http_verification', true, $root, $root_url ) ) {
			return self::report( $root, 'Automatic HTTP verification disabled; exact-root operator verification required.' );
		}
		$cached = self::evidence( $context );
		if ( null !== $cached ) {
			self::$reports[ $root ] = $cached['detail'];
			return false;
		}
		$url_parts = parse_url( $url );
		if ( ! is_array( $url_parts ) || 'https' !== ( $url_parts['scheme'] ?? '' ) || empty( $url_parts['host'] )
			|| isset( $url_parts['user'] ) || isset( $url_parts['pass'] ) || isset( $url_parts['query'] ) || isset( $url_parts['fragment'] )
			|| ! function_exists( 'wp_safe_remote_get' ) ) {
			return self::report( $root, 'Automatic verification requires a reachable HTTPS uploads URL; configure exact-root operator verification.' );
		}
		if ( self::$probed ) {
			return self::report( $root, 'HTTP verification deferred by the per-request probe budget.' );
		}
		$lock_path = $root . '/.oc-http-verification.lock';
		if ( is_link( $lock_path ) ) {
			return false;
		}
		$lock = @fopen( $lock_path, 'c' );
		if ( false === $lock ) {
			return self::report( $root, 'Cannot lock HTTP storage verification.' );
		}
		$files = [];
		try {
			if ( ! flock( $lock, LOCK_EX | LOCK_NB ) ) {
				return self::report( $root, 'HTTP storage verification is already running.' );
			}
			$cached = self::evidence( $context );
			if ( null !== $cached ) {
				self::$reports[ $root ] = $cached['detail'];
				return false;
			}
			self::$probed = true;
			$ok = false;
			$message = 'Advisory HTTP check failed or was inconclusive. Recursive public storage requires explicit operator verification of all directory/content routing.';
			// Set a signed backoff before I/O, including process interruption.
			self::remember( $context, false, $message, 300 );
			$nonce = bin2hex( random_bytes( 20 ) );
			$content = 'OverCustomise harmless storage check ' . $nonce;
			$names = [ 'oc-control-' . $nonce . '.txt' ];
			foreach ( [ 'pdf', 'png', 'jpg', 'jpeg', 'webp', 'svg', 'eps', 'heic', 'heif', 'csv', 'dst', 'zip', 'txt', 'bin', 'pdf.oc-backup-' . $nonce ] as $extension ) {
				$names[] = 'oc-denied-' . $nonce . '.' . $extension;
			}
			$deadline = microtime( true ) + 5;
			foreach ( $names as $index => $name ) {
				if ( microtime( true ) >= $deadline ) {
					return self::report( $root, $message );
				}
				$file = ( 0 === $index ? $base : $root ) . '/' . $name;
				$handle = @fopen( $file, 'x' );
				if ( false === $handle ) {
					return self::report( $root, $message );
				}
				$files[] = $file;
				$written = fwrite( $handle, $content );
				fclose( $handle );
				if ( strlen( $content ) !== $written || ! chmod( $file, 0644 ) ) {
					return self::report( $root, $message );
				}
				$response = wp_safe_remote_get( ( 0 === $index ? $url : $root_url ) . '/' . $name, [
					'timeout' => 2, 'redirection' => 0, 'limit_response_size' => 1024, 'cookies' => [],
					'sslverify' => true, 'headers' => [ 'Cache-Control' => 'no-cache, no-store' ],
				] );
				if ( is_wp_error( $response ) ) {
					return self::report( $root, $message );
				}
				$status = wp_remote_retrieve_response_code( $response );
				$body = wp_remote_retrieve_body( $response );
				$ok = 0 === $index ? 200 === $status && hash_equals( $content, $body )
					: in_array( $status, [ 403, 404 ], true ) && ! str_contains( $body, $nonce );
				if ( ! $ok ) {
					return self::report( $root, $message );
				}
			}
			$message = 'Advisory canaries were denied, but child routing and content rejection remain unproven. Recursive public storage still requires explicit operator attestation.';
			self::remember( $context, false, $message, self::TTL );
			self::$reports[ $root ] = $message;
			return false;
		} finally {
			foreach ( $files as $file ) {
				@unlink( $file );
			}
			flock( $lock, LOCK_UN );
			fclose( $lock );
		}
	}
}
