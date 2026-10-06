<?php
/**
 * Pixel-level regression coverage for Ghostscript-free embroidery output.
 *
 * @package OverCustomise
 */

// Fixtures deliberately use local temporary files.
// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.PHP.NoSilencedErrors.Discouraged

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once OC_PATH . 'includes/print/class-oc-embroidery-raster.php';
require_once OC_PATH . 'includes/class-oc-preview-generator.php';

if ( ! class_exists( 'WC_Order' ) ) {
	class WC_Order {
		public function get_id(): int {
			return 1001;
		}
		public function get_order_number(): string {
			return '1001';
		}
	}
}

class Test_Embroidery_Gd extends TestCase {
	private array $files = [];

	protected function setUp(): void {
		if ( ! function_exists( 'imagebmp' ) ) {
			$this->markTestSkipped( 'PHP GD is required.' );
		}
	}

	protected function tearDown(): void {
		$font = new ReflectionProperty( OC_Print_Embroidery::class, 'native_default_font' );
		if ( is_string( $font->getValue() ) ) {
			$this->files[] = $font->getValue();
		}
		$font->setValue( null, null );
		( new ReflectionProperty( OC_Print_Embroidery::class, 'native_raster' ) )->setValue( null, false );
		foreach ( $this->files as $path ) {
			@unlink( $path );
		}
	}

	private function temporary( string $extension ): string {
		$path          = sys_get_temp_dir() . '/oc-gd-' . bin2hex( random_bytes( 8 ) ) . '.' . $extension;
		$this->files[] = $path;
		return $path;
	}

	private function render( string $commands ): GdImage {
		$source = $this->temporary( 'eps' );
		$bmp    = $this->temporary( 'bmp' );
		file_put_contents( $source, "%!PS-Adobe-3.0 EPSF-3.0\n%%BoundingBox: 0 0 72 36\n" . $commands . "\nshowpage\n" );
		OC_Embroidery_Raster::render( $source, $bmp );
		$header = file_get_contents( $bmp, false, null, 0, 54 );
		$this->assertSame( 'BM', substr( $header, 0, 2 ) );
		$this->assertSame( 24, unpack( 'v', substr( $header, 28, 2 ) )[1] );
		$this->assertSame( 0, unpack( 'V', substr( $header, 30, 4 ) )[1] );
		$this->assertEqualsWithDelta( 5669, unpack( 'V', substr( $header, 38, 4 ) )[1], 1 );
		$image = imagecreatefrombmp( $bmp );
		$this->assertSame( 144, imagesx( $image ) );
		$this->assertSame( 72, imagesy( $image ) );
		return $image;
	}

	#[Test]
	public function native_bitmap_retains_rgb_scale_white_background_and_winding_holes(): void {
		$image = $this->render(
			'1 0 0 setrgbcolor 0 0 36 36 rectfill
0 0 1 setrgbcolor newpath 40 4 moveto 68 4 lineto 68 32 lineto 40 32 lineto closepath
48 12 moveto 48 24 lineto 60 24 lineto 60 12 lineto closepath fill'
		);
		$this->assertSame( 0xff0000, imagecolorat( $image, 30, 30 ) );
		$this->assertSame( 0x0000ff, imagecolorat( $image, 84, 30 ) );
		$this->assertSame( 0xffffff, imagecolorat( $image, 108, 36 ) );
		imagedestroy( $image );
	}

	#[Test]
	public function clipping_rotation_and_graphics_state_are_preserved(): void {
		$image = $this->render(
			'gsave 36 18 translate 90 rotate
newpath 0 0 10 0 360 arc closepath clip newpath
0 1 0 setrgbcolor -20 -20 40 40 rectfill
0 0 1 setrgbcolor 1 0 4 8 rectfill grestore
1 0 0 setrgbcolor 0 0 5 5 rectfill'
		);
		$this->assertSame( 0x00ff00, imagecolorat( $image, 72, 36 ) );
		$this->assertSame( 0x0000ff, imagecolorat( $image, 66, 30 ) );
		$this->assertSame( 0x00ff00, imagecolorat( $image, 78, 42 ) );
		$this->assertSame( 0xffffff, imagecolorat( $image, 91, 55 ) );
		$this->assertSame( 0xff0000, imagecolorat( $image, 3, 68 ) );
		imagedestroy( $image );
	}

	#[Test]
	public function evenodd_holes_curves_and_strokes_render(): void {
		$image = $this->render(
			'newpath 2 2 moveto 34 2 lineto 34 34 lineto 2 34 lineto closepath
10 10 moveto 26 10 lineto 26 26 lineto 10 26 lineto closepath eofill
0 0 1 setrgbcolor 2 setlinewidth newpath 40 4 moveto 40 32 68 32 68 4 curveto stroke'
		);
		$this->assertSame( 0x000000, imagecolorat( $image, 8, 36 ) );
		$this->assertSame( 0xffffff, imagecolorat( $image, 36, 36 ) );
		$this->assertSame( 0x0000ff, imagecolorat( $image, 108, 22 ) );
		imagedestroy( $image );
	}

