import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const samplerSource = await readFile(
	'src/frontend/customiser/engraving-background.js',
	'utf8'
);
const previewSource = await readFile(
	'src/frontend/customiser/engraving-preview.js',
	'utf8'
);
function preview( createSampler ) {
	return new Function(
		'createEngravingBackgroundSampler',
		previewSource
			.replace( /import\s*\{[\s\S]*?\}\s*from\s*'[^']+';/g, '' )
			.replace( /^export /gm, '' ) +
			'\nreturn { engravingPalette, engravingPreviewPalette };'
	)( createSampler );
}
const methods = preview();

function fixture( colourAt, { denied = false, unavailable = false } = {} ) {
	let reads = 0;
	let draws = 0;
	const image = {};
	const document = {
		createElement() {
			return {
				getContext() {
					if ( unavailable ) {
						return null;
					}
					return {
						drawImage( source ) {
							assert.equal(
								source,
								image,
								'reads only the loaded mockup'
							);
							draws++;
						},
						getImageData( x, y, w, h ) {
							reads++;
							if ( denied ) {
								throw new Error( 'SecurityError' );
							}
							const data = new Uint8ClampedArray( w * h * 4 );
							for ( let row = 0; row < h; row++ ) {
								for ( let col = 0; col < w; col++ ) {
									data.set(
										colourAt( col / w, row / h ),
										( row * w + col ) * 4
									);
								}
							}
							return { data };
						},
					};
				},
			};
		},
	};
	const create = new Function(
		'document',
		samplerSource.replace( /^export /gm, '' ) +
			'\nreturn createEngravingBackgroundSampler;'
	)( document );
	return {
		sample: create( image, 1000, 500 ),
		palette: preview( create ).engravingPreviewPalette,
		canvas: { _ocEngravingMockup: [ image, 1000, 500 ] },
		counts: () => ( { reads, draws } ),
	};
}

const center = { x: 500, y: 250 };
const box = { w: 200, h: 100 };
const original = {
	text: '#c9c9c3',
	imageTint: '#c9c9c3',
	bg: 'ECEFF1',
	highlight: 'rgba(255,255,255,0.42)',
	brightness: -0.28,
	contrast: 0.18,
	opacity: 0.9,
};

test( 'pastel pink gets deeper silver for both text and images; dark colours stay exact', () => {
	for ( const colour of [
		[ 245, 201, 215, 255 ],
		[ 255, 255, 255, 255 ],
	] ) {
		const { sample } = fixture( () => colour );
		const palette = methods.engravingPalette(
			'silver_metal',
			sample( center, box )
		);
		assert.equal( palette.text, '#747873' );
		assert.equal( palette.imageTint, palette.text );
		assert.equal( palette.highlight, original.highlight );
	}
	for ( const colour of [
		[ 10, 10, 10, 255 ],
		[ 65, 75, 45, 255 ],
	] ) {
		const { sample } = fixture( () => colour );
		assert.deepEqual(
			methods.engravingPalette( 'silver_metal', sample( center, box ) ),
			original
		);
	}
	for ( const material of [
		'gold_metal',
		'silver_plaque',
		'black_metal',
		'wood',
		'glass',
		'leather',
	] ) {
		assert.deepEqual(
			methods.engravingPalette( material, 1 ),
			methods.engravingPalette( material )
		);
	}
} );

test( 'local footprint excludes the white surround and supports rotation and grouped areas', () => {
	const { sample, counts } = fixture( ( x, y ) =>
		x > 0.45 && x < 0.55 && y > 0.2 && y < 0.8
			? [ 30, 40, 20, 255 ]
			: [ 255, 255, 255, 255 ]
	);
	assert.ok( sample( center, { w: 280, h: 50 }, 90 ) < 0.6 );
	assert.ok( sample( { x: 100, y: 100 }, box ) >= 0.6 );
	for ( let i = 0; i < 20; i++ ) {
		assert.ok( sample( center, { w: 50, h: 200 } ) < 0.6 );
	}
	assert.deepEqual( counts(), { reads: 1, draws: 1 } );
} );

test( 'missing, transparent, out-of-image and cross-origin samples use the exact fallback', () => {
	for ( const options of [ { denied: true }, { unavailable: true }, {} ] ) {
		const { sample, counts } = fixture(
			() => [ 255, 255, 255, 0 ],
			options
		);
		for ( let i = 0; i < 5; i++ ) {
			assert.equal( sample( center, box ), null );
			assert.deepEqual(
				methods.engravingPalette(
					'silver_metal',
					sample( center, box )
				),
				original
			);
		}
		assert.ok( counts().reads <= 1 );
	}
	const { sample } = fixture( () => [ 255, 255, 255, 255 ] );
	assert.equal( sample( { x: -500, y: -500 }, box ), null );
	assert.deepEqual( methods.engravingPalette(), original );
} );

test( 'new mockup canvas gets fresh pixels after a variation switch, including after failure', () => {
	for ( const options of [ {}, { denied: true } ] ) {
		const old = fixture( () => [ 245, 201, 215, 255 ], options );
		old.sample( center, box );
		const next = fixture( () => [ 30, 40, 20, 255 ] );
		assert.deepEqual(
			methods.engravingPalette(
				'silver_metal',
				next.sample( center, box )
			),
			original
		);
		assert.deepEqual( next.counts(), { reads: 1, draws: 1 } );
	}
} );

test( 'lazy preview reuses one sampler per canvas and samples silver only', () => {
	const f = fixture( () => [ 245, 201, 215, 255 ] );
	assert.deepEqual( f.counts(), { reads: 0, draws: 0 } );
	f.palette( 'gold_metal', f.canvas, center, box );
	assert.deepEqual( f.counts(), { reads: 0, draws: 0 } );
	for ( let i = 0; i < 10; i++ ) {
		assert.equal(
			f.palette( 'silver_metal', f.canvas, center, box ).text,
			'#747873'
		);
	}
	assert.deepEqual( f.counts(), { reads: 1, draws: 1 } );
	const next = fixture( () => [ 30, 40, 20, 255 ] );
	assert.deepEqual(
		next.palette( 'silver_metal', next.canvas, center, box ),
		original
	);
	assert.deepEqual( next.counts(), { reads: 1, draws: 1 } );
} );
