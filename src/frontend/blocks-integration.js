/* eslint-disable import/no-unresolved */

import { registerCheckoutFilters } from '@woocommerce/blocks-checkout';

const getSummary = ( extensions ) => {
	const summary = extensions?.overcustomise?.summary || [];
	return Array.isArray( summary ) ? summary : [];
};

const escapeHtml = ( value ) => {
	return String( value )
		.replace( /&/g, '&amp;' )
		.replace( /"/g, '&quot;' )
		.replace( /</g, '&lt;' )
		.replace( />/g, '&gt;' )
		.replace( /'/g, '&#039;' );
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
	itemName: ( defaultValue, extensions ) => {
		const summary = renderSummary( getSummary( extensions ) );

		return `<span class="oc-blocks-line-name">${ defaultValue }</span>${ summary }`;
	},
} );
