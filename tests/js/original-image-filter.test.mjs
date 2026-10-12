import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import { JSDOM } from 'jsdom';

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

for ( const key of [
	'grayscale',
	'negative',
	'sepia',
	'brightness',
	'contrast',
	'saturation',
	'hue',
] ) {
	test( `${ key } restores source and preserves selection without an AI request`, async () => {
		const app = context();
		app.data.imageFilters[ 0 ].key = key;
		let aborted = false;
		app.aiFilterAbortControllers[ 1 ] = {
			abort() {
				aborted = true;
			},
		};
		const originalFetch = globalThis.fetch;
		let requests = 0;
		globalThis.fetch = () => {
			requests++;
			throw new Error(
				'Standard filters must not make network requests'
			);
		};
		try {
			assert.equal( await app.applyAiImageFilter( 1, 8 ), true );
			assert.equal( requests, 0 );
			assert.equal( aborted, true );
			assert.equal( app.inputs[ 1 ].attachmentId, 10 );
			assert.equal( app.linkedInput.imageFilterId, 8 );
			assert.equal( app.payload.imageFilterId, 8 );
		} finally {
			globalThis.fetch = originalFetch;
		}
	} );
}

function retryContext() {
	const app = context();
	app.data.imageFilters.push( { id: 7, isAi: true } );
	app.data.applyImageFilterUrl = '/apply-image-filter';
	app._customisationActive = true;
	app.inputs[ 1 ].imageFilterResults = {
		7: [
			{
				attachment_id: 20,
				preview_url: 'https://example.com/effect.png',
			},
		],
	};
	app.ensureRequestToken = async () => {};
	app.createStateAbortController = () => ( {
		controller: new AbortController(),
		timedOut: () => false,
		release() {},
	} );
	app.beginArtworkOperation = () => {
		app.pending = true;
		return {};
	};
	app.finishArtworkOperation = () => {
		app.pending = false;
	};
	app.setUploadProgress = () => {};
	app.showUploadError = ( zone, message ) => {
		app.message = message;
	};
	app.restHeaders = ( headers ) => headers;
	app.imageFilterRequestBody = () => ( {} );
	app.authenticatedFetch = async () => ( {
		ok: false,
		json: async () => ( { message: 'Provider unavailable' } ),
	} );
	app.loadAiFilterResults = async () => {
		throw new Error( 'Unexpected listing request' );
	};
	return app;
}

test( 'fixed AI effects show selectable generated results and retries after replacing an upload', () => {
	const dom = new JSDOM(
		'<div data-oc-image-filter-results="1" data-oc-filter-allow-original="false" hidden><span data-oc-image-filter-remaining></span><div data-oc-image-filter-result-grid></div><button data-oc-image-filter-retry></button></div>'
	);
	const previousDocument = globalThis.document;
	globalThis.document = dom.window.document;
	try {
		const app = retryContext();
		app.inputs[ 1 ].imageFilterResults[ 7 ][ 0 ].attempt = 1;
		methods.renderAiFilterResults.call( app, 1 );
		const panel = document.querySelector(
			'[data-oc-image-filter-results]'
		);
		assert.equal( panel.hidden, false );
		assert.equal(
			panel.querySelector( '[data-oc-filter-result-choice="0"]' ),
			null
		);
		assert.equal(
			panel
				.querySelector( '[data-oc-filter-result-choice="20"]' )
				.getAttribute( 'aria-pressed' ),
			'true'
		);
		assert.equal(
			panel.querySelector( '[data-oc-image-filter-retry]' ).disabled,
			false
		);
		// Replacement uploads reset the per-photo results, then populate this same panel.
		app.inputs[ 1 ].baseAttachmentId = 30;
		app.inputs[ 1 ].baseAttachmentUrl =
			'https://example.com/replacement.png';
		app.inputs[ 1 ].attachmentId = 40;
		app.inputs[ 1 ].imageFilterResults = {
			7: [
				{
					attachment_id: 40,
					preview_url: 'https://example.com/new-result.png',
					attempt: 1,
				},
			],
		};
		methods.renderAiFilterResults.call( app, 1 );
		assert.equal(
			panel.querySelector( '[data-oc-filter-result-choice="20"]' ),
			null
		);
		assert.equal(
			panel
				.querySelector( '[data-oc-filter-result-choice="40"]' )
				.getAttribute( 'aria-pressed' ),
			'true'
		);
		assert.equal( panel.hidden, false );
		panel.dataset.ocFilterAllowOriginal = 'true';
		methods.renderAiFilterResults.call( app, 1 );
		assert.ok(
			panel.querySelector( '[data-oc-filter-result-choice="0"]' )
		);
	} finally {
		globalThis.document = previousDocument;
		dom.window.close();
	}
} );

