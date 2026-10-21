import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { JSDOM } from 'jsdom';

const source = readFileSync( 'assets/js/admin-chooser-icons.js', 'utf8' );
const markup = (
	id = 12
) => `<div data-oc-chooser-icon data-kind="clipart" data-id="${ id }">
	<img data-oc-icon-preview src="/old.png"><button data-oc-icon-select>Select</button>
	<button data-oc-icon-remove>Remove</button><span data-oc-icon-status></span></div>`;
const tick = () => new Promise( ( resolve ) => setImmediate( resolve ) );

function fixture( t, fetch ) {
	const dom = new JSDOM( markup(), {
		runScripts: 'outside-only',
		url: 'https://shop.test',
	} );
	t.after( () => dom.window.close() );
	let select;
	const frame = {
		on( name, callback ) {
			select = callback;
		},
		open() {},
		state: () => ( { get: () => ( { first: () => ( { id: 88 } ) } ) } ),
	};
	dom.window.wp = { media: () => frame };
	dom.window.ocChooserIcons = { url: '/ajax', nonce: 'test-nonce' };
	dom.window.fetch = fetch;
	dom.window.eval( source );
	return { document: dom.window.document, select: () => select() };
}

test( 'media selection and removal save only a separate attachment reference', async ( t ) => {
	const bodies = [];
	const f = fixture( t, async ( url, options ) => {
		bodies.push( Object.fromEntries( options.body ) );
		return {
			ok: true,
			json: async () => ( {
				success: true,
				data: { url: bodies.length === 1 ? '/icon.png' : '' },
			} ),
		};
	} );
	const events = [];
	f.document.addEventListener( 'oc:chooser-icon-saved', ( event ) =>
		events.push( event.detail )
	);
	f.document.querySelector( '[data-oc-icon-select]' ).click();
	f.select();
	await tick();
	assert.equal( bodies[ 0 ].attachment_id, '88' );
	assert.equal( bodies[ 0 ].id, '12' );
	assert.equal( bodies[ 0 ].action, 'oc_save_chooser_icon' );
	assert.equal(
		f.document.querySelector( 'img' ).getAttribute( 'src' ),
		'/icon.png'
	);
	f.document.querySelector( '[data-oc-icon-remove]' ).click();
	await tick();
	assert.equal( bodies[ 1 ].attachment_id, '0' );
	assert.equal( f.document.querySelector( 'img' ).style.display, 'none' );
	assert.equal( events.length, 2 );
} );

test( 'late save does not overwrite another clipart editor preview', async ( t ) => {
	let finish;
	const f = fixture(
		t,
		() =>
			new Promise( ( resolve ) => {
				finish = resolve;
			} )
	);
	f.document.querySelector( '[data-oc-icon-select]' ).click();
	f.select();
	const control = f.document.querySelector( '[data-oc-chooser-icon]' );
	control.dataset.id = '13';
	control.querySelector( 'img' ).src = '/other.png';
	finish( {
		ok: true,
		json: async () => ( { success: true, data: { url: '/late.png' } } ),
	} );
	await tick();
	assert.equal(
		control.querySelector( 'img' ).getAttribute( 'src' ),
		'/other.png'
	);
	assert.equal( control.querySelector( 'button' ).disabled, false );
} );

test( 'failed saves preserve the previous icon and allow retry', async ( t ) => {
	const f = fixture( t, async () => ( {
		ok: false,
		json: async () => ( {
			success: false,
			data: { message: 'Not saved' },
		} ),
	} ) );
	f.document.querySelector( '[data-oc-icon-remove]' ).click();
	await tick();
	assert.equal(
		f.document.querySelector( 'img' ).getAttribute( 'src' ),
		'/old.png'
	);
	assert.equal(
		f.document.querySelector( '[data-oc-icon-status]' ).textContent,
		'Not saved'
	);
	assert.equal( f.document.querySelector( 'button' ).disabled, false );
} );
