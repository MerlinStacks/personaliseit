<?php
/**
 * Unit tests for OC_Print_Base utility methods.
 *
 * These are pure-PHP calculations, no WP or TCPDF required.
 *
 * @package OverCustomise
 */

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

if ( ! class_exists( 'WC_Order' ) ) {
	class WC_Order {
		public function get_id(): int {
			return 1234;
		}

		public function get_order_number(): string {
			return '1234';
		}
	}
}

if ( ! class_exists( 'OC_Test_Vector_SVG_PDF' ) && class_exists( 'TCPDF' ) ) {
	class OC_Test_Vector_SVG_PDF extends TCPDF {
		public bool $image_called = false;
		public bool $image_svg_called = false;
		public array $image_svg_args = [];

		public function Image( $file, $x = '', $y = '', $w = 0, $h = 0, $type = '', $link = '', $align = '', $resize = false, $dpi = 300, $palign = '', $ismask = false, $imgmask = false, $border = 0, $fitbox = false, $hidden = false, $fitonpage = false, $alt = false, $altimgs = [] ) {
			$this->image_called = true;
		}

		public function ImageSVG( $file, $x = '', $y = '', $w = 0, $h = 0, $link = '', $align = '', $palign = '', $border = 0, $fitonpage = false ) {
			$this->image_svg_called = true;
			$this->image_svg_args = func_get_args();
		}
	}
}

if ( ! class_exists( 'OC_Test_Layer_Rotation_PDF' ) && class_exists( 'TCPDF' ) ) {
	class OC_Test_Layer_Rotation_PDF extends TCPDF {
		public array $rotations = [];

		public function StartTransform() {}
		public function StopTransform() {}
		public function Rotate( $_angle, $_x = null, $_y = null ) {
			$this->rotations[] = [ (float) $_angle, (float) $_x, (float) $_y ];
		}
		public function SetFillColorArray( $color, $_ret = false ) {}
		public function Rect( $_x, $_y, $_w, $_h, $_style = '', $_border_style = [], $_fill_color = [] ) {}
	}
}

if ( ! class_exists( 'OC_Test_Text_Cell_PDF' ) && class_exists( 'TCPDF' ) ) {
	class OC_Test_Text_Cell_PDF extends TCPDF {
		public array $cell_args = [];

		public function StartTransform() {}
		public function StopTransform() {}
		public function Rect( $_x, $_y, $_w, $_h, $_style = '', $_border_style = [], $_fill_color = [] ) {}
		public function SetXY( $_x, $_y, $_rtloff = false ) {}
		public function Cell( $_w, $_h = 0, $_txt = '', $_border = 0, $_ln = 0, $_align = '', $_fill = false, $_link = '', $_stretch = 0, $_ignore_min_height = false, $_calign = 'T', $_valign = 'M' ) {
			$this->cell_args = func_get_args();
		}
	}
}

/**
 * Concrete subclass to expose the protected static methods for testing.
 */
class OC_Print_Base_Testable extends OC_Print_Base {
	public static function test_px_to_mm( int $px ): float {
		return self::px_to_mm( $px );
	}

	public static function test_hex_to_cmyk( string $hex ): array {
		return self::hex_to_cmyk( $hex );
	}

	public static function test_hex_to_rgb( string $hex ): array {
		return self::hex_to_rgb( $hex );
	}

	public static function test_cell_h( float $size ): float {
		return self::cell_h( $size );
	}

	public static function test_build_filename( \WC_Order $order, int $item_id, object $area, string $ext ): string {
		return self::build_filename( $order, $item_id, $area, $ext );
	}

	public static function test_extract_spotify_uri( string $input ): string {
		return self::extract_spotify_uri( $input );
	}

	public static function test_build_spotify_code_url( string $input, bool $engraving = false ): string {
		return self::build_spotify_code_url( $input, $engraving );
	}

	public static function test_resolve_artwork_path( array $area_data ): ?string {
		return self::resolve_artwork_path( $area_data );
	}

	public static function test_normalise_engraving_text( string $text ): string {
		return self::normalise_engraving_text( $text );
	}

	public static function test_normalise_rotated_artboard_for_print( object $area, array $area_data ): array {
		return self::normalise_rotated_artboard_for_print( $area, $area_data );
	}

	public static function test_combined_sheet_layout( array $areas, float $inset = 0.0, float $gap = 5.0 ): array {
		return self::combined_sheet_layout( $areas, $inset, $gap );
	}

	public static function test_make_pdf( float $w_mm, float $h_mm, float $bleed = 0.0 ): \TCPDF {
		return self::make_pdf( $w_mm, $h_mm, $bleed );
	}

	public static function test_resolve_font( int $font_id ): string {
		return self::resolve_font( $font_id );
	}

