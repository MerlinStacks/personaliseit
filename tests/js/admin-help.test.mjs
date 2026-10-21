import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { JSDOM } from 'jsdom';

const source = readFileSync(
	new URL( '../../assets/js/admin-help.js', import.meta.url ),
	'utf8'
);
const markup = ( id ) =>
	`<span data-oc-help-disclosure><button type="button" class="oc-help-toggle" aria-expanded="false" aria-controls="${ id }">?</button><span class="oc-help-hint" id="${ id }" hidden>Help</span></span>`;

test( 'help disclosures toggle, close siblings/outside, and dismiss with Escape', () => {
	const dom = new JSDOM(
		markup( 'first' ) + markup( 'second' ) + '<input id="outside">',
		{ runScripts: 'outside-only' }
	);
	try {
		const { window } = dom;
		window.eval( source );
		const buttons = window.document.querySelectorAll( 'button' );
		const hints = window.document.querySelectorAll( '.oc-help-hint' );
		buttons[ 0 ].click();
		assert.equal( hints[ 0 ].hidden, false );
		assert.equal( buttons[ 0 ].getAttribute( 'aria-expanded' ), 'true' );
		buttons[ 0 ].click();
		assert.equal( hints[ 0 ].hidden, true );
		buttons[ 0 ].click();
		buttons[ 1 ].click();
		assert.equal( hints[ 0 ].hidden, true );
		assert.equal( hints[ 1 ].hidden, false );
		buttons[ 1 ].dispatchEvent(
			new window.KeyboardEvent( 'keydown', {
				key: 'Escape',
				bubbles: true,
			} )
		);
		assert.equal( hints[ 1 ].hidden, true );
		assert.equal( window.document.activeElement, buttons[ 1 ] );
		buttons[ 0 ].click();
		window.document.querySelector( '#outside' ).click();
		assert.equal( hints[ 0 ].hidden, true );
		buttons[ 0 ].click();
		window.document.querySelector( '#outside' ).focus();
		assert.equal( hints[ 0 ].hidden, true );
		window.document.body.insertAdjacentHTML(
			'beforeend',
			markup( 'dynamic' )
		);
		window.document.querySelector( '[aria-controls="dynamic"]' ).click();
		assert.equal(
			window.document.querySelector( '#dynamic' ).hidden,
			false
		);
	} finally {
		dom.window.close();
	}
} );

test( 'release includes both help assets', () => {
	const pkg = JSON.parse(
		readFileSync( new URL( '../../package.json', import.meta.url ), 'utf8' )
	);
	for ( const file of [
		'assets/css/admin-help.css',
		'assets/js/admin-help.js',
	] ) {
		assert.ok(
			pkg.files.includes( file ),
			`${ file } must ship in the plugin`
		);
	}
} );
