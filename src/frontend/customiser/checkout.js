/**
 * Cart submission, mobile preview confirmation, and preview upload helpers.
 */

/* eslint-disable no-console, no-alert */

const QUALITY_WARNING_MESSAGE =
	'We found quality warnings that may affect print output. Press OK to continue, or Cancel to review.';
const MAX_CUSTOMISATION_BYTES = 1024 * 1024;

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
			this.aiFilterPending > 0 ||
			Object.keys( this.aiFilterErrors || {} ).length > 0
		) {
			if ( this.aiFilterPending > 0 ) {
				window.alert(
					'Please wait for the AI image filter to finish.'
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
		form.querySelectorAll(
			'[type="submit"], .single_add_to_cart_button'
		).forEach( ( button ) => {
			button.disabled = true;
			button.setAttribute( 'aria-disabled', 'true' );
		} );
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

		if ( this.editMode ) {
			form.addEventListener(
				'submit',
				async ( e ) => {
					this.closeFontComboboxes( true );
					e.preventDefault();
					e.stopImmediatePropagation();
					if ( ! this.acquireCartSubmitGuard( form ) ) {
						return;
					}
					try {
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
							const res = await fetch(
								this.data.updateCartItemUrl,
								{
									method: 'POST',
									headers: this.restHeaders( {
										'Content-Type': 'application/json',
									} ),
									body: JSON.stringify( {
										cart_key: this.cartKey,
										designId: this.data.designId,
										layers,
										uploadToken:
											this.data.requestToken || '',
										designVariant:
											this.selectedDesignVariant || '',
										designVariantLabel:
											this.designVariants.find(
												( item ) =>
													item.id ===
													this.selectedDesignVariant
											)?.label || '',
										snapshots,
										previewUrl: this._previewUrl || '',
									} ),
								}
							);
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
							console.error(
								'[OC] Update cart item failed:',
								err
							);
							this.renderPreflightMessages(
								[
									'Failed to update customisation. Please try again.',
								],
								[]
							);
						}
					} finally {
						this.resetCartSubmitState( form );
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
					if ( this.handleVariationSubmitBlock() ) {
						e.preventDefault();
						e.stopImmediatePropagation();
						return;
					}
					if ( ! this._customisationActive ) {
						this.updateHiddenField();
						return;
					}
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
					return; // preview already saved, let submit through
				}
				e.preventDefault();
				e.stopImmediatePropagation();
				if ( ! this.acquireCartSubmitGuard( form ) ) {
					return;
				}
				try {
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
						const proceed = window.confirm(
							QUALITY_WARNING_MESSAGE
						);
						if ( ! proceed ) {
							this.resetCartSubmitState( form );
							return;
						}
					}

					const acceptedPreview =
						await this.confirmMobileCartPreview();
					if ( ! acceptedPreview ) {
						this.resetCartSubmitState( form );
						return;
					}

					await this.uploadPreview();
					await this.updateHiddenField( true );
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
		form._ocGuardFromClick = false;
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

	async getMobileCartPreviewAreas() {
		const activeArea = this.activeArea;
		const fallbackUrl = this.getCurrentPreviewDataUrl();
		const previews = [];
		for ( let index = 0; index < this.areas.length; index++ ) {
			await this.redraw( index, { pushGallery: false } );
			const canvas = this.canvases[ index ];
			if ( ! canvas ) {
				continue;
			}
			try {
				previews.push( {
					index,
					label: this.areas[ index ]?.name || `Area ${ index + 1 }`,
					url: canvas.toDataURL( { format: 'jpeg', quality: 0.92 } ),
				} );
			} catch {
				// Omit an area only when its canvas cannot be safely exported.
			}
		}
		await this.redraw( activeArea );
		if ( ! previews.length && fallbackUrl ) {
			previews.push( {
				index: activeArea,
				label:
					this.areas[ activeArea ]?.name ||
					`Area ${ activeArea + 1 }`,
				url: fallbackUrl,
			} );
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
