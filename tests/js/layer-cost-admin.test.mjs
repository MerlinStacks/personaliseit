import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

// Evaluate the source factories without relying on bundler extension resolution.
const createProductsPageDataNormalisers = new Function(
	( await readFile( 'src/admin/products-page-data.js', 'utf8' ) ).replace(
		'export function',
		'function'
	) + '\nreturn createProductsPageDataNormalisers;'
)();
const cutLineSource = await readFile( 'src/admin/products-page-cut-line.js', 'utf8' );
const settingsSource = await readFile(
	'src/admin/products-page-settings.js',
	'utf8'
);
const createProductsPageSettings = new Function(
	cutLineSource.replaceAll( 'export ', '' ) +
	settingsSource
		.replace( "import { bindCutLineUpload } from './products-page-cut-line';", '' )
		.replace( 'export function', 'function' ) +
	'\nreturn createProductsPageSettings;'
)();
const normalisers = createProductsPageDataNormalisers( {} );
const supportedTypes = [ 'text', 'textarea', 'image', 'clipmask' ];
const unsupportedTypes = [
	'ai_image',
	'mask',
	'clipart',
	'cut_line',
	'spotify',
	'lineart',
	'night_sky',
];

function setupUi( t ) {
	const previousWindow = Object.getOwnPropertyDescriptor( globalThis, 'window' );
	const previousDocument = Object.getOwnPropertyDescriptor( globalThis, 'document' );
	const handlers = new Map();
	const commits = [];
	globalThis.window = {};
	globalThis.document = {
		getElementById: ( id ) =>
			id.startsWith( 'oc-set-additional-cost' )
				? {
					addEventListener: ( event, handler ) => {
						handlers.set( `${ id }:${ event }`, handler );
					},
				  }
				: null,
		querySelectorAll: () => [],
	};
	t.after( () => {
		for ( const [ key, descriptor ] of [
			[ 'window', previousWindow ],
			[ 'document', previousDocument ],
		] ) {
			if ( descriptor ) {
				Object.defineProperty( globalThis, key, descriptor );
			} else {
				delete globalThis[ key ];
			}
		}
	} );
	const ui = createProductsPageSettings( {
		selectedArea: () => null,
		getAreas: () => [],
		normaliseLinkGroup: ( value ) => value || '',
		esc: ( value ) =>
			String( value )
				.replaceAll( '&', '&amp;' )
				.replaceAll( '"', '&quot;' )
				.replaceAll( '<', '&lt;' )
				.replaceAll( '>', '&gt;' ),
		commitChange: ( options ) => commits.push( options ),
	} );
	return { ...ui, handlers, commits };
}

test( 'supported layers default to disabled, zero additional cost', () => {
	for ( const type of supportedTypes ) {
		for ( const settings of [
			normalisers.defaultSettings( type ),
			normalisers.normaliseSettings( type ),
		] ) {
			assert.equal( settings.additional_cost_enabled, false, type );
			assert.equal( settings.additional_cost, 0, type );
		}
	}
} );

test( 'normalization preserves decimal amounts, enable flags and existing settings', () => {
	for ( const type of supportedTypes ) {
		for ( const enabled of [ true, false ] ) {
			const existing = {
				additional_cost_enabled: enabled,
				additional_cost: '4.125',
				link_group: 'customer-name',
			};
			const settings = normalisers.normaliseSettings( type, existing );
			assert.equal( settings.additional_cost_enabled, enabled );
			assert.equal( settings.additional_cost, 4.125 );
			assert.equal( settings.link_group, existing.link_group );
			assert.equal( existing.additional_cost, '4.125' );
		}
	}
} );

test( 'normalization clamps negative costs and rejects nonfinite or invalid amounts', () => {
	for ( const type of supportedTypes ) {
		for ( const value of [ -4, '-2.75', '', null, undefined, 'invalid', Infinity, NaN ] ) {
			assert.equal(
				normalisers.normaliseSettings( type, { additional_cost: value } )
					.additional_cost,
				0
			);
		}
	}
} );

