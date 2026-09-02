/**
 * Frontend Customiser — vanilla JS, no framework dependency.
 *
 * Data: window.ocCustomiserData (wp_localize_script).
 * Canvas: Fabric.js 7.x  |  Uploads: Uppy 5.x
 *
 * @package
 */

import './customiser-app.scss';
import inputControlMethods from './customiser/input-controls';
import cartSerializationMethods from './customiser/cart-serialization';
import galleryPreviewMethods from './customiser/gallery-preview';
import clipartMethods from './customiser/clipart';
import preflightMethods from './customiser/preflight';
import spotifyMethods from './customiser/spotify';
import uploadMethods from './customiser/uploads';
import checkoutMethods from './customiser/checkout';

// ── Boot ──────────────────────────────────────────────────────────────────────

const setBootLoading = ( loading ) => {
	const panel = document.getElementById( 'oc-customiser-panel' );
	if ( ! panel ) {
		return;
	}
	panel.inert = loading;
	panel.setAttribute( 'aria-busy', loading ? 'true' : 'false' );
};

const setBootSubmitDisabled = ( disabled ) => {
	const panel = document.getElementById( 'oc-customiser-panel' );
	const form = panel?.closest( 'form' );
	const controls =
		form?.querySelectorAll(
			'[type="submit"], .single_add_to_cart_button'
		) || [];
	controls.forEach( ( control ) => {
		if ( disabled ) {
			if ( control.dataset.ocBootDisabled === undefined ) {
				control.dataset.ocBootDisabled = control.disabled ? '1' : '0';
			}
			control.disabled = true;
			control.setAttribute( 'aria-disabled', 'true' );
			return;
		}
		if ( control.dataset.ocBootDisabled === undefined ) {
			return;
		}
		control.disabled = control.dataset.ocBootDisabled === '1';
		control.setAttribute(
			'aria-disabled',
			control.disabled ? 'true' : 'false'
		);
		delete control.dataset.ocBootDisabled;
	} );
};

const renderBootFailure = ( retry ) => {
	const root = document.getElementById( 'oc-preflight-messages' );
	if ( ! root ) {
		return;
	}
	const message = document.createElement( 'div' );
	message.className = 'oc-preflight-error';
	message.setAttribute( 'role', 'alert' );
	message.textContent =
		'The customisation preview could not load. Check your connection and retry.';
	const button = document.createElement( 'button' );
	button.type = 'button';
	button.className = 'oc-upload-retry';
	button.textContent = 'Retry customiser';
	button.addEventListener( 'click', retry, { once: true } );
	root.replaceChildren( message, button );
	root.dataset.ocBootFailure = '1';
	root.hidden = false;
};

const clearBootFailure = () => {
	const root = document.getElementById( 'oc-preflight-messages' );
	if ( root?.dataset.ocBootFailure !== '1' ) {
		return;
	}
	root.replaceChildren();
	delete root.dataset.ocBootFailure;
	root.hidden = true;
};

const bootCustomiser = async ( data ) => {
	setBootLoading( true );
	let modules;
	try {
		modules = await Promise.all( [
			import(
				/* webpackChunkName: "customiser-core" */ './customiser/canvas-renderer'
			),
			import(
				/* webpackChunkName: "customiser-core" */ './customiser/design-variants'
			),
		] );
	} catch {
		setBootLoading( false );
		setBootSubmitDisabled( true );
		renderBootFailure( () => bootCustomiser( data ) );
		return;
	}
	const [ { default: canvasMethods }, { default: variantMethods } ] = modules;
	Object.assign( OCCustomiser.prototype, canvasMethods, variantMethods );
	setBootSubmitDisabled( false );
	setBootLoading( false );
	clearBootFailure();
	new OCCustomiser( data ).init();
};

