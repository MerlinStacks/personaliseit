import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import { JSDOM } from 'jsdom';

const conditionSource = await readFile(
	'src/shared/layer-conditions.js',
	'utf8'
);
const { layerIsShown } = await import(
	`data:text/javascript;base64,${ Buffer.from( conditionSource ).toString(
		'base64'
	) }`
);
async function methods( path, name ) {
	const source = await readFile( path, 'utf8' );
	return new Function(
		'layerIsShown',
		source
			.replace( /^import\s[\s\S]*?;\n/gm, '' )
			.replace( `export default ${ name };`, `return ${ name };` )
	)( layerIsShown );
}
const preflight = await methods(
	'src/frontend/customiser/preflight.js',
	'preflightMethods'
);
const serialization = await methods(
	'src/frontend/customiser/cart-serialization.js',
	'cartSerializationMethods'
);
const checkout = await methods(
	'src/frontend/customiser/checkout.js',
	'checkoutMethods'
);
const controls = await methods(
	'src/frontend/customiser/input-controls.js',
	'inputControlMethods'
);

function linkedApp( type = 'text' ) {
	const first = {
		...target,
		type,
		settings: { ...target.settings, link_group: 'shared' },
	};
	const second = {
		...first,
		id: 3,
		label: 'Back',
		settings: { ...first.settings, condition_clipart_ids: [ 11 ] },
	};
	return {
		...controls,
		...preflight,
		...serialization,
		...checkout,
		areas: [ { layers: [ source, first ] }, { layers: [ second ] } ],
		inputs: {
			1: { clipartId: 11 },
			2: { value: '', fontId: 1 },
			3: { value: '', fontId: 1 },
		},
		fonts: [ { id: 1 } ],
		getLayerById( id ) {
			return this.areas
				.flatMap( ( area ) => area.layers )
				.find( ( layer ) => Number( layer.id ) === Number( id ) );
		},
		areaIndexForLayer( id ) {
			return this.areas.findIndex( ( area ) =>
				area.layers.some( ( layer ) => layer.id === id )
			);
		},
		clearPreflightMessages() {},
		clampLayerInputValue() {},
		updateLinkedLayerControls() {},
		normaliseLayerTextValue( id, value ) {
			return value;
		},
	};
}

test( 'shared linked controls remain usable when only a later member is shown', async ( t ) => {
	const dom = new JSDOM(
		'<section data-oc-condition-layer="2"><input data-oc-layer-text="2" required value=""><button disabled>Busy</button></section>'
	);
	const previous = globalThis.document;
	globalThis.document = dom.window.document;
	t.after( () => {
		globalThis.document = previous;
		dom.window.close();
	} );
	const app = linkedApp();
	const section = document.querySelector( 'section' );
	const field = document.querySelector( 'input' );
	field.setCustomValidity( 'Stale error for the hidden first layer' );
	app.syncConditionalLayers();
	assert.equal( section.hidden, false );
	assert.equal( field.disabled, false );
	assert.equal( field.validity.customError, false );
	assert.equal( field.validity.valueMissing, true );
	assert.equal( app.runImmediateBlockingPreflight().ok, false );
	field.value = 'Shared name';
	app.inputs[ 2 ].value = field.value;
	app.syncLinkedLayerInput( 2, [ 'value' ], { redraw: false } );
	assert.equal( app.runImmediateBlockingPreflight().ok, true );
	assert.equal( ( await app.runPreflight() ).ok, true );
	const frozen = app.serialiseLayers( app.cloneSubmissionInputs() );
	assert.equal( frozen[ 2 ], undefined );
	assert.equal( frozen[ 3 ].value, 'Shared name' );
	app.inputs[ 1 ].clipartId = 0;
	app.syncConditionalLayers();
	assert.equal( section.hidden, true );
	assert.equal( field.disabled, true );
	assert.equal( app.runImmediateBlockingPreflight().ok, true );
	app.inputs[ 1 ].clipartId = 10;
	app.syncConditionalLayers();
	assert.equal( section.hidden, false );
	assert.equal( field.disabled, false );
	assert.equal( field.value, 'Shared name' );
	assert.equal( document.querySelector( 'button' ).disabled, true );
	app.getLayerById( 3 ).settings.required = false;
	app.inputs[ 1 ].clipartId = 11;
	app.syncConditionalLayers();
	assert.equal( field.required, false );
	app.inputs[ 1 ].clipartId = 10;
	app.syncConditionalLayers();
	assert.equal( field.required, true );
} );

