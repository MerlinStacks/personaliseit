<?php
namespace PersonaliseIt\Api;

use WP_REST_Controller;
use WP_REST_Server;
use WP_Error;
use PersonaliseIt\Services\SpotifyService;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SpotifyController extends WP_REST_Controller {

    private $service;

    public function __construct() {
        $this->namespace = 'personaliseit/v1';
        $this->rest_base = 'spotify';
        $this->service = new SpotifyService();
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/search', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'search_spotify' ],
            'permission_callback' => '__return_true', // Public for frontend users
            'args'                => [
                'q' => [
                    'required' => true,
                    'type'     => 'string',
                ],
                'type' => [
                    'required' => false,
                    'default'  => 'track,album,artist',
                ]
            ],
        ] );

        register_rest_route( $this->namespace, '/' . $this->rest_base . '/code', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'proxy_code_image' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'uri' => [
                    'required' => true,
                    'type'     => 'string',
                ]
            ],
        ] );

        // Metadata endpoint - fetches actual song/artist/playlist names
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/metadata', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'get_metadata' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'uri' => [
                    'required' => true,
                    'type'     => 'string',
                ]
            ],
        ] );
    }

    /**
     * Search Spotify via Service
     */
    public function search_spotify( $request ) {
        $query = sanitize_text_field( $request->get_param( 'q' ) );
        $type  = sanitize_text_field( $request->get_param( 'type' ) );

        $result = $this->service->search( $query, $type );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return rest_ensure_response( $result );
    }

    /**
     * Proxy Spotify Code Image
     */
    public function proxy_code_image( $request ) {
        $input = sanitize_text_field( $request->get_param( 'uri' ) );
        
        $uri = $input;
        if ( preg_match( '/open\.spotify\.com\/(track|album|artist|playlist)\/([a-zA-Z0-9]+)/', $input, $matches ) ) {
            $uri = 'spotify:' . $matches[1] . ':' . $matches[2];
        }
        
        if ( ! preg_match( '/^spotify:(track|album|artist|playlist):[a-zA-Z0-9]+$/', $uri ) ) {
            return new WP_Error( 'invalid_uri', __( 'Invalid Spotify URI format. Provide a Spotify URL or URI.', 'personaliseit' ), [ 'status' => 400 ] );
        }
        
        // Colors
        $bg_param = $request->get_param( 'bg' ) ?? '';
        if ( empty( $bg_param ) || $bg_param === 'transparent' ) {
            $bg_color = '000000';
        } elseif ( preg_match( '/^[a-fA-F0-9]{6}$/', $bg_param ) ) {
            $bg_color = $bg_param;
        } else {
            $bg_color = '000000';
        }
        
        $bar_color = $request->get_param( 'color' ) ?? '';
        if ( $bar_color === 'white' || $bar_color === 'black' ) {
            // ok
        } elseif ( preg_match( '/^[a-fA-F0-9]{6}$/', $bar_color ) ) {
            $r = hexdec( substr( $bar_color, 0, 2 ) );
            $g = hexdec( substr( $bar_color, 2, 2 ) );
            $b = hexdec( substr( $bar_color, 4, 2 ) );
            $brightness = ( $r * 299 + $g * 587 + $b * 114 ) / 1000;
            $bar_color = $brightness > 127 ? 'white' : 'black';
        } else {
            $bar_color = 'white';
        }
        
        $format = $request->get_param( 'format' ) === 'png' ? 'png' : 'svg';

        $scannable_url = $this->service->get_scannable_url( $uri, $bg_color, $bar_color, $format );
        $result = $this->service->fetch_proxy_image( $scannable_url );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        // Raw output support
        $raw = $request->get_param( 'raw' );
        if ( $raw === '1' || $raw === 'true' ) {
            header( 'Content-Type: ' . $result['content_type'] );
            header( 'Cache-Control: public, max-age=86400' );
            echo $result['body'];
            exit;
        }

        return rest_ensure_response( [
            'data' => 'data:' . $result['content_type'] . ';base64,' . base64_encode( $result['body'] ),
            'type' => $result['content_type'],
        ] );
    }

    /**
     * Get Metadata
     */
    public function get_metadata( $request ) {
        $input = sanitize_text_field( $request->get_param( 'uri' ) );
        $result = $this->service->get_metadata( $input );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return rest_ensure_response( $result );
    }
}
