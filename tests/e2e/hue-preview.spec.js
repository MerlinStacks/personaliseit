const { test, expect } = require( '@playwright/test' );
const { readFileSync } = require( 'node:fs' );
const path = require( 'node:path' );
// Use the existing wp-scripts parser, as in the Node renderer parity tests.
// eslint-disable-next-line import/no-extraneous-dependencies
const { parse } = require( 'acorn' );
const { createCanvas, loadImage } = require( 'canvas' );
const { filters: FabricFilters } = require( 'fabric' );

// Strip only module declarations, keeping the actual admin renderer intact.
// Its text/cut-line dependencies are not used by these image-layer tests.
const source = readFileSync(
	path.resolve( __dirname, '../../src/admin/products-page-preview.js' ),
	'utf8'
);
const rendererCode = parse( source, {
	ecmaVersion: 'latest',
	sourceType: 'module',
} )
	.body.filter( ( node ) => node.type !== 'ImportDeclaration' )
	.map( ( node ) =>
		source.slice( node.declaration?.start ?? node.start, node.end )
	)
	.join( '\n' );

const tile = 24;
const pixels = [
	[ 255, 0, 0, 255 ],
	[ 0, 255, 0, 255 ],
	[ 0, 0, 255, 255 ],
	[ 12, 100, 240, 255 ],
	[ 231, 97, 18, 255 ],
	[ 128, 128, 128, 255 ],
	[ 0, 0, 0, 255 ],
	[ 255, 255, 255, 255 ],
	[ 255, 0, 0, 128 ],
	[ 0, 255, 0, 64 ],
	[ 0, 0, 255, 192 ],
	[ 255, 0, 0, 0 ],
];
const width = pixels.length * tile;
const fixture = createCanvas( width, tile );
const fixtureContext = fixture.getContext( '2d' );
pixels.forEach( ( [ r, g, b, a ], index ) => {
	fixtureContext.fillStyle = `rgba(${ r },${ g },${ b },${ a / 255 })`;
	fixtureContext.fillRect( index * tile, 0, tile, tile );
} );
const imageUrl = fixture.toDataURL();
// Read back the encoded fixture's source pixels, including alpha quantization.
const sourcePixels = pixels.flatMap( ( _, index ) =>
	Array.from(
		fixtureContext.getImageData( index * tile + tile / 2, tile / 2, 1, 1 )
			.data
	)
);

function expectedPixels( amount, background ) {
	const data = new Uint8ClampedArray( sourcePixels );
	if ( amount !== null ) {
		const hue = new FabricFilters.HueRotation( { rotation: amount } );
		hue.calculateMatrix();
		hue.applyTo2d( { imageData: { data } } );
	}
	return pixels.map( ( _, index ) => {
		const offset = index * 4;
		const alpha = data[ offset + 3 ] / 255;
		// Only source-over compositing is calculated here; Fabric supplies hue.
		return [ 0, 1, 2 ].map( ( channel ) =>
			Math.round(
				data[ offset + channel ] * alpha + background * ( 1 - alpha )
			)
		);
	} );
}

async function render(
	page,
	{ amount = 0, filterId = 7, empty = false } = {}
) {
	await page.evaluate(
		async ( options ) => {
			window.ocProductsData = {
				imageFilters: [ { id: 7, key: 'hue', value: options.amount } ],
			};
			window.renderHuePreview(
				options.empty
					? null
					: {
							type: 'image',
							settings: {
								default_attachment_url: options.imageUrl,
								default_image_filter_id: options.filterId,
							},
					  },
				document.getElementById( 'preview' ),
				options.width,
				options.tile,
				false,
				false
			);
			const image = document.querySelector( '#preview img' );
			if ( image ) {
				await image.decode();
			}
		},
		{ amount, filterId, empty, imageUrl, width, tile }
	);
}

