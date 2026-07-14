/* eslint-disable no-console, no-alert, no-undef, no-shadow, no-unused-vars, @wordpress/no-unused-vars-before-return */
import {
	displayEntity,
	normaliseDpi,
	normaliseUnit,
	unitPxScale,
} from '../shared/render-math';
import { createProductsPageSettings } from './products-page-settings';
import {
	AREA_COLORS,
	ICO_BIN,
	ICO_EYE,
	ICO_EYE_OFF,
	ICO_LOCK,
	ICO_UNLOCK,
	LAYER_DEFAULTS,
	LAYER_TABS,
	LAYER_TYPES,
	areaColor,
	layerColor,
	layerIcon,
	layerLabel,
} from './products-page-metadata';
import { createLayerPreviewRenderer } from './products-page-preview';
import { renderProductsPageHiddenFields } from './products-page-hidden-fields';
import { createProductsPageDataNormalisers } from './products-page-data';
import { createMockupPicker } from './products-page-mockup-picker';
( function () {
	'use strict';
	let areas = [];
	let selectedIndex = -1;
	let selectedLayerIndex = -1;
	let activeLayerTab = 'general';
	let uidCounter = 0;
	let layerDragSrc = -1;
	const HISTORY_MAX = 25;
	let history = [];
	let historyIndex = -1;
	let isDirty = false;
	let autosaveTimer = null;
	let lastSavedTime = null;
	let autosaveError = '';
	let designId = 0;
	const autosaveInterval = 30000;
	function snapshot() {
		if ( historyIndex < history.length - 1 ) {
			history = history.slice( 0, historyIndex + 1 );
		}
		history.push( JSON.stringify( areas ) );
		if ( history.length > HISTORY_MAX ) {
			history.shift();
		}
		historyIndex = history.length - 1;
		updateUndoRedoBtns();
		markDirty();
	}
	function undo() {
		if ( historyIndex <= 0 ) {
			return;
		}
		historyIndex--;
		restoreHistory();
	}
	function redo() {
		if ( historyIndex >= history.length - 1 ) {
			return;
		}
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
		areas.forEach( ( a ) => {
			uidCounter = Math.max( uidCounter, a._uid || 0 );
			( a.layers || [] ).forEach( ( l ) => {
				uidCounter = Math.max( uidCounter, l._uid || 0 );
			} );
		} );
		if ( selectedIndex >= areas.length ) {
			selectedIndex = areas.length - 1;
		}
		const area = areas[ selectedIndex ];
		if ( ! area || selectedLayerIndex >= ( area.layers || [] ).length ) {
			selectedLayerIndex = -1;
		}
		renderAll();
		updateUndoRedoBtns();
	}
	function updateUndoRedoBtns() {
		const u = document.getElementById( 'oc-undo-btn' );
		const r = document.getElementById( 'oc-redo-btn' );
		if ( u ) {
			u.disabled = historyIndex <= 0;
		}
		if ( r ) {
			r.disabled = historyIndex >= history.length - 1;
		}
	}
	function markDirty() {
		isDirty = true;
		updateAutosaveIndicator();
	}
	function updateAutosaveIndicator() {
		const el = document.getElementById( 'oc-autosave-indicator' );
		if ( ! el ) {
			return;
		}
		if ( autosaveError ) {
			el.textContent = autosaveError;
			el.className = 'oc-autosave-indicator oc-autosave-indicator--error';
		} else if ( isDirty ) {
			el.textContent = 'Unsaved changes';
			el.className = 'oc-autosave-indicator oc-autosave-indicator--dirty';
		} else if ( lastSavedTime ) {
			const diff = Math.round( ( Date.now() - lastSavedTime ) / 1000 );
			const label =
				diff < 60 ? diff + 's ago' : Math.floor( diff / 60 ) + 'm ago';
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
					id: a.id,
					label: a.label,
					method: a.method,
					material: a.material,
					mockupId: a.mockupId,
					mockupUrl: a.mockupUrl,
					x: a.x,
					y: a.y,
					w: a.w,
					h: a.h,
					dpi: a.dpi,
					ratioLocked: a.ratioLocked,
					aspectRatio: a.aspectRatio,
					rotation: a.rotation,
					sortOrder: a.sortOrder,
					visible: a.visible,
					locked: a.locked,
					layers: ( a.layers || [] ).map( function ( l ) {
						return {
							id: l.id,
							type: l.type,
							label: l.label,
							x: l.x,
							y: l.y,
							w: l.w,
							h: l.h,
							sortOrder: l.sortOrder,
							visible: l.visible,
							locked: l.locked,
							settings: l.settings || {},
						};
					} ),
				};
			} ),
		};
	}
	function applyAutosavedState( savedState ) {
		const layersByAreaId = {};
		( savedState.areas || [] ).forEach( function ( a ) {
			( a.layers || [] ).forEach( function ( l ) {
				const aid = Number( l.areaId || 0 );
				if ( ! layersByAreaId[ aid ] ) {
					layersByAreaId[ aid ] = [];
				}
				layersByAreaId[ aid ].push( normaliseLayer( l ) );
			} );
		} );
		areas = ( savedState.areas || [] ).map( function ( a, i ) {
			return Object.assign( normaliseArea( a, i ), {
				layers: layersByAreaId[ Number( a.id ) ] || [],
			} );
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
		if ( ! isDirty || ! designId ) {
			return;
		}
		const state = collectState();
		const body = new URLSearchParams( {
			action: 'oc_autosave_design',
			nonce: ocProductsData.nonce,
			design_id: designId,
			state: JSON.stringify( state ),
		} );
		fetch( ocProductsData.ajaxUrl, { method: 'POST', body } )
			.then( function ( r ) {
				if ( ! r.ok ) {
					throw new Error( 'HTTP ' + r.status );
				}
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
		if ( autosaveTimer ) {
			clearInterval( autosaveTimer );
		}
		autosaveTimer = setInterval( doAutosave, autosaveInterval );
	}
	function stopAutosavePoll() {
		if ( autosaveTimer ) {
			clearInterval( autosaveTimer );
			autosaveTimer = null;
		}
	}
	function init() {
		const data = window.ocProductsData || {};
		designId = Number( data.designId || 0 );
		if ( designId > 0 ) {
			const body = new URLSearchParams( {
				action: 'oc_restore_autosave',
				nonce: data.nonce,
				design_id: designId,
			} );
			fetch( data.ajaxUrl, { method: 'POST', body } )
				.then( function ( r ) {
					if ( ! r.ok ) {
						throw new Error( 'HTTP ' + r.status );
					}
					return r.json();
				} )
				.then( function ( json ) {
					if ( json.success && json.data && json.data.state ) {
						const ts = json.data.timestamp || 0;
						const diff = Math.round(
							( Date.now() - ts * 1000 ) / 1000
						);
						const mins = Math.max( 1, Math.floor( diff / 60 ) );
						const msg =
							'You have unsaved changes from ' +
							mins +
							' minute' +
							( mins > 1 ? 's' : '' ) +
							' ago. Restore?';
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
		( data.layers || [] ).forEach( ( l ) => {
			const aid = Number( l.areaId );
			if ( ! layersByAreaId[ aid ] ) {
				layersByAreaId[ aid ] = [];
			}
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
		document
			.getElementById( 'oc-design-form' )
			?.addEventListener( 'submit', () => {
				renderHiddenFields();
				stopAutosavePoll();
			} );
	}
	const {
		normaliseArea,
		normaliseLayer,
		defaultSettings,
		normaliseSettings,
	} = createProductsPageDataNormalisers( {
		nextUid: () => ++uidCounter,
		normaliseAspectRatio,
		normaliseDpi,
		normaliseRotation,
	} );
	function selectedArea() {
		return areas[ selectedIndex ] || null;
	}
	function selectedLayer() {
		const area = selectedArea();
		return area && selectedLayerIndex >= 0
			? area.layers[ selectedLayerIndex ] || null
			: null;
	}
	const applyLayerPreview = createLayerPreviewRenderer( {
		fontLimit,
		layerLabel,
		normaliseHex,
	} );
	const openMockupPicker = createMockupPicker( {
		commitChange,
		getSelectedIndex: () => selectedIndex,
		selectedArea,
	} );
	const { initInteractions, addLayerWithBounds } =
		createProductsPageInteractions( {
			addArea: ( area ) => areas.push( area ),
			commitChange,
			getAreas: () => areas,
			getSelectedIndex: () => selectedIndex,
			getSelectedLayerIndex: () => selectedLayerIndex,
			initCanvasInteractions,
			markDirty,
			normaliseArea,
			normaliseDpi,
			normaliseUnit,
			openMockupPicker,
			renderAll,
			renderGhosts,
			renderRatioLockButton,
			selectedArea,
			setSelectedIndex: ( index ) => {
				selectedIndex = index;
			},
			setSelectedLayerIndex: ( index ) => {
				selectedLayerIndex = index;
			},
			snapshot,
			syncBoundsFromInputs,
			updateAspectRatio,
			updateBoundsBox,
		} );
	const {
		renderCanvas,
		updateBoundsBox,
		renderGhosts,
		initCanvasInteractions,
		syncBoundsFromInputs,
		syncRightBounds,
	} = createProductsPageCanvas( {
		addLayerWithBounds,
		applyLayerPreview,
		clamp,
		clampLayerToArea,
		currentAspectRatio,
		getAreas: () => areas,
		getScale,
		getSelectedIndex: () => selectedIndex,
		getSelectedLayerIndex: () => selectedLayerIndex,
		hexRgba,
		markDirty,
		normaliseDpi,
		normaliseRotation,
		renderAll,
		renderHiddenFields,
		renderRatioLockButton,
		selectedArea,
		selectedLayer,
		setSelectedLayerIndex: ( index ) => {
			selectedLayerIndex = index;
		},
		setVal,
		snapshot,
		updateAspectRatio,
	} );
	const { buildTabContent, bindSettingsHandlers } =
		createProductsPageSettings( {
			commitChange,
			esc,
			fontLimit,
			getAreas: () => areas,
			layerLabel,
			normaliseHex,
			normaliseLinkGroup,
			renderLayerList,
			selectedArea,
			syncBoundsFromInputs,
		} );
	function renderAll() {
		renderAreasList();
		renderAreaStrip();
		renderLeftAreaProps();
		renderCanvas();
		renderRightColumn();
		renderHiddenFields();
	}
	function commitChange( options = {} ) {
		if ( options.all ) {
			renderAll();
		} else {
			if ( options.areasList ) {
				renderAreasList();
			}
			if ( options.areaStrip ) {
				renderAreaStrip();
			}
			if ( options.canvas ) {
				renderCanvas();
			}
			if ( options.rightColumn ) {
				renderRightColumn();
			}
			if ( options.hiddenFields !== false ) {
				renderHiddenFields();
			}
		}
		markDirty();
	}
	function renderAreaStrip() {
		const strip = document.getElementById( 'oc-area-strip' );
		if ( ! strip ) {
			return;
		}
		if ( areas.length === 0 ) {
			strip.style.display = 'none';
			return;
		}
		strip.style.display = '';
		strip.innerHTML = '';
		areas.forEach( ( area, i ) => {
			const card = document.createElement( 'div' );
			card.className =
				'oc-area-strip-card' +
				( i === selectedIndex ? ' oc-area-strip-card--active' : '' ) +
				( ! area.visible ? ' oc-area-strip-card--hidden' : '' );
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
			lbl.className = 'oc-area-strip-label';
			lbl.textContent = area.label || 'Area ' + ( i + 1 );
			card.appendChild( thumb );
			card.appendChild( lbl );
			card.addEventListener( 'click', () => {
				if ( area.locked ) {
					return;
				} // locked areas can't be selected from strip
				selectedIndex = i;
				selectedLayerIndex = -1;
				activeLayerTab = 'general';
				renderAll();
			} );
			strip.appendChild( card );
		} );
	}
	function renderAreasList() {
		const list = document.getElementById( 'oc-areas-list' );
		const empty = document.getElementById( 'oc-areas-empty' );
		if ( ! list ) {
			return;
		}
		list.innerHTML = '';
		if ( areas.length === 0 ) {
			if ( empty ) {
				empty.style.display = '';
			}
			return;
		}
		if ( empty ) {
			empty.style.display = 'none';
		}
		areas.forEach( ( area, i ) => {
			const item = document.createElement( 'div' );
			item.className =
				'oc-area-item' +
				( i === selectedIndex ? ' oc-area-item--active' : '' ) +
				( ! area.visible ? ' oc-layer--hidden' : '' ) +
				( area.locked ? ' oc-layer--locked' : '' );
			item.innerHTML =
				'<span class="oc-area-dot" style="background:' +
				areaColor( i ) +
				';flex-shrink:0;"></span>' +
				'<span class="oc-area-item-name">' +
				esc( area.label || 'Print Area ' + ( i + 1 ) ) +
				'</span>' +
				'<span class="oc-area-item-method">' +
				esc( methodLabel( area.method ) ) +
				'</span>' +
				'<div class="oc-layer-actions">' +
				'<button type="button" class="oc-layer-action-btn oc-layer-vis-btn' +
				( ! area.visible ? ' is-off' : '' ) +
				'" title="' +
				( area.visible ? 'Hide area' : 'Show area' ) +
				'">' +
				( area.visible ? ICO_EYE : ICO_EYE_OFF ) +
				'</button>' +
				'<button type="button" class="oc-layer-action-btn oc-layer-lock-btn' +
				( area.locked ? ' is-on' : '' ) +
				'" title="' +
				( area.locked ? 'Unlock area' : 'Lock area' ) +
				'">' +
				( area.locked ? ICO_LOCK : ICO_UNLOCK ) +
				'</button>' +
				'<button type="button" class="oc-layer-action-btn oc-layer-delete-btn" title="Delete area">' +
				ICO_BIN +
				'</button>' +
				'</div>';
			item.addEventListener( 'click', ( e ) => {
				if ( e.target.closest( '.oc-layer-actions' ) ) {
					return;
				}
				if ( area.locked ) {
					return;
				} // locked areas can't be selected from list
				selectedIndex = i;
				selectedLayerIndex = -1;
				activeLayerTab = 'general';
				renderAll();
			} );
			item.querySelector( '.oc-layer-vis-btn' ).addEventListener(
				'click',
				( e ) => {
					e.stopPropagation();
					area.visible = ! area.visible;
					snapshot();
					renderAll();
				}
			);
			item.querySelector( '.oc-layer-lock-btn' ).addEventListener(
				'click',
				( e ) => {
					e.stopPropagation();
					area.locked = ! area.locked;
					snapshot();
					renderAll();
				}
			);
			item.querySelector( '.oc-layer-delete-btn' ).addEventListener(
				'click',
				( e ) => {
					e.stopPropagation();
					if (
						! confirm(
							'Remove this print area and all its layers?'
						)
					) {
						return;
					}
					areas.splice( i, 1 );
					if ( selectedIndex === i ) {
						selectedIndex =
							areas.length > 0
								? Math.min( i, areas.length - 1 )
								: -1;
					} else if ( selectedIndex > i ) {
						selectedIndex--;
					}
					selectedLayerIndex = -1;
					snapshot();
					renderAll();
				}
			);
			list.appendChild( item );
		} );
	}
	function renderLeftAreaProps() {
		const noSel = document.getElementById( 'oc-area-no-sel' );
		const inner = document.getElementById( 'oc-area-props-inner' );
		const area = selectedArea();
		if ( ! area ) {
			if ( noSel ) {
				noSel.style.display = '';
			}
			if ( inner ) {
				inner.style.display = 'none';
			}
			return;
		}
		if ( noSel ) {
			noSel.style.display = 'none';
		}
		if ( inner ) {
			inner.style.display = '';
		}
		setVal( 'oc-prop-label', area.label );
		setVal( 'oc-prop-method', area.method );
		setVal( 'oc-prop-engraving-material', area.material || 'silver_metal' );
		setVal( 'oc-prop-unit', area.unit || 'px' );
		setVal( 'oc-prop-x', area.x );
		setVal( 'oc-prop-y', area.y );
		setVal( 'oc-prop-w', area.w );
		setVal( 'oc-prop-h', area.h );
		setVal( 'oc-prop-dpi', area.dpi || 300 );
		setVal( 'oc-prop-rotation', area.rotation );
		renderRatioLockButton( area );
		const thumb = document.getElementById( 'oc-mockup-thumb-img' );
		const noThumb = document.getElementById( 'oc-mockup-thumb-empty' );
		const removeBtn = document.getElementById( 'oc-remove-mockup-btn' );
		const chooseBtn = document.getElementById( 'oc-choose-mockup-btn' );
		const dot = document.getElementById( 'oc-right-area-color' );
		if ( area.mockupUrl ) {
			if ( thumb ) {
				thumb.src = area.mockupUrl;
				thumb.style.display = '';
			}
			if ( noThumb ) {
				noThumb.style.display = 'none';
			}
		} else {
			if ( thumb ) {
				thumb.style.display = 'none';
			}
			if ( noThumb ) {
				noThumb.style.display = '';
			}
		}
		if ( removeBtn ) {
			removeBtn.style.display = area.mockupUrl ? '' : 'none';
		}
		if ( chooseBtn ) {
			const chooseLabel = area.mockupUrl
				? 'Change Mockup'
				: 'Choose Mockup';
			chooseBtn.setAttribute( 'aria-label', chooseLabel );
			chooseBtn.setAttribute( 'title', chooseLabel );
		}
		if ( dot ) {
			dot.style.background = areaColor( selectedIndex );
		}
		const materialWrap = document.getElementById(
			'oc-prop-engraving-material-wrap'
		);
		if ( materialWrap ) {
			materialWrap.style.display =
				area.method === 'engraving' ? '' : 'none';
		}
	}
	function renderRatioLockButton( area ) {
		const btn = document.getElementById( 'oc-prop-ratio-lock' );
		if ( ! btn || ! area ) {
			return;
		}
		btn.innerHTML = area.ratioLocked ? ICO_LOCK : ICO_UNLOCK;
		btn.classList.toggle( 'is-on', !! area.ratioLocked );
		btn.setAttribute( 'aria-pressed', area.ratioLocked ? 'true' : 'false' );
		btn.setAttribute(
			'aria-label',
			area.ratioLocked ? 'Unlock aspect ratio' : 'Lock aspect ratio'
		);
		btn.setAttribute(
			'title',
			area.ratioLocked ? 'Unlock aspect ratio' : 'Lock aspect ratio'
		);
	}
	function renderRightColumn() {
		const area = selectedArea();
		const layer = selectedLayer();
		const hint = document.getElementById( 'oc-type-picker-hint' );
		if ( hint ) {
			hint.style.display = area ? 'none' : '';
		}
		document.querySelectorAll( '.oc-layer-type-btn' ).forEach( ( btn ) => {
			btn.disabled = ! area;
			btn.style.opacity = area ? '' : '.4';
		} );
		renderLayerList( area );
		const noSel = document.getElementById( 'oc-layer-no-sel' );
		const inner = document.getElementById( 'oc-layer-props-inner' );
		if ( ! layer ) {
			if ( noSel ) {
				noSel.style.display = '';
			}
			if ( inner ) {
				inner.style.display = 'none';
			}
		} else {
			if ( noSel ) {
				noSel.style.display = 'none';
			}
			if ( inner ) {
				inner.style.display = '';
			}
			renderLayerPanel( layer );
		}
	}
	function renderLayerList( area ) {
		const listEl = document.getElementById( 'oc-layers-list' );
		const noArea = document.getElementById( 'oc-layers-no-area' );
		const emptyEl = document.getElementById( 'oc-layers-empty' );
		const countEl = document.getElementById( 'oc-layers-count' );
		if ( ! listEl ) {
			return;
		}
		listEl.innerHTML = '';
		if ( ! area ) {
			if ( noArea ) {
				noArea.style.display = '';
			}
			if ( emptyEl ) {
				emptyEl.style.display = 'none';
			}
			if ( countEl ) {
				countEl.textContent = '';
			}
			return;
		}
		if ( noArea ) {
			noArea.style.display = 'none';
		}
		const layers = area.layers || [];
		if ( countEl ) {
			countEl.textContent =
				layers.length + ( 1 === layers.length ? ' layer' : ' layers' );
		}
		if ( layers.length === 0 ) {
			if ( emptyEl ) {
				emptyEl.style.display = '';
			}
			return;
		}
		if ( emptyEl ) {
			emptyEl.style.display = 'none';
		}
		layers.forEach( ( layer, li ) => {
			const item = document.createElement( 'div' );
			item.className =
				'oc-layer-item' +
				( li === selectedLayerIndex ? ' oc-layer-item--active' : '' ) +
				( ! layer.visible ? ' oc-layer--hidden' : '' ) +
				( layer.locked ? ' oc-layer--locked' : '' );
			item.draggable = true;
			item.dataset.layerIndex = li;
			item.innerHTML =
				'<span class="oc-layer-drag-handle" title="Drag to reorder">\u22ee\u22ee</span>' +
				'<span class="oc-layer-icon" style="color:' +
				layerColor( layer.type ) +
				';">' +
				esc( layerIcon( layer.type ) ) +
				'</span>' +
				'<span class="oc-layer-item-name">' +
				esc( layer.label || layerLabel( layer.type ) ) +
				'</span>' +
				'<div class="oc-layer-actions">' +
				'<button type="button" class="oc-layer-action-btn oc-layer-vis-btn' +
				( ! layer.visible ? ' is-off' : '' ) +
				'" title="' +
				( layer.visible ? 'Hide layer' : 'Show layer' ) +
				'">' +
				( layer.visible ? ICO_EYE : ICO_EYE_OFF ) +
				'</button>' +
				'<button type="button" class="oc-layer-action-btn oc-layer-lock-btn' +
				( layer.locked ? ' is-on' : '' ) +
				'" title="' +
				( layer.locked ? 'Unlock layer' : 'Lock layer' ) +
				'">' +
				( layer.locked ? ICO_LOCK : ICO_UNLOCK ) +
				'</button>' +
				'<button type="button" class="oc-layer-action-btn oc-layer-delete-btn" title="Delete layer">' +
				ICO_BIN +
				'</button>' +
				'</div>';
			item.addEventListener( 'click', ( e ) => {
				if (
					e.target.closest( '.oc-layer-actions' ) ||
					e.target.classList.contains( 'oc-layer-drag-handle' )
				) {
					return;
				}
				if ( layer.locked ) {
					return;
				} // locked layers can't be selected from list
				selectedLayerIndex = li;
				renderAll();
			} );
			item.querySelector( '.oc-layer-vis-btn' ).addEventListener(
				'click',
				( e ) => {
					e.stopPropagation();
					layer.visible = ! layer.visible;
					snapshot();
					renderAll();
				}
			);
			item.querySelector( '.oc-layer-lock-btn' ).addEventListener(
				'click',
				( e ) => {
					e.stopPropagation();
					layer.locked = ! layer.locked;
					snapshot();
					renderAll();
				}
			);
			item.querySelector( '.oc-layer-delete-btn' ).addEventListener(
				'click',
				( e ) => {
					e.stopPropagation();
					if ( ! confirm( 'Remove this layer?' ) ) {
						return;
					}
					area.layers.splice( li, 1 );
					if ( selectedLayerIndex === li ) {
						selectedLayerIndex = -1;
					} else if ( selectedLayerIndex > li ) {
						selectedLayerIndex--;
					}
					snapshot();
					renderAll();
				}
			);
			item.addEventListener( 'dragstart', ( e ) => {
				layerDragSrc = li;
				e.dataTransfer.effectAllowed = 'move';
				setTimeout(
					() => item.classList.add( 'oc-layer-item--dragging' ),
					0
				);
			} );
			item.addEventListener( 'dragend', () => {
				layerDragSrc = -1;
				listEl
					.querySelectorAll(
						'.oc-layer-item--dragging, .oc-layer-item--drag-over'
					)
					.forEach( ( el ) => {
						el.classList.remove(
							'oc-layer-item--dragging',
							'oc-layer-item--drag-over'
						);
					} );
			} );
			item.addEventListener( 'dragover', ( e ) => {
				e.preventDefault();
				e.dataTransfer.dropEffect = 'move';
				if ( Number( item.dataset.layerIndex ) !== layerDragSrc ) {
					listEl
						.querySelectorAll( '.oc-layer-item--drag-over' )
						.forEach( ( el ) =>
							el.classList.remove( 'oc-layer-item--drag-over' )
						);
					item.classList.add( 'oc-layer-item--drag-over' );
				}
			} );
			item.addEventListener( 'drop', ( e ) => {
				e.preventDefault();
				const targetIdx = Number( item.dataset.layerIndex );
				if ( layerDragSrc < 0 || layerDragSrc === targetIdx ) {
					return;
				}
				const moved = area.layers.splice( layerDragSrc, 1 )[ 0 ];
				area.layers.splice( targetIdx, 0, moved );
				if ( selectedLayerIndex === layerDragSrc ) {
					selectedLayerIndex = targetIdx;
				} else if (
					layerDragSrc < targetIdx &&
					selectedLayerIndex > layerDragSrc &&
					selectedLayerIndex <= targetIdx
				) {
					selectedLayerIndex--;
				} else if (
					layerDragSrc > targetIdx &&
					selectedLayerIndex >= targetIdx &&
					selectedLayerIndex < layerDragSrc
				) {
					selectedLayerIndex++;
				}
				snapshot();
				renderAll();
			} );
			listEl.appendChild( item );
		} );
	}
	function renderLayerPanel( layer ) {
		const iconEl = document.getElementById( 'oc-layer-type-icon' );
		const lblEl = document.getElementById( 'oc-layer-type-label' );
		const dotEl = document.getElementById( 'oc-layer-color-dot' );
		if ( iconEl ) {
			iconEl.textContent = layerIcon( layer.type );
		}
		if ( lblEl ) {
			lblEl.textContent = layerLabel( layer.type );
		}
		if ( dotEl ) {
			dotEl.style.background = layerColor( layer.type );
		}
		const tabs = LAYER_TABS[ layer.type ] || LAYER_TABS.text;
		if ( ! tabs.some( ( t ) => t.id === activeLayerTab ) ) {
			activeLayerTab = 'general';
		}
		const settingsEl = document.getElementById( 'oc-layer-settings' );
		if ( ! settingsEl ) {
			return;
		}
		let html = '<div class="oc-layer-tabs-bar">';
		tabs.forEach( ( t ) => {
			html +=
				'<button type="button" class="oc-layer-tab' +
				( t.id === activeLayerTab ? ' oc-layer-tab--active' : '' ) +
				'" data-tab="' +
				t.id +
				'" title="' +
				esc( t.label ) +
				'" aria-label="' +
				esc( t.label ) +
				'"><span aria-hidden="true">' +
				esc( t.icon || t.label.charAt( 0 ) ) +
				'</span></button>';
		} );
		html += '</div>';
		tabs.forEach( ( t ) => {
			html +=
				'<div class="oc-layer-tab-panel' +
				( t.id === activeLayerTab
					? ' oc-layer-tab-panel--active'
					: '' ) +
				'" data-panel="' +
				t.id +
				'">';
			html += buildTabContent( t.id, layer );
			html += '</div>';
		} );
		settingsEl.innerHTML = html;
		settingsEl.querySelectorAll( '.oc-layer-tab' ).forEach( ( btn ) => {
			btn.addEventListener( 'click', () => {
				activeLayerTab = btn.dataset.tab;
				settingsEl
					.querySelectorAll( '.oc-layer-tab' )
					.forEach( ( b ) =>
						b.classList.toggle(
							'oc-layer-tab--active',
							b.dataset.tab === activeLayerTab
						)
					);
				settingsEl
					.querySelectorAll( '.oc-layer-tab-panel' )
					.forEach( ( p ) =>
						p.classList.toggle(
							'oc-layer-tab-panel--active',
							p.dataset.panel === activeLayerTab
						)
					);
			} );
		} );
		bindSettingsHandlers( layer );
	}
	function renderHiddenFields() {
		renderProductsPageHiddenFields( areas, esc );
	}

	document.addEventListener( 'DOMContentLoaded', init );
} )();
