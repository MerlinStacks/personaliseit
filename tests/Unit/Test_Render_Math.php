<?php
/**
 * Unit tests for shared render geometry conversion helpers.
 *
 * @package OverCustomise
 */

use PHPUnit\Framework\Attributes\Test;

class Test_Render_Math extends \PHPUnit\Framework\TestCase {

	#[Test]
	public function dpi_is_clamped_to_the_shared_render_range(): void {
		$this->assertSame( 36, OC_Render_Math::normalise_dpi( 1 ) );
		$this->assertSame( 300, OC_Render_Math::normalise_dpi( 300 ) );
		$this->assertSame( 1200, OC_Render_Math::normalise_dpi( 2400 ) );
	}

	#[Test]
	public function unit_px_scale_converts_physical_units_using_area_dpi(): void {
		$this->assertSame( 1.0, OC_Render_Math::unit_px_scale( [ 'unit' => 'px', 'dpi' => 600 ] ) );
		$this->assertEqualsWithDelta( 600 / 25.4, OC_Render_Math::unit_px_scale( [ 'unit' => 'mm', 'dpi' => 600 ] ), 0.0001 );
		$this->assertEqualsWithDelta( 300 / 2.54, OC_Render_Math::unit_px_scale( [ 'unit' => 'cm', 'dpi' => 300 ] ), 0.0001 );
		$this->assertSame( 150.0, OC_Render_Math::unit_px_scale( [ 'unit' => 'in', 'dpi' => 150 ] ) );
	}

	#[Test]
	public function display_entity_scales_size_and_offsets_from_area_origin(): void {
		$area = [ 'x' => 100, 'y' => 50, 'w' => 80, 'h' => 40, 'unit' => 'mm', 'dpi' => 254 ];
		$layer = [ 'x' => 110, 'y' => 55, 'w' => 20, 'h' => 10 ];

		$display = OC_Render_Math::display_entity( $layer, $area );

		$this->assertSame( 200.0, $display['x'] );
		$this->assertSame( 100.0, $display['y'] );
		$this->assertSame( 200.0, $display['w'] );
		$this->assertSame( 100.0, $display['h'] );
	}

	#[Test]
	public function display_font_size_uses_same_unit_scale_as_layer_boxes(): void {
		$area = [ 'unit' => 'mm', 'dpi' => 254 ];

		$this->assertSame( 140.0, OC_Render_Math::display_font_size( 14, $area, 1 ) );
		$this->assertSame( 70.0, OC_Render_Math::display_font_size( 14, $area, 0.5 ) );
	}
}
