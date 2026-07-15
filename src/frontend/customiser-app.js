/**
 * Frontend Customiser — vanilla JS, no framework dependency.
 *
 * Data: window.ocCustomiserData (wp_localize_script).
 * Canvas: Fabric.js 6.x  |  Uploads: Uppy 4.x
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
		this.fontCache = {}; // fontName  → load Promise
		this.outlineFontCache = {}; // fontName  → parsed font Promise
		this.clipartSvgCache = {};
		this.galleryImg = null; // the main <img> in the product gallery
		this._previewUrl = null; // saved preview URL (set just before cart submit)
		this._focusPreviewSlide = false; // jump TVPG to preview slide after user edits
		this._hasCustomerPersonalisation = this.editMode;
		this._tvpgPreviewLocked = false;
		this._galleryPreviewGeneration = 0;
		this.productVariationStates = {};
		this._variationRequestSeq = 0;
		this._designGeneration = 0;
		this.spotifyValidateTimers = {};
		this.spotifyValidateTokens = {};
		this.spotifyAbortControllers = {};
		this.uploadGenerations = {};
		this.preflightRoot = null;
		this.clipartByGroup = {};
		this.clipartSearchTimers = {};
		this.clipartSearchTerms = {};
		this.clipartCategoryFilters = {};
		this.spotifyModalCloseTimer = null;
		this.mobileCartPreviewDialog = null;
		this.formSubmitBound = false;
		this.fontComboboxDocumentClickBound = false;
		this._customisationActive = true;
		this._submitInProgress = false;

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
		this.seedTemplateImageDefaults();
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
		this.setupDesignVariantCarousel();
		this.renderDesignVariantThumbnails();

		// Canvas init runs in background; calls redraw() when done.
		this.initAllCanvases();
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
