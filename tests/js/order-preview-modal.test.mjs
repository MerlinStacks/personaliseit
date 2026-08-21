import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const helperSource = await readFile( 'src/shared/modal-events.js', 'utf8' );
const helperUrl = `data:text/javascript;base64,${ Buffer.from(
	helperSource
).toString( 'base64' ) }`;
const { shouldOpenPreviewModal } = await import( helperUrl );

const click = ( overrides = {} ) => ( {
	defaultPrevented: false,
	button: 0,
	metaKey: false,
	ctrlKey: false,
	shiftKey: false,
	altKey: false,
	...overrides,
} );

test( 'opens previews only for unmodified primary clicks', () => {
	assert.equal( shouldOpenPreviewModal( click() ), true );
	assert.equal( shouldOpenPreviewModal( click( { ctrlKey: true } ) ), false );
	assert.equal( shouldOpenPreviewModal( click( { metaKey: true } ) ), false );
	assert.equal(
		shouldOpenPreviewModal( click( { shiftKey: true } ) ),
		false
	);
	assert.equal( shouldOpenPreviewModal( click( { button: 1 } ) ), false );
	assert.equal(
		shouldOpenPreviewModal( click( { defaultPrevented: true } ) ),
		false
	);
} );

test( 'preview modal handles failed images and uses localised strings', async () => {
	const source = await readFile(
		'src/frontend/order-preview-modal.js',
		'utf8'
	);

	assert.match( source, /window\.ocOrderPreviewModal/ );
	assert.match( source, /previewImage\.addEventListener\(\s*'error'/ );
	assert.match( source, /new AbortController\(\)/ );
	assert.match( source, /modal\.classList\.add\( 'is-error' \)/ );
	assert.match( source, /previewError\.hidden = true/ );
	assert.match( source, /directLink\.href = trigger\.href/ );
} );

test( 'reduced-motion styles include the animated controls themselves', async () => {
	const source = await readFile(
		'src/frontend/order-preview-modal.scss',
		'utf8'
	);

	assert.match( source, /&,[\s\S]*animation-duration: 0\.01ms/ );
	assert.match( source, /\.oc-order-preview-trigger:hover/ );
} );
