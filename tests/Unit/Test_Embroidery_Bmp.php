<?php

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once OC_PATH . 'includes/class-oc-preview-generator.php';
require_once OC_PATH . 'includes/class-oc-command-runner.php';

/** Exercise the real converter, including the BMP header Hatch consumes. */
class Test_Embroidery_Bmp extends TestCase {
	#[Test]
	public function bitmap_is_uncompressed_rgb_with_scale_and_white_background(): void {
		$binary = OC_Preview_Generator::find_ghostscript();
		if ( ! $binary || ! function_exists( 'imagecreatefrombmp' ) ) {
			$this->markTestSkipped( 'Ghostscript and GD are required.' );
		}
		$source = tempnam( sys_get_temp_dir(), 'oc-bmp-' );
		$bmp = $source . '.bmp';
		$thumb = $source . '.png';
		try {
			file_put_contents( $source, "%!PS-Adobe-3.0 EPSF-3.0\n%%BoundingBox: 0 0 72 36\n%%EndComments\n1 0 0 setrgbcolor 0 0 36 36 rectfill\nshowpage\n" );
			$method = new ReflectionMethod( OC_Print_Embroidery::class, 'render_bmp' );
			$method->invoke( null, $binary, $source, $bmp );
			$header = file_get_contents( $bmp, false, null, 0, 54 );
			$this->assertSame( 'BM', substr( $header, 0, 2 ) );
			$this->assertSame( 24, unpack( 'v', substr( $header, 28, 2 ) )[1] );
			$this->assertSame( 0, unpack( 'V', substr( $header, 30, 4 ) )[1] );
			$this->assertEqualsWithDelta( 5669, unpack( 'V', substr( $header, 38, 4 ) )[1], 1 );
			$image = imagecreatefrombmp( $bmp );
			$this->assertSame( 144, imagesx( $image ) );
			$this->assertSame( 72, imagesy( $image ) );
			$this->assertSame( 0xff0000, imagecolorat( $image, 30, 30 ) );
			$this->assertSame( 0xffffff, imagecolorat( $image, 100, 30 ) );
			imagedestroy( $image );
			$this->assertTrue( OC_Preview_Generator::from_bmp( $bmp, $thumb ) );
			$this->assertSame( IMAGETYPE_PNG, getimagesize( $thumb )[2] );
			$mime = new ReflectionMethod( OC_Print_Generator::class, 'mime_for_extension' );
			$this->assertSame( 'image/bmp', $mime->invoke( null, 'bmp' ) );
		} finally {
			@unlink( $source );
			@unlink( $bmp );
			@unlink( $thumb );
		}
	}
}
