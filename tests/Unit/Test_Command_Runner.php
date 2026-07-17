<?php
/**
 * Unit tests for shell-free command execution.
 *
 * @package OverCustomise
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class Test_Command_Runner extends TestCase {
	#[Test]
	public function passes_metacharacters_as_one_literal_argument(): void {
		if ( ! function_exists( 'proc_open' ) ) {
			$this->markTestSkipped( 'proc_open is unavailable.' );
		}

		$argument = '$(touch /tmp/never); & | `nope`';
		$result   = OC_Command_Runner::run( [ PHP_BINARY, '-r', 'echo $argv[1];', $argument ] );

		$this->assertSame( 0, $result['code'] );
		$this->assertSame( [ $argument ], $result['output'] );
	}

	#[Test]
	public function rejects_non_scalar_arguments(): void {
		$this->expectException( \InvalidArgumentException::class );
		OC_Command_Runner::run( [ PHP_BINARY, [] ] );
	}

	#[Test]
	public function rejects_control_characters_in_arguments(): void {
		$this->expectException( \InvalidArgumentException::class );
		OC_Command_Runner::run( [ PHP_BINARY, "bad\0argument" ] );
	}
}