	#[Test]
	public function composer_renders_text_svg_and_transparent_rasters_without_processes(): void {
		( new ReflectionProperty( OC_Print_Embroidery::class, 'native_raster' ) )->setValue( null, true );
		$png    = $this->temporary( 'png' );
		$raster = imagecreatetruecolor( 2, 1 );
		imagealphablending( $raster, false );
		imagesavealpha( $raster, true );
		imagesetpixel( $raster, 0, 0, 0x00ff0000 );
		imagesetpixel( $raster, 1, 0, 0x7f000000 );
		imagepng( $raster, $png );
		imagedestroy( $raster );
		$svg = $this->temporary( 'svg' );
		file_put_contents( $svg, '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="#0000ff" fill-rule="evenodd" d="M0 0H20V20H0Z M5 5H15V15H5Z"/></svg>' );
		$lines = [ '0 1 0 setrgbcolor 0 0 72 36 rectfill' ];
		$art   = new ReflectionMethod( OC_Print_Embroidery::class, 'append_eps_image_or_reference' );
		$art->invokeArgs( null, [ &$lines, $png, 0.0, 0.0, 20.0, 10.0 ] );
		$art->invokeArgs( null, [ &$lines, $svg, 25.0, 0.0, 20.0, 20.0 ] );
		$input = [
			'value'    => 'O',
			'colorHex' => '#000000',
			'fontSize' => 20,
		];
		( new ReflectionMethod( OC_Print_Embroidery::class, 'append_eps_text' ) )->invokeArgs( null, [ &$lines, $input, [], 48.0, 0.0, 24.0, 36.0 ] );
		$output = [];
		foreach ( $lines as $line ) {
			if ( is_array( $line ) ) {
				$this->files[] = $line['oc_eps_fragment'];
				$output[]      = file_get_contents( $line['oc_eps_fragment'] );
			} else {
				$output[] = $line;
			}
		}
		$image = $this->render( implode( "\n", $output ) );
		$this->assertSame( 0xff0000, imagecolorat( $image, 5, 65 ) );
		$this->assertSame( 0x00ff00, imagecolorat( $image, 35, 65 ) );
		$this->assertSame( 0x0000ff, imagecolorat( $image, 54, 65 ) );
		$this->assertSame( 0x00ff00, imagecolorat( $image, 70, 50 ) );
		$black = 0;
		for ( $y = 0; $y < 72; $y++ ) {
			for ( $x = 96; $x < 144; $x++ ) {
				$black += 0 === imagecolorat( $image, $x, $y ) ? 1 : 0;
			}
		}
		$this->assertGreaterThan( 10, $black, 'Default-font customer text must actually paint.' );
		imagedestroy( $image );
	}

	#[Test]
	public function generation_automatically_falls_back_and_cleans_internal_files(): void {
		if ( function_exists( 'proc_open' ) && OC_Preview_Generator::find_ghostscript() ) {
			$this->markTestSkipped( 'Run with proc_open disabled to exercise backend selection on Ghostscript hosts.' );
		}
		$area          = (object) [
			'area_key'    => 'gd-test',
			'label'       => 'GD test',
			'canvas_unit' => 'mm',
			'canvas_w'    => 25.4,
			'canvas_h'    => 12.7,
		];
		$data          = [
			'text'  => 'Test',
			'color' => '#ff0000',
		];
		$result        = OC_Print_Embroidery::generate( new WC_Order(), 22, $area, $data );
		$this->files[] = $result['file_path'];
		$this->assertSame( 'files_ready', $result['status'] );
		$this->assertSame( IMAGETYPE_BMP, getimagesize( $result['file_path'] )[2] );
		$this->assertFileDoesNotExist( substr( $result['file_path'], 0, -4 ) . '.eps' );
		$this->assertNull( ( new ReflectionProperty( OC_Print_Embroidery::class, 'native_default_font' ) )->getValue() );
		$this->assertFalse( ( new ReflectionProperty( OC_Print_Embroidery::class, 'native_raster' ) )->getValue() );
	}

	#[Test]
	public function native_oval_mask_clips_artwork_and_restores_the_parent_clip(): void {
		( new ReflectionProperty( OC_Print_Embroidery::class, 'native_raster' ) )->setValue( null, true );
		$lines = [ 'gsave' ];
		( new ReflectionMethod( OC_Print_Embroidery::class, 'append_eps_clip_path' ) )->invokeArgs( null, [ &$lines, 10.0, 10.0, 40.0, 20.0, 'oval' ] );
		$lines[] = '1 0 0 setrgbcolor 0 0 72 36 rectfill grestore';
		$lines[] = '0 0 1 setrgbcolor 60 0 10 10 rectfill';
		$image   = $this->render( implode( "\n", $lines ) );
		$this->assertSame( 0xff0000, imagecolorat( $image, 60, 32 ) );
		$this->assertSame( 0xffffff, imagecolorat( $image, 21, 13 ) );
		$this->assertSame( 0x0000ff, imagecolorat( $image, 125, 65 ) );
		imagedestroy( $image );
	}

