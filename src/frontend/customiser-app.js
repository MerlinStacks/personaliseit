/**
 * Frontend Customiser — vanilla JS, no framework dependency.
 *
 * Data: window.ocCustomiserData (wp_localize_script).
 * Canvas: Fabric.js 6.x  |  Uploads: Uppy 4.x
 *
 * @package
 */

/* eslint-disable no-console, no-alert, no-undef, no-unused-vars, no-nested-ternary, @wordpress/no-unused-vars-before-return */

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
import Uppy from '@uppy/core';
import DragDrop from '@uppy/drag-drop';
import XHRUpload from '@uppy/xhr-upload';

import '@uppy/core/css/style.min.css';
import '@uppy/drag-drop/css/style.min.css';
import './customiser-app.scss';
import {
	displayBounds,
	displayFontSize,
	displayLayer,
} from '../shared/render-math';

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

	applyPreviewToImage( img, dataUrl, dimensions = null ) {
		if ( ! img ) {
			return;
		}
		const hasDimensions = dimensions?.width && dimensions?.height;
		const aspectRatio = hasDimensions
			? `${ dimensions.width } / ${ dimensions.height }`
			: '';
		const ratioPadding = hasDimensions
			? `${ ( dimensions.height / dimensions.width ) * 100 }%`
			: '';
		img.src = dataUrl;
		img.srcset = '';
		img.sizes = '';
		img.classList.add( 'oc-live-preview-applied' );

		if ( hasDimensions ) {
			img.width = dimensions.width;
			img.height = dimensions.height;
			img.style.aspectRatio = aspectRatio;
		}
		img.style.display = 'block';
		img.style.width = '100%';
		img.style.objectFit = 'contain';
		img.style.height = 'auto';
		img.style.maxHeight = 'none';
		img.style.position = 'static';

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

		const galleryItem = img.closest(
			'.woocommerce-product-gallery__image, .product-gallery-slider .slide'
		);
		if ( galleryItem ) {
			galleryItem.setAttribute( 'data-thumb', dataUrl );
			if (
				hasDimensions &&
				! img.closest( '.product-thumbnails, .tvpg-thumb-slider' )
			) {
				galleryItem.classList.add( 'oc-live-preview-frame' );
				galleryItem.style.aspectRatio = aspectRatio;
				galleryItem.style.height = 'auto';
				galleryItem.style.paddingTop = '0';
				galleryItem.style.paddingBottom = ratioPadding;

				const link = img.closest( 'a' );
				if ( link && galleryItem.contains( link ) ) {
					link.classList.add( 'oc-live-preview-frame' );
					link.style.aspectRatio = aspectRatio;
					link.style.height = 'auto';
					link.style.paddingTop = '0';
					link.style.paddingBottom = ratioPadding;
				}
			}
		}
	}

	refreshFlatsomeGallery() {
		const slider = document.querySelector( '.product-gallery-slider' );
		if ( ! slider ) {
			return;
		}

		const flickity =
			slider.flickity || window.jQuery?.( slider ).data( 'flickity' );
		flickity?.reloadCells?.();
		flickity?.resize?.();
	}

	getFlickityInstance( slider ) {
		if ( ! slider ) {
			return null;
		}
		return (
			slider.flickity ||
			window.jQuery?.( slider ).data( 'flickity' ) ||
			null
		);
	}

	applyFlatsomeOverlayPreview( dataUrl, dimensions = null ) {
		const slider = document.querySelector( '.product-gallery-slider' );
		if ( ! slider ) {
			return false;
		}

		const realSlides = slider.querySelectorAll(
			'.woocommerce-product-gallery__image:not(.oc-live-preview-slide), .slide:not(.oc-live-preview-slide)'
		);
		if ( realSlides.length <= 1 ) {
			return false;
		}

		let flickity = this.getFlickityInstance( slider );
		let previewSlide = slider.querySelector( '.oc-live-preview-slide' );

		if ( ! previewSlide ) {
			previewSlide = document.createElement( 'div' );
			previewSlide.className =
				'woocommerce-product-gallery__image slide oc-live-preview-slide';
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

		const previewImg = previewSlide.querySelector(
			'img.oc-live-preview-image'
		);
		if ( previewImg ) {
			this.applyPreviewToImage( previewImg, dataUrl, dimensions );
		}

		previewSlide.setAttribute( 'data-thumb', dataUrl );
		previewSlide.querySelector( 'a' )?.setAttribute( 'href', dataUrl );

		flickity = this.getFlickityInstance( slider );
		if ( flickity ) {
			flickity.reloadCells?.();
			flickity.resize?.();

			const previewIndex = ( flickity.cells || [] ).findIndex(
				( cell ) => cell.element === previewSlide
			);
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
		if ( ! canvasWrap ) {
			return false;
		}

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

		canvasWrap.classList.add(
			'oc-gallery-mounted-preview',
			'oc-preview-visible'
		);
		return true;
	}

	stopTVPGAutoScroll( ...swipers ) {
		swipers.forEach( ( swiper ) => swiper?.autoplay?.stop?.() );
	}

	releaseTVPGPreviewLock( resumeAutoplay = false ) {
		this._focusPreviewSlide = false;
		this._tvpgPreviewLocked = false;

		if ( ! resumeAutoplay ) {
			return;
		}

		const mainSwiper =
			document.querySelector( '.tvpg-main-slider' )?.swiper;
		const thumbSwiper =
			document.querySelector( '.tvpg-thumb-slider' )?.swiper;
		[ mainSwiper, thumbSwiper ].forEach( ( swiper ) =>
			swiper?.autoplay?.start?.()
		);
	}

	setupCartGalleryUnlock() {
		if ( this._cartGalleryUnlockBound ) {
			return;
		}
		this._cartGalleryUnlockBound = true;

		window
			.jQuery?.( document.body )
			.on?.( 'added_to_cart', () => this.releaseTVPGPreviewLock( true ) );
	}

	lockTVPGPreviewSlide( swiper, slide ) {
		if ( ! swiper || ! slide ) {
			return;
		}
		const previewIndex = Array.from( swiper.slides || [] ).indexOf( slide );
		if ( previewIndex < 0 ) {
			return;
		}

		swiper._ocPreviewSlideIndex = previewIndex;
		if ( swiper._ocPreviewLockBound ) {
			return;
		}

		const keepPreviewActive = () => {
			if ( ! this._tvpgPreviewLocked || swiper._ocPreviewLocking ) {
				return;
			}
			const targetIndex = swiper._ocPreviewSlideIndex;
			if (
				targetIndex === undefined ||
				swiper.activeIndex === targetIndex
			) {
				return;
			}

			swiper._ocPreviewLocking = true;
			requestAnimationFrame( () => {
				swiper.slideTo?.( targetIndex, 0, false );
				swiper._ocPreviewLocking = false;
			} );
		};

		swiper.on?.(
			'activeIndexChange slideChange transitionStart',
			keepPreviewActive
		);

		swiper._ocPreviewLockBound = true;
	}

	applyTVPGOverlayPreview( dataUrl, dimensions = null ) {
		const mainSliderEl = document.querySelector( '.tvpg-main-slider' );
		const mainWrapper = mainSliderEl?.querySelector( '.swiper-wrapper' );
		if ( ! mainSliderEl || ! mainWrapper ) {
			return false;
		}

		const realSlides = mainWrapper.querySelectorAll(
			'.swiper-slide:not(.oc-live-preview-slide)'
		);
		if ( realSlides.length <= 1 ) {
			return false;
		}

		let mainPreviewSlide = mainWrapper.querySelector(
			'.swiper-slide.oc-live-preview-slide'
		);
		if ( ! mainPreviewSlide ) {
			mainPreviewSlide = document.createElement( 'div' );
			mainPreviewSlide.className = 'swiper-slide oc-live-preview-slide';
			mainPreviewSlide.innerHTML =
				'<div class="woocommerce-product-gallery__image">' +
				'<img class="oc-live-preview-image" alt="Custom preview">' +
				'</div>';
			mainWrapper.appendChild( mainPreviewSlide );
		}

		const mainImg = mainPreviewSlide.querySelector(
			'img.oc-live-preview-image'
		);
		if ( mainImg ) {
			this.applyPreviewToImage( mainImg, dataUrl, dimensions );
		}

		const thumbSliderEl = document.querySelector( '.tvpg-thumb-slider' );
		const thumbWrapper = thumbSliderEl?.querySelector( '.swiper-wrapper' );
		if ( thumbWrapper ) {
			let thumbPreviewSlide = thumbWrapper.querySelector(
				'.swiper-slide.oc-live-preview-thumb-slide'
			);
			if ( ! thumbPreviewSlide ) {
				thumbPreviewSlide = document.createElement( 'div' );
				thumbPreviewSlide.className =
					'swiper-slide oc-live-preview-thumb-slide';
				thumbPreviewSlide.innerHTML =
					'<img class="oc-live-preview-thumb-image" alt="Custom preview thumbnail">';
				thumbWrapper.appendChild( thumbPreviewSlide );
			}

			const thumbImg = thumbPreviewSlide.querySelector(
				'img.oc-live-preview-thumb-image'
			);
			if ( thumbImg ) {
				this.applyPreviewToImage( thumbImg, dataUrl, dimensions );
			}
		}

		// Swiper attaches instances to the root element; update so the new last slide is navigable.
		const mainSwiper = mainSliderEl.swiper;
		const thumbSwiper = thumbSliderEl?.swiper;
		this.stopTVPGAutoScroll( mainSwiper, thumbSwiper );

		mainSwiper?.update?.();
		thumbSwiper?.update?.();

		this.lockTVPGPreviewSlide( mainSwiper, mainPreviewSlide );
		this.lockTVPGPreviewSlide(
			thumbSwiper,
			thumbWrapper?.querySelector(
				'.swiper-slide.oc-live-preview-thumb-slide'
			)
		);

		if ( this._focusPreviewSlide && mainSwiper?.slides?.length ) {
			this._tvpgPreviewLocked = true;
			const previewIndex =
				mainSwiper._ocPreviewSlideIndex ?? mainSwiper.slides.length - 1;
			const thumbIndex =
				thumbSwiper?._ocPreviewSlideIndex ?? previewIndex;
			mainSwiper.slideTo( previewIndex );
			thumbSwiper?.slideTo?.( thumbIndex );
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
			console.warn(
				'[OC] toDataURL failed — image may be cross-origin:',
				e.message
			);
			return;
		}
		const dimensions = {
			width: Math.round( canvas.getWidth?.() || canvas.width || 0 ),
			height: Math.round( canvas.getHeight?.() || canvas.height || 0 ),
		};

		const previewImg = document.getElementById( 'oc-canvas-preview' );
		if ( previewImg ) {
			previewImg.src = dataUrl;
			previewImg.srcset = '';
			if ( dimensions.width && dimensions.height ) {
				previewImg.width = dimensions.width;
				previewImg.height = dimensions.height;
			}
		}

		if ( ! this._hasCustomerPersonalisation ) {
			return;
		}

		if ( this.applyTVPGOverlayPreview( dataUrl, dimensions ) ) {
			this.setPanelPreviewHandoff( true );
			this._focusPreviewSlide = false;
			return;
		}

		if ( this.applyFlatsomeOverlayPreview( dataUrl, dimensions ) ) {
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
		].forEach( ( selector ) => {
			document
				.querySelectorAll( selector )
				.forEach( ( img ) => targets.add( img ) );
		} );

		const applyTargets = () =>
			targets.forEach( ( img ) =>
				this.applyPreviewToImage( img, dataUrl, dimensions )
			);
		applyTargets();

		if ( document.querySelector( '.product-gallery-slider' ) ) {
			this.refreshFlatsomeGallery();
			requestAnimationFrame( applyTargets );
			setTimeout( applyTargets, 250 );
		}

		this.setPanelPreviewHandoff(
			targets.size > 0 || this.mountPreviewInGallery()
		);

		this._focusPreviewSlide = false;
	}

	requestPreviewFocus() {
		this._hasCustomerPersonalisation = true;
		this._focusPreviewSlide = true;
	}

	setupVariationGalleryHandoff() {
		const form = document.querySelector(
			'form.variations_form, form.cart'
		);
		if ( ! form || form._ocVariationGalleryHandoffBound ) {
			return;
		}

		form._ocVariationGalleryHandoffBound = true;
		const getSelectedVariationId = () =>
			parseInt(
				form.querySelector( 'input.variation_id' )?.value || '0',
				10
			) || 0;
		const releasePreviewLock = () => this.releaseTVPGPreviewLock();
		const handleVariationChange = ( variation ) => {
			releasePreviewLock();
			this.switchProductVariation(
				parseInt(
					variation?.variation_id || getSelectedVariationId(),
					10
				) || 0
			);
		};

		form.addEventListener( 'change', ( event ) => {
			if ( event.target.closest( '.variations, [name^="attribute_"]' ) ) {
				releasePreviewLock();
				setTimeout(
					() =>
						this.switchProductVariation( getSelectedVariationId() ),
					0
				);
			}
		} );

		window
			.jQuery?.( form )
			.on?.( 'woocommerce_variation_select_change', releasePreviewLock );
		window.jQuery?.( form ).on?.( 'reset_data', () => {
			releasePreviewLock();
			this.switchProductVariation( 0 );
		} );
		window
			.jQuery?.( form )
			.on?.( 'found_variation show_variation', ( event, variation ) =>
				handleVariationChange( variation )
			);

		const initialVariationId = getSelectedVariationId();
		if ( initialVariationId ) {
			this.switchProductVariation( initialVariationId );
		}
	}

	async switchProductVariation( variationId ) {
		if ( this.editMode ) {
			return;
		}

		const key = String( Math.max( 0, parseInt( variationId, 10 ) || 0 ) );
		const requestSeq = ++this._variationRequestSeq;
		let state = this.productVariationStates[ key ];

		if ( ! state ) {
			const designUrl =
				this.data.productDesignUrl ||
				`${
					window.location.origin
				}/wp-json/overcustomise/v1/product-design/${
					this.data.productId || 0
				}`;
			const url = new URL( designUrl, window.location.origin );
			url.searchParams.set( 'variant_id', key );

			try {
				const response = await fetch( url.toString(), {
					credentials: 'same-origin',
					headers: { Accept: 'application/json' },
				} );
				if ( ! response.ok ) {
					throw new Error(
						`Variation design request failed (${ response.status })`
					);
				}
				state = await response.json();
				this.productVariationStates[ key ] = state;
			} catch ( err ) {
				console.warn( '[OC] Variation design load failed:', err );
				return;
			}
		}

		if (
			requestSeq !== this._variationRequestSeq ||
			! state?.active ||
			! state?.panelHtml
		) {
			return;
		}

		await this.applyDesignState(
			state,
			state.selectedDesignVariant ||
				`design-${ state.designId || state.design_id }`,
			false
		);
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
				)
					.replace( /\r\n?/g, '\n' );
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
						opacity: 0.92,
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
		const raw = layer.type === 'text' ? normalisedText.trim() : normalisedText;
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

	extractSpotifyUri( inputValue ) {
		const raw = String( inputValue || '' ).trim();
		if ( ! raw ) {
			return '';
		}
		if ( /^spotify:[a-z]+:[A-Za-z0-9]+$/i.test( raw ) ) {
			return raw;
		}

		let parsed;
		try {
			parsed = new URL( raw );
		} catch ( e ) {
			return '';
		}

		const host = parsed.hostname.toLowerCase();
		if ( host !== 'open.spotify.com' && host !== 'play.spotify.com' ) {
			return '';
		}

		const parts = parsed.pathname
			.split( '/' )
			.filter( Boolean )
			.filter( ( p ) => ! /^intl-[a-z]{2}$/i.test( p ) );

		if ( ! parts.length ) {
			return '';
		}

		const validTypes = [
			'track',
			'album',
			'artist',
			'playlist',
			'episode',
			'show',
		];
		const typeIndex = parts.findIndex( ( p ) => validTypes.includes( p ) );
		if ( typeIndex < 0 || ! parts[ typeIndex + 1 ] ) {
			return '';
		}

		const type = parts[ typeIndex ];
		const id = parts[ typeIndex + 1 ];
		if ( ! /^[A-Za-z0-9]+$/.test( id ) ) {
			return '';
		}

		return `spotify:${ type }:${ id }`;
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
				text: '#5d3922',
				imageTint: '#5d3922',
				bg: '8A5A34',
				highlight: 'rgba(255,225,180,0.24)',
				brightness: -0.16,
				contrast: 0.2,
				opacity: 0.9,
			},
		};

		return palettes[ material ] || palettes.silver_metal;
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

	buildSpotifyCodeUrl( inputValue, isEngraving, engravingPalette = null ) {
		const spotifyUri = this.extractSpotifyUri( inputValue );
		if ( ! spotifyUri ) {
			return '';
		}

		// Official Spotify scannable-code endpoint.
		// Endpoint shape:
		// /uri/plain/{format}/{background-hex}/{bar-colour}/{size}/{spotify-uri}
		// We request SVG and then strip white in-canvas for transparent compositing.
		const format = 'svg';
		const bgHex = isEngraving ? engravingPalette?.bg || 'F5F2EF' : 'FFFFFF';
		const bar = isEngraving ? 'black' : 'black';
		const size = 640;

		return `https://scannables.scdn.co/uri/plain/${ format }/${ bgHex }/${ bar }/${ size }/${ spotifyUri }`;
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
			if ( nonRenderedContainers.includes( parent.localName.toLowerCase() ) ) {
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
							alpha: 1,
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
				img.set( { opacity: palette.opacity } );
			} else if ( isEngraving ) {
				const palette = engravingPalette || this.engravingPalette();
				img.set( {
					opacity: palette.opacity,
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
			const isSelected = option.dataset.ocFontOption === select.value;
			option.setAttribute(
				'aria-selected',
				isSelected ? 'true' : 'false'
			);
		} );
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

				const setOpen = ( isOpen ) => {
					combo.classList.toggle( 'oc-open', isOpen );
					input.setAttribute(
						'aria-expanded',
						isOpen ? 'true' : 'false'
					);
				};

				const filterOptions = () => {
					const query = input.value.trim().toLowerCase();
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
					filterOptions();
					setOpen( true );
				} );
				input.addEventListener( 'input', () => {
					filterOptions();
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
						setOpen( true );
						options.find( ( option ) => ! option.hidden )?.focus();
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
				document
					.querySelectorAll( '.oc-font-combobox.oc-open' )
					.forEach( ( combo ) => {
						if ( ! combo.contains( e.target ) ) {
							combo.classList.remove( 'oc-open' );
							combo
								.querySelector( '[data-oc-font-search]' )
								?.setAttribute( 'aria-expanded', 'false' );
						}
					} );
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

	setupDesignVariantOptions() {
		if ( ! this.designVariants.length ) {
			return;
		}

		document
			.querySelectorAll( '[data-oc-design-variant]' )
			.forEach( ( btn ) => {
				btn.addEventListener( 'click', () => {
					const variant = this.designVariants.find(
						( item ) => item.id === btn.dataset.ocDesignVariant
					);
					if (
						! variant ||
						variant.id === this.selectedDesignVariant
					) {
						return;
					}

					this.switchDesignVariant( variant.id );
				} );
			} );
	}

	async switchDesignVariant( variantId ) {
		const state = this.data.designVariantStates?.[ variantId ];
		if ( ! state?.panelHtml ) {
			return;
		}

		await this.applyDesignState( state, variantId, true );
	}

	async applyDesignState( state, variantId, preserveCurrentState = true ) {
		if ( ! state?.panelHtml ) {
			return;
		}

		if ( preserveCurrentState ) {
			this.syncInputsFromDOM();
			const currentState =
				this.data.designVariantStates?.[ this.selectedDesignVariant ];
			if ( currentState ) {
				currentState.layerInputs = JSON.parse(
					JSON.stringify( this.inputs || {} )
				);
			}
		}

		Object.values( this.canvases || {} ).forEach( ( canvas ) =>
			canvas?.dispose?.()
		);
		this.canvases = {};
		this._previewUrl = null;
		this.activeArea = 0;
		this.selectedDesignVariant = variantId;

		const currentPanel = document.getElementById( 'oc-customiser-panel' );
		if ( currentPanel ) {
			currentPanel.outerHTML = state.panelHtml;
		}

		this.data.designId = state.designId;
		this.data.designName = state.designName;
		this.data.flatRate = state.flatRate;
		this.data.areas = state.areas || [];
		this.data.layerInputs = state.layerInputs || {};
		this.data.clipartByLayer = state.clipartByLayer || {};
		this.data.clipartGroups = state.clipartGroups || [];
		this.data.designVariants = state.designVariants || this.designVariants;
		this.data.designVariantStates =
			state.designVariantStates || this.data.designVariantStates || {};
		this.data.selectedDesignVariant = variantId;

		this.areas = this.data.areas || [];
		this.designVariants = this.data.designVariants || [];
		this.layersById = {};
		this.areas.forEach( ( area ) =>
			( area.layers || [] ).forEach( ( layer ) => {
				this.layersById[ layer.id ] = layer;
			} )
		);
		this.inputs = {};
		Object.entries( this.data.layerInputs || {} ).forEach( ( [ k, v ] ) => {
			const layerId = parseInt( k, 10 );
			this.inputs[ layerId ] = { ...v };
			this.clampLayerInputValue( layerId );
		} );

		this.preflightRoot = document.getElementById( 'oc-preflight-messages' );
		this.mobileCartPreviewDialog = null;
		this.setupInputListeners();
		this.setupDesignVariantOptions();
		this.setupUploadZones();
		this.setupVariationGalleryHandoff();
		this.applyInputsToDOM();
		this.updateHiddenField();
		await this.initAllCanvases();
	}

	setupSpotifyModal() {
		const dialog = document.getElementById( 'oc-spotify-share-dialog' );
		if ( ! dialog ) {
			return;
		}

		document
			.querySelectorAll( '.oc-spotify-modal-trigger' )
			.forEach( ( trigger ) => {
				trigger.addEventListener( 'click', ( event ) => {
					event.preventDefault();
					event.stopPropagation();
					this.openSpotifyModal();
				} );
			} );

		dialog
			.querySelectorAll( '[data-oc-spotify-modal-close]' )
			.forEach( ( closeBtn ) => {
				closeBtn.addEventListener( 'click', () =>
					this.closeSpotifyModal()
				);
			} );

		dialog.addEventListener( 'click', ( event ) => {
			const rect = dialog.getBoundingClientRect();
			const inDialog =
				rect.top <= event.clientY &&
				event.clientY <= rect.top + rect.height &&
				rect.left <= event.clientX &&
				event.clientX <= rect.left + rect.width;

			if ( ! inDialog ) {
				this.closeSpotifyModal();
			}
		} );

		dialog.addEventListener( 'close', () => {
			dialog.classList.remove( 'is-visible' );
			document.body.style.overflow = '';
		} );
	}

	openSpotifyModal() {
		const dialog = document.getElementById( 'oc-spotify-share-dialog' );
		if ( ! dialog || dialog.open ) {
			return;
		}

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
		if ( ! dialog || ! dialog.open ) {
			return;
		}

		dialog.classList.remove( 'is-visible' );
		clearTimeout( this.spotifyModalCloseTimer );
		this.spotifyModalCloseTimer = setTimeout( () => {
			if ( dialog.open ) {
				dialog.close();
			}
			document.body.style.overflow = '';
		}, 300 );
	}

	filterClipart( layerId ) {
		const grid =
			document.querySelector(
				`.oc-clipart-grid[data-oc-clipart-grid="${ layerId }"]`
			) ||
			document
				.querySelector( `[data-oc-clipart-search="${ layerId }"]` )
				?.closest( '.oc-layer-body' )
				?.querySelector( '.oc-clipart-grid' );
		if ( ! grid ) {
			return;
		}

		const items = grid.querySelectorAll( '.oc-clipart-item' );
		const term = ( this.clipartSearchTerms[ layerId ] || '' )
			.toLowerCase()
			.trim();
		const category = this.clipartCategoryFilters[ layerId ] || '';
		let visibleCount = 0;

		items.forEach( ( btn ) => {
			const name = ( btn.title || '' ).toLowerCase();
			const groups = btn.dataset.ocClipartGroups
				? btn.dataset.ocClipartGroups.split( '||' ).filter( Boolean )
				: [];
			const matchesSearch = ! term || name.includes( term );
			const matchesCategory = ! category || groups.includes( category );
			const visible = matchesSearch && matchesCategory;
			btn.style.display = visible ? '' : 'none';
			if ( visible ) {
				visibleCount++;
			}
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

		this.refreshClipartCarousel( layerId );
	}

	setupClipartCarousels() {
		document
			.querySelectorAll( '[data-oc-clipart-carousel]' )
			.forEach( ( carousel ) => {
				const layerId = parseInt(
					carousel.dataset.ocClipartCarousel,
					10
				);
				const grid = carousel.querySelector(
					'.oc-clipart-grid--carousel'
				);
				if ( ! layerId || ! grid ) {
					return;
				}

				carousel
					.querySelector( '[data-oc-clipart-prev]' )
					?.addEventListener( 'click', () =>
						this.scrollClipartCarousel( layerId, -1 )
					);
				carousel
					.querySelector( '[data-oc-clipart-next]' )
					?.addEventListener( 'click', () =>
						this.scrollClipartCarousel( layerId, 1 )
					);
				grid.addEventListener(
					'scroll',
					() => this.updateClipartCarouselDots( layerId ),
					{ passive: true }
				);
				this.refreshClipartCarousel( layerId );
			} );
	}

	visibleClipartItems( grid ) {
		return Array.from( grid.querySelectorAll( '.oc-clipart-item' ) ).filter(
			( item ) => item.style.display !== 'none'
		);
	}

	clipartCarouselPageCount( grid ) {
		const visibleItems = this.visibleClipartItems( grid );
		if ( ! visibleItems.length || ! grid.clientWidth ) {
			return 1;
		}
		return Math.max( 1, Math.ceil( grid.scrollWidth / grid.clientWidth ) );
	}

	scrollClipartCarousel( layerId, direction ) {
		const grid = document.querySelector(
			`.oc-clipart-grid--carousel[data-oc-clipart-grid="${ layerId }"]`
		);
		if ( ! grid ) {
			return;
		}
		const page =
			Math.round( grid.scrollLeft / Math.max( 1, grid.clientWidth ) ) +
			direction;
		const maxPage = this.clipartCarouselPageCount( grid ) - 1;
		grid.scrollTo( {
			left: Math.max( 0, Math.min( maxPage, page ) ) * grid.clientWidth,
			behavior: 'smooth',
		} );
	}

	refreshClipartCarousel( layerId ) {
		const carousel = document.querySelector(
			`[data-oc-clipart-carousel="${ layerId }"]`
		);
		const grid = carousel?.querySelector( '.oc-clipart-grid--carousel' );
		const dots = carousel?.querySelector( '[data-oc-clipart-dots]' );
		if ( ! carousel || ! grid || ! dots ) {
			return;
		}

		const pageCount = this.clipartCarouselPageCount( grid );
		const maxLeft = Math.max( 0, ( pageCount - 1 ) * grid.clientWidth );
		if ( grid.scrollLeft > maxLeft ) {
			grid.scrollLeft = maxLeft;
		}
		dots.innerHTML = '';
		for ( let i = 0; i < pageCount; i++ ) {
			const dot = document.createElement( 'button' );
			dot.type = 'button';
			dot.className = 'oc-clipart-carousel-dot';
			dot.setAttribute( 'aria-label', `Go to clipart page ${ i + 1 }` );
			dot.addEventListener( 'click', () =>
				grid.scrollTo( {
					left: i * grid.clientWidth,
					behavior: 'smooth',
				} )
			);
			dots.appendChild( dot );
		}

		carousel.classList.toggle(
			'oc-clipart-carousel--single-page',
			pageCount <= 1
		);
		this.updateClipartCarouselDots( layerId );
	}

	updateClipartCarouselDots( layerId ) {
		const carousel = document.querySelector(
			`[data-oc-clipart-carousel="${ layerId }"]`
		);
		const grid = carousel?.querySelector( '.oc-clipart-grid--carousel' );
		if ( ! carousel || ! grid ) {
			return;
		}

		const pageCount = this.clipartCarouselPageCount( grid );
		const page = Math.max(
			0,
			Math.min(
				pageCount - 1,
				Math.round( grid.scrollLeft / Math.max( 1, grid.clientWidth ) )
			)
		);
		carousel
			.querySelectorAll( '.oc-clipart-carousel-dot' )
			.forEach( ( dot, i ) => {
				dot.classList.toggle( 'oc-active', i === page );
				dot.setAttribute(
					'aria-current',
					i === page ? 'true' : 'false'
				);
			} );
		carousel
			.querySelector( '[data-oc-clipart-prev]' )
			?.toggleAttribute( 'disabled', page <= 0 );
		carousel
			.querySelector( '[data-oc-clipart-next]' )
			?.toggleAttribute( 'disabled', page >= pageCount - 1 );
	}

	setSpotifyError( layerId, message, inputEl = null ) {
		const msg = String( message || '' );
		const el = document.querySelector(
			`[data-oc-spotify-error="${ layerId }"]`
		);
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

	getLayerInputEl( layer ) {
		if ( ! layer?.id ) {
			return null;
		}
		switch ( layer.type ) {
			case 'text':
			case 'textarea':
				return document.querySelector(
					`[data-oc-layer-text="${ layer.id }"]`
				);
			case 'spotify':
				return document.querySelector(
					`[data-oc-layer-spotify="${ layer.id }"]`
				);
			case 'image':
			case 'clipmask':
				return document.querySelector(
					`[data-oc-upload-zone="${ layer.id }"]`
				);
			case 'clipart':
				return document.querySelector(
					`[data-oc-layer-clipart="${ layer.id }"]`
				);
			default:
				return null;
		}
	}

	clearPreflightMessages() {
		if ( this.preflightRoot ) {
			this.preflightRoot.innerHTML = '';
			this.preflightRoot.hidden = true;
		}

		document
			.querySelectorAll( '.oc-preflight-field-error' )
			.forEach( ( el ) => {
				el.classList.remove( 'oc-preflight-field-error' );
			} );

		document
			.querySelectorAll( '[data-oc-layer-text], [data-oc-layer-spotify]' )
			.forEach( ( el ) => {
				el.setCustomValidity( '' );
				el.setAttribute( 'aria-invalid', 'false' );
			} );
	}

	renderPreflightMessages( errors = [], warnings = [] ) {
		if ( ! this.preflightRoot ) {
			return;
		}

		if ( ! errors.length && ! warnings.length ) {
			this.clearPreflightMessages();
			return;
		}

		const box = document.createElement( 'div' );
		box.className = 'oc-preflight-box';
		box.setAttribute( 'role', 'alert' );
		box.setAttribute( 'aria-live', 'assertive' );

		const appendTitle = ( text ) => {
			const title = document.createElement( 'p' );
			title.className = 'oc-preflight-title';
			title.textContent = text;
			box.appendChild( title );
		};

		const appendList = ( items, cls ) => {
			if ( ! items.length ) {
				return;
			}
			const list = document.createElement( 'ul' );
			list.className = cls;
			items.forEach( ( msg ) => {
				const item = document.createElement( 'li' );
				item.textContent = String( msg );
				list.appendChild( item );
			} );
			box.appendChild( list );
		};

		this.preflightRoot.innerHTML = '';
		if ( errors.length ) {
			appendTitle( 'Please fix these issues before checkout:' );
			appendList( errors, 'oc-preflight-errors' );
		}
		if ( warnings.length ) {
			appendTitle( 'Quality warnings:' );
			appendList( warnings, 'oc-preflight-warnings' );
		}
		this.preflightRoot.appendChild( box );

		this.preflightRoot.hidden = false;
		this.preflightRoot.scrollIntoView( {
			behavior: 'smooth',
			block: 'start',
		} );
	}

	async getImageMeta( url ) {
		if ( ! url ) {
			return null;
		}

		return new Promise( ( resolve ) => {
			const img = new Image();
			img.onload = () =>
				resolve( {
					width: img.naturalWidth || 0,
					height: img.naturalHeight || 0,
				} );
			img.onerror = () => resolve( null );
			img.src = url;
		} );
	}

	async runPreflight() {
		this.clearPreflightMessages();

		const errors = [];
		const warnings = [];
		const spotifyValidated = new Set();
		const invalidSpotifyStatuses = [
			'invalid_format',
			'playlist_private_or_invalid',
			'invalid_or_unavailable',
			'unreachable',
			'rate_limited',
		];

		for ( const area of this.areas ) {
			for ( const layer of area.layers || [] ) {
				if ( layer.locked ) {
					continue;
				} // Locked layers skip preflight validation
				const input = this.inputs[ layer.id ] || {};
				const settings = layer.settings || {};
				const required = Boolean( settings.required );
				const label = layer.label || layer.type;
				const fieldEl = this.getLayerInputEl( layer );
				let value = '';

				switch ( layer.type ) {
					case 'text':
					case 'textarea':
						value = String( input.value || '' ).trim();
						if ( required && ! value ) {
							errors.push( `${ label } is required.` );
							fieldEl?.classList.add(
								'oc-preflight-field-error'
							);
							if ( fieldEl ) {
								fieldEl.setCustomValidity(
									'This field is required.'
								);
								fieldEl.setAttribute( 'aria-invalid', 'true' );
							}
						}
						if ( value ) {
							const charLimit =
								parseInt( settings.char_limit, 10 ) || 0;
							if (
								charLimit > 0 &&
								this.textLength( value ) > charLimit
							) {
								errors.push(
									`${ label } exceeds the ${ charLimit } character limit.`
								);
								fieldEl?.classList.add(
									'oc-preflight-field-error'
								);
								if ( fieldEl ) {
									fieldEl.setCustomValidity(
										`Maximum ${ charLimit } characters.`
									);
									fieldEl.setAttribute(
										'aria-invalid',
										'true'
									);
								}
							}
						}
						break;

					case 'image':
					case 'clipmask':
						if ( required && ! input.attachmentId ) {
							errors.push(
								`${ label } needs an uploaded image.`
							);
							fieldEl?.classList.add(
								'oc-preflight-field-error'
							);
						}
						if ( input.attachmentUrl ) {
							let imageMeta = input.imageMeta || null;
							if ( ! imageMeta ) {
								imageMeta = await this.getImageMeta(
									input.attachmentUrl
								);
								if ( imageMeta && this.inputs[ layer.id ] ) {
									this.inputs[ layer.id ].imageMeta =
										imageMeta;
								}
							}
							if (
								imageMeta &&
								imageMeta.width > 0 &&
								imageMeta.height > 0
							) {
								if (
									imageMeta.width < layer.w ||
									imageMeta.height < layer.h
								) {
									warnings.push(
										`${ label } may print soft (${ imageMeta.width }x${ imageMeta.height }px for a ${ layer.w }x${ layer.h }px print area).`
									);
								}
							}
						}
						break;

					case 'clipart':
						if ( required && ! input.clipartId ) {
							errors.push(
								`${ label } requires a clipart selection.`
							);
							fieldEl?.classList.add(
								'oc-preflight-field-error'
							);
						}
						break;

					case 'lineart':
						value = String( input.colorHex || '' ).trim();
						if ( required && ! value ) {
							errors.push(
								`${ label } requires a line-art colour.`
							);
							fieldEl?.classList.add(
								'oc-preflight-field-error'
							);
							if ( fieldEl ) {
								fieldEl.setCustomValidity(
									'Please choose a line-art colour.'
								);
								fieldEl.setAttribute( 'aria-invalid', 'true' );
							}
						}
						break;

					case 'spotify':
						value = String( input.value || '' ).trim();
						if ( required && ! value ) {
							errors.push(
								`${ label } requires a Spotify link.`
							);
							fieldEl?.classList.add(
								'oc-preflight-field-error'
							);
							if ( fieldEl ) {
								fieldEl.setCustomValidity(
									'Please provide a Spotify link.'
								);
								fieldEl.setAttribute( 'aria-invalid', 'true' );
							}
							break;
						}

						if ( value && ! spotifyValidated.has( layer.id ) ) {
							await this.validateSpotifyLayer(
								layer.id,
								value,
								fieldEl
							);
							spotifyValidated.add( layer.id );
						}

						if ( value ) {
							const status = String(
								this.inputs[ layer.id ]?.spotifyStatus || ''
							);
							if ( invalidSpotifyStatuses.includes( status ) ) {
								errors.push(
									`${ label } has an invalid or unavailable Spotify link.`
								);
								fieldEl?.classList.add(
									'oc-preflight-field-error'
								);
								if ( fieldEl ) {
									fieldEl.setCustomValidity(
										'Spotify link is invalid or unavailable.'
									);
									fieldEl.setAttribute(
										'aria-invalid',
										'true'
									);
								}
							}
						}
						break;
				}
			}
		}

		return { errors, warnings, ok: errors.length === 0 };
	}

	runImmediateBlockingPreflight() {
		this.clearPreflightMessages();

		const errors = [];

		for ( const area of this.areas ) {
			for ( const layer of area.layers || [] ) {
				if ( layer.locked ) {
					continue;
				}

				const input = this.inputs[ layer.id ] || {};
				const settings = layer.settings || {};
				const label = layer.label || layer.type;
				const fieldEl = this.getLayerInputEl( layer );

				if ( ! settings.required ) {
					continue;
				}

				let filled = true;
				switch ( layer.type ) {
					case 'text':
					case 'textarea':
					case 'spotify':
						filled = String( input.value || '' ).trim() !== '';
						break;

					case 'image':
					case 'clipmask':
						filled = Boolean( input.attachmentId );
						break;

					case 'clipart':
						filled = Boolean( input.clipartId );
						break;

					default:
						filled = true;
				}

				if ( ! filled ) {
					errors.push( `${ label } is required.` );
					fieldEl?.classList.add( 'oc-preflight-field-error' );
					if ( fieldEl ) {
						fieldEl.setCustomValidity( 'This field is required.' );
						fieldEl.setAttribute( 'aria-invalid', 'true' );
					}
				}
			}
		}

		return { errors, warnings: [], ok: errors.length === 0 };
	}

	async validateSpotifyLayer( layerId, rawValue, inputEl = null ) {
		const value = String( rawValue || '' ).trim();
		if ( ! this.inputs[ layerId ] ) {
			this.inputs[ layerId ] = {};
		}
		const token = ( this.spotifyValidateTokens[ layerId ] || 0 ) + 1;
		this.spotifyValidateTokens[ layerId ] = token;

		if ( ! value ) {
			this.inputs[ layerId ].spotifyStatus = '';
			this.inputs[ layerId ].spotifyUri = '';
			this.syncLinkedLayerInput( layerId, [
				'value',
				'spotifyStatus',
				'spotifyUri',
			] );
			this.setSpotifyError( layerId, '', inputEl );
			this.scheduleRedraw( this.areaIndexForLayer( layerId ) );
			this.updateHiddenField();
			return;
		}

		const localUri = this.extractSpotifyUri( value );
		if ( ! localUri ) {
			this.inputs[ layerId ].spotifyStatus = 'invalid_format';
			this.inputs[ layerId ].spotifyUri = '';
			this.syncLinkedLayerInput( layerId, [
				'value',
				'spotifyStatus',
				'spotifyUri',
			] );
			this.setSpotifyError(
				layerId,
				'Invalid Spotify link format.',
				inputEl
			);
			this.scheduleRedraw( this.areaIndexForLayer( layerId ) );
			this.updateHiddenField();
			return;
		}

		if ( ! this.data.validateSpotifyUrl ) {
			this.inputs[ layerId ].spotifyStatus = 'ok';
			this.inputs[ layerId ].spotifyUri = localUri;
			this.syncLinkedLayerInput( layerId, [
				'value',
				'spotifyStatus',
				'spotifyUri',
			] );
			this.setSpotifyError( layerId, '', inputEl );
			this.scheduleRedraw( this.areaIndexForLayer( layerId ) );
			this.updateHiddenField();
			return;
		}

		try {
			const res = await fetch( this.data.validateSpotifyUrl, {
				method: 'POST',
				headers: this.restHeaders( {
					'Content-Type': 'application/json',
				} ),
				body: JSON.stringify( { url: value } ),
			} );
			const isJson = res.headers
				.get( 'content-type' )
				?.includes( 'application/json' );
			let json = null;
			let text = '';
			if ( isJson ) {
				try {
					json = await res.json();
				} catch ( err ) {
					console.warn(
						'[OC] Spotify validation JSON parse failed:',
						err
					);
				}
			} else {
				text = await res.text();
			}
			if ( this.spotifyValidateTokens[ layerId ] !== token ) {
				return;
			}
			if ( ! res.ok ) {
				const statusReason =
					json?.code === 'rate_limited' || res.status === 429
						? 'rate_limited'
						: 'unreachable';
				const statusMessage =
					json?.message ||
					text ||
					'Could not validate Spotify right now. Please try again.';
				this.inputs[ layerId ].spotifyStatus = statusReason;
				this.inputs[ layerId ].spotifyUri = '';
				this.syncLinkedLayerInput( layerId, [
					'value',
					'spotifyStatus',
					'spotifyUri',
				] );
				this.setSpotifyError( layerId, statusMessage, inputEl );
				this.scheduleRedraw( this.areaIndexForLayer( layerId ) );
				this.updateHiddenField();
				return;
			}

			if ( ! json ) {
				this.inputs[ layerId ].spotifyStatus = 'unreachable';
				this.inputs[ layerId ].spotifyUri = '';
				this.syncLinkedLayerInput( layerId, [
					'value',
					'spotifyStatus',
					'spotifyUri',
				] );
				this.setSpotifyError(
					layerId,
					'Could not validate Spotify right now. Please try again.',
					inputEl
				);
				this.scheduleRedraw( this.areaIndexForLayer( layerId ) );
				this.updateHiddenField();
				return;
			}

			const valid = Boolean( json?.valid );

			if ( valid ) {
				this.inputs[ layerId ].spotifyStatus = 'ok';
				this.inputs[ layerId ].spotifyUri = json.spotifyUri || localUri;
				this.setSpotifyError( layerId, '', inputEl );
			} else {
				this.inputs[ layerId ].spotifyStatus =
					json?.reason || 'invalid_or_unavailable';
				this.inputs[ layerId ].spotifyUri = '';
				this.setSpotifyError(
					layerId,
					json?.message || 'Spotify link is invalid or unavailable.',
					inputEl
				);
			}
		} catch ( e ) {
			if ( this.spotifyValidateTokens[ layerId ] !== token ) {
				return;
			}
			this.inputs[ layerId ].spotifyStatus = 'unreachable';
			this.inputs[ layerId ].spotifyUri = '';
			this.setSpotifyError(
				layerId,
				'Could not validate Spotify right now. Please try again.',
				inputEl
			);
		}

		this.syncLinkedLayerInput( layerId, [
			'value',
			'spotifyStatus',
			'spotifyUri',
		] );
		this.scheduleRedraw( this.areaIndexForLayer( layerId ) );
		this.updateHiddenField();
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

	setupFormSubmit() {
		if ( this.formSubmitBound ) {
			return;
		}
		const form = document.querySelector( 'form.cart' );
		if ( ! form ) {
			return;
		}
		this.formSubmitBound = true;

		if ( this.editMode ) {
			form.addEventListener(
				'submit',
				async ( e ) => {
					e.preventDefault();
					e.stopImmediatePropagation();
					this.syncInputsFromDOM();
					await this.flushRedraw();

					const preflight = await this.runPreflight();
					this.renderPreflightMessages(
						preflight.errors,
						preflight.warnings
					);
					if ( ! preflight.ok ) {
						return;
					}

					if ( preflight.warnings.length ) {
						const proceed = window.confirm(
							'We found quality warnings that may affect print output. Press OK to continue, or Cancel to review.'
						);
						if ( ! proceed ) {
							return;
						}
					}

					await this.uploadPreview();
					this.updateHiddenField();

					const layers = {};
					this.areas.forEach( ( area ) => {
						( area.layers || [] ).forEach( ( layer ) => {
							const inp = this.inputs[ layer.id ];
							if ( inp ) {
								layers[ layer.id ] = {
									type: layer.type,
									...inp,
								};
							}
						} );
					} );
					const snapshots = await this.captureAreaSnapshots();

					try {
						const res = await fetch( this.data.updateCartItemUrl, {
							method: 'POST',
							headers: this.restHeaders( {
								'Content-Type': 'application/json',
							} ),
							body: JSON.stringify( {
								cart_key: this.cartKey,
								designId: this.data.designId,
								layers,
								snapshots,
								previewUrl: this._previewUrl || '',
							} ),
						} );
						let json = null;
						const isJson = res.headers
							.get( 'content-type' )
							?.includes( 'application/json' );
						if ( isJson ) {
							try {
								json = await res.json();
							} catch ( err ) {
								console.warn(
									'[OC] Cart update response parse failed:',
									err
								);
							}
						}

						if ( ! res.ok ) {
							this.renderPreflightMessages(
								[
									json?.message ||
										'Failed to update customisation.',
								],
								[]
							);
							return;
						}

						if ( json?.success ) {
							window.location.href =
								window.wc_cart_params?.cart_url || '/cart/';
						} else {
							this.renderPreflightMessages(
								[
									json?.message ||
										'Failed to update customisation.',
								],
								[]
							);
						}
					} catch ( err ) {
						console.error( '[OC] Update cart item failed:', err );
						this.renderPreflightMessages(
							[
								'Failed to update customisation. Please try again.',
							],
							[]
						);
					}
				},
				true
			);
			return;
		}

		form.querySelectorAll(
			'[type="submit"], .single_add_to_cart_button'
		).forEach( ( button ) => {
			button.addEventListener(
				'click',
				( e ) => {
					if ( form._ocSubmitReady ) {
						return;
					}

					e.preventDefault();
					e.stopImmediatePropagation();

					this.syncInputsFromDOM();
					const preflight = this.runImmediateBlockingPreflight();
					if ( preflight.ok ) {
						if ( form.requestSubmit ) {
							form.requestSubmit( button );
						} else {
							form.dispatchEvent(
								new Event( 'submit', {
									bubbles: true,
									cancelable: true,
								} )
							);
						}
						return;
					}

					this.resetCartSubmitState( form );
					this.renderPreflightMessages(
						preflight.errors,
						preflight.warnings
					);
				},
				true
			);
		} );

		form.addEventListener(
			'submit',
			async ( e ) => {
				if (
					this.mobileCartPreviewDismissedAt &&
					Date.now() - this.mobileCartPreviewDismissedAt < 750
				) {
					e.preventDefault();
					e.stopImmediatePropagation();
					this.resetCartSubmitState( form );
					return;
				}

				if ( form._ocSubmitReady ) {
					form._ocSubmitReady = false;
					return; // preview already saved — let submit through
				}
				e.preventDefault();
				e.stopImmediatePropagation();
				this.syncInputsFromDOM();
				await this.flushRedraw();

				const preflight = await this.runPreflight();
				this.renderPreflightMessages(
					preflight.errors,
					preflight.warnings
				);
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
				await this.updateHiddenField( true );
				form._ocSubmitReady = true;
				// requestSubmit() re-triggers HTML5 validation before submitting.
				if ( form.requestSubmit ) {
					const submitter =
						form.querySelector( '[type="submit"]' ) || undefined;
					form.requestSubmit( submitter );
				} else {
					form.submit();
				}
			},
			true
		);
	}

	resetCartSubmitState( form ) {
		form.classList.remove( 'loading', 'processing' );
		form.querySelectorAll(
			'[type="submit"], .single_add_to_cart_button'
		).forEach( ( button ) => {
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

		const panel =
			document.getElementById( 'oc-customiser-panel' ) || document.body;
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
			const acceptBtn = dialog.querySelector(
				'[data-oc-cart-preview-accept]'
			);
			const changeBtn = dialog.querySelector(
				'[data-oc-cart-preview-change]'
			);
			const previousFocus = dialog.ownerDocument.activeElement;

			const finish = ( accepted ) => {
				if ( ! accepted ) {
					this.mobileCartPreviewDismissedAt = Date.now();
				}

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

			const stopModalAction = ( event ) => {
				event?.preventDefault?.();
				event?.stopPropagation?.();
				event?.stopImmediatePropagation?.();
			};

			const onAccept = ( event ) => {
				stopModalAction( event );
				finish( true );
			};
			const onChange = ( event ) => {
				stopModalAction( event );
				finish( false );
			};
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
			window.requestAnimationFrame( () =>
				dialog.classList.add( 'is-visible' )
			);
			acceptBtn?.focus?.();
		} );
	}

	async uploadPreview() {
		if ( ! this.data.savePreviewUrl ) {
			return;
		}
		await this.flushRedraw();

		let dataUrl;
		try {
			dataUrl = this.getCurrentPreviewDataUrl();
		} catch ( e ) {
			this._previewUrl = '';
			this.updateHiddenField();
			console.warn(
				'[OC] Could not capture preview for cart:',
				e.message
			);
			return;
		}

		try {
			const res = await fetch( this.data.savePreviewUrl, {
				method: 'POST',
				headers: this.restHeaders( {
					'Content-Type': 'application/json',
				} ),
				body: JSON.stringify( { image: dataUrl } ),
			} );
			if ( ! res.ok ) {
				this._previewUrl = '';
				this.updateHiddenField();
				return;
			}
			let json = null;
			const isJson = res.headers
				.get( 'content-type' )
				?.includes( 'application/json' );
			if ( isJson ) {
				try {
					json = await res.json();
				} catch ( err ) {
					console.warn(
						'[OC] Preview upload JSON parse failed:',
						err
					);
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

	// ── Uppy upload zones ────────────────────────────────────────────────────────

	setupUploadZones() {
		document
			.querySelectorAll( '[data-oc-upload-zone]' )
			.forEach( ( zoneEl ) => {
				const lid = parseInt( zoneEl.dataset.ocUploadZone, 10 );
				if ( ! lid ) {
					return;
				}
				const uploadUrl = this.data?.uploadUrl || '';
				if ( ! uploadUrl ) {
					this.showUploadError(
						zoneEl,
						'Uploads are unavailable right now.'
					);
					return;
				}

				// Find the layer's per-layer settings; fall back to global defaults.
				let layer = null;
				for ( const area of this.areas ) {
					layer = ( area.layers || [] ).find( ( l ) => l.id === lid );
					if ( layer ) {
						break;
					}
				}
				if ( ! layer ) {
					console.warn(
						'[OC] Upload zone has no matching layer:',
						lid
					);
					return;
				}
				const layerFormats = Array.isArray( layer?.settings?.formats )
					? layer.settings.formats
					: [];
				const globalFormats = Array.isArray( this.data.allowedFormats )
					? this.data.allowedFormats
					: [];
				const serverFormats = [
					'jpg',
					'jpeg',
					'png',
					'svg',
					'pdf',
					'eps',
					'webp',
				];
				const effective = (
					layerFormats.length ? layerFormats : globalFormats
				)
					.map( ( f ) =>
						String( f ).toLowerCase().replace( /^\./, '' )
					)
					.filter( ( ext ) => serverFormats.includes( ext ) );
				const allowedExt = effective.length
					? effective.map( ( ext ) => `.${ ext }` )
					: [ '.jpg', '.jpeg', '.png', '.svg', '.pdf', '.webp' ];

				const layerMaxMb = parseInt( layer?.settings?.max_size_mb, 10 );
				const globalMaxMb = parseInt( this.data.maxUploadSizeMb, 10 );
				const maxMb =
					layerMaxMb > 0
						? layerMaxMb
						: globalMaxMb > 0
						? globalMaxMb
						: 10;

				const uppy = new Uppy( {
					autoProceed: true,
					onBeforeFileAdded: () => {
						uppy.getFiles().forEach( ( existingFile ) =>
							uppy.removeFile( existingFile.id )
						);
						this.setUploadZoneState( zoneEl, '' );
						const warnEl = document.querySelector(
							`.oc-resolution-warning[data-oc-resolution-warning="${ lid }"]`
						);
						if ( warnEl ) {
							warnEl.style.display = 'none';
						}
						this.setUploadProgress(
							zoneEl,
							0,
							'Starting upload...'
						);
						this.showUploadError( zoneEl, '' );
						return true;
					},
					restrictions: {
						maxNumberOfFiles: 1,
						maxFileSize: maxMb * 1024 * 1024,
						allowedFileTypes: allowedExt,
					},
				} );
				uppy.use( DragDrop, {
					target: zoneEl,
					note:
						'We accept ' +
						( allowedExt.length
							? allowedExt
									.map( ( e ) =>
										e.replace( '.', '' ).toUpperCase()
									)
									.join( ', ' )
							: 'JPG, PNG, PDF, EPS' ) +
						' and other common image types.',
					locale: {
						strings: {
							dropHereOr: '%{browse}',
							browse: 'Tap / click here to upload your image',
						},
					},
				} );
				uppy.use( XHRUpload, {
					endpoint: this.uploadEndpoint( uploadUrl, lid ),
					formData: true,
					fieldName: 'artwork',
				} );

				uppy.on( 'upload-progress', ( file, progress ) => {
					const percent = progress?.bytesTotal
						? Math.round(
								( progress.bytesUploaded /
									progress.bytesTotal ) *
									100
						  )
						: 0;
					this.setUploadProgress(
						zoneEl,
						percent,
						`Uploading ${ percent }%`
					);
				} );

				uppy.on( 'upload-success', async ( file, res ) => {
					this.setUploadProgress( zoneEl, 100, '' );
					if ( ! res?.body ) {
						this.setUploadZoneState( zoneEl, 'error' );
						this.showUploadError(
							zoneEl,
							'Upload succeeded but server returned no data.'
						);
						return;
					}
					if ( ! this.inputs[ lid ] ) {
						this.inputs[ lid ] = {};
					}
					this.inputs[ lid ].attachmentId =
						res.body.attachment_id || 0;
					this.inputs[ lid ].attachmentUrl =
						res.body.preview_url || '';
					this.inputs[ lid ].imageMeta = null;
					if ( ! this.inputs[ lid ].attachmentUrl ) {
						this.setUploadZoneState( zoneEl, 'error' );
						this.showUploadError(
							zoneEl,
							'Server did not return a preview URL.'
						);
						return;
					}
					const meta = await this.getImageMeta(
						this.inputs[ lid ].attachmentUrl
					);
					if ( meta && this.inputs[ lid ] ) {
						this.inputs[ lid ].imageMeta = meta;
						const thresholdW = Math.round( layer.w * ( 300 / 72 ) );
						const thresholdH = Math.round( layer.h * ( 300 / 72 ) );
						const warnEl = document.querySelector(
							`.oc-resolution-warning[data-oc-resolution-warning="${ lid }"]`
						);
						if ( warnEl ) {
							const belowThreshold =
								meta.width < thresholdW ||
								meta.height < thresholdH;
							const belowHalf =
								meta.width < thresholdW * 0.5 ||
								meta.height < thresholdH * 0.5;
							if ( belowHalf ) {
								warnEl.className =
									'oc-resolution-warning oc-res-error';
								warnEl.textContent = `This image is too low resolution for quality printing. Minimum required: ${ thresholdW } x ${ thresholdH } pixels.`;
								warnEl.style.display = '';
								this.inputs[ lid ].attachmentId = 0;
								this.inputs[ lid ].attachmentUrl = '';
								this.inputs[ lid ].imageMeta = null;
								this.syncLinkedLayerInput( lid, [
									'attachmentId',
									'attachmentUrl',
									'imageMeta',
								] );
								this.setUploadZoneState( zoneEl, 'error' );
								this.showUploadError(
									zoneEl,
									'Image resolution too low. Please upload a higher resolution image.'
								);
								this.scheduleRedraw(
									this.areaIndexForLayer( lid )
								);
								this.updateHiddenField();
								return;
							} else if ( belowThreshold ) {
								warnEl.className =
									'oc-resolution-warning oc-res-warning';
								warnEl.textContent = `This image may not print clearly at full size. Recommended minimum: ${ thresholdW } x ${ thresholdH } pixels.`;
								warnEl.style.display = '';
							} else {
								warnEl.style.display = 'none';
							}
						}
					}
					this.setUploadZoneState( zoneEl, 'uploaded' );
					this.syncLinkedLayerInput( lid, [
						'attachmentId',
						'attachmentUrl',
						'imageMeta',
					] );
					this.requestPreviewFocus();
					this.scheduleRedraw( this.areaIndexForLayer( lid ) );
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
					const msg =
						responseBody?.message ||
						error?.message ||
						'Upload failed.';
					console.warn( '[OC] Upload error:', msg, response );
					this.setUploadZoneState( zoneEl, 'error' );
					this.setUploadProgress( zoneEl, 0, '' );
					this.showUploadError( zoneEl, msg );
				} );
				uppy.on( 'restriction-failed', ( file, error ) => {
					this.setUploadZoneState( zoneEl, 'error' );
					this.setUploadProgress( zoneEl, 0, '' );
					this.showUploadError(
						zoneEl,
						error?.message || 'File not allowed.'
					);
				} );
			} );
	}

	setUploadZoneState( zoneEl, state ) {
		zoneEl.classList.toggle(
			'oc-upload-zone--uploaded',
			state === 'uploaded'
		);
		zoneEl.classList.toggle( 'oc-upload-zone--error', state === 'error' );

		const browse = zoneEl.querySelector( '.uppy-DragDrop-browse' );
		const note = zoneEl.querySelector( '.uppy-DragDrop-note' );
		if ( browse ) {
			browse.textContent =
				state === 'uploaded'
					? 'Image uploaded'
					: 'Tap / click here to upload your image';
		}
		if ( note ) {
			if ( ! note.dataset.ocOriginalText ) {
				note.dataset.ocOriginalText = note.textContent;
			}
			note.textContent =
				state === 'uploaded'
					? 'Click to replace image'
					: note.dataset.ocOriginalText || note.textContent;
		}
	}

	setUploadProgress( zoneEl, percent, label ) {
		const wrap = zoneEl.closest( '.oc-artwork-wrap' );
		if ( ! wrap ) {
			return;
		}
		let progressEl = wrap.querySelector( '.oc-upload-progress' );
		if ( ! progressEl ) {
			progressEl = document.createElement( 'div' );
			progressEl.className = 'oc-upload-progress';
			progressEl.innerHTML =
				'<div class="oc-upload-progress-label"></div><div class="oc-upload-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><div class="oc-upload-progress-bar"></div></div>';
			zoneEl.insertAdjacentElement( 'afterend', progressEl );
		}

		const safePercent = Math.max(
			0,
			Math.min( 100, parseInt( percent, 10 ) || 0 )
		);
		const labelEl = progressEl.querySelector( '.oc-upload-progress-label' );
		const track = progressEl.querySelector( '.oc-upload-progress-track' );
		const bar = progressEl.querySelector( '.oc-upload-progress-bar' );

		if ( labelEl ) {
			labelEl.textContent = label || '';
		}
		if ( track ) {
			track.setAttribute( 'aria-valuenow', String( safePercent ) );
		}
		if ( bar ) {
			bar.style.width = `${ safePercent }%`;
		}
		progressEl.style.display = label ? '' : 'none';
	}

	showUploadError( zoneEl, message ) {
		const wrap = zoneEl.closest( '.oc-artwork-wrap' );
		if ( ! wrap ) {
			return;
		}
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
