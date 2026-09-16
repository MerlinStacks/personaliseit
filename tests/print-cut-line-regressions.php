<?php
/** Standalone production cut-line regressions, including real TCPDF vector output. */
declare(strict_types=1);
define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'OC_PATH', ABSPATH );
define( 'K_PATH_FONTS', ABSPATH . 'vendor/tecnickcom/tc-lib-pdf-font/target/fonts' );
define( 'K_PATH_CACHE', '/tmp/opencode/' );
function apply_filters( string $hook, mixed $value ): mixed { return $value; }
function wp_upload_dir(): array { return [ 'basedir' => '/tmp/opencode', 'error' => false ]; }
function trailingslashit( string $path ): string { return rtrim( $path, '/' ) . '/'; }
function __( string $text, string $domain = '' ): string { return $text; }
function sanitize_key( string $value ): string { return strtolower( $value ); }
function sanitize_textarea_field( string $value ): string { return strip_tags( $value ); }
function wp_unslash( string $value ): string { return $value; }
function wp_parse_args( array $args, array $defaults = [] ): array { return array_merge( $defaults, $args ); }
function get_option( string $key, mixed $default = false ): mixed { return $GLOBALS['options'][ $key ] ?? $default; }
function is_wp_error( mixed $value ): bool { return $value instanceof WP_Error; }
class WP_Error {
	public function __construct( public string $code, public string $message ) {}
	public function get_error_message(): string { return $this->message; }
}
require ABSPATH . 'vendor/autoload.php';
require ABSPATH . 'includes/class-oc-svg-sanitiser.php';
require ABSPATH . 'includes/class-oc-cut-line.php';
require ABSPATH . 'includes/admin/class-oc-admin-print-methods.php';
require ABSPATH . 'includes/print/class-oc-print-base.php';
require ABSPATH . 'includes/class-oc-print-generator.php';

