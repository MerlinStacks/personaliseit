<?php
// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited, WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Isolated CLI fixtures and assertions, not WordPress request globals or HTML output.
/** Aztec bitstream regressions and decoder fixtures; --fixtures emits JSON only. */
declare(strict_types=1);

use Com\Tecnick\Barcode\Barcode;
use Com\Tecnick\Barcode\Type\Square\Aztec\Data;

spl_autoload_register(
	static function ( string $class_name ): void {
		foreach ( [
			'Barcode' => 'tc-lib-barcode',
			'Color'   => 'tc-lib-color',
		] as $namespace => $package ) {
			$prefix = 'Com\\Tecnick\\' . $namespace . '\\';
			if ( str_starts_with( $class_name, $prefix ) ) {
				$file = dirname( __DIR__ ) . '/vendor/tecnickcom/' . $package . '/src/'
				. str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';
				if ( is_file( $file ) ) {
					require $file;
				}
				return;
			}
		}
	}
);

class OC_Aztec_Bitstream_Probe extends \Com\Tecnick\Barcode\Type\Square\Aztec\Bitstream {
	public function flag( int $eci ): string {
		$this->bitstream = [];
		$this->totbits   = 0;
		$this->encmode   = Data::MODE_UPPER;
		$this->addFLG( $eci );
		return implode( '', $this->bitstream );
	}

	public function binary( string $payload ): string {
		$this->bitstream = [];
		$this->totbits   = 0;
		$this->encmode   = Data::MODE_UPPER;
		$this->binaryEncode( array_values( unpack( 'C*', $payload ) ), strlen( $payload ) );
		return implode( '', $this->bitstream );
	}
}

