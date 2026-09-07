<?php
/** php tests/admin-upgrade-recovery.php */
require __DIR__ . '/admin-autosave-regressions.php';
class OC_Admin_Print_Methods {
	public static function get() { return [ 'uv' => [] ]; }
	public static function is_enabled( $method ) { return true; }
}
class OC_Cart {
	public static function normalise_layer_settings( $raw, $type ) {
		return $raw + [ 'font_groups' => [], 'colour_groups' => [], 'clipart_groups' => [], 'default_font_id' => 0, 'image_filter_ids' => [], 'default_image_filter_id' => 0 ];
	}
}
function get_post_type( $id ) { return false; }
$GLOBALS['user_id'] = 1;
$wpdb = new class {
	public $prefix = 'wp_';
	public $insert_id = 100;
	public $fail_commit = false;
	public $queries = [];
	public $rows = [];
	public function prepare( $sql, ...$args ) { return $sql; }
	public function get_var( $sql ) { return 1; }
	public function query( $sql ) { $this->queries[] = $sql; return ! ( $this->fail_commit && 'COMMIT' === $sql ); }
	public function insert( $table, $data, ...$args ) { $this->rows[] = [ $table, $data ]; $this->insert_id++; return 1; }
	public function update( ...$args ) { throw new LogicException( 'Recovery must never update source rows' ); }
};
// Exact envelope and collectState fields shipped in HEAD, deliberately no baseRevision.
$fixture = json_decode( file_get_contents( __DIR__ . '/fixtures/admin-head-autosave.json' ), true );
$GLOBALS['drafts']['oc_autosave_7_1'] = $fixture;
$restored = OC_Autosave::restore( 7 );
$token = $restored['recoveryToken'];
check( $fixture === OC_Autosave::recovery( $token ), 'Original HEAD envelope retained verbatim' );
check( ! isset( $restored['state']['design']['baseRevision'] ), 'Never synthesize a base digest' );
$new = $fixture['state'];
$new['design']['name'] = 'Concurrent tab';
check( 'stored' === OC_Autosave::store( 7, $new, 5, 4 )['status'], 'Concurrent tab advances autosave' );
check( $fixture === OC_Autosave::recovery( $token ), 'Concurrent overwrite cannot replace recovery copy' );
$_POST = [ 'nonce' => 'valid', 'design_id' => 7, 'state' => json_encode( $new ), 'revision' => 6, 'expected_revision' => 5 ];
OC_Admin_Products::ajax_autosave_design();
check( 6 === $GLOBALS['ajax_result']['revision'], 'Cached HEAD JavaScript autosave without digest is accepted, not discarded' );
$GLOBALS['user_id'] = 2;
check( null === OC_Autosave::recovery( $token ), 'Recovery is user scoped' );
$GLOBALS['user_id'] = 1;
$base = [ 'oc_design_nonce' => 'valid', 'oc_design_id' => 7, 'oc_design_name' => 'Recovered', 'oc_custom_type' => 'text_only', 'oc_flat_rate' => '12.50', 'oc_active' => '1',
	'oc_recover_new' => '1', 'oc_recovery_token' => $token,
	'oc_final_manifest' => json_encode( [ 'marker' => 'complete', 'areas' => 0, 'layers' => 0, 'autosaveRevision' => 4 ] ) ];
