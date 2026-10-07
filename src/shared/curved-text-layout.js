/**
 * Circular glyph layout in font-em units, shared by both browser previews.
 * Keep the geometry in sync with OC_Print_Curved_Text::curved_text_layout().
 * @param {number[]} widths  Glyph advances in em units.
 * @param {number}   angle   Signed arc angle in degrees.
 * @param {number}   width   Available box width.
 * @param {number}   height  Available box height.
 * @param {number}   size    Requested font size.
 * @param {number}   minimum Minimum font size.
 * @param {string}   align   Horizontal alignment.
 */
export function curvedTextLayout(
	widths,
	angle,
	width,
	height,
	size,
	minimum = 0,
	align = 'center'
) {
	const degrees = Number( angle ?? 120 );
	const sweep =
		( Math.max(
			-180,
			Math.min( 180, Number.isFinite( degrees ) ? degrees : 120 )
		) *
			Math.PI ) /
		180;
	const total = widths.reduce( ( sum, advance ) => sum + advance, 0 );
	if ( ! widths.length || total <= 0 ) {
		return { glyphs: [], fontSize: size };
	}
	const radius = Math.abs( sweep ) > 0.000001 ? total / sweep : 0;
	let cursor = 0;
	let minX = Infinity;
	let maxX = -Infinity;
	let minY = Infinity;
	let maxY = -Infinity;
	const glyphs = widths.map( ( advance ) => {
		const distance = cursor + advance / 2 - total / 2;
		cursor += advance;
		const theta = radius ? distance / radius : 0;
		const x = radius ? radius * Math.sin( theta ) : distance;
		const y = radius ? radius * ( 1 - Math.cos( theta ) ) : 0;
		const halfW =
			( Math.abs( Math.cos( theta ) ) * advance +
				Math.abs( Math.sin( theta ) ) * 1.13 ) /
			2;
		const halfH =
			( Math.abs( Math.sin( theta ) ) * advance +
				Math.abs( Math.cos( theta ) ) * 1.13 ) /
			2;
		minX = Math.min( minX, x - halfW );
		maxX = Math.max( maxX, x + halfW );
		minY = Math.min( minY, y - halfH );
		maxY = Math.max( maxY, y + halfH );
		return { x, y, angle: ( theta * 180 ) / Math.PI };
	} );
	const fontSize = Math.max(
		minimum,
		Math.min( size, width / ( maxX - minX ), height / ( maxY - minY ) )
	);
	const freeX = width - ( maxX - minX ) * fontSize;
	let offsetX = freeX / 2;
	if ( align === 'left' ) {
		offsetX = 0;
	} else if ( align === 'right' ) {
		offsetX = freeX;
	}
	return {
		fontSize,
		glyphs: glyphs.map( ( glyph ) => ( {
			...glyph,
			x: offsetX + ( glyph.x - minX ) * fontSize,
			y: height / 2 + ( glyph.y - ( minY + maxY ) / 2 ) * fontSize,
		} ) ),
	};
}
