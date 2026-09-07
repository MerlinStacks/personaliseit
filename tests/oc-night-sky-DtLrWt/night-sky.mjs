/*
 * Deterministic, dependency-free night-sky geometry.
 *
 * Star positions use J2000 right ascension/declination. A compact built-in
 * set provides a fallback while the detailed catalogue loads. Planet
 * positions use low-precision orbital elements; this is intended for
 * decorative star maps, not navigation.
 */

const DEG = Math.PI / 180;
const RAD = 180 / Math.PI;
let detailedCatalog = null;

const CONSTELLATION_NAMES = {
	And: 'Andromeda',
	Aql: 'Aquila',
	Ari: 'Aries',
	Aur: 'Auriga',
	Boo: 'Boötes',
	CMa: 'Canis Major',
	Car: 'Carina',
	Cas: 'Cassiopeia',
	Cen: 'Centaurus',
	Cet: 'Cetus',
	Cyg: 'Cygnus',
	Eri: 'Eridanus',
	Gem: 'Gemini',
	Leo: 'Leo',
	Ori: 'Orion',
	Peg: 'Pegasus',
	Per: 'Perseus',
	Sco: 'Scorpius',
	Sgr: 'Sagittarius',
	Tau: 'Taurus',
	UMa: 'Ursa Major',
	Vir: 'Virgo',
};

// [id, display name, right ascension hours, declination degrees, magnitude]
const STARS = [
	[ 'polaris', 'Polaris', 2.5303, 89.2641, 1.98 ],
	[ 'sirius', 'Sirius', 6.7525, -16.7161, -1.46 ],
	[ 'canopus', 'Canopus', 6.3992, -52.6957, -0.74 ],
	[ 'arcturus', 'Arcturus', 14.261, 19.1824, -0.05 ],
	[ 'vega', 'Vega', 18.6156, 38.7837, 0.03 ],
	[ 'capella', 'Capella', 5.2782, 45.998, 0.08 ],
	[ 'rigel', 'Rigel', 5.2423, -8.2016, 0.13 ],
	[ 'procyon', 'Procyon', 7.655, 5.225, 0.34 ],
	[ 'betelgeuse', 'Betelgeuse', 5.9195, 7.4071, 0.42 ],
	[ 'achernar', 'Achernar', 1.6286, -57.2368, 0.46 ],
	[ 'hadar', 'Hadar', 14.0637, -60.373, 0.61 ],
	[ 'altair', 'Altair', 19.8464, 8.8683, 0.77 ],
	[ 'acrux', 'Acrux', 12.4433, -63.0991, 0.76 ],
	[ 'aldebaran', 'Aldebaran', 4.5987, 16.5093, 0.86 ],
	[ 'antares', 'Antares', 16.4901, -26.432, 0.96 ],
	[ 'spica', 'Spica', 13.4199, -11.1613, 0.97 ],
	[ 'pollux', 'Pollux', 7.7553, 28.0262, 1.14 ],
	[ 'fomalhaut', 'Fomalhaut', 22.9608, -29.6222, 1.16 ],
	[ 'deneb', 'Deneb', 20.6905, 45.2803, 1.25 ],
	[ 'regulus', 'Regulus', 10.1395, 11.9672, 1.35 ],
	[ 'castor', 'Castor', 7.5767, 31.8883, 1.58 ],
	[ 'bellatrix', 'Bellatrix', 5.4189, 6.3497, 1.64 ],
	[ 'elnath', 'Elnath', 5.4382, 28.6075, 1.65 ],
	[ 'alnilam', 'Alnilam', 5.6036, -1.2019, 1.69 ],
	[ 'alnitak', 'Alnitak', 5.6793, -1.9426, 1.74 ],
	[ 'alioth', 'Alioth', 12.9005, 55.9598, 1.76 ],
	[ 'mirfak', 'Mirfak', 3.4054, 49.8612, 1.79 ],
	[ 'dubhe', 'Dubhe', 11.0621, 61.7508, 1.79 ],
	[ 'kaus', 'Kaus Australis', 18.4029, -34.3846, 1.79 ],
	[ 'wezen', 'Wezen', 7.1399, -26.3932, 1.83 ],
	[ 'alkaid', 'Alkaid', 13.7923, 49.3133, 1.86 ],
	[ 'sargas', 'Sargas', 17.6219, -42.9978, 1.86 ],
	[ 'menkalinan', 'Menkalinan', 5.9921, 44.9474, 1.9 ],
	[ 'avior', 'Avior', 8.3752, -59.5095, 1.86 ],
	[ 'alhena', 'Alhena', 6.6285, 16.3993, 1.93 ],
	[ 'peacock', 'Peacock', 20.4275, -56.7351, 1.94 ],
	[ 'mirzam', 'Mirzam', 6.3783, -17.9559, 1.98 ],
	[ 'alphard', 'Alphard', 9.4598, -8.6586, 1.98 ],
	[ 'hamal', 'Hamal', 2.1196, 23.4624, 2.0 ],
	[ 'diphda', 'Diphda', 0.7265, -17.9866, 2.04 ],
	[ 'nunki', 'Nunki', 18.9211, -26.2967, 2.05 ],
	[ 'menkent', 'Menkent', 14.1114, -36.37, 2.06 ],
	[ 'alpheratz', 'Alpheratz', 0.1398, 29.0904, 2.06 ],
	[ 'mirach', 'Mirach', 1.1622, 35.6206, 2.07 ],
	[ 'kochab', 'Kochab', 14.8451, 74.1555, 2.08 ],
	[ 'saiph', 'Saiph', 5.7959, -9.6696, 2.09 ],
	[ 'rasalhague', 'Rasalhague', 17.5822, 12.56, 2.08 ],
	[ 'algol', 'Algol', 3.1361, 40.9556, 2.12 ],
	[ 'denebola', 'Denebola', 11.8177, 14.5721, 2.14 ],
	[ 'mizar', 'Mizar', 13.3987, 54.9254, 2.23 ],
	[ 'merak', 'Merak', 11.0307, 56.3824, 2.37 ],
	[ 'phecda', 'Phecda', 11.8972, 53.6948, 2.44 ],
	[ 'megrez', 'Megrez', 12.2571, 57.0326, 3.31 ],
	[ 'mintaka', 'Mintaka', 5.5334, -0.2991, 2.23 ],
	[ 'scheat', 'Scheat', 23.0629, 28.0828, 2.42 ],
	[ 'markab', 'Markab', 23.0794, 15.2053, 2.49 ],
	[ 'algenib', 'Algenib', 0.2206, 15.1836, 2.84 ],
	[ 'sadr', 'Sadr', 20.3705, 40.2567, 2.23 ],
	[ 'gienah', 'Gienah', 20.7702, 33.9703, 2.48 ],
	[ 'albireo', 'Albireo', 19.512, 27.9597, 3.05 ],
];

