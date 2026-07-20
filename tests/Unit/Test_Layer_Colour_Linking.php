<?php
/**
 * Unit tests for filtered-image colour settings and cross-type colour links.
 *
 * @package OverCustomise
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once OC_PATH . 'includes/frontend/class-oc-cart.php';

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( string $value ): string {
		return sanitize_text_field( $value );
	}
}
if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( string $value ): string {
		return $value;
	}
}

class Test_Layer_Colour_Linking extends TestCase {
	#[Test]
	public function image_colour_settings_are_normalised(): void {
		$settings = OC_Cart::normalise_layer_settings( [
			'enable_image_colour' => 'yes',
			'allow_colour_change' => 'no',
			'colour_link_group'   => 'Thread Colour',
		], 'image' );

		$this->assertTrue( $settings['enable_image_colour'] );
		$this->assertFalse( $settings['allow_colour_change'] );
		$this->assertSame( 'threadcolour', $settings['colour_link_group'] );
	}

	#[Test]
	public function customer_colour_is_copied_from_text_to_locked_image_colour_control(): void {
		$layers = [
			(object) [
				'id'       => 10,
				'type'     => 'image',
				'visible'  => true,
				'locked'   => false,
				'settings' => [
					'enable_image_colour' => true,
					'allow_colour_change' => false,
					'colour_link_group'   => 'thread',
				],
			],
			(object) [
				'id'       => 20,
				'type'     => 'text',
				'visible'  => true,
				'locked'   => false,
				'settings' => [
					'allow_colour_change' => true,
					'colour_link_group'   => 'thread',
				],
			],
		];
		$raw = [
			10 => [ 'colorHex' => '#000000' ],
			20 => [ 'colorHex' => '#cc3300' ],
		];

		$method = new ReflectionMethod( OC_Cart::class, 'synchronise_linked_layer_colours' );
		$result = $method->invoke( null, $layers, $raw );

		$this->assertSame( '#cc3300', $result[10]['colorHex'] );
		$this->assertSame( '#cc3300', $result[20]['colorHex'] );
		$this->assertTrue( $result[10]['_oc_linked_colour'] );
	}

	#[Test]
	public function validated_text_colour_overrides_a_linked_images_locked_default(): void {
		$layers = [
			(object) [ 'id' => 10, 'type' => 'image', 'locked' => false, 'settings' => [
				'enable_image_colour' => true, 'allow_colour_change' => false, 'colour_link_group' => 'thread',
			] ],
			(object) [ 'id' => 20, 'type' => 'text', 'locked' => false, 'settings' => [
				'allow_colour_change' => true, 'colour_link_group' => 'thread',
			] ],
		];
		$normalised = [
			10 => [ 'colorHex' => '#000000' ],
			20 => [ 'colorHex' => '#cc3300' ],
		];

		$method = new ReflectionMethod( OC_Cart::class, 'synchronise_normalised_linked_colours' );
		$result = $method->invoke( null, $layers, $normalised );

		$this->assertSame( '#cc3300', $result[10]['colorHex'] );
		$this->assertSame( '#cc3300', $result[20]['colorHex'] );
	}
}
