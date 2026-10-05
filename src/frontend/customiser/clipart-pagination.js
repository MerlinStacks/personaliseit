/**
 * Loaded only when a customer searches or asks for another clipart page.
 * @param {number}  layerId Clipart layer identifier.
 * @param {boolean} reset   Start a fresh search instead of appending a page.
 */
export default async function loadClipartPage( layerId, reset = false ) {
	const page = this.data.clipartPages?.[ layerId ];
	const grid = document.querySelector(
		`[data-oc-clipart-grid="${ layerId }"]`
	);
	const more = document.querySelector(
		`[data-oc-clipart-more="${ layerId }"]`
	);
	if ( ! page?.url || ! grid || ! more ) {
		return;
	}
	const status = document.querySelector(
		`[data-oc-clipart-status="${ layerId }"]`
	);
	grid.ocClipartRequest?.abort();
	const request = this.createStateAbortController( 10000 );
	const controller = request.controller;
	grid.ocClipartRequest = controller;
	const search = this.clipartSearchTerms[ layerId ] || '';
	const category = this.clipartCategoryFilters[ layerId ] || '';
	const url = new URL( page.url, window.location.href );
	url.searchParams.set( 'page', String( reset ? 1 : page.nextPage || 2 ) );
	url.searchParams.set( 'search', search );
	url.searchParams.set( 'category', category );
	more.disabled = true;
	grid.setAttribute( 'aria-busy', 'true' );
	if ( status ) {
		status.textContent = 'Loading clipart…';
	}
	const current = () =>
		grid.isConnected &&
		grid.ocClipartRequest === controller &&
		this.data.clipartPages?.[ layerId ] === page &&
		( this.clipartSearchTerms[ layerId ] || '' ) === search &&
		( this.clipartCategoryFilters[ layerId ] || '' ) === category;
	try {
		const response = await fetch( url.toString(), {
			cache: 'no-store',
			signal: controller.signal,
		} );
		if ( ! response.ok ) {
			throw new Error( 'Clipart unavailable' );
		}
		const result = await response.json();
		if (
			! Array.isArray( result.items ) ||
			! Number.isInteger( result.nextPage )
		) {
			throw new Error( 'Invalid clipart response' );
		}
		if ( ! current() ) {
			return;
		}
		if ( reset ) {
			grid.replaceChildren();
		}
		grid.querySelectorAll(
			'.oc-clipart-empty, .oc-clipart-no-results'
		).forEach( ( item ) => item.remove() );
		for ( const item of result.items ) {
			const id = Number( item.id );
			if (
				! Number.isInteger( id ) ||
				id <= 0 ||
				grid.querySelector( `[data-oc-clipart="${ id }"]` )
			) {
				continue;
			}
			const button = document.createElement( 'button' );
			button.type = 'button';
			button.className = 'oc-clipart-item';
			button.dataset.ocClipart = String( id );
			button.dataset.ocLayerClipart = String( layerId );
			button.dataset.ocClipartUrl = item.url;
			button.dataset.ocClipartRecolourable = item.recolourable
				? '1'
				: '0';
			button.dataset.ocClipartGroups = ( item.groupNames || [] ).join(
				'||'
			);
			button.title = item.name;
			button.setAttribute( 'aria-label', item.name );
			const selected = Number( this.inputs[ layerId ]?.clipartId ) === id;
			button.classList.toggle( 'oc-selected', selected );
			button.setAttribute( 'aria-pressed', String( selected ) );
			const image = document.createElement( 'img' );
			image.src = item.url;
			image.alt = item.name;
			image.loading = 'lazy';
			button.appendChild( image );
			grid.appendChild( button );
		}
		page.nextPage = result.nextPage;
		page.hasMore = Boolean( result.hasMore );
		page.retryReset = false;
		more.hidden = ! page.hasMore;
		if ( status ) {
			status.textContent = grid.querySelector( '.oc-clipart-item' )
				? ''
				: 'No clipart matches your search.';
		}
		this.refreshClipartCarousel( layerId );
	} catch ( error ) {
		if (
			current() &&
			( error.name !== 'AbortError' || request.timedOut() )
		) {
			page.retryReset = reset;
			more.hidden = false;
			if ( status ) {
				status.textContent =
					'Clipart could not be loaded. Use Load more clipart to retry.';
			}
		}
	} finally {
		request.release();
		if ( grid.ocClipartRequest === controller ) {
			more.disabled = false;
			grid.removeAttribute( 'aria-busy' );
		}
	}
}
