const cartSerializationMethods = {
	cloneSubmissionInputs() {
		return JSON.parse( JSON.stringify( this.inputs || {} ) );
	},

	freezeSubmissionValue( value ) {
		if (
			! value ||
			typeof value !== 'object' ||
			Object.isFrozen( value )
		) {
			return value;
		}
		Object.values( value ).forEach( ( child ) =>
			this.freezeSubmissionValue( child )
		);
		return Object.freeze( value );
	},

	serialiseLayers( inputs ) {
		const layers = {};
		this.areas.forEach( ( area ) => {
			( area.layers || [] ).forEach( ( layer ) => {
				const input = { ...( inputs[ layer.id ] || {} ) };
				if ( [ 'text', 'textarea' ].includes( layer.type ) ) {
					if ( layer.locked ) {
						input.value = layer.settings?.default_text || '';
					} else if ( input.value !== undefined ) {
						input.value = this.normaliseLayerTextValue(
							layer.id,
							input.value
						);
					}
				}

				if ( [ 'image', 'clipmask' ].includes( layer.type ) ) {
					const canonicalId = this.canonicalLinkedLayerId( layer.id );
					const canonicalInput = inputs[ canonicalId ] || {};
					const sameAttachment =
						canonicalId !== layer.id &&
						layer.settings?.allow_image_change !== false &&
						( ( Number( input.attachmentId || 0 ) > 0 &&
							Number( input.attachmentId ) ===
								Number( canonicalInput.attachmentId || 0 ) ) ||
							( input.attachmentUrl &&
								input.attachmentUrl ===
									canonicalInput.attachmentUrl ) );
					if ( sameAttachment ) {
						[
							'attachmentId',
							'attachmentUrl',
							'sourceAttachmentId',
							'sourceAttachmentUrl',
							'originalAttachmentUrl',
							'sourceOriginalAttachmentUrl',
							'artworkFileType',
							'sourceArtworkFileType',
							'previewAttachmentId',
							'imageMeta',
							'sourceImageMeta',
						].forEach( ( key ) => delete input[ key ] );
						input.linkedSourceLayerId = canonicalId;
					}
					if ( canonicalId === layer.id ) {
						const linkedIds = this.linkedLayerIds( layer.id );
						if ( linkedIds.length ) {
							input.linkedLayerIds = linkedIds;
						}
					}
				}

				layers[ layer.id ] = { type: layer.type, ...input };
			} );
		} );
		return layers;
	},

	createSubmissionGeneration() {
		const inputs = this.cloneSubmissionInputs();
		this.areas.forEach( ( area ) => {
			( area.layers || [] ).forEach( ( layer ) => {
				if ( ! inputs[ layer.id ] ) {
					inputs[ layer.id ] = {};
				}
				if (
					layer.locked &&
					[ 'text', 'textarea' ].includes( layer.type )
				) {
					inputs[ layer.id ].value =
						layer.settings?.default_text || '';
				}
			} );
		} );
		const generation = {
			designGeneration: this._designGeneration,
			designId: this.data.designId,
			designVariant: this.selectedDesignVariant || '',
			designVariantLabel:
				this.designVariants.find(
					( item ) => item.id === this.selectedDesignVariant
				)?.label || '',
			layers: this.serialiseLayers( inputs ),
		};
		return this.freezeSubmissionValue( generation );
	},

	buildCustomisationPayload(
		generation,
		{ previewUrl = '', previewImage = '' } = {}
	) {
		const payload = {
			v: 2,
			designId: generation.designId,
			layers: generation.layers,
			uploadToken: this.data.requestToken || '',
		};
		if ( generation.designVariant ) {
			payload.designVariant = generation.designVariant;
			if ( generation.designVariantLabel ) {
				payload.designVariantLabel = generation.designVariantLabel;
			}
		}
		if ( previewImage ) {
			payload.previewImage = previewImage;
		} else if ( previewUrl ) {
			payload.previewUrl = previewUrl;
		}
		return payload;
	},

	updateHiddenField( options = {} ) {
		const el = document.getElementById( 'oc-customisation-data' );
		if ( ! el ) {
			return;
		}
		if ( ! this._customisationActive ) {
			el.value = '';
			el.disabled = true;
			return;
		}
		el.disabled = false;
		const generation =
			options.generation || this.createSubmissionGeneration();
		const payload = this.buildCustomisationPayload( generation, {
			previewUrl: options.previewUrl || '',
			previewImage: options.previewImage || '',
		} );
		el.value = JSON.stringify( payload );
		return payload;
	},
};

export default cartSerializationMethods;
