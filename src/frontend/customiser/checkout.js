/**
 * Cart submission, mobile preview confirmation, and preview capture helpers.
 */

/* eslint-disable no-console, no-alert */

const QUALITY_WARNING_MESSAGE =
	'We found quality warnings that may affect print output. Press OK to continue, or Cancel to review.';
const MAX_CUSTOMISATION_BYTES = 1024 * 1024;
const MAX_INLINE_CUSTOMISATION_BYTES = 768 * 1024;
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
			return false;
		}

		const variationId =
			parseInt(
				document.querySelector( 'form.cart input.variation_id' )
					?.value || '0',
				10
			) || 0;
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
					'An AI image filter failed. Retry the filter before adding this product to your cart.'
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
					this.syncInputsFromDOM();

					const preflight = await this.runPreflight();
					this.renderPreflightMessages(
						preflight.errors,
						preflight.warnings
					);
					if ( ! preflight.ok ) {
						this.resetCartSubmitState( form );
						return;
					}

					if ( preflight.warnings.length ) {
						const proceed = window.confirm(
							QUALITY_WARNING_MESSAGE
						);
						if ( ! proceed ) {
							this.resetCartSubmitState( form );
							return;
						}
					}

					await this.flushRedraw( this.inputs, {
						pushGallery: false,
					} );
					const generation = this.createSubmissionGeneration();
					const acceptedPreview =
						await this.confirmMobileCartPreview( generation );
					if ( ! acceptedPreview ) {
						this.restoreGalleryPreview();
						this.resetCartSubmitState( form );
						return;
					}

					const previewImage =
						this.getSubmissionPreviewImage( generation );
					await this.updateHiddenField( {
						generation,
						previewImage,
					} );
					const payload = document.getElementById(
						'oc-customisation-data'
					)?.value;
					if (
						payload &&
						new Blob( [ payload ] ).size > MAX_CUSTOMISATION_BYTES
					) {
						this.renderPreflightMessages(
							[
								'This personalisation is too large to add safely. Please simplify the design or contact us for help.',
							],
							[]
						);
						this.restoreGalleryPreview();
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
							'Could not prepare your customisation. Please try again.',
						],
						[]
					);
					this.resetCartSubmitState( form );
				}
			},
			true
		);
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

	getSubmissionPreviewImage( generation ) {
		if ( generation.designGeneration !== this._designGeneration ) {
			throw new Error( 'The selected design changed while rendering.' );
		}

		let previewImage = '';
		try {
			previewImage = this.getCurrentPreviewDataUrl();
		} catch ( error ) {
			console.warn(
				'[OC] Could not capture preview for cart:',
				error.message
			);
			return '';
		}

		const payload = this.buildCustomisationPayload( generation, {
			previewImage,
		} );
		if (
			this.customisationPayloadBytes( payload ) >
			MAX_INLINE_CUSTOMISATION_BYTES
		) {
			console.warn(
				'[OC] Cart preview omitted because the submission would be too large.'
			);
			return '';
		}

		return previewImage;
	},

	restoreGalleryPreview() {
		this.redraw( this.activeArea ).catch( ( error ) => {
			console.warn( '[OC] Could not restore gallery preview:', error.message );
		} );
	},

	async getMobileCartPreviewAreas() {
		const activeArea = this.activeArea;
		const previews = [];
		for ( let index = 0; index < this.areas.length; index++ ) {
			if ( index !== activeArea ) {
				await this.redraw( index, { pushGallery: false } );
			}
			try {
				previews.push( {
					index,
					label: this.areas[ index ]?.name || `Area ${ index + 1 }`,
					url: this.getCanvasPreviewDataUrl( index ),
				} );
			} catch {
				// Omit an area only when its canvas cannot be safely exported.
			}
		}
		return previews;
	},

	getMobileCartPreviewDialog() {
		if ( this.mobileCartPreviewDialog ) {
			return this.mobileCartPreviewDialog;
		}

		let dialogRoot = document.getElementById( 'oc-cart-preview-root' );
		if ( ! dialogRoot ) {
			dialogRoot = document.createElement( 'div' );
			dialogRoot.id = 'oc-cart-preview-root';
			dialogRoot.className = 'oc-customiser-panel oc-cart-preview-root';
			document.body.appendChild( dialogRoot );
		}

		const dialog = document.createElement( 'dialog' );
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
		this.mobileCartPreviewDialog = dialog;
		return dialog;
	},

	async confirmMobileCartPreview() {
		if ( ! this.isMobileCartPreviewRequired() ) {
			return true;
		}

		const customiserPanel = document.getElementById(
			'oc-customiser-panel'
		);
		const previousFocus = customiserPanel?.ownerDocument.activeElement;
		this.closeFontComboboxes( true );

		const previews = await this.getMobileCartPreviewAreas();
		if ( ! previews.length ) {
			this.renderPreflightMessages(
				[
					'The customisation preview is unavailable. Please wait for it to finish loading and try again.',
				],
				[]
			);
			return false;
		}
		const dialog = this.getMobileCartPreviewDialog();
		dialog.ownerDocument.activeElement?.blur?.();
		const tabs = dialog.querySelector( '.oc-cart-preview-tabs' );
		const panels = dialog.querySelector( '.oc-cart-preview-panels' );
		tabs.replaceChildren();
		panels.replaceChildren();
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

		if ( ! dialog.showModal ) {
			return true;
		}

		return new Promise( ( resolve ) => {
			const acceptBtn = dialog.querySelector(
				'[data-oc-cart-preview-accept]'
			);
			const changeBtn = dialog.querySelector(
				'[data-oc-cart-preview-change]'
			);
			const finish = ( accepted ) => {
				if ( ! accepted ) {
					this.mobileCartPreviewDismissedAt = Date.now();
				}

				dialog.classList.remove( 'is-visible' );
				dialog.removeEventListener( 'click', onBackdropClick );
				dialog.removeEventListener( 'cancel', onCancel );
				acceptBtn?.removeEventListener( 'click', onAccept );
				changeBtn?.removeEventListener( 'click', onChange );
				if ( dialog.open ) {
					dialog.close();
				}
				window.setTimeout( () => previousFocus?.focus?.(), 0 );
				resolve( accepted );
			};

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

			acceptBtn?.addEventListener( 'click', onAccept );
			changeBtn?.addEventListener( 'click', onChange );
			dialog.addEventListener( 'click', onBackdropClick );
			dialog.addEventListener( 'cancel', onCancel );

			dialog.showModal();
			window.requestAnimationFrame( () =>
				dialog.classList.add( 'is-visible' )
			);
			acceptBtn?.focus?.();
		} );
	},
};

export default checkoutMethods;
