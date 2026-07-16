/**
 * Design variant carousel, thumbnails, and state switching.
 */

/* eslint-disable no-console */

import { StaticCanvas } from 'fabric';
import { displayBounds } from '../../shared/render-math';

const designVariantMethods = {
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
	},

	setupDesignVariantCarousel() {
		const carousel = document.querySelector(
			'[data-oc-design-variant-carousel]'
		);
		const track = carousel?.querySelector(
			'[data-oc-design-variant-track]'
		);
		if (
			! carousel ||
			! track ||
			carousel.dataset.ocCarouselReady === '1'
		) {
			return;
		}

		carousel.dataset.ocCarouselReady = '1';
		carousel
			.querySelector( '[data-oc-design-variant-prev]' )
			?.addEventListener( 'click', () =>
				this.scrollDesignVariantCarousel( -1 )
			);
		carousel
			.querySelector( '[data-oc-design-variant-next]' )
			?.addEventListener( 'click', () =>
				this.scrollDesignVariantCarousel( 1 )
			);
		track.addEventListener(
			'scroll',
			() => this.updateDesignVariantCarouselDots(),
			{ passive: true }
		);
		this.refreshDesignVariantCarousel();
	},

	designVariantCarouselPageCount( track ) {
		if ( ! track || ! track.clientWidth ) {
			return 1;
		}
		return Math.max(
			1,
			Math.ceil( track.scrollWidth / track.clientWidth )
		);
	},

	scrollDesignVariantCarousel( direction ) {
		const track = document.querySelector(
			'[data-oc-design-variant-track]'
		);
		if ( ! track ) {
			return;
		}

		const page =
			Math.round( track.scrollLeft / Math.max( 1, track.clientWidth ) ) +
			direction;
		const maxPage = this.designVariantCarouselPageCount( track ) - 1;
		track.scrollTo( {
			left: Math.max( 0, Math.min( maxPage, page ) ) * track.clientWidth,
			behavior: 'smooth',
		} );
	},

	refreshDesignVariantCarousel() {
		const carousel = document.querySelector(
			'[data-oc-design-variant-carousel]'
		);
		const track = carousel?.querySelector(
			'[data-oc-design-variant-track]'
		);
		const dots = carousel?.querySelector( '[data-oc-design-variant-dots]' );
		if ( ! carousel || ! track || ! dots ) {
			return;
		}

		const pageCount = this.designVariantCarouselPageCount( track );
		const maxLeft = Math.max( 0, ( pageCount - 1 ) * track.clientWidth );
		if ( track.scrollLeft > maxLeft ) {
			track.scrollLeft = maxLeft;
		}

		dots.innerHTML = '';
		for ( let i = 0; i < pageCount; i++ ) {
			const dot = document.createElement( 'button' );
			dot.type = 'button';
			dot.className = 'oc-design-variant-carousel-dot';
			dot.setAttribute(
				'aria-label',
				`Go to artwork option page ${ i + 1 }`
			);
			dot.addEventListener( 'click', () =>
				track.scrollTo( {
					left: i * track.clientWidth,
					behavior: 'smooth',
				} )
			);
			dots.appendChild( dot );
		}

		carousel.classList.toggle(
			'oc-design-variant-carousel--single-page',
			pageCount <= 1
		);
		this.updateDesignVariantCarouselDots();
	},

	updateDesignVariantCarouselDots() {
		const carousel = document.querySelector(
			'[data-oc-design-variant-carousel]'
		);
		const track = carousel?.querySelector(
			'[data-oc-design-variant-track]'
		);
		if ( ! carousel || ! track ) {
			return;
		}

		const pageCount = this.designVariantCarouselPageCount( track );
		const page = Math.max(
			0,
			Math.min(
				pageCount - 1,
				Math.round(
					track.scrollLeft / Math.max( 1, track.clientWidth )
				)
			)
		);
		carousel
			.querySelectorAll( '.oc-design-variant-carousel-dot' )
			.forEach( ( dot, i ) => {
				dot.classList.toggle( 'oc-active', i === page );
				dot.setAttribute(
					'aria-current',
					i === page ? 'true' : 'false'
				);
			} );
		carousel
			.querySelector( '[data-oc-design-variant-prev]' )
			?.toggleAttribute( 'disabled', page <= 0 );
		carousel
			.querySelector( '[data-oc-design-variant-next]' )
			?.toggleAttribute( 'disabled', page >= pageCount - 1 );
	},

	async renderDesignVariantThumbnails() {
		const canvases = Array.from(
			document.querySelectorAll( '[data-oc-design-variant-thumb]' )
		);
		if ( ! canvases.length ) {
			return;
		}

		for ( const canvasEl of canvases ) {
			if ( canvasEl.dataset.ocThumbRendered === '1' ) {
				continue;
			}

			const variantId = canvasEl.dataset.ocDesignVariantThumb;
			const state = this.data.designVariantStates?.[ variantId ];
			if ( ! state?.areas?.length ) {
				canvasEl
					.closest( '.oc-design-variant-option' )
					?.classList.remove( 'oc-thumb-pending' );
				continue;
			}

			try {
				const rendered = await this.renderDesignVariantThumbnailCanvas(
					canvasEl,
					state
				);
				canvasEl
					.closest( '.oc-design-variant-option' )
					?.classList.remove( 'oc-thumb-pending' );
				if ( rendered ) {
					canvasEl.dataset.ocThumbRendered = '1';
					canvasEl
						.closest( '.oc-design-variant-option' )
						?.classList.add( 'oc-thumb-rendered' );
				}
			} catch ( err ) {
				canvasEl
					.closest( '.oc-design-variant-option' )
					?.classList.remove( 'oc-thumb-pending' );
				console.warn(
					'[OC] Design variant thumbnail failed:',
					variantId,
					err
				);
			}
		}
	},

	async renderDesignVariantThumbnailCanvas( canvasEl, state ) {
		const area = state.areas?.[ 0 ];
		if ( ! area ) {
			return;
		}

		const sourceBounds = this.areaBounds( area );
		const bounds = displayBounds( sourceBounds );
		const size = 320;
		canvasEl.width = size;
		canvasEl.height = size;

		const canvas = new StaticCanvas( canvasEl, {
			width: size,
			height: size,
			backgroundColor: 'rgba(255,255,255,0)',
		} );
		const scale = Math.min(
			size / Math.max( 1, bounds.w || 1 ),
			size / Math.max( 1, bounds.h || 1 )
		);
		const offsetX = ( size - ( bounds.w || 1 ) * scale ) / 2;
		const offsetY = ( size - ( bounds.h || 1 ) * scale ) / 2;

		canvas.setViewportTransform( [
			1,
			0,
			0,
			1,
			offsetX - Number( bounds.x || 0 ) * scale,
			offsetY - Number( bounds.y || 0 ) * scale,
		] );
		canvas._ocScaleX = scale;
		const previousFonts = this.fonts;
		this.fonts = state.fonts || this.fonts || [];
		try {
			const thumbArea = {
				...area,
				printMethod: 'uv',
			};
			for ( const layer of area.layers || [] ) {
				const input = {
					...( state.layerInputs?.[ layer.id ] || {} ),
				};
				if (
					( layer.type === 'text' || layer.type === 'textarea' ) &&
					! String( input.value || '' ).trim()
				) {
					input.value =
						layer.settings?.default_text || layer.label || '';
				}
				await this.renderLayer( canvas, layer, input, thumbArea );
			}
		} finally {
			this.fonts = previousFonts;
		}

		canvas.renderAll();
		return (
			canvas
				.getObjects()
				.some( ( object ) => object._ocContent === true ) &&
			this.canvasHasVisiblePixels( canvasEl )
		);
	},

	canvasHasVisiblePixels( canvasEl ) {
		const context = canvasEl.getContext( '2d', {
			willReadFrequently: true,
		} );
		if ( ! context ) {
			return false;
		}

		const { width, height } = canvasEl;
		if ( ! width || ! height ) {
			return false;
		}

		const data = context.getImageData( 0, 0, width, height ).data;
		for ( let i = 0; i < data.length; i += 4 ) {
			if ( data[ i + 3 ] > 8 ) {
				return true;
			}
		}

		return false;
	},

	async switchDesignVariant( variantId ) {
		const state = this.data.designVariantStates?.[ variantId ];
		if ( ! state?.panelHtml ) {
			return;
		}

		await this.applyDesignState( state, variantId, true );
	},

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
		this._designGeneration += 1;
		Object.keys( this.aiFilterGenerations ).forEach( ( layerId ) => {
			this.aiFilterGenerations[ layerId ] += 1;
		} );
		Object.values( this.aiFilterAbortControllers ).forEach(
			( controller ) => controller.abort()
		);
		this.aiFilterAbortControllers = {};
		this.aiFilterErrors = {};
		Object.keys( this.uploadGenerations ).forEach( ( layerId ) => {
			this.uploadGenerations[ layerId ] += 1;
		} );
		Object.keys( this.spotifyValidateTokens ).forEach( ( layerId ) =>
			this.invalidateSpotifyValidation( layerId )
		);

		Object.values( this.canvases || {} ).forEach( ( canvas ) =>
			canvas?.dispose?.()
		);
		Object.keys( this._redrawGenerations ).forEach( ( areaIndex ) => {
			this._redrawGenerations[ areaIndex ] += 1;
		} );
		this.canvases = {};
		this._previewUrl = null;
		this.activeArea = 0;
		this.selectedDesignVariant = variantId;
		this._customisationActive = true;

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
		this.seedTemplateImageDefaults();
		this.setupDesignVariantOptions();
		this.setupDesignVariantCarousel();
		this.renderDesignVariantThumbnails();
		this.setupClipartCarousels();
		this.setupUploadZones();
		this.applyInitialAiFilters();
		this.setupVariationGalleryHandoff();
		this.applyInputsToDOM();
		this.updateHiddenField();
		await this.initAllCanvases();
	},
};

export default designVariantMethods;
