/* eslint-disable no-console, @wordpress/no-unused-vars-before-return */

const LINKED_IMAGE_INPUT_KEYS = [
	'attachmentId',
	'attachmentUrl',
	'sourceAttachmentId',
	'sourceAttachmentUrl',
	'originalAttachmentUrl',
	'sourceOriginalAttachmentUrl',
	'artworkFileType',
	'sourceArtworkFileType',
	'previewAttachmentId',
	'sourcePreviewAttachmentId',
	'imageMeta',
	'sourceImageMeta',
	'imageFilterId',
];

const inputControlMethods = {
	// ── Input listeners ─────────────────────────────────────────────────────────

	closeFontComboboxes( resetSearch = false ) {
		document
			.querySelectorAll( '.oc-font-combobox.oc-open' )
			.forEach( ( combo ) => {
				combo.classList.remove( 'oc-open' );
				const input = combo.querySelector( '[data-oc-font-search]' );
				input?.setAttribute( 'aria-expanded', 'false' );

				if ( resetSearch ) {
					const select = document.querySelector(
						`[data-oc-layer-font="${ combo.dataset.ocFontCombobox }"]`
					);
					if ( select ) {
						this.updateFontCombobox( select );
					}
				}
			} );
	},

	updateFontCombobox( select ) {
		const lid = select?.dataset?.ocLayerFont;
		if ( ! lid ) {
			return;
		}
		const combo = document.querySelector(
			`.oc-font-combobox[data-oc-font-combobox="${ lid }"]`
		);
		const input = combo?.querySelector( '[data-oc-font-search]' );
		const options = combo?.querySelectorAll( '[data-oc-font-option]' );
		const selected = select.options[ select.selectedIndex ];
		if ( ! combo || ! input || ! selected ) {
			return;
		}

		input.value = selected.textContent.trim();
		input.style.fontFamily = selected.style.fontFamily || '';
		options?.forEach( ( option ) => {
			option.hidden = false;
			const isSelected = option.dataset.ocFontOption === select.value;
			option.setAttribute(
				'aria-selected',
				isSelected ? 'true' : 'false'
			);
		} );
		combo
			.querySelector( '[data-oc-font-empty]' )
			?.setAttribute( 'hidden', '' );
	},

	setupFontComboboxes() {
		const stateSignal = this._panelListenerController?.signal;
		const useNativeFontSelect = window.matchMedia?.(
			'(max-width: 639px) and (hover: none) and (pointer: coarse)'
		)?.matches;

		document
			.querySelectorAll( '[data-oc-layer-font]' )
			.forEach( ( select ) => {
				if ( useNativeFontSelect ) {
					select.setAttribute( 'aria-hidden', 'false' );
					select.removeAttribute( 'tabindex' );
					return;
				}

				const lid = select.dataset.ocLayerFont;
				const combo = document.querySelector(
					`.oc-font-combobox[data-oc-font-combobox="${ lid }"]`
				);
				if ( ! combo ) {
					return;
				}
				if ( combo.dataset.ocFontComboboxReady === '1' ) {
					this.updateFontCombobox( select );
					return;
				}

				const input = combo.querySelector( '[data-oc-font-search]' );
				const list = combo.querySelector( '[data-oc-font-list]' );
				const options = Array.from(
					combo.querySelectorAll( '[data-oc-font-option]' )
				);
				const empty = combo.querySelector( '[data-oc-font-empty]' );
				if ( ! input || ! list || ! options.length ) {
					return;
				}
				combo.dataset.ocFontComboboxReady = '1';
				let filterFrame = null;

				const setOpen = ( isOpen ) => {
					combo.classList.toggle( 'oc-open', isOpen );
					input.setAttribute(
						'aria-expanded',
						isOpen ? 'true' : 'false'
					);
				};

				const selectedFontLabel = () =>
					select.options[
						select.selectedIndex
					]?.textContent.trim() || '';
				const filterOptions = ( queryOverride = null ) => {
					const query = ( queryOverride ?? input.value )
						.trim()
						.toLowerCase();
					let visibleCount = 0;
					options.forEach( ( option ) => {
						const isVisible = option.textContent
							.trim()
							.toLowerCase()
							.includes( query );
						option.hidden = ! isVisible;
						if ( isVisible ) {
							visibleCount++;
						}
					} );
					if ( empty ) {
						empty.hidden = visibleCount > 0;
					}
				};
				const scheduleFilterOptions = () => {
					if ( filterFrame ) {
						window.cancelAnimationFrame( filterFrame );
					}
					filterFrame = this.requestStateAnimationFrame( () => {
						filterFrame = null;
						filterOptions();
					} );
				};
				const firstVisibleOption = () =>
					options.find( ( option ) => ! option.hidden );

				const selectFont = ( value, keepOpen = false ) => {
					select.value = value;
					select.dispatchEvent(
						new Event( 'change', { bubbles: true } )
					);
					if ( keepOpen ) {
						filterOptions( '' );
						setOpen( true );
						input.focus( { preventScroll: true } );
					} else {
						setOpen( false );
					}
				};

				this.updateFontCombobox( select );
				filterOptions();

				input.addEventListener(
					'focus',
					() => {
						if ( input.value.trim() === selectedFontLabel() ) {
							filterOptions( '' );
						} else {
							scheduleFilterOptions();
						}
						setOpen( true );
					},
					{ signal: stateSignal }
				);
				input.addEventListener(
					'input',
					() => {
						scheduleFilterOptions();
						setOpen( true );
					},
					{ signal: stateSignal }
				);
				input.addEventListener(
					'search',
					() => {
						scheduleFilterOptions();
						setOpen( true );
					},
					{ signal: stateSignal }
				);
				input.addEventListener(
					'keydown',
					( e ) => {
						if ( e.key === 'Escape' ) {
							setOpen( false );
							this.updateFontCombobox( select );
							return;
						}
						if ( e.key === 'ArrowDown' ) {
							e.preventDefault();
							filterOptions();
							setOpen( true );
							firstVisibleOption()?.focus();
							return;
						}
						if ( e.key === 'Enter' ) {
							e.preventDefault();
							filterOptions();
							const option = firstVisibleOption();
							if ( option ) {
								selectFont( option.dataset.ocFontOption );
							} else {
								setOpen( false );
								this.updateFontCombobox( select );
							}
						}
					},
					{ signal: stateSignal }
				);
				input.addEventListener(
					'blur',
					() => {
						this.setStateTimeout( () => {
							if ( stateSignal?.aborted ) {
								return;
							}
							if (
								! combo.contains(
									combo.ownerDocument.activeElement
								)
							) {
								setOpen( false );
								this.updateFontCombobox( select );
							}
						}, 120 );
					},
					{ signal: stateSignal }
				);

				options.forEach( ( option ) => {
					option.addEventListener(
						'pointerdown',
						( e ) => {
							e.preventDefault();
							selectFont( option.dataset.ocFontOption );
						},
						{ signal: stateSignal }
					);
					option.addEventListener(
						'click',
						() => selectFont( option.dataset.ocFontOption ),
						{ signal: stateSignal }
					);
					option.addEventListener(
						'keydown',
						( e ) => {
							const visible = options.filter(
								( item ) => ! item.hidden
							);
							const index = visible.indexOf( option );
							if ( e.key === 'ArrowDown' ) {
								e.preventDefault();
								visible[ index + 1 ]?.focus();
							} else if ( e.key === 'ArrowUp' ) {
								e.preventDefault();
								( visible[ index - 1 ] || input ).focus();
							} else if ( e.key === 'Escape' ) {
								setOpen( false );
								input.focus();
							}
						},
						{ signal: stateSignal }
					);
				} );
			} );

		if ( ! this.fontComboboxDocumentClickBound ) {
			this.fontComboboxDocumentClickBound = true;
			document.addEventListener( 'click', ( e ) => {
				if ( ! e.target.closest( '.oc-font-combobox' ) ) {
					this.closeFontComboboxes( true );
				}
			} );
		}
	},

	setupInputListeners() {
		const stateSignal = this._panelListenerController?.signal;
		const designGeneration = this._designGeneration;
		this.setupControlAccessibility();
		this.setupFontComboboxes();

		// Area tabs
		const areaTabs = Array.from(
			document.querySelectorAll( '.oc-area-tab' )
		);
		areaTabs.forEach( ( btn ) => {
			btn.addEventListener(
				'click',
				() => this.switchArea( parseInt( btn.dataset.areaIndex, 10 ) ),
				{ signal: stateSignal }
			);
			btn.addEventListener(
				'touchend',
				( e ) => {
					e.preventDefault();
					this.switchArea( parseInt( btn.dataset.areaIndex, 10 ) );
				},
				{ passive: false, signal: stateSignal }
			);
			btn.addEventListener(
				'keydown',
				( e ) => {
					if (
						! [ 'ArrowLeft', 'ArrowRight', 'Home', 'End' ].includes(
							e.key
						)
					) {
						return;
					}
					e.preventDefault();
					const currentIndex = areaTabs.indexOf( btn );
					let nextIndex = currentIndex;
					if ( e.key === 'ArrowLeft' ) {
						nextIndex = Math.max( 0, currentIndex - 1 );
					}
					if ( e.key === 'ArrowRight' ) {
						nextIndex = Math.min(
							areaTabs.length - 1,
							currentIndex + 1
						);
					}
					if ( e.key === 'Home' ) {
						nextIndex = 0;
					}
					if ( e.key === 'End' ) {
						nextIndex = areaTabs.length - 1;
					}
					areaTabs[ nextIndex ]?.focus();
					this.switchArea(
						parseInt(
							areaTabs[ nextIndex ]?.dataset.areaIndex || '0',
							10
						)
					);
				},
				{ signal: stateSignal }
			);
		} );

		// Text / textarea
		document.querySelectorAll( '[data-oc-layer-text]' ).forEach( ( el ) => {
			const lid = parseInt( el.dataset.ocLayerText, 10 );
			const counter = el.parentElement?.querySelector(
				`.oc-char-counter[data-oc-char-counter="${ lid }"]`
			);
			const limit =
				parseInt( counter?.dataset.charLimit, 10 ) ||
				this.charLimitForLayer( lid );
			if ( limit > 0 ) {
				el.maxLength = limit;
			}
			const updateCounter = () => {
				if ( ! counter ) {
					return;
				}
				const current = this.textLength( el.value );
				if ( limit === 0 || current <= limit ) {
					counter.style.display = 'none';
					return;
				}
				counter.textContent = `${ current } / ${ limit }`;
				counter.style.display = '';
			};
			updateCounter();
			el.addEventListener(
				'input',
				async () => {
					el.setCustomValidity( '' );
					el.setAttribute( 'aria-invalid', 'false' );
					el.classList.remove( 'oc-preflight-field-error' );
					const cleaned = this.normaliseLayerTextValue(
						lid,
						el.value
					);
					if ( cleaned !== el.value ) {
						el.value = cleaned;
					}
					if ( ! this.inputs[ lid ] ) {
						this.inputs[ lid ] = {};
					}
					this.inputs[ lid ].value = cleaned;
					this.syncLinkedLayerInput( lid, [ 'value' ] );
					updateCounter();
					await this.updateTextSizeSliderCap( lid );
					if (
						designGeneration !== this._designGeneration ||
						stateSignal?.aborted
					) {
						return;
					}
					this.requestPreviewFocus();
					this.scheduleRedraw( this.areaIndexForLayer( lid ) );
					this.updateHiddenField();
				},
				{ signal: stateSignal }
			);
		} );

		// Spotify validation (invalid format / private playlist / unavailable).
		document
			.querySelectorAll( '[data-oc-layer-spotify]' )
			.forEach( ( el ) => {
				const lid = parseInt( el.dataset.ocLayerSpotify, 10 );
				if ( ! lid ) {
					return;
				}

				el.addEventListener(
					'input',
					() => {
						el.setCustomValidity( '' );
						el.setAttribute( 'aria-invalid', 'false' );
						el.classList.remove( 'oc-preflight-field-error' );
						this.invalidateSpotifyValidation( lid );
						if ( ! this.inputs[ lid ] ) {
							this.inputs[ lid ] = {};
						}
						this.inputs[ lid ].value = el.value;
						this.inputs[ lid ].spotifyStatus = el.value.trim()
							? 'pending'
							: '';
						this.inputs[ lid ].spotifyUri = this.extractSpotifyUri(
							el.value
						);
						this.syncLinkedLayerInput( lid, [
							'value',
							'spotifyStatus',
							'spotifyUri',
						] );
						this.setSpotifyError( lid, '', el );
						this.requestPreviewFocus();
						this.scheduleRedraw( this.areaIndexForLayer( lid ) );
						this.updateHiddenField();

						this.spotifyValidateTimers[ lid ] =
							this.setStateTimeout( () => {
								delete this.spotifyValidateTimers[ lid ];
								this.validateSpotifyLayer( lid, el.value, el );
							}, 450 );
					},
					{ signal: stateSignal }
				);

				el.addEventListener(
					'blur',
					() => {
						this.clearStateTimeout(
							this.spotifyValidateTimers[ lid ]
						);
						delete this.spotifyValidateTimers[ lid ];
						this.validateSpotifyLayer( lid, el.value, el );
					},
					{ signal: stateSignal }
				);
			} );

		// Help tooltips: tap to toggle on touch devices, close on outside tap.
		const closeHelpTooltips = () => {
			document
				.querySelectorAll(
					'.oc-help-tooltip.oc-open, .oc-spotify-help.oc-open'
				)
				.forEach( ( help ) => {
					help.classList.remove( 'oc-open' );
					help.querySelector(
						'.oc-help-toggle, .oc-spotify-help-toggle'
					)?.setAttribute( 'aria-expanded', 'false' );
				} );
		};
		document
			.querySelectorAll(
				'.oc-help-toggle:not(.oc-spotify-modal-trigger), .oc-spotify-help-toggle'
			)
			.forEach( ( btn ) => {
				btn.addEventListener(
					'click',
					( e ) => {
						e.preventDefault();
						e.stopPropagation();
						const help = btn.closest(
							'.oc-help-tooltip, .oc-spotify-help'
						);
						if ( ! help ) {
							return;
						}
						const willOpen = ! help.classList.contains( 'oc-open' );
						closeHelpTooltips();
						if ( willOpen ) {
							help.classList.add( 'oc-open' );
							btn.setAttribute( 'aria-expanded', 'true' );
						}
					},
					{ signal: stateSignal }
				);
			} );
		if ( ! this.helpTooltipDocumentClickBound ) {
			this.helpTooltipDocumentClickBound = true;
			document.addEventListener( 'click', ( e ) => {
				if (
					! e.target.closest( '.oc-help-tooltip, .oc-spotify-help' )
				) {
					closeHelpTooltips();
				}
			} );
		}

		this.setupSpotifyModal();

		// Font selects — also reflect the picked font in the closed select.
		const reflectFontOnSelect = ( el ) => {
			const opt = el.options[ el.selectedIndex ];
			const fam = opt?.style?.fontFamily || '';
			if ( fam ) {
				el.style.fontFamily = fam;
			}
		};
		document.querySelectorAll( '[data-oc-layer-font]' ).forEach( ( el ) => {
			reflectFontOnSelect( el );
			this.updateFontCombobox( el );
			const lid = parseInt( el.dataset.ocLayerFont, 10 );
			const selectedFontId = parseInt( el.value, 10 ) || 0;
			if ( selectedFontId ) {
				if ( ! this.inputs[ lid ] ) {
					this.inputs[ lid ] = {};
				}
				this.inputs[ lid ].fontId = selectedFontId;
			}
			el.addEventListener(
				'change',
				async () => {
					if ( ! this.inputs[ lid ] ) {
						this.inputs[ lid ] = {};
					}
					this.inputs[ lid ].fontId = parseInt( el.value, 10 );
					const font = this.fonts.find(
						( f ) => f.id === this.inputs[ lid ].fontId
					);
					if ( font ) {
						try {
							await this.loadFont( font );
						} catch ( err ) {
							console.warn( '[OC] Font load failed:', err );
						}
						if (
							designGeneration !== this._designGeneration ||
							stateSignal?.aborted
						) {
							return;
						}
					}
					reflectFontOnSelect( el );
					this.updateFontCombobox( el );
					const preview = document.querySelector(
						`.oc-font-preview[data-oc-font-preview="${ lid }"]`
					);
					if ( preview && font ) {
						preview.style.fontFamily = font.name;
					}
					await this.updateTextSizeSliderCap( lid );
					if (
						designGeneration !== this._designGeneration ||
						stateSignal?.aborted
					) {
						return;
					}
					this.requestPreviewFocus();
					this.scheduleRedraw( this.areaIndexForLayer( lid ) );
					this.updateHiddenField();
				},
				{ signal: stateSignal }
			);
		} );

		// Font size
		document
			.querySelectorAll( '[data-oc-layer-font-size]' )
			.forEach( ( el ) => {
				const lid = parseInt( el.dataset.ocLayerFontSize, 10 );
				const valueEl = document.querySelector(
					`.oc-range-value[data-oc-range-value="${ lid }"]`
				);
				if ( ! el.dataset.ocOriginalMax ) {
					el.dataset.ocOriginalMax = el.max || '200';
				}
				const updateValue = () => {
					if ( valueEl ) {
						valueEl.textContent = el.value;
					}
				};
				updateValue();
				this.updateTextSizeSliderCap( lid );
				el.addEventListener(
					'input',
					() => {
						if ( ! this.inputs[ lid ] ) {
							this.inputs[ lid ] = {};
						}
						this.inputs[ lid ].fontSize = Math.max(
							1,
							parseInt( el.value, 10 ) || 1
						);
						updateValue();
						this.requestPreviewFocus();
						this.scheduleRedraw( this.areaIndexForLayer( lid ) );
						this.updateHiddenField();
					},
					{ signal: stateSignal }
				);
			} );

		// Colour swatches
		document
			.querySelectorAll( '[data-oc-layer-swatch]' )
			.forEach( ( btn ) => {
				btn.addEventListener(
					'click',
					() => {
						const lid = parseInt( btn.dataset.ocLayerSwatch, 10 );
						if ( ! this.inputs[ lid ] ) {
							this.inputs[ lid ] = {};
						}
						this.inputs[ lid ].colorHex = btn.dataset.hex;
						if (
							[ 'clipart', 'lineart' ].includes(
								this.getLayerById( lid )?.type
							)
						) {
							this.syncLinkedLayerInput( lid, [ 'colorHex' ] );
						}
						btn.closest( '.oc-colour-swatches' )
							?.querySelectorAll( '.oc-colour-swatch' )
							.forEach( ( s ) => {
								const isSelected = s === btn;
								s.classList.toggle( 'oc-selected', isSelected );
								s.setAttribute(
									'aria-pressed',
									isSelected ? 'true' : 'false'
								);
							} );
						this.requestPreviewFocus();
						this.scheduleRedraw( this.areaIndexForLayer( lid ) );
						this.updateHiddenField();
					},
					{ signal: stateSignal }
				);
			} );

		// Free colour picker
		document
			.querySelectorAll( '[data-oc-layer-color]' )
			.forEach( ( el ) => {
				const lid = parseInt( el.dataset.ocLayerColor, 10 );
				el.addEventListener(
					'input',
					() => {
						if ( ! this.inputs[ lid ] ) {
							this.inputs[ lid ] = {};
						}
						this.inputs[ lid ].colorHex = el.value;
						if (
							[ 'clipart', 'lineart' ].includes(
								this.getLayerById( lid )?.type
							)
						) {
							this.syncLinkedLayerInput( lid, [ 'colorHex' ] );
						}
						this.requestPreviewFocus();
						this.scheduleRedraw( this.areaIndexForLayer( lid ) );
						this.updateHiddenField();
					},
					{ signal: stateSignal }
				);
			} );

		// Image filters
		document
			.querySelectorAll( '[data-oc-layer-image-filter]' )
			.forEach( ( el ) => {
				const lid = parseInt( el.dataset.ocLayerImageFilter, 10 );
				if ( ! this.inputs[ lid ] ) {
					this.inputs[ lid ] = {};
				}
				this.inputs[ lid ].imageFilterId =
					parseInt( el.value, 10 ) || 0;
				el.addEventListener(
					'change',
					async () => {
						if ( ! this.inputs[ lid ] ) {
							this.inputs[ lid ] = {};
						}
						const filterId = parseInt( el.value, 10 ) || 0;
						el.disabled = true;
						try {
							await this.applyAiImageFilter( lid, filterId );
						} finally {
							if (
								designGeneration === this._designGeneration &&
								! stateSignal?.aborted &&
								el.isConnected
							) {
								el.disabled = this._controlLocks.size > 0;
							}
						}
						if (
							designGeneration !== this._designGeneration ||
							stateSignal?.aborted
						) {
							return;
						}
						this.requestPreviewFocus();
						this.updateHiddenField();
					},
					{ signal: stateSignal }
				);
			} );

		// Clipart items
		document
			.querySelectorAll( '[data-oc-layer-clipart]' )
			.forEach( ( btn ) => {
				btn.addEventListener(
					'click',
					() => {
						const lid = parseInt( btn.dataset.ocLayerClipart, 10 );
						if ( ! this.inputs[ lid ] ) {
							this.inputs[ lid ] = {};
						}
						this.inputs[ lid ].clipartId = parseInt(
							btn.dataset.ocClipart,
							10
						);
						this.inputs[ lid ].clipartUrl =
							btn.dataset.ocClipartUrl;
						this.inputs[ lid ].clipartRecolourable =
							btn.dataset.ocClipartRecolourable === '1';
						this.syncLinkedLayerInput( lid, [
							'clipartId',
							'clipartUrl',
							'clipartRecolourable',
						] );
						btn.closest( '.oc-clipart-grid' )
							?.querySelectorAll( '.oc-clipart-item' )
							.forEach( ( i ) => {
								const isSelected = i === btn;
								i.classList.toggle( 'oc-selected', isSelected );
								i.setAttribute(
									'aria-pressed',
									isSelected ? 'true' : 'false'
								);
								i.setAttribute(
									'aria-checked',
									isSelected ? 'true' : 'false'
								);
							} );
						this.requestPreviewFocus();
						this.scheduleRedraw( this.areaIndexForLayer( lid ) );
						this.updateHiddenField();
					},
					{ signal: stateSignal }
				);
			} );

		if ( ! this.carouselResizeBound ) {
			this.carouselResizeBound = true;
			window.addEventListener( 'resize', () => {
				this.refreshDesignVariantCarousel();
				document
					.querySelectorAll( '[data-oc-clipart-carousel]' )
					.forEach( ( carousel ) => {
						this.refreshClipartCarousel(
							parseInt( carousel.dataset.ocClipartCarousel, 10 )
						);
					} );
			} );
		}

		// Clipart search (debounced 200ms)
		document
			.querySelectorAll( '[data-oc-clipart-search]' )
			.forEach( ( input ) => {
				const lid = parseInt( input.dataset.ocClipartSearch, 10 );
				this.clipartSearchTerms[ lid ] = '';
				input.addEventListener(
					'input',
					() => {
						this.clipartSearchTerms[ lid ] = input.value;
						this.clearStateTimeout(
							this.clipartSearchTimers[ lid ]
						);
						this.clipartSearchTimers[ lid ] = this.setStateTimeout(
							() => {
								delete this.clipartSearchTimers[ lid ];
								this.filterClipart( lid );
							},
							200
						);
					},
					{ signal: stateSignal }
				);
			} );

		// Clipart category filter
		document
			.querySelectorAll( '[data-oc-clipart-category]' )
			.forEach( ( select ) => {
				const lid = parseInt( select.dataset.ocClipartCategory, 10 );
				this.clipartCategoryFilters[ lid ] = '';
				select.addEventListener(
					'change',
					() => {
						this.clipartCategoryFilters[ lid ] = select.value;
						this.filterClipart( lid );
					},
					{ signal: stateSignal }
				);
			} );

		// Dismiss resolution warning
		document
			.querySelectorAll( '.oc-resolution-warning' )
			.forEach( ( warnEl ) => {
				warnEl.addEventListener(
					'click',
					( e ) => {
						if (
							e.target === warnEl &&
							warnEl.classList.contains( 'oc-res-warning' )
						) {
							warnEl.style.display = 'none';
						}
					},
					{ signal: stateSignal }
				);
			} );
	},

	getLayerById( layerId ) {
		return this.layersById[ layerId ] || null;
	},

	ensureLayerControlHeader( layer, control, required ) {
		const section = control?.closest( '.oc-layer-section' );
		const body = control?.closest( '.oc-layer-body' );
		if ( ! section || ! body ) {
			return;
		}
		const hasHeader = Array.from( section.children ).some( ( child ) =>
			child.classList.contains( 'oc-layer-header' )
		);
		if ( hasHeader ) {
			return;
		}

		const header = document.createElement( 'div' );
		header.className = 'oc-layer-header';
		const label = document.createElement( 'span' );
		label.textContent = layer.label || 'Personalisation option';
		header.appendChild( label );
		if ( required ) {
			const requiredLabel = document.createElement( 'span' );
			requiredLabel.className = 'oc-layer-required';
			requiredLabel.textContent = '* Required';
			header.appendChild( requiredLabel );
		}
		section.insertBefore( header, body );
	},

	setupControlAccessibility() {
		this.areas.forEach( ( area ) => {
			( area.layers || [] ).forEach( ( layer ) => {
				const required = Boolean(
					layer.required || layer.settings?.required
				);
				const label = layer.label || 'Personalisation option';
				if ( [ 'text', 'textarea' ].includes( layer.type ) ) {
					const input = document.querySelector(
						`[data-oc-layer-text="${ layer.id }"]`
					);
					if ( input ) {
						input.required = required;
						input.setAttribute(
							'aria-required',
							required ? 'true' : 'false'
						);
					}
					return;
				}

				if ( [ 'image', 'clipmask' ].includes( layer.type ) ) {
					const zone = document.querySelector(
						`[data-oc-upload-zone="${ layer.id }"]`
					);
					const fallback = document.querySelector(
						`[data-oc-default-image="${ layer.id }"]`
					);
					this.ensureLayerControlHeader(
						layer,
						zone || fallback,
						required
					);
					if ( zone ) {
						zone.setAttribute( 'role', 'group' );
						zone.setAttribute( 'aria-label', label );
						zone.setAttribute(
							'aria-required',
							required ? 'true' : 'false'
						);
					}
					return;
				}

				if ( layer.type === 'clipart' ) {
					const grid = document.querySelector(
						`[data-oc-clipart-grid="${ layer.id }"]`
					);
					this.ensureLayerControlHeader( layer, grid, required );
					grid?.setAttribute( 'role', 'radiogroup' );
					grid?.setAttribute( 'aria-label', label );
					grid?.setAttribute(
						'aria-required',
						required ? 'true' : 'false'
					);
					document
						.querySelectorAll(
							`[data-oc-layer-clipart="${ layer.id }"]`
						)
						.forEach( ( option ) => {
							option.setAttribute( 'role', 'radio' );
							option.setAttribute(
								'aria-checked',
								option.classList.contains( 'oc-selected' )
									? 'true'
									: 'false'
							);
						} );
					const search = document.querySelector(
						`[data-oc-clipart-search="${ layer.id }"]`
					);
					const category = document.querySelector(
						`[data-oc-clipart-category="${ layer.id }"]`
					);
					search?.setAttribute( 'aria-label', `Search ${ label }` );
					category?.setAttribute(
						'aria-label',
						`Filter ${ label } by category`
					);
					return;
				}

				if ( layer.type === 'spotify' ) {
					const input = document.querySelector(
						`[data-oc-layer-spotify="${ layer.id }"]`
					);
					if ( input ) {
						input.required = required;
						input.setAttribute(
							'aria-required',
							required ? 'true' : 'false'
						);
						input.setAttribute( 'aria-label', label );
					}
				}
			} );
		} );
	},

	applyUploadZoneAccessibility( zone, layer ) {
		if ( ! zone || ! layer ) {
			return;
		}
		const required = Boolean( layer.required || layer.settings?.required );
		const label = layer.label || 'Upload artwork';
		zone.setAttribute( 'aria-label', label );
		zone.setAttribute( 'aria-required', required ? 'true' : 'false' );
		zone.querySelectorAll( 'input[type="file"]' ).forEach( ( input ) => {
			input.setAttribute( 'aria-label', label );
			input.setAttribute( 'aria-required', required ? 'true' : 'false' );
		} );
	},

	seedLayerFontDefaults() {
		this.areas.forEach( ( area ) => {
			( area.layers || [] ).forEach( ( layer ) => {
				if ( ! [ 'text', 'textarea' ].includes( layer.type ) ) {
					return;
				}
				if ( ! this.inputs[ layer.id ] ) {
					this.inputs[ layer.id ] = {};
				}
				const select = document.querySelector(
					`[data-oc-layer-font="${ layer.id }"]`
				);
				if ( select ) {
					const allowedIds = Array.from( select.options )
						.map( ( option ) => parseInt( option.value, 10 ) || 0 )
						.filter( Boolean );
					const configured =
						parseInt( this.inputs[ layer.id ].fontId, 10 ) ||
						parseInt( layer.settings?.default_font_id, 10 ) ||
						0;
					const selected = allowedIds.includes( configured )
						? configured
						: parseInt( select.value, 10 ) || allowedIds[ 0 ] || 0;
					this.inputs[ layer.id ].fontId = selected;
					if ( selected ) {
						select.value = String( selected );
					}
					return;
				}

				const activeIds = this.fonts.map( ( font ) =>
					Number( font.id )
				);
				const configured =
					parseInt( this.inputs[ layer.id ].fontId, 10 ) ||
					parseInt( layer.settings?.default_font_id, 10 ) ||
					0;
				this.inputs[ layer.id ].fontId = activeIds.includes(
					configured
				)
					? configured
					: activeIds[ 0 ] || 0;
			} );
		} );
	},

	seedLockedLayerDefaults() {
		this.areas.forEach( ( area ) => {
			( area.layers || [] ).forEach( ( layer ) => {
				if ( ! [ 'text', 'textarea' ].includes( layer.type ) ) {
					return;
				}
				if ( ! this.inputs[ layer.id ] ) {
					this.inputs[ layer.id ] = {};
				}
				if (
					layer.locked ||
					this.inputs[ layer.id ].value === undefined
				) {
					this.inputs[ layer.id ].value =
						layer.settings?.default_text || '';
					this.clampLayerInputValue( layer.id );
				}
			} );
		} );
	},

	seedTemplateImageDefaults() {
		document
			.querySelectorAll( '[data-oc-default-image]' )
			.forEach( ( el ) => {
				const layerId = parseInt( el.dataset.ocDefaultImage, 10 );
				const url = el.dataset.ocDefaultImageUrl || '';
				if ( ! layerId || ! url ) {
					return;
				}
				if ( ! this.inputs[ layerId ] ) {
					this.inputs[ layerId ] = {};
				}
				if ( ! this.inputs[ layerId ].attachmentUrl ) {
					this.inputs[ layerId ].attachmentId =
						parseInt( el.dataset.ocDefaultImageId, 10 ) || 0;
					this.inputs[ layerId ].attachmentUrl = url;
					this.inputs[ layerId ].sourceAttachmentId =
						this.inputs[ layerId ].attachmentId;
					this.inputs[ layerId ].sourceAttachmentUrl = url;
					this.inputs[ layerId ].originalAttachmentUrl = url;
					this.inputs[ layerId ].sourceOriginalAttachmentUrl = url;
					const extension = url.match(
						/\.([a-z0-9]+)(?:[?#]|$)/i
					)?.[ 1 ];
					if ( extension ) {
						this.inputs[ layerId ].artworkFileType =
							extension.toLowerCase();
						this.inputs[ layerId ].sourceArtworkFileType =
							extension.toLowerCase();
					}
				}
			} );
	},

	seedLinkedImageInputs() {
		const seeded = new Set();
		this.areas.forEach( ( area ) => {
			( area.layers || [] ).forEach( ( layer ) => {
				if ( ! [ 'image', 'clipmask' ].includes( layer.type ) ) {
					return;
				}
				const canonicalId = this.canonicalLinkedLayerId( layer.id );
				const members = this.linkedLayerMembers( layer.id );
				if ( members.length < 2 || seeded.has( canonicalId ) ) {
					return;
				}
				seeded.add( canonicalId );
				const source = this.inputs[ canonicalId ] || {};
				members.forEach( ( layerId ) => {
					if ( layerId === canonicalId ) {
						return;
					}
					this.inputs[ layerId ] = this.inputs[ layerId ] || {};
					LINKED_IMAGE_INPUT_KEYS.forEach( ( key ) => {
						if ( source[ key ] === undefined ) {
							delete this.inputs[ layerId ][ key ];
						} else {
							this.inputs[ layerId ][ key ] = source[ key ];
						}
					} );
				} );
			} );
		} );
	},

	isProductionImageInput( input ) {
		return Number( input?.attachmentId || 0 ) > 0;
	},

	imageLayerRequiresAttachment( layer ) {
		return Boolean(
			layer?.locked ||
				layer?.required ||
				layer?.settings?.required ||
				layer?.settings?.allow_image_change === false
		);
	},

	charLimitForLayer( layerId ) {
		return Math.max(
			0,
			parseInt(
				this.getLayerById( layerId )?.settings?.char_limit,
				10
			) || 0
		);
	},

	textLength( value ) {
		return Array.from( String( value || '' ) ).length;
	},

	truncateText( value, limit ) {
		const text = String( value || '' );
		return limit > 0 && this.textLength( text ) > limit
			? Array.from( text ).slice( 0, limit ).join( '' )
			: text;
	},

	printMethodForLayer( layerId ) {
		const area = this.areas[ this.areaIndexForLayer( layerId ) ];
		return String( area?.printMethod || '' );
	},

	isThreadOrEngravingLayer( layerId ) {
		return [ 'engraving', 'embroidery' ].includes(
			this.printMethodForLayer( layerId )
		);
	},

	stripUnsupportedPrintEmoji( value ) {
		return String( value || '' )
			.replace(
				/[\u{1F000}-\u{1FAFF}\u{2600}-\u{27BF}][\u{FE0E}\u{FE0F}]?/gu,
				''
			)
			.replace(
				/[\u{1F3FB}-\u{1F3FF}\u{1F9B0}-\u{1F9B3}\u{200D}\u{FE0E}\u{FE0F}]/gu,
				''
			);
	},

	normaliseLayerTextValue( layerId, value ) {
		let text = String( value || '' );
		if ( this.isThreadOrEngravingLayer( layerId ) ) {
			text = this.stripUnsupportedPrintEmoji( text );
		}

		const limit = this.charLimitForLayer( layerId );
		return limit > 0 ? this.truncateText( text, limit ) : text;
	},

	clampLayerInputValue( layerId ) {
		if ( this.inputs[ layerId ]?.value !== undefined ) {
			this.inputs[ layerId ].value = this.normaliseLayerTextValue(
				layerId,
				this.inputs[ layerId ].value
			);
		}
	},

	isLinkedLayerEligible( layer ) {
		if ( ! layer || layer.visible === false || layer.locked ) {
			return false;
		}
		if ( [ 'image', 'clipmask' ].includes( layer.type ) ) {
			return layer.settings?.allow_image_change !== false;
		}
		if ( layer.type === 'clipart' ) {
			return layer.settings?.allow_clipart_change !== false;
		}
		return true;
	},

	linkedLayerMembers( sourceLayerId ) {
		const source = this.getLayerById( sourceLayerId );
		const group = String( source?.settings?.link_group || '' ).trim();
		if ( ! source || ! group ) {
			return this.isLinkedLayerEligible( source ) ? [ source.id ] : [];
		}

		const members = [];
		this.areas.forEach( ( area ) => {
			( area.layers || [] ).forEach( ( layer ) => {
				if ( layer.type !== source.type ) {
					return;
				}
				if (
					String( layer.settings?.link_group || '' ).trim() ===
						group &&
					this.isLinkedLayerEligible( layer )
				) {
					members.push( layer.id );
				}
			} );
		} );
		return members;
	},

	linkedLayerIds( sourceLayerId ) {
		return this.linkedLayerMembers( sourceLayerId ).filter(
			( layerId ) => Number( layerId ) !== Number( sourceLayerId )
		);
	},

	syncLinkedImageInput( sourceLayerId ) {
		this.syncLinkedLayerInput( sourceLayerId, LINKED_IMAGE_INPUT_KEYS );
	},

	canonicalLinkedLayerId( layerId ) {
		const layer = this.getLayerById( layerId );
		const group = String( layer?.settings?.link_group || '' ).trim();
		if ( ! layer || ! group ) {
			return layerId;
		}

		return this.linkedLayerMembers( layerId )[ 0 ] || layerId;
	},

	syncLinkedLayerInput( sourceLayerId, keys ) {
		const sourceInput = this.inputs[ sourceLayerId ];
		if ( ! sourceInput ) {
			return;
		}
		const sourceLayer = this.getLayerById( sourceLayerId );
		if ( ! this.isLinkedLayerEligible( sourceLayer ) ) {
			return;
		}

		const targetAreaIndexes = new Set();
		this.linkedLayerIds( sourceLayerId ).forEach( ( layerId ) => {
			if ( ! this.inputs[ layerId ] ) {
				this.inputs[ layerId ] = {};
			}
			keys.forEach( ( key ) => {
				if ( sourceInput[ key ] === undefined ) {
					delete this.inputs[ layerId ][ key ];
				} else {
					this.inputs[ layerId ][ key ] = sourceInput[ key ];
				}
			} );
			this.clampLayerInputValue( layerId );
			this.updateLinkedLayerControls( layerId, keys );
			targetAreaIndexes.add( this.areaIndexForLayer( layerId ) );
		} );
		targetAreaIndexes.forEach( ( areaIndex ) =>
			this.scheduleRedraw( areaIndex )
		);
	},

	updateLinkedLayerControls( layerId, keys ) {
		const input = this.inputs[ layerId ] || {};
		if ( keys.includes( 'value' ) ) {
			document
				.querySelectorAll(
					`[data-oc-layer-text="${ layerId }"], [data-oc-layer-spotify="${ layerId }"]`
				)
				.forEach( ( el ) => {
					el.value = input.value || '';
				} );
			this.updateTextSizeSliderCap( layerId );
			const counter = document.querySelector(
				`.oc-char-counter[data-oc-char-counter="${ layerId }"]`
			);
			if ( counter ) {
				const limit =
					parseInt( counter.dataset.charLimit, 10 ) ||
					this.charLimitForLayer( layerId );
				const current = this.textLength( input.value || '' );
				counter.textContent = `${ current } / ${ limit }`;
				counter.style.display =
					limit > 0 && current > limit ? '' : 'none';
			}
		}
		if ( keys.includes( 'colorHex' ) ) {
			document
				.querySelectorAll( `[data-oc-layer-swatch="${ layerId }"]` )
				.forEach( ( swatch ) => {
					const isSelected = swatch.dataset.hex === input.colorHex;
					swatch.classList.toggle( 'oc-selected', isSelected );
					swatch.setAttribute(
						'aria-pressed',
						isSelected ? 'true' : 'false'
					);
				} );
			const colorEl = document.querySelector(
				`[data-oc-layer-color="${ layerId }"]`
			);
			if ( colorEl && input.colorHex ) {
				colorEl.value = input.colorHex;
			}
		}
		if ( keys.includes( 'clipartId' ) ) {
			document
				.querySelectorAll( `[data-oc-layer-clipart="${ layerId }"]` )
				.forEach( ( item ) => {
					const isSelected =
						Number( item.dataset.ocClipart ) ===
						Number( input.clipartId );
					item.classList.toggle( 'oc-selected', isSelected );
					item.setAttribute(
						'aria-pressed',
						isSelected ? 'true' : 'false'
					);
					item.setAttribute(
						'aria-checked',
						isSelected ? 'true' : 'false'
					);
				} );
		}
		if (
			keys.includes( 'attachmentId' ) ||
			keys.includes( 'attachmentUrl' )
		) {
			document
				.querySelectorAll( `[data-oc-upload-zone="${ layerId }"]` )
				.forEach( ( zone ) => {
					this.setUploadZoneState(
						zone,
						this.isProductionImageInput( input ) ? 'uploaded' : ''
					);
				} );
		}
		if ( keys.includes( 'imageFilterId' ) ) {
			document
				.querySelectorAll(
					`[data-oc-layer-image-filter="${ layerId }"]`
				)
				.forEach( ( select ) => {
					select.value = String( input.imageFilterId || 0 );
				} );
		}
	},

	// ── Form submit — upload preview then proceed ──────────────────────────────

	applyInputsToDOM( { redraw = true } = {} ) {
		for ( const layerIdStr in this.inputs ) {
			const layerId = parseInt( layerIdStr, 10 );
			const inp = this.inputs[ layerId ];
			if ( ! inp ) {
				continue;
			}

			const textEl = document.querySelector(
				`[data-oc-layer-text="${ layerId }"]`
			);
			if ( textEl && inp.value !== undefined ) {
				this.clampLayerInputValue( layerId );
				textEl.value = inp.value;
			}

			const fontEl = document.querySelector(
				`[data-oc-layer-font="${ layerId }"]`
			);
			if ( fontEl && inp.fontId ) {
				fontEl.value = inp.fontId;
				this.updateFontCombobox( fontEl );
			}

			const swatch = document.querySelector(
				`[data-oc-layer-swatch="${ layerId }"][data-hex="${ inp.colorHex }"]`
			);
			if ( swatch ) {
				swatch
					.closest( '.oc-colour-swatches' )
					?.querySelectorAll( '.oc-colour-swatch' )
					.forEach( ( s ) => {
						const selected = s === swatch;
						s.classList.toggle( 'oc-selected', selected );
						s.setAttribute(
							'aria-pressed',
							selected ? 'true' : 'false'
						);
					} );
			}

			const colorEl = document.querySelector(
				`[data-oc-layer-color="${ layerId }"]`
			);
			if ( colorEl && inp.colorHex ) {
				colorEl.value = inp.colorHex;
			}

			const sizeEl = document.querySelector(
				`[data-oc-layer-font-size="${ layerId }"]`
			);
			if ( sizeEl && inp.fontSize ) {
				sizeEl.value = inp.fontSize;
				document
					.querySelector(
						`.oc-range-value[data-oc-range-value="${ layerId }"]`
					)
					?.replaceChildren(
						document.createTextNode( sizeEl.value )
					);
			}

			const clipartBtn = document.querySelector(
				`[data-oc-layer-clipart="${ layerId }"][data-oc-clipart="${ inp.clipartId }"]`
			);
			if ( clipartBtn ) {
				clipartBtn
					.closest( '.oc-clipart-grid' )
					?.querySelectorAll( '.oc-clipart-item' )
					.forEach( ( i ) => {
						const selected = i === clipartBtn;
						i.classList.toggle( 'oc-selected', selected );
						i.setAttribute(
							'aria-pressed',
							selected ? 'true' : 'false'
						);
						i.setAttribute(
							'aria-checked',
							selected ? 'true' : 'false'
						);
					} );
			}

			const imageFilterEl = document.querySelector(
				`[data-oc-layer-image-filter="${ layerId }"]`
			);
			if ( imageFilterEl ) {
				imageFilterEl.value = String( inp.imageFilterId || 0 );
			}

			document
				.querySelectorAll( `[data-oc-upload-zone="${ layerId }"]` )
				.forEach( ( zone ) => {
					this.setUploadZoneState(
						zone,
						this.isProductionImageInput( inp ) ? 'uploaded' : ''
					);
				} );
		}

		this.updateHiddenField();
		if ( redraw ) {
			this.areas.forEach( ( _, i ) => this.redraw( i ) );
		}
	},

	syncInputsFromDOM() {
		this.areas.forEach( ( area ) => {
			( area.layers || [] ).forEach( ( layer ) => {
				const layerId = layer.id;
				if ( ! this.inputs[ layerId ] ) {
					this.inputs[ layerId ] = {};
				}
				const input = this.inputs[ layerId ];

				const textEl = document.querySelector(
					`[data-oc-layer-text="${ layerId }"]`
				);
				if ( textEl ) {
					const limit = this.charLimitForLayer( layerId );
					input.value =
						limit > 0
							? this.truncateText( textEl.value, limit )
							: textEl.value;
				}

				const spotifyEl = document.querySelector(
					`[data-oc-layer-spotify="${ layerId }"]`
				);
				if ( spotifyEl ) {
					input.value = spotifyEl.value;
				}

				const fontEl = document.querySelector(
					`[data-oc-layer-font="${ layerId }"]`
				);
				if ( fontEl ) {
					input.fontId = parseInt( fontEl.value, 10 ) || 0;
				}

				const sizeEl = document.querySelector(
					`[data-oc-layer-font-size="${ layerId }"]`
				);
				if ( sizeEl ) {
					input.fontSize = Math.max(
						1,
						parseInt( sizeEl.value, 10 ) || 1
					);
				}

				const colorEl = document.querySelector(
					`[data-oc-layer-color="${ layerId }"]`
				);
				if ( colorEl ) {
					input.colorHex = colorEl.value;
				} else {
					const selectedSwatch = document.querySelector(
						`[data-oc-layer-swatch="${ layerId }"].oc-selected`
					);
					if ( selectedSwatch?.dataset.hex ) {
						input.colorHex = selectedSwatch.dataset.hex;
					}
				}

				const selectedClipart = document.querySelector(
					`[data-oc-layer-clipart="${ layerId }"].oc-selected`
				);
				if ( selectedClipart ) {
					input.clipartId =
						parseInt( selectedClipart.dataset.ocClipart, 10 ) || 0;
					input.clipartUrl =
						selectedClipart.dataset.ocClipartUrl || '';
					input.clipartRecolourable =
						selectedClipart.dataset.ocClipartRecolourable === '1';
				}

				const imageFilterEl = document.querySelector(
					`[data-oc-layer-image-filter="${ layerId }"]`
				);
				if ( imageFilterEl ) {
					input.imageFilterId =
						parseInt( imageFilterEl.value, 10 ) || 0;
				}
			} );
		} );

		this.updateHiddenField();
	},

	applyActiveAreaState( index ) {
		this.activeArea = Math.max(
			0,
			Math.min(
				Math.max( 0, this.areas.length - 1 ),
				Number( index ) || 0
			)
		);
		document.querySelectorAll( '.oc-area-tab' ).forEach( ( btn, i ) => {
			btn.classList.toggle( 'oc-active', i === this.activeArea );
			btn.setAttribute(
				'aria-selected',
				i === this.activeArea ? 'true' : 'false'
			);
			btn.setAttribute( 'tabindex', i === this.activeArea ? '0' : '-1' );
		} );
		document.querySelectorAll( '.oc-area-controls' ).forEach( ( el ) => {
			const isActive =
				parseInt( el.dataset.areaIndex, 10 ) === this.activeArea;
			el.hidden = ! isActive;
			el.setAttribute( 'aria-hidden', isActive ? 'false' : 'true' );
		} );
	},

	switchArea( index ) {
		this.applyActiveAreaState( index );
		this.redraw( this.activeArea );
		document
			.querySelectorAll(
				'.oc-area-controls[data-area-index="' +
					this.activeArea +
					'"] [data-oc-clipart-carousel]'
			)
			.forEach( ( carousel ) => {
				this.refreshClipartCarousel(
					parseInt( carousel.dataset.ocClipartCarousel, 10 )
				);
			} );

		if ( window.innerWidth < 640 ) {
			const activeTab = document.querySelector(
				`.oc-area-tab[aria-selected="true"]`
			);
			if ( activeTab ) {
				activeTab.scrollIntoView( {
					behavior: 'smooth',
					block: 'nearest',
					inline: 'center',
				} );
			}
		}
	},
};

export default inputControlMethods;
