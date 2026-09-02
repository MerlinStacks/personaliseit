/**
 * Return whether an editor layer can be used with a print method.
 *
 * @param {string} type        Layer type.
 * @param {string} printMethod Print method key.
 */
export function layerTypeSupportsPrintMethod( type, printMethod ) {
	return ! ( type === 'night_sky' && printMethod === 'embroidery' );
}

/**
 * Return whether an AI Image layer has a usable private instruction.
 *
 * @param {*} value Instruction value.
 */
export function aiImageInstructionIsValid( value ) {
	if ( typeof value !== 'string' || value.trim() === '' ) {
		return false;
	}

	return new TextEncoder().encode( value.trim() ).length <= 16384;
}
