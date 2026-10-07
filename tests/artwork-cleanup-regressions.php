<?php
// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited, WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Standalone CLI doubles.
/** Exercise 48-hour / seven-day artwork expiry, reference failures and pagination. */
define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'DAY_IN_SECONDS', 86400 );
$options = $metadata = $deleted = $scheduled = [];
function get_option( $key, $fallback = false ) { return $GLOBALS['options'][ $key ] ?? $fallback; }
function update_option( $key, $value, $autoload = null ) { $GLOBALS['options'][ $key ] = $value; return true; }
function get_post_meta( $id, $key, $single = false ) { return $GLOBALS['metadata'][ $id ][ $key ] ?? ''; }
function get_post_time( $format, $gmt, $id ) { return strtotime( $GLOBALS['wpdb']->attachments[ $id ] . ' UTC' ); }
function delete_post_meta( $id, $key ) { unset( $GLOBALS['metadata'][ $id ][ $key ] ); }
function absint( $value ) { return abs( (int) $value ); }
function wp_delete_attachment( $id, $force ) { $GLOBALS['deleted'][] = $id; unset( $GLOBALS['wpdb']->attachments[ $id ] ); }
function wp_next_scheduled( $hook ) { return $GLOBALS['scheduled'][ $hook ] ?? false; }
function wp_schedule_single_event( $time, $hook ) { $GLOBALS['scheduled'][ $hook ] = $time; }
class OC_Logger { public static function warning( $message ) {} }
class wpdb {
	public $posts = 'wp_posts';
	public $postmeta = 'wp_postmeta';
	public $usermeta = 'wp_usermeta';
	public $prefix = 'wp_';
	public $last_error = '';
	public $attachments = [];
	public $orders = [];
	public $carts = [];
	public $late_orders = [];
	public $fail = '';
	public $queries = [];
	public function prepare( $sql, ...$args ) { return [ $sql, $args ]; }
	public function esc_like( $value ) { return addcslashes( $value, '_%\\' ); }
	public function get_col( $query ) {
		[ $sql, $args ] = $query;
		$this->queries[] = $sql;
		$this->last_error = '';
		if ( str_contains( $sql, 'SELECT DISTINCT p.ID' ) ) {
			[ $cutoff, $cursor, $limit ] = $args;
			return array_slice( array_keys( array_filter( $this->attachments, fn( $date, $id ) => $id > $cursor && $date < $cutoff, ARRAY_FILTER_USE_BOTH ) ), 0, $limit );
		}
		if ( 'batch' === $this->fail ) { $this->last_error = 'Unavailable'; return null; }
		return str_contains( $sql, 'woocommerce_order_itemmeta' ) ? $this->orders : $this->carts;
	}
	public function get_var( $query ) {
		[ $sql, $args ] = $query;
		$this->queries[] = $sql;
		$this->last_error = '';
		if ( str_contains( $sql, 'SHOW TABLES' ) ) { return 'wp_woocommerce_sessions'; }
		if ( 'recheck' === $this->fail ) { $this->last_error = 'Unavailable'; return null; }
		$payloads = str_contains( $sql, 'woocommerce_order_itemmeta' ) ? array_merge( $this->orders, $this->late_orders ) : $this->carts;
		foreach ( $payloads as $payload ) {
			foreach ( $args as $pattern ) {
				if ( is_string( $pattern ) && str_contains( $payload, stripslashes( trim( $pattern, '%' ) ) ) ) { return 1; }
			}
		}
		return null;
	}
}
require ABSPATH . 'includes/class-oc-file-cleanup.php';
function check( $condition, $message ) { if ( ! $condition ) { throw new RuntimeException( $message ); } }
function reset_artwork_test() {
	$GLOBALS['options'] = $GLOBALS['metadata'] = $GLOBALS['deleted'] = $GLOBALS['scheduled'] = [];
	$GLOBALS['wpdb'] = new wpdb();
}
reset_artwork_test();
$old = gmdate( 'Y-m-d H:i:s', time() - 49 * 3600 );
$wpdb->attachments = [ 1 => gmdate( 'Y-m-d H:i:s', time() - 47 * 3600 ), 2 => $old, 3 => $old, 4 => $old ];
$wpdb->orders = [ serialize( [ 'attachmentId' => 3 ] ) ];
$wpdb->late_orders = [ serialize( [ 'attachmentId' => 4 ] ) ];
OC_File_Cleanup::cleanup_customer_artwork();
check( [ 2 ] === $deleted, 'Delete old unordered uploads, retain fresh uploads, existing orders and orders appearing at recheck.' );
check( 0 === $options['oc_artwork_cleanup_cursor'], 'Reset cursor at end of a short page.' );
reset_artwork_test();
$wpdb->attachments = [ 5 => $old, 6 => gmdate( 'Y-m-d H:i:s', time() - 8 * DAY_IN_SECONDS ), 7 => gmdate( 'Y-m-d H:i:s', time() - 8 * DAY_IN_SECONDS ) ];
$wpdb->carts = [ serialize( [ 'attachmentId' => 5 ] ), serialize( [ 'attachmentId' => 6 ] ) ];
$wpdb->orders = [ serialize( [ 'attachmentId' => 7 ] ) ];
OC_File_Cleanup::cleanup_customer_artwork();
check( [ 6 ] === $deleted, 'Carts extend retention to seven days, while orders remain protected beyond seven days.' );
foreach ( [ 'batch', 'recheck' ] as $failure ) {
	reset_artwork_test();
	$wpdb->attachments = [ 2 => $old ];
	$wpdb->fail = $failure;
	OC_File_Cleanup::cleanup_customer_artwork();
	check( [] === $deleted, 'Database uncertainty must retain artwork: ' . $failure );
}
reset_artwork_test();
$wpdb->attachments = [ 10 => $old, 11 => $old, 12 => $old ];
$metadata = [
	10 => [ '_oc_print_derivative_attachment_id' => 11, '_oc_artwork_preview_attachment_id' => 12 ],
	11 => [ '_oc_artwork_parent_id' => 10 ],
	12 => [ '_oc_artwork_parent_id' => 10 ],
];
$wpdb->orders = [ serialize( [ 'attachmentId' => 12 ] ) ];
OC_File_Cleanup::cleanup_customer_artwork();
check( [] === $deleted, 'An order referencing a preview must protect the original and sibling print derivative.' );
reset_artwork_test();
$wpdb->attachments = array_fill_keys( range( 1, 101 ), $old );
OC_File_Cleanup::cleanup_customer_artwork();
check( 100 === count( $deleted ) && 100 === $options['oc_artwork_cleanup_cursor'], 'Large backlogs must use bounded pages.' );
check( isset( $scheduled['oc_artwork_cleanup_batch'] ), 'Full pages must schedule a continuation.' );
OC_File_Cleanup::cleanup_customer_artwork();
check( 101 === count( $deleted ) && 0 === $options['oc_artwork_cleanup_cursor'], 'Continuation must finish the backlog and reset.' );
echo "Artwork cleanup regressions passed.\n";
