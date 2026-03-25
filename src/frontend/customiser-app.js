/**
 * Frontend Customiser — vanilla JS, no framework dependency.
 *
 * Data: window.ocCustomiserData (wp_localize_script).
 * Canvas: Fabric.js 6.x  |  Uploads: Uppy 4.x
 *
 * @package OverCustomise
 */

import { StaticCanvas, FabricImage, FabricText, Rect } from 'fabric';
import Uppy      from '@uppy/core';
import DragDrop  from '@uppy/drag-drop';
import XHRUpload from '@uppy/xhr-upload';

import '@uppy/core/dist/style.min.css';
import '@uppy/drag-drop/dist/style.min.css';

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

		this.canvases      = {};   // areaIndex → Fabric StaticCanvas
		this.fontCache     = {};   // fontName  → load Promise
		this.galleryImg    = null; // the main <img> in the product gallery
		this._previewUrl   = null; // saved preview URL (set just before cart submit)
	}

	// ── Init ───────────────────────────────────────────────────────────────────

	init() {
		this.findGalleryImage();

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
		this.setupUploadZones();
		this.setupFormSubmit();
		this.updateHiddenField();

		// Canvas init runs in background; calls redraw() when done.
		this.initAllCanvases();
	}

	// ── Gallery: find the <img> and store a reference ─────────────────────────

	findGalleryImage() {
		const SELECTORS = [
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
				console.log( '[OC] Gallery img found:', sel, img.src.slice( 0, 60 ) );
				return;
			}
		}
		console.warn( '[OC] Gallery <img> not found — preview will show only in panel.' );
	}

	pushToGallery( canvas ) {
		if ( ! this.galleryImg ) return;
		try {
			const dataUrl = canvas.toDataURL( { format: 'jpeg', quality: 0.92 } );
			this.galleryImg.src    = dataUrl;
			this.galleryImg.srcset = '';
			// Update zoom / lightbox href if wrapped in <a>.
			const a = this.galleryImg.closest( 'a' );
			if ( a ) a.href = dataUrl;
			// WooCommerce zoom plugin reads this attribute.
			this.galleryImg.setAttribute( 'data-large_image', dataUrl );
		} catch ( e ) {
			console.warn( '[OC] toDataURL failed — image may be cross-origin:', e.message );
		}
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
			await this.renderLayer( canvas, layer, this.inputs[ layer.id ] || {} );
		}

		canvas.renderAll();
		if ( areaIndex === this.activeArea ) this.pushToGallery( canvas );
	}

	async renderLayer( canvas, layer, input ) {
		const scale = canvas._ocScaleX ?? 1;
		const lx    = layer.x * scale;
		const ly    = layer.y * scale;
		const lw    = Math.max( layer.w * scale, 10 );
		const lh    = Math.max( layer.h * scale, 10 );

		switch ( layer.type ) {

			case 'text':
			case 'textarea': {
				const raw  = ( input.value || '' ).trim() || ( layer.settings?.default_text || '' ).trim();
				if ( ! raw ) break;

				const font  = this.fonts.find( f => f.id === ( input.fontId || 0 ) );
				const color = input.colorHex || '#000000';
				const align = layer.settings?.alignment || 'center';
				if ( font ) await this.loadFont( font );

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

				while ( obj.width > lw && fontSize > 8 ) {
					fontSize -= 1; obj.set( { fontSize } );
				}
				canvas.add( obj );
				break;
			}

			case 'image':
				if ( input.attachmentUrl ) await this.renderFabricImg( canvas, input.attachmentUrl, lx, ly, lw, lh );
				break;

			case 'clipart':
				if ( input.clipartUrl ) await this.renderFabricImg( canvas, input.clipartUrl, lx, ly, lw, lh );
				break;

			case 'lineart': {
				if ( ! input.colorHex || input.colorHex === '#000000' ) break;
				const r = new Rect( { left: lx, top: ly, width: lw, height: lh,
					fill: input.colorHex, opacity: 0.6, selectable: false, evented: false } );
				r._ocContent = true;
				canvas.add( r );
				break;
			}

			case 'spotify': {
				const val = ( input.value || '' ).trim();
				if ( ! val ) break;
				const obj = new FabricText( '\u266b ' + val, {
					left: lx + lw / 2, top: ly + lh / 2,
					originX: 'center', originY: 'center',
					fontFamily: 'monospace', fontSize: Math.max( 9, Math.round( lh * 0.25 ) ),
					fill: '#1db954', textAlign: 'center', selectable: false, evented: false,
				} );
				obj._ocContent = true;
				canvas.add( obj );
				break;
			}
		}
	}

	async renderFabricImg( canvas, url, x, y, w, h ) {
		try {
			const img = await FabricImage.fromURL( url ); // same-origin, no crossOrigin needed
			const s   = Math.min( w / img.width, h / img.height );
			img.set( { left: x + w / 2, top: y + h / 2, originX: 'center', originY: 'center',
				scaleX: s, scaleY: s, selectable: false, evented: false } );
			img._ocContent = true;
			canvas.add( img );
		} catch { /* skip */ }
	}

	async loadFont( font ) {
		if ( ! font?.name || ! font?.url ) return;
		if ( this.fontCache[ font.name ] ) return this.fontCache[ font.name ];
		const ff = new FontFace( font.name, `url('${ font.url }')`, {
			weight: font.weight || 'normal', style: font.style || 'normal',
		} );
		this.fontCache[ font.name ] = ff.load().then( f => document.fonts.add( f ) ).catch( () => {} );
		return this.fontCache[ font.name ];
	}

	// ── Input listeners ─────────────────────────────────────────────────────────

	setupInputListeners() {
		// Area tabs
		document.querySelectorAll( '.oc-area-tab' ).forEach( btn => {
			btn.addEventListener( 'click', () => this.switchArea( parseInt( btn.dataset.areaIndex, 10 ) ) );
		} );

		// Text / textarea
		document.querySelectorAll( '[data-oc-layer-text]' ).forEach( el => {
			const lid = parseInt( el.dataset.ocLayerText, 10 );
			el.addEventListener( 'input', () => {
				if ( ! this.inputs[ lid ] ) this.inputs[ lid ] = {};
				this.inputs[ lid ].value = el.value;
				this.scheduleRedraw();
				this.updateHiddenField();
			} );
		} );

		// Font selects
		document.querySelectorAll( '[data-oc-layer-font]' ).forEach( el => {
			const lid = parseInt( el.dataset.ocLayerFont, 10 );
			el.addEventListener( 'change', async () => {
				if ( ! this.inputs[ lid ] ) this.inputs[ lid ] = {};
				this.inputs[ lid ].fontId = parseInt( el.value, 10 );
				const font = this.fonts.find( f => f.id === this.inputs[ lid ].fontId );
				if ( font ) await this.loadFont( font );
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
				this.scheduleRedraw();
				this.updateHiddenField();
			} );
		} );

		// Remove uploaded image
		document.querySelectorAll( '[data-oc-remove-image]' ).forEach( btn => {
			btn.addEventListener( 'click', () => {
				const lid = parseInt( btn.dataset.ocRemoveImage, 10 );
				if ( this.inputs[ lid ] ) { this.inputs[ lid ].attachmentId = 0; this.inputs[ lid ].attachmentUrl = ''; }
				btn.closest( '.oc-artwork-wrap' )?.querySelector( '.oc-artwork-preview' )
					?.setAttribute( 'style', 'display:none' );
				this.scheduleRedraw();
				this.updateHiddenField();
			} );
		} );
	}

	// ── Form submit — upload preview then proceed ──────────────────────────────

	setupFormSubmit() {
		const form = document.querySelector( 'form.cart' );
		if ( ! form ) return;

		form.addEventListener( 'submit', async e => {
			if ( form._ocSubmitReady ) return; // preview already saved — let submit through
			e.preventDefault();
			await this.uploadPreview();
			form._ocSubmitReady = true;
			// requestSubmit() re-triggers HTML5 validation before submitting.
			if ( form.requestSubmit ) {
				form.requestSubmit( form.querySelector( '[type="submit"]' ) );
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
			console.warn( '[OC] Could not capture preview for cart:', e.message );
			return;
		}

		try {
			const res  = await fetch( this.data.savePreviewUrl, {
				method:  'POST',
				headers: { 'Content-Type': 'application/json', 'X-OC-Nonce': this.data.uploadNonce },
				body:    JSON.stringify( { image: dataUrl } ),
			} );
			const json = await res.json();
			if ( json.url ) {
				this._previewUrl = json.url;
				this.updateHiddenField(); // embed previewUrl in the cart payload
			}
		} catch ( e ) {
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
	}

	// ── Uppy upload zones ────────────────────────────────────────────────────────

	setupUploadZones() {
		document.querySelectorAll( '[data-oc-upload-zone]' ).forEach( zoneEl => {
			const lid = parseInt( zoneEl.dataset.ocUploadZone, 10 );
			if ( ! lid ) return;
			const allowed = ( this.data.allowedFormats || [] ).map( ext => `.${ ext }` );
			const uppy = new Uppy( {
				autoProceed: true,
				restrictions: { maxNumberOfFiles: 1,
					maxFileSize: ( this.data.maxUploadSizeMb || 10 ) * 1024 * 1024,
					allowedFileTypes: allowed.length ? allowed : [ '.jpg', '.png', '.svg', '.pdf' ] },
			} );
			uppy.use( DragDrop, { target: zoneEl } );
			uppy.use( XHRUpload, { endpoint: this.data.uploadUrl, formData: true,
				fieldName: 'artwork', headers: { 'X-OC-Nonce': this.data.uploadNonce } } );
			uppy.on( 'upload-success', ( file, res ) => {
				if ( ! res?.body ) return;
				if ( ! this.inputs[ lid ] ) this.inputs[ lid ] = {};
				this.inputs[ lid ].attachmentId  = res.body.attachment_id || 0;
				this.inputs[ lid ].attachmentUrl = res.body.preview_url   || '';
				const preview = zoneEl.closest( '.oc-artwork-wrap' )?.querySelector( '.oc-artwork-preview' );
				if ( preview ) { preview.querySelector( 'img' ).src = this.inputs[ lid ].attachmentUrl; preview.style.display = ''; }
				this.scheduleRedraw();
				this.updateHiddenField();
			} );
		} );
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
