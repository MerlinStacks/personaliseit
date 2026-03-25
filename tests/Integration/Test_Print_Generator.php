<?php
/**
 * Integration tests for OC_Print_Generator.
 *
 * Tests the end-to-end print file generation flow: DB record lifecycle,
 * dispatch to drivers, and error handling.
 *
 * Requires: WordPress + WooCommerce + Composer vendor (TCPDF) test environment.
 *   WP_TESTS_DIR=/path/to/wp-tests vendor/bin/phpunit --testsuite Integration
 *
 * @package OverCustomise
 */

use PHPUnit\Framework\Attributes\Test;

class Test_Print_Generator extends WC_Unit_Test_Case {

	/** @var WC_Order */
	private WC_Order $order;

	/** @var object Fake print area object. */
	private object $area;

	public function setUp(): void {
		parent::setUp();

		$this->order = WC_Helper_Order::create_order();

		// Insert a config + area so the generator can find them.
		global $wpdb;

		$product_id = (int) current( $this->order->get_items() )->get_product_id();

		$wpdb->insert( $wpdb->prefix . 'oc_product_configs', [
			'product_id'  => $product_id,
			'custom_type' => 'text_only',
			'flat_rate'   => 0.00,
			'active'      => 1,
		] );
		$config_id = (int) $wpdb->insert_id;

		$wpdb->insert( $wpdb->prefix . 'oc_print_areas', [
			'config_id'    => $config_id,
			'area_key'     => 'front',
			'label'        => 'Front',
			'print_method' => 'engraving',
			'canvas_x'     => 50,
			'canvas_y'     => 50,
			'canvas_w'     => 200,
			'canvas_h'     => 100,
			'sort_order'   => 0,
		] );
		$area_id = (int) $wpdb->insert_id;

		$this->area = (object) [
			'id'           => $area_id,
			'config_id'    => $config_id,
			'area_key'     => 'front',
			'label'        => 'Front',
			'print_method' => 'engraving',
			'canvas_x'     => 50,
			'canvas_y'     => 50,
			'canvas_w'     => 200,
			'canvas_h'     => 100,
			'sort_order'   => 0,
		];
	}

	// ── DB record lifecycle ───────────────────────────────────────────────────

	#[Test]
	public function unknown_print_method_fails_and_rolls_record_back_to_pending(): void {
		$bad_area = clone $this->area;
		$bad_area->print_method = 'fax'; // Not a real method.

		$items  = $this->order->get_items();
		$item   = current( $items );
		$key    = key( $items );

		// Add customisation meta to the order item.
		$item->update_meta_data( '_oc_customisation', [
			'front' => [
				'text'                => 'Test',
				'fontId'              => 0,
				'color'               => '#000000',
				'artworkAttachmentId' => 0,
			],
		] );
		$item->save();

		// Count records before.
		global $wpdb;
		$before_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}oc_print_files" );

		// Call generate — this inserts a record then should set it to 'pending' on failure.
		$generator = new OC_Print_Generator();

		// Expose generate_for_area via a fresh generate_for_order call using bad area.
		// Since generate_for_order iterates the real areas from DB, we call dispatch indirectly
		// by inserting our own record and calling a method.
		// Instead: test via the full generate_for_order path with a known-bad method in DB.
		$wpdb->update(
			$wpdb->prefix . 'oc_print_areas',
			[ 'print_method' => 'fax' ],
			[ 'id' => $this->area->id ]
		);

		$generator->generate_for_order( $this->order );

		// A record should now exist.
		$after_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}oc_print_files" );
		$this->assertGreaterThan( $before_count, $after_count );

		// The inserted record should be 'pending' (rolled back on failure).
		$record = $wpdb->get_row(
			"SELECT * FROM {$wpdb->prefix}oc_print_files ORDER BY id DESC LIMIT 1"
		);
		$this->assertSame( 'pending', $record->file_status );
	}

	#[Test]
	public function regenerate_throws_for_missing_record(): void {
		$this->expectException( \RuntimeException::class );
		( new OC_Print_Generator() )->regenerate( 999999 );
	}

	// ── Engraving driver produces a file ─────────────────────────────────────

	#[Test]
	public function engraving_generates_pdf_file(): void {
		if ( ! class_exists( 'TCPDF' ) ) {
			$autoloader = OC_PATH . 'vendor/autoload.php';
			if ( ! file_exists( $autoloader ) ) {
				$this->markTestSkipped( 'Composer vendor not installed — run `composer install`.' );
			}
			require_once $autoloader;
		}

		$items  = $this->order->get_items();
		$item   = current( $items );
		$key    = key( $items );

		$area_data = [
			'text'                => 'Integration Test',
			'fontId'              => 0,
			'color'               => '#000000',
			'artworkAttachmentId' => 0,
		];

		$file_path = OC_Print_Engraving::generate( $this->order, (int) $key, $this->area, $area_data );

		$this->assertNotEmpty( $file_path );
		$this->assertFileExists( $file_path );
		$this->assertStringEndsWith( '.pdf', $file_path );

		// PDF file should have PDF header.
		$header = file_get_contents( $file_path, false, null, 0, 4 );
		$this->assertSame( '%PDF', $header );

		// Clean up.
		@unlink( $file_path );
	}
}
