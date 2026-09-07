<?php
/** Dependency-free checks: php tests/admin-transaction-regressions.php */
require __DIR__ . '/admin-autosave-regressions.php';
function is_wp_error( $value ) { return $value instanceof WP_Error; }
class WP_Error {
	public function __construct( public $code, public $message, public $data = [] ) {}
	public function get_error_message() { return $this->message; }
}
class OC_Logger { public static function error( $message ) {} }
class WP_REST_Request {
	public function get_header( $name ) { return 'valid'; }
	public function get_param( $name ) { return 7; }
	public function get_file_params() { throw new LogicException( 'Engine rejection must precede upload handling' ); }
}
require __DIR__ . '/../includes/admin/class-oc-admin-colours.php';
require __DIR__ . '/../includes/admin/class-oc-admin-clipart.php';
require __DIR__ . '/../includes/class-oc-rest-api.php';

// Any database access after the engine guard is a failure, including START or a lock.
$wpdb = new class {
	public $prefix = 'wp_';
	public function __call( $name, $args ) { throw new LogicException( 'Unexpected database access: ' . $name ); }
};
$_POST = [ 'id' => 7, 'name' => 'Group' ];
$_GET = [ 'id' => 7, '_wpnonce' => 'valid' ];
$cases = [];
foreach ( [ 'Fonts' => 'font', 'Colours' => 'colour', 'Clipart' => 'clipart' ] as $class => $suffix ) {
	foreach ( [ 'create', 'update', 'delete' ] as $action ) {
		$cases[] = [ 'OC_Admin_' . $class, 'ajax_group_' . $action, [ 'oc_' . $suffix . '_groups', 'oc_' . $suffix . '_group_items' ] ];
	}
}
$design_tables = [ 'oc_designs', 'oc_design_print_areas', 'oc_design_layers' ];
$cases[] = [ 'OC_Admin_Products', 'handle_design_duplicate', $design_tables ];
$cases[] = [ 'OC_Admin_Products', 'handle_design_delete', array_merge( $design_tables, [ 'oc_product_assignments', 'oc_vdp_templates', 'oc_vdp_fields' ] ) ];
$cases[] = [ 'OC_Admin_Colours', 'handle_delete', [ 'oc_colours', 'oc_colour_group_items' ] ];
foreach ( $cases as [ $class, $method, $tables ] ) {
	foreach ( $tables as $table ) {
		OC_DB::$unsupported_table = $table;
		$reflection = new ReflectionMethod( $class, $method );
		$instance = $reflection->isStatic() ? null : ( new ReflectionClass( $class ) )->newInstanceWithoutConstructor();
		try {
			$reflection->invoke( $instance );
			throw new LogicException( 'Expected rejection: ' . $class . '::' . $method );
		} catch ( RuntimeException $error ) {
			check( str_contains( $error->getMessage(), 'InnoDB' ), $error->getMessage() );
		}
		check( $tables === OC_DB::$checked_tables, 'Guard must cover all participating tables: ' . $method );
	}
}
OC_DB::$unsupported_table = 'options';
$reserve = new ReflectionMethod( OC_Rest_API::class, 'reserve_budgets' );
$error = $reserve->invoke( null, [ [ 'key' => 'test' ] ] );
check( $error instanceof WP_Error && 503 === $error->data['status'], 'Budget reservations fail closed before writes' );
$reduce = new ReflectionMethod( OC_Rest_API::class, 'reduce_budget_reservation' );
check( false === $reduce->invoke( null, [ 'items' => [ 'test' => [ 'count' => 1 ] ] ], [ 'test' => [ 'count' => 1 ] ] ), 'Budget release fails closed before writes' );

$api = ( new ReflectionClass( OC_Rest_API::class ) )->newInstanceWithoutConstructor();
foreach ( [ 'oc_designs', 'oc_design_layers', 'oc_vdp_templates', 'oc_vdp_fields' ] as $table ) {
	OC_DB::$unsupported_table = $table;
	$error = $api->upload_vdp_csv( new WP_REST_Request() );
	check( $error instanceof WP_Error && 'transaction_unavailable' === $error->code && 503 === $error->data['status'], 'VDP rejects unsupported table before upload handling: ' . $table );
}

// Real HTTP upload provenance cannot be manufactured by CLI. Verify ordering in the handler.
$method = new ReflectionMethod( OC_Rest_API::class, 'upload_vdp_csv' );
$lines = file( $method->getFileName() );
$source = implode( '', array_slice( $lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1 ) );
$guard = strpos( $source, "OC_DB::tables_support_transactions( [ 'oc_designs', 'oc_design_layers', 'oc_vdp_templates', 'oc_vdp_fields' ] )" );
check( false !== $guard, 'VDP guards the design lock and both template tables' );
foreach ( [ 'self::protected_vdp_directory()', 'move_uploaded_file(', "'START TRANSACTION'", 'OC_DB::delete_vdp_template(' ] as $operation ) {
	check( false !== strpos( $source, $operation ) && $guard < strpos( $source, $operation ), 'VDP guard precedes ' . $operation );
}

