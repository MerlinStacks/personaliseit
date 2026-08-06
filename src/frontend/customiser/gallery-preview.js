/**
 * Product gallery preview integration for the frontend customiser.
 */

/* eslint-disable no-console */

const PREVIEW_DISCLAIMER =
	'This is a preview, not the final product. In some cases, our production team may need to adjust the personalisation. Any changes will preserve the spelling, grammar and spirit of your design, including any image supplied.';

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
		if ( ! node ) {
			return null;
		}
		return new Map(
			Array.from( node.attributes || [] ).map( ( attribute ) => [
				attribute.name,
				attribute.value,
			] )
		);
	},

	recordGalleryNodeState( node, before ) {
		if ( ! node || ! before ) {
			return;
		}
		const after = this.captureGalleryNodeState( node );
		const names = new Set( [ ...before.keys(), ...after.keys() ] );
		const state = node._ocOriginalPreviewState || new Map();
		names.forEach( ( name ) => {
			const beforeValue = before.has( name ) ? before.get( name ) : null;
			const afterValue = after.has( name ) ? after.get( name ) : null;
			if ( beforeValue === afterValue ) {
				return;
			}
			const previous = state.get( name );
			state.set( name, {
				original:
					previous && previous.preview === beforeValue
						? previous.original
						: beforeValue,
				preview: afterValue,
			} );
		} );
		if ( state.size ) {
			node._ocOriginalPreviewState = state;
			this._galleryPreviewNodes.add( node );
		}
	},

	restoreProductGallery() {
		this._galleryPreviewGeneration += 1;
		document
			.querySelectorAll(
				'.oc-live-preview-slide, .oc-live-preview-thumb-slide, .oc-preview-disclaimer'
			)
			.forEach( ( slide ) => slide.remove() );
		this._galleryPreviewNodes.forEach( ( node ) => {
			const state = node._ocOriginalPreviewState;
			if ( ! state ) {
				return;
			}
			state.forEach( ( values, name ) => {
				const current = node.hasAttribute( name )
					? node.getAttribute( name )
					: null;
				if ( current !== values.preview ) {
					return;
				}
				if ( values.original === null ) {
					node.removeAttribute( name );
				} else {
					node.setAttribute( name, values.original );
				}
			} );
			delete node._ocOriginalPreviewState;
		} );
		this._galleryPreviewNodes.clear();
		this._galleryFallbackNodeStates.forEach( ( state, node ) => {
			if ( state.parent ) {
				const reference =
					state.nextSibling?.parentNode === state.parent
						? state.nextSibling
						: null;
				state.parent.insertBefore( node, reference );
			}
			const currentNames = Array.from( node.attributes || [] ).map(
				( attribute ) => attribute.name
			);
			currentNames.forEach( ( name ) => {
				if ( ! state.attributes.has( name ) ) {
					node.removeAttribute( name );
				}
			} );
			state.attributes.forEach( ( value, name ) =>
				node.setAttribute( name, value )
			);
		} );
		this._galleryFallbackNodeStates.clear();
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

	addPreviewDisclaimer( img ) {
		if (
			! img ||
			img.closest( '.product-thumbnails, .tvpg-thumb-slider' )
		) {
			return;
		}

		const host =
			img.closest(
				'.woocommerce-product-gallery__image, .product-gallery-slider .slide, .swiper-slide'
			) || img.parentElement;
		if (
			! host ||
			host.querySelector( ':scope > .oc-preview-disclaimer' )
		) {
			return;
		}

		const badge = document.createElement( 'span' );
		const button = document.createElement( 'button' );
		const hint = document.createElement( 'span' );

		badge.className = 'oc-preview-disclaimer';
		button.type = 'button';
		button.className = 'oc-preview-disclaimer-toggle';
		button.textContent = 'i';
		button.setAttribute(
			'aria-label',
			`Preview information: ${ PREVIEW_DISCLAIMER }`
		);
		button.addEventListener( 'click', ( event ) => {
			event.preventDefault();
			event.stopPropagation();
		} );
		hint.className = 'oc-preview-disclaimer-text';
		hint.setAttribute( 'role', 'tooltip' );
		hint.textContent = PREVIEW_DISCLAIMER;
		badge.append( button, hint );
		host.appendChild( badge );
	},

	applyPreviewToImage( img, dataUrl, dimensions = null ) {
		if ( ! img ) {
			return;
		}
		const imageState = this.captureGalleryNodeState( img );
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
			const linkState = this.captureGalleryNodeState( a );
			a.href = dataUrl;
			a.setAttribute( 'data-src', dataUrl );
			this.recordGalleryNodeState( a, linkState );
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
		this.recordGalleryNodeState( img, imageState );

		const galleryItem = img.closest(
			'.woocommerce-product-gallery__image, .product-gallery-slider .slide'
		);
		if ( galleryItem ) {
			const galleryItemState =
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
					const linkState = this.captureGalleryNodeState( link );
					link.classList.add( 'oc-live-preview-frame' );
					link.style.aspectRatio = aspectRatio;
					link.style.height = 'auto';
					link.style.paddingTop = '0';
					link.style.paddingBottom = ratioPadding;
					this.recordGalleryNodeState( link, linkState );
				}
			}
			this.recordGalleryNodeState( galleryItem, galleryItemState );
		}
		this.addPreviewDisclaimer( img );
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
		if ( ! this._galleryFallbackNodeStates.has( canvasWrap ) ) {
			this._galleryFallbackNodeStates.set( canvasWrap, {
				parent: canvasWrap.parentNode,
				nextSibling: canvasWrap.nextSibling,
				attributes: this.captureGalleryNodeState( canvasWrap ),
			} );
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
		swipers.forEach( ( swiper ) => {
			if ( ! swiper ) {
				return;
			}
			if ( ! this._tvpgLockedSwipers.has( swiper ) ) {
				swiper._ocPreviewAutoplayWasRunning = Boolean(
					swiper.autoplay?.running
				);
				this._tvpgLockedSwipers.add( swiper );
			}
			swiper.autoplay?.stop?.();
		} );
	},

	releaseTVPGPreviewLock( resumeAutoplay = false ) {
		this._focusPreviewSlide = false;
		this._tvpgPreviewLocked = false;

		if ( ! resumeAutoplay ) {
			return;
		}

		this._tvpgLockedSwipers.forEach( ( swiper ) => {
			if ( swiper._ocPreviewLockHandler ) {
				( swiper._ocPreviewLockEvents || [] ).forEach( ( eventName ) =>
					swiper.off?.( eventName, swiper._ocPreviewLockHandler )
				);
			}
			if ( swiper._ocPreviewAutoplayWasRunning ) {
				swiper.autoplay?.start?.();
			}
			delete swiper._ocPreviewLockHandler;
			delete swiper._ocPreviewLockEvents;
			delete swiper._ocPreviewLockBound;
			delete swiper._ocPreviewSlideIndex;
			delete swiper._ocPreviewLocking;
			delete swiper._ocPreviewAutoplayWasRunning;
		} );
		this._tvpgLockedSwipers.clear();
	},

	setupCartGalleryUnlock() {
		if ( this._cartGalleryUnlockBound ) {
			return;
		}
		this._cartGalleryUnlockBound = true;

		window.jQuery?.( document.body ).on?.( 'added_to_cart', () => {
			this.restoreProductGallery();
		} );
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
			this.requestStateAnimationFrame( () => {
				swiper.slideTo?.( targetIndex, 0, false );
				swiper._ocPreviewLocking = false;
			} );
		};

		const lockEvents = [
			'activeIndexChange',
			'slideChange',
			'transitionStart',
		];
		lockEvents.forEach( ( eventName ) =>
			swiper.on?.( eventName, keepPreviewActive )
		);

		swiper._ocPreviewLockBound = true;
		swiper._ocPreviewLockHandler = keepPreviewActive;
		swiper._ocPreviewLockEvents = lockEvents;
		this._tvpgLockedSwipers.add( swiper );
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
			this.requestStateAnimationFrame( applyTargets );
			this.clearStateTimeout( this._galleryPreviewTimer );
			this._galleryPreviewTimer = this.setStateTimeout( () => {
				this._galleryPreviewTimer = null;
				applyTargets();
			}, 250 );
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

	saveActiveVariationState() {
		if ( ! this._customisationActive ) {
			return null;
		}

		this.syncInputsFromDOM();
		this.captureLinkGroupCarry();
		if ( this.linkGroupCarry.size ) {
			this.requestPreviewFocus();
		}
		const snapshot = {
			designId: parseInt( this.data.designId, 10 ) || 0,
			selectedDesignVariant: this.selectedDesignVariant || '',
			layerInputs: this.cloneLayerInputs(),
		};
		const state = this.productVariationStates[ this._activeVariationKey ];
		if ( ! state ) {
			return snapshot;
		}

		state.selectedDesignVariant = snapshot.selectedDesignVariant;
		const selectedState =
			state.designVariantStates?.[ snapshot.selectedDesignVariant ];
		if ( selectedState ) {
			selectedState.layerInputs = this.cloneLayerInputs(
				snapshot.layerInputs
			);
		}
		if (
			parseInt( state.designId || state.design_id, 10 ) ===
			snapshot.designId
		) {
			state.layerInputs = this.cloneLayerInputs( snapshot.layerInputs );
		}
		return snapshot;
	},

	scheduleProductVariationSwitch( variationId ) {
		this.clearStateTimeout( this._variationChangeTimer );
		this._variationChangeTimer = this.setStateTimeout( () => {
			this._variationChangeTimer = null;
			this.switchProductVariation( variationId );
		}, 100 );
	},

	async fetchProductVariationState( key, requestSeq, designId = 0 ) {
		const designUrl =
			this.data.productDesignUrl ||
			`${
				window.location.origin
			}/wp-json/overcustomise/v1/product-design/${
				this.data.productId || 0
			}`;
		const url = new URL( designUrl, window.location.origin );
		url.searchParams.set( 'variant_id', key );
		if ( designId ) {
			url.searchParams.set( 'design_id', String( designId ) );
		}
		const request = this.createStateAbortController( 10000 );
		this._variationAbortController = request.controller;

		try {
			const response = await fetch( url.toString(), {
				credentials: 'same-origin',
				headers: { Accept: 'application/json' },
				signal: request.controller.signal,
			} );
			if ( ! response.ok ) {
				throw new Error(
					`Variation design request failed (${ response.status })`
				);
			}
			const state = await response.json();
			if (
				! state ||
				typeof state !== 'object' ||
				Array.isArray( state )
			) {
				throw new Error( 'Variation design response was invalid.' );
			}
			return state;
		} catch ( error ) {
			if ( request.timedOut() ) {
				throw new Error( 'Variation design request timed out.' );
			}
			throw error;
		} finally {
			request.release();
			if ( this._variationAbortController === request.controller ) {
				this._variationAbortController = null;
			}
		}
	},

	setupVariationGalleryHandoff() {
		const form = document.querySelector(
			'form.variations_form, form.cart, form[data-wp-on--submit*="addToCart"]'
		);
		if ( ! form || form._ocVariationGalleryHandoffBound ) {
			return;
		}

		form._ocVariationGalleryHandoffBound = true;
		const getSelectedVariationId = () =>
			parseInt(
				form.querySelector( 'input[name="variation_id"]' )?.value ||
					'0',
				10
			) || 0;
		const releasePreviewLock = () => this.releaseTVPGPreviewLock();
		const handleVariationChange = ( variation ) => {
			releasePreviewLock();
			this.clearStateTimeout( this._variationChangeTimer );
			this._variationChangeTimer = null;
			const variationId =
				parseInt(
					variation?.variation_id || getSelectedVariationId(),
					10
				) || 0;
			const variationKey = String( variationId );

			Promise.resolve( this.switchProductVariation( variationId ) ).then(
				( switched ) => {
					if ( ! switched ) {
						return;
					}

					const refocusPreview = () => {
						if (
							! this._hasCustomerPersonalisation ||
							! this._customisationActive ||
							this._activeVariationKey !== variationKey
						) {
							return;
						}

						const canvas = this.canvases[ this.activeArea ];
						if ( ! canvas || canvas._ocMissingMockup ) {
							return;
						}

						this.requestPreviewFocus();
						this.pushToGallery( canvas );
					};

					refocusPreview();
					this.requestStateAnimationFrame( refocusPreview );
					this.setStateTimeout( refocusPreview, 250 );
				}
			);
		};

		form.addEventListener( 'change', ( event ) => {
			if (
				event.target?.closest?.( '.variations, [name^="attribute_"]' )
			) {
				releasePreviewLock();
				this.scheduleProductVariationSwitch( getSelectedVariationId() );
			}
		} );

		window
			.jQuery?.( form )
			.on?.( 'woocommerce_variation_select_change', releasePreviewLock );
		window.jQuery?.( form ).on?.( 'reset_data', () => {
			releasePreviewLock();
			this.scheduleProductVariationSwitch( 0 );
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
		const key = String( Math.max( 0, parseInt( variationId, 10 ) || 0 ) );
		if (
			this._variationSwitchPromise &&
			this._pendingVariationKey === key
		) {
			return this._variationSwitchPromise;
		}
		if (
			! this._variationSwitchPromise &&
			this._activeVariationKey === key &&
			! this._variationSwitchFailed
		) {
			return true;
		}

		const previousSwitch = this._variationSwitchPromise;
		const previousKey = this._activeVariationKey;
		const initialSnapshot = this.saveActiveVariationState();
		const requestSeq = ++this._variationRequestSeq;
		this._variationAbortController?.abort();
		this.cancelPendingDesignVariantRequest();
		this._pendingVariationKey = key;
		this._variationSwitchPending = true;
		this._variationSwitchFailed = false;
		this.setControlLock( 'variation', true );

		const switchPromise = ( async () => {
			try {
				if ( previousSwitch ) {
					await previousSwitch;
				}
				if ( requestSeq !== this._variationRequestSeq ) {
					return false;
				}

				let state = this.productVariationStates[ key ];
				if ( ! state ) {
					state = await this.fetchProductVariationState(
						key,
						requestSeq
					);
				}
				if ( requestSeq !== this._variationRequestSeq ) {
					return false;
				}

				if ( ! previousKey && initialSnapshot && state.active ) {
					const defaultDesignId = Number(
						state.designId || state.design_id
					);
					const allowedVariant =
						state.designVariants?.find(
							( variant ) =>
								Number( variant.designId ) ===
								initialSnapshot.designId
						) ||
						( defaultDesignId === initialSnapshot.designId
							? {
									id:
										state.selectedDesignVariant ||
										`design-${ defaultDesignId }`,
									designId: defaultDesignId,
							  }
							: null );
					if ( allowedVariant ) {
						let initialVariantState =
							state.designVariantStates?.[ allowedVariant.id ];
						if ( ! initialVariantState?.panelHtml ) {
							try {
								const restoredState =
									await this.fetchProductVariationState(
										key,
										requestSeq,
										initialSnapshot.designId
									);
								initialVariantState =
									restoredState.designVariantStates?.[
										allowedVariant.id
									];
							} catch ( error ) {
								console.warn(
									'[OC] Initial design restore failed; using variation default:',
									error
								);
							}
						}
						if ( initialVariantState?.panelHtml ) {
							state.designVariantStates ||= {};
							state.designVariantStates[ allowedVariant.id ] =
								initialVariantState;
							state.selectedDesignVariant = allowedVariant.id;
							initialVariantState.layerInputs =
								this.cloneLayerInputs(
									initialSnapshot.layerInputs
								);
						}
					}
				}
				if ( requestSeq !== this._variationRequestSeq ) {
					return false;
				}
				this.productVariationStates[ key ] = state;

				if ( ! state.active || ! state.panelHtml ) {
					this._activeVariationKey = key;
					this.deactivateCustomisation();
					return true;
				}

				const selectedVariant =
					state.selectedDesignVariant ||
					`design-${ state.designId || state.design_id }`;
				const selectedState =
					state.designVariantStates?.[ selectedVariant ] || state;
				const nextState = {
					...selectedState,
					designVariants:
						selectedState.designVariants ||
						state.designVariants ||
						[],
					designVariantStates: state.designVariantStates || {},
					selectedDesignVariant: selectedVariant,
				};
				const applied = await this.applyDesignState(
					nextState,
					selectedVariant,
					false,
					false
				);
				if ( ! applied || requestSeq !== this._variationRequestSeq ) {
					return false;
				}

				state.selectedDesignVariant = selectedVariant;
				this._activeVariationKey = key;
				return true;
			} catch ( error ) {
				if ( requestSeq !== this._variationRequestSeq ) {
					return false;
				}
				console.warn( '[OC] Variation design load failed:', error );
				this._variationSwitchFailed = true;
				this.renderPreflightMessages(
					[
						'We could not load the personalisation options for this variation. Check your connection, then press Add to cart to retry.',
					],
					[]
				);
				return false;
			}
		} )();

		this._variationSwitchPromise = switchPromise;
		try {
			const switched = await switchPromise;
			if (
				requestSeq === this._variationRequestSeq &&
				switched &&
				this._customisationActive
			) {
				this._variationSwitchFailed = false;
				this._initialAiFilterPromise = this.applyInitialAiFilters();
			}
			return switched;
		} finally {
			if ( this._variationSwitchPromise === switchPromise ) {
				this._variationSwitchPromise = null;
				this._pendingVariationKey = '';
				this._variationSwitchPending = false;
				this.setControlLock( 'variation', false );
			}
		}
	},

	deactivateCustomisation() {
		this.invalidateDesignState();
		this._customisationActive = false;
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
