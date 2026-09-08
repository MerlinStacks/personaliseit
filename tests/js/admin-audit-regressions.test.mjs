import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { createRequire } from 'node:module';
import test from 'node:test';
import vm from 'node:vm';

const { parse } = createRequire( import.meta.url )( 'acorn' );
async function loadFunctions( file, names, globals ) {
	const source = await readFile( file, 'utf8' );
	const tree = parse( source, {
		ecmaVersion: 'latest',
		sourceType: 'module',
	} );
	const functions = [];
	function visit( node ) {
		if ( ! node || typeof node !== 'object' ) {
			return;
		}
		if (
			node.type === 'FunctionDeclaration' &&
			names.includes( node.id.name )
		) {
			functions.push( source.slice( node.start, node.end ) );
		}
		Object.values( node ).forEach( ( value ) => {
			if ( Array.isArray( value ) ) {
				value.forEach( visit );
			} else if ( value && typeof value === 'object' ) {
				visit( value );
			}
		} );
	}
	visit( tree );
	assert.equal( functions.length, names.length );
	return vm.runInNewContext(
		`${ functions.join( '\n' ) }; ({ ${ names.join( ',' ) } })`,
		globals
	);
}
const deferred = () => {
	let resolve;
	const promise = new Promise( ( done ) => {
		resolve = done;
	} );
	return { promise, resolve };
};
const tick = () => new Promise( ( resolve ) => setImmediate( resolve ) );

test( 'rotated area layer handles and movement follow local axes at every image and unit scale', async () => {
	const globals = {
		drag: null,
		document: { getElementById: () => ( {} ) },
		getScale: () => globals.imageScale,
		unitPxScale: () => globals.unitScale,
		activeEntity: () => globals.layer,
		selectedLayer: () => globals.layer,
		selectedArea: () => globals.area,
		clamp: ( value, min, max ) => Math.max( min, Math.min( max, value ) ),
		normaliseRotation: ( value ) => Number( value ) || 0,
		updateBoundsBox() {},
		renderGhosts() {},
		updateCoordsReadout() {},
		syncRightBounds() {},
		renderHiddenFields() {},
	};
	const { startDrag, onDragMove } = await loadFunctions(
		'src/admin/products-page-canvas.js',
		[ 'startDrag', 'onDragMove' ],
		globals
	);
	for ( const rotation of [ 0, 45, 90, 180, 270, -30 ] ) {
		for ( const imageScale of [ 1, 0.4 ] ) {
			for ( const unitScale of [ 1, 300 / 25.4 ] ) {
				for ( const dir of [
					'nw',
					'n',
					'ne',
					'e',
					'se',
					's',
					'sw',
					'w',
					'move',
				] ) {
					globals.area = { x: 100, y: 200, w: 300, h: 300, rotation };
					globals.layer = { x: 150, y: 250, w: 80, h: 60 };
					globals.imageScale = imageScale;
					globals.unitScale = unitScale;
					const moving = dir === 'move';
					let dx = 0;
					if ( moving || dir.includes( 'e' ) ) {
						dx = 12;
					} else if ( dir.includes( 'w' ) ) {
						dx = -12;
					}
					let dy = 0;
					if ( moving || dir.includes( 's' ) ) {
						dy = 8;
					} else if ( dir.includes( 'n' ) ) {
						dy = -8;
					}
					const radians = ( rotation * Math.PI ) / 180;
					startDrag(
						{ clientX: 400, clientY: 300 },
						moving ? 'move' : 'resize',
						dir
					);
					onDragMove( {
						clientX:
							400 +
							( dx * Math.cos( radians ) -
								dy * Math.sin( radians ) ) *
								unitScale *
								imageScale,
						clientY:
							300 +
							( dx * Math.sin( radians ) +
								dy * Math.cos( radians ) ) *
								unitScale *
								imageScale,
					} );
					assert.deepEqual(
						globals.layer,
						{
							x: 150 + ( moving || dir.includes( 'w' ) ? dx : 0 ),
							y: 250 + ( moving || dir.includes( 'n' ) ? dy : 0 ),
							w: 80 + ( moving ? 0 : Math.abs( dx ) ),
							h: 60 + ( moving ? 0 : Math.abs( dy ) ),
						},
						`rotation=${ rotation }, scale=${ imageScale }, units=${ unitScale }, handle=${ dir }`
					);
				}
			}
		}
	}
} );

