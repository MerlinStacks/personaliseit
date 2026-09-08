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

test( 'layer control lookup preserves selectors, document receiver and native results', () => {
	const lookup = new Function(
		'document',
		controlsSource.match( /function layerControl\([\s\S]*?\n\}/ )[ 0 ] +
			'; return layerControl;'
	);
	const first = {};
	const all = [ first, {} ];
	const queries = [];
	const document = {
		querySelector( selector ) {
			assert.equal( this, document );
			queries.push( selector );
			return selector.includes( 'missing' ) ? null : first;
		},
		querySelectorAll( selector ) {
			assert.equal( this, document );
			queries.push( selector );
			return all;
		},
	};
	const layerControl = lookup( document );
	assert.equal( layerControl( 'layer-font', 2 ), first );
	assert.equal( layerControl( 'layer-font', '02' ), first );
	assert.equal( layerControl( 'upload-zone', 2, true ), all );
	assert.equal( layerControl( 'missing', 2 ), null );
	assert.deepEqual( queries, [
		'[data-oc-layer-font="2"]',
		'[data-oc-layer-font="02"]',
		'[data-oc-upload-zone="2"]',
		'[data-oc-missing="2"]',
	] );
} );

test( 'restored filters update the first control while linked filters update all controls', () => {
	const previous = globalThis.document;
	const filters = [ { value: 'old' }, { value: 'old' } ];
	const zones = [ {}, {} ];
	const uploads = [];
	globalThis.document = {
		querySelector( selector ) {
			return selector === '[data-oc-layer-image-filter="2"]'
				? filters[ 0 ]
				: null;
		},
		querySelectorAll( selector ) {
			if ( selector === '[data-oc-layer-image-filter="2"]' ) {
				return filters;
			}
			return selector === '[data-oc-upload-zone="2"]' ? zones : [];
		},
	};
	try {
		const app = {
			...controls,
			inputs: { 2: { imageFilterId: 7, attachmentId: 10 } },
			updateHiddenField() {},
			setUploadZoneState( zone, state ) {
				uploads.push( [ zone, state ] );
			},
		};
		app.applyInputsToDOM( { redraw: false } );
		assert.deepEqual(
			filters.map( ( filter ) => filter.value ),
			[ '7', 'old' ]
		);
		assert.deepEqual(
			uploads,
			zones.map( ( zone ) => [ zone, 'uploaded' ] )
		);
		uploads.length = 0;
		app.inputs[ 2 ] = { imageFilterId: 0, attachmentId: 0 };
		app.updateLinkedLayerControls( 2, [ 'imageFilterId', 'attachmentId' ] );
		assert.deepEqual(
			filters.map( ( filter ) => filter.value ),
			[ '0', '0' ]
		);
		assert.deepEqual(
			uploads,
			zones.map( ( zone ) => [ zone, '' ] )
		);
	} finally {
		globalThis.document = previous;
	}
} );

test( 'font and size changes propagate to linked text layers within their limits', () => {
	const options = [
		{ value: '4', style: { fontFamily: 'Sans' } },
		{ value: '9', style: { fontFamily: 'Script' } },
	];
	const targetSelect = {
		options,
		style: {},
		selectedIndex: 0,
		get value() {
			return this.options[ this.selectedIndex ]?.value || '';
		},
		set value( value ) {
			this.selectedIndex = this.options.findIndex(
				( option ) => option.value === String( value )
			);
		},
	};
	const targetPreview = { style: {} };
	const targetSize = { min: '8', max: '30', value: '18' };
	const targetSizeValue = { textContent: '18' };
	const previous = globalThis.document;
	globalThis.document = {
		querySelector( selector ) {
			if ( selector === '[data-oc-layer-font="2"]' ) {
				return targetSelect;
			}
			if ( selector === '.oc-font-preview[data-oc-font-preview="2"]' ) {
				return targetPreview;
			}
			if ( selector === '[data-oc-layer-font-size="2"]' ) {
				return targetSize;
			}
			if ( selector === '.oc-range-value[data-oc-range-value="2"]' ) {
				return targetSizeValue;
			}
			return null;
		},
	};
	try {
		const layers = [
			{ id: 1, type: 'text', settings: { link_group: 'name' } },
			{
				id: 2,
				type: 'text',
				settings: { link_group: 'name', max_font_size: 36 },
			},
		];
		const app = {
			...controls,
			areas: [ { layers } ],
			inputs: {
				1: { fontId: 9, fontSize: 44 },
				2: { fontId: 4, fontSize: 18 },
			},
			fonts: [
				{ id: 4, name: 'Sans' },
				{ id: 9, name: 'Script' },
			],
			getLayerById( id ) {
				return layers.find( ( layer ) => layer.id === Number( id ) );
			},
			areaIndexForLayer() {
				return 0;
			},
			scheduleRedraw( areaIndex ) {
				this.redrawnArea = areaIndex;
			},
			updateFontCombobox( select ) {
				this.updatedFontSelect = select;
			},
			updateTextSizeSliderCap( layerId ) {
				this.updatedFontSizeLayer = layerId;
			},
		};

		app.syncLinkedLayerInput( 1, [ 'fontId' ] );
		app.syncLinkedLayerInput( 1, [ 'fontSize' ] );

		assert.equal( app.inputs[ 2 ].fontId, 9 );
		assert.equal( app.inputs[ 2 ].fontSize, 30 );
		assert.equal( targetSelect.value, '9' );
		assert.equal( targetSelect.style.fontFamily, 'Script' );
		assert.equal( targetPreview.style.fontFamily, 'Script' );
		assert.equal( targetSize.value, '30' );
		assert.equal( targetSizeValue.textContent, '30' );
		assert.equal( app.updatedFontSelect, targetSelect );
		assert.equal( app.updatedFontSizeLayer, 2 );
		assert.equal( app.redrawnArea, 0 );
		assert.match(
			controlsSource,
			/this\.syncLinkedLayerInput\( lid, \[ 'fontId' \] \);/
		);
		assert.match(
			controlsSource,
			/this\.syncLinkedLayerInput\( lid, \[ 'fontSize' \] \);/
		);
	} finally {
		globalThis.document = previous;
	}
} );

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