$count = 0;
$check = static function ( bool $ok, string $message ) use ( &$count ): void {
	++$count;
	if ( ! $ok ) { throw new RuntimeException( $message ); }
};
$invoke = static fn ( string $method, mixed ...$args ): mixed => ( new ReflectionMethod( OC_Print_Base::class, $method ) )->invoke( null, ...$args );
$near = static fn ( float $a, float $b ): bool => abs( $a - $b ) < 0.00001;
$area = (object) [ 'canvas_x' => 20, 'canvas_y' => 30, 'canvas_w' => 100, 'canvas_h' => 50, 'canvas_unit' => 'mm' ];
$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><g transform="translate(10 10)"><path style="fill:#123456;stroke:blue;opacity:0" stroke-width="12" d="M0 0 L80 0 L80 80 Z"/></g></svg>';
$layer = [ 'type' => 'cut_line', 'x' => 0, 'y' => 20, 'w' => 140, 'h' => 80, 'settings' => [ 'cutLineSvg' => $svg ] ];
$data = [ 'bounds' => [ 'x' => 20, 'y' => 30, 'w' => 100, 'h' => 50 ], 'layers' => [ $layer ] ];
$check( '#FF0000' === OC_Admin_Print_Methods::get( 'engraving' )['cut_line_colour'], 'Missing setting must default red' );
$GLOBALS['options']['oc_print_methods'] = [ 'engraving' => [ 'cut_line_colour' => '#0a7' ] ];
$check( '#00AA77' === OC_Admin_Print_Methods::get( 'engraving' )['cut_line_colour'], 'Saved short hex normalization' );
foreach ( [ '', 'red', '#xyz', [], '#1234567' ] as $bad ) {
	$check( '#FF0000' === OC_Admin_Print_Methods::cut_line_colour( $bad ), 'Invalid setting default' );
}
$admin = ( new ReflectionClass( OC_Admin_Print_Methods::class ) )->newInstanceWithoutConstructor();
$saved = ( new ReflectionMethod( $admin, 'sanitize_method_settings' ) )->invoke( $admin, 'engraving', [ 'cut_line_colour' => '#13579b' ], OC_Admin_Print_Methods::get( 'engraving' ) );
$check( '#13579B' === $saved['cut_line_colour'], 'Admin save colour' );
$bounds = $invoke( 'cut_line_page_bounds', $area, $data );
foreach ( [ -20.1, -10.1, 120.1, 70.1 ] as $i => $expected ) { $check( $near( $bounds[$i], $expected ), 'Overflow bounds' ); }
$ordinary = [ 'layers' => [ [ 'type' => 'lineart', 'x' => -500, 'y' => -500, 'w' => 1000, 'h' => 1000 ] ] ];
$check( [ 0.0, 0.0, 100.0, 50.0 ] === $invoke( 'cut_line_page_bounds', $area, $ordinary ), 'Ordinary artboard remains unchanged' );
$layout = $invoke( 'combined_sheet_layout', [ [ 'area' => $area, 'area_data' => $data ], [ 'area' => $area, 'area_data' => $data ] ], 0.0, 5.0, true );
$check( $near( $layout['page_w'], 285.4 ) && $near( $layout['page_h'], 80.2 ), 'Combined page includes both expanded areas and gap' );
$check( $near( $layout['entries'][0]['x'], 20.1 ) && $near( $layout['entries'][1]['x'], 165.3 ), 'Combined origins compensate overflow' );
$rotated = $data;
$rotated['layers'][0]['rotation'] = 90;
$rb = $invoke( 'cut_line_page_bounds', $area, $rotated );
$check( $near( $rb[1], -40.1 ) && $near( $rb[3], 100.1 ), 'Layer rotation expands page' );
foreach ( [ 90, 270 ] as $turn ) {
	$turned = $data;
	$turned['bounds']['rotation'] = $turn;
	[ $flat ] = $invoke( 'normalise_rotated_artboard_for_print', $area, $turned );
	$box = $invoke( 'cut_line_box', $flat, $turned, $layer );
	$check( $near( $box[2], 140 ) && $near( $box[3], 80 ) && $near( $box[4], $turn ), 'Quarter turn retains physical cutting geometry' );
}
$prepared = $invoke( 'cut_line_svg', $layer, 140.0, 80.0 );
$check( str_contains( $prepared, 'preserveAspectRatio="none"' ) && str_contains( $prepared, 'transform="translate(10 10)"' ), 'Free stretch and SVG transforms preserved' );
$offset_layer = $layer;
$offset_layer['settings']['cutLineSvg'] = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="-50 25 100 100" transform="rotate(10)"><path d="M-50 25L50 125"/></svg>';
$offset_svg = $invoke( 'cut_line_svg', $offset_layer, 100.0, 100.0 );
$check( str_contains( $offset_svg, 'viewBox="0 0 100.2 100.2"' ) && str_contains( $offset_svg, 'translate(50.1 -24.9)' ) && str_contains( $offset_svg, 'transform="rotate(10)"' ), 'Nonzero viewport origins normalized without losing root transforms' );
$dom = new DOMDocument();
$dom->loadXML( $prepared );
foreach ( $dom->getElementsByTagName( '*' ) as $node ) {
	if ( ! $node->hasAttribute( 'stroke' ) ) { continue; } // New normalization groups inherit paint.
	$check( 'none' === $node->getAttribute( 'fill' ) && '#00AA77' === $node->getAttribute( 'stroke' ) && ! $node->hasAttribute( 'style' ), 'All paint overrides enforced' );
}
foreach ( [ '', '<svg>', '<svg xmlns="http://www.w3.org/2000/svg"><image href="bad.png"/></svg>', '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0L1 1"/></svg>' ] as $bad ) {
	$invalid = $layer;
	$invalid['settings']['cutLineSvg'] = $bad;
	try { $invoke( 'cut_line_svg', $invalid, 140.0, 80.0 ); $rejected = false; } catch ( RuntimeException $e ) { $rejected = true; }
	$check( $rejected, 'Invalid/missing SVG fails closed' );
}
$check( ( new ReflectionMethod( OC_Print_Generator::class, 'area_has_printable_data' ) )->invoke( null, $data ), 'Cut-line-only area is printable' );

