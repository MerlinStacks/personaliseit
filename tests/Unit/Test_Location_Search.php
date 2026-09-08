<?php
/** Behavioral regression tests for country-first location searches. */

use PHPUnit\Framework\TestCase;

class Test_Location_Search extends TestCase {
	private array $options;

	protected function setUp(): void {
		$this->options = $GLOBALS['oc_test_options'] ?? [];
		$GLOBALS['oc_test_options']['woocommerce_default_country'] = 'AU:NSW';
		$GLOBALS['oc_test_http_requests'] = [];
		$GLOBALS['oc_test_http_responses'] = [];
	}

	protected function tearDown(): void {
		$GLOBALS['oc_test_options'] = $this->options;
		unset( $GLOBALS['oc_test_http_requests'], $GLOBALS['oc_test_http_responses'] );
	}

	private function place( string $name, string $lat = '-33.8688', string $lon = '151.2093' ): array {
		return [ 'lat' => $lat, 'lon' => $lon, 'display_name' => $name ];
	}

	private function respond( array $places ): void {
		$GLOBALS['oc_test_http_responses'][] = [ 'response' => [ 'code' => 200 ], 'body' => json_encode( $places ) ];
	}

	public function test_country_matches_precede_global_matches_and_duplicates_do_not_consume_slots(): void {
		$au = $this->place( 'Richmond, Australia' );
		$us = $this->place( 'Richmond, USA', '37.54', '-77.43' );
		$uk = $this->place( 'Richmond, UK', '51.46', '-0.30' );
		$this->respond( [ $au, $au ] );
		$this->respond( [ $us, $au, $uk ] );
		$result = OC_Rest_API::find_locations( 'Richmond', false, 3 );
		$this->assertSame( [ $au['display_name'], $us['display_name'], $uk['display_name'] ], array_column( $result, 'displayName' ) );
		$this->assertSame( -33.8688, $result[0]['latitude'] );
		$this->assertCount( 2, $GLOBALS['oc_test_http_requests'] );
		foreach ( $GLOBALS['oc_test_http_requests'] as $index => [ $url, $args ] ) {
			parse_str( parse_url( $url, PHP_URL_QUERY ), $query );
			$this->assertSame( 'Richmond', $query['q'] );
			$this->assertSame( '3', $query['limit'] );
			$this->assertSame( 0 === $index ? 'au' : null, $query['countrycodes'] ?? null );
			$this->assertSame( 5, $args['timeout'] );
			$this->assertSame( 0, $args['redirection'] );
			$this->assertSame( 65536, $args['limit_response_size'] );
		}
	}

	public function test_preferred_failures_and_empty_matches_fall_back_worldwide(): void {
		foreach ( [
			new WP_Error( 'timeout' ),
			[ 'response' => [ 'code' => 503 ], 'body' => '' ],
			[ 'response' => [ 'code' => 200 ], 'body' => '{broken' ],
			[ 'response' => [ 'code' => 200 ], 'body' => '{}' ],
			[ 'response' => [ 'code' => 200 ], 'body' => str_repeat( 'x', 65537 ) ],
			[ 'response' => [ 'code' => 200 ], 'body' => '[]' ],
		] as $failure ) {
			$GLOBALS['oc_test_http_responses'] = [ $failure ];
			$this->respond( [ $this->place( 'Paris, France' ) ] );
			$this->assertSame( 'Paris, France', OC_Rest_API::find_location( 'Paris', false )['displayName'] );
			$this->assertSame( [], $GLOBALS['oc_test_http_responses'] );
		}
	}

	public function test_missing_or_malformed_country_uses_only_worldwide_search(): void {
		foreach ( [ '', 'Australia', 'AU,US', [], 'A1:NSW' ] as $country ) {
			$GLOBALS['oc_test_options']['woocommerce_default_country'] = $country;
			$GLOBALS['oc_test_http_requests'] = [];
			$this->respond( [] );
			$this->assertSame( [], OC_Rest_API::find_locations( 'Paris', false ) );
			$this->assertCount( 1, $GLOBALS['oc_test_http_requests'] );
			$this->assertStringNotContainsString( 'countrycodes', $GLOBALS['oc_test_http_requests'][0][0] );
		}
	}

	public function test_full_preferred_page_skips_global_and_clamps_limit(): void {
		foreach ( [ 0 => 1, 99 => 6 ] as $limit => $expected ) {
			$GLOBALS['oc_test_http_requests'] = [];
			$this->respond( array_map( fn ( $i ) => $this->place( 'Australian place ' . $i ), range( 1, 8 ) ) );
			$this->assertCount( $expected, OC_Rest_API::find_locations( 'Place', false, $limit ) );
			$this->assertCount( 1, $GLOBALS['oc_test_http_requests'] );
		}
	}

	public function test_invalid_rows_are_filtered_before_global_fill(): void {
		$this->respond( [ $this->place( 'Invalid latitude', '91' ), $this->place( 'Invalid longitude', '0', '181' ), $this->place( "Bad\nlabel" ), $this->place( str_repeat( 'x', 301 ) ), $this->place( 'Invalid number', 'NaN' ) ] );
		$this->respond( [ $this->place( 'Worldwide match' ) ] );
		$this->assertSame( 'Worldwide match', OC_Rest_API::find_locations( 'Place', false )[0]['displayName'] );
	}

	public function test_global_failure_preserves_preferred_results_but_errors_without_any(): void {
		$this->respond( [ $this->place( 'Australian match' ) ] );
		$GLOBALS['oc_test_http_responses'][] = new WP_Error( 'timeout' );
		$this->assertCount( 1, OC_Rest_API::find_locations( 'Place', false ) );
		$this->respond( [] );
		$GLOBALS['oc_test_http_responses'][] = new WP_Error( 'timeout' );
		$this->assertSame( 'lookup_unavailable', OC_Rest_API::find_locations( 'Place', false )->get_error_code() );
	}

	public function test_invalid_queries_never_reach_http_or_rate_reservation(): void {
		foreach ( [ ' Place', 'x', str_repeat( 'x', 201 ), "Place\n", "Bad\xff" ] as $query ) {
			$this->assertSame( 'invalid_location_query', OC_Rest_API::find_locations( $query )->get_error_code() );
		}
		$this->assertSame( [], $GLOBALS['oc_test_http_requests'] );
	}
}
