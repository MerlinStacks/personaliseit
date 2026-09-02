import assert from 'node:assert/strict';
import { mkdtemp, readFile, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';
import test from 'node:test';

const source = await readFile( 'src/shared/night-sky.js', 'utf8' );
const moduleDir = await mkdtemp( join( tmpdir(), 'oc-night-sky-' ) );
const modulePath = join( moduleDir, 'night-sky.mjs' );
await writeFile( modulePath, source );
const { generateNightSkyGeometry, nightSkyLabel } = await import(
	pathToFileURL( modulePath )
);
const adminDataSource = await readFile(
	'src/admin/products-page-data.js',
	'utf8'
);
const controlsSource = await readFile(
	'src/frontend/customiser/input-controls.js',
	'utf8'
);
const templateSource = await readFile(
	'templates/frontend/customiser-panel.php',
	'utf8'
);

const london = {
	date: '2026-09-01',
	time: '22:00',
	utcOffset: 60,
	latitude: 51.5074,
	longitude: -0.1278,
	locationLabel: 'London, United Kingdom',
};

test( 'generates bounded deterministic vector geometry', () => {
	const first = generateNightSkyGeometry( london, {} );
	const second = generateNightSkyGeometry( london, {} );
	assert.deepEqual( first, second );
	assert.equal( first.v, 1 );
	assert.ok( first.stars.length > 10 );
	for ( const star of first.stars ) {
		assert.ok( star.x >= 0 && star.x <= 1 );
		assert.ok( star.y >= 0 && star.y <= 1 );
	}
} );

test( 'date and place alter the visible sky', () => {
	const londonSky = generateNightSkyGeometry( london, {} );
	const sydneySky = generateNightSkyGeometry(
		{ ...london, latitude: -33.8688, longitude: 151.2093 },
		{}
	);
	assert.notDeepEqual( londonSky.stars, sydneySky.stars );
} );

test( 'admin options remove optional geometry', () => {
	const geometry = generateNightSkyGeometry( london, {
		show_constellations: false,
		show_planets: false,
		show_labels: false,
		show_border: false,
	} );
	assert.deepEqual( geometry.segments, [] );
	assert.deepEqual( geometry.labels, [] );
	assert.equal( geometry.border, false );
	assert.equal(
		geometry.stars.some( ( star ) => star.planet ),
		false
	);
} );

test( 'rejects incomplete or out-of-range observations', () => {
	assert.equal(
		generateNightSkyGeometry( { ...london, date: '' }, {} ),
		null
	);
	assert.equal(
		generateNightSkyGeometry( { ...london, latitude: 91 }, {} ),
		null
	);
	assert.equal(
		generateNightSkyGeometry( { ...london, latitude: null }, {} ),
		null
	);
	assert.equal(
		generateNightSkyGeometry( { ...london, longitude: '' }, {} ),
		null
	);
	assert.equal(
		generateNightSkyGeometry( { ...london, date: '2026-02-31' }, {} ),
		null
	);
} );

test( 'builds a concise order label', () => {
	assert.equal(
		nightSkyLabel( london ),
		'London, United Kingdom · 2026-09-01 · 22:00'
	);
} );

test( 'truncates labels without splitting Unicode characters', () => {
	const label = nightSkyLabel( {
		locationLabel: '🌟'.repeat( 201 ),
	} );
	assert.equal( [ ...label ].length, 200 );
	assert.equal( label, '🌟'.repeat( 200 ) );
} );

test( 'new Night Sky layers are optional by default', () => {
	assert.match(
		adminDataSource,
		/case 'night_sky':[\s\S]*?required: false,/
	);
} );

test( 'place lookup uses the same-origin proxy only', () => {
	assert.doesNotMatch( controlsSource, /nominatim\.openstreetmap\.org/ );
	assert.match( controlsSource, /this\.data\.locationLookupUrl/ );
	assert.match( controlsSource, /method: 'POST'/ );
} );

test( 'customer chooses an address or coordinates without seeing UTC controls', () => {
	assert.doesNotMatch( templateSource, /UTC offset at that date/ );
	assert.doesNotMatch( templateSource, /Place search © OpenStreetMap/ );
	assert.match( templateSource, /data-oc-night-sky-results/ );
	assert.match( templateSource, /Searching addresses…/ );
	assert.match( templateSource, /data-oc-night-sky-use-coordinates/ );
	assert.match( templateSource, /data-oc-night-sky-use-address/ );
	assert.match( controlsSource, /timezoneLookup/ );
	assert.match( controlsSource, /localUtcOffset/ );
	assert.match( controlsSource, /showResultStatus/ );
	assert.match( controlsSource, /}, 350 \);/ );
} );
