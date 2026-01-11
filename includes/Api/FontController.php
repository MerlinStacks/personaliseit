<?php
namespace PersonaliseIt\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_REST_Controller;
use WP_REST_Server;
use WP_Error;

/**
 * Font Controller
 */
class FontController extends WP_REST_Controller {

    /**
     * Constructor
     */
    public function __construct() {
        $this->namespace = 'personaliseit/v1';
        $this->rest_base = 'fonts';
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    /**
     * Register Routes
     */
    public function register_routes() {
        register_rest_route( $this->namespace, '/' . $this->rest_base, [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_fonts' ],
                'permission_callback' => '__return_true', // Public access for reading fonts
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'upload_font' ],
                'permission_callback' => [ $this, 'permissions_check' ],
            ],
        ] );

        register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', [
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [ $this, 'delete_font' ],
                'permission_callback' => [ $this, 'permissions_check' ],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE, // POST to update with file
                'callback'            => [ $this, 'update_font' ],
                'permission_callback' => [ $this, 'permissions_check' ],
            ],
        ] );
    }

    /**
     * Permissions Check (for admin-only endpoints)
     */
    public function permissions_check() {
        return current_user_can( 'manage_options' );
    }

    /**
     * Get Fonts
     */
    public function get_fonts() {
        // We will use a Custom Post Type 'personaliseit_font'
        // Security: Cap at 200 fonts to prevent DoS
        $args = [
            'post_type'      => 'personaliseit_font',
            'posts_per_page' => 200,
            'post_status'    => 'publish',
        ];

        $posts = get_posts( $args );
        $data = [];

        foreach ( $posts as $post ) {
            $url = get_post_meta( $post->ID, '_font_url', true );
            $files = [
                'ttf'   => get_post_meta( $post->ID, '_font_url_ttf', true ),
                'otf'   => get_post_meta( $post->ID, '_font_url_otf', true ),
                'woff'  => get_post_meta( $post->ID, '_font_url_woff', true ),
                'woff2' => get_post_meta( $post->ID, '_font_url_woff2', true ),
            ];

            // Backwards compatibility: if specific meta empty, check if main url matches ext
            if ($url) {
                $ext = strtolower(pathinfo($url, PATHINFO_EXTENSION));
                if (empty($files[$ext]) && isset($files[$ext])) {
                     $files[$ext] = $url;
                }
            }

            $family = get_post_meta( $post->ID, '_font_family', true );
            $data[] = [
                'id'     => $post->ID,
                'title'  => $post->post_title,
                'url'    => $url, // Legacy/Primary
                'files'  => $files,
                'family' => $family,
                'source' => get_post_meta( $post->ID, '_font_source', true ) ?: 'local',
            ];
        }

        // Add default fonts
        $defaults = [
            ['id' => 'arial', 'title' => 'Arial', 'family' => 'Arial', 'url' => '', 'files' => []],
            ['id' => 'times', 'title' => 'Times New Roman', 'family' => 'Times New Roman', 'url' => '', 'files' => []],
            ['id' => 'courier', 'title' => 'Courier New', 'family' => 'Courier New', 'url' => '', 'files' => []],
            ['id' => 'roboto', 'title' => 'Roboto', 'family' => 'Roboto', 'url' => 'https://fonts.gstatic.com/s/roboto/v30/KFOmCnqEu92Fr1Mu4mxK.woff2', 'files' => [
                'woff2' => 'https://fonts.gstatic.com/s/roboto/v30/KFOmCnqEu92Fr1Mu4mxK.woff2',
                'ttf' => 'https://github.com/google/fonts/raw/main/apache/roboto/Roboto-Regular.ttf' // Allow vectorization
            ]], 
        ];

        return array_merge( $defaults, $data );
    }

    /**
     * Upload Font
     */
    public function upload_font( $request ) {
        $files = $request->get_file_params();
        
        $params = $request->get_json_params();
        if ( ! $params ) {
            $params = $request->get_body_params();
        }

        // Check if this is a Google Font / URL import
        if ( isset( $params['source'] ) && $params['source'] === 'google' ) {
            $title = sanitize_text_field( $params['title'] );
            $family = sanitize_text_field( $params['family'] );
            $url = esc_url_raw( $params['url'] );

            if ( empty( $title ) || empty( $url ) ) {
                return new WP_Error( 'invalid_data', 'Title and URL are required', [ 'status' => 400 ] );
            }

            $post_id = wp_insert_post( [
                'post_title'  => $title,
                'post_type'   => 'personaliseit_font',
                'post_status' => 'publish',
            ] );

            if ( is_wp_error( $post_id ) ) {
                return $post_id;
            }

            update_post_meta( $post_id, '_font_source', 'google' );
            update_post_meta( $post_id, '_font_url', $url );
            update_post_meta( $post_id, '_font_family', $family );
            
            // Assume WOFF2 for Google Fonts usually, or store as plain URL.
            // Google Fonts CSS URL is not a file, it's a CSS file.
            // Frontend logic handles CSS imports if it detects CSS extension or google domain.
            
            return [
                'id'     => $post_id,
                'title'  => $title,
                'url'    => $url,
                'files'  => [], // No local files
                'family' => $family,
                'source' => 'google'
            ];
        }

        if ( empty( $files['file'] ) ) {
            return new WP_Error( 'no_file', 'No file uploaded', [ 'status' => 400 ] );
        }

        $file = $files['file'];
        $title = sanitize_text_field( $params['title'] ?? pathinfo( $file['name'], PATHINFO_FILENAME ) );
        
        // Security: Validate actual file content MIME type
        $finfo = finfo_open( FILEINFO_MIME_TYPE );
        $actual_mime = finfo_file( $finfo, $file['tmp_name'] );
        finfo_close( $finfo );

        // Allow standard font MIME types and application/octet-stream (common for fonts)
        $valid_mimes = [ 'font/woff2', 'font/woff', 'font/ttf', 'font/otf', 'font/sfnt', 'application/font-woff', 'application/font-woff2', 'application/x-font-ttf', 'application/x-font-otf', 'application/octet-stream' ];
        if ( ! in_array( $actual_mime, $valid_mimes, true ) ) {
            return new WP_Error( 'invalid_file', __( 'Invalid font file type.', 'personaliseit' ), [ 'status' => 400 ] );
        }

        // Handle upload
        require_once( ABSPATH . 'wp-admin/includes/file.php' );
        
        $overrides = [ 
            'test_form' => false,
            'mimes' => [
                'woff2' => 'font/woff2',
                'woff'  => 'font/woff',
                'ttf'   => 'font/ttf',
                'otf'   => 'font/otf'
            ]
        ];
        
        $upload = wp_handle_upload( $file, $overrides );

        if ( isset( $upload['error'] ) ) {
            return new WP_Error( 'upload_error', $upload['error'], [ 'status' => 500 ] );
        }

        // Create Post
        $post_id = wp_insert_post( [
            'post_title'  => $title,
            'post_type'   => 'personaliseit_font',
            'post_status' => 'publish',
        ] );

        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        update_post_meta( $post_id, '_font_source', 'local' ); // Default
        update_post_meta( $post_id, '_font_url', $upload['url'] );
        update_post_meta( $post_id, '_font_family', $title ); // Use title as font family name
        
        // Save specific meta for ext
        $ext = strtolower(pathinfo($upload['file'], PATHINFO_EXTENSION));
        update_post_meta( $post_id, '_font_url_' . $ext, $upload['url'] );

        return [
            'id'     => $post_id,
            'title'  => $title,
            'url'    => $upload['url'],
            'files'  => [ $ext => $upload['url'] ],
            'family' => $title,
            'source' => 'local'
        ];
    }

    /**
     * Update Font
     */
    public function update_font( $request ) {
        $id = $request['id'];
        $files = $request->get_file_params();
        // $params = $request->get_body_params(); // If we want to update title too

        if ( empty( $files['file'] ) ) {
            return new WP_Error( 'no_file', 'No file uploaded', [ 'status' => 400 ] );
        }

        $file = $files['file'];
        
        require_once( ABSPATH . 'wp-admin/includes/file.php' );
        
        $overrides = [ 
            'test_form' => false,
            'mimes' => [
                'woff2' => 'font/woff2',
                'woff'  => 'font/woff',
                'ttf'   => 'font/ttf',
                'otf'   => 'font/otf'
            ]
        ];
        
        $upload = wp_handle_upload( $file, $overrides );

        if ( isset( $upload['error'] ) ) {
            return new WP_Error( 'upload_error', $upload['error'], [ 'status' => 500 ] );
        }

        update_post_meta( $id, '_font_url', $upload['url'] );
        
        $ext = strtolower(pathinfo($upload['file'], PATHINFO_EXTENSION));
        update_post_meta( $id, '_font_url_' . $ext, $upload['url'] );

        // Return updated object
        $title = get_the_title( $id );
        
        $files = [
            'ttf'   => get_post_meta( $id, '_font_url_ttf', true ),
            'otf'   => get_post_meta( $id, '_font_url_otf', true ),
            'woff'  => get_post_meta( $id, '_font_url_woff', true ),
            'woff2' => get_post_meta( $id, '_font_url_woff2', true ),
        ];

        return [
            'id'     => (int) $id,
            'title'  => $title,
            'url'    => $upload['url'],
            'files'  => $files,
            'family' => $title,
        ];
    }

    /**
     * Delete Font
     */
    public function delete_font( $request ) {
        $id = $request['id'];
        wp_delete_post( $id, true );
        return [ 'success' => true ];
    }
}
