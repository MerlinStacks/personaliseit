/**
 * Admin — Font Manager JS.
 *
 * Features:
 * - Upload modal with drag-and-drop + file browse
 * - Auto-detects font family name, weight and style from TTF/OTF name table
 * - AJAX upload (no page reload)
 * - Card click → family detail panel with rename (AJAX) and variant list
 * - "Add weight / style" from detail panel pre-fills the upload modal
 */

( function () {
	'use strict';

	// ── State ──────────────────────────────────────────────────────────────────
	let fonts       = window.ocFontsData || [];
	const nonce     = window.ocFontNonce || '';
	const ajaxUrl   = window.ocAjaxUrl   || '';

	let currentFile       = null;
	let currentFontFace   = null;
	let detailFontName    = null; // Family name currently shown in detail panel
	let nameLocked        = false; // True when opened from "Add weight" flow

	const PREVIEW_FAMILY  = 'oc-admin-upload-preview';

	// ── DOM refs ───────────────────────────────────────────────────────────────
	const uploadModal     = document.getElementById( 'oc-upload-modal' );
	const uploadFontBtn   = document.getElementById( 'oc-upload-font-btn' );
	const modalCloseBtn   = document.getElementById( 'oc-modal-close-btn' );
	const dropZone        = document.getElementById( 'oc-drop-zone' );
	const fileInput       = document.getElementById( 'oc_font_file' );
	const step1           = document.getElementById( 'oc-upload-step-1' );
	const step2           = document.getElementById( 'oc-upload-step-2' );
	const modalFooter     = document.getElementById( 'oc-modal-footer-step2' );
	const backBtn         = document.getElementById( 'oc-upload-back-btn' );
	const submitBtn       = document.getElementById( 'oc-upload-submit-btn' );
	const previewText     = document.getElementById( 'oc-upload-preview-text' );
	const nameInput       = document.getElementById( 'oc_font_name' );
	const weightSel       = document.getElementById( 'oc_font_weight' );
	const styleSel        = document.getElementById( 'oc_font_style' );
	const embroCheck      = document.getElementById( 'oc_font_embroidery' );
	const uploadError     = document.getElementById( 'oc-upload-error' );
	const fontGrid        = document.getElementById( 'oc-font-grid' );
	const fontsEmpty      = document.getElementById( 'oc-fonts-empty' );
	const fontsCount      = document.getElementById( 'oc-fonts-count' );
	const detailPanel     = document.getElementById( 'oc-font-detail' );
	const detailNameInput = document.getElementById( 'oc-detail-name' );
	const detailSaveBtn   = document.getElementById( 'oc-detail-save-name' );
	const detailCloseBtn  = document.getElementById( 'oc-detail-close' );
	const addWeightBtn    = document.getElementById( 'oc-add-weight-btn' );
	const detailVariants  = document.getElementById( 'oc-detail-variants' );

	if ( ! uploadModal ) return; // Safety — not on the fonts page.

	// ── TTF / OTF name-table parser ────────────────────────────────────────────

	/**
	 * Parse the 'name' table from a raw TTF/OTF ArrayBuffer.
	 * Returns an object keyed by nameID, or null if the file is not a parseable sfnt.
	 */
	function parseFontNames( buffer ) {
		const view = new DataView( buffer );
		if ( buffer.byteLength < 12 ) return null;

		const sig = view.getUint32( 0 );
		// Valid sfnt signatures: TrueType (0x00010000), 'true' (0x74727565), CFF/OTF ('OTTO' 0x4f54544f)
		if ( sig !== 0x00010000 && sig !== 0x74727565 && sig !== 0x4f54544f ) return null;

		const numTables = view.getUint16( 4 );
		let nameOffset  = 0;

		for ( let i = 0; i < numTables; i++ ) {
			const base = 12 + i * 16;
			const tag  = String.fromCharCode(
				view.getUint8( base ), view.getUint8( base + 1 ),
				view.getUint8( base + 2 ), view.getUint8( base + 3 )
			);
			if ( tag === 'name' ) {
				nameOffset = view.getUint32( base + 8 );
				break;
			}
		}
		if ( ! nameOffset ) return null;

		const count     = view.getUint16( nameOffset + 2 );
		const strBase   = nameOffset + view.getUint16( nameOffset + 4 );
		const names     = {};

		for ( let i = 0; i < count; i++ ) {
			const r          = nameOffset + 6 + i * 12;
			const platformID = view.getUint16( r );
			const encodingID = view.getUint16( r + 2 );
			const langID     = view.getUint16( r + 4 );
			const nameID     = view.getUint16( r + 6 );
			const length     = view.getUint16( r + 8 );
			const offset     = view.getUint16( r + 10 );

			if ( nameID > 17 ) continue; // Only standard IDs

			const isWin = platformID === 3 && encodingID === 1 && langID === 0x0409;
			const isMac = platformID === 1 && encodingID === 0 && langID === 0;
			if ( ! isWin && ! isMac ) continue;
			if ( names[ nameID ] && ! isWin ) continue; // Prefer Windows records

			let str       = '';
			const start   = strBase + offset;
			if ( isWin ) {
				for ( let j = 0; j < length; j += 2 ) str += String.fromCharCode( view.getUint16( start + j ) );
			} else {
				for ( let j = 0; j < length; j++ ) str += String.fromCharCode( view.getUint8( start + j ) );
			}
			names[ nameID ] = str;
		}
		return names;
	}

	/**
	 * Map an sfnt subfamily string (e.g. "Bold Italic") to CSS weight + style.
	 */
	function subfamilyToProps( subfamily ) {
		const s = ( subfamily || 'Regular' ).toLowerCase();
		let weight = 'normal';

		if      ( s.includes( 'thin' ) || s.includes( 'hairline' ) )                                weight = '100';
		else if ( s.includes( 'extralight' ) || s.includes( 'extra light' ) || s.includes( 'ultralight' ) || s.includes( 'ultra light' ) ) weight = '200';
		else if ( s.includes( 'light' ) )                                                            weight = '300';
		else if ( s.includes( 'semibold' ) || s.includes( 'semi bold' ) || s.includes( 'demibold' ) ) weight = '600';
		else if ( s.includes( 'extrabold' ) || s.includes( 'extra bold' ) || s.includes( 'ultrabold' ) || s.includes( 'ultra bold' ) ) weight = '800';
		else if ( s.includes( 'black' ) || s.includes( 'heavy' ) )                                  weight = '900';
		else if ( s.includes( 'bold' ) )                                                             weight = 'bold';
		else if ( s.includes( 'medium' ) )                                                           weight = '500';

		const style = ( s.includes( 'italic' ) || s.includes( 'oblique' ) ) ? 'italic' : 'normal';
		return { weight, style };
	}

	// ── Upload modal ───────────────────────────────────────────────────────────

	function openUploadModal( prefillName ) {
		nameLocked = !! prefillName;
		resetModal();
		if ( prefillName ) {
			nameInput.value    = prefillName;
			nameInput.readOnly = true;
			nameInput.style.opacity = '.65';
		}
		uploadModal.hidden = false;
		document.body.style.overflow = 'hidden';
	}

	function closeUploadModal() {
		uploadModal.hidden = true;
		document.body.style.overflow = '';
		resetModal();
	}

	function resetModal() {
		currentFile     = null;
		currentFontFace = null;
		step1.style.display   = '';
		step2.style.display   = 'none';
		modalFooter.style.display = 'none';
		uploadError.style.display = 'none';
		uploadError.textContent   = '';
		nameInput.value    = '';
		nameInput.readOnly = false;
		nameInput.style.opacity = '';
		weightSel.value  = 'normal';
		styleSel.value   = 'normal';
		if ( embroCheck ) embroCheck.checked = false;
		previewText.textContent   = 'AaBbCc 123';
		previewText.style.fontFamily = '';
		previewText.style.fontWeight = '';
		previewText.style.fontStyle  = '';
		submitBtn.disabled    = false;
		submitBtn.textContent = submitBtn.dataset.label || submitBtn.textContent;
		dropZone.classList.remove( 'oc-drop-over' );
		fileInput.value = '';
	}

	function showStep2() {
		step1.style.display   = 'none';
		step2.style.display   = '';
		modalFooter.style.display = 'none'; // Show after preview
		modalFooter.style.display = '';
	}

	// ── File handling ──────────────────────────────────────────────────────────

	async function handleFileSelected( file ) {
		if ( ! file ) return;
		currentFile = file;

		let buffer;
		let names;
		try {
			buffer = await file.arrayBuffer();
			names  = parseFontNames( buffer );
		} catch ( e ) {
			console.warn( '[OC] Font file read failed:', e );
			currentFile = null;
			uploadError.textContent = 'Could not read that font file. Please try another.';
			uploadError.style.display = '';
			return;
		}

		if ( names && ! nameLocked ) {
			const family    = names[ 16 ] || names[ 1 ] || filenameToTitle( file.name );
			const subfamily = names[ 17 ] || names[ 2 ] || 'Regular';
			const { weight, style } = subfamilyToProps( subfamily );
			nameInput.value  = family;
			weightSel.value  = weight;
			styleSel.value   = style;
		} else if ( ! nameLocked && ! nameInput.value.trim() ) {
			nameInput.value = filenameToTitle( file.name );
		}

		// Live preview via FontFace API.
		try {
			// Remove any previous upload preview font.
			document.fonts.forEach( function ( f ) {
				if ( f.family === PREVIEW_FAMILY ) document.fonts.delete( f );
			} );
			const ff = new FontFace( PREVIEW_FAMILY, buffer, {
				weight: weightSel.value,
				style:  styleSel.value,
			} );
			await ff.load();
			document.fonts.add( ff );
			currentFontFace = ff;
			applyPreviewStyle();
		} catch ( e ) {
			console.warn( '[OC] Font preview failed:', e );
			previewText.style.fontFamily = '';
			uploadError.textContent = 'Preview unavailable for this font. You can still upload it.';
			uploadError.style.display = '';
		}

		showStep2();
	}

	function applyPreviewStyle() {
		previewText.style.fontFamily = `"${PREVIEW_FAMILY}", sans-serif`;
		previewText.style.fontWeight = weightSel.value;
		previewText.style.fontStyle  = styleSel.value;
	}

	// ── AJAX upload ────────────────────────────────────────────────────────────

	async function submitUpload() {
		if ( ! currentFile ) return;
		if ( ! nameInput.value.trim() ) {
			nameInput.focus();
			return;
		}

		const label = submitBtn.textContent;
		submitBtn.disabled    = true;
		submitBtn.textContent = 'Uploading…';
		uploadError.style.display = 'none';

		const fd = new FormData();
		fd.append( 'action',           'oc_font_upload' );
		fd.append( 'nonce',            nonce );
		fd.append( 'oc_font_file',     currentFile, currentFile.name );
		fd.append( 'oc_font_name',     nameInput.value.trim() );
		fd.append( 'oc_font_weight',   weightSel.value );
		fd.append( 'oc_font_style',    styleSel.value );
		if ( embroCheck && embroCheck.checked ) fd.append( 'oc_font_embroidery', '1' );

		try {
			const res  = await fetch( ajaxUrl, { method: 'POST', body: fd } );
			if ( ! res.ok ) throw new Error( `HTTP ${ res.status }` );
			const text = await res.text();
			let json;
			try {
				json = JSON.parse( text );
			} catch ( err ) {
				throw new Error( text || 'Invalid server response.' );
			}

			if ( ! json.success ) {
				showUploadError( json.data?.message || 'Upload failed.' );
				return;
			}

			const font = json.data;
			fonts.push( font );
			addCardToGrid( font );
			updateFontsCount();
			closeUploadModal();

			// If detail panel is open for this family, refresh it.
			if ( detailFontName === font.name ) {
				renderDetailVariants( font.name );
			}

		} catch ( err ) {
			showUploadError( err?.message || 'Network error — please try again.' );
		} finally {
			submitBtn.disabled    = false;
			submitBtn.textContent = label;
		}
	}

	function showUploadError( msg ) {
		uploadError.textContent   = msg;
		uploadError.style.display = '';
	}

	// ── Card rendering ─────────────────────────────────────────────────────────

	function injectFontFace( font ) {
		const styleId = `oc-ff-${font.id}`;
		if ( document.getElementById( styleId ) ) return;
		const s = document.createElement( 'style' );
		s.id = styleId;
		s.textContent = `@font-face { font-family: 'oc-preview-${font.id}'; src: url('${font.url}'); font-weight: ${font.weight}; font-style: ${font.style}; }`;
		document.head.appendChild( s );
	}

	function buildCardEl( font ) {
		injectFontFace( font );
		const family = `oc-preview-${font.id}`;
		const el     = document.createElement( 'div' );

		el.className     = 'oc-font-card' + ( font.active ? '' : ' oc-font-card--inactive' );
		el.dataset.fontId   = font.id;
		el.dataset.fontName = font.name;
		el.setAttribute( 'role', 'button' );
		el.setAttribute( 'tabindex', '0' );

		el.innerHTML = `
			<div class="oc-font-preview">
				<span class="oc-font-preview-text" style="font-family:'${family}';font-weight:${font.weight};font-style:${font.style};">AaBbCc 123</span>
			</div>
			<div class="oc-font-card-body">
				<div class="oc-font-card-title-row">
					<p class="oc-font-card-name" title="${h( font.name )}">${h( font.name )}</p>
					<span class="oc-badge ${font.active ? 'oc-badge-active' : 'oc-badge-inactive'}">${font.active ? 'Active' : 'Inactive'}</span>
				</div>
				<div class="oc-font-card-meta">
					<span class="oc-badge oc-badge-format">${h( font.ext )}</span>
					<span class="oc-code">${h( font.weight )}</span>
					${font.style === 'italic' ? '<span class="oc-badge oc-badge-inactive">Italic</span>' : ''}
					${font.embroidery_suitable ? '<span class="oc-badge oc-badge-active">Embroidery</span>' : ''}
				</div>
				<div class="oc-font-card-actions">
					<a href="${font.toggleUrl}" class="oc-btn oc-btn-secondary oc-btn-sm">${font.active ? 'Deactivate' : 'Activate'}</a>
					<a href="${font.deleteUrl}" onclick="return confirm('Delete this font?');" class="oc-btn oc-btn-danger oc-btn-sm">Delete</a>
				</div>
			</div>
		`;
		return el;
	}

	function addCardToGrid( font ) {
		if ( fontsEmpty ) fontsEmpty.style.display = 'none';
		fontGrid.style.display = '';
		const card = buildCardEl( font );
		fontGrid.appendChild( card );
		bindCardClick( card );
	}

	function updateFontsCount() {
		if ( ! fontsCount ) return;
		const n = fonts.length;
		fontsCount.textContent = `${n} ${n === 1 ? 'font' : 'fonts'}`;
	}

	// ── Detail panel ───────────────────────────────────────────────────────────

	function openDetailPanel( fontId, familyName ) {
		// Mark the active card.
		document.querySelectorAll( '.oc-font-card--active-detail' )
			.forEach( c => c.classList.remove( 'oc-font-card--active-detail' ) );
		const activeCard = document.querySelector( `.oc-font-card[data-font-id="${fontId}"]` );
		if ( activeCard ) activeCard.classList.add( 'oc-font-card--active-detail' );

		detailFontName         = familyName;
		detailNameInput.value  = familyName;
		renderDetailVariants( familyName );
		detailPanel.hidden = false;
		document.body.style.overflow = 'hidden';
	}

	function closeDetailPanel() {
		document.querySelectorAll( '.oc-font-card--active-detail' )
			.forEach( c => c.classList.remove( 'oc-font-card--active-detail' ) );
		detailPanel.hidden = true;
		document.body.style.overflow = '';
		detailFontName = null;
	}

	function renderDetailVariants( familyName ) {
		const variants = fonts.filter( f => f.name === familyName );
		if ( ! variants.length ) {
			detailVariants.innerHTML = '<p style="padding:16px 20px;color:var(--oc-gray-400);font-size:13px;">No variants found.</p>';
			return;
		}

		detailVariants.innerHTML = variants.map( f => {
			injectFontFace( f );
			const family = `oc-preview-${f.id}`;
			return `
				<div class="oc-detail-variant" data-font-id="${f.id}">
					<div class="oc-detail-variant-preview" style="font-family:'${family}';font-weight:${f.weight};font-style:${f.style};">AaBbCc 123</div>
					<div class="oc-detail-variant-meta">
						<span class="oc-badge oc-badge-format">${h( f.ext )}</span>
						<span class="oc-code">${h( f.weight )}</span>
						${f.style === 'italic' ? '<span class="oc-badge oc-badge-inactive">Italic</span>' : ''}
						${f.embroidery_suitable ? '<span class="oc-badge oc-badge-active">Embroidery</span>' : ''}
						<span class="oc-badge ${f.active ? 'oc-badge-active' : 'oc-badge-inactive'}">${f.active ? 'Active' : 'Inactive'}</span>
					</div>
					<div class="oc-detail-variant-actions">
						<a href="${f.toggleUrl}" class="oc-btn oc-btn-secondary oc-btn-sm">${f.active ? 'Deactivate' : 'Activate'}</a>
						<a href="${f.deleteUrl}" onclick="return confirm('Delete this font?');" class="oc-btn oc-btn-danger oc-btn-sm">Delete</a>
					</div>
				</div>
			`;
		} ).join( '' );
	}

	// ── AJAX rename ────────────────────────────────────────────────────────────

	async function saveRename() {
		const newName = detailNameInput.value.trim();
		if ( ! newName || newName === detailFontName ) return;

		// Get any font ID from this family to pass to the server.
		const sample = fonts.find( f => f.name === detailFontName );
		if ( ! sample ) return;

		detailSaveBtn.disabled    = true;
		detailSaveBtn.textContent = 'Saving…';

		try {
			const fd = new FormData();
			fd.append( 'action', 'oc_font_rename' );
			fd.append( 'nonce',  nonce );
			fd.append( 'id',     sample.id );
			fd.append( 'name',   newName );

			const res  = await fetch( ajaxUrl, { method: 'POST', body: fd } );
			if ( ! res.ok ) throw new Error( `HTTP ${ res.status }` );
			const json = await res.json();

			if ( ! json.success ) {
				alert( json.data?.message || 'Rename failed.' );
				return;
			}

			// Update local state.
			const oldName = json.data.oldName;
			fonts.forEach( f => { if ( f.name === oldName ) f.name = newName; } );

			// Update all matching cards in the DOM.
			document.querySelectorAll( `.oc-font-card[data-font-name="${CSS.escape( oldName )}"]` )
				.forEach( card => {
					card.dataset.fontName = newName;
					const nameEl = card.querySelector( '.oc-font-card-name' );
					if ( nameEl ) { nameEl.textContent = newName; nameEl.title = newName; }
				} );

			detailFontName = newName;

		} catch ( err ) {
			alert( err?.message || 'Network error — please try again.' );
		} finally {
			detailSaveBtn.disabled    = false;
			detailSaveBtn.textContent = 'Rename family';
		}
	}

	// ── Event: card clicks ─────────────────────────────────────────────────────

	function bindCardClick( card ) {
		card.addEventListener( 'click', function ( e ) {
			// Don't intercept clicks on action links.
			if ( e.target.closest( 'a' ) || e.target.closest( 'button' ) ) return;
			openDetailPanel( this.dataset.fontId, this.dataset.fontName );
		} );
		card.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Enter' || e.key === ' ' ) {
				e.preventDefault();
				openDetailPanel( this.dataset.fontId, this.dataset.fontName );
			}
		} );
	}

	document.querySelectorAll( '.oc-font-card' ).forEach( bindCardClick );

	// ── Event: upload button ───────────────────────────────────────────────────

	uploadFontBtn.addEventListener( 'click', function () {
		openUploadModal( null );
	} );

	// ── Event: modal close ─────────────────────────────────────────────────────

	modalCloseBtn.addEventListener( 'click', closeUploadModal );

	uploadModal.addEventListener( 'click', function ( e ) {
		if ( e.target === uploadModal ) closeUploadModal();
	} );

	// ── Event: drop zone ───────────────────────────────────────────────────────

	dropZone.addEventListener( 'click', function () { fileInput.click(); } );

	dropZone.addEventListener( 'dragover', function ( e ) {
		e.preventDefault();
		dropZone.classList.add( 'oc-drop-over' );
	} );
	dropZone.addEventListener( 'dragleave', function () {
		dropZone.classList.remove( 'oc-drop-over' );
	} );
	dropZone.addEventListener( 'drop', function ( e ) {
		e.preventDefault();
		dropZone.classList.remove( 'oc-drop-over' );
		const file = e.dataTransfer?.files[ 0 ];
		if ( file ) handleFileSelected( file );
	} );

	fileInput.addEventListener( 'change', function () {
		if ( this.files[ 0 ] ) handleFileSelected( this.files[ 0 ] );
	} );

	// ── Event: upload step 2 controls ─────────────────────────────────────────

	backBtn.addEventListener( 'click', function () {
		step1.style.display   = '';
		step2.style.display   = 'none';
		modalFooter.style.display = 'none';
		currentFile = null;
		fileInput.value = '';
	} );

	submitBtn.dataset.label = submitBtn.textContent;
	submitBtn.addEventListener( 'click', submitUpload );

	// Live preview weight/style update.
	[ weightSel, styleSel ].forEach( function ( sel ) {
		if ( sel ) sel.addEventListener( 'change', function () {
			if ( currentFontFace ) applyPreviewStyle();
		} );
	} );

	// ── Event: detail panel controls ───────────────────────────────────────────

	detailCloseBtn.addEventListener( 'click', closeDetailPanel );

	detailPanel.addEventListener( 'click', function ( e ) {
		if ( e.target === detailPanel ) closeDetailPanel();
	} );

	detailSaveBtn.addEventListener( 'click', saveRename );

	detailNameInput.addEventListener( 'keydown', function ( e ) {
		if ( e.key === 'Enter' ) saveRename();
	} );

	addWeightBtn.addEventListener( 'click', function () {
		openUploadModal( detailFontName );
	} );

	// ── Tab switching ──────────────────────────────────────────────────────────

	const createGroupBtn = document.getElementById( 'oc-create-group-btn' );

	document.querySelectorAll( '.oc-tab' ).forEach( function ( tab ) {
		tab.addEventListener( 'click', function () {
			document.querySelectorAll( '.oc-tab' ).forEach( t => t.classList.remove( 'oc-tab--active' ) );
			document.querySelectorAll( '.oc-tab-panel' ).forEach( p => { p.hidden = true; } );
			tab.classList.add( 'oc-tab--active' );
			document.getElementById( tab.dataset.target ).hidden = false;

			const onFonts = tab.dataset.target === 'oc-tab-fonts';
			if ( uploadFontBtn ) uploadFontBtn.style.display  = onFonts ? '' : 'none';
			if ( createGroupBtn ) createGroupBtn.style.display = onFonts ? 'none' : '';
		} );
	} );

	// ── Groups state ───────────────────────────────────────────────────────────

	let groups        = window.ocGroupsData || [];
	let editingGroup  = null; // null = new group, object = existing group

	const groupModal       = document.getElementById( 'oc-group-modal' );
	const groupNameInput   = document.getElementById( 'oc-group-name-input' );
	const groupFontPicker  = document.getElementById( 'oc-group-font-picker' );
	const groupSaveBtn     = document.getElementById( 'oc-group-save-btn' );
	const groupDeleteBtn   = document.getElementById( 'oc-group-delete-btn' );
	const groupCancelBtn   = document.getElementById( 'oc-group-cancel-btn' );
	const groupModalClose  = document.getElementById( 'oc-group-modal-close' );
	const groupGrid        = document.getElementById( 'oc-group-grid' );
	const groupsEmpty      = document.getElementById( 'oc-groups-empty' );
	const groupsCount      = document.getElementById( 'oc-groups-count' );
	const selectedCountEl  = document.getElementById( 'oc-group-selected-count' );

	// ── Group modal open / close ───────────────────────────────────────────────

	function openGroupModal( group ) {
		editingGroup = group || null;
		groupNameInput.value = group ? group.name : '';

		// Show delete button only for existing groups.
		if ( groupDeleteBtn ) groupDeleteBtn.style.display = group ? '' : 'none';

		renderFontPicker( group ? group.fontIds : [] );
		groupModal.hidden = false;
		document.body.style.overflow = 'hidden';
		groupNameInput.focus();
	}

	function closeGroupModal() {
		groupModal.hidden = true;
		document.body.style.overflow = '';
		editingGroup = null;
	}

	// ── Font picker (checkboxes) ───────────────────────────────────────────────

	function renderFontPicker( selectedIds ) {
		if ( ! fonts.length ) {
			groupFontPicker.innerHTML = '<p style="padding:20px;color:var(--oc-gray-400);font-size:13px;">No fonts uploaded yet.</p>';
			updateSelectedCount();
			return;
		}

		// Deduplicate by family name — show one row per family, pick the first variant for preview.
		const families = [];
		const seen     = new Set();
		fonts.forEach( f => {
			if ( ! seen.has( f.name ) ) {
				seen.add( f.name );
				families.push( f );
			}
		} );

		groupFontPicker.innerHTML = families.map( f => {
			injectFontFace( f );
			const checked = selectedIds.includes( f.id ) ? 'checked' : '';
			return `
				<label class="oc-group-font-item${checked ? ' oc-selected' : ''}" data-font-id="${f.id}">
					<input type="checkbox" value="${f.id}" ${checked} />
					<span class="oc-group-font-preview-mini" style="font-family:'oc-preview-${f.id}';font-weight:${f.weight};font-style:${f.style};">Aa</span>
					<span class="oc-group-font-info">
						<span class="oc-group-font-info-name">${h( f.name )}</span>
						<span class="oc-group-font-info-meta">
							<span class="oc-badge oc-badge-format">${h( f.ext )}</span>
							<span class="oc-code">${h( f.weight )}</span>
							${f.style === 'italic' ? '<span class="oc-badge oc-badge-inactive">Italic</span>' : ''}
						</span>
					</span>
				</label>
			`;
		} ).join( '' );

		// Toggle selected class on checkbox change.
		groupFontPicker.querySelectorAll( 'input[type="checkbox"]' ).forEach( cb => {
			cb.addEventListener( 'change', function () {
				this.closest( '.oc-group-font-item' ).classList.toggle( 'oc-selected', this.checked );
				updateSelectedCount();
			} );
		} );

		updateSelectedCount();
	}

	function getCheckedFontIds() {
		return Array.from( groupFontPicker.querySelectorAll( 'input[type="checkbox"]:checked' ) )
			.map( cb => parseInt( cb.value, 10 ) );
	}

	function updateSelectedCount() {
		if ( ! selectedCountEl ) return;
		const n = getCheckedFontIds().length;
		selectedCountEl.textContent = `${n} selected`;
	}

	// ── AJAX: save group (create or update) ────────────────────────────────────

	async function saveGroup() {
		const name     = groupNameInput.value.trim();
		const fontIds  = getCheckedFontIds();

		if ( ! name ) { groupNameInput.focus(); return; }

		const label = groupSaveBtn.textContent;
		groupSaveBtn.disabled    = true;
		groupSaveBtn.textContent = 'Saving…';

		try {
			const fd = new FormData();
			fd.append( 'nonce', nonce );
			fontIds.forEach( id => fd.append( 'font_ids[]', id ) );

			if ( editingGroup ) {
				fd.append( 'action', 'oc_group_update' );
				fd.append( 'id',     editingGroup.id );
				fd.append( 'name',   name );
			} else {
				fd.append( 'action', 'oc_group_create' );
				fd.append( 'name',   name );
			}

			const res  = await fetch( ajaxUrl, { method: 'POST', body: fd } );
			if ( ! res.ok ) throw new Error( `HTTP ${ res.status }` );
			const json = await res.json();

			if ( ! json.success ) { alert( json.data?.message || 'Save failed.' ); return; }

			const saved = json.data;

			if ( editingGroup ) {
				// Update in state.
				const idx = groups.findIndex( g => g.id === saved.id );
				if ( idx !== -1 ) groups[ idx ] = saved;
				// Update card in DOM.
				const card = groupGrid.querySelector( `.oc-group-card[data-group-id="${saved.id}"]` );
				if ( card ) {
					card.dataset.groupName = saved.name;
					refreshGroupCard( card, saved );
				}
			} else {
				groups.push( saved );
				addGroupCardToGrid( saved );
				updateGroupsCount();
			}

			closeGroupModal();
		} catch ( err ) {
			alert( err?.message || 'Network error — please try again.' );
		} finally {
			groupSaveBtn.disabled    = false;
			groupSaveBtn.textContent = label;
		}
	}

	// ── AJAX: delete group ─────────────────────────────────────────────────────

	async function deleteGroup() {
		if ( ! editingGroup ) return;
		if ( ! confirm( `Delete the group "${editingGroup.name}"?` ) ) return;

		const fd = new FormData();
		fd.append( 'action',  'oc_group_delete' );
		fd.append( 'nonce',   nonce );
		fd.append( 'id',      editingGroup.id );

		try {
		const res  = await fetch( ajaxUrl, { method: 'POST', body: fd } );
		if ( ! res.ok ) throw new Error( `HTTP ${ res.status }` );
		const json = await res.json();
			if ( ! json.success ) { alert( 'Delete failed.' ); return; }

			// Remove from state.
			groups = groups.filter( g => g.id !== editingGroup.id );

			// Remove card from DOM.
			const card = groupGrid.querySelector( `.oc-group-card[data-group-id="${editingGroup.id}"]` );
			if ( card ) card.remove();

			updateGroupsCount();
			closeGroupModal();
		} catch ( err ) {
			alert( err?.message || 'Network error — please try again.' );
		}
	}

	// ── Group card rendering ───────────────────────────────────────────────────

	function buildGroupCardEl( group ) {
		const el = document.createElement( 'div' );
		el.className = 'oc-group-card';
		el.dataset.groupId   = group.id;
		el.dataset.groupName = group.name;
		el.setAttribute( 'role', 'button' );
		el.setAttribute( 'tabindex', '0' );
		refreshGroupCard( el, group );
		return el;
	}

	function refreshGroupCard( el, group ) {
		const members = fonts.filter( f => group.fontIds.includes( f.id ) );
		const preview = members.slice( 0, 5 ).map( f => {
			injectFontFace( f );
			return `<span style="font-family:'oc-preview-${f.id}';font-weight:${f.weight};font-style:${f.style};" title="${h( f.name )}">Aa</span>`;
		} ).join( '' );
		const more = group.fontIds.length > 5
			? `<span class="oc-group-card-more">+${group.fontIds.length - 5}</span>` : '';
		const empty = ! group.fontIds.length
			? `<span style="color:var(--oc-gray-400);font-size:12px;font-family:sans-serif;">Empty group</span>` : '';
		const n = group.fontIds.length;

		el.innerHTML = `
			<div class="oc-group-card-body">
				<p class="oc-group-card-name">${h( group.name )}</p>
				<p class="oc-group-card-count">${n} ${n === 1 ? 'font' : 'fonts'}</p>
				<div class="oc-group-card-previews">${preview}${more}${empty}</div>
			</div>
		`;
	}

	function addGroupCardToGrid( group ) {
		if ( groupsEmpty ) groupsEmpty.style.display = 'none';
		if ( groupGrid )   groupGrid.style.display   = '';
		const card = buildGroupCardEl( group );
		bindGroupCardClick( card );
		groupGrid.appendChild( card );
	}

	function updateGroupsCount() {
		if ( ! groupsCount ) return;
		const n = groups.length;
		groupsCount.textContent = `${n} ${n === 1 ? 'group' : 'groups'}`;
	}

	// ── Event: group card click ────────────────────────────────────────────────

	function bindGroupCardClick( card ) {
		card.addEventListener( 'click', function () {
			const id    = parseInt( this.dataset.groupId, 10 );
			const group = groups.find( g => g.id === id );
			if ( group ) openGroupModal( group );
		} );
		card.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Enter' || e.key === ' ' ) {
				e.preventDefault();
				this.click();
			}
		} );
	}

	document.querySelectorAll( '.oc-group-card' ).forEach( bindGroupCardClick );

	// ── Event: group modal controls ────────────────────────────────────────────

	if ( createGroupBtn ) createGroupBtn.addEventListener( 'click', () => openGroupModal( null ) );
	if ( groupModalClose ) groupModalClose.addEventListener( 'click', closeGroupModal );
	if ( groupCancelBtn )  groupCancelBtn.addEventListener( 'click', closeGroupModal );
	if ( groupSaveBtn )    groupSaveBtn.addEventListener( 'click', saveGroup );
	if ( groupDeleteBtn )  groupDeleteBtn.addEventListener( 'click', deleteGroup );

	if ( groupModal ) {
		groupModal.addEventListener( 'click', function ( e ) {
			if ( e.target === groupModal ) closeGroupModal();
		} );
	}

	if ( groupNameInput ) {
		groupNameInput.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Enter' ) saveGroup();
		} );
	}

	// ── Utilities ──────────────────────────────────────────────────────────────

	/** Escape a string for safe HTML insertion. */
	function h( str ) {
		return String( str )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' );
	}

	function filenameToTitle( filename ) {
		return filename
			.replace( /\.[^.]+$/, '' )
			.replace( /[-_]/g, ' ' )
			.replace( /\b\w/g, c => c.toUpperCase() );
	}

} )();
