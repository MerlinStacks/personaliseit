import { layerIsShown } from '../../shared/layer-conditions';

const cartSerializationMethods = {
	syncConditionalLayers() {
		const layers = this.areas.flatMap( ( area ) => area.layers || [] );
		layers.forEach( ( layer ) => {
			const shown = layerIsShown( layer, layers, this.inputs );
			this._conditionVisibility ||= {};
			const changed = this._conditionVisibility[ layer.id ] !== shown;
			this._conditionVisibility[ layer.id ] = shown;
			if ( changed && layer.settings?.condition_enabled ) {
				this.clearPreflightMessages?.();
				this.scheduleRedraw?.( this.areaIndexForLayer( layer.id ) );
			}
			const section = document.querySelector(
				`[data-oc-condition-layer="${ layer.id }"]`
			);
			if ( ! section ) {
				return;
			}
			// The template renders one control for the entire linked group. Keep
			// that control usable while any member still needs customer input.
			const memberIds = this.linkedLayerMembers?.( layer.id ) || [];
			const members = layers.filter( ( candidate ) =>
				memberIds.includes( candidate.id )
			);
			const shownMembers = members.filter( ( candidate ) =>
				layerIsShown( candidate, layers, this.inputs )
			);
			const controlShown = shown || shownMembers.length > 0;
			section.hidden = ! controlShown;
			section.style.display = controlShown ? '' : 'none';
			section
				.querySelectorAll( 'input, select, textarea, button' )
				.forEach( ( control ) => {
					if ( members.length > 1 && 'required' in control ) {
						if (
							control.dataset.ocConditionRequired === undefined
						) {
							control.dataset.ocConditionRequired =
								control.required ? '1' : '0';
						}
						if ( control.dataset.ocConditionRequired === '1' ) {
							control.required = shownMembers.some(
								( member ) => member.settings?.required
							);
							control.setAttribute(
								'aria-required',
								String( control.required )
							);
						}
					}
					if ( ! controlShown || ! shown ) {
						control.setCustomValidity?.( '' );
						control.classList.remove( 'oc-preflight-field-error' );
						control.removeAttribute( 'aria-invalid' );
					}
					if ( ! controlShown ) {
						if (
							! control.disabled ||
							control.dataset.ocLockDisabled === '0'
						) {
							control.dataset.ocConditionDisabled = '1';
							control.disabled = true;
						}
						if ( control.dataset.ocLockDisabled !== undefined ) {
							control.dataset.ocLockDisabled = '1';
						}
					} else if ( control.dataset.ocConditionDisabled ) {
						if ( control.dataset.ocLockDisabled !== undefined ) {
							control.dataset.ocLockDisabled = '0';
						} else {
							control.disabled = false;
						}
						delete control.dataset.ocConditionDisabled;
					}
				} );
		} );
	},
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
				if (
					[ 'mask', 'laser_material' ].includes( layer.type ) ||
					! layerIsShown(
						layer,
						this.areas.flatMap( ( entry ) => entry.layers || [] ),
						inputs
					)
				) {
					return;
				}
				const input = { ...( inputs[ layer.id ] || {} ) };
				if ( layer.type === 'staff_note' ) {
					layers[ layer.id ] = {
						type: 'staff_note',
						value: String( input.value || '' ),
					};
					return;
				}
				if (
					[ 'text', 'curved_text', 'textarea' ].includes( layer.type )
				) {
					if ( layer.locked ) {
						input.value = layer.settings?.default_text || '';
					} else if ( input.value !== undefined ) {
						input.value = this.normaliseLayerTextValue(
							layer.id,
							input.value
						);
					}
				}

				if (
					[ 'image', 'ai_image', 'clipmask' ].includes( layer.type )
				) {
					[
						'imageFilterResults',
						'imageFilterAttemptCount',
						'baseAttachmentId',
						'baseAttachmentUrl',
						'baseOriginalAttachmentUrl',
						'baseArtworkFileType',
						'basePreviewAttachmentId',
						'baseImageMeta',
					].forEach( ( key ) => delete input[ key ] );
					delete input.aiDescription;
					delete input.aiImageResults;
					delete input.aiImageAttemptCount;
					const canonicalId = this.canonicalLinkedLayerId( layer.id );
					const linkedMembers = this.linkedLayerMembers( layer.id );
					if ( linkedMembers.length > 1 ) {
						input.linkedSourceLayerId = canonicalId;
					}
					if ( canonicalId === layer.id ) {
						const linkedIds = linkedMembers.filter(
							( layerId ) =>
								Number( layerId ) !== Number( canonicalId )
						);
						if ( linkedIds.length ) {
							input.linkedLayerIds = linkedIds;
						}
					}
				}

				if ( layer.type === 'spotify' ) {
					const spotifyUri = this.extractSpotifyUri(
						input.spotifyUri || input.value
					);
					if ( spotifyUri ) {
						input.value = spotifyUri;
						input.spotifyUri = spotifyUri;
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
					[ 'text', 'curved_text', 'textarea' ].includes( layer.type )
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
			materials: { ...( this.materialSelections || {} ) },
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
			materials: generation.materials || {},
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
		this.syncConditionalLayers();
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
