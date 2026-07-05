<?php
/**
 * Unit tests for embroidery EPS export helpers.
 *
 * @package OverCustomise
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class Test_Print_Embroidery extends TestCase {

	#[Test]
	public function text_export_does_not_fall_back_to_default_placeholder(): void {
		$lines  = [];
		$method = new ReflectionMethod( OC_Print_Embroidery::class, 'append_eps_text' );
		$method->invokeArgs( null, [ &$lines, [ 'value' => '' ], [ 'default_text' => 'Your Name Here' ], 0.0, 0.0, 100.0, 20.0, true ] );

		$this->assertSame( [], $lines );
	}

	#[Test]
	public function layer_export_uses_area_text_when_layer_input_is_empty(): void {
		$lines = [];
		$area  = (object) [
			'canvas_unit' => 'px',
			'canvas_x'    => 0,
			'canvas_y'    => 0,
			'canvas_w'    => 300,
			'canvas_h'    => 120,
		];
		$data  = [
			'text'   => 'Customer Name',
			'bounds' => [ 'x' => 0, 'y' => 0, 'w' => 300, 'h' => 120, 'rotation' => 0 ],
			'layers' => [
				[
					'type'     => 'text',
					'x'        => 20,
					'y'        => 20,
					'w'        => 200,
					'h'        => 40,
					'input'    => [ 'value' => '', 'colorHex' => '#000000' ],
					'settings' => [ 'default_text' => 'Your Name Here' ],
				],
			],
		];

		$method = new ReflectionMethod( OC_Print_Embroidery::class, 'append_eps_layers' );
		$method->invokeArgs( null, [ &$lines, $area, $data ] );

		$output = implode( "\n", $lines );
		$this->assertStringContainsString( 'Customer Name', $output );
		$this->assertStringNotContainsString( 'Your Name Here', $output );
	}

	#[Test]
	public function mask_image_uses_imagemask_not_white_colorimage_card(): void {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			$this->markTestSkipped( 'GD is required for EPS mask generation.' );
		}

		$image = imagecreatetruecolor( 2, 1 );
		imagealphablending( $image, false );
		imagesavealpha( $image, true );
		$clear = imagecolorallocatealpha( $image, 255, 255, 255, 127 );
		$black = imagecolorallocatealpha( $image, 0, 0, 0, 0 );
		imagesetpixel( $image, 0, 0, $black );
		imagesetpixel( $image, 1, 0, $clear );

		$lines  = [];
		$method = new ReflectionMethod( OC_Print_Embroidery::class, 'append_eps_mask_image' );
		$method->invokeArgs( null, [ &$lines, $image, '#000000', 0.0, 0.0, 10.0, 5.0 ] );
		imagedestroy( $image );

		$output = implode( "\n", $lines );
		$this->assertStringContainsString( 'imagemask', $output );
		$this->assertStringNotContainsString( 'colorimage', $output );
		$this->assertStringContainsString( '80', $output );
	}
}
