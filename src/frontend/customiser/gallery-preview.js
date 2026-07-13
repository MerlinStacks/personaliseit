/**
 * Product gallery preview integration for the frontend customiser.
 */

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
};

export default galleryPreviewMethods;
