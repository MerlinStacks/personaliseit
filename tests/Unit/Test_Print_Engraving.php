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
	public function textarea_areas_render_order_time_snapshot_when_available(): void {
		if ( ! class_exists( 'TCPDF' ) ) {
			$this->markTestSkipped( 'TCPDF is not available.' );
		}

		$pdf = ( new ReflectionClass( OC_Test_Engraving_PDF::class ) )->newInstanceWithoutConstructor();
		$area = (object) [
			'canvas_unit' => 'px',
			'canvas_w'    => 300,
			'canvas_h'    => 150,
		];
		$area_data = [
			'layers'   => [
				[
					'type' => 'textarea',
				],
			],
			'snapshot' => [
				'svg' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 300 150"><path d="M10 10h50v10h-50z" fill="#000000"/></svg>',
			],
		];

		$method = new ReflectionMethod( OC_Print_Base::class, 'render_textarea_snapshot_payload' );
		$result = $method->invokeArgs( null, [ $pdf, $area, $area_data, 0.0, 0.0 ] );

		$this->assertTrue( $result );
		$this->assertTrue( $pdf->image_svg_called );
		$this->assertStringContainsString( '<path', $pdf->image_svg );
	}
}
