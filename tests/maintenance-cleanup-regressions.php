<?php
/** Check storefront/worker separation and conservative HPOS-aware history retention. */
define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'DAY_IN_SECONDS', 86400 );
$options = [];
$calls = [];
function get_option( $key, $default = false ) { return $GLOBALS['options'][ $key ] ?? $default; }
function update_option( $key, $value, $autoload = null ) { $GLOBALS['options'][ $key ] = $value; return true; }
function delete_option( $key ) { unset( $GLOBALS['options'][ $key ] ); }
function apply_filters( $hook, $value ) { return $value; }
function wc_get_order( $id ) {
	if ( 30 === $id ) { throw new RuntimeException( 'Store unavailable' ); }
	return 20 === $id ? false : (object) [ 'status' => 'trash' ];
}
class OC_Rest_API { public static function ensure_vdp_storage( $migrate = true ) { $GLOBALS['calls'][] = [ 'vdp', $migrate ]; } }
class OC_Upload_Handler { public static function ensure_private_storage( $force = false, $migrate = true ) { $GLOBALS['calls'][] = [ 'artwork', $migrate ]; } }
class OC_Print_Base { public static function ensure_output_storage_protected() { $GLOBALS['calls'][] = [ 'protection' ]; } }
class OC_Print_Generator { public static function with_output_lock( $order, $item, $callback ) { return $callback(); } }
class OC_Logger { public static function warning( $message ) {} }
$wpdb = new class {
	public $prefix = 'wp_';
	public $options = 'wp_options';
	public $last_error = '';
	public $lock = 1;
	public $deleted = [];
	public $fail = false;
	public function prepare( $sql, ...$args ) { return [ $sql, $args ]; }
	public function esc_like( $s ) { return $s; }
	public function get_var( $query ) { return $this->lock; }
	public function get_results( $query ) {
		if ( $this->fail ) { $this->last_error = 'Database unavailable'; return null; }
		if ( str_contains( $query[0], 'oc_print_queue' ) ) {
			return array_map( fn( $id ) => (object) [ 'id' => $id, 'order_id' => $id, 'order_item_id' => $id ], [ 10, 20, 30 ] );
		}
		return array_map( fn( $id ) => (object) [ 'option_id' => $id, 'option_name' => 'oc_print_generated_emitted_' . $id, 'option_value' => '2020-01-01 00:00:00' ], [ 10, 20, 30 ] );
	}
	public function query( $query ) { $this->deleted[] = $query[1][0]; return 1; }
};
require ABSPATH . 'includes/class-oc-plugin.php';
require ABSPATH . 'includes/class-oc-history-cleanup.php';
function check( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } }
OC_Plugin::maintain_storage();
check( [ [ 'vdp', false ], [ 'artwork', false ], [ 'protection' ] ] === $calls, 'Customer requests must protect storage without migrating files.' );
$calls = [];
$wpdb->lock = 0;
OC_Plugin::migrate_storage();
check( [] === $calls, 'Concurrent migration workers must not both run.' );
$wpdb->lock = 1;
OC_Plugin::migrate_storage();
check( [ [ 'vdp', true ], [ 'artwork', true ] ] === $calls, 'Background worker must resume both migrations.' );
foreach ( [ 10, 20, 30 ] as $id ) { $options[ 'oc_print_generated_emitted_' . $id ] = '2020-01-01 00:00:00'; }
OC_History_Cleanup::run();
check( [ 20 ] === $wpdb->deleted, 'Preserve existing/trashed orders and uncertain reads; remove only deleted-order history.' );
check( false === get_option( 'oc_print_generated_emitted_20' ) && false !== get_option( 'oc_print_generated_emitted_10' ) && false !== get_option( 'oc_print_generated_emitted_30' ), 'Deduplication markers follow the same retention policy.' );
$wpdb->fail = true;
OC_History_Cleanup::run();
check( [ 20 ] === $wpdb->deleted, 'Database failures must not delete history.' );
echo "Maintenance cleanup regressions passed.\n";
