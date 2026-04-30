<?php
/**
 * Thin wrapper around WC_Logger.
 *
 * Usage:
 *   OC_Logger::log( 'Something happened', 'info' );
 *   OC_Logger::error( 'Something went wrong' );
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

class OC_Logger {

	private const SOURCE = 'overcustomise';

	/** @var \WC_Logger_Interface|null */
	private static ?\WC_Logger_Interface $logger = null;

	private static function get_logger(): \WC_Logger_Interface {
		if ( null === self::$logger ) {
			// Fallback to error_log if WooCommerce is not active.
			if ( ! function_exists( 'wc_get_logger' ) ) {
				self::$logger = new OC_Fallback_Logger();
			} else {
				self::$logger = wc_get_logger();
			}
		}
		return self::$logger;
	}

	public static function log( string $message, string $level = 'debug' ): void {
		self::get_logger()->log(
			$level,
			$message,
			[ 'source' => self::SOURCE ]
		);
	}

	public static function debug( string $message ): void {
		self::log( $message, 'debug' );
	}

	public static function info( string $message ): void {
		self::log( $message, 'info' );
	}

	public static function warning( string $message ): void {
		self::log( $message, 'warning' );
	}

	public static function error( string $message ): void {
		self::log( $message, 'error' );
	}
}

/**
 * Fallback logger when WooCommerce is not active.
 */
class OC_Fallback_Logger {
	public function log( string $level, string $message, array $context = [] ): void {
		$prefix = strtoupper( $level ) . ' ';
		error_log( $prefix . $message );
	}
}
