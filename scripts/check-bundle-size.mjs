import { readdir, stat } from 'node:fs/promises';
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

export function measureBundles( files ) {
	const isEntryAsset = ( item ) => /^(?:admin|frontend)\//.test( item.file );
	const isLoadedCustomiserAsset = ( item ) =>
		/^frontend\/customiser-app\.(?:js|css)$/.test( item.file );
	const isCoreChunk = ( item ) =>
		/^chunks\/customiser-core\.[a-f0-9]+\.js$/.test( item.file );
	const isUploadChunk = ( item ) =>
		/^chunks\/upload-tools\.[a-f0-9]+\.js$/.test( item.file ) ||
		item.file === 'upload-tools.css';
	const entries = files.filter( isEntryAsset );
	const chunks = files.filter( ( item ) => ! isEntryAsset( item ) );
	const coreEntryBytes = totalSize( files.filter( isLoadedCustomiserAsset ) );
	const requiredStartupBytes =
		coreEntryBytes + totalSize( files.filter( isCoreChunk ) );
	const uploadEnabledStartupBytes =
		requiredStartupBytes + totalSize( files.filter( isUploadChunk ) );
	return {
		totalBytes: totalSize( files ),
		coreEntryBytes,
		requiredStartupBytes,
		uploadEnabledStartupBytes,
		chunks,
		entries,
	};
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
		coreEntry: Number( process.env.BUNDLE_CORE_ENTRY_MAX_BYTES || 180_000 ),
		requiredStartup: Number(
			process.env.BUNDLE_REQUIRED_STARTUP_MAX_BYTES || 505_000
		),
		uploadEnabledStartup: Number(
			process.env.BUNDLE_UPLOAD_STARTUP_MAX_BYTES || 575_000
		),
		entryAsset: Number(
			process.env.BUNDLE_ENTRY_ASSET_MAX_BYTES || 450_000
		),
		chunk: Number( process.env.BUNDLE_CHUNK_MAX_BYTES || 340_000 ),
		total: Number( process.env.BUNDLE_TOTAL_MAX_BYTES || 1_350_000 ),
	};
	const files = await collectBundleFiles( buildDirectory );
	const measurements = measureBundles( files );
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
