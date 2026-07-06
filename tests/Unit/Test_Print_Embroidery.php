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
	public function text_export_keeps_customer_text_editable(): void {
		$lines  = [];
		$method = new ReflectionMethod( OC_Print_Embroidery::class, 'append_eps_text' );
		$method->invokeArgs( null, [ &$lines, [ 'value' => 'Editable Text', 'colorHex' => '#123456' ], [], 0.0, 0.0, 100.0, 20.0, true ] );

		$output = implode( "\n", $lines );
		$this->assertStringContainsString( '(Editable Text)', $output );
		$this->assertStringContainsString( '0.0000 14.0000 translate', $output );
		$this->assertStringNotContainsString( ' exch div 1 scale', $output );
		$this->assertStringContainsString( ' show', $output );
		$this->assertStringNotContainsString( 'imagemask', $output );
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
		$this->assertStringContainsString( '/Helvetica findfont 56.6929 scalefont setfont', $output );
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
		$append = new ReflectionMethod( OC_Print_Embroidery::class, 'append_eps_image_or_reference' );
		$append->invokeArgs( null, [ &$lines, $path, 0.0, 0.0, 20.0, 20.0 ] );

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
		$source_base = tempnam( sys_get_temp_dir(), 'oc-svg-source-' );
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
		$this->assertStringContainsString( '-10.0000 10.0000 translate', $output );
		$this->assertStringNotContainsString( '-10.0000 9.4000 translate', $output );

		@unlink( $source_base );
		@unlink( $source );
	}

	#[Test]
	public function embroidery_eps_filenames_are_versioned_for_regeneration(): void {
		$method = new ReflectionMethod( OC_Print_Embroidery::class, 'build_versioned_filename' );

		$filename = $method->invoke( null, 123, 'front area', 'eps' );

		$this->assertMatchesRegularExpression( '/^123-front-area-\d{14}-[a-f0-9-]{8}\.eps$/', $filename );
		$this->assertNotSame( '123-front-area.eps', $filename );
	}

	#[Test]
	public function embroidery_export_can_use_supported_svg_snapshot(): void {
		$lines = [];
		$area  = (object) [ 'area_key' => 'front' ];
		$data  = [
			'snapshot' => [
				'format' => 'fabric-svg-v1',
				'svg'    => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect x="1" y="2" width="3" height="4" fill="#123456"/></svg>',
			],
		];

		$method = new ReflectionMethod( OC_Print_Embroidery::class, 'append_eps_snapshot' );
		$used   = $method->invokeArgs( null, [ &$lines, $area, $data, 20.0, 20.0 ] );

		$output = implode( "\n", $lines );
		$this->assertTrue( $used );
		$this->assertStringContainsString( '%%OCSnapshotFormat: fabric-svg-v1', $output );
		$this->assertStringContainsString( 'setrgbcolor', $output );
		$this->assertStringContainsString( 'fill', $output );
	}

	#[Test]
	public function embroidery_generation_prefers_layer_payload_over_stored_snapshot(): void {
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

		$this->assertStringContainsString( '%%OCSnapshotFallback: layer-payload', $output );
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

		$this->assertStringContainsString( '%%OCSnapshotFallback: legacy-artwork', $output );
		$this->assertStringNotContainsString( '%%OCSnapshotFormat: fabric-svg-v1', $output );
		$this->assertStringContainsString( 'Legacy Text', $output );

		@unlink( $path );
		@rmdir( $output_dir );
	}

	#[Test]
	public function embroidery_export_falls_back_when_snapshot_has_unoutlined_text(): void {
		$lines = [ 'before' ];
		$area  = (object) [ 'area_key' => 'front' ];
		$data  = [
			'snapshot' => [
				'format' => 'fabric-svg-v1',
				'svg'    => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><text x="1" y="2">Name</text></svg>',
			],
		];

		$method = new ReflectionMethod( OC_Print_Embroidery::class, 'append_eps_snapshot' );
		$used   = $method->invokeArgs( null, [ &$lines, $area, $data, 20.0, 20.0 ] );

		$this->assertFalse( $used );
		$this->assertSame( [ 'before' ], $lines );
	}

	#[Test]
	public function embroidery_export_rejects_svg_snapshot_with_pattern_paint(): void {
		$method = new ReflectionMethod( OC_Print_Embroidery::class, 'snapshot_svg_supported' );
		$svg    = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><path d="M0 0H10V10H0Z" fill="url(#oc-embroidery-stitch)"/></svg>';

		$this->assertFalse( $method->invoke( null, $svg ) );
	}

	#[Test]
	public function embroidery_page_mask_hides_overflow_without_relying_on_clipping(): void {
		$lines  = [];
		$method = new ReflectionMethod( OC_Print_Embroidery::class, 'append_eps_page_overflow_mask' );
		$method->invokeArgs( null, [ &$lines, 100.0, 50.0 ] );

		$output = implode( "\n", $lines );
		$this->assertStringContainsString( '1 1 1 setrgbcolor', $output );
		$this->assertStringContainsString( '-400.0000 -400.0000 400.0000 850.0000 rectfill', $output );
		$this->assertStringNotContainsString( 'clip', $output );
	}

	#[Test]
	public function layer_export_applies_print_area_rotation_to_position_and_layer_angle(): void {
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
		$this->assertStringContainsString( '22.8009 21.6009 translate', $output );
		$this->assertStringContainsString( '90.0000 rotate', $output );
	}

	#[Test]
	public function layer_export_combines_print_area_and_explicit_layer_rotation(): void {
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
		$this->assertStringContainsString( '22.8009 21.6009 translate', $output );
		$this->assertStringContainsString( '105.0000 rotate', $output );
	}

}
