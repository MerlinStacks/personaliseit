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
		public function MultiCell( ...$args ) {
			$this->cell_args = $args;
			return 1;
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
	public function text_rendering_rejects_nonfinite_geometry_before_font_or_pdf_work(): void {
		if ( ! class_exists( 'TCPDF' ) ) {
			$this->markTestSkipped( 'TCPDF required.' );
		}
		$pdf = new TCPDF();
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Non-finite text geometry' );
		( new ReflectionMethod( OC_Print_Base::class, 'render_layer_text' ) )->invoke(
			null,
			$pdf,
			[
				'type' => 'text',
				'h'    => 20,
			],
			[ 'value' => 'Alex' ],
			[],
			INF,
			0.0,
			20.0,
			20.0,
			'engraving'
		);
	}

	#[Test]
	public function verified_layout_validation_is_atomic_and_matches_preview_limits(): void {
		$method = new ReflectionMethod( OC_Print_Base::class, 'browser_rendered_text_layout' );
		$input  = [
			'renderedLayoutVersion' => 1,
			'renderedFontSize'      => 2.5,
			'renderedScaleX'        => 0.75,
			'renderedInsetX'        => 0,
			'fontSize'              => 0,
		];
		$layer  = [
			'type' => 'text',
			'h'    => 10,
		];
		$this->assertSame( 2.5, $method->invoke( null, $input, $layer, [] )['renderedFontSize'] );
		foreach ( [
			'renderedLayoutVersion' => [ null, true, 2, '1junk' ],
			'renderedFontSize'      => [ 0, -1, INF, NAN, 7.3 ],
			'renderedScaleX'        => [ 0, -1, 1.01, INF, true ],
			'renderedInsetX'        => [ -0.1, 0.1, 0.5, INF ],
		] as $key => $values ) {
			foreach ( $values as $value ) {
				$this->assertNull( $method->invoke( null, array_replace( $input, [ $key => $value ] ), $layer, [] ), $key );
			}
			$partial = $input;
			unset( $partial[ $key ] );
			$this->assertNull( $method->invoke( null, $partial, $layer, [] ) );
		}
		$input['renderedFontSize'] = 3;
		$this->assertSame(
			3.0,
			$method->invoke(
				null,
				$input,
				$layer,
				[
					'min_font_size' => 8,
					'max_font_size' => 3,
				]
			)['renderedFontSize']
		);
		$input['renderedScaleX'] = 1;
		$input['renderedInsetX'] = 0.025;
		$input['value']          = "Alex\n\nBob";
		$input['renderedLines']  = [ 'Alex', '', 'Bob' ];
		$layer['type']           = 'textarea';
		$this->assertSame( 0.025, $method->invoke( null, $input, $layer, [] )['renderedInsetX'] );
		$this->assertSame( [ 'Alex', '', 'Bob' ], $method->invoke( null, $input, $layer, [] )['renderedLines'] );
		foreach ( [ null, [], [ 'Alex' ], [ 'different' ], [ [ 'Alex' ], 'Bob' ], array_fill( 0, 201, 'Alex' ) ] as $lines ) {
			$this->assertNull( $method->invoke( null, array_replace( $input, [ 'renderedLines' => $lines ] ), $layer, [] ) );
		}
		$input['renderedScaleX'] = 0.9;
		$this->assertNull( $method->invoke( null, $input, $layer, [] ) );
	}

	public static function nonengraving_text_modes(): array {
		return [
			'colour configured' => [ 'colour', 20 ],
			'spot configured'   => [ 'spot', 20 ],
			'colour auto'       => [ 'colour', 0 ],
			'spot auto'         => [ 'spot', 0 ],
		];
	}

	#[Test]
	#[DataProvider( 'nonengraving_text_modes' )]
	public function new_layout_fields_preserve_nonengraving_pdf_text_and_validated_size( string $mode, int $configured ): void {
		if ( ! class_exists( 'TCPDF' ) ) {
			$this->markTestSkipped( 'TCPDF required.' );
		}
		$pdf   = new OC_Test_Text_Cell_PDF();
		$input = [
			'value'                 => 'Alex',
			'fontSize'              => $configured,
			'renderedLayoutVersion' => 1,
			'renderedFontSize'      => 8,
			'renderedScaleX'        => 0.75,
			'renderedInsetX'        => 0,
			'colorHex'              => '#ff0000',
		];
		( new ReflectionMethod( OC_Print_Base::class, 'render_layer_text' ) )->invoke(
			null,
			$pdf,
			[
				'type' => 'text',
				'h'    => 40,
			],
			$input,
			[],
			0.0,
			0.0,
			100.0,
			40.0,
			$mode,
			1.0
		);
		$this->assertSame( 'Alex', $pdf->cell_args[2] );
		$this->assertEqualsWithDelta( 8.0, $pdf->getFontSizePt(), 0.00001 );
		$input['value']          = "Alex\nBob";
		$input['renderedLines']  = [ 'Alex', '', 'Bob' ];
		$input['renderedScaleX'] = 1;
		$input['renderedInsetX'] = 0.025;
		$method                  = new ReflectionMethod( OC_Print_Base::class, 'render_layer_text' );
		$method->invoke(
			null,
			$pdf,
			[
				'type' => 'textarea',
				'h'    => 40,
			],
			$input,
			[],
			0.0,
			0.0,
			100.0,
			40.0,
			$mode,
			1.0
		);
		$this->assertSame( "Alex\n\nBob", $pdf->cell_args[2] );
		$this->assertEqualsWithDelta( 8.0, $pdf->getFontSizePt(), 0.00001 );
		// Invalid geometry discards otherwise-valid lines too; invalid lines also
		// discard the captured size rather than partially accepting the bundle.
		$input['renderedScaleX'] = 0.75;
		$method->invoke(
			null,
			$pdf,
			[
				'type' => 'textarea',
				'h'    => 40,
			],
			$input,
			[],
			0.0,
			0.0,
			100.0,
			40.0,
			$mode,
			1.0
		);
		$this->assertSame( "Alex\nBob", $pdf->cell_args[2] );
		$input['renderedScaleX'] = 1;
		$input['renderedLines']  = [ 'Alex' ];
		$method->invoke(
			null,
			$pdf,
			[
				'type' => 'textarea',
				'h'    => 40,
			],
			$input,
			[],
			0.0,
			0.0,
			100.0,
			40.0,
			$mode,
			1.0
		);
		$this->assertSame( "Alex\nBob", $pdf->cell_args[2] );
		$this->assertGreaterThan( 8.0, $pdf->getFontSizePt() );
	}


	private static function raster_wrapper( string $uri, string $extra = '' ): string {
		return '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="2" height="1" viewBox="0 0 2 1" data-oc-converted="raster"><image width="2" height="1" href="' . $uri . '" xlink:href="' . $uri . '" ' . $extra . '/></svg>';
	}

	#[Test]
	public function embedded_clipart_is_extracted_and_blackened_with_alpha_preserved(): void {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			$this->markTestSkipped( 'GD is required.' );
		}
		$source = imagecreatetruecolor( 2, 1 );
		imagealphablending( $source, false );
		imagesavealpha( $source, true );
		imagesetpixel( $source, 0, 0, imagecolorallocatealpha( $source, 255, 0, 0, 32 ) );
		imagesetpixel( $source, 1, 0, imagecolorallocatealpha( $source, 0, 0, 255, 127 ) );
		$path = tempnam( sys_get_temp_dir(), 'oc-wrapper-' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Give the local test fixture its renderer-required extension.
		rename( $path, $path . '.svg' );
		$path  .= '.svg';
		$output = null;
		try {
			foreach ( [ 'png', 'jpeg' ] as $format ) {
				ob_start();
				if ( 'png' === $format ) {
					imagepng( $source );
				} else {
					imagejpeg( $source );
				}
				$bytes = ob_get_clean();
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents, WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Write the local embedded-image fixture directly.
				file_put_contents( $path, self::raster_wrapper( 'data:image/' . $format . ';base64,' . base64_encode( $bytes ) ) );
				$dom = ( new ReflectionMethod( OC_Print_Base::class, 'load_print_svg' ) )->invoke( null, $path );
				$this->assertSame( [ $bytes, $format ], ( new ReflectionMethod( OC_Print_Base::class, 'embedded_svg_raster' ) )->invoke( null, $dom ) );
				$output = ( new ReflectionMethod( OC_Print_Base::class, 'build_black_clipart' ) )->invoke( null, $path );
				$this->assertIsString( $output );
				$black = imagecreatefrompng( $output );
				$this->assertSame( 0, imagecolorat( $black, 0, 0 ) & 0xFFFFFF );
				if ( 'png' === $format ) {
					$this->assertSame( 32, ( imagecolorat( $black, 0, 0 ) >> 24 ) & 127 );
					$this->assertSame( 127, ( imagecolorat( $black, 1, 0 ) >> 24 ) & 127 );
				}
				imagedestroy( $black );
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Unit test temporary-file cleanup.
				unlink( $output );
				$output  = null;
				$decoded = ( new ReflectionMethod( OC_Print_Engraving::class, 'open_svg_image_resource' ) )->invoke( null, $path, 600, 300 );
				$this->assertSame( 2, imagesx( $decoded ) );
				imagedestroy( $decoded );
				if ( class_exists( 'OC_Test_Vector_SVG_PDF' ) ) {
					$pdf = ( new ReflectionClass( OC_Test_Vector_SVG_PDF::class ) )->newInstanceWithoutConstructor();
					( new ReflectionMethod( OC_Print_Base::class, 'draw_pdf_svg' ) )->invoke( null, $pdf, $path, 0, 0, 20, 10 );
					$this->assertTrue( $pdf->image_called );
					$this->assertFalse( $pdf->image_svg_called );
				}
			}
		} finally {
			imagedestroy( $source );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Unit test temporary-file cleanup.
			unlink( $path );
			if ( $output ) {
				unlink( $output ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Unit test temporary-file cleanup.
			}
		}
	}

	#[Test]
	public function embedded_rasters_reject_unsafe_data_and_budgets(): void {
		$png     = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAIAAAABCAYAAAD0In+KAAAAD0lEQVQIHWP4z8DwH4QZABH4A/0mVt8AAAAASUVORK5CYII=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decode the PNG fixture.
		$uri     = 'data:image/png;base64,' . base64_encode( $png ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Encode the embedded image fixture.
		$invalid = [
			self::raster_wrapper( 'https://example.com/a.png' ),
			self::raster_wrapper( 'file:///etc/passwd' ),
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Encode embedded image fixture bytes.
			self::raster_wrapper( 'data:image/svg+xml;base64,' . base64_encode( '<svg/>' ) ),
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Encode embedded image fixture bytes.
			self::raster_wrapper( 'data:image/jpeg;base64,' . base64_encode( $png ) ),
			self::raster_wrapper( 'data:image/png;base64,!!!!' ),
			self::raster_wrapper( 'data:image/png;base64,' ),
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Encode embedded image fixture bytes.
			self::raster_wrapper( 'data:image/png;base64,' . base64_encode( 'not an image' ) ),
			str_replace( 'xlink:href="' . $uri, 'xlink:href="https://example.com/a.png', self::raster_wrapper( $uri ) ),
		];
		foreach ( [ [ 12001, 1 ], [ 7000, 7000 ] ] as [ $w, $h ] ) {
			$oversized = substr_replace( $png, pack( 'NN', $w, $h ), 16, 8 );
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Encode embedded image fixture bytes.
			$invalid[] = self::raster_wrapper( 'data:image/png;base64,' . base64_encode( $oversized ) );
		}
		// Single href keeps the SVG below its 5 MiB limit while exceeding the decoded budget.
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Encode embedded image fixture bytes.
		$invalid[] = '<svg xmlns="http://www.w3.org/2000/svg" width="2" height="1" viewBox="0 0 2 1"><image width="2" height="1" href="data:image/png;base64,' . base64_encode( $png . str_repeat( 'x', 2097153 ) ) . '"/></svg>';
		$path      = tempnam( sys_get_temp_dir(), 'oc-invalid-wrapper-' );
		try {
			foreach ( $invalid as $index => $svg ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Write the local test fixture directly.
				file_put_contents( $path, $svg );
				try {
					( new ReflectionMethod( OC_Print_Base::class, 'load_print_svg' ) )->invoke( null, $path );
					$this->fail( 'Accepted unsafe wrapper ' . $index );
				} catch ( RuntimeException $e ) {
					$this->assertNotEmpty( $e->getMessage() );
				}
			}
		} finally {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Unit test temporary-file cleanup.
			unlink( $path );
		}
	}

	#[Test]
	public function embedded_raster_accepts_ascii_line_wrapping_without_relaxing_validation(): void {
		$encoded = 'iVBORw0KGgoAAAANSUhEUgAAAAIAAAABCAYAAAD0In+KAAAAD0lEQVQIHWP4z8DwH4QZABH4A/0mVt8AAAAASUVORK5CYII=';
		$decode  = new ReflectionMethod( OC_Print_Base::class, 'decode_embedded_svg_raster' );
		$wrapped = chunk_split( $encoded, 16, " \t\r\n\f\v" );
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decode embedded image fixture bytes.
		$this->assertSame( [ base64_decode( $encoded ), 'png' ], $decode->invoke( null, 'data:image/png;base64,' . $wrapped ) );
		$path = tempnam( sys_get_temp_dir(), 'oc-wrapped-svg-' );
		try {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Write the local test fixture directly.
			file_put_contents( $path, self::raster_wrapper( 'data:image/png;base64,' . chunk_split( $encoded, 16, "\r\n\t " ) ) );
			$dom = ( new ReflectionMethod( OC_Print_Base::class, 'load_print_svg' ) )->invoke( null, $path );
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decode embedded image fixture bytes.
			$this->assertSame( [ base64_decode( $encoded ), 'png' ], ( new ReflectionMethod( OC_Print_Base::class, 'embedded_svg_raster' ) )->invoke( null, $dom ) );
		} finally {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Unit test temporary-file cleanup.
			unlink( $path );
		}
		foreach ( [
			'data:image/jpeg;base64,' . $wrapped,
			'data:image/svg+xml;base64,' . $wrapped,
			'data:image/png;base64,' . $encoded . "\xc2\xa0",
			'data:image/png;base64,' . $wrapped . '!',
			'data:image/png;base64,' . substr( $encoded, 0, -1 ) . 'B=',
			'data:image/png;base64,' . str_repeat( ' ', 5242880 ) . $encoded,
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Encode embedded image fixture bytes.
			'data:image/png;base64,' . chunk_split( base64_encode( str_repeat( 'x', 2097153 ) ), 76, "\n" ),
		] as $uri ) {
			try {
				$decode->invoke( null, $uri );
				$this->fail( 'Whitespace normalisation must retain MIME, canonical base64, and byte-budget checks.' );
			} catch ( RuntimeException $e ) {
				$this->assertNotEmpty( $e->getMessage() );
			}
		}
	}

	#[Test]
	public function stylesheet_and_referenced_definition_artwork_recolour_without_changing_masks(): void {
		$path   = tempnam( sys_get_temp_dir(), 'oc-css-colour-' );
		$output = null;
		try {
			foreach ( [
				'<defs/><style>.mark{fill:red}</style><rect class="mark" width="20" height="20"/>',
				'<defs><rect id="shape" class="mark" width="20" height="20"/></defs><style>.mark{fill:red}</style><use href="#shape"/>',
				'<defs><rect id="shape" class="mark" width="20" height="20"/><rect id="mask-shape" class="mask-paint" width="20" height="20"/><mask id="m"><use href="#mask-shape"/><circle r="5" fill="black"/></mask></defs><style>.mark{fill:red !important}.mask-paint{fill:white}</style><use href="#shape" mask="url(#m)"/><rect class="mark" width="5" height="5"/>',
			] as $index => $content ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Write the local test fixture directly.
				file_put_contents( $path, '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20">' . $content . '</svg>' );
				$output = ( new ReflectionMethod( OC_Print_Base::class, 'build_coloured_svg' ) )->invoke( null, $path, '#0000ff' );
				$dom    = new DOMDocument();
				$dom->load( $output );
				$xpath = new DOMXPath( $dom );
				foreach ( $xpath->query( '//*[@class="mark"]' ) as $mark ) {
					$this->assertSame( '#0000ff', $mark->getAttribute( 'fill' ) );
					if ( 2 === $index ) {
						$this->assertStringContainsString( 'fill:#0000ff !important', $mark->getAttribute( 'style' ) );
					}
				}
				if ( 2 === $index ) {
					$this->assertSame( 'white', $xpath->query( '//*[@id="mask-shape"]' )->item( 0 )->getAttribute( 'fill' ) );
					$this->assertSame( 'black', $dom->getElementsByTagName( 'circle' )->item( 0 )->getAttribute( 'fill' ) );
					$this->assertSame( 'url(#m)', $xpath->query( '//*[@mask]' )->item( 0 )->getAttribute( 'mask' ) );
					$this->assertStringContainsString( '.mask-paint{fill:white}', $dom->getElementsByTagName( 'style' )->item( 0 )->textContent );
				} else {
					$this->assertStringContainsString( '.mark{fill:#0000ff}', $dom->getElementsByTagName( 'style' )->item( 0 )->textContent );
				}
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Unit test temporary-file cleanup.
				unlink( $output );
				$output = null;
			}
		} finally {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Unit test temporary-file cleanup.
			unlink( $path );
			if ( $output ) {
				unlink( $output ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Unit test temporary-file cleanup.
			}
		}
	}

	#[Test]
	public function safe_complex_embedded_artwork_and_coloured_wrappers_keep_vector_handling(): void {
		$uri  = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAIAAAABCAYAAAD0In+KAAAAD0lEQVQIHWP4z8DwH4QZABH4A/0mVt8AAAAASUVORK5CYII=';
		$path = tempnam( sys_get_temp_dir(), 'oc-compatible-svg-' );
		try {
			foreach ( [ self::raster_wrapper( $uri ), self::raster_wrapper( $uri, 'transform="rotate(30)"' ), str_replace( '</svg>', '<g><image href="' . $uri . '" width="1" height="1"/></g></svg>', self::raster_wrapper( $uri ) ) ] as $index => $raw ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Write the local test fixture directly.
				file_put_contents( $path, $raw );
				$dom = ( new ReflectionMethod( OC_Print_Base::class, 'load_print_svg' ) )->invoke( null, $path );
				if ( $index > 0 ) {
					$this->assertNull( ( new ReflectionMethod( OC_Print_Base::class, 'embedded_svg_raster' ) )->invoke( null, $dom ) );
				}
				$output = ( new ReflectionMethod( OC_Print_Base::class, 'build_coloured_svg' ) )->invoke( null, $path, '#ff0000' );
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Read the local generated artwork fixture.
				$this->assertStringContainsString( $uri, file_get_contents( $output ) );
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Unit test temporary-file cleanup.
				unlink( $output );
			}
		} finally {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Unit test temporary-file cleanup.
			unlink( $path );
		}
	}

	#[Test]
	public function safe_svg_features_and_benign_doctype_are_accepted(): void {
		$path = tempnam( sys_get_temp_dir(), 'oc-compatible-svg-' );
		$raw  = '<!DOCTYPE svg><svg xmlns="http://www.w3.org/2000/svg" xmlns:editor="urn:editor" viewBox="0 0 20 20"><metadata><editor:document editor:version="1"/></metadata><defs><linearGradient id="paint"><stop stop-color="red" stop-opacity="0.5"/></linearGradient><filter id="blur"><feGaussianBlur stdDeviation="1"/></filter><path id="shape" d="M0 0L20 20"/></defs><style>/* editor */ .mark { fill: url(#paint); filter: url(#blur); }</style><use href="#shape" class="mark"/><text x="1" y="10">Hello</text></svg>';
		try {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Write the local test fixture directly.
			file_put_contents( $path, $raw );
			$dom = ( new ReflectionMethod( OC_Print_Base::class, 'load_print_svg' ) )->invoke( null, $path );
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM property.
			( new ReflectionMethod( OC_Print_Base::class, 'force_svg_node_colour' ) )->invoke( null, $dom->documentElement, '#000000' );
			$this->assertSame( 'red', $dom->getElementsByTagName( 'stop' )[0]->getAttribute( 'stop-color' ) );
			$this->assertSame( '#shape', $dom->getElementsByTagName( 'use' )[0]->getAttribute( 'href' ) );
			$this->assertStringContainsString( 'fill: url(#paint)', $dom->getElementsByTagName( 'style' )[0]->textContent );
			$this->assertSame( 1, $dom->getElementsByTagNameNS( 'urn:editor', 'document' )->length );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Write the local test fixture directly.
			file_put_contents( $path, str_replace( '<!DOCTYPE svg>', '<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "https://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">', $raw ) );
			$dom = ( new ReflectionMethod( OC_Print_Base::class, 'load_print_svg' ) )->invoke( null, $path );
			$this->assertNull( $dom->doctype, 'Legacy external-only declarations must be stripped before decoding.' );
		} finally {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Unit test temporary-file cleanup.
			unlink( $path );
		}
	}

	#[Test]
	public function absolute_svg_units_work_in_the_print_raster_fallback(): void {
		if ( ! class_exists( 'Imagick' ) || ! Imagick::queryFormats( 'SVG' ) ) {
			$this->markTestSkipped( 'Imagick SVG support is required.' );
		}
		$path   = tempnam( sys_get_temp_dir(), 'oc-unit-svg-' );
		$output = tempnam( sys_get_temp_dir(), 'oc-unit-png-' );
		try {
			foreach ( [ '96', '96px', '25.4mm', '2.54cm', '1in', '72pt', '6pc' ] as $length ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Write the local test fixture directly.
				file_put_contents( $path, '<svg xmlns="http://www.w3.org/2000/svg" width="' . $length . '" height="48px"><rect x="48" width="48" height="48" fill="black"/></svg>' );
				$this->assertTrue( ( new ReflectionMethod( OC_Print_Base::class, 'convert_svg_with_imagick' ) )->invoke( null, $path, $output, 25.4, 12.7 ) );
				$image = new Imagick( $output );
				$this->assertSame( 600, $image->getImageWidth() );
				$this->assertSame( 300, $image->getImageHeight() );
				$this->assertEqualsWithDelta( 0, $image->getImagePixelColor( 100, 100 )->getColorValue( Imagick::COLOR_ALPHA ), 0.01 );
				$this->assertEqualsWithDelta( 1, $image->getImagePixelColor( 450, 100 )->getColorValue( Imagick::COLOR_ALPHA ), 0.01 );
				$image->clear();
			}
		} finally {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Unit test temporary-file cleanup.
			unlink( $path );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Unit test temporary-file cleanup.
			unlink( $output );
		}
	}

	#[Test]
	public function absolute_svg_lengths_reject_relative_invalid_and_nonfinite_values(): void {
		$method = new ReflectionMethod( OC_Print_Base::class, 'svg_absolute_length_px' );
		foreach ( [ '', '100%', '1em', '1rem', '0px', '-2mm', 'NaN', 'INF', '1e999in', '12garbage' ] as $value ) {
			try {
				$method->invoke( null, $value );
				$this->fail( 'Accepted invalid length: ' . $value );
			} catch ( RuntimeException $e ) {
				$this->assertNotEmpty( $e->getMessage() );
			}
		}
	}

	#[Test]
	public function complex_svg_preserves_mask_resources_and_attempts_rendering(): void {
		$temp = tempnam( sys_get_temp_dir(), 'oc-mask-' );
		$path = $temp . '.svg';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Give the local test fixture its renderer-required extension.
		rename( $temp, $path );
		$raw = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"><defs><mask id="m"><rect width="20" height="20" fill="white"/><circle r="5" fill="black"/></mask></defs><rect width="20" height="20" mask="url(#m)" fill="red"/></svg>';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Write the local test fixture directly.
		file_put_contents( $path, $raw );
		try {
			$dom = ( new ReflectionMethod( OC_Print_Base::class, 'load_print_svg' ) )->invoke( null, $path );
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM property.
			( new ReflectionMethod( OC_Print_Base::class, 'force_svg_node_colour' ) )->invoke( null, $dom->documentElement, '#000000' );
			$this->assertSame( 'white', $dom->getElementsByTagName( 'rect' )[0]->getAttribute( 'fill' ) );
			$this->assertSame( 'black', $dom->getElementsByTagName( 'circle' )[0]->getAttribute( 'fill' ) );
			$this->assertSame( 'url(#m)', $dom->getElementsByTagName( 'rect' )[1]->getAttribute( 'mask' ) );
			$output = ( new ReflectionMethod( OC_Print_Base::class, 'build_black_clipart' ) )->invoke( null, $path );
			$this->assertFileExists( $output );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Unit test temporary-file cleanup.
			unlink( $output );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Read the local artwork fixture.
			$this->assertSame( $raw, file_get_contents( $path ) );
			if ( class_exists( 'OC_Test_Vector_SVG_PDF' ) ) {
				$pdf = ( new ReflectionClass( OC_Test_Vector_SVG_PDF::class ) )->newInstanceWithoutConstructor();
				( new ReflectionMethod( OC_Print_Base::class, 'draw_pdf_svg' ) )->invoke( null, $pdf, $path, 0, 0, 20, 20 );
				$this->assertTrue( $pdf->image_svg_called );
			}
		} finally {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Unit test temporary-file cleanup.
			unlink( $path );
		}
	}

	#[Test]
	public function complex_black_silhouette_keeps_original_renderer_alpha(): void {
		if ( ! class_exists( 'Imagick' ) || ! Imagick::queryFormats( 'SVG' ) || ! function_exists( 'imagecreatefrompng' ) ) {
			$this->markTestSkipped( 'Imagick SVG and GD are required.' );
		}
		$temp = tempnam( sys_get_temp_dir(), 'oc-alpha-svg-' );
		$path = $temp . '.svg';
		// The production dimension reader selects its decoder from the file extension.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Match the extension of real SVG uploads.
		rename( $temp, $path );
		$original = null;
		$output   = null;
		$before   = false;
		$after    = false;
		try {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Write the local test fixture directly.
			file_put_contents( $path, '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 2"><defs><linearGradient id="g"><stop stop-color="red" stop-opacity="0.2"/><stop offset="1" stop-color="blue" stop-opacity="0.8"/></linearGradient><mask id="m"><rect width="20" height="2" fill="white"/></mask></defs><rect x="2" y="0.2" width="16" height="1.6" fill="url(#g)" mask="url(#m)"/></svg>' );
			$original = ( new ReflectionMethod( OC_Print_Base::class, 'normalise_svg_for_tcpdf' ) )->invoke( null, $path, 173.4, 17.34 );
			$output   = ( new ReflectionMethod( OC_Print_Base::class, 'build_coloured_svg' ) )->invoke( null, $path, '#000000' );
			$this->assertIsString( $original, 'The original SVG must rasterise for the alpha comparison.' );
			$this->assertIsString( $output, 'The silhouette conversion must produce an output file.' );
			$this->assertSame( 'image/png', getimagesize( $original )['mime'] ?? null );
			$this->assertSame( 'png', pathinfo( $output, PATHINFO_EXTENSION ), 'Expected the raster silhouette, not the legacy SVG fallback.' );
			$this->assertSame( 'image/png', getimagesize( $output )['mime'] ?? null );
			$before = imagecreatefrompng( $original );
			$after  = imagecreatefrompng( $output );
			$this->assertInstanceOf( GdImage::class, $before );
			$this->assertInstanceOf( GdImage::class, $after );
			$this->assertSame( imagesx( $before ), imagesx( $after ) );
			$this->assertSame( imagesy( $before ), imagesy( $after ) );
			foreach ( [ 0.05, 0.25, 0.5, 0.75, 0.95 ] as $fraction ) {
				$x = (int) ( imagesx( $before ) * $fraction );
				$y = (int) ( imagesy( $before ) / 2 );
				$this->assertSame( ( imagecolorat( $before, $x, $y ) >> 24 ) & 127, ( imagecolorat( $after, $x, $y ) >> 24 ) & 127 );
				$this->assertSame( 0, imagecolorat( $after, $x, $y ) & 0xffffff );
			}
		} finally {
			foreach ( [ $before, $after ] as $image ) {
				if ( $image instanceof GdImage ) {
					imagedestroy( $image );
				}
			}
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Unit test temporary-file cleanup.
			unlink( $path );
			foreach ( [ $original, $output ] as $file ) {
				if ( $file ) {
					unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Unit test temporary-file cleanup.
				}
			}
		}
	}

	#[Test]
	public function svg_preflight_rejects_external_resources(): void {
		$path = tempnam( sys_get_temp_dir(), 'oc-unsafe-svg-' );
		try {
			foreach ( [ '<image href="https://example.com/a.png"/>', '<use href="other.svg#shape"/>', '<style>@import "https://example.com/a.css";</style>', '<rect style="fill:u\\72l(https://example.com/a)"/>', '<rect xml:base="https://example.com/"/>', '<rect fill="url(file:///etc/passwd)"/>', '<style>rect { fill: u/**/rl(https://example.com/a); }</style>', '<script/>', '<image href="#safe" onload="alert(1)"/>' ] as $content ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Write the local test fixture directly.
				file_put_contents( $path, '<svg xmlns="http://www.w3.org/2000/svg">' . $content . '</svg>' );
				try {
					( new ReflectionMethod( OC_Print_Base::class, 'load_print_svg' ) )->invoke( null, $path );
					$this->fail( 'Unsafe or unsupported SVG was accepted: ' . $content );
				} catch ( RuntimeException $e ) {
					$this->assertNotEmpty( $e->getMessage() );
				}
			}
		} finally {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Unit test temporary-file cleanup.
			unlink( $path );
		}
	}

	#[Test]
	public function physical_raster_budget_is_bounded_and_size_dependent(): void {
		$method = new ReflectionMethod( OC_Print_Base::class, 'print_raster_dimensions' );
		$this->assertSame( [ 600, 300 ], $method->invoke( null, 25.4, 12.7, 600 ) );
		$this->assertSame( [ 3000, 1500 ], $method->invoke( null, 127, 63.5, 600 ) );
		$this->assertSame( [ 6000, 3000 ], $method->invoke( null, 254, 127, 600, 12000, 40000000 ) );
		$this->assertSame( [ 2400, 1200 ], $method->invoke( null, 25.4, 12.7, 2400, 12000, 40000000 ) );
		[ $w, $h ] = $method->invoke( null, 1000000, 1000000, 1200 );
		$this->assertLessThanOrEqual( 4096, max( $w, $h ) );
		$this->assertLessThanOrEqual( 16000000, $w * $h );
		$this->expectException( RuntimeException::class );
		$method->invoke( null, INF, 10 );
	}

	#[Test]
	public function simple_black_svg_preserves_unpainted_regions_and_opacity(): void {
		$temp = tempnam( sys_get_temp_dir(), 'oc-simple-black-' );
		$path = $temp . '.svg';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Give the local test fixture its renderer-required extension.
		rename( $temp, $path );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Write the local test fixture directly.
		file_put_contents( $path, '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none"><path d="M0 0L20 20" stroke="red" opacity="0.5"/><rect width="5" height="5" fill="blue"/></svg>' );
		$output = null;
		try {
			$output = ( new ReflectionMethod( OC_Print_Base::class, 'build_black_clipart' ) )->invoke( null, $path );
			$dom    = new DOMDocument();
			$dom->load( $output );
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM property.
			$this->assertSame( 'none', $dom->documentElement->getAttribute( 'fill' ) );
			$this->assertSame( '#000000', $dom->getElementsByTagName( 'path' )[0]->getAttribute( 'stroke' ) );
			$this->assertSame( '0.5', $dom->getElementsByTagName( 'path' )[0]->getAttribute( 'opacity' ) );
			$this->assertSame( '#000000', $dom->getElementsByTagName( 'rect' )[0]->getAttribute( 'fill' ) );
		} finally {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Unit test temporary-file cleanup.
			unlink( $path );
			if ( is_string( $output ) ) {
				unlink( $output ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Unit test temporary-file cleanup.
			}
		}
	}

	#[Test]
	public function svg_preflight_rejects_entity_declarations_before_parsing(): void {
		$path = tempnam( sys_get_temp_dir(), 'oc-entity-svg-' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Write the local test fixture directly.
		file_put_contents( $path, '<!DOCTYPE svg [<!ENTITY secret SYSTEM "file:///etc/passwd">]><svg xmlns="http://www.w3.org/2000/svg"><desc>&secret;</desc></svg>' );
		try {
			$this->expectException( RuntimeException::class );
			$this->expectExceptionMessage( 'Unsafe SVG declarations' );
			( new ReflectionMethod( OC_Print_Base::class, 'load_print_svg' ) )->invoke( null, $path );
		} finally {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Unit test temporary-file cleanup.
			unlink( $path );
		}
	}

	#[Test]
	public function engraving_filter_retains_final_size_detail_above_2048(): void {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			$this->markTestSkipped( 'GD is not available.' );
		}
		$temp = tempnam( sys_get_temp_dir(), 'oc-filter-budget-' );
		$path = $temp . '.png';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Give the local test fixture its renderer-required extension.
		rename( $temp, $path );
		$image = imagecreatetruecolor( 6000, 300 );
		$white = imagecolorallocate( $image, 255, 255, 255 );
		for ( $x = 1; $x < 6000; $x += 2 ) {
			imageline( $image, $x, 0, $x, 299, $white );
		}
		imagepng( $image, $path );
		imagedestroy( $image );
		$outputs = [];
		try {
			$method    = new ReflectionMethod( OC_Print_Base::class, 'build_filtered_image' );
			$args      = [
				null,
				$path,
				[ 'settings' => [ 'image_filter_ids' => [ 7 ] ] ],
				[
					'imageFilterId'  => 7,
					'imageFilterKey' => 'grayscale',
				],
			];
			$outputs[] = $method->invoke( ...$args );
			$outputs[] = $method->invoke( ...array_merge( $args, [ 254.0, 12.7, 600 ] ) );
			$this->assertSame( 2048, getimagesize( $outputs[0] )[0] );
			$this->assertSame( [ 6000, 300 ], array_slice( getimagesize( $outputs[1] ), 0, 2 ) );
			$filtered = imagecreatefrompng( $outputs[1] );
			try {
				$this->assertSame( 0, imagecolorat( $filtered, 100, 100 ) & 0xFFFFFF );
				$this->assertSame( 0xFFFFFF, imagecolorat( $filtered, 101, 100 ) & 0xFFFFFF );
			} finally {
				imagedestroy( $filtered );
			}
		} finally {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Unit test temporary-file cleanup.
			unlink( $path );
			foreach ( $outputs as $output ) {
				if ( is_string( $output ) ) {
					unlink( $output ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Unit test temporary-file cleanup.
				}
			}
		}
	}

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

		// TCPDF 7 resolves custom array dimensions when the page is started.
		$pdf->AddPage();
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
		$order = $this->createStub( \WC_Order::class );
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
