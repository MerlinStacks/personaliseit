/**
 * Cart submission, mobile preview confirmation, and preview capture helpers.
 */

/* eslint-disable no-console, no-alert */

const QUALITY_WARNING_MESSAGE =
	'We found quality warnings that may affect print output. Press OK to continue, or Cancel to review.';
const MAX_CUSTOMISATION_BYTES = 1024 * 1024;
const CART_PREVIEW_MAX_DIMENSION = 640;
const CART_PREVIEW_QUALITY = 0.82;
const STORE_API_MERGE_TIMEOUT_MS = 10000;

const checkoutMethods = {
	getCustomiserCartForm() {
		const panel = document.getElementById( 'oc-customiser-panel' );
		const owningForm = panel?.closest( 'form' );
		if ( owningForm ) {
			return owningForm;
		}

		const productRoot = panel?.closest(
			'.product, [data-block-name="woocommerce/single-product"]'
		);
		if ( ! productRoot ) {
			return null;
		}
		const candidates = Array.from(
			productRoot.querySelectorAll(
				'form.cart, form[data-wp-on--submit*="addToCart"]'
			)
		);
		if ( candidates.length !== 1 ) {
			return null;
		}

		const form = candidates[ 0 ];
		const hiddenField = panel.querySelector( '#oc-customisation-data' );
		if ( hiddenField && ! form.contains( hiddenField ) ) {
			if ( ! form.id ) {
				form.id = `oc-cart-form-${ this.data.productId || 'product' }`;
			}
			hiddenField.setAttribute( 'form', form.id );
		}
		return form;
	},

	handleVariationSubmitBlock() {
		if ( this._designVariantPendingSeq ) {
			window.alert(
				'Please wait while the selected artwork option finishes loading.'
			);
			return true;
		}
		if ( this._variationSwitchPending ) {
			window.alert(
				'Please wait while the personalisation options finish loading.'
			);
			return true;
		}
		if ( ! this._variationSwitchFailed ) {
			const variationId = this.currentVariationId();
			if (
				! variationId ||
				String( variationId ) === this._activeVariationKey
			) {
				return false;
			}

			window.alert(
				'Please wait while the personalisation options load for this variation.'
			);
			this.switchProductVariation( variationId );
			return true;
		}

		const variationId = this.currentVariationId();
		window.alert(
			'Retrying the personalisation options for this variation.'
		);
		this.switchProductVariation( variationId );
		return true;
	},

	acquireCartSubmitGuard( form ) {
		if ( this.handleVariationSubmitBlock() ) {
			return false;
		}
		if (
			this._submitInProgress ||
			! this._customisationActive ||
			this.artworkPendingCount > 0 ||
			this.failedArtworkReplacements.size > 0 ||
			Object.keys( this.aiFilterErrors || {} ).length > 0
		) {
			if ( this.artworkPendingCount > 0 ) {
				window.alert(
					'Please wait for artwork uploads and image processing to finish.'
				);
			} else if ( this.failedArtworkReplacements.size > 0 ) {
				window.alert(
					'A replacement upload failed. Retry it or choose "Use previous image" before adding this product to your cart.'
				);
			} else if ( Object.keys( this.aiFilterErrors || {} ).length > 0 ) {
				window.alert(
					'An image effect could not be applied. Retry it before adding this product to your cart.'
				);
			}
			return false;
		}
		this._submitInProgress = true;
		form.classList.add( 'processing' );
		this.setControlLock( 'submission', true );
		return true;
	},

	setupFormSubmit() {
		if ( this.formSubmitBound ) {
			return;
		}
		const form = this.getCustomiserCartForm();
		if ( ! form?.matches( 'form.cart' ) ) {
			return;
		}
		this.formSubmitBound = true;

		form.querySelectorAll(
			'[type="submit"], .single_add_to_cart_button'
		).forEach( ( button ) => {
			button.addEventListener(
				'click',
				( e ) => {
					this.closeFontComboboxes( true );
					if ( form._ocSubmitReady ) {
						return;
					}
					if ( this.handleVariationSubmitBlock() ) {
						e.preventDefault();
						e.stopImmediatePropagation();
						return;
					}
					if ( ! this._customisationActive ) {
						this.updateHiddenField();
						return;
					}
					this.clearCustomValidity();
					if ( ! form.checkValidity() ) {
						return;
					}

					e.preventDefault();
					e.stopImmediatePropagation();

					this.syncInputsFromDOM();
					const preflight = this.runImmediateBlockingPreflight();
					if ( preflight.ok ) {
						if ( form.requestSubmit ) {
							form.requestSubmit( button );
						} else {
							form.dispatchEvent(
								new Event( 'submit', {
									bubbles: true,
									cancelable: true,
								} )
							);
						}
						return;
					}

					this.resetCartSubmitState( form );
					this.renderPreflightMessages(
						preflight.errors,
						preflight.warnings
					);
				},
				true
			);
		} );

		form.addEventListener(
			'submit',
			async ( e ) => {
				this.closeFontComboboxes( true );
				if ( this.handleVariationSubmitBlock() ) {
					e.preventDefault();
					e.stopImmediatePropagation();
					this.resetCartSubmitState( form );
					return;
				}
				if ( ! this._customisationActive ) {
					this.updateHiddenField();
					return;
				}

				if (
					this.mobileCartPreviewDismissedAt &&
					Date.now() - this.mobileCartPreviewDismissedAt < 750
				) {
					e.preventDefault();
					e.stopImmediatePropagation();
					this.resetCartSubmitState( form );
					return;
				}

				if ( form._ocSubmitReady ) {
					form._ocSubmitReady = false;
					this.resetCartSubmitState( form );
					return; // preview already captured, let submit through
				}
				e.preventDefault();
				e.stopImmediatePropagation();
				if ( ! this.acquireCartSubmitGuard( form ) ) {
					return;
				}
				try {
					const prepared = await this.prepareCartCustomisation();
					if ( ! prepared ) {
						this.resetCartSubmitState( form );
						return;
					}
					form._ocSubmitReady = true;
					this.resetCartSubmitState( form );
					// requestSubmit() re-triggers HTML5 validation before submitting.
					if ( form.requestSubmit ) {
						const submitter =
							form.querySelector( '[type="submit"]' ) ||
							undefined;
						form.requestSubmit( submitter );
					} else {
						form.submit();
					}
				} catch ( error ) {
					console.error( '[OC] Cart submission failed:', error );
					this.restoreGalleryPreview();
					this.renderPreflightMessages(
						[
							'The customisation preview could not be prepared. Please wait for all artwork to load and try again.',
						],
						[]
					);
					this.resetCartSubmitState( form );
				}
			},
			true
		);
	},

	async prepareCartCustomisation() {
		this.syncInputsFromDOM();
		if ( this.failedArtworkReplacements.size > 0 ) {
			this.renderPreflightMessages(
				[
					'A replacement image failed to upload. Retry it or explicitly use the previous image.',
				],
				[]
			);
			return null;
		}
		const preflight = await this.runPreflight();
		this.renderPreflightMessages( preflight.errors, preflight.warnings );
		if ( ! preflight.ok ) {
			return null;
		}

		if (
			preflight.warnings.length &&
			! window.confirm( QUALITY_WARNING_MESSAGE )
		) {
			return null;
		}

		await this.flushRedraw( this.inputs, { pushGallery: false } );
		const generation = this.createSubmissionGeneration();
		const previews = this.getSubmissionPreviewAreas( generation );
		const acceptedPreview = await this.confirmMobileCartPreview(
			generation,
			previews
		);
		if ( ! acceptedPreview ) {
			this.restoreGalleryPreview();
			return null;
		}

		const previewImage = this.getSubmissionPreviewImage(
			generation,
			previews
		);
		const previewUrl = await this.uploadCartPreview(
			previewImage,
			generation
		);
		const payload = this.buildCustomisationPayload( generation, {
			previewUrl,
		} );

		const serialisedPayload = JSON.stringify( payload );
		if (
			new Blob( [ serialisedPayload ] ).size > MAX_CUSTOMISATION_BYTES
		) {
			this.renderPreflightMessages(
				[
					'This personalisation is too large to add safely. Please simplify the design or contact us for help.',
				],
				[]
			);
			return null;
		}

		const hiddenField = document.getElementById( 'oc-customisation-data' );
		if ( hiddenField ) {
			hiddenField.disabled = false;
			hiddenField.value = serialisedPayload;
		}
		return { generation, payload, serialisedPayload };
	},

	resetCartSubmitState( form ) {
		this._submitInProgress = false;
		this.setControlLock( 'submission', false );
		form.classList.remove( 'loading', 'processing' );
		form.querySelectorAll(
			'[type="submit"], .single_add_to_cart_button'
		).forEach( ( button ) => {
			button.classList.remove( 'loading', 'processing' );
		} );
	},

	setupStoreApiIntegration() {
		if ( this._storeApiSubmitBound ) {
			return;
		}
		const form = this.getCustomiserCartForm();
		if (
			! form?.matches(
				'form[data-wp-on--submit*="addToCart"]:not(.cart)'
			)
		) {
			return;
		}

		this._storeApiSubmitBound = true;
		this.setupStoreApiFetchMerger();
		form.addEventListener(
			'submit',
			async ( event ) => {
				if ( ! this._customisationActive ) {
					this.updateHiddenField();
					return;
				}
				if ( form._ocSubmitReady ) {
					form._ocSubmitReady = false;
					this.resetCartSubmitState( form );
					return;
				}
				event.preventDefault();
				event.stopImmediatePropagation();
				this.closeFontComboboxes( true );

				if ( ! form.checkValidity() ) {
					form.reportValidity?.();
					return;
				}
				if ( form.querySelector( '[name^="quantity["]' ) ) {
					event.preventDefault();
					event.stopImmediatePropagation();
					this.renderPreflightMessages(
						[
							'Personalised grouped products require the standard add-to-cart form.',
						],
						[]
					);
					return;
				}
				if ( this._storeApiPreparationPromise ) {
					return;
				}

				const submitter = event.submitter;
				const task = ( async () => {
					if ( ! this.acquireCartSubmitGuard( form ) ) {
						return;
					}
					try {
						const prepared = await this.prepareCartCustomisation();
						if ( ! prepared ) {
							return;
						}
						this.armStoreApiCustomisationMerge( prepared.payload );
						form._ocSubmitReady = true;
						this.resetCartSubmitState( form );
						if ( form.requestSubmit ) {
							form.requestSubmit(
								submitter?.isConnected ? submitter : undefined
							);
						} else {
							form.dispatchEvent(
								new Event( 'submit', {
									bubbles: true,
									cancelable: true,
								} )
							);
						}
					} catch ( error ) {
						this.failStoreApiCustomisationMerge(
							error?.message,
							false
						);
						console.error(
							'[OC] Store API preparation failed:',
							error
						);
						this.restoreGalleryPreview();
						this.renderPreflightMessages(
							[
								error?.message ||
									'The customisation preview could not be prepared. Please try again.',
							],
							[]
						);
					} finally {
						if ( ! form._ocSubmitReady ) {
							this.resetCartSubmitState( form );
						}
					}
				} )();
				this._storeApiPreparationPromise = task;
				task.finally( () => {
					if ( this._storeApiPreparationPromise === task ) {
						this._storeApiPreparationPromise = null;
					}
				} );
			},
			true
		);
	},

	setupStoreApiFetchMerger() {
		if ( this._storeApiFetchBound ) {
			return;
		}
		this._storeApiFetchBound = true;
		const originalFetch = window.fetch;
		window.fetch = async ( input, init ) => {
			const pending = this._pendingStoreApiCustomisation;
			if ( ! pending ) {
				return originalFetch.call( window, input, init );
			}

			const requestUrl =
				input instanceof Request ? input.url : String( input );
			const route = this.getStoreApiRequestRoute( requestUrl );
			const method = String(
				init?.method ||
					( input instanceof Request ? input.method : 'GET' )
			).toUpperCase();
			if (
				method !== 'POST' ||
				! [ 'add-item', 'batch' ].includes( route )
			) {
				return originalFetch.call( window, input, init );
			}

			let bodyText;
			try {
				bodyText = '';
				if ( init?.body !== undefined ) {
					bodyText = String( init.body );
				} else if ( input instanceof Request ) {
					bodyText = await input.clone().text();
				}
			} catch {
				return this.rejectStoreApiCustomisationRequest();
			}

			let body;
			try {
				body = JSON.parse( bodyText );
			} catch {
				return this.rejectStoreApiCustomisationRequest();
			}

			const merged = this.mergeStoreApiCustomisationBody(
				route,
				body,
				pending
			);
			if ( ! merged ) {
				const containsAddItem =
					route === 'add-item' ||
					body?.requests?.some(
						( request ) =>
							String(
								request?.method || 'POST'
							).toUpperCase() === 'POST' &&
							this.getStoreApiRequestRoute(
								request?.path || ''
							) === 'add-item'
					);
				if ( containsAddItem ) {
					return this.rejectStoreApiCustomisationRequest();
				}
				return originalFetch.call( window, input, init );
			}
			if ( pending.expired ) {
				return this.rejectStoreApiCustomisationRequest();
			}

			this.consumeStoreApiCustomisationMerge();
			const nextInit = {
				...( init || {} ),
				body: JSON.stringify( merged ),
			};
			if ( input instanceof Request ) {
				return originalFetch.call(
					window,
					new Request( input, nextInit )
				);
			}
			return originalFetch.call( window, input, nextInit );
		};
	},

	getStoreApiRequestRoute( requestUrl ) {
		let url;
		try {
			url = new URL( requestUrl, window.location.href );
		} catch {
			return '';
		}
		if ( url.origin !== window.location.origin ) {
			return '';
		}
		const route = decodeURIComponent(
			url.searchParams.get( 'rest_route' ) || url.pathname
		).replace( /\/+$/, '' );
		if ( ! /\/wc\/store\/v\d+(?:\/|$)/.test( route ) ) {
			return '';
		}
		if ( /\/cart\/add-item$/.test( route ) ) {
			return 'add-item';
		}
		return /\/batch$/.test( route ) ? 'batch' : '';
	},

	storeApiBodyMatchesProduct( body, pending ) {
		return (
			body &&
			typeof body === 'object' &&
			pending.expectedProductIds.includes( Number( body.id || 0 ) )
		);
	},

	mergeStoreApiCustomisationBody( route, body, pending ) {
		const mergeItem = ( itemBody ) => ( {
			...itemBody,
			extensions: {
				...( itemBody.extensions || {} ),
				overcustomise: {
					...( itemBody.extensions?.overcustomise || {} ),
					customisation: pending.payload,
				},
			},
		} );

		if ( route === 'add-item' ) {
			return this.storeApiBodyMatchesProduct( body, pending )
				? mergeItem( body )
				: null;
		}
		if ( ! Array.isArray( body?.requests ) ) {
			return null;
		}

		const matchingIndexes = body.requests
			.map( ( request, index ) => ( {
				request,
				index,
			} ) )
			.filter(
				( { request } ) =>
					String( request?.method || 'POST' ).toUpperCase() ===
						'POST' &&
					this.getStoreApiRequestRoute( request?.path || '' ) ===
						'add-item' &&
					this.storeApiBodyMatchesProduct( request?.body, pending )
			)
			.map( ( { index } ) => index );
		if ( matchingIndexes.length !== 1 ) {
			return null;
		}

		const matchedIndex = matchingIndexes[ 0 ];
		const requests = body.requests.map( ( request, index ) =>
			index === matchedIndex
				? { ...request, body: mergeItem( request.body ) }
				: request
		);
		return { ...body, requests };
	},

	armStoreApiCustomisationMerge( payload ) {
		this.consumeStoreApiCustomisationMerge();
		const variationId = this.currentVariationId();
		const expectedProductIds = [
			Number( this.data.productId || 0 ),
			Number( variationId || 0 ),
		].filter( ( id, index, ids ) => id > 0 && ids.indexOf( id ) === index );
		const pending = {
			payload,
			expectedProductIds,
			expired: false,
			timer: null,
		};
		pending.timer = this.setStateTimeout( () => {
			if ( this._pendingStoreApiCustomisation !== pending ) {
				return;
			}
			this.expireStoreApiCustomisationMerge();
		}, STORE_API_MERGE_TIMEOUT_MS );
		this._pendingStoreApiCustomisation = pending;
	},

	expireStoreApiCustomisationMerge( render = true ) {
		const pending = this._pendingStoreApiCustomisation;
		if ( ! pending || pending.expired ) {
			return;
		}
		if ( pending.timer !== null ) {
			this.clearStateTimeout( pending.timer );
		}
		pending.expired = true;
		pending.timer = null;
		if ( render ) {
			this.renderPreflightMessages(
				[
					'WooCommerce did not receive the personalisation request. Please try adding the product again.',
				],
				[]
			);
		}
	},

	consumeStoreApiCustomisationMerge() {
		const pending = this._pendingStoreApiCustomisation;
		if ( pending?.timer !== null && pending?.timer !== undefined ) {
			this.clearStateTimeout( pending.timer );
		}
		this._pendingStoreApiCustomisation = null;
	},

	failStoreApiCustomisationMerge( message, render = true ) {
		if ( ! this._pendingStoreApiCustomisation ) {
			return;
		}
		this.consumeStoreApiCustomisationMerge();
		if ( render ) {
			this.renderPreflightMessages(
				[
					message ||
						'The personalisation could not be attached to the cart request. Please try again.',
				],
				[]
			);
		}
	},

	rejectStoreApiCustomisationRequest() {
		const message =
			'The personalisation could not be attached to the WooCommerce request. Please try adding the product again.';
		this.failStoreApiCustomisationMerge( message );
		return Promise.reject( new Error( message ) );
	},

	isMobileCartPreviewRequired() {
		return (
			window.matchMedia?.( '(max-width: 639px)' )?.matches ||
			window.innerWidth < 640
		);
	},

	getCanvasPreviewDataUrl( areaIndex ) {
		const canvas = this.canvases[ areaIndex ];
		if ( ! canvas || canvas._ocMissingMockup ) {
			throw new Error( 'The customisation preview is unavailable.' );
		}
		if ( canvas._ocRenderErrors?.length ) {
			throw new Error( 'Some artwork could not be rendered.' );
		}
		if ( this._redrawPromises[ areaIndex ] ) {
			throw new Error( 'The customisation preview is still rendering.' );
		}

		const revision = `${ this._designGeneration }:${
			this._redrawGenerations[ areaIndex ] || 0
		}`;
		if (
			canvas._ocCartPreviewRevision === revision &&
			canvas._ocCartPreviewDataUrl
		) {
			return canvas._ocCartPreviewDataUrl;
		}

		const width = Math.max(
			1,
			Number( canvas.getWidth?.() || canvas.width || 1 )
		);
		const height = Math.max(
			1,
			Number( canvas.getHeight?.() || canvas.height || 1 )
		);
		const multiplier = Math.min(
			1,
			CART_PREVIEW_MAX_DIMENSION / Math.max( width, height )
		);
		const dataUrl = canvas.toDataURL( {
			format: 'jpeg',
			quality: CART_PREVIEW_QUALITY,
			multiplier,
		} );
		if ( ! /^data:image\/(?:jpeg|png);base64,/i.test( dataUrl ) ) {
			throw new Error(
				'The customisation preview could not be captured.'
			);
		}
		canvas._ocCartPreviewRevision = revision;
		canvas._ocCartPreviewDataUrl = dataUrl;
		return dataUrl;
	},

	getCurrentPreviewDataUrl() {
		return this.getCanvasPreviewDataUrl( this.activeArea );
	},

	customisationPayloadBytes( payload ) {
		return new Blob( [ JSON.stringify( payload ) ] ).size;
	},

	getSubmissionPreviewAreas( generation ) {
		if ( generation.designGeneration !== this._designGeneration ) {
			throw new Error( 'The selected design changed while rendering.' );
		}
		if ( ! this.areas.length ) {
			throw new Error( 'The customisation has no preview areas.' );
		}

		return this.areas.map( ( area, index ) => ( {
			index,
			label: area?.label || `Area ${ index + 1 }`,
			url: this.getCanvasPreviewDataUrl( index ),
		} ) );
	},

	getSubmissionPreviewImage( generation, previews = null ) {
		const availablePreviews =
			previews || this.getSubmissionPreviewAreas( generation );
		const previewImage = availablePreviews.find(
			( preview ) => preview.index === this.activeArea
		)?.url;
		if ( ! previewImage ) {
			throw new Error(
				'The active customisation preview is unavailable.'
			);
		}

		return previewImage;
	},

	async uploadCartPreview( previewImage, generation ) {
		if ( ! this.data.savePreviewUrl ) {
			throw new Error( 'The preview upload service is unavailable.' );
		}

		const request = this.createStateAbortController( 20000 );
		try {
			const response = await fetch( this.data.savePreviewUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: this.restHeaders( {
					'Content-Type': 'application/json',
					Accept: 'application/json',
				} ),
				body: JSON.stringify( { image: previewImage } ),
				signal: request.controller.signal,
			} );
			const body = await response.json().catch( () => null );
			if ( generation.designGeneration !== this._designGeneration ) {
				throw new Error( 'The selected design changed while saving.' );
			}
			const previewUrl =
				typeof body?.url === 'string' ? body.url.trim() : '';
			if ( ! response.ok || ! previewUrl ) {
				throw new Error(
					body?.message ||
						'The customisation preview could not be saved.'
				);
			}

			return previewUrl;
		} catch ( error ) {
			if ( request.timedOut() ) {
				throw new Error(
					'The customisation preview upload timed out. Please try again.'
				);
			}
			throw error;
		} finally {
			request.release();
		}
	},

	restoreGalleryPreview() {
		this.redraw( this.activeArea ).catch( ( error ) => {
			console.warn(
				'[OC] Could not restore gallery preview:',
				error.message
			);
		} );
	},

	getMobileCartPreviewAreas( generation ) {
		return this.getSubmissionPreviewAreas( generation );
	},

	getMobileCartPreviewDialog() {
		if ( this.mobileCartPreviewDialog?.isConnected ) {
			return this.mobileCartPreviewDialog;
		}

		let dialogRoot = document.getElementById( 'oc-cart-preview-root' );
		if ( ! dialogRoot ) {
			dialogRoot = document.createElement( 'div' );
			dialogRoot.id = 'oc-cart-preview-root';
			dialogRoot.className = 'oc-customiser-panel oc-cart-preview-root';
			document.body.appendChild( dialogRoot );
		}

		let dialog = document.getElementById( 'oc-cart-preview-dialog' );
		if ( ! dialog ) {
			const nativeDialog = document.createElement( 'dialog' );
			dialog = nativeDialog.showModal
				? nativeDialog
				: document.createElement( 'div' );
			dialog.id = 'oc-cart-preview-dialog';
			dialog.className = 'oc-cart-preview-dialog';
			dialog.setAttribute( 'aria-labelledby', 'oc-cart-preview-title' );
			dialog.setAttribute( 'aria-describedby', 'oc-cart-preview-desc' );
			dialog.innerHTML =
				'<div class="oc-cart-preview-card">' +
				'<div class="oc-cart-preview-copy">' +
				'<h2 id="oc-cart-preview-title">Check your preview</h2>' +
				'<p id="oc-cart-preview-desc">Please confirm your customisation looks correct before adding this product to your cart.</p>' +
				'</div>' +
				'<div class="oc-cart-preview-tabs" role="tablist" aria-label="Preview areas"></div>' +
				'<div class="oc-cart-preview-panels"></div>' +
				'<div class="oc-cart-preview-actions">' +
				'<button type="button" class="oc-cart-preview-change" data-oc-cart-preview-change>Change</button>' +
				'<button type="button" class="oc-cart-preview-accept" data-oc-cart-preview-accept>Accept</button>' +
				'</div>' +
				'</div>';
			dialogRoot.appendChild( dialog );
		}
		this.mobileCartPreviewDialog = dialog;
		return dialog;
	},

	dismissMobileCartPreview() {
		if ( this._mobileCartPreviewResolve ) {
			this._mobileCartPreviewResolve( false );
			return;
		}

		const dialog = this.mobileCartPreviewDialog;
		if ( ! dialog ) {
			return;
		}
		dialog.classList.remove( 'is-visible' );
		if ( typeof dialog.showModal === 'function' && dialog.open ) {
			dialog.close?.();
		} else {
			dialog.removeAttribute( 'open' );
		}
		if ( ! dialog.showModal ) {
			dialog.hidden = true;
		}
		document.documentElement.classList.remove( 'oc-cart-preview-open' );
	},

	confirmMobileCartPreview( generation, suppliedPreviews = null ) {
		if ( ! this.isMobileCartPreviewRequired() ) {
			return Promise.resolve( true );
		}
		if ( this._mobileCartPreviewPromise ) {
			return this._mobileCartPreviewPromise;
		}

		const ownerDocument =
			document.getElementById( 'oc-customiser-panel' )?.ownerDocument ||
			window.document;
		const previousFocus = ownerDocument.activeElement;
		this.closeFontComboboxes( true );

		const previews =
			suppliedPreviews || this.getMobileCartPreviewAreas( generation );
		if ( ! previews.length ) {
			this.renderPreflightMessages(
				[
					'The customisation preview is unavailable. Please wait for it to finish loading and try again.',
				],
				[]
			);
			return Promise.resolve( false );
		}
		const dialog = this.getMobileCartPreviewDialog();
		const supportsModal = typeof dialog.showModal === 'function';
		dialog.classList.toggle(
			'oc-cart-preview-dialog--fallback',
			! supportsModal
		);
		dialog.setAttribute( 'role', 'dialog' );
		dialog.setAttribute( 'aria-modal', 'true' );
		dialog.ownerDocument.activeElement?.blur?.();
		const tabs = dialog.querySelector( '.oc-cart-preview-tabs' );
		const panels = dialog.querySelector( '.oc-cart-preview-panels' );
		tabs.replaceChildren();
		panels.replaceChildren();
		tabs.hidden = previews.length === 1;
		previews.forEach( ( preview, position ) => {
			const tab = document.createElement( 'button' );
			tab.type = 'button';
			tab.id = `oc-cart-preview-tab-${ preview.index }`;
			tab.className = 'oc-cart-preview-tab';
			tab.role = 'tab';
			tab.textContent = preview.label;
			tab.dataset.ocPreviewPosition = String( position );
			tab.setAttribute(
				'aria-selected',
				position === 0 ? 'true' : 'false'
			);
			tab.setAttribute(
				'aria-controls',
				`oc-cart-preview-panel-${ preview.index }`
			);
			tab.tabIndex = position === 0 ? 0 : -1;

			const panel = document.createElement( 'div' );
			panel.id = `oc-cart-preview-panel-${ preview.index }`;
			panel.className = 'oc-cart-preview-image-wrap';
			panel.role = 'tabpanel';
			panel.setAttribute( 'aria-labelledby', tab.id );
			panel.hidden = position !== 0;
			const img = document.createElement( 'img' );
			img.className = 'oc-cart-preview-image';
			img.alt = `${ preview.label } customisation preview`;
			img.src = preview.url;
			panel.appendChild( img );
			tabs.appendChild( tab );
			panels.appendChild( panel );
		} );
		const previewTabs = Array.from(
			tabs.querySelectorAll( '[role="tab"]' )
		);
		const selectPreview = ( position, focus = false ) => {
			previewTabs.forEach( ( tab, index ) => {
				const selected = index === position;
				tab.setAttribute(
					'aria-selected',
					selected ? 'true' : 'false'
				);
				tab.tabIndex = selected ? 0 : -1;
				panels.children[ index ].hidden = ! selected;
			} );
			if ( focus ) {
				previewTabs[ position ]?.focus();
			}
		};
		previewTabs.forEach( ( tab, position ) => {
			tab.addEventListener( 'click', () => selectPreview( position ) );
			tab.addEventListener( 'keydown', ( event ) => {
				if (
					! [ 'ArrowLeft', 'ArrowRight', 'Home', 'End' ].includes(
						event.key
					)
				) {
					return;
				}
				event.preventDefault();
				let next = event.key === 'Home' ? 0 : previewTabs.length - 1;
				if ( event.key === 'ArrowLeft' ) {
					next =
						( position - 1 + previewTabs.length ) %
						previewTabs.length;
				}
				if ( event.key === 'ArrowRight' ) {
					next = ( position + 1 ) % previewTabs.length;
				}
				selectPreview( next, true );
			} );
		} );

		const promise = new Promise( ( resolve ) => {
			const acceptBtn = dialog.querySelector(
				'[data-oc-cart-preview-accept]'
			);
			const changeBtn = dialog.querySelector(
				'[data-oc-cart-preview-change]'
			);
			let settled = false;
			const finish = ( accepted ) => {
				if ( settled ) {
					return;
				}
				settled = true;
				this._mobileCartPreviewResolve = null;
				if ( ! accepted ) {
					this.mobileCartPreviewDismissedAt = Date.now();
				}

				dialog.classList.remove( 'is-visible' );
				dialog.removeEventListener( 'click', onBackdropClick );
				dialog.removeEventListener( 'cancel', onCancel );
				dialog.removeEventListener( 'keydown', onDialogKeydown );
				acceptBtn?.removeEventListener( 'click', onAccept );
				changeBtn?.removeEventListener( 'click', onChange );
				if ( supportsModal && dialog.open ) {
					dialog.close?.();
				} else {
					dialog.removeAttribute( 'open' );
					dialog.hidden = true;
				}
				document.documentElement.classList.remove(
					'oc-cart-preview-open'
				);
				this.setStateTimeout( () => previousFocus?.focus?.(), 0 );
				resolve( accepted );
			};
			this._mobileCartPreviewResolve = finish;

			const stopModalAction = ( event ) => {
				event?.preventDefault?.();
				event?.stopPropagation?.();
				event?.stopImmediatePropagation?.();
			};

			const onAccept = ( event ) => {
				stopModalAction( event );
				finish( true );
			};
			const onChange = ( event ) => {
				stopModalAction( event );
				finish( false );
			};
			const onBackdropClick = ( event ) => {
				if ( event.target === dialog ) {
					finish( false );
				}
			};
			const onCancel = ( event ) => {
				event.preventDefault();
				finish( false );
			};
			const onDialogKeydown = ( event ) => {
				if ( event.key === 'Escape' && ! supportsModal ) {
					event.preventDefault();
					finish( false );
					return;
				}
				if ( event.key !== 'Tab' || supportsModal ) {
					return;
				}
				const focusable = Array.from(
					dialog.querySelectorAll(
						'button:not(:disabled), [href], input:not(:disabled), select:not(:disabled), textarea:not(:disabled), [tabindex]:not([tabindex="-1"])'
					)
				).filter( ( control ) => ! control.closest( '[hidden]' ) );
				const first = focusable[ 0 ];
				const last = focusable[ focusable.length - 1 ];
				if ( ! first || ! last ) {
					event.preventDefault();
					return;
				}
				if (
					event.shiftKey &&
					dialog.ownerDocument.activeElement === first
				) {
					event.preventDefault();
					last.focus();
				} else if (
					! event.shiftKey &&
					dialog.ownerDocument.activeElement === last
				) {
					event.preventDefault();
					first.focus();
				}
			};

			acceptBtn?.addEventListener( 'click', onAccept );
			changeBtn?.addEventListener( 'click', onChange );
			dialog.addEventListener( 'click', onBackdropClick );
			dialog.addEventListener( 'cancel', onCancel );
			dialog.addEventListener( 'keydown', onDialogKeydown );

			if ( supportsModal ) {
				dialog.hidden = false;
				dialog.showModal();
			} else {
				dialog.hidden = false;
				dialog.setAttribute( 'open', '' );
				document.documentElement.classList.add(
					'oc-cart-preview-open'
				);
			}
			this.requestStateAnimationFrame( () =>
				dialog.classList.add( 'is-visible' )
			);
			acceptBtn?.focus?.();
		} );

		this._mobileCartPreviewPromise = promise.finally( () => {
			this._mobileCartPreviewPromise = null;
		} );
		return this._mobileCartPreviewPromise;
	},
};

export default checkoutMethods;
