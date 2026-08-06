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
		if ( ! $this->is_authorised_font_request() ) {
			return $mimes;
		}
		foreach ( self::MIME_TYPES as $ext => $mime ) {
			$mimes[ $ext ] = $mime;
		}
		return $mimes;
	}

	/** Fix MIME type detection for font files (WP's finfo can misidentify them). */
	public function fix_font_mime( array $data, string $file, string $filename, ?array $mimes ): array {
		$ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		if ( $this->is_authorised_font_request() && isset( self::MIME_TYPES[ $ext ] ) && self::has_valid_font_magic( $file, $ext ) ) {
			$data['ext']  = $ext;
			$data['type'] = self::MIME_TYPES[ $ext ];
		}
		return $data;
	}

	/** Validate the container signature before WordPress accepts a font MIME override. */
	public static function has_valid_font_magic( string $file, string $ext ): bool {
		if ( ! is_file( $file ) ) return false;
		$magic = file_get_contents( $file, false, null, 0, 4 );
		if ( false === $magic || 4 !== strlen( $magic ) ) return false;
		return match ( strtolower( $ext ) ) {
			'ttf'   => in_array( $magic, [ "\x00\x01\x00\x00", 'true' ], true ),
			'otf'   => 'OTTO' === $magic,
			'woff'  => 'wOFF' === $magic,
			'woff2' => 'wOF2' === $magic,
			default => false,
		};
	}

	private function is_authorised_font_request(): bool {
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
		return wp_doing_ajax() && current_user_can( 'manage_woocommerce' ) && in_array( $action, [ 'oc_font_upload', 'oc_font_replace_print' ], true );
	}

	/** Output @font-face declarations for all active fonts. */
	public function output_font_face_css(): void {
		OC_Plugin::output_font_face_css();
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

	/** Return fonts flagged as suitable for embroidery artwork. */
	public static function get_embroidery_fonts(): array {
		global $wpdb;
		return $wpdb->get_results(
			"SELECT * FROM {$wpdb->prefix}oc_fonts WHERE active = 1 AND embroidery_suitable = 1 ORDER BY name ASC"
		) ?: [];
	}
}
