import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

async function shared( path ) {
	return import(
		`data:text/javascript;base64,${ Buffer.from(
			await readFile( path, 'utf8' )
		).toString( 'base64' ) }`
	);
}
const math = await shared( 'src/shared/render-math.js' );
const layout = await shared( 'src/shared/text-layout.js' );
class Text {
	constructor( text, options ) {
		Object.assign(
			this,
			{ scaleX: 1, scaleY: 1, width: 200, height: 10 },
			options
		);
		this._textLines = [ [ ...text ] ];
	}
	set( values ) {
		Object.assign( this, values );
	}
	initDimensions() {}
	setCoords() {}
}
const dependencies = { ...math, ...layout, FabricText: Text, Textbox: Text };
const source = await readFile(
	'src/frontend/customiser/canvas-renderer.js',
	'utf8'
);
const methods = new Function(
	...Object.keys( dependencies ),
	source
		.replace( /import\s*\{[\s\S]*?\}\s*from\s*'[^']+';/g, '' )
		.replace(
			'export default canvasRendererMethods;',
			'return canvasRendererMethods;'
		)
)( ...Object.values( dependencies ) );

function fixture( type = 'text', settings = {}, unit = 'px', scale = 1 ) {
	const area = { x: 0, y: 0, w: 200, h: 100, unit, dpi: 254 };
	const canvas = {
		_ocArea: area,
		_ocScaleX: scale,
		add( obj ) {
			this.object = obj;
		},
	};
	const layer = { type, x: 0, y: 0, w: 100, h: 20, settings };
	const input = { value: 'Long text', fontId: 1 };
	const app = {
		...methods,
		fonts: [ { id: 1, name: 'Actual', url: '/font.woff' } ],
		areaBounds: ( value ) => value,
		rotatedLayerCenter: ( box ) => ( {
			x: box.x + box.w / 2,
			y: box.y + box.h / 2,
		} ),
		engravingPalette: () => ( {} ),
		loadFont: async () => true,
		rectClipPath() {},
		textClipPadding: () => 0,
		applyContentClip() {},
		centerObjectBounds() {},
		keepObjectInsidePrintArea() {},
		textFitsBox: () => true,
		measureSingleLineText: () => ( { width: 400 } ),
	};
	return {
		app,
		canvas,
		layer,
		input,
		area,
		render: () => app.renderLayer( canvas, layer, input, area ),
	};
}

test( 'non-engraving rendering does not request the engraving chunk', async () => {
	const f = fixture();
	f.app.engravingPalette = () => {
		assert.fail( 'non-engraving must not load engraving preview code' );
	};
	await f.render();
	assert.ok( f.canvas.object );
} );

test( 'variation switching during the engraving import cannot paint stale content', async () => {
	const f = fixture();
	f.area.printMethod = 'engraving';
	let resolvePalette;
	f.app.engravingPalette = () =>
		new Promise( ( resolve ) => {
			resolvePalette = resolve;
		} );
	f.app.loadFont = () => assert.fail( 'stale render must stop before fonts' );
	let current = true;
	const render = f.app.renderLayer(
		f.canvas,
		f.layer,
		f.input,
		f.area,
		() => current
	);
	assert.equal( typeof resolvePalette, 'function' );
	assert.equal( f.canvas.object, undefined );
	current = false;
	resolvePalette( { text: '#747873' } );
	await render;
	assert.equal( f.canvas.object, undefined );
	assert.equal( f.input.renderedLayoutVersion, undefined );
} );

test( 'captures final single-line compression and auto-size in canonical units', async () => {
	const f = fixture( 'text', { default_font_size: 0 }, 'mm', 0.2 );
	await f.render();
	assert.equal( f.input.renderedLayoutVersion, 1 );
	assert.equal( f.input.renderedScaleX, 0.5 );
	assert.equal( f.input.renderedScaleX, f.canvas.object.scaleX );
	assert.equal( f.input.renderedInsetX, 0 );
	assert.ok( Math.abs( f.input.renderedFontSize - 14.4 ) < 1e-12 );
} );

