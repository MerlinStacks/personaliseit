import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const template = readFileSync(
	new URL(
		'../../includes/admin/class-oc-admin-products.php',
		import.meta.url
	),
	'utf8'
);

test( 'design editor omits canvas DPI helper text but retains the control', () => {
	assert.ok( template.includes( 'id="oc-prop-dpi"' ) );
	assert.ok(
		! template.includes( 'Controls how mm, cm and inch sizes appear' )
	);
} );

test( 'legacy flat rate is preserved without a visible pricing control', () => {
	assert.match(
		template,
		/<input type="hidden" id="oc_flat_rate" name="oc_flat_rate"/
	);
	assert.ok( ! template.includes( '<label for="oc_flat_rate">' ) );
	assert.ok( ! template.includes( 'Flat Rate (AUD)' ) );
} );
