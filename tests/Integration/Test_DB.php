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
