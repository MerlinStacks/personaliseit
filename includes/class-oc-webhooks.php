<?php
defined( 'ABSPATH' ) || exit;

class OC_Webhooks {

	private const MAX_RETRIES            = 3;
	private const RETRY_DELAYS           = [ 60, 300, 900 ];
	private const MAX_PAYLOAD_BYTES      = 2097152;
	private const MAX_RESPONSE_BYTES     = 65536;
	private const JOB_PREFIX             = 'oc_wh_job_';
	private const CLAIM_TIMEOUT          = 300;
	private const ACTION_GROUP           = 'overcustomise-webhooks';
	private const RECOVERY_BATCH_SIZE    = 20;
	private const RECOVERY_CURSOR_OPTION = 'oc_wh_recovery_cursor';

	public function register(): void {
		add_action( 'woocommerce_checkout_order_created', [ $this, 'on_order_created' ], 25, 1 );
		add_action( 'oc_print_files_generated', [ $this, 'on_print_files_generated' ], 10, 1 );
		add_action( 'oc_print_file_failed', [ $this, 'on_print_file_failed' ], 10, 4 );
		add_action( 'oc_webhook_deliver', [ $this, 'process_delivery' ], 10, 2 );
		add_action( 'oc_recover_webhook_deliveries', [ $this, 'run_recovery' ] );
		add_action( 'oc_webhook_retry', [ $this, 'retry_webhook' ], 10, 2 );
	}

