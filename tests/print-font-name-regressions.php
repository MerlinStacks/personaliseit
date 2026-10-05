<?php
// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput -- Standalone binary fixtures and CLI diagnostics; WordPress and HTML output are absent.
/** Real importer regressions for incomplete PostScript name metadata. */
declare(strict_types=1);

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
require ABSPATH . 'vendor/autoload.php';
require ABSPATH . 'includes/print/class-oc-print-base.php';

function trailingslashit( string $path ): string {
	return rtrim( $path, '/' ) . '/'; }
function wp_upload_dir(): array {
	return [ 'basedir' => $GLOBALS['font_test_root'] ]; }
function wp_mkdir_p( string $path ): bool {
	return is_dir( $path ) || mkdir( $path, 0700, true ); }

$checks = 0;
$check  = static function ( bool $ok, string $message ) use ( &$checks ): void {
	++$checks;
	if ( ! $ok ) {
		throw new RuntimeException( $message ); }
};
$invoke = static fn( string $method, mixed ...$args ): mixed => ( new ReflectionMethod( OC_Print_Base::class, $method ) )->invoke( null, ...$args );
$root   = sys_get_temp_dir() . '/oc-font-name-' . bin2hex( random_bytes( 8 ) );
mkdir( $root, 0700 );
$GLOBALS['font_test_root'] = $root;

try {
	// Reuse the licensed, bundled font; only fixture name records are modified.
	$original    = gzuncompress( file_get_contents( ABSPATH . 'vendor/tecnickcom/tc-lib-pdf-font/target/fonts/dejavu/dejavusans.z' ) );
	$ushort      = static fn( string $data, int $offset ): int => unpack( 'n', substr( $data, $offset, 2 ) )[1];
	$name_offset = null;
	$table_count = $ushort( $original, 4 );
	for ( $i = 0; $i < $table_count; ++$i ) {
		$entry = 12 + 16 * $i;
		if ( 'name' === substr( $original, $entry, 4 ) ) {
			$name_offset = unpack( 'N', substr( $original, $entry + 8, 4 ) )[1];
			break;
		}
	}
	$check( is_int( $name_offset ), 'Bundled fixture has no name table' );
	$records      = [];
	$record_count = $ushort( $original, $name_offset + 2 );
	for ( $i = 0; $i < $record_count; ++$i ) {
		$record = $name_offset + 6 + 12 * $i;
		if ( 6 === $ushort( $original, $record + 6 ) ) {
			$records[] = $record; }
	}
	$check( count( $records ) >= 2, 'Fixture needs legacy and Unicode PostScript names' );
	$first_empty = substr_replace( $original, "\0\0", $records[0] + 8, 2 );
	$all_empty   = $original;
	$missing     = $original;
	$punctuation = $original;
	$storage     = $name_offset + $ushort( $original, $name_offset + 4 );
	foreach ( $records as $record ) {
		$all_empty   = substr_replace( $all_empty, "\0\0", $record + 8, 2 );
		$missing     = substr_replace( $missing, pack( 'n', 10 ), $record + 6, 2 );
		$length      = $ushort( $original, $record + 8 );
		$punctuation = substr_replace( $punctuation, str_repeat( '!', $length ), $storage + $ushort( $original, $record + 10 ), $length );
	}
	$baseline = null;
	foreach ( [
		'normal'      => $original,
		'firstempty'  => $first_empty,
		'allempty'    => $all_empty,
		'missing'     => $missing,
		'punctuation' => $punctuation,
	] as $case => $bytes ) {
		$source = $root . '/' . $case . '.ttf';
		file_put_contents( $source, $bytes );
		$name       = $invoke( 'register_tcpdf_font', $source );
		$definition = $invoke( 'tcpdf_font_definition_path', $source, $name );
		$data       = json_decode( file_get_contents( $definition ), true, 512, JSON_THROW_ON_ERROR );
		$baseline ??= $data;
		$check( 1 === preg_match( '/^[A-Za-z0-9_-]+$/D', $data['name'] ), "$case: unusable PDF font name" );
		$check( $data['cw'] === $baseline['cw'] && $data['desc'] === $baseline['desc'], "$case: glyph metrics changed" );
		$check( file_get_contents( $source ) === $bytes, "$case: retained source changed" );
		$check( gzuncompress( file_get_contents( dirname( $definition ) . '/' . $data['file'] ) ) === $bytes, "$case: embedded font changed" );
		$check( $name === $invoke( 'register_tcpdf_font', $source ), "$case: verified cache could not be reused" );
		if ( in_array( $case, [ 'normal', 'firstempty' ], true ) ) {
			$check( $baseline['name'] === $data['name'], "$case: valid original name lost" );
		} else {
			$check( str_starts_with( $data['name'], 'OCFont' ), "$case: missing deterministic resource name" );
		}
	}

	// Corrupt offsets still fail; the fallback must not accept broken tables.
	$broken = substr_replace( $original, "\xff\xff", $records[0] + 10, 2 );
	$source = $root . '/broken.ttf';
	file_put_contents( $source, $broken );
	$rejected = false;
	try {
		$invoke( 'register_tcpdf_font', $source ); } catch ( RuntimeException $error ) {
		$rejected = str_contains( $error->getMessage(), 'Invalid font name string.' );
		}
		$check( $rejected, 'Out-of-bounds name string was accepted' );
		$check( [] === glob( $root . '/overcustomise/tcpdf-fonts/.ocmodern*' ), 'Failed import leaked staging files' );
		print "PASS: {$checks} real font-name/cache checks\n";
} finally {
	$entries = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $entries as $entry ) {
		if ( $entry->isDir() ) {
			rmdir( $entry->getPathname() ); } else {
			unlink( $entry->getPathname() ); }
	}
	rmdir( $root );
}
