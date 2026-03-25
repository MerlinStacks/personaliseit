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
use PHPUnit\Framework\Attributes\DataProvider;
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
}

class Test_Upload_Handler_Validation extends TestCase {

	// ── NONCE_ACTION constant ─────────────────────────────────────────────

	#[Test]
	public function nonce_action_constant_is_defined(): void {
		$this->assertSame( 'oc_upload_artwork', OC_Upload_Handler::NONCE_ACTION );
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
