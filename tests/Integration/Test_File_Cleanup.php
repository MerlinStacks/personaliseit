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

		$reference_queries = 0;
		$query_filter      = static function ( string $query ) use ( &$reference_queries ): string {
			if ( str_contains( $query, 'SELECT DISTINCT file_path AS path FROM' ) && str_contains( $query, 'UNION' ) ) {
				++$reference_queries;
			}
			return $query;
		};
		add_filter( 'query', $query_filter );
		OC_File_Cleanup::run();
		remove_filter( 'query', $query_filter );
		$this->assertFileExists( $file );
		$this->assertFileExists( $thumb );
		$this->assertSame( 'expired', OC_DB::get_print_file( $expired_id )->file_status );
		$this->assertSame( 1, $reference_queries );

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
		$mutable_method = new ReflectionMethod( OC_File_Cleanup::class, 'mutable_payload_has_reference' );
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
		$this->assertTrue( $mutable_method->invoke( null, [ $preview_id ] ) );
		$this->assertFalse( $mutable_method->invoke( null, [ str_repeat( '9', 40 ) ] ) );

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
	public function artwork_cleanup_batches_wildcard_reference_scans(): void {
		global $wpdb;
		$method        = new ReflectionMethod( OC_File_Cleanup::class, 'customer_artwork_batch_references' );
		$session_table = $wpdb->prefix . 'woocommerce_sessions';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $session_table ) ) !== $session_table ) {
			$this->markTestSkipped( 'WooCommerce sessions table is unavailable.' );
		}
		$wpdb->insert(
			$session_table,
			[
				'session_key'    => 'oc-cleanup-batch-test',
				'session_value'  => serialize( [ 'cart' => [ [ 'attachmentId' => 551234 ] ] ] ),
				'session_expiry' => time() + HOUR_IN_SECONDS,
			]
		);
		$regexp_queries = 0;
		$show_queries   = 0;
		$query_filter   = static function ( string $query ) use ( &$regexp_queries, &$show_queries ): string {
			$regexp_queries += str_contains( $query, ' REGEXP ' ) ? 1 : 0;
			$show_queries   += str_starts_with( trim( $query ), 'SHOW TABLES' ) ? 1 : 0;
			return $query;
		};
		add_filter( 'query', $query_filter );
		try {
			$references = $method->invoke( null, [ 551234, 551235 ] );
			$this->assertArrayHasKey( 551234, $references );
			$this->assertArrayNotHasKey( 551235, $references );
			$this->assertSame( 3, $regexp_queries );
			$this->assertLessThanOrEqual( 1, $show_queries );
		} finally {
			remove_filter( 'query', $query_filter );
			$wpdb->delete( $session_table, [ 'session_key' => 'oc-cleanup-batch-test' ] );
		}
	}

	#[Test]
	public function preview_reference_query_failure_retains_the_whole_batch(): void {
		global $wpdb;
		$filter = static function ( string $query ): string {
			if ( str_contains( $query, 'meta_id AS row_id' ) && str_contains( $query, 'woocommerce_order_itemmeta' ) ) {
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
			delete_option( 'oc_preview_reference_scan_adhoc' );
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
		$unsafe_query = false;
		$query_filter = function ( string $query ) use ( &$query_counts, &$unsafe_query ): string {
			if ( preg_match( '/(?:meta_value|session_value) LIKE [\'\"]%/', $query ) ) {
				$unsafe_query = true;
			}
			if ( str_contains( $query, 'woocommerce_order_itemmeta' ) && str_contains( $query, 'meta_id AS row_id' ) ) {
				$query_counts['order']++;
				$this->assertStringContainsString( "meta_key IN ('_oc_customisation','_oc_preview_url')", $query );
			} elseif ( str_contains( $query, 'woocommerce_sessions' ) && str_contains( $query, 'session_id AS row_id' ) ) {
				$query_counts['session']++;
			} elseif ( str_contains( $query, 'umeta_id AS row_id' ) && str_contains( $query, '_woocommerce_persistent_cart_' ) ) {
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
			$this->assertGreaterThanOrEqual( 1, $query_counts['order'] );
			$this->assertFalse( $unsafe_query );
		} finally {
			remove_filter( 'query', $query_filter );
			delete_option( 'oc_private_preview_' . $id );
			delete_option( 'oc_private_preview_' . $unused_id );
			delete_option( 'oc_preview_cleanup_private_cursor' );
			$order->delete( true );
		}
	}

	#[Test]
	public function preview_references_are_found_beyond_the_first_payload_page(): void {
		global $wpdb;
		$reference = str_repeat( '7', 40 );
		$filter = static fn ( mixed $value ): int => 1;
		add_filter( 'oc_preview_reference_payload_batch_size', $filter );
		$wpdb->insert( $wpdb->prefix . 'woocommerce_order_itemmeta', [
			'order_item_id' => 1,
			'meta_key'      => '_oc_customisation',
			'meta_value'    => 'unrelated-preview',
		] );
		$wpdb->insert( $wpdb->prefix . 'woocommerce_order_itemmeta', [
			'order_item_id' => 1,
			'meta_key'      => '_oc_customisation',
			'meta_value'    => wp_json_encode( [ 'preview_id' => $reference ] ),
		] );

		try {
			$method = new ReflectionMethod( OC_File_Cleanup::class, 'stored_payload_references' );
			$this->assertArrayHasKey( $reference, $method->invoke( null, [ $reference ] ) );
		} finally {
			remove_filter( 'oc_preview_reference_payload_batch_size', $filter );
		}
	}

	#[Test]
	public function preview_reference_scan_resumes_its_saved_cursor(): void {
		global $wpdb;
		$reference = str_repeat( '8', 40 );
		$wpdb->insert( $wpdb->prefix . 'woocommerce_order_itemmeta', [
			'order_item_id' => 1,
			'meta_key'      => '_oc_customisation',
			'meta_value'    => 'already-scanned',
		] );
		$cursor = (int) $wpdb->insert_id;
		$wpdb->insert( $wpdb->prefix . 'woocommerce_order_itemmeta', [
			'order_item_id' => 1,
			'meta_key'      => '_oc_customisation',
			'meta_value'    => wp_json_encode( [ 'preview_id' => $reference ] ),
		] );
		$maximum = (int) $wpdb->insert_id;
		update_option( 'oc_preview_reference_scan_adhoc', [
			'signature'  => hash( 'sha256', (string) wp_json_encode( [ [ $reference ], [] ] ) ),
			'source'     => 'order',
			'cursor'     => $cursor,
			'max'        => $maximum,
			'active_at'  => 0,
			'references' => [],
		], false );

		try {
			$method = new ReflectionMethod( OC_File_Cleanup::class, 'stored_payload_references' );
			$this->assertArrayHasKey( $reference, $method->invoke( null, [ $reference ] ) );
			$this->assertFalse( get_option( 'oc_preview_reference_scan_adhoc', false ) );
		} finally {
			delete_option( 'oc_preview_reference_scan_adhoc' );
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
