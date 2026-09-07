<?php
/** Legacy font publication regressions; all fixtures stay beneath this plugin. */
declare(strict_types=1);

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
function trailingslashit( string $path ): string { return rtrim( $path, '/' ) . '/'; }
function wp_upload_dir(): array { return [ 'basedir' => $GLOBALS['font_test_root'] ]; }
function wp_mkdir_p( string $path ): bool { return is_dir( $path ) || mkdir( $path, 0700, true ); }
class OC_Logger {
	public static function warning( string $message ): void {}
}
class TCPDF_FONTS {
	public static int $imports = 0;
	public static mixed $during_import = null;
	public static bool $fail = false;
	public static function addTTFfont( string $source, string $type, string $encoding, int $flags, string $dir ): string {
		++self::$imports;
		$name = pathinfo( $source, PATHINFO_FILENAME );
		$definition = "<?php\n\$type='TrueTypeUnicode';\n\$name='TestFont';\n\$desc=array('Ascent'=>800,'Descent'=>-200);\n\$cw=array(65=>500);\n\$file='{$name}.z';\n\$ctg='{$name}.ctg.z';\n";
		file_put_contents( $dir . $name . '.php', $definition );
		if ( is_callable( self::$during_import ) ) { ( self::$during_import )( $dir, $name ); }
		if ( self::$fail ) { throw new RuntimeException( 'Simulated interrupted import.' ); }
		file_put_contents( $dir . $name . '.z', gzcompress( file_get_contents( $source ) ) );
		file_put_contents( $dir . $name . '.ctg.z', gzcompress( str_repeat( "\0", 131072 ) ) );
		return $name;
	}
}
require ABSPATH . 'includes/print/class-oc-print-base.php';

