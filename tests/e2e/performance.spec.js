const { test, expect } = require( '@playwright/test' );

const hasBaseUrl = Boolean( process.env.E2E_BASE_URL );
const productPath = process.env.E2E_PRODUCT_PATH || '/';
const maximumScriptBytes = Number(
	process.env.E2E_MAX_SCRIPT_BYTES || 3_000_000
);
const maximumStyleBytes = Number( process.env.E2E_MAX_STYLE_BYTES || 750_000 );
const maximumDomNodes = Number( process.env.E2E_MAX_DOM_NODES || 3_000 );
const maximumReadyMs = Number(
	process.env.E2E_MAX_CUSTOMISER_READY_MS || 5_000
);

test.describe( 'customiser performance budgets', () => {
	test.skip(
		! hasBaseUrl,
		'Set E2E_BASE_URL to run browser performance tests.'
	);

	test( 'stays within transfer, DOM, and readiness budgets', async ( {
		page,
	} ) => {
		const bytes = { script: 0, stylesheet: 0 };
		page.on( 'response', async ( response ) => {
			const type = response.request().resourceType();
			if ( type !== 'script' && type !== 'stylesheet' ) {
				return;
			}
			const length = Number(
				( await response.allHeaders() )[ 'content-length' ] || 0
			);
			bytes[ type ] += length;
		} );

		const startedAt = Date.now();
		await page.goto( productPath, { waitUntil: 'domcontentloaded' } );
		await expect( page.locator( '#oc-customiser-panel' ) ).toBeVisible();
		const readyMs = Date.now() - startedAt;
		await page.waitForLoadState( 'networkidle' );
		const domNodes = await page.locator( '*' ).count();

		expect( readyMs, 'customiser readiness time' ).toBeLessThanOrEqual(
			maximumReadyMs
		);
		expect( bytes.script, 'transferred script bytes' ).toBeLessThanOrEqual(
			maximumScriptBytes
		);
		expect(
			bytes.stylesheet,
			'transferred stylesheet bytes'
		).toBeLessThanOrEqual( maximumStyleBytes );
		expect( domNodes, 'DOM node count' ).toBeLessThanOrEqual(
			maximumDomNodes
		);
	} );
} );
