<?php
/**
 * Plugin bootstrap — loads all includes and wires up hooks.
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

class OC_Plugin {

	/** @var OC_Plugin|null Singleton instance. */
	private static ?OC_Plugin $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->init();
		}
		return self::$instance;
	}

	private function init(): void {
		$this->load_includes();
		$this->register_hooks();
	}

	private function load_includes(): void {
		// Core utilities.
		require_once OC_PATH . 'includes/class-oc-tooltips.php';
		require_once OC_PATH . 'includes/class-oc-cache.php';
		require_once OC_PATH . 'includes/class-oc-woff-converter.php';
		require_once OC_PATH . 'includes/class-oc-db.php';
		require_once OC_PATH . 'includes/class-oc-logger.php';
		require_once OC_PATH . 'includes/class-oc-command-runner.php';
		require_once OC_PATH . 'includes/class-oc-font-registry.php';
		require_once OC_PATH . 'includes/class-oc-render-math.php';
		require_once OC_PATH . 'includes/class-oc-render-spec.php';
		require_once OC_PATH . 'includes/class-oc-rest-api.php';
		require_once OC_PATH . 'includes/class-oc-blocks-integration.php';
		require_once OC_PATH . 'includes/class-oc-file-cleanup.php';
		require_once OC_PATH . 'includes/class-oc-svg-sanitiser.php';
		require_once OC_PATH . 'includes/class-oc-upload-handler.php';
		require_once OC_PATH . 'includes/class-oc-preview-generator.php';
		require_once OC_PATH . 'includes/class-oc-print-generator.php';
		require_once OC_PATH . 'includes/class-oc-print-queue.php';
		require_once OC_PATH . 'includes/class-oc-autosave.php';
		require_once OC_PATH . 'includes/class-oc-vdp.php';
		require_once OC_PATH . 'includes/class-oc-webhooks.php';
		require_once OC_PATH . 'includes/print/class-oc-print-base.php';
		require_once OC_PATH . 'includes/print/class-oc-print-engraving.php';
		require_once OC_PATH . 'includes/print/class-oc-print-uv.php';
		require_once OC_PATH . 'includes/print/class-oc-print-sublimation.php';
		require_once OC_PATH . 'includes/print/class-oc-print-embroidery.php';

		// Settings must be available outside admin (REST API + frontend use it).
		require_once OC_PATH . 'includes/admin/class-oc-admin-settings.php';

		// Mockups taxonomy must register on all requests (frontend queries need it).
		require_once OC_PATH . 'includes/admin/class-oc-admin-mockups.php';

		// Frontend — loaded on all requests (cart hooks fire in both admin AJAX and frontend).
		require_once OC_PATH . 'includes/frontend/class-oc-frontend.php';
		require_once OC_PATH . 'includes/frontend/class-oc-cart.php';

		// Admin.
		if ( is_admin() ) {
			require_once OC_PATH . 'includes/admin/class-oc-admin-menu.php';
			require_once OC_PATH . 'includes/admin/class-oc-admin-products.php';
			require_once OC_PATH . 'includes/admin/class-oc-admin-fonts.php';
			require_once OC_PATH . 'includes/admin/class-oc-admin-colours.php';
			require_once OC_PATH . 'includes/admin/class-oc-admin-image-filters.php';
			require_once OC_PATH . 'includes/admin/class-oc-admin-clipart.php';
			require_once OC_PATH . 'includes/admin/class-oc-admin-customer-uploads.php';
			require_once OC_PATH . 'includes/admin/class-oc-admin-print-queue.php';
			require_once OC_PATH . 'includes/admin/class-oc-admin-print-methods.php';
			require_once OC_PATH . 'includes/admin/class-oc-admin-order-metabox.php';
		}
	}

	private function register_hooks(): void {
		// Font registry (MIME types + @font-face CSS — both admin and frontend).
		( new OC_Font_Registry() )->register();

		// REST API.
		( new OC_Rest_API() )->register();

		// Mockup taxonomy + AJAX handlers.
		OC_Admin_Mockups::init();

		// Frontend hooks (non-admin context, plus cart/order hooks for AJAX/checkout).
		( new OC_Frontend() )->register();
		( new OC_Cart() )->register();
		( new OC_Blocks_Integration() )->register();

		// Print file generation (fires on checkout order creation).
		( new OC_Print_Generator() )->register();

		// Print queue processor.
		OC_Print_Queue::instance()->register();

		// Webhooks.
		( new OC_Webhooks() )->register();

		if ( is_admin() ) {
			( new OC_Admin_Menu() )->register();
			( new OC_Admin_Order_Metabox() )->register();
			OC_Admin_Customer_Uploads::register_hooks();
			OC_Admin_Products::register_ajax();
			OC_Admin_Fonts::register_ajax();
			OC_Admin_Colours::register_ajax();
			OC_Admin_Image_Filters::register_ajax();
			OC_Admin_Clipart::register_ajax();
		}

		// Register custom cron recurrence.
		add_filter( 'cron_schedules', [ self::class, 'add_cron_schedules' ] );

		// DB upgrades.
		add_action( 'init', [ OC_DB::class, 'maybe_upgrade' ] );
		add_action( 'init', [ self::class, 'ensure_cron_events' ] );

		// File cleanup cron callback.
		add_action( 'oc_daily_file_cleanup', [ 'OC_File_Cleanup', 'run' ] );
	}

	// -------------------------------------------------------------------------
	// Cron schedules
	// -------------------------------------------------------------------------

	public static function add_cron_schedules( array $schedules ): array {
		$schedules['oc_every_minute'] = [
			'interval' => 60,
			'display'  => __( 'Every Minute', 'overcustomise' ),
		];
		return $schedules;
	}

	/** Ensure queue/file cleanup events exist even if activation scheduling failed. */
	public static function ensure_cron_events(): void {
		if ( ! wp_next_scheduled( 'oc_daily_file_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'oc_daily_file_cleanup' );
		}

		if ( ! wp_next_scheduled( 'oc_process_print_queue' ) ) {
			wp_schedule_event( time(), 'oc_every_minute', 'oc_process_print_queue' );
		}
	}

	// -------------------------------------------------------------------------
	// Activation / deactivation
	// -------------------------------------------------------------------------

	public static function activate(): void {
		require_once OC_PATH . 'includes/class-oc-db.php';
		require_once OC_PATH . 'includes/admin/class-oc-admin-mockups.php';
		add_filter( 'cron_schedules', [ self::class, 'add_cron_schedules' ] );
		OC_DB::create_tables();

		// Register mockup taxonomy before flushing rules.
		OC_Admin_Mockups::register_taxonomy();
		flush_rewrite_rules();

		if ( ! wp_next_scheduled( 'oc_daily_file_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'oc_daily_file_cleanup' );
		}

		if ( ! wp_next_scheduled( 'oc_process_print_queue' ) ) {
			wp_schedule_event( time(), 'oc_every_minute', 'oc_process_print_queue' );
		}

		update_option( 'oc_db_version', OC_DB_VERSION );
	}

	public static function deactivate(): void {
		$timestamp = wp_next_scheduled( 'oc_daily_file_cleanup' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'oc_daily_file_cleanup' );
		}

		$timestamp = wp_next_scheduled( 'oc_process_print_queue' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'oc_process_print_queue' );
		}

		wp_clear_scheduled_hook( 'oc_process_print_queue_now' );

		// Clear webhook delivery options.
		global $wpdb;
		$options = $wpdb->get_results( "SELECT option_name FROM {$wpdb->prefix}options WHERE option_name LIKE 'oc_wh_delivery_%'" );
		foreach ( $options as $opt ) {
			delete_option( $opt->option_name );
		}
	}

	public static function uninstall(): void {
		require_once OC_PATH . 'includes/class-oc-db.php';
		OC_DB::drop_all_tables();

		delete_option( 'oc_db_version' );
		delete_option( 'oc_settings' );
		delete_option( 'oc_print_methods' );

		wp_clear_scheduled_hook( 'oc_daily_file_cleanup' );
		wp_clear_scheduled_hook( 'oc_process_print_queue' );
		wp_clear_scheduled_hook( 'oc_process_print_queue_now' );

		unregister_taxonomy( 'oc_mockup', 'attachment' );

		$upload_dir = wp_upload_dir();
		$upload_path = $upload_dir['basedir'] . '/overcustomise/';
		if ( is_dir( $upload_path ) ) {
			self::delete_directory( $upload_path );
		}

		wp_cache_flush_group( 'oc_data' );

		// Clear webhook delivery options.
		global $wpdb;
		$options = $wpdb->get_results( "SELECT option_name FROM {$wpdb->prefix}options WHERE option_name LIKE 'oc_wh_delivery_%'" );
		foreach ( $options as $opt ) {
			delete_option( $opt->option_name );
		}
	}

	private static function delete_directory( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$items = scandir( $dir );
		if ( false === $items ) {
			return;
		}
		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}
			$path = $dir . DIRECTORY_SEPARATOR . $item;
			is_dir( $path ) ? self::delete_directory( $path ) : wp_delete_file( $path );
		}
		rmdir( $dir );
	}
}