	public function on_order_created( \WC_Order $order ): void {
		$items = [];
		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof \WC_Order_Item_Product ) {
				continue;
			}
			$customisation = $item->get_meta( '_oc_customisation', true );
			if ( ! empty( $customisation ) && is_array( $customisation ) ) {
				$items[] = [
					'item_id'       => $item->get_id(),
					'product_id'    => $item->get_product_id(),
					'variation_id'  => $item->get_variation_id(),
					'name'          => $item->get_name(),
					'quantity'      => $item->get_quantity(),
					'customisation' => self::without_internal_paths( $customisation ),
				];
			}
		}

		if ( empty( $items ) ) {
			return;
		}

		$this->send( 'order.customisation.created', [
			'order_id'       => $order->get_id(),
			'customer_email' => $order->get_billing_email(),
			'total'          => $order->get_total(),
			'items'          => $items,
			'timestamp'      => current_time( 'mysql', true ),
		] );
	}

	public function on_print_files_generated( \WC_Order $order ): void {
		$files = [];
		foreach ( OC_DB::get_print_files_for_order( (int) $order->get_id() ) as $file ) {
				$files[] = [
					'file_id'       => (int) $file->id,
					'order_item_id' => (int) $file->order_item_id,
					'print_area_id' => (int) $file->print_area_id,
					'file_type'     => $file->file_type,
					'file_status'   => $file->file_status,
					'file_path'     => ! empty( $file->file_path ) ? basename( (string) $file->file_path ) : null,
				];
		}

		$this->send( 'print.files.generated', [
			'order_id'  => $order->get_id(),
			'files'     => $files,
			'timestamp' => current_time( 'mysql', true ),
		] );
	}

	public function on_print_file_failed( \WC_Order $order, int $item_id, object $area, \Throwable $exception ): void {
		$this->send( 'print.file.failed', [
			'order_id'      => $order->get_id(),
			'order_item_id' => $item_id,
			'area_key'      => $area->area_key,
			'print_method'  => $area->print_method,
			'error'         => self::redact_local_paths( $exception->getMessage() ),
			'timestamp'     => current_time( 'mysql', true ),
		] );
	}

	public function send( string $event, array $payload ): void {
		if ( ! preg_match( '/^[a-z0-9._-]{1,64}$/D', $event ) ) {
			return;
		}
		$webhooks = OC_DB::get_active_webhooks();

		if ( empty( $webhooks ) ) {
			return;
		}

		$body = wp_json_encode( [
			'event'   => $event,
			'payload' => $payload,
		] );
		if ( ! is_string( $body ) || strlen( $body ) > self::MAX_PAYLOAD_BYTES ) {
			OC_Logger::error( 'Webhook payload was not sent because it exceeded the safe size limit for event ' . sanitize_key( $event ) . '.' );
			return;
		}

		foreach ( $webhooks as $webhook ) {
			$events = json_decode( $webhook->events, true );

			if ( ! is_array( $events ) || ! in_array( $event, $events, true ) ) {
				continue;
			}

			$this->enqueue_delivery( (int) $webhook->id, $body, $event );
		}
	}

	/** Persist a delivery before scheduling it off the caller's critical path. */
	private function enqueue_delivery( int $webhook_id, string $body, string $event ): void {
		$delivery_id = wp_generate_uuid4();
		$job_key     = self::JOB_PREFIX . $delivery_id;
		$job         = [
			'webhook_id'  => $webhook_id,
			'body'        => $body,
			'event'       => $event,
			'attempt'     => 0,
			'delivery_id' => $delivery_id,
			'status'      => 'pending',
			'run_after'   => time(),
			'claimed_at'  => 0,
		];
		if ( ! add_option( $job_key, $job, '', false ) ) {
			OC_Logger::error( "Webhook #{$webhook_id} delivery could not be persisted for event {$event}." );
			return;
		}

		if ( ! $this->schedule_delivery( $webhook_id, $job_key, time() ) ) {
			OC_Logger::error( "Webhook #{$webhook_id} delivery scheduling failed for event {$event}; the durable job was retained." );
		}
	}

	/** Prefer Action Scheduler, falling back to WP-Cron when it cannot schedule. */
	private function schedule_delivery( int $webhook_id, string $job_key, int $timestamp ): bool {
		$args         = [ $webhook_id, $job_key ];
		$as_scheduled = false;
		if ( function_exists( 'as_schedule_single_action' ) ) {
			try {
				if ( function_exists( 'as_has_scheduled_action' ) && as_has_scheduled_action( 'oc_webhook_deliver', $args, self::ACTION_GROUP ) ) {
					return true;
				}
				$as_scheduled = (bool) as_schedule_single_action( $timestamp, 'oc_webhook_deliver', $args, self::ACTION_GROUP, true );
			} catch ( \Throwable $e ) {
				OC_Logger::warning( 'Action Scheduler could not queue a webhook delivery: ' . self::bounded_message( $e->getMessage() ) );
			}
		}
		if ( $as_scheduled ) {
			return true;
		}
		$cron_scheduled = false !== wp_schedule_single_event( $timestamp, 'oc_webhook_deliver', $args );

		return $cron_scheduled || false !== wp_next_scheduled( 'oc_webhook_deliver', $args );
	}

	/** Run webhook recovery from its scheduled action. */
	public function run_recovery(): void {
		$this->recover_deliveries();
	}

	/** Reschedule a bounded batch of due or abandoned durable jobs. */
	public function recover_deliveries(): int {
		global $wpdb;
		$limit  = apply_filters( 'oc_webhook_recovery_batch_size', self::RECOVERY_BATCH_SIZE );
		$limit  = is_numeric( $limit ) ? max( 1, min( 100, (int) $limit ) ) : self::RECOVERY_BATCH_SIZE;
		$like   = $wpdb->esc_like( self::JOB_PREFIX ) . '%';
		$cursor = max( 0, (int) get_option( self::RECOVERY_CURSOR_OPTION, 0 ) );
		$rows   = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_id, option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s AND option_id > %d ORDER BY option_id ASC LIMIT %d",
				$like,
				$cursor,
				$limit
			)
		);
		if ( ! is_array( $rows ) || '' !== (string) $wpdb->last_error ) {
			OC_Logger::warning( 'Webhook recovery could not read durable delivery jobs.' );
			return 0;
		}
		if ( empty( $rows ) ) {
			if ( $cursor > 0 ) {
				update_option( self::RECOVERY_CURSOR_OPTION, 0, false );
			}
			return 0;
		}

		$scheduled = 0;
		$now       = time();
		foreach ( $rows as $row ) {
			$job_key = (string) ( $row->option_name ?? '' );
			if ( ! preg_match( '/^oc_wh_job_[A-Za-z0-9-]{16,64}$/D', $job_key ) ) {
				continue;
			}
			$job = maybe_unserialize( $row->option_value ?? '' );
			if ( ! is_array( $job ) ) {
				continue;
			}
			$status     = (string) ( $job['status'] ?? '' );
			$run_after  = (int) ( $job['run_after'] ?? 0 );
			$claimed_at = (int) ( $job['claimed_at'] ?? 0 );
			$is_due     = 'pending' === $status && $run_after <= $now;
			$is_stale   = 'processing' === $status && $claimed_at <= $now - self::CLAIM_TIMEOUT;
			$webhook_id = (int) ( $job['webhook_id'] ?? 0 );
			if ( $webhook_id <= 0 || ( ! $is_due && ! $is_stale ) ) {
				continue;
			}
			$args      = [ $webhook_id, $job_key ];
			$recovered = $is_stale
				? ( false !== wp_schedule_single_event( $now, 'oc_webhook_deliver', $args ) || false !== wp_next_scheduled( 'oc_webhook_deliver', $args ) )
				: $this->schedule_delivery( $webhook_id, $job_key, $now );
			if ( $recovered ) {
				++$scheduled;
			}
		}
		$last_row = end( $rows );
		$last_id  = is_object( $last_row ) ? (int) ( $last_row->option_id ?? 0 ) : 0;
		update_option( self::RECOVERY_CURSOR_OPTION, count( $rows ) < $limit ? 0 : $last_id, false );

		return $scheduled;
	}

	/** Backward-compatible consumer for retries queued by older plugin versions. */
	public function retry_webhook( int $webhook_id, string $retry_key ): void {
		$retry_data = get_transient( $retry_key );

		if ( false === $retry_data || ! is_array( $retry_data ) ) {
			return;
		}

		$body        = is_string( $retry_data['body'] ?? null ) ? $retry_data['body'] : '';
		$event       = is_string( $retry_data['event'] ?? null ) ? $retry_data['event'] : '';
		$attempt     = (int) ( $retry_data['attempt'] ?? 1 );
		$delivery_id = (string) ( $retry_data['delivery_id'] ?? wp_generate_uuid4() );
		$job_key     = self::JOB_PREFIX . $delivery_id;
		$persisted   = add_option(
			$job_key,
			[
				'webhook_id'  => $webhook_id,
				'body'        => $body,
				'event'       => $event,
				'attempt'     => $attempt,
				'delivery_id' => $delivery_id,
				'status'      => 'pending',
				'run_after'   => time(),
				'claimed_at'  => 0,
			],
			'',
			false
		);
		if ( ! $persisted && ! is_array( get_option( $job_key, null ) ) ) {
			OC_Logger::error( "Legacy webhook #{$webhook_id} retry could not be persisted; its transient was retained." );
			return;
		}
		delete_transient( $retry_key );
		if ( ! $this->schedule_delivery( $webhook_id, $job_key, time() ) ) {
			OC_Logger::error( "Legacy webhook #{$webhook_id} retry scheduling failed; the durable job was retained." );
		}
	}

	/** Claim and deliver one durable job; duplicate actions become no-ops. */
	public function process_delivery( int $webhook_id, string $job_key ): void {
		$job = $this->claim_delivery( $job_key );
		if ( null === $job ) {
			return;
		}
		if ( (int) ( $job['webhook_id'] ?? 0 ) !== $webhook_id ) {
			$pending               = $job;
			$pending['status']     = 'pending';
			$pending['claimed_at'] = 0;
			$this->compare_and_swap_delivery( $job_key, $job, $pending );
			return;
		}
		$webhook = OC_DB::get_webhook( $webhook_id );
		if ( ! $webhook || ! $webhook->active ) {
			$this->compare_and_delete_delivery( $job_key, $job );
			return;
		}
		$body        = is_string( $job['body'] ?? null ) ? $job['body'] : '';
		$event       = is_string( $job['event'] ?? null ) ? $job['event'] : '';
		$attempt     = max( 0, (int) ( $job['attempt'] ?? 0 ) );
		$delivery_id = (string) ( $job['delivery_id'] ?? '' );
		if ( ! self::is_safe_webhook_url( (string) $webhook->url ) || strlen( (string) ( $webhook->secret ?? '' ) ) < 32 ) {
			$this->update_delivery_status( $webhook_id, 'failed', 0, 'Webhook configuration is no longer valid.' );
			$this->compare_and_delete_delivery( $job_key, $job );
			return;
		}
		$response = self::post( (string) $webhook->url, (string) ( $webhook->secret ?? '' ), $body, $event, $delivery_id, 10 );

		if ( is_wp_error( $response ) ) {
			$this->update_delivery_status( $webhook_id, 'failed', 0, $response->get_error_message() );
			$this->retry_delivery( $webhook_id, $job_key, $job, $attempt );
			return;
		}

		$status = (int) wp_remote_retrieve_response_code( $response );

		if ( $status >= 200 && $status < 300 ) {
			$this->update_delivery_status( $webhook_id, 'success', $status );
			$this->compare_and_delete_delivery( $job_key, $job );
			if ( $attempt > 0 ) {
				OC_Logger::info( "Webhook #{$webhook_id} retry succeeded for event {$event}." );
			}
		} else {
			$this->update_delivery_status( $webhook_id, 'failed', $status, self::bounded_message( (string) wp_remote_retrieve_body( $response ) ) );
			if ( in_array( $status, [ 408, 429 ], true ) || $status >= 500 || 0 === $status ) {
				$this->retry_delivery( $webhook_id, $job_key, $job, $attempt );
			} else {
				$this->compare_and_delete_delivery( $job_key, $job );
			}
		}
	}

	/** Atomically claim a due option-backed job, including stale-claim recovery. */
	private function claim_delivery( string $job_key ): ?array {
		if ( ! str_starts_with( $job_key, self::JOB_PREFIX ) ) {
			return null;
		}
		$job = get_option( $job_key, null );
		if ( ! is_array( $job ) || (int) ( $job['run_after'] ?? 0 ) > time() ) {
			return null;
		}
		if ( 'processing' === ( $job['status'] ?? '' ) && (int) ( $job['claimed_at'] ?? 0 ) > time() - self::CLAIM_TIMEOUT ) {
			return null;
		}

		$claimed               = $job;
		$claimed['status']     = 'processing';
		$claimed['claimed_at'] = time();
		if ( ! $this->compare_and_swap_delivery( $job_key, $job, $claimed ) ) {
			return null;
		}

		return $claimed;
	}

	/** Persist the next attempt before scheduling it. */
	private function retry_delivery( int $webhook_id, string $job_key, array $job, int $attempt ): void {
		$event = (string) ( $job['event'] ?? '' );
		if ( $attempt >= self::MAX_RETRIES ) {
			$this->compare_and_delete_delivery( $job_key, $job );
			OC_Logger::error( "Webhook #{$webhook_id} exceeded max retries for event {$event}." );
			return;
		}
		$delay               = self::RETRY_DELAYS[ $attempt ] ?? self::RETRY_DELAYS[ count( self::RETRY_DELAYS ) - 1 ];
		$retry               = $job;
		$retry['attempt']    = $attempt + 1;
		$retry['status']     = 'pending';
		$retry['run_after']  = time() + $delay;
		$retry['claimed_at'] = 0;
		if ( ! $this->compare_and_swap_delivery( $job_key, $job, $retry ) ) {
			OC_Logger::warning( "Webhook #{$webhook_id} retry was not scheduled because its durable job changed state." );
			return;
		}
		if ( ! $this->schedule_delivery( $webhook_id, $job_key, (int) $retry['run_after'] ) ) {
			OC_Logger::error( "Webhook #{$webhook_id} retry scheduling failed for event {$event}; the durable job was retained." );
		}
	}

	/** Replace an option-backed job only if it still has the state this worker read. */
	private function compare_and_swap_delivery( string $job_key, array $expected, array $replacement ): bool {
		global $wpdb;
		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s",
				maybe_serialize( $replacement ),
				$job_key,
				maybe_serialize( $expected )
			)
		);
		if ( 1 !== $updated ) {
			return false;
		}
		wp_cache_delete( $job_key, 'options' );
		wp_cache_delete( 'alloptions', 'options' );
		return true;
	}

	/** Delete an option-backed job only while this worker still owns its state. */
	private function compare_and_delete_delivery( string $job_key, array $expected ): bool {
		global $wpdb;
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s",
				$job_key,
				maybe_serialize( $expected )
			)
		);
		if ( 1 !== $deleted ) {
			return false;
		}
		wp_cache_delete( $job_key, 'options' );
		wp_cache_delete( 'alloptions', 'options' );
		return true;
	}

	private function update_delivery_status( int $webhook_id, string $status, int $code, string $message = '' ): void {
		update_option( 'oc_wh_delivery_' . $webhook_id, [
			'status'  => $status,
			'code'    => $code,
			'message' => self::bounded_message( $message ),
			'at'      => current_time( 'mysql', true ),
		] );
	}

	public static function test_delivery( int $webhook_id, string $event = 'test.ping' ): array {
		$webhook = OC_DB::get_webhook( $webhook_id );

		if ( ! $webhook ) {
			return [ 'success' => false, 'message' => 'Webhook not found.' ];
		}

		$url    = (string) $webhook->url;
		$secret = (string) $webhook->secret;

		if ( strlen( $secret ) < 32 ) {
			return [ 'success' => false, 'message' => 'Webhook secret must contain at least 32 characters.' ];
		}
		if ( ! self::is_safe_webhook_url( $url ) ) {
			return [ 'success' => false, 'message' => 'URL resolves to a blocked address.' ];
		}

		$body = wp_json_encode( [
			'event'   => $event,
			'payload' => [ 'test' => true, 'timestamp' => current_time( 'mysql', true ) ],
		] );
		if ( ! is_string( $body ) ) {
			return [ 'success' => false, 'message' => 'Could not encode the test payload.' ];
		}

		$response = self::post( $url, $secret, $body, $event, wp_generate_uuid4(), 15 );

		if ( is_wp_error( $response ) ) {
			return [ 'success' => false, 'message' => $response->get_error_message() ];
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$body   = self::bounded_message( (string) wp_remote_retrieve_body( $response ), 500 );

		if ( $status >= 200 && $status < 300 ) {
			return [
				'success'     => true,
				'status_code' => $status,
				'response'    => $body,
				'message'     => 'Test delivery successful.',
			];
		}

		return [
			'success'     => false,
			'status_code' => $status,
			'response'    => $body,
			'message'     => "Received status {$status}.",
		];
	}

	private static function post( string $url, string $secret, string $body, string $event, string $delivery_id, int $timeout ): array|\WP_Error {
		if ( strlen( $body ) > self::MAX_PAYLOAD_BYTES
			|| strlen( $secret ) < 32
			|| ! preg_match( '/^[a-z0-9._-]{1,64}$/D', $event )
			|| ! preg_match( '/^[A-Za-z0-9-]{16,64}$/D', $delivery_id )
			|| ! self::is_safe_webhook_url( $url )
		) {
			return new \WP_Error( 'http_request_not_executed', 'Webhook delivery parameters are invalid.' );
		}
		$timestamp    = (string) time();
		$signature_v1 = hash_hmac( 'sha256', $body, $secret );
		$signature_v2 = hash_hmac( 'sha256', $timestamp . '.' . $event . '.' . $body, $secret );
		return wp_safe_remote_post( $url, [
			'timeout' => $timeout, 'blocking' => true, 'sslverify' => true, 'redirection' => 0, 'reject_unsafe_urls' => true,
			'limit_response_size' => self::MAX_RESPONSE_BYTES,
			'body' => $body,
			'headers' => [
				'Content-Type'       => 'application/json',
				'X-OC-Event'        => $event,
				'X-OC-Signature'    => 'sha256=' . $signature_v1,
				'X-OC-Signature-V2' => 'sha256=' . $signature_v2,
				'X-OC-Timestamp'    => $timestamp,
				'X-OC-Delivery-ID'  => $delivery_id,
			],
		] );
	}

	/** Reject local, private, reserved, loopback, and link-local destinations. */
	public static function is_safe_webhook_url( string $url ): bool {
		if ( false === filter_var( $url, FILTER_VALIDATE_URL ) ) return false;
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts )
			|| 'https' !== strtolower( (string) ( $parts['scheme'] ?? '' ) )
			|| empty( $parts['host'] )
			|| isset( $parts['user'] )
			|| isset( $parts['pass'] )
			|| ( isset( $parts['port'] ) && 443 !== (int) $parts['port'] )
		) return false;
		$host = strtolower( rtrim( (string) $parts['host'], '.' ) );
		if ( 'localhost' === $host || str_ends_with( $host, '.localhost' ) ) return false;
		$ips = [];
		if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
			$ips[] = $host;
		} else {
			$records = function_exists( 'dns_get_record' ) ? @dns_get_record( $host, DNS_A | DNS_AAAA ) : [];
			foreach ( is_array( $records ) ? $records : [] as $record ) {
				if ( ! empty( $record['ip'] ) ) $ips[] = $record['ip'];
				if ( ! empty( $record['ipv6'] ) ) $ips[] = $record['ipv6'];
			}
		}
		if ( empty( $ips ) ) return false;
		foreach ( array_unique( $ips ) as $ip ) {
			if ( str_starts_with( strtolower( $ip ), '::ffff:' ) ) {
				$mapped = substr( $ip, 7 );
				$ip = filter_var( $mapped, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ? $mapped : $ip;
			}
			if ( false === filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) return false;
		}
		return true;
	}

	/** Strip control characters and cap stored/displayed remote response text. */
	private static function bounded_message( string $message, int $limit = 1000 ): string {
		$message = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $message ) ?? '';
		$limit   = max( 0, min( self::MAX_RESPONSE_BYTES, $limit ) );
		return function_exists( 'mb_strcut' ) ? mb_strcut( $message, 0, $limit, 'UTF-8' ) : substr( $message, 0, $limit );
	}

	/** Remove local filesystem roots from failure details sent off-site. */
	private static function redact_local_paths( string $message ): string {
		$uploads   = wp_upload_dir();
		$site_root = defined( 'ABSPATH' ) ? (string) ABSPATH : '';
		$replacements = [
			(string) ( $uploads['basedir'] ?? '' ) => '[uploads]',
			$site_root => '[site]',
			sys_get_temp_dir() => '[temp]',
		];
		foreach ( $replacements as $path => $label ) {
			if ( '' !== $path ) {
				$message = str_replace( [ $path, str_replace( '\\', '/', $path ) ], $label, $message );
			}
		}

		return self::bounded_message( $message );
	}

	/** Remove local production paths from nested customisation payloads. */
	private static function without_internal_paths( array $value ): array {
		$clean = [];
		foreach ( $value as $key => $item ) {
			if ( is_string( $key ) && in_array( $key, [ 'artworkPath', 'file_path', 'thumbnail_path' ], true ) ) {
				continue;
			}
			$clean[ $key ] = is_array( $item ) ? self::without_internal_paths( $item ) : $item;
		}

		return $clean;
	}
}
