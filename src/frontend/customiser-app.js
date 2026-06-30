/**
 * Frontend Customiser — vanilla JS, no framework dependency.
 *
 * Data: window.ocCustomiserData (wp_localize_script).
 * Canvas: Fabric.js 6.x  |  Uploads: Uppy 4.x
 *
 * @package OverCustomise
 */

import { StaticCanvas, FabricImage, FabricText, Rect, Shadow, filters as FabricFilters } from 'fabric';
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
		this.activeArea = 0;

		// Deep-clone mutable per-layer inputs; keys are integer layer IDs.
		this.inputs = {};
		Object.entries( data.layerInputs || {} ).forEach( ( [ k, v ] ) => {
			this.inputs[ parseInt( k, 10 ) ] = { ...v };
		} );

		this.editMode      = !!( data.editMode && data.cartKey );
		this.cartKey       = this.editMode ? data.cartKey : '';
		this.canvases      = {};   // areaIndex → Fabric StaticCanvas
		this.fontCache     = {};   // fontName  → load Promise
		this.galleryImg        = null; // the main <img> in the product gallery
		this._previewUrl       = null; // saved preview URL (set just before cart submit)
		this._focusPreviewSlide = false; // jump TVPG to preview slide after user edits
		this._mobilePreviewVisible = false;
		this.spotifyValidateTimers = {};
		this.spotifyValidateTokens = {};
		this.preflightRoot = null;
		this.clipartByGroup = {};
		this.clipartSearchTimers = {};
		this.clipartSearchTerms = {};
		this.clipartCategoryFilters = {};

		if ( this.editMode ) {
			Object.entries( data.layerInputs || {} ).forEach( ( [ k, v ] ) => {
				const key = parseInt( k, 10 );
				if ( this.inputs[ key ] && typeof v === 'object' && v !== null ) {
					Object.assign( this.inputs[ key ], v );
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
		this.setupMobilePreview();
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
		const center      = this.rotatedLayerCenter( layer, bounds, rotation );
		const lx          = ( center.x - layer.w / 2 ) * scale;
		const ly          = ( center.y - layer.h / 2 ) * scale;
		const lw          = Math.max( layer.w * scale, 10 );
		const lh          = Math.max( layer.h * scale, 10 );
		const lcX         = center.x * scale;
		const lcY         = center.y * scale;
		const isEngraving = area?.printMethod === 'engraving';
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
				// Engraving uses the product undertone instead of a customer-selected colour.
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
				const obj    = new FabricText( raw, {
					left: lcX, top: lcY,
					originX: 'center', originY: 'center',
					width: lw,
					angle: rotation,
					fontFamily: font?.name || 'sans-serif',
					fontSize, fill: color, textAlign: align,
					selectable: false, evented: false,
				} );
				obj._ocContent = true; // tag after creation

				if ( isEngraving ) {
					// Fake etched depth: subtle light highlight below + soft dark shadow above.
					obj.set( {
						opacity: 0.92,
						shadow: new Shadow( { color: engravingPalette.highlight, offsetX: 0, offsetY: 1, blur: 1 } ),
					} );
				}

				while ( obj.width > lw && fontSize > Math.max( 8, minFontSize ) ) {
					fontSize -= 1; obj.set( { fontSize } );
				}
				canvas.add( obj );
				break;
			}

			case 'image':
				if ( input.attachmentUrl ) await this.renderFabricImg( canvas, input.attachmentUrl, lx, ly, lw, lh, isEngraving, 'anonymous', false, rotation, engravingPalette );
				break;

			case 'clipart':
				if ( input.clipartUrl ) await this.renderFabricImg( canvas, input.clipartUrl, lx, ly, lw, lh, isEngraving, 'anonymous', false, rotation, engravingPalette );
				break;

			case 'lineart': {
				const lineartColor = isEngraving ? engravingPalette.text : String( input.colorHex || '' ).trim();
				if ( ! lineartColor ) break;
				const r = new Rect( { left: lcX, top: lcY, originX: 'center', originY: 'center', angle: rotation, width: lw, height: lh,
					fill: lineartColor, opacity: 0.6, selectable: false, evented: false } );
				r._ocContent = true;
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
					canvas.add( invalidObj );
					break;
				}

				const spotifyCodeUrl = this.buildSpotifyCodeUrl( input.spotifyUri || val, isEngraving, engravingPalette );
				if ( spotifyCodeUrl ) {
					// Try CORS-safe load first; if Spotify CDN blocks CORS for this origin,
					// retry without crossOrigin so users still see the scannable in live preview.
					let rendered = await this.renderFabricImg( canvas, spotifyCodeUrl, lx, ly, lw, lh, isEngraving, 'anonymous', true, rotation, engravingPalette );
					if ( ! rendered ) {
						rendered = await this.renderFabricImg( canvas, spotifyCodeUrl, lx, ly, lw, lh, isEngraving, '', true, rotation, engravingPalette );
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
		const palettes = {
			warm:    { text: '#2a1f14', bg: 'F5F2EF', highlight: 'rgba(255,255,255,0.35)', brightness: -0.35, contrast: 0.15, opacity: 0.88 },
			cool:    { text: '#27313a', bg: 'ECEFF1', highlight: 'rgba(255,255,255,0.42)', brightness: -0.28, contrast: 0.18, opacity: 0.9 },
			gold:    { text: '#4a3410', bg: 'F7E7BE', highlight: 'rgba(255,246,215,0.45)', brightness: -0.32, contrast: 0.16, opacity: 0.88 },
			rose:    { text: '#4a241f', bg: 'F3D6CC', highlight: 'rgba(255,234,224,0.45)', brightness: -0.3, contrast: 0.16, opacity: 0.88 },
			dark:    { text: '#d1b994', bg: '2F2924', highlight: 'rgba(255,235,200,0.25)', brightness: 0.05, contrast: 0.22, opacity: 0.92 },
			neutral: { text: '#333333', bg: 'F0F0F0', highlight: 'rgba(255,255,255,0.35)', brightness: -0.3, contrast: 0.15, opacity: 0.88 },
		};
		return palettes[ this.data.engravingUndertone ] || palettes.warm;
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

	async renderFabricImg( canvas, url, x, y, w, h, isEngraving = false, crossOrigin = 'anonymous', makeWhiteTransparent = false, angle = 0, engravingPalette = null ) {
		try {
			const imgLoadOpts = crossOrigin ? { crossOrigin } : {};
			const img = await FabricImage.fromURL( url, imgLoadOpts );
			if ( ! img || ! img.width ) {
				console.warn( '[OC] Image failed to load or has zero dimensions:', url );
				return false;
			}
			const s = Math.min( w / img.width, h / img.height );
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
			const updateCounter = () => {
				if ( ! counter ) return;
				const limit = parseInt( counter.dataset.charLimit, 10 ) || 0;
				if ( limit === 0 ) {
					counter.style.display = 'none';
					return;
				}
				const current = el.value.length;
				counter.textContent = `${ current } / ${ limit }`;
				counter.classList.remove( '--under', '--near', '--over' );
				const pct = current / limit;
				if ( pct > 0.95 ) counter.classList.add( '--over' );
				else if ( pct >= 0.80 ) counter.classList.add( '--near' );
				else counter.classList.add( '--under' );
				counter.style.display = '';
			};
			updateCounter();
			el.addEventListener( 'input', () => {
				if ( ! this.inputs[ lid ] ) this.inputs[ lid ] = {};
				this.inputs[ lid ].value = el.value;
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
		document.querySelectorAll( '.oc-help-toggle, .oc-spotify-help-toggle' ).forEach( btn => {
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
			el.addEventListener( 'input', () => {
				if ( ! this.inputs[ lid ] ) this.inputs[ lid ] = {};
				this.inputs[ lid ].fontSize = Math.max( 1, parseInt( el.value, 10 ) || 1 );
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

		// Remove uploaded image
		document.querySelectorAll( '[data-oc-remove-image]' ).forEach( btn => {
			btn.addEventListener( 'click', () => {
				const lid = parseInt( btn.dataset.ocRemoveImage, 10 );
				const zoneEl = btn.closest( '.oc-artwork-wrap' )?.querySelector( `[data-oc-upload-zone="${ lid }"]` );
				if ( zoneEl ) this.clearUploadedImage( lid, zoneEl );
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

	getLayerInputEl( layer ) {
		if ( ! layer?.id ) return null;
		switch ( layer.type ) {
			case 'text':
			case 'textarea':
				return document.querySelector( `[data-oc-layer-text="${ layer.id }"]` );
			case 'spotify':
				return document.querySelector( `[data-oc-layer-spotify="${ layer.id }"]` );
			case 'image':
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
							if ( charLimit > 0 && value.length > charLimit ) {
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
			this.setSpotifyError( layerId, '', inputEl );
			this.scheduleRedraw();
			this.updateHiddenField();
			return;
		}

		const localUri = this.extractSpotifyUri( value );
		if ( ! localUri ) {
			this.inputs[ layerId ].spotifyStatus = 'invalid_format';
			this.inputs[ layerId ].spotifyUri = '';
			this.setSpotifyError( layerId, 'Invalid Spotify link format.', inputEl );
			this.scheduleRedraw();
			this.updateHiddenField();
			return;
		}

		if ( ! this.data.validateSpotifyUrl ) {
			this.inputs[ layerId ].spotifyStatus = 'ok';
			this.inputs[ layerId ].spotifyUri = localUri;
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
				this.setSpotifyError( layerId, statusMessage, inputEl );
				this.scheduleRedraw();
				this.updateHiddenField();
				return;
			}

			if ( ! json ) {
				this.inputs[ layerId ].spotifyStatus = 'unreachable';
				this.inputs[ layerId ].spotifyUri = '';
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

		form.addEventListener( 'submit', async e => {
			if ( form._ocSubmitReady ) return; // preview already saved — let submit through

			const preflight = await this.runPreflight();
			this.renderPreflightMessages( preflight.errors, preflight.warnings );
			if ( ! preflight.ok ) {
				e.preventDefault();
				return;
			}

			if ( preflight.warnings.length ) {
				const proceed = window.confirm(
					'We found quality warnings that may affect print output. Press OK to continue, or Cancel to review.'
				);
				if ( ! proceed ) {
					e.preventDefault();
					return;
				}
			}

			e.preventDefault();
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

	setupMobilePreview() {
		const toggleBtn = document.getElementById( 'oc-preview-toggle' );
		const canvasWrap = document.getElementById( 'oc-canvas-wrap' );
		if ( ! toggleBtn || ! canvasWrap ) return;

		const updateToggle = () => {
			const isVisible = canvasWrap.classList.contains( 'oc-preview-visible' );
			this._mobilePreviewVisible = isVisible;
			toggleBtn.setAttribute( 'aria-expanded', isVisible ? 'true' : 'false' );
			toggleBtn.textContent = isVisible ? 'Hide Preview' : 'Show Preview';
		};

		toggleBtn.addEventListener( 'click', () => {
			canvasWrap.classList.toggle( 'oc-preview-visible' );
			updateToggle();
		} );

		toggleBtn.addEventListener( 'touchend', ( e ) => {
			e.preventDefault();
			toggleBtn.click();
		}, { passive: false } );
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
				this.setUploadProgress( zoneEl, 100, 'Upload complete' );
				if ( ! res?.body ) {
					this.showUploadError( zoneEl, 'Upload succeeded but server returned no data.' );
					return;
				}
				if ( ! this.inputs[ lid ] ) this.inputs[ lid ] = {};
				this.inputs[ lid ].attachmentId  = res.body.attachment_id || 0;
				this.inputs[ lid ].attachmentUrl = res.body.preview_url   || '';
				this.inputs[ lid ].imageMeta     = null;
				if ( ! this.inputs[ lid ].attachmentUrl ) {
					this.showUploadError( zoneEl, 'Server did not return a preview URL.' );
					return;
				}
				const actions = zoneEl.closest( '.oc-artwork-wrap' )?.querySelector( '.oc-artwork-actions' );
				if ( actions ) actions.style.display = '';
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
							if ( actions ) actions.style.display = 'none';
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
				this.setUploadProgress( zoneEl, 0, '' );
				this.showUploadError( zoneEl, msg );
			} );
			uppy.on( 'restriction-failed', ( file, error ) => {
				this.setUploadProgress( zoneEl, 0, '' );
				this.showUploadError( zoneEl, error?.message || 'File not allowed.' );
			} );
		} );
	}

	clearUploadedImage( layerId, zoneEl ) {
		if ( this.inputs[ layerId ] ) {
			this.inputs[ layerId ].attachmentId = 0;
			this.inputs[ layerId ].attachmentUrl = '';
			this.inputs[ layerId ].imageMeta = null;
		}
		const actions = zoneEl.closest( '.oc-artwork-wrap' )?.querySelector( '.oc-artwork-actions' );
		if ( actions ) actions.style.display = 'none';
		const warnEl = document.querySelector( `.oc-resolution-warning[data-oc-resolution-warning="${ layerId }"]` );
		if ( warnEl ) warnEl.style.display = 'none';
		this.requestPreviewFocus();
		this.scheduleRedraw();
		this.updateHiddenField();
		this.showUploadError( zoneEl, '' );
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
				if ( inp ) layers[ layer.id ] = { type: layer.type, ...inp };
			} );
		} );
		const payload = { v: 2, designId: this.data.designId, engravingUndertone: this.data.engravingUndertone || 'warm', layers };
		if ( this._previewUrl ) payload.previewUrl = this._previewUrl;
		el.value = JSON.stringify( payload );
	}
}
