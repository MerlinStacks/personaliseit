import { FabricText, util } from 'fabric';
import { curvedTextLayout } from './curved-text-layout';

/**
 * Keep combining marks and emoji sequences together when bending lettering.
 * @param {string} text    Customer text.
 * @param {Object} options Fabric text options including layer centre and rotation.
 * @param {number} width   Available box width.
 * @param {number} height  Available box height.
 * @param {number} angle   Signed arc angle in degrees.
 * @param {number} minimum Minimum font size.
 */
export function createCurvedText(
	text,
	options,
	width,
	height,
	angle,
	minimum = 0
) {
	const value = String( text )
		.replace( /[\r\n]+/g, ' ' )
		.trim();
	const characters =
		typeof Intl.Segmenter === 'function'
			? Array.from(
					new Intl.Segmenter( undefined, {
						granularity: 'grapheme',
					} ).segment( value ),
					( item ) => item.segment
			  )
			: util.graphemeSplit( value );
	const objects = characters.map(
		( character ) =>
			new FabricText( character, {
				...options,
				fontSize: 100,
				originX: 'center',
				originY: 'center',
				selectable: false,
				evented: false,
				objectCaching: false,
			} )
	);
	const layout = curvedTextLayout(
		objects.map( ( object ) => object.width / 100 ),
		angle,
		width,
		height,
		options.fontSize,
		minimum,
		options.textAlign
	);
	if ( ! layout.glyphs.length ) {
		objects.forEach( ( object ) => object.dispose() );
		return [];
	}
	const rotation = Number( options.angle ) || 0;
	const radians = ( rotation * Math.PI ) / 180;
	objects.forEach( ( object, index ) => {
		const glyph = layout.glyphs[ index ];
		const x = glyph.x - width / 2;
		const y = glyph.y - height / 2;
		object.set( {
			fontSize: layout.fontSize,
			left:
				( options.left || 0 ) +
				x * Math.cos( radians ) -
				y * Math.sin( radians ),
			top:
				( options.top || 0 ) +
				x * Math.sin( radians ) +
				y * Math.cos( radians ),
			angle: rotation + glyph.angle,
		} );
		object.setCoords();
	} );
	return objects;
}