test( 'General tab shows the amount only when enabled and explains charging rules', ( t ) => {
	const ui = setupUi( t );
	for ( const type of supportedTypes ) {
		const layer = { type, settings: normalisers.defaultSettings( type ) };
		const disabled = ui.buildTabContent( 'general', layer );
		assert.match( disabled, /type="checkbox" id="oc-set-additional-cost-enabled"/ );
		assert.doesNotMatch( disabled, /id="oc-set-additional-cost"/ );
		assert.match( disabled, /per item.*nonblank custom text.*differs from the default.*customer-uploaded photo/ );
		assert.match( disabled, /Default content is never charged/ );
		assert.match( disabled, /base design fee is separate/ );
		layer.settings.additional_cost_enabled = true;
		layer.settings.additional_cost = 2.75;
		const enabled = ui.buildTabContent( 'general', layer );
		assert.match( enabled, /id="oc-set-additional-cost-enabled" checked/ );
		assert.match( enabled, /id="oc-set-additional-cost"[^>]*min="0"[^>]*step="any"[^>]*value="2.75"/ );
	}
} );

test( 'enable handlers request a rerender and preserve the amount across toggles', ( t ) => {
	const ui = setupUi( t );
	for ( const type of supportedTypes ) {
		const settings = normalisers.normaliseSettings( type, {
			additional_cost_enabled: true,
			additional_cost: 2.75,
		} );
		const layer = { type, settings };
		ui.bindSettingsHandlers( layer );
		const toggle = ui.handlers.get( 'oc-set-additional-cost-enabled:change' );
		for ( const checked of [ false, true ] ) {
			toggle( { target: { checked } } );
			assert.equal( settings.additional_cost_enabled, checked );
			assert.equal( settings.additional_cost, 2.75 );
			assert.deepEqual( ui.commits.at( -1 ), { rightColumn: true } );
			assert.equal(
				ui.buildTabContent( 'general', layer ).includes( 'id="oc-set-additional-cost"' ),
				checked
			);
		}
	}
} );

test( 'amount handlers retain decimals and commit nonnegative finite values', ( t ) => {
	const ui = setupUi( t );
	for ( const type of supportedTypes ) {
		const settings = normalisers.defaultSettings( type );
		ui.bindSettingsHandlers( { type, settings } );
		const input = ui.handlers.get( 'oc-set-additional-cost:input' );
		for ( const [ value, expected ] of [
			[ '4.125', 4.125 ],
			[ '0', 0 ],
			[ '-4', 0 ],
			[ '', 0 ],
			[ 'invalid', 0 ],
			[ 'Infinity', 0 ],
		] ) {
			const count = ui.commits.length;
			input( { target: { value } } );
			assert.equal( settings.additional_cost, expected );
			assert.equal( ui.commits.length, count + 1 );
		}
	}
} );

test( 'additional cost output escapes dynamic values', ( t ) => {
	const ui = setupUi( t );
	const html = ui.buildTabContent( 'general', {
		type: 'text',
		settings: {
			additional_cost_enabled: true,
			additional_cost: '"><script>alert(1)</script>',
		},
	} );
	assert.doesNotMatch( html, /<script>/ );
	assert.match( html, /value="&quot;&gt;&lt;script&gt;/ );
} );

test( 'unsupported layer types have no cost defaults, controls or handlers', ( t ) => {
	const ui = setupUi( t );
	for ( const type of unsupportedTypes ) {
		const settings = normalisers.defaultSettings( type );
		assert.equal( settings.additional_cost_enabled, undefined );
		assert.equal( settings.additional_cost, undefined );
		const layer = { type, settings };
		assert.doesNotMatch( ui.buildTabContent( 'general', layer ), /oc-set-additional-cost/ );
		ui.bindSettingsHandlers( layer );
		assert.equal( ui.handlers.size, 0 );
	}
} );
