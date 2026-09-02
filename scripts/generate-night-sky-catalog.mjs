import { createHash } from 'node:crypto';
import { writeFile } from 'node:fs/promises';

const revision = '7e720a3de062059d4c5400a379146a601d9010e0';
const baseUrl = `https://raw.githubusercontent.com/ofrohn/d3-celestial/${ revision }/data`;
const sources = {
	stars: {
		url: `${ baseUrl }/stars.6.json`,
		sha256: '0297b8fa3adfbce1dc26566f61c4abcc1df4f29c6a28729ca06b56d1c6d25602',
	},
	constellations: {
		url: `${ baseUrl }/constellations.lines.json`,
		sha256: '294f66bef5d5cf50b1e17f16d2efa1d97a15131612c68dd935adef6e7373e13c',
	},
};

async function loadJson( source ) {
	const response = await fetch( source.url );
	if ( ! response.ok ) {
		throw new Error(
			`Could not fetch ${ source.url }: ${ response.status }`
		);
	}
	const body = await response.text();
	const digest = createHash( 'sha256' ).update( body ).digest( 'hex' );
	if ( digest !== source.sha256 ) {
		throw new Error( `Checksum mismatch for ${ source.url }` );
	}
	return JSON.parse( body );
}

const [ starData, constellationData ] = await Promise.all( [
	loadJson( sources.stars ),
	loadJson( sources.constellations ),
] );
const stars = starData.features
	.filter( ( feature ) => Number( feature.properties?.mag ) <= 4.2 )
	.map( ( feature ) => [
		feature.geometry.coordinates[ 0 ],
		feature.geometry.coordinates[ 1 ],
		feature.properties.mag,
	] )
	.sort( ( a, b ) => a[ 2 ] - b[ 2 ] );
const constellations = constellationData.features
	.filter( ( feature ) => Number( feature.properties?.rank ) <= 2 )
	.map( ( feature ) => [
		feature.id,
		Number( feature.properties.rank ),
		feature.geometry.coordinates,
	] );
const output = JSON.stringify( {
	source: `d3-celestial ${ revision } (BSD-3-Clause); see THIRD_PARTY_NOTICES.md`,
	stars,
	constellations,
} );
await writeFile( 'includes/data/night-sky-catalog.json', output );
