<?php
/** UV text must remain font-independent when imported into production software. @package OverCustomise */

// phpcs:disable WordPress.WP.AlternativeFunctions -- Fixtures use local files without a WordPress filesystem.

use PHPUnit\Framework\TestCase;

require_once OC_PATH . 'includes/print/class-oc-print-uv.php';

class Test_Print_UV_Text extends TestCase {
	public function test_cff_fonts_named_ttf_offer_conversion_and_resolve_the_print_companion(): void {
		require_once OC_PATH . 'includes/admin/class-oc-admin-fonts.php';
		$relative = 'overcustomise/fonts/oc-cff-test-' . bin2hex( random_bytes( 8 ) ) . '.ttf';
		$source   = wp_upload_dir()['basedir'] . '/' . $relative;
		$print    = substr( $source, 0, -4 ) . '-print.ttf';
		wp_mkdir_p( dirname( $source ) );
		file_put_contents( $source, 'OTTO' );
		try {
			$convert = new ReflectionMethod( OC_Admin_Fonts::class, 'can_convert_for_print' );
			$this->assertTrue( $convert->invoke( null, $relative ) );
			$this->assertFalse( $convert->invoke( null, '../outside.ttf' ) );
			copy( OC_PATH . 'tests/Annabelle JF Regular.ttf', $print );
			$resolve = new ReflectionMethod( OC_Print_Base::class, 'get_font_path' );
			$this->assertSame( realpath( $print ), $resolve->invoke( null, (object) [ 'file_path' => $relative ] ) );
			file_put_contents( $source, "\x00\x01\x00\x00" );
			$this->assertFalse( $convert->invoke( null, $relative ), 'Ordinary TTF fonts must not require conversion.' );
		} finally {
			unlink( $source );
			if ( is_file( $print ) ) {
				unlink( $print );
			}
		}
	}

	public function test_missing_glyphs_use_fallback_without_replacing_supported_lettering(): void {
		require_once OC_PATH . 'includes/print/class-oc-print-embroidery.php';
		$font    = OC_PATH . 'tests/Annabelle JF Regular.ttf';
		$outline = new ReflectionMethod( OC_Print_Embroidery::class, 'ttf_text_outline' );
		$runs    = new ReflectionMethod( OC_Print_Base::class, 'uv_text_outlines' );
		$measure = new ReflectionMethod( OC_Print_Base::class, 'uv_text_runs' );
		$this->assertNull( $outline->invoke( null, $font, '♥', 1.0 ), 'Fixture must lack the fallback character.' );
		$left  = $outline->invoke( null, $font, 'AV', 1.0 );
		$right = $outline->invoke( null, $font, 'VA', 1.0 );
		$this->assertSame( [ $left ], $runs->invoke( null, 'AV', $font ), 'Supported runs must retain their original outlines and kerning.' );
		$mixed = $runs->invoke( null, 'AV♥VA', $font );
		$this->assertCount( 3, $mixed );
		$this->assertSame( $left, $mixed[0] );
		$this->assertSame( $right, $mixed[2] );
		$this->assertNotEmpty( $mixed[1]['commands'] );
		$this->assertGreaterThan( 0, $mixed[1]['width'] );
		$artwork = $measure->invoke( null, 'AV♥VA', $font );
		$metrics = $measure->invoke( null, 'AV♥VA', $font, false );
		$this->assertEqualsWithDelta( array_sum( array_column( $mixed, 'width' ) ), $artwork['width'], 0.000001 );
		$this->assertSame( $artwork['width'], $metrics['width'], 'Wrapping and artwork must use identical fallback metrics.' );
		$this->assertSame( '', $metrics['svg'] );
		$this->assertSame( 3, substr_count( $artwork['svg'], '<path ' ) );
	}

	public function test_characters_missing_from_both_fonts_still_fail_instead_of_printing_boxes(): void {
		require_once OC_PATH . 'includes/print/class-oc-print-embroidery.php';
		$method = new ReflectionMethod( OC_Print_Base::class, 'uv_text_outlines' );
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Neither the selected UV font nor the bundled fallback can outline:' );
		$method->invoke( null, "A\u{10FFFF}", OC_PATH . 'tests/Annabelle JF Regular.ttf' );
	}

