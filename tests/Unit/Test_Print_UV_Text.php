<?php
/** UV text must remain font-independent when imported into production software. @package OverCustomise */

// phpcs:disable WordPress.WP.AlternativeFunctions -- Fixtures use local files without a WordPress filesystem.

use PHPUnit\Framework\TestCase;

require_once OC_PATH . 'includes/print/class-oc-print-uv.php';

class Test_Print_UV_Text extends TestCase {
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
