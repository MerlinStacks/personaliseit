<?php
// Standalone CLI fixtures deliberately replace WordPress globals and have no WP runtime.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped, WordPress.WP.GlobalVariablesOverride.Prohibited, WordPress.WP.AlternativeFunctions.json_encode_json_encode
/** Exercise the real filter endpoint's failure accounting without a WordPress server. */
define( 'ABSPATH', __DIR__ );
define( 'DAY_IN_SECONDS', 86400 );
define( 'MINUTE_IN_SECONDS', 60 );
class WP_Error {
	public function __construct( private string $code, private string $message = '', private array $data = [] ) {}
	public function get_error_code(): string {
		return $this->code; }
	public function get_error_message(): string {
		return $this->message; }
	public function get_error_data(): array {
		return $this->data; }
	public function add_data( array $data ): void {
		$this->data = $data; }
}
class WP_REST_Response {
	public function __construct( public array $data ) {}
	public function header( string $name, string $value ): void {}
}
class WP_REST_Request {
	public function __construct( private array $body ) {}
	public function get_json_params(): array {
		return $this->body; }
	public function get_header( string $key ): string {
		return ''; }
}
function __( string $value, string $domain = '' ): string {
	return $value; }
function absint( mixed $value ): int {
	return abs( (int) $value ); }
function sanitize_key( string $value ): string {
	return $value; }
function is_wp_error( mixed $value ): bool {
	return $value instanceof WP_Error; }
function is_user_logged_in(): bool {
	return true; }
function get_current_user_id(): int {
	return 1; }
function wp_check_invalid_utf8( string $value, bool $strip = false ): string {
	return $value; }
function rest_ensure_response( array $data ): WP_REST_Response {
	return new WP_REST_Response( $data ); }
function get_transient( string $key ): int {
	return 3; } // Previously burned attempts must not strand this image.
function get_posts( array $args ): array {
	return array_keys( OC_Upload_Handler::$results ); }
function get_post_meta( int $id, string $key, bool $single ): mixed {
	return match ( $key ) {
		'_oc_ai_filter_source_id' => 10,
		'_oc_ai_filter_attempt' => OC_Upload_Handler::$results[ $id ]['attempt'],
		'_oc_ai_filter_id' => 7,
		'_oc_ai_filter_group' => 'test-group',
		default => 'png',
	};
}
class OC_Logger {
	public static function warning( string $message ): void {}
	public static function error( string $message ): void {}
}
class OC_Admin_Settings {
	public static function get_ai_image_configuration(): array {
		return [
			'api_key'  => 'test-key',
			'model'    => 'test-model',
			'provider' => 'google',
		]; }
	public static function get_ai_image_providers(): array {
		return [ 'google' => 'Google' ]; }
}
class OC_DB {
	public static function get_image_filters( bool $active ): array {
		return [
			(object) [
				'id'         => 7,
				'name'       => 'Portrait',
				'filter_key' => 'ai',
				'prompt'     => 'Draw a portrait',
			],
		]; }
}
class OC_AI_Image_Filter {
	public static string $mode = 'error';
	public static function generate( int $id, string $prompt ): array|WP_Error {
		if ( 'throw' === self::$mode ) {
			throw new RuntimeException( 'Provider timeout' ); }
		return 'error' === self::$mode ? new WP_Error( 'ai_unavailable' ) : [
			'bytes' => 'image',
			'mime'  => 'image/png',
		];
	}
}
class OC_Upload_Handler {
	public static array $results = [];
	public static function recent_filter_attachment_ids( array $context, string $token ): array {
		return [ 1, 0, 1, 1 ] === $context ? array_keys( self::$results ) : [];
	}
	public static function admin_default_attachment_is_valid( int $id ): bool {
		return true; }
	public static function ai_source_is_valid( int $id, bool $owned ): bool {
		return true; }
	public static function attachment_is_accepted( mixed ...$args ): bool {
		return true; }
	public static function attachment_fingerprint( int $id ): string {
		return 'source-fingerprint'; }
	public static function attachment_access_url( int $id ): string {
		return 'https://example.com/artwork/' . $id; }
	public static function save_generated_image( string $bytes, string $mime, array $context, array $provenance, bool $remove, int $colours ): array|WP_Error {
		if ( 'storage-error' === OC_AI_Image_Filter::$mode ) {
			return new WP_Error( 'storage_failed' ); }
		if ( 'storage-throw' === OC_AI_Image_Filter::$mode ) {
			throw new RuntimeException( 'Storage failed' ); }
		$id                   = count( self::$results ) + 20;
		self::$results[ $id ] = $provenance;
		return [
			'attachment_id' => $id,
			'preview_url'   => self::attachment_access_url( $id ),
		];
	}
	public static function result_attachment_usage( array $result ): array {
		return [ $result['attachment_id'] => 5 ]; }
}
require_once __DIR__ . '/../includes/rest-api/trait-oc-rest-api-ai.php';
class Filter_Endpoint_Fixture {
	use OC_Rest_API_AI;

