/**
 * Frontend Customiser — vanilla JS, no framework dependency.
 *
 * Data: window.ocCustomiserData (wp_localize_script).
 * Canvas: Fabric.js 6.x  |  Uploads: Uppy 4.x
 *
 * @package
 */

/* eslint-disable no-console, no-undef, no-unused-vars, no-nested-ternary, @wordpress/no-unused-vars-before-return */

import {
	StaticCanvas,
	FabricImage,
	FabricText,
	Textbox,
	Rect,
	Circle,
	Shadow,
	Pattern,
	filters as FabricFilters,
} from 'fabric';
import { createFont } from 'fonteditor-core';

import '@uppy/core/css/style.min.css';
import '@uppy/drag-drop/css/style.min.css';
import './customiser-app.scss';
import {
	displayBounds,
	displayFontSize,
	displayLayer,
} from '../shared/render-math';
import designVariantMethods from './customiser/design-variants';
import galleryPreviewMethods from './customiser/gallery-preview';
import clipartMethods from './customiser/clipart';
import preflightMethods from './customiser/preflight';
import spotifyMethods from './customiser/spotify';
import uploadMethods from './customiser/uploads';
import checkoutMethods from './customiser/checkout';

// ── Boot ──────────────────────────────────────────────────────────────────────

document.addEventListener( 'DOMContentLoaded', () => {
	const data = window.ocCustomiserData;
	if ( ! data || ! data.areas?.length ) {
		return;
	}
	new OCCustomiser( data ).init();
} );

// ── Main class ─────────────────────────────────────────────────────────────────

class OCCustomiser {
	constructor( data ) {
		this.data = data;
		this.areas = data.areas || [];
		this.fonts = data.fonts || [];
		this.layersById = {};
		this.areas.forEach( ( area ) =>
			( area.layers || [] ).forEach( ( layer ) => {
				this.layersById[ layer.id ] = layer;
			} )
		);
		this.designVariants = data.designVariants || [];
		this.selectedDesignVariant =
			data.selectedDesignVariant || this.designVariants[ 0 ]?.id || '';
		this.activeArea = 0;

		// Deep-clone mutable per-layer inputs; keys are integer layer IDs.
		this.inputs = {};
		Object.entries( data.layerInputs || {} ).forEach( ( [ k, v ] ) => {
			const layerId = parseInt( k, 10 );
			this.inputs[ layerId ] = { ...v };
			this.clampLayerInputValue( layerId );
		} );

		this.editMode = !! ( data.editMode && data.cartKey );
		this.cartKey = this.editMode ? data.cartKey : '';
		this.canvases = {}; // areaIndex → Fabric StaticCanvas
		this.fontCache = {}; // fontName  → load Promise
		this.outlineFontCache = {}; // fontName  → parsed font Promise
		this.clipartSvgCache = {};
		this.galleryImg = null; // the main <img> in the product gallery
		this._previewUrl = null; // saved preview URL (set just before cart submit)
		this._focusPreviewSlide = false; // jump TVPG to preview slide after user edits
		this._hasCustomerPersonalisation = this.editMode;
		this._tvpgPreviewLocked = false;
		this.productVariationStates = {};
		this._variationRequestSeq = 0;
		this.spotifyValidateTimers = {};
		this.spotifyValidateTokens = {};
		this.preflightRoot = null;
		this.clipartByGroup = {};
		this.clipartSearchTimers = {};
		this.clipartSearchTerms = {};
		this.clipartCategoryFilters = {};
		this.spotifyModalCloseTimer = null;
		this.mobileCartPreviewDialog = null;
		this.formSubmitBound = false;
		this.fontComboboxDocumentClickBound = false;

		if ( this.editMode ) {
			Object.entries( data.layerInputs || {} ).forEach( ( [ k, v ] ) => {
				const key = parseInt( k, 10 );
				if (
					this.inputs[ key ] &&
					typeof v === 'object' &&
					v !== null
				) {
					Object.assign( this.inputs[ key ], v );
					this.clampLayerInputValue( key );
				}
			} );
		}

		for ( const [ lidStr, items ] of Object.entries(
			data.clipartByLayer || {}
		) ) {
			const lid = parseInt( lidStr, 10 );
			for ( const item of items ) {
				for ( const gn of item.groupNames || [] ) {
					if ( ! this.clipartByGroup[ gn ] ) {
						this.clipartByGroup[ gn ] = {};
					}
					this.clipartByGroup[ gn ][ lid ] = item;
				}
			}
		}
	}

	restHeaders( extra = {} ) {
		const headers = {
			'X-WP-Nonce': this.data.uploadNonce,
			...extra,
		};
		if ( this.data.requestToken ) {
			headers[ 'X-OC-Token' ] = this.data.requestToken;
		}
		return headers;
	}

	uploadEndpoint( uploadUrl, layerId ) {
		const params = new URLSearchParams( {
			layer_id: String( layerId ),
			design_id: String( this.data.designId || '' ),
			product_id: String( this.data.productId || '' ),
		} );
		if ( this.data.uploadNonce ) {
			params.set( '_wpnonce', this.data.uploadNonce );
		}
		if ( this.data.requestToken ) {
			params.set( 'oc_token', this.data.requestToken );
		}
		return (
			uploadUrl +
			( uploadUrl.includes( '?' ) ? '&' : '?' ) +
			params.toString()
		);
	}

	// ── Init ───────────────────────────────────────────────────────────────────

	init() {
		this.findGalleryImage();
		this.preflightRoot = document.getElementById( 'oc-preflight-messages' );

		// Seed configured/default fonts for text layers so they render immediately.
		if ( this.fonts.length ) {
			const firstFont = this.fonts[ 0 ];
			this.areas.forEach( ( area ) => {
				( area.layers || [] ).forEach( ( layer ) => {
					if ( layer.type === 'text' || layer.type === 'textarea' ) {
						const inp = this.inputs[ layer.id ];
						if ( inp && ! inp.fontId ) {
							inp.fontId =
								layer.settings?.default_font_id || firstFont.id;
						}
					}
				} );
			} );
		}

		// Wire up controls IMMEDIATELY — don't block on canvas.
		this.setupInputListeners();
		this.setupVariationGalleryHandoff();
		this.setupCartGalleryUnlock();
		this.setupDesignVariantOptions();
		this.setupClipartCarousels();
		this.setupUploadZones();
		if ( this.editMode ) {
			this.updateInputsFromDOM();
		}
		this.setupFormSubmit();
		this.updateHiddenField();
		this.setupDesignVariantCarousel();
		this.renderDesignVariantThumbnails();

		// Canvas init runs in background; calls redraw() when done.
		this.initAllCanvases();
	}

	// ── Canvas initialisation ──────────────────────────────────────────────────

	async initAllCanvases() {
		for ( let i = 0; i < this.areas.length; i++ ) {
			const el = document.getElementById( `oc-canvas-${ i }` );
			if ( el ) {
				await this.initCanvas( el, i );
				// Full redraw AFTER init picks up any text the user already typed.
				await this.redraw( i );
			}
		}
	}

	async initCanvas( canvasEl, areaIndex ) {
		const area = this.areas[ areaIndex ];
		const bounds = this.areaBounds( area );

		// Use mockup natural width when available (works even when canvas is visually hidden).
		// Cap at 1200px for performance; fall back to element width or 600px.
		await new Promise( ( r ) => requestAnimationFrame( r ) );
		const displayW = area.mockupW
			? Math.min( area.mockupW, 1200 )
			: Math.max( canvasEl.parentElement?.offsetWidth || 0, 600 );

		if ( ! area.mockupUrl ) {
			this.canvases[ areaIndex ] = this.blankCanvas(
				canvasEl,
				displayW,
				240,
				'No mockup set. Add one in the Design Editor.'
			);
			this.canvases[ areaIndex ]._ocMissingMockup = true;
			return;
		}

		let mockupImg;
		try {
			// Do NOT use crossOrigin:'anonymous' — WordPress uploads are same-origin
			// and CORS headers aren't sent, which would taint the canvas and break toDataURL.
			mockupImg = await Promise.race( [
				FabricImage.fromURL( area.mockupUrl ),
				new Promise( ( _, rej ) =>
					setTimeout( () => rej( new Error( 'timeout' ) ), 10000 )
				),
			] );
		} catch ( e ) {
			console.warn(
				'[OC] Mockup failed to load:',
				area.mockupUrl,
				e.message
			);
			this.canvases[ areaIndex ] = this.blankCanvas(
				canvasEl,
				displayW,
				240,
				'Mockup image could not load.'
			);
			return;
		}

		const mockupEl = mockupImg.getElement?.();
		const sourceW =
			mockupEl?.naturalWidth || mockupImg.width || area.mockupW || 1;
		const sourceH =
			mockupEl?.naturalHeight || mockupImg.height || area.mockupH || 1;
		const coordW = area.mockupW || sourceW;
		const coordH = area.mockupH || sourceH;
		const scaleX = displayW / coordW;
		const displayH = Math.round( coordH * scaleX );
		const canvas = new StaticCanvas( canvasEl, {
			width: displayW,
			height: displayH,
		} );

		mockupImg.set( {
			left: 0,
			top: 0,
			originX: 'left',
			originY: 'top',
			scaleX: displayW / sourceW,
			scaleY: displayH / sourceH,
			selectable: false,
			evented: false,
		} );
		canvas.add( mockupImg );

		canvas._ocScaleX = scaleX;
		canvas._ocArea = area;
		canvas.renderAll();
		this.canvases[ areaIndex ] = canvas;
	}

	areaBounds( area ) {
		return {
			...( area?.bounds || {} ),
			unit: area?.bounds?.unit || area?.unit || 'px',
		};
	}

	areaCanvasGroupIndexes( areaIndex ) {
		const area = this.areas[ areaIndex ];
		const mockupUrl = area?.mockupUrl || '';
		if ( ! mockupUrl ) {
			return [ areaIndex ];
		}

		return this.areas
			.map( ( candidate, index ) =>
				( candidate?.mockupUrl || '' ) === mockupUrl ? index : -1
			)
			.filter( ( index ) => index >= 0 );
	}

	async rebuildCanvas( areaIndex ) {
		const oldCanvas = this.canvases[ areaIndex ];
		if ( oldCanvas?.dispose ) {
			oldCanvas.dispose();
		}
		delete this.canvases[ areaIndex ];

		const oldEl = document.getElementById( `oc-canvas-${ areaIndex }` );
		if ( ! oldEl ) {
			return;
		}

		const canvasEl = document.createElement( 'canvas' );
		canvasEl.id = oldEl.id;
		oldEl.replaceWith( canvasEl );

		await this.initCanvas( canvasEl, areaIndex );
		await this.redraw( areaIndex );
	}

	blankCanvas( el, w, h, msg ) {
		const c = new StaticCanvas( el, {
			width: w,
			height: h,
			backgroundColor: '#f0f0f0',
		} );
		const t = new FabricText( msg, {
			left: w / 2,
			top: h / 2,
			originX: 'center',
			originY: 'center',
			fontSize: 12,
			fill: '#888',
			fontFamily: 'sans-serif',
			textAlign: 'center',
			selectable: false,
		} );
		c.add( t );
		c.renderAll();
		c._ocScaleX = 1;
		return c;
	}

	// ── Redraw ──────────────────────────────────────────────────────────────────

	areaIndexForLayer( layerId ) {
		for ( let i = 0; i < this.areas.length; i++ ) {
			if (
				( this.areas[ i ]?.layers || [] ).some(
					( layer ) =>
						parseInt( layer.id, 10 ) === parseInt( layerId, 10 )
				)
			) {
				return i;
			}
		}
		return this.activeArea;
	}

	focusPreviewArea( areaIndex ) {
		const index = Number.isInteger( areaIndex )
			? areaIndex
			: this.activeArea;
		this.activeArea = Math.max(
			0,
			Math.min( this.areas.length - 1, index )
		);
		document.querySelectorAll( '.oc-area-tab' ).forEach( ( btn, i ) => {
			btn.classList.toggle( 'oc-active', i === this.activeArea );
			btn.setAttribute(
				'aria-selected',
				i === this.activeArea ? 'true' : 'false'
			);
			btn.setAttribute( 'tabindex', i === this.activeArea ? '0' : '-1' );
		} );
	}

	scheduleRedraw( areaIndex = this.activeArea ) {
		clearTimeout( this._redrawTimer );
		this.focusPreviewArea( areaIndex );
		this._redrawTimer = setTimeout(
			() => this.redraw( this.activeArea ),
			120
		);
	}

	async flushRedraw() {
		clearTimeout( this._redrawTimer );
		await this.redraw( this.activeArea );
	}

	async redraw( areaIndex, options = {} ) {
		const canvas = this.canvases[ areaIndex ];
		if ( ! canvas ) {
			return;
		} // canvas not ready yet — will redraw after initCanvas

		// Remove previously added content objects.
		[ ...canvas.getObjects() ]
			.filter( ( o ) => o._ocContent === true )
			.forEach( ( o ) => canvas.remove( o ) );

		const groupIndexes =
			options.renderGroup === false
				? [ areaIndex ]
				: this.areaCanvasGroupIndexes( areaIndex );
		for ( const groupIndex of groupIndexes ) {
			const area = this.areas[ groupIndex ];
			for ( const layer of area?.layers ?? [] ) {
				// PHP already filters to visible-only layers — no client-side check needed.
				try {
					await this.renderLayer(
						canvas,
						layer,
						this.inputs[ layer.id ] || {},
						area
					);
				} catch ( err ) {
					console.warn( '[OC] Layer render failed:', layer?.id, err );
				}
			}
		}

		canvas.renderAll();
		if (
			options.pushGallery !== false &&
			areaIndex === this.activeArea &&
			! canvas._ocMissingMockup
		) {
			this.pushToGallery( canvas );
		}
	}

