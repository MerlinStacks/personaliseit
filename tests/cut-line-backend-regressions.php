<?php
/** Focused backend regression suite. Run: php tests/cut-line-backend-regressions.php
 * @package OverCustomise
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
class WP_Error {
	public function __construct( public string $code, public string $message ) {}
	public function get_error_message(): string {
		return $this->message;
	}
}
function __( string $value, string $domain = '' ): string {
	return $value;
}
function is_wp_error( mixed $value ): bool {
	return $value instanceof WP_Error;
}
function absint( mixed $value ): int {
	return abs( (int) $value );
}
function sanitize_key( string $value ): string {
	return preg_replace( '/[^a-z0-9_-]/', '', strtolower( $value ) );
}
function sanitize_text_field( string $value ): string {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Standalone WordPress stub uses native PHP.
	return strip_tags( $value );
}
function wp_json_encode( mixed $value ): string {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Native implementation of the WordPress stub.
	return json_encode( $value );
}
function wp_unslash( mixed $value ): mixed {
	return $value;
}
function check_ajax_referer( string $action, string $field ): void {
	check( 'oc-products-nonce' === $action && 'nonce' === $field, 'Upload nonce contract' );
}
function current_user_can( string $cap ): bool {
	return $GLOBALS['can_manage'] ?? false;
}
class Json_Response extends RuntimeException {
	public function __construct( public array $data, public int $status ) {}
}
function wp_send_json_error( array $data, int $status = 200 ): never {
	// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Capture the original response payload for assertions, not HTML output.
	throw new Json_Response( $data, $status );
}
function check( bool $condition, string $message ): void {
	if ( ! $condition ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI assertion diagnostic.
		throw new RuntimeException( $message );
	}
	++$GLOBALS['assertions'];
}
class OC_DB {
	public static array $areas  = [];
	public static array $layers = [];
	public static function get_design( int $id ): object {
		return (object) [
			'id'     => $id,
			'active' => 1,
		];
	}
	public static function get_design_print_areas( int $id ): array {
		return self::$areas;
	}
	public static function get_design_layers( int $id ): array {
		return self::$layers;
	}
	public static function get_assignment_for_product( int $id, int $variant ): object {
		return (object) [ 'design_id' => 1 ];
	}
	public static function assignment_allows_design( object $assignment, int $id ): bool {
		return 1 === $id;
	}
}
foreach ( [ 'class-oc-svg-sanitiser.php', 'class-oc-cut-line.php', 'class-oc-render-math.php', 'class-oc-render-spec.php', 'frontend/class-oc-cart.php', 'frontend/class-oc-frontend.php', 'admin/class-oc-admin-products.php' ] as $file ) {
	require_once ABSPATH . 'includes/' . $file;
}
$assertions = 0;
$svg        = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="-25 -10 900 17" preserveAspectRatio="none"><g transform="rotate(12)"><path d="M-25 -10L875 7Z" fill="none" stroke="#ff0000"/></g></svg>';
$clean      = OC_Cut_Line::sanitize( $svg );
check( is_string( $clean ), 'Accept non-square signed geometry' );
check( str_contains( $clean, 'viewBox="-25 -10 900 17"' ) && str_contains( $clean, 'preserveAspectRatio="none"' ), 'Preserve aspect and viewBox' );
check( OC_Cut_Line::sanitize( $clean ) === $clean, 'Sanitization is idempotent' );
$unsafe = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10" onload="alert(1)"><script>alert(1)</script><foreignObject><div>secret</div></foreignObject><image href="https://example.org/secret"/><use href="#loop"/><path d="M0 0L1 1" style="stroke:red;fill:url(https://example.org/a)" onclick="evil()"/></svg>';
$safe   = OC_Cut_Line::sanitize( $unsafe );
check( is_string( $safe ) && str_contains( $safe, '<path' ), 'Retain safe geometry' );
foreach ( [ 'script', 'foreignObject', '<image', '<use', 'onload', 'onclick', 'https://', 'url(' ] as $forbidden ) {
	check( ! str_contains( $safe, $forbidden ), 'Remove unsafe content: ' . $forbidden );
}
foreach ( [ '', [], '<svg>', '<html/>', '<svg/>', '<!DOCTYPE svg [<!ENTITY x SYSTEM "file:///etc/passwd">]><svg>&x;</svg>', str_repeat( 'x', OC_Cut_Line::MAX_BYTES + 1 ) ] as $invalid ) {
	check( is_wp_error( OC_Cut_Line::sanitize( $invalid ) ), 'Reject invalid/oversized SVG' );
}
// All viewport fixtures contain geometry so rejection specifically exercises viewport validation.
$viewport_svg = static fn ( string $attributes ): string => '<svg xmlns="http://www.w3.org/2000/svg" ' . $attributes . '><path d="M0 0L1 1"/></svg>';
foreach ( [ '96', '96px', '25.4mm', '2.54cm', '1in', '72pt', '6pc', '+9.6e1px', ' .96e2 ' ] as $length ) {
	$normalized = OC_Cut_Line::sanitize( $viewport_svg( 'width="' . $length . '" height=".5in"' ) );
	check( is_string( $normalized ), 'Accept absolute length: ' . $length );
	$dom = new DOMDocument();
	$dom->loadXML( $normalized );
	// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM property name.
	$view = array_map( 'floatval', explode( ' ', $dom->documentElement->getAttribute( 'viewBox' ) ) );
	check( 4 === count( $view ) && 0.0 === $view[0] && 0.0 === $view[1] && abs( $view[2] - 96 ) < 0.000001 && abs( $view[3] - 48 ) < 0.000001, 'Normalize absolute dimensions at 96 DPI: ' . $length );
	check( OC_Cut_Line::sanitize( $normalized ) === $normalized, 'Normalized viewport is idempotent' );
}
foreach ( [ '0 0 100 20', '-25, -10, 9e2, +17', '0 0 .5 1.' ] as $view ) {
	check( is_string( OC_Cut_Line::sanitize( $viewport_svg( 'viewBox="' . $view . '" width="100%" height="auto"' ) ) ), 'Valid viewBox takes precedence over intrinsic dimensions' );
}
$invalid_viewports = [ '', 'width="10"', 'height="10"', 'style="width:10px;height:10px"' ];
foreach ( [ '', '100%', 'auto', '1em', '1rem', '1vw', '1Q', '1PX', '1 px', '-1', '0', '0mm', 'NaN', 'INF', '1e309', '1e308in', '1e-999', '10junk' ] as $length ) {
	$invalid_viewports[] = 'width="' . $length . '" height="10"';
	$invalid_viewports[] = 'width="10" height="' . $length . '"';
}
foreach ( [ '', '0 0 10', '0 0 10 10 10', '0 0 0 10', '0 0 10 -1', 'NaN 0 10 10', '0 INF 10 10', '0 0 1e309 10', '0 0 10 1e-999', '0 0 10px 10' ] as $view ) {
	$invalid_viewports[] = 'viewBox="' . $view . '" width="10" height="10"';
}
foreach ( $invalid_viewports as $attributes ) {
	$viewport_error = OC_Cut_Line::sanitize( $viewport_svg( $attributes ) );
	check( is_wp_error( $viewport_error ) && 'invalid_cut_line' === $viewport_error->code, 'Reject invalid viewport early: ' . $attributes );
	check( str_contains( $viewport_error->get_error_message(), 'viewBox' ) && str_contains( $viewport_error->get_error_message(), 'width and height' ), 'Viewport error explains how to fix artwork' );
}
$save = new ReflectionMethod( OC_Admin_Products::class, 'normalise_design_layer_settings' );
check(
	[ 'cutLineSvg' => $safe ] === $save->invoke(
		null,
		[
			'cutLineSvg'            => $unsafe,
			'default_attachment_id' => 99,
		],
		'cut_line',
		'engraving'
	),
	'Save re-sanitizes and drops attachment settings'
);
try {
	$save->invoke( null, [ 'cutLineSvg' => '<svg/>' ], 'cut_line', 'engraving' );
	throw new LogicException( 'Invalid SVG was saved' );
} catch ( RuntimeException $error ) {
	check( ! $error instanceof LogicException, 'Save rejects invalid SVG' );
}
$area          = (object) [
	'id'           => 10,
	'area_key'     => 'front',
	'label'        => 'Front',
	'print_method' => 'engraving',
	'canvas_x'     => 0,
	'canvas_y'     => 0,
	'canvas_w'     => 100,
	'canvas_h'     => 100,
	'visible'      => 1,
];
$cut           = (object) [
	'id'       => 20,
	'area_id'  => 10,
	'type'     => 'cut_line',
	'label'    => 'Private die',
	'x'        => -25,
	'y'        => -10,
	'w'        => 900,
	'h'        => 17,
	'visible'  => 1,
	'locked'   => 0,
	'settings' => wp_json_encode( [ 'cutLineSvg' => $svg ] ),
];
OC_DB::$areas  = [ $area ];
OC_DB::$layers = [ $cut ];
$spec          = OC_Render_Spec::build(
	1,
	[
		20 => [
			'cutLineSvg'   => $unsafe,
			'attachmentId' => 99,
			'x'            => 0,
		],
	]
);
$layer         = $spec['areas'][10]['layers'][0];
check( $clean === $layer['settings']['cutLineSvg'] && [] === $layer['input'], 'Render spec uses server SVG and ignores all submitted inputs' );
check( -25 === $layer['x'] && 900 === $layer['w'] && 17 === $layer['h'] && $layer['locked'], 'Render spec preserves independent geometry' );
check( ! isset( $spec['areas'][10]['snapshot'] ) && ! isset( $layer['artworkAttachmentId'] ), 'No cut-line snapshot or attachment' );
foreach ( [ 'uv', 'sublimation', 'embroidery' ] as $method ) {
	$area->print_method = $method;
	check( [] === OC_Render_Spec::build( 1, [] )['areas'][10]['layers'], 'Cut line excluded from ' . $method );
}
$area->print_method = 'engraving';
$text               = clone $cut;
$text->id           = 21;
$text->type         = 'text';
$text->settings     = '{}';
OC_DB::$layers[]    = $text;
foreach ( [ [ 20 => [] ], [ 21 => [ 'type' => 'cut_line' ] ], [ 21 => [ 'cutLineSvg' => $svg ] ], [ 21 => [ 'settings' => [ 'cutLineSvg' => $svg ] ] ] ] as $tampered ) {
	check( is_wp_error( OC_Cart::normalise_v2_layers( 1, 0, 1, $tampered ) ), 'Reject tampered cart submission' );
}
$context_method = new ReflectionMethod( OC_Frontend::class, 'get_usable_design_context' );
$context        = $context_method->invoke( new OC_Frontend(), 1 );
check( [ 21 ] === array_map( static fn( $row ) => $row->id, $context['layers'] ), 'Public contexts, including variants, exclude private layers' );
$thumb         = new ReflectionMethod( OC_Frontend::class, 'get_design_variant_thumb_layers' );
OC_DB::$layers = [ $cut ];
check( [] === $thumb->invoke( new OC_Frontend(), 1, $area ), 'Private geometry excluded from thumbnail bounds and layers' );
check( [] === OC_Cart::render_spec_layer_map( [ 'renderSpec' => $spec ] ), 'Customer-facing layer metadata excludes cut lines' );
foreach (
	[
		false => 403,
		true  => 400,
	] as $permission => $expected_status
) {
	$GLOBALS['can_manage'] = (bool) $permission;
	try {
		OC_Admin_Products::ajax_upload_cut_line();
		throw new LogicException( 'Invalid upload succeeded' );
	} catch ( Json_Response $response ) {
		check( $expected_status === $response->status, 'Upload requires permission and valid upload' );
	}
}
// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CLI progress goes to STDOUT.
fwrite( STDOUT, "Cut-line backend regressions passed ({$assertions} assertions).\n" );