test( 'captures fitted size and textarea inset as fraction of outer width per side', async () => {
	const f = fixture( 'textarea', { default_font_size: 20 } );
	f.app.textFitsBox = ( raw, font, size ) => size <= 12;
	await f.render();
	assert.equal( f.input.renderedFontSize, 12 );
	assert.equal( f.input.renderedInsetX, 0.01 );
	assert.equal( f.input.renderedScaleX, 1 );
	assert.deepEqual( f.input.renderedLines, [ 'Long text' ] );
} );

test( 'explicit zero selects auto-size; canonical sub-4 sizes survive display scaling', async () => {
	const f = fixture( 'text', { default_font_size: 24 }, 'in', 0.1 );
	f.input.fontSize = 0;
	f.layer.h = 0.25;
	await f.render();
	assert.ok( Math.abs( f.input.renderedFontSize - 0.18 ) < 1e-12 );
} );

test( 'auto-size obeys canonical min/max including contradictory limits', async () => {
	for ( const [ min, max, expected ] of [
		[ 0, 10, 10 ],
		[ 18, 24, 18 ],
		[ 18, 10, 10 ],
	] ) {
		const f = fixture( 'text', {
			default_font_size: 0,
			min_font_size: min,
			max_font_size: max,
		} );
		await f.render();
		assert.equal( f.input.renderedFontSize, expected );
	}
} );

test( 'failed final rendering and superseded renders cannot publish layout', async () => {
	const f = fixture();
	await f.render();
	f.canvas.add = () => {
		throw new Error( 'canvas failure' );
	};
	await assert.rejects( f.render() );
	assert.equal( 'renderedLayoutVersion' in f.input, false );
	assert.equal( 'renderedFontSize' in f.input, false );
	let current = true;
	f.app.loadFont = async () => {
		current = false;
		return true;
	};
	await f.app.renderLayer(
		f.canvas,
		f.layer,
		f.input,
		f.area,
		() => current
	);
	assert.equal( 'renderedLayoutVersion' in f.input, false );
} );

test( 'clears stale layout before awaiting fonts, on empty text, and fallback font renders', async () => {
	for ( const mode of [ 'empty', 'missing', 'failed', 'unverified' ] ) {
		const f = fixture( 'textarea' );
		await f.render();
		const fields = [
			'renderedFontSize',
			'renderedScaleX',
			'renderedInsetX',
			'renderedLayoutVersion',
			'renderedLines',
		];
		if ( mode === 'empty' ) {
			f.input.value = '';
		}
		if ( mode === 'missing' ) {
			f.app.fonts = [];
		}
		f.app.loadFont = async () => {
			for ( const key of fields ) {
				assert.equal( key in f.input, false );
			}
			if ( mode === 'failed' ) {
				throw new Error( 'network' );
			}
			return false;
		};
		await f.render();
		for ( const key of fields ) {
			assert.equal( key in f.input, false, `${ mode }: ${ key }` );
		}
	}
} );

test( 'font loader rejects incomplete metadata and only verifies loaded FontFace', async () => {
	const previous = {
		FontFace: globalThis.FontFace,
		document: globalThis.document,
	};
	try {
		const app = { ...methods, fontCache: {} };
		assert.equal( await app.loadFont( { name: 'Missing URL' } ), false );
		let added = 0;
		globalThis.document = {
			fonts: {
				add() {
					added++;
				},
			},
		};
		globalThis.FontFace = class {
			status = 'loaded';
			async load() {
				return this;
			}
		};
		assert.equal(
			await app.loadFont( { name: 'Real', url: '/real.woff' } ),
			true
		);
		assert.equal( added, 1 );
		globalThis.FontFace = class {
			async load() {
				return { status: 'error' };
			}
		};
		await assert.rejects(
			app.loadFont( { name: 'Bad', url: '/bad.woff' } )
		);
		assert.equal( added, 1 );
	} finally {
		Object.assign( globalThis, previous );
	}
} );

