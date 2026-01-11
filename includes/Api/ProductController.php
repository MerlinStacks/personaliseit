<?php
namespace PersonaliseIt\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Product Controller
 */
class ProductController {

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
        register_rest_route( 'personaliseit/v1', '/products', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_products' ],
            'permission_callback' => function() {
                return current_user_can( 'manage_options' );
            },
        ] );
        
        register_rest_route( 'personaliseit/v1', '/products/assign-template', [
            'methods'  => 'POST',
            'callback' => [ $this, 'assign_template' ],
            'permission_callback' => function() {
                return current_user_can( 'manage_options' );
            },
        ] );
        
        register_rest_route( 'personaliseit/v1', '/products/by-template/(?P<template_id>\d+)', [
            'methods'  => 'GET',
            'callback' => [ $this, 'get_products_by_template' ],
            'permission_callback' => function() {
                return current_user_can( 'manage_options' );
            },
        ] );
    }

    /**
     * Get Products (including variations).
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_products( $request ) {
        $search = $request->get_param( 'search' );
        
        // Search parent products
        $args = [
            'post_type'      => 'product',
            'posts_per_page' => 20,
            'post_status'    => 'publish',
        ];

        if ( ! empty( $search ) ) {
            $args['s'] = sanitize_text_field( $search );
        }

        $products = get_posts( $args );
        $data = [];

        foreach ( $products as $product_post ) {
            $product = wc_get_product( $product_post->ID );
            if ( ! $product ) continue;
            
            $image_id = $product->get_image_id();
            $image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : wc_placeholder_img_src( 'thumbnail' );
            
            // Check if product has personalization config
            $config = get_post_meta( $product_post->ID, '_personaliseit_config', true );
            $has_config = ! empty( $config ) && ! empty( $config['views'] );

            $data[] = [
                'id'           => $product->get_id(),
                'title'        => $product->get_name(),
                'image'        => $image_url,
                'has_config'   => $has_config,
                'type'         => $product->get_type(),
                'is_variation' => false,
            ];
            
            // If variable product, also include its variations
            if ( $product->is_type( 'variable' ) ) {
                $variations = $product->get_available_variations();
                foreach ( $variations as $variation ) {
                    $var_product = wc_get_product( $variation['variation_id'] );
                    if ( ! $var_product ) continue;
                    
                    // Build variation title with attributes
                    $attributes = [];
                    foreach ( $variation['attributes'] as $attr_key => $attr_value ) {
                        if ( $attr_value ) {
                            $attributes[] = $attr_value;
                        }
                    }
                    $var_title = $product->get_name() . ' - ' . implode( ', ', $attributes );
                    
                    $var_image = $variation['image']['thumb_src'] ?? $image_url;
                    
                    // Check variation-specific config
                    $var_config = get_post_meta( $variation['variation_id'], '_personaliseit_config', true );
                    $var_has_config = ! empty( $var_config ) && ! empty( $var_config['views'] );
                    
                    $data[] = [
                        'id'           => $variation['variation_id'],
                        'title'        => $var_title,
                        'image'        => $var_image,
                        'has_config'   => $var_has_config,
                        'type'         => 'variation',
                        'is_variation' => true,
                        'parent_id'    => $product->get_id(),
                    ];
                }
            }
        }

        return rest_ensure_response( $data );
    }

    /**
     * Bulk assign a template to multiple products.
     * Copies the template config into each product's meta.
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function assign_template( $request ) {
        $template_id = absint( $request->get_param( 'template_id' ) );
        $product_ids = $request->get_param( 'product_ids' );
        
        if ( empty( $template_id ) || empty( $product_ids ) || ! is_array( $product_ids ) ) {
            return new \WP_Error( 'invalid_params', __( 'Template ID and product IDs are required.', 'personaliseit' ), [ 'status' => 400 ] );
        }
        
        // Fetch template content
        $template = get_post( $template_id );
        if ( ! $template || $template->post_type !== 'personaliseit_tpl' ) {
            return new \WP_Error( 'invalid_template', __( 'Template not found.', 'personaliseit' ), [ 'status' => 404 ] );
        }
        
        $config = json_decode( $template->post_content, true );
        if ( json_last_error() !== JSON_ERROR_NONE || empty( $config ) ) {
            return new \WP_Error( 'invalid_config', __( 'Template has invalid configuration.', 'personaliseit' ), [ 'status' => 400 ] );
        }
        
        $updated = [];
        $failed = [];
        
        foreach ( $product_ids as $pid ) {
            $pid = absint( $pid );
            $product = wc_get_product( $pid );
            if ( ! $product ) {
                $failed[] = $pid;
                continue;
            }
            
            // Save config to product meta (copy, not link)
            update_post_meta( $pid, '_personaliseit_config', $config );
            update_post_meta( $pid, '_personaliseit_source_template', $template_id );
            $updated[] = $pid;
        }
        
        return rest_ensure_response( [
            'success' => true,
            'updated' => $updated,
            'failed'  => $failed,
            'message' => sprintf( __( 'Template assigned to %d product(s).', 'personaliseit' ), count( $updated ) ),
        ] );
    }

    /**
     * Get products that use a specific template.
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_products_by_template( $request ) {
        $template_id = absint( $request->get_param( 'template_id' ) );
        
        $args = [
            'post_type'      => 'product',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query'     => [
                [
                    'key'     => '_personaliseit_source_template',
                    'value'   => $template_id,
                    'compare' => '=',
                ],
            ],
        ];
        
        $products = get_posts( $args );
        $data = [];
        
        foreach ( $products as $product_post ) {
            $product = wc_get_product( $product_post->ID );
            $data[] = [
                'id'    => $product->get_id(),
                'title' => $product->get_name(),
            ];
        }
        
        return rest_ensure_response( $data );
    }
}
