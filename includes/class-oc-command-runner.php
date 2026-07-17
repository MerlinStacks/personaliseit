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
		if ( count( $parts ) > 128 ) {
			throw new \InvalidArgumentException( 'Command contains too many arguments.' );
		}
		$total_length = 0;
		foreach ( $parts as $part ) {
			if ( ! is_string( $part ) && ! is_int( $part ) && ! is_float( $part ) ) {
				throw new \InvalidArgumentException( 'Command contains an invalid argument.' );
			}
			if ( preg_match( '/[\x00-\x1F\x7F]/', (string) $part ) ) {
				throw new \InvalidArgumentException( 'Command contains control characters.' );
			}
			$total_length += strlen( (string) $part );
		}
		if ( $total_length > 65536 ) {
			throw new \InvalidArgumentException( 'Command exceeds the safe length limit.' );
		}

		$parts = array_values( array_map( static fn( string|int|float $value ): string => (string) $value, $parts ) );
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
			throw new \InvalidArgumentException( 'Safe process execution is not available.' );
		}

		$descriptors = [ 0 => [ 'pipe', 'r' ], 1 => [ 'pipe', 'w' ], 2 => [ 'pipe', 'w' ] ];
		try {
			$process = proc_open( $parts, $descriptors, $pipes, null, null, [ 'bypass_shell' => true ] );
		} catch ( \Throwable $e ) {
			throw new \InvalidArgumentException( 'Could not start command.', 0, $e );
		}
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
		$stopped = false;
		do {
			$buffer .= (string) stream_get_contents( $pipes[1], 65536 ) . (string) stream_get_contents( $pipes[2], 65536 );
			if ( strlen( $buffer ) > self::MAX_OUTPUT_BYTES ) {
				proc_terminate( $process, 9 );
				$buffer = substr( $buffer, 0, self::MAX_OUTPUT_BYTES ) . "\n[output truncated]";
				$code = 125;
				$stopped = true;
				break;
			}
			$status = proc_get_status( $process );
			if ( ! is_array( $status ) ) {
				$code = 125;
				break;
			}
			if ( ! $status['running'] ) { $code = (int) $status['exitcode']; break; }
			if ( microtime( true ) - $started >= $timeout ) {
				proc_terminate( $process, 9 );
				$buffer .= "\n[command timed out]";
				$code = 124;
				$stopped = true;
				break;
			}
			usleep( 10000 );
		} while ( true );
		$buffer .= (string) stream_get_contents( $pipes[1], 65536 ) . (string) stream_get_contents( $pipes[2], 65536 );
		if ( strlen( $buffer ) > self::MAX_OUTPUT_BYTES + 32 ) {
			$buffer = substr( $buffer, 0, self::MAX_OUTPUT_BYTES ) . "\n[output truncated]";
		}
		fclose( $pipes[1] ); fclose( $pipes[2] );
		$closed = proc_close( $process );
		if ( ! $stopped && $code < 0 && $closed >= 0 ) $code = $closed;
		$output = preg_split( '/\R/', trim( $buffer ) ) ?: [];

		return [
			'code'    => (int) $code,
			'output'  => array_values( $output ),
			'command' => $cmd,
		];
	}
}
