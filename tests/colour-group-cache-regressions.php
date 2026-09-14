<?php
/** Dependency-free checks: php tests/colour-group-cache-regressions.php */
define( 'ABSPATH', dirname( __DIR__ ) . '/' );
function absint( $value ) {
	return abs( (int) $value );
}
function get_current_blog_id() {
	return $GLOBALS['blog_id'] ?? 1;
}
function wp_cache_get( $key, $group, $force = false, &$found = null ) {
	$found = isset( $GLOBALS['cache'][ get_current_blog_id() ][ $group ][ $key ] );
	return $found ? unserialize( $GLOBALS['cache'][ get_current_blog_id() ][ $group ][ $key ] ) : false;
}
function wp_cache_set( $key, $value, $group, $ttl = 0 ) {
	$GLOBALS['cache'][ get_current_blog_id() ][ $group ][ $key ] = serialize( $value );
}
function wp_cache_get_last_changed( $group ) {
	$last_changed = wp_cache_get( 'last_changed', $group );
	return $last_changed ? $last_changed : 'initial';
}
function wp_cache_set_last_changed( $group ) {
	static $generation = 0;
	wp_cache_set( 'last_changed', (string) ++$generation, $group );
}
function wp_cache_flush_group( $group ) {
	unset( $GLOBALS['cache'][ get_current_blog_id() ][ $group ] );
}
function check( $condition, $message ) {
	if ( ! $condition ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI-only assertion messages are plain text, not HTML output.
		throw new RuntimeException( $message );
	}
}
require ABSPATH . 'includes/class-oc-cache.php';
require ABSPATH . 'includes/class-oc-db.php';
require ABSPATH . 'includes/admin/class-oc-admin-colours.php';

// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- This standalone test supplies the WordPress database global with an in-memory stub.
$wpdb          = new class() {
	public $prefix     = 'wp_';
	public $last_error = '';
	public $queries    = 0;
	public $fail       = false;
	public $during_read;
	public $colours;
	public $members = [
		1 => [ 3, 2 ],
		2 => [ 2, 1 ],
		9 => [],
	];
	public function prepare( $sql, ...$args ) {
		return [ $sql, $args ];
	}
	public function get_results( $query ) {
		$active = is_array( $query );
		return array_values( array_filter( $this->colours, static fn( $row ) => ! $active || $row->active ) );
	}
	public function get_col( $query ) {
		++$this->queries;
		check( str_contains( $query[0], 'GROUP BY colour_id ORDER BY MIN(sort_order) ASC' ), 'Membership SQL remains compatible' );
		$this->last_error = $this->fail ? 'Read failed' : '';
		if ( $this->fail ) {
			return [];
		}
		$result = [];
		foreach ( $query[1] as $id ) {
			$result = array_merge( $result, $this->members[ $id ] ?? [] );
		}
		if ( $this->during_read ) {
			$callback          = $this->during_read;
			$this->during_read = null;
			$callback();
		}
		return $result;
	}
};
$wpdb->colours = [
	(object) [
		'id'     => '1',
		'name'   => 'Amber',
		'hex'    => '#ffaa00',
		'active' => 1,
	],
	(object) [
		'id'     => '2',
		'name'   => 'Blue',
		'hex'    => '#0000ff',
		'active' => 0,
	],
	(object) [
		'id'     => '3',
		'name'   => 'Red',
		'hex'    => '#ff0000',
		'active' => 1,
	],
];
function ids( $groups ) {
	return array_map( static fn( $row ) => (int) $row->id, OC_DB::get_colours_for_groups( $groups ) );
}
check( [ 1, 3 ] === ids( [] ) && [ 1, 3 ] === ids( [ 0, '', null, 'invalid' ] ), 'Empty normalized IDs use active colours' );
check( 0 === $wpdb->queries, 'Empty IDs skip membership SQL' );
check( [ 1, 2, 3 ] === ids( [ 2, 1 ] ), 'Union includes inactive members in colour-name order' );
check( [ 1, 2, 3 ] === ids( [ '1', -2, 2, 0 ] ) && 1 === $wpdb->queries, 'Equivalent normalized sets share a query' );
check( [ 2, 3 ] === ids( [ 1 ] ) && 2 === $wpdb->queries, 'Different sets remain independent' );
check( [] === ids( [ 9 ] ) && [] === ids( [ 9, 9 ] ) && 3 === $wpdb->queries, 'Empty memberships are cached' );
check( [] === ids( [ 999 ] ), 'Unknown groups do not fall back to all colours' );
$rows          = OC_DB::get_colours_for_groups( [ 1 ] );
$rows[0]->name = 'Caller edit';
check( 'Blue' === OC_DB::get_colours_for_groups( [ 1 ] )[0]->name, 'Membership cache does not retain returned objects' );