	public static function test_has_vector_snapshot_payload( array $area_data ): bool {
		return self::has_vector_snapshot_payload( $area_data );
	}

	public static function test_render_vector_snapshot_payload( \TCPDF $pdf, array $area_data, float $x_mm, float $y_mm, float $w_mm, float $h_mm ): bool {
		return self::render_vector_snapshot_payload( $pdf, $area_data, $x_mm, $y_mm, $w_mm, $h_mm );
	}

	public static function test_render_layer_payload( \TCPDF $pdf, object $area, array $area_data ): void {
		self::render_layer_payload( $pdf, $area, $area_data, 0.0, 0.0 );
	}

	public static function test_build_filtered_image( string $path, array $layer, array $input ): ?string {
		return self::build_filtered_image( $path, $layer, $input );
	}

	public static function test_ghostscript_outline_command( string $binary, string $source, string $output ): array {
		return self::ghostscript_outline_command( $binary, $source, $output );
	}

	public static function test_outline_pdf_text( string $raw, string $binary ): string {
		return self::outline_pdf_text( $raw, $binary );
	}

	public static function test_draw_clipped_text_cell( \TCPDF $pdf, string $text ): void {
		self::draw_clipped_text_cell( $pdf, 0.0, 0.0, 20.0, 10.0, $text, 5.0 );
	}

	public static function test_browser_rendered_text_lines( array $input, string $text ): ?array {
		return self::browser_rendered_text_lines( $input, $text );
	}

	public static function test_browser_rendered_font_size( array $input, float $configured_size ): ?float {
		return self::browser_rendered_font_size( $input, $configured_size );
	}
}

class Test_Print_Base extends TestCase {

	#[Test]
	public function browser_rendered_text_lines_preserve_preview_wrapping(): void {
		$lines = [ 'Happy birthday dad, I love you', '- Levi' ];

		$this->assertSame(
			$lines,
			OC_Print_Base_Testable::test_browser_rendered_text_lines(
				[ 'renderedLines' => $lines ],
				'Happy birthday dad, I love you - Levi'
			)
		);
	}

	#[Test]
	public function browser_rendered_text_lines_preserve_grapheme_wrapping(): void {
		$lines = [ 'personali', 'sation' ];

		$this->assertSame(
			$lines,
			OC_Print_Base_Testable::test_browser_rendered_text_lines(
				[ 'renderedLines' => $lines ],
				'personalisation'
			)
		);
	}

	#[Test]
	public function browser_rendered_text_lines_reject_relocated_spaces(): void {
		$this->assertNull(
			OC_Print_Base_Testable::test_browser_rendered_text_lines(
				[ 'renderedLines' => [ 'foob', 'ar' ] ],
				'foo bar'
			)
		);
	}

	#[Test]
	public function browser_rendered_text_lines_reject_changed_text(): void {
		$this->assertNull(
			OC_Print_Base_Testable::test_browser_rendered_text_lines(
				[ 'renderedLines' => [ 'Different customer text' ] ],
				'Original customer text'
			)
		);
	}

	#[Test]
	public function browser_rendered_font_size_preserves_preview_autofit(): void {
		$this->assertSame(
			18.25,
			OC_Print_Base_Testable::test_browser_rendered_font_size( [ 'renderedFontSize' => 18.25 ], 24.0 )
		);
	}

	#[Test]
	public function browser_rendered_font_size_cannot_enlarge_configured_text(): void {
		$this->assertNull(
			OC_Print_Base_Testable::test_browser_rendered_font_size( [ 'renderedFontSize' => 30 ], 24.0 )
		);
	}

	// ── px_to_mm ──────────────────────────────────────────────────────────

	/** @return array<array{int, float}> */
	public static function px_to_mm_provider(): array {
		return [
			'zero'        => [ 0,    0.0 ],
			'1 inch'      => [ 300,  25.4 ],
			'half inch'   => [ 150,  12.7 ],
			'100mm'       => [ 1181, 100.0 ],   // ~100mm at 300 DPI
		];
	}

	#[Test]
	#[DataProvider( 'px_to_mm_provider' )]
	public function it_converts_pixels_to_mm( int $px, float $expected_mm ): void {
		$result = OC_Print_Base_Testable::test_px_to_mm( $px );
		$this->assertEqualsWithDelta( $expected_mm, $result, 0.1 );
	}

	#[Test]
	public function px_to_mm_uses_300_dpi(): void {
		// 300 px at 300 DPI = 1 inch = 25.4 mm.
		$this->assertEqualsWithDelta( 25.4, OC_Print_Base_Testable::test_px_to_mm( 300 ), 0.001 );
	}

	// ── hex_to_rgb ────────────────────────────────────────────────────────

