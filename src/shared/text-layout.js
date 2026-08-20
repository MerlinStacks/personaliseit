export function multilineTextSafetyMargin( fontSize ) {
	const size = Number( fontSize ) || 0;

	return {
		x: Math.max( 1, Math.ceil( size * 0.06 ) ),
		y: Math.max( 2, Math.ceil( size * 0.12 ) ),
	};
}

export function layoutMultilineTextbox( textbox, maxWidth, fontSize ) {
	const outerWidth = Math.max( 1, Number( maxWidth ) || 0 );
	const margin = multilineTextSafetyMargin( fontSize );
	const contentWidth = Math.max( 1, outerWidth - margin.x * 2 );
	const setLayout = ( splitByGrapheme ) => {
		textbox.set?.( { width: contentWidth, splitByGrapheme } );
		textbox.initDimensions?.();
	};

	// Preserve normal word wrapping where possible. Fabric expands a Textbox to
	// its longest unbroken word, so retry with grapheme wrapping only when that
	// expansion would push letters outside the configured text area.
	setLayout( false );
	if ( Number( textbox.width || 0 ) > contentWidth + 0.01 ) {
		setLayout( true );
	}

	return {
		contentWidth,
		margin,
		width: Number( textbox.getScaledWidth?.() || textbox.width || 0 ),
		height: Number( textbox.getScaledHeight?.() || textbox.height || 0 ),
		splitByGrapheme: Boolean( textbox.splitByGrapheme ),
	};
}

export function multilineTextboxFits( textbox, maxWidth, maxHeight, fontSize ) {
	const layout = layoutMultilineTextbox( textbox, maxWidth, fontSize );

	return (
		layout.width + layout.margin.x * 2 <=
			Math.max( 1, Number( maxWidth ) || 0 ) + 0.01 &&
		layout.height + layout.margin.y * 2 <=
			Math.max( 1, Number( maxHeight ) || 0 ) + 0.01
	);
}
