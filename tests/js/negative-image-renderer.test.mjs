import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { createRequire } from 'node:module';
import test from 'node:test';
import vm from 'node:vm';
import { filters as FabricFilters } from 'fabric';
import { JSDOM } from 'jsdom';

const { parse } = createRequire( import.meta.url )( 'acorn' );
async function rendererCode( path ) {
	const source = await readFile( new URL( path, import.meta.url ), 'utf8' );
	const tree = parse( source, {
		ecmaVersion: 'latest',
		sourceType: 'module',
	} );
	return tree.body
		.filter( ( node ) => node.type !== 'ImportDeclaration' )
		.map( ( node ) =>
			source.slice( node.declaration?.start ?? node.start, node.end )
		)
		.join( '\n' );
}

const canvasCode = await rendererCode(
	'../../src/frontend/customiser/canvas-renderer.js'
);
const previewCode = await rendererCode(
	'../../src/admin/products-page-preview.js'
);
const methods = vm.runInNewContext( canvasCode + '; canvasRendererMethods', {
	FabricFilters,
} );

test( 'negative dispatches real Fabric inversion, preserving alpha regardless of value', () => {
	for ( const value of [ undefined, 0, 0.5, 1, -1 ] ) {
		const filters = [];
		methods.addConfiguredImageFilter( filters, { key: 'negative', value } );
		assert.equal( filters.length, 1 );
		assert.ok( filters[ 0 ] instanceof FabricFilters.Invert );
		const data = new Uint8ClampedArray( [
			0, 0, 0, 255, 255, 255, 255, 255, 12, 100, 240, 128, 40, 60, 80, 0,
		] );
		filters[ 0 ].applyTo2d( { imageData: { data } } );
		assert.deepEqual(
			Array.from( data ),
			[
				255, 255, 255, 255, 0, 0, 0, 255, 243, 155, 15, 128, 215, 195,
				175, 0,
			]
		);
	}
} );

test( 'negative appends to existing effects and safely handles unavailable Invert', () => {
	const existing = new FabricFilters.Grayscale();
	const filters = [ existing ];
	methods.addConfiguredImageFilter( filters, { key: 'negative' } );
	assert.equal( filters[ 0 ], existing );
	assert.equal( filters.length, 2 );
	const unavailable = vm.runInNewContext(
		canvasCode + '; canvasRendererMethods',
		{
			FabricFilters: {},
		}
	);
	unavailable.addConfiguredImageFilter( filters, { key: 'negative' } );
	assert.equal( filters.length, 2 );
} );

test( 'admin image preview applies full negative and clears it for Original or unknown filters', () => {
	const { window } = new JSDOM( '<div id="preview"></div>' );
	try {
		window.ocProductsData = {
			imageFilters: [ { id: 7, key: 'negative', value: 0, isAi: false } ],
		};
		const create = vm.runInNewContext(
			previewCode + '; createLayerPreviewRenderer',
			{
				window,
				document: window.document,
			}
		);
		const render = create( {} );
		const el = window.document.getElementById( 'preview' );
		for ( const [ id, expected ] of [
			[ '7', 'invert(1)' ],
			[ 0, '' ],
			[ 999, '' ],
		] ) {
			render(
				{
					type: 'image',
					settings: {
						default_attachment_url: 'https://example.com/photo.png',
						default_image_filter_id: id,
					},
				},
				el,
				100,
				100,
				false,
				false
			);
			assert.equal( el.querySelectorAll( 'img' ).length, 1 );
			assert.equal( el.querySelector( 'img' ).style.filter, expected );
		}
	} finally {
		window.close();
	}
} );
