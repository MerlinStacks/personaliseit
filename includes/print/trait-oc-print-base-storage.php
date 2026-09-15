<?php
/**
 * Shared storage helpers for print file generators.
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

trait OC_Print_Base_Storage {

	// -------------------------------------------------------------------------
	// Output directory management
	// -------------------------------------------------------------------------

	/**
	 * Ensure the per-order output directory exists and is protected.
	 *
	 * @param  int    $order_id
	 * @return string Absolute path to the directory (no trailing slash).
	 */
	protected static function ensure_output_dir( int $order_id ): string {
		$base = class_exists( 'OC_Upload_Handler' ) ? OC_Upload_Handler::private_storage_path( 'print-files', true ) : null;
		if ( null === $base ) {
			throw new \RuntimeException( esc_html__( 'Could not protect print directory.', 'overcustomise' ) );
		}

		$order_token = substr( hash_hmac( 'sha256', (string) $order_id, wp_salt( 'auth' ) ), 0, 32 );
		$dir         = $base . '/' . $order_token;

		if ( ! wp_mkdir_p( $dir ) ) {
			throw new \RuntimeException( __( 'Could not create print output directory.', 'overcustomise' ) );
		}

		$real = realpath( $dir );
		if ( false === $real || is_link( $dir ) || ! self::path_is_within( $real, $base ) ) {
			throw new \RuntimeException( 'Print output directory escaped private storage.' );
		}
		return $real;
	}

	/** Existing print roots only. History is operator code configuration, never DB paths. */
	public static function output_migration_roots(): array {
		require_once dirname( __DIR__ ) . '/class-oc-storage-upgrade.php';
		$uploads = wp_upload_dir();
		$base = ! empty( $uploads['basedir'] ) ? realpath( $uploads['basedir'] ) : false;
		$candidates = array_map( static fn ( string $root ): string => $root . '/print-files', OC_Storage_Upgrade::legacy_private_roots() );
		if ( false !== $base ) {
			$candidates[] = $base . '/' . self::PRINT_SUBDIR;
		}
		// Source inventory only. This must never authorize serving or recursive deletion.
		return array_values( array_filter( array_unique( $candidates ), static fn ( string $root ): bool => is_dir( $root ) && realpath( $root ) === $root ) );
	}

	/** Canonical serving roots with deny files; unknown custom history remains opt-in. */
	public static function output_storage_roots( bool $force_check = false ): array {
		require_once dirname( __DIR__ ) . '/class-oc-storage-upgrade.php';
		$roots = [];
		$current = class_exists( 'OC_Upload_Handler' ) ? OC_Upload_Handler::private_storage_path( 'print-files', $force_check ) : null;
		if ( null !== $current ) {
			$roots[] = $current;
		}
		$uploads = wp_upload_dir();
		$upload_real = ! empty( $uploads['basedir'] ) ? realpath( $uploads['basedir'] ) : false;
		$known = self::output_migration_roots();
		$roots = array_merge( $roots, $known );
		// Returning a root attests that it remains dedicated print storage and HTTP-denied.
		$history = apply_filters( 'oc_print_historical_storage_roots', [] );
		foreach ( is_array( $history ) ? $history : [] as $root ) {
			if ( ! is_string( $root ) || str_contains( $root, "\0" ) || ! self::is_absolute_file_path( $root ) ) {
				continue;
			}
			$root = rtrim( wp_normalize_path( $root ), '/' );
			$real = realpath( $root );
			if ( false !== $real && is_dir( $real ) && 'print-files' === basename( $real ) && wp_normalize_path( $real ) === $root ) {
				$roots[] = $real;
			}
		}
		// Automatic policy checks local deny files, not HTTP protection or canaries.
		return array_values( array_filter( array_unique( $roots ), static function ( string $root ) use ( $upload_real, $known ): bool {
			if ( false !== $upload_real && self::path_is_within( $root, $upload_real ) ) {
				if ( ! self::protect_output_root( $root, true ) ) {
					return false;
				}
				OC_Storage_Upgrade::report( $root, 'Automatic storage is operational; direct HTTP protection has not been verified.' );
			} elseif ( in_array( $root, $known, true ) ) {
				$parent = dirname( $root );
				$document_root = ! empty( $_SERVER['DOCUMENT_ROOT'] ) && is_string( $_SERVER['DOCUMENT_ROOT'] ) ? realpath( $_SERVER['DOCUMENT_ROOT'] ) : false;
				if ( ! OC_Storage_Upgrade::private_root_verified( $parent, $parent, $document_root ) || ! self::protect_output_root( $root, true ) ) {
					return false;
				}
			}
			return true;
		} ) );
	}

	/** Resolve retained absolute paths without basename fallback or symlink aliases. */
	public static function resolve_output_storage_path( string $path, bool $force_check = false ): ?string {
		require_once dirname( __DIR__ ) . '/class-oc-storage-upgrade.php';
		if ( '' === $path || str_contains( $path, "\0" ) || ! self::is_absolute_file_path( $path ) ) {
			return null;
		}
		$real = OC_Storage_Upgrade::canonical_file( $path );
		if ( null === $real ) {
			return null;
		}
		foreach ( self::output_storage_roots( $force_check ) as $root ) {
			if ( self::path_is_within( $real, $root ) ) {
				return $real;
			}
		}
		return null;
	}

	/** Run storage protection from the WordPress init action. */
	public static function maintain_output_storage(): void {
		self::ensure_output_storage_protected();
	}

	/** Ensure existing and future print files are denied by Apache and IIS. */
	public static function ensure_output_storage_protected( bool $force_check = false ): bool {
		$private = class_exists( 'OC_Upload_Handler' ) ? OC_Upload_Handler::private_storage_path( 'print-files', $force_check ) : null;
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
			return false;
		}

		$base = trailingslashit( (string) $uploads['basedir'] ) . self::PRINT_SUBDIR;
		$real = realpath( $base );
		$uploads_real = realpath( $uploads['basedir'] );
		if ( false !== $real && ( false === $uploads_real || wp_normalize_path( $real ) !== wp_normalize_path( $uploads_real . '/' . self::PRINT_SUBDIR ) || ! self::protect_output_root( $real, $force_check ) ) ) {
			OC_Logger::warning( 'Generated print storage could not be protected.' );
			return false;
		}
		if ( false !== $real ) {
			OC_Storage_Upgrade::report( $real, 'Automatic storage is operational; direct HTTP protection has not been verified.' );
		}

		return null !== $private;
	}

	/** Create the print root and write server-specific deny rules. */
	private static function protect_output_root( string $base, bool $force_check = false ): bool {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- Local path validation is required before WP_Filesystem credentials are available.
		if ( ( ! is_dir( $base ) && ! wp_mkdir_p( $base ) ) || ! is_writable( $base ) ) {
			return false;
		}
		$base       = rtrim( wp_normalize_path( $base ), '/' );
		foreach ( [ '.htaccess', 'web.config', 'index.php' ] as $filename ) {
			if ( is_link( $base . '/' . $filename ) ) {
				return false;
			}
		}
		$path_hash  = hash( 'sha256', $base );
		$marker_key = 'oc_storage_protection_' . $path_hash;
		$marker     = get_option( $marker_key, [] );
		if ( ! $force_check && is_array( $marker )
			&& self::PROTECTION_MARKER_VERSION === (int) ( $marker['version'] ?? 0 )
			&& hash_equals( $path_hash, (string) ( $marker['path'] ?? '' ) )
			&& (int) ( $marker['verified_at'] ?? 0 ) > time() - self::PROTECTION_MARKER_TTL
		) {
			return true;
		}

		$files = [
			'.htaccess' => "Options -Indexes\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n",
			'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?><configuration><system.webServer><security><authorization><remove users=\"*\" roles=\"\" verbs=\"\"/><add accessType=\"Deny\" users=\"*\"/></authorization></security></system.webServer></configuration>\n",
			'index.php' => "<?php\nhttp_response_code( 404 );\nexit;\n",
		];
		foreach ( $files as $filename => $contents ) {
			$path = $base . '/' . $filename;
			if ( is_link( $path ) ) {
				return false;
			}
			if ( ( ! is_file( $path ) || (string) file_get_contents( $path ) !== $contents ) && strlen( $contents ) !== file_put_contents( $path, $contents ) ) {
				return false;
			}
		}

		update_option(
			$marker_key,
			[
				'version'     => self::PROTECTION_MARKER_VERSION,
				'path'        => $path_hash,
				'verified_at' => time(),
			],
			false
		);
		return true;
	}

	/** Build a stable output filename for a print file. */
	protected static function build_filename( \WC_Order $order, int $item_id, object $area, string $extension ): string {
		return sprintf(
			'%s-p%d.%s',
			self::order_filename_part( $order ),
			self::print_file_position( $order, $item_id, $area ),
			sanitize_file_name( $extension )
		);
	}

	/** Return the customer-facing order number as a safe filename segment. */
	protected static function order_filename_part( \WC_Order $order ): string {
		$order_number = (string) $order->get_order_number();
		$order_number = sanitize_file_name( $order_number );

		return '' !== $order_number ? $order_number : (string) $order->get_id();
	}

	/** Return this print file's 1-based position within its order. */
	protected static function print_file_position( \WC_Order $order, int $item_id, object $area ): int {
		global $wpdb;

		if ( empty( $wpdb ) || ! method_exists( $wpdb, 'get_results' ) || ! method_exists( $wpdb, 'prepare' ) ) {
			return 1;
		}

		$area_id = (int) ( $area->id ?? 0 );
		if ( $area_id <= 0 ) {
			return 1;
		}

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT order_item_id, print_area_id FROM {$wpdb->prefix}oc_print_files WHERE order_id = %d ORDER BY id ASC",
			(int) $order->get_id()
		) );

		if ( ! is_array( $rows ) || empty( $rows ) ) {
			return 1;
		}

		$position = 1;
		foreach ( $rows as $row ) {
			if ( (int) $row->order_item_id === $item_id && (int) $row->print_area_id === $area_id ) {
				return $position;
			}
			$position++;
		}

		return $position;
	}

	/** Create a temporary file, loading WP's file API when queue runners have not done so. */
	protected static function temp_path( string $filename ): string|false {
		if ( ! function_exists( 'wp_tempnam' ) && defined( 'ABSPATH' ) ) {
			$file_api = ABSPATH . 'wp-admin/includes/file.php';
			if ( file_exists( $file_api ) ) {
				require_once $file_api;
			}
		}

		if ( function_exists( 'wp_tempnam' ) ) {
			return wp_tempnam( $filename );
		}

		return tempnam( sys_get_temp_dir(), $filename ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_tempnam
	}

	/** Return a writable temporary path that keeps the requested extension for file-type detection. */
	protected static function temp_path_with_extension( string $filename, string $extension ): string|false {
		$temp = self::temp_path( $filename );
		if ( ! is_string( $temp ) || '' === $temp ) {
			return false;
		}

		$extension = ltrim( strtolower( $extension ), '.' );
		if ( '' === $extension || $extension === strtolower( pathinfo( $temp, PATHINFO_EXTENSION ) ) ) {
			return $temp;
		}

		$typed_temp = $temp . '.' . $extension;
		if ( file_exists( $typed_temp ) ) {
			@unlink( $typed_temp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		if ( ! @rename( $temp, $typed_temp ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			@unlink( $temp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return false;
		}

		return $typed_temp;
	}

	/**
	 * Resolve artwork path for print renderers.
	 *
	 * Accepts either:
	 * - artworkAttachmentId (media library attachment)
	 * - artworkPath (absolute path under uploads)
	 */
	protected static function resolve_artwork_path( array $area_data ): ?string {
		if ( ! empty( $area_data['artworkAttachmentId'] ) ) {
			$attachment_path = self::resolve_attachment_artwork_path( (int) $area_data['artworkAttachmentId'] );
			if ( $attachment_path ) {
				return self::production_artwork_path( $attachment_path, $area_data, (int) $area_data['artworkAttachmentId'] );
			}
			throw new \RuntimeException( __( 'The selected production artwork attachment is missing or unreadable.', 'overcustomise' ) );
		}

		if ( ! empty( $area_data['artworkPath'] ) && is_string( $area_data['artworkPath'] ) ) {
			$real = self::resolve_uploads_file_path( $area_data['artworkPath'] );
			if ( $real ) {
				return self::production_artwork_path( $real, $area_data );
			}
			throw new \RuntimeException( __( 'The selected production artwork path is missing or outside protected storage.', 'overcustomise' ) );
		}

		return null;
	}

	/**
	 * Resolve PDF/EPS originals to an explicitly retained production derivative.
	 * Production must never silently replace accepted artwork with a placeholder.
	 */
	private static function production_artwork_path( string $path, array $area_data, int $attachment_id = 0 ): string {
		$extension = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		if ( ! in_array( $extension, [ 'pdf', 'eps' ], true ) ) {
			return $path;
		}

		$path_keys = [ 'artworkDerivativePath', 'derivativePath', 'artworkPreviewPath', 'previewPath' ];
		foreach ( $path_keys as $key ) {
			if ( ! empty( $area_data[ $key ] ) && is_string( $area_data[ $key ] ) ) {
				$derivative = self::resolve_uploads_file_path( $area_data[ $key ] );
				if ( $derivative && self::is_supported_production_derivative( $derivative ) ) {
					return $derivative;
				}
			}
		}

		$id_keys = [ 'artworkDerivativeAttachmentId', 'derivativeAttachmentId', 'previewAttachmentId' ];
		foreach ( $id_keys as $key ) {
			$derivative_id = absint( $area_data[ $key ] ?? 0 );
			$derivative    = $derivative_id ? self::resolve_attachment_artwork_path( $derivative_id ) : null;
			if ( $derivative && self::is_supported_production_derivative( $derivative ) ) {
				return $derivative;
			}
		}

		if ( $attachment_id > 0 && function_exists( 'get_post_meta' ) ) {
			foreach ( [ '_oc_print_derivative_attachment_id', '_oc_artwork_preview_attachment_id' ] as $meta_key ) {
				$derivative_id = absint( get_post_meta( $attachment_id, $meta_key, true ) );
				$derivative    = $derivative_id ? self::resolve_attachment_artwork_path( $derivative_id ) : null;
				if ( $derivative && self::is_supported_production_derivative( $derivative ) ) {
					return $derivative;
				}
			}
		}

		$stem = pathinfo( $path, PATHINFO_DIRNAME ) . '/' . pathinfo( $path, PATHINFO_FILENAME );
		foreach ( [ '-preview.png', '-preview.jpg', '-preview.webp', '-derivative.png' ] as $suffix ) {
			$derivative = self::resolve_uploads_file_path( $stem . $suffix );
			if ( $derivative && self::is_supported_production_derivative( $derivative ) ) {
				return $derivative;
			}
		}

		throw new \RuntimeException(
			sprintf(
				__( 'Production rendering does not support the %1$s original "%2$s" without a safe PNG, JPEG, WEBP, or SVG derivative.', 'overcustomise' ),
				strtoupper( $extension ),
				basename( $path )
			)
		);
	}

	/** Return whether a resolved derivative is supported by all production renderers. */
	private static function is_supported_production_derivative( string $path ): bool {
		return is_readable( $path ) && in_array( strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ), [ 'png', 'jpg', 'jpeg', 'webp', 'gif', 'bmp', 'svg' ], true );
	}

	/** Resolve a media-library artwork attachment, tolerating stale absolute upload paths. */
	protected static function resolve_attachment_artwork_path( int $attachment_id ): ?string {
		if ( $attachment_id <= 0 ) {
			return null;
		}

		$attachment_path = get_attached_file( $attachment_id );
		if ( is_string( $attachment_path ) && '' !== $attachment_path ) {
			$real = self::resolve_uploads_file_path( $attachment_path );
			if ( $real && self::attachment_storage_path_is_allowed( $attachment_id, $real ) ) {
				return $real;
			}
		}

		$attached_file = get_post_meta( $attachment_id, '_wp_attached_file', true );
		if ( is_string( $attached_file ) && '' !== $attached_file ) {
			$real = self::resolve_uploads_file_path( $attached_file );
			if ( $real && self::attachment_storage_path_is_allowed( $attachment_id, $real ) ) {
				return $real;
			}
		}

		return null;
	}

	/** Resolve a path to private artwork or a bounded uploads-relative production asset. */
	protected static function resolve_uploads_file_path( string $path ): ?string {
		$uploads           = wp_upload_dir();
		$base_real         = ! empty( $uploads['basedir'] ) ? realpath( $uploads['basedir'] ) : false;
		$private_available = class_exists( 'OC_Upload_Handler' ) && function_exists( 'apply_filters' ) && function_exists( 'wp_normalize_path' );
		$private_root      = $private_available
			? OC_Upload_Handler::private_storage_path()
			: null;
		$private_real = $private_available
			? OC_Upload_Handler::private_storage_path( 'artwork' )
			: null;
		if ( ! $base_real && null === $private_real ) {
			return null;
		}
		$base_real    = $base_real ? rtrim( $base_real, '/\\' ) : '';
		$private_root = null !== $private_root ? rtrim( $private_root, '/\\' ) : '';
		$private_real = null !== $private_real ? rtrim( $private_real, '/\\' ) : '';

		$candidates = [ $path ];
		if ( ! self::is_absolute_file_path( $path ) ) {
			if ( '' !== $base_real ) {
				$candidates[] = trailingslashit( (string) $uploads['basedir'] ) . ltrim( $path, '/\\' );
			}
			if ( '' !== $private_root ) {
				$candidates[] = $private_root . DIRECTORY_SEPARATOR . ltrim( $path, '/\\' );
			}
			if ( '' !== $private_real ) {
				$candidates[] = $private_real . DIRECTORY_SEPARATOR . ltrim( $path, '/\\' );
			}
		}

		$normalised = str_replace( '\\', '/', $path );
		$marker_pos = strpos( $normalised, '/uploads/' );
		if ( false !== $marker_pos ) {
			$relative = substr( $normalised, $marker_pos + strlen( '/uploads/' ) );
			if ( '' !== $relative ) {
				if ( '' !== $base_real ) {
					$candidates[] = trailingslashit( (string) $uploads['basedir'] ) . ltrim( $relative, '/\\' );
				}
			}
		}

		foreach ( array_unique( $candidates ) as $candidate ) {
			$real       = realpath( $candidate );
			$in_uploads = $real && '' !== $base_real && str_starts_with( $real, $base_real . DIRECTORY_SEPARATOR );
			$in_private = $real && '' !== $private_real && str_starts_with( $real, $private_real . DIRECTORY_SEPARATOR );
			if ( $real && ( $in_uploads || $in_private ) && is_file( $real ) && is_readable( $real ) ) {
				return $real;
			}
		}

		return null;
	}

	/** Customer artwork must stay in current private storage or the protected legacy root. */
	private static function attachment_storage_path_is_allowed( int $attachment_id, string $path ): bool {
		if ( 1 !== (int) get_post_meta( $attachment_id, '_oc_artwork', true ) ) {
			return true;
		}

		return class_exists( 'OC_Upload_Handler' ) && OC_Upload_Handler::is_allowed_artwork_path( $path );
	}

	private static function is_absolute_file_path( string $path ): bool {
		return str_starts_with( $path, '/' ) || (bool) preg_match( '#^[A-Za-z]:[\\\\/]#D', $path );
	}

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
}
