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
}
