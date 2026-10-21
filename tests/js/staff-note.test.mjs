import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import { JSDOM } from 'jsdom';

const rules = await readFile( 'src/shared/layer-conditions.js', 'utf8' );
const { layerIsShown } = await import(
	`data:text/javascript;base64,${ Buffer.from( rules ).toString( 'base64' ) }`
);
async function loadMethods( file, name ) {
	const source = await readFile( file, 'utf8' );
	return new Function(
		'layerIsShown',
		source
			.replace( /^import\s[\s\S]*?;\n/gm, '' )
			.replace( `export default ${ name };`, `return ${ name };` )
	)( layerIsShown );
}
const preflight = await loadMethods(
	'src/frontend/customiser/preflight.js',
	'preflightMethods'
);
const serialization = await loadMethods(
	'src/frontend/customiser/cart-serialization.js',
	'cartSerializationMethods'
);
const controls = await loadMethods(
	'src/frontend/customiser/input-controls.js',
	'inputControlMethods'
);
const renderer = await loadMethods(
	'src/frontend/customiser/canvas-renderer.js',
	'canvasRendererMethods'
);
const note = {
	id: 2,
	type: 'staff_note',
	label: 'Instructions',
	settings: { required: true, char_limit: 20 },
};

test( 'staff-note editor offers label, character limit and required controls without print styling', async () => {
	const source = await readFile(
		'src/admin/products-page-settings.js',
		'utf8'
	);
	const factory = new Function(
		'window',
		source
			.replace( /^import\s[\s\S]*?;\n/gm, '' )
			.replace( 'export function', 'function' ) +
			'; return createProductsPageSettings;'
	)( {} );
	const ui = factory( {
		selectedArea: () => ( { method: 'uv' } ),
		esc: String,
	} );
	const general = ui.buildTabContent( 'general', note );
	assert.match( general, /oc-layer-label/ );
	assert.match( general, /Never shown on the design preview/ );
	assert.doesNotMatch(
		general,
		/oc-layer-x|oc-layer-w|oc-link-group|Additional cost/
	);
	assert.match( ui.buildTabContent( 'content', note ), /oc-set-char-limit/ );
	assert.match( ui.buildTabContent( 'validation', note ), /oc-set-required/ );
} );

test( 'staff notes validate without fonts or geometry and serialize only text', async () => {
	const app = {
		...preflight,
		...serialization,
		areas: [ { layers: [ note ] } ],
		inputs: { 2: { value: '', fontId: 123, attachmentId: 55 } },
		textLength: ( text ) => [ ...text ].length,
		clearPreflightMessages() {},
		getLayerInputEl() {
			return null;
		},
	};
	assert.equal( app.runImmediateBlockingPreflight().ok, false );
	assert.equal( ( await app.runPreflight() ).ok, false );
	app.inputs[ 2 ].value = 'Line one\nLine two';
	assert.equal( ( await app.runPreflight() ).ok, true );
	assert.deepEqual( app.serialiseLayers( app.inputs ), {
		2: { type: 'staff_note', value: 'Line one\nLine two' },
	} );
	app.inputs[ 2 ].value = 'x'.repeat( 21 );
	assert.equal( ( await app.runPreflight() ).ok, false );
	const conditional = {
		...note,
		settings: {
			...note.settings,
			condition_enabled: true,
			condition_source_key: 'missing',
			condition_clipart_ids: [ 10 ],
		},
	};
	app.areas[ 0 ].layers = [ conditional ];
	assert.equal( app.runImmediateBlockingPreflight().ok, true );
	assert.equal( ( await app.runPreflight() ).ok, true );
	assert.deepEqual( app.serialiseLayers( app.inputs ), {} );
} );

test( 'note layers never touch Fabric or production artwork in direct preview renders', async () => {
	await renderer.renderLayer.call(
		{},
		null,
		note,
		{ value: 'Do not print me' },
		null
	);
} );

test( 'customer note input saves and restores multiline values without preview work', ( t ) => {
	const dom = new JSDOM( '<textarea data-oc-layer-note="2"></textarea>' );
	const previous = {
		document: globalThis.document,
		window: globalThis.window,
	};
	Object.assign( globalThis, {
		document: dom.window.document,
		window: dom.window,
	} );
	t.after( () => {
		Object.assign( globalThis, previous );
		dom.window.close();
	} );
	let saves = 0;
	const app = {
		...controls,
		areas: [ { layers: [ note ] } ],
		inputs: { 2: { value: 'Saved\nNote' } },
		setupControlAccessibility() {},
		setupFontComboboxes() {},
		setupSpotifyModal() {},
		updateHiddenField() {
			saves++;
		},
		updateImageCropControl() {},
	};
	app.setupInputListeners();
	app.applyInputsToDOM( { redraw: false } );
	const field = document.querySelector( 'textarea' );
	assert.equal( field.value, 'Saved\nNote' );
	field.value = 'New\nInstructions';
	field.dispatchEvent( new dom.window.Event( 'input', { bubbles: true } ) );
	assert.equal( app.inputs[ 2 ].value, 'New\nInstructions' );
	assert.equal( saves, 2 );
	field.value = 'Autofilled';
	app.syncInputsFromDOM();
	assert.equal( app.inputs[ 2 ].value, 'Autofilled' );
} );
