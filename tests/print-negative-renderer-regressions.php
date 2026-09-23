<?php
/** Standalone negative print renderer pixel regressions. */
declare(strict_types=1);
define( 'ABSPATH', dirname( __DIR__ ) . '/' );

function absint( mixed $value ): int {
	return abs( (int) $value );
}
function sanitize_key( string $value ): string {
	return strtolower( $value );
}
function wp_generate_uuid4(): string {
	return bin2hex( random_bytes( 16 ) );
}

require ABSPATH . 'includes/print/trait-oc-print-base-artwork-effects.php';

class Negative_Renderer_Fixture {
	use OC_Print_Base_Artwork_Effects;

	public static array $paths = [];

	protected static function open_raster_resource( string $path, int $dimension, int $pixels ) {
		return imagecreatefrompng( $path );
	}
	protected static function temp_path_with_extension( string $name, string $extension ): string {
		$path          = sys_get_temp_dir() . '/' . $name;
		self::$paths[] = $path;
		return $path;
	}
	public static function render( string $path, int $id, float $value = 0.0 ): ?string {
		return self::build_filtered_image(
			$path,
			[ 'settings' => [ 'image_filter_ids' => [ 7 ] ] ],
			[
				'imageFilterId'    => $id,
				'imageFilterKey'   => 'negative',
				'imageFilterValue' => $value,
			]
		);
	}
}

$count  = 0;
$check  = static function ( bool $ok, string $message ) use ( &$count ): void {
	++$count;
	if ( ! $ok ) {
		throw new RuntimeException( $message ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI assertion diagnostics are plain text, not HTML.
	}
};
$source = tempnam( sys_get_temp_dir(), 'oc-negative-source-' );
$pixels = [ [ 0, 0, 0, 0 ], [ 255, 255, 255, 0 ], [ 12, 100, 240, 64 ], [ 40, 60, 80, 127 ] ];
try {
	$image = imagecreatetruecolor( count( $pixels ), 1 );
	imagealphablending( $image, false );
	imagesavealpha( $image, true );
	foreach ( $pixels as $x => $pixel ) {
		imagesetpixel( $image, $x, 0, imagecolorallocatealpha( $image, ...$pixel ) );
	}
	imagepng( $image, $source );
	imagedestroy( $image );
	$original = file_get_contents( $source ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Read local PNG fixture bytes without WordPress.
	foreach ( [ 0.0, 0.5, 1.0, -1.0 ] as $value ) {
		$output = Negative_Renderer_Fixture::render( $source, 7, $value );
		$check( is_string( $output ) && $source !== $output, 'Negative produces a separate PNG' );
		$result = imagecreatefrompng( $output );
		$check( count( $pixels ) === imagesx( $result ) && 1 === imagesy( $result ), 'Dimensions retained' );
		foreach ( $pixels as $x => [ $r, $g, $b, $a ] ) {
			$actual = imagecolorsforindex( $result, imagecolorat( $result, $x, 0 ) );
			$check(
				[
					'red'   => 255 - $r,
					'green' => 255 - $g,
					'blue'  => 255 - $b,
					'alpha' => $a,
				] === $actual,
				'RGB inverted and alpha retained'
			);
		}
		imagedestroy( $result );
	}
	$check( null === Negative_Renderer_Fixture::render( $source, 0 ), 'Original bypasses filtering' );
	$check( null === Negative_Renderer_Fixture::render( $source, 8 ), 'Unallowed filter bypasses filtering' );
	$check( file_get_contents( $source ) === $original, 'Source is unchanged' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Compare local PNG fixture bytes without WordPress.
} finally {
	unlink( $source ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Clean up the standalone fixture's temporary PNG.
	foreach ( Negative_Renderer_Fixture::$paths as $fixture_path ) {
		if ( is_file( $fixture_path ) ) {
			unlink( $fixture_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Clean up temporary renderer output without WordPress.
		}
	}
}
echo "Passed {$count} negative print renderer checks.\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain-text CLI test summary.
