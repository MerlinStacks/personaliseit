<?php
/**
 * PHPUnit bootstrap for OverCustomise.
 *
 * Unit tests (tests/Unit/) run without WordPress — they test pure PHP classes.
 * Integration tests (tests/Integration/) load the full WP + WC test suite.
 *
 * For unit tests only:
 *   vendor/bin/phpunit --testsuite Unit
 *
 * For integration tests (requires WP test environment):
 *   WP_TESTS_DIR=/path/to/wp-tests vendor/bin/phpunit --testsuite Integration
 */

// Composer autoloader.
$autoloader = dirname( __DIR__ ) . '/vendor/autoload.php';
if ( file_exists( $autoloader ) ) {
	require_once $autoloader;
}

// ── Unit test stubs ──────────────────────────────────────────────────────────
// Define bare-minimum WordPress stubs so unit-tested classes can be loaded
// without a full WP environment.

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

if ( ! defined( 'OC_PATH' ) ) {
	define( 'OC_PATH', dirname( __DIR__ ) . '/' );
}

if ( ! defined( 'OC_VERSION' ) ) {
	define( 'OC_VERSION', '1.0.0-test' );
}

// Stub commonly used WP functions so unit tests don't need a WP environment.
if ( ! function_exists( 'wp_upload_dir' ) ) {
	function wp_upload_dir(): array {
		return [
			'basedir' => sys_get_temp_dir(),
			'baseurl' => 'http://example.com/wp-content/uploads',
		];
	}
}

if ( ! function_exists( 'wp_mkdir_p' ) ) {
	function wp_mkdir_p( string $dir ): bool {
		if ( ! is_dir( $dir ) ) {
			return mkdir( $dir, 0755, true );
		}
		return true;
	}
}

if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( string $value ): string {
		return rtrim( $value, '/\\' ) . '/';
	}
}

if ( ! function_exists( 'sanitize_file_name' ) ) {
	function sanitize_file_name( string $name ): string {
		return preg_replace( '/[^a-zA-Z0-9._-]/', '-', $name );
	}
}

