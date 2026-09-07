<?php
/**
 * Unit tests for immutable print queue identities.
 *
 * @package OverCustomise
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once OC_PATH . 'includes/class-oc-print-queue.php';
require_once OC_PATH . 'includes/class-oc-print-generator.php';
require_once OC_PATH . 'includes/class-oc-db.php';

class Test_Print_Queue extends TestCase {

	#[Test]
	public function failed_reconciliation_advances_the_bounded_cleanup_cursor(): void {
		$previous = $GLOBALS['wpdb'] ?? null;
		$GLOBALS['wpdb'] = new class {
			public string $prefix = 'wp_';
			public string $last_error = '';
			public array $selection_cursors = [];
			public function prepare( string $sql, mixed ...$args ): string {
				if ( str_contains( $sql, 'WHERE id > %d AND attempts' ) ) {
					$this->selection_cursors[] = $args[0];
				}
				return $sql;
			}
			public function get_results( string $sql ): array {
				$cursor = end( $this->selection_cursors );
				return array_map( static fn ( int $id ): object => (object) [
					'id' => $id, 'order_id' => 1, 'order_item_id' => 2, 'print_area_id' => 3,
					'area_data' => '{}', 'status' => 'pending', 'attempts' => 3, 'processed_at' => null,
				], 0 === $cursor ? range( 1, 100 ) : [ 101 ] );
			}
			public function get_row( string $sql ): ?object { return null; }
			public function get_var( string $sql ): string { return 'MyISAM'; }
			public function query( string $sql ): int { return 0; }
		};
		delete_option( 'oc_print_failure_cleanup_cursor' );
		try {
			$this->assertSame( 0, OC_Print_Queue::instance()->reset_stale_processing_jobs() );
			$this->assertSame( 100, get_option( 'oc_print_failure_cleanup_cursor' ) );
			$this->assertSame( 0, OC_Print_Queue::instance()->reset_stale_processing_jobs() );
			$this->assertSame( [ 0, 100 ], $GLOBALS['wpdb']->selection_cursors );
			$this->assertSame( 0, get_option( 'oc_print_failure_cleanup_cursor' ) );
		} finally {
			delete_option( 'oc_print_failure_cleanup_cursor' );
			$GLOBALS['wpdb'] = $previous;
		}
	}

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