	/** @return array<array{string, array{int,int,int}}> */
	public static function hex_to_rgb_provider(): array {
		return [
			'black'      => [ '#000000', [ 0,   0,   0   ] ],
			'white'      => [ '#ffffff', [ 255, 255, 255 ] ],
			'red'        => [ '#ff0000', [ 255, 0,   0   ] ],
			'blue'       => [ '#0000ff', [ 0,   0,   255 ] ],
			'no hash'    => [ 'ff0000',  [ 255, 0,   0   ] ],
			'shorthand'  => [ '#f00',    [ 255, 0,   0   ] ],
		];
	}

	#[Test]
	#[DataProvider( 'hex_to_rgb_provider' )]
	public function it_converts_hex_to_rgb( string $hex, array $expected ): void {
		$result = OC_Print_Base_Testable::test_hex_to_rgb( $hex );
		$this->assertSame( $expected, $result );
	}

	// ── hex_to_cmyk ───────────────────────────────────────────────────────

	#[Test]
	public function black_is_100_percent_key(): void {
		$cmyk = OC_Print_Base_Testable::test_hex_to_cmyk( '#000000' );
		$this->assertEqualsWithDelta( 0.0,   $cmyk[0], 0.1 ); // C
		$this->assertEqualsWithDelta( 0.0,   $cmyk[1], 0.1 ); // M
		$this->assertEqualsWithDelta( 0.0,   $cmyk[2], 0.1 ); // Y
		$this->assertEqualsWithDelta( 100.0, $cmyk[3], 0.1 ); // K
	}

	#[Test]
	public function white_is_all_zero_cmyk(): void {
		$cmyk = OC_Print_Base_Testable::test_hex_to_cmyk( '#ffffff' );
		foreach ( $cmyk as $channel ) {
			$this->assertEqualsWithDelta( 0.0, $channel, 0.1 );
		}
	}

	#[Test]
	public function red_has_zero_cyan(): void {
		$cmyk = OC_Print_Base_Testable::test_hex_to_cmyk( '#ff0000' );
		$this->assertEqualsWithDelta( 0.0, $cmyk[0], 0.1 ); // C = 0
		$this->assertGreaterThan( 0.0, $cmyk[1] );          // M > 0
	}

	#[Test]
	public function cmyk_channels_are_0_to_100(): void {
		$colors = [ '#ff5733', '#1abc9c', '#3498db', '#9b59b6', '#f1c40f' ];
		foreach ( $colors as $hex ) {
			$cmyk = OC_Print_Base_Testable::test_hex_to_cmyk( $hex );
			$this->assertCount( 4, $cmyk );
			foreach ( $cmyk as $channel ) {
				$this->assertGreaterThanOrEqual( 0.0, $channel );
				$this->assertLessThanOrEqual( 100.0, $channel );
			}
		}
	}

	#[Test]
	public function shorthand_hex_works_in_cmyk(): void {
		$full      = OC_Print_Base_Testable::test_hex_to_cmyk( '#ff0000' );
		$shorthand = OC_Print_Base_Testable::test_hex_to_cmyk( '#f00' );
		foreach ( range( 0, 3 ) as $i ) {
			$this->assertEqualsWithDelta( $full[ $i ], $shorthand[ $i ], 0.01 );
		}
	}

	// ── cell_h ────────────────────────────────────────────────────────────

	#[Test]
	public function cell_h_increases_with_font_size(): void {
		$small = OC_Print_Base_Testable::test_cell_h( 8.0 );
		$large = OC_Print_Base_Testable::test_cell_h( 24.0 );
		$this->assertGreaterThan( $small, $large );
	}

	#[Test]
	public function cell_h_is_positive(): void {
		$this->assertGreaterThan( 0.0, OC_Print_Base_Testable::test_cell_h( 10.0 ) );
	}

	#[Test]
	public function production_pdf_command_outlines_fonts_without_rasterising(): void {
		$command = OC_Print_Base_Testable::test_ghostscript_outline_command( 'gs', '/tmp/source.pdf', '/tmp/output.pdf' );

		$this->assertContains( '-sDEVICE=pdfwrite', $command );
		$this->assertContains( '-dNoOutputFonts', $command );
		$this->assertNotContains( '-sDEVICE=png16m', $command );
		$this->assertSame( '/tmp/source.pdf', $command[ count( $command ) - 1 ] );
	}

	#[Test]
	public function production_pdf_retains_embedded_fonts_without_ghostscript(): void {
		$raw = "%PDF-1.7\nembedded-font-pdf";

		$this->assertSame( $raw, OC_Print_Base_Testable::test_outline_pdf_text( $raw, '' ) );
	}

