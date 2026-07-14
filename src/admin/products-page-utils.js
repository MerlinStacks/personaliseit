export function esc( s ) {
	return String( s )
		.replace( /&/g, '&amp;' )
		.replace( /</g, '&lt;' )
		.replace( />/g, '&gt;' )
		.replace( /"/g, '&quot;' );
}

export function setVal( id, v ) {
	const el = document.getElementById( id );
	if ( el ) {
		el.value = v;
	}
}

export function getScale( img ) {
	return img && img.naturalWidth ? img.clientWidth / img.naturalWidth : 0;
}

export function clamp( v, lo, hi ) {
	return Math.min( Math.max( v, lo ), hi );
}

export function fontLimit( value ) {
	return Math.max( 0, parseInt( value, 10 ) || 0 );
}

export function normaliseHex( value ) {
	return /^#[0-9a-f]{6}$/i.test( String( value || '' ) ) ? value : '#000000';
}

export function normaliseLinkGroup( value ) {
	return String( value || '' ).trim();
}

export function normaliseRotation( value ) {
	const angle = Number( value ) || 0;
	return Math.round( ( ( angle % 360 ) + 360 ) % 360 );
}

export function normaliseAspectRatio( value, w, h ) {
	const ratio =
		Number( value ) ||
		( Number( w ) && Number( h ) ? Number( w ) / Number( h ) : 1 );
	return ratio > 0 ? ratio : 1;
}

export function currentAspectRatio( entity ) {
	return normaliseAspectRatio( entity?.aspectRatio, entity?.w, entity?.h );
}

export function updateAspectRatio( entity ) {
	if ( entity?.w && entity?.h ) {
		entity.aspectRatio = normaliseAspectRatio( 0, entity.w, entity.h );
	}
}

export function clampLayerToArea( layer, area ) {
	if ( ! layer || ! area ) {
		return;
	}
	const maxW = Math.max( 1, area.w );
	const maxH = Math.max( 1, area.h );
	layer.w = clamp( Math.round( layer.w ), 1, maxW );
	layer.h = clamp( Math.round( layer.h ), 1, maxH );
	layer.x = clamp( Math.round( layer.x ), area.x, area.x + area.w - layer.w );
	layer.y = clamp( Math.round( layer.y ), area.y, area.y + area.h - layer.h );
}

export function hexRgba( hex, a ) {
	const r = parseInt( hex.slice( 1, 3 ), 16 );
	const g = parseInt( hex.slice( 3, 5 ), 16 );
	const b = parseInt( hex.slice( 5, 7 ), 16 );
	return 'rgba(' + r + ',' + g + ',' + b + ',' + a + ')';
}

export function methodLabel( m ) {
	return ( ( window.ocProductsData || {} ).methodLabels || {} )[ m ] || m;
}