$checks = 0;
$check = static function ( bool $ok, string $message ) use ( &$checks ): void {
	++$checks;
	if ( ! $ok ) { throw new RuntimeException( $message ); }
};
$invoke = static fn ( string $method, mixed ...$args ): mixed => ( new ReflectionMethod( OC_Print_Base::class, $method ) )->invoke( null, ...$args );
$reject = static function ( callable $call, string $message ) use ( $check ): void {
	try { $call(); } catch ( RuntimeException $e ) { $check( true, $message ); return; }
	$check( false, $message );
};
$root = tempnam( __DIR__, 'oc-font-cache-test-' );
unlink( $root );
mkdir( $root, 0700 );
$GLOBALS['font_test_root'] = $root;
$reader = null;
try {
	$source = $root . '/test.ttf';
	file_put_contents( $source, "\0\1\0\0test-source" );
	$cache = $root . '/overcustomise/tcpdf-fonts/';
	wp_mkdir_p( $cache );
	$identity = $invoke( 'tc_lib_pdf_font_name', $source );
	$name = 'oclegacy' . substr( $identity, 2 );
	$published = $cache . $name . '/';
	// Leave the pre-fix shared artifacts in place: upgrading must not touch readers.
	foreach ( [ '.php', '.z', '.ctg.z' ] as $suffix ) {
		file_put_contents( $cache . $identity . $suffix, 'old-reader-' . $suffix );
	}
	TCPDF_FONTS::$during_import = static function ( string $stage, string $actual_name ) use ( $check, $published, $name ): void {
		$check( $actual_name === $name && $stage !== $published, 'Import did not use isolated staging' );
		$check( ! file_exists( rtrim( $published, '/' ) ), 'Partial font set became visible before publication' );
		$check( is_file( $stage . $name . '.php' ) && ! is_file( $stage . $name . '.z' ), 'Atomic-publication test did not observe a partial import' );
	};
	$check( $name === $invoke( 'register_tcpdf_font', $source ), 'Initial legacy registration failed' );
	$check( 1 === TCPDF_FONTS::$imports, 'Unexpected initial import count' );
	$check( $published . $name . '.php' === $invoke( 'tcpdf_font_definition_path', $source, $name ), 'Definition lookup does not resolve the published bundle' );
	$check( ! file_exists( $published . $name . '.ttf' ), 'Staged source was unnecessarily published' );
	$check( [] === glob( $cache . '.*' . $name . '-*' ), 'Successful publication leaked a staging directory' );
	$before = [];
	foreach ( [ '.php', '.z', '.ctg.z', '.manifest.json' ] as $suffix ) {
		$file = $published . $name . $suffix;
		$before[ $suffix ] = [ fileinode( $file ), hash_file( 'sha256', $file ) ];
	}
	$reader = fopen( $published . $name . '.z', 'rb' );
	TCPDF_FONTS::$during_import = static function (): void { throw new RuntimeException( 'A valid published font was reimported while a reader was active.' ); };
	$check( $name === $invoke( 'register_tcpdf_font', $source ), 'Valid cache reuse failed' );
	$check( 1 === TCPDF_FONTS::$imports, 'Valid cache reuse rebuilt shared artifacts' );
	// A directory at the lock path makes fopen(..., 'c') impossible even as root.
	// Verified immutable readers must not need a lock or any cache writes.
	unlink( $cache . $name . '.lock' );
	mkdir( $cache . $name . '.lock', 0700 );
	$check( $name === $invoke( 'register_tcpdf_font', $source ), 'Verified upgraded cache required a writable lock' );
	rmdir( $cache . $name . '.lock' );
	$check( gzuncompress( stream_get_contents( $reader ) ) === file_get_contents( $source ), 'Active reader lost its font binary' );
	fclose( $reader );
	$reader = null;
	foreach ( $before as $suffix => $state ) {
		$file = $published . $name . $suffix;
		clearstatcache( true, $file );
		$check( $state === [ fileinode( $file ), hash_file( 'sha256', $file ) ], 'Cache reuse changed the file a late-opening reader needs: ' . $suffix );
	}
	foreach ( [ '.php', '.z', '.ctg.z' ] as $suffix ) {
		$check( 'old-reader-' . $suffix === file_get_contents( $cache . $identity . $suffix ), 'Upgrade rewrote a pre-fix reader artifact' );
	}

	// Failure during a new import must leave both existing readers and publication intact.
	$new_source = $root . '/new.ttf';
	file_put_contents( $new_source, "\0\1\0\0different-source" );
	$new_name = 'oclegacy' . substr( $invoke( 'tc_lib_pdf_font_name', $new_source ), 2 );
	TCPDF_FONTS::$during_import = static function () use ( $check, $published, $name, $before ): void {
		foreach ( $before as $suffix => $state ) {
			$check( hash_file( 'sha256', $published . $name . $suffix ) === $state[1], 'An overlapping import disturbed a published reader' );
		}
	};
	TCPDF_FONTS::$fail = true;
	$reject( static fn () => $invoke( 'register_legacy_tcpdf_font', $new_source, $cache ), 'Interrupted import did not fail' );
	$check( ! file_exists( $cache . $new_name ), 'Interrupted import published incomplete files' );
	$check( [] === glob( $cache . '.*' . $new_name . '-*' ), 'Interrupted import leaked staging artifacts' );
	TCPDF_FONTS::$fail = false;
	TCPDF_FONTS::$during_import = null;
	$check( $new_name === $invoke( 'register_legacy_tcpdf_font', $new_source, $cache ), 'Unpublished failed import could not be retried safely' );
	$reject( static fn () => $invoke( 'register_tcpdf_font', $root . '/missing.ttf' ), 'Missing custom font silently selected a core font' );
	$old_source = $root . '/old-only.ttf';
	file_put_contents( $old_source, 'retained source with pre-upgrade cache only' );
	foreach ( [ '.php', '.z', '.ctg.z' ] as $suffix ) {
		file_put_contents( $cache . 'oldonly' . $suffix, 'unverified HEAD cache' );
	}
	TCPDF_FONTS::$fail = true;
	$reject( static fn () => $invoke( 'register_tcpdf_font', $old_source ), 'Unmigratable old cache silently selected a core font' );
	TCPDF_FONTS::$fail = false;
	foreach ( [ '.php', '.z', '.ctg.z' ] as $suffix ) {
		$check( 'unverified HEAD cache' === file_get_contents( $cache . 'oldonly' . $suffix ), 'Failed upgrade changed old reader artifacts' );
	}
	// Verified historical v7 artifacts are now read-only: even an unusable lock
	// must not prevent warm reuse, and registration must leave every byte intact.
	$v7_name = $invoke( 'tc_lib_pdf_font_name', $old_source );
	$v7_artifacts = [
		'.json' => json_encode( [ 'type' => 'TrueTypeUnicode', 'file' => $v7_name . '.z', 'ctg' => $v7_name . '.ctg.z', 'cw' => [ 65 => 500 ] ] ),
		'.z' => gzcompress( file_get_contents( $old_source ) ),
		'.ctg.z' => gzcompress( str_repeat( "\0", 131072 ) ),
	];
	$v7_manifest = [];
	foreach ( $v7_artifacts as $suffix => $bytes ) {
		file_put_contents( $cache . $v7_name . $suffix, $bytes );
		$v7_manifest[$suffix] = hash( 'sha256', $bytes );
	}
	file_put_contents( $cache . $v7_name . '.manifest.json', json_encode( $v7_manifest ) );
	$check( true === $invoke( 'tcpdf_font_cache_complete', $cache, $v7_name ), 'Read-only v7 fixture is not a complete upgraded cache' );
	mkdir( $cache . $v7_name . '.lock', 0700 );
	set_error_handler( static fn(): bool => true );
	try {
		$check( $v7_name === $invoke( 'register_tc_lib_pdf_font', $old_source, $cache ), 'Complete warm v7 cache required a writable lock' );
	} finally {
		restore_error_handler();
		rmdir( $cache . $v7_name . '.lock' );
	}
	foreach ( $v7_manifest as $suffix => $hash ) {
		$check( $hash === hash_file( 'sha256', $cache . $v7_name . $suffix ), 'Read-only v7 reuse changed retained artifacts' );
	}

	// Even a forged checksum manifest must not make arbitrary PHP executable.
	$definition_path = $published . $name . '.php';
	$manifest_path = $published . $name . '.manifest.json';
	$definition = file_get_contents( $definition_path );
	$manifest = json_decode( file_get_contents( $manifest_path ), true );
	foreach ( [
		$definition . "\$GLOBALS['font_cache_executed']=true;\n",
		$definition . "\$up='strlen'('execute');\n",
		$definition . "include 'unexpected.php';\n",
		str_replace( $name . '.ctg.z', '../other.ctg.z', $definition ),
		$definition . "\$type='OtherType';\n",
	] as $unsafe ) {
		file_put_contents( $definition_path, $unsafe );
		$changed_manifest = $manifest;
		$changed_manifest['.php'] = hash( 'sha256', $unsafe );
		file_put_contents( $manifest_path, json_encode( $changed_manifest ) );
		$check( false === $invoke( 'legacy_tcpdf_font_cache_complete', $published, $name, hash_file( 'sha256', $source ) ), 'Unsafe definition was accepted' );
	}
	$check( ! isset( $GLOBALS['font_cache_executed'] ), 'Cache PHP was executed during validation' );
	file_put_contents( $definition_path, $definition );
	file_put_contents( $manifest_path, json_encode( $manifest ) );
	$check( true === $invoke( 'legacy_tcpdf_font_cache_complete', $published, $name, hash_file( 'sha256', $source ) ), 'Valid literal PHP definition rejected' );
	file_put_contents( $published . $name . '.ctg.z', 'corrupt' );
	$imports_before = TCPDF_FONTS::$imports;
	$reject( static fn () => $invoke( 'register_legacy_tcpdf_font', $source, $cache ), 'Invalid published cache was not rejected' );
	$check( $imports_before === TCPDF_FONTS::$imports && 'corrupt' === file_get_contents( $published . $name . '.ctg.z' ), 'Invalid published cache was destructively repaired' );
	$check( $definition === file_get_contents( $definition_path ), 'Failure removed a published definition still needed by a reader' );
	if ( function_exists( 'pcntl_fork' ) && function_exists( 'pcntl_waitpid' ) ) {
		$parallel_source = $root . '/parallel.ttf';
		file_put_contents( $parallel_source, "\0\1\0\0parallel-source" );
		TCPDF_FONTS::$during_import = static function () use ( $root ): void {
			file_put_contents( $root . '/imports.log', "import\n", FILE_APPEND | LOCK_EX );
			usleep( 100000 );
		};
		$children = [];
		for ( $index = 0; $index < 2; ++$index ) {
			$pid = pcntl_fork();
			if ( 0 === $pid ) {
				try {
					$result = $invoke( 'register_legacy_tcpdf_font', $parallel_source, $cache );
					file_put_contents( $root . '/worker-' . $index, $result );
					exit( 0 );
				} catch ( Throwable $e ) {
					file_put_contents( $root . '/worker-' . $index, $e->getMessage() );
					exit( 1 );
				}
			}
			$check( $pid > 0, 'Could not fork cache registration worker' );
			$children[] = $pid;
		}
		foreach ( $children as $pid ) {
			pcntl_waitpid( $pid, $status );
			$check( pcntl_wifexited( $status ) && 0 === pcntl_wexitstatus( $status ), 'Concurrent cache registration failed' );
		}
		$check( "import\n" === file_get_contents( $root . '/imports.log' ), 'Concurrent builders imported or published more than once' );
		$check( file_get_contents( $root . '/worker-0' ) === file_get_contents( $root . '/worker-1' ), 'Concurrent readers received different font identities' );
	} else {
		print "SKIP: concurrent-process test (pcntl unavailable)\n";
	}
	print "PASS: {$checks} legacy font-cache checks\n";
} finally {
	if ( is_resource( $reader ) ) { fclose( $reader ); }
	$entries = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $entries as $entry ) {
		if ( $entry->isDir() && ! $entry->isLink() ) { rmdir( $entry->getPathname() ); } else { unlink( $entry->getPathname() ); }
	}
	rmdir( $root );
}