	#[Test]
	public function single_line_pdf_text_scales_to_fit_instead_of_wrapping(): void {
		if ( ! class_exists( 'OC_Test_Text_Cell_PDF' ) ) {
			$this->markTestSkipped( 'TCPDF is not available.' );
		}

		$pdf = ( new ReflectionClass( OC_Test_Text_Cell_PDF::class ) )->newInstanceWithoutConstructor();
		OC_Print_Base_Testable::test_draw_clipped_text_cell( $pdf, 'DR ADELLINE CARDON' );

		$this->assertSame( 1, $pdf->cell_args[8] );
	}

	#[Test]
	public function rotated_layer_payload_uses_swapped_flat_artboard_dimensions(): void {
		$area = (object) [
			'canvas_unit'     => 'mm',
			'canvas_w'        => 40,
			'canvas_h'        => 120,
			'canvas_rotation' => 90,
		];
		$area_data = [
			'bounds' => [ 'w' => 40, 'h' => 120, 'rotation' => 90 ],
			'layers' => [ [ 'type' => 'image', 'x' => 0, 'y' => 0, 'w' => 40, 'h' => 120 ] ],
		];

		[ $flat_area, $w_mm, $h_mm ] = OC_Print_Base_Testable::test_normalise_rotated_artboard_for_print( $area, $area_data );

		$this->assertSame( 120.0, $w_mm );
		$this->assertSame( 40.0, $h_mm );
		$this->assertSame( 120.0, $flat_area->canvas_w );
		$this->assertSame( 40.0, $flat_area->canvas_h );
		$this->assertSame( 0, $flat_area->canvas_rotation );
	}

	#[Test]
	public function combined_sheet_layout_places_areas_without_overlap(): void {
		$areas = [
			[
				'area'      => (object) [ 'canvas_unit' => 'mm', 'canvas_w' => 40, 'canvas_h' => 20 ],
				'area_data' => [],
			],
			[
				'area'      => (object) [ 'canvas_unit' => 'mm', 'canvas_w' => 30, 'canvas_h' => 50 ],
				'area_data' => [],
			],
		];

		$layout = OC_Print_Base_Testable::test_combined_sheet_layout( $areas, 3.0, 5.0 );

		$this->assertCount( 2, $layout['entries'] );
		$this->assertSame( 3.0, $layout['entries'][0]['x'] );
		$this->assertSame( 54.0, $layout['entries'][1]['x'] );
		$this->assertGreaterThan( $layout['entries'][0]['x'] + $layout['entries'][0]['w'], $layout['entries'][1]['x'] );
		$this->assertSame( 87.0, $layout['page_w'] );
		$this->assertSame( 56.0, $layout['page_h'] );
	}

	#[Test]
	public function fabric_clockwise_layer_rotation_is_inverted_for_tcpdf(): void {
		if ( ! class_exists( 'OC_Test_Layer_Rotation_PDF' ) ) {
			$this->markTestSkipped( 'TCPDF is not available.' );
		}

		$pdf = ( new ReflectionClass( OC_Test_Layer_Rotation_PDF::class ) )->newInstanceWithoutConstructor();
		$area = (object) [
			'canvas_unit' => 'mm',
			'canvas_x'    => 0,
			'canvas_y'    => 0,
			'canvas_w'    => 100,
			'canvas_h'    => 50,
		];
		$data = [
			'bounds' => [ 'x' => 0, 'y' => 0, 'w' => 100, 'h' => 50 ],
			'layers' => [
				[
					'type'     => 'lineart',
					'x'        => 10,
					'y'        => 10,
					'w'        => 20,
					'h'        => 10,
					'rotation' => 15,
					'input'    => [ 'colorHex' => '#000000' ],
				],
			],
		];

		OC_Print_Base_Testable::test_render_layer_payload( $pdf, $area, $data );

		$this->assertCount( 1, $pdf->rotations );
		$this->assertSame( -15.0, $pdf->rotations[0][0] );
		$this->assertSame( 20.0, $pdf->rotations[0][1] );
		$this->assertSame( 15.0, $pdf->rotations[0][2] );
	}

	#[Test]
	public function make_pdf_preserves_landscape_artboard_orientation(): void {
		if ( ! class_exists( 'TCPDF' ) ) {
			$this->markTestSkipped( 'TCPDF is not available.' );
		}

		try {
			$pdf = OC_Print_Base_Testable::test_make_pdf( 120.0, 40.0, 3.0 );
		} catch ( \RuntimeException $e ) {
			if ( str_contains( $e->getMessage(), 'TCPDF font assets are missing' ) ) {
				$this->markTestSkipped( $e->getMessage() );
			}
			throw $e;
		}

		$this->assertGreaterThan( $pdf->getPageHeight(), $pdf->getPageWidth() );
		$this->assertEqualsWithDelta( 126.0, $pdf->getPageWidth(), 0.001 );
		$this->assertEqualsWithDelta( 46.0, $pdf->getPageHeight(), 0.001 );
	}

