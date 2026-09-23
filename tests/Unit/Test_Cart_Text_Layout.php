<?php
/** Cart preview geometry contract regressions. */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once OC_PATH . 'includes/frontend/class-oc-cart.php';

class Test_Cart_Text_Layout extends TestCase {
	#[Test]
	public function literal_text_layout_matches_without_entity_decoding_or_tag_stripping(): void {
		$text   = '<name> &lt; %20 "Zoë"';
		$posted = [
			'value'                 => $text,
			'fontId'                => 3,
			'renderedLayoutVersion' => 1,
		];
		$match  = new ReflectionMethod( OC_Cart::class, 'matching_text_layout_source' );
		$this->assertSame( $posted, $match->invoke( null, $posted, 'text', $text, 3 ) );
		$this->assertSame( [], $match->invoke( null, $posted, 'text', ' &lt; %20 "Zoë"', 3 ) );
		$lines = new ReflectionMethod( OC_Cart::class, 'normalise_rendered_text_lines' );
		$this->assertSame( [ '<name>', '&lt; %20 "Zoë"' ], $lines->invoke( null, [ '<name>', '&lt; %20 "Zoë"' ], $text ) );
		$this->assertNull( $lines->invoke( null, [ '<name>', '< %20 "Zoë"' ], $text ) );
	}

	#[Test]
	public function legacy_hints_remain_independent_unverified_and_use_previous_size_limits(): void {
		$method = new ReflectionMethod( OC_Cart::class, 'normalise_rendered_text_layout' );
		$source = [
			'renderedFontSize' => '24.56789',
			'renderedLines'    => [ 'Hello', 'world' ],
		];
		$this->assertSame(
			[
				'renderedLines'    => [ 'Hello', 'world' ],
				'renderedFontSize' => 24.5679,
			],
			$method->invoke( null, $source, 'textarea', 0, 10, 0, 0, 'Hello world' )
		);
		foreach ( [ 0, -1, INF, NAN, 1001, [] ] as $size ) {
			$this->assertSame( [ 'renderedLines' => [ 'Hello', 'world' ] ], $method->invoke( null, array_replace( $source, [ 'renderedFontSize' => $size ] ), 'textarea', 0, 10, 0, 0, 'Hello world' ) );
		}
		$this->assertSame( [ 'renderedFontSize' => 24.5679 ], $method->invoke( null, array_replace( $source, [ 'renderedLines' => [ 'Wrong' ] ] ), 'textarea', 0, 10, 0, 0, 'Hello world' ) );
		foreach ( [ [ 3, 20, 0, 0 ], [ 21, 20, 0, 0 ], [ 5, 0, 6, 10 ], [ 11, 0, 0, 10 ] ] as [ $size, $configured, $min, $max ] ) {
			$this->assertSame( [], $method->invoke( null, [ 'renderedFontSize' => $size ], 'text', $configured, 10, $min, $max, 'Hello world' ) );
		}
		foreach ( [ [ 'renderedLayoutVersion' => null ], [ 'renderedScaleX' => 1 ], [ 'renderedInsetX' => 0 ] ] as $partial ) {
			$this->assertSame( [], $method->invoke( null, $source + $partial, 'textarea', 0, 10, 0, 0, 'Hello world' ) );
		}
	}

	private function normalise( array $overrides = [], string $type = 'text', float $configured = 20, float $height = 10, float $min = 0, float $max = 0 ): array {
		$source = array_replace(
			[
				'renderedLayoutVersion' => 1,
				'renderedFontSize'      => 2.5,
				'renderedScaleX'        => 0.75,
				'renderedInsetX'        => 0,
			],
			$overrides
		);
		return ( new ReflectionMethod( OC_Cart::class, 'normalise_rendered_text_layout' ) )->invoke( null, $source, $type, $configured, $height, $min, $max, 'Hello world' );
	}

	#[Test]
	public function accepts_positive_canonical_sizes_without_four_unit_floor(): void {
		foreach ( [ 2.5, 0.1, 0.000001 ] as $size ) {
			$this->assertSame( $size, $this->normalise( [ 'renderedFontSize' => $size ] )['renderedFontSize'] );
		}
		$this->assertSame( 0.75, $this->normalise()['renderedScaleX'] );
	}