	public function test_invalid_primary_font_is_not_silently_replaced(): void {
		require_once OC_PATH . 'includes/print/class-oc-print-embroidery.php';
		$method = new ReflectionMethod( OC_Print_Base::class, 'uv_text_outlines' );
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'The selected UV print font is invalid.' );
		$method->invoke( null, 'ABC', __FILE__ );
	}

	public function test_emoji_sequences_and_text_presentation(): void {
		$method = new ReflectionMethod( OC_Print_Base::class, 'uv_emoji_key' );
		foreach ( [
			'💕'         => '1f495',
			'❤️'        => '2764',
			'👍🏽'        => '1f44d-1f3fd',
			'🇦🇺'        => '1f1e6-1f1fa',
			'1️⃣'       => '31-20e3',
			'👩‍👩‍👧‍👦'   => '1f469-200d-1f469-200d-1f467-200d-1f466',
			'©️'        => 'a9',
			'A'         => null,
			'1'         => null,
			'©'         => null,
			"♥\u{FE0E}" => null,
		] as $text => $key ) {
			$this->assertSame( $key, $method->invoke( null, (string) $text ), (string) $text );
		}
	}

	public function test_uv_pdf_contains_paths_and_colour_emoji_without_live_lettering(): void {
		$key      = '1f495';
		$cached   = wp_upload_dir()['basedir'] . '/overcustomise/emoji/17.0.2/' . $key . '.svg';
		$original = is_file( $cached ) ? file_get_contents( $cached ) : null;
		if ( is_file( $cached ) ) {
			unlink( $cached );
		}
		$GLOBALS['oc_test_http_requests']  = [];
		$GLOBALS['oc_test_http_responses'] = [
			[
				'response' => [ 'code' => 200 ],
				'body'     => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 36 36"><path fill="#DD2E44" d="M0 0 L36 0 L18 36 Z"/></svg>',
			],
		];
		try {
			$make = new ReflectionMethod( OC_Print_Base::class, 'make_pdf' );
			$pdf  = $make->invoke( null, 100.0, 60.0 );
			$pdf->SetCompression( false );
			$pdf->AddPage();
			$render  = new ReflectionMethod( OC_Print_UV::class, 'render_colour_page' );
			$area    = (object) [
				'canvas_w'    => 100,
				'canvas_h'    => 60,
				'canvas_unit' => 'mm',
				'canvas_dpi'  => 300,
			];
			$payload = [
				'bounds' => [
					'x' => 0,
					'y' => 0,
					'w' => 100,
					'h' => 60,
				],
				'layers' => [
					[
						'type'  => 'textarea',
						'x'     => 0,
						'y'     => 0,
						'w'     => 100,
						'h'     => 60,
						'input' => [
							'value'    => "In loving memory of\nSoda\n2026\nMuch loved and missed 💕",
							'fontSize' => 4,
						],
					],
				],
			];
			$render->invoke( null, $pdf, $area, 100.0, 60.0, 0.0, 0.0, $payload );
			$bytes = $pdf->Output( '', 'S' );
			$this->assertStringStartsWith( '%PDF-', $bytes );
			preg_match( '/\/Filter \/FlateDecode \/Length (\d+) >>\nstream\n/', $bytes, $stream, PREG_OFFSET_CAPTURE );
			$this->assertNotEmpty( $stream );
			$content = gzuncompress( substr( $bytes, $stream[0][1] + strlen( $stream[0][0] ), (int) $stream[1][0] ) );
			$this->assertDoesNotMatchRegularExpression( '/\b(?:Tj|TJ)\b/', $content, 'UV customer lettering must not use PDF text operators.' );
			$this->assertMatchesRegularExpression( '/[-\d.]+ [-\d.]+ m\b/', $content, 'Outlined glyphs must be present.' );
			$this->assertCount( 1, $GLOBALS['oc_test_http_requests'] );
			$this->assertStringEndsWith( '/1f495.svg', $GLOBALS['oc_test_http_requests'][0][0] );
			$this->assertFileExists( $cached );
		} finally {
			if ( null !== $original ) {
				file_put_contents( $cached, $original );
			} elseif ( is_file( $cached ) ) {
				unlink( $cached );
			}
			$GLOBALS['oc_test_http_responses'] = [];
		}
	}
}
