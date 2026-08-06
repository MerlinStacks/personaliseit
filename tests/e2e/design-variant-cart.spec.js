const { test, expect } = require( '@playwright/test' );

const hasBaseUrl = Boolean( process.env.E2E_BASE_URL );
const productPath = process.env.E2E_PRODUCT_PATH || '/';
const cartPath = process.env.E2E_CART_PATH || '/cart/';
const textValue = process.env.E2E_TEXT_VALUE || 'Playwright Test Design';

test.describe( 'product customiser', () => {
	test.skip( ! hasBaseUrl, 'Set E2E_BASE_URL to run browser tests.' );

	test.beforeEach( async ( { page } ) => {
		await page.goto( productPath, { waitUntil: 'domcontentloaded' } );
		await expect( page.locator( '#oc-customiser-panel' ) ).toBeVisible();
	} );

	test( 'switches artwork variants and serialises the selection', async ( {
		page,
	} ) => {
		const variants = page.locator( '[data-oc-design-variant]' );
		test.skip(
			( await variants.count() ) < 2,
			'The E2E product needs two design variants.'
		);

		const target = process.env.E2E_VARIANT_LABEL
			? variants
					.filter( { hasText: process.env.E2E_VARIANT_LABEL } )
					.first()
			: variants.nth( 1 );
		const variantId = await target.getAttribute( 'data-oc-design-variant' );
		await target.click();

		await expect( target ).toHaveAttribute( 'aria-pressed', 'true' );
		await expect
			.poll( async () => {
				const value = await page
					.locator( '#oc-customisation-data' )
					.inputValue();
				return JSON.parse( value ).designVariant;
			} )
			.toBe( variantId );
	} );

	test( 'keeps personalisation data through add to cart', async ( {
		page,
	} ) => {
		const textInput = page
			.locator( '[data-oc-layer-text]:visible' )
			.first();
		const hasTextInput = ( await textInput.count() ) > 0;
		if ( hasTextInput ) {
			await textInput.fill( textValue );
			await textInput.blur();
		}

		const hiddenField = page.locator( '#oc-customisation-data' );
		await expect
			.poll( async () => ( await hiddenField.inputValue() ).length )
			.toBeGreaterThan( 2 );
		page.on( 'dialog', ( dialog ) => dialog.accept() );
		const cartRequest = page.waitForRequest(
			( request ) => request.method() === 'POST',
			{ timeout: 15_000 }
		);
		await page
			.locator( '.single_add_to_cart_button, form.cart [type="submit"]' )
			.first()
			.click();
		await cartRequest;

		await page.goto( cartPath, { waitUntil: 'domcontentloaded' } );
		const cart = page.locator( '.woocommerce-cart-form, .wc-block-cart' );
		await expect( cart ).toBeVisible();
		if ( process.env.E2E_PRODUCT_NAME ) {
			await expect( cart ).toContainText( process.env.E2E_PRODUCT_NAME );
		}
		if ( hasTextInput ) {
			await expect( cart ).toContainText( textValue );
		}
	} );
} );
