/**
 * Cart submission, mobile preview confirmation, and preview upload helpers.
 */

/* eslint-disable no-console, no-alert */

const QUALITY_WARNING_MESSAGE =
	'We found quality warnings that may affect print output. Press OK to continue, or Cancel to review.';

const checkoutMethods = {
	setupFormSubmit() {
		if ( this.formSubmitBound ) {
			return;
		}
		const form = document.querySelector( 'form.cart' );
		if ( ! form ) {
			return;
		}
		this.formSubmitBound = true;

		if ( this.editMode ) {
			form.addEventListener(
				'submit',
				async ( e ) => {
					this.closeFontComboboxes( true );
					e.preventDefault();
					e.stopImmediatePropagation();
					this.syncInputsFromDOM();
					await this.flushRedraw();

					const preflight = await this.runPreflight();
					this.renderPreflightMessages(
						preflight.errors,
						preflight.warnings
					);
					if ( ! preflight.ok ) {
						return;
					}

					if ( preflight.warnings.length ) {
						const proceed = window.confirm(
							QUALITY_WARNING_MESSAGE
						);
						if ( ! proceed ) {
							return;
						}
					}

					await this.uploadPreview();
					this.updateHiddenField();

					const layers = {};
					this.areas.forEach( ( area ) => {
						( area.layers || [] ).forEach( ( layer ) => {
							const inp = this.inputs[ layer.id ];
							if ( inp ) {
								layers[ layer.id ] = {
									type: layer.type,
									...inp,
								};
							}
						} );
					} );
					const snapshots = await this.captureAreaSnapshots();

					try {
						const res = await fetch( this.data.updateCartItemUrl, {
							method: 'POST',
							headers: this.restHeaders( {
								'Content-Type': 'application/json',
							} ),
							body: JSON.stringify( {
								cart_key: this.cartKey,
								designId: this.data.designId,
								layers,
								snapshots,
								previewUrl: this._previewUrl || '',
							} ),
						} );
						let json = null;
						const isJson = res.headers
							.get( 'content-type' )
							?.includes( 'application/json' );
						if ( isJson ) {
							try {
								json = await res.json();
							} catch ( err ) {
								console.warn(
									'[OC] Cart update response parse failed:',
									err
								);
							}
						}

						if ( ! res.ok ) {
							this.renderPreflightMessages(
								[
									json?.message ||
										'Failed to update customisation.',
								],
								[]
							);
							return;
						}

						if ( json?.success ) {
							window.location.href =
								window.wc_cart_params?.cart_url || '/cart/';
						} else {
							this.renderPreflightMessages(
								[
									json?.message ||
										'Failed to update customisation.',
								],
								[]
							);
						}
					} catch ( err ) {
						console.error( '[OC] Update cart item failed:', err );
						this.renderPreflightMessages(
							[
								'Failed to update customisation. Please try again.',
							],
							[]
						);
					}
				},
				true
			);
			return;
		}

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
					return; // preview already saved, let submit through
				}
				e.preventDefault();
				e.stopImmediatePropagation();
				this.syncInputsFromDOM();
				await this.flushRedraw();

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
					const proceed = window.confirm( QUALITY_WARNING_MESSAGE );
					if ( ! proceed ) {
						this.resetCartSubmitState( form );
						return;
					}
				}

				const acceptedPreview = await this.confirmMobileCartPreview();
				if ( ! acceptedPreview ) {
					this.resetCartSubmitState( form );
					return;
				}

				await this.uploadPreview();
				await this.updateHiddenField( true );
				form._ocSubmitReady = true;
				// requestSubmit() re-triggers HTML5 validation before submitting.
				if ( form.requestSubmit ) {
					const submitter =
						form.querySelector( '[type="submit"]' ) || undefined;
					form.requestSubmit( submitter );
				} else {
					form.submit();
				}
			},
			true
		);
	},

	resetCartSubmitState( form ) {
		form.classList.remove( 'loading', 'processing' );
		form.querySelectorAll(
			'[type="submit"], .single_add_to_cart_button'
		).forEach( ( button ) => {
			button.classList.remove( 'loading', 'processing' );
			button.disabled = false;
			button.removeAttribute( 'disabled' );
			button.setAttribute( 'aria-disabled', 'false' );
		} );
	},

	isMobileCartPreviewRequired() {
		return (
			window.matchMedia?.( '(max-width: 639px)' )?.matches ||
			window.innerWidth < 640
		);
	},

	getCurrentPreviewDataUrl() {
		const canvas = this.canvases[ this.activeArea ];
		if ( canvas ) {
			try {
				return canvas.toDataURL( { format: 'jpeg', quality: 0.92 } );
			} catch {
				// Fall back to the already-rendered preview image below.
			}
		}

		return document.getElementById( 'oc-canvas-preview' )?.src || '';
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
			'<div class="oc-cart-preview-image-wrap">' +
			'<img class="oc-cart-preview-image" alt="Customisation preview">' +
			'</div>' +
			'<div class="oc-cart-preview-actions">' +
			'<button type="button" class="oc-cart-preview-change" data-oc-cart-preview-change>Change</button>' +
			'<button type="button" class="oc-cart-preview-accept" data-oc-cart-preview-accept>Accept</button>' +
			'</div>' +
			'</div>';

		dialogRoot.appendChild( dialog );
		this.mobileCartPreviewDialog = dialog;
		return dialog;
	},

	confirmMobileCartPreview() {
		if ( ! this.isMobileCartPreviewRequired() ) {
			return Promise.resolve( true );
		}

		this.closeFontComboboxes( true );

		const previewUrl = this.getCurrentPreviewDataUrl();
		const dialog = this.getMobileCartPreviewDialog();
		dialog.ownerDocument.activeElement?.blur?.();
		const img = dialog.querySelector( '.oc-cart-preview-image' );
		if ( img && previewUrl ) {
			img.src = previewUrl;
		}

		if ( ! dialog.showModal ) {
			return Promise.resolve( true );
		}

		return new Promise( ( resolve ) => {
			const acceptBtn = dialog.querySelector(
				'[data-oc-cart-preview-accept]'
			);
			const changeBtn = dialog.querySelector(
				'[data-oc-cart-preview-change]'
			);
			const previousFocus = dialog.ownerDocument.activeElement;

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
				previousFocus?.focus?.();
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

	async uploadPreview() {
		if ( ! this.data.savePreviewUrl ) {
			return;
		}
		await this.flushRedraw();

		let dataUrl;
		try {
			dataUrl = this.getCurrentPreviewDataUrl();
		} catch ( e ) {
			this._previewUrl = '';
			this.updateHiddenField();
			console.warn(
				'[OC] Could not capture preview for cart:',
				e.message
			);
			return;
		}

		try {
			const res = await fetch( this.data.savePreviewUrl, {
				method: 'POST',
				headers: this.restHeaders( {
					'Content-Type': 'application/json',
				} ),
				body: JSON.stringify( { image: dataUrl } ),
			} );
			if ( ! res.ok ) {
				this._previewUrl = '';
				this.updateHiddenField();
				return;
			}
			let json = null;
			const isJson = res.headers
				.get( 'content-type' )
				?.includes( 'application/json' );
			if ( isJson ) {
				try {
					json = await res.json();
				} catch ( err ) {
					console.warn(
						'[OC] Preview upload JSON parse failed:',
						err
					);
				}
			}
			if ( ! json ) {
				this._previewUrl = '';
				this.updateHiddenField();
				return;
			}
			if ( json.url ) {
				this._previewUrl = json.url;
				this.updateHiddenField();
			}
		} catch ( e ) {
			this._previewUrl = '';
			this.updateHiddenField();
			// Non-fatal: cart submits without a preview image.
			console.warn( '[OC] Preview upload failed:', e.message );
		}
	},
};

export default checkoutMethods;
