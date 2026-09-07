<?php
/** Dependency-free checks using real storage consumers, stubbed HTTP and plugin-local fixtures. */
$fixture = __DIR__ . '/.storage-upgrade-' . bin2hex( random_bytes( 6 ) );
define( 'ABSPATH', $fixture . '/site/' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );
$options = $meta = $metadata = $transients = $requests = [];
$site = 1;
$salt = 'storage-upgrade-test';
$configured = $fixture . '/private';
$upload_base = $fixture . '/uploads';
$upload_url = 'https://shop.example/uploads';
$http_mode = 'denied';
$automatic = true;
$deployment = '';
$session = null;
class WP_Error {}
class OC_Logger { public static function warning( $message ): void {} public static function error( $message ): void {} }
class OC_SVG_Sanitiser { public static function sanitise( $bytes ) { return $bytes; } public static function sanitise_file( $path ) { return true; } }
function wp_normalize_path( $path ) { return str_replace( '\\', '/', $path ); }
function wp_upload_dir() { return [ 'basedir' => $GLOBALS['upload_base'], 'baseurl' => $GLOBALS['upload_url'] ]; }
function wp_mkdir_p( $path ) { return is_dir( $path ) || mkdir( $path, 0750, true ); }
function trailingslashit( $path ) { return rtrim( $path, '/' ) . '/'; }
function get_current_blog_id() { return $GLOBALS['site']; }
function home_url( $path = '' ) { return 'https://shop.example' . $path; }
function site_url( $path = '' ) { return home_url( $path ); }
function admin_url( $path = '' ) { return home_url( '/wp-admin/' . $path ); }
function add_query_arg( $args, $url ) { return $url . '?' . http_build_query( $args ); }
function esc_url_raw( $url ) { return $url; }
function wp_parse_url( $url ) { return parse_url( $url ); }
function wp_parse_str( $query, &$result ) { parse_str( $query, $result ); }
function wp_salt( $scheme ) { return $GLOBALS['salt']; }
function get_option( $key, $default = false ) { return $GLOBALS['options'][ $key ] ?? $default; }
function update_option( $key, $value, ...$args ) { $GLOBALS['options'][ $key ] = $value; return true; }
function add_option( $key, $value, ...$args ) { if ( isset( $GLOBALS['options'][ $key ] ) ) { return false; } return update_option( $key, $value ); }
function get_transient( $key ) { return $GLOBALS['transients'][ $key ] ?? false; }
function get_post_meta( $id, $key, $single = true ) { return $GLOBALS['meta'][ $id ][ $key ] ?? ''; }
function update_post_meta( $id, $key, $value, $previous = null ) {
	$old = get_post_meta( $id, $key );
	if ( '_wp_attached_file' === $key && ! empty( $GLOBALS['fail_publication'] ) ) { return false; }
	if ( ( array_key_exists( $key, $GLOBALS['meta'][ $id ] ?? [] ) && $old === $value ) || ( null !== $previous && $previous !== $old ) ) { return false; }
	$GLOBALS['meta'][ $id ][ $key ] = $value; return true;
}
function get_attached_file( $id ) { $file = get_post_meta( $id, '_wp_attached_file' ); return str_starts_with( $file, '/' ) ? $file : $GLOBALS['upload_base'] . '/' . $file; }
function get_post_mime_type( $id ) { return 'image/svg+xml'; }
function wp_get_attachment_metadata( $id ) { return $GLOBALS['metadata'][ $id ] ?? []; }
function wp_update_post( $post, $error = false ) { return $post['ID']; }
function get_current_user_id() { return 0; }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_file_name( $value ) { return $value; }
function wp_generate_password( $length, ...$args ) { return substr( bin2hex( random_bytes( $length ) ), 0, $length ); }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function WC() { return (object) [ 'session' => $GLOBALS['session'] ]; }
function __( $value, ...$args ) { return $value; }
function esc_html__( $value, ...$args ) { return $value; }
function apply_filters( $hook, $value, ...$args ) {
	return match ( $hook ) {
		'oc_private_storage_root' => $GLOBALS['configured'] ?? $value,
		'oc_storage_automatic_http_verification' => $GLOBALS['automatic'],
		'oc_storage_verification_context' => $GLOBALS['deployment'],
		default => $value,
	};
}
function wp_safe_remote_get( $url, $args ) {
	$GLOBALS['requests'][] = [ $url, $args ];
	check( $args['redirection'] === 0 && $args['timeout'] === 2 && $args['limit_response_size'] === 1024 && $args['cookies'] === [], 'HTTP bounds and unauthenticated probes' );
	if ( 'error' === $GLOBALS['http_mode'] ) { return new WP_Error(); }
	if ( 'redirect' === $GLOBALS['http_mode'] ) { return [ 'status' => 302, 'body' => '' ]; }
	$path = $GLOBALS['upload_base'] . substr( rawurldecode( $url ), strlen( $GLOBALS['upload_url'] ) );
	$content = file_get_contents( $path );
	$control = str_contains( basename( $path ), 'oc-control-' );
	$exposed = 'exposed' === $GLOBALS['http_mode'] || ( 'partial' === $GLOBALS['http_mode'] && str_ends_with( $path, '.jpg' ) );
	$exposed = $exposed || ( 'parent-only' === $GLOBALS['http_mode'] && str_contains( $path, '/order/' ) )
		|| ( 'content-rejection' === $GLOBALS['http_mode'] && str_starts_with( $content, '%PDF-' ) );
	return [ 'status' => $control || $exposed ? 200 : 403, 'body' => $control || $exposed ? $content : 'Forbidden' ];
}
function wp_remote_retrieve_response_code( $response ) { return $response['status']; }
function wp_remote_retrieve_body( $response ) { return $response['body']; }
function check( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } $GLOBALS['checks'] = ( $GLOBALS['checks'] ?? 0 ) + 1; }
function invoke( $class, $method, ...$args ) { return ( new ReflectionMethod( $class, $method ) )->invoke( null, ...$args ); }
function reset_probe() { ( new ReflectionProperty( OC_Storage_Upgrade::class, 'probed' ) )->setValue( null, false ); }
require dirname( __DIR__ ) . '/includes/class-oc-upload-handler.php';
require dirname( __DIR__ ) . '/includes/class-oc-rest-api.php';
require dirname( __DIR__ ) . '/includes/print/class-oc-print-base.php';
require dirname( __DIR__ ) . '/includes/class-oc-print-generator.php';