	#[Test]
	public function woff2_font_failure_explains_how_to_prepare_it_for_print(): void {
		global $wpdb;
		$previous_wpdb = $wpdb ?? null;
		$font_dir      = trailingslashit( wp_upload_dir()['basedir'] ) . 'overcustomise/fonts';
		wp_mkdir_p( $font_dir );
		$font_path = $font_dir . '/web-font.woff2';
		// Direct fixture creation is appropriate in this isolated filesystem test.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $font_path, 'wOF2' );

		$wpdb = new class() {
			public string $prefix = 'wp_';

			public function prepare( string $query, int $font_id ): string {
				return $query . ' -- ' . $font_id;
			}

			public function get_row( string $query ): object {
				return (object) [
					'id'        => 33,
					'file_path' => 'overcustomise/fonts/web-font.woff2',
				];
			}
		};

		try {
			$this->expectException( \RuntimeException::class );
			$this->expectExceptionMessage( 'Convert it for print in OverCustomise > Fonts' );
			OC_Print_Base_Testable::test_resolve_font( 33 );
		} finally {
			if ( file_exists( $font_path ) ) {
				// Direct fixture cleanup is appropriate in this isolated filesystem test.
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				unlink( $font_path );
			}
			$wpdb = $previous_wpdb;
		}
	}

	#[Test]
	public function viewbox_only_svg_gets_intrinsic_size_for_tcpdf_vector_rendering(): void {
		$path = tempnam( sys_get_temp_dir(), 'oc-svg-' );
		file_put_contents( $path, '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 256"><path d="M0 0h512v256H0z"/></svg>' );

		$method = new ReflectionMethod( OC_Print_Base::class, 'normalise_svg_intrinsic_size_for_tcpdf' );
		$method->setAccessible( true );

		try {
			$normalised = $method->invoke( null, $path );

			$this->assertIsString( $normalised );
			$this->assertFileExists( $normalised );
			$svg = file_get_contents( $normalised );
			$this->assertStringContainsString( 'width="512.0000"', $svg );
			$this->assertStringContainsString( 'height="256.0000"', $svg );
		} finally {
			@unlink( $path );
			if ( isset( $normalised ) && is_string( $normalised ) ) {
				@unlink( $normalised );
			}
		}
	}

	#[Test]
	public function single_quoted_svg_size_is_rewritten_for_tcpdf_vector_rendering(): void {
		$path = tempnam( sys_get_temp_dir(), 'oc-svg-' );
		file_put_contents( $path, "<svg xmlns='http://www.w3.org/2000/svg' width='512' height='256' viewBox='0 0 512 256'><path d='M0 0h512v256H0z'/></svg>" );

		$method = new ReflectionMethod( OC_Print_Base::class, 'normalise_svg_intrinsic_size_for_tcpdf' );
		$method->setAccessible( true );

		try {
			$normalised = $method->invoke( null, $path );

			$this->assertIsString( $normalised );
			$this->assertFileExists( $normalised );
			$svg = file_get_contents( $normalised );
			$this->assertStringContainsString( 'width="512.0000"', $svg );
			$this->assertStringContainsString( 'height="256.0000"', $svg );
		} finally {
			@unlink( $path );
			if ( isset( $normalised ) && is_string( $normalised ) ) {
				@unlink( $normalised );
			}
		}
	}

	#[Test]
	public function svg_css_presentation_styles_are_inlined_for_tcpdf_vector_rendering(): void {
		$path = tempnam( sys_get_temp_dir(), 'oc-svg-' );
		file_put_contents( $path, '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="10" viewBox="0 0 20 10"><style>.st0{fill:#78d5df;stroke:#123456;stroke-width:2}</style><path class="st0" d="M0 0h20v10H0z"/></svg>' );

		$method = new ReflectionMethod( OC_Print_Base::class, 'normalise_svg_intrinsic_size_for_tcpdf' );
		$method->setAccessible( true );

		try {
			$normalised = $method->invoke( null, $path );

			$this->assertIsString( $normalised );
			$this->assertFileExists( $normalised );
			$svg = file_get_contents( $normalised );
			$this->assertStringContainsString( 'fill="#78d5df"', $svg );
			$this->assertStringContainsString( 'stroke="#123456"', $svg );
			$this->assertStringContainsString( 'stroke-width="2"', $svg );
			$this->assertStringContainsString( '<style', $svg );
		} finally {
			@unlink( $path );
			if ( isset( $normalised ) && is_string( $normalised ) ) {
				@unlink( $normalised );
			}
		}
	}

