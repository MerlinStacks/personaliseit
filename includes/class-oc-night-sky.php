<?php
/**
 * Authoritative Night Sky geometry generation.
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

/**
 * Generate deterministic decorative star-map geometry.
 *
 * This is the PHP counterpart of src/shared/night-sky.js. Star positions use
 * the bundled J2000 catalogue and planets use low-precision elements.
 */
class OC_Night_Sky {

	private const DEG = M_PI / 180;
	private const RAD = 180 / M_PI;

	private const CONSTELLATION_NAMES = [
		'And' => 'Andromeda',
		'Aql' => 'Aquila',
		'Ari' => 'Aries',
		'Aur' => 'Auriga',
		'Boo' => 'Boötes',
		'CMa' => 'Canis Major',
		'Car' => 'Carina',
		'Cas' => 'Cassiopeia',
		'Cen' => 'Centaurus',
		'Cet' => 'Cetus',
		'Cyg' => 'Cygnus',
		'Eri' => 'Eridanus',
		'Gem' => 'Gemini',
		'Leo' => 'Leo',
		'Ori' => 'Orion',
		'Peg' => 'Pegasus',
		'Per' => 'Perseus',
		'Sco' => 'Scorpius',
		'Sgr' => 'Sagittarius',
		'Tau' => 'Taurus',
		'UMa' => 'Ursa Major',
		'Vir' => 'Virgo',
	];

	/** @var array<string,array<int,float|int>> */
	private const PLANET_ELEMENTS = [
		'Mercury' => [ 48.3313, 3.24587e-5, 7.0047, 5e-8, 29.1241, 1.01444e-5, 0.387098, 0, 0.205635, 5.59e-10, 168.6562, 4.0923344368 ],
		'Venus'   => [ 76.6799, 2.4659e-5, 3.3946, 2.75e-8, 54.891, 1.38374e-5, 0.72333, 0, 0.006773, -1.302e-9, 48.0052, 1.6021302244 ],
		'Earth'   => [ 0, 0, 0, 0, 282.9404, 4.70935e-5, 1, 0, 0.016709, -1.151e-9, 356.047, 0.9856002585 ],
		'Mars'    => [ 49.5574, 2.11081e-5, 1.8497, -1.78e-8, 286.5016, 2.92961e-5, 1.523688, 0, 0.093405, 2.516e-9, 18.6021, 0.5240207766 ],
		'Jupiter' => [ 100.4542, 2.76854e-5, 1.303, -1.557e-7, 273.8777, 1.64505e-5, 5.20256, 0, 0.048498, 4.469e-9, 19.895, 0.0830853001 ],
		'Saturn'  => [ 113.6634, 2.3898e-5, 2.4886, -1.081e-7, 339.3939, 2.97661e-5, 9.55475, 0, 0.055546, -9.499e-9, 316.967, 0.0334442282 ],
		'Uranus'  => [ 74.0005, 1.3978e-5, 0.7733, 1.9e-8, 96.6612, 3.0565e-5, 19.18171, -1.55e-8, 0.047318, 7.45e-9, 142.5905, 0.011725806 ],
		'Neptune' => [ 131.7806, 3.0173e-5, 1.77, -2.55e-7, 272.8461, -6.027e-6, 30.05826, 3.313e-8, 0.008606, 2.15e-9, 260.2471, 0.005995147 ],
	];

