<?php
/** Dependency-free storage checks; all fixtures stay inside this plugin. */
define( 'ABSPATH', __DIR__ . '/print-storage-site/' );
define( 'DAY_IN_SECONDS', 86400 );
function wp_normalize_path( $path ) { return str_replace( '\\', '/', $path ); }
function wp_upload_dir() { return [ 'basedir' => $GLOBALS['fixture'] . '/uploads' ]; }
function wp_mkdir_p( $path ) { return is_dir( $path ) || mkdir( $path, 0750, true ); }
function wp_salt( $scheme ) { return 'test-salt'; }
function get_current_blog_id() { return 1; }
function home_url( $path = '' ) { return 'https://storage.example' . $path; }
function site_url( $path = '' ) { return home_url( $path ); }
function __( $text, $domain = '' ) { return $text; }
function esc_html__( $text, $domain = '' ) { return $text; }
function get_post_meta( ...$args ) { return 1; }
function get_option( $name, $default = false ) { return $GLOBALS['options'][ $name ] ?? $default; }
function update_option( $name, $value, $autoload = false ) { $GLOBALS['options'][ $name ] = $value; return true; }
function apply_filters( $name, $value, ...$args ) {
	if ( 'oc_print_historical_storage_roots' === $name ) { return $GLOBALS['history']; }
	if ( 'oc_private_storage_web_protected' === $name ) { return $GLOBALS['attestations'][ $args[0] ] ?? false; }
	return $value;
}
class OC_Upload_Handler {
	public static ?string $root = null;
	public static bool $artwork = false;
	public static function private_storage_path( string $subdirectory = '', bool $force = false ): ?string { return self::$root; }
	public static function is_allowed_artwork_path( string $path ): bool { return self::$artwork; }
}
class OC_Logger { public static function warning( $message ): void {} }
require dirname( __DIR__ ) . '/includes/print/class-oc-print-base.php';
require dirname( __DIR__ ) . '/includes/class-oc-print-generator.php';
require dirname( __DIR__ ) . '/includes/class-oc-file-cleanup.php';
function check( bool $condition, string $message ): void {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
}
$fixture = __DIR__ . '/print-storage-fixture-' . bin2hex( random_bytes( 6 ) );
$history = [];
$attestations = [];
mkdir( $fixture );
try {
	foreach ( [ '/private/print-files', '/old/print-files', '/uploads/overcustomise/print-files', '/private/print-files-sibling' ] as $dir ) {
		mkdir( $fixture . $dir, 0750, true );
		file_put_contents( $fixture . $dir . '/output.pdf', 'test' );
	}
	OC_Upload_Handler::$root = $fixture . '/private/print-files';
	$current = OC_Upload_Handler::$root . '/output.pdf';
	$legacy = $fixture . '/uploads/overcustomise/print-files/output.pdf';
	$old = $fixture . '/old/print-files/output.pdf';
	check( $current === OC_Print_Base::resolve_output_storage_path( $current ), 'Current output rejected' );
	$resolve = new ReflectionMethod( OC_Print_Generator::class, 'resolve_print_storage_path' );
	$cleanup = new ReflectionMethod( OC_File_Cleanup::class, 'cleanup_record_path' );
	$backup = new ReflectionMethod( OC_Print_Generator::class, 'generate_with_backup' );
	foreach ( [ false, 1, 'true' ] as $attestation ) {
		$attestations[ dirname( $legacy ) ] = $attestation;
		$history = [ dirname( $legacy ) ];
		foreach ( [ false, true ] as $forced ) {
			check( null === $resolve->invoke( null, $legacy, $forced ), 'Unattested legacy download accepted' );
		}
		$handled = [];
		$args = [ (object) [ 'file_path' => $legacy ], 'file_path', null, &$handled, [] ];
		check( false === $cleanup->invokeArgs( null, $args ) && is_file( $legacy ) && [] === $handled, 'Unprotected legacy file was expired' );
		$args[0]->file_path = dirname( $legacy ) . '/missing.pdf';
		check( false === $cleanup->invokeArgs( null, $args ), 'Unprotected missing legacy path was expired' );
		$generated = false;
		try {
			$backup->invoke( null, $legacy, static function () use ( &$generated ): array { $generated = true; return []; } );
			throw new LogicException( 'Unprotected regeneration accepted' );
		} catch ( RuntimeException $expected ) {}
		check( ! $generated && [] === glob( $legacy . '.oc-backup-*' ), 'Unprotected regeneration wrote a backup' );
	}
	$history = [];
	$attestations[ dirname( $legacy ) ] = true;
	check( $legacy === OC_Print_Base::resolve_output_storage_path( $legacy, true ), 'Attested legacy output rejected' );
	// Revocation must override intact deny files and cached protection markers.
	$attestations = [];
	check( null === $resolve->invoke( null, $legacy, true ), 'Deny files bypassed revoked attestation' );
	check( null === $resolve->invoke( null, $legacy, false ), 'Cached marker bypassed revoked attestation' );
	$attestations[ dirname( $legacy ) ] = true;
	check( null === OC_Print_Base::resolve_output_storage_path( $old ), 'Unknown history trusted' );
	$history = [ dirname( $old ), $fixture ];
	check( $old === OC_Print_Base::resolve_output_storage_path( $old ), 'Attested history rejected' );
	check( ! in_array( $fixture, OC_Print_Base::output_storage_roots(), true ), 'Broad historical root trusted' );
	foreach ( [ 'output.pdf', $fixture . '/private/print-files-sibling/output.pdf', dirname( $current ) . '/../print-files/output.pdf', $current . "\0" ] as $bad ) {
		check( null === OC_Print_Base::resolve_output_storage_path( $bad ), 'Unsafe path accepted' );
	}
	symlink( $old, dirname( $current ) . '/alias.pdf' );
	check( null === OC_Print_Base::resolve_output_storage_path( dirname( $current ) . '/alias.pdf' ), 'Symlink accepted' );
	$ensure = new ReflectionMethod( OC_Print_Base::class, 'ensure_output_dir' );
	check( str_starts_with( $ensure->invoke( null, 42 ), OC_Upload_Handler::$root . '/' ), 'New output not private' );
	OC_Upload_Handler::$root = null;
	try { $ensure->invoke( null, 43 ); throw new LogicException( 'Generation did not fail closed' ); } catch ( RuntimeException $expected ) {}
	check( $old === OC_Print_Base::resolve_output_storage_path( $old ), 'History lost when current unavailable' );
	$artwork = new ReflectionMethod( OC_Print_Base::class, 'attachment_storage_path_is_allowed' );
	check( false === $artwork->invoke( null, 1, $legacy ), 'Artwork guard bypassed' );
	OC_Upload_Handler::$artwork = true;
	check( true === $artwork->invoke( null, 1, $legacy ), 'Artwork guard not delegated' );
	$cleanup = new ReflectionMethod( OC_File_Cleanup::class, 'cleanup_record_path' );
	$handled = [];
	$args = [ (object) [ 'file_path' => $old ], 'file_path', null, &$handled, [ $old => true ] ];
	check( $cleanup->invokeArgs( null, $args ) && is_file( $old ), 'Shared history deleted' );
	$args[4] = [];
	check( $cleanup->invokeArgs( null, $args ) && ! file_exists( $old ), 'Historical cleanup failed' );
	$resolve = new ReflectionMethod( OC_Print_Generator::class, 'resolve_print_storage_path' );
	check( $legacy === $resolve->invoke( null, $legacy, true ), 'Download resolver lost legacy output' );
	fwrite( STDOUT, "Print storage regression checks passed.\n" );
} finally {
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $fixture, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $iterator as $file ) {
		if ( $file->isLink() || ! $file->isDir() ) { unlink( $file->getPathname() ); } else { rmdir( $file->getPathname() ); }
	}
	rmdir( $fixture );
}
