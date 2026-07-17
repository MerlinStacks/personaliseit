export const VALID_UNITS = [ 'px', 'mm', 'cm', 'in' ];

export function normaliseUnit( value ) {
	return VALID_UNITS.includes( value ) ? value : 'px';
}

export function normaliseDpi( value ) {
	return Math.min(
		1200,
		Math.max( 1, Math.round( Number( value ) || 300 ) )
	);
}

export function unitPxScale( areaOrBounds ) {
	const dpi = normaliseDpi( areaOrBounds?.dpi );
	switch ( normaliseUnit( areaOrBounds?.unit ) ) {
		case 'mm':
			return dpi / 25.4;
		case 'cm':
			return dpi / 2.54;
		case 'in':
			return dpi;
		default:
			return 1;
	}
}

export function displayEntity( entity, area = null ) {
	if ( ! entity ) {
		return entity;
	}
	const sourceArea = area || entity;
	const px = unitPxScale( sourceArea );
	if ( px === 1 ) {
		return entity;
	}
	const originX = Number( sourceArea.x ) || 0;
	const originY = Number( sourceArea.y ) || 0;
	return {
		...entity,
		x: originX + ( Number( entity.x ) - originX ) * px,
		y: originY + ( Number( entity.y ) - originY ) * px,
		w: Number( entity.w || 0 ) * px,
		h: Number( entity.h || 0 ) * px,
	};
}

export function displayBounds( bounds ) {
	return displayEntity( bounds );
}

export function displayLayer( layer, bounds ) {
	return displayEntity( layer, bounds );
}

export function rasterDimensionsForLayer( layer, areaOrBounds ) {
	const display = displayLayer( layer, areaOrBounds );
	return {
		width: Math.max(
			1,
			Math.ceil( Math.abs( Number( display?.w ) || 0 ) )
		),
		height: Math.max(
			1,
			Math.ceil( Math.abs( Number( display?.h ) || 0 ) )
		),
	};
}

export function displayFontSize( fontSize, areaOrBounds, canvasScale = 1 ) {
	return (
		Math.max( 1, Number( fontSize ) || 0 ) *
		unitPxScale( areaOrBounds ) *
		canvasScale
	);
}
