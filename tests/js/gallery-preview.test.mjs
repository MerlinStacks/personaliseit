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
