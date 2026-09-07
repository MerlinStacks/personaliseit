<?php
/** Dependency-free renderer regressions. Run with open_basedir set to the plugin. */
declare(strict_types=1);

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
function trailingslashit( string $path ): string { return rtrim( $path, '/' ) . '/'; }
function sanitize_text_field( string $text ): string { return strip_tags( $text ); }
function get_option( string $name, mixed $default = false ): mixed { return $default; }
function __( string $text, string $domain = '' ): string { return $text; }
function absint( mixed $value ): int { return abs( (int) $value ); }
function sanitize_file_name( string $value ): string { return preg_replace( '/[^a-zA-Z0-9.-]/', '-', $value ); }
function wp_generate_uuid4(): string { return bin2hex( random_bytes( 16 ) ); }
class WC_Order {
	public function get_id(): int { return 123; }
	public function get_order_number(): string { return 'oc-renderer-upgrade'; }
}
class TCPDF {
	public array $calls = [];
	public function __call( string $name, array $args ): mixed {
		$this->calls[] = [ $name, $args ];
		return 'GetStringWidth' === $name ? 20.0 : null;
	}
}
require ABSPATH . 'includes/print/class-oc-print-base.php';
require ABSPATH . 'includes/print/class-oc-print-embroidery.php';
require ABSPATH . 'includes/print/class-oc-print-engraving.php';

$checks = 0;
$files = [];
$check = static function ( bool $ok, string $message ) use ( &$checks ): void {
	++$checks;
	if ( ! $ok ) { throw new RuntimeException( $message ); }
};
$invoke = static fn ( string $class, string $method, mixed ...$args ): mixed => ( new ReflectionMethod( $class, $method ) )->invoke( null, ...$args );
$write = static function ( string $data, string $suffix = '' ) use ( &$files ): string {
	$base = tempnam( __DIR__, 'oc-renderer-audit-' );
	$files[] = $base;
	$path = $base . $suffix;
	if ( $path !== $base ) { $files[] = $path; }
	file_put_contents( $path, $data );
	return $path;
};
$reject = static function ( callable $call, string $message ) use ( $check ): void {
	try { $call(); } catch ( RuntimeException $e ) { $check( true, $message ); return; }
	$check( false, $message );
};

