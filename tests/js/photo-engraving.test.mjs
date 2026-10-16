import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { createRequire } from 'node:module';
import test from 'node:test';
import vm from 'node:vm';
import { filters as FabricFilters } from 'fabric';

const { parse } = createRequire( import.meta.url )( 'acorn' );
const source = await readFile(
	'src/frontend/customiser/canvas-renderer.js',
	'utf8'
);
const tree = parse( source, { ecmaVersion: 'latest', sourceType: 'module' } );
const code = tree.body
	.filter( ( node ) => node.type !== 'ImportDeclaration' )
	.map( ( node ) =>
		source.slice( node.declaration?.start ?? node.start, node.end )
	)
	.join( '\n' );
const methods = vm.runInNewContext( code + '; canvasRendererMethods', {
	FabricFilters,
	Shadow: class {},
	imagePlacementScale: ( contain ) => contain,
	console,
} );
const paletteSource = await readFile(
	'src/frontend/customiser/engraving-preview.js',
	'utf8'
);
const palette = new Function(
	paletteSource
		.replace( /import\s*\{[\s\S]*?\}\s*from\s*'[^']+';/g, '' )
		.replace( /^export /gm, '' ) + '\nreturn engravingPalette;'
)();

async function render( material, effects, brightness = null, dithered = null ) {
	const imageData = {
		data: new Uint8ClampedArray( [
			80, 80, 80, 255, 160, 160, 160, 255, 220, 220, 220, 255, 255, 255,
			255, 255, 100, 100, 100, 0,
		] ),
	};
	const img = {
		width: 5,
		height: 1,
		getElement: () => ( {} ),
		setElement() {},
		set() {},
		applyFilters() {
			for ( const filter of this.filters ) {
				filter.applyTo2d( { imageData } );
			}
		},
	};
	let added = false;
	const rendered = await methods.renderFabricImg.call(
		{
			...methods,
			loadFabricImage: async () => img,
			applyContentClip() {},
			silverPlaquePhotoDither: () => dithered,
		},
		{
			add: () => {
				added = true;
			},
		},
		'photo.jpg',
		0,
		0,
		100,
		100,
		true,
		'anonymous',
		false,
		0,
		palette( material, brightness ),
		null,
		0,
		'',
		effects
	);
	assert.equal( rendered, true );
	assert.equal( added, true );
	return { pixels: imageData.data, filters: img.filters || [] };
}

test( 'silver photo engraving retains distinct tones on dark and pale mockups', async () => {
	for ( const brightness of [ null, 0.8 ] ) {
		const { pixels } = await render(
			'silver_metal',
			{ photoEngraving: true },
			brightness
		);
		assert.deepEqual(
			Array.from( pixels.slice( 0, 3 ) ),
			brightness === null ? [ 201, 201, 195 ] : [ 116, 120, 115 ],
			'dark artwork exposes silver instead of remaining black'
		);
		assert.ok( pixels[ 0 ] < pixels[ 4 ] );
		assert.ok( pixels[ 4 ] < pixels[ 8 ] );
		assert.equal( pixels[ 3 ], 255 );
		assert.equal( pixels[ 15 ], 0, 'white background remains transparent' );
		assert.equal(
			pixels[ 19 ],
			0,
			'transparent pixels remain transparent'
		);
	}
} );

test( 'solid engraving artwork still uses the exact palette tint', async () => {
	const { pixels } = await render( 'silver_metal', {} );
	assert.deepEqual( Array.from( pixels.slice( 0, 3 ) ), [ 201, 201, 195 ] );
	assert.deepEqual( Array.from( pixels.slice( 4, 7 ) ), [ 201, 201, 195 ] );
} );

test( 'glass uploads retain the pale frosted tint and transparent background', async () => {
	for ( const effects of [ {}, { photoEngraving: true } ] ) {
		const { pixels } = await render( 'glass', effects );
		for ( const offset of [ 0, 4, 8 ] ) {
			assert.deepEqual(
				Array.from( pixels.slice( offset, offset + 3 ) ),
				[ 238, 244, 244 ]
			);
			assert.equal( pixels[ offset + 3 ], offset === 8 ? 0 : 255 );
		}
		assert.equal( pixels[ 15 ], 0, 'white background remains transparent' );
		assert.equal(
			pixels[ 19 ],
			0,
			'transparent pixels remain transparent'
		);
	}
} );

test( 'black metal uploads use silver engraving, including black line artwork', async () => {
	for ( const effects of [ {}, { photoEngraving: true } ] ) {
		const { pixels } = await render( 'black_metal', effects );
		for ( const offset of [ 0, 4, 8 ] ) {
			assert.deepEqual(
				Array.from( pixels.slice( offset, offset + 3 ) ),
				[ 216, 216, 216 ]
			);
			assert.equal( pixels[ offset + 3 ], offset === 8 ? 0 : 255 );
		}
		assert.equal( pixels[ 15 ], 0, 'white background remains transparent' );
		assert.equal(
			pixels[ 19 ],
			0,
			'transparent pixels remain transparent'
		);
	}
} );

test( 'silver plaque dithering bypasses tint; fallback preserves photo tones', async () => {
	const dithered = await render(
		'silver_plaque',
		{ photoEngraving: true },
		null,
		{}
	);
	assert.equal( dithered.filters.length, 0 );
	const { pixels } = await render( 'silver_plaque', {
		photoEngraving: true,
	} );
	assert.ok( pixels[ 0 ] < pixels[ 8 ] );
} );
