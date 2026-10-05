import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { JSDOM } from 'jsdom';

const source = readFileSync(
	new URL(
		'../../src/frontend/customiser/clipart-pagination.js',
		import.meta.url
	),
	'utf8'
).replace( 'export default async function', 'return async function' );
function fixture( t, fetch ) {
	const dom = new JSDOM(
		'<div data-oc-clipart-grid="7"><button class="oc-clipart-item" data-oc-clipart="1">Existing</button></div><button data-oc-clipart-more="7"></button><p data-oc-clipart-status="7"></p>',
		{ url: 'https://example.test/product' }
	);
	t.after( () => dom.window.close() );
	const methods = new Function( 'document', 'window', 'fetch', source )(
		dom.window.document,
		dom.window,
		fetch
	);
	const app = {
		loadClipartPage: methods,
		data: {
			clipartPages: {
				7: {
					url: '/clipart/7?product_id=4',
					nextPage: 2,
					hasMore: true,
				},
			},
		},
		inputs: { 7: { clipartId: 125 } },
		clipartSearchTerms: {},
		clipartCategoryFilters: {},
		refreshClipartCarousel() {},
		createStateAbortController() {
			return {
				controller: new AbortController(),
				timedOut: () => false,
				release() {},
			};
		},
	};
	return { app, document: dom.window.document };
}
const response = ( ids, nextPage = 3 ) => ( {
	ok: true,
	json: async () => ( {
		items: ids.map( ( id ) => ( {
			id,
			name: `<Art ${ id }>`,
			url: '/art.svg',
			groupNames: [ 'Animals' ],
			recolourable: true,
		} ) ),
		nextPage,
		hasMore: false,
	} ),
} );

test( 'additional clipart pages deduplicate items and preserve customer selection', async ( t ) => {
	let requested;
	const f = fixture( t, async ( url ) => {
		requested = new URL( url );
		return response( [ 1, 125 ] );
	} );
	await f.app.loadClipartPage( 7 );
	assert.equal( requested.searchParams.get( 'page' ), '2' );
	assert.equal( requested.searchParams.get( 'product_id' ), '4' );
	assert.equal( f.document.querySelectorAll( '.oc-clipart-item' ).length, 2 );
	assert.equal(
		f.document
			.querySelector( '[data-oc-clipart="125"]' )
			.getAttribute( 'aria-pressed' ),
		'true'
	);
	assert.equal( f.document.querySelectorAll( 'art' ).length, 0 );
	assert.equal( f.app.inputs[ 7 ].clipartId, 125 );
	assert.equal(
		f.document.querySelector( '[data-oc-clipart-more]' ).hidden,
		true
	);
} );

test( 'search covers the server catalogue and superseded responses cannot replace newer results', async ( t ) => {
	const pending = [];
	const f = fixture(
		t,
		( url ) =>
			new Promise( ( resolve ) => pending.push( { url, resolve } ) )
	);
	f.app.clipartSearchTerms[ 7 ] = 'old';
	const old = f.app.loadClipartPage( 7, true );
	f.app.clipartSearchTerms[ 7 ] = 'new';
	const newer = f.app.loadClipartPage( 7, true );
	assert.equal( new URL( pending[ 1 ].url ).searchParams.get( 'page' ), '1' );
	pending[ 1 ].resolve( response( [ 3 ], 2 ) );
	await newer;
	pending[ 0 ].resolve( response( [ 2 ], 2 ) );
	await old;
	assert.ok( f.document.querySelector( '[data-oc-clipart="3"]' ) );
	assert.equal( f.document.querySelector( '[data-oc-clipart="2"]' ), null );
	assert.equal( f.app.inputs[ 7 ].clipartId, 125 );
} );

test( 'failed search keeps existing work and offers a first-page retry', async ( t ) => {
	const f = fixture( t, async () => {
		throw new Error( 'Offline' );
	} );
	await f.app.loadClipartPage( 7, true );
	assert.ok( f.document.querySelector( '[data-oc-clipart="1"]' ) );
	assert.equal( f.app.data.clipartPages[ 7 ].retryReset, true );
	assert.equal(
		f.document.querySelector( '[data-oc-clipart-more]' ).disabled,
		false
	);
	assert.match(
		f.document.querySelector( '[role="status"], [data-oc-clipart-status]' )
			.textContent,
		/retry/
	);
} );

test( 'switching designs discards in-flight clipart responses', async ( t ) => {
	let resolve;
	const f = fixture(
		t,
		() =>
			new Promise( ( done ) => {
				resolve = done;
			} )
	);
	const loading = f.app.loadClipartPage( 7 );
	f.app.data.clipartPages = {};
	resolve( response( [ 125 ] ) );
	await loading;
	assert.equal( f.document.querySelector( '[data-oc-clipart="125"]' ), null );
} );
