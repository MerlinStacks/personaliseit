import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { createRequire } from 'node:module';
import test from 'node:test';
import vm from 'node:vm';
import { JSDOM } from 'jsdom';

const { parse } = createRequire( import.meta.url )( 'acorn' );
async function load( path, globals = {} ) {
	const source = await readFile( path, 'utf8' );
	const tree = parse( source, {
		ecmaVersion: 'latest',
		sourceType: 'module',
	} );
	const names = [];
	const code = tree.body
		.map( ( node ) => {
			if ( node.type === 'ImportDeclaration' ) {
				return '';
			}
			if ( node.type === 'ExportNamedDeclaration' ) {
				const decl = node.declaration;
				names.push(
					...( decl.id
						? [ decl.id.name ]
						: decl.declarations.map( ( d ) => d.id.name ) )
				);
				return source.slice( decl.start, decl.end );
			}
			return source.slice( node.start, node.end );
		} )
		.join( '\n' );
	return vm.runInNewContext(
		`${ code }; ({ ${ names.join( ',' ) } })`,
		globals
	);
}
const admin = 'src/admin/products-page-';
const svg =
	'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><path d="M0 0L100 100" stroke="#ff00ff"/></svg>';
const utils = await load( admin + 'utils.js' );
const math = await load( 'src/shared/render-math.js' );
const metadata = await load( admin + 'metadata.js' );
const tick = () => new Promise( ( resolve ) => setImmediate( resolve ) );

test( 'cut line normalization and form serialization preserve SVG and out-of-area geometry', async () => {
	const { createProductsPageDataNormalisers } = await load(
		admin + 'data.js'
	);
	const normalisers = createProductsPageDataNormalisers( {
		...utils,
		...math,
		nextUid: () => 1,
	} );
	const layer = normalisers.normaliseLayer( {
		type: 'cut_line',
		x: -45,
		y: -30,
		w: 600,
		h: 70,
		settings: { cutLineSvg: svg, required: true },
	} );
	const area = { x: 100, y: 100, w: 50, h: 50, layers: [ layer ] };
	utils.clampLayerToArea( layer, area );
	assert.equal( layer.x, -45 );
	assert.equal( layer.w, 600 );
	assert.equal(
		JSON.stringify( layer.settings ),
		JSON.stringify( { cutLineSvg: svg } )
	);
	assert.equal( normalisers.defaultSettings( 'cut_line' ).cutLineSvg, '' );
	assert.equal(
		normalisers.normaliseSettings( 'cut_line', { cutLineSvg: 42 } )
			.cutLineSvg,
		''
	);
	const dom = new JSDOM( '<div id="oc-hidden-fields"></div>' );
	const { document } = dom.window;
	const { renderProductsPageHiddenFields } = await load(
		admin + 'hidden-fields.js',
		{ document }
	);
	renderProductsPageHiddenFields( [ area ], utils.esc );
	assert.equal(
		JSON.parse(
			document.querySelector( '[name="oc_layers[0][settings]"]' ).value
		).cutLineSvg,
		svg
	);
	assert.equal(
		document.querySelector( '[name="oc_layers[0][x]"]' ).value,
		'-45'
	);
	const ordinary = { type: 'image', x: -45, y: -30, w: 600, h: 70 };
	utils.clampLayerToArea( ordinary, area );
	assert.deepEqual( ordinary, {
		type: 'image',
		x: 100,
		y: 100,
		w: 50,
		h: 50,
	} );
	dom.window.close();
} );

