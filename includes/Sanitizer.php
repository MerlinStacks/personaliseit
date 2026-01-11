<?php
/**
 * Shared Sanitizer Helper
 *
 * Why: Centralizes recursive sanitization logic previously duplicated across
 * ShareController, ConfigController, DataController, and CartIntegration.
 *
 * @package PersonaliseIt
 */

namespace PersonaliseIt;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Sanitizer utility class for data sanitization.
 */
class Sanitizer {

    /**
     * Recursively sanitize data structure.
     *
     * Handles arrays, strings, and other primitive types.
     * Strings are sanitized using sanitize_textarea_field to preserve newlines.
     *
     * @param mixed $data Data to sanitize (array, string, or primitive).
     * @return mixed Sanitized data with same structure as input.
     */
    public static function recursive( $data ) {
        if ( is_array( $data ) ) {
            foreach ( $data as $key => $value ) {
                $data[ $key ] = self::recursive( $value );
            }
            return $data;
        }
        
        if ( is_string( $data ) ) {
            return sanitize_textarea_field( $data );
        }
        
        // Booleans, integers, floats, null pass through unchanged
        return $data;
    }

    /**
     * Sanitize URL with validation.
     *
     * @param string $url URL to sanitize.
     * @return string Sanitized URL or empty string if invalid.
     */
    public static function url( $url ) {
        $url = esc_url_raw( $url );
        return filter_var( $url, FILTER_VALIDATE_URL ) ? $url : '';
    }

    /**
     * Sanitize hex color.
     *
     * @param string $color Color value.
     * @return string Sanitized hex color or fallback to sanitize_text_field.
     */
    public static function color( $color ) {
        return sanitize_hex_color( $color ) ?: sanitize_text_field( $color );
    }
}
