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

	/** Return the current atomic generation token for a cache group. */
	public static function generation( string $group = self::GROUP ): string {
		return (string) wp_cache_get_last_changed( $group );
	}

	/** Prefix a key with a generation captured by the caller. */
	private static function versioned_key( string $key, string $generation ): string {
		return $generation . ':' . $key;
	}

	/** Read a value and expose the exact generation used for the lookup. */
	public static function get( string $key, ?string &$generation = null, ?bool &$found = null ) {
		$generation = self::generation();
		$cache_found = false;
		$value = wp_cache_get( self::versioned_key( $key, $generation ), self::GROUP, false, $cache_found );
		$found = $cache_found;

		return $cache_found ? $value : null;
	}

	/** Set a value in either the supplied generation or the current one. */
	public static function set( string $key, $value, int $ttl = self::TTL_LONG, ?string $generation = null ): void {
		$generation = null !== $generation ? $generation : self::generation();
		wp_cache_set( self::versioned_key( $key, $generation ), $value, self::GROUP, $ttl );
	}

	/** Load and cache a value against the generation captured before the query. */
	public static function remember( string $key, callable $loader, int $ttl = self::TTL_LONG ) {
		return self::remember_in_group( self::GROUP, $key, $loader, $ttl );
	}

	/** Generation-safe remember() variant for a dedicated cache group. */
	public static function remember_in_group( string $group, string $key, callable $loader, int $ttl = self::TTL_LONG ) {
		$generation = self::generation( $group );
		$found      = false;
		$value      = wp_cache_get( self::versioned_key( $key, $generation ), $group, false, $found );
		if ( $found ) {
			return $value;
		}

		$value = $loader();
		// Never publish a query started in an older generation into the current one.
		wp_cache_set( self::versioned_key( $key, $generation ), $value, $group, $ttl );

		return $value;
	}

	public static function delete( string $key ): void {
		// Advancing the generation also protects against an in-flight remember().
		wp_cache_set_last_changed( self::GROUP );
	}

	public static function flush_group(): void {
		wp_cache_flush_group( self::GROUP );
		self::invalidate_group( self::GROUP );
	}

	public static function flush_pattern( string $pattern ): void {
		// Generation invalidation is deliberately group-wide. It is atomic and avoids
		// stale values caused by maintaining a racy mutable key index.
		self::invalidate_group( self::GROUP );
	}

	/** Atomically advance a cache group's generation. */
	public static function invalidate_group( string $group ): void {
		wp_cache_set_last_changed( $group );
	}
}
