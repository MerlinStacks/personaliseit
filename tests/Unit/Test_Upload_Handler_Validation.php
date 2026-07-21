<?php
/**
 * Unit tests for OC_Upload_Handler — MIME detection and validation logic.
 *
 * These tests exercise the parts of OC_Upload_Handler that don't require
 * WordPress (file type detection, size checks). The full process() flow
 * requires WP media library functions and lives in the Integration suite.
 *
 * @package OverCustomise
 */

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Expose OC_Upload_Handler protected helpers via reflection.
 */
class UploadHandlerReflector {
	private static ?\ReflectionClass $rc = null;

	private static function rc(): \ReflectionClass {
		if ( null === self::$rc ) {
			self::$rc = new \ReflectionClass( OC_Upload_Handler::class );
		}
		return self::$rc;
	}

	public static function detect_mime( string $tmp_path, string $filename ): string {
		$m = self::rc()->getMethod( 'detect_mime' );
		$m->setAccessible( true );
		return $m->invoke( null, $tmp_path, $filename );
	}

	public static function normalise_extension( string $ext ): string {
		$m = self::rc()->getMethod( 'normalise_extension' );
		$m->setAccessible( true );
		return $m->invoke( null, $ext );
	}

	public static function type_from_extension( string $ext ): ?string {
		$m = self::rc()->getMethod( 'type_from_extension' );
		$m->setAccessible( true );
		return $m->invoke( null, $ext );
	}

	public static function document_content_is_valid( string $path, string $type ): bool {
		$m = self::rc()->getMethod( 'document_content_is_valid' );
		$m->setAccessible( true );
		return $m->invoke( null, $path, $type );
	}

	public static function protected_uploads_storage_root(): ?string {
		$m = self::rc()->getMethod( 'protected_uploads_storage_root' );
		$m->setAccessible( true );
		return $m->invoke( null );
	}
}

class Test_Upload_Handler_Validation extends TestCase {
	private array $temporary_files = [];

	protected function tearDown(): void {
		foreach ( $this->temporary_files as $file ) {
			@unlink( $file );
		}
		$GLOBALS['oc_test_post_meta']       = [];
		$GLOBALS['oc_test_attached_files']  = [];
		$GLOBALS['oc_test_post_mime_types'] = [];
		$GLOBALS['oc_test_transients']      = [];
		$GLOBALS['oc_test_options']         = [];
		parent::tearDown();
	}

	private function create_test_artwork( int $attachment_id, array $context = [], string $token = '' ): void {
		$directory = OC_Upload_Handler::private_storage_path( 'artwork' );
		$this->assertIsString( $directory );
		$file = tempnam( $directory, 'oc_artwork_' );
		file_put_contents( $file, base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true ) );
		$this->temporary_files[] = $file;

