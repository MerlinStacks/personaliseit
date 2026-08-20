<?php
/**
 * Abstract base for print file generators.
 *
 * Shared utilities: dimension conversion, font loading, color conversion,
 * directory management, and TCPDF bootstrapping.
 *
 * Canvas coordinates use the DPI snapshotted with each print area.
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

abstract class OC_Print_Base {

	/** Fallback DPI for historical areas that pre-date canvas_dpi snapshots. */
	protected const CANVAS_DPI = 300;

	/** Resource limits for customer-supplied render sources. */
	protected const MAX_RASTER_DIMENSION = 12000;
	protected const MAX_RASTER_PIXELS = 40000000;
	protected const MAX_WORK_RASTER_DIMENSION = 4096;
	protected const MAX_WORK_RASTER_PIXELS = 16000000;
	protected const MAX_SVG_BYTES = 5242880;
	protected const MAX_SPOT_MASK_DIMENSION = 900;
	protected const MAX_SPOT_MASK_RUNS = 150000;
	protected const MAX_SPOTIFY_RESPONSE_BYTES = 524288;

	/** Engraving print files must output customer text and clipart as black. */
	protected const ENGRAVING_TONE_RGB = [ 0, 0, 0 ];
	/** Fabric.js single-line text metrics used by the customer preview. */
	private const FABRIC_FONT_SIZE_MULTIPLIER = 1.13;
	private const FABRIC_FONT_SIZE_FRACTION = 0.222;

	/** Subdirectory within wp-content/uploads for generated print files. */
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
		$base = $upload_dir['basedir'] . '/' . self::PRINT_SUBDIR;
		if ( ! self::protect_output_root( $base ) ) {
			throw new \RuntimeException( __( 'Could not protect print directory.', 'overcustomise' ) );
		}

		$order_token = substr( hash_hmac( 'sha256', (string) $order_id, wp_salt( 'auth' ) ), 0, 32 );
		$dir         = $base . '/' . $order_token;

		if ( ! wp_mkdir_p( $dir ) ) {
			throw new \RuntimeException( __( 'Could not create print output directory.', 'overcustomise' ) );
		}

		return $dir;
	}

	/** Ensure existing and future print files are denied by Apache and IIS. */
	public static function ensure_output_storage_protected(): void {
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
			return;
		}

		$base = trailingslashit( (string) $uploads['basedir'] ) . self::PRINT_SUBDIR;
		if ( ! self::protect_output_root( $base ) ) {
			OC_Logger::warning( 'Generated print storage could not be protected.' );
		}
	}

	/** Create the print root and write server-specific deny rules. */
	private static function protect_output_root( string $base ): bool {
		if ( ( ! is_dir( $base ) && ! wp_mkdir_p( $base ) ) || ! is_writable( $base ) ) {
			return false;
		}

		$files = [
			'.htaccess' => "Options -Indexes\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n",
			'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?><configuration><system.webServer><security><authorization><remove users=\"*\" roles=\"\" verbs=\"\"/><add accessType=\"Deny\" users=\"*\"/></authorization></security></system.webServer></configuration>\n",
			'index.php' => "<?php\nhttp_response_code( 404 );\nexit;\n",
		];
		foreach ( $files as $filename => $contents ) {
			$path = $base . '/' . $filename;
			if ( ( ! is_file( $path ) || (string) file_get_contents( $path ) !== $contents ) && false === file_put_contents( $path, $contents ) ) {
				return false;
			}
		}

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

	// -------------------------------------------------------------------------
	// Font helpers
	// -------------------------------------------------------------------------

	/** Fetch a retained font DB row by its explicit ID, including inactive rows. */
	protected static function get_font( int $font_id ): ?object {
		if ( ! $font_id ) {
			return null;
		}
		static $fonts_by_site = [];
		$blog_id = function_exists( 'get_current_blog_id' ) ? get_current_blog_id() : 0;
		if ( array_key_exists( $font_id, $fonts_by_site[ $blog_id ] ?? [] ) ) {
			return $fonts_by_site[ $blog_id ][ $font_id ];
		}
		global $wpdb;
		$font = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}oc_fonts WHERE id = %d LIMIT 1",
			$font_id
		) ) ?: null;
		$fonts_by_site[ $blog_id ][ $font_id ] = $font;
		return $font;
	}

	/** Return the absolute path to a font file, or null if not accessible. */
	protected static function get_raw_font_path( object $font ): ?string {
		// Validate font path doesn't contain directory traversal.
		$file_path = ltrim( (string) ( $font->file_path ?? '' ), '/' );
		if ( '' === $file_path || str_contains( $file_path, '..' ) ) {
			return null;
		}
		
		$path = wp_upload_dir()['basedir'] . '/' . $file_path;
		$real = realpath( $path );
		$base = realpath( wp_upload_dir()['basedir'] );
		
		// Ensure the font file is within the uploads directory.
		$base_prefix = $base ? rtrim( $base, '/\\' ) . DIRECTORY_SEPARATOR : '';
		if ( ! $real || '' === $base_prefix || ! str_starts_with( $real, $base_prefix ) || ! is_file( $real ) ) {
			return null;
		}

		return $real;
	}

	/** Return the absolute path to a print-compatible TrueType-outline font file, or null. */
	protected static function get_font_path( object $font ): ?string {
		$real = self::get_raw_font_path( $font );
		if ( ! $real ) {
			return null;
		}

		if ( 'otf' === strtolower( pathinfo( $real, PATHINFO_EXTENSION ) ) && self::is_cff_opentype( $real ) ) {
			$print_font = self::get_print_companion_font_path( $real );
			if ( ! $print_font ) {
				$print_font = self::get_print_variant_font_path( $font );
			}
			if ( $print_font ) {
				return $print_font;
			}

			OC_Logger::warning( 'Print font fallback: ' . basename( $real ) . ' is an OpenType/CFF font. TCPDF needs a TrueType-outline TTF/OTF file.' );
			return null;
		}

		if ( 'woff' === strtolower( pathinfo( $real, PATHINFO_EXTENSION ) ) && class_exists( 'OC_WOFF_Converter' ) ) {
			if ( self::is_cff_woff( $real ) ) {
				$print_font = self::get_print_companion_font_path( $real );
				if ( ! $print_font ) {
					$print_font = self::get_print_variant_font_path( $font );
				}
				if ( $print_font ) {
					return $print_font;
				}

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

	/** Return a browser-converted print TTF companion for CFF/WOFF fonts, if one exists. */
	protected static function get_print_companion_font_path( string $source_path ): ?string {
		$dir  = dirname( $source_path );
		$base = pathinfo( $source_path, PATHINFO_FILENAME );
		if ( '' === $base || ! is_dir( $dir ) ) {
			return null;
		}

		$candidates = [ $dir . '/' . $base . '-print.ttf' ];
		$matches    = glob( $dir . '/' . $base . '-print*.ttf' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_glob
		if ( is_array( $matches ) ) {
			$candidates = array_merge( $candidates, $matches );
		}

		foreach ( array_unique( $candidates ) as $candidate ) {
			$real = realpath( $candidate );
			if ( $real && is_readable( $real ) && self::is_truetype_outline_font( $real ) ) {
				return $real;
			}
		}

		return null;
	}

	/** Find another retained family row that already points to a print-safe TTF. */
	protected static function get_print_variant_font_path( object $font ): ?string {
		$name   = trim( (string) ( $font->name ?? '' ) );
		$weight = trim( (string) ( $font->weight ?? 'normal' ) );
		$style  = trim( (string) ( $font->style ?? 'normal' ) );
		$id     = (int) ( $font->id ?? 0 );
		if ( '' === $name || $id <= 0 ) {
			return null;
		}

		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}oc_fonts
			 WHERE name = %s AND weight = %s AND style = %s AND id <> %d
			 ORDER BY active DESC, id DESC",
			$name,
			$weight,
			$style,
			$id
		) );
		if ( ! is_array( $rows ) ) {
			return null;
		}

		foreach ( $rows as $row ) {
			$path = self::get_raw_font_path( $row );
			if ( is_string( $path ) && '' !== $path && self::is_truetype_outline_font( $path ) ) {
				return $path;
			}
		}

		return null;
	}

	protected static function is_truetype_outline_font( string $path ): bool {
		$handle = fopen( $path, 'rb' );
		if ( ! $handle ) {
			return false;
		}
		$signature = fread( $handle, 4 );
		fclose( $handle );

		return in_array( $signature, [ "\x00\x01\x00\x00", 'true' ], true );
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

		try {
			$upload_dir = wp_upload_dir();
			$font_dir   = trailingslashit( $upload_dir['basedir'] ) . 'overcustomise/tcpdf-fonts/';
			wp_mkdir_p( $font_dir );

			if ( class_exists( '\TCPDF_FONTS' ) ) {
				$name = \TCPDF_FONTS::addTTFfont( $font_path, 'TrueTypeUnicode', '', 96, $font_dir );
				return is_string( $name ) ? $name : '';
			}

			if ( class_exists( '\Com\Tecnick\Pdf\Font\Import' ) ) {
				return self::register_tc_lib_pdf_font( $font_path, $font_dir );
			}

			OC_Logger::warning( 'TCPDF font registration skipped for ' . basename( $font_path ) . ': no compatible TCPDF font importer is available.' );
			return '';
		} catch ( \Throwable $e ) {
			OC_Logger::warning( 'TCPDF font registration failed for ' . basename( $font_path ) . ': ' . $e->getMessage() );
			return '';
		}
	}

	/** Register a font using the TCPDF v7 tc-lib-pdf-font importer. */
	protected static function register_tc_lib_pdf_font( string $font_path, string $font_dir ): string {
		$font_dir  = trailingslashit( $font_dir );
		$font_name = self::tc_lib_pdf_font_name( $font_path );
		if ( '' !== $font_name && file_exists( $font_dir . $font_name . '.json' ) ) {
			return $font_name;
		}

		try {
			$import = new \Com\Tecnick\Pdf\Font\Import(
				$font_path,
				$font_dir,
				'TrueTypeUnicode',
				'',
				32,
				3,
				1,
				false
			);

			return $import->getFontName();
		} catch ( \Throwable $e ) {
			if ( preg_match( '/([a-z0-9_\-]+)\.json$/i', $e->getMessage(), $match ) ) {
				return strtolower( (string) $match[1] );
			}

			throw $e;
		}
	}

	/** Match tc-lib-pdf-font's generated family name for cache lookups. */
	protected static function tc_lib_pdf_font_name( string $font_path ): string {
		$name = strtolower( (string) pathinfo( $font_path, PATHINFO_FILENAME ) );
		$name = preg_replace( '/[^a-z0-9_]/', '', $name );
		if ( ! is_string( $name ) || '' === $name ) {
			return '';
		}

		return str_replace( [ 'bold', 'oblique', 'italic', 'regular' ], [ 'b', 'i', 'i', '' ], $name );
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

	/** Embed an image in TCPDF, converting artwork to a safe PNG when needed. */
	protected static function draw_pdf_image( \TCPDF $pdf, string $path, float $x_mm, float $y_mm, float $w_mm, float $h_mm ): void {
		if ( ! is_readable( $path ) ) {
			throw new \RuntimeException( sprintf( __( 'Production artwork is not readable: %s', 'overcustomise' ), basename( $path ) ) );
		}
		if ( $w_mm <= 0.0 || $h_mm <= 0.0 ) {
			return;
		}

		if ( 'svg' === strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) ) {
			self::draw_pdf_svg( $pdf, $path, $x_mm, $y_mm, $w_mm, $h_mm );
			return;
		}
		self::assert_safe_raster_dimensions( $path );

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

	/** Embed SVG artwork as vector first, with a print-resolution raster fallback for unsupported SVGs. */
	private static function draw_pdf_svg( \TCPDF $pdf, string $path, float $x_mm, float $y_mm, float $w_mm, float $h_mm ): void {
		$vector_path = self::normalise_svg_intrinsic_size_for_tcpdf( $path );
		$svg_path    = is_string( $vector_path ) && '' !== $vector_path ? $vector_path : $path;
		$fallback_path = null;

		try {
			$pdf->ImageSVG( $svg_path, $x_mm, $y_mm, $w_mm, $h_mm, '', '', '', 0, false );
		} catch ( \Throwable $e ) {
			$fallback_path = self::normalise_svg_for_tcpdf( $path, $w_mm, $h_mm );
			if ( ! is_string( $fallback_path ) || '' === $fallback_path ) {
				throw $e;
			}

			try {
				OC_Logger::warning( 'TCPDF could not render SVG artwork directly, retrying print-resolution PNG: ' . basename( $path ) . ' (' . $e->getMessage() . ')' );
				$pdf->Image( $fallback_path, $x_mm, $y_mm, $w_mm, $h_mm, '', '', '', false, 600 );
			} finally {
				@unlink( $fallback_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
		} finally {
			if ( is_string( $vector_path ) && '' !== $vector_path && file_exists( $vector_path ) ) {
				@unlink( $vector_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
		}
	}

	/** Add explicit SVG width/height from viewBox so TCPDF can keep vector artwork. */
	private static function normalise_svg_intrinsic_size_for_tcpdf( string $path ): ?string {
		if ( ! class_exists( '\DOMDocument' ) || ! is_readable( $path ) ) {
			return null;
		}
		if ( filesize( $path ) > self::MAX_SVG_BYTES ) {
			throw new \RuntimeException( __( 'SVG artwork exceeds the safe production size limit.', 'overcustomise' ) );
		}

		$data = file_get_contents( $path );
		$has_positive_intrinsic_size = is_string( $data ) && self::tcpdf_svg_markup_has_positive_intrinsic_size( $data );

		$dom = new \DOMDocument();
		$previous = libxml_use_internal_errors( true );
		$loaded = $dom->load( $path, LIBXML_NONET );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		if ( ! $loaded || ! $dom->documentElement instanceof \DOMElement || 'svg' !== strtolower( $dom->documentElement->localName ) ) {
			return null;
		}

		$svg = $dom->documentElement;
		$width  = self::tcpdf_svg_length_is_positive( $svg->getAttribute( 'width' ) ) ? (float) $svg->getAttribute( 'width' ) : 0.0;
		$height = self::tcpdf_svg_length_is_positive( $svg->getAttribute( 'height' ) ) ? (float) $svg->getAttribute( 'height' ) : 0.0;

		$changed = false;
		if ( $width <= 0.0 || $height <= 0.0 ) {
			$view_box = preg_split( '/[\s,]+/', trim( $svg->getAttribute( 'viewBox' ) ) );
			if ( ! is_array( $view_box ) || count( $view_box ) < 4 ) {
				return null;
			}

			$width  = (float) $view_box[2];
			$height = (float) $view_box[3];
		}

		if ( $width <= 0.0 || $height <= 0.0 ) {
			return null;
		}

		if ( ! $has_positive_intrinsic_size ) {
			$svg->setAttribute( 'width', sprintf( '%.4F', $width ) );
			$svg->setAttribute( 'height', sprintf( '%.4F', $height ) );
			$changed = true;
		}

		$changed = self::normalise_svg_paths_for_tcpdf( $svg ) || $changed;
		$changed = self::inline_svg_presentation_styles( $dom, $svg ) || $changed;
		if ( ! $changed ) {
			return null;
		}

		$temp = self::temp_path_with_extension( 'oc-tcpdf-vector-svg-' . wp_generate_uuid4() . '.svg', 'svg' );
		if ( ! is_string( $temp ) || '' === $temp ) {
			return null;
		}

		if ( false === $dom->save( $temp ) ) {
			@unlink( $temp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return null;
		}

		return $temp;
	}

	/** Serialize compact path data and make closures explicit for TCPDF. */
	private static function normalise_svg_paths_for_tcpdf( \DOMElement $svg ): bool {
		$changed = false;
		foreach ( $svg->getElementsByTagName( 'path' ) as $path ) {
			$data = $path->getAttribute( 'd' );
			if ( '' === $data ) {
				continue;
			}

			$normalised = self::normalise_svg_path_data_for_tcpdf( $data );
			if ( null === $normalised ) {
				continue;
			}

			if ( $normalised !== $data ) {
				$path->setAttribute( 'd', $normalised );
				$changed = true;
			}
		}

		return $changed;
	}

	/** Convert SVG path geometry to absolute commands that TCPDF handles reliably. */
	private static function normalise_svg_path_data_for_tcpdf( string $data ): ?string {
		$pattern = '/[AaCcHhLlMmQqSsTtVvZz]|[+-]?(?:\d+\.\d*|\.\d+|\d+)(?:[eE][+-]?\d+)?/';
		preg_match_all( $pattern, $data, $matches );
		$tokens  = $matches[0];
		$residue = preg_replace( $pattern, '', $data ) ?? $data;
		if ( empty( $tokens ) || count( $tokens ) > 200000 || preg_match( '/[^\s,]/', $residue ) || ! in_array( $tokens[0], [ 'M', 'm' ], true ) ) {
			return null;
		}
		foreach ( $tokens as $token ) {
			if ( ! self::svg_path_token_is_command( $token ) && ! is_finite( (float) $token ) ) {
				return null;
			}
		}

		$out = [];
		$i = 0;
		$command = '';
		$x = 0.0;
		$y = 0.0;
		$start_x = 0.0;
		$start_y = 0.0;
		$cubic_x = null;
		$cubic_y = null;
		$quad_x = null;
		$quad_y = null;
		while ( $i < count( $tokens ) ) {
			$explicit = false;
			if ( self::svg_path_token_is_command( $tokens[ $i ] ) ) {
				$command = $tokens[ $i++ ];
				$explicit = true;
			}
			if ( '' === $command ) {
				return null;
			}

			$relative = ctype_lower( $command );
			$type     = strtoupper( $command );
			$before   = $i;
			switch ( $type ) {
				case 'M':
					$first = true;
					while ( self::svg_path_has_numbers( $tokens, $i, 2 ) ) {
						$nx = (float) $tokens[ $i++ ];
						$ny = (float) $tokens[ $i++ ];
						$x = $relative ? $x + $nx : $nx;
						$y = $relative ? $y + $ny : $ny;
						$out[] = ( $first ? 'M ' : 'L ' ) . self::normalise_svg_path_point( $x, $y );
						if ( $first ) {
							$start_x = $x;
							$start_y = $y;
							$first = false;
						}
					}
					$command = $relative ? 'l' : 'L';
					$cubic_x = $cubic_y = $quad_x = $quad_y = null;
					break;
				case 'L':
					while ( self::svg_path_has_numbers( $tokens, $i, 2 ) ) {
						$nx = (float) $tokens[ $i++ ];
						$ny = (float) $tokens[ $i++ ];
						$x = $relative ? $x + $nx : $nx;
						$y = $relative ? $y + $ny : $ny;
						$out[] = 'L ' . self::normalise_svg_path_point( $x, $y );
					}
					$cubic_x = $cubic_y = $quad_x = $quad_y = null;
					break;
				case 'H':
					while ( self::svg_path_has_numbers( $tokens, $i, 1 ) ) {
						$nx = (float) $tokens[ $i++ ];
						$x = $relative ? $x + $nx : $nx;
						$out[] = 'L ' . self::normalise_svg_path_point( $x, $y );
					}
					$cubic_x = $cubic_y = $quad_x = $quad_y = null;
					break;
				case 'V':
					while ( self::svg_path_has_numbers( $tokens, $i, 1 ) ) {
						$ny = (float) $tokens[ $i++ ];
						$y = $relative ? $y + $ny : $ny;
						$out[] = 'L ' . self::normalise_svg_path_point( $x, $y );
					}
					$cubic_x = $cubic_y = $quad_x = $quad_y = null;
					break;
				case 'C':
					while ( self::svg_path_has_numbers( $tokens, $i, 6 ) ) {
						$values = array_map( 'floatval', array_slice( $tokens, $i, 6 ) );
						$i += 6;
						[ $x1, $y1, $x2, $y2, $ex, $ey ] = $values;
						if ( $relative ) {
							$x1 += $x; $y1 += $y; $x2 += $x; $y2 += $y; $ex += $x; $ey += $y;
						}
						$out[] = 'C ' . self::normalise_svg_path_point( $x1, $y1 ) . ' ' . self::normalise_svg_path_point( $x2, $y2 ) . ' ' . self::normalise_svg_path_point( $ex, $ey );
						$x = $ex; $y = $ey; $cubic_x = $x2; $cubic_y = $y2; $quad_x = $quad_y = null;
					}
					break;
				case 'S':
					while ( self::svg_path_has_numbers( $tokens, $i, 4 ) ) {
						$values = array_map( 'floatval', array_slice( $tokens, $i, 4 ) );
						$i += 4;
						[ $x2, $y2, $ex, $ey ] = $values;
						$x1 = null !== $cubic_x ? 2 * $x - $cubic_x : $x;
						$y1 = null !== $cubic_y ? 2 * $y - $cubic_y : $y;
						if ( $relative ) {
							$x2 += $x; $y2 += $y; $ex += $x; $ey += $y;
						}
						$out[] = 'C ' . self::normalise_svg_path_point( $x1, $y1 ) . ' ' . self::normalise_svg_path_point( $x2, $y2 ) . ' ' . self::normalise_svg_path_point( $ex, $ey );
						$x = $ex; $y = $ey; $cubic_x = $x2; $cubic_y = $y2; $quad_x = $quad_y = null;
					}
					break;
				case 'Q':
				case 'T':
					$parameter_count = 'Q' === $type ? 4 : 2;
					while ( self::svg_path_has_numbers( $tokens, $i, $parameter_count ) ) {
						if ( 'Q' === $type ) {
							$qx = (float) $tokens[ $i++ ]; $qy = (float) $tokens[ $i++ ];
							if ( $relative ) { $qx += $x; $qy += $y; }
						} else {
							$qx = null !== $quad_x ? 2 * $x - $quad_x : $x;
							$qy = null !== $quad_y ? 2 * $y - $quad_y : $y;
						}
						$ex = (float) $tokens[ $i++ ]; $ey = (float) $tokens[ $i++ ];
						if ( $relative ) { $ex += $x; $ey += $y; }
						$c1x = $x + 2 / 3 * ( $qx - $x ); $c1y = $y + 2 / 3 * ( $qy - $y );
						$c2x = $ex + 2 / 3 * ( $qx - $ex ); $c2y = $ey + 2 / 3 * ( $qy - $ey );
						$out[] = 'C ' . self::normalise_svg_path_point( $c1x, $c1y ) . ' ' . self::normalise_svg_path_point( $c2x, $c2y ) . ' ' . self::normalise_svg_path_point( $ex, $ey );
						$x = $ex; $y = $ey; $quad_x = $qx; $quad_y = $qy; $cubic_x = $cubic_y = null;
					}
					break;
				case 'A':
					while ( self::svg_path_has_numbers( $tokens, $i, 7 ) ) {
						$values = array_map( 'floatval', array_slice( $tokens, $i, 7 ) );
						$i += 7;
						[ $rx, $ry, $rotation, $large, $sweep, $ex, $ey ] = $values;
						if ( ! in_array( $large, [ 0.0, 1.0 ], true ) || ! in_array( $sweep, [ 0.0, 1.0 ], true ) ) { return null; }
						if ( $relative ) { $ex += $x; $ey += $y; }
						$out[] = 'A ' . self::normalise_svg_path_number( abs( $rx ) ) . ' ' . self::normalise_svg_path_number( abs( $ry ) ) . ' ' . self::normalise_svg_path_number( $rotation ) . ' ' . (int) $large . ' ' . (int) $sweep . ' ' . self::normalise_svg_path_point( $ex, $ey );
						$x = $ex; $y = $ey; $cubic_x = $cubic_y = $quad_x = $quad_y = null;
					}
					break;
				case 'Z':
					if ( ! $explicit ) { return null; }
					$out[] = 'Z';
					$x = $start_x; $y = $start_y; $command = '';
					$cubic_x = $cubic_y = $quad_x = $quad_y = null;
					break;
				default:
					return null;
			}
			if ( 'Z' !== $type && $i === $before ) {
				return null;
			}
		}

		return implode( ' ', $out );
	}

	private static function svg_path_token_is_command( string $token ): bool {
		return 1 === strlen( $token ) && ctype_alpha( $token );
	}

	private static function svg_path_has_numbers( array $tokens, int $offset, int $count ): bool {
		if ( $offset + $count > count( $tokens ) ) {
			return false;
		}
		for ( $index = 0; $index < $count; $index++ ) {
			if ( self::svg_path_token_is_command( (string) $tokens[ $offset + $index ] ) ) {
				return false;
			}
		}

		return true;
	}

	private static function normalise_svg_path_point( float $x, float $y ): string {
		return self::normalise_svg_path_number( $x ) . ' ' . self::normalise_svg_path_number( $y );
	}

	/** Return a plain decimal that TCPDF's path-number parser accepts. */
	private static function normalise_svg_path_number( string|float $number ): string {
		$normalised = rtrim( rtrim( sprintf( '%.12F', (float) $number ), '0' ), '.' );

		return '-0' === $normalised || '' === $normalised ? '0' : $normalised;
	}

	/** Inline simple SVG CSS presentation styles because TCPDF does not apply them reliably. */
	private static function inline_svg_presentation_styles( \DOMDocument $dom, \DOMElement $svg ): bool {
		$changed = false;
		$xpath   = new \DOMXPath( $dom );
		$styles  = [];
		foreach ( $svg->getElementsByTagName( 'style' ) as $style ) {
			$styles[] = $style;
		}

		foreach ( $styles as $style ) {
			$css = preg_replace( '/\/\*[\s\S]*?\*\//', '', (string) $style->textContent ) ?? '';
			if ( preg_match_all( '/([^{}@]+)\{([^{}]+)\}/', $css, $rules, PREG_SET_ORDER ) ) {
				foreach ( $rules as $rule ) {
					$declarations = self::svg_presentation_declarations( (string) $rule[2] );
					if ( empty( $declarations ) ) {
						continue;
					}

					foreach ( explode( ',', (string) $rule[1] ) as $selector ) {
						$query = self::svg_css_selector_xpath( trim( $selector ) );
						if ( '' === $query ) {
							continue;
						}

						$nodes = $xpath->query( $query, $svg );
						if ( ! $nodes instanceof \DOMNodeList ) {
							continue;
						}

						foreach ( $nodes as $node ) {
							if ( ! $node instanceof \DOMElement ) {
								continue;
							}
							foreach ( $declarations as $attribute => $value ) {
								$node->setAttribute( $attribute, $value );
								$changed = true;
							}
						}
					}
				}
			}

			// Keep original cleaned CSS as a fallback for selectors we do not inline.
		}

		foreach ( $xpath->query( './/*[@style]', $svg ) ?: [] as $node ) {
			if ( ! $node instanceof \DOMElement ) {
				continue;
			}
			foreach ( self::svg_presentation_declarations( $node->getAttribute( 'style' ) ) as $attribute => $value ) {
				$node->setAttribute( $attribute, $value );
				$changed = true;
			}
		}

		return $changed;
	}

	/** @return array<string,string> */
	private static function svg_presentation_declarations( string $css ): array {
		$allowed = [ 'fill', 'stroke', 'opacity', 'fill-opacity', 'stroke-opacity', 'stroke-width', 'stroke-linecap', 'stroke-linejoin', 'fill-rule', 'clip-rule' ];
		$declarations = [];
		foreach ( explode( ';', $css ) as $declaration ) {
			$parts = explode( ':', $declaration, 2 );
			if ( count( $parts ) !== 2 ) {
				continue;
			}

			$property = strtolower( trim( $parts[0] ) );
			$value    = preg_replace( '/\s*!important$/i', '', trim( $parts[1] ) ) ?? '';
			if ( in_array( $property, $allowed, true ) && '' !== $value ) {
				$declarations[ $property ] = $value;
			}
		}

		return $declarations;
	}

	private static function svg_css_selector_xpath( string $selector ): string {
		if ( preg_match( '/^\.([A-Za-z_][A-Za-z0-9_-]*)$/', $selector, $match ) ) {
			$class = self::xpath_literal( ' ' . $match[1] . ' ' );
			return './/*[contains(concat(" ", normalize-space(@class), " "), ' . $class . ')]';
		}

		if ( preg_match( '/^#([A-Za-z_][A-Za-z0-9_-]*)$/', $selector, $match ) ) {
			return './/*[@id=' . self::xpath_literal( $match[1] ) . ']';
		}

		if ( preg_match( '/^([A-Za-z][A-Za-z0-9_-]*)\.([A-Za-z_][A-Za-z0-9_-]*)$/', $selector, $match ) ) {
			$class = self::xpath_literal( ' ' . $match[2] . ' ' );
			return './/*[local-name()=' . self::xpath_literal( $match[1] ) . ' and contains(concat(" ", normalize-space(@class), " "), ' . $class . ')]';
		}

		if ( preg_match( '/^[A-Za-z][A-Za-z0-9_-]*$/', $selector ) ) {
			return './/*[local-name()=' . self::xpath_literal( $selector ) . ']';
		}

		return '';
	}

	private static function xpath_literal( string $value ): string {
		if ( ! str_contains( $value, "'" ) ) {
			return "'" . $value . "'";
		}

		if ( ! str_contains( $value, '"' ) ) {
			return '"' . $value . '"';
		}

		$parts = array_map(
			static fn( string $part ): string => "'" . $part . "'",
			explode( "'", $value )
		);

		return 'concat(' . implode( ', "\'", ', $parts ) . ')';
	}

	/** Check whether the raw SVG root already matches TCPDF's strict double-quoted size parser. */
	private static function tcpdf_svg_markup_has_positive_intrinsic_size( string $data ): bool {
		$matches = [];
		if ( ! preg_match( '/<svg([^>]*)>/si', $data, $matches ) || empty( $matches[1] ) ) {
			return false;
		}

		$attrs = $matches[1];
		$width = [];
		$height = [];
		return preg_match( '/[\s]+width[\s]*=[\s]*"([^"]*)"/si', $attrs, $width )
			&& preg_match( '/[\s]+height[\s]*=[\s]*"([^"]*)"/si', $attrs, $height )
			&& self::tcpdf_svg_length_is_positive( $width[1] )
			&& self::tcpdf_svg_length_is_positive( $height[1] );
	}

	/** Check whether TCPDF's SVG size regex/unit parser will see a positive intrinsic size. */
	private static function tcpdf_svg_length_is_positive( string $value ): bool {
		$value = trim( $value );
		if ( '' === $value || str_contains( $value, '%' ) ) {
			return false;
		}

		return preg_match( '/^[+]?(?:\d+\.?\d*|\.\d+)(?:px|pt|pc|mm|cm|in)?$/i', $value ) && (float) $value > 0.0;
	}

	/** Convert SVG artwork to a transparent PNG at the final print size. */
	private static function normalise_svg_for_tcpdf( string $path, float $w_mm = 0.0, float $h_mm = 0.0 ): ?string {
		if ( ! class_exists( '\Imagick' ) ) {
			return null;
		}

		$temp = self::temp_path_with_extension( 'oc-tcpdf-svg-' . wp_generate_uuid4() . '.png', 'png' );
		if ( ! is_string( $temp ) || '' === $temp ) {
			return null;
		}

		if ( self::convert_svg_with_imagick( $path, $temp, $w_mm, $h_mm ) ) {
			return $temp;
		}

		@unlink( $temp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		return null;
	}

	/** Rasterise an SVG with enough pixels for the placed PDF dimensions. */
	private static function convert_svg_with_imagick( string $path, string $output_path, float $w_mm, float $h_mm ): bool {
		if ( ! class_exists( '\Imagick' ) ) {
			return false;
		}

		$dpi = 600;
		[ $width_px, $height_px ] = self::bounded_work_dimensions(
			max( 1, (int) ceil( max( 0.1, $w_mm ) / 25.4 * $dpi ) ),
			max( 1, (int) ceil( max( 0.1, $h_mm ) / 25.4 * $dpi ) )
		);

		try {
			$imagick = new \Imagick();
			self::configure_imagick_limits( $imagick );
			$imagick->setResolution( $dpi, $dpi );
			$imagick->setBackgroundColor( new \ImagickPixel( 'transparent' ) );
			$imagick->readImage( $path );
			$imagick->setImageAlphaChannel( \Imagick::ALPHACHANNEL_ACTIVATE );
			$imagick->setImageFormat( 'png32' );
			$imagick->resizeImage( $width_px, $height_px, \Imagick::FILTER_LANCZOS, 1, false );
			$result = $imagick->writeImage( $output_path );
			$imagick->clear();
			$imagick->destroy();
			return (bool) $result;
		} catch ( \Throwable $e ) {
			OC_Logger::warning( 'SVG to print-resolution PNG conversion failed for print artwork: ' . $e->getMessage() );
			return false;
		}
	}

	/** TCPDF does not reliably import WEBP, so create a temporary PNG copy first. */
	private static function tcpdf_compatible_image_path( string $path, ?string &$temp_path ): string {
		$temp_path = null;
		if ( 'webp' !== strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) ) {
			return $path;
		}

		$temp = self::temp_path_with_extension( 'oc-tcpdf-webp-' . wp_generate_uuid4() . '.png', 'png' );
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

		$temp = self::temp_path_with_extension( 'oc-tcpdf-raster-' . wp_generate_uuid4() . '.png', 'png' );
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
			$imagick = new \Imagick();
			self::configure_imagick_limits( $imagick );
			$imagick->readImage( $path );
			[ $width, $height ] = self::bounded_work_dimensions( $imagick->getImageWidth(), $imagick->getImageHeight() );
			if ( $width !== $imagick->getImageWidth() || $height !== $imagick->getImageHeight() ) {
				$imagick->resizeImage( $width, $height, \Imagick::FILTER_LANCZOS, 1, true );
			}
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

	/** Apply bounded ImageMagick memory/map/disk limits before reading customer input. */
	protected static function configure_imagick_limits( \Imagick $imagick ): void {
		$imagick->setResourceLimit( \Imagick::RESOURCETYPE_MEMORY, 256 * 1024 * 1024 );
		$imagick->setResourceLimit( \Imagick::RESOURCETYPE_MAP, 256 * 1024 * 1024 );
		$imagick->setResourceLimit( \Imagick::RESOURCETYPE_DISK, 512 * 1024 * 1024 );
		if ( defined( '\\Imagick::RESOURCETYPE_AREA' ) ) {
			$imagick->setResourceLimit( \Imagick::RESOURCETYPE_AREA, self::MAX_RASTER_PIXELS );
		}
	}

	/** Scale dimensions to the bounded working raster envelope without distortion. */
	protected static function bounded_work_dimensions( int $width, int $height, int $max_dimension = self::MAX_WORK_RASTER_DIMENSION, int $max_pixels = self::MAX_WORK_RASTER_PIXELS ): array {
		$width  = max( 1, $width );
		$height = max( 1, $height );
		$scale  = min(
			1.0,
			$max_dimension / max( $width, $height ),
			sqrt( $max_pixels / max( 1, $width * $height ) )
		);

		return [
			max( 1, (int) floor( $width * $scale ) ),
			max( 1, (int) floor( $height * $scale ) ),
		];
	}

	/** Reject raster headers that exceed the upload/production resource envelope. */
	protected static function assert_safe_raster_dimensions( string $path ): array {
		$size = @getimagesize( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! is_array( $size ) || empty( $size[0] ) || empty( $size[1] ) ) {
			throw new \RuntimeException( sprintf( __( 'Production artwork is not a supported raster image: %s', 'overcustomise' ), basename( $path ) ) );
		}

		$width  = (int) $size[0];
		$height = (int) $size[1];
		if ( $width > self::MAX_RASTER_DIMENSION || $height > self::MAX_RASTER_DIMENSION || $width * $height > self::MAX_RASTER_PIXELS ) {
			throw new \RuntimeException( __( 'Artwork dimensions exceed the safe production rendering limit.', 'overcustomise' ) );
		}

		return [ $width, $height ];
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

		$bleed = max( 0.0, $bleed );
		$slug  = max( 0.0, $slug );
		$inset = $bleed + $slug;
		$page_w = $w_mm + $inset * 2;
		$page_h = $h_mm + $inset * 2;

		$orientation = $page_w > $page_h ? 'L' : 'P';
		$pdf_mode = self::pdf_conformance_mode();
		$pdf = new class( $orientation, 'mm', [ $page_w, $page_h ], true, 'UTF-8', false, $pdf_mode ) extends \TCPDF {
			/** Allow the legacy TCPDF facade to initialise tc-lib-pdf in PDF/X mode. */
			protected function normalizePdfaMode( mixed $pdfa ): string {
				$mode = strtolower( trim( (string) $pdfa ) );
				if ( preg_match( '/^pdfx(?:1a|3|4|5)?$/', $mode ) ) {
					return $mode;
				}

				return parent::normalizePdfaMode( $pdfa );
			}

			/** Include WordPress uploads in TCPDF 7's local file allowlist. */
			protected function fileAllowedPaths(): array {
				$paths      = parent::fileAllowedPaths();
				$upload_dir = wp_upload_dir();

				if ( empty( $upload_dir['error'] ) ) {
					$paths[] = $upload_dir['basedir'];
					$paths[] = trailingslashit( $upload_dir['basedir'] ) . 'overcustomise/tcpdf-fonts';
					$paths[] = trailingslashit( $upload_dir['basedir'] ) . 'overcustomise/tcpdf-cache';
				}
				$private_artwork = class_exists( 'OC_Upload_Handler' ) ? OC_Upload_Handler::private_storage_path( 'artwork' ) : null;
				if ( is_string( $private_artwork ) ) {
					$paths[] = $private_artwork;
				}

				$allowed = [];
				foreach ( $paths as $path ) {
					if ( is_string( $path ) && '' !== $path ) {
						$real = realpath( $path );
						$allowed[] = false !== $real ? $real : $path;
					}
				}

				return array_values( array_unique( $allowed ) );
			}
		};
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
	// Shared text/font helpers (used by all generators)
	// -------------------------------------------------------------------------

	/**
	 * Resolve a TCPDF font name from a font DB ID.
	 * A zero ID is the deliberate legacy default; explicit IDs must render exactly.
	 */
	protected static function resolve_font( int $font_id, ?\TCPDF $pdf = null ): string {
		if ( $font_id <= 0 ) {
			return 'helvetica';
		}

		$font = self::get_font( $font_id );
		if ( ! $font ) {
			throw new \RuntimeException( sprintf( __( 'The selected print font #%d is no longer retained.', 'overcustomise' ), $font_id ) );
		}
		$raw_path = self::get_raw_font_path( $font );
		if ( is_string( $raw_path ) && 'woff2' === strtolower( pathinfo( $raw_path, PATHINFO_EXTENSION ) ) ) {
			/* translators: %d: Font database ID. */
			throw new \RuntimeException( sprintf( __( 'The selected print font #%d is a WOFF2 web font. Convert it for print in OverCustomise > Fonts, then regenerate this file.', 'overcustomise' ), $font_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are not rendered as HTML.
		}
		$path = self::get_font_path( $font );
		if ( ! $path ) {
			throw new \RuntimeException( sprintf( __( 'The selected print font #%d has no renderable production file.', 'overcustomise' ), $font_id ) );
		}
		$name = self::register_tcpdf_font( $path );
		if ( '' === $name ) {
			throw new \RuntimeException( sprintf( __( 'The selected print font #%d could not be registered for production.', 'overcustomise' ), $font_id ) );
		}
		if ( $pdf ) {
			$font_file = self::tcpdf_font_definition_path( $path, $name );
			if ( '' === $font_file ) {
				throw new \RuntimeException( sprintf( __( 'The selected print font #%d has no usable PDF definition.', 'overcustomise' ), $font_id ) );
			}
			$pdf->AddFont( $name, '', $font_file );
		}

		return $name;
	}

	protected static function tcpdf_font_definition_path( string $font_path, string $font_name ): string {
		$upload_dir = wp_upload_dir();
		$font_dir   = trailingslashit( $upload_dir['basedir'] ) . 'overcustomise/tcpdf-fonts/';
		$json_file  = $font_dir . $font_name . '.json';
		if ( file_exists( $json_file ) ) {
			return $json_file;
		}

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
		float $font_size_pt,
		bool $multiline = false
	): bool {
		$cell_h = self::cell_h( $font_size_pt );
		if ( $multiline ) {
			$lines = max( 1, (int) $pdf->getNumLines( $text, $w_mm ) );

			return $lines * $cell_h <= $h_mm;
		}

		return $pdf->GetStringWidth( $text ) <= $w_mm && $cell_h <= $h_mm;
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
		string $valign = 'C',
		bool $multiline = false
	): void {
		if ( $multiline ) {
			$line_count = max( 1, (int) $pdf->getNumLines( $text, $w_mm ) );
			$content_h = min( $h_mm, $line_count * $cell_h );
		} else {
			$content_h = $cell_h;
		}

		$offset_y = match ( $valign ) {
			'T' => 0.0,
			'B' => max( 0.0, $h_mm - $content_h ),
			default => max( 0.0, ( $h_mm - $content_h ) / 2 ),
		};

		$pdf->StartTransform();
		$pdf->Rect( $x_mm, $y_mm, $w_mm, $h_mm, 'CNZ' );
		$pdf->SetXY( $x_mm, $y_mm + $offset_y );
		if ( $multiline ) {
			$pdf->MultiCell( $w_mm, $cell_h, $text, 0, $align, false, 1, $x_mm, $y_mm + $offset_y );
		} else {
			$pdf->Cell( $w_mm, $cell_h, $text, 0, 0, $align, false, '', 1 );
		}
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

		$real = realpath( $path );
		if ( false === $real || ! is_file( $real ) ) {
			return false;
		}
		$private = class_exists( 'OC_Upload_Handler' ) ? OC_Upload_Handler::private_storage_path( 'artwork' ) : null;
		if ( is_string( $private ) && self::path_is_within( $real, $private ) ) {
			return true;
		}

		$uploads = wp_upload_dir();
		$legacy  = ! empty( $uploads['basedir'] ) ? realpath( trailingslashit( (string) $uploads['basedir'] ) . 'overcustomise/artwork' ) : false;
		return false !== $legacy && self::path_is_within( $real, $legacy ) && self::legacy_artwork_root_is_protected( $legacy );
	}

	/** Require intact deny rules before using a legacy public-upload artwork path. */
	private static function legacy_artwork_root_is_protected( string $directory ): bool {
		$files = [
			'.htaccess' => "Options -Indexes\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n",
			'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?><configuration><system.webServer><security><authorization><remove users=\"*\" roles=\"\" verbs=\"\"/><add accessType=\"Deny\" users=\"*\"/></authorization></security></system.webServer></configuration>\n",
			'index.php' => "<?php\nhttp_response_code( 404 );\nexit;\n",
		];
		foreach ( $files as $filename => $contents ) {
			$file = $directory . DIRECTORY_SEPARATOR . $filename;
			if ( ! is_file( $file ) || ! hash_equals( $contents, (string) file_get_contents( $file ) ) ) {
				return false;
			}
		}

		return true;
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

	/** Return true when the print payload contains v2 layer geometry. */
	protected static function has_layer_payload( array $area_data ): bool {
		return ! empty( $area_data['layers'] ) && is_array( $area_data['layers'] );
	}

	/** Match the frontend convention: stored ascending layers paint bottom to top. */
	protected static function layer_paint_order( array $layers ): array {
		return array_values( $layers );
	}

	/** Return true when the print payload contains a fully vector snapshot. */
	protected static function has_vector_snapshot_payload( array $area_data ): bool {
		$snapshot = is_array( $area_data['snapshot'] ?? null ) ? $area_data['snapshot'] : [];
		$svg      = is_string( $snapshot['svg'] ?? null ) ? trim( (string) $snapshot['svg'] ) : '';

		return '' !== $svg && str_contains( $svg, '<svg' ) && ! preg_match( '/<image\b/i', $svg );
	}

	/** Render the browser-captured vector snapshot so PDF output matches the customer preview. */
	protected static function render_vector_snapshot_payload( \TCPDF $pdf, array $area_data, float $x_mm, float $y_mm, float $w_mm, float $h_mm ): bool {
		if ( ! self::has_vector_snapshot_payload( $area_data ) ) {
			return false;
		}

		$snapshot = is_array( $area_data['snapshot'] ?? null ) ? $area_data['snapshot'] : [];
		$svg      = (string) $snapshot['svg'];
		if ( strlen( $svg ) > self::MAX_SVG_BYTES ) {
			throw new \RuntimeException( __( 'Vector snapshot exceeds the safe production size limit.', 'overcustomise' ) );
		}
		$temp     = self::temp_path_with_extension( 'oc-vector-snapshot-' . wp_generate_uuid4() . '.svg', 'svg' );
		if ( ! is_string( $temp ) || '' === $temp ) {
			return false;
		}

		if ( false === file_put_contents( $temp, $svg ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			@unlink( $temp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return false;
		}

		try {
			self::draw_pdf_svg( $pdf, $temp, $x_mm, $y_mm, $w_mm, $h_mm );
			return true;
		} catch ( \Throwable $e ) {
			OC_Logger::warning( 'Vector snapshot render failed, falling back to layer payload: ' . $e->getMessage() );
			return false;
		} finally {
			@unlink( $temp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
	}

	/** Render a vector snapshot's visible alpha using the active spot colour. */
	protected static function render_vector_snapshot_spot_mask( \TCPDF $pdf, array $area_data, float $x_mm, float $y_mm, float $w_mm, float $h_mm ): bool {
		if ( ! self::has_vector_snapshot_payload( $area_data ) ) {
			return false;
		}

		$svg = (string) $area_data['snapshot']['svg'];
		if ( strlen( $svg ) > self::MAX_SVG_BYTES ) {
			throw new \RuntimeException( __( 'Vector snapshot exceeds the safe production size limit.', 'overcustomise' ) );
		}
		$temp = self::temp_path_with_extension( 'oc-vector-spot-snapshot-' . wp_generate_uuid4() . '.svg', 'svg' );
		if ( ! is_string( $temp ) || '' === $temp || false === file_put_contents( $temp, $svg ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			if ( is_string( $temp ) ) {
				@unlink( $temp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
			return false;
		}

		try {
			self::render_artwork_spot_mask( $pdf, $temp, $x_mm, $y_mm, $w_mm, $h_mm );
			return true;
		} finally {
			@unlink( $temp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
	}

	/**
	 * Convert quarter-turn v2 areas to a flat artboard. The source dimensions and
	 * turn are retained on the cloned row so layer coordinates can be transformed
	 * rather than stretched into the swapped dimensions.
	 *
	 * @return array{0:object,1:float,2:float}
	 */
	protected static function normalise_rotated_artboard_for_print( object $area, array $area_data ): array {
		[ $w_mm, $h_mm ] = self::area_dimensions_mm( $area );

		if ( ! self::has_layer_payload( $area_data ) ) {
			return [ $area, $w_mm, $h_mm ];
		}

		$bounds   = is_array( $area_data['bounds'] ?? null ) ? $area_data['bounds'] : [];
		$rotation = fmod( (float) ( $bounds['rotation'] ?? $area->canvas_rotation ?? 0 ), 360.0 );
		$rotation = $rotation < 0.0 ? $rotation + 360.0 : $rotation;
		if ( abs( $rotation - 90.0 ) >= 0.001 && abs( $rotation - 270.0 ) >= 0.001 ) {
			return [ $area, $w_mm, $h_mm ];
		}

		$flat_area = clone $area;
		$flat_area->_oc_source_canvas_w = (float) ( $area->canvas_w ?? $bounds['w'] ?? 1 );
		$flat_area->_oc_source_canvas_h = (float) ( $area->canvas_h ?? $bounds['h'] ?? 1 );
		$flat_area->_oc_print_quarter_turn = (int) round( $rotation );
		$flat_area->canvas_w = (float) ( $area->canvas_h ?? $bounds['h'] ?? $area->canvas_w ?? 1 );
		$flat_area->canvas_h = (float) ( $area->canvas_w ?? $bounds['w'] ?? $area->canvas_h ?? 1 );
		$flat_area->canvas_rotation = 0;

		return [ $flat_area, $h_mm, $w_mm ];
	}

	/**
	 * Lay combined print areas out from left to right on one production sheet.
	 *
	 * @param array<int,array{area:object,area_data:array}> $areas
	 * @return array{entries:array<int,array{area:object,area_data:array,x:float,y:float,w:float,h:float}>,page_w:float,page_h:float}
	 */
	protected static function combined_sheet_layout( array $areas, float $inset = 0.0, float $gap = 5.0 ): array {
		$entries = [];
		$cursor  = 0.0;
		$page_h  = 0.0;
		$inset   = max( 0.0, $inset );
		$gap     = max( 0.0, $gap );

		foreach ( $areas as $entry ) {
			if ( ! is_array( $entry ) || ! isset( $entry['area'], $entry['area_data'] ) || ! is_object( $entry['area'] ) || ! is_array( $entry['area_data'] ) ) {
				continue;
			}

			[ $area, $w_mm, $h_mm ] = self::normalise_rotated_artboard_for_print( $entry['area'], $entry['area_data'] );
			$entries[] = [
				'area'      => $area,
				'area_data' => $entry['area_data'],
				'x'         => $cursor + $inset,
				'y'         => $inset,
				'w'         => $w_mm,
				'h'         => $h_mm,
			];
			$cursor += $w_mm + $inset * 2 + $gap;
			$page_h  = max( $page_h, $h_mm + $inset * 2 );
		}

		return [
			'entries' => $entries,
			'page_w'  => max( 0.01, $cursor - ( empty( $entries ) ? 0.0 : $gap ) ),
			'page_h'  => max( 0.01, $page_h ),
		];
	}

	/**
	 * Render v2 layers into the PDF using the same layer boxes as the live preview.
	 * Layer coordinates are stored in mockup pixels, so they are offset back into
	 * print-area space before converting to millimetres.
	 */
	protected static function render_layer_payload( \TCPDF $pdf, object $area, array $area_data, float $origin_x_mm, float $origin_y_mm, string $mode = 'colour', array $options = [] ): void {
		$bounds = is_array( $area_data['bounds'] ?? null ) ? $area_data['bounds'] : [];
		$area_x = isset( $bounds['x'] ) ? (float) $bounds['x'] : (float) ( $area->canvas_x ?? 0 );
		$area_y = isset( $bounds['y'] ) ? (float) $bounds['y'] : (float) ( $area->canvas_y ?? 0 );
		$bounds_w = max( 1.0, (float) ( $bounds['w'] ?? $area->canvas_w ?? 1 ) );
		$bounds_h = max( 1.0, (float) ( $bounds['h'] ?? $area->canvas_h ?? 1 ) );
		[ $area_w_mm, $area_h_mm ] = self::area_dimensions_mm( $area );
		$quarter_turn = (int) ( $area->_oc_print_quarter_turn ?? 0 );
		$font_px_to_pt = self::mm_to_pt_value( in_array( $quarter_turn, [ 90, 270 ], true ) ? $area_w_mm : $area_h_mm ) / $bounds_h;

		foreach ( self::layer_paint_order( $area_data['layers'] ) as $layer ) {
			if ( ! is_array( $layer ) ) {
				continue;
			}

			$type = (string) ( $layer['type'] ?? '' );
			if ( 'mask' === $type ) {
				continue;
			}
			$layer_x = (float) ( $layer['x'] ?? 0 );
			$layer_y = (float) ( $layer['y'] ?? 0 );
			$layer_w = max( 1.0, (float) ( $layer['w'] ?? 1 ) );
			$layer_h = max( 1.0, (float) ( $layer['h'] ?? 1 ) );
			$input = is_array( $layer['input'] ?? null ) ? $layer['input'] : [];
			$settings = is_array( $layer['settings'] ?? null ) ? $layer['settings'] : [];
			$relative_cx = ( $layer_x - $area_x + $layer_w / 2 ) / $bounds_w;
			$relative_cy = ( $layer_y - $area_y + $layer_h / 2 ) / $bounds_h;

			if ( 90 === $quarter_turn ) {
				$center_x = $origin_x_mm + ( 1.0 - $relative_cy ) * $area_w_mm;
				$center_y = $origin_y_mm + $relative_cx * $area_h_mm;
				$w_mm     = ( $layer_w / $bounds_w ) * $area_h_mm;
				$h_mm     = ( $layer_h / $bounds_h ) * $area_w_mm;
			} elseif ( 270 === $quarter_turn ) {
				$center_x = $origin_x_mm + $relative_cy * $area_w_mm;
				$center_y = $origin_y_mm + ( 1.0 - $relative_cx ) * $area_h_mm;
				$w_mm     = ( $layer_w / $bounds_w ) * $area_h_mm;
				$h_mm     = ( $layer_h / $bounds_h ) * $area_w_mm;
			} else {
				$center_x = $origin_x_mm + $relative_cx * $area_w_mm;
				$center_y = $origin_y_mm + $relative_cy * $area_h_mm;
				$w_mm     = ( $layer_w / $bounds_w ) * $area_w_mm;
				$h_mm     = ( $layer_h / $bounds_h ) * $area_h_mm;
			}

			$x_mm = $center_x - $w_mm / 2;
			$y_mm = $center_y - $h_mm / 2;
			if (
				'colour' === $mode
				&& ! empty( $options['full_bleed_artwork'] )
				&& in_array( $type, [ 'image', 'clipart', 'clipmask' ], true )
			) {
				$bleed_mm = max( 0.0, (float) ( $options['bleed_mm'] ?? 0.0 ) );
				$background = ! empty( $settings['background'] ) || ! empty( $settings['is_background'] ) || ! empty( $settings['full_bleed'] );
				$tolerance = max( 0.01, min( $area_w_mm, $area_h_mm ) * 0.002 );
				$left   = $background || $x_mm <= $origin_x_mm + $tolerance;
				$top    = $background || $y_mm <= $origin_y_mm + $tolerance;
				$right  = $background || $x_mm + $w_mm >= $origin_x_mm + $area_w_mm - $tolerance;
				$bottom = $background || $y_mm + $h_mm >= $origin_y_mm + $area_h_mm - $tolerance;
				if ( $left ) {
					$x_mm -= $bleed_mm;
					$w_mm += $bleed_mm;
				}
				if ( $right ) {
					$w_mm += $bleed_mm;
				}
				if ( $top ) {
					$y_mm -= $bleed_mm;
					$h_mm += $bleed_mm;
				}
				if ( $bottom ) {
					$h_mm += $bleed_mm;
				}
			}
			$rotation = $quarter_turn + self::layer_rotation_degrees( $layer, $input, $settings );
			$rotation = fmod( $rotation, 360.0 );
			$transformed = abs( $rotation ) >= 0.001;
			if ( $transformed ) {
				$pdf->StartTransform();
				// Fabric uses clockwise angles in its top-left coordinate system;
				// TCPDF expects counter-clockwise angles.
				$pdf->Rotate( -$rotation, $center_x, $center_y );
			}

			try {
				switch ( $type ) {
					case 'text':
					case 'textarea':
						self::render_layer_text( $pdf, $layer, $input, $settings, $x_mm, $y_mm, $w_mm, $h_mm, $mode, $font_px_to_pt );
						break;

					case 'spotify':
						self::render_layer_spotify( $pdf, $input, $x_mm, $y_mm, $w_mm, $h_mm, $mode );
						break;

					case 'image':
					case 'clipart':
						self::render_layer_image( $pdf, $layer, $input, $x_mm, $y_mm, $w_mm, $h_mm, $mode, $options );
						break;

					case 'clipmask':
						self::render_layer_clipped_image( $pdf, $layer, $x_mm, $y_mm, $w_mm, $h_mm, $mode, $options );
						break;

					case 'lineart':
						$hex = (string) ( $input['colorHex'] ?? '#000000' );
						if ( 'engraving' === $mode ) {
							$pdf->SetFillColor( ...self::ENGRAVING_TONE_RGB );
						} elseif ( 'spot' !== $mode ) {
							[ $c, $m, $y, $k ] = self::hex_to_cmyk( $hex );
							$pdf->SetFillColorArray( [ $c, $m, $y, $k ] );
						}
						$pdf->Rect( $x_mm, $y_mm, $w_mm, $h_mm, 'F' );
						break;
				}
			} finally {
				if ( $transformed ) {
					$pdf->StopTransform();
				}
			}
		}
	}

	/** Resolve a layer-local rotation without mixing it with print-area rotation. */
	private static function layer_rotation_degrees( array $layer, array $input, array $settings ): float {
		foreach ( [ $layer['rotation'] ?? null, $input['rotation'] ?? null, $settings['rotation'] ?? null ] as $rotation ) {
			if ( is_numeric( $rotation ) ) {
				return (float) $rotation;
			}
		}

		return 0.0;
	}

	private static function render_layer_text( \TCPDF $pdf, array $layer, array $input, array $settings, float $x_mm, float $y_mm, float $w_mm, float $h_mm, string $mode, ?float $font_px_to_pt = null ): void {
		$is_textarea = 'textarea' === (string) ( $layer['type'] ?? '' );
		$text        = trim( str_replace( [ "\r\n", "\r" ], "\n", (string) ( $input['value'] ?? '' ) ) );
		if ( '' === $text ) {
			return;
		}
		if ( 'engraving' === $mode ) {
			$text = self::normalise_engraving_text( $text );
		}
		$rendered_lines = $is_textarea ? self::browser_rendered_text_lines( $input, $text ) : null;
		$render_text    = null !== $rendered_lines ? implode( "\n", $rendered_lines ) : $text;

		$font_id              = ! empty( $input['fontId'] ) ? (int) $input['fontId'] : (int) ( $settings['default_font_id'] ?? 0 );
		$font                 = $font_id ? self::get_font( $font_id ) : null;
		$font_name            = self::resolve_font( $font_id, $pdf );
		$raw_font_path        = is_object( $font ) ? self::get_raw_font_path( $font ) : null;
		$engraving_font_path  = 'engraving' === $mode && is_object( $font ) ? self::get_font_path( $font ) : null;
		$font_px_to_pt        = $font_px_to_pt && $font_px_to_pt > 0 ? $font_px_to_pt : self::px_to_pt( 1.0 );
		$configured_font_size = (float) ( $input['fontSize'] ?? $settings['default_font_size'] ?? 0 );
		$rendered_font_size   = self::browser_rendered_font_size( $input, $configured_font_size );
		$font_size            = $configured_font_size > 0
			? max( 4.0, ( $rendered_font_size ?? $configured_font_size ) * $font_px_to_pt )
			: max( 4.0, max( 1.0, (float) ( $layer['h'] ?? 1 ) ) * 0.72 * $font_px_to_pt );
		$min_size  = ! empty( $settings['min_font_size'] ) ? (float) $settings['min_font_size'] * $font_px_to_pt : 0.0;
		$max_size  = ! empty( $settings['max_font_size'] ) ? (float) $settings['max_font_size'] * $font_px_to_pt : 0.0;
		if ( $max_size > 0.0 ) {
			$font_size = min( $font_size, $max_size );
		}
		if ( $min_size > 0.0 ) {
			$font_size = max( $font_size, $min_size );
		}

		$draw_x_mm = $x_mm;
		$draw_w_mm = $w_mm;

		while ( $font_size > max( 4.0, $min_size ) ) {
			$pdf->SetFont( $font_name, '', $font_size );
			if ( null !== $rendered_lines ) {
				$fits = is_string( $engraving_font_path ) && '' !== $engraving_font_path
					? self::engraving_outline_lines_fit_box( $rendered_lines, $engraving_font_path, $draw_w_mm, $h_mm, $font_size )
					: self::fixed_text_lines_fit_box( $pdf, $rendered_lines, $draw_w_mm, $h_mm, $font_size );
			} else {
				$fits = is_string( $engraving_font_path ) && '' !== $engraving_font_path && $is_textarea
					? self::engraving_outline_text_fits_box( $text, $engraving_font_path, $draw_w_mm, $h_mm, $font_size )
					: self::text_fits_box( $pdf, $text, $draw_w_mm, $h_mm, $font_size, $is_textarea );
			}
			if ( $fits ) {
				break;
			}
			$font_size -= 0.5;
		}

		$align = strtoupper( substr( (string) ( $settings['alignment'] ?? 'center' ), 0, 1 ) );
		if ( ! in_array( $align, [ 'L', 'C', 'R' ], true ) ) {
			$align = 'C';
		}
		$valign_setting = $is_textarea ? (string) ( $settings['line_alignment'] ?? 'top' ) : 'center';
		$valign = match ( $valign_setting ) {
			'top' => 'T',
			'bottom' => 'B',
			default => 'C',
		};

		$textarea_wraps = false;
		if ( $is_textarea ) {
			$pdf->SetFont( $font_name, '', $font_size );
			$textarea_wraps = null !== $rendered_lines && count( $rendered_lines ) > 1;
			$textarea_wraps = $textarea_wraps || str_contains( $render_text, "\n" ) || ( is_string( $engraving_font_path ) && '' !== $engraving_font_path
				? count( self::wrap_engraving_outline_lines( $text, $engraving_font_path, $font_size, self::mm_to_pt_value( $draw_w_mm ) ) ) > 1
				: (int) $pdf->getNumLines( $text, $w_mm ) > 1 );
		}

		if ( 'engraving' === $mode && is_string( $engraving_font_path ) && '' !== $engraving_font_path ) {
			if ( $textarea_wraps && self::render_engraving_multiline_text_outline( $pdf, $render_text, $engraving_font_path, $font_size, $draw_x_mm, $y_mm, $draw_w_mm, $h_mm, $align, $valign, $rendered_lines ) ) {
				return;
			}

			if ( ! $textarea_wraps && self::render_engraving_text_outline( $pdf, $render_text, $engraving_font_path, $font_size, $draw_x_mm, $y_mm, $draw_w_mm, $h_mm, $align ) ) {
				return;
			}
		}

		if ( 'engraving' === $mode && is_string( $raw_font_path ) && '' !== $raw_font_path && self::render_engraving_text_raster( $pdf, $render_text, $raw_font_path, $font_size, $draw_x_mm, $y_mm, $draw_w_mm, $h_mm, $align, $valign ) ) {
			return;
		}

		$pdf->SetFont( $font_name, '', $font_size );
		if ( 'engraving' === $mode ) {
			$pdf->SetTextColor( ...self::ENGRAVING_TONE_RGB );
		} elseif ( 'spot' !== $mode ) {
			[ $c, $m, $y, $k ] = self::hex_to_cmyk( (string) ( $input['colorHex'] ?? $settings['default_color'] ?? '#000000' ) );
			$pdf->SetTextColorArray( [ $c, $m, $y, $k ] );
		}

		$cell_h = self::cell_h( $font_size );
		self::draw_clipped_text_cell( $pdf, $draw_x_mm, $y_mm, $draw_w_mm, $h_mm, $render_text, $cell_h, $align, $valign, $is_textarea );
	}

	/** Use Fabric's submitted lines only when they reproduce the canonical text. */
	protected static function browser_rendered_text_lines( array $input, string $text ): ?array {
		$raw_lines = $input['renderedLines'] ?? null;
		if ( ! is_array( $raw_lines ) || empty( $raw_lines ) || count( $raw_lines ) > 200 ) {
			return null;
		}

		$lines = [];
		foreach ( $raw_lines as $line ) {
			if ( ! is_string( $line ) ) {
				return null;
			}
			$lines[] = $line;
		}
		$normalise = static fn( string $value ): string => preg_replace( '/\s+/u', ' ', trim( $value ) ) ?? '';

		$rendered_text = implode( "\n", $lines );
		if ( $normalise( $rendered_text ) === $normalise( $text ) ) {
			return $lines;
		}

		// Grapheme wrapping can add a soft line boundary inside an unbroken word.
		$compact = static fn( string $value ): string => preg_replace( '/\s+/u', '', $value ) ?? '';

		return $compact( $rendered_text ) === $compact( $text ) ? $lines : null;
	}

	/** Use Fabric's final auto-fitted size only when it cannot enlarge the configured text. */
	protected static function browser_rendered_font_size( array $input, float $configured_size ): ?float {
		$rendered_size = $input['renderedFontSize'] ?? null;
		if ( ! is_numeric( $rendered_size ) || $configured_size <= 0.0 ) {
			return null;
		}

		$rendered_size = (float) $rendered_size;
		return $rendered_size > 0.0 && $rendered_size <= $configured_size ? $rendered_size : null;
	}

	/** Check fixed browser lines without allowing TCPDF to choose different wraps. */
	private static function fixed_text_lines_fit_box( \TCPDF $pdf, array $lines, float $w_mm, float $h_mm, float $font_size ): bool {
		foreach ( $lines as $line ) {
			if ( $pdf->GetStringWidth( $line ) > $w_mm ) {
				return false;
			}
		}

		return count( $lines ) * self::cell_h( $font_size ) <= $h_mm;
	}

	private static function render_engraving_text_raster( \TCPDF $pdf, string $text, string $font_path, float $font_size, float $x_mm, float $y_mm, float $w_mm, float $h_mm, string $align, string $valign ): bool {
		if ( ! class_exists( '\Imagick' ) || ! class_exists( '\ImagickDraw' ) || ! is_readable( $font_path ) ) {
			return false;
		}

		$dpi = 600;
		[ $width_px, $height_px ] = self::bounded_work_dimensions(
			max( 1, (int) ceil( max( 0.1, $w_mm ) / 25.4 * $dpi ) ),
			max( 1, (int) ceil( max( 0.1, $h_mm ) / 25.4 * $dpi ) )
		);
		$temp = self::temp_path_with_extension( 'oc-engraving-text-raster-' . wp_generate_uuid4() . '.png', 'png' );
		if ( ! is_string( $temp ) || '' === $temp ) {
			return false;
		}

		try {
			$image = new \Imagick();
			self::configure_imagick_limits( $image );
			$image->newImage( $width_px, $height_px, new \ImagickPixel( 'transparent' ), 'png' );
			$image->setImageFormat( 'png32' );

			$draw = new \ImagickDraw();
			$draw->setFont( $font_path );
			$draw->setFontSize( max( 1.0, $font_size * $dpi / 72 ) );
			$draw->setFillColor( new \ImagickPixel( 'black' ) );
			$draw->setTextAntialias( true );
			$draw->setTextAlignment( match ( $align ) {
				'L' => \Imagick::ALIGN_LEFT,
				'R' => \Imagick::ALIGN_RIGHT,
				default => \Imagick::ALIGN_CENTER,
			} );
			$draw->setGravity( self::imagick_text_gravity( $align, $valign ) );
			$image->annotateImage( $draw, 0, 0, 0, $text );

			if ( ! $image->writeImage( $temp ) ) {
				return false;
			}

			$pdf->Image( $temp, $x_mm, $y_mm, $w_mm, $h_mm, '', '', '', false, $dpi );
			return true;
		} catch ( \Throwable $e ) {
			OC_Logger::warning( 'Engraving text raster font render failed for ' . basename( $font_path ) . ': ' . $e->getMessage() );
			return false;
		} finally {
			if ( isset( $draw ) ) {
				$draw->clear();
				$draw->destroy();
			}
			if ( isset( $image ) ) {
				$image->clear();
				$image->destroy();
			}
			@unlink( $temp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
	}

	/** @return \Imagick::GRAVITY_NORTHWEST|\Imagick::GRAVITY_NORTH|\Imagick::GRAVITY_NORTHEAST|\Imagick::GRAVITY_WEST|\Imagick::GRAVITY_CENTER|\Imagick::GRAVITY_EAST|\Imagick::GRAVITY_SOUTHWEST|\Imagick::GRAVITY_SOUTH|\Imagick::GRAVITY_SOUTHEAST */
	private static function imagick_text_gravity( string $align, string $valign ): int {
		return match ( $valign ) {
			'T' => match ( $align ) {
				'L' => \Imagick::GRAVITY_NORTHWEST,
				'R' => \Imagick::GRAVITY_NORTHEAST,
				default => \Imagick::GRAVITY_NORTH,
			},
			'B' => match ( $align ) {
				'L' => \Imagick::GRAVITY_SOUTHWEST,
				'R' => \Imagick::GRAVITY_SOUTHEAST,
				default => \Imagick::GRAVITY_SOUTH,
			},
			default => match ( $align ) {
				'L' => \Imagick::GRAVITY_WEST,
				'R' => \Imagick::GRAVITY_EAST,
				default => \Imagick::GRAVITY_CENTER,
			},
		};
	}

	private static function engraving_outline_text_fits_box( string $text, string $font_path, float $w_mm, float $h_mm, float $font_size ): bool {
		$lines = self::wrap_engraving_outline_lines( $text, $font_path, $font_size, self::mm_to_pt_value( $w_mm ) );
		if ( empty( $lines ) ) {
			return false;
		}

		return count( $lines ) * self::cell_h( $font_size ) <= $h_mm;
	}

	/** Check fixed browser lines against outline metrics, shrinking all lines uniformly when needed. */
	private static function engraving_outline_lines_fit_box( array $lines, string $font_path, float $w_mm, float $h_mm, float $font_size ): bool {
		$max_width = self::mm_to_pt_value( $w_mm );
		foreach ( $lines as $line ) {
			if ( self::engraving_outline_text_width( $font_path, $line, $font_size ) > $max_width ) {
				return false;
			}
		}

		return count( $lines ) * self::cell_h( $font_size ) <= $h_mm;
	}

	private static function render_engraving_text_outline( \TCPDF $pdf, string $text, string $font_path, float $font_size, float $x_mm, float $y_mm, float $w_mm, float $h_mm, string $align ): bool {
		if ( ! class_exists( 'OC_Print_Embroidery' ) ) {
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
		// Fabric positions every single-line text object from the same typographic
		// baseline. Centring each string's visible glyph box here moves script and
		// capitals by different amounts and destroys intentional layer overlaps.
		$line_h    = $font_size * self::FABRIC_FONT_SIZE_MULTIPLIER;
		$fit_scale = min( 1.0, $box_w_pt / $layout_w, $box_h_pt / $line_h );
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
		$path_x    = -1 * (float) $bbox[0] * $fit_scale + $pad;
		$path_y    = (float) $bbox[3] * $fit_scale + $pad;
		$baseline_y = $box_h_pt / 2 + $line_h * $fit_scale * ( 0.5 - self::FABRIC_FONT_SIZE_FRACTION );
		$origin_y   = $baseline_y - $path_y;

		$svg = sprintf(
			'<svg xmlns="http://www.w3.org/2000/svg" width="%.4Fpt" height="%.4Fpt" viewBox="0 0 %.4F %.4F"><g transform="translate(%.4F %.4F) scale(%.8F %.8F)"><path d="%s" fill="#000000" fill-rule="nonzero" clip-rule="nonzero"/></g></svg>',
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

		$temp = self::temp_path_with_extension( 'oc-engraving-text-outline-' . wp_generate_uuid4() . '.svg', 'svg' );
		if ( ! is_string( $temp ) || '' === $temp ) {
			return false;
		}

		if ( false === file_put_contents( $temp, $svg ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			@unlink( $temp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
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
		}
	}

	private static function render_engraving_multiline_text_outline( \TCPDF $pdf, string $text, string $font_path, float $font_size, float $x_mm, float $y_mm, float $w_mm, float $h_mm, string $align, string $valign, ?array $fixed_lines = null ): bool {
		$lines = null !== $fixed_lines ? $fixed_lines : self::wrap_engraving_outline_lines( $text, $font_path, $font_size, self::mm_to_pt_value( $w_mm ) );
		if ( empty( $lines ) ) {
			return false;
		}

		$line_h = min( self::cell_h( $font_size ), $h_mm / count( $lines ) );
		$total_h = count( $lines ) * $line_h;
		$offset_y = match ( $valign ) {
			'T' => 0.0,
			'B' => max( 0.0, $h_mm - $total_h ),
			default => max( 0.0, ( $h_mm - $total_h ) / 2 ),
		};

		$rendered = false;
		foreach ( $lines as $index => $line ) {
			$line_y = $y_mm + $offset_y + ( $index * $line_h );
			if ( $line_y + $line_h > $y_mm + $h_mm + 0.001 ) {
				break;
			}

			$rendered = self::render_engraving_text_outline( $pdf, $line, $font_path, $font_size, $x_mm, $line_y, $w_mm, $line_h, $align ) || $rendered;
		}

		return $rendered;
	}

	/** @return string[] */
	private static function wrap_engraving_outline_lines( string $text, string $font_path, float $font_size, float $max_width_pt ): array {
		$lines = [];
		foreach ( preg_split( '/\R/u', $text ) ?: [] as $paragraph ) {
			$paragraph = trim( (string) $paragraph );
			if ( '' === $paragraph ) {
				continue;
			}

			$current = '';
			foreach ( preg_split( '/\s+/u', $paragraph ) ?: [] as $word ) {
				$word = (string) $word;
				$candidate = '' === $current ? $word : $current . ' ' . $word;
				if ( self::engraving_outline_text_width( $font_path, $candidate, $font_size ) <= $max_width_pt ) {
					$current = $candidate;
					continue;
				}

				if ( '' !== $current ) {
					$lines[] = $current;
				}

				if ( self::engraving_outline_text_width( $font_path, $word, $font_size ) <= $max_width_pt ) {
					$current = $word;
				} else {
					$split = self::wrap_engraving_outline_word( $word, $font_path, $font_size, $max_width_pt );
					$lines = array_merge( $lines, array_slice( $split, 0, -1 ) );
					$current = (string) end( $split );
				}
			}

			if ( '' !== $current ) {
				$lines[] = $current;
			}
		}

		return $lines;
	}

	/** @return string[] */
	private static function wrap_engraving_outline_word( string $word, string $font_path, float $font_size, float $max_width_pt ): array {
		$chars = preg_split( '//u', $word, -1, PREG_SPLIT_NO_EMPTY ) ?: str_split( $word );
		$lines = [];
		$current = '';

		foreach ( $chars as $char ) {
			$candidate = $current . $char;
			if ( '' === $current || self::engraving_outline_text_width( $font_path, $candidate, $font_size ) <= $max_width_pt ) {
				$current = $candidate;
				continue;
			}

			$lines[] = $current;
			$current = $char;
		}

		if ( '' !== $current ) {
			$lines[] = $current;
		}

		return $lines;
	}

	private static function engraving_outline_text_width( string $font_path, string $text, float $font_size ): float {
		if ( '' === $text || ! class_exists( 'OC_Print_Embroidery' ) ) {
			return 0.0;
		}

		try {
			$method  = new \ReflectionMethod( 'OC_Print_Embroidery', 'ttf_text_outline' );
			$outline = $method->invoke( null, $font_path, $text, $font_size );
		} catch ( \Throwable $e ) {
			return 0.0;
		}

		return is_array( $outline ) ? max( 0.0, (float) ( $outline['width'] ?? 0.0 ) ) : 0.0;
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
			throw new \RuntimeException( __( 'The Spotify layer does not contain a valid Spotify URI.', 'overcustomise' ) );
		}

		$svg_path = self::download_spotify_code_svg( $spotify_url );
		if ( ! $svg_path ) {
			throw new \RuntimeException( __( 'The Spotify code could not be retrieved for production.', 'overcustomise' ) );
		}

		try {
			if ( 'spot' === $mode ) {
				self::render_artwork_spot_mask( $pdf, $svg_path, $x_mm, $y_mm, $w_mm, $h_mm );
			} else {
				$pdf->ImageSVG( $svg_path, $x_mm, $y_mm, $w_mm, $h_mm, '', '', '', 0, false );
			}
		} finally {
			@unlink( $svg_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
	}

	protected static function build_spotify_code_url( string $input_value, bool $engraving = false ): string {
		$spotify_uri = self::extract_spotify_uri( $input_value );
		if ( '' === $spotify_uri ) {
			return '';
		}

		return sprintf(
			'https://scannables.scdn.co/uri/plain/svg/FFFFFF/black/640/%s',
			$spotify_uri
		);
	}

	protected static function extract_spotify_uri( string $input_value ): string {
		$raw = trim( $input_value );
		if ( '' === $raw ) {
			return '';
		}

		if ( preg_match( '/^spotify:(track|album|artist|playlist|episode|show):([A-Za-z0-9]{1,128})$/i', $raw, $matches ) ) {
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
			if ( preg_match( '/^[A-Za-z0-9]{1,128}$/', $id ) ) {
				return sprintf( 'spotify:%s:%s', $part, $id );
			}
		}

		return '';
	}

	protected static function download_spotify_code_svg( string $spotify_url ): ?string {
		$response = wp_safe_remote_get( $spotify_url, [
			'timeout'     => 8,
			'redirection' => 2,
			'limit_response_size' => self::MAX_SPOTIFY_RESPONSE_BYTES,
		] );

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$body = wp_remote_retrieve_body( $response );
		if ( ! is_string( $body ) || strlen( $body ) > self::MAX_SPOTIFY_RESPONSE_BYTES || ! str_contains( $body, '<svg' ) ) {
			return null;
		}

		$temp = self::temp_path_with_extension( 'oc-spotify-code-' . wp_generate_uuid4() . '.svg', 'svg' );
		if ( ! is_string( $temp ) || '' === $temp ) {
			return null;
		}

		if ( class_exists( 'OC_SVG_Sanitiser' ) ) {
			try {
				$body = OC_SVG_Sanitiser::sanitise( $body );
			} catch ( \InvalidArgumentException $e ) {
				return null;
			}
		}
		$body = self::make_spotify_svg_background_transparent( $body );

		if ( false === file_put_contents( $temp, $body ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			@unlink( $temp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return null;
		}

		return $temp;
	}

	private static function make_spotify_svg_background_transparent( string $svg ): string {
		$white = '(?:#fff(?:fff)?|white|rgb\(\s*255\s*,\s*255\s*,\s*255\s*\)|rgba\(\s*255\s*,\s*255\s*,\s*255\s*,\s*1\s*\))';
		$svg   = preg_replace( '/(\sfill=["\'])' . $white . '(["\'])/i', '$1none$2', $svg ) ?? $svg;
		$svg   = preg_replace( '/(\sstroke=["\'])' . $white . '(["\'])/i', '$1none$2', $svg ) ?? $svg;
		$svg   = preg_replace( '/(fill\s*:\s*)' . $white . '/i', '$1none', $svg ) ?? $svg;
		$svg   = preg_replace( '/(stroke\s*:\s*)' . $white . '/i', '$1none', $svg ) ?? $svg;

		return $svg;
	}

	private static function render_layer_image( \TCPDF $pdf, array $layer, array $input, float $x_mm, float $y_mm, float $w_mm, float $h_mm, string $mode = 'colour', array $options = [] ): void {
		$path = self::resolve_artwork_path( array_merge( $input, $layer ) );
		if ( ! $path ) {
			if ( absint( $input['attachmentId'] ?? 0 ) > 0 || absint( $layer['artworkAttachmentId'] ?? 0 ) > 0 || absint( $input['clipartId'] ?? 0 ) > 0 ) {
				throw new \RuntimeException( __( 'A selected artwork layer no longer has a readable production file.', 'overcustomise' ) );
			}
			return;
		}

		$temp_paths = [];
		if (
			'colour' === $mode
			&& 'clipart' === (string) ( $layer['type'] ?? '' )
			&& ! empty( $input['clipartRecolourable'] )
		) {
			$hex = sanitize_hex_color( (string) ( $input['colorHex'] ?? '' ) );
			if ( $hex ) {
				$coloured_path = self::build_coloured_clipart( $path, $hex );
				if ( is_string( $coloured_path ) && '' !== $coloured_path ) {
					$temp_paths[] = $coloured_path;
					$path         = $coloured_path;
				}
			}
		}
		if ( 'image' === (string) ( $layer['type'] ?? '' ) ) {
			$filtered_path = self::build_filtered_image( $path, $layer, $input );
			if ( is_string( $filtered_path ) && '' !== $filtered_path ) {
				if ( $filtered_path !== $path ) {
					$temp_paths[] = $filtered_path;
				}
				$path         = $filtered_path;
			} elseif ( absint( $input['imageFilterId'] ?? 0 ) > 0 ) {
				throw new \RuntimeException( __( 'The selected image filter could not be reproduced for production.', 'overcustomise' ) );
			}
		}
		if ( 'engraving' === $mode ) {
			$crop_amount                       = 'image' === (string) ( $layer['type'] ?? '' )
				? max( 0.0, min( 1.0, absint( $input['imageCrop'] ?? 0 ) / 100 ) )
				: 0.0;
			[ , , $engraving_w, $engraving_h ] = self::fit_artwork_box( $path, $x_mm, $y_mm, $w_mm, $h_mm, $crop_amount );
			if ( 'clipart' === (string) ( $layer['type'] ?? '' ) ) {
				$engraved_path = self::build_black_clipart( $path );
				if ( ! is_string( $engraved_path ) || '' === $engraved_path ) {
					throw new \RuntimeException( __( 'The selected clipart could not be prepared for engraving.', 'overcustomise' ) );
				}
			} else {
				if ( ! class_exists( 'OC_Print_Engraving' ) ) {
					throw new \RuntimeException( __( 'The engraving artwork converter is unavailable.', 'overcustomise' ) );
				}
				$engraved_path = OC_Print_Engraving::prepare_artwork_for_layer( $path, is_array( $options['engraving_profile'] ?? null ) ? $options['engraving_profile'] : [], $engraving_w, $engraving_h );
			}
			$temp_paths[]  = $engraved_path;
			$path          = $engraved_path;
		}

		$crop_amount = 'image' === (string) ( $layer['type'] ?? '' )
			? max( 0.0, min( 1.0, absint( $input['imageCrop'] ?? 0 ) / 100 ) )
			: 0.0;
		[ $draw_x, $draw_y, $draw_w, $draw_h ] = self::fit_artwork_box( $path, $x_mm, $y_mm, $w_mm, $h_mm, $crop_amount );
		$clip_to_layer = $crop_amount > 0.0;

		try {
			if ( $clip_to_layer ) {
				$pdf->StartTransform();
				$pdf->Rect( $x_mm, $y_mm, $w_mm, $h_mm, 'CNZ' );
			}
			if ( 'spot' === $mode ) {
				self::render_artwork_spot_mask( $pdf, $path, $draw_x, $draw_y, $draw_w, $draw_h );
			} else {
				self::draw_pdf_image( $pdf, $path, $draw_x, $draw_y, $draw_w, $draw_h );
			}
		} finally {
			if ( $clip_to_layer ) {
				$pdf->StopTransform();
			}
			foreach ( array_unique( $temp_paths ) as $temp_path ) {
				if ( is_string( $temp_path ) && '' !== $temp_path && file_exists( $temp_path ) ) {
					@unlink( $temp_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				}
			}
		}
	}

	private static function render_layer_clipped_image( \TCPDF $pdf, array $layer, float $x_mm, float $y_mm, float $w_mm, float $h_mm, string $mode = 'colour', array $options = [] ): void {
		$input = is_array( $layer['input'] ?? null ) ? $layer['input'] : [];
		$path  = self::resolve_artwork_path( array_merge( $input, $layer ) );
		if ( ! $path ) {
			if ( absint( $input['attachmentId'] ?? 0 ) > 0 || absint( $layer['artworkAttachmentId'] ?? 0 ) > 0 ) {
				throw new \RuntimeException( __( 'A selected clipped artwork layer no longer has a readable production file.', 'overcustomise' ) );
			}
			return;
		}
		$temp_path = null;
		if ( 'engraving' === $mode ) {
			if ( ! class_exists( 'OC_Print_Engraving' ) ) {
				throw new \RuntimeException( __( 'The engraving artwork converter is unavailable.', 'overcustomise' ) );
			}
			[ , , $engraving_w, $engraving_h ] = self::fit_artwork_box( $path, $x_mm, $y_mm, $w_mm, $h_mm, 'cover' );
			$temp_path                         = OC_Print_Engraving::prepare_artwork_for_layer( $path, is_array( $options['engraving_profile'] ?? null ) ? $options['engraving_profile'] : [], $engraving_w, $engraving_h );
			$path                              = $temp_path;
		}

		[ $draw_x, $draw_y, $draw_w, $draw_h ] = self::fit_artwork_box( $path, $x_mm, $y_mm, $w_mm, $h_mm, 'cover' );

		$settings = is_array( $layer['settings'] ?? null ) ? $layer['settings'] : [];
		$shape    = sanitize_key( (string) ( $settings['mask_shape'] ?? 'circle' ) );

		$pdf->StartTransform();
		try {
			if ( 'circle' === $shape ) {
				$radius = min( $w_mm, $h_mm ) / 2;
				$pdf->Circle( $x_mm + $w_mm / 2, $y_mm + $h_mm / 2, $radius, 0, 360, 'CNZ' );
			} else {
				$pdf->Rect( $x_mm, $y_mm, $w_mm, $h_mm, 'CNZ' );
			}
			if ( 'spot' === $mode ) {
				self::render_artwork_spot_mask( $pdf, $path, $draw_x, $draw_y, $draw_w, $draw_h );
			} else {
				self::draw_pdf_image( $pdf, $path, $draw_x, $draw_y, $draw_w, $draw_h );
			}
		} finally {
			$pdf->StopTransform();
			if ( is_string( $temp_path ) && '' !== $temp_path && file_exists( $temp_path ) ) {
				@unlink( $temp_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
		}
	}

	/** Fit artwork by intrinsic dimensions, including SVG viewBox dimensions. */
	private static function fit_artwork_box( string $path, float $x, float $y, float $w, float $h, float|string $fit ): array {
		$size = self::artwork_intrinsic_dimensions( $path );
		if ( ! $size ) {
			return [ $x, $y, $w, $h ];
		}

		[ $source_w, $source_h ] = $size;
		$crop_amount  = 'cover' === $fit ? 1.0 : max( 0.0, min( 1.0, (float) $fit ) );
		$contain_scale = min( $w / $source_w, $h / $source_h );
		$cover_scale   = max( $w / $source_w, $h / $source_h );
		$scale         = $contain_scale + ( $cover_scale - $contain_scale ) * $crop_amount;
		$draw_w = $source_w * $scale;
		$draw_h = $source_h * $scale;

		return [ $x + ( $w - $draw_w ) / 2, $y + ( $h - $draw_h ) / 2, $draw_w, $draw_h ];
	}

	/** Return width/height without decoding an unbounded raster. */
	private static function artwork_intrinsic_dimensions( string $path ): ?array {
		if ( 'svg' !== strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) ) {
			return self::assert_safe_raster_dimensions( $path );
		}
		if ( ! class_exists( '\DOMDocument' ) || ! is_readable( $path ) || filesize( $path ) > self::MAX_SVG_BYTES ) {
			return null;
		}

		$dom      = new \DOMDocument();
		$previous = libxml_use_internal_errors( true );
		$loaded   = $dom->load( $path, LIBXML_NONET );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		if ( ! $loaded || ! $dom->documentElement instanceof \DOMElement ) {
			return null;
		}

		$svg = $dom->documentElement;
		$view_box = preg_split( '/[\s,]+/', trim( $svg->getAttribute( 'viewBox' ) ) );
		if ( is_array( $view_box ) && count( $view_box ) >= 4 && (float) $view_box[2] > 0.0 && (float) $view_box[3] > 0.0 ) {
			return [ (float) $view_box[2], (float) $view_box[3] ];
		}

		$width  = (float) $svg->getAttribute( 'width' );
		$height = (float) $svg->getAttribute( 'height' );
		return $width > 0.0 && $height > 0.0 ? [ $width, $height ] : null;
	}

	/** Draw only non-transparent artwork pixels using the currently selected spot fill. */
	protected static function render_artwork_spot_mask( \TCPDF $pdf, string $path, float $x_mm, float $y_mm, float $w_mm, float $h_mm ): void {
		if ( 'svg' === strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) ) {
			$image = self::rasterise_svg_spot_mask( $path, $w_mm, $h_mm );
		} else {
			$image = self::open_raster_resource( $path );
			if ( $image ) {
				$image = self::bounded_gd_resource( $image, self::MAX_SPOT_MASK_DIMENSION, self::MAX_SPOT_MASK_DIMENSION * self::MAX_SPOT_MASK_DIMENSION );
			}
		}
		if ( ! $image ) {
			throw new \RuntimeException( sprintf( __( 'Could not build a white-ink mask from %s.', 'overcustomise' ), basename( $path ) ) );
		}

		$width  = imagesx( $image );
		$height = imagesy( $image );
		$x_scale = $w_mm / max( 1, $width );
		$y_scale = $h_mm / max( 1, $height );
		$runs    = 0;
		for ( $row = 0; $row < $height; $row++ ) {
			$run_start = -1;
			for ( $column = 0; $column < $width; $column++ ) {
				$rgba    = imagecolorat( $image, $column, $row );
				$visible = ( ( $rgba >> 24 ) & 0x7F ) < 120;
				if ( $visible && $run_start < 0 ) {
					$run_start = $column;
				} elseif ( ! $visible && $run_start >= 0 ) {
					if ( ++$runs > self::MAX_SPOT_MASK_RUNS ) {
						imagedestroy( $image );
						throw new \RuntimeException( __( 'The white-ink mask is too complex to render safely.', 'overcustomise' ) );
					}
					$pdf->Rect( $x_mm + $run_start * $x_scale, $y_mm + $row * $y_scale, ( $column - $run_start ) * $x_scale, $y_scale, 'F' );
					$run_start = -1;
				}
			}
			if ( $run_start >= 0 ) {
				if ( ++$runs > self::MAX_SPOT_MASK_RUNS ) {
					imagedestroy( $image );
					throw new \RuntimeException( __( 'The white-ink mask is too complex to render safely.', 'overcustomise' ) );
				}
				$pdf->Rect( $x_mm + $run_start * $x_scale, $y_mm + $row * $y_scale, ( $width - $run_start ) * $x_scale, $y_scale, 'F' );
			}
		}

		imagedestroy( $image );
	}

	/** Rasterise SVG alpha only at a bounded white-plate working resolution. */
	private static function rasterise_svg_spot_mask( string $path, float $w_mm, float $h_mm ) {
		if ( ! class_exists( '\Imagick' ) || ! function_exists( 'imagecreatefromstring' ) || filesize( $path ) > self::MAX_SVG_BYTES ) {
			return false;
		}
		$ratio = max( 0.01, $w_mm ) / max( 0.01, $h_mm );
		$width = $ratio >= 1.0 ? self::MAX_SPOT_MASK_DIMENSION : max( 1, (int) round( self::MAX_SPOT_MASK_DIMENSION * $ratio ) );
		$height = $ratio >= 1.0 ? max( 1, (int) round( self::MAX_SPOT_MASK_DIMENSION / $ratio ) ) : self::MAX_SPOT_MASK_DIMENSION;

		try {
			$imagick = new \Imagick();
			self::configure_imagick_limits( $imagick );
			$imagick->setBackgroundColor( new \ImagickPixel( 'transparent' ) );
			$imagick->readImage( $path );
			$imagick->setImageAlphaChannel( \Imagick::ALPHACHANNEL_ACTIVATE );
			$imagick->setImageFormat( 'png32' );
			$imagick->resizeImage( $width, $height, \Imagick::FILTER_LANCZOS, 1, true );
			$blob = $imagick->getImageBlob();
			$imagick->clear();
			$imagick->destroy();

			return is_string( $blob ) && '' !== $blob ? @imagecreatefromstring( $blob ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		} catch ( \Throwable $e ) {
			OC_Logger::warning( 'SVG white-ink mask conversion failed: ' . $e->getMessage() );
			return false;
		}
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

		$temp = self::temp_path_with_extension( 'oc-colour-clipart-' . wp_generate_uuid4() . '.svg', 'svg' );
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

		$temp = self::temp_path_with_extension( 'oc-black-clipart-' . wp_generate_uuid4() . '.png', 'png' );
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
		self::assert_safe_raster_dimensions( $path );
		$ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		$image = match ( $ext ) {
			'jpg', 'jpeg' => @imagecreatefromjpeg( $path ), // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			'png' => @imagecreatefrompng( $path ), // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			'webp' => function_exists( 'imagecreatefromwebp' ) ? @imagecreatefromwebp( $path ) : false, // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			'bmp' => function_exists( 'imagecreatefrombmp' ) ? @imagecreatefrombmp( $path ) : false, // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			'gif' => @imagecreatefromgif( $path ), // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			default => false,
		};
		if ( ! $image ) {
			return false;
		}

		return self::bounded_gd_resource( $image );
	}

	/** Downsample expensive per-pixel work while preserving transparency and aspect ratio. */
	protected static function bounded_gd_resource( $image, int $max_dimension = self::MAX_WORK_RASTER_DIMENSION, int $max_pixels = self::MAX_WORK_RASTER_PIXELS ) {
		if ( function_exists( 'imageistruecolor' ) && ! imageistruecolor( $image ) && function_exists( 'imagepalettetotruecolor' ) ) {
			imagepalettetotruecolor( $image );
			imagealphablending( $image, false );
			imagesavealpha( $image, true );
		}
		$width  = imagesx( $image );
		$height = imagesy( $image );
		[ $target_w, $target_h ] = self::bounded_work_dimensions( $width, $height, $max_dimension, $max_pixels );
		if ( $target_w === $width && $target_h === $height ) {
			return $image;
		}

		$resized = imagecreatetruecolor( $target_w, $target_h );
		imagealphablending( $resized, false );
		imagesavealpha( $resized, true );
		$transparent = imagecolorallocatealpha( $resized, 0, 0, 0, 127 );
		imagefilledrectangle( $resized, 0, 0, $target_w, $target_h, $transparent );
		imagecopyresampled( $resized, $image, 0, 0, 0, 0, $target_w, $target_h, $width, $height );
		imagedestroy( $image );

		return $resized;
	}

	protected static function build_filtered_image( string $path, array $layer, array $input ): ?string {
		$filter_id = absint( $input['imageFilterId'] ?? 0 );
		if ( ! $filter_id ) {
			return null;
		}

		$settings    = is_array( $layer['settings'] ?? null ) ? $layer['settings'] : [];
		$allowed_ids = array_values( array_filter( array_map( 'absint', is_array( $settings['image_filter_ids'] ?? null ) ? $settings['image_filter_ids'] : [] ) ) );
		if ( ! in_array( $filter_id, $allowed_ids, true ) ) {
			return null;
		}

		$key   = sanitize_key( (string) ( $input['imageFilterKey'] ?? '' ) );
		$value = is_numeric( $input['imageFilterValue'] ?? null ) ? (float) $input['imageFilterValue'] : 0.0;
		if ( '' === $key ) {
			$filter = null;
			foreach ( OC_DB::get_image_filters( true ) as $candidate ) {
				if ( (int) $candidate->id === $filter_id ) {
					$filter = $candidate;
					break;
				}
			}
			if ( ! $filter ) {
				return null;
			}
			$key   = sanitize_key( (string) $filter->filter_key );
			$value = (float) $filter->value;
		}
		$colour = ! empty( $settings['enable_image_colour'] )
			? sanitize_hex_color( (string) ( $input['colorHex'] ?? $settings['default_color'] ?? '' ) )
			: null;
		if ( 'ai' === $key && ! $colour ) {
			return $path;
		}
		if ( ! function_exists( 'imagefilter' ) ) {
			return null;
		}

		$src = self::open_raster_resource( $path );
		if ( ! $src ) {
			return null;
		}
		$src = self::bounded_gd_resource( $src, 2048, 4000000 );
		imagealphablending( $src, false );
		imagesavealpha( $src, true );

		$ok    = match ( $key ) {
			'ai'         => true,
			'grayscale'  => imagefilter( $src, IMG_FILTER_GRAYSCALE ),
			'sepia'      => imagefilter( $src, IMG_FILTER_GRAYSCALE ) && imagefilter( $src, IMG_FILTER_COLORIZE, 90, 45, 0 ),
			'brightness' => imagefilter( $src, IMG_FILTER_BRIGHTNESS, max( -255, min( 255, (int) round( $value * 255 ) ) ) ),
			'contrast'   => imagefilter( $src, IMG_FILTER_CONTRAST, max( -100, min( 100, (int) round( -100 * $value ) ) ) ),
			'saturation' => self::adjust_raster_saturation( $src, $value ),
			'hue'        => self::adjust_raster_hue( $src, $value ),
			default      => false,
		};

		if ( ! $ok ) {
			imagedestroy( $src );
			return null;
		}
		if ( $colour ) {
			self::recolour_raster_pixels( $src, $colour );
		}

		$temp = self::temp_path_with_extension( 'oc-filtered-image-' . wp_generate_uuid4() . '.png', 'png' );
		if ( ! is_string( $temp ) || '' === $temp ) {
			imagedestroy( $src );
			return null;
		}

		$result = imagepng( $src, $temp );
		imagedestroy( $src );
		if ( ! $result ) {
			@unlink( $temp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return null;
		}

		return $temp;
	}

	/** Replace visible filtered pixels with one production colour while retaining alpha. */
	private static function recolour_raster_pixels( $img, string $hex ): void {
		$rgb = sscanf( ltrim( $hex, '#' ), '%02x%02x%02x' );
		if ( ! is_array( $rgb ) || 3 !== count( $rgb ) ) {
			return;
		}
		$w = imagesx( $img );
		$h = imagesy( $img );
		$colours = [];
		for ( $y = 0; $y < $h; $y++ ) {
			for ( $x = 0; $x < $w; $x++ ) {
				$rgba = imagecolorat( $img, $x, $y );
				$alpha = ( $rgba >> 24 ) & 0x7F;
				$colours[ $alpha ] ??= imagecolorallocatealpha( $img, $rgb[0], $rgb[1], $rgb[2], $alpha );
				imagesetpixel( $img, $x, $y, $colours[ $alpha ] );
			}
		}
	}

	private static function adjust_raster_saturation( $img, float $amount ): bool {
		$w      = imagesx( $img );
		$h      = imagesy( $img );
		$factor = max( 0.0, 1.0 + $amount );
		for ( $y = 0; $y < $h; $y++ ) {
			for ( $x = 0; $x < $w; $x++ ) {
				$rgba  = imagecolorat( $img, $x, $y );
				$a     = ( $rgba >> 24 ) & 0x7F;
				$r     = ( $rgba >> 16 ) & 0xFF;
				$g     = ( $rgba >> 8 ) & 0xFF;
				$b     = $rgba & 0xFF;
				$gray  = ( $r + $g + $b ) / 3;
				$color = imagecolorallocatealpha(
					$img,
					self::clamp_rgb( $gray + ( $r - $gray ) * $factor ),
					self::clamp_rgb( $gray + ( $g - $gray ) * $factor ),
					self::clamp_rgb( $gray + ( $b - $gray ) * $factor ),
					$a
				);
				imagesetpixel( $img, $x, $y, $color );
			}
		}
		return true;
	}

	private static function adjust_raster_hue( $img, float $amount ): bool {
		$w = imagesx( $img );
		$h = imagesy( $img );
		$angle = $amount * 2 * M_PI;
		$cos = cos( $angle );
		$sin = sin( $angle );
		for ( $y = 0; $y < $h; $y++ ) {
			for ( $x = 0; $x < $w; $x++ ) {
				$rgba = imagecolorat( $img, $x, $y );
				$a    = ( $rgba >> 24 ) & 0x7F;
				$r    = ( $rgba >> 16 ) & 0xFF;
				$g    = ( $rgba >> 8 ) & 0xFF;
				$b    = $rgba & 0xFF;
				$new_r = ( .213 + $cos * .787 - $sin * .213 ) * $r + ( .715 - $cos * .715 - $sin * .715 ) * $g + ( .072 - $cos * .072 + $sin * .928 ) * $b;
				$new_g = ( .213 - $cos * .213 + $sin * .143 ) * $r + ( .715 + $cos * .285 + $sin * .140 ) * $g + ( .072 - $cos * .072 - $sin * .283 ) * $b;
				$new_b = ( .213 - $cos * .213 - $sin * .787 ) * $r + ( .715 - $cos * .715 + $sin * .715 ) * $g + ( .072 + $cos * .928 + $sin * .072 ) * $b;
				$color = imagecolorallocatealpha( $img, self::clamp_rgb( $new_r ), self::clamp_rgb( $new_g ), self::clamp_rgb( $new_b ), $a );
				imagesetpixel( $img, $x, $y, $color );
			}
		}
		return true;
	}

	private static function clamp_rgb( float $value ): int {
		return max( 0, min( 255, (int) round( $value ) ) );
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
