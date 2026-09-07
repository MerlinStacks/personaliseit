<?php
/** Upgrade fixtures with in-memory WP/DB boundaries; no live CSV, design or external filesystem access. */
declare(strict_types=1);
define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'DAY_IN_SECONDS', 86400 );
function __( string $text, string $domain = '' ): string { return $text; }
function absint( mixed $value ): int { return abs( (int) $value ); }
function sanitize_key( string $value ): string { return strtolower( $value ); }
function wp_json_encode( mixed $value ): string|false { return json_encode( $value ); }
function current_time( string $type, bool $gmt = false ): string { return '2026-09-07 00:00:00'; }
function apply_filters( string $name, mixed $value, mixed ...$args ): mixed { return $value; }
function wp_next_scheduled( string $hook, array $args = [] ): bool { return false; }
function wp_schedule_single_event( int $time, string $hook, array $args = [] ): bool { return true; }
function delete_option( string $name ): bool { return true; }
function add_option( string $name, mixed ...$args ): bool { return true; }
function do_action( string $hook, mixed ...$args ): void { $GLOBALS['events'][] = $hook; }
function wc_get_order( int $id ): WC_Order { return $GLOBALS['order']; }
class Upgrade_Item {
	public array $meta = [];
	public function __construct( public int $id ) {}
	public function get_meta( string $key, bool $single = true ): mixed { return $this->meta[$key] ?? ''; }
	public function update_meta_data( string $key, mixed $value ): void { $this->meta[$key] = $value; }
	public function delete_meta_data( string $key ): void { unset( $this->meta[$key] ); }
	public function save_meta_data(): void {}
	public function read_meta_data( bool $force = false ): void {}
	public function get_product_id(): int { return $this->id; }
}
class WC_Order extends Upgrade_Item {
	public array $items = [];
	public array $notes = [];
	public function get_id(): int { return $this->id; }
	public function get_items(): array { return $this->items; }
	public function get_item( int $id ): ?Upgrade_Item { return $this->items[$id] ?? null; }
	public function add_order_note( string $note ): void { $this->notes[] = $note; }
}
class OC_Logger {
	public static function info( string $message ): void {}
	public static function warning( string $message ): void {}
	public static function error( string $message ): void {}
}
class OC_Admin_Settings { public static function get( string $key ): int { return 90; } }
class OC_VDP { public function is_enabled( int $id ): bool { throw new LogicException( 'Upgrade touched the current VDP template/CSV' ); } }
class OC_Print_Base {
	public static function resolve_output_storage_path( string $path, bool $force = false ): ?string {
		return is_file( $path ) && dirname( $path ) === $GLOBALS['fixture_root'] ? $path : null;
	}
}
class OC_Print_Engraving {
	public static array $rendered = [];
	public static function generate( WC_Order $order, int $item, object $area, array $data ): string {
		self::$rendered[] = [ $item, $area, $data ];
		$path = $GLOBALS['fixture_root'] . '/output.bin';
		file_put_contents( $path, json_encode( $data ) );
		return $path;
	}
}
class OC_DB {
	public static array $files = [];
	public static array $jobs = [];
	public static array $released = [];
	public static function print_pipeline_available(): bool { return true; }
	public static function get_print_file( int $id ): ?object { return self::$files[$id] ?? null; }
	public static function get_config_by_product( int $id ): object { return (object) [ 'id' => $id ]; }
	public static function get_print_areas( int $id ): array {
		return [ (object) [ 'id' => 80, 'area_key' => 'front', 'print_method' => 'engraving', 'canvas_w' => 100, 'canvas_h' => 50 ] ];
	}
	public static function insert_print_file( array $data ): int {
		foreach ( self::$files as $file ) {
			if ( $file->order_item_id === $data['order_item_id'] && $file->row_index === $data['row_index'] && $file->print_area_id === $data['print_area_id'] ) { return $file->id; }
		}
		$id = count( self::$files ) + 1;
		self::$files[$id] = (object) ( [ 'id' => $id ] + $data );
		return $id;
	}
	public static function release_deferred_queue_jobs( array $ids ): int { self::$released = $ids; return count( $ids ); }
	public static function get_order_print_pipeline_state( int $id ): string { return 'complete'; }
	public static function get_due_queue_positions( array $ids ): array { return []; }
	public static function claim_queue_job( int $id, int $max ): ?object {
		$job = self::$jobs[$id];
		if ( 'pending' !== $job->status ) { return null; }
		$job->status = 'processing';
		++$job->attempts;
		return clone $job;
	}
	public static function heartbeat_queue_job( int $id, int $attempts ): bool { return true; }
	public static function complete_queue_job( int $id, int $attempts, array $updates ): bool {
		self::$jobs[$id]->status = 'completed';
		foreach ( $updates as $update ) { foreach ( $update['data'] as $key => $value ) { self::$files[$update['id']]->$key = $value; } }
		return true;
	}
	public static function transition_queue_job( int $id, string $status, array $data, mixed ...$args ): bool {
		foreach ( $data as $key => $value ) { self::$jobs[$id]->$key = $value; }
		return true;
	}
}
class Upgrade_DB {
	public string $prefix = 'wp_';
	public string $last_error = '';
	public int $insert_id = 0;
	public bool $unreadable = false;
	public function prepare( string $sql, mixed ...$args ): string { return vsprintf( str_replace( '%s', "'%s'", $sql ), $args ); }
	public function esc_like( string $value ): string { return $value; }
	public function get_col( string $sql ): array { return array_map( static fn( $row ) => $row->area_data, $this->get_results( $sql ) ?? [] ); }
	public function get_var( string $sql ): mixed {
		$this->last_error = '';
		if ( str_contains( $sql, 'GET_LOCK' ) || str_contains( $sql, 'RELEASE_LOCK' ) ) { return 1; }
		$rows = $this->get_results( $sql );
		return $rows ? reset( $rows )->id : null;
	}
	public function get_row( string $sql ): ?object { $rows = $this->get_results( $sql ); return $rows ? reset( $rows ) : null; }
	public function get_results( string $sql ): ?array {
		$this->last_error = '';
		if ( $this->unreadable && str_contains( $sql, 'row_index > 0' ) ) { $this->last_error = 'Unavailable evidence'; return null; }
		if ( ! str_contains( $sql, 'oc_print_files' ) && ! str_contains( $sql, 'oc_print_queue' ) ) { throw new LogicException( 'Unexpected/current-design query: ' . $sql ); }
		$rows = str_contains( $sql, 'oc_print_files' ) ? OC_DB::$files : OC_DB::$jobs;
		foreach ( [ 'order_id', 'order_item_id', 'print_file_id' ] as $key ) {
			if ( preg_match( '/\b' . $key . ' = (\d+)/', $sql, $match ) ) {
				$rows = array_filter( $rows, static fn( $row ) => (int) ( $row->$key ?? 0 ) === (int) $match[1] );
			}
		}
		if ( str_contains( $sql, 'row_index > 0' ) ) { $rows = array_filter( $rows, static fn( $row ) => $row->row_index > 0 ); }
		if ( str_contains( $sql, "status IN ('pending','processing','failed')" ) ) { $rows = array_filter( $rows, static fn( $row ) => in_array( $row->status, [ 'pending', 'processing', 'failed' ], true ) ); }
		return array_values( $rows );
	}
	public function insert( string $table, array $data ): int {
		$this->insert_id = count( OC_DB::$jobs ) + 1;
		OC_DB::$jobs[$this->insert_id] = (object) ( [ 'id' => $this->insert_id, 'attempts' => 0 ] + $data );
		return 1;
	}
}
require ABSPATH . 'includes/class-oc-print-generator.php';
require ABSPATH . 'includes/class-oc-print-queue.php';
require ABSPATH . 'includes/class-oc-render-spec.php';
require ABSPATH . 'includes/class-oc-render-math.php';

