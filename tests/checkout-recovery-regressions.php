<?php
// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited, WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Isolated CLI test doubles; no HTML output.
/** Checkout lifecycle and recovery retention checks with isolated WordPress boundaries. */
define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'DAY_IN_SECONDS', 86400 );

$options = [];
$hooks   = [];
$orders  = [];
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['hooks'][ $hook ][ $priority ][] = $callback;
}
function add_filter( ...$args ) {}
function do_action( $hook, ...$args ) {
	$priorities = $GLOBALS['hooks'][ $hook ] ?? [];
	ksort( $priorities );
	foreach ( $priorities as $callbacks ) {
		foreach ( $callbacks as $callback ) {
			$callback( ...$args );
		}
	}
}
function get_current_user_id() {
	return $GLOBALS['user_id'] ?? 1;
}
function get_option( $key, $fallback = false ) {
	return $GLOBALS['options'][ $key ] ?? $fallback;
}
function add_option( $key, $value, ...$args ) {
	if ( isset( $GLOBALS['options'][ $key ] ) ) {
		return false;
	}
	$GLOBALS['options'][ $key ] = $value;
	return true;
}
function update_option( $key, $value, ...$args ) {
	$GLOBALS['options'][ $key ] = $value;
	return true;
}
function delete_option( $key ) {
	unset( $GLOBALS['options'][ $key ] );
	return true;
}
function wp_json_encode( $data ) {
	return json_encode( $data ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- WordPress boundary double.
}
function maybe_serialize( $data ) {
	return serialize( $data );
}
function wp_cache_delete( ...$args ) {}
function wp_next_scheduled( ...$args ) {
	return false;
}
function wp_schedule_single_event( ...$args ) {
	throw new LogicException( 'Draft scheduled a retry' );
}
function wc_get_order( $id ) {
	return $GLOBALS['orders'][ $id ] ?? false;
}
function wc_get_order_id_by_order_item_id( $id ) {
	return 7;
}
function current_time( ...$args ) {
	return gmdate( 'Y-m-d H:i:s' );
}
function absint( $value ) {
	return abs( (int) $value );
}
class WC_Order_Item_Product {
	public function get_meta( ...$args ) {
		return [ 'front' => [ 'text' => 'Final artwork' ] ];
	}
	public function get_id() {
		return 11;
	}
	public function get_product_id() {
		return 12;
	}
	public function get_variation_id() {
		return 0;
	}
	public function get_name() {
		return 'Custom product';
	}
	public function get_quantity() {
		return 1;
	}
}
class WC_Order {
	public string $status = 'checkout-draft';
	public function get_status() {
		return $this->status;
	}
	public function get_id() {
		return 7;
	}
	public function get_items() {
		return [ new WC_Order_Item_Product() ];
	}
	public function get_billing_email() {
		return 'customer@example.test';
	}
	public function get_total() {
		return '25.00';
	}
}
class OC_DB {
	public static bool $available = true;
	public static function print_pipeline_available() {
		return self::$available;
	}
}
class OC_Release_Cache {
	public static function register() {}
}
require ABSPATH . 'includes/class-oc-print-generator.php';
require ABSPATH . 'includes/class-oc-webhooks.php';
require ABSPATH . 'includes/class-oc-autosave.php';
require ABSPATH . 'includes/class-oc-plugin.php';
class OC_Checkout_Test_Generator extends OC_Print_Generator {
	public array $generated = [];
	public function generate_for_order( WC_Order $order ): void {
		$this->generated[] = $order->get_status();
	}
}
class OC_Checkout_Test_Webhooks extends OC_Webhooks {
	public array $sent = [];
	public function send( string $event, array $payload ): void {
		$this->sent[] = [ $event, $payload ];
	}
}
function check( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

$order     = new WC_Order();
$orders[7] = $order;
$generator = new OC_Checkout_Test_Generator();
$webhooks  = new OC_Checkout_Test_Webhooks();
$generator->register();
$webhooks->register();
foreach ( [ true, false ] as $available ) {
	OC_DB::$available = $available;
	// The base implementation must exit before any print/schema/order-item writes.
	( new OC_Print_Generator() )->generate_for_order( $order );
	do_action( 'woocommerce_new_order', 7, $order );
	do_action( 'added_order_item_meta', 1, 11, '_oc_customisation', [] );
	do_action( 'shutdown' );
	$generator->retry_order_generation( 7 );
	check( [] === $generator->generated, 'Draft must not reach generation through shutdown or recovery' );
}
OC_DB::$available = true;
$order->status    = 'pending';
do_action( 'woocommerce_store_api_checkout_order_processed', $order );
check( [ 'pending' ] === $generator->generated, 'Submitted Block checkout triggers generation' );
check( 'order.customisation.created' === $webhooks->sent[0][0], 'Block checkout sends the created webhook' );
check( 'Final artwork' === $webhooks->sent[0][1]['items'][0]['customisation']['front']['text'], 'Webhook contains final customisation' );
do_action( 'woocommerce_checkout_order_created', $order );
check( 2 === count( $generator->generated ) && 2 === count( $webhooks->sent ), 'Classic checkout remains wired' );
$generator->defer_order_generation( 7, $order );
$generator->generate_deferred_orders();
check( 3 === count( $generator->generated ), 'Submitted admin/API orders retain deferred generation' );

$wpdb    = new class() {
	public string $options      = 'wp_options';
	public string $last_error   = '';
	public array $rows          = [];
	public bool $fail           = false;
	public mixed $before_delete = null;
	public array $last_select   = [];
	public function esc_like( $value ) {
		return addcslashes( $value, '_%\\' );
	}
	public function prepare( $sql, ...$args ) {
		return [ $sql, $args ];
	}
	public function get_results( $query ) {
		$this->last_select = $query;
		$this->last_error  = $this->fail ? 'Read failed' : '';
		return $this->fail ? null : $this->rows;
	}
	public function query( $query ) {
		if ( str_contains( $query[0], 'LIMIT 500' ) ) {
			return 0;
		}
		[ $key, $expected ] = $query[1];
		if ( is_callable( $this->before_delete ) ) {
			$callback            = $this->before_delete;
			$this->before_delete = null;
			$callback( $key );
		}
		if ( serialize( get_option( $key, null ) ) !== $expected ) {
			return 0;
		}
		delete_option( $key );
		return 1;
	}
	public function get_col( $query ) {
		$prefixes = array_map( static fn( $value ) => rtrim( stripslashes( $value ), '%' ), $query[1] );
		return array_values(
			array_filter(
				array_keys( $GLOBALS['options'] ),
				static function ( $key ) use ( $prefixes ) {
					foreach ( $prefixes as $prefix ) {
						if ( str_starts_with( $key, $prefix ) ) {
							return true;
						}
					}
					return false;
				}
			)
		);
	}
};
$data    = [
	'state'     => [ 'design' => [ 'name' => 'Recovery' ] ],
	'timestamp' => 123,
	'revision'  => 2,
];
$token   = OC_Autosave::retain_recovery( $data );
$key     = 'oc_recovery_1_' . $token;
$expires = $options[ $key ]['expires_at'];
check( OC_Autosave::recovery( $token ) === $data, 'Recovery preserves the exact original envelope and token' );
check( time() + 30 * DAY_IN_SECONDS - 1 <= $expires, 'Recovery has a 30-day retention window' );
check( OC_Autosave::retain_recovery( $data ) === $token && $expires === $options[ $key ]['expires_at'], 'Unexpired recovery is idempotent without renewal' );
$GLOBALS['user_id'] = 2;
check( null === OC_Autosave::recovery( $token ), 'Recovery stays user scoped' );
$GLOBALS['user_id']            = 1;
$options[ $key ]['expires_at'] = time() - 1;
check( null === OC_Autosave::recovery( $token ), 'Expired recovery is unavailable even before cleanup' );
$wpdb->rows = [
	(object) [
		'option_id'   => 1,
		'option_name' => $key,
	],
];
OC_Autosave::cleanup_recovery();
check( ! isset( $options[ $key ] ), 'Daily cleanup deletes expired recovery' );

// Old copies have no expiry; do not delete them based on their old autosave timestamp.
$options[ $key ] = $data;
OC_Autosave::cleanup_recovery();
check( OC_Autosave::recovery( $token ) === $data && time() < $options[ $key ]['expires_at'], 'Legacy copies receive a grace period, with payloads unchanged' );
$expires = $options[ $key ]['expires_at'];
OC_Autosave::cleanup_recovery();
check( $expires === $options[ $key ]['expires_at'], 'Daily scans do not extend retention' );
$rejected = [
	'format'   => 'overcustomise-rejected-form',
	'complete' => false,
	'received' => [ 'oc_design_name' => 'Old form' ],
];

$rejected_token = hash( 'sha256', wp_json_encode( $rejected ) );
$rejected_key   = 'oc_recovery_1_' . $rejected_token;

$options[ $rejected_key ] = $rejected;
check( OC_Autosave::recovery( $rejected_token ) === $rejected, 'Legacy rejected-form exports also migrate without losing data' );
check( isset( $options[ $rejected_key ]['expires_at'] ), 'Rejected-form exports receive the same retention window' );
$options[ $key ]['expires_at'] = time() - 1;
$wpdb->before_delete           = static function () use ( $data, $token ) {
	check( OC_Autosave::retain_recovery( $data ) === $token, 'Explicit reuse can retain an expired copy again' );
};
OC_Autosave::cleanup_recovery();
check( OC_Autosave::recovery( $token ) === $data, 'Concurrent retention survives cleanup compare-and-delete' );
$wpdb->rows = array_map(
	static fn( $id ) => (object) [
		'option_id'   => $id,
		'option_name' => $key,
	],
	range( 1, 100 )
);
OC_Autosave::cleanup_recovery();
check( 100 === get_option( 'oc_recovery_cleanup_cursor' ) && str_contains( $wpdb->last_select[0], 'LIMIT 100' ), 'Cleanup is bounded and advances a cursor' );
$wpdb->fail = true;
$before     = $options;
OC_Autosave::cleanup_recovery();
check( $before === $options, 'Failed scans preserve recovery and cursor' );
$wpdb->fail = false;

$plugin = ( new ReflectionClass( OC_Plugin::class ) )->newInstanceWithoutConstructor();
( new ReflectionMethod( OC_Plugin::class, 'register_maintenance_hooks' ) )->invoke( $plugin );
check( in_array( [ OC_Autosave::class, 'cleanup_recovery' ], $hooks['oc_daily_file_cleanup'][30], true ), 'Recovery cleanup is wired to daily maintenance' );
$options['unrelated_setting'] = 'keep';
$options['oc_wh_created_7_1'] = '2026-10-17 00:00:00';
$options['oc_chooser_icon_design_7'] = 88;
$options['oc_chooser_icon_clipart_9'] = 99;

$cleanup = new ReflectionMethod( OC_Plugin::class, 'delete_runtime_options' );
$cleanup->invoke( null, false );
check( isset( $options[ $key ] ), 'Deactivation preserves recovery copies' );
check( isset( $options['oc_wh_created_7_1'] ), 'Deactivation preserves webhook deduplication receipts' );
check( 88 === get_option( 'oc_chooser_icon_design_7' ) && 99 === get_option( 'oc_chooser_icon_clipart_9' ), 'Deactivation preserves chooser icon references' );
$cleanup->invoke( null, true );
check( ! isset( $options[ $key ] ) && ! isset( $options['oc_recovery_cleanup_cursor'] ), 'Uninstall removes recovery copies and their cursor' );
check( ! isset( $options['oc_wh_created_7_1'] ), 'Uninstall removes webhook deduplication receipts' );
check( ! isset( $options['oc_chooser_icon_design_7'] ) && ! isset( $options['oc_chooser_icon_clipart_9'] ), 'Uninstall removes design and clipart icon references without deleting media attachments' );
check( 'keep' === $options['unrelated_setting'], 'Uninstall cleanup leaves unrelated options alone' );
echo "Checkout lifecycle and recovery retention regressions passed.\n";
