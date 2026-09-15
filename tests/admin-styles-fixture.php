<?php
/**
 * Capture the production admin enqueue calls without a WordPress installation.
 * Invoked in a separate PHP process by tests/js/admin-styles.test.mjs.
 *
 * @package OverCustomise\Tests
 */

define( 'ABSPATH', __DIR__ . '/../' );
define( 'OC_PATH', isset( $argv[2] ) ? $argv[2] : ABSPATH );
define( 'OC_URL', 'https://example.test/wp-content/plugins/overcustomise/' );
define( 'OC_ASSETS_URL', OC_URL . 'assets/build/' );
define( 'OC_VERSION', 'test-version' );

$styles = [];
$queue  = [];

function wp_register_style( $handle, $src, $deps = [], $version = false, $media = 'all' ) {
	$GLOBALS['styles'][ $handle ] = compact( 'handle', 'src', 'deps', 'version', 'media' );
}

function wp_enqueue_style( $handle, $src, $deps = [], $version = false, $media = 'all' ) {
	wp_register_style( $handle, $src, $deps, $version, $media );
	$GLOBALS['queue'][] = $handle;
}

function wp_enqueue_script( ...$args ) {}
function wp_register_script( ...$args ) {}

require __DIR__ . '/../includes/admin/class-oc-admin-menu.php';
( new OC_Admin_Menu() )->enqueue_assets( $argv[1] ?? 'overcustomise_page_overcustomise-settings' );

$result = [
	'styles' => (object) $styles,
	'queue'  => $queue,
];
// WordPress is not loaded in this standalone CLI fixture.
echo json_encode( $result, JSON_THROW_ON_ERROR ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
