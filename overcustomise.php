<?php
/**
 * Plugin Name: OverCustomise
 * Plugin URI:  https://customkings.com.au
 * Description: WooCommerce product customiser — text, artwork and print file generation for Custom Kings.
 * Version:     1.13.4
 * Requires at least: 6.8
 * Requires PHP: 8.2
 * Author:      Custom Kings
 * License:     GPL-2.0-or-later
 * Text Domain: overcustomise
 * Domain Path: /languages
 * Requires Plugins: woocommerce
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

// Plugin constants.
define( 'OC_VERSION',     '1.13.4' );
define( 'OC_FILE',        __FILE__ );
define( 'OC_PATH',        plugin_dir_path( __FILE__ ) );
define( 'OC_URL',         plugin_dir_url( __FILE__ ) );
define( 'OC_ASSETS_URL',  OC_URL . 'assets/build/' );
define( 'OC_DB_VERSION',  '1.13.7' );

/** Return the bundled Composer autoloader path. */
function oc_composer_autoload_path(): string {
	return OC_PATH . 'vendor/autoload.php';
}

/** Human-readable dependency error for source installs missing Composer vendor files. */
function oc_missing_composer_message(): string {
	return sprintf(
		/* translators: %s: Composer autoload path. */
		__( 'OverCustomise is missing bundled Composer dependencies at %s. Install the release ZIP that includes vendor files, or run composer install in the plugin directory before activating this source checkout.', 'overcustomise' ),
		oc_composer_autoload_path()
	);
}

/** Load Composer dependencies when they are bundled with the plugin. */
function oc_load_composer_dependencies(): bool {
	$autoloader = oc_composer_autoload_path();
	if ( ! file_exists( $autoloader ) ) {
		return false;
	}

	require_once $autoloader;
	return true;
}

oc_load_composer_dependencies();

// Declare WooCommerce feature compatibility.
add_action( 'before_woocommerce_init', function (): void {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
	}
} );

// Boot the plugin once WooCommerce is confirmed active.
add_action( 'plugins_loaded', function (): void {
	if ( ! file_exists( oc_composer_autoload_path() ) ) {
		add_action( 'admin_notices', function (): void {
			echo '<div class="notice notice-error"><p>' . esc_html( oc_missing_composer_message() ) . '</p></div>';
		} );
		return;
	}

	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', function (): void {
			echo '<div class="notice notice-error"><p>'
				. esc_html__( 'OverCustomise requires WooCommerce to be active.', 'overcustomise' )
				. '</p></div>';
		} );
		return;
	}

	require_once OC_PATH . 'includes/class-oc-plugin.php';
	OC_Plugin::instance();
}, 10 );

/** Activation callback. */
function oc_activate_plugin(): void {
	if ( ! file_exists( oc_composer_autoload_path() ) ) {
		wp_die( esc_html( oc_missing_composer_message() ) );
	}

	require_once OC_PATH . 'includes/class-oc-plugin.php';
	OC_Plugin::activate();
}

// Activation / deactivation hooks.
// The plugin class must be loaded here because activation hooks fire before plugins_loaded.
require_once plugin_dir_path( __FILE__ ) . 'includes/class-oc-plugin.php';
register_activation_hook( __FILE__, 'oc_activate_plugin' );
register_deactivation_hook( __FILE__, [ 'OC_Plugin', 'deactivate' ] );
register_uninstall_hook( __FILE__, [ 'OC_Plugin', 'uninstall' ] );
