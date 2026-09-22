<?php
/** Server-side optional layer fee regressions. Run: php tests/layer-cost-regressions.php */
define( 'ABSPATH', dirname( __DIR__ ) . '/' );
set_error_handler( static function ( $severity, $message, $file, $line ) { throw new ErrorException( $message, 0, $severity, $file, $line ); } );
function __( $text, $domain = '' ) { return $text; }
function esc_html__( $text, $domain = '' ) { return $text; }
function esc_html( $text ) { return $text; }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( $value ) ); }
function sanitize_text_field( $value ) { return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( $value ) ) ); }
function sanitize_textarea_field( $value ) { return trim( strip_tags( $value ) ); }
function sanitize_hex_color( $value ) { return preg_match( '/^#[a-f0-9]{6}$/i', $value ) ? $value : null; }
function esc_url_raw( $value ) { return $value; }
function wp_json_encode( $value ) { return json_encode( $value ); }
function get_post_mime_type( $id ) { return 'image/png'; }
function get_post_meta( $id, $key, $single = true ) { return $GLOBALS['meta'][ $id ][ $key ] ?? ''; }
function wc_get_price_decimals() { return 2; }
function wc_format_decimal( $value, $decimals = 2 ) { return number_format( $value, $decimals, '.', '' ); }
function is_admin() { return false; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
class WP_Error {
	public function __construct( public string $code, public string $message ) {}
	public function get_error_message() { return $this->message; }
}
class OC_DB {
	public static array $layers = [];
	public static function get_assignment_for_product( ...$args ) { return (object) [ 'design_id' => 1 ]; }
	public static function assignment_allows_design( $assignment, $id ) { return 1 === $id; }
	public static function get_design( $id ) { return (object) [ 'id' => 1, 'name' => 'Design', 'active' => 1, 'flat_rate' => 3.5 ]; }
	public static function get_design_print_areas( $id ) { return [ (object) [ 'id' => 1, 'visible' => 1 ], (object) [ 'id' => 2, 'visible' => 0 ] ]; }
	public static function get_design_layers( $id ) { return self::$layers; }
	public static function get_fonts( $active ) { return []; }
	public static function get_colours( $active ) { return []; }
	public static function get_image_filters( $active ) { return [ (object) [ 'id' => 7, 'filter_key' => 'ai', 'value' => 1 ] ]; }
}
class OC_Upload_Handler {
	public static function admin_default_attachment_is_valid( $id ) { return 10 === $id; }
	public static function attachment_is_accepted( $id, ...$args ) { return in_array( $id, [ 20, 21, 22 ], true ); }
	public static function attachment_primary_context( $id ) { return null; }
}
class OC_Render_Spec {
	public static function build( ...$args ) { return []; }
}
class WC_Cart {
	public array $fees = [];
	public function __construct( public array $items ) {}
	public function get_cart() { return $this->items; }
	public function fees_api() { return $this; }
	public function add_fee( $fee ) { $this->fees[ $fee['id'] ] = $fee; return (object) $fee; }
}
class WC_Order {}
class WC_Order_Item_Product {
	public array $meta = [];
	public function update_meta_data( $key, $value ) { $this->meta[ $key ] = $value; }
}
require_once ABSPATH . 'includes/frontend/class-oc-cart.php';
$assertions = 0;
function check( $condition, $message ) {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
	++$GLOBALS['assertions'];
}
function layer( $id = 1, $type = 'text', $settings = [], $properties = [] ) {
	return (object) array_replace( [ 'id' => $id, 'area_id' => 1, 'type' => $type, 'label' => 'Layer ' . $id, 'visible' => 1, 'locked' => 0, 'settings' => array_replace( [ 'additional_cost_enabled' => true, 'additional_cost' => 2.25, 'default_text' => 'Your name' ], $settings ) ], $properties );
}
function normalise( $input ) { return OC_Cart::normalise_v2_layers( 100, 0, 1, $input ); }
function amount( $input ) {
	$result = normalise( $input );
	check( ! is_wp_error( $result ), 'Expected valid normalisation' );
	return (float) array_sum( array_column( $result['layer_costs'], 'amount' ) );
}
foreach ( [ 'text', 'textarea', 'image', 'clipmask' ] as $type ) {
	$settings = OC_Cart::normalise_layer_settings( [], $type );
	check( false === $settings['additional_cost_enabled'] && 0.0 === $settings['additional_cost'], 'Old designs default to no fee' );
}
foreach ( [ -2, '-1.20', [], true, INF, NAN, '1e999', 'money', '2junk' ] as $invalid ) {
	check( 0.0 === OC_Cart::normalise_layer_settings( [ 'additional_cost' => $invalid ], 'text' )['additional_cost'], 'Reject invalid money' );
}
check( 2.35 === OC_Cart::normalise_layer_settings( '{"additional_cost":"2.345"}', 'text' )['additional_cost'], 'Round money to currency precision' );
foreach ( [ 'ai_image', 'spotify', 'lineart', 'clipart', 'night_sky', 'mask', 'cut_line' ] as $type ) {
	check( ! OC_Cart::normalise_layer_settings( [ 'additional_cost_enabled' => true ], $type )['additional_cost_enabled'], 'Unsupported type cannot charge' );
}
foreach ( [ 'text', 'textarea' ] as $type ) {
	OC_DB::$layers = [ layer( 1, $type, [ 'default_text' => "Your\r\nname" ] ) ];
	foreach ( [ [], [ 'value' => '' ], [ 'value' => " \t\n " ], [ 'value' => ' Your name ' ], [ 'value' => "Your\nname" ], [ 'value' => '<b>Your</b> name' ] ] as $input ) {
		check( 0.0 === amount( [ 1 => $input ] ), 'Empty and sanitised multiline defaults are free' );
	}
	check( 2.25 === amount( [ 1 => [ 'value' => 'Alice', 'additional_cost' => -999, 'settings' => [ 'additional_cost_enabled' => false ] ] ] ), 'Only saved amounts are trusted' );
}
foreach ( [ [ 'additional_cost_enabled' => false ], [ 'additional_cost_enabled' => 'false' ], [ 'additional_cost' => -3 ], [ 'additional_cost' => 0 ] ] as $settings ) {
	OC_DB::$layers = [ layer( 1, 'text', $settings ) ];
	check( 0.0 === amount( [ 1 => [ 'value' => 'Alice' ] ] ), 'Disabled/zero/negative costs are free' );
}
OC_DB::$layers = [ layer( 1, 'text', [], [ 'locked' => 1 ] ), layer( 2, 'text', [], [ 'visible' => 0 ] ), layer( 3, 'text', [], [ 'area_id' => 2 ] ) ];
check( 0.0 === amount( [ 1 => [ 'value' => 'Alice' ] ] ), 'Locked layers and hidden layers/areas never charge' );
check( is_wp_error( normalise( [ 2 => [ 'value' => 'Alice' ] ] ) ), 'Hidden submission rejected' );

foreach ( [ 'image', 'clipmask' ] as $type ) {
	OC_DB::$layers = [ layer( 1, $type, [ 'default_attachment_id' => 10 ] ) ];
	foreach ( [ [], [ 'attachmentId' => 0 ], [ 'attachmentId' => 10, 'sourceAttachmentId' => 10, 'imageCrop' => 60 ] ] as $input ) {
		check( 0.0 === amount( [ 1 => $input ] ), 'Absent/default/cropped default artwork is free' );
	}
	check( 2.25 === amount( [ 1 => [ 'attachmentId' => 20 ] ] ), 'Validated custom photo charges' );
	foreach ( [ 0, 10, 21 ] as $forged_source ) {
		check( is_wp_error( normalise( [ 1 => [ 'attachmentId' => 20, 'sourceAttachmentId' => $forged_source ] ] ) ), 'Cannot forge default/empty/unrelated source' );
	}
	check( is_wp_error( normalise( [ 1 => [ 'attachmentId' => 999 ] ] ) ), 'Unvalidated upload rejected' );
	$GLOBALS['meta'][21] = [ '_oc_ai_filter_id' => 7, '_oc_ai_filter_source_id' => 10 ];
	$GLOBALS['meta'][22] = [ '_oc_ai_filter_id' => 7, '_oc_ai_filter_source_id' => 20 ];
	check( 0.0 === amount( [ 1 => [ 'attachmentId' => 21, 'sourceAttachmentId' => 10, 'imageCrop' => 25 ] ] ), 'Verified default derivative is free' );
	check( 0.0 === amount( [ 1 => [ 'attachmentId' => 21 ] ] ), 'Omitted source resolved from server provenance' );
	check( 2.25 === amount( [ 1 => [ 'attachmentId' => 22, 'sourceAttachmentId' => 20 ] ] ), 'Verified custom derivative charges' );
	check( is_wp_error( normalise( [ 1 => [ 'attachmentId' => 22, 'sourceAttachmentId' => 10 ] ] ) ), 'Custom derivative cannot claim default source' );
}
OC_DB::$layers = [ layer( 1, 'image', [ 'default_attachment_id' => 10, 'image_filter_ids' => [ 7 ] ] ) ];
check( 0.0 === amount( [ 1 => [ 'attachmentId' => 21, 'sourceAttachmentId' => 10, 'imageFilterId' => 7 ] ] ), 'Selected verified AI effect on default is free' );
check( 2.25 === amount( [ 1 => [ 'attachmentId' => 22, 'sourceAttachmentId' => 20, 'imageFilterId' => 7 ] ] ), 'Selected verified AI effect on custom photo charges' );
OC_DB::$layers = [ layer( 1, 'image', [ 'default_attachment_id' => 10, 'allow_image_change' => false ] ) ];
check( 0.0 === amount( [ 1 => [ 'attachmentId' => 20 ] ] ), 'Fixed default image never charges' );

OC_DB::$layers = [ layer( 1, 'text', [ 'link_group' => 'name' ] ), layer( 2, 'text', [ 'link_group' => 'name', 'additional_cost' => 1.5 ] ) ];
$payload = [ 'v' => 2, 'designId' => 1, 'layers' => [ 1 => [ 'value' => 'Alice' ] ], '_oc_flat_rate' => 0, 'layer_costs' => [] ];
$raw = wp_json_encode( $payload );
check( true === OC_Cart::validate_v2_submission( 100, 0, $raw ), 'Validation cache succeeds' );
$integration = new OC_Cart();
$data = $integration->add_cart_item_data( [ '_oc_submission_raw' => $raw, '_oc_flat_rate' => -100, '_oc_layer_costs' => [ 'forged' ] ], 100, 0 );
check( 7.25 === $data['_oc_flat_rate'], 'Both linked layers charged and base design fee preserved through cached add-to-cart' );
check( 2 === count( $data['_oc_layer_costs'] ), 'One audit entry per configured linked layer' );
$data['quantity'] = 3;
$cart = new WC_Cart( [ 'line' => $data ] );
$integration->add_flat_rate_fee( $cart );
check( 21.75 === array_values( $cart->fees )[0]['amount'], 'Combined per-unit fee multiplied by quantity exactly once' );
$cart->items['line']['quantity'] = 2;
$integration->add_flat_rate_fee( $cart );
check( 14.5 === array_values( $cart->fees )[0]['amount'], 'Quantity changes recalculate fee' );
$order_item = new WC_Order_Item_Product();
$integration->save_to_order_item( $order_item, 'line', $data, new WC_Order() );
check( $data['_oc_layer_costs'] === $order_item->meta['_oc_layer_costs'], 'Order keeps fee breakdown snapshot' );
check( '21.75' === $order_item->meta['_oc_personalisation_fee_total'], 'Order audit includes quantity total' );
check( in_array( '_oc_layer_costs', $integration->hidden_order_item_meta( [] ), true ), 'Audit breakdown remains internal' );
$payload['layers'][1]['value'] = 'Your name';
$data = $integration->add_cart_item_data( [ '_oc_submission_raw' => wp_json_encode( $payload ) ], 100, 0 );
check( 3.5 === $data['_oc_flat_rate'] && [] === $data['_oc_layer_costs'], 'Uncached default submission retains only base design fee' );
OC_DB::$layers = [ layer( 1, 'text', [ 'required' => true ] ) ];
check( is_wp_error( normalise( [ 1 => [ 'value' => '' ] ] ) ), 'Required empty value fails validation before fee calculation' );
print "Layer cost regressions: {$assertions} assertions passed.\n";