const CONSTELLATIONS = [
	[
		'Ursa Major',
		[
			[ 'dubhe', 'merak' ],
			[ 'merak', 'phecda' ],
			[ 'phecda', 'megrez' ],
			[ 'megrez', 'alioth' ],
			[ 'alioth', 'mizar' ],
			[ 'mizar', 'alkaid' ],
			[ 'megrez', 'dubhe' ],
		],
	],
	[
		'Orion',
		[
			[ 'betelgeuse', 'bellatrix' ],
			[ 'bellatrix', 'mintaka' ],
			[ 'mintaka', 'alnilam' ],
			[ 'alnilam', 'alnitak' ],
			[ 'alnitak', 'saiph' ],
			[ 'saiph', 'rigel' ],
			[ 'rigel', 'bellatrix' ],
			[ 'betelgeuse', 'alnitak' ],
		],
	],
	[
		'Gemini',
		[
			[ 'castor', 'pollux' ],
			[ 'castor', 'alhena' ],
			[ 'pollux', 'alhena' ],
		],
	],
	[
		'Taurus',
		[
			[ 'aldebaran', 'elnath' ],
			[ 'aldebaran', 'hamal' ],
		],
	],
	[
		'Perseus',
		[
			[ 'mirfak', 'algol' ],
			[ 'algol', 'alpheratz' ],
		],
	],
	[
		'Pegasus',
		[
			[ 'alpheratz', 'scheat' ],
			[ 'scheat', 'markab' ],
			[ 'markab', 'algenib' ],
			[ 'algenib', 'alpheratz' ],
		],
	],
	[
		'Cygnus',
		[
			[ 'deneb', 'sadr' ],
			[ 'sadr', 'gienah' ],
			[ 'sadr', 'albireo' ],
			[ 'gienah', 'albireo' ],
		],
	],
	[
		'Summer Triangle',
		[
			[ 'vega', 'deneb' ],
			[ 'deneb', 'altair' ],
			[ 'altair', 'vega' ],
		],
	],
	[
		'Ursa Minor',
		[
			[ 'polaris', 'kochab' ],
			[ 'kochab', 'dubhe' ],
		],
	],
	[
		'Scorpius',
		[
			[ 'antares', 'sargas' ],
			[ 'sargas', 'kaus' ],
		],
	],
	[
		'Sagittarius',
		[
			[ 'kaus', 'nunki' ],
			[ 'nunki', 'sargas' ],
		],
	],
];

