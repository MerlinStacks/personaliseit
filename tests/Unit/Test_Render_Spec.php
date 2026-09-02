<?php
/**
 * Unit tests for the canonical v2 render spec shape.
 *
 * @package OverCustomise
 */

use PHPUnit\Framework\Attributes\Test;

if ( ! class_exists( 'OC_Print_Generator' ) ) {
	require_once OC_PATH . 'includes/class-oc-print-generator.php';
}

class Test_Render_Spec extends \PHPUnit\Framework\TestCase {

	#[Test]
	public function area_to_print_data_preserves_layer_geometry_and_summary_fields(): void {
		$area = [
			'id'          => 10,
			'areaKey'     => 'front',
			'label'       => 'Front',
			'printMethod' => 'uv',
			'bounds'      => [
				'x'        => 50,
				'y'        => 60,
				'w'        => 300,
				'h'        => 120,
				'rotation' => 0,
			],
			'layers'      => [
				[
					'id'       => 22,
					'type'     => 'text',
					'x'        => 80,
					'y'        => 90,
					'w'        => 160,
					'h'        => 40,
					'settings' => [ 'min_font_size' => 12, 'max_font_size' => 26 ],
					'input'    => [ 'value' => 'Alex', 'fontId' => 7, 'colorHex' => '#123456' ],
				],
				[
					'id'                  => 23,
					'type'                => 'image',
					'x'                   => 250,
					'y'                   => 95,
					'w'                   => 60,
					'h'                   => 60,
					'settings'            => [],
					'input'               => [ 'attachmentId' => 88, 'imageCrop' => 65 ],
					'artworkAttachmentId' => 88,
				],
			],
			'snapshot'    => [
				'format' => 'fabric-svg-v1',
				'unit'   => 'mockup_px',
				'scale'  => 1.5,
				'svg'    => '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0L1 1"/></svg>',
			],
		];

		$data = OC_Render_Spec::area_to_print_data( $area );

		$this->assertSame( 'Alex', $data['text'] );
		$this->assertSame( 7, $data['fontId'] );
		$this->assertSame( '#123456', $data['color'] );
		$this->assertSame( 12, $data['minFontSize'] );
		$this->assertSame( 26, $data['maxFontSize'] );
		$this->assertSame( 88, $data['artworkAttachmentId'] );
		$this->assertSame( 65, $data['layers'][1]['input']['imageCrop'] );
		$this->assertSame( 80, $data['layers'][0]['x'] );
		$this->assertSame( 160, $data['layers'][0]['w'] );
		$this->assertSame( [ 'x' => 50, 'y' => 60, 'w' => 300, 'h' => 120, 'rotation' => 0 ], $data['bounds'] );
		$this->assertSame( 'fabric-svg-v1', $data['snapshot']['format'] );
		$this->assertSame( $area, $data['renderSpecArea'] );
	}

	#[Test]
	public function area_to_print_data_preserves_clipmask_attachment_and_mask_settings(): void {
		$area = [
			'layers' => [
				[
					'id'                  => 44,
					'type'                => 'clipmask',
					'x'                   => 10,
					'y'                   => 20,
					'w'                   => 100,
					'h'                   => 100,
					'settings'            => [ 'mask_shape' => 'circle' ],
					'input'               => [ 'attachmentId' => 99 ],
					'artworkAttachmentId' => 99,
				],
			],
		];

		$data = OC_Render_Spec::area_to_print_data( $area );

		$this->assertSame( 99, $data['artworkAttachmentId'] );
		$this->assertSame( 'clipmask', $data['layers'][0]['type'] );
		$this->assertSame( 'circle', $data['layers'][0]['settings']['mask_shape'] );
	}