if ( ( $argv[1] ?? '' ) === '--fixtures' ) {
	$fixtures = [];
	$factory  = new Barcode();
	foreach ( [
		[ 'AZTEC', 'Plain Aztec 123', 'Plain Aztec 123' ],
		[ 'AZTEC,33,A,A,26', "UTF-8 caf\xc3\xa9", "UTF-8 caf\xc3\xa9" ],
		[ 'AZTEC,33,A,A,0', '0101234567890128', '0101234567890128' ],
		[ 'AZTEC,1,B,F', str_repeat( "\xa5", 2079 ), str_repeat( "\xa5", 2079 ) ],
		// ZXing normalizes UPC-E to its equivalent EAN-13 representation.
		[ 'UPCE', '425261', '0042100005264' ],
		[ 'PDF417', 'PDF417 row regression', 'PDF417 row regression' ],
		[ 'AZTEC,1,A,A', str_repeat( 'A', 110 ), str_repeat( 'A', 110 ) ],
		[ 'DATAMATRIX,S,GS1', '0101234567890128', '0101234567890128' ],
		[ 'DATAMATRIX,S,GS1', "\x1d0101234567890128", '0101234567890128' ],
		[ 'DATAMATRIX,S,GS1,C40', 'ABCDEFGHIJKL', 'ABCDEFGHIJKL' ],
		[ 'DATAMATRIX,R', str_repeat( 'A', 25 ), str_repeat( 'A', 25 ) ],
		[ 'DATAMATRIX,S,N,BASE256', str_repeat( "\x80", 1400 ), str_repeat( "\x80", 1400 ) ],
		[ 'DATAMATRIX,S,N,BASE256', str_repeat( "\x80", 1555 ), str_repeat( "\x80", 1555 ) ],
		[ 'DATAMATRIX,S,N,BASE256', str_repeat( "\x80", 1556 ), str_repeat( "\x80", 1556 ) ],
		[ 'PDF417,1', str_repeat( 'A', 1800 ), str_repeat( 'A', 1800 ) ],
		[ 'PDF417', str_repeat( '1234567890123', 4 ), str_repeat( '1234567890123', 4 ), 2 ],
		[ 'PDF417', str_repeat( "\x80", 12 ), str_repeat( "\x80", 12 ), 2 ],
		[ 'QRCODE,L,8B,0,1,-1,0,-1', 'QR mask regression 123', 'QR mask regression 123' ],
		[ 'DATAMATRIX', str_repeat( 'A', 22 ) . '*', str_repeat( 'A', 22 ) . '*' ],
		[ 'QRCODE', str_repeat( 'A', 25 ), str_repeat( 'A', 25 ) ],
		[ 'QRCODE', '12345678901234567890lowercase-ABC12345678901234567890', '12345678901234567890lowercase-ABC12345678901234567890' ],
		[ 'QRCODE,L,8B,10', str_repeat( 'multi-block ', 40 ), str_repeat( 'multi-block ', 40 ) ],
		[ 'QRCODE,L,8B,0,1,9', 'Nine mask candidates', 'Nine mask candidates' ],
		[ 'C128A', "\x00\x1f _", "\x00\x1f _" ],
		[ 'C128B', " az\x7f", " az\x7f" ],
		[ 'C128', "ab\x01cd", "ab\x01cd" ],
		[ 'C128', "\x01\x02a\x03", "\x01\x02a\x03" ],
		[ 'AZTEC,100,A,A,0', 'A', 'A' ],
		[ 'AZTEC,33,B,F', str_repeat( "\xa5", 31 ), str_repeat( "\xa5", 31 ) ],
		[ 'AZTEC,33,B,F', str_repeat( "\xa5", 32 ), str_repeat( "\xa5", 32 ) ],
	] as $fixture ) {
		[$type, $payload, $expected, $scale] = array_pad( $fixture, 4, 0 );
		$previous_scale                      = bcscale();
		bcscale( $scale );
		try {
			$model      = $factory->getBarcodeObj( $type, $payload );
			$fixtures[] = [
				'type'     => $type,
				'grid'     => $model->getGridArray(),
				'expected' => base64_encode( $expected ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Transport binary decoder fixtures through JSON.
			];
		} finally {
			bcscale( $previous_scale );
		}
	}
	echo json_encode( $fixtures, JSON_THROW_ON_ERROR ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Standalone CLI JSON protocol without WordPress.
	exit;
}

function oc_encoding_reject( callable $callback ): void {
	try {
		$callback();
	} catch ( \Com\Tecnick\Barcode\Exception $expected ) {
		return;
	}
	throw new RuntimeException( 'Expected a barcode library exception.' );
}

$factory  = new Barcode();
$promoted = $factory->getBarcodeObj( 'AZTEC,1,A,A', str_repeat( 'A', 110 ) )->getArray();
if ( 31 !== $promoted['ncols'] || 31 !== $promoted['nrows'] ) {
	throw new RuntimeException( 'Aztec must promote an overflowing compact mode message to full mode.' );
}
foreach ( [ 0, 5, 7, 16 ] as $word_size ) {
	oc_encoding_reject( fn () => new \Com\Tecnick\Barcode\Type\Square\Aztec\ErrorCorrection( $word_size ) );
}
foreach ( [ 4, 6, 8, 10, 12 ] as $word_size ) {
	$rs = new \Com\Tecnick\Barcode\Type\Square\Aztec\ErrorCorrection( $word_size );
	if ( $rs->checkwords( [ 1, 2 ], 0 ) !== [] || count( $rs->checkwords( [ 1, 2 ], 3 ) ) !== 3 ) {
		throw new RuntimeException( 'Valid Aztec Reed-Solomon parity generation changed.' );
	}
	oc_encoding_reject( fn () => $rs->checkwords( [ 1, 2 ], -1 ) );
	oc_encoding_reject( fn () => $rs->checkwords( [ 1, 2 ], 1 << $word_size ) );
	oc_encoding_reject( fn () => $rs->checkwords( [ 1 << $word_size ], 3 ) );
	oc_encoding_reject( fn () => $rs->checkwords( [ -1 ], 3 ) );
}
class OC_Aztec_Mode_Probe extends \Com\Tecnick\Barcode\Type\Square\Aztec\Encode {
	public function __construct() {}
	public function invalidMode( bool $compact, int $words ): void {
		$this->compact = $compact;
		$this->drawMode( $words );
	}
}
$mode_probe = new OC_Aztec_Mode_Probe();
oc_encoding_reject( fn () => $mode_probe->invalidMode( true, 65 ) );
oc_encoding_reject( fn () => $mode_probe->invalidMode( false, 2049 ) );
oc_encoding_reject( fn () => $mode_probe->invalidMode( true, 0 ) );

$rect = \Com\Tecnick\Barcode\Type\Square\Datamatrix\Data::getPaddingSize( 'R', 22 );
if ( 12 !== $rect[0] || 36 !== $rect[1] || 22 !== $rect[11] ) {
	throw new RuntimeException( 'Incorrect Data Matrix 12x36 capacity.' );
}
$largest = \Com\Tecnick\Barcode\Type\Square\Datamatrix\Data::getPaddingSize( 'S', 1558 );
if ( 144 !== $largest[0] || 1558 !== $largest[11] ) {
	throw new RuntimeException( 'Incorrect Data Matrix 144x144 capacity.' );
}
oc_encoding_reject( fn () => \Com\Tecnick\Barcode\Type\Square\Datamatrix\Data::getPaddingSize( 'S', 1559 ) );
oc_encoding_reject( fn () => $factory->getBarcodeObj( 'DATAMATRIX,S,N,BASE256', str_repeat( "\x80", 1557 ) ) );
$ecc = new \Com\Tecnick\Barcode\Type\Square\Datamatrix\ErrorCorrection();
if ( count( $ecc->getErrorCorrection( array_fill( 0, 1558, 0 ), 10, 156, 62 ) ) !== 2178 ) {
	throw new RuntimeException( 'Largest Data Matrix must contain exactly 2178 codewords.' );
}
if ( $factory->getBarcodeObj( 'DATAMATRIX,S,GS1', '0101234567890128' )->getArray()['bars']
	!== $factory->getBarcodeObj( 'DATAMATRIX,S,GS1', "\x1d0101234567890128" )->getArray()['bars'] ) {
	throw new RuntimeException( 'Implicit and explicit initial GS1 FNC1 must encode identically.' );
}

$placement = new \Com\Tecnick\Barcode\Type\Square\Datamatrix\Encode();
$map       = $placement->getPlacementMap( 132, 132 );
if ( hash( 'sha256', json_encode( $map ) ) !== 'a855c7d697a6087bfe5345623790f2d4ce8694861e48c575b92582d7a31771f1' ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Hash the standalone decoder fixture representation.
	throw new RuntimeException( 'In-place Data Matrix placement changed the largest placement map.' );
}
class OC_Datamatrix_Tie_Probe extends \Com\Tecnick\Barcode\Type\Square\Datamatrix\Encode {
	public function tie( string $tail ): int {
		$data = str_repeat( 'A', 21 ) . $tail;
		return $this->stepRf( [ 20.0, 14.0, 28.0, 14.0, 16.0, 22.0 ], 0, strlen( $data ), 20, $data );
	}
}
$ties = new OC_Datamatrix_Tie_Probe();
if ( $ties->tie( 'A*' ) !== \Com\Tecnick\Barcode\Type\Square\Datamatrix\Data::ENC_X12
	|| $ties->tie( 'a*' ) !== \Com\Tecnick\Barcode\Type\Square\Datamatrix\Data::ENC_C40 ) {
	throw new RuntimeException( 'C40/X12 tie-breaker must scan shared characters and stop at incompatible characters.' );
}
class OC_Pdf417_Budget_Probe extends \Com\Tecnick\Barcode\Type\Square\PdfFourOneSeven\Sequence {
	public function __construct() {}
	public function level( int $words ): int {
		return $this->getErrorCorrectionLevel( -1, $words );
	}
}
$budget = new OC_Pdf417_Budget_Probe();
for ( $words = 0; $words <= 925; ++$words ) {
	if ( $words + 1 + ( 2 << $budget->level( $words ) ) > 928 ) {
		throw new RuntimeException( 'PDF417 error correction exceeds total symbol capacity.' );
	}
}
oc_encoding_reject( fn () => $budget->level( 926 ) );
oc_encoding_reject( fn () => $factory->getBarcodeObj( 'PDF417,2,0,1,0,' . str_repeat( 'A', 1900 ), 'ABC' ) );
foreach ( [ 1800, 1848, 1850 ] as $length ) {
	$pdf   = $factory->getBarcodeObj( 'PDF417,1', str_repeat( 'A', $length ) );
	$rows  = 0;
	$cols  = 0;
	$level = 0;
	$words = $pdf->getCodewords( $rows, $cols, $level );
	if ( $rows < 3 || $rows > 90 || $cols < 1 || $cols > 30 || $rows * $cols > 928
		|| count( $words ) !== $rows * $cols ) {
		throw new RuntimeException( 'PDF417 selected a truncated or invalid symbol layout.' );
	}
}
oc_encoding_reject( fn () => $factory->getBarcodeObj( 'PDF417', str_repeat( 'A', 1852 ) ) );
$previous_scale = bcscale();
try {
	foreach ( [ str_repeat( '1234567890123', 4 ), str_repeat( "\x80", 12 ) ] as $payload ) {
		bcscale( 0 );
		$baseline = $factory->getBarcodeObj( 'PDF417', $payload )->getArray()['bars'];
		bcscale( 5 );
		if ( $factory->getBarcodeObj( 'PDF417', $payload )->getArray()['bars'] !== $baseline || bcscale() !== 5 ) {
			throw new RuntimeException( 'PDF417 must ignore and preserve the caller BCMath scale.' );
		}
	}
} finally {
	bcscale( $previous_scale );
}
if ( $factory->getBarcodeObj( 'QRCODE,L,8B,0,1,-1,0,-1', 'Mask' )->getArray()['bars']
	!== $factory->getBarcodeObj( 'QRCODE,L,8B,0,1,-1,0,7', 'Mask' )->getArray()['bars'] ) {
	throw new RuntimeException( 'Negative QR mask IDs must normalize into the supported range.' );
}

$probe = new OC_Aztec_Bitstream_Probe();
foreach ( [
	-1 => '',
	0  => '0000000000000',
	26 => '000000000001001001000',
] as $eci => $expected ) {
	if ( $probe->flag( $eci ) !== $expected ) {
		throw new RuntimeException( 'Incorrect Aztec ECI/FNC1 bitstream for ' . $eci );
	}
}
foreach ( [ 1, 31, 32, 62, 63, 2048, 2078, 2079, 4156 ] as $length ) {
	$bits = $probe->binary( str_repeat( "\xa5", $length ) );
	// Decode binary-run headers independently to verify lengths and preserved bytes.
	$position   = 0;
	$decoded    = '';
	$bit_length = strlen( $bits );
	while ( $position < $bit_length ) {
		if ( substr( $bits, $position, 5 ) !== '11111' ) {
			throw new RuntimeException( 'Missing Aztec binary shift' );
		}
		$position += 5;
		$count     = bindec( substr( $bits, $position, 5 ) );
		$position += 5;
		if ( 0 === $count ) {
			$count     = bindec( substr( $bits, $position, 11 ) ) + 31;
			$position += 11;
		}
		for ( $index = 0; $index < $count; ++$index ) {
			$byte = substr( $bits, $position, 8 );
			if ( strlen( $byte ) !== 8 ) {
				throw new RuntimeException( 'Truncated Aztec binary byte' );
			}
			$decoded  .= chr( bindec( $byte ) );
			$position += 8;
		}
	}
	if ( str_repeat( "\xa5", $length ) !== $decoded ) {
		throw new RuntimeException( 'Aztec binary run lost data at length ' . $length );
	}
}
class OC_Qr_Stream_Probe extends \Com\Tecnick\Barcode\Type\Square\QrCode\ByteStream {
	public function estimate( array $items, int $version ): int {
		return $this->estimateBitStreamSize( $items, $version );
	}
	public function zeroAppends( array $bits ): array {
		return [ $this->appendNum( $bits, 0, 0 ), $this->appendBytes( $bits, 0, [] ) ];
	}
}
class OC_Qr_Encoder_Probe extends \Com\Tecnick\Barcode\Type\Square\QrCode\Encoder {
	public int $mask_evaluations = 0;
	protected function evaluateSymbol( int $width, array $frame ): int {
		++$this->mask_evaluations;
		return parent::evaluateSymbol( $width, $frame );
	}
	public function checkBlocks(): void {
		foreach ( $this->rsblocks as $block ) {
			if ( count( $block['data'] ) !== $block['dataLength'] || count( $block['ecc'] ) !== $block['eccLength'] ) {
				throw new RuntimeException( 'QR block retains data outside its boundaries.' );
			}
		}
	}
	public function invalidRs( int $size ): void {
		$this->checkRsCharParamsA( $size, 0, 1 );
	}
}
$qr = new OC_Qr_Stream_Probe( 2, 1, 0 );
if ( $qr->zeroAppends( [ 1, 0, 1 ] ) !== [ [ 1, 0, 1 ], [ 1, 0, 1 ] ] ) {
	throw new RuntimeException( 'Zero-length QR appends must preserve existing bits.' );
}
foreach ( [ 1, 10, 27 ] as $version ) {
	foreach ( [
		0 => '1234567890',
		1 => 'ABCDE',
		2 => 'abcde',
		3 => "\x81\x40\xE0\x40",
	] as $mode => $unit ) {
		foreach ( [ 1, 3, 210 ] as $repeat ) {
			$payload = str_repeat( $unit, $repeat );
			$items   = $qr->appendNewInputItem( [], $mode, strlen( $payload ), str_split( $payload ) );
			$actual  = count( $qr->encodeBitStream( $items[0], $version )['bstream'] );
			if ( $qr->estimate( $items, $version ) !== $actual || $qr->estimate( array_merge( $items, $items ), $version ) !== 2 * $actual ) {
				throw new RuntimeException( 'QR segment estimate disagrees with encoded bits.' );
			}
		}
	}
}
$header = $qr->appendNewInputItem( [], 4, 3, [ chr( 2 ), chr( 1 ), chr( 255 ) ] );
$qr->appendNewInputItem( [], 4, 3, [ chr( 1 ), chr( 1 ), chr( 0 ) ] );
$body = $qr->appendNewInputItem( [], 2, 1, [ 'a' ] );
if ( $qr->estimate( array_merge( $header, $body ), 1 ) !== 40
	|| count( $qr->encodeBitStream( $header[0], 1 )['bstream'] ) !== 20 ) {
	throw new RuntimeException( 'QR structured append must include header and payload estimates.' );
}
foreach ( [
	[ 2, 3, [ 'A' ] ],
	[ 2, 1, [ 'AB' ] ],
	[ 2, 1, [ 1 ] ],
	[ 4, 2, [ chr( 2 ), chr( 1 ) ] ],
	[ 4, 3, [ chr( 17 ), chr( 1 ), chr( 0 ) ] ],
	[ 4, 3, [ chr( 2 ), chr( 3 ), chr( 0 ) ] ],
	[ 4, 3, [ chr( 2 ), chr( 0 ), chr( 0 ) ] ],
] as [$mode, $size, $data] ) {
	oc_encoding_reject( fn () => $qr->appendNewInputItem( [], $mode, $size, $data ) );
}
$split = new \Com\Tecnick\Barcode\Type\Square\QrCode\Split( $qr, 2, 1 );
$split->getSplittedString( 'FIRST' );
$second = $split->getSplittedString( 'SECOND' );
if ( implode( '', array_merge( ...array_column( $second, 'data' ) ) ) !== 'SECOND' || $split->getSplittedString( '' ) !== [] ) {
	throw new RuntimeException( 'Reused QR split retains a previous payload.' );
}
$spec = new \Com\Tecnick\Barcode\Type\Square\QrCode\Spec();
foreach ( [ 0, -1, 41 ] as $version ) {
	oc_encoding_reject( fn () => $spec->getEccSpec( $version, 0, [] ) );
	oc_encoding_reject( fn () => $spec->createFrame( $version ) );
	oc_encoding_reject( fn () => ( new OC_Qr_Encoder_Probe( $version, 0 ) )->encodeMask( 0, [] ) );
}
foreach ( [ -1, 4 ] as $level ) {
	oc_encoding_reject( fn () => $spec->getEccSpec( 1, $level, [] ) );
	oc_encoding_reject( fn () => ( new OC_Qr_Encoder_Probe( 1, $level ) )->encodeMask( 0, [] ) );
}
for ( $version = 1; $version <= 40; ++$version ) {
	$frame = $spec->createFrame( $version );
	if ( count( $frame ) !== 17 + 4 * $version || array_unique( array_map( 'strlen', $frame ) ) !== [ 17 + 4 * $version ] ) {
		throw new RuntimeException( 'QR frame geometry is invalid.' );
	}
}
$bytes = array_fill( 0, $spec->getDataLength( 1, 0 ), 0 );
if ( ( new OC_Qr_Encoder_Probe( 1, 0, -1, false, -1 ) )->encodeMask( -1, $bytes )
	!== ( new OC_Qr_Encoder_Probe( 1, 0 ) )->encodeMask( 7, $bytes ) ) {
	throw new RuntimeException( 'Direct QR encoder must normalize a negative default mask.' );
}
$encoder = new OC_Qr_Encoder_Probe( 1, 0 );
foreach ( [ -2, 8 ] as $mask ) {
	oc_encoding_reject( fn () => $encoder->encodeMask( $mask, $bytes ) );
}
oc_encoding_reject( fn () => $encoder->encodeMask( 0, array_slice( $bytes, 1 ) ) );
oc_encoding_reject( fn () => $encoder->encodeMask( 0, array_merge( $bytes, [ 0 ] ) ) );
foreach ( [ -1, 256, '1' ] as $invalid ) {
	$invalid_bytes    = $bytes;
	$invalid_bytes[0] = $invalid;
	oc_encoding_reject( fn () => $encoder->encodeMask( 0, $invalid_bytes ) );
}
foreach ( [ -1, 0, 9 ] as $size ) {
	oc_encoding_reject( fn () => $encoder->invalidRs( $size ) );
}
foreach ( [
	0  => 1,
	1  => 1,
	7  => 7,
	8  => 8,
	9  => 8,
	17 => 8,
] as $requested => $expected ) {
	$encoder = new OC_Qr_Encoder_Probe( 1, 0, $requested );
	$encoder->encodeMask( -1, $bytes );
	if ( $encoder->mask_evaluations !== $expected ) {
		throw new RuntimeException( 'QR mask candidate count wraps instead of clamping.' );
	}
}
foreach ( [ 1, 10, 40 ] as $version ) {
	$encoder = new OC_Qr_Encoder_Probe( $version, 0 );
	$bytes   = array_fill( 0, $spec->getDataLength( $version, 0 ), 0 );
	$first   = $encoder->encodeMask( 0, $bytes );
	$encoder->checkBlocks();
	if ( $encoder->encodeMask( 0, $bytes ) !== $first ) {
		throw new RuntimeException( 'Reused QR encoder output changed.' );
	}
}
echo "Barcode encoding regressions passed.\n";
