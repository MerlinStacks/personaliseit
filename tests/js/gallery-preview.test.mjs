import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import vm from 'node:vm';
import { JSDOM } from 'jsdom';

const source = await readFile(
	'src/frontend/customiser/gallery-preview.js',
	'utf8'
);
const styles = await readFile( 'src/frontend/customiser-app.scss', 'utf8' );

function createGalleryRuntime( t ) {
	const dom = new JSDOM( `
		<div id="oc-customiser-panel"><div id="oc-canvas-wrap"><img id="oc-canvas-preview"></div></div>
		<div class="woocommerce-product-gallery">
			<div class="tvpg-main-slider"><div class="swiper-wrapper">
				<div class="swiper-slide swiper-slide-active"><div class="woocommerce-product-gallery__image"><a href="original.jpg"><img src="original.jpg"></a></div></div>
				<div class="swiper-slide tvpg-video-slide"></div>
			</div></div>
			<div class="tvpg-thumb-slider"><div class="swiper-wrapper"><div class="swiper-slide"></div><div class="swiper-slide"></div></div></div>
		</div>
	` );
	t.after( () => dom.window.close() );
	const { document } = dom.window;
	const methods = vm.runInNewContext(
		source.replace(
			'export default galleryPreviewMethods;',
			'galleryPreviewMethods;'
		),
		{ window: dom.window, document, console }
	);
	const canvas = {
		toDataURL: () => 'data:image/jpeg;base64,cHJldmlldw==',
		width: 400,
		height: 300,
	};
	const app = Object.assign(
		{
			_customisationActive: true,
			_hasCustomerPersonalisation: true,
			_focusPreviewSlide: true,
			_galleryPreviewGeneration: 0,
			_galleryPreviewNodes: new Set(),
			_galleryFallbackNodeStates: new Map(),
			_tvpgLockedSwipers: new Set(),
			canvases: { 0: canvas },
			activeArea: 0,
		},
		methods
	);
	const main = document.querySelector( '.tvpg-main-slider' );
	const ready = () =>
		document.querySelector( '.woocommerce-product-gallery' ).dispatchEvent(
			new dom.window.CustomEvent( 'tvpg-gallery-ready', {
				bubbles: true,
			} )
		);
	const attachSwiper = () => {
		main.swiper = {
			initialized: true,
			activeIndex: 0,
			slides: [],
			update() {
				this.slides = Array.from(
					main.querySelectorAll( '.swiper-slide' )
				);
			},
			slideTo( index ) {
				this.activeIndex = index;
			},
		};
		return main.swiper;
	};
	return { app, canvas, document, main, ready, attachSwiper };
}

test( 'unavailable TVPG uses the image fallback and preserves focus until readiness', ( t ) => {
	const { app, canvas, document, ready, attachSwiper } =
		createGalleryRuntime( t );
	assert.equal( app.applyTVPGOverlayPreview( 'preview.jpg' ), false );
	assert.equal( document.querySelector( '.oc-live-preview-slide' ), null );
	app.pushToGallery( canvas );
	app.pushToGallery( canvas );
	assert.equal( app._focusPreviewSlide, true );
	assert.equal(
		document.querySelector( '.tvpg-main-slider img' ).src,
		canvas.toDataURL()
	);
	assert.equal(
		document.querySelector( '.oc-live-preview-thumb-slide' ),
		null
	);
	const swiper = attachSwiper();
	// The retry must use the current canvas rather than the earlier fallback snapshot.
	app.canvases[ 0 ] = { ...canvas, toDataURL: () => 'latest-preview.jpg' };
	const generation = app._galleryPreviewGeneration;
	ready();
	assert.equal( app._galleryPreviewGeneration, generation + 1 );
	assert.equal( swiper.activeIndex, 2 );
	assert.equal(
		document
			.querySelector( '.oc-live-preview-slide img' )
			.getAttribute( 'src' ),
		'latest-preview.jpg'
	);
	assert.equal( app._focusPreviewSlide, false );
	assert.equal( app._tvpgReadyRetry, null );
	assert.equal( app._tvpgPreviewLocked, true );
	ready();
	assert.equal( app._galleryPreviewGeneration, generation + 1 );
	assert.equal(
		document.querySelectorAll( '.oc-live-preview-slide' ).length,
		1
	);
} );

test( 'a later push selects the preview even without a readiness event', ( t ) => {
	const { app, canvas, attachSwiper } = createGalleryRuntime( t );
	app.pushToGallery( canvas );
	const swiper = attachSwiper();
	app.pushToGallery( canvas );
	assert.equal( swiper.activeIndex, 2 );
	assert.equal( app._focusPreviewSlide, false );
	assert.equal( app._tvpgReadyRetry, null );
} );

test( 'restoring the gallery cancels a pending readiness retry', ( t ) => {
	const { app, canvas, document, ready, attachSwiper } =
		createGalleryRuntime( t );
	app.pushToGallery( canvas );
	app.restoreProductGallery();
	attachSwiper();
	ready();
	assert.equal( app._tvpgReadyRetry, null );
	assert.equal( app._focusPreviewSlide, false );
	assert.equal( document.querySelector( '.oc-live-preview-slide' ), null );
	assert.equal(
		document.querySelector( '.tvpg-main-slider img' ).getAttribute( 'src' ),
		'original.jpg'
	);
} );