// The actual renderer must emit vector drawing operators, without any image XObjects.
$pdf = $invoke( 'make_pdf', 140.2, 80.2 );
$pdf->setPrintHeader( false );
$pdf->setPrintFooter( false );
$pdf->SetAutoPageBreak( false, 0 );
$pdf->SetCompression( false );
$pdf->AddPage();
$invoke( 'render_layer_payload', $pdf, $area, $data, 20.1, 10.1, 'engraving' );
$raw = $pdf->Output( 'cut-line.pdf', 'S' );
$check( ! preg_match( '~/Subtype\s*/Image\b~', $raw ), 'Cut line must never rasterize' );
$streams = '';
preg_match_all( '/stream\r?\n(.*?)\r?\nendstream/s', $raw, $matches );
foreach ( $matches[1] as $stream ) { $streams .= @gzuncompress( $stream ) ?: $stream; }
$check( (bool) preg_match( '/\b0(?:\.0+)? 0\.666667 0\.466667 RG/', $streams ), 'Configured RGB stroke survives PDF output' );
$check( str_contains( $streams, ' l' ) && str_contains( $streams, "S\n" ), 'PDF contains stroked vector paths' );
$check( (bool) preg_match( '~/MediaBox\s*\[\s*0(?:\.0+)?\s+0(?:\.0+)?\s+397\.4173\d*\s+227\.3385\d*\s*\]~', $raw ), 'PDF retains exact expanded physical page dimensions' );
$check( str_contains( $streams, '5.291339 0.000000 0.000000 3.023622' ), 'Actual PDF stretches the square SVG independently to 140 by 80 mm' );

// Exercise the same production PDF factory and entry origins as combined engraving.
$combined = $invoke( 'make_pdf', $layout['page_w'], $layout['page_h'] );
$combined->AddPage();
foreach ( $layout['entries'] as $entry ) {
	$invoke( 'render_layer_payload', $combined, $entry['area'], $entry['area_data'], $entry['x'], $entry['y'], 'engraving' );
}
$combined_raw = $combined->Output( 'combined-cut-lines.pdf', 'S' );
$combined_streams = '';
preg_match_all( '/stream\r?\n(.*?)\r?\nendstream/s', $combined_raw, $matches );
foreach ( $matches[1] as $stream ) { $combined_streams .= @gzuncompress( $stream ) ?: $stream; }
$check( ! preg_match( '~/Subtype\s*/Image\b~', $combined_raw ), 'Combined cut lines stay vector-only' );
$check( 2 === preg_match_all( '/\bh\s+S\b/', $combined_streams ), 'Both combined entries emit their cutting paths' );
$check( (bool) preg_match( '~/MediaBox\s*\[\s*0(?:\.0+)?\s+0(?:\.0+)?\s+809\.0078\d*\s+227\.3385\d*\s*\]~', $combined_raw ), 'Combined PDF retains both overflow regions and the gap' );

// Overflow engraving remains clipped to its artboard, while cut lines bypass that clip.
$probe = new class extends TCPDF {
	public array $events = [];
	public function StartTransform() { $this->events[] = 'push'; }
	public function StopTransform() { $this->events[] = 'pop'; }
	public function Rect( $x, $y, $w, $h, $style = '', $border_style = [], $fill_color = [] ) { $this->events[] = $style; }
	public function ImageSVG( $_file, $_x = null, $_y = null, $_w = 0, $_h = 0, $_link = '', $_align = '', $_palign = '', $_border = 0, $_fitonpage = false ) { $this->events[] = 'svg'; }
	public function Rotate( $angle, $x = -1, $y = -1 ) {}
	public function SetFillColor( $c1 = 0, $c2 = -1, $c3 = -1, $c4 = -1, $ret = false, $name = '' ) {}
};
$mixed = $data;
$mixed['layers'] = [ $ordinary['layers'][0], $layer ];
$invoke( 'render_layer_payload', $probe, $area, $mixed, 20.1, 10.1, 'engraving' );
$check( [ 'push', 'CNZ', 'F', 'pop', 'push', 'svg', 'pop' ] === $probe->events, 'Only ordinary engraving is clipped on expanded pages' );
foreach ( [ 'colour', 'spot' ] as $mode ) {
	$probe = new class extends TCPDF {
		public int $svg_calls = 0;
		public function ImageSVG( $_file, $_x = null, $_y = null, $_w = 0, $_h = 0, $_link = '', $_align = '', $_palign = '', $_border = 0, $_fitonpage = false ) { ++$this->svg_calls; }
	};
	$invoke( 'render_layer_payload', $probe, $area, $data, 0.0, 0.0, $mode );
	$check( 0 === $probe->svg_calls, 'Nonengraving excludes cut lines' );
}
echo "PASS: {$count} cut-line print checks\n";
