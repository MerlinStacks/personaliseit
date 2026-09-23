import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import vm from 'node:vm';
import { JSDOM } from 'jsdom';

const source = await readFile( 'src/frontend/customiser/design-variants.js', 'utf8' );
const deferred = () => {
	let resolve;
	const promise = new Promise( ( done ) => { resolve = done; } );
	return { promise, resolve };
};
const stateFor = ( id ) => ( { designId: id, panelHtml: '<div></div>', areas: [ { layers: [] } ] } );
const responseFor = ( id ) => ( {
	ok: true,
	json: async () => ( {
		active: true,
		selectedDesignVariant: String( id ),
		designVariantStates: { [ id ]: stateFor( id ) },
	} ),
} );

function runtime( t, fetcher ) {
	const dom = new JSDOM( [ 1, 2, 3 ].map( ( id ) => `
		<div class="oc-design-variant-option oc-thumb-pending">
			<canvas data-oc-design-variant-thumb="${ id }"></canvas>
		</div>` ).join( '' ), { url: 'https://shop.example/product' } );
	t.after( () => dom.window.close() );
	const calls = [];
	const requests = [];
	const rendered = [];
	const methods = vm.runInNewContext(
		source.replace( /^import .*;\n/gm, '' ).replace( 'export default designVariantMethods;', 'designVariantMethods;' ),
		{
			window: dom.window, document: dom.window.document, URL, DOMException,
			console: { warn() {} },
			fetch: ( url, options ) => {
				calls.push( { url: new URL( url ), options } );
				return fetcher( new URL( url ).searchParams.get( 'design_id' ), options );
			},
		}
	);
	const app = {
		...methods,
		_designGeneration: 1, _variationRequestSeq: 1,
		_activeVariationKey: '10', _pendingVariationKey: '',
		_designVariantRequestSeq: 5, _designVariantPendingSeq: 5,
		selectedDesignVariant: '1',
		data: { productId: 123, designVariantStates: { 1: stateFor( 1 ) } },
		productVariationStates: { 10: {} },
		designVariants: [ 1, 2, 3 ].map( ( id ) => ( { id: String( id ), designId: id } ) ),
		currentVariationId: () => 10,
		createStateAbortController() {
			const request = { controller: new AbortController(), timedOut: () => false, release() { this.released = true; } };
			requests.push( request );
			return request;
		},
		async renderDesignVariantThumbnailCanvas( canvas, state ) {
			rendered.push( state.designId );
			return Boolean( state.areas?.length );
		},
	};
	return { app, calls, requests, rendered, document: dom.window.document };
}

test( 'initial lazy payload fetches missing thumbnails and caches both states without selection side effects', async ( t ) => {
	const { app, calls, requests, rendered, document } = runtime( t, async ( id ) => responseFor( id ) );
	for ( const method of [ 'applyDesignState', 'setDesignVariantCartPending', 'updateDesignVariantUrl' ] ) {
		app[ method ] = () => assert.fail( `Unexpected ${ method }` );
	}
	await app.renderDesignVariantThumbnails();
	assert.deepEqual( calls.map( ( call ) => call.url.searchParams.get( 'design_id' ) ), [ '2', '3' ] );
	assert.ok( calls.every( ( call ) => call.url.searchParams.get( 'variant_id' ) === '10' ) );
	assert.deepEqual( rendered.map( Number ), [ 1, 2, 3 ] );
	for ( const id of [ 2, 3 ] ) {
		assert.equal( app.data.designVariantStates[ id ], app.productVariationStates[ 10 ].designVariantStates[ id ] );
	}
	assert.equal( document.querySelectorAll( '.oc-thumb-rendered' ).length, 3 );
	assert.equal( app.selectedDesignVariant, '1' );
	assert.equal( app._designVariantRequestSeq, 5 );
	assert.equal( app._designVariantPendingSeq, 5 );
	assert.ok( requests.every( ( request ) => request.released ) );
} );