// Exercise the actual invalidation entry point shared by all colour admin mutations.
$clear = new ReflectionMethod( OC_Admin_Colours::class, 'clear_colour_cache' );
$clear->setAccessible( true );
$wpdb->members[9] = [ 1 ];
$clear->invoke( null );
check( [ 1 ] === ids( [ 9 ] ), 'Group creation invalidates cached empty membership' );
$wpdb->members[1] = [ 1 ];
$clear->invoke( null );
check( [ 1 ] === ids( [ 1 ] ), 'Group membership replacement is visible in the same request' );
$wpdb->colours[0]->name = 'Apricot';
$wpdb->colours[0]->hex  = '#ffbb00';
$clear->invoke( null );
$rows = OC_DB::get_colours_for_groups( [ 1 ] );
check( 'Apricot' === $rows[0]->name && '#ffbb00' === $rows[0]->hex, 'Colour edits refresh row data' );
$wpdb->colours[0]->active = 0;
$clear->invoke( null );
check( [ 3 ] === ids( [] ) && [ 1 ] === ids( [ 1 ] ), 'Toggle refreshes active list but preserves explicit inactive membership' );
array_shift( $wpdb->colours );
$clear->invoke( null );
check( [] === ids( [ 1 ] ), 'Deleted colours are excluded even with dangling membership' );
unset( $wpdb->members[2] );
$clear->invoke( null );
check( [] === ids( [ 2 ] ), 'Deleted groups return empty' );

foreach ( [ 'delete', 'flush_pattern', 'flush_group', 'invalidate_group' ] as $method ) {
	ids( [ 1 ] );
	$before = $wpdb->queries;
	OC_Cache::$method( OC_Cache::GROUP );
	ids( [ 1 ] );
	check( $before + 1 === $wpdb->queries, $method . ' invalidates local membership' );
}
$wpdb->fail = true;
check( [] === ids( [ 77 ] ), 'Read failure retains empty return contract' );
$wpdb->fail        = false;
$wpdb->members[77] = [ 3 ];
check( [ 3 ] === ids( [ 77 ] ), 'Failed read is retried without invalidation' );

$wpdb->members[88] = [ 2 ];
$wpdb->during_read = static function () use ( $wpdb ) {
	$wpdb->members[88] = [ 3 ];
	OC_Cache::invalidate_group( OC_Cache::GROUP );
};
check( [ 2 ] === ids( [ 88 ] ) && [ 3 ] === ids( [ 88 ] ), 'In-flight old-generation read cannot populate new generation' );

$before = $wpdb->queries;
// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Simulate a blog switch for the standalone get_current_blog_id() stub.
$GLOBALS['blog_id'] = 2;
$wpdb->prefix       = 'wp_2_';
check( [ 3 ] === ids( [ 77 ] ) && $before + 1 === $wpdb->queries, 'Blog switch isolates request-local memberships' );
$before       = $wpdb->queries;
$wpdb->prefix = 'other_';
ids( [ 77 ] );
check( $before + 1 === $wpdb->queries, 'Table prefix changes isolate memberships even within one blog' );
print "Colour group cache regression checks passed.\n";
