<?php
/** Dependency-free readiness regressions. Run: php tests/compatibility-readiness-regressions.php */
define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'OC_DB_VERSION', '1.14.0' );
define( 'HOUR_IN_SECONDS', 3600 );
$options = [ 'oc_db_version' => OC_DB_VERSION ];
$transients = [];
$can_manage = true;
$valid_nonce = true;
function get_option( $key, $default = false ) { return $GLOBALS['options'][ $key ] ?? $default; }
function get_transient( $key ) { return $GLOBALS['transients'][ $key ] ?? false; }
function set_transient( $key, $value, $ttl ) { $GLOBALS['transients'][ $key ] = $value; expect( 300 === $ttl, 'bounded cache' ); }
function delete_transient( $key ) { unset( $GLOBALS['transients'][ $key ] ); }
function current_user_can( $cap ) { expect( 'manage_woocommerce' === $cap, 'manager capability' ); return $GLOBALS['can_manage']; }
function wp_doing_ajax() { return false; }
function esc_html__( $text, $domain ) { return $text; }
function __( $text, $domain ) { return $text; }
function esc_html( $text ) { return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $text ) { return esc_html( $text ); }
function wp_nonce_field( $action ) { expect( 'oc_recheck_readiness' === $action, 'notice nonce action' ); print '<input name="_wpnonce" value="test">'; }
function wp_die( $text, $title, $args ) { throw new RuntimeException( (string) $args['response'] ); }
function check_admin_referer( $action ) { expect( 'oc_recheck_readiness' === $action, 'nonce action' ); if ( ! $GLOBALS['valid_nonce'] ) { throw new RuntimeException( 'nonce' ); } }
function admin_url( $path ) { return 'https://example.test/wp-admin/' . $path; }
function wp_safe_redirect( $url ) {
	expect( 'https://example.test/wp-admin/admin.php?page=overcustomise-settings&tab=system' === $url, 'fixed status-page redirect' );
	throw new RuntimeException( 'redirect' );
}
function expect( $condition, $label ) { if ( ! $condition ) { throw new RuntimeException( $label ); } }
class OC_Logger { public static function error( $message ) {} }
class OC_Storage_Upgrade {
	public static array $messages = [];
	public static int $calls = 0;
	public static function reports(): array {
		self::$calls++;
		expect( OC_Upload_Handler::$calls > 0 && 0 === OC_Upload_Handler::$calls % 4, 'consume diagnostics after all storage checks' );
		return self::$messages;
	}
}
class OC_Upload_Handler {
	public static array $blocked = [];
	public static int $calls = 0;
	public static function private_storage_path( $directory, $force ) {
		self::$calls++;
		expect( true === $force, 'storage protection is verified' );
		return in_array( $directory, self::$blocked, true ) ? null : '/secret/private/' . $directory;
	}
}
require ABSPATH . 'includes/class-oc-db.php';
require ABSPATH . 'includes/class-oc-system-status.php';

