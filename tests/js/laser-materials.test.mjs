import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { createRequire } from 'node:module';
import test from 'node:test';
import vm from 'node:vm';
import { JSDOM } from 'jsdom';

const { parse } = createRequire( import.meta.url )( 'acorn' );
async function load( path, name, context = {} ) {
	const source = await readFile( path, 'utf8' );
	const tree = parse( source, {
		ecmaVersion: 'latest',
		sourceType: 'module',
	} );
	const code = tree.body
		.filter( ( node ) => node.type !== 'ImportDeclaration' )
		.map( ( node ) =>
			source.slice( node.declaration?.start ?? node.start, node.end )
		)
		.join( '\n' );
	return vm.runInNewContext( code + `; ${ name }`, context );
}
const helpers = await load(
	'src/frontend/customiser/materials.js',
	'({ selectedMaterial, materialForLayer, materialPalette })'
);
const materials = [
	{
		id: 'black-white',
		surface: '#171717',
		engraving: '#ffffff',
		active: true,
	},
	{
		id: 'pink-black',
		surface: '#edabc4',
		engraving: '#171717',
		active: true,
	},
];
const source =
	'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><path fill-rule="evenodd" d="M0 0H100V100H0Z M25 25V75H75V25Z"/></svg>';
const surfaceLayer = {
	id: 37,
	type: 'laser_material',
	x: 30,
	y: 40,
	w: 200,
	h: 100,
	settings: {
		materialSvg: source,
		laserMaterial: { materials, defaultId: 'black-white' },
	},
};

test( 'materials are opt-in layers and only affect the stack above them in the same area', () => {
	const before = { id: 1, type: 'text' };
	const after = { id: 2, type: 'text' };
	const secondSurface = { ...surfaceLayer, id: 38 };
	const last = { id: 3, type: 'image' };
	const area = {
		layers: [ before, surfaceLayer, after, secondSurface, last ],
	};
	const selections = { 37: 'pink-black', 38: 'black-white' };
	assert.equal( helpers.materialForLayer( area, before, selections ), null );
	assert.equal(
		helpers.materialForLayer( area, after, selections ),
		materials[ 1 ]
	);
	assert.equal(
		helpers.materialForLayer( area, last, selections ),
		materials[ 0 ]
	);
	assert.equal(
		helpers.materialForLayer( { layers: [ after ] }, after, selections ),
		null
	);
	assert.equal(
		helpers.selectedMaterial( surfaceLayer, { 37: 'invalid' } ),
		undefined
	);
	assert.equal(
		helpers.selectedMaterial(
			{ ...surfaceLayer, locked: true },
			{ 37: 'pink-black' }
		),
		materials[ 0 ]
	);
	assert.equal( helpers.materialPalette( materials[ 1 ] ).text, '#171717' );
} );

test( 'engraving material finish survives editor normalization', async () => {
	const create = await load(
		'src/admin/products-page-data.js',
		'createProductsPageDataNormalisers'
	);
	const normalisers = create( {
		nextUid: () => 1,
		normaliseDpi: () => 300,
		normaliseRotation: () => 0,
		normaliseAspectRatio: () => 1,
	} );
	assert.equal(
		normalisers.normaliseArea( { material: 'engraving_material' }, 0 )
			.material,
		'engraving_material'
	);
	assert.equal(
		normalisers.normaliseArea( { material: 'glass' }, 0 ).material,
		'glass'
	);
} );

test( 'laser material layers preserve group assignments and resolve their combined palette', async () => {
	const create = await load(
		'src/admin/products-page-data.js',
		'createProductsPageDataNormalisers'
	);
	const normalisers = create( {
		nextUid: () => 1,
		normaliseDpi: () => 300,
		normaliseRotation: () => 0,
		normaliseAspectRatio: () => 1,
	} );
	const layer = normalisers.normaliseLayer( {
		type: 'laser_material',
		settings: {
			material_group_ids: [ 'group-acrylic' ],
			material_ids: [ 'legacy-direct-choice' ],
			default_material_id: 'pink-black',
		},
	} );
	assert.deepEqual( layer.settings.material_group_ids, [ 'group-acrylic' ] );

	const adminMaterials = await load(
		'src/admin/products-page-materials.js',
		'({ defaultLayerMaterial })',
		{
			window: {
				ocProductsData: {
					materialGroups: [
						{
							id: 'group-acrylic',
							material_ids: [ 'black-white', 'pink-black' ],
						},
					],
				},
			},
		}
	);
	assert.equal(
		adminMaterials.defaultLayerMaterial( layer, materials ).id,
		'pink-black'
	);
	assert.equal(
		adminMaterials.defaultLayerMaterial(
			{
				...layer,
				settings: {
					...layer.settings,
					material_group_ids: [ 'deleted-group' ],
					material_ids: [ 'black-white' ],
				},
			},
			materials
		).id,
		'black-white'
	);
} );

