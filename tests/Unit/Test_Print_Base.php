<?php
/**
 * Unit tests for OC_Print_Base utility methods.
 *
 * These are pure-PHP calculations, no WP or TCPDF required.
 *
 * @package OverCustomise
 */

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * Concrete subclass to expose the protected static methods for testing.
 */
class OC_Print_Base_Testable extends OC_Print_Base {
	public static function test_px_to_mm( int $px ): float {
		return self::px_to_mm( $px );
	}

	public static function test_hex_to_cmyk( string $hex ): array {
		return self::hex_to_cmyk( $hex );
	}

	public static function test_hex_to_rgb( string $hex ): array {
		return self::hex_to_rgb( $hex );
	}

	public static function test_cell_h( float $size ): float {
		return self::cell_h( $size );
	}

	public static function test_build_filename( int $item_id, string $area_key, string $ext ): string {
		return self::build_filename( $item_id, $area_key, $ext );
	}

	public static function test_extract_spotify_uri( string $input ): string {
		return self::extract_spotify_uri( $input );
	}

	public static function test_build_spotify_code_url( string $input, bool $engraving = false ): string {
		return self::build_spotify_code_url( $input, $engraving );
	}

	public static function test_resolve_artwork_path( array $area_data ): ?string {
		return self::resolve_artwork_path( $area_data );
	}

	public static function test_normalise_engraving_text( string $text ): string {
		return self::normalise_engraving_text( $text );
	}
}

class Test_Print_Base extends TestCase {

	// ── px_to_mm ──────────────────────────────────────────────────────────

	/** @return array<array{int, float}> */
	public static function px_to_mm_provider(): array {
		return [
			'zero'        => [ 0,    0.0 ],
			'1 inch'      => [ 300,  25.4 ],
			'half inch'   => [ 150,  12.7 ],
			'100mm'       => [ 1181, 100.0 ],   // ~100mm at 300 DPI
		];
	}

	#[Test]
	#[DataProvider( 'px_to_mm_provider' )]
	public function it_converts_pixels_to_mm( int $px, float $expected_mm ): void {
		$result = OC_Print_Base_Testable::test_px_to_mm( $px );
		$this->assertEqualsWithDelta( $expected_mm, $result, 0.1 );
	}

	#[Test]
	public function px_to_mm_uses_300_dpi(): void {
		// 300 px at 300 DPI = 1 inch = 25.4 mm.
		$this->assertEqualsWithDelta( 25.4, OC_Print_Base_Testable::test_px_to_mm( 300 ), 0.001 );
	}

	// ── hex_to_rgb ────────────────────────────────────────────────────────

	/** @return array<array{string, array{int,int,int}}> */
	public static function hex_to_rgb_provider(): array {
		return [
			'black'      => [ '#000000', [ 0,   0,   0   ] ],
			'white'      => [ '#ffffff', [ 255, 255, 255 ] ],
			'red'        => [ '#ff0000', [ 255, 0,   0   ] ],
			'blue'       => [ '#0000ff', [ 0,   0,   255 ] ],
			'no hash'    => [ 'ff0000',  [ 255, 0,   0   ] ],
			'shorthand'  => [ '#f00',    [ 255, 0,   0   ] ],
		];
	}

	#[Test]
	#[DataProvider( 'hex_to_rgb_provider' )]
	public function it_converts_hex_to_rgb( string $hex, array $expected ): void {
		$result = OC_Print_Base_Testable::test_hex_to_rgb( $hex );
		$this->assertSame( $expected, $result );
	}

	// ── hex_to_cmyk ───────────────────────────────────────────────────────

	#[Test]
	public function black_is_100_percent_key(): void {
		$cmyk = OC_Print_Base_Testable::test_hex_to_cmyk( '#000000' );
		$this->assertEqualsWithDelta( 0.0,   $cmyk[0], 0.1 ); // C
		$this->assertEqualsWithDelta( 0.0,   $cmyk[1], 0.1 ); // M
		$this->assertEqualsWithDelta( 0.0,   $cmyk[2], 0.1 ); // Y
		$this->assertEqualsWithDelta( 100.0, $cmyk[3], 0.1 ); // K
	}

	#[Test]
	public function white_is_all_zero_cmyk(): void {
		$cmyk = OC_Print_Base_Testable::test_hex_to_cmyk( '#ffffff' );
		foreach ( $cmyk as $channel ) {
			$this->assertEqualsWithDelta( 0.0, $channel, 0.1 );
		}
	}

	#[Test]
	public function red_has_zero_cyan(): void {
		$cmyk = OC_Print_Base_Testable::test_hex_to_cmyk( '#ff0000' );
		$this->assertEqualsWithDelta( 0.0, $cmyk[0], 0.1 ); // C = 0
		$this->assertGreaterThan( 0.0, $cmyk[1] );          // M > 0
	}

	#[Test]
	public function cmyk_channels_are_0_to_100(): void {
		$colors = [ '#ff5733', '#1abc9c', '#3498db', '#9b59b6', '#f1c40f' ];
		foreach ( $colors as $hex ) {
			$cmyk = OC_Print_Base_Testable::test_hex_to_cmyk( $hex );
			$this->assertCount( 4, $cmyk );
			foreach ( $cmyk as $channel ) {
				$this->assertGreaterThanOrEqual( 0.0, $channel );
				$this->assertLessThanOrEqual( 100.0, $channel );
			}
		}
	}

