<?php
/**
 * Upload Handler — processes customer artwork uploads.
 *
 * Handles: SVG, PDF, EPS, PNG, JPG/JPEG
 *
 * Flow:
 *  1. Validate file type via finfo (real MIME, not extension).
 *  2. Validate file size against plugin setting.
 *  3. SVG  → sanitise via OC_SVG_Sanitiser → save to WP media library.
 *  4. PDF/EPS → convert page 1 to PNG preview via Imagick or GhostScript.
 *  5. PNG/JPG → save directly to WP media library.
 *  6. Return structured result: { attachment_id, preview_url, original_url, file_type }.
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

class OC_Upload_Handler {

	/** Nonce action used to authenticate upload requests. */
	public const NONCE_ACTION = 'oc_upload_artwork';

	/** Subdirectory within WP uploads for artwork files. */
	private const UPLOAD_SUBDIR = 'overcustomise/artwork';

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
	];

	/** Allowed file extensions mapped to canonical type keys. */
	private const EXT_TO_TYPE = [
		'svg'  => 'svg',
		'pdf'  => 'pdf',
		'eps'  => 'eps',
		'png'  => 'png',
		'jpg'  => 'jpg',
		'jpeg' => 'jpg',
	];

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Process an uploaded file.
	 *
	 * @param  array $_file   An element from $_FILES.
	 * @return array{attachment_id:int,preview_url:string,original_url:string,file_type:string}
	 * @throws \RuntimeException On validation or processing failure.
	 */
	public static function process( array $_file, ?array $overrides = null ): array {
		self::validate( $_file, $overrides );

		$mime     = self::detect_mime( $_file['tmp_name'], $_file['name'] );
		$type_key = self::SUPPORTED_TYPES[ $mime ] ?? null;

		if ( null === $type_key ) {
			throw new \RuntimeException(
				sprintf( __( 'Unsupported file type: %s', 'overcustomise' ), $mime )
			);
		}

		return match ( $type_key ) {
			'svg'   => self::process_svg( $_file ),
			'pdf'   => self::process_pdf_eps( $_file, 'pdf' ),
			'eps'   => self::process_pdf_eps( $_file, 'eps' ),
			default => self::process_raster( $_file, $type_key ),
		};
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

		$max_mb = isset( $overrides['max_size_mb'] ) && $overrides['max_size_mb'] > 0
			? (int) $overrides['max_size_mb']
			: ( (int) OC_Admin_Settings::get( 'max_upload_size_mb' ) ?: 10 );
		$max_bytes = $max_mb * 1024 * 1024;

		$size = (int) ( $_file['size'] ?? 0 );
		if ( $size <= 0 ) {
			throw new \RuntimeException( __( 'Uploaded file is empty.', 'overcustomise' ) );
		}
		if ( $size > $max_bytes ) {
			throw new \RuntimeException(
				sprintf(
					/* translators: %d: max size in MB */
					__( 'File exceeds maximum size of %dMB.', 'overcustomise' ),
					$max_mb
				)
			);
		}

		$allowed_formats = ! empty( $overrides['formats'] ) && is_array( $overrides['formats'] )
			? $overrides['formats']
			: (array) OC_Admin_Settings::get( 'allowed_upload_formats' );
		$allowed_formats = array_values( array_filter( array_map( [ self::class, 'normalise_extension' ], $allowed_formats ) ) );
		$ext             = self::normalise_extension( pathinfo( $name, PATHINFO_EXTENSION ) );

		if ( '' === $ext ) {
			throw new \RuntimeException( __( 'File has no extension.', 'overcustomise' ) );
		}

		if ( ! empty( $allowed_formats ) && ! in_array( $ext, $allowed_formats, true ) ) {
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

	// -------------------------------------------------------------------------
	// Type-specific processors
	// -------------------------------------------------------------------------

	/** Process SVG: sanitise → save to WP media library. */
	private static function process_svg( array $_file ): array {
		$raw = file_get_contents( $_file['tmp_name'] );
		if ( false === $raw ) {
			throw new \RuntimeException( __( 'Could not read uploaded SVG.', 'overcustomise' ) );
		}

		$sanitised = OC_SVG_Sanitiser::sanitise( $raw );

		// Write sanitised content to a temp file so WP can handle the upload.
		$tmp = wp_tempnam( 'oc-svg-' );
		if ( false === file_put_contents( $tmp, $sanitised ) ) {
			@unlink( $tmp );
			throw new \RuntimeException( __( 'Could not stage sanitised SVG for upload.', 'overcustomise' ) );
		}

		$attachment_id = self::save_to_media_library(
			$tmp,
			sanitize_file_name( basename( (string) $_file['name'] ) ),
			'image/svg+xml'
		);

		@unlink( $tmp );

		if ( is_wp_error( $attachment_id ) ) {
			throw new \RuntimeException( $attachment_id->get_error_message() );
		}

		$url = wp_get_attachment_url( $attachment_id );

		return [
			'attachment_id' => $attachment_id,
			'preview_url'   => $url,
			'original_url'  => $url,
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

		$original_url  = wp_get_attachment_url( $original_id );
		$original_path = get_attached_file( $original_id );

		if ( ! $original_path || ! file_exists( $original_path ) ) {
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

		if ( $preview_generated && file_exists( $preview_path ) ) {
			$preview_id = self::save_to_media_library(
				$preview_path,
				pathinfo( $safe_name, PATHINFO_FILENAME ) . '-preview.png',
				'image/png'
			);
			@unlink( $preview_path );

			$preview_url = is_wp_error( $preview_id )
				? OC_URL . 'assets/images/pdf-placeholder.svg'
				: wp_get_attachment_url( $preview_id );
		} else {
			// Fallback: use a generic placeholder.
			$preview_url = OC_URL . 'assets/images/pdf-placeholder.svg';
			OC_Logger::warning( 'Could not generate preview for ' . $safe_name . ' — neither Imagick nor GhostScript available.' );
		}

		return [
			'attachment_id' => $original_id,
			'preview_url'   => $preview_url,
			'original_url'  => $original_url,
			'file_type'     => $type,
		];
	}

	/** Process PNG / JPG: validate as image, save to WP media library. */
	private static function process_raster( array $_file, string $type ): array {
		// Confirm it is actually an image via getimagesize.
		$image_info = @getimagesize( $_file['tmp_name'] );
		if ( ! $image_info || empty( $image_info['mime'] ) ) {
			throw new \RuntimeException( __( 'File is not a valid image.', 'overcustomise' ) );
		}

		$mime          = $image_info['mime'];
		$attachment_id = self::save_to_media_library(
			$_file['tmp_name'],
			sanitize_file_name( basename( (string) $_file['name'] ) ),
			$mime
		);

		if ( is_wp_error( $attachment_id ) ) {
			throw new \RuntimeException( $attachment_id->get_error_message() );
		}

		$url = wp_get_attachment_url( $attachment_id );

		return [
			'attachment_id' => $attachment_id,
			'preview_url'   => $url,
			'original_url'  => $url,
			'file_type'     => $type,
		];
	}

	// -------------------------------------------------------------------------
	// Conversion helpers
	// -------------------------------------------------------------------------

	/** Convert PDF/EPS page 1 to PNG via PHP Imagick extension. */
	private static function convert_via_imagick( string $source, string $dest ): bool {
		try {
			$im = new \Imagick();
			$im->setResolution( 150, 150 );
			$im->readImage( $source . '[0]' ); // First page only.
			$im->setImageFormat( 'png' );
			$im->setImageAlphaChannel( \Imagick::ALPHACHANNEL_REMOVE );
			$im->setImageBackgroundColor( 'white' );
			$im->flattenImages();
			$result = $im->writeImage( $dest );
			$im->clear();
			$im->destroy();
			return $result;
		} catch ( \ImagickException $e ) {
			OC_Logger::warning( 'Imagick conversion failed: ' . $e->getMessage() );
			return false;
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
				'-dFirstPage=1',
				'-dLastPage=1',
				'-sDEVICE=png16m',
				'-r150',
				'-dFITALL',
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

		return file_exists( $dest );
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
	 * Save a file to the WordPress media library.
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

		$upload_dir = wp_upload_dir();
		$subdir     = $upload_dir['basedir'] . '/' . self::UPLOAD_SUBDIR;
		wp_mkdir_p( $subdir );

		// Write .htaccess protection.
		// Keep artwork files publicly readable (for previews), but block script execution.
		$htaccess = $subdir . '/.htaccess';
		$rules    = "Options -Indexes\n"
			. "<FilesMatch \"\\.(php|phtml|phar|cgi|pl|py|sh|bash)$\">\n"
			. "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n"
			. "<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n"
			. "</FilesMatch>\n";
		if ( ! file_exists( $htaccess ) || false === strpos( (string) file_get_contents( $htaccess ), 'FilesMatch' ) ) {
			file_put_contents( $htaccess, $rules );
		}

		// Unique destination filename.
		$dest_filename = wp_unique_filename( $subdir, $filename );
		$dest_path     = $subdir . '/' . $dest_filename;

		// Copy file to upload directory (don't move — tmp file still needed).
		if ( ! copy( $file_path, $dest_path ) ) {
			return new \WP_Error( 'copy_failed', __( 'Could not save uploaded file.', 'overcustomise' ) );
		}

		// Build the attachment array.
		$attachment = [
			'post_mime_type' => $mime_type,
			'post_title'     => preg_replace( '/\.[^.]+$/', '', $dest_filename ),
			'post_content'   => '',
			'post_status'    => 'inherit',
			'post_parent'    => 0,
		];

		$file_url = $upload_dir['baseurl'] . '/' . self::UPLOAD_SUBDIR . '/' . $dest_filename;

		$attachment_id = wp_insert_attachment( $attachment, $dest_path, 0, true );

		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}
		if ( ! $attachment_id ) {
			return new \WP_Error( 'insert_failed', __( 'Could not register uploaded file.', 'overcustomise' ) );
		}

		// Generate image sizes for raster images.
		if ( in_array( $mime_type, [ 'image/png', 'image/jpeg', 'image/jpg' ], true ) ) {
			$metadata = wp_generate_attachment_metadata( $attachment_id, $dest_path );
			if ( is_array( $metadata ) ) {
				wp_update_attachment_metadata( $attachment_id, $metadata );
			}
		}

		// Tag as OC artwork for easy identification.
		update_post_meta( $attachment_id, '_oc_artwork', 1 );
		update_post_meta( $attachment_id, '_oc_artwork_type', pathinfo( $filename, PATHINFO_EXTENSION ) );

		return $attachment_id;
	}

	// -------------------------------------------------------------------------
	// MIME detection
	// -------------------------------------------------------------------------

	/**
	 * Detect real MIME type via finfo, with SVG XML detection fallback.
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
		} elseif ( function_exists( 'mime_content_type' ) ) {
			$mime = mime_content_type( $tmp_path ) ?: 'application/octet-stream';
		} else {
			$mime = 'application/octet-stream';
		}

		// finfo often returns text/plain or text/xml for SVG — peek at content.
		if ( in_array( $mime, [ 'text/plain', 'text/xml', 'application/xml', 'application/octet-stream' ], true ) ) {
			$content = file_get_contents( $tmp_path, false, null, 0, 512 );
			if ( false !== $content && false !== strpos( $content, '<svg' ) ) {
				return 'image/svg+xml';
			}
		}

		// Some systems return text/plain or octet-stream for EPS — use extension hint.
		$ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		if ( 'eps' === $ext && in_array( $mime, [ 'text/plain', 'application/octet-stream' ], true ) ) {
			return 'application/eps';
		}

		return $mime;
	}
}