test( 'HEAD recovery requires confirmation and stops source autosaves without changing the original draft', async () => {
	const draft = JSON.parse(
		await readFile( 'tests/fixtures/admin-head-autosave.json', 'utf8' )
	);
	draft.recoveryToken = 'c'.repeat( 64 );
	const original = JSON.stringify( draft );
	const nodes = [];
	const fields = [];
	let confirmed = false;
	let stopped = false;
	const globals = {
		designId: 7,
		autosaveConflict: true,
		window: { confirm: () => confirmed },
		document: {
			getElementById: () => ( {
				prepend() {},
				appendChild: ( node ) => fields.push( node ),
			} ),
			createElement: () => {
				const node = {
					appendChild() {},
					addEventListener( event, fn ) {
						this.click = fn;
					},
				};
				nodes.push( node );
				return node;
			},
		},
		stopAutosavePoll() {
			stopped = true;
		},
		setSubmitEnabled() {},
		updateAutosaveIndicator() {},
	};
	const { showDraftRecovery } = await loadFunctions(
		'src/admin/products-page-editor.js',
		[ 'showDraftRecovery' ],
		globals
	);
	showDraftRecovery( draft );
	const button = nodes[ 1 ];
	button.click();
	assert.equal( globals.designId, 7 );
	assert.equal( fields.length, 0 );
	confirmed = true;
	button.click();
	assert.equal( globals.designId, 0 );
	assert.equal( globals.autosaveConflict, false );
	assert.equal( stopped, true );
	assert.equal(
		fields.find( ( field ) => field.name === 'oc_recovery_token' ).value,
		draft.recoveryToken
	);
	assert.equal( JSON.stringify( draft ), original );
	assert.equal(
		JSON.parse( decodeURIComponent( nodes[ 2 ].href.split( ',' )[ 1 ] ) )
			.state.areas[ 0 ].layers[ 0 ].id,
		91
	);
} );

test( 'final submit appends its completeness manifest after generated controls', async () => {
	const controls = [];
	const form = {
		querySelector: () => null,
		appendChild: ( control ) => controls.push( control ),
	};
	const { handleDesignSubmit } = await loadFunctions(
		'src/admin/products-page-editor.js',
		[ 'handleDesignSubmit' ],
		{
			isHydrated: true,
			areas: [ { layers: [ { type: 'text' } ] } ],
			autosaveConflict: false,
			designId: 0,
			autosaveRevision: 3,
			document: {
				getElementById: () => form,
				createElement: () => ( {} ),
			},
			renderHiddenFields: () =>
				controls.push( { name: 'oc_layers[0][settings]' } ),
			stopAutosavePoll() {},
		}
	);
	handleDesignSubmit( { preventDefault: assert.fail } );
	assert.equal( controls.at( -1 ).name, 'oc_final_manifest' );
	assert.deepEqual( JSON.parse( controls.at( -1 ).value ), {
		marker: 'complete',
		areas: 1,
		layers: 1,
		autosaveRevision: 3,
	} );
} );

for ( const baseRevision of [ '', 'a'.repeat( 64 ) ] ) {
	test( `restored ${
		baseRevision || 'legacy'
	} draft cannot acquire the current design revision`, async () => {
		const revisionField = { value: 'b'.repeat( 64 ) };
		const globals = {
			window: {
				ocProductsData: { designId: 7 },
				addEventListener() {},
				confirm: () => true,
			},
			document: {
				getElementById: ( id ) =>
					id === 'oc-design-revision'
						? revisionField
						: { addEventListener() {} },
			},
			setHydrationControlsDisabled() {},
			setSubmitEnabled() {},
			updateAutosaveIndicator() {},
			handleDesignSubmit() {},
			URLSearchParams,
			fetch: async () => ( {
				json: async () => ( {
					success: true,
					data: {
						revision: 1,
						state: { design: { baseRevision }, areas: [] },
					},
				} ),
			} ),
			applyAutosavedState() {},
			showDraftRecovery() {},
			finishHydration() {},
			loadDefaultData: assert.fail,
		};
		const { init } = await loadFunctions(
			'src/admin/products-page-editor.js',
			[ 'init' ],
			globals
		);
		init();
		await tick();
		assert.equal( globals.autosaveConflict, true );
		assert.equal( revisionField.value, baseRevision );
	} );
}

test( 'Enter activates the searchable assignment through its click handler', async () => {
	const php = await readFile(
		'includes/admin/class-oc-admin-products.php',
		'utf8'
	);
	const start = php.indexOf(
		'function initSearchableDesignSelect( select )'
	);
	const end = php.indexOf( 'function parseVariants( box )', start );
	const nodes = [];
	const node = () => ( {
		handlers: {},
		dataset: {},
		classList: { add() {}, remove() {} },
		addEventListener( name, handler ) {
			this.handlers[ name ] = handler;
		},
		setAttribute() {},
		removeAttribute() {},
		appendChild() {},
	} );
	let changed = 0;
	const select = {
		...node(),
		value: '0',
		selectedIndex: 0,
		options: [ { textContent: 'None' } ],
		parentNode: { insertBefore() {} },
		dispatchEvent: () => changed++,
	};
	const init = vm.runInNewContext(
		`${ php.slice( start, end ) }; initSearchableDesignSelect`,
		{
			document: {
				createElement: () => {
					const el = node();
					nodes.push( el );
					return el;
				},
				addEventListener() {},
			},
			window: { removeEventListener() {} },
			Event,
		}
	);
	init( select );
	const [ , input, list ] = nodes;
	const option = {
		dataset: { value: '7' },
		closest() {
			return this;
		},
		click: () =>
			list.handlers.click( { target: option, preventDefault() {} } ),
	};
	list.querySelector = () => option;
	input.handlers.keydown( { key: 'Enter', preventDefault() {} } );
	assert.equal( select.value, '7' );
	assert.equal( changed, 1 );
} );

