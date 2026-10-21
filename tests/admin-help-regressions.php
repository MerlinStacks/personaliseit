<?php
/** Dependency-free rendering regressions: php tests/admin-help-regressions.php. */
define( 'ABSPATH', dirname( __DIR__ ) . '/' );
function esc_attr( $value ) {
	return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
}
function esc_html( $value ) {
	return esc_attr( $value );
}
function esc_attr_e( $value, $domain ) {
	echo esc_attr( $value );
}
function sanitize_html_class( $value ) {
	return preg_replace( '/[^A-Za-z0-9_-]/', '', $value );
}
function wp_unique_id( $prefix = '' ) {
	static $counter = 0;
	return $prefix . ( ++$counter );
}
require ABSPATH . 'includes/class-oc-tooltips.php';
ob_start();
OC_Tooltips::render( 'same" onclick="bad', '<script>alert("x")</script>' );
OC_Tooltips::render( 'same" onclick="bad', 'Second hint' );
$html = ob_get_clean();
preg_match_all( '/aria-controls="([^"]+)"/', $html, $controls );
foreach ( [
	2 === count( array_unique( $controls[1] ) ),
	! str_contains( $html, 'aria-haspopup' ),
	! str_contains( $html, '<script>' ),
	str_contains( $html, '&lt;script&gt;' ),
	! str_contains( $html, ' onclick="bad' ),
	2 === substr_count( $html, 'aria-expanded="false"' ),
	2 === substr_count( $html, ' hidden>' ),
] as $passed ) {
	if ( ! $passed ) {
		throw new RuntimeException( 'Help disclosure rendering regression.' );
	}
}
foreach ( $controls[1] as $hint_id ) {
	if ( ! str_contains( $html, 'id="' . $hint_id . '" hidden' ) ) {
		throw new RuntimeException( 'Help control must reference its own hint.' );
	}
}
echo "Admin help rendering regressions passed.\n";
