import { materialShapeUrl } from '../shared/material-shape';

export function defaultLayerMaterial( layer, library ) {
	const groupIds = layer.settings?.material_group_ids || [];
	const groups = window.ocProductsData?.materialGroups || [];
	const validGroupIds = groupIds.filter( ( id ) =>
		groups.some( ( group ) => group.id === id )
	);
	const ids = validGroupIds.length
		? [
				...new Set(
					groups
						.filter( ( group ) =>
							validGroupIds.includes( group.id )
						)
						.flatMap( ( group ) => group.material_ids || [] )
				),
		  ]
		: layer.settings?.material_ids || [];
	const choices = library.filter(
		( item ) => item.active && ids.includes( item.id )
	);
	return (
		choices.find(
			( item ) => item.id === layer.settings?.default_material_id
		) ||
		choices[ 0 ] ||
		null
	);
}

export function areaLayerMaterial( area, layer, library ) {
	let material = null;
	for ( const candidate of area.layers || [] ) {
		if (
			candidate.visible !== false &&
			candidate.type === 'laser_material'
		) {
			material = defaultLayerMaterial( candidate, library );
		}
		if ( candidate === layer ) {
			return material;
		}
	}
	return null;
}

export function appendMaterialPreview( el, layer, width, height ) {
	const material = defaultLayerMaterial(
		layer,
		window.ocProductsData?.laserMaterials || []
	);
	const preview = document.createElement( 'div' );
	preview.className = 'oc-lp';
	if ( layer.settings?.materialSvg && material ) {
		const image = document.createElement( 'img' );
		image.alt = '';
		image.src = materialShapeUrl(
			layer.settings.materialSvg,
			material.surface,
			width,
			height
		);
		image.style.cssText =
			'display:block;width:100%;height:100%;object-fit:fill;';
		preview.appendChild( image );
	} else {
		preview.textContent = 'Upload SVG shape and choose materials';
	}
	el.appendChild( preview );
}

export function bindMaterialSettings( layer, area, commitChange ) {
	const settings = layer.settings;
	document
		.querySelectorAll( '[data-oc-material-group-choice]' )
		.forEach( ( input ) => {
			input.addEventListener( 'change', () => {
				settings.material_group_ids = Array.from(
					document.querySelectorAll(
						'[data-oc-material-group-choice]:checked'
					),
					( choice ) => choice.value
				);
				const groups = window.ocProductsData?.materialGroups || [];
				settings.material_ids = [
					...new Set(
						groups
							.filter( ( group ) =>
								settings.material_group_ids.includes( group.id )
							)
							.flatMap( ( group ) => group.material_ids || [] )
					),
				];
				if (
					! settings.material_ids.includes(
						settings.default_material_id
					)
				) {
					settings.default_material_id =
						settings.material_ids[ 0 ] || '';
				}
				commitChange( { canvas: true, rightColumn: true } );
			} );
		} );
	document
		.getElementById( 'oc-set-default-material' )
		?.addEventListener( 'change', ( event ) => {
			settings.default_material_id = event.target.value;
			commitChange( { canvas: true } );
		} );
	const input = document.getElementById( 'oc-material-shape-file' );
	const status = document.getElementById( 'oc-material-shape-status' );
	input?.addEventListener( 'change', async () => {
		const file = input.files?.[ 0 ];
		if ( ! file ) {
			return;
		}
		if (
			! /\.svg$/i.test( file.name ) ||
			! file.size ||
			file.size > 256 * 1024
		) {
			status.textContent =
				'Choose an SVG silhouette no larger than 256 KiB.';
			input.value = '';
			return;
		}
		input.disabled = true;
		status.textContent = 'Uploading SVG silhouette…';
		try {
			const data = window.ocProductsData || {};
			const body = new FormData();
			body.append( 'action', 'oc_upload_material_shape' );
			body.append( 'nonce', data.nonce || '' );
			body.append( 'file', file );
			const response = await fetch( data.ajaxUrl, {
				method: 'POST',
				body,
			} );
			const result = await response.json();
			if (
				! response.ok ||
				! result.success ||
				typeof result.data?.materialSvg !== 'string' ||
				! result.data.materialSvg
			) {
				throw new Error(
					result.data?.message ||
						'Unable to upload the SVG silhouette.'
				);
			}
			if ( ! input.isConnected || ! area?.layers.includes( layer ) ) {
				return;
			}
			settings.materialSvg = result.data.materialSvg;
			commitChange( { canvas: true, rightColumn: true } );
		} catch ( error ) {
			status.textContent =
				error.message || 'Unable to upload the SVG silhouette.';
		} finally {
			input.disabled = false;
			input.value = '';
		}
	} );
}
