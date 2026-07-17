<?php
/**
 * Unit tests for webhook payload redaction.
 *
 * @package OverCustomise
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once OC_PATH . 'includes/class-oc-webhooks.php';

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
}
