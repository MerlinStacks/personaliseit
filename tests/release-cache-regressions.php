<?php
/** Dependency-free deployment regressions: php tests/release-cache-regressions.php. */
define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'OC_PATH', ABSPATH );
define( 'OC_VERSION', 'release-test' );

$GLOBALS['oc_test_site']    = 1;
$GLOBALS['oc_test_options'] = [];
$GLOBALS['oc_test_purges']  = [];
$GLOBALS['oc_test_cache']   = [];

function get_option( $key, $fallback = false ) {
	return $GLOBALS['oc_test_options'][ $GLOBALS['oc_test_site'] ][ $key ] ?? $fallback;
}
function get_transient( $key ) { return get_option( '_transient_' . $key ); }
function set_transient( $key, $value, $ttl ) { return update_option( '_transient_' . $key, $value, false ); }
function apply_filters( $hook, $value, ...$args ) { return $value; }
function add_option( $key, $value, $deprecated = '', $autoload = null ) {
	if ( false !== get_option( $key ) ) {
		return false;
	}
	return update_option( $key, $value, $autoload );
}
function update_option( $key, $value, $autoload = null ) {
	$GLOBALS['oc_test_options'][ $GLOBALS['oc_test_site'] ][ $key ] = $value;
	return true;
}
function wp_generate_uuid4() {
	static $id = 0;
	return 'owner-' . ( ++$id );
}
function wp_cache_delete( $key, $group ) {
	unset( $GLOBALS['oc_test_cache'][ $group ][ $key ] );
}
function wp_cache_get( $key, $group, $force = false, &$found = null ) {
	$found = isset( $GLOBALS['oc_test_cache'][ $group ][ $key ] );
	return $GLOBALS['oc_test_cache'][ $group ][ $key ] ?? false;
}
function wp_cache_set( $key, $value, $group, $ttl = 0 ) {
	$GLOBALS['oc_test_cache'][ $group ][ $key ] = $value;
}
function wp_cache_get_last_changed( $group ) {
	$generation = wp_cache_get( 'last_changed', $group );
	return $generation ? $generation : 'initial';
}
function wp_cache_set_last_changed( $group ) {
	wp_cache_set( 'last_changed', uniqid(), $group );
}
function rocket_clean_domain() {
	$GLOBALS['oc_test_purges'][] = 'rocket';
	// Simulate a concurrent or re-entrant request while the lease is held.
	OC_Release_Cache::maybe_purge();
	if ( ! empty( $GLOBALS['oc_test_fail_purge'] ) ) {
		throw new RuntimeException( 'Cache provider unavailable' );
	}
}
function w3tc_flush_posts() {
	$GLOBALS['oc_test_purges'][] = 'w3tc-pages';
}
function w3tc_flush_minify() {
	$GLOBALS['oc_test_purges'][] = 'w3tc-minify';
}
function wp_cache_clear_cache() {
	$GLOBALS['oc_test_purges'][] = 'super-cache';
}
function sg_cachepress_purge_cache() {
	$GLOBALS['oc_test_purges'][] = 'siteground';
}
// phpcs:ignore PEAR.NamingConventions.ValidClassName.StartWithCapital -- Match the third-party API's class name.
class autoptimizeCache {
	public static function clearall() {
		$GLOBALS['oc_test_purges'][] = 'autoptimize';
	}
}
class OC_Logger {
	public static function error( $message ) {
		$GLOBALS['oc_test_log'][] = $message;
	}
}
function do_action( $hook, ...$args ) {
	$GLOBALS['oc_test_purges'][] = $hook;
	foreach ( $GLOBALS['oc_test_hooks'][ $hook ] ?? [] as $callback ) {
		$callback( ...$args );
	}
}
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['oc_test_hooks'][ $hook ][] = $callback;
}
function get_current_blog_id() {
	return $GLOBALS['oc_test_site'];
}
function switch_to_blog( $id ) {
	$GLOBALS['oc_test_blog_stack'][] = get_current_blog_id();
	$GLOBALS['oc_test_site']         = $id;
}
function restore_current_blog() {
	$GLOBALS['oc_test_site'] = array_pop( $GLOBALS['oc_test_blog_stack'] );
}
function wp_next_scheduled( $hook ) {
	return $GLOBALS['oc_test_cron'][ get_current_blog_id() ][ $hook ] ?? false;
}
function wp_schedule_single_event( $timestamp, $hook ) {
	$GLOBALS['oc_test_cron'][ get_current_blog_id() ][ $hook ] = $timestamp;
}
function get_post_meta( $id, $key, $single = false ) {
	return 99 === $id && '_oc_artwork' === $key;
}
function nocache_headers() {
	$GLOBALS['oc_test_no_cache_headers'] = true;
}
function oc_check( $condition, $message ) {
	if ( ! $condition ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI assertion, not HTML.
		throw new RuntimeException( $message );
	}
}

// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- In-memory database double for atomic lease deletion.
$wpdb = new class() {
	public $options = 'wp_options';
	public $prefix = 'wp_';
	public $last_error = '';
	public function get_var( $args ) {
		return 42 === ( $args[0] ?? null ) ? 1 : null;
	}
	public function prepare( $sql, ...$args ) {
		return $args;
	}
	public function query( $args ) {
		[ $key, $expected ] = $args;
		if ( get_option( $key ) === $expected ) {
			unset( $GLOBALS['oc_test_options'][ $GLOBALS['oc_test_site'] ][ $key ] );
			return 1;
		}
		return 0;
	}
};

require OC_PATH . 'includes/class-oc-cache.php';

OC_Release_Cache::maybe_purge();
oc_check( count( $GLOBALS['oc_test_purges'] ) === 8, 'All supported integrations must run once, without recursive purges.' );
oc_check( get_option( 'oc_cache_release' ) === OC_Release_Cache::version(), 'Record successful release.' );
oc_check( false === get_option( 'oc_cache_release_lock' ), 'Release the completed lease.' );
OC_Release_Cache::maybe_purge();
oc_check( count( $GLOBALS['oc_test_purges'] ) === 8, 'An unchanged release must not purge again.' );

$GLOBALS['oc_test_site'] = 2;
update_option( 'oc_cache_release', 'previous-build' );
update_option( 'oc_cache_release_lock', ( time() + 300 ) . ':other-request' );
OC_Release_Cache::maybe_purge();
oc_check( count( $GLOBALS['oc_test_purges'] ) === 8, 'Do not compete with an active purge.' );
update_option( 'oc_cache_release_lock', ( time() - 1 ) . ':crashed-request' );
OC_Release_Cache::maybe_purge();
oc_check( count( $GLOBALS['oc_test_purges'] ) === 16, 'Recover expired leases and purge each multisite site independently.' );

// An old worker must not remove a newer lease after its own lease expires.
update_option( 'oc_cache_release_lock', 'new-owner' );
$oc_release_lock = new ReflectionMethod( OC_Release_Cache::class, 'release_lock' );
$oc_release_lock->invoke( null, 'old-owner' );
oc_check( get_option( 'oc_cache_release_lock' ) === 'new-owner', 'Lease deletion must compare ownership.' );

$GLOBALS['oc_test_site']       = 3;
$GLOBALS['oc_test_fail_purge'] = true;
OC_Release_Cache::maybe_purge();
oc_check( false === get_option( 'oc_cache_release' ), 'A failed purge must remain pending.' );
oc_check( count( $GLOBALS['oc_test_log'] ) === 1, 'Provider failures are logged without breaking the storefront.' );
$oc_purge_count = count( $GLOBALS['oc_test_purges'] );
OC_Release_Cache::maybe_purge();
oc_check( count( $GLOBALS['oc_test_purges'] ) === $oc_purge_count, 'Provider failures have a cooldown.' );
$GLOBALS['oc_test_fail_purge'] = false;
update_option( 'oc_cache_release_lock', ( time() - 1 ) . ':failed-request' );
OC_Release_Cache::maybe_purge();
oc_check( get_option( 'oc_cache_release' ) === OC_Release_Cache::version(), 'Retry a failed purge after cooldown.' );

