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
	public function text_export_keeps_customer_text_editable(): void {
		$lines  = [];
		$method = new ReflectionMethod( OC_Print_Embroidery::class, 'append_eps_text' );
		$method->invokeArgs( null, [ &$lines, [ 'value' => 'Editable Text', 'colorHex' => '#123456' ], [], 0.0, 0.0, 100.0, 20.0, true ] );

		$output = implode( "\n", $lines );
		$this->assertStringContainsString( '(Editable Text)', $output );
		$this->assertStringContainsString( '0.0000 14.0000 translate', $output );
		$this->assertStringContainsString( '96.0000 exch div 1 scale', $output );
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
	public function clipart_layers_are_lowered_to_match_embroidery_preview_overlap(): void {
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
		$this->assertStringContainsString( '-10.0000 7.8000 translate', $output );

		@unlink( $source_base );
		@unlink( $source );
	}

	#[Test]
	public function layer_export_uses_print_area_rotation_for_position_only(): void {
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
		$this->assertStringContainsString( '1.2000 2.4001 translate', $output );
		$this->assertStringNotContainsString( ' rotate', $output );
	}

	#[Test]
	public function layer_export_applies_explicit_layer_rotation_only(): void {
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
		$this->assertStringContainsString( '1.2000 2.4001 translate', $output );
		$this->assertStringContainsString( '15.0000 rotate', $output );
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