test( 'draw-picker layer addition uses the shared compatibility guard', async () => {
	const area = { method: 'embroidery', layers: [] };
	let renders = 0;
	const { addLayerWithBounds } = await loadFunctions(
		'src/admin/products-page-interactions.js',
		[ 'addLayerWithBounds' ],
		{
			selectedArea: () => area,
			layerTypeSupportsPrintMethod: ( type, method ) =>
				type !== 'night_sky' || method !== 'embroidery',
			createLayer: ( type ) => ( { type } ),
			setSelectedLayerIndex() {},
			snapshot() {},
			renderAll: () => renders++,
		}
	);
	addLayerWithBounds( 'night_sky', 0, 0, 10, 10 );
	assert.equal( area.layers.length, 0 );
	assert.equal( renders, 0 );
	addLayerWithBounds( 'text', 0, 0, 10, 10 );
	assert.equal( area.layers.length, 1 );
} );

test( 'ghost rerender disposes Fabric canvases even without a usable image scale', async () => {
	let disposed = 0;
	const ghost = { _ocTextPreviewCanvas: { dispose: () => disposed++ } };
	const container = { querySelectorAll: () => [ ghost ], innerHTML: 'old' };
	const { renderGhosts } = await loadFunctions(
		'src/admin/products-page-canvas.js',
		[ 'renderGhosts' ],
		{
			document: { getElementById: () => container },
			getScale: () => 0,
		}
	);
	renderGhosts();
	assert.equal( disposed, 1 );
	assert.equal( ghost._ocTextPreviewCanvas, null );
	assert.equal( container.innerHTML, '' );
} );

test( 'font callbacks cannot recreate canvases on detached previews', async () => {
	const load = deferred();
	let renders = 0;
	const el = { isConnected: true, querySelectorAll: () => [] };
	const { applyLayerPreview } = await loadFunctions(
		'src/admin/products-page-preview.js',
		[ 'applyLayerPreview' ],
		{
			engravingPreview: () => ( {} ),
			fontLimit: () => 0,
			clampFontSize: ( size ) => size,
			findFont: () => ( { name: 'Family' } ),
			normaliseHex: () => '#000000',
			renderTextPreview: () => {
				renders++;
				el._ocTextPreviewCanvas = { dispose() {} };
			},
			document: { fonts: { load: () => load.promise } },
			cache: { clearFontCache() {} },
		}
	);
	applyLayerPreview(
		{ type: 'text', h: 20, settings: { default_text: 'Preview' } },
		el,
		100,
		20,
		true,
		false
	);
	el.isConnected = false;
	load.resolve();
	await tick();
	assert.equal( renders, 1 );
} );

test( 'font picker preserves selected non-first family variants', async () => {
	const picker = { innerHTML: '', querySelectorAll: () => [] };
	const { renderFontPicker } = await loadFunctions(
		'src/admin/font-manager.js',
		[ 'renderFontPicker' ],
		{
			fonts: [
				{ id: 1, name: 'Family', weight: 'normal' },
				{ id: 2, name: 'Family', weight: 'bold' },
			],
			groupFontPicker: picker,
			injectFontFace() {},
			updateSelectedCount() {},
			h: String,
		}
	);
	renderFontPicker( [ 2 ] );
	assert.match( picker.innerHTML, /value="1"/ );
	assert.match( picker.innerHTML, /value="2" checked/ );
} );

