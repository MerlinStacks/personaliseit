/**
 * Product gallery preview integration for the frontend customiser.
 */

/* eslint-disable no-console, no-undef */

const GALLERY_IMAGE_SELECTORS = [
	// True Video Product Gallery (Swiper): prefer active non-video slide.
	'.tvpg-main-slider .swiper-slide-active:not(.tvpg-video-slide) .woocommerce-product-gallery__image img',
	'.tvpg-main-slider .swiper-slide-active .woocommerce-product-gallery__image img',
	'.tvpg-main-slider .swiper-slide:not(.tvpg-video-slide) .woocommerce-product-gallery__image img',
	// Flatsome theme.
	'.product-gallery-slider .flickity-slider .woocommerce-product-gallery__image.is-selected img',
	'.product-gallery-slider .flickity-slider .slide.is-selected img',
	'.product-gallery-slider .is-selected img',
	'.product-gallery-slider .flickity-slider .woocommerce-product-gallery__image:first-child img',
	'.product-gallery-slider .flickity-slider .slide:first-child img',
	'.product-gallery .woocommerce-product-gallery__image:first-child img',
	'.product-images .woocommerce-product-gallery__image:first-child a img',
	'.product-image-wrap .woocommerce-product-gallery__image:first-child img',
	// WC Blocks.
	'.wp-block-woocommerce-product-image-gallery .woocommerce-product-gallery__image:first-child img',
	// Default WC / Storefront.
	'.woocommerce-product-gallery__image:first-child a img',
	'.woocommerce-product-gallery__image:first-child img',
	// Broad fallback.
	'.woocommerce-product-gallery .wp-post-image',
	'.wp-post-image',
];

