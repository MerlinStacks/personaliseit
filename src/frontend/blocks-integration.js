import { registerCheckoutFilters } from '@woocommerce/blocks-checkout';

const getPreviewUrl = ( extensions ) => {
	const previewUrl = extensions?.overcustomise?.preview_url || '';
	return typeof previewUrl === 'string' && previewUrl ? previewUrl : '';
};

const getSummary = ( extensions ) => {
	const summary = extensions?.overcustomise?.summary || [];
	return Array.isArray( summary ) ? summary : [];
};

const escapeAttribute = ( value ) => {
	return String( value )
		.replace( /&/g, '&amp;' )
		.replace( /"/g, '&quot;' )
		.replace( /</g, '&lt;' )
		.replace( />/g, '&gt;' );
};

const escapeHtml = ( value ) => {
	return escapeAttribute( value ).replace( /'/g, '&#039;' );
};

const renderSummary = ( summary ) => {
	const lines = summary
		.map( ( line ) => {
			const key = typeof line?.key === 'string' ? line.key : '';
			const value = typeof line?.value === 'string' ? line.value : '';
			if ( ! key || ! value ) {
				return '';
			}

			return `<li><strong>${ escapeHtml( key ) }:</strong> ${ escapeHtml(
				value
			) }</li>`;
		} )
		.filter( Boolean )
		.join( '' );

	return lines
		? `<ul class="oc-blocks-personalisation-summary">${ lines }</ul>`
		: '';
};

registerCheckoutFilters( 'overcustomise', {
	cartItemClass: ( defaultValue, extensions ) => {
		return getPreviewUrl( extensions ) || getSummary( extensions ).length
			? `${ defaultValue } oc-has-personalisation`.trim()
			: defaultValue;
	},
	itemName: ( defaultValue, extensions ) => {
		const previewUrl = getPreviewUrl( extensions );
		const summary = renderSummary( getSummary( extensions ) );
		const escapedPreviewUrl = escapeAttribute( previewUrl );
		const preview = previewUrl
			? `<span class="oc-blocks-line-preview"><img src="${ escapedPreviewUrl }" alt="Personalised preview" loading="lazy" /></span>`
			: '';

		return `${ preview }<span class="oc-blocks-line-name">${ defaultValue }</span>${ summary }`;
	},
} );
