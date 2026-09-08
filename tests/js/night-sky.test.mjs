import assert from 'node:assert/strict';
import { mkdtemp, readFile, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';
import test from 'node:test';

const source = await readFile( 'src/shared/night-sky.js', 'utf8' );
const moduleDir = await mkdtemp( join( tmpdir(), 'oc-night-sky-' ) );
const modulePath = join( moduleDir, 'night-sky.mjs' );
const catalog = JSON.parse(
	await readFile( 'includes/data/night-sky-catalog.json', 'utf8' )
);
await writeFile( modulePath, source );
const { generateNightSkyGeometry, nightSkyLabel, setNightSkyCatalog } =
	await import( pathToFileURL( modulePath ) );
setNightSkyCatalog( catalog );
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
	assert.ok( first.stars.length > 250 );
	assert.ok( first.segments.length > 100 );
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

test( 'Night Sky date defaults to today', () => {
	assert.match( templateSource, /value="<\?php echo esc_attr\( wp_date/ );
	assert.match( controlsSource, /fields\.date\.value = localToday\(\)/ );
} );

test( 'place lookup uses the same-origin proxy only', () => {
	assert.doesNotMatch( controlsSource, /nominatim\.openstreetmap\.org/ );
	assert.match( controlsSource, /this\.data\.locationLookupUrl/ );
	assert.match( controlsSource, /method: 'POST'/ );
} );

test( 'address updates preserve typed spaces while normalizing saved labels', () => {
	const reader = controlsSource.slice(
		controlsSource.indexOf( 'function readNightSkyFields(' ),
		controlsSource.indexOf( 'const LINKED_IMAGE_INPUT_KEYS' )
	);
	const updateBody = controlsSource.match( /update = \(\) => \{([\s\S]*?)this\.regenerateNightSkyInput\( lid \);\s*\};/ )[ 1 ];
	const fields = {
		locationLabel: { value: '', classList: { remove() {} } },
		latitude: { value: '', classList: { remove() {} } },
		longitude: { value: '', classList: { remove() {} } },
	};
	const coordinateMode = { hidden: true };
	const app = { inputs: {} };
	const update = new Function( 'fields', 'coordinateMode', 'resolveTimezone', 'lid', `${ reader }\n${ updateBody }` );
	for ( const character of 'New South Wales ' ) {
		fields.locationLabel.value += character;
		update.call( app, fields, coordinateMode, () => {}, 1 );
	}
	assert.equal( fields.locationLabel.value, 'New South Wales ' );
	assert.equal( app.inputs[ 1 ].locationLabel, 'New South Wales' );
	coordinateMode.hidden = false;
	fields.latitude.value = '-33.8688';
	fields.longitude.value = '151.2093';
	update.call( app, fields, coordinateMode, () => {}, 1 );
	assert.equal( fields.locationLabel.value, '-33.8688, 151.2093' );
} );

test( 'place lookup aborts superseded requests and reuses recent results', () => {
	assert.match( controlsSource, /locationRequest\?\.controller\.abort\(\)/ );
	assert.match( controlsSource, /signal: request\.controller\.signal/ );
	assert.match( controlsSource, /const locationSearchCache = new Map\(\)/ );
	assert.match( controlsSource, /locationSearchCache\.has\( cacheKey \)/ );
	assert.match( controlsSource, /locationSearchCache\.size > 20/ );
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
