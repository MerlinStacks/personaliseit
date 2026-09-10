const { test, expect } = require( '@playwright/test' );
const path = require( 'node:path' );
const sass = require( 'sass' );

const { css } = sass.compile(
	path.resolve( __dirname, '../../src/frontend/customiser-app.scss' )
);

// Isolated layout regression: no WordPress server or geocoding service needed.
test( 'address suggestions remain reachable inside a clipped mobile form', async ( {
	page,
	isMobile,
	viewport,
} ) => {
	await page.setContent( `
		<!doctype html>
		<meta name="viewport" content="width=device-width, initial-scale=1" />
		<div class="oc-customiser-panel">
			<div style="overflow: hidden">
				<div class="oc-night-sky-controls">
					<div data-address-mode>
						<div class="oc-control-group">
							<label for="place">Place</label>
							<div class="oc-night-sky-combobox">
								<input id="place" type="search" />
								<div class="oc-night-sky-results" hidden>
									${ Array.from(
										{ length: 6 },
										( _, i ) =>
											`<button type="button" class="oc-night-sky-result">Address ${
												i + 1
											}</button>`
									).join( '' ) }
								</div>
							</div>
						</div>
						<p class="oc-night-sky-switch">Enter coordinates instead</p>
					</div>
				</div>
			</div>
		</div>
	` );
	// Without viewport metadata, mobile Chromium uses a desktop-width layout.
	expect( await page.evaluate( () => window.innerWidth ) ).toBe(
		viewport.width
	);
	await page.addStyleTag( { content: css } );
	const results = page.locator( '.oc-night-sky-results' );
	await expect( results ).toBeHidden();
	await results.evaluate( ( element ) => {
		element.hidden = false;
	} );
	await expect( results ).toHaveCSS(
		'position',
		isMobile ? 'static' : 'absolute'
	);
	if ( isMobile ) {
		await expect( page.locator( '#place' ) ).toHaveCSS(
			'font-size',
			'16px'
		);
		const bounds = await results.boundingBox();
		const following = await page
			.locator( '.oc-night-sky-switch' )
			.boundingBox();
		expect( following.y ).toBeGreaterThanOrEqual(
			bounds.y + bounds.height
		);
		// Tap actionability checks ensure both ends of the scrollable list are
		// hit-testable, not merely present behind the clipped wrapper.
		await page
			.getByRole( 'button', { name: 'Address 1', exact: true } )
			.tap();
		await page
			.getByRole( 'button', { name: 'Address 6', exact: true } )
			.tap();
	}
	await results.evaluate( ( element ) => {
		element.hidden = true;
	} );
	await expect( results ).toBeHidden();
} );