test( 'failed retry retains selected AI artwork and clears the cart blocker without another listing request', async () => {
	const app = retryContext();
	assert.equal( await app.applyAiImageFilter( 1, 7, {}, true ), false );
	assert.equal( app.inputs[ 1 ].attachmentId, 20 );
	assert.equal( app.payload.imageFilterId, 7 );
	assert.equal( app.aiFilterErrors[ 1 ], undefined );
	assert.equal( app.pending, false );
	assert.match( app.message, /previous image is still selected/ );
} );

test( 'authentication failure during a retry also preserves usable artwork', async () => {
	const app = retryContext();
	app.ensureRequestToken = async () => {
		throw new Error( 'Security verification failed' );
	};
	await app.applyAiImageFilter( 1, 7, {}, true );
	assert.equal( app.aiFilterErrors[ 1 ], undefined );
	assert.equal( app.payload.attachmentId, 20 );
	assert.equal( app.pending, false );
} );

test( 'failed change to another AI effect restores the previous effect selection as well as its image', async () => {
	const app = retryContext();
	app.data.imageFilters.push( { id: 9, isAi: true } );
	app.inputs[ 1 ].imageFilterResults[ 9 ] = [];
	await app.applyAiImageFilter( 1, 9, {}, true );
	assert.equal( app.aiFilterErrors[ 1 ], undefined );
	assert.equal( app.payload.attachmentId, 20 );
	assert.equal( app.payload.imageFilterId, 7 );
	assert.equal( app.dropdown, 7 );
} );

test( 'failed first generation remains blocked rather than silently submitting unfiltered artwork', async () => {
	const app = retryContext();
	app.inputs[ 1 ].attachmentId = 10;
	app.inputs[ 1 ].imageFilterResults[ 7 ] = [];
	await app.applyAiImageFilter( 1, 7, {}, true );
	assert.equal( app.aiFilterErrors[ 1 ], 'Provider unavailable' );
	assert.equal( app.pending, false );
} );

test( 'quota rejection updates the displayed allowance without blocking the previous successful image', async () => {
	const app = retryContext();
	app.authenticatedFetch = async () => ( {
		ok: false,
		json: async () => ( {
			message: 'Generation limit reached',
			data: { quota: { remaining: 0, limit: 5 } },
		} ),
	} );
	await app.applyAiImageFilter( 1, 7, {}, true );
	assert.equal( app.aiFilterErrors[ 1 ], undefined );
	assert.equal( app.aiFilterQuota.remaining, 0 );
} );

test( 'generation allowance displays the shared customer limit separately from per-image results', () => {
	const app = context();
	const elements = [ {}, {} ];
	const previousDocument = globalThis.document;
	globalThis.document = { querySelectorAll: () => elements };
	try {
		app.aiFilterQuota = { remaining: 2, limit: 5, next_reset_in: 89 };
		app.renderAiFilterQuota();
		for ( const element of elements ) {
			assert.match( element.textContent, /2 \/ 5 generations left/ );
			assert.match( element.textContent, /More available in 2 min/ );
		}
	} finally {
		globalThis.document = previousDocument;
	}
} );

