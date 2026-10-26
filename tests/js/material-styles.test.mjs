import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const source = await readFile(
	'src/frontend/customiser/design-variants.js',
	'utf8'
);
const start = source.indexOf( '\tasync loadMaterialStyles(' );
const method = source
	.slice( start, source.indexOf( '\n\t},', start ) + 4 )
	.replace( /\bimport\(/g, 'loadModule(' );
const loader = ( loadModule ) =>
	new Function( 'loadModule', `return { ${ method } };` )( loadModule );
const areas = [ { layers: [ { type: 'laser_material' } ] } ];

test( 'ordinary designs need no material stylesheet; material designs await it', async () => {
	let resolve;
	const requests = [];
	const app = loader( ( name ) => {
		requests.push( name );
		return new Promise( ( done ) => {
			resolve = done;
		} );
	} );
	await app.loadMaterialStyles();
	await app.loadMaterialStyles( [ { layers: [ { type: 'text' } ] } ] );
	assert.deepEqual( requests, [] );
	let ready = false;
	const pending = app.loadMaterialStyles( areas ).then( () => {
		ready = true;
	} );
	assert.deepEqual( requests, [ './materials.scss' ] );
	assert.equal( ready, false );
	resolve();
	await pending;
	assert.equal( ready, true );
} );

test( 'stylesheet failures propagate for boot recovery and remain retryable', async () => {
	let requests = 0;
	const app = loader( async () => {
		if ( requests++ === 0 ) {
			throw Object.assign( new Error( 'Missing CSS' ), {
				code: 'CSS_CHUNK_LOAD_FAILED',
			} );
		}
	} );
	await assert.rejects( app.loadMaterialStyles( areas ), {
		code: 'CSS_CHUNK_LOAD_FAILED',
	} );
	await app.loadMaterialStyles( areas );
	assert.equal( requests, 2 );
	assert.match(
		source,
		/await this\.loadMaterialStyles\( this\.areas \);\s*if \( designGeneration !== this\._designGeneration \)/
	);
} );
