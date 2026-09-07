<?php
/** Dependency-free regression smoke tests for the owned audit fixes. Run: php tests/audit-security.php */

$plugin = dirname( __DIR__ );
$fixture = __DIR__ . '/.audit-' . bin2hex( random_bytes( 6 ) );
define( 'ABSPATH', $fixture . '/site/' );
define( 'OC_PRIVATE_STORAGE_ROOT', $fixture . '/private' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );
$_SERVER['DOCUMENT_ROOT'] = ABSPATH;
$options = $transients = [];
$session = null;
$pending = [];
$scheduled = [];
$visited = [];
$web_protected = false;

class WP_Error { public function get_error_message(): string { return 'Fixture failure'; } }
class OC_Logger {
	public static function warning( string $message ): void {}
	public static function error( string $message ): void {}
}
function __( string $message, string $domain = '' ): string { return $message; }
function is_wp_error( mixed $value ): bool { return $value instanceof WP_Error; }
function wp_normalize_path( string $path ): string { return str_replace( '\\', '/', $path ); }
function trailingslashit( string $path ): string { return rtrim( $path, '/' ) . '/'; }
function wp_upload_dir(): array { global $fixture; return [ 'basedir' => $fixture . '/uploads' ]; }
function wp_mkdir_p( string $path ): bool { return is_dir( $path ) || mkdir( $path, 0750, true ); }
function apply_filters( string $hook, mixed $value, mixed ...$args ): mixed { global $web_protected; return 'oc_private_storage_web_protected' === $hook ? $web_protected : $value; }
function get_option( string $key, mixed $default = false ): mixed { global $options; return $options[ $key ] ?? $default; }
function update_option( string $key, mixed $value, mixed ...$args ): bool { global $options; $options[ $key ] = $value; return true; }
function add_option( string $key, mixed $value, mixed ...$args ): bool { global $options; if ( isset( $options[ $key ] ) ) { return false; } $options[ $key ] = $value; return true; }
function get_transient( string $key ): mixed { global $transients; return $transients[ $key ] ?? false; }
function wp_salt( string $scheme ): string { return 'local-audit-secret'; }
function get_current_blog_id(): int { return 1; }
function home_url( string $path = '' ): string { return 'https://audit.example' . $path; }
function site_url( string $path = '' ): string { return home_url( $path ); }
function get_post_meta( int $id, string $key, bool $single = true ): mixed { return '_oc_artwork' === $key ? 1 : ''; }
function get_attached_file( int $id ): false { return false; }
function WC(): object { global $session; return (object) [ 'session' => $session ]; }
function absint( mixed $value ): int { return abs( (int) $value ); }
function wp_update_post( array $post, bool $error ): WP_Error { global $visited; $visited[] = $post['ID']; return new WP_Error(); }
function wp_generate_uuid4(): string { return bin2hex( random_bytes( 16 ) ); }
function wp_generate_password( int $length, bool $special = true, bool $extra = false ): string { return substr( bin2hex( random_bytes( $length ) ), 0, $length ); }
function wp_delete_file( string $path ): void { unlink( $path ); }
function as_has_scheduled_action( mixed ...$args ): bool { return true; } // Running action exists.
function as_get_scheduled_actions( array $query, string $format ): array {
	global $pending;
	check( 'pending' === $query['status'], 'Only pending actions may suppress scheduling.' );
	return $pending;
}
function as_schedule_single_action( int $timestamp, string $hook, array $args, string $group, bool $unique ): int {
	global $scheduled;
	$scheduled[] = compact( 'timestamp', 'args', 'unique' );
	return 1;
}
function check( bool $condition, string $message ): void {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
}
function invoke( string $class, string $method, mixed ...$args ): mixed {
	return ( new ReflectionMethod( $class, $method ) )->invoke( null, ...$args );
}
class Audit_DB {
	public string $posts = 'posts';
	public string $postmeta = 'postmeta';
	public string $prefix = 'wp_';
	public string $last_error = '';
	public array $vdp = [];
	public function prepare( string $sql, mixed ...$args ): array { return [ $sql, $args ]; }
	public function esc_like( string $value ): string { return addcslashes( $value, '_%\\' ); }
	public function get_col( array $query ): array {
		check( str_contains( $query[0], 'p.ID > %d' ), 'Artwork uses an ID cursor.' );
		return array_slice( array_values( array_filter( range( 1, 51 ), fn( $id ) => $id > $query[1][0] ) ), 0, $query[1][3] );
	}
	public function get_results( array $query ): array {
		check( str_contains( $query[0], 'id > %d' ), 'VDP uses an ID cursor.' );
		return array_slice( array_values( array_filter( $this->vdp, fn( $row ) => $row->id > $query[1][1] ) ), 0, 25 );
	}
	public function query( array $query ): int { return 1; }
}