	#[Test]
	public function shorthand_hex_works_in_cmyk(): void {
		$full      = OC_Print_Base_Testable::test_hex_to_cmyk( '#ff0000' );
		$shorthand = OC_Print_Base_Testable::test_hex_to_cmyk( '#f00' );
		foreach ( range( 0, 3 ) as $i ) {
			$this->assertEqualsWithDelta( $full[ $i ], $shorthand[ $i ], 0.01 );
		}
	}

	// ── cell_h ────────────────────────────────────────────────────────────

	#[Test]
	public function cell_h_increases_with_font_size(): void {
		$small = OC_Print_Base_Testable::test_cell_h( 8.0 );
		$large = OC_Print_Base_Testable::test_cell_h( 24.0 );
		$this->assertGreaterThan( $small, $large );
	}

	#[Test]
	public function cell_h_is_positive(): void {
		$this->assertGreaterThan( 0.0, OC_Print_Base_Testable::test_cell_h( 10.0 ) );
	}

	// ── build_filename ────────────────────────────────────────────────────

	#[Test]
	public function build_filename_includes_item_id_area_key_ext(): void {
		$name = OC_Print_Base_Testable::test_build_filename( 42, 'front', 'pdf' );
		$this->assertStringStartsWith( '42-', $name );
		$this->assertStringContainsString( 'front', $name );
		$this->assertStringEndsWith( '.pdf', $name );
	}

	#[Test]
	public function build_filename_sanitises_area_key(): void {
		// Area keys with special characters should be sanitised.
		$name = OC_Print_Base_Testable::test_build_filename( 1, 'front/back', 'pdf' );
		$this->assertStringNotContainsString( '/', $name );
	}

	// ── Spotify scannable codes ────────────────────────────────────────────

	#[Test]
	public function extracts_spotify_uri_from_uri(): void {
		$this->assertSame(
			'spotify:track:6rqhFgbbKwnb9MLmUQDhG6',
			OC_Print_Base_Testable::test_extract_spotify_uri( 'spotify:track:6rqhFgbbKwnb9MLmUQDhG6' )
		);
	}

	#[Test]
	public function extracts_spotify_uri_from_open_url(): void {
		$this->assertSame(
			'spotify:track:6rqhFgbbKwnb9MLmUQDhG6',
			OC_Print_Base_Testable::test_extract_spotify_uri( 'https://open.spotify.com/intl-en/track/6rqhFgbbKwnb9MLmUQDhG6?si=abc' )
		);
	}

	#[Test]
	public function builds_spotify_scannable_svg_url(): void {
		$url = OC_Print_Base_Testable::test_build_spotify_code_url( 'https://open.spotify.com/track/6rqhFgbbKwnb9MLmUQDhG6' );

		$this->assertSame(
			'https://scannables.scdn.co/uri/plain/svg/FFFFFF/black/640/spotify:track:6rqhFgbbKwnb9MLmUQDhG6',
			$url
		);
	}

	#[Test]
	public function rejects_non_spotify_urls_for_scannable_codes(): void {
		$this->assertSame( '', OC_Print_Base_Testable::test_build_spotify_code_url( 'https://example.com/track/6rqhFgbbKwnb9MLmUQDhG6' ) );
	}

	#[Test]
	public function engraving_text_normalises_colour_emoji_to_text_presentation(): void {
		$result = OC_Print_Base_Testable::test_normalise_engraving_text( "Name \u{2764}\u{FE0F} \u{1F44D}\u{1F3FD} \u{1F600}" );

		$this->assertSame( "Name \u{2764}\u{FE0E} \u{1F44D}\u{FE0E} \u{1F600}\u{FE0E}", $result );
		$this->assertStringNotContainsString( "\u{FE0F}", $result );
		$this->assertStringNotContainsString( "\u{1F3FD}", $result );
	}

	#[Test]
	public function resolves_stale_absolute_attachment_path_inside_current_uploads(): void {
		global $oc_test_attached_files, $oc_test_post_meta;

		$dir = sys_get_temp_dir() . '/overcustomise/artwork';
		wp_mkdir_p( $dir );
		$path = $dir . '/customer-upload.png';
		file_put_contents( $path, 'png' );

		$oc_test_attached_files = [
			123 => '/var/www/html/wp-content/uploads/overcustomise/artwork/customer-upload.png',
		];
		$oc_test_post_meta = [];

		try {
			$this->assertSame( realpath( $path ), OC_Print_Base_Testable::test_resolve_artwork_path( [
				'artworkAttachmentId' => 123,
			] ) );
		} finally {
			@unlink( $path );
			$oc_test_attached_files = [];
			$oc_test_post_meta = [];
		}
	}

	#[Test]
	public function resolves_attachment_meta_relative_upload_path(): void {
		global $oc_test_attached_files, $oc_test_post_meta;

		$dir = sys_get_temp_dir() . '/overcustomise/artwork';
		wp_mkdir_p( $dir );
		$path = $dir . '/relative-upload.png';
		file_put_contents( $path, 'png' );

		$oc_test_attached_files = [ 456 => '/missing/path/relative-upload.png' ];
		$oc_test_post_meta = [
			456 => [ '_wp_attached_file' => 'overcustomise/artwork/relative-upload.png' ],
		];

		try {
			$this->assertSame( realpath( $path ), OC_Print_Base_Testable::test_resolve_artwork_path( [
				'artworkAttachmentId' => 456,
			] ) );
		} finally {
			@unlink( $path );
			$oc_test_attached_files = [];
			$oc_test_post_meta = [];
		}
	}
}
