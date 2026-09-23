<?php
/** Literal customer text regressions. @package OverCustomise */

use PHPUnit\Framework\TestCase;

class Test_Print_Text extends TestCase {
	public function test_literal_characters_are_preserved_and_normalisation_is_idempotent(): void {
		foreach ( [ '< >', '<name>', '<script>alert("x")</script>', '& &lt; &#60; &amp;lt;', '"quotes" \'apostrophe\' \\', 'Zoë 中文 👩‍👩‍👧‍👦', '%20 %3C 100% %aa' ] as $text ) {
			foreach ( [ false, true ] as $multiline ) {
				$this->assertSame( $text, OC_Print_Text::normalise( $text, $multiline ) );
				$this->assertSame( $text, OC_Print_Text::normalise( OC_Print_Text::normalise( $text, $multiline ), $multiline ) );
			}
		}
	}

	public function test_whitespace_and_unsafe_controls(): void {
		$input = " \t<one>\r\n\r<two>\t  &lt;\x00\x01\x0B\x0C\x7F\u{0085} \n";
		$this->assertSame( '<one> <two> &lt;', OC_Print_Text::normalise( $input ) );
		$this->assertSame( "<one>\n\n<two>\t  &lt;", OC_Print_Text::normalise( $input, true ) );
		$this->assertSame( "a\u{00A0}b", OC_Print_Text::normalise( "a\u{00A0}b" ) );
	}

	public function test_non_scalars_and_malformed_utf8_are_rejected(): void {
		foreach ( [ null, [], new stdClass(), "bad\xFF", "\xC0\xAF", "\xED\xA0\x80", "\xF4\x90\x80\x80", "\xE2\x82" ] as $invalid ) {
			$this->assertSame( '', OC_Print_Text::normalise( $invalid ) );
		}
		$resource = fopen( 'php://memory', 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- An in-memory resource is the invalid-input fixture.
		try {
			$this->assertSame( '', OC_Print_Text::normalise( $resource ) );
		} finally {
			fclose( $resource ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close the in-memory resource fixture.
		}
		$this->assertSame( '0', OC_Print_Text::normalise( 0 ) );
		$this->assertSame( '1.5', OC_Print_Text::normalise( 1.5 ) );
		$this->assertSame( '1', OC_Print_Text::normalise( true ) );
		$this->assertSame( '', OC_Print_Text::normalise( false ) );
	}
}