const PLANET_ELEMENTS = {
	Mercury: [
		48.3313, 3.24587e-5, 7.0047, 5e-8, 29.1241, 1.01444e-5, 0.387098, 0,
		0.205635, 5.59e-10, 168.6562, 4.0923344368,
	],
	Venus: [
		76.6799, 2.4659e-5, 3.3946, 2.75e-8, 54.891, 1.38374e-5, 0.72333, 0,
		0.006773, -1.302e-9, 48.0052, 1.6021302244,
	],
	Earth: [
		0, 0, 0, 0, 282.9404, 4.70935e-5, 1, 0, 0.016709, -1.151e-9, 356.047,
		0.9856002585,
	],
	Mars: [
		49.5574, 2.11081e-5, 1.8497, -1.78e-8, 286.5016, 2.92961e-5, 1.523688,
		0, 0.093405, 2.516e-9, 18.6021, 0.5240207766,
	],
	Jupiter: [
		100.4542, 2.76854e-5, 1.303, -1.557e-7, 273.8777, 1.64505e-5, 5.20256,
		0, 0.048498, 4.469e-9, 19.895, 0.0830853001,
	],
	Saturn: [
		113.6634, 2.3898e-5, 2.4886, -1.081e-7, 339.3939, 2.97661e-5, 9.55475,
		0, 0.055546, -9.499e-9, 316.967, 0.0334442282,
	],
	Uranus: [
		74.0005, 1.3978e-5, 0.7733, 1.9e-8, 96.6612, 3.0565e-5, 19.18171,
		-1.55e-8, 0.047318, 7.45e-9, 142.5905, 0.011725806,
	],
	Neptune: [
		131.7806, 3.0173e-5, 1.77, -2.55e-7, 272.8461, -6.027e-6, 30.05826,
		3.313e-8, 0.008606, 2.15e-9, 260.2471, 0.005995147,
	],
};

const mod = ( value, base = 360 ) => ( ( value % base ) + base ) % base;
const round = ( value ) => Number( value.toFixed( 6 ) );

export function setNightSkyCatalog( catalog ) {
	detailedCatalog =
		Array.isArray( catalog?.stars ) &&
		Array.isArray( catalog?.constellations )
			? catalog
			: null;
}

function parseMoment( date, time, utcOffset ) {
	if (
		! /^\d{4}-\d{2}-\d{2}$/.test( date || '' ) ||
		! /^\d{2}:\d{2}$/.test( time || '' )
	) {
		return null;
	}
	const [ year, month, day ] = date.split( '-' ).map( Number );
	const [ hour, minute ] = time.split( ':' ).map( Number );
	if (
		year < 1900 ||
		year > 2100 ||
		month < 1 ||
		month > 12 ||
		day < 1 ||
		hour < 0 ||
		hour > 23 ||
		minute < 0 ||
		minute > 59
	) {
		return null;
	}
	const localMoment = new Date(
		Date.UTC( year, month - 1, day, hour, minute )
	);
	if (
		localMoment.getUTCFullYear() !== year ||
		localMoment.getUTCMonth() !== month - 1 ||
		localMoment.getUTCDate() !== day
	) {
		return null;
	}
	const offset = Math.max( -840, Math.min( 840, Number( utcOffset ) || 0 ) );
	const stamp =
		Date.UTC( year, month - 1, day, hour, minute ) - offset * 60000;
	const parsed = new Date( stamp );
	return Number.isFinite( stamp ) &&
		parsed.getUTCFullYear() >= 1900 &&
		parsed.getUTCFullYear() <= 2100
		? parsed
		: null;
}

