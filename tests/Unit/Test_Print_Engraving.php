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

		public function ImageSVG( $file, $x = '', $y = '', $w = 0, $h = 0, $link = '', $align = '', $palign = '', $border = 0, $fitonpage = false ) {
			$this->image_svg_called = true;
		}
	}
}

class Test_Print_Engraving extends TestCase {

	#[Test]
	public function engraving_snapshot_svg_is_used_for_outline_safe_export(): void {
		if ( ! class_exists( 'TCPDF' ) ) {
			$this->markTestSkipped( 'TCPDF is not available.' );
		}
		$pdf = ( new ReflectionClass( OC_Test_Engraving_PDF::class ) )->newInstanceWithoutConstructor();

		$method = new ReflectionMethod( OC_Print_Engraving::class, 'render_snapshot_svg' );
		$result = $method->invokeArgs( null, [
			$pdf,
			[
				'snapshot' => [
					'format' => 'fabric-svg-v1',
					'svg'    => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M1 1L19 1L19 19Z" fill="#000000"/></svg>',
				],
			],
			20.0,
			20.0,
		] );

		$this->assertTrue( $result );
		$this->assertTrue( $pdf->image_svg_called );
	}
}
