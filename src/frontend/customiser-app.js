/**
 * Frontend Customiser — vanilla JS, no framework dependency.
 *
 * Data: window.ocCustomiserData (wp_localize_script).
 * Canvas: Fabric.js 7.x  |  Uploads: Uppy 5.x
 *
 * @package
 */

import '@uppy/core/css/style.min.css';
import '@uppy/drag-drop/css/style.min.css';
import './customiser-app.scss';
import canvasRendererMethods from './customiser/canvas-renderer';
import inputControlMethods from './customiser/input-controls';
import cartSerializationMethods from './customiser/cart-serialization';
import designVariantMethods from './customiser/design-variants';
import galleryPreviewMethods from './customiser/gallery-preview';
import clipartMethods from './customiser/clipart';
import preflightMethods from './customiser/preflight';
import spotifyMethods from './customiser/spotify';
import uploadMethods from './customiser/uploads';
import checkoutMethods from './customiser/checkout';

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
		this._redrawTimers = {};
		this._redrawGenerations = {};
		this._redrawPromises = {};
		this._canvasReadyPromise = Promise.resolve();
		this._canvasReadyGeneration = 0;
		this._canvasLoadTimers = new Set();
		this.fontCache = {}; // font family/weight/style/URL -> load Promise
		this.clipartSvgCache = {};
		this.galleryImg = null; // the main <img> in the product gallery
		this._focusPreviewSlide = false; // jump TVPG to preview slide after user edits
		this._hasCustomerPersonalisation = this.editMode;
		this._tvpgPreviewLocked = false;
		this._galleryPreviewGeneration = 0;
		this._galleryPreviewNodes = new Set();
		this.productVariationStates = {};
		this._variationRequestSeq = 0;
		this._variationSwitchPending = false;
		this._variationSwitchFailed = false;
		this._designGeneration = 0;
		this.spotifyValidateTimers = {};
		this.spotifyValidateTokens = {};
		this.spotifyAbortControllers = {};
		this.uploadGenerations = {};
		this.aiFilterGenerations = {};
		this.aiFilterAbortControllers = {};
		this.aiFilterErrors = {};
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
		this.formSubmitBound = false;
		this.fontComboboxDocumentClickBound = false;
		this._customisationActive = true;
		this._submitInProgress = false;
		this._controlLocks = new Set();
		this._galleryPreviewTimer = null;
		this._variationChangeTimer = null;
		this._mobileCartPreviewResolve = null;
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
		if ( panel ) {
			panel.inert = locked;
			panel.setAttribute( 'aria-busy', locked ? 'true' : 'false' );
		}

		const controls = new Set( [
			...( panel?.querySelectorAll(
				'input:not([type="hidden"]), select, textarea, button'
			) || [] ),
			...( document
				.querySelector( 'form.cart' )
				?.querySelectorAll(
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
		const variationId =
			parseInt(
				document.querySelector( 'form.cart input.variation_id' )
					?.value || '0',
				10
			) || 0;
		const params = new URLSearchParams( {
			layer_id: String( layerId ),
			design_id: String( this.data.designId || '' ),
			product_id: String( this.data.productId || '' ),
			variation_id: String( variationId ),
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

		// Hydrate state before listeners read values from the template controls.
		this.seedLockedLayerDefaults();
		this.seedTemplateImageDefaults();
		this.applyInputsToDOM( { redraw: false } );
		this.setupInputListeners();
		this.setupVariationGalleryHandoff();
		this.setupCartGalleryUnlock();
		this.setupDesignVariantOptions();
		this.setupClipartCarousels();
		this._uploadSetupPromise = this.setupUploadZones();
		this.applyInitialAiFilters();
		this.setupFormSubmit();
		this.updateHiddenField();
		this.setupDesignVariantCarousel();
		this.renderDesignVariantThumbnails();

		// Canvas init runs in background; calls redraw() when done.
		this.startCanvasInitialisation();
	}
}

Object.assign( OCCustomiser.prototype, canvasRendererMethods );
Object.assign( OCCustomiser.prototype, inputControlMethods );
Object.assign( OCCustomiser.prototype, cartSerializationMethods );
Object.assign( OCCustomiser.prototype, designVariantMethods );
Object.assign( OCCustomiser.prototype, galleryPreviewMethods );
Object.assign( OCCustomiser.prototype, clipartMethods );
Object.assign( OCCustomiser.prototype, preflightMethods );
Object.assign( OCCustomiser.prototype, spotifyMethods );
Object.assign( OCCustomiser.prototype, uploadMethods );
Object.assign( OCCustomiser.prototype, checkoutMethods );