for ( const failure of [ 'network', 'invalid', 'empty', 'render' ] ) {
	test( `${ failure } failure preserves fallback and continues other thumbnails`, async ( t ) => {
		const { app, document } = runtime( t, async ( id ) => {
			if ( id !== '2' ) { return responseFor( id ); }
			if ( failure === 'network' ) { throw new Error( 'offline' ); }
			if ( failure === 'invalid' ) { return responseFor( 999 ); }
			if ( failure === 'empty' ) {
				const response = responseFor( id );
				const body = await response.json();
				body.designVariantStates[ id ].areas = [];
				return { ok: true, json: async () => body };
			}
			return responseFor( id );
		} );
		const render = app.renderDesignVariantThumbnailCanvas;
		app.renderDesignVariantThumbnailCanvas = ( canvas, state ) => {
			if ( failure === 'render' && Number( state.designId ) === 2 ) { throw new Error( 'render failed' ); }
			return render( canvas, state );
		};
		await app.renderDesignVariantThumbnails();
		assert.equal( document.querySelectorAll( '.oc-thumb-pending' ).length, 0 );
		assert.equal( document.querySelector( '[data-oc-design-variant-thumb="2"]' ).dataset.ocThumbRendered, undefined );
		assert.equal( document.querySelector( '[data-oc-design-variant-thumb="3"]' ).dataset.ocThumbRendered, '1' );
		if ( failure !== 'render' ) { assert.equal( app.data.designVariantStates[ 2 ], undefined ); }
	} );
}

for ( const change of [ '_designGeneration', '_variationRequestSeq', '_activeVariationKey' ] ) {
	test( `${ change } change discards an in-flight response`, async ( t ) => {
		const pending = deferred();
		const started = deferred();
		const { app, rendered, calls } = runtime( t, () => { started.resolve(); return pending.promise; } );
		const loading = app.renderDesignVariantThumbnails();
		await started.promise;
		app[ change ] += 1;
		pending.resolve( responseFor( 2 ) );
		await loading;
		assert.equal( app.data.designVariantStates[ 2 ], undefined );
		assert.equal( app.productVariationStates[ 10 ].designVariantStates, undefined );
		assert.deepEqual( rendered, [ 1 ] );
		assert.equal( calls.length, 1 );
	} );
}

test( 'background requests leave interactive controller and sequence independent', async ( t ) => {
	const background = deferred();
	const interactive = deferred();
	const { app, requests } = runtime( t, ( id ) => id === '2' ? background.promise : interactive.promise );
	const selection = app.fetchDesignVariantState( app.designVariants[ 2 ], 5 );
	const controller = app._designVariantAbortController;
	const thumbnail = app.fetchDesignVariantState( app.designVariants[ 1 ], undefined, { background: true } );
	assert.equal( app._designVariantAbortController, controller );
	app._designVariantRequestSeq++;
	controller.abort();
	assert.equal( requests[ 1 ].controller.signal.aborted, false );
	background.resolve( responseFor( 2 ) );
	assert.equal( ( await thumbnail ).designId, 2 );
	assert.equal( app._designVariantAbortController, controller );
	interactive.resolve( responseFor( 3 ) );
	await assert.rejects( selection, { name: 'AbortError' } );
	assert.equal( app._designVariantAbortController, null );
	assert.ok( requests.every( ( request ) => request.released ) );
} );

test( 'generation changes during rendering do not mark the old thumbnail rendered', async ( t ) => {
	const pending = deferred();
	const { app, document } = runtime( t, async ( id ) => responseFor( id ) );
	app.renderDesignVariantThumbnailCanvas = async ( canvas, state, isCurrent ) => {
		await pending.promise;
		assert.equal( isCurrent(), false );
		return true;
	};
	const loading = app.renderDesignVariantThumbnails();
	app._designGeneration++;
	pending.resolve();
	await loading;
	assert.equal( document.querySelectorAll( '.oc-thumb-rendered' ).length, 0 );
} );

test( 'panel applied during a variation switch caches against the pending variation', async ( t ) => {
	const pending = deferred();
	const started = deferred();
	const { app } = runtime( t, async ( id ) => {
		if ( id === '2' ) { started.resolve(); await pending.promise; }
		return responseFor( id );
	} );
	app._activeVariationKey = '9';
	app._pendingVariationKey = '10';
	app.productVariationStates[ 9 ] = {};
	const loading = app.renderDesignVariantThumbnails();
	await started.promise;
	app._activeVariationKey = '10';
	app._pendingVariationKey = '';
	pending.resolve();
	await loading;
	assert.equal( app.productVariationStates[ 9 ].designVariantStates, undefined );
	assert.equal( app.productVariationStates[ 10 ].designVariantStates[ 2 ], app.data.designVariantStates[ 2 ] );
} );