	#[Test]
	public function auto_size_uses_canonical_height_and_configured_limits(): void {
		$this->assertNotEmpty( $this->normalise( [ 'renderedFontSize' => 7.2 ], 'text', 0 ) );
		$this->assertSame( [], $this->normalise( [ 'renderedFontSize' => 7.21 ], 'text', 0 ) );
		$this->assertSame( [], $this->normalise( [ 'renderedFontSize' => 4.1 ], 'text', 0, 10, 0, 4 ) );
		$this->assertNotEmpty( $this->normalise( [ 'renderedFontSize' => 8 ], 'text', 0, 10, 8, 12 ) );
		$this->assertSame( [], $this->normalise( [], 'text', 20, 10, 3, 10 ) );
		$this->assertSame( [], $this->normalise( [ 'renderedFontSize' => 20.1 ] ) );
	}

	#[Test]
	public function invalid_or_partial_versioned_geometry_is_discarded_atomically(): void {
		foreach ( [
			'renderedLayoutVersion' => [ null, 0, 2, 1.5, true, '1junk' ],
			'renderedFontSize'      => [ null, 0, -1, INF, NAN, 'NaN', [] ],
			'renderedScaleX'        => [ null, 0, -1, 1.01, INF, NAN, [] ],
			'renderedInsetX'        => [ null, -0.01, 0.5, INF, NAN, [], 0.1 ],
		] as $key => $invalid ) {
			foreach ( $invalid as $value ) {
				$this->assertSame( [], $this->normalise( [ $key => $value ] ), $key );
			}
		}
	}

	#[Test]
	public function textarea_insets_are_fractions_and_numeric_fields_are_normalised(): void {
		$input = [
			'renderedLayoutVersion' => '1',
			'renderedFontSize'      => '2.5',
			'renderedScaleX'        => '1',
			'renderedInsetX'        => '0.025',
			'renderedLines'         => [ 'Hello', 'world' ],
		];
		$this->assertSame(
			[
				'renderedFontSize'      => 2.5,
				'renderedScaleX'        => 1.0,
				'renderedInsetX'        => 0.025,
				'renderedLayoutVersion' => 1,
				'renderedLines'         => [ 'Hello', 'world' ],
			],
			$this->normalise( $input, 'textarea' )
		);
		$this->assertSame( [], $this->normalise( [], 'textarea' ) );
		$this->assertSame( [], $this->normalise( array_replace( $input, [ 'renderedInsetX' => 0.5 ] ), 'textarea' ) );
	}

	#[Test]
	public function textarea_requires_valid_lines_as_part_of_the_atomic_layout(): void {
		$input = [
			'renderedScaleX' => 1,
			'renderedInsetX' => 0.01,
		];
		foreach ( [ null, [], [ 'Other text' ], [ [] ], array_fill( 0, 201, 'Hello' ) ] as $lines ) {
			$this->assertSame( [], $this->normalise( $input + [ 'renderedLines' => $lines ], 'textarea' ) );
		}
		$lines = array_merge( [ 'Hello world' ], array_fill( 0, 199, '' ) );
		$this->assertCount( 200, $this->normalise( $input + [ 'renderedLines' => $lines ], 'textarea' )['renderedLines'] );
		$this->assertNotEmpty( $this->normalise( $input + [ 'renderedLines' => [ 'Hel', 'lo world' ] ], 'textarea' ) );
		$this->assertSame(
			[],
			$this->normalise(
				$input + [
					'renderedLayoutVersion' => 2,
					'renderedLines'         => [ 'Hello world' ],
				],
				'textarea'
			)
		);
	}

	#[Test]
	public function derived_layout_requires_authoritative_text_and_font(): void {
		$method = new ReflectionMethod( OC_Cart::class, 'matching_text_layout_source' );
		$posted = [
			'value'                 => 'Hello world',
			'fontId'                => '3',
			'fontSize'              => 999,
			'renderedLayoutVersion' => 1,
		];
		$this->assertSame( $posted, $method->invoke( null, $posted, 'text', 'Hello world', 3 ) );
		foreach ( [ [ 'value' => 'Edited' ], [ 'fontId' => 4 ], [ 'fontId' => '3junk' ], [ 'fontId' => null ], [ 'value' => null ] ] as $invalid ) {
			$this->assertSame( [], $method->invoke( null, array_replace( $posted, $invalid ), 'text', 'Hello world', 3 ) );
		}
	}
}
