<?php
/**
 * Night Sky layer validation tests.
 *
 * @package OverCustomise
 */

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once OC_PATH . 'includes/class-oc-night-sky.php';
require_once OC_PATH . 'includes/frontend/class-oc-cart.php';

class Test_Night_Sky extends TestCase {
	#[Test]
	public function settings_options_are_strictly_normalised(): void {
		$settings = OC_Cart::normalise_layer_settings(
			[
				'show_constellations' => 'no',
				'show_planets'        => 'yes',
				'show_labels'         => false,
				'show_border'         => true,
			],
			'night_sky'
		);

		$this->assertFalse( $settings['show_constellations'] );
		$this->assertTrue( $settings['show_planets'] );
		$this->assertFalse( $settings['show_labels'] );
		$this->assertTrue( $settings['show_border'] );
	}

	#[Test]
	public function posted_geometry_and_label_are_ignored(): void {
		$method = new ReflectionMethod( OC_Cart::class, 'normalise_night_sky_input' );
		$result = $method->invoke(
			null,
			[
				'date'             => '2026-09-01',
				'time'             => '22:00',
				'utcOffset'        => 60,
				'locationLabel'    => 'London',
				'latitude'         => 51.5074001,
				'longitude'        => -0.1278001,
				'nightSkyLabel'    => 'Forged label',
				'nightSkyGeometry' => [
					'v'               => 1,
					'coordinateSpace' => 'unit-box-v1',
					'stars'           => [
						[
							'x'      => 0.25,
							'y'      => 0.5,
							'r'      => 0.05,
							'planet' => 'Forged',
						],
					],
					'segments'        => [
						[
							'x1' => 0.1,
							'y1' => 0.2,
							'x2' => 0.3,
							'y2' => 0.4,
							'w'  => 0.001,
						],
					],
					'labels'          => [
						[
							'x'    => 0.2,
							'y'    => 0.3,
							'text' => 'Orion',
							'size' => 0.02,
						],
					],
				],
			],
			[
				'show_constellations' => false,
				'show_planets'        => true,
				'show_labels'         => false,
				'show_border'         => true,
			]
		);

		$this->assertIsArray( $result );
		$this->assertSame( 51.5074, $result['latitude'] );
		$this->assertSame( 'London ' . "\xC2\xB7" . ' 2026-09-01 ' . "\xC2\xB7" . ' 22:00', $result['nightSkyLabel'] );
		$this->assertCount( 32, $result['nightSkyGeometry']['stars'] );
		$this->assertSame(
			[
				'x' => 0.504146,
				'y' => 0.293898,
				'r' => 0.004022,
			],
			$result['nightSkyGeometry']['stars'][0]
		);
		$this->assertSame( [], $result['nightSkyGeometry']['segments'] );
		$this->assertSame( [], $result['nightSkyGeometry']['labels'] );
		$this->assertTrue( $result['nightSkyGeometry']['border'] );
	}

	#[Test]
	public function known_london_input_matches_javascript_fixture(): void {
		$geometry = OC_Night_Sky::generate(
			[
				'date'      => '2026-09-01',
				'time'      => '22:00',
				'utcOffset' => 60,
				'latitude'  => 51.5074,
				'longitude' => -0.1278,
			],
			[
				'show_constellations' => true,
				'show_planets'        => true,
				'show_labels'         => true,
				'show_border'         => true,
			]
		);

		$this->assertIsArray( $geometry );
		$this->assertCount( 32, $geometry['stars'] );
		$this->assertCount( 23, $geometry['segments'] );
		$this->assertCount( 9, $geometry['labels'] );
		$this->assertSame(
			[
				'x' => 0.504146,
				'y' => 0.293898,
				'r' => 0.004022,
			],
			$geometry['stars'][0]
		);
		$this->assertSame(
			[
				'x'      => 0.922553,
				'y'      => 0.559576,
				'r'      => 0.0065,
				'planet' => 'Saturn',
			],
			$geometry['stars'][30]
		);
		$this->assertSame(
			[
				'x'    => 0.316937,
				'y'    => 0.252659,
				'text' => 'Ursa Major',
				'size' => 0.018,
			],
			$geometry['labels'][0]
		);
	}

	#[Test]
	public function empty_optional_input_ignores_posted_derived_fields(): void {
		$method = new ReflectionMethod( OC_Cart::class, 'normalise_night_sky_input' );
		$result = $method->invoke(
			null,
			[
				'nightSkyLabel'    => 'Forged',
				'nightSkyGeometry' => [ 'stars' => [ [ 'x' => 0.5 ] ] ],
			],
			[ 'show_border' => false ]
		);

		$this->assertSame( '', $result['nightSkyLabel'] );
		$this->assertSame( [], $result['nightSkyGeometry']['stars'] );
		$this->assertFalse( $result['nightSkyGeometry']['border'] );
	}

	#[Test]
	public function invalid_coordinates_are_rejected(): void {
		$method = new ReflectionMethod( OC_Cart::class, 'normalise_night_sky_input' );
		$result = $method->invoke(
			null,
			[
				'date'             => '2026-09-01',
				'time'             => '22:00',
				'locationLabel'    => 'Invalid',
				'latitude'         => 91,
				'longitude'        => 0,
				'nightSkyGeometry' => [],
			],
			[]
		);

		$this->assertInstanceOf( WP_Error::class, $result );
	}
}
