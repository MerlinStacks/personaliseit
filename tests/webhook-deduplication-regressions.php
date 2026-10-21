<?php
// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited, WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Isolated CLI boundaries, not HTML output.
/** Exercise durable order-created deduplication without network or a WordPress installation. */
define( 'ABSPATH', dirname( __DIR__ ) . '/' );
$options   = [];
$scheduled = [];
$requests  = [];
$fail_add  = false;
$on_add    = null;
$http_code = 200;

function get_option( $key, $fallback = false ) {
	return $GLOBALS['options'][ $key ] ?? $fallback;
}
function add_option( $key, $value, ...$args ) {
	if ( is_callable( $GLOBALS['on_add'] ) ) {
		$callback          = $GLOBALS['on_add'];
		$GLOBALS['on_add'] = null;
		$callback();
	}
	if ( $GLOBALS['fail_add'] || isset( $GLOBALS['options'][ $key ] ) ) {
		return false;
	}
	$GLOBALS['options'][ $key ] = $value;
	return true;
}
function update_option( $key, $value, ...$args ) {
	$GLOBALS['options'][ $key ] = $value;
	return true;
}
function wp_cache_delete( ...$args ) {}
function maybe_serialize( $value ) {
	return serialize( $value );
}
function wp_json_encode( $value ) {
	return json_encode( $value ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Boundary double.
}
function absint( $value ) {
	return abs( (int) $value );
}
function sanitize_key( $value ) {
	return $value;
}
function wp_generate_uuid4() {
	return sprintf( '00000000-0000-4000-8000-%012d', ++$GLOBALS['uuid_counter'] );
}
function wp_schedule_single_event( $time, $hook, $args ) {
	$GLOBALS['scheduled'][] = [ $time, $hook, $args ];
	return ! empty( $GLOBALS['schedule_ok'] );
}
function wp_next_scheduled( ...$args ) {
	return false;
}
function current_time( ...$args ) {
	return gmdate( 'Y-m-d H:i:s' );
}
function is_wp_error( $value ) {
	return false;
}
function wp_parse_url( $url ) {
	return parse_url( $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Boundary double.
}
function wp_safe_remote_post( $url, $args ) {
	$GLOBALS['requests'][] = $args;
	return [ 'response' => [ 'code' => $GLOBALS['http_code'] ] ];
}
function wp_remote_retrieve_response_code( $response ) {
	return $response['response']['code'];
}
function wp_remote_retrieve_body( $response ) {
	return '';
}
class OC_Logger {
	public static function error( $message ) {}
	public static function warning( $message ) {}
	public static function info( $message ) {}
}
class OC_DB {
	public static array $webhooks = [];
	public static function get_active_webhooks() {
		return array_values( self::$webhooks );
	}
	public static function get_webhook( $id ) {
		return self::$webhooks[ $id ] ?? null;
	}
}
$wpdb = new class() {
	public string $options    = 'wp_options';
	public string $last_error = '';
	public bool $read_fails   = false;
	public bool $write_fails  = false;
	public function prepare( $sql, ...$args ) {
		return [ $sql, $args ];
	}
	public function get_var( $query ) {
		$this->last_error = $this->read_fails ? 'Read failed' : '';
		return isset( $GLOBALS['options'][ $query[1][0] ] ) ? $query[1][0] : null;
	}
	public function query( $query ) {
		if ( str_starts_with( $query[0], 'INSERT IGNORE INTO' ) ) {
			if ( is_callable( $GLOBALS['on_add'] ) ) {
				$callback          = $GLOBALS['on_add'];
				$GLOBALS['on_add'] = null;
				$callback();
			}
			[ $key, $value ] = $query[1];
			if ( $GLOBALS['fail_add'] ) {
				return false;
			}
			if ( isset( $GLOBALS['options'][ $key ] ) ) {
				return 0; // A conflicting insert never updates a pending/processing job.
			}
			$GLOBALS['options'][ $key ] = unserialize( $value );
			return 1;
		}
		if ( $this->write_fails ) {
			return false;
		}
		[ $sql, $args ] = $query;
		$rename         = str_contains( $sql, 'SET option_name' );
		$update         = str_contains( $sql, 'SET option_value' );
		$key            = $args[ $rename ? 2 : ( $update ? 1 : 0 ) ];
		$expected       = $args[ $rename ? 3 : ( $update ? 2 : 1 ) ];
		if ( serialize( get_option( $key, null ) ) !== $expected ) {
			return 0;
		}
		if ( $rename && isset( $GLOBALS['options'][ $args[0] ] ) ) {
			return false; // Model the unique option_name constraint.
		}
		if ( $rename ) {
			$GLOBALS['options'][ $args[0] ] = $args[1];
			unset( $GLOBALS['options'][ $key ] );
		} elseif ( $update ) {
			$GLOBALS['options'][ $key ] = unserialize( $args[0] );
		} else {
			unset( $GLOBALS['options'][ $key ] );
		}
		return 1;
	}
};
require ABSPATH . 'includes/class-oc-webhooks.php';
function check( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}
function job_key( $order, $destination = 1 ) {
	return 'oc_wh_job_' . hash( 'sha256', 'oc_wh_created_' . $order . '_' . $destination );
}
function send_created( $order = 7 ) {
	( new OC_Webhooks() )->send(
		'order.customisation.created',
		[
			'order_id' => $order,
			'items'    => [ 'original' ],
		]
	);
}
function pending_job_count() {
	return count( array_filter( array_keys( $GLOBALS['options'] ), static fn( $key ) => str_starts_with( $key, 'oc_wh_job_' ) ) );
}
$GLOBALS['uuid_counter'] = 0;
$GLOBALS['schedule_ok']  = true;
OC_DB::$webhooks[1]      = (object) [
	'id'     => 1,
	'events' => '["order.customisation.created","print.files.generated"]',
	'url'    => 'https://8.8.8.8/webhooks',
	'secret' => str_repeat( 's', 32 ),
	'active' => 1,
];

$webhooks = new OC_Webhooks();
send_created();
send_created();
check( 1 === pending_job_count() && 1 === count( $scheduled ), 'Repeated checkout creates one durable job and one scheduled action' );
$original = $options[ job_key( 7 ) ];
$webhooks->send( 'order.customisation.created', [ 'order_id' => 7 ] );
check( $original === $options[ job_key( 7 ) ], 'A repeated callback cannot replace the retained event payload' );
$key       = job_key( 7 );
$http_code = 503;
$webhooks->process_delivery( 1, $key );
$retry = $options[ $key ];
check( 1 === $retry['attempt'] && 'pending' === $retry['status'] && time() < $retry['run_after'], 'Failed HTTP delivery retains the normal backoff' );
send_created();
check( $retry === $options[ $key ] && 2 === count( $scheduled ), 'Repeated checkout never resets an existing retry or adds an action' );
$options[ $key ]['run_after'] = time() - 1;
$http_code                    = 200;
$webhooks->process_delivery( 1, $key );
check( 0 === pending_job_count() && is_string( $options['oc_wh_created_7_1'] ), 'Terminal job becomes a compact receipt outside the recovery prefix' );
check( $requests[0]['headers']['X-OC-Delivery-ID'] === $requests[1]['headers']['X-OC-Delivery-ID'], 'HTTP retries preserve delivery identity' );
check( 1 === preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D', $requests[0]['headers']['X-OC-Delivery-ID'] ), 'New deliveries use random UUID-compatible headers for existing receivers' );
send_created();
check( 0 === pending_job_count() && 2 === count( $requests ), 'Completed event cannot be requeued by checkout retries' );

// Nested calls model two checkout requests racing for the same unique option name.
$on_add = static fn() => send_created( 8 );
send_created( 8 );
check( 1 === pending_job_count(), 'Concurrent enqueue callbacks share one job' );
$before   = count( $scheduled );
$fail_add = true;
send_created( 9 );
check( ! isset( $options[ job_key( 9 ) ] ) && ! isset( $options['oc_wh_created_9_1'] ), 'Persistence failure does not mark the event as emitted' );
$fail_add = false;
send_created( 9 );
check( isset( $options[ job_key( 9 ) ] ) && count( $scheduled ) === $before + 1, 'A later callback can retry a failed persistence attempt' );

// Rename failure retains the claimed job so recovery can retry, never losing the event.
$wpdb->write_fails = true;
$retire            = new ReflectionMethod( OC_Webhooks::class, 'compare_and_delete_delivery' );
check( ! $retire->invoke( $webhooks, job_key( 9 ), $options[ job_key( 9 ) ] ), 'Failed retirement is reported' );
check( isset( $options[ job_key( 9 ) ] ), 'Failed retirement retains the durable job' );
$wpdb->write_fails = false;

// Completion between the first receipt read and insertion cannot recreate a delivery.
$old_job = $options[ job_key( 8 ) ];
$on_add  = static function () use ( $retire, $webhooks, $old_job ) {
	check( $retire->invoke( $webhooks, job_key( 8 ), $old_job ), 'Previous worker retires atomically' );
};

$before = count( $scheduled );
send_created( 8 );
check( ! isset( $options[ job_key( 8 ) ] ) && count( $scheduled ) === $before, 'Completion/enqueue race leaves only a receipt and schedules no duplicate' );

// Even a recovery worker claiming an obsolete racing job must not make an HTTP request.
$options[ job_key( 8 ) ] = $old_job;
$before                  = count( $requests );
$webhooks->process_delivery( 1, job_key( 8 ) );
check( count( $requests ) === $before && ! isset( $options[ job_key( 8 ) ] ), 'Receipt is checked again before outgoing delivery' );

$wpdb->read_fails = true;
send_created( 10 );
check( ! isset( $options[ job_key( 10 ) ] ), 'Uncertain receipt reads cannot enqueue duplicates' );
$wpdb->read_fails       = false;
$GLOBALS['schedule_ok'] = false;
send_created( 10 );
check( isset( $options[ job_key( 10 ) ] ) && ! isset( $options['oc_wh_created_10_1'] ), 'Scheduler failure retains a recoverable durable job' );
$GLOBALS['schedule_ok'] = true;

$before           = count( $requests );
$wpdb->read_fails = true;
$webhooks->process_delivery( 1, job_key( 10 ) );
check( count( $requests ) === $before && isset( $options[ job_key( 10 ) ] ), 'A failed worker receipt read retains the job without sending HTTP' );
$wpdb->read_fails                       = false;
$options[ job_key( 10 ) ]['claimed_at'] = time() - 301;
$webhooks->process_delivery( 1, job_key( 10 ) );
check( isset( $options['oc_wh_created_10_1'] ), 'Stale-claim recovery can finish after the database read recovers' );

OC_DB::$webhooks[2]     = clone OC_DB::$webhooks[1];
OC_DB::$webhooks[2]->id = 2;
send_created( 7 );
check( isset( $options[ job_key( 7, 2 ) ] ), 'Each webhook destination gets an independent event' );
$before_uuid = $GLOBALS['uuid_counter'];
$webhooks->send( 'print.files.generated', [ 'order_id' => 7 ] );
$webhooks->send( 'print.files.generated', [ 'order_id' => 7 ] );
check( $before_uuid + 4 === $GLOBALS['uuid_counter'], 'Regeneration notifications remain repeatable, not order-created deduplicated' );

// Existing queued deliveries retain their pre-upgrade identity through retries.
send_created( 11 );
$legacy_id                               = '11111111-1111-5111-a111-111111111111';
$options[ job_key( 11 ) ]['delivery_id'] = $legacy_id;
send_created( 11 );
$http_code = 503;
$webhooks->process_delivery( 1, job_key( 11 ) );
check( $legacy_id === $options[ job_key( 11 ) ]['delivery_id'], 'An existing delivery UUID is never regenerated on checkout retries or HTTP failures' );
$options[ job_key( 11 ) ]['run_after'] = time() - 1;
$http_code                             = 200;
$webhooks->process_delivery( 1, job_key( 11 ) );
check( hash_equals( $legacy_id, end( $requests )['headers']['X-OC-Delivery-ID'] ), 'Upgraded jobs send the same header on their successful retry' );

// A separate store has its own options table but may reuse order/destination IDs.
$first_store_id = $original['delivery_id'];
unset( OC_DB::$webhooks[2] );
$options   = [];
$scheduled = [];
send_created( 7 );
$second_store_job = $options[ job_key( 7 ) ];
check( ! hash_equals( $first_store_id, $second_store_job['delivery_id'] ), 'Matching order and destination IDs on separate stores have distinct delivery identities' );
send_created( 7 );
check( $second_store_job === $options[ job_key( 7 ) ] && 1 === count( $scheduled ), 'The second store still deduplicates locally without replacing its UUID or payload' );
echo "Webhook deduplication regressions passed.\n";
