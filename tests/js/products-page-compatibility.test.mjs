import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const source = await readFile(
	'src/admin/products-page-compatibility.js',
	'utf8'
);
const moduleUrl = `data:text/javascript;base64,${ Buffer.from(
	source
).toString( 'base64' ) }`;
const { aiImageInstructionIsValid, layerTypeSupportsPrintMethod } =
	await import( moduleUrl );

test( 'AI image instructions must contain text', () => {
	assert.equal( aiImageInstructionIsValid( '' ), false );
	assert.equal( aiImageInstructionIsValid( '   ' ), false );
	assert.equal( aiImageInstructionIsValid( 'Create a pet portrait' ), true );
} );

test( 'AI image instructions enforce the UTF-8 byte limit', () => {
	assert.equal( aiImageInstructionIsValid( 'a'.repeat( 16384 ) ), true );
	assert.equal( aiImageInstructionIsValid( 'a'.repeat( 16385 ) ), false );
	assert.equal( aiImageInstructionIsValid( '😀'.repeat( 4097 ) ), false );
} );

test( 'night sky layers are unavailable for embroidery', () => {
	assert.equal(
		layerTypeSupportsPrintMethod( 'night_sky', 'embroidery' ),
		false
	);
} );

test( 'night sky layers remain available for supported print methods', () => {
	[ 'uv', 'sublimation', 'engraving' ].forEach( ( method ) => {
		assert.equal(
			layerTypeSupportsPrintMethod( 'night_sky', method ),
			true
		);
	} );
} );

test( 'other layers remain available for embroidery', () => {
	[ 'text', 'image', 'ai_image', 'clipart' ].forEach( ( type ) => {
		assert.equal(
			layerTypeSupportsPrintMethod( type, 'embroidery' ),
			true
		);
	} );
} );