try {
	wp_mkdir_p( ABSPATH ); wp_mkdir_p( $upload_base );
	$_SERVER['DOCUMENT_ROOT'] = ABSPATH;
	$_SERVER['REQUEST_METHOD'] = 'GET';
	$root = OC_Upload_Handler::private_storage_root();
	check( $root === $configured, 'Filtered HTTP root accepted' );
	check( [] === array_filter( array_keys( $options ), static fn ( $key ) => str_starts_with( $key, 'oc_storage_evidence_' ) ), 'CLI cannot mint HTTP evidence from spoofed server variables' );
	unset( $_SERVER['DOCUMENT_ROOT'], $_SERVER['REQUEST_METHOD'] );
	check( $root === OC_Upload_Handler::private_storage_root(), 'CLI works without any positive evidence' );
	check( 'Automatic storage is operational; direct HTTP protection has not been verified.' === OC_Storage_Upgrade::reports()[ $root ], 'Unknown exposure emits the exact non-blocking warning' );
	// Simulate a prior real HTTP validation without making a network request or changing PHP SAPI.
	$context = invoke( OC_Storage_Upgrade::class, 'context', $root, 'private:' . $configured );
	invoke( OC_Storage_Upgrade::class, 'remember', $context, true, rtrim( ABSPATH, '/' ), 21600 );
	$alias_context = invoke( OC_Storage_Upgrade::class, 'context', $root, 'private:' . $configured . '/' );
	invoke( OC_Storage_Upgrade::class, 'remember', $alias_context, true, rtrim( ABSPATH, '/' ), 21600 );
	unset( $_SERVER['DOCUMENT_ROOT'], $_SERVER['REQUEST_METHOD'] );
	check( $root === OC_Upload_Handler::private_storage_root(), 'Positive evidence is optional for CLI' );
	$valid_options = $options;
	$site = 2;
	check( $root === OC_Upload_Handler::private_storage_root(), 'Another site validates its configured filesystem without borrowing evidence' );
	$site = 1;
	$salt = 'rotated';
	check( $root === OC_Upload_Handler::private_storage_root(), 'Salt rotation does not disable filesystem storage' );
	$salt = 'storage-upgrade-test';
	foreach ( $options as &$record ) { if ( is_array( $record ) && isset( $record['mac'] ) ) { $record['data']['until'] += 86400; } } unset( $record );
	check( null === invoke( OC_Storage_Upgrade::class, 'evidence', $context ) && $root === OC_Upload_Handler::private_storage_root(), 'Tampered evidence is ignored, not required for storage' );
	$options = $valid_options;
	foreach ( $options as &$record ) { if ( is_array( $record ) && isset( $record['mac'] ) ) {
		$record['data']['at'] = time() - 30000; $record['data']['until'] = time() - 1;
		$record['mac'] = hash_hmac( 'sha256', serialize( $record['data'] ), wp_salt( 'auth' ) );
	} } unset( $record );
	check( null === invoke( OC_Storage_Upgrade::class, 'evidence', $context ) && $root === OC_Upload_Handler::private_storage_root(), 'Expired evidence cannot disable healthy storage' );
	$options = $valid_options;
	$upload_url = 'https://changed.example/uploads';
	check( $root === OC_Upload_Handler::private_storage_root(), 'Changed uploads URL does not disable filesystem storage' );
	$upload_url = 'https://shop.example/uploads';
	$configured = $fixture . '/other-private';
	check( $configured === OC_Upload_Handler::private_storage_root() && is_dir( $configured ), 'CLI creates a valid filtered root without external approval' );
	$configured = $root;
	$_SERVER['DOCUMENT_ROOT'] = $fixture;
	check( null === OC_Upload_Handler::private_storage_root(), 'Live overlapping document root overrides stored evidence' );
	unset( $_SERVER['DOCUMENT_ROOT'], $_SERVER['REQUEST_METHOD'] );
	check( null === OC_Upload_Handler::private_storage_root(), 'Rejected HTTP document root persistently revokes subsequent CLI reuse' );
	$salt = 'rotated';
	check( null === OC_Upload_Handler::private_storage_root(), 'Salt rotation does not erase a known contradiction' );
	$salt = 'storage-upgrade-test';
	$configured = $fixture . '/other-private';
	check( $configured === OC_Upload_Handler::private_storage_root(), 'Contradiction is root-specific, not a blanket CLI block' );
	$configured = $root;
	// Replaying a previously valid evidence record cannot override the separate root denial.
	foreach ( $valid_options as $name => $value ) { $options[ $name ] = $value; }
	check( null === OC_Upload_Handler::private_storage_root(), 'Old signed evidence replay cannot override root revocation' );
	$configured .= '/';
	check( null === OC_Upload_Handler::private_storage_root(), 'Changing to another previously approved root configuration cannot bypass revocation' );
	$configured = $root;
	$_SERVER['DOCUMENT_ROOT'] = ABSPATH; $_SERVER['REQUEST_METHOD'] = 'GET';
	check( null === OC_Upload_Handler::private_storage_root(), 'A narrower subsequent document root does not erase contradictory routing evidence' );
	$deployment = 'routing-corrected';
	check( $root === OC_Upload_Handler::private_storage_root(), 'Trusted deployment revision permits fresh root validation' );
	unset( $_SERVER['DOCUMENT_ROOT'], $_SERVER['REQUEST_METHOD'] );
	check( $root === OC_Upload_Handler::private_storage_root(), 'Revalidated deployment does not require positive evidence for CLI' );
	$deployment = ''; $options = $valid_options;
	check( ! OC_Storage_Upgrade::private_root_verified( $root, $configured, '/' ), 'Filesystem-root document-root contradiction rejected without accessing outside fixtures' );
	check( null === OC_Upload_Handler::private_storage_root(), 'Filesystem-root contradiction also revokes CLI evidence' );
	$options = $valid_options;
	$_SERVER['DOCUMENT_ROOT'] = ABSPATH;
	$_SERVER['REQUEST_METHOD'] = 'GET';

	$legacy = $upload_base . '/overcustomise/print-files';
	wp_mkdir_p( $legacy ); file_put_contents( $legacy . '/old.pdf', 'retained' );
	check( $legacy . '/old.pdf' === OC_Print_Base::resolve_output_storage_path( $legacy . '/old.pdf', true ), 'Known legacy print root automatically receives deny files' );
	check( [] === $requests, 'Runtime storage never probes HTTP' );
	check( ! OC_Storage_Upgrade::public_subtree_verified( $legacy ), 'Optional denied canaries do not prove recursive protection' );
	check( count( $requests ) === 16, 'Optional diagnostic checks positive control and supported suffixes' );
	check( [] === glob( $upload_base . '/oc-control-*' ) && [] === glob( $legacy . '/oc-denied-*' ), 'Canaries removed' );
	check( $legacy . '/old.pdf' === OC_Print_Base::resolve_output_storage_path( $legacy . '/old.pdf', true ) && count( $requests ) === 16, 'Runtime operation remains independent of advisory results' );
	$old_context = invoke( OC_Storage_Upgrade::class, 'context', $legacy, 'public:' . $upload_url . '/overcustomise/print-files' );
	invoke( OC_Storage_Upgrade::class, 'remember', $old_context, true, 'Old blanket approval', 21600 );
	check( ! OC_Storage_Upgrade::public_subtree_verified( $legacy ), 'Previously signed blanket probe approval is not proof' );
	$automatic = false;
	check( $legacy . '/old.pdf' === OC_Print_Base::resolve_output_storage_path( $legacy . '/old.pdf', true ), 'Disabling optional probes does not disable storage' );
	$automatic = true;
	wp_mkdir_p( $legacy . '/order' );
	file_put_contents( $legacy . '/order/actual.pdf', "%PDF-1.4\nFixture production bytes\n" );
	foreach ( [ 'parent-only', 'content-rejection' ] as $mode ) {
		$options = $valid_options; reset_probe(); $http_mode = $mode; $requests = [];
		check( ! OC_Storage_Upgrade::public_subtree_verified( $legacy ) && count( $requests ) === 16, 'Denied parent/content-rejected canaries remain advisory: ' . $mode );
		$response = wp_safe_remote_get( $upload_url . '/overcustomise/print-files/order/actual.pdf', [ 'redirection' => 0, 'timeout' => 2, 'limit_response_size' => 1024, 'cookies' => [] ] );
		check( 200 === $response['status'], 'Fixture confirms production child is exposed despite all denied canaries: ' . $mode );
		$count = count( $requests );
		check( $legacy . '/order/actual.pdf' === OC_Print_Base::resolve_output_storage_path( $legacy . '/order/actual.pdf', true ) && $count === count( $requests ), 'Automatic local policy is independent of misleading probes: ' . $mode );
	}
	foreach ( [ 'exposed', 'partial', 'error', 'redirect' ] as $mode ) {
		$options = $valid_options; reset_probe(); $http_mode = $mode; $requests = [];
		check( ! OC_Storage_Upgrade::public_subtree_verified( $legacy ), 'Optional HTTP diagnostic cannot verify denial: ' . $mode );
		$count = count( $requests ); reset_probe();
		check( ! OC_Storage_Upgrade::public_subtree_verified( $legacy ) && count( $requests ) === $count, 'Optional signed failure backoff avoids repeated I/O' );
		check( $legacy . '/old.pdf' === OC_Print_Base::resolve_output_storage_path( $legacy . '/old.pdf', true ) && count( $requests ) === $count, 'Failed diagnostic does not become a runtime storage gate' );
	}
	$http_mode = 'denied'; $options = $valid_options; reset_probe();
	$options['oc_private_storage_token'] = str_repeat( 'a', 32 );
	$fallback = $upload_base . '/.overcustomise-private-' . str_repeat( 'a', 32 );
	wp_mkdir_p( $fallback );
	$automatic = false;
	$requests = [];
	check( $fallback === invoke( OC_Upload_Handler::class, 'protected_uploads_storage_root' ), 'Fallback operates without approvals or probes' );
	wp_mkdir_p( $fallback . '/print-files' ); file_put_contents( $fallback . '/print-files/new.pdf', 'new' );
	check( ! OC_Storage_Upgrade::public_subtree_verified( $fallback . '/print-files' ), 'Automatic fallback does not claim HTTP verification' );
	check( $fallback . '/print-files/new.pdf' === OC_Print_Base::resolve_output_storage_path( $fallback . '/print-files/new.pdf' ), 'Known old fallback print root is accepted without a history filter' );
	$salt = 'rotated';
	check( $fallback === invoke( OC_Upload_Handler::class, 'protected_uploads_storage_root' ), 'Fallback token and root survive salt rotation' );
	$salt = 'storage-upgrade-test';
	$configured = null; $_SERVER['DOCUMENT_ROOT'] = $fixture;
	$output = invoke( OC_Print_Base::class, 'ensure_output_dir', 42 ) . '/generated.pdf';
	file_put_contents( $output, 'generated PDF fixture' );
	$final = OC_Print_Generator::finalise_generated_output( $output, 12 );
	check( is_file( $final ) && $final === OC_Print_Base::resolve_output_storage_path( $final, true ), 'Fallback print creation, finalization and download share one parent policy' );
	unset( $_SERVER['DOCUMENT_ROOT'] );
	check( $fallback === OC_Upload_Handler::private_storage_root(), 'Known default contradiction selects fallback in CLI without configuration' );
	check( [] === $requests, 'Fallback generation and CLI resolution perform no HTTP probes' );
	$configured = $root; $_SERVER['DOCUMENT_ROOT'] = ABSPATH;
	wp_mkdir_p( $fallback . '-sibling' );
	check( ! OC_Storage_Upgrade::public_subtree_verified( $fallback . '-sibling' ), 'Parent policy cannot approve sibling' );
	$alias = $fixture . '/uploads-alias'; symlink( $upload_base, $alias );
	$upload_base = $alias;
	check( $legacy . '/old.pdf' === OC_Print_Base::resolve_output_storage_path( $alias . '/overcustomise/print-files/old.pdf' ), 'Configured uploads symlink alias accepted' );
	check( [ $legacy . '/old.pdf', $alias . '/overcustomise/print-files/old.pdf' ] === OC_Storage_Upgrade::file_reference_paths( $legacy . '/old.pdf' ), 'Reference queries can check canonical and configured-alias spellings' );
	symlink( $legacy . '/old.pdf', $legacy . '/file-alias.pdf' );
	check( null === OC_Print_Base::resolve_output_storage_path( $alias . '/overcustomise/print-files/file-alias.pdf' ), 'Per-file symlink rejected' );
	check( null === OC_Print_Base::resolve_output_storage_path( $legacy . '/../print-files/old.pdf' ), 'Traversal alias rejected' );
	symlink( $legacy, $legacy . '/directory-alias' );
	check( null === OC_Print_Base::resolve_output_storage_path( $legacy . '/directory-alias/old.pdf' ), 'Per-directory symlink rejected' );
	$upload_base = $fixture . '/uploads';

	$destination = OC_Upload_Handler::private_storage_path( 'artwork' );
	$old_default = dirname( rtrim( ABSPATH, '/' ) ) . '/.overcustomise-private-' . substr( hash( 'sha256', wp_normalize_path( ABSPATH ) ), 0, 12 );
	// The old default was contradicted above. Keep it migration-only, not a serving bypass.
	wp_mkdir_p( $old_default . '/print-files' ); file_put_contents( $old_default . '/print-files/old.pdf', 'old' );
	check( null === OC_Print_Base::resolve_output_storage_path( $old_default . '/print-files/old.pdf' ), 'Known old default cannot bypass its persisted contradiction' );
	foreach ( [ $old_default, $fallback ] as $index => $old ) {
		wp_mkdir_p( $old . '/artwork' );
		$source = $old . '/artwork/art.svg';
		file_put_contents( $source, '<svg xmlns="http://www.w3.org/2000/svg"><rect width="1" height="1"/></svg>' );
		file_put_contents( $old . '/artwork/small.svg', file_get_contents( $source ) );
		$id = 10 + $index;
		$meta[ $id ] = [ '_oc_artwork' => 1, '_wp_attached_file' => $source, '_oc_private_storage_version' => 2, '_oc_artwork_token' => 'unchanged', '_oc_artwork_context' => [ 1, 0, 2, 3 ], '_oc_artwork_owner_secret' => 'keep' ];
		$metadata[ $id ] = [ 'file' => 'art.svg', 'width' => 1, 'sizes' => [ 'small' => [ 'file' => 'small.svg' ] ] ];
		$before = $meta[ $id ]; $before_metadata = $metadata[ $id ];
		check( invoke( OC_Upload_Handler::class, 'migrate_legacy_attachment', $id, $destination ), 'Already-versioned old private artwork relocated' );
		check( str_starts_with( get_attached_file( $id ), $destination . '/' ) && is_file( dirname( get_attached_file( $id ) ) . '/small.svg' ), 'Primary and derivative published together' );
		check( $metadata[ $id ] === $before_metadata && get_post_meta( $id, '_oc_artwork_context' ) === $before['_oc_artwork_context'] && get_post_meta( $id, '_oc_artwork_token' ) === 'unchanged' && get_post_meta( $id, '_oc_artwork_owner_secret' ) === 'keep', 'Metadata and ownership preserved' );
		check( is_file( $source ), 'Source retained until shared references can be safely cleaned' );
		check( invoke( OC_Upload_Handler::class, 'migrate_legacy_attachment', $id, $destination ), 'Relocation retry is idempotent' );
	}
	check( null === invoke( OC_Upload_Handler::class, 'known_old_private_artwork', $legacy . '/old.pdf' ), 'Print rows cannot introduce artwork migration roots' );
	$source = $old_default . '/artwork/art.svg';
	$meta[20] = [ '_oc_artwork' => 1, '_wp_attached_file' => $source, '_oc_private_storage_version' => 2 ];
	$before_directories = glob( $destination . '/relocated-*' );
	$fail_publication = true;
	check( ! invoke( OC_Upload_Handler::class, 'migrate_legacy_attachment', 20, $destination ), 'Failed pointer CAS does not publish relocation' );
	check( get_attached_file( 20 ) === $source && is_file( $source ) && $before_directories === glob( $destination . '/relocated-*' ), 'CAS failure retains source and removes only staged copies' );
	$fail_publication = false;
	$meta[21] = [ '_oc_artwork' => 1, '_wp_attached_file' => $source, '_oc_private_storage_version' => 2 ];
	$metadata[21] = [ 'sizes' => [ 'bad' => [ 'file' => '../outside.svg' ] ] ];
	check( ! invoke( OC_Upload_Handler::class, 'migrate_legacy_attachment', 21, $destination ), 'Unsafe derivative path blocks relocation' );
	check( in_array( $legacy, OC_Print_Base::output_migration_roots(), true ), 'Generator source inventory exposes exact legacy print root' );

	// Private previews retain their metadata and existing signatures across root relocation.
	$png = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=' );
	foreach ( [ $old_default, $fallback ] as $index => $old ) {
		wp_mkdir_p( $old . '/previews' );
		$data = $png . str_repeat( 'x', 100 + $index );
		$record = [ 'version' => 1, 'file' => 'preview-' . str_repeat( (string) ( $index + 1 ), 40 ) . '.png', 'mime' => 'image/png', 'bytes' => strlen( $data ), 'content_hash' => hash( 'sha256', $data ), 'secret' => str_repeat( 'e', 64 ), 'created_at' => time() - 3600 ];
		$id = substr( hash_hmac( 'sha256', $record['content_hash'], wp_salt( 'nonce' ) ), 0, 40 );
		$key = 'oc_private_preview_' . $id;
		$options[ $key ] = json_encode( $record ); $raw = $options[ $key ];
		file_put_contents( $old . '/previews/' . $record['file'], $data );
		$url = invoke( OC_Rest_API::class, 'private_preview_url', $id, $record );
		$previews = OC_Upload_Handler::private_storage_path( 'previews' );
		check( null === invoke( OC_Rest_API::class, 'private_preview_record', $id, str_repeat( '0', 64 ) ) && ! file_exists( $previews . '/' . $record['file'] ), 'Bad preview signature cannot initiate relocation' );
		check( $url === OC_Rest_API::validate_private_preview_url( $url ), 'Existing signed preview URL survives old-root relocation' );
		check( $options[ $key ] === $raw && file_get_contents( $previews . '/' . $record['file'] ) === $data, 'Preview bytes, metadata, secret and identity preserved' );
		check( is_file( $old . '/previews/' . $record['file'] ), 'Preview source retained for shared/order-reference-safe cleanup' );
		check( $url === OC_Rest_API::validate_private_preview_url( $url ), 'Preview relocation is idempotent' );
	}
	$record['file'] = 'preview-' . str_repeat( '3', 40 ) . '.png';
	$options[ $key ] = json_encode( $record );
	file_put_contents( $fallback . '/previews/' . $record['file'], str_repeat( 'z', $record['bytes'] ) );
	check( '' === OC_Rest_API::validate_private_preview_url( $url ) && ! file_exists( $previews . '/' . $record['file'] ), 'Corrupt/mismatching preview cannot relocate' );
	file_put_contents( $fallback . '/previews/' . $record['file'], $png . str_repeat( 'y', $record['bytes'] - strlen( $png ) ) );
	check( '' === OC_Rest_API::validate_private_preview_url( $url ) && ! file_exists( $previews . '/' . $record['file'] ), 'Valid MIME and size do not bypass preview hash verification' );
	file_put_contents( $fallback . '/previews/' . $record['file'], $data );
	file_put_contents( $previews . '/' . $record['file'], 'do not overwrite' );
	check( '' === OC_Rest_API::validate_private_preview_url( $url ) && file_get_contents( $previews . '/' . $record['file'] ) === 'do not overwrite', 'Conflicting current preview is retained, never overwritten' );
	$record['file'] = 'preview-' . str_repeat( '4', 40 ) . '.png';
	$options[ $key ] = json_encode( $record );
	symlink( $fallback . '/previews/preview-' . str_repeat( '2', 40 ) . '.png', $fallback . '/previews/' . $record['file'] );
	check( '' === OC_Rest_API::validate_private_preview_url( $url ), 'Per-file preview source symlink rejected' );
	$record['file'] = '../previews/preview-' . str_repeat( '2', 40 ) . '.png';
	$options[ $key ] = json_encode( $record );
	check( '' === OC_Rest_API::validate_private_preview_url( $url ), 'Preview metadata cannot introduce traversal' );

	// Real VDP migration logic with deterministic in-memory DB race/failure behavior.
	$wpdb = new class {
		public string $prefix = 'wp_';
		public string $last_error = '';
		public array $rows = [];
		public array $visited = [];
		public string $mode = '';
		public function prepare( $sql, ...$args ) { return [ $sql, $args ]; }
		public function esc_like( $value ) { return $value; }
		public function get_row( $query ) { $this->last_error = ''; $this->visited[] = $query[1][0]; return isset( $this->rows[ $query[1][0] ] ) ? clone $this->rows[ $query[1][0] ] : null; }
		public function get_results( $query ) {
			$this->last_error = '';
			return array_slice( array_values( array_filter( $this->rows, static fn ( $row ) => $row->id > $query[1][0] && ! str_starts_with( $row->csv_file_path, rtrim( $query[1][1], '%' ) ) ) ), 0, 25 );
		}
		public function query( $query ) {
			[ $destination, $id, $design, $original ] = $query[1];
			if ( 'race' === $this->mode ) { $this->rows[ $id ]->csv_file_path = 'concurrent-replacement.csv'; return 0; }
			if ( $this->rows[ $id ]->csv_file_path !== $original || $this->rows[ $id ]->design_id !== $design ) { return 0; }
			$this->rows[ $id ]->csv_file_path = $destination;
			if ( 'uncertain' === $this->mode ) { $this->last_error = 'Unknown commit outcome'; return false; }
			return 1;
		}
	};
	foreach ( [ $old_default, $fallback ] as $index => $old ) {
		wp_mkdir_p( $old . '/vdp' ); $csv = $old . '/vdp/original.csv'; file_put_contents( $csv, "name\nOriginal\n" );
		$id = $index + 1;
		$wpdb->rows[ $id ] = (object) [ 'id' => $id, 'design_id' => 100 + $id, 'csv_file_path' => $csv, 'active' => 1, 'fields' => [ 'name' => 42 ] ];
		$moved = OC_Rest_API::relocate_private_vdp_template( $id );
		check( is_string( $moved ) && $wpdb->rows[ $id ]->csv_file_path === $moved && file_get_contents( $moved ) === file_get_contents( $csv ), 'VDP old private CSV copied and row pointer published' );
		check( is_file( $csv ) && $wpdb->rows[ $id ]->fields === [ 'name' => 42 ] && $wpdb->rows[ $id ]->design_id === 100 + $id, 'VDP source and design/field identity preserved' );
		check( $moved === OC_Rest_API::relocate_private_vdp_template( $id ), 'VDP relocation retry reuses current row' );
	}
	$wpdb->rows[3] = (object) [ 'id' => 3, 'design_id' => 103, 'csv_file_path' => $fallback . '/vdp/original.csv' ];
	$wpdb->mode = 'race';
	check( null === OC_Rest_API::relocate_private_vdp_template( 3 ) && $wpdb->rows[3]->csv_file_path === 'concurrent-replacement.csv', 'VDP CAS preserves concurrent replacement' );
	$wpdb->rows[4] = (object) [ 'id' => 4, 'design_id' => 104, 'csv_file_path' => $fallback . '/vdp/original.csv' ];
	$wpdb->mode = 'uncertain';
	check( null === OC_Rest_API::relocate_private_vdp_template( 4 ) && is_file( $wpdb->rows[4]->csv_file_path ), 'Unknown VDP commit retains possibly published destination' );
	$wpdb->mode = '';
	wp_mkdir_p( $fixture . '/unknown/vdp' ); file_put_contents( $fixture . '/unknown/vdp/arbitrary.csv', "name\nNot trusted\n" );
	$wpdb->rows[5] = (object) [ 'id' => 5, 'design_id' => 105, 'csv_file_path' => $fixture . '/unknown/vdp/arbitrary.csv' ];
	check( null === OC_Rest_API::relocate_private_vdp_template( 5 ), 'VDP DB path cannot introduce an arbitrary private root' );
	symlink( $fallback . '/vdp/original.csv', $fallback . '/vdp/alias.csv' );
	$wpdb->rows[6] = (object) [ 'id' => 6, 'design_id' => 106, 'csv_file_path' => $fallback . '/vdp/alias.csv' ];
	check( null === OC_Rest_API::relocate_private_vdp_template( 6 ), 'VDP per-file symlink rejected' );
	$wpdb->rows = [];
	for ( $i = 1; $i <= 26; $i++ ) { $wpdb->rows[ $i ] = (object) [ 'id' => $i, 'design_id' => $i, 'csv_file_path' => $i < 26 ? $fixture . '/unknown/missing.csv' : $fallback . '/vdp/original.csv' ]; }
	$vdp = OC_Upload_Handler::private_storage_path( 'vdp' );
	invoke( OC_Rest_API::class, 'migrate_private_vdp_files', $vdp );
	check( get_option( 'oc_private_vdp_migration_cursor' ) === 25, 'Private VDP cursor advances beyond failed sources' );
	invoke( OC_Rest_API::class, 'migrate_private_vdp_files', $vdp );
	check( str_starts_with( $wpdb->rows[26]->csv_file_path, $vdp . '/' ) && get_option( 'oc_private_vdp_migration_cursor' ) === 0, 'Later private VDP row migrates and cursor wraps' );
	check( isset( OC_Storage_Upgrade::reports()['vdp:5'] ), 'Migration diagnostics exposed for status owner integration' );
	$record['file'] = 'preview-' . str_repeat( '2', 40 ) . '.png';
	$options[ $key ] = json_encode( $record );
	check( $previews . '/' . $record['file'] === OC_Rest_API::relocate_private_preview( substr( $key, strlen( 'oc_private_preview_' ) ) ), 'Cleanup owner can resolve previews through a storage-only API' );
	$before_expiry = $options;
	unset( $_SERVER['DOCUMENT_ROOT'], $_SERVER['REQUEST_METHOD'] );
	foreach ( $options as &$evidence ) { if ( is_array( $evidence ) && isset( $evidence['mac'], $evidence['data']['until'] ) ) {
		$evidence['data']['at'] = time() - 30000; $evidence['data']['until'] = time() - 1;
		$evidence['mac'] = hash_hmac( 'sha256', serialize( $evidence['data'] ), wp_salt( 'auth' ) );
	} } unset( $evidence );
	$old_preview_metadata = $options[ $key ]; $old_csv_pointer = $wpdb->rows[26]->csv_file_path;
	$expiry_url = invoke( OC_Rest_API::class, 'private_preview_url', substr( $key, strlen( 'oc_private_preview_' ) ), $record );
	check( $expiry_url === OC_Rest_API::validate_private_preview_url( $expiry_url ) && $options[ $key ] === $old_preview_metadata, 'Idle CLI evidence expiry preserves preview reads and signing metadata' );
	check( $old_csv_pointer === OC_Rest_API::relocate_private_vdp_template( 26 ) && $wpdb->rows[26]->csv_file_path === $old_csv_pointer, 'Idle CLI evidence expiry preserves VDP storage and row identity' );
	$options = $before_expiry; $_SERVER['DOCUMENT_ROOT'] = ABSPATH; $_SERVER['REQUEST_METHOD'] = 'GET';

	$secret = str_repeat( 'b', 64 );
	$_COOKIE['oc_private_browser'] = $secret . '.' . hash_hmac( 'sha256', $secret, wp_salt( 'auth' ) );
	$principal = OC_Rest_API::browser_principal();
	check( invoke( OC_Upload_Handler::class, 'record_ownership', 90, [ 'product_id' => 1, 'design_id' => 2, 'layer_id' => 3, 'token_hash' => str_repeat( 'c', 64 ) ], 'art.svg' ), 'New ownership recorded' );
	check( get_post_meta( 90, '_oc_artwork_browser' ) === $principal, 'Signed browser principal persisted' );
	$token = str_repeat( 'd', 64 );
	$transients[ 'oc_pubtok_' . hash( 'sha256', $token ) ] = [ 'version' => 2, 'binding_type' => 'browser', 'binding_hash' => hash( 'sha256', $secret ), 'created_at' => time(), 'expires_at' => time() + 3600 ];
	check( OC_Rest_API::public_token_owns_attachment( $token, 90, [ 1, 0, 2, 3 ] ), 'Rotated live token retains browser artwork ownership' );
	check( ! OC_Rest_API::public_token_owns_attachment( $token, 90, [ 2, 0, 2, 3 ] ), 'Browser ownership does not bypass attachment context' );
	$meta[91] = [ '_oc_artwork_context' => [ 1, 0, 2, 3 ], '_oc_artwork_token' => str_repeat( 'c', 64 ) ];
	check( ! OC_Rest_API::public_token_owns_attachment( $token, 91, [ 1, 0, 2, 3 ] ), 'New principal does not claim legacy token-only attachments' );
	$transients[ 'oc_pubtok_' . hash( 'sha256', $token ) ]['expires_at'] = time() - 1;
	check( ! OC_Rest_API::public_token_owns_attachment( $token, 90, [ 1, 0, 2, 3 ] ), 'Durable principal still requires a live request token' );
	$transients[ 'oc_pubtok_' . hash( 'sha256', $token ) ]['expires_at'] = time() + 3600;
	$site = 2; check( $principal !== OC_Rest_API::browser_principal(), 'Browser ownership principal is site scoped' ); $site = 1;
	$_COOKIE['oc_private_browser'] = $secret . '.' . str_repeat( '0', 64 );
	check( ! OC_Rest_API::public_token_owns_attachment( $token, 90, [ 1, 0, 2, 3 ] ), 'Forged cookie cannot recover ownership' );
	$transients[ 'oc_pubtok_' . hash( 'sha256', $token ) ]['binding_type'] = 'ip';
	check( ! OC_Rest_API::validate_public_token( $token ), 'IP ownership remains revoked' );
	fwrite( STDOUT, 'Storage upgrade checks passed: ' . $checks . ".\n" );
} finally {
	if ( is_dir( $fixture ) ) {
		$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $fixture, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $iterator as $file ) { $file->isDir() && ! $file->isLink() ? rmdir( $file->getPathname() ) : unlink( $file->getPathname() ); }
		rmdir( $fixture );
	}
}
