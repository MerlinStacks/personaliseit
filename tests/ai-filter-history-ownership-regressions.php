<?php
/** Verify history queries stay bounded to the customer and authorised context. */
define( 'ABSPATH', __DIR__ );
define( 'DAY_IN_SECONDS', 86400 );
function absint( mixed $value ): int { return abs( (int) $value ); }
function is_user_logged_in(): bool { return ! empty( $GLOBALS['user_id'] ); }
function get_current_user_id(): int { return $GLOBALS['user_id'] ?? 0; }
function maybe_serialize( mixed $value ): string { return serialize( $value ); }
function get_posts( array $args ): array { $GLOBALS['query'] = $args; return []; }
class OC_Rest_API {
	public static function validate_public_token( string $token ): bool { return 'valid-token' === $token; }
	public static function browser_principal(): string { return str_repeat( 'b', 64 ); }
}
require_once __DIR__ . '/../includes/class-oc-upload-handler.php';
function check( bool $condition, string $message ): void { if ( ! $condition ) { throw new RuntimeException( $message ); } }
$context = [ 50, 51, 7, 8 ];
OC_Upload_Handler::recent_filter_attachment_ids( $context, '' );
check( ! isset( $GLOBALS['query'] ), 'Anonymous requests without ownership must not scan artwork' );
$GLOBALS['user_id'] = 42;
OC_Upload_Handler::recent_filter_attachment_ids( $context, '' );
$query = $GLOBALS['query'];
check( 24 === $query['posts_per_page'], 'History query must remain bounded' );
check( 'AND' === $query['meta_query']['relation'], 'Ownership must be combined with context, not optional' );
check( [ 'relation' => 'OR', [ 'key' => '_oc_artwork_user_id', 'value' => 42 ] ] === $query['meta_query'][1], 'Account history must query this customer only' );
check( serialize( $context ) === $query['meta_query'][2][0]['value'], 'History must require the exact primary context' );
check( serialize( $context ) === $query['meta_query'][2][1]['value'], 'Granted contexts must also match exactly' );
check( str_starts_with( $query['meta_query'][2][1]['key'], '_oc_artwork_context_grant_' ), 'Only explicit grants may bridge variations' );
$GLOBALS['user_id'] = 0;
OC_Upload_Handler::recent_filter_attachment_ids( $context, 'valid-token' );
$owners = $GLOBALS['query']['meta_query'][1];
check( hash( 'sha256', 'valid-token' ) === $owners[0]['value'], 'Guest token ownership must match' );
check( str_repeat( 'b', 64 ) === $owners[1]['value'], 'Guest browser ownership must match' );
unset( $GLOBALS['query'] );
OC_Upload_Handler::recent_filter_attachment_ids( [ 0, 0, 0, 0 ], 'valid-token' );
check( ! isset( $GLOBALS['query'] ), 'Invalid contexts must never query the history' );
echo "AI filter history ownership regressions passed.\n";
