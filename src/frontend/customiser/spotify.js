/**
 * Spotify modal, URI parsing, code URL building, and validation.
 */

/* eslint-disable no-console, no-undef */

const SPOTIFY_LINK_SYNC_KEYS = [ 'value', 'spotifyStatus', 'spotifyUri' ];

const spotifyMethods = {
	extractSpotifyUri( inputValue ) {
		const raw = String( inputValue || '' ).trim();
		if ( ! raw ) {
			return '';
		}
		if ( /^spotify:[a-z]+:[A-Za-z0-9]+$/i.test( raw ) ) {
			return raw;
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
			.filter( ( p ) => ! /^intl-[a-z]{2}$/i.test( p ) );

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
		const typeIndex = parts.findIndex( ( p ) => validTypes.includes( p ) );
		if ( typeIndex < 0 || ! parts[ typeIndex + 1 ] ) {
			return '';
		}

		const type = parts[ typeIndex ];
		const id = parts[ typeIndex + 1 ];
		if ( ! /^[A-Za-z0-9]+$/.test( id ) ) {
			return '';
		}

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

		document
			.querySelectorAll( '.oc-spotify-modal-trigger' )
			.forEach( ( trigger ) => {
				trigger.addEventListener( 'click', ( event ) => {
					event.preventDefault();
					event.stopPropagation();
					this.openSpotifyModal();
				} );
			} );

		dialog
			.querySelectorAll( '[data-oc-spotify-modal-close]' )
			.forEach( ( closeBtn ) => {
				closeBtn.addEventListener( 'click', () =>
					this.closeSpotifyModal()
				);
			} );

		dialog.addEventListener( 'click', ( event ) => {
			const rect = dialog.getBoundingClientRect();
			const inDialog =
				rect.top <= event.clientY &&
				event.clientY <= rect.top + rect.height &&
				rect.left <= event.clientX &&
				event.clientX <= rect.left + rect.width;

			if ( ! inDialog ) {
				this.closeSpotifyModal();
			}
		} );

		dialog.addEventListener( 'close', () => {
			dialog.classList.remove( 'is-visible' );
			document.body.style.overflow = '';
		} );
	},

	openSpotifyModal() {
		const dialog = document.getElementById( 'oc-spotify-share-dialog' );
		if ( ! dialog || dialog.open ) {
			return;
		}

		clearTimeout( this.spotifyModalCloseTimer );
		dialog.showModal();
		requestAnimationFrame( () => {
			requestAnimationFrame( () => {
				dialog.classList.add( 'is-visible' );
			} );
		} );
		document.body.style.overflow = 'hidden';
	},

	closeSpotifyModal() {
		const dialog = document.getElementById( 'oc-spotify-share-dialog' );
		if ( ! dialog || ! dialog.open ) {
			return;
		}

		dialog.classList.remove( 'is-visible' );
		clearTimeout( this.spotifyModalCloseTimer );
		this.spotifyModalCloseTimer = setTimeout( () => {
			if ( dialog.open ) {
				dialog.close();
			}
			document.body.style.overflow = '';
		}, 300 );
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
		inputEl = null
	) {
		this.inputs[ layerId ].spotifyStatus = status;
		this.inputs[ layerId ].spotifyUri = uri;
		this.syncLinkedLayerInput( layerId, SPOTIFY_LINK_SYNC_KEYS );
		this.setSpotifyError( layerId, message, inputEl );
		this.scheduleRedraw( this.areaIndexForLayer( layerId ) );
		this.updateHiddenField();
	},

	async validateSpotifyLayer( layerId, rawValue, inputEl = null ) {
		const value = String( rawValue || '' ).trim();
		if ( ! this.inputs[ layerId ] ) {
			this.inputs[ layerId ] = {};
		}
		const token = ( this.spotifyValidateTokens[ layerId ] || 0 ) + 1;
		this.spotifyValidateTokens[ layerId ] = token;

		if ( ! value ) {
			this.clearSpotifyLayerStatus( layerId, inputEl );
			return;
		}

		const localUri = this.extractSpotifyUri( value );
		if ( ! localUri ) {
			this.setSpotifyValidationResult(
				layerId,
				'invalid_format',
				'',
				'Invalid Spotify link format.',
				inputEl
			);
			return;
		}

		if ( ! this.data.validateSpotifyUrl ) {
			this.setSpotifyValidationResult(
				layerId,
				'ok',
				localUri,
				'',
				inputEl
			);
			return;
		}

		try {
			const res = await fetch( this.data.validateSpotifyUrl, {
				method: 'POST',
				headers: this.restHeaders( {
					'Content-Type': 'application/json',
				} ),
				body: JSON.stringify( { url: value } ),
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
			if ( this.spotifyValidateTokens[ layerId ] !== token ) {
				return;
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
				return;
			}

			if ( ! json ) {
				this.setSpotifyValidationResult(
					layerId,
					'unreachable',
					'',
					'Could not validate Spotify right now. Please try again.',
					inputEl
				);
				return;
			}

			if ( Boolean( json?.valid ) ) {
				this.inputs[ layerId ].spotifyStatus = 'ok';
				this.inputs[ layerId ].spotifyUri = json.spotifyUri || localUri;
				this.setSpotifyError( layerId, '', inputEl );
			} else {
				this.inputs[ layerId ].spotifyStatus =
					json?.reason || 'invalid_or_unavailable';
				this.inputs[ layerId ].spotifyUri = '';
				this.setSpotifyError(
					layerId,
					json?.message || 'Spotify link is invalid or unavailable.',
					inputEl
				);
			}
		} catch {
			if ( this.spotifyValidateTokens[ layerId ] !== token ) {
				return;
			}
			this.inputs[ layerId ].spotifyStatus = 'unreachable';
			this.inputs[ layerId ].spotifyUri = '';
			this.setSpotifyError(
				layerId,
				'Could not validate Spotify right now. Please try again.',
				inputEl
			);
		}

		this.syncLinkedLayerInput( layerId, SPOTIFY_LINK_SYNC_KEYS );
		this.scheduleRedraw( this.areaIndexForLayer( layerId ) );
		this.updateHiddenField();
	},
};

export default spotifyMethods;
