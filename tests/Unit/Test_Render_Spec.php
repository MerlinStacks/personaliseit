<?php
/**
 * Unit tests for the canonical v2 render spec shape.
 *
 * @package OverCustomise
 */

use PHPUnit\Framework\Attributes\Test;

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
					'input'               => [ 'attachmentId' => 88 ],
					'artworkAttachmentId' => 88,
				],
			],
		];

		$data = OC_Render_Spec::area_to_print_data( $area );

		$this->assertSame( 'Alex', $data['text'] );
		$this->assertSame( 7, $data['fontId'] );
		$this->assertSame( '#123456', $data['color'] );
		$this->assertSame( 12, $data['minFontSize'] );
		$this->assertSame( 26, $data['maxFontSize'] );
		$this->assertSame( 88, $data['artworkAttachmentId'] );
		$this->assertSame( 80, $data['layers'][0]['x'] );
		$this->assertSame( 160, $data['layers'][0]['w'] );
		$this->assertSame( [ 'x' => 50, 'y' => 60, 'w' => 300, 'h' => 120, 'rotation' => 0 ], $data['bounds'] );
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
}
