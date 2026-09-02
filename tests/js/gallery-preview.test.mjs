import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const source = await readFile(
	'src/frontend/customiser/gallery-preview.js',
	'utf8'
);

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
	assert.match(
		source,
		/galleryItem\.style\.aspectRatio = aspectRatio;[\s\S]*?galleryItem\.style\.paddingBottom = '0';/
	);
} );

test( 'synthetic slides proxy clicks through an existing native lightbox link', () => {
	assert.match( source, /bindPreviewToNativeLightbox\( previewSlide \);/ );
	assert.match(
		source,
		/bindPreviewToNativeLightbox\( mainPreviewSlide \);/
	);
	assert.match( source, /nativeAnchor\.click\(\);/ );
} );
