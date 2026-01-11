import { useRef, useEffect } from '@wordpress/element';

const Ruler = ( { orientation, scale, offset, length, thickness = 20 } ) => {
	const canvasRef = useRef( null );

	useEffect( () => {
		const canvas = canvasRef.current;
		if ( ! canvas ) return;
		const ctx = canvas.getContext( '2d' );

		// Clear
		ctx.clearRect( 0, 0, canvas.width, canvas.height );
		ctx.fillStyle = '#f9f9f9';
		ctx.fillRect( 0, 0, canvas.width, canvas.height );

		ctx.fillStyle = '#333';
		ctx.strokeStyle = '#ccc';
		ctx.lineWidth = 1;
		ctx.font = '10px sans-serif';

		// Border
		ctx.beginPath();
		if ( orientation === 'horizontal' ) {
			ctx.moveTo( 0, thickness );
			ctx.lineTo( length, thickness );
		} else {
			ctx.moveTo( thickness, 0 );
			ctx.lineTo( thickness, length );
		}
		ctx.stroke();

		// Ticks
		// Determine step size based on scale
		// We want ticks every ~50 visual pixels
		const baseStep = 50 / scale;
		// Round baseStep to nice number (10, 20, 50, 100)
		const log = Math.log10( baseStep );
		const power = Math.floor( log );
		const mag = Math.pow( 10, power );
		let step = mag;
		if ( baseStep / mag > 5 ) step = 10 * mag;
		else if ( baseStep / mag > 2 ) step = 5 * mag;
		else step = 2 * mag;

		// Ensure step is not too small
		if ( step * scale < 10 ) step = 10 / scale;

		const start = -offset / scale;
		const end = start + length / scale;

		// Find first tick
		const firstTick = Math.ceil( start / step ) * step;

		ctx.strokeStyle = '#999';
		ctx.beginPath();

		for ( let val = firstTick; val < end; val += step ) {
			// Pos in pixels
			const pos = val * scale + offset;

			// Avoid drawing off-canvas (though loop handles it)
			if ( pos < 0 || pos > length ) continue;

			const valInt = Math.round( val );
			const isMajor = valInt % ( step * 5 ) === 0 || step >= 100;

			if ( orientation === 'horizontal' ) {
				ctx.moveTo( pos, isMajor ? 0 : thickness * 0.6 );
				ctx.lineTo( pos, thickness );
				if ( isMajor ) ctx.fillText( valInt, pos + 2, thickness * 0.5 );
			} else {
				ctx.moveTo( isMajor ? 0 : thickness * 0.6, pos );
				ctx.lineTo( thickness, pos );
				if ( isMajor ) {
					ctx.save();
					ctx.translate( thickness * 0.5, pos + 2 );
					ctx.rotate( -Math.PI / 2 );
					ctx.fillText( valInt, 0, 0 );
					ctx.restore();
				}
			}
		}
		ctx.stroke();
	}, [ orientation, scale, offset, length, thickness ] );

	return (
		<canvas
			ref={ canvasRef }
			width={ orientation === 'horizontal' ? length : thickness }
			height={ orientation === 'horizontal' ? thickness : length }
			style={ {
				position: 'absolute',
				top: orientation === 'horizontal' ? 0 : thickness,
				left: orientation === 'horizontal' ? thickness : 0,
				zIndex: 5,
				pointerEvents: 'none',
				background: '#f9f9f9',
			} }
		/>
	);
};

export default Ruler;
