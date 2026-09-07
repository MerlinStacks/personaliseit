import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const skySource = await readFile( 'src/shared/night-sky.js', 'utf8' );
// Evaluate the dependency-free modules without generated files or a bundler.
const sky = new Function(
	skySource.replace( /export function /g, 'function ' ) +
		'; return { generateNightSkyGeometry, nightSkyLabel, setNightSkyCatalog };'
)();
sky.setNightSkyCatalog(
	JSON.parse(
		await readFile( 'includes/data/night-sky-catalog.json', 'utf8' )
	)
);
const controlsSource = await readFile(
	'src/frontend/customiser/input-controls.js',
	'utf8'
);
const controls = new Function(
	'generateNightSkyGeometry',
	'nightSkyLabel',
	'setNightSkyCatalog',
	controlsSource
		.replace( /import \{[\s\S]*?;\n/, '' )
		.replace(
			'export default inputControlMethods;',
			'return inputControlMethods;'
		)
)( sky.generateNightSkyGeometry, sky.nightSkyLabel, sky.setNightSkyCatalog );
const preflight = new Function(
	(
		await readFile( 'src/frontend/customiser/preflight.js', 'utf8' )
	).replace( 'export default preflightMethods;', 'return preflightMethods;' )
)();

test( 'Original clears the effect while ordinary filters retain their selection', async () => {
	const source = await readFile(
		'src/frontend/customiser/uploads.js',
		'utf8'
	);
	const methods = new Function(
		source
			.replace( /import \{[^;]*;/, '' )
			.replace( 'export default uploadMethods;', 'return uploadMethods;' )
	)();
	for ( const filterId of [ 0, 8 ] ) {
		const app = {
			...methods,
			inputs: {
				1: {
					attachmentId: 20,
					baseAttachmentId: 10,
					baseAttachmentUrl: 'https://example.com/original.png',
					imageFilterId: 7,
				},
			},
			data: { imageFilters: [ { id: 8, isAi: false } ] },
			aiFilterErrors: {},
			aiFilterGenerations: {},
			aiFilterAbortControllers: {},
			_controlLocks: new Set(),
			updateLinkedLayerControls() {},
			syncLinkedImageInput() {},
			recordImageLinkGroupCarry() {},
			renderAiFilterResults() {},
			requestPreviewFocus() {},
			areaIndexForLayer() {
				return 0;
			},
			scheduleRedraw() {},
			updateHiddenField() {
				this.payload = { ...this.inputs[ 1 ] };
			},
		};
		assert.equal( await app.applyAiImageFilter( 1, filterId ), true );
		assert.equal( app.payload.attachmentId, 10 );
		assert.equal( app.payload.imageFilterId, filterId );
		await app.selectAiFilterResult( 1, 0 );
		assert.equal( app.payload.imageFilterId, 0 );
	}
} );

test( 'coordinate mode survives DOM serialization with a generated label and geometry', () => {
	const fields = Object.fromEntries(
		Object.entries( {
			date: '2026-09-07',
			time: '22:00',
			offset: '0',
			timezone: 'UTC',
			location: '',
			latitude: '51.5074',
			longitude: '-0.1278',
		} ).map( ( [ key, value ] ) => [
			`[data-oc-night-sky-${ key }]`,
			{ value },
		] )
	);
	fields[ '[data-oc-night-sky-coordinate-mode]' ] = { hidden: false };
	const root = { querySelector: ( selector ) => fields[ selector ] || null };
	const previous = globalThis.document;
	globalThis.document = {
		querySelector: ( selector ) =>
			selector === '[data-oc-night-sky-controls="1"]' ? root : null,
	};
	try {
		const app = {
			areas: [
				{ layers: [ { id: 1, type: 'night_sky', settings: {} } ] },
			],
			inputs: { 1: {} },
			updateHiddenField() {
				this.payload = structuredClone( this.inputs );
			},
		};
		controls.syncInputsFromDOM.call( app );
		assert.equal( app.payload[ 1 ].locationLabel, '51.5074, -0.1278' );
		assert.ok( app.payload[ 1 ].nightSkyGeometry.stars.length > 0 );
		assert.match( app.payload[ 1 ].nightSkyLabel, /51\.5074/ );
		fields[ '[data-oc-night-sky-coordinate-mode]' ].hidden = true;
		fields[ '[data-oc-night-sky-location]' ].value = '  London  ';
		fields[ '[data-oc-night-sky-offset]' ].value = '60';
		fields[ '[data-oc-night-sky-timezone]' ].value = 'Europe/London';
		controls.syncInputsFromDOM.call( app );
		assert.equal( app.payload[ 1 ].locationLabel, 'London' );
		assert.equal( app.payload[ 1 ].utcOffset, 60 );
		assert.equal( app.payload[ 1 ].timezone, 'Europe/London' );

		fields[ '[data-oc-night-sky-coordinate-mode]' ].hidden = false;
		fields[ '[data-oc-night-sky-latitude]' ].value = '0';
		fields[ '[data-oc-night-sky-longitude]' ].value = '0';
		controls.syncInputsFromDOM.call( app );
		assert.equal( app.payload[ 1 ].locationLabel, '0.0000, 0.0000' );
		fields[ '[data-oc-night-sky-longitude]' ].value = '';
		controls.syncInputsFromDOM.call( app );
		assert.equal( app.payload[ 1 ].latitude, 0 );
		assert.equal( app.payload[ 1 ].longitude, null );
		assert.equal( app.payload[ 1 ].locationLabel, '' );
		assert.equal( app.payload[ 1 ].nightSkyGeometry, null );
	} finally {
		globalThis.document = previous;
	}
} );

test( 'optional prepopulated sky is absent; required and partial locations block checkout', async () => {
	const layer = { id: 1, type: 'night_sky', settings: {} };
	const app = {
		areas: [ { layers: [ layer ] } ],
		inputs: {
			1: {
				date: '2026-09-07',
				time: '22:00',
				latitude: null,
				longitude: '',
			},
		},
		clearPreflightMessages() {},
		getLayerInputEl() {
			return null;
		},
	};
	assert.equal( ( await preflight.runPreflight.call( app ) ).ok, true );
	for ( const partial of [
		{ latitude: 0 },
		{ longitude: 0 },
		{ latitude: 'invalid' },
		{ locationLabel: 'London' },
	] ) {
		app.inputs[ 1 ] = {
			date: '2026-09-07',
			time: '22:00',
			latitude: null,
			longitude: '',
			...partial,
		};
		assert.equal( ( await preflight.runPreflight.call( app ) ).ok, false );
	}
	app.inputs[ 1 ] = {};
	layer.settings.required = true;
	assert.equal( ( await preflight.runPreflight.call( app ) ).ok, false );
} );