	/**
	 * Generate unit-box geometry from a validated observation.
	 *
	 * @param array<string,mixed> $input Observation fields.
	 * @param array<string,mixed> $settings Stored layer settings.
	 * @return array<string,mixed>|null
	 */
	public static function generate( array $input = [], array $settings = [] ): ?array {
		$moment    = self::parse_moment( $input['date'] ?? null, $input['time'] ?? null, $input['utcOffset'] ?? null );
		$latitude  = is_numeric( $input['latitude'] ?? null ) ? (float) $input['latitude'] : NAN;
		$longitude = is_numeric( $input['longitude'] ?? null ) ? (float) $input['longitude'] : NAN;
		if ( null === $moment || ! is_finite( $latitude ) || ! is_finite( $longitude ) || $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180 ) {
			return null;
		}

		$julian     = $moment / 86400 + 2440587.5;
		$days_j2000 = $julian - 2451543.5;
		$lst        = self::mod( 280.46061837 + 360.98564736629 * ( $julian - 2451545 ) + $longitude );
		$catalog    = self::catalog();
		$stars      = [];
		foreach ( $catalog['stars'] as [ $ra, $dec, $magnitude ] ) {
			$point = self::project( (float) $ra, (float) $dec, $latitude, $lst );
			if ( null === $point ) {
				continue;
			}
			$stars[] = $point + [ 'r' => self::round( max( 0.0015, min( 0.007, 0.0062 - (float) $magnitude * 0.0011 ) ) ) ];
		}

		$segments = [];
		$labels   = [];
		if ( false !== ( $settings['show_constellations'] ?? true ) ) {
			foreach ( $catalog['constellations'] as [ $id, $rank, $paths ] ) {
				$used = [];
				foreach ( $paths as $path ) {
					for ( $index = 1, $count = count( $path ); $index < $count; $index++ ) {
						$a = self::project( (float) $path[ $index - 1 ][0], (float) $path[ $index - 1 ][1], $latitude, $lst );
						$b = self::project( (float) $path[ $index ][0], (float) $path[ $index ][1], $latitude, $lst );
						if ( null !== $a && null !== $b && hypot( $a['x'] - $b['x'], $a['y'] - $b['y'] ) < 0.45 ) {
							$segments[] = [
								'x1' => $a['x'],
								'y1' => $a['y'],
								'x2' => $b['x'],
								'y2' => $b['y'],
								'w'  => 0.0012,
							];
							$used[]     = $a;
							$used[]     = $b;
						}
					}
				}
				if ( false !== ( $settings['show_labels'] ?? true ) && 1 === (int) $rank && $used ) {
					$labels[] = [
						'x'    => self::round( array_sum( array_column( $used, 'x' ) ) / count( $used ) ),
						'y'    => self::round( array_sum( array_column( $used, 'y' ) ) / count( $used ) ),
						'text' => self::CONSTELLATION_NAMES[ $id ] ?? $id,
						'size' => 0.015,
					];
				}
			}
		}

		if ( false !== ( $settings['show_planets'] ?? true ) ) {
			foreach ( array_keys( self::PLANET_ELEMENTS ) as $name ) {
				if ( 'Earth' === $name ) {
					continue;
				}
				$equatorial = self::planet_ra_dec( $name, $days_j2000 );
				$point      = self::project( $equatorial['ra'], $equatorial['dec'], $latitude, $lst );
				if ( null === $point ) {
					continue;
				}
				$stars[] = $point + [
					'r'      => 0.0065,
					'planet' => $name,
				];
				if ( false !== ( $settings['show_labels'] ?? true ) ) {
					$labels[] = [
						'x'    => self::round( $point['x'] + 0.012 ),
						'y'    => self::round( $point['y'] - 0.01 ),
						'text' => $name,
						'size' => 0.022,
					];
				}
			}
		}

		return [
			'v'               => 1,
			'coordinateSpace' => 'unit-box-v1',
			'stars'           => $stars,
			'segments'        => $segments,
			'labels'          => $labels,
			'border'          => false !== ( $settings['show_border'] ?? true ),
		];
	}

	/** Load the generated star and constellation catalogue once per request. */
	private static function catalog(): array {
		static $catalog = null;
		if ( null === $catalog ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads a bundled local catalogue.
			$decoded = json_decode( (string) file_get_contents( __DIR__ . '/data/night-sky-catalog.json' ), true );
			$catalog = is_array( $decoded ) ? $decoded : [
				'stars'          => [],
				'constellations' => [],
			];
		}

		return $catalog;
	}

