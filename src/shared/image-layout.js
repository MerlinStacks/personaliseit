export function imageCropRatio( value ) {
	if ( value === 'cover' ) {
		return 1;
	}

	return Math.max( 0, Math.min( 1, ( Number( value ) || 0 ) / 100 ) );
}

export function imagePlacementScale( containScale, coverScale, crop ) {
	const amount = imageCropRatio( crop );

	return containScale + ( coverScale - containScale ) * amount;
}

export function imagePlacementNeedsClip( crop ) {
	return imageCropRatio( crop ) > 0;
}

export function imagePlacementClipPath( crop, clipPath ) {
	return imagePlacementNeedsClip( crop ) ? clipPath : null;
}
