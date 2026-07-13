<?php
/**
 * Unit tests for engraving print export helpers.
 *
 * @package OverCustomise
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

if ( ! class_exists( 'OC_Test_Engraving_PDF' ) && class_exists( 'TCPDF' ) ) {
	class OC_Test_Engraving_PDF extends TCPDF {
		public bool $image_svg_called = false;
		public string $image_svg = '';

		public function ImageSVG( $file, $x = '', $y = '', $w = 0, $h = 0, $link = '', $align = '', $palign = '', $border = 0, $fitonpage = false ) {
			$this->image_svg_called = true;
			$this->image_svg        = is_readable( (string) $file ) ? ( file_get_contents( (string) $file ) ?: '' ) : '';
		}
	}
}

class Test_Print_Engraving extends TestCase {

	#[Test]
	public function engraving_layer_text_renders_as_font_independent_svg_path(): void {
		$font_path = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
		if ( ! class_exists( 'TCPDF' ) || ! file_exists( $font_path ) ) {
			$this->markTestSkipped( 'TCPDF or DejaVuSans.ttf is not available.' );
		}

		$pdf = ( new ReflectionClass( OC_Test_Engraving_PDF::class ) )->newInstanceWithoutConstructor();

		$method = new ReflectionMethod( OC_Print_Base::class, 'render_engraving_text_outline' );
		$result = $method->invokeArgs( null, [ $pdf, 'Alex', $font_path, 18.0, 0.0, 0.0, 40.0, 12.0, 'C' ] );

		$this->assertTrue( $result );
		$this->assertTrue( $pdf->image_svg_called );
		$this->assertStringNotContainsString( '<text', $pdf->image_svg );
		$this->assertMatchesRegularExpression( '/<svg[^>]+width="[0-9.]+pt"[^>]+height="[0-9.]+pt"/', $pdf->image_svg );
		$this->assertStringContainsString( '<path d=', $pdf->image_svg );
		$this->assertStringContainsString( 'fill-rule="evenodd"', $pdf->image_svg );
	}

	#[Test]
	public function print_temp_image_paths_keep_requested_extension(): void {
		$method = new ReflectionMethod( OC_Print_Base::class, 'temp_path_with_extension' );
		$path   = $method->invokeArgs( null, [ 'oc-test-image-' . wp_generate_uuid4() . '.png', 'png' ] );

		$this->assertIsString( $path );
		$this->assertSame( 'png', strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) );
		$this->assertFileExists( $path );

		@unlink( $path );
	}

}