require __DIR__ . '/../includes/class-oc-vdp.php';
// Execute the actual lock/validation block without manufacturing an HTTP upload or touching files.
$start = strpos( $source, '$locked_design = ' );
$end = strpos( $source, '$old_template = ' );
check( false !== $start && false !== $end && $start < $end, 'Mapping validation precedes template replacement' );
check( strpos( $source, "'START TRANSACTION'" ) < $start, 'Mapping validation runs inside the transaction' );
check( ! str_contains( $source, 'OC_DB::get_design_layers(' ), 'VDP must not use cached layers' );
$validate_mapping = eval( 'return function ( $wpdb, $csv_data, &$validation_error ) { $design_id = 7; $vdp = new OC_VDP(); '
	. substr( $source, $start, $end - $start ) . ' return $layer_ids; };' );
$wpdb = new class {
	public $prefix = 'wp_';
	public $last_error = '';
	public $design;
	public $layers = [];
	public $locked = false;
	public $fail_layers = false;
	public function prepare( $query, ...$args ) { check( [ 7 ] === $args, 'Reads are scoped to the requested design' ); return $query; }
	public function get_row( $query ) {
		check( str_contains( $query, 'oc_designs WHERE design_id' ) === false && str_contains( $query, 'WHERE id = %d FOR UPDATE' ), 'Design read acquires a row lock' );
		$this->locked = true;
		return $this->design;
	}
	public function get_results( $query ) {
		check( $this->locked, 'Fresh layers are read after the design lock' );
		check( str_contains( $query, 'oc_design_layers WHERE design_id = %d ORDER BY area_id ASC, sort_order ASC, id ASC FOR UPDATE' ), 'Current layer read locks rows in deterministic mapping order' );
		if ( $this->fail_layers ) { $this->last_error = 'Read failed'; return []; }
		return $this->layers;
	}
};
$layer = (object) [ 'id' => 91, 'visible' => 1, 'locked' => 0, 'type' => 'text', 'label' => 'Name', 'settings' => '{}' ];
foreach ( [ 'fresh', 'reordered', 'removed', 'hidden', 'locked', 'type_changed', 'required', 'char_limit', 'inactive', 'deleted_design', 'read_error' ] as $case ) {
	$wpdb->design = (object) [ 'id' => 7, 'active' => 1 ];
	$wpdb->layers = [ clone $layer ];
	$wpdb->locked = false;
	$wpdb->fail_layers = false;
	$wpdb->last_error = '';
	$csv = [ 'headers' => [ 'name' ], 'rows' => [ [ 'name' => 'Alice' ] ] ];
	$validation_error = null;
	if ( 'reordered' === $case ) { $wpdb->layers = [ (object) array_merge( (array) $layer, [ 'id' => 92 ] ), clone $layer ]; }
	if ( 'removed' === $case ) { $wpdb->layers = []; }
	if ( 'hidden' === $case ) { $wpdb->layers[0]->visible = 0; }
	if ( 'locked' === $case ) { $wpdb->layers[0]->locked = 1; }
	if ( 'type_changed' === $case ) { $wpdb->layers[0]->type = 'image'; }
	if ( 'required' === $case ) { $wpdb->layers[0]->settings = '{"required":true}'; $csv['rows'][0]['name'] = ''; }
	if ( 'char_limit' === $case ) { $wpdb->layers[0]->settings = '{"char_limit":2}'; }
	if ( 'inactive' === $case ) { $wpdb->design->active = 0; }
	if ( 'deleted_design' === $case ) { $wpdb->design = null; }
	if ( 'read_error' === $case ) { $wpdb->fail_layers = true; }
	try {
		$ids = $validate_mapping( $wpdb, $csv, $validation_error );
		if ( ! in_array( $case, [ 'fresh', 'reordered' ], true ) ) { throw new LogicException( 'Expected mapping rejection: ' . $case ); }
		check( ( 'fresh' === $case ? [ 91 ] : [ 92, 91 ] ) === $ids, 'Mapping uses authoritative current IDs and ordering' );
	} catch ( RuntimeException $error ) {
		check( ! in_array( $case, [ 'fresh', 'reordered' ], true ), $error->getMessage() );
		if ( in_array( $case, [ 'required', 'char_limit' ], true ) ) {
			check( 'invalid_csv_value' === $validation_error->code, 'Fresh settings validate every CSV value' );
		} elseif ( in_array( $case, [ 'removed', 'hidden', 'locked', 'type_changed' ], true ) ) {
			check( 'invalid_csv_fields' === $validation_error->code, 'Fresh eligibility controls mapping capacity' );
		} elseif ( 'inactive' === $case ) {
			check( 'invalid_design' === $validation_error->code, 'Fresh design status must remain active' );
		} else {
			check( null === $validation_error, 'Database failures remain server errors' );
		}
	}
}
check( str_contains( $source, "\$wpdb->query( 'ROLLBACK' );\n\t\t\tself::delete_vdp_file( \$filepath );" ), 'Failed mapping validation rolls back and removes the staged upload' );
print "Admin transaction regression checks passed.\n";