async function canvasHarness( rotation = 0, unit = 'px', scale = 1 ) {
	const dom = new JSDOM(
		'<div id="oc-canvas-stage"><img id="oc-canvas-mockup-img"><div id="oc-canvas-ghosts"></div><div id="oc-bounds-box"><i class="oc-bounds-handle" data-dir="e"></i><i class="oc-bounds-handle" data-dir="n"></i><i class="oc-bounds-handle" data-dir="se"></i></div></div><input id="oc-layer-x"><input id="oc-layer-y"><input id="oc-layer-w"><input id="oc-layer-h"><input id="oc-prop-w">'
	);
	const { document } = dom.window;
	const layer = {
		type: 'cut_line',
		x: -20,
		y: -10,
		w: 160,
		h: 70,
		visible: true,
		settings: { cutLineSvg: svg },
	};
	const area = {
		x: 20,
		y: 30,
		w: 100,
		h: 100,
		unit,
		dpi: 254,
		rotation,
		visible: true,
		layers: [ layer ],
	};
	let selected = 0;
	const cutLine = await load( admin + 'cut-line.js', { document } );
	const { createLayerPreviewRenderer } = await load( admin + 'preview.js', {
		document,
		window: dom.window,
		...cutLine,
	} );
	const { createProductsPageCanvas } = await load( admin + 'canvas.js', {
		document,
		window: dom.window,
		...math,
		...metadata,
	} );
	const noop = () => {};
	const canvas = createProductsPageCanvas( {
		...utils,
		...math,
		getScale: () => scale,
		selectedArea: () => area,
		selectedLayer: () => ( selected >= 0 ? layer : null ),
		getSelectedIndex: () => 0,
		getSelectedLayerIndex: () => selected,
		setSelectedLayerIndex: ( value ) => {
			selected = value;
		},
		getAreas: () => [ area ],
		getDesignMaskEntry: () => null,
		applyLayerPreview: createLayerPreviewRenderer( {
			...utils,
			...metadata,
		} ),
		setVal: ( id, value ) => {
			const el = document.getElementById( id );
			if ( el ) {
				el.value = value;
			}
		},
		renderHiddenFields: noop,
		markDirty: noop,
		snapshot: noop,
		renderAll: noop,
		renderRatioLockButton: noop,
	} );
	canvas.initCanvasInteractions();
	function drag( dir, dx, dy ) {
		const target =
			dir === 'move'
				? document.getElementById( 'oc-bounds-box' )
				: document.querySelector( `[data-dir="${ dir }"]` );
		target.dispatchEvent(
			new dom.window.MouseEvent( 'mousedown', {
				bubbles: true,
				clientX: 100,
				clientY: 100,
			} )
		);
		const rad = ( rotation * Math.PI ) / 180;
		const px = math.unitPxScale( area ) * scale;
		document.dispatchEvent(
			new dom.window.MouseEvent( 'mousemove', {
				clientX:
					100 + ( dx * Math.cos( rad ) - dy * Math.sin( rad ) ) * px,
				clientY:
					100 + ( dx * Math.sin( rad ) + dy * Math.cos( rad ) ) * px,
			} )
		);
		document.dispatchEvent( new dom.window.MouseEvent( 'mouseup' ) );
	}
	return {
		dom,
		document,
		layer,
		area,
		canvas,
		drag,
		selectArea: () => {
			selected = -1;
		},
	};
}

test( 'cut line dragging and independent canvas resizing cross every print boundary at rotated physical-unit scales', async () => {
	for ( const rotation of [ 0, 45, 90, 270 ] ) {
		for ( const unit of [ 'px', 'mm' ] ) {
			const h = await canvasHarness( rotation, unit, 0.5 );
			h.drag( 'move', -100, -100 );
			assert.equal( h.layer.x, -120 );
			assert.equal( h.layer.y, -110 );
			h.drag( 'e', 400, 0 );
			assert.equal( h.layer.w, 560 );
			assert.equal( h.layer.h, 70 );
			h.drag( 'n', 0, -200 );
			assert.equal( h.layer.y, -310 );
			assert.equal( h.layer.h, 270 );
			h.drag( 'se', 40, 500 );
			assert.equal( h.layer.w, 600 );
			assert.equal( h.layer.h, 770 );
			h.drag( 'e', -1000, 0 );
			assert.equal( h.layer.w, 1 );
			h.dom.window.close();
		}
	}
} );

