<?php
namespace PersonaliseIt\Services;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class SpotifyService
 * Handles all Spotify API interactions.
 */
class SpotifyService {

    /**
     * Get Client Credentials Token
     * 
     * @return string|WP_Error Access Token or Error
     */
    public function get_access_token() {
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
     * Search Spotify
     * 
     * @param string $query
     * @param string $type
     * @return array|WP_Error
     */
    public function search( $query, $type = 'track,album,artist' ) {
        if ( ! get_option( 'personaliseit_enable_spotify' ) ) {
            return new WP_Error( 'disabled', 'Spotify integration is disabled.', [ 'status' => 403 ] );
        }

        $token = $this->get_access_token();
        if ( is_wp_error( $token ) ) {
            return $token;
        }

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
        return json_decode( $body, true );
    }

    /**
     * Get Scannable Code Image URL
     * 
     * @param string $uri Spotify URI
     * @param string $bg_color Hex
     * @param string $bar_color 'white' or 'black'
     * @param string $format 'svg' or 'png'
     * @return string
     */
    public function get_scannable_url( $uri, $bg_color = '000000', $bar_color = 'white', $format = 'svg' ) {
        $size = 640;
        return sprintf( 
            'https://scannables.scdn.co/uri/plain/%s/%s/%s/%d/%s',
            $format,
            $bg_color,
            $bar_color,
            $size,
            $uri
        );
    }

    /**
     * Fetch Proxy Image
     * 
     * @param string $url
     * @return array|WP_Error
     */
    public function fetch_proxy_image( $url ) {
        $response = wp_remote_get( $url );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        if ( $status_code !== 200 ) {
            $error_body = wp_remote_retrieve_body( $response );
            error_log( 'Spotify Scannable API failed: Status=' . $status_code . ', URL=' . $url . ', Body=' . substr( $error_body, 0, 500 ) );
            return new WP_Error( 
                'spotify_error', 
                sprintf( __( 'Spotify code generation failed (status %d).', 'personaliseit' ), $status_code ),
                [ 'status' => 502, 'spotify_status' => $status_code ] 
            );
        }

        return [
            'content_type' => wp_remote_retrieve_header( $response, 'content-type' ),
            'body'         => wp_remote_retrieve_body( $response )
        ];
    }

    /**
     * Get Metadata via OEmbed
     * 
     * @param string $uri
     * @return array|WP_Error
     */
    public function get_metadata( $uri ) {
        // Validation moved to caller or handled here? Handled here is safe.
        $url = $uri;
        $type = '';
        $id = '';
        
        if ( preg_match( '/^spotify:(track|album|artist|playlist):([a-zA-Z0-9]+)$/', $uri, $parts ) ) {
            $url = 'https://open.spotify.com/' . $parts[1] . '/' . $parts[2];
            $type = $parts[1];
            $id   = $parts[2];
        } elseif ( preg_match( '/open\.spotify\.com\/(track|album|artist|playlist)\/([a-zA-Z0-9]+)/', $uri, $matches ) ) {
            $type = $matches[1];
            $id   = $matches[2];
            $url  = 'https://open.spotify.com/' . $type . '/' . $id;
        } else {
            return new WP_Error( 'invalid_uri', __( 'Invalid Spotify URI format.', 'personaliseit' ), [ 'status' => 400 ] );
        }
        
        $oembed_url = 'https://open.spotify.com/oembed?url=' . urlencode( $url );
        
        $response = wp_remote_get( $oembed_url, [
            'timeout' => 10,
            'headers' => [ 'Accept' => 'application/json' ],
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
        
        $title = $body['title'] ?? '';
        $name   = $title;
        $artist = '';
        
        if ( strpos( $title, ' · ' ) !== false ) {
            $parts = explode( ' · ', $title, 2 );
            $name   = trim( $parts[0] );
            $artist = isset( $parts[1] ) ? trim( $parts[1] ) : '';
        } elseif ( strpos( $title, ' - ' ) !== false ) {
            $parts = explode( ' - ', $title, 2 );
            $name   = trim( $parts[0] );
            $artist = isset( $parts[1] ) ? trim( $parts[1] ) : '';
        }
        
        return [
            'name'        => $name,
            'artist'      => $artist,
            'type'        => $type,
            'id'          => $id,
            'thumbnail'   => $body['thumbnail_url'] ?? '',
            'provider'    => 'oembed',
        ];
    }
}
