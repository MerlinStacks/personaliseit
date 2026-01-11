<?php
namespace PersonaliseIt\Api;

use WP_REST_Controller;
use WP_REST_Server;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SpotifyController extends WP_REST_Controller {

    public function __construct() {
        $this->namespace = 'personaliseit/v1';
        $this->rest_base = 'spotify';
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
     * Search Spotify via Proxy
     */
    public function search_spotify( $request ) {
        if ( ! get_option( 'personaliseit_enable_spotify' ) ) {
            return new WP_Error( 'disabled', 'Spotify integration is disabled.', [ 'status' => 403 ] );
        }

        $token = $this->get_access_token();
        if ( is_wp_error( $token ) ) {
            return $token;
        }

        $query = sanitize_text_field( $request->get_param( 'q' ) );
        $type  = sanitize_text_field( $request->get_param( 'type' ) );

        $url = add_query_arg( [
            'q'    => $query,
            'type' => $type,
            'limit'=> 20,
        ], 'https://api.spotify.com/v1/search' );

        $response = wp_remote_get( $url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
            ],
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = wp_remote_retrieve_body( $response );
        return rest_ensure_response( json_decode( $body, true ) );
    }

    /**
     * Proxy Spotify Code Image
     * Solves CORS + Allows consistent frontend usage
     * 
     * Optional params:
     * - bg: Background color (hex without #, e.g., 000000)
     * - color: Bar color (white, black, or hex)
     * - format: Image format (svg, png) - defaults to svg
     */
    public function proxy_code_image( $request ) {
        $input = sanitize_text_field( $request->get_param( 'uri' ) );
        
        // Convert Spotify URL to URI if needed
        // Supports: https://open.spotify.com/track/abc123 or https://open.spotify.com/track/abc123?si=xxx
        $uri = $input;
        if ( preg_match( '/open\.spotify\.com\/(track|album|artist|playlist)\/([a-zA-Z0-9]+)/', $input, $matches ) ) {
            $uri = 'spotify:' . $matches[1] . ':' . $matches[2];
        }
        
        // Security: Validate Spotify URI format to prevent SSRF
        // Valid formats: spotify:track:id, spotify:album:id, spotify:artist:id, spotify:playlist:id
        if ( ! preg_match( '/^spotify:(track|album|artist|playlist):[a-zA-Z0-9]+$/', $uri ) ) {
            return new WP_Error( 'invalid_uri', __( 'Invalid Spotify URI format. Provide a Spotify URL or URI.', 'personaliseit' ), [ 'status' => 400 ] );
        }
        
        // Format: spotify:track:xxxxx
        // Scannable URL: https://scannables.scdn.co/uri/plain/{format}/{bg_color}/{bar_color}/{size}/{uri}
        
        // Customizable colors with secure defaults - validate hex color format
        // Note: Spotify API only accepts hex colors, NOT 'transparent'
        $bg_param = $request->get_param( 'bg' ) ?? '';
        if ( empty( $bg_param ) || $bg_param === 'transparent' ) {
            // Default to black background - frontend handles transparency via background removal
            $bg_color = '000000';
        } elseif ( preg_match( '/^[a-fA-F0-9]{6}$/', $bg_param ) ) {
            $bg_color = $bg_param;
        } else {
            $bg_color = '000000'; // Fallback to black
        }
        
        $bar_color = $request->get_param( 'color' ) ?? '';
        // Spotify Scannable API only accepts 'white' or 'black' - NOT hex codes
        // Convert hex codes to closest match, or default to white on dark background
        if ( $bar_color === 'white' || $bar_color === 'black' ) {
            // Already valid
        } elseif ( preg_match( '/^[a-fA-F0-9]{6}$/', $bar_color ) ) {
            // Convert hex to closest color name - check if it's light or dark
            $r = hexdec( substr( $bar_color, 0, 2 ) );
            $g = hexdec( substr( $bar_color, 2, 2 ) );
            $b = hexdec( substr( $bar_color, 4, 2 ) );
            $brightness = ( $r * 299 + $g * 587 + $b * 114 ) / 1000;
            $bar_color = $brightness > 127 ? 'white' : 'black';
        } else {
            // Default: white bars on black background looks best
            $bar_color = 'white';
        }
        $size = 640;
        // SVG format for better scalability and print quality
        $format = $request->get_param( 'format' ) === 'png' ? 'png' : 'svg';

        $scannable_url = sprintf( 
            'https://scannables.scdn.co/uri/plain/%s/%s/%s/%d/%s',
            $format,
            $bg_color,
            $bar_color,
            $size,
            $uri
        );

        $response = wp_remote_get( $scannable_url );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        if ( $status_code !== 200 ) {
            $error_body = wp_remote_retrieve_body( $response );
            error_log( 'Spotify Scannable API failed: Status=' . $status_code . ', URL=' . $scannable_url . ', Body=' . substr( $error_body, 0, 500 ) );
            return new WP_Error( 
                'spotify_error', 
                sprintf( __( 'Spotify code generation failed (status %d). The Spotify Scannable API may be temporarily unavailable.', 'personaliseit' ), $status_code ),
                [ 'status' => 502, 'spotify_status' => $status_code ] 
            );
        }

        $content_type = wp_remote_retrieve_header( $response, 'content-type' );
        $body = wp_remote_retrieve_body( $response );

        // If raw=1 is passed, serve the image directly (for use in <img src> or canvas)
        $raw = $request->get_param( 'raw' );
        if ( $raw === '1' || $raw === 'true' ) {
            header( 'Content-Type: ' . $content_type );
            header( 'Cache-Control: public, max-age=86400' ); // Cache for 1 day
            echo $body;
            exit;
        }

        // Return image as proper REST response with base64 encoding
        return rest_ensure_response( [
            'data' => 'data:' . $content_type . ';base64,' . base64_encode( $body ),
            'type' => $content_type,
        ] );
    }

    /**
     * Get Client Credentials Token
     */
    private function get_access_token() {
        $client_id = get_option( 'personaliseit_spotify_client_id' );
        $client_secret = get_option( 'personaliseit_spotify_client_secret' );

        if ( empty( $client_id ) || empty( $client_secret ) ) {
            return new WP_Error( 'missing_creds', 'Spotify Credentials not configured.', [ 'status' => 500 ] );
        }

        $transient_key = 'personaliseit_spotify_token';
        $cached_token = get_transient( $transient_key );

        if ( $cached_token ) {
            return $cached_token;
        }

        $response = wp_remote_post( 'https://accounts.spotify.com/api/token', [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode( $client_id . ':' . $client_secret ),
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ],
            'body' => [
                'grant_type' => 'client_credentials',
            ],
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        
        if ( isset( $body['access_token'] ) ) {
            // Cache for slightly less than expires_in (usually 3600)
            set_transient( $transient_key, $body['access_token'], 3000 ); 
            return $body['access_token'];
        }

        return new WP_Error( 'auth_failed', 'Failed to authenticate with Spotify.', [ 'status' => 500 ] );
    }

    /**
     * Get metadata (name, artist) for a Spotify URI using oEmbed API.
     * No authentication required - uses public Spotify oEmbed endpoint.
     * Works for tracks, albums, artists, and playlists.
     */
    public function get_metadata( $request ) {
        $input = sanitize_text_field( $request->get_param( 'uri' ) );
        
        // Convert URI to URL if needed (oEmbed requires URL format)
        $url = $input;
        if ( preg_match( '/^spotify:(track|album|artist|playlist):([a-zA-Z0-9]+)$/', $input, $parts ) ) {
            $url = 'https://open.spotify.com/' . $parts[1] . '/' . $parts[2];
            $type = $parts[1];
            $id   = $parts[2];
        } elseif ( preg_match( '/open\.spotify\.com\/(track|album|artist|playlist)\/([a-zA-Z0-9]+)/', $input, $matches ) ) {
            $type = $matches[1];
            $id   = $matches[2];
            $url  = 'https://open.spotify.com/' . $type . '/' . $id;
        } else {
            return new WP_Error( 'invalid_uri', __( 'Invalid Spotify URI format.', 'personaliseit' ), [ 'status' => 400 ] );
        }
        
        // Use Spotify oEmbed API - no authentication required!
        $oembed_url = 'https://open.spotify.com/oembed?url=' . urlencode( $url );
        
        $response = wp_remote_get( $oembed_url, [
            'timeout' => 10,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ] );
        
        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'fetch_error', $response->get_error_message(), [ 'status' => 502 ] );
        }
        
        $status_code = wp_remote_retrieve_response_code( $response );
        if ( $status_code !== 200 ) {
            return new WP_Error( 'api_error', __( 'Failed to fetch Spotify metadata.', 'personaliseit' ), [ 'status' => 502 ] );
        }
        
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        
        if ( empty( $body ) || ! isset( $body['title'] ) ) {
            return new WP_Error( 'parse_error', __( 'Could not parse Spotify response.', 'personaliseit' ), [ 'status' => 502 ] );
        }
        
        // oEmbed returns title in format "Song Name · Artist Name" for tracks
        // or "Playlist Name · Owner Name" for playlists
        $title = $body['title'] ?? '';
        $name   = $title;
        $artist = '';
        
        // Try to split on " · " (Spotify's separator) or " - "
        if ( strpos( $title, ' · ' ) !== false ) {
            $parts = explode( ' · ', $title, 2 );
            $name   = trim( $parts[0] );
            $artist = isset( $parts[1] ) ? trim( $parts[1] ) : '';
        } elseif ( strpos( $title, ' - ' ) !== false ) {
            $parts = explode( ' - ', $title, 2 );
            $name   = trim( $parts[0] );
            $artist = isset( $parts[1] ) ? trim( $parts[1] ) : '';
        }
        
        return rest_ensure_response( [
            'name'        => $name,
            'artist'      => $artist,
            'type'        => $type,
            'id'          => $id,
            'thumbnail'   => $body['thumbnail_url'] ?? '',
            'provider'    => 'oembed',
        ] );
    }
}
