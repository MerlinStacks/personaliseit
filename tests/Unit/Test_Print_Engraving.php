<?php
/**
 * Unit tests for engraving print export helpers.
 *
 * @package OverCustomise
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

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

if ( ! class_exists( 'OC_Test_Engraving_PDF' ) && class_exists( 'TCPDF' ) ) {
	class OC_Test_Engraving_PDF extends TCPDF {
		public bool $image_svg_called = false;
		public int $image_svg_call_count = 0;
		public bool $image_called        = false;
		public string $image_svg         = '';
		/** @var array<int, array{x: float, y: float, w: float, h: float, svg: string}> */
		public array $image_svg_calls = [];

		public function Image( $file, $x = '', $y = '', $w = 0, $h = 0, $type = '', $link = '', $align = '', $resize = false, $dpi = 300, $palign = '', $ismask = false, $imgmask = false, $border = 0, $fitbox = false, $hidden = false, $fitonpage = false, $alt = false, $altimgs = [] ) {
			$this->image_called = true;
		}

		public function ImageSVG( $file, $x = '', $y = '', $w = 0, $h = 0, $link = '', $align = '', $palign = '', $border = 0, $fitonpage = false ) {
			$this->image_svg_called = true;
			++$this->image_svg_call_count;
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- TCPDF supplies a local temporary SVG path.
			$this->image_svg         = is_readable( (string) $file ) ? (string) file_get_contents( (string) $file ) : '';
			$this->image_svg_calls[] = [
				'x'   => (float) $x,
				'y'   => (float) $y,
				'w'   => (float) $w,
				'h'   => (float) $h,
				'svg' => $this->image_svg,
			];
		}
	}
}

if ( class_exists( 'OC_Test_Engraving_PDF' ) ) {
	class OC_Test_Retry_Engraving_PDF extends OC_Test_Engraving_PDF {
		public static int $attempts = 0;
		public function ImageSVG( $file, $x = '', $y = '', $w = 0, $h = 0, $link = '', $align = '', $palign = '', $border = 0, $fitonpage = false ) {
			if ( 2 === ++self::$attempts ) {
				throw new RuntimeException( 'Simulated second-line renderer failure' );
			}
			parent::ImageSVG( $file, $x, $y, $w, $h );
		}
	}
	class OC_Test_Verified_Engraving_PDF extends OC_Test_Engraving_PDF {
		public function AddFont( $family, $style = '', $fontfile = '', $subset = 'default' ) {
			throw new RuntimeException( 'PDF registration must not run' );
		}
	}
}

