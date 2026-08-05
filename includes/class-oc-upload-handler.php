<?php
/**
 * Upload Handler — processes customer artwork uploads.
 *
 * Handles: SVG, PDF, EPS, PNG, JPG/JPEG, WebP, HEIC/HEIF
 *
 * Flow:
 *  1. Validate file type via finfo (real MIME, not extension).
 *  2. Validate file size against plugin setting.
 *  3. SVG  → sanitise via OC_SVG_Sanitiser → save to WP media library.
 *  4. PDF/EPS → convert page 1 to PNG preview via Imagick or GhostScript.
 *  5. HEIC/HEIF → convert to an auto-oriented JPEG.
 *  6. PNG/JPG/WebP → save directly to WP media library.
 *  7. Return structured result: { attachment_id, preview_url, original_url, file_type }.
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

class OC_Upload_Handler {
	private const MAX_IMAGE_DIMENSION = 12000;
	private const MAX_IMAGE_PIXELS = 40000000;
	private const MAX_GENERATED_IMAGE_BYTES = 15728640;
	private const ACCESS_URL_TTL = DAY_IN_SECONDS;
	private const STORAGE_VERSION = 2;
	private const FALLBACK_STORAGE_TOKEN_OPTION = 'oc_private_storage_token';

	/** Nonce action used to authenticate upload requests. */
	public const NONCE_ACTION = 'oc_upload_artwork';

	/** Legacy subdirectory within WP uploads. New artwork is never written here. */
	private const UPLOAD_SUBDIR = 'overcustomise/artwork';
	private const PRIVATE_ARTWORK_SUBDIR = 'artwork';

	/** Supported file types and their normalised type keys. */
	private const SUPPORTED_TYPES = [
		'image/svg+xml'              => 'svg',
		'text/xml'                   => 'svg', // Some browsers send this for SVG
		'application/pdf'            => 'pdf',
		'application/postscript'     => 'eps',
		'application/eps'            => 'eps',
		'application/x-eps'          => 'eps',
		'image/x-eps'                => 'eps',
		'image/png'                  => 'png',
		'image/jpeg'                 => 'jpg',
		'image/jpg'                  => 'jpg',
		'image/webp'                 => 'webp',
		'image/heic'                 => 'heic',
		'image/heif'                 => 'heic',
		'image/heic-sequence'        => 'heic',
		'image/heif-sequence'        => 'heic',
	];

	/** Allowed file extensions mapped to canonical type keys. */
	private const EXT_TO_TYPE = [
		'svg'  => 'svg',
		'pdf'  => 'pdf',
		'eps'  => 'eps',
		'png'  => 'png',
		'jpg'  => 'jpg',
		'jpeg' => 'jpg',
		'webp' => 'webp',
		'heic' => 'heic',
		'heif' => 'heic',
	];

	/** Keep original/production-derivative attachment lifecycles linked. */
	public static function register(): void {
		add_action( 'init', [ self::class, 'ensure_private_storage' ] );
		add_filter( 'pre_delete_attachment', [ self::class, 'prevent_referenced_artwork_deletion' ], 10, 3 );
		add_action( 'delete_attachment', [ self::class, 'delete_related_artwork_attachments' ] );
		add_action( 'admin_post_oc_serve_artwork', [ self::class, 'serve_artwork' ] );
		add_action( 'admin_post_nopriv_oc_serve_artwork', [ self::class, 'serve_artwork' ] );
	}

	/** Prevent any deletion route from removing artwork retained by an order or cart. */
	public static function prevent_referenced_artwork_deletion( mixed $delete, \WP_Post $post, bool $force_delete ): mixed {
		if ( null !== $delete || 1 !== (int) get_post_meta( $post->ID, '_oc_artwork', true ) ) {
			return $delete;
		}

		return OC_File_Cleanup::customer_artwork_is_referenced( (int) $post->ID ) ? false : null;
	}

	/** Delete a document derivative with its original, or unlink a derivative deleted directly. */
	public static function delete_related_artwork_attachments( int $attachment_id ): void {
		self::delete_private_attachment_file( $attachment_id );

		$parent_id = absint( get_post_meta( $attachment_id, '_oc_artwork_parent_id', true ) );
		if ( $parent_id > 0 ) {
			foreach ( [ '_oc_print_derivative_attachment_id', '_oc_artwork_preview_attachment_id' ] as $meta_key ) {
				if ( $attachment_id === absint( get_post_meta( $parent_id, $meta_key, true ) ) ) {
					delete_post_meta( $parent_id, $meta_key );
				}
			}
			return;
		}

		$related_ids = [];
		foreach ( [ '_oc_print_derivative_attachment_id', '_oc_artwork_preview_attachment_id' ] as $meta_key ) {
			$related_ids[] = absint( get_post_meta( $attachment_id, $meta_key, true ) );
			delete_post_meta( $attachment_id, $meta_key );
		}
		foreach ( array_unique( array_filter( $related_ids ) ) as $related_id ) {
			if ( $attachment_id === absint( get_post_meta( $related_id, '_oc_artwork_parent_id', true ) ) ) {
				delete_post_meta( $related_id, '_oc_artwork_parent_id' );
				wp_delete_attachment( $related_id, true );
			}
		}
	}

	/** WordPress only deletes attachment files inside uploads, so remove private files explicitly. */
	private static function delete_private_attachment_file( int $attachment_id ): void {
		if ( 1 !== (int) get_post_meta( $attachment_id, '_oc_artwork', true ) ) {
			return;
		}

		$path = get_attached_file( $attachment_id );
		$real = is_string( $path ) ? realpath( $path ) : false;
		$base = self::private_storage_path( self::PRIVATE_ARTWORK_SUBDIR );
		if ( false === $real || null === $base || ! is_file( $real ) || ! self::path_is_within( $real, $base ) ) {
			return;
		}

		if ( ! @unlink( $real ) && is_file( $real ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			OC_Logger::warning( 'A private customer artwork file could not be deleted: ' . basename( $real ) );
		}
	}

	/** Return a short-lived same-origin URL for customer artwork. */
	public static function attachment_access_url( int $attachment_id, bool $download = false ): string {
		if ( $attachment_id <= 0 ) {
			return '';
		}
		if ( 1 !== (int) get_post_meta( $attachment_id, '_oc_artwork', true ) ) {
			return (string) wp_get_attachment_url( $attachment_id );
		}
		if ( ! self::artwork_file_is_valid( $attachment_id ) ) {
			return '';
		}

		$secret = (string) get_post_meta( $attachment_id, '_oc_artwork_owner_secret', true );
		if ( '' === $secret ) {
			return '';
		}
		$expires   = time() + self::ACCESS_URL_TTL;
		$download_value = $download ? 1 : 0;
		$signature      = hash_hmac( 'sha256', $attachment_id . '|' . $expires . '|' . $download_value, $secret );

		return add_query_arg(
			[
				'action'        => 'oc_serve_artwork',
				'attachment_id' => $attachment_id,
				'expires'       => $expires,
				'signature'     => $signature,
				'download'      => $download_value,
			],
			admin_url( 'admin-post.php' )
		);
	}

	/** Stream one signed customer attachment without exposing its storage URL. */
	public static function serve_artwork(): void {
		$attachment_id = absint( $_GET['attachment_id'] ?? 0 );
		$expires       = absint( $_GET['expires'] ?? 0 );
		$signature     = sanitize_text_field( wp_unslash( $_GET['signature'] ?? '' ) );
		$download      = ! empty( $_GET['download'] ) ? 1 : 0;
		$secret        = (string) get_post_meta( $attachment_id, '_oc_artwork_owner_secret', true );
		$expected      = '' !== $secret ? hash_hmac( 'sha256', $attachment_id . '|' . $expires . '|' . $download, $secret ) : '';

		if (
			$attachment_id <= 0
			|| 1 !== (int) get_post_meta( $attachment_id, '_oc_artwork', true )
			|| $expires < time()
			|| $expires > time() + self::ACCESS_URL_TTL + 300
			|| 64 !== strlen( $signature )
			|| '' === $expected
			|| ! hash_equals( $expected, $signature )
		) {
			wp_die( esc_html__( 'Artwork is not available.', 'overcustomise' ), '', [ 'response' => 404 ] );
		}

		if ( ! self::artwork_file_is_valid( $attachment_id ) ) {
			wp_die( esc_html__( 'Artwork is not available.', 'overcustomise' ), '', [ 'response' => 404 ] );
		}
		$path = (string) get_attached_file( $attachment_id );

		$stored_mime = (string) get_post_mime_type( $attachment_id );
		$type_key    = self::SUPPORTED_TYPES[ $stored_mime ] ?? '';
		$mime        = match ( $type_key ) {
			'svg'  => 'image/svg+xml',
			'pdf'  => 'application/pdf',
			'eps'  => 'application/postscript',
			'png'  => 'image/png',
			'jpg'  => 'image/jpeg',
			'webp' => 'image/webp',
			'heic' => 'image/heic',
			default => 'application/octet-stream',
		};
		header( 'Content-Type: ' . $mime );
		if ( $download ) {
			$original_name = sanitize_file_name( (string) get_post_meta( $attachment_id, '_oc_artwork_original_name', true ) );
			$extension     = self::normalise_extension( pathinfo( $path, PATHINFO_EXTENSION ) );
			$original_name = '' !== $original_name ? $original_name : 'artwork' . ( $extension ? '.' . $extension : '' );
			header( 'Content-Disposition: attachment; filename="' . $original_name . '"' );
		} else {
			header( 'Content-Disposition: inline' );
		}
		$size = filesize( $path );
		if ( false === $size || $size <= 0 ) {
			wp_die( esc_html__( 'Artwork is not available.', 'overcustomise' ), '', [ 'response' => 404 ] );
		}
		header( 'Content-Length: ' . $size );
		header( 'Cache-Control: private, max-age=300' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Cross-Origin-Resource-Policy: same-origin' );
		header( 'Referrer-Policy: no-referrer' );
		header( "Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; sandbox" );
		while ( ob_get_level() ) {
			ob_end_clean();
		}
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
	}

	/**
	 * Return the configured private storage root, creating it when safe.
	 *
	 * The preferred default is a site-specific sibling of ABSPATH. When PHP cannot
	 * create it, an unguessable, deny-protected uploads directory is used instead.
	 * Explicitly configured or filtered paths remain fail-closed.
	 */
	public static function private_storage_root(): ?string {
		$default_root = dirname( rtrim( ABSPATH, '/\\' ) ) . DIRECTORY_SEPARATOR
			. '.overcustomise-private-' . substr( hash( 'sha256', wp_normalize_path( ABSPATH ) ), 0, 12 );
		$configured   = defined( 'OC_PRIVATE_STORAGE_ROOT' ) ? OC_PRIVATE_STORAGE_ROOT : $default_root;
		$filtered     = apply_filters( 'oc_private_storage_root', $configured, $default_root );
		$root         = self::prepare_storage_root( $filtered );
		if ( null !== $root ) {
			return $root;
		}

		if ( defined( 'OC_PRIVATE_STORAGE_ROOT' ) || $filtered !== $default_root ) {
			return null;
		}

		return self::protected_uploads_storage_root();
	}

	/** Validate and create a storage root, optionally deferring public-path checks to the caller. */
	private static function prepare_storage_root( mixed $path, bool $allow_public_path = false ): ?string {
		if ( ! is_string( $path ) || '' === trim( $path ) || str_contains( $path, "\0" ) ) {
			return null;
		}

		$candidate = rtrim( wp_normalize_path( trim( $path ) ), '/' );
		if ( ! self::is_absolute_path( $candidate ) || '/' === $candidate || preg_match( '#^[A-Za-z]:$#D', $candidate ) ) {
			return null;
		}

		$abspath = rtrim( wp_normalize_path( realpath( ABSPATH ) ?: ABSPATH ), '/' );
		if ( ! $allow_public_path && ( self::path_is_within( $candidate, $abspath, true ) || self::path_is_within( $abspath, $candidate, true ) ) ) {
			return null;
		}

		$uploads      = wp_upload_dir();
		$uploads_base = empty( $uploads['error'] ) && ! empty( $uploads['basedir'] )
			? rtrim( wp_normalize_path( realpath( (string) $uploads['basedir'] ) ?: (string) $uploads['basedir'] ), '/' )
			: '';
		if ( ! $allow_public_path && '' !== $uploads_base
			&& ( self::path_is_within( $candidate, $uploads_base, true ) || self::path_is_within( $uploads_base, $candidate, true ) )
		) {
			return null;
		}

		if ( ( ! is_dir( $candidate ) && ! wp_mkdir_p( $candidate ) ) || ! is_writable( $candidate ) ) {
			return null;
		}
		$real = realpath( $candidate );
		if ( false === $real ) {
			return null;
		}
		$real = rtrim( wp_normalize_path( $real ), '/' );
		if ( ! $allow_public_path && ( self::path_is_within( $real, $abspath, true ) || self::path_is_within( $abspath, $real, true )
			|| ( '' !== $uploads_base && ( self::path_is_within( $real, $uploads_base, true ) || self::path_is_within( $uploads_base, $real, true ) ) )
		) ) {
			return null;
		}

		@chmod( $real, 0750 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		return $real;
	}

	/** Create a deny-protected fallback beneath uploads when no private parent is writable. */
	private static function protected_uploads_storage_root(): ?string {
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
			return null;
		}

		$uploads_real = realpath( (string) $uploads['basedir'] );
		if ( false === $uploads_real || ! is_dir( $uploads_real ) || ! is_writable( $uploads_real ) ) {
			return null;
		}

		$token = self::fallback_storage_token();
		if ( null === $token ) {
			return null;
		}
		$directory   = rtrim( wp_normalize_path( $uploads_real ), '/' )
			. '/.overcustomise-private-' . $token;
		$root        = self::prepare_storage_root( $directory, true );
		$uploads_real = rtrim( wp_normalize_path( $uploads_real ), '/' );
		if ( null === $root || ! self::path_is_within( $root, $uploads_real ) || ! self::protect_artwork_directory( $root ) ) {
			return null;
		}

		return $root;
	}

	/** Return a persistent random token so salt rotation cannot orphan stored files. */
	private static function fallback_storage_token(): ?string {
		$token = get_option( self::FALLBACK_STORAGE_TOKEN_OPTION, '' );
		if ( is_string( $token ) && preg_match( '/^[a-z0-9]{32}$/D', $token ) ) {
			return $token;
		}

		$generated = strtolower( wp_generate_password( 32, false, false ) );
		if ( ! preg_match( '/^[a-z0-9]{32}$/D', $generated ) ) {
			return null;
		}
		if ( add_option( self::FALLBACK_STORAGE_TOKEN_OPTION, $generated, '', false ) ) {
			return $generated;
		}

		$token = get_option( self::FALLBACK_STORAGE_TOKEN_OPTION, '' );
		return is_string( $token ) && preg_match( '/^[a-z0-9]{32}$/D', $token ) ? $token : null;
	}

	/** Whether a validated storage path uses the protected public-uploads fallback. */
	private static function storage_path_uses_uploads_fallback( string $path ): bool {
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
			return false;
		}

		$uploads_real = realpath( (string) $uploads['basedir'] );
		return false !== $uploads_real && self::path_is_within( $path, wp_normalize_path( $uploads_real ) );
	}

	/** Return a validated private subdirectory for other plugin components. */
	public static function private_storage_path( string $subdirectory = '' ): ?string {
		$root = self::private_storage_root();
		if ( null === $root ) {
			return null;
		}

		$subdirectory = trim( wp_normalize_path( $subdirectory ), '/' );
		if ( '' === $subdirectory ) {
			return $root;
		}
		if ( ! preg_match( '#^[a-z0-9][a-z0-9/_-]*$#D', $subdirectory ) || str_contains( $subdirectory, '..' ) ) {
			return null;
		}

		$directory = $root . '/' . $subdirectory;
		if ( ( ! is_dir( $directory ) && ! wp_mkdir_p( $directory ) ) || ! is_writable( $directory ) ) {
			return null;
		}
		$real = realpath( $directory );
		if ( false === $real || ! self::path_is_within( wp_normalize_path( $real ), $root ) ) {
			return null;
		}
		if ( self::storage_path_uses_uploads_fallback( $root ) && ! self::protect_artwork_directory( $real ) ) {
			return null;
		}

		@chmod( $real, 0750 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		return rtrim( wp_normalize_path( $real ), '/' );
	}

	/** Protect the legacy root and migrate legacy artwork records in bounded batches. */
	public static function ensure_private_storage(): void {
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
			OC_Logger::warning( 'Legacy artwork storage could not be inspected.' );
			return;
		}

		$legacy_directory = trailingslashit( (string) $uploads['basedir'] ) . self::UPLOAD_SUBDIR;
		$uploads_real     = realpath( (string) $uploads['basedir'] );
		$legacy_real      = is_dir( $legacy_directory ) ? realpath( $legacy_directory ) : false;
		if ( false !== $legacy_real ) {
			if ( false === $uploads_real || ! self::path_is_within( $legacy_real, $uploads_real ) ) {
				OC_Logger::warning( 'Legacy customer artwork storage resolved outside the uploads root.' );
			} elseif ( ! self::protect_artwork_directory( $legacy_real ) ) {
				OC_Logger::warning( 'Legacy customer artwork storage could not be protected.' );
			}
		}

		$private_directory = self::private_storage_path( self::PRIVATE_ARTWORK_SUBDIR );
		if ( null === $private_directory ) {
			OC_Logger::warning( 'Private customer storage is unavailable.' );
			return;
		}
		if ( self::STORAGE_VERSION === (int) get_option( 'oc_private_artwork_storage_version', 0 ) ) {
			return;
		}
		$limit      = 50;
		$legacy_ids = get_posts( [
			'post_type'      => 'attachment',
			'post_status'    => [ 'private', 'inherit' ],
			'posts_per_page' => $limit,
			'fields'         => 'ids',
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'meta_query'     => [
				'relation' => 'AND',
				[ 'key' => '_oc_artwork', 'value' => '1' ],
				[
					'relation' => 'OR',
					[ 'key' => '_oc_private_storage_version', 'compare' => 'NOT EXISTS' ],
					[ 'key' => '_oc_private_storage_version', 'value' => (string) self::STORAGE_VERSION, 'compare' => '!=' ],
				],
			],
		] );
		$migration_ok = true;
		foreach ( array_map( 'absint', $legacy_ids ) as $attachment_id ) {
			if ( ! self::migrate_legacy_attachment( $attachment_id, $private_directory ) ) {
				$migration_ok = false;
			}
		}

		if ( $migration_ok && count( $legacy_ids ) < $limit ) {
			update_option( 'oc_private_artwork_storage_version', self::STORAGE_VERSION, false );
		}
	}

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Validate an upload without persisting it and return a conservative storage reservation.
	 *
	 * @return array{type:string,source_bytes:int,reservation_bytes:int,attachment_count:int}
	 * @throws \RuntimeException When validation fails or a safe reservation cannot be calculated.
	 */
	public static function inspect_upload( array $_file, ?array $overrides = null ): array {
		self::validate( $_file, $overrides );
		$mime     = self::detect_mime( (string) $_file['tmp_name'], (string) $_file['name'] );
		$type_key = self::SUPPORTED_TYPES[ $mime ] ?? null;
		if ( null === $type_key ) {
			throw new \RuntimeException( __( 'Unsupported artwork format.', 'overcustomise' ) );
		}

		$jpeg_orientation = 1;
		if ( in_array( $type_key, [ 'png', 'jpg', 'webp' ], true ) ) {
			$image_info = @getimagesize( (string) $_file['tmp_name'] );
			if ( ! is_array( $image_info ) || (string) ( $image_info['mime'] ?? '' ) !== $mime ) {
				throw new \RuntimeException( __( 'File is not a valid image.', 'overcustomise' ) );
			}
			self::validate_image_dimensions( (int) $image_info[0], (int) $image_info[1] );
			if ( 'jpg' === $type_key ) {
				$jpeg_orientation = self::jpeg_exif_orientation( (string) $_file['tmp_name'] );
			}
		} elseif ( 'heic' === $type_key ) {
			if ( ! self::heic_content_is_valid( (string) $_file['tmp_name'] ) ) {
				throw new \RuntimeException( __( 'The Apple photo content is not a valid HEIC or HEIF image.', 'overcustomise' ) );
			}
			if ( ! self::heic_conversion_is_available() ) {
				throw new \RuntimeException( __( 'Apple HEIC/HEIF photos require ImageMagick with HEIC support on this server.', 'overcustomise' ) );
			}
		} elseif ( 'svg' === $type_key ) {
			$raw = file_get_contents( (string) $_file['tmp_name'] );
			if ( false === $raw ) {
				throw new \RuntimeException( __( 'Uploaded artwork could not be read.', 'overcustomise' ) );
			}
			try {
				OC_SVG_Sanitiser::sanitise( $raw );
			} catch ( \Throwable $e ) {
				throw new \RuntimeException( __( 'The SVG file is not safe to process.', 'overcustomise' ) );
			}
		} elseif ( in_array( $type_key, [ 'pdf', 'eps' ], true ) ) {
			if ( ! self::document_content_is_valid( (string) $_file['tmp_name'], $type_key ) ) {
				throw new \RuntimeException( __( 'The document content does not match its artwork format.', 'overcustomise' ) );
			}
		}

		$source_bytes = (int) filesize( (string) $_file['tmp_name'] );
		$count        = in_array( $type_key, [ 'pdf', 'eps' ], true ) ? 2 : 1;
		$reservation = 'heic' === $type_key ? self::MAX_GENERATED_IMAGE_BYTES : $source_bytes;
		if ( 'jpg' === $type_key && $jpeg_orientation > 1 ) {
			$reservation = self::jpeg_orientation_reservation_bytes( $source_bytes, (int) $image_info[0], (int) $image_info[1] );
		}
		if ( 2 === $count ) {
			$preview_limit = self::filtered_positive_int( 'oc_document_preview_max_bytes', 20 * 1024 * 1024, 1024, 100 * 1024 * 1024 );
			if ( null === $preview_limit ) {
				throw new \RuntimeException( __( 'Artwork storage is unavailable.', 'overcustomise' ) );
			}
			$reservation += $preview_limit;
		}

		return [
			'type'             => $type_key,
			'source_bytes'     => $source_bytes,
			'reservation_bytes' => $reservation,
			'attachment_count' => $count,
		];
	}

	/** Conservatively reserve JPEG encoder output without charging every small photo the global maximum. */
	private static function jpeg_orientation_reservation_bytes( int $source_bytes, int $width, int $height ): int {
		$encoded_upper_bound = ( $width * $height * 4 ) + 65536;
		return min( self::MAX_GENERATED_IMAGE_BYTES, max( $source_bytes, $encoded_upper_bound ) );
	}

	/**
	 * Process an uploaded file.
	 *
	 * @param  array      $_file     An element from $_FILES.
	 * @param  array|null $overrides Per-layer validation and processing overrides.
	 * @return array{attachment_id:int,preview_url:string,original_url:string,file_type:string,preview_attachment_id?:int,related_attachment_ids?:array<int,int>}
	 * @throws \RuntimeException On validation or processing failure.
	 */
	public static function process( array $_file, ?array $overrides = null, array $context = [] ): array {
		$inspection = self::inspect_upload( $_file, $overrides );
		$type_key   = $inspection['type'];
		$result      = [];
		$base_result = [];
		try {
			$base_result = match ( $type_key ) {
				'svg'   => self::process_svg( $_file ),
				'pdf'   => self::process_pdf_eps( $_file, 'pdf' ),
				'eps'   => self::process_pdf_eps( $_file, 'eps' ),
				'heic'  => self::process_heic( $_file ),
				default => self::process_raster( $_file, $type_key ),
			};
			$result = $base_result;

			if ( ! empty( $overrides['remove_background'] ) ) {
				$filtered = apply_filters( 'oc_upload_remove_background', $result, $_file, $type_key );
				if ( ! is_array( $filtered ) || absint( $filtered['attachment_id'] ?? 0 ) !== absint( $base_result['attachment_id'] ?? 0 ) ) {
					throw new \RuntimeException( __( 'The background-removal result could not be validated.', 'overcustomise' ) );
				}
				$result = $filtered;
			}

			$attachment_id = absint( $result['attachment_id'] ?? 0 );
			if ( $attachment_id <= 0 || 'attachment' !== get_post_type( $attachment_id ) ) {
				throw new \RuntimeException( __( 'The artwork result could not be validated.', 'overcustomise' ) );
			}
			$related_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) ( $result['related_attachment_ids'] ?? [] ) ) ) ) );
			$preview_id  = absint( $result['preview_attachment_id'] ?? $attachment_id );
			if ( ! in_array( $preview_id, array_merge( [ $attachment_id ], $related_ids ), true ) ) {
				throw new \RuntimeException( __( 'The artwork preview result could not be validated.', 'overcustomise' ) );
			}
			$stored_name  = (string) ( $result['stored_name'] ?? $_file['name'] ?? '' );
			$ownership_ok = self::record_ownership( $attachment_id, $context, $stored_name );
			foreach ( $related_ids as $related_id ) {
				if ( $related_id > 0 && 'attachment' === get_post_type( $related_id ) ) {
					$ownership_ok = self::record_ownership( $related_id, $context, $stored_name ) && $ownership_ok;
				} else {
					$ownership_ok = false;
				}
			}
			if ( ! $ownership_ok ) {
				throw new \RuntimeException( __( 'Artwork ownership could not be recorded.', 'overcustomise' ) );
			}
			if ( '' === self::attachment_fingerprint( $attachment_id ) ) {
				throw new \RuntimeException( __( 'Artwork fingerprint could not be recorded.', 'overcustomise' ) );
			}

			$result['preview_url']  = self::attachment_access_url( $preview_id );
			$result['original_url'] = self::attachment_access_url( $attachment_id );
			unset( $result['stored_name'] );
			if ( '' === $result['preview_url'] || '' === $result['original_url'] ) {
				throw new \RuntimeException( __( 'Secure artwork URLs could not be created.', 'overcustomise' ) );
			}

			return $result;
		} catch ( \Throwable $e ) {
			if ( $result ) {
				self::delete_result_attachments( $result );
			}
			if ( $base_result && $base_result !== $result ) {
				self::delete_result_attachments( $base_result );
			}
			if ( $e instanceof \RuntimeException ) {
				throw $e;
			}
			throw new \RuntimeException( __( 'Artwork processing failed unexpectedly.', 'overcustomise' ), 0, $e );
		}
	}

	/** Save an AI-generated raster image as owned customer artwork. */
	public static function save_generated_image( string $bytes, string $mime, array $context, array $provenance = [], bool $remove_background = false ): array|\WP_Error {
		$extensions = [
			'image/png'  => 'png',
			'image/jpeg' => 'jpg',
			'image/webp' => 'webp',
		];
		if ( ! isset( $extensions[ $mime ] ) || '' === $bytes || strlen( $bytes ) > self::MAX_GENERATED_IMAGE_BYTES ) {
			return new \WP_Error( 'invalid_generated_image', __( 'The generated image is invalid.', 'overcustomise' ) );
		}

		$info = @getimagesizefromstring( $bytes );
		if ( ! is_array( $info ) || (string) ( $info['mime'] ?? '' ) !== $mime ) {
			return new \WP_Error( 'invalid_generated_image', __( 'The generated image is invalid.', 'overcustomise' ) );
		}
		self::validate_image_dimensions( (int) $info[0], (int) $info[1] );

		$tmp = self::temp_path( 'oc-ai-image-' );
		if ( false === $tmp || strlen( $bytes ) !== file_put_contents( $tmp, $bytes, LOCK_EX ) ) {
			if ( is_string( $tmp ) && is_file( $tmp ) ) {
				@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
			return new \WP_Error( 'generated_image_save_failed', __( 'Could not stage the generated image.', 'overcustomise' ) );
		}
		if ( $remove_background ) {
			$processed = self::remove_background_via_imagick( $tmp );
			if ( is_wp_error( $processed ) ) {
				@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				return $processed;
			}
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$tmp  = $processed;
			$mime = 'image/png';
		}

		try {
			$attachment_id = self::save_to_media_library( $tmp, 'ai-filter.' . $extensions[ $mime ], $mime );
		} finally {
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		if ( ! self::record_ownership( $attachment_id, $context, 'ai-filter.' . $extensions[ $mime ] ) ) {
			wp_delete_attachment( $attachment_id, true );
			return new \WP_Error( 'generated_image_save_failed', __( 'Could not retain the generated image.', 'overcustomise' ) );
		}
		$provenance_ok = update_post_meta( $attachment_id, '_oc_ai_filter', 1 )
			&& update_post_meta( $attachment_id, '_oc_ai_filter_source_id', absint( $provenance['source_attachment_id'] ?? 0 ) )
			&& update_post_meta( $attachment_id, '_oc_ai_filter_id', absint( $provenance['filter_id'] ?? 0 ) )
			&& update_post_meta( $attachment_id, '_oc_ai_filter_attempt', absint( $provenance['attempt'] ?? 0 ) )
			&& update_post_meta( $attachment_id, '_oc_ai_filter_group', sanitize_key( (string) ( $provenance['group'] ?? '' ) ) )
			&& update_post_meta( $attachment_id, '_oc_ai_filter_model', sanitize_text_field( (string) ( $provenance['model'] ?? '' ) ) );
		if ( ! $provenance_ok ) {
			wp_delete_attachment( $attachment_id, true );
			return new \WP_Error( 'generated_image_save_failed', __( 'Could not retain the generated image.', 'overcustomise' ) );
		}

		$url = self::attachment_access_url( $attachment_id );
		if ( '' === $url ) {
			wp_delete_attachment( $attachment_id, true );
			return new \WP_Error( 'generated_image_save_failed', __( 'Could not retain the generated image.', 'overcustomise' ) );
		}
		return [
			'attachment_id' => $attachment_id,
			'preview_url'   => $url,
			'original_url'  => $url,
			'file_type'     => $extensions[ $mime ],
		];
	}

	/** Return the immutable context in which customer artwork was created. */
	public static function attachment_primary_context( int $attachment_id ): ?array {
		$context = self::normalise_artwork_context( get_post_meta( $attachment_id, '_oc_artwork_context', true ) );
		return null !== $context && $context[0] > 0 && $context[2] > 0 && $context[3] > 0 ? $context : null;
	}

	/** Confirm an exact context is either primary or explicitly granted. */
	public static function attachment_context_is_authorised( int $attachment_id, array $context ): bool {
		$context = self::normalise_artwork_context( $context );
		if ( null === $context ) {
			return false;
		}
		$primary = self::attachment_primary_context( $attachment_id );
		if ( $context === $primary ) {
			return true;
		}
		if ( null === $primary || $context[0] !== $primary[0] ) {
			return false;
		}

		$stored = self::normalise_artwork_context( get_post_meta( $attachment_id, self::context_grant_meta_key( $context ), true ) );
		return $stored === $context;
	}

	/** Add an idempotent same-product authorization for another exact layer context. */
	public static function authorise_attachment_context( int $attachment_id, array $context ): bool {
		$context = self::normalise_artwork_context( $context );
		$primary = self::attachment_primary_context( $attachment_id );
		if ( null === $context || null === $primary || $context[0] !== $primary[0] ) {
			return false;
		}
		if ( self::attachment_context_is_authorised( $attachment_id, $context ) ) {
			return true;
		}

		$key = self::context_grant_meta_key( $context );
		update_post_meta( $attachment_id, $key, $context );
		return self::normalise_artwork_context( get_post_meta( $attachment_id, $key, true ) ) === $context;
	}

	/** Ensure persisted artwork meets a destination layer's format and size policy. */
	public static function attachment_matches_upload_policy( int $attachment_id, array $policy, bool $validate_content = true ): bool {
		if ( $validate_content && ! self::artwork_file_is_valid( $attachment_id ) ) {
			return false;
		}
		$formats     = is_array( $policy['formats'] ?? null ) ? array_map( 'strtolower', $policy['formats'] ) : [];
		$type        = self::SUPPORTED_TYPES[ (string) get_post_mime_type( $attachment_id ) ] ?? '';
		$source_type = sanitize_key( (string) get_post_meta( $attachment_id, '_oc_artwork_source_type', true ) );
		if ( 'jpg' === $type && array_intersect( [ 'jpg', 'jpeg' ], $formats ) ) {
			$formats[] = 'jpg';
		}
		$max_bytes = absint( $policy['max_size_mb'] ?? 0 ) * 1024 * 1024;
		$path      = get_attached_file( $attachment_id );
		$stored_size = is_string( $path ) && is_file( $path ) ? filesize( $path ) : false;
		$source_size = absint( get_post_meta( $attachment_id, '_oc_artwork_source_bytes', true ) );
		$size        = $source_size > 0 ? $source_size : $stored_size;

		return '' !== $type && ( in_array( $type, $formats, true ) || in_array( $source_type, $formats, true ) )
			&& $max_bytes > 0 && false !== $stored_size && $stored_size > 0 && $size <= $max_bytes;
	}

	/** Verify that customer artwork belongs to this customer and exact layer context. */
	public static function attachment_is_accepted( int $attachment_id, int $product_id, int $variation_id, int $design_id, int $layer_id, string $token = '' ): bool {
		if ( ! self::artwork_file_is_valid( $attachment_id ) ) return false;
		$context = [ $product_id, $variation_id, $design_id, $layer_id ];
		if ( ! self::attachment_context_is_authorised( $attachment_id, $context ) ) return false;
		return self::attachment_owner_matches( $attachment_id, $token, $context );
	}

	/** Verify ownership of artwork posted by the legacy product customiser. */
	public static function legacy_attachment_is_accepted( int $attachment_id, int $product_id, int $variation_id, string $token = '' ): bool {
		if ( ! self::artwork_file_is_valid( $attachment_id ) ) return false;
		$actual = array_values( array_map( 'intval', (array) get_post_meta( $attachment_id, '_oc_artwork_context', true ) ) );
		if ( [ $product_id, $variation_id, 0, 0 ] !== $actual ) return false;
		return self::attachment_owner_matches( $attachment_id, $token, $actual );
	}

	private static function attachment_owner_matches( int $attachment_id, string $token, array $context ): bool {
		$user_id = (int) get_post_meta( $attachment_id, '_oc_artwork_user_id', true );
		if ( $user_id > 0 && is_user_logged_in() && $user_id === get_current_user_id() ) {
			return true;
		}

		$stored_session  = (string) get_post_meta( $attachment_id, '_oc_artwork_session', true );
		$current_session = self::session_hash();
		if ( 64 === strlen( $stored_session ) && 64 === strlen( $current_session ) && hash_equals( $stored_session, $current_session ) ) {
			return true;
		}
		if ( ! class_exists( 'OC_Rest_API' ) ) {
			return false;
		}

		if ( '' === $token ) {
			$token = OC_Rest_API::current_session_public_token();
		}
		return '' !== $token && OC_Rest_API::public_token_owns_attachment( $token, $attachment_id, $context );
	}

	/** Convert an artwork context into a strict four-integer tuple. */
	private static function normalise_artwork_context( mixed $context ): ?array {
		if ( ! is_array( $context ) || 4 !== count( $context ) ) {
			return null;
		}
		$context = array_values( array_map( 'intval', $context ) );
		return $context[0] > 0 && $context[1] >= 0 && $context[2] > 0 && $context[3] > 0 ? $context : null;
	}

	/** Build a direct, collision-resistant post-meta key for one context grant. */
	private static function context_grant_meta_key( array $context ): string {
		return '_oc_artwork_context_grant_' . hash( 'sha256', implode( '|', $context ) );
	}

	private static function artwork_file_is_valid( int $attachment_id ): bool {
		if ( 1 !== (int) get_post_meta( $attachment_id, '_oc_artwork', true ) ) return false;
		$path = get_attached_file( $attachment_id );
		if ( ! is_string( $path ) || ! self::is_allowed_artwork_path( $path ) ) return false;
		return self::artwork_content_is_valid( $path, (string) get_post_mime_type( $attachment_id ) );
	}

	/** Revalidate persisted artwork bytes independently of their storage location. */
	private static function artwork_content_is_valid( string $path, string $mime ): bool {
		if ( ! is_file( $path ) ) {
			return false;
		}
		$detected_mime = self::detect_mime( $path, basename( $path ) );
		if ( ! isset( self::SUPPORTED_TYPES[ $mime ], self::SUPPORTED_TYPES[ $detected_mime ] ) || self::SUPPORTED_TYPES[ $mime ] !== self::SUPPORTED_TYPES[ $detected_mime ] ) {
			return false;
		}
		$size = filesize( $path );
		if ( false === $size || $size <= 0 || $size > 100 * 1024 * 1024 ) {
			return false;
		}

		$type = self::SUPPORTED_TYPES[ $mime ];
		if ( in_array( $type, [ 'png', 'jpg', 'webp' ], true ) ) {
			$info = @getimagesize( $path );
			if ( ! is_array( $info ) || (string) ( $info['mime'] ?? '' ) !== $detected_mime ) {
				return false;
			}
			try {
				self::validate_image_dimensions( (int) $info[0], (int) $info[1] );
			} catch ( \RuntimeException $e ) {
				return false;
			}
		} elseif ( 'svg' === $type ) {
			$raw = file_get_contents( $path );
			if ( ! is_string( $raw ) ) {
				return false;
			}
			try {
				$clean = OC_SVG_Sanitiser::sanitise( $raw );
			} catch ( \Throwable $e ) {
				return false;
			}
			if ( ! hash_equals( $raw, $clean ) ) {
				return false;
			}
		} elseif ( in_array( $type, [ 'pdf', 'eps' ], true ) ) {
			if ( ! self::document_content_is_valid( $path, $type ) ) {
				return false;
			}
		}

		return true;
	}

	/** Validate PDF and EPS signatures, including DOS binary EPS wrappers. */
	private static function document_content_is_valid( string $path, string $type ): bool {
		$header = file_get_contents( $path, false, null, 0, 1024 );
		if ( ! is_string( $header ) ) {
			return false;
		}
		if ( 'pdf' === $type ) {
			return str_starts_with( $header, '%PDF-' );
		}
		if ( 'eps' !== $type ) {
			return false;
		}

		if ( str_starts_with( $header, "\xC5\xD0\xD3\xC6" ) ) {
			$binary_header = unpack( 'Voffset/Vlength', substr( $header, 4, 8 ) );
			$file_size     = filesize( $path );
			$offset        = (int) ( $binary_header['offset'] ?? 0 );
			$length        = (int) ( $binary_header['length'] ?? 0 );
			if ( false === $file_size || $offset < 30 || $length <= 0 || $offset + $length > $file_size ) {
				return false;
			}
			$header = file_get_contents( $path, false, null, $offset, min( 1024, $length ) );
			if ( ! is_string( $header ) ) {
				return false;
			}
		}

		return str_starts_with( $header, '%!PS-Adobe-' )
			&& ( false !== strpos( substr( $header, 0, 256 ), 'EPSF-' )
				|| 1 === preg_match( '/^%%BoundingBox:\s+(?:-?\d|\(atend\))/m', $header ) );
	}

	/** Confirm a raster attachment is safe for the paid AI image service. */
	public static function ai_source_is_valid( int $attachment_id, bool $require_customer_storage = true ): bool {
		$path = get_attached_file( $attachment_id );
		if ( ! is_string( $path ) || ! is_file( $path ) || ( $require_customer_storage && ! self::is_allowed_artwork_path( $path ) ) ) {
			return false;
		}
		$mime = (string) get_post_mime_type( $attachment_id );
		if ( ! in_array( $mime, [ 'image/jpeg', 'image/png', 'image/webp' ], true ) || self::detect_mime( $path, basename( $path ) ) !== $mime ) {
			return false;
		}
		$size = filesize( $path );
		$info = @getimagesize( $path );
		if ( false === $size || $size <= 0 || $size > 15 * 1024 * 1024 || ! is_array( $info ) || (string) ( $info['mime'] ?? '' ) !== $mime ) {
			return false;
		}
		try {
			self::validate_image_dimensions( (int) $info[0], (int) $info[1] );
			return true;
		} catch ( \RuntimeException $e ) {
			return false;
		}
	}

	/** Return a stable SHA-256 fingerprint for an artwork attachment. */
	public static function attachment_fingerprint( int $attachment_id ): string {
		$stored = strtolower( trim( (string) get_post_meta( $attachment_id, '_oc_artwork_fingerprint', true ) ) );
		if ( preg_match( '/^[a-f0-9]{64}$/D', $stored ) ) {
			return $stored;
		}

		$path = get_attached_file( $attachment_id );
		if ( ! is_string( $path ) || ! is_file( $path ) ) {
			return '';
		}
		$fingerprint = hash_file( 'sha256', $path );
		if ( ! is_string( $fingerprint ) || ! preg_match( '/^[a-f0-9]{64}$/D', $fingerprint ) ) {
			return '';
		}
		if ( 1 === (int) get_post_meta( $attachment_id, '_oc_artwork', true )
			&& ! update_post_meta( $attachment_id, '_oc_artwork_fingerprint', $fingerprint )
		) {
			return '';
		}
		return $fingerprint;
	}

	/** Admin-configured defaults are immutable and need validity, not customer ownership. */
	public static function admin_default_attachment_is_valid( int $attachment_id ): bool {
		$path = get_attached_file( $attachment_id );
		if ( ! is_string( $path ) || ! is_file( $path ) ) return false;
		$stored   = (string) get_post_mime_type( $attachment_id );
		$detected = self::detect_mime( $path, basename( $path ) );
		return isset( self::SUPPORTED_TYPES[ $stored ], self::SUPPORTED_TYPES[ $detected ] ) && self::SUPPORTED_TYPES[ $stored ] === self::SUPPORTED_TYPES[ $detected ];
	}

	private static function record_ownership( int $attachment_id, array $context, string $original_name = '' ): bool {
		$token_hash = is_string( $context['token_hash'] ?? null ) ? strtolower( trim( $context['token_hash'] ) ) : '';
		if ( '' !== $token_hash && ! preg_match( '/^[a-f0-9]{64}$/D', $token_hash ) ) {
			return false;
		}

		$stored = update_post_meta( $attachment_id, '_oc_artwork_context', [
			absint( $context['product_id'] ?? 0 ), absint( $context['variation_id'] ?? 0 ),
			absint( $context['design_id'] ?? 0 ), absint( $context['layer_id'] ?? 0 ),
		] );
		$stored = update_post_meta( $attachment_id, '_oc_artwork_user_id', get_current_user_id() ) && $stored;
		$stored = update_post_meta( $attachment_id, '_oc_artwork_session', self::session_hash() ) && $stored;
		$stored = update_post_meta( $attachment_id, '_oc_artwork_token', $token_hash ) && $stored;
		$stored = update_post_meta( $attachment_id, '_oc_artwork_owner_secret', wp_generate_password( 64, false, false ) ) && $stored;
		$stored = update_post_meta( $attachment_id, '_oc_artwork_original_name', sanitize_file_name( basename( $original_name ) ) ) && $stored;
		return $stored;
	}

	/** Return persisted attachment IDs and byte sizes for authoritative token registration. */
	public static function result_attachment_usage( array $result ): array {
		$ids = array_values( array_unique( array_filter( array_map( 'absint', array_merge(
			[ $result['attachment_id'] ?? 0 ],
			(array) ( $result['related_attachment_ids'] ?? [] )
		) ) ) ) );
		$usage = [];
		foreach ( $ids as $attachment_id ) {
			$path = get_attached_file( $attachment_id );
			$size = is_string( $path ) ? filesize( $path ) : false;
			if ( ! self::artwork_file_is_valid( $attachment_id ) || ! is_string( $path ) || false === $size || $size <= 0 ) {
				return [];
			}
			$usage[ $attachment_id ] = (int) $size;
		}
		return $usage;
	}

	/** Delete every attachment created by a failed processing result. */
	public static function delete_result_attachments( array $result ): void {
		$ids = array_values( array_unique( array_filter( array_map( 'absint', array_merge(
			(array) ( $result['related_attachment_ids'] ?? [] ),
			[ $result['attachment_id'] ?? 0 ]
		) ) ) ) );
		foreach ( $ids as $attachment_id ) {
			if ( 'attachment' === get_post_type( $attachment_id ) ) {
				wp_delete_attachment( $attachment_id, true );
			}
		}
	}

	private static function session_hash(): string {
		$session = function_exists( 'WC' ) && WC() ? WC()->session ?? null : null;
		$id      = $session && method_exists( $session, 'get_customer_id' ) ? (string) $session->get_customer_id() : '';
		return '' !== $id ? hash_hmac( 'sha256', $id, wp_salt( 'auth' ) ) : '';
	}

	// -------------------------------------------------------------------------
	// Validators
	// -------------------------------------------------------------------------

	/** @throws \RuntimeException */
	private static function validate( array $_file, ?array $overrides = null ): void {
		if ( empty( $_file['tmp_name'] ) || ! is_uploaded_file( $_file['tmp_name'] ) ) {
			throw new \RuntimeException( __( 'No file uploaded.', 'overcustomise' ) );
		}

		// PHP upload error codes other than OK indicate truncation or failure.
		if ( isset( $_file['error'] ) && UPLOAD_ERR_OK !== (int) $_file['error'] ) {
			throw new \RuntimeException( __( 'Upload failed before reaching the server.', 'overcustomise' ) );
		}

		// basename() strips any directory components that may have slipped through.
		$name = isset( $_file['name'] ) ? basename( (string) $_file['name'] ) : '';
		if ( '' === $name ) {
			throw new \RuntimeException( __( 'Filename is missing.', 'overcustomise' ) );
		}

		$max_value = array_key_exists( 'max_size_mb', (array) $overrides )
			? $overrides['max_size_mb']
			: OC_Admin_Settings::get( 'max_upload_size_mb' );
		if ( ! is_int( $max_value ) && ! ( is_string( $max_value ) && preg_match( '/^[0-9]+$/D', $max_value ) ) ) {
			throw new \RuntimeException( __( 'Artwork upload limits are unavailable.', 'overcustomise' ) );
		}
		$max_mb = (int) $max_value;
		if ( $max_mb <= 0 || $max_mb > 100 ) {
			throw new \RuntimeException( __( 'Artwork upload limits are unavailable.', 'overcustomise' ) );
		}
		$max_bytes = $max_mb * 1024 * 1024;

		$actual_size = filesize( (string) $_file['tmp_name'] );
		$size        = (int) ( $_file['size'] ?? 0 );
		if ( false === $actual_size || $actual_size <= 0 || $size <= 0 || $size !== (int) $actual_size ) {
			throw new \RuntimeException( __( 'Uploaded file is empty.', 'overcustomise' ) );
		}
		if ( $actual_size > $max_bytes ) {
			throw new \RuntimeException(
				sprintf(
					/* translators: %d: max size in MB */
					__( 'File exceeds maximum size of %dMB.', 'overcustomise' ),
					$max_mb
				)
			);
		}

		$allowed_formats = array_key_exists( 'formats', (array) $overrides )
			? $overrides['formats']
			: OC_Admin_Settings::get( 'allowed_upload_formats' );
		if ( ! is_array( $allowed_formats ) ) {
			throw new \RuntimeException( __( 'Artwork upload formats are unavailable.', 'overcustomise' ) );
		}
		$normalised_formats = [];
		foreach ( $allowed_formats as $format ) {
			if ( ! is_string( $format ) ) {
				throw new \RuntimeException( __( 'Artwork upload formats are unavailable.', 'overcustomise' ) );
			}
			$format = self::normalise_extension( $format );
			if ( ! isset( self::EXT_TO_TYPE[ $format ] ) ) {
				throw new \RuntimeException( __( 'Artwork upload formats are unavailable.', 'overcustomise' ) );
			}
			$normalised_formats[] = $format;
		}
		$allowed_formats = array_values( array_unique( $normalised_formats ) );
		$ext             = self::normalise_extension( pathinfo( $name, PATHINFO_EXTENSION ) );
		if ( empty( $allowed_formats ) ) {
			throw new \RuntimeException( __( 'Artwork uploads are not enabled for this layer.', 'overcustomise' ) );
		}

		if ( '' === $ext ) {
			throw new \RuntimeException( __( 'File has no extension.', 'overcustomise' ) );
		}

		if ( ! in_array( $ext, $allowed_formats, true ) ) {
			throw new \RuntimeException(
				sprintf(
					/* translators: %s: file extension */
					__( 'File type .%s is not allowed.', 'overcustomise' ),
					$ext
				)
			);
		}

		$detected_mime = self::detect_mime( $_file['tmp_name'], $name );
		$detected_type = self::SUPPORTED_TYPES[ $detected_mime ] ?? null;
		if ( null === $detected_type ) {
			throw new \RuntimeException(
				sprintf( __( 'Unsupported file type: %s', 'overcustomise' ), $detected_mime )
			);
		}

		$ext_type = self::type_from_extension( $ext );
		if ( null === $ext_type ) {
			throw new \RuntimeException(
				sprintf(
					/* translators: %s: file extension */
					__( 'File type .%s is not supported.', 'overcustomise' ),
					$ext
				)
			);
		}

		if ( $detected_type !== $ext_type ) {
			throw new \RuntimeException(
				sprintf(
					/* translators: 1: extension, 2: detected mime */
					__( 'File extension .%1$s does not match detected content type (%2$s).', 'overcustomise' ),
					$ext,
					$detected_mime
				)
			);
		}
	}

	/** Normalise a file extension (lowercase, no leading dot). */
	private static function normalise_extension( string $ext ): string {
		return ltrim( strtolower( trim( $ext ) ), '.' );
	}

	/** Map a file extension to its canonical file type key. */
	private static function type_from_extension( string $ext ): ?string {
		$normalised = self::normalise_extension( $ext );
		return self::EXT_TO_TYPE[ $normalised ] ?? null;
	}

	/** Create a temporary file, loading WP's file API when the current request has not done so. */
	private static function temp_path( string $filename ): string|false {
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

	// -------------------------------------------------------------------------
	// Type-specific processors
	// -------------------------------------------------------------------------

	/** Process SVG: sanitise → save to WP media library. */
	private static function process_svg( array $_file ): array {
		$raw = file_get_contents( $_file['tmp_name'] );
		if ( false === $raw ) {
			throw new \RuntimeException( __( 'Could not read uploaded SVG.', 'overcustomise' ) );
		}

		try {
			$sanitised = OC_SVG_Sanitiser::sanitise( $raw );
		} catch ( \Throwable $e ) {
			throw new \RuntimeException( $e->getMessage() );
		}

		// Write sanitised content to a temp file so WP can handle the upload.
		$tmp = self::temp_path( 'oc-svg-' );
		if ( false === $tmp ) {
			throw new \RuntimeException( __( 'Could not stage sanitised SVG for upload.', 'overcustomise' ) );
		}
		if ( strlen( $sanitised ) !== file_put_contents( $tmp, $sanitised, LOCK_EX ) ) {
			@unlink( $tmp );
			throw new \RuntimeException( __( 'Could not stage sanitised SVG for upload.', 'overcustomise' ) );
		}

		try {
			$attachment_id = self::save_to_media_library(
				$tmp,
				sanitize_file_name( basename( (string) $_file['name'] ) ),
				'image/svg+xml'
			);
		} finally {
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		if ( is_wp_error( $attachment_id ) ) {
			throw new \RuntimeException( $attachment_id->get_error_message() );
		}

		return [
			'attachment_id' => $attachment_id,
			'preview_url'   => '',
			'original_url'  => '',
			'file_type'     => 'svg',
		];
	}

	/** Process PDF or EPS: convert page 1 to PNG preview, save both files. */
	private static function process_pdf_eps( array $_file, string $type ): array {
		$safe_name = sanitize_file_name( basename( (string) $_file['name'] ) );

		// Save the original file first.
		$original_id = self::save_to_media_library(
			$_file['tmp_name'],
			$safe_name,
			'pdf' === $type ? 'application/pdf' : 'application/postscript'
		);

		if ( is_wp_error( $original_id ) ) {
			throw new \RuntimeException( $original_id->get_error_message() );
		}

		$original_path = get_attached_file( $original_id );

		if ( ! $original_path || ! file_exists( $original_path ) ) {
			wp_delete_attachment( $original_id, true );
			throw new \RuntimeException( __( 'Saved original file could not be located.', 'overcustomise' ) );
		}

		// Generate PNG preview.
		$preview_info      = pathinfo( $original_path );
		$preview_path      = $preview_info['dirname'] . '/' . $preview_info['filename'] . '-preview.png';
		$preview_generated = false;

		if ( extension_loaded( 'imagick' ) ) {
			$preview_generated = self::convert_via_imagick( $original_path, $preview_path );
		}

		if ( ! $preview_generated ) {
			$preview_generated = self::convert_via_ghostscript( $original_path, $preview_path );
		}

		$preview_id = 0;
		if ( $preview_generated && file_exists( $preview_path ) ) {
			$preview_size  = filesize( $preview_path );
			$preview_limit = self::filtered_positive_int( 'oc_document_preview_max_bytes', 20 * 1024 * 1024, 1024, 100 * 1024 * 1024 );
			if ( false === $preview_size || null === $preview_limit || $preview_size <= 0 || $preview_size > $preview_limit ) {
				@unlink( $preview_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				wp_delete_attachment( $original_id, true );
				throw new \RuntimeException( __( 'The document preview exceeds the safe storage limit.', 'overcustomise' ) );
			}
			$preview_id = self::save_to_media_library(
				$preview_path,
				pathinfo( $safe_name, PATHINFO_FILENAME ) . '-preview.png',
				'image/png'
			);
			@unlink( $preview_path );

			if ( is_wp_error( $preview_id ) || ! $preview_id ) {
				wp_delete_attachment( $original_id, true );
				throw new \RuntimeException( __( 'The document preview could not be retained for production.', 'overcustomise' ) );
			}
		} else {
			@unlink( $preview_path );
			wp_delete_attachment( $original_id, true );
			throw new \RuntimeException( __( 'This server could not create the production derivative required for PDF or EPS artwork.', 'overcustomise' ) );
		}

		$linked = update_post_meta( $original_id, '_oc_print_derivative_attachment_id', (int) $preview_id )
			&& update_post_meta( $original_id, '_oc_artwork_preview_attachment_id', (int) $preview_id )
			&& update_post_meta( $preview_id, '_oc_artwork_parent_id', (int) $original_id );
		if ( ! $linked ) {
			wp_delete_attachment( $preview_id, true );
			wp_delete_attachment( $original_id, true );
			throw new \RuntimeException( __( 'The document production derivative could not be linked to its original.', 'overcustomise' ) );
		}

		return [
			'attachment_id'        => $original_id,
			'preview_attachment_id' => (int) $preview_id,
			'related_attachment_ids' => [ (int) $preview_id ],
			'preview_url'          => '',
			'original_url'         => '',
			'file_type'            => $type,
		];
	}

	/** Process PNG / JPG / WebP: validate and store a consistently oriented image. */
	private static function process_raster( array $_file, string $type ): array {
		// Confirm it is actually an image via getimagesize.
		$image_info = @getimagesize( $_file['tmp_name'] );
		if ( ! $image_info || empty( $image_info['mime'] ) ) {
			$err = error_get_last();
			OC_Logger::warning( 'Image validation failed: ' . ( $err['message'] ?? 'unknown error' ) );
			throw new \RuntimeException( __( 'File is not a valid image.', 'overcustomise' ) );
		}
		self::validate_image_dimensions( (int) $image_info[0], (int) $image_info[1] );

		$mime        = $image_info['mime'];
		$source_path = (string) $_file['tmp_name'];
		$staged_path = null;
		try {
			// Browsers disagree with server-side renderers about whether JPEG EXIF
			// orientation should be applied. Bake it into the pixels once so the
			// customiser, derivatives, and print output all use the same geometry.
			if ( 'image/jpeg' === $mime ) {
				$orientation = self::jpeg_exif_orientation( $source_path );
				if ( $orientation > 1 ) {
					$staged_path = self::normalise_jpeg_orientation( $source_path, $orientation, (int) $image_info[0], (int) $image_info[1] );
					$source_path = $staged_path;
					$image_info  = @getimagesize( $source_path );
					if ( ! is_array( $image_info ) || 'image/jpeg' !== (string) ( $image_info['mime'] ?? '' ) ) {
						throw new \RuntimeException( __( 'The photo orientation could not be normalised.', 'overcustomise' ) );
					}
					self::validate_image_dimensions( (int) $image_info[0], (int) $image_info[1] );
				}
			}

			$attachment_id = self::save_to_media_library(
				$source_path,
				sanitize_file_name( basename( (string) $_file['name'] ) ),
				$mime
			);
			if ( is_string( $staged_path ) && ! is_wp_error( $attachment_id ) ) {
				$source_size = filesize( (string) $_file['tmp_name'] );
				if ( false === $source_size || $source_size <= 0
					|| ! update_post_meta( (int) $attachment_id, '_oc_artwork_source_type', 'jpg' )
					|| ! update_post_meta( (int) $attachment_id, '_oc_artwork_source_bytes', (int) $source_size )
				) {
					wp_delete_attachment( (int) $attachment_id, true );
					throw new \RuntimeException( __( 'The photo source metadata could not be retained.', 'overcustomise' ) );
				}
			}
		} finally {
			if ( is_string( $staged_path ) ) {
				@unlink( $staged_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
		}

		if ( is_wp_error( $attachment_id ) ) {
			throw new \RuntimeException( $attachment_id->get_error_message() );
		}

		return [
			'attachment_id' => $attachment_id,
			'preview_url'   => '',
			'original_url'  => '',
			'file_type'     => $type,
		];
	}

	/** Read the orientation value from a JPEG EXIF APP1 segment without requiring ext-exif. */
	private static function jpeg_exif_orientation( string $path ): int {
		$handle = @fopen( $path, 'rb' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( false === $handle ) {
			return 1;
		}

		try {
			if ( "\xFF\xD8" !== fread( $handle, 2 ) ) {
				return 1;
			}
			while ( ! feof( $handle ) ) {
				$prefix = fread( $handle, 1 );
				if ( "\xFF" !== $prefix ) {
					continue;
				}
				do {
					$marker = fread( $handle, 1 );
				} while ( "\xFF" === $marker );
				if ( '' === $marker || "\xD9" === $marker || "\xDA" === $marker ) {
					break;
				}
				if ( ord( $marker ) >= 0xD0 && ord( $marker ) <= 0xD8 ) {
					continue;
				}

				$length_bytes = fread( $handle, 2 );
				if ( 2 !== strlen( $length_bytes ) ) {
					break;
				}
				$length = (int) ( unpack( 'nlength', $length_bytes )['length'] ?? 0 );
				if ( $length < 2 ) {
					break;
				}
				$payload_length = $length - 2;
				if ( "\xE1" !== $marker ) {
					if ( 0 !== fseek( $handle, $payload_length, SEEK_CUR ) ) {
						break;
					}
					continue;
				}

				$payload = fread( $handle, $payload_length );
				if ( strlen( $payload ) !== $payload_length || ! str_starts_with( $payload, "Exif\0\0" ) ) {
					continue;
				}
				$orientation = self::tiff_orientation( substr( $payload, 6 ) );
				if ( $orientation >= 1 && $orientation <= 8 ) {
					return $orientation;
				}
			}
		} finally {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		}

		return 1;
	}

	/** Extract an EXIF orientation from a TIFF header. */
	private static function tiff_orientation( string $tiff ): int {
		if ( strlen( $tiff ) < 8 ) {
			return 1;
		}
		$byte_order = substr( $tiff, 0, 2 );
		if ( ! in_array( $byte_order, [ 'II', 'MM' ], true ) ) {
			return 1;
		}
		$short_format = 'II' === $byte_order ? 'vvalue' : 'nvalue';
		$long_format  = 'II' === $byte_order ? 'Vvalue' : 'Nvalue';
		$read_short   = static function ( int $offset ) use ( $tiff, $short_format ): ?int {
			if ( $offset < 0 || $offset + 2 > strlen( $tiff ) ) {
				return null;
			}
			return (int) ( unpack( $short_format, substr( $tiff, $offset, 2 ) )['value'] ?? 0 );
		};
		$read_long = static function ( int $offset ) use ( $tiff, $long_format ): ?int {
			if ( $offset < 0 || $offset + 4 > strlen( $tiff ) ) {
				return null;
			}
			return (int) ( unpack( $long_format, substr( $tiff, $offset, 4 ) )['value'] ?? 0 );
		};

		if ( 42 !== $read_short( 2 ) ) {
			return 1;
		}
		$ifd_offset = $read_long( 4 );
		$count      = null !== $ifd_offset ? $read_short( $ifd_offset ) : null;
		if ( null === $ifd_offset || null === $count || $count > 512 ) {
			return 1;
		}
		for ( $index = 0; $index < $count; $index++ ) {
			$entry = $ifd_offset + 2 + ( $index * 12 );
			if ( 0x0112 !== $read_short( $entry ) ) {
				continue;
			}
			$type       = $read_short( $entry + 2 );
			$value_count = $read_long( $entry + 4 );
			if ( 3 !== $type || 1 !== $value_count ) {
				return 1;
			}
			$value = $read_short( $entry + 8 );
			return null !== $value && $value >= 1 && $value <= 8 ? $value : 1;
		}

		return 1;
	}

	/** Bake a non-default JPEG orientation into its pixels and remove stale metadata. */
	private static function normalise_jpeg_orientation( string $source, int $orientation, int $width, int $height ): string {
		$temp = self::temp_path( 'oc-oriented-' );
		if ( false === $temp ) {
			throw new \RuntimeException( __( 'The photo orientation could not be staged.', 'overcustomise' ) );
		}
		$output = $temp . '.jpg';
		@unlink( $temp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$source_size = filesize( $source );
		if ( false === $source_size || $source_size <= 0 ) {
			@unlink( $output ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			throw new \RuntimeException( __( 'The photo orientation could not be normalised.', 'overcustomise' ) );
		}

		try {
			if ( extension_loaded( 'imagick' ) && class_exists( '\\Imagick' ) ) {
				$image = new \Imagick();
				try {
					$image->setResourceLimit( \Imagick::RESOURCETYPE_MEMORY, 256 * 1024 * 1024 );
					$image->setResourceLimit( \Imagick::RESOURCETYPE_MAP, 256 * 1024 * 1024 );
					$image->setResourceLimit( \Imagick::RESOURCETYPE_DISK, 512 * 1024 * 1024 );
					if ( defined( '\\Imagick::RESOURCETYPE_AREA' ) ) {
						$image->setResourceLimit( \Imagick::RESOURCETYPE_AREA, self::MAX_IMAGE_PIXELS );
					}
					$image->readImage( $source . '[0]' );
					$image->setIteratorIndex( 0 );
					self::apply_imagick_orientation( $image, $orientation );
					$image->setImageOrientation( \Imagick::ORIENTATION_TOPLEFT );
					$image->setImageFormat( 'jpeg' );
					$image->stripImage();
					foreach ( [ 92, 85, 78, 70, 60, 50 ] as $quality ) {
						$image->setImageCompressionQuality( $quality );
						if ( $image->writeImage( $output ) && self::normalised_jpeg_is_valid( $output ) ) {
							return $output;
						}
					}
				} finally {
					$image->clear();
					$image->destroy();
				}
			} else {
				if ( ! self::gd_orientation_memory_is_safe( $width, $height ) ) {
					throw new \RuntimeException( __( 'This photo is too large to rotate safely on this server. Please export it as a new JPEG or PNG and try again.', 'overcustomise' ) );
				}
				require_once ABSPATH . 'wp-admin/includes/image.php';
				$editor = wp_get_image_editor( $source );
				if ( ! is_wp_error( $editor ) ) {
					self::apply_image_editor_orientation( $editor, $orientation );
					foreach ( [ 92, 85, 78, 70, 60, 50 ] as $quality ) {
						$editor->set_quality( $quality );
						$result = $editor->save( $output, 'image/jpeg' );
						if ( ! is_wp_error( $result ) && self::normalised_jpeg_is_valid( $output ) ) {
							return $output;
						}
					}
				}
			}
		} catch ( \Throwable $e ) {
			OC_Logger::warning( 'JPEG orientation normalisation failed: ' . $e->getMessage() );
		}

		@unlink( $output ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		throw new \RuntimeException( __( 'The photo orientation could not be normalised. Please export it as a new JPEG or PNG and try again.', 'overcustomise' ) );
	}

	/** Apply one EXIF orientation directly to an ImageMagick image. */
	private static function apply_imagick_orientation( \Imagick $image, int $orientation ): void {
		switch ( $orientation ) {
			case 2:
				$image->flopImage();
				break;
			case 3:
				$image->rotateImage( new \ImagickPixel( 'white' ), 180 );
				break;
			case 4:
				$image->flipImage();
				break;
			case 5:
				$image->transposeImage();
				break;
			case 6:
				$image->rotateImage( new \ImagickPixel( 'white' ), 90 );
				break;
			case 7:
				$image->transverseImage();
				break;
			case 8:
				$image->rotateImage( new \ImagickPixel( 'white' ), 270 );
				break;
		}
	}

	/** Apply one EXIF orientation using the portable WordPress image-editor API. */
	private static function apply_image_editor_orientation( object $editor, int $orientation ): void {
		$result = match ( $orientation ) {
			2       => $editor->flip( true, false ),
			3       => $editor->rotate( 180 ),
			4       => $editor->flip( false, true ),
			5       => is_wp_error( $editor->rotate( 90 ) ) ? new \WP_Error( 'orientation_failed' ) : $editor->flip( true, false ),
			6       => $editor->rotate( 90 ),
			7       => is_wp_error( $editor->rotate( 90 ) ) ? new \WP_Error( 'orientation_failed' ) : $editor->flip( false, true ),
			8       => $editor->rotate( 270 ),
			default => true,
		};
		if ( is_wp_error( $result ) ) {
			throw new \RuntimeException( __( 'The photo orientation could not be normalised.', 'overcustomise' ) );
		}
	}

	/** Validate a normalised JPEG against generated-artifact safety limits. */
	private static function normalised_jpeg_is_valid( string $path ): bool {
		clearstatcache( true, $path );
		$size = filesize( $path );
		$info = @getimagesize( $path );
		if ( false === $size || $size <= 0 || $size > self::MAX_GENERATED_IMAGE_BYTES
			|| ! is_array( $info ) || 'image/jpeg' !== (string) ( $info['mime'] ?? '' ) || 1 !== self::jpeg_exif_orientation( $path )
		) {
			return false;
		}
		try {
			self::validate_image_dimensions( (int) $info[0], (int) $info[1] );
			return true;
		} catch ( \RuntimeException $e ) {
			return false;
		}
	}

	/** Ensure GD has conservative headroom for a decoded source and rotated destination. */
	private static function gd_orientation_memory_is_safe( int $width, int $height, ?int $memory_limit = null, ?int $memory_used = null ): bool {
		if ( $width < 1 || $height < 1 ) {
			return false;
		}
		if ( null === $memory_limit ) {
			$setting = trim( (string) ini_get( 'memory_limit' ) );
			if ( '-1' === $setting ) {
				return true;
			}
			if ( ! preg_match( '/^(\d+)([KMG]?)$/i', $setting, $matches ) ) {
				return false;
			}
			$memory_limit = (int) $matches[1] * match ( strtoupper( $matches[2] ) ) {
				'G' => 1024 * 1024 * 1024,
				'M' => 1024 * 1024,
				'K' => 1024,
				default => 1,
			};
		}
		$memory_used = $memory_used ?? memory_get_usage( true );
		$pixels      = $width * $height;
		$estimated   = ( $pixels * 12 ) + ( 32 * 1024 * 1024 );
		return $memory_limit <= 0 || $estimated <= max( 0, $memory_limit - $memory_used );
	}

	/** Convert an Apple HEIC/HEIF upload to a browser- and filter-compatible JPEG. */
	private static function process_heic( array $_file ): array {
		$output = self::temp_path( 'oc-heic.jpg' );
		if ( false === $output ) {
			throw new \RuntimeException( __( 'Could not stage the converted Apple photo.', 'overcustomise' ) );
		}

		try {
			self::convert_heic_via_imagick( (string) $_file['tmp_name'], $output );
			$filename = pathinfo( sanitize_file_name( basename( (string) $_file['name'] ) ), PATHINFO_FILENAME ) . '.jpg';
			$result   = self::process_raster(
				[
					'tmp_name' => $output,
					'name'     => $filename,
				],
				'jpg'
			);
			$source_type = self::normalise_extension( pathinfo( (string) $_file['name'], PATHINFO_EXTENSION ) );
			$source_size = filesize( (string) $_file['tmp_name'] );
			if ( false === $source_size || $source_size <= 0
				|| ! update_post_meta( (int) $result['attachment_id'], '_oc_artwork_source_type', $source_type )
				|| ! update_post_meta( (int) $result['attachment_id'], '_oc_artwork_source_bytes', (int) $source_size )
			) {
				wp_delete_attachment( (int) $result['attachment_id'], true );
				throw new \RuntimeException( __( 'The converted Apple photo metadata could not be retained.', 'overcustomise' ) );
			}
			$result['stored_name'] = $filename;
			return $result;
		} finally {
			@unlink( $output ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
	}

	// -------------------------------------------------------------------------
	// Conversion helpers
	// -------------------------------------------------------------------------

	/** Return whether ImageMagick has an HEIC/HEIF decoder available. */
	public static function heic_conversion_is_available(): bool {
		if ( ! extension_loaded( 'imagick' ) || ! class_exists( '\\Imagick' ) ) {
			return false;
		}
		try {
			return ! empty( \Imagick::queryFormats( 'HEI*' ) ) && ! empty( \Imagick::queryFormats( 'JPEG' ) );
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	/** Decode and normalize one HEIC/HEIF image through ImageMagick. */
	private static function read_heic_via_imagick( string $source ): \Imagick {
		if ( ! self::heic_conversion_is_available() ) {
			throw new \RuntimeException( __( 'Apple HEIC/HEIF photos require ImageMagick with HEIC support on this server.', 'overcustomise' ) );
		}

		$image = new \Imagick();
		try {
			$image->setResourceLimit( \Imagick::RESOURCETYPE_MEMORY, 256 * 1024 * 1024 );
			$image->setResourceLimit( \Imagick::RESOURCETYPE_MAP, 256 * 1024 * 1024 );
			$image->setResourceLimit( \Imagick::RESOURCETYPE_DISK, 512 * 1024 * 1024 );
			if ( defined( '\\Imagick::RESOURCETYPE_AREA' ) ) {
				$image->setResourceLimit( \Imagick::RESOURCETYPE_AREA, self::MAX_IMAGE_PIXELS );
			}
			$image->readImage( $source . '[0]' );
			$image->setIteratorIndex( 0 );
			self::validate_image_dimensions( $image->getImageWidth(), $image->getImageHeight() );
			return $image;
		} catch ( \Throwable $e ) {
			$image->clear();
			$image->destroy();
			OC_Logger::warning( 'HEIC decoding failed: ' . $e->getMessage() );
			throw new \RuntimeException( __( 'The Apple photo could not be decoded. Please try exporting it as JPEG.', 'overcustomise' ), 0, $e );
		}
	}

	/** Convert the first HEIC/HEIF image to an auto-oriented, flattened JPEG. */
	private static function convert_heic_via_imagick( string $source, string $dest ): void {
		$image = self::read_heic_via_imagick( $source );
		try {
			if ( method_exists( $image, 'autoOrientImage' ) ) {
				$image->autoOrientImage();
			}
			self::validate_image_dimensions( $image->getImageWidth(), $image->getImageHeight() );
			$image->setImageBackgroundColor( 'white' );
			$image->setImageAlphaChannel( \Imagick::ALPHACHANNEL_REMOVE );
			$image->transformImageColorspace( \Imagick::COLORSPACE_SRGB );
			$image->setImageDepth( 8 );
			$image->setImageFormat( 'jpeg' );
			$image->stripImage();
			$written = false;
			foreach ( [ 92, 85, 78, 70, 60 ] as $quality ) {
				$image->setImageCompressionQuality( $quality );
				if ( ! $image->writeImage( $dest ) ) {
					throw new \RuntimeException( __( 'The converted Apple photo could not be written.', 'overcustomise' ) );
				}
				$size = filesize( $dest );
				if ( false !== $size && $size > 0 && $size <= self::MAX_GENERATED_IMAGE_BYTES ) {
					$written = true;
					break;
				}
			}

			$size = filesize( $dest );
			$info = @getimagesize( $dest );
			if ( ! $written || false === $size || $size <= 0 || $size > self::MAX_GENERATED_IMAGE_BYTES || ! is_array( $info ) || 'image/jpeg' !== (string) ( $info['mime'] ?? '' ) ) {
				throw new \RuntimeException( __( 'The converted Apple photo is invalid or too large.', 'overcustomise' ) );
			}
		} finally {
			$image->clear();
			$image->destroy();
		}
	}

	/** Convert a high-contrast line-art background into transparency and return a temporary PNG path. */
	private static function remove_background_via_imagick( string $source ): string|\WP_Error {
		if ( ! extension_loaded( 'imagick' ) || ! class_exists( '\Imagick' ) ) {
			return new \WP_Error( 'background_removal_unavailable', __( 'Local background removal requires the PHP ImageMagick extension.', 'overcustomise' ) );
		}

		$image  = null;
		$mask   = null;
		$output = self::temp_path( 'oc-background-' );
		if ( false === $output ) {
			return new \WP_Error( 'background_removal_failed', __( 'The background-removed image could not be staged.', 'overcustomise' ) );
		}

		try {
			$image = new \Imagick();
			$image->setResourceLimit( \Imagick::RESOURCETYPE_MEMORY, 256 * 1024 * 1024 );
			$image->setResourceLimit( \Imagick::RESOURCETYPE_MAP, 256 * 1024 * 1024 );
			$image->setResourceLimit( \Imagick::RESOURCETYPE_DISK, 512 * 1024 * 1024 );
			if ( defined( '\Imagick::RESOURCETYPE_AREA' ) ) {
				$image->setResourceLimit( \Imagick::RESOURCETYPE_AREA, self::MAX_IMAGE_PIXELS );
			}
			$image->readImage( $source . '[0]' );
			$image->setIteratorIndex( 0 );
			$width  = $image->getImageWidth();
			$height = $image->getImageHeight();
			self::validate_image_dimensions( $width, $height );

			$positions = [ [ 0, 0 ], [ $width - 1, 0 ], [ 0, $height - 1 ], [ $width - 1, $height - 1 ] ];
			$samples   = [];
			foreach ( $positions as [ $x, $y ] ) {
				$pixel  = $image->getImagePixelColor( $x, $y );
				$colour = $pixel->getColor( true );
				$samples[] = [
					'x'     => $x,
					'y'     => $y,
					'pixel' => $pixel,
					'rgb'   => [ (float) $colour['r'], (float) $colour['g'], (float) $colour['b'] ],
				];
			}

			$background = [];
			foreach ( $samples as $candidate ) {
				$matching = array_values( array_filter( $samples, static function ( array $sample ) use ( $candidate ): bool {
					$distance = sqrt(
						( $sample['rgb'][0] - $candidate['rgb'][0] ) ** 2
						+ ( $sample['rgb'][1] - $candidate['rgb'][1] ) ** 2
						+ ( $sample['rgb'][2] - $candidate['rgb'][2] ) ** 2
					);
					return $distance <= 0.18;
				} ) );
				if ( count( $matching ) > count( $background ) ) {
					$background = $matching;
				}
			}
			if ( count( $background ) < 3 ) {
				throw new \RuntimeException( __( 'Local background removal requires a plain, consistent background around the image edges.', 'overcustomise' ) );
			}

			$reference     = $background[0]['rgb'];
			$luminance     = 0.2126 * $reference[0] + 0.7152 * $reference[1] + 0.0722 * $reference[2];
			$quantum       = $image->getQuantumRange();
			$quantum_range = (float) ( $quantum['quantumRangeLong'] ?? 65535 );

			$mask = clone $image;
			$mask->setImageAlphaChannel( \Imagick::ALPHACHANNEL_REMOVE );
			$mask->transformImageColorspace( \Imagick::COLORSPACE_GRAY );
			if ( $luminance >= 0.5 ) {
				$mask->negateImage( false );
			}
			$mask->levelImage( $quantum_range * 0.01, 1.0, $quantum_range * 0.99 );

			$image->setImageAlphaChannel( \Imagick::ALPHACHANNEL_ACTIVATE );
			$image->compositeImage( $mask, \Imagick::COMPOSITE_COPYOPACITY, 0, 0 );

			$alpha          = $image->getImageChannelMean( \Imagick::CHANNEL_ALPHA );
			$visible_ratio  = $quantum_range > 0 ? (float) ( $alpha['mean'] ?? 0 ) / $quantum_range : 0.0;
			if ( $visible_ratio < 0.002 ) {
				throw new \RuntimeException( __( 'Background removal was stopped because it would remove nearly all visible artwork.', 'overcustomise' ) );
			}

			$image->setImageFormat( 'png' );
			$image->stripImage();
			if ( ! $image->writeImage( $output ) ) {
				throw new \RuntimeException( __( 'The background-removed image could not be written.', 'overcustomise' ) );
			}
			$size = filesize( $output );
			$info = @getimagesize( $output );
			if ( false === $size || $size <= 0 || $size > self::MAX_GENERATED_IMAGE_BYTES || ! is_array( $info ) || 'image/png' !== (string) ( $info['mime'] ?? '' ) ) {
				throw new \RuntimeException( __( 'The background-removed image is invalid or too large.', 'overcustomise' ) );
			}
			return $output;
		} catch ( \Throwable $e ) {
			@unlink( $output ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			OC_Logger::warning( 'Local background removal failed: ' . $e->getMessage() );
			return new \WP_Error( 'background_removal_failed', $e->getMessage() );
		} finally {
			if ( $mask instanceof \Imagick ) {
				$mask->clear();
				$mask->destroy();
			}
			if ( $image instanceof \Imagick ) {
				$image->clear();
				$image->destroy();
			}
		}
	}

	/** Convert PDF/EPS page 1 to PNG via PHP Imagick extension. */
	private static function convert_via_imagick( string $source, string $dest ): bool {
		$im = null;
		try {
			$im = new \Imagick();
			$im->setResourceLimit( \Imagick::RESOURCETYPE_MEMORY, 256 * 1024 * 1024 );
			$im->setResourceLimit( \Imagick::RESOURCETYPE_MAP, 256 * 1024 * 1024 );
			$im->setResourceLimit( \Imagick::RESOURCETYPE_DISK, 512 * 1024 * 1024 );
			$im->setResolution( 150, 150 );
			$im->readImage( $source . '[0]' ); // First page only.
			self::validate_image_dimensions( $im->getImageWidth(), $im->getImageHeight() );
			$im->setImageFormat( 'png' );
			$im->setImageAlphaChannel( \Imagick::ALPHACHANNEL_REMOVE );
			$im->setImageBackgroundColor( 'white' );
			$im->flattenImages();
			$result = $im->writeImage( $dest );
			return $result;
		} catch ( \Throwable $e ) {
			OC_Logger::warning( 'Imagick conversion failed: ' . $e->getMessage() );
			return false;
		} finally {
			if ( $im instanceof \Imagick ) {
				$im->clear();
				$im->destroy();
			}
		}
	}

	/** Convert PDF/EPS page 1 to PNG via GhostScript CLI. */
	private static function convert_via_ghostscript( string $source, string $dest ): bool {
		$binary = self::detect_ghostscript_binary();
		if ( '' === $binary ) {
			return false;
		}

		try {
			$result = OC_Command_Runner::run( [
				$binary,
				'-dNOPAUSE',
				'-dBATCH',
				'-dSAFER',
				'-dQUIET',
				'-dMaxBitmap=40000000',
				'-dFirstPage=1',
				'-dLastPage=1',
				'-sDEVICE=png16m',
				'-r150',
				'-g2400x2400',
				'-dPDFFitPage',
				'-sOutputFile=' . $dest,
				$source,
			] );
		} catch ( \InvalidArgumentException $e ) {
			OC_Logger::warning( 'Ghostscript command rejected: ' . $e->getMessage() );
			return false;
		}

		if ( 0 !== (int) $result['code'] ) {
			OC_Logger::warning( 'Ghostscript conversion failed: ' . implode( "\n", (array) $result['output'] ) );
			return false;
		}

		if ( ! file_exists( $dest ) ) return false;
		$info = @getimagesize( $dest );
		if ( ! is_array( $info ) ) { @unlink( $dest ); return false; }
		try { self::validate_image_dimensions( (int) $info[0], (int) $info[1] ); } catch ( \RuntimeException $e ) { @unlink( $dest ); return false; }
		return true;
	}

	private static function validate_image_dimensions( int $width, int $height ): void {
		if ( $width <= 0 || $height <= 0 || $width > self::MAX_IMAGE_DIMENSION || $height > self::MAX_IMAGE_DIMENSION || $width * $height > self::MAX_IMAGE_PIXELS ) {
			throw new \RuntimeException( __( 'Image dimensions exceed the safe processing limit.', 'overcustomise' ) );
		}
	}

	/** Detect a usable Ghostscript binary for the host platform. */
	private static function detect_ghostscript_binary(): string {
		$candidates = [ 'gs' ];
		if ( str_starts_with( strtoupper( PHP_OS_FAMILY ), 'WINDOWS' ) ) {
			$candidates = [ 'gswin64c', 'gswin32c', 'gs' ];
		}

		foreach ( $candidates as $bin ) {
			try {
				$probe = OC_Command_Runner::run( [ $bin, '--version' ] );
				if ( 0 === (int) $probe['code'] ) {
					return $bin;
				}
			} catch ( \InvalidArgumentException $e ) {
				// Ignore invalid binary candidates and continue probing.
			}
		}

		return '';
	}

	// -------------------------------------------------------------------------
	// WP Media Library helper
	// -------------------------------------------------------------------------

	/**
	 * Save a file to isolated storage outside public uploads.
	 *
	 * The attachment record is retained for order/print compatibility, but tagged
	 * so it can be hidden from the standard Media Library and managed separately.
	 *
	 * @param  string $file_path  Absolute path to the source file.
	 * @param  string $filename   Target filename (sanitised).
	 * @param  string $mime_type  MIME type string.
	 * @return int|\WP_Error      Attachment post ID on success.
	 */
	private static function save_to_media_library( string $file_path, string $filename, string $mime_type ): int|\WP_Error {
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$subdir = self::private_storage_path( self::PRIVATE_ARTWORK_SUBDIR );
		if ( null === $subdir ) {
			OC_Logger::error( 'Private artwork root is unavailable while saving customer artwork.' );
			return new \WP_Error( 'storage_unavailable', __( 'Private artwork storage is unavailable.', 'overcustomise' ) );
		}

		$extension     = self::normalise_extension( pathinfo( $filename, PATHINFO_EXTENSION ) );
		$mime_key      = self::SUPPORTED_TYPES[ $mime_type ] ?? null;
		$extension_key = self::EXT_TO_TYPE[ $extension ] ?? null;
		if ( null === $mime_key || null === $extension_key || $mime_key !== $extension_key || ! is_file( $file_path ) ) {
			OC_Logger::error( 'A private artwork save was rejected because its MIME and extension did not agree.' );
			return new \WP_Error( 'invalid_artwork', __( 'Could not save uploaded file.', 'overcustomise' ) );
		}
		$dest_filename = self::new_private_filename( 'artwork', $extension );
		$dest_path     = $subdir . '/' . $dest_filename;

		if ( ! self::atomic_copy( $file_path, $dest_path ) ) {
			OC_Logger::error( 'Customer artwork could not be copied into private storage.' );
			return new \WP_Error( 'copy_failed', __( 'Could not save uploaded file.', 'overcustomise' ) );
		}

		// Build the attachment array.
		$attachment = [
			'post_mime_type' => $mime_type,
			'post_title'     => __( 'Customer artwork', 'overcustomise' ),
			'post_content'   => '',
			'post_status'    => 'private',
			'post_parent'    => 0,
		];

		$attachment_id = wp_insert_attachment( $attachment, $dest_path, 0, true );

		if ( is_wp_error( $attachment_id ) ) {
			OC_Logger::error( 'Private artwork attachment insert failed: ' . $attachment_id->get_error_message() );
			@unlink( $dest_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return $attachment_id;
		}
		if ( ! $attachment_id ) {
			@unlink( $dest_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return new \WP_Error( 'insert_failed', __( 'Could not register uploaded file.', 'overcustomise' ) );
		}

		// Store dimensions without creating additional unreserved private derivatives.
		if ( in_array( $mime_type, [ 'image/png', 'image/jpeg', 'image/jpg', 'image/webp' ], true ) ) {
			$image_info = @getimagesize( $dest_path );
			$metadata   = is_array( $image_info ) ? [
				'width'  => (int) $image_info[0],
				'height' => (int) $image_info[1],
				'file'   => $dest_filename,
				'sizes'  => [],
			] : null;
			if ( null === $metadata || false === wp_update_attachment_metadata( $attachment_id, $metadata ) ) {
				@unlink( $dest_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				wp_delete_attachment( $attachment_id, true );
				return new \WP_Error( 'insert_failed', __( 'Could not register uploaded file.', 'overcustomise' ) );
			}
		}

		// Tag as OC artwork for easy identification.
		if ( ! update_post_meta( $attachment_id, '_oc_artwork', 1 )
			|| ! update_post_meta( $attachment_id, '_oc_artwork_type', $extension )
			|| ! update_post_meta( $attachment_id, '_oc_private_storage_version', self::STORAGE_VERSION )
		) {
			@unlink( $dest_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			wp_delete_attachment( $attachment_id, true );
			return new \WP_Error( 'insert_failed', __( 'Could not register uploaded file.', 'overcustomise' ) );
		}

		return $attachment_id;
	}

	/** Write deny rules for Apache/IIS and a non-listing fallback entry point. */
	private static function protect_artwork_directory( string $directory ): bool {
		$files = [
			'.htaccess' => "Options -Indexes\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n",
			'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?><configuration><system.webServer><security><authorization><remove users=\"*\" roles=\"\" verbs=\"\"/><add accessType=\"Deny\" users=\"*\"/></authorization></security></system.webServer></configuration>\n",
			'index.php' => "<?php\nhttp_response_code( 404 );\nexit;\n",
		];
		foreach ( $files as $filename => $contents ) {
			$path = $directory . '/' . $filename;
			if ( ( ! is_file( $path ) || (string) file_get_contents( $path ) !== $contents ) && ! self::atomic_write( $path, $contents, 0640 ) ) {
				return false;
			}
		}

		return true;
	}

	/** Confirm a resolved attachment stays inside the private artwork root. */
	private static function is_private_artwork_path( string $path ): bool {
		$base = self::private_storage_path( self::PRIVATE_ARTWORK_SUBDIR );
		$real = realpath( $path );

		return null !== $base && false !== $real && is_file( $real ) && self::path_is_within( wp_normalize_path( $real ), $base );
	}

	/** Accept exact legacy paths only while bounded migration is still in progress. */
	private static function is_legacy_artwork_path( string $path ): bool {
		return self::path_is_in_legacy_artwork_root( $path, true );
	}

	/** Validate an exact legacy path, optionally requiring web-server deny rules. */
	private static function path_is_in_legacy_artwork_root( string $path, bool $require_protection ): bool {
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
			return false;
		}
		$base = realpath( trailingslashit( (string) $uploads['basedir'] ) . self::UPLOAD_SUBDIR );
		$real = realpath( $path );
		$uploads_base = realpath( (string) $uploads['basedir'] );

		return false !== $uploads_base && false !== $base && self::path_is_within( $base, $uploads_base )
			&& ( ! $require_protection || self::legacy_artwork_storage_is_protected( $base ) ) && false !== $real && is_file( $real )
			&& self::path_is_within( wp_normalize_path( $real ), wp_normalize_path( $base ) );
	}

	/** Require intact deny rules before any not-yet-migrated public path is used. */
	private static function legacy_artwork_storage_is_protected( string $directory ): bool {
		$files = [
			'.htaccess' => "Options -Indexes\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n",
			'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?><configuration><system.webServer><security><authorization><remove users=\"*\" roles=\"\" verbs=\"\"/><add accessType=\"Deny\" users=\"*\"/></authorization></security></system.webServer></configuration>\n",
			'index.php' => "<?php\nhttp_response_code( 404 );\nexit;\n",
		];
		foreach ( $files as $filename => $contents ) {
			$path = $directory . '/' . $filename;
			if ( ! is_file( $path ) || ! hash_equals( $contents, (string) file_get_contents( $path ) ) ) {
				return false;
			}
		}
		return true;
	}

	private static function is_allowed_artwork_path( string $path ): bool {
		return self::is_private_artwork_path( $path ) || self::is_legacy_artwork_path( $path );
	}

	/** Move one marked legacy attachment and remove its publicly routed image sizes. */
	private static function migrate_legacy_attachment( int $attachment_id, string $private_directory ): bool {
		$post_update = wp_update_post( [ 'ID' => $attachment_id, 'post_status' => 'private' ], true );
		if ( is_wp_error( $post_update ) ) {
			OC_Logger::warning( 'Artwork migration could not make attachment private: ' . $post_update->get_error_message() );
			return false;
		}

		if ( '' === (string) get_post_meta( $attachment_id, '_oc_artwork_owner_secret', true )
			&& ! update_post_meta( $attachment_id, '_oc_artwork_owner_secret', wp_generate_password( 64, false, false ) )
		) {
			return false;
		}

		$source = get_attached_file( $attachment_id );
		if ( ! is_string( $source ) || ! is_file( $source ) ) {
			OC_Logger::warning( 'Artwork migration skipped an attachment with a missing file.' );
			return false;
		}
		if ( self::is_private_artwork_path( $source ) ) {
			if ( 'svg' === self::normalise_extension( pathinfo( $source, PATHINFO_EXTENSION ) ) && ! OC_SVG_Sanitiser::sanitise_file( $source ) ) {
				OC_Logger::warning( 'Artwork migration rejected an unsafe private SVG.' );
				return false;
			}
			if ( ! self::artwork_file_is_valid( $attachment_id ) ) {
				OC_Logger::warning( 'Artwork migration rejected invalid private artwork content.' );
				return false;
			}
			return (bool) update_post_meta( $attachment_id, '_oc_private_storage_version', self::STORAGE_VERSION );
		}
		if ( ! self::path_is_in_legacy_artwork_root( $source, false ) ) {
			OC_Logger::warning( 'Artwork migration rejected a file outside the legacy artwork root.' );
			return false;
		}

		$extension = self::normalise_extension( pathinfo( $source, PATHINFO_EXTENSION ) );
		$dest      = $private_directory . '/' . self::new_private_filename( 'artwork', $extension );
		if ( ! self::atomic_copy( $source, $dest ) ) {
			OC_Logger::warning( 'Artwork migration could not copy a legacy file.' );
			return false;
		}
		if ( 'svg' === $extension && ! OC_SVG_Sanitiser::sanitise_file( $dest ) ) {
			@unlink( $dest ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			OC_Logger::warning( 'Artwork migration rejected an unsafe legacy SVG.' );
			return false;
		}
		$stored_mime    = (string) get_post_mime_type( $attachment_id );
		$detected_mime  = self::detect_mime( $dest, basename( $dest ) );
		$extension_type = self::EXT_TO_TYPE[ $extension ] ?? null;
		$detected_type   = self::SUPPORTED_TYPES[ $detected_mime ] ?? null;
		if ( null === $extension_type || null === $detected_type
			|| $extension_type !== $detected_type
			|| ! self::artwork_content_is_valid( $dest, $detected_mime )
		) {
			@unlink( $dest ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			OC_Logger::warning( sprintf(
				'Artwork migration rejected invalid legacy artwork content (attachment %d, extension %s, stored MIME %s, detected MIME %s).',
				$attachment_id,
				$extension ?: '(none)',
				$stored_mime ?: '(none)',
				$detected_mime ?: '(none)'
			) );
			return false;
		}
		$stored_type = self::SUPPORTED_TYPES[ $stored_mime ] ?? null;
		if ( $stored_type !== $detected_type ) {
			$mime_update = wp_update_post( [ 'ID' => $attachment_id, 'post_mime_type' => $detected_mime ], true );
			if ( is_wp_error( $mime_update ) ) {
				@unlink( $dest ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				OC_Logger::warning( 'Artwork migration could not repair legacy MIME metadata: ' . $mime_update->get_error_message() );
				return false;
			}
		}

		$metadata   = wp_get_attachment_metadata( $attachment_id );
		$old_files  = [ $source ];
		if ( is_array( $metadata ) ) {
			foreach ( (array) ( $metadata['sizes'] ?? [] ) as $size_data ) {
				if ( is_array( $size_data ) && ! empty( $size_data['file'] ) ) {
					$candidate = dirname( $source ) . '/' . basename( (string) $size_data['file'] );
					if ( self::path_is_in_legacy_artwork_root( $candidate, false ) ) {
						$old_files[] = $candidate;
					}
				}
			}
		}

		if ( ! update_attached_file( $attachment_id, $dest ) ) {
			@unlink( $dest ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			OC_Logger::warning( 'Artwork migration could not update the attachment path.' );
			return false;
		}
		if ( is_array( $metadata ) ) {
			$metadata['file']  = basename( $dest );
			$metadata['sizes'] = [];
			if ( false === wp_update_attachment_metadata( $attachment_id, $metadata ) ) {
				update_attached_file( $attachment_id, $source );
				@unlink( $dest ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				OC_Logger::warning( 'Artwork migration could not update private attachment metadata.' );
				return false;
			}
		}
		foreach ( array_unique( $old_files ) as $old_file ) {
			if ( self::path_is_in_legacy_artwork_root( $old_file, false ) ) {
				wp_delete_file( $old_file );
			}
		}

		if ( ! update_post_meta( $attachment_id, '_oc_private_storage_version', self::STORAGE_VERSION ) ) {
			OC_Logger::warning( 'Artwork migration completed without recording its storage version.' );
			return false;
		}
		return true;
	}

	/** Atomically copy a file into a destination in the same filesystem. */
	private static function atomic_copy( string $source, string $destination ): bool {
		if ( ! is_file( $source ) || is_file( $destination ) ) {
			return false;
		}
		$tmp = $destination . '.part-' . strtolower( wp_generate_password( 12, false, false ) );
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

	/** Atomically replace a small control file. */
	private static function atomic_write( string $path, string $contents, int $mode ): bool {
		$tmp = dirname( $path ) . '/.' . basename( $path ) . '.part-' . strtolower( wp_generate_password( 12, false, false ) );
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

	private static function new_private_filename( string $prefix, string $extension ): string {
		$extension = self::normalise_extension( $extension );
		return $prefix . '-' . strtolower( wp_generate_password( 40, false, false ) ) . ( $extension ? '.' . $extension : '' );
	}

	private static function filtered_positive_int( string $filter, int $default, int $minimum, int $maximum ): ?int {
		$value = apply_filters( $filter, $default );
		if ( ! is_int( $value ) && ! ( is_string( $value ) && preg_match( '/^[0-9]+$/D', $value ) ) ) {
			return null;
		}
		$value = (int) $value;
		return $value >= $minimum && $value <= $maximum ? $value : null;
	}

	private static function is_absolute_path( string $path ): bool {
		return str_starts_with( $path, '/' ) || (bool) preg_match( '#^[A-Za-z]:/#D', $path );
	}

	/** Compare canonical-looking paths without allowing prefix collisions. */
	private static function path_is_within( string $path, string $base, bool $allow_equal = false ): bool {
		$path = rtrim( wp_normalize_path( $path ), '/' );
		$base = rtrim( wp_normalize_path( $base ), '/' );
		if ( '' === $path || '' === $base ) {
			return false;
		}
		if ( str_starts_with( strtoupper( PHP_OS_FAMILY ), 'WINDOWS' ) ) {
			$path = strtolower( $path );
			$base = strtolower( $base );
		}

		return ( $allow_equal && $path === $base ) || str_starts_with( $path, $base . '/' );
	}

	// -------------------------------------------------------------------------
	// MIME detection
	// -------------------------------------------------------------------------

	/**
	 * Detect real MIME type via finfo, with safe content-signature fallbacks.
	 *
	 * @param  string $tmp_path  Path to the uploaded temp file.
	 * @param  string $filename  Original filename (used as fallback hint).
	 * @return string            MIME type string.
	 */
	private static function detect_mime( string $tmp_path, string $filename ): string {
		// Use finfo when available; fall back to mime_content_type() or extension guess.
		if ( class_exists( 'finfo', false ) ) {
			$finfo = new \finfo( FILEINFO_MIME_TYPE );
			$mime  = $finfo->file( $tmp_path );
			if ( false === $mime ) {
				$mime = 'application/octet-stream';
			}
		} elseif ( function_exists( 'mime_content_type' ) ) {
			$mime = mime_content_type( $tmp_path ) ?: 'application/octet-stream';
		} else {
			$mime = 'application/octet-stream';
		}

		// finfo often returns text/plain or text/xml for SVG — peek at content.
		if ( in_array( $mime, [ 'text/plain', 'text/xml', 'application/xml', 'application/octet-stream' ], true ) ) {
			$content = file_get_contents( $tmp_path, false, null, 0, 512 );
			if ( false !== $content && preg_match( '/<svg[\s>]/i', $content ) ) {
				return 'image/svg+xml';
			}
		}

		// Some systems return text/plain or octet-stream for EPS — use extension hint.
		$ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		if ( 'eps' === $ext && in_array( $mime, [ 'text/plain', 'application/octet-stream' ], true ) ) {
			return 'application/eps';
		}

		// Older libmagic databases do not recognise the HEIF container used by Apple photos.
		if ( in_array( $ext, [ 'heic', 'heif' ], true ) && 'application/octet-stream' === $mime && self::heic_content_is_valid( $tmp_path ) ) {
			return 'heic' === $ext ? 'image/heic' : 'image/heif';
		}

		return $mime;
	}

	/** Validate the ISO-BMFF file-type box against HEIC codec brands, excluding AVIF. */
	private static function heic_content_is_valid( string $path ): bool {
		$header = file_get_contents( $path, false, null, 0, 4096 );
		if ( ! is_string( $header ) || strlen( $header ) < 16 || 'ftyp' !== substr( $header, 4, 4 ) ) {
			return false;
		}
		$box_size = unpack( 'Nsize', substr( $header, 0, 4 ) )['size'] ?? 0;
		if ( $box_size < 16 || $box_size > strlen( $header ) ) {
			return false;
		}
		$brands = str_split( substr( $header, 8, $box_size - 8 ), 4 );
		return ! array_intersect( [ 'avif', 'avis' ], $brands )
			&& (bool) array_intersect( [ 'heic', 'heix', 'hevc', 'hevx', 'heim', 'heis', 'hevm', 'hevs' ], $brands );
	}
}