	#[Test]
	public function failed_native_generation_cleans_staged_font_and_drawing(): void {
		if ( function_exists( 'proc_open' ) && OC_Preview_Generator::find_ghostscript() ) {
			$this->markTestSkipped( 'Run with proc_open disabled to exercise fallback cleanup.' );
		}
		$dir    = ( new ReflectionMethod( OC_Print_Embroidery::class, 'ensure_output_dir' ) )->invoke( null, 1001 );
		$before = glob( $dir . '/*' );
		$fonts  = glob( sys_get_temp_dir() . '/oc-embroidery-font-*' );
		$area   = (object) [
			'area_key'    => 'gd-failure',
			'canvas_unit' => 'mm',
			'canvas_w'    => 25.4,
			'canvas_h'    => 12.7,
		];
		$data   = [
			'text'        => 'Test',
			'artworkPath' => '/missing-embroidery-artwork.png',
		];
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'selected production artwork path' );
		try {
			OC_Print_Embroidery::generate( new WC_Order(), 22, $area, $data );
		} finally {
			$this->assertSame( $before, glob( $dir . '/*' ) );
			$this->assertSame( $fonts, glob( sys_get_temp_dir() . '/oc-embroidery-font-*' ) );
			$this->assertNull( ( new ReflectionProperty( OC_Print_Embroidery::class, 'native_default_font' ) )->getValue() );
			$this->assertFalse( ( new ReflectionProperty( OC_Print_Embroidery::class, 'native_raster' ) )->getValue() );
		}
	}

	#[Test]
	public function native_geometry_matches_ghostscript_apart_from_edge_rasterisation(): void {
		$binary = OC_Preview_Generator::find_ghostscript();
		if ( ! $binary ) {
			$this->markTestSkipped( 'Ghostscript is required for the cross-backend comparison.' );
		}
		$font  = ( new ReflectionMethod( OC_Print_Embroidery::class, 'native_default_font' ) )->invoke( null );
		$lines = [
			'gsave 20 18 translate 30 rotate',
			'1 0 0 setrgbcolor -8 -8 16 16 rectfill grestore',
			'0 0 1 setrgbcolor',
		];
		( new ReflectionMethod( OC_Print_Embroidery::class, 'append_eps_ttf_text_outline' ) )->invokeArgs( null, [ &$lines, 'OB', 'left', 38.0, 8.0, 20.0, $font ] );
		$source = $this->temporary( 'eps' );
		$native = $this->temporary( 'bmp' );
		$gs     = $this->temporary( 'bmp' );
		file_put_contents( $source, "%!PS-Adobe-3.0 EPSF-3.0\n%%BoundingBox: 0 0 72 36\n" . implode( "\n", $lines ) . "\nshowpage\n" );
		OC_Embroidery_Raster::render( $source, $native );
		( new ReflectionMethod( OC_Print_Embroidery::class, 'render_bmp' ) )->invoke( null, $binary, $source, $gs );
		$a       = imagecreatefrombmp( $native );
		$b       = imagecreatefrombmp( $gs );
		$painted = 0;

		$interior_differences = 0;
		for ( $y = 0; $y < 72; $y++ ) {
			for ( $x = 0; $x < 144; $x++ ) {
				$ca       = imagecolorat( $a, $x, $y );
				$cb       = imagecolorat( $b, $x, $y );
				$painted += 0xffffff !== $ca || 0xffffff !== $cb ? 1 : 0;
				if ( $ca === $cb ) {
					continue;
				}
				// Ghostscript's any-part coverage and GD's pixel-centre coverage
				// can disagree at boundaries, but not more than one pixel inside.
				$near_a = false;
				$near_b = false;
				$max_y  = min( 71, $y + 1 );
				$max_x  = min( 143, $x + 1 );
				for ( $dy = max( 0, $y - 1 ); $dy <= $max_y; $dy++ ) {
					for ( $dx = max( 0, $x - 1 ); $dx <= $max_x; $dx++ ) {
						$near_a = $near_a || imagecolorat( $a, $dx, $dy ) === $cb;
						$near_b = $near_b || imagecolorat( $b, $dx, $dy ) === $ca;
					}
				}
				$interior_differences += $near_a && $near_b ? 0 : 1;
			}
		}
		$this->assertGreaterThan( 1000, $painted );
		$this->assertSame( 0, $interior_differences );
		imagedestroy( $a );
		imagedestroy( $b );
	}

	#[Test]
	public function arbitrary_postscript_is_rejected(): void {
		$this->expectException( RuntimeException::class );
		$this->render( '(untrusted) run' );
	}

	#[Test]
	public function oversized_artboard_is_rejected_before_allocation(): void {
		$source = $this->temporary( 'eps' );
		file_put_contents( $source, "%%BoundingBox: 0 0 3000 36\n" );
		$this->expectException( RuntimeException::class );
		OC_Embroidery_Raster::render( $source, $this->temporary( 'bmp' ) );
	}
}