test( 'images and all clipart dispatch the selected layer palette rather than the glass or leather finish', async () => {
	const methods = await load(
		'src/frontend/customiser/canvas-renderer.js',
		'canvasRendererMethods',
		{
			...helpers,
			displayBounds: ( value ) => value,
			displayLayer: ( value ) => value,
			imagePlacementClipPath: ( _, clip ) => clip,
		}
	);
	for ( const type of [ 'image', 'ai_image', 'clipmask', 'clipart' ] ) {
		const layer = {
			id: 50,
			type,
			x: 30,
			y: 40,
			w: 100,
			h: 100,
			settings: {},
		};
		const area = {
			printMethod: 'engraving',
			engravingMaterial: 'glass',
			bounds: { x: 0, y: 0, w: 400, h: 300 },
			layers: [ surfaceLayer, layer ],
		};
		let palette;
		const app = {
			...methods,
			materialSelections: {},
			engravingPalette: () => {
				throw new Error( 'Legacy palette must not be used' );
			},
			imageFilterForLayer: () => null,
			rectClipPath: () => null,
			printAreaClipPath: () => null,
			layerClipPath: () => null,
			normaliseSvgClipartUrl: async ( url ) => url,
			recolourSvgClipartUrl: async ( url ) => url,
			renderFabricImg: async ( ...args ) => {
				palette = args[ 10 ];
				return true;
			},
		};
		for ( const [ id, colour ] of [
			[ 'black-white', '#ffffff' ],
			[ 'pink-black', '#171717' ],
		] ) {
			app.materialSelections = { 37: id };
			await app.renderLayer(
				{ _ocScaleX: 1 },
				layer,
				{
					attachmentUrl: 'image.png',
					clipartUrl: 'clipart.png',
					clipartRecolourable: false,
				},
				area
			);
			assert.equal( palette.text, colour );
			assert.equal( palette.imageTint, colour );
			assert.equal( palette.flat, true );
			assert.equal( palette.opacity, 1 );
			assert.equal( palette.pattern, undefined );
		}
	}
} );

test( 'backend SVG upload commits only sanitized responses and preserves the old shape on failure', async () => {
	const dom = new JSDOM(
		'<input type="file" id="oc-material-shape-file"><p id="oc-material-shape-status"></p>'
	);
	const input = dom.window.document.querySelector( 'input' );
	const layer = { type: 'laser_material', settings: { materialSvg: source } };
	const area = { layers: [ layer ] };
	let response = {
		ok: true,
		success: true,
		svg: '<svg viewBox="0 0 2 2"><path d="M0 0H2V2Z"/></svg>',
	};
	let commits = 0;
	const { bindMaterialSettings } = await load(
		'src/admin/products-page-materials.js',
		'({ bindMaterialSettings })',
		{
			window: dom.window,
			document: dom.window.document,
			FormData: dom.window.FormData,
			fetch: async () => ( {
				ok: response.ok,
				json: async () => ( {
					success: response.success,
					data: {
						materialSvg: response.svg,
						message: 'Invalid silhouette',
					},
				} ),
			} ),
		}
	);
	bindMaterialSettings( layer, area, () => {
		commits++;
	} );
	Object.defineProperty( input, 'files', {
		value: [
			new dom.window.File( [ source ], 'shape.svg', {
				type: 'image/svg+xml',
			} ),
		],
	} );
	input.dispatchEvent( new dom.window.Event( 'change' ) );
	await new Promise( ( resolve ) => setImmediate( resolve ) );
	assert.equal( commits, 1 );
	assert.equal( layer.settings.materialSvg, response.svg );
	const saved = layer.settings.materialSvg;
	response = { ok: false, success: false, svg: '' };
	input.dispatchEvent( new dom.window.Event( 'change' ) );
	await new Promise( ( resolve ) => setImmediate( resolve ) );
	assert.equal( layer.settings.materialSvg, saved );
	assert.equal( commits, 1 );
	assert.match(
		dom.window.document.querySelector( 'p' ).textContent,
		/Invalid silhouette/
	);
	response = { ok: true, success: true, svg: source };
	input.dispatchEvent( new dom.window.Event( 'change' ) );
	input.remove();
	await new Promise( ( resolve ) => setImmediate( resolve ) );
	assert.equal( layer.settings.materialSvg, saved );
	dom.window.close();
} );