	async renderLayer( canvas, layer, input, area ) {
		const scale = canvas._ocScaleX ?? 1;
		const areaBounds = this.areaBounds( area );
		const bounds = displayBounds( areaBounds );
		const layerBox = displayLayer( layer, areaBounds );
		const rotation = Number( bounds.rotation ) || 0;
		const contentClip = () =>
			this.printAreaClipPath( bounds, scale, layerBox );
		const center = this.rotatedLayerCenter( layerBox, bounds, rotation );
		const lx = ( center.x - layerBox.w / 2 ) * scale;
		const ly = ( center.y - layerBox.h / 2 ) * scale;
		const lw = Math.max( layerBox.w * scale, 10 );
		const lh = Math.max( layerBox.h * scale, 10 );
		const textClip = ( pad = 0 ) =>
			this.rectClipPath(
				lx - pad,
				ly - pad,
				lw + pad * 2,
				lh + pad * 2,
				rotation
			);
		const lcX = center.x * scale;
		const lcY = center.y * scale;
		const isEngraving = area?.printMethod === 'engraving';
		const isEmbroidery = area?.printMethod === 'embroidery';
		const engravingPalette = this.engravingPalette(
			area?.engravingMaterial
		);
		const fontLimit = ( value ) => this.fontLimit( value );
		const clampFontSize = ( size, settings ) => {
			const minLimit = fontLimit( settings?.min_font_size );
			const maxLimit = fontLimit( settings?.max_font_size );
			const min = minLimit
				? displayFontSize( minLimit, areaBounds, scale )
				: 0;
			const max = maxLimit
				? displayFontSize( maxLimit, areaBounds, scale )
				: 0;
			if ( max && ( ! min || min <= max ) ) {
				size = Math.min( size, max );
			}
			if ( min ) {
				size = Math.max( size, min );
			}
			return size;
		};

		switch ( layer.type ) {
			case 'text':
			case 'textarea': {
				const isSingleLineText = layer.type === 'text';
				const normalisedText = (
					isEngraving || isEmbroidery
						? this.stripUnsupportedPrintEmoji( input.value )
						: input.value || ''
				).replace( /\r\n?/g, '\n' );
				const raw = isSingleLineText
					? normalisedText.trim()
					: normalisedText;
				if ( ! raw.trim() ) {
					break;
				}
				const lineAlign = [ 'top', 'center', 'bottom' ].includes(
					layer.settings?.line_alignment
				)
					? layer.settings.line_alignment
					: 'top';

				let font = this.fonts.find(
					( f ) => f.id === ( input.fontId || 0 )
				);
				// Engraving uses a fixed silver tone instead of a customer-selected colour.
				const color = isEngraving
					? engravingPalette.text
					: input.colorHex ||
					  layer.settings?.default_color ||
					  '#000000';
				const align = layer.settings?.alignment || 'center';
				const anchorPad = Math.max( 2, Math.min( 10, lw * 0.01 ) );
				if ( font ) {
					try {
						await this.loadFont( font );
					} catch ( err ) {
						console.warn(
							'[OC] Font load failed, falling back to sans-serif:',
							err
						);
						font = null;
					}
				}

				const minLimit = fontLimit( layer.settings?.min_font_size );
				const minFontSize = minLimit
					? displayFontSize( minLimit, areaBounds, scale )
					: 0;
				const configuredFontSize =
					input.fontSize || layer.settings?.default_font_size;
				let fontSize = configuredFontSize
					? clampFontSize(
							displayFontSize(
								parseInt( configuredFontSize, 10 ),
								areaBounds,
								scale
							),
							layer.settings
					  )
					: clampFontSize(
							Math.max( 10, Math.round( lh * 0.42 ) ),
							layer.settings
					  );
				let textPadding = this.textRenderPadding( fontSize );
				const textFill = isEmbroidery
					? this.embroideryPattern( color, fontSize )
					: isEngraving && engravingPalette.grainPattern
					? this.woodEngravingPattern( fontSize )
					: color;
				const textClass = isSingleLineText ? FabricText : Textbox;
				const textBoxSize = isSingleLineText ? {} : { width: lw };
				const singleLineMaxWidth = Math.max( 1, lw - anchorPad * 2 );
				const singleLineMaxHeight = Math.max( 1, lh );
				const obj = new textClass( raw, {
					left: lcX,
					top: lcY,
					originX: 'center',
					originY: 'center',
					...textBoxSize,
					padding: textPadding,
					angle: rotation,
					fontFamily: font?.name || 'sans-serif',
					fontSize,
					fill: textFill,
					textAlign: align,
					selectable: false,
					evented: false,
					objectCaching: false,
				} );
				obj._ocContent = true; // tag after creation
				let stitchPad = null;
				let stitchLift = null;
				const textareaPosition = ( target, extraX = 0, extraY = 0 ) => {
					if ( isSingleLineText || ! target ) {
						return;
					}

					target.initDimensions?.();
					const contentH = Math.min(
						Math.max(
							Number(
								target.getScaledHeight?.() || target.height || 0
							),
							0
						),
						lh
					);
					const freeY = Math.max( 0, ( lh - contentH ) / 2 );
					const localY =
						lineAlign === 'bottom'
							? freeY
							: lineAlign === 'center'
							? 0
							: -freeY;
					const rad = ( rotation * Math.PI ) / 180;

					target.set( {
						left: lcX - localY * Math.sin( rad ) + extraX,
						top: lcY + localY * Math.cos( rad ) + extraY,
					} );
					target.setCoords?.();
				};

				if ( isEngraving ) {
					// Fake etched depth: subtle light highlight below + soft dark shadow above.
					obj.set( {
						opacity: engravingPalette.opacity,
						globalCompositeOperation:
							engravingPalette.composite || 'source-over',
						shadow: new Shadow( {
							color: engravingPalette.highlight,
							offsetX: 0,
							offsetY: 1,
							blur: 1,
						} ),
					} );
				} else if ( isEmbroidery ) {
					const threadLift = this.embroideryHighlightColor( color );
					const threadShadow = this.embroideryShadowColor( color );

					stitchPad = new textClass( raw, {
						left: lcX + Math.max( 0.45, fontSize * 0.015 ),
						top: lcY + Math.max( 0.65, fontSize * 0.02 ),
						originX: 'center',
						originY: 'center',
						...textBoxSize,
						padding: textPadding,
						angle: rotation,
						fontFamily: font?.name || 'sans-serif',
						fontSize,
						fill: threadShadow,
						opacity: 0.24,
						shadow: new Shadow( {
							color: 'rgba(0,0,0,0.22)',
							offsetX: 0.6,
							offsetY: 0.9,
							blur: 1.8,
						} ),
						textAlign: align,
						selectable: false,
						evented: false,
						objectCaching: false,
					} );
					stitchPad._ocContent = true;
					canvas.add( stitchPad );

					stitchLift = new textClass( raw, {
						left: lcX - Math.max( 0.25, fontSize * 0.006 ),
						top: lcY - Math.max( 0.25, fontSize * 0.006 ),
						originX: 'center',
						originY: 'center',
						...textBoxSize,
						padding: textPadding,
						angle: rotation,
						fontFamily: font?.name || 'sans-serif',
						fontSize,
						fill: 'rgba(255,255,255,0)',
						stroke: threadLift,
						strokeWidth: Math.max( 0.2, fontSize * 0.006 ),
						opacity: 0.22,
						textAlign: align,
						selectable: false,
						evented: false,
						objectCaching: false,
					} );
					stitchLift._ocContent = true;
					canvas.add( stitchLift );

					obj.set( {
						stroke: this.embroiderySoftEdgeColor( color ),
						strokeWidth: Math.max( 0.18, fontSize * 0.005 ),
						shadow: new Shadow( {
							color: 'rgba(0,0,0,0.22)',
							offsetX: 0.7,
							offsetY: 0.95,
							blur: 1.1,
						} ),
					} );
				}

				const fitsTextLayer = ( size ) => {
					if ( isSingleLineText ) {
						return this.textFitsBox(
							raw,
							font,
							size,
							layer.settings,
							singleLineMaxWidth,
							singleLineMaxHeight,
							false
						);
					}

					return this.textFitsBox(
						raw,
						font,
						size,
						layer.settings,
						lw,
						lh,
						true
					);
				};
				const fittingFloor = minFontSize || 4;
				while (
					! fitsTextLayer( fontSize ) &&
					fontSize > fittingFloor
				) {
					fontSize = Math.max( fittingFloor, fontSize - 1 );
					textPadding = this.textRenderPadding( fontSize );
					obj.set( { fontSize, padding: textPadding } );
					if ( stitchPad ) {
						stitchPad.set( { fontSize, padding: textPadding } );
					}
					if ( stitchLift ) {
						stitchLift.set( { fontSize, padding: textPadding } );
					}
				}
				obj.initDimensions?.();
				const textareaScale = isSingleLineText ? 1 : 1;
				if ( ! isSingleLineText ) {
					obj.set( { scaleX: textareaScale, scaleY: textareaScale } );
				}
				textareaPosition( obj );
				obj.setCoords?.();
				const measuredText = this.measureSingleLineText(
					raw,
					font,
					fontSize,
					layer.settings
				);
				const renderedWidth = Math.max(
					1,
					Math.ceil( measuredText.width + textPadding * 2 )
				);
				const singleLineScaleX = isSingleLineText
					? Math.min( 1, singleLineMaxWidth / renderedWidth )
					: 1;
				if ( isSingleLineText ) {
					let alignedLeft = lcX;
					let alignedTop = lcY;
					let alignmentOffset = 0;
					const renderedDisplayWidth =
						renderedWidth * singleLineScaleX;

					if ( align === 'left' ) {
						alignmentOffset =
							-lw / 2 + anchorPad + renderedDisplayWidth / 2;
					} else if ( align === 'right' ) {
						alignmentOffset =
							lw / 2 - anchorPad - renderedDisplayWidth / 2;
					}

					if ( alignmentOffset ) {
						const rad = ( rotation * Math.PI ) / 180;
						alignedLeft += alignmentOffset * Math.cos( rad );
						alignedTop += alignmentOffset * Math.sin( rad );
					}

					obj.set( {
						left: alignedLeft,
						top: alignedTop,
						scaleX: singleLineScaleX,
					} );
					obj.initDimensions?.();
					obj.setCoords?.();
					this.centerObjectBounds(
						obj,
						alignedLeft,
						alignedTop,
						rotation
					);
					this.keepObjectInsidePrintArea( obj, bounds, scale );
				}
				if ( isEmbroidery ) {
					obj.set( {
						fill: this.embroideryPattern( color, fontSize ),
					} );
				}
				const textClipPath = textClip(
					this.textClipPadding( fontSize )
				);

				if ( stitchPad ) {
					const padX = Math.max( 0.45, fontSize * 0.015 );
					const padY = Math.max( 0.65, fontSize * 0.02 );
					stitchPad.set( {
						left: ( isSingleLineText ? obj.left : lcX ) + padX,
						top: ( isSingleLineText ? obj.top : lcY ) + padY,
						fontSize,
						padding: textPadding,
					} );
					if ( isSingleLineText ) {
						stitchPad.set( { scaleX: singleLineScaleX } );
					} else {
						stitchPad.set( {
							scaleX: textareaScale,
							scaleY: textareaScale,
						} );
						textareaPosition( stitchPad, padX, padY );
					}
					this.applyContentClip( stitchPad, textClipPath );
				}
				if ( stitchLift ) {
					const liftX = Math.max( 0.25, fontSize * 0.006 );
					const liftY = Math.max( 0.25, fontSize * 0.006 );
					stitchLift.set( {
						left: ( isSingleLineText ? obj.left : lcX ) - liftX,
						top: ( isSingleLineText ? obj.top : lcY ) - liftY,
						fontSize,
						padding: textPadding,
						strokeWidth: Math.max( 0.2, fontSize * 0.006 ),
					} );
					if ( isSingleLineText ) {
						stitchLift.set( { scaleX: singleLineScaleX } );
					} else {
						stitchLift.set( {
							scaleX: textareaScale,
							scaleY: textareaScale,
						} );
						textareaPosition( stitchLift, -liftX, -liftY );
					}
					this.applyContentClip( stitchLift, textClipPath );
				}
				this.applyContentClip( obj, textClipPath );
				canvas.add( obj );
				break;
			}

			case 'image':
				if ( input.attachmentUrl ) {
					await this.renderFabricImg(
						canvas,
						input.attachmentUrl,
						lx,
						ly,
						lw,
						lh,
						isEngraving,
						'anonymous',
						false,
						rotation,
						engravingPalette,
						contentClip()
					);
				}
				break;

			case 'clipmask':
				if ( input.attachmentUrl ) {
					await this.renderFabricImg(
						canvas,
						input.attachmentUrl,
						lx,
						ly,
						lw,
						lh,
						isEngraving,
						'anonymous',
						false,
						rotation,
						engravingPalette,
						this.layerClipPath(
							lx,
							ly,
							lw,
							lh,
							rotation,
							layer.settings
						),
						'cover'
					);
				}
				break;

			case 'clipart':
				if ( input.clipartUrl ) {
					const selectedClipartColor = String(
						input.colorHex || ''
					).trim();
					const shouldRecolourClipart =
						input.clipartRecolourable &&
						( isEngraving || isEmbroidery );
					const clipartColor = shouldRecolourClipart
						? isEngraving
							? engravingPalette.text
							: selectedClipartColor
						: '';
					const clipartUrl = clipartColor
						? await this.recolourSvgClipartUrl(
								input.clipartUrl,
								clipartColor,
								isEmbroidery ? 'embroidery' : ''
						  )
						: await this.normaliseSvgClipartUrl( input.clipartUrl );
					const clipartCrossOrigin = clipartUrl.startsWith( 'data:' )
						? ''
						: 'anonymous';
					const clipartEffects = isEmbroidery
						? {
								embroideryColor:
									clipartColor ||
									selectedClipartColor ||
									'#000000',
						  }
						: shouldRecolourClipart
						? { preserveRecolouredPixels: true }
						: {};
					await this.renderFabricImg(
						canvas,
						clipartUrl,
						lx,
						ly,
						lw,
						lh,
						isEngraving,
						clipartCrossOrigin,
						false,
						rotation,
						engravingPalette,
						contentClip(),
						'contain',
						'',
						clipartEffects
					);
				}
				break;

			case 'lineart': {
				const lineartColor = isEngraving
					? engravingPalette.text
					: String( input.colorHex || '' ).trim();
				if ( ! lineartColor ) {
					break;
				}
				const r = new Rect( {
					left: lcX,
					top: lcY,
					originX: 'center',
					originY: 'center',
					angle: rotation,
					width: lw,
					height: lh,
					fill: lineartColor,
					opacity: 0.6,
					selectable: false,
					evented: false,
				} );
				r._ocContent = true;
				this.applyContentClip( r, contentClip() );
				canvas.add( r );
				break;
			}

			case 'spotify': {
				const val = ( input.value || '' ).trim();
				if ( ! val ) {
					break;
				}

				if (
					input.spotifyStatus === 'invalid_format' ||
					input.spotifyStatus === 'playlist_private_or_invalid' ||
					input.spotifyStatus === 'invalid_or_unavailable'
				) {
					const invalidText =
						input.spotifyStatus === 'playlist_private_or_invalid'
							? 'Private / invalid Spotify playlist'
							: 'Invalid Spotify link';
					const invalidObj = new FabricText( invalidText, {
						left: lcX,
						top: lcY,
						originX: 'center',
						originY: 'center',
						angle: rotation,
						fontFamily: 'monospace',
						fontSize: Math.max( 9, Math.round( lh * 0.17 ) ),
						fill: '#b32d2e',
						textAlign: 'center',
						selectable: false,
						evented: false,
					} );
					invalidObj._ocContent = true;
					this.applyContentClip( invalidObj, contentClip() );
					canvas.add( invalidObj );
					break;
				}

				const spotifyCodeUrl = this.buildSpotifyCodeUrl(
					input.spotifyUri || val,
					isEngraving,
					engravingPalette
				);
				if ( spotifyCodeUrl ) {
					// Try CORS-safe load first; if Spotify CDN blocks CORS for this origin,
					// retry without crossOrigin so users still see the scannable in live preview.
					let rendered = await this.renderFabricImg(
						canvas,
						spotifyCodeUrl,
						lx,
						ly,
						lw,
						lh,
						isEngraving,
						'anonymous',
						true,
						rotation,
						engravingPalette,
						contentClip()
					);
					if ( ! rendered ) {
						rendered = await this.renderFabricImg(
							canvas,
							spotifyCodeUrl,
							lx,
							ly,
							lw,
							lh,
							isEngraving,
							'',
							true,
							rotation,
							engravingPalette,
							contentClip()
						);
					}
					if ( rendered ) {
						break;
					}
				}

				const fallback = new FabricText(
					'\u266b Spotify code unavailable',
					{
						left: lcX,
						top: lcY,
						originX: 'center',
						originY: 'center',
						angle: rotation,
						fontFamily: 'monospace',
						fontSize: Math.max( 9, Math.round( lh * 0.22 ) ),
						fill: '#666666',
						textAlign: 'center',
						selectable: false,
						evented: false,
					}
				);
				fallback._ocContent = true;
				this.applyContentClip( fallback, contentClip() );
				canvas.add( fallback );
				break;
			}
		}
	}

