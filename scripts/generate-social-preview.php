<?php
/**
 * Generate the PNG used for GitHub's repository social preview.
 *
 * Run with: php scripts/generate-social-preview.php
 *
 * @package OverCustomise
 */

declare(strict_types=1);

if ( ! extension_loaded( 'gd' ) ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- This is a standalone CLI script.
	fwrite( STDERR, "The GD extension is required.\n" );
	exit( 1 );
}

$root      = dirname( __DIR__ );
$font_root = $root . '/vendor/tecnickcom/tc-lib-pdf-font/target/fonts/dejavu/';
$output    = $root . '/docs/assets/social-preview.png';
$temp      = [];

/** Expand a bundled TCPDF font for GD's FreeType functions. */
function oc_preview_font( string $compressed_path, array &$temp ): string {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads a local bundled font in a standalone CLI script.
	$data = file_get_contents( $compressed_path );
	if ( false === $data ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception text is emitted only by this CLI script.
		throw new RuntimeException( 'Unable to read bundled font: ' . $compressed_path );
	}

	$decoded = zlib_decode( $data );
	if ( false === $decoded ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception text is emitted only by this CLI script.
		throw new RuntimeException( 'Unable to decode bundled font: ' . $compressed_path );
	}

	$path = tempnam( sys_get_temp_dir(), 'oc-font-' );
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- WordPress is not loaded by this standalone CLI script.
	if ( false === $path || false === file_put_contents( $path, $decoded ) ) {
		throw new RuntimeException( 'Unable to prepare a temporary font.' );
	}

	$temp[] = $path;
	return $path;
}

/** Allocate an RGB colour from a hex string. */
function oc_preview_colour( GdImage $image, string $hex ): int {
	$hex = ltrim( $hex, '#' );
	return imagecolorallocate(
		$image,
		hexdec( substr( $hex, 0, 2 ) ),
		hexdec( substr( $hex, 2, 2 ) ),
		hexdec( substr( $hex, 4, 2 ) )
	);
}

/** Draw a filled rounded rectangle. */
function oc_preview_round_rect( GdImage $image, int $x1, int $y1, int $x2, int $y2, int $radius, int $colour ): void {
	imagefilledrectangle( $image, $x1 + $radius, $y1, $x2 - $radius, $y2, $colour );
	imagefilledrectangle( $image, $x1, $y1 + $radius, $x2, $y2 - $radius, $colour );
	imagefilledellipse( $image, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, $colour );
	imagefilledellipse( $image, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, $colour );
	imagefilledellipse( $image, $x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, $colour );
	imagefilledellipse( $image, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, $colour );
}

/** Draw anti-aliased text. */
function oc_preview_text( GdImage $image, string $font, float $size, int $x, int $baseline, int $colour, string $text ): void {
	$result = imagettftext( $image, $size, 0, $x, $baseline, $colour, $font, $text );
	if ( false === $result ) {
		throw new RuntimeException( 'Unable to render preview text.' );
	}
}

