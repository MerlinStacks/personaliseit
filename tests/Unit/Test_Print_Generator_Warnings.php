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
	public function uv_regeneration_uses_canonical_layers_without_snapshot_warning(): void {
		$warning = $this->regeneration_warning(
			(object) [ 'print_method' => 'uv' ],
			[
				'layers' => [ [ 'type' => 'text' ] ],
			]
		);

		$this->assertSame( '', $warning );
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

	#[Test]
	public function regeneration_restores_in_place_output_when_commit_fails(): void {
		$path  = tempnam( sys_get_temp_dir(), 'oc-regeneration-' );
		$thumb = $path . '-thumb.png';
		file_put_contents( $path, 'original output' );
		file_put_contents( $thumb, 'original thumbnail' );
		$method = new ReflectionMethod( OC_Print_Generator::class, 'generate_with_backup' );
		$method->setAccessible( true );

		try {
			$method->invoke(
				null,
				$path,
				static function () use ( $path ): array {
					file_put_contents( $path, 'replacement output' );
					return [ 'file_path' => $path, 'status' => 'files_ready' ];
				},
				static function ( array &$result ) use ( $thumb ): void {
					file_put_contents( $thumb, 'replacement thumbnail' );
					$result['thumbnail_path'] = $thumb;
					throw new RuntimeException( 'Commit failed.' );
				},
				[ $thumb ]
			);
			$this->fail( 'A failed commit should throw.' );
		} catch ( RuntimeException $e ) {
			$this->assertSame( 'Commit failed.', $e->getMessage() );
			$this->assertSame( 'original output', file_get_contents( $path ) );
			$this->assertSame( 'original thumbnail', file_get_contents( $thumb ) );
		} finally {
			@unlink( $path );
			@unlink( $thumb );
		}
	}

	#[Test]
	public function combined_file_lookup_ignores_print_file_text_outside_structured_entries(): void {
		$method = new ReflectionMethod( OC_Print_Generator::class, 'combined_payload_contains_print_file_id' );
		$method->setAccessible( true );
		$customer_text = wp_json_encode( [
			'layers' => [ [ 'input' => [ 'value' => 'Customer wrote "printFileId":42 here.' ] ] ],
		] );
		$combined = wp_json_encode( [
			'__combined_print_areas' => [ [ 'printFileId' => 42, 'areaData' => [] ] ],
		] );

		$this->assertFalse( $method->invoke( null, $customer_text, 42 ) );
		$this->assertTrue( $method->invoke( null, $combined, 42 ) );
	}

	private function regeneration_warning( object $area, array $area_data ): string {
		$method = new ReflectionMethod( OC_Print_Generator::class, 'regeneration_snapshot_warning' );
		$method->setAccessible( true );

		return (string) $method->invoke( null, $area, $area_data );
	}
}
