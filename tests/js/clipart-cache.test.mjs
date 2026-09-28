import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import { JSDOM } from 'jsdom';

const source = await readFile(
	'src/frontend/customiser/canvas-renderer.js',
	'utf8'
);
const createMethods = new Function(
	'fetch',
	'window',
	source
		.replace( /import\s*\{[\s\S]*?\}\s*from\s*'[^']+';/g, '' )
		.replace(
			'export default canvasRendererMethods;',
			'return canvasRendererMethods;'
		)
);

test( 'clipart revalidates browser caches and treats new content revisions as new artwork', async ( t ) => {
	const dom = new JSDOM();
	t.after( () => dom.window.close() );
	const requests = [];
	const methods = createMethods( async ( url, options ) => {
		assert.equal( options.cache, 'no-cache' );
		requests.push( url );
		return new Response(
			`<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><text>${
				url.endsWith( 'new' ) ? 'New' : 'Old'
			}</text></svg>`
		);
	}, dom.window );
	const app = {
		...methods,
		clipartSvgCache: {},
		createStateAbortController: () => ( {
			controller: new AbortController(),
			release() {},
		} ),
	};
	const old = await app.recolourSvgClipartUrl(
		'/art.svg?oc_media=old',
		'#ff0000'
	);
	assert.equal(
		await app.recolourSvgClipartUrl( '/art.svg?oc_media=old', '#ff0000' ),
		old
	);
	const current = await app.recolourSvgClipartUrl(
		'/art.svg?oc_media=new',
		'#ff0000'
	);
	assert.notEqual( old, current );
	assert.match( decodeURIComponent( current ), /New/ );
	assert.equal( requests.length, 2 );
} );

test( 'failed SVG requests are retryable rather than permanently memoized as a fallback', async ( t ) => {
	const dom = new JSDOM();
	t.after( () => dom.window.close() );
	let requests = 0;
	const methods = createMethods( async ( url, options ) => {
		assert.equal( options.cache, 'no-cache' );
		requests += 1;
		return requests === 1
			? new Response( '', { status: 503 } )
			: new Response(
					'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect width="10" height="10"/></svg>'
			  );
	}, dom.window );
	const app = {
		...methods,
		clipartSvgCache: {},
		createStateAbortController: () => ( {
			controller: new AbortController(),
			release() {},
		} ),
	};
	assert.equal( await app.normaliseSvgClipartUrl( '/art.svg' ), '/art.svg' );
	assert.match(
		await app.normaliseSvgClipartUrl( '/art.svg' ),
		/^data:image\/svg\+xml/
	);
	assert.equal( requests, 2 );
} );
