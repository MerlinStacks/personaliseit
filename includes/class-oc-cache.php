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

	/** Prefix keys with WordPress's atomic group generation token. */
	private static function versioned_key( string $key ): string {
		return wp_cache_get_last_changed( self::GROUP ) . ':' . $key;
	}

	public static function get( string $key ) {
		$value = wp_cache_get( self::versioned_key( $key ), self::GROUP );
		if ( false === $value ) {
			return null;
		}
		return $value;
	}

	public static function set( string $key, $value, int $ttl = self::TTL_LONG ): void {
		wp_cache_set( self::versioned_key( $key ), $value, self::GROUP, $ttl );
	}

	public static function delete( string $key ): void {
		wp_cache_delete( self::versioned_key( $key ), self::GROUP );
	}

	public static function flush_group(): void {
		wp_cache_flush_group( self::GROUP );
		wp_cache_set_last_changed( self::GROUP );
	}

	public static function flush_pattern( string $pattern ): void {
		// Generation invalidation is deliberately group-wide. It is atomic and avoids
		// stale values caused by maintaining a racy mutable key index.
		wp_cache_set_last_changed( self::GROUP );
	}
}
