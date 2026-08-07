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
		public int $image_svg_call_count = 0;
		public bool $image_called = false;
		public string $image_svg = '';

		public function Image( $file, $x = '', $y = '', $w = 0, $h = 0, $type = '', $link = '', $align = '', $resize = false, $dpi = 300, $palign = '', $ismask = false, $imgmask = false, $border = 0, $fitbox = false, $hidden = false, $fitonpage = false, $alt = false, $altimgs = [] ) {
			$this->image_called = true;
		}

		public function ImageSVG( $file, $x = '', $y = '', $w = 0, $h = 0, $link = '', $align = '', $palign = '', $border = 0, $fitonpage = false ) {
			$this->image_svg_called = true;
			++$this->image_svg_call_count;
			$this->image_svg        = is_readable( (string) $file ) ? ( file_get_contents( (string) $file ) ?: '' ) : '';
		}
	}
}

class Test_Print_Engraving extends TestCase {
	#[Test]
	public function isolated_transparent_pixels_do_not_turn_artwork_into_a_logo_silhouette(): void {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			$this->markTestSkipped( 'GD is not available.' );
		}

		$image = imagecreatetruecolor( 20, 20 );
		imagealphablending( $image, false );
		imagesavealpha( $image, true );
		$blue        = imagecolorallocatealpha( $image, 0, 80, 220, 0 );
		$transparent = imagecolorallocatealpha( $image, 0, 0, 0, 127 );
		imagefilledrectangle( $image, 0, 0, 19, 19, $blue );
		imagesetpixel( $image, 0, 0, $transparent );

