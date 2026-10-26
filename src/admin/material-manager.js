/** Laser material and group manager. */

/* eslint-disable no-alert */

let materials = ( window.ocMaterialsData || [] ).map( normaliseMaterial );
let groups = ( window.ocMaterialGroups || [] ).map( normaliseGroup );
let editMaterialId = '';
let editGroupId = '';

function normaliseMaterial( item ) {
	return {
		id: String( item.id || '' ),
		name: String( item.name || '' ),
		surface: item.surface || '#171717',
		engraving: item.engraving || '#ffffff',
		active: !! item.active,
	};
}

function normaliseGroup( group ) {
	return {
		id: String( group.id || '' ),
		name: String( group.name || '' ),
		material_ids: ( group.material_ids || [] ).map( String ),
	};
}

function escapeHtml( value ) {
	return String( value )
		.replace( /&/g, '&amp;' )
		.replace( /</g, '&lt;' )
		.replace( />/g, '&gt;' )
		.replace( /"/g, '&quot;' );
}

async function request( action, values ) {
	const body = new URLSearchParams( {
		action,
		nonce: window.ocMaterialNonce,
		...values,
	} );
	if ( Array.isArray( values.material_ids ) ) {
		body.delete( 'material_ids' );
		values.material_ids.forEach( ( id ) =>
			body.append( 'material_ids[]', id )
		);
	}
	const response = await fetch( window.ocAjaxUrl, { method: 'POST', body } );
	const result = await response.json();
	if ( ! response.ok || ! result.success ) {
		throw new Error( result.data?.message || 'Unable to save changes.' );
	}
	return result.data;
}

function renderMaterials() {
	const grid = document.getElementById( 'oc-material-grid' );
	const empty = document.getElementById( 'oc-materials-empty' );
	grid.innerHTML = '';
	materials.forEach( ( item ) => {
		const card = document.createElement( 'div' );
		card.className = `oc-colour-card oc-material-card${
			item.active ? '' : ' oc-colour-card--inactive'
		}`;
		card.tabIndex = 0;
		card.setAttribute( 'role', 'button' );
		card.innerHTML = `<div class="oc-material-swatch"><span style="background:${ escapeHtml(
			item.surface
		) }"></span><span style="background:${ escapeHtml(
			item.engraving
		) }"></span></div><div class="oc-colour-card-body"><div class="oc-colour-card-title-row"><p class="oc-colour-card-name">${ escapeHtml(
			item.name
		) }</p><span class="oc-badge ${
			item.active ? 'oc-badge-active' : 'oc-badge-inactive'
		}">${
			item.active ? 'Active' : 'Inactive'
		}</span></div><p class="oc-colour-hex-label">Surface ${ escapeHtml(
			item.surface.toUpperCase()
		) } · Engraving ${ escapeHtml(
			item.engraving.toUpperCase()
		) }</p></div>`;
		card.addEventListener( 'click', () => openMaterial( item.id ) );
		card.addEventListener( 'keydown', ( event ) => {
			if ( event.key === 'Enter' || event.key === ' ' ) {
				event.preventDefault();
				openMaterial( item.id );
			}
		} );
		grid.appendChild( card );
	} );
	empty.style.display = materials.length ? 'none' : '';
	document.getElementById( 'oc-materials-count' ).textContent = `${
		materials.length
	} ${ materials.length === 1 ? 'material' : 'materials' }`;
	document.querySelector(
		'[data-target="oc-tab-materials"] .oc-tab-count'
	).textContent = materials.length;
}

function renderGroups() {
	const grid = document.getElementById( 'oc-material-group-grid' );
	const empty = document.getElementById( 'oc-material-groups-empty' );
	grid.innerHTML = '';
	groups.forEach( ( group ) => {
		const card = document.createElement( 'div' );
		card.className = 'oc-group-card';
		card.tabIndex = 0;
		card.setAttribute( 'role', 'button' );
		const swatches = group.material_ids
			.slice( 0, 8 )
			.map( ( id ) => materials.find( ( item ) => item.id === id ) )
			.filter( Boolean )
			.map(
				( item ) =>
					`<span class="oc-material-group-dot" title="${ escapeHtml(
						item.name
					) }" style="--surface:${ escapeHtml(
						item.surface
					) };--engraving:${ escapeHtml( item.engraving ) }"></span>`
			)
			.join( '' );
		card.innerHTML = `<div class="oc-group-card-body"><p class="oc-group-card-name">${ escapeHtml(
			group.name
		) }</p><p class="oc-group-card-count">${ group.material_ids.length } ${
			group.material_ids.length === 1 ? 'material' : 'materials'
		}</p><div class="oc-material-group-swatches">${
			swatches || 'Empty group'
		}</div></div>`;
		card.addEventListener( 'click', () => openGroup( group.id ) );
		card.addEventListener( 'keydown', ( event ) => {
			if ( event.key === 'Enter' || event.key === ' ' ) {
				event.preventDefault();
				openGroup( group.id );
			}
		} );
		grid.appendChild( card );
	} );
	empty.style.display = groups.length ? 'none' : '';
	document.getElementById( 'oc-material-groups-count' ).textContent = `${
		groups.length
	} ${ groups.length === 1 ? 'group' : 'groups' }`;
	document.querySelector(
		'[data-target="oc-tab-material-groups"] .oc-tab-count'
	).textContent = groups.length;
}

function setError( id, message = '' ) {
	const element = document.getElementById( id );
	element.textContent = message;
	element.style.display = message ? '' : 'none';
}

function openMaterial( id = '' ) {
	editMaterialId = id;
	const item = materials.find( ( candidate ) => candidate.id === id );
	document.getElementById( 'oc-material-modal-title' ).textContent = item
		? 'Edit Material'
		: 'Add Material';
	document.getElementById( 'oc_material_name' ).value = item?.name || '';
	document.getElementById( 'oc_material_surface' ).value =
		item?.surface || '#171717';
	document.getElementById( 'oc_material_engraving' ).value =
		item?.engraving || '#ffffff';
	document.getElementById( 'oc_material_active' ).checked =
		item?.active ?? true;
	document.getElementById( 'oc-material-delete-btn' ).style.display = item
		? ''
		: 'none';
	setError( 'oc-material-error' );
	document.getElementById( 'oc-material-modal' ).hidden = false;
}

function closeMaterial() {
	document.getElementById( 'oc-material-modal' ).hidden = true;
}

async function saveMaterial() {
	try {
		const saved = normaliseMaterial(
			await request( 'oc_material_save', {
				id: editMaterialId,
				name: document.getElementById( 'oc_material_name' ).value,
				surface: document.getElementById( 'oc_material_surface' ).value,
				engraving: document.getElementById( 'oc_material_engraving' )
					.value,
				active: document.getElementById( 'oc_material_active' ).checked
					? '1'
					: '',
			} )
		);
		const index = materials.findIndex( ( item ) => item.id === saved.id );
		if ( index < 0 ) {
			materials.push( saved );
		} else {
			materials[ index ] = saved;
		}
		renderMaterials();
		renderGroups();
		closeMaterial();
	} catch ( error ) {
		setError( 'oc-material-error', error.message );
	}
}

async function deleteMaterial() {
	if ( ! editMaterialId || ! window.confirm( 'Delete this material?' ) ) {
		return;
	}
	try {
		await request( 'oc_material_delete', { id: editMaterialId } );
		materials = materials.filter( ( item ) => item.id !== editMaterialId );
		groups = groups.map( ( group ) => ( {
			...group,
			material_ids: group.material_ids.filter(
				( id ) => id !== editMaterialId
			),
		} ) );
		renderMaterials();
		renderGroups();
		closeMaterial();
	} catch ( error ) {
		setError( 'oc-material-error', error.message );
	}
}

function openGroup( id = '' ) {
	editGroupId = id;
	const group = groups.find( ( candidate ) => candidate.id === id );
	document.getElementById( 'oc-material-group-name' ).value =
		group?.name || '';
	document.getElementById( 'oc-material-group-delete-btn' ).style.display =
		group ? '' : 'none';
	const selected = new Set( group?.material_ids || [] );
	const picker = document.getElementById( 'oc-material-group-picker' );
	picker.innerHTML = materials
		.map(
			( item ) =>
				`<label class="oc-group-font-item${
					selected.has( item.id ) ? ' oc-selected' : ''
				}"><input type="checkbox" value="${ escapeHtml( item.id ) }"${
					selected.has( item.id ) ? ' checked' : ''
				}><span class="oc-material-picker-swatch" style="--surface:${ escapeHtml(
					item.surface
				) };--engraving:${ escapeHtml(
					item.engraving
				) }"></span><span class="oc-group-font-info"><span class="oc-group-font-info-name">${ escapeHtml(
					item.name
				) }</span></span></label>`
		)
		.join( '' );
	const updateCount = () => {
		document.getElementById(
			'oc-material-group-selected-count'
		).textContent = `${
			picker.querySelectorAll( ':checked' ).length
		} selected`;
	};
	picker.onchange = ( event ) => {
		event.target
			.closest( 'label' )
			?.classList.toggle( 'oc-selected', event.target.checked );
		updateCount();
	};
	updateCount();
	setError( 'oc-material-group-error' );
	document.getElementById( 'oc-material-group-modal' ).hidden = false;
}

function closeGroup() {
	document.getElementById( 'oc-material-group-modal' ).hidden = true;
}

async function saveGroup() {
	const picker = document.getElementById( 'oc-material-group-picker' );
	try {
		const saved = normaliseGroup(
			await request( 'oc_material_group_save', {
				id: editGroupId,
				name: document.getElementById( 'oc-material-group-name' ).value,
				material_ids: Array.from(
					picker.querySelectorAll( ':checked' ),
					( input ) => input.value
				),
			} )
		);
		const index = groups.findIndex( ( group ) => group.id === saved.id );
		if ( index < 0 ) {
			groups.push( saved );
		} else {
			groups[ index ] = saved;
		}
		renderGroups();
		closeGroup();
	} catch ( error ) {
		setError( 'oc-material-group-error', error.message );
	}
}

async function deleteGroup() {
	if ( ! editGroupId || ! window.confirm( 'Delete this material group?' ) ) {
		return;
	}
	try {
		await request( 'oc_material_group_delete', { id: editGroupId } );
		groups = groups.filter( ( group ) => group.id !== editGroupId );
		renderGroups();
		closeGroup();
	} catch ( error ) {
		setError( 'oc-material-group-error', error.message );
	}
}

document.addEventListener( 'DOMContentLoaded', () => {
	renderMaterials();
	renderGroups();
	document.querySelectorAll( '.oc-tab' ).forEach( ( tab ) => {
		tab.addEventListener( 'click', () => {
			document
				.querySelectorAll( '.oc-tab' )
				.forEach( ( item ) =>
					item.classList.toggle( 'oc-tab--active', item === tab )
				);
			document.querySelectorAll( '.oc-tab-panel' ).forEach( ( panel ) => {
				panel.hidden = panel.id !== tab.dataset.target;
			} );
			const groupsActive =
				tab.dataset.target === 'oc-tab-material-groups';
			document.getElementById( 'oc-add-material-btn' ).style.display =
				groupsActive ? 'none' : '';
			document.getElementById(
				'oc-create-material-group-btn'
			).style.display = groupsActive ? '' : 'none';
		} );
	} );
	document
		.getElementById( 'oc-add-material-btn' )
		.addEventListener( 'click', () => openMaterial() );
	document
		.getElementById( 'oc-create-material-group-btn' )
		.addEventListener( 'click', () => openGroup() );
	document
		.querySelectorAll( '[data-oc-close-material]' )
		.forEach( ( button ) =>
			button.addEventListener( 'click', closeMaterial )
		);
	document
		.querySelectorAll( '[data-oc-close-material-group]' )
		.forEach( ( button ) =>
			button.addEventListener( 'click', closeGroup )
		);
	document
		.getElementById( 'oc-material-save-btn' )
		.addEventListener( 'click', saveMaterial );
	document
		.getElementById( 'oc-material-delete-btn' )
		.addEventListener( 'click', deleteMaterial );
	document
		.getElementById( 'oc-material-group-save-btn' )
		.addEventListener( 'click', saveGroup );
	document
		.getElementById( 'oc-material-group-delete-btn' )
		.addEventListener( 'click', deleteGroup );
} );