	/** Build the concise display label used by the browser implementation. */
	public static function label( array $input = [] ): string {
		$parts = array_filter(
			[ $input['locationLabel'] ?? null, $input['date'] ?? null, $input['time'] ?? null ],
			static fn ( mixed $value ): bool => (bool) $value
		);

		$label = implode( ' ' . "\xC2\xB7" . ' ', array_map( 'strval', $parts ) );
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $label, 0, 200, 'UTF-8' );
		}
		if ( false !== preg_match_all( '/./us', $label, $characters ) ) {
			return implode( '', array_slice( $characters[0], 0, 200 ) );
		}

		return '';
	}

	/** Return the UTC Unix timestamp represented by local date/time and offset. */
	private static function parse_moment( mixed $date, mixed $time, mixed $utc_offset ): ?int {
		$date = is_string( $date ) ? $date : '';
		$time = is_string( $time ) ? $time : '';
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/D', $date, $date_parts ) || ! preg_match( '/^(\d{2}):(\d{2})$/D', $time, $time_parts ) ) {
			return null;
		}

		$year   = (int) $date_parts[1];
		$month  = (int) $date_parts[2];
		$day    = (int) $date_parts[3];
		$hour   = (int) $time_parts[1];
		$minute = (int) $time_parts[2];
		if ( $year < 1900 || $year > 2100 || ! checkdate( $month, $day, $year ) || $hour > 23 || $minute > 59 ) {
			return null;
		}

		$offset = is_numeric( $utc_offset ) ? (float) $utc_offset : 0.0;
		$offset = max( -840, min( 840, $offset ) );
		$stamp  = gmmktime( $hour, $minute, 0, $month, $day, $year );

		return false === $stamp ? null : (int) ( $stamp - $offset * 60 );
	}

	/** Project equatorial coordinates onto the unit-box horizon map. */
	private static function project( float $ra_deg, float $dec_deg, float $latitude, float $lst ): ?array {
		$lat        = $latitude * self::DEG;
		$dec        = $dec_deg * self::DEG;
		$hour_angle = self::mod( $lst - $ra_deg + 180 ) - 180;
		$h          = $hour_angle * self::DEG;
		$sin_alt    = sin( $dec ) * sin( $lat ) + cos( $dec ) * cos( $lat ) * cos( $h );
		$altitude   = asin( max( -1, min( 1, $sin_alt ) ) );
		if ( $altitude < 0 ) {
			return null;
		}

		$azimuth = atan2(
			-sin( $h ) * cos( $dec ),
			sin( $dec ) * cos( $lat ) - cos( $dec ) * sin( $lat ) * cos( $h )
		);
		$radius  = ( 1 - $altitude / ( M_PI / 2 ) ) * 0.48;

		return [
			'x' => self::round( 0.5 + $radius * sin( $azimuth ) ),
			'y' => self::round( 0.5 - $radius * cos( $azimuth ) ),
		];
	}

	/** Calculate low-precision heliocentric rectangular coordinates. */
	private static function heliocentric( string $name, float $days ): array {
		$e            = self::PLANET_ELEMENTS[ $name ];
		$node         = ( $e[0] + $e[1] * $days ) * self::DEG;
		$inclination  = ( $e[2] + $e[3] * $days ) * self::DEG;
		$perihelion   = ( $e[4] + $e[5] * $days ) * self::DEG;
		$axis         = $e[6] + $e[7] * $days;
		$eccentricity = $e[8] + $e[9] * $days;
		$mean_anomaly = self::mod( $e[10] + $e[11] * $days ) * self::DEG;
		$anomaly      = $mean_anomaly + $eccentricity * sin( $mean_anomaly ) * ( 1 + $eccentricity * cos( $mean_anomaly ) );
		for ( $iteration = 0; $iteration < 5; $iteration++ ) {
			$anomaly -= ( $anomaly - $eccentricity * sin( $anomaly ) - $mean_anomaly ) / ( 1 - $eccentricity * cos( $anomaly ) );
		}
		$xv        = $axis * ( cos( $anomaly ) - $eccentricity );
		$yv        = $axis * sqrt( 1 - $eccentricity * $eccentricity ) * sin( $anomaly );
		$true      = atan2( $yv, $xv );
		$radius    = hypot( $xv, $yv );
		$longitude = $true + $perihelion;

		return [
			'x' => $radius * ( cos( $node ) * cos( $longitude ) - sin( $node ) * sin( $longitude ) * cos( $inclination ) ),
			'y' => $radius * ( sin( $node ) * cos( $longitude ) + cos( $node ) * sin( $longitude ) * cos( $inclination ) ),
			'z' => $radius * sin( $longitude ) * sin( $inclination ),
		];
	}

	/** Convert a planet position to right ascension and declination. */
	private static function planet_ra_dec( string $name, float $days ): array {
		$earth     = self::heliocentric( 'Earth', $days );
		$planet    = self::heliocentric( $name, $days );
		$x         = $planet['x'] + $earth['x'];
		$y         = $planet['y'] + $earth['y'];
		$z         = $planet['z'] + $earth['z'];
		$obliquity = ( 23.4393 - 3.563e-7 * $days ) * self::DEG;
		$equ_y     = $y * cos( $obliquity ) - $z * sin( $obliquity );
		$equ_z     = $y * sin( $obliquity ) + $z * cos( $obliquity );

		return [
			'ra'  => self::mod( atan2( $equ_y, $x ) * self::RAD ),
			'dec' => atan2( $equ_z, hypot( $x, $equ_y ) ) * self::RAD,
		];
	}

	/** JavaScript-compatible positive modulus. */
	private static function mod( float $value, float $base = 360 ): float {
		return fmod( fmod( $value, $base ) + $base, $base );
	}

	/** Round generated geometry to the shared six-decimal precision. */
	private static function round( float $value ): float {
		return round( $value, 6 );
	}
}