wp_cache_set( 'initial:design_1', 'legacy data', 'oc_data' );
wp_cache_set( 'old-build:initial:design_1', 'old release data', 'oc_data' );
wp_cache_set( 'session-token', 'keep me', 'transient' );
oc_check( OC_Cache::get( 'design_1' ) === null, 'Never reuse previous-release object-cache entries.' );
OC_Cache::set( 'design_1', 'current data' );
oc_check( OC_Cache::get( 'design_1' ) === 'current data', 'Current-release caching still works.' );
OC_Cache::flush_group();
oc_check( OC_Cache::get( 'design_1' ) === null, 'Generation invalidation does not require group-flush support.' );
oc_check( wp_cache_get( 'session-token', 'transient' ) === 'keep me', 'Keep unrelated session/transient state.' );

OC_Release_Cache::prepare_refresh();
oc_check( ! defined( 'DONOTCACHEPAGE' ), 'Normal product pages stay cacheable.' );
$_GET['oc_cache_refresh'] = '123';
OC_Release_Cache::prepare_refresh();
OC_Release_Cache::refresh_headers();
oc_check( DONOTCACHEPAGE && $GLOBALS['oc_test_no_cache_headers'], 'Explicit recovery renders a non-cacheable page.' );
echo "Release cache regressions passed.\n";

// Catalogue mutations are coalesced, and page purges leave optimisation/session caches alone.
OC_Release_Cache::register();
$GLOBALS['oc_test_purges'] = [];
OC_Cache::set( 'design_1', 'cached design' );
OC_Cache::get( 'design_1' );
OC_Cache::invalidate_group( 'oc_print_files' );
OC_Release_Cache::option_changed( 'unrelated_option' );
OC_Release_Cache::media_changed( 99 );
OC_Release_Cache::media_changed( 123 );
OC_Release_Cache::purge_pending_content();
oc_check( [] === $GLOBALS['oc_test_purges'], 'Reads, print jobs, private uploads and unrelated options must not purge pages.' );
OC_Cache::invalidate_group( OC_Cache::GROUP );
OC_Cache::invalidate_group( OC_Cache::GROUP );
OC_Release_Cache::option_changed( 'oc_settings' );
OC_Release_Cache::option_changed( 'oc_print_methods' );
OC_Release_Cache::media_changed( 42 );
oc_check( ! in_array( 'rocket', $GLOBALS['oc_test_purges'], true ), 'Purge after a save batch, not during each write.' );
OC_Release_Cache::purge_pending_content();
oc_check( 1 === count( array_filter( $GLOBALS['oc_test_purges'], static fn ( $hook ) => 'rocket' === $hook ) ), 'Multiple mutations cause one page purge.' );
oc_check( ! in_array( 'w3tc-minify', $GLOBALS['oc_test_purges'], true ) && ! in_array( 'autoptimize', $GLOBALS['oc_test_purges'], true ), 'Ordinary saves do not discard built optimisation assets.' );
$oc_purge_count = count( $GLOBALS['oc_test_purges'] );
OC_Release_Cache::purge_pending_content();
oc_check( count( $GLOBALS['oc_test_purges'] ) === $oc_purge_count, 'Clean requests do not repeat a content purge.' );

foreach ( [ 3, 4 ] as $oc_site ) {
	$GLOBALS['oc_test_site'] = $oc_site;
	update_option( 'oc_cache_release', OC_Release_Cache::version() );
	OC_Release_Cache::queue_content_purge();
}
$GLOBALS['oc_test_purges'] = [];
OC_Release_Cache::purge_pending_content();
oc_check( 2 === count( array_filter( $GLOBALS['oc_test_purges'], static fn ( $hook ) => 'rocket' === $hook ) ), 'Each switched site gets its own purge.' );
oc_check( 4 === get_current_blog_id(), 'Restore the original site after purging switched sites.' );
$GLOBALS['oc_test_fail_purge'] = true;
OC_Release_Cache::queue_content_purge();
OC_Release_Cache::purge_pending_content();
oc_check( false !== wp_next_scheduled( 'oc_retry_content_cache_purge' ), 'Provider failures schedule a retry.' );
$GLOBALS['oc_test_fail_purge'] = false;
OC_Release_Cache::retry_content_purge();
echo "Catalogue cache regressions passed.\n";

