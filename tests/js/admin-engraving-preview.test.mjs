import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import vm from 'node:vm';
import { filters as FabricFilters } from 'fabric';
import { JSDOM } from 'jsdom';

const paletteSource = await readFile(
	'src/frontend/customiser/engraving-preview.js',
	'utf8'
);
const engravingPalette = new Function(
	paletteSource
		.replace( /import\s*\{[\s\S]*?\}\s*from\s*'[^']+';/g, '' )
		.replace( /^export /gm, '' ) + '\nreturn engravingPalette;'
)();
const materialPalette = new Function(
	( await readFile( 'src/frontend/customiser/materials.js', 'utf8' ) )
		.replace( /^export /gm, '' ) + '\nreturn materialPalette;'
)();
const previewSource = await readFile(
	'src/admin/products-page-preview.js',
	'utf8'
);
const previewCode = previewSource
	.replace( /import\s*\{[\s\S]*?\}\s*from\s*'[^']+';/g, '' )
	.replace( /^export /gm, '' );

function fixture() {
	const { window } = new JSDOM(
		'<div id="preview"></div><div id="other"></div>'
	);
	const create = vm.runInNewContext(
		previewCode + '; createLayerPreviewRenderer',
		{ window, document: window.document, engravingPalette, materialPalette }
	);
	return {
		window,
		render: create( {} ),
		el: window.document.getElementById( 'preview' ),
		other: window.document.getElementById( 'other' ),
	};
}

test( 'material-driven previews recolour images and non-recolourable clipart without etched effects', () => {
	const { window, render, el } = fixture();
	try {
		for ( const material of [ 'engraving_material', { surface: '#171717', engraving: '#abcdef' } ] ) {
			for ( const type of [ 'image', 'clipart' ] ) {
				render( { type, settings: {
					default_attachment_url: 'https://example.com/photo.png',
					default_clipart_url: 'https://example.com/coloured.png',
					default_clipart_recolourable: false,
				} }, el, 100, 100, false, true, material );
				const image = el.querySelector( 'img' );
				assert.match( image.style.filter, /^url\(#/ );
				assert.ok( ! image.style.filter.includes( 'drop-shadow' ) );
				assert.equal( image.style.mixBlendMode, '' );
				const stages = el.querySelectorAll( 'feColorMatrix' );
				const values = stages[stages.length - 1].getAttribute( 'values' ).split( ' ' ).map( Number );
				assert.equal( values[18], 1, 'full opacity' );
				const expected = typeof material === 'object' ? [171, 205, 239] : [23, 23, 23];
				assert.deepEqual( [values[4], values[9], values[14]].map( ( v ) => Math.round( v * 255 ) ), expected );
			}
		}
	} finally {
		window.close();
	}
} );

for ( const material of [ 'gold_metal', 'wood', 'glass', 'engraving_material' ] ) {
	test( `${ material } admin colours and photo tint match the storefront palette`, () => {
		const { window, render, el, other } = fixture();
		try {
			const palette = engravingPalette( material );
			render( { type: 'lineart' }, el, 100, 100, false, true, material );
			const colour = window.document.createElement( 'span' );
			colour.style.color = palette.text;
			assert.equal(
				el.querySelector( '.oc-lp-icon' ).style.color,
				colour.style.color
			);

			for ( const type of [ 'image', 'clipart' ] ) {
				const layer = {
					type,
					settings: {
						default_attachment_url: 'https://example.com/image.png',
						default_clipart_url: 'https://example.com/artwork.svg',
						default_clipart_recolourable: true,
					},
				};
				render( layer, el, 100, 100, false, true, material );
				assert.equal( el.querySelectorAll( 'filter' ).length, 1 );
				const definition = el.querySelector( 'filter' );
				assert.equal(
					definition.getAttribute( 'color-interpolation-filters' ),
					'sRGB'
				);
				assert.ok(
					el
						.querySelector( 'img' )
						.style.filter.startsWith( `url(#${ definition.id })` )
				);
				assert.equal(
					el.querySelector( 'img' ).style.mixBlendMode,
					palette.composite || ''
				);

				const data = new Uint8ClampedArray( [
					0, 0, 0, 255, 80, 80, 80, 255, 160, 160, 160, 128, 120, 120,
					120, 0,
				] );
				const actual = { data: data.slice() };
				for ( const stage of definition.querySelectorAll(
					'feColorMatrix'
				) ) {
					new FabricFilters.ColorMatrix( {
						matrix: stage
							.getAttribute( 'values' )
							.split( ' ' )
							.map( Number ),
						colorsOnly: false,
					} ).applyTo2d( { imageData: actual } );
				}
				const expected = { data: data.slice() };
				for ( const filter of [
					new FabricFilters.Grayscale(),
					new FabricFilters.Brightness( {
						brightness: palette.brightness,
					} ),
					new FabricFilters.Contrast( {
						contrast: palette.contrast,
					} ),
					new FabricFilters.BlendColor( {
						color: palette.imageTint,
						alpha: palette.tintAlpha ?? 1,
						mode:
							type === 'image'
								? palette.photoTintMode || 'multiply'
								: 'tint',
					} ),
				] ) {
					filter.applyTo2d( { imageData: expected } );
				}
				for ( let i = 0; i < data.length; i++ ) {
					const value =
						i % 4 === 3
							? Math.round( expected.data[ i ] * palette.opacity )
							: expected.data[ i ];
					assert.ok(
						Math.abs( actual.data[ i ] - value ) <= 1,
						`channel ${ i } matches storefront`
					);
				}
				render( layer, other, 100, 100, false, true, material );
				assert.notEqual(
					other.querySelector( 'filter' ).id,
					definition.id
				);
				render( layer, el, 100, 100, false, false, material );
				assert.equal(
					el.querySelector( 'filter' ),
					null,
					'non-engraving removes stale material definitions'
				);
				assert.equal( el.querySelector( 'img' ).style.filter, '' );
			}
		} finally {
			window.close();
		}
	} );
}