try {
	$regular = oc_preview_font( $font_root . 'dejavusans.z', $temp );
	$bold    = oc_preview_font( $font_root . 'dejavusansb.z', $temp );
	$image   = imagecreatetruecolor( 1280, 640 );
	if ( false === $image ) {
		throw new RuntimeException( 'Unable to create preview image.' );
	}

	imageantialias( $image, true );

	// Deep indigo gradient background.
	for ( $y = 0; $y < 640; $y++ ) {
		$ratio = $y / 639;
		$r     = (int) round( 17 + ( 49 - 17 ) * $ratio );
		$g     = (int) round( 24 + ( 46 - 24 ) * $ratio );
		$b     = (int) round( 39 + ( 129 - 39 ) * $ratio );
		imageline( $image, 0, $y, 1279, $y, imagecolorallocate( $image, $r, $g, $b ) );
	}

	$white      = oc_preview_colour( $image, '#ffffff' );
	$indigo     = oc_preview_colour( $image, '#4f46e5' );
	$indigo_100 = oc_preview_colour( $image, '#c7d2fe' );
	$indigo_50  = oc_preview_colour( $image, '#eef2ff' );
	$slate      = oc_preview_colour( $image, '#111827' );
	$muted      = oc_preview_colour( $image, '#64748b' );
	$line       = oc_preview_colour( $image, '#d1d5db' );
	$green      = oc_preview_colour( $image, '#34d399' );
	$orange     = oc_preview_colour( $image, '#f59e0b' );

	// Brand mark.
	oc_preview_round_rect( $image, 72, 64, 150, 142, 20, $indigo );
	imagearc( $image, 111, 103, 35, 35, 0, 360, $white );
	imagearc( $image, 111, 103, 36, 36, 0, 360, $white );
	imagesetthickness( $image, 4 );
	imageline( $image, 91, 84, 91, 96, $white );
	imageline( $image, 91, 84, 103, 84, $white );
	imageline( $image, 131, 110, 131, 122, $white );
	imageline( $image, 119, 122, 131, 122, $white );
	imagesetthickness( $image, 1 );

	oc_preview_text( $image, $bold, 50, 72, 226, $white, 'OverCustomise' );
	oc_preview_text( $image, $regular, 23, 74, 276, $indigo_100, 'Visual customisation for WooCommerce' );
	oc_preview_text( $image, $regular, 15, 74, 316, $indigo_100, 'From live product previews to production artwork.' );

	// Feature pills.
	$pills = [
		[ 'LIVE PREVIEW', 74, 372, 132 ],
		[ 'PRINT QUEUE', 220, 372, 128 ],
		[ 'OPEN SOURCE', 362, 372, 132 ],
	];
	foreach ( $pills as [ $label, $x, $y, $width ] ) {
		oc_preview_round_rect( $image, $x, $y, $x + $width, $y + 38, 19, $indigo );
		oc_preview_text( $image, $bold, 9, $x + 17, $y + 24, $white, $label );
	}

	// Product preview card.
	oc_preview_round_rect( $image, 710, 72, 1208, 570, 26, $white );
	oc_preview_round_rect( $image, 736, 98, 1182, 154, 12, $indigo_50 );
	imagefilledellipse( $image, 765, 126, 16, 16, $green );
	oc_preview_text( $image, $bold, 12, 786, 132, $slate, 'Live product preview' );

	oc_preview_round_rect( $image, 736, 176, 1018, 538, 16, $indigo_50 );
	// Stylised shirt.
	imagefilledpolygon( $image, [ 810, 248, 877, 216, 944, 248, 992, 272, 964, 340, 932, 325, 932, 466, 822, 466, 822, 325, 790, 340, 762, 272 ], $white );
	imagesetthickness( $image, 3 );
	imagerectangle( $image, 829, 292, 925, 380, $indigo );
	imagesetthickness( $image, 1 );
	oc_preview_text( $image, $bold, 14, 840, 333, $slate, 'YOUR' );
	oc_preview_text( $image, $bold, 14, 834, 356, $slate, 'DESIGN' );

	oc_preview_text( $image, $bold, 11, 1043, 198, $slate, 'Personalised text' );
	oc_preview_round_rect( $image, 1043, 211, 1160, 246, 6, $indigo_50 );
	oc_preview_text( $image, $regular, 9, 1054, 234, $muted, 'YOUR DESIGN' );
	oc_preview_text( $image, $bold, 11, 1043, 282, $slate, 'Text colour' );
	imagefilledellipse( $image, 1056, 310, 22, 22, $slate );
	imagefilledellipse( $image, 1090, 310, 22, 22, $indigo );
	imagefilledellipse( $image, 1124, 310, 22, 22, $orange );
	oc_preview_round_rect( $image, 1043, 350, 1174, 394, 8, $indigo_50 );
	oc_preview_text( $image, $bold, 7, 1052, 367, $muted, 'IMAGE PLACEMENT' );
	oc_preview_text( $image, $regular, 9, 1052, 385, $indigo, 'Fit image' );
	imagefilledellipse( $image, 1055, 444, 18, 18, $green );
	oc_preview_text( $image, $regular, 8, 1072, 449, $muted, 'Preview updates live' );
	imagesetthickness( $image, 7 );
	imageline( $image, 1044, 485, 1158, 485, $line );
	imageline( $image, 1044, 510, 1134, 510, $line );
	imagesetthickness( $image, 1 );

	if ( ! imagepng( $image, $output, 9 ) ) {
		throw new RuntimeException( 'Unable to write social preview: ' . $output );
	}
	imagedestroy( $image );
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- This is a standalone CLI script.
	fwrite( STDOUT, "Generated {$output}\n" );
} catch ( Throwable $error ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- This is a standalone CLI script.
	fwrite( STDERR, $error->getMessage() . "\n" );
	exit( 1 );
} finally {
	foreach ( $temp as $temporary_path ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- WordPress is not loaded by this standalone CLI script.
		unlink( $temporary_path );
	}
}
