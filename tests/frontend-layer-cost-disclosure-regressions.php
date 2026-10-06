<?php
/** Run: php tests/frontend-layer-cost-disclosure-regressions.php */
define( 'ABSPATH', dirname( __DIR__ ) . '/' );
// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Fail standalone CLI tests on PHP warnings.
set_error_handler(
	static function ( $severity, $message, $file, $line ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Preserve raw CLI diagnostic details.
		throw new ErrorException( $message, 0, $severity, $file, $line );
	}
);
function __( $text, $domain = '' ) {
	return $text;
}
function esc_html( $text ) {
	return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
}
function sanitize_key( $text ) {
	return preg_replace( '/[^a-z0-9_-]/', '', strtolower( $text ) );
}
function sanitize_textarea_field( $text ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- WordPress is unavailable in this standalone stub.
	return strip_tags( $text );
}
function sanitize_text_field( $text ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- WordPress is unavailable in this standalone stub.
	return strip_tags( $text );
}
function sanitize_hex_color( $text ) {
	return $text;
}
function esc_url_raw( $text ) {
	return $text;
}
function absint( $value ) {
	return abs( (int) $value );
}
function wc_get_price_decimals() {
	return 2;
}
function wc_price( $amount ) {
	return '$' . number_format( $amount, 2, '.', '' );
}
function get_option( $key ) {
	return $GLOBALS['display'];
}
function wc_tax_enabled() {
	return $GLOBALS['taxable'];
}
function wc_get_product( $id ) {
	return new class( $id ) {
		public function __construct( private int $id ) {}
		public function is_taxable() {
			return $GLOBALS['taxable'];
		}
		public function get_tax_class() {
			return 200 === $this->id ? 'reduced' : '';
		}
	};
}
// phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid -- Match WooCommerce's public accessor in this standalone stub.
function WC() {
	return (object) [
		'customer'  => new class() {
			public function get_is_vat_exempt() {
				return $GLOBALS['exempt'];
			}
		},
		'countries' => new class() {
			public function inc_tax_or_vat() {
				return 'incl. tax';
			}
			public function ex_tax_or_vat() {
				return 'excl. tax';
			}
		},
	];
}
class WC_Tax {
	public static function get_rates( $tax_class ) {
		return [ 'reduced' === $tax_class ? 0.05 : 0.2 ];
	}
	public static function calc_tax( $amount, $rates, $inclusive ) {
		if ( $inclusive ) {
			throw new RuntimeException( 'Fees must be exclusive of tax.' );
		}
		return [ $amount * $rates[0] ];
	}
}
require_once ABSPATH . 'includes/frontend/class-oc-cart.php';
require_once ABSPATH . 'includes/frontend/class-oc-frontend.php';
$assertions = 0;
function check( $condition, $message ) {
	if ( ! $condition ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Assertion failures are plain-text CLI diagnostics.
		throw new RuntimeException( $message );
	}
	++$GLOBALS['assertions'];
}
// Layer fees now appear inline in the template. Retain coverage of the public
// base-surcharge formatter rather than the retired separate layer-fee list.
foreach (
	[
		[ 'incl', true, false, 100, '$12.00' ],
		[ 'excl', true, false, 100, '$10.00' ],
		[ 'incl', true, true, 100, '$10.00' ],
		[ 'incl', false, false, 100, '$10.00' ],
		[ 'incl', true, false, 200, '$10.50' ],
	] as [ $display, $taxable, $exempt, $product, $expected ]
) {
	check( str_contains( OC_Frontend::surcharge_html( (object) [ 'flat_rate' => 10 ], $product ), $expected ), 'Base fee retains matching tax semantics.' );
}
check( '' === OC_Frontend::surcharge_html( (object) [ 'flat_rate' => 0 ], 100 ), 'Zero base fees are omitted.' );
check( '' === OC_Frontend::surcharge_html( (object) [ 'flat_rate' => -10 ], 100 ), 'Negative base fees are omitted.' );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain-text CLI summary, not HTML output.
echo "Passed {$assertions} frontend surcharge disclosure assertions.\n";
