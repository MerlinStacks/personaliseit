<?php
/** Modern importer boundary fixtures; all files and child-process access stay inside this plugin. */
declare(strict_types=1);

namespace Com\Tecnick\Pdf\Font {
	class Import {
		public static int $imports = 0;
		public static mixed $during_import = null;
		public static bool $fail = false;
		private string $name;
		public function __construct( string $source, string $dir, mixed ...$args ) {
			++self::$imports;
			$this->name = pathinfo( $source, PATHINFO_FILENAME );
			$name = $this->name;
			file_put_contents( $dir . $name . '.json', json_encode( [ 'type' => 'TrueTypeUnicode', 'file' => $name . '.z', 'ctg' => $name . '.ctg.z', 'cw' => [ 65 => 500 ] ] ) );
			if ( is_callable( self::$during_import ) ) { ( self::$during_import )( $dir, $name ); }
			if ( self::$fail ) { throw new \RuntimeException( 'Simulated interrupted modern import.' ); }
			file_put_contents( $dir . $name . '.z', gzcompress( file_get_contents( $source ) ) );
			file_put_contents( $dir . $name . '.ctg.z', gzcompress( str_repeat( "\0", 131072 ) ) );
		}
		public function getFontName(): string { return $this->name; }
	}
}

namespace {
	use Com\Tecnick\Pdf\Font\Import;
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
	function trailingslashit( string $path ): string { return rtrim( $path, '/' ) . '/'; }
	function wp_upload_dir(): array { return [ 'basedir' => $GLOBALS['font_test_root'] ]; }
	function wp_mkdir_p( string $path ): bool { return is_dir( $path ) || mkdir( $path, 0700, true ); }
	require ABSPATH . 'includes/print/class-oc-print-base.php';
	$checks = 0;
	$check = static function ( bool $ok, string $message ) use ( &$checks ): void {
		++$checks;
		if ( ! $ok ) { throw new RuntimeException( $message ); }
	};
	$invoke = static fn( string $method, mixed ...$args ): mixed => ( new ReflectionMethod( OC_Print_Base::class, $method ) )->invoke( null, ...$args );
	$reject = static function ( callable $call, string $message ) use ( $check ): void {
		try { $call(); } catch ( RuntimeException $error ) { $check( true, $message ); return; }
		$check( false, $message );
	};
	$root = tempnam( __DIR__, 'oc-modern-font-' );
	unlink( $root );
	mkdir( $root, 0700 );
	$GLOBALS['font_test_root'] = $root;
	$cache = $root . '/overcustomise/tcpdf-fonts/';
	wp_mkdir_p( $cache );
	$reader = null;
	try {
		$source = $root . '/font.ttf';
		file_put_contents( $source, "\0\1\0\0original-source" );
		$identity = $invoke( 'tc_lib_pdf_font_name', $source );
		$name = 'ocmodern' . substr( $identity, 2 );
		$published = $cache . $name . '/';
		Import::$during_import = static function ( string $stage, string $import_name ) use ( $check, $published, $name ): void {
			$check( $name === $import_name && $stage !== $published, 'Import did not use a separate immutable identity/staging directory' );
			$check( ! file_exists( rtrim( $published, '/' ) ), 'Partial bundle became visible before publication' );
			$check( is_file( $stage . $name . '.json' ) && ! file_exists( $stage . $name . '.z' ), 'Fixture did not exercise partial publication' );
		};
		$check( $name === $invoke( 'register_tcpdf_font', $source ), 'Cold modern registration failed' );
		$check( $published . $name . '.json' === $invoke( 'tcpdf_font_definition_path', $source, $name ), 'Definition lookup did not select the immutable bundle' );
		$check( ! file_exists( $published . $name . '.ttf' ) && [] === glob( $cache . '.' . $name . '-*' ), 'Successful import leaked staged source/artifacts' );
		$before = [];
		foreach ( [ '.json', '.z', '.ctg.z', '.manifest.json' ] as $suffix ) {
			$file = $published . $name . $suffix;
			$before[$suffix] = [ fileinode( $file ), hash_file( 'sha256', $file ) ];
			chmod( $file, 0444 );
		}
		$reader = fopen( $published . $name . '.z', 'rb' );
		// chmod tests real permissions; the directory at the lock path also makes
		// fopen(..., 'c') impossible when the fixture runs with root privileges.
		unlink( $cache . $name . '.lock' );
		mkdir( $cache . $name . '.lock', 0700 );
		chmod( $published, 0555 );
		chmod( $cache, 0555 );
		Import::$during_import = static function (): void { throw new RuntimeException( 'A warm cache was reimported.' ); };
		set_error_handler( static function ( int $severity, string $message ): never { throw new RuntimeException( $message ); } );
		try {
			$check( $name === $invoke( 'register_tcpdf_font', $source ), 'Complete read-only modern cache required a write lock' );
			$check( 1 === Import::$imports, 'Warm reuse invoked the importer' );
		} finally {
			restore_error_handler();
			chmod( $cache, 0700 );
			chmod( $published, 0700 );
			rmdir( $cache . $name . '.lock' );
		}
		$check_reader = static function () use ( $check, $before, $published, $name ): void {
			foreach ( $before as $suffix => $state ) {
				$file = $published . $name . $suffix;
				clearstatcache( true, $file );
				$check( $state === [ fileinode( $file ), hash_file( 'sha256', $file ) ], 'Publication changed a reader artifact: ' . $suffix );
			}
		};
		$check_reader();

		// Replacing the source creates a different bundle, never rewrites an old
		// reader's definition or binaries, even if the new import fails midway.
		file_put_contents( $source, "\0\1\0\0replacement-source" );
		$next_name = 'ocmodern' . substr( $invoke( 'tc_lib_pdf_font_name', $source ), 2 );
		Import::$during_import = $check_reader;
		Import::$fail = true;
		$reject( static fn() => $invoke( 'register_tcpdf_font', $source ), 'Interrupted rebuild silently substituted a font' );
		$check( ! file_exists( $cache . $next_name ) && [] === glob( $cache . '.' . $next_name . '-*' ), 'Failed import published or leaked partial artifacts' );
		Import::$fail = false;
		$check( $next_name === $invoke( 'register_tcpdf_font', $source ), 'Unpublished failed import could not be retried' );
		$check_reader();
		$check( gzuncompress( stream_get_contents( $reader ) ) === "\0\1\0\0original-source", 'Active reader lost its old font source' );
		fclose( $reader );
		$reader = null;

		// A corrupt published entry is never replaced, even with a writable cache.
		$next_dir = $cache . $next_name . '/';
		file_put_contents( $next_dir . $next_name . '.json', 'corrupt' );
		$imports = Import::$imports;
		$binary_hash = hash_file( 'sha256', $next_dir . $next_name . '.ctg.z' );
		$reject( static fn() => $invoke( 'register_tcpdf_font', $source ), 'Corrupt published cache silently fell back/rebuilt' );
		$check( $imports === Import::$imports && 'corrupt' === file_get_contents( $next_dir . $next_name . '.json' ) && $binary_hash === hash_file( 'sha256', $next_dir . $next_name . '.ctg.z' ), 'Corrupt-cache rejection mutated published artifacts' );
		$check_reader();

		// Historical complete flat caches also remain usable without any writes.
		$flat_source = $root . '/flat.ttf';
		file_put_contents( $flat_source, 'flat retained source' );
		$flat = $invoke( 'tc_lib_pdf_font_name', $flat_source );
		$artifacts = [ '.json' => json_encode( [ 'type' => 'TrueTypeUnicode', 'file' => $flat . '.z', 'ctg' => $flat . '.ctg.z', 'cw' => [ 65 => 500 ] ] ), '.z' => gzcompress( 'flat retained source' ), '.ctg.z' => gzcompress( str_repeat( "\0", 131072 ) ) ];
		$manifest = [];
		foreach ( $artifacts as $suffix => $bytes ) {
			file_put_contents( $cache . $flat . $suffix, $bytes );
			$manifest[$suffix] = hash( 'sha256', $bytes );
			chmod( $cache . $flat . $suffix, 0444 );
		}
		file_put_contents( $cache . $flat . '.manifest.json', json_encode( $manifest ) );
		mkdir( $cache . $flat . '.lock', 0700 );
		chmod( $cache, 0555 );
		try { $check( $flat === $invoke( 'register_tcpdf_font', $flat_source ), 'Read-only flat cache was not reused' ); }
		finally { chmod( $cache, 0700 ); }
		$check( $cache . $flat . '.json' === $invoke( 'tcpdf_font_definition_path', $flat_source, $flat ), 'Flat definition path changed' );
		foreach ( $manifest as $suffix => $hash ) { $check( $hash === hash_file( 'sha256', $cache . $flat . $suffix ), 'Warm flat reuse changed artifacts' ); }
		chmod( $cache . $flat . '.json', 0600 );
		file_put_contents( $cache . $flat . '.json', 'corrupt flat' );
		$reject( static fn() => $invoke( 'register_tcpdf_font', $flat_source ), 'Corrupt flat cache silently fell back/rebuilt' );
		$check( $imports === Import::$imports && 'corrupt flat' === file_get_contents( $cache . $flat . '.json' ), 'Flat cache rejection invoked importer or changed evidence' );

		if ( function_exists( 'pcntl_fork' ) && function_exists( 'pcntl_waitpid' ) ) {
			$reader = fopen( $published . $name . '.z', 'rb' );
			$parallel_source = $root . '/parallel.ttf';
			file_put_contents( $parallel_source, 'parallel modern source' );
			Import::$during_import = static function () use ( $root, $check_reader ): void {
				$check_reader();
				file_put_contents( $root . '/imports.log', "import\n", FILE_APPEND | LOCK_EX );
				usleep( 100000 );
			};
			$children = [];
			for ( $index = 0; $index < 2; ++$index ) {
				$pid = pcntl_fork();
				if ( 0 === $pid ) {
					try {
						$result = $invoke( 'register_tcpdf_font', $parallel_source );
						file_put_contents( $root . '/worker-' . $index, $result );
						exit( 0 );
					} catch ( Throwable $error ) { print $error->getMessage() . "\n"; exit( 1 ); }
				}
				$check( $pid > 0, 'Could not fork modern cache builder' );
				$children[] = $pid;
			}
			foreach ( $children as $pid ) {
				pcntl_waitpid( $pid, $status );
				$check( pcntl_wifexited( $status ) && 0 === pcntl_wexitstatus( $status ), 'Concurrent modern publication failed' );
			}
			$check( "import\n" === file_get_contents( $root . '/imports.log' ), 'Concurrent builders imported more than once' );
			$check( file_get_contents( $root . '/worker-0' ) === file_get_contents( $root . '/worker-1' ), 'Concurrent builders returned different bundles' );
			$check_reader();
			$check( gzuncompress( stream_get_contents( $reader ) ) === "\0\1\0\0original-source", 'Concurrent builders disturbed an open font reader' );
			$check( strlen( gzuncompress( file_get_contents( $published . $name . '.ctg.z' ) ) ) === 131072, 'A late-opening reader lost its original glyph map' );
			fclose( $reader );
			$reader = null;
		} else { print "SKIP: concurrent-process test (pcntl unavailable)\n"; }
		print "PASS: {$checks} modern font-cache checks\n";
	} finally {
		if ( is_resource( $reader ) ) { fclose( $reader ); }
		chmod( $cache, 0700 );
		$entries = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $entries as $entry ) {
			if ( $entry->isDir() && ! $entry->isLink() ) { chmod( $entry->getPathname(), 0700 ); rmdir( $entry->getPathname() ); }
			else { unlink( $entry->getPathname() ); }
		}
		rmdir( $root );
	}
}
