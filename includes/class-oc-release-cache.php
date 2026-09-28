<?php
/**
 * Release identity and one-time invalidation of deployment caches.
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

class OC_Release_Cache {

	/** Catalogue writes are coalesced until after their request has finished. */
	private static array $content_pending = [];

	/** Include the build identity so replacement ZIPs also invalidate caches. */
	public static function version(): string {
		static $version = null;
		if ( null === $version ) {
			$manifest = OC_PATH . 'assets/build/release.json';
			$hash     = is_file( $manifest ) ? hash_file( 'sha256', $manifest ) : '';
			$version  = OC_VERSION . '-' . substr( (string) $hash, 0, 16 );
		}
		return $version;
	}

	/** Run after other plugins have registered their cache integrations. */
	public static function register(): void {
		add_action( 'wp_loaded', [ self::class, 'maybe_purge' ], 100 );
		add_action( 'init', [ self::class, 'prepare_refresh' ], 1 );
		add_action( 'template_redirect', [ self::class, 'refresh_headers' ], 0 );
		add_action( 'oc_catalogue_cache_invalidated', [ self::class, 'queue_content_purge' ] );
		add_action( 'updated_option', [ self::class, 'option_changed' ] );
		add_action( 'added_option', [ self::class, 'option_changed' ] );
		add_action( 'deleted_option', [ self::class, 'option_changed' ] );
		add_action( 'edit_attachment', [ self::class, 'media_changed' ] );
		add_action( 'delete_attachment', [ self::class, 'media_changed' ] );
		add_action( 'shutdown', [ self::class, 'purge_pending_content' ], 0 );
		add_action( 'oc_retry_content_cache_purge', [ self::class, 'retry_content_purge' ] );
	}

	/** Settings and print-method changes alter embedded storefront state. */
	public static function option_changed( string $option ): void {
		if ( in_array( $option, [ 'oc_settings', 'oc_print_methods' ], true ) ) {
			OC_Cache::invalidate_group( OC_Cache::GROUP );
		}
	}

	/** Public media replacement can change mockups and template artwork. */
	public static function media_changed( int $attachment_id ): void {
		if ( ! get_post_meta( $attachment_id, '_oc_artwork', true ) ) {
			OC_Cache::invalidate_group( OC_Cache::GROUP );
		}
	}

	/** Shared fonts, colours and designs can affect multiple assigned products. */
	public static function queue_content_purge(): void {
		self::$content_pending[ get_current_blog_id() ] = true;
	}

	/** Retry an unsuccessful provider call through WordPress cron. */
	public static function retry_content_purge(): void {
		self::queue_content_purge();
		self::purge_pending_content();
	}

	/** Purge once after a batch of catalogue writes, including removed assignments. */
	public static function purge_pending_content(): void {
		$sites                 = array_keys( self::$content_pending );
		self::$content_pending = [];
		foreach ( $sites as $site_id ) {
			$switched = get_current_blog_id() !== $site_id;
			if ( $switched ) {
				switch_to_blog( $site_id );
			}
			try {
				self::purge_content();
			} finally {
				if ( $switched ) {
					restore_current_blog();
				}
			}
		}
	}

	/** Content writes need only a page purge, with retry on provider exceptions. */
	private static function purge_content(): void {
		try {
			self::purge_pages();
			do_action( 'oc_content_cache_purge' );
		} catch ( \Throwable $error ) {
			OC_Logger::error( 'Catalogue cache purge failed: ' . $error->getMessage() );
			if ( ! wp_next_scheduled( 'oc_retry_content_cache_purge' ) ) {
				wp_schedule_single_event( time() + 60, 'oc_retry_content_cache_purge' );
			}
		}
	}

	/** Page-cache APIs deliberately exclude object/session and optimisation caches. */
	private static function purge_pages(): void {
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
		}
		if ( function_exists( 'w3tc_flush_posts' ) ) {
			w3tc_flush_posts();
		}
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
		}
		if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
			sg_cachepress_purge_cache();
		}
		do_action( 'litespeed_purge_all' );
	}

	/** A user-requested chunk recovery must render fresh page data. */
	public static function prepare_refresh(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only cache bypass; no state mutation or privileged action.
		if ( isset( $_GET['oc_cache_refresh'] ) && ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
	}

	/** Prevent a recovered page from being stored by downstream caches. */
	public static function refresh_headers(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only cache bypass; no state mutation or privileged action.
		if ( isset( $_GET['oc_cache_refresh'] ) ) {
			nocache_headers();
		}
	}

	/** Purge once per site and release, including ZIP and filesystem deployments. */
	public static function maybe_purge(): void {
		$version = self::version();
		if ( get_option( 'oc_cache_release' ) === $version ) {
			return;
		}

		// An atomic option prevents concurrent requests from stampeding page caches.
		$lock     = 'oc_cache_release_lock';
		$existing = (string) get_option( $lock, '' );
		if ( '' !== $existing && (int) $existing < time() ) {
			self::release_lock( $existing );
		}
		$owner = ( time() + 300 ) . ':' . wp_generate_uuid4();
		if ( ! add_option( $lock, $owner, '', false ) ) {
			return;
		}
		$failed = false;
		try {
			if ( get_option( 'oc_cache_release' ) === $version ) {
				return;
			}
			$previous = (string) get_option( 'oc_cache_release', '' );
			self::purge_pages();
			if ( function_exists( 'w3tc_flush_minify' ) ) {
				w3tc_flush_minify();
			}
			if ( is_callable( [ 'autoptimizeCache', 'clearall' ] ) ) {
				call_user_func( [ 'autoptimizeCache', 'clearall' ] );
			}
			// Hosts/CDNs without a local purge API can subscribe to this action.
			do_action( 'oc_release_cache_purge', $version, $previous );
			update_option( 'oc_cache_release', $version, false );
		} catch ( \Throwable $error ) {
			// A failing third-party cache must not take the storefront down. Retain
			// the lease for a short cooldown, then retry on a later request.
			$failed = true;
			OC_Logger::error( 'Release cache purge failed: ' . $error->getMessage() );
		} finally {
			if ( ! $failed ) {
				self::release_lock( $owner );
			}
		}
	}

	/** Compare-and-delete: an expired request must not release a newer lease. */
	private static function release_lock( string $owner ): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic lease ownership check; invalidate the option cache below.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s",
				'oc_cache_release_lock',
				$owner
			)
		);
		wp_cache_delete( 'oc_cache_release_lock', 'options' );
	}
}
