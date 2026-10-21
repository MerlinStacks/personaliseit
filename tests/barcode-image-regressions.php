<?php
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Isolated CLI assertions, not HTML output.
/** Raster guard regressions: run with php and php -n to cover missing extensions. */
declare(strict_types=1);

// Load only the code under test so missing optional extensions do not prevent boot.
$root = dirname( __DIR__ ) . '/vendor/tecnickcom/tc-lib-barcode/src/';
require $root . 'Exception.php';
require $root . 'Model.php';
require $root . 'Type/Convert.php';
require $root . 'Type.php';

spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = 'Com\\Tecnick\\Color\\';
		if ( str_starts_with( $class_name, $prefix ) ) {
			$file = dirname( __DIR__ ) . '/vendor/tecnickcom/tc-lib-color/src/'
			. str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';
			if ( is_file( $file ) ) {
				require $file;
			}
		}
	}
);

use Com\Tecnick\Barcode\Exception as BarcodeException;

class OC_Barcode_Image_Probe extends \Com\Tecnick\Barcode\Type {
	public function __construct( int $width, int $height, array $padding = [ 0, 0, 0, 0 ] ) {
		// Avoid encoding and colour dependencies; do not allocate any image here.
		$this->ncols = 1;
		$this->nrows = 1;
		$this->setSize( $width, $height, $padding );
	}

	public function imageSize(): array {
		return $this->getImageSize();
	}
}

function oc_barcode_expect_exception( callable $callback, string $message ): void {
	try {
		$callback();
	} catch ( BarcodeException $error ) {
		if ( ! str_contains( $error->getMessage(), $message ) ) {
			throw new RuntimeException( 'Unexpected library exception: ' . $error->getMessage() );
		}
		return;
	}
	throw new RuntimeException( 'Expected a barcode library exception: ' . $message );
}

foreach ( [ [ 1, 1 ], [ 4000, 4000 ], [ 30000, 1 ], [ 1, 30000 ] ] as [$width, $height] ) {
	$probe = new OC_Barcode_Image_Probe( $width, $height );
	if ( $probe->imageSize() !== [ $width, $height ] ) {
		throw new RuntimeException( 'Valid raster dimensions changed.' );
	}
}
foreach ( [ [ 4001, 4000 ], [ 4000, 4001 ], [ 30000, 30000 ] ] as [$width, $height] ) {
	$probe = new OC_Barcode_Image_Probe( $width, $height );
	oc_barcode_expect_exception( fn () => $probe->imageSize(), '16000000 pixels' );
}
foreach ( [ [ 30001, 1 ], [ 1, 30001 ] ] as [$width, $height] ) {
	$probe = new OC_Barcode_Image_Probe( $width, $height );
	oc_barcode_expect_exception( fn () => $probe->imageSize(), '30000 px per side' );
}
$padded = new OC_Barcode_Image_Probe( 3998, 3998, [ 1, 1, 1, 1 ] );
if ( $padded->imageSize() !== [ 4000, 4000 ] ) {
	throw new RuntimeException( 'Padding must be included in image dimensions.' );
}
$too_large = new OC_Barcode_Image_Probe( 3999, 3999, [ 1, 1, 1, 1 ] );
oc_barcode_expect_exception( fn () => $too_large->imageSize(), '16000000 pixels' );

// The pixel limit applies only when rasterizing, not while producing SVG.
$vector = new OC_Barcode_Image_Probe( 30000, 30000 );
$vector->setColor( 'black' );
if ( ! str_contains( $vector->getInlineSvgCode(), 'viewBox="0 0 30000.000000 30000.000000"' ) ) {
	throw new RuntimeException( 'Large vector output should remain available.' );
}

$small = new OC_Barcode_Image_Probe( 10, 10 );
$small->setColor( 'black' );
foreach ( [
	'gd'      => 'getGd',
	'imagick' => 'getPngDataImagick',
] as $extension => $method ) {
	if ( ! extension_loaded( $extension ) ) {
		oc_barcode_expect_exception( fn () => $small->$method(), 'extension is required' );
		echo "Missing $extension produces a library exception.\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI diagnostic.
		continue;
	}
	// Reject oversized renders before allocating GD or Imagick images.
	oc_barcode_expect_exception( fn () => $vector->$method(), '16000000 pixels' );
	$png = 'gd' === $extension ? $small->getPngData( false ) : $small->getPngDataImagick();
	if ( ! str_starts_with( $png, "\x89PNG\r\n\x1a\n" ) ) {
		throw new RuntimeException( 'Small ' . $extension . ' PNG output changed.' );
	}
	echo "Small $extension PNG renders successfully.\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI diagnostic.
}
if ( ! extension_loaded( 'gd' ) && ! extension_loaded( 'imagick' ) ) {
	oc_barcode_expect_exception( fn () => $small->getPngData(), 'GD extension is required' );
}
echo "Barcode raster regressions passed.\n";
