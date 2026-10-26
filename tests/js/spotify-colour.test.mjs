import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import vm from 'node:vm';

const source = await readFile( 'src/admin/products-page-settings.js', 'utf8' );
const createSettings = vm.runInNewContext(
	source.replace( /^import .*;$/gm, '' ).replace( /^export /gm, '' ) +
		'; createProductsPageSettings',
	{ window: {} }
);

test( 'Spotify appearance exposes the saved code colour, except for engraving', () => {
	let method = 'uv';
	const settings = createSettings( {
		esc: ( value ) => value,
		normaliseHex: ( value ) => value || '#000000',
		selectedArea: () => ( { method } ),
	} );
	const layer = { type: 'spotify', settings: { default_color: '#abcdef' } };
	const html = settings.buildTabContent( 'appearance', layer );
	assert.match( html, /Code colour/ );
	assert.match( html, /id="oc-set-default-color"/ );
	assert.match( html, /value="#abcdef"/ );
	method = 'engraving';
	assert.doesNotMatch(
		settings.buildTabContent( 'appearance', layer ),
		/type="color"/
	);
} );
