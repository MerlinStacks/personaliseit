<?php
/**
 * Font Registry — manages @font-face CSS generation for admin and frontend,
 * MIME type registration, and provides font data to JavaScript.
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

class OC_Font_Registry {

	/** Allowed font MIME types. */
	private const MIME_TYPES = [
		'ttf'   => 'font/ttf',
		'otf'   => 'font/otf',
		'woff'  => 'font/woff',
		'woff2' => 'font/woff2',
	];

	public function register(): void {
		// Allow font uploads in WP media.
		add_filter( 'upload_mimes', [ $this, 'allow_font_mimes' ] );
		add_filter( 'wp_check_filetype_and_ext', [ $this, 'fix_font_mime' ], 10, 4 );

		// Inject @font-face CSS on frontend and admin.
		add_action( 'wp_head', [ $this, 'output_font_face_css' ], 5 );
		add_action( 'admin_head', [ $this, 'output_font_face_css' ], 5 );
	}

	/** Add font MIME types to WordPress allowed uploads. */
	public function allow_font_mimes( array $mimes ): array {
		foreach ( self::MIME_TYPES as $ext => $mime ) {
			$mimes[ $ext ] = $mime;
		}
		return $mimes;
	}

	/** Fix MIME type detection for font files (WP's finfo can misidentify them). */
	public function fix_font_mime( array $data, string $file, string $filename, ?array $mimes ): array {
		$ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		if ( isset( self::MIME_TYPES[ $ext ] ) ) {
			$data['ext']  = $ext;
			$data['type'] = self::MIME_TYPES[ $ext ];
		}
		return $data;
	}

	/** Output @font-face declarations for all active fonts. */
	public function output_font_face_css(): void {
		$fonts = OC_DB::get_fonts( true );
		if ( empty( $fonts ) ) {
			return;
		}

		$upload = wp_upload_dir();
		if ( ! empty( $upload['error'] ) ) {
			return;
		}
		$output = '';

		foreach ( $fonts as $font ) {
			$rel = ltrim( (string) $font->file_path, '/' );
			if ( '' === $rel ) {
				continue;
			}
			// Block paths that try to escape the uploads directory.
			if ( str_contains( $rel, '..' ) ) {
				continue;
			}
			$abs = $upload['basedir'] . '/' . $rel;
			if ( ! file_exists( $abs ) ) {
				continue;
			}

			$url    = esc_url( $upload['baseurl'] . '/' . $rel );
			$format = $this->get_font_format( $rel );
			$output .= sprintf(
				"@font-face {\n\tfont-family: '%s';\n\tsrc: url('%s') format('%s');\n\tfont-weight: %s;\n\tfont-style: %s;\n\tfont-display: swap;\n}\n",
				esc_js( $font->name ),
				$url,
				esc_attr( $format ),
				esc_attr( $font->weight ),
				esc_attr( $font->style )
			);
		}

		if ( '' === $output ) {
			return;
		}

		echo "\n<style id=\"oc-font-faces\">\n" . $output . "</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/** Return CSS format string for a font file path. */
	private function get_font_format( string $file_path ): string {
		$ext = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
		return match ( $ext ) {
			'woff2' => 'woff2',
			'woff'  => 'woff',
			'otf'   => 'opentype',
			default => 'truetype',
		};
	}

	/**
	 * Return all active fonts as a structured array for JavaScript.
	 * Used by the Fabric.js font selector and the admin font picker.
	 */
	public static function get_fonts_for_js(): array {
		$fonts  = OC_DB::get_fonts( true );
		$upload = wp_upload_dir();
		if ( ! empty( $upload['error'] ) ) {
			return [];
		}
		$result = [];

		foreach ( $fonts as $font ) {
			if ( empty( $font->file_path ) ) {
				continue;
			}
			$result[] = [
				'id'                => (int) $font->id,
				'name'              => $font->name,
				'url'               => $upload['baseurl'] . '/' . ltrim( $font->file_path, '/' ),
				'weight'            => $font->weight,
				'style'             => $font->style,
				'embroidery'        => (bool) $font->embroidery_suitable,
			];
		}

		return $result;
	}

	/**
	 * Return fonts suitable for Tier 1 embroidery auto-DST only.
	 */
	public static function get_embroidery_fonts(): array {
		global $wpdb;
		return $wpdb->get_results(
			"SELECT * FROM {$wpdb->prefix}oc_fonts WHERE active = 1 AND embroidery_suitable = 1 ORDER BY name ASC"
		) ?: [];
	}
}