class Readiness_DB {
	public string $prefix = 'wp_';
	public string $last_error = '';
	public array $engines = [];
	public mixed $xtradb = 'YES';
	public string $failure = '';
	public string $missing = '';
	public int $calls = 0;
	public array $columns = [];
	public array $indexes = [];
	public function __construct() {
		// Build metadata fixtures from the shipped CREATE statements, including every plugin table.
		$source = file_get_contents( ABSPATH . 'includes/class-oc-db.php' );
		preg_match_all( '/CREATE TABLE \{\$wpdb->prefix\}(oc_\w+) \((.*?)\) \$charset;/s', $source, $tables, PREG_SET_ORDER );
		expect( 21 === count( $tables ), 'schema fixtures include all tables' );
		foreach ( $tables as $table ) {
			$name = 'wp_' . $table[1];
			foreach ( explode( "\n", $table[2] ) as $line ) {
				$line = trim( $line, " \t\r," );
				if ( preg_match( '/^(PRIMARY KEY|UNIQUE KEY\s+(\w+)|KEY\s+(\w+))\s*\(([^)]+)\)/', $line, $index ) ) {
					$index_name = str_starts_with( $index[1], 'PRIMARY' ) ? 'PRIMARY' : ( $index[2] ?: $index[3] );
					foreach ( explode( ',', $index[4] ) as $position => $column ) {
						$this->indexes[] = (object) [ 'TABLE_NAME' => $name, 'INDEX_NAME' => $index_name, 'NON_UNIQUE' => str_starts_with( $index[1], 'KEY' ) ? 1 : 0, 'SEQ_IN_INDEX' => $position + 1, 'COLUMN_NAME' => trim( $column ) ];
					}
				} elseif ( preg_match( '/^(\w+)\s+(\w+)(.*)$/', $line, $column ) ) {
					$this->columns[] = (object) [ 'TABLE_NAME' => $name, 'COLUMN_NAME' => $column[1], 'DATA_TYPE' => strtolower( $column[2] ), 'COLUMN_TYPE' => $column[2] . $column[3], 'IS_NULLABLE' => str_contains( $line, 'NOT NULL' ) ? 'NO' : 'YES' ];
				}
			}
		}
	}
	public function prepare( $sql, ...$args ) {
		foreach ( $args as $arg ) { $sql = preg_replace( '/%s/', "'" . $arg . "'", $sql, 1 ); }
		return $sql;
	}
	public function get_var( $sql ) {
		$this->calls++;
		$this->last_error = '';
		if ( $this->failure && str_contains( $sql, $this->failure ) ) { $this->last_error = '/secret/database-error'; return null; }
		if ( str_contains( $sql, 'IS_FREE_LOCK' ) ) { return '1'; }
		if ( str_contains( $sql, 'INFORMATION_SCHEMA.ENGINES' ) ) { return $this->xtradb; }
		preg_match( "/TABLE_NAME = 'wp_(\w+)'/", $sql, $match );
		return array_key_exists( $match[1], $this->engines ) ? $this->engines[ $match[1] ] : 'InnoDB';
	}
	public function get_results( $sql ) {
		$this->calls++;
		$this->last_error = '';
		if ( $this->failure && str_contains( $sql, $this->failure ) ) { $this->last_error = '/secret/database-error'; return []; }
		$rows = str_contains( $sql, 'INFORMATION_SCHEMA.COLUMNS' ) ? $this->columns : $this->indexes;
		return array_values( array_filter( $rows, fn ( $row ) => str_contains( $sql, "'" . $row->TABLE_NAME . "'" ) && $row->TABLE_NAME !== $this->missing ) );
	}
}
$wpdb = new Readiness_DB();
expect( 'ready' === OC_DB::schema_readiness(), 'complete current schema' );
expect( 'ready' === OC_DB::schema_readiness( true ), 'complete print schema' );
expect( OC_DB::tables_support_transactions( [ 'oc_print_files', 'oc_print_queue' ] ), 'InnoDB' );
foreach ( [ 'MyISAM', 'Aria', 'MEMORY', 'unknown' ] as $engine ) {
	$wpdb->engines = [ 'oc_print_queue' => $engine ];
	expect( ! OC_DB::tables_support_transactions( [ 'oc_print_files', 'oc_print_queue' ] ), 'mixed unsafe engines fail closed' );
	expect( 'unsupported_engine' === OC_DB::transaction_readiness( [ 'oc_print_queue' ] )['oc_print_queue'], 'unsupported engine distinguished' );
}
$wpdb->engines = [ 'oc_print_queue' => 'XtraDB' ];
expect( OC_DB::tables_support_transactions( [ 'oc_print_files', 'oc_print_queue' ] ), 'verified mixed InnoDB/XtraDB' );
foreach ( [ null, 'NO', 'DISABLED' ] as $evidence ) {
	$wpdb->xtradb = $evidence;
	expect( ! OC_DB::tables_support_transactions( [ 'oc_print_queue' ] ), 'XtraDB needs positive evidence' );
}
$wpdb->xtradb = 'YES';
$wpdb->failure = 'INFORMATION_SCHEMA.ENGINES';
expect( 'metadata_query_failed' === OC_DB::transaction_readiness( [ 'oc_print_queue' ] )['oc_print_queue'], 'XtraDB evidence failure' );
$wpdb->failure = 'INFORMATION_SCHEMA.TABLES';
expect( 'metadata_query_failed' === OC_DB::transaction_readiness( [ 'oc_print_files' ] )['oc_print_files'], 'table query failure' );
foreach ( [ 'COLUMNS', 'STATISTICS' ] as $metadata ) {
	$wpdb->failure = 'INFORMATION_SCHEMA.' . $metadata;
	expect( 'metadata_query_failed' === OC_DB::schema_readiness(), 'schema query failure: ' . $metadata );
}
$wpdb->failure = '';
$wpdb->engines = [ 'oc_print_files' => null ];
expect( 'table_metadata_missing' === OC_DB::transaction_readiness( [ 'oc_print_files' ] )['oc_print_files'], 'missing table is not engine evidence' );
$wpdb->engines = [];
$report = OC_System_Status::readiness_report( true );
expect( ! $report['print_retry_pause'], 'current healthy schema and runtime' );
$calls = $wpdb->calls;
$storage_calls = OC_Upload_Handler::$calls;
expect( $report === OC_System_Status::readiness_report(), 'cached report reused' );
expect( $calls === $wpdb->calls && $storage_calls === OC_Upload_Handler::$calls, 'no probes on cache hit' );
$diagnostic_calls = OC_Storage_Upgrade::$calls;
expect( $report === OC_System_Status::cached_readiness_report(), 'worker reads healthy cached report' );
expect( $calls === $wpdb->calls && $storage_calls === OC_Upload_Handler::$calls && $diagnostic_calls === OC_Storage_Upgrade::$calls, 'cached-only hit performs no probes or storage calls' );
$cache_key = 'oc_compatibility_readiness_v2';
foreach ( [ false, [], array_replace( $report, [ 'checked_at' => time() - 300 ] ), array_replace( $report, [ 'checked_at' => time() + 60 ] ), array_replace( $report, [ 'checked_at' => (string) time() ] ), array_replace( $report, [ 'recheck_after' => 3600 ] ), array_replace( $report, [ 'target_version' => 'other' ] ), array_replace( $report, [ 'schema_version' => 'other' ] ) ] as $invalid ) {
	$transients[ $cache_key ] = $invalid;
	$before = $transients;
	expect( null === OC_System_Status::cached_readiness_report(), 'missing, expired or invalid report is unknown, not pause' );
	expect( $before === $transients, 'cached-only miss never writes or refreshes cache' );
}
unset( $transients[ $cache_key ] );
expect( null === OC_System_Status::cached_readiness_report(), 'absent report does not build' );
expect( $calls === $wpdb->calls && $storage_calls === OC_Upload_Handler::$calls && $diagnostic_calls === OC_Storage_Upgrade::$calls, 'cached-only misses perform no metadata, HTTP, directory or diagnostic IO' );
$transients[ $cache_key ] = array_replace( $report, [ 'checked_at' => time() - 100 ] );
expect( null !== OC_System_Status::cached_readiness_report(), 'unexpired report remains valid' );
$wpdb->engines = [ 'oc_print_queue' => 'MyISAM' ];
$report = OC_System_Status::readiness_report( true );
expect( 'ready' === $report['schema'] && 'ready' === $report['migration'] && $report['print_retry_pause'], 'current schema is not engine readiness' );
$wpdb->engines = [ 'oc_colours' => 'MyISAM' ];
$wpdb->missing = 'wp_oc_fonts';
$report = OC_System_Status::readiness_report( true );
expect( 'schema_incomplete' === $report['schema'] && ! $report['resources']['colours'] && ! $report['print_retry_pause'], 'unrelated resources do not disable print' );
$wpdb->missing = 'wp_oc_print_files';
expect( OC_System_Status::readiness_report( true )['print_retry_pause'], 'current version does not prove print schema readiness' );
$wpdb->missing = '';
OC_Upload_Handler::$blocked = [ 'print-files' ];
$report = OC_System_Status::readiness_report( true );
expect( 'storage_blocked' === $report['storage']['print-files'] && $report['print_retry_pause'], 'blocked storage pauses print' );
expect( ! str_contains( json_encode( $report ), '/secret/' ), 'report contains no paths/errors' );
OC_Upload_Handler::$blocked = [];
expect( ! OC_System_Status::readiness_report( true )['print_retry_pause'], 'recheck clears repaired state' );
OC_Storage_Upgrade::$messages = [
	'/secret/private/source' => 'Relocation blocked: verified current private storage is unavailable; source retained.',
	'/secret/private/old-copy' => 'Private copy published; old source retained pending reference-safe cleanup and any required HTTP denial/purge.',
	'/secret/private/duplicate' => 'VDP path relocated without changing template fields/design; source retained pending reference-safe cleanup.',
	'/secret/private/http' => 'HTTPS uploads-route denial verified (not proof of unknown aliases or external mirrors).',
	'/secret/private/changed' => 'Relocation source hash did not match; source retained.',
	'/secret/private/pointer' => 'VDP pointer publication raced or failed; source and copied destination retained for reference-safe reconciliation.',
	'/secret/private/hostile' => '<script>leak("/secret/private/file")</script>',
	'/secret/private/suffixed' => 'Private root overlaps the document root. /secret/private/root',
	'/secret/private/typed' => [ 'detail' => '/secret/private/array' ],
];
$report = OC_System_Status::readiness_report( true );
expect( [
	'relocation_storage_blocked' => 'relocation_storage_blocked',
	'relocation_source_retained' => 'relocation_source_retained',
	'storage_http_verified' => 'ready',
	'relocation_source_review' => 'relocation_source_review',
	'relocation_publication_blocked' => 'relocation_publication_blocked',
	'storage_diagnostic_unknown' => 'storage_diagnostic_unknown',
] === $report['storage_upgrade'], 'exact known messages map to deduplicated codes; arbitrary messages are discarded' );
expect( ! $report['print_retry_pause'], 'relocation diagnostics do not impose a blanket print pause' );
expect( ! str_contains( json_encode( $report ), '/secret/' ) && ! str_contains( json_encode( $report ), '<script>' ), 'persisted report discards message and root paths' );
expect( $report === OC_System_Status::cached_readiness_report(), 'worker receives only sanitized relocation diagnostics' );
ob_start();
OC_System_Status::readiness_notice();
$notice = ob_get_clean();
expect( str_contains( $notice, 'relocation_storage_blocked' ) && str_contains( $notice, 'relocation_source_retained' ), 'admin notice exposes relocation warnings' );
expect( str_contains( $notice, 'reference-safe source cleanup' ) && ! str_contains( $notice, '/secret/' ) && ! str_contains( $notice, '<script>' ), 'admin guidance is fixed text with no paths or raw messages' );
OC_Storage_Upgrade::$messages = [];
expect( [] === OC_System_Status::readiness_report( true )['storage_upgrade'], 'fresh report does not retain obsolete request-local warnings' );
$options['oc_db_version'] = '0';
expect( 'migration_required' === OC_System_Status::readiness_report()['migration'], 'version change invalidates cache' );
$options['oc_db_version'] = OC_DB_VERSION;
$options['oc_db_upgrade_lock'] = json_encode( [ 'expires' => time() + 60 ] );
expect( 'migration_running' === OC_DB::migration_readiness(), 'active migration remains locked' );
unset( $options['oc_db_upgrade_lock'] );
$wpdb->failure = 'IS_FREE_LOCK';
expect( 'metadata_query_failed' === OC_DB::migration_readiness(), 'lock inspection failure' );
$wpdb->failure = '';

