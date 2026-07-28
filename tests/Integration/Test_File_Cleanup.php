<?php
/**
 * Integration tests for OC_File_Cleanup.
 *
 * Verifies that expired print file records are deleted (DB row + file on disk)
 * and that non-expired records are left alone.
 *
 * Requires: WordPress test environment.
 *   WP_TESTS_DIR=/path/to/wp-tests vendor/bin/phpunit --testsuite Integration
 *
 * @package OverCustomise
 */

use PHPUnit\Framework\Attributes\Test;

class Test_File_Cleanup extends WP_UnitTestCase {

	/** Temporary files created during tests, cleaned up in tearDown. */
	private array $tmp_files = [];

	public function tearDown(): void {
		foreach ( $this->tmp_files as $path ) {
			if ( file_exists( $path ) ) {
				@unlink( $path );
			}
		}
		$this->tmp_files = [];
		parent::tearDown();
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	private function insert_print_file( string $status, string $expires_at, ?string $file_path = null ): int {
		return OC_DB::insert_print_file( [
			'order_id'      => 1,
			'order_item_id' => 1,
			'print_area_id' => 1,
			'file_type'     => 'uv',
			'file_status'   => $status,
			'file_path'     => $file_path,
			'generated_at'  => gmdate( 'Y-m-d H:i:s' ),
			'expires_at'    => $expires_at,
		] );
	}

	private function make_tmp_file(): string {
		$uploads = wp_upload_dir();
		$directory = trailingslashit( $uploads['basedir'] ) . 'overcustomise/print-files/test';
		wp_mkdir_p( $directory );
		$path = tempnam( $directory, 'oc_cleanup_test_' );
		file_put_contents( $path, 'test content' );
		$this->tmp_files[] = $path;
		return $path;
	}

	// ── Tests ─────────────────────────────────────────────────────────────────

	#[Test]
	public function expired_file_record_is_deleted_and_file_removed(): void {
		$tmp_path = $this->make_tmp_file();

		// Insert a record that expired yesterday.
		$id = $this->insert_print_file(
			'files_ready',
			gmdate( 'Y-m-d H:i:s', strtotime( '-1 day' ) ),
			$tmp_path
		);

		$this->assertGreaterThan( 0, $id );
		$this->assertFileExists( $tmp_path );

		OC_File_Cleanup::run();

		// File should be gone from disk.
		$this->assertFileDoesNotExist( $tmp_path );

		// Record should be marked as expired (not deleted from DB — for audit trail).
		$record = OC_DB::get_print_file( $id );
		$this->assertNotNull( $record );
		$this->assertSame( 'expired', $record->file_status );
	}

	#[Test]
	public function non_expired_file_is_not_cleaned_up(): void {
		$tmp_path = $this->make_tmp_file();

		$id = $this->insert_print_file(
			'files_ready',
			gmdate( 'Y-m-d H:i:s', strtotime( '+90 days' ) ),
			$tmp_path
		);

		OC_File_Cleanup::run();

		// File should still be on disk.
		$this->assertFileExists( $tmp_path );

		// Status should remain 'files_ready'.
		$record = OC_DB::get_print_file( $id );
		$this->assertSame( 'files_ready', $record->file_status );
	}

	#[Test]
	public function expired_awaiting_dst_record_is_also_expired(): void {
		$id = $this->insert_print_file(
			'awaiting_dst_upload',
			gmdate( 'Y-m-d H:i:s', strtotime( '-1 day' ) ),
			null
		);

		OC_File_Cleanup::run();

		$record = OC_DB::get_print_file( $id );
		$this->assertSame( 'expired', $record->file_status );
	}

	#[Test]
	public function cleanup_handles_missing_file_gracefully(): void {
		// Record points to a file that doesn't exist.
		$id = $this->insert_print_file(
			'files_ready',
			gmdate( 'Y-m-d H:i:s', strtotime( '-1 day' ) ),
			'/tmp/nonexistent-oc-file-99999.pdf'
		);

		// Should not throw.
		OC_File_Cleanup::run();

		$record = OC_DB::get_print_file( $id );
		$this->assertSame( 'expired', $record->file_status );
	}

	#[Test]
	public function pending_records_are_not_cleaned_up(): void {
		$id = $this->insert_print_file(
			'pending',
			gmdate( 'Y-m-d H:i:s', strtotime( '-1 day' ) ),
			null
		);

		OC_File_Cleanup::run();

		$record = OC_DB::get_print_file( $id );
		// 'pending' status is not in the cleanup query — should be untouched.
		$this->assertSame( 'pending', $record->file_status );
	}

	#[Test]
	public function cleanup_removes_thumbnail_and_shared_output_only_after_all_references_expire(): void {
		$file  = $this->make_tmp_file();
		$thumb = $this->make_tmp_file();
		$past  = gmdate( 'Y-m-d H:i:s', strtotime( '-1 day' ) );
		$future = gmdate( 'Y-m-d H:i:s', strtotime( '+1 day' ) );

		$expired_id = $this->insert_print_file( 'files_ready', $past, $file );
		OC_DB::update_print_file( $expired_id, [ 'thumbnail_path' => $thumb ] );
		$active_id = $this->insert_print_file( 'files_ready', $future, $file );
		OC_DB::update_print_file( $active_id, [ 'thumbnail_path' => $thumb ] );

		OC_File_Cleanup::run();
		$this->assertFileExists( $file );
		$this->assertFileExists( $thumb );
		$this->assertSame( 'expired', OC_DB::get_print_file( $expired_id )->file_status );

		OC_DB::update_print_file( $active_id, [ 'expires_at' => $past ] );
		OC_File_Cleanup::run();
		$this->assertFileDoesNotExist( $file );
		$this->assertFileDoesNotExist( $thumb );
	}

	#[Test]
	public function cleanup_does_not_delete_existing_file_outside_uploads(): void {
		$path = tempnam( sys_get_temp_dir(), 'oc_cleanup_outside_' );
		$this->tmp_files[] = $path;
		$id = $this->insert_print_file( 'files_ready', gmdate( 'Y-m-d H:i:s', strtotime( '-1 day' ) ), $path );

		OC_File_Cleanup::run();

		$this->assertFileExists( $path );
		$this->assertSame( 'files_ready', OC_DB::get_print_file( $id )->file_status );
	}

	#[Test]
	public function cleanup_does_not_delete_unrelated_upload_file(): void {
		$uploads = wp_upload_dir();
		$path    = tempnam( $uploads['basedir'], 'oc_cleanup_unrelated_' );
		$this->tmp_files[] = $path;
		$id = $this->insert_print_file( 'files_ready', gmdate( 'Y-m-d H:i:s', strtotime( '-1 day' ) ), $path );

		OC_File_Cleanup::run();

		$this->assertFileExists( $path );
		$this->assertSame( 'files_ready', OC_DB::get_print_file( $id )->file_status );
	}

	#[Test]
	public function active_wc_session_and_persistent_cart_references_protect_artwork(): void {
		global $wpdb;
		$method         = new ReflectionMethod( OC_File_Cleanup::class, 'customer_artwork_is_referenced' );
		$preview_method = new ReflectionMethod( OC_File_Cleanup::class, 'stored_payload_references' );
		$preview_id     = str_repeat( 'c', 40 );
		$preview_file   = 'preview-' . str_repeat( 'd', 40 ) . '.jpg';
		$session_table = $wpdb->prefix . 'woocommerce_sessions';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $session_table ) ) !== $session_table ) {
			$this->markTestSkipped( 'WooCommerce sessions table is unavailable.' );
		}

		$wpdb->insert( $session_table, [
			'session_key'    => 'oc-cleanup-test',
			'session_value'  => serialize( [ 'cart' => [ [ 'sourceAttachmentId' => 45671, 'preview_id' => $preview_id ] ] ] ),
			'session_expiry' => time() + HOUR_IN_SECONDS,
		] );
		$this->assertTrue( $method->invoke( null, 45671 ) );
		$this->assertFalse( $method->invoke( null, 4567 ) );

		$user_id = self::factory()->user->create();
		update_user_meta( $user_id, '_woocommerce_persistent_cart_1', [
			'cart' => [ [ 'customisation' => [ 'previewAttachmentId' => 45672, 'previewUrl' => $preview_file ] ] ],
		] );
		$this->assertTrue( $method->invoke( null, 45672 ) );
		$preview_references = $preview_method->invoke( null, [ $preview_id, $preview_file ] );
		$this->assertArrayHasKey( $preview_id, $preview_references );
		$this->assertArrayHasKey( $preview_file, $preview_references );
	}

	#[Test]
	public function preview_reference_query_failure_retains_the_whole_batch(): void {
		global $wpdb;
		$filter = static function ( string $query ): string {
			if ( str_contains( $query, 'SELECT meta_value FROM' ) && str_contains( $query, 'woocommerce_order_itemmeta' ) ) {
				return 'SELECT oc_missing_column FROM oc_missing_preview_reference_table';
			}
			return $query;
		};
		add_filter( 'query', $filter );
		$previous_suppression = $wpdb->suppress_errors( true );
		try {
			$method     = new ReflectionMethod( OC_File_Cleanup::class, 'stored_payload_references' );
			$candidates = [ str_repeat( 'e', 40 ), 'preview-' . str_repeat( 'f', 40 ) . '.png' ];
			$this->assertSame( array_fill_keys( $candidates, true ), $method->invoke( null, $candidates ) );
		} finally {
			$wpdb->suppress_errors( $previous_suppression );
			remove_filter( 'query', $filter );
		}
	}

	#[Test]
	public function order_reference_protects_an_expired_private_preview(): void {
		$directory = OC_Upload_Handler::private_storage_path( 'previews' );
		$this->assertIsString( $directory );
		$id         = str_repeat( 'a', 40 );
		$filename   = 'preview-' . $id . '.png';
		$path       = $directory . '/' . $filename;
		$unused_id  = str_repeat( 'b', 40 );
		$unused_file = 'preview-' . $unused_id . '.png';
		$unused_path = $directory . '/' . $unused_file;
		file_put_contents( $path, 'preview' );
		file_put_contents( $unused_path, 'unused preview' );
		touch( $path, time() - ( 400 * DAY_IN_SECONDS ) );
		touch( $unused_path, time() - ( 400 * DAY_IN_SECONDS ) );
		$this->tmp_files[] = $path;
		$this->tmp_files[] = $unused_path;
		update_option( 'oc_private_preview_' . $id, wp_json_encode( [
			'file'       => $filename,
			'created_at' => time() - ( 400 * DAY_IN_SECONDS ),
		] ), false );
		update_option( 'oc_private_preview_' . $unused_id, wp_json_encode( [
			'file'       => $unused_file,
			'created_at' => time() - ( 400 * DAY_IN_SECONDS ),
		] ), false );
		update_option( 'oc_preview_cleanup_private_cursor', 0, false );

		$order = WC_Helper_Order::create_order();
		$item  = current( $order->get_items() );
		$item->update_meta_data( '_oc_preview_url', 'https://example.test/?preview_id=' . $id );
		$item->save();
		$query_counts = [ 'order' => 0, 'session' => 0, 'cart' => 0 ];
		$query_filter = static function ( string $query ) use ( &$query_counts ): string {
			if ( str_contains( $query, 'woocommerce_order_itemmeta' ) && str_contains( $query, 'SELECT meta_value' ) ) {
				$query_counts['order']++;
			} elseif ( str_contains( $query, 'woocommerce_sessions' ) && str_contains( $query, 'SELECT session_value' ) ) {
				$query_counts['session']++;
			} elseif ( str_contains( $query, 'SELECT meta_value FROM' ) && str_contains( $query, '_woocommerce_persistent_cart_' ) ) {
				$query_counts['cart']++;
			}
			return $query;
		};
		add_filter( 'query', $query_filter );
		try {
			$method = new ReflectionMethod( OC_File_Cleanup::class, 'cleanup_private_preview_images' );
			$this->assertSame( 1, $method->invoke( null, time() - DAY_IN_SECONDS, 200 ) );
			$this->assertFileExists( $path );
			$this->assertFileDoesNotExist( $unused_path );
			$this->assertNotFalse( get_option( 'oc_private_preview_' . $id, false ) );
			$this->assertFalse( get_option( 'oc_private_preview_' . $unused_id, false ) );
			$this->assertSame( [ 'order' => 1, 'session' => 1, 'cart' => 1 ], $query_counts );
		} finally {
			remove_filter( 'query', $query_filter );
			delete_option( 'oc_private_preview_' . $id );
			delete_option( 'oc_private_preview_' . $unused_id );
			delete_option( 'oc_preview_cleanup_private_cursor' );
			$order->delete( true );
		}
	}

	#[Test]
	public function security_budget_cleanup_does_not_delete_its_cursor_option(): void {
		global $wpdb;
		$expired = 'oc_budget_' . str_repeat( 'a', 64 );
		$active  = 'oc_budget_' . str_repeat( 'b', 64 );
		delete_option( $expired );
		delete_option( $active );
		update_option( 'oc_budget_cleanup_cursor', 0, false );
		$cursor_id = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT option_id FROM {$wpdb->options} WHERE option_name = %s",
			'oc_budget_cleanup_cursor'
		) );
		$window_start = time() - 10;
		add_option( $expired, wp_json_encode( [
			'version'      => 1,
			'window_start' => $window_start - HOUR_IN_SECONDS,
			'window_end'   => time() - 1,
			'count'        => 1,
			'bytes'        => 0,
		] ), '', false );
		add_option( $active, wp_json_encode( [
			'version'      => 1,
			'window_start' => $window_start,
			'window_end'   => time() + HOUR_IN_SECONDS,
			'count'        => 1,
			'bytes'        => 0,
		] ), '', false );

		try {
			$method = new ReflectionMethod( OC_File_Cleanup::class, 'cleanup_security_budgets' );
			$method->invoke( null );

			$this->assertFalse( get_option( $expired, false ) );
			$this->assertNotFalse( get_option( $active, false ) );
			$this->assertSame( $cursor_id, (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT option_id FROM {$wpdb->options} WHERE option_name = %s",
				'oc_budget_cleanup_cursor'
			) ) );
		} finally {
			delete_option( $expired );
			delete_option( $active );
			delete_option( 'oc_budget_cleanup_cursor' );
		}
	}
}
