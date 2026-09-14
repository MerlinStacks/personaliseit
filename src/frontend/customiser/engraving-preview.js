import { createEngravingBackgroundSampler } from './engraving-background';

// Loaded only for engraving. Samplers belong to the loaded mockup canvas, not
// the shared module, so grouped areas reuse pixels and variations cannot leak them.
export function engravingPreviewPalette(
	material,
	canvas,
	center,
	box,
	rotation
) {
	let brightness = null;
	if (
		( ! material || material === 'silver_metal' ) &&
		canvas?._ocEngravingMockup
	) {
		canvas._ocEngravingBackground ??= createEngravingBackgroundSampler(
			...canvas._ocEngravingMockup
		);
		brightness = canvas._ocEngravingBackground( center, box, rotation );
	}
	return engravingPalette( material, brightness );
}

export function engravingPalette(
	material = 'silver_metal',
	backgroundBrightness = null
) {
	const palettes = {
		glass: {
			text: '#eef4f4',
			imageTint: '#eef4f4',
			bg: 'F7FAFA',
			highlight: 'rgba(255,255,255,0.7)',
			brightness: 0.16,
			contrast: -0.04,
			opacity: 0.62,
		},
		gold_metal: {
			text: '#6f5227',
			imageTint: '#6f5227',
			bg: 'D9A72E',
			highlight: 'rgba(255,238,176,0.34)',
			brightness: -0.18,
			contrast: 0.22,
			opacity: 0.88,
		},
		silver_metal: {
			text: '#c9c9c3',
			imageTint: '#c9c9c3',
			bg: 'ECEFF1',
			highlight: 'rgba(255,255,255,0.42)',
			brightness: -0.28,
			contrast: 0.18,
			opacity: 0.9,
		},
		silver_plaque: {
			text: '#17191b',
			imageTint: '#111315',
			bg: 'ECEFF1',
			highlight: 'rgba(255,255,255,0.08)',
			brightness: -0.08,
			contrast: 0.34,
			opacity: 0.96,
			tintAlpha: 0.9,
			composite: 'multiply',
			photoDither: true,
		},
		black_metal: {
			text: '#d8d8d8',
			imageTint: '#d8d8d8',
			bg: '1F2328',
			highlight: 'rgba(255,255,255,0.24)',
			shadow: 'rgba(0,0,0,0.42)',
			brightness: -0.34,
			contrast: 0.28,
			opacity: 0.95,
		},
		wood: {
			text: 'rgba(78,42,20,0.7)',
			imageTint: '#5d3922',
			bg: 'C8A06B',
			highlight: 'rgba(255,225,180,0.16)',
			brightness: -0.16,
			contrast: 0.2,
			opacity: 0.72,
			tintAlpha: 0.72,
			composite: 'multiply',
			pattern: 'wood',
		},
		leather: {
			text: 'rgba(66,35,21,0.86)',
			imageTint: '#4a2919',
			bg: 'A66F45',
			highlight: 'rgba(235,190,140,0.18)',
			brightness: -0.2,
			contrast: 0.24,
			opacity: 0.84,
			tintAlpha: 0.82,
			composite: 'multiply',
			pattern: 'leather',
			noise: 5,
		},
	};

	const palette = palettes[ material ] || palettes.silver_metal;
	if (
		palette === palettes.silver_metal &&
		Number.isFinite( backgroundBrightness ) &&
		backgroundBrightness >= 0.6
	) {
		// A deeper neutral silver retains the metallic highlight on pale items.
		return { ...palette, text: '#747873', imageTint: '#747873' };
	}
	return palette;
}
