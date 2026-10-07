/**
 * Shared filtering and numbered pagination for the admin asset libraries.
 * @param {Object}      root0           Options for the library.
 * @param {HTMLElement} root0.grid      Card container.
 * @param {HTMLElement} root0.count     Result count.
 * @param {HTMLElement} root0.empty     Empty-library message.
 * @param {Function}    root0.getItems  Read current assets.
 * @param {Function}    root0.getGroups Read current groups.
 * @param {string}      root0.memberKey Group membership property.
 * @param {Function}    root0.buildCard Render an interactive card.
 * @param {Function}    root0.editGroup Open the group editor.
 */
export function createLibraryBrowser( {
	grid,
	count,
	empty,
	getItems,
	getGroups,
	memberKey,
	buildCard,
	editGroup,
} ) {
	const toolbar = document.getElementById( 'oc-library-toolbar' );
	if ( ! toolbar || ! grid ) {
		return null;
	}
	let page = 1;
	toolbar.innerHTML = `
		<label>Search<input type="search" class="oc-input" placeholder="Search by name" data-filter="search" /></label>
		<label>Group<select data-filter="group"><option value="">All groups</option></select></label>
		<label>Status<select data-filter="status"><option value="">All statuses</option><option value="active">Active</option><option value="inactive">Inactive</option></select></label>
		<label>Per page<select data-filter="size"><option>24</option><option>48</option><option>96</option></select></label>
		<button type="button" class="oc-btn oc-btn-secondary" data-action="reset">Reset filters</button>
		<button type="button" class="oc-btn oc-btn-secondary" data-action="edit" disabled>Edit group</button>`;
	const search = toolbar.querySelector( '[data-filter="search"]' );
	const groupSelect = toolbar.querySelector( '[data-filter="group"]' );
	const status = toolbar.querySelector( '[data-filter="status"]' );
	const size = toolbar.querySelector( '[data-filter="size"]' );
	const edit = toolbar.querySelector( '[data-action="edit"]' );
	const noResults = document.createElement( 'p' );
	noResults.className = 'oc-empty';
	noResults.textContent =
		'No matching items. Try changing or resetting the filters.';
	grid.after( noResults );
	const pagination = document.createElement( 'nav' );
	pagination.className = 'oc-library-pagination';
	pagination.setAttribute( 'aria-label', 'Library pages' );
	noResults.after( pagination );
	count?.setAttribute( 'aria-live', 'polite' );

	function render() {
		const groups = getGroups();
		const selected = groupSelect.value;
		groupSelect.replaceChildren(
			new window.Option( 'All groups', '' ),
			new window.Option( 'Ungrouped', 'ungrouped' )
		);
		groups.forEach( ( group ) =>
			groupSelect.add(
				new window.Option( group.name, String( group.id ) )
			)
		);
		groupSelect.value = [
			'',
			'ungrouped',
			...groups.map( ( group ) => String( group.id ) ),
		].includes( selected )
			? selected
			: '';
		const group = groups.find(
			( item ) => String( item.id ) === groupSelect.value
		);
		edit.disabled = ! group;
		const members = new Set( ( group?.[ memberKey ] || [] ).map( Number ) );
		const grouped = new Set(
			groups.flatMap( ( item ) => item[ memberKey ].map( Number ) )
		);
		const items = getItems();
		const query = search.value.trim().toLocaleLowerCase();
		const filtered = items
			.filter(
				( item ) =>
					item.name.toLocaleLowerCase().includes( query ) &&
					( ! status.value ||
						Boolean( item.active ) ===
							( status.value === 'active' ) ) &&
					( groupSelect.value === 'ungrouped'
						? ! grouped.has( Number( item.id ) )
						: ! group || members.has( Number( item.id ) ) )
			)
			.sort(
				( a, b ) =>
					a.name.localeCompare( b.name ) ||
					Number( a.id ) - Number( b.id )
			);
		const perPage = Number( size.value );
		const pages = Math.max( 1, Math.ceil( filtered.length / perPage ) );
		page = Math.min( page, pages );
		const start = ( page - 1 ) * perPage;
		grid.replaceChildren(
			...filtered.slice( start, start + perPage ).map( buildCard )
		);
		grid.style.display = filtered.length ? '' : 'none';
		if ( empty ) {
			empty.style.display = items.length ? 'none' : '';
		}
		noResults.hidden = ! items.length || !! filtered.length;
		if ( count ) {
			count.textContent = filtered.length
				? `${ start + 1 }–${ Math.min(
						start + perPage,
						filtered.length
				  ) } of ${ filtered.length } items`
				: '0 items';
		}
		pagination.replaceChildren();
		pagination.hidden = pages === 1;
		function button( label, target, disabled = false ) {
			const element = document.createElement( 'button' );
			element.type = 'button';
			element.className = 'oc-btn oc-btn-secondary';
			element.textContent = label;
			element.disabled = disabled;
			if ( label === String( page ) ) {
				element.setAttribute( 'aria-current', 'page' );
			}
			element.addEventListener( 'click', () => {
				page = target;
				render();
				pagination.querySelector( '[aria-current="page"]' )?.focus();
			} );
			pagination.appendChild( element );
		}
		button( 'Previous', page - 1, page === 1 );
		let previous = 0;
		for ( let number = 1; number <= pages; number++ ) {
			if (
				number !== 1 &&
				number !== pages &&
				Math.abs( number - page ) > 2
			) {
				continue;
			}
			if ( previous && number - previous > 1 ) {
				const gap = document.createElement( 'span' );
				gap.textContent = '…';
				pagination.appendChild( gap );
			}
			button( String( number ), number );
			previous = number;
		}
		button( 'Next', page + 1, page === pages );
	}
	toolbar.addEventListener( 'input', ( event ) => {
		if ( event.target === search ) {
			page = 1;
			render();
		}
	} );
	toolbar.addEventListener( 'change', ( event ) => {
		if ( event.target !== search ) {
			page = 1;
			render();
		}
	} );
	toolbar
		.querySelector( '[data-action="reset"]' )
		.addEventListener( 'click', () => {
			search.value = '';
			groupSelect.value = '';
			status.value = '';
			page = 1;
			render();
		} );
	edit.addEventListener( 'click', () => {
		const group = getGroups().find(
			( item ) => String( item.id ) === groupSelect.value
		);
		if ( group ) {
			editGroup( group );
		}
	} );
	render();
	return { refresh: render };
}