async function expectPaint( page, expected, label ) {
	// Screenshot the DOM image: drawImage(img) would bypass its CSS/SVG filter.
	// CSS scale normalizes the Pixel 7 project's deviceScaleFactor as well.
	const screenshot = await page.locator( '#preview' ).screenshot( {
		scale: 'css',
	} );
	const image = await loadImage( screenshot );
	expect( image.width ).toBe( width );
	expect( image.height ).toBe( tile );
	const canvas = createCanvas( width, tile );
	const context = canvas.getContext( '2d' );
	context.drawImage( image, 0, 0 );
	expected.forEach( ( rgb, index ) => {
		// Sample an interior patch, avoiding image edges and resampling seams.
		const actual = context.getImageData(
			index * tile + tile / 2 - 1,
			tile / 2 - 1,
			3,
			3
		).data;
		for ( let offset = 0; offset < actual.length; offset += 4 ) {
			rgb.forEach( ( value, channel ) => {
				expect(
					Math.abs( actual[ offset + channel ] - value ),
					`${ label }, tile ${ index }, channel ${ channel }: expected ${ value }, painted ${
						actual[ offset + channel ]
					}`
				).toBeLessThanOrEqual( 2 );
			} );
			expect( actual[ offset + 3 ] ).toBe( 255 );
		}
	} );
}

test.beforeEach( async ( { page } ) => {
	await page.setContent( `<!doctype html>
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<style>
			body { margin: 0; }
			#preview { position: relative; width: ${ width }px; height: ${ tile }px; background: white; }
			.oc-lp-media { display: block; width: 100%; height: 100%; object-fit: contain; }
		</style>
		<div id="preview"></div>` );
	await page.addScriptTag( { path: require.resolve( 'fabric' ) } );
	await page.addScriptTag( {
		content: `const FabricFilters = window.fabric.filters;
			${ rendererCode }
			window.renderHuePreview = createLayerPreviewRenderer({});`,
	} );
} );

for ( const amount of [ -1, -2 / 3, -0.25, 0, 0.25, 0.5, 2 / 3, 1 ] ) {
	test( `admin hue ${ amount } paints Fabric RGB and preserves source alpha`, async ( {
		page,
	} ) => {
		await render( page, { amount } );
		for ( const background of [ 255, 0 ] ) {
			await page.locator( '#preview' ).evaluate( ( element, value ) => {
				element.style.background = `rgb(${ value },${ value },${ value })`;
			}, background );
			await expectPaint(
				page,
				expectedPixels( amount, background ),
				`hue ${ amount }, background ${ background }`
			);
		}
	} );
}

test( 'admin hue rerenders replace painted hue and clear stale filters', async ( {
	page,
} ) => {
	for ( const reset of [
		{ amount: 0 },
		{ filterId: 0 },
		{ filterId: 999 },
	] ) {
		await render( page, { amount: 2 / 3 } );
		await expectPaint( page, expectedPixels( 2 / 3, 255 ), 'initial hue' );
		const previousId = await page
			.locator( '#preview filter' )
			.getAttribute( 'id' );
		await render( page, { amount: -2 / 3 } );
		await expectPaint(
			page,
			expectedPixels( -2 / 3, 255 ),
			'opposite hue'
		);
		await expect( page.locator( '#preview filter' ) ).toHaveCount( 1 );
		await expect( page.locator( `[id="${ previousId }"]` ) ).toHaveCount(
			0
		);
		await render( page, reset );
		await expectPaint(
			page,
			expectedPixels( null, 255 ),
			'reset to original'
		);
		await expect( page.locator( '#preview img' ) ).toHaveCount( 1 );
		await expect( page.locator( '#preview filter' ) ).toHaveCount(
			reset.filterId === undefined ? 1 : 0
		);
	}
	await render( page, { amount: 0.25 } );
	await expectPaint(
		page,
		expectedPixels( 0.25, 255 ),
		'hue before removal'
	);
	await render( page, { empty: true } );
	await expect( page.locator( '#preview .oc-lp' ) ).toHaveCount( 0 );
	await expectPaint(
		page,
		pixels.map( () => [ 255, 255, 255 ] ),
		'empty layer'
	);
} );
