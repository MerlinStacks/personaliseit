<?php
/**
 * Unit tests for OC_Print_Generator regeneration warnings.
 *
 * @package OverCustomise
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once OC_PATH . 'includes/class-oc-print-generator.php';

class Test_Print_Generator_Warnings extends TestCase {

	#[Test]
	public function uv_regeneration_warns_without_usable_vector_snapshot(): void {
		$warning = $this->regeneration_warning(
			(object) [ 'print_method' => 'uv' ],
			[
				'layers' => [ [ 'type' => 'text' ] ],
			]
		);

		$this->assertNotSame( '', $warning );
		$this->assertStringContainsString( 'does not contain a usable browser-rendered vector snapshot', $warning );
	}

	#[Test]
	public function uv_regeneration_does_not_warn_with_usable_vector_snapshot(): void {
		$warning = $this->regeneration_warning(
			(object) [ 'print_method' => 'uv' ],
			[
				'snapshot' => [
					'svg' => '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0h10v10H0z"/></svg>',
				],
			]
		);

		$this->assertSame( '', $warning );
	}

	#[Test]
	public function uv_regeneration_warns_when_snapshot_contains_unresolved_image(): void {
		$warning = $this->regeneration_warning(
			(object) [ 'print_method' => 'uv' ],
			[
				'snapshot' => [
					'svg' => '<svg xmlns="http://www.w3.org/2000/svg"><image href="photo.png"/></svg>',
				],
			]
		);

		$this->assertNotSame( '', $warning );
	}

	#[Test]
	public function engraving_regeneration_does_not_warn_without_snapshot(): void {
		$warning = $this->regeneration_warning(
			(object) [ 'print_method' => 'engraving' ],
			[ 'layers' => [ [ 'type' => 'text' ] ] ]
		);

		$this->assertSame( '', $warning );
	}

	private function regeneration_warning( object $area, array $area_data ): string {
		$method = new ReflectionMethod( OC_Print_Generator::class, 'regeneration_snapshot_warning' );
		$method->setAccessible( true );

		return (string) $method->invoke( null, $area, $area_data );
	}
}
