<?php
/**
 * Unit tests for REST input parsing.
 *
 * @package OverCustomise
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once OC_PATH . 'includes/class-oc-rest-api.php';

class Test_REST_API_Parsing extends TestCase {

	#[Test]
	public function spotify_parser_rejects_identifiers_that_require_character_stripping(): void {
		$method = new ReflectionMethod( OC_Rest_API::class, 'parse_spotify_input' );
		$method->setAccessible( true );
		$api = new OC_Rest_API();

		$this->assertNull( $method->invoke( $api, 'https://open.spotify.com/track/abc-def' ) );
		$this->assertNull( $method->invoke( $api, 'spotify:track:' . str_repeat( 'a', 129 ) ) );
		$this->assertSame(
			'spotify:track:6rqhFgbbKwnb9MLmUQDhG6',
			$method->invoke( $api, 'https://open.spotify.com/intl-en/track/6rqhFgbbKwnb9MLmUQDhG6?si=test' )['spotify_uri']
		);
	}

	#[Test]
	public function sliding_budgets_expire_attempts_independently(): void {
		$method = new ReflectionMethod( OC_Rest_API::class, 'normalise_sliding_budget_state' );
		$now    = 2000000000;
		$state  = [
			'version' => 2, 'window_type' => 'sliding', 'window_seconds' => 900,
			'buckets' => [
				[ 'timestamp' => $now - 900, 'count' => 1, 'bytes' => 0 ],
				[ 'timestamp' => $now - 899, 'count' => 1, 'bytes' => 0 ],
				[ 'timestamp' => $now - 10, 'count' => 1, 'bytes' => 0 ],
			],
		];

		$result = $method->invoke( null, $state, 900, $now );
		$this->assertSame( [ $now - 899, $now - 10 ], array_column( $result['buckets'], 'timestamp' ) );
	}

	#[Test]
	public function sliding_budget_retry_uses_oldest_capacity_releasing_attempt(): void {
		$method  = new ReflectionMethod( OC_Rest_API::class, 'sliding_budget_retry_after' );
		$now     = 2000000000;
		$buckets = [
			[ 'timestamp' => $now - 800, 'count' => 2, 'bytes' => 0 ],
			[ 'timestamp' => $now - 100, 'count' => 3, 'bytes' => 0 ],
		];
		$spec = [ 'count' => 1, 'bytes' => 0, 'count_limit' => 5, 'byte_limit' => 0 ];

		$this->assertSame( 100, $method->invoke( null, $buckets, $spec, $now, 900 ) );
	}

	#[Test]
	public function legacy_sliding_budget_migration_preserves_expiry(): void {
		$method = new ReflectionMethod( OC_Rest_API::class, 'normalise_sliding_budget_state' );
		$now    = 2000000000;
		$state  = [
			'version' => 1, 'window_start' => $now - 100, 'window_end' => $now + 800,
			'count' => 4, 'bytes' => 0, 'sliding_window' => true,
		];

		$result = $method->invoke( null, $state, 900, $now );
		$this->assertSame( $now - 100, $result['buckets'][0]['timestamp'] );
		$this->assertSame( 4, $result['buckets'][0]['count'] );
	}
}
