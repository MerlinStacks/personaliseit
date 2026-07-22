/* eslint-disable no-console, no-undef, no-unused-vars, no-nested-ternary, @wordpress/no-unused-vars-before-return */

import {
	StaticCanvas,
	FabricImage,
	FabricText,
	Textbox,
	Rect,
	Circle,
	Shadow,
	Pattern,
	filters as FabricFilters,
} from 'fabric';

import {
	displayBounds,
	displayFontSize,
	displayLayer,
} from '../../shared/render-math';

const canvasRendererMethods = {
	// ── Canvas initialisation ──────────────────────────────────────────────────

	startCanvasInitialisation() {
		const generation = this._designGeneration;
		this._canvasReadyGeneration = generation;
		const task = this.initAllCanvases( generation );
		this._canvasReadyPromise = task;
		return task;
	},

	async awaitCanvasReady( generation = this._designGeneration ) {
		const task = this._canvasReadyPromise;
		if ( ! task || this._canvasReadyGeneration !== generation ) {
			throw new Error( 'The customisation preview is not ready.' );
		}
		await task;
		if (
			generation !== this._designGeneration ||
			this._canvasReadyGeneration !== generation
		) {
			throw new Error( 'The selected design changed while rendering.' );
		}
		if ( this.areas.some( ( _, index ) => ! this.canvases[ index ] ) ) {
			throw new Error( 'The customisation preview is not ready.' );
		}
	},

	async initAllCanvases( designGeneration = this._designGeneration ) {
		for ( let i = 0; i < this.areas.length; i++ ) {
			if ( designGeneration !== this._designGeneration ) {
				return;
			}
			const el = document.getElementById( `oc-canvas-${ i }` );
			if ( el ) {
				await this.initCanvas( el, i, designGeneration );
				if ( designGeneration !== this._designGeneration ) {
					return;
				}
				// Full redraw AFTER init picks up any text the user already typed.
				await this.redraw( i );
			}
		}
	},

	async initCanvas(
		canvasEl,
		areaIndex,
		designGeneration = this._designGeneration
	) {
		const area = this.areas[ areaIndex ];
		const bounds = this.areaBounds( area );

		// Use mockup natural width when available (works even when canvas is visually hidden).
		// Cap at 1200px for performance; fall back to element width or 600px.
		await new Promise( ( r ) => requestAnimationFrame( r ) );
		if ( designGeneration !== this._designGeneration ) {
			return;
		}
		const displayW = area.mockupW
			? Math.min( area.mockupW, 1200 )
			: Math.max( canvasEl.parentElement?.offsetWidth || 0, 600 );

		if ( ! area.mockupUrl ) {
			this.canvases[ areaIndex ] = this.blankCanvas(
				canvasEl,
				displayW,
				240,
				'No mockup set. Add one in the Design Editor.'
			);
			this.canvases[ areaIndex ]._ocMissingMockup = true;
			return;
		}

		let mockupImg;
		try {
			// Do NOT use crossOrigin:'anonymous' — WordPress uploads are same-origin
			// and CORS headers aren't sent, which would taint the canvas and break toDataURL.
			mockupImg = await this.loadFabricImage( area.mockupUrl, {}, 10000 );
		} catch ( e ) {
			if ( designGeneration !== this._designGeneration ) {
				return;
			}
			console.warn(
				'[OC] Mockup failed to load:',
				area.mockupUrl,
				e.message
			);
			this.canvases[ areaIndex ] = this.blankCanvas(
				canvasEl,
				displayW,
				240,
				'Mockup image could not load.'
			);
			this.canvases[ areaIndex ]._ocMissingMockup = true;
			return;
		}

		const mockupEl = mockupImg.getElement?.();
		const sourceW =
			mockupEl?.naturalWidth || mockupImg.width || area.mockupW || 1;
		const sourceH =
			mockupEl?.naturalHeight || mockupImg.height || area.mockupH || 1;
		const coordW = area.mockupW || sourceW;
		const coordH = area.mockupH || sourceH;
		const scaleX = displayW / coordW;
		const displayH = Math.round( coordH * scaleX );
		const canvas = new StaticCanvas( canvasEl, {
			width: displayW,
			height: displayH,
		} );
		if ( designGeneration !== this._designGeneration ) {
			canvas.dispose();
			return;
		}

		mockupImg.set( {
			left: 0,
			top: 0,
			originX: 'left',
			originY: 'top',
			scaleX: displayW / sourceW,
			scaleY: displayH / sourceH,
			selectable: false,
			evented: false,
		} );
		canvas.add( mockupImg );

		canvas._ocScaleX = scaleX;
		canvas._ocArea = area;
		canvas.renderAll();
		this.canvases[ areaIndex ] = canvas;
	},

	async loadFabricImage( url, options = {}, timeoutMs = 10000 ) {
		const request = this.createStateAbortController( timeoutMs );
		try {
			return await FabricImage.fromURL( url, {
				...options,
				signal: request.controller.signal,
			} );
		} catch ( error ) {
			if ( request.timedOut() ) {
				throw new Error( 'Image load timed out.' );
			}
			throw error;
		} finally {
			request.release();
		}
	},

	areaBounds( area ) {
		return {
			...( area?.bounds || {} ),
			unit: area?.bounds?.unit || area?.unit || 'px',
		};
	},

	areaCanvasGroupIndexes( areaIndex ) {
		const area = this.areas[ areaIndex ];
		const mockupUrl = area?.mockupUrl || '';
		if ( ! mockupUrl ) {
			return [ areaIndex ];
		}

		return this.areas
			.map( ( candidate, index ) =>
				( candidate?.mockupUrl || '' ) === mockupUrl ? index : -1
			)
			.filter( ( index ) => index >= 0 );
	},

	async rebuildCanvas( areaIndex ) {
		const oldCanvas = this.canvases[ areaIndex ];
		if ( oldCanvas?.dispose ) {
			oldCanvas.dispose();
		}
		delete this.canvases[ areaIndex ];

		const oldEl = document.getElementById( `oc-canvas-${ areaIndex }` );
		if ( ! oldEl ) {
			return;
		}

		const canvasEl = document.createElement( 'canvas' );
		canvasEl.id = oldEl.id;
		oldEl.replaceWith( canvasEl );

		await this.initCanvas( canvasEl, areaIndex );
		await this.redraw( areaIndex );
	},

	blankCanvas( el, w, h, msg ) {
		const c = new StaticCanvas( el, {
			width: w,
			height: h,
			backgroundColor: '#f0f0f0',
		} );
		const t = new FabricText( msg, {
			left: w / 2,
			top: h / 2,
			originX: 'center',
			originY: 'center',
			fontSize: 12,
			fill: '#888',
			fontFamily: 'sans-serif',
			textAlign: 'center',
			selectable: false,
		} );
		c.add( t );
		c.renderAll();
		c._ocScaleX = 1;
		return c;
	},

	// ── Redraw ──────────────────────────────────────────────────────────────────

	areaIndexForLayer( layerId ) {
		for ( let i = 0; i < this.areas.length; i++ ) {
			if (
				( this.areas[ i ]?.layers || [] ).some(
					( layer ) =>
						parseInt( layer.id, 10 ) === parseInt( layerId, 10 )
				)
			) {
				return i;
			}
		}
		return this.activeArea;
	},

	focusPreviewArea( areaIndex ) {
		const index = Number.isInteger( areaIndex )
			? areaIndex
			: this.activeArea;
		this.applyActiveAreaState( index );
	},

	scheduleRedraw( areaIndex = this.activeArea ) {
		clearTimeout( this._redrawTimers[ areaIndex ] );
		this._redrawTimers[ areaIndex ] = setTimeout(
			() => this.redraw( areaIndex ),
			120
		);
	},

	async flushRedraw( inputs = this.inputs, options = {} ) {
		Object.values( this._redrawTimers ).forEach( clearTimeout );
		this._redrawTimers = {};
		Object.keys( this._redrawGenerations ).forEach( ( areaIndex ) => {
			this._redrawGenerations[ areaIndex ] += 1;
		} );
		await Promise.all( Object.values( this._redrawPromises ) );
		await this.awaitCanvasReady();
		await Promise.all(
			this.areas.map( ( _, areaIndex ) =>
				this.redraw( areaIndex, {
					...options,
					inputs,
					pushGallery:
						options.pushGallery !== false &&
						this.areaCanvasGroupIndexes( areaIndex ).includes(
							this.activeArea
						),
				} )
			)
		);
	},

	redraw( areaIndex, options = {} ) {
		const canvas = this.canvases[ areaIndex ];
		if ( ! canvas ) {
			return Promise.resolve();
		} // canvas not ready yet — will redraw after initCanvas
		const generation = ( this._redrawGenerations[ areaIndex ] || 0 ) + 1;
		this._redrawGenerations[ areaIndex ] = generation;
		const isCurrent = () =>
			this._redrawGenerations[ areaIndex ] === generation &&
			this.canvases[ areaIndex ] === canvas &&
			this._customisationActive;

		const task = ( async () => {
			canvas._ocRenderErrors = [];
			[ ...canvas.getObjects() ]
				.filter( ( o ) => o._ocContent === true )
				.forEach( ( o ) => canvas.remove( o ) );

			const groupIndexes =
				options.renderGroup === false
					? [ areaIndex ]
					: this.areaCanvasGroupIndexes( areaIndex );
			for ( const groupIndex of groupIndexes ) {
				const area = this.areas[ groupIndex ];
				for ( const layer of area?.layers ?? [] ) {
					if ( ! isCurrent() ) {
						return;
					}
					try {
						await this.renderLayer(
							canvas,
							layer,
							options.inputs?.[ layer.id ] ||
								this.inputs[ layer.id ] ||
								{},
							area,
							isCurrent
						);
					} catch ( err ) {
						canvas._ocRenderErrors.push( {
							layerId: layer?.id,
							message: err?.message || 'Layer render failed.',
						} );
						console.warn(
							'[OC] Layer render failed:',
							layer?.id,
							err
						);
					}
				}
			}

			if ( ! isCurrent() ) {
				return;
			}
			canvas.renderAll();
			canvas._ocCartPreviewRevision = '';
			canvas._ocCartPreviewDataUrl = '';
			if (
				options.pushGallery !== false &&
				this.areaCanvasGroupIndexes( areaIndex ).includes(
					this.activeArea
				) &&
				! canvas._ocMissingMockup
			) {
				this.pushToGallery( canvas );
			}
		} )();
		this._redrawPromises[ areaIndex ] = task;
		return task.finally( () => {
			if ( this._redrawPromises[ areaIndex ] === task ) {
				delete this._redrawPromises[ areaIndex ];
			}
		} );
	},

	async renderLayer( canvas, layer, input, area, isCurrent = () => true ) {
		if ( ! isCurrent() ) {
			return;
		}
		const scale = canvas._ocScaleX ?? 1;
		const areaBounds = this.areaBounds( area );
		const bounds = displayBounds( areaBounds );
		const layerBox = displayLayer( layer, areaBounds );
		const rotation = Number( bounds.rotation ) || 0;
		const contentClip = () =>
			this.printAreaClipPath( bounds, scale, layerBox );
		const center = this.rotatedLayerCenter( layerBox, bounds, rotation );
		const lx = ( center.x - layerBox.w / 2 ) * scale;
		const ly = ( center.y - layerBox.h / 2 ) * scale;
		const lw = Math.max( layerBox.w * scale, 10 );
		const lh = Math.max( layerBox.h * scale, 10 );
		const textClip = ( pad = 0 ) =>
			this.rectClipPath(
				lx - pad,
				ly - pad,
				lw + pad * 2,
				lh + pad * 2,
				rotation
			);
		const lcX = center.x * scale;
		const lcY = center.y * scale;
		const isEngraving = area?.printMethod === 'engraving';
		const isEmbroidery = area?.printMethod === 'embroidery';
		const engravingPalette = this.engravingPalette(
			area?.engravingMaterial
		);
		const fontLimit = ( value ) => this.fontLimit( value );
		const clampFontSize = ( size, settings ) => {
			const minLimit = fontLimit( settings?.min_font_size );
			const maxLimit = fontLimit( settings?.max_font_size );
			const min = minLimit
				? displayFontSize( minLimit, areaBounds, scale )
				: 0;
			const max = maxLimit
				? displayFontSize( maxLimit, areaBounds, scale )
				: 0;
			if ( max && ( ! min || min <= max ) ) {
				size = Math.min( size, max );
			}
			if ( min ) {
				size = Math.max( size, min );
			}
			return size;
		};

		switch ( layer.type ) {
			case 'text':
			case 'textarea': {
				const isSingleLineText = layer.type === 'text';
				let inputValue = input.value;
				if ( inputValue === undefined ) {
					inputValue = layer.locked
						? layer.settings?.default_text || ''
						: '';
				}
				const normalisedText = (
					isEngraving || isEmbroidery
						? this.stripUnsupportedPrintEmoji( inputValue )
						: inputValue || ''
				).replace( /\r\n?/g, '\n' );
				const raw = isSingleLineText
					? normalisedText.trim()
					: normalisedText;
				if ( ! raw.trim() ) {
					break;
				}
				const lineAlign = [ 'top', 'center', 'bottom' ].includes(
					layer.settings?.line_alignment
				)
					? layer.settings.line_alignment
					: 'top';

				let font = this.fonts.find(
					( f ) =>
						f.id ===
						( input.fontId || layer.settings?.default_font_id || 0 )
				);
				// Engraving colour follows the substrate rather than the customer's ink colour.
				const color = isEngraving
					? engravingPalette.text
					: input.colorHex ||
					  layer.settings?.default_color ||
					  '#000000';
				const align = layer.settings?.alignment || 'center';
				const anchorPad = Math.max( 2, Math.min( 10, lw * 0.01 ) );
				if ( font ) {
					try {
						await this.loadFont( font );
					} catch ( err ) {
						console.warn(
							'[OC] Font load failed, falling back to sans-serif:',
							err
						);
						font = null;
					}
				}
				if ( ! isCurrent() ) {
					return;
				}

				const minLimit = fontLimit( layer.settings?.min_font_size );
				const minFontSize = minLimit
					? displayFontSize( minLimit, areaBounds, scale )
					: 0;
				const configuredFontSize =
					input.fontSize || layer.settings?.default_font_size;
				let fontSize = configuredFontSize
					? clampFontSize(
							displayFontSize(
								parseInt( configuredFontSize, 10 ),
								areaBounds,
								scale
							),
							layer.settings
					  )
					: clampFontSize(
							Math.max( 10, Math.round( lh * 0.42 ) ),
							layer.settings
					  );
				let textPadding = this.textRenderPadding( fontSize );
				const textFill = isEmbroidery
					? this.embroideryPattern( color, fontSize )
					: isEngraving && engravingPalette.pattern === 'wood'
					? this.woodEngravingPattern( fontSize )
					: isEngraving && engravingPalette.pattern === 'leather'
					? this.leatherEngravingPattern( fontSize )
					: color;
				const textClass = isSingleLineText ? FabricText : Textbox;
				const textBoxSize = isSingleLineText ? {} : { width: lw };
				const singleLineMaxWidth = Math.max( 1, lw - anchorPad * 2 );
				const singleLineMaxHeight = Math.max( 1, lh );
				const obj = new textClass( raw, {
					left: lcX,
					top: lcY,
					originX: 'center',
					originY: 'center',
					...textBoxSize,
					padding: textPadding,
					angle: rotation,
					fontFamily: font?.name || 'sans-serif',
					fontWeight: font?.weight || 'normal',
					fontStyle: font?.style || 'normal',
					fontSize,
					fill: textFill,
					textAlign: align,
					selectable: false,
					evented: false,
					objectCaching: false,
				} );
				obj._ocContent = true; // tag after creation
				let stitchPad = null;
				let stitchLift = null;
				const textareaPosition = ( target, extraX = 0, extraY = 0 ) => {
					if ( isSingleLineText || ! target ) {
						return;
					}

					target.initDimensions?.();
					const contentH = Math.min(
						Math.max(
							Number(
								target.getScaledHeight?.() || target.height || 0
							),
							0
						),
						lh
					);
					const freeY = Math.max( 0, ( lh - contentH ) / 2 );
					const localY =
						lineAlign === 'bottom'
							? freeY
							: lineAlign === 'center'
							? 0
							: -freeY;
					const rad = ( rotation * Math.PI ) / 180;

					target.set( {
						left: lcX - localY * Math.sin( rad ) + extraX,
						top: lcY + localY * Math.cos( rad ) + extraY,
					} );
					target.setCoords?.();
				};

				if ( isEngraving ) {
					// Fake etched depth: subtle light highlight below + soft dark shadow above.
					obj.set( {
						opacity: engravingPalette.opacity,
						globalCompositeOperation:
							engravingPalette.composite || 'source-over',
						shadow: new Shadow( {
							color: engravingPalette.highlight,
							offsetX: 0,
							offsetY: 1,
							blur: 1,
						} ),
					} );
				} else if ( isEmbroidery ) {
					const threadLift = this.embroideryHighlightColor( color );
					const threadShadow = this.embroideryShadowColor( color );

					stitchPad = new textClass( raw, {
						left: lcX + Math.max( 0.45, fontSize * 0.015 ),
						top: lcY + Math.max( 0.65, fontSize * 0.02 ),
						originX: 'center',
						originY: 'center',
						...textBoxSize,
						padding: textPadding,
						angle: rotation,
						fontFamily: font?.name || 'sans-serif',
						fontWeight: font?.weight || 'normal',
						fontStyle: font?.style || 'normal',
						fontSize,
						fill: threadShadow,
						opacity: 0.24,
						shadow: new Shadow( {
							color: 'rgba(0,0,0,0.22)',
							offsetX: 0.6,
							offsetY: 0.9,
							blur: 1.8,
						} ),
						textAlign: align,
						selectable: false,
						evented: false,
						objectCaching: false,
					} );
					stitchPad._ocContent = true;
					canvas.add( stitchPad );

					stitchLift = new textClass( raw, {
						left: lcX - Math.max( 0.25, fontSize * 0.006 ),
						top: lcY - Math.max( 0.25, fontSize * 0.006 ),
						originX: 'center',
						originY: 'center',
						...textBoxSize,
						padding: textPadding,
						angle: rotation,
						fontFamily: font?.name || 'sans-serif',
						fontWeight: font?.weight || 'normal',
						fontStyle: font?.style || 'normal',
						fontSize,
						fill: 'rgba(255,255,255,0)',
						stroke: threadLift,
						strokeWidth: Math.max( 0.2, fontSize * 0.006 ),
						opacity: 0.22,
						textAlign: align,
						selectable: false,
						evented: false,
						objectCaching: false,
					} );
					stitchLift._ocContent = true;
					canvas.add( stitchLift );

					obj.set( {
						stroke: this.embroiderySoftEdgeColor( color ),
						strokeWidth: Math.max( 0.18, fontSize * 0.005 ),
						shadow: new Shadow( {
							color: 'rgba(0,0,0,0.22)',
							offsetX: 0.7,
							offsetY: 0.95,
							blur: 1.1,
						} ),
					} );
				}

				const fitsTextLayer = ( size ) => {
					if ( isSingleLineText ) {
						return this.textFitsBox(
							raw,
							font,
							size,
							layer.settings,
							singleLineMaxWidth,
							singleLineMaxHeight,
							false
						);
					}

					return this.textFitsBox(
						raw,
						font,
						size,
						layer.settings,
						lw,
						lh,
						true
					);
				};
				const fittingFloor = minFontSize || 4;
				while (
					! fitsTextLayer( fontSize ) &&
					fontSize > fittingFloor
				) {
					fontSize = Math.max( fittingFloor, fontSize - 1 );
					textPadding = this.textRenderPadding( fontSize );
					obj.set( { fontSize, padding: textPadding } );
					if ( stitchPad ) {
						stitchPad.set( { fontSize, padding: textPadding } );
					}
					if ( stitchLift ) {
						stitchLift.set( { fontSize, padding: textPadding } );
					}
				}
				obj.initDimensions?.();
				const textareaScale = isSingleLineText ? 1 : 1;
				if ( ! isSingleLineText ) {
					obj.set( { scaleX: textareaScale, scaleY: textareaScale } );
				}
				textareaPosition( obj );
				obj.setCoords?.();
				const measuredText = this.measureSingleLineText(
					raw,
					font,
					fontSize,
					layer.settings
				);
				const renderedWidth = Math.max(
					1,
					Math.ceil( measuredText.width + textPadding * 2 )
				);
				const singleLineScaleX = isSingleLineText
					? Math.min( 1, singleLineMaxWidth / renderedWidth )
					: 1;
				if ( isSingleLineText ) {
					let alignedLeft = lcX;
					let alignedTop = lcY;
					let alignmentOffset = 0;
					const renderedDisplayWidth =
						renderedWidth * singleLineScaleX;

					if ( align === 'left' ) {
						alignmentOffset =
							-lw / 2 + anchorPad + renderedDisplayWidth / 2;
					} else if ( align === 'right' ) {
						alignmentOffset =
							lw / 2 - anchorPad - renderedDisplayWidth / 2;
					}

					if ( alignmentOffset ) {
						const rad = ( rotation * Math.PI ) / 180;
						alignedLeft += alignmentOffset * Math.cos( rad );
						alignedTop += alignmentOffset * Math.sin( rad );
					}

					obj.set( {
						left: alignedLeft,
						top: alignedTop,
						scaleX: singleLineScaleX,
					} );
					obj.initDimensions?.();
					obj.setCoords?.();
					this.centerObjectBounds(
						obj,
						alignedLeft,
						alignedTop,
						rotation
					);
					this.keepObjectInsidePrintArea( obj, bounds, scale );
				}
				if ( isEmbroidery ) {
					obj.set( {
						fill: this.embroideryPattern( color, fontSize ),
					} );
				}
				const textClipPath = textClip(
					this.textClipPadding( fontSize )
				);

				if ( stitchPad ) {
					const padX = Math.max( 0.45, fontSize * 0.015 );
					const padY = Math.max( 0.65, fontSize * 0.02 );
					stitchPad.set( {
						left: ( isSingleLineText ? obj.left : lcX ) + padX,
						top: ( isSingleLineText ? obj.top : lcY ) + padY,
						fontSize,
						padding: textPadding,
					} );
					if ( isSingleLineText ) {
						stitchPad.set( { scaleX: singleLineScaleX } );
					} else {
						stitchPad.set( {
							scaleX: textareaScale,
							scaleY: textareaScale,
						} );
						textareaPosition( stitchPad, padX, padY );
					}
					this.applyContentClip( stitchPad, textClipPath );
				}
				if ( stitchLift ) {
					const liftX = Math.max( 0.25, fontSize * 0.006 );
					const liftY = Math.max( 0.25, fontSize * 0.006 );
					stitchLift.set( {
						left: ( isSingleLineText ? obj.left : lcX ) - liftX,
						top: ( isSingleLineText ? obj.top : lcY ) - liftY,
						fontSize,
						padding: textPadding,
						strokeWidth: Math.max( 0.2, fontSize * 0.006 ),
					} );
					if ( isSingleLineText ) {
						stitchLift.set( { scaleX: singleLineScaleX } );
					} else {
						stitchLift.set( {
							scaleX: textareaScale,
							scaleY: textareaScale,
						} );
						textareaPosition( stitchLift, -liftX, -liftY );
					}
					this.applyContentClip( stitchLift, textClipPath );
				}
				this.applyContentClip( obj, textClipPath );
				canvas.add( obj );
				break;
			}

			case 'image': {
				if ( input.attachmentUrl ) {
					const imageFilter = this.imageFilterForLayer(
						layer,
						input.imageFilterId
					);
					const imageEffects = {
						...( imageFilter ? { imageFilter } : {} ),
						...( imageFilter && layer.settings?.enable_image_colour
							? {
									imageColor:
										input.colorHex ||
										layer.settings?.default_color ||
										'#000000',
							  }
							: {} ),
						...( isEmbroidery ? { embroidery: true } : {} ),
						...( isEngraving ? { photoEngraving: true } : {} ),
					};
					const rendered = await this.renderFabricImg(
						canvas,
						input.attachmentUrl,
						lx,
						ly,
						lw,
						lh,
						isEngraving,
						'anonymous',
						false,
						rotation,
						engravingPalette,
						contentClip(),
						'contain',
						'',
						imageEffects,
						isCurrent
					);
					if ( ! rendered && isCurrent() ) {
						throw new Error(
							'Artwork image could not be rendered.'
						);
					}
				}
				break;
			}

			case 'clipmask': {
				if ( input.attachmentUrl ) {
					const rendered = await this.renderFabricImg(
						canvas,
						input.attachmentUrl,
						lx,
						ly,
						lw,
						lh,
						isEngraving,
						'anonymous',
						false,
						rotation,
						engravingPalette,
						this.layerClipPath(
							lx,
							ly,
							lw,
							lh,
							rotation,
							layer.settings
						),
						'cover',
						'',
						{ photoEngraving: isEngraving },
						isCurrent
					);
					if ( ! rendered && isCurrent() ) {
						throw new Error(
							'Masked artwork could not be rendered.'
						);
					}
				}
				break;
			}

			case 'clipart': {
				if ( input.clipartUrl ) {
					const selectedClipartColor = String(
						input.colorHex || ''
					).trim();
					const shouldRecolourClipart = Boolean(
						input.clipartRecolourable &&
							( selectedClipartColor ||
								isEngraving ||
								isEmbroidery )
					);
					const clipartColor = shouldRecolourClipart
						? isEngraving
							? engravingPalette.text
							: selectedClipartColor
						: '';
					const clipartUrl = clipartColor
						? await this.recolourSvgClipartUrl(
								input.clipartUrl,
								clipartColor,
								isEmbroidery ? 'embroidery' : ''
						  )
						: await this.normaliseSvgClipartUrl( input.clipartUrl );
					const clipartCrossOrigin = clipartUrl.startsWith( 'data:' )
						? ''
						: 'anonymous';
					const clipartEffects = isEmbroidery
						? {
								embroideryColor:
									clipartColor ||
									selectedClipartColor ||
									'#000000',
						  }
						: shouldRecolourClipart
						? { preserveRecolouredPixels: true }
						: {};
					const rendered = await this.renderFabricImg(
						canvas,
						clipartUrl,
						lx,
						ly,
						lw,
						lh,
						isEngraving,
						clipartCrossOrigin,
						false,
						rotation,
						engravingPalette,
						contentClip(),
						'contain',
						'',
						clipartEffects,
						isCurrent
					);
					if ( ! rendered && isCurrent() ) {
						throw new Error( 'Clipart could not be rendered.' );
					}
				}
				break;
			}

			case 'lineart': {
				const lineartColor = isEngraving
					? engravingPalette.text
					: String( input.colorHex || '' ).trim();
				if ( ! lineartColor ) {
					break;
				}
				const r = new Rect( {
					left: lcX,
					top: lcY,
					originX: 'center',
					originY: 'center',
					angle: rotation,
					width: lw,
					height: lh,
					fill: lineartColor,
					opacity: 0.6,
					selectable: false,
					evented: false,
				} );
				r._ocContent = true;
				this.applyContentClip( r, contentClip() );
				canvas.add( r );
				break;
			}

			case 'spotify': {
				const val = ( input.value || '' ).trim();
				if ( ! val ) {
					break;
				}

				if (
					input.spotifyStatus === 'invalid_format' ||
					input.spotifyStatus === 'playlist_private_or_invalid' ||
					input.spotifyStatus === 'invalid_or_unavailable'
				) {
					const invalidText =
						input.spotifyStatus === 'playlist_private_or_invalid'
							? 'Private / invalid Spotify playlist'
							: 'Invalid Spotify link';
					const invalidObj = new FabricText( invalidText, {
						left: lcX,
						top: lcY,
						originX: 'center',
						originY: 'center',
						angle: rotation,
						fontFamily: 'monospace',
						fontSize: Math.max( 9, Math.round( lh * 0.17 ) ),
						fill: '#b32d2e',
						textAlign: 'center',
						selectable: false,
						evented: false,
					} );
					invalidObj._ocContent = true;
					this.applyContentClip( invalidObj, contentClip() );
					canvas.add( invalidObj );
					break;
				}

				const spotifyCodeUrl = this.buildSpotifyCodeUrl(
					input.spotifyUri || val,
					isEngraving,
					engravingPalette
				);
				if ( spotifyCodeUrl ) {
					// Try CORS-safe load first; if Spotify CDN blocks CORS for this origin,
					// retry without crossOrigin so users still see the scannable in live preview.
					let rendered = await this.renderFabricImg(
						canvas,
						spotifyCodeUrl,
						lx,
						ly,
						lw,
						lh,
						isEngraving,
						'anonymous',
						true,
						rotation,
						engravingPalette,
						contentClip(),
						'contain',
						'',
						{},
						isCurrent
					);
					if ( ! rendered ) {
						rendered = await this.renderFabricImg(
							canvas,
							spotifyCodeUrl,
							lx,
							ly,
							lw,
							lh,
							isEngraving,
							'',
							true,
							rotation,
							engravingPalette,
							contentClip(),
							'contain',
							'',
							{},
							isCurrent
						);
					}
					if ( rendered ) {
						break;
					}
				}
				if ( ! isCurrent() ) {
					return;
				}

				const fallback = new FabricText(
					'\u266b Spotify code unavailable',
					{
						left: lcX,
						top: lcY,
						originX: 'center',
						originY: 'center',
						angle: rotation,
						fontFamily: 'monospace',
						fontSize: Math.max( 9, Math.round( lh * 0.22 ) ),
						fill: '#666666',
						textAlign: 'center',
						selectable: false,
						evented: false,
					}
				);
				fallback._ocContent = true;
				this.applyContentClip( fallback, contentClip() );
				canvas.add( fallback );
				break;
			}
		}
	},

	fontLimit( value ) {
		return Math.max( 0, parseInt( value, 10 ) || 0 );
	},

	textRenderPadding( fontSize ) {
		return Math.max( 4, Math.ceil( ( Number( fontSize ) || 0 ) * 0.18 ) );
	},

	textClipPadding( fontSize ) {
		return Math.max( 2, Math.ceil( ( Number( fontSize ) || 0 ) * 0.18 ) );
	},

	textFitSafetyMargin( fontSize ) {
		const size = Number( fontSize ) || 0;

		return {
			x: Math.max( 1, Math.ceil( size * 0.06 ) ),
			y: Math.max( 2, Math.ceil( size * 0.12 ) ),
		};
	},

	textFitsBox(
		raw,
		font,
		fontSize,
		settings,
		maxW,
		maxH,
		multiline = false
	) {
		if ( ! raw ) {
			return true;
		}

		const margin = this.textFitSafetyMargin( fontSize );
		const textClass = multiline ? Textbox : FabricText;
		const textBoxSize = multiline ? { width: Math.max( 1, maxW ) } : {};
		const obj = new textClass( raw, {
			left: 0,
			top: 0,
			originX: 'center',
			originY: 'center',
			...textBoxSize,
			fontFamily: font?.name || 'sans-serif',
			fontWeight: font?.weight || 'normal',
			fontStyle: font?.style || 'normal',
			fontSize,
			textAlign: settings?.alignment || 'center',
			selectable: false,
			evented: false,
		} );
		obj.initDimensions?.();
		obj.setCoords?.();
		const measured = obj.getBoundingRect?.( true, true ) || obj;

		if ( multiline ) {
			return (
				Number( measured.height || 0 ) + margin.y * 2 <=
				Math.max( maxH, 10 )
			);
		}

		return (
			Number( measured.width || 0 ) + margin.x * 2 <=
				Math.max( maxW, 10 ) &&
			Number( measured.height || 0 ) + margin.y * 2 <=
				Math.max( maxH, 10 )
		);
	},

	measureSingleLineText( raw, font, fontSize, settings = {} ) {
		const obj = new FabricText( raw || '', {
			left: 0,
			top: 0,
			originX: 'left',
			originY: 'top',
			fontFamily: font?.name || 'sans-serif',
			fontWeight: font?.weight || 'normal',
			fontStyle: font?.style || 'normal',
			fontSize,
			textAlign: settings?.alignment || 'center',
			selectable: false,
			evented: false,
		} );
		obj.setCoords?.();
		const measured = obj.getBoundingRect?.( true, true ) || obj;

		return {
			width: Number( measured.width || 0 ),
			height: Number( measured.height || 0 ),
		};
	},

	textLayerFitsAtSize( layer, raw, font, fontSize ) {
		const area = this.areas[ this.areaIndexForLayer( layer?.id ) ];
		const bounds = area ? this.areaBounds( area ) : null;
		const layerBox = bounds ? displayLayer( layer, bounds ) : layer;
		const displaySize = bounds
			? displayFontSize( fontSize, bounds )
			: fontSize;

		return this.textFitsBox(
			raw,
			font,
			displaySize,
			layer?.settings || {},
			Number( layerBox?.w || 0 ),
			Number( layerBox?.h || 0 ),
			layer?.type === 'textarea'
		);
	},

	async maxFittingFontSize( layerId, upperLimit ) {
		const layer = this.getLayerById( layerId );
		if ( ! layer || ! [ 'text', 'textarea' ].includes( layer.type ) ) {
			return upperLimit;
		}
		const maxLimit = this.fontLimit( layer.settings?.max_font_size );
		if ( maxLimit ) {
			upperLimit = Math.min( upperLimit, maxLimit );
		}

		const input = this.inputs[ layerId ] || {};
		const normalisedText = String( input.value || '' ).replace(
			/\r\n?/g,
			'\n'
		);
		const raw =
			layer.type === 'text' ? normalisedText.trim() : normalisedText;
		if ( ! raw.trim() ) {
			return upperLimit;
		}

		let font = this.fonts.find(
			( f ) =>
				f.id ===
				( input.fontId || layer.settings?.default_font_id || 0 )
		);
		if ( font ) {
			try {
				await this.loadFont( font );
			} catch {
				font = null;
			}
		}

		const min = this.fontLimit( layer.settings?.min_font_size ) || 1;
		let low = min;
		let high = Math.max( min, upperLimit );
		let best = min;

		while ( low <= high ) {
			const mid = Math.floor( ( low + high ) / 2 );
			if ( this.textLayerFitsAtSize( layer, raw, font, mid ) ) {
				best = mid;
				low = mid + 1;
			} else {
				high = mid - 1;
			}
		}

		return Math.max( min, Math.min( upperLimit, best ) );
	},

	async updateTextSizeSliderCap( layerId, clampValue = true ) {
		const sizeEl = document.querySelector(
			`[data-oc-layer-font-size="${ layerId }"]`
		);
		if ( ! sizeEl ) {
			return;
		}

		if ( ! sizeEl.dataset.ocOriginalMax ) {
			sizeEl.dataset.ocOriginalMax = sizeEl.max || '200';
		}
		const originalMax = Math.max(
			parseInt( sizeEl.dataset.ocOriginalMax, 10 ) || 200,
			parseInt( sizeEl.min, 10 ) || 1
		);
		const layer = this.getLayerById( layerId );
		const configuredMax = this.fontLimit( layer?.settings?.max_font_size );
		let cappedMax = Math.max(
			parseInt( sizeEl.min, 10 ) || 1,
			configuredMax ? Math.min( originalMax, configuredMax ) : originalMax
		);
		cappedMax = await this.maxFittingFontSize( layerId, cappedMax );

		sizeEl.max = String( cappedMax );
		const hasAdjustableRange =
			cappedMax > ( parseInt( sizeEl.min, 10 ) || 1 );
		const control = sizeEl.closest( '[data-oc-font-size-control]' );
		control
			?.querySelector( '[data-oc-font-size-label]' )
			?.toggleAttribute( 'hidden', ! hasAdjustableRange );
		sizeEl.toggleAttribute( 'hidden', ! hasAdjustableRange );
		control
			?.querySelector( '[data-oc-font-size-notice]' )
			?.toggleAttribute( 'hidden', hasAdjustableRange );
		if ( clampValue && parseInt( sizeEl.value, 10 ) > cappedMax ) {
			sizeEl.value = String( cappedMax );
			if ( ! this.inputs[ layerId ] ) {
				this.inputs[ layerId ] = {};
			}
			this.inputs[ layerId ].fontSize = cappedMax;
		}

		document
			.querySelector(
				`.oc-range-value[data-oc-range-value="${ layerId }"]`
			)
			?.replaceChildren( document.createTextNode( sizeEl.value ) );
	},

	rotatedLayerCenter( layer, bounds, rotation ) {
		let x = layer.x + layer.w / 2;
		let y = layer.y + layer.h / 2;
		if ( ! bounds?.w || ! rotation ) {
			return { x, y };
		}

		const cx = bounds.x + bounds.w / 2;
		const cy = bounds.y + bounds.h / 2;
		const rad = ( rotation * Math.PI ) / 180;
		const dx = x - cx;
		const dy = y - cy;

		x = cx + dx * Math.cos( rad ) - dy * Math.sin( rad );
		y = cy + dx * Math.sin( rad ) + dy * Math.cos( rad );
		return { x, y };
	},

	engravingPalette( material = 'silver_metal' ) {
		const palettes = {
			glass: {
				text: '#eef4f4',
				imageTint: '#eef4f4',
				bg: 'F7FAFA',
				highlight: 'rgba(255,255,255,0.7)',
				brightness: 0.16,
				contrast: -0.04,
				opacity: 0.62,
			},
			gold_metal: {
				text: '#6f5227',
				imageTint: '#6f5227',
				bg: 'D9A72E',
				highlight: 'rgba(255,238,176,0.34)',
				brightness: -0.18,
				contrast: 0.22,
				opacity: 0.88,
			},
			silver_metal: {
				text: '#c9c9c3',
				imageTint: '#c9c9c3',
				bg: 'ECEFF1',
				highlight: 'rgba(255,255,255,0.42)',
				brightness: -0.28,
				contrast: 0.18,
				opacity: 0.9,
			},
			silver_plaque: {
				text: '#17191b',
				imageTint: '#111315',
				bg: 'ECEFF1',
				highlight: 'rgba(255,255,255,0.08)',
				brightness: -0.08,
				contrast: 0.34,
				opacity: 0.96,
				tintAlpha: 0.9,
				composite: 'multiply',
				photoDither: true,
			},
			black_metal: {
				text: '#d8d8d8',
				imageTint: '#d8d8d8',
				bg: '1F2328',
				highlight: 'rgba(255,255,255,0.24)',
				brightness: -0.34,
				contrast: 0.28,
				opacity: 0.95,
			},
			wood: {
				text: 'rgba(78,42,20,0.7)',
				imageTint: '#5d3922',
				bg: 'C8A06B',
				highlight: 'rgba(255,225,180,0.16)',
				brightness: -0.16,
				contrast: 0.2,
				opacity: 0.72,
				tintAlpha: 0.72,
				composite: 'multiply',
				pattern: 'wood',
			},
			leather: {
				text: 'rgba(66,35,21,0.86)',
				imageTint: '#4a2919',
				bg: 'A66F45',
				highlight: 'rgba(235,190,140,0.18)',
				brightness: -0.2,
				contrast: 0.24,
				opacity: 0.84,
				tintAlpha: 0.82,
				composite: 'multiply',
				pattern: 'leather',
				noise: 5,
			},
		};

		return palettes[ material ] || palettes.silver_metal;
	},

	woodEngravingPattern( fontSize = 24 ) {
		const source = document.createElement( 'canvas' );
		const width = Math.max(
			42,
			Math.min( 96, Math.round( fontSize * 1.9 ) )
		);
		const height = Math.max(
			14,
			Math.min( 30, Math.round( fontSize * 0.48 ) )
		);
		source.width = width;
		source.height = height;

		const ctx = source.getContext( '2d' );
		if ( ! ctx ) {
			return 'rgba(78,42,20,0.7)';
		}

		ctx.fillStyle = 'rgba(78,42,20,0.64)';
		ctx.fillRect( 0, 0, width, height );

		for ( let y = 1; y < height; y += 4 ) {
			ctx.strokeStyle = 'rgba(255,220,165,0.16)';
			ctx.lineWidth = 1;
			ctx.beginPath();
			ctx.moveTo( 0, y + ( y % 3 ) * 0.25 );
			ctx.bezierCurveTo(
				width * 0.28,
				y - 1.3,
				width * 0.62,
				y + 1.2,
				width,
				y - 0.4
			);
			ctx.stroke();

			ctx.strokeStyle = 'rgba(54,26,12,0.18)';
			ctx.beginPath();
			ctx.moveTo( 0, y + 1.8 );
			ctx.bezierCurveTo(
				width * 0.32,
				y + 2.8,
				width * 0.7,
				y + 0.8,
				width,
				y + 1.6
			);
			ctx.stroke();
		}

		return new Pattern( { source, repeat: 'repeat' } );
	},

	leatherEngravingPattern( fontSize = 24 ) {
		const source = document.createElement( 'canvas' );
		const size = Math.max(
			18,
			Math.min( 36, Math.round( fontSize * 0.72 ) )
		);
		source.width = size;
		source.height = size;

		const ctx = source.getContext( '2d' );
		if ( ! ctx ) {
			return 'rgba(66,35,21,0.86)';
		}

		ctx.fillStyle = 'rgba(66,35,21,0.82)';
		ctx.fillRect( 0, 0, size, size );

		// Fixed pore positions keep the grain stable between preview redraws.
		const pores = [
			[ 0.12, 0.18, 0.035 ],
			[ 0.43, 0.1, 0.025 ],
			[ 0.76, 0.22, 0.04 ],
			[ 0.26, 0.48, 0.03 ],
			[ 0.61, 0.55, 0.035 ],
			[ 0.9, 0.44, 0.025 ],
			[ 0.08, 0.8, 0.025 ],
			[ 0.48, 0.88, 0.04 ],
			[ 0.8, 0.76, 0.03 ],
		];
		pores.forEach( ( [ x, y, radius ], index ) => {
			ctx.beginPath();
			ctx.fillStyle =
				index % 2 ? 'rgba(235,190,140,0.12)' : 'rgba(27,13,8,0.18)';
			ctx.ellipse(
				x * size,
				y * size,
				Math.max( 0.55, radius * size ),
				Math.max( 0.4, radius * size * 0.58 ),
				( index * Math.PI ) / 7,
				0,
				Math.PI * 2
			);
			ctx.fill();
		} );

		return new Pattern( { source, repeat: 'repeat' } );
	},

	silverPlaquePhotoDither( element, displayW, displayH ) {
		try {
			const sourceW = Number(
				element?.naturalWidth || element?.width || 0
			);
			const sourceH = Number(
				element?.naturalHeight || element?.height || 0
			);
			if ( ! sourceW || ! sourceH ) {
				return null;
			}

			const maxDimension = Math.min(
				1200,
				Math.max(
					240,
					Math.round( Math.max( displayW, displayH ) * 1.5 )
				)
			);
			const scale = Math.min(
				1,
				maxDimension / Math.max( sourceW, sourceH )
			);
			const width = Math.max( 1, Math.round( sourceW * scale ) );
			const height = Math.max( 1, Math.round( sourceH * scale ) );
			const output = document.createElement( 'canvas' );
			output.width = width;
			output.height = height;

			const ctx = output.getContext( '2d', { willReadFrequently: true } );
			if ( ! ctx ) {
				return null;
			}
			ctx.drawImage( element, 0, 0, width, height );
			const image = ctx.getImageData( 0, 0, width, height );
			const pixels = image.data;
			const matrix = [
				[ 0, 48, 12, 60, 3, 51, 15, 63 ],
				[ 32, 16, 44, 28, 35, 19, 47, 31 ],
				[ 8, 56, 4, 52, 11, 59, 7, 55 ],
				[ 40, 24, 36, 20, 43, 27, 39, 23 ],
				[ 2, 50, 14, 62, 1, 49, 13, 61 ],
				[ 34, 18, 46, 30, 33, 17, 45, 29 ],
				[ 10, 58, 6, 54, 9, 57, 5, 53 ],
				[ 42, 26, 38, 22, 41, 25, 37, 21 ],
			];

			for ( let y = 0; y < height; y++ ) {
				for ( let x = 0; x < width; x++ ) {
					const index = ( y * width + x ) * 4;
					const alpha = pixels[ index + 3 ];
					const luminance =
						pixels[ index ] * 0.2126 +
						pixels[ index + 1 ] * 0.7152 +
						pixels[ index + 2 ] * 0.0722;
					const contrasted = Math.max(
						0,
						Math.min( 255, ( luminance - 128 ) * 1.22 + 142 )
					);
					const threshold = ( matrix[ y % 8 ][ x % 8 ] + 0.5 ) * 4;
					const engraved = contrasted < threshold;

					pixels[ index ] = 17;
					pixels[ index + 1 ] = 19;
					pixels[ index + 2 ] = 21;
					pixels[ index + 3 ] = engraved ? alpha : 0;
				}
			}

			ctx.putImageData( image, 0, 0 );
			return output;
		} catch ( error ) {
			console.warn( '[OC] Silver plaque photo dithering failed:', error );
			return null;
		}
	},

	embroideryPattern( color, fontSize = 24 ) {
		const source = document.createElement( 'canvas' );
		const size = Math.max(
			10,
			Math.min( 20, Math.round( fontSize * 0.18 ) )
		);
		source.width = size;
		source.height = size;

		const ctx = source.getContext( '2d' );
		if ( ! ctx ) {
			return color;
		}

		const rgb = this.hexToRgb( color ) || { r: 0, g: 0, b: 0 };
		const hi = {
			r: Math.min( 255, rgb.r + 92 ),
			g: Math.min( 255, rgb.g + 92 ),
			b: Math.min( 255, rgb.b + 92 ),
		};
		const lo = {
			r: Math.max( 0, rgb.r - 78 ),
			g: Math.max( 0, rgb.g - 78 ),
			b: Math.max( 0, rgb.b - 78 ),
		};

		const base = ctx.createLinearGradient(
			0,
			0,
			source.width,
			source.height
		);
		base.addColorStop( 0, `rgb(${ hi.r },${ hi.g },${ hi.b })` );
		base.addColorStop( 0.42, color );
		base.addColorStop( 1, `rgb(${ lo.r },${ lo.g },${ lo.b })` );
		ctx.fillStyle = base;
		ctx.fillRect( 0, 0, source.width, source.height );

		ctx.lineWidth = Math.max( 1.2, size * 0.12 );
		ctx.lineCap = 'round';
		const stitchGap = Math.max( 2.4, size * 0.24 );
		let stitchIndex = 0;
		for ( let i = -source.height; i < source.width * 2; i += stitchGap ) {
			ctx.strokeStyle =
				stitchIndex % 2
					? `rgba(${ hi.r },${ hi.g },${ hi.b },0.48)`
					: `rgba(${ lo.r },${ lo.g },${ lo.b },0.24)`;
			ctx.beginPath();
			ctx.moveTo( i, source.height + 1.5 );
			ctx.lineTo( i + source.height + 1.5, -1.5 );
			ctx.stroke();
			stitchIndex += 1;
		}

		ctx.lineWidth = Math.max( 0.45, size * 0.045 );
		ctx.strokeStyle = `rgba(${ lo.r },${ lo.g },${ lo.b },0.16)`;
		for (
			let i = -source.height;
			i < source.width * 2;
			i += Math.max( 3.2, size * 0.32 )
		) {
			ctx.beginPath();
			ctx.moveTo( i, source.height + 1 );
			ctx.lineTo( i + source.height + 1, -1 );
			ctx.stroke();
		}

		ctx.lineWidth = 0.5;
		ctx.strokeStyle = 'rgba(255,255,255,0.14)';
		for ( let y = 1; y < source.height; y += 4 ) {
			ctx.beginPath();
			ctx.moveTo( 0, y + 0.5 );
			ctx.lineTo( source.width, y + 0.5 );
			ctx.stroke();
		}

		return new Pattern( { source, repeat: 'repeat' } );
	},

	embroiderySoftEdgeColor( color ) {
		const rgb = this.hexToRgb( color );
		if ( ! rgb ) {
			return 'rgba(0,0,0,0.16)';
		}

		return `rgba(${ Math.max( 0, rgb.r - 36 ) },${ Math.max(
			0,
			rgb.g - 36
		) },${ Math.max( 0, rgb.b - 36 ) },0.24)`;
	},

	embroideryHighlightColor( color ) {
		const rgb = this.hexToRgb( color );
		if ( ! rgb ) {
			return 'rgba(255,255,255,0.42)';
		}

		return `rgba(${ Math.min( 255, rgb.r + 88 ) },${ Math.min(
			255,
			rgb.g + 88
		) },${ Math.min( 255, rgb.b + 88 ) },0.62)`;
	},

	embroideryShadowColor( color ) {
		const rgb = this.hexToRgb( color );
		if ( ! rgb ) {
			return 'rgba(0,0,0,0.42)';
		}

		return `rgba(${ Math.max( 0, rgb.r - 96 ) },${ Math.max(
			0,
			rgb.g - 96
		) },${ Math.max( 0, rgb.b - 96 ) },0.72)`;
	},

	hexToRgb( color ) {
		const value = String( color || '' ).trim();
		const match = value.match( /^#([0-9a-f]{3}|[0-9a-f]{6})$/i );
		if ( ! match ) {
			return null;
		}

		const hex =
			match[ 1 ].length === 3
				? match[ 1 ]
						.split( '' )
						.map( ( char ) => char + char )
						.join( '' )
				: match[ 1 ];

		return {
			r: parseInt( hex.slice( 0, 2 ), 16 ),
			g: parseInt( hex.slice( 2, 4 ), 16 ),
			b: parseInt( hex.slice( 4, 6 ), 16 ),
		};
	},

	printAreaClipPath( bounds, scale ) {
		if ( ! bounds || ! bounds.w || ! bounds.h ) {
			return null;
		}

		return new Rect( {
			left: ( Number( bounds.x ) + Number( bounds.w ) / 2 ) * scale,
			top: ( Number( bounds.y ) + Number( bounds.h ) / 2 ) * scale,
			originX: 'center',
			originY: 'center',
			angle: Number( bounds.rotation ) || 0,
			width: Number( bounds.w ) * scale,
			height: Number( bounds.h ) * scale,
			absolutePositioned: true,
		} );
	},

	rectClipPath( x, y, w, h, angle = 0 ) {
		if ( ! w || ! h ) {
			return null;
		}

		return new Rect( {
			left: x + w / 2,
			top: y + h / 2,
			originX: 'center',
			originY: 'center',
			angle,
			width: w,
			height: h,
			absolutePositioned: true,
		} );
	},

	layerClipPath( x, y, w, h, angle = 0, settings = {} ) {
		if ( ! w || ! h ) {
			return null;
		}

		const shape = String( settings?.mask_shape || 'circle' ).toLowerCase();
		const left = x + w / 2;
		const top = y + h / 2;

		if ( shape === 'circle' ) {
			return new Circle( {
				left,
				top,
				originX: 'center',
				originY: 'center',
				radius: Math.min( w, h ) / 2,
				absolutePositioned: true,
			} );
		}

		return new Rect( {
			left,
			top,
			originX: 'center',
			originY: 'center',
			angle,
			width: w,
			height: h,
			absolutePositioned: true,
		} );
	},

	applyContentClip( obj, clipPath ) {
		if ( clipPath ) {
			obj.set( { clipPath } );
		}
	},

	centerObjectBounds( obj, targetX, targetY, angle = 0 ) {
		if ( ! obj ) {
			return;
		}

		obj.setCoords?.();
		const points =
			typeof obj.getCoords === 'function' ? obj.getCoords() : [];
		if ( ! points.length ) {
			return;
		}

		const rad = ( ( Number( angle ) || 0 ) * Math.PI ) / 180;
		const cos = Math.cos( rad );
		const sin = Math.sin( rad );
		const local = points.map( ( point ) => {
			const dx = point.x - targetX;
			const dy = point.y - targetY;

			return {
				x: targetX + dx * cos + dy * sin,
				y: targetY - dx * sin + dy * cos,
			};
		} );
		const minX = Math.min( ...local.map( ( point ) => point.x ) );
		const maxX = Math.max( ...local.map( ( point ) => point.x ) );
		const minY = Math.min( ...local.map( ( point ) => point.y ) );
		const maxY = Math.max( ...local.map( ( point ) => point.y ) );
		const moveX = targetX - ( minX + maxX ) / 2;
		const moveY = targetY - ( minY + maxY ) / 2;

		if ( Math.abs( moveX ) < 0.01 && Math.abs( moveY ) < 0.01 ) {
			return;
		}

		obj.set( {
			left: Number( obj.left || 0 ) + moveX * cos - moveY * sin,
			top: Number( obj.top || 0 ) + moveX * sin + moveY * cos,
		} );
		obj.setCoords?.();
	},

	keepObjectInsidePrintArea( obj, bounds, scale ) {
		if ( ! obj || ! bounds || ! bounds.w || ! bounds.h ) {
			return;
		}

		obj.setCoords?.();
		const points =
			typeof obj.getCoords === 'function' ? obj.getCoords() : [];
		if ( ! points.length ) {
			return;
		}

		const cx = ( Number( bounds.x ) + Number( bounds.w ) / 2 ) * scale;
		const cy = ( Number( bounds.y ) + Number( bounds.h ) / 2 ) * scale;
		const halfW = ( Number( bounds.w ) * scale ) / 2;
		const halfH = ( Number( bounds.h ) * scale ) / 2;
		const angle = ( ( Number( bounds.rotation ) || 0 ) * Math.PI ) / 180;
		const cos = Math.cos( angle );
		const sin = Math.sin( angle );

		const local = points.map( ( point ) => {
			const dx = point.x - cx;
			const dy = point.y - cy;

			return {
				x: cx + dx * cos + dy * sin,
				y: cy - dx * sin + dy * cos,
			};
		} );
		const minX = Math.min( ...local.map( ( point ) => point.x ) );
		const maxX = Math.max( ...local.map( ( point ) => point.x ) );
		const minY = Math.min( ...local.map( ( point ) => point.y ) );
		const maxY = Math.max( ...local.map( ( point ) => point.y ) );
		const left = cx - halfW;
		const right = cx + halfW;
		const top = cy - halfH;
		const bottom = cy + halfH;
		let moveX = 0;
		let moveY = 0;

		if ( minX < left ) {
			moveX = left - minX;
		} else if ( maxX > right ) {
			moveX = right - maxX;
		}

		if ( minY < top ) {
			moveY = top - minY;
		} else if ( maxY > bottom ) {
			moveY = bottom - maxY;
		}

		if ( ! moveX && ! moveY ) {
			return;
		}

		obj.set( {
			left: Number( obj.left || 0 ) + moveX * cos - moveY * sin,
			top: Number( obj.top || 0 ) + moveX * sin + moveY * cos,
		} );
		obj.setCoords?.();
	},

	async recolourSvgClipartUrl( url, color, effect = '' ) {
		const key = `${ url }|${ color }|${ effect }`;
		if ( this.clipartSvgCache[ key ] ) {
			return this.clipartSvgCache[ key ];
		}

		const request = this.createStateAbortController( 10000 );
		try {
			const response = await fetch( url, {
				credentials: 'same-origin',
				cache: 'force-cache',
				signal: request.controller.signal,
			} );
			if ( ! response.ok ) {
				throw new Error(
					`Could not load clipart SVG (${ response.status }).`
				);
			}

			const raw = await response.text();
			const doc = new window.DOMParser().parseFromString(
				raw,
				'image/svg+xml'
			);
			const svg = doc.documentElement;
			if ( ! svg || svg.localName.toLowerCase() !== 'svg' ) {
				throw new Error( 'Clipart is not an SVG.' );
			}

			const paint =
				effect === 'embroidery' ? 'url(#oc-embroidery-stitch)' : color;
			svg.setAttribute( 'color', color );
			svg.setAttribute( 'fill', paint );
			this.forceSvgPreviewColour( svg, paint );
			if ( effect === 'embroidery' ) {
				this.addEmbroiderySvgPattern( svg, color );
			}
			this.ensureSvgIntrinsicSize( svg );
			const output = new window.XMLSerializer().serializeToString( svg );
			this.clipartSvgCache[
				key
			] = `data:image/svg+xml;charset=utf-8,${ encodeURIComponent(
				output
			) }`;
			return this.clipartSvgCache[ key ];
		} catch ( e ) {
			console.warn( '[OC] SVG clipart recolour failed:', e, 'URL:', url );
			return url;
		} finally {
			request.release();
		}
	},

	async normaliseSvgClipartUrl( url ) {
		if ( ! this.isSvgClipartUrl( url ) ) {
			return url;
		}

		const key = `${ url }|normalise`;
		if ( this.clipartSvgCache[ key ] ) {
			return this.clipartSvgCache[ key ];
		}

		const request = this.createStateAbortController( 10000 );
		try {
			const response = await fetch( url, {
				credentials: 'same-origin',
				cache: 'force-cache',
				signal: request.controller.signal,
			} );
			if ( ! response.ok ) {
				throw new Error(
					`Could not load clipart SVG (${ response.status }).`
				);
			}

			const raw = await response.text();
			const doc = new window.DOMParser().parseFromString(
				raw,
				'image/svg+xml'
			);
			const svg = doc.documentElement;
			if ( ! svg || svg.localName.toLowerCase() !== 'svg' ) {
				throw new Error( 'Clipart is not an SVG.' );
			}

			if ( ! this.ensureSvgIntrinsicSize( svg ) ) {
				this.clipartSvgCache[ key ] = url;
				return url;
			}

			const output = new window.XMLSerializer().serializeToString( svg );
			this.clipartSvgCache[
				key
			] = `data:image/svg+xml;charset=utf-8,${ encodeURIComponent(
				output
			) }`;
			return this.clipartSvgCache[ key ];
		} catch {
			this.clipartSvgCache[ key ] = url;
			return url;
		} finally {
			request.release();
		}
	},

	isSvgClipartUrl( url ) {
		const value = String( url || '' ).trim();
		return (
			/^data:image\/svg\+xml/i.test( value ) ||
			/\.svg(?:[?#]|$)/i.test( value )
		);
	},

	addEmbroiderySvgPattern( svg, color ) {
		const rgb = this.hexToRgb( color ) || { r: 0, g: 0, b: 0 };
		const hi = `rgb(${ Math.min( 255, rgb.r + 92 ) },${ Math.min(
			255,
			rgb.g + 92
		) },${ Math.min( 255, rgb.b + 92 ) })`;
		const lo = `rgb(${ Math.max( 0, rgb.r - 78 ) },${ Math.max(
			0,
			rgb.g - 78
		) },${ Math.max( 0, rgb.b - 78 ) })`;
		const ns = 'http://www.w3.org/2000/svg';
		const defs =
			svg.querySelector( 'defs' ) ||
			svg.insertBefore(
				document.createElementNS( ns, 'defs' ),
				svg.firstChild
			);
		const pattern = document.createElementNS( ns, 'pattern' );

		pattern.setAttribute( 'id', 'oc-embroidery-stitch' );
		pattern.setAttribute( 'patternUnits', 'userSpaceOnUse' );
		pattern.setAttribute( 'width', '12' );
		pattern.setAttribute( 'height', '12' );

		const bg = document.createElementNS( ns, 'rect' );
		bg.setAttribute( 'width', '12' );
		bg.setAttribute( 'height', '12' );
		bg.setAttribute( 'fill', color );
		pattern.appendChild( bg );

		[
			[ lo, '0.34', '-3' ],
			[ hi, '0.46', '3' ],
			[ lo, '0.2', '9' ],
		].forEach( ( [ stroke, opacity, x ] ) => {
			const line = document.createElementNS( ns, 'line' );
			line.setAttribute( 'x1', x );
			line.setAttribute( 'y1', '13' );
			line.setAttribute( 'x2', String( Number( x ) + 13 ) );
			line.setAttribute( 'y2', '-1' );
			line.setAttribute( 'stroke', stroke );
			line.setAttribute( 'stroke-width', '2' );
			line.setAttribute( 'stroke-linecap', 'round' );
			line.setAttribute( 'opacity', opacity );
			pattern.appendChild( line );
		} );

		defs.appendChild( pattern );
		return 'url(#oc-embroidery-stitch)';
	},

	forceSvgPreviewColour( element, color ) {
		const tagName = element.localName.toLowerCase();
		if ( tagName === 'style' ) {
			element.textContent = this.recolourSvgCss(
				element.textContent || '',
				color
			);
			return;
		}

		if ( tagName !== 'svg' ) {
			this.recolourSvgAttribute( element, 'fill', color );
			this.recolourSvgAttribute( element, 'stroke', color );

			if ( element.hasAttribute( 'style' ) ) {
				element.setAttribute(
					'style',
					this.recolourSvgStyle(
						element.getAttribute( 'style' ),
						color
					)
				);
			}

			const shapeTags = [
				'path',
				'rect',
				'circle',
				'ellipse',
				'polygon',
				'polyline',
				'text',
			];
			if (
				shapeTags.includes( tagName ) &&
				! element.hasAttribute( 'fill' ) &&
				! element.hasAttribute( 'stroke' ) &&
				! element.hasAttribute( 'style' )
			) {
				element.setAttribute( 'fill', color );
			}
		}

		Array.from( element.children ).forEach( ( child ) =>
			this.forceSvgPreviewColour( child, color )
		);
	},

	ensureSvgIntrinsicSize( svg ) {
		const viewBox = this.parseSvgViewBox( svg );
		if ( ! viewBox ) {
			return false;
		}

		let changed = false;
		if ( ! this.hasUsableSvgLength( svg.getAttribute( 'width' ) ) ) {
			svg.setAttribute( 'width', String( viewBox.width ) );
			changed = true;
		}
		if ( ! this.hasUsableSvgLength( svg.getAttribute( 'height' ) ) ) {
			svg.setAttribute( 'height', String( viewBox.height ) );
			changed = true;
		}

		return changed;
	},

	parseSvgViewBox( svg ) {
		const raw = String( svg.getAttribute( 'viewBox' ) || '' ).trim();
		const values = raw
			.split( /[\s,]+/ )
			.map( Number )
			.filter( ( value ) => Number.isFinite( value ) );
		if ( values.length !== 4 || values[ 2 ] <= 0 || values[ 3 ] <= 0 ) {
			return null;
		}

		return {
			x: values[ 0 ],
			y: values[ 1 ],
			width: values[ 2 ],
			height: values[ 3 ],
		};
	},

	hasUsableSvgLength( value ) {
		const raw = String( value || '' ).trim();
		if ( ! raw || raw.endsWith( '%' ) ) {
			return false;
		}

		return parseFloat( raw ) > 0;
	},

	removeInvisibleSvgShapes( element ) {
		Array.from( element.children ).forEach( ( child ) => {
			this.removeInvisibleSvgShapes( child );

			const tagName = child.localName.toLowerCase();
			const shapeTags = [
				'path',
				'rect',
				'circle',
				'ellipse',
				'polygon',
				'polyline',
				'line',
				'text',
			];
			if ( ! shapeTags.includes( tagName ) ) {
				return;
			}

			const fill = ( child.getAttribute( 'fill' ) || '' )
				.trim()
				.toLowerCase();
			const stroke = ( child.getAttribute( 'stroke' ) || '' )
				.trim()
				.toLowerCase();
			const style = ( child.getAttribute( 'style' ) || '' )
				.replace( /\s+/g, '' )
				.toLowerCase();
			const hasVisibleFill =
				( fill && fill !== 'none' ) ||
				/fill:(?!none(?:;|$))/.test( style );
			const hasVisibleStroke =
				( stroke && stroke !== 'none' ) ||
				/stroke:(?!none(?:;|$))/.test( style );

			if ( ! hasVisibleFill && ! hasVisibleStroke ) {
				child.remove();
			}
		} );
	},

	recolourSvgAttribute( element, attribute, color ) {
		if ( ! element.hasAttribute( attribute ) ) {
			return;
		}

		const value = element.getAttribute( attribute ).trim();
		if ( value.toLowerCase() === 'none' ) {
			return;
		}

		element.setAttribute( attribute, color );
	},

	recolourSvgStyle( style, color ) {
		return style.replace(
			/\b(fill|stroke)\s*:\s*([^;]+)/gi,
			( match, property, value ) => {
				const trimmed = String( value || '' ).trim();
				if ( trimmed.toLowerCase() === 'none' ) {
					return match;
				}

				return `${ property }:${ color }`;
			}
		);
	},

	recolourSvgCss( css, color ) {
		return css.replace(
			/\b(fill|stroke)\s*:\s*([^;}]+)/gi,
			( match, property, value ) => {
				const trimmed = String( value || '' ).trim();
				if ( trimmed.toLowerCase() === 'none' ) {
					return match;
				}

				return `${ property }:${ color }`;
			}
		);
	},

	async renderFabricImg(
		canvas,
		url,
		x,
		y,
		w,
		h,
		isEngraving = false,
		crossOrigin = 'anonymous',
		makeWhiteTransparent = false,
		angle = 0,
		engravingPalette = null,
		clipPath = null,
		fit = 'contain',
		tintColor = '',
		effects = {},
		isCurrent = () => true
	) {
		try {
			const imgLoadOpts = crossOrigin ? { crossOrigin } : {};
			const img = await this.loadFabricImage( url, imgLoadOpts, 10000 );
			if ( ! isCurrent() ) {
				return false;
			}
			if ( ! img || ! img.width ) {
				console.warn(
					'[OC] Image failed to load or has zero dimensions:',
					url
				);
				return false;
			}
			const palette = engravingPalette || this.engravingPalette();
			let isDitheredEngraving = Boolean(
				isEngraving && effects.photoEngraving && palette.photoDither
			);
			if ( isDitheredEngraving ) {
				const dithered = this.silverPlaquePhotoDither(
					img.getElement(),
					w,
					h
				);
				if ( dithered ) {
					img.setElement( dithered );
				} else {
					isDitheredEngraving = false;
				}
			}
			const s =
				fit === 'cover'
					? Math.max( w / img.width, h / img.height )
					: Math.min( w / img.width, h / img.height );
			img.set( {
				left: x + w / 2,
				top: y + h / 2,
				originX: 'center',
				originY: 'center',
				scaleX: s,
				scaleY: s,
				angle,
				selectable: false,
				evented: false,
				imageSmoothing: ! isDitheredEngraving,
			} );

			const filters = [];
			if (
				makeWhiteTransparent ||
				( isEngraving &&
					! effects.preserveRecolouredPixels &&
					! isDitheredEngraving )
			) {
				filters.push(
					new FabricFilters.RemoveColor( {
						color: '#FFFFFF',
						distance: isEngraving ? 0.18 : 0.1,
					} )
				);
			}
			if ( tintColor && FabricFilters.BlendColor ) {
				filters.push(
					new FabricFilters.BlendColor( {
						color: tintColor,
						mode: 'tint',
						alpha: 1,
					} )
				);
			}
			if ( effects.imageFilter && ! isDitheredEngraving ) {
				this.addConfiguredImageFilter( filters, effects.imageFilter );
			}
			if (
				effects.imageColor &&
				FabricFilters.BlendColor &&
				! isDitheredEngraving
			) {
				filters.push(
					new FabricFilters.BlendColor( {
						color: effects.imageColor,
						mode: 'tint',
						alpha: 1,
					} )
				);
			}
			if (
				isEngraving &&
				! effects.preserveRecolouredPixels &&
				! isDitheredEngraving
			) {
				filters.push(
					new FabricFilters.Grayscale(),
					new FabricFilters.Brightness( {
						brightness: palette.brightness,
					} ),
					new FabricFilters.Contrast( { contrast: palette.contrast } )
				);
				if ( palette.imageTint && FabricFilters.BlendColor ) {
					filters.push(
						new FabricFilters.BlendColor( {
							color: palette.imageTint,
							mode: 'tint',
							alpha: palette.tintAlpha ?? 1,
						} )
					);
				}
				if ( palette.noise && FabricFilters.Noise ) {
					filters.push(
						new FabricFilters.Noise( { noise: palette.noise } )
					);
				}
			}
			if (
				( effects.embroidery || effects.embroideryColor ) &&
				FabricFilters.Noise
			) {
				filters.push(
					new FabricFilters.Contrast( { contrast: 0.08 } ),
					new FabricFilters.Noise( { noise: 22 } )
				);
			}
			if ( filters.length ) {
				img.filters = filters;
				img.applyFilters();
			}
			if ( isEngraving && effects.preserveRecolouredPixels ) {
				img.set( {
					opacity: palette.opacity,
					globalCompositeOperation:
						palette.composite || 'source-over',
				} );
			} else if ( isEngraving ) {
				img.set( {
					opacity: palette.opacity,
					globalCompositeOperation:
						palette.composite || 'source-over',
					shadow: new Shadow( {
						color: palette.highlight,
						offsetX: 0,
						offsetY: 1,
						blur: 1,
					} ),
				} );
			} else if ( effects.embroidery || effects.embroideryColor ) {
				img.set( {
					shadow: new Shadow( {
						color: 'rgba(0,0,0,0.24)',
						offsetX: 0.7,
						offsetY: 0.95,
						blur: 1.1,
					} ),
				} );
			}

			img._ocContent = true;
			img._ocSourceUrl = url;
			img._ocSnapshotColor =
				effects.imageColor ||
				effects.embroideryColor ||
				tintColor ||
				'';
			img._ocSnapshotInlineSvg = filters.length === 0;
			this.applyContentClip( img, clipPath );
			if ( ! isCurrent() ) {
				return false;
			}
			canvas.add( img );
			return true;
		} catch ( e ) {
			console.warn( '[OC] renderFabricImg error:', e, 'URL:', url );
			return false;
		}
	},

	imageFilterForLayer( layer, filterId ) {
		filterId = parseInt( filterId, 10 ) || 0;
		if ( ! filterId ) {
			return null;
		}
		const allowedIds = Array.isArray( layer?.settings?.image_filter_ids )
			? layer.settings.image_filter_ids.map( Number )
			: [];
		if ( ! allowedIds.includes( filterId ) ) {
			return null;
		}
		return ( this.data?.imageFilters || [] ).find(
			( filter ) => Number( filter.id ) === filterId
		);
	},

	addConfiguredImageFilter( filters, config ) {
		const key = String( config?.key || '' );
		const value = Number( config?.value );
		const amount = Number.isFinite( value ) ? value : 1;
		switch ( key ) {
			case 'grayscale':
				if ( FabricFilters.Grayscale ) {
					filters.push( new FabricFilters.Grayscale() );
				}
				break;
			case 'sepia':
				if ( FabricFilters.Sepia ) {
					filters.push( new FabricFilters.Sepia() );
				}
				break;
			case 'brightness':
				if ( FabricFilters.Brightness ) {
					filters.push(
						new FabricFilters.Brightness( { brightness: amount } )
					);
				}
				break;
			case 'contrast':
				if ( FabricFilters.Contrast ) {
					filters.push(
						new FabricFilters.Contrast( { contrast: amount } )
					);
				}
				break;
			case 'saturation':
				if ( FabricFilters.Saturation ) {
					filters.push(
						new FabricFilters.Saturation( { saturation: amount } )
					);
				}
				break;
			case 'hue':
				if ( FabricFilters.HueRotation ) {
					filters.push(
						new FabricFilters.HueRotation( { rotation: amount } )
					);
				}
				break;
		}
	},

	fontCacheKey( font ) {
		return [
			font?.name || '',
			font?.weight || 'normal',
			font?.style || 'normal',
			font?.url || '',
		].join( '|' );
	},

	async loadFont( font ) {
		if ( ! font?.name || ! font?.url ) {
			return;
		}
		const key = this.fontCacheKey( font );
		if ( this.fontCache[ key ] ) {
			return this.fontCache[ key ];
		}
		const ff = new FontFace( font.name, `url('${ font.url }')`, {
			weight: font.weight || 'normal',
			style: font.style || 'normal',
		} );
		this.fontCache[ key ] = ff
			.load()
			.then( ( f ) => document.fonts.add( f ) )
			.catch( ( err ) => {
				delete this.fontCache[ key ];
				console.warn( '[OC] Font load failed:', err );
				throw err;
			} );
		return this.fontCache[ key ];
	},
};

export default canvasRendererMethods;
