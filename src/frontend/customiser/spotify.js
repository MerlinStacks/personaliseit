/**
 * Spotify modal, URI parsing, code URL building, and validation.
 */

/* eslint-disable no-console */

const SPOTIFY_LINK_SYNC_KEYS = [
	'value',
	'spotifyStatus',
	'spotifyUri',
	'spotifyValidationProof',
	'spotifyValidationExpires',
];

const spotifyMethods = {
	invalidateSpotifyValidation( layerId ) {
		this.clearStateTimeout( this.spotifyValidateTimers[ layerId ] );
		delete this.spotifyValidateTimers[ layerId ];
		this.spotifyValidateTokens[ layerId ] =
			( this.spotifyValidateTokens[ layerId ] || 0 ) + 1;
		this.spotifyAbortControllers[ layerId ]?.abort();
		delete this.spotifyAbortControllers[ layerId ];
		delete this.spotifyValidationPromises[ layerId ];
	},

	extractSpotifyUri( inputValue ) {
		const raw = String( inputValue || '' ).trim();
		if ( ! raw ) {
			return '';
		}
		const uriMatch = raw.match(
			/^spotify:(track|album|artist|playlist|episode|show):([A-Za-z0-9]{1,128})$/i
		);
		if ( uriMatch ) {
			return `spotify:${ uriMatch[ 1 ].toLowerCase() }:${
				uriMatch[ 2 ]
			}`;
		}

		let parsed;
		try {
			parsed = new URL( raw );
		} catch {
			return '';
		}

		const host = parsed.hostname.toLowerCase();
		if ( host !== 'open.spotify.com' && host !== 'play.spotify.com' ) {
			return '';
		}

		const parts = parsed.pathname
			.split( '/' )
			.filter( Boolean )
			.filter(
				( part ) =>
					! /^(?:intl-[a-z]{2}(?:-[a-z]{2})?|embed)$/i.test( part )
			);

		if ( ! parts.length ) {
			return '';
		}

		const validTypes = [
			'track',
			'album',
			'artist',
			'playlist',
			'episode',
			'show',
		];
		const typeIndex = parts.findIndex( ( part ) =>
			validTypes.includes( part.toLowerCase() )
		);
		if ( typeIndex < 0 || ! parts[ typeIndex + 1 ] ) {
			return '';
		}

		const id = parts[ typeIndex + 1 ];
		if ( ! /^[A-Za-z0-9]{1,128}$/.test( id ) ) {
			return '';
		}

		const type = parts[ typeIndex ].toLowerCase();
		return `spotify:${ type }:${ id }`;
	},

	buildSpotifyCodeUrl( inputValue, isEngraving, engravingPalette = null ) {
		const spotifyUri = this.extractSpotifyUri( inputValue );
		if ( ! spotifyUri ) {
			return '';
		}

		// Official Spotify scannable-code endpoint.
		// Endpoint shape:
		// /uri/plain/{format}/{background-hex}/{bar-colour}/{size}/{spotify-uri}
		// We request SVG and then strip white in-canvas for transparent compositing.
		const format = 'svg';
		const bgHex = isEngraving ? engravingPalette?.bg || 'F5F2EF' : 'FFFFFF';
		const bar = isEngraving ? 'black' : 'black';
		const size = 640;

		return `https://scannables.scdn.co/uri/plain/${ format }/${ bgHex }/${ bar }/${ size }/${ spotifyUri }`;
	},

	setupSpotifyModal() {
		const dialog = document.getElementById( 'oc-spotify-share-dialog' );
		if ( ! dialog ) {
			return;
		}

		const stateSignal = this._panelListenerController?.signal;
		dialog.setAttribute( 'role', 'dialog' );
		dialog.setAttribute( 'aria-modal', 'true' );
		document
			.querySelectorAll( '.oc-spotify-modal-trigger' )
			.forEach( ( trigger ) => {
				trigger.addEventListener(
					'click',
					( event ) => {
						event.preventDefault();
						event.stopPropagation();
						this.openSpotifyModal();
					},
					{ signal: stateSignal }
				);
			} );

		dialog
			.querySelectorAll( '[data-oc-spotify-modal-close]' )
			.forEach( ( closeBtn ) => {
				closeBtn.addEventListener(
					'click',
					() => this.closeSpotifyModal(),
					{ signal: stateSignal }
				);
			} );

		dialog.addEventListener(
			'click',
			( event ) => {
				if ( dialog.classList.contains( 'oc-dialog-fallback' ) ) {
					if (
						! dialog
							.querySelector( '.oc-sp-modal-card' )
							?.contains( event.target )
					) {
						this.closeSpotifyModal();
					}
					return;
				}

				const rect = dialog.getBoundingClientRect();
				const inDialog =
					rect.top <= event.clientY &&
					event.clientY <= rect.top + rect.height &&
					rect.left <= event.clientX &&
					event.clientX <= rect.left + rect.width;

				if ( ! inDialog ) {
					this.closeSpotifyModal();
				}
			},
			{ signal: stateSignal }
		);
		dialog.addEventListener(
			'keydown',
			( event ) => {
				if ( event.key === 'Escape' && ! dialog.showModal ) {
					event.preventDefault();
					this.closeSpotifyModal();
				}
			},
			{ signal: stateSignal }
		);

		dialog.addEventListener(
			'close',
			() => {
				dialog.classList.remove( 'is-visible' );
				document.body.style.overflow = '';
			},
			{ signal: stateSignal }
		);
	},

	openSpotifyModal() {
		const dialog = document.getElementById( 'oc-spotify-share-dialog' );
		if ( ! dialog || dialog.hasAttribute( 'open' ) ) {
			return;
		}

		this.clearStateTimeout( this.spotifyModalCloseTimer );
		if ( typeof dialog.showModal === 'function' ) {
			dialog.showModal();
		} else {
			dialog.hidden = false;
			dialog.setAttribute( 'open', '' );
			dialog.classList.add( 'oc-dialog-fallback' );
		}
		this.requestStateAnimationFrame( () => {
			this.requestStateAnimationFrame( () => {
				dialog.classList.add( 'is-visible' );
			} );
		} );
		document.body.style.overflow = 'hidden';
		dialog.querySelector( '[data-oc-spotify-modal-close]' )?.focus?.();
	},

	closeSpotifyModal() {
		const dialog = document.getElementById( 'oc-spotify-share-dialog' );
		if ( ! dialog || ! dialog.hasAttribute( 'open' ) ) {
			return;
		}

		dialog.classList.remove( 'is-visible' );
		this.clearStateTimeout( this.spotifyModalCloseTimer );
		this.spotifyModalCloseTimer = this.setStateTimeout( () => {
			if ( typeof dialog.close === 'function' && dialog.open ) {
				dialog.close();
			} else {
				dialog.removeAttribute( 'open' );
				dialog.hidden = true;
			}
			document.body.style.overflow = '';
		}, 300 );
	},

	dismissSpotifyModal() {
		this.clearStateTimeout( this.spotifyModalCloseTimer );
		this.spotifyModalCloseTimer = null;
		const dialog = document.getElementById( 'oc-spotify-share-dialog' );
		if ( dialog ) {
			dialog.classList.remove( 'is-visible' );
			if ( typeof dialog.close === 'function' && dialog.open ) {
				dialog.close();
			} else {
				dialog.removeAttribute( 'open' );
				dialog.hidden = true;
			}
		}
		document.body.style.overflow = '';
	},

	setSpotifyError( layerId, message, inputEl = null ) {
		const msg = String( message || '' );
		const el = document.querySelector(
			`[data-oc-spotify-error="${ layerId }"]`
		);
		if ( el ) {
			el.textContent = msg;
			el.style.display = msg ? '' : 'none';
		}
		if ( inputEl ) {
			inputEl.setCustomValidity( msg );
			inputEl.setAttribute( 'aria-invalid', msg ? 'true' : 'false' );
		}
	},

	clearSpotifyLayerStatus( layerId, inputEl = null ) {
		this.inputs[ layerId ].spotifyStatus = '';
		this.inputs[ layerId ].spotifyUri = '';
		this.inputs[ layerId ].spotifyValidationProof = '';
		this.inputs[ layerId ].spotifyValidationExpires = 0;
		this.syncLinkedLayerInput( layerId, SPOTIFY_LINK_SYNC_KEYS );
		this.setSpotifyError( layerId, '', inputEl );
		this.scheduleRedraw( this.areaIndexForLayer( layerId ) );
		this.updateHiddenField();
	},

	setSpotifyValidationResult(
		layerId,
		status,
		uri,
		message,
		inputEl = null,
		proof = '',
		expires = 0
	) {
		this.inputs[ layerId ].spotifyStatus = status;
		this.inputs[ layerId ].spotifyUri = uri;
		this.inputs[ layerId ].spotifyValidationProof = proof;
		this.inputs[ layerId ].spotifyValidationExpires = expires;
		this.syncLinkedLayerInput( layerId, SPOTIFY_LINK_SYNC_KEYS );
		this.setSpotifyError( layerId, message, inputEl );
		this.scheduleRedraw( this.areaIndexForLayer( layerId ) );
		this.updateHiddenField();
	},

	validateSpotifyLayer( layerId, rawValue, inputEl = null, force = false ) {
		const value = String( rawValue || '' ).trim();
		if ( ! this.inputs[ layerId ] ) {
			this.inputs[ layerId ] = {};
		}
		this.clearStateTimeout( this.spotifyValidateTimers[ layerId ] );
		delete this.spotifyValidateTimers[ layerId ];
		const existing = this.spotifyValidationPromises[ layerId ];
		if ( ! force && existing?.value === value ) {
			return existing.promise;
		}
		this.invalidateSpotifyValidation( layerId );
		const token = this.spotifyValidateTokens[ layerId ];
		const designGeneration = this._designGeneration;
		const input = this.inputs[ layerId ];
		input.value = value;
		const localUri = this.extractSpotifyUri( value );
		const isCurrent = () =>
			this.spotifyValidateTokens[ layerId ] === token &&
			this._designGeneration === designGeneration &&
			this.inputs[ layerId ] === input &&
			String( input.value || '' ).trim() === value &&
			( ! inputEl || String( inputEl.value || '' ).trim() === value );

		const promise = ( async () => {
			if ( ! value ) {
				this.clearSpotifyLayerStatus( layerId, inputEl );
				return true;
			}
			if ( ! localUri ) {
				this.setSpotifyValidationResult(
					layerId,
					'invalid_format',
					'',
					'Invalid Spotify link format.',
					inputEl
				);
				return false;
			}

			this.setSpotifyValidationResult(
				layerId,
				'pending',
				localUri,
				'',
				inputEl
			);
			if ( ! this.data.validateSpotifyUrl ) {
				this.setSpotifyValidationResult(
					layerId,
					'ok',
					localUri,
					'',
					inputEl
				);
				return true;
			}

			const request = this.createStateAbortController( 12000 );
			const controller = request.controller;
			this.spotifyAbortControllers[ layerId ] = controller;
			try {
				const res = await fetch( this.data.validateSpotifyUrl, {
					method: 'POST',
					headers: this.restHeaders( {
						'Content-Type': 'application/json',
					} ),
					body: JSON.stringify( { url: localUri } ),
					signal: controller.signal,
				} );
				const isJson = res.headers
					.get( 'content-type' )
					?.includes( 'application/json' );
				let json = null;
				let text = '';
				if ( isJson ) {
					try {
						json = await res.json();
					} catch ( err ) {
						console.warn(
							'[OC] Spotify validation JSON parse failed:',
							err
						);
					}
				} else {
					text = await res.text();
				}
				if ( ! isCurrent() ) {
					return false;
				}
				if ( ! res.ok ) {
					const statusReason =
						json?.code === 'rate_limited' || res.status === 429
							? 'rate_limited'
							: 'unreachable';
					const statusMessage =
						json?.message ||
						text ||
						'Could not validate Spotify right now. Please try again.';
					this.setSpotifyValidationResult(
						layerId,
						statusReason,
						'',
						statusMessage,
						inputEl
					);
					return false;
				}

				if ( ! json ) {
					this.setSpotifyValidationResult(
						layerId,
						'unreachable',
						'',
						'Could not validate Spotify right now. Please try again.',
						inputEl
					);
					return false;
				}

				const canonicalUri = this.extractSpotifyUri(
					json?.spotifyUri || localUri
				);
				if ( json?.valid === true && canonicalUri ) {
					this.setSpotifyValidationResult(
						layerId,
						'ok',
						canonicalUri,
						'',
						inputEl,
						String( json.validationProof || '' ),
						Number( json.validationExpires || 0 )
					);
					return true;
				}
				this.setSpotifyValidationResult(
					layerId,
					json?.reason || 'invalid_or_unavailable',
					'',
					json?.message || 'Spotify link is invalid or unavailable.',
					inputEl
				);
				return false;
			} catch {
				if ( ! isCurrent() ) {
					return false;
				}
				this.setSpotifyValidationResult(
					layerId,
					'unreachable',
					'',
					request.timedOut()
						? 'Spotify validation timed out. Please try again.'
						: 'Could not validate Spotify right now. Please try again.',
					inputEl
				);
				return false;
			} finally {
				request.release();
				if ( this.spotifyAbortControllers[ layerId ] === controller ) {
					delete this.spotifyAbortControllers[ layerId ];
				}
			}
		} )();
		this.spotifyValidationPromises[ layerId ] = { value, promise };
		return promise;
	},
};

export default spotifyMethods;