class Test_Print_Engraving extends TestCase {
	#[Test]
	public function vector_fallback_forwards_size_but_preserves_resource_coordinate_paths(): void {
		if ( class_exists( 'Imagick' ) ) {
			$this->markTestSkipped( 'This fallback regression requires the SVG raster backend to be unavailable.' );
		}
		$base = tempnam( sys_get_temp_dir(), 'oc-closure-fallback-' );
		$path = $base . '.svg';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Give the local temporary fixture its renderer-required extension.
		rename( $base, $path );
		$visible  = 'M0 0C10 0 10 10 0 .02Z';
		$resource = 'M0 0C1 0 1 1 0 .00001Z';
		try {
			foreach ( [ [ false, 20 ], [ false, 100 ], [ true, 20 ] ] as [ $complex, $size ] ) {
				$definitions = $complex ? '<defs><clipPath id="clip" clipPathUnits="objectBoundingBox"><path d="' . $resource . '"/></clipPath></defs>' : '';
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Write a local temporary SVG fixture without WordPress filesystem services.
				file_put_contents( $path, '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">' . $definitions . '<path id="visible" clip-path="' . ( $complex ? 'url(#clip)' : 'none' ) . '" d="' . $visible . '"/></svg>' );
				$output = OC_Print_Engraving::prepare_artwork_for_layer( $path, [], $size, $size );
				try {
					$this->assertSame( 'svg', pathinfo( $output, PATHINFO_EXTENSION ) );
					$dom = new DOMDocument();
					$dom->load( $output );
					$xpath  = new DOMXPath( $dom );
					$actual = $xpath->query( '//*[@id="visible"]' )[0]->getAttribute( 'd' );
					if ( $complex ) {
						$this->assertSame( $visible, $actual );
						$this->assertSame( $resource, $dom->getElementsByTagName( 'clipPath' )[0]->getElementsByTagName( 'path' )[0]->getAttribute( 'd' ) );
					} else {
						$this->assertSame( 'M 0 0 C 10 0 10 10 0 ' . ( 20 === $size ? '0' : '0.02' ) . ' Z', $actual );
					}
				} finally {
					if ( file_exists( $output ) ) {
						// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Clean up the local temporary renderer output.
						unlink( $output );
					}
				}
			}
		} finally {
			if ( file_exists( $path ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Clean up the local temporary SVG fixture.
				unlink( $path );
			}
		}
	}

	public static function verified_font_formats(): array {
		return [
			'TrueType'          => [ false, 9876123 ],
			'same-source WOFF1' => [ true, 9876124 ],
		];
	}

	#[Test]
	public function verified_partial_outlines_are_rolled_back_before_legacy_retry(): void {
		$font = getenv( 'OC_TEST_FONT_PATH' );
		$font = $font ? $font : '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
		if ( ! class_exists( 'TCPDF' ) || ! is_file( $font ) ) {
			$this->markTestSkipped( 'Actual TrueType font and TCPDF required.' );
		}
		$uploads = wp_upload_dir()['basedir'];
		wp_mkdir_p( $uploads );
		$path = tempnam( $uploads, 'oc-retry-font-' );
		copy( $font, $path );
		global $wpdb;
		$previous = $wpdb;
		$wpdb     = new class( basename( $path ) ) {
			public string $prefix = 'wp_';
			public function __construct( private string $path ) {}
			public function prepare( $query, ...$args ) {
				return $query;
			}
			public function get_row( $query ) {
				return (object) [
					'id'        => 9876126,
					'file_path' => $this->path,
				];
			}
		};
		try {
			OC_Test_Retry_Engraving_PDF::$attempts = 0;
			$pdf                                   = new OC_Test_Retry_Engraving_PDF();
			$pdf->AddPage();
			$input = [
				'value'                 => "Alex\nBob",
				'fontId'                => 9876126,
				'fontSize'              => 12,
				'renderedLayoutVersion' => 1,
				'renderedFontSize'      => 10,
				'renderedScaleX'        => 1,
				'renderedInsetX'        => 0,
				'renderedLines'         => [ 'Alex', 'Bob' ],
			];
			( new ReflectionMethod( OC_Print_Base::class, 'render_layer_text' ) )->invoke(
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
				40.0,
				40.0,
				'engraving',
				1.0
			);
			$this->assertSame( 4, OC_Test_Retry_Engraving_PDF::$attempts );
			$this->assertCount( 2, $pdf->image_svg_calls, 'Only the complete legacy retry should remain in the PDF.' );
		} finally {
			$wpdb = $previous;
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Unit test temporary-file cleanup.
			unlink( $path );
		}
	}

	public static function legacy_verified_font_sources(): array {
		return [
			'CFF companion'   => [ 'otf', 9876125 ],
			'WOFF2 companion' => [ 'woff2', 9876127 ],
			'absent source'   => [ 'missing', 9876128 ],
		];
	}

	#[Test]
	#[DataProvider( 'legacy_verified_font_sources' )]
	public function verified_source_uses_existing_companion_or_pdf_fallback( string $format, int $font_id ): void {
		$font = getenv( 'OC_TEST_FONT_PATH' );
		$font = $font ? $font : '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
		if ( ! is_file( $font ) ) {
			$this->markTestSkipped( 'Actual TrueType font required.' );
		}
		$uploads = wp_upload_dir()['basedir'];
		wp_mkdir_p( $uploads );
		$source = tempnam( $uploads, 'oc-cff-source-' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Unit test temporary-file cleanup.
		unlink( $source );
		$companion = $source . '-print.ttf';
		$source   .= '.' . $format;
		$temporary = null;
		global $wpdb;
		$previous = $wpdb;
		$wpdb     = new class( basename( $source ) ) {
			public string $prefix = 'wp_';
			public function __construct( private string $path ) {}
			public function prepare( $query, ...$args ) {
				return $query;
			}
			public function get_row( $query ) {
				return (object) [
					'id'        => 9876125,
					'file_path' => $this->path,
				];
			}
		};
		try {
			if ( 'missing' !== $format ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Write the local test fixture directly.
				file_put_contents( $source, ( 'woff2' === $format ? 'wOF2' : 'OTTO' ) . str_repeat( "\0", 64 ) );
				copy( $font, $companion );
				$this->assertSame( $companion, ( new ReflectionMethod( OC_Print_Base::class, 'get_print_companion_font_path' ) )->invoke( null, $source ) );
			}
			$pdf = new OC_Test_Engraving_PDF();
			$pdf->AddPage();
			$input = [
				'value'                 => 'Alex',
				'fontId'                => $font_id,
				'fontSize'              => 12,
				'renderedLayoutVersion' => 1,
				'renderedFontSize'      => 10,
				'renderedScaleX'        => 1,
				'renderedInsetX'        => 0,
			];
			( new ReflectionMethod( OC_Print_Base::class, 'render_layer_text' ) )->invoke(
				null,
				$pdf,
				[
					'type' => 'text',
					'h'    => 20,
				],
				$input,
				[],
				0.0,
				0.0,
				40.0,
				20.0,
				'engraving',
				1.0
			);
			if ( 'missing' === $format ) {
				$this->assertSame( 0, $pdf->image_svg_call_count );
				$this->assertStringStartsWith( '%PDF-', $pdf->Output( '', 'S' ) );
			} else {
				$this->assertSame( 1, $pdf->image_svg_call_count );
				$this->assertStringContainsString( '<path', $pdf->image_svg );
			}
		} finally {
			$wpdb = $previous;
			if ( is_file( $source ) ) {
				unlink( $source ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Unit test temporary-file cleanup.
			}
			if ( is_file( $companion ) ) {
				unlink( $companion ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Unit test temporary-file cleanup.
			}
		}
	}

	#[Test]
	#[DataProvider( 'verified_font_formats' )]
	public function verified_layer_uses_raw_font_without_pdf_registration_and_applies_outer_inset( bool $woff, int $font_id ): void {
		$font = getenv( 'OC_TEST_FONT_PATH' );
		$font = $font ? $font : '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
		if ( ! class_exists( 'TCPDF' ) || ! is_file( $font ) ) {
			$this->markTestSkipped( 'Actual TrueType font and TCPDF required.' );
		}
		$uploads = wp_upload_dir()['basedir'];
		wp_mkdir_p( $uploads );
		$path = tempnam( $uploads, 'oc-verified-font-' );
		if ( $woff ) {
			$this->assertTrue( OC_WOFF_Converter::convert( $font, $path ) );
		} else {
			copy( $font, $path );
		}
		global $wpdb;
		$previous = $wpdb;
		$wpdb     = new class( basename( $path ) ) {
			public string $prefix = 'wp_';
			public function __construct( private string $path ) {}
			public function prepare( $query, ...$args ) {
				return $query;
			}
			public function get_row( $query ) {
				return (object) [
					'id'        => 9876123,
					'file_path' => $this->path,
				];
			}
		};
		try {
			$pdf    = new OC_Test_Verified_Engraving_PDF();
			$method = new ReflectionMethod( OC_Print_Base::class, 'render_layer_text' );
			$input  = [
				'value'                 => 'Alex',
				'fontId'                => $font_id,
				'fontSize'              => 0,
				'renderedLayoutVersion' => 1,
				'renderedFontSize'      => 2.5,
				'renderedScaleX'        => 1,
				'renderedInsetX'        => 0.1,
				'renderedLines'         => [ 'Alex' ],
			];
			$method->invoke(
				null,
				$pdf,
				[
					'type' => 'textarea',
					'h'    => 20,
				],
				$input,
				[ 'alignment' => 'left' ],
				10.0,
				0.0,
				40.0,
				20.0,
				'engraving',
				1.0
			);
			$inset_call              = $pdf->image_svg_calls[0];
			$input['renderedInsetX'] = 0;
			$method->invoke(
				null,
				$pdf,
				[
					'type' => 'textarea',
					'h'    => 20,
				],
				$input,
				[ 'alignment' => 'left' ],
				10.0,
				0.0,
				40.0,
				20.0,
				'engraving',
				1.0
			);
			$this->assertEqualsWithDelta( 4.0, $inset_call['x'] - $pdf->image_svg_calls[1]['x'], 0.00001 );
			$this->assertSame( $inset_call['h'], $pdf->image_svg_calls[1]['h'] );
			$this->assertStringContainsString( 'scale(1.00000000 1.00000000)', $inset_call['svg'] );
			// The decoded WOFF must emit exactly the same glyph paths and placement
			// as the original fixture, not a same-family or adjacent print companion.
			$reference = new OC_Test_Engraving_PDF();
			( new ReflectionMethod( OC_Print_Base::class, 'render_engraving_multiline_text_outline' ) )->invoke( null, $reference, 'Alex', $font, 2.5, 10.0, 0.0, 40.0, 20.0, 'L', 'T', [ 'Alex' ], true );
			$this->assertSame( $reference->image_svg_calls[0], $pdf->image_svg_calls[1] );
		} finally {
			$wpdb = $previous;
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Unit test temporary-file cleanup.
			unlink( $path );
		}
	}

	#[Test]
	public function verified_outline_compresses_only_x_and_keeps_small_type_and_blank_lines(): void {
		$font = getenv( 'OC_TEST_FONT_PATH' );
		$font = $font ? $font : '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
		if ( ! class_exists( 'TCPDF' ) || ! is_file( $font ) ) {
			$this->markTestSkipped( 'Actual TrueType font and TCPDF required.' );
		}
		$pdf    = new OC_Test_Engraving_PDF();
		$single = new ReflectionMethod( OC_Print_Base::class, 'render_engraving_text_outline' );
		$this->assertTrue( $single->invoke( null, $pdf, 'Alex', $font, 2.5, 0.0, 0.0, 0.1, 0.1, 'C', 0.4 ) );
		$this->assertStringContainsString( 'scale(0.40000000 1.00000000)', $pdf->image_svg );
		$multi = new ReflectionMethod( OC_Print_Base::class, 'render_engraving_multiline_text_outline' );
		$this->assertTrue( $multi->invoke( null, $pdf, "Alex\n\nBob", $font, 2.5, 0.0, 0.0, 0.1, 0.1, 'L', 'T', [ 'Alex', '', 'Bob' ], true ) );
		$this->assertCount( 3, $pdf->image_svg_calls );
		$this->assertStringContainsString( 'scale(1.00000000 1.00000000)', $pdf->image_svg );
		// Different glyph bounds cancel when reconstructing each typographic baseline.
		$baselines = [];
		foreach ( array_slice( $pdf->image_svg_calls, 1 ) as $call ) {
			preg_match( '/translate\([\d.-]+ ([\d.-]+)\)/', $call['svg'], $match );
			$baselines[] = $call['y'] + (float) $match[1] * 25.4 / 72;
		}
		$this->assertEqualsWithDelta( 2 * 2.5 * 1.13 * 1.16 * 25.4 / 72, $baselines[1] - $baselines[0], 0.0001 );
	}

	#[Test]
	public function multiline_outline_failure_after_emission_is_explicit(): void {
		$font = getenv( 'OC_TEST_FONT_PATH' );
		$font = $font ? $font : '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
		if ( ! class_exists( 'TCPDF' ) || ! is_file( $font ) ) {
			$this->markTestSkipped( 'Actual TrueType font and TCPDF required.' );
		}
		$pdf = new class() extends OC_Test_Engraving_PDF {
			public function ImageSVG( $file, $x = '', $y = '', $w = 0, $h = 0, $link = '', $align = '', $palign = '', $border = 0, $fitonpage = false ) {
				if ( $this->image_svg_called ) {
					throw new RuntimeException( 'Second line failed' );
				}
				parent::ImageSVG( $file, $x, $y, $w, $h );
			}
		};
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'nonblank text line' );
		( new ReflectionMethod( OC_Print_Base::class, 'render_engraving_multiline_text_outline' ) )->invoke( null, $pdf, "Alex\nBob", $font, 2.5, 0.0, 0.0, 40.0, 40.0, 'L', 'T', [ 'Alex', 'Bob' ] );
	}

	#[Test]
	public function legacy_generators_embed_artwork_at_each_areas_physical_dimensions(): void {
		if ( ! class_exists( 'TCPDF' ) || ! function_exists( 'imagecreatetruecolor' ) ) {
			$this->markTestSkipped( 'TCPDF and GD are required.' );
		}
		$upload_dir = wp_upload_dir()['basedir'];
		if ( ! is_dir( $upload_dir ) ) {
			mkdir( $upload_dir, 0755, true );
		}
		$source = tempnam( $upload_dir, 'oc-legacy-' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Unit test temporary-file cleanup.
		unlink( $source );
		$source .= '.png';
		$image   = imagecreatetruecolor( 40, 20 );
		imagepng( $image, $source );
		imagedestroy( $image );
		$order = $this->createStub( WC_Order::class );
		$order->method( 'get_id' )->willReturn( 987654 );
		$order->method( 'get_order_number' )->willReturn( 'engraving-regression' );
		$area   = (object) [
			'label'       => 'Front',
			'canvas_unit' => 'mm',
			'canvas_w'    => 25.4,
			'canvas_h'    => 12.7,
		];
		$data   = [ 'artworkPath' => $source ];
		$output = '';
		try {
			$output = OC_Print_Engraving::generate( $order, 1, $area, $data );
			$raw    = file_get_contents( $output ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Read the local generated PDF fixture.
			$this->assertMatchesRegularExpression( '/\/Width\s+600\b/', $raw );
			$this->assertMatchesRegularExpression( '/\/Height\s+300\b/', $raw );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Unit test temporary-file cleanup.
			unlink( $output );
			$output           = '';
			$second           = clone $area;
			$second->canvas_w = 12.7;
			$second->canvas_h = 6.35;
			$output           = OC_Print_Engraving::generate_combined(
				$order,
				1,
				[
					[
						'area'      => $area,
						'area_data' => $data,
					],
					[
						'area'      => $second,
						'area_data' => $data,
					],
				]
			);
			$raw              = file_get_contents( $output ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Read the local generated PDF fixture.
			$this->assertMatchesRegularExpression( '/\/Width\s+600\b/', $raw );
			$this->assertMatchesRegularExpression( '/\/Height\s+300\b/', $raw );
			$this->assertMatchesRegularExpression( '/\/Width\s+300\b/', $raw );
			$this->assertMatchesRegularExpression( '/\/Height\s+150\b/', $raw );
		} finally {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Unit test temporary-file cleanup.
			unlink( $source );
			if ( '' !== $output && file_exists( $output ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Unit test temporary-file cleanup.
				unlink( $output );
			}
		}
	}

	public static function svg_viewports(): array {
		return [
			'large intrinsic canvas'   => [ 'width="1000000" height="500000"', 600, 300, '0 0 1000000 500000', 600, 300 ],
			'viewBox origin preserved' => [ 'viewBox="10 20 100 50"', 6000, 3000, '10 20 100 50', 6000, 3000 ],
			'dimension budget'         => [ 'viewBox="0 0 100 1"', 24000, 240, '0 0 100 1', 12000, 120 ],
			'pixel budget'             => [ 'viewBox="0 0 1 1"', 12000, 12000, '0 0 1 1', 6324, 6324 ],
			'intrinsic fallback'       => [ 'width="6000" height="30"', 0, 0, '0 0 6000 30', 6000, 30 ],
			'pixels'                   => [ 'width="96px" height="48px"', 600, 300, '0 0 96 48', 600, 300 ],
			'millimetres'              => [ 'width="25.4mm" height="12.7mm"', 600, 300, '0 0 96 48', 600, 300 ],
			'centimetres'              => [ 'width="2.54cm" height="1.27cm"', 600, 300, '0 0 96 48', 600, 300 ],
			'inches'                   => [ 'width="1in" height="0.5in"', 600, 300, '0 0 96 48', 600, 300 ],
			'points'                   => [ 'width="72pt" height="36pt"', 600, 300, '0 0 96 48', 600, 300 ],
			'picas'                    => [ 'width="6pc" height="3pc"', 600, 300, '0 0 96 48', 600, 300 ],
		];
	}

	#[Test]
	#[DataProvider( 'svg_viewports' )]
	public function svg_viewport_is_bounded_before_decoding( string $attributes, int $width, int $height, string $view_box, int $expected_width, int $expected_height ): void {
		$path = tempnam( sys_get_temp_dir(), 'oc-svg-viewport-' );
		try {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Write the local test fixture directly.
			file_put_contents( $path, '<svg xmlns="http://www.w3.org/2000/svg" ' . $attributes . '><path d="M0 0L1 1"/></svg>' );
			$dom = ( new ReflectionMethod( OC_Print_Engraving::class, 'engraving_svg_viewport' ) )->invoke( null, $path, $width, $height );
			$svg = $dom->documentElement; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM property.
			$this->assertSame( (string) $expected_width, $svg->getAttribute( 'width' ) );
			$this->assertSame( (string) $expected_height, $svg->getAttribute( 'height' ) );
			$this->assertSame( $view_box, $svg->getAttribute( 'viewBox' ) );
		} finally {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Unit test temporary-file cleanup.
			unlink( $path );
		}
	}

	#[Test]
	public function direct_svg_path_uses_the_base_validation_gate_even_without_imagick(): void {
		$path = tempnam( sys_get_temp_dir(), 'oc-unsafe-svg-' );
		try {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Write the local test fixture directly.
			file_put_contents( $path, '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><image href="https://example.com/image.png"/></svg>' );
			$this->expectException( RuntimeException::class );
			$this->expectExceptionMessage( 'External SVG resources' );
			( new ReflectionMethod( OC_Print_Engraving::class, 'open_svg_image_resource' ) )->invoke( null, $path, 600, 300 );
		} finally {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Unit test temporary-file cleanup.
			unlink( $path );
		}
	}

	#[Test]
	public function svg_decoder_renders_the_requested_viewport_without_the_4096_working_cap(): void {
		if ( ! class_exists( 'Imagick' ) || ! function_exists( 'imagecreatefromstring' ) || ! Imagick::queryFormats( 'SVG' ) ) {
			$this->markTestSkipped( 'Imagick with SVG support and GD are required.' );
		}
		$path  = tempnam( sys_get_temp_dir(), 'oc-svg-decode-' );
		$image = false;
		try {
			// A huge intrinsic viewport must never be allocated by the SVG decoder.
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Write the local test fixture directly.
			file_put_contents( $path, '<svg xmlns="http://www.w3.org/2000/svg" width="1000000" height="10000" viewBox="0 0 100 1"><rect x="25" width="50" height="1" fill="black"/></svg>' );
			$image = ( new ReflectionMethod( OC_Print_Engraving::class, 'open_svg_image_resource' ) )->invoke( null, $path, 6000, 60 );
			$this->assertNotFalse( $image );
			$this->assertSame( 6000, imagesx( $image ) );
			$this->assertSame( 60, imagesy( $image ) );
			$this->assertSame( 127, ( imagecolorat( $image, 0, 30 ) >> 24 ) & 127 );
			$this->assertSame( 0, ( imagecolorat( $image, 3000, 30 ) >> 24 ) & 127 );
		} finally {
			if ( $image ) {
				imagedestroy( $image );
			}
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Unit test temporary-file cleanup.
			unlink( $path );
		}
	}

	#[Test]
	public function indexed_png_is_promoted_before_alpha_analysis_without_resizing(): void {
		if ( ! function_exists( 'imagecreate' ) ) {
			$this->markTestSkipped( 'GD is not available.' );
		}
		$path = tempnam( __DIR__, 'oc-indexed-' ) . '.png';
		$base = substr( $path, 0, -4 );
		$image = imagecreate( 1201, 2 );
		$transparent = imagecolorallocate( $image, 0, 0, 0 );
		$white = imagecolorallocate( $image, 255, 255, 255 );
		imagecolortransparent( $image, $transparent );
		imagesetpixel( $image, 1200, 1, $white );
		imagepng( $image, $path );
		imagedestroy( $image );
		$source = false;
		try {
			$source = ( new ReflectionMethod( OC_Print_Engraving::class, 'open_image_resource' ) )->invoke( null, $path, 20, 20 );
			$this->assertTrue( imageistruecolor( $source ) );
			$this->assertSame( 1201, imagesx( $source ) );
			$this->assertSame( 127, ( imagecolorat( $source, 0, 0 ) >> 24 ) & 127 );
			$this->assertTrue( ( new ReflectionMethod( OC_Print_Engraving::class, 'is_transparent_logo' ) )->invoke( null, $source ) );
		} finally {
			if ( $source ) {
				imagedestroy( $source );
			}
			unlink( $path );
			unlink( $base );
		}
	}

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
	public function large_engraving_uses_the_configured_dpi_beyond_the_generic_working_limit(): void {
		$method = new ReflectionMethod( OC_Print_Engraving::class, 'engraving_raster_dimensions' );
		$size   = $method->invoke( null, [ 'dpi' => 600 ], 254.0, 127.0 );

		$this->assertSame( [ 6000, 3000 ], $size );
	}

	#[Test]
	public function engraving_keeps_safe_source_pixels_beyond_the_generic_working_limit(): void {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			$this->markTestSkipped( 'GD is not available.' );
		}

		$temp   = tempnam( sys_get_temp_dir(), 'oc-photo-source-' );
		$source = $temp . '.png';
		unlink( $temp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Unit test temporary-file cleanup.
		$image = imagecreatetruecolor( 4100, 10 );
		imagepng( $image, $source );
		imagedestroy( $image );

		$opened = false;
		try {
			$method = new ReflectionMethod( OC_Print_Engraving::class, 'open_image_resource' );
			$opened = $method->invoke( null, $source );
			$this->assertNotFalse( $opened );
			$this->assertSame( 4100, imagesx( $opened ) );
			$this->assertSame( 10, imagesy( $opened ) );
		} finally {
			if ( false !== $opened ) {
				imagedestroy( $opened );
			}
			if ( file_exists( $source ) ) {
				unlink( $source ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Unit test temporary-file cleanup.
			}
		}
	}

	#[Test]
	public function engraving_layer_text_renders_as_font_independent_svg_path(): void {
		$font_path = getenv( 'OC_TEST_FONT_PATH' );
		$font_path = $font_path ? $font_path : '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
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
	public function engraving_outline_uses_fabric_baseline_independent_of_glyph_bounds(): void {
		$font_path = getenv( 'OC_TEST_FONT_PATH' );
		$font_path = $font_path ? $font_path : '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
		if ( ! class_exists( 'TCPDF' ) || ! file_exists( $font_path ) ) {
			$this->markTestSkipped( 'TCPDF or DejaVuSans.ttf is not available.' );
		}

		$pdf    = ( new ReflectionClass( OC_Test_Engraving_PDF::class ) )->newInstanceWithoutConstructor();
		$method = new ReflectionMethod( OC_Print_Base::class, 'render_engraving_text_outline' );
		foreach ( [ 'BRIDE', 'gjpqy' ] as $text ) {
			$this->assertTrue( $method->invokeArgs( null, [ $pdf, $text, $font_path, 18.0, 0.0, 0.0, 80.0, 20.0, 'C' ] ) );
		}

		$baselines = array_map(
			static function ( array $call ): float {
				preg_match( '/translate\([0-9.-]+ ([0-9.-]+)\)/', $call['svg'], $matches );
				return $call['y'] * 72 / 25.4 + (float) $matches[1];
			},
			$pdf->image_svg_calls
		);

		$this->assertCount( 2, $baselines );
		$this->assertEqualsWithDelta( $baselines[0], $baselines[1], 0.001 );
	}

	#[Test]
	public function constrained_engraving_textarea_keeps_its_bottom_line(): void {
		$font_path = getenv( 'OC_TEST_FONT_PATH' );
		$font_path = $font_path ? $font_path : '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
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
	public function single_line_engraving_textarea_honours_vertical_alignment(): void {
		$font_path = getenv( 'OC_TEST_FONT_PATH' );
		$font_path = $font_path ? $font_path : '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
		if ( ! class_exists( 'TCPDF' ) || ! file_exists( $font_path ) ) {
			$this->markTestSkipped( 'TCPDF or DejaVuSans.ttf is not available.' );
		}

		$method     = new ReflectionMethod( OC_Print_Base::class, 'render_engraving_multiline_text_outline' );
		$top_pdf    = ( new ReflectionClass( OC_Test_Engraving_PDF::class ) )->newInstanceWithoutConstructor();
		$bottom_pdf = ( new ReflectionClass( OC_Test_Engraving_PDF::class ) )->newInstanceWithoutConstructor();
		$args       = [ 'Let there be Rock!', $font_path, 18.0, 0.0, 2.0, 80.0, 20.0, 'C' ];

		$this->assertTrue( $method->invokeArgs( null, [ $top_pdf, ...$args, 'T', [ 'Let there be Rock!' ] ] ) );
		$this->assertTrue( $method->invokeArgs( null, [ $bottom_pdf, ...$args, 'B', [ 'Let there be Rock!' ] ] ) );
		$this->assertCount( 1, $top_pdf->image_svg_calls );
		$this->assertCount( 1, $bottom_pdf->image_svg_calls );
		$this->assertGreaterThan( $top_pdf->image_svg_calls[0]['y'], $bottom_pdf->image_svg_calls[0]['y'] );
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
