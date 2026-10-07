/**
 * Paginated group membership, kept independently of the visible checkboxes.
 *
 * @param {HTMLElement} container   Picker grid.
 * @param {Function}    renderItems Render a page of items and their selection.
 * @param {Function}    updateCount Update the total selected count.
 * @return {Object} Picker controls.
 */
export function createGroupPicker( container, renderItems, updateCount ) {
	const pageSize = 12;
	let items = [];
	let selected = new Set();
	let page = 1;
	const navigation = document.createElement( 'nav' );
	navigation.className = 'oc-group-picker-pagination';
	navigation.setAttribute( 'aria-label', 'Group selection pages' );
	container.classList.add( 'oc-group-picker-grid' );
	container.after( navigation );

	function render() {
		const pages = Math.max( 1, Math.ceil( items.length / pageSize ) );
		renderItems( items.slice( ( page - 1 ) * pageSize, page * pageSize ), [
			...selected,
		] );
		container.scrollTop = 0;
		navigation.innerHTML = `
			<button type="button" class="oc-btn oc-btn-secondary" data-step="-1" ${
				page === 1 ? 'disabled' : ''
			}>Previous</button>
			<span aria-live="polite">Page ${ page } of ${ pages }</span>
			<button type="button" class="oc-btn oc-btn-secondary" data-step="1" ${
				page === pages ? 'disabled' : ''
			}>Next</button>`;
		navigation.hidden = items.length <= pageSize;
		updateCount( selected.size );
	}

	navigation.addEventListener( 'click', ( event ) => {
		const button = event.target.closest( 'button[data-step]' );
		if ( ! button || button.disabled ) {
			return;
		}
		page += Number( button.dataset.step );
		render();
		// Keep keyboard navigation in the pager after replacing its buttons.
		const nextFocus =
			navigation.querySelector(
				`button[data-step="${ button.dataset.step }"]:not(:disabled)`
			) || navigation.querySelector( 'button:not(:disabled)' );
		nextFocus?.focus();
	} );
	container.addEventListener( 'change', ( event ) => {
		const checkbox = event.target;
		if ( ! checkbox.matches( 'input[type="checkbox"]' ) ) {
			return;
		}
		const id = Number( checkbox.value );
		if ( checkbox.checked ) {
			selected.add( id );
		} else {
			selected.delete( id );
		}
		checkbox
			.closest( 'label' )
			.classList.toggle( 'oc-selected', checkbox.checked );
		updateCount( selected.size );
	} );

	return {
		open( availableItems, selectedIds ) {
			items = availableItems;
			selected = new Set( selectedIds.map( Number ) );
			page = 1;
			render();
		},
		getSelectedIds: () => [ ...selected ],
	};
}
