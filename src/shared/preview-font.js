/* global FontFace */
// This is byte-identical to the bundled DejaVu font used by print outlines.
import fallbackUrl from '../../assets/fonts/oc-preview-fallback.ttf';

export const PREVIEW_FALLBACK_FAMILY = 'OC Preview Fallback';
const fallbackReady = new Map();

export function previewFontFamily( font ) {
	const primary = font?.name;
	return primary
		? `${ JSON.stringify( primary ) }, "${ PREVIEW_FALLBACK_FAMILY }"`
		: 'sans-serif';
}

export function loadPreviewFallback( font ) {
	const descriptors = {
		weight: font?.weight || 'normal',
		style: font?.style || 'normal',
	};
	const key = JSON.stringify( descriptors );
	if ( ! fallbackReady.has( key ) ) {
		// Match the requested face to prevent browser-synthesised bold/italic
		// on fallback glyphs: production uses these exact regular outlines.
		const face = new FontFace(
			PREVIEW_FALLBACK_FAMILY,
			`url('${ fallbackUrl }')`,
			descriptors
		);
		fallbackReady.set(
			key,
			face
				.load()
				.then( ( loaded ) => {
					document.fonts.add( loaded );
					return true;
				} )
				.catch( ( error ) => {
					fallbackReady.delete( key );
					throw error;
				} )
		);
	}
	return fallbackReady.get( key );
}
