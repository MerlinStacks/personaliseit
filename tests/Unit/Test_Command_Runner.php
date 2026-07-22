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

	#[Test]
	public function unavailable_executable_does_not_emit_a_php_warning(): void {
		$warnings = [];
		set_error_handler( static function ( int $severity, string $message ) use ( &$warnings ): bool {
			$warnings[] = $message;
			return true;
		} );
		try {
			$result = OC_Command_Runner::run( [ '/definitely/not/an/oc-command' ] );
		} catch ( \InvalidArgumentException $e ) {
			$result = [ 'code' => 127 ];
		} finally {
			restore_error_handler();
		}

		$this->assertEmpty( $warnings );
		$this->assertNotSame( 0, $result['code'] );
	}
}