		$method = new ReflectionMethod( OC_Print_Engraving::class, 'is_transparent_logo' );
		$this->assertFalse( $method->invoke( null, $image ) );
		imagedestroy( $image );
	}

	#[Test]
	public function sparse_logo_marks_are_still_detected_on_a_transparent_canvas(): void {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			$this->markTestSkipped( 'GD is not available.' );
		}
		$image = imagecreatetruecolor( 20, 20 );
		imagealphablending( $image, false );
		imagesavealpha( $image, true );
		$transparent = imagecolorallocatealpha( $image, 0, 0, 0, 127 );
		$white       = imagecolorallocatealpha( $image, 255, 255, 255, 0 );
		imagefilledrectangle( $image, 0, 0, 19, 19, $transparent );
		imagesetpixel( $image, 10, 10, $white );

		$method = new ReflectionMethod( OC_Print_Engraving::class, 'is_transparent_logo' );
		$this->assertTrue( $method->invoke( null, $image ) );
		imagedestroy( $image );
	}

	#[Test]
	public function near_full_canvas_white_logo_is_detected_despite_a_small_transparent_margin(): void {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			$this->markTestSkipped( 'GD is not available.' );
		}
		$image = imagecreatetruecolor( 100, 100 );
		imagealphablending( $image, false );
		imagesavealpha( $image, true );
		$transparent = imagecolorallocatealpha( $image, 0, 0, 0, 127 );
		$white       = imagecolorallocatealpha( $image, 255, 255, 255, 0 );
		imagefilledrectangle( $image, 0, 0, 99, 99, $white );
		imagefilledrectangle( $image, 0, 0, 1, 99, $transparent );

		$method = new ReflectionMethod( OC_Print_Engraving::class, 'is_transparent_logo' );
		$this->assertTrue( $method->invoke( null, $image ) );
		imagedestroy( $image );
	}

	#[Test]
	public function low_opacity_dark_fringe_does_not_change_white_logo_classification(): void {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			$this->markTestSkipped( 'GD is not available.' );
		}
		$image = imagecreatetruecolor( 20, 20 );
		imagealphablending( $image, false );
		imagesavealpha( $image, true );
		$transparent = imagecolorallocatealpha( $image, 0, 0, 0, 127 );
		$white       = imagecolorallocatealpha( $image, 255, 255, 255, 0 );
		$fringe      = imagecolorallocatealpha( $image, 0, 0, 0, 119 );
		imagefilledrectangle( $image, 0, 0, 19, 19, $transparent );
		imagefilledrectangle( $image, 4, 4, 15, 15, $white );
		imagesetpixel( $image, 3, 10, $fringe );

		$method = new ReflectionMethod( OC_Print_Engraving::class, 'is_transparent_logo' );
		$this->assertTrue( $method->invoke( null, $image ) );
		imagedestroy( $image );
	}

	#[Test]
	public function multicolour_transparent_artwork_keeps_tonal_processing_instead_of_becoming_a_flat_silhouette(): void {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			$this->markTestSkipped( 'GD is not available.' );
		}
		$image = imagecreatetruecolor( 20, 20 );
		imagealphablending( $image, false );
		imagesavealpha( $image, true );
		$transparent = imagecolorallocatealpha( $image, 0, 0, 0, 127 );
		imagefilledrectangle( $image, 0, 0, 19, 19, $transparent );
		for ( $x = 0; $x < 20; $x++ ) {
			$colour = imagecolorallocatealpha( $image, $x * 12, 30, 255 - $x * 12, 0 );
			imagefilledrectangle( $image, $x, 5, $x, 14, $colour );
		}

		$method = new ReflectionMethod( OC_Print_Engraving::class, 'is_transparent_logo' );
		$this->assertFalse( $method->invoke( null, $image ) );
		imagedestroy( $image );
	}

	#[Test]
	public function equal_luminance_different_hues_are_not_mistaken_for_a_monochrome_logo(): void {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			$this->markTestSkipped( 'GD is not available.' );
		}
		$image = imagecreatetruecolor( 20, 20 );
		imagealphablending( $image, false );
		imagesavealpha( $image, true );
		$transparent = imagecolorallocatealpha( $image, 0, 0, 0, 127 );
		$red         = imagecolorallocatealpha( $image, 255, 0, 0, 0 );
		$green       = imagecolorallocatealpha( $image, 0, 76, 0, 0 );
		imagefilledrectangle( $image, 0, 0, 19, 19, $transparent );
		imagefilledrectangle( $image, 4, 4, 9, 15, $red );
		imagefilledrectangle( $image, 10, 4, 15, 15, $green );

		$method = new ReflectionMethod( OC_Print_Engraving::class, 'is_transparent_logo' );
		$this->assertFalse( $method->invoke( null, $image ) );
		imagedestroy( $image );
	}


	#[Test]
	public function leather_material_uses_its_engraving_profile(): void {
		$method  = new ReflectionMethod( OC_Print_Engraving::class, 'resolve_profile' );
		$profile = $method->invokeArgs(
			null,
			[
				null,
				[ 'renderSpecArea' => [ 'engravingMaterial' => 'leather' ] ],
			]
		);

		$this->assertSame( 'leather', $profile['material'] );
		$this->assertSame( 1.7, $profile['gamma'] );
		$this->assertSame( 'floyd_steinberg', $profile['dithering'] );
	}

	#[Test]
	public function silver_plaque_profile_supports_photo_engraving(): void {
		$method  = new ReflectionMethod( OC_Print_Engraving::class, 'resolve_profile' );
		$profile = $method->invokeArgs(
			null,
			[
				null,
				[ 'renderSpecArea' => [ 'engravingMaterial' => 'silver_plaque' ] ],
			]
		);

		$this->assertSame( 'silver_plaque', $profile['material'] );
		$this->assertSame( 1.25, $profile['gamma'] );
		$this->assertSame( 'floyd_steinberg', $profile['dithering'] );
		$this->assertSame( 600, $profile['dpi'] );
	}

	#[Test]
	public function photo_engraving_is_rasterised_at_its_final_print_size(): void {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			$this->markTestSkipped( 'GD is not available.' );
		}

		$temp   = tempnam( sys_get_temp_dir(), 'oc-photo-dpi-' );
		$source = $temp . '.png';
		unlink( $temp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Unit test temporary-file cleanup.
		$image = imagecreatetruecolor( 40, 20 );
		for ( $x = 0; $x < 40; $x++ ) {
			$gray = imagecolorallocate( $image, $x * 6, $x * 6, $x * 6 );
			imagefilledrectangle( $image, $x, 0, $x, 19, $gray );
		}
		imagepng( $image, $source );
		imagedestroy( $image );

		$output = '';
		try {
			$output = OC_Print_Engraving::prepare_artwork_for_layer(
				$source,
				[
					'dpi'       => 600,
					'gamma'     => 1.0,
					'dithering' => 'floyd_steinberg',
				],
				25.4,
				12.7
			);
			$size   = getimagesize( $output );
			$this->assertSame( 600, $size[0] );
			$this->assertSame( 300, $size[1] );
		} finally {
			@unlink( $source );
			if ( '' !== $output ) {
				@unlink( $output );
			}
		}
	}

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
		$this->assertStringContainsString( 'fill-rule="nonzero"', $pdf->image_svg );
	}

	#[Test]
	public function constrained_engraving_textarea_keeps_its_bottom_line(): void {
		$font_path = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
		if ( ! class_exists( 'TCPDF' ) || ! file_exists( $font_path ) ) {
			$this->markTestSkipped( 'TCPDF or DejaVuSans.ttf is not available.' );
		}

		$pdf = ( new ReflectionClass( OC_Test_Engraving_PDF::class ) )->newInstanceWithoutConstructor();
		$method = new ReflectionMethod( OC_Print_Base::class, 'render_engraving_multiline_text_outline' );
		$result = $method->invokeArgs(
			null,
			[
				$pdf,
				"Happy Birthday lots of love\nBrett, Kristina & the kids xxx",
				$font_path,
				18.0,
				0.0,
				0.0,
				80.0,
				12.0,
				'C',
				'C',
				[ 'Happy Birthday lots of love', 'Brett, Kristina & the kids xxx' ],
			]
		);

		$this->assertTrue( $result );
		$this->assertSame( 2, $pdf->image_svg_call_count );
	}

	#[Test]
	public function engraving_svg_clipart_remains_vector(): void {
		if ( ! class_exists( 'TCPDF' ) ) {
			$this->markTestSkipped( 'TCPDF is not available.' );
		}

		$upload_dir = wp_upload_dir()['basedir'];
		if ( ! is_dir( $upload_dir ) ) {
			mkdir( $upload_dir, 0755, true );
		}
		$source = $upload_dir . '/engraving-vector-clipart.svg';
		file_put_contents( $source, '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 20"><rect width="10" height="20" fill="#ff0000"/></svg>' );

		try {
			$pdf    = ( new ReflectionClass( OC_Test_Engraving_PDF::class ) )->newInstanceWithoutConstructor();
			$method = new ReflectionMethod( OC_Print_Base::class, 'render_layer_image' );
			$method->invokeArgs(
				null,
				[
					$pdf,
					[ 'type' => 'clipart', 'artworkPath' => $source ],
					[],
					0.0,
					0.0,
					10.0,
					20.0,
					'engraving',
					[],
				]
			);

			$this->assertTrue( $pdf->image_svg_called );
			$this->assertFalse( $pdf->image_called );
			$this->assertStringContainsString( '#000000', $pdf->image_svg );
		} finally {
			@unlink( $source );
		}
	}

	#[Test]
	public function transparent_white_logo_becomes_a_solid_engraving_mark(): void {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			$this->markTestSkipped( 'GD is not available.' );
		}

		$temp   = tempnam( sys_get_temp_dir(), 'oc-white-logo-' );
		$source = $temp . '.png';
		@unlink( $temp );
		$image  = imagecreatetruecolor( 20, 20 );
		imagealphablending( $image, false );
		imagesavealpha( $image, true );
		$transparent = imagecolorallocatealpha( $image, 0, 0, 0, 127 );
		$white       = imagecolorallocatealpha( $image, 255, 255, 255, 0 );
		imagefilledrectangle( $image, 0, 0, 19, 19, $transparent );
		imagefilledrectangle( $image, 5, 5, 14, 14, $white );
		imagepng( $image, $source );
		imagedestroy( $image );

		$output = '';
		try {
			$output = OC_Print_Engraving::prepare_artwork_for_layer(
				$source,
				[
					'material'   => 'glass',
					'gamma'      => 1.0,
					'contrast'   => 0,
					'edge_boost' => 25,
					'dithering'  => 'floyd_steinberg',
				]
			);
			$result = imagecreatefrompng( $output );

			$centre = imagecolorat( $result, 10, 10 );
			$outside = imagecolorat( $result, 1, 1 );
			$this->assertSame( 0, ( $centre >> 16 ) & 0xFF );
			$this->assertSame( 0, ( $centre >> 24 ) & 0x7F );
			$this->assertSame( 127, ( $outside >> 24 ) & 0x7F );
			imagedestroy( $result );
		} finally {
			@unlink( $source );
			if ( '' !== $output ) {
				@unlink( $output );
			}
		}
	}

	#[Test]
	public function transparent_dark_logo_is_not_reduced_to_an_outline(): void {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			$this->markTestSkipped( 'GD is not available.' );
		}

		$temp   = tempnam( sys_get_temp_dir(), 'oc-dark-logo-' );
		$source = $temp . '.png';
		@unlink( $temp );
		$image  = imagecreatetruecolor( 20, 20 );
		imagealphablending( $image, false );
		imagesavealpha( $image, true );
		$transparent = imagecolorallocatealpha( $image, 255, 255, 255, 127 );
		$black       = imagecolorallocatealpha( $image, 0, 0, 0, 0 );
		imagefilledrectangle( $image, 0, 0, 19, 19, $transparent );
		imagefilledrectangle( $image, 4, 4, 15, 15, $black );
		imagepng( $image, $source );
		imagedestroy( $image );

		$output = '';
		try {
			$output = OC_Print_Engraving::prepare_artwork_for_layer(
				$source,
				[ 'material' => 'glass', 'edge_boost' => 25, 'dithering' => 'floyd_steinberg' ]
			);
			$result = imagecreatefrompng( $output );
			$centre = imagecolorat( $result, 10, 10 );
			$this->assertSame( 0, ( $centre >> 16 ) & 0xFF );
			$this->assertSame( 0, ( $centre >> 24 ) & 0x7F );
			imagedestroy( $result );
		} finally {
			@unlink( $source );
			if ( '' !== $output ) {
				@unlink( $output );
			}
		}
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

	#[Test]
	public function cff_font_uses_browser_converted_print_companion(): void {
		$font_dir = trailingslashit( wp_upload_dir()['basedir'] ) . 'overcustomise/fonts';
		if ( ! is_dir( $font_dir ) ) {
			mkdir( $font_dir, 0755, true );
		}

		$source    = $font_dir . '/Belinda-Script-1783647232.otf';
		$companion = $font_dir . '/Belinda-Script-1783647232-print.ttf';
		$ttf       = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
		if ( ! file_exists( $ttf ) ) {
			$this->markTestSkipped( 'DejaVuSans.ttf is not available.' );
		}

		file_put_contents( $source, 'OTTOtest' );
		copy( $ttf, $companion );

		try {
			$method = new ReflectionMethod( OC_Print_Base::class, 'get_font_path' );
			$path   = $method->invokeArgs( null, [ (object) [ 'file_path' => 'overcustomise/fonts/Belinda-Script-1783647232.otf' ] ] );

			$this->assertSame( realpath( $companion ), $path );
		} finally {
			@unlink( $source );
			@unlink( $companion );
		}
	}

}