function historyContext() {
	const app = retryContext();
	app.artworkPendingCount = 0;
	app.aiFilterQuota = { remaining: 2, limit: 5 };
	app.getTrackedImageMeta = async () => ( { width: 1024, height: 1024 } );
	app.setControlLock = ( key, locked ) => {
		app.locked = locked;
	};
	app.clearFailedArtworkReplacements = () => {};
	app.updateImageCropControl = () => {};
	app.authenticatedFetch = async () => {
		throw new Error( 'Reusing artwork must not generate another image' );
	};
	return app;
}

const savedResult = {
	attachment_id: 40,
	preview_url: 'https://example.com/saved.png',
	source_attachment_id: 30,
	source_preview_url: 'https://example.com/source.png',
	filter_id: 7,
	file_type: 'png',
	source_file_type: 'jpg',
	attempt: 1,
	related_results: [
		{
			attachment_id: 40,
			preview_url: 'https://example.com/saved.png',
			attempt: 1,
		},
	],
};

test( 'opening previous results lists retained images without changing the current design', async () => {
	const dom = new JSDOM(
		'<details data-oc-filter-history="1"><p data-oc-filter-history-status></p><div data-oc-filter-history-grid></div></details>'
	);
	const previousDocument = globalThis.document;
	globalThis.document = dom.window.document;
	try {
		const app = historyContext();
		const original = app.inputs[ 1 ];
		app.data.imageFilterHistoryUrl = '/image-filter-history';
		app.imageFilterRequestBody = () => ( {
			product_id: 50,
			variation_id: 51,
			design_id: 7,
			layer_id: 1,
		} );
		app.authenticatedFetch = async ( url, options ) => {
			assert.equal( url, '/image-filter-history' );
			assert.equal( JSON.parse( options.body ).product_id, 50 );
			return {
				ok: true,
				json: async () => ( {
					results: [ { ...savedResult, filter_name: 'Portrait' } ],
				} ),
			};
		};
		const details = document.querySelector( 'details' );
		await app.loadAiFilterHistory( 1, details );
		assert.match( details.textContent, /Choose a saved result/ );
		assert.equal(
			details.querySelector( '[data-oc-history-result="40"]' )
				.textContent,
			'Portrait · Result 1'
		);
		assert.equal( app.inputs[ 1 ], original );
		assert.equal( app.aiFilterQuota.remaining, 2 );
		assert.equal( details._ocHistoryLoading, false );
	} finally {
		globalThis.document = previousDocument;
		dom.window.close();
	}
} );

test( 'previous results restore both the original photo and generated artwork without consuming quota', async () => {
	const app = historyContext();
	assert.equal( await app.usePreviousAiFilterResult( 1, savedResult ), true );
	assert.equal( app.payload.attachmentId, 40 );
	assert.equal( app.payload.sourceAttachmentId, 30 );
	assert.equal( app.payload.baseAttachmentId, 30 );
	assert.equal( app.payload.imageFilterId, 7 );
	assert.equal( app.payload.imageFilterAttemptCount[ 7 ], 1 );
	assert.equal( app.aiFilterQuota.remaining, 2 );
	assert.equal( app.aiFilterErrors[ 1 ], undefined );
	assert.equal( app.locked, false );
	assert.equal( app.pending, false );
} );

test( 'missing saved images preserve the current artwork and unlock controls', async () => {
	const app = historyContext();
	const original = app.inputs[ 1 ];
	app.getTrackedImageMeta = async () => null;
	await assert.rejects(
		app.usePreviousAiFilterResult( 1, savedResult ),
		/unavailable/
	);
	assert.equal( app.inputs[ 1 ], original );
	assert.equal( app.locked, false );
	assert.equal( app.pending, false );
} );

test( 'switching designs while loading a saved result never replaces the new design input', async () => {
	const app = historyContext();
	app._designGeneration = 1;
	const original = app.inputs[ 1 ];
	app.getTrackedImageMeta = async () => {
		app._designGeneration = 2;
		return { width: 1024, height: 1024 };
	};
	assert.equal(
		await app.usePreviousAiFilterResult( 1, savedResult ),
		false
	);
	assert.equal( app.inputs[ 1 ], original );
	assert.equal( app.pending, false );
} );
