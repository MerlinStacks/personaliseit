<?php
namespace PersonaliseIt\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AssetController {
    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        register_rest_route( 'personaliseit/v1', '/assets', [
            'methods' => 'GET',
            'callback' => [ $this, 'get_assets' ],
            'permission_callback' => '__return_true', // Public
        ] );

        register_rest_route( 'personaliseit/v1', '/assets', [
            'methods' => 'POST',
            'callback' => [ $this, 'create_asset' ],
            'permission_callback' => function() { return current_user_can('upload_files'); },
        ] );

        register_rest_route( 'personaliseit/v1', '/assets/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [ $this, 'delete_asset' ],
            'permission_callback' => function() { return current_user_can('upload_files'); },
        ] );
    }

    public function get_assets( $request ) {
        $page = $request->get_param( 'page' ) ? max( 1, intval( $request->get_param( 'page' ) ) ) : 1;
        $per_page = $request->get_param( 'per_page' ) ? intval( $request->get_param( 'per_page' ) ) : 100;
        
        // Security: Cap per_page to prevent DoS
        $per_page = min( max( 1, $per_page ), 100 );

        $args = [
            'post_type' => 'personaliseit_asset',
            'posts_per_page' => $per_page,
            'paged' => $page,
            'post_status' => 'publish',
        ];
        
        $query = new \WP_Query( $args );
        
        $result = [];
        
        foreach ( $query->posts as $post ) {
            $cats = get_the_terms( $post->ID, 'personaliseit_asset_cat' );
            $cat_names = [];
            if ( $cats && ! is_wp_error( $cats ) ) {
                foreach ( $cats as $c ) {
                    $cat_names[] = $c->name;
                }
            } else {
                $cat_names[] = 'Uncategorized';
            }
            
            $thumb = get_the_post_thumbnail_url( $post->ID, 'full' );
            if ( ! $thumb ) continue;

            $price = get_post_meta( $post->ID, 'personaliseit_asset_price', true );
            
            $result[] = [
                'id' => $post->ID,
                'title' => $post->post_title,
                'url' => $thumb,
                'price' => $price ? floatval( $price ) : 0,
                'categories' => $cat_names,
            ];
        }
        
        $response = rest_ensure_response( $result );
        $response->header( 'X-WP-Total', (int) $query->found_posts );
        $response->header( 'X-WP-TotalPages', (int) $query->max_num_pages );

        return $response;
    }

    public function create_asset( $request ) {
        $params = $request->get_file_params();
        $file = $params['file'];
        
        if ( ! $file ) {
            return new \WP_Error( 'no_file', 'No file uploaded', [ 'status' => 400 ] );
        }

        require_once( ABSPATH . 'wp-admin/includes/image.php' );
        require_once( ABSPATH . 'wp-admin/includes/file.php' );
        require_once( ABSPATH . 'wp-admin/includes/media.php' );

        $attachment_id = media_handle_upload( 'file', 0 );

        if ( is_wp_error( $attachment_id ) ) {
            return $attachment_id;
        }

        $title = sanitize_text_field( $request->get_param( 'title' ) );
        $price = floatval( $request->get_param( 'price' ) );
        
        // Handle categories (comma separated string or array)
        $categories_param = $request->get_param( 'categories' );
        $categories = [];
        if ( is_array( $categories_param ) ) {
            $categories = array_map( 'sanitize_text_field', $categories_param );
        } elseif ( is_string( $categories_param ) ) {
            $categories = array_map( 'sanitize_text_field', array_map( 'trim', explode( ',', $categories_param ) ) );
        }

        $post_id = wp_insert_post( [
            'post_title' => $title ? $title : get_the_title( $attachment_id ),
            'post_type' => 'personaliseit_asset',
            'post_status' => 'publish',
        ] );

        set_post_thumbnail( $post_id, $attachment_id );
        update_post_meta( $post_id, 'personaliseit_asset_price', $price );

        if ( ! empty( $categories ) ) {
            wp_set_object_terms( $post_id, $categories, 'personaliseit_asset_cat' );
        } else {
            wp_set_object_terms( $post_id, 'Uncategorized', 'personaliseit_asset_cat' );
        }

        $url = wp_get_attachment_url( $attachment_id );

        return rest_ensure_response( [
            'id' => $post_id,
            'title' => get_the_title( $post_id ),
            'url' => $url,
            'price' => $price,
            'categories' => $categories,
        ] );
    }

    public function delete_asset( $request ) {
        $id = (int) $request['id'];
        
        // Security: Verify the post exists and is our type before deleting
        $post = get_post( $id );
        if ( ! $post || $post->post_type !== 'personaliseit_asset' ) {
            return new \WP_Error( 'not_found', __( 'Asset not found.', 'personaliseit' ), [ 'status' => 404 ] );
        }

        $deleted = wp_delete_post( $id, true );

        if ( ! $deleted ) {
            return new \WP_Error( 'delete_failed', 'Failed to delete asset', [ 'status' => 500 ] );
        }

        return rest_ensure_response( [ 'deleted' => true, 'id' => $id ] );
    }
}