if ( ! function_exists( 'current_time' ) ) {
	function current_time( string $type, bool $gmt = false ): string {
		return gmdate( 'Y-m-d H:i:s' );
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( string $option, $default = false ) {
		return $default;
	}
}

if ( ! function_exists( 'wp_parse_args' ) ) {
	function wp_parse_args( $args, $defaults = [] ): array {
		return array_merge( (array) $defaults, (array) $args );
	}
}

if ( ! class_exists( 'OC_Admin_Settings' ) ) {
	class OC_Admin_Settings {
		public static function get( string $key = '' ) {
			$defaults = [
				'file_retention_days'   => 90,
				'max_upload_size_mb'    => 10,
				'bleed_mm'              => 3,
				'crop_mark_style'       => 'standard',
			];
			return $key ? ( $defaults[ $key ] ?? null ) : $defaults;
		}
	}
}

if ( ! class_exists( 'OC_Logger' ) ) {
	class OC_Logger {
		public static function debug( string $msg ): void {}
		public static function info( string $msg ): void {}
		public static function warning( string $msg ): void {}
		public static function error( string $msg ): void {}
	}
}

if ( ! function_exists( 'wp_tempnam' ) ) {
	function wp_tempnam( string $prefix = 'tmp' ): string {
		return tempnam( sys_get_temp_dir(), $prefix );
	}
}

if ( ! function_exists( 'wp_generate_uuid4' ) ) {
	function wp_generate_uuid4(): string {
		return '00000000-0000-4000-8000-000000000000';
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( string $key ): string {
		return strtolower( preg_replace( '/[^a-zA-Z0-9_-]/', '', $key ) );
	}
}

if ( ! function_exists( 'sanitize_hex_color' ) ) {
	function sanitize_hex_color( string $color ): string {
		return preg_match( '/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $color ) ? $color : '';
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( string $str ): string {
		return trim( strip_tags( $str ) );
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $maybeint ): int {
		return abs( (int) $maybeint );
	}
}

if ( ! function_exists( '__' ) ) {
	function __( string $text, string $domain = 'default' ): string {
		return $text;
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ): bool {
		return $thing instanceof WP_Error;
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private string $code;
		private string $message;
		public function __construct( string $code = '', string $message = '' ) {
			$this->code    = $code;
			$this->message = $message;
		}
		public function get_error_message(): string { return $this->message; }
		public function get_error_code(): string    { return $this->code; }
	}
}

if ( ! function_exists( 'pathinfo' ) ) {
	// PHP built-in — just in case.
}

// Stubs for WP media functions used by OC_Upload_Handler (full paths only tested in integration).
if ( ! function_exists( 'wp_insert_attachment' ) ) {
	function wp_insert_attachment( array $args, string $path ): int { return 0; }
}
if ( ! function_exists( 'wp_unique_filename' ) ) {
	function wp_unique_filename( string $dir, string $filename ): string { return $filename; }
}
if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
	function wp_generate_attachment_metadata( int $id, string $path ): array { return []; }
}
if ( ! function_exists( 'wp_update_attachment_metadata' ) ) {
	function wp_update_attachment_metadata( int $id, array $meta ): void {}
}
if ( ! function_exists( 'update_post_meta' ) ) {
	function update_post_meta( int $id, string $key, $value ): void {}
}
if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( int $id, string $key, bool $single = false ) {
		global $oc_test_post_meta;
		return $oc_test_post_meta[ $id ][ $key ] ?? ( $single ? '' : [] );
	}
}
if ( ! function_exists( 'get_attached_file' ) ) {
	function get_attached_file( int $id ) {
		global $oc_test_attached_files;
		return $oc_test_attached_files[ $id ] ?? '';
	}
}
if ( ! function_exists( 'wp_get_attachment_url' ) ) {
	function wp_get_attachment_url( int $id ): string { return ''; }
}

// Load classes under test (unit tests only need the pure PHP classes).
require_once OC_PATH . 'includes/class-oc-svg-sanitiser.php';
require_once OC_PATH . 'includes/class-oc-command-runner.php';
require_once OC_PATH . 'includes/class-oc-upload-handler.php';
require_once OC_PATH . 'includes/class-oc-render-math.php';
require_once OC_PATH . 'includes/class-oc-render-spec.php';
require_once OC_PATH . 'includes/print/class-oc-print-base.php';
require_once OC_PATH . 'includes/print/class-oc-print-engraving.php';
require_once OC_PATH . 'includes/print/class-oc-print-embroidery.php';

// ── Integration base-class stubs ─────────────────────────────────────────────
// When WP is not loaded, integration test classes extend these stubs which
// mark every test as skipped rather than fataling on undefined base classes.

if ( ! class_exists( 'WP_UnitTestCase' ) ) {
	class WP_UnitTestCase extends \PHPUnit\Framework\TestCase {
		public function setUp(): void {
			$this->markTestSkipped( 'Integration tests require WP_TESTS_DIR to be set.' );
		}
	}
}

if ( ! class_exists( 'WC_Unit_Test_Case' ) ) {
	class WC_Unit_Test_Case extends WP_UnitTestCase {}
}

if ( ! class_exists( 'WP_Test_REST_TestCase' ) ) {
	class WP_Test_REST_TestCase extends WP_UnitTestCase {}
}

// ── Integration test bootstrap ────────────────────────────────────────────────
// Only loaded when WP_TESTS_DIR is set in the environment.
// Usage: WP_TESTS_DIR=/path/to/wp-tests vendor/bin/phpunit --testsuite Integration

$_oc_wp_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( $_oc_wp_tests_dir && is_dir( $_oc_wp_tests_dir ) ) {
	// Load the WP test bootstrap (sets up the test database, loads WP, etc.).
	require_once $_oc_wp_tests_dir . '/includes/bootstrap.php';

	// Define the plugin constant so the plugin file can self-register.
	if ( ! defined( 'OC_VERSION' ) ) {
		define( 'OC_VERSION', '1.0.0-test' );
	}
	if ( ! defined( 'OC_FILE' ) ) {
		define( 'OC_FILE', OC_PATH . 'overcustomise.php' );
	}
	if ( ! defined( 'OC_URL' ) ) {
		define( 'OC_URL', 'http://example.com/wp-content/plugins/overcustomise/' );
	}
	if ( ! defined( 'OC_ASSETS_URL' ) ) {
		define( 'OC_ASSETS_URL', OC_URL . 'assets/build/' );
	}
	if ( ! defined( 'OC_DB_VERSION' ) ) {
		define( 'OC_DB_VERSION', '1.0.0' );
	}

	// Load and initialise the plugin.
	require_once OC_PATH . 'includes/class-oc-db.php';
	require_once OC_PATH . 'includes/class-oc-logger.php';
	require_once OC_PATH . 'includes/class-oc-command-runner.php';
	require_once OC_PATH . 'includes/class-oc-font-registry.php';
	require_once OC_PATH . 'includes/class-oc-render-math.php';
	require_once OC_PATH . 'includes/class-oc-render-spec.php';
	require_once OC_PATH . 'includes/admin/class-oc-admin-settings.php';
	require_once OC_PATH . 'includes/admin/class-oc-admin-mockups.php';
	require_once OC_PATH . 'includes/class-oc-rest-api.php';
	require_once OC_PATH . 'includes/frontend/class-oc-cart.php';
	require_once OC_PATH . 'includes/print/class-oc-print-engraving.php';
	require_once OC_PATH . 'includes/print/class-oc-print-uv.php';
	require_once OC_PATH . 'includes/print/class-oc-print-sublimation.php';
	require_once OC_PATH . 'includes/print/class-oc-print-embroidery.php';
	require_once OC_PATH . 'includes/class-oc-print-queue.php';
	require_once OC_PATH . 'includes/class-oc-print-generator.php';
	require_once OC_PATH . 'includes/class-oc-file-cleanup.php';

	// Create plugin tables.
	add_action( 'after_setup_theme', function (): void {
		OC_DB::create_tables();
	} );
}
