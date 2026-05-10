<?php
defined( 'ABSPATH' ) || exit;

class OC_Webhooks {

	private const MAX_RETRIES  = 3;
	private const RETRY_DELAYS = [ 60, 300, 900 ];

	public function register(): void {
		add_action( 'woocommerce_checkout_order_created', [ $this, 'on_order_created' ], 25, 1 );
		add_action( 'oc_print_files_generated', [ $this, 'on_print_files_generated' ], 10, 1 );
		add_action( 'oc_print_file_failed', [ $this, 'on_print_file_failed' ], 10, 4 );
		add_action( 'oc_webhook_retry', [ $this, 'retry_webhook' ], 10, 2 );
	}

	public function on_order_created( \WC_Order $order ): void {
		$items = [];
		foreach ( $order->get_items() as $item ) {
			$customisation = $item->get_meta( '_oc_customisation', true );
			if ( ! empty( $customisation ) && is_array( $customisation ) ) {
				$items[] = [
					'item_id'       => $item->get_id(),
					'product_id'    => $item->get_product_id(),
					'variation_id'  => $item->get_variation_id(),
					'name'          => $item->get_name(),
					'quantity'      => $item->get_quantity(),
					'customisation' => $customisation,
				];
			}
		}

		if ( empty( $items ) ) {
			return;
		}

		$this->send( 'order.customisation.created', [
			'order_id'       => $order->get_id(),
			'order_key'      => $order->get_order_key(),
			'customer_email' => $order->get_billing_email(),
			'total'          => $order->get_total(),
			'items'          => $items,
			'timestamp'      => current_time( 'mysql', true ),
		] );
	}

