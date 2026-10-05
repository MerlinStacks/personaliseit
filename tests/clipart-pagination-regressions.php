<?php
// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.WP.GlobalVariablesOverride.Prohibited, WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Standalone WP doubles, real local fixtures and CLI assertions.
/** Real catalogue filtering, pagination and revision caching with a fixture library. */
define( 'ABSPATH', dirname( __DIR__ ) . '/' );
$root       = sys_get_temp_dir() . '/oc-clipart-pages-' . bin2hex( random_bytes( 6 ) );
$options    = [];
$transients = [];
function get_option( $key, $fallback = false ) {
	return $GLOBALS['options'][ $key ] ?? $fallback; }
function update_option( $key, $value, $autoload = null ) {
	$GLOBALS['options'][ $key ] = $value; }
function get_transient( $key ) {
	return $GLOBALS['transients'][ $key ] ?? false; }
function set_transient( $key, $value, $ttl ) {
	$GLOBALS['transients'][ $key ] = $value; }
function wp_generate_uuid4() {
	return 'changed-generation'; }
function absint( $v ) {
	return abs( (int) $v ); }
function wp_upload_dir() {
	return [
		'basedir' => $GLOBALS['root'],
		'baseurl' => 'https://example.test/uploads',
	]; }
function trailingslashit( $v ) {
	return rtrim( $v, '/' ) . '/'; }
function wp_normalize_path( $v ) {
	return str_replace( '\\', '/', $v ); }
function esc_url_raw( $v ) {
	return $v; }
function add_query_arg( $args, $url ) {
	return $url . '?' . http_build_query( $args ); }
function check( $ok, $message ) {
	if ( ! $ok ) {
		throw new RuntimeException( $message ); } }
$wpdb = new class() {
	public $prefix     = 'wp_';
	public $last_error = '';
	public $reads      = 0;
	public function get_results( $sql ) {
		++$this->reads;
		return array_map(
			fn( $id ) => (object) [
				'id'                    => $id,
				'name'                  => 'Art ' . $id,
				'file_path'             => $GLOBALS['root'] . '/overcustomise/clipart/art.svg',
				'file_type'             => 'svg',
				'colour_changeable'     => 1,
				'allowed_print_methods' => 130 === $id ? '["embroidery"]' : '',
				'group_ids'             => '1',
				'group_names'           => 'Animals',
			],
			range( 1, 130 )
		);
	}
};
require ABSPATH . 'includes/class-oc-clipart-catalog.php';
mkdir( $root . '/overcustomise/clipart', 0700, true );
file_put_contents( $root . '/overcustomise/clipart/art.svg', '<svg/>' );
try {
	$first = OC_Clipart_Catalog::page( [ 1 ], 'uv', 1, '', '', 125 );
	check( 61 === count( $first['items'] ) && 125 === end( $first['items'] )['id'], 'Initial page must retain the configured default beyond page one.' );
	$second = OC_Clipart_Catalog::page( [ 1 ], 'uv', 2 );
	check( 61 === $second['items'][0]['id'] && 60 === count( $second['items'] ), 'Pagination must not skip catalogue items.' );
	$last = OC_Clipart_Catalog::page( [ 1 ], 'uv', 3 );
	check( false === $last['hasMore'] && 9 === count( $last['items'] ), 'Print-method restrictions must apply before pagination.' );
	check( [] === OC_Clipart_Catalog::page( [ 2 ], 'uv' )['items'], 'Group restrictions must be enforced.' );
	$search = OC_Clipart_Catalog::page( [ 1 ], 'uv', 1, 'Art 125', 'Animals' );
	check( 1 === count( $search['items'] ) && 125 === $search['items'][0]['id'], 'Search must cover items beyond the loaded page.' );
	check( 1 === $wpdb->reads, 'Repeated pages should reuse the persistent metadata cache.' );
	OC_Clipart_Catalog::invalidate();
	OC_Clipart_Catalog::page( [ 1 ], 'uv' );
	check( 2 === $wpdb->reads, 'Manager mutations must invalidate cached catalogue data.' );
	echo "Clipart pagination regressions passed.\n";
} finally {
	unlink( $root . '/overcustomise/clipart/art.svg' );
	rmdir( $root . '/overcustomise/clipart' );
	rmdir( $root . '/overcustomise' );
	rmdir( $root );
}
