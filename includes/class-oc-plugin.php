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
		require_once OC_PATH . 'includes/class-oc-woff-converter.php';
		require_once OC_PATH . 'includes/class-oc-db.php';
		require_once OC_PATH . 'includes/class-oc-logger.php';
		require_once OC_PATH . 'includes/class-oc-font-registry.php';
		require_once OC_PATH . 'includes/class-oc-rest-api.php';
		require_once OC_PATH . 'includes/class-oc-blocks-integration.php';
		require_once OC_PATH . 'includes/class-oc-file-cleanup.php';
		require_once OC_PATH . 'includes/class-oc-svg-sanitiser.php';
		require_once OC_PATH . 'includes/class-oc-upload-handler.php';
		require_once OC_PATH . 'includes/class-oc-print-generator.php';
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
			require_once OC_PATH . 'includes/admin/class-oc-admin-clipart.php';
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

		if ( is_admin() ) {
			( new OC_Admin_Menu() )->register();
			( new OC_Admin_Order_Metabox() )->register();
			OC_Admin_Products::register_ajax();
			OC_Admin_Fonts::register_ajax();
			OC_Admin_Colours::register_ajax();
			OC_Admin_Clipart::register_ajax();
		}

		// DB upgrades.
		add_action( 'init', [ OC_DB::class, 'maybe_upgrade' ] );

		// File cleanup cron callback.
		add_action( 'oc_daily_file_cleanup', [ 'OC_File_Cleanup', 'run' ] );
	}

	// -------------------------------------------------------------------------
	// Activation / deactivation
	// -------------------------------------------------------------------------

	public static function activate(): void {
		require_once OC_PATH . 'includes/class-oc-db.php';
		require_once OC_PATH . 'includes/admin/class-oc-admin-mockups.php';
		OC_DB::create_tables();

		// Register mockup taxonomy before flushing rules.
		OC_Admin_Mockups::register_taxonomy();
		flush_rewrite_rules();

		if ( ! wp_next_scheduled( 'oc_daily_file_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'oc_daily_file_cleanup' );
		}

		update_option( 'oc_db_version', OC_DB_VERSION );
	}

	public static function deactivate(): void {
		$timestamp = wp_next_scheduled( 'oc_daily_file_cleanup' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'oc_daily_file_cleanup' );
		}
	}
}