test( 'checkout flush succeeds on font fallback and clears previously verified metadata', async () => {
	for ( const failure of [ 'missing', 'metadata', 'network' ] ) {
		const f = fixture();
		f.layer.id = 7;
		f.area.layers = [ f.layer ];
		Object.assign( f.canvas, { getObjects: () => [], renderAll() {} } );
		Object.assign( f.app, {
			areas: [ f.area ],
			canvases: [ f.canvas ],
			inputs: { 7: f.input },
			_redrawTimers: {},
			_redrawGenerations: {},
			_redrawPromises: {},
			_customisationActive: true,
			awaitCanvasReady: async () => {},
			areaCanvasGroupIndexes: () => [ 0 ],
		} );
		await f.app.flushRedraw( f.app.inputs, { pushGallery: false } );
		assert.equal( f.input.renderedLayoutVersion, 1 );
		if ( failure === 'missing' ) {
			f.app.fonts = [];
		}
		f.app.loadFont = async () => {
			if ( failure === 'network' ) {
				throw new Error( 'offline' );
			}
			return false;
		};
		await f.app.flushRedraw( f.app.inputs, { pushGallery: false } );
		assert.equal( f.canvas.object.fontFamily, 'sans-serif' );
		assert.deepEqual( f.canvas._ocRenderErrors, [] );
		for ( const key of [
			'renderedLayoutVersion',
			'renderedFontSize',
			'renderedScaleX',
			'renderedInsetX',
			'renderedLines',
		] ) {
			assert.equal( key in f.input, false );
		}
		f.app.fonts = [ { id: 1, name: 'Actual' } ];
		f.app.loadFont = async () => true;
		await f.app.flushRedraw( f.app.inputs, { pushGallery: false } );
		assert.deepEqual( f.canvas._ocRenderErrors, [] );
		assert.equal( f.input.renderedLayoutVersion, 1 );
		f.canvas.add = () => {
			throw new Error( 'artwork failure' );
		};
		await f.app.flushRedraw( f.app.inputs, { pushGallery: false } );
		assert.deepEqual( f.canvas._ocRenderErrors, [
			{ layerId: 7, message: 'artwork failure' },
		] );
		assert.equal( 'renderedLayoutVersion' in f.input, false );
		f.canvas.renderAll = () => {
			throw new Error( 'canvas failure' );
		};
		await assert.rejects(
			f.app.flushRedraw( f.app.inputs, { pushGallery: false } ),
			/canvas failure/
		);
	}
} );

test( 'textarea only publishes versioned metadata with matching lines within the 200-line limit', async () => {
	for ( const lines of [
		undefined,
		[],
		[ 'Wrong' ],
		Array( 201 ).fill( 'Long text' ),
	] ) {
		const f = fixture( 'textarea' );
		f.canvas.add = ( obj ) => {
			obj._textLines = lines;
		};
		await f.render();
		for ( const key of [
			'renderedLayoutVersion',
			'renderedFontSize',
			'renderedScaleX',
			'renderedInsetX',
			'renderedLines',
		] ) {
			assert.equal( key in f.input, false );
		}
	}
	for ( const lines of [
		[ 'Long', 'text' ],
		[ 'Lo', 'ng text' ],
		[ 'Long text', ...Array( 199 ).fill( '' ) ],
	] ) {
		const f = fixture( 'textarea' );
		f.canvas.add = ( obj ) => {
			obj._textLines = lines;
		};
		await f.render();
		assert.equal( f.input.renderedLayoutVersion, 1 );
		assert.deepEqual( f.input.renderedLines, lines );
	}
} );

test( 'invalid final geometry never replaces cleared verified metadata', async () => {
	for ( const geometry of [
		{ fontSize: NaN },
		{ fontSize: Infinity },
		{ fontSize: 0 },
		{ scaleX: NaN },
		{ scaleX: Infinity },
		{ scaleX: 0 },
		{ scaleX: 1.01 },
	] ) {
		const f = fixture();
		await f.render();
		assert.equal( f.input.renderedLayoutVersion, 1 );
		f.canvas.add = ( obj ) => Object.assign( obj, geometry );
		await f.render();
		assert.equal( 'renderedLayoutVersion' in f.input, false );
		assert.equal( 'renderedFontSize' in f.input, false );
	}
	for ( const width of [ NaN, -Infinity, 0 ] ) {
		const f = fixture( 'textarea' );
		f.canvas.add = ( obj ) => {
			obj.width = width;
		};
		await f.render();
		assert.equal( 'renderedLayoutVersion' in f.input, false );
	}
} );
