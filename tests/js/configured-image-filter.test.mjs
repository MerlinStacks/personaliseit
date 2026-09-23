import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { createRequire } from 'node:module';
import test from 'node:test';
import vm from 'node:vm';
import { filters as FabricFilters } from 'fabric';

const { parse } = createRequire( import.meta.url )( 'acorn' );
const source = await readFile(
	new URL(
		'../../src/frontend/customiser/canvas-renderer.js',
		import.meta.url
	),
	'utf8'
);
const tree = parse( source, { ecmaVersion: 'latest', sourceType: 'module' } );
const code = tree.body
	.filter( ( node ) => node.type !== 'ImportDeclaration' )
	.map( ( node ) =>
		source.slice( node.declaration?.start ?? node.start, node.end )
	)
	.join( '\n' );
const renderer = ( classes = FabricFilters ) =>
	vm.runInNewContext( code + '; canvasRendererMethods', {
		FabricFilters: classes,
	} );
const methods = renderer();

const cases = [
	[ 'negative', 'Invert' ],
	[ 'grayscale', 'Grayscale' ],
	[ 'sepia', 'Sepia' ],
	[ 'brightness', 'Brightness', 'brightness' ],
	[ 'contrast', 'Contrast', 'contrast' ],
	[ 'saturation', 'Saturation', 'saturation' ],
	[ 'hue', 'HueRotation', 'rotation' ],
];

test( 'configured filters preserve classes, signed amounts, coercion and defaults', () => {
	for ( const [ key, className, property ] of cases ) {
		for ( const [ value, amount ] of [
			[ undefined, 1 ],
			[ NaN, 1 ],
			[ Infinity, 1 ],
			[ -Infinity, 1 ],
			[ 'invalid', 1 ],
			[ null, 0 ],
			[ '', 0 ],
			[ false, 0 ],
			[ 0, 0 ],
			[ -0.5, -0.5 ],
			[ '0.25', 0.25 ],
			[ 1, 1 ],
		] ) {
			const existing = new FabricFilters.Contrast( { contrast: 0.2 } );
			const filters = [ existing ];
			methods.addConfiguredImageFilter( filters, { key, value } );
			assert.equal( filters.length, 2 );
			assert.equal( filters[ 0 ], existing );
			assert.ok( filters[ 1 ] instanceof FabricFilters[ className ] );
			const expected = new FabricFilters[ className ](
				property ? { [ property ]: amount } : undefined
			);
			assert.deepEqual(
				filters[ 1 ].toObject(),
				expected.toObject(),
				key
			);
		}
	}
} );

test( 'missing filter classes and unknown or prototype keys leave effects intact', () => {
	for ( const [ key, className ] of cases ) {
		const classes = { ...FabricFilters, [ className ]: undefined };
		const filters = [ 'existing' ];
		renderer( classes ).addConfiguredImageFilter( filters, { key } );
		assert.deepEqual( filters, [ 'existing' ] );
	}
	for ( const config of [
		undefined,
		null,
		{},
		...[
			'original',
			'unknown',
			'__proto__',
			'constructor',
			'toString',
		].map( ( key ) => ( { key } ) ),
	] ) {
		const filters = [ 'existing' ];
		methods.addConfiguredImageFilter( filters, config );
		assert.deepEqual( filters, [ 'existing' ] );
	}
} );
