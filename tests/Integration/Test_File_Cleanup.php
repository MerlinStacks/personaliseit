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
		wp_mkdir_p( $uploads['basedir'] );
		$path = tempnam( $uploads['basedir'], 'oc_cleanup_test_' );
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
}