test( 'artwork blockers follow visible linked images, not only the shared control layer', () => {
	for ( const type of [ 'image', 'ai_image', 'clipmask' ] ) {
		const app = linkedApp( type );
		app._artworkOperations = new Set( [ { layerId: 2 } ] );
		app.failedArtworkReplacements = new Set( [ 2 ] );
		app.aiFilterErrors = { 2: 'Failed' };
		assert.deepEqual( app.visibleArtworkBlockers(), {
			pending: true,
			failed: true,
			filterError: true,
		} );
		app.inputs[ 1 ].clipartId = 0;
		assert.deepEqual( app.visibleArtworkBlockers(), {
			pending: false,
			failed: false,
			filterError: false,
		} );
		app.inputs[ 1 ].clipartId = 11;
		for ( const override of [
			{ locked: true },
			{ visible: false },
			{ settings: { link_group: 'other' } },
			{ type: 'text' },
		] ) {
			const second = app.getLayerById( 3 );
			app.areas[ 1 ].layers[ 0 ] = { ...second, ...override };
			assert.deepEqual( app.visibleArtworkBlockers(), {
				pending: false,
				failed: false,
				filterError: false,
			} );
			app.areas[ 1 ].layers[ 0 ] = second;
		}
	}
} );

test( 'Conditions tab lists only the source layer’s permitted clipart and escapes labels', async () => {
	const sourceText = await readFile(
		'src/admin/products-page-settings.js',
		'utf8'
	);
	const factory = new Function(
		'window',
		sourceText
			.replace( /import[^;]+;/g, '' )
			.replace( 'export function', 'function' ) +
			'; return createProductsPageSettings;'
	);
	const clipartLayer = {
		id: 0,
		type: 'clipart',
		label: '<Art>',
		settings: { condition_key: 'new-art', clipart_groups: [ 2 ] },
	};
	const dependent = {
		type: 'text',
		settings: {
			condition_enabled: true,
			condition_source_key: 'new-art',
			condition_clipart_ids: [ 10 ],
		},
	};
	const area = { method: 'uv', layers: [ clipartLayer, dependent ] };
	const ui = factory( {
		ocProductsData: {
			clipartItems: [
				{
					id: 10,
					name: '<Heart>',
					groupIds: [ 2 ],
					allowedPrintMethods: [ 'uv' ],
				},
				{ id: 11, name: 'Wrong group', groupIds: [ 3 ] },
				{
					id: 12,
					name: 'Wrong method',
					groupIds: [ 2 ],
					allowedPrintMethods: [ 'engraving' ],
				},
			],
		},
	} )( {
		getAreas: () => [ area ],
		selectedArea: () => area,
		esc: ( value ) =>
			String( value ).replaceAll( '<', '&lt;' ).replaceAll( '>', '&gt;' ),
	} );
	const html = ui.buildTabContent( 'conditions', dependent );
	assert.match( html, /Enable conditions/ );
	assert.match( html, /oc-set-condition-operator/ );
	assert.match( html, /value="is" selected/ );
	assert.match( html, /value="is_not"/ );
	assert.match( html, /oc-set-condition-action/ );
	assert.match( html, /value="show" selected/ );
	assert.match( html, /value="hide"/ );
	assert.match( html, /&lt;Art&gt;/ );
	assert.match( html, /value="10" selected/ );
	assert.match( html, /&lt;Heart&gt;/ );
	assert.doesNotMatch( html, /Wrong group|Wrong method|<Heart>/ );
} );
const source = {
	id: 1,
	type: 'clipart',
	settings: { condition_key: 'art', default_clipart_id: 10 },
};
const target = {
	id: 2,
	type: 'text',
	label: 'Name',
	settings: {
		required: true,
		condition_enabled: true,
		condition_source_key: 'art',
		condition_clipart_ids: [ 10, 12 ],
	},
};

