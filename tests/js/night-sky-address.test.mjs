import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { JSDOM } from 'jsdom';

const source = readFileSync(
	new URL(
		'../../src/frontend/customiser/input-controls.js',
		import.meta.url
	),
	'utf8'
);
const handlers = source.slice(
	source.indexOf( 'const cancelSearch = () =>' ),
	source.indexOf(
		'\n\t\t\t\tupdate();\n\t\t\t} );',
		source.indexOf( 'const cancelSearch = () =>' )
	)
);
const result = {
	displayName: 'Sydney, Australia',
	latitude: -33.8688,
	longitude: 151.2093,
};

function fixture( t, { timeout = false } = {} ) {
	const dom = new JSDOM(
		`<div id="root"><div id="address"><input id="label"><div id="results" hidden></div><button data-oc-night-sky-use-coordinates></button></div><div id="coordinates" hidden><input id="latitude"><input id="longitude"><button data-oc-night-sky-use-address></button></div><input id="date"></div><button id="outside"></button>`
	);
	t.after( () => dom.window.close() );
	const { document } = dom.window;
	const fields = Object.fromEntries(
		[
			[ 'locationLabel', 'label' ],
			[ 'latitude', 'latitude' ],
			[ 'longitude', 'longitude' ],
		].map( ( [ key, id ] ) => [ key, document.getElementById( id ) ] )
	);
	let timer;
	let resolve;
	let request;
	let saved;
	const app = {
		clearStateTimeout() {
			timer = null;
		},
		setStateTimeout( callback ) {
			timer = callback;
			return 1;
		},
		createStateAbortController() {
			request = {
				controller: new dom.window.AbortController(),
				release() {},
				timedOut: () => timeout,
			};
			if ( timeout ) {
				request.controller.abort();
			}
			return request;
		},
		async ensureRequestToken() {},
		restHeaders: ( headers ) => headers,
		data: { locationLookupUrl: '/lookup' },
	};
	const results = document.getElementById( 'results' );
	new Function(
		'document',
		'fields',
		'root',
		'addressMode',
		'coordinateMode',
		'resultsEl',
		'stateSignal',
		'fetch',
		'DOMException',
		`let searchTimer = null, searchSequence = 0, locationRequest = null;
		const locationSearchCache = new Map(); const error = null; const update = arguments[9];
		${ handlers }`
	).call(
		app,
		document,
		fields,
		document.getElementById( 'root' ),
		document.getElementById( 'address' ),
		document.getElementById( 'coordinates' ),
		results,
		new dom.window.AbortController().signal,
		() =>
			new Promise( ( done ) => {
				resolve = done;
			} ),
		dom.window.DOMException,
		() => {
			saved = Object.fromEntries(
				Object.entries( fields ).map( ( [ key, field ] ) => [
					key,
					field.value,
				] )
			);
		}
	);
	return {
		document,
		fields,
		results,
		get saved() {
			return saved;
		},
		get request() {
			return request;
		},
		type() {
			fields.locationLabel.value = 'Sydney';
			fields.locationLabel.dispatchEvent(
				new dom.window.Event( 'input' )
			);
		},
		async start() {
			const pending = timer();
			await Promise.resolve();
			return { pending };
		},
		respond() {
			resolve( {
				ok: true,
				json: async () => ( { results: [ result ] } ),
			} );
		},
		event( type, options ) {
			return new dom.window.MouseEvent( type, {
				bubbles: true,
				cancelable: true,
				...options,
			} );
		},
	};
}

test( 'address option preserves focus on pointer-down and selects only on click', async ( t ) => {
	const f = fixture( t );
	f.fields.locationLabel.focus();
	f.type();
	const { pending } = await f.start();
	f.respond();
	await pending;
	const option = f.results.querySelector( 'button' );
	const down = f.event( 'pointerdown', { button: 0 } );
	option.dispatchEvent( down );
	assert.equal( down.defaultPrevented, true );
	assert.equal( f.fields.latitude.value, '' );
	option.dispatchEvent( f.event( 'pointercancel' ) );
	assert.equal( f.fields.latitude.value, '' );
	option.click();
	assert.deepEqual( f.saved, {
		locationLabel: result.displayName,
		latitude: '-33.868800',
		longitude: '151.209300',
	} );
	assert.equal( f.results.hidden, true );
} );

for ( const dismissal of [ 'outside', 'escape', 'coordinates', 'date' ] ) {
	test( `${ dismissal } dismissal prevents late results reopening`, async ( t ) => {
		const f = fixture( t );
		f.type();
		const { pending } = await f.start();
		if ( dismissal === 'escape' ) {
			f.fields.locationLabel.dispatchEvent(
				new f.document.defaultView.KeyboardEvent( 'keydown', {
					key: 'Escape',
				} )
			);
		} else if ( dismissal === 'coordinates' ) {
			f.document
				.querySelector( '[data-oc-night-sky-use-coordinates]' )
				.click();
		} else {
			f.document.getElementById( dismissal ).click();
		}
		assert.equal( f.request.controller.signal.aborted, true );
		f.respond();
		await pending;
		assert.equal( f.results.hidden, true );
		assert.equal( f.results.hasAttribute( 'aria-busy' ), false );
	} );
}

test( 'refocusing an unchanged query restores cached suggestions', async ( t ) => {
	const f = fixture( t );
	f.type();
	const { pending } = await f.start();
	f.respond();
	await pending;
	f.document.getElementById( 'outside' ).click();
	assert.equal( f.results.hidden, true );
	f.fields.locationLabel.focus();
	assert.equal( f.results.hidden, false );
	assert.equal( f.results.querySelectorAll( 'button' ).length, 1 );
} );

test( 'timeout during token lookup clears busy status and displays failure', async ( t ) => {
	const f = fixture( t, { timeout: true } );
	f.type();
	const { pending } = await f.start();
	await pending;
	assert.equal( f.results.hasAttribute( 'aria-busy' ), false );
	assert.match( f.results.textContent, /unavailable/ );
} );
