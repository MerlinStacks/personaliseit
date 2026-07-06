<?php
/**
 * Ensure TCPDF 7 generated font metadata exists after Composer installs.
 *
 * @package OverCustomise
 */

$root      = dirname( __DIR__ );
$font_dir  = $root . '/vendor/tecnickcom/tc-lib-pdf-font';
$sentinel  = $font_dir . '/target/fonts/core/helvetica.json';
$composer  = getenv( 'COMPOSER_BINARY' ) ?: 'composer';

if ( file_exists( $sentinel ) ) {
	fwrite( STDOUT, "TCPDF font assets already initialized.\n" );
	return;
}

if ( ! is_dir( $font_dir ) ) {
	fwrite( STDERR, "tc-lib-pdf-font is not installed. Run composer install first.\n" );
	exit( 1 );
}

$commands = [
	sprintf( '%s install --no-interaction', escapeshellcmd( $composer ) ),
	'make fonts',
];

foreach ( $commands as $command ) {
	passthru( sprintf( 'cd %s && %s', escapeshellarg( $font_dir ), $command ), $exit_code );
	if ( 0 !== $exit_code ) {
		exit( $exit_code );
	}
}

if ( ! file_exists( $sentinel ) ) {
	fwrite( STDERR, "TCPDF font asset generation completed, but helvetica.json is still missing.\n" );
	exit( 1 );
}

fwrite( STDOUT, "TCPDF font assets initialized.\n" );
