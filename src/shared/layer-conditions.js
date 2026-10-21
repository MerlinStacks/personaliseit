/**
 * Evaluate clipart conditions, including chained sources and broken references.
 * @param {Object} layer    Layer to evaluate.
 * @param {Array}  layers   Layers in the active design.
 * @param {Object} inputs   Customer inputs keyed by layer ID.
 * @param {Set}    visiting Ancestors used to reject circular rules.
 */
export function layerIsShown(
	layer,
	layers,
	inputs = {},
	visiting = new Set()
) {
	if ( layer.visible === false || layer.visible === 0 ) {
		return false;
	}
	const settings = layer.settings || {};
	if ( ! settings.condition_enabled ) {
		return true;
	}
	if ( visiting.has( layer ) ) {
		return false;
	}
	const sources = layers.filter(
		( candidate ) =>
			candidate.type === 'clipart' &&
			candidate.settings?.condition_key === settings.condition_source_key
	);
	if ( ! settings.condition_source_key || sources.length !== 1 ) {
		return false;
	}
	const source = sources[ 0 ];
	if (
		! layerIsShown(
			source,
			layers,
			inputs,
			new Set( visiting ).add( layer )
		)
	) {
		return false;
	}
	const sourceSettings = source.settings || {};
	// Inputs include the server-resolved catalogue fallback, even for fixed or
	// locked clipart. The saved default may no longer be available.
	const selected =
		inputs[ source.id ]?.clipartId ?? sourceSettings.default_clipart_id;
	const options = ( settings.condition_clipart_ids || [] )
		.map( Number )
		.filter( ( id ) => id > 0 );
	if ( ! options.length ) {
		return false;
	}
	const matches =
		Number( selected ) > 0 && options.includes( Number( selected ) );
	const applies =
		settings.condition_operator === 'is_not' ? ! matches : matches;
	return settings.condition_action === 'hide' ? ! applies : applies;
}
