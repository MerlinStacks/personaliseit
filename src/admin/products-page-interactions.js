/* eslint-disable @wordpress/no-global-active-element, no-alert */

import { unitPxScale } from '../shared/render-math';
import { LAYER_DEFAULTS, layerLabel } from './products-page-metadata';
import { layerTypeSupportsPrintMethod } from './products-page-compatibility';

export function createProductsPageInteractions( deps ) {
	const {
		addArea,
		clampLayerToArea,
		commitChange,
		defaultSettings,
		getAreas,
		getSelectedIndex,
		getSelectedLayerIndex,
		initCanvasInteractions,
		markDirty,
		normaliseArea,
		normaliseDpi,
		normaliseLayerDefaults,
		normaliseUnit,
		nextUid,
		openMockupPicker,
		redo,
		renderAll,
		renderGhosts,
		renderRatioLockButton,
		selectedArea,
		setSelectedIndex,
		setSelectedLayerIndex,
		snapshot,
		syncBoundsFromInputs,
		undo,
		updateAspectRatio,
		updateBoundsBox,
	} = deps;

	function initInteractions() {
		document
			.getElementById( 'oc-add-area-btn' )
			?.addEventListener( 'click', () => {
				const currentArea =
					getAreas()[ getSelectedIndex() ] || getAreas()[ 0 ] || {};
				addArea( {
					...normaliseArea(
						{
							id: 0,
							label: 'Print Area ' + ( getAreas().length + 1 ),
							method: currentArea.method,
							material: currentArea.material,
							unit: currentArea.unit,
							mockupId: currentArea.mockupId,
							mockupUrl: currentArea.mockupUrl,
							storedMockupId: currentArea.mockupId,
							dpi: currentArea.dpi,
							visible: true,
							locked: false,
						},
						getAreas().length
					),
					layers: [],
				} );
				setSelectedIndex( getAreas().length - 1 );
				setSelectedLayerIndex( -1 );
				snapshot();
				renderAll();
			} );
		document.querySelectorAll( '.oc-layer-type-btn' ).forEach( ( btn ) => {
			btn.addEventListener( 'click', () => {
				if ( getSelectedIndex() >= 0 && btn.dataset.type ) {
					addLayer( btn.dataset.type );
				}
			} );
		} );
		document
			.getElementById( 'oc-prop-label' )
			?.addEventListener( 'input', () => {
				const area = selectedArea();
				if ( area ) {
					area.label =
						document.getElementById( 'oc-prop-label' ).value;
					commitChange( { areasList: true, areaStrip: true } );
				}
			} );
		document
			.getElementById( 'oc-prop-method' )
			?.addEventListener( 'change', () => {
				const area = selectedArea();
				if ( area ) {
					const methodSelect =
						document.getElementById( 'oc-prop-method' );
					const nextMethod = methodSelect.value;
					const incompatibleLayer = ( area.layers || [] ).find(
						( layer ) =>
							! layerTypeSupportsPrintMethod(
								layer.type,
								nextMethod
							)
					);
					if ( incompatibleLayer ) {
						methodSelect.value = area.method;
						window.alert(
							`${ layerLabel(
								incompatibleLayer.type
							) } is not supported for this print method. Remove the layer before changing the print method.`
						);
						return;
					}
					area.method = nextMethod;
					if ( area.method === 'engraving' && ! area.material ) {
						area.material = 'silver_metal';
					}
					( area.layers || [] ).forEach( ( layer ) =>
						normaliseLayerDefaults( layer, area )
					);
					commitChange( { all: true } );
				}
			} );
		document
			.getElementById( 'oc-prop-engraving-material' )
			?.addEventListener( 'change', () => {
				const area = selectedArea();
				if ( area ) {
					area.material = document.getElementById(
						'oc-prop-engraving-material'
					).value;
					commitChange();
				}
			} );
		document
			.getElementById( 'oc-prop-unit' )
			?.addEventListener( 'change', () => {
				const area = selectedArea();
				if ( area ) {
					area.unit = normaliseUnit(
						document.getElementById( 'oc-prop-unit' ).value
					);
					updateBoundsBox();
					renderGhosts();
					commitChange();
				}
			} );
		document
			.getElementById( 'oc-prop-dpi' )
			?.addEventListener( 'input', () => {
				const area = selectedArea();
				if ( area ) {
					area.dpi = normaliseDpi(
						document.getElementById( 'oc-prop-dpi' ).value
					);
					updateBoundsBox();
					renderGhosts();
					commitChange();
				}
			} );
		document
			.getElementById( 'oc-prop-ratio-lock' )
			?.addEventListener( 'click', () => {
				const area = selectedArea();
				if ( area ) {
					area.ratioLocked = ! area.ratioLocked;
					updateAspectRatio( area );
					renderRatioLockButton( area );
					markDirty();
				}
			} );
		[
			'oc-prop-x',
			'oc-prop-y',
			'oc-prop-w',
			'oc-prop-h',
			'oc-prop-rotation',
		].forEach( ( id ) => {
			document.getElementById( id )?.addEventListener( 'input', () => {
				syncBoundsFromInputs( id );
			} );
		} );
		[ 'oc-layer-x', 'oc-layer-y', 'oc-layer-w', 'oc-layer-h' ].forEach(
			( id ) => {
				document
					.getElementById( id )
					?.addEventListener( 'input', () =>
						syncBoundsFromInputs( id )
					);
			}
		);
		document
			.getElementById( 'oc-choose-mockup-btn' )
			?.addEventListener( 'click', openMockupPicker );
		document
			.getElementById( 'oc-remove-mockup-btn' )
			?.addEventListener( 'click', () => {
				if ( getAreas().length ) {
					getAreas().forEach( ( area ) => {
						area.mockupId = 0;
						area.mockupUrl = '';
						area.storedMockupId = 0;
					} );
					snapshot();
					renderAll();
				}
			} );
		document
			.getElementById( 'oc-undo-btn' )
			?.addEventListener( 'click', undo );
		document
			.getElementById( 'oc-redo-btn' )
			?.addEventListener( 'click', redo );
		document
			.getElementById( 'oc-canvas-stage' )
			?.addEventListener( 'click', ( e ) => {
				if (
					e.target === document.getElementById( 'oc-canvas-stage' ) ||
					e.target ===
						document.getElementById( 'oc-canvas-mockup-img' ) ||
					e.target === document.getElementById( 'oc-canvas-ghosts' )
				) {
					if ( getSelectedLayerIndex() >= 0 ) {
						setSelectedLayerIndex( -1 );
						renderAll();
					}
				}
			} );
		document.addEventListener( 'keydown', ( e ) => {
			const tag = document.activeElement?.tagName?.toLowerCase();
			if ( tag === 'input' || tag === 'textarea' || tag === 'select' ) {
				return;
			}
			if ( e.ctrlKey || e.metaKey ) {
				if ( e.key === 'z' && ! e.shiftKey ) {
					e.preventDefault();
					undo();
				}
				if ( e.key === 'z' && e.shiftKey ) {
					e.preventDefault();
					redo();
				}
				if ( e.key === 'y' ) {
					e.preventDefault();
					redo();
				}
			}
		} );
		window.addEventListener( 'resize', () => {
			if ( getSelectedIndex() >= 0 ) {
				updateBoundsBox();
				renderGhosts();
			}
		} );
		document
			.getElementById( 'oc-canvas-mockup-img' )
			?.addEventListener( 'load', () => {
				updateBoundsBox();
				renderGhosts();
			} );
		initCanvasInteractions();
	}

	function addLayer( type ) {
		const area = selectedArea();
		if ( ! area || ! layerTypeSupportsPrintMethod( type, area.method ) ) {
			return;
		}
		const def = LAYER_DEFAULTS[ type ] || { w: 200, h: 100 };
		const px = unitPxScale( area );
		const lw = Math.max( 1, Math.round( def.w / px ) );
		const lh = Math.max( 1, Math.round( def.h / px ) );
		const lx = area.x + Math.max( 0, Math.round( ( area.w - lw ) / 2 ) );
		const ly = area.y + Math.max( 0, Math.round( ( area.h - lh ) / 2 ) );
		addLayerWithBounds( type, lx, ly, lw, lh );
	}

	function createLayer( type, area, x, y, w, h ) {
		const layer = {
			_uid: nextUid(),
			id: 0,
			type,
			label: layerLabel( type ) + ' ' + ( area.layers.length + 1 ),
			x,
			y,
			w,
			h,
			visible: true,
			locked: false,
			settings: defaultSettings( type ),
			sortOrder: area.layers.length,
		};
		clampLayerToArea( layer, area );
		return layer;
	}

	function addLayerWithBounds( type, x, y, w, h ) {
		const area = selectedArea();
		if ( ! area ) {
			return;
		}
		area.layers.push( createLayer( type, area, x, y, w, h ) );
		setSelectedLayerIndex( area.layers.length - 1 );
		snapshot();
		renderAll();
	}

	return { initInteractions, addLayerWithBounds };
}