const galleryPreviewMethods = {
	captureGalleryNodeState( node ) {
		if ( ! node || node._ocOriginalPreviewState ) {
			return;
		}
		node._ocOriginalPreviewState = {
			attributes: Array.from( node.attributes || [] ).map(
				( attribute ) => [ attribute.name, attribute.value ]
			),
		};
	},

	restoreProductGallery() {
		this._galleryPreviewGeneration += 1;
		document
			.querySelectorAll(
				'.oc-live-preview-slide, .oc-live-preview-thumb-slide'
			)
			.forEach( ( slide ) => slide.remove() );
		document.querySelectorAll( '*' ).forEach( ( node ) => {
			const state = node._ocOriginalPreviewState;
			if ( ! state ) {
				return;
			}
			Array.from( node.attributes ).forEach( ( attribute ) =>
				node.removeAttribute( attribute.name )
			);
			state.attributes.forEach( ( [ name, value ] ) =>
				node.setAttribute( name, value )
			);
			delete node._ocOriginalPreviewState;
		} );
		this.releaseTVPGPreviewLock( true );
		this.setPanelPreviewHandoff( false );
		this.findGalleryImage();
		document.querySelector( '.tvpg-main-slider' )?.swiper?.update?.();
		document.querySelector( '.tvpg-thumb-slider' )?.swiper?.update?.();
		this.refreshFlatsomeGallery();
	},

	findGalleryImage() {
		for ( const sel of GALLERY_IMAGE_SELECTORS ) {
			const img = document.querySelector( sel );
			if ( img ) {
				this.galleryImg = img;
				return;
			}
		}
		this.galleryImg = null;
	},

	applyPreviewToImage( img, dataUrl, dimensions = null ) {
		if ( ! img ) {
			return;
		}
		this.captureGalleryNodeState( img );
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
			this.captureGalleryNodeState( a );
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
			this.captureGalleryNodeState( galleryItem );
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
	},

	refreshFlatsomeGallery() {
		const slider = document.querySelector( '.product-gallery-slider' );
		if ( ! slider ) {
			return;
		}

		const flickity =
			slider.flickity || window.jQuery?.( slider ).data( 'flickity' );
		flickity?.reloadCells?.();
		flickity?.resize?.();
	},

	getFlickityInstance( slider ) {
		if ( ! slider ) {
			return null;
		}
		return (
			slider.flickity ||
			window.jQuery?.( slider ).data( 'flickity' ) ||
			null
		);
	},

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
	},

	setPanelPreviewHandoff( isActive ) {
		const panel = document.getElementById( 'oc-customiser-panel' );
		if ( panel ) {
			panel.classList.toggle( 'oc-gallery-preview-active', isActive );
		}
	},

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
	},

	stopTVPGAutoScroll( ...swipers ) {
		swipers.forEach( ( swiper ) => swiper?.autoplay?.stop?.() );
	},

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
	},

	setupCartGalleryUnlock() {
		if ( this._cartGalleryUnlockBound ) {
			return;
		}
		this._cartGalleryUnlockBound = true;

		window
			.jQuery?.( document.body )
			.on?.( 'added_to_cart', () => this.releaseTVPGPreviewLock( true ) );
	},

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
	},

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
	},

	pushToGallery( canvas ) {
		if ( ! this._customisationActive ) {
			return;
		}
		const generation = ++this._galleryPreviewGeneration;
		this.findGalleryImage();

		let dataUrl;
		try {
			dataUrl = canvas.toDataURL( { format: 'jpeg', quality: 0.92 } );
		} catch ( e ) {
			console.warn(
				'[OC] toDataURL failed - image may be cross-origin:',
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

		const applyTargets = () => {
			if (
				generation !== this._galleryPreviewGeneration ||
				! this._customisationActive
			) {
				return;
			}
			targets.forEach( ( img ) =>
				this.applyPreviewToImage( img, dataUrl, dimensions )
			);
		};
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
	},

	requestPreviewFocus() {
		this._hasCustomerPersonalisation = true;
		this._focusPreviewSlide = true;
	},

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
	},

	async switchProductVariation( variationId ) {
		if ( this.editMode ) {
			return;
		}

		const key = String( Math.max( 0, parseInt( variationId, 10 ) || 0 ) );
		const requestSeq = ++this._variationRequestSeq;
		if ( this._activeVariationKey && this._customisationActive ) {
			this.syncInputsFromDOM();
			const previousState =
				this.productVariationStates[ this._activeVariationKey ];
			if ( previousState ) {
				previousState.layerInputs = JSON.parse(
					JSON.stringify( this.inputs || {} )
				);
			}
		}
		this.deactivateCustomisation();
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

		if ( requestSeq !== this._variationRequestSeq ) {
			return;
		}
		if ( ! state?.active || ! state?.panelHtml ) {
			this.deactivateCustomisation();
			this._activeVariationKey = key;
			return;
		}

		await this.applyDesignState(
			state,
			state.selectedDesignVariant ||
				`design-${ state.designId || state.design_id }`,
			false
		);
		if ( requestSeq === this._variationRequestSeq ) {
			this._activeVariationKey = key;
		}
	},

	deactivateCustomisation() {
		this._customisationActive = false;
		this._designGeneration += 1;
		Object.keys( this.uploadGenerations ).forEach( ( layerId ) => {
			this.uploadGenerations[ layerId ] += 1;
		} );
		Object.keys( this.spotifyValidateTokens ).forEach( ( layerId ) =>
			this.invalidateSpotifyValidation( layerId )
		);
		this._previewUrl = null;
		Object.keys( this._redrawGenerations ).forEach( ( areaIndex ) => {
			this._redrawGenerations[ areaIndex ] += 1;
		} );
		const panel = document.getElementById( 'oc-customiser-panel' );
		if ( panel ) {
			panel.hidden = true;
			panel.setAttribute( 'aria-hidden', 'true' );
			panel
				.querySelectorAll( 'input, select, textarea, button' )
				.forEach( ( control ) => {
					control.disabled = true;
				} );
		}
		this.updateHiddenField();
		this.restoreProductGallery();
	},
};

export default galleryPreviewMethods;
