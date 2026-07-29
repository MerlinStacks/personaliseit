export function renderProductsPageHiddenFields( areas, esc ) {
	const c = document.getElementById( 'oc-hidden-fields' );
	if ( ! c ) {
		return;
	}
	let html = '';

	areas.forEach( ( area, i ) => {
		const p = 'oc_design_areas[' + i + ']';
		html +=
			'<input type="hidden" name="' +
			p +
			'[id]"                   value="' +
			esc( area.id ) +
			'">' +
			'<input type="hidden" name="' +
			p +
			'[label]"                value="' +
			esc( area.label ) +
			'">' +
			'<input type="hidden" name="' +
			p +
			'[print_method]"         value="' +
			esc( area.method ) +
			'">' +
			'<input type="hidden" name="' +
			p +
			'[engraving_material]"   value="' +
			esc( area.material || 'silver_metal' ) +
			'">' +
			'<input type="hidden" name="' +
			p +
			'[canvas_unit]"           value="' +
			esc( area.unit || 'px' ) +
			'">' +
			'<input type="hidden" name="' +
			p +
			'[mockup_attachment_id]" value="' +
			esc(
				area.storedMockupId === undefined
					? area.mockupId
					: area.storedMockupId
			) +
			'">' +
			'<input type="hidden" name="' +
			p +
			'[canvas_x]"             value="' +
			esc( area.x ) +
			'">' +
			'<input type="hidden" name="' +
			p +
			'[canvas_y]"             value="' +
			esc( area.y ) +
			'">' +
			'<input type="hidden" name="' +
			p +
			'[canvas_w]"             value="' +
			esc( area.w ) +
			'">' +
			'<input type="hidden" name="' +
			p +
			'[canvas_h]"             value="' +
			esc( area.h ) +
			'">' +
			'<input type="hidden" name="' +
			p +
			'[canvas_dpi]"           value="' +
			esc( area.dpi || 300 ) +
			'">' +
			'<input type="hidden" name="' +
			p +
			'[canvas_rotation]"      value="' +
			esc( area.rotation ) +
			'">' +
			'<input type="hidden" name="' +
			p +
			'[sort_order]"           value="' +
			esc( i ) +
			'">' +
			'<input type="hidden" name="' +
			p +
			'[visible]"              value="' +
			esc( area.visible ? '1' : '0' ) +
			'">' +
			'<input type="hidden" name="' +
			p +
			'[locked]"               value="' +
			esc( area.locked ? '1' : '0' ) +
			'">';
	} );

	let li = 0;
	areas.forEach( ( area, areaIdx ) => {
		( area.layers || [] ).forEach( ( layer, sort ) => {
			const p = 'oc_layers[' + li + ']';
			html +=
				'<input type="hidden" name="' +
				p +
				'[id]"         value="' +
				esc( layer.id ) +
				'">' +
				'<input type="hidden" name="' +
				p +
				'[area_index]" value="' +
				esc( areaIdx ) +
				'">' +
				'<input type="hidden" name="' +
				p +
				'[type]"       value="' +
				esc( layer.type ) +
				'">' +
				'<input type="hidden" name="' +
				p +
				'[label]"      value="' +
				esc( layer.label ) +
				'">' +
				'<input type="hidden" name="' +
				p +
				'[x]"          value="' +
				esc( layer.x ) +
				'">' +
				'<input type="hidden" name="' +
				p +
				'[y]"          value="' +
				esc( layer.y ) +
				'">' +
				'<input type="hidden" name="' +
				p +
				'[w]"          value="' +
				esc( layer.w ) +
				'">' +
				'<input type="hidden" name="' +
				p +
				'[h]"          value="' +
				esc( layer.h ) +
				'">' +
				'<input type="hidden" name="' +
				p +
				'[sort_order]" value="' +
				esc( sort ) +
				'">' +
				'<input type="hidden" name="' +
				p +
				'[visible]"    value="' +
				esc( layer.visible ? '1' : '0' ) +
				'">' +
				'<input type="hidden" name="' +
				p +
				'[locked]"     value="' +
				esc( layer.locked ? '1' : '0' ) +
				'">' +
				'<input type="hidden" name="' +
				p +
				'[settings]"   value="' +
				esc( JSON.stringify( layer.settings || {} ) ) +
				'">';
			li++;
		} );
	} );

	c.innerHTML = html;
}
