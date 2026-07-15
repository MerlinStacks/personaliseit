/* eslint-disable no-console, @wordpress/no-unused-vars-before-return */

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
					filterFrame = window.requestAnimationFrame( () => {
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

				input.addEventListener( 'focus', () => {
					if ( input.value.trim() === selectedFontLabel() ) {
						filterOptions( '' );
					} else {
						scheduleFilterOptions();
					}
					setOpen( true );
				} );
				input.addEventListener( 'input', () => {
					scheduleFilterOptions();
					setOpen( true );
				} );
				input.addEventListener( 'search', () => {
					scheduleFilterOptions();
					setOpen( true );
				} );
				input.addEventListener( 'keydown', ( e ) => {
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
				} );
				input.addEventListener( 'blur', () => {
					window.setTimeout( () => {
						if (
							! combo.contains(
								combo.ownerDocument.activeElement
							)
						) {
							setOpen( false );
							this.updateFontCombobox( select );
						}
					}, 120 );
				} );

				options.forEach( ( option ) => {
					option.addEventListener( 'pointerdown', ( e ) => {
						e.preventDefault();
						selectFont( option.dataset.ocFontOption );
					} );
					option.addEventListener( 'click', () =>
						selectFont( option.dataset.ocFontOption )
					);
					option.addEventListener( 'keydown', ( e ) => {
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
					} );
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
		this.setupFontComboboxes();

		// Area tabs
		const areaTabs = Array.from(
			document.querySelectorAll( '.oc-area-tab' )
		);
		areaTabs.forEach( ( btn ) => {
			btn.addEventListener( 'click', () =>
				this.switchArea( parseInt( btn.dataset.areaIndex, 10 ) )
			);
			btn.addEventListener(
				'touchend',
				( e ) => {
					e.preventDefault();
					this.switchArea( parseInt( btn.dataset.areaIndex, 10 ) );
				},
				{ passive: false }
			);
			btn.addEventListener( 'keydown', ( e ) => {
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
			} );
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
			el.addEventListener( 'input', async () => {
				const cleaned = this.normaliseLayerTextValue( lid, el.value );
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
				this.requestPreviewFocus();
				this.scheduleRedraw( this.areaIndexForLayer( lid ) );
				this.updateHiddenField();
			} );
		} );

		// Spotify validation (invalid format / private playlist / unavailable).
		document
			.querySelectorAll( '[data-oc-layer-spotify]' )
			.forEach( ( el ) => {
				const lid = parseInt( el.dataset.ocLayerSpotify, 10 );
				if ( ! lid ) {
					return;
				}

				el.addEventListener( 'input', () => {
					this.invalidateSpotifyValidation( lid );
					if ( ! this.inputs[ lid ] ) {
						this.inputs[ lid ] = {};
					}
					this.inputs[ lid ].value = el.value;
					this.inputs[ lid ].spotifyStatus = '';
					this.inputs[ lid ].spotifyUri = '';
					this.syncLinkedLayerInput( lid, [
						'value',
						'spotifyStatus',
						'spotifyUri',
					] );
					this.setSpotifyError( lid, '', el );
					this.requestPreviewFocus();
					this.scheduleRedraw( this.areaIndexForLayer( lid ) );
					this.updateHiddenField();

					clearTimeout( this.spotifyValidateTimers[ lid ] );
					this.spotifyValidateTimers[ lid ] = setTimeout( () => {
						this.validateSpotifyLayer( lid, el.value, el );
					}, 450 );
				} );

				el.addEventListener( 'blur', () => {
					this.validateSpotifyLayer( lid, el.value, el );
				} );
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
				btn.addEventListener( 'click', ( e ) => {
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
				} );
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
			el.addEventListener( 'change', async () => {
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
				this.requestPreviewFocus();
				this.scheduleRedraw( this.areaIndexForLayer( lid ) );
				this.updateHiddenField();
			} );
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
				el.addEventListener( 'input', () => {
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
				} );
			} );

		// Colour swatches
		document
			.querySelectorAll( '[data-oc-layer-swatch]' )
			.forEach( ( btn ) => {
				btn.addEventListener( 'click', () => {
					const lid = parseInt( btn.dataset.ocLayerSwatch, 10 );
					if ( ! this.inputs[ lid ] ) {
						this.inputs[ lid ] = {};
					}
					this.inputs[ lid ].colorHex = btn.dataset.hex;
					if ( this.getLayerById( lid )?.type === 'lineart' ) {
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
				} );
			} );

		// Free colour picker
		document
			.querySelectorAll( '[data-oc-layer-color]' )
			.forEach( ( el ) => {
				const lid = parseInt( el.dataset.ocLayerColor, 10 );
				el.addEventListener( 'input', () => {
					if ( ! this.inputs[ lid ] ) {
						this.inputs[ lid ] = {};
					}
					this.inputs[ lid ].colorHex = el.value;
					if ( this.getLayerById( lid )?.type === 'lineart' ) {
						this.syncLinkedLayerInput( lid, [ 'colorHex' ] );
					}
					this.requestPreviewFocus();
					this.scheduleRedraw( this.areaIndexForLayer( lid ) );
					this.updateHiddenField();
				} );
			} );

		// Clipart items
		document
			.querySelectorAll( '[data-oc-layer-image-filter]' )
			.forEach( ( el ) => {
				const lid = parseInt( el.dataset.ocLayerImageFilter, 10 );
				if ( ! this.inputs[ lid ] ) {
					this.inputs[ lid ] = {};
				}
				this.inputs[ lid ].imageFilterId =
					parseInt( el.value, 10 ) || 0;
				el.addEventListener( 'change', () => {
					if ( ! this.inputs[ lid ] ) {
						this.inputs[ lid ] = {};
					}
					this.inputs[ lid ].imageFilterId =
						parseInt( el.value, 10 ) || 0;
					this.syncLinkedLayerInput( lid, [ 'imageFilterId' ] );
					this.requestPreviewFocus();
					this.scheduleRedraw( this.areaIndexForLayer( lid ) );
					this.updateHiddenField();
				} );
			} );

		// Clipart items
		document
			.querySelectorAll( '[data-oc-layer-clipart]' )
			.forEach( ( btn ) => {
				btn.addEventListener( 'click', () => {
					const lid = parseInt( btn.dataset.ocLayerClipart, 10 );
					if ( ! this.inputs[ lid ] ) {
						this.inputs[ lid ] = {};
					}
					this.inputs[ lid ].clipartId = parseInt(
						btn.dataset.ocClipart,
						10
					);
					this.inputs[ lid ].clipartUrl = btn.dataset.ocClipartUrl;
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
						} );
					this.requestPreviewFocus();
					this.scheduleRedraw( this.areaIndexForLayer( lid ) );
					this.updateHiddenField();
				} );
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
				input.addEventListener( 'input', () => {
					this.clipartSearchTerms[ lid ] = input.value;
					clearTimeout( this.clipartSearchTimers[ lid ] );
					this.clipartSearchTimers[ lid ] = setTimeout( () => {
						this.filterClipart( lid );
					}, 200 );
				} );
			} );

		// Clipart category filter
		document
			.querySelectorAll( '[data-oc-clipart-category]' )
			.forEach( ( select ) => {
				const lid = parseInt( select.dataset.ocClipartCategory, 10 );
				this.clipartCategoryFilters[ lid ] = '';
				select.addEventListener( 'change', () => {
					this.clipartCategoryFilters[ lid ] = select.value;
					this.filterClipart( lid );
				} );
			} );

		// Dismiss resolution warning
		document
			.querySelectorAll( '.oc-resolution-warning' )
			.forEach( ( warnEl ) => {
				warnEl.addEventListener( 'click', ( e ) => {
					if (
						e.target === warnEl &&
						warnEl.classList.contains( 'oc-res-warning' )
					) {
						warnEl.style.display = 'none';
					}
				} );
			} );
	},

	getLayerById( layerId ) {
		return this.layersById[ layerId ] || null;
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
				}
			} );
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

	linkedLayerIds( sourceLayerId ) {
		const source = this.getLayerById( sourceLayerId );
		const group = String( source?.settings?.link_group || '' ).trim();
		if ( ! source || ! group ) {
			return [];
		}

		const ids = [];
		this.areas.forEach( ( area ) => {
			( area.layers || [] ).forEach( ( layer ) => {
				if (
					layer.id === sourceLayerId ||
					layer.type !== source.type
				) {
					return;
				}
				if (
					String( layer.settings?.link_group || '' ).trim() === group
				) {
					ids.push( layer.id );
				}
			} );
		} );
		return ids;
	},

	syncLinkedLayerInput( sourceLayerId, keys ) {
		const sourceInput = this.inputs[ sourceLayerId ];
		if ( ! sourceInput ) {
			return;
		}

		this.linkedLayerIds( sourceLayerId ).forEach( ( layerId ) => {
			const targetLayer = this.getLayerById( layerId );
			if (
				targetLayer?.type === 'image' &&
				targetLayer.settings?.allow_image_change === false
			) {
				return;
			}
			if (
				targetLayer?.type === 'clipart' &&
				targetLayer.settings?.allow_clipart_change === false
			) {
				return;
			}

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
		} );
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
						input.attachmentUrl ? 'uploaded' : ''
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

	updateInputsFromDOM() {
		this.syncInputsFromDOM();
		this.applyInputsToDOM();
	},

	applyInputsToDOM() {
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
					.forEach( ( s ) =>
						s.classList.toggle( 'oc-selected', s === swatch )
					);
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
					.forEach( ( i ) =>
						i.classList.toggle( 'oc-selected', i === clipartBtn )
					);
			}

			const imageFilterEl = document.querySelector(
				`[data-oc-layer-image-filter="${ layerId }"]`
			);
			if ( imageFilterEl ) {
				imageFilterEl.value = String( inp.imageFilterId || 0 );
			}
		}

		this.updateHiddenField();
		this.areas.forEach( ( _, i ) => this.redraw( i ) );
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

	switchArea( index ) {
		this.activeArea = index;
		document.querySelectorAll( '.oc-area-tab' ).forEach( ( btn, i ) => {
			btn.classList.toggle( 'oc-active', i === index );
			btn.setAttribute( 'aria-selected', i === index ? 'true' : 'false' );
			btn.setAttribute( 'tabindex', i === index ? '0' : '-1' );
		} );
		document.querySelectorAll( '.oc-area-controls' ).forEach( ( el ) => {
			const isActive = parseInt( el.dataset.areaIndex, 10 ) === index;
			el.hidden = ! isActive;
			el.setAttribute( 'aria-hidden', isActive ? 'false' : 'true' );
		} );
		this.redraw( index );
		document
			.querySelectorAll(
				'.oc-area-controls[data-area-index="' +
					index +
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
