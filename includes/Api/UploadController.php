<?php
namespace PersonaliseIt\Api;

use PersonaliseIt\Services\SecurityService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class UploadController {
    
    private $security_service;

    public function __construct() {
        $this->security_service = new SecurityService();
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes() {
        register_rest_route('personaliseit/v1', '/upload', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_upload'],
            'permission_callback' => [$this, 'check_upload_permissions'],
        ]);
    }

    public function check_upload_permissions( $request ) {
        // 1. Allow if user is logged in and can upload files
        if ( current_user_can( 'upload_files' ) ) {
            return true;
        }

        // 2. For frontend users (guests included), verify the nonce
        $nonce = $request->get_header( 'x_wp_nonce' );
        if ( wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return true;
        }

        return new \WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to do that.', 'personaliseit' ), [ 'status' => 401 ] );
    }

    public function handle_upload($request) {
        $files = $request->get_file_params();
        if (empty($files['file'])) {
            return new \WP_Error('no_file', 'No file uploaded', ['status' => 400]);
        }

        $file = $files['file'];
        
        // Check size
        $max_size_mb = get_option('personaliseit_max_upload_size', 5);
        if ($file['size'] > $max_size_mb * 1024 * 1024) {
            return new \WP_Error('file_too_large', 'File exceeds maximum size limit', ['status' => 400]);
        }

        if ( ! function_exists( 'wp_handle_upload' ) ) {
            require_once( ABSPATH . 'wp-admin/includes/image.php' );
            require_once( ABSPATH . 'wp-admin/includes/file.php' );
            require_once( ABSPATH . 'wp-admin/includes/media.php' );
        }

        // Add filter to change upload dir
        add_filter( 'upload_dir', [ $this, 'custom_upload_dir' ] );

        $upload_overrides = [
            'test_form' => false,
            'mimes' => [
                'jpg|jpeg|jpe' => 'image/jpeg',
                'png'          => 'image/png',
                'gif'          => 'image/gif',
                'webp'         => 'image/webp',
            ]
        ];
        
        $movefile = wp_handle_upload($file, $upload_overrides);

        // Remove filter immediately
        remove_filter( 'upload_dir', [ $this, 'custom_upload_dir' ] );

        if ($movefile && !isset($movefile['error'])) {
            // Success
            // Generate Signed URL
            $url = $this->security_service->generate_signed_url( $movefile['file'] );

            return rest_ensure_response([
                'url' => $url, // Signed URL
                'file' => $movefile['file'], // Absolute path (internal use)
                'type' => $movefile['type']
            ]);
        } else {
            return new \WP_Error('upload_error', $movefile['error'], ['status' => 500]);
        }
    }

    /**
     * Filter upload directory to secure folder
     */
    public function custom_upload_dir( $uploads ) {
        $subdir = '/personaliseit_secure';
        $uploads['subdir'] = $subdir;
        $uploads['path'] = $uploads['basedir'] . $subdir;
        $uploads['url']  = $uploads['baseurl'] . $subdir; // Note: This URL is blocked by .htaccess, but WP needs a value
        
        // Auto-provision .htaccess protection
        if ( ! file_exists( $uploads['path'] ) ) {
            wp_mkdir_p( $uploads['path'] );
        }
        
        $htaccess_path = $uploads['path'] . '/.htaccess';
        if ( file_exists( $uploads['path'] ) && ! file_exists( $htaccess_path ) ) {
            file_put_contents( $htaccess_path, "Order Deny,Allow\nDeny from all" );
        }

        return $uploads;
    }
}
