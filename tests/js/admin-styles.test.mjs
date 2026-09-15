import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { readFileSync, readdirSync, statSync } from 'node:fs';
import { createRequire } from 'node:module';
import test from 'node:test';

// PostCSS ships with the existing wp-scripts toolchain.
const { parse } = createRequire( import.meta.url )( 'postcss' );
const root = new URL( '../../', import.meta.url );
const read = ( file ) => readFileSync( new URL( file, root ), 'utf8' );
const modules = [
	'base',
	'operations',
	'components',
	'fonts-and-modals',
	'colours',
	'design-editor',
	'layers',
	'catalog',
	'settings',
];
const pages = [
	'products',
	'fonts',
	'colours',
	'image-filters',
	'clipart',
	'customer-uploads',
	'print-methods',
	'print-queue',
	'settings',
];
const prefix = 'https://example.test/wp-content/plugins/overcustomise/';

function capture( hook, assetRoot ) {
	return JSON.parse(
		execFileSync(
			'php',
			[
				'tests/admin-styles-fixture.php',
				hook,
				...( assetRoot ? [ assetRoot ] : [] ),
			],
			{ cwd: root, encoding: 'utf8' }
		)
	);
}

// Resolve the actual registered dependency graph, including missing/cyclic
// dependency detection, rather than assuming filesystem/alphabetical order.
function loadSequence( { styles, queue } ) {
	const ordered = new Set();
	const visiting = new Set();
	function visit( handle ) {
		assert.ok( styles[ handle ], `Missing dependency: ${ handle }` );
		assert.ok( ! visiting.has( handle ), `Cycle: ${ handle }` );
		if ( ordered.has( handle ) ) {
			return;
		}
		visiting.add( handle );
		styles[ handle ].deps.forEach( visit );
		visiting.delete( handle );
		ordered.add( handle );
	}
	queue.forEach( visit );
	return [ ...ordered ].map( ( handle ) => {
		assert.ok( styles[ handle ].src.startsWith( prefix ) );
		return styles[ handle ].src.slice( prefix.length );
	} );
}

const assets = capture( 'overcustomise_page_overcustomise-settings' );
const files = loadSequence( assets );

test( 'all OC pages load the same ordered cascade through the existing handle', () => {
	assert.deepEqual( assets.queue, [ 'oc-admin' ] );
	assert.deepEqual( files, [
		...modules.map( ( name ) => `assets/css/admin/${ name }.css` ),
		'assets/css/admin.css',
	] );
	assert.equal( Object.keys( assets.styles ).length, files.length );
	for ( const page of pages ) {
		assert.deepEqual(
			capture( `overcustomise_page_overcustomise-${ page }` ),
			assets
		);
	}
	for ( const hook of [
		'index.php',
		'edit.php',
		'toplevel_page_overcustomise',
	] ) {
		assert.deepEqual( capture( hook ), { styles: {}, queue: [] } );
	}
} );

test( 'each stylesheet keeps filemtime cache busting and the version fallback', () => {
	for ( const style of Object.values( assets.styles ) ) {
		const file = new URL( style.src.slice( prefix.length ), root );
		assert.equal(
			style.version,
			String( Math.floor( statSync( file ).mtimeMs / 1000 ) )
		);
		assert.equal( style.media, 'all' );
	}
	const missing = capture(
		'overcustomise_page_overcustomise-settings',
		new URL( 'tests/nonexistent-admin-assets/', root ).pathname
	);
	assert.deepEqual( loadSequence( missing ), files );
	for ( const style of Object.values( missing.styles ) ) {
		assert.equal( style.version, 'test-version' );
	}
} );

test( 'every focused module is loaded, packaged, valid CSS and below 1000 lines', () => {
	assert.deepEqual(
		readdirSync( new URL( 'assets/css/admin/', root ) ).sort(),
		modules.map( ( name ) => `${ name }.css` ).sort()
	);
	const packaged = JSON.parse( read( 'package.json' ) ).files;
	assert.ok( packaged.includes( 'assets/css/admin.css' ) );
	assert.ok( packaged.includes( 'assets/css/admin' ) );
	for ( const file of files ) {
		const css = read( file );
		assert.ok( css.trimEnd().split( '\n' ).length < 1000, file );
		const tree = parse( css, { from: file } );
		assert.ok( tree.nodes.length > 0 );
		tree.walkAtRules( /^(import|charset|namespace)$/i, ( rule ) => {
			assert.fail( `Unexpected stylesheet-scoped rule: ${ rule }` );
		} );
		// Moving into a subdirectory must not change relative URL resolution.
		tree.walkDecls( ( declaration ) => {
			for ( const match of declaration.value.matchAll(
				/url\(\s*["']?([^"')]+)/gi
			) ) {
				assert.match( match[ 1 ], /^(data:|https?:|\/)/i );
			}
		} );
	}
} );

// Compare ordered, nested rules and declarations, retaining selector/value
// spelling, duplicate declarations, !important, media queries and keyframes.
// Only comments and formatting metadata are omitted.
function sequence( css ) {
	function nodes( children ) {
		return children
			.filter( ( node ) => node.type !== 'comment' )
			.map( ( node ) => {
				if ( node.type === 'decl' ) {
					return [
						node.type,
						node.prop,
						node.value,
						Boolean( node.important ),
					];
				}
				assert.ok( [ 'rule', 'atrule' ].includes( node.type ) );
				return [
					node.type,
					node.selector ?? node.name,
					node.params ?? null,
					node.nodes ? nodes( node.nodes ) : null,
				];
			} );
	}
	return nodes( parse( css ).nodes );
}

const baseline = process.env.OC_ADMIN_CSS_BASE_REF;
test(
	'parsed cascade exactly matches the original stylesheet',
	{ skip: ! baseline },
	( t ) => {
		const original = execFileSync(
			'git',
			[ 'show', `${ baseline }:assets/css/admin.css` ],
			{ cwd: root, encoding: 'utf8' }
		);
		const actual = files.flatMap( ( file ) => sequence( read( file ) ) );
		assert.deepEqual( actual, sequence( original ) );
		// Also prove that complete individual rules/at-rules survived verbatim.
		const rawRules = ( css ) =>
			parse( css )
				.nodes.filter( ( node ) => node.type !== 'comment' )
				.map( ( node ) => node.toString() );
		assert.deepEqual(
			files.flatMap( ( file ) => rawRules( read( file ) ) ),
			rawRules( original )
		);
		const counts = { rules: 0, declarations: 0, atRules: 0 };
		const tree = parse( original );
		tree.walkRules( () => counts.rules++ );
		tree.walkDecls( () => counts.declarations++ );
		tree.walkAtRules( () => counts.atRules++ );
		t.diagnostic(
			`${ JSON.stringify( counts ) }; ${
				files.length
			} files; exact parsed and verbatim rule equality`
		);
	}
);
