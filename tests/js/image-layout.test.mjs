import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const source = await readFile( 'src/shared/image-layout.js', 'utf8' );
const moduleUrl = `data:text/javascript;base64,${ Buffer.from(
	source
).toString( 'base64' ) }`;
const {
	imageCropRatio,
	imagePlacementClipPath,
	imagePlacementNeedsClip,
	imagePlacementScale,
} = await import( moduleUrl );

test( 'fit placement uses contain scale without clipping', () => {
	assert.equal( imageCropRatio( 0 ), 0 );
	assert.equal( imagePlacementScale( 0.5, 1, 0 ), 0.5 );
	assert.equal( imagePlacementNeedsClip( 0 ), false );
} );

test( 'intermediate placement scales toward cover and enables clipping', () => {
	assert.equal( imageCropRatio( 50 ), 0.5 );
	assert.equal( imagePlacementScale( 0.5, 1, 50 ), 0.75 );
	assert.equal( imagePlacementNeedsClip( 50 ), true );
} );

test( 'cover placement uses cover scale and clipping', () => {
	assert.equal( imageCropRatio( 'cover' ), 1 );
	assert.equal( imagePlacementScale( 0.5, 1, 100 ), 1 );
	assert.equal( imagePlacementNeedsClip( 100 ), true );
} );

test( 'live placement transitions remove and restore the crop clip', () => {
	const clipPath = { type: 'rect' };

	assert.equal( imagePlacementClipPath( 0, clipPath ), null );
	assert.equal( imagePlacementClipPath( 1, clipPath ), clipPath );
	assert.equal( imagePlacementClipPath( 0, clipPath ), null );
} );
