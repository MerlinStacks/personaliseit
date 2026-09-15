<?php
/**
 * Standalone checks for the REST API composition and direct-load contract.
 *
 * Run: php tests/rest-api-structure-regressions.php
 *
 * @package OverCustomise
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

class WP_REST_Server {
	public const READABLE  = 'GET';
	public const CREATABLE = 'POST';
}

// phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionDoubleUnderscore -- Standalone stub must match the WordPress callback name.
function __return_true(): bool {
	return true;
}

function expect( bool $condition, string $message ): void {
	if ( ! $condition ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI assertion message, not HTML output.
		throw new RuntimeException( $message );
	}
}

$hooks  = [];
$routes = [];

function add_action( string $hook, array $callback ): void {
	global $hooks;
	expect( is_callable( $callback ), 'Hook callback is callable: ' . $hook );
	$hooks[ $hook ] = $callback;
}

function register_rest_route( string $route_namespace, string $route, array $args ): void {
	global $routes;
	expect( 'overcustomise/v1' === $route_namespace, 'REST namespace is preserved.' );
	expect( is_callable( $args['callback'] ), 'Endpoint callback is callable: ' . $route );
	expect( is_callable( $args['permission_callback'] ), 'Permission callback is callable: ' . $route );
	$routes[ $route ] = $args;
}

// Do not bootstrap the plugin: this file must load its own traits.
require_once ABSPATH . 'includes/class-oc-rest-api.php';

$api = new OC_Rest_API();
$api->register();
$api->register_routes();
expect( 13 === count( $routes ), 'All existing routes are registered.' );
expect( [ OC_Rest_API::class, 'ensure_vdp_storage' ] === $hooks['init'], 'VDP hook retains its class identity.' );
foreach ( [ 'admin_post_oc_serve_preview', 'admin_post_nopriv_oc_serve_preview' ] as $hook ) {
	expect( [ OC_Rest_API::class, 'serve_private_preview' ] === $hooks[ $hook ], 'Preview hook retains its class identity.' );
}
expect( [ OC_Rest_API::class, 'validate_location_query' ] === $routes['/location-lookup']['args']['query']['validate_callback'], 'Location validator retains its class identity.' );

$class = new ReflectionClass( OC_Rest_API::class );
$files = [ $class->getFileName() ];
foreach ( $class->getTraits() as $trait ) {
	$files[] = $trait->getFileName();
}
foreach ( $files as $file ) {
	expect( count( file( $file ) ) < 1000, basename( $file ) . ' must stay below 1,000 lines.' );
}

echo "REST API structure regressions passed.\n";