	public function on_print_files_generated( \WC_Order $order ): void {
		$files = [];
		foreach ( $order->get_items() as $item ) {
			$print_files = OC_DB::get_print_files_for_item( $item->get_id() );
			foreach ( $print_files as $file ) {
				$files[] = [
					'file_id'       => (int) $file->id,
					'order_item_id' => (int) $file->order_item_id,
					'print_area_id' => (int) $file->print_area_id,
					'file_type'     => $file->file_type,
					'file_status'   => $file->file_status,
					'file_path'     => $file->file_path,
				];
			}
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
			'error'         => $exception->getMessage(),
			'timestamp'     => current_time( 'mysql', true ),
		] );
	}

	public function send( string $event, array $payload ): void {
		$webhooks = OC_DB::get_active_webhooks();

		if ( empty( $webhooks ) ) {
			return;
		}

		$body = wp_json_encode( [
			'event'   => $event,
			'payload' => $payload,
		] );

		foreach ( $webhooks as $webhook ) {
			$events = json_decode( $webhook->events, true );

			if ( ! is_array( $events ) || ! in_array( $event, $events, true ) ) {
				continue;
			}

			$this->deliver( (int) $webhook->id, (string) $webhook->url, (string) $webhook->secret, $body, $event );
		}
	}

	private function deliver( int $webhook_id, string $url, string $secret, string $body, string $event ): void {
		if ( filter_var( $url, FILTER_VALIDATE_URL ) === false ) {
			$this->update_delivery_status( $webhook_id, 'failed', 0, 'Invalid webhook URL.' );
			return;
		}

		$parsed = wp_parse_url( $url );

		if ( ! is_array( $parsed ) || ! isset( $parsed['scheme'] ) || ! in_array( strtolower( $parsed['scheme'] ), [ 'http', 'https' ], true ) ) {
			$this->update_delivery_status( $webhook_id, 'failed', 0, 'Invalid webhook URL scheme.' );
			return;
		}

		$signature = hash_hmac( 'sha256', $body, $secret );

		$response = wp_remote_post( $url, [
			'method'      => 'POST',
			'timeout'     => 10,
			'blocking'    => true,
			'sslverify'   => true,
			'redirection' => 0,
			'body'        => $body,
			'headers'     => [
				'Content-Type'   => 'application/json',
				'X-OC-Event'     => $event,
				'X-OC-Signature' => 'sha256=' . $signature,
				'X-OC-Timestamp' => (string) time(),
			],
		] );

		if ( is_wp_error( $response ) ) {
			$this->update_delivery_status( $webhook_id, 'failed', 0, $response->get_error_message() );
			$this->schedule_retry( $webhook_id, $body, $event, 0 );
			return;
		}

		$status = (int) wp_remote_retrieve_response_code( $response );

		if ( $status >= 200 && $status < 300 ) {
			$this->update_delivery_status( $webhook_id, 'success', $status );
		} else {
			$this->update_delivery_status( $webhook_id, 'failed', $status, (string) wp_remote_retrieve_body( $response ) );
			if ( in_array( $status, [ 408, 429 ], true ) || $status >= 500 || 0 === $status ) {
				$this->schedule_retry( $webhook_id, $body, $event, 0 );
			}
		}
	}

	private function schedule_retry( int $webhook_id, string $body, string $event, int $attempt = 0 ): void {
		if ( $attempt >= self::MAX_RETRIES ) {
			OC_Logger::error( "Webhook #{$webhook_id} exceeded max retries for event {$event}." );
			return;
		}

		$delay     = self::RETRY_DELAYS[ $attempt ] ?? self::RETRY_DELAYS[ count( self::RETRY_DELAYS ) - 1 ];
		$retry_key = 'oc_wh_retry_' . $webhook_id . '_' . wp_generate_password( 12, false );

		set_transient( $retry_key, [
			'body'    => $body,
			'event'   => $event,
			'attempt' => $attempt + 1,
		], DAY_IN_SECONDS );

		$scheduled = wp_schedule_single_event( time() + $delay, 'oc_webhook_retry', [ $webhook_id, $retry_key ] );
		if ( ! $scheduled ) {
			OC_Logger::error( "Webhook #{$webhook_id} retry scheduling failed for event {$event}." );
		}
	}

	public function retry_webhook( int $webhook_id, string $retry_key ): void {
		$retry_data = get_transient( $retry_key );

		if ( false === $retry_data || ! is_array( $retry_data ) ) {
			return;
		}

		$webhook = OC_DB::get_webhook( $webhook_id );

		if ( ! $webhook || ! $webhook->active ) {
			delete_transient( $retry_key );
			return;
		}

		$body    = $retry_data['body'] ?? '';
		$event   = $retry_data['event'] ?? '';
		$attempt = (int) ( $retry_data['attempt'] ?? 1 );

		$signature = hash_hmac( 'sha256', $body, $webhook->secret ?? '' );

		$response = wp_remote_post( $webhook->url, [
			'method'      => 'POST',
			'timeout'     => 10,
			'blocking'    => true,
			'sslverify'   => true,
			'redirection' => 0,
			'body'        => $body,
			'headers'     => [
				'Content-Type'   => 'application/json',
				'X-OC-Event'     => $event,
				'X-OC-Signature' => 'sha256=' . $signature,
				'X-OC-Timestamp' => (string) time(),
			],
		] );

		delete_transient( $retry_key );

		if ( is_wp_error( $response ) ) {
			$this->update_delivery_status( $webhook_id, 'failed', 0, $response->get_error_message() );
			$this->schedule_retry( $webhook_id, $body, $event, $attempt );
			return;
		}

		$status = (int) wp_remote_retrieve_response_code( $response );

		if ( $status >= 200 && $status < 300 ) {
			$this->update_delivery_status( $webhook_id, 'success', $status );
			OC_Logger::info( "Webhook #{$webhook_id} retry succeeded for event {$event}." );
		} else {
			$this->update_delivery_status( $webhook_id, 'failed', $status, (string) wp_remote_retrieve_body( $response ) );
			if ( in_array( $status, [ 408, 429 ], true ) || $status >= 500 || 0 === $status ) {
				$this->schedule_retry( $webhook_id, $body, $event, $attempt );
			}
		}
	}

	private function update_delivery_status( int $webhook_id, string $status, int $code, string $message = '' ): void {
		update_option( 'oc_wh_delivery_' . $webhook_id, [
			'status'  => $status,
			'code'    => $code,
			'message' => $message,
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

		if ( filter_var( $url, FILTER_VALIDATE_URL ) === false ) {
			return [ 'success' => false, 'message' => 'Invalid URL.' ];
		}

		$parsed = wp_parse_url( $url );

		if ( ! is_array( $parsed ) || ! isset( $parsed['scheme'] ) || ! in_array( strtolower( $parsed['scheme'] ), [ 'http', 'https' ], true ) ) {
			return [ 'success' => false, 'message' => 'Invalid URL scheme.' ];
		}

		$body = wp_json_encode( [
			'event'   => $event,
			'payload' => [ 'test' => true, 'timestamp' => current_time( 'mysql', true ) ],
		] );

		$signature = hash_hmac( 'sha256', $body, $secret );

		$response = wp_remote_post( $url, [
			'method'      => 'POST',
			'timeout'     => 15,
			'blocking'    => true,
			'sslverify'   => true,
			'redirection' => 0,
			'body'        => $body,
			'headers'     => [
				'Content-Type'   => 'application/json',
				'X-OC-Event'     => $event,
				'X-OC-Signature' => 'sha256=' . $signature,
				'X-OC-Timestamp' => (string) time(),
			],
		] );

		if ( is_wp_error( $response ) ) {
			return [ 'success' => false, 'message' => $response->get_error_message() ];
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$body   = wp_remote_retrieve_body( $response );

		if ( $status >= 200 && $status < 300 ) {
			return [
				'success'     => true,
				'status_code' => $status,
				'response'    => substr( $body, 0, 500 ),
				'message'     => 'Test delivery successful.',
			];
		}

		return [
			'success'     => false,
			'status_code' => $status,
			'response'    => substr( $body, 0, 500 ),
			'message'     => "Received status {$status}.",
		];
	}
}
