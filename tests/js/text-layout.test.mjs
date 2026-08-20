import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const source = await readFile( 'src/shared/text-layout.js', 'utf8' );
const moduleUrl = `data:text/javascript;base64,${ Buffer.from(
	source
).toString( 'base64' ) }`;
const {
	layoutMultilineTextbox,
	multilineTextSafetyMargin,
	multilineTextboxFits,
} = await import( moduleUrl );

class FakeTextbox {
	constructor( longestWordWidth, height = 20 ) {
		this.longestWordWidth = longestWordWidth;
		this.height = height;
		this.scaleX = 1;
		this.scaleY = 1;
	}

	set( values ) {
		Object.assign( this, values );
	}

	initDimensions() {
		if ( ! this.splitByGrapheme ) {
			this.width = Math.max( this.width, this.longestWordWidth );
		}
	}

	getScaledWidth() {
		return this.width * this.scaleX;
	}

	getScaledHeight() {
		return this.height * this.scaleY;
	}
}

test( 'keeps multiline text inside the horizontal safety margins', () => {
	const textbox = new FakeTextbox( 40 );
	const layout = layoutMultilineTextbox( textbox, 100, 20 );
	const margin = multilineTextSafetyMargin( 20 );

	assert.equal( layout.contentWidth, 100 - margin.x * 2 );
	assert.equal( layout.splitByGrapheme, false );
	assert.ok( layout.width + margin.x * 2 <= 100 );
} );

test( 'grapheme-wraps a word that would make Fabric expand the textbox', () => {
	const textbox = new FakeTextbox( 140 );
	const layout = layoutMultilineTextbox( textbox, 100, 20 );

	assert.equal( layout.splitByGrapheme, true );
	assert.ok( layout.width + layout.margin.x * 2 <= 100 );
} );

test( 'checks both multiline width and height', () => {
	assert.equal(
		multilineTextboxFits( new FakeTextbox( 140, 40 ), 100, 60, 20 ),
		true
	);
	assert.equal(
		multilineTextboxFits( new FakeTextbox( 40, 58 ), 100, 60, 20 ),
		false
	);
} );
