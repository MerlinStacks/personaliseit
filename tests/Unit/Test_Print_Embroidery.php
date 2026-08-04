<?php
/**
 * Unit tests for embroidery EPS export helpers.
 *
 * @package OverCustomise
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

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

class Test_Print_Embroidery extends TestCase {
	#[Test]
	public function image_crop_uses_the_same_contain_to_cover_interpolation(): void {
		$method = new ReflectionMethod( OC_Print_Embroidery::class, 'fit_eps_box' );

		$this->assertEqualsWithDelta( [ 0.0, 25.0, 100.0, 50.0 ], $method->invoke( null, 200.0, 100.0, 0.0, 0.0, 100.0, 100.0, 0.0 ), 0.001 );
		$this->assertEqualsWithDelta( [ -25.0, 12.5, 150.0, 75.0 ], $method->invoke( null, 200.0, 100.0, 0.0, 0.0, 100.0, 100.0, 0.5 ), 0.001 );
		$this->assertEqualsWithDelta( [ -50.0, 0.0, 200.0, 100.0 ], $method->invoke( null, 200.0, 100.0, 0.0, 0.0, 100.0, 100.0, 1.0 ), 0.001 );
	}

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
	public function text_export_marks_font_dependent_fallback_when_no_font_file_is_available(): void {
		$lines  = [];
		$method = new ReflectionMethod( OC_Print_Embroidery::class, 'append_eps_text' );
		$method->invokeArgs( null, [ &$lines, [ 'value' => 'Editable Text', 'colorHex' => '#123456' ], [], 0.0, 0.0, 100.0, 20.0, true ] );

		$output = implode( "\n", $lines );
		$this->assertStringContainsString( '(Editable Text)', $output );
		$this->assertStringContainsString( '0.0000 16.9500 translate', $output );
		$this->assertStringNotContainsString( ' exch div 1 scale', $output );
		$this->assertStringContainsString( '%%OCTextOutlineFallback: font-dependent', $output );
		$this->assertStringContainsString( 'charpath fill', $output );
		$this->assertStringNotContainsString( ' show', $output );
		$this->assertStringNotContainsString( 'imagemask', $output );
	}

	#[Test]
	public function text_export_can_embed_real_ttf_glyph_paths(): void {
		$font_path = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
		if ( ! file_exists( $font_path ) ) {
			$this->markTestSkipped( 'DejaVuSans.ttf is not available.' );
		}

		$lines  = [];
		$method = new ReflectionMethod( OC_Print_Embroidery::class, 'append_eps_ttf_text_outline' );
		$ok     = $method->invokeArgs( null, [ &$lines, 'Tellaasd', 'left', 0.0, 439.0, 72.0, $font_path, -50.0, -50.0, 100.0, 100.0 ] );

		$output = implode( "\n", $lines );
		$this->assertTrue( $ok );
		$this->assertStringContainsString( '%%OCTextOutline: glyph-paths', $output );
		$this->assertStringContainsString( '%%OCTextFontFile: DejaVuSans.ttf', $output );
		$this->assertStringContainsString( '%%OCTextFitScale:', $output );
		$this->assertMatchesRegularExpression( '/0\.[0-9]+ 0\.[0-9]+ scale/', $output );
		$this->assertStringContainsString( 'curveto', $output );
		$this->assertStringContainsString( 'fill', $output );
		$this->assertStringNotContainsString( '0.0000 439.0000 translate', $output );
		$this->assertStringNotContainsString( '(Tellaasd)', $output );
		$this->assertStringNotContainsString( 'charpath', $output );
		$this->assertStringNotContainsString( 'findfont', $output );
	}

	#[Test]
	public function layer_export_scales_mockup_layer_coordinates_into_physical_area_units(): void {
		$lines = [];
		$area  = (object) [
			'canvas_unit' => 'mm',
			'canvas_x'    => 100,
			'canvas_y'    => 200,
			'canvas_w'    => 100,
			'canvas_h'    => 50,
		];
		$data  = [
			'text'   => '',
			'bounds' => [ 'x' => 100, 'y' => 200, 'w' => 1000, 'h' => 500, 'rotation' => 0 ],
			'layers' => [
				[
					'type'     => 'text',
					'x'        => 350,
					'y'        => 325,
					'w'        => 500,
					'h'        => 250,
					'input'    => [ 'value' => 'Scaled', 'colorHex' => '#000000' ],
					'settings' => [ 'default_font_size' => 200 ],
				],
			],
		];

		$method = new ReflectionMethod( OC_Print_Embroidery::class, 'append_eps_layers' );
		$method->invokeArgs( null, [ &$lines, $area, $data ] );

		$output = implode( "\n", $lines );
		$this->assertStringContainsString( '141.7323 70.8661 translate', $output );
		$this->assertStringContainsString( '/Helvetica findfont 42.6929 scalefont setfont', $output );
	}

	#[Test]
	public function layer_export_paints_top_layers_last(): void {
		$lines = [];
		$area  = (object) [
			'canvas_unit' => 'px',
			'canvas_x'    => 0,
			'canvas_y'    => 0,
			'canvas_w'    => 100,
			'canvas_h'    => 100,
		];
		$data  = [
			'text'   => '',
			'bounds' => [ 'x' => 0, 'y' => 0, 'w' => 100, 'h' => 100, 'rotation' => 0 ],
			'layers' => [
				[
					'type'  => 'lineart',
					'x'     => 0,
					'y'     => 0,
					'w'     => 50,
					'h'     => 50,
					'input' => [ 'colorHex' => '#111111' ],
				],
				[
					'type'  => 'lineart',
					'x'     => 50,
					'y'     => 50,
					'w'     => 50,
					'h'     => 50,
					'input' => [ 'colorHex' => '#222222' ],
				],
			],
		];

		$method = new ReflectionMethod( OC_Print_Embroidery::class, 'append_eps_layers' );
		$method->invokeArgs( null, [ &$lines, $area, $data ] );

		$output = implode( "\n", $lines );
		$this->assertLessThan( strpos( $output, '%%OCLineartColor: #222222' ), strpos( $output, '%%OCLineartColor: #111111' ) );
	}

	#[Test]
	public function recoloured_svg_clipart_keeps_svg_extension_and_renders_vector(): void {
		$source_base = tempnam( sys_get_temp_dir(), 'oc-svg-source-' );
		$source      = $source_base . '.svg';
		file_put_contents( $source, '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 20"><rect x="0" y="0" width="10" height="20" fill="#ffffff" stroke="#ffffff"/><rect x="1" y="2" width="3" height="4" fill="#000000"/></svg>' );

		$build = new ReflectionMethod( OC_Print_Embroidery::class, 'build_coloured_svg' );
		$path  = $build->invoke( null, $source, '#FF0000' );

		$this->assertIsString( $path );
		$this->assertSame( 'svg', strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) );

		$lines  = [];
		$append = new ReflectionMethod( OC_Print_Embroidery::class, 'append_eps_svg_vector' );
		$this->assertTrue( $append->invokeArgs( null, [ &$lines, $path, 0.0, 0.0, 20.0, 20.0 ] ) );

		$output = implode( "\n", $lines );
		$this->assertStringContainsString( '2.5000 20.0000 translate', $output );
		$this->assertStringContainsString( '-1.0000 -2.0000 translate', $output );
		$this->assertStringContainsString( 'setrgbcolor', $output );
		$this->assertStringContainsString( 'fill', $output );
		$this->assertStringNotContainsString( '0.0000 0.0000 moveto', $output );
		$this->assertStringNotContainsString( 'Clipart:', $output );

		@unlink( $source_base );
		@unlink( $source );
		@unlink( $path );
	}

	#[Test]
	public function clipart_layers_are_not_manually_offset_from_text_layers(): void {
		$uploads_dir = wp_upload_dir()['basedir'];
		wp_mkdir_p( $uploads_dir );
		$source_base = tempnam( $uploads_dir, 'oc-svg-source-' );
		$source      = $source_base . '.svg';
		file_put_contents( $source, '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect x="0" y="0" width="10" height="10" fill="#000000"/></svg>' );

		$lines = [];
		$layer = [
			'type'        => 'clipart',
			'artworkPath' => $source,
			'input'       => [],
		];
		$method = new ReflectionMethod( OC_Print_Embroidery::class, 'append_eps_artwork' );
		$method->invokeArgs( null, [ &$lines, $layer, -10.0, -10.0, 20.0, 20.0, 'contain' ] );

		$output = implode( "\n", $lines );
		$this->assertMatchesRegularExpression( '/-10\.0000 (?:-10\.0000|10\.0000) translate/', $output );
		$this->assertStringNotContainsString( '-10.0000 9.4000 translate', $output );

		@unlink( $source_base );
		@unlink( $source );
	}

	#[Test]
	public function embroidery_eps_filenames_are_versioned_for_regeneration(): void {
		$method = new ReflectionMethod( OC_Print_Embroidery::class, 'build_versioned_filename' );
		$order  = $this->createMock( \WC_Order::class );
		$order->method( 'get_id' )->willReturn( 1001 );
		$order->method( 'get_order_number' )->willReturn( '1001' );

		$filename = $method->invoke( null, $order, 123, (object) [ 'id' => 10, 'area_key' => 'front area' ], 'eps' );

		$this->assertMatchesRegularExpression( '/^1001-p1-\d{14}-[a-f0-9-]{8}\.eps$/', $filename );
		$this->assertNotSame( '1001-p1.eps', $filename );
	}

	#[Test]
	public function embroidery_generation_uses_layer_payload_for_eps_export(): void {
		$output_dir = sys_get_temp_dir() . '/oc-embroidery-test-' . uniqid();
		mkdir( $output_dir );

		$area = (object) [
			'id'          => 12,
			'area_key'    => 'front',
			'label'       => 'Front',
			'canvas_unit' => 'px',
			'canvas_x'    => 0,
			'canvas_y'    => 0,
			'canvas_w'    => 100,
			'canvas_h'    => 100,
		];
		$data = [
			'text'     => '',
			'color'    => '#000000',
			'bounds'   => [ 'x' => 0, 'y' => 0, 'w' => 100, 'h' => 100, 'rotation' => 0 ],
			'snapshot' => [
				'format' => 'fabric-svg-v1',
				'svg'    => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect x="0" y="0" width="10" height="10" fill="#ff0000"/></svg>',
			],
			'layers'   => [
				[
					'type'     => 'text',
					'x'        => 10,
					'y'        => 10,
					'w'        => 80,
					'h'        => 30,
					'input'    => [ 'value' => 'Layer Text', 'colorHex' => '#000000' ],
					'settings' => [],
				],
			],
		];

		$method = new ReflectionMethod( OC_Print_Embroidery::class, 'generate_eps' );
		$path   = $method->invokeArgs( null, [ $output_dir, new WC_Order(), 22, $area, $data ] );
		$output = file_get_contents( $path );

		$this->assertStringContainsString( '%%OCExportMode: layer-payload', $output );
		$this->assertStringNotContainsString( '%%OCSnapshotFormat: fabric-svg-v1', $output );
		$this->assertStringContainsString( 'Layer Text', $output );

		@unlink( $path );
		@rmdir( $output_dir );
	}

	#[Test]
	public function embroidery_generation_ignores_snapshot_for_legacy_payload(): void {
		$output_dir = sys_get_temp_dir() . '/oc-embroidery-test-' . uniqid();
		mkdir( $output_dir );

		$area = (object) [
			'area_key'    => 'front',
			'label'       => 'Front',
			'canvas_unit' => 'px',
			'canvas_w'    => 100,
			'canvas_h'    => 100,
		];
		$data = [
			'text'     => 'Legacy Text',
			'color'    => '#000000',
			'snapshot' => [
				'format' => 'fabric-svg-v1',
				'svg'    => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect x="0" y="0" width="10" height="10" fill="#ff0000"/></svg>',
			],
		];

		$method = new ReflectionMethod( OC_Print_Embroidery::class, 'generate_eps' );
		$path   = $method->invokeArgs( null, [ $output_dir, new WC_Order(), 23, $area, $data ] );
		$output = file_get_contents( $path );

		$this->assertStringContainsString( '%%OCExportMode: legacy-artwork', $output );
		$this->assertStringNotContainsString( '%%OCSnapshotFormat: fabric-svg-v1', $output );
		$this->assertStringContainsString( 'Legacy Text', $output );

		@unlink( $path );
		@rmdir( $output_dir );
	}

	#[Test]
	public function embroidery_generation_does_not_draw_a_white_overflow_mask(): void {
		$output_dir = sys_get_temp_dir() . '/oc-embroidery-test-' . uniqid();
		mkdir( $output_dir );

		$area = (object) [
			'area_key'    => 'front',
			'label'       => 'Front',
			'canvas_unit' => 'mm',
			'canvas_w'    => 100,
			'canvas_h'    => 50,
		];

		$method = new ReflectionMethod( OC_Print_Embroidery::class, 'generate_eps' );
		$path   = $method->invokeArgs( null, [ $output_dir, new WC_Order(), 24, $area, [ 'text' => 'Artwork', 'color' => '#000000' ] ] );
		$output = file_get_contents( $path );

		$this->assertStringContainsString( '%%BoundingBox: 0 0 284 142', $output );
		$this->assertStringNotContainsString( '-400.0000 -400.0000', $output );
		$this->assertStringNotContainsString( '1 1 1 setrgbcolor', $output );
		$this->assertStringNotContainsString( 'clip', $output );

		@unlink( $path );
		@rmdir( $output_dir );
	}

	#[Test]
	public function embroidery_generation_rejects_empty_artwork(): void {
		$output_dir = sys_get_temp_dir() . '/oc-embroidery-test-' . uniqid();
		mkdir( $output_dir );
		$area = (object) [
			'area_key'    => 'front',
			'label'       => 'Front',
			'canvas_unit' => 'mm',
			'canvas_w'    => 100,
			'canvas_h'    => 50,
		];
		$method = new ReflectionMethod( OC_Print_Embroidery::class, 'generate_eps' );

		try {
			$method->invokeArgs( null, [ $output_dir, new WC_Order(), 25, $area, [] ] );
			$this->fail( 'Empty embroidery artwork should not produce a file.' );
		} catch ( RuntimeException $e ) {
			$this->assertStringContainsString( 'no printable artwork', $e->getMessage() );
		} finally {
			@rmdir( $output_dir );
		}
	}

	#[Test]
	public function transparent_raster_export_draws_visible_runs_without_white_matte(): void {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			$this->markTestSkipped( 'GD is not available.' );
		}

		$image = imagecreatetruecolor( 2, 2 );
		imagealphablending( $image, false );
		imagesavealpha( $image, true );
		$transparent = imagecolorallocatealpha( $image, 0, 0, 0, 127 );
		$red         = imagecolorallocatealpha( $image, 255, 0, 0, 0 );
		imagefilledrectangle( $image, 0, 0, 1, 1, $transparent );
		imagesetpixel( $image, 0, 0, $red );

		$lines  = [];
		$method = new ReflectionMethod( OC_Print_Embroidery::class, 'append_eps_raster_image' );
		$method->invokeArgs( null, [ &$lines, $image, 0.0, 0.0, 10.0, 10.0 ] );
		imagedestroy( $image );

		$output = implode( "\n", $lines );
		$this->assertStringContainsString( '%%OCTransparentRaster: vector-runs', $output );
		$this->assertStringContainsString( '%%OCTransparentRasterSize: 2 2', $output );
		$this->assertStringContainsString( '1.0000 0.0000 0.0000 setrgbcolor', $output );
		$this->assertStringContainsString( 'rectfill', $output );
		$this->assertStringNotContainsString( 'colorimage', $output );
		$this->assertStringNotContainsString( '1.0000 1.0000 1.0000 setrgbcolor', $output );
	}

	#[Test]
	public function transparent_raster_export_keeps_print_resolution_for_smooth_edges(): void {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			$this->markTestSkipped( 'GD is not available.' );
		}

		$image = imagecreatetruecolor( 600, 2 );
		imagealphablending( $image, false );
		imagesavealpha( $image, true );
		$transparent = imagecolorallocatealpha( $image, 0, 0, 0, 127 );
		$red         = imagecolorallocatealpha( $image, 255, 0, 0, 0 );
		imagefilledrectangle( $image, 0, 0, 599, 1, $transparent );
		imagesetpixel( $image, 599, 1, $red );

		$lines  = [];
		$method = new ReflectionMethod( OC_Print_Embroidery::class, 'append_eps_raster_image' );
		$method->invokeArgs( null, [ &$lines, $image, 0.0, 0.0, 600.0, 2.0 ] );
		imagedestroy( $image );

		$output = implode( "\n", $lines );
		$this->assertStringContainsString( '%%OCTransparentRasterSize: 600 2', $output );
		$this->assertStringContainsString( '599 0 1 1 rectfill', $output );
	}

	#[Test]
	public function transparent_raster_export_uses_half_opacity_as_the_visible_edge(): void {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			$this->markTestSkipped( 'GD is not available.' );
		}

		$image = imagecreatetruecolor( 3, 1 );
		imagealphablending( $image, false );
		imagesavealpha( $image, true );
		$opaque     = imagecolorallocatealpha( $image, 255, 0, 0, 0 );
		$mostly_in  = imagecolorallocatealpha( $image, 255, 0, 0, 63 );
		$mostly_out = imagecolorallocatealpha( $image, 255, 0, 0, 64 );
		imagesetpixel( $image, 0, 0, $opaque );
		imagesetpixel( $image, 1, 0, $mostly_in );
		imagesetpixel( $image, 2, 0, $mostly_out );

		$lines  = [];
		$method = new ReflectionMethod( OC_Print_Embroidery::class, 'append_eps_raster_image' );
		$method->invokeArgs( null, [ &$lines, $image, 0.0, 0.0, 3.0, 1.0 ] );
		imagedestroy( $image );

		$output = implode( "\n", $lines );
		$this->assertStringContainsString( '0 0 2 1 rectfill', $output );
		$this->assertStringNotContainsString( '2 0 1 1 rectfill', $output );
	}

	#[Test]
	public function svg_percentage_background_is_removed_before_eps_conversion(): void {
		$source_base = tempnam( sys_get_temp_dir(), 'oc-svg-source-' );
		$source      = $source_base . '.svg';
		file_put_contents( $source, '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 50"><rect width="100%" height="100%" fill="#fff"/><circle cx="50" cy="25" r="10" fill="#ff0000"/></svg>' );

		$method = new ReflectionMethod( OC_Print_Embroidery::class, 'build_svg_without_white_background' );
		$clean  = $method->invoke( null, $source );

		$this->assertIsString( $clean );
		$output = file_get_contents( $clean );
		$this->assertStringNotContainsString( '<rect', $output );
		$this->assertStringContainsString( '<circle', $output );

		@unlink( $clean );
		@unlink( $source_base );
		@unlink( $source );
	}

	#[Test]
	public function svg_white_shapes_smaller_than_the_canvas_are_preserved(): void {
		$source_base = tempnam( sys_get_temp_dir(), 'oc-svg-source-' );
		$source      = $source_base . '.svg';
		file_put_contents( $source, '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 50"><rect x="20" y="10" width="60" height="30" fill="#fff"/></svg>' );

		$method = new ReflectionMethod( OC_Print_Embroidery::class, 'build_svg_without_white_background' );

		$this->assertNull( $method->invoke( null, $source ) );

		@unlink( $source_base );
		@unlink( $source );
	}

	#[Test]
	public function svg_ellipse_fallback_exports_valid_bezier_paths(): void {
		$source_base = tempnam( sys_get_temp_dir(), 'oc-svg-source-' );
		$source      = $source_base . '.svg';
		file_put_contents( $source, '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><ellipse cx="10" cy="10" rx="6" ry="4" fill="#000000"/></svg>' );

		$lines  = [];
		$method = new ReflectionMethod( OC_Print_Embroidery::class, 'append_eps_svg_vector' );
		$this->assertTrue( $method->invokeArgs( null, [ &$lines, $source, 0.0, 0.0, 20.0, 20.0 ] ) );

		$output = implode( "\n", $lines );
		$this->assertStringContainsString( 'curveto', $output );
		$this->assertStringNotContainsString( '0 0 1 0 360 arc', $output );

		@unlink( $source_base );
		@unlink( $source );
	}

	#[Test]
	public function layer_export_does_not_apply_print_area_rotation_to_production_eps(): void {
		$lines = [];
		$area  = (object) [
			'canvas_unit' => 'px',
			'canvas_x'    => 0,
			'canvas_y'    => 0,
			'canvas_w'    => 100,
			'canvas_h'    => 100,
		];
		$data  = [
			'text'   => '',
			'bounds' => [ 'x' => 0, 'y' => 0, 'w' => 100, 'h' => 100, 'rotation' => 90 ],
			'layers' => [
				[
					'type'     => 'text',
					'x'        => 0,
					'y'        => 0,
					'w'        => 20,
					'h'        => 10,
					'input'    => [ 'value' => 'Rotated', 'colorHex' => '#000000' ],
					'settings' => [],
				],
			],
		];

		$method = new ReflectionMethod( OC_Print_Embroidery::class, 'append_eps_layers' );
		$method->invokeArgs( null, [ &$lines, $area, $data ] );

		$output = implode( "\n", $lines );
		$this->assertStringContainsString( '2.4001 22.8009 translate', $output );
		$this->assertStringNotContainsString( '90.0000 rotate', $output );
	}

	#[Test]
	public function layer_export_keeps_only_explicit_layer_rotation(): void {
		$lines = [];
		$area  = (object) [
			'canvas_unit' => 'px',
			'canvas_x'    => 0,
			'canvas_y'    => 0,
			'canvas_w'    => 100,
			'canvas_h'    => 100,
		];
		$data  = [
			'text'   => '',
			'bounds' => [ 'x' => 0, 'y' => 0, 'w' => 100, 'h' => 100, 'rotation' => 90 ],
			'layers' => [
				[
					'type'     => 'text',
					'x'        => 0,
					'y'        => 0,
					'w'        => 20,
					'h'        => 10,
					'rotation' => 15,
					'input'    => [ 'value' => 'Rotated', 'colorHex' => '#000000' ],
					'settings' => [],
				],
			],
		];

		$method = new ReflectionMethod( OC_Print_Embroidery::class, 'append_eps_layers' );
		$method->invokeArgs( null, [ &$lines, $area, $data ] );

		$output = implode( "\n", $lines );
		$this->assertStringContainsString( '2.4001 22.8009 translate', $output );
		$this->assertStringContainsString( '-15.0000 rotate', $output );
		$this->assertStringNotContainsString( '105.0000 rotate', $output );
	}

}
