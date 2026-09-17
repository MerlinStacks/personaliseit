import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import { JSDOM } from 'jsdom';

const source = await readFile( 'src/frontend/customiser-app.js', 'utf8' );
const mixinNames = [ ...source.matchAll( /^import (\w+) from /gm ) ].map(
	( match ) => match[ 1 ]
);
const executable = source
	.replace( /^import .*;\n/gm, '' )
	.replace( /\bimport\(/g, 'loadModule(' );
const flush = () => new Promise( ( resolve ) => setImmediate( resolve ) );
const deferred = () => {
	let resolve;
	let reject;
	const promise = new Promise( ( yes, no ) => {
		resolve = yes;
		reject = no;
	} );
	return { promise, resolve, reject };
};

async function harness(
	t,
	{ readyState = 'loading', token, hydrate, load } = {}
) {
	const dom = new JSDOM( `<form><section id="oc-customiser-panel">
		<input id="address"><div id="oc-preflight-messages" hidden></div>
		</section><button type="submit">Cart</button>
		<button type="submit" disabled>Unavailable</button></form>` );
	t.after( () => dom.window.close() );
	const { window } = dom;
	const { document } = window;
	// Drain JSDOM's automatic readiness events before controlling startup.
	await flush();
	Object.defineProperty( document, 'readyState', { value: readyState } );
	window.ocCustomiserData = { areas: [ { layers: [] } ] };
	const instances = [];
	let imports = 0;
	let inputs = 0;
	const methods = Object.fromEntries(
		[
			'findGalleryImage',
			'seedLockedLayerDefaults',
			'seedTemplateImageDefaults',
			'seedLayerFontDefaults',
			'seedLinkedImageInputs',
			'seedLinkedColourInputs',
			'applyInputsToDOM',
			'setupVariationGalleryHandoff',
			'setupCartGalleryUnlock',
			'setupDesignVariantOptions',
			'setupClipartCarousels',
			'setupUploadZones',
			'applyInitialAiFilters',
			'setupFormSubmit',
			'setupStoreApiIntegration',
			'updateHiddenField',
			'setupDesignVariantCarousel',
			'renderDesignVariantThumbnails',
			'startCanvasInitialisation',
		].map( ( name ) => [ name, () => {} ] )
	);
	methods.ensureRequestToken = function () {
		instances.push( this );
		return token?.( this );
	};
	methods.hydrateLinkGroupCarry = function () {
		return hydrate?.( this );
	};
	methods.setupInputListeners = function () {
		document.getElementById( 'address' ).addEventListener(
			'input',
			() => {
				inputs += 1;
			},
			{ signal: this._panelListenerController.signal }
		);
	};
	const boot = new Function(
		'window',
		'document',
		'AbortController',
		'loadModule',
		...mixinNames,
		executable + '; return bootCustomiser;'
	)(
		window,
		document,
		window.AbortController,
		() => {
			imports += 1;
			return load ? load() : Promise.resolve( { default: {} } );
		},
		...mixinNames.map( ( _, index ) => ( index === 0 ? methods : {} ) )
	);
	return {
		window,
		document,
		instances,
		boot,
		panel: document.getElementById( 'oc-customiser-panel' ),
		root: document.getElementById( 'oc-preflight-messages' ),
		buttons: [ ...document.querySelectorAll( '[type="submit"]' ) ],
		get imports() {
			return imports;
		},
		get inputs() {
			return inputs;
		},
		ready() {
			document.dispatchEvent( new window.Event( 'DOMContentLoaded' ) );
		},
		input() {
			document
				.getElementById( 'address' )
				.dispatchEvent( new window.Event( 'input' ) );
		},
	};
}

test( 'keeps the panel inert through slow token and hydration, then enables attached handlers once', async ( t ) => {
	const token = deferred();
	const hydrate = deferred();
	const app = await harness( t, {
		token: () => token.promise,
		hydrate: () => hydrate.promise,
	} );
	assert.equal( app.imports, 0 );
	app.ready();
	await flush();
	assert.equal( app.panel.inert, true );
	assert.equal( app.panel.getAttribute( 'aria-busy' ), 'true' );
	app.ready();
	await app.boot( app.window.ocCustomiserData );
	assert.equal( app.imports, 2 );
	token.resolve();
	await flush();
	assert.equal( app.panel.inert, true );
	app.input();
	assert.equal( app.inputs, 0 );
	hydrate.resolve();
	await flush();
	assert.equal( app.panel.inert, false );
	assert.equal( app.panel.getAttribute( 'aria-busy' ), 'false' );
	app.ready();
	await app.boot( app.window.ocCustomiserData );
	app.input();
	assert.equal( app.inputs, 1 );
	assert.equal( app.instances.length, 1 );
} );

test( 'init rejection cleans partial state and offers a working single retry', async ( t ) => {
	const hydrate = deferred();
	const app = await harness( t, { hydrate: () => hydrate.promise } );
	app.ready();
	await flush();
	const failed = app.instances[ 0 ];
	const signal = failed._panelListenerController.signal;
	failed.setupInputListeners();
	let refreshed = false;
	failed._requestTokenRefreshTimer = app.window.setTimeout( () => {
		refreshed = true;
	}, 20 );
	hydrate.reject( new Error( 'Hydration failed' ) );
	await flush();
	assert.equal( signal.aborted, true );
	assert.equal( failed._designGeneration, 1 );
	assert.equal( app.panel.inert, false );
	assert.equal( app.root.hidden, false );
	assert.equal( app.root.querySelector( '[role="alert"]' ) !== null, true );
	assert.ok( app.buttons.every( ( button ) => button.disabled ) );
	app.input();
	assert.equal( app.inputs, 0 );
	const retryHydrate = deferred();
	hydrate.promise = retryHydrate.promise;
	const retry = app.root.querySelector( 'button' );
	retry.click();
	retry.click();
	await flush();
	assert.equal( app.panel.inert, true );
	assert.equal( app.instances.length, 2 );
	assert.equal( app.buttons[ 0 ].disabled, true );
	retryHydrate.resolve();
	await flush();
	assert.equal( app.root.hidden, true );
	assert.equal( app.panel.inert, false );
	assert.deepEqual(
		app.buttons.map( ( button ) => button.disabled ),
		[ false, true ]
	);
	app.input();
	assert.equal( app.inputs, 1 );
	await new Promise( ( resolve ) => setTimeout( resolve, 30 ) );
	assert.equal( refreshed, false );
} );

for ( const readyState of [ 'interactive', 'complete' ] ) {
	test( `starts immediately when document is ${ readyState }, without double boot`, async ( t ) => {
		const app = await harness( t, { readyState } );
		await flush();
		assert.equal( app.instances.length, 1 );
		assert.equal( app.panel.inert, false );
		app.ready();
		await app.boot( app.window.ocCustomiserData );
		app.input();
		assert.equal( app.inputs, 1 );
		assert.equal( app.imports, 2 );
	} );
}

test( 'core chunk rejection still offers retry before constructing an instance', async ( t ) => {
	let fail = true;
	const app = await harness( t, {
		load: () =>
			fail
				? Promise.reject( new Error( 'Offline' ) )
				: Promise.resolve( { default: {} } ),
	} );
	app.ready();
	await flush();
	assert.equal( app.instances.length, 0 );
	assert.equal( app.panel.inert, false );
	assert.equal( app.buttons[ 0 ].disabled, true );
	fail = false;
	app.root.querySelector( 'button' ).click();
	await flush();
	assert.equal( app.instances.length, 1 );
	assert.equal( app.root.hidden, true );
	assert.equal( app.buttons[ 0 ].disabled, false );
} );
