<?php
/**
 * Unit tests for OC_SVG_Sanitiser.
 *
 * @package OverCustomise
 */

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class Test_SVG_Sanitiser extends TestCase {

	// ── Valid SVG passthrough ──────────────────────────────────────────────

	#[Test]
	public function it_passes_through_clean_svg(): void {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100">'
			. '<rect x="10" y="10" width="80" height="80" fill="blue"/>'
			. '</svg>';

		$result = OC_SVG_Sanitiser::sanitise( $svg );

		$this->assertStringContainsString( '<svg', $result );
		$this->assertStringContainsString( '<rect', $result );
		$this->assertStringContainsString( 'fill="blue"', $result );
	}

	#[Test]
	public function it_accepts_svg_with_xml_declaration(): void {
		$svg = '<?xml version="1.0" encoding="UTF-8"?>'
			. '<svg xmlns="http://www.w3.org/2000/svg"><circle cx="50" cy="50" r="40"/></svg>';

		$result = OC_SVG_Sanitiser::sanitise( $svg );
		$this->assertStringContainsString( '<circle', $result );
	}

	#[Test]
	public function it_strips_bom(): void {
		$svg = "\xEF\xBB\xBF" . '<svg xmlns="http://www.w3.org/2000/svg"><g/></svg>';

		$result = OC_SVG_Sanitiser::sanitise( $svg );
		$this->assertStringStartsWith( '<svg', $result );
	}

	// ── Script injection ──────────────────────────────────────────────────

	#[Test]
	public function it_removes_script_elements(): void {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg">'
			. '<script>alert("xss")</script>'
			. '<rect width="100" height="100"/>'
			. '</svg>';

		$result = OC_SVG_Sanitiser::sanitise( $svg );
		$this->assertStringNotContainsString( '<script', $result );
		$this->assertStringNotContainsString( 'alert', $result );
	}

	#[Test]
	public function it_removes_event_handler_attributes(): void {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg">'
			. '<rect onload="alert(1)" onclick="evil()" width="10" height="10"/>'
			. '</svg>';

		$result = OC_SVG_Sanitiser::sanitise( $svg );
		$this->assertStringNotContainsString( 'onload', $result );
		$this->assertStringNotContainsString( 'onclick', $result );
		$this->assertStringContainsString( '<rect', $result );
	}

	// ── External resource blocking ────────────────────────────────────────

	#[Test]
	public function it_removes_external_href_on_use(): void {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg">'
			. '<use href="http://evil.com/sprite.svg#icon"/>'
			. '</svg>';

		$result = OC_SVG_Sanitiser::sanitise( $svg );
		$this->assertStringNotContainsString( 'evil.com', $result );
		$this->assertStringNotContainsString( '<use', $result );
	}

	#[Test]
	public function it_allows_internal_use_href(): void {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg">'
			. '<defs><circle id="c" cx="10" cy="10" r="5"/></defs>'
			. '<use href="#c"/>'
			. '</svg>';

		$result = OC_SVG_Sanitiser::sanitise( $svg );
		$this->assertStringContainsString( '<use', $result );
		$this->assertStringContainsString( '#c', $result );
	}

	#[Test]
	public function it_removes_javascript_href(): void {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg">'
			. '<a href="javascript:alert(1)"><text>click</text></a>'
			. '</svg>';

		$result = OC_SVG_Sanitiser::sanitise( $svg );
		$this->assertStringNotContainsString( 'javascript:', $result );
	}

	#[Test]
	public function it_removes_data_uri_in_image_href(): void {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg">'
			. '<image href="data:image/png;base64,abc123"/>'
			. '</svg>';

		$result = OC_SVG_Sanitiser::sanitise( $svg );
		$this->assertStringNotContainsString( 'data:', $result );
	}

	#[Test]
	public function it_removes_obfuscated_protocols_and_non_fragment_urls(): void {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg">'
			. '<a href="java&#x09;script:alert(1)"><text>bad</text></a>'
			. '<image href="file:///etc/passwd"/><image href="ftp://example.com/a.png"/>'
			. '<image href="images/local.png"/><use href="ftp://example.com/sprite.svg#local"/>'
			. '</svg>';

		$result = OC_SVG_Sanitiser::sanitise( $svg );

		$this->assertStringNotContainsString( 'javascript', strtolower( preg_replace( '/\s+/', '', $result ) ) );
		$this->assertStringNotContainsString( 'file:', $result );
		$this->assertStringNotContainsString( 'ftp:', $result );
		$this->assertStringNotContainsString( 'images/local.png', $result );
		$this->assertStringNotContainsString( '<use', $result );
	}

	#[Test]
	public function it_allows_only_fragment_css_urls_after_whitespace_normalisation(): void {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="g"/></defs>'
			. '<rect style="fill:url( #g );stroke:url(data:image/svg+xml,bad)"/>'
			. '</svg>';
		$result = OC_SVG_Sanitiser::sanitise( $svg );

		$this->assertStringContainsString( 'url(#g)', $result );
		$this->assertStringNotContainsString( 'data:', $result );
	}

	#[Test]
	public function it_retains_safe_static_stylesheet_rules(): void {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg"><style>.st0{fill:#78d5df;stroke:url(#g)}</style>'
			. '<defs><linearGradient id="g"/></defs><path class="st0" d="M0 0h10v10z"/></svg>';

		$result = OC_SVG_Sanitiser::sanitise( $svg );

		$this->assertStringContainsString( '<style>', $result );
		$this->assertStringContainsString( '.st0{fill:#78d5df;stroke:url(#g)}', $result );
	}

	// ── Blocked element removal ───────────────────────────────────────────

	#[Test]
	public function it_removes_foreignobject(): void {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg">'
			. '<foreignObject><body xmlns="http://www.w3.org/1999/xhtml"><p>html</p></body></foreignObject>'
			. '</svg>';

		$result = OC_SVG_Sanitiser::sanitise( $svg );
		$this->assertStringNotContainsString( 'foreignObject', $result );
		$this->assertStringNotContainsString( '<body', $result );
	}

	#[Test]
	public function it_removes_animate_elements(): void {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg">'
			. '<rect width="10" height="10"><animate attributeName="x" from="0" to="100"/></rect>'
			. '</svg>';

		$result = OC_SVG_Sanitiser::sanitise( $svg );
		$this->assertStringNotContainsString( '<animate', $result );
	}

	#[Test]
	public function it_removes_active_styles_foreign_namespaces_and_presentation_urls(): void {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:evil="https://example.com/evil">'
			. '<style>@import url(https://example.com/a.css);rect{fill:red}</style>'
			. '<iframe href="#safe"/><evil:rect width="10" height="10"/>'
			. '<rect id="safe" fill="url(https://example.com/paint.svg#g)" width="10" height="10"/>'
			. '</svg>';

		$result = OC_SVG_Sanitiser::sanitise( $svg );

		$this->assertStringNotContainsString( '<style', $result );
		$this->assertStringNotContainsString( '<iframe', $result );
		$this->assertStringNotContainsString( 'evil:rect', $result );
		$this->assertStringNotContainsString( 'example.com/paint', $result );
		$this->assertStringContainsString( 'id="safe"', $result );
	}

	// ── Style attribute ───────────────────────────────────────────────────

	#[Test]
	public function it_strips_javascript_in_style(): void {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg">'
			. '<rect style="fill:javascript:alert(1)" width="10" height="10"/>'
			. '</svg>';

		$result = OC_SVG_Sanitiser::sanitise( $svg );
		$this->assertStringNotContainsString( 'javascript', $result );
	}

	#[Test]
	public function it_drops_css_escape_sequences(): void {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg">'
			. '<rect style="fill:u\\72l(https://example.com/a.svg)" width="10" height="10"/>'
			. '</svg>';

		$result = OC_SVG_Sanitiser::sanitise( $svg );
		$this->assertStringNotContainsString( 'style=', $result );
	}

	// ── xml:base ──────────────────────────────────────────────────────────

	#[Test]
	public function it_removes_xml_base(): void {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" xml:base="http://evil.com/">'
			. '<rect width="10" height="10"/>'
			. '</svg>';

		$result = OC_SVG_Sanitiser::sanitise( $svg );
		$this->assertStringNotContainsString( 'xml:base', $result );
	}

	// ── Invalid input ─────────────────────────────────────────────────────

	#[Test]
	public function it_throws_for_non_svg_content(): void {
		$this->expectException( \InvalidArgumentException::class );
		OC_SVG_Sanitiser::sanitise( '<html><body>Not SVG</body></html>' );
	}

	#[Test]
	public function it_throws_for_empty_string(): void {
		$this->expectException( \InvalidArgumentException::class );
		OC_SVG_Sanitiser::sanitise( '' );
	}

	#[Test]
	public function it_rejects_foreign_root_namespace(): void {
		$this->expectException( \InvalidArgumentException::class );
		OC_SVG_Sanitiser::sanitise( '<svg xmlns="https://example.com/not-svg"><rect/></svg>' );
	}

	#[Test]
	public function it_rejects_excessive_nesting(): void {
		$this->expectException( \InvalidArgumentException::class );
		OC_SVG_Sanitiser::sanitise(
			'<svg xmlns="http://www.w3.org/2000/svg">' . str_repeat( '<g>', 130 ) . '<rect/>' . str_repeat( '</g>', 130 ) . '</svg>'
		);
	}

	// ── sanitise_file() ───────────────────────────────────────────────────

	#[Test]
	public function it_returns_false_for_nonexistent_file(): void {
		$result = OC_SVG_Sanitiser::sanitise_file( '/tmp/does-not-exist-oc-test.svg' );
		$this->assertFalse( $result );
	}

	#[Test]
	public function it_sanitises_file_in_place(): void {
		$svg  = '<svg xmlns="http://www.w3.org/2000/svg"><script>evil()</script><rect width="5" height="5"/></svg>';
		$path = tempnam( sys_get_temp_dir(), 'oc_svg_test_' ) . '.svg';
		file_put_contents( $path, $svg );

		$result = OC_SVG_Sanitiser::sanitise_file( $path );
		$this->assertTrue( $result );

		$cleaned = file_get_contents( $path );
		$this->assertStringNotContainsString( 'script', $cleaned );
		$this->assertStringContainsString( '<rect', $cleaned );

		@unlink( $path );
	}
}
