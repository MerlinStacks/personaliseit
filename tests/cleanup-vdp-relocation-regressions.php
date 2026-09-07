<?php
/** Real relocation consumers and APIs; plugin-local files with WP/DB boundaries stubbed. */
declare(strict_types=1);
$fixture = __DIR__ . '/.cleanup-relocation-' . bin2hex( random_bytes( 6 ) );
define( 'ABSPATH', $fixture . '/site/' );
define( 'DAY_IN_SECONDS', 86400 );
$options = [];
function wp_normalize_path( $path ) { return str_replace( '\\', '/', $path ); }
function wp_upload_dir() { return [ 'basedir' => $GLOBALS['fixture'] . '/uploads' ]; }
function wp_salt( $scheme ) { return 'cleanup-relocation'; }
function get_current_blog_id() { return 1; }
function get_option( $key, $default = false ) { return $GLOBALS['options'][$key] ?? $default; }
function update_option( $key, $value, ...$args ) { $GLOBALS['options'][$key] = $value; return true; }
function delete_option( $key ) { unset( $GLOBALS['options'][$key] ); return true; }
function wp_cache_delete( ...$args ) {}
function wp_json_encode( $value ) { return json_encode( $value ); }
function absint( $value ) { return abs( (int) $value ); }
function apply_filters( $hook, $value, ...$args ) { return $value; }
function is_wp_error( $value ) { return false; }
function sanitize_key( $value ) { return strtolower( $value ); }
function __( $text, ...$args ) { return $text; }
class OC_Logger { public static function warning( $message ): void {} }
class OC_Upload_Handler {
	public static bool $blocked = false;
	public static function private_storage_path( string $directory = '', bool $force = false ): ?string {
		return self::$blocked ? null : $GLOBALS['fixture'] . '/current/' . $directory;
	}
}
class OC_DB {
	public static object $template;
	public static function get_vdp_template( int $id ): object { return clone self::$template; }
	public static function get_vdp_fields( int $id ): array { return [ (object) [ 'id' => 9, 'field_name' => 'name', 'layer_id' => 4, 'sort_order' => 0 ] ]; }
}
class wpdb {
	public string $prefix = 'wp_';
	public string $options = 'wp_options';
	public string $usermeta = 'wp_usermeta';
	public string $last_error = '';
	public bool $fail_publication = false;
	public function prepare( string $sql, mixed ...$args ): array { return [ $sql, $args ]; }
	public function esc_like( string $text ): string { return $text; }
	public function get_results( array $query ): array {
		$rows = [];
		foreach ( $GLOBALS['options'] as $name => $raw ) {
			if ( str_starts_with( $name, 'oc_private_preview_' ) ) { $rows[] = (object) [ 'option_id' => count( $rows ) + 1, 'option_name' => $name, 'option_value' => $raw ]; }
		}
		return $rows;
	}
	public function get_var( mixed $query ): mixed { $this->last_error = ''; return null; }
	public function get_row( array $query ): object { $this->last_error = ''; return clone OC_DB::$template; }
	public function query( array $query ): int|false {
		[ $sql, $args ] = $query;
		$this->last_error = '';
		if ( str_starts_with( $sql, 'UPDATE ' ) ) {
			if ( $this->fail_publication ) { $this->last_error = 'Publication failed'; return false; }
			OC_DB::$template->csv_file_path = $args[0];
			return 1;
		}
		if ( str_starts_with( $sql, 'DELETE ' ) && get_option( $args[0] ) === $args[1] ) { delete_option( $args[0] ); return 1; }
		return 0;
	}
}
$wpdb = new wpdb();
require dirname( __DIR__ ) . '/includes/class-oc-storage-upgrade.php';
require dirname( __DIR__ ) . '/includes/class-oc-rest-api.php';
require dirname( __DIR__ ) . '/includes/class-oc-file-cleanup.php';
require dirname( __DIR__ ) . '/includes/class-oc-vdp.php';
function check( bool $ok, string $message ): void { if ( ! $ok ) { throw new LogicException( $message ); } }
mkdir( $fixture );
try {
	$old = $fixture . '/.overcustomise-private-' . substr( hash( 'sha256', wp_normalize_path( ABSPATH ) ), 0, 12 );
	foreach ( [ ABSPATH, $fixture . '/uploads', $fixture . '/current/previews', $fixture . '/current/vdp', $old . '/previews', $old . '/vdp' ] as $directory ) { mkdir( $directory, 0750, true ); }
	$data = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=' ) . str_repeat( 'x', 100 );
	$record = [ 'version' => 1, 'file' => 'preview-' . str_repeat( 'a', 40 ) . '.png', 'mime' => 'image/png', 'bytes' => strlen( $data ), 'content_hash' => hash( 'sha256', $data ), 'secret' => str_repeat( 'b', 64 ), 'created_at' => time() - 100 * DAY_IN_SECONDS ];
	$id = substr( hash_hmac( 'sha256', $record['content_hash'], wp_salt( 'nonce' ) ), 0, 40 );
	$key = 'oc_private_preview_' . $id;
	$raw = json_encode( $record );
	$options[$key] = $raw;
	$source = $old . '/previews/' . $record['file'];
	$target = $fixture . '/current/previews/' . $record['file'];
	file_put_contents( $source, $data );
	touch( $source, $record['created_at'], $record['created_at'] );
	$cleanup = new ReflectionMethod( OC_File_Cleanup::class, 'cleanup_private_preview_images' );
	$run = static fn (): int => $cleanup->invoke( null, time() - 90 * DAY_IN_SECONDS, 200 );
	check( 0 === $run() && is_file( $target ) && is_file( $source ), 'Cleanup before first signed read failed to relocate old preview' );
	check( get_option( $key ) === $raw && file_get_contents( $target ) === $data, 'Relocation changed preview metadata, secret or bytes' );
	check( 0 === $run() && get_option( $key ) === $raw, 'Relocated preview expired during refreshed retention' );
	unlink( $target );
	foreach ( [ 'missing', 'symlink', 'corrupt', 'blocked' ] as $failure ) {
		if ( file_exists( $source ) || is_link( $source ) ) { unlink( $source ); }
		if ( 'symlink' === $failure ) { file_put_contents( $fixture . '/arbitrary.png', $data ); symlink( $fixture . '/arbitrary.png', $source ); }
		if ( 'corrupt' === $failure ) { file_put_contents( $source, str_repeat( 'z', strlen( $data ) ) ); }
		OC_Upload_Handler::$blocked = 'blocked' === $failure;
		check( 0 === $run() && get_option( $key ) === $raw && ! file_exists( $target ), 'Unresolved preview lost metadata or secret: ' . $failure );
	}
	OC_Upload_Handler::$blocked = false;
	file_put_contents( $target, $data );
	touch( $target, $record['created_at'], $record['created_at'] );
	clearstatcache();
	check( 1 === $run() && ! file_exists( $target ) && false === get_option( $key ), 'Verified expired unreferenced preview did not clean up' );
	$csv = $old . '/vdp/template.csv';
	file_put_contents( $csv, "name\nAlice\nBob\n" );
	OC_DB::$template = (object) [ 'id' => 7, 'design_id' => 2, 'csv_file_path' => $csv, 'active' => 1, 'created_at' => '2020-01-01' ];
	$vdp = new OC_VDP();
	$template = $vdp->get_template( 2 );
	check( str_starts_with( $template['csv_file_path'], $fixture . '/current/vdp/' ) && is_file( $csv ), 'VDP consumer did not relocate while retaining source' );
	check( [ [ 'name' => 'Alice' ], [ 'name' => 'Bob' ] ] === $vdp->parse_csv( $template['csv_file_path'] )['rows'], 'Relocated VDP rows changed' );
	check( $template['fields'][0]['layer_id'] === 4 && $template['id'] === 7, 'VDP mapping or identity changed' );
	check( $vdp->get_template( 2 ) === $template, 'Current VDP resolution was not idempotent' );
	foreach ( [ 'missing', 'arbitrary', 'publication', 'blocked' ] as $failure ) {
		OC_DB::$template->csv_file_path = match ( $failure ) { 'missing' => $csv . '.missing', 'arbitrary' => $fixture . '/arbitrary.csv', default => $csv };
		file_put_contents( $fixture . '/arbitrary.csv', "name\nWrong\n" );
		$original = OC_DB::$template->csv_file_path;
		$wpdb->fail_publication = 'publication' === $failure;
		OC_Upload_Handler::$blocked = 'blocked' === $failure;
		try { $vdp->get_template( 2 ); throw new LogicException( 'Unresolved VDP permitted fallback: ' . $failure ); } catch ( RuntimeException $expected ) {}
		check( OC_DB::$template->csv_file_path === $original && is_file( $csv ), 'Failed VDP resolution lost original pointer or source' );
	}
	fwrite( STDOUT, "Cleanup and VDP relocation regressions passed.\n" );
} finally {
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $fixture, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $iterator as $file ) { if ( $file->isLink() || ! $file->isDir() ) { unlink( $file->getPathname() ); } else { rmdir( $file->getPathname() ); } }
	rmdir( $fixture );
}
