<?php
/**
 * WordPress object cache wrapper with grouped keys and pattern invalidation.
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

class OC_Cache {

	const GROUP = 'oc_data';

	const TTL_LONG  = 3600;

	const TTL_SHORT = 300;

	protected static string $key_index = 'oc_key_index';

	protected static function get_index(): array {
		$index = wp_cache_get( self::$key_index, self::GROUP );
		return is_array( $index ) ? $index : [];
	}

	protected static function add_to_index( string $key ): void {
		$index = self::get_index();
		if ( ! in_array( $key, $index, true ) ) {
			$index[] = $key;
			wp_cache_set( self::$key_index, $index, self::GROUP, self::TTL_LONG );
		}
	}

	protected static function remove_from_index( string $key ): void {
		$index = self::get_index();
		$pos   = array_search( $key, $index, true );
		if ( false !== $pos ) {
			unset( $index[ $pos ] );
			$index = array_values( $index );
			wp_cache_set( self::$key_index, $index, self::GROUP, self::TTL_LONG );
		}
	}

	public static function get( string $key ) {
		$value = wp_cache_get( $key, self::GROUP );
		if ( false === $value ) {
			return null;
		}
		return $value;
	}

	public static function set( string $key, $value, int $ttl = self::TTL_LONG ): void {
		wp_cache_set( $key, $value, self::GROUP, $ttl );
		self::add_to_index( $key );
	}

	public static function delete( string $key ): void {
		wp_cache_delete( $key, self::GROUP );
		self::remove_from_index( $key );
	}

	public static function flush_group(): void {
		wp_cache_flush_group( self::GROUP );
	}

	public static function flush_pattern( string $pattern ): void {
		$index = self::get_index();
		$regex = '/^' . preg_quote( $pattern, '/' ) . '/';
		foreach ( $index as $key ) {
			if ( preg_match( $regex, $key ) ) {
				wp_cache_delete( $key, self::GROUP );
			}
		}
		$filtered = array_values( array_filter( $index, function ( $k ) use ( $regex ) {
			return ! preg_match( $regex, $k );
		} ) );
		wp_cache_set( self::$key_index, $filtered, self::GROUP, self::TTL_LONG );
	}
}
