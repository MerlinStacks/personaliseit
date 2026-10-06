<?php
/**
 * Canonical vector proof and embroidery export regressions.
 *
 * @package OverCustomise
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once OC_PATH . 'includes/class-oc-artwork-vectoriser.php';

class Test_Artwork_Vectoriser extends TestCase {
	protected function setUp(): void {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			$this->markTestSkipped( 'GD is required.' );
		}
	}

	private function png( $image ): string {
		ob_start();
		imagepng( $image );
		$bytes = ob_get_clean();
		imagedestroy( $image );
		return $bytes;
	}

	/** Sample the filled SVG polygons independently of the production EPS parser. */
	private function colour_at( string $svg, float $x, float $y ): ?string {
		$dom = new DOMDocument();
		$dom->loadXML( $svg );
		$result = null;
		foreach ( $dom->getElementsByTagName( 'path' ) as $path ) {
			$inside = false;
			foreach ( explode( 'Z', $path->getAttribute( 'd' ) ) as $ring ) {
				preg_match_all( '/[ML](\d+) (\d+)/', $ring, $matches, PREG_SET_ORDER );
				for ( $i = 0, $j = count( $matches ) - 1; $i < count( $matches ); $j = $i++ ) {
					[ , $xi, $yi ] = $matches[$i];
					[ , $xj, $yj ] = $matches[$j];
					if ( ( $yi > $y ) !== ( $yj > $y ) && $x < ( $xj - $xi ) * ( $y - $yi ) / ( $yj - $yi ) + $xi ) {
						$inside = ! $inside;
					}
				}
			}
			if ( $inside ) { $result = $path->getAttribute( 'fill' ); }
		}
		return $result;
	}

	#[Test]
	public function white_background_is_removed_but_enclosed_white_details_survive(): void {
		$image = imagecreatetruecolor( 80, 60 );
		imagefilledrectangle( $image, 0, 0, 79, 59, 0xffffff );
		imagefilledrectangle( $image, 10, 10, 69, 49, 0xcc2222 );
		imagefilledrectangle( $image, 30, 20, 49, 39, 0xffffff );
		$svg = OC_Artwork_Vectoriser::trace( $this->png( $image ), 2, true );
		$this->assertNull( $this->colour_at( $svg, 2.5, 2.5 ) );
		$this->assertSame( '#cc2222', $this->colour_at( $svg, 15.5, 15.5 ) );
		$this->assertSame( '#ffffff', $this->colour_at( $svg, 35.5, 25.5 ) );
		$this->assertSame( 2, substr_count( $svg, '<path ' ) );
		$this->assertLessThan( 1000, strlen( $svg ) );
	}

	#[Test]
	public function faint_transparency_is_resolved_before_approval_and_survives_eps_export(): void {
		$image = imagecreatetruecolor( 60, 60 );
		imagealphablending( $image, false );
		imagesavealpha( $image, true );
		imagefilledrectangle( $image, 0, 0, 59, 59, imagecolorallocatealpha( $image, 0, 0, 0, 127 ) );
		imagefilledrectangle( $image, 10, 30, 49, 49, 0xcc2222 );
		// The old exporter dropped this entire pale "steam" shape (alpha >= 64).
		imagefilledrectangle( $image, 25, 5, 29, 24, imagecolorallocatealpha( $image, 100, 100, 100, 100 ) );
		$svg = OC_Artwork_Vectoriser::trace( $this->png( $image ), 2, false );
		$this->assertNotNull( $this->colour_at( $svg, 27.5, 12.5 ) );
		$this->assertNull( $this->colour_at( $svg, 2.5, 2.5 ) );
		$this->assertSame( $svg, OC_SVG_Sanitiser::sanitise( $svg ) );
		$path = tempnam( sys_get_temp_dir(), 'oc-vector-test-' );
		file_put_contents( $path, $svg );
		try {
			$lines = [];
			$method = new ReflectionMethod( OC_Print_Embroidery::class, 'append_eps_svg_vector' );
			$this->assertTrue( $method->invokeArgs( null, [ &$lines, $path, 0.0, 0.0, 60.0, 60.0, 'contain' ] ) );
			$eps = implode( "\n", $lines );
			$this->assertSame( 2, substr_count( $eps, 'eofill' ) );
			$this->assertStringNotContainsString( 'rectfill', $eps );
			$this->assertStringNotContainsString( 'colorimage', $eps );
		} finally {
			unlink( $path );
		}
	}

	#[Test]
	public function transparent_holes_and_separate_islands_are_not_filled_in(): void {
		$image = imagecreatetruecolor( 60, 60 );
		imagealphablending( $image, false );
		imagesavealpha( $image, true );
		$clear = imagecolorallocatealpha( $image, 0, 0, 0, 127 );
		imagefilledrectangle( $image, 0, 0, 59, 59, $clear );
		imagefilledrectangle( $image, 5, 5, 35, 35, 0xcc2222 );
		imagefilledrectangle( $image, 10, 10, 29, 29, $clear );
		imagefilledrectangle( $image, 40, 40, 49, 49, 0xcc2222 );
		imagesetpixel( $image, 55, 55, 0xcc2222 );
		$svg = OC_Artwork_Vectoriser::trace( $this->png( $image ), 1, false );
		$this->assertNull( $this->colour_at( $svg, 15.5, 15.5 ) );
		$this->assertNotNull( $this->colour_at( $svg, 7.5, 7.5 ) );
		$this->assertNotNull( $this->colour_at( $svg, 45.5, 45.5 ) );
		$this->assertNull( $this->colour_at( $svg, 55.5, 55.5 ) );
		$this->assertSame( 1, substr_count( $svg, '<path ' ) );
	}

	#[Test]
	public function textured_colours_are_limited_and_repeatable(): void {
		$image = imagecreatetruecolor( 120, 120 );
		for ( $y = 0; $y < 120; $y++ ) {
			for ( $x = 0; $x < 120; $x++ ) {
				$shade = ( $x * 13 + $y * 19 ) % 16;
				$rgb = $x < 60 ? ( ( 190 + $shade ) << 16 | 0x2222 ) : ( 0x222200 | ( 190 + $shade ) );
				imagesetpixel( $image, $x, $y, $rgb );
			}
		}
		$bytes = $this->png( $image );
		$svg = OC_Artwork_Vectoriser::trace( $bytes, 2, false );
		$this->assertSame( 2, substr_count( $svg, '<path ' ) );
		$this->assertLessThan( 1000, strlen( $svg ) );
		$this->assertSame( $svg, OC_Artwork_Vectoriser::trace( $bytes, 2, false ) );
	}

	#[Test]
	public function tiny_edge_fragments_are_merged_without_erasing_larger_details(): void {
		$image = imagecreatetruecolor( 1024, 1024 );
		imagefilledrectangle( $image, 0, 0, 1023, 1023, 0xffffff );
		imagefilledrectangle( $image, 40, 40, 980, 980, 0xcc2222 );
		imagefilledrectangle( $image, 200, 200, 249, 249, 0xee7777 );
		for ( $x = 60; $x < 950; $x += 12 ) {
			// Four-pixel-square colour islands on an edge: the former neighbour
			// filter retained these and each became a separate imported object.
			imagefilledrectangle( $image, $x, 40, $x + 3, 43, 0xee7777 );
		}
		$svg = OC_Artwork_Vectoriser::trace( $this->png( $image ), 2, true );
		$this->assertSame( 3, substr_count( $svg, 'Z' ), 'Count real contours, not colour-group path elements.' );
		$body = $this->colour_at( $svg, 100.5, 100.5 );
		$this->assertNotNull( $body );
		$this->assertSame( $body, $this->colour_at( $svg, 61.5, 41.5 ) );
		$this->assertNotSame( $body, $this->colour_at( $svg, 225.5, 225.5 ) );
		$this->assertNotNull( $this->colour_at( $svg, 225.5, 225.5 ) );
		$this->assertNull( $this->colour_at( $svg, 10.5, 10.5 ) );
	}

	#[Test]
	public function a_single_colour_group_cannot_hide_hundreds_of_separate_shapes(): void {
		$image = imagecreatetruecolor( 512, 512 );
		imagefilledrectangle( $image, 0, 0, 511, 511, 0xffffff );
		for ( $y = 0; $y < 15; $y++ ) {
			for ( $x = 0; $x < 20; $x++ ) {
				imagefilledrectangle( $image, 10 + $x * 20, 10 + $y * 20, 13 + $x * 20, 13 + $y * 20, 0xcc2222 );
			}
		}
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'too many separate shapes' );
		OC_Artwork_Vectoriser::trace( $this->png( $image ), 1, true );
	}

	#[Test]
	public function ai_vector_colour_override_keeps_paths_and_enclosed_white_shapes(): void {
		$path = tempnam( sys_get_temp_dir(), 'oc-vector-colour-' ) . '.svg';
		file_put_contents( $path, '<svg xmlns="http://www.w3.org/2000/svg" width="60" height="60"><path fill="#cc2222" d="M5 5L50 5L50 50L5 50Z"/><path fill="#ffffff" d="M20 20L30 20L30 30L20 30Z"/></svg>' );
		$output = null;
		try {
			$method = new ReflectionMethod( OC_Print_Embroidery::class, 'build_filtered_image' );
			$output = $method->invoke( null, $path, [ 'settings' => [ 'image_filter_ids' => [ 7 ], 'enable_image_colour' => true ] ], [ 'imageFilterId' => 7, 'imageFilterKey' => 'ai', 'colorHex' => '#123456' ] );
			$this->assertIsString( $output );
			$this->assertStringEndsWith( '.svg', $output );
			$dom = new DOMDocument();
			$dom->loadXML( file_get_contents( $output ) );
			$this->assertCount( 2, $dom->getElementsByTagName( 'path' ) );
			foreach ( $dom->getElementsByTagName( 'path' ) as $shape ) {
				$this->assertSame( '#123456', $shape->getAttribute( 'fill' ) );
			}
		} finally {
			unlink( $path );
			unlink( substr( $path, 0, -4 ) );
			if ( is_string( $output ) && is_file( $output ) ) { unlink( $output ); }
		}
	}

	#[Test]
	public function removing_background_from_a_transparent_image_preserves_black_artwork(): void {
		$image = imagecreatetruecolor( 40, 40 );
		imagealphablending( $image, false );
		imagesavealpha( $image, true );
		imagefilledrectangle( $image, 0, 0, 39, 39, imagecolorallocatealpha( $image, 0, 0, 0, 127 ) );
		imagefilledrectangle( $image, 10, 10, 29, 29, 0 );
		$svg = OC_Artwork_Vectoriser::trace( $this->png( $image ), 1, true );
		$this->assertSame( '#000000', $this->colour_at( $svg, 20.5, 20.5 ) );
		$this->assertNull( $this->colour_at( $svg, 2.5, 2.5 ) );
	}

	#[Test]
	public function empty_artwork_fails_instead_of_saving_a_blank_proof(): void {
		$image = imagecreatetruecolor( 20, 20 );
		imagefilledrectangle( $image, 0, 0, 19, 19, 0xffffff );
		$this->expectException( RuntimeException::class );
		OC_Artwork_Vectoriser::trace( $this->png( $image ), 3, true );
	}
}
