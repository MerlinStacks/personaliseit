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
	private const TIMEOUT_SECONDS = 120;
	private const MAX_OUTPUT_BYTES = 1048576;

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
		$parts = array_values( array_map( static fn( $v ) => (string) $v, $parts ) );
		$bin   = trim( $parts[0] ?? '' );
		if ( '' === $bin ) {
			throw new \InvalidArgumentException( 'Executable is empty.' );
		}

		// Reject control characters and unusual executable names defensively.
		if ( preg_match( '/[\x00-\x1F\x7F]/', $bin ) ) {
			throw new \InvalidArgumentException( 'Executable contains control characters.' );
		}
		if ( ! preg_match( '/^[A-Za-z0-9_\-\.\/\\\\\(\) :]+$/', $bin ) ) {
			throw new \InvalidArgumentException( 'Executable contains unsupported characters.' );
		}

		$cmd = implode(
			' ',
			array_map(
				static fn( string $arg ): string => escapeshellarg( $arg ),
				$parts
			)
		);

		if ( ! function_exists( 'proc_open' ) ) {
			return self::run_with_exec( $cmd );
		}

		$descriptors = [ 0 => [ 'pipe', 'r' ], 1 => [ 'pipe', 'w' ], 2 => [ 'pipe', 'w' ] ];
		$process = proc_open( $parts, $descriptors, $pipes, null, null, [ 'bypass_shell' => true ] );
		if ( ! is_resource( $process ) ) throw new \InvalidArgumentException( 'Could not start command.' );
		fclose( $pipes[0] );
		stream_set_blocking( $pipes[1], false );
		stream_set_blocking( $pipes[2], false );
		$timeout = function_exists( 'apply_filters' )
			? (int) apply_filters( 'oc_command_timeout_seconds', self::TIMEOUT_SECONDS, $parts )
			: self::TIMEOUT_SECONDS;
		$timeout = max( 1, min( 900, $timeout ) );
		$started = microtime( true );
		$buffer  = '';
		$code    = -1;
		do {
			$buffer .= (string) stream_get_contents( $pipes[1] ) . (string) stream_get_contents( $pipes[2] );
			if ( strlen( $buffer ) > self::MAX_OUTPUT_BYTES ) {
				proc_terminate( $process, 9 );
				$buffer = substr( $buffer, 0, self::MAX_OUTPUT_BYTES ) . "\n[output truncated]";
				break;
			}
			$status = proc_get_status( $process );
			if ( ! $status['running'] ) { $code = (int) $status['exitcode']; break; }
			if ( microtime( true ) - $started >= $timeout ) {
				proc_terminate( $process, 9 );
				$buffer .= "\n[command timed out]";
				break;
			}
			usleep( 10000 );
		} while ( true );
		$buffer .= (string) stream_get_contents( $pipes[1] ) . (string) stream_get_contents( $pipes[2] );
		fclose( $pipes[1] ); fclose( $pipes[2] );
		$closed = proc_close( $process );
		if ( $code < 0 && $closed >= 0 ) $code = $closed;
		$output = preg_split( '/\R/', trim( $buffer ) ) ?: [];

		return [
			'code'    => (int) $code,
			'output'  => array_values( $output ),
			'command' => $cmd,
		];
	}

	/** Use the previous escaped exec path when proc_open is unavailable. */
	private static function run_with_exec( string $cmd ): array {
		if ( ! function_exists( 'exec' ) ) {
			throw new \InvalidArgumentException( 'Safe process execution is not available.' );
		}

		$output = [];
		$code   = -1;
		exec( $cmd . ' 2>&1', $output, $code ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec
		$buffer = implode( "\n", array_map( 'strval', $output ) );
		if ( strlen( $buffer ) > self::MAX_OUTPUT_BYTES ) {
			$buffer = substr( $buffer, 0, self::MAX_OUTPUT_BYTES ) . "\n[output truncated]";
		}

		return [
			'code'    => (int) $code,
			'output'  => '' === $buffer ? [] : ( preg_split( '/\R/', $buffer ) ?: [] ),
			'command' => $cmd,
		];
	}
}