foreach ( [ [ false, 'POST', true, '403' ], [ true, 'GET', true, '405' ], [ true, 'POST', false, 'nonce' ] ] as [ $can_manage, $method, $valid_nonce, $expected ] ) {
	$_SERVER['REQUEST_METHOD'] = $method;
	$before = $transients;
	try { OC_System_Status::recheck_readiness(); throw new RuntimeException( 'accepted invalid recheck' ); }
	catch ( RuntimeException $error ) { expect( $expected === $error->getMessage(), 'recheck authorization' ); }
	expect( $before === $transients, 'rejected recheck preserves state' );
}
$can_manage = true;
$valid_nonce = true;
$_SERVER['REQUEST_METHOD'] = 'POST';
$wpdb->engines = [];
$calls = $wpdb->calls;
try { OC_System_Status::recheck_readiness(); }
catch ( RuntimeException $error ) { expect( 'redirect' === $error->getMessage(), 'authorized recheck redirects' ); }
expect( $wpdb->calls > $calls && ! OC_System_Status::readiness_report()['print_retry_pause'], 'authorized recheck refreshes repaired state' );
$can_manage = false;
$calls = $wpdb->calls;
ob_start();
OC_System_Status::readiness_notice();
expect( '' === ob_get_clean() && $calls === $wpdb->calls, 'guest notice neither probes nor exposes details' );
print "Compatibility readiness regressions passed.\n";
