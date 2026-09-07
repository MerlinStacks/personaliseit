import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const mathSource = await readFile(
	new URL( '../../src/shared/render-math.js', import.meta.url ),
	'utf8'
);
// Evaluate the real dependency without data URLs, which Bun resolves as paths.
const rasterDimensionsForLayer = new Function(
	'"use strict";\n' +
		mathSource.replace( /^export /gm, '' ) +
		'\nreturn rasterDimensionsForLayer;'
)();
const source = await readFile(
	new URL( '../../src/frontend/customiser/uploads.js', import.meta.url ),
	'utf8'
);
const methods = new Function(
	'rasterDimensionsForLayer',
	'"use strict";\n' +
		source
			.replace(
				"import { rasterDimensionsForLayer } from '../../shared/render-math';",
				''
			)
			.replace( 'export default uploadMethods;', 'return uploadMethods;' )
)( rasterDimensionsForLayer );

function context() {
	const input = {
		attachmentId: 20,
		attachmentUrl: 'https://example.com/effect.png',
		sourceAttachmentId: 10,
		sourceAttachmentUrl: 'https://example.com/original.png',
		baseAttachmentId: 10,
		baseAttachmentUrl: 'https://example.com/original.png',
		imageFilterId: 7,
	};
	return {
		...methods,
		inputs: { 1: input },
		data: { imageFilters: [ { id: 8, isAi: false, key: 'grayscale' } ] },
		aiFilterErrors: { 1: 'Previous error' },
		aiFilterGenerations: {},
		aiFilterAbortControllers: {},
		_controlLocks: new Set(),
		updateLinkedLayerControls( id, keys ) {
			if ( keys.includes( 'imageFilterId' ) ) {
				this.dropdown = this.inputs[ id ].imageFilterId;
			}
		},
		syncLinkedImageInput( id ) {
			this.linkedInput = { ...this.inputs[ id ] };
		},
		recordImageLinkGroupCarry() {},
		renderAiFilterResults() {},
		requestPreviewFocus() {},
		areaIndexForLayer() {
			return 0;
		},
		scheduleRedraw() {},
		updateHiddenField() {
			this.payload = { ...this.inputs[ 1 ] };
		},
	};
}

test( 'Original result restores source, clears filter and synchronizes controls and payload', async () => {
	const app = context();
	let aborted = false;
	app.aiFilterAbortControllers[ 1 ] = {
		abort() {
			aborted = true;
		},
	};
	assert.equal( await app.selectAiFilterResult( 1, 0 ), true );
	assert.equal( aborted, true );
	assert.equal( app.inputs[ 1 ].attachmentId, 10 );
	assert.equal(
		app.inputs[ 1 ].attachmentUrl,
		'https://example.com/original.png'
	);
	assert.equal( app.dropdown, 0 );
	assert.equal( app.linkedInput.imageFilterId, 0 );
	assert.equal( app.payload.imageFilterId, 0 );
	assert.equal( app.aiFilterErrors[ 1 ], undefined );
} );

test( 'Original dropdown selection restores the source and serializes zero', async () => {
	const app = context();
	assert.equal( await app.applyAiImageFilter( 1, 0 ), true );
	assert.equal( app.payload.attachmentId, 10 );
	assert.equal( app.payload.imageFilterId, 0 );
} );

test( 'non-AI filters restore the source without clearing the selected effect', async () => {
	const app = context();
	assert.equal( await app.applyAiImageFilter( 1, 8 ), true );
	assert.equal( app.inputs[ 1 ].attachmentId, 10 );
	assert.equal( app.linkedInput.imageFilterId, 8 );
	assert.equal( app.payload.imageFilterId, 8 );
} );
