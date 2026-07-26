/**
 * Cart submission, mobile preview confirmation, and preview capture helpers.
 */

/* eslint-disable no-console, no-alert, import/no-unresolved */

import { dispatch } from '@wordpress/data';

const CART_STORE_KEY = 'wc/store/cart';

const QUALITY_WARNING_MESSAGE =
	'We found quality warnings that may affect print output. Press OK to continue, or Cancel to review.';
const MAX_CUSTOMISATION_BYTES = 1024 * 1024;
const CART_PREVIEW_MAX_DIMENSION = 640;
const CART_PREVIEW_QUALITY = 0.82;

const checkoutMethods = {
	handleVariationSubmitBlock() {
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
			Object.keys( this.aiFilterErrors || {} ).length > 0
		) {
			if ( this.artworkPendingCount > 0 ) {
				window.alert(
					'Please wait for artwork uploads and image processing to finish.'
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
		const form = document.querySelector( 'form.cart' );
		if ( ! form ) {
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
		const form = document.querySelector(
			'form[data-wp-on--submit*="addToCart"]:not(.cart)'
		);
		if ( ! form ) {
			return;
		}

		this._storeApiSubmitBound = true;
		form.addEventListener(
			'submit',
			( event ) => {
				if ( ! this._customisationActive ) {
					return;
				}
				event.preventDefault();
				event.stopImmediatePropagation();
				this.closeFontComboboxes( true );

				if ( ! form.checkValidity() ) {
					form.reportValidity?.();
					return;
				}
				if ( this._storeApiPreparationPromise ) {
					return;
				}

				const task = this.submitStoreApiCart( form );
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

	getStoreApiCartRequest( form ) {
		if ( form.querySelector( '[name^="quantity["]' ) ) {
			throw new Error(
				'Personalised grouped products require the standard add-to-cart form.'
			);
		}

		const variationField = form.querySelector( '[name="variation_id"]' );
		const variationId = parseInt( variationField?.value || '0', 10 ) || 0;
		const parentId =
			parseInt(
				form.querySelector( '[name="product_id"]' )?.value ||
					form.querySelector( '[name="add-to-cart"]' )?.value ||
					this.data.productId ||
					'0',
				10
			) || 0;
		if ( variationField && ! variationId ) {
			throw new Error(
				'Please select all product options before adding this item to your cart.'
			);
		}

		const productId = variationId || parentId;
		if ( ! productId ) {
			throw new Error( 'The selected product could not be identified.' );
		}

		const variation = [];
		const attributes = new Map();
		new FormData( form ).forEach( ( value, name ) => {
			if ( name.startsWith( 'attribute_' ) ) {
				attributes.set( name, String( value ) );
			}
		} );
		attributes.forEach( ( value, attribute ) => {
			if ( ! value ) {
				throw new Error(
					'Please select all product options before adding this item to your cart.'
				);
			}
			variation.push( { attribute, value } );
		} );

		const quantity = Number(
			form.querySelector( '[name="quantity"]' )?.value || 1
		);
		if ( ! Number.isFinite( quantity ) || quantity <= 0 ) {
			throw new Error( 'Please enter a valid product quantity.' );
		}

		return { productId, quantity, variation };
	},

	async submitStoreApiCart( form ) {
		let request;
		try {
			request = this.getStoreApiCartRequest( form );
		} catch ( error ) {
			this.renderPreflightMessages( [ error.message ], [] );
			return;
		}
		if ( ! this.acquireCartSubmitGuard( form ) ) {
			return;
		}

		try {
			const prepared = await this.prepareCartCustomisation();
			if ( ! prepared ) {
				return;
			}

			await dispatch( CART_STORE_KEY ).addItemToCart(
				request.productId,
				request.quantity,
				request.variation,
				{
					extensions: {
						overcustomise: {
							customisation: prepared.payload,
						},
					},
				}
			);
			this.restoreProductGallery();
		} catch ( error ) {
			console.error( '[OC] Store API cart submission failed:', error );
			this.restoreGalleryPreview();
			this.renderPreflightMessages(
				[
					error?.message ||
						'Could not add this personalisation to the cart. Please try again.',
				],
				[]
			);
		} finally {
			this.resetCartSubmitState( form );
		}
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

		const response = await fetch( this.data.savePreviewUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: this.restHeaders( {
				'Content-Type': 'application/json',
				Accept: 'application/json',
			} ),
			body: JSON.stringify( { image: previewImage } ),
		} );
		const body = await response.json().catch( () => null );
		if ( generation.designGeneration !== this._designGeneration ) {
			throw new Error( 'The selected design changed while saving.' );
		}
		const previewUrl = typeof body?.url === 'string' ? body.url.trim() : '';
		if ( ! response.ok || ! previewUrl ) {
			throw new Error(
				body?.message || 'The customisation preview could not be saved.'
			);
		}

		return previewUrl;
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