	#[Test]
	public function illustrator_compact_path_decimals_are_normalised_for_tcpdf_vector_rendering(): void {
		$path = tempnam( sys_get_temp_dir(), 'oc-svg-' );
		file_put_contents( $path, '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="10" viewBox="0 0 20 10"><path d="M1,1l6.25,3.77-2.72.1h.01v-.02c-.09.03-.2-.04-.3-.5Z"/></svg>' );

		$method = new ReflectionMethod( OC_Print_Base::class, 'normalise_svg_intrinsic_size_for_tcpdf' );
		$method->setAccessible( true );

		try {
			$normalised = $method->invoke( null, $path );

			$this->assertIsString( $normalised );
			$svg = file_get_contents( $normalised );
			$this->assertStringContainsString( 'L 7.25 4.77 L 4.53 4.87', $svg );
			$this->assertStringContainsString( 'L 4.54 4.87 L 4.54 4.85', $svg );
			$this->assertStringContainsString( 'C 4.45 4.88 4.34 4.81 4.24 4.35 Z', $svg );
			$this->assertStringNotContainsString( 'L 1 1 Z', $svg );
		} finally {
			@unlink( $path );
			if ( isset( $normalised ) && is_string( $normalised ) ) {
				@unlink( $normalised );
			}
		}
	}

	#[Test]
	public function relative_curves_are_made_absolute_without_artificial_closing_lines(): void {
		$path = tempnam( sys_get_temp_dir(), 'oc-svg-' );
		file_put_contents( $path, '<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40"><path d="m10 10c5 0 5 10 10 10s5 10 10 0z"/></svg>' );
		$method = new ReflectionMethod( OC_Print_Base::class, 'normalise_svg_intrinsic_size_for_tcpdf' );
		$method->setAccessible( true );

		try {
			$normalised = $method->invoke( null, $path );
			$svg = file_get_contents( $normalised );

			$this->assertStringContainsString( 'M 10 10 C 15 10 15 20 20 20 C 25 20 25 30 30 20 Z', $svg );
			$this->assertStringNotContainsString( 'L 10 10 Z', $svg );
		} finally {
			@unlink( $path );
			if ( isset( $normalised ) && is_string( $normalised ) ) {
				@unlink( $normalised );
			}
		}
	}

	#[Test]
	public function svg_print_artwork_uses_tcpdf_vector_renderer_before_raster_fallback(): void {
		if ( ! class_exists( 'TCPDF' ) ) {
			$this->markTestSkipped( 'TCPDF is not available.' );
		}

		$path = tempnam( sys_get_temp_dir(), 'oc-svg-' );
		file_put_contents( $path, '<svg xmlns="http://www.w3.org/2000/svg" width="512" height="256" viewBox="0 0 512 256"><path d="M0 0h512v256H0z"/></svg>' );
		$pdf = ( new ReflectionClass( OC_Test_Vector_SVG_PDF::class ) )->newInstanceWithoutConstructor();
		$method = new ReflectionMethod( OC_Print_Base::class, 'draw_pdf_svg' );
		$method->setAccessible( true );

		try {
			$method->invokeArgs( null, [ $pdf, $path, 0.0, 0.0, 50.0, 25.0 ] );

			$this->assertTrue( $pdf->image_svg_called );
			$this->assertFalse( $pdf->image_called );
		} finally {
			@unlink( $path );
		}
	}

	#[Test]
	public function vector_snapshot_payload_renders_through_tcpdf_svg_renderer(): void {
		if ( ! class_exists( 'TCPDF' ) ) {
			$this->markTestSkipped( 'TCPDF is not available.' );
		}

		$pdf = ( new ReflectionClass( OC_Test_Vector_SVG_PDF::class ) )->newInstanceWithoutConstructor();
		$data = [
			'snapshot' => [
				'format' => 'fabric-svg-v1',
				'svg'    => '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="50" viewBox="0 0 100 50"><path d="M0 0h100v50H0z" fill="#ff0000"/></svg>',
			],
		];

		$this->assertTrue( OC_Print_Base_Testable::test_has_vector_snapshot_payload( $data ) );
		$this->assertTrue( OC_Print_Base_Testable::test_render_vector_snapshot_payload( $pdf, $data, 3.0, 4.0, 50.0, 25.0 ) );
		$this->assertTrue( $pdf->image_svg_called );
		$this->assertFalse( $pdf->image_called );
		$this->assertSame( 3.0, $pdf->image_svg_args[1] );
		$this->assertSame( 4.0, $pdf->image_svg_args[2] );
		$this->assertSame( 50.0, $pdf->image_svg_args[3] );
		$this->assertSame( 25.0, $pdf->image_svg_args[4] );
	}

	#[Test]
	public function vector_snapshot_payload_rejects_unresolved_image_nodes(): void {
		$data = [
			'snapshot' => [
				'format' => 'fabric-svg-v1',
				'svg'    => '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="50"><image href="photo.png" width="100" height="50"/></svg>',
			],
		];

		$this->assertFalse( OC_Print_Base_Testable::test_has_vector_snapshot_payload( $data ) );
	}

