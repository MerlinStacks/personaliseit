<?php
namespace PersonaliseIt\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Config Controller
 */
class ConfigController {

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    /**
     * Register Routes
     */
    public function register_routes() {
        register_rest_route( 'personaliseit/v1', '/config/(?P<id>\d+)', [
            'methods'  => 'POST',
            'callback' => [ $this, 'save_config' ],
            'permission_callback' => function() {
                return current_user_can( 'manage_options' );
            },
        ] );

        register_rest_route( 'personaliseit/v1', '/config/(?P<id>\d+)', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_config' ],
            'permission_callback' => '__return_true', // Public for frontend
        ] );
    }

    /**
     * Save Config
     */
    /**
     * Save Config
     */
    public function save_config( $request ) {
        $product_id = absint( $request->get_param( 'id' ) );
        
        // Security: Verify the post is a WooCommerce product
        if ( get_post_type( $product_id ) !== 'product' ) {
            return new \WP_Error( 'invalid_product', __( 'Invalid product ID.', 'personaliseit' ), [ 'status' => 400 ] );
        }

        $config = $request->get_json_params();

        if ( ! $config ) {
            return new \WP_Error( 'no_data', 'No configuration data provided', [ 'status' => 400 ] );
        }

        // Sanitize
        $config = $this->sanitize_recursive( $config );

        update_post_meta( $product_id, '_personaliseit_config', $config );

        return rest_ensure_response( [ 'success' => true ] );
    }

    private function sanitize_recursive( $data ) {
        if ( is_array( $data ) ) {
            foreach ( $data as $key => $value ) {
                $data[ $key ] = $this->sanitize_recursive( $value );
            }
            return $data;
        }
        if ( is_string( $data ) ) {
            return sanitize_textarea_field( $data );
        }
        return $data;
    }

    /**
     * Get Config
     */
    public function get_config( $request ) {
        $product_id = absint( $request->get_param( 'id' ) );
        
        // Security: Verify product exists and is accessible
        $post = get_post( $product_id );
        if ( ! $post || $post->post_type !== 'product' ) {
            return rest_ensure_response( [] );
        }
        
        // Only allow access to published products for unauthenticated requests
        if ( $post->post_status !== 'publish' && ! current_user_can( 'edit_post', $product_id ) ) {
            return rest_ensure_response( [] );
        }

        $config = get_post_meta( $product_id, '_personaliseit_config', true );

        if ( ! $config ) {
            return rest_ensure_response( [] );
        }

        return rest_ensure_response( $config );
    }
}
