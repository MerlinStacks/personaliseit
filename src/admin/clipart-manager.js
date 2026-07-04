import ImageTracer from 'imagetracerjs';

/**
 * Clipart Manager admin JS.
 *
 * Handles:
 *  - Tab switching (Clipart / Clipart Groups)
 *  - Upload modal with drag-and-drop (step 1 then step 2 then AJAX)
 *  - Edit modal — rename via AJAX; delete via server redirect link
 *  - Clipart group editor modal (create / update / delete)
 */

/* global ocClipartData, ocClipartGroups, ocClipartNonce, ocAjaxUrl */

// ---------------------------------------------------------------------------
// State
// ---------------------------------------------------------------------------

let clipart = ( window.ocClipartData   || [] ).map( normaliseClipart );
let groups  = ( window.ocClipartGroups || [] ).map( normaliseGroup );

let currentFile   = null;
let editClipartId = null;
let editGroupId   = null;

const TRACE_MAX_SIZE = 1200;

// ---------------------------------------------------------------------------
// Normalisers
// ---------------------------------------------------------------------------

function normaliseClipart( c ) {
	return {
		id:        Number( c.id ),
		name:      c.name     || '',
		fileType:  c.fileType || '',
		canConvert: !! c.canConvert,
		active:    !! c.active,
		url:       c.url       || '',
		toggleUrl: c.toggleUrl || '',
		deleteUrl: c.deleteUrl || '',
	};
}

