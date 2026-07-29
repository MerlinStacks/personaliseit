export const LAYER_TYPES = {
	text: { label: 'Text', icon: 'Aa', color: '#0284c7' },
	textarea: { label: 'Text Area', icon: '\u00b6', color: '#7c3aed' },
	image: { label: 'Image', icon: '\ud83d\uddbc', color: '#059669' },
	clipmask: { label: 'Clipping Mask', icon: '◯', color: '#0d9488' },
	mask: { label: 'Mask', icon: '\u25a0', color: '#64748b' },
	spotify: { label: 'Spotify Code', icon: '\u266b', color: '#1db954' },
	lineart: { label: 'Line Art', icon: '\u270f', color: '#d97706' },
	clipart: { label: 'Clipart', icon: '\u2726', color: '#dc2626' },
};

export const LAYER_DEFAULTS = {
	text: { w: 300, h: 50 },
	textarea: { w: 300, h: 120 },
	image: { w: 200, h: 200 },
	clipmask: { w: 200, h: 200 },
	spotify: { w: 150, h: 150 },
	lineart: { w: 200, h: 200 },
	clipart: { w: 150, h: 150 },
};

export function layerIcon( type ) {
	return ( LAYER_TYPES[ type ] || {} ).icon || '?';
}

export function layerColor( type ) {
	return ( LAYER_TYPES[ type ] || {} ).color || '#9ca3af';
}

export function layerLabel( type ) {
	return ( LAYER_TYPES[ type ] || {} ).label || type;
}

export const ICO_EYE =
	'<svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M1 8c0 0 2.5-5 7-5s7 5 7 5-2.5 5-7 5-7-5-7-5z"/><circle cx="8" cy="8" r="2"/></svg>';
export const ICO_EYE_OFF =
	'<svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M1 8c0 0 2.5-5 7-5s7 5 7 5-2.5 5-7 5-7-5-7-5z"/><circle cx="8" cy="8" r="2"/><line x1="2" y1="14" x2="14" y2="2"/></svg>';
export const ICO_LOCK =
	'<svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="7" width="10" height="8" rx="1.5"/><path d="M5.5 7V5a2.5 2.5 0 0 1 5 0v2"/></svg>';
export const ICO_UNLOCK =
	'<svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="7" width="10" height="8" rx="1.5"/><path d="M5.5 7V5a2.5 2.5 0 0 1 5 0"/></svg>';
export const ICO_BIN =
	'<svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><line x1="1.5" y1="4" x2="14.5" y2="4"/><path d="M6 4V2.5a.5.5 0 0 1 .5-.5h3a.5.5 0 0 1 .5.5V4"/><path d="M3 4l.8 9.5a.5.5 0 0 0 .5.5h7.4a.5.5 0 0 0 .5-.5L13 4"/></svg>';

export const LAYER_TABS = {
	text: [
		{ id: 'general', label: 'General', icon: 'G' },
		{ id: 'content', label: 'Content', icon: 'T' },
		{ id: 'style', label: 'Style', icon: 'A' },
		{ id: 'properties', label: 'Properties', icon: '\u2699' },
	],
	textarea: [
		{ id: 'general', label: 'General', icon: 'G' },
		{ id: 'content', label: 'Content', icon: 'T' },
		{ id: 'style', label: 'Style', icon: 'A' },
		{ id: 'properties', label: 'Properties', icon: '\u2699' },
	],
	image: [
		{ id: 'general', label: 'General', icon: 'G' },
		{ id: 'file', label: 'File', icon: '\ud83d\uddbc' },
		{ id: 'colours', label: 'Colours', icon: '\u25cf' },
		{ id: 'validation', label: 'Validation', icon: '\u2713' },
		{ id: 'properties', label: 'Properties', icon: '\u2699' },
	],
	clipmask: [
		{ id: 'general', label: 'General', icon: 'G' },
		{ id: 'file', label: 'File', icon: '\ud83d\uddbc' },
		{ id: 'mask', label: 'Mask', icon: '◯' },
		{ id: 'validation', label: 'Validation', icon: '\u2713' },
	],
	spotify: [
		{ id: 'general', label: 'General', icon: 'G' },
		{ id: 'appearance', label: 'Appearance', icon: '\u25d0' },
		{ id: 'validation', label: 'Validation', icon: '\u2713' },
	],
	lineart: [
		{ id: 'general', label: 'General', icon: 'G' },
		{ id: 'colours', label: 'Colours', icon: '\u25cf' },
		{ id: 'validation', label: 'Validation', icon: '\u2713' },
	],
	clipart: [
		{ id: 'general', label: 'General', icon: 'G' },
		{ id: 'library', label: 'Library', icon: '\u2726' },
		{ id: 'validation', label: 'Validation', icon: '\u2713' },
		{ id: 'properties', label: 'Properties', icon: '\u2699' },
	],
};

export const AREA_COLORS = [
	'#4f46e5',
	'#059669',
	'#d97706',
	'#dc2626',
	'#0284c7',
	'#7c3aed',
	'#db2777',
	'#ea580c',
];

export function areaColor( index ) {
	return AREA_COLORS[ index % AREA_COLORS.length ];
}
