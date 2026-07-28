<?php
/**
 * Integration tests for OC_DB — requires a full WordPress test environment.
 *
 * Run with:
 *   WP_TESTS_DIR=/path/to/wp-tests vendor/bin/phpunit --testsuite Integration
 *
 * @package OverCustomise
 */

use PHPUnit\Framework\Attributes\Test;

class Test_DB extends WP_UnitTestCase {

	/** @var int Product post ID created for testing. */
	private static int $product_id;

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		if ( ! method_exists( static::class, 'factory' ) ) {
			static::markTestSkipped( 'Integration tests require WP_TESTS_DIR to be set.' );
		}
		self::$product_id = self::factory()->post->create( [ 'post_type' => 'product' ] );
	}

	// ── oc_product_configs ────────────────────────────────────────────────────

	#[Test]
	public function get_config_by_product_returns_null_when_missing(): void {
		$this->assertNull( OC_DB::get_config_by_product( 999999 ) );
	}

	#[Test]
	public function can_insert_and_retrieve_product_config(): void {
		global $wpdb;

		$wpdb->insert( $wpdb->prefix . 'oc_product_configs', [
			'product_id'  => self::$product_id,
			'custom_type' => 'text_only',
			'flat_rate'   => 5.00,
			'active'      => 1,
		] );

		$config = OC_DB::get_config_by_product( self::$product_id );

		$this->assertIsObject( $config );
		$this->assertEquals( self::$product_id, (int) $config->product_id );
		$this->assertSame( 'text_only', $config->custom_type );
		$this->assertEquals( 5.00, (float) $config->flat_rate );
	}

	// ── oc_print_areas ────────────────────────────────────────────────────────

	#[Test]
	public function get_print_areas_returns_empty_for_unknown_config(): void {
		$areas = OC_DB::get_print_areas( 999999 );
		$this->assertIsArray( $areas );
		$this->assertEmpty( $areas );
	}

	#[Test]
	public function get_print_areas_returns_ordered_rows(): void {
		global $wpdb;

		$config = OC_DB::get_config_by_product( self::$product_id );
		if ( ! $config ) {
			$this->markTestSkipped( 'Product config not created yet — run in correct test order.' );
		}

		$config_id = (int) $config->id;

		// Insert two areas in reverse sort order.
		$wpdb->insert( $wpdb->prefix . 'oc_print_areas', [
			'config_id'    => $config_id,
			'area_key'     => 'back',
			'label'        => 'Back',
			'print_method' => 'uv',
			'sort_order'   => 1,
		] );
		$wpdb->insert( $wpdb->prefix . 'oc_print_areas', [
			'config_id'    => $config_id,
			'area_key'     => 'front',
			'label'        => 'Front',
			'print_method' => 'engraving',
			'sort_order'   => 0,
		] );

		$areas = OC_DB::get_print_areas( $config_id );

		$this->assertCount( 2, $areas );
		$this->assertSame( 'front', $areas[0]->area_key );
		$this->assertSame( 'back',  $areas[1]->area_key );
	}

	// ── oc_print_files ────────────────────────────────────────────────────────

	#[Test]
	public function get_print_file_returns_null_for_unknown_id(): void {
		$this->assertNull( OC_DB::get_print_file( 999999 ) );
	}

	#[Test]
	public function can_insert_and_update_print_file(): void {
		$now = gmdate( 'Y-m-d H:i:s' );

		$id = OC_DB::insert_print_file( [
			'order_id'      => 1,
			'order_item_id' => 1,
			'print_area_id' => 1,
			'file_type'     => 'uv',
			'file_status'   => 'generating',
			'generated_at'  => $now,
			'expires_at'    => gmdate( 'Y-m-d H:i:s', strtotime( '+90 days' ) ),
		] );

		$this->assertGreaterThan( 0, $id );

		$record = OC_DB::get_print_file( $id );
		$this->assertIsObject( $record );
		$this->assertSame( 'generating', $record->file_status );

		// Update to files_ready.
		OC_DB::update_print_file( $id, [
			'file_path'   => '/tmp/test-file.pdf',
			'file_status' => 'files_ready',
		] );

		$updated = OC_DB::get_print_file( $id );
		$this->assertSame( 'files_ready', $updated->file_status );
		$this->assertSame( '/tmp/test-file.pdf', $updated->file_path );
	}

	#[Test]
	public function get_print_files_for_item_returns_matching_rows(): void {
		global $wpdb;

		$item_id = 9001;
		$now     = gmdate( 'Y-m-d H:i:s' );

		OC_DB::insert_print_file( [
			'order_id'      => 1,
			'order_item_id' => $item_id,
			'print_area_id' => 1,
			'file_type'     => 'sublimation',
			'file_status'   => 'pending',
			'generated_at'  => $now,
			'expires_at'    => gmdate( 'Y-m-d H:i:s', strtotime( '+90 days' ) ),
		] );

		$files = OC_DB::get_print_files_for_item( $item_id );

		$this->assertNotEmpty( $files );
		foreach ( $files as $f ) {
			$this->assertEquals( $item_id, (int) $f->order_item_id );
		}
	}

	#[Test]
	public function print_file_identity_insert_is_atomic_and_distinguishes_source_and_vdp_row(): void {
		$base = [
			'order_id'      => 7001,
			'order_item_id' => 7002,
			'print_area_id' => 42,
			'area_source'   => 'design',
			'row_index'     => 1,
			'row_key'       => 'row-one',
			'file_type'     => 'uv',
			'file_status'   => 'pending',
		];

		$first = OC_DB::insert_print_file( $base );
		$this->assertSame( $first, OC_DB::insert_print_file( $base ) );
		$this->assertNotSame( $first, OC_DB::insert_print_file( array_merge( $base, [ 'row_index' => 2 ] ) ) );
		$this->assertNotSame( $first, OC_DB::insert_print_file( array_merge( $base, [ 'area_source' => 'legacy' ] ) ) );
	}

	#[Test]
	public function queue_job_can_only_be_claimed_once(): void {
		global $wpdb;
		$wpdb->insert( $wpdb->prefix . 'oc_print_queue', [
			'order_id'      => 8001,
			'order_item_id' => 8002,
			'print_area_id' => 1,
			'area_source'   => 'legacy',
			'row_index'     => 0,
			'area_data'     => '{}',
			'print_method'  => 'uv',
			'status'        => 'pending',
		] );
		$id = (int) $wpdb->insert_id;

		$this->assertNotNull( OC_DB::claim_queue_job( $id, 3 ) );
		$this->assertNull( OC_DB::claim_queue_job( $id, 3 ) );
		$this->assertSame( 1, (int) OC_DB::get_queue_job( $id )->attempts );
	}

	#[Test]
	public function stale_queue_jobs_are_retried_or_failed_based_on_attempt_count(): void {
		global $wpdb;
		$ids = [];
		foreach ( [ 2, 3 ] as $attempts ) {
			$wpdb->insert( $wpdb->prefix . 'oc_print_queue', [
				'order_id'      => 8101,
				'order_item_id' => 8102 + $attempts,
				'print_area_id' => 1,
				'area_source'   => 'legacy',
				'row_index'     => 0,
				'area_data'     => '{}',
				'print_method'  => 'uv',
				'status'        => 'processing',
				'attempts'      => $attempts,
				'processed_at'  => gmdate( 'Y-m-d H:i:s', strtotime( '-1 hour' ) ),
			] );
			$ids[ $attempts ] = (int) $wpdb->insert_id;
		}

		$this->assertSame( 2, OC_Print_Queue::instance()->reset_stale_processing_jobs() );
		$this->assertSame( 'pending', OC_DB::get_queue_job( $ids[2] )->status );
		$this->assertSame( 'failed', OC_DB::get_queue_job( $ids[3] )->status );
		$this->assertNotEmpty( OC_DB::get_queue_job( $ids[3] )->error_message );
	}

	#[Test]
	public function deleted_design_is_removed_from_all_assignment_variants(): void {
		global $wpdb;
		$variants = [
			[ 'designId' => 501, 'label' => 'Keep' ],
			[ 'designId' => 502, 'label' => 'Delete' ],
			[ 'designId' => 502, 'label' => 'Delete duplicate' ],
		];
		$wpdb->insert( $wpdb->prefix . 'oc_product_assignments', [
			'product_id'     => self::$product_id + 1000,
			'variant_id'     => 0,
			'design_id'      => 501,
			'design_variants' => wp_json_encode( $variants ),
		] );
		$assignment_id = (int) $wpdb->insert_id;
		$malformed = '[{"designId":502';
		$wpdb->insert( $wpdb->prefix . 'oc_product_assignments', [
			'product_id'      => self::$product_id + 1001,
			'variant_id'      => 0,
			'design_id'       => 501,
			'design_variants' => $malformed,
		] );
		$malformed_id = (int) $wpdb->insert_id;

		$this->assertTrue( OC_DB::remove_design_from_assignment_variants( 502 ) );
		$stored = json_decode( (string) $wpdb->get_var( $wpdb->prepare(
			"SELECT design_variants FROM {$wpdb->prefix}oc_product_assignments WHERE id = %d",
			$assignment_id
		) ), true );

		$this->assertSame( [ [ 'designId' => 501, 'label' => 'Keep' ] ], $stored );
		$this->assertSame( $malformed, $wpdb->get_var( $wpdb->prepare(
			"SELECT design_variants FROM {$wpdb->prefix}oc_product_assignments WHERE id = %d",
			$malformed_id
		) ) );
	}

	#[Test]
	public function assignment_variant_cleanup_reports_select_failure(): void {
		global $wpdb;
		$filter = static function ( string $query ): string {
			if ( str_contains( $query, 'SELECT id, design_variants FROM' ) ) {
				return 'SELECT oc_missing_column FROM oc_missing_assignment_table';
			}
			return $query;
		};
		add_filter( 'query', $filter );
		$previous_suppression = $wpdb->suppress_errors( true );
		try {
			$this->assertFalse( OC_DB::remove_design_from_assignment_variants( 502 ) );
		} finally {
			$wpdb->suppress_errors( $previous_suppression );
			remove_filter( 'query', $filter );
		}
	}

	#[Test]
	public function print_pipeline_writes_are_deferred_while_migration_is_locked(): void {
		add_option( 'oc_db_upgrade_lock', time(), '', false );
		try {
			$id = OC_DB::insert_print_file( [
				'order_id'      => 8201,
				'order_item_id' => 8202,
				'print_area_id' => 1,
				'area_source'   => 'design',
				'row_index'     => 0,
				'file_status'   => 'pending',
			] );
			$this->assertSame( 0, $id );
		} finally {
			delete_option( 'oc_db_upgrade_lock' );
		}
	}

	#[Test]
	public function unknown_area_source_preserves_design_then_legacy_lookup(): void {
		global $wpdb;
		$area_id = 987654;
		$wpdb->insert( $wpdb->prefix . 'oc_design_print_areas', [
			'id'           => $area_id,
			'design_id'    => 1,
			'area_key'     => 'design-first',
			'label'        => 'Design',
			'print_method' => 'uv',
		] );
		$wpdb->insert( $wpdb->prefix . 'oc_print_areas', [
			'id'           => $area_id,
			'config_id'    => 1,
			'area_key'     => 'legacy-second',
			'label'        => 'Legacy',
			'print_method' => 'uv',
		] );

		$method = new ReflectionMethod( OC_Print_Queue::class, 'get_print_area_for_job' );
		$area   = $method->invoke( OC_Print_Queue::instance(), $area_id, 'unknown' );
		$this->assertSame( 'design-first', $area->area_key );
	}

	// ── oc_fonts ──────────────────────────────────────────────────────────────

	#[Test]
	public function get_fonts_returns_only_active_by_default(): void {
		global $wpdb;

		// Insert one active, one inactive.
		$wpdb->insert( $wpdb->prefix . 'oc_fonts', [
			'name'      => 'Test Active Font',
			'file_path' => 'overcustomise/fonts/active.ttf',
			'active'    => 1,
		] );
		$wpdb->insert( $wpdb->prefix . 'oc_fonts', [
			'name'      => 'Test Inactive Font',
			'file_path' => 'overcustomise/fonts/inactive.ttf',
			'active'    => 0,
		] );

		$active_fonts = OC_DB::get_fonts( true );
		$names        = array_column( $active_fonts, 'name' );

		$this->assertContains( 'Test Active Font', $names );
		$this->assertNotContains( 'Test Inactive Font', $names );
	}
}
