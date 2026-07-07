import { registerCheckoutFilters } from '@woocommerce/blocks-checkout';

const getPreviewUrl = ( extensions ) => {
	const previewUrl = extensions?.overcustomise?.preview_url || '';
	return typeof previewUrl === 'string' && previewUrl ? previewUrl : '';
};

const escapeAttribute = ( value ) => {
	return String( value )
		.replace( /&/g, '&amp;' )
		.replace( /"/g, '&quot;' )
		.replace( /</g, '&lt;' )
		.replace( />/g, '&gt;' );
};

registerCheckoutFilters( 'overcustomise', {
	cartItemClass: ( defaultValue, extensions ) => {
		return getPreviewUrl( extensions )
			? `${ defaultValue } oc-has-personalised-preview`.trim()
			: defaultValue;
	},
	itemName: ( defaultValue, extensions ) => {
		const previewUrl = getPreviewUrl( extensions );
		if ( ! previewUrl ) {
			return defaultValue;
		}

		return `<span class="oc-blocks-line-preview"><img src="${ escapeAttribute( previewUrl ) }" alt="Personalised preview" loading="lazy" /></span>${ defaultValue }`;
	},
} );
