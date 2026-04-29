<?php
/**
 * Safe command runner wrapper for external tooling calls.
 *
 * Centralises shell execution so command construction and output handling
 * are consistent across upload conversion and print-generation flows.
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

class OC_Command_Runner {

	/**
	 * Run a command with escaped arguments and capture stdout/stderr.
	 *
	 * @param  array<int,string|int|float> $parts Command parts: [binary, arg1, ...].
	 * @return array{code:int,output:array<int,string>,command:string}
	 * @throws \InvalidArgumentException When command parts are invalid.
	 */
	public static function run( array $parts ): array {
		if ( empty( $parts ) ) {
			throw new \InvalidArgumentException( 'Command is empty.' );
		}
		if ( ! function_exists( 'exec' ) ) {
			throw new \InvalidArgumentException( 'Shell execution is not available.' );
		}

		$parts = array_values( array_map( static fn( $v ) => (string) $v, $parts ) );
		$bin   = trim( $parts[0] ?? '' );
		if ( '' === $bin ) {
			throw new \InvalidArgumentException( 'Executable is empty.' );
		}

		// Reject control characters and unusual executable names defensively.
		if ( preg_match( '/[\x00-\x1F\x7F]/', $bin ) ) {
			throw new \InvalidArgumentException( 'Executable contains control characters.' );
		}
		if ( ! preg_match( '/^[A-Za-z0-9_\-\.\/:\\\(\) ]+$/', $bin ) ) {
			throw new \InvalidArgumentException( 'Executable contains unsupported characters.' );
		}

		$cmd = implode(
			' ',
			array_map(
				static fn( string $arg ): string => escapeshellarg( $arg ),
				$parts
			)
		);

		$output = [];
		$code   = 0;
		exec( $cmd . ' 2>&1', $output, $code );

		return [
			'code'    => (int) $code,
			'output'  => array_values( $output ),
			'command' => $cmd,
		];
	}
}
