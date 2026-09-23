<?php
/** GD pixel adapter for the cross-renderer hue regression test. */
declare(strict_types=1);
define( 'ABSPATH', dirname( __DIR__ ) . '/' );
require ABSPATH . 'includes/print/trait-oc-print-base-artwork-effects.php';

class Hue_Print_Pixel_Fixture {
	use OC_Print_Base_Artwork_Effects {
		adjust_raster_hue as public rotate;
	}
}

$cases   = json_decode( stream_get_contents( STDIN ), true, 512, JSON_THROW_ON_ERROR );
$results = [];
foreach ( $cases as $case ) {
	$image = imagecreatetruecolor( count( $case['pixels'] ), 1 );
	imagealphablending( $image, false );
	imagesavealpha( $image, true );
	try {
		foreach ( $case['pixels'] as $x => $pixel ) {
			imagesetpixel( $image, $x, 0, imagecolorallocatealpha( $image, ...$pixel ) );
		}
		if ( ! Hue_Print_Pixel_Fixture::rotate( $image, (float) $case['amount'] ) ) {
			throw new RuntimeException( 'Hue rotation failed' );
		}
		$pixels = [];
		foreach ( $case['pixels'] as $x => $pixel ) {
			$pixels[] = array_values( imagecolorsforindex( $image, imagecolorat( $image, $x, 0 ) ) );
		}
		$results[] = $pixels;
	} finally {
		imagedestroy( $image );
	}
}
// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Standalone CLI adapter emits JSON for the JS pixel test without loading WordPress.
echo json_encode( $results, JSON_THROW_ON_ERROR );
