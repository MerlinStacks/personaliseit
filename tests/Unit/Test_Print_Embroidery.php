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
	public function rotated_layer_center_matches_frontend_formula(): void {
		$method = new ReflectionMethod( OC_Print_Embroidery::class, 'rotated_layer_center' );
		$result = $method->invoke( null, 80.0, 90.0, 160.0, 40.0, 50.0, 60.0, 300.0, 120.0, 30.0 );

		$this->assertEqualsWithDelta( 170.3590, $result[0], 0.0001 );
		$this->assertEqualsWithDelta( 91.3397, $result[1], 0.0001 );
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