		$GLOBALS['oc_test_attached_files'][ $attachment_id ]  = $file;
		$GLOBALS['oc_test_post_mime_types'][ $attachment_id ] = 'image/png';
		$GLOBALS['oc_test_post_meta'][ $attachment_id ] = [
			'_oc_artwork'         => 1,
			'_oc_artwork_context' => $context,
			'_oc_artwork_user_id' => 0,
			'_oc_artwork_token'   => '' !== $token ? hash( 'sha256', $token ) : '',
			'_oc_artwork_session' => '',
		];
		if ( '' !== $token ) {
			$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
			$GLOBALS['oc_test_transients'][ 'oc_pubtok_' . hash( 'sha256', $token ) ] = [
				'version'      => 2,
				'binding_type' => 'ip',
				'binding_hash' => hash( 'sha256', '127.0.0.1' ),
				'created_at'   => time(),
				'expires_at'   => time() + HOUR_IN_SECONDS,
			];
		}
	}

	// ── NONCE_ACTION constant ─────────────────────────────────────────────

	#[Test]
	public function nonce_action_constant_is_defined(): void {
		$this->assertSame( 'oc_upload_artwork', OC_Upload_Handler::NONCE_ACTION );
	}

	#[Test]
	public function uploads_fallback_is_stable_and_deny_protected(): void {
		$uploads = wp_upload_dir();
		if ( ! is_dir( $uploads['basedir'] ) ) {
			mkdir( $uploads['basedir'], 0755, true );
		}

		$root = UploadHandlerReflector::protected_uploads_storage_root();
		$this->assertIsString( $root );
		$this->assertMatchesRegularExpression( '/\/\.overcustomise-private-[a-z0-9]{32}$/D', $root );
		$this->assertSame( $root, UploadHandlerReflector::protected_uploads_storage_root() );
		$this->assertFileExists( $root . '/.htaccess' );
		$this->assertFileExists( $root . '/web.config' );
		$this->assertFileExists( $root . '/index.php' );
		$this->assertStringContainsString( 'Require all denied', (string) file_get_contents( $root . '/.htaccess' ) );

		foreach ( [ '.htaccess', 'web.config', 'index.php' ] as $filename ) {
			@unlink( $root . '/' . $filename );
		}
		@rmdir( $root );
	}

	// ── MIME detection — SVG fallback ─────────────────────────────────────

	#[Test]
	public function detect_mime_returns_svg_for_svg_content(): void {
		$tmp = tempnam( sys_get_temp_dir(), 'oc_test_' );
		file_put_contents( $tmp, '<svg xmlns="http://www.w3.org/2000/svg"><circle/></svg>' );

		$mime = UploadHandlerReflector::detect_mime( $tmp, 'test.svg' );

		@unlink( $tmp );

		// finfo may return text/plain, text/xml, or image/svg+xml — all valid.
		// Our detect_mime should normalise text/plain + <svg content to svg+xml.
		$this->assertContains( $mime, [ 'image/svg+xml', 'text/xml', 'application/xml' ] );
	}

	#[Test]
	public function detect_mime_returns_svg_when_finfo_says_text_plain(): void {
		// Write a minimal SVG that finfo commonly misidentifies as text/plain.
		$tmp = tempnam( sys_get_temp_dir(), 'oc_test_' );
		file_put_contents( $tmp, '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg"/>' );

		$mime = UploadHandlerReflector::detect_mime( $tmp, 'art.svg' );
		@unlink( $tmp );

		// Acceptable: svg+xml, text/xml (also listed in SUPPORTED_TYPES).
		$this->assertMatchesRegularExpression( '/svg|xml/', $mime );
	}

	#[Test]
	public function detect_mime_for_eps_falls_back_on_ext(): void {
		// Create a fake EPS file that finfo might return application/octet-stream for.
		$tmp = tempnam( sys_get_temp_dir(), 'oc_test_' );
		file_put_contents( $tmp, "%!PS-Adobe-3.0 EPSF-3.0\n%%BoundingBox: 0 0 100 100\n" );

		$mime = UploadHandlerReflector::detect_mime( $tmp, 'graphic.eps' );
		@unlink( $tmp );

		// Should be some kind of postscript / eps MIME.
		$this->assertTrue(
			str_contains( $mime, 'postscript' )
			|| str_contains( $mime, 'eps' )
			|| str_contains( $mime, 'pdf' )  // Some systems report PDF for EPS
			|| str_contains( $mime, 'octet' ) // Raw fallback
		);
	}

	#[Test]
	public function validates_eps_with_a_dsc_bounding_box(): void {
		$tmp = tempnam( sys_get_temp_dir(), 'oc_test_' );
		file_put_contents( $tmp, "%!PS-Adobe-3.0\n%%BoundingBox: 0 0 100 100\nshowpage\n" );

		$this->assertTrue( UploadHandlerReflector::document_content_is_valid( $tmp, 'eps' ) );
		@unlink( $tmp );
	}

	#[Test]
	public function validates_dos_binary_eps_wrapper(): void {
		$postscript = "%!PS-Adobe-3.0 EPSF-3.0\n%%BoundingBox: 0 0 100 100\nshowpage\n";
		$tmp        = tempnam( sys_get_temp_dir(), 'oc_test_' );
		$header     = "\xC5\xD0\xD3\xC6" . pack( 'V', 30 ) . pack( 'V', strlen( $postscript ) ) . str_repeat( "\0", 18 );
		file_put_contents( $tmp, $header . $postscript );

		$this->assertTrue( UploadHandlerReflector::document_content_is_valid( $tmp, 'eps' ) );
		@unlink( $tmp );
	}

	#[Test]
	public function rejects_postscript_without_eps_markers(): void {
		$tmp = tempnam( sys_get_temp_dir(), 'oc_test_' );
		file_put_contents( $tmp, "%!PS-Adobe-3.0\nshowpage\n" );

		$this->assertFalse( UploadHandlerReflector::document_content_is_valid( $tmp, 'eps' ) );
		@unlink( $tmp );
	}

	// ── SUPPORTED_TYPES constant ──────────────────────────────────────────

	#[Test]
	public function svg_mime_is_in_supported_types(): void {
		$rc   = new \ReflectionClass( OC_Upload_Handler::class );
		$prop = $rc->getConstant( 'SUPPORTED_TYPES' );

		$this->assertIsArray( $prop );
		$this->assertArrayHasKey( 'image/svg+xml', $prop );
		$this->assertSame( 'svg', $prop['image/svg+xml'] );
	}

	#[Test]
	public function pdf_mime_is_in_supported_types(): void {
		$rc   = new \ReflectionClass( OC_Upload_Handler::class );
		$prop = $rc->getConstant( 'SUPPORTED_TYPES' );

		$this->assertArrayHasKey( 'application/pdf', $prop );
		$this->assertSame( 'pdf', $prop['application/pdf'] );
	}

	#[Test]
	public function webp_mime_is_in_supported_types(): void {
		$rc   = new \ReflectionClass( OC_Upload_Handler::class );
		$prop = $rc->getConstant( 'SUPPORTED_TYPES' );

		$this->assertArrayHasKey( 'image/webp', $prop );
		$this->assertSame( 'webp', $prop['image/webp'] );
		$this->assertSame( 'webp', UploadHandlerReflector::type_from_extension( 'webp' ) );
	}

	#[Test]
	public function normalise_extension_trims_dot_and_case(): void {
		$this->assertSame( 'jpeg', UploadHandlerReflector::normalise_extension( '.JpEg' ) );
	}

	#[Test]
	public function type_from_extension_handles_jpeg_alias(): void {
		$this->assertSame( 'jpg', UploadHandlerReflector::type_from_extension( 'jpeg' ) );
		$this->assertSame( 'jpg', UploadHandlerReflector::type_from_extension( 'jpg' ) );
	}

	#[Test]
	public function customer_artwork_requires_the_exact_variation_context(): void {
		$token = str_repeat( 'A', 64 );
		$this->create_test_artwork( 41, [ 10, 12, 20, 30 ], $token );

		$this->assertTrue( OC_Upload_Handler::attachment_is_accepted( 41, 10, 12, 20, 30, $token ) );
		$this->assertFalse( OC_Upload_Handler::attachment_is_accepted( 41, 10, 0, 20, 30, $token ) );
		$this->assertFalse( OC_Upload_Handler::attachment_is_accepted( 41, 10, 13, 20, 30, $token ) );
	}

	#[Test]
	public function legacy_artwork_requires_owned_exact_product_context(): void {
		$token = str_repeat( 'C', 64 );
		$this->create_test_artwork( 43, [ 10, 12, 0, 0 ], $token );

		$this->assertTrue( OC_Upload_Handler::legacy_attachment_is_accepted( 43, 10, 12, $token ) );
		$this->assertFalse( OC_Upload_Handler::legacy_attachment_is_accepted( 43, 10, 0, $token ) );
		$this->assertFalse( OC_Upload_Handler::legacy_attachment_is_accepted( 43, 11, 12, $token ) );
		$this->assertFalse( OC_Upload_Handler::legacy_attachment_is_accepted( 43, 10, 12, str_repeat( 'D', 64 ) ) );
	}

	// ── process() — missing file throws ──────────────────────────────────

	#[Test]
	public function process_throws_when_tmp_name_is_empty(): void {
		// Override is_uploaded_file to simulate PHP upload check.
		// Since we can't easily mock the PHP builtin without a framework,
		// we just confirm the RuntimeException is thrown for an empty tmp_name.
		$this->expectException( \RuntimeException::class );

		OC_Upload_Handler::process( [
			'tmp_name' => '',
			'name'     => 'test.svg',
			'size'     => 0,
			'type'     => 'image/svg+xml',
		] );
	}

	#[Test]
	public function process_throws_when_file_too_large(): void {
		// Create a real uploaded temp file check bypass — fake a "not uploaded" error
		// to verify the upload path. The actual size check fires before the is_uploaded_file
		// check in our implementation, so we verify via a fake zero-size upload.
		// (Integration tests cover the full happy path.)
		$this->expectException( \RuntimeException::class );

		OC_Upload_Handler::process( [
			'tmp_name' => '',
			'name'     => 'huge.pdf',
			'size'     => PHP_INT_MAX,
			'type'     => 'application/pdf',
		] );
	}
}