	// ── build_filename ────────────────────────────────────────────────────

	#[Test]
	public function build_filename_uses_order_number_and_print_position(): void {
		$name = OC_Print_Base_Testable::test_build_filename( $this->mock_order( 1234 ), 42, (object) [ 'id' => 8 ], 'pdf' );

		$this->assertStringStartsWith( '1234-p1', $name );
		$this->assertStringEndsWith( '.pdf', $name );
	}

	#[Test]
	public function build_filename_sanitises_order_number(): void {
		$name = OC_Print_Base_Testable::test_build_filename( $this->mock_order( 'CK/1234' ), 1, (object) [ 'id' => 8 ], 'pdf' );

		$this->assertStringNotContainsString( '/', $name );
	}

	#[Test]
	public function build_filename_uses_second_print_file_position(): void {
		global $wpdb;
		$previous_wpdb = $wpdb ?? null;
		$wpdb = new class {
			public string $prefix = 'wp_';

			public function prepare( string $query, int $order_id ): string {
				return $query . ' -- ' . $order_id;
			}

			public function get_results( string $query ): array {
				return [
					(object) [ 'order_item_id' => 42, 'print_area_id' => 8 ],
					(object) [ 'order_item_id' => 43, 'print_area_id' => 9 ],
				];
			}
		};

		try {
			$name = OC_Print_Base_Testable::test_build_filename( $this->mock_order( 1234 ), 43, (object) [ 'id' => 9 ], 'pdf' );

			$this->assertSame( '1234-p2.pdf', $name );
		} finally {
			$wpdb = $previous_wpdb;
		}
	}

	private function mock_order( int|string $order_number ): \WC_Order {
		$order = $this->createMock( \WC_Order::class );
		$order->method( 'get_id' )->willReturn( 1234 );
		$order->method( 'get_order_number' )->willReturn( (string) $order_number );

		return $order;
	}

	// ── Spotify scannable codes ────────────────────────────────────────────

	#[Test]
	public function extracts_spotify_uri_from_uri(): void {
		$this->assertSame(
			'spotify:track:6rqhFgbbKwnb9MLmUQDhG6',
			OC_Print_Base_Testable::test_extract_spotify_uri( 'spotify:track:6rqhFgbbKwnb9MLmUQDhG6' )
		);
	}

	#[Test]
	public function extracts_spotify_uri_from_open_url(): void {
		$this->assertSame(
			'spotify:track:6rqhFgbbKwnb9MLmUQDhG6',
			OC_Print_Base_Testable::test_extract_spotify_uri( 'https://open.spotify.com/intl-en/track/6rqhFgbbKwnb9MLmUQDhG6?si=abc' )
		);
	}

	#[Test]
	public function builds_spotify_scannable_svg_url(): void {
		$url = OC_Print_Base_Testable::test_build_spotify_code_url( 'https://open.spotify.com/track/6rqhFgbbKwnb9MLmUQDhG6' );

		$this->assertSame(
			'https://scannables.scdn.co/uri/plain/svg/FFFFFF/black/640/spotify:track:6rqhFgbbKwnb9MLmUQDhG6',
			$url
		);
	}

	#[Test]
	public function rejects_non_spotify_urls_for_scannable_codes(): void {
		$this->assertSame( '', OC_Print_Base_Testable::test_build_spotify_code_url( 'https://example.com/track/6rqhFgbbKwnb9MLmUQDhG6' ) );
	}

	#[Test]
	public function engraving_text_removes_colour_emoji(): void {
		$result = OC_Print_Base_Testable::test_normalise_engraving_text( "Name \u{2764}\u{FE0F} \u{1F44D}\u{1F3FD} \u{1F600}" );

		$this->assertSame( 'Name   ', $result );
		$this->assertStringNotContainsString( "\u{FE0F}", $result );
		$this->assertStringNotContainsString( "\u{1F3FD}", $result );
	}

	#[Test]
	public function resolves_stale_absolute_attachment_path_inside_current_uploads(): void {
		global $oc_test_attached_files, $oc_test_post_meta;

		$dir = trailingslashit( wp_upload_dir()['basedir'] ) . 'overcustomise/artwork';
		wp_mkdir_p( $dir );
		$path = $dir . '/customer-upload.png';
		file_put_contents( $path, 'png' );

		$oc_test_attached_files = [
			123 => '/var/www/html/wp-content/uploads/overcustomise/artwork/customer-upload.png',
		];
		$oc_test_post_meta = [];

		try {
			$this->assertSame( realpath( $path ), OC_Print_Base_Testable::test_resolve_artwork_path( [
				'artworkAttachmentId' => 123,
			] ) );
		} finally {
			@unlink( $path );
			$oc_test_attached_files = [];
			$oc_test_post_meta = [];
		}
	}

