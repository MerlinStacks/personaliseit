const { test, expect } = require( '@playwright/test' );
const path = require( 'node:path' );
const sass = require( 'sass' );
const { readFileSync } = require( 'node:fs' );

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

test( 'address selection saves coordinates with the search field focused', async ( {
	page,
	isMobile,
} ) => {
	const pageErrors = [];
	page.on( 'pageerror', ( error ) => pageErrors.push( error.message ) );
	const source = readFileSync(
		path.resolve(
			__dirname,
			'../../src/frontend/customiser/input-controls.js'
		),
		'utf8'
	);
	const start = source.indexOf( 'const cancelSearch = () =>' );
	const handlers = source.slice(
		start,
		source.indexOf( '\n\t\t\t\tupdate();\n\t\t\t} );', start )
	);
	await page.setContent( `<!doctype html><meta name="viewport" content="width=device-width, initial-scale=1">
		<div class="oc-customiser-panel"><div id="root" class="oc-night-sky-controls">
		<div id="address"><div class="oc-night-sky-combobox"><input id="place" type="search">
		<div id="results" class="oc-night-sky-results" hidden></div></div></div>
		<div id="coordinates" hidden><input id="latitude"><input id="longitude"></div>
		</div></div>` );
	await page.addStyleTag( { content: css } );
	await page.evaluate( ( code ) => {
		const fields = {
			locationLabel: document.getElementById( 'place' ),
			latitude: document.getElementById( 'latitude' ),
			longitude: document.getElementById( 'longitude' ),
		};
		const app = {
			// Native timers require the Window receiver, not this mock app.
			clearStateTimeout: ( timer ) => window.clearTimeout( timer ),
			setStateTimeout: ( callback, delay ) =>
				window.setTimeout( callback, delay ),
			createStateAbortController: () => ( {
				controller: new AbortController(),
				release() {},
				timedOut: () => false,
			} ),
			ensureRequestToken: async () => {},
			restHeaders: ( headers ) => headers,
			data: { locationLookupUrl: '/lookup' },
		};
		new Function(
			'fields',
			'root',
			'addressMode',
			'coordinateMode',
			'resultsEl',
			'fetch',
			`
			let searchTimer = null, searchSequence = 0, locationRequest = null;
			const locationSearchCache = new Map(), error = null;
			const stateSignal = new AbortController().signal;
			const update = () => { window.selectedAddress = { latitude: fields.latitude.value, longitude: fields.longitude.value }; };
			${ code }
		`
		).call(
			app,
			fields,
			document.getElementById( 'root' ),
			document.getElementById( 'address' ),
			document.getElementById( 'coordinates' ),
			document.getElementById( 'results' ),
			async () => ( {
				ok: true,
				json: async () => ( {
					results: Array.from( { length: 6 }, ( _, index ) => ( {
						displayName: `Address ${ index + 1 }, Australia`,
						latitude: -33.8688,
						longitude: 151.2093,
					} ) ),
				} ),
			} )
		);
	}, handlers );
	const input = page.locator( '#place' );
	await input.fill( 'Sydney' );
	expect( pageErrors ).toEqual( [] );
	const option = page.getByRole( 'option' ).last();
	await expect( option ).toBeVisible();
	if ( isMobile ) {
		await option.tap();
	} else {
		await option.click();
	}
	await expect( input ).toHaveValue( 'Address 6, Australia' );
	await expect( input ).toBeFocused();
	await expect( page.locator( '#results' ) ).toBeHidden();
	expect( await page.evaluate( () => window.selectedAddress ) ).toEqual( {
		latitude: '-33.868800',
		longitude: '151.209300',
	} );
	expect( pageErrors ).toEqual( [] );
} );
