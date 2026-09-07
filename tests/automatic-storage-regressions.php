<?php
/** Real storage and download handler, isolated PHP processes; WP/DB boundaries are mocked. */
declare(strict_types=1);
$child = '--download' === ( $argv[1] ?? '' );
$fixture = $child ? $argv[2] : __DIR__ . '/.automatic-storage-' . bin2hex( random_bytes( 6 ) );
define( 'ABSPATH', $fixture . '/site/' );
define( 'DAY_IN_SECONDS', 86400 );
$options = [ 'oc_private_storage_token' => str_repeat( 'a', 32 ) ];
$configured = null;
$default = $fixture . '/.overcustomise-private-' . substr( hash( 'sha256', ABSPATH ), 0, 12 );
$uploads = $fixture . '/uploads';
$fallback = $uploads . '/.overcustomise-private-' . str_repeat( 'a', 32 );
$legacy = $uploads . '/overcustomise';
function wp_normalize_path( $path ) { return str_replace( '\\', '/', $path ); }
function wp_upload_dir() { return [ 'basedir' => $GLOBALS['uploads'] ]; }
function wp_mkdir_p( $path ) { return is_dir( $path ) || mkdir( $path, 0750, true ); }
function trailingslashit( $path ) { return rtrim( $path, '/' ) . '/'; }
function get_option( $key, $default = false ) { return $GLOBALS['options'][$key] ?? $default; }
function update_option( $key, $value, ...$args ) { $GLOBALS['options'][$key] = $value; return true; }
function add_option( $key, $value, ...$args ) { if ( isset( $GLOBALS['options'][$key] ) ) { return false; } return update_option( $key, $value ); }
function wp_generate_password( $length, ...$args ) { return substr( bin2hex( random_bytes( $length ) ), 0, $length ); }
function wp_salt( $scheme ) { return 'automatic-storage'; }
function get_current_blog_id() { return 1; }
function home_url( $path = '' ) { return 'https://fixture.example' . $path; }
function site_url( $path = '' ) { return home_url( $path ); }
function apply_filters( $hook, $value, ...$args ) {
	if ( 'oc_private_storage_root' === $hook ) { return $GLOBALS['configured'] ?? $value; }
	if ( in_array( $hook, [ 'oc_private_storage_web_protected', 'oc_private_storage_outside_web_root', 'oc_storage_automatic_http_verification' ], true ) ) {
		throw new LogicException( 'Runtime storage requested external approval or HTTP diagnostics' );
	}
	return $value;
}
function wp_safe_remote_get( ...$args ) { throw new LogicException( 'Runtime storage attempted HTTP' ); }
function __( $text, ...$args ) { return $text; }
function esc_html__( $text, ...$args ) { return $text; }
function current_user_can( $capability ) { return 'denied-capability' !== ( $GLOBALS['argv'][3] ?? '' ); }
function wp_verify_nonce( $nonce, $action ) { return 'valid' === $nonce && 'oc_download_1' === $action; }
function wp_die( $message, $status = 0, ...$args ) { fwrite( STDOUT, 'DENIED:' . $status ); exit; }
class OC_Logger { public static function warning( $message ): void {} public static function error( $message ): void {} }
class OC_DB { public static function get_print_file( $id ) { return (object) [ 'file_status' => 'files_ready', 'file_path' => $GLOBALS['download'] ]; } }
require dirname( __DIR__ ) . '/includes/class-oc-upload-handler.php';
require dirname( __DIR__ ) . '/includes/print/class-oc-print-base.php';
require dirname( __DIR__ ) . '/includes/class-oc-print-generator.php';
function check( bool $ok, string $message ): void { if ( ! $ok ) { throw new LogicException( $message ); } }
function invoke( string $method, ...$args ) { return ( new ReflectionMethod( OC_Upload_Handler::class, $method ) )->invoke( null, ...$args ); }

if ( $child ) {
	// A new CLI process has neither HTTP evidence nor DOCUMENT_ROOT.
	unset( $_SERVER['DOCUMENT_ROOT'] );
	$configured = $fixture . '/current';
	$kind = $argv[3];
	$download = match ( $kind ) {
		'default' => $default . '/print-files/retained.pdf',
		'fallback' => $fallback . '/print-files/retained.pdf',
		'symlink' => $legacy . '/print-files/alias.pdf',
		default => $legacy . '/print-files/retained.pdf',
	};
	$_GET = [ 'oc_download_file' => 1, '_wpnonce' => 'denied-nonce' === $kind ? 'invalid' : 'valid' ];
	( new OC_Print_Generator() )->handle_admin_download();
	throw new LogicException( 'Download handler did not terminate' );
}

