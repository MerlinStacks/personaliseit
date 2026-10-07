import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import vm from 'node:vm';
import { JSDOM } from 'jsdom';

const source = (
	await readFile( 'src/admin/library-browser.js', 'utf8' )
).replace( 'export function', 'function' );

function setup( memberKey ) {
	const { window } = new JSDOM(
		'<div id="oc-library-toolbar"></div><span id="count"></span><div id="empty"></div><div id="grid"></div>'
	);
	const document = window.document;
	const state = {
		items: Array.from( { length: 61 }, ( _, index ) => ( {
			id: index + 1,
			name: `Asset ${ String( index + 1 ).padStart( 2, '0' ) }`,
			active: index % 2 === 0,
		} ) ),
		groups: [ { id: 7, name: 'Seasonal', [ memberKey ]: [ '1', 3, 61 ] } ],
	};
	const create = vm.runInNewContext( `${ source }; createLibraryBrowser`, {
		window,
		document,
	} );
	const browser = create( {
		grid: document.getElementById( 'grid' ),
		count: document.getElementById( 'count' ),
		empty: document.getElementById( 'empty' ),
		getItems: () => state.items,
		getGroups: () => state.groups,
		memberKey,
		buildCard: ( item ) => {
			const card = document.createElement( 'button' );
			card.textContent = item.name;
			return card;
		},
		editGroup: ( group ) => {
			state.edited = group;
		},
	} );
	return {
		state,
		browser,
		document,
		cards: () =>
			[ ...document.querySelectorAll( '#grid button' ) ].map(
				( card ) => card.textContent
			),
		filter: ( key, value ) => {
			const field = document.querySelector( `[data-filter="${ key }"]` );
			field.value = value;
			field.dispatchEvent(
				new window.Event( key === 'search' ? 'input' : 'change', {
					bubbles: true,
				} )
			);
		},
		page: ( label ) =>
			[ ...document.querySelectorAll( 'nav button' ) ]
				.find( ( button ) => button.textContent === label )
				.click(),
	};
}

for ( const memberKey of [ 'fontIds', 'clipartIds' ] ) {
	test( `${ memberKey }: pagination replaces cards and filters the whole library`, () => {
		const ui = setup( memberKey );
		assert.equal( ui.cards().length, 24 );
		ui.page( '3' );
		assert.equal( ui.cards().length, 13 );
		assert.equal( ui.cards()[ 0 ], 'Asset 49' );
		ui.filter( 'search', ' ASSET 61 ' );
		assert.deepEqual( ui.cards(), [ 'Asset 61' ] );
		assert.equal( ui.document.querySelector( 'nav' ).hidden, true );
		ui.filter( 'search', '' );
		ui.filter( 'group', '7' );
		assert.deepEqual( ui.cards(), [ 'Asset 01', 'Asset 03', 'Asset 61' ] );
		ui.document.querySelector( '[data-action="edit"]' ).click();
		assert.equal( ui.state.edited.id, 7 );
		ui.filter( 'status', 'inactive' );
		assert.equal( ui.cards().length, 0 );
		assert.equal( ui.document.querySelector( '#grid + p' ).hidden, false );
		ui.document.querySelector( '[data-action="reset"]' ).click();
		ui.filter( 'size', '48' );
		assert.equal( ui.cards().length, 48 );
		ui.filter( 'group', 'ungrouped' );
		assert.equal( ui.cards().includes( 'Asset 01' ), false );
	} );

	test( `${ memberKey }: refresh handles edits, removed groups and shrinking pages`, () => {
		const ui = setup( memberKey );
		ui.page( '3' );
		ui.state.items = ui.state.items.slice( 0, 25 );
		ui.browser.refresh();
		assert.deepEqual( ui.cards(), [ 'Asset 25' ] );
		ui.filter( 'group', '7' );
		ui.state.groups[ 0 ][ memberKey ] = [ 2 ];
		ui.state.groups[ 0 ].name = 'Renamed group';
		ui.browser.refresh();
		assert.deepEqual( ui.cards(), [ 'Asset 02' ] );
		assert.equal(
			ui.document.querySelector( 'option[value="7"]' ).textContent,
			'Renamed group'
		);
		ui.state.groups = [];
		ui.browser.refresh();
		assert.equal( ui.cards().length, 24 );
		assert.equal(
			ui.document.querySelector( '[data-action="edit"]' ).disabled,
			true
		);
		ui.state.items = [];
		ui.browser.refresh();
		assert.equal( ui.cards().length, 0 );
		assert.equal( ui.document.getElementById( 'empty' ).style.display, '' );
	} );
}
