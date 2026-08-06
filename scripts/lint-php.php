<?php
/**
 * Run PHP's syntax checker over plugin-owned PHP files.
 *
 * @package OverCustomise
 */

declare(strict_types=1);

$root       = dirname( __DIR__ );
$directories = [ 'includes', 'scripts', 'templates', 'tests' ];
$files       = [ $root . '/overcustomise.php' ];

foreach ( $directories as $directory ) {
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $root . '/' . $directory, FilesystemIterator::SKIP_DOTS )
	);
	foreach ( $iterator as $file ) {
		if ( $file->isFile() && 'php' === strtolower( $file->getExtension() ) ) {
			$files[] = $file->getPathname();
		}
	}
}

sort( $files );
$failed = false;
foreach ( $files as $file ) {
	$command = escapeshellarg( PHP_BINARY ) . ' -l ' . escapeshellarg( $file );
	exec( $command, $output, $exit_code );
	if ( 0 !== $exit_code ) {
		$failed = true;
		echo implode( PHP_EOL, $output ) . PHP_EOL;
	}
	$output = [];
}

if ( $failed ) {
	exit( 1 );
}

printf( "PHP syntax OK (%d files).\n", count( $files ) );
