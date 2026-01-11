<?php
namespace PersonaliseIt\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_REST_Controller;
use WP_REST_Server;
use WP_Error;

/**
 * Palette Controller
 */
class PaletteController extends WP_REST_Controller {

    /**
     * Constructor
     */
    public function __construct() {
        $this->namespace = 'personaliseit/v1';
        $this->rest_base = 'palettes';
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    /**
     * Register Routes
     */
    public function register_routes() {
        register_rest_route( $this->namespace, '/' . $this->rest_base, [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_palettes' ],
                'permission_callback' => '__return_true', // Public access for reading
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'create_palette' ],
                'permission_callback' => [ $this, 'permissions_check' ],
            ],
        ] );

        register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', [
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [ $this, 'delete_palette' ],
                'permission_callback' => [ $this, 'permissions_check' ],
            ],
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [ $this, 'update_palette' ],
                'permission_callback' => [ $this, 'permissions_check' ],
            ],
        ] );
    }

    /**
     * Permissions Check
     */
    public function permissions_check() {
        return current_user_can( 'manage_options' );
    }

    /**
     * Get Palettes
     */
    public function get_palettes() {
        // Security: Cap at 50 palettes to prevent DoS
        $args = [
            'post_type'      => 'personaliseit_pal',
            'posts_per_page' => 50,
            'post_status'    => 'publish',
        ];

        $posts = get_posts( $args );
        $data = [];

        foreach ( $posts as $post ) {
            $colors = get_post_meta( $post->ID, '_palette_colors', true );
            if ( ! is_array( $colors ) ) $colors = [];
            
            $data[] = [
                'id'     => $post->ID,
                'title'  => $post->post_title,
                'colors' => $colors,
            ];
        }

        return $data;
    }

    /**
     * Create Palette
     */
    public function create_palette( $request ) {
        $params = $request->get_json_params();
        
        $title = sanitize_text_field( $params['title'] ?? 'New Palette' );
        $colors = $params['colors'] ?? [];

        if ( ! is_array( $colors ) ) {
            return new WP_Error( 'invalid_data', 'Colors must be an array', [ 'status' => 400 ] );
        }

        // Sanitize colors robustly
        $colors = array_map(function($c) {
            // Allow hex or simple css color names if needed, but hex is safest for palettes.
            // If user enters 'red', sanitize_hex_color fails.
            // If we allow text, we must strip XSS.
            return sanitize_hex_color($c) ?: sanitize_text_field($c);
        }, $colors);
        // Filter out empty if strictly required, but empty string might be placeholder?
        // Let's keep empty strings but generally array_filter removes false/null/empty.
        // It's safer to just map.
        
        $post_id = wp_insert_post( [
            'post_title'  => $title,
            'post_type'   => 'personaliseit_pal',
            'post_status' => 'publish',
        ] );

        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        update_post_meta( $post_id, '_palette_colors', $colors );

        return [
            'id'     => $post_id,
            'title'  => $title,
            'colors' => $colors,
        ];
    }

    /**
     * Update Palette
     */
    public function update_palette( $request ) {
        $id = $request['id'];
        $params = $request->get_json_params();

        if ( isset( $params['title'] ) ) {
            wp_update_post( [
                'ID' => $id,
                'post_title' => sanitize_text_field( $params['title'] ),
            ] );
        }

        if ( isset( $params['colors'] ) && is_array( $params['colors'] ) ) {
            $colors = array_map(function($c) {
                return sanitize_hex_color($c) ?: sanitize_text_field($c);
            }, $params['colors']);
            update_post_meta( $id, '_palette_colors', $colors );
        }

        $colors = get_post_meta( $id, '_palette_colors', true );

        return [
            'id'     => (int) $id,
            'title'  => get_the_title( $id ),
            'colors' => $colors,
        ];
    }

    /**
     * Delete Palette
     */
    public function delete_palette( $request ) {
        $id = $request['id'];
        wp_delete_post( $id, true );
        return [ 'success' => true ];
    }
}
