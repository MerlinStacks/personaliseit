import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import { Circle, Ellipse, Rect } from 'fabric';
import { JSDOM } from 'jsdom';

const stripImports = ( source ) =>
	source.replace( /import\s+[\s\S]*?from\s+['"][^'"]+['"];\n/g, '' );
const rendererSource = await readFile( 'src/frontend/customiser/canvas-renderer.js', 'utf8' );
const renderer = new Function( 'Circle', 'Ellipse', 'Rect',
	stripImports( rendererSource ).replace( 'export default canvasRendererMethods;', 'return canvasRendererMethods;' )
)( Circle, Ellipse, Rect );
const previewSource = await readFile( 'src/admin/products-page-preview.js', 'utf8' );

test( 'oval preview and storefront crop follow independent width and height at any rotation', () => {
	const { window } = new JSDOM();
	const preview = new Function( 'document', 'window',
		stripImports( previewSource ).replace( 'export function', 'function' ) + '\nreturn createLayerPreviewRenderer({});'
	)( window.document, window );
	for ( const [ width, height ] of [ [ 200, 100 ], [ 100, 200 ], [ 100, 100 ] ] ) {
		for ( const shape of [ 'circle', 'oval' ] ) {
			const crop = renderer.layerClipPath( 10, 20, width, height, 30, { mask_shape: shape } );
			assert.equal( crop.left, 10 + width / 2 );
			assert.equal( crop.top, 20 + height / 2 );
			if ( shape === 'oval' ) {
				assert.ok( crop instanceof Ellipse );
				assert.equal( crop.angle, 30 );
			}
			for ( const ghost of [ false, true ] ) {
				const el = window.document.createElement( 'div' );
				preview( { type: 'clipmask', settings: { mask_shape: shape } }, el, width, height, ghost, false );
				const outline = el.querySelector( '.oc-lp-clipmask-rounded' );
				assert.equal( parseFloat( outline.style.width ), crop.width );
				assert.equal( parseFloat( outline.style.height ), crop.height );
			}
		}
	}
} );