test( 'hidden artwork failures and pending work do not block checkout', () => {
	const app = {
		...checkout,
		areas: [ { layers: [ source, target ] } ],
		inputs: { 1: { clipartId: 11 } },
		failedArtworkReplacements: new Set( [ 2 ] ),
		aiFilterErrors: { 2: 'Failed' },
		_artworkOperations: new Set( [ { layerId: 2 } ] ),
	};
	assert.deepEqual( app.visibleArtworkBlockers(), {
		pending: false,
		failed: false,
		filterError: false,
	} );
	app.inputs[ 1 ].clipartId = 10;
	assert.deepEqual( app.visibleArtworkBlockers(), {
		pending: true,
		failed: true,
		filterError: true,
	} );
} );

test( 'clipart conditions support defaults, multiple options and switching away', () => {
	assert.equal( layerIsShown( target, [ source, target ], {} ), true );
	for ( const id of [ 10, '12', 0, 11 ] ) {
		assert.equal(
			layerIsShown( target, [ source, target ], {
				1: { clipartId: id },
			} ),
			[ 10, '12' ].includes( id )
		);
	}
	const locked = { ...source, locked: true };
	assert.equal(
		layerIsShown( target, [ locked, target ], { 1: { clipartId: 11 } } ),
		false
	);
} );

test( 'locked and fixed clipart conditions use resolved fallback inputs, not removed defaults', () => {
	for ( const locked of [ false, true ] ) {
		for ( const allowChange of [ false, true ] ) {
			const controller = {
				...source,
				locked,
				settings: {
					...source.settings,
					allow_clipart_change: allowChange,
				},
			};
			for ( const operator of [ 'is', 'is_not' ] ) {
				for ( const action of [ 'show', 'hide' ] ) {
					const dependent = {
						...target,
						settings: {
							...target.settings,
							condition_operator: operator,
							condition_action: action,
						},
					};
					for ( const id of [ 10, 11, 12, 0 ] ) {
						const matches = [ 10, 12 ].includes( id );
						const applies =
							operator === 'is_not' ? ! matches : matches;
						assert.equal(
							layerIsShown(
								dependent,
								[ controller, dependent ],
								{
									1: { clipartId: id },
								}
							),
							action === 'hide' ? ! applies : applies
						);
					}
				}
			}
			assert.equal(
				layerIsShown( target, [ controller, target ], {} ),
				true
			);
		}
	}
} );

test( 'resolved fixed clipart fallback controls required-note preflight and frozen submission', async () => {
	const controller = {
		...source,
		locked: true,
		settings: { ...source.settings, allow_clipart_change: false },
	};
	const note = { ...target, type: 'staff_note' };
	const app = {
		...preflight,
		...serialization,
		areas: [ { layers: [ controller, note ] } ],
		inputs: { 1: { clipartId: 11 }, 2: { value: '' } },
		clearPreflightMessages() {},
		getLayerInputEl() {
			return null;
		},
		textLength: ( value ) => value.length,
	};
	assert.equal( app.runImmediateBlockingPreflight().ok, true );
	assert.equal( ( await app.runPreflight() ).ok, true );
	assert.equal(
		app.serialiseLayers( app.cloneSubmissionInputs() )[ 2 ],
		undefined
	);
	app.inputs[ 1 ].clipartId = 12;
	assert.equal( app.runImmediateBlockingPreflight().ok, false );
	assert.equal( ( await app.runPreflight() ).ok, false );
	app.inputs[ 2 ].value = 'Keep this instruction';
	assert.equal( app.runImmediateBlockingPreflight().ok, true );
	assert.equal( ( await app.runPreflight() ).ok, true );
	assert.deepEqual( app.serialiseLayers( app.cloneSubmissionInputs() )[ 2 ], {
		type: 'staff_note',
		value: 'Keep this instruction',
	} );
} );

test( 'missing, duplicate, hidden and circular sources stay hidden', () => {
	assert.equal( layerIsShown( target, [ target ], {} ), false );
	assert.equal(
		layerIsShown( target, [ source, { ...source, id: 3 }, target ], {} ),
		false
	);
	assert.equal(
		layerIsShown( target, [ { ...source, visible: false }, target ], {} ),
		false
	);
	const cyclic = {
		...source,
		settings: {
			...source.settings,
			condition_enabled: true,
			condition_source_key: 'art',
			condition_clipart_ids: [ 10 ],
		},
	};
	assert.equal( layerIsShown( target, [ cyclic, target ], {} ), false );
} );

