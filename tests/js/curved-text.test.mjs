import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { execFileSync } from 'node:child_process';
import test from 'node:test';
import * as fabric from 'fabric/node';

const moduleUrl = ( source ) =>
	`data:text/javascript;base64,${ Buffer.from( source ).toString(
		'base64'
	) }`;
const layoutUrl = moduleUrl(
	await readFile( 'src/shared/curved-text-layout.js', 'utf8' )
);
const { curvedTextLayout } = await import( layoutUrl );
globalThis.ocCurvedFabric = fabric;
const factorySource = ( await readFile( 'src/shared/curved-text.js', 'utf8' ) )
	.replace(
		"import { FabricText, util } from 'fabric';",
		'const { FabricText, util } = globalThis.ocCurvedFabric;'
	)
	.replace( "'./curved-text-layout'", JSON.stringify( layoutUrl ) );
const { createCurvedText } = await import( moduleUrl( factorySource ) );
delete globalThis.ocCurvedFabric;

test( 'arch, bowl and straight text preserve left-to-right order and fit both dimensions', () => {
	for ( const angle of [ -180, -120, -1, 0, 1, 120, 180 ] ) {
		const widths = [ 0.7, 0.2, 0.8, 0.3, 0.7 ];
		const { glyphs, fontSize } = curvedTextLayout(
			widths,
			angle,
			100,
			35,
			100
		);
		glyphs.forEach( ( glyph, index ) => {
			const radians = ( glyph.angle * Math.PI ) / 180;
			const halfW =
				( ( Math.abs( Math.cos( radians ) ) * widths[ index ] +
					Math.abs( Math.sin( radians ) ) * 1.13 ) *
					fontSize ) /
				2;
			const halfH =
				( ( Math.abs( Math.sin( radians ) ) * widths[ index ] +
					Math.abs( Math.cos( radians ) ) * 1.13 ) *
					fontSize ) /
				2;
			assert.ok(
				glyph.x - halfW >= -1e-8 && glyph.x + halfW <= 100 + 1e-8
			);
			assert.ok(
				glyph.y - halfH >= -1e-8 && glyph.y + halfH <= 35 + 1e-8
			);
			if ( index ) {
				assert.ok( glyph.x > glyphs[ index - 1 ].x );
			}
		} );
		assert.equal(
			Math.sign( glyphs[ 0 ].y - glyphs[ 2 ].y ),
			Math.sign( angle )
		);
	}
} );

test( 'browser geometry matches production PHP for every curve direction and alignment', () => {
	const cases = [];
	for ( const angle of [ -180, -75, 0, 45, 180 ] ) {
		for ( const align of [ 'left', 'center', 'right' ] ) {
			cases.push( [
				[ 0.8, 0.22, 0.5, 0.33 ],
				angle,
				240,
				90,
				30,
				0,
				align,
			] );
		}
	}
	const php = `define('ABSPATH', getcwd()); require 'includes/print/trait-oc-print-curved-text.php'; class CurvedTest { use OC_Print_Curved_Text; public static function run($args) { return self::curved_text_layout(...$args); } } echo json_encode(array_map([CurvedTest::class, 'run'], json_decode(stream_get_contents(STDIN), true)));`;
	const results = JSON.parse(
		execFileSync( 'php', [ '-r', php ], {
			input: JSON.stringify( cases ),
			encoding: 'utf8',
		} )
	);
	cases.forEach( ( args, index ) => {
		const browser = curvedTextLayout( ...args );
		assert.ok(
			Math.abs( browser.fontSize - results[ index ].fontSize ) < 1e-8
		);
		browser.glyphs.forEach( ( glyph, i ) => {
			for ( const key of [ 'x', 'y', 'angle' ] ) {
				assert.ok(
					Math.abs(
						glyph[ key ] - results[ index ].glyphs[ i ][ key ]
					) < 1e-8
				);
			}
		} );
	} );
} );

test( 'Fabric keeps graphemes intact and applies layer rotation around the layer centre', () => {
	const text = 'Ae\u0301👩‍👩‍👧‍👦';
	const options = {
		left: 100,
		top: 100,
		fontSize: 30,
		fontFamily: 'sans-serif',
	};
	const normal = createCurvedText( text, options, 180, 90, 120 );
	const rotated = createCurvedText(
		text,
		{ ...options, angle: 90 },
		180,
		90,
		120
	);
	assert.equal( normal.length, 3 );
	assert.equal( normal[ 1 ].text, 'e\u0301' );
	normal.forEach( ( glyph, index ) => {
		assert.ok(
			Math.abs( rotated[ index ].left - ( 200 - glyph.top ) ) < 1e-8
		);
		assert.ok( Math.abs( rotated[ index ].top - glyph.left ) < 1e-8 );
		assert.equal( rotated[ index ].angle, glyph.angle + 90 );
	} );
	[ ...normal, ...rotated ].forEach( ( glyph ) => glyph.dispose() );
	assert.deepEqual( createCurvedText( '   ', options, 180, 90, 120 ), [] );
} );
