/**
 * Colour Manager admin JS.
 *
 * Handles:
 *  - Tab switching (Colours / Colour Groups)
 *  - Colour editor modal (add / edit, colour picker ↔ hex sync)
 *  - Colour AJAX save / delete (toggle & delete via redirect links already in PHP)
 *  - Colour group editor modal (create / update / delete)
 */

/* eslint-disable no-console, no-alert, no-undef, @wordpress/no-unused-vars-before-return */

// ---------------------------------------------------------------------------
// State
// ---------------------------------------------------------------------------

const colours = ( window.ocColoursData || [] ).map( normaliseColour );
let groups = ( window.ocColourGroups || [] ).map( normaliseGroup );

let editColourId = null; // null = adding new, number = editing existing
let editGroupId = null;

// ---------------------------------------------------------------------------
// Normalisers
// ---------------------------------------------------------------------------

function normaliseColour( c ) {
	return {
		id: Number( c.id ),
		name: c.name || '',
		hex: c.hex || '#000000',
		active: !! c.active,
		toggleUrl: c.toggleUrl || '',
		deleteUrl: c.deleteUrl || '',
	};
}

function normaliseGroup( g ) {
	return {
		id: Number( g.id ),
		name: g.name || '',
		colourIds: ( g.colourIds || [] ).map( Number ),
	};
}

// ---------------------------------------------------------------------------
// Escape helper
// ---------------------------------------------------------------------------

