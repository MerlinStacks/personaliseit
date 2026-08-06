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

		const stateSignal = this._panelListenerController?.signal;
		document
			.querySelectorAll( '[data-oc-design-variant]' )
			.forEach( ( btn ) => {
				btn.addEventListener(
					'click',
					() => {
						const variant = this.designVariants.find(
							( item ) => item.id === btn.dataset.ocDesignVariant
						);
						if ( ! variant ) {
							return;
						}
						if ( variant.id === this.selectedDesignVariant ) {
							this.cancelPendingDesignVariantRequest();
							return;
						}

						this.switchDesignVariant( variant.id );
					},
					{ signal: stateSignal }
				);
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
		const designGeneration = this._designGeneration;
		const canvases = Array.from(
			document.querySelectorAll( '[data-oc-design-variant-thumb]' )
		);
		if ( ! canvases.length ) {
			return;
		}

		for ( const canvasEl of canvases ) {
			if ( designGeneration !== this._designGeneration ) {
				return;
			}
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
		this._thumbnailCanvases.add( canvas );
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
		const thumbnailArea = { ...area, printMethod: '' };
		const thumbnailFonts = state.fonts || this.fonts || [];
		const thumbnailLayers = [ ...( area.layers || [] ) ].sort(
			( a, b ) =>
				Number( a.type === 'mask' ) - Number( b.type === 'mask' )
		);
		for ( const layer of thumbnailLayers ) {
			const input = {
				...( state.layerInputs?.[ layer.id ] || {} ),
			};
			if (
				( layer.type === 'text' || layer.type === 'textarea' ) &&
				! String( input.value || '' ).trim()
			) {
				input.value = layer.settings?.default_text || layer.label || '';
			}
			await this.renderLayer(
				canvas,
				layer,
				input,
				thumbnailArea,
				() => true,
				{ fonts: thumbnailFonts }
			);
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
		const variant = this.designVariants.find(
			( item ) => item.id === variantId
		);
		if ( ! variant || this._variationSwitchPending ) {
			return;
		}

		this.cancelPendingDesignVariantRequest();
		const requestSeq = ++this._designVariantRequestSeq;
		this.setDesignVariantCartPending( requestSeq, true );
		const button = document.querySelector(
			`[data-oc-design-variant="${ CSS.escape( variantId ) }"]`
		);
		button?.classList.add( 'oc-loading' );
		button?.setAttribute( 'aria-busy', 'true' );

		try {
			let state = this.data.designVariantStates?.[ variantId ];
			if ( ! state?.panelHtml ) {
				state = await this.fetchDesignVariantState(
					variant,
					requestSeq
				);
			}
			if ( requestSeq !== this._designVariantRequestSeq ) {
				return;
			}

			this.data.designVariantStates ||= {};
			this.data.designVariantStates[ variantId ] = state;
			const variationState =
				this.productVariationStates[ this._activeVariationKey ];
			if ( variationState ) {
				variationState.designVariantStates ||= {};
				variationState.designVariantStates[ variantId ] = state;
			}

			const applied = await this.applyDesignState(
				state,
				variantId,
				true
			);
			if ( ! applied || requestSeq !== this._designVariantRequestSeq ) {
				return;
			}
			if ( variationState ) {
				variationState.selectedDesignVariant = variantId;
			}
			this.updateDesignVariantUrl( variantId );
		} catch ( error ) {
			if (
				requestSeq !== this._designVariantRequestSeq ||
				error?.name === 'AbortError'
			) {
				return;
			}
			console.error( '[OC] Design option switch failed:', error );
			this.renderPreflightMessages(
				[
					'The selected artwork option could not be loaded. Please try again.',
				],
				[]
			);
		} finally {
			this.setDesignVariantCartPending( requestSeq, false );
			button?.classList.remove( 'oc-loading' );
			button?.removeAttribute( 'aria-busy' );
		}
	},

	cancelPendingDesignVariantRequest() {
		if ( ! this._designVariantPendingSeq ) {
			return false;
		}
		const pendingSeq = this._designVariantPendingSeq;
		this._designVariantRequestSeq += 1;
		this._designVariantAbortController?.abort();
		this._designVariantAbortController = null;
		this.setDesignVariantCartPending( pendingSeq, false );
		document
			.querySelectorAll( '[data-oc-design-variant].oc-loading' )
			.forEach( ( button ) => {
				button.classList.remove( 'oc-loading' );
				button.removeAttribute( 'aria-busy' );
			} );
		return true;
	},

	setDesignVariantCartPending( requestSeq, pending ) {
		const form = this.getCustomiserCartForm?.();
		const submitControls = form?.querySelectorAll(
			'[type="submit"], .single_add_to_cart_button'
		);
		if ( pending ) {
			this._designVariantPendingSeq = requestSeq;
			submitControls?.forEach( ( control ) => {
				if ( control.dataset.ocDesignLoadDisabled === undefined ) {
					control.dataset.ocDesignLoadDisabled = control.disabled ? '1' : '0';
				}
				control.disabled = true;
				control.setAttribute( 'aria-disabled', 'true' );
			} );
			return;
		}
		if ( this._designVariantPendingSeq !== requestSeq ) {
			return;
		}
		this._designVariantPendingSeq = 0;
		submitControls?.forEach( ( control ) => {
			if ( control.dataset.ocDesignLoadDisabled === undefined ) {
				return;
			}
			control.disabled = control.dataset.ocDesignLoadDisabled === '1';
			control.setAttribute(
				'aria-disabled',
				control.disabled ? 'true' : 'false'
			);
			delete control.dataset.ocDesignLoadDisabled;
		} );
		this.applyControlLocks();
	},

	async fetchDesignVariantState( variant, requestSeq ) {
		const designUrl =
			this.data.productDesignUrl ||
			`${ window.location.origin }/wp-json/overcustomise/v1/product-design/${
				this.data.productId || 0
			}`;
		const url = new URL( designUrl, window.location.origin );
		url.searchParams.set( 'variant_id', String( this.currentVariationId() ) );
		url.searchParams.set( 'design_id', String( variant.designId ) );
		const request = this.createStateAbortController( 10000 );
		this._designVariantAbortController = request.controller;

		try {
			const response = await fetch( url.toString(), {
				credentials: 'same-origin',
				headers: { Accept: 'application/json' },
				signal: request.controller.signal,
			} );
			const body = await response.json().catch( () => null );
			if ( ! response.ok ) {
				throw new Error(
					body?.message ||
						`Artwork option request failed (${ response.status })`
				);
			}
			if ( requestSeq !== this._designVariantRequestSeq ) {
				throw new DOMException( 'Superseded request', 'AbortError' );
			}

			const state = body?.designVariantStates?.[ variant.id ];
			if (
				! body?.active ||
				! state?.panelHtml ||
				Number( state.designId ) !== Number( variant.designId ) ||
				body.selectedDesignVariant !== variant.id
			) {
				throw new Error( 'Artwork option response was invalid.' );
			}
			state.designVariants = body.designVariants || this.designVariants;
			return state;
		} catch ( error ) {
			if ( request.timedOut() ) {
				throw new Error(
					'Artwork option request timed out. Please retry.'
				);
			}
			throw error;
		} finally {
			request.release();
			if ( this._designVariantAbortController === request.controller ) {
				this._designVariantAbortController = null;
			}
		}
	},

	updateDesignVariantUrl( variantId ) {
		if ( ! window.history?.replaceState ) {
			return;
		}
		const url = new URL( window.location.href );
		url.searchParams.set( 'oc_design_variant', variantId );
		window.history.replaceState( window.history.state, '', url.toString() );
	},

	cloneLayerInputs( inputs = this.inputs ) {
		return JSON.parse( JSON.stringify( inputs || {} ) );
	},

	saveCurrentDesignVariantInputs() {
		this.syncInputsFromDOM();
		const currentState =
			this.data.designVariantStates?.[ this.selectedDesignVariant ];
		if ( currentState ) {
			currentState.layerInputs = this.cloneLayerInputs();
		}
	},

	async applyDesignState(
		state,
		variantId,
		preserveCurrentState = true,
		initialiseAiFilters = true
	) {
		if ( ! state?.panelHtml ) {
			return;
		}

		if ( preserveCurrentState ) {
			this.saveCurrentDesignVariantInputs();
		}
		const designGeneration = this.invalidateDesignState();
		this.activeArea = 0;
		this.selectedDesignVariant =
			variantId || state.selectedDesignVariant || '';
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
		this.data.selectedDesignVariant = this.selectedDesignVariant;
		this.data.fonts = state.fonts || this.data.fonts || [];
		this.data.colours = state.colours || this.data.colours || [];
		this.data.imageFilters =
			state.imageFilters || this.data.imageFilters || [];
		this.data.restrictedLayerColours =
			state.restrictedLayerColours ||
			this.data.restrictedLayerColours ||
			{};

		this.areas = this.data.areas || [];
		this.fonts = this.data.fonts || [];
		this.designVariants = this.data.designVariants || [];
		this.layersById = {};
		this.areas.forEach( ( area ) =>
			( area.layers || [] ).forEach( ( layer ) => {
				this.layersById[ layer.id ] = layer;
			} )
		);
		this.inputs = {};
		Object.entries( this.cloneLayerInputs( state.layerInputs ) ).forEach(
			( [ k, v ] ) => {
				const layerId = parseInt( k, 10 );
				this.inputs[ layerId ] = { ...v };
				this.clampLayerInputValue( layerId );
			}
		);
		this.data.layerInputs = this.cloneLayerInputs( this.inputs );

		this.preflightRoot = document.getElementById( 'oc-preflight-messages' );
		this.beginDesignStateListeners();
		this.seedLockedLayerDefaults();
		this.seedTemplateImageDefaults();
		this.seedLayerFontDefaults();
		await this.hydrateLinkGroupCarry();
		if ( designGeneration !== this._designGeneration ) {
			return false;
		}
		this.seedLinkedImageInputs();
		this.seedLinkedColourInputs();
		this.applyInputsToDOM( { redraw: false } );
		this.setupInputListeners();
		this.setupDesignVariantOptions();
		this.setupDesignVariantCarousel();
		this.renderDesignVariantThumbnails();
		this.setupClipartCarousels();
		this._uploadSetupPromise = this.setupUploadZones();
		if ( initialiseAiFilters ) {
			this._initialAiFilterPromise = this.applyInitialAiFilters();
		}
		this.applyActiveAreaState( 0 );
		if ( preserveCurrentState ) {
			this.requestPreviewFocus();
		}
		this.applyControlLocks();
		this.updateHiddenField();
		await this.startCanvasInitialisation();
		return designGeneration === this._designGeneration;
	},
};

export default designVariantMethods;
