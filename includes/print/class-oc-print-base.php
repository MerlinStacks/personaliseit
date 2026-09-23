<?php
/**
 * Abstract base for print file generators.
 *
 * Shared utilities are composed from storage, fonts, images, text, layers,
 * and artwork-effects traits. Constants, dimension and colour helpers,
 * PDF bootstrapping/output, and crop marks remain in this base class.
 *
 * Canvas coordinates use the DPI snapshotted with each print area.
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/trait-oc-print-base-storage.php';
require_once __DIR__ . '/trait-oc-print-base-fonts.php';
require_once __DIR__ . '/trait-oc-print-base-images.php';
require_once __DIR__ . '/trait-oc-print-base-text.php';
require_once __DIR__ . '/trait-oc-print-base-layers.php';
require_once __DIR__ . '/trait-oc-print-base-artwork-effects.php';

abstract class OC_Print_Base {

	use OC_Print_Base_Storage;
	use OC_Print_Base_Fonts;
	use OC_Print_Base_Images;
	use OC_Print_Base_Text;
	use OC_Print_Base_Layers;
	use OC_Print_Base_Artwork_Effects;

	/** Fallback DPI for historical areas that pre-date canvas_dpi snapshots. */
	protected const CANVAS_DPI = 300;

	/** Resource limits for customer-supplied render sources. */
	protected const MAX_RASTER_DIMENSION       = 12000;
	protected const MAX_RASTER_PIXELS          = 40000000;
	protected const MAX_WORK_RASTER_DIMENSION  = 4096;
	protected const MAX_WORK_RASTER_PIXELS     = 16000000;
	protected const MAX_SVG_BYTES              = 5242880;
	protected const MAX_EMBEDDED_RASTER_BYTES  = 2097152;
	protected const MAX_SPOT_MASK_DIMENSION    = 900;
	protected const MAX_SPOT_MASK_RUNS         = 150000;
	protected const MAX_SPOTIFY_RESPONSE_BYTES = 524288;

	/** Engraving print files must output customer text and clipart as black. */
	protected const ENGRAVING_TONE_RGB = [ 0, 0, 0 ];
	/** Fabric.js single-line text metrics used by the customer preview. */
	private const FABRIC_FONT_SIZE_MULTIPLIER = 1.13;
	private const FABRIC_FONT_SIZE_FRACTION   = 0.222;
	private const FABRIC_TEXTBOX_LINE_HEIGHT  = 1.16;
	private const PROTECTION_MARKER_VERSION   = 1;
	private const PROTECTION_MARKER_TTL       = DAY_IN_SECONDS;

	/** Legacy subdirectory, retained for existing absolute file paths only. */
	protected const PRINT_SUBDIR = 'overcustomise/print-files';

	// -------------------------------------------------------------------------
	// Dimension helpers
	// -------------------------------------------------------------------------

	/** Convert canvas pixels to millimetres at the snapshotted canvas DPI. */
	protected static function px_to_mm( float $pixels, int $dpi = self::CANVAS_DPI ): float {
		return round( $pixels * 25.4 / self::normalise_canvas_dpi( $dpi ), 3 );
	}

	/** Convert a stored print-bound value to millimetres using its selected unit. */
	protected static function unit_to_mm( float $value, string $unit, int $dpi = self::CANVAS_DPI ): float {
		switch ( $unit ) {
			case 'mm':
				return round( $value, 3 );
			case 'cm':
				return round( $value * 10, 3 );
			case 'in':
				return round( $value * 25.4, 3 );
			case 'px':
			default:
				return self::px_to_mm( $value, $dpi );
		}
	}

	/** Return the physical print area dimensions in millimetres. */
	protected static function area_dimensions_mm( object $area ): array {
		$unit = isset( $area->canvas_unit ) ? (string) $area->canvas_unit : 'px';
		$dpi  = self::normalise_canvas_dpi( $area->canvas_dpi ?? self::CANVAS_DPI );

		return [
			self::unit_to_mm( (float) ( $area->canvas_w ?? 1 ), $unit, $dpi ),
			self::unit_to_mm( (float) ( $area->canvas_h ?? 1 ), $unit, $dpi ),
		];
	}

	/** Convert canvas pixels to font points at the snapshotted canvas DPI. */
	protected static function px_to_pt( float $pixels, int $dpi = self::CANVAS_DPI ): float {
		return round( $pixels * 72 / self::normalise_canvas_dpi( $dpi ), 3 );
	}

	/** Clamp invalid or hostile DPI snapshots to a practical production range. */
	protected static function normalise_canvas_dpi( mixed $dpi ): int {
		$dpi = is_numeric( $dpi ) ? (int) round( (float) $dpi ) : self::CANVAS_DPI;

		return max( 36, min( 1200, $dpi ) );
	}

	/** Read bleed without treating an explicitly configured zero as missing. */
	protected static function configured_bleed_mm(): float {
		$value = OC_Admin_Settings::get( 'bleed_mm' );

		return is_numeric( $value ) ? max( 0.0, min( 50.0, (float) $value ) ) : 3.0;
	}

	/** Reserve enough page slug for crop marks to remain inside the PDF page. */
	protected static function crop_mark_slug_mm( float $bleed ): float {
		if ( $bleed <= 0.0 || 'none' === OC_Admin_Settings::get( 'crop_mark_style' ) ) {
			return 0.0;
		}

		return 7.5;
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

		$raw = self::outline_pdf_text( $raw );
		self::log_pdf_preflight_warnings( $raw, $output_path );

		if ( false === file_put_contents( $output_path, $raw ) ) {
			throw new \RuntimeException( sprintf( 'Could not write print PDF to %s', $output_path ) );
		}
	}

	/** Convert PDF text to vector paths when Ghostscript is available. */
	protected static function outline_pdf_text( string $raw, ?string $binary = null ): string {
		$binary = null === $binary ? self::detect_ghostscript_binary() : $binary;
		if ( '' === $binary ) {
			OC_Logger::warning( 'Ghostscript is unavailable; the production print PDF will retain its embedded fonts.' );
			return $raw;
		}

		$source = self::temp_path_with_extension( 'oc-print-source', 'pdf' );
		$output = self::temp_path_with_extension( 'oc-print-outlined', 'pdf' );
		if ( ! is_string( $source ) || ! is_string( $output ) ) {
			if ( is_string( $source ) ) {
				@unlink( $source ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- best-effort temporary-file cleanup.
			}
			if ( is_string( $output ) ) {
				@unlink( $output ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- best-effort temporary-file cleanup.
			}
			throw new \RuntimeException( __( 'Could not create temporary files for font outlining.', 'overcustomise' ) );
		}

		try {
			if ( false === file_put_contents( $source, $raw ) ) {
				throw new \RuntimeException( __( 'Could not stage the print PDF for font outlining.', 'overcustomise' ) );
			}
			@unlink( $output ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Ghostscript must create a new output file.
			$result = OC_Command_Runner::run( self::ghostscript_outline_command( $binary, $source, $output ) );
			if ( 0 !== (int) $result['code'] || ! is_file( $output ) ) {
				$message = trim( implode( "\n", (array) $result['output'] ) );
				throw new \RuntimeException( '' !== $message
					? sprintf( __( 'Ghostscript could not outline the print PDF: %s', 'overcustomise' ), $message )
					: __( 'Ghostscript could not outline the print PDF.', 'overcustomise' ) );
			}

			$outlined = file_get_contents( $output );
			if ( ! is_string( $outlined ) || ! str_starts_with( $outlined, '%PDF-' ) ) {
				throw new \RuntimeException( __( 'Font outlining returned an invalid print PDF.', 'overcustomise' ) );
			}

			return $outlined;
		} catch ( \InvalidArgumentException $e ) {
			throw new \RuntimeException( __( 'The font outlining command could not be started.', 'overcustomise' ), 0, $e );
		} finally {
			@unlink( $source ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- best-effort temporary-file cleanup.
			@unlink( $output ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- best-effort temporary-file cleanup.
		}
	}

	/** Build the shell-free Ghostscript command used to replace fonts with paths. */
	protected static function ghostscript_outline_command( string $binary, string $source, string $output ): array {
		return [
			$binary,
			'-dSAFER',
			'-dBATCH',
			'-dNOPAUSE',
			'-dQUIET',
			'-sDEVICE=pdfwrite',
			'-dCompatibilityLevel=1.7',
			'-dAutoRotatePages=/None',
			'-dNoOutputFonts',
			'-sOutputFile=' . $output,
			$source,
		];
	}

	/** Detect the Ghostscript executable used for optional production outlining. */
	private static function detect_ghostscript_binary(): string {
		$status = class_exists( 'OC_System_Status' ) ? OC_System_Status::ghostscript() : [ 'binary' => '' ];

		return is_string( $status['binary'] ?? null ) ? $status['binary'] : '';
	}

	/** Log lightweight generated-PDF checks before the file is written. */
	private static function log_pdf_preflight_warnings( string $raw, string $output_path ): void {
		$mode = self::pdf_conformance_mode();
		if ( '' !== $mode && ! str_contains( $raw, '/GTS_PDFX' ) ) {
			OC_Logger::warning( sprintf( 'Generated print PDF is missing the expected PDF/X output intent (%s): %s', $mode, basename( $output_path ) ) );
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

		if ( ! defined( 'K_ALLOWED_PATHS' ) ) {
			$upload_dir = wp_upload_dir();
			$allowed_paths = [
				$upload_dir['basedir'],
				trailingslashit( $upload_dir['basedir'] ) . 'overcustomise/tcpdf-fonts',
				trailingslashit( $upload_dir['basedir'] ) . 'overcustomise/tcpdf-cache',
			];
			$private_artwork = class_exists( 'OC_Upload_Handler' ) ? OC_Upload_Handler::private_storage_path( 'artwork' ) : null;
			if ( is_string( $private_artwork ) ) {
				$allowed_paths[] = $private_artwork;
			}
			define(
				'K_ALLOWED_PATHS',
				$allowed_paths
			);
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
	 * @param  float  $slug  Non-printing page slug outside the bleed.
	 * @return \TCPDF
	 */
	protected static function make_pdf( float $w_mm, float $h_mm, float $bleed = 0.0, float $slug = 0.0 ): \TCPDF {
		self::require_tcpdf();
		require_once __DIR__ . '/class-oc-print-pdf.php';

		$bleed = max( 0.0, $bleed );
		$slug  = max( 0.0, $slug );
		$inset = $bleed + $slug;
		$page_w = $w_mm + $inset * 2;
		$page_h = $h_mm + $inset * 2;

		$orientation = $page_w > $page_h ? 'L' : 'P';
		$pdf_mode = self::pdf_conformance_mode();
		$pdf = new OC_Print_PDF( $orientation, 'mm', [ $page_w, $page_h ], true, 'UTF-8', false, $pdf_mode );
		$pdf->SetCreator( 'OverCustomise' );
		$pdf->SetAuthor( 'Custom Kings' );
		$pdf->SetSubject( 'Production print artwork' );
		$pdf->SetKeywords( 'print,production,customisation,PDF/X' );
		$pdf->SetMargins( $inset, $inset, $inset );
		$pdf->SetAutoPageBreak( false, 0 );
		$pdf->setPrintHeader( false );
		$pdf->setPrintFooter( false );

		return $pdf;
	}

	/** Return the PDF conformance mode for generated print PDFs. */
	protected static function pdf_conformance_mode(): string {
		$mode = apply_filters( 'oc_print_pdf_conformance_mode', 'pdfx4' );
		$mode = strtolower( trim( is_string( $mode ) ? $mode : '' ) );

		return preg_match( '/^pdfx(?:1a|3|4|5)?$/', $mode ) ? $mode : '';
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
	 * @param float  $slug  Page slug outside the bleed.
	 * @param float  $offset_x Horizontal offset of this area's page slot.
	 * @param float  $offset_y Vertical offset of this area's page slot.
	 */
	protected static function draw_crop_marks( \TCPDF $pdf, float $w_mm, float $h_mm, float $bleed, float $slug = 0.0, float $offset_x = 0.0, float $offset_y = 0.0 ): void {
		if ( $bleed <= 0 ) {
			return;
		}
		if ( 'none' === OC_Admin_Settings::get( 'crop_mark_style' ) ) {
			return;
		}

		$mark_len  = 5.0;  // Length of crop mark line beyond bleed edge.
		$mark_gap  = 2.0;  // Gap between trim edge and start of crop mark.
		$trim_x    = $offset_x + max( 0.0, $slug ) + $bleed;
		$trim_y    = $offset_y + max( 0.0, $slug ) + $bleed;

		$pdf->SetDrawColor( 0, 0, 0 );
		$pdf->SetLineWidth( 0.25 );

		// Trim box corners: TL, TR, BL, BR.
		$corners = [
			[ $trim_x, $trim_y ],            // TL
			[ $trim_x + $w_mm, $trim_y ],    // TR
			[ $trim_x, $trim_y + $h_mm ],    // BL
			[ $trim_x + $w_mm, $trim_y + $h_mm ], // BR
		];

		foreach ( $corners as [ $cx, $cy ] ) {
			// Horizontal line.
			$dir_x = $cx <= $trim_x ? -1 : 1;
			$pdf->Line(
				$cx + $dir_x * $mark_gap,
				$cy,
				$cx + $dir_x * ( $mark_gap + $mark_len ),
				$cy
			);
			// Vertical line.
			$dir_y = $cy <= $trim_y ? -1 : 1;
			$pdf->Line(
				$cx,
				$cy + $dir_y * $mark_gap,
				$cx,
				$cy + $dir_y * ( $mark_gap + $mark_len )
			);
		}
	}
}
