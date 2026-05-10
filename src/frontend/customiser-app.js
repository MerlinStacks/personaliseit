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

	// ── Init ───────────────────────────────────────────────────────────────────

	init() {
		this.findGalleryImage();
		this.preflightRoot = document.getElementById( 'oc-preflight-messages' );

		// Seed first font for text layers so they render immediately.
		if ( this.fonts.length ) {
			const firstFont = this.fonts[ 0 ];
			this.areas.forEach( area => {
				( area.layers || [] ).forEach( layer => {
					if ( ( layer.type === 'text' || layer.type === 'textarea' ) ) {
						const inp = this.inputs[ layer.id ];
						if ( inp && ! inp.fontId ) inp.fontId = firstFont.id;
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

		// Update zoom / lightbox href if wrapped in <a>.
		const a = img.closest( 'a' );
		if ( a ) a.href = dataUrl;

		// WooCommerce zoom/lightbox compatibility attributes.
		img.setAttribute( 'data-large_image', dataUrl );
		img.setAttribute( 'data-src', dataUrl );
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
			this._focusPreviewSlide = false;
			return;
		}

		const targets = new Set();
		if ( this.galleryImg ) {
			targets.add( this.galleryImg );
		}

		document.querySelectorAll(
			'.tvpg-main-slider .swiper-slide .woocommerce-product-gallery__image img'
		).forEach( img => targets.add( img ) );

		document.querySelectorAll(
			'.woocommerce-product-gallery .woocommerce-product-gallery__image img'
		).forEach( img => targets.add( img ) );

		targets.forEach( img => this.applyPreviewToImage( img, dataUrl ) );

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
				left: b.x * scaleX, top: b.y * scaleX,
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
		const lx          = layer.x * scale;
		const ly          = layer.y * scale;
		const lw          = Math.max( layer.w * scale, 10 );
		const lh          = Math.max( layer.h * scale, 10 );
		const isEngraving = area?.printMethod === 'engraving';

		switch ( layer.type ) {

			case 'text':
			case 'textarea': {
				const raw  = ( input.value || '' ).trim() || ( layer.settings?.default_text || '' ).trim();
				if ( ! raw ) break;

				let font  = this.fonts.find( f => f.id === ( input.fontId || 0 ) );
				// Engraving uses a fixed etched colour — user colour is irrelevant for laser engraving.
				const color = isEngraving ? '#2a1f14' : ( input.colorHex || '#000000' );
				const align = layer.settings?.alignment || 'center';
				if ( font ) {
					try {
						await this.loadFont( font );
					} catch ( err ) {
						console.warn( '[OC] Font load failed, falling back to sans-serif:', err );
						font = null;
					}
				}

				let fontSize = Math.max( 10, Math.round( lh * 0.42 ) );
				const obj    = new FabricText( raw, {
					left: lx + lw / 2, top: ly + lh / 2,
					originX: 'center', originY: 'center',
					width: lw,
					fontFamily: font?.name || 'sans-serif',
					fontSize, fill: color, textAlign: align,
					selectable: false, evented: false,
				} );
				obj._ocContent = true; // tag after creation

				if ( isEngraving ) {
					// Fake etched depth: subtle light highlight below + soft dark shadow above.
					obj.set( {
						opacity: 0.92,
						shadow: new Shadow( { color: 'rgba(255,255,255,0.35)', offsetX: 0, offsetY: 1, blur: 1 } ),
					} );
				}

				while ( obj.width > lw && fontSize > 8 ) {
					fontSize -= 1; obj.set( { fontSize } );
				}
				canvas.add( obj );
				break;
			}

			case 'image':
				if ( input.attachmentUrl ) await this.renderFabricImg( canvas, input.attachmentUrl, lx, ly, lw, lh, isEngraving );
				break;

			case 'clipart':
				if ( input.clipartUrl ) await this.renderFabricImg( canvas, input.clipartUrl, lx, ly, lw, lh, isEngraving );
				break;

			case 'lineart': {
				const lineartColor = String( input.colorHex || '' ).trim();
				if ( ! lineartColor ) break;
				const r = new Rect( { left: lx, top: ly, width: lw, height: lh,
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
						left: lx + lw / 2, top: ly + lh / 2,
						originX: 'center', originY: 'center',
						fontFamily: 'monospace', fontSize: Math.max( 9, Math.round( lh * 0.17 ) ),
						fill: '#b32d2e',
						textAlign: 'center', selectable: false, evented: false,
					} );
					invalidObj._ocContent = true;
					canvas.add( invalidObj );
					break;
				}

				const spotifyCodeUrl = this.buildSpotifyCodeUrl( input.spotifyUri || val, isEngraving );
				if ( spotifyCodeUrl ) {
					// Try CORS-safe load first; if Spotify CDN blocks CORS for this origin,
					// retry without crossOrigin so users still see the scannable in live preview.
					let rendered = await this.renderFabricImg( canvas, spotifyCodeUrl, lx, ly, lw, lh, isEngraving, 'anonymous', true );
					if ( ! rendered ) {
						rendered = await this.renderFabricImg( canvas, spotifyCodeUrl, lx, ly, lw, lh, isEngraving, '', true );
					}
					if ( rendered ) break;
				}

				const fallback = new FabricText( '\u266b Spotify code unavailable', {
					left: lx + lw / 2, top: ly + lh / 2,
					originX: 'center', originY: 'center',
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

	buildSpotifyCodeUrl( inputValue, isEngraving ) {
		const spotifyUri = this.extractSpotifyUri( inputValue );
		if ( ! spotifyUri ) return '';

		// Official Spotify scannable-code endpoint.
		// Endpoint shape:
		// /uri/plain/{format}/{background-hex}/{bar-colour}/{size}/{spotify-uri}
		// We request SVG and then strip white in-canvas for transparent compositing.
		const format = 'svg';
		const bgHex  = isEngraving ? 'F5F2EF' : 'FFFFFF';
		const bar    = isEngraving ? 'black' : 'black';
		const size   = 640;

		return `https://scannables.scdn.co/uri/plain/${ format }/${ bgHex }/${ bar }/${ size }/${ spotifyUri }`;
	}

	async renderFabricImg( canvas, url, x, y, w, h, isEngraving = false, crossOrigin = 'anonymous', makeWhiteTransparent = false ) {
		try {
			const imgLoadOpts = crossOrigin ? { crossOrigin } : {};
			const img = await FabricImage.fromURL( url, imgLoadOpts );
			if ( ! img || ! img.width ) {
				console.warn( '[OC] Image failed to load or has zero dimensions:', url );
				return false;
			}
			const s = Math.min( w / img.width, h / img.height );
			img.set( { left: x + w / 2, top: y + h / 2, originX: 'center', originY: 'center',
				scaleX: s, scaleY: s, selectable: false, evented: false } );

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
				filters.push(
					new FabricFilters.Grayscale(),
					new FabricFilters.Brightness( { brightness: -0.35 } ),
					new FabricFilters.Contrast( { contrast: 0.15 } )
				);
			}
			if ( filters.length ) {
				img.filters = filters;
				img.applyFilters();
			}
			if ( isEngraving ) {
				img.set( { opacity: 0.88 } );
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
		document.querySelectorAll( '.oc-area-tab' ).forEach( btn => {
			btn.addEventListener( 'click', () => this.switchArea( parseInt( btn.dataset.areaIndex, 10 ) ) );
			btn.addEventListener( 'touchend', ( e ) => {
				e.preventDefault();
				this.switchArea( parseInt( btn.dataset.areaIndex, 10 ) );
			}, { passive: false } );
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

		// Colour swatches
		document.querySelectorAll( '[data-oc-layer-swatch]' ).forEach( btn => {
			btn.addEventListener( 'click', () => {
				const lid = parseInt( btn.dataset.ocLayerSwatch, 10 );
				if ( ! this.inputs[ lid ] ) this.inputs[ lid ] = {};
				this.inputs[ lid ].colorHex = btn.dataset.hex;
				btn.closest( '.oc-colour-swatches' )?.querySelectorAll( '.oc-colour-swatch' )
					.forEach( s => s.classList.toggle( 'oc-selected', s === btn ) );
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
					.forEach( i => i.classList.toggle( 'oc-selected', i === btn ) );
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
				if ( this.inputs[ lid ] ) {
					this.inputs[ lid ].attachmentId = 0;
					this.inputs[ lid ].attachmentUrl = '';
					this.inputs[ lid ].imageMeta = null;
				}
				btn.closest( '.oc-artwork-wrap' )?.querySelector( '.oc-artwork-actions' )
					?.setAttribute( 'style', 'display:none' );
				const warnEl = document.querySelector( `.oc-resolution-warning[data-oc-resolution-warning="${ lid }"]` );
				if ( warnEl ) warnEl.style.display = 'none';
				this.requestPreviewFocus();
				this.scheduleRedraw();
				this.updateHiddenField();
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
			const effective     = ( layerFormats.length ? layerFormats : globalFormats ).map( f => String( f ).toLowerCase().replace( /^\./, '' ) );
			const allowedExt    = effective.length ? effective.map( ext => `.${ ext }` ) : [ '.jpg', '.jpeg', '.png', '.svg', '.pdf' ];

			const layerMaxMb  = parseInt( layer?.settings?.max_size_mb, 10 );
			const globalMaxMb = parseInt( this.data.maxUploadSizeMb, 10 );
			const maxMb       = layerMaxMb > 0 ? layerMaxMb : ( globalMaxMb > 0 ? globalMaxMb : 10 );

			const uppy = new Uppy( {
				autoProceed: true,
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
				endpoint:   uploadUrl + ( uploadUrl.includes( '?' ) ? '&' : '?' ) + 'layer_id=' + lid,
				formData:   true,
				fieldName:  'artwork',
				headers:    this.restHeaders(),
			} );

			uppy.on( 'upload-success', async ( file, res ) => {
				console.log( '[OC] Upload success — response body:', res?.body );
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
				const msg = response?.body?.message || error?.message || 'Upload failed.';
				console.warn( '[OC] Upload error:', msg, response );
				this.showUploadError( zoneEl, msg );
			} );
			uppy.on( 'restriction-failed', ( file, error ) => {
				this.showUploadError( zoneEl, error?.message || 'File not allowed.' );
			} );
		} );
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
		const payload = { v: 2, designId: this.data.designId, layers };
		if ( this._previewUrl ) payload.previewUrl = this._previewUrl;
		el.value = JSON.stringify( payload );
	}
}
