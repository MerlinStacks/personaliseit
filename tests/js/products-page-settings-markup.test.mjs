import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const source = await readFile( 'src/admin/products-page-settings.js', 'utf8' );
const createSettings = new Function(
	'window',
	source
		.replace(
			"import { bindCutLineUpload } from './products-page-cut-line';",
			''
		)
		.replace( 'export function', 'function' ) +
		'\nreturn createProductsPageSettings;'
);
const esc = ( value ) =>
	String( value )
		.replaceAll( '&', '&amp;' )
		.replaceAll( '"', '&quot;' )
		.replaceAll( '<', '&lt;' )
		.replaceAll( '>', '&gt;' );

// These fingerprints freeze the complete pre-deduplication HTML, including
// whitespace, attribute order, escaping, empty states and selected options.
test( 'settings tabs preserve their original markup across layer and data variants', () => {
	const hashes = {};
	for ( const tab of [
		'general',
		'content',
		'style',
		'file',
		'mask',
		'appearance',
		'library',
		'validation',
	] ) {
		const hash = createHash( 'sha256' );
		for ( const type of [
			'text',
			'textarea',
			'image',
			'ai_image',
			'clipart',
			'clipmask',
			'cut_line',
			'night_sky',
		] ) {
			for ( const method of [ 'print', 'engraving' ] ) {
				for ( const populated of [ false, true ] ) {
					const data = populated
						? {
								fonts: [ { id: 1, name: 'Font <&"' } ],
								fontGroups: [
									{
										id: 2,
										name: 'Fonts <&"',
										fontIds: [ 1 ],
									},
								],
								colours: [
									{
										id: 1,
										name: 'Colour <&"',
										hex: '#AbCdEf',
									},
								],
								colourGroups: [
									{
										id: 2,
										name: 'Colours <&"',
										colourIds: [ 1 ],
									},
								],
								imageFilters: [
									{ id: 1, name: 'Filter <&"' },
									{ id: 2, name: 'Other' },
								],
								clipartGroups: [ { id: 2, name: 'Art <&"' } ],
								clipartItems: [
									{
										id: 1,
										name: 'Art <&"',
										groupIds: [ 2 ],
										allowedPrintMethods: [ 'print' ],
									},
								],
						  }
						: {};
					const ui = createSettings( { ocProductsData: data } )( {
						esc,
						selectedArea: () => ( { method } ),
						getAreas: () => [],
						normaliseLinkGroup: ( value ) => value || '',
						normaliseHex: ( value ) => value || '#000000',
					} );
					for ( const settings of [
						{},
						{
							additional_cost_enabled: true,
							additional_cost: '2.75',
							default_text: '<&"',
							char_limit: '12"',
							default_font_size: 12,
							min_font_size: 0,
							max_font_size: '40"',
							font_groups: [ 2 ],
							colour_groups: [ 2 ],
							default_font_id: '1',
							default_color: '#abcdef',
							image_filter_ids: [ 1 ],
							default_image_filter_id: '1',
							max_size_mb: 20,
							clipart_groups: [ 2 ],
							default_clipart_id: '1',
							clipart_display: 'carousel',
							alignment: 'right',
							line_alignment: 'bottom',
						},
						{
							font_groups: [ 99 ],
							colour_groups: [ 99 ],
							clipart_groups: [ 99 ],
							alignment: 'unknown',
							line_alignment: 'unknown',
							clipart_display: 'unknown',
							mask_shape: 'unknown',
							image_filter_ids: [],
						},
						{
							alignment: 'left',
							line_alignment: 'center',
							max_size_mb: 0,
						},
					] ) {
						hash.update(
							JSON.stringify(
								ui.buildTabContent( tab, {
									type,
									label: 'Label <&"',
									x: -2,
									y: 0,
									w: 100,
									h: 50,
									settings,
								} )
							)
						);
					}
				}
			}
		}
		hashes[ tab ] = hash.digest( 'hex' );
	}
	assert.deepEqual( hashes, {
		general:
			'1ef2b0a0e1a684f9cc6d97d1235efec7e054946998f64c2baa519a012f143505',
		content:
			'fb4372465c5d1db41f70f55f34fb096274ba004bb2b374f174424ad85cd5125c',
		style: 'b2d3d7f6a42d6894919300e2db4b1582c95c0d10b850727a5ddfa9400f46348e',
		file: '419fbc6af179a24dc8d1aa20c9717634901498b2774331bcc594ca87f552e0a6',
		mask: '65806890bdacc9e09a72a97d3052fb68b694cdf99062bad4c8cf90c4e658ee81',
		appearance:
			'd8aa9504c690c17bbf342bd6cd481145e1695c470ce3552fe58e0a9a3d41e5f4',
		library:
			'6fa884ca9e631267f8904b8597d64dcfa1e8b01aa618c49134ba15751cc6e996',
		validation:
			'04fb07b3e0a635bcf1273a1c573bf1ece614501058c57d5716d317f8ef7f0e4c',
	} );
} );
