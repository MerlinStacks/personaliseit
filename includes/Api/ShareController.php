<?php
namespace PersonaliseIt\Api;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ShareController {

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        register_rest_route( 'personaliseit/v1', '/share', [
            'methods' => 'POST',
            'callback' => [ $this, 'create_share_link' ],
            'permission_callback' => [ $this, 'verify_share_permissions' ],
        ] );

        register_rest_route( 'personaliseit/v1', '/share/(?P<id>[a-zA-Z0-9]+)', [
            'methods' => 'GET',
            'callback' => [ $this, 'get_shared_design' ],
            'permission_callback' => '__return_true',
        ] );
    }

    /**
     * Verify share permissions with nonce and rate limiting.
     *
     * Why: Public share endpoint needs protection against spam/abuse.
     * Rate limit: 10 shares per hour per IP address.
     *
     * @param \WP_REST_Request $request The request object.
     * @return bool|\WP_Error True if allowed, WP_Error otherwise.
     */
    public function verify_share_permissions( $request ) {
        // Verify nonce for CSRF protection
        $nonce = $request->get_header( 'x_wp_nonce' );
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return new \WP_Error( 'rest_forbidden', __( 'Invalid or missing nonce.', 'personaliseit' ), [ 'status' => 401 ] );
        }

        // Rate limit: 10 shares per hour per IP
        $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
        $key = 'personaliseit_share_limit_' . md5( $ip );
        $count = (int) get_transient( $key );

        if ( $count >= 10 ) {
            return new \WP_Error( 'rate_limited', __( 'Too many share requests. Please try again later.', 'personaliseit' ), [ 'status' => 429 ] );
        }

        set_transient( $key, $count + 1, HOUR_IN_SECONDS );
        return true;
    }

    /**
     * Create/Save Share Link
     */
    public function create_share_link( $request ) {
        $data = $request->get_json_params();

        if ( empty( $data['config'] ) && empty( $data['userInputs'] ) ) {
            return new \WP_Error( 'missing_data', 'Missing design data.', [ 'status' => 400 ] );
        }

        // Sanitize Data
        $config = isset($data['config']) ? $this->sanitize_recursive($data['config']) : [];
        $userInputs = isset($data['userInputs']) ? $this->sanitize_recursive($data['userInputs']) : [];
        $productId = isset($data['productId']) ? absint($data['productId']) : 0;

        // Generate a short hash/slug
        // We use a combination of time and randomness to ensure uniqueness
        // Since we want short URLs, we can use uniqid or generate 8 char random string
        $slug = substr( md5( uniqid( rand(), true ) ), 0, 8 );

        // Conflict check? (very unlikely with md5 8 chars, 4 billion^2 combinations roughly, but let's be safe-ish)
        // For MVP, we assume uniqueness.

        $post_id = wp_insert_post( [
            'post_type'    => 'personaliseit_share',
            'post_title'   => $slug,
            'post_name'    => $slug,
            'post_status'  => 'publish',
            'post_content' => wp_json_encode( [
                'config' => $config, // Full View/Layer Config
                'userInputs' => $userInputs, // Text values, image URLs etc.
                'productId' => $productId
            ] ),
        ] );

        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        // Return the Share URL and ID
        // The URL will be constructed by the frontend: product_url + ?share_id=$slug
        return rest_ensure_response( [
            'id' => $slug,
            'message' => 'Design shared successfully.'
        ] );
    }

    private function sanitize_recursive( $data ) {
        if ( is_array( $data ) ) {
            foreach ( $data as $key => $value ) {
                $data[ $key ] = $this->sanitize_recursive( $value );
            }
            return $data;
        }
        if ( is_string( $data ) ) {
            return sanitize_textarea_field( $data ); // Preserves newlines
        }
        return $data;
    }

    /**
     * Get Shared Design
     * Used via AJAX or pre-loading
     */
    public function get_shared_design( $request ) {
        $slug = $request->get_param( 'id' );
        
        // Find post by slug
        $args = [
            'name'        => $slug,
            'post_type'   => 'personaliseit_share',
            'post_status' => 'publish',
            'numberposts' => 1
        ];
        
        $posts = get_posts( $args );
        
        if ( ! $posts ) {
            return new \WP_Error( 'not_found', 'Shared design not found.', [ 'status' => 404 ] );
        }
        
        $post = $posts[0];
        $content = json_decode( $post->post_content, true );
        
        return rest_ensure_response( $content );
    }
}
