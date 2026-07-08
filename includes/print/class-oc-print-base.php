<?php
/**
 * Abstract base for print file generators.
 *
 * Shared utilities: dimension conversion, font loading, color conversion,
 * directory management, and TCPDF bootstrapping.
 *
 * Canvas coordinate assumption: 300 DPI.
 * 1 canvas pixel = 25.4/300 mm = 0.0847 mm.
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

abstract class OC_Print_Base {

	/** Assumed DPI for canvas coordinates stored in the DB. */
	protected const CANVAS_DPI = 300;

	/** Engraving print files must output customer text and clipart as black. */
	protected const ENGRAVING_TONE_RGB = [ 0, 0, 0 ];

	/** Subdirectory within wp-content/uploads for generated print files. */
	protected const PRINT_SUBDIR = 'overcustomise/print-files';

	// -------------------------------------------------------------------------
	// Dimension helpers
	// -------------------------------------------------------------------------

	/** Convert canvas pixels to millimetres at CANVAS_DPI. */
	protected static function px_to_mm( int $pixels ): float {
		return round( $pixels * 25.4 / self::CANVAS_DPI, 3 );
	}

	/** Convert a stored print-bound value to millimetres using its selected unit. */
	protected static function unit_to_mm( float $value, string $unit ): float {
		switch ( $unit ) {
			case 'mm':
				return round( $value, 3 );
			case 'cm':
				return round( $value * 10, 3 );
			case 'in':
				return round( $value * 25.4, 3 );
			case 'px':
			default:
				return self::px_to_mm( (int) round( $value ) );
		}
	}

	/** Return the physical print area dimensions in millimetres. */
	protected static function area_dimensions_mm( object $area ): array {
		$unit = isset( $area->canvas_unit ) ? (string) $area->canvas_unit : 'px';

		return [
			self::unit_to_mm( (float) ( $area->canvas_w ?? 1 ), $unit ),
			self::unit_to_mm( (float) ( $area->canvas_h ?? 1 ), $unit ),
		];
	}

	/** Convert canvas pixels to font points at CANVAS_DPI. */
	protected static function px_to_pt( float $pixels ): float {
		return round( $pixels * 72 / self::CANVAS_DPI, 3 );
	}

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
		$upload_dir = wp_upload_dir();
		if ( ! empty( $upload_dir['error'] ) ) {
			throw new \RuntimeException( (string) $upload_dir['error'] );
		}
		$base       = $upload_dir['basedir'] . '/' . self::PRINT_SUBDIR;
		$dir        = $base . '/' . $order_id;

		if ( ! wp_mkdir_p( $dir ) ) {
			throw new \RuntimeException( __( 'Could not create print output directory.', 'overcustomise' ) );
		}

		// Protect the print-files root with .htaccess if not already there.
		$htaccess = $base . '/.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			if ( false === file_put_contents( $htaccess, "Options -Indexes\nDeny from all\n" ) ) {
				throw new \RuntimeException( __( 'Could not protect print directory.', 'overcustomise' ) );
			}
		}

		return $dir;
	}

	/** Build a stable output filename for a print file. */
	protected static function build_filename( int $item_id, string $area_key, string $extension ): string {
		return sprintf( '%d-%s.%s', $item_id, sanitize_file_name( $area_key ), $extension );
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

	// -------------------------------------------------------------------------
	// Font helpers
	// -------------------------------------------------------------------------

	/** Fetch a font DB row by its ID (or null if not found / inactive). */
	protected static function get_font( int $font_id ): ?object {
		if ( ! $font_id ) {
			return null;
		}
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}oc_fonts WHERE id = %d AND active = 1 LIMIT 1",
			$font_id
		) ) ?: null;
	}

	/** Return the absolute path to a font file, or null if not accessible. */
	protected static function get_font_path( object $font ): ?string {
		// Validate font path doesn't contain directory traversal.
		$file_path = ltrim( (string) ( $font->file_path ?? '' ), '/' );
		if ( '' === $file_path || str_contains( $file_path, '..' ) ) {
			return null;
		}
		
		$path = wp_upload_dir()['basedir'] . '/' . $file_path;
		$real = realpath( $path );
		$base = realpath( wp_upload_dir()['basedir'] );
		
		// Ensure the font file is within the uploads directory.
		if ( ! $real || ! $base || 0 !== strpos( $real, $base ) ) {
			return null;
		}
		
		if ( ! file_exists( $real ) ) {
			return null;
		}

		if ( 'otf' === strtolower( pathinfo( $real, PATHINFO_EXTENSION ) ) && self::is_cff_opentype( $real ) ) {
			OC_Logger::warning( 'Print font fallback: ' . basename( $real ) . ' is an OpenType/CFF font. TCPDF needs a TrueType-outline TTF/OTF file.' );
			return null;
		}

		if ( 'woff' === strtolower( pathinfo( $real, PATHINFO_EXTENSION ) ) && class_exists( 'OC_WOFF_Converter' ) ) {
			if ( self::is_cff_woff( $real ) ) {
				OC_Logger::warning( 'Print font fallback: ' . basename( $real ) . ' is a WOFF-wrapped OpenType/CFF font. TCPDF needs a TrueType-outline TTF/OTF file.' );
				return null;
			}

			$upload_dir = wp_upload_dir();
			$cache_dir  = trailingslashit( $upload_dir['basedir'] ) . 'overcustomise/tcpdf-fonts';
			wp_mkdir_p( $cache_dir );
			$dest = trailingslashit( $cache_dir ) . sanitize_file_name( pathinfo( $real, PATHINFO_FILENAME ) ) . '-' . md5_file( $real ) . '.ttf';
			if ( file_exists( $dest ) || OC_WOFF_Converter::extract_sfnt( $real, $dest ) ) {
				return $dest;
			}
		}

		return $real;
	}

	protected static function is_cff_opentype( string $path ): bool {
		$handle = fopen( $path, 'rb' );
		if ( ! $handle ) {
			return false;
		}
		$signature = fread( $handle, 4 );
		fclose( $handle );

		return 'OTTO' === $signature;
	}

	protected static function is_cff_woff( string $path ): bool {
		$handle = fopen( $path, 'rb' );
		if ( ! $handle ) {
			return false;
		}
		$signature = fread( $handle, 4 );
		$flavor    = fread( $handle, 4 );
		fclose( $handle );

		return 'wOFF' === $signature && 'OTTO' === $flavor;
	}

	/**
	 * Register a TTF/OTF font with TCPDF and return the internal font name.
	 * Returns empty string on failure (caller should fall back to a core font).
	 *
	 * @param  string $font_path  Absolute path to the font file.
	 * @return string             TCPDF font name.
	 */
	protected static function register_tcpdf_font( string $font_path ): string {
		if ( ! file_exists( $font_path ) ) {
			return '';
		}

		if ( ! class_exists( '\TCPDF_FONTS' ) ) {
			OC_Logger::warning( 'TCPDF font registration skipped for ' . basename( $font_path ) . ': TCPDF_FONTS helper is not available in this TCPDF build.' );
			return '';
		}

		try {
			$upload_dir = wp_upload_dir();
			$font_dir   = trailingslashit( $upload_dir['basedir'] ) . 'overcustomise/tcpdf-fonts/';
			wp_mkdir_p( $font_dir );

			$name = \TCPDF_FONTS::addTTFfont( $font_path, 'TrueTypeUnicode', '', 96, $font_dir );
			return is_string( $name ) ? $name : '';
		} catch ( \Throwable $e ) {
			OC_Logger::warning( 'TCPDF font registration failed for ' . basename( $font_path ) . ': ' . $e->getMessage() );
			return '';
		}
	}

	/** Write a TCPDF instance to an exact file path across TCPDF wrapper versions. */
	protected static function write_pdf_file( \TCPDF $pdf, string $output_path ): void {
		$dir = dirname( $output_path );
		if ( ! is_dir( $dir ) || ! is_writable( $dir ) ) {
			throw new \RuntimeException( sprintf( 'Print output directory is not writable: %s', $dir ) );
		}

		$raw = $pdf->Output( basename( $output_path ), 'S' );
		if ( ! is_string( $raw ) || '' === $raw ) {
			throw new \RuntimeException( 'TCPDF returned an empty PDF document.' );
		}

		if ( false === file_put_contents( $output_path, $raw ) ) {
			throw new \RuntimeException( sprintf( 'Could not write print PDF to %s', $output_path ) );
		}
	}

	/** Embed an image in TCPDF, converting artwork to a safe PNG when needed. */
	protected static function draw_pdf_image( \TCPDF $pdf, string $path, float $x_mm, float $y_mm, float $w_mm, float $h_mm ): void {
		$temp_path      = null;
		$fallback_path  = null;
		$image_path     = self::tcpdf_compatible_image_path( $path, $temp_path );

		try {
			try {
				$pdf->Image( $image_path, $x_mm, $y_mm, $w_mm, $h_mm, '', '', '', false, 300 );
			} catch ( \Throwable $e ) {
				$fallback_path = self::normalise_raster_image_for_tcpdf( $path );
				if ( ! is_string( $fallback_path ) || '' === $fallback_path || $fallback_path === $image_path ) {
					throw $e;
				}

				OC_Logger::warning( 'TCPDF could not read print artwork directly, retrying normalised PNG: ' . basename( $path ) . ' (' . $e->getMessage() . ')' );
				$pdf->Image( $fallback_path, $x_mm, $y_mm, $w_mm, $h_mm, '', '', '', false, 300 );
			}
		} finally {
			if ( is_string( $temp_path ) && '' !== $temp_path && file_exists( $temp_path ) ) {
				@unlink( $temp_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
			if ( is_string( $fallback_path ) && '' !== $fallback_path && file_exists( $fallback_path ) ) {
				@unlink( $fallback_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
		}
	}

	/** TCPDF does not reliably import WEBP, so create a temporary PNG copy first. */
	private static function tcpdf_compatible_image_path( string $path, ?string &$temp_path ): string {
		$temp_path = null;
		if ( 'webp' !== strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) ) {
			return $path;
		}

		$temp = self::temp_path( 'oc-tcpdf-webp-' . wp_generate_uuid4() . '.png' );
		if ( ! is_string( $temp ) || '' === $temp ) {
			return $path;
		}

		$src = self::open_raster_resource( $path );
		if ( ! $src ) {
			if ( self::convert_image_with_imagick( $path, $temp ) ) {
				$temp_path = $temp;
				return $temp;
			}

			@unlink( $temp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return $path;
		}

		imagesavealpha( $src, true );
		if ( ! imagepng( $src, $temp ) ) {
			imagedestroy( $src );
			@unlink( $temp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return $path;
		}

		imagedestroy( $src );
		$temp_path = $temp;
		return $temp;
	}

	/** Re-encode raster artwork to a PNG that TCPDF can import. */
	private static function normalise_raster_image_for_tcpdf( string $path ): ?string {
		$ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, [ 'jpg', 'jpeg', 'png', 'webp', 'bmp', 'gif' ], true ) ) {
			return null;
		}

		$temp = self::temp_path( 'oc-tcpdf-raster-' . wp_generate_uuid4() . '.png' );
		if ( ! is_string( $temp ) || '' === $temp ) {
			return null;
		}

		$src = self::open_raster_resource( $path );
		if ( $src ) {
			if ( function_exists( 'imagepalettetotruecolor' ) ) {
				imagepalettetotruecolor( $src );
			}
			imagealphablending( $src, false );
			imagesavealpha( $src, true );

			if ( imagepng( $src, $temp ) ) {
				imagedestroy( $src );
				return $temp;
			}

			imagedestroy( $src );
		}

		if ( self::convert_image_with_imagick( $path, $temp ) ) {
			return $temp;
		}

		@unlink( $temp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		return null;
	}

	/** Convert an image to PNG using Imagick when GD cannot open it. */
	private static function convert_image_with_imagick( string $path, string $output_path ): bool {
		if ( ! class_exists( '\Imagick' ) ) {
			return false;
		}

		try {
			$imagick = new \Imagick( $path );
			$imagick->setImageFormat( 'png' );
			$imagick->setImageAlphaChannel( \Imagick::ALPHACHANNEL_ACTIVATE );
			$result = $imagick->writeImage( $output_path );
			$imagick->clear();
			$imagick->destroy();
			return (bool) $result;
		} catch ( \Throwable $e ) {
			OC_Logger::warning( 'Image to PNG conversion failed for print artwork: ' . $e->getMessage() );
			return false;
		}
	}

	// -------------------------------------------------------------------------
	// Colour helpers
	// -------------------------------------------------------------------------

	/**
	 * Convert a CSS hex colour to a CMYK array [C, M, Y, K] (0–100).
	 *
	 * @param  string $hex  e.g. '#ff0000' or 'ff0000'
	 * @return float[]      [C, M, Y, K]
	 */
	protected static function hex_to_cmyk( string $hex ): array {
		$hex = ltrim( $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		$r = hexdec( substr( $hex, 0, 2 ) ) / 255;
		$g = hexdec( substr( $hex, 2, 2 ) ) / 255;
		$b = hexdec( substr( $hex, 4, 2 ) ) / 255;

		$k = 1 - max( $r, $g, $b );
		if ( $k >= 1.0 ) {
			return [ 0.0, 0.0, 0.0, 100.0 ];
		}

		$c = ( 1 - $r - $k ) / ( 1 - $k );
		$m = ( 1 - $g - $k ) / ( 1 - $k );
		$y = ( 1 - $b - $k ) / ( 1 - $k );

		return [
			round( $c * 100, 1 ),
			round( $m * 100, 1 ),
			round( $y * 100, 1 ),
			round( $k * 100, 1 ),
		];
	}

	/**
	 * Convert a CSS hex colour to an RGB array [R, G, B] (0–255).
	 *
	 * @param  string $hex
	 * @return int[]
	 */
	protected static function hex_to_rgb( string $hex ): array {
		$hex = ltrim( $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		return [
			hexdec( substr( $hex, 0, 2 ) ),
			hexdec( substr( $hex, 2, 2 ) ),
			hexdec( substr( $hex, 4, 2 ) ),
		];
	}

	// -------------------------------------------------------------------------
	// TCPDF bootstrap
	// -------------------------------------------------------------------------

	/** Ensure TCPDF is available via Composer autoloader and cache dir is writable. */
	protected static function require_tcpdf(): void {
		$autoloader = OC_PATH . 'vendor/autoload.php';
		if ( ! file_exists( $autoloader ) ) {
			throw new \RuntimeException( sprintf( 'TCPDF not available — Composer autoload file missing at %s. Run `composer install` in that plugin directory.', $autoloader ) );
		}

		// Point TCPDF's font cache to the WP uploads directory so it is always
		// writable, regardless of the server's PHP configuration.
		if ( ! defined( 'K_PATH_CACHE' ) ) {
			$cache_dir = wp_upload_dir()['basedir'] . '/overcustomise/tcpdf-cache/';
			wp_mkdir_p( $cache_dir );
			define( 'K_PATH_CACHE', $cache_dir );
		}

		if ( ! defined( 'K_PATH_FONTS' ) ) {
			define( 'K_PATH_FONTS', OC_PATH . 'vendor/tecnickcom/tc-lib-pdf-font/target/fonts' );
		}

		if ( ! file_exists( K_PATH_FONTS . '/core/helvetica.json' ) ) {
			throw new \RuntimeException( sprintf( 'TCPDF font assets are missing at %s. Run `composer run tcpdf:fonts` in the plugin directory and deploy the generated vendor/tecnickcom/tc-lib-pdf-font/target/fonts directory.', K_PATH_FONTS . '/core/helvetica.json' ) );
		}

		if ( ! class_exists( '\\TCPDF' ) ) {
			require_once $autoloader;
		}

		if ( ! class_exists( '\\TCPDF' ) ) {
			throw new \RuntimeException( sprintf( 'TCPDF not available — Composer autoload loaded from %s but TCPDF class was not registered.', $autoloader ) );
		}
	}

	/**
	 * Create a base TCPDF instance with shared defaults.
	 *
	 * @param  float  $w_mm  Page width in mm (without bleed).
	 * @param  float  $h_mm  Page height in mm (without bleed).
	 * @param  float  $bleed Bleed in mm (added to all sides).
	 * @return \TCPDF
	 */
	protected static function make_pdf( float $w_mm, float $h_mm, float $bleed = 0.0 ): \TCPDF {
		self::require_tcpdf();

		$page_w = $w_mm + $bleed * 2;
		$page_h = $h_mm + $bleed * 2;

		$pdf = new \TCPDF( 'P', 'mm', [ $page_w, $page_h ], true, 'UTF-8' );
		$pdf->SetCreator( 'OverCustomise' );
		$pdf->SetAuthor( 'Custom Kings' );
		$pdf->SetMargins( $bleed, $bleed, $bleed );
		$pdf->SetAutoPageBreak( false, 0 );
		$pdf->setPrintHeader( false );
		$pdf->setPrintFooter( false );

		return $pdf;
	}

	// -------------------------------------------------------------------------
	// Shared text/font helpers (used by all generators)
	// -------------------------------------------------------------------------

	/**
	 * Resolve a TCPDF font name from a font DB ID.
	 * Falls back to 'helvetica' if the font is missing or cannot be registered.
	 */
	protected static function resolve_font( int $font_id, ?\TCPDF $pdf = null ): string {
		if ( $font_id ) {
			$font = self::get_font( $font_id );
			if ( $font ) {
				$path = self::get_font_path( $font );
				if ( $path ) {
					$name = self::register_tcpdf_font( $path );
					if ( $name ) {
						if ( $pdf ) {
							$font_file = self::tcpdf_font_definition_path( $path, $name );
							if ( $font_file ) {
								$pdf->AddFont( $name, '', $font_file );
							}
						}
						return $name;
					}
					OC_Logger::warning( 'Print font fallback: TCPDF could not register font #' . $font_id . ' from ' . basename( $path ) . '.' );
				} else {
					OC_Logger::warning( 'Print font fallback: font #' . $font_id . ' file was not accessible.' );
				}
			} else {
				OC_Logger::warning( 'Print font fallback: font #' . $font_id . ' was not found or inactive.' );
			}
		}
		return 'helvetica';
	}

	protected static function tcpdf_font_definition_path( string $font_path, string $font_name ): string {
		$upload_dir = wp_upload_dir();
		$font_dir   = trailingslashit( $upload_dir['basedir'] ) . 'overcustomise/tcpdf-fonts/';
		$font_file  = $font_dir . $font_name . '.php';

		return file_exists( $font_file ) ? $font_file : '';
	}

	/**
	 * Auto-size font to fit text within the live area width (max ~40% of height).
	 *
	 * @param \TCPDF $pdf
	 * @param string $text
	 * @param string $font_name  Already-registered TCPDF font name.
	 * @param float  $w_mm       Live area width in mm.
	 * @param float  $h_mm       Live area height in mm.
	 * @return float             Font size in points.
	 */
	protected static function auto_font_size( \TCPDF $pdf, string $text, string $font_name, float $w_mm, float $h_mm, float $min_size = 0.0, float $max_size = 0.0 ): float {
		$min_size = max( 4.0, $min_size );
		$max_size = max( 0.0, $max_size );
		$size     = max( 8.0, $h_mm * 0.4 );

		if ( $max_size > 0.0 ) {
			$size = min( $size, $max_size );
		}
		if ( $min_size > $size ) {
			$size = $min_size;
		}

		while ( $size > $min_size ) {
			$pdf->SetFont( $font_name, '', $size );
			if ( self::text_fits_box( $pdf, $text, $w_mm * 0.92, $h_mm, $size ) ) {
				break;
			}
			$size -= 0.5;
		}

		return $size;
	}

	/** Resolve optional font-size bounds from generated area data. */
	protected static function font_size_bounds( array $area_data ): array {
		return [
			(float) max( 0, absint( $area_data['minFontSize'] ?? 0 ) ),
			(float) max( 0, absint( $area_data['maxFontSize'] ?? 0 ) ),
		];
	}

	/**
	 * Convert font size in points to an appropriate cell height in mm.
	 * Uses 1.2× line height.
	 */
	protected static function cell_h( float $font_size_pt ): float {
		return $font_size_pt * 0.3528 * 1.2; // 1pt = 0.3528mm
	}

	/** Return true when the current font size fits within the supplied box. */
	protected static function text_fits_box(
		\TCPDF $pdf,
		string $text,
		float $w_mm,
		float $h_mm,
		float $font_size_pt
	): bool {
		return $pdf->GetStringWidth( $text ) <= $w_mm && self::cell_h( $font_size_pt ) <= $h_mm;
	}

	/** Draw text constrained to the supplied box. */
	protected static function draw_clipped_text_cell(
		\TCPDF $pdf,
		float $x_mm,
		float $y_mm,
		float $w_mm,
		float $h_mm,
		string $text,
		float $cell_h,
		string $align = 'C',
		string $valign = 'C'
	): void {
		$offset_y = match ( $valign ) {
			'T' => 0.0,
			'B' => max( 0.0, $h_mm - $cell_h ),
			default => max( 0.0, ( $h_mm - $cell_h ) / 2 ),
		};

		$pdf->StartTransform();
		$pdf->Rect( $x_mm, $y_mm, $w_mm, $h_mm, 'CNZ' );
		$pdf->SetXY( $x_mm, $y_mm + $offset_y );
		$pdf->Cell( $w_mm, $cell_h, $text, 0, 0, $align, false );
		$pdf->StopTransform();
	}

	/** Remove emoji that cannot be reproduced as engraving or thread-colour output. */
	protected static function normalise_engraving_text( string $text ): string {
		if ( '' === $text ) {
			return '';
		}

		$text = preg_replace( '/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}][\x{FE0E}\x{FE0F}]?/u', '', $text ) ?? $text;

		return preg_replace( '/[\x{1F3FB}-\x{1F3FF}\x{1F9B0}-\x{1F9B3}\x{200D}\x{FE0E}\x{FE0F}]/u', '', $text ) ?? $text;
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
				return $attachment_path;
			}
		}

		if ( ! empty( $area_data['artworkPath'] ) && is_string( $area_data['artworkPath'] ) ) {
			$real = self::resolve_uploads_file_path( $area_data['artworkPath'] );
			if ( $real ) {
				return $real;
			}
		}

		return null;
	}

	/** Resolve a media-library artwork attachment, tolerating stale absolute upload paths. */
	protected static function resolve_attachment_artwork_path( int $attachment_id ): ?string {
		if ( $attachment_id <= 0 ) {
			return null;
		}

		$attachment_path = get_attached_file( $attachment_id );
		if ( is_string( $attachment_path ) && '' !== $attachment_path ) {
			$real = self::resolve_uploads_file_path( $attachment_path );
			if ( $real ) {
				return $real;
			}
		}

		$attached_file = get_post_meta( $attachment_id, '_wp_attached_file', true );
		if ( is_string( $attached_file ) && '' !== $attached_file ) {
			$real = self::resolve_uploads_file_path( $attached_file );
			if ( $real ) {
				return $real;
			}
		}

		return null;
	}

	/** Resolve a path or uploads-relative filename to a readable file inside the current uploads directory. */
	protected static function resolve_uploads_file_path( string $path ): ?string {
		$uploads   = wp_upload_dir();
		$base_real = ! empty( $uploads['basedir'] ) ? realpath( $uploads['basedir'] ) : false;
		if ( ! $base_real ) {
			return null;
		}

		$candidates = [ $path ];
		if ( ! str_starts_with( $path, '/' ) ) {
			$candidates[] = trailingslashit( (string) $uploads['basedir'] ) . ltrim( $path, '/\\' );
		}

		$normalised = str_replace( '\\', '/', $path );
		$marker_pos = strpos( $normalised, '/uploads/' );
		if ( false !== $marker_pos ) {
			$relative = substr( $normalised, $marker_pos + strlen( '/uploads/' ) );
			if ( is_string( $relative ) && '' !== $relative ) {
				$candidates[] = trailingslashit( (string) $uploads['basedir'] ) . ltrim( $relative, '/\\' );
			}
		}

		foreach ( array_unique( $candidates ) as $candidate ) {
			$real = realpath( $candidate );
			if ( $real && 0 === strpos( $real, $base_real ) && is_readable( $real ) ) {
				return $real;
			}
		}

		return null;
	}

	/** Return true when the print payload contains v2 layer geometry. */
	protected static function has_layer_payload( array $area_data ): bool {
		return ! empty( $area_data['layers'] ) && is_array( $area_data['layers'] );
	}

	/**
	 * Render v2 layers into the PDF using the same layer boxes as the live preview.
	 * Layer coordinates are stored in mockup pixels, so they are offset back into
	 * print-area space before converting to millimetres.
	 */
	protected static function render_layer_payload( \TCPDF $pdf, object $area, array $area_data, float $origin_x_mm, float $origin_y_mm, string $mode = 'colour' ): void {
		$bounds = is_array( $area_data['bounds'] ?? null ) ? $area_data['bounds'] : [];
		$unit   = isset( $area->canvas_unit ) ? (string) $area->canvas_unit : 'px';
		$area_x = isset( $bounds['x'] ) ? (float) $bounds['x'] : (float) ( $area->canvas_x ?? 0 );
		$area_y = isset( $bounds['y'] ) ? (float) $bounds['y'] : (float) ( $area->canvas_y ?? 0 );

		foreach ( $area_data['layers'] as $layer ) {
			if ( ! is_array( $layer ) ) {
				continue;
			}

			$type = (string) ( $layer['type'] ?? '' );
			$x_mm = $origin_x_mm + self::unit_to_mm( (float) ( $layer['x'] ?? 0 ) - $area_x, $unit );
			$y_mm = $origin_y_mm + self::unit_to_mm( (float) ( $layer['y'] ?? 0 ) - $area_y, $unit );
			$w_mm = self::unit_to_mm( max( 1.0, (float) ( $layer['w'] ?? 1 ) ), $unit );
			$h_mm = self::unit_to_mm( max( 1.0, (float) ( $layer['h'] ?? 1 ) ), $unit );
			$input = is_array( $layer['input'] ?? null ) ? $layer['input'] : [];
			$settings = is_array( $layer['settings'] ?? null ) ? $layer['settings'] : [];

			switch ( $type ) {
				case 'text':
				case 'textarea':
					self::render_layer_text( $pdf, $layer, $input, $settings, $x_mm, $y_mm, $w_mm, $h_mm, $mode );
					break;

				case 'spotify':
					self::render_layer_spotify( $pdf, $input, $x_mm, $y_mm, $w_mm, $h_mm, $mode );
					break;

				case 'image':
				case 'clipart':
					self::render_layer_image( $pdf, $layer, $input, $x_mm, $y_mm, $w_mm, $h_mm, $mode );
					break;

				case 'clipmask':
					self::render_layer_clipped_image( $pdf, $layer, $x_mm, $y_mm, $w_mm, $h_mm, $mode );
					break;

				case 'lineart':
					$hex = (string) ( $input['colorHex'] ?? '#000000' );
					if ( 'engraving' === $mode ) {
						$pdf->SetFillColor( ...self::ENGRAVING_TONE_RGB );
					} else {
						[ $c, $m, $y, $k ] = self::hex_to_cmyk( $hex );
						$pdf->SetFillColorArray( [ $c, $m, $y, $k ] );
					}
					$pdf->Rect( $x_mm, $y_mm, $w_mm, $h_mm, 'F' );
					break;
			}
		}
	}

	private static function render_layer_text( \TCPDF $pdf, array $layer, array $input, array $settings, float $x_mm, float $y_mm, float $w_mm, float $h_mm, string $mode ): void {
		$text = trim( (string) ( $input['value'] ?? '' ) );
		if ( '' === $text ) {
			return;
		}
		if ( 'engraving' === $mode ) {
			$text = self::normalise_engraving_text( $text );
		}

		$font_id   = ! empty( $input['fontId'] ) ? (int) $input['fontId'] : (int) ( $settings['default_font_id'] ?? 0 );
		$font      = $font_id ? self::get_font( $font_id ) : null;
		$font_name = 'engraving' === $mode ? 'helvetica' : self::resolve_font( $font_id, $pdf );
		$font_size = ! empty( $input['fontSize'] ) || ! empty( $settings['default_font_size'] )
			? self::px_to_pt( (float) ( $input['fontSize'] ?? $settings['default_font_size'] ) )
			: max( 4.0, self::px_to_pt( max( 1, (int) ( $layer['h'] ?? 1 ) ) * 0.42 ) );
		$min_size  = ! empty( $settings['min_font_size'] ) ? self::px_to_pt( (float) $settings['min_font_size'] ) : 0.0;
		$max_size  = ! empty( $settings['max_font_size'] ) ? self::px_to_pt( (float) $settings['max_font_size'] ) : 0.0;
		if ( $max_size > 0.0 ) {
			$font_size = min( $font_size, $max_size );
		}
		if ( $min_size > 0.0 ) {
			$font_size = max( $font_size, $min_size );
		}

		while ( $font_size > max( 4.0, $min_size ) ) {
			$pdf->SetFont( $font_name, '', $font_size );
			if ( self::text_fits_box( $pdf, $text, $w_mm, $h_mm, $font_size ) ) {
				break;
			}
			$font_size -= 0.5;
		}

		$align = strtoupper( substr( (string) ( $settings['alignment'] ?? 'center' ), 0, 1 ) );
		if ( ! in_array( $align, [ 'L', 'C', 'R' ], true ) ) {
			$align = 'C';
		}
		$valign_setting = 'textarea' === (string) ( $layer['type'] ?? '' ) ? (string) ( $settings['line_alignment'] ?? 'top' ) : 'center';
		$valign = match ( $valign_setting ) {
			'top' => 'T',
			'bottom' => 'B',
			default => 'C',
		};

		if ( 'engraving' === $mode && is_object( $font ) ) {
			$font_path = self::get_font_path( $font );
			if ( is_string( $font_path ) && '' !== $font_path && self::render_engraving_text_outline( $pdf, $text, $font_path, $font_size, $x_mm, $y_mm, $w_mm, $h_mm, $align ) ) {
				return;
			}
		}

		$pdf->SetFont( $font_name, '', $font_size );
		if ( 'engraving' === $mode ) {
			$pdf->SetTextColor( ...self::ENGRAVING_TONE_RGB );
		} else {
			[ $c, $m, $y, $k ] = self::hex_to_cmyk( (string) ( $input['colorHex'] ?? $settings['default_color'] ?? '#000000' ) );
			$pdf->SetTextColorArray( [ $c, $m, $y, $k ] );
		}

		$cell_h = self::cell_h( $font_size );
		self::draw_clipped_text_cell( $pdf, $x_mm, $y_mm, $w_mm, $h_mm, $text, $cell_h, $align, $valign );
	}

	private static function render_engraving_text_outline( \TCPDF $pdf, string $text, string $font_path, float $font_size, float $x_mm, float $y_mm, float $w_mm, float $h_mm, string $align ): bool {
		if ( ! class_exists( 'OC_Print_Embroidery' ) || ! method_exists( 'OC_Print_Embroidery', 'ttf_text_outline' ) ) {
			return false;
		}

		try {
			$method  = new \ReflectionMethod( 'OC_Print_Embroidery', 'ttf_text_outline' );
			$outline = $method->invoke( null, $font_path, $text, $font_size );
		} catch ( \Throwable $e ) {
			OC_Logger::warning( 'Engraving text outline failed: ' . $e->getMessage() );
			return false;
		}

		if ( ! is_array( $outline ) || empty( $outline['commands'] ) ) {
			return false;
		}

		$d = self::eps_outline_commands_to_svg_path( $outline['commands'] );
		if ( '' === $d ) {
			return false;
		}

		$width     = max( 0.01, (float) ( $outline['width'] ?? 0.0 ) );
		$bbox      = is_array( $outline['bbox'] ?? null ) ? $outline['bbox'] : [ 0.0, 0.0, $width, $font_size ];
		$glyph_w   = max( 0.01, (float) $bbox[2] - (float) $bbox[0] );
		$glyph_h   = max( 0.01, (float) $bbox[3] - (float) $bbox[1] );
		$box_w_pt  = self::mm_to_pt_value( $w_mm );
		$box_h_pt  = self::mm_to_pt_value( $h_mm );
		$layout_min_x = min( 0.0, (float) $bbox[0] );
		$layout_max_x = max( $width, (float) $bbox[2] );
		$layout_w     = max( 0.01, $layout_max_x - $layout_min_x );
		$fit_scale = min( 1.0, $box_w_pt / $layout_w, $box_h_pt / $glyph_h );
		$fit_scale = max( 0.01, $fit_scale );
		$pad       = max( 1.0, $font_size * $fit_scale * 0.08 );
		$advance_w = $width * $fit_scale;
		$draw_w    = $glyph_w * $fit_scale + $pad * 2;
		$draw_h    = $glyph_h * $fit_scale + $pad * 2;
		$origin_x  = match ( $align ) {
			'R' => $box_w_pt - $advance_w + (float) $bbox[0] * $fit_scale - $pad,
			'L' => (float) $bbox[0] * $fit_scale - $pad,
			default => ( $box_w_pt - $advance_w ) / 2 + (float) $bbox[0] * $fit_scale - $pad,
		};
		$origin_y  = ( $box_h_pt - $draw_h ) / 2;
		$path_x    = -1 * (float) $bbox[0] * $fit_scale + $pad;
		$path_y    = (float) $bbox[3] * $fit_scale + $pad;

		$svg = sprintf(
			'<svg xmlns="http://www.w3.org/2000/svg" width="%.4Fpt" height="%.4Fpt" viewBox="0 0 %.4F %.4F"><g transform="translate(%.4F %.4F) scale(%.8F %.8F)"><path d="%s" fill="#000000" fill-rule="evenodd" clip-rule="evenodd"/></g></svg>',
			$draw_w,
			$draw_h,
			$draw_w,
			$draw_h,
			$path_x,
			$path_y,
			$fit_scale,
			$fit_scale,
			htmlspecialchars( $d, ENT_QUOTES | ENT_XML1, 'UTF-8' )
		);

		$temp_base = self::temp_path( 'oc-engraving-text-outline-' . wp_generate_uuid4() . '.svg' );
		if ( ! is_string( $temp_base ) || '' === $temp_base ) {
			return false;
		}
		$temp = 'svg' === strtolower( pathinfo( $temp_base, PATHINFO_EXTENSION ) ) ? $temp_base : $temp_base . '.svg';

		if ( false === file_put_contents( $temp, $svg ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			@unlink( $temp_base ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return false;
		}

		try {
			$pdf->ImageSVG( $temp, $x_mm + self::pt_to_mm_value( $origin_x ), $y_mm + self::pt_to_mm_value( $origin_y ), self::pt_to_mm_value( $draw_w ), self::pt_to_mm_value( $draw_h ), '', '', '', 0, false );
			return true;
		} catch ( \Throwable $e ) {
			OC_Logger::warning( 'Engraving text outline SVG render failed: ' . $e->getMessage() );
			return false;
		} finally {
			@unlink( $temp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( $temp !== $temp_base ) {
				@unlink( $temp_base ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
		}
	}

	private static function eps_outline_commands_to_svg_path( array $commands ): string {
		$parts = [];
		foreach ( $commands as $command ) {
			$command = trim( (string) $command );
			if ( preg_match( '/^([-0-9.]+)\s+([-0-9.]+)\s+moveto$/', $command, $m ) ) {
				$parts[] = sprintf( 'M%.4F %.4F', (float) $m[1], -1 * (float) $m[2] );
			} elseif ( preg_match( '/^([-0-9.]+)\s+([-0-9.]+)\s+lineto$/', $command, $m ) ) {
				$parts[] = sprintf( 'L%.4F %.4F', (float) $m[1], -1 * (float) $m[2] );
			} elseif ( preg_match( '/^([-0-9.]+)\s+([-0-9.]+)\s+([-0-9.]+)\s+([-0-9.]+)\s+([-0-9.]+)\s+([-0-9.]+)\s+curveto$/', $command, $m ) ) {
				$parts[] = sprintf( 'C%.4F %.4F %.4F %.4F %.4F %.4F', (float) $m[1], -1 * (float) $m[2], (float) $m[3], -1 * (float) $m[4], (float) $m[5], -1 * (float) $m[6] );
			} elseif ( 'closepath' === $command ) {
				$parts[] = 'Z';
			}
		}

		return implode( ' ', $parts );
	}

	private static function mm_to_pt_value( float $mm ): float {
		return $mm * 72 / 25.4;
	}

	private static function pt_to_mm_value( float $pt ): float {
		return $pt * 25.4 / 72;
	}

	private static function render_layer_spotify( \TCPDF $pdf, array $input, float $x_mm, float $y_mm, float $w_mm, float $h_mm, string $mode ): void {
		$spotify_url = self::build_spotify_code_url( (string) ( $input['spotifyUri'] ?? $input['value'] ?? '' ), 'engraving' === $mode );
		if ( '' === $spotify_url ) {
			return;
		}

		$svg_path = self::download_spotify_code_svg( $spotify_url );
		if ( ! $svg_path ) {
			return;
		}

		try {
			$pdf->ImageSVG( $svg_path, $x_mm, $y_mm, $w_mm, $h_mm, '', '', '', 0, false );
		} finally {
			@unlink( $svg_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
	}

	protected static function build_spotify_code_url( string $input_value, bool $engraving = false ): string {
		$spotify_uri = self::extract_spotify_uri( $input_value );
		if ( '' === $spotify_uri ) {
			return '';
		}

		$background_hex = $engraving ? 'ECEFF1' : 'FFFFFF';

		return sprintf(
			'https://scannables.scdn.co/uri/plain/svg/%s/black/640/%s',
			$background_hex,
			$spotify_uri
		);
	}

	protected static function extract_spotify_uri( string $input_value ): string {
		$raw = trim( $input_value );
		if ( '' === $raw ) {
			return '';
		}

		if ( preg_match( '/^spotify:(track|album|artist|playlist|episode|show):([A-Za-z0-9]+)$/i', $raw, $matches ) ) {
			return sprintf( 'spotify:%s:%s', strtolower( $matches[1] ), $matches[2] );
		}

		$parts = parse_url( $raw );
		if ( ! is_array( $parts ) ) {
			return '';
		}

		$host = strtolower( (string) ( $parts['host'] ?? '' ) );
		if ( ! in_array( $host, [ 'open.spotify.com', 'play.spotify.com' ], true ) ) {
			return '';
		}

		$path_parts  = array_values( array_filter( explode( '/', (string) ( $parts['path'] ?? '' ) ) ) );
		$valid_types = [ 'track', 'album', 'artist', 'playlist', 'episode', 'show' ];

		foreach ( $path_parts as $index => $part ) {
			$part = strtolower( $part );
			if ( str_starts_with( $part, 'intl-' ) ) {
				continue;
			}
			if ( ! in_array( $part, $valid_types, true ) || empty( $path_parts[ $index + 1 ] ) ) {
				continue;
			}

			$id = (string) $path_parts[ $index + 1 ];
			if ( preg_match( '/^[A-Za-z0-9]+$/', $id ) ) {
				return sprintf( 'spotify:%s:%s', $part, $id );
			}
		}

		return '';
	}

	private static function download_spotify_code_svg( string $spotify_url ): ?string {
		$response = wp_safe_remote_get( $spotify_url, [
			'timeout'     => 15,
			'redirection' => 2,
		] );

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$body = wp_remote_retrieve_body( $response );
		if ( ! is_string( $body ) || ! str_contains( $body, '<svg' ) ) {
			return null;
		}

		$temp = self::temp_path( 'oc-spotify-code-' . wp_generate_uuid4() . '.svg' );
		if ( ! is_string( $temp ) || '' === $temp ) {
			return null;
		}

		if ( false === file_put_contents( $temp, $body ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			@unlink( $temp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return null;
		}

		return $temp;
	}

	private static function render_layer_image( \TCPDF $pdf, array $layer, array $input, float $x_mm, float $y_mm, float $w_mm, float $h_mm, string $mode = 'colour' ): void {
		$path = self::resolve_artwork_path( $layer );
		if ( ! $path ) {
			return;
		}

		$temp_path = null;
		if ( 'engraving' === $mode && 'clipart' === (string) ( $layer['type'] ?? '' ) ) {
			$temp_path = self::build_black_clipart( $path );
			if ( is_string( $temp_path ) && '' !== $temp_path ) {
				$path = $temp_path;
			}
		} elseif (
			'colour' !== $mode
			&& 'clipart' === (string) ( $layer['type'] ?? '' )
			&& ! empty( $input['clipartRecolourable'] )
		) {
			$hex = sanitize_hex_color( (string) ( $input['colorHex'] ?? '' ) );
			if ( $hex ) {
				$temp_path = self::build_coloured_clipart( $path, $hex );
				if ( is_string( $temp_path ) && '' !== $temp_path ) {
					$path = $temp_path;
				}
			}
		}

		$draw_w = $w_mm;
		$draw_h = $h_mm;
		$draw_x = $x_mm;
		$draw_y = $y_mm;
		$size = @getimagesize( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( is_array( $size ) && ! empty( $size[0] ) && ! empty( $size[1] ) ) {
			$scale = min( $w_mm / (float) $size[0], $h_mm / (float) $size[1] );
			$draw_w = (float) $size[0] * $scale;
			$draw_h = (float) $size[1] * $scale;
			$draw_x = $x_mm + ( $w_mm - $draw_w ) / 2;
			$draw_y = $y_mm + ( $h_mm - $draw_h ) / 2;
		}

		self::draw_pdf_image( $pdf, $path, $draw_x, $draw_y, $draw_w, $draw_h );

		if ( is_string( $temp_path ) && '' !== $temp_path && file_exists( $temp_path ) ) {
			@unlink( $temp_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
	}

	private static function render_layer_clipped_image( \TCPDF $pdf, array $layer, float $x_mm, float $y_mm, float $w_mm, float $h_mm, string $mode = 'colour' ): void {
		$path = self::resolve_artwork_path( $layer );
		if ( ! $path ) {
			return;
		}

		$draw_w = $w_mm;
		$draw_h = $h_mm;
		$draw_x = $x_mm;
		$draw_y = $y_mm;
		$size = @getimagesize( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( is_array( $size ) && ! empty( $size[0] ) && ! empty( $size[1] ) ) {
			$scale  = max( $w_mm / (float) $size[0], $h_mm / (float) $size[1] );
			$draw_w = (float) $size[0] * $scale;
			$draw_h = (float) $size[1] * $scale;
			$draw_x = $x_mm + ( $w_mm - $draw_w ) / 2;
			$draw_y = $y_mm + ( $h_mm - $draw_h ) / 2;
		}

		$settings = is_array( $layer['settings'] ?? null ) ? $layer['settings'] : [];
		$shape    = sanitize_key( (string) ( $settings['mask_shape'] ?? 'circle' ) );

		$pdf->StartTransform();
		if ( 'circle' === $shape ) {
			$radius = min( $w_mm, $h_mm ) / 2;
			$pdf->Circle( $x_mm + $w_mm / 2, $y_mm + $h_mm / 2, $radius, 0, 360, 'CNZ' );
		} else {
			$pdf->Rect( $x_mm, $y_mm, $w_mm, $h_mm, 'CNZ' );
		}
		self::draw_pdf_image( $pdf, $path, $draw_x, $draw_y, $draw_w, $draw_h );
		$pdf->StopTransform();
	}

	private static function build_black_clipart( string $path ): ?string {
		return self::build_coloured_clipart( $path, '#000000' );
	}

	private static function build_coloured_clipart( string $path, string $hex ): ?string {
		$ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		if ( 'svg' === $ext ) {
			return self::build_coloured_svg( $path, $hex );
		}

		return '#000000' === $hex ? self::build_black_raster( $path ) : null;
	}

	private static function build_coloured_svg( string $path, string $hex ): ?string {
		$raw = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( ! is_string( $raw ) || '' === $raw ) {
			return null;
		}

		$dom = new \DOMDocument();
		$previous = libxml_use_internal_errors( true );
		$loaded = $dom->loadXML( $raw, LIBXML_NONET | LIBXML_NOCDATA );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		if ( ! $loaded || ! $dom->documentElement || 'svg' !== strtolower( $dom->documentElement->localName ) ) {
			return null;
		}

		$dom->documentElement->setAttribute( 'color', $hex );
		$dom->documentElement->setAttribute( 'fill', $hex );
		self::force_svg_node_colour( $dom->documentElement, $hex );

		$temp = self::temp_path( 'oc-colour-clipart-' . wp_generate_uuid4() . '.svg' );
		if ( ! is_string( $temp ) || '' === $temp ) {
			return null;
		}

		$output = $dom->saveXML( $dom->documentElement );
		if ( ! is_string( $output ) || false === file_put_contents( $temp, $output ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			@unlink( $temp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return null;
		}

		return $temp;
	}

	private static function force_svg_node_colour( \DOMElement $element, string $hex ): void {
		if ( 'style' === strtolower( $element->localName ) ) {
			$element->nodeValue = self::force_svg_css_colour( $element->nodeValue ?? '', $hex );
			return;
		}

		if ( $element->hasAttribute( 'fill' ) && 'none' !== strtolower( trim( $element->getAttribute( 'fill' ) ) ) ) {
			$element->setAttribute( 'fill', $hex );
		}
		if ( $element->hasAttribute( 'stroke' ) && 'none' !== strtolower( trim( $element->getAttribute( 'stroke' ) ) ) ) {
			$element->setAttribute( 'stroke', $hex );
		}
		if ( $element->hasAttribute( 'style' ) ) {
			$element->setAttribute( 'style', self::force_svg_style_colour( $element->getAttribute( 'style' ), $hex ) );
		}

		foreach ( $element->childNodes as $child ) {
			if ( $child instanceof \DOMElement ) {
				self::force_svg_node_colour( $child, $hex );
			}
		}
	}

	private static function force_svg_style_colour( string $style, string $hex ): string {
		$parts = array_filter( array_map( 'trim', explode( ';', $style ) ) );
		foreach ( $parts as &$part ) {
			if ( preg_match( '/^\s*(fill|stroke)\s*:/i', $part ) && ! preg_match( '/:\s*none\s*$/i', $part ) ) {
				$property = trim( (string) strtok( $part, ':' ) );
				$part = $property . ':' . $hex;
			}
		}

		return implode( ';', $parts );
	}

	private static function force_svg_css_colour( string $css, string $hex ): string {
		return (string) preg_replace_callback(
			'/\b(fill|stroke)\s*:\s*([^;}]+)/i',
			static function ( array $matches ) use ( $hex ): string {
				$value = strtolower( trim( (string) $matches[2] ) );
				return 'none' === $value ? $matches[0] : $matches[1] . ':' . $hex;
			},
			$css
		);
	}

	private static function build_black_raster( string $path ): ?string {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			return null;
		}

		$src = self::open_raster_resource( $path );
		if ( ! $src ) {
			return null;
		}

		$w = imagesx( $src );
		$h = imagesy( $src );
		if ( $w < 1 || $h < 1 ) {
			imagedestroy( $src );
			return null;
		}

		$dst = imagecreatetruecolor( $w, $h );
		imagealphablending( $dst, false );
		imagesavealpha( $dst, true );

		for ( $y = 0; $y < $h; $y++ ) {
			for ( $x = 0; $x < $w; $x++ ) {
				$rgba  = imagecolorat( $src, $x, $y );
				$alpha = ( $rgba >> 24 ) & 0x7F;
				$black = imagecolorallocatealpha( $dst, 0, 0, 0, $alpha );
				imagesetpixel( $dst, $x, $y, $black );
			}
		}
		imagedestroy( $src );

		$temp = self::temp_path( 'oc-black-clipart-' . wp_generate_uuid4() . '.png' );
		if ( ! is_string( $temp ) || '' === $temp ) {
			imagedestroy( $dst );
			return null;
		}

		$result = imagepng( $dst, $temp );
		imagedestroy( $dst );
		if ( ! $result ) {
			@unlink( $temp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return null;
		}

		return $temp;
	}

	private static function open_raster_resource( string $path ) {
		$ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		return match ( $ext ) {
			'jpg', 'jpeg' => @imagecreatefromjpeg( $path ), // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			'png' => @imagecreatefrompng( $path ), // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			'webp' => function_exists( 'imagecreatefromwebp' ) ? @imagecreatefromwebp( $path ) : false, // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			'bmp' => function_exists( 'imagecreatefrombmp' ) ? @imagecreatefrombmp( $path ) : false, // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			'gif' => @imagecreatefromgif( $path ), // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			default => false,
		};
	}

	// -------------------------------------------------------------------------
	// Crop marks
	// -------------------------------------------------------------------------

	/**
	 * Draw crop marks on the current TCPDF page.
	 *
	 * @param \TCPDF $pdf
	 * @param float  $w_mm  Trim width (without bleed).
	 * @param float  $h_mm  Trim height (without bleed).
	 * @param float  $bleed Bleed in mm.
	 */
	protected static function draw_crop_marks( \TCPDF $pdf, float $w_mm, float $h_mm, float $bleed ): void {
		if ( $bleed <= 0 ) {
			return;
		}
		if ( 'none' === OC_Admin_Settings::get( 'crop_mark_style' ) ) {
			return;
		}

		$mark_len  = 5.0;  // Length of crop mark line beyond bleed edge.
		$mark_gap  = 2.0;  // Gap between trim edge and start of crop mark.
		$page_w    = $w_mm + $bleed * 2;
		$page_h    = $h_mm + $bleed * 2;

		$pdf->SetDrawColor( 0, 0, 0 );
		$pdf->SetLineWidth( 0.25 );

		// Trim box corners: TL, TR, BL, BR.
		$corners = [
			[ $bleed, $bleed ],            // TL
			[ $bleed + $w_mm, $bleed ],    // TR
			[ $bleed, $bleed + $h_mm ],    // BL
			[ $bleed + $w_mm, $bleed + $h_mm ], // BR
		];

		foreach ( $corners as [ $cx, $cy ] ) {
			// Horizontal line.
			$dir_x = $cx <= $bleed ? -1 : 1;
			$pdf->Line(
				$cx + $dir_x * $mark_gap,
				$cy,
				$cx + $dir_x * ( $mark_gap + $mark_len ),
				$cy
			);
			// Vertical line.
			$dir_y = $cy <= $bleed ? -1 : 1;
			$pdf->Line(
				$cx,
				$cy + $dir_y * $mark_gap,
				$cx,
				$cy + $dir_y * ( $mark_gap + $mark_len )
			);
		}
	}
}
