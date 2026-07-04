/**
 * WooCommerce Blocks integration.
 *
 * The Store API image filter replaces the product image where Blocks renders an
 * image slot. Checkout and some cart layouts do not always render that slot, so
 * this filter also prepends the preview to the item name from our extension data.
 */

import { registerCheckoutFilters } from '@woocommerce/blocks-checkout';

const getPreviewUrl = ( extensions, args ) => {
	const fromExtensions = extensions?.overcustomise?.preview_url;
	if ( fromExtensions ) return fromExtensions;

	return args?.cartItem?.extensions?.overcustomise?.preview_url || '';
};

const escapeAttribute = value => String( value )
	.replace( /&/g, '&amp;' )
	.replace( /"/g, '&quot;' )
	.replace( /</g, '&lt;' )
	.replace( />/g, '&gt;' );

registerCheckoutFilters( 'overcustomise', {
	itemName: ( defaultValue, extensions, args ) => {
		const previewUrl = getPreviewUrl( extensions, args );
		if ( ! previewUrl || defaultValue.includes( 'oc-blocks-line-preview' ) ) {
			return defaultValue;
		}

		const safeUrl = escapeAttribute( previewUrl );
		return `<span class="oc-blocks-line-preview"><img src="${ safeUrl }" alt="Personalised preview" loading="lazy" /></span>${ defaultValue }`;
	},
} );
