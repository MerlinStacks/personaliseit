<?php
/**
 * Unit tests for webhook payload redaction.
 *
 * @package OverCustomise
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once OC_PATH . 'includes/class-oc-webhooks.php';
require_once OC_PATH . 'includes/class-oc-plugin.php';

if ( ! function_exists( 'maybe_serialize' ) ) {
	function maybe_serialize( mixed $value ): string {
		return serialize( $value );
	}
}

if ( ! function_exists( 'maybe_unserialize' ) ) {
	function maybe_unserialize( mixed $value ): mixed {
		return is_string( $value ) ? unserialize( $value ) : $value;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( string $option, mixed $value, ?bool $autoload = null ): bool {
		$GLOBALS['oc_test_options'][ $option ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( string $option ): bool {
		unset( $GLOBALS['oc_test_options'][ $option ] );
		return true;
	}
}

if ( ! function_exists( 'wp_cache_delete' ) ) {
	function wp_cache_delete( string $key, string $group = '' ): bool {
		return true;
	}
}

if ( ! function_exists( 'wp_schedule_single_event' ) ) {
	function wp_schedule_single_event( int $timestamp, string $hook, array $args = [] ): bool {
		$GLOBALS['oc_test_scheduled_events'][] = compact( 'timestamp', 'hook', 'args' ) + [
			'cas_completed' => ! empty( $GLOBALS['oc_test_cas_completed'] ),
		];
		return true;
	}
}

if ( ! function_exists( 'wp_next_scheduled' ) ) {
	function wp_next_scheduled( string $hook, array $args = [] ): int|false {
		return false;
	}
}

if ( ! function_exists( 'wp_schedule_event' ) ) {
	function wp_schedule_event( int $timestamp, string $recurrence, string $hook, array $args = [] ): bool {
		$GLOBALS['oc_test_recurring_events'][] = compact( 'timestamp', 'recurrence', 'hook', 'args' );
		return true;
	}
}

if ( ! function_exists( 'wp_clear_scheduled_hook' ) ) {
	function wp_clear_scheduled_hook( string $hook, array $args = [] ): int|false {
		$GLOBALS['oc_test_cleared_hooks'][] = [ $hook, $args ];
		return 1;
	}
}

if ( ! function_exists( '_get_cron_array' ) ) {
	function _get_cron_array(): array {
		return [];
	}
}

if ( ! function_exists( 'wp_unschedule_hook' ) ) {
	function wp_unschedule_hook( string $hook ): int|false {
		return 1;
	}
}

if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
	function as_unschedule_all_actions( string $hook, array $args = [], string $group = '' ): void {
		$GLOBALS['oc_test_unscheduled_actions'][] = compact( 'hook', 'args', 'group' );
	}
}

class OC_Test_Webhook_WPDB {
	public string $options         = 'wp_options';
	public string $last_error      = '';
	public array $rows             = [];
	public int|false $query_result = 1;
	public mixed $last_prepared    = null;

	public function esc_like( string $value ): string {
		return addcslashes( $value, '_%\\' );
	}

	public function prepare( string $query, mixed ...$args ): array {
		$this->last_prepared = [ $query, $args ];
		return $this->last_prepared;
	}

	public function get_results( mixed $query ): array {
		return $this->rows;
	}

	public function query( mixed $query ): int|false {
		$this->last_prepared              = $query;
		$GLOBALS['oc_test_cas_completed'] = 1 === $this->query_result;
		return $this->query_result;
	}
}

class Test_Webhooks extends TestCase {

	#[Test]
	public function it_removes_internal_paths_from_nested_customisation_data(): void {
		$method = new ReflectionMethod( OC_Webhooks::class, 'without_internal_paths' );
		$method->setAccessible( true );

		$result = $method->invoke( null, [
			'layers' => [
				[
					'artworkPath' => '/srv/site/uploads/clipart.svg',
					'input'       => [ 'value' => 'Alex' ],
				],
			],
			'file_path'     => '/srv/site/output.pdf',
			'thumbnail_path' => '/srv/site/output.png',
		] );

		$this->assertSame( 'Alex', $result['layers'][0]['input']['value'] );
		$this->assertArrayNotHasKey( 'artworkPath', $result['layers'][0] );
		$this->assertArrayNotHasKey( 'file_path', $result );
		$this->assertArrayNotHasKey( 'thumbnail_path', $result );
	}

	#[Test]
	public function it_persists_delivery_before_scheduling_background_work(): void {
		$job_key = 'oc_wh_job_00000000-0000-4000-8000-000000000000';
		unset( $GLOBALS['oc_test_options'][ $job_key ] );
		$GLOBALS['oc_test_scheduled_events'] = [];
		$method = new ReflectionMethod( OC_Webhooks::class, 'enqueue_delivery' );
		$method->setAccessible( true );

		$method->invoke( new OC_Webhooks(), 17, '{"event":"test"}', 'test.event' );

		$this->assertSame( 17, $GLOBALS['oc_test_options'][ $job_key ]['webhook_id'] );
		$this->assertSame( 'pending', $GLOBALS['oc_test_options'][ $job_key ]['status'] );
		$this->assertSame( 'oc_webhook_deliver', $GLOBALS['oc_test_scheduled_events'][0]['hook'] );
		$this->assertSame( [ 17, $job_key ], $GLOBALS['oc_test_scheduled_events'][0]['args'] );
		unset( $GLOBALS['oc_test_options'][ $job_key ], $GLOBALS['oc_test_scheduled_events'] );
	}

	#[Test]
	public function recovery_schedules_a_bounded_due_job_batch_and_advances_its_cursor(): void {
		global $wpdb;
		$previous_wpdb                       = $wpdb ?? null;
		$wpdb                                = new OC_Test_Webhook_WPDB();
		$job_key                             = 'oc_wh_job_11111111-1111-4111-8111-111111111111';
		$wpdb->rows                          = [
			(object) [
				'option_id'    => 44,
				'option_name'  => $job_key,
				'option_value' => serialize(
					[
						'webhook_id' => 9,
						'status'     => 'pending',
						'run_after'  => time() - 1,
						'claimed_at' => 0,
					]
				),
			],
		];
		$GLOBALS['oc_test_scheduled_events'] = [];

		try {
			$this->assertSame( 1, ( new OC_Webhooks() )->recover_deliveries() );
			$this->assertSame( 20, $wpdb->last_prepared[1][2] );
			$this->assertSame( [ 9, $job_key ], $GLOBALS['oc_test_scheduled_events'][0]['args'] );
			$this->assertSame( 0, $GLOBALS['oc_test_options']['oc_wh_recovery_cursor'] );
		} finally {
			$wpdb = $previous_wpdb;
			unset( $GLOBALS['oc_test_scheduled_events'], $GLOBALS['oc_test_options']['oc_wh_recovery_cursor'] );
		}
	}

	#[Test]
	public function retry_is_not_scheduled_when_its_cas_persistence_fails(): void {
		global $wpdb;
		$previous_wpdb                       = $wpdb ?? null;
		$wpdb                                = new OC_Test_Webhook_WPDB();
		$wpdb->query_result                  = 0;
		$GLOBALS['oc_test_scheduled_events'] = [];
		$method                              = new ReflectionMethod( OC_Webhooks::class, 'retry_delivery' );
		$method->setAccessible( true );
		$job = [
			'webhook_id'  => 5,
			'event'       => 'test.event',
			'attempt'     => 0,
			'status'      => 'processing',
			'run_after'   => time(),
			'claimed_at'  => time(),
			'delivery_id' => '22222222-2222-4222-8222-222222222222',
		];

		try {
			$method->invoke( new OC_Webhooks(), 5, 'oc_wh_job_' . $job['delivery_id'], $job, 0 );
			$this->assertSame( [], $GLOBALS['oc_test_scheduled_events'] );
		} finally {
			$wpdb = $previous_wpdb;
			unset( $GLOBALS['oc_test_scheduled_events'] );
		}
	}

	#[Test]
	public function retry_is_scheduled_only_after_its_cas_persistence_succeeds(): void {
		global $wpdb;
		$previous_wpdb                       = $wpdb ?? null;
		$wpdb                                = new OC_Test_Webhook_WPDB();
		$GLOBALS['oc_test_scheduled_events'] = [];
		$GLOBALS['oc_test_cas_completed']    = false;
		$method                              = new ReflectionMethod( OC_Webhooks::class, 'retry_delivery' );
		$method->setAccessible( true );
		$job = [
			'webhook_id'  => 6,
			'event'       => 'test.event',
			'attempt'     => 0,
			'status'      => 'processing',
			'run_after'   => time(),
			'claimed_at'  => time(),
			'delivery_id' => '33333333-3333-4333-8333-333333333333',
		];

		try {
			$method->invoke( new OC_Webhooks(), 6, 'oc_wh_job_' . $job['delivery_id'], $job, 0 );
			$this->assertTrue( $GLOBALS['oc_test_scheduled_events'][0]['cas_completed'] );
			$persisted = unserialize( $wpdb->last_prepared[1][0] );
			$this->assertSame( 1, $persisted['attempt'] );
			$this->assertSame( 'pending', $persisted['status'] );
		} finally {
			$wpdb = $previous_wpdb;
			unset( $GLOBALS['oc_test_scheduled_events'], $GLOBALS['oc_test_cas_completed'] );
		}
	}

	#[Test]
	public function runtime_cron_setup_includes_webhook_recovery(): void {
		$GLOBALS['oc_test_recurring_events'] = [];

		OC_Plugin::ensure_cron_events();

		$this->assertContains( 'oc_recover_webhook_deliveries', array_column( $GLOBALS['oc_test_recurring_events'], 'hook' ) );
		unset( $GLOBALS['oc_test_recurring_events'] );
	}

	#[Test]
	public function lifecycle_cleanup_clears_delivery_hooks_and_action_scheduler_group(): void {
		$GLOBALS['oc_test_cleared_hooks']       = [];
		$GLOBALS['oc_test_unscheduled_actions'] = [];
		$method                                 = new ReflectionMethod( OC_Plugin::class, 'clear_scheduled_events' );
		$method->setAccessible( true );

		$method->invoke( null );

		$this->assertContains( 'oc_webhook_deliver', array_column( $GLOBALS['oc_test_cleared_hooks'], 0 ) );
		$this->assertContains( 'oc_recover_webhook_deliveries', array_column( $GLOBALS['oc_test_cleared_hooks'], 0 ) );
		$this->assertContains(
			[
				'hook'  => '',
				'args'  => [],
				'group' => 'overcustomise-webhooks',
			],
			$GLOBALS['oc_test_unscheduled_actions']
		);
		unset( $GLOBALS['oc_test_cleared_hooks'], $GLOBALS['oc_test_unscheduled_actions'] );
	}

	#[Test]
	public function uninstall_job_cleanup_uses_bounded_direct_sql(): void {
		global $wpdb;
		$previous_wpdb      = $wpdb ?? null;
		$wpdb               = new OC_Test_Webhook_WPDB();
		$wpdb->query_result = 0;
		$GLOBALS['oc_test_options']['oc_wh_recovery_cursor'] = 99;
		$method = new ReflectionMethod( OC_Plugin::class, 'delete_webhook_jobs' );
		$method->setAccessible( true );

		try {
			$method->invoke( null );
			$this->assertStringContainsString( 'LIMIT 500', $wpdb->last_prepared[0] );
			$this->assertStringContainsString( 'option_name LIKE %s', $wpdb->last_prepared[0] );
			$this->assertArrayNotHasKey( 'oc_wh_recovery_cursor', $GLOBALS['oc_test_options'] );
		} finally {
			$wpdb = $previous_wpdb;
		}
	}
}