function normaliseGroup( g ) {
	return {
		id:         Number( g.id ),
		name:       g.name || '',
		clipartIds: ( g.clipartIds || [] ).map( Number ),
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
	const tabs   = document.querySelectorAll( '.oc-tab' );
	const panels = document.querySelectorAll( '.oc-tab-panel' );

	const uploadBtn    = document.getElementById( 'oc-upload-clipart-btn' );
	const createGrpBtn = document.getElementById( 'oc-create-clipart-group-btn' );

	tabs.forEach( tab => {
		tab.addEventListener( 'click', () => {
			tabs.forEach(   t => t.classList.remove( 'oc-tab--active' ) );
			panels.forEach( p => { p.hidden = true; } );

			tab.classList.add( 'oc-tab--active' );
			const target = document.getElementById( tab.dataset.target );
			if ( target ) target.hidden = false;

			const isGroups = tab.dataset.target === 'oc-tab-clipart-groups';
			if ( uploadBtn    ) uploadBtn.style.display    = isGroups ? 'none'        : '';
			if ( createGrpBtn ) createGrpBtn.style.display = isGroups ? 'inline-flex' : 'none';
		} );
	} );
}

// ---------------------------------------------------------------------------
// Clipart card grid
// ---------------------------------------------------------------------------

function buildClipartCardEl( item ) {
	const card = document.createElement( 'div' );
	card.className = 'oc-clipart-card' + ( item.active ? '' : ' oc-clipart-card--inactive' );
	card.dataset.clipartId = item.id;
	card.setAttribute( 'role', 'button' );
	card.setAttribute( 'tabindex', '0' );

	card.innerHTML =
		'<div class="oc-clipart-preview">' +
			'<img src="' + h( item.url ) + '" alt="' + h( item.name ) + '" loading="lazy" />' +
		'</div>' +
		'<div class="oc-clipart-card-body">' +
			'<div class="oc-clipart-card-title-row">' +
				'<p class="oc-clipart-card-name" title="' + h( item.name ) + '">' + h( item.name ) + '</p>' +
				'<span class="oc-badge ' + ( item.active ? 'oc-badge-active' : 'oc-badge-inactive' ) + '">' +
					( item.active ? 'Active' : 'Inactive' ) +
				'</span>' +
			'</div>' +
			'<p class="oc-clipart-type-label">' + h( item.fileType.toUpperCase() ) + '</p>' +
			'<div class="oc-clipart-card-actions">' +
				( item.canConvert ? '<button type="button" class="oc-btn oc-btn-secondary oc-btn-sm" data-oc-convert-clipart="' + item.id + '">Convert to SVG</button>' : '' ) +
				'<a href="' + h( item.toggleUrl ) + '" class="oc-btn oc-btn-secondary oc-btn-sm">' +
					( item.active ? 'Deactivate' : 'Activate' ) +
				'</a>' +
				'<a href="' + h( item.deleteUrl ) + '" onclick="return confirm(\'Delete this clipart?\');" class="oc-btn oc-btn-danger oc-btn-sm">Delete</a>' +
			'</div>' +
		'</div>';

	card.addEventListener( 'click', e => {
		if ( e.target.closest( 'a,button' ) ) return;
		openEditModal( item.id );
	} );
	card.querySelector( '[data-oc-convert-clipart]' )?.addEventListener( 'click', e => {
		e.preventDefault();
		convertClipartToSvg( item.id, e.currentTarget );
	} );
	card.addEventListener( 'keydown', e => {
		if ( e.key === 'Enter' || e.key === ' ' ) openEditModal( item.id );
	} );

	return card;
}

async function convertClipartToSvg( id, button ) {
	const item = clipart.find( c => c.id === Number( id ) );
	if ( ! item ) return;

	const originalText = button?.textContent || 'Convert to SVG';
	if ( button ) {
		button.disabled = true;
		button.textContent = 'Converting...';
	}

	const body = new URLSearchParams( {
		action: 'oc_clipart_convert_svg',
		nonce:  window.ocClipartNonce,
		id:     Number( id ),
	} );

	try {
		let requestBody = body;
		try {
			const svg = await traceUrlToSvg( item.url );
			const fd = new FormData();
			fd.append( 'action', 'oc_clipart_convert_svg' );
			fd.append( 'nonce', window.ocClipartNonce );
			fd.append( 'id', Number( id ) );
			fd.append( 'clipart_file', new Blob( [ svg ], { type: 'image/svg+xml' } ), `${ safeFilename( item.name ) || 'clipart' }.svg` );
			requestBody = fd;
		} catch ( traceErr ) {
			console.warn( '[OC] Browser clipart tracing failed; using server fallback:', traceErr );
		}

		const res = await fetch( window.ocAjaxUrl, { method: 'POST', body: requestBody } );
		if ( ! res.ok ) throw new Error( `HTTP ${ res.status }` );
		const json = await res.json();
		if ( ! json.success ) throw new Error( json.data?.message || 'Conversion failed.' );

		const converted = normaliseClipart( json.data );
		const idx = clipart.findIndex( c => c.id === converted.id );
		if ( idx !== -1 ) {
			clipart[ idx ] = converted;
			updateClipartGridUI();
		}
	} catch ( err ) {
		alert( err?.message || 'Conversion failed. Please try again.' );
		if ( button ) {
			button.disabled = false;
			button.textContent = originalText;
		}
	}
}

async function traceUrlToSvg( url ) {
	const response = await fetch( url, { credentials: 'same-origin', cache: 'no-store' } );
	if ( ! response.ok ) throw new Error( `Could not load clipart (${ response.status }).` );
	return traceBlobToSvg( await response.blob() );
}

async function traceBlobToSvg( blob ) {
	const image = await loadImageFromBlob( blob );
	const scale = Math.min( 1, TRACE_MAX_SIZE / Math.max( image.naturalWidth || image.width, image.naturalHeight || image.height ) );
	const width = Math.max( 1, Math.round( ( image.naturalWidth || image.width ) * scale ) );
	const height = Math.max( 1, Math.round( ( image.naturalHeight || image.height ) * scale ) );
	const canvas = document.createElement( 'canvas' );
	canvas.width = width;
	canvas.height = height;
	const ctx = canvas.getContext( '2d', { willReadFrequently: true } );
	if ( ! ctx ) throw new Error( 'Canvas is unavailable for tracing.' );
	ctx.clearRect( 0, 0, width, height );
	ctx.drawImage( image, 0, 0, width, height );

	const imgData = ctx.getImageData( 0, 0, width, height );
	const data = imgData.data;
	for ( let i = 0; i < data.length; i += 4 ) {
		const r = data[ i ];
		const g = data[ i + 1 ];
		const b = data[ i + 2 ];
		const a = data[ i + 3 ];
		const nearWhite = r >= 245 && g >= 245 && b >= 245 && ( Math.max( r, g, b ) - Math.min( r, g, b ) ) <= 12;
		if ( a < 16 || nearWhite ) {
			data[ i ] = 255;
			data[ i + 1 ] = 255;
			data[ i + 2 ] = 255;
			data[ i + 3 ] = 0;
		} else {
			data[ i ] = 0;
			data[ i + 1 ] = 0;
			data[ i + 2 ] = 0;
			data[ i + 3 ] = 255;
		}
	}

	let svg = ImageTracer.imagedataToSVG( imgData, {
		colorsampling: 0,
		pal: [
			{ r: 0, g: 0, b: 0, a: 255 },
			{ r: 255, g: 255, b: 255, a: 0 },
		],
		numberofcolors: 2,
		pathomit: 4,
		ltres: 1,
		qtres: 1,
		strokewidth: 0,
		roundcoords: 1,
		viewbox: true,
		desc: false,
	} );

	svg = svg
		.replace( /<path[^>]+fill="rgba\(255,255,255,0\)"[^>]*>\s*<\/path>/g, '' )
		.replace( /fill="rgb\(0,0,0\)"/g, 'fill="currentColor"' )
		.replace( /stroke="rgb\(0,0,0\)"/g, 'stroke="currentColor"' )
		.replace( /<svg\b/, '<svg color="#000000" data-oc-traced="browser"' );

	if ( ! /<path\b/.test( svg ) ) {
		throw new Error( 'No traceable artwork found after background removal.' );
	}

	return svg;
}

function loadImageFromBlob( blob ) {
	return new Promise( ( resolve, reject ) => {
		const url = URL.createObjectURL( blob );
		const image = new Image();
		image.onload = () => {
			URL.revokeObjectURL( url );
			resolve( image );
		};
		image.onerror = () => {
			URL.revokeObjectURL( url );
			reject( new Error( 'Could not load image for tracing.' ) );
		};
		image.src = url;
	} );
}

function safeFilename( value ) {
	return String( value || '' ).toLowerCase().replace( /[^a-z0-9_-]+/g, '-' ).replace( /^-+|-+$/g, '' );
}

function updateClipartGridUI() {
	const grid  = document.getElementById( 'oc-clipart-grid' );
	const empty = document.getElementById( 'oc-clipart-empty' );
	const count = document.getElementById( 'oc-clipart-count' );
	const tab   = document.querySelector( '.oc-tab[data-target="oc-tab-clipart"] .oc-tab-count' );

	if ( ! grid ) return;

	if ( count ) count.textContent = clipart.length + ' ' + ( 1 === clipart.length ? 'item' : 'items' );
	if ( tab )   tab.textContent   = clipart.length;

	if ( clipart.length === 0 ) {
		if ( empty ) empty.style.display = '';
		grid.style.display = 'none';
		return;
	}

	if ( empty ) empty.style.display = 'none';
	grid.style.display = '';
	grid.innerHTML     = '';
	clipart.forEach( c => grid.appendChild( buildClipartCardEl( c ) ) );
}

// ---------------------------------------------------------------------------
// Upload modal
// ---------------------------------------------------------------------------

function initUploadModal() {
	const modal      = document.getElementById( 'oc-upload-clipart-modal' );
	const openBtn    = document.getElementById( 'oc-upload-clipart-btn' );
	const closeBtn   = document.getElementById( 'oc-clipart-upload-modal-close' );
	const dropZone   = document.getElementById( 'oc-clipart-drop-zone' );
	const fileInput  = document.getElementById( 'oc_clipart_file' );
	const step1      = document.getElementById( 'oc-clipart-upload-step-1' );
	const step2      = document.getElementById( 'oc-clipart-upload-step-2' );
	const footer     = document.getElementById( 'oc-clipart-upload-modal-footer' );
	const previewImg = document.getElementById( 'oc-clipart-upload-preview-img' );
	const nameInput  = document.getElementById( 'oc_clipart_upload_name' );
	const errDiv     = document.getElementById( 'oc-clipart-upload-error' );
	const backBtn    = document.getElementById( 'oc-clipart-upload-back-btn' );
	const submitBtn  = document.getElementById( 'oc-clipart-upload-submit-btn' );

	if ( ! modal ) return;

	function openModal() {
		resetToStep1();
		modal.hidden = false;
		document.body.style.overflow = 'hidden';
	}

	function closeModal() {
		modal.hidden = true;
		document.body.style.overflow = '';
		currentFile = null;
	}

	function resetToStep1() {
		if ( step1 )  step1.style.display  = '';
		if ( step2 )  step2.style.display  = 'none';
		if ( footer ) footer.style.display = 'none';
		if ( errDiv ) { errDiv.style.display = 'none'; errDiv.textContent = ''; }
		currentFile = null;
	}

	function showStep2( file ) {
		currentFile = file;

		if ( previewImg ) {
			const url = URL.createObjectURL( file );
			previewImg.src = url;
			previewImg.onload = () => URL.revokeObjectURL( url );
		}

		if ( nameInput && ! nameInput.value ) {
			nameInput.value = file.name
				.replace( /\.[^.]+$/, '' )
				.replace( /[-_]+/g, ' ' )
				.replace( /\b\w/g, l => l.toUpperCase() );
		}

		if ( step1 )  step1.style.display  = 'none';
		if ( step2 )  step2.style.display  = '';
		if ( footer ) footer.style.display = '';
		if ( errDiv ) errDiv.style.display  = 'none';
	}

	function handleFile( file ) {
		if ( ! /\.(svg|png|jpe?g|webp|gif)$/i.test( file.name ) ) {
			alert( 'File type not supported. Use SVG, PNG, JPG, WEBP, or GIF.' );
			return;
		}
		showStep2( file );
	}

	dropZone?.addEventListener( 'dragover', e => {
		e.preventDefault();
		dropZone.classList.add( 'oc-drop-zone--over' );
	} );
	dropZone?.addEventListener( 'dragleave', () => dropZone.classList.remove( 'oc-drop-zone--over' ) );
	dropZone?.addEventListener( 'drop', e => {
		e.preventDefault();
		dropZone.classList.remove( 'oc-drop-zone--over' );
		const file = e.dataTransfer.files[0];
		if ( file ) handleFile( file );
	} );

	dropZone?.addEventListener( 'click', () => fileInput?.click() );
	fileInput?.addEventListener( 'change', () => {
		if ( fileInput.files[0] ) handleFile( fileInput.files[0] );
	} );

	openBtn?.addEventListener(  'click', openModal );
	closeBtn?.addEventListener( 'click', closeModal );
	backBtn?.addEventListener(  'click', () => {
		if ( nameInput ) nameInput.value = '';
		resetToStep1();
	} );
	modal?.addEventListener( 'click', e => {
		if ( e.target === modal ) closeModal();
	} );

	submitBtn?.addEventListener( 'click', async () => {
		if ( ! currentFile ) return;

		const name = nameInput?.value.trim() || '';
		if ( ! name ) {
			if ( errDiv ) { errDiv.textContent = 'Name is required.'; errDiv.style.display = ''; }
			return;
		}

		const origLabel      = submitBtn.textContent;
		submitBtn.disabled   = true;
		submitBtn.textContent = 'Uploading\u2026';

		let uploadFile = currentFile;
		if ( ! /\.svg$/i.test( currentFile.name ) ) {
			try {
				const svg = await traceBlobToSvg( currentFile );
				uploadFile = new File( [ svg ], `${ safeFilename( name ) || 'clipart' }.svg`, { type: 'image/svg+xml' } );
				submitBtn.textContent = 'Uploading traced SVG...';
			} catch ( traceErr ) {
				console.warn( '[OC] Browser clipart tracing failed; uploading for server fallback:', traceErr );
			}
		}

		const fd = new FormData();
		fd.append( 'action',       'oc_clipart_upload' );
		fd.append( 'nonce',        window.ocClipartNonce );
		fd.append( 'name',         name );
		fd.append( 'clipart_file', uploadFile );

		try {
			const res  = await fetch( window.ocAjaxUrl, { method: 'POST', body: fd } );
			if ( ! res.ok ) throw new Error( `HTTP ${ res.status }` );
			const text = await res.text();
			let json;
			try {
				json = JSON.parse( text );
			} catch ( err ) {
				throw new Error( text || 'Invalid server response.' );
			}

			if ( ! json.success ) {
				if ( errDiv ) { errDiv.textContent = ( json.data && json.data.message ) || 'Upload failed.'; errDiv.style.display = ''; }
				return;
			}

			clipart.push( normaliseClipart( json.data ) );
			updateClipartGridUI();
			if ( nameInput ) nameInput.value = '';
			closeModal();
		} catch ( err ) {
			if ( errDiv ) { errDiv.textContent = err?.message || 'Upload failed. Please try again.'; errDiv.style.display = ''; }
		} finally {
			submitBtn.disabled   = false;
			submitBtn.textContent = origLabel;
		}
	} );
}

// ---------------------------------------------------------------------------
// Edit modal (rename)
// ---------------------------------------------------------------------------

function openEditModal( id ) {
	editClipartId = id;
	const item = clipart.find( c => c.id === id );
	if ( ! item ) return;

	const modal   = document.getElementById( 'oc-clipart-modal' );
	const nameInp = document.getElementById( 'oc_clipart_name' );
	const preview = document.getElementById( 'oc-clipart-modal-preview-img' );
	const errDiv  = document.getElementById( 'oc-clipart-error' );
	const delBtn  = document.getElementById( 'oc-clipart-delete-btn' );

	if ( nameInp ) nameInp.value = item.name;
	if ( preview ) { preview.src = item.url; preview.alt = item.name; }
	if ( errDiv )  { errDiv.style.display = 'none'; errDiv.textContent = ''; }
	if ( delBtn )  delBtn.style.display = '';

	if ( modal ) {
		modal.hidden = false;
		document.body.style.overflow = 'hidden';
		if ( nameInp ) nameInp.focus();
	}
}

function initEditModal() {
	const modal     = document.getElementById( 'oc-clipart-modal' );
	const closeBtn  = document.getElementById( 'oc-clipart-modal-close' );
	const cancelBtn = document.getElementById( 'oc-clipart-cancel-btn' );
	const saveBtn   = document.getElementById( 'oc-clipart-save-btn' );
	const deleteBtn = document.getElementById( 'oc-clipart-delete-btn' );
	const nameInput = document.getElementById( 'oc_clipart_name' );
	const errDiv    = document.getElementById( 'oc-clipart-error' );

	if ( ! modal ) return;

	function closeModal() {
		modal.hidden = true;
		document.body.style.overflow = '';
		editClipartId = null;
	}

	closeBtn?.addEventListener(  'click', closeModal );
	cancelBtn?.addEventListener( 'click', closeModal );
	modal?.addEventListener( 'click', e => {
		if ( e.target === modal ) closeModal();
	} );

	deleteBtn?.addEventListener( 'click', () => {
		if ( ! editClipartId ) return;
		const item = clipart.find( c => c.id === editClipartId );
		if ( item && confirm( 'Delete this clipart?' ) ) {
			window.location.href = item.deleteUrl;
		}
	} );

	saveBtn?.addEventListener( 'click', async () => {
		if ( ! editClipartId ) return;
		const name = ( nameInput && nameInput.value.trim() ) || '';
		if ( ! name ) { if ( nameInput ) nameInput.focus(); return; }

		const body = new URLSearchParams( {
			action: 'oc_clipart_rename',
			nonce:  window.ocClipartNonce,
			id:     editClipartId,
			name,
		} );

		try {
			const res = await fetch( window.ocAjaxUrl, { method: 'POST', body } );
			if ( ! res.ok ) throw new Error( `HTTP ${ res.status }` );
			const json = await res.json();

			if ( ! json.success ) {
				if ( errDiv ) { errDiv.textContent = ( json.data && json.data.message ) || 'Save failed.'; errDiv.style.display = ''; }
				return;
			}

			const idx = clipart.findIndex( c => c.id === editClipartId );
			if ( idx !== -1 ) {
				clipart[ idx ].name = name;
				updateClipartGridUI();
			}
			closeModal();
		} catch ( e ) {
			console.warn( '[OC] Clipart rename failed:', e );
			if ( errDiv ) {
				errDiv.textContent = 'Save failed. Please try again.';
				errDiv.style.display = '';
			}
		}
	} );

	// Wire up server-rendered cards.
	document.querySelectorAll( '.oc-clipart-card' ).forEach( card => {
		card.addEventListener( 'click', e => {
			if ( e.target.closest( 'a,button' ) ) return;
			openEditModal( Number( card.dataset.clipartId ) );
		} );
		card.querySelector( '[data-oc-convert-clipart]' )?.addEventListener( 'click', e => {
			e.preventDefault();
			convertClipartToSvg( Number( card.dataset.clipartId ), e.currentTarget );
		} );
		card.addEventListener( 'keydown', e => {
			if ( e.key === 'Enter' || e.key === ' ' ) openEditModal( Number( card.dataset.clipartId ) );
		} );
	} );
}

// ---------------------------------------------------------------------------
// Group card grid
// ---------------------------------------------------------------------------

function clipartById( id ) {
	return clipart.find( c => c.id === id );
}

function buildGroupCardEl( group ) {
	const card = document.createElement( 'div' );
	card.className = 'oc-group-card oc-clipart-group-card';
	card.dataset.groupId   = group.id;
	card.dataset.groupName = group.name;
	card.setAttribute( 'role', 'button' );
	card.setAttribute( 'tabindex', '0' );

	let thumbsHtml = group.clipartIds.slice( 0, 6 ).map( cid => {
		const c = clipartById( cid );
		if ( ! c ) return '';
		return '<div class="oc-clipart-thumb" title="' + h( c.name ) + '"><img src="' + h( c.url ) + '" alt="' + h( c.name ) + '" /></div>';
	} ).join( '' );

	if ( group.clipartIds.length > 6 ) {
		thumbsHtml += '<span class="oc-group-card-more">+' + ( group.clipartIds.length - 6 ) + '</span>';
	}
	if ( group.clipartIds.length === 0 ) {
		thumbsHtml = '<span style="color:var(--oc-gray-400);font-size:12px;">Empty group</span>';
	}

	card.innerHTML =
		'<div class="oc-group-card-body">' +
			'<p class="oc-group-card-name">' + h( group.name ) + '</p>' +
			'<p class="oc-group-card-count">' + group.clipartIds.length + ' ' + ( 1 === group.clipartIds.length ? 'item' : 'items' ) + '</p>' +
			'<div class="oc-clipart-group-thumbs">' + thumbsHtml + '</div>' +
		'</div>';

	card.addEventListener( 'click', () => openGroupModal( group.id ) );
	card.addEventListener( 'keydown', e => {
		if ( e.key === 'Enter' || e.key === ' ' ) openGroupModal( group.id );
	} );

	return card;
}

function updateGroupGridUI() {
	const grid  = document.getElementById( 'oc-clipart-group-grid' );
	const empty = document.getElementById( 'oc-clipart-groups-empty' );
	const count = document.getElementById( 'oc-clipart-groups-count' );
	const tab   = document.querySelector( '.oc-tab[data-target="oc-tab-clipart-groups"] .oc-tab-count' );

	if ( ! grid ) return;

	if ( count ) count.textContent = groups.length + ' ' + ( 1 === groups.length ? 'group' : 'groups' );
	if ( tab )   tab.textContent   = groups.length;

	if ( groups.length === 0 ) {
		if ( empty ) empty.style.display = '';
		grid.style.display = 'none';
		return;
	}

	if ( empty ) empty.style.display = 'none';
	grid.style.display = '';
	grid.innerHTML     = '';
	groups.forEach( g => grid.appendChild( buildGroupCardEl( g ) ) );
}

// ---------------------------------------------------------------------------
// Group modal
// ---------------------------------------------------------------------------

const groupModal     = () => document.getElementById( 'oc-clipart-group-modal' );
const groupNameInput = () => document.getElementById( 'oc-clipart-group-name-input' );
const groupPicker    = () => document.getElementById( 'oc-clipart-group-picker' );
const groupSelCount  = () => document.getElementById( 'oc-clipart-group-selected-count' );
const groupDeleteBtn = () => document.getElementById( 'oc-clipart-group-delete-btn' );

function openGroupModal( id ) {
	editGroupId = id || null;
	const group = id ? groups.find( g => g.id === id ) : null;

	groupNameInput().value = group ? group.name : '';

	const deleteBtn = groupDeleteBtn();
	if ( deleteBtn ) deleteBtn.style.display = group ? '' : 'none';

	renderClipartPicker( group ? group.clipartIds : [] );

	groupModal().hidden = false;
	document.body.style.overflow = 'hidden';
	groupNameInput().focus();
}

function closeGroupModal() {
	groupModal().hidden = true;
	document.body.style.overflow = '';
	editGroupId = null;
}

function renderClipartPicker( selectedIds ) {
	const picker = groupPicker();
	if ( ! picker ) return;
	picker.innerHTML = '';

	clipart.forEach( item => {
		const checked = selectedIds.includes( item.id );
		const label   = document.createElement( 'label' );
		label.className = 'oc-group-font-item';
		label.innerHTML =
			'<input type="checkbox" value="' + item.id + '"' + ( checked ? ' checked' : '' ) + ' />' +
			'<div class="oc-clipart-thumb oc-clipart-picker-thumb" title="' + h( item.name ) + '">' +
				'<img src="' + h( item.url ) + '" alt="' + h( item.name ) + '" />' +
			'</div>' +
			'<span class="oc-group-font-info">' +
				'<span class="oc-group-font-info-name">' + h( item.name ) + '</span>' +
				'<span class="oc-group-font-info-meta">' + h( item.fileType.toUpperCase() ) + '</span>' +
			'</span>';
		label.querySelector( 'input' ).addEventListener( 'change', updateGroupSelCount );
		picker.appendChild( label );
	} );

	updateGroupSelCount();
}

function updateGroupSelCount() {
	const n = groupPicker() ? groupPicker().querySelectorAll( 'input:checked' ).length : 0;
	if ( groupSelCount() ) groupSelCount().textContent = n + ' selected';
}

function selectedClipartIds() {
	const picker = groupPicker();
	if ( ! picker ) return [];
	return Array.from( picker.querySelectorAll( 'input:checked' ) ).map( cb => Number( cb.value ) );
}

async function saveGroup() {
	const name       = groupNameInput().value.trim();
	const clipartIds = selectedClipartIds();

	if ( ! name ) { groupNameInput().focus(); return; }

	const action = editGroupId ? 'oc_clipart_group_update' : 'oc_clipart_group_create';
	const body   = new URLSearchParams( {
		action,
		nonce:  window.ocClipartNonce,
		name,
		id:     editGroupId || 0,
	} );
	clipartIds.forEach( id => body.append( 'clipart_ids[]', id ) );

	let json;
	try {
		const res = await fetch( window.ocAjaxUrl, { method: 'POST', body } );
		if ( ! res.ok ) throw new Error( `HTTP ${ res.status }` );
		json = await res.json();
	} catch ( e ) {
		console.warn( '[OC] Clipart group save failed:', e );
		alert( 'Save failed. Please try again.' );
		return;
	}
	if ( ! json.success ) {
		alert( json.data?.message || 'Save failed.' );
		return;
	}

	const saved = normaliseGroup( json.data );

	if ( editGroupId ) {
		const idx = groups.findIndex( g => g.id === editGroupId );
		if ( idx !== -1 ) groups[ idx ] = saved;
	} else {
		groups.push( saved );
	}

	updateGroupGridUI();
	closeGroupModal();
}

async function deleteGroup() {
	if ( ! editGroupId ) return;
	if ( ! confirm( 'Delete this clipart group?' ) ) return;

	const body = new URLSearchParams( {
		action: 'oc_clipart_group_delete',
		nonce:  window.ocClipartNonce,
		id:     editGroupId,
	} );

	let json;
	try {
		const res = await fetch( window.ocAjaxUrl, { method: 'POST', body } );
		if ( ! res.ok ) throw new Error( `HTTP ${ res.status }` );
		json = await res.json();
	} catch ( e ) {
		console.warn( '[OC] Clipart group delete failed:', e );
		alert( 'Delete failed. Please try again.' );
		return;
	}
	if ( ! json.success ) {
		alert( json.data?.message || 'Delete failed.' );
		return;
	}

	groups = groups.filter( g => g.id !== editGroupId );
	updateGroupGridUI();
	closeGroupModal();
}

function initGroupModal() {
	document.getElementById( 'oc-create-clipart-group-btn' )
		?.addEventListener( 'click', () => openGroupModal( null ) );

	document.getElementById( 'oc-clipart-group-modal-close' )
		?.addEventListener( 'click', closeGroupModal );
	document.getElementById( 'oc-clipart-group-cancel-btn' )
		?.addEventListener( 'click', closeGroupModal );

	groupModal()?.addEventListener( 'click', e => {
		if ( e.target === groupModal() ) closeGroupModal();
	} );

	document.getElementById( 'oc-clipart-group-save-btn' )
		?.addEventListener( 'click', saveGroup );

	groupDeleteBtn()?.addEventListener( 'click', deleteGroup );

	document.querySelectorAll( '.oc-clipart-group-card' ).forEach( card => {
		card.addEventListener( 'click', () => openGroupModal( Number( card.dataset.groupId ) ) );
		card.addEventListener( 'keydown', e => {
			if ( e.key === 'Enter' || e.key === ' ' )
				openGroupModal( Number( card.dataset.groupId ) );
		} );
	} );
}

// ---------------------------------------------------------------------------
// Boot
// ---------------------------------------------------------------------------

document.addEventListener( 'DOMContentLoaded', () => {
	initTabs();
	initUploadModal();
	initEditModal();
	initGroupModal();
} );