	private const MAX_AI_PROMPT_BYTES    = 16384;
	private const MAX_AI_RESULT_BYTES    = 15728640;
	private const MAX_AI_FILTER_ATTEMPTS = 3;
	private const AI_LOCK_TTL            = 300;
	public static int $quota             = 0;
	public static int $storage           = 0;
	public static int $locks             = 0;
	public static array $settings        = [];
	private function verify_public_write_auth( WP_REST_Request $request ): bool {
		return true; }
	private static function active_assignment_design( mixed ...$args ): array {
		return []; }
	private static function public_design_layer( mixed ...$args ): object {
		return (object) [ 'settings' => [] ]; }
	private static function decode_layer_settings( mixed $settings ): array {
		return self::$settings + [
			'image_filter_ids'      => [ 7 ],
			'default_attachment_id' => 10,
		]; }
	private static function setting_boolean( mixed $value ): bool {
		return (bool) $value; }
	private static function setting_positive_int( mixed $value, int $max ): int {
		return (int) $value; }
	private static function ai_quota_specs( string $actor ): array {
		return [ 'kind' => 'quota' ]; }
	private static function image_filter_quota_status( string $actor ): array {
		return [
			'remaining' => 5 - self::$quota,
			'limit'     => 5,
		]; }
	private static function upload_capacity_specs( mixed ...$args ): array {
		return [ 'kind' => 'storage' ]; }
	private static function acquire_option_lock( mixed ...$args ): string {
		++self::$locks;
		return 'owner'; }
	private static function delete_owned_option( mixed ...$args ): void {
		--self::$locks; }
	private static function reserve_budgets( array $spec ): array|WP_Error {
		if ( 'quota' === $spec['kind'] && self::$quota >= 5 ) {
			return new WP_Error( 'ai_quota_exceeded', 'Limit reached', [ 'status' => 429 ] ); }
		if ( 'quota' === $spec['kind'] ) {
			++self::$quota;
		} else {
			++self::$storage; }
		return $spec;
	}
	private static function release_budget_reservation( array $spec ): bool {
		if ( 'quota' === $spec['kind'] ) {
			--self::$quota;
		} else {
			--self::$storage; }
		return true;
	}
	private static function reservation_covers_usage( mixed ...$args ): bool {
		return true; }
	private static function finalise_budget_reservation( mixed ...$args ): bool {
		return true; }
}
function check( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message ); }
}
$api  = new Filter_Endpoint_Fixture();
$body = [
	'source_attachment_id' => 10,
	'filter_id'            => 7,
	'layer_id'             => 1,
	'design_id'            => 1,
	'product_id'           => 1,
];
foreach ( [ 'error', 'throw', 'storage-error', 'storage-throw' ] as $mode ) {
	OC_AI_Image_Filter::$mode = $mode;
	$result                   = $api->apply_image_filter( new WP_REST_Request( $body ) );
	check( is_wp_error( $result ), $mode . ' should return a controlled error' );
	check( 0 === Filter_Endpoint_Fixture::$quota, $mode . ' consumed generation quota' );
	check( 0 === Filter_Endpoint_Fixture::$storage, $mode . ' leaked storage reservation' );
	check( 0 === Filter_Endpoint_Fixture::$locks, $mode . ' leaked its group lock' );
}
$listing = $api->apply_image_filter( new WP_REST_Request( $body + [ 'list_only' => true ] ) );
check( 3 === $listing->data['retries_remaining'], 'Failed legacy attempts still consume results' );
check( 5 === $listing->data['quota']['remaining'], 'Failed requests changed the customer allowance' );
Filter_Endpoint_Fixture::$quota = 5;
$limited                        = $api->apply_image_filter( new WP_REST_Request( $body ) );
check( is_wp_error( $limited ) && 0 === $limited->get_error_data()['quota']['remaining'], 'Quota rejection must report the current remaining count' );
check( 0 === Filter_Endpoint_Fixture::$storage && 0 === Filter_Endpoint_Fixture::$locks, 'Quota rejection leaked resources' );
Filter_Endpoint_Fixture::$quota = 0;
OC_AI_Image_Filter::$mode       = 'success';
for ( $i = 1; $i <= 3; ++$i ) {
	$result = $api->apply_image_filter( new WP_REST_Request( $body ) );
	check( $result instanceof WP_REST_Response, 'A valid result was blocked by past failures' );
	check( 3 - $i === $result->data['retries_remaining'], 'Incorrect successful-result allowance' );
	check( 5 - $i === $result->data['quota']['remaining'], 'Incorrect global allowance' );
}
$result = $api->apply_image_filter( new WP_REST_Request( $body ) );
check( is_wp_error( $result ) && 'ai_filter_attempt_limit' === $result->get_error_code(), 'Successful results must still respect the per-image limit' );
check( 3 === Filter_Endpoint_Fixture::$quota, 'Rejected fourth result consumed quota' );
echo "AI filter attempt regressions passed.\n";

