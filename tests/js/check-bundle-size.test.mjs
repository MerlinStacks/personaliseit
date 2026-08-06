import assert from 'node:assert/strict';
import test from 'node:test';

import {
	budgetFailures,
	measureBundles,
} from '../../scripts/check-bundle-size.mjs';

const files = [
	{ file: 'frontend/customiser-app.js', bytes: 400 },
	{ file: 'frontend/customiser-app.css', bytes: 50 },
	{ file: 'frontend/customiser-app-rtl.css', bytes: 55 },
	{ file: 'admin/products-page.js', bytes: 300 },
	{ file: 'chunks/customiser-core.12345678.js', bytes: 200 },
	{ file: 'chunks/upload-tools.12345678.js', bytes: 100 },
	{ file: 'upload-tools.css', bytes: 25 },
	{ file: 'upload-tools-rtl.css', bytes: 26 },
];

test( 'measures required and upload-enabled browser startup honestly', () => {
	const measured = measureBundles( files );
	assert.equal( measured.coreEntryBytes, 450 );
	assert.equal( measured.requiredStartupBytes, 650 );
	assert.equal( measured.uploadEnabledStartupBytes, 775 );
	assert.equal( measured.totalBytes, 1156 );
	assert.deepEqual( measured.chunks, files.slice( 4 ) );
} );

test( 'reports each startup, entry, chunk, and total budget independently', () => {
	const failures = budgetFailures( measureBundles( files ), {
		coreEntry: 449,
		requiredStartup: 649,
		uploadEnabledStartup: 774,
		entryAsset: 350,
		chunk: 199,
		total: 1155,
	} );
	assert.equal( failures.length, 6 );
	assert.match( failures[ 0 ], /Core entry/ );
	assert.match( failures[ 1 ], /Required startup/ );
	assert.match( failures[ 2 ], /Upload-enabled startup/ );
	assert.match( failures[ 3 ], /Total JS\/CSS/ );
	assert.match( failures[ 4 ], /customiser-app\.js/ );
	assert.match( failures[ 5 ], /customiser-core/ );
} );