function h( str ) {
	return String( str )
		.replace( /&/g, '&amp;' )
		.replace( /</g, '&lt;' )
		.replace( />/g, '&gt;' )
		.replace( /"/g, '&quot;' );
}

// ---------------------------------------------------------------------------
// Tab switching
// ---------------------------------------------------------------------------

function initTabs() {
	const tabs = document.querySelectorAll( '.oc-tab' );
	const panels = document.querySelectorAll( '.oc-tab-panel' );

	const addBtn = document.getElementById( 'oc-add-colour-btn' );
	const createGrpBtn = document.getElementById(
		'oc-create-colour-group-btn'
	);

	tabs.forEach( ( tab ) => {
		tab.addEventListener( 'click', () => {
			tabs.forEach( ( t ) => t.classList.remove( 'oc-tab--active' ) );
			panels.forEach( ( p ) => ( p.hidden = true ) );

			tab.classList.add( 'oc-tab--active' );
			const target = document.getElementById( tab.dataset.target );
			if ( target ) {
				target.hidden = false;
			}

			const isGroups = tab.dataset.target === 'oc-tab-colour-groups';
			if ( addBtn ) {
				addBtn.style.display = isGroups ? 'none' : '';
			}
			if ( createGrpBtn ) {
				createGrpBtn.style.display = isGroups ? 'inline-flex' : 'none';
			}
		} );
	} );
}

// ---------------------------------------------------------------------------
// Colour grid helpers
// ---------------------------------------------------------------------------

function buildColourCardEl( colour ) {
	const card = document.createElement( 'div' );
	card.className =
		'oc-colour-card' + ( colour.active ? '' : ' oc-colour-card--inactive' );
	card.dataset.colourId = colour.id;
	card.dataset.colourHex = colour.hex;
	card.setAttribute( 'role', 'button' );
	card.setAttribute( 'tabindex', '0' );

	card.innerHTML = `
		<div class="oc-colour-swatch" style="background:${ h( colour.hex ) };"></div>
		<div class="oc-colour-card-body">
			<div class="oc-colour-card-title-row">
				<p class="oc-colour-card-name" title="${ h( colour.name ) }">${ h(
					colour.name
				) }</p>
				<span class="oc-badge ${
					colour.active ? 'oc-badge-active' : 'oc-badge-inactive'
				}">
					${ colour.active ? 'Active' : 'Inactive' }
				</span>
			</div>
			<p class="oc-colour-hex-label">
				<span class="oc-code">${ h( colour.hex.toUpperCase() ) }</span>
			</p>
			<div class="oc-colour-card-actions">
				<a href="${ h( colour.toggleUrl ) }" class="oc-btn oc-btn-secondary oc-btn-sm">
					${ colour.active ? 'Deactivate' : 'Activate' }
				</a>
				<a href="${ h( colour.deleteUrl ) }"
				   onclick="return confirm('Delete this colour?');"
				   class="oc-btn oc-btn-danger oc-btn-sm">Delete</a>
			</div>
		</div>`;

	card.addEventListener( 'click', ( e ) => {
		if ( e.target.closest( 'a' ) ) {
			return;
		}
		openColourModal( colour.id );
	} );
	card.addEventListener( 'keydown', ( e ) => {
		if ( e.key === 'Enter' || e.key === ' ' ) {
			openColourModal( colour.id );
		}
	} );

	return card;
}

function updateColourGridUI() {
	const grid = document.getElementById( 'oc-colour-grid' );
	const empty = document.getElementById( 'oc-colours-empty' );
	const count = document.getElementById( 'oc-colours-count' );
	const tab = document.querySelector(
		'.oc-tab[data-target="oc-tab-colours"] .oc-tab-count'
	);

	if ( ! grid ) {
		return;
	}

	if ( count ) {
		count.textContent =
			colours.length +
			' ' +
			( 1 === colours.length ? 'colour' : 'colours' );
	}
	if ( tab ) {
		tab.textContent = colours.length;
	}

	if ( colours.length === 0 ) {
		if ( empty ) {
			empty.style.display = '';
		}
		grid.style.display = 'none';
		return;
	}

	if ( empty ) {
		empty.style.display = 'none';
	}
	grid.style.display = '';
	grid.innerHTML = '';
	colours.forEach( ( c ) => grid.appendChild( buildColourCardEl( c ) ) );
}

// ---------------------------------------------------------------------------
// Colour editor modal
// ---------------------------------------------------------------------------

const colourModal = () => document.getElementById( 'oc-colour-modal' );
const colourPicker = () => document.getElementById( 'oc-colour-picker' );
const swatchLarge = () => document.getElementById( 'oc-colour-swatch-large' );
const nameInput = () => document.getElementById( 'oc_colour_name' );
const hexInput = () => document.getElementById( 'oc_colour_hex' );
const colourError = () => document.getElementById( 'oc-colour-error' );
const colourDeleteBtn = () => document.getElementById( 'oc-colour-delete-btn' );
const colourModalTitle = () =>
	document.getElementById( 'oc-colour-modal-title' );

function openColourModal( id ) {
	editColourId = id || null;
	const colour = id ? colours.find( ( c ) => c.id === id ) : null;

	colourModalTitle().textContent = colour ? 'Edit Colour' : 'Add Colour';

	const hex = colour ? colour.hex : '#4f46e5';
	const name = colour ? colour.name : '';

	colourPicker().value = hex;
	swatchLarge().style.background = hex;
	hexInput().value = hex;
	nameInput().value = name;

	const deleteBtn = colourDeleteBtn();
	if ( deleteBtn ) {
		deleteBtn.style.display = colour ? '' : 'none';
	}

	if ( colourError() ) {
		colourError().style.display = 'none';
		colourError().textContent = '';
	}

	colourModal().hidden = false;
	document.body.style.overflow = 'hidden';
	nameInput().focus();
}

function closeColourModal() {
	colourModal().hidden = true;
	document.body.style.overflow = '';
	editColourId = null;
}

async function saveColour() {
	const name = nameInput().value.trim();
	const hex = hexInput().value.trim();
	const err = colourError();

	if ( ! name || ! hex ) {
		err.textContent = 'Colour name and hex value are required.';
		err.style.display = '';
		return;
	}

	const body = new URLSearchParams( {
		action: 'oc_colour_save',
		nonce: window.ocColourNonce,
		name,
		hex,
		id: editColourId || 0,
	} );

	let json;
	try {
		const res = await fetch( window.ocAjaxUrl, { method: 'POST', body } );
		if ( ! res.ok ) {
			throw new Error( `HTTP ${ res.status }` );
		}
		json = await res.json();
	} catch ( e ) {
		console.warn( '[OC] Colour save failed:', e );
		err.textContent = 'Save failed. Please try again.';
		err.style.display = '';
		return;
	}

	if ( ! json.success ) {
		err.textContent = json.data?.message || 'Save failed.';
		err.style.display = '';
		return;
	}

	const saved = normaliseColour( json.data );

	if ( editColourId ) {
		const idx = colours.findIndex( ( c ) => c.id === editColourId );
		if ( idx !== -1 ) {
			colours[ idx ] = saved;
		}
	} else {
		colours.push( saved );
	}

	updateColourGridUI();
	closeColourModal();
}

async function deleteColourFromModal() {
	if ( ! editColourId ) {
		return;
	}
	if ( ! confirm( 'Delete this colour?' ) ) {
		return;
	}

	const colour = colours.find( ( c ) => c.id === editColourId );
	if ( ! colour ) {
		return;
	}

	// Use the server-generated delete URL (has nonce baked in).
	window.location.href = colour.deleteUrl;
}

function initColourModal() {
	// Add colour button.
	document
		.getElementById( 'oc-add-colour-btn' )
		?.addEventListener( 'click', () => openColourModal( null ) );

	// Close.
	document
		.getElementById( 'oc-colour-modal-close' )
		?.addEventListener( 'click', closeColourModal );
	document
		.getElementById( 'oc-colour-cancel-btn' )
		?.addEventListener( 'click', closeColourModal );

	// Backdrop click.
	colourModal()?.addEventListener( 'click', ( e ) => {
		if ( e.target === colourModal() ) {
			closeColourModal();
		}
	} );

	// Save.
	document
		.getElementById( 'oc-colour-save-btn' )
		?.addEventListener( 'click', saveColour );

	// Delete.
	colourDeleteBtn()?.addEventListener( 'click', deleteColourFromModal );

	// Open from existing cards (initial render).
	document.querySelectorAll( '.oc-colour-card' ).forEach( ( card ) => {
		card.addEventListener( 'click', ( e ) => {
			if ( e.target.closest( 'a' ) ) {
				return;
			}
			openColourModal( Number( card.dataset.colourId ) );
		} );
		card.addEventListener( 'keydown', ( e ) => {
			if ( e.key === 'Enter' || e.key === ' ' ) {
				openColourModal( Number( card.dataset.colourId ) );
			}
		} );
	} );

	// Colour picker ↔ hex text sync.
	colourPicker()?.addEventListener( 'input', () => {
		const hex = colourPicker().value;
		if ( swatchLarge() ) {
			swatchLarge().style.background = hex;
		}
		if ( hexInput() ) {
			hexInput().value = hex;
		}
	} );

	hexInput()?.addEventListener( 'input', () => {
		const raw = hexInput().value.trim();
		if ( /^#[0-9a-fA-F]{6}$/.test( raw ) ) {
			colourPicker().value = raw;
			swatchLarge().style.background = raw;
		}
	} );

	// Click on swatch forwards to colour picker.
	swatchLarge()?.addEventListener( 'click', () => colourPicker()?.click() );
}

// ---------------------------------------------------------------------------
// Colour group grid helpers
// ---------------------------------------------------------------------------

function colourById( id ) {
	return colours.find( ( c ) => c.id === id );
}

function buildGroupCardEl( group ) {
	const card = document.createElement( 'div' );
	card.className = 'oc-group-card oc-colour-group-card';
	card.dataset.groupId = group.id;
	card.dataset.groupName = group.name;
	card.setAttribute( 'role', 'button' );
	card.setAttribute( 'tabindex', '0' );

	const dots = group.colourIds
		.slice( 0, 10 )
		.map( ( cid ) => {
			const c = colourById( cid );
			if ( ! c ) {
				return '';
			}
			return `<span class="oc-colour-dot" style="background:${ h(
				c.hex
			) };" title="${ h( c.name ) }"></span>`;
		} )
		.join( '' );

	const more =
		group.colourIds.length > 10
			? `<span class="oc-group-card-more">+${
					group.colourIds.length - 10
			  }</span>`
			: '';

	const empty =
		group.colourIds.length === 0
			? `<span style="color:var(--oc-gray-400);font-size:12px;">Empty group</span>`
			: '';

	card.innerHTML = `
		<div class="oc-group-card-body">
			<p class="oc-group-card-name">${ h( group.name ) }</p>
			<p class="oc-group-card-count">${ group.colourIds.length } ${
				1 === group.colourIds.length ? 'colour' : 'colours'
			}</p>
			<div class="oc-colour-group-dots">${ dots }${ more }${ empty }</div>
		</div>`;

	card.addEventListener( 'click', () => openGroupModal( group.id ) );
	card.addEventListener( 'keydown', ( e ) => {
		if ( e.key === 'Enter' || e.key === ' ' ) {
			openGroupModal( group.id );
		}
	} );

	return card;
}

function updateGroupGridUI() {
	const grid = document.getElementById( 'oc-colour-group-grid' );
	const empty = document.getElementById( 'oc-colour-groups-empty' );
	const count = document.getElementById( 'oc-colour-groups-count' );
	const tab = document.querySelector(
		'.oc-tab[data-target="oc-tab-colour-groups"] .oc-tab-count'
	);

	if ( ! grid ) {
		return;
	}

	if ( count ) {
		count.textContent =
			groups.length + ' ' + ( 1 === groups.length ? 'group' : 'groups' );
	}
	if ( tab ) {
		tab.textContent = groups.length;
	}

	if ( groups.length === 0 ) {
		if ( empty ) {
			empty.style.display = '';
		}
		grid.style.display = 'none';
		return;
	}

	if ( empty ) {
		empty.style.display = 'none';
	}
	grid.style.display = '';
	grid.innerHTML = '';
	groups.forEach( ( g ) => grid.appendChild( buildGroupCardEl( g ) ) );
}

// ---------------------------------------------------------------------------
// Colour group modal
// ---------------------------------------------------------------------------

const groupModal = () => document.getElementById( 'oc-colour-group-modal' );
const groupNameInput = () =>
	document.getElementById( 'oc-colour-group-name-input' );
const groupPicker = () => document.getElementById( 'oc-colour-group-picker' );
const groupSelCount = () =>
	document.getElementById( 'oc-colour-group-selected-count' );
const groupDeleteBtn = () =>
	document.getElementById( 'oc-colour-group-delete-btn' );

function openGroupModal( id ) {
	editGroupId = id || null;
	const group = id ? groups.find( ( g ) => g.id === id ) : null;

	groupNameInput().value = group ? group.name : '';

	const deleteBtn = groupDeleteBtn();
	if ( deleteBtn ) {
		deleteBtn.style.display = group ? '' : 'none';
	}

	renderColourPicker( group ? group.colourIds : [] );

	groupModal().hidden = false;
	document.body.style.overflow = 'hidden';
	groupNameInput().focus();
}

function closeGroupModal() {
	groupModal().hidden = true;
	document.body.style.overflow = '';
	editGroupId = null;
}

function renderColourPicker( selectedIds ) {
	const picker = groupPicker();
	if ( ! picker ) {
		return;
	}

	picker.innerHTML = '';

	colours.forEach( ( colour ) => {
		const checked = selectedIds.includes( colour.id );

		const item = document.createElement( 'label' );
		item.className = 'oc-group-font-item';
		item.innerHTML = `
			<input type="checkbox" value="${ colour.id }" ${ checked ? 'checked' : '' } />
			<span class="oc-colour-dot" style="background:${ h(
				colour.hex
			) };" title="${ h( colour.name ) }"></span>
			<span class="oc-group-font-info">
				<span class="oc-group-font-info-name">${ h( colour.name ) }</span>
				<span class="oc-group-font-info-meta">${ h( colour.hex.toUpperCase() ) }</span>
			</span>`;

		item.querySelector( 'input' ).addEventListener(
			'change',
			updateGroupSelCount
		);
		picker.appendChild( item );
	} );

	updateGroupSelCount();
}

function updateGroupSelCount() {
	const n = groupPicker()?.querySelectorAll( 'input:checked' ).length ?? 0;
	if ( groupSelCount() ) {
		groupSelCount().textContent = n + ' selected';
	}
}

function selectedColourIds() {
	return [
		...( groupPicker()?.querySelectorAll( 'input:checked' ) || [] ),
	].map( ( cb ) => Number( cb.value ) );
}

async function saveGroup() {
	const name = groupNameInput().value.trim();
	const colourIds = selectedColourIds();

	if ( ! name ) {
		groupNameInput().focus();
		return;
	}

	const action = editGroupId
		? 'oc_colour_group_update'
		: 'oc_colour_group_create';
	const body = new URLSearchParams( {
		action,
		nonce: window.ocColourNonce,
		name,
		id: editGroupId || 0,
	} );
	colourIds.forEach( ( id ) => body.append( 'colour_ids[]', id ) );

	let json;
	try {
		const res = await fetch( window.ocAjaxUrl, { method: 'POST', body } );
		if ( ! res.ok ) {
			throw new Error( `HTTP ${ res.status }` );
		}
		json = await res.json();
	} catch ( e ) {
		console.warn( '[OC] Colour group save failed:', e );
		alert( 'Save failed. Please try again.' );
		return;
	}

	if ( ! json.success ) {
		alert( json.data?.message || 'Save failed.' );
		return;
	}

	const saved = normaliseGroup( json.data );

	if ( editGroupId ) {
		const idx = groups.findIndex( ( g ) => g.id === editGroupId );
		if ( idx !== -1 ) {
			groups[ idx ] = saved;
		}
	} else {
		groups.push( saved );
	}

	updateGroupGridUI();
	closeGroupModal();
}

async function deleteGroup() {
	if ( ! editGroupId ) {
		return;
	}
	if ( ! confirm( 'Delete this colour group?' ) ) {
		return;
	}

	const body = new URLSearchParams( {
		action: 'oc_colour_group_delete',
		nonce: window.ocColourNonce,
		id: editGroupId,
	} );

	let json;
	try {
		const res = await fetch( window.ocAjaxUrl, { method: 'POST', body } );
		if ( ! res.ok ) {
			throw new Error( `HTTP ${ res.status }` );
		}
		json = await res.json();
	} catch ( e ) {
		console.warn( '[OC] Colour group delete failed:', e );
		alert( 'Delete failed. Please try again.' );
		return;
	}

	if ( ! json.success ) {
		alert( json.data?.message || 'Delete failed.' );
		return;
	}

	groups = groups.filter( ( g ) => g.id !== editGroupId );
	updateGroupGridUI();
	closeGroupModal();
}

function initGroupModal() {
	document
		.getElementById( 'oc-create-colour-group-btn' )
		?.addEventListener( 'click', () => openGroupModal( null ) );

	document
		.getElementById( 'oc-colour-group-modal-close' )
		?.addEventListener( 'click', closeGroupModal );
	document
		.getElementById( 'oc-colour-group-cancel-btn' )
		?.addEventListener( 'click', closeGroupModal );

	groupModal()?.addEventListener( 'click', ( e ) => {
		if ( e.target === groupModal() ) {
			closeGroupModal();
		}
	} );

	document
		.getElementById( 'oc-colour-group-save-btn' )
		?.addEventListener( 'click', saveGroup );

	groupDeleteBtn()?.addEventListener( 'click', deleteGroup );

	// Open from server-rendered cards.
	document.querySelectorAll( '.oc-colour-group-card' ).forEach( ( card ) => {
		card.addEventListener( 'click', () =>
			openGroupModal( Number( card.dataset.groupId ) )
		);
		card.addEventListener( 'keydown', ( e ) => {
			if ( e.key === 'Enter' || e.key === ' ' ) {
				openGroupModal( Number( card.dataset.groupId ) );
			}
		} );
	} );
}

// ---------------------------------------------------------------------------
// Boot
// ---------------------------------------------------------------------------

document.addEventListener( 'DOMContentLoaded', () => {
	initTabs();
	initColourModal();
	initGroupModal();
} );
