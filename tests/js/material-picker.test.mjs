import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import { JSDOM } from 'jsdom';

const source = ( await readFile( 'src/frontend/customiser/input-controls.js', 'utf8' ) )
	.replace( /^import\s[\s\S]*?;\n/gm, '' )
	.replace( 'export default inputControlMethods;', 'return inputControlMethods;' );

function picker( document, layerId ) {
	const fieldset = document.createElement( 'fieldset' );
	fieldset.className = 'oc-material-picker';
	fieldset.innerHTML = `<legend>Material ${ layerId }</legend><div class="oc-material-options"></div><p aria-live="polite" aria-atomic="true">Selected: <strong data-oc-selected-material-name></strong></p>`;
	for ( const [ id, name ] of [ [ 'black', 'Black with White Engraving' ], [ 'pink', 'Pink with Black Engraving' ] ] ) {
		const label = document.createElement( 'label' );
		label.className = 'oc-material-option';
		const input = document.createElement( 'input' );
		input.type = 'radio';
		input.name = `oc_material_${ layerId }`;
		input.value = id;
		input.dataset.ocMaterialLayer = layerId;
		input.dataset.materialName = name;
		const swatch = document.createElement( 'span' );
		swatch.className = 'oc-material-swatch';
		swatch.setAttribute( 'aria-hidden', 'true' );
		swatch.textContent = 'Text';
		const text = document.createElement( 'span' );
		text.className = 'oc-material-option-name';
		text.textContent = name;
		label.append( input, swatch, text );
		fieldset.querySelector( '.oc-material-options' ).append( label );
	}
	document.body.append( fieldset );
	return fieldset;
}

function fixture( selections = {} ) {
	const dom = new JSDOM( '<!doctype html><html><body></body></html>' );
	const first = picker( dom.window.document, 37 );
	const second = picker( dom.window.document, 38 );
	const controls = new Function( 'document', 'window', source )( dom.window.document, dom.window );
	let saves = 0;
	let redraws = 0;
	const app = {
		...controls,
		areas: [ { layers: [ 37, 38 ].map( ( id ) => ( {
			id, type: 'laser_material', settings: { laserMaterial: {
				defaultId: 'black', materials: [ { id: 'black' }, { id: 'pink' } ],
			} },
		} ) ) } ],
		materialSelections: selections,
		inputs: {},
		setupControlAccessibility() {},
		setupFontComboboxes() {},
		setupSpotifyModal() {},
		updateImageCropControl() {},
		updateHiddenField() { saves++; },
		scheduleRedraw() { redraws++; },
	};
	app.setupInputListeners();
	return { dom, app, first, second, saves: () => saves, redraws: () => redraws };
}

test( 'restored material selections update the readable name and native radio state', () => {
	const f = fixture( { 37: 'pink' } );
	try {
		assert.equal( f.first.querySelector( ':checked' ).value, 'pink' );
		assert.equal( f.first.querySelector( '[data-oc-selected-material-name]' ).textContent, 'Pink with Black Engraving' );
		assert.equal( f.second.querySelector( ':checked' ).value, 'black' );
		assert.equal( f.second.querySelector( '[data-oc-selected-material-name]' ).textContent, 'Black with White Engraving' );
	} finally { f.dom.window.close(); }
} );

test( 'selecting a compact swatch updates only its own name and keeps live preview/submission in sync', () => {
	const f = fixture();
	try {
		f.first.querySelector( '[value="pink"]' ).click();
		assert.equal( f.app.materialSelections[37], 'pink' );
		assert.equal( f.first.querySelector( '[data-oc-selected-material-name]' ).textContent, 'Pink with Black Engraving' );
		assert.equal( f.second.querySelector( '[data-oc-selected-material-name]' ).textContent, 'Black with White Engraving' );
		assert.equal( f.redraws(), 1 );
		assert.equal( f.saves(), 1 );
		assert.equal( f.first.querySelector( ':checked' ).labels[0].textContent, 'TextPink with Black Engraving' );
	} finally { f.dom.window.close(); }
} );

test( 'selected names are inserted as text, not executable markup', () => {
	const f = fixture();
	try {
		const radio = f.first.querySelector( '[value="pink"]' );
		radio.dataset.materialName = '<img src=x onerror=alert(1)>';
		radio.click();
		const name = f.first.querySelector( '[data-oc-selected-material-name]' );
		assert.equal( name.textContent, '<img src=x onerror=alert(1)>' );
		assert.equal( name.querySelector( 'img' ), null );
	} finally { f.dom.window.close(); }
} );

test( 'production markup keeps labelled radios, a live selection name and an associated finish disclaimer', async () => {
	const template = await readFile( 'templates/frontend/customiser-panel.php', 'utf8' );
	assert.match( template, /data-material-name=/ );
	assert.match( template, /data-oc-selected-material-name/ );
	assert.match( template, /oc-material-selection" aria-live="polite" aria-atomic="true"/ );
	assert.match( template, /oc-material-picker" aria-describedby="oc-material-hint-/ );
	assert.match( template, /oc-material-option-name/ );
} );