$history = $api->image_filter_history( new WP_REST_Request( $body ) );
check( 3 === count( $history->data['results'] ), 'History should restore retained results from this context' );
check( 3 === count( $history->data['results'][0]['related_results'] ), 'History must restore the result group for accurate retry counts' );
check( 10 === $history->data['results'][0]['source_attachment_id'], 'History lost the original source' );
check( 3 === Filter_Endpoint_Fixture::$quota, 'History retrieval consumed a generation' );
$other               = $body;
$other['product_id'] = 2;
check( [] === $api->image_filter_history( new WP_REST_Request( $other ) )->data['results'], 'History crossed product contexts' );
Filter_Endpoint_Fixture::$settings = [
	'allow_image_filter_change' => false,
	'default_image_filter_id'   => 8,
];
check( [] === $api->image_filter_history( new WP_REST_Request( $body ) )->data['results'], 'History exposed an effect disallowed by fixed filter settings' );
Filter_Endpoint_Fixture::$settings = [ 'allow_image_change' => false ];
check( is_wp_error( $api->image_filter_history( new WP_REST_Request( $body ) ) ), 'Fixed artwork must not be replaceable from history' );
echo "AI filter history endpoint regressions passed.\n";

// Use the real quota reader against rolling usage, independently of the endpoint doubles.
function current_user_can( string $capability ): bool {
	return $GLOBALS['quota_admin'] ?? false; }
function apply_filters( string $hook, mixed $value ): mixed {
	return $value; }
function get_current_blog_id(): int {
	return 1; }
$GLOBALS['wpdb'] = new class() {
	public string $options    = 'wp_options';
	public string $last_error = '';
	public function prepare( string $sql, string $key ): string {
		return $key; }
	public function get_var( string $key ): mixed {
		return $GLOBALS['quota_options'][ $key ] ?? null; }
};
require_once __DIR__ . '/../includes/rest-api/trait-oc-rest-api-budgets.php';
class Quota_Status_Fixture {
	use OC_Rest_API_Budgets;

	public static function status( string $actor ): ?array {
		return self::image_filter_quota_status( $actor ); }
	public static function key( string $actor ): string {
		return self::budget_option_name( 'ai:actor:' . hash( 'sha256', $actor ) ); }
}
check( 5 === Quota_Status_Fixture::status( 'customer' )['remaining'], 'New customer should have five generations' );
$GLOBALS['quota_options'][ Quota_Status_Fixture::key( 'customer' ) ] = json_encode(
	[
		'version'        => 2,
		'window_type'    => 'sliding',
		'window_seconds' => 900,
		'buckets'        => [
			[
				'timestamp' => time() - 901,
				'count'     => 2,
				'bytes'     => 0,
			],
			[
				'timestamp' => time() - 100,
				'count'     => 3,
				'bytes'     => 0,
			],
		],
	]
);
$status = Quota_Status_Fixture::status( 'customer' );
check( 2 === $status['remaining'], 'Expired generations were not returned to the allowance' );
check( $status['next_reset_in'] > 795 && $status['next_reset_in'] <= 800, 'Wrong rolling reset time' );
check( 5 === Quota_Status_Fixture::status( 'another-customer' )['remaining'], 'Allowance leaked across customers' );
$GLOBALS['quota_options'][ Quota_Status_Fixture::key( 'customer' ) ] = 'invalid';
check( null === Quota_Status_Fixture::status( 'customer' ), 'Malformed state must not promise five available generations' );
$GLOBALS['quota_admin'] = true;
check( true === Quota_Status_Fixture::status( 'admin' )['unlimited'], 'Admin exemption should be explicit' );
echo "AI filter quota display regressions passed.\n";
