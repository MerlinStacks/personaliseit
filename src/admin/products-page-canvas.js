/* eslint-disable @wordpress/no-unused-vars-before-return */

import { displayEntity, unitPxScale } from '../shared/render-math';
import {
	LAYER_TYPES,
	areaColor,
	layerColor,
	layerIcon,
	layerLabel,
} from './products-page-metadata';

export function createProductsPageCanvas( deps ) {
	const {
		addLayerWithBounds,
		applyLayerPreview,
		clamp,
		clampLayerToArea,
		currentAspectRatio,
		getAreas,
		getDesignMaskEntry,
		getScale,
		getSelectedIndex,
		getSelectedLayerIndex,
		hexRgba,
		markDirty,
		normaliseDpi,
		normaliseRotation,
		renderAll,
		renderHiddenFields,
		renderRatioLockButton,
		selectedArea,
		selectedLayer,
		setSelectedLayerIndex,
		setVal,
		snapshot,
		updateAspectRatio,
	} = deps;
	let drag = null;
	let drawState = null;
	let drawEl = null;
	let drawPopup = null;
	let drawModeArea = null;
	let drawModeButton = null;

	function renderCanvas() {
		const stage = document.getElementById( 'oc-canvas-stage' );
		const noMockup = document.getElementById( 'oc-canvas-no-mockup' );
		const coords = document.getElementById( 'oc-canvas-coords' );
		const noMsg = document.getElementById( 'oc-canvas-no-mockup-msg' );
		if ( ! stage ) {
			return;
		}
		const area = selectedArea();
		if ( ! area || ! area.mockupUrl ) {
			drawModeArea = null;
			stage.style.display = 'none';
			noMockup.style.display = '';
			if ( coords ) {
				coords.style.display = 'none';
			}
			if ( noMsg ) {
				noMsg.textContent =
					getAreas().length === 0
						? 'Click \u201c+ Add\u201d on the left to create a print area.'
						: 'Select a print area and choose its mockup image.';
			}
			updateDrawModeControl();
			return;
		}
		noMockup.style.display = 'none';
		stage.style.display = '';
		if ( coords ) {
			coords.style.display = '';
		}
		const img = document.getElementById( 'oc-canvas-mockup-img' );
		if ( ! img ) {
			return;
		}
		if ( img.getAttribute( 'src' ) !== area.mockupUrl ) {
			img.src = area.mockupUrl;
			img.onload = () => {
				updateBoundsBox();
				renderGhosts();
			};
		} else {
			updateBoundsBox();
			renderGhosts();
		}
		const entity =
			getSelectedLayerIndex() >= 0
				? area.layers[ getSelectedLayerIndex() ] || area
				: area;
		updateCoordsReadout( entity );
		updateDrawModeControl();
	}
	function updateBoundsBox() {
		const box = document.getElementById( 'oc-bounds-box' );
		const img = document.getElementById( 'oc-canvas-mockup-img' );
		const area = selectedArea();
		if ( ! box || ! img || ! area ) {
			if ( box ) {
				box.style.display = 'none';
			}
			return;
		}
		const scale = getScale( img );
		if ( ! scale ) {
			return;
		}
		const layer = selectedLayer();
		const entity = layer || area;
		const display = displayEntity( entity, layer ? area : null );
		const color = layer
			? layerColor( layer.type )
			: areaColor( getSelectedIndex() );
		const isHidden = layer ? ! layer.visible : ! area.visible;
		const isLocked = layer ? layer.locked : area.locked;
		const hideForLocked = ! layer && area.locked;
		box.style.display = isHidden || hideForLocked ? 'none' : '';
		box.style.opacity = '';
		pos(
			box,
			display,
			scale,
			layer
				? normaliseRotation( area.rotation )
				: normaliseRotation( entity.rotation ),
			layer ? displayEntity( area ) : null
		);
		box.style.borderColor = color;
		box.style.background = hexRgba( color, 0.12 );
		box.title = layer
			? 'Drag to move this layer.'
			: 'Drag to move this area, or use Draw layer to create a layer.';
		box.classList.toggle( 'oc-bounds-box--locked', isLocked );
		box.classList.toggle( 'oc-bounds-box--rotatable', ! layer );
		box.querySelectorAll( '.oc-bounds-handle' ).forEach( ( h ) => {
			h.style.borderColor = color;
		} );
		box.querySelectorAll( '.oc-bounds-rotate-handle' ).forEach( ( h ) => {
			h.style.borderColor = color;
			h.style.color = color;
			h.style.display = layer ? 'none' : '';
		} );
		box.querySelectorAll( '.oc-bounds-box-pill' ).forEach( ( el ) =>
			el.remove()
		);
		const renderedW = Math.round( display.w * scale );
		const renderedH = Math.round( display.h * scale );
		applyLayerPreview(
			layer,
			box,
			renderedW,
			renderedH,
			false,
			area.method === 'engraving',
			area.material
		);
		if ( layer ) {
			const pill = document.createElement( 'div' );
			pill.className = 'oc-bounds-box-pill';
			pill.style.background = color;
			pill.textContent =
				layerIcon( layer.type ) + ' ' + layerLabel( layer.type );
			box.appendChild( pill );
		}
	}
	function renderGhosts() {
		const ghosts = document.getElementById( 'oc-canvas-ghosts' );
		const img = document.getElementById( 'oc-canvas-mockup-img' );
		if ( ! ghosts || ! img ) {
			return;
		}
		ghosts.innerHTML = '';
		const scale = getScale( img );
		if ( ! scale ) {
			return;
		}
		const area = selectedArea();
		const activeMockupId = Number( area?.mockupId ) || 0;
		const activeMockup = area?.mockupUrl || '';
		getAreas().forEach( ( a, i ) => {
			const sameMockup =
				activeMockupId && Number( a.mockupId )
					? Number( a.mockupId ) === activeMockupId
					: a.mockupUrl === activeMockup;
			if (
				i === getSelectedIndex() ||
				! sameMockup ||
				! activeMockup ||
				! a.visible
			) {
				return;
			}
			const g = ghost( a, areaColor( i ), 0.06 );
			g.appendChild(
				ghostLabel( a.label || 'Area ' + ( i + 1 ), areaColor( i ) )
			);
			pos(
				g,
				displayEntity( a ),
				scale,
				normaliseRotation( a.rotation )
			);
			ghosts.appendChild( g );
		} );
		if ( ! area ) {
			return;
		}
		if ( getSelectedLayerIndex() >= 0 ) {
			const outline = document.createElement( 'div' );
			outline.className = 'oc-canvas-area-outline';
			pos(
				outline,
				displayEntity( area ),
				scale,
				normaliseRotation( area.rotation )
			);
			ghosts.appendChild( outline );
		}
		const designMask = getDesignMaskEntry();
		const previewLayers = ( area.layers || [] )
			.map( ( layer, li ) => ( { area, layer, li } ) )
			.filter( ( entry ) => entry.layer.type !== 'mask' );
		if ( designMask ) {
			previewLayers.push( {
				area: designMask.area,
				layer: designMask.layer,
				li: -1,
			} );
		}
		previewLayers
			.sort(
				( a, b ) =>
					Number( a.layer.type === 'mask' ) -
					Number( b.layer.type === 'mask' )
			)
			.forEach( ( { area: layerArea, layer, li } ) => {
				if (
					( li >= 0 && li === getSelectedLayerIndex() ) ||
					! layer.visible
				) {
					return;
				}
				const isDesignMask = layer.type === 'mask';
				const displayLayer = isDesignMask
					? {
							x: 0,
							y: 0,
							w:
								img.naturalWidth ||
								Math.round( img.width / scale ),
							h:
								img.naturalHeight ||
								Math.round( img.height / scale ),
					  }
					: displayEntity( layer, layerArea );
				const g = ghost( layer, layerColor( layer.type ), 0.1 );
				g.classList.add( 'oc-canvas-layer-ghost' );
				g.appendChild(
					ghostLabel(
						layerIcon( layer.type ) +
							' ' +
							( layer.label || layerLabel( layer.type ) ),
						layerColor( layer.type )
					)
				);
				applyLayerPreview(
					layer,
					g,
					Math.round( displayLayer.w * scale ),
					Math.round( displayLayer.h * scale ),
					true,
					layerArea.method === 'engraving',
					layerArea.material
				);
				pos(
					g,
					displayLayer,
					scale,
					isDesignMask ? 0 : normaliseRotation( layerArea.rotation ),
					isDesignMask ? null : displayEntity( layerArea )
				);
				if ( layer.locked && ! isDesignMask ) {
					g.style.cursor = 'not-allowed';
					g.style.opacity = '0.5';
				} else if ( li >= 0 ) {
					g.style.cursor = 'pointer';
					g.addEventListener( 'click', () => {
						setSelectedLayerIndex( li );
						renderAll();
					} );
				}
				ghosts.appendChild( g );
			} );
	}
	function ghost( entity, color, bgAlpha ) {
		const g = document.createElement( 'div' );
		g.className = 'oc-canvas-ghost';
		g.style.borderColor = color;
		g.style.background = hexRgba( color, bgAlpha );
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
			const rad = ( rotation * Math.PI ) / 180;
			const dx = cx - acx;
			const dy = cy - acy;
			cx = acx + dx * Math.cos( rad ) - dy * Math.sin( rad );
			cy = acy + dx * Math.sin( rad ) + dy * Math.cos( rad );
		}
		el.style.left = Math.round( ( cx - entity.w / 2 ) * scale ) + 'px';
		el.style.top = Math.round( ( cy - entity.h / 2 ) * scale ) + 'px';
		el.style.width = Math.round( entity.w * scale ) + 'px';
		el.style.height = Math.round( entity.h * scale ) + 'px';
		el.style.transform = rotation ? 'rotate(' + rotation + 'deg)' : '';
		el.style.transformOrigin = 'center center';
	}
	function updateCoordsReadout( entity ) {
		const el = document.getElementById( 'oc-coords-text' );
		if ( el && entity ) {
			el.textContent =
				'X\u2009' +
				entity.x +
				'\u2002 Y\u2009' +
				entity.y +
				'\u2002 W\u2009' +
				entity.w +
				'\u2002 H\u2009' +
				entity.h +
				( entity.rotation
					? '\u2002 R\u2009' + entity.rotation + '\u00b0'
					: '' );
		}
	}

	function initCanvasInteractions() {
		initDrawModeControl();
		document
			.getElementById( 'oc-canvas-stage' )
			?.addEventListener( 'mousedown', routeCanvasMouseDown, true );
		const box = document.getElementById( 'oc-bounds-box' );
		if ( box ) {
			box.addEventListener( 'mousedown', ( e ) => {
				if ( e.target !== box ) {
					return;
				}
				e.preventDefault();
				e.stopPropagation();
				startDrag( e, 'move', '' );
			} );
			box.querySelectorAll( '.oc-bounds-handle' ).forEach( ( h ) => {
				h.addEventListener( 'mousedown', ( e ) => {
					e.preventDefault();
					e.stopPropagation();
					startDrag( e, 'resize', h.dataset.dir );
				} );
			} );
			box.querySelectorAll( '.oc-bounds-rotate-handle' ).forEach(
				( h ) => {
					h.addEventListener( 'mousedown', ( e ) => {
						e.preventDefault();
						e.stopPropagation();
						startDrag( e, 'rotate', '' );
					} );
				}
			);
		}
		document.addEventListener( 'mousemove', ( e ) => {
			if ( drag ) {
				onDragMove( e );
				return;
			}
			if ( drawState ) {
				onDrawMove( e );
			}
		} );
		document.addEventListener( 'mouseup', ( e ) => {
			if ( drag ) {
				onDragEnd();
				return;
			}
			if ( drawState ) {
				onDrawEnd( e );
			}
		} );
		document.addEventListener( 'keydown', ( e ) => {
			if ( e.key !== 'Escape' || drawPopup ) {
				return;
			}
			if ( drawState ) {
				cancelDraw();
				e.preventDefault();
			} else if ( drawModeArea ) {
				setDrawMode( false );
				e.preventDefault();
			}
		} );
	}
	function initDrawModeControl() {
		const wrap = document.querySelector( '.oc-editor-canvas-wrap' );
		if ( ! wrap ) {
			return;
		}
		drawModeButton = document.createElement( 'button' );
		drawModeButton.type = 'button';
		drawModeButton.className = 'oc-canvas-draw-toggle';
		drawModeButton.textContent = 'Draw layer';
		drawModeButton.setAttribute( 'aria-pressed', 'false' );
		drawModeButton.addEventListener( 'click', () => {
			setDrawMode( drawModeArea !== selectedArea() );
		} );
		wrap.appendChild( drawModeButton );
		updateDrawModeControl();
	}
	function canDrawInArea( area ) {
		return !! (
			area?.mockupUrl &&
			area.visible &&
			! area.locked &&
			getSelectedLayerIndex() < 0
		);
	}
	function setDrawMode( enabled ) {
		const area = selectedArea();
		drawModeArea = enabled && canDrawInArea( area ) ? area : null;
		updateDrawModeControl();
	}
	function updateDrawModeControl() {
		const area = selectedArea();
		const available = canDrawInArea( area );
		if ( drawModeArea && ( drawModeArea !== area || ! available ) ) {
			drawModeArea = null;
		}
		const active = !! drawModeArea;
		if ( drawModeButton ) {
			let title = 'Select an unlocked, visible area to draw a layer';
			if ( available ) {
				title = active
					? 'Cancel drawing'
					: 'Draw a layer inside the selected area';
			}
			drawModeButton.hidden = ! area?.mockupUrl;
			drawModeButton.disabled = ! available;
			drawModeButton.setAttribute(
				'aria-pressed',
				active ? 'true' : 'false'
			);
			drawModeButton.title = title;
		}
		document
			.getElementById( 'oc-canvas-stage' )
			?.classList.toggle( 'oc-canvas-stage--draw-mode', active );
	}
	function routeCanvasMouseDown( e ) {
		if ( e.button !== 0 || ! drawModeArea ) {
			return;
		}
		if ( startDrawRect( e, drawModeArea ) ) {
			e.preventDefault();
			e.stopImmediatePropagation();
		}
	}
	function startDrawRect( e, area ) {
		const img = document.getElementById( 'oc-canvas-mockup-img' );
		if ( ! img ) {
			return false;
		}
		const scale = getScale( img );
		if ( ! scale ) {
			return false;
		}
		const rect = img.getBoundingClientRect();
		const pointX = ( e.clientX - rect.left ) / scale;
		const pointY = ( e.clientY - rect.top ) / scale;
		const bounds = displayEntity( area );
		if (
			pointX < bounds.x ||
			pointX > bounds.x + bounds.w ||
			pointY < bounds.y ||
			pointY > bounds.y + bounds.h
		) {
			return false;
		}
		const sx = clamp( Math.round( pointX ), bounds.x, bounds.x + bounds.w );
		const sy = clamp( Math.round( pointY ), bounds.y, bounds.y + bounds.h );
		drawState = {
			area,
			bounds,
			startX: sx,
			startY: sy,
			curX: sx,
			curY: sy,
		};
		drawEl = document.createElement( 'div' );
		drawEl.className = 'oc-canvas-draw-preview';
		document.getElementById( 'oc-canvas-stage' )?.appendChild( drawEl );
		updateDrawEl();
		return true;
	}
	function onDrawMove( e ) {
		if ( ! drawState ) {
			return;
		}
		const img = document.getElementById( 'oc-canvas-mockup-img' );
		if ( ! img ) {
			return;
		}
		const scale = getScale( img );
		if ( ! scale ) {
			return;
		}
		const rect = img.getBoundingClientRect();
		drawState.curX = clamp(
			Math.round( ( e.clientX - rect.left ) / scale ),
			drawState.bounds.x,
			drawState.bounds.x + drawState.bounds.w
		);
		drawState.curY = clamp(
			Math.round( ( e.clientY - rect.top ) / scale ),
			drawState.bounds.y,
			drawState.bounds.y + drawState.bounds.h
		);
		updateDrawEl();
	}
	function updateDrawEl() {
		if ( ! drawEl || ! drawState ) {
			return;
		}
		const img = document.getElementById( 'oc-canvas-mockup-img' );
		if ( ! img ) {
			return;
		}
		const scale = getScale( img );
		const x = Math.min( drawState.startX, drawState.curX );
		const y = Math.min( drawState.startY, drawState.curY );
		const w = Math.abs( drawState.curX - drawState.startX );
		const h = Math.abs( drawState.curY - drawState.startY );
		drawEl.style.left = Math.round( x * scale ) + 'px';
		drawEl.style.top = Math.round( y * scale ) + 'px';
		drawEl.style.width = Math.round( w * scale ) + 'px';
		drawEl.style.height = Math.round( h * scale ) + 'px';
	}
	function onDrawEnd( e ) {
		if ( ! drawState ) {
			return;
		}
		const state = drawState;
		drawState = null;
		if ( drawEl ) {
			drawEl.remove();
			drawEl = null;
		}
		const x = Math.min( state.startX, state.curX );
		const y = Math.min( state.startY, state.curY );
		const w = Math.abs( state.curX - state.startX );
		const h = Math.abs( state.curY - state.startY );
		if ( w < 10 || h < 10 ) {
			return;
		} // too small — treat as click miss
		setDrawMode( false );
		showDrawTypePicker( state.area, x, y, w, h, e.clientX, e.clientY );
	}
	function cancelDraw() {
		drawState = null;
		if ( drawEl ) {
			drawEl.remove();
			drawEl = null;
		}
	}
	function showDrawTypePicker(
		area,
		natX,
		natY,
		natW,
		natH,
		clientX,
		clientY
	) {
		closeDrawTypePicker();
		const backdrop = document.createElement( 'div' );
		backdrop.className = 'oc-draw-popup-backdrop';
		const popup = document.createElement( 'div' );
		popup.className = 'oc-draw-type-popup';
		popup.id = 'oc-draw-type-popup';
		popup.setAttribute( 'role', 'dialog' );
		popup.setAttribute( 'aria-label', 'Choose layer type' );
		popup.setAttribute( 'aria-modal', 'true' );
		Object.keys( LAYER_TYPES ).forEach( ( type ) => {
			const btn = document.createElement( 'button' );
			btn.type = 'button';
			btn.className = 'oc-draw-type-btn';
			btn.innerHTML =
				'<span style="font-size:18px;color:' +
				layerColor( type ) +
				';">' +
				layerIcon( type ) +
				'</span><span>' +
				layerLabel( type ) +
				'</span>';
			btn.addEventListener( 'click', ( e ) => {
				e.stopPropagation();
				addLayerAt( type, area, natX, natY, natW, natH );
				closeDrawTypePicker();
			} );
			popup.appendChild( btn );
		} );
		document.body.appendChild( backdrop );
		document.body.appendChild( popup );
		drawPopup = { popup, backdrop, returnFocus: drawModeButton };
		window.requestAnimationFrame( () => {
			if ( drawPopup?.popup !== popup ) {
				return;
			}
			const pw = popup.offsetWidth,
				ph = popup.offsetHeight;
			const vw = window.innerWidth,
				vh = window.innerHeight;
			let left = clientX + 8;
			let top = clientY + 8;
			if ( left + pw > vw - 8 ) {
				left = clientX - pw - 8;
			}
			if ( top + ph > vh - 8 ) {
				top = clientY - ph - 8;
			}
			popup.style.left = Math.max( 8, left ) + 'px';
			popup.style.top = Math.max( 8, top ) + 'px';
			popup.querySelector( 'button' )?.focus();
		} );
		backdrop.addEventListener( 'click', closeDrawTypePicker );
		document.addEventListener( 'keydown', onDrawPickerKey );
	}
	function onDrawPickerKey( e ) {
		if ( e.key === 'Escape' ) {
			e.preventDefault();
			closeDrawTypePicker();
		}
	}
	function closeDrawTypePicker() {
		let returnFocus = null;
		if ( drawPopup ) {
			returnFocus = drawPopup.returnFocus;
			drawPopup.popup.remove();
			drawPopup.backdrop.remove();
			drawPopup = null;
		}
		document.removeEventListener( 'keydown', onDrawPickerKey );
		if (
			returnFocus?.isConnected &&
			! returnFocus.hidden &&
			! returnFocus.disabled
		) {
			returnFocus.focus();
		}
	}
	function addLayerAt( type, area, x, y, w, h ) {
		if ( ! area || selectedArea() !== area ) {
			return;
		}
		const px = unitPxScale( area );
		addLayerWithBounds(
			type,
			area.x + Math.round( ( x - area.x ) / px ),
			area.y + Math.round( ( y - area.y ) / px ),
			Math.max( 1, Math.round( w / px ) ),
			Math.max( 1, Math.round( h / px ) )
		);
	}
	function activeEntity() {
		const area = selectedArea();
		if ( ! area ) {
			return null;
		}
		const layer = selectedLayer();
		return layer || area;
	}
	function startDrag( e, type, dir ) {
		const entity = activeEntity();
		if ( ! entity ) {
			return;
		}
		const area = selectedArea();
		const layer = selectedLayer();
		if ( layer ? layer.locked : area && area.locked ) {
			return;
		}
		const unitScale = unitPxScale( area );
		const entityScale = layer ? 1 : unitScale;
		drag = {
			area,
			entity,
			layer,
			type,
			dir,
			startClientX: e.clientX,
			startClientY: e.clientY,
			startX: entity.x,
			startY: entity.y,
			startW: entity.w,
			startH: entity.h,
			startRight: entity.x + entity.w * entityScale,
			startBottom: entity.y + entity.h * entityScale,
			unitScale,
			childGeometry: layer
				? []
				: ( area.layers || [] ).map( ( child ) => ( {
						child,
						x: child.x,
						y: child.y,
						w: child.w,
						h: child.h,
				  } ) ),
		};
	}
	function restoreChildrenFromDrag( area, shouldClamp ) {
		const deltaX = area.x - drag.startX;
		const deltaY = area.y - drag.startY;
		drag.childGeometry.forEach( ( start ) => {
			start.child.x = start.x + deltaX;
			start.child.y = start.y + deltaY;
			start.child.w = start.w;
			start.child.h = start.h;
			if ( shouldClamp ) {
				clampLayerToArea( start.child, area );
			}
		} );
	}
	function onDragMove( e ) {
		if ( ! drag ) {
			return;
		}
		const entity = drag.entity;
		const img = document.getElementById( 'oc-canvas-mockup-img' );
		const area = drag.area;
		const layer = drag.layer;
		if ( ! entity || ! img ) {
			return;
		}
		const scale = getScale( img );
		if ( ! scale ) {
			return;
		}
		if ( drag.type === 'rotate' ) {
			const rect = img.getBoundingClientRect();
			const displayArea = displayEntity( area );
			const cx =
				rect.left + ( displayArea.x + displayArea.w / 2 ) * scale;
			const cy = rect.top + ( displayArea.y + displayArea.h / 2 ) * scale;
			entity.rotation = normaliseRotation(
				( Math.atan2( e.clientY - cy, e.clientX - cx ) * 180 ) /
					Math.PI +
					90
			);
			updateBoundsBox();
			renderGhosts();
			updateCoordsReadout( entity );
			syncRightBounds( entity );
			renderHiddenFields();
			return;
		}
		const displayDx = ( e.clientX - drag.startClientX ) / scale;
		const displayDy = ( e.clientY - drag.startClientY ) / scale;
		const unitScale = drag.unitScale;
		const dx = Math.round( displayDx / ( layer ? unitScale : 1 ) );
		const dy = Math.round( displayDy / ( layer ? unitScale : 1 ) );
		const natW = img.naturalWidth || 2000;
		const natH = img.naturalHeight || 2000;
		const d = drag.dir;
		if ( drag.type === 'move' ) {
			if ( layer ) {
				entity.x = clamp(
					drag.startX + dx,
					area.x,
					area.x + area.w - drag.startW
				);
				entity.y = clamp(
					drag.startY + dy,
					area.y,
					area.y + area.h - drag.startH
				);
			} else {
				entity.x = clamp(
					Math.round( drag.startX + displayDx ),
					0,
					Math.max( 0, natW - drag.startW * unitScale )
				);
				entity.y = clamp(
					Math.round( drag.startY + displayDy ),
					0,
					Math.max( 0, natH - drag.startH * unitScale )
				);
				restoreChildrenFromDrag( area, false );
			}
		} else if ( layer ) {
			let left = drag.startX;
			let right = drag.startRight;
			let top = drag.startY;
			let bottom = drag.startBottom;
			if ( d.includes( 'e' ) ) {
				right = clamp(
					drag.startRight + dx,
					drag.startX + 1,
					area.x + area.w
				);
			}
			if ( d.includes( 's' ) ) {
				bottom = clamp(
					drag.startBottom + dy,
					drag.startY + 1,
					area.y + area.h
				);
			}
			if ( d.includes( 'w' ) ) {
				left = clamp( drag.startX + dx, area.x, drag.startRight - 1 );
			}
			if ( d.includes( 'n' ) ) {
				top = clamp( drag.startY + dy, area.y, drag.startBottom - 1 );
			}
			entity.x = left;
			entity.y = top;
			entity.w = right - left;
			entity.h = bottom - top;
		} else {
			const maxW = Math.max(
				1,
				( d.includes( 'w' ) ? drag.startRight : natW - drag.startX ) /
					unitScale
			);
			const maxH = Math.max(
				1,
				( d.includes( 'n' ) ? drag.startBottom : natH - drag.startY ) /
					unitScale
			);
			let nw = drag.startW;
			let nh = drag.startH;
			if ( area.ratioLocked ) {
				const ratio = currentAspectRatio( area );
				if ( d === 'n' || d === 's' ) {
					const desiredH =
						d === 'n'
							? drag.startH - displayDy / unitScale
							: drag.startH + displayDy / unitScale;
					nh = clamp(
						Math.round( desiredH ),
						1,
						Math.max(
							1,
							Math.floor( Math.min( maxH, maxW / ratio ) )
						)
					);
					nw = Math.max( 1, Math.round( nh * ratio ) );
				} else {
					const desiredW = d.includes( 'w' )
						? drag.startW - displayDx / unitScale
						: drag.startW + displayDx / unitScale;
					nw = clamp(
						Math.round( desiredW ),
						1,
						Math.max(
							1,
							Math.floor( Math.min( maxW, maxH * ratio ) )
						)
					);
					nh = Math.max( 1, Math.round( nw / ratio ) );
				}
			} else {
				if ( d.includes( 'e' ) ) {
					nw = clamp(
						Math.round( drag.startW + displayDx / unitScale ),
						1,
						Math.max( 1, Math.floor( maxW ) )
					);
				}
				if ( d.includes( 'w' ) ) {
					nw = clamp(
						Math.round( drag.startW - displayDx / unitScale ),
						1,
						Math.max( 1, Math.floor( maxW ) )
					);
				}
				if ( d.includes( 's' ) ) {
					nh = clamp(
						Math.round( drag.startH + displayDy / unitScale ),
						1,
						Math.max( 1, Math.floor( maxH ) )
					);
				}
				if ( d.includes( 'n' ) ) {
					nh = clamp(
						Math.round( drag.startH - displayDy / unitScale ),
						1,
						Math.max( 1, Math.floor( maxH ) )
					);
				}
			}
			entity.x = d.includes( 'w' )
				? Math.max( 0, Math.round( drag.startRight - nw * unitScale ) )
				: drag.startX;
			entity.y = d.includes( 'n' )
				? Math.max( 0, Math.round( drag.startBottom - nh * unitScale ) )
				: drag.startY;
			entity.w = nw;
			entity.h = nh;
			restoreChildrenFromDrag( area, true );
			if ( ! area.ratioLocked ) {
				updateAspectRatio( area );
			}
		}
		updateBoundsBox();
		renderGhosts();
		updateCoordsReadout( entity );
		syncRightBounds( entity );
		renderHiddenFields();
	}
	function onDragEnd() {
		if ( drag ) {
			snapshot();
		} // snapshot after every move/resize
		drag = null;
	}
	function syncBoundsFromInputs( changedId = '' ) {
		const area = selectedArea();
		if ( ! area ) {
			return;
		}
		const layer =
			changedId.startsWith( 'oc-layer-' ) && getSelectedLayerIndex() >= 0
				? area.layers[ getSelectedLayerIndex() ]
				: null;
		const entity = layer || area;
		const previousAreaX = area.x;
		const previousAreaY = area.y;
		const inputPrefix = layer ? 'oc-layer' : 'oc-prop';
		const readInt = ( id, fallback ) => {
			const value = parseInt(
				document.getElementById( id )?.value || fallback,
				10
			);
			return Number.isFinite( value ) ? value : fallback;
		};
		if ( changedId === inputPrefix + '-x' ) {
			entity.x = Math.max( 0, readInt( changedId, entity.x || 0 ) );
		}
		if ( changedId === inputPrefix + '-y' ) {
			entity.y = Math.max( 0, readInt( changedId, entity.y || 0 ) );
		}
		if ( changedId === inputPrefix + '-w' ) {
			entity.w = Math.max( 1, readInt( changedId, entity.w || 1 ) );
			if ( ! layer && area.ratioLocked ) {
				entity.h = Math.max(
					1,
					Math.round( entity.w / currentAspectRatio( area ) )
				);
			}
		}
		if ( changedId === inputPrefix + '-h' ) {
			entity.h = Math.max( 1, readInt( changedId, entity.h || 1 ) );
			if ( ! layer && area.ratioLocked ) {
				entity.w = Math.max(
					1,
					Math.round( entity.h * currentAspectRatio( area ) )
				);
			}
		}
		if (
			! layer &&
			! area.ratioLocked &&
			( changedId === 'oc-prop-w' || changedId === 'oc-prop-h' )
		) {
			updateAspectRatio( area );
		}
		if ( ! layer && changedId === 'oc-prop-dpi' ) {
			entity.dpi = normaliseDpi(
				readInt( changedId, entity.dpi || 300 )
			);
		}
		if ( ! layer && changedId === 'oc-prop-rotation' ) {
			entity.rotation = normaliseRotation(
				readInt( changedId, entity.rotation || 0 )
			);
		}
		if ( layer ) {
			clampLayerToArea( layer, area );
		} else {
			const deltaX = area.x - previousAreaX;
			const deltaY = area.y - previousAreaY;
			if ( deltaX || deltaY ) {
				( area.layers || [] ).forEach( ( child ) => {
					child.x += deltaX;
					child.y += deltaY;
				} );
			}
			if ( changedId === 'oc-prop-w' || changedId === 'oc-prop-h' ) {
				( area.layers || [] ).forEach( ( child ) =>
					clampLayerToArea( child, area )
				);
			}
		}
		updateBoundsBox();
		renderGhosts();
		updateCoordsReadout( entity );
		renderHiddenFields();
		markDirty();
	}
	function syncRightBounds( entity ) {
		const area = selectedArea();
		const layer = selectedLayer();
		const prefix = entity === layer ? 'oc-layer' : 'oc-prop';
		setVal( prefix + '-x', entity.x );
		setVal( prefix + '-y', entity.y );
		setVal( prefix + '-w', entity.w );
		setVal( prefix + '-h', entity.h );
		if ( entity === area ) {
			setVal( 'oc-prop-dpi', entity.dpi || 300 );
		}
		if ( entity === area ) {
			renderRatioLockButton( entity );
		}
		if ( entity === area ) {
			setVal( 'oc-prop-rotation', normaliseRotation( entity.rotation ) );
		}
	}

	return {
		renderCanvas,
		updateBoundsBox,
		renderGhosts,
		initCanvasInteractions,
		syncBoundsFromInputs,
		syncRightBounds,
	};
}
