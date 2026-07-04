/**
 * Frontend Customiser — vanilla JS, no framework dependency.
 *
 * Data: window.ocCustomiserData (wp_localize_script).
 * Canvas: Fabric.js 6.x  |  Uploads: Uppy 4.x
 *
 * @package OverCustomise
 */

import { StaticCanvas, FabricImage, FabricText, Rect, Circle, Shadow, Pattern, filters as FabricFilters } from 'fabric';
import Uppy      from '@uppy/core';
import DragDrop  from '@uppy/drag-drop';
import XHRUpload from '@uppy/xhr-upload';

import '@uppy/core/dist/style.min.css';
import '@uppy/drag-drop/dist/style.min.css';
import './customiser-app.scss';

// ── Boot ──────────────────────────────────────────────────────────────────────

document.addEventListener( 'DOMContentLoaded', () => {
	const data = window.ocCustomiserData;
	if ( ! data || ! data.areas?.length ) return;
	new OCCustomiser( data ).init();
} );

// ── Main class ─────────────────────────────────────────────────────────────────

class OCCustomiser {

	constructor( data ) {
		this.data       = data;
		this.areas      = data.areas   || [];
		this.fonts      = data.fonts   || [];
		this.layersById = {};
		this.areas.forEach( area => ( area.layers || [] ).forEach( layer => {
			this.layersById[ layer.id ] = layer;
		} ) );
		this.designVariants = data.designVariants || [];
		this.selectedDesignVariant = this.designVariants[ 0 ]?.id || '';
		this.activeArea = 0;

		// Deep-clone mutable per-layer inputs; keys are integer layer IDs.
		this.inputs = {};
		Object.entries( data.layerInputs || {} ).forEach( ( [ k, v ] ) => {
			const layerId = parseInt( k, 10 );
			this.inputs[ layerId ] = { ...v };
			this.clampLayerInputValue( layerId );
		} );

		this.editMode      = !!( data.editMode && data.cartKey );
		this.cartKey       = this.editMode ? data.cartKey : '';
		this.canvases      = {};   // areaIndex → Fabric StaticCanvas
		this.fontCache     = {};   // fontName  → load Promise
		this.galleryImg        = null; // the main <img> in the product gallery
		this._previewUrl       = null; // saved preview URL (set just before cart submit)
		this._focusPreviewSlide = false; // jump TVPG to preview slide after user edits
		this.spotifyValidateTimers = {};
		this.spotifyValidateTokens = {};
		this.preflightRoot = null;
		this.clipartByGroup = {};
		this.clipartSearchTimers = {};
		this.clipartSearchTerms = {};
		this.clipartCategoryFilters = {};
		this.spotifyModalCloseTimer = null;
		this.mobileCartPreviewDialog = null;

		if ( this.editMode ) {
			Object.entries( data.layerInputs || {} ).forEach( ( [ k, v ] ) => {
				const key = parseInt( k, 10 );
				if ( this.inputs[ key ] && typeof v === 'object' && v !== null ) {
					Object.assign( this.inputs[ key ], v );
					this.clampLayerInputValue( key );
				}
			} );
		}

		for ( const [ lidStr, items ] of Object.entries( data.clipartByLayer || {} ) ) {
			const lid = parseInt( lidStr, 10 );
			for ( const item of items ) {
				for ( const gn of ( item.groupNames || [] ) ) {
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
		const params = new URLSearchParams( { layer_id: String( layerId ) } );
		if ( this.data.uploadNonce ) params.set( '_wpnonce', this.data.uploadNonce );
		if ( this.data.requestToken ) params.set( 'oc_token', this.data.requestToken );
		return uploadUrl + ( uploadUrl.includes( '?' ) ? '&' : '?' ) + params.toString();
	}

	// ── Init ───────────────────────────────────────────────────────────────────

	init() {
		this.findGalleryImage();
		this.preflightRoot = document.getElementById( 'oc-preflight-messages' );

		// Seed configured/default fonts for text layers so they render immediately.
		if ( this.fonts.length ) {
			const firstFont = this.fonts[ 0 ];
			this.areas.forEach( area => {
				( area.layers || [] ).forEach( layer => {
					if ( ( layer.type === 'text' || layer.type === 'textarea' ) ) {
						const inp = this.inputs[ layer.id ];
						if ( inp && ! inp.fontId ) inp.fontId = layer.settings?.default_font_id || firstFont.id;
					}
				} );
			} );
		}

		// Wire up controls IMMEDIATELY — don't block on canvas.
		this.setupInputListeners();
		this.setupDesignVariantOptions();
		this.setupUploadZones();
		if ( this.editMode ) this.updateInputsFromDOM();
		this.setupFormSubmit();
		this.updateHiddenField();

		// Canvas init runs in background; calls redraw() when done.
		this.initAllCanvases();
	}

	// ── Gallery: find the <img> and store a reference ─────────────────────────

	findGalleryImage() {
		const SELECTORS = [
			// True Video Product Gallery (Swiper): prefer active non-video slide.
			'.tvpg-main-slider .swiper-slide-active:not(.tvpg-video-slide) .woocommerce-product-gallery__image img',
			'.tvpg-main-slider .swiper-slide-active .woocommerce-product-gallery__image img',
			'.tvpg-main-slider .swiper-slide:not(.tvpg-video-slide) .woocommerce-product-gallery__image img',
			// Flatsome theme
			'.product-gallery-slider .flickity-slider .woocommerce-product-gallery__image.is-selected img',
			'.product-gallery-slider .flickity-slider .slide.is-selected img',
			'.product-gallery-slider .is-selected img',
			'.product-gallery-slider .flickity-slider .woocommerce-product-gallery__image:first-child img',
			'.product-gallery-slider .flickity-slider .slide:first-child img',
			'.product-gallery .woocommerce-product-gallery__image:first-child img',
			'.product-images .woocommerce-product-gallery__image:first-child a img',
			'.product-image-wrap .woocommerce-product-gallery__image:first-child img',
			// WC Blocks
			'.wp-block-woocommerce-product-image-gallery .woocommerce-product-gallery__image:first-child img',
			// Default WC / Storefront
			'.woocommerce-product-gallery__image:first-child a img',
			'.woocommerce-product-gallery__image:first-child img',
			// Broad fallback
			'.woocommerce-product-gallery .wp-post-image',
			'.wp-post-image',
		];

		for ( const sel of SELECTORS ) {
			const img = document.querySelector( sel );
			if ( img ) {
				this.galleryImg = img;
				return;
			}
		}
		this.galleryImg = null;
	}

	applyPreviewToImage( img, dataUrl ) {
		if ( ! img ) return;
		img.src    = dataUrl;
		img.srcset = '';
		img.sizes  = '';

		// Update zoom / lightbox href if wrapped in <a>.
		const a = img.closest( 'a' );
		if ( a ) {
			a.href = dataUrl;
			a.setAttribute( 'data-src', dataUrl );
		}

		// WooCommerce zoom/lightbox compatibility attributes.
		img.setAttribute( 'data-large_image', dataUrl );
		img.setAttribute( 'data-large-image', dataUrl );
		img.setAttribute( 'data-src', dataUrl );
		img.setAttribute( 'data-lazy-src', dataUrl );
		img.setAttribute( 'data-zoom-image', dataUrl );
		img.removeAttribute( 'data-srcset' );
		img.removeAttribute( 'data-lazy-srcset' );
		img.removeAttribute( 'data-o_srcset' );
		img.removeAttribute( 'data-o_src' );

		const galleryItem = img.closest( '.woocommerce-product-gallery__image, .product-gallery-slider .slide' );
		if ( galleryItem ) {
			galleryItem.setAttribute( 'data-thumb', dataUrl );
		}
	}

	refreshFlatsomeGallery() {
		const slider = document.querySelector( '.product-gallery-slider' );
		if ( ! slider ) return;

		const flickity = slider.flickity || window.jQuery?.( slider ).data( 'flickity' );
		flickity?.reloadCells?.();
		flickity?.resize?.();
	}

	getFlickityInstance( slider ) {
		if ( ! slider ) return null;
		return slider.flickity || window.jQuery?.( slider ).data( 'flickity' ) || null;
	}

	applyFlatsomeOverlayPreview( dataUrl ) {
		const slider = document.querySelector( '.product-gallery-slider' );
		if ( ! slider ) return false;

		let flickity = this.getFlickityInstance( slider );
		let previewSlide = slider.querySelector( '.oc-live-preview-slide' );

		if ( ! previewSlide ) {
			previewSlide = document.createElement( 'div' );
			previewSlide.className = 'woocommerce-product-gallery__image slide oc-live-preview-slide';
			previewSlide.innerHTML =
				'<a href="#">' +
					'<img class="oc-live-preview-image wp-post-image" alt="Custom preview">' +
				'</a>';

			if ( flickity?.append ) {
				flickity.append( previewSlide );
			} else {
				slider.appendChild( previewSlide );
			}
		}

		const previewImg = previewSlide.querySelector( 'img.oc-live-preview-image' );
		if ( previewImg ) {
			this.applyPreviewToImage( previewImg, dataUrl );
		}

		previewSlide.setAttribute( 'data-thumb', dataUrl );
		previewSlide.querySelector( 'a' )?.setAttribute( 'href', dataUrl );

		flickity = this.getFlickityInstance( slider );
		if ( flickity ) {
			flickity.reloadCells?.();
			flickity.resize?.();

			const previewIndex = ( flickity.cells || [] ).findIndex( cell => cell.element === previewSlide );
			if ( previewIndex >= 0 ) {
				flickity.select?.( previewIndex, false, true );
			}
		}

		return true;
	}

	setPanelPreviewHandoff( isActive ) {
		const panel = document.getElementById( 'oc-customiser-panel' );
		if ( panel ) {
			panel.classList.toggle( 'oc-gallery-preview-active', isActive );
		}
	}

	mountPreviewInGallery() {
		const canvasWrap = document.getElementById( 'oc-canvas-wrap' );
		if ( ! canvasWrap ) return false;

		const gallery = document.querySelector(
			'.product-gallery, .product-images, .woocommerce-product-gallery, .product .images'
		);

		if ( ! gallery ) {
			canvasWrap.classList.add( 'oc-preview-visible' );
			return false;
		}

		if ( canvasWrap.parentElement !== gallery ) {
			gallery.prepend( canvasWrap );
		}

		canvasWrap.classList.add( 'oc-gallery-mounted-preview', 'oc-preview-visible' );
		return true;
	}

	applyTVPGOverlayPreview( dataUrl ) {
		const mainSliderEl = document.querySelector( '.tvpg-main-slider' );
		const mainWrapper  = mainSliderEl?.querySelector( '.swiper-wrapper' );
		if ( ! mainSliderEl || ! mainWrapper ) return false;

		let mainPreviewSlide = mainWrapper.querySelector( '.swiper-slide.oc-live-preview-slide' );
		if ( ! mainPreviewSlide ) {
			mainPreviewSlide = document.createElement( 'div' );
			mainPreviewSlide.className = 'swiper-slide oc-live-preview-slide';
			mainPreviewSlide.innerHTML =
				'<div class="woocommerce-product-gallery__image">' +
					'<img class="oc-live-preview-image" alt="Custom preview">' +
				'</div>';
			mainWrapper.appendChild( mainPreviewSlide );
		}

		const mainImg = mainPreviewSlide.querySelector( 'img.oc-live-preview-image' );
		if ( mainImg ) {
			this.applyPreviewToImage( mainImg, dataUrl );
		}

		const thumbSliderEl = document.querySelector( '.tvpg-thumb-slider' );
		const thumbWrapper  = thumbSliderEl?.querySelector( '.swiper-wrapper' );
		if ( thumbWrapper ) {
			let thumbPreviewSlide = thumbWrapper.querySelector( '.swiper-slide.oc-live-preview-thumb-slide' );
			if ( ! thumbPreviewSlide ) {
				thumbPreviewSlide = document.createElement( 'div' );
				thumbPreviewSlide.className = 'swiper-slide oc-live-preview-thumb-slide';
				thumbPreviewSlide.innerHTML = '<img class="oc-live-preview-thumb-image" alt="Custom preview thumbnail">';
				thumbWrapper.appendChild( thumbPreviewSlide );
			}

			const thumbImg = thumbPreviewSlide.querySelector( 'img.oc-live-preview-thumb-image' );
			if ( thumbImg ) {
				this.applyPreviewToImage( thumbImg, dataUrl );
			}
		}

		// Swiper attaches instances to the root element; update so the new last slide is navigable.
		const mainSwiper  = mainSliderEl.swiper;
		const thumbSwiper = thumbSliderEl?.swiper;

		mainSwiper?.update?.();
		thumbSwiper?.update?.();

		if ( this._focusPreviewSlide && mainSwiper?.slides?.length ) {
			const lastIndex = mainSwiper.slides.length - 1;
			mainSwiper.slideTo( lastIndex );
			thumbSwiper?.slideTo?.( lastIndex );
		}

		this._focusPreviewSlide = false;
		return true;
	}

	pushToGallery( canvas ) {
		this.findGalleryImage();

		let dataUrl;
		try {
			dataUrl = canvas.toDataURL( { format: 'jpeg', quality: 0.92 } );
		} catch ( e ) {
			console.warn( '[OC] toDataURL failed — image may be cross-origin:', e.message );
			return;
		}

		const previewImg = document.getElementById( 'oc-canvas-preview' );
		if ( previewImg ) {
			previewImg.src = dataUrl;
			previewImg.srcset = '';
		}

		if ( this.applyTVPGOverlayPreview( dataUrl ) ) {
			this.setPanelPreviewHandoff( true );
			this._focusPreviewSlide = false;
			return;
		}

		if ( this.applyFlatsomeOverlayPreview( dataUrl ) ) {
			this.setPanelPreviewHandoff( true );
			this._focusPreviewSlide = false;
			return;
		}

		const targets = new Set();
		if ( this.galleryImg ) {
			targets.add( this.galleryImg );
		}

		[
			'.tvpg-main-slider .swiper-slide .woocommerce-product-gallery__image img',
			'.product-gallery-slider .flickity-slider .woocommerce-product-gallery__image img',
			'.product-gallery-slider .flickity-slider .slide img',
			'.product-gallery-slider .slide img',
			'.product-gallery-slider img',
			'.product-thumbnails img',
			'.product-gallery .woocommerce-product-gallery__image img',
			'.product-images .woocommerce-product-gallery__image img',
			'.product-image-wrap .woocommerce-product-gallery__image img',
			'.woocommerce-product-gallery .woocommerce-product-gallery__image img',
		].forEach( selector => {
			document.querySelectorAll( selector ).forEach( img => targets.add( img ) );
		} );

		const applyTargets = () => targets.forEach( img => this.applyPreviewToImage( img, dataUrl ) );
		applyTargets();

		if ( document.querySelector( '.product-gallery-slider' ) ) {
			this.refreshFlatsomeGallery();
			requestAnimationFrame( applyTargets );
			setTimeout( applyTargets, 250 );
		}

		this.setPanelPreviewHandoff( targets.size > 0 || this.mountPreviewInGallery() );

		this._focusPreviewSlide = false;
	}

	requestPreviewFocus() {
		this._focusPreviewSlide = true;
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

		// Use mockup natural width when available (works even when canvas is visually hidden).
		// Cap at 1200px for performance; fall back to element width or 600px.
		await new Promise( r => requestAnimationFrame( r ) );
		const displayW = area.mockupW
			? Math.min( area.mockupW, 1200 )
			: Math.max( canvasEl.parentElement?.offsetWidth || 0, 600 );

		if ( ! area.mockupUrl ) {
			this.canvases[ areaIndex ] = this.blankCanvas( canvasEl, displayW, 240,
				'No mockup set. Add one in the Design Editor.' );
			return;
		}

		let mockupImg;
		try {
			// Do NOT use crossOrigin:'anonymous' — WordPress uploads are same-origin
			// and CORS headers aren't sent, which would taint the canvas and break toDataURL.
			mockupImg = await Promise.race( [
				FabricImage.fromURL( area.mockupUrl ),
				new Promise( ( _, rej ) => setTimeout( () => rej( new Error( 'timeout' ) ), 10000 ) ),
			] );
		} catch ( e ) {
			console.warn( '[OC] Mockup failed to load:', area.mockupUrl, e.message );
			this.canvases[ areaIndex ] = this.blankCanvas( canvasEl, displayW, 240,
				'Mockup image could not load.' );
			return;
		}

		const scaleX   = displayW / ( area.mockupW || mockupImg.width || 1 );
		const displayH = Math.round( ( area.mockupH || mockupImg.height ) * scaleX );
		const canvas   = new StaticCanvas( canvasEl, { width: displayW, height: displayH } );

		mockupImg.set( { left: 0, top: 0, scaleX, scaleY: scaleX, selectable: false } );
		canvas.add( mockupImg );

		// Dashed print-bounds guide.
		const b = area.bounds;
		if ( b && b.w > 0 ) {
			canvas.add( new Rect( {
				left: ( b.x + b.w / 2 ) * scaleX, top: ( b.y + b.h / 2 ) * scaleX,
				originX: 'center', originY: 'center', angle: Number( b.rotation ) || 0,
				width: b.w * scaleX, height: b.h * scaleX,
				fill: 'rgba(255,255,255,0.05)',
				stroke: 'rgba(255,255,255,0.7)',
				strokeWidth: 1.5, strokeDashArray: [ 5, 4 ],
				selectable: false, evented: false,
			} ) );
		}

		canvas._ocScaleX = scaleX;
		canvas._ocArea   = area;
		canvas.renderAll();
		this.canvases[ areaIndex ] = canvas;

		console.log( `[OC] Canvas ${ areaIndex } ready — ${ displayW }x${ displayH }, scale=${ scaleX.toFixed( 3 ) }` );
	}

	async rebuildCanvas( areaIndex ) {
		const oldCanvas = this.canvases[ areaIndex ];
		if ( oldCanvas?.dispose ) {
			oldCanvas.dispose();
		}
		delete this.canvases[ areaIndex ];

		const oldEl = document.getElementById( `oc-canvas-${ areaIndex }` );
		if ( ! oldEl ) return;

		const canvasEl = document.createElement( 'canvas' );
		canvasEl.id = oldEl.id;
		oldEl.replaceWith( canvasEl );

		await this.initCanvas( canvasEl, areaIndex );
		await this.redraw( areaIndex );
	}

	blankCanvas( el, w, h, msg ) {
		const c = new StaticCanvas( el, { width: w, height: h, backgroundColor: '#f0f0f0' } );
		const t = new FabricText( msg, {
			left: w / 2, top: h / 2, originX: 'center', originY: 'center',
			fontSize: 12, fill: '#888', fontFamily: 'sans-serif', textAlign: 'center',
			selectable: false,
		} );
		c.add( t );
		c.renderAll();
		c._ocScaleX = 1;
		return c;
	}

	// ── Redraw ──────────────────────────────────────────────────────────────────

	scheduleRedraw() {
		clearTimeout( this._redrawTimer );
		this._redrawTimer = setTimeout( () => this.redraw( this.activeArea ), 120 );
	}

	async redraw( areaIndex ) {
		const canvas = this.canvases[ areaIndex ];
		if ( ! canvas ) return; // canvas not ready yet — will redraw after initCanvas

		// Remove previously added content objects.
		[ ...canvas.getObjects() ]
			.filter( o => o._ocContent === true )
			.forEach( o => canvas.remove( o ) );

		const area = this.areas[ areaIndex ];
		for ( const layer of ( area?.layers ?? [] ) ) {
			// PHP already filters to visible-only layers — no client-side check needed.
			await this.renderLayer( canvas, layer, this.inputs[ layer.id ] || {}, area );
		}

		canvas.renderAll();
		if ( areaIndex === this.activeArea ) this.pushToGallery( canvas );
	}

	async renderLayer( canvas, layer, input, area ) {
		const scale       = canvas._ocScaleX ?? 1;
		const bounds      = area?.bounds || {};
		const rotation    = Number( bounds.rotation ) || 0;
		const contentClip = () => this.printAreaClipPath( bounds, scale );
		const center      = this.rotatedLayerCenter( layer, bounds, rotation );
		const lx          = ( center.x - layer.w / 2 ) * scale;
		const ly          = ( center.y - layer.h / 2 ) * scale;
		const lw          = Math.max( layer.w * scale, 10 );
		const lh          = Math.max( layer.h * scale, 10 );
		const lcX         = center.x * scale;
		const lcY         = center.y * scale;
		const isEngraving = area?.printMethod === 'engraving';
		const isEmbroidery = area?.printMethod === 'embroidery';
		const engravingPalette = this.engravingPalette();
		const fontLimit = value => Math.max( 0, parseInt( value, 10 ) || 0 );
		const clampFontSize = ( size, settings ) => {
			const min = fontLimit( settings?.min_font_size ) * scale;
			const max = fontLimit( settings?.max_font_size ) * scale;
			if ( max && ( ! min || min <= max ) ) size = Math.min( size, max );
			if ( min ) size = Math.max( size, min );
			return size;
		};

			switch ( layer.type ) {

			case 'text':
			case 'textarea': {
				const raw  = ( input.value || '' ).trim() || ( layer.settings?.default_text || '' ).trim();
				if ( ! raw ) break;

				let font  = this.fonts.find( f => f.id === ( input.fontId || 0 ) );
				// Engraving uses a fixed silver tone instead of a customer-selected colour.
				const color = isEngraving ? engravingPalette.text : ( input.colorHex || layer.settings?.default_color || '#000000' );
				const align = layer.settings?.alignment || 'center';
				if ( font ) {
					try {
						await this.loadFont( font );
					} catch ( err ) {
						console.warn( '[OC] Font load failed, falling back to sans-serif:', err );
						font = null;
					}
				}

				const minFontSize = fontLimit( layer.settings?.min_font_size ) * scale;
				const configuredFontSize = input.fontSize || layer.settings?.default_font_size;
				let fontSize = configuredFontSize
					? clampFontSize( Math.max( 1, parseInt( configuredFontSize, 10 ) ) * scale, layer.settings )
					: clampFontSize( Math.max( 10, Math.round( lh * 0.42 ) ), layer.settings );
				const textFill = isEmbroidery ? this.embroideryPattern( color, fontSize ) : color;
				const obj    = new FabricText( raw, {
					left: lcX, top: lcY,
					originX: 'center', originY: 'center',
					width: lw,
					angle: rotation,
					fontFamily: font?.name || 'sans-serif',
					fontSize, fill: textFill, textAlign: align,
					selectable: false, evented: false,
				} );
				obj._ocContent = true; // tag after creation
				let stitchPad = null;
				let stitchLift = null;

				if ( isEngraving ) {
					// Fake etched depth: subtle light highlight below + soft dark shadow above.
					obj.set( {
						opacity: 0.92,
						shadow: new Shadow( { color: engravingPalette.highlight, offsetX: 0, offsetY: 1, blur: 1 } ),
					} );
				} else if ( isEmbroidery ) {
					const threadLift = this.embroideryHighlightColor( color );
					const threadShadow = this.embroideryShadowColor( color );

					stitchPad = new FabricText( raw, {
						left: lcX + Math.max( 0.45, fontSize * 0.015 ),
						top: lcY + Math.max( 0.65, fontSize * 0.02 ),
						originX: 'center', originY: 'center',
						width: lw,
						angle: rotation,
						fontFamily: font?.name || 'sans-serif',
						fontSize,
						fill: threadShadow,
						opacity: 0.24,
						shadow: new Shadow( { color: 'rgba(0,0,0,0.22)', offsetX: 0.6, offsetY: 0.9, blur: 1.8 } ),
						textAlign: align,
						selectable: false,
						evented: false,
					} );
					stitchPad._ocContent = true;
					canvas.add( stitchPad );

					stitchLift = new FabricText( raw, {
						left: lcX - Math.max( 0.25, fontSize * 0.006 ),
						top: lcY - Math.max( 0.25, fontSize * 0.006 ),
						originX: 'center', originY: 'center',
						width: lw,
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
					} );
					stitchLift._ocContent = true;
					canvas.add( stitchLift );

					obj.set( {
						stroke: this.embroiderySoftEdgeColor( color ),
						strokeWidth: Math.max( 0.18, fontSize * 0.005 ),
						shadow: new Shadow( { color: 'rgba(0,0,0,0.22)', offsetX: 0.7, offsetY: 0.95, blur: 1.1 } ),
					} );
				}

				while ( ( obj.width > lw || obj.height > lh ) && fontSize > Math.max( 8, minFontSize ) ) {
					fontSize -= 1;
					obj.set( { fontSize } );
				}
				if ( isEmbroidery ) {
					obj.set( { fill: this.embroideryPattern( color, fontSize ) } );
				}
				if ( stitchPad ) {
					stitchPad.set( {
						left: lcX + Math.max( 0.45, fontSize * 0.015 ),
						top: lcY + Math.max( 0.65, fontSize * 0.02 ),
						fontSize,
					} );
					this.applyContentClip( stitchPad, contentClip() );
				}
				if ( stitchLift ) {
					stitchLift.set( {
						left: lcX - Math.max( 0.25, fontSize * 0.006 ),
						top: lcY - Math.max( 0.25, fontSize * 0.006 ),
						fontSize,
						strokeWidth: Math.max( 0.2, fontSize * 0.006 ),
					} );
					this.applyContentClip( stitchLift, contentClip() );
				}
				this.applyContentClip( obj, contentClip() );
				canvas.add( obj );
				break;
			}

			case 'image':
				if ( input.attachmentUrl ) await this.renderFabricImg( canvas, input.attachmentUrl, lx, ly, lw, lh, isEngraving, 'anonymous', false, rotation, engravingPalette, contentClip() );
				break;

			case 'clipmask':
				if ( input.attachmentUrl ) await this.renderFabricImg( canvas, input.attachmentUrl, lx, ly, lw, lh, isEngraving, 'anonymous', false, rotation, engravingPalette, this.layerClipPath( lx, ly, lw, lh, rotation, layer.settings ), 'cover' );
				break;

			case 'clipart':
				if ( input.clipartUrl ) {
					const clipartColor = input.clipartRecolourable && ! isEngraving ? String( input.colorHex || '' ).trim() : '';
					await this.renderFabricImg( canvas, input.clipartUrl, lx, ly, lw, lh, isEngraving, 'anonymous', false, rotation, engravingPalette, contentClip(), 'contain', clipartColor );
				}
				break;

			case 'lineart': {
				const lineartColor = isEngraving ? engravingPalette.text : String( input.colorHex || '' ).trim();
				if ( ! lineartColor ) break;
				const r = new Rect( { left: lcX, top: lcY, originX: 'center', originY: 'center', angle: rotation, width: lw, height: lh,
					fill: lineartColor, opacity: 0.6, selectable: false, evented: false } );
				r._ocContent = true;
				this.applyContentClip( r, contentClip() );
				canvas.add( r );
				break;
			}

			case 'spotify': {
				const val = ( input.value || '' ).trim();
				if ( ! val ) break;

				if ( input.spotifyStatus === 'invalid_format' || input.spotifyStatus === 'playlist_private_or_invalid' || input.spotifyStatus === 'invalid_or_unavailable' ) {
					const invalidText = input.spotifyStatus === 'playlist_private_or_invalid'
						? 'Private / invalid Spotify playlist'
						: 'Invalid Spotify link';
					const invalidObj = new FabricText( invalidText, {
						left: lcX, top: lcY,
						originX: 'center', originY: 'center',
						angle: rotation,
						fontFamily: 'monospace', fontSize: Math.max( 9, Math.round( lh * 0.17 ) ),
						fill: '#b32d2e',
						textAlign: 'center', selectable: false, evented: false,
					} );
					invalidObj._ocContent = true;
					this.applyContentClip( invalidObj, contentClip() );
					canvas.add( invalidObj );
					break;
				}

				const spotifyCodeUrl = this.buildSpotifyCodeUrl( input.spotifyUri || val, isEngraving, engravingPalette );
				if ( spotifyCodeUrl ) {
					// Try CORS-safe load first; if Spotify CDN blocks CORS for this origin,
					// retry without crossOrigin so users still see the scannable in live preview.
					let rendered = await this.renderFabricImg( canvas, spotifyCodeUrl, lx, ly, lw, lh, isEngraving, 'anonymous', true, rotation, engravingPalette, contentClip() );
					if ( ! rendered ) {
						rendered = await this.renderFabricImg( canvas, spotifyCodeUrl, lx, ly, lw, lh, isEngraving, '', true, rotation, engravingPalette, contentClip() );
					}
					if ( rendered ) break;
				}

				const fallback = new FabricText( '\u266b Spotify code unavailable', {
					left: lcX, top: lcY,
					originX: 'center', originY: 'center',
					angle: rotation,
					fontFamily: 'monospace', fontSize: Math.max( 9, Math.round( lh * 0.22 ) ),
					fill: '#666666',
					textAlign: 'center', selectable: false, evented: false,
				} );
				fallback._ocContent = true;
				this.applyContentClip( fallback, contentClip() );
				canvas.add( fallback );
				break;
			}
		}
	}

	rotatedLayerCenter( layer, bounds, rotation ) {
		let x = layer.x + layer.w / 2;
		let y = layer.y + layer.h / 2;
		if ( ! bounds?.w || ! rotation ) return { x, y };

		const cx  = bounds.x + bounds.w / 2;
		const cy  = bounds.y + bounds.h / 2;
		const rad = rotation * Math.PI / 180;
		const dx  = x - cx;
		const dy  = y - cy;

		x = cx + dx * Math.cos( rad ) - dy * Math.sin( rad );
		y = cy + dx * Math.sin( rad ) + dy * Math.cos( rad );
		return { x, y };
	}

	extractSpotifyUri( inputValue ) {
		const raw = String( inputValue || '' ).trim();
		if ( ! raw ) return '';
		if ( /^spotify:[a-z]+:[A-Za-z0-9]+$/i.test( raw ) ) return raw;

		let parsed;
		try {
			parsed = new URL( raw );
		} catch ( e ) {
			return '';
		}

		const host = parsed.hostname.toLowerCase();
		if ( host !== 'open.spotify.com' && host !== 'play.spotify.com' ) return '';

		const parts = parsed.pathname
			.split( '/' )
			.filter( Boolean )
			.filter( p => ! /^intl-[a-z]{2}$/i.test( p ) );

		if ( ! parts.length ) return '';

		const validTypes = [ 'track', 'album', 'artist', 'playlist', 'episode', 'show' ];
		const typeIndex  = parts.findIndex( p => validTypes.includes( p ) );
		if ( typeIndex < 0 || ! parts[ typeIndex + 1 ] ) return '';

		const type = parts[ typeIndex ];
		const id   = parts[ typeIndex + 1 ];
		if ( ! /^[A-Za-z0-9]+$/.test( id ) ) return '';

		return `spotify:${ type }:${ id }`;
	}

	engravingPalette() {
		return { text: '#dadad6', bg: 'ECEFF1', highlight: 'rgba(255,255,255,0.42)', brightness: -0.28, contrast: 0.18, opacity: 0.9 };
	}

	embroideryPattern( color, fontSize = 24 ) {
		const source = document.createElement( 'canvas' );
		const size = Math.max( 10, Math.min( 20, Math.round( fontSize * 0.18 ) ) );
		source.width = size;
		source.height = size;

		const ctx = source.getContext( '2d' );
		if ( ! ctx ) return color;

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

		const base = ctx.createLinearGradient( 0, 0, source.width, source.height );
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
			ctx.strokeStyle = stitchIndex % 2
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
		for ( let i = -source.height; i < source.width * 2; i += Math.max( 3.2, size * 0.32 ) ) {
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
		if ( ! rgb ) return 'rgba(0,0,0,0.16)';

		return `rgba(${ Math.max( 0, rgb.r - 36 ) },${ Math.max( 0, rgb.g - 36 ) },${ Math.max( 0, rgb.b - 36 ) },0.24)`;
	}

	embroideryHighlightColor( color ) {
		const rgb = this.hexToRgb( color );
		if ( ! rgb ) return 'rgba(255,255,255,0.42)';

		return `rgba(${ Math.min( 255, rgb.r + 88 ) },${ Math.min( 255, rgb.g + 88 ) },${ Math.min( 255, rgb.b + 88 ) },0.62)`;
	}

	embroideryShadowColor( color ) {
		const rgb = this.hexToRgb( color );
		if ( ! rgb ) return 'rgba(0,0,0,0.42)';

		return `rgba(${ Math.max( 0, rgb.r - 96 ) },${ Math.max( 0, rgb.g - 96 ) },${ Math.max( 0, rgb.b - 96 ) },0.72)`;
	}

	hexToRgb( color ) {
		const value = String( color || '' ).trim();
		const match = value.match( /^#([0-9a-f]{3}|[0-9a-f]{6})$/i );
		if ( ! match ) return null;

		const hex = match[1].length === 3
			? match[1].split( '' ).map( char => char + char ).join( '' )
			: match[1];

		return {
			r: parseInt( hex.slice( 0, 2 ), 16 ),
			g: parseInt( hex.slice( 2, 4 ), 16 ),
			b: parseInt( hex.slice( 4, 6 ), 16 ),
		};
	}

	buildSpotifyCodeUrl( inputValue, isEngraving, engravingPalette = null ) {
		const spotifyUri = this.extractSpotifyUri( inputValue );
		if ( ! spotifyUri ) return '';

		// Official Spotify scannable-code endpoint.
		// Endpoint shape:
		// /uri/plain/{format}/{background-hex}/{bar-colour}/{size}/{spotify-uri}
		// We request SVG and then strip white in-canvas for transparent compositing.
		const format = 'svg';
		const bgHex  = isEngraving ? ( engravingPalette?.bg || 'F5F2EF' ) : 'FFFFFF';
		const bar    = isEngraving ? 'black' : 'black';
		const size   = 640;

		return `https://scannables.scdn.co/uri/plain/${ format }/${ bgHex }/${ bar }/${ size }/${ spotifyUri }`;
	}

	printAreaClipPath( bounds, scale ) {
		if ( ! bounds || ! bounds.w || ! bounds.h ) return null;

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

	layerClipPath( x, y, w, h, angle = 0, settings = {} ) {
		if ( ! w || ! h ) return null;

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

	async renderFabricImg( canvas, url, x, y, w, h, isEngraving = false, crossOrigin = 'anonymous', makeWhiteTransparent = false, angle = 0, engravingPalette = null, clipPath = null, fit = 'contain', tintColor = '' ) {
		try {
			const imgLoadOpts = crossOrigin ? { crossOrigin } : {};
			const img = await FabricImage.fromURL( url, imgLoadOpts );
			if ( ! img || ! img.width ) {
				console.warn( '[OC] Image failed to load or has zero dimensions:', url );
				return false;
			}
			const s = fit === 'cover' ? Math.max( w / img.width, h / img.height ) : Math.min( w / img.width, h / img.height );
			img.set( { left: x + w / 2, top: y + h / 2, originX: 'center', originY: 'center',
				scaleX: s, scaleY: s, angle, selectable: false, evented: false } );

			const filters = [];
			if ( makeWhiteTransparent ) {
				filters.push(
					new FabricFilters.RemoveColor( {
						color: '#FFFFFF',
						distance: 0.1,
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
			if ( isEngraving ) {
				const palette = engravingPalette || this.engravingPalette();
				filters.push(
					new FabricFilters.Grayscale(),
					new FabricFilters.Brightness( { brightness: palette.brightness } ),
					new FabricFilters.Contrast( { contrast: palette.contrast } )
				);
			}
			if ( filters.length ) {
				img.filters = filters;
				img.applyFilters();
			}
			if ( isEngraving ) {
				const palette = engravingPalette || this.engravingPalette();
				img.set( { opacity: palette.opacity } );
			}

			img._ocContent = true;
			this.applyContentClip( img, clipPath );
			canvas.add( img );
			return true;
		} catch ( e ) {
			console.warn( '[OC] renderFabricImg error:', e, 'URL:', url );
			return false;
		}
	}

	async loadFont( font ) {
		if ( ! font?.name || ! font?.url ) return;
		if ( this.fontCache[ font.name ] ) return this.fontCache[ font.name ];
		const ff = new FontFace( font.name, `url('${ font.url }')`, {
			weight: font.weight || 'normal', style: font.style || 'normal',
		} );
		this.fontCache[ font.name ] = ff.load().then( f => document.fonts.add( f ) ).catch( err => {
			delete this.fontCache[ font.name ];
			console.warn( '[OC] Font load failed:', err );
			throw err;
		} );
		return this.fontCache[ font.name ];
	}

	// ── Input listeners ─────────────────────────────────────────────────────────

	setupInputListeners() {
		// Area tabs
		const areaTabs = Array.from( document.querySelectorAll( '.oc-area-tab' ) );
		areaTabs.forEach( btn => {
			btn.addEventListener( 'click', () => this.switchArea( parseInt( btn.dataset.areaIndex, 10 ) ) );
			btn.addEventListener( 'touchend', ( e ) => {
				e.preventDefault();
				this.switchArea( parseInt( btn.dataset.areaIndex, 10 ) );
			}, { passive: false } );
			btn.addEventListener( 'keydown', ( e ) => {
				if ( ! [ 'ArrowLeft', 'ArrowRight', 'Home', 'End' ].includes( e.key ) ) return;
				e.preventDefault();
				const currentIndex = areaTabs.indexOf( btn );
				let nextIndex = currentIndex;
				if ( e.key === 'ArrowLeft' ) nextIndex = Math.max( 0, currentIndex - 1 );
				if ( e.key === 'ArrowRight' ) nextIndex = Math.min( areaTabs.length - 1, currentIndex + 1 );
				if ( e.key === 'Home' ) nextIndex = 0;
				if ( e.key === 'End' ) nextIndex = areaTabs.length - 1;
				areaTabs[ nextIndex ]?.focus();
				this.switchArea( parseInt( areaTabs[ nextIndex ]?.dataset.areaIndex || '0', 10 ) );
			} );
		} );

		// Text / textarea
		document.querySelectorAll( '[data-oc-layer-text]' ).forEach( el => {
			const lid = parseInt( el.dataset.ocLayerText, 10 );
			const counter = el.parentElement?.querySelector( `.oc-char-counter[data-oc-char-counter="${ lid }"]` );
			const limit = parseInt( counter?.dataset.charLimit, 10 ) || this.charLimitForLayer( lid );
			if ( limit > 0 ) el.maxLength = limit;
			const updateCounter = () => {
				if ( ! counter ) return;
				const current = this.textLength( el.value );
				if ( limit === 0 || current <= limit ) {
					counter.style.display = 'none';
					return;
				}
				counter.textContent = `${ current } / ${ limit }`;
				counter.style.display = '';
			};
			updateCounter();
			el.addEventListener( 'input', () => {
				if ( limit > 0 ) {
					const clipped = this.truncateText( el.value, limit );
					if ( clipped !== el.value ) el.value = clipped;
				}
				if ( ! this.inputs[ lid ] ) this.inputs[ lid ] = {};
				this.inputs[ lid ].value = limit > 0 ? this.truncateText( el.value, limit ) : el.value;
				this.syncLinkedLayerInput( lid, [ 'value' ] );
				updateCounter();
				this.requestPreviewFocus();
				this.scheduleRedraw();
				this.updateHiddenField();
			} );
		} );

		// Spotify validation (invalid format / private playlist / unavailable).
		document.querySelectorAll( '[data-oc-layer-spotify]' ).forEach( el => {
			const lid = parseInt( el.dataset.ocLayerSpotify, 10 );
			if ( ! lid ) return;

			el.addEventListener( 'input', () => {
				if ( ! this.inputs[ lid ] ) this.inputs[ lid ] = {};
				this.inputs[ lid ].value = el.value;
				this.inputs[ lid ].spotifyStatus = '';
				this.inputs[ lid ].spotifyUri = '';
				this.syncLinkedLayerInput( lid, [ 'value', 'spotifyStatus', 'spotifyUri' ] );
				this.setSpotifyError( lid, '', el );
				this.requestPreviewFocus();
				this.scheduleRedraw();
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
			document.querySelectorAll( '.oc-help-tooltip.oc-open, .oc-spotify-help.oc-open' ).forEach( help => {
				help.classList.remove( 'oc-open' );
				help.querySelector( '.oc-help-toggle, .oc-spotify-help-toggle' )?.setAttribute( 'aria-expanded', 'false' );
			} );
		};
		document.querySelectorAll( '.oc-help-toggle:not(.oc-spotify-modal-trigger), .oc-spotify-help-toggle' ).forEach( btn => {
			btn.addEventListener( 'click', e => {
				e.preventDefault();
				e.stopPropagation();
				const help = btn.closest( '.oc-help-tooltip, .oc-spotify-help' );
				if ( ! help ) return;
				const willOpen = ! help.classList.contains( 'oc-open' );
				closeHelpTooltips();
				if ( willOpen ) {
					help.classList.add( 'oc-open' );
					btn.setAttribute( 'aria-expanded', 'true' );
				}
			} );
		} );
		document.addEventListener( 'click', e => {
			if ( ! e.target.closest( '.oc-help-tooltip, .oc-spotify-help' ) ) closeHelpTooltips();
		} );

		this.setupSpotifyModal();

		// Font selects — also reflect the picked font in the closed select.
		const reflectFontOnSelect = ( el ) => {
			const opt = el.options[ el.selectedIndex ];
			const fam = opt?.style?.fontFamily || '';
			if ( fam ) el.style.fontFamily = fam;
		};
		document.querySelectorAll( '[data-oc-layer-font]' ).forEach( el => {
			reflectFontOnSelect( el );
			const lid = parseInt( el.dataset.ocLayerFont, 10 );
			el.addEventListener( 'change', async () => {
				if ( ! this.inputs[ lid ] ) this.inputs[ lid ] = {};
				this.inputs[ lid ].fontId = parseInt( el.value, 10 );
				const font = this.fonts.find( f => f.id === this.inputs[ lid ].fontId );
				if ( font ) {
					try {
						await this.loadFont( font );
					} catch ( err ) {
						console.warn( '[OC] Font load failed:', err );
					}
				}
				reflectFontOnSelect( el );
				const preview = document.querySelector( `.oc-font-preview[data-oc-font-preview="${ lid }"]` );
				if ( preview && font ) preview.style.fontFamily = font.name;
				this.requestPreviewFocus();
				this.scheduleRedraw();
				this.updateHiddenField();
			} );
		} );

		// Font size
		document.querySelectorAll( '[data-oc-layer-font-size]' ).forEach( el => {
			const lid = parseInt( el.dataset.ocLayerFontSize, 10 );
			const valueEl = document.querySelector( `.oc-range-value[data-oc-range-value="${ lid }"]` );
			const updateValue = () => {
				if ( valueEl ) valueEl.textContent = el.value;
			};
			updateValue();
			el.addEventListener( 'input', () => {
				if ( ! this.inputs[ lid ] ) this.inputs[ lid ] = {};
				this.inputs[ lid ].fontSize = Math.max( 1, parseInt( el.value, 10 ) || 1 );
				updateValue();
				this.requestPreviewFocus();
				this.scheduleRedraw();
				this.updateHiddenField();
			} );
		} );

		// Colour swatches
		document.querySelectorAll( '[data-oc-layer-swatch]' ).forEach( btn => {
			btn.addEventListener( 'click', () => {
				const lid = parseInt( btn.dataset.ocLayerSwatch, 10 );
				if ( ! this.inputs[ lid ] ) this.inputs[ lid ] = {};
				this.inputs[ lid ].colorHex = btn.dataset.hex;
				if ( this.getLayerById( lid )?.type === 'lineart' ) this.syncLinkedLayerInput( lid, [ 'colorHex' ] );
				btn.closest( '.oc-colour-swatches' )?.querySelectorAll( '.oc-colour-swatch' )
					.forEach( s => {
						const isSelected = s === btn;
						s.classList.toggle( 'oc-selected', isSelected );
						s.setAttribute( 'aria-pressed', isSelected ? 'true' : 'false' );
					} );
				this.requestPreviewFocus();
				this.scheduleRedraw();
				this.updateHiddenField();
			} );
		} );

		// Free colour picker
		document.querySelectorAll( '[data-oc-layer-color]' ).forEach( el => {
			const lid = parseInt( el.dataset.ocLayerColor, 10 );
			el.addEventListener( 'input', () => {
				if ( ! this.inputs[ lid ] ) this.inputs[ lid ] = {};
				this.inputs[ lid ].colorHex = el.value;
				if ( this.getLayerById( lid )?.type === 'lineart' ) this.syncLinkedLayerInput( lid, [ 'colorHex' ] );
				this.requestPreviewFocus();
				this.scheduleRedraw();
				this.updateHiddenField();
			} );
		} );

		// Clipart items
		document.querySelectorAll( '[data-oc-layer-clipart]' ).forEach( btn => {
			btn.addEventListener( 'click', () => {
				const lid = parseInt( btn.dataset.ocLayerClipart, 10 );
				if ( ! this.inputs[ lid ] ) this.inputs[ lid ] = {};
				this.inputs[ lid ].clipartId  = parseInt( btn.dataset.ocClipart, 10 );
				this.inputs[ lid ].clipartUrl = btn.dataset.ocClipartUrl;
				this.inputs[ lid ].clipartRecolourable = btn.dataset.ocClipartRecolourable === '1';
				this.syncLinkedLayerInput( lid, [ 'clipartId', 'clipartUrl', 'clipartRecolourable' ] );
				btn.closest( '.oc-clipart-grid' )?.querySelectorAll( '.oc-clipart-item' )
					.forEach( i => {
						const isSelected = i === btn;
						i.classList.toggle( 'oc-selected', isSelected );
						i.setAttribute( 'aria-pressed', isSelected ? 'true' : 'false' );
					} );
				this.requestPreviewFocus();
				this.scheduleRedraw();
				this.updateHiddenField();
			} );
		} );

		// Clipart search (debounced 200ms)
		document.querySelectorAll( '[data-oc-clipart-search]' ).forEach( input => {
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
		document.querySelectorAll( '[data-oc-clipart-category]' ).forEach( select => {
			const lid = parseInt( select.dataset.ocClipartCategory, 10 );
			this.clipartCategoryFilters[ lid ] = '';
			select.addEventListener( 'change', () => {
				this.clipartCategoryFilters[ lid ] = select.value;
				this.filterClipart( lid );
			} );
		} );

		// Dismiss resolution warning
		document.querySelectorAll( '.oc-resolution-warning' ).forEach( warnEl => {
			warnEl.addEventListener( 'click', e => {
				if ( e.target === warnEl && warnEl.classList.contains( 'oc-res-warning' ) ) {
					warnEl.style.display = 'none';
				}
			} );
		} );
	}

	setupDesignVariantOptions() {
		if ( ! this.designVariants.length ) return;

		document.querySelectorAll( '[data-oc-design-variant]' ).forEach( btn => {
			btn.addEventListener( 'click', async () => {
				const variant = this.designVariants.find( item => item.id === btn.dataset.ocDesignVariant );
				if ( ! variant || variant.id === this.selectedDesignVariant ) return;

				this.selectedDesignVariant = variant.id;
				document.querySelectorAll( '[data-oc-design-variant]' ).forEach( option => {
					const isSelected = option === btn;
					option.classList.toggle( 'oc-selected', isSelected );
					option.setAttribute( 'aria-pressed', isSelected ? 'true' : 'false' );
				} );

				if ( this.areas[ 0 ] ) {
					this.areas[ 0 ].mockupUrl = variant.mockupUrl;
					this.areas[ 0 ].mockupW   = variant.mockupW;
					this.areas[ 0 ].mockupH   = variant.mockupH;
					const previewImg = document.getElementById( 'oc-canvas-preview' );
					if ( previewImg ) {
						previewImg.src = variant.mockupUrl;
						previewImg.srcset = '';
					}
					this.requestPreviewFocus();
					await this.rebuildCanvas( 0 );
				}

				this.updateHiddenField();
			} );
		} );
	}

	setupSpotifyModal() {
		const dialog = document.getElementById( 'oc-spotify-share-dialog' );
		if ( ! dialog ) return;

		document.querySelectorAll( '.oc-spotify-modal-trigger' ).forEach( trigger => {
			trigger.addEventListener( 'click', event => {
				event.preventDefault();
				event.stopPropagation();
				this.openSpotifyModal();
			} );
		} );

		dialog.querySelectorAll( '[data-oc-spotify-modal-close]' ).forEach( closeBtn => {
			closeBtn.addEventListener( 'click', () => this.closeSpotifyModal() );
		} );

		dialog.addEventListener( 'click', event => {
			const rect = dialog.getBoundingClientRect();
			const inDialog = rect.top <= event.clientY && event.clientY <= rect.top + rect.height &&
				rect.left <= event.clientX && event.clientX <= rect.left + rect.width;

			if ( ! inDialog ) this.closeSpotifyModal();
		} );

		dialog.addEventListener( 'close', () => {
			dialog.classList.remove( 'is-visible' );
			document.body.style.overflow = '';
		} );
	}

	openSpotifyModal() {
		const dialog = document.getElementById( 'oc-spotify-share-dialog' );
		if ( ! dialog || dialog.open ) return;

		clearTimeout( this.spotifyModalCloseTimer );
		dialog.showModal();
		requestAnimationFrame( () => {
			requestAnimationFrame( () => {
				dialog.classList.add( 'is-visible' );
			} );
		} );
		document.body.style.overflow = 'hidden';
	}

	closeSpotifyModal() {
		const dialog = document.getElementById( 'oc-spotify-share-dialog' );
		if ( ! dialog || ! dialog.open ) return;

		dialog.classList.remove( 'is-visible' );
		clearTimeout( this.spotifyModalCloseTimer );
		this.spotifyModalCloseTimer = setTimeout( () => {
			if ( dialog.open ) dialog.close();
			document.body.style.overflow = '';
		}, 300 );
	}

	filterClipart( layerId ) {
		const grid = document.querySelector( `.oc-clipart-grid[data-oc-clipart-grid="${ layerId }"]` )
			|| document.querySelector( `[data-oc-clipart-search="${ layerId }"]` )?.closest( '.oc-layer-body' )?.querySelector( '.oc-clipart-grid' );
		if ( ! grid ) return;

		const items = grid.querySelectorAll( '.oc-clipart-item' );
		const term = ( this.clipartSearchTerms[ layerId ] || '' ).toLowerCase().trim();
		const category = this.clipartCategoryFilters[ layerId ] || '';
		let visibleCount = 0;

		items.forEach( btn => {
			const name = ( btn.title || '' ).toLowerCase();
			const groups = btn.dataset.ocClipartGroups ? btn.dataset.ocClipartGroups.split( '||' ).filter( Boolean ) : [];
			const matchesSearch = !term || name.includes( term );
			const matchesCategory = !category || groups.includes( category );
			const visible = matchesSearch && matchesCategory;
			btn.style.display = visible ? '' : 'none';
			if ( visible ) visibleCount++;
		} );

		let noResults = grid.querySelector( '.oc-clipart-no-results' );
		if ( visibleCount === 0 ) {
			if ( ! noResults ) {
				noResults = document.createElement( 'p' );
				noResults.className = 'oc-clipart-no-results';
				noResults.textContent = 'No clipart matches your search.';
				grid.appendChild( noResults );
			}
			noResults.style.display = '';
		} else if ( noResults ) {
			noResults.style.display = 'none';
		}
	}

	setSpotifyError( layerId, message, inputEl = null ) {
		const msg = String( message || '' );
		const el = document.querySelector( `[data-oc-spotify-error="${ layerId }"]` );
		if ( el ) {
			el.textContent = msg;
			el.style.display = msg ? '' : 'none';
		}
		if ( inputEl ) {
			inputEl.setCustomValidity( msg );
			inputEl.setAttribute( 'aria-invalid', msg ? 'true' : 'false' );
		}
	}

	getLayerById( layerId ) {
		return this.layersById[ layerId ] || null;
	}

	charLimitForLayer( layerId ) {
		return Math.max( 0, parseInt( this.getLayerById( layerId )?.settings?.char_limit, 10 ) || 0 );
	}

	textLength( value ) {
		return Array.from( String( value || '' ) ).length;
	}

	truncateText( value, limit ) {
		const text = String( value || '' );
		return limit > 0 && this.textLength( text ) > limit ? Array.from( text ).slice( 0, limit ).join( '' ) : text;
	}

	clampLayerInputValue( layerId ) {
		const limit = this.charLimitForLayer( layerId );
		if ( limit > 0 && this.inputs[ layerId ]?.value !== undefined ) {
			this.inputs[ layerId ].value = this.truncateText( this.inputs[ layerId ].value, limit );
		}
	}

	linkedLayerIds( sourceLayerId ) {
		const source = this.getLayerById( sourceLayerId );
		const group = String( source?.settings?.link_group || '' ).trim();
		if ( ! source || ! group ) return [];

		const ids = [];
		this.areas.forEach( area => {
			( area.layers || [] ).forEach( layer => {
				if ( layer.id === sourceLayerId || layer.type !== source.type ) return;
				if ( String( layer.settings?.link_group || '' ).trim() === group ) ids.push( layer.id );
			} );
		} );
		return ids;
	}

	syncLinkedLayerInput( sourceLayerId, keys ) {
		const sourceInput = this.inputs[ sourceLayerId ];
		if ( ! sourceInput ) return;

		this.linkedLayerIds( sourceLayerId ).forEach( layerId => {
			if ( ! this.inputs[ layerId ] ) this.inputs[ layerId ] = {};
			keys.forEach( key => {
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
			document.querySelectorAll( `[data-oc-layer-text="${ layerId }"], [data-oc-layer-spotify="${ layerId }"]` ).forEach( el => {
				el.value = input.value || '';
			} );
			const counter = document.querySelector( `.oc-char-counter[data-oc-char-counter="${ layerId }"]` );
			if ( counter ) {
				const limit = parseInt( counter.dataset.charLimit, 10 ) || this.charLimitForLayer( layerId );
				const current = this.textLength( input.value || '' );
				counter.textContent = `${ current } / ${ limit }`;
				counter.style.display = limit > 0 && current > limit ? '' : 'none';
			}
		}
		if ( keys.includes( 'colorHex' ) ) {
			document.querySelectorAll( `[data-oc-layer-swatch="${ layerId }"]` ).forEach( swatch => {
				const isSelected = swatch.dataset.hex === input.colorHex;
				swatch.classList.toggle( 'oc-selected', isSelected );
				swatch.setAttribute( 'aria-pressed', isSelected ? 'true' : 'false' );
			} );
			const colorEl = document.querySelector( `[data-oc-layer-color="${ layerId }"]` );
			if ( colorEl && input.colorHex ) colorEl.value = input.colorHex;
		}
		if ( keys.includes( 'clipartId' ) ) {
			document.querySelectorAll( `[data-oc-layer-clipart="${ layerId }"]` ).forEach( item => {
				const isSelected = Number( item.dataset.ocClipart ) === Number( input.clipartId );
				item.classList.toggle( 'oc-selected', isSelected );
				item.setAttribute( 'aria-pressed', isSelected ? 'true' : 'false' );
			} );
		}
		if ( keys.includes( 'attachmentId' ) || keys.includes( 'attachmentUrl' ) ) {
			document.querySelectorAll( `[data-oc-upload-zone="${ layerId }"]` ).forEach( zone => {
				this.setUploadZoneState( zone, input.attachmentUrl ? 'uploaded' : '' );
			} );
		}
	}

	getLayerInputEl( layer ) {
		if ( ! layer?.id ) return null;
		switch ( layer.type ) {
			case 'text':
			case 'textarea':
				return document.querySelector( `[data-oc-layer-text="${ layer.id }"]` );
			case 'spotify':
				return document.querySelector( `[data-oc-layer-spotify="${ layer.id }"]` );
			case 'image':
			case 'clipmask':
				return document.querySelector( `[data-oc-upload-zone="${ layer.id }"]` );
			case 'clipart':
				return document.querySelector( `[data-oc-layer-clipart="${ layer.id }"]` );
			default:
				return null;
		}
	}

	clearPreflightMessages() {
		if ( this.preflightRoot ) {
			this.preflightRoot.innerHTML = '';
			this.preflightRoot.hidden = true;
		}

		document.querySelectorAll( '.oc-preflight-field-error' ).forEach( el => {
			el.classList.remove( 'oc-preflight-field-error' );
		} );

		document.querySelectorAll( '[data-oc-layer-text], [data-oc-layer-spotify]' ).forEach( el => {
			el.setCustomValidity( '' );
			el.setAttribute( 'aria-invalid', 'false' );
		} );
	}

	renderPreflightMessages( errors = [], warnings = [] ) {
		if ( ! this.preflightRoot ) return;

		if ( ! errors.length && ! warnings.length ) {
			this.clearPreflightMessages();
			return;
		}

		const asList = ( items, cls ) => {
			return items.length
				? `<ul class="${ cls }">${ items.map( msg => `<li>${ msg }</li>` ).join( '' ) }</ul>`
				: '';
		};

		this.preflightRoot.innerHTML =
			'<div class="oc-preflight-box" role="alert" aria-live="assertive">' +
				( errors.length ? '<p class="oc-preflight-title">Please fix these issues before checkout:</p>' : '' ) +
				asList( errors, 'oc-preflight-errors' ) +
				( warnings.length ? '<p class="oc-preflight-title">Quality warnings:</p>' : '' ) +
				asList( warnings, 'oc-preflight-warnings' ) +
			'</div>';

		this.preflightRoot.hidden = false;
		this.preflightRoot.scrollIntoView( { behavior: 'smooth', block: 'start' } );
	}

	async getImageMeta( url ) {
		if ( ! url ) return null;

		return new Promise( resolve => {
			const img = new Image();
			img.onload = () => resolve( { width: img.naturalWidth || 0, height: img.naturalHeight || 0 } );
			img.onerror = () => resolve( null );
			img.src = url;
		} );
	}

	async runPreflight() {
		this.clearPreflightMessages();

		const errors = [];
		const warnings = [];
		const spotifyValidated = new Set();
		const invalidSpotifyStatuses = [ 'invalid_format', 'playlist_private_or_invalid', 'invalid_or_unavailable', 'unreachable', 'rate_limited' ];

		for ( const area of this.areas ) {
			for ( const layer of ( area.layers || [] ) ) {
				if ( layer.locked ) continue; // Locked layers skip preflight validation
				const input    = this.inputs[ layer.id ] || {};
				const settings = layer.settings || {};
				const required = Boolean( settings.required );
				const label    = `${ area.label || 'Area' }: ${ layer.label || layer.type }`;
				const fieldEl  = this.getLayerInputEl( layer );
				let value      = '';

				switch ( layer.type ) {
					case 'text':
					case 'textarea':
						value = String( input.value || '' ).trim();
						if ( required && ! value ) {
							errors.push( `${ label } is required.` );
							fieldEl?.classList.add( 'oc-preflight-field-error' );
							if ( fieldEl ) {
								fieldEl.setCustomValidity( 'This field is required.' );
								fieldEl.setAttribute( 'aria-invalid', 'true' );
							}
						}
						if ( value ) {
							const charLimit = parseInt( settings.char_limit, 10 ) || 0;
							if ( charLimit > 0 && this.textLength( value ) > charLimit ) {
								errors.push( `${ label } exceeds the ${ charLimit } character limit.` );
								fieldEl?.classList.add( 'oc-preflight-field-error' );
								if ( fieldEl ) {
									fieldEl.setCustomValidity( `Maximum ${ charLimit } characters.` );
									fieldEl.setAttribute( 'aria-invalid', 'true' );
								}
							}
						}
						break;

					case 'image':
					case 'clipmask':
						if ( required && ! input.attachmentId ) {
							errors.push( `${ label } needs an uploaded image.` );
							fieldEl?.classList.add( 'oc-preflight-field-error' );
						}
						if ( input.attachmentUrl ) {
							let imageMeta = input.imageMeta || null;
							if ( ! imageMeta ) {
								imageMeta = await this.getImageMeta( input.attachmentUrl );
								if ( imageMeta && this.inputs[ layer.id ] ) {
									this.inputs[ layer.id ].imageMeta = imageMeta;
								}
							}
							if ( imageMeta && imageMeta.width > 0 && imageMeta.height > 0 ) {
								if ( imageMeta.width < layer.w || imageMeta.height < layer.h ) {
									warnings.push( `${ label } may print soft (${ imageMeta.width }x${ imageMeta.height }px for a ${ layer.w }x${ layer.h }px print area).` );
								}
							}
						}
						break;

					case 'clipart':
						if ( required && ! input.clipartId ) {
							errors.push( `${ label } requires a clipart selection.` );
							fieldEl?.classList.add( 'oc-preflight-field-error' );
						}
						break;

					case 'lineart':
						value = String( input.colorHex || '' ).trim();
						if ( required && ! value ) {
							errors.push( `${ label } requires a line-art colour.` );
							fieldEl?.classList.add( 'oc-preflight-field-error' );
							if ( fieldEl ) {
								fieldEl.setCustomValidity( 'Please choose a line-art colour.' );
								fieldEl.setAttribute( 'aria-invalid', 'true' );
							}
						}
						break;

					case 'spotify':
						value = String( input.value || '' ).trim();
						if ( required && ! value ) {
							errors.push( `${ label } requires a Spotify link.` );
							fieldEl?.classList.add( 'oc-preflight-field-error' );
							if ( fieldEl ) {
								fieldEl.setCustomValidity( 'Please provide a Spotify link.' );
								fieldEl.setAttribute( 'aria-invalid', 'true' );
							}
							break;
						}

						if ( value && ! spotifyValidated.has( layer.id ) ) {
							await this.validateSpotifyLayer( layer.id, value, fieldEl );
							spotifyValidated.add( layer.id );
						}

						if ( value ) {
							const status = String( this.inputs[ layer.id ]?.spotifyStatus || '' );
							if ( invalidSpotifyStatuses.includes( status ) ) {
								errors.push( `${ label } has an invalid or unavailable Spotify link.` );
								fieldEl?.classList.add( 'oc-preflight-field-error' );
								if ( fieldEl ) {
									fieldEl.setCustomValidity( 'Spotify link is invalid or unavailable.' );
									fieldEl.setAttribute( 'aria-invalid', 'true' );
								}
							}
						}
						break;
				}
			}
		}

		return { errors, warnings, ok: errors.length === 0 };
	}

	async validateSpotifyLayer( layerId, rawValue, inputEl = null ) {
		const value = String( rawValue || '' ).trim();
		if ( ! this.inputs[ layerId ] ) this.inputs[ layerId ] = {};
		const token = ( this.spotifyValidateTokens[ layerId ] || 0 ) + 1;
		this.spotifyValidateTokens[ layerId ] = token;

		if ( ! value ) {
			this.inputs[ layerId ].spotifyStatus = '';
			this.inputs[ layerId ].spotifyUri = '';
			this.syncLinkedLayerInput( layerId, [ 'value', 'spotifyStatus', 'spotifyUri' ] );
			this.setSpotifyError( layerId, '', inputEl );
			this.scheduleRedraw();
			this.updateHiddenField();
			return;
		}

		const localUri = this.extractSpotifyUri( value );
		if ( ! localUri ) {
			this.inputs[ layerId ].spotifyStatus = 'invalid_format';
			this.inputs[ layerId ].spotifyUri = '';
			this.syncLinkedLayerInput( layerId, [ 'value', 'spotifyStatus', 'spotifyUri' ] );
			this.setSpotifyError( layerId, 'Invalid Spotify link format.', inputEl );
			this.scheduleRedraw();
			this.updateHiddenField();
			return;
		}

		if ( ! this.data.validateSpotifyUrl ) {
			this.inputs[ layerId ].spotifyStatus = 'ok';
			this.inputs[ layerId ].spotifyUri = localUri;
			this.syncLinkedLayerInput( layerId, [ 'value', 'spotifyStatus', 'spotifyUri' ] );
			this.setSpotifyError( layerId, '', inputEl );
			this.scheduleRedraw();
			this.updateHiddenField();
			return;
		}

		try {
			const res = await fetch( this.data.validateSpotifyUrl, {
				method: 'POST',
				headers: this.restHeaders( { 'Content-Type': 'application/json' } ),
				body: JSON.stringify( { url: value } ),
			} );
			const isJson = res.headers.get( 'content-type' )?.includes( 'application/json' );
			let json = null;
			let text = '';
			if ( isJson ) {
				try {
					json = await res.json();
				} catch ( err ) {
					console.warn( '[OC] Spotify validation JSON parse failed:', err );
				}
			} else {
				text = await res.text();
			}
			if ( this.spotifyValidateTokens[ layerId ] !== token ) return;
			if ( ! res.ok ) {
				const statusReason = json?.code === 'rate_limited' || res.status === 429 ? 'rate_limited' : 'unreachable';
				const statusMessage = json?.message || text || 'Could not validate Spotify right now. Please try again.';
				this.inputs[ layerId ].spotifyStatus = statusReason;
				this.inputs[ layerId ].spotifyUri = '';
				this.syncLinkedLayerInput( layerId, [ 'value', 'spotifyStatus', 'spotifyUri' ] );
				this.setSpotifyError( layerId, statusMessage, inputEl );
				this.scheduleRedraw();
				this.updateHiddenField();
				return;
			}

			if ( ! json ) {
				this.inputs[ layerId ].spotifyStatus = 'unreachable';
				this.inputs[ layerId ].spotifyUri = '';
				this.syncLinkedLayerInput( layerId, [ 'value', 'spotifyStatus', 'spotifyUri' ] );
				this.setSpotifyError( layerId, 'Could not validate Spotify right now. Please try again.', inputEl );
				this.scheduleRedraw();
				this.updateHiddenField();
				return;
			}

			const valid = Boolean( json?.valid );

			if ( valid ) {
				this.inputs[ layerId ].spotifyStatus = 'ok';
				this.inputs[ layerId ].spotifyUri = json.spotifyUri || localUri;
				this.setSpotifyError( layerId, '', inputEl );
			} else {
				this.inputs[ layerId ].spotifyStatus = json?.reason || 'invalid_or_unavailable';
				this.inputs[ layerId ].spotifyUri = '';
				this.setSpotifyError( layerId, json?.message || 'Spotify link is invalid or unavailable.', inputEl );
			}
		} catch ( e ) {
			if ( this.spotifyValidateTokens[ layerId ] !== token ) return;
			this.inputs[ layerId ].spotifyStatus = 'unreachable';
			this.inputs[ layerId ].spotifyUri = '';
			this.setSpotifyError( layerId, 'Could not validate Spotify right now. Please try again.', inputEl );
		}

		this.syncLinkedLayerInput( layerId, [ 'value', 'spotifyStatus', 'spotifyUri' ] );
		this.scheduleRedraw();
		this.updateHiddenField();
	}

	// ── Form submit — upload preview then proceed ──────────────────────────────

	updateInputsFromDOM() {
		for ( const layerIdStr in this.inputs ) {
			const layerId = parseInt( layerIdStr, 10 );
			const inp = this.inputs[ layerId ];
			if ( ! inp ) continue;

			const textEl = document.querySelector( `[data-oc-layer-text="${ layerId }"]` );
			if ( textEl && inp.value !== undefined ) {
				this.clampLayerInputValue( layerId );
				textEl.value = inp.value;
			}

			const fontEl = document.querySelector( `[data-oc-layer-font="${ layerId }"]` );
			if ( fontEl && inp.fontId ) {
				fontEl.value = inp.fontId;
			}

			const swatch = document.querySelector( `[data-oc-layer-swatch="${ layerId }"][data-hex="${ inp.colorHex }"]` );
			if ( swatch ) {
				swatch.closest( '.oc-colour-swatches' )?.querySelectorAll( '.oc-colour-swatch' )
					.forEach( s => s.classList.toggle( 'oc-selected', s === swatch ) );
			}

			const colorEl = document.querySelector( `[data-oc-layer-color="${ layerId }"]` );
			if ( colorEl && inp.colorHex ) {
				colorEl.value = inp.colorHex;
			}

			const sizeEl = document.querySelector( `[data-oc-layer-font-size="${ layerId }"]` );
			if ( sizeEl && inp.fontSize ) {
				sizeEl.value = inp.fontSize;
				document.querySelector( `.oc-range-value[data-oc-range-value="${ layerId }"]` )?.replaceChildren( document.createTextNode( sizeEl.value ) );
			}

			const clipartBtn = document.querySelector( `[data-oc-layer-clipart="${ layerId }"][data-oc-clipart="${ inp.clipartId }"]` );
			if ( clipartBtn ) {
				clipartBtn.closest( '.oc-clipart-grid' )?.querySelectorAll( '.oc-clipart-item' )
					.forEach( i => i.classList.toggle( 'oc-selected', i === clipartBtn ) );
			}
		}

		this.updateHiddenField();
		this.areas.forEach( ( _, i ) => this.redraw( i ) );
	}

	setupFormSubmit() {
		const form = document.querySelector( 'form.cart' );
		if ( ! form ) return;

		if ( this.editMode ) {
			form.addEventListener( 'submit', async e => {
				e.preventDefault();

				const preflight = await this.runPreflight();
				this.renderPreflightMessages( preflight.errors, preflight.warnings );
				if ( ! preflight.ok ) return;

				if ( preflight.warnings.length ) {
					const proceed = window.confirm(
						'We found quality warnings that may affect print output. Press OK to continue, or Cancel to review.'
					);
					if ( ! proceed ) return;
				}

				await this.uploadPreview();
				this.updateHiddenField();

				const layers = {};
				this.areas.forEach( area => {
					( area.layers || [] ).forEach( layer => {
						const inp = this.inputs[ layer.id ];
						if ( inp ) layers[ layer.id ] = { type: layer.type, ...inp };
					} );
				} );

				try {
					const res = await fetch( this.data.updateCartItemUrl, {
						method:  'POST',
						headers: this.restHeaders( { 'Content-Type': 'application/json' } ),
						body: JSON.stringify( {
							cart_key:   this.cartKey,
							designId:   this.data.designId,
							layers,
							previewUrl: this._previewUrl || '',
						} ),
					} );
					let json = null;
					const isJson = res.headers.get( 'content-type' )?.includes( 'application/json' );
					if ( isJson ) {
						try {
							json = await res.json();
						} catch ( err ) {
							console.warn( '[OC] Cart update response parse failed:', err );
						}
					}

					if ( ! res.ok ) {
						this.renderPreflightMessages( [ json?.message || 'Failed to update customisation.' ], [] );
						return;
					}

					if ( json?.success ) {
						window.location.href = wc_cart_params?.cart_url || '/cart/';
					} else {
						this.renderPreflightMessages( [ json?.message || 'Failed to update customisation.' ], [] );
					}
				} catch ( err ) {
					console.error( '[OC] Update cart item failed:', err );
					this.renderPreflightMessages( [ 'Failed to update customisation. Please try again.' ], [] );
				}
			} );
			return;
		}

		form.addEventListener( 'submit', async ( e ) => {
			if ( form._ocSubmitReady ) {
				return; // preview already saved — let submit through
			}
			e.preventDefault();

			const preflight = await this.runPreflight();
			this.renderPreflightMessages( preflight.errors, preflight.warnings );
			if ( ! preflight.ok ) {
				this.resetCartSubmitState( form );
				return;
			}

			if ( preflight.warnings.length ) {
				const proceed = window.confirm(
					'We found quality warnings that may affect print output. Press OK to continue, or Cancel to review.'
				);
				if ( ! proceed ) {
					this.resetCartSubmitState( form );
					return;
				}
			}

			const acceptedPreview = await this.confirmMobileCartPreview();
			if ( ! acceptedPreview ) {
				this.resetCartSubmitState( form );
				return;
			}

			await this.uploadPreview();
			form._ocSubmitReady = true;
			// requestSubmit() re-triggers HTML5 validation before submitting.
			if ( form.requestSubmit ) {
				const submitter = form.querySelector( '[type="submit"]' ) || undefined;
				form.requestSubmit( submitter );
			} else {
				form.submit();
			}
		} );
	}

	resetCartSubmitState( form ) {
		form.classList.remove( 'loading', 'processing' );
		form.querySelectorAll( '[type="submit"], .single_add_to_cart_button' ).forEach( ( button ) => {
			button.classList.remove( 'loading', 'processing' );
			button.disabled = false;
			button.removeAttribute( 'disabled' );
			button.setAttribute( 'aria-disabled', 'false' );
		} );
	}

	isMobileCartPreviewRequired() {
		return (
			window.matchMedia?.( '(max-width: 639px)' )?.matches ||
			window.innerWidth < 640
		);
	}

	getCurrentPreviewDataUrl() {
		const canvas = this.canvases[ this.activeArea ];
		if ( canvas ) {
			try {
				return canvas.toDataURL( { format: 'jpeg', quality: 0.92 } );
			} catch ( e ) {
				// Fall back to the already-rendered preview image below.
			}
		}

		return document.getElementById( 'oc-canvas-preview' )?.src || '';
	}

	getMobileCartPreviewDialog() {
		if ( this.mobileCartPreviewDialog ) {
			return this.mobileCartPreviewDialog;
		}

		const panel = document.getElementById( 'oc-customiser-panel' ) || document.body;
		const dialog = document.createElement( 'dialog' );
		dialog.id = 'oc-cart-preview-dialog';
		dialog.className = 'oc-cart-preview-dialog';
		dialog.setAttribute( 'aria-labelledby', 'oc-cart-preview-title' );
		dialog.setAttribute( 'aria-describedby', 'oc-cart-preview-desc' );
		dialog.innerHTML =
			'<div class="oc-cart-preview-card">' +
				'<div class="oc-cart-preview-copy">' +
					'<h2 id="oc-cart-preview-title">Check your preview</h2>' +
					'<p id="oc-cart-preview-desc">Please confirm your customisation looks correct before adding this product to your cart.</p>' +
				'</div>' +
				'<div class="oc-cart-preview-image-wrap">' +
					'<img class="oc-cart-preview-image" alt="Customisation preview">' +
				'</div>' +
				'<div class="oc-cart-preview-actions">' +
					'<button type="button" class="oc-cart-preview-change" data-oc-cart-preview-change>Change</button>' +
					'<button type="button" class="oc-cart-preview-accept" data-oc-cart-preview-accept>Accept</button>' +
				'</div>' +
			'</div>';

		panel.appendChild( dialog );
		this.mobileCartPreviewDialog = dialog;
		return dialog;
	}

	confirmMobileCartPreview() {
		if ( ! this.isMobileCartPreviewRequired() ) {
			return Promise.resolve( true );
		}

		const previewUrl = this.getCurrentPreviewDataUrl();
		const dialog = this.getMobileCartPreviewDialog();
		const img = dialog.querySelector( '.oc-cart-preview-image' );
		if ( img && previewUrl ) {
			img.src = previewUrl;
		}

		if ( ! dialog.showModal ) {
			return Promise.resolve( true );
		}

		return new Promise( ( resolve ) => {
			const acceptBtn = dialog.querySelector( '[data-oc-cart-preview-accept]' );
			const changeBtn = dialog.querySelector( '[data-oc-cart-preview-change]' );
			const previousFocus = dialog.ownerDocument.activeElement;

			const finish = ( accepted ) => {
				dialog.classList.remove( 'is-visible' );
				dialog.removeEventListener( 'click', onBackdropClick );
				dialog.removeEventListener( 'cancel', onCancel );
				acceptBtn?.removeEventListener( 'click', onAccept );
				changeBtn?.removeEventListener( 'click', onChange );
				if ( dialog.open ) {
					dialog.close();
				}
				previousFocus?.focus?.();
				resolve( accepted );
			};

			const onAccept = () => finish( true );
			const onChange = () => finish( false );
			const onBackdropClick = ( event ) => {
				if ( event.target === dialog ) {
					finish( false );
				}
			};
			const onCancel = ( event ) => {
				event.preventDefault();
				finish( false );
			};

			acceptBtn?.addEventListener( 'click', onAccept );
			changeBtn?.addEventListener( 'click', onChange );
			dialog.addEventListener( 'click', onBackdropClick );
			dialog.addEventListener( 'cancel', onCancel );

			dialog.showModal();
			window.requestAnimationFrame( () => dialog.classList.add( 'is-visible' ) );
			acceptBtn?.focus?.();
		} );
	}

	async uploadPreview() {
		const canvas = this.canvases[ this.activeArea ];
		if ( ! canvas || ! this.data.savePreviewUrl ) return;

		let dataUrl;
		try {
			dataUrl = canvas.toDataURL( { format: 'jpeg', quality: 0.85 } );
		} catch ( e ) {
			this._previewUrl = '';
			this.updateHiddenField();
			console.warn( '[OC] Could not capture preview for cart:', e.message );
			return;
		}

		try {
			const res  = await fetch( this.data.savePreviewUrl, {
				method:  'POST',
				headers: this.restHeaders( { 'Content-Type': 'application/json' } ),
				body:    JSON.stringify( { image: dataUrl } ),
			} );
			if ( ! res.ok ) {
				this._previewUrl = '';
				this.updateHiddenField();
				return;
			}
			let json = null;
			const isJson = res.headers.get( 'content-type' )?.includes( 'application/json' );
			if ( isJson ) {
				try {
					json = await res.json();
				} catch ( err ) {
					console.warn( '[OC] Preview upload JSON parse failed:', err );
				}
			}
			if ( ! json ) {
				this._previewUrl = '';
				this.updateHiddenField();
				return;
			}
			if ( json.url ) {
				this._previewUrl = json.url;
				this.updateHiddenField(); // embed previewUrl in the cart payload
			}
		} catch ( e ) {
			this._previewUrl = '';
			this.updateHiddenField();
			// Non-fatal — cart submits without a preview image.
			console.warn( '[OC] Preview upload failed:', e.message );
		}
	}

	switchArea( index ) {
		this.activeArea = index;
		document.querySelectorAll( '.oc-area-tab' ).forEach( ( btn, i ) => {
			btn.classList.toggle( 'oc-active', i === index );
			btn.setAttribute( 'aria-selected', i === index ? 'true' : 'false' );
			btn.setAttribute( 'tabindex', i === index ? '0' : '-1' );
		} );
		document.querySelectorAll( '.oc-area-controls' ).forEach( el => {
			el.style.display = parseInt( el.dataset.areaIndex, 10 ) === index ? '' : 'none';
		} );
		this.redraw( index );

		if ( window.innerWidth < 640 ) {
			const activeTab = document.querySelector( `.oc-area-tab[aria-selected="true"]` );
			if ( activeTab ) {
				activeTab.scrollIntoView( { behavior: 'smooth', block: 'nearest', inline: 'center' } );
			}
		}
	}

	// ── Uppy upload zones ────────────────────────────────────────────────────────

	setupUploadZones() {
		document.querySelectorAll( '[data-oc-upload-zone]' ).forEach( zoneEl => {
			const lid = parseInt( zoneEl.dataset.ocUploadZone, 10 );
			if ( ! lid ) return;
			const uploadUrl = this.data?.uploadUrl || '';
			if ( ! uploadUrl ) {
				this.showUploadError( zoneEl, 'Uploads are unavailable right now.' );
				return;
			}

			// Find the layer's per-layer settings; fall back to global defaults.
			let layer = null;
			for ( const area of this.areas ) {
				layer = ( area.layers || [] ).find( l => l.id === lid );
				if ( layer ) break;
			}
			if ( ! layer ) {
				console.warn( '[OC] Upload zone has no matching layer:', lid );
				return;
			}
			const layerFormats = Array.isArray( layer?.settings?.formats ) ? layer.settings.formats : [];
			const globalFormats = Array.isArray( this.data.allowedFormats ) ? this.data.allowedFormats : [];
			const serverFormats = [ 'jpg', 'jpeg', 'png', 'svg', 'pdf', 'eps', 'webp' ];
			const effective     = ( layerFormats.length ? layerFormats : globalFormats )
				.map( f => String( f ).toLowerCase().replace( /^\./, '' ) )
				.filter( ext => serverFormats.includes( ext ) );
			const allowedExt    = effective.length ? effective.map( ext => `.${ ext }` ) : [ '.jpg', '.jpeg', '.png', '.svg', '.pdf', '.webp' ];

			const layerMaxMb  = parseInt( layer?.settings?.max_size_mb, 10 );
			const globalMaxMb = parseInt( this.data.maxUploadSizeMb, 10 );
			const maxMb       = layerMaxMb > 0 ? layerMaxMb : ( globalMaxMb > 0 ? globalMaxMb : 10 );

			const uppy = new Uppy( {
				autoProceed: true,
				onBeforeFileAdded: () => {
					uppy.getFiles().forEach( existingFile => uppy.removeFile( existingFile.id ) );
					this.setUploadZoneState( zoneEl, '' );
					const warnEl = document.querySelector( `.oc-resolution-warning[data-oc-resolution-warning="${ lid }"]` );
					if ( warnEl ) warnEl.style.display = 'none';
					this.setUploadProgress( zoneEl, 0, 'Starting upload...' );
					this.showUploadError( zoneEl, '' );
					return true;
				},
				restrictions: {
					maxNumberOfFiles: 1,
					maxFileSize:      maxMb * 1024 * 1024,
					allowedFileTypes: allowedExt,
				},
			} );
			uppy.use( DragDrop, {
				target: zoneEl,
				note: 'We accept ' + ( allowedExt.length ? allowedExt.map( e => e.replace( '.', '' ).toUpperCase() ).join( ', ' ) : 'JPG, PNG, PDF, EPS' ) + ' and other common image types.',
				locale: {
					strings: {
						dropHereOr: '%{browse}',
						browse:     'Tap / click here to upload your image',
					},
				},
			} );
			uppy.use( XHRUpload, {
				endpoint:   this.uploadEndpoint( uploadUrl, lid ),
				formData:   true,
				fieldName:  'artwork',
			} );

			uppy.on( 'upload-progress', ( file, progress ) => {
				const percent = progress?.bytesTotal
					? Math.round( ( progress.bytesUploaded / progress.bytesTotal ) * 100 )
					: 0;
				this.setUploadProgress( zoneEl, percent, `Uploading ${ percent }%` );
			} );

			uppy.on( 'upload-success', async ( file, res ) => {
				console.log( '[OC] Upload success — response body:', res?.body );
				this.setUploadProgress( zoneEl, 100, '' );
				if ( ! res?.body ) {
					this.setUploadZoneState( zoneEl, 'error' );
					this.showUploadError( zoneEl, 'Upload succeeded but server returned no data.' );
					return;
				}
				if ( ! this.inputs[ lid ] ) this.inputs[ lid ] = {};
				this.inputs[ lid ].attachmentId  = res.body.attachment_id || 0;
				this.inputs[ lid ].attachmentUrl = res.body.preview_url   || '';
				this.inputs[ lid ].imageMeta     = null;
				if ( ! this.inputs[ lid ].attachmentUrl ) {
					this.setUploadZoneState( zoneEl, 'error' );
					this.showUploadError( zoneEl, 'Server did not return a preview URL.' );
					return;
				}
				const meta = await this.getImageMeta( this.inputs[ lid ].attachmentUrl );
				if ( meta && this.inputs[ lid ] ) {
					this.inputs[ lid ].imageMeta = meta;
					const thresholdW = Math.round( layer.w * ( 300 / 72 ) );
					const thresholdH = Math.round( layer.h * ( 300 / 72 ) );
					const warnEl = document.querySelector( `.oc-resolution-warning[data-oc-resolution-warning="${ lid }"]` );
					if ( warnEl ) {
						const belowThreshold = meta.width < thresholdW || meta.height < thresholdH;
						const belowHalf = meta.width < thresholdW * 0.5 || meta.height < thresholdH * 0.5;
						if ( belowHalf ) {
							warnEl.className = 'oc-resolution-warning oc-res-error';
							warnEl.textContent = `This image is too low resolution for quality printing. Minimum required: ${ thresholdW } x ${ thresholdH } pixels.`;
							warnEl.style.display = '';
							this.inputs[ lid ].attachmentId = 0;
							this.inputs[ lid ].attachmentUrl = '';
							this.inputs[ lid ].imageMeta = null;
							this.syncLinkedLayerInput( lid, [ 'attachmentId', 'attachmentUrl', 'imageMeta' ] );
							this.setUploadZoneState( zoneEl, 'error' );
							this.showUploadError( zoneEl, 'Image resolution too low. Please upload a higher resolution image.' );
							this.scheduleRedraw();
							this.updateHiddenField();
							return;
						} else if ( belowThreshold ) {
							warnEl.className = 'oc-resolution-warning oc-res-warning';
							warnEl.textContent = `This image may not print clearly at full size. Recommended minimum: ${ thresholdW } x ${ thresholdH } pixels.`;
							warnEl.style.display = '';
						} else {
							warnEl.style.display = 'none';
						}
					}
				}
				this.setUploadZoneState( zoneEl, 'uploaded' );
				this.syncLinkedLayerInput( lid, [ 'attachmentId', 'attachmentUrl', 'imageMeta' ] );
				this.requestPreviewFocus();
				this.scheduleRedraw();
				this.updateHiddenField();
				this.showUploadError( zoneEl, '' );
			} );

			uppy.on( 'upload-error', ( file, error, response ) => {
				let responseBody = response?.body || null;
				if ( ! responseBody && response?.responseText ) {
					try {
						responseBody = JSON.parse( response.responseText );
					} catch ( e ) {
						responseBody = { message: response.responseText };
					}
				}
				const msg = responseBody?.message || error?.message || 'Upload failed.';
				console.warn( '[OC] Upload error:', msg, response );
				this.setUploadZoneState( zoneEl, 'error' );
				this.setUploadProgress( zoneEl, 0, '' );
				this.showUploadError( zoneEl, msg );
			} );
			uppy.on( 'restriction-failed', ( file, error ) => {
				this.setUploadZoneState( zoneEl, 'error' );
				this.setUploadProgress( zoneEl, 0, '' );
				this.showUploadError( zoneEl, error?.message || 'File not allowed.' );
			} );
		} );
	}

	setUploadZoneState( zoneEl, state ) {
		zoneEl.classList.toggle( 'oc-upload-zone--uploaded', state === 'uploaded' );
		zoneEl.classList.toggle( 'oc-upload-zone--error', state === 'error' );

		const browse = zoneEl.querySelector( '.uppy-DragDrop-browse' );
		const note = zoneEl.querySelector( '.uppy-DragDrop-note' );
		if ( browse ) {
			browse.textContent = state === 'uploaded'
				? 'Image uploaded'
				: 'Tap / click here to upload your image';
		}
		if ( note ) {
			if ( ! note.dataset.ocOriginalText ) note.dataset.ocOriginalText = note.textContent;
			note.textContent = state === 'uploaded'
				? 'Click to replace image'
				: note.dataset.ocOriginalText || note.textContent;
		}
	}

	setUploadProgress( zoneEl, percent, label ) {
		const wrap = zoneEl.closest( '.oc-artwork-wrap' );
		if ( ! wrap ) return;
		let progressEl = wrap.querySelector( '.oc-upload-progress' );
		if ( ! progressEl ) {
			progressEl = document.createElement( 'div' );
			progressEl.className = 'oc-upload-progress';
			progressEl.innerHTML = '<div class="oc-upload-progress-label"></div><div class="oc-upload-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><div class="oc-upload-progress-bar"></div></div>';
			zoneEl.insertAdjacentElement( 'afterend', progressEl );
		}

		const safePercent = Math.max( 0, Math.min( 100, parseInt( percent, 10 ) || 0 ) );
		const labelEl = progressEl.querySelector( '.oc-upload-progress-label' );
		const track = progressEl.querySelector( '.oc-upload-progress-track' );
		const bar = progressEl.querySelector( '.oc-upload-progress-bar' );

		if ( labelEl ) labelEl.textContent = label || '';
		if ( track ) track.setAttribute( 'aria-valuenow', String( safePercent ) );
		if ( bar ) bar.style.width = `${ safePercent }%`;
		progressEl.style.display = label ? '' : 'none';
	}

	showUploadError( zoneEl, message ) {
		const wrap = zoneEl.closest( '.oc-artwork-wrap' );
		if ( ! wrap ) return;
		let err = wrap.querySelector( '.oc-artwork-error' );
		if ( ! err ) {
			err = document.createElement( 'div' );
			err.className = 'oc-artwork-error';
			err.style.cssText = 'color:#b32d2e;font-size:12px;margin-top:6px;';
			wrap.appendChild( err );
		}
		err.textContent = message || '';
		err.style.display = message ? '' : 'none';
	}

	// ── Cart serialisation ────────────────────────────────────────────────────────

	updateHiddenField() {
		const el = document.getElementById( 'oc-customisation-data' );
		if ( ! el ) return;
		const layers = {};
		this.areas.forEach( area => {
			( area.layers || [] ).forEach( layer => {
				const inp = this.inputs[ layer.id ];
				if ( inp ) {
					this.clampLayerInputValue( layer.id );
					layers[ layer.id ] = { type: layer.type, ...this.inputs[ layer.id ] };
				}
			} );
		} );
		const payload = { v: 2, designId: this.data.designId, layers };
		if ( this.selectedDesignVariant ) {
			const variant = this.designVariants.find( item => item.id === this.selectedDesignVariant );
			payload.designVariant = this.selectedDesignVariant;
			if ( variant?.label ) payload.designVariantLabel = variant.label;
		}
		if ( this._previewUrl ) payload.previewUrl = this._previewUrl;
		el.value = JSON.stringify( payload );
	}
}
