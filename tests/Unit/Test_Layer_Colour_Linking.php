<?php
/**
 * Unit tests for filtered-image colour settings and cross-type colour links.
 *
 * @package OverCustomise
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once OC_PATH . 'includes/frontend/class-oc-cart.php';
require_once OC_PATH . 'includes/admin/class-oc-admin-order-metabox.php';

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
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( string $value ): string {
		return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( string $value ): string {
		return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( string $value, string $domain = 'default' ): string {
		return esc_html( $value );
	}
}

class Test_Layer_Colour_Linking extends TestCase {
	#[Test]
	public function linked_text_copies_only_value_and_keeps_target_layout(): void {
		$method = new ReflectionMethod( OC_Cart::class, 'synchronise_linked_layer_inputs' );
		foreach ( [ 'text', 'textarea' ] as $type ) {
			$layers = [
				(object) [ 'id' => 10, 'type' => $type, 'settings' => [ 'link_group' => 'name' ] ],
				(object) [ 'id' => 20, 'type' => $type, 'settings' => [ 'link_group' => 'name' ] ],
			];
			$target = [ 'value' => 'Old', 'fontId' => 2, 'fontSize' => 30, 'colorHex' => '#ff0000', 'renderedFontSize' => 22.5, 'renderedLines' => [ 'New' ] ];
			$result = $method->invoke( null, $layers, [ 10 => [ 'value' => 'New', 'fontId' => 1, 'fontSize' => 10, 'renderedFontSize' => 9 ], 20 => $target ] );
			$target['value'] = 'New';
			$this->assertSame( $target, $result[20] );
			$result = $method->invoke( null, $layers, [ 10 => [ 'value' => 'New' ] ] );
			$this->assertSame( [ 'value' => 'New' ], $result[20] );
		}
	}

	#[Test]
	public function optional_sky_ignores_prepopulated_date_but_rejects_partial_location(): void {
		$method = new ReflectionMethod( OC_Cart::class, 'normalise_night_sky_input' );
		$source = [ 'date' => '2026-09-07', 'time' => '22:00', 'latitude' => null, 'longitude' => '' ];
		$result = $method->invoke( null, $source, [] );
		$this->assertSame( [], $result['nightSkyGeometry']['stars'] );
		foreach ( [ [ 'latitude' => 0 ], [ 'longitude' => 0 ], [ 'latitude' => 'invalid' ], [ 'locationLabel' => 'London' ] ] as $partial ) {
			$this->assertInstanceOf( WP_Error::class, $method->invoke( null, array_replace( $source, $partial ), [] ) );
		}
	}

	#[Test]
	public function blocks_and_admin_summaries_use_snapshot_without_live_design_lookup(): void {
		require_once OC_PATH . 'includes/class-oc-blocks-integration.php';
		$customisation = [ 'v' => 2, 'designId' => 99999, 'layers' => [ 10 => [ 'type' => 'text', 'value' => 'Customer name' ] ], 'renderSpec' => [ 'areas' => [ [ 'printMethod' => 'uv', 'layers' => [ [ 'id' => 10, 'type' => 'text', 'label' => 'Historical label', 'settings' => [] ] ] ] ] ] ];
		$data = ( new OC_Blocks_Integration() )->cart_item_data( [ '_oc_customisation' => $customisation ] );
		$this->assertSame( 'Historical label', $data['summary'][0]['key'] );
		$method = new ReflectionMethod( OC_Admin_Order_Metabox::class, 'render_v2_customisation_summary' );
		ob_start();
		try {
			$method->invoke( new OC_Admin_Order_Metabox(), $customisation, 99999 );
			$html = ob_get_contents();
		} finally {
			ob_end_clean();
		}
		$this->assertStringContainsString( 'Historical label', $html );
	}

	#[Test]
	public function internal_source_context_is_stripped_and_only_recreated_for_linked_members(): void {
		$method = new ReflectionMethod( OC_Cart::class, 'synchronise_linked_layer_inputs' );
		$layers = [
			(object) [ 'id' => 10, 'type' => 'image', 'settings' => [] ],
			(object) [ 'id' => 20, 'type' => 'image', 'settings' => [] ],
		];
		$input = [ 10 => [ 'value' => 'test', '_oc_link_source_layer_id' => 20 ] ];
		$result = $method->invoke( null, $layers, $input );
		$this->assertArrayNotHasKey( '_oc_link_source_layer_id', $result[10] );
		foreach ( $layers as $layer ) {
			$layer->settings = [ 'link_group' => 'photo' ];
		}
		$result = $method->invoke( null, $layers, $input );
		$this->assertSame( 10, $result[10]['_oc_link_source_layer_id'] );
		$this->assertSame( 10, $result[20]['_oc_link_source_layer_id'] );
	}

	#[Test]
	public function rendered_lines_reject_relocated_customer_spaces(): void {
		$method = new ReflectionMethod( OC_Cart::class, 'normalise_rendered_text_lines' );

		$this->assertNull( $method->invoke( null, [ 'foob', 'ar' ], 'foo bar' ) );
		$this->assertSame( [ 'personali', 'sation' ], $method->invoke( null, [ 'personali', 'sation' ], 'personalisation' ) );
	}

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
			10 => [ 'colorHex' => '#000000', 'colorName' => 'Black' ],
			20 => [ 'colorHex' => '#cc3300', 'colorName' => 'Burnt Orange' ],
		];

		$method = new ReflectionMethod( OC_Cart::class, 'synchronise_normalised_linked_colours' );
		$result = $method->invoke( null, $layers, $normalised );

		$this->assertSame( '#cc3300', $result[10]['colorHex'] );
		$this->assertSame( '#cc3300', $result[20]['colorHex'] );
		$this->assertSame( 'Burnt Orange', $result[10]['colorName'] );
		$this->assertTrue( $result[10]['colourLinked'] );
		$this->assertTrue( $result[20]['colourLinked'] );
	}

	#[Test]
	public function persisted_linked_image_colour_remains_authoritative_without_the_design_layer(): void {
		$cart   = new OC_Cart();
		$method = new ReflectionMethod( OC_Cart::class, 'image_layer_has_order_colour' );
		$data   = [ 'type' => 'image', 'colorHex' => '#cc3300', 'colourLinked' => true ];

		$this->assertTrue( $method->invoke( $cart, $data, null ) );
	}

	#[Test]
	public function unresolved_or_ambiguous_legacy_colour_name_falls_back_to_hex(): void {
		global $wpdb;
		$previous = $wpdb ?? null;
		$wpdb = new class {
			public string $prefix = 'wp_';
			public array $names = [];
			public function prepare( string $query, ...$args ): string {
				return $query;
			}
			public function get_col( string $query ): array {
				return $this->names;
			}
		};
		$cart   = new OC_Cart();
		$method = new ReflectionMethod( OC_Cart::class, 'colour_display_value' );
		$data   = [ 'colorHex' => '#cc3300', 'colorName' => 'Black' ];

		try {
			$output = $method->invoke( $cart, $data, true );
			$this->assertStringContainsString( '#cc3300', $output );
			$this->assertStringNotContainsString( 'Black', $output );

			$wpdb->names = [ 'Burnt Orange', 'Rust' ];
			$output = $method->invoke( $cart, $data, true );
			$this->assertStringContainsString( '#cc3300', $output );
			$this->assertStringNotContainsString( 'Burnt Orange', $output );
			$this->assertStringNotContainsString( 'Rust', $output );
		} finally {
			$wpdb = $previous;
		}
	}

	#[Test]
	public function embroidery_text_and_image_colours_are_included_on_customer_documents(): void {
		$cart   = new OC_Cart();
		$method = new ReflectionMethod( OC_Cart::class, 'layer_display_value' );
		$colour = [
			'colorHex'  => '#cc3300',
			'colorName' => 'Burnt Orange',
		];

		$text_output  = $method->invoke(
			$cart,
			array_merge(
				$colour,
				[
					'type'  => 'text',
					'value' => 'Jones',
				]
			),
			null,
			false,
			'embroidery'
		);
		$image_output = $method->invoke(
			$cart,
			array_merge(
				$colour,
				[
					'type'         => 'image',
					'attachmentId' => 123,
					'colourLinked' => true,
				]
			),
			null,
			false,
			'embroidery'
		);

		$this->assertStringContainsString( 'Burnt Orange', $text_output );
		$this->assertStringContainsString( 'Burnt Orange', $image_output );
	}

	#[Test]
	public function engraving_colours_are_excluded_from_invoice_and_backend_values(): void {
		$cart   = new OC_Cart();
		$method = new ReflectionMethod( OC_Cart::class, 'layer_display_value' );
		$colour = [
			'colorHex'  => '#cc3300',
			'colorName' => 'Burnt Orange',
		];

		foreach ( [ false, true ] as $admin_context ) {
			$text_output  = $method->invoke(
				$cart,
				array_merge(
					$colour,
					[
						'type'  => 'text',
						'value' => 'Jones',
					]
				),
				null,
				$admin_context,
				'engraving'
			);
			$image_output = $method->invoke(
				$cart,
				array_merge(
					$colour,
					[
						'type'         => 'image',
						'attachmentId' => 123,
						'colourLinked' => true,
					]
				),
				null,
				$admin_context,
				'engraving'
			);

			$this->assertStringNotContainsString( 'Burnt Orange', $text_output );
			$this->assertStringNotContainsString( '#cc3300', $text_output );
			$this->assertStringNotContainsString( 'Burnt Orange', $image_output );
			$this->assertStringNotContainsString( '#cc3300', $image_output );
		}
	}

	#[Test]
	public function order_time_render_spec_is_the_layer_print_method_authority(): void {
		$cart          = new OC_Cart();
		$method        = new ReflectionMethod( OC_Cart::class, 'layer_print_method_map' );
		$customisation = [
			'renderSpec' => [
				'areas' => [
					[
						'printMethod' => 'embroidery',
						'layers'      => [ [ 'id' => 10 ] ],
					],
					[
						'printMethod' => 'engraving',
						'layers'      => [ [ 'id' => 20 ] ],
					],
				],
			],
		];

		$result = $method->invoke( $cart, $customisation, [], 0 );

		$this->assertSame(
			[
				10 => 'embroidery',
				20 => 'engraving',
			],
			$result
		);
	}

	#[Test]
	public function order_time_render_spec_is_the_layer_settings_authority(): void {
		$cart   = new OC_Cart();
		$method = new ReflectionMethod( OC_Cart::class, 'render_spec_layer_map' );
		$result = $method->invoke(
			$cart,
			[
				'renderSpec' => [
					'areas' => [
						[
							'layers' => [
								[
									'id'       => 10,
									'type'     => 'text',
									'label'    => 'Name',
									'settings' => [ 'allow_colour_change' => false ],
								],
							],
						],
					],
				],
			]
		);

		$this->assertFalse( $result[10]->settings['allow_colour_change'] );
		$this->assertSame( 'Name', $result[10]->label );
	}

	#[Test]
	public function plain_text_order_details_include_embroidery_colour(): void {
		$method = new ReflectionMethod( OC_Cart::class, 'plain_text_layer_display_value' );
		$output = $method->invoke(
			new OC_Cart(),
			[
				'type'      => 'text',
				'value'     => 'Jones',
				'colorHex'  => '#cc3300',
				'colorName' => 'Burnt Orange',
			],
			(object) [
				'type'     => 'text',
				'settings' => [ 'allow_colour_change' => true ],
			],
			'embroidery'
		);

		$this->assertSame( 'Jones; Burnt Orange', $output );
		$this->assertSame(
			'Jones',
			$method->invoke(
				new OC_Cart(),
				[
					'type'      => 'text',
					'value'     => 'Jones',
					'colorHex'  => '#cc3300',
					'colorName' => 'Burnt Orange',
				],
				(object) [
					'type'     => 'text',
					'settings' => [ 'allow_colour_change' => true ],
				],
				'engraving'
			)
		);
		$this->assertSame(
			'',
			$method->invoke(
				new OC_Cart(),
				[
					'type'     => 'text',
					'value'    => '',
					'colorHex' => '#cc3300',
				],
				(object) [
					'type'     => 'text',
					'settings' => [ 'allow_colour_change' => true ],
				],
				'embroidery'
			)
		);
	}

	#[Test]
	public function print_files_metabox_excludes_engraving_colour_for_text_and_images(): void {
		$metabox = new OC_Admin_Order_Metabox();
		$method  = new ReflectionMethod( OC_Admin_Order_Metabox::class, 'v2_layer_display_value' );
		$colour  = [
			'colorHex'  => '#cc3300',
			'colorName' => 'Burnt Orange',
		];

		$text_output  = $method->invoke(
			$metabox,
			array_merge(
				$colour,
				[
					'type'  => 'text',
					'value' => 'Jones',
				]
			),
			null,
			'engraving'
		);
		$image_output = $method->invoke(
			$metabox,
			array_merge(
				$colour,
				[
					'type'         => 'image',
					'attachmentId' => 123,
					'colourLinked' => true,
				]
			),
			null,
			'engraving'
		);

		$this->assertStringNotContainsString( 'Burnt Orange', $text_output );
		$this->assertStringNotContainsString( 'Burnt Orange', $image_output );
	}
}
