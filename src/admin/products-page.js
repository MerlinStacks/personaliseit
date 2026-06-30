/**
 * Admin — Design Editor JS
 *
 * Features:
 *  - 3-column live editor (areas / canvas / layers)
 *  - Draw-to-create: click-drag on canvas to define new layer bounds
 *  - Multi-area navigation strip above the canvas
 *  - Layer type indicators on canvas ghosts and bounds box
 *  - Undo/redo (Ctrl+Z / Ctrl+Shift+Z) with 25-step history
 */

/* global ocProductsData, wp */

( function () {
	'use strict';

	// ── Layer type metadata ────────────────────────────────────────────────────

	const LAYER_TYPES = {
		text:     { label: 'Text',         icon: 'Aa', color: '#0284c7' },
		textarea: { label: 'Text Area',    icon: '\u00b6',  color: '#7c3aed' },
		image:    { label: 'Image',        icon: '\ud83d\uddbc',  color: '#059669' },
		spotify:  { label: 'Spotify Code', icon: '\u266b',  color: '#1db954' },
		lineart:  { label: 'Line Art',     icon: '\u270f',  color: '#d97706' },
		clipart:  { label: 'Clipart',      icon: '\u2726',  color: '#dc2626' },
	};

	const LAYER_DEFAULTS = {
		text:     { w: 300, h:  50 },
		textarea: { w: 300, h: 120 },
		image:    { w: 200, h: 200 },
		spotify:  { w: 150, h: 150 },
		lineart:  { w: 200, h: 200 },
		clipart:  { w: 150, h: 150 },
	};

	function layerIcon ( t ) { return ( LAYER_TYPES[ t ] || {} ).icon  || '?';       }
	function layerColor( t ) { return ( LAYER_TYPES[ t ] || {} ).color || '#9ca3af'; }
	function layerLabel( t ) { return ( LAYER_TYPES[ t ] || {} ).label || t;         }

	// ── Layer action icons ─────────────────────────────────────────────────────

	const ICO_EYE     = '<svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M1 8c0 0 2.5-5 7-5s7 5 7 5-2.5 5-7 5-7-5-7-5z"/><circle cx="8" cy="8" r="2"/></svg>';
	const ICO_EYE_OFF = '<svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M1 8c0 0 2.5-5 7-5s7 5 7 5-2.5 5-7 5-7-5-7-5z"/><circle cx="8" cy="8" r="2"/><line x1="2" y1="14" x2="14" y2="2"/></svg>';
	const ICO_LOCK    = '<svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="7" width="10" height="8" rx="1.5"/><path d="M5.5 7V5a2.5 2.5 0 0 1 5 0v2"/></svg>';
	const ICO_UNLOCK  = '<svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="7" width="10" height="8" rx="1.5"/><path d="M5.5 7V5a2.5 2.5 0 0 1 5 0"/></svg>';
	const ICO_BIN     = '<svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><line x1="1.5" y1="4" x2="14.5" y2="4"/><path d="M6 4V2.5a.5.5 0 0 1 .5-.5h3a.5.5 0 0 1 .5.5V4"/><path d="M3 4l.8 9.5a.5.5 0 0 0 .5.5h7.4a.5.5 0 0 0 .5-.5L13 4"/></svg>';

	// ── Layer tabs ─────────────────────────────────────────────────────────────

	const LAYER_TABS = {
		text:     [ { id: 'general', label: 'General', icon: 'G' }, { id: 'content', label: 'Content', icon: 'T' }, { id: 'style', label: 'Style', icon: 'A' }, { id: 'properties', label: 'Properties', icon: '\u2699' }, { id: 'validation', label: 'Validation', icon: '\u2713' } ],
		textarea: [ { id: 'general', label: 'General', icon: 'G' }, { id: 'content', label: 'Content', icon: 'T' }, { id: 'style', label: 'Style', icon: 'A' }, { id: 'properties', label: 'Properties', icon: '\u2699' }, { id: 'validation', label: 'Validation', icon: '\u2713' } ],
		image:    [ { id: 'general', label: 'General', icon: 'G' }, { id: 'file',       label: 'File',       icon: '\ud83d\uddbc' }, { id: 'validation', label: 'Validation', icon: '\u2713' } ],
		spotify:  [ { id: 'general', label: 'General', icon: 'G' }, { id: 'appearance', label: 'Appearance', icon: '\u25d0' }, { id: 'validation', label: 'Validation', icon: '\u2713' } ],
		lineart:  [ { id: 'general', label: 'General', icon: 'G' }, { id: 'colours',    label: 'Colours',    icon: '\u25cf' }, { id: 'validation', label: 'Validation', icon: '\u2713' } ],
		clipart:  [ { id: 'general', label: 'General', icon: 'G' }, { id: 'library',    label: 'Library',    icon: '\u2726' }, { id: 'validation', label: 'Validation', icon: '\u2713' } ],
	};

	// ── Area colours ───────────────────────────────────────────────────────────

	const AREA_COLORS = [ '#4f46e5', '#059669', '#d97706', '#dc2626', '#0284c7', '#7c3aed', '#db2777', '#ea580c' ];
	function areaColor( i ) { return AREA_COLORS[ i % AREA_COLORS.length ]; }

	// ── State ──────────────────────────────────────────────────────────────────

	let areas              = [];
	let selectedIndex      = -1;
	let selectedLayerIndex = -1;
	let activeLayerTab     = 'general';
	let uidCounter         = 0;

	// Canvas drag/resize
	let drag         = null;
	let layerDragSrc = -1;

	// Draw-to-create
	let drawState  = null; // { startX, startY, startCX, startCY } natural-px + client
	let drawEl     = null; // preview DOM element

	// Draw type picker
	let drawPopup  = null;

	// Undo/redo
	const HISTORY_MAX = 25;
	let history      = [];
	let historyIndex = -1;

	let mediaFrame = null;

	// ── Autosave ─────────────────────────────────────────────────────────────────
	let isDirty           = false;
	let autosaveTimer     = null;
	let lastSavedTime     = null;
	let autosaveError     = '';
	let designId          = 0;
	let autosaveInterval  = 30000;

	// ── Undo / Redo ────────────────────────────────────────────────────────────

	function snapshot() {
		if ( historyIndex < history.length - 1 ) history = history.slice( 0, historyIndex + 1 );
		history.push( JSON.stringify( areas ) );
		if ( history.length > HISTORY_MAX ) history.shift();
		historyIndex = history.length - 1;
		updateUndoRedoBtns();
		markDirty();
	}

	function undo() {
		if ( historyIndex <= 0 ) return;
		historyIndex--;
		restoreHistory();
	}

	function redo() {
		if ( historyIndex >= history.length - 1 ) return;
		historyIndex++;
		restoreHistory();
	}

	function restoreHistory() {
		let snapshot;
		try {
			snapshot = JSON.parse( history[ historyIndex ] );
		} catch ( err ) {
			console.warn( '[OC] Failed to restore history snapshot:', err );
			history = [];
			historyIndex = -1;
			updateUndoRedoBtns();
			return;
		}
		areas = snapshot;
		// Ensure uidCounter is above all restored _uid values to avoid collisions
		areas.forEach( a => {
			uidCounter = Math.max( uidCounter, a._uid || 0 );
			( a.layers || [] ).forEach( l => { uidCounter = Math.max( uidCounter, l._uid || 0 ); } );
		} );
		if ( selectedIndex >= areas.length ) selectedIndex = areas.length - 1;
		const area = areas[ selectedIndex ];
		if ( ! area || selectedLayerIndex >= ( area.layers || [] ).length ) selectedLayerIndex = -1;
		renderAll();
		updateUndoRedoBtns();
	}

	function updateUndoRedoBtns() {
		const u = document.getElementById( 'oc-undo-btn' );
		const r = document.getElementById( 'oc-redo-btn' );
		if ( u ) u.disabled = historyIndex <= 0;
		if ( r ) r.disabled = historyIndex >= history.length - 1;
	}

	// ── Autosave helpers ────────────────────────────────────────────────────────

	function markDirty() {
		isDirty = true;
		updateAutosaveIndicator();
	}

	function updateAutosaveIndicator() {
		var el = document.getElementById( 'oc-autosave-indicator' );
		if ( ! el ) return;
		if ( autosaveError ) {
			el.textContent = autosaveError;
			el.className = 'oc-autosave-indicator oc-autosave-indicator--error';
		} else if ( isDirty ) {
			el.textContent = 'Unsaved changes';
			el.className = 'oc-autosave-indicator oc-autosave-indicator--dirty';
		} else if ( lastSavedTime ) {
			var diff = Math.round( ( Date.now() - lastSavedTime ) / 1000 );
			var label = diff < 60 ? diff + 's ago' : Math.floor( diff / 60 ) + 'm ago';
			el.textContent = 'Saved ' + label;
			el.className = 'oc-autosave-indicator oc-autosave-indicator--saved';
		} else {
			el.textContent = '';
			el.className = 'oc-autosave-indicator';
		}
	}

	function collectState() {
		return {
			areas: areas.map( function ( a ) {
				return {
					id: a.id, label: a.label, method: a.method,
					mockupId: a.mockupId, mockupUrl: a.mockupUrl,
					x: a.x, y: a.y, w: a.w, h: a.h, rotation: a.rotation,
					sortOrder: a.sortOrder, visible: a.visible, locked: a.locked,
					layers: ( a.layers || [] ).map( function ( l ) {
						return {
							id: l.id, type: l.type, label: l.label,
							x: l.x, y: l.y, w: l.w, h: l.h,
							sortOrder: l.sortOrder, visible: l.visible, locked: l.locked,
							settings: l.settings || {},
						};
					} ),
				};
			} ),
		};
	}

	function applyAutosavedState( savedState ) {
		var layersByAreaId = {};
		( savedState.areas || [] ).forEach( function ( a ) {
			( a.layers || [] ).forEach( function ( l ) {
				var aid = Number( l.areaId || 0 );
				if ( ! layersByAreaId[ aid ] ) layersByAreaId[ aid ] = [];
				layersByAreaId[ aid ].push( normaliseLayer( l ) );
			} );
		} );
		areas = ( savedState.areas || [] ).map( function ( a, i ) {
			return Object.assign( normaliseArea( a, i ), { layers: layersByAreaId[ Number( a.id ) ] || [] } );
		} );
		selectedIndex = areas.length > 0 ? 0 : -1;
		selectedLayerIndex = -1;
		activeLayerTab = 'general';
		history = [];
		historyIndex = -1;
		snapshot();
		renderAll();
	}

	function doAutosave() {
		if ( ! isDirty || ! designId ) return;
		var state = collectState();
		var body = new URLSearchParams( {
			action:  'oc_autosave_design',
			nonce:   ocProductsData.nonce,
			design_id: designId,
			state:   JSON.stringify( state ),
		} );
		fetch( ocProductsData.ajaxUrl, { method: 'POST', body: body } )
			.then( function ( r ) {
				if ( ! r.ok ) throw new Error( 'HTTP ' + r.status );
				return r.json();
			} )
			.then( function ( json ) {
				if ( json.success ) {
					isDirty = false;
					lastSavedTime = Date.now();
					autosaveError = '';
					updateAutosaveIndicator();
				} else {
					autosaveError = 'Autosave failed';
					updateAutosaveIndicator();
				}
			} )
			.catch( function ( err ) {
				console.warn( '[OC] Autosave failed:', err );
				autosaveError = 'Autosave failed';
				updateAutosaveIndicator();
			} );
	}

	function startAutosavePoll() {
		if ( autosaveTimer ) clearInterval( autosaveTimer );
		autosaveTimer = setInterval( doAutosave, autosaveInterval );
	}

	function stopAutosavePoll() {
		if ( autosaveTimer ) { clearInterval( autosaveTimer ); autosaveTimer = null; }
	}

	// ── Init ───────────────────────────────────────────────────────────────────

	function init() {
		const data = window.ocProductsData || {};
		designId = Number( data.designId || 0 );

		if ( designId > 0 ) {
			var body = new URLSearchParams( {
				action:    'oc_restore_autosave',
				nonce:     data.nonce,
				design_id: designId,
			} );
			fetch( data.ajaxUrl, { method: 'POST', body: body } )
				.then( function ( r ) {
					if ( ! r.ok ) throw new Error( 'HTTP ' + r.status );
					return r.json();
				} )
				.then( function ( json ) {
					if ( json.success && json.data && json.data.state ) {
						var ts = json.data.timestamp || 0;
						var diff = Math.round( ( Date.now() - ts * 1000 ) / 1000 );
						var mins = Math.max( 1, Math.floor( diff / 60 ) );
						var msg = 'You have unsaved changes from ' + mins + ' minute' + ( mins > 1 ? 's' : '' ) + ' ago. Restore?';
						if ( confirm( msg ) ) {
							applyAutosavedState( json.data.state );
							isDirty = true;
							startAutosavePoll();
							updateAutosaveIndicator();
							initInteractions();
							return;
						}
					}
					loadDefaultData();
				} )
				.catch( loadDefaultData );
		} else {
			loadDefaultData();
		}
	}

	function loadDefaultData() {
		const data = window.ocProductsData || {};

		const layersByAreaId = {};
		( data.layers || [] ).forEach( l => {
			const aid = Number( l.areaId );
			if ( ! layersByAreaId[ aid ] ) layersByAreaId[ aid ] = [];
			layersByAreaId[ aid ].push( normaliseLayer( l ) );
		} );

		areas = ( data.areas || [] ).map( ( a, i ) => ( {
			...normaliseArea( a, i ),
			layers: layersByAreaId[ Number( a.id ) ] || [],
		} ) );

		selectedIndex = areas.length > 0 ? 0 : -1;

		renderAll();
		snapshot(); // seed initial history state
		isDirty = false; // reset after seed
		initInteractions();

		if ( designId > 0 ) {
			startAutosavePoll();
		}

		// Safety net: force hidden-field re-render immediately before submit,
		// so the latest in-memory settings are always serialised into POST data.
		document.getElementById( 'oc-design-form' )?.addEventListener( 'submit', () => {
			renderHiddenFields();
			stopAutosavePoll();
		} );
	}

	// ── Normalisers ────────────────────────────────────────────────────────────

	function normaliseArea( a, i ) {
		return {
			_uid:      ++uidCounter,
			id:        Number( a.id )       || 0,
			label:     a.label              || '',
			method:    a.method             || 'uv',
			mockupId:  Number( a.mockupId ) || 0,
			mockupUrl: a.mockupUrl          || '',
			x:         Number( a.x )        || 0,
			y:         Number( a.y )        || 0,
			w:         Number( a.w )        || 300,
			h:         Number( a.h )        || 300,
			rotation:  normaliseRotation( a.rotation ),
			sortOrder: i,
			visible:   a.visible !== false && a.visible !== 0,
			locked:    !! ( a.locked ),
		};
	}

	function normaliseLayer( l ) {
		const type = l.type || 'text';
		return {
			_uid:      ++uidCounter,
			id:        Number( l.id )        || 0,
			type,
			label:     l.label              || '',
			x:         Number( l.x )        || 0,
			y:         Number( l.y )        || 0,
			w:         Number( l.w )        || 200,
			h:         Number( l.h )        || 50,
			sortOrder: Number( l.sortOrder ) || 0,
			visible:   l.visible !== false && l.visible !== 0,
			locked:    !! ( l.locked ),
			settings:  normaliseSettings( type, l.settings ),
		};
	}

	function defaultSettings( type ) {
		switch ( type ) {
			case 'text':
			case 'textarea': return { default_text: '', char_limit: 0, alignment: 'center', default_font_id: 0, default_font_size: 0, default_color: '#000000', min_font_size: 0, max_font_size: 0, font_groups: [], colour_groups: [], allow_font_change: true, allow_colour_change: true, allow_size_change: false, required: false };
			case 'image':    return { formats: [ 'png', 'jpg', 'svg', 'webp' ], max_size_mb: 10, required: false };
			case 'spotify':  return { colour_groups: [], required: false };
			case 'lineart':  return { colour_groups: [], required: false };
			case 'clipart':  return { clipart_groups: [], required: false };
			default:         return { required: false };
		}
	}
	function normaliseSettings( type, existing ) {
		return Object.assign( defaultSettings( type ), existing || {} );
	}

	// ── Utilities ──────────────────────────────────────────────────────────────

	function esc( s ) {
		return String( s ).replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' ).replace( /"/g, '&quot;' );
	}
	function setVal( id, v ) { const el = document.getElementById( id ); if ( el ) el.value = v; }
	function getScale( img ) { return ( img && img.naturalWidth ) ? img.clientWidth / img.naturalWidth : 0; }
	function clamp( v, lo, hi ) { return Math.min( Math.max( v, lo ), hi ); }
	function fontLimit( value ) { return Math.max( 0, parseInt( value, 10 ) || 0 ); }
	function normaliseHex( value ) { return /^#[0-9a-f]{6}$/i.test( String( value || '' ) ) ? value : '#000000'; }
	function clampFontSize( size, settings, scale ) {
		const min = fontLimit( settings?.min_font_size ) * scale;
		const max = fontLimit( settings?.max_font_size ) * scale;
		if ( max && ( ! min || min <= max ) ) size = Math.min( size, max );
		if ( min ) size = Math.max( size, min );
		return size;
	}
	function findFont( fontId ) {
		const fonts = ( window.ocProductsData || {} ).fonts || [];
		return fonts.find( f => Number( f.id ) === Number( fontId ) ) || ( ! fontId ? fonts[ 0 ] : null );
	}
	function normaliseRotation( value ) {
		const angle = Number( value ) || 0;
		return Math.round( ( ( angle % 360 ) + 360 ) % 360 );
	}

	function clampLayerToArea( layer, area ) {
		if ( ! layer || ! area ) return;
		const maxW = Math.max( 1, area.w );
		const maxH = Math.max( 1, area.h );
		layer.w = clamp( Math.round( layer.w ), 1, maxW );
		layer.h = clamp( Math.round( layer.h ), 1, maxH );
		layer.x = clamp( Math.round( layer.x ), area.x, area.x + area.w - layer.w );
		layer.y = clamp( Math.round( layer.y ), area.y, area.y + area.h - layer.h );
	}
	function hexRgba( hex, a ) {
		const r = parseInt( hex.slice( 1, 3 ), 16 );
		const g = parseInt( hex.slice( 3, 5 ), 16 );
		const b = parseInt( hex.slice( 5, 7 ), 16 );
		return 'rgba(' + r + ',' + g + ',' + b + ',' + a + ')';
	}
	function methodLabel( m ) { return ( ( window.ocProductsData || {} ).methodLabels || {} )[ m ] || m; }

	/** Apply rich visual content to a layer container element */
	function applyLayerPreview( layer, el, renderedH, isGhost ) {
		// Remove any existing preview children
		el.querySelectorAll( '.oc-lp' ).forEach( c => c.remove() );
		if ( ! layer ) return;

		const s = layer.settings || {};

		if ( layer.type === 'text' || layer.type === 'textarea' ) {
			const text  = s.default_text || layer.label || layerLabel( layer.type );
			const align = s.alignment || 'center';
			const scale = renderedH / Math.max( 1, layer.h );
			const defaultFontSize = fontLimit( s.default_font_size );
			const autoFontSize = Math.max( 8, Math.min( renderedH * ( isGhost ? 0.36 : 0.42 ), isGhost ? 22 : 30 ) );
			const fs = clampFontSize( defaultFontSize ? defaultFontSize * scale : autoFontSize, s, scale );
			const font = findFont( s.default_font_id || 0 );

			const d = document.createElement( 'div' );
			d.className      = 'oc-lp oc-lp-text';
			d.style.fontSize = fs + 'px';
			d.style.textAlign = align;
			d.style.color = normaliseHex( s.default_color );
			if ( font ) d.style.fontFamily = "'" + String( font.name ).replace( /'/g, "\\'" ) + "', sans-serif";
			if ( layer.type === 'textarea' ) d.style.alignItems = 'flex-start';
			d.textContent    = text;
			el.appendChild( d );

		} else if ( layer.type === 'image' ) {
			const fs = Math.max( 14, Math.min( renderedH * 0.35, 40 ) );
			const d  = document.createElement( 'div' );
			d.className = 'oc-lp oc-lp-icon';
			d.innerHTML =
				'<svg width="' + Math.round( fs ) + '" height="' + Math.round( fs * 0.8 ) + '" viewBox="0 0 24 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">' +
					'<rect x="1" y="4" width="22" height="15" rx="2"/>' +
					'<circle cx="12" cy="11.5" r="3.5"/>' +
					'<path d="M8 4l2-3h4l2 3"/>' +
				'</svg>' +
				'<span>Upload Image</span>';
			el.appendChild( d );

		} else {
			const icons = { spotify: '\u266b', lineart: '\u270f', clipart: '\u2726' };
			const labels = { spotify: 'Spotify Code', lineart: 'Line Art', clipart: 'Clipart' };
			const fs = Math.max( 14, Math.min( renderedH * 0.35, 36 ) );
			const d  = document.createElement( 'div' );
			d.className = 'oc-lp oc-lp-icon';
			d.innerHTML = '<span style="font-size:' + Math.round( fs ) + 'px;">' + ( icons[ layer.type ] || '' ) + '</span><span>' + ( labels[ layer.type ] || layerLabel( layer.type ) ) + '</span>';
			el.appendChild( d );
		}
	}

	// ── Settings HTML helpers ──────────────────────────────────────────────────

	function sectionHdr( label ) { return '<p class="oc-settings-section-hdr">' + label + '</p>'; }
	function field( label, inputHtml ) {
		return '<div class="oc-editor-field"><label class="oc-settings-label">' + label + '</label>' + inputHtml + '</div>';
	}
	function toggleField( label, id, checked ) {
		return '<label class="oc-toggle-label oc-settings-toggle"><span class="oc-toggle"><input type="checkbox" id="' + id + '"' + ( checked ? ' checked' : '' ) + ' /><span class="oc-toggle-slider"></span></span> ' + label + '</label>';
	}
	function alignBtns( current ) {
		return '<div class="oc-align-btns">' +
			[ [ 'left', '\u2190', 'Left' ], [ 'center', '\u2261', 'Center' ], [ 'right', '\u2192', 'Right' ] ]
				.map( ( [ a, icon, lbl ] ) => '<button type="button" class="oc-align-btn' + ( a === current ? ' oc-align-btn--active' : '' ) + '" data-align="' + a + '">' + icon + ' ' + lbl + '</button>' )
				.join( '' ) +
			'</div>';
	}
	function groupChecks( cls, groups, selected ) {
		if ( ! groups.length ) return '<span class="oc-settings-empty">No groups created yet.</span>';
		return '<div class="oc-group-checks">' +
			groups.map( g => '<label class="oc-group-check-item"><input type="checkbox" class="' + cls + '" value="' + esc( g.id ) + '"' + ( selected.indexOf( Number( g.id ) ) !== -1 ? ' checked' : '' ) + ' /><span>' + esc( g.name ) + '</span></label>' ).join( '' ) +
			'</div>';
	}
	function fontOptions( fonts, selected ) {
		return '<option value="0">Auto / first available</option>' + fonts.map( f => '<option value="' + esc( f.id ) + '"' + ( Number( selected ) === Number( f.id ) ? ' selected' : '' ) + '>' + esc( f.name ) + '</option>' ).join( '' );
	}
	function formatChecks( selected ) {
		return '<div class="oc-group-checks">' +
			[ 'png', 'jpg', 'svg', 'webp', 'pdf', 'eps' ].map( fmt =>
				'<label class="oc-group-check-item"><input type="checkbox" class="oc-fmt-check" value="' + fmt + '"' + ( selected.indexOf( fmt ) !== -1 ? ' checked' : '' ) + ' /><span>' + fmt.toUpperCase() + '</span></label>'
			).join( '' ) + '</div>';
	}

	// ── Tab content builders ───────────────────────────────────────────────────

	function buildTabContent( tabId, layer ) {
		const s       = layer.settings;
		const data    = window.ocProductsData || {};
		const fGroups = data.fontGroups    || [];
		const fonts   = data.fonts         || [];
		const cGroups = data.colourGroups  || [];
		const aGroups = data.clipartGroups || [];
		// Engraving has no colour — don't show colour group pickers for layers in engraving areas.
		const area        = areas[ selectedIndex ];
		const isEngraving = area && area.method === 'engraving';

		switch ( tabId ) {
			case 'general':
				return field( 'Label', '<input type="text" id="oc-layer-label" class="oc-input" style="width:100%;" value="' + esc( layer.label ) + '" />' ) +
					'<p class="oc-settings-section-hdr">Position</p>' +
					'<div class="oc-bounds-grid">' +
						'<div class="oc-editor-field"><label class="oc-settings-label">X</label><input type="number" id="oc-layer-x" class="oc-input" min="0" style="width:100%;" value="' + layer.x + '" /></div>' +
						'<div class="oc-editor-field"><label class="oc-settings-label">Y</label><input type="number" id="oc-layer-y" class="oc-input" min="0" style="width:100%;" value="' + layer.y + '" /></div>' +
						'<div class="oc-editor-field"><label class="oc-settings-label">W</label><input type="number" id="oc-layer-w" class="oc-input" min="1" style="width:100%;" value="' + layer.w + '" /></div>' +
						'<div class="oc-editor-field"><label class="oc-settings-label">H</label><input type="number" id="oc-layer-h" class="oc-input" min="1" style="width:100%;" value="' + layer.h + '" /></div>' +
					'</div>';
			case 'content':
				return field( 'Default text', '<input type="text" id="oc-set-default-text" class="oc-input" style="width:100%;" placeholder="e.g. Your Name Here" value="' + esc( s.default_text || '' ) + '" />' ) +
					field( 'Max characters <span class="oc-hint">(0 = unlimited)</span>', '<input type="number" id="oc-set-char-limit" class="oc-input" min="0" style="width:100%;" value="' + esc( s.char_limit || 0 ) + '" />' );
			case 'style':
				return field( 'Alignment', alignBtns( s.alignment || 'center' ) ) +
					( fonts.length ? field( 'Default font', '<select id="oc-set-default-font" class="oc-input" style="width:100%;">' + fontOptions( fonts, s.default_font_id || 0 ) + '</select>' ) : field( 'Default font', '<span class="oc-settings-empty">No fonts uploaded yet.</span>' ) ) +
					'<div class="oc-bounds-grid">' +
						'<div class="oc-editor-field"><label class="oc-settings-label">Default font size <span class="oc-hint">(0 = auto)</span></label><input type="number" id="oc-set-default-font-size" class="oc-input" min="0" style="width:100%;" value="' + esc( s.default_font_size || 0 ) + '" /></div>' +
						( isEngraving ? '' : '<div class="oc-editor-field"><label class="oc-settings-label">Default colour</label><input type="color" id="oc-set-default-color" class="oc-input" style="width:100%;height:38px;" value="' + esc( normaliseHex( s.default_color ) ) + '" /></div>' ) +
					'</div>' +
					'<div class="oc-bounds-grid">' +
						'<div class="oc-editor-field"><label class="oc-settings-label">Min font size <span class="oc-hint">(0 = auto)</span></label><input type="number" id="oc-set-min-font-size" class="oc-input" min="0" style="width:100%;" value="' + esc( s.min_font_size || 0 ) + '" /></div>' +
						'<div class="oc-editor-field"><label class="oc-settings-label">Max font size <span class="oc-hint">(0 = auto)</span></label><input type="number" id="oc-set-max-font-size" class="oc-input" min="0" style="width:100%;" value="' + esc( s.max_font_size || 0 ) + '" /></div>' +
					'</div>' +
					( fGroups.length ? field( 'Font groups <span class="oc-hint">(empty = all)</span>', groupChecks( 'oc-fg-check', fGroups, s.font_groups || [] ) ) : field( 'Font groups', '<span class="oc-settings-empty">No font groups created yet.</span>' ) ) +
					( isEngraving
						? ''
						: ( cGroups.length ? field( 'Colour groups <span class="oc-hint">(empty = all)</span>', groupChecks( 'oc-cg-check', cGroups, s.colour_groups || [] ) ) : field( 'Colour groups', '<span class="oc-settings-empty">No colour groups created yet.</span>' ) ) );
			case 'file':
				return field( 'Accepted formats', formatChecks( s.formats || [ 'png', 'jpg', 'svg', 'webp' ] ) ) +
					field( 'Max file size (MB)', '<input type="number" id="oc-set-max-size" class="oc-input" min="1" style="width:100%;" value="' + esc( s.max_size_mb || 10 ) + '" />' );
			case 'appearance':
			case 'colours':
				if ( isEngraving ) return '<span class="oc-settings-empty">Colour is not applicable for engraving.</span>';
				return cGroups.length ? field( 'Colour groups <span class="oc-hint">(empty = all)</span>', groupChecks( 'oc-cg-check', cGroups, s.colour_groups || [] ) ) : '<span class="oc-settings-empty">No colour groups created yet.</span>';
			case 'library':
				return aGroups.length ? field( 'Clipart groups <span class="oc-hint">(empty = all)</span>', groupChecks( 'oc-ag-check', aGroups, s.clipart_groups || [] ) ) : '<span class="oc-settings-empty">No clipart groups created yet.</span>';
			case 'validation':
				return toggleField( 'Required field', 'oc-set-required', s.required );
			case 'properties':
				return '<p class="oc-settings-section-hdr">Customer can change</p>' +
					toggleField( 'Font', 'oc-set-allow-font-change', s.allow_font_change !== false ) +
					( isEngraving ? '' : toggleField( 'Colour', 'oc-set-allow-colour-change', s.allow_colour_change !== false ) ) +
					toggleField( 'Size', 'oc-set-allow-size-change', !! s.allow_size_change );
			default:
				return '';
		}
	}

	function bindSettingsHandlers( layer ) {
		const s    = layer.settings;
		const area = areas[ selectedIndex ];

		document.getElementById( 'oc-layer-label' )?.addEventListener( 'input', e => {
			layer.label = e.target.value;
			renderLayerList( area );
			renderCanvas();
			renderHiddenFields();
			markDirty();
		} );
		[ 'oc-layer-x', 'oc-layer-y', 'oc-layer-w', 'oc-layer-h' ].forEach( id => {
			document.getElementById( id )?.addEventListener( 'input', syncBoundsFromInputs );
		} );
		document.getElementById( 'oc-set-default-text' )?.addEventListener( 'input', e => { s.default_text = e.target.value; renderCanvas(); renderHiddenFields(); markDirty(); } );
		document.getElementById( 'oc-set-char-limit'   )?.addEventListener( 'input', e => { s.char_limit   = parseInt( e.target.value, 10 ) || 0; renderHiddenFields(); markDirty(); } );
		document.getElementById( 'oc-set-default-font' )?.addEventListener( 'change', e => { s.default_font_id = parseInt( e.target.value, 10 ) || 0; renderCanvas(); renderHiddenFields(); markDirty(); } );
		document.getElementById( 'oc-set-default-font-size' )?.addEventListener( 'input', e => { s.default_font_size = fontLimit( e.target.value ); renderCanvas(); renderHiddenFields(); markDirty(); } );
		document.getElementById( 'oc-set-default-color' )?.addEventListener( 'input', e => { s.default_color = normaliseHex( e.target.value ); renderCanvas(); renderHiddenFields(); markDirty(); } );
		document.getElementById( 'oc-set-min-font-size' )?.addEventListener( 'input', e => { s.min_font_size = fontLimit( e.target.value ); renderCanvas(); renderHiddenFields(); markDirty(); } );
		document.getElementById( 'oc-set-max-font-size' )?.addEventListener( 'input', e => { s.max_font_size = fontLimit( e.target.value ); renderCanvas(); renderHiddenFields(); markDirty(); } );
		document.querySelectorAll( '.oc-align-btn' ).forEach( btn => {
			btn.addEventListener( 'click', () => {
				s.alignment = btn.dataset.align;
				document.querySelectorAll( '.oc-align-btn' ).forEach( b => b.classList.toggle( 'oc-align-btn--active', b.dataset.align === btn.dataset.align ) );
				renderCanvas();
				renderHiddenFields();
				markDirty();
			} );
		} );
		document.querySelectorAll( '.oc-fg-check' ).forEach( cb => { cb.addEventListener( 'change', () => { s.font_groups   = [ ...document.querySelectorAll( '.oc-fg-check:checked'  ) ].map( c => Number( c.value ) ); renderHiddenFields(); markDirty(); } ); } );
		document.querySelectorAll( '.oc-cg-check' ).forEach( cb => { cb.addEventListener( 'change', () => { s.colour_groups  = [ ...document.querySelectorAll( '.oc-cg-check:checked'  ) ].map( c => Number( c.value ) ); renderHiddenFields(); markDirty(); } ); } );
		document.querySelectorAll( '.oc-ag-check' ).forEach( cb => { cb.addEventListener( 'change', () => { s.clipart_groups = [ ...document.querySelectorAll( '.oc-ag-check:checked'  ) ].map( c => Number( c.value ) ); renderHiddenFields(); markDirty(); } ); } );
		document.querySelectorAll( '.oc-fmt-check' ).forEach( cb => { cb.addEventListener( 'change', () => { s.formats = [ ...document.querySelectorAll( '.oc-fmt-check:checked' ) ].map( c => c.value ); renderHiddenFields(); markDirty(); } ); } );
		document.getElementById( 'oc-set-max-size'   )?.addEventListener( 'input', e => { s.max_size_mb = parseInt( e.target.value, 10 ) || 10; renderHiddenFields(); markDirty(); } );
		document.getElementById( 'oc-set-required'   )?.addEventListener( 'change', e => { s.required = e.target.checked; renderHiddenFields(); markDirty(); } );
		document.getElementById( 'oc-set-allow-font-change' )?.addEventListener( 'change', e => { s.allow_font_change = e.target.checked; renderHiddenFields(); markDirty(); } );
		document.getElementById( 'oc-set-allow-colour-change' )?.addEventListener( 'change', e => { s.allow_colour_change = e.target.checked; renderHiddenFields(); markDirty(); } );
		document.getElementById( 'oc-set-allow-size-change' )?.addEventListener( 'change', e => { s.allow_size_change = e.target.checked; renderHiddenFields(); markDirty(); } );
	}

	// ── Render ─────────────────────────────────────────────────────────────────

	function renderAll() {
		renderAreasList();
		renderAreaStrip();
		renderLeftAreaProps();
		renderCanvas();
		renderRightColumn();
		renderHiddenFields();
	}

	// ── Feature 3: Multi-area navigation strip ─────────────────────────────────

	function renderAreaStrip() {
		const strip = document.getElementById( 'oc-area-strip' );
		if ( ! strip ) return;

		if ( areas.length === 0 ) { strip.style.display = 'none'; return; }
		strip.style.display = '';
		strip.innerHTML = '';

		areas.forEach( ( area, i ) => {
			const card = document.createElement( 'div' );
			card.className = 'oc-area-strip-card' + ( i === selectedIndex ? ' oc-area-strip-card--active' : '' ) + ( ! area.visible ? ' oc-area-strip-card--hidden' : '' );

			const thumb = document.createElement( 'div' );
			thumb.className = 'oc-area-strip-thumb';
			if ( area.mockupUrl ) {
				const img = new Image();
				img.src = area.mockupUrl;
				img.draggable = false;
				thumb.appendChild( img );
			} else {
				thumb.style.background = areaColor( i );
			}

			const lbl = document.createElement( 'span' );
			lbl.className   = 'oc-area-strip-label';
			lbl.textContent = area.label || ( 'Area ' + ( i + 1 ) );

			card.appendChild( thumb );
			card.appendChild( lbl );
			card.addEventListener( 'click', () => {
				if ( area.locked ) return; // locked areas can't be selected from strip
				selectedIndex      = i;
				selectedLayerIndex = -1;
				activeLayerTab     = 'general';
				renderAll();
			} );
			strip.appendChild( card );
		} );
	}

	// ── Left panel ─────────────────────────────────────────────────────────────

	function renderAreasList() {
		const list  = document.getElementById( 'oc-areas-list' );
		const empty = document.getElementById( 'oc-areas-empty' );
		if ( ! list ) return;

		list.innerHTML = '';

		if ( areas.length === 0 ) { if ( empty ) empty.style.display = ''; return; }
		if ( empty ) empty.style.display = 'none';

		areas.forEach( ( area, i ) => {
			const item = document.createElement( 'div' );
			item.className = 'oc-area-item' +
				( i === selectedIndex ? ' oc-area-item--active' : '' ) +
				( ! area.visible      ? ' oc-layer--hidden'      : '' ) +
				( area.locked         ? ' oc-layer--locked'       : '' );

			item.innerHTML =
				'<span class="oc-area-dot" style="background:' + areaColor( i ) + ';flex-shrink:0;"></span>' +
				'<span class="oc-area-item-name">' + esc( area.label || ( 'Print Area ' + ( i + 1 ) ) ) + '</span>' +
				'<span class="oc-area-item-method">' + esc( methodLabel( area.method ) ) + '</span>' +
				'<div class="oc-layer-actions">' +
					'<button type="button" class="oc-layer-action-btn oc-layer-vis-btn'  + ( ! area.visible ? ' is-off' : '' ) + '" title="' + ( area.visible  ? 'Hide area'   : 'Show area'   ) + '">' + ( area.visible  ? ICO_EYE    : ICO_EYE_OFF ) + '</button>' +
					'<button type="button" class="oc-layer-action-btn oc-layer-lock-btn' + ( area.locked    ? ' is-on'  : '' ) + '" title="' + ( area.locked   ? 'Unlock area' : 'Lock area'   ) + '">' + ( area.locked   ? ICO_LOCK   : ICO_UNLOCK  ) + '</button>' +
					'<button type="button" class="oc-layer-action-btn oc-layer-delete-btn" title="Delete area">' + ICO_BIN + '</button>' +
				'</div>';

			item.addEventListener( 'click', e => {
				if ( e.target.closest( '.oc-layer-actions' ) ) return;
				if ( area.locked ) return; // locked areas can't be selected from list
				selectedIndex = i; selectedLayerIndex = -1; activeLayerTab = 'general';
				renderAll();
			} );
			item.querySelector( '.oc-layer-vis-btn'    ).addEventListener( 'click', e => { e.stopPropagation(); area.visible = ! area.visible; snapshot(); renderAll(); } );
			item.querySelector( '.oc-layer-lock-btn'   ).addEventListener( 'click', e => { e.stopPropagation(); area.locked = ! area.locked; snapshot(); renderAll(); } );
			item.querySelector( '.oc-layer-delete-btn' ).addEventListener( 'click', e => {
				e.stopPropagation();
				if ( ! confirm( 'Remove this print area and all its layers?' ) ) return;
				areas.splice( i, 1 );
				if ( selectedIndex === i )     selectedIndex = areas.length > 0 ? Math.min( i, areas.length - 1 ) : -1;
				else if ( selectedIndex > i )  selectedIndex--;
				selectedLayerIndex = -1;
				snapshot();
				renderAll();
			} );
			list.appendChild( item );
		} );
	}

	function renderLeftAreaProps() {
		const noSel = document.getElementById( 'oc-area-no-sel' );
		const inner = document.getElementById( 'oc-area-props-inner' );
		const area  = areas[ selectedIndex ];

		if ( ! area ) { if ( noSel ) noSel.style.display = ''; if ( inner ) inner.style.display = 'none'; return; }
		if ( noSel ) noSel.style.display = 'none';
		if ( inner ) inner.style.display = '';

		setVal( 'oc-prop-label',  area.label  );
		setVal( 'oc-prop-method', area.method );
		setVal( 'oc-prop-x', area.x );
		setVal( 'oc-prop-y', area.y );
		setVal( 'oc-prop-w', area.w );
		setVal( 'oc-prop-h', area.h );
		setVal( 'oc-prop-rotation', area.rotation );

		const thumb     = document.getElementById( 'oc-mockup-thumb-img' );
		const noThumb   = document.getElementById( 'oc-mockup-thumb-empty' );
		const removeBtn = document.getElementById( 'oc-remove-mockup-btn' );
		const chooseBtn = document.getElementById( 'oc-choose-mockup-btn' );
		const dot       = document.getElementById( 'oc-right-area-color' );

		if ( area.mockupUrl ) {
			if ( thumb ) { thumb.src = area.mockupUrl; thumb.style.display = ''; }
			if ( noThumb ) noThumb.style.display = 'none';
		} else {
			if ( thumb ) thumb.style.display = 'none';
			if ( noThumb ) noThumb.style.display = '';
		}
		if ( removeBtn ) removeBtn.style.display = area.mockupUrl ? '' : 'none';
		if ( chooseBtn ) chooseBtn.textContent   = area.mockupUrl ? 'Change Mockup' : 'Choose Mockup';
		if ( dot       ) dot.style.background    = areaColor( selectedIndex );
	}

	// ── Right column ───────────────────────────────────────────────────────────

	function renderRightColumn() {
		const area  = areas[ selectedIndex ];
		const layer = area && selectedLayerIndex >= 0 ? area.layers[ selectedLayerIndex ] : null;

		const hint = document.getElementById( 'oc-type-picker-hint' );
		if ( hint ) hint.style.display = area ? 'none' : '';
		document.querySelectorAll( '.oc-layer-type-btn' ).forEach( btn => {
			btn.disabled = ! area; btn.style.opacity = area ? '' : '.4';
		} );

		renderLayerList( area );

		const noSel = document.getElementById( 'oc-layer-no-sel' );
		const inner = document.getElementById( 'oc-layer-props-inner' );

		if ( ! layer ) {
			if ( noSel ) noSel.style.display = '';
			if ( inner ) inner.style.display = 'none';
		} else {
			if ( noSel ) noSel.style.display = 'none';
			if ( inner ) inner.style.display = '';
			renderLayerPanel( layer );
		}
	}

	function renderLayerList( area ) {
		const listEl  = document.getElementById( 'oc-layers-list' );
		const noArea  = document.getElementById( 'oc-layers-no-area' );
		const emptyEl = document.getElementById( 'oc-layers-empty' );
		const countEl = document.getElementById( 'oc-layers-count' );

		if ( ! listEl ) return;
		listEl.innerHTML = '';

		if ( ! area ) {
			if ( noArea  ) noArea.style.display  = '';
			if ( emptyEl ) emptyEl.style.display = 'none';
			if ( countEl ) countEl.textContent   = '';
			return;
		}
		if ( noArea ) noArea.style.display = 'none';
		const layers = area.layers || [];
		if ( countEl ) countEl.textContent = layers.length + ( 1 === layers.length ? ' layer' : ' layers' );

		if ( layers.length === 0 ) { if ( emptyEl ) emptyEl.style.display = ''; return; }
		if ( emptyEl ) emptyEl.style.display = 'none';

		layers.forEach( ( layer, li ) => {
			const item = document.createElement( 'div' );
			item.className = 'oc-layer-item' +
				( li === selectedLayerIndex ? ' oc-layer-item--active' : '' ) +
				( ! layer.visible            ? ' oc-layer--hidden'      : '' ) +
				( layer.locked               ? ' oc-layer--locked'       : '' );
			item.draggable = true;
			item.dataset.layerIndex = li;
			item.innerHTML =
				'<span class="oc-layer-drag-handle" title="Drag to reorder">\u22ee\u22ee</span>' +
				'<span class="oc-layer-icon" style="color:' + layerColor( layer.type ) + ';">' + esc( layerIcon( layer.type ) ) + '</span>' +
				'<span class="oc-layer-item-name">' + esc( layer.label || layerLabel( layer.type ) ) + '</span>' +
				'<div class="oc-layer-actions">' +
					'<button type="button" class="oc-layer-action-btn oc-layer-vis-btn'  + ( ! layer.visible ? ' is-off' : '' ) + '" title="' + ( layer.visible  ? 'Hide layer'   : 'Show layer'   ) + '">' + ( layer.visible  ? ICO_EYE  : ICO_EYE_OFF ) + '</button>' +
					'<button type="button" class="oc-layer-action-btn oc-layer-lock-btn' + ( layer.locked    ? ' is-on'  : '' ) + '" title="' + ( layer.locked   ? 'Unlock layer' : 'Lock layer'   ) + '">' + ( layer.locked   ? ICO_LOCK : ICO_UNLOCK  ) + '</button>' +
					'<button type="button" class="oc-layer-action-btn oc-layer-delete-btn" title="Delete layer">' + ICO_BIN + '</button>' +
				'</div>';

			item.addEventListener( 'click', e => {
				if ( e.target.closest( '.oc-layer-actions' ) || e.target.classList.contains( 'oc-layer-drag-handle' ) ) return;
				if ( layer.locked ) return; // locked layers can't be selected from list
				selectedLayerIndex = li; renderAll();
			} );
			item.querySelector( '.oc-layer-vis-btn'    ).addEventListener( 'click', e => { e.stopPropagation(); layer.visible = ! layer.visible; snapshot(); renderAll(); } );
			item.querySelector( '.oc-layer-lock-btn'   ).addEventListener( 'click', e => { e.stopPropagation(); layer.locked  = ! layer.locked;  snapshot(); renderAll(); } );
			item.querySelector( '.oc-layer-delete-btn' ).addEventListener( 'click', e => {
				e.stopPropagation();
				if ( ! confirm( 'Remove this layer?' ) ) return;
				area.layers.splice( li, 1 );
				if ( selectedLayerIndex === li )      selectedLayerIndex = -1;
				else if ( selectedLayerIndex > li )   selectedLayerIndex--;
				snapshot();
				renderAll();
			} );

			// Drag-to-reorder
			item.addEventListener( 'dragstart', e => {
				layerDragSrc = li;
				e.dataTransfer.effectAllowed = 'move';
				setTimeout( () => item.classList.add( 'oc-layer-item--dragging' ), 0 );
			} );
			item.addEventListener( 'dragend', () => {
				layerDragSrc = -1;
				listEl.querySelectorAll( '.oc-layer-item--dragging, .oc-layer-item--drag-over' ).forEach( el => {
					el.classList.remove( 'oc-layer-item--dragging', 'oc-layer-item--drag-over' );
				} );
			} );
			item.addEventListener( 'dragover', e => {
				e.preventDefault(); e.dataTransfer.dropEffect = 'move';
				if ( Number( item.dataset.layerIndex ) !== layerDragSrc ) {
					listEl.querySelectorAll( '.oc-layer-item--drag-over' ).forEach( el => el.classList.remove( 'oc-layer-item--drag-over' ) );
					item.classList.add( 'oc-layer-item--drag-over' );
				}
			} );
			item.addEventListener( 'drop', e => {
				e.preventDefault();
				const targetIdx = Number( item.dataset.layerIndex );
				if ( layerDragSrc < 0 || layerDragSrc === targetIdx ) return;
				const moved = area.layers.splice( layerDragSrc, 1 )[ 0 ];
				area.layers.splice( targetIdx, 0, moved );
				if ( selectedLayerIndex === layerDragSrc )                                                 selectedLayerIndex = targetIdx;
				else if ( layerDragSrc < targetIdx && selectedLayerIndex > layerDragSrc && selectedLayerIndex <= targetIdx ) selectedLayerIndex--;
				else if ( layerDragSrc > targetIdx && selectedLayerIndex >= targetIdx   && selectedLayerIndex < layerDragSrc ) selectedLayerIndex++;
				snapshot();
				renderAll();
			} );

			listEl.appendChild( item );
		} );
	}

	function renderLayerPanel( layer ) {
		const iconEl = document.getElementById( 'oc-layer-type-icon' );
		const lblEl  = document.getElementById( 'oc-layer-type-label' );
		const dotEl  = document.getElementById( 'oc-layer-color-dot' );
		if ( iconEl ) iconEl.textContent     = layerIcon( layer.type );
		if ( lblEl  ) lblEl.textContent      = layerLabel( layer.type );
		if ( dotEl  ) dotEl.style.background = layerColor( layer.type );

		const tabs = LAYER_TABS[ layer.type ] || LAYER_TABS.text;
		if ( ! tabs.some( t => t.id === activeLayerTab ) ) activeLayerTab = 'general';

		const settingsEl = document.getElementById( 'oc-layer-settings' );
		if ( ! settingsEl ) return;

		let html = '<div class="oc-layer-tabs-bar">';
		tabs.forEach( t => { html += '<button type="button" class="oc-layer-tab' + ( t.id === activeLayerTab ? ' oc-layer-tab--active' : '' ) + '" data-tab="' + t.id + '" title="' + esc( t.label ) + '" aria-label="' + esc( t.label ) + '"><span aria-hidden="true">' + esc( t.icon || t.label.charAt( 0 ) ) + '</span></button>'; } );
		html += '</div>';
		tabs.forEach( t => {
			html += '<div class="oc-layer-tab-panel' + ( t.id === activeLayerTab ? ' oc-layer-tab-panel--active' : '' ) + '" data-panel="' + t.id + '">';
			html += buildTabContent( t.id, layer );
			html += '</div>';
		} );
		settingsEl.innerHTML = html;

		settingsEl.querySelectorAll( '.oc-layer-tab' ).forEach( btn => {
			btn.addEventListener( 'click', () => {
				activeLayerTab = btn.dataset.tab;
				settingsEl.querySelectorAll( '.oc-layer-tab'       ).forEach( b => b.classList.toggle( 'oc-layer-tab--active',       b.dataset.tab   === activeLayerTab ) );
				settingsEl.querySelectorAll( '.oc-layer-tab-panel' ).forEach( p => p.classList.toggle( 'oc-layer-tab-panel--active', p.dataset.panel === activeLayerTab ) );
			} );
		} );
		bindSettingsHandlers( layer );
	}

	// ── Canvas ─────────────────────────────────────────────────────────────────

	function renderCanvas() {
		const stage    = document.getElementById( 'oc-canvas-stage' );
		const noMockup = document.getElementById( 'oc-canvas-no-mockup' );
		const coords   = document.getElementById( 'oc-canvas-coords' );
		const noMsg    = document.getElementById( 'oc-canvas-no-mockup-msg' );
		if ( ! stage ) return;

		const area = areas[ selectedIndex ];
		if ( ! area || ! area.mockupUrl ) {
			stage.style.display    = 'none';
			noMockup.style.display = '';
			if ( coords ) coords.style.display = 'none';
			if ( noMsg ) noMsg.textContent = areas.length === 0 ? 'Click \u201c+ Add\u201d on the left to create a print area.' : 'Select a print area and choose its mockup image.';
			return;
		}

		noMockup.style.display = 'none';
		stage.style.display    = '';
		if ( coords ) coords.style.display = '';

		const img = document.getElementById( 'oc-canvas-mockup-img' );
		if ( ! img ) return;

		if ( img.getAttribute( 'src' ) !== area.mockupUrl ) {
			img.src = area.mockupUrl;
			img.onload = () => { updateBoundsBox(); renderGhosts(); };
		} else {
			updateBoundsBox(); renderGhosts();
		}

		const entity = selectedLayerIndex >= 0 ? ( area.layers[ selectedLayerIndex ] || area ) : area;
		updateCoordsReadout( entity );
	}

	function updateBoundsBox() {
		const box  = document.getElementById( 'oc-bounds-box' );
		const img  = document.getElementById( 'oc-canvas-mockup-img' );
		const area = areas[ selectedIndex ];
		if ( ! box || ! img || ! area ) { if ( box ) box.style.display = 'none'; return; }

		const scale  = getScale( img );
		if ( ! scale ) return;

		const layer  = selectedLayerIndex >= 0 ? area.layers[ selectedLayerIndex ] : null;
		const entity = layer || area;
		const color  = layer ? layerColor( layer.type ) : areaColor( selectedIndex );

		const isHidden = layer ? ! layer.visible : ! area.visible;
		const isLocked = layer ? layer.locked    : area.locked;

		// Hide bounds box entirely when the area (not a layer) is locked, so the
		// locked area doesn't look "selected" on canvas while mockup + ghosts still render.
		const hideForLocked = ! layer && area.locked;
		box.style.display = ( isHidden || hideForLocked ) ? 'none' : '';
		box.style.opacity = '';
		pos( box, entity, scale, layer ? normaliseRotation( area.rotation ) : normaliseRotation( entity.rotation ), layer ? area : null );
		box.style.borderColor = color;
		box.style.background  = hexRgba( color, 0.12 );
		box.classList.toggle( 'oc-bounds-box--locked', isLocked );
		box.classList.toggle( 'oc-bounds-box--rotatable', ! layer );
		box.querySelectorAll( '.oc-bounds-handle' ).forEach( h => { h.style.borderColor = color; } );
		box.querySelectorAll( '.oc-bounds-rotate-handle' ).forEach( h => {
			h.style.borderColor = color;
			h.style.color = color;
			h.style.display = layer ? 'none' : '';
		} );

		// Layer content preview inside bounds box
		box.querySelectorAll( '.oc-bounds-box-pill' ).forEach( el => el.remove() );
		const renderedH = Math.round( entity.h * scale );
		applyLayerPreview( layer, box, renderedH, false );

		if ( layer ) {
			const pill = document.createElement( 'div' );
			pill.className        = 'oc-bounds-box-pill';
			pill.style.background = color;
			pill.textContent      = layerIcon( layer.type ) + ' ' + layerLabel( layer.type );
			box.appendChild( pill );
		}
	}

	function renderGhosts() {
		const ghosts = document.getElementById( 'oc-canvas-ghosts' );
		const img    = document.getElementById( 'oc-canvas-mockup-img' );
		if ( ! ghosts || ! img ) return;

		ghosts.innerHTML = '';
		const scale        = getScale( img );
		if ( ! scale ) return;

		const area         = areas[ selectedIndex ];
		const activeMockup = area ? area.mockupUrl : '';

		areas.forEach( ( a, i ) => {
			if ( i === selectedIndex || a.mockupUrl !== activeMockup || ! activeMockup || ! a.visible ) return;
			const g = ghost( a, areaColor( i ), 0.06 );
			g.appendChild( ghostLabel( a.label || ( 'Area ' + ( i + 1 ) ), areaColor( i ) ) );
			pos( g, a, scale, normaliseRotation( a.rotation ) );
			ghosts.appendChild( g );
		} );

		if ( ! area ) return;

		if ( selectedLayerIndex >= 0 ) {
			const outline = document.createElement( 'div' );
			outline.className = 'oc-canvas-area-outline';
			pos( outline, area, scale, normaliseRotation( area.rotation ) );
			ghosts.appendChild( outline );
		}

		( area.layers || [] ).forEach( ( layer, li ) => {
			if ( li === selectedLayerIndex || ! layer.visible ) return;
			const g = ghost( layer, layerColor( layer.type ), 0.1 );
			g.classList.add( 'oc-canvas-layer-ghost' );
			g.appendChild( ghostLabel( layerIcon( layer.type ) + ' ' + ( layer.label || layerLabel( layer.type ) ), layerColor( layer.type ) ) );
			applyLayerPreview( layer, g, Math.round( layer.h * scale ), true );

			pos( g, layer, scale, normaliseRotation( area.rotation ), area );

			if ( layer.locked ) {
				g.style.cursor  = 'not-allowed';
				g.style.opacity = '0.5';
			} else {
				g.style.cursor = 'pointer';
				g.addEventListener( 'click', () => { selectedLayerIndex = li; renderAll(); } );
			}
			ghosts.appendChild( g );
		} );
	}

	function ghost( entity, color, bgAlpha ) {
		const g = document.createElement( 'div' );
		g.className = 'oc-canvas-ghost';
		g.style.borderColor = color;
		g.style.background  = hexRgba( color, bgAlpha );
		return g;
	}
	function ghostLabel( text, color ) {
		const l = document.createElement( 'span' );
		l.className = 'oc-canvas-ghost-label';
		l.textContent = text;
		l.style.color = color;
		return l;
	}
	function pos( el, entity, scale, rotation = 0, area = null ) {
		let cx = entity.x + entity.w / 2;
		let cy = entity.y + entity.h / 2;
		if ( area && rotation ) {
			const acx = area.x + area.w / 2;
			const acy = area.y + area.h / 2;
			const rad = rotation * Math.PI / 180;
			const dx  = cx - acx;
			const dy  = cy - acy;
			cx = acx + dx * Math.cos( rad ) - dy * Math.sin( rad );
			cy = acy + dx * Math.sin( rad ) + dy * Math.cos( rad );
		}
		el.style.left   = Math.round( ( cx - entity.w / 2 ) * scale ) + 'px';
		el.style.top    = Math.round( ( cy - entity.h / 2 ) * scale ) + 'px';
		el.style.width  = Math.round( entity.w * scale ) + 'px';
		el.style.height = Math.round( entity.h * scale ) + 'px';
		el.style.transform = rotation ? 'rotate(' + rotation + 'deg)' : '';
		el.style.transformOrigin = 'center center';
	}

	function updateCoordsReadout( entity ) {
		const el = document.getElementById( 'oc-coords-text' );
		if ( el && entity ) el.textContent = 'X\u2009' + entity.x + '\u2002 Y\u2009' + entity.y + '\u2002 W\u2009' + entity.w + '\u2002 H\u2009' + entity.h + ( entity.rotation ? '\u2002 R\u2009' + entity.rotation + '\u00b0' : '' );
	}

	// ── Hidden fields ──────────────────────────────────────────────────────────

	function renderHiddenFields() {
		const c = document.getElementById( 'oc-hidden-fields' );
		if ( ! c ) return;
		let html = '';

		areas.forEach( ( area, i ) => {
			const p = 'oc_design_areas[' + i + ']';
			html +=
				'<input type="hidden" name="' + p + '[id]"                   value="' + esc( area.id      ) + '">' +
				'<input type="hidden" name="' + p + '[label]"                value="' + esc( area.label    ) + '">' +
				'<input type="hidden" name="' + p + '[print_method]"         value="' + esc( area.method   ) + '">' +
				'<input type="hidden" name="' + p + '[mockup_attachment_id]" value="' + esc( area.mockupId ) + '">' +
				'<input type="hidden" name="' + p + '[canvas_x]"             value="' + esc( area.x        ) + '">' +
				'<input type="hidden" name="' + p + '[canvas_y]"             value="' + esc( area.y        ) + '">' +
				'<input type="hidden" name="' + p + '[canvas_w]"             value="' + esc( area.w        ) + '">' +
				'<input type="hidden" name="' + p + '[canvas_h]"             value="' + esc( area.h        ) + '">' +
				'<input type="hidden" name="' + p + '[canvas_rotation]"      value="' + esc( area.rotation ) + '">' +
				'<input type="hidden" name="' + p + '[sort_order]"           value="' + esc( i                        ) + '">' +
				'<input type="hidden" name="' + p + '[visible]"              value="' + esc( area.visible ? '1' : '0' ) + '">' +
				'<input type="hidden" name="' + p + '[locked]"               value="' + esc( area.locked  ? '1' : '0' ) + '">';
		} );

		let li = 0;
		areas.forEach( ( area, areaIdx ) => {
			( area.layers || [] ).forEach( ( layer, sort ) => {
				const p = 'oc_layers[' + li + ']';
				html +=
					'<input type="hidden" name="' + p + '[id]"         value="' + esc( layer.id ) + '">' +
					'<input type="hidden" name="' + p + '[area_index]" value="' + esc( areaIdx  ) + '">' +
					'<input type="hidden" name="' + p + '[type]"       value="' + esc( layer.type  ) + '">' +
					'<input type="hidden" name="' + p + '[label]"      value="' + esc( layer.label ) + '">' +
					'<input type="hidden" name="' + p + '[x]"          value="' + esc( layer.x     ) + '">' +
					'<input type="hidden" name="' + p + '[y]"          value="' + esc( layer.y     ) + '">' +
					'<input type="hidden" name="' + p + '[w]"          value="' + esc( layer.w     ) + '">' +
					'<input type="hidden" name="' + p + '[h]"          value="' + esc( layer.h     ) + '">' +
					'<input type="hidden" name="' + p + '[sort_order]" value="' + esc( sort                        ) + '">' +
					'<input type="hidden" name="' + p + '[visible]"    value="' + esc( layer.visible ? '1' : '0' ) + '">' +
					'<input type="hidden" name="' + p + '[locked]"     value="' + esc( layer.locked  ? '1' : '0' ) + '">' +
					'<input type="hidden" name="' + p + '[settings]"   value="' + esc( JSON.stringify( layer.settings || {} ) ) + '">';
				li++;
			} );
		} );

		c.innerHTML = html;
	}

	// ── Interactions ───────────────────────────────────────────────────────────

	function initInteractions() {
		// Add area
		document.getElementById( 'oc-add-area-btn' )?.addEventListener( 'click', () => {
			areas.push( { ...normaliseArea( { id: 0, label: 'Print Area ' + ( areas.length + 1 ), visible: true, locked: false }, areas.length ), layers: [] } );
			selectedIndex = areas.length - 1; selectedLayerIndex = -1;
			snapshot(); renderAll();
		} );

		// Type picker buttons → add layer
		document.querySelectorAll( '.oc-layer-type-btn' ).forEach( btn => {
			btn.addEventListener( 'click', () => { if ( selectedIndex >= 0 && btn.dataset.type ) addLayer( btn.dataset.type ); } );
		} );

		// Area prop inputs
		document.getElementById( 'oc-prop-label' )?.addEventListener( 'input', () => {
			const area = areas[ selectedIndex ];
			if ( area ) { area.label = document.getElementById( 'oc-prop-label' ).value; renderAreasList(); renderAreaStrip(); renderHiddenFields(); markDirty(); }
		} );
		document.getElementById( 'oc-prop-method' )?.addEventListener( 'change', () => {
			const area = areas[ selectedIndex ];
			if ( area ) {
				area.method = document.getElementById( 'oc-prop-method' ).value;
				// Re-render everything so layer panels reflect method-dependent UI (e.g. hide colour picks under engraving).
				renderAll();
				markDirty();
			}
		} );
		[ 'oc-prop-x', 'oc-prop-y', 'oc-prop-w', 'oc-prop-h', 'oc-prop-rotation' ].forEach( id => {
			document.getElementById( id )?.addEventListener( 'input', () => { syncBoundsFromInputs(); markDirty(); } );
		} );
		[ 'oc-layer-x', 'oc-layer-y', 'oc-layer-w', 'oc-layer-h' ].forEach( id => {
			document.getElementById( id )?.addEventListener( 'input', syncBoundsFromInputs );
		} );

		// Mockup
		document.getElementById( 'oc-choose-mockup-btn' )?.addEventListener( 'click', openMockupPicker );
		document.getElementById( 'oc-remove-mockup-btn' )?.addEventListener( 'click', () => {
			const area = areas[ selectedIndex ];
			if ( area ) { area.mockupId = 0; area.mockupUrl = ''; renderAll(); markDirty(); }
		} );

		// Undo/Redo buttons
		document.getElementById( 'oc-undo-btn' )?.addEventListener( 'click', undo );
		document.getElementById( 'oc-redo-btn' )?.addEventListener( 'click', redo );

		// Canvas background click → deselect layer
		document.getElementById( 'oc-canvas-stage' )?.addEventListener( 'click', e => {
			if ( e.target === document.getElementById( 'oc-canvas-stage' ) ||
			     e.target === document.getElementById( 'oc-canvas-mockup-img' ) ||
			     e.target === document.getElementById( 'oc-canvas-ghosts' ) ) {
				if ( selectedLayerIndex >= 0 ) { selectedLayerIndex = -1; renderAll(); }
			}
		} );

		// Keyboard shortcuts
		document.addEventListener( 'keydown', e => {
			const tag = document.activeElement?.tagName?.toLowerCase();
			if ( tag === 'input' || tag === 'textarea' || tag === 'select' ) return;
			if ( e.ctrlKey || e.metaKey ) {
				if ( e.key === 'z' && ! e.shiftKey ) { e.preventDefault(); undo(); }
				if ( e.key === 'z' &&   e.shiftKey ) { e.preventDefault(); redo(); }
				if ( e.key === 'y'                 ) { e.preventDefault(); redo(); }
			}
		} );

		window.addEventListener( 'resize', () => { if ( selectedIndex >= 0 ) { updateBoundsBox(); renderGhosts(); } } );
		document.getElementById( 'oc-canvas-mockup-img' )?.addEventListener( 'load', () => { updateBoundsBox(); renderGhosts(); } );

		initCanvasInteractions();
	}

	function addLayer( type ) {
		const area = areas[ selectedIndex ];
		if ( ! area ) return;
		const def = LAYER_DEFAULTS[ type ] || { w: 200, h: 100 };
		const lx  = area.x + Math.max( 0, Math.round( ( area.w - def.w ) / 2 ) );
		const ly  = area.y + Math.max( 0, Math.round( ( area.h - def.h ) / 2 ) );
		const layer = { _uid: ++uidCounter, id: 0, type, label: layerLabel( type ) + ' ' + ( area.layers.length + 1 ), x: lx, y: ly, w: def.w, h: def.h, visible: true, locked: false, settings: defaultSettings( type ), sortOrder: area.layers.length };
		clampLayerToArea( layer, area );
		area.layers.push( layer );
		selectedLayerIndex = area.layers.length - 1;
		snapshot();
		renderAll();
	}

	// ── Feature 2: Draw-to-create ─────────────────────────────────────────────

	function initCanvasInteractions() {
		const box = document.getElementById( 'oc-bounds-box' );
		if ( box ) {
			box.addEventListener( 'mousedown', e => {
				if ( e.target !== box ) return;
				e.preventDefault(); e.stopPropagation();
				startDrag( e, 'move', '' );
			} );
			box.querySelectorAll( '.oc-bounds-handle' ).forEach( h => {
				h.addEventListener( 'mousedown', e => {
					e.preventDefault(); e.stopPropagation();
					startDrag( e, 'resize', h.dataset.dir );
				} );
			} );
			box.querySelectorAll( '.oc-bounds-rotate-handle' ).forEach( h => {
				h.addEventListener( 'mousedown', e => {
					e.preventDefault(); e.stopPropagation();
					startDrag( e, 'rotate', '' );
				} );
			} );
		}

		// Draw-to-create: mousedown on the image itself (not covered by bounds/ghosts)
		document.getElementById( 'oc-canvas-mockup-img' )?.addEventListener( 'mousedown', e => {
			const area = areas[ selectedIndex ];
			if ( ! area || area.locked || selectedLayerIndex >= 0 ) return;
			e.preventDefault();
			startDrawRect( e );
		} );

		document.addEventListener( 'mousemove', e => {
			if ( drag )      { onDragMove( e ); return; }
			if ( drawState ) { onDrawMove( e ); }
		} );
		document.addEventListener( 'mouseup', e => {
			if ( drag )      { onDragEnd();     return; }
			if ( drawState ) { onDrawEnd( e ); }
		} );
	}

	function startDrawRect( e ) {
		const img = document.getElementById( 'oc-canvas-mockup-img' );
		if ( ! img ) return;
		const area = areas[ selectedIndex ];
		if ( ! area ) return;
		const scale = getScale( img );
		if ( ! scale ) return;
		const rect = img.getBoundingClientRect();
		const sx   = clamp( Math.round( ( e.clientX - rect.left ) / scale ), area.x, area.x + area.w );
		const sy   = clamp( Math.round( ( e.clientY - rect.top  ) / scale ), area.y, area.y + area.h );
		drawState = { startX: sx, startY: sy, curX: sx, curY: sy, startClientX: e.clientX, startClientY: e.clientY };

		drawEl = document.createElement( 'div' );
		drawEl.className = 'oc-canvas-draw-preview';
		document.getElementById( 'oc-canvas-stage' )?.appendChild( drawEl );
		updateDrawEl();
	}

	function onDrawMove( e ) {
		if ( ! drawState ) return;
		const img = document.getElementById( 'oc-canvas-mockup-img' );
		if ( ! img ) return;
		const area = areas[ selectedIndex ];
		if ( ! area ) return;
		const scale = getScale( img );
		const rect  = img.getBoundingClientRect();
		drawState.curX = clamp( Math.round( ( e.clientX - rect.left ) / scale ), area.x, area.x + area.w );
		drawState.curY = clamp( Math.round( ( e.clientY - rect.top  ) / scale ), area.y, area.y + area.h );
		updateDrawEl();
	}

	function updateDrawEl() {
		if ( ! drawEl || ! drawState ) return;
		const img = document.getElementById( 'oc-canvas-mockup-img' );
		if ( ! img ) return;
		const scale = getScale( img );
		const x = Math.min( drawState.startX, drawState.curX );
		const y = Math.min( drawState.startY, drawState.curY );
		const w = Math.abs( drawState.curX - drawState.startX );
		const h = Math.abs( drawState.curY - drawState.startY );
		drawEl.style.left   = Math.round( x * scale ) + 'px';
		drawEl.style.top    = Math.round( y * scale ) + 'px';
		drawEl.style.width  = Math.round( w * scale ) + 'px';
		drawEl.style.height = Math.round( h * scale ) + 'px';
	}

	function onDrawEnd( e ) {
		if ( ! drawState ) return;
		const state = drawState;
		drawState   = null;
		if ( drawEl ) { drawEl.remove(); drawEl = null; }

		const x = Math.min( state.startX, state.curX );
		const y = Math.min( state.startY, state.curY );
		const w = Math.abs( state.curX - state.startX );
		const h = Math.abs( state.curY - state.startY );

		if ( w < 10 || h < 10 ) return; // too small — treat as click miss

		// Show floating type picker
		showDrawTypePicker( x, y, w, h, e.clientX, e.clientY );
	}

	function showDrawTypePicker( natX, natY, natW, natH, clientX, clientY ) {
		closeDrawTypePicker();

		const backdrop = document.createElement( 'div' );
		backdrop.className = 'oc-draw-popup-backdrop';

		const popup = document.createElement( 'div' );
		popup.className = 'oc-draw-type-popup';
		popup.id = 'oc-draw-type-popup';

		Object.keys( LAYER_TYPES ).forEach( type => {
			const btn = document.createElement( 'button' );
			btn.type = 'button';
			btn.className = 'oc-draw-type-btn';
			btn.innerHTML = '<span style="font-size:18px;color:' + layerColor( type ) + ';">' + layerIcon( type ) + '</span><span>' + layerLabel( type ) + '</span>';
			btn.addEventListener( 'click', e => {
				e.stopPropagation();
				addLayerAt( type, natX, natY, natW, natH );
				closeDrawTypePicker();
			} );
			popup.appendChild( btn );
		} );

		// Position near mouse, keep on screen
		document.body.appendChild( backdrop );
		document.body.appendChild( popup );
		drawPopup = { popup, backdrop };

		// Position after appending so we know the size
		requestAnimationFrame( () => {
			const pw = popup.offsetWidth, ph = popup.offsetHeight;
			const vw = window.innerWidth, vh = window.innerHeight;
			let left = clientX + 8;
			let top  = clientY + 8;
			if ( left + pw > vw - 8 ) left = clientX - pw - 8;
			if ( top  + ph > vh - 8 ) top  = clientY - ph - 8;
			popup.style.left = Math.max( 8, left ) + 'px';
			popup.style.top  = Math.max( 8, top  ) + 'px';
		} );

		// Close on backdrop click or Escape
		backdrop.addEventListener( 'click', closeDrawTypePicker );
		document.addEventListener( 'keydown', onDrawPickerKey );
	}

	function onDrawPickerKey( e ) {
		if ( e.key === 'Escape' ) { e.preventDefault(); closeDrawTypePicker(); }
	}

	function closeDrawTypePicker() {
		if ( drawPopup ) {
			drawPopup.popup.remove();
			drawPopup.backdrop.remove();
			drawPopup = null;
		}
		document.removeEventListener( 'keydown', onDrawPickerKey );
	}

	function addLayerAt( type, x, y, w, h ) {
		const area = areas[ selectedIndex ];
		if ( ! area ) return;
		const layer = { _uid: ++uidCounter, id: 0, type, label: layerLabel( type ) + ' ' + ( area.layers.length + 1 ), x, y, w, h, visible: true, locked: false, settings: defaultSettings( type ), sortOrder: area.layers.length };
		clampLayerToArea( layer, area );
		area.layers.push( layer );
		selectedLayerIndex = area.layers.length - 1;
		snapshot();
		renderAll();
	}

	// ── Canvas drag/resize ─────────────────────────────────────────────────────

	function activeEntity() {
		const area = areas[ selectedIndex ];
		if ( ! area ) return null;
		const layer = selectedLayerIndex >= 0 ? area.layers[ selectedLayerIndex ] : null;
		return layer || area;
	}

	function startDrag( e, type, dir ) {
		const entity = activeEntity();
		if ( ! entity ) return;
		const area  = areas[ selectedIndex ];
		const layer = area && selectedLayerIndex >= 0 ? area.layers[ selectedLayerIndex ] : null;
		if ( layer ? layer.locked : area && area.locked ) return;
		drag = { type, dir, startClientX: e.clientX, startClientY: e.clientY, startX: entity.x, startY: entity.y, startW: entity.w, startH: entity.h, startRotation: normaliseRotation( entity.rotation ) };
	}

	function onDragMove( e ) {
		if ( ! drag ) return;
		const entity = activeEntity();
		const img    = document.getElementById( 'oc-canvas-mockup-img' );
		const area   = areas[ selectedIndex ];
		const layer  = area && selectedLayerIndex >= 0 ? area.layers[ selectedLayerIndex ] : null;
		if ( ! entity || ! img ) return;

		const scale = getScale( img );
		if ( ! scale ) return;

		if ( drag.type === 'rotate' ) {
			const rect = img.getBoundingClientRect();
			const cx   = rect.left + ( area.x + area.w / 2 ) * scale;
			const cy   = rect.top + ( area.y + area.h / 2 ) * scale;
			entity.rotation = normaliseRotation( Math.atan2( e.clientY - cy, e.clientX - cx ) * 180 / Math.PI + 90 );
			updateBoundsBox(); renderGhosts(); updateCoordsReadout( entity );
			syncRightBounds( entity );
			renderHiddenFields();
			return;
		}

		const dx   = Math.round( ( e.clientX - drag.startClientX ) / scale );
		const dy   = Math.round( ( e.clientY - drag.startClientY ) / scale );
		const natW = img.naturalWidth  || 2000;
		const natH = img.naturalHeight || 2000;
		const d    = drag.dir;

		const minX = layer ? area.x : 0;
		const minY = layer ? area.y : 0;
		const maxX = layer ? area.x + area.w : natW;
		const maxY = layer ? area.y + area.h : natH;

		if ( drag.type === 'move' ) {
			entity.x = clamp( drag.startX + dx, minX, maxX - entity.w );
			entity.y = clamp( drag.startY + dy, minY, maxY - entity.h );
		} else {
			let nx = drag.startX, ny = drag.startY, nw = drag.startW, nh = drag.startH;
			if ( d.includes( 'e' ) ) { nw = Math.max( 10, drag.startW + dx ); }
			if ( d.includes( 's' ) ) { nh = Math.max( 10, drag.startH + dy ); }
			if ( d.includes( 'w' ) ) { nw = Math.max( 10, drag.startW - dx ); nx = drag.startX + drag.startW - nw; }
			if ( d.includes( 'n' ) ) { nh = Math.max( 10, drag.startH - dy ); ny = drag.startY + drag.startH - nh; }
			entity.x = clamp( nx, minX, maxX );
			entity.y = clamp( ny, minY, maxY );
			entity.w = Math.min( nw, maxX - entity.x );
			entity.h = Math.min( nh, maxY - entity.y );
		}

		updateBoundsBox(); renderGhosts(); updateCoordsReadout( entity );
		syncRightBounds( entity );
		renderHiddenFields();
	}

	function onDragEnd() {
		if ( drag ) snapshot(); // snapshot after every move/resize
		drag = null;
	}

	function syncBoundsFromInputs() {
		const area   = areas[ selectedIndex ];
		if ( ! area ) return;
		const layer  = selectedLayerIndex >= 0 ? area.layers[ selectedLayerIndex ] : null;
		const entity = layer || area;
		const prefix = layer ? 'oc-layer' : 'oc-prop';

		entity.x = parseInt( document.getElementById( prefix + '-x' )?.value || 0, 10 );
		entity.y = parseInt( document.getElementById( prefix + '-y' )?.value || 0, 10 );
		entity.w = Math.max( 1, parseInt( document.getElementById( prefix + '-w' )?.value || 1, 10 ) );
		entity.h = Math.max( 1, parseInt( document.getElementById( prefix + '-h' )?.value || 1, 10 ) );
		if ( ! layer ) entity.rotation = normaliseRotation( document.getElementById( 'oc-prop-rotation' )?.value || 0 );
		if ( layer ) clampLayerToArea( layer, area );
		updateBoundsBox(); renderGhosts(); updateCoordsReadout( entity ); renderHiddenFields(); markDirty();
	}

	function syncRightBounds( entity ) {
		const area  = areas[ selectedIndex ];
		const layer = area && selectedLayerIndex >= 0 ? area.layers[ selectedLayerIndex ] : null;
		const prefix = ( entity === layer ) ? 'oc-layer' : 'oc-prop';
		setVal( prefix + '-x', entity.x );
		setVal( prefix + '-y', entity.y );
		setVal( prefix + '-w', entity.w );
		setVal( prefix + '-h', entity.h );
		if ( entity === area ) setVal( 'oc-prop-rotation', normaliseRotation( entity.rotation ) );
	}

	function openMockupPicker() {
		if ( selectedIndex < 0 ) return;
		if ( ! window.wp || ! window.wp.media ) {
			alert( 'Media library is not available.' );
			return;
		}
		if ( ! mediaFrame ) {
			const data = window.ocProductsData || {};
			mediaFrame = wp.media( { title: data.mediaTitle || 'Select Mockup Image', button: { text: data.mediaBtn || 'Use as Mockup' }, library: { type: 'image' }, multiple: false } );
			mediaFrame.on( 'select', () => {
				const att  = mediaFrame.state().get( 'selection' ).first().toJSON();
				const area = areas[ selectedIndex ];
				if ( area ) {
					area.mockupId  = att.id;
					area.mockupUrl = ( att.sizes && att.sizes.large && att.sizes.large.url ) || att.url;
					renderAll();
					markDirty();
				}
			} );
		}
		mediaFrame.open();
	}

	// ── Boot ───────────────────────────────────────────────────────────────────

	document.addEventListener( 'DOMContentLoaded', init );

} )();
