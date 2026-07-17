<?php
/**
 * Unit tests for immutable print queue identities.
 *
 * @package OverCustomise
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once OC_PATH . 'includes/class-oc-print-queue.php';

class Test_Print_Queue extends TestCase {

	#[Test]
	public function exact_print_file_identity_is_accepted(): void {
		$method = new ReflectionMethod( OC_Print_Queue::class, 'assert_print_file_identity' );
		$method->setAccessible( true );
		$queue = OC_Print_Queue::instance();
		$job = (object) [
			'order_id'      => 10,
			'order_item_id' => 20,
			'print_method'  => 'uv',
		];
		$file = (object) [
			'id'            => 30,
			'order_id'      => 10,
			'order_item_id' => 20,
			'print_area_id' => 40,
			'area_source'   => 'design',
			'row_index'     => 0,
			'file_type'     => 'uv',
		];

		$method->invoke( $queue, $file, $job, 40, 'design', 0 );
		$this->addToAssertionCount( 1 );
	}

	#[Test]
	public function cross_order_print_file_identity_is_rejected(): void {
		$method = new ReflectionMethod( OC_Print_Queue::class, 'assert_print_file_identity' );
		$method->setAccessible( true );
		$job = (object) [ 'order_id' => 10, 'order_item_id' => 20, 'print_method' => 'uv' ];
		$file = (object) [
			'id'            => 30,
			'order_id'      => 11,
			'order_item_id' => 20,
			'print_area_id' => 40,
			'area_source'   => 'design',
			'row_index'     => 0,
			'file_type'     => 'uv',
		];

		$this->expectException( RuntimeException::class );
		$method->invoke( OC_Print_Queue::instance(), $file, $job, 40, 'design', 0 );
	}

	#[Test]
	public function duplicate_combined_file_identity_is_rejected(): void {
		$method = new ReflectionMethod( OC_Print_Queue::class, 'validate_combined_entries' );
		$method->setAccessible( true );
		$entries = [
			[
				'printFileId' => 30,
				'areaId'      => 40,
				'areaSource'  => 'design',
				'rowIndex'    => 0,
				'areaData'    => [],
			],
			[
				'printFileId' => 30,
				'areaId'      => 41,
				'areaSource'  => 'design',
				'rowIndex'    => 0,
				'areaData'    => [],
			],
		];

		$this->expectException( RuntimeException::class );
		$method->invoke( OC_Print_Queue::instance(), $entries, (object) [ 'print_file_id' => 30 ] );
	}
}
