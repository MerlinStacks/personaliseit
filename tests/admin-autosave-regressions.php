<?php
/** Standalone admin persistence checks: php tests/admin-autosave-regressions.php */
define( 'ABSPATH', __DIR__ . '/../' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'ARRAY_A', 'ARRAY_A' );
function get_current_user_id() { return $GLOBALS['user_id'] ?? 1; }
function current_user_can( ...$args ) { return $GLOBALS['allowed'] ?? true; }
function wp_verify_nonce( ...$args ) { return $GLOBALS['nonce_valid'] ?? true; }
function get_option( $key, $default = false ) { return $GLOBALS['options'][ $key ] ?? $default; }
function add_option( $key, $value, ...$args ) {
	if ( ! empty( $GLOBALS['fail_retention'] ) || isset( $GLOBALS['options'][ $key ] ) ) { return false; }
	$GLOBALS['options'][ $key ] = $value; return true;
}
function esc_textarea( $value ) { return htmlspecialchars( $value ); }
function esc_url( $value ) { return htmlspecialchars( $value ); }
function wp_create_nonce( $action ) { return 'valid'; }
function admin_url( $path ) { return '/wp-admin/' . $path; }
function add_query_arg( $args, $url ) { return $url . '?' . http_build_query( $args ); }
function sanitize_key( $value ) { return $value; }
function sanitize_text_field( $value ) { return $value; }
function wp_unslash( $value ) { return $value; }
function absint( $value ) { return abs( (int) $value ); }
function __( $value, ...$args ) { return $value; }
function esc_html__( $value, ...$args ) { return $value; }
function wp_die( $message, ...$args ) { throw new RuntimeException( $message ); }
function wp_json_encode( $value ) { return json_encode( $value ); }
function check_ajax_referer( ...$args ) {}
function wp_send_json_success( $data ) { $GLOBALS['ajax_result'] = $data; }
function wp_send_json_error( $data, $status = null ) {
	$GLOBALS['ajax_error'] = [ 'data' => $data, 'status' => $status ];
	throw new RuntimeException( $data['message'] );
}
function get_transient( $key ) { return $GLOBALS['drafts'][ $key ] ?? false; }
function set_transient( $key, $value, $ttl ) { $GLOBALS['drafts'][ $key ] = $value; return true; }
function delete_transient( $key ) { unset( $GLOBALS['drafts'][ $key ] ); return true; }
class OC_DB {
	public static function get_font_groups() { return []; }
	public static function get_colour_groups() { return []; }
	public static function get_clipart_groups() { return []; }
	public static function get_fonts( ...$args ) { return []; }
	public static function get_image_filters( ...$args ) { return []; }
	public static function get_clipart( ...$args ) { return []; }
	public static $unsupported_table = null;
	public static $checked_tables = [];
	public static function get_design( $id ) { return (object) [ 'id' => $id, 'active' => 1 ]; }
	public static function tables_support_transactions( array $tables ): bool {
		self::$checked_tables = $tables;
		return ! in_array( self::$unsupported_table, $tables, true );
	}
}
class OC_Cache { const GROUP = 'design'; public static function invalidate_group( $group ) {} }
class OC_Plugin { public static function reset_browser_fonts() {} }
require __DIR__ . '/../includes/class-oc-autosave.php';
require __DIR__ . '/../includes/admin/class-oc-admin-products.php';
require __DIR__ . '/../includes/admin/class-oc-admin-fonts.php';

function check( $condition, $message ) {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
}
$wpdb = new class {
	public $prefix = 'wp_';
	public $name = 'Original';
	public $mutations = 0;
	public $locked = false;
	public $queries = [];
	public function prepare( $sql, ...$args ) { return $sql; }
	public function get_var( $sql ) {
		if ( str_contains( $sql, 'FOR UPDATE' ) ) { $this->locked = true; }
		return 1;
	}
	public function get_results( $sql, $format = null ) {
		$this->queries[] = $sql;
		if ( str_contains( $sql, 'FOR UPDATE' ) ) { check( $this->locked, 'Revision must be checked after design lock' ); }
		return str_contains( $sql, 'wp_oc_designs ' ) ? [ [ 'id' => '7', 'name' => $this->name ] ] : [];
	}
	public function query( $sql ) { $this->queries[] = $sql; return 1; }
	public function update( $table, $data, ...$args ) { $this->mutations++; $this->name = $data['name']; return 1; }
};
$state = [ 'design' => [ 'name' => 'Draft', 'customType' => 'text_only', 'flatRate' => 0, 'active' => true ], 'areas' => [] ];
$first = OC_Autosave::store( 7, $state, 1, 0 );
check( 'stored' === $first['status'], 'Initial autosave' );
check( $first === OC_Autosave::store( 7, $state, 1, 0 ), 'Exact retry must return original acknowledgement' );
$newer = $state;
$newer['design']['name'] = 'Newer';
check( 'conflict' === OC_Autosave::store( 7, $newer, 1, 0 )['status'], 'Different payload is not a retry' );
check( 'stored' === OC_Autosave::store( 7, $newer, 2, 1 )['status'], 'Newer draft' );
check( ! OC_Autosave::clear( 7, 1 ), 'Old final save must not clear newer draft' );
check( 2 === OC_Autosave::restore( 7 )['revision'], 'Newer draft survives' );

