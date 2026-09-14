/**
 * Preview-only mockup sampler. Each loaded canvas owns one lazy, small readback;
 * artwork never enters the buffer. A failed (e.g. CORS-tainted) read is cached too.
 * Coordinates are in the design's mockup space, independent of display scaling.
 *
 * @param {HTMLImageElement} image  Loaded mockup image.
 * @param {number}           width  Design coordinate width.
 * @param {number}           height Design coordinate height.
 */
export function createEngravingBackgroundSampler( image, width, height ) {
	let attempted = false;
	let pixels = null;
	const size = 128;

	return ( center, box, rotation = 0 ) => {
		if ( ! attempted ) {
			attempted = true;
			try {
				if ( image && width > 0 && height > 0 ) {
					const buffer = document.createElement( 'canvas' );
					buffer.width = size;
					buffer.height = size;
					const ctx = buffer.getContext( '2d', {
						willReadFrequently: true,
					} );
					ctx.drawImage( image, 0, 0, size, size );
					pixels = ctx.getImageData( 0, 0, size, size ).data;
				}
			} catch {
				// Keep the established palette when browser pixel access is unavailable.
			}
		}
		if (
			! pixels ||
			! [ center?.x, center?.y, box?.w, box?.h, rotation ].every(
				Number.isFinite
			) ||
			box.w <= 0 ||
			box.h <= 0
		) {
			return null;
		}
		const rad = ( rotation * Math.PI ) / 180;
		const values = [];
		// Sample the rotated layer footprint, not the white studio surround.
		for ( let row = 0; row < 9; row++ ) {
			for ( let col = 0; col < 9; col++ ) {
				const dx = ( ( col + 0.5 ) / 9 - 0.5 ) * box.w;
				const dy = ( ( row + 0.5 ) / 9 - 0.5 ) * box.h;
				const x =
					center.x + dx * Math.cos( rad ) - dy * Math.sin( rad );
				const y =
					center.y + dx * Math.sin( rad ) + dy * Math.cos( rad );
				if ( x < 0 || y < 0 || x >= width || y >= height ) {
					continue;
				}
				const i =
					( Math.floor( ( y / height ) * size ) * size +
						Math.floor( ( x / width ) * size ) ) *
					4;
				if ( pixels[ i + 3 ] < 230 ) {
					continue;
				}
				values.push(
					( pixels[ i ] * 0.2126 +
						pixels[ i + 1 ] * 0.7152 +
						pixels[ i + 2 ] * 0.0722 ) /
						255
				);
			}
		}
		// Require a majority of usable samples; median resists small highlights.
		if ( values.length < 41 ) {
			return null;
		}
		values.sort( ( a, b ) => a - b );
		return values[ Math.floor( values.length / 2 ) ];
	};
}
