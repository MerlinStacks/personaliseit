import { readFile, readdir, stat } from 'node:fs/promises';
import path from 'node:path';
import process from 'node:process';
import { fileURLToPath } from 'node:url';

/* eslint-disable no-console */

const measuredExtensions = new Set( [ '.js', '.css' ] );

export async function collectBundleFiles( directory, root = directory ) {
	const files = [];
	for ( const entry of await readdir( directory, { withFileTypes: true } ) ) {
		const absolutePath = path.join( directory, entry.name );
		if ( entry.isDirectory() ) {
			files.push( ...( await collectBundleFiles( absolutePath, root ) ) );
		} else if ( measuredExtensions.has( path.extname( entry.name ) ) ) {
			files.push( {
				file: path.relative( root, absolutePath ),
				bytes: ( await stat( absolutePath ) ).size,
			} );
		}
	}
	return files;
}

const totalSize = ( files ) =>
	files.reduce( ( total, item ) => total + item.bytes, 0 );

export function measureBundles( files, activeChunkFiles = null ) {
	const isEntryAsset = ( item ) => /^(?:admin|frontend)\//.test( item.file );
	const isLoadedCustomiserAsset = ( item ) =>
		/^frontend\/customiser-app\.(?:js|css)$/.test( item.file );
	const isCoreChunk = ( item ) =>
		/^chunks\/customiser-core\.[a-f0-9]+\.js$/.test( item.file );
	const isUploadChunk = ( item ) =>
		/^chunks\/upload-tools\.[a-f0-9]+\.js$/.test( item.file ) ||
		item.file === 'upload-tools.css';
	const isActiveCustomiserChunk = ( item ) =>
		item.file === 'upload-tools.css' ||
		activeChunkFiles === null ||
		activeChunkFiles.has( item.file );
	const entries = files.filter( isEntryAsset );
	const chunks = files.filter( ( item ) => ! isEntryAsset( item ) );
	const coreEntryBytes = totalSize( files.filter( isLoadedCustomiserAsset ) );
	const requiredStartupBytes =
		coreEntryBytes +
		totalSize(
			files.filter(
				( item ) =>
					isCoreChunk( item ) && isActiveCustomiserChunk( item )
			)
		);
	const uploadEnabledStartupBytes =
		requiredStartupBytes +
		totalSize(
			files.filter(
				( item ) =>
					isUploadChunk( item ) && isActiveCustomiserChunk( item )
			)
		);
	const budgetedFiles = files.filter(
		( item ) =>
			( ! isCoreChunk( item ) &&
				! /^chunks\/upload-tools\.[a-f0-9]+\.js$/.test( item.file ) ) ||
			isActiveCustomiserChunk( item )
	);
	return {
		totalBytes: totalSize( budgetedFiles ),
		coreEntryBytes,
		requiredStartupBytes,
		uploadEnabledStartupBytes,
		chunks,
		entries,
	};
}

export function activeCustomiserChunks( files, entrySource ) {
	return new Set(
		files
			.filter( ( item ) =>
				/^chunks\/(?:customiser-core|upload-tools)\.[a-f0-9]+\.js$/.test(
					item.file
				)
			)
			.filter( ( item ) => {
				const hash = item.file.match( /\.([a-f0-9]+)\.js$/ )?.[ 1 ];
				return hash && entrySource.includes( hash );
			} )
			.map( ( item ) => item.file )
	);
}

export function budgetFailures( measurements, limits ) {
	const failures = [];
	const startupBudgets = [
		[ 'Core entry', measurements.coreEntryBytes, limits.coreEntry ],
		[
			'Required startup',
			measurements.requiredStartupBytes,
			limits.requiredStartup,
		],
		[
			'Upload-enabled startup',
			measurements.uploadEnabledStartupBytes,
			limits.uploadEnabledStartup,
		],
	];
	startupBudgets.forEach( ( [ label, bytes, limit ] ) => {
		if ( bytes > limit ) {
			failures.push(
				`${ label } is ${ bytes } bytes (limit ${ limit }).`
			);
		}
	} );
	if ( measurements.totalBytes > limits.total ) {
		failures.push(
			`Total JS/CSS is ${ measurements.totalBytes } bytes (limit ${ limits.total }).`
		);
	}
	for ( const item of measurements.entries ) {
		if ( item.bytes > limits.entryAsset ) {
			failures.push(
				`Entry asset ${ item.file } is ${ item.bytes } bytes (limit ${ limits.entryAsset }).`
			);
		}
	}
	for ( const item of measurements.chunks ) {
		if ( item.bytes > limits.chunk ) {
			failures.push(
				`Async chunk ${ item.file } is ${ item.bytes } bytes (limit ${ limits.chunk }).`
			);
		}
	}
	return failures;
}

async function main() {
	const buildDirectory = path.resolve( 'assets/build' );
	const limits = {
		// Night Sky and AI layers add customer-facing controls to the core entry.
		// Timezone boundary data remains lazy-loaded and is covered by the total.
		coreEntry: Number( process.env.BUNDLE_CORE_ENTRY_MAX_BYTES || 200_000 ),
		requiredStartup: Number(
			process.env.BUNDLE_REQUIRED_STARTUP_MAX_BYTES || 530_000
		),
		uploadEnabledStartup: Number(
			process.env.BUNDLE_UPLOAD_STARTUP_MAX_BYTES || 600_000
		),
		entryAsset: Number(
			process.env.BUNDLE_ENTRY_ASSET_MAX_BYTES || 450_000
		),
		chunk: Number( process.env.BUNDLE_CHUNK_MAX_BYTES || 340_000 ),
		total: Number( process.env.BUNDLE_TOTAL_MAX_BYTES || 1_460_000 ),
	};
	const files = await collectBundleFiles( buildDirectory );
	const entrySource = await readFile(
		path.join( buildDirectory, 'frontend/customiser-app.js' ),
		'utf8'
	);
	const measurements = measureBundles(
		files,
		activeCustomiserChunks( files, entrySource )
	);
	const failures = budgetFailures( measurements, limits );

	console.log(
		`Customiser core entry: ${ measurements.coreEntryBytes } bytes.`
	);
	console.log(
		`Customiser required startup: ${ measurements.requiredStartupBytes } bytes.`
	);
	console.log(
		`Customiser upload-enabled startup: ${ measurements.uploadEnabledStartupBytes } bytes.`
	);
	console.log(
		`Production JS/CSS: ${ measurements.totalBytes } bytes across ${ files.length } files (${ measurements.chunks.length } async chunks).`
	);
	measurements.chunks
		.sort( ( a, b ) => b.bytes - a.bytes )
		.forEach( ( item ) =>
			console.log( `${ item.file }: ${ item.bytes } bytes` )
		);

	if ( failures.length ) {
		failures.forEach( ( failure ) => console.error( failure ) );
		process.exitCode = 1;
	}
}

if ( process.argv[ 1 ] === fileURLToPath( import.meta.url ) ) {
	await main();
}