$checks = 0;
$check = static function ( bool $ok, string $message ) use ( &$checks ): void { ++$checks; if ( ! $ok ) { throw new RuntimeException( $message ); } };
$root = tempnam( __DIR__, 'oc-upgrade-' );
unlink( $root );
mkdir( $root, 0700 );
$GLOBALS['fixture_root'] = $root;
$GLOBALS['events'] = [];
$wpdb = new Upgrade_DB();
$order = new WC_Order( 100 );
$generator = new OC_Print_Generator();
$legacy_item = new Upgrade_Item( 10 );
$legacy_item->meta['_oc_customisation'] = [ 'v' => 2, 'designId' => 90, 'renderSpec' => [ 'changed' => true ] ];
$other_item = new Upgrade_Item( 20 );
$other_item->meta['_oc_customisation'] = [ 'front' => [ 'text' => 'Unrelated item' ] ];
$order->items = [ 10 => $legacy_item, 20 => $other_item ];
$area = [ 'id' => 70, 'design_id' => 90, 'print_method' => 'engraving', 'canvas_w' => 123, 'canvas_h' => 45 ];
$file = (object) [ 'id' => 1, 'order_id' => 100, 'order_item_id' => 10, 'print_area_id' => 70, 'area_source' => 'design', 'row_index' => 1, 'row_key' => 'original-row-hash', 'file_type' => 'engraving', 'file_status' => 'pending', 'area_snapshot' => json_encode( $area ) ];
$job = (object) [ 'id' => 1, 'print_file_id' => 1, 'order_id' => 100, 'order_item_id' => 10, 'print_area_id' => 70, 'area_source' => 'design', 'row_index' => 1, 'print_method' => 'engraving', 'status' => 'pending', 'attempts' => 0, 'area_data' => json_encode( [ 'text' => 'Original CSV row value' ] ) ];
OC_DB::$files = [ 1 => $file ];
OC_DB::$jobs = [ 1 => $job ];
try {
	$generator->generate_for_order( $order );
	$check( '' !== $legacy_item->get_meta( '_oc_print_generation_hold' ), 'Legacy expansion did not retain a durable review hold' );
	$check( '' === $legacy_item->get_meta( '_oc_vdp_generation_snapshot' ), 'Upgrade guessed a complete expansion snapshot' );
	$check( [ 1, 2 ] === OC_DB::$released && 2 === count( OC_DB::$files ), 'Retained pending job or unrelated item did not proceed' );
	$check( $job->area_data === OC_DB::$jobs[1]->area_data, 'Legacy queue payload was rewritten' );
	$notes = count( $order->notes );
	$generator->generate_for_order( $order );
	$check( 2 === count( OC_DB::$files ) && 2 === count( OC_DB::$jobs ), 'Repeated upgrade duplicated jobs/files' );
	$check( $notes === count( $order->notes ), 'Repeated legacy review produced duplicate notes' );
	OC_Print_Queue::instance()->process_one( 1 );
	$check( 'completed' === OC_DB::$jobs[1]->status, 'Existing VDP job without expansion snapshot did not finish' );
	$check( 'Original CSV row value' === OC_Print_Engraving::$rendered[0][2]['text'] && 123 === OC_Print_Engraving::$rendered[0][1]->canvas_w, 'Worker mixed current values/geometry into original VDP row' );
	$check( ! in_array( 'oc_print_files_generated', $GLOBALS['events'], true ), 'Incomplete expansion was announced as complete' );
	OC_Print_Queue::instance()->process_one( 2 );
	$check( 'completed' === OC_DB::$jobs[2]->status, 'Unrelated item was held by legacy VDP review' );

	// Missing evidence must stop before a live design lookup or any rendering.
	OC_DB::$jobs[1]->status = 'pending';
	OC_DB::$jobs[1]->attempts = 0;
	OC_DB::$files[1]->area_snapshot = '';
	$count = count( OC_Print_Engraving::$rendered );
	OC_Print_Queue::instance()->process_one( 1 );
	$check( $count === count( OC_Print_Engraving::$rendered ) && str_contains( OC_DB::$jobs[1]->error_message, 'snapshot' ), 'Missing VDP snapshot fell back to live design' );
	$check( 'Original CSV row value' === json_decode( OC_DB::$jobs[1]->area_data, true )['text'], 'Blocked worker lost original row evidence' );
	OC_DB::$files[1]->area_snapshot = json_encode( $area );
	$bad = clone OC_DB::$files[1];
	$bad->print_area_id = 999;
	try { OC_Print_Generator::retained_vdp_area( $bad, [] ); $check( false, 'Mismatched area identity accepted' ); }
	catch ( RuntimeException $error ) { $check( str_contains( $error->getMessage(), 'snapshot' ), 'Unexpected snapshot failure' ); }

	// A row without a job remains held; no new CSV expansion or replacement job.
	OC_DB::$jobs = [];
	$method = new ReflectionMethod( OC_Print_Generator::class, 'queue_vdp_files' );
	$result = $method->invoke( $generator, $order, 10, 90, [], current_time( 'mysql' ), '2099-01-01' );
	$check( true === $result['held'] && [] === $result['queue_ids'] && [] === OC_DB::$jobs, 'Missing legacy jobs were guessed from current CSV' );
	try { $generator->regenerate( 1 ); $check( false, 'Regeneration used unexpanded order renderSpec instead of missing original row payload' ); }
	catch ( RuntimeException $error ) { $check( str_contains( $error->getMessage(), 'original VDP row payload' ), 'Missing row regeneration did not require review' ); }
	OC_DB::$jobs = [ 1 => clone $job ];
	OC_DB::$jobs[1]->status = 'failed';
	$original_job = clone OC_DB::$jobs[1];
	$result = $method->invoke( $generator, $order, 10, 90, [], current_time( 'mysql' ), '2099-01-01' );
	$check( [] === $result['queue_ids'] && $original_job == OC_DB::$jobs[1], 'Expansion recovery reset a failed retained job without explicit retry' );
	OC_DB::$files = [];
	OC_DB::$jobs = [ 1 => clone $job ];
	$result = $method->invoke( $generator, $order, 10, 90, [], current_time( 'mysql' ), '2099-01-01' );
	$check( true === $result['held'] && [] === OC_DB::$files, 'Orphan legacy job caused current CSV expansion' );
	OC_DB::$jobs = [];
	$result = $method->invoke( $generator, $order, 10, 90, [], current_time( 'mysql' ), '2099-01-01' );
	$check( true === $result['held'] && [] === OC_DB::$files && [] === OC_DB::$jobs, 'Retention erased legacy provenance and allowed a new CSV expansion' );

	// Invalid item snapshots and unreadable evidence do not abort later items.
	foreach ( [ false, true ] as $unreadable ) {
		OC_DB::$files = [];
		OC_DB::$jobs = [];
		$wpdb->unreadable = $unreadable;
		$legacy_item->meta['_oc_vdp_generation_snapshot'] = $unreadable ? '' : [ 'version' => 99 ];
		$generator->generate_for_order( $order );
		$check( 1 === count( OC_DB::$files ) && 20 === reset( OC_DB::$files )->order_item_id, 'Item-specific VDP failure aborted unrelated generation' );
	}
	$wpdb->unreadable = false;
	OC_DB::$files = [];
	OC_DB::$jobs = [];
	$spec_area = [ 'id' => 70, 'printMethod' => 'engraving', 'bounds' => [ 'w' => 123, 'h' => 45 ], 'layers' => [ [ 'id' => 7, 'type' => 'text', 'input' => [ 'value' => 'Unexpanded order value' ] ] ] ];
	$legacy_item->meta['_oc_vdp_generation_snapshot'] = [
		'version' => 1, 'design_id' => 90,
		'rows' => [ [ 'row' => [ 'name' => 'Proven original' ], 'values' => [ 7 => 'Proven original' ] ] ],
		'render_spec' => [ 'designId' => 90, 'areas' => [ $spec_area ] ],
	];
	$result = $method->invoke( $generator, $order, 10, 90, [ 'renderSpec' => [ 'changed' => true ] ], current_time( 'mysql' ), '2099-01-01' );
	$check( 1 === $result['queued'] && '' === $legacy_item->get_meta( '_oc_print_generation_hold' ) && '' === $legacy_item->get_meta( '_oc_vdp_legacy_expansion' ), 'Valid retained expansion could not resume/clear review hold' );
	$check( 'Proven original' === json_decode( OC_DB::$jobs[1]->area_data, true )['text'], 'Known expansion did not use its original mapped row values' );
	$retry = $method->invoke( $generator, $order, 10, 90, [], current_time( 'mysql' ), '2099-01-01' );
	$check( 0 === $retry['queued'] && 1 === count( OC_DB::$jobs ), 'Known expansion replay was not idempotent' );
	$file_without_geometry = clone OC_DB::$files[1];
	$file_without_geometry->area_snapshot = '';
	$retained_area = OC_Print_Generator::retained_vdp_area( $file_without_geometry, json_decode( OC_DB::$jobs[1]->area_data, true ) );
	$check( 123.0 === $retained_area->canvas_w, 'Queue-retained renderSpecArea could not supply original geometry' );
	print "PASS: {$checks} print upgrade checks\n";
} finally {
	foreach ( glob( $root . '/*' ) as $path ) { unlink( $path ); }
	rmdir( $root );
}
