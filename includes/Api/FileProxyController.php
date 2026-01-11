<?php
namespace PersonaliseIt\Api;

use WP_REST_Controller;
use WP_REST_Server;
use WP_Error;
use PersonaliseIt\Services\SecurityService;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FileProxyController extends WP_REST_Controller {

    private $security_service;
    private $upload_dir;

    public function __construct() {
        $this->namespace = 'personaliseit/v1';
        $this->rest_base = 'file';
        $this->security_service = new SecurityService();

        $upload_dir_info = wp_upload_dir();
        $this->upload_dir = $upload_dir_info['basedir'] . '/personaliseit_secure/';

        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        register_rest_route( $this->namespace, '/' . $this->rest_base, [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'serve_file' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'file' => [ 'required' => true ],
                'expires' => [ 'required' => true ],
                'sig' => [ 'required' => true ],
            ],
        ] );
    }

    public function serve_file( $request ) {
        $filename = sanitize_file_name( $request->get_param( 'file' ) );
        $expiry   = intval( $request->get_param( 'expires' ) );
        $sig      = sanitize_text_field( $request->get_param( 'sig' ) );

        if ( ! $this->security_service->verify_signature( $filename, $expiry, $sig ) ) {
            return new WP_Error( 'forbidden', 'Invalid or expired signature.', [ 'status' => 403 ] );
        }

        $path = $this->upload_dir . $filename;

        if ( ! file_exists( $path ) ) {
            return new WP_Error( 'not_found', 'File not found.', [ 'status' => 404 ] );
        }

        // Serve File
        $mime = wp_check_filetype( $path )['type'];
        if ( ! $mime ) $mime = 'application/octet-stream';

        header( 'Content-Type: ' . $mime );
        header( 'Content-Length: ' . filesize( $path ) );
        header( 'Cache-Control: private, max-age=3600' );
        
        readfile( $path );
        exit;
    }
}
