import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { JSDOM } from 'jsdom';

const source = readFileSync( new URL( '../../src/admin/group-picker.js', import.meta.url ), 'utf8' )
	.replace( 'export function', 'return function' );

test( 'group selections span pages, survive navigation, and reset when reopened', ( t ) => {
	const dom = new JSDOM( '<div id="picker"></div><span id="count"></span>' );
	t.after( () => dom.window.close() );
	const document = dom.window.document;
	const container = document.getElementById( 'picker' );
	const count = document.getElementById( 'count' );
	const create = new Function( 'document', source )( document );
	const picker = create( container, ( items, selected ) => {
		container.innerHTML = items.map( ( item ) => `<label><input type="checkbox" value="${ item.id }" ${ selected.includes( item.id ) ? 'checked' : '' } />${ item.id }</label>` ).join( '' );
	}, ( n ) => { count.textContent = n; } );
	const items = Array.from( { length: 25 }, ( _, index ) => ( { id: index + 1 } ) );
	const step = ( value ) => document.querySelector( `[data-step="${ value }"]` ).click();
	const toggle = ( id ) => container.querySelector( `[value="${ id }"]` ).click();
	picker.open( items, [ 25 ] );
	assert.equal( container.querySelectorAll( 'input' ).length, 12 );
	assert.equal( count.textContent, '1' );
	toggle( 1 );
	step( 1 );
	toggle( 13 );
	step( 1 );
	assert.equal( container.querySelector( '[value="25"]' ).checked, true );
	assert.equal( document.querySelector( '[data-step="1"]' ).disabled, true );
	toggle( 25 );
	step( -1 );
	assert.equal( container.querySelector( '[value="13"]' ).checked, true );
	step( -1 );
	assert.equal( container.querySelector( '[value="1"]' ).checked, true );
	assert.deepEqual( picker.getSelectedIds(), [ 1, 13 ] );
	assert.equal( count.textContent, '2' );
	picker.open( items, [ 24 ] );
	assert.equal( container.querySelector( '[value="1"]' ).checked, false );
	assert.deepEqual( picker.getSelectedIds(), [ 24 ] );
	picker.open( [], [] );
	assert.equal( document.querySelector( 'nav' ).hidden, true );
	assert.equal( count.textContent, '0' );
	assert.deepEqual( picker.getSelectedIds(), [] );
} );