test( 'is and is not support show and hide actions, including no clipart selection', () => {
	for ( const operator of [ 'is', 'is_not' ] ) {
		for ( const action of [ 'show', 'hide' ] ) {
			const layer = {
				...target,
				settings: {
					...target.settings,
					condition_operator: operator,
					condition_action: action,
				},
			};
			for ( const id of [ 10, 12, 11, 0 ] ) {
				const matches = [ 10, 12 ].includes( id );
				const applies = operator === 'is_not' ? ! matches : matches;
				assert.equal(
					layerIsShown( layer, [ source, layer ], {
						1: { clipartId: id },
					} ),
					action === 'hide' ? ! applies : applies,
					`${ operator } / ${ action } / ${ id }`
				);
			}
			assert.equal( layerIsShown( layer, [ layer ], {} ), false );
			const incomplete = {
				...layer,
				settings: { ...layer.settings, condition_clipart_ids: [] },
			};
			assert.equal(
				layerIsShown( incomplete, [ source, incomplete ], {} ),
				false
			);
		}
	}
} );

test( 'hide rules exempt required staff notes from validation and submission only while hidden', async () => {
	for ( const operator of [ 'is', 'is_not' ] ) {
		const layer = {
			...target,
			type: 'staff_note',
			settings: {
				...target.settings,
				condition_operator: operator,
				condition_action: 'hide',
			},
		};
		const app = {
			...preflight,
			...serialization,
			areas: [ { layers: [ source, layer ] } ],
			inputs: { 1: { clipartId: 10 }, 2: { value: '' } },
			clearPreflightMessages() {},
			getLayerInputEl() {
				return null;
			},
			textLength: ( value ) => value.length,
		};
		for ( const id of [ 10, 11 ] ) {
			app.inputs[ 1 ].clipartId = id;
			const hidden = operator === 'is' ? id === 10 : id !== 10;
			assert.equal( app.runImmediateBlockingPreflight().ok, hidden );
			assert.equal( ( await app.runPreflight() ).ok, hidden );
			assert.equal(
				app.serialiseLayers( app.inputs )[ 2 ] === undefined,
				hidden
			);
		}
	}
} );

test( 'hidden required fields pass both preflights and are excluded from frozen submissions', async () => {
	const app = {
		...preflight,
		...serialization,
		areas: [ { layers: [ source, target ] } ],
		inputs: { 1: { clipartId: 11 }, 2: { value: '' } },
		clearPreflightMessages() {},
		getLayerInputEl() {
			return null;
		},
		normaliseLayerTextValue( id, value ) {
			return value;
		},
	};
	assert.equal( app.runImmediateBlockingPreflight().ok, true );
	assert.equal( ( await app.runPreflight() ).ok, true );
	assert.equal( app.serialiseLayers( app.inputs )[ 2 ], undefined );
	app.inputs[ 1 ].clipartId = 10;
	assert.equal( app.runImmediateBlockingPreflight().ok, false );
	assert.equal( ( await app.runPreflight() ).ok, false );
	assert.equal( app.serialiseLayers( app.inputs )[ 2 ].type, 'text' );
} );

test( 'hiding disables native validation, clears stale validity and retains entered values', ( t ) => {
	const dom = new JSDOM(
		'<section data-oc-condition-layer="2"><input required value="Saved name"><button disabled>Busy</button></section>'
	);
	const previous = globalThis.document;
	globalThis.document = dom.window.document;
	t.after( () => {
		globalThis.document = previous;
		dom.window.close();
	} );
	const app = {
		...serialization,
		areas: [ { layers: [ source, target ] } ],
		inputs: { 1: { clipartId: 11 }, 2: { value: 'Saved name' } },
	};
	const field = document.querySelector( 'input' );
	field.setCustomValidity( 'Required' );
	app.syncConditionalLayers();
	assert.equal( field.disabled, true );
	assert.equal( field.validationMessage, '' );
	assert.equal( document.querySelector( 'section' ).hidden, true );
	app.inputs[ 1 ].clipartId = 10;
	app.syncConditionalLayers();
	assert.equal( field.disabled, false );
	assert.equal( field.value, 'Saved name' );
	assert.equal( document.querySelector( 'button' ).disabled, true );
} );
