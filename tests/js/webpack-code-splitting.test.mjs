import assert from 'node:assert/strict';
import { readFile, readdir } from 'node:fs/promises';
import { createRequire } from 'node:module';
import test from 'node:test';

const require = createRequire( import.meta.url );
const config = require( '../../webpack.config.js' );

test( 'resolves lazy chunks from the WordPress build root', () => {
	assert.equal( config.output.publicPath, 'auto' );
	assert.equal(
		config.output.chunkFilename,
		'chunks/[name].[contenthash:8].js'
	);
} );

test( 'loads Fabric-bound mixins through the required core chunk', async () => {
	const source = await readFile( 'src/frontend/customiser-app.js', 'utf8' );
	assert.doesNotMatch( source, /^import .*canvas-renderer/m );
	assert.doesNotMatch( source, /^import .*design-variants/m );
	assert.match( source, /webpackChunkName: "customiser-core"/ );
	assert.match( source, /clearBootFailure\(\)/ );
	assert.match( source, /new OCCustomiser\( data \)\.init\(\)/ );
} );

test( 'clears stale upload import errors after a successful retry', async () => {
	const source = await readFile(
		'src/frontend/customiser/uploads.js',
		'utf8'
	);
	const successPath = source.slice(
		source.indexOf( 'zoneEls.forEach', source.indexOf( 'const [', 0 ) ),
		source.indexOf( 'const lid =', source.indexOf( 'zoneEls.forEach' ) )
	);
	assert.match( successPath, /showUploadError\( zoneEl, '' \)/ );
	assert.match( successPath, /setUploadZoneState/ );
} );

test( 'does not retry permanent upload validation failures', async () => {
	const source = await readFile(
		'src/frontend/customiser/uploads.js',
		'utf8'
	);

	assert.match( source, /shouldRetry: \( xhr \) =>/ );
	assert.match( source, /xhr\.status === 429/ );
	assert.match( source, /xhr\.status >= 500/ );
	assert.doesNotMatch( source, /xhr\.status === 422/ );
} );

test( 'built customiser references the emitted upload chunk', async () => {
	const [ buildSource, chunkFiles ] = await Promise.all( [
		readFile( 'assets/build/frontend/customiser-app.js', 'utf8' ),
		readdir( 'assets/build/chunks' ),
	] );
	const uploadChunks = chunkFiles.filter( ( file ) =>
		/^upload-tools\.[a-f0-9]{8}\.js$/.test( file )
	);

	assert.equal( uploadChunks.length, 1 );
	const chunkHash = uploadChunks[ 0 ].match(
		/^upload-tools\.([a-f0-9]{8})\.js$/
	)[ 1 ];
	assert.match(
		buildSource,
		new RegExp( `"upload-tools":"${ chunkHash }"` )
	);

	const chunkSource = await readFile(
		`assets/build/chunks/${ uploadChunks[ 0 ] }`,
		'utf8'
	);
	assert.match(
		chunkSource,
		/\.push\(\[\[\s*["']upload-tools["']\s*\],/
	);
} );
