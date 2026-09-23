<?php
/**
 * Literal printable text at JSON, HTML and plain-email output boundaries.
 *
 * @package OverCustomise\Tests
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once OC_PATH . 'includes/class-oc-print-text.php';
require_once OC_PATH . 'includes/frontend/class-oc-cart.php';
require_once OC_PATH . 'includes/class-oc-blocks-integration.php';

if ( ! class_exists( 'WC_Order_Item' ) ) {
	/** Type-only stand-in: snapshot-backed summaries do not query the order item. */
	class WC_Order_Item {}
}

class Test_Printable_Text_Summaries extends TestCase {
	private function cart(): OC_Cart {
		return ( new ReflectionClass( OC_Cart::class ) )->newInstanceWithoutConstructor();
	}

	private function payload( mixed $text, string $type = 'text' ): array {
		return [
			'v'          => 2,
			'designId'   => 0,
			'renderSpec' => [],
			'layers'     => [
				1 => [
					'type'  => $type,
					'value' => $text,
				],
			],
		];
	}

	private function plain_email( array $payload ): string {
		$method = new ReflectionMethod( OC_Cart::class, 'display_plain_text_order_item' );
		$item   = ( new ReflectionClass( WC_Order_Item::class ) )->newInstanceWithoutConstructor();
		ob_start();
		try {
			$method->invoke( $this->cart(), $payload, '', $item );
			return ob_get_contents();
		} finally {
			ob_end_clean();
		}
	}

	#[Test]
	public function literal_values_survive_all_summary_formats(): void {
		$blocks = new OC_Blocks_Integration();
		foreach ( [ 'text', 'textarea', 'spotify' ] as $type ) {
			foreach ( [ '<name> %20 "Zoë"', '&lt; &#60; &amp; &#x1F600;', '<script>alert("x")</script><img src=x onerror=alert(1)>', '0' ] as $text ) {
				$payload = $this->payload( $text, $type );
				$item    = [ '_oc_customisation' => $payload ];
				$summary = $blocks->cart_item_data( $item )['summary'];
				$this->assertSame(
					[
						[
							'key'   => ucfirst( $type ),
							'value' => $text,
						],
					],
					$summary
				);
				// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Assert a native JSON round trip of the Store API summary, including encoding failures.
				$this->assertSame( $summary, json_decode( json_encode( $summary, JSON_THROW_ON_ERROR ), true, 512, JSON_THROW_ON_ERROR ) );
				$this->assertSame( "\nCustomisation:\n" . ucfirst( $type ) . ': ' . $text . "\n", $this->plain_email( $payload ) );

				$html = $this->cart()->display_item_data( [], $item )[0]['value'];
				$this->assertSame( $text, html_entity_decode( $html, ENT_QUOTES, 'UTF-8' ) );
				$this->assertStringNotContainsString( '<', $html );
				$this->assertStringNotContainsString( '>', $html );
				if ( str_contains( $text, '&lt;' ) ) {
					$this->assertStringContainsString( '&amp;lt;', $html );
				}
			}
		}
	}

	#[Test]
	public function legacy_summaries_preserve_literal_entities_and_multiline_text(): void {
		$text    = "<name> &lt; %20\nSecond\tline";
		$payload = [ 'front-panel' => [ 'text' => $text ] ];
		$item    = [ '_oc_customisation' => $payload ];
		$this->assertSame( $text, ( new OC_Blocks_Integration() )->cart_item_data( $item )['summary'][0]['value'] );
		$this->assertSame( "\nCustomisation:\nFront Panel: " . $text . "\n", $this->plain_email( $payload ) );
		$html = $this->cart()->display_item_data( [], $item )[0]['value'];
		$this->assertSame( $text, html_entity_decode( $html, ENT_QUOTES, 'UTF-8' ) );
		$this->assertStringContainsString( '&lt;name&gt; &amp;lt; %20', $html );
	}

	#[Test]
	public function summaries_normalise_controls_and_line_endings_consistently(): void {
		foreach ( [
			'text'     => '<name> %20 &lt; next',
			'textarea' => "<name>\n%20\t&lt;\nnext",
		] as $type => $expected ) {
			$payload = $this->payload( " \0<name>\r\n%20\t&lt;\rnext\x7f ", $type );
			$item    = [ '_oc_customisation' => $payload ];
			$this->assertSame( $expected, ( new OC_Blocks_Integration() )->cart_item_data( $item )['summary'][0]['value'] );
			$this->assertSame( $expected, html_entity_decode( $this->cart()->display_item_data( [], $item )[0]['value'], ENT_QUOTES, 'UTF-8' ) );
			$this->assertSame( "\nCustomisation:\n" . ucfirst( $type ) . ': ' . $expected . "\n", $this->plain_email( $payload ) );
		}
	}

	#[Test]
	public function invalid_or_empty_values_do_not_become_summary_content(): void {
		foreach ( [ [], new stdClass(), null, "\xC3\x28", "\0\x7f", '   ' ] as $text ) {
			$payload = $this->payload( $text );
			$item    = [ '_oc_customisation' => $payload ];
			$this->assertSame( [], ( new OC_Blocks_Integration() )->cart_item_data( $item )['summary'] );
			$this->assertSame( [], $this->cart()->display_item_data( [], $item ) );
			$this->assertSame( "\nCustomisation:\n", $this->plain_email( $payload ) );
		}
	}
}
