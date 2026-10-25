import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { inflateSync } from 'node:zlib';
import test from 'node:test';
import { previewFonts } from './helpers/preview-font.mjs';

test( 'preview fallback is the exact production outline font', async () => {
	assert.deepEqual(
		await readFile( 'assets/fonts/oc-preview-fallback.ttf' ),
		inflateSync(
			await readFile(
				'vendor/tecnickcom/tc-lib-pdf-font/target/fonts/dejavu/dejavusans.z'
			)
		)
	);
	assert.equal(
		previewFonts.previewFontFamily( { name: 'DK Lemon Yellow Sun' } ),
		'"DK Lemon Yellow Sun", "OC Preview Fallback"'
	);
	assert.equal( previewFonts.previewFontFamily( null ), 'sans-serif' );
} );

test( 'fallback loads once before measurement and retries after failure', async ( t ) => {
	const previousFontFace = globalThis.FontFace;
	const previousDocument = globalThis.document;
	t.after( () => {
		globalThis.FontFace = previousFontFace;
		globalThis.document = previousDocument;
	} );
	let attempts = 0;
	const added = [];
	globalThis.FontFace = class {
		async load() {
			attempts++;
			if ( attempts === 1 ) {
				throw new Error( 'Network failure' );
			}
			return this;
		}
	};
	globalThis.document = { fonts: { add: ( face ) => added.push( face ) } };
	await assert.rejects(
		previewFonts.loadPreviewFallback(),
		/Network failure/
	);
	await Promise.all( [
		previewFonts.loadPreviewFallback(),
		previewFonts.loadPreviewFallback(),
	] );
	assert.equal( attempts, 2 );
	assert.equal( added.length, 1 );
} );
