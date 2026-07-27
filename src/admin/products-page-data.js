export function createProductsPageDataNormalisers( deps ) {
	const { nextUid, normaliseAspectRatio, normaliseDpi, normaliseRotation } =
		deps;

	function normaliseArea( a, i ) {
		const unit = [ 'px', 'mm', 'cm', 'in' ].includes( a.unit )
			? a.unit
			: 'px';
		const material = [
			'glass',
			'gold_metal',
			'silver_metal',
			'silver_plaque',
			'black_metal',
			'wood',
			'leather',
		].includes( a.material )
			? a.material
			: 'silver_metal';
		return {
			_uid: nextUid(),
			id: Number( a.id ) || 0,
			label: a.label || '',
			method: a.method || 'uv',
			material,
			unit,
			mockupId: Number( a.mockupId ) || 0,
			mockupUrl: a.mockupUrl || '',
			x: Number( a.x ) || 0,
			y: Number( a.y ) || 0,
			w: Number( a.w ) || 300,
			h: Number( a.h ) || 300,
			dpi: normaliseDpi( a.dpi ),
			ratioLocked: !! a.ratioLocked,
			aspectRatio: normaliseAspectRatio(
				a.aspectRatio,
				Number( a.w ) || 300,
				Number( a.h ) || 300
			),
			rotation: normaliseRotation( a.rotation ),
			sortOrder: i,
			visible: a.visible !== false && a.visible !== 0,
			locked: !! a.locked,
		};
	}

	function normaliseLayer( l ) {
		const type = l.type || 'text';
		return {
			_uid: nextUid(),
			id: Number( l.id ) || 0,
			type,
			label: l.label || '',
			x: Number( l.x ) || 0,
			y: Number( l.y ) || 0,
			w: Number( l.w ) || 200,
			h: Number( l.h ) || 50,
			sortOrder: Number( l.sortOrder ) || 0,
			visible: l.visible !== false && l.visible !== 0,
			locked: !! l.locked,
			settings: normaliseSettings( type, l.settings ),
		};
	}

	function defaultSettings( type ) {
		switch ( type ) {
			case 'text':
				return {
					default_text: '',
					char_limit: 0,
					alignment: 'center',
					default_font_id: 0,
					default_font_size: 0,
					default_color: '#000000',
					min_font_size: 0,
					max_font_size: 0,
					font_groups: [],
					colour_groups: [],
					allow_font_change: true,
					allow_colour_change: true,
					allow_size_change: false,
					required: false,
					link_group: '',
					colour_link_group: '',
				};
			case 'textarea':
				return {
					default_text: '',
					char_limit: 0,
					alignment: 'center',
					line_alignment: 'top',
					default_font_id: 0,
					default_font_size: 0,
					default_color: '#000000',
					min_font_size: 0,
					max_font_size: 0,
					font_groups: [],
					colour_groups: [],
					allow_font_change: true,
					allow_colour_change: true,
					allow_size_change: false,
					required: false,
					link_group: '',
					colour_link_group: '',
				};
			case 'image':
				return {
					formats: [ 'png', 'jpg', 'svg', 'webp' ],
					max_size_mb: 10,
					remove_background: false,
					image_filter_ids: [],
					default_image_filter_id: 0,
					enable_image_colour: false,
					default_color: '#000000',
					colour_groups: [],
					default_attachment_id: 0,
					default_attachment_url: '',
					allow_image_change: true,
					allow_image_filter_change: true,
					allow_colour_change: true,
					required: false,
					link_group: '',
					colour_link_group: '',
				};
			case 'clipmask':
				return {
					formats: [ 'png', 'jpg', 'webp' ],
					max_size_mb: 10,
					remove_background: false,
					mask_shape: 'circle',
					required: false,
					link_group: '',
				};
			case 'mask':
				return {
					default_attachment_id: 0,
					default_attachment_url: '',
					required: false,
					link_group: '',
				};
			case 'spotify':
				return { colour_groups: [], required: false, link_group: '' };
			case 'lineart':
				return {
					colour_groups: [],
					required: false,
					link_group: '',
					colour_link_group: '',
				};
			case 'clipart':
				return {
					clipart_groups: [],
					default_clipart_id: 0,
					default_clipart_url: '',
					default_clipart_recolourable: false,
					allow_clipart_change: true,
					required: false,
					clipart_display: 'grid',
					link_group: '',
					colour_link_group: '',
				};
			default:
				return { required: false, link_group: '' };
		}
	}

	function normaliseSettings( type, existing ) {
		const settings = Object.assign(
			defaultSettings( type ),
			existing || {}
		);
		if (
			type === 'textarea' &&
			! [ 'top', 'center', 'bottom' ].includes( settings.line_alignment )
		) {
			settings.line_alignment = 'top';
		}
		if ( type === 'clipart' ) {
			settings.clipart_display =
				settings.clipart_display === 'carousel' ? 'carousel' : 'grid';
			settings.default_clipart_id =
				Number( settings.default_clipart_id ) || 0;
			settings.default_clipart_recolourable =
				!! settings.default_clipart_recolourable;
			settings.allow_clipart_change =
				settings.allow_clipart_change !== false;
		}
		if ( [ 'image', 'mask' ].includes( type ) ) {
			settings.default_attachment_id =
				Number( settings.default_attachment_id ) || 0;
			settings.default_attachment_url =
				settings.default_attachment_url || '';
		}
		if ( type === 'image' ) {
			settings.image_filter_ids = Array.isArray(
				settings.image_filter_ids
			)
				? settings.image_filter_ids.map( Number ).filter( Boolean )
				: [];
			settings.default_image_filter_id =
				Number( settings.default_image_filter_id ) || 0;
			if (
				settings.default_image_filter_id &&
				! settings.image_filter_ids.includes(
					settings.default_image_filter_id
				)
			) {
				settings.default_image_filter_id = 0;
			}
			settings.allow_image_change = settings.allow_image_change !== false;
			settings.allow_image_filter_change =
				settings.allow_image_filter_change !== false;
			settings.enable_image_colour = !! settings.enable_image_colour;
			settings.allow_colour_change =
				settings.allow_colour_change !== false;
		}
		return settings;
	}

	return {
		normaliseArea,
		normaliseLayer,
		defaultSettings,
		normaliseSettings,
	};
}