test( 'font group delete uses captured identity and leaves a later modal open', async () => {
	const response = deferred();
	const removed = [];
	let closed = 0;
	const globals = {
		editingGroup: { id: 1, name: 'First' },
		groupGeneration: 1,
		groupWrite: false,
		groups: [ { id: 1 }, { id: 2 } ],
		FormData,
		nonce: '',
		ajaxUrl: '',
		confirm: () => true,
		fetch: () => response.promise,
		alert: assert.fail,
		groupGrid: {
			querySelector: ( selector ) => ( {
				remove: () => removed.push( selector ),
			} ),
		},
		updateGroupsCount() {},
		closeGroupModal: () => closed++,
	};
	const { deleteGroup } = await loadFunctions(
		'src/admin/font-manager.js',
		[ 'deleteGroup' ],
		globals
	);
	const request = deleteGroup();
	globals.editingGroup = { id: 2, name: 'Second' };
	globals.groupGeneration++;
	response.resolve( { ok: true, json: async () => ( { success: true } ) } );
	await request;
	assert.deepEqual(
		globals.groups.map( ( group ) => group.id ),
		[ 2 ]
	);
	assert.match( removed[ 0 ], /"1"/ );
	assert.equal( closed, 0 );
} );

test( 'font group create remains a create after opening an existing group', async () => {
	const response = deferred();
	let added = 0;
	let closed = 0;
	const globals = {
		editingGroup: null,
		groupGeneration: 1,
		groupWrite: false,
		groupNameInput: { value: 'Created' },
		getCheckedFontIds: () => [ 2 ],
		groupSaveBtn: { textContent: 'Save' },
		groups: [ { id: 9 } ],
		FormData,
		nonce: '',
		ajaxUrl: '',
		fetch: () => response.promise,
		alert: assert.fail,
		addGroupCardToGrid: () => added++,
		updateGroupsCount() {},
		closeGroupModal: () => closed++,
	};
	const { saveGroup } = await loadFunctions(
		'src/admin/font-manager.js',
		[ 'saveGroup' ],
		globals
	);
	const request = saveGroup();
	globals.editingGroup = { id: 9 };
	globals.groupGeneration++;
	response.resolve( {
		ok: true,
		json: async () => ( {
			success: true,
			data: { id: 10, name: 'Created', fontIds: [ 2 ] },
		} ),
	} );
	await request;
	assert.equal( globals.groups.length, 2 );
	assert.equal( added, 1 );
	assert.equal( closed, 0 );
} );

for ( const manager of [ 'colour', 'clipart' ] ) {
	test( `${ manager } successful background group save updates data without closing another modal`, async () => {
		let updated = 0;
		let closed = 0;
		const globals = {
			groupWrite: null,
			editGroupId: 1,
			groupModalGeneration: 1,
			groupNameInput: () => ( { value: 'Saved' } ),
			selectedColourIds: () => [],
			selectedClipartIds: () => [],
			window: {},
			URLSearchParams,
			syncGroupWriteControls() {},
			fetch: async () => ( {
				ok: true,
				json: async () => ( {
					success: true,
					data: { id: 1, name: 'Saved' },
				} ),
			} ),
			isGroupContextCurrent: () => false,
			normaliseGroup: ( group ) => group,
			groups: [ { id: 1, name: 'Original' } ],
			updateGroupGridUI: () => updated++,
			closeGroupModal: () => closed++,
			alert: assert.fail,
			console,
		};
		const { saveGroup } = await loadFunctions(
			`src/admin/${ manager }-manager.js`,
			[ 'saveGroup' ],
			globals
		);
		await saveGroup();
		assert.equal( globals.groups[ 0 ].name, 'Saved' );
		assert.equal( updated, 1 );
		assert.equal( closed, 0 );
	} );
}

test( 'artwork writes serialize and send the latest queued selection', async () => {
	const php = await readFile(
		'includes/admin/class-oc-admin-products.php',
		'utf8'
	);
	const start = php.indexOf( 'function saveVariants( box )' );
	const end = php.indexOf(
		"document.querySelectorAll( '.oc-design-variants-admin' )",
		start
	);
	const requests = [];
	const hidden = { value: '[{"designId":1}]' };
	const status = {};
	const box = {
		dataset: {},
		querySelectorAll: () => [],
		querySelector: ( selector ) =>
			selector.includes( '-data' ) ? hidden : status,
	};
	const saveVariants = vm.runInNewContext(
		`${ php.slice( start, end ) }; saveVariants`,
		{
			parseVariants: () => JSON.parse( hidden.value ),
			URLSearchParams,
			ocProductsData: {},
			fetch: ( url, options ) => {
				const request = deferred();
				requests.push( { ...request, body: options.body } );
				return request.promise;
			},
		}
	);
	saveVariants( box );
	hidden.value = '[{"designId":2}]';
	saveVariants( box );
	hidden.value = '[{"designId":3}]';
	saveVariants( box );
	assert.equal( requests.length, 1 );
	requests[ 0 ].resolve( { json: async () => ( { success: true } ) } );
	await tick();
	assert.equal( requests.length, 2 );
	assert.equal( requests[ 1 ].body.get( 'variants' ), '[{"designId":3}]' );
	requests[ 1 ].resolve( { json: async () => ( { success: true } ) } );
	await tick();
	assert.equal( box._ocVariantSaving, false );
} );
