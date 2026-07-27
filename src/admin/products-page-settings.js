/* eslint-disable no-unused-vars, no-nested-ternary */

export function createProductsPageSettings( deps ) {
	const {
		commitChange,
		esc,
		fontLimit,
		getAreas,
		layerLabel,
		normaliseHex,
		normaliseLinkGroup,
		renderLayerList,
		selectedArea,
		syncBoundsFromInputs,
	} = deps;

	function field( label, inputHtml ) {
		return (
			'<div class="oc-editor-field"><label class="oc-settings-label">' +
			label +
			'</label>' +
			inputHtml +
			'</div>'
		);
	}
	function toggleField( label, id, checked ) {
		return (
			'<label class="oc-toggle-label oc-settings-toggle"><span class="oc-toggle"><input type="checkbox" id="' +
			id +
			'"' +
			( checked ? ' checked' : '' ) +
			' /><span class="oc-toggle-slider"></span></span> ' +
			label +
			'</label>'
		);
	}
	function clipartDisplayField( current ) {
		const value = current === 'carousel' ? 'carousel' : 'grid';
		return (
			'<select id="oc-set-clipart-display" class="oc-input" style="width:100%;">' +
			'<option value="grid"' +
			( value === 'grid' ? ' selected' : '' ) +
			'>Grid</option>' +
			'<option value="carousel"' +
			( value === 'carousel' ? ' selected' : '' ) +
			'>One-row scroll with arrows and dots</option>' +
			'</select>'
		);
	}
	function clipartAllowedForMethod( item, printMethod ) {
		const allowed = Array.isArray( item.allowedPrintMethods )
			? item.allowedPrintMethods
			: [];
		return ! allowed.length || allowed.includes( printMethod );
	}
	function clipartForSelectedGroups( items, groupIds, printMethod = '' ) {
		const selected = selectedGroupIds( groupIds );
		const activeItems = ( items || [] ).filter(
			( item ) =>
				item.active !== false &&
				clipartAllowedForMethod( item, printMethod )
		);
		if ( ! selected.length ) {
			return activeItems;
		}
		return activeItems.filter( ( item ) =>
			( item.groupIds || [] ).some( ( id ) =>
				selected.includes( Number( id ) )
			)
		);
	}
	function clipartOptions( items, currentId ) {
		return (
			'<option value="0">No default clipart</option>' +
			( items || [] )
				.map(
					( item ) =>
						'<option value="' +
						esc( item.id ) +
						'"' +
						( Number( item.id ) === Number( currentId || 0 )
							? ' selected'
							: '' ) +
						'>' +
						esc( item.name ) +
						'</option>'
				)
				.join( '' )
		);
	}
	function setDefaultClipart( settings, clipartId, items ) {
		const item = ( items || [] ).find(
			( c ) => Number( c.id ) === Number( clipartId || 0 )
		);
		settings.default_clipart_id = item ? Number( item.id ) : 0;
		settings.default_clipart_url = item ? item.url || '' : '';
		settings.default_clipart_recolourable = item
			? item.fileType === 'svg' && item.colourChangeable !== false
			: false;
	}
	function ensureDefaultClipartInList( settings, items ) {
		if ( ! settings.default_clipart_id ) {
			return false;
		}
		if (
			( items || [] ).some(
				( item ) =>
					Number( item.id ) === Number( settings.default_clipart_id )
			)
		) {
			return false;
		}
		setDefaultClipart( settings, 0, items );
		return true;
	}
	function mediaDefaultField( settings ) {
		const hasDefault = !! settings.default_attachment_url;
		return (
			'<div class="oc-default-media-field">' +
			'<input type="hidden" id="oc-set-default-attachment-id" value="' +
			esc( settings.default_attachment_id || 0 ) +
			'" />' +
			'<input type="hidden" id="oc-set-default-attachment-url" value="' +
			esc( settings.default_attachment_url || '' ) +
			'" />' +
			'<div class="oc-mockup-thumb" style="margin-bottom:8px;">' +
			'<img id="oc-default-attachment-preview" src="' +
			esc( settings.default_attachment_url || '' ) +
			'" alt="" style="' +
			( hasDefault ? '' : 'display:none;' ) +
			'max-width:100%;height:auto;" />' +
			'<span id="oc-default-attachment-empty" style="font-size:12px;color:var(--oc-gray-400);' +
			( hasDefault ? 'display:none;' : '' ) +
			'">No default image set</span>' +
			'</div>' +
			'<div style="display:flex;gap:8px;flex-wrap:wrap;">' +
			'<button type="button" id="oc-choose-default-attachment" class="oc-btn oc-btn-secondary oc-btn-sm">Choose image</button>' +
			'<button type="button" id="oc-remove-default-attachment" class="oc-btn oc-btn-secondary oc-btn-sm' +
			( hasDefault ? '' : ' oc-default-media-remove--hidden' ) +
			'"' +
			'>Remove</button>' +
			'</div>' +
			'</div>'
		);
	}
	function alignBtns( current ) {
		return (
			'<div class="oc-align-btns">' +
			[
				[ 'left', '\u2190', 'Left' ],
				[ 'center', '\u2261', 'Center' ],
				[ 'right', '\u2192', 'Right' ],
			]
				.map(
					( [ a, icon, lbl ] ) =>
						'<button type="button" class="oc-align-btn' +
						( a === current ? ' oc-align-btn--active' : '' ) +
						'" data-align="' +
						a +
						'">' +
						icon +
						' ' +
						lbl +
						'</button>'
				)
				.join( '' ) +
			'</div>'
		);
	}
	function lineAlignBtns( current ) {
		const value = [ 'top', 'center', 'bottom' ].includes( current )
			? current
			: 'top';
		return (
			'<div class="oc-align-btns">' +
			[
				[ 'top', '\u2191', 'Top' ],
				[ 'center', '\u2195', 'Center' ],
				[ 'bottom', '\u2193', 'Bottom' ],
			]
				.map(
					( [ a, icon, lbl ] ) =>
						'<button type="button" class="oc-line-align-btn' +
						( a === value ? ' oc-align-btn--active' : '' ) +
						'" data-line-align="' +
						a +
						'">' +
						icon +
						' ' +
						lbl +
						'</button>'
				)
				.join( '' ) +
			'</div>'
		);
	}
	function groupChecks( cls, groups, selected ) {
		if ( ! groups.length ) {
			return '<span class="oc-settings-empty">No groups created yet.</span>';
		}
		return (
			'<div class="oc-group-checks">' +
			groups
				.map(
					( g ) =>
						'<label class="oc-group-check-item"><input type="checkbox" class="' +
						cls +
						'" value="' +
						esc( g.id ) +
						'"' +
						( selected.indexOf( Number( g.id ) ) !== -1
							? ' checked'
							: '' ) +
						' /><span>' +
						esc( g.name ) +
						'</span></label>'
				)
				.join( '' ) +
			'</div>'
		);
	}
	function imageFilterChecks( filters, selected ) {
		if ( ! filters.length ) {
			return '<span class="oc-settings-empty">No image filters created yet.</span>';
		}
		return (
			'<div class="oc-group-checks">' +
			filters
				.map(
					( filter ) =>
						'<label class="oc-group-check-item"><input type="checkbox" class="oc-if-check" value="' +
						esc( filter.id ) +
						'"' +
						( selected.indexOf( Number( filter.id ) ) !== -1
							? ' checked'
							: '' ) +
						' /><span>' +
						esc( filter.name ) +
						'</span></label>'
				)
				.join( '' ) +
			'</div>'
		);
	}
	function imageFilterOptions( filters, allowedIds, selectedId ) {
		const allowed = Array.isArray( allowedIds )
			? allowedIds.map( Number ).filter( Boolean )
			: [];
		const items = ( filters || [] ).filter( ( filter ) =>
			allowed.includes( Number( filter.id ) )
		);
		return (
			'<option value="0">Original</option>' +
			items
				.map(
					( filter ) =>
						'<option value="' +
						esc( filter.id ) +
						'"' +
						( Number( filter.id ) === Number( selectedId || 0 )
							? ' selected'
							: '' ) +
						'>' +
						esc( filter.name ) +
						'</option>'
				)
				.join( '' )
		);
	}
	function linkGroupOptions( current, setting = 'link_group' ) {
		const groups = [];
		getAreas().forEach( ( area ) => {
			( area.layers || [] ).forEach( ( layer ) => {
				const group = normaliseLinkGroup( layer.settings?.[ setting ] );
				if ( group && groups.indexOf( group ) === -1 ) {
					groups.push( group );
				}
			} );
		} );
		current = normaliseLinkGroup( current );
		if ( current && groups.indexOf( current ) === -1 ) {
			groups.push( current );
		}
		return groups.sort( ( a, b ) => a.localeCompare( b ) );
	}
	function linkGroupField( current, setting = 'link_group' ) {
		const groups = linkGroupOptions( current, setting );
		current = normaliseLinkGroup( current );
		const id =
			setting === 'colour_link_group'
				? 'oc-set-colour-link-group'
				: 'oc-set-link-group';
		const newId = `${ id }-new`;
		if ( ! groups.length ) {
			return `<input type="text" id="${ id }" class="oc-input" style="width:100%;" placeholder="Create a link group, e.g. name" value="" />`;
		}
		return (
			`<select id="${ id }" class="oc-input" style="width:100%;">` +
			'<option value="">No link group</option>' +
			groups
				.map(
					( group ) =>
						'<option value="' +
						esc( group ) +
						'"' +
						( group === current ? ' selected' : '' ) +
						'>' +
						esc( group ) +
						'</option>'
				)
				.join( '' ) +
			'<option value="__new">Create new link group...</option>' +
			'</select>' +
			`<input type="text" id="${ newId }" class="oc-input" style="width:100%;margin-top:8px;display:none;" placeholder="New link group name" value="" />`
		);
	}
	function fontOptions( fonts, selected ) {
		return (
			'<option value="0">Auto / first available</option>' +
			fonts
				.map(
					( f ) =>
						'<option value="' +
						esc( f.id ) +
						'"' +
						( Number( selected ) === Number( f.id )
							? ' selected'
							: '' ) +
						'>' +
						esc( f.name ) +
						'</option>'
				)
				.join( '' )
		);
	}
	function selectedGroupIds( value ) {
		return Array.isArray( value )
			? value.map( Number ).filter( Boolean )
			: [];
	}
	function membersForGroups( groups, selected, memberKey ) {
		if ( ! selected.length ) {
			return [];
		}
		const ids = [];
		groups.forEach( ( group ) => {
			if ( selected.indexOf( Number( group.id ) ) === -1 ) {
				return;
			}
			( group[ memberKey ] || [] ).forEach( ( id ) => {
				id = Number( id );
				if ( id && ids.indexOf( id ) === -1 ) {
					ids.push( id );
				}
			} );
		} );
		return ids;
	}
	function fontsForSelectedGroups( fonts, groups, selected ) {
		if ( ! selected.length ) {
			return fonts;
		}
		const ids = membersForGroups( groups, selected, 'fontIds' );
		return ids
			.map( ( id ) => fonts.find( ( font ) => Number( font.id ) === id ) )
			.filter( Boolean );
	}
	function coloursForSelectedGroups( colours, groups, selected ) {
		if ( ! selected.length ) {
			return colours;
		}
		const ids = membersForGroups( groups, selected, 'colourIds' );
		return ids
			.map( ( id ) =>
				colours.find( ( colour ) => Number( colour.id ) === id )
			)
			.filter( Boolean );
	}
	function colourOptions( colours, selected ) {
		const selectedHex = normaliseHex( selected ).toLowerCase();
		return colours
			.map( ( colour ) => {
				const hex = normaliseHex( colour.hex );
				return (
					'<option value="' +
					esc( hex ) +
					'"' +
					( hex.toLowerCase() === selectedHex ? ' selected' : '' ) +
					'>' +
					esc( colour.name ) +
					' (' +
					esc( hex ) +
					')</option>'
				);
			} )
			.join( '' );
	}
	function ensureDefaultFontInList( settings, fonts ) {
		const currentId = Number( settings.default_font_id ) || 0;
		if ( ! currentId ) {
			return false;
		}
		if ( fonts.some( ( font ) => Number( font.id ) === currentId ) ) {
			return false;
		}
		settings.default_font_id = 0;
		return true;
	}
	function normaliseLayerDefaults( layer, area = selectedArea() ) {
		if ( ! layer?.settings ) {
			return false;
		}
		const settings = layer.settings;
		const data = window.ocProductsData || {};
		let changed = false;

		if ( layer.type === 'text' || layer.type === 'textarea' ) {
			changed = ensureDefaultFontInList(
				settings,
				fontsForSelectedGroups(
					data.fonts || [],
					data.fontGroups || [],
					selectedGroupIds( settings.font_groups )
				)
			);
		}
		if ( layer.type === 'clipart' ) {
			changed =
				ensureDefaultClipartInList(
					settings,
					clipartForSelectedGroups(
						data.clipartItems || [],
						settings.clipart_groups,
						area?.method || ''
					)
				) || changed;
		}

		return changed;
	}
	function ensureDefaultColourInList( settings, colours ) {
		if ( ! colours.length ) {
			return;
		}
		const selectedHex = normaliseHex(
			settings.default_color
		).toLowerCase();
		if (
			colours.some(
				( colour ) =>
					normaliseHex( colour.hex ).toLowerCase() === selectedHex
			)
		) {
			return;
		}
		settings.default_color = normaliseHex( colours[ 0 ].hex );
	}
	function formatChecks( selected ) {
		return (
			'<div class="oc-group-checks">' +
			[ 'png', 'jpg', 'svg', 'webp', 'pdf', 'eps' ]
				.map(
					( fmt ) =>
						'<label class="oc-group-check-item"><input type="checkbox" class="oc-fmt-check" value="' +
						fmt +
						'"' +
						( selected.indexOf( fmt ) !== -1 ? ' checked' : '' ) +
						' /><span>' +
						fmt.toUpperCase() +
						'</span></label>'
				)
				.join( '' ) +
			'</div>'
		);
	}

	// ── Tab content builders ───────────────────────────────────────────────────

	function buildTabContent( tabId, layer ) {
		const s = layer.settings;
		const data = window.ocProductsData || {};
		const fGroups = data.fontGroups || [];
		const fonts = data.fonts || [];
		const colours = data.colours || [];
		const cGroups = data.colourGroups || [];
		// Engraving has no colour — don't show colour group pickers for layers in engraving areas.
		const area = selectedArea();
		const printMethod = area?.method || '';
		const aGroups = data.clipartGroups || [];
		const isEngraving = area && area.method === 'engraving';
		const supportsColourLink = [
			'text',
			'textarea',
			'image',
			'clipart',
			'lineart',
		].includes( layer.type );

		switch ( tabId ) {
			case 'general':
				return (
					field(
						'Label',
						'<input type="text" id="oc-layer-label" class="oc-input" style="width:100%;" value="' +
							esc( layer.label ) +
							'" />'
					) +
					field(
						'Link group <span class="oc-hint">(same type layers with the same value mirror customer input)</span>',
						linkGroupField( s.link_group || '' )
					) +
					( supportsColourLink
						? field(
								'Colour link group <span class="oc-hint">(keeps colour identical across text and artwork layers)</span>',
								linkGroupField(
									s.colour_link_group || '',
									'colour_link_group'
								)
						  )
						: '' ) +
					'<p class="oc-settings-section-hdr">Position</p>' +
					'<div class="oc-bounds-grid">' +
					'<div class="oc-editor-field"><label class="oc-settings-label">X</label><input type="number" id="oc-layer-x" class="oc-input" min="0" style="width:100%;" value="' +
					layer.x +
					'" /></div>' +
					'<div class="oc-editor-field"><label class="oc-settings-label">Y</label><input type="number" id="oc-layer-y" class="oc-input" min="0" style="width:100%;" value="' +
					layer.y +
					'" /></div>' +
					'<div class="oc-editor-field"><label class="oc-settings-label">W</label><input type="number" id="oc-layer-w" class="oc-input" min="1" style="width:100%;" value="' +
					layer.w +
					'" /></div>' +
					'<div class="oc-editor-field"><label class="oc-settings-label">H</label><input type="number" id="oc-layer-h" class="oc-input" min="1" style="width:100%;" value="' +
					layer.h +
					'" /></div>' +
					'</div>'
				);
			case 'content':
				return (
					field(
						'Default text',
						'<input type="text" id="oc-set-default-text" class="oc-input" style="width:100%;" placeholder="e.g. Your Name Here" value="' +
							esc( s.default_text || '' ) +
							'" />'
					) +
					field(
						'Max characters <span class="oc-hint">(0 = unlimited)</span>',
						'<input type="number" id="oc-set-char-limit" class="oc-input" min="0" style="width:100%;" value="' +
							esc( s.char_limit || 0 ) +
							'" />'
					)
				);
			case 'style': {
				const fontGroupsSelected = selectedGroupIds( s.font_groups );
				const colourGroupsSelected = selectedGroupIds(
					s.colour_groups
				);
				const availableFonts = fontsForSelectedGroups(
					fonts,
					fGroups,
					fontGroupsSelected
				);
				const availableColours = coloursForSelectedGroups(
					colours,
					cGroups,
					colourGroupsSelected
				);
				return (
					field( 'Alignment', alignBtns( s.alignment || 'center' ) ) +
					( layer.type === 'textarea'
						? field(
								'Line alignment',
								lineAlignBtns( s.line_alignment || 'top' )
						  )
						: '' ) +
					( availableFonts.length
						? field(
								'Default font',
								'<select id="oc-set-default-font" class="oc-input" style="width:100%;">' +
									fontOptions(
										availableFonts,
										s.default_font_id || 0
									) +
									'</select>'
						  )
						: field(
								'Default font',
								'<span class="oc-settings-empty">' +
									( fontGroupsSelected.length
										? 'No fonts are available in the selected groups.'
										: 'No fonts uploaded yet.' ) +
									'</span>'
						  ) ) +
					'<div class="oc-bounds-grid">' +
					'<div class="oc-editor-field"><label class="oc-settings-label">Default font size <span class="oc-hint">(0 = auto)</span></label><input type="number" id="oc-set-default-font-size" class="oc-input" min="0" style="width:100%;" value="' +
					esc( s.default_font_size || 0 ) +
					'" /></div>' +
					( isEngraving
						? ''
						: colourGroupsSelected.length
						? availableColours.length
							? '<div class="oc-editor-field"><label class="oc-settings-label">Default colour</label><select id="oc-set-default-color" class="oc-input" style="width:100%;">' +
							  colourOptions(
									availableColours,
									s.default_color
							  ) +
							  '</select></div>'
							: '<div class="oc-editor-field"><label class="oc-settings-label">Default colour</label><span class="oc-settings-empty">No colours are available in the selected groups.</span></div>'
						: '<div class="oc-editor-field"><label class="oc-settings-label">Default colour</label><input type="color" id="oc-set-default-color" class="oc-input" style="width:100%;height:38px;" value="' +
						  esc( normaliseHex( s.default_color ) ) +
						  '" /></div>' ) +
					'</div>' +
					'<div class="oc-bounds-grid">' +
					'<div class="oc-editor-field"><label class="oc-settings-label">Min font size <span class="oc-hint">(0 = auto)</span></label><input type="number" id="oc-set-min-font-size" class="oc-input" min="0" style="width:100%;" value="' +
					esc( s.min_font_size || 0 ) +
					'" /></div>' +
					'<div class="oc-editor-field"><label class="oc-settings-label">Max font size <span class="oc-hint">(0 = auto)</span></label><input type="number" id="oc-set-max-font-size" class="oc-input" min="0" style="width:100%;" value="' +
					esc( s.max_font_size || 0 ) +
					'" /></div>' +
					'</div>' +
					( fGroups.length
						? field(
								'Font groups <span class="oc-hint">(empty = all)</span>',
								groupChecks(
									'oc-fg-check',
									fGroups,
									s.font_groups || []
								)
						  )
						: field(
								'Font groups',
								'<span class="oc-settings-empty">No font groups created yet.</span>'
						  ) ) +
					( isEngraving
						? ''
						: cGroups.length
						? field(
								'Colour groups <span class="oc-hint">(empty = all)</span>',
								groupChecks(
									'oc-cg-check',
									cGroups,
									s.colour_groups || []
								)
						  )
						: field(
								'Colour groups',
								'<span class="oc-settings-empty">No colour groups created yet.</span>'
						  ) )
				);
			}
			case 'file':
				return (
					field( 'Default image', mediaDefaultField( s ) ) +
					field(
						'Enabled image filters <span class="oc-hint">(available choices)</span>',
						imageFilterChecks(
							data.imageFilters || [],
							s.image_filter_ids || []
						)
					) +
					field(
						'Default filter',
						'<select id="oc-set-default-image-filter" class="oc-input" style="width:100%;">' +
							imageFilterOptions(
								data.imageFilters || [],
								s.image_filter_ids || [],
								s.default_image_filter_id || 0
							) +
							'</select><span class="oc-hint">Turn off Customer can change > Filter to lock this selection and hide filter options on the storefront.</span>'
					) +
					field(
						'Accepted formats',
						formatChecks(
							s.formats || [ 'png', 'jpg', 'svg', 'webp' ]
						)
					) +
					field(
						'Max file size (MB)',
						'<input type="number" id="oc-set-max-size" class="oc-input" min="1" style="width:100%;" value="' +
							esc( s.max_size_mb || 10 ) +
							'" />'
					) +
					toggleField(
						'Automatically remove background',
						'oc-set-remove-background',
						!! s.remove_background
					)
				);
			case 'overlay':
				return (
					field( 'Mask PNG', mediaDefaultField( s ) ) +
					'<span class="oc-hint">This transparent PNG is shown above all customer artwork and is excluded from print files.</span>'
				);
			case 'mask':
				return field(
					'Mask shape',
					'<select id="oc-set-mask-shape" class="oc-input" style="width:100%;"><option value="circle"' +
						( ( s.mask_shape || 'circle' ) === 'circle'
							? ' selected'
							: '' ) +
						'>Circle</option></select>'
				);
			case 'appearance':
			case 'colours':
				if ( isEngraving ) {
					return '<span class="oc-settings-empty">Colour is not applicable for engraving.</span>';
				}
				if ( layer.type === 'image' ) {
					const selected = selectedGroupIds( s.colour_groups );
					const available = coloursForSelectedGroups(
						colours,
						cGroups,
						selected
					);
					return (
						toggleField(
							'Enable colour for filtered image',
							'oc-set-enable-image-colour',
							!! s.enable_image_colour
						) +
						field(
							'Default colour',
							selected.length && available.length
								? '<select id="oc-set-default-color" class="oc-input" style="width:100%;">' +
										colourOptions(
											available,
											s.default_color
										) +
										'</select>'
								: '<input type="color" id="oc-set-default-color" class="oc-input" style="width:100%;height:38px;" value="' +
										esc( normaliseHex( s.default_color ) ) +
										'" />'
						) +
						( cGroups.length
							? field(
									'Colour groups <span class="oc-hint">(empty = all)</span>',
									groupChecks(
										'oc-cg-check',
										cGroups,
										s.colour_groups || []
									)
							  )
							: field(
									'Colour groups',
									'<span class="oc-settings-empty">No colour groups created yet.</span>'
							  ) )
					);
				}
				return cGroups.length
					? field(
							'Colour groups <span class="oc-hint">(empty = all)</span>',
							groupChecks(
								'oc-cg-check',
								cGroups,
								s.colour_groups || []
							)
					  )
					: '<span class="oc-settings-empty">No colour groups created yet.</span>';
			case 'library': {
				const availableClipartItems = clipartForSelectedGroups(
					data.clipartItems || [],
					selectedGroupIds( s.clipart_groups ),
					printMethod
				);
				return (
					( aGroups.length
						? field(
								'Clipart groups <span class="oc-hint">(empty = all)</span>',
								groupChecks(
									'oc-ag-check',
									aGroups,
									s.clipart_groups || []
								)
						  )
						: '<span class="oc-settings-empty">No clipart groups created yet.</span>' ) +
					field(
						'Default clipart',
						availableClipartItems.length
							? '<select id="oc-set-default-clipart" class="oc-input" style="width:100%;">' +
									clipartOptions(
										availableClipartItems,
										s.default_clipart_id || 0
									) +
									'</select>'
							: '<span class="oc-settings-empty">No active clipart is available.</span>'
					)
				);
			}
			case 'validation':
				return (
					toggleField(
						'Required field',
						'oc-set-required',
						s.required
					) +
					( layer.type === 'clipart'
						? field(
								'Frontend display',
								clipartDisplayField(
									s.clipart_display || 'grid'
								)
						  )
						: '' )
				);
			case 'properties':
				if ( layer.type === 'image' ) {
					return (
						'<p class="oc-settings-section-hdr">Customer can change</p>' +
						toggleField(
							'Image',
							'oc-set-allow-image-change',
							s.allow_image_change !== false
						) +
						toggleField(
							'Filter',
							'oc-set-allow-image-filter-change',
							s.allow_image_filter_change !== false
						) +
						( s.enable_image_colour
							? toggleField(
									'Colour',
									'oc-set-allow-colour-change',
									s.allow_colour_change !== false
							  )
							: '' )
					);
				}
				if ( layer.type === 'clipart' ) {
					return (
						'<p class="oc-settings-section-hdr">Customer can change</p>' +
						toggleField(
							'Clipart',
							'oc-set-allow-clipart-change',
							s.allow_clipart_change !== false
						)
					);
				}
				return (
					toggleField(
						'Required field',
						'oc-set-required',
						s.required
					) +
					'<p class="oc-settings-section-hdr">Customer can change</p>' +
					toggleField(
						'Font',
						'oc-set-allow-font-change',
						s.allow_font_change !== false
					) +
					( isEngraving
						? ''
						: toggleField(
								'Colour',
								'oc-set-allow-colour-change',
								s.allow_colour_change !== false
						  ) ) +
					toggleField(
						'Size',
						'oc-set-allow-size-change',
						!! s.allow_size_change
					)
				);
			default:
				return '';
		}
	}

	function bindSettingsHandlers( layer ) {
		const s = layer.settings;
		const area = selectedArea();
		const data = window.ocProductsData || {};

		document
			.getElementById( 'oc-layer-label' )
			?.addEventListener( 'input', ( e ) => {
				layer.label = e.target.value;
				renderLayerList( area );
				commitChange( { canvas: true } );
			} );
		[ 'oc-layer-x', 'oc-layer-y', 'oc-layer-w', 'oc-layer-h' ].forEach(
			( id ) => {
				document
					.getElementById( id )
					?.addEventListener( 'input', () =>
						syncBoundsFromInputs( id )
					);
			}
		);
		document
			.getElementById( 'oc-set-default-text' )
			?.addEventListener( 'input', ( e ) => {
				s.default_text = e.target.value;
				commitChange( { canvas: true } );
			} );
		const linkGroupControl = document.getElementById( 'oc-set-link-group' );
		const newLinkGroupControl = document.getElementById(
			'oc-set-link-group-new'
		);
		linkGroupControl?.addEventListener(
			linkGroupControl.tagName === 'SELECT' ? 'change' : 'input',
			( e ) => {
				if ( e.target.value === '__new' ) {
					if ( newLinkGroupControl ) {
						newLinkGroupControl.style.display = '';
						newLinkGroupControl.focus();
						s.link_group = normaliseLinkGroup(
							newLinkGroupControl.value
						);
					}
				} else {
					if ( newLinkGroupControl ) {
						newLinkGroupControl.style.display = 'none';
					}
					s.link_group = normaliseLinkGroup( e.target.value );
				}
				commitChange();
			}
		);
		newLinkGroupControl?.addEventListener( 'input', ( e ) => {
			s.link_group = normaliseLinkGroup( e.target.value );
			commitChange();
		} );
		const colourLinkGroupControl = document.getElementById(
			'oc-set-colour-link-group'
		);
		const newColourLinkGroupControl = document.getElementById(
			'oc-set-colour-link-group-new'
		);
		colourLinkGroupControl?.addEventListener(
			colourLinkGroupControl.tagName === 'SELECT' ? 'change' : 'input',
			( e ) => {
				if ( e.target.value === '__new' ) {
					if ( newColourLinkGroupControl ) {
						newColourLinkGroupControl.style.display = '';
						newColourLinkGroupControl.focus();
						s.colour_link_group = normaliseLinkGroup(
							newColourLinkGroupControl.value
						);
					}
				} else {
					if ( newColourLinkGroupControl ) {
						newColourLinkGroupControl.style.display = 'none';
					}
					s.colour_link_group = normaliseLinkGroup( e.target.value );
				}
				commitChange();
			}
		);
		newColourLinkGroupControl?.addEventListener( 'input', ( e ) => {
			s.colour_link_group = normaliseLinkGroup( e.target.value );
			commitChange();
		} );
		document
			.getElementById( 'oc-set-char-limit' )
			?.addEventListener( 'input', ( e ) => {
				s.char_limit = parseInt( e.target.value, 10 ) || 0;
				commitChange();
			} );
		document
			.getElementById( 'oc-choose-default-attachment' )
			?.addEventListener( 'click', () => {
				if ( ! window.wp || ! window.wp.media ) {
					return;
				}
				const frame = window.wp.media( {
					title:
						layer.type === 'mask'
							? 'Select Mask PNG'
							: 'Select Default Image',
					button: {
						text:
							layer.type === 'mask'
								? 'Use as Mask'
								: 'Use as Default',
					},
					multiple: false,
					library: {
						type: 'image',
						...( layer.type === 'mask' ? { subtype: 'png' } : {} ),
					},
				} );
				frame.on( 'select', () => {
					const attachment = frame
						.state()
						.get( 'selection' )
						.first()
						?.toJSON();
					if ( ! attachment ) {
						return;
					}
					const attachmentUrl =
						attachment.url ||
						attachment.sizes?.full?.url ||
						attachment.originalImageURL ||
						'';
					const attachmentMime = String(
						attachment.mime || ''
					).toLowerCase();
					const isPng =
						[ 'image/png', 'image/x-png' ].includes(
							attachmentMime
						) ||
						attachment.subtype === 'png' ||
						/\.png(?:[?#]|$)/i.test(
							attachment.filename || attachmentUrl
						);
					if (
						layer.type === 'mask' &&
						( ! isPng || ! attachmentUrl )
					) {
						const empty = document.getElementById(
							'oc-default-attachment-empty'
						);
						if ( empty ) {
							empty.textContent = ! isPng
								? 'Please select a PNG image.'
								: 'The selected PNG has no usable URL.';
						}
						return;
					}
					s.default_attachment_id = Number( attachment.id ) || 0;
					s.default_attachment_url =
						layer.type === 'mask'
							? attachmentUrl
							: attachment.sizes?.medium?.url ||
							  attachmentUrl ||
							  '';
					commitChange( { canvas: true, rightColumn: true } );
				} );
				frame.open();
			} );
		document
			.getElementById( 'oc-remove-default-attachment' )
			?.addEventListener( 'click', () => {
				s.default_attachment_id = 0;
				s.default_attachment_url = '';
				commitChange( { canvas: true, rightColumn: true } );
			} );
		document
			.getElementById( 'oc-set-default-font' )
			?.addEventListener( 'change', ( e ) => {
				s.default_font_id = parseInt( e.target.value, 10 ) || 0;
				commitChange( { canvas: true } );
			} );
		document
			.getElementById( 'oc-set-default-font-size' )
			?.addEventListener( 'input', ( e ) => {
				s.default_font_size = fontLimit( e.target.value );
				commitChange( { canvas: true } );
			} );
		document
			.getElementById( 'oc-set-default-color' )
			?.addEventListener( 'change', ( e ) => {
				s.default_color = normaliseHex( e.target.value );
				commitChange( { canvas: true } );
			} );
		document
			.getElementById( 'oc-set-default-color' )
			?.addEventListener( 'input', ( e ) => {
				s.default_color = normaliseHex( e.target.value );
				commitChange( { canvas: true } );
			} );
		document
			.getElementById( 'oc-set-min-font-size' )
			?.addEventListener( 'input', ( e ) => {
				s.min_font_size = fontLimit( e.target.value );
				commitChange( { canvas: true } );
			} );
		document
			.getElementById( 'oc-set-max-font-size' )
			?.addEventListener( 'input', ( e ) => {
				s.max_font_size = fontLimit( e.target.value );
				commitChange( { canvas: true } );
			} );
		document.querySelectorAll( '.oc-align-btn' ).forEach( ( btn ) => {
			btn.addEventListener( 'click', () => {
				s.alignment = btn.dataset.align;
				document
					.querySelectorAll( '.oc-align-btn' )
					.forEach( ( b ) =>
						b.classList.toggle(
							'oc-align-btn--active',
							b.dataset.align === btn.dataset.align
						)
					);
				commitChange( { canvas: true } );
			} );
		} );
		document.querySelectorAll( '.oc-line-align-btn' ).forEach( ( btn ) => {
			btn.addEventListener( 'click', () => {
				s.line_alignment = btn.dataset.lineAlign || 'top';
				document
					.querySelectorAll( '.oc-line-align-btn' )
					.forEach( ( b ) =>
						b.classList.toggle(
							'oc-align-btn--active',
							b.dataset.lineAlign === btn.dataset.lineAlign
						)
					);
				commitChange( { canvas: true } );
			} );
		} );
		document.querySelectorAll( '.oc-fg-check' ).forEach( ( cb ) => {
			cb.addEventListener( 'change', () => {
				s.font_groups = [
					...document.querySelectorAll( '.oc-fg-check:checked' ),
				].map( ( c ) => Number( c.value ) );
				normaliseLayerDefaults( layer, area );
				commitChange( { canvas: true, rightColumn: true } );
			} );
		} );
		document.querySelectorAll( '.oc-cg-check' ).forEach( ( cb ) => {
			cb.addEventListener( 'change', () => {
				s.colour_groups = [
					...document.querySelectorAll( '.oc-cg-check:checked' ),
				].map( ( c ) => Number( c.value ) );
				const selected = selectedGroupIds( s.colour_groups );
				if ( selected.length ) {
					ensureDefaultColourInList(
						s,
						coloursForSelectedGroups(
							data.colours || [],
							data.colourGroups || [],
							selected
						)
					);
				}
				commitChange( { canvas: true, rightColumn: true } );
			} );
		} );
		document.querySelectorAll( '.oc-ag-check' ).forEach( ( cb ) => {
			cb.addEventListener( 'change', () => {
				s.clipart_groups = [
					...document.querySelectorAll( '.oc-ag-check:checked' ),
				].map( ( c ) => Number( c.value ) );
				ensureDefaultClipartInList(
					s,
					clipartForSelectedGroups(
						data.clipartItems || [],
						s.clipart_groups,
						area?.method || ''
					)
				);
				commitChange( { rightColumn: true } );
			} );
		} );
		document.querySelectorAll( '.oc-if-check' ).forEach( ( cb ) => {
			cb.addEventListener( 'change', () => {
				s.image_filter_ids = [
					...document.querySelectorAll( '.oc-if-check:checked' ),
				].map( ( c ) => Number( c.value ) );
				if (
					s.default_image_filter_id &&
					! s.image_filter_ids.includes(
						Number( s.default_image_filter_id )
					)
				) {
					s.default_image_filter_id = 0;
				}
				commitChange( { rightColumn: true } );
			} );
		} );
		document
			.getElementById( 'oc-set-default-image-filter' )
			?.addEventListener( 'change', ( e ) => {
				s.default_image_filter_id = parseInt( e.target.value, 10 ) || 0;
				commitChange( { canvas: true } );
			} );
		document
			.getElementById( 'oc-set-default-clipart' )
			?.addEventListener( 'change', ( e ) => {
				setDefaultClipart(
					s,
					e.target.value,
					clipartForSelectedGroups(
						data.clipartItems || [],
						s.clipart_groups,
						area?.method || ''
					)
				);
				commitChange( { canvas: true } );
			} );
		document.querySelectorAll( '.oc-fmt-check' ).forEach( ( cb ) => {
			cb.addEventListener( 'change', () => {
				s.formats = [
					...document.querySelectorAll( '.oc-fmt-check:checked' ),
				].map( ( c ) => c.value );
				commitChange();
			} );
		} );
		document
			.getElementById( 'oc-set-max-size' )
			?.addEventListener( 'input', ( e ) => {
				s.max_size_mb = parseInt( e.target.value, 10 ) || 10;
				commitChange();
			} );
		document
			.getElementById( 'oc-set-remove-background' )
			?.addEventListener( 'change', ( e ) => {
				s.remove_background = e.target.checked;
				commitChange();
			} );
		document
			.getElementById( 'oc-set-mask-shape' )
			?.addEventListener( 'change', ( e ) => {
				s.mask_shape = e.target.value || 'circle';
				commitChange( { canvas: true } );
			} );
		document
			.getElementById( 'oc-set-required' )
			?.addEventListener( 'change', ( e ) => {
				s.required = e.target.checked;
				commitChange();
			} );
		document
			.getElementById( 'oc-set-clipart-display' )
			?.addEventListener( 'change', ( e ) => {
				s.clipart_display =
					e.target.value === 'carousel' ? 'carousel' : 'grid';
				commitChange();
			} );
		document
			.getElementById( 'oc-set-enable-image-colour' )
			?.addEventListener( 'change', ( e ) => {
				s.enable_image_colour = e.target.checked;
				commitChange( { canvas: true } );
			} );
		document
			.getElementById( 'oc-set-allow-font-change' )
			?.addEventListener( 'change', ( e ) => {
				s.allow_font_change = e.target.checked;
				commitChange();
			} );
		document
			.getElementById( 'oc-set-allow-colour-change' )
			?.addEventListener( 'change', ( e ) => {
				s.allow_colour_change = e.target.checked;
				commitChange();
			} );
		document
			.getElementById( 'oc-set-allow-size-change' )
			?.addEventListener( 'change', ( e ) => {
				s.allow_size_change = e.target.checked;
				commitChange();
			} );
		document
			.getElementById( 'oc-set-allow-image-change' )
			?.addEventListener( 'change', ( e ) => {
				s.allow_image_change = e.target.checked;
				commitChange();
			} );
		document
			.getElementById( 'oc-set-allow-image-filter-change' )
			?.addEventListener( 'change', ( e ) => {
				s.allow_image_filter_change = e.target.checked;
				commitChange();
			} );
		document
			.getElementById( 'oc-set-allow-clipart-change' )
			?.addEventListener( 'change', ( e ) => {
				s.allow_clipart_change = e.target.checked;
				commitChange();
			} );
	}

	return {
		buildTabContent,
		bindSettingsHandlers,
		normaliseLayerDefaults,
	};
}
