import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { readFile } from 'node:fs/promises';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';
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
const amounts = [ -1, -2 / 3, -0.5, -0.25, 0, 0.1, 0.25, 0.5, 2 / 3, 1 ];
// Alpha is GD's 7-bit inverse alpha; compare it separately from RGB.
const pixels = [
	[ 255, 0, 0, 0 ],
	[ 0, 255, 0, 0 ],
	[ 0, 0, 255, 0 ],
	[ 0, 0, 0, 0 ],
	[ 255, 255, 255, 0 ],
	[ 128, 128, 128, 64 ],
	[ 12, 100, 240, 64 ],
	[ 40, 60, 80, 127 ],
	[ 231, 97, 18, 32 ],
];

function storefrontHue( amount ) {
	const filters = [];
	methods.addConfiguredImageFilter( filters, { key: 'hue', value: amount } );
	assert.equal( filters.length, 1 );
	assert.ok( filters[ 0 ] instanceof FabricFilters.HueRotation );
	assert.equal( filters[ 0 ].rotation, amount );
	filters[ 0 ].calculateMatrix();
	return filters[ 0 ];
}

function fabricPixels( filter ) {
	const data = new Uint8ClampedArray( pixels.flat() );
	filter.applyTo2d( { imageData: { data } } );
	return pixels.map( ( _, i ) =>
		Array.from( data.slice( i * 4, i * 4 + 4 ) )
	);
}

test( 'stored hue amounts retain Fabric half-turn semantics and direction', () => {
	assert.deepEqual( fabricPixels( storefrontHue( 0 ) ), pixels );
	assert.deepEqual(
		fabricPixels( storefrontHue( 1 ) )[ 0 ],
		[ 0, 170, 170, 0 ]
	);
	assert.deepEqual(
		fabricPixels( storefrontHue( 2 / 3 ) )[ 0 ],
		[ 0, 255, 0, 0 ]
	);
	assert.deepEqual(
		fabricPixels( storefrontHue( -2 / 3 ) )[ 0 ],
		[ 0, 0, 255, 0 ]
	);
} );

test( 'admin uses the storefront matrix in sRGB and removes stale definitions', () => {
	const { window } = new JSDOM(
		'<div id="preview"></div><div id="other"></div>'
	);
	try {
		const create = vm.runInNewContext(
			previewCode + '; createLayerPreviewRenderer',
			{
				window,
				document: window.document,
				FabricFilters,
			}
		);
		const render = create( {} );
		const el = window.document.getElementById( 'preview' );
		const other = window.document.getElementById( 'other' );
		const layer = ( id ) => ( {
			type: 'image',
			settings: {
				default_attachment_url: 'https://example.com/photo.png',
				default_image_filter_id: id,
			},
		} );
		for ( const amount of amounts ) {
			window.ocProductsData = {
				imageFilters: [ { id: 7, key: 'hue', value: amount } ],
			};
			render( layer( 7 ), el, 100, 100, false, false );
			const definition = el.querySelector( 'filter' );
			assert.equal( el.querySelectorAll( 'filter' ).length, 1 );
			assert.equal(
				definition.getAttribute( 'color-interpolation-filters' ),
				'sRGB'
			);
			assert.equal(
				el.querySelector( 'img' ).style.filter,
				`url(#${ definition.id })`
			);
			const matrix = el
				.querySelector( 'feColorMatrix' )
				.getAttribute( 'values' )
				.split( ' ' )
				.map( Number );
			assert.deepEqual( matrix, storefrontHue( amount ).matrix );
			assert.deepEqual(
				fabricPixels( new FabricFilters.ColorMatrix( { matrix } ) ),
				fabricPixels( storefrontHue( amount ) )
			);
			render( layer( 7 ), other, 100, 100, false, false );
			assert.notEqual(
				other.querySelector( 'filter' ).id,
				definition.id
			);
		}
		for ( const id of [ 0, 999 ] ) {
			render( layer( id ), el, 100, 100, false, false );
			assert.equal( el.querySelector( 'filter' ), null );
			assert.equal( el.querySelector( 'img' ).style.filter, '' );
		}
		render( layer( 7 ), el, 100, 100, false, true );
		assert.equal( el.querySelector( 'filter' ), null );
		assert.ok( el.querySelector( 'img' ).style.filter );
	} finally {
		window.close();
	}
} );

test( 'GD hue pixels match real Fabric across signed amounts, clipping and alpha', () => {
	const actual = JSON.parse(
		execFileSync(
			'php',
			[
				fileURLToPath(
					new URL( '../hue-print-pixels.php', import.meta.url )
				),
			],
			{
				input: JSON.stringify(
					amounts.map( ( amount ) => ( { amount, pixels } ) )
				),
				encoding: 'utf8',
			}
		)
	);
	amounts.forEach( ( amount, i ) => {
		assert.deepEqual(
			actual[ i ],
			fabricPixels( storefrontHue( amount ) ),
			`amount ${ amount }`
		);
	} );
} );
