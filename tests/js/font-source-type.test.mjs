import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = readFileSync(
	new URL( '../../src/admin/font-manager.js', import.meta.url ),
	'utf8'
);
const match = source.match(
	/function fontSourceType\( font, buffer \) \{[\s\S]*?\n\t\}/
);
assert.ok( match, 'Font source detection must exist' );
const fontSourceType = vm.runInNewContext( `(${ match[ 0 ] })` );

test( 'font conversion reads sfnt signatures rather than trusting TTF/OTF filenames', () => {
	for ( const ext of [ 'ttf', 'otf', 'TTF' ] ) {
		assert.equal(
			fontSourceType(
				{ ext },
				Uint8Array.from( [ 0x4f, 0x54, 0x54, 0x4f ] ).buffer
			),
			'otf'
		);
		assert.equal(
			fontSourceType( { ext }, Uint8Array.from( [ 0, 1, 0, 0 ] ).buffer ),
			'ttf'
		);
	}
	for ( const ext of [ 'woff', 'woff2' ] ) {
		assert.equal( fontSourceType( { ext }, new ArrayBuffer( 4 ) ), ext );
	}
	assert.equal(
		fontSourceType( { ext: 'ttf' }, new ArrayBuffer( 2 ) ),
		'ttf'
	);
} );
