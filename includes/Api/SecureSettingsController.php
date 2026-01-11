<?php
/**
 * Secure Settings Controller
 *
 * Handles sensitive settings (API keys, secrets) that should not be exposed
 * via the public WordPress REST Settings API. Admin-only access required.
 *
 * @package PersonaliseIt\Api
 */

namespace PersonaliseIt\Api;

use WP_REST_Controller;
use WP_REST_Server;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Controller for admin-only secure settings operations.
 */
class SecureSettingsController extends WP_REST_Controller {

    /**
     * List of sensitive option keys that this controller manages.
     *
     * @var array
     */
    private $sensitive_keys = [
        'personaliseit_openrouter_api_key',
        'personaliseit_spotify_client_secret',
    ];

    /**
     * Constructor - register routes on REST API init.
     */
    public function __construct() {
        $this->namespace = 'personaliseit/v1';
        $this->rest_base = 'secure-settings';
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    /**
     * Register REST API routes for secure settings.
     */
    public function register_routes() {
        // GET: Retrieve sensitive settings (masked for display)
        register_rest_route( $this->namespace, '/' . $this->rest_base, [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'get_settings' ],
            'permission_callback' => [ $this, 'admin_permission_check' ],
        ] );

        // POST: Update sensitive settings
        register_rest_route( $this->namespace, '/' . $this->rest_base, [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'update_settings' ],
            'permission_callback' => [ $this, 'admin_permission_check' ],
        ] );
    }

    /**
     * Check if the current user has admin privileges.
     *
     * @return bool|WP_Error True if admin, WP_Error otherwise.
     */
    public function admin_permission_check() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return new WP_Error(
                'rest_forbidden',
                __( 'You do not have permission to access this resource.', 'personaliseit' ),
                [ 'status' => 403 ]
            );
        }
        return true;
    }

    /**
     * Get sensitive settings values.
     *
     * Returns actual values (not masked) since this endpoint is admin-only
     * and the frontend needs to display/save them.
     *
     * @return \WP_REST_Response Response with settings values.
     */
    public function get_settings() {
        $settings = [];
        foreach ( $this->sensitive_keys as $key ) {
            $settings[ $key ] = get_option( $key, '' );
        }
        return rest_ensure_response( $settings );
    }

    /**
     * Update sensitive settings values.
     *
     * @param \WP_REST_Request $request Request object with settings data.
     * @return \WP_REST_Response Response indicating success.
     */
    public function update_settings( $request ) {
        $data = $request->get_json_params();
        $updated = [];

        foreach ( $this->sensitive_keys as $key ) {
            if ( isset( $data[ $key ] ) ) {
                $value = sanitize_text_field( $data[ $key ] );
                update_option( $key, $value );
                $updated[ $key ] = true;
            }
        }

        return rest_ensure_response( [
            'success' => true,
            'updated' => $updated,
        ] );
    }
}