	#[Test]
	public function render_spec_omits_private_ai_instruction_but_keeps_generated_artwork(): void {
		$area   = (object) [
			'id'              => 10,
			'area_key'        => 'front',
			'label'           => 'Front',
			'print_method'    => 'uv',
			'canvas_unit'     => 'px',
			'canvas_x'        => 0,
			'canvas_y'        => 0,
			'canvas_w'        => 300,
			'canvas_h'        => 300,
			'canvas_dpi'      => 300,
			'canvas_rotation' => 0,
		];
		$layer  = (object) [
			'id'       => 22,
			'type'     => 'ai_image',
			'label'    => 'Generated artwork',
			'x'        => 0,
			'y'        => 0,
			'w'        => 300,
			'h'        => 300,
			'locked'   => 0,
			'settings' => wp_json_encode(
				[
					'required'              => true,
					'ai_prompt_instruction' => 'Private store rule',
				]
			),
		];
		$method = ( new ReflectionClass( OC_Render_Spec::class ) )->getMethod( 'build_area' );
		$result = $method->invoke(
			null,
			$area,
			[ $layer ],
			[
				22 => [
					'attachmentId' => 88,
					'aiPromptHash' => str_repeat( 'a', 64 ),
				],
			]
		);

		$this->assertArrayNotHasKey( 'ai_prompt_instruction', $result['layers'][0]['settings'] );
		$this->assertSame( 88, $result['layers'][0]['artworkAttachmentId'] );
		$this->assertSame( 'ai_image', $result['layers'][0]['type'] );
	}

	#[Test]
	public function area_to_print_data_preserves_but_excludes_mask_layers_from_print_summary(): void {
		$area = [
			'layers' => [
				[
					'id'                  => 45,
					'type'                => 'mask',
					'x'                   => 10,
					'y'                   => 20,
					'w'                   => 100,
					'h'                   => 100,
					'input'               => [ 'attachmentId' => 99, 'value' => 'Do not print' ],
					'artworkAttachmentId' => 99,
				],
			],
		];

		$data = OC_Render_Spec::area_to_print_data( $area );

		$this->assertSame( '', $data['text'] );
		$this->assertSame( 0, $data['artworkAttachmentId'] );
		$this->assertSame( '', $data['artworkPath'] );
		$this->assertSame( 'mask', $data['layers'][0]['type'] );
	}

	#[Test]
	public function print_generation_area_uses_order_time_render_spec_snapshot(): void {
		$current = (object) [
			'id'              => 10,
			'area_key'        => 'front-current',
			'label'           => 'Current Front',
			'print_method'    => 'uv',
			'canvas_unit'     => 'px',
			'canvas_x'        => 999,
			'canvas_y'        => 999,
			'canvas_w'        => 999,
			'canvas_h'        => 999,
			'canvas_dpi'      => 300,
			'canvas_rotation' => 45,
		];
		$area_data = [
			'renderSpecArea' => [
				'id'          => 10,
				'areaKey'     => 'front',
				'label'       => 'Front At Order',
				'printMethod' => 'embroidery',
				'unit'        => 'mm',
				'bounds'      => [ 'x' => 10, 'y' => 20, 'w' => 300, 'h' => 120, 'dpi' => 600, 'rotation' => 0 ],
			],
		];

		$area = OC_Print_Generator::area_object_for_generation( $current, $area_data );

		$this->assertSame( 'front', $area->area_key );
		$this->assertSame( 'Front At Order', $area->label );
		$this->assertSame( 'embroidery', $area->print_method );
		$this->assertSame( 'mm', $area->canvas_unit );
		$this->assertSame( 10.0, $area->canvas_x );
		$this->assertSame( 20.0, $area->canvas_y );
		$this->assertSame( 300.0, $area->canvas_w );
		$this->assertSame( 120.0, $area->canvas_h );
		$this->assertSame( 600, $area->canvas_dpi );
		$this->assertSame( 0.0, $area->canvas_rotation );
	}

	#[Test]
	public function print_generation_area_falls_back_to_current_area_without_snapshot(): void {
		$current = (object) [ 'id' => 10, 'canvas_w' => 999 ];

		$this->assertSame( $current, OC_Print_Generator::area_object_for_generation( $current, [] ) );
	}
}
