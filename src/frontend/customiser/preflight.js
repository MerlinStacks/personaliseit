/* eslint-disable no-undef */

/**
 * Preflight validation and validation message rendering.
 */

const INVALID_SPOTIFY_STATUSES = [
	'invalid_format',
	'playlist_private_or_invalid',
	'invalid_or_unavailable',
	'unreachable',
	'rate_limited',
];

const preflightMethods = {
	getLayerInputEl( layer ) {
		if ( ! layer?.id ) {
			return null;
		}
		switch ( layer.type ) {
			case 'text':
			case 'textarea':
				return document.querySelector(
					`[data-oc-layer-text="${ layer.id }"]`
				);
			case 'spotify':
				return document.querySelector(
					`[data-oc-layer-spotify="${ layer.id }"]`
				);
			case 'image':
			case 'clipmask':
				return document.querySelector(
					`[data-oc-upload-zone="${ layer.id }"]`
				);
			case 'clipart':
				return document.querySelector(
					`[data-oc-layer-clipart="${ layer.id }"]`
				);
			default:
				return null;
		}
	},

	clearPreflightMessages() {
		if ( this.preflightRoot ) {
			this.preflightRoot.innerHTML = '';
			this.preflightRoot.hidden = true;
		}

		document
			.querySelectorAll( '.oc-preflight-field-error' )
			.forEach( ( el ) => {
				el.classList.remove( 'oc-preflight-field-error' );
			} );

		document
			.querySelectorAll( '[data-oc-layer-text], [data-oc-layer-spotify]' )
			.forEach( ( el ) => {
				el.setCustomValidity( '' );
				el.setAttribute( 'aria-invalid', 'false' );
			} );
	},

	renderPreflightMessages( errors = [], warnings = [] ) {
		if ( ! this.preflightRoot ) {
			return;
		}

		if ( ! errors.length && ! warnings.length ) {
			this.clearPreflightMessages();
			return;
		}

		const box = document.createElement( 'div' );
		box.className = 'oc-preflight-box';
		box.setAttribute( 'role', 'alert' );
		box.setAttribute( 'aria-live', 'assertive' );

		const appendTitle = ( text ) => {
			const title = document.createElement( 'p' );
			title.className = 'oc-preflight-title';
			title.textContent = text;
			box.appendChild( title );
		};

		const appendList = ( items, cls ) => {
			if ( ! items.length ) {
				return;
			}
			const list = document.createElement( 'ul' );
			list.className = cls;
			items.forEach( ( msg ) => {
				const item = document.createElement( 'li' );
				item.textContent = String( msg );
				list.appendChild( item );
			} );
			box.appendChild( list );
		};

		this.preflightRoot.innerHTML = '';
		if ( errors.length ) {
			appendTitle( 'Please fix these issues before checkout:' );
			appendList( errors, 'oc-preflight-errors' );
		}
		if ( warnings.length ) {
			appendTitle( 'Quality warnings:' );
			appendList( warnings, 'oc-preflight-warnings' );
		}
		this.preflightRoot.appendChild( box );

		this.preflightRoot.hidden = false;
		this.preflightRoot.scrollIntoView( {
			behavior: 'smooth',
			block: 'start',
		} );
	},

	async getImageMeta( url ) {
		if ( ! url ) {
			return null;
		}

		return new Promise( ( resolve ) => {
			const img = new Image();
			img.onload = () =>
				resolve( {
					width: img.naturalWidth || 0,
					height: img.naturalHeight || 0,
				} );
			img.onerror = () => resolve( null );
			img.src = url;
		} );
	},

	async runPreflight() {
		this.clearPreflightMessages();

		const errors = [];
		const warnings = [];
		const spotifyValidated = new Set();

		for ( const area of this.areas ) {
			for ( const layer of area.layers || [] ) {
				if ( layer.locked ) {
					continue;
				}
				const input = this.inputs[ layer.id ] || {};
				const settings = layer.settings || {};
				const required = Boolean( settings.required );
				const label = layer.label || layer.type;
				const fieldEl = this.getLayerInputEl( layer );
				let value = '';

				switch ( layer.type ) {
					case 'text':
					case 'textarea':
						value = String( input.value || '' ).trim();
						if ( required && ! value ) {
							errors.push( `${ label } is required.` );
							fieldEl?.classList.add(
								'oc-preflight-field-error'
							);
							if ( fieldEl ) {
								fieldEl.setCustomValidity(
									'This field is required.'
								);
								fieldEl.setAttribute( 'aria-invalid', 'true' );
							}
						}
						if ( value ) {
							const charLimit =
								parseInt( settings.char_limit, 10 ) || 0;
							if (
								charLimit > 0 &&
								this.textLength( value ) > charLimit
							) {
								errors.push(
									`${ label } exceeds the ${ charLimit } character limit.`
								);
								fieldEl?.classList.add(
									'oc-preflight-field-error'
								);
								if ( fieldEl ) {
									fieldEl.setCustomValidity(
										`Maximum ${ charLimit } characters.`
									);
									fieldEl.setAttribute(
										'aria-invalid',
										'true'
									);
								}
							}
						}
						break;

					case 'image':
					case 'clipmask':
						if ( required && ! input.attachmentId ) {
							errors.push(
								`${ label } needs an uploaded image.`
							);
							fieldEl?.classList.add(
								'oc-preflight-field-error'
							);
						}
						if ( input.attachmentUrl ) {
							let imageMeta = input.imageMeta || null;
							if ( ! imageMeta ) {
								imageMeta = await this.getImageMeta(
									input.attachmentUrl
								);
								if ( imageMeta && this.inputs[ layer.id ] ) {
									this.inputs[ layer.id ].imageMeta =
										imageMeta;
								}
							}
							if (
								imageMeta &&
								imageMeta.width > 0 &&
								imageMeta.height > 0 &&
								( imageMeta.width < layer.w ||
									imageMeta.height < layer.h )
							) {
								warnings.push(
									`${ label } may print soft (${ imageMeta.width }x${ imageMeta.height }px for a ${ layer.w }x${ layer.h }px print area).`
								);
							}
						}
						break;

					case 'clipart':
						if ( required && ! input.clipartId ) {
							errors.push(
								`${ label } requires a clipart selection.`
							);
							fieldEl?.classList.add(
								'oc-preflight-field-error'
							);
						}
						break;

					case 'lineart':
						value = String( input.colorHex || '' ).trim();
						if ( required && ! value ) {
							errors.push(
								`${ label } requires a line-art colour.`
							);
							fieldEl?.classList.add(
								'oc-preflight-field-error'
							);
							if ( fieldEl ) {
								fieldEl.setCustomValidity(
									'Please choose a line-art colour.'
								);
								fieldEl.setAttribute( 'aria-invalid', 'true' );
							}
						}
						break;

					case 'spotify':
						value = String( input.value || '' ).trim();
						if ( required && ! value ) {
							errors.push(
								`${ label } requires a Spotify link.`
							);
							fieldEl?.classList.add(
								'oc-preflight-field-error'
							);
							if ( fieldEl ) {
								fieldEl.setCustomValidity(
									'Please provide a Spotify link.'
								);
								fieldEl.setAttribute( 'aria-invalid', 'true' );
							}
							break;
						}

						if ( value && ! spotifyValidated.has( layer.id ) ) {
							await this.validateSpotifyLayer(
								layer.id,
								value,
								fieldEl
							);
							spotifyValidated.add( layer.id );
						}

						if ( value ) {
							const status = String(
								this.inputs[ layer.id ]?.spotifyStatus || ''
							);
							if ( INVALID_SPOTIFY_STATUSES.includes( status ) ) {
								errors.push(
									`${ label } has an invalid or unavailable Spotify link.`
								);
								fieldEl?.classList.add(
									'oc-preflight-field-error'
								);
								if ( fieldEl ) {
									fieldEl.setCustomValidity(
										'Spotify link is invalid or unavailable.'
									);
									fieldEl.setAttribute(
										'aria-invalid',
										'true'
									);
								}
							}
						}
						break;
				}
			}
		}

		return { errors, warnings, ok: errors.length === 0 };
	},

	runImmediateBlockingPreflight() {
		this.clearPreflightMessages();

		const errors = [];

		for ( const area of this.areas ) {
			for ( const layer of area.layers || [] ) {
				if ( layer.locked ) {
					continue;
				}

				const input = this.inputs[ layer.id ] || {};
				const settings = layer.settings || {};
				const label = layer.label || layer.type;
				const fieldEl = this.getLayerInputEl( layer );

				if ( ! settings.required ) {
					continue;
				}

				let filled = true;
				switch ( layer.type ) {
					case 'text':
					case 'textarea':
					case 'spotify':
						filled = String( input.value || '' ).trim() !== '';
						break;

					case 'image':
					case 'clipmask':
						filled = Boolean( input.attachmentId );
						break;

					case 'clipart':
						filled = Boolean( input.clipartId );
						break;

					default:
						filled = true;
				}

				if ( ! filled ) {
					errors.push( `${ label } is required.` );
					fieldEl?.classList.add( 'oc-preflight-field-error' );
					if ( fieldEl ) {
						fieldEl.setCustomValidity( 'This field is required.' );
						fieldEl.setAttribute( 'aria-invalid', 'true' );
					}
				}
			}
		}

		return { errors, warnings: [], ok: errors.length === 0 };
	},
};

export default preflightMethods;
