<?php
/** Exact upgrade spellings with real storage/generator/cleanup code and an in-memory DB boundary. */
declare(strict_types=1);
define( 'ABSPATH', __DIR__ . '/' );
define( 'DAY_IN_SECONDS', 86400 );
function wp_normalize_path( $path ) { return str_replace( '\\', '/', $path ); }
function wp_upload_dir() { return [ 'basedir' => $GLOBALS['fixture'] . '/uploads-alias' ]; }
function get_option( $name, $default = false ) { return $default; }
function update_option( $name, $value, $autoload = false ) { return true; }
function apply_filters( $name, $value, ...$args ) {
	if ( 'oc_private_storage_web_protected' === $name ) { return $GLOBALS['protected']; }
	return $value;
}
function current_time( $type, $gmt = false ) { return '2026-09-07 00:00:00'; }
class OC_Upload_Handler {
	public static function private_storage_path( string $subdirectory = '', bool $force = false ): ?string { return null; }
}
class OC_Logger { public static function warning( $message ): void {} }
class OC_DB {
	public static int $updates = 0;
	public static function update_print_file( int $id, array $data ): bool { ++self::$updates; return true; }
}
class wpdb {
	public string $prefix = 'wp_';
	public string $last_error = '';
	public string $failure = '';
	public array $rows = [];
	public array $queries = [];
	public object $current;
	public function prepare( string $sql, mixed ...$args ): array { return [ $sql, $args ]; }
	public function get_row( array $query ): object { $this->last_error = ''; return $this->current; }
	public function get_col( array $query ): ?array {
		$this->queries[] = $query;
		$this->last_error = 'error' === $this->failure ? 'Reference lookup failed' : '';
		if ( 'throw' === $this->failure ) { throw new RuntimeException( 'Reference lookup failed' ); }
		if ( 'null' === $this->failure ) { return null; }
		[ $sql, $args ] = $query;
		if ( ! str_contains( $sql, 'file_path IN (' ) || ! str_contains( $sql, 'thumbnail_path IN (' ) ) { throw new LogicException( 'Expected exact IN queries for both columns' ); }
		$found = [];
		foreach ( $this->rows as $row ) {
			foreach ( [ 'file_path', 'thumbnail_path' ] as $column ) {
				if ( isset( $row[$column] ) && in_array( $row[$column], $args, true ) ) { $found[] = $row[$column]; }
			}
		}
		return $found;
	}
	public function get_var( array $query ): mixed {
		$found = $this->get_col( $query );
		return null === $found ? null : count( $found );
	}
}
require dirname( __DIR__ ) . '/includes/print/class-oc-print-base.php';
require dirname( __DIR__ ) . '/includes/class-oc-print-generator.php';
require dirname( __DIR__ ) . '/includes/class-oc-file-cleanup.php';
function check( bool $ok, string $message ): void { if ( ! $ok ) { throw new LogicException( $message ); } }
$fixture = __DIR__ . '/print-alias-fixture-' . bin2hex( random_bytes( 6 ) );
$protected = true;
$wpdb = new wpdb();
mkdir( $fixture );
try {
	$root = $fixture . '/uploads/overcustomise/print-files';
	mkdir( $root, 0750, true );
	symlink( $fixture . '/uploads', $fixture . '/uploads-alias' );
	symlink( $fixture . '/uploads', $fixture . '/arbitrary-alias' );
	symlink( $root, $root . '/nested-alias' );
	$canonical = $root . '/shared.pdf';
	$alias = $fixture . '/uploads-alias/overcustomise/print-files/shared.pdf';
	$superseded = new ReflectionMethod( OC_Print_Generator::class, 'delete_superseded_artifacts' );
	$expire = new ReflectionMethod( OC_File_Cleanup::class, 'expire_locked_record' );
	foreach ( [ [ $canonical, $alias ], [ $alias, $canonical ] ] as [ $candidate, $reference ] ) {
		foreach ( [ 'file_path', 'thumbnail_path' ] as $candidate_column ) {
			foreach ( [ 'file_path', 'thumbnail_path' ] as $reference_column ) {
				file_put_contents( $canonical, 'shared output or thumbnail' );
				$wpdb->rows = [ [ $reference_column => $reference ] ];
				$wpdb->current = (object) [ 'id' => 1, 'order_id' => 2, 'order_item_id' => 3, 'file_status' => 'files_ready', 'expires_at' => '2020-01-01 00:00:00', $candidate_column => $candidate ];
				OC_Print_Generator::remove_uncommitted_artifacts( [ $candidate ] );
				check( is_file( $canonical ), 'Worker removed mixed-spelling shared artifact' );
				$superseded->invoke( null, [ $candidate ], [] );
				check( is_file( $canonical ), 'Regeneration removed mixed-spelling predecessor' );
				check( $expire->invoke( null, $wpdb->current, null ) && is_file( $canonical ), 'Expiry removed mixed-spelling shared artifact' );
			}
		}
		foreach ( [ 'error', 'null', 'throw' ] as $failure ) {
			$wpdb->failure = $failure;
			$wpdb->rows = [];
			OC_Print_Generator::remove_uncommitted_artifacts( [ $candidate ] );
			$superseded->invoke( null, [ $candidate ], [] );
			$updates = OC_DB::$updates;
			try { $expire->invoke( null, $wpdb->current, null ); throw new LogicException( 'DB failure allowed expiry' ); } catch ( RuntimeException $expected ) {}
			check( is_file( $canonical ) && OC_DB::$updates === $updates, 'DB failure lost file or record' );
		}
		$wpdb->failure = '';
		OC_Print_Generator::remove_uncommitted_artifacts( [ $candidate ] );
		check( ! file_exists( $canonical ), 'Unreferenced artifact was not removed' );
		file_put_contents( $canonical, 'unreferenced predecessor' );
		$superseded->invoke( null, [ $candidate ], [] );
		check( ! file_exists( $canonical ), 'Unreferenced predecessor was not removed' );
		file_put_contents( $canonical, 'unreferenced expired output' );
		$wpdb->current->file_path = $candidate;
		$wpdb->current->thumbnail_path = null;
		check( $expire->invoke( null, $wpdb->current, null ) && ! file_exists( $canonical ), 'Unreferenced expiry did not delete output' );
	}
	file_put_contents( $canonical, 'retained' );
	$backup = new ReflectionMethod( OC_Print_Generator::class, 'generate_with_backup' );
	foreach ( [ '', 'error', 'null', 'throw' ] as $failure ) {
		$wpdb->failure = $failure;
		$wpdb->rows = [ [ 'thumbnail_path' => $alias ] ];
		try {
			$backup->invoke( null, '', static fn (): array => [ 'file_path' => $canonical ], static function ( array &$result ): void { throw new RuntimeException( 'Commit failed' ); } );
			throw new LogicException( 'Commit failure swallowed' );
		} catch ( RuntimeException $expected ) {}
		check( is_file( $canonical ), 'Rollback deleted a shared or uncertain output' );
	}
	$wpdb->failure = '';
	$wpdb->rows = [];
	foreach ( [ $fixture . '/arbitrary-alias/overcustomise/print-files/shared.pdf', $root . '/nested-alias/shared.pdf', $root . '/../print-files/shared.pdf' ] as $bad ) {
		check( [] === OC_Storage_Upgrade::file_reference_paths( $bad ), 'Arbitrary alias acquired equivalence' );
		OC_Print_Generator::remove_uncommitted_artifacts( [ $bad ] );
		check( is_file( $canonical ), 'Arbitrary alias authorized deletion' );
	}
	foreach ( [ $alias . '.missing', $fixture . '/unmounted/print-files/missing.pdf' ] as $missing ) {
		$wpdb->current->file_path = $missing;
		$wpdb->current->thumbnail_path = null;
		$updates = OC_DB::$updates;
		try { $expire->invoke( null, $wpdb->current, null ); throw new LogicException( 'Uncertain missing path expired' ); } catch ( RuntimeException $expected ) {}
		check( OC_DB::$updates === $updates, 'Uncertain missing path lost its record' );
	}
	$wpdb->current->file_path = $canonical . '.missing';
	check( $expire->invoke( null, $wpdb->current, null ), 'Known missing canonical file could not expire' );
	fwrite( STDOUT, "Print alias reference regressions passed.\n" );
} finally {
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $fixture, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $iterator as $file ) {
		if ( $file->isLink() || ! $file->isDir() ) { unlink( $file->getPathname() ); } else { rmdir( $file->getPathname() ); }
	}
	rmdir( $fixture );
}
