import assert from 'node:assert/strict';
import { mkdtempSync, mkdirSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { createRequire } from 'node:module';
import test from 'node:test';

const require = createRequire( import.meta.url );
const ReleaseManifestPlugin = require( '../../scripts/release-manifest.cjs' );

test( 'release identity is deterministic and changes for PHP-only and CSS-only replacements', ( t ) => {
	const root = mkdtempSync( path.join( tmpdir(), 'oc-release-' ) );
	t.after( () => rmSync( root, { recursive: true, force: true } ) );
	for ( const directory of [
		'includes',
		'templates',
		'assets/css',
		'assets/js',
	] ) {
		mkdirSync( path.join( root, directory ), { recursive: true } );
	}
	for ( const file of [
		'overcustomise.php',
		'includes/example.php',
		'composer.lock',
	] ) {
		writeFileSync( path.join( root, file ), 'original' );
	}
	const build = ( css = 'original CSS' ) => {
		let manifest;
		const compilation = {
			fileDependencies: new Set(),
			getAssets: () => [
				{
					name: 'frontend/app.css',
					source: { buffer: () => Buffer.from( css ) },
				},
			],
			emitAsset: ( name, source ) => {
				manifest = source.value;
			},
			hooks: {
				processAssets: { tap: ( options, callback ) => callback() },
			},
		};
		new ReleaseManifestPlugin().apply( {
			context: root,
			webpack: {
				Compilation: { PROCESS_ASSETS_STAGE_REPORT: 1 },
				sources: {
					RawSource: class {
						constructor( value ) {
							this.value = value;
						}
					},
				},
			},
			hooks: {
				thisCompilation: {
					tap: ( name, callback ) => callback( compilation ),
				},
			},
		} );
		assert.ok(
			compilation.fileDependencies.has(
				path.join( root, 'includes/example.php' )
			)
		);
		return manifest;
	};
	const first = build();
	assert.equal( first, build() );
	writeFileSync( path.join( root, 'includes/example.php' ), 'patched PHP' );
	assert.notEqual( first, build() );
	assert.notEqual( build(), build( 'patched CSS' ) );
} );