try {
	$eps = OC_Print_Embroidery::class;
	$base = OC_Print_Base::class;
	$legacy = [ 'layers' => [], 'text' => 'Retained legacy name', 'fontId' => 0 ];
	$normalised = OC_Print_Embroidery::normalise_payload( $legacy, 'legacy' );
	$check( 1 === $normalised['_oc_payload_version'] && ! array_key_exists( 'layers', $normalised ), 'Proven legacy empty layers did not migrate to summary semantics' );
	$check( $normalised === OC_Print_Embroidery::normalise_payload( json_decode( json_encode( $normalised ), true ) ), 'Persisted legacy semantics changed on replay' );
	$lines = [];
	( new ReflectionMethod( $eps, 'append_eps_legacy_artwork' ) )->invokeArgs( null, [ &$lines, (object) [ 'canvas_unit' => 'mm', 'canvas_w' => 100, 'canvas_h' => 100 ], $normalised ] );
	$check( str_contains( implode( "\n", $lines ), 'Retained legacy name' ), 'Upgraded legacy summary did not render' );
	$eps_area = (object) [ 'area_key' => 'front', 'canvas_unit' => 'mm', 'canvas_w' => 100, 'canvas_h' => 50 ];
	$eps_path = $invoke( $eps, 'generate_eps', __DIR__, new WC_Order(), 1, $eps_area, $normalised );
	$files[] = $eps_path;
	$check( str_contains( file_get_contents( $eps_path ), '%%OCExportMode: legacy-artwork' ) && str_contains( file_get_contents( $eps_path ), 'Retained legacy name' ), 'Legacy upgrade did not publish the expected EPS' );
	foreach ( [ $legacy + [ '_oc_payload_version' => 2 ], $legacy + [ 'renderSpecArea' => [ 'layers' => [] ] ] ] as $modern ) {
		$modern = OC_Print_Embroidery::normalise_payload( $modern, 'legacy' );
		$check( 2 === $modern['_oc_payload_version'] && [] === $modern['layers'], 'Modern empty layers lost authority to stale summary/source' );
		$lines = [];
		( new ReflectionMethod( $eps, 'append_eps_layers' ) )->invokeArgs( null, [ &$lines, (object) [ 'canvas_unit' => 'mm', 'canvas_w' => 100, 'canvas_h' => 100 ], $modern ] );
		$check( ! str_contains( implode( "\n", $lines ), 'Retained legacy name' ), 'Modern empty layers resurrected stale summary' );
		$reject( static fn () => $invoke( $eps, 'generate_eps', __DIR__, new WC_Order(), 1, $eps_area, $modern ), 'Modern empty payload published a stale-summary EPS' );
	}
	foreach ( [
		$legacy,
		[ 'layers' => [], 'text' => '0' ],
		[ 'layers' => [ [ 'type' => 'text', 'input' => [ 'value' => '' ] ] ], 'text' => 'Unknown fallback' ],
		[ '_oc_payload_version' => 1, 'layers' => [ [ 'type' => 'text' ] ], 'text' => 'Mixed legacy' ],
		[ '_oc_payload_version' => 2, 'text' => 'Missing modern layers' ],
		[ '_oc_payload_version' => 3, 'layers' => [] ],
		[ 'layers' => [], 'renderSpecArea' => [ 'layers' => [ [ 'type' => 'text' ] ] ] ],
	] as $ambiguous ) {
		$reject( static fn () => OC_Print_Embroidery::normalise_payload( $ambiguous ), 'Ambiguous/version-conflicting payload was rendered' );
	}
	$layer_only = [ 'layers' => [ [ 'type' => 'text', 'input' => [ 'value' => 'Retained layer' ] ] ] ];
	$check( 2 === OC_Print_Embroidery::normalise_payload( $layer_only )['_oc_payload_version'], 'Self-contained historical layer payload was blocked' );
	$check( null === $invoke( $eps, 'ttf_parse_cmap_format12', pack( 'nnNNN', 12, 0, 16, 0, 0xFFFFFFFF ), 0 ), 'Unbounded cmap12 count' );
	$check( null === $invoke( $eps, 'ttf_parse_cmap_format4', pack( 'n8', 4, 16, 0, 65534, 0, 0, 0, 0 ), 0 ), 'Unbounded cmap4 segments' );
	$check( null === $invoke( $eps, 'ttf_parse_cmap', pack( 'nnnnN', 0, 1, 3, 10, 9999 ), 0 ), 'Out-of-table cmap offset' );
	$check( null === $invoke( $eps, 'ttf_parse_cmap_format12', pack( 'nnNNNNNN', 12, 0, 28, 0, 1, 90, 65, 1 ), 0 ), 'Reversed cmap group' );
	$reject( static fn () => $invoke( $eps, 'ttf_u8', '', -1 ), 'Negative byte read' );
	$reject( static fn () => $invoke( $eps, 'ttf_u16', "\0", 0 ), 'Truncated word read' );
	$reject( static fn () => $invoke( $eps, 'ttf_u32', "\0", 0 ), 'Truncated dword read' );

	// A minimal retained TrueType with one triangular A and an empty .notdef.
	$glyph = pack( 'n5', 1, 0, 0, 10, 10 ) . pack( 'nn', 2, 0 ) . "\x31\x33\x35\x0a\x0a\0";
	$head = str_repeat( "\0", 54 );
	$head = substr_replace( $head, pack( 'n', 1000 ), 18, 2 );
	$tables = [ 'head' => $head, 'hhea' => str_repeat( "\0", 34 ) . pack( 'n', 1 ), 'maxp' => pack( 'Nn', 0x10000, 2 ), 'hmtx' => pack( 'n3', 500, 0, 0 ), 'loca' => pack( 'n3', 0, 0, 10 ), 'glyf' => $glyph, 'cmap' => pack( 'nnnnN', 0, 1, 3, 10, 12 ) . pack( 'nnNNNNNN', 12, 0, 28, 0, 1, 65, 65, 1 ) ];
	$font = pack( 'Nnnnn', 0x10000, count( $tables ), 0, 0, 0 );
	$offset = 12 + count( $tables ) * 16;
	foreach ( $tables as $tag => $data ) {
		$font .= $tag . pack( 'NNN', 0, $offset, strlen( $data ) );
		$offset += strlen( $data );
	}
	$font .= implode( '', $tables );
	$path = $write( $font, '.ttf' );
	$outline = $invoke( $eps, 'ttf_text_outline', $path, 'A', 10.0 );
	$check( is_array( $outline ) && 5.0 === $outline['width'] && count( $outline['commands'] ) >= 4, 'Valid font outline rejected' );
	$check( null === $invoke( $eps, 'load_ttf_outline_font', $write( substr_replace( $font, pack( 'N', 0xFFFFFFFF ), 20, 4 ), '.ttf' ) ), 'Invalid table offset accepted' );
	$loca_offset = 12 + count( $tables ) * 16 + strlen( $head ) + 36 + 6 + 6;
	$check( null === $invoke( $eps, 'load_ttf_outline_font', $write( substr_replace( $font, pack( 'n3', 0, 11, 10 ), $loca_offset, 6 ), '.ttf' ) ), 'Descending loca accepted' );

	$composite = pack( 'n5', 65535, 0, 0, 0, 0 ) . pack( 'nn', 3, 0 );
	$composite_font = [ 'data' => $composite . str_repeat( "\0", 30 ), 'num_glyphs' => 1, 'glyph_offsets' => [ 0, strlen( $composite ) ], 'tables' => [ 'glyf' => [ 'offset' => 0, 'length' => strlen( $composite ) ] ] ];
	$reject( static fn () => $invoke( $eps, 'ttf_glyph_contours', $composite_font, 0 ), 'Composite read crossed glyph boundary' );
	$cycle = $composite . pack( 'nn', 0, 0 );
	$composite_font['data'] = $cycle;
	$composite_font['glyph_offsets'][1] = strlen( $cycle );
	$composite_font['tables']['glyf']['length'] = strlen( $cycle );
	$reject( static fn () => $invoke( $eps, 'ttf_glyph_contours', $composite_font, 0 ), 'Composite cycle not rejected' );
	$budget = 100000;
	$reject( static function () use ( $eps, $composite_font, &$budget ): void {
		( new ReflectionMethod( $eps, 'ttf_glyph_contours' ) )->invokeArgs( null, [ $composite_font, 0, 0, &$budget ] );
	}, 'Composite work budget not enforced' );

	$lines = [];
	( new ReflectionMethod( $eps, 'append_svg_paint_eps' ) )->invokeArgs( null, [ &$lines, [ '0 0 moveto', '10 10 lineto', 'closepath' ], [ 'fill' => 'red', 'stroke' => 'red', 'fill-rule' => 'evenodd', 'stroke-width' => '4' ] ] );
	$check( in_array( 'eofill', $lines, true ) && in_array( 'stroke', $lines, true ) && in_array( '1.0000 0.0000 0.0000 setrgbcolor', $lines, true ), 'SVG fill/stroke semantics changed' );
	foreach ( [ '<path d="m10 10 l20 20 a30 20 45 0 1 50 50"/>', '<g transform="translate(50 50)"><rect width="10" height="20"/></g>' ] as $content ) {
		$dom = new DOMDocument();
		$dom->loadXML( '<svg viewBox="0 0 200 100" width="200" height="100">' . $content . '</svg>' );
		$invoke( $eps, 'crop_svg_to_visible_bounds', $dom->documentElement );
		$check( '0 0 200 100' === $dom->documentElement->getAttribute( 'viewBox' ), 'Incorrect SVG viewport crop' );
	}
	$lines = [];
	$svg = $write( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><circle cx="10" cy="10" r="5" fill="rebeccapurple"/></svg>', '.svg' );
	$check( false === ( new ReflectionMethod( $eps, 'append_eps_svg_vector' ) )->invokeArgs( null, [ &$lines, $svg, 0.0, 0.0, 20.0, 20.0 ] ) && [] === $lines, 'Unsupported named paint did not request fallback atomically' );
	$lines = [];
	( new ReflectionMethod( $eps, 'append_eps_layers' ) )->invokeArgs( null, [ &$lines, (object) [ 'canvas_unit' => 'mm', 'canvas_w' => 100, 'canvas_h' => 100 ], [ 'text' => 'STALE SUMMARY', 'layers' => [ [ 'type' => 'text', 'w' => 10, 'h' => 10, 'input' => [ 'value' => '' ] ] ] ] ] );
	$check( ! str_contains( implode( "\n", $lines ), 'STALE SUMMARY' ), 'Canonical empty text repopulated' );
	$lines = [];
	( new ReflectionMethod( $eps, 'append_eps_legacy_artwork' ) )->invokeArgs( null, [ &$lines, (object) [ 'canvas_unit' => 'mm', 'canvas_w' => 100, 'canvas_h' => 100 ], [ 'text' => 'REAL LEGACY' ] ] );
	$check( str_contains( implode( "\n", $lines ), 'REAL LEGACY' ), 'Real legacy text no longer renders' );

	$name = $invoke( $base, 'tc_lib_pdf_font_name', $path );
	$check( 1 === preg_match( '/^oc[a-f0-9]{64}$/D', $name ), 'Unsafe importer cache identity' );
	$check( $name !== $invoke( $base, 'tc_lib_pdf_font_name', $write( $font, '.ttf' ) ), 'Distinct source identities collided' );
	$stem = $write( '' );
	foreach ( [ '.ttf', '-regular.ttf' ] as $suffix ) {
		$files[] = $stem . $suffix;
		file_put_contents( $stem . $suffix, $font );
	}
	$check( $invoke( $base, 'tc_lib_pdf_font_name', $stem . '.ttf' ) !== $invoke( $base, 'tc_lib_pdf_font_name', $stem . '-regular.ttf' ), 'Lossy importer basenames collided' );
	file_put_contents( $path, $font . "\0" );
	$check( $name !== $invoke( $base, 'tc_lib_pdf_font_name', $path ), 'Changed source reused font identity' );
	$dir = __DIR__ . '/';
	$cache_name = basename( $write( '' ) );
	$artifacts = [ '.json' => json_encode( [ 'type' => 'TrueTypeUnicode', 'file' => $cache_name . '.z', 'ctg' => $cache_name . '.ctg.z', 'cw' => [ 65 => 500 ] ] ), '.z' => gzcompress( $font ), '.ctg.z' => gzcompress( str_repeat( "\0", 131072 ) ) ];
	$manifest = [];
	foreach ( $artifacts as $suffix => $data ) {
		$artifact = $dir . $cache_name . $suffix;
		$files[] = $artifact;
		file_put_contents( $artifact, $data );
		$manifest[ $suffix ] = hash( 'sha256', $data );
	}
	$check( false === $invoke( $base, 'tcpdf_font_cache_complete', $dir, $cache_name ), 'Uncommitted font artifacts reused' );
	$files[] = $dir . $cache_name . '.manifest.json';
	file_put_contents( end( $files ), json_encode( $manifest ) );
	$check( true === $invoke( $base, 'tcpdf_font_cache_complete', $dir, $cache_name ), 'Complete font cache rejected' );
	file_put_contents( $dir . $cache_name . '.ctg.z', 'broken' );
	$check( false === $invoke( $base, 'tcpdf_font_cache_complete', $dir, $cache_name ), 'Corrupt glyph artifact reused' );
	unlink( $dir . $cache_name . '.z' );
	$check( false === $invoke( $base, 'tcpdf_font_cache_complete', $dir, $cache_name ), 'Missing compressed font reused' );

	$pdf = new TCPDF();
	$invoke( $base, 'render_layer_night_sky', $pdf, [ 'nightSkyGeometry' => [ 'v' => 1, 'border' => true, 'segments' => [], 'stars' => [], 'labels' => [ [ 'text' => 'A', 'x' => 0.5, 'y' => 0.5, 'size' => 0.02 ] ] ] ], 0.0, 0.0, 100.0, 100.0, 'engraving' );
	$circles = array_values( array_filter( $pdf->calls, static fn ( array $call ): bool => 'Circle' === $call[0] ) );
	$check( 1 === count( $circles ) && 'D' === $circles[0][1][5] && 48.0 === $circles[0][1][2], 'Night Sky border is not one continuous circle' );
	$texts = array_values( array_filter( $pdf->calls, static fn ( array $call ): bool => 'Text' === $call[0] ) );
	$check( 40.0 === $texts[0][1][0] && 'C' === $texts[0][1][13], 'Night Sky label not centered' );
	$alphas = array_values( array_map( static fn ( array $call ): float => $call[1][0], array_filter( $pdf->calls, static fn ( array $call ): bool => 'SetAlpha' === $call[0] ) ) );
	$check( [ 0.48, 1.0, 0.78, 1.0 ] === $alphas, 'Night Sky opacity parity/reset' );

	if ( function_exists( 'imagecreate' ) ) {
		$image = imagecreate( 1201, 2 );
		$transparent = imagecolorallocate( $image, 0, 0, 0 );
		$white = imagecolorallocate( $image, 255, 255, 255 );
		imagecolortransparent( $image, $transparent );
		imagesetpixel( $image, 1200, 1, $white );
		$png = $write( '', '.png' );
		imagepng( $image, $png );
		imagedestroy( $image );
		$image = $invoke( OC_Print_Engraving::class, 'open_image_resource', $png, 20, 20 );
		$check( imageistruecolor( $image ) && 1201 === imagesx( $image ), 'Palette source was not promoted at original size' );
		$check( 127 === ( ( imagecolorat( $image, 0, 0 ) >> 24 ) & 127 ), 'Palette transparency lost' );
		$check( true === $invoke( OC_Print_Engraving::class, 'is_transparent_logo', $image ), 'Indexed white logo not detected' );
		imagedestroy( $image );
	} else {
		print "SKIP: GD palette regressions (extension unavailable)\n";
	}
	print "PASS: {$checks} renderer checks\n";
} finally {
	foreach ( array_unique( $files ) as $file ) {
		if ( is_file( $file ) ) { unlink( $file ); }
	}
}