	#[Test]
	public function resolves_attachment_meta_relative_upload_path(): void {
		global $oc_test_attached_files, $oc_test_post_meta;

		$dir = trailingslashit( wp_upload_dir()['basedir'] ) . 'overcustomise/artwork';
		wp_mkdir_p( $dir );
		$path = $dir . '/relative-upload.png';
		file_put_contents( $path, 'png' );

		$oc_test_attached_files = [ 456 => '/missing/path/relative-upload.png' ];
		$oc_test_post_meta = [
			456 => [ '_wp_attached_file' => 'overcustomise/artwork/relative-upload.png' ],
		];

		try {
			$this->assertSame( realpath( $path ), OC_Print_Base_Testable::test_resolve_artwork_path( [
				'artworkAttachmentId' => 456,
			] ) );
		} finally {
			@unlink( $path );
			$oc_test_attached_files = [];
			$oc_test_post_meta = [];
		}
	}

	#[Test]
	public function resolves_marked_customer_artwork_from_private_storage(): void {
		global $oc_test_attached_files, $oc_test_post_meta;

		$directory = OC_Upload_Handler::private_storage_path( 'artwork' );
		$this->assertIsString( $directory );
		$path = tempnam( $directory, 'oc-private-artwork-' );
		file_put_contents( $path, 'png' );

		$oc_test_attached_files = [ 789 => $path ];
		$oc_test_post_meta      = [ 789 => [ '_oc_artwork' => 1 ] ];

		try {
			$this->assertSame( realpath( $path ), OC_Print_Base_Testable::test_resolve_artwork_path( [
				'artworkAttachmentId' => 789,
			] ) );
		} finally {
			@unlink( $path );
			$oc_test_attached_files = [];
			$oc_test_post_meta = [];
		}
	}

	#[Test]
	public function filtered_image_can_be_recoloured_for_production(): void {
		if ( ! function_exists( 'imagecreatetruecolor' ) || ! function_exists( 'imagefilter' ) ) {
			$this->markTestSkipped( 'GD is not available.' );
		}

		$temp_source = tempnam( sys_get_temp_dir(), 'oc-filter-source-' );
		$source = $temp_source . '.png';
		rename( $temp_source, $source );
		$image  = imagecreatetruecolor( 2, 2 );
		imagealphablending( $image, false );
		imagesavealpha( $image, true );
		imagefilledrectangle( $image, 0, 0, 1, 1, imagecolorallocatealpha( $image, 120, 140, 160, 0 ) );
		imagepng( $image, $source );
		imagedestroy( $image );

		$result = null;
		try {
			$result = OC_Print_Base_Testable::test_build_filtered_image(
				$source,
				[ 'settings' => [ 'image_filter_ids' => [ 7 ], 'enable_image_colour' => true ] ],
				[ 'imageFilterId' => 7, 'imageFilterKey' => 'grayscale', 'colorHex' => '#336699' ]
			);
			$this->assertIsString( $result );
			$filtered = imagecreatefrompng( $result );
			$pixel = imagecolorat( $filtered, 0, 0 );
			$this->assertSame( 0x33, ( $pixel >> 16 ) & 0xFF );
			$this->assertSame( 0x66, ( $pixel >> 8 ) & 0xFF );
			$this->assertSame( 0x99, $pixel & 0xFF );
			imagedestroy( $filtered );
		} finally {
			@unlink( $source );
			if ( is_string( $result ) ) {
				@unlink( $result );
			}
		}
	}

	#[Test]
	public function image_crop_interpolates_between_contain_and_cover_after_filtering(): void {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			$this->markTestSkipped( 'GD is not available.' );
		}

		$temp_source = tempnam( sys_get_temp_dir(), 'oc-fit-source-' );
		$source = $temp_source . '.png';
		rename( $temp_source, $source );
		$image = imagecreatetruecolor( 200, 100 );
		imagepng( $image, $source );
		imagedestroy( $image );

		try {
			$method = new ReflectionMethod( OC_Print_Base::class, 'fit_artwork_box' );
			$this->assertEqualsWithDelta( [ 0.0, 25.0, 100.0, 50.0 ], $method->invoke( null, $source, 0.0, 0.0, 100.0, 100.0, 0.0 ), 0.001 );
			$this->assertEqualsWithDelta( [ -25.0, 12.5, 150.0, 75.0 ], $method->invoke( null, $source, 0.0, 0.0, 100.0, 100.0, 0.5 ), 0.001 );
			$this->assertEqualsWithDelta( [ -50.0, 0.0, 200.0, 100.0 ], $method->invoke( null, $source, 0.0, 0.0, 100.0, 100.0, 1.0 ), 0.001 );
		} finally {
			@unlink( $source );
		}
	}
}