foreach ( [ 'allowed', 'nonce_valid' ] as $guard ) {
	$GLOBALS[ $guard ] = false;
	$_POST = $base;
	try { $save->invoke( $admin ); throw new LogicException( 'Expected auth rejection' ); } catch ( RuntimeException $e ) {}
	check( [] === $wpdb->rows, 'Auth rejects before writes' );
	$GLOBALS[ $guard ] = true;
}
$_POST = $base;
$wpdb->fail_commit = true;
try { $save->invoke( $admin ); throw new LogicException( 'Expected interrupted recovery' ); } catch ( RuntimeException $e ) {}
check( in_array( 'ROLLBACK', $wpdb->queries, true ), 'Interrupted recovery rolls back' );
check( $fixture === OC_Autosave::recovery( $token ), 'Interrupted recovery preserves original' );
$_POST = $base;
$wpdb->fail_commit = false;
check( 102 === $save->invoke( $admin ), 'Retry creates independent design' );
check( 0 === $wpdb->rows[0][1]['active'], 'Recovered design is inactive' );
check( 6 === OC_Autosave::restore( 7 )['revision'], 'Successful recovery leaves concurrent source autosave alone' );
$_POST = $base;
$_POST['oc_design_areas'] = [ [ 'id' => 41, 'design_id' => 7, 'area_key' => 'front', 'label' => 'Front', 'print_method' => 'uv' ] ];
$_POST['oc_layers'] = [ [ 'id' => 91, 'design_id' => 7, 'area_id' => 41, 'area_index' => '0', 'type' => 'text', 'settings' => '{}' ] ];
$_POST['oc_final_manifest'] = json_encode( [ 'marker' => 'complete', 'areas' => 1, 'layers' => 1, 'autosaveRevision' => 4 ] );
OC_DB::$unsupported_table = 'oc_design_layers';
try { $save->invoke( $admin ); throw new LogicException( 'Expected engine guard' ); } catch ( RuntimeException $e ) { check( str_contains( $e->getMessage(), 'InnoDB' ), 'Stripped IDs pass new-design ownership validation' ); }
check( ! isset( $_POST['oc_design_areas'][0]['id'], $_POST['oc_layers'][0]['id'], $_POST['oc_layers'][0]['area_id'] ), 'Server strips persisted relationships' );
check( '0' === $_POST['oc_layers'][0]['area_index'], 'Local index mapping is retained' );
OC_DB::$unsupported_table = null;
// Re-submit with persisted IDs, not the already stripped POST, and execute all inserts.
$_POST['oc_design_areas'][0]['id'] = 41;
$_POST['oc_layers'][0]['id'] = 91;
$_POST['oc_layers'][0]['area_id'] = 41;
$new_design_id = $save->invoke( $admin );
$layer_row = end( $wpdb->rows )[1];
check( $new_design_id === $layer_row['design_id'] && 41 !== $layer_row['area_id'], 'Layer insert uses newly allocated design and area IDs' );
check( $fixture === OC_Autosave::recovery( $token ), 'Full area/layer recovery keeps original snapshot' );
foreach ( [ false, true ] as $oversized ) {
	$_POST = [ 'oc_design_nonce' => 'valid', 'oc_design_id' => 7, 'oc_design_name' => 'Old cached JS', 'oc_layers' => [ [ 'settings' => $oversized ? str_repeat( 'x', 1048576 ) : '{"default_text":"Alice"}' ] ] ];
	try { $save->invoke( $admin ); throw new LogicException( 'Expected legacy rejection' ); } catch ( RuntimeException $e ) {
		check( str_contains( $e->getMessage(), 'Download retained recovery JSON' ), 'Old form has actionable export' );
		check( str_contains( $e->getMessage(), 'may be truncated' ), 'Never imply completeness' );
	}
	$data = end( $GLOBALS['options'] );
	check( false === $data['complete'], 'Retained form marked incomplete' );
	check( $oversized ? [ 'oc_layers' ] === $data['omittedFields'] : isset( $data['received']['oc_layers'][0]['settings'] ), 'Bounded fields retained with explicit omissions' );
}
// Archive failure must not destroy a legacy draft during replacement.
$GLOBALS['drafts']['oc_autosave_8_1'] = $fixture;
$GLOBALS['drafts']['oc_autosave_8_1']['timestamp']++;
$GLOBALS['fail_retention'] = true;
check( 'failed' === OC_Autosave::store( 8, $new, 5, 4 )['status'], 'Archive failure fails replacement closed' );
check( 4 === $GLOBALS['drafts']['oc_autosave_8_1']['revision'], 'Original survives archive failure' );
print "Admin upgrade recovery checks passed.\n";