const { window } = new JSDOM();
const { materialShapeUrl } = await load(
	'src/shared/material-shape.js',
	'({ materialShapeUrl })',
	{ DOMParser: window.DOMParser, XMLSerializer: window.XMLSerializer }
);

test( 'SVG surface recolouring preserves holes and transparent geometry', () => {
	const url = materialShapeUrl( source, '#edabc4', 200, 100 );
	const svg = decodeURIComponent( url.split( ',' )[ 1 ] );
	const doc = new window.DOMParser().parseFromString( svg, 'image/svg+xml' );
	assert.equal( doc.querySelector( 'parsererror' ), null );
	assert.equal(
		doc.querySelector( 'path' ).getAttribute( 'fill-rule' ),
		'evenodd'
	);
	assert.equal(
		doc.querySelector( 'path' ).getAttribute( 'd' ),
		'M0 0H100V100H0Z M25 25V75H75V25Z'
	);
	assert.match(
		doc.querySelector( 'feColorMatrix' ).getAttribute( 'values' ),
		/0 0 0 1 0$/
	);
	assert.equal(
		doc.querySelector( 'svg svg' ).getAttribute( 'preserveAspectRatio' ),
		'none'
	);
} );

test( 'SVG material renders at layer bounds, not the entire print-area background', async () => {
	const methods = await load(
		'src/frontend/customiser/canvas-renderer.js',
		'canvasRendererMethods',
		{
			...helpers,
			materialShapeUrl,
			displayBounds: ( value ) => value,
			displayLayer: ( value ) => value,
		}
	);
	const area = {
		printMethod: 'engraving',
		bounds: { x: 0, y: 0, w: 400, h: 300, rotation: 0 },
		layers: [ surfaceLayer ],
	};
	const objects = [];
	let loaded;
	const app = {
		...methods,
		materialSelections: { 37: 'pink-black' },
		loadFabricImage: async ( url ) => {
			loaded = url;
			return {
				width: 100,
				height: 100,
				set( values ) {
					Object.assign( this, values );
				},
			};
		},
	};
	await app.renderLayer(
		{ _ocScaleX: 0.5, add: ( object ) => objects.push( object ) },
		surfaceLayer,
		{},
		area
	);
	assert.equal( objects.length, 1 );
	assert.equal( objects[ 0 ].left, 65 );
	assert.equal( objects[ 0 ].top, 45 );
	assert.equal( objects[ 0 ].scaleX, 1 );
	assert.equal( objects[ 0 ].scaleY, 0.5 );
	assert.match( loaded, /^data:image\/svg\+xml/ );
	assert.equal( objects[ 0 ]._ocContent, true );
} );

test( 'material IDs are frozen separately; SVG surfaces are never submitted as print layers', async () => {
	const cart = await load(
		'src/frontend/customiser/cart-serialization.js',
		'cartSerializationMethods',
		{ layerIsShown: () => true }
	);
	const app = {
		...cart,
		areas: [ { layers: [ surfaceLayer ] } ],
		inputs: {},
		data: { designId: 4 },
		designVariants: [],
		materialSelections: { 37: 'pink-black' },
	};
	const generation = app.createSubmissionGeneration();
	app.materialSelections[ 37 ] = 'black-white';
	assert.ok( Object.isFrozen( generation.materials ) );
	assert.equal(
		app.buildCustomisationPayload( generation ).materials[ 37 ],
		'pink-black'
	);
	assert.equal( Object.keys( generation.layers ).length, 0 );
} );