try {
	wp_mkdir_p( ABSPATH ); wp_mkdir_p( $uploads );
	unset( $_SERVER['DOCUMENT_ROOT'] );
	check( $default === OC_Upload_Handler::private_storage_root( true ), 'Fresh CLI default requires no positive evidence' );
	$control_target = $fixture . '/deny-target';
	$original = file_get_contents( $default . '/.htaccess' );
	file_put_contents( $control_target, $original );
	foreach ( [ '.htaccess', 'web.config', 'index.php' ] as $name ) {
		$path = $default . '/' . $name;
		$bytes = file_get_contents( $path );
		file_put_contents( $control_target, $bytes );
		unlink( $path ); symlink( $control_target, $path );
		check( $fallback === OC_Upload_Handler::private_storage_root(), 'Default deny-file failure must automatically fall back despite its cached marker' );
		$configured = $default . '/';
		check( null === OC_Upload_Handler::private_storage_root(), 'Explicit filtered root protection failure must not fall back' );
		$configured = null;
		check( $bytes === file_get_contents( $control_target ), 'Deny-file symlink target was modified' );
		unlink( $path );
		check( $default === OC_Upload_Handler::private_storage_root( true ), 'Default deny files repair without configuration' );
	}
	foreach ( [ '', 'relative/root', ABSPATH . 'public', $uploads . '/custom', $fixture . '/../escape' ] as $bad ) {
		$configured = $bad;
		check( null === OC_Upload_Handler::private_storage_root( true ), 'Invalid custom root silently fell back: ' . $bad );
	}
	$configured = null;
	rename( $fallback, $fallback . '-saved' );
	symlink( $fallback . '-saved', $fallback );
	check( null === invoke( 'protected_uploads_storage_root' ), 'Token directory alias inside uploads was accepted' );
	unlink( $fallback ); rename( $fallback . '-saved', $fallback );
	foreach ( [ '.htaccess', 'web.config', 'index.php' ] as $name ) {
		$path = $fallback . '/' . $name;
		unlink( $path ); symlink( $control_target, $path );
		check( null === invoke( 'protected_uploads_storage_root' ), 'Fallback accepted symlinked deny file' );
		unlink( $path );
		check( $fallback === invoke( 'protected_uploads_storage_root' ), 'Fallback did not repair missing deny file' );
	}
	$artwork = OC_Upload_Handler::private_storage_path( 'artwork', true );
	wp_mkdir_p( $legacy . '/artwork' );
	foreach ( [ $artwork, $legacy . '/artwork' ] as $directory ) {
		$file = $directory . '/retained.svg';
		file_put_contents( $file, '<svg xmlns="http://www.w3.org/2000/svg"/>' );
		check( OC_Upload_Handler::is_allowed_artwork_path( $file ), 'Canonical artwork should be admitted by storage policy' );
		symlink( $file, $directory . '/alias.svg' );
		symlink( $directory, $directory . '/nested' );
		foreach ( [ $directory . '/alias.svg', $directory . '/nested/retained.svg', $directory . '/../artwork/retained.svg' ] as $bad ) {
			check( ! OC_Upload_Handler::is_allowed_artwork_path( $bad ), 'Artwork symlink/traversal was accepted' );
		}
	}
	rename( $legacy . '/artwork', $legacy . '/artwork-saved' );
	symlink( $legacy . '/artwork-saved', $legacy . '/artwork' );
	check( ! OC_Upload_Handler::is_allowed_artwork_path( $legacy . '/artwork/retained.svg' ), 'Redirected legacy artwork root accepted' );
	check( ! invoke( 'path_is_in_legacy_artwork_root', $legacy . '/artwork/retained.svg', false ), 'Migration source guard accepted redirected legacy root' );
	unlink( $legacy . '/artwork' ); rename( $legacy . '/artwork-saved', $legacy . '/artwork' );
	foreach ( [ $default, $fallback, $legacy ] as $root ) {
		wp_mkdir_p( $root . '/print-files' );
		file_put_contents( $root . '/print-files/retained.pdf', "%PDF-1.4\nretained fixture\n" );
	}
	symlink( $legacy . '/print-files/retained.pdf', $legacy . '/print-files/alias.pdf' );
	foreach ( [ 'default', 'fallback', 'legacy', 'symlink', 'denied-capability', 'denied-nonce' ] as $kind ) {
		$process = proc_open( [ PHP_BINARY, '-d', 'open_basedir=' . dirname( __DIR__ ), __FILE__, '--download', $fixture, $kind ], [ 0 => [ 'pipe', 'r' ], 1 => [ 'pipe', 'w' ], 2 => [ 'pipe', 'w' ] ], $pipes, dirname( __DIR__ ) );
		check( is_resource( $process ), 'Could not start download endpoint process' );
		fclose( $pipes[0] );
		$output = stream_get_contents( $pipes[1] ); $errors = stream_get_contents( $pipes[2] );
		fclose( $pipes[1] ); fclose( $pipes[2] );
		check( 0 === proc_close( $process ) && '' === $errors, 'Download process failed: ' . $errors );
		$expected = match ( $kind ) { 'symlink' => 'DENIED:400', 'denied-capability', 'denied-nonce' => 'DENIED:403', default => "%PDF-1.4\nretained fixture\n" };
		check( $expected === $output, 'Actual download handler returned incorrect bytes/auth result for ' . $kind );
	}
	rename( $legacy . '/print-files', $legacy . '/print-files-saved' );
	symlink( $legacy . '/print-files-saved', $legacy . '/print-files' );
	check( null === OC_Print_Base::resolve_output_storage_path( $legacy . '/print-files/retained.pdf', true ), 'Redirected legacy print root was accepted' );
	fwrite( STDOUT, "Automatic storage and six isolated download endpoint checks passed (mocked WP/DB, no HTTP server).\n" );
} finally {
	if ( is_dir( $fixture ) ) {
		$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $fixture, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $iterator as $file ) { $file->isDir() && ! $file->isLink() ? rmdir( $file->getPathname() ) : unlink( $file->getPathname() ); }
		rmdir( $fixture );
	}
}