	fontLimit( value ) {
		return Math.max( 0, parseInt( value, 10 ) || 0 );
	}

	textRenderPadding( fontSize ) {
		return Math.max( 4, Math.ceil( ( Number( fontSize ) || 0 ) * 0.18 ) );
	}

	textClipPadding( fontSize ) {
		return Math.max( 2, Math.ceil( ( Number( fontSize ) || 0 ) * 0.18 ) );
	}

	textFitSafetyMargin( fontSize ) {
		const size = Number( fontSize ) || 0;

		return {
			x: Math.max( 1, Math.ceil( size * 0.06 ) ),
			y: Math.max( 2, Math.ceil( size * 0.12 ) ),
		};
	}

	textFitsBox(
		raw,
		font,
		fontSize,
		settings,
		maxW,
		maxH,
		multiline = false
	) {
		if ( ! raw ) {
			return true;
		}

		const margin = this.textFitSafetyMargin( fontSize );
		const textClass = multiline ? Textbox : FabricText;
		const textBoxSize = multiline ? { width: Math.max( 1, maxW ) } : {};
		const obj = new textClass( raw, {
			left: 0,
			top: 0,
			originX: 'center',
			originY: 'center',
			...textBoxSize,
			fontFamily: font?.name || 'sans-serif',
			fontSize,
			textAlign: settings?.alignment || 'center',
			selectable: false,
			evented: false,
		} );
		obj.initDimensions?.();
		obj.setCoords?.();
		const measured = obj.getBoundingRect?.( true, true ) || obj;

		if ( multiline ) {
			return (
				Number( measured.height || 0 ) + margin.y * 2 <=
				Math.max( maxH, 10 )
			);
		}

		return (
			Number( measured.width || 0 ) + margin.x * 2 <=
				Math.max( maxW, 10 ) &&
			Number( measured.height || 0 ) + margin.y * 2 <=
				Math.max( maxH, 10 )
		);
	}

	textObjectFitsBox( obj, maxW, maxH, fontSize ) {
		if ( ! obj ) {
			return true;
		}

		obj.initDimensions?.();
		const margin = this.textFitSafetyMargin( fontSize );

		return (
			Number( obj.width || 0 ) <= Math.max( maxW, 10 ) &&
			Number( obj.height || 0 ) + margin.y * 2 <= Math.max( maxH, 10 )
		);
	}

	measureSingleLineText( raw, font, fontSize, settings = {} ) {
		const obj = new FabricText( raw || '', {
			left: 0,
			top: 0,
			originX: 'left',
			originY: 'top',
			fontFamily: font?.name || 'sans-serif',
			fontSize,
			textAlign: settings?.alignment || 'center',
			selectable: false,
			evented: false,
		} );
		obj.setCoords?.();
		const measured = obj.getBoundingRect?.( true, true ) || obj;

		return {
			width: Number( measured.width || 0 ),
			height: Number( measured.height || 0 ),
		};
	}

	textLayerFitsAtSize( layer, raw, font, fontSize ) {
		const area = this.areas[ this.areaIndexForLayer( layer?.id ) ];
		const bounds = area ? this.areaBounds( area ) : null;
		const layerBox = bounds ? displayLayer( layer, bounds ) : layer;
		const displaySize = bounds
			? displayFontSize( fontSize, bounds )
			: fontSize;

		return this.textFitsBox(
			raw,
			font,
			displaySize,
			layer?.settings || {},
			Number( layerBox?.w || 0 ),
			Number( layerBox?.h || 0 ),
			layer?.type === 'textarea'
		);
	}

	async maxFittingFontSize( layerId, upperLimit ) {
		const layer = this.getLayerById( layerId );
		if ( ! layer || ! [ 'text', 'textarea' ].includes( layer.type ) ) {
			return upperLimit;
		}
		const maxLimit = this.fontLimit( layer.settings?.max_font_size );
		if ( maxLimit ) {
			upperLimit = Math.min( upperLimit, maxLimit );
		}

		const input = this.inputs[ layerId ] || {};
		const normalisedText = String( input.value || '' ).replace(
			/\r\n?/g,
			'\n'
		);
		const raw =
			layer.type === 'text' ? normalisedText.trim() : normalisedText;
		if ( ! raw.trim() ) {
			return upperLimit;
		}

		let font = this.fonts.find(
			( f ) =>
				f.id ===
				( input.fontId || layer.settings?.default_font_id || 0 )
		);
		if ( font ) {
			try {
				await this.loadFont( font );
			} catch {
				font = null;
			}
		}

		const min = this.fontLimit( layer.settings?.min_font_size ) || 1;
		let low = min;
		let high = Math.max( min, upperLimit );
		let best = min;

		while ( low <= high ) {
			const mid = Math.floor( ( low + high ) / 2 );
			if ( this.textLayerFitsAtSize( layer, raw, font, mid ) ) {
				best = mid;
				low = mid + 1;
			} else {
				high = mid - 1;
			}
		}

		return Math.max( min, Math.min( upperLimit, best ) );
	}

	async updateTextSizeSliderCap( layerId, clampValue = true ) {
		const sizeEl = document.querySelector(
			`[data-oc-layer-font-size="${ layerId }"]`
		);
		if ( ! sizeEl ) {
			return;
		}

		if ( ! sizeEl.dataset.ocOriginalMax ) {
			sizeEl.dataset.ocOriginalMax = sizeEl.max || '200';
		}
		const originalMax = Math.max(
			parseInt( sizeEl.dataset.ocOriginalMax, 10 ) || 200,
			parseInt( sizeEl.min, 10 ) || 1
		);
		const layer = this.getLayerById( layerId );
		const configuredMax = this.fontLimit( layer?.settings?.max_font_size );
		let cappedMax = Math.max(
			parseInt( sizeEl.min, 10 ) || 1,
			configuredMax ? Math.min( originalMax, configuredMax ) : originalMax
		);
		cappedMax = await this.maxFittingFontSize( layerId, cappedMax );

		sizeEl.max = String( cappedMax );
		if ( clampValue && parseInt( sizeEl.value, 10 ) > cappedMax ) {
			sizeEl.value = String( cappedMax );
			if ( ! this.inputs[ layerId ] ) {
				this.inputs[ layerId ] = {};
			}
			this.inputs[ layerId ].fontSize = cappedMax;
		}

		document
			.querySelector(
				`.oc-range-value[data-oc-range-value="${ layerId }"]`
			)
			?.replaceChildren( document.createTextNode( sizeEl.value ) );
	}

	rotatedLayerCenter( layer, bounds, rotation ) {
		let x = layer.x + layer.w / 2;
		let y = layer.y + layer.h / 2;
		if ( ! bounds?.w || ! rotation ) {
			return { x, y };
		}

		const cx = bounds.x + bounds.w / 2;
		const cy = bounds.y + bounds.h / 2;
		const rad = ( rotation * Math.PI ) / 180;
		const dx = x - cx;
		const dy = y - cy;

		x = cx + dx * Math.cos( rad ) - dy * Math.sin( rad );
		y = cy + dx * Math.sin( rad ) + dy * Math.cos( rad );
		return { x, y };
	}

	engravingPalette( material = 'silver_metal' ) {
		const palettes = {
			glass: {
				text: '#eef4f4',
				imageTint: '#eef4f4',
				bg: 'F7FAFA',
				highlight: 'rgba(255,255,255,0.7)',
				brightness: 0.16,
				contrast: -0.04,
				opacity: 0.62,
			},
			gold_metal: {
				text: '#6f5227',
				imageTint: '#6f5227',
				bg: 'D9A72E',
				highlight: 'rgba(255,238,176,0.34)',
				brightness: -0.18,
				contrast: 0.22,
				opacity: 0.88,
			},
			silver_metal: {
				text: '#c9c9c3',
				imageTint: '#c9c9c3',
				bg: 'ECEFF1',
				highlight: 'rgba(255,255,255,0.42)',
				brightness: -0.28,
				contrast: 0.18,
				opacity: 0.9,
			},
			black_metal: {
				text: '#d8d8d8',
				imageTint: '#d8d8d8',
				bg: '1F2328',
				highlight: 'rgba(255,255,255,0.24)',
				brightness: -0.34,
				contrast: 0.28,
				opacity: 0.95,
			},
			wood: {
				text: 'rgba(78,42,20,0.7)',
				imageTint: '#5d3922',
				bg: 'C8A06B',
				highlight: 'rgba(255,225,180,0.16)',
				brightness: -0.16,
				contrast: 0.2,
				opacity: 0.72,
				tintAlpha: 0.72,
				composite: 'multiply',
				grainPattern: true,
			},
		};

		return palettes[ material ] || palettes.silver_metal;
	}

	woodEngravingPattern( fontSize = 24 ) {
		const source = document.createElement( 'canvas' );
		const width = Math.max(
			42,
			Math.min( 96, Math.round( fontSize * 1.9 ) )
		);
		const height = Math.max(
			14,
			Math.min( 30, Math.round( fontSize * 0.48 ) )
		);
		source.width = width;
		source.height = height;

		const ctx = source.getContext( '2d' );
		if ( ! ctx ) {
			return 'rgba(78,42,20,0.7)';
		}

		ctx.fillStyle = 'rgba(78,42,20,0.64)';
		ctx.fillRect( 0, 0, width, height );

		for ( let y = 1; y < height; y += 4 ) {
			ctx.strokeStyle = 'rgba(255,220,165,0.16)';
			ctx.lineWidth = 1;
			ctx.beginPath();
			ctx.moveTo( 0, y + ( y % 3 ) * 0.25 );
			ctx.bezierCurveTo(
				width * 0.28,
				y - 1.3,
				width * 0.62,
				y + 1.2,
				width,
				y - 0.4
			);
			ctx.stroke();

			ctx.strokeStyle = 'rgba(54,26,12,0.18)';
			ctx.beginPath();
			ctx.moveTo( 0, y + 1.8 );
			ctx.bezierCurveTo(
				width * 0.32,
				y + 2.8,
				width * 0.7,
				y + 0.8,
				width,
				y + 1.6
			);
			ctx.stroke();
		}

		return new Pattern( { source, repeat: 'repeat' } );
	}

	embroideryPattern( color, fontSize = 24 ) {
		const source = document.createElement( 'canvas' );
		const size = Math.max(
			10,
			Math.min( 20, Math.round( fontSize * 0.18 ) )
		);
		source.width = size;
		source.height = size;

		const ctx = source.getContext( '2d' );
		if ( ! ctx ) {
			return color;
		}

		const rgb = this.hexToRgb( color ) || { r: 0, g: 0, b: 0 };
		const hi = {
			r: Math.min( 255, rgb.r + 92 ),
			g: Math.min( 255, rgb.g + 92 ),
			b: Math.min( 255, rgb.b + 92 ),
		};
		const lo = {
			r: Math.max( 0, rgb.r - 78 ),
			g: Math.max( 0, rgb.g - 78 ),
			b: Math.max( 0, rgb.b - 78 ),
		};

		const base = ctx.createLinearGradient(
			0,
			0,
			source.width,
			source.height
		);
		base.addColorStop( 0, `rgb(${ hi.r },${ hi.g },${ hi.b })` );
		base.addColorStop( 0.42, color );
		base.addColorStop( 1, `rgb(${ lo.r },${ lo.g },${ lo.b })` );
		ctx.fillStyle = base;
		ctx.fillRect( 0, 0, source.width, source.height );

		ctx.lineWidth = Math.max( 1.2, size * 0.12 );
		ctx.lineCap = 'round';
		const stitchGap = Math.max( 2.4, size * 0.24 );
		let stitchIndex = 0;
		for ( let i = -source.height; i < source.width * 2; i += stitchGap ) {
			ctx.strokeStyle =
				stitchIndex % 2
					? `rgba(${ hi.r },${ hi.g },${ hi.b },0.48)`
					: `rgba(${ lo.r },${ lo.g },${ lo.b },0.24)`;
			ctx.beginPath();
			ctx.moveTo( i, source.height + 1.5 );
			ctx.lineTo( i + source.height + 1.5, -1.5 );
			ctx.stroke();
			stitchIndex += 1;
		}

		ctx.lineWidth = Math.max( 0.45, size * 0.045 );
		ctx.strokeStyle = `rgba(${ lo.r },${ lo.g },${ lo.b },0.16)`;
		for (
			let i = -source.height;
			i < source.width * 2;
			i += Math.max( 3.2, size * 0.32 )
		) {
			ctx.beginPath();
			ctx.moveTo( i, source.height + 1 );
			ctx.lineTo( i + source.height + 1, -1 );
			ctx.stroke();
		}

		ctx.lineWidth = 0.5;
		ctx.strokeStyle = 'rgba(255,255,255,0.14)';
		for ( let y = 1; y < source.height; y += 4 ) {
			ctx.beginPath();
			ctx.moveTo( 0, y + 0.5 );
			ctx.lineTo( source.width, y + 0.5 );
			ctx.stroke();
		}

		return new Pattern( { source, repeat: 'repeat' } );
	}

	embroiderySoftEdgeColor( color ) {
		const rgb = this.hexToRgb( color );
		if ( ! rgb ) {
			return 'rgba(0,0,0,0.16)';
		}

		return `rgba(${ Math.max( 0, rgb.r - 36 ) },${ Math.max(
			0,
			rgb.g - 36
		) },${ Math.max( 0, rgb.b - 36 ) },0.24)`;
	}

	embroideryHighlightColor( color ) {
		const rgb = this.hexToRgb( color );
		if ( ! rgb ) {
			return 'rgba(255,255,255,0.42)';
		}

		return `rgba(${ Math.min( 255, rgb.r + 88 ) },${ Math.min(
			255,
			rgb.g + 88
		) },${ Math.min( 255, rgb.b + 88 ) },0.62)`;
	}

	embroideryShadowColor( color ) {
		const rgb = this.hexToRgb( color );
		if ( ! rgb ) {
			return 'rgba(0,0,0,0.42)';
		}

		return `rgba(${ Math.max( 0, rgb.r - 96 ) },${ Math.max(
			0,
			rgb.g - 96
		) },${ Math.max( 0, rgb.b - 96 ) },0.72)`;
	}

