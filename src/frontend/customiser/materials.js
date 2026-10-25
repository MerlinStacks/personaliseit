// Select only server-offered material IDs; colours never come from form data.
export function selectedMaterial( layer, selections = {} ) {
	const config = layer?.settings?.laserMaterial;
	return config?.materials?.find(
		( material ) =>
			material.id ===
			( layer.locked
				? config.defaultId
				: selections[ layer.id ] || config.defaultId )
	);
}

// Only layers above a material layer in the SAME area inherit its finish.
// Another material layer starts a new stack; legacy finishes remain below it.
export function materialForLayer( area, layer, selections = {} ) {
	let material = null;
	for ( const candidate of area?.layers || [] ) {
		if ( candidate.visible === false || candidate.visible === 0 ) {
			continue;
		}
		if ( candidate.type === 'laser_material' ) {
			material = selectedMaterial( candidate, selections );
		}
		if ( candidate.id === layer.id ) {
			return material;
		}
	}
	return null;
}

export function materialPalette( material ) {
	if ( ! material ) {
		return null;
	}
	return {
		flat: true,
		text: material.engraving,
		imageTint: material.engraving,
		photoTintMode: 'tint',
		bg: material.surface.replace( '#', '' ),
		highlight: 'rgba(255,255,255,0)',
		brightness: 0,
		contrast: 0,
		opacity: 1,
	};
}
