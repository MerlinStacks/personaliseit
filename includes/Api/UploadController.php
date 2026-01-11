<?php
namespace PersonaliseIt\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class UploadController {
    public function __construct() {
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
        
        // Check size limit from settings
        $max_size_mb = get_option('personaliseit_max_upload_size', 5);
        if ($file['size'] > $max_size_mb * 1024 * 1024) {
            return new \WP_Error('file_too_large', 'File exceeds maximum size limit', ['status' => 400]);
        }

        // Use core WP upload handler setup
        if ( ! function_exists( 'wp_handle_upload' ) ) {
            require_once( ABSPATH . 'wp-admin/includes/image.php' );
            require_once( ABSPATH . 'wp-admin/includes/file.php' );
            require_once( ABSPATH . 'wp-admin/includes/media.php' );
        }

        // Deep validation of file content
        $uploaded_file_path = $file['tmp_name'];
        
        // 1. Check real MIME type
        $finfo = finfo_open( FILEINFO_MIME_TYPE );
        $mime_type = finfo_file( $finfo, $uploaded_file_path );
        finfo_close( $finfo );

        $allowed_mimes = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp'
        ];

        if ( ! in_array( $mime_type, $allowed_mimes, true ) ) {
            return new \WP_Error( 'invalid_file_type', 'Invalid file type detected.', [ 'status' => 400 ] );
        }

        // 2. Check for image validity (headers etc)
        $image_size = getimagesize( $uploaded_file_path );
        if ( $image_size === false ) {
             return new \WP_Error( 'invalid_image', 'File is not a valid image.', [ 'status' => 400 ] );
        }

        // 'test_form' => false because we are not submitting a standard POST form with nonce check here (handled via REST)
        $upload_overrides = [
            'test_form' => false,
            'mimes' => [
                'jpg|jpeg|jpe' => 'image/jpeg',
                'png'          => 'image/png',
                'gif'          => 'image/gif',
                'webp'         => 'image/webp',
            ]
        ];
        
        // Handle the upload
        $movefile = wp_handle_upload($file, $upload_overrides);

        if ($movefile && !isset($movefile['error'])) {
            // Success
            return rest_ensure_response([
                'url' => $movefile['url'],
                'file' => $movefile['file'],
                'type' => $movefile['type']
            ]);
        } else {
            return new \WP_Error('upload_error', $movefile['error'], ['status' => 500]);
        }
    }
}