$revision = new ReflectionMethod( OC_Admin_Products::class, 'design_revision' );
$save = new ReflectionMethod( OC_Admin_Products::class, 'handle_design_save' );
$admin = ( new ReflectionClass( OC_Admin_Products::class ) )->newInstanceWithoutConstructor();
$post = [ 'oc_design_id' => 7, 'oc_design_name' => 'Saved', 'oc_design_revision' => $revision->invoke( null, 7 ),
	'oc_final_manifest' => json_encode( [ 'marker' => 'complete', 'areas' => 0, 'layers' => 0, 'autosaveRevision' => 1 ] ) ];
check( 1 === preg_match( '/^[a-f0-9]{64}$/D', $post['oc_design_revision'] ), 'Fixture uses the actual persisted SHA-256 design revision' );
foreach ( [ 'missing_marker', 'missing_layers', 'missing_revision', 'short_revision', 'stale_user', 'stale_tab' ] as $case ) {
	$_POST = $post;
	if ( 'missing_marker' === $case ) { unset( $_POST['oc_final_manifest'] ); }
	if ( 'missing_layers' === $case ) { $_POST['oc_final_manifest'] = json_encode( [ 'marker' => 'complete', 'areas' => 0, 'layers' => 5, 'autosaveRevision' => 1 ] ); }
	if ( 'missing_revision' === $case ) { unset( $_POST['oc_design_revision'] ); }
	if ( 'short_revision' === $case ) { $_POST['oc_design_revision'] = substr( $post['oc_design_revision'], 0, 63 ); }
	if ( str_starts_with( $case, 'stale_' ) ) { $wpdb->name = 'Changed by another editor'; }
	$GLOBALS['user_id'] = 'stale_user' === $case ? 2 : 1;
	try {
		$save->invoke( $admin );
		throw new LogicException( 'Expected rejection: ' . $case );
	} catch ( RuntimeException $error ) {
		check( str_contains( $error->getMessage(), 'No changes were applied' ), $case . ': ' . $error->getMessage() );
	}
	check( 0 === $wpdb->mutations, 'Rejected submission mutated database: ' . $case );
}
$wpdb->name = 'Original';
$_POST = $post;
foreach ( [ 'oc_designs', 'oc_design_print_areas', 'oc_design_layers' ] as $table ) {
	OC_DB::$unsupported_table = $table;
	$wpdb->queries = [];
	$wpdb->locked = false;
	$drafts = $GLOBALS['drafts'];
	try {
		$save->invoke( $admin );
		throw new LogicException( 'Expected engine rejection: ' . $table );
	} catch ( RuntimeException $error ) {
		check( str_contains( $error->getMessage(), 'InnoDB' ), $error->getMessage() );
	}
	check( [] === $wpdb->queries && ! $wpdb->locked && 0 === $wpdb->mutations, 'Engine rejection must precede transactions, revision locks and mutations' );
	check( $drafts === $GLOBALS['drafts'], 'Engine rejection must preserve drafts' );
}
OC_DB::$unsupported_table = null;
check( 7 === $save->invoke( $admin ), 'Complete current submission saves' );
check( 1 === $wpdb->mutations, 'Only valid submission mutated database' );
check( 2 === OC_Autosave::restore( 7 )['revision'], 'Final save preserves newer draft' );
check( OC_Autosave::clear( 7, 2 ), 'Matching draft can be cleared' );
$state['design']['baseRevision'] = $post['oc_design_revision'];
$_POST = [ 'design_id' => '7', 'state' => json_encode( $state ), 'revision' => '1', 'expected_revision' => '0' ];
OC_Admin_Products::ajax_autosave_design();
check( 1 === $GLOBALS['ajax_result']['revision'], 'AJAX acknowledges the editor revision' );
check( $state === OC_Autosave::restore( 7 )['state'], 'Restoration preserves the editor base revision' );
OC_Admin_Products::ajax_autosave_design();
check( 1 === $GLOBALS['ajax_result']['revision'], 'AJAX exact retry is idempotent' );
$newer = $state;
$newer['design']['name'] = 'Conflicting draft';
$_POST['state'] = json_encode( $newer );
try {
	OC_Admin_Products::ajax_autosave_design();
	throw new LogicException( 'Expected AJAX revision conflict' );
} catch ( RuntimeException $error ) {
	check( str_contains( $error->getMessage(), 'newer autosave' ), 'AJAX rejects conflicting editor revisions' );
	check( 409 === $GLOBALS['ajax_error']['status'] && 'autosave_conflict' === $GLOBALS['ajax_error']['data']['code'], 'Conflict must not be an early state-validation rejection' );
	check( 1 === $GLOBALS['ajax_error']['data']['revision'] && $state === OC_Autosave::restore( 7 )['state'], 'Conflict preserves the acknowledged draft and revision' );
}
$validate_state = new ReflectionMethod( OC_Autosave::class, 'is_valid_state' );
foreach ( [ 501, 1000 ] as $count ) {
	$large_state = $state;
	$large_state['areas'] = [ [ 'unit' => 'px', 'layers' => array_fill( 0, $count, [ 'type' => 'text', 'settings' => [] ] ) ] ];
	check( $validate_state->invoke( null, $large_state ), 'Autosave accepts bounded ' . $count . '-layer designs' );
}
$_POST = [ 'design_id' => '7', 'state' => json_encode( $large_state ), 'revision' => '2', 'expected_revision' => '1' ];
OC_Admin_Products::ajax_autosave_design();
check( 2 === $GLOBALS['ajax_result']['revision'] && $large_state === OC_Autosave::restore( 7 )['state'], '1000-layer draft round-trips through AJAX and restore' );
foreach ( [ 'layers', 'areas', 'bytes' ] as $limit ) {
	$invalid = $large_state;
	if ( 'layers' === $limit ) { $invalid['areas'][] = [ 'unit' => 'px', 'layers' => [ [ 'type' => 'text' ] ] ]; }
	if ( 'areas' === $limit ) { $invalid['areas'] = array_fill( 0, 101, [ 'unit' => 'px', 'layers' => [] ] ); }
	if ( 'bytes' === $limit ) { $invalid['design']['name'] = str_repeat( 'x', 1048576 ); }
	check( ! $validate_state->invoke( null, $invalid ), 'Autosave retains ' . $limit . ' bound' );
	$_POST['state'] = json_encode( $invalid );
	$_POST['revision'] = '3';
	$_POST['expected_revision'] = '2';
	try {
		OC_Admin_Products::ajax_autosave_design();
		throw new LogicException( 'Expected autosave size rejection: ' . $limit );
	} catch ( RuntimeException $error ) {
		check( in_array( $error->getMessage(), [ 'Autosave failed.', 'Invalid state.' ], true ), $error->getMessage() );
	}
	check( $large_state === OC_Autosave::restore( 7 )['state'], 'Rejected oversized draft preserves saved state' );
}
foreach ( [ 1000, 1001 ] as $count ) {
	$_POST = $post;
	$_POST['oc_design_areas'] = [ [ 'label' => 'Front', 'print_method' => 'uv' ] ];
	$_POST['oc_layers'] = array_fill( 0, $count, [ 'type' => 'text', 'area_index' => '0', 'settings' => '{}' ] );
	$_POST['oc_final_manifest'] = json_encode( [ 'marker' => 'complete', 'areas' => 1, 'layers' => $count, 'autosaveRevision' => 2 ] );
	OC_DB::$unsupported_table = 'oc_design_layers';
	$queries = $wpdb->queries;
	$mutations = $wpdb->mutations;
	try {
		$save->invoke( $admin );
		throw new LogicException( 'Expected count/engine rejection' );
	} catch ( RuntimeException $error ) {
		check( 1000 === $count ? str_contains( $error->getMessage(), 'InnoDB' ) : 'Invalid design data.' === $error->getMessage(), 'Final save accepts 1000 layers through validation but rejects 1001' );
	}
	check( $queries === $wpdb->queries && $mutations === $wpdb->mutations, 'Final-save boundary checks must not write' );
}
OC_DB::$unsupported_table = null;
$wpdb = new class {
	public $prefix = 'wp_';
	public $fonts = [ [ 'id' => 1, 'name' => 'Family' ], [ 'id' => 2, 'name' => 'Family' ], [ 'id' => 3, 'name' => 'Other' ] ];
	public function prepare( $query, ...$args ) { return $query; }
	public function get_var( $query ) { return 'Family'; }
	public function update( $table, $data, $where, ...$args ) {
		$count = 0;
		foreach ( $this->fonts as &$font ) {
			$key = array_key_first( $where );
			if ( $font[ $key ] === $where[ $key ] ) { $font['name'] = $data['name']; $count++; }
		}
		return $count;
	}
};
$_POST = [ 'id' => 1, 'name' => 'Renamed' ];
OC_Admin_Fonts::ajax_rename();
check( [ 'Renamed', 'Renamed', 'Other' ] === array_column( $wpdb->fonts, 'name' ), 'Family rename updates every variant, not other families' );
print "Admin/autosave regression checks passed.\n";