	hexToRgb( color ) {
		const value = String( color || '' ).trim();
		const match = value.match( /^#([0-9a-f]{3}|[0-9a-f]{6})$/i );
		if ( ! match ) {
			return null;
		}

		const hex =
			match[ 1 ].length === 3
				? match[ 1 ]
						.split( '' )
						.map( ( char ) => char + char )
						.join( '' )
				: match[ 1 ];

		return {
			r: parseInt( hex.slice( 0, 2 ), 16 ),
			g: parseInt( hex.slice( 2, 4 ), 16 ),
			b: parseInt( hex.slice( 4, 6 ), 16 ),
		};
	}

	printAreaClipPath( bounds, scale ) {
		if ( ! bounds || ! bounds.w || ! bounds.h ) {
			return null;
		}

		return new Rect( {
			left: ( Number( bounds.x ) + Number( bounds.w ) / 2 ) * scale,
			top: ( Number( bounds.y ) + Number( bounds.h ) / 2 ) * scale,
			originX: 'center',
			originY: 'center',
			angle: Number( bounds.rotation ) || 0,
			width: Number( bounds.w ) * scale,
			height: Number( bounds.h ) * scale,
			absolutePositioned: true,
		} );
	}

	rectClipPath( x, y, w, h, angle = 0 ) {
		if ( ! w || ! h ) {
			return null;
		}

		return new Rect( {
			left: x + w / 2,
			top: y + h / 2,
			originX: 'center',
			originY: 'center',
			angle,
			width: w,
			height: h,
			absolutePositioned: true,
		} );
	}

	layerClipPath( x, y, w, h, angle = 0, settings = {} ) {
		if ( ! w || ! h ) {
			return null;
		}

		const shape = String( settings?.mask_shape || 'circle' ).toLowerCase();
		const left = x + w / 2;
		const top = y + h / 2;

		if ( shape === 'circle' ) {
			return new Circle( {
				left,
				top,
				originX: 'center',
				originY: 'center',
				radius: Math.min( w, h ) / 2,
				absolutePositioned: true,
			} );
		}

		return new Rect( {
			left,
			top,
			originX: 'center',
			originY: 'center',
			angle,
			width: w,
			height: h,
			absolutePositioned: true,
		} );
	}

	applyContentClip( obj, clipPath ) {
		if ( clipPath ) {
			obj.set( { clipPath } );
		}
	}

	centerObjectBounds( obj, targetX, targetY, angle = 0 ) {
		if ( ! obj ) {
			return;
		}

		obj.setCoords?.();
		const points =
			typeof obj.getCoords === 'function' ? obj.getCoords() : [];
		if ( ! points.length ) {
			return;
		}

		const rad = ( ( Number( angle ) || 0 ) * Math.PI ) / 180;
		const cos = Math.cos( rad );
		const sin = Math.sin( rad );
		const local = points.map( ( point ) => {
			const dx = point.x - targetX;
			const dy = point.y - targetY;

			return {
				x: targetX + dx * cos + dy * sin,
				y: targetY - dx * sin + dy * cos,
			};
		} );
		const minX = Math.min( ...local.map( ( point ) => point.x ) );
		const maxX = Math.max( ...local.map( ( point ) => point.x ) );
		const minY = Math.min( ...local.map( ( point ) => point.y ) );
		const maxY = Math.max( ...local.map( ( point ) => point.y ) );
		const moveX = targetX - ( minX + maxX ) / 2;
		const moveY = targetY - ( minY + maxY ) / 2;

		if ( Math.abs( moveX ) < 0.01 && Math.abs( moveY ) < 0.01 ) {
			return;
		}

		obj.set( {
			left: Number( obj.left || 0 ) + moveX * cos - moveY * sin,
			top: Number( obj.top || 0 ) + moveX * sin + moveY * cos,
		} );
		obj.setCoords?.();
	}

	keepObjectInsidePrintArea( obj, bounds, scale ) {
		if ( ! obj || ! bounds || ! bounds.w || ! bounds.h ) {
			return;
		}

		obj.setCoords?.();
		const points =
			typeof obj.getCoords === 'function' ? obj.getCoords() : [];
		if ( ! points.length ) {
			return;
		}

		const cx = ( Number( bounds.x ) + Number( bounds.w ) / 2 ) * scale;
		const cy = ( Number( bounds.y ) + Number( bounds.h ) / 2 ) * scale;
		const halfW = ( Number( bounds.w ) * scale ) / 2;
		const halfH = ( Number( bounds.h ) * scale ) / 2;
		const angle = ( ( Number( bounds.rotation ) || 0 ) * Math.PI ) / 180;
		const cos = Math.cos( angle );
		const sin = Math.sin( angle );

		const local = points.map( ( point ) => {
			const dx = point.x - cx;
			const dy = point.y - cy;

			return {
				x: cx + dx * cos + dy * sin,
				y: cy - dx * sin + dy * cos,
			};
		} );
		const minX = Math.min( ...local.map( ( point ) => point.x ) );
		const maxX = Math.max( ...local.map( ( point ) => point.x ) );
		const minY = Math.min( ...local.map( ( point ) => point.y ) );
		const maxY = Math.max( ...local.map( ( point ) => point.y ) );
		const left = cx - halfW;
		const right = cx + halfW;
		const top = cy - halfH;
		const bottom = cy + halfH;
		let moveX = 0;
		let moveY = 0;

		if ( minX < left ) {
			moveX = left - minX;
		} else if ( maxX > right ) {
			moveX = right - maxX;
		}

		if ( minY < top ) {
			moveY = top - minY;
		} else if ( maxY > bottom ) {
			moveY = bottom - maxY;
		}

		if ( ! moveX && ! moveY ) {
			return;
		}

		obj.set( {
			left: Number( obj.left || 0 ) + moveX * cos - moveY * sin,
			top: Number( obj.top || 0 ) + moveX * sin + moveY * cos,
		} );
		obj.setCoords?.();
	}

	async recolourSvgClipartUrl( url, color, effect = '' ) {
		const key = `${ url }|${ color }|${ effect }`;
		if ( this.clipartSvgCache[ key ] ) {
			return this.clipartSvgCache[ key ];
		}

		try {
			const response = await fetch( url, {
				credentials: 'same-origin',
				cache: 'force-cache',
			} );
			if ( ! response.ok ) {
				throw new Error(
					`Could not load clipart SVG (${ response.status }).`
				);
			}

			const raw = await response.text();
			const doc = new window.DOMParser().parseFromString(
				raw,
				'image/svg+xml'
			);
			const svg = doc.documentElement;
			if ( ! svg || svg.localName.toLowerCase() !== 'svg' ) {
				throw new Error( 'Clipart is not an SVG.' );
			}

			const paint =
				effect === 'embroidery' ? 'url(#oc-embroidery-stitch)' : color;
			svg.setAttribute( 'color', color );
			this.forceSvgPreviewColour( svg, paint );
			if ( effect === 'embroidery' ) {
				this.addEmbroiderySvgPattern( svg, color );
			}
			this.ensureSvgIntrinsicSize( svg );
			const output = new window.XMLSerializer().serializeToString( svg );
			this.clipartSvgCache[
				key
			] = `data:image/svg+xml;charset=utf-8,${ encodeURIComponent(
				output
			) }`;
			return this.clipartSvgCache[ key ];
		} catch ( e ) {
			console.warn( '[OC] SVG clipart recolour failed:', e, 'URL:', url );
			return url;
		}
	}

	async cropSvgClipartUrl( url ) {
		if ( ! this.isSvgClipartUrl( url ) ) {
			return url;
		}

		const key = `${ url }|crop`;
		if ( this.clipartSvgCache[ key ] ) {
			return this.clipartSvgCache[ key ];
		}

		try {
			const response = await fetch( url, {
				credentials: 'same-origin',
				cache: 'force-cache',
			} );
			if ( ! response.ok ) {
				throw new Error(
					`Could not load clipart SVG (${ response.status }).`
				);
			}

			const raw = await response.text();
			const doc = new window.DOMParser().parseFromString(
				raw,
				'image/svg+xml'
			);
			const svg = doc.documentElement;
			if ( ! svg || svg.localName.toLowerCase() !== 'svg' ) {
				throw new Error( 'Clipart is not an SVG.' );
			}

			if ( ! this.hasComplexSvgPaintReferences( svg ) ) {
				this.cropSvgToVisibleBounds( svg );
			}
			const output = new window.XMLSerializer().serializeToString( svg );
			this.clipartSvgCache[
				key
			] = `data:image/svg+xml;charset=utf-8,${ encodeURIComponent(
				output
			) }`;
			return this.clipartSvgCache[ key ];
		} catch {
			return url;
		}
	}

	async normaliseSvgClipartUrl( url ) {
		if ( ! this.isSvgClipartUrl( url ) ) {
			return url;
		}

		const key = `${ url }|normalise`;
		if ( this.clipartSvgCache[ key ] ) {
			return this.clipartSvgCache[ key ];
		}

		try {
			const response = await fetch( url, {
				credentials: 'same-origin',
				cache: 'force-cache',
			} );
			if ( ! response.ok ) {
				throw new Error(
					`Could not load clipart SVG (${ response.status }).`
				);
			}

			const raw = await response.text();
			const doc = new window.DOMParser().parseFromString(
				raw,
				'image/svg+xml'
			);
			const svg = doc.documentElement;
			if ( ! svg || svg.localName.toLowerCase() !== 'svg' ) {
				throw new Error( 'Clipart is not an SVG.' );
			}

			if ( ! this.ensureSvgIntrinsicSize( svg ) ) {
				this.clipartSvgCache[ key ] = url;
				return url;
			}

			const output = new window.XMLSerializer().serializeToString( svg );
			this.clipartSvgCache[
				key
			] = `data:image/svg+xml;charset=utf-8,${ encodeURIComponent(
				output
			) }`;
			return this.clipartSvgCache[ key ];
		} catch {
			this.clipartSvgCache[ key ] = url;
			return url;
		}
	}

	isSvgClipartUrl( url ) {
		const value = String( url || '' ).trim();
		return (
			/^data:image\/svg\+xml/i.test( value ) ||
			/\.svg(?:[?#]|$)/i.test( value )
		);
	}

	hasComplexSvgPaintReferences( svg ) {
		return !! svg.querySelector(
			'clipPath, mask, filter, [clip-path], [mask], [filter]'
		);
	}

	addEmbroiderySvgPattern( svg, color ) {
		const rgb = this.hexToRgb( color ) || { r: 0, g: 0, b: 0 };
		const hi = `rgb(${ Math.min( 255, rgb.r + 92 ) },${ Math.min(
			255,
			rgb.g + 92
		) },${ Math.min( 255, rgb.b + 92 ) })`;
		const lo = `rgb(${ Math.max( 0, rgb.r - 78 ) },${ Math.max(
			0,
			rgb.g - 78
		) },${ Math.max( 0, rgb.b - 78 ) })`;
		const ns = 'http://www.w3.org/2000/svg';
		const defs =
			svg.querySelector( 'defs' ) ||
			svg.insertBefore(
				document.createElementNS( ns, 'defs' ),
				svg.firstChild
			);
		const pattern = document.createElementNS( ns, 'pattern' );

		pattern.setAttribute( 'id', 'oc-embroidery-stitch' );
		pattern.setAttribute( 'patternUnits', 'userSpaceOnUse' );
		pattern.setAttribute( 'width', '12' );
		pattern.setAttribute( 'height', '12' );

		const bg = document.createElementNS( ns, 'rect' );
		bg.setAttribute( 'width', '12' );
		bg.setAttribute( 'height', '12' );
		bg.setAttribute( 'fill', color );
		pattern.appendChild( bg );

		[
			[ lo, '0.34', '-3' ],
			[ hi, '0.46', '3' ],
			[ lo, '0.2', '9' ],
		].forEach( ( [ stroke, opacity, x ] ) => {
			const line = document.createElementNS( ns, 'line' );
			line.setAttribute( 'x1', x );
			line.setAttribute( 'y1', '13' );
			line.setAttribute( 'x2', String( Number( x ) + 13 ) );
			line.setAttribute( 'y2', '-1' );
			line.setAttribute( 'stroke', stroke );
			line.setAttribute( 'stroke-width', '2' );
			line.setAttribute( 'stroke-linecap', 'round' );
			line.setAttribute( 'opacity', opacity );
			pattern.appendChild( line );
		} );

		defs.appendChild( pattern );
		return 'url(#oc-embroidery-stitch)';
	}

	forceSvgPreviewColour( element, color ) {
		const tagName = element.localName.toLowerCase();
		if ( tagName === 'style' ) {
			element.textContent = this.recolourSvgCss(
				element.textContent || '',
				color
			);
			return;
		}

		if ( tagName !== 'svg' ) {
			this.recolourSvgAttribute( element, 'fill', color );
			this.recolourSvgAttribute( element, 'stroke', color );

			if ( element.hasAttribute( 'style' ) ) {
				element.setAttribute(
					'style',
					this.recolourSvgStyle(
						element.getAttribute( 'style' ),
						color
					)
				);
			}

			const shapeTags = [
				'path',
				'rect',
				'circle',
				'ellipse',
				'polygon',
				'polyline',
				'text',
			];
			if (
				shapeTags.includes( tagName ) &&
				! element.hasAttribute( 'fill' ) &&
				! element.hasAttribute( 'stroke' ) &&
				! element.hasAttribute( 'style' )
			) {
				element.setAttribute( 'fill', color );
			}
		}

		Array.from( element.children ).forEach( ( child ) =>
			this.forceSvgPreviewColour( child, color )
		);
	}

	cropSvgToVisibleBounds( svg ) {
		if (
			typeof document === 'undefined' ||
			! document.body ||
			typeof svg.getAttribute !== 'function'
		) {
			return;
		}

		const wrapper = document.createElement( 'div' );
		wrapper.style.cssText =
			'position:absolute;left:-99999px;top:-99999px;opacity:0;pointer-events:none;';
		const clone = document.importNode( svg, true );
		clone.setAttribute( 'width', '1000' );
		clone.setAttribute( 'height', '1000' );

		try {
			wrapper.appendChild( clone );
			document.body.appendChild( wrapper );
			const bounds = this.svgVisibleBounds( clone );
			if ( bounds.width > 0 && bounds.height > 0 ) {
				svg.setAttribute(
					'viewBox',
					`${ bounds.x } ${ bounds.y } ${ bounds.width } ${ bounds.height }`
				);
				svg.setAttribute( 'width', String( bounds.width ) );
				svg.setAttribute( 'height', String( bounds.height ) );
			}
		} catch ( e ) {
			console.warn( '[OC] SVG clipart bounds crop failed:', e );
		} finally {
			wrapper.remove();
		}
	}

	ensureSvgIntrinsicSize( svg ) {
		const viewBox = this.parseSvgViewBox( svg );
		if ( ! viewBox ) {
			return false;
		}

		let changed = false;
		if ( ! this.hasUsableSvgLength( svg.getAttribute( 'width' ) ) ) {
			svg.setAttribute( 'width', String( viewBox.width ) );
			changed = true;
		}
		if ( ! this.hasUsableSvgLength( svg.getAttribute( 'height' ) ) ) {
			svg.setAttribute( 'height', String( viewBox.height ) );
			changed = true;
		}

		return changed;
	}

	parseSvgViewBox( svg ) {
		const raw = String( svg.getAttribute( 'viewBox' ) || '' ).trim();
		const values = raw
			.split( /[\s,]+/ )
			.map( Number )
			.filter( ( value ) => Number.isFinite( value ) );
		if ( values.length !== 4 || values[ 2 ] <= 0 || values[ 3 ] <= 0 ) {
			return null;
		}

		return {
			x: values[ 0 ],
			y: values[ 1 ],
			width: values[ 2 ],
			height: values[ 3 ],
		};
	}

	hasUsableSvgLength( value ) {
		const raw = String( value || '' ).trim();
		if ( ! raw || raw.endsWith( '%' ) ) {
			return false;
		}

		return parseFloat( raw ) > 0;
	}

	svgVisibleBounds( svg ) {
		const boxes = Array.from( svg.querySelectorAll( '*' ) )
			.filter( ( element ) => this.isVisibleSvgGraphicElement( element ) )
			.map( ( element ) => this.svgElementRootBounds( element ) )
			.filter( Boolean );

		if ( ! boxes.length ) {
			return svg.getBBox();
		}

		const minX = Math.min( ...boxes.map( ( box ) => box.x ) );
		const minY = Math.min( ...boxes.map( ( box ) => box.y ) );
		const maxX = Math.max( ...boxes.map( ( box ) => box.x + box.width ) );
		const maxY = Math.max( ...boxes.map( ( box ) => box.y + box.height ) );

		return {
			x: minX,
			y: minY,
			width: maxX - minX,
			height: maxY - minY,
		};
	}

	isVisibleSvgGraphicElement( element ) {
		const tagName = element.localName.toLowerCase();
		if ( this.isInSvgDefinitionTree( element ) ) {
			return false;
		}

		const graphicTags = [
			'path',
			'rect',
			'circle',
			'ellipse',
			'polygon',
			'polyline',
			'line',
			'text',
			'image',
			'use',
		];
		if ( ! graphicTags.includes( tagName ) ) {
			return false;
		}

		const style = window.getComputedStyle( element );
		if (
			style.display === 'none' ||
			style.visibility === 'hidden' ||
			Number( style.opacity ) === 0
		) {
			return false;
		}

		if ( [ 'image', 'use' ].includes( tagName ) ) {
			return true;
		}

		return style.fill !== 'none' || style.stroke !== 'none';
	}

	isInSvgDefinitionTree( element ) {
		const nonRenderedContainers = [
			'defs',
			'clippath',
			'mask',
			'pattern',
			'symbol',
			'marker',
			'filter',
			'lineargradient',
			'radialgradient',
		];

		let parent = element.parentElement;
		while ( parent ) {
			if (
				nonRenderedContainers.includes( parent.localName.toLowerCase() )
			) {
				return true;
			}
			parent = parent.parentElement;
		}

		return false;
	}

	svgElementRootBounds( element ) {
		if ( typeof element.getBBox !== 'function' ) {
			return null;
		}

		try {
			const box = element.getBBox();
			if ( ! box.width || ! box.height ) {
				return null;
			}

			const root = element.ownerSVGElement;
			const rootMatrix =
				root && typeof root.getScreenCTM === 'function'
					? root.getScreenCTM()
					: null;
			const elementMatrix =
				typeof element.getScreenCTM === 'function'
					? element.getScreenCTM()
					: null;
			const matrix =
				rootMatrix && elementMatrix
					? rootMatrix.inverse().multiply( elementMatrix )
					: null;
			if ( ! matrix ) {
				return box;
			}

			const points = [
				[ box.x, box.y ],
				[ box.x + box.width, box.y ],
				[ box.x + box.width, box.y + box.height ],
				[ box.x, box.y + box.height ],
			].map( ( [ x, y ] ) => ( {
				x: matrix.a * x + matrix.c * y + matrix.e,
				y: matrix.b * x + matrix.d * y + matrix.f,
			} ) );

			const minX = Math.min( ...points.map( ( point ) => point.x ) );
			const minY = Math.min( ...points.map( ( point ) => point.y ) );
			const maxX = Math.max( ...points.map( ( point ) => point.x ) );
			const maxY = Math.max( ...points.map( ( point ) => point.y ) );

			return {
				x: minX,
				y: minY,
				width: maxX - minX,
				height: maxY - minY,
			};
		} catch {
			return null;
		}
	}

	removeInvisibleSvgShapes( element ) {
		Array.from( element.children ).forEach( ( child ) => {
			this.removeInvisibleSvgShapes( child );

			const tagName = child.localName.toLowerCase();
			const shapeTags = [
				'path',
				'rect',
				'circle',
				'ellipse',
				'polygon',
				'polyline',
				'line',
				'text',
			];
			if ( ! shapeTags.includes( tagName ) ) {
				return;
			}

			const fill = ( child.getAttribute( 'fill' ) || '' )
				.trim()
				.toLowerCase();
			const stroke = ( child.getAttribute( 'stroke' ) || '' )
				.trim()
				.toLowerCase();
			const style = ( child.getAttribute( 'style' ) || '' )
				.replace( /\s+/g, '' )
				.toLowerCase();
			const hasVisibleFill =
				( fill && fill !== 'none' ) ||
				/fill:(?!none(?:;|$))/.test( style );
			const hasVisibleStroke =
				( stroke && stroke !== 'none' ) ||
				/stroke:(?!none(?:;|$))/.test( style );

			if ( ! hasVisibleFill && ! hasVisibleStroke ) {
				child.remove();
			}
		} );
	}

	recolourSvgAttribute( element, attribute, color ) {
		if ( ! element.hasAttribute( attribute ) ) {
			return;
		}

		const value = element.getAttribute( attribute ).trim();
		if ( value.toLowerCase() === 'none' ) {
			return;
		}

		element.setAttribute(
			attribute,
			this.isSvgWhite( value ) ? 'none' : color
		);
	}

	recolourSvgStyle( style, color ) {
		return style.replace(
			/\b(fill|stroke)\s*:\s*([^;]+)/gi,
			( match, property, value ) => {
				const trimmed = String( value || '' ).trim();
				if ( trimmed.toLowerCase() === 'none' ) {
					return match;
				}

				return `${ property }:${
					this.isSvgWhite( trimmed ) ? 'none' : color
				}`;
			}
		);
	}

	recolourSvgCss( css, color ) {
		return css.replace(
			/\b(fill|stroke)\s*:\s*([^;}]+)/gi,
			( match, property, value ) => {
				const trimmed = String( value || '' ).trim();
				if ( trimmed.toLowerCase() === 'none' ) {
					return match;
				}

				return `${ property }:${
					this.isSvgWhite( trimmed ) ? 'none' : color
				}`;
			}
		);
	}

	isSvgWhite( value ) {
		const normalised = String( value || '' )
			.trim()
			.toLowerCase()
			.replace( /\s+/g, '' );
		return [
			'#fff',
			'#ffffff',
			'white',
			'rgb(255,255,255)',
			'rgba(255,255,255,1)',
		].includes( normalised );
	}

	async renderFabricImg(
		canvas,
		url,
		x,
		y,
		w,
		h,
		isEngraving = false,
		crossOrigin = 'anonymous',
		makeWhiteTransparent = false,
		angle = 0,
		engravingPalette = null,
		clipPath = null,
		fit = 'contain',
		tintColor = '',
		effects = {}
	) {
		try {
			const imgLoadOpts = crossOrigin ? { crossOrigin } : {};
			const img = await FabricImage.fromURL( url, imgLoadOpts );
			if ( ! img || ! img.width ) {
				console.warn(
					'[OC] Image failed to load or has zero dimensions:',
					url
				);
				return false;
			}
			const s =
				fit === 'cover'
					? Math.max( w / img.width, h / img.height )
					: Math.min( w / img.width, h / img.height );
			img.set( {
				left: x + w / 2,
				top: y + h / 2,
				originX: 'center',
				originY: 'center',
				scaleX: s,
				scaleY: s,
				angle,
				selectable: false,
				evented: false,
			} );

			const filters = [];
			if (
				makeWhiteTransparent ||
				( isEngraving && ! effects.preserveRecolouredPixels )
			) {
				filters.push(
					new FabricFilters.RemoveColor( {
						color: '#FFFFFF',
						distance: isEngraving ? 0.18 : 0.1,
					} )
				);
			}
			if ( tintColor && FabricFilters.BlendColor ) {
				filters.push(
					new FabricFilters.BlendColor( {
						color: tintColor,
						mode: 'tint',
						alpha: 1,
					} )
				);
			}
			if ( isEngraving && ! effects.preserveRecolouredPixels ) {
				const palette = engravingPalette || this.engravingPalette();
				filters.push(
					new FabricFilters.Grayscale(),
					new FabricFilters.Brightness( {
						brightness: palette.brightness,
					} ),
					new FabricFilters.Contrast( { contrast: palette.contrast } )
				);
				if ( palette.imageTint && FabricFilters.BlendColor ) {
					filters.push(
						new FabricFilters.BlendColor( {
							color: palette.imageTint,
							mode: 'tint',
							alpha: palette.tintAlpha ?? 1,
						} )
					);
				}
			}
			if ( filters.length ) {
				img.filters = filters;
				img.applyFilters();
			}
			if ( isEngraving && effects.preserveRecolouredPixels ) {
				const palette = engravingPalette || this.engravingPalette();
				img.set( {
					opacity: palette.opacity,
					globalCompositeOperation:
						palette.composite || 'source-over',
				} );
			} else if ( isEngraving ) {
				const palette = engravingPalette || this.engravingPalette();
				img.set( {
					opacity: palette.opacity,
					globalCompositeOperation:
						palette.composite || 'source-over',
					shadow: new Shadow( {
						color: palette.highlight,
						offsetX: 0,
						offsetY: 1,
						blur: 1,
					} ),
				} );
			} else if ( effects.embroideryColor ) {
				img.set( {
					shadow: new Shadow( {
						color: 'rgba(0,0,0,0.24)',
						offsetX: 0.7,
						offsetY: 0.95,
						blur: 1.1,
					} ),
				} );
			}

			img._ocContent = true;
			img._ocSourceUrl = url;
			img._ocSnapshotColor = effects.embroideryColor || tintColor || '';
			this.applyContentClip( img, clipPath );
			canvas.add( img );
			return true;
		} catch ( e ) {
			console.warn( '[OC] renderFabricImg error:', e, 'URL:', url );
			return false;
		}
	}

	async loadFont( font ) {
		if ( ! font?.name || ! font?.url ) {
			return;
		}
		if ( this.fontCache[ font.name ] ) {
			return this.fontCache[ font.name ];
		}
		const ff = new FontFace( font.name, `url('${ font.url }')`, {
			weight: font.weight || 'normal',
			style: font.style || 'normal',
		} );
		this.fontCache[ font.name ] = ff
			.load()
			.then( ( f ) => document.fonts.add( f ) )
			.catch( ( err ) => {
				delete this.fontCache[ font.name ];
				console.warn( '[OC] Font load failed:', err );
				throw err;
			} );
		return this.fontCache[ font.name ];
	}

	// ── Input listeners ─────────────────────────────────────────────────────────

	closeFontComboboxes( resetSearch = false ) {
		document
			.querySelectorAll( '.oc-font-combobox.oc-open' )
			.forEach( ( combo ) => {
				combo.classList.remove( 'oc-open' );
				const input = combo.querySelector( '[data-oc-font-search]' );
				input?.setAttribute( 'aria-expanded', 'false' );

				if ( resetSearch ) {
					const select = document.querySelector(
						`[data-oc-layer-font="${ combo.dataset.ocFontCombobox }"]`
					);
					if ( select ) {
						this.updateFontCombobox( select );
					}
				}
			} );
	}

	updateFontCombobox( select ) {
		const lid = select?.dataset?.ocLayerFont;
		if ( ! lid ) {
			return;
		}
		const combo = document.querySelector(
			`.oc-font-combobox[data-oc-font-combobox="${ lid }"]`
		);
		const input = combo?.querySelector( '[data-oc-font-search]' );
		const options = combo?.querySelectorAll( '[data-oc-font-option]' );
		const selected = select.options[ select.selectedIndex ];
		if ( ! combo || ! input || ! selected ) {
			return;
		}

		input.value = selected.textContent.trim();
		input.style.fontFamily = selected.style.fontFamily || '';
		options?.forEach( ( option ) => {
			option.hidden = false;
			const isSelected = option.dataset.ocFontOption === select.value;
			option.setAttribute(
				'aria-selected',
				isSelected ? 'true' : 'false'
			);
		} );
		combo
			.querySelector( '[data-oc-font-empty]' )
			?.setAttribute( 'hidden', '' );
	}

	setupFontComboboxes() {
		document
			.querySelectorAll( '[data-oc-layer-font]' )
			.forEach( ( select ) => {
				const lid = select.dataset.ocLayerFont;
				const combo = document.querySelector(
					`.oc-font-combobox[data-oc-font-combobox="${ lid }"]`
				);
				if ( ! combo ) {
					return;
				}
				if ( combo.dataset.ocFontComboboxReady === '1' ) {
					this.updateFontCombobox( select );
					return;
				}

				const input = combo.querySelector( '[data-oc-font-search]' );
				const list = combo.querySelector( '[data-oc-font-list]' );
				const options = Array.from(
					combo.querySelectorAll( '[data-oc-font-option]' )
				);
				const empty = combo.querySelector( '[data-oc-font-empty]' );
				if ( ! input || ! list || ! options.length ) {
					return;
				}
				combo.dataset.ocFontComboboxReady = '1';
				let filterFrame = null;

				const setOpen = ( isOpen ) => {
					combo.classList.toggle( 'oc-open', isOpen );
					input.setAttribute(
						'aria-expanded',
						isOpen ? 'true' : 'false'
					);
				};

				const selectedFontLabel = () =>
					select.options[
						select.selectedIndex
					]?.textContent.trim() || '';
				const filterOptions = ( queryOverride = null ) => {
					const query = ( queryOverride ?? input.value )
						.trim()
						.toLowerCase();
					let visibleCount = 0;
					options.forEach( ( option ) => {
						const isVisible = option.textContent
							.trim()
							.toLowerCase()
							.includes( query );
						option.hidden = ! isVisible;
						if ( isVisible ) {
							visibleCount++;
						}
					} );
					if ( empty ) {
						empty.hidden = visibleCount > 0;
					}
				};
				const scheduleFilterOptions = () => {
					if ( filterFrame ) {
						window.cancelAnimationFrame( filterFrame );
					}
					filterFrame = window.requestAnimationFrame( () => {
						filterFrame = null;
						filterOptions();
					} );
				};
				const firstVisibleOption = () =>
					options.find( ( option ) => ! option.hidden );

				const selectFont = ( value ) => {
					select.value = value;
					select.dispatchEvent(
						new Event( 'change', { bubbles: true } )
					);
					setOpen( false );
				};

				this.updateFontCombobox( select );
				filterOptions();

				input.addEventListener( 'focus', () => {
					if ( input.value.trim() === selectedFontLabel() ) {
						filterOptions( '' );
					} else {
						scheduleFilterOptions();
					}
					setOpen( true );
				} );
				input.addEventListener( 'input', () => {
					scheduleFilterOptions();
					setOpen( true );
				} );
				input.addEventListener( 'search', () => {
					scheduleFilterOptions();
					setOpen( true );
				} );
				input.addEventListener( 'keydown', ( e ) => {
					if ( e.key === 'Escape' ) {
						setOpen( false );
						this.updateFontCombobox( select );
						return;
					}
					if ( e.key === 'ArrowDown' ) {
						e.preventDefault();
						filterOptions();
						setOpen( true );
						firstVisibleOption()?.focus();
						return;
					}
					if ( e.key === 'Enter' ) {
						e.preventDefault();
						filterOptions();
						const option = firstVisibleOption();
						if ( option ) {
							selectFont( option.dataset.ocFontOption );
						} else {
							setOpen( false );
							this.updateFontCombobox( select );
						}
					}
				} );
				input.addEventListener( 'blur', () => {
					window.setTimeout( () => {
						if (
							! combo.contains(
								combo.ownerDocument.activeElement
							)
						) {
							setOpen( false );
							this.updateFontCombobox( select );
						}
					}, 120 );
				} );

				options.forEach( ( option ) => {
					option.addEventListener( 'pointerdown', ( e ) => {
						e.preventDefault();
						selectFont( option.dataset.ocFontOption );
					} );
					option.addEventListener( 'click', () =>
						selectFont( option.dataset.ocFontOption )
					);
					option.addEventListener( 'keydown', ( e ) => {
						const visible = options.filter(
							( item ) => ! item.hidden
						);
						const index = visible.indexOf( option );
						if ( e.key === 'ArrowDown' ) {
							e.preventDefault();
							visible[ index + 1 ]?.focus();
						} else if ( e.key === 'ArrowUp' ) {
							e.preventDefault();
							( visible[ index - 1 ] || input ).focus();
						} else if ( e.key === 'Escape' ) {
							setOpen( false );
							input.focus();
						}
					} );
				} );
			} );

		if ( ! this.fontComboboxDocumentClickBound ) {
			this.fontComboboxDocumentClickBound = true;
			document.addEventListener( 'click', ( e ) => {
				if ( ! e.target.closest( '.oc-font-combobox' ) ) {
					this.closeFontComboboxes( true );
				}
			} );
		}
	}

	setupInputListeners() {
		this.setupFontComboboxes();

		// Area tabs
		const areaTabs = Array.from(
			document.querySelectorAll( '.oc-area-tab' )
		);
		areaTabs.forEach( ( btn ) => {
			btn.addEventListener( 'click', () =>
				this.switchArea( parseInt( btn.dataset.areaIndex, 10 ) )
			);
			btn.addEventListener(
				'touchend',
				( e ) => {
					e.preventDefault();
					this.switchArea( parseInt( btn.dataset.areaIndex, 10 ) );
				},
				{ passive: false }
			);
			btn.addEventListener( 'keydown', ( e ) => {
				if (
					! [ 'ArrowLeft', 'ArrowRight', 'Home', 'End' ].includes(
						e.key
					)
				) {
					return;
				}
				e.preventDefault();
				const currentIndex = areaTabs.indexOf( btn );
				let nextIndex = currentIndex;
				if ( e.key === 'ArrowLeft' ) {
					nextIndex = Math.max( 0, currentIndex - 1 );
				}
				if ( e.key === 'ArrowRight' ) {
					nextIndex = Math.min(
						areaTabs.length - 1,
						currentIndex + 1
					);
				}
				if ( e.key === 'Home' ) {
					nextIndex = 0;
				}
				if ( e.key === 'End' ) {
					nextIndex = areaTabs.length - 1;
				}
				areaTabs[ nextIndex ]?.focus();
				this.switchArea(
					parseInt(
						areaTabs[ nextIndex ]?.dataset.areaIndex || '0',
						10
					)
				);
			} );
		} );

		// Text / textarea
		document.querySelectorAll( '[data-oc-layer-text]' ).forEach( ( el ) => {
			const lid = parseInt( el.dataset.ocLayerText, 10 );
			const counter = el.parentElement?.querySelector(
				`.oc-char-counter[data-oc-char-counter="${ lid }"]`
			);
			const limit =
				parseInt( counter?.dataset.charLimit, 10 ) ||
				this.charLimitForLayer( lid );
			if ( limit > 0 ) {
				el.maxLength = limit;
			}
			const updateCounter = () => {
				if ( ! counter ) {
					return;
				}
				const current = this.textLength( el.value );
				if ( limit === 0 || current <= limit ) {
					counter.style.display = 'none';
					return;
				}
				counter.textContent = `${ current } / ${ limit }`;
				counter.style.display = '';
			};
			updateCounter();
			el.addEventListener( 'input', async () => {
				const cleaned = this.normaliseLayerTextValue( lid, el.value );
				if ( cleaned !== el.value ) {
					el.value = cleaned;
				}
				if ( ! this.inputs[ lid ] ) {
					this.inputs[ lid ] = {};
				}
				this.inputs[ lid ].value = cleaned;
				this.syncLinkedLayerInput( lid, [ 'value' ] );
				updateCounter();
				await this.updateTextSizeSliderCap( lid );
				this.requestPreviewFocus();
				this.scheduleRedraw( this.areaIndexForLayer( lid ) );
				this.updateHiddenField();
			} );
		} );

		// Spotify validation (invalid format / private playlist / unavailable).
		document
			.querySelectorAll( '[data-oc-layer-spotify]' )
			.forEach( ( el ) => {
				const lid = parseInt( el.dataset.ocLayerSpotify, 10 );
				if ( ! lid ) {
					return;
				}

				el.addEventListener( 'input', () => {
					if ( ! this.inputs[ lid ] ) {
						this.inputs[ lid ] = {};
					}
					this.inputs[ lid ].value = el.value;
					this.inputs[ lid ].spotifyStatus = '';
					this.inputs[ lid ].spotifyUri = '';
					this.syncLinkedLayerInput( lid, [
						'value',
						'spotifyStatus',
						'spotifyUri',
					] );
					this.setSpotifyError( lid, '', el );
					this.requestPreviewFocus();
					this.scheduleRedraw( this.areaIndexForLayer( lid ) );
					this.updateHiddenField();

					clearTimeout( this.spotifyValidateTimers[ lid ] );
					this.spotifyValidateTimers[ lid ] = setTimeout( () => {
						this.validateSpotifyLayer( lid, el.value, el );
					}, 450 );
				} );

				el.addEventListener( 'blur', () => {
					this.validateSpotifyLayer( lid, el.value, el );
				} );
			} );

		// Help tooltips: tap to toggle on touch devices, close on outside tap.
		const closeHelpTooltips = () => {
			document
				.querySelectorAll(
					'.oc-help-tooltip.oc-open, .oc-spotify-help.oc-open'
				)
				.forEach( ( help ) => {
					help.classList.remove( 'oc-open' );
					help.querySelector(
						'.oc-help-toggle, .oc-spotify-help-toggle'
					)?.setAttribute( 'aria-expanded', 'false' );
				} );
		};
		document
			.querySelectorAll(
				'.oc-help-toggle:not(.oc-spotify-modal-trigger), .oc-spotify-help-toggle'
			)
			.forEach( ( btn ) => {
				btn.addEventListener( 'click', ( e ) => {
					e.preventDefault();
					e.stopPropagation();
					const help = btn.closest(
						'.oc-help-tooltip, .oc-spotify-help'
					);
					if ( ! help ) {
						return;
					}
					const willOpen = ! help.classList.contains( 'oc-open' );
					closeHelpTooltips();
					if ( willOpen ) {
						help.classList.add( 'oc-open' );
						btn.setAttribute( 'aria-expanded', 'true' );
					}
				} );
			} );
		document.addEventListener( 'click', ( e ) => {
			if ( ! e.target.closest( '.oc-help-tooltip, .oc-spotify-help' ) ) {
				closeHelpTooltips();
			}
		} );

		this.setupSpotifyModal();

		// Font selects — also reflect the picked font in the closed select.
		const reflectFontOnSelect = ( el ) => {
			const opt = el.options[ el.selectedIndex ];
			const fam = opt?.style?.fontFamily || '';
			if ( fam ) {
				el.style.fontFamily = fam;
			}
		};
		document.querySelectorAll( '[data-oc-layer-font]' ).forEach( ( el ) => {
			reflectFontOnSelect( el );
			this.updateFontCombobox( el );
			const lid = parseInt( el.dataset.ocLayerFont, 10 );
			const selectedFontId = parseInt( el.value, 10 ) || 0;
			if ( selectedFontId ) {
				if ( ! this.inputs[ lid ] ) {
					this.inputs[ lid ] = {};
				}
				this.inputs[ lid ].fontId = selectedFontId;
			}
			el.addEventListener( 'change', async () => {
				if ( ! this.inputs[ lid ] ) {
					this.inputs[ lid ] = {};
				}
				this.inputs[ lid ].fontId = parseInt( el.value, 10 );
				const font = this.fonts.find(
					( f ) => f.id === this.inputs[ lid ].fontId
				);
				if ( font ) {
					try {
						await this.loadFont( font );
					} catch ( err ) {
						console.warn( '[OC] Font load failed:', err );
					}
				}
				reflectFontOnSelect( el );
				this.updateFontCombobox( el );
				const preview = document.querySelector(
					`.oc-font-preview[data-oc-font-preview="${ lid }"]`
				);
				if ( preview && font ) {
					preview.style.fontFamily = font.name;
				}
				await this.updateTextSizeSliderCap( lid );
				this.requestPreviewFocus();
				this.scheduleRedraw( this.areaIndexForLayer( lid ) );
				this.updateHiddenField();
			} );
		} );

		// Font size
		document
			.querySelectorAll( '[data-oc-layer-font-size]' )
			.forEach( ( el ) => {
				const lid = parseInt( el.dataset.ocLayerFontSize, 10 );
				const valueEl = document.querySelector(
					`.oc-range-value[data-oc-range-value="${ lid }"]`
				);
				if ( ! el.dataset.ocOriginalMax ) {
					el.dataset.ocOriginalMax = el.max || '200';
				}
				const updateValue = () => {
					if ( valueEl ) {
						valueEl.textContent = el.value;
					}
				};
				updateValue();
				this.updateTextSizeSliderCap( lid );
				el.addEventListener( 'input', () => {
					if ( ! this.inputs[ lid ] ) {
						this.inputs[ lid ] = {};
					}
					this.inputs[ lid ].fontSize = Math.max(
						1,
						parseInt( el.value, 10 ) || 1
					);
					updateValue();
					this.requestPreviewFocus();
					this.scheduleRedraw( this.areaIndexForLayer( lid ) );
					this.updateHiddenField();
				} );
			} );

		// Colour swatches
		document
			.querySelectorAll( '[data-oc-layer-swatch]' )
			.forEach( ( btn ) => {
				btn.addEventListener( 'click', () => {
					const lid = parseInt( btn.dataset.ocLayerSwatch, 10 );
					if ( ! this.inputs[ lid ] ) {
						this.inputs[ lid ] = {};
					}
					this.inputs[ lid ].colorHex = btn.dataset.hex;
					if ( this.getLayerById( lid )?.type === 'lineart' ) {
						this.syncLinkedLayerInput( lid, [ 'colorHex' ] );
					}
					btn.closest( '.oc-colour-swatches' )
						?.querySelectorAll( '.oc-colour-swatch' )
						.forEach( ( s ) => {
							const isSelected = s === btn;
							s.classList.toggle( 'oc-selected', isSelected );
							s.setAttribute(
								'aria-pressed',
								isSelected ? 'true' : 'false'
							);
						} );
					this.requestPreviewFocus();
					this.scheduleRedraw( this.areaIndexForLayer( lid ) );
					this.updateHiddenField();
				} );
			} );

		// Free colour picker
		document
			.querySelectorAll( '[data-oc-layer-color]' )
			.forEach( ( el ) => {
				const lid = parseInt( el.dataset.ocLayerColor, 10 );
				el.addEventListener( 'input', () => {
					if ( ! this.inputs[ lid ] ) {
						this.inputs[ lid ] = {};
					}
					this.inputs[ lid ].colorHex = el.value;
					if ( this.getLayerById( lid )?.type === 'lineart' ) {
						this.syncLinkedLayerInput( lid, [ 'colorHex' ] );
					}
					this.requestPreviewFocus();
					this.scheduleRedraw( this.areaIndexForLayer( lid ) );
					this.updateHiddenField();
				} );
			} );

		// Clipart items
		document
			.querySelectorAll( '[data-oc-layer-clipart]' )
			.forEach( ( btn ) => {
				btn.addEventListener( 'click', () => {
					const lid = parseInt( btn.dataset.ocLayerClipart, 10 );
					if ( ! this.inputs[ lid ] ) {
						this.inputs[ lid ] = {};
					}
					this.inputs[ lid ].clipartId = parseInt(
						btn.dataset.ocClipart,
						10
					);
					this.inputs[ lid ].clipartUrl = btn.dataset.ocClipartUrl;
					this.inputs[ lid ].clipartRecolourable =
						btn.dataset.ocClipartRecolourable === '1';
					this.syncLinkedLayerInput( lid, [
						'clipartId',
						'clipartUrl',
						'clipartRecolourable',
					] );
					btn.closest( '.oc-clipart-grid' )
						?.querySelectorAll( '.oc-clipart-item' )
						.forEach( ( i ) => {
							const isSelected = i === btn;
							i.classList.toggle( 'oc-selected', isSelected );
							i.setAttribute(
								'aria-pressed',
								isSelected ? 'true' : 'false'
							);
						} );
					this.requestPreviewFocus();
					this.scheduleRedraw( this.areaIndexForLayer( lid ) );
					this.updateHiddenField();
				} );
			} );

		window.addEventListener( 'resize', () => {
			this.refreshDesignVariantCarousel();
			document
				.querySelectorAll( '[data-oc-clipart-carousel]' )
				.forEach( ( carousel ) => {
					this.refreshClipartCarousel(
						parseInt( carousel.dataset.ocClipartCarousel, 10 )
					);
				} );
		} );

		// Clipart search (debounced 200ms)
		document
			.querySelectorAll( '[data-oc-clipart-search]' )
			.forEach( ( input ) => {
				const lid = parseInt( input.dataset.ocClipartSearch, 10 );
				this.clipartSearchTerms[ lid ] = '';
				input.addEventListener( 'input', () => {
					this.clipartSearchTerms[ lid ] = input.value;
					clearTimeout( this.clipartSearchTimers[ lid ] );
					this.clipartSearchTimers[ lid ] = setTimeout( () => {
						this.filterClipart( lid );
					}, 200 );
				} );
			} );

		// Clipart category filter
		document
			.querySelectorAll( '[data-oc-clipart-category]' )
			.forEach( ( select ) => {
				const lid = parseInt( select.dataset.ocClipartCategory, 10 );
				this.clipartCategoryFilters[ lid ] = '';
				select.addEventListener( 'change', () => {
					this.clipartCategoryFilters[ lid ] = select.value;
					this.filterClipart( lid );
				} );
			} );

		// Dismiss resolution warning
		document
			.querySelectorAll( '.oc-resolution-warning' )
			.forEach( ( warnEl ) => {
				warnEl.addEventListener( 'click', ( e ) => {
					if (
						e.target === warnEl &&
						warnEl.classList.contains( 'oc-res-warning' )
					) {
						warnEl.style.display = 'none';
					}
				} );
			} );
	}

	getLayerById( layerId ) {
		return this.layersById[ layerId ] || null;
	}

	charLimitForLayer( layerId ) {
		return Math.max(
			0,
			parseInt(
				this.getLayerById( layerId )?.settings?.char_limit,
				10
			) || 0
		);
	}

	textLength( value ) {
		return Array.from( String( value || '' ) ).length;
	}

	truncateText( value, limit ) {
		const text = String( value || '' );
		return limit > 0 && this.textLength( text ) > limit
			? Array.from( text ).slice( 0, limit ).join( '' )
			: text;
	}

	printMethodForLayer( layerId ) {
		const area = this.areas[ this.areaIndexForLayer( layerId ) ];
		return String( area?.printMethod || '' );
	}

	isThreadOrEngravingLayer( layerId ) {
		return [ 'engraving', 'embroidery' ].includes(
			this.printMethodForLayer( layerId )
		);
	}

	stripUnsupportedPrintEmoji( value ) {
		return String( value || '' )
			.replace(
				/[\u{1F000}-\u{1FAFF}\u{2600}-\u{27BF}][\u{FE0E}\u{FE0F}]?/gu,
				''
			)
			.replace(
				/[\u{1F3FB}-\u{1F3FF}\u{1F9B0}-\u{1F9B3}\u{200D}\u{FE0E}\u{FE0F}]/gu,
				''
			);
	}

	normaliseLayerTextValue( layerId, value ) {
		let text = String( value || '' );
		if ( this.isThreadOrEngravingLayer( layerId ) ) {
			text = this.stripUnsupportedPrintEmoji( text );
		}

		const limit = this.charLimitForLayer( layerId );
		return limit > 0 ? this.truncateText( text, limit ) : text;
	}

	clampLayerInputValue( layerId ) {
		if ( this.inputs[ layerId ]?.value !== undefined ) {
			this.inputs[ layerId ].value = this.normaliseLayerTextValue(
				layerId,
				this.inputs[ layerId ].value
			);
		}
	}

	linkedLayerIds( sourceLayerId ) {
		const source = this.getLayerById( sourceLayerId );
		const group = String( source?.settings?.link_group || '' ).trim();
		if ( ! source || ! group ) {
			return [];
		}

		const ids = [];
		this.areas.forEach( ( area ) => {
			( area.layers || [] ).forEach( ( layer ) => {
				if (
					layer.id === sourceLayerId ||
					layer.type !== source.type
				) {
					return;
				}
				if (
					String( layer.settings?.link_group || '' ).trim() === group
				) {
					ids.push( layer.id );
				}
			} );
		} );
		return ids;
	}

	syncLinkedLayerInput( sourceLayerId, keys ) {
		const sourceInput = this.inputs[ sourceLayerId ];
		if ( ! sourceInput ) {
			return;
		}

		this.linkedLayerIds( sourceLayerId ).forEach( ( layerId ) => {
			const targetLayer = this.getLayerById( layerId );
			if (
				targetLayer?.type === 'image' &&
				targetLayer.settings?.allow_image_change === false
			) {
				return;
			}
			if (
				targetLayer?.type === 'clipart' &&
				targetLayer.settings?.allow_clipart_change === false
			) {
				return;
			}

			if ( ! this.inputs[ layerId ] ) {
				this.inputs[ layerId ] = {};
			}
			keys.forEach( ( key ) => {
				if ( sourceInput[ key ] === undefined ) {
					delete this.inputs[ layerId ][ key ];
				} else {
					this.inputs[ layerId ][ key ] = sourceInput[ key ];
				}
			} );
			this.clampLayerInputValue( layerId );
			this.updateLinkedLayerControls( layerId, keys );
		} );
	}

	updateLinkedLayerControls( layerId, keys ) {
		const input = this.inputs[ layerId ] || {};
		if ( keys.includes( 'value' ) ) {
			document
				.querySelectorAll(
					`[data-oc-layer-text="${ layerId }"], [data-oc-layer-spotify="${ layerId }"]`
				)
				.forEach( ( el ) => {
					el.value = input.value || '';
				} );
			this.updateTextSizeSliderCap( layerId );
			const counter = document.querySelector(
				`.oc-char-counter[data-oc-char-counter="${ layerId }"]`
			);
			if ( counter ) {
				const limit =
					parseInt( counter.dataset.charLimit, 10 ) ||
					this.charLimitForLayer( layerId );
				const current = this.textLength( input.value || '' );
				counter.textContent = `${ current } / ${ limit }`;
				counter.style.display =
					limit > 0 && current > limit ? '' : 'none';
			}
		}
		if ( keys.includes( 'colorHex' ) ) {
			document
				.querySelectorAll( `[data-oc-layer-swatch="${ layerId }"]` )
				.forEach( ( swatch ) => {
					const isSelected = swatch.dataset.hex === input.colorHex;
					swatch.classList.toggle( 'oc-selected', isSelected );
					swatch.setAttribute(
						'aria-pressed',
						isSelected ? 'true' : 'false'
					);
				} );
			const colorEl = document.querySelector(
				`[data-oc-layer-color="${ layerId }"]`
			);
			if ( colorEl && input.colorHex ) {
				colorEl.value = input.colorHex;
			}
		}
		if ( keys.includes( 'clipartId' ) ) {
			document
				.querySelectorAll( `[data-oc-layer-clipart="${ layerId }"]` )
				.forEach( ( item ) => {
					const isSelected =
						Number( item.dataset.ocClipart ) ===
						Number( input.clipartId );
					item.classList.toggle( 'oc-selected', isSelected );
					item.setAttribute(
						'aria-pressed',
						isSelected ? 'true' : 'false'
					);
				} );
		}
		if (
			keys.includes( 'attachmentId' ) ||
			keys.includes( 'attachmentUrl' )
		) {
			document
				.querySelectorAll( `[data-oc-upload-zone="${ layerId }"]` )
				.forEach( ( zone ) => {
					this.setUploadZoneState(
						zone,
						input.attachmentUrl ? 'uploaded' : ''
					);
				} );
		}
	}

	// ── Form submit — upload preview then proceed ──────────────────────────────

	updateInputsFromDOM() {
		this.syncInputsFromDOM();
		this.applyInputsToDOM();
	}

	applyInputsToDOM() {
		for ( const layerIdStr in this.inputs ) {
			const layerId = parseInt( layerIdStr, 10 );
			const inp = this.inputs[ layerId ];
			if ( ! inp ) {
				continue;
			}

			const textEl = document.querySelector(
				`[data-oc-layer-text="${ layerId }"]`
			);
			if ( textEl && inp.value !== undefined ) {
				this.clampLayerInputValue( layerId );
				textEl.value = inp.value;
			}

			const fontEl = document.querySelector(
				`[data-oc-layer-font="${ layerId }"]`
			);
			if ( fontEl && inp.fontId ) {
				fontEl.value = inp.fontId;
				this.updateFontCombobox( fontEl );
			}

			const swatch = document.querySelector(
				`[data-oc-layer-swatch="${ layerId }"][data-hex="${ inp.colorHex }"]`
			);
			if ( swatch ) {
				swatch
					.closest( '.oc-colour-swatches' )
					?.querySelectorAll( '.oc-colour-swatch' )
					.forEach( ( s ) =>
						s.classList.toggle( 'oc-selected', s === swatch )
					);
			}

			const colorEl = document.querySelector(
				`[data-oc-layer-color="${ layerId }"]`
			);
			if ( colorEl && inp.colorHex ) {
				colorEl.value = inp.colorHex;
			}

			const sizeEl = document.querySelector(
				`[data-oc-layer-font-size="${ layerId }"]`
			);
			if ( sizeEl && inp.fontSize ) {
				sizeEl.value = inp.fontSize;
				document
					.querySelector(
						`.oc-range-value[data-oc-range-value="${ layerId }"]`
					)
					?.replaceChildren(
						document.createTextNode( sizeEl.value )
					);
			}

			const clipartBtn = document.querySelector(
				`[data-oc-layer-clipart="${ layerId }"][data-oc-clipart="${ inp.clipartId }"]`
			);
			if ( clipartBtn ) {
				clipartBtn
					.closest( '.oc-clipart-grid' )
					?.querySelectorAll( '.oc-clipart-item' )
					.forEach( ( i ) =>
						i.classList.toggle( 'oc-selected', i === clipartBtn )
					);
			}
		}

		this.updateHiddenField();
		this.areas.forEach( ( _, i ) => this.redraw( i ) );
	}

	syncInputsFromDOM() {
		this.areas.forEach( ( area ) => {
			( area.layers || [] ).forEach( ( layer ) => {
				const layerId = layer.id;
				if ( ! this.inputs[ layerId ] ) {
					this.inputs[ layerId ] = {};
				}
				const input = this.inputs[ layerId ];

				const textEl = document.querySelector(
					`[data-oc-layer-text="${ layerId }"]`
				);
				if ( textEl ) {
					const limit = this.charLimitForLayer( layerId );
					input.value =
						limit > 0
							? this.truncateText( textEl.value, limit )
							: textEl.value;
				}

				const spotifyEl = document.querySelector(
					`[data-oc-layer-spotify="${ layerId }"]`
				);
				if ( spotifyEl ) {
					input.value = spotifyEl.value;
				}

				const fontEl = document.querySelector(
					`[data-oc-layer-font="${ layerId }"]`
				);
				if ( fontEl ) {
					input.fontId = parseInt( fontEl.value, 10 ) || 0;
				}

				const sizeEl = document.querySelector(
					`[data-oc-layer-font-size="${ layerId }"]`
				);
				if ( sizeEl ) {
					input.fontSize = Math.max(
						1,
						parseInt( sizeEl.value, 10 ) || 1
					);
				}

				const colorEl = document.querySelector(
					`[data-oc-layer-color="${ layerId }"]`
				);
				if ( colorEl ) {
					input.colorHex = colorEl.value;
				} else {
					const selectedSwatch = document.querySelector(
						`[data-oc-layer-swatch="${ layerId }"].oc-selected`
					);
					if ( selectedSwatch?.dataset.hex ) {
						input.colorHex = selectedSwatch.dataset.hex;
					}
				}

				const selectedClipart = document.querySelector(
					`[data-oc-layer-clipart="${ layerId }"].oc-selected`
				);
				if ( selectedClipart ) {
					input.clipartId =
						parseInt( selectedClipart.dataset.ocClipart, 10 ) || 0;
					input.clipartUrl =
						selectedClipart.dataset.ocClipartUrl || '';
					input.clipartRecolourable =
						selectedClipart.dataset.ocClipartRecolourable === '1';
				}
			} );
		} );

		this.updateHiddenField();
	}

	switchArea( index ) {
		this.activeArea = index;
		document.querySelectorAll( '.oc-area-tab' ).forEach( ( btn, i ) => {
			btn.classList.toggle( 'oc-active', i === index );
			btn.setAttribute( 'aria-selected', i === index ? 'true' : 'false' );
			btn.setAttribute( 'tabindex', i === index ? '0' : '-1' );
		} );
		document.querySelectorAll( '.oc-area-controls' ).forEach( ( el ) => {
			el.style.display =
				parseInt( el.dataset.areaIndex, 10 ) === index ? '' : 'none';
		} );
		this.redraw( index );
		document
			.querySelectorAll(
				'.oc-area-controls[data-area-index="' +
					index +
					'"] [data-oc-clipart-carousel]'
			)
			.forEach( ( carousel ) => {
				this.refreshClipartCarousel(
					parseInt( carousel.dataset.ocClipartCarousel, 10 )
				);
			} );

		if ( window.innerWidth < 640 ) {
			const activeTab = document.querySelector(
				`.oc-area-tab[aria-selected="true"]`
			);
			if ( activeTab ) {
				activeTab.scrollIntoView( {
					behavior: 'smooth',
					block: 'nearest',
					inline: 'center',
				} );
			}
		}
	}

	// ── Cart serialisation ────────────────────────────────────────────────────────

	async captureAreaSnapshots() {
		const snapshots = {};
		for ( const [ areaIndex, area ] of this.areas.entries() ) {
			const canvas = this.canvases[ areaIndex ];
			const bounds = this.areaBounds( area );
			const display = displayBounds( bounds );
			const scale = canvas?._ocScaleX || 1;
			if (
				! canvas ||
				! display?.w ||
				! display?.h ||
				typeof canvas.toSVG !== 'function'
			) {
				continue;
			}

			await this.redraw( areaIndex, {
				renderGroup: false,
				pushGallery: false,
			} );

			const objects = canvas.getObjects ? canvas.getObjects() : [];
			const imageSources = objects
				.filter(
					( obj ) => obj._ocContent === true && obj._ocSourceUrl
				)
				.map( ( obj ) => ( {
					url: obj._ocSourceUrl,
					color: obj._ocSnapshotColor || '',
				} ) );
			const previousExportFlags = objects.map( ( obj ) => [
				obj,
				obj.excludeFromExport,
			] );
			objects.forEach( ( obj ) => {
				obj.excludeFromExport = obj._ocContent !== true;
			} );

			try {
				let svg = canvas.toSVG( {
					width: Math.max(
						1,
						Math.round( Number( display.w ) * scale )
					),
					height: Math.max(
						1,
						Math.round( Number( display.h ) * scale )
					),
					viewBox: {
						x: Number( display.x || 0 ) * scale,
						y: Number( display.y || 0 ) * scale,
						width: Math.max( 1, Number( display.w ) * scale ),
						height: Math.max( 1, Number( display.h ) * scale ),
					},
				} );
				svg = await this.outlineSnapshotText( svg );
				svg = await this.inlineSnapshotSvgImages( svg, imageSources );
				if ( svg && svg.includes( '<svg' ) ) {
					snapshots[ area.id || area.areaId || areaIndex ] = {
						format: 'fabric-svg-v1',
						unit: 'mockup_px',
						scale,
						svg,
					};
				}
			} catch {
				// Snapshot export is best-effort; PHP generation keeps the layer fallback.
			} finally {
				previousExportFlags.forEach( ( [ obj, flag ] ) => {
					obj.excludeFromExport = flag;
				} );
			}
		}

		await this.redraw( this.activeArea );

		return snapshots;
	}

	async inlineSnapshotSvgImages( svg, imageSources = [] ) {
		if ( ! svg || ! svg.includes( '<image' ) || ! imageSources.length ) {
			return svg;
		}

		const doc = new DOMParser().parseFromString( svg, 'image/svg+xml' );
		const svgEl = doc.documentElement;
		if ( ! svgEl || svgEl.nodeName.toLowerCase() !== 'svg' ) {
			return svg;
		}

		const imageNodes = Array.from( svgEl.querySelectorAll( 'image' ) );
		for ( let index = 0; index < imageNodes.length; index++ ) {
			const source = imageSources[ index ];
			if ( ! source?.url ) {
				continue;
			}

			let sourceSvg = '';
			try {
				sourceSvg = await this.svgSourceForSnapshotImage( source.url );
			} catch {
				continue;
			}
			if ( ! sourceSvg ) {
				continue;
			}

			const sourceDoc = new DOMParser().parseFromString(
				sourceSvg,
				'image/svg+xml'
			);
			const sourceEl = sourceDoc.documentElement;
			if ( ! sourceEl || sourceEl.nodeName.toLowerCase() !== 'svg' ) {
				continue;
			}

			if ( source.color ) {
				this.flattenSnapshotPatternPaint( sourceEl, source.color );
			}

			const replacement = this.snapshotImageReplacementGroup(
				doc,
				imageNodes[ index ],
				sourceEl
			);
			if ( replacement ) {
				imageNodes[ index ].replaceWith( replacement );
			}
		}

		return new XMLSerializer().serializeToString( svgEl );
	}

	async svgSourceForSnapshotImage( url ) {
		const value = String( url || '' );
		if ( value.startsWith( 'data:image/svg+xml' ) ) {
			const payload = value.slice( value.indexOf( ',' ) + 1 );
			return decodeURIComponent( payload );
		}

		const cleanUrl = value.split( '?' )[ 0 ].toLowerCase();
		if ( ! cleanUrl.endsWith( '.svg' ) ) {
			return '';
		}

		const response = await fetch( value, {
			credentials: 'same-origin',
			cache: 'force-cache',
		} );
		if ( ! response.ok ) {
			return '';
		}
		return response.text();
	}

	flattenSnapshotPatternPaint( element, color ) {
		Array.from( element.querySelectorAll( '[fill], [stroke]' ) ).forEach(
			( node ) => {
				[ 'fill', 'stroke' ].forEach( ( attr ) => {
					const value = String(
						node.getAttribute( attr ) || ''
					).trim();
					if ( /^url\(/i.test( value ) ) {
						node.setAttribute( attr, color );
					}
				} );
			}
		);
		Array.from( element.querySelectorAll( '[style]' ) ).forEach(
			( node ) => {
				node.setAttribute(
					'style',
					String( node.getAttribute( 'style' ) || '' ).replace(
						/\b(fill|stroke)\s*:\s*url\([^;)]+\)/gi,
						`$1:${ color }`
					)
				);
			}
		);
	}

	snapshotImageReplacementGroup( doc, imageNode, sourceEl ) {
		const viewBox = this.svgViewBoxValues( sourceEl );
		if ( ! viewBox ) {
			return null;
		}

		const [ vbX, vbY, vbW, vbH ] = viewBox;
		const imageX = this.svgNumber( imageNode.getAttribute( 'x' ), 0 );
		const imageY = this.svgNumber( imageNode.getAttribute( 'y' ), 0 );
		const imageW = this.svgNumber( imageNode.getAttribute( 'width' ), vbW );
		const imageH = this.svgNumber(
			imageNode.getAttribute( 'height' ),
			vbH
		);
		if ( vbW <= 0 || vbH <= 0 || imageW <= 0 || imageH <= 0 ) {
			return null;
		}

		const ns = 'http://www.w3.org/2000/svg';
		const outer = doc.createElementNS( ns, 'g' );
		[ 'transform', 'opacity', 'clip-path' ].forEach( ( attr ) => {
			if ( imageNode.hasAttribute( attr ) ) {
				outer.setAttribute( attr, imageNode.getAttribute( attr ) );
			}
		} );

		const inner = doc.createElementNS( ns, 'g' );
		inner.setAttribute(
			'transform',
			`translate(${ imageX } ${ imageY }) scale(${ imageW / vbW } ${
				imageH / vbH
			}) translate(${ -vbX } ${ -vbY })`
		);

		Array.from( sourceEl.childNodes ).forEach( ( child ) => {
			inner.appendChild( doc.importNode( child, true ) );
		} );
		outer.appendChild( inner );

		return outer;
	}

	svgViewBoxValues( svgEl ) {
		const viewBox = String( svgEl.getAttribute( 'viewBox' ) || '' ).trim();
		if ( viewBox ) {
			const parts = viewBox.split( /[\s,]+/ ).map( Number );
			if ( parts.length >= 4 && parts.every( Number.isFinite ) ) {
				return parts.slice( 0, 4 );
			}
		}

		const width = this.svgNumber( svgEl.getAttribute( 'width' ), 0 );
		const height = this.svgNumber( svgEl.getAttribute( 'height' ), 0 );
		return width > 0 && height > 0 ? [ 0, 0, width, height ] : null;
	}

	async outlineSnapshotText( svg ) {
		if ( ! svg || ! svg.includes( '<text' ) ) {
			return svg;
		}

		const doc = new DOMParser().parseFromString( svg, 'image/svg+xml' );
		const svgEl = doc.documentElement;
		if ( ! svgEl || svgEl.nodeName.toLowerCase() !== 'svg' ) {
			return svg;
		}

		const textNodes = Array.from( svgEl.querySelectorAll( 'text' ) );
		for ( const textNode of textNodes ) {
			const fontFamily = this.cleanSvgFontFamily(
				textNode.getAttribute( 'font-family' ) ||
					textNode.style?.fontFamily ||
					''
			);
			const font = this.fonts.find(
				( item ) => item.name === fontFamily
			);
			if ( ! font ) {
				continue;
			}

			let outlineFont = null;
			try {
				outlineFont = await this.loadOutlineFont( font );
			} catch {
				continue;
			}

			const pathData = this.svgTextNodeToPathData(
				textNode,
				outlineFont
			);
			if ( ! pathData ) {
				continue;
			}

			const path = doc.createElementNS(
				'http://www.w3.org/2000/svg',
				'path'
			);
			path.setAttribute( 'd', pathData );
			[
				'fill',
				'stroke',
				'stroke-width',
				'opacity',
				'fill-opacity',
				'stroke-opacity',
				'transform',
			].forEach( ( attr ) => {
				if ( textNode.hasAttribute( attr ) ) {
					path.setAttribute( attr, textNode.getAttribute( attr ) );
				}
			} );
			if ( ! path.hasAttribute( 'fill' ) ) {
				path.setAttribute( 'fill', '#000000' );
			}
			textNode.replaceWith( path );
		}

		return new XMLSerializer().serializeToString( svgEl );
	}

	cleanSvgFontFamily( value ) {
		return String( value || '' )
			.split( ',' )[ 0 ]
			.trim()
			.replace( /^['"]|['"]$/g, '' );
	}

	async loadOutlineFont( font ) {
		if ( ! font?.name || ! font?.url ) {
			throw new Error( 'Missing font.' );
		}
		if ( this.outlineFontCache[ font.name ] ) {
			return this.outlineFontCache[ font.name ];
		}

		this.outlineFontCache[ font.name ] = fetch( font.url, {
			credentials: 'same-origin',
			cache: 'force-cache',
		} )
			.then( ( response ) => {
				if ( ! response.ok ) {
					throw new Error( 'Font download failed.' );
				}
				return response.arrayBuffer();
			} )
			.then( ( buffer ) => {
				const parsed = createFont( buffer, {
					type: this.fontTypeFromUrl( font.url ),
					compound2simple: true,
				} ).get();
				return {
					font,
					ttf: parsed,
					unitsPerEm: Number( parsed?.head?.unitsPerEm ) || 1000,
					glyphs: this.glyphMapForFont( parsed ),
				};
			} )
			.catch( ( err ) => {
				delete this.outlineFontCache[ font.name ];
				throw err;
			} );

		return this.outlineFontCache[ font.name ];
	}

	fontTypeFromUrl( url ) {
		const cleanUrl = String( url || '' )
			.split( '?' )[ 0 ]
			.toLowerCase();
		const ext = cleanUrl.split( '.' ).pop();
		return [ 'ttf', 'otf', 'woff', 'woff2', 'eot', 'svg' ].includes( ext )
			? ext
			: 'ttf';
	}

	glyphMapForFont( parsed ) {
		const glyphs = {};
		( parsed?.glyf || [] ).forEach( ( glyph ) => {
			( glyph.unicode || [] ).forEach( ( code ) => {
				glyphs[ code ] = glyph;
			} );
		} );
		return glyphs;
	}

	svgTextNodeToPathData( textNode, outlineFont ) {
		const chunks = this.svgTextChunks( textNode );
		if ( ! chunks.length ) {
			return '';
		}

		return chunks
			.map( ( chunk ) =>
				this.textChunkToPathData( chunk, textNode, outlineFont )
			)
			.filter( Boolean )
			.join( ' ' );
	}

	svgTextChunks( textNode ) {
		const tspans = Array.from( textNode.querySelectorAll( 'tspan' ) );
		if ( tspans.length ) {
			return tspans
				.map( ( tspan ) => ( {
					text: tspan.textContent || '',
					x: this.svgNumber(
						tspan.getAttribute( 'x' ),
						this.svgNumber( textNode.getAttribute( 'x' ), 0 )
					),
					y: this.svgNumber(
						tspan.getAttribute( 'y' ),
						this.svgNumber( textNode.getAttribute( 'y' ), 0 )
					),
					fontSize: this.svgNumber(
						tspan.getAttribute( 'font-size' ),
						this.svgNumber(
							textNode.getAttribute( 'font-size' ),
							16
						)
					),
					anchor:
						tspan.getAttribute( 'text-anchor' ) ||
						textNode.getAttribute( 'text-anchor' ) ||
						'start',
				} ) )
				.filter( ( chunk ) => chunk.text );
		}

		return [
			{
				text: textNode.textContent || '',
				x: this.svgNumber( textNode.getAttribute( 'x' ), 0 ),
				y: this.svgNumber( textNode.getAttribute( 'y' ), 0 ),
				fontSize: this.svgNumber(
					textNode.getAttribute( 'font-size' ),
					16
				),
				anchor: textNode.getAttribute( 'text-anchor' ) || 'start',
			},
		].filter( ( chunk ) => chunk.text );
	}

	textChunkToPathData( chunk, textNode, outlineFont ) {
		const scale = chunk.fontSize / outlineFont.unitsPerEm;
		const advance =
			this.textAdvanceWidth( chunk.text, outlineFont ) * scale;
		let cursor = chunk.x;
		const anchor = String( chunk.anchor || '' ).toLowerCase();
		if ( anchor === 'middle' ) {
			cursor -= advance / 2;
		}
		if ( anchor === 'end' ) {
			cursor -= advance;
		}

		const parts = [];
		for ( const char of Array.from( chunk.text ) ) {
			const glyph = outlineFont.glyphs[ char.codePointAt( 0 ) ];
			if ( glyph?.contours?.length ) {
				parts.push(
					this.glyphToSvgPath( glyph, cursor, chunk.y, scale )
				);
			}
			cursor +=
				( Number( glyph?.advanceWidth ) ||
					outlineFont.unitsPerEm * 0.5 ) * scale;
		}

		return parts.filter( Boolean ).join( ' ' );
	}

	textAdvanceWidth( text, outlineFont ) {
		return Array.from( text ).reduce( ( width, char ) => {
			const glyph = outlineFont.glyphs[ char.codePointAt( 0 ) ];
			return (
				width +
				( Number( glyph?.advanceWidth ) ||
					outlineFont.unitsPerEm * 0.5 )
			);
		}, 0 );
	}

	glyphToSvgPath( glyph, x, baseline, scale ) {
		return glyph.contours
			.map( ( contour ) =>
				this.contourToSvgPath( contour, x, baseline, scale )
			)
			.filter( Boolean )
			.join( ' ' );
	}

	contourToSvgPath( contour, x, baseline, scale ) {
		if ( ! contour?.length ) {
			return '';
		}
		const pointAt = ( index ) =>
			contour[ ( index + contour.length ) % contour.length ];
		const mid = ( a, b ) => ( {
			x: ( a.x + b.x ) / 2,
			y: ( a.y + b.y ) / 2,
			onCurve: true,
		} );
		const tx = ( point ) => Number( ( x + point.x * scale ).toFixed( 3 ) );
		const ty = ( point ) =>
			Number( ( baseline - point.y * scale ).toFixed( 3 ) );

		let startIndex = 0;
		let start = contour[ 0 ];
		const last = contour[ contour.length - 1 ];
		if ( ! start.onCurve ) {
			if ( last.onCurve ) {
				start = last;
				startIndex = contour.length - 1;
			} else {
				start = mid( last, start );
			}
		}

		const commands = [ `M${ tx( start ) } ${ ty( start ) }` ];
		let i = startIndex + 1;
		let processed = 0;
		while ( processed < contour.length ) {
			const p = pointAt( i );
			if ( p.onCurve ) {
				commands.push( `L${ tx( p ) } ${ ty( p ) }` );
				i += 1;
			} else {
				const next = pointAt( i + 1 );
				const end = next.onCurve ? next : mid( p, next );
				commands.push(
					`Q${ tx( p ) } ${ ty( p ) } ${ tx( end ) } ${ ty( end ) }`
				);
				i += next.onCurve ? 2 : 1;
			}
			processed = i - startIndex - 1;
		}

		commands.push( 'Z' );
		return commands.join( ' ' );
	}

	svgNumber( value, fallback = 0 ) {
		const num = parseFloat( String( value || '' ).replace( /px$/i, '' ) );
		return Number.isFinite( num ) ? num : fallback;
	}

	async updateHiddenField( includeSnapshots = false ) {
		const el = document.getElementById( 'oc-customisation-data' );
		if ( ! el ) {
			return;
		}
		const layers = {};
		this.areas.forEach( ( area ) => {
			( area.layers || [] ).forEach( ( layer ) => {
				const inp = this.inputs[ layer.id ];
				if ( inp ) {
					this.clampLayerInputValue( layer.id );
					layers[ layer.id ] = {
						type: layer.type,
						...this.inputs[ layer.id ],
					};
				}
			} );
		} );
		const payload = { v: 2, designId: this.data.designId, layers };
		if ( this.selectedDesignVariant ) {
			const variant = this.designVariants.find(
				( item ) => item.id === this.selectedDesignVariant
			);
			payload.designVariant = this.selectedDesignVariant;
			if ( variant?.label ) {
				payload.designVariantLabel = variant.label;
			}
		}
		if ( includeSnapshots ) {
			const snapshots = await this.captureAreaSnapshots();
			if ( Object.keys( snapshots ).length ) {
				payload.snapshots = snapshots;
			}
		}
		if ( this._previewUrl ) {
			payload.previewUrl = this._previewUrl;
		}
		el.value = JSON.stringify( payload );
	}
}

Object.assign( OCCustomiser.prototype, designVariantMethods );
Object.assign( OCCustomiser.prototype, galleryPreviewMethods );
Object.assign( OCCustomiser.prototype, clipartMethods );
Object.assign( OCCustomiser.prototype, preflightMethods );
Object.assign( OCCustomiser.prototype, spotifyMethods );
Object.assign( OCCustomiser.prototype, uploadMethods );
Object.assign( OCCustomiser.prototype, checkoutMethods );
