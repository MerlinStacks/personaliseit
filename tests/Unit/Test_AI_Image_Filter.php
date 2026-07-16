<?php
/**
 * Unit tests for OpenRouter image response parsing.
 *
 * @package OverCustomise
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class Test_AI_Image_Filter extends TestCase {

	private function extract_image( array $body ): array|WP_Error {
		$method = ( new ReflectionClass( OC_AI_Image_Filter::class ) )->getMethod( 'extract_image' );
		$method->setAccessible( true );
		return $method->invoke( null, $body );
	}

	#[Test]
	public function extracts_openrouter_message_image_data_url(): void {
		$png = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true );
		$result = $this->extract_image( [
			'choices' => [
				[ 'message' => [ 'images' => [ [ 'image_url' => [ 'url' => 'data:image/png;base64,' . base64_encode( $png ) ] ] ] ] ],
			],
		] );

		$this->assertIsArray( $result );
		$this->assertSame( 'image/png', $result['mime'] );
		$this->assertSame( $png, $result['bytes'] );
	}

	#[Test]
	public function rejects_response_without_an_image(): void {
		$result = $this->extract_image( [ 'choices' => [ [ 'message' => [ 'content' => 'No image' ] ] ] ] );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_ai_response', $result->get_error_code() );
	}
}