document.addEventListener( 'DOMContentLoaded', () => {
	const data = window.ocCustomiserData;
	if ( ! data || ! data.areas?.length ) {
		return;
	}
	bootCustomiser( data );
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

		this.canvases = {}; // areaIndex → Fabric StaticCanvas
		this._redrawTimers = {};
		this._redrawGenerations = {};
		this._redrawPromises = {};
		this._canvasReadyPromise = null;
		this._canvasReadyGeneration = -1;
		this._stateAbortControllers = new Set();
		this._stateTimers = new Set();
		this._stateAnimationFrames = new Set();
		this._panelListenerController = null;
		this.fontCache = {}; // font family/weight/style/URL -> load Promise
		this.clipartSvgCache = {};
		this.galleryImg = null; // the main <img> in the product gallery
		this._focusPreviewSlide = false; // jump TVPG to preview slide after user edits
		this._hasCustomerPersonalisation = false;
		this._tvpgPreviewLocked = false;
		this._galleryPreviewGeneration = 0;
		this._galleryPreviewNodes = new Set();
		this._galleryFallbackNodeStates = new Map();
		this._galleryPreviewObjectUrl = '';
		this._tvpgLockedSwipers = new Set();
		this.productVariationStates = {};
		this.linkGroupCarry = new Map();
		this.artworkContextAuthorisations = new Set();
		this._variationRequestSeq = 0;
		this._variationSwitchPending = false;
		this._variationSwitchFailed = false;
		this._activeVariationKey = '';
		this._pendingVariationKey = '';
		this._variationAbortController = null;
		this._variationSwitchPromise = null;
		this._designVariantRequestSeq = 0;
		this._designVariantAbortController = null;
		this._designVariantPendingSeq = 0;
		this._designGeneration = 0;
		this.spotifyValidateTimers = {};
		this.spotifyValidateTokens = {};
		this.spotifyAbortControllers = {};
		this.spotifyValidationPromises = {};
		this.uploadGenerations = {};
		this.aiFilterGenerations = {};
		this.aiFilterAbortControllers = {};
		this.aiFilterErrors = {};
		this.failedArtworkReplacements = new Set();
		this.artworkPendingCount = 0;
		this._artworkOperations = new Set();
		this.uppyInstances = new Set();
		this._thumbnailCanvases = new Set();
		this.preflightRoot = null;
		this.clipartSearchTimers = {};
		this.clipartSearchTerms = {};
		this.clipartCategoryFilters = {};
		this.spotifyModalCloseTimer = null;
		this.mobileCartPreviewDialog = null;
		this.mobileCartPreviewDismissedAt = 0;
		this.formSubmitBound = false;
		this.fontComboboxDocumentClickBound = false;
		this._customisationActive = true;
		this._submitInProgress = false;
		this._controlLocks = new Set();
		this._galleryPreviewTimer = null;
		this._cropGalleryTimer = null;
		this._cropGalleryCanvas = null;
		this._variationChangeTimer = null;
		this._mobileCartPreviewResolve = null;
		this._mobileCartPreviewPromise = null;
		this._storeApiPreparationPromise = null;
		this._storeApiSubmitBound = false;
		this._storeApiFetchBound = false;
		this._pendingStoreApiCustomisation = null;
	}

	beginDesignStateListeners() {
		this._panelListenerController?.abort();
		this._panelListenerController = new AbortController();
		return this._panelListenerController.signal;
	}

	setStateTimeout( callback, delay ) {
		const timer = window.setTimeout( () => {
			this._stateTimers.delete( timer );
			callback();
		}, delay );
		this._stateTimers.add( timer );
		return timer;
	}

	clearStateTimeout( timer ) {
		if ( timer !== null && timer !== undefined ) {
			window.clearTimeout( timer );
			this._stateTimers.delete( timer );
		}
	}

	requestStateAnimationFrame( callback ) {
		const frame = window.requestAnimationFrame( () => {
			this._stateAnimationFrames.delete( frame );
			callback();
		} );
		this._stateAnimationFrames.add( frame );
		return frame;
	}

	createStateAbortController( timeoutMs = 10000 ) {
		const controller = new AbortController();
		let timedOut = false;
		let timer = null;
		this._stateAbortControllers.add( controller );
		if ( timeoutMs > 0 ) {
			timer = this.setStateTimeout( () => {
				timedOut = true;
				controller.abort();
			}, timeoutMs );
		}

		return {
			controller,
			timedOut: () => timedOut,
			release: () => {
				if ( timer !== null ) {
					this.clearStateTimeout( timer );
				}
				this._stateAbortControllers.delete( controller );
			},
		};
	}

	invalidateDesignState() {
		this._designGeneration += 1;
		this.teardownDesignState();
		this._canvasReadyGeneration = -1;
		this._canvasReadyPromise = null;
		return this._designGeneration;
	}

	teardownDesignState() {
		this.restoreProductGallery?.();
		this.dismissMobileCartPreview?.();
		this.dismissSpotifyModal?.();
		this.consumeStoreApiCustomisationMerge?.();
		this._panelListenerController?.abort();
		this._panelListenerController = null;

		Object.values( this._redrawTimers ).forEach( window.clearTimeout );
		this._redrawTimers = {};
		Object.keys( this._redrawGenerations ).forEach( ( areaIndex ) => {
			this._redrawGenerations[ areaIndex ] += 1;
		} );
		this._redrawPromises = {};

		new Set( [
			...Object.keys( this.spotifyValidateTokens ),
			...Object.keys( this.spotifyValidationPromises ),
		] ).forEach( ( layerId ) =>
			this.invalidateSpotifyValidation( layerId )
		);
		this.spotifyValidationPromises = {};
		this.spotifyAbortControllers = {};

		Object.keys( this.aiFilterGenerations ).forEach( ( layerId ) => {
			this.aiFilterGenerations[ layerId ] += 1;
		} );
		Object.values( this.aiFilterAbortControllers ).forEach(
			( controller ) => controller.abort()
		);
		this.aiFilterAbortControllers = {};
		this.aiFilterErrors = {};
		this.failedArtworkReplacements.clear();
		Object.keys( this.uploadGenerations ).forEach( ( layerId ) => {
			this.uploadGenerations[ layerId ] += 1;
		} );

		this.uppyInstances.forEach( ( uppy ) => {
			try {
				uppy.cancelAll?.();
				uppy.destroy?.();
			} catch {
				// The instance may already have been destroyed by its own teardown.
			}
		} );
		this.uppyInstances.clear();
		this.cancelArtworkOperations?.();

		this.clipartSearchTimers = {};
		this.expireStoreApiCustomisationMerge?.( false );
		this._stateTimers.forEach( window.clearTimeout );
		this._stateTimers.clear();
		this._stateAnimationFrames.forEach( window.cancelAnimationFrame );
		this._stateAnimationFrames.clear();
		this.spotifyModalCloseTimer = null;
		this._galleryPreviewTimer = null;
		this._cropGalleryTimer = null;
		this._cropGalleryCanvas = null;
		this._variationChangeTimer = null;

		this._stateAbortControllers.forEach( ( controller ) =>
			controller.abort()
		);
		this._stateAbortControllers.clear();

		Object.values( this.canvases || {} ).forEach( ( canvas ) =>
			canvas?.dispose?.()
		);
		this.canvases = {};
		this._thumbnailCanvases.forEach( ( canvas ) => canvas?.dispose?.() );
		this._thumbnailCanvases.clear();
	}

	setControlLock( reason, locked ) {
		if ( locked ) {
			this._controlLocks.add( reason );
		} else {
			this._controlLocks.delete( reason );
		}
		this.applyControlLocks();
	}

	applyControlLocks() {
		const locked = this._controlLocks.size > 0;
		const panel = document.getElementById( 'oc-customiser-panel' );
		const cartForm =
			panel?.closest( 'form' ) ||
			document.querySelector(
				'form.cart, form[data-wp-on--submit*="addToCart"]'
			);
		if ( panel ) {
			panel.inert = locked;
			panel.setAttribute( 'aria-busy', locked ? 'true' : 'false' );
		}

		const controls = new Set( [
			...( panel?.querySelectorAll(
				'input:not([type="hidden"]), select, textarea, button'
			) || [] ),
			...( cartForm?.querySelectorAll(
				'input:not([type="hidden"]), select, textarea, button'
			) || [] ),
		] );
		controls.forEach( ( control ) => {
			if ( locked ) {
				if ( control.dataset.ocLockDisabled === undefined ) {
					control.dataset.ocLockDisabled = control.disabled
						? '1'
						: '0';
				}
				control.disabled = true;
				control.setAttribute( 'aria-disabled', 'true' );
				return;
			}
			if ( control.dataset.ocLockDisabled === undefined ) {
				return;
			}
			control.disabled = control.dataset.ocLockDisabled === '1';
			control.setAttribute(
				'aria-disabled',
				control.disabled ? 'true' : 'false'
			);
			delete control.dataset.ocLockDisabled;
		} );
		if ( this._designVariantPendingSeq ) {
			cartForm
				?.querySelectorAll(
					'[type="submit"], .single_add_to_cart_button'
				)
				.forEach( ( control ) => {
					control.disabled = true;
					control.setAttribute( 'aria-disabled', 'true' );
				} );
		}
	}

	restHeaders( extra = {} ) {
		const headers = { ...extra };
		if ( this.data.uploadNonce ) {
			headers[ 'X-WP-Nonce' ] = this.data.uploadNonce;
		}
		if ( this.data.requestToken ) {
			headers[ 'X-OC-Token' ] = this.data.requestToken;
		}
		return headers;
	}

	async ensureRequestToken() {
		const expiresAt = Number( this.data.requestTokenExpiresAt || 0 );
		if ( this.data.requestToken && expiresAt > Date.now() + 300000 ) {
			return;
		}
		if ( ! this.data.requestTokenUrl ) {
			return;
		}
		this.data.requestToken = '';
		this.data.requestTokenExpiresAt = 0;

		const request = this.createStateAbortController( 12000 );
		try {
			const response = await fetch( this.data.requestTokenUrl, {
				credentials: 'same-origin',
				cache: 'no-store',
				headers: { Accept: 'application/json' },
				signal: request.controller.signal,
			} );
			const body = await response.json().catch( () => null );
			const token = typeof body?.token === 'string' ? body.token : '';
			const expiresIn = Number( body?.expires_in );
			const tokenLifetime = Math.max(
				1,
				Number.isFinite( expiresIn ) ? expiresIn : 300
			);
			if ( ! response.ok || ! /^[A-Za-z0-9]{64}$/.test( token ) ) {
				throw new Error(
					body?.message ||
						'Security verification could not be started.'
				);
			}

			this.data.requestToken = token;
			this.data.requestTokenExpiresAt = Date.now() + tokenLifetime * 1000;
			clearTimeout( this._requestTokenRefreshTimer );
			this._requestTokenRefreshTimer = setTimeout(
				() => {
					this.data.requestTokenExpiresAt = 0;
					this.ensureRequestToken().catch( () => {} );
				},
				Math.max( 1000, ( tokenLifetime - 300 ) * 1000 )
			);
		} catch ( error ) {
			if ( request.timedOut() ) {
				throw new Error(
					'Security verification timed out. Please retry.'
				);
			}
			throw error;
		} finally {
			request.release();
		}
	}

	currentVariationId() {
		const panelForm = document
			.getElementById( 'oc-customiser-panel' )
			?.closest( 'form' );
		return (
			parseInt(
				panelForm?.querySelector( '[name="variation_id"]' )?.value ||
					document.querySelector( '[name="variation_id"]' )?.value ||
					'0',
				10
			) || 0
		);
	}

	uploadEndpoint( uploadUrl, layerId ) {
		const variationId = this.currentVariationId();
		const params = new URLSearchParams( {
			layer_id: String( layerId ),
			design_id: String( this.data.designId || '' ),
			product_id: String( this.data.productId || '' ),
			variation_id: String( variationId ),
		} );
		return (
			uploadUrl +
			( uploadUrl.includes( '?' ) ? '&' : '?' ) +
			params.toString()
		);
	}

	// ── Init ───────────────────────────────────────────────────────────────────

	async init() {
		this.findGalleryImage();
		this.preflightRoot = document.getElementById( 'oc-preflight-messages' );
		this.beginDesignStateListeners();
		try {
			await this.ensureRequestToken();
		} catch ( error ) {
			this._requestTokenError =
				error?.message || 'Security verification is unavailable.';
			this.renderPreflightMessages( [ this._requestTokenError ], [] );
		}

		// Hydrate state before listeners read values from the template controls.
		this.seedLockedLayerDefaults();
		this.seedTemplateImageDefaults();
		this.seedLayerFontDefaults();
		await this.hydrateLinkGroupCarry();
		this.seedLinkedImageInputs();
		this.seedLinkedColourInputs();
		this.applyInputsToDOM( { redraw: false } );
		this.setupInputListeners();
		this.setupVariationGalleryHandoff();
		this.setupCartGalleryUnlock();
		this.setupDesignVariantOptions();
		this.setupClipartCarousels();
		this._uploadSetupPromise = this.setupUploadZones();
		if ( ! this._variationSwitchPending ) {
			this._initialAiFilterPromise = this.applyInitialAiFilters();
		}
		this.setupFormSubmit();
		this.setupStoreApiIntegration();
		this.updateHiddenField();
		this.setupDesignVariantCarousel();
		this.renderDesignVariantThumbnails();

		// Canvas init runs in background; calls redraw() when done.
		this.startCanvasInitialisation();
	}
}

Object.assign( OCCustomiser.prototype, inputControlMethods );
Object.assign( OCCustomiser.prototype, cartSerializationMethods );
Object.assign( OCCustomiser.prototype, galleryPreviewMethods );
Object.assign( OCCustomiser.prototype, clipartMethods );
Object.assign( OCCustomiser.prototype, preflightMethods );
Object.assign( OCCustomiser.prototype, spotifyMethods );
Object.assign( OCCustomiser.prototype, uploadMethods );
Object.assign( OCCustomiser.prototype, checkoutMethods );
