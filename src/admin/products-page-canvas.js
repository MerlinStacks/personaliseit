/* eslint-disable no-undef, @wordpress/no-unused-vars-before-return */

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
			area.method === 'engraving'
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
		const activeMockup = area ? area.mockupUrl : '';
		getAreas().forEach( ( a, i ) => {
			if (
				i === getSelectedIndex() ||
				a.mockupUrl !== activeMockup ||
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
		( area.layers || [] ).forEach( ( layer, li ) => {
			if ( li === getSelectedLayerIndex() || ! layer.visible ) {
				return;
			}
			const displayLayer = displayEntity( layer, area );
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
				area.method === 'engraving'
			);
			pos(
				g,
				displayLayer,
				scale,
				normaliseRotation( area.rotation ),
				displayEntity( area )
			);
			if ( layer.locked ) {
				g.style.cursor = 'not-allowed';
				g.style.opacity = '0.5';
			} else {
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
		document
			.getElementById( 'oc-canvas-mockup-img' )
			?.addEventListener( 'mousedown', ( e ) => {
				const area = selectedArea();
				if ( ! area || area.locked || getSelectedLayerIndex() >= 0 ) {
					return;
				}
				e.preventDefault();
				startDrawRect( e );
			} );
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
	}
	function startDrawRect( e ) {
		const img = document.getElementById( 'oc-canvas-mockup-img' );
		if ( ! img ) {
			return;
		}
		const area = selectedArea();
		if ( ! area ) {
			return;
		}
		const scale = getScale( img );
		if ( ! scale ) {
			return;
		}
		const rect = img.getBoundingClientRect();
		const sx = clamp(
			Math.round( ( e.clientX - rect.left ) / scale ),
			area.x,
			area.x + area.w
		);
		const sy = clamp(
			Math.round( ( e.clientY - rect.top ) / scale ),
			area.y,
			area.y + area.h
		);
		drawState = {
			startX: sx,
			startY: sy,
			curX: sx,
			curY: sy,
			startClientX: e.clientX,
			startClientY: e.clientY,
		};
		drawEl = document.createElement( 'div' );
		drawEl.className = 'oc-canvas-draw-preview';
		document.getElementById( 'oc-canvas-stage' )?.appendChild( drawEl );
		updateDrawEl();
	}
	function onDrawMove( e ) {
		if ( ! drawState ) {
			return;
		}
		const img = document.getElementById( 'oc-canvas-mockup-img' );
		if ( ! img ) {
			return;
		}
		const area = selectedArea();
		if ( ! area ) {
			return;
		}
		const scale = getScale( img );
		const rect = img.getBoundingClientRect();
		drawState.curX = clamp(
			Math.round( ( e.clientX - rect.left ) / scale ),
			area.x,
			area.x + area.w
		);
		drawState.curY = clamp(
			Math.round( ( e.clientY - rect.top ) / scale ),
			area.y,
			area.y + area.h
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
		showDrawTypePicker( x, y, w, h, e.clientX, e.clientY );
	}
	function showDrawTypePicker( natX, natY, natW, natH, clientX, clientY ) {
		closeDrawTypePicker();
		const backdrop = document.createElement( 'div' );
		backdrop.className = 'oc-draw-popup-backdrop';
		const popup = document.createElement( 'div' );
		popup.className = 'oc-draw-type-popup';
		popup.id = 'oc-draw-type-popup';
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
				addLayerAt( type, natX, natY, natW, natH );
				closeDrawTypePicker();
			} );
			popup.appendChild( btn );
		} );
		document.body.appendChild( backdrop );
		document.body.appendChild( popup );
		drawPopup = { popup, backdrop };
		requestAnimationFrame( () => {
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
		if ( drawPopup ) {
			drawPopup.popup.remove();
			drawPopup.backdrop.remove();
			drawPopup = null;
		}
		document.removeEventListener( 'keydown', onDrawPickerKey );
	}
	function addLayerAt( type, x, y, w, h ) {
		const area = selectedArea();
		if ( ! area ) {
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
		drag = {
			type,
			dir,
			startClientX: e.clientX,
			startClientY: e.clientY,
			startX: entity.x,
			startY: entity.y,
			startW: entity.w,
			startH: entity.h,
			startRotation: normaliseRotation( entity.rotation ),
		};
	}
	function onDragMove( e ) {
		if ( ! drag ) {
			return;
		}
		const entity = activeEntity();
		const img = document.getElementById( 'oc-canvas-mockup-img' );
		const area = selectedArea();
		const layer = selectedLayer();
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
		const unitScale =
			layer || drag.type !== 'move' ? unitPxScale( area ) : 1;
		const dx = Math.round(
			( e.clientX - drag.startClientX ) / scale / unitScale
		);
		const dy = Math.round(
			( e.clientY - drag.startClientY ) / scale / unitScale
		);
		const natW = img.naturalWidth || 2000;
		const natH = img.naturalHeight || 2000;
		const d = drag.dir;
		const minX = layer ? area.x : 0;
		const minY = layer ? area.y : 0;
		const maxX = layer ? area.x + area.w : natW;
		const maxY = layer ? area.y + area.h : natH;
		const maxW = layer ? maxX - entity.x : ( maxX - entity.x ) / unitScale;
		const maxH = layer ? maxY - entity.y : ( maxY - entity.y ) / unitScale;
		if ( drag.type === 'move' ) {
			entity.x = clamp( drag.startX + dx, minX, maxX - entity.w );
			entity.y = clamp( drag.startY + dy, minY, maxY - entity.h );
		} else {
			let nx = drag.startX,
				ny = drag.startY,
				nw = drag.startW,
				nh = drag.startH;
			if ( d.includes( 'e' ) ) {
				nw = Math.max( 1, drag.startW + dx );
			}
			if ( d.includes( 's' ) ) {
				nh = Math.max( 1, drag.startH + dy );
			}
			if ( d.includes( 'w' ) ) {
				nw = Math.max( 1, drag.startW - dx );
				nx = drag.startX + drag.startW - nw;
			}
			if ( d.includes( 'n' ) ) {
				nh = Math.max( 1, drag.startH - dy );
				ny = drag.startY + drag.startH - nh;
			}
			if ( ! layer && area.ratioLocked ) {
				const ratio = currentAspectRatio( area );
				if ( d === 'n' || d === 's' ) {
					nw = Math.max( 1, nh * ratio );
				} else {
					nh = Math.max( 1, nw / ratio );
				}
				if ( d.includes( 'w' ) ) {
					nx = drag.startX + drag.startW - nw;
				}
				if ( d.includes( 'n' ) ) {
					ny = drag.startY + drag.startH - nh;
				}
			}
			entity.x = clamp( nx, minX, maxX );
			entity.y = clamp( ny, minY, maxY );
			entity.w = Math.min( nw, maxW );
			entity.h = Math.min( nh, maxH );
			if ( ! layer && area.ratioLocked ) {
				const ratio = currentAspectRatio( area );
				if ( d === 'n' || d === 's' ) {
					entity.w = Math.min(
						maxW,
						Math.max( 1, Math.round( entity.h * ratio ) )
					);
				} else {
					entity.h = Math.min(
						maxH,
						Math.max( 1, Math.round( entity.w / ratio ) )
					);
				}
			}
			if ( ! layer && ! area.ratioLocked ) {
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
		const inputPrefix = layer ? 'oc-layer' : 'oc-prop';
		const readInt = ( id, fallback ) => {
			const value = parseInt(
				document.getElementById( id )?.value || fallback,
				10
			);
			return Number.isFinite( value ) ? value : fallback;
		};
		if ( changedId === inputPrefix + '-x' ) {
			entity.x = readInt( changedId, entity.x || 0 );
		}
		if ( changedId === inputPrefix + '-y' ) {
			entity.y = readInt( changedId, entity.y || 0 );
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