// Exercise the dispatch policy for guest success, inactive data and error responses.
class WP_HTTP_Response {
	public array $headers = [];
	public function __construct( public $data = null, public $status = 200 ) {}
	public function header( $name, $value ) {
		$this->headers[ $name ] = $value;
	}
}
class WP_REST_Response extends WP_HTTP_Response {}
class WP_REST_Request {
	public function __construct( private string $route ) {}
	public function get_route(): string {
		return $this->route;
	}
}
require OC_PATH . 'includes/class-oc-rest-api.php';
$oc_rest = new OC_Rest_API();
foreach ( [ [ [ 'active' => true ], 200 ], [ [ 'active' => false ], 200 ], [ [ 'code' => 'invalid_design' ], 404 ], [ [ 'code' => 'token_unavailable' ], 503 ] ] as [ $oc_body, $oc_status ] ) {
	$oc_response = new WP_REST_Response( $oc_body, $oc_status );
	$oc_result   = $oc_rest->prevent_response_caching( $oc_response, null, new WP_REST_Request( '/overcustomise/v1/product-design/42' ) );
	oc_check( $oc_result === $oc_response && $oc_status === $oc_result->status, 'Cache headers preserve response data and status.' );
	oc_check( str_contains( $oc_result->headers['Cache-Control'], 'no-store' ), 'All public design responses must be non-cacheable.' );
}
$oc_response = new WP_REST_Response( [] );
$oc_rest->prevent_response_caching( $oc_response, null, new WP_REST_Request( '/wp/v2/posts' ) );
oc_check( [] === $oc_response->headers, 'Other plugins and WordPress REST routes retain their own caching policies.' );
echo "REST cache policy regressions passed.\n";

// An in-place replacement with the same size and timestamp must get a new public URL.
function wp_upload_dir() {
	return [
		'basedir' => $GLOBALS['oc_test_upload_root'],
		'baseurl' => 'https://example.test/uploads',
	];
}
function trailingslashit( $value ) {
	return rtrim( $value, '/' ) . '/';
}
function wp_normalize_path( $value ) {
	return str_replace( '\\', '/', $value );
}
function esc_url_raw( $value ) {
	return $value;
}
function add_query_arg( array $args, string $url ) {
	return $url . '?' . http_build_query( $args );
}
require OC_PATH . 'includes/frontend/class-oc-frontend.php';
$oc_root                        = sys_get_temp_dir() . '/oc-media-cache-' . bin2hex( random_bytes( 8 ) );
$GLOBALS['oc_test_upload_root'] = $oc_root;
// phpcs:disable WordPress.WP.AlternativeFunctions -- This standalone CLI fixture has no WordPress filesystem layer.
mkdir( $oc_root . '/overcustomise/clipart', 0700, true );
$oc_file = $oc_root . '/overcustomise/clipart/art.svg';
try {
	file_put_contents( $oc_file, '<svg><text>A</text></svg>' );
	touch( $oc_file, 1700000000 );
	$oc_method = new ReflectionMethod( OC_Frontend::class, 'clipart_public_url' );
	$oc_before = $oc_method->invoke( null, $oc_file );
	file_put_contents( $oc_file, '<svg><text>B</text></svg>' );
	touch( $oc_file, 1700000000 );
	oc_check( $oc_before !== OC_Clipart_Catalog::public_url( $oc_file, true ), 'Explicit replacements refresh actual content revisions even with preserved size and mtime.' );
	$oc_after = $oc_method->invoke( null, $oc_file );
	oc_check( $oc_after === $oc_method->invoke( null, $oc_file ), 'Unchanged files reuse their cached content revision.' );
	oc_check( '' === $oc_method->invoke( null, OC_PATH . 'overcustomise.php' ), 'Versioning must not expose files outside managed clipart storage.' );
} finally {
	unlink( $oc_file );
	rmdir( $oc_root . '/overcustomise/clipart' );
	rmdir( $oc_root . '/overcustomise' );
	rmdir( $oc_root );
}
echo "Media cache regressions passed.\n";
// phpcs:enable WordPress.WP.AlternativeFunctions
