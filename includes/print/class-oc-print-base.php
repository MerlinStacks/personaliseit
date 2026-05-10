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

	/** Subdirectory within wp-content/uploads for generated print files. */
	protected const PRINT_SUBDIR = 'overcustomise/print-files';

	// -------------------------------------------------------------------------
	// Dimension helpers
	// -------------------------------------------------------------------------

	/** Convert canvas pixels to millimetres at CANVAS_DPI. */
	protected static function px_to_mm( int $pixels ): float {
		return round( $pixels * 25.4 / self::CANVAS_DPI, 3 );
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
		
		return file_exists( $real ) ? $real : null;
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
		try {
			$name = \TCPDF_FONTS::addTTFfont( $font_path, 'TrueTypeUnicode', '', 96 );
			return is_string( $name ) ? $name : '';
		} catch ( \Throwable $e ) {
			OC_Logger::warning( 'TCPDF font registration failed: ' . $e->getMessage() );
			return '';
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
		if ( class_exists( '\\TCPDF' ) ) {
			return;
		}

		$autoloader = OC_PATH . 'vendor/autoload.php';
		if ( ! file_exists( $autoloader ) ) {
			throw new \RuntimeException( 'TCPDF not available — run `composer install` in the plugin directory.' );
		}

		// Point TCPDF's font cache to the WP uploads directory so it is always
		// writable, regardless of the server's PHP configuration.
		if ( ! defined( 'K_PATH_CACHE' ) ) {
			$cache_dir = wp_upload_dir()['basedir'] . '/overcustomise/tcpdf-cache/';
			wp_mkdir_p( $cache_dir );
			define( 'K_PATH_CACHE', $cache_dir );
		}

		require_once $autoloader;
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
	protected static function resolve_font( int $font_id ): string {
		if ( $font_id ) {
			$font = self::get_font( $font_id );
			if ( $font ) {
				$path = self::get_font_path( $font );
				if ( $path ) {
					$name = self::register_tcpdf_font( $path );
					if ( $name ) {
						return $name;
					}
				}
			}
		}
		return 'helvetica';
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
	protected static function auto_font_size( \TCPDF $pdf, string $text, string $font_name, float $w_mm, float $h_mm ): float {
		$size = max( 8.0, $h_mm * 0.4 );

		while ( $size > 4.0 ) {
			$pdf->SetFont( $font_name, '', $size );
			if ( $pdf->GetStringWidth( $text ) <= $w_mm * 0.92 ) {
				break;
			}
			$size -= 0.5;
		}

		return $size;
	}

	/**
	 * Convert font size in points to an appropriate cell height in mm.
	 * Uses 1.2× line height.
	 */
	protected static function cell_h( float $font_size_pt ): float {
		return $font_size_pt * 0.3528 * 1.2; // 1pt = 0.3528mm
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
			$attachment_path = get_attached_file( (int) $area_data['artworkAttachmentId'] );
			if ( $attachment_path && file_exists( $attachment_path ) ) {
				return $attachment_path;
			}
		}

		if ( ! empty( $area_data['artworkPath'] ) && is_string( $area_data['artworkPath'] ) ) {
			$candidate = $area_data['artworkPath'];
			$real      = realpath( $candidate );
			$uploads   = wp_upload_dir();
			$base_real = ! empty( $uploads['basedir'] ) ? realpath( $uploads['basedir'] ) : false;
			if ( $real && $base_real && 0 === strpos( $real, $base_real ) && file_exists( $real ) ) {
				return $real;
			}
		}

		return null;
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