function project( raDeg, decDeg, latitude, lst ) {
	const lat = latitude * DEG;
	const dec = decDeg * DEG;
	const hourAngle = mod( lst - raDeg + 180 ) - 180;
	const h = hourAngle * DEG;
	const sinAlt =
		Math.sin( dec ) * Math.sin( lat ) +
		Math.cos( dec ) * Math.cos( lat ) * Math.cos( h );
	const altitude = Math.asin( Math.max( -1, Math.min( 1, sinAlt ) ) );
	if ( altitude < 0 ) {
		return null;
	}
	const azimuth = Math.atan2(
		-Math.sin( h ) * Math.cos( dec ),
		Math.sin( dec ) * Math.cos( lat ) -
			Math.cos( dec ) * Math.sin( lat ) * Math.cos( h )
	);
	const radius = ( 1 - altitude / ( Math.PI / 2 ) ) * 0.48;
	return {
		x: round( 0.5 + radius * Math.sin( azimuth ) ),
		y: round( 0.5 - radius * Math.cos( azimuth ) ),
	};
}

function heliocentric( name, days ) {
	const e = PLANET_ELEMENTS[ name ];
	const N = ( e[ 0 ] + e[ 1 ] * days ) * DEG;
	const i = ( e[ 2 ] + e[ 3 ] * days ) * DEG;
	const w = ( e[ 4 ] + e[ 5 ] * days ) * DEG;
	const a = e[ 6 ] + e[ 7 ] * days;
	const eccentricity = e[ 8 ] + e[ 9 ] * days;
	const M = mod( e[ 10 ] + e[ 11 ] * days ) * DEG;
	let E =
		M + eccentricity * Math.sin( M ) * ( 1 + eccentricity * Math.cos( M ) );
	for ( let n = 0; n < 5; n++ ) {
		E -=
			( E - eccentricity * Math.sin( E ) - M ) /
			( 1 - eccentricity * Math.cos( E ) );
	}
	const xv = a * ( Math.cos( E ) - eccentricity );
	const yv = a * Math.sqrt( 1 - eccentricity * eccentricity ) * Math.sin( E );
	const v = Math.atan2( yv, xv );
	const r = Math.hypot( xv, yv );
	const vw = v + w;
	return {
		x:
			r *
			( Math.cos( N ) * Math.cos( vw ) -
				Math.sin( N ) * Math.sin( vw ) * Math.cos( i ) ),
		y:
			r *
			( Math.sin( N ) * Math.cos( vw ) +
				Math.cos( N ) * Math.sin( vw ) * Math.cos( i ) ),
		z: r * Math.sin( vw ) * Math.sin( i ),
	};
}

function planetRaDec( name, days ) {
	const earth = heliocentric( 'Earth', days );
	const planet = heliocentric( name, days );
	// The Earth element set yields the Sun's geocentric vector in this
	// low-precision model, so add it to each heliocentric planet vector.
	const x = planet.x + earth.x;
	const y = planet.y + earth.y;
	const z = planet.z + earth.z;
	const obliquity = ( 23.4393 - 3.563e-7 * days ) * DEG;
	const equY = y * Math.cos( obliquity ) - z * Math.sin( obliquity );
	const equZ = y * Math.sin( obliquity ) + z * Math.cos( obliquity );
	return {
		ra: mod( Math.atan2( equY, x ) * RAD ),
		dec: Math.atan2( equZ, Math.hypot( x, equY ) ) * RAD,
	};
}

