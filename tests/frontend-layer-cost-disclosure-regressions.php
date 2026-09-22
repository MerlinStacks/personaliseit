<?php
/** Run: php tests/frontend-layer-cost-disclosure-regressions.php */
define( 'ABSPATH', dirname( __DIR__ ) . '/' );
set_error_handler( static function ( $severity, $message, $file, $line ) { throw new ErrorException( $message, 0, $severity, $file, $line ); } );
function __( $text, $domain = '' ) { return $text; }
function esc_html( $text ) { return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); }
function sanitize_key( $text ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( $text ) ); }
function sanitize_textarea_field( $text ) { return strip_tags( $text ); }
function sanitize_text_field( $text ) { return strip_tags( $text ); }
function sanitize_hex_color( $text ) { return $text; }
function esc_url_raw( $text ) { return $text; }
function absint( $value ) { return abs( (int) $value ); }
function wc_get_price_decimals() { return 2; }
function wc_price( $amount ) { return '$' . number_format( $amount, 2, '.', '' ); }
function get_option( $key ) { return $GLOBALS['display']; }
function wc_tax_enabled() { return $GLOBALS['taxable']; }
function wc_get_product( $id ) {
	return new class( $id ) {
		public function __construct( private int $id ) {}
		public function is_taxable() { return $GLOBALS['taxable']; }
		public function get_tax_class() { return 200 === $this->id ? 'reduced' : ''; }
	};
}
function WC() {
	return (object) [
		'customer' => new class { public function get_is_vat_exempt() { return $GLOBALS['exempt']; } },
		'countries' => new class {
			public function inc_tax_or_vat() { return 'incl. tax'; }
			public function ex_tax_or_vat() { return 'excl. tax'; }
		},
	];
}
class WC_Tax {
	public static function get_rates( $class ) { return [ 'reduced' === $class ? 0.05 : 0.2 ]; }
	public static function calc_tax( $amount, $rates, $inclusive ) {
		if ( $inclusive ) { throw new RuntimeException( 'Fees must be exclusive of tax.' ); }
		return [ $amount * $rates[0] ];
	}
}
require_once ABSPATH . 'includes/frontend/class-oc-cart.php';
require_once ABSPATH . 'includes/frontend/class-oc-frontend.php';
$assertions = 0;
function check( $condition, $message ) {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
	++$GLOBALS['assertions'];
}
function layer( $type = 'text', $settings = [], $properties = [] ) {
	return (object) array_replace( [ 'id' => 1, 'area_id' => 1, 'label' => 'Name <script>', 'type' => $type, 'settings' => array_replace( [ 'additional_cost_enabled' => true, 'additional_cost' => 10, 'link_group' => 'shared' ], $settings ) ], $properties );
}
$areas = [ (object) [ 'id' => 1, 'label' => 'Front & back' ], (object) [ 'id' => 2, 'visible' => 0 ] ];
$method = new ReflectionMethod( OC_Frontend::class, 'layer_costs_html' );
$render = static fn ( $layers, $product = 100 ) => $method->invoke( null, $areas, $layers, $product );
foreach ( [ [ 'incl', true, false, 100, '$12.00' ], [ 'excl', true, false, 100, '$10.00' ], [ 'incl', true, true, 100, '$10.00' ], [ 'incl', false, false, 100, '$10.00' ], [ 'incl', true, false, 200, '$10.50' ] ] as [ $display, $taxable, $exempt, $product, $expected ] ) {
	check( str_contains( $render( [ layer() ], $product ), $expected ), 'Layer fee respects product tax display/exemption.' );
	check( str_contains( OC_Frontend::surcharge_html( (object) [ 'flat_rate' => 10 ], $product ), $expected ), 'Base fee retains matching tax semantics.' );
}
foreach ( [ 'text', 'textarea', 'image', 'clipmask' ] as $type ) {
	check( str_contains( $render( [ layer( $type ) ] ), 'per item' ), 'Supported layer disclosed.' );
}
foreach ( [ layer( 'clipart' ), layer( 'ai_image' ), layer( 'text', [], [ 'locked' => 1 ] ), layer( 'text', [], [ 'visible' => 0 ] ), layer( 'text', [], [ 'area_id' => 2 ] ), layer( 'text', [], [ 'area_id' => 99 ] ), layer( 'image', [ 'allow_image_change' => false ] ), layer( 'clipmask', [ 'allow_image_change' => false ] ), layer( 'text', [ 'additional_cost_enabled' => false ] ), layer( 'text', [ 'additional_cost' => 0 ] ), layer( 'text', [ 'additional_cost' => -1 ] ) ] as $excluded ) {
	check( '' === $render( [ $excluded ] ), 'Ineligible layer excluded.' );
}
$html = $render( [ layer(), layer( 'text', [ 'additional_cost' => 20 ], [ 'id' => 2 ] ) ] );
check( 2 === substr_count( $html, '<li>' ) && str_contains( $html, '$12.00' ) && str_contains( $html, '$24.00' ), 'Linked layers retain each individual price.' );
check( str_contains( $html, '&lt;script&gt;' ) && str_contains( $html, 'Front &amp; back' ), 'Labels are escaped.' );
check( '' === OC_Frontend::surcharge_html( (object) [ 'flat_rate' => 0 ], 100 ) && '' !== $html, 'Layer disclosure independent of base fee.' );
echo "Passed {$assertions} frontend layer-cost disclosure assertions.\n";
