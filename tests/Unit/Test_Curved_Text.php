<?php
/** Curved text settings and production output regressions. @package OverCustomise */

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

require_once OC_PATH . 'includes/print/class-oc-print-uv.php';
require_once OC_PATH . 'includes/print/class-oc-print-embroidery.php';
require_once OC_PATH . 'includes/frontend/class-oc-cart.php';

class Test_Curved_Text extends TestCase {
	public function test_settings_preserve_literal_text_and_clamp_the_curve(): void {
		foreach ( [
			-999 => -180,
			0    => 0,
			999  => 180,
		] as $angle => $expected ) {
			$settings = OC_Cart::normalise_layer_settings(
				[
					'curve_angle'             => $angle,
					'default_text'            => '<3 & Zoë',
					'additional_cost_enabled' => true,
				],
				'curved_text'
			);
			$this->assertEquals( $expected, $settings['curve_angle'] );
			$this->assertSame( '<3 & Zoë', $settings['default_text'] );
			$this->assertTrue( $settings['additional_cost_enabled'] );
		}
	}

	public static function print_modes(): array {
		return [
			'colour'    => [ 'colour' ],
			'engraving' => [ 'engraving' ],
		];
	}

	#[DataProvider( 'print_modes' )]
	public function test_curved_pdf_contains_outlined_glyphs_for_each_direction( string $mode ): void {
		$outputs = [];
		foreach ( [ -120, 0, 120 ] as $angle ) {
			$pdf = ( new ReflectionMethod( OC_Print_Base::class, 'make_pdf' ) )->invoke( null, 100.0, 60.0 );
			$pdf->AddPage();
			$area = (object) [
				'canvas_w'    => 100,
				'canvas_h'    => 60,
				'canvas_unit' => 'mm',
				'canvas_dpi'  => 300,
			];
			$data = [
				'bounds' => [
					'x' => 0,
					'y' => 0,
					'w' => 100,
					'h' => 60,
				],
				'layers' => [
					[
						'type'     => 'curved_text',
						'x'        => 0,
						'y'        => 0,
						'w'        => 100,
						'h'        => 60,
						'input'    => [
							'value'    => 'Curved Text',
							'fontSize' => 20,
						],
						'settings' => [ 'curve_angle' => $angle ],
					],
				],
			];
			( new ReflectionMethod( OC_Print_Base::class, 'render_layer_payload' ) )->invoke( null, $pdf, $area, $data, 0.0, 0.0, $mode );
			$bytes = $pdf->Output( '', 'S' );
			preg_match( '/\/Filter \/FlateDecode \/Length (\d+) >>\nstream\n/', $bytes, $stream, PREG_OFFSET_CAPTURE );
			$this->assertNotEmpty( $stream );
			$content = gzuncompress( substr( $bytes, $stream[0][1] + strlen( $stream[0][0] ), (int) $stream[1][0] ) );
			$this->assertDoesNotMatchRegularExpression( '/\b(?:Tj|TJ)\b/', $content );
			$this->assertMatchesRegularExpression( '/[-\d.]+ [-\d.]+ m\b/', $content );
			$outputs[] = $content;
		}
		$this->assertCount( 3, array_unique( $outputs ), 'Curve direction must affect actual production paths.' );
	}

	public function test_embroidery_bends_each_character_and_keeps_spaces(): void {
		$lines = [];
		$area  = (object) [
			'canvas_unit' => 'px',
			'canvas_w'    => 400,
			'canvas_h'    => 180,
		];
		$data  = [
			'layers' => [
				[
					'type'     => 'curved_text',
					'x'        => 0,
					'y'        => 0,
					'w'        => 400,
					'h'        => 180,
					'input'    => [
						'value'    => 'A B',
						'fontSize' => 30,
					],
					'settings' => [ 'curve_angle' => 120 ],
				],
			],
		];
		( new ReflectionMethod( OC_Print_Embroidery::class, 'append_eps_layers' ) )->invokeArgs( null, [ &$lines, $area, $data ] );
		$output = implode( "\n", $lines );
		$this->assertStringContainsString( '/ocText (A) def', $output );
		$this->assertStringContainsString( '/ocText (B) def', $output );
		$this->assertMatchesRegularExpression( '/translate 40\.000000 rotate/', $output );
		$this->assertMatchesRegularExpression( '/translate -40\.000000 rotate/', $output );
	}
}
