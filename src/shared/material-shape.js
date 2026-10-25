/* global DOMParser, XMLSerializer */
// Input geometry is sanitized server-side. Apply one colour to its alpha without
// replacing holes, transparent surroundings, transforms or SVG fill rules.
export function materialShapeUrl( source, colour, width, height ) {
	const parsed = new DOMParser().parseFromString( source, 'image/svg+xml' );
	const root = parsed.documentElement;
	if ( root.localName !== 'svg' || parsed.querySelector( 'parsererror' ) ) {
		throw new Error( 'The material layer needs a valid SVG silhouette.' );
	}
	let hex = /^#[\da-f]{3}(?:[\da-f]{3})?$/i.test( colour )
		? colour.slice( 1 )
		: '171717';
	if ( hex.length === 3 ) {
		hex = [ ...hex ].map( ( c ) => c + c ).join( '' );
	}
	const channels = [ 0, 2, 4 ].map(
		( offset ) => parseInt( hex.slice( offset, offset + 2 ), 16 ) / 255
	);
	const w = Math.max( 1, Number( width ) || 1 );
	const h = Math.max( 1, Number( height ) || 1 );
	root.setAttribute( 'width', String( w ) );
	root.setAttribute( 'height', String( h ) );
	root.setAttribute( 'preserveAspectRatio', 'none' );
	const geometry = new XMLSerializer().serializeToString( root );
	const matrix = `0 0 0 0 ${ channels[ 0 ] } 0 0 0 0 ${ channels[ 1 ] } 0 0 0 0 ${ channels[ 2 ] } 0 0 0 1 0`;
	const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${ w }" height="${ h }" viewBox="0 0 ${ w } ${ h }"><defs><filter id="oc-material-tint" x="0" y="0" width="100%" height="100%" color-interpolation-filters="sRGB"><feColorMatrix type="matrix" values="${ matrix }"/></filter></defs><g filter="url(#oc-material-tint)">${ geometry }</g></svg>`;
	return `data:image/svg+xml;charset=utf-8,${ encodeURIComponent( svg ) }`;
}