foreach ( [ 'rest-api', 'upload-handler', 'svg-sanitiser', 'ai-image-filter', 'webhooks' ] as $class ) {
	require_once $plugin . '/includes/class-oc-' . $class . '.php';
}

if ( in_array( '--no-decoder', $argv, true ) ) {
	$png = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true );
	try {
		OC_Upload_Handler::validate_raster_bytes( $png, 'image/png' );
	} catch ( RuntimeException $e ) {
		check( str_contains( $e->getMessage(), 'requires PHP GD or ImageMagick' ), 'Missing decoder produces an actionable error.' );
		fwrite( STDOUT, "Missing-decoder smoke test passed.\n" );
		exit( 0 );
	}
	throw new RuntimeException( 'Expected decoding to fail closed without a decoder.' );
}

try {
	wp_mkdir_p( ABSPATH );
	wp_mkdir_p( $fixture . '/uploads/overcustomise/vdp' );
	$root = OC_Upload_Handler::private_storage_root();
	check( OC_PRIVATE_STORAGE_ROOT === $root, 'A resolved root outside the document root is accepted.' );
	check( is_file( $root . '/.htaccess' ) && is_file( $root . '/web.config' ), 'Private roots receive deny rules.' );
	check( null === invoke( OC_Upload_Handler::class, 'prepare_storage_root', ABSPATH . 'private' ), 'Public root is rejected.' );
	$before_contradiction = $options;
	$_SERVER['DOCUMENT_ROOT'] = $fixture;
	check( null === OC_Upload_Handler::private_storage_root(), 'A sibling of ABSPATH inside the document root is rejected.' );
	$options = $before_contradiction; // Independent subsequent storage scenarios, not recovery by a narrower document root.
	$_SERVER['DOCUMENT_ROOT'] = ABSPATH;
	symlink( ABSPATH, $fixture . '/alias' );
	check( null === invoke( OC_Upload_Handler::class, 'prepare_storage_root', $fixture . '/alias/private' ), 'Symlinked public ancestors are rejected before creation.' );
	check( ! is_dir( ABSPATH . 'private' ), 'Rejected paths are not created.' );
	unset( $_SERVER['DOCUMENT_ROOT'] );
	check( null === OC_Upload_Handler::private_storage_root(), 'Unknown document root fails closed.' );
	$_SERVER['DOCUMENT_ROOT'] = ABSPATH;
	check( null === invoke( OC_Upload_Handler::class, 'protected_uploads_storage_root' ), 'Deny files alone cannot enable public uploads fallback.' );
	$web_protected = true;
	$fallback = invoke( OC_Upload_Handler::class, 'protected_uploads_storage_root' );
	check( is_string( $fallback ), 'Explicit server protection permits the fallback.' );
	unlink( $fallback . '/.htaccess' );
	check( $fallback === invoke( OC_Upload_Handler::class, 'protected_uploads_storage_root' ) && is_file( $fallback . '/.htaccess' ), 'Fallback repairs missing deny rules despite a cached marker.' );
	$web_protected = false;

	$token = str_repeat( 'b', 64 );
	$secret = str_repeat( 'a', 64 );
	$key = 'oc_pubtok_' . hash( 'sha256', $token );
	$cookie = $secret . '.' . hash_hmac( 'sha256', $secret, wp_salt( 'auth' ) );
	$_COOKIE['oc_private_browser'] = $cookie;
	$_SERVER['REMOTE_ADDR'] = '203.0.113.10';
	$transients[ $key ] = [ 'version' => 2, 'binding_type' => 'browser', 'binding_hash' => hash( 'sha256', $secret ), 'created_at' => time(), 'expires_at' => time() + 3600 ];
	$transients[ 'oc_pubmap_' . hash( 'sha256', $secret ) ] = $token;
	check( OC_Rest_API::validate_public_token( $token ), 'Browser token validates.' );
	check( $token === OC_Rest_API::issue_public_token(), 'The same browser reuses its private token.' );
	$session = new class { public function get_customer_id(): string { return 'wc-session'; } };
	check( OC_Rest_API::validate_public_token( $token ), 'Starting WC session preserves browser token ownership.' );
	check( $token === OC_Rest_API::current_session_public_token(), 'Cart lookup retains browser token after WC initializes.' );
	unset( $_COOKIE['oc_private_browser'] );
	check( ! OC_Rest_API::validate_public_token( $token ), 'Another browser on the same IP cannot use the token.' );
	$transients[ $key ]['binding_type'] = 'session';
	$transients[ $key ]['binding_hash'] = hash_hmac( 'sha256', 'wc-session', wp_salt( 'auth' ) );
	check( OC_Rest_API::validate_public_token( $token ), 'Existing WC-session tokens remain valid.' );
	$transients[ $key ]['binding_type'] = 'ip';
	$transients[ $key ]['binding_hash'] = hash( 'sha256', $_SERVER['REMOTE_ADDR'] );
	check( ! OC_Rest_API::validate_public_token( $token ), 'Legacy shared IP tokens are revoked.' );

	foreach ( [ 'url(//evil.example/a', "url('//evil.example/a", 'url(#safe) url(//evil.example/a' ] as $css ) {
		check( null === invoke( OC_SVG_Sanitiser::class, 'clean_resource_urls', $css ), 'Unclosed CSS URL is rejected.' );
	}
	$svg = "<svg xmlns='http://www.w3.org/2000/svg'><text title='\"'/></svg>";
	$clean = OC_SVG_Sanitiser::sanitise( $svg );
	check( $clean === OC_SVG_Sanitiser::sanitise( $clean ), 'SVG serialization is stable for reservation and storage.' );
	check( strlen( $clean ) > strlen( $svg ), 'SVG fixture expands during serialization.' );
	file_put_contents( $fixture . '/artwork.svg', $svg );
	$inspection = invoke( OC_Upload_Handler::class, 'inspect_validated_upload', [ 'tmp_name' => $fixture . '/artwork.svg', 'name' => 'artwork.svg' ], null );
	check( strlen( $clean ) === $inspection['reservation_bytes'] && strlen( $svg ) === $inspection['source_bytes'], 'SVG reservation covers sanitized bytes, not original bytes.' );
	$png = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true );
	OC_Upload_Handler::validate_raster_bytes( $png, 'image/png' );
	$header = substr( $png, 0, 33 );
	check( is_array( getimagesizefromstring( $header ) ), 'Corrupt fixture has a valid raster header.' );
	$rejected = false;
	try { OC_Upload_Handler::validate_raster_bytes( $header, 'image/png' ); } catch ( RuntimeException $e ) { $rejected = true; }
	check( $rejected, 'Header-only raster is rejected by full decode.' );
	check( invoke( OC_AI_Image_Filter::class, 'decode_image_url', 'data:image/png;base64,' . base64_encode( $header ) ) instanceof WP_Error, 'AI corrupt raster is rejected.' );

	$options['oc_wh_job_audit'] = [ 'attempt' => 1 ];
	$schedule = new ReflectionMethod( OC_Webhooks::class, 'schedule_delivery' );
	check( $schedule->invoke( new OC_Webhooks(), 7, 'oc_wh_job_audit', time() + 60 ), 'Running action does not suppress retry.' );
	check( [ 7, 'oc_wh_job_audit', 1 ] === $scheduled[0]['args'] && false === $scheduled[0]['unique'], 'Future attempt has distinct args without running-action uniqueness.' );
	$pending = [ 1 ];
	$schedule->invoke( new OC_Webhooks(), 7, 'oc_wh_job_audit', time() + 60 );
	check( 1 === count( $scheduled ), 'Pending action suppresses duplicate scheduling.' );

	$wpdb = new Audit_DB();
	OC_Upload_Handler::ensure_private_storage();
	OC_Upload_Handler::ensure_private_storage();
	check( range( 1, 51 ) === $visited, 'Artwork migration reaches records beyond 50 failures.' );
	check( 0 === get_option( 'oc_private_artwork_migration_cursor' ), 'Artwork cursor wraps for retry.' );
	check( false === get_option( 'oc_private_artwork_storage_version' ), 'Failed migrations never mark completion.' );
	for ( $id = 1; $id <= 26; $id++ ) {
		$wpdb->vdp[] = (object) [ 'id' => $id, 'csv_file_path' => $fixture . '/uploads/overcustomise/vdp/' . $id . '.csv' ];
	}
	file_put_contents( $wpdb->vdp[25]->csv_file_path, "name\nAlice\n" );
	$vdp = OC_Upload_Handler::private_storage_path( 'vdp' );
	invoke( OC_Rest_API::class, 'migrate_legacy_vdp_files', $vdp );
	check( 25 === get_option( 'oc_vdp_migration_cursor' ), 'VDP cursor advances past 25 failures.' );
	invoke( OC_Rest_API::class, 'migrate_legacy_vdp_files', $vdp );
	check( ! is_file( $wpdb->vdp[25]->csv_file_path ) && 1 === count( glob( $vdp . '/*.csv' ) ), 'VDP migrates a valid later row despite earlier failures.' );
	check( 0 === get_option( 'oc_vdp_migration_cursor' ), 'VDP cursor wraps for retry.' );
	fwrite( STDOUT, "Audit security smoke tests passed.\n" );
} finally {
	if ( is_dir( $fixture ) ) {
		$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $fixture, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $files as $file ) {
			$file->isDir() && ! $file->isLink() ? rmdir( $file->getPathname() ) : unlink( $file->getPathname() );
		}
		rmdir( $fixture );
	}
}
