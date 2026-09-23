<?php
/**
 * Literal printable text normalization, independent of HTML sanitization.
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

class OC_Print_Text {

	/**
	 * Normalize customer text without interpreting markup, entities or URL escapes.
	 *
	 * Output remains plain data; callers must escape it for its display context.
	 * Invalid UTF-8 and non-scalar input are rejected as empty text.
	 *
	 * @param mixed $value     Printable scalar value.
	 * @param bool  $multiline Preserve internal newlines and horizontal whitespace.
	 * @return string
	 */
	public static function normalise( mixed $value, bool $multiline = false ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		$text = (string) $value;
		if ( function_exists( 'wp_check_invalid_utf8' ) ) {
			$text = wp_check_invalid_utf8( $text );
		}
		// Also validate when WordPress uses a non-UTF-8 site charset or is absent.
		if ( 1 !== preg_match( '//u', $text ) ) {
			return '';
		}
		$text = str_replace( [ "\r\n", "\r" ], "\n", $text );
		// Remove C0/C1 controls except tab and LF; retain Unicode format characters.
		$text = preg_replace( '/[\x{0000}-\x{0008}\x{000B}\x{000C}\x{000E}-\x{001F}\x{007F}-\x{009F}]/u', '', $text ) ?? '';
		if ( ! $multiline ) {
			$text = preg_replace( '/[\n\t ]+/', ' ', $text ) ?? '';
		}
		return trim( $text );
	}
}