test( 'numeric bounds and area shrink retain cut line geometry; selected and ghost previews stretch without clipping', async () => {
	const h = await canvasHarness();
	for ( const [ field, value ] of [
		[ 'x', -500 ],
		[ 'y', -300 ],
		[ 'w', 900 ],
		[ 'h', 50 ],
	] ) {
		h.document.getElementById( 'oc-layer-' + field ).value = value;
		h.canvas.syncBoundsFromInputs( 'oc-layer-' + field );
		assert.equal( h.layer[ field ], value );
	}
	const before = JSON.stringify( h.layer );
	const selected = h.document.querySelector( '#oc-bounds-box svg' );
	assert.equal( selected.getAttribute( 'preserveAspectRatio' ), 'none' );
	h.selectArea();
	h.document.getElementById( 'oc-prop-w' ).value = 10;
	h.canvas.syncBoundsFromInputs( 'oc-prop-w' );
	assert.equal( JSON.stringify( h.layer ), before );
	const ghost = h.document.querySelector( '.oc-canvas-layer-ghost' );
	assert.equal( ghost.style.left, '-500px' );
	assert.equal( ghost.style.width, '900px' );
	const preview = ghost.querySelector( '.oc-lp-cut-line svg' );
	assert.equal( preview.getAttribute( 'preserveAspectRatio' ), 'none' );
	assert.equal( preview.getAttribute( 'width' ), '100%' );
	assert.equal(
		preview.querySelector( 'path' ).getAttribute( 'stroke' ),
		'#ff00ff'
	);
	assert.equal( h.layer.settings.cutLineSvg, svg );
	const css = await readFile( 'assets/css/admin/layers.css', 'utf8' );
	assert.match(
		css,
		/\.oc-lp-cut-line,\s*\.oc-lp-cut-line > svg\s*\{\s*overflow: visible;/
	);
	h.dom.window.close();
} );

test( 'dimension-only SVGs stretch with the layer rather than keeping their original viewport', async () => {
	const dom = new JSDOM( '<main></main>' );
	const { document } = dom.window;
	const { appendCutLinePreview } = await load( admin + 'cut-line.js', {
		document,
	} );
	const el = document.querySelector( 'main' );
	appendCutLinePreview(
		el,
		'<svg width="200" height="80"><path d="M0 0L200 80"/></svg>'
	);
	const preview = el.querySelector( 'svg' );
	assert.equal( preview.getAttribute( 'viewBox' ), '0 0 200 80' );
	assert.equal( preview.getAttribute( 'preserveAspectRatio' ), 'none' );
	assert.equal( preview.style.width, '100%' );
	assert.equal( preview.style.height, '100%' );
	assert.equal( preview.style.overflow, 'visible' );
	dom.window.close();
} );

test( 'canvas area resize preserves oversized cut lines while constraining ordinary children', async () => {
	const h = await canvasHarness();
	const ordinary = { type: 'image', x: 20, y: 30, w: 100, h: 100 };
	h.area.layers.push( ordinary );
	const before = JSON.stringify( h.layer );
	h.selectArea();
	h.drag( 'se', -90, -80 );
	assert.equal( h.area.w, 10 );
	assert.equal( h.area.h, 20 );
	assert.equal( JSON.stringify( h.layer ), before );
	assert.equal( ordinary.w, 10 );
	assert.equal( ordinary.h, 20 );
	h.dom.window.close();
} );

test( 'upload contract validates extension/size and only accepts successful sanitized responses', async () => {
	let calls = 0;
	let result = { success: true, data: { cutLineSvg: svg } };
	let ok = true;
	const { uploadCutLine } = await load( admin + 'cut-line.js', {
		FormData,
		fetch: async ( url, options ) => {
			calls++;
			assert.equal( url, '/admin-ajax.php' );
			assert.equal( options.method, 'POST' );
			assert.equal( options.body.get( 'action' ), 'oc_upload_cut_line' );
			assert.equal( options.body.get( 'nonce' ), 'editor-nonce' );
			assert.equal( options.body.get( 'file' ).name, 'outline.SVG' );
			return { ok, json: async () => result };
		},
	} );
	const data = { ajaxUrl: '/admin-ajax.php', nonce: 'editor-nonce' };
	for ( const file of [
		new File( [ svg ], 'bad.png' ),
		new File( [], 'empty.svg' ),
		new File( [ 'x'.repeat( 256 * 1024 + 1 ) ], 'large.svg' ),
	] ) {
		await assert.rejects( uploadCutLine( file, data ), /256 KiB/ );
	}
	assert.equal( calls, 0 );
	const file = new File( [ 'x'.repeat( 256 * 1024 ) ], 'outline.SVG' );
	assert.equal( await uploadCutLine( file, data ), svg );
	result = { success: false, data: { message: 'Invalid SVG' } };
	await assert.rejects( uploadCutLine( file, data ), /Invalid SVG/ );
	result = { success: true, data: { cutLineSvg: '' } };
	await assert.rejects( uploadCutLine( file, data ), /Unable to upload/ );
	ok = false;
	result = { success: true, data: { cutLineSvg: svg } };
	await assert.rejects( uploadCutLine( file, data ), /Unable to upload/ );
} );

test( 'settings upload/replace UI commits sanitized SVG, preserves old SVG on failure, and ignores detached uploads', async () => {
	const dom = new JSDOM( '<main></main>' );
	const { document } = dom.window;
	const layer = {
		type: 'cut_line',
		label: 'Cut line',
		x: -10,
		y: -20,
		w: 50,
		h: 80,
		settings: { cutLineSvg: svg },
	};
	const area = { layers: [ layer ] };
	let resolve;
	let commits = 0;
	const cutLine = await load( admin + 'cut-line.js', {
		document,
		window: dom.window,
		FormData,
		fetch: () =>
			new Promise( ( done ) => {
				resolve = done;
			} ),
	} );
	const { createProductsPageSettings } = await load( admin + 'settings.js', {
		document,
		window: dom.window,
		...cutLine,
	} );
	const settings = createProductsPageSettings( {
		...utils,
		selectedArea: () => area,
		getAreas: () => [ area ],
		commitChange: () => {
			commits++;
		},
	} );
	const main = document.querySelector( 'main' );
	main.innerHTML = settings.buildTabContent( 'general', layer );
	assert.equal(
		document.getElementById( 'oc-layer-x' ).hasAttribute( 'min' ),
		false
	);
	assert.equal(
		document.getElementById( 'oc-layer-y' ).hasAttribute( 'min' ),
		false
	);
	assert.equal( document.getElementById( 'oc-set-link-group' ), null );
	main.innerHTML = settings.buildTabContent( 'cut_line', layer );
	assert.match( main.textContent, /Replace cut line SVG/ );
	assert.equal(
		document.getElementById( 'oc-choose-default-attachment' ),
		null
	);
	settings.bindSettingsHandlers( layer );
	const input = document.getElementById( 'oc-cut-line-file' );
	Object.defineProperty( input, 'files', {
		value: [ new File( [ 'raw' ], 'outline.svg' ) ],
	} );
	const start = () => input.dispatchEvent( new dom.window.Event( 'change' ) );
	start();
	assert.equal( input.disabled, true );
	resolve( {
		ok: false,
		json: async () => ( { success: false, data: { message: 'Rejected' } } ),
	} );
	await tick();
	assert.equal( layer.settings.cutLineSvg, svg );
	assert.equal( commits, 0 );
	assert.equal(
		document.getElementById( 'oc-cut-line-status' ).textContent,
		'Rejected'
	);
	start();
	resolve( {
		ok: true,
		json: async () => ( { success: true, data: { cutLineSvg: '<svg/>' } } ),
	} );
	await tick();
	assert.equal( layer.settings.cutLineSvg, '<svg/>' );
	assert.equal( commits, 1 );
	start();
	main.innerHTML = '';
	resolve( {
		ok: true,
		json: async () => ( { success: true, data: { cutLineSvg: svg } } ),
	} );
	await tick();
	assert.equal( layer.settings.cutLineSvg, '<svg/>' );
	assert.equal( commits, 1 );
	dom.window.close();
} );
