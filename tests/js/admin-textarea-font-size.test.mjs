import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { createRequire } from 'node:module';
import test from 'node:test';
import vm from 'node:vm';
import { JSDOM } from 'jsdom';

const { parse } = createRequire( import.meta.url )( 'acorn' );
const source = await readFile( 'src/admin/products-page-preview.js', 'utf8' );
const tree = parse( source, { ecmaVersion: 'latest', sourceType: 'module' } );
const code = tree.body
	.map( ( node ) => {
		if ( node.type === 'ImportDeclaration' ) {
			return '';
		}
		return source.slice( node.declaration?.start ?? node.start, node.end );
	} )
	.join( '\n' );

function previewSize( settings, scale = 1, type = 'textarea' ) {
	const { window } = new JSDOM( '<div id="preview"></div>' );
	let rendered;
	class Text {
		constructor( text, options ) {
			Object.assign( this, options );
			this.width = 100;
			this.height = options.fontSize * 4;
		}
		set( options ) {
			Object.assign( this, options );
		}
		getScaledHeight() {
			return this.height;
		}
	}
	class Canvas {
		add( object ) {
			rendered = object;
		}
		renderAll() {}
	}
	const create = vm.runInNewContext(
		`${ code }; createLayerPreviewRenderer`,
		{
			window,
			document: window.document,
			StaticCanvas: Canvas,
			Textbox: Text,
			FabricText: Text,
			layoutMultilineTextbox() {},
			multilineTextboxFits: ( object, width, height ) =>
				object.height <= height,
		}
	);
	const renderer = create( {
		fontLimit: ( value ) => Math.max( 0, Number( value ) || 0 ),
		layerLabel: () => 'Text',
		normaliseHex: () => '#000000',
	} );
	renderer(
		{ type, h: 40, settings },
		window.document.getElementById( 'preview' ),
		200 * scale,
		40 * scale,
		false,
		false
	);
	window.close();
	return rendered.fontSize;
}

test( 'textarea preview honours explicit defaults instead of shrinking to the minimum', () => {
	assert.equal(
		previewSize( { default_font_size: 24, min_font_size: 8 } ),
		24
	);
	assert.equal(
		previewSize( { default_font_size: 32, min_font_size: 8 } ),
		32
	);
	assert.equal(
		previewSize( { default_font_size: 24, min_font_size: 12 } ),
		24
	);
} );

test( 'explicit textarea defaults still respect bounds and preview scale', () => {
	assert.equal(
		previewSize( { default_font_size: 24, max_font_size: 20 } ),
		20
	);
	assert.equal(
		previewSize( { default_font_size: 6, min_font_size: 8 } ),
		8
	);
	assert.equal( previewSize( { default_font_size: 24 }, 0.5 ), 12 );
} );

test( 'automatic textarea sizing and single-line fitting remain enabled', () => {
	assert.ok( previewSize( { default_font_size: 0 } ) <= 10 );
	assert.equal(
		previewSize( { default_font_size: 0, min_font_size: 12 } ),
		12
	);
	assert.equal( previewSize( { default_font_size: 24 }, 1, 'text' ), 10 );
} );