test( 'destroyed and not-yet-initialized Swipers cannot accept a handoff', ( t ) => {
	const { app, canvas, document, attachSwiper } = createGalleryRuntime( t );
	const swiper = attachSwiper();
	for ( const state of [
		{ initialized: false, destroyed: false },
		{ initialized: true, destroyed: true },
	] ) {
		Object.assign( swiper, state );
		app.pushToGallery( canvas );
		assert.equal( app._focusPreviewSlide, true );
		assert.equal(
			document.querySelector( '.oc-live-preview-slide' ),
			null
		);
	}
} );

test( 'readiness ignores unrelated galleries and waits for a usable active canvas', ( t ) => {
	const { app, canvas, document, ready, attachSwiper } =
		createGalleryRuntime( t );
	app.pushToGallery( canvas );
	const swiper = attachSwiper();
	const generation = app._galleryPreviewGeneration;
	document.getElementById( 'oc-customiser-panel' ).dispatchEvent(
		new document.defaultView.CustomEvent( 'tvpg-gallery-ready', {
			bubbles: true,
		} )
	);
	assert.equal( app._galleryPreviewGeneration, generation );
	for ( const activeCanvas of [
		null,
		{ ...canvas, _ocMissingMockup: true },
	] ) {
		app.canvases[ 0 ] = activeCanvas;
		ready();
		assert.equal( app._galleryPreviewGeneration, generation );
	}
	app.canvases[ 0 ] = canvas;
	app._customisationActive = false;
	ready();
	assert.equal( document.querySelector( '.oc-live-preview-slide' ), null );
	app._customisationActive = true;
	app._hasCustomerPersonalisation = false;
	ready();
	assert.equal( document.querySelector( '.oc-live-preview-slide' ), null );
	app._hasCustomerPersonalisation = true;
	ready();
	assert.equal( swiper.activeIndex, 2 );
} );

test( 'an early readiness signal preserves focus until Swiper recognizes the preview', ( t ) => {
	const { app, canvas, ready, attachSwiper } = createGalleryRuntime( t );
	app.pushToGallery( canvas );
	ready();
	assert.equal( app._focusPreviewSlide, true );
	const swiper = attachSwiper();
	const update = swiper.update;
	swiper.update = () => {};
	ready();
	assert.equal( app._focusPreviewSlide, true );
	assert.equal( swiper.activeIndex, 0 );
	swiper.update = update;
	ready();
	assert.equal( swiper.activeIndex, 2 );
	assert.equal( app._focusPreviewSlide, false );
	assert.equal( app._tvpgReadyRetry, null );
} );

test( 'live preview supplies dimensions required by gallery lightboxes', () => {
	assert.match(
		source,
		/a\.setAttribute\(\s*'data-size',\s*`\$\{ dimensions\.width \}x\$\{ dimensions\.height \}`\s*\)/
	);
	assert.match(
		source,
		/img\.setAttribute\( 'data-large_image_width', dimensions\.width \);/
	);
	assert.match(
		source,
		/img\.setAttribute\( 'data-large_image_height', dimensions\.height \);/
	);
} );

test( 'TVPG live preview uses the linked WooCommerce image structure', () => {
	assert.match(
		source,
		/'<div class="woocommerce-product-gallery__image">' \+\s*'<a>' \+\s*'<img class="oc-live-preview-image" alt="Custom preview">' \+\s*'<\/a>'/
	);
} );

test( 'gallery previews use a managed blob URL instead of a large data URL', () => {
	assert.match(
		source,
		/const galleryUrl = this\.createGalleryPreviewUrl\( dataUrl \);/
	);
	assert.match(
		source,
		/window\.URL\.createObjectURL\(\s*new Blob\( \[ bytes \], \{ type: match\[ 1 \] \} \)/
	);
	assert.match(
		source,
		/window\.URL\?\.revokeObjectURL\?\.\( this\._galleryPreviewObjectUrl \);/
	);
} );

test( 'preview frame does not combine aspect ratio with padding compensation', () => {
	assert.doesNotMatch( source, /const ratioPadding/ );
	assert.doesNotMatch(
		source,
		/link\.classList\.add\( 'oc-live-preview-frame' \)/
	);
	assert.match(
		source,
		/galleryItem\.style\.aspectRatio = aspectRatio;[\s\S]*?galleryItem\.style\.paddingBottom = '0';/
	);
} );

test( 'custom previews open in an accessible modal lightbox', () => {
	assert.match( source, /dialog = document\.createElement\( 'dialog' \);/ );
	assert.match( source, /dialog\.showModal\(\);/ );
	assert.match( source, /aria-label="Close preview"/ );
	assert.match( source, /this\.openGalleryPreviewLightbox\( img \);/ );
	assert.match( styles, /\.oc-gallery-preview-lightbox/ );
	assert.match( styles, /&::backdrop/ );
} );
