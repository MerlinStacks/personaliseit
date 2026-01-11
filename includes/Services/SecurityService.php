<?php
namespace PersonaliseIt\Services;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SecurityService {

    private $secret_key;

    public function __construct() {
        // Use AUTH_KEY or a specific option
        $this->secret_key = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'personaliseit_secret_fallback';
    }

    /**
     * Generate Signed URL for a file
     * 
     * @param string $file_path Absolute path or relative to uploads
     * @param int $ttl Seconds until expiry (default 1 hour)
     * @return string
     */
    public function generate_signed_url( $file_path, $ttl = 3600 ) {
        $filename = basename( $file_path );
        $expiry = time() + $ttl;
        
        // Data to sign
        $data = $filename . '|' . $expiry;
        $signature = hash_hmac( 'sha256', $data, $this->secret_key );

        return add_query_arg( [
            'file' => $filename,
            'expires' => $expiry,
            'sig' => $signature
        ], rest_url( 'personaliseit/v1/file' ) );
    }

    /**
     * Verify Token
     * 
     * @param string $filename
     * @param int $expiry
     * @param string $signature
     * @return bool
     */
    public function verify_signature( $filename, $expiry, $signature ) {
        if ( time() > $expiry ) {
            return false;
        }

        $data = $filename . '|' . $expiry;
        $expected = hash_hmac( 'sha256', $data, $this->secret_key );

        return hash_equals( $expected, $signature );
    }
}
