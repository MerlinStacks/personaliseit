import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const source = await readFile( 'src/frontend/customiser-app.js', 'utf8' );
const classSource = source.slice(
	source.indexOf( 'class OCCustomiser {' ),
	source.indexOf( '\nObject.assign(' )
);
const uploadSource = await readFile(
	'src/frontend/customiser/uploads.js',
	'utf8'
);
const uploadMethods = new Function(
	uploadSource
		.replace( /^import .*;\n/gm, '' )
		.replace( 'export default uploadMethods;', 'return uploadMethods;' )
)();
const oldToken = 'A'.repeat( 64 );
const newToken = 'B'.repeat( 64 );
const json = ( body, status = 200 ) =>
	new Response( JSON.stringify( body ), {
		status,
		headers: { 'Content-Type': 'application/json' },
	} );
const rejected = ( code = 'invalid_token' ) => json( { code }, 403 );

function fixture( fetch, data = {} ) {
	const prototype = new Function(
		'fetch',
		'setTimeout',
		'clearTimeout',
		classSource + '; return OCCustomiser.prototype;'
	)(
		fetch,
		() => 1,
		() => {}
	);
	return Object.assign( Object.create( prototype ), {
		data: {
			requestToken: oldToken,
			requestTokenExpiresAt: Date.now() + 3600000,
			requestTokenUrl: '/token',
			restNonceUrl: '/nonce',
			uploadNonce: '',
			...data,
		},
		inputs: { 1: { value: 'Keep my customisation' } },
		createStateAbortController() {
			return {
				controller: new AbortController(),
				timedOut: () => false,
				release() {},
			};
		},
	} );
}

test( 'evicted guest tokens renew once for concurrent failures, preserving payloads and inputs', async () => {
	let refreshes = 0;
	let effects = 0;
	const payloads = [];
	const app = fixture( async ( url, options ) => {
		assert.equal( options.cache, 'no-store' );
		if ( url === '/token' ) {
			refreshes += 1;
			assert.equal( options.headers[ 'X-WP-Nonce' ], undefined );
			return json( { token: newToken, expires_in: 21600 } );
		}
		payloads.push( options.body );
		if ( options.headers[ 'X-OC-Token' ] === oldToken ) {
			return rejected();
		}
		effects += 1;
		return json( { ok: true } );
	} );
	const inputs = app.inputs;
	const responses = await Promise.all( [
		app.authenticatedFetch( '/ai', { method: 'POST', body: 'design-one' } ),
		app.authenticatedFetch( '/preview', {
			method: 'POST',
			body: 'design-two',
		} ),
	] );
	assert.ok( responses.every( ( response ) => response.ok ) );
	assert.equal( refreshes, 1 );
	assert.equal( effects, 2 );
	assert.deepEqual( payloads, [
		'design-one',
		'design-two',
		'design-one',
		'design-two',
	] );
	assert.equal( app.inputs, inputs );
} );

test( 'expired logged-in REST nonces renew through WordPress AJAX without guest downgrade', async () => {
	const requests = [];
	const app = fixture(
		async ( url, options ) => {
			requests.push( [ url, options ] );
			if ( url === '/nonce' ) {
				assert.equal( options.credentials, 'same-origin' );
				return new Response( '123456789a' );
			}
			return options.headers[ 'X-WP-Nonce' ] === 'old-nonce'
				? rejected( 'rest_cookie_invalid_nonce' )
				: json( { ok: true } );
		},
		{ uploadNonce: 'old-nonce' }
	);
	assert.equal(
		(
			await app.authenticatedFetch( '/ai', {
				method: 'POST',
				body: 'same-prompt',
				headers: app.restHeaders(),
			} )
		).status,
		200
	);
	assert.deepEqual(
		requests.map( ( request ) => request[ 0 ] ),
		[ '/ai', '/nonce', '/ai' ]
	);
	assert.equal( requests[ 2 ][ 1 ].headers[ 'X-WP-Nonce' ], '123456789a' );
	assert.equal( requests[ 2 ][ 1 ].body, 'same-prompt' );
} );

test( 'lost logins preserve customer work and do not retry as a guest', async () => {
	let writes = 0;
	const app = fixture(
		async ( url ) => {
			if ( url === '/nonce' ) {
				return new Response( '0', { status: 400 } );
			}
			writes += 1;
			return rejected( 'rest_cookie_invalid_nonce' );
		},
		{ uploadNonce: 'old-nonce' }
	);
	await assert.rejects( app.authenticatedFetch( '/ai' ), /sign in again/ );
	assert.equal( writes, 1 );
	assert.equal( app.data.uploadNonce, 'old-nonce' );
	assert.equal( app.inputs[ 1 ].value, 'Keep my customisation' );
} );

test( 'network, server and unrelated permission failures never replay a write', async () => {
	for ( const outcome of [
		new Error( 'Connection lost' ),
		json( {}, 500 ),
		rejected( 'invalid_origin' ),
		new Response( 'Forbidden', { status: 403 } ),
	] ) {
		let calls = 0;
		const app = fixture( async () => {
			calls += 1;
			if ( outcome instanceof Error ) {
				throw outcome;
			}
			return outcome;
		} );
		if ( outcome instanceof Error ) {
			await assert.rejects(
				app.authenticatedFetch( '/ai' ),
				/Connection lost/
			);
		} else {
			assert.equal( await app.authenticatedFetch( '/ai' ), outcome );
		}
		assert.equal( calls, 1 );
	}
} );

test( 'a second authentication rejection is returned, without a refresh loop', async () => {
	let calls = 0;
	const app = fixture( async ( url ) => {
		calls += 1;
		return url === '/token'
			? json( { token: newToken, expires_in: 21600 } )
			: rejected();
	} );
	assert.equal( ( await app.authenticatedFetch( '/ai' ) ).status, 403 );
	assert.equal( calls, 3 );
} );

test( 'a design switch during renewal cancels the rejected write instead of replaying it', async () => {
	const controller = new AbortController();
	let writes = 0;
	const app = fixture( async ( url ) => {
		if ( url === '/token' ) {
			controller.abort();
			return json( { token: newToken, expires_in: 21600 } );
		}
		writes += 1;
		return rejected();
	} );
	await assert.rejects(
		app.authenticatedFetch( '/ai', { signal: controller.signal } ),
		{ name: 'AbortError' }
	);
	assert.equal( writes, 1 );
} );

test( 'Uppy sends renewed headers once, without concatenating old and new credentials', async () => {
	const app = fixture( async () =>
		json( { token: newToken, expires_in: 21600 } )
	);
	const hooks = uploadMethods.uploadAuthenticationOptions.call( app );
	const file = {};
	const xhr = () => ( {
		status: 403,
		responseText: JSON.stringify( { code: 'invalid_token' } ),
		headers: {},
		setRequestHeader( name, value ) {
			assert.equal( this.headers[ name ], undefined );
			this.headers[ name ] = value;
		},
	} );
	const first = xhr();
	hooks.onBeforeRequest( first, 0, [ file ] );
	assert.deepEqual( hooks.headers, {} );
	await hooks.onAfterResponse( first );
	assert.equal( hooks.shouldRetry( first ), true );
	const retry = xhr();
	hooks.onBeforeRequest( retry, 1, [ file ] );
	assert.equal( retry.headers[ 'X-OC-Token' ], newToken );
	await hooks.onAfterResponse( retry );
	assert.equal( hooks.shouldRetry( retry ), false );
	assert.equal( hooks.shouldRetry( { status: 422 } ), false );
} );
