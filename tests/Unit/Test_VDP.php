<?php
/**
 * Unit tests for variable data printing values.
 *
 * @package OverCustomise
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once OC_PATH . 'includes/class-oc-vdp.php';

class Test_VDP extends TestCase {
	#[Test]
	public function printable_values_preserve_literal_syntax_and_enforce_actual_length(): void {
		$vdp  = new OC_VDP();
		$text = '<name> &lt; &#60; & "Zoë" %20';
		foreach ( [ 'text', 'textarea' ] as $type ) {
			$layer = (object) [
				'type'     => $type,
				'label'    => 'Name',
				'settings' => '{}',
			];
			$this->assertSame( $text, $vdp->normalise_layer_value( $layer, $text ) );
			$this->assertSame( 'textarea' === $type ? "<a>\n<b>" : '<a> <b>', $vdp->normalise_layer_value( $layer, "<a>\r\n<b>" ) );
			$layer->settings = '{"char_limit":3}';
			$this->assertSame( 'vdp_character_limit', $vdp->normalise_layer_value( $layer, '<name>' )->get_error_code() );
			$layer->settings = '{"required":true}';
			$this->assertSame( '<>', $vdp->normalise_layer_value( $layer, '<>' ) );
			$this->assertSame( 'required_vdp_value', $vdp->normalise_layer_value( $layer, "\x00" )->get_error_code() );
		}
	}

	#[Test]
	public function merge_values_supports_sanitized_hyphenated_headers(): void {
		$vdp = new OC_VDP();

		$this->assertSame(
			'Hello Ada Lovelace',
			$vdp->merge_values( 'Hello {{first-name}}', [ 'first-name' => 'Ada Lovelace' ] )
		);
	}

	#[Test]
	public function merge_values_retains_unknown_hyphenated_placeholder(): void {
		$vdp = new OC_VDP();

		$this->assertSame( '{{missing-field}}', $vdp->merge_values( '{{missing-field}}', [] ) );
	}

	#[Test]
	public function locked_layers_cannot_be_vdp_targets(): void {
		$result = ( new OC_VDP() )->normalise_layer_value(
			(object) [ 'type' => 'text', 'label' => 'Name', 'locked' => 1, 'settings' => '{}' ],
			'Ada'
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_vdp_layer', $result->get_error_code() );
	}
}
