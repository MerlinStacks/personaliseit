/**
 * WooCommerce Blocks integration — OverCustomise.
 *
 * Shows personalised preview images + customisation details in the
 * order-summary area of both the Cart Block and Checkout Block.
 *
 * Requires:
 *   @wordpress/plugins   → wp.plugins.registerPlugin
 *   @wordpress/data      → wp.data.useSelect
 *   @wordpress/element   → wp.element (React compat)
 *   @woocommerce/blocks-checkout → wc.blocksCheckout.ExperimentalOrderMeta
 */

import { registerPlugin }        from '@wordpress/plugins';
import { useSelect }             from '@wordpress/data';
import { ExperimentalOrderMeta } from '@woocommerce/blocks-checkout';

// ── Hook: read personalised cart items from the WC Store API ─────────────────

function useOcItems() {
	return useSelect( select => {
		const store = select( 'wc/store/cart' );
		if ( ! store ) return [];
		const cart = store.getCartData ? store.getCartData() : null;
		return ( cart?.items ?? [] ).filter( item => {
			const oc = item?.extensions?.overcustomise;
			return oc && ( oc.preview_url || oc.summary?.length );
		} );
	} );
}

// ── Shared component: personalisation panel ───────────────────────────────────

function OcPersonalisationPanel() {
	const items = useOcItems();
	if ( ! items.length ) return null;

	return (
		<ExperimentalOrderMeta>
			<div className="oc-blocks-panel">
				{ items.map( item => {
					const oc = item.extensions.overcustomise;
					return (
						<div key={ item.key } className="oc-blocks-item">
							{ oc.preview_url && (
								<img
									src={ oc.preview_url }
									alt=""
									className="oc-blocks-preview"
								/>
							) }
							<div className="oc-blocks-details">
								<p className="oc-blocks-name">{ item.name }</p>
								{ ( oc.summary ?? [] ).map( line => (
									<p key={ line.key } className="oc-blocks-line">
										<strong>{ line.key }:</strong>{ ' ' }{ line.value }
									</p>
								) ) }
							</div>
						</div>
					);
				} ) }
			</div>
		</ExperimentalOrderMeta>
	);
}

// ── Register for both Cart Block and Checkout Block ───────────────────────────

registerPlugin( 'overcustomise-cart', {
	render: OcPersonalisationPanel,
	scope:  'woocommerce-cart',
} );

registerPlugin( 'overcustomise-checkout', {
	render: OcPersonalisationPanel,
	scope:  'woocommerce-checkout',
} );