export function generateNightSkyGeometry( input = {}, settings = {} ) {
	const moment = parseMoment( input.date, input.time, input.utcOffset );
	const hasLatitude =
		input.latitude !== null &&
		input.latitude !== undefined &&
		input.latitude !== '';
	const hasLongitude =
		input.longitude !== null &&
		input.longitude !== undefined &&
		input.longitude !== '';
	const latitude = hasLatitude ? Number( input.latitude ) : Number.NaN;
	const longitude = hasLongitude ? Number( input.longitude ) : Number.NaN;
	if (
		! moment ||
		! Number.isFinite( latitude ) ||
		! Number.isFinite( longitude ) ||
		latitude < -90 ||
		latitude > 90 ||
		longitude < -180 ||
		longitude > 180
	) {
		return null;
	}
	const julian = moment.getTime() / 86400000 + 2440587.5;
	const daysJ2000 = julian - 2451543.5;
	const lst = mod(
		280.46061837 + 360.98564736629 * ( julian - 2451545 ) + longitude
	);
	const stars = [];
	const points = new Map();
	if ( detailedCatalog ) {
		for ( const [ ra, dec, magnitude ] of detailedCatalog.stars ) {
			const point = project( ra, dec, latitude, lst );
			if ( ! point ) {
				continue;
			}
			stars.push( {
				...point,
				r: round(
					Math.max(
						0.0015,
						Math.min( 0.007, 0.0062 - magnitude * 0.0011 )
					)
				),
			} );
		}
	} else {
		for ( const [ id, , ra, dec, magnitude ] of STARS ) {
			const point = project( ra * 15, dec, latitude, lst );
			if ( ! point ) {
				continue;
			}
			points.set( id, point );
			stars.push( {
				...point,
				r: round(
					Math.max(
						0.0015,
						Math.min( 0.007, 0.0062 - magnitude * 0.0011 )
					)
				),
			} );
		}
	}
	const segments = [];
	const labels = [];
	if ( settings.show_constellations !== false ) {
		for ( const [ id, rank, paths ] of detailedCatalog?.constellations ||
			[] ) {
			const used = [];
			for ( const path of paths ) {
				for ( let index = 1; index < path.length; index++ ) {
					const a = project(
						path[ index - 1 ][ 0 ],
						path[ index - 1 ][ 1 ],
						latitude,
						lst
					);
					const b = project(
						path[ index ][ 0 ],
						path[ index ][ 1 ],
						latitude,
						lst
					);
					if ( a && b && Math.hypot( a.x - b.x, a.y - b.y ) < 0.45 ) {
						segments.push( {
							x1: a.x,
							y1: a.y,
							x2: b.x,
							y2: b.y,
							w: 0.0012,
						} );
						used.push( a, b );
					}
				}
			}
			if ( settings.show_labels !== false && rank === 1 && used.length ) {
				labels.push( {
					x: round(
						used.reduce( ( sum, p ) => sum + p.x, 0 ) / used.length
					),
					y: round(
						used.reduce( ( sum, p ) => sum + p.y, 0 ) / used.length
					),
					text: CONSTELLATION_NAMES[ id ] || id,
					size: 0.015,
				} );
			}
		}
		if ( ! detailedCatalog ) {
			for ( const [ name, pairs ] of CONSTELLATIONS ) {
				const used = [];
				for ( const [ from, to ] of pairs ) {
					const a = points.get( from );
					const b = points.get( to );
					if ( a && b && Math.hypot( a.x - b.x, a.y - b.y ) < 0.45 ) {
						segments.push( {
							x1: a.x,
							y1: a.y,
							x2: b.x,
							y2: b.y,
							w: 0.0015,
						} );
						used.push( a, b );
					}
				}
				if ( settings.show_labels !== false && used.length ) {
					labels.push( {
						x: round(
							used.reduce( ( sum, p ) => sum + p.x, 0 ) /
								used.length
						),
						y: round(
							used.reduce( ( sum, p ) => sum + p.y, 0 ) /
								used.length
						),
						text: name,
						size: 0.018,
					} );
				}
			}
		}
	}
	if ( settings.show_planets !== false ) {
		for ( const name of Object.keys( PLANET_ELEMENTS ).filter(
			( item ) => item !== 'Earth'
		) ) {
			const equatorial = planetRaDec( name, daysJ2000 );
			const point = project(
				equatorial.ra,
				equatorial.dec,
				latitude,
				lst
			);
			if ( point ) {
				stars.push( { ...point, r: 0.0065, planet: name } );
				if ( settings.show_labels !== false ) {
					labels.push( {
						x: round( point.x + 0.012 ),
						y: round( point.y - 0.01 ),
						text: name,
						size: 0.022,
					} );
				}
			}
		}
	}
	return {
		v: 1,
		coordinateSpace: 'unit-box-v1',
		stars,
		segments,
		labels,
		border: settings.show_border !== false,
	};
}

export function nightSkyLabel( input = {} ) {
	const label = [ input.locationLabel, input.date, input.time ]
		.filter( Boolean )
		.join( ' · ' );
	return [ ...label ].slice( 0, 200 ).join( '' );
}
