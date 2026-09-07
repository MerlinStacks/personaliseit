<?php
/**
 * WordPress integration-test bootstrap.
 *
 * @package OverCustomise
 */

if ( ! isset( $_oc_wp_tests_dir ) || ! is_string( $_oc_wp_tests_dir ) ) {
	throw new RuntimeException( 'WP_TESTS_DIR is required for integration tests.' );
}

require_once $_oc_wp_tests_dir . '/includes/bootstrap.php';

defined( 'OC_PATH' ) || define( 'OC_PATH', dirname( __DIR__ ) . '/' );
defined( 'OC_VERSION' ) || define( 'OC_VERSION', '1.16.1-test' );
defined( 'OC_FILE' ) || define( 'OC_FILE', OC_PATH . 'overcustomise.php' );
defined( 'OC_URL' ) || define( 'OC_URL', 'http://example.com/wp-content/plugins/overcustomise/' );
defined( 'OC_ASSETS_URL' ) || define( 'OC_ASSETS_URL', OC_URL . 'assets/build/' );
defined( 'OC_DB_VERSION' ) || define( 'OC_DB_VERSION', '1.16.2' );

require_once OC_PATH . 'includes/class-oc-cache.php';
require_once OC_PATH . 'includes/class-oc-woff-converter.php';
require_once OC_PATH . 'includes/class-oc-db.php';
require_once OC_PATH . 'includes/class-oc-logger.php';
require_once OC_PATH . 'includes/class-oc-command-runner.php';
require_once OC_PATH . 'includes/class-oc-system-status.php';
require_once OC_PATH . 'includes/class-oc-font-registry.php';
require_once OC_PATH . 'includes/class-oc-render-math.php';
require_once OC_PATH . 'includes/class-oc-render-spec.php';
require_once OC_PATH . 'includes/admin/class-oc-admin-settings.php';
require_once OC_PATH . 'includes/admin/class-oc-admin-mockups.php';
require_once OC_PATH . 'includes/class-oc-svg-sanitiser.php';
require_once OC_PATH . 'includes/class-oc-upload-handler.php';
require_once OC_PATH . 'includes/class-oc-ai-image-filter.php';
require_once OC_PATH . 'includes/class-oc-preview-generator.php';
require_once OC_PATH . 'includes/class-oc-vdp.php';
require_once OC_PATH . 'includes/class-oc-rest-api.php';
require_once OC_PATH . 'includes/frontend/class-oc-cart.php';
require_once OC_PATH . 'includes/print/class-oc-print-base.php';
require_once OC_PATH . 'includes/print/class-oc-print-engraving.php';
require_once OC_PATH . 'includes/print/class-oc-print-uv.php';
require_once OC_PATH . 'includes/print/class-oc-print-sublimation.php';
require_once OC_PATH . 'includes/print/class-oc-print-embroidery.php';
require_once OC_PATH . 'includes/class-oc-print-queue.php';
require_once OC_PATH . 'includes/class-oc-print-generator.php';
require_once OC_PATH . 'includes/class-oc-file-cleanup.php';

// WordPress has already fired setup hooks, so create the plugin schema directly.
OC_DB::create_tables();
