<?php
/** Dependency-free readiness regressions. Run: php tests/compatibility-readiness-regressions.php */
define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'OC_PATH', ABSPATH );
$wp_version = '6.8';
define( 'OC_DB_VERSION', '1.14.0' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
$user_options = [];
$user_id      = 1;
$site_id      = 1;
function get_current_user_id() {
	return $GLOBALS['user_id'];
}
function get_user_option( $key ) {
	return $GLOBALS['user_options'][ $GLOBALS['site_id'] ][ get_current_user_id() ][ $key ] ?? false;
}
function update_user_option( $user, $key, $value, $is_global ) {
	expect( false === $is_global, 'site-scoped preference' );
	$GLOBALS['user_options'][ $GLOBALS['site_id'] ][ $user ][ $key ] = $value;
}
function wp_json_encode( $value ) {
	return json_encode( $value ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Implements the WordPress wrapper in this standalone stub.
}
function esc_attr( $value ) {
	return esc_html( $value );
}
$options     = [ 'oc_db_version' => OC_DB_VERSION ];
$transients  = [];
$can_manage  = true;
$valid_nonce = true;
function get_option( $key, $default_value = false ) {
	return $GLOBALS['options'][ $key ] ?? $default_value;
}
function get_transient( $key ) {
	return $GLOBALS['transients'][ $key ] ?? false;
}
function set_transient( $key, $value, $ttl ) {
	$GLOBALS['transients'][ $key ] = $value;
	expect( 300 === $ttl, 'bounded cache' );
}
function delete_transient( $key ) {
	unset( $GLOBALS['transients'][ $key ] );
}
function current_user_can( $cap ) {
	expect( 'manage_woocommerce' === $cap, 'manager capability' );
	return $GLOBALS['can_manage'];
}
function wp_doing_ajax() {
	return false;
}
function esc_html__( $text, $domain ) {
	return $text;
}
function __( $text, $domain ) {
	return $text;
}
function esc_html( $text ) {
	return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
}
function esc_url( $text ) {
	return esc_html( $text );
}
function wp_nonce_field( $action ) {
	expect( 'oc_recheck_readiness' === $action || preg_match( '/^oc_dismiss_readiness_[a-f0-9]{64}$/', $action ), 'notice nonce action' );
	print '<input name="_wpnonce" value="test">';
}
function wp_die( $text, $title, $args ) {
	throw new RuntimeException( (string) $args['response'] ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Test control flow carries the response code, not HTML output.
}
function check_admin_referer( $action ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- This stub verifies the nonce action against the test fixture.
	expect( 'oc_recheck_readiness' === $action || 'oc_dismiss_readiness_' . ( $_POST['fingerprint'] ?? '' ) === $action, 'nonce action bound to fingerprint' );
	if ( ! $GLOBALS['valid_nonce'] ) {
		throw new RuntimeException( 'nonce' );
	}
}
function admin_url( $path ) {
	return 'https://example.test/wp-admin/' . $path;
}
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
	public static function heic_conversion_is_available() { return false; }
	public static array $blocked = [];
	public static int $calls = 0;
	public static function private_storage_path( $directory, $force ) {
		self::$calls++;
		expect( true === $force, 'storage filesystem validation is requested, not HTTP proof' );
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
$cache_key = 'oc_compatibility_readiness_v5';
$transients['oc_compatibility_readiness_v4'] = array_replace( $report, [ 'storage_upgrade' => [ 'storage_evidence_revoked' => 'storage_evidence_revoked' ], 'storage' => [ 'print-files' => 'storage_blocked' ], 'print_retry_pause' => true ] );
$transients['oc_compatibility_readiness_v2'] = array_replace( $report, [ 'storage_upgrade' => [ 'storage_http_verified' => 'ready' ] ] );
unset( $transients[ $cache_key ] );
expect( null === OC_System_Status::cached_readiness_report(), 'old readiness cache is ignored even when unexpired' );
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
$checks = array_column( OC_System_Status::checks(), null, 'key' );
expect( ! $checks['readiness_transactions_print']['available'] && str_contains( $checks['readiness_transactions_print']['version'], 'oc_print_queue' ), 'grouped transaction failure identifies its affected table' );
expect( ! isset( $checks['readiness_oc_print_queue'] ), 'grouped transaction failure replaces individual table rows' );
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
	'storage_operator_verification_required' => 'storage_operator_verification_required',
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
$current_messages = [
	'storage_automatic_fallback' => [ 'Default storage candidate skipped; automatic fallback is operational. Persistent root denial is unchanged.' ],
	'storage_http_protection_unverified' => [ 'Automatic storage is operational; direct HTTP protection has not been verified.' ],
	'storage_evidence_revoked' => [
		'Private root overlaps the known document root; prior CLI evidence revoked.',
		'Private-root evidence revoked by a known document-root contradiction. Correct routing and change the trusted deployment revision before revalidation.',
	],
	'storage_evidence_missing' => [ 'No live HTTP-validated private-root evidence. Visit the site over HTTP or configure a verified operator root.' ],
	'storage_http_verification_blocked' => [
		'Automatic HTTP verification disabled; exact-root operator verification required.',
		'Automatic verification requires a reachable HTTPS uploads URL; configure exact-root operator verification.',
		'Advisory HTTP check failed or was inconclusive. Recursive public storage requires explicit operator verification of all directory/content routing.',
	],
	'storage_http_verification_deferred' => [
		'HTTP verification deferred by the per-request probe budget.',
		'Cannot lock HTTP storage verification.',
		'HTTP storage verification is already running.',
	],
	'storage_operator_verification_required' => [ 'Advisory canaries were denied, but child routing and content rejection remain unproven. Recursive public storage still requires explicit operator attestation.' ],
	'relocation_storage_blocked' => [
		'Relocation blocked: verified current private storage is unavailable; source retained.',
		'Preview relocation/read blocked: current verified storage unavailable; metadata and source retained.',
		'VDP relocation blocked: verified destination unavailable; source and row retained.',
	],
	'relocation_source_review' => [
		'Relocation source is empty, unreadable or exceeds the bounded copy limit.',
		'Relocation source hash did not match; source retained.',
		'Preview unavailable or changed during relocation; existing metadata and source retained.',
		'VDP row is missing or unreadable; no relocation performed.',
		'VDP source is missing or outside exact known prior private VDP roots; row retained for review.',
		'Private VDP source exceeds its 5 MiB relocation limit or is unreadable; retained for review.',
	],
	'relocation_publication_blocked' => [
		'Relocation copy verification failed; source retained.',
		'Atomic no-overwrite relocation publication failed; source retained.',
		'VDP pointer publication raced or failed; source and copied destination retained for reference-safe reconciliation.',
		'Private VDP migration could not read its batch; files and rows retained.',
	],
	'relocation_source_retained' => [
		'Private copy published; old source retained pending reference-safe cleanup and any required HTTP denial/purge.',
		'VDP path relocated without changing template fields/design; source retained pending reference-safe cleanup.',
	],
];
// Check shipped emitters too, so changed/new helper text cannot silently escape fixture coverage.
$emitted = [];
foreach ( [ 'class-oc-storage-upgrade.php', 'class-oc-rest-api.php' ] as $file ) {
	$source = file_get_contents( ABSPATH . 'includes/' . $file );
	preg_match_all( "/(?:self::report|OC_Storage_Upgrade::report)\( [^,\n]+, '([^']+)' \)|\\$(?:reason|message) = '([^']+)'/", $source, $matches, PREG_SET_ORDER );
	foreach ( $matches as $match ) {
		$message = $match[1] ?: ( $match[2] ?? '' );
		// Only helper assignments are storage reports; REST has other local messages.
		if ( '' !== $match[1] || 'class-oc-storage-upgrade.php' === $file ) { $emitted[] = $message; }
	}
}
$fixtures = array_merge( ...array_values( $current_messages ) );
expect( ! array_diff( $emitted, $fixtures ), 'fixtures cover every current literal storage report and helper message assignment, including retained diagnostic mappings' );
foreach ( $current_messages as $code => $messages ) {
	foreach ( $messages as $message ) {
		OC_Storage_Upgrade::$messages = [ '/secret/private/root' => $message ];
		$report = OC_System_Status::readiness_report( true );
		expect( [ $code => $code ] === $report['storage_upgrade'], 'current exact message maps to expected warning: ' . $message );
		expect( ! $report['print_retry_pause'], 'helper warning alone never imposes print pause' );
		OC_Storage_Upgrade::$messages[] = $message . ' /secret/private/suffix';
		$report = OC_System_Status::readiness_report( true );
		expect( isset( $report['storage_upgrade']['storage_diagnostic_unknown'] ) && ! str_contains( json_encode( $report ), '/secret/' ), 'modified known messages stay scrubbed' );
	}
}
OC_Upload_Handler::$blocked = [ 'artwork', 'previews', 'vdp', 'print-files' ];
foreach ( [ 'storage_http_protection_unverified', 'storage_http_verification_blocked', 'storage_operator_verification_required', 'storage_evidence_revoked' ] as $code ) {
	OC_Storage_Upgrade::$messages = $current_messages[ $code ];
	$report = OC_System_Status::readiness_report( true );
	expect( array_fill_keys( OC_Upload_Handler::$blocked, 'storage_blocked' ) === $report['storage'] && $report['print_retry_pause'], 'all blocked resources remain blocked despite advisory/revocation mapping' );
	expect( [ $code => $code ] === $report['storage_upgrade'], 'current messages deduplicate without unknown diagnostics' );
	ob_start();
	OC_System_Status::readiness_notice();
	$notice = ob_get_clean();
	if ( 'storage_http_protection_unverified' === $code ) {
		expect( ! str_contains( $notice, 'Direct file access protection' ) && str_contains( $notice, 'Storage is unavailable.' ) && str_contains( $notice, 'missing HTTP evidence alone is not a blocker' ), 'actual filesystem failure is shown without the persistent HTTP advisory' );
	} else {
		expect( str_contains( $notice, $code ) && str_contains( $notice, 'Storage is unavailable.' ) && str_contains( $notice, 'missing HTTP evidence alone is not a blocker' ), 'actual filesystem failure blocks, not missing HTTP proof' );
	}
	if ( 'storage_evidence_revoked' === $code ) {
		expect( str_contains( $notice, 'affected root' ) && str_contains( $notice, 'Automatic selection can use the fallback' ), 'contradiction is root-specific with automatic fallback' );
	}
}
OC_Storage_Upgrade::$messages = [];
$report = OC_System_Status::readiness_report( true );
expect( [] === $report['storage_upgrade'] && $report['print_retry_pause'], 'silent validation failure remains blocked without inventing a helper cause' );
OC_Upload_Handler::$blocked = [];
$wpdb->engines = [];
OC_Storage_Upgrade::$messages = $current_messages['storage_http_protection_unverified'];
$report = OC_System_Status::readiness_report( true );
expect( ! in_array( false, $report['resources'], true ) && array_fill_keys( [ 'artwork', 'previews', 'vdp', 'print-files' ], 'ready' ) === $report['storage'], 'all actual resource checks remain ready with unverified HTTP protection' );
expect( ! $report['print_retry_pause'] && [ 'storage_http_protection_unverified' => 'storage_http_protection_unverified' ] === $report['storage_upgrade'], 'operational automatic storage warns without pausing print' );
$checks = array_column( OC_System_Status::checks(), null, 'key' );
expect( ! $checks['readiness_storage_http_protection_unverified']['required'] && ! $checks['readiness_storage_http_protection_unverified']['available'], 'HTTP warning is visible and nonblocking in System Status' );
expect( 'Direct file access protection' === $checks['readiness_storage_http_protection_unverified']['label'], 'HTTP advisory has a human-readable title' );
expect( 'Advisory' === $checks['readiness_storage_http_protection_unverified']['requirement_label'], 'HTTP advisory is not a recommended dependency' );
expect( 'Not verified' === $checks['readiness_storage_http_protection_unverified']['result_label'], 'HTTP advisory is not labelled Missing or Available' );
expect( '' === $checks['readiness_storage_http_protection_unverified']['version'], 'HTTP advisory does not repeat its internal diagnostic code' );
expect( ! isset( $checks['php']['result_label'] ) && ! isset( $checks['php']['requirement_label'] ), 'Real dependency checks retain their normal display labels' );
expect( isset( $checks['readiness_transactions_print'], $checks['readiness_transactions_designs'], $checks['readiness_storage_print-files'] ), 'readiness checks are grouped by resource' );
expect( ! isset( $checks['readiness_oc_print_files'], $checks['readiness_oc_designs'] ), 'individual transaction tables do not create repetitive System Status rows' );
expect( 13 === count( array_filter( array_keys( $checks ), static fn ( string $key ): bool => str_starts_with( $key, 'readiness_' ) ) ), 'healthy readiness UI has twelve grouped checks plus the current warning' );
ob_start();
OC_System_Status::readiness_notice();
$notice = ob_get_clean();
expect( '' === $notice, 'nonblocking HTTP advisory does not create a persistent admin notice' );
OC_Storage_Upgrade::$messages = array_merge( $current_messages['storage_automatic_fallback'], $current_messages['storage_http_protection_unverified'] );
$report = OC_System_Status::readiness_report( true );
expect( isset( $report['storage_upgrade']['storage_automatic_fallback'], $report['storage_upgrade']['storage_http_protection_unverified'] ) && ! isset( $report['storage_upgrade']['storage_evidence_revoked'] ) && ! $report['print_retry_pause'], 'handled fallback coexists with honest HTTP advisory without revoked warning or print pause' );
$checks = array_column( OC_System_Status::checks(), null, 'key' );
$fallback_check = $checks['readiness_storage_automatic_fallback'];
expect( 'Automatic storage fallback' === $fallback_check['label'] && 'Default candidate skipped' === $fallback_check['version'] && 'Operational' === $fallback_check['result_label'] && 'Advisory' === $fallback_check['requirement_label'] && ! $fallback_check['required'], 'handled fallback has human-readable label, version and operational advisory result, not Review' );
expect( 'Not verified' === $checks['readiness_storage_http_protection_unverified']['result_label'], 'fallback does not imply HTTP verification' );
$guidance = ( new ReflectionMethod( OC_System_Status::class, 'readiness_guidance' ) )->invoke( null, 'storage_automatic_fallback' );
expect( str_contains( $guidance, 'Persistent denial' ) && str_contains( $guidance, 'fallback is operational' ) && str_contains( $guidance, 'Direct HTTP protection has not been verified' ), 'fallback guidance preserves denial and HTTP caveat' );
ob_start(); OC_System_Status::readiness_notice(); $notice = ob_get_clean();
expect( '' === $notice, 'handled fallback and HTTP advisory do not create an admin notice' );
OC_Storage_Upgrade::$messages = array_merge( OC_Storage_Upgrade::$messages, $current_messages['storage_evidence_revoked'] );
OC_System_Status::readiness_report( true );
$checks = array_column( OC_System_Status::checks(), null, 'key' );
expect( 'Review' === $checks['readiness_storage_evidence_revoked']['result_label'], 'actionable retained-root revocation still needs review alongside handled fallback' );
ob_start(); OC_System_Status::readiness_notice(); $notice = ob_get_clean();
expect( str_contains( $notice, 'storage_evidence_revoked' ) && ! str_contains( $notice, 'Automatic storage fallback' ), 'handled fallback never suppresses actionable admin warnings' );
OC_Upload_Handler::$blocked = [ 'print-files' ];
OC_Storage_Upgrade::$messages = $current_messages['storage_automatic_fallback'];
expect( OC_System_Status::readiness_report( true )['print_retry_pause'], 'handled fallback cannot override a later resource failure' );
ob_start(); OC_System_Status::readiness_notice(); $notice = ob_get_clean();
expect( str_contains( $notice, 'Storage is unavailable.' ), 'resource failure notice remains visible alongside handled fallback' );
OC_Upload_Handler::$blocked = [];
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
// Dismissal is presentation-only, scoped to user/site, and bound to the shown report.
OC_Storage_Upgrade::$messages = $current_messages['storage_evidence_revoked'];
$report                       = OC_System_Status::readiness_report( true );
ob_start();
OC_System_Status::readiness_notice();
$notice = ob_get_clean();
expect( str_contains( $notice, 'Dismiss for 24 hours' ), 'persistent dismissal control rendered' );
preg_match( '/name="fingerprint" value="([a-f0-9]{64})"/', $notice, $match );
$_POST['fingerprint'] = $match[1];
$before               = [ $options, $transients, $wpdb->calls, OC_Upload_Handler::$calls ];
foreach ( [ [ false, 'POST', true, '403' ], [ true, 'GET', true, '405' ], [ true, 'POST', false, 'nonce' ] ] as [ $can_manage, $method, $valid_nonce, $expected ] ) {
	$_SERVER['REQUEST_METHOD'] = $method;
	try {
		OC_System_Status::dismiss_readiness();
		throw new RuntimeException( 'accepted invalid dismissal' );
	} catch ( RuntimeException $error ) {
		expect( $expected === $error->getMessage(), 'dismissal authorization' );
	}
	expect( [] === $user_options, 'rejected dismissal writes no preference' );
}
$can_manage                = true;
$valid_nonce               = true;
$_SERVER['REQUEST_METHOD'] = 'POST';
foreach ( [ [], 'bad', str_repeat( 'a', 65 ) ] as $invalid ) {
	$_POST['fingerprint'] = $invalid;
	try {
		OC_System_Status::dismiss_readiness();
		throw new RuntimeException( 'accepted malformed fingerprint' );
	} catch ( RuntimeException $error ) {
		expect( '400' === $error->getMessage(), 'malformed dismissal rejected' );
	}
}
$_POST['fingerprint'] = $match[1];
try {
	OC_System_Status::dismiss_readiness();
} catch ( RuntimeException $error ) {
	expect( 'redirect' === $error->getMessage(), 'dismissal redirects to full status' );
}
expect( [ $options, $transients, $wpdb->calls, OC_Upload_Handler::$calls ] === $before, 'dismissal does not change operational state or perform probes' );
ob_start();
OC_System_Status::readiness_notice();
expect( '' === ob_get_clean(), 'acknowledged notice hidden' );
$checks = array_column( OC_System_Status::checks(), null, 'key' );
expect( isset( $checks['readiness_storage_evidence_revoked'] ), 'dismissed diagnostic remains in System Status' );
$transients[ $cache_key ]['checked_at'] = time() - 10;
$transients[ $cache_key ]['tables']     = array_reverse( $report['tables'], true );
ob_start();
OC_System_Status::readiness_notice();
expect( '' === ob_get_clean(), 'timestamp and ordering do not undo acknowledgement' );
foreach ( [ [ 2, 1 ], [ 1, 2 ] ] as [ $user_id, $site_id ] ) {
	ob_start();
	OC_System_Status::readiness_notice();
	expect( str_contains( ob_get_clean(), 'Dismiss for 24 hours' ), 'other user/site still sees warning' );
}
$user_id                    = 1;
$site_id                    = 1;
OC_Upload_Handler::$blocked = [ 'print-files' ];
expect( OC_System_Status::readiness_report( true )['print_retry_pause'], 'active failure still pauses print after dismissal' );
ob_start();
OC_System_Status::readiness_notice();
expect( str_contains( ob_get_clean(), 'Storage is unavailable.' ), 'new failure is not hidden by old dismissal' );
OC_Upload_Handler::$blocked = [];
OC_System_Status::readiness_report( true );
$user_options[1][1]['oc_readiness_notice_dismissal']['expires'] = time();
ob_start();
OC_System_Status::readiness_notice();
expect( str_contains( ob_get_clean(), 'Dismiss for 24 hours' ), 'expired dismissal shows notice again' );
$can_manage = false;
$calls = $wpdb->calls;
ob_start();
OC_System_Status::readiness_notice();
expect( '' === ob_get_clean() && $calls === $wpdb->calls, 'guest notice neither probes nor exposes details' );
print "Compatibility readiness regressions passed.\n";
