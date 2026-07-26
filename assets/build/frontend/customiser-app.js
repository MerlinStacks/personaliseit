/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./src/frontend/customiser/canvas-renderer.js"
/*!****************************************************!*\
  !*** ./src/frontend/customiser/canvas-renderer.js ***!
  \****************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var fabric__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! fabric */ "./node_modules/fabric/dist/index.min.mjs");
/* harmony import */ var _shared_render_math__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../../shared/render-math */ "./src/shared/render-math.js");
/* eslint-disable no-console, no-undef, no-unused-vars, no-nested-ternary, @wordpress/no-unused-vars-before-return */



const canvasRendererMethods = {
  // ── Canvas initialisation ──────────────────────────────────────────────────

  startCanvasInitialisation() {
    const generation = this._designGeneration;
    this._canvasReadyGeneration = generation;
    const task = this.initAllCanvases(generation);
    this._canvasReadyPromise = task;
    return task;
  },
  async awaitCanvasReady(generation = this._designGeneration) {
    const task = this._canvasReadyPromise;
    if (!task || this._canvasReadyGeneration !== generation) {
      throw new Error('The customisation preview is not ready.');
    }
    await task;
    if (generation !== this._designGeneration || this._canvasReadyGeneration !== generation) {
      throw new Error('The selected design changed while rendering.');
    }
    if (this.areas.some((_, index) => !this.canvases[index])) {
      throw new Error('The customisation preview is not ready.');
    }
  },
  async initAllCanvases(designGeneration = this._designGeneration) {
    for (let i = 0; i < this.areas.length; i++) {
      if (designGeneration !== this._designGeneration) {
        return;
      }
      const el = document.getElementById(`oc-canvas-${i}`);
      if (el) {
        await this.initCanvas(el, i, designGeneration);
        if (designGeneration !== this._designGeneration) {
          return;
        }
        // Full redraw AFTER init picks up any text the user already typed.
        await this.redraw(i);
      }
    }
  },
  async initCanvas(canvasEl, areaIndex, designGeneration = this._designGeneration) {
    const area = this.areas[areaIndex];
    const bounds = this.areaBounds(area);

    // Use mockup natural width when available (works even when canvas is visually hidden).
    // Cap at 1200px for performance; fall back to element width or 600px.
    await new Promise(r => requestAnimationFrame(r));
    if (designGeneration !== this._designGeneration) {
      return;
    }
    const displayW = area.mockupW ? Math.min(area.mockupW, 1200) : Math.max(canvasEl.parentElement?.offsetWidth || 0, 600);
    if (!area.mockupUrl) {
      this.canvases[areaIndex] = this.blankCanvas(canvasEl, displayW, 240, 'No mockup set. Add one in the Design Editor.');
      this.canvases[areaIndex]._ocMissingMockup = true;
      return;
    }
    let mockupImg;
    try {
      // Do NOT use crossOrigin:'anonymous' — WordPress uploads are same-origin
      // and CORS headers aren't sent, which would taint the canvas and break toDataURL.
      mockupImg = await this.loadFabricImage(area.mockupUrl, {}, 10000);
    } catch (e) {
      if (designGeneration !== this._designGeneration) {
        return;
      }
      console.warn('[OC] Mockup failed to load:', area.mockupUrl, e.message);
      this.canvases[areaIndex] = this.blankCanvas(canvasEl, displayW, 240, 'Mockup image could not load.');
      this.canvases[areaIndex]._ocMissingMockup = true;
      return;
    }
    const mockupEl = mockupImg.getElement?.();
    const sourceW = mockupEl?.naturalWidth || mockupImg.width || area.mockupW || 1;
    const sourceH = mockupEl?.naturalHeight || mockupImg.height || area.mockupH || 1;
    const coordW = area.mockupW || sourceW;
    const coordH = area.mockupH || sourceH;
    const scaleX = displayW / coordW;
    const displayH = Math.round(coordH * scaleX);
    const canvas = new fabric__WEBPACK_IMPORTED_MODULE_0__.StaticCanvas(canvasEl, {
      width: displayW,
      height: displayH
    });
    if (designGeneration !== this._designGeneration) {
      canvas.dispose();
      return;
    }
    mockupImg.set({
      left: 0,
      top: 0,
      originX: 'left',
      originY: 'top',
      scaleX: displayW / sourceW,
      scaleY: displayH / sourceH,
      selectable: false,
      evented: false
    });
    canvas.add(mockupImg);
    canvas._ocScaleX = scaleX;
    canvas._ocArea = area;
    canvas.renderAll();
    this.canvases[areaIndex] = canvas;
  },
  async loadFabricImage(url, options = {}, timeoutMs = 10000) {
    const request = this.createStateAbortController(timeoutMs);
    try {
      return await fabric__WEBPACK_IMPORTED_MODULE_0__.FabricImage.fromURL(url, {
        ...options,
        signal: request.controller.signal
      });
    } catch (error) {
      if (request.timedOut()) {
        throw new Error('Image load timed out.');
      }
      throw error;
    } finally {
      request.release();
    }
  },
  areaBounds(area) {
    return {
      ...(area?.bounds || {}),
      unit: area?.bounds?.unit || area?.unit || 'px'
    };
  },
  areaCanvasGroupIndexes(areaIndex) {
    const area = this.areas[areaIndex];
    const mockupUrl = area?.mockupUrl || '';
    if (!mockupUrl) {
      return [areaIndex];
    }
    return this.areas.map((candidate, index) => (candidate?.mockupUrl || '') === mockupUrl ? index : -1).filter(index => index >= 0);
  },
  async rebuildCanvas(areaIndex) {
    const oldCanvas = this.canvases[areaIndex];
    if (oldCanvas?.dispose) {
      oldCanvas.dispose();
    }
    delete this.canvases[areaIndex];
    const oldEl = document.getElementById(`oc-canvas-${areaIndex}`);
    if (!oldEl) {
      return;
    }
    const canvasEl = document.createElement('canvas');
    canvasEl.id = oldEl.id;
    oldEl.replaceWith(canvasEl);
    await this.initCanvas(canvasEl, areaIndex);
    await this.redraw(areaIndex);
  },
  blankCanvas(el, w, h, msg) {
    const c = new fabric__WEBPACK_IMPORTED_MODULE_0__.StaticCanvas(el, {
      width: w,
      height: h,
      backgroundColor: '#f0f0f0'
    });
    const t = new fabric__WEBPACK_IMPORTED_MODULE_0__.FabricText(msg, {
      left: w / 2,
      top: h / 2,
      originX: 'center',
      originY: 'center',
      fontSize: 12,
      fill: '#888',
      fontFamily: 'sans-serif',
      textAlign: 'center',
      selectable: false
    });
    c.add(t);
    c.renderAll();
    c._ocScaleX = 1;
    return c;
  },
  // ── Redraw ──────────────────────────────────────────────────────────────────

  areaIndexForLayer(layerId) {
    for (let i = 0; i < this.areas.length; i++) {
      if ((this.areas[i]?.layers || []).some(layer => parseInt(layer.id, 10) === parseInt(layerId, 10))) {
        return i;
      }
    }
    return this.activeArea;
  },
  focusPreviewArea(areaIndex) {
    const index = Number.isInteger(areaIndex) ? areaIndex : this.activeArea;
    this.applyActiveAreaState(index);
  },
  scheduleRedraw(areaIndex = this.activeArea) {
    clearTimeout(this._redrawTimers[areaIndex]);
    this._redrawTimers[areaIndex] = setTimeout(() => this.redraw(areaIndex), 120);
  },
  async flushRedraw(inputs = this.inputs, options = {}) {
    Object.values(this._redrawTimers).forEach(clearTimeout);
    this._redrawTimers = {};
    Object.keys(this._redrawGenerations).forEach(areaIndex => {
      this._redrawGenerations[areaIndex] += 1;
    });
    await Promise.all(Object.values(this._redrawPromises));
    await this.awaitCanvasReady();
    await Promise.all(this.areas.map((_, areaIndex) => this.redraw(areaIndex, {
      ...options,
      inputs,
      pushGallery: options.pushGallery !== false && this.areaCanvasGroupIndexes(areaIndex).includes(this.activeArea)
    })));
  },
  redraw(areaIndex, options = {}) {
    const canvas = this.canvases[areaIndex];
    if (!canvas) {
      return Promise.resolve();
    } // canvas not ready yet — will redraw after initCanvas
    const generation = (this._redrawGenerations[areaIndex] || 0) + 1;
    this._redrawGenerations[areaIndex] = generation;
    const isCurrent = () => this._redrawGenerations[areaIndex] === generation && this.canvases[areaIndex] === canvas && this._customisationActive;
    const task = (async () => {
      canvas._ocRenderErrors = [];
      [...canvas.getObjects()].filter(o => o._ocContent === true).forEach(o => canvas.remove(o));
      const groupIndexes = options.renderGroup === false ? [areaIndex] : this.areaCanvasGroupIndexes(areaIndex);
      for (const groupIndex of groupIndexes) {
        const area = this.areas[groupIndex];
        for (const layer of area?.layers ?? []) {
          if (!isCurrent()) {
            return;
          }
          try {
            await this.renderLayer(canvas, layer, options.inputs?.[layer.id] || this.inputs[layer.id] || {}, area, isCurrent);
          } catch (err) {
            canvas._ocRenderErrors.push({
              layerId: layer?.id,
              message: err?.message || 'Layer render failed.'
            });
            console.warn('[OC] Layer render failed:', layer?.id, err);
          }
        }
      }
      if (!isCurrent()) {
        return;
      }
      canvas.renderAll();
      canvas._ocCartPreviewRevision = '';
      canvas._ocCartPreviewDataUrl = '';
      if (options.pushGallery !== false && this.areaCanvasGroupIndexes(areaIndex).includes(this.activeArea) && !canvas._ocMissingMockup) {
        this.pushToGallery(canvas);
      }
    })();
    this._redrawPromises[areaIndex] = task;
    return task.finally(() => {
      if (this._redrawPromises[areaIndex] === task) {
        delete this._redrawPromises[areaIndex];
      }
    });
  },
  async renderLayer(canvas, layer, input, area, isCurrent = () => true) {
    if (!isCurrent()) {
      return;
    }
    const scale = canvas._ocScaleX ?? 1;
    const areaBounds = this.areaBounds(area);
    const bounds = (0,_shared_render_math__WEBPACK_IMPORTED_MODULE_1__.displayBounds)(areaBounds);
    const layerBox = (0,_shared_render_math__WEBPACK_IMPORTED_MODULE_1__.displayLayer)(layer, areaBounds);
    const rotation = Number(bounds.rotation) || 0;
    const contentClip = () => this.printAreaClipPath(bounds, scale, layerBox);
    const center = this.rotatedLayerCenter(layerBox, bounds, rotation);
    const lx = (center.x - layerBox.w / 2) * scale;
    const ly = (center.y - layerBox.h / 2) * scale;
    const lw = Math.max(layerBox.w * scale, 10);
    const lh = Math.max(layerBox.h * scale, 10);
    const textClip = (pad = 0) => this.rectClipPath(lx - pad, ly - pad, lw + pad * 2, lh + pad * 2, rotation);
    const lcX = center.x * scale;
    const lcY = center.y * scale;
    const isEngraving = area?.printMethod === 'engraving';
    const isEmbroidery = area?.printMethod === 'embroidery';
    const engravingPalette = this.engravingPalette(area?.engravingMaterial);
    const fontLimit = value => this.fontLimit(value);
    const clampFontSize = (size, settings) => {
      const minLimit = fontLimit(settings?.min_font_size);
      const maxLimit = fontLimit(settings?.max_font_size);
      const min = minLimit ? (0,_shared_render_math__WEBPACK_IMPORTED_MODULE_1__.displayFontSize)(minLimit, areaBounds, scale) : 0;
      const max = maxLimit ? (0,_shared_render_math__WEBPACK_IMPORTED_MODULE_1__.displayFontSize)(maxLimit, areaBounds, scale) : 0;
      if (max && (!min || min <= max)) {
        size = Math.min(size, max);
      }
      if (min) {
        size = Math.max(size, min);
      }
      return size;
    };
    switch (layer.type) {
      case 'text':
      case 'textarea':
        {
          const isSingleLineText = layer.type === 'text';
          let inputValue = input.value;
          if (inputValue === undefined) {
            inputValue = layer.locked ? layer.settings?.default_text || '' : '';
          }
          const normalisedText = (isEngraving || isEmbroidery ? this.stripUnsupportedPrintEmoji(inputValue) : inputValue || '').replace(/\r\n?/g, '\n');
          const raw = isSingleLineText ? normalisedText.trim() : normalisedText;
          if (!raw.trim()) {
            break;
          }
          const lineAlign = ['top', 'center', 'bottom'].includes(layer.settings?.line_alignment) ? layer.settings.line_alignment : 'top';
          let font = this.fonts.find(f => f.id === (input.fontId || layer.settings?.default_font_id || 0));
          // Engraving colour follows the substrate rather than the customer's ink colour.
          const color = isEngraving ? engravingPalette.text : input.colorHex || layer.settings?.default_color || '#000000';
          const align = layer.settings?.alignment || 'center';
          if (font) {
            try {
              await this.loadFont(font);
            } catch (err) {
              console.warn('[OC] Font load failed, falling back to sans-serif:', err);
              font = null;
            }
          }
          if (!isCurrent()) {
            return;
          }
          const minLimit = fontLimit(layer.settings?.min_font_size);
          const minFontSize = minLimit ? (0,_shared_render_math__WEBPACK_IMPORTED_MODULE_1__.displayFontSize)(minLimit, areaBounds, scale) : 0;
          const configuredFontSize = input.fontSize || layer.settings?.default_font_size;
          let fontSize = configuredFontSize ? clampFontSize((0,_shared_render_math__WEBPACK_IMPORTED_MODULE_1__.displayFontSize)(parseInt(configuredFontSize, 10), areaBounds, scale), layer.settings) : clampFontSize(Math.max(10, Math.round(lh * 0.72)), layer.settings);
          const textFill = isEmbroidery ? this.embroideryPattern(color, fontSize) : isEngraving && engravingPalette.pattern === 'wood' ? this.woodEngravingPattern(fontSize) : isEngraving && engravingPalette.pattern === 'leather' ? this.leatherEngravingPattern(fontSize) : color;
          const textClass = isSingleLineText ? fabric__WEBPACK_IMPORTED_MODULE_0__.FabricText : fabric__WEBPACK_IMPORTED_MODULE_0__.Textbox;
          const textBoxSize = isSingleLineText ? {} : {
            width: lw
          };
          const singleLineMaxWidth = Math.max(1, lw);
          const singleLineMaxHeight = Math.max(1, lh);
          const obj = new textClass(raw, {
            left: lcX,
            top: lcY,
            originX: 'center',
            originY: 'center',
            ...textBoxSize,
            angle: rotation,
            fontFamily: font?.name || 'sans-serif',
            fontWeight: font?.weight || 'normal',
            fontStyle: font?.style || 'normal',
            fontSize,
            fill: textFill,
            textAlign: align,
            selectable: false,
            evented: false,
            objectCaching: false
          });
          obj._ocContent = true; // tag after creation
          let stitchPad = null;
          let stitchLift = null;
          const textareaPosition = (target, extraX = 0, extraY = 0) => {
            if (isSingleLineText || !target) {
              return;
            }
            target.initDimensions?.();
            const contentH = Math.min(Math.max(Number(target.getScaledHeight?.() || target.height || 0), 0), lh);
            const freeY = Math.max(0, (lh - contentH) / 2);
            const localY = lineAlign === 'bottom' ? freeY : lineAlign === 'center' ? 0 : -freeY;
            const rad = rotation * Math.PI / 180;
            target.set({
              left: lcX - localY * Math.sin(rad) + extraX,
              top: lcY + localY * Math.cos(rad) + extraY
            });
            target.setCoords?.();
          };
          if (isEngraving) {
            // Fake etched depth: subtle light highlight below + soft dark shadow above.
            obj.set({
              opacity: engravingPalette.opacity,
              globalCompositeOperation: engravingPalette.composite || 'source-over',
              shadow: new fabric__WEBPACK_IMPORTED_MODULE_0__.Shadow({
                color: engravingPalette.highlight,
                offsetX: 0,
                offsetY: 1,
                blur: 1
              })
            });
          } else if (isEmbroidery) {
            const threadLift = this.embroideryHighlightColor(color);
            const threadShadow = this.embroideryShadowColor(color);
            stitchPad = new textClass(raw, {
              left: lcX + Math.max(0.45, fontSize * 0.015),
              top: lcY + Math.max(0.65, fontSize * 0.02),
              originX: 'center',
              originY: 'center',
              ...textBoxSize,
              angle: rotation,
              fontFamily: font?.name || 'sans-serif',
              fontWeight: font?.weight || 'normal',
              fontStyle: font?.style || 'normal',
              fontSize,
              fill: threadShadow,
              opacity: 0.24,
              shadow: new fabric__WEBPACK_IMPORTED_MODULE_0__.Shadow({
                color: 'rgba(0,0,0,0.22)',
                offsetX: 0.6,
                offsetY: 0.9,
                blur: 1.8
              }),
              textAlign: align,
              selectable: false,
              evented: false,
              objectCaching: false
            });
            stitchPad._ocContent = true;
            canvas.add(stitchPad);
            stitchLift = new textClass(raw, {
              left: lcX - Math.max(0.25, fontSize * 0.006),
              top: lcY - Math.max(0.25, fontSize * 0.006),
              originX: 'center',
              originY: 'center',
              ...textBoxSize,
              angle: rotation,
              fontFamily: font?.name || 'sans-serif',
              fontWeight: font?.weight || 'normal',
              fontStyle: font?.style || 'normal',
              fontSize,
              fill: 'rgba(255,255,255,0)',
              stroke: threadLift,
              strokeWidth: Math.max(0.2, fontSize * 0.006),
              opacity: 0.22,
              textAlign: align,
              selectable: false,
              evented: false,
              objectCaching: false
            });
            stitchLift._ocContent = true;
            canvas.add(stitchLift);
            obj.set({
              stroke: this.embroiderySoftEdgeColor(color),
              strokeWidth: Math.max(0.18, fontSize * 0.005),
              shadow: new fabric__WEBPACK_IMPORTED_MODULE_0__.Shadow({
                color: 'rgba(0,0,0,0.22)',
                offsetX: 0.7,
                offsetY: 0.95,
                blur: 1.1
              })
            });
          }
          const fitsTextLayer = size => {
            if (isSingleLineText) {
              return this.textFitsBox(raw, font, size, layer.settings, singleLineMaxWidth, singleLineMaxHeight, false);
            }
            return this.textFitsBox(raw, font, size, layer.settings, lw, lh, true);
          };
          const fittingFloor = minFontSize || 4;
          while (!fitsTextLayer(fontSize) && fontSize > fittingFloor) {
            fontSize = Math.max(fittingFloor, fontSize - 1);
            obj.set({
              fontSize
            });
            if (stitchPad) {
              stitchPad.set({
                fontSize
              });
            }
            if (stitchLift) {
              stitchLift.set({
                fontSize
              });
            }
          }
          obj.initDimensions?.();
          const textareaScale = isSingleLineText ? 1 : 1;
          if (!isSingleLineText) {
            obj.set({
              scaleX: textareaScale,
              scaleY: textareaScale
            });
          }
          textareaPosition(obj);
          obj.setCoords?.();
          const measuredText = this.measureSingleLineText(raw, font, fontSize, layer.settings);
          const renderedWidth = Math.max(1, Math.ceil(measuredText.width));
          const singleLineScaleX = isSingleLineText ? Math.min(1, singleLineMaxWidth / renderedWidth) : 1;
          if (isSingleLineText) {
            let alignedLeft = lcX;
            let alignedTop = lcY;
            let alignmentOffset = 0;
            const renderedDisplayWidth = renderedWidth * singleLineScaleX;
            if (align === 'left') {
              alignmentOffset = -lw / 2 + renderedDisplayWidth / 2;
            } else if (align === 'right') {
              alignmentOffset = lw / 2 - renderedDisplayWidth / 2;
            }
            if (alignmentOffset) {
              const rad = rotation * Math.PI / 180;
              alignedLeft += alignmentOffset * Math.cos(rad);
              alignedTop += alignmentOffset * Math.sin(rad);
            }
            obj.set({
              left: alignedLeft,
              top: alignedTop,
              scaleX: singleLineScaleX
            });
            obj.initDimensions?.();
            obj.setCoords?.();
            this.centerObjectBounds(obj, alignedLeft, alignedTop, rotation);
            this.keepObjectInsidePrintArea(obj, bounds, scale);
          }
          if (isEmbroidery) {
            obj.set({
              fill: this.embroideryPattern(color, fontSize)
            });
          }
          const textClipPath = textClip(this.textClipPadding(fontSize));
          if (stitchPad) {
            const padX = Math.max(0.45, fontSize * 0.015);
            const padY = Math.max(0.65, fontSize * 0.02);
            stitchPad.set({
              left: (isSingleLineText ? obj.left : lcX) + padX,
              top: (isSingleLineText ? obj.top : lcY) + padY,
              fontSize
            });
            if (isSingleLineText) {
              stitchPad.set({
                scaleX: singleLineScaleX
              });
            } else {
              stitchPad.set({
                scaleX: textareaScale,
                scaleY: textareaScale
              });
              textareaPosition(stitchPad, padX, padY);
            }
            this.applyContentClip(stitchPad, textClipPath);
          }
          if (stitchLift) {
            const liftX = Math.max(0.25, fontSize * 0.006);
            const liftY = Math.max(0.25, fontSize * 0.006);
            stitchLift.set({
              left: (isSingleLineText ? obj.left : lcX) - liftX,
              top: (isSingleLineText ? obj.top : lcY) - liftY,
              fontSize,
              strokeWidth: Math.max(0.2, fontSize * 0.006)
            });
            if (isSingleLineText) {
              stitchLift.set({
                scaleX: singleLineScaleX
              });
            } else {
              stitchLift.set({
                scaleX: textareaScale,
                scaleY: textareaScale
              });
              textareaPosition(stitchLift, -liftX, -liftY);
            }
            this.applyContentClip(stitchLift, textClipPath);
          }
          this.applyContentClip(obj, textClipPath);
          canvas.add(obj);
          break;
        }
      case 'image':
        {
          if (input.attachmentUrl) {
            const imageFilter = this.imageFilterForLayer(layer, input.imageFilterId);
            const imageEffects = {
              ...(imageFilter ? {
                imageFilter
              } : {}),
              ...(imageFilter && layer.settings?.enable_image_colour ? {
                imageColor: input.colorHex || layer.settings?.default_color || '#000000'
              } : {}),
              ...(isEmbroidery ? {
                embroidery: true
              } : {}),
              ...(isEngraving ? {
                photoEngraving: true
              } : {})
            };
            const rendered = await this.renderFabricImg(canvas, input.attachmentUrl, lx, ly, lw, lh, isEngraving, 'anonymous', false, rotation, engravingPalette, contentClip(), 'contain', '', imageEffects, isCurrent);
            if (!rendered && isCurrent()) {
              throw new Error('Artwork image could not be rendered.');
            }
          }
          break;
        }
      case 'clipmask':
        {
          if (input.attachmentUrl) {
            const rendered = await this.renderFabricImg(canvas, input.attachmentUrl, lx, ly, lw, lh, isEngraving, 'anonymous', false, rotation, engravingPalette, this.layerClipPath(lx, ly, lw, lh, rotation, layer.settings), 'cover', '', {
              photoEngraving: isEngraving
            }, isCurrent);
            if (!rendered && isCurrent()) {
              throw new Error('Masked artwork could not be rendered.');
            }
          }
          break;
        }
      case 'clipart':
        {
          if (input.clipartUrl) {
            const selectedClipartColor = String(input.colorHex || '').trim();
            const shouldRecolourClipart = Boolean(input.clipartRecolourable && (selectedClipartColor || isEngraving || isEmbroidery));
            const clipartColor = shouldRecolourClipart ? isEngraving ? engravingPalette.text : selectedClipartColor : '';
            const clipartUrl = clipartColor ? await this.recolourSvgClipartUrl(input.clipartUrl, clipartColor, isEmbroidery ? 'embroidery' : '') : await this.normaliseSvgClipartUrl(input.clipartUrl);
            const clipartCrossOrigin = clipartUrl.startsWith('data:') ? '' : 'anonymous';
            const clipartEffects = isEmbroidery ? {
              embroideryColor: clipartColor || selectedClipartColor || '#000000'
            } : shouldRecolourClipart ? {
              preserveRecolouredPixels: true
            } : {};
            const rendered = await this.renderFabricImg(canvas, clipartUrl, lx, ly, lw, lh, isEngraving, clipartCrossOrigin, false, rotation, engravingPalette, contentClip(), 'contain', '', clipartEffects, isCurrent);
            if (!rendered && isCurrent()) {
              throw new Error('Clipart could not be rendered.');
            }
          }
          break;
        }
      case 'lineart':
        {
          const lineartColor = isEngraving ? engravingPalette.text : String(input.colorHex || '').trim();
          if (!lineartColor) {
            break;
          }
          const r = new fabric__WEBPACK_IMPORTED_MODULE_0__.Rect({
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
            evented: false
          });
          r._ocContent = true;
          this.applyContentClip(r, contentClip());
          canvas.add(r);
          break;
        }
      case 'spotify':
        {
          const val = (input.value || '').trim();
          if (!val) {
            break;
          }
          if (input.spotifyStatus === 'invalid_format' || input.spotifyStatus === 'playlist_private_or_invalid' || input.spotifyStatus === 'invalid_or_unavailable') {
            const invalidText = input.spotifyStatus === 'playlist_private_or_invalid' ? 'Private / invalid Spotify playlist' : 'Invalid Spotify link';
            const invalidObj = new fabric__WEBPACK_IMPORTED_MODULE_0__.FabricText(invalidText, {
              left: lcX,
              top: lcY,
              originX: 'center',
              originY: 'center',
              angle: rotation,
              fontFamily: 'monospace',
              fontSize: Math.max(9, Math.round(lh * 0.17)),
              fill: '#b32d2e',
              textAlign: 'center',
              selectable: false,
              evented: false
            });
            invalidObj._ocContent = true;
            this.applyContentClip(invalidObj, contentClip());
            canvas.add(invalidObj);
            break;
          }
          const spotifyCodeUrl = this.buildSpotifyCodeUrl(input.spotifyUri || val, isEngraving, engravingPalette);
          if (spotifyCodeUrl) {
            // Try CORS-safe load first; if Spotify CDN blocks CORS for this origin,
            // retry without crossOrigin so users still see the scannable in live preview.
            let rendered = await this.renderFabricImg(canvas, spotifyCodeUrl, lx, ly, lw, lh, isEngraving, 'anonymous', true, rotation, engravingPalette, contentClip(), 'contain', '', {}, isCurrent);
            if (!rendered) {
              rendered = await this.renderFabricImg(canvas, spotifyCodeUrl, lx, ly, lw, lh, isEngraving, '', true, rotation, engravingPalette, contentClip(), 'contain', '', {}, isCurrent);
            }
            if (rendered) {
              break;
            }
          }
          if (!isCurrent()) {
            return;
          }
          const fallback = new fabric__WEBPACK_IMPORTED_MODULE_0__.FabricText('\u266b Spotify code unavailable', {
            left: lcX,
            top: lcY,
            originX: 'center',
            originY: 'center',
            angle: rotation,
            fontFamily: 'monospace',
            fontSize: Math.max(9, Math.round(lh * 0.22)),
            fill: '#666666',
            textAlign: 'center',
            selectable: false,
            evented: false
          });
          fallback._ocContent = true;
          this.applyContentClip(fallback, contentClip());
          canvas.add(fallback);
          break;
        }
    }
  },
  fontLimit(value) {
    return Math.max(0, parseInt(value, 10) || 0);
  },
  textClipPadding(fontSize) {
    return Math.max(2, Math.ceil((Number(fontSize) || 0) * 0.18));
  },
  textFitSafetyMargin(fontSize) {
    const size = Number(fontSize) || 0;
    return {
      x: Math.max(1, Math.ceil(size * 0.06)),
      y: Math.max(2, Math.ceil(size * 0.12))
    };
  },
  textFitsBox(raw, font, fontSize, settings, maxW, maxH, multiline = false) {
    if (!raw) {
      return true;
    }
    const margin = multiline ? this.textFitSafetyMargin(fontSize) : {
      x: 0,
      y: 0
    };
    const textClass = multiline ? fabric__WEBPACK_IMPORTED_MODULE_0__.Textbox : fabric__WEBPACK_IMPORTED_MODULE_0__.FabricText;
    const textBoxSize = multiline ? {
      width: Math.max(1, maxW)
    } : {};
    const obj = new textClass(raw, {
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
      evented: false
    });
    obj.initDimensions?.();
    obj.setCoords?.();
    const measured = obj.getBoundingRect?.(true, true) || obj;
    if (multiline) {
      return Number(measured.height || 0) + margin.y * 2 <= Math.max(maxH, 10);
    }
    return Number(measured.width || 0) + margin.x * 2 <= Math.max(maxW, 10) && Number(measured.height || 0) + margin.y * 2 <= Math.max(maxH, 10);
  },
  measureSingleLineText(raw, font, fontSize, settings = {}) {
    const obj = new fabric__WEBPACK_IMPORTED_MODULE_0__.FabricText(raw || '', {
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
      evented: false
    });
    obj.setCoords?.();
    const measured = obj.getBoundingRect?.(true, true) || obj;
    return {
      width: Number(measured.width || 0),
      height: Number(measured.height || 0)
    };
  },
  textLayerFitsAtSize(layer, raw, font, fontSize) {
    const area = this.areas[this.areaIndexForLayer(layer?.id)];
    const bounds = area ? this.areaBounds(area) : null;
    const layerBox = bounds ? (0,_shared_render_math__WEBPACK_IMPORTED_MODULE_1__.displayLayer)(layer, bounds) : layer;
    const displaySize = bounds ? (0,_shared_render_math__WEBPACK_IMPORTED_MODULE_1__.displayFontSize)(fontSize, bounds) : fontSize;
    return this.textFitsBox(raw, font, displaySize, layer?.settings || {}, Number(layerBox?.w || 0), Number(layerBox?.h || 0), layer?.type === 'textarea');
  },
  async maxFittingFontSize(layerId, upperLimit) {
    const layer = this.getLayerById(layerId);
    if (!layer || !['text', 'textarea'].includes(layer.type)) {
      return upperLimit;
    }
    const maxLimit = this.fontLimit(layer.settings?.max_font_size);
    if (maxLimit) {
      upperLimit = Math.min(upperLimit, maxLimit);
    }
    const input = this.inputs[layerId] || {};
    const normalisedText = String(input.value || '').replace(/\r\n?/g, '\n');
    const raw = layer.type === 'text' ? normalisedText.trim() : normalisedText;
    if (!raw.trim()) {
      return upperLimit;
    }
    let font = this.fonts.find(f => f.id === (input.fontId || layer.settings?.default_font_id || 0));
    if (font) {
      try {
        await this.loadFont(font);
      } catch {
        font = null;
      }
    }
    const min = this.fontLimit(layer.settings?.min_font_size) || 1;
    let low = min;
    let high = Math.max(min, upperLimit);
    let best = min;
    while (low <= high) {
      const mid = Math.floor((low + high) / 2);
      if (this.textLayerFitsAtSize(layer, raw, font, mid)) {
        best = mid;
        low = mid + 1;
      } else {
        high = mid - 1;
      }
    }
    return Math.max(min, Math.min(upperLimit, best));
  },
  async updateTextSizeSliderCap(layerId, clampValue = true) {
    const sizeEl = document.querySelector(`[data-oc-layer-font-size="${layerId}"]`);
    if (!sizeEl) {
      return;
    }
    if (!sizeEl.dataset.ocOriginalMax) {
      sizeEl.dataset.ocOriginalMax = sizeEl.max || '200';
    }
    const originalMax = Math.max(parseInt(sizeEl.dataset.ocOriginalMax, 10) || 200, parseInt(sizeEl.min, 10) || 1);
    const layer = this.getLayerById(layerId);
    const configuredMax = this.fontLimit(layer?.settings?.max_font_size);
    let cappedMax = Math.max(parseInt(sizeEl.min, 10) || 1, configuredMax ? Math.min(originalMax, configuredMax) : originalMax);
    cappedMax = await this.maxFittingFontSize(layerId, cappedMax);
    sizeEl.max = String(cappedMax);
    const hasAdjustableRange = cappedMax > (parseInt(sizeEl.min, 10) || 1);
    const control = sizeEl.closest('[data-oc-font-size-control]');
    control?.querySelector('[data-oc-font-size-label]')?.toggleAttribute('hidden', !hasAdjustableRange);
    sizeEl.toggleAttribute('hidden', !hasAdjustableRange);
    control?.querySelector('[data-oc-font-size-notice]')?.toggleAttribute('hidden', hasAdjustableRange);
    if (clampValue && parseInt(sizeEl.value, 10) > cappedMax) {
      sizeEl.value = String(cappedMax);
      if (!this.inputs[layerId]) {
        this.inputs[layerId] = {};
      }
      this.inputs[layerId].fontSize = cappedMax;
    }
    document.querySelector(`.oc-range-value[data-oc-range-value="${layerId}"]`)?.replaceChildren(document.createTextNode(sizeEl.value));
  },
  rotatedLayerCenter(layer, bounds, rotation) {
    let x = layer.x + layer.w / 2;
    let y = layer.y + layer.h / 2;
    if (!bounds?.w || !rotation) {
      return {
        x,
        y
      };
    }
    const cx = bounds.x + bounds.w / 2;
    const cy = bounds.y + bounds.h / 2;
    const rad = rotation * Math.PI / 180;
    const dx = x - cx;
    const dy = y - cy;
    x = cx + dx * Math.cos(rad) - dy * Math.sin(rad);
    y = cy + dx * Math.sin(rad) + dy * Math.cos(rad);
    return {
      x,
      y
    };
  },
  engravingPalette(material = 'silver_metal') {
    const palettes = {
      glass: {
        text: '#eef4f4',
        imageTint: '#eef4f4',
        bg: 'F7FAFA',
        highlight: 'rgba(255,255,255,0.7)',
        brightness: 0.16,
        contrast: -0.04,
        opacity: 0.62
      },
      gold_metal: {
        text: '#6f5227',
        imageTint: '#6f5227',
        bg: 'D9A72E',
        highlight: 'rgba(255,238,176,0.34)',
        brightness: -0.18,
        contrast: 0.22,
        opacity: 0.88
      },
      silver_metal: {
        text: '#c9c9c3',
        imageTint: '#c9c9c3',
        bg: 'ECEFF1',
        highlight: 'rgba(255,255,255,0.42)',
        brightness: -0.28,
        contrast: 0.18,
        opacity: 0.9
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
        photoDither: true
      },
      black_metal: {
        text: '#d8d8d8',
        imageTint: '#d8d8d8',
        bg: '1F2328',
        highlight: 'rgba(255,255,255,0.24)',
        brightness: -0.34,
        contrast: 0.28,
        opacity: 0.95
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
        pattern: 'wood'
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
        noise: 5
      }
    };
    return palettes[material] || palettes.silver_metal;
  },
  woodEngravingPattern(fontSize = 24) {
    const source = document.createElement('canvas');
    const width = Math.max(42, Math.min(96, Math.round(fontSize * 1.9)));
    const height = Math.max(14, Math.min(30, Math.round(fontSize * 0.48)));
    source.width = width;
    source.height = height;
    const ctx = source.getContext('2d');
    if (!ctx) {
      return 'rgba(78,42,20,0.7)';
    }
    ctx.fillStyle = 'rgba(78,42,20,0.64)';
    ctx.fillRect(0, 0, width, height);
    for (let y = 1; y < height; y += 4) {
      ctx.strokeStyle = 'rgba(255,220,165,0.16)';
      ctx.lineWidth = 1;
      ctx.beginPath();
      ctx.moveTo(0, y + y % 3 * 0.25);
      ctx.bezierCurveTo(width * 0.28, y - 1.3, width * 0.62, y + 1.2, width, y - 0.4);
      ctx.stroke();
      ctx.strokeStyle = 'rgba(54,26,12,0.18)';
      ctx.beginPath();
      ctx.moveTo(0, y + 1.8);
      ctx.bezierCurveTo(width * 0.32, y + 2.8, width * 0.7, y + 0.8, width, y + 1.6);
      ctx.stroke();
    }
    return new fabric__WEBPACK_IMPORTED_MODULE_0__.Pattern({
      source,
      repeat: 'repeat'
    });
  },
  leatherEngravingPattern(fontSize = 24) {
    const source = document.createElement('canvas');
    const size = Math.max(18, Math.min(36, Math.round(fontSize * 0.72)));
    source.width = size;
    source.height = size;
    const ctx = source.getContext('2d');
    if (!ctx) {
      return 'rgba(66,35,21,0.86)';
    }
    ctx.fillStyle = 'rgba(66,35,21,0.82)';
    ctx.fillRect(0, 0, size, size);

    // Fixed pore positions keep the grain stable between preview redraws.
    const pores = [[0.12, 0.18, 0.035], [0.43, 0.1, 0.025], [0.76, 0.22, 0.04], [0.26, 0.48, 0.03], [0.61, 0.55, 0.035], [0.9, 0.44, 0.025], [0.08, 0.8, 0.025], [0.48, 0.88, 0.04], [0.8, 0.76, 0.03]];
    pores.forEach(([x, y, radius], index) => {
      ctx.beginPath();
      ctx.fillStyle = index % 2 ? 'rgba(235,190,140,0.12)' : 'rgba(27,13,8,0.18)';
      ctx.ellipse(x * size, y * size, Math.max(0.55, radius * size), Math.max(0.4, radius * size * 0.58), index * Math.PI / 7, 0, Math.PI * 2);
      ctx.fill();
    });
    return new fabric__WEBPACK_IMPORTED_MODULE_0__.Pattern({
      source,
      repeat: 'repeat'
    });
  },
  silverPlaquePhotoDither(element, displayW, displayH) {
    try {
      const sourceW = Number(element?.naturalWidth || element?.width || 0);
      const sourceH = Number(element?.naturalHeight || element?.height || 0);
      if (!sourceW || !sourceH) {
        return null;
      }
      const maxDimension = Math.min(1200, Math.max(240, Math.round(Math.max(displayW, displayH) * 1.5)));
      const scale = Math.min(1, maxDimension / Math.max(sourceW, sourceH));
      const width = Math.max(1, Math.round(sourceW * scale));
      const height = Math.max(1, Math.round(sourceH * scale));
      const output = document.createElement('canvas');
      output.width = width;
      output.height = height;
      const ctx = output.getContext('2d', {
        willReadFrequently: true
      });
      if (!ctx) {
        return null;
      }
      ctx.drawImage(element, 0, 0, width, height);
      const image = ctx.getImageData(0, 0, width, height);
      const pixels = image.data;
      const matrix = [[0, 48, 12, 60, 3, 51, 15, 63], [32, 16, 44, 28, 35, 19, 47, 31], [8, 56, 4, 52, 11, 59, 7, 55], [40, 24, 36, 20, 43, 27, 39, 23], [2, 50, 14, 62, 1, 49, 13, 61], [34, 18, 46, 30, 33, 17, 45, 29], [10, 58, 6, 54, 9, 57, 5, 53], [42, 26, 38, 22, 41, 25, 37, 21]];
      for (let y = 0; y < height; y++) {
        for (let x = 0; x < width; x++) {
          const index = (y * width + x) * 4;
          const alpha = pixels[index + 3];
          const luminance = pixels[index] * 0.2126 + pixels[index + 1] * 0.7152 + pixels[index + 2] * 0.0722;
          const contrasted = Math.max(0, Math.min(255, (luminance - 128) * 1.22 + 142));
          const threshold = (matrix[y % 8][x % 8] + 0.5) * 4;
          const engraved = contrasted < threshold;
          pixels[index] = 17;
          pixels[index + 1] = 19;
          pixels[index + 2] = 21;
          pixels[index + 3] = engraved ? alpha : 0;
        }
      }
      ctx.putImageData(image, 0, 0);
      return output;
    } catch (error) {
      console.warn('[OC] Silver plaque photo dithering failed:', error);
      return null;
    }
  },
  embroideryPattern(color, fontSize = 24) {
    const source = document.createElement('canvas');
    const size = Math.max(10, Math.min(20, Math.round(fontSize * 0.18)));
    source.width = size;
    source.height = size;
    const ctx = source.getContext('2d');
    if (!ctx) {
      return color;
    }
    const rgb = this.hexToRgb(color) || {
      r: 0,
      g: 0,
      b: 0
    };
    const hi = {
      r: Math.min(255, rgb.r + 92),
      g: Math.min(255, rgb.g + 92),
      b: Math.min(255, rgb.b + 92)
    };
    const lo = {
      r: Math.max(0, rgb.r - 78),
      g: Math.max(0, rgb.g - 78),
      b: Math.max(0, rgb.b - 78)
    };
    const base = ctx.createLinearGradient(0, 0, source.width, source.height);
    base.addColorStop(0, `rgb(${hi.r},${hi.g},${hi.b})`);
    base.addColorStop(0.42, color);
    base.addColorStop(1, `rgb(${lo.r},${lo.g},${lo.b})`);
    ctx.fillStyle = base;
    ctx.fillRect(0, 0, source.width, source.height);
    ctx.lineWidth = Math.max(1.2, size * 0.12);
    ctx.lineCap = 'round';
    const stitchGap = Math.max(2.4, size * 0.24);
    let stitchIndex = 0;
    for (let i = -source.height; i < source.width * 2; i += stitchGap) {
      ctx.strokeStyle = stitchIndex % 2 ? `rgba(${hi.r},${hi.g},${hi.b},0.48)` : `rgba(${lo.r},${lo.g},${lo.b},0.24)`;
      ctx.beginPath();
      ctx.moveTo(i, source.height + 1.5);
      ctx.lineTo(i + source.height + 1.5, -1.5);
      ctx.stroke();
      stitchIndex += 1;
    }
    ctx.lineWidth = Math.max(0.45, size * 0.045);
    ctx.strokeStyle = `rgba(${lo.r},${lo.g},${lo.b},0.16)`;
    for (let i = -source.height; i < source.width * 2; i += Math.max(3.2, size * 0.32)) {
      ctx.beginPath();
      ctx.moveTo(i, source.height + 1);
      ctx.lineTo(i + source.height + 1, -1);
      ctx.stroke();
    }
    ctx.lineWidth = 0.5;
    ctx.strokeStyle = 'rgba(255,255,255,0.14)';
    for (let y = 1; y < source.height; y += 4) {
      ctx.beginPath();
      ctx.moveTo(0, y + 0.5);
      ctx.lineTo(source.width, y + 0.5);
      ctx.stroke();
    }
    return new fabric__WEBPACK_IMPORTED_MODULE_0__.Pattern({
      source,
      repeat: 'repeat'
    });
  },
  embroiderySoftEdgeColor(color) {
    const rgb = this.hexToRgb(color);
    if (!rgb) {
      return 'rgba(0,0,0,0.16)';
    }
    return `rgba(${Math.max(0, rgb.r - 36)},${Math.max(0, rgb.g - 36)},${Math.max(0, rgb.b - 36)},0.24)`;
  },
  embroideryHighlightColor(color) {
    const rgb = this.hexToRgb(color);
    if (!rgb) {
      return 'rgba(255,255,255,0.42)';
    }
    return `rgba(${Math.min(255, rgb.r + 88)},${Math.min(255, rgb.g + 88)},${Math.min(255, rgb.b + 88)},0.62)`;
  },
  embroideryShadowColor(color) {
    const rgb = this.hexToRgb(color);
    if (!rgb) {
      return 'rgba(0,0,0,0.42)';
    }
    return `rgba(${Math.max(0, rgb.r - 96)},${Math.max(0, rgb.g - 96)},${Math.max(0, rgb.b - 96)},0.72)`;
  },
  hexToRgb(color) {
    const value = String(color || '').trim();
    const match = value.match(/^#([0-9a-f]{3}|[0-9a-f]{6})$/i);
    if (!match) {
      return null;
    }
    const hex = match[1].length === 3 ? match[1].split('').map(char => char + char).join('') : match[1];
    return {
      r: parseInt(hex.slice(0, 2), 16),
      g: parseInt(hex.slice(2, 4), 16),
      b: parseInt(hex.slice(4, 6), 16)
    };
  },
  printAreaClipPath(bounds, scale) {
    if (!bounds || !bounds.w || !bounds.h) {
      return null;
    }
    return new fabric__WEBPACK_IMPORTED_MODULE_0__.Rect({
      left: (Number(bounds.x) + Number(bounds.w) / 2) * scale,
      top: (Number(bounds.y) + Number(bounds.h) / 2) * scale,
      originX: 'center',
      originY: 'center',
      angle: Number(bounds.rotation) || 0,
      width: Number(bounds.w) * scale,
      height: Number(bounds.h) * scale,
      absolutePositioned: true
    });
  },
  rectClipPath(x, y, w, h, angle = 0) {
    if (!w || !h) {
      return null;
    }
    return new fabric__WEBPACK_IMPORTED_MODULE_0__.Rect({
      left: x + w / 2,
      top: y + h / 2,
      originX: 'center',
      originY: 'center',
      angle,
      width: w,
      height: h,
      absolutePositioned: true
    });
  },
  layerClipPath(x, y, w, h, angle = 0, settings = {}) {
    if (!w || !h) {
      return null;
    }
    const shape = String(settings?.mask_shape || 'circle').toLowerCase();
    const left = x + w / 2;
    const top = y + h / 2;
    if (shape === 'circle') {
      return new fabric__WEBPACK_IMPORTED_MODULE_0__.Circle({
        left,
        top,
        originX: 'center',
        originY: 'center',
        radius: Math.min(w, h) / 2,
        absolutePositioned: true
      });
    }
    return new fabric__WEBPACK_IMPORTED_MODULE_0__.Rect({
      left,
      top,
      originX: 'center',
      originY: 'center',
      angle,
      width: w,
      height: h,
      absolutePositioned: true
    });
  },
  applyContentClip(obj, clipPath) {
    if (clipPath) {
      obj.set({
        clipPath
      });
    }
  },
  centerObjectBounds(obj, targetX, targetY, angle = 0) {
    if (!obj) {
      return;
    }
    obj.setCoords?.();
    const points = typeof obj.getCoords === 'function' ? obj.getCoords() : [];
    if (!points.length) {
      return;
    }
    const rad = (Number(angle) || 0) * Math.PI / 180;
    const cos = Math.cos(rad);
    const sin = Math.sin(rad);
    const local = points.map(point => {
      const dx = point.x - targetX;
      const dy = point.y - targetY;
      return {
        x: targetX + dx * cos + dy * sin,
        y: targetY - dx * sin + dy * cos
      };
    });
    const minX = Math.min(...local.map(point => point.x));
    const maxX = Math.max(...local.map(point => point.x));
    const minY = Math.min(...local.map(point => point.y));
    const maxY = Math.max(...local.map(point => point.y));
    const moveX = targetX - (minX + maxX) / 2;
    const moveY = targetY - (minY + maxY) / 2;
    if (Math.abs(moveX) < 0.01 && Math.abs(moveY) < 0.01) {
      return;
    }
    obj.set({
      left: Number(obj.left || 0) + moveX * cos - moveY * sin,
      top: Number(obj.top || 0) + moveX * sin + moveY * cos
    });
    obj.setCoords?.();
  },
  keepObjectInsidePrintArea(obj, bounds, scale) {
    if (!obj || !bounds || !bounds.w || !bounds.h) {
      return;
    }
    obj.setCoords?.();
    const points = typeof obj.getCoords === 'function' ? obj.getCoords() : [];
    if (!points.length) {
      return;
    }
    const cx = (Number(bounds.x) + Number(bounds.w) / 2) * scale;
    const cy = (Number(bounds.y) + Number(bounds.h) / 2) * scale;
    const halfW = Number(bounds.w) * scale / 2;
    const halfH = Number(bounds.h) * scale / 2;
    const angle = (Number(bounds.rotation) || 0) * Math.PI / 180;
    const cos = Math.cos(angle);
    const sin = Math.sin(angle);
    const local = points.map(point => {
      const dx = point.x - cx;
      const dy = point.y - cy;
      return {
        x: cx + dx * cos + dy * sin,
        y: cy - dx * sin + dy * cos
      };
    });
    const minX = Math.min(...local.map(point => point.x));
    const maxX = Math.max(...local.map(point => point.x));
    const minY = Math.min(...local.map(point => point.y));
    const maxY = Math.max(...local.map(point => point.y));
    const left = cx - halfW;
    const right = cx + halfW;
    const top = cy - halfH;
    const bottom = cy + halfH;
    let moveX = 0;
    let moveY = 0;
    if (minX < left) {
      moveX = left - minX;
    } else if (maxX > right) {
      moveX = right - maxX;
    }
    if (minY < top) {
      moveY = top - minY;
    } else if (maxY > bottom) {
      moveY = bottom - maxY;
    }
    if (!moveX && !moveY) {
      return;
    }
    obj.set({
      left: Number(obj.left || 0) + moveX * cos - moveY * sin,
      top: Number(obj.top || 0) + moveX * sin + moveY * cos
    });
    obj.setCoords?.();
  },
  async recolourSvgClipartUrl(url, color, effect = '') {
    const key = `${url}|${color}|${effect}`;
    if (this.clipartSvgCache[key]) {
      return this.clipartSvgCache[key];
    }
    const request = this.createStateAbortController(10000);
    try {
      const response = await fetch(url, {
        credentials: 'same-origin',
        cache: 'force-cache',
        signal: request.controller.signal
      });
      if (!response.ok) {
        throw new Error(`Could not load clipart SVG (${response.status}).`);
      }
      const raw = await response.text();
      const doc = new window.DOMParser().parseFromString(raw, 'image/svg+xml');
      const svg = doc.documentElement;
      if (!svg || svg.localName.toLowerCase() !== 'svg') {
        throw new Error('Clipart is not an SVG.');
      }
      const paint = effect === 'embroidery' ? 'url(#oc-embroidery-stitch)' : color;
      svg.setAttribute('color', color);
      svg.setAttribute('fill', paint);
      this.forceSvgPreviewColour(svg, paint);
      if (effect === 'embroidery') {
        this.addEmbroiderySvgPattern(svg, color);
      }
      this.ensureSvgIntrinsicSize(svg);
      const output = new window.XMLSerializer().serializeToString(svg);
      this.clipartSvgCache[key] = `data:image/svg+xml;charset=utf-8,${encodeURIComponent(output)}`;
      return this.clipartSvgCache[key];
    } catch (e) {
      console.warn('[OC] SVG clipart recolour failed:', e, 'URL:', url);
      return url;
    } finally {
      request.release();
    }
  },
  async normaliseSvgClipartUrl(url) {
    if (!this.isSvgClipartUrl(url)) {
      return url;
    }
    const key = `${url}|normalise`;
    if (this.clipartSvgCache[key]) {
      return this.clipartSvgCache[key];
    }
    const request = this.createStateAbortController(10000);
    try {
      const response = await fetch(url, {
        credentials: 'same-origin',
        cache: 'force-cache',
        signal: request.controller.signal
      });
      if (!response.ok) {
        throw new Error(`Could not load clipart SVG (${response.status}).`);
      }
      const raw = await response.text();
      const doc = new window.DOMParser().parseFromString(raw, 'image/svg+xml');
      const svg = doc.documentElement;
      if (!svg || svg.localName.toLowerCase() !== 'svg') {
        throw new Error('Clipart is not an SVG.');
      }
      if (!this.ensureSvgIntrinsicSize(svg)) {
        this.clipartSvgCache[key] = url;
        return url;
      }
      const output = new window.XMLSerializer().serializeToString(svg);
      this.clipartSvgCache[key] = `data:image/svg+xml;charset=utf-8,${encodeURIComponent(output)}`;
      return this.clipartSvgCache[key];
    } catch {
      this.clipartSvgCache[key] = url;
      return url;
    } finally {
      request.release();
    }
  },
  isSvgClipartUrl(url) {
    const value = String(url || '').trim();
    return /^data:image\/svg\+xml/i.test(value) || /\.svg(?:[?#]|$)/i.test(value);
  },
  addEmbroiderySvgPattern(svg, color) {
    const rgb = this.hexToRgb(color) || {
      r: 0,
      g: 0,
      b: 0
    };
    const hi = `rgb(${Math.min(255, rgb.r + 92)},${Math.min(255, rgb.g + 92)},${Math.min(255, rgb.b + 92)})`;
    const lo = `rgb(${Math.max(0, rgb.r - 78)},${Math.max(0, rgb.g - 78)},${Math.max(0, rgb.b - 78)})`;
    const ns = 'http://www.w3.org/2000/svg';
    const defs = svg.querySelector('defs') || svg.insertBefore(document.createElementNS(ns, 'defs'), svg.firstChild);
    const pattern = document.createElementNS(ns, 'pattern');
    pattern.setAttribute('id', 'oc-embroidery-stitch');
    pattern.setAttribute('patternUnits', 'userSpaceOnUse');
    pattern.setAttribute('width', '12');
    pattern.setAttribute('height', '12');
    const bg = document.createElementNS(ns, 'rect');
    bg.setAttribute('width', '12');
    bg.setAttribute('height', '12');
    bg.setAttribute('fill', color);
    pattern.appendChild(bg);
    [[lo, '0.34', '-3'], [hi, '0.46', '3'], [lo, '0.2', '9']].forEach(([stroke, opacity, x]) => {
      const line = document.createElementNS(ns, 'line');
      line.setAttribute('x1', x);
      line.setAttribute('y1', '13');
      line.setAttribute('x2', String(Number(x) + 13));
      line.setAttribute('y2', '-1');
      line.setAttribute('stroke', stroke);
      line.setAttribute('stroke-width', '2');
      line.setAttribute('stroke-linecap', 'round');
      line.setAttribute('opacity', opacity);
      pattern.appendChild(line);
    });
    defs.appendChild(pattern);
    return 'url(#oc-embroidery-stitch)';
  },
  forceSvgPreviewColour(element, color) {
    const tagName = element.localName.toLowerCase();
    if (tagName === 'style') {
      element.textContent = this.recolourSvgCss(element.textContent || '', color);
      return;
    }
    if (tagName !== 'svg') {
      this.recolourSvgAttribute(element, 'fill', color);
      this.recolourSvgAttribute(element, 'stroke', color);
      if (element.hasAttribute('style')) {
        element.setAttribute('style', this.recolourSvgStyle(element.getAttribute('style'), color));
      }
      const shapeTags = ['path', 'rect', 'circle', 'ellipse', 'polygon', 'polyline', 'text'];
      if (shapeTags.includes(tagName) && !element.hasAttribute('fill') && !element.hasAttribute('stroke') && !element.hasAttribute('style')) {
        element.setAttribute('fill', color);
      }
    }
    Array.from(element.children).forEach(child => this.forceSvgPreviewColour(child, color));
  },
  ensureSvgIntrinsicSize(svg) {
    const viewBox = this.parseSvgViewBox(svg);
    if (!viewBox) {
      return false;
    }
    let changed = false;
    if (!this.hasUsableSvgLength(svg.getAttribute('width'))) {
      svg.setAttribute('width', String(viewBox.width));
      changed = true;
    }
    if (!this.hasUsableSvgLength(svg.getAttribute('height'))) {
      svg.setAttribute('height', String(viewBox.height));
      changed = true;
    }
    return changed;
  },
  parseSvgViewBox(svg) {
    const raw = String(svg.getAttribute('viewBox') || '').trim();
    const values = raw.split(/[\s,]+/).map(Number).filter(value => Number.isFinite(value));
    if (values.length !== 4 || values[2] <= 0 || values[3] <= 0) {
      return null;
    }
    return {
      x: values[0],
      y: values[1],
      width: values[2],
      height: values[3]
    };
  },
  hasUsableSvgLength(value) {
    const raw = String(value || '').trim();
    if (!raw || raw.endsWith('%')) {
      return false;
    }
    return parseFloat(raw) > 0;
  },
  removeInvisibleSvgShapes(element) {
    Array.from(element.children).forEach(child => {
      this.removeInvisibleSvgShapes(child);
      const tagName = child.localName.toLowerCase();
      const shapeTags = ['path', 'rect', 'circle', 'ellipse', 'polygon', 'polyline', 'line', 'text'];
      if (!shapeTags.includes(tagName)) {
        return;
      }
      const fill = (child.getAttribute('fill') || '').trim().toLowerCase();
      const stroke = (child.getAttribute('stroke') || '').trim().toLowerCase();
      const style = (child.getAttribute('style') || '').replace(/\s+/g, '').toLowerCase();
      const hasVisibleFill = fill && fill !== 'none' || /fill:(?!none(?:;|$))/.test(style);
      const hasVisibleStroke = stroke && stroke !== 'none' || /stroke:(?!none(?:;|$))/.test(style);
      if (!hasVisibleFill && !hasVisibleStroke) {
        child.remove();
      }
    });
  },
  recolourSvgAttribute(element, attribute, color) {
    if (!element.hasAttribute(attribute)) {
      return;
    }
    const value = element.getAttribute(attribute).trim();
    if (value.toLowerCase() === 'none') {
      return;
    }
    element.setAttribute(attribute, color);
  },
  recolourSvgStyle(style, color) {
    return style.replace(/\b(fill|stroke)\s*:\s*([^;]+)/gi, (match, property, value) => {
      const trimmed = String(value || '').trim();
      if (trimmed.toLowerCase() === 'none') {
        return match;
      }
      return `${property}:${color}`;
    });
  },
  recolourSvgCss(css, color) {
    return css.replace(/\b(fill|stroke)\s*:\s*([^;}]+)/gi, (match, property, value) => {
      const trimmed = String(value || '').trim();
      if (trimmed.toLowerCase() === 'none') {
        return match;
      }
      return `${property}:${color}`;
    });
  },
  async renderFabricImg(canvas, url, x, y, w, h, isEngraving = false, crossOrigin = 'anonymous', makeWhiteTransparent = false, angle = 0, engravingPalette = null, clipPath = null, fit = 'contain', tintColor = '', effects = {}, isCurrent = () => true) {
    try {
      const imgLoadOpts = crossOrigin ? {
        crossOrigin
      } : {};
      const img = await this.loadFabricImage(url, imgLoadOpts, 10000);
      if (!isCurrent()) {
        return false;
      }
      if (!img || !img.width) {
        console.warn('[OC] Image failed to load or has zero dimensions:', url);
        return false;
      }
      const palette = engravingPalette || this.engravingPalette();
      let isDitheredEngraving = Boolean(isEngraving && effects.photoEngraving && palette.photoDither);
      if (isDitheredEngraving) {
        const dithered = this.silverPlaquePhotoDither(img.getElement(), w, h);
        if (dithered) {
          img.setElement(dithered);
        } else {
          isDitheredEngraving = false;
        }
      }
      const s = fit === 'cover' ? Math.max(w / img.width, h / img.height) : Math.min(w / img.width, h / img.height);
      img.set({
        left: x + w / 2,
        top: y + h / 2,
        originX: 'center',
        originY: 'center',
        scaleX: s,
        scaleY: s,
        angle,
        selectable: false,
        evented: false,
        imageSmoothing: !isDitheredEngraving
      });
      const filters = [];
      if (makeWhiteTransparent || isEngraving && !effects.preserveRecolouredPixels && !isDitheredEngraving) {
        filters.push(new fabric__WEBPACK_IMPORTED_MODULE_0__.filters.RemoveColor({
          color: '#FFFFFF',
          distance: isEngraving ? 0.18 : 0.1
        }));
      }
      if (tintColor && fabric__WEBPACK_IMPORTED_MODULE_0__.filters.BlendColor) {
        filters.push(new fabric__WEBPACK_IMPORTED_MODULE_0__.filters.BlendColor({
          color: tintColor,
          mode: 'tint',
          alpha: 1
        }));
      }
      if (effects.imageFilter && !isDitheredEngraving) {
        this.addConfiguredImageFilter(filters, effects.imageFilter);
      }
      if (effects.imageColor && fabric__WEBPACK_IMPORTED_MODULE_0__.filters.BlendColor && !isDitheredEngraving) {
        filters.push(new fabric__WEBPACK_IMPORTED_MODULE_0__.filters.BlendColor({
          color: effects.imageColor,
          mode: 'tint',
          alpha: 1
        }));
      }
      if (isEngraving && !effects.preserveRecolouredPixels && !isDitheredEngraving) {
        filters.push(new fabric__WEBPACK_IMPORTED_MODULE_0__.filters.Grayscale(), new fabric__WEBPACK_IMPORTED_MODULE_0__.filters.Brightness({
          brightness: palette.brightness
        }), new fabric__WEBPACK_IMPORTED_MODULE_0__.filters.Contrast({
          contrast: palette.contrast
        }));
        if (palette.imageTint && fabric__WEBPACK_IMPORTED_MODULE_0__.filters.BlendColor) {
          filters.push(new fabric__WEBPACK_IMPORTED_MODULE_0__.filters.BlendColor({
            color: palette.imageTint,
            mode: 'tint',
            alpha: palette.tintAlpha ?? 1
          }));
        }
        if (palette.noise && fabric__WEBPACK_IMPORTED_MODULE_0__.filters.Noise) {
          filters.push(new fabric__WEBPACK_IMPORTED_MODULE_0__.filters.Noise({
            noise: palette.noise
          }));
        }
      }
      if ((effects.embroidery || effects.embroideryColor) && fabric__WEBPACK_IMPORTED_MODULE_0__.filters.Noise) {
        filters.push(new fabric__WEBPACK_IMPORTED_MODULE_0__.filters.Contrast({
          contrast: 0.08
        }), new fabric__WEBPACK_IMPORTED_MODULE_0__.filters.Noise({
          noise: 22
        }));
      }
      if (filters.length) {
        img.filters = filters;
        img.applyFilters();
      }
      if (isEngraving && effects.preserveRecolouredPixels) {
        img.set({
          opacity: palette.opacity,
          globalCompositeOperation: palette.composite || 'source-over'
        });
      } else if (isEngraving) {
        img.set({
          opacity: palette.opacity,
          globalCompositeOperation: palette.composite || 'source-over',
          shadow: new fabric__WEBPACK_IMPORTED_MODULE_0__.Shadow({
            color: palette.highlight,
            offsetX: 0,
            offsetY: 1,
            blur: 1
          })
        });
      } else if (effects.embroidery || effects.embroideryColor) {
        img.set({
          shadow: new fabric__WEBPACK_IMPORTED_MODULE_0__.Shadow({
            color: 'rgba(0,0,0,0.24)',
            offsetX: 0.7,
            offsetY: 0.95,
            blur: 1.1
          })
        });
      }
      img._ocContent = true;
      img._ocSourceUrl = url;
      img._ocSnapshotColor = effects.imageColor || effects.embroideryColor || tintColor || '';
      img._ocSnapshotInlineSvg = filters.length === 0;
      this.applyContentClip(img, clipPath);
      if (!isCurrent()) {
        return false;
      }
      canvas.add(img);
      return true;
    } catch (e) {
      console.warn('[OC] renderFabricImg error:', e, 'URL:', url);
      return false;
    }
  },
  imageFilterForLayer(layer, filterId) {
    filterId = parseInt(filterId, 10) || 0;
    if (!filterId) {
      return null;
    }
    const allowedIds = Array.isArray(layer?.settings?.image_filter_ids) ? layer.settings.image_filter_ids.map(Number) : [];
    if (!allowedIds.includes(filterId)) {
      return null;
    }
    return (this.data?.imageFilters || []).find(filter => Number(filter.id) === filterId);
  },
  addConfiguredImageFilter(filters, config) {
    const key = String(config?.key || '');
    const value = Number(config?.value);
    const amount = Number.isFinite(value) ? value : 1;
    switch (key) {
      case 'grayscale':
        if (fabric__WEBPACK_IMPORTED_MODULE_0__.filters.Grayscale) {
          filters.push(new fabric__WEBPACK_IMPORTED_MODULE_0__.filters.Grayscale());
        }
        break;
      case 'sepia':
        if (fabric__WEBPACK_IMPORTED_MODULE_0__.filters.Sepia) {
          filters.push(new fabric__WEBPACK_IMPORTED_MODULE_0__.filters.Sepia());
        }
        break;
      case 'brightness':
        if (fabric__WEBPACK_IMPORTED_MODULE_0__.filters.Brightness) {
          filters.push(new fabric__WEBPACK_IMPORTED_MODULE_0__.filters.Brightness({
            brightness: amount
          }));
        }
        break;
      case 'contrast':
        if (fabric__WEBPACK_IMPORTED_MODULE_0__.filters.Contrast) {
          filters.push(new fabric__WEBPACK_IMPORTED_MODULE_0__.filters.Contrast({
            contrast: amount
          }));
        }
        break;
      case 'saturation':
        if (fabric__WEBPACK_IMPORTED_MODULE_0__.filters.Saturation) {
          filters.push(new fabric__WEBPACK_IMPORTED_MODULE_0__.filters.Saturation({
            saturation: amount
          }));
        }
        break;
      case 'hue':
        if (fabric__WEBPACK_IMPORTED_MODULE_0__.filters.HueRotation) {
          filters.push(new fabric__WEBPACK_IMPORTED_MODULE_0__.filters.HueRotation({
            rotation: amount
          }));
        }
        break;
    }
  },
  fontCacheKey(font) {
    return [font?.name || '', font?.weight || 'normal', font?.style || 'normal', font?.url || ''].join('|');
  },
  async loadFont(font) {
    if (!font?.name || !font?.url) {
      return;
    }
    const key = this.fontCacheKey(font);
    if (this.fontCache[key]) {
      return this.fontCache[key];
    }
    const ff = new FontFace(font.name, `url('${font.url}')`, {
      weight: font.weight || 'normal',
      style: font.style || 'normal'
    });
    this.fontCache[key] = ff.load().then(f => document.fonts.add(f)).catch(err => {
      delete this.fontCache[key];
      console.warn('[OC] Font load failed:', err);
      throw err;
    });
    return this.fontCache[key];
  }
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (canvasRendererMethods);

/***/ },

/***/ "./src/frontend/customiser/cart-serialization.js"
/*!*******************************************************!*\
  !*** ./src/frontend/customiser/cart-serialization.js ***!
  \*******************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
const cartSerializationMethods = {
  cloneSubmissionInputs() {
    return JSON.parse(JSON.stringify(this.inputs || {}));
  },
  freezeSubmissionValue(value) {
    if (!value || typeof value !== 'object' || Object.isFrozen(value)) {
      return value;
    }
    Object.values(value).forEach(child => this.freezeSubmissionValue(child));
    return Object.freeze(value);
  },
  serialiseLayers(inputs) {
    const layers = {};
    this.areas.forEach(area => {
      (area.layers || []).forEach(layer => {
        const input = {
          ...(inputs[layer.id] || {})
        };
        if (['text', 'textarea'].includes(layer.type)) {
          if (layer.locked) {
            input.value = layer.settings?.default_text || '';
          } else if (input.value !== undefined) {
            input.value = this.normaliseLayerTextValue(layer.id, input.value);
          }
        }
        if (['image', 'clipmask'].includes(layer.type)) {
          const canonicalId = this.canonicalLinkedLayerId(layer.id);
          const linkedMembers = this.linkedLayerMembers(layer.id);
          if (linkedMembers.length > 1) {
            input.linkedSourceLayerId = canonicalId;
          }
          if (canonicalId === layer.id) {
            const linkedIds = linkedMembers.filter(layerId => Number(layerId) !== Number(canonicalId));
            if (linkedIds.length) {
              input.linkedLayerIds = linkedIds;
            }
          }
        }
        if (layer.type === 'spotify') {
          const spotifyUri = this.extractSpotifyUri(input.spotifyUri || input.value);
          if (spotifyUri) {
            input.value = spotifyUri;
            input.spotifyUri = spotifyUri;
          }
        }
        layers[layer.id] = {
          type: layer.type,
          ...input
        };
      });
    });
    return layers;
  },
  createSubmissionGeneration() {
    const inputs = this.cloneSubmissionInputs();
    this.areas.forEach(area => {
      (area.layers || []).forEach(layer => {
        if (!inputs[layer.id]) {
          inputs[layer.id] = {};
        }
        if (layer.locked && ['text', 'textarea'].includes(layer.type)) {
          inputs[layer.id].value = layer.settings?.default_text || '';
        }
      });
    });
    const generation = {
      designGeneration: this._designGeneration,
      designId: this.data.designId,
      designVariant: this.selectedDesignVariant || '',
      designVariantLabel: this.designVariants.find(item => item.id === this.selectedDesignVariant)?.label || '',
      layers: this.serialiseLayers(inputs)
    };
    return this.freezeSubmissionValue(generation);
  },
  buildCustomisationPayload(generation, {
    previewUrl = '',
    previewImage = ''
  } = {}) {
    const payload = {
      v: 2,
      designId: generation.designId,
      layers: generation.layers,
      uploadToken: this.data.requestToken || ''
    };
    if (generation.designVariant) {
      payload.designVariant = generation.designVariant;
      if (generation.designVariantLabel) {
        payload.designVariantLabel = generation.designVariantLabel;
      }
    }
    if (previewImage) {
      payload.previewImage = previewImage;
    } else if (previewUrl) {
      payload.previewUrl = previewUrl;
    }
    return payload;
  },
  updateHiddenField(options = {}) {
    const el = document.getElementById('oc-customisation-data');
    if (!el) {
      return;
    }
    if (!this._customisationActive) {
      el.value = '';
      el.disabled = true;
      return;
    }
    el.disabled = false;
    const generation = options.generation || this.createSubmissionGeneration();
    const payload = this.buildCustomisationPayload(generation, {
      previewUrl: options.previewUrl || '',
      previewImage: options.previewImage || ''
    });
    el.value = JSON.stringify(payload);
    return payload;
  }
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (cartSerializationMethods);

/***/ },

/***/ "./src/frontend/customiser/checkout.js"
/*!*********************************************!*\
  !*** ./src/frontend/customiser/checkout.js ***!
  \*********************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_0__);
/**
 * Cart submission, mobile preview confirmation, and preview capture helpers.
 */

/* eslint-disable no-console, no-alert, import/no-unresolved */


const CART_STORE_KEY = 'wc/store/cart';
const QUALITY_WARNING_MESSAGE = 'We found quality warnings that may affect print output. Press OK to continue, or Cancel to review.';
const MAX_CUSTOMISATION_BYTES = 1024 * 1024;
const CART_PREVIEW_MAX_DIMENSION = 640;
const CART_PREVIEW_QUALITY = 0.82;
const checkoutMethods = {
  handleVariationSubmitBlock() {
    if (this._variationSwitchPending) {
      window.alert('Please wait while the personalisation options finish loading.');
      return true;
    }
    if (!this._variationSwitchFailed) {
      const variationId = this.currentVariationId();
      if (!variationId || String(variationId) === this._activeVariationKey) {
        return false;
      }
      window.alert('Please wait while the personalisation options load for this variation.');
      this.switchProductVariation(variationId);
      return true;
    }
    const variationId = this.currentVariationId();
    window.alert('Retrying the personalisation options for this variation.');
    this.switchProductVariation(variationId);
    return true;
  },
  acquireCartSubmitGuard(form) {
    if (this.handleVariationSubmitBlock()) {
      return false;
    }
    if (this._submitInProgress || !this._customisationActive || this.artworkPendingCount > 0 || Object.keys(this.aiFilterErrors || {}).length > 0) {
      if (this.artworkPendingCount > 0) {
        window.alert('Please wait for artwork uploads and image processing to finish.');
      } else if (Object.keys(this.aiFilterErrors || {}).length > 0) {
        window.alert('An image effect could not be applied. Retry it before adding this product to your cart.');
      }
      return false;
    }
    this._submitInProgress = true;
    form.classList.add('processing');
    this.setControlLock('submission', true);
    return true;
  },
  setupFormSubmit() {
    if (this.formSubmitBound) {
      return;
    }
    const form = document.querySelector('form.cart');
    if (!form) {
      return;
    }
    this.formSubmitBound = true;
    form.querySelectorAll('[type="submit"], .single_add_to_cart_button').forEach(button => {
      button.addEventListener('click', e => {
        this.closeFontComboboxes(true);
        if (form._ocSubmitReady) {
          return;
        }
        if (this.handleVariationSubmitBlock()) {
          e.preventDefault();
          e.stopImmediatePropagation();
          return;
        }
        if (!this._customisationActive) {
          this.updateHiddenField();
          return;
        }
        this.clearCustomValidity();
        if (!form.checkValidity()) {
          return;
        }
        e.preventDefault();
        e.stopImmediatePropagation();
        this.syncInputsFromDOM();
        const preflight = this.runImmediateBlockingPreflight();
        if (preflight.ok) {
          if (form.requestSubmit) {
            form.requestSubmit(button);
          } else {
            form.dispatchEvent(new Event('submit', {
              bubbles: true,
              cancelable: true
            }));
          }
          return;
        }
        this.resetCartSubmitState(form);
        this.renderPreflightMessages(preflight.errors, preflight.warnings);
      }, true);
    });
    form.addEventListener('submit', async e => {
      this.closeFontComboboxes(true);
      if (this.handleVariationSubmitBlock()) {
        e.preventDefault();
        e.stopImmediatePropagation();
        this.resetCartSubmitState(form);
        return;
      }
      if (!this._customisationActive) {
        this.updateHiddenField();
        return;
      }
      if (this.mobileCartPreviewDismissedAt && Date.now() - this.mobileCartPreviewDismissedAt < 750) {
        e.preventDefault();
        e.stopImmediatePropagation();
        this.resetCartSubmitState(form);
        return;
      }
      if (form._ocSubmitReady) {
        form._ocSubmitReady = false;
        this.resetCartSubmitState(form);
        return; // preview already captured, let submit through
      }
      e.preventDefault();
      e.stopImmediatePropagation();
      if (!this.acquireCartSubmitGuard(form)) {
        return;
      }
      try {
        const prepared = await this.prepareCartCustomisation();
        if (!prepared) {
          this.resetCartSubmitState(form);
          return;
        }
        form._ocSubmitReady = true;
        this.resetCartSubmitState(form);
        // requestSubmit() re-triggers HTML5 validation before submitting.
        if (form.requestSubmit) {
          const submitter = form.querySelector('[type="submit"]') || undefined;
          form.requestSubmit(submitter);
        } else {
          form.submit();
        }
      } catch (error) {
        console.error('[OC] Cart submission failed:', error);
        this.restoreGalleryPreview();
        this.renderPreflightMessages(['The customisation preview could not be prepared. Please wait for all artwork to load and try again.'], []);
        this.resetCartSubmitState(form);
      }
    }, true);
  },
  async prepareCartCustomisation() {
    this.syncInputsFromDOM();
    const preflight = await this.runPreflight();
    this.renderPreflightMessages(preflight.errors, preflight.warnings);
    if (!preflight.ok) {
      return null;
    }
    if (preflight.warnings.length && !window.confirm(QUALITY_WARNING_MESSAGE)) {
      return null;
    }
    await this.flushRedraw(this.inputs, {
      pushGallery: false
    });
    const generation = this.createSubmissionGeneration();
    const previews = this.getSubmissionPreviewAreas(generation);
    const acceptedPreview = await this.confirmMobileCartPreview(generation, previews);
    if (!acceptedPreview) {
      this.restoreGalleryPreview();
      return null;
    }
    const previewImage = this.getSubmissionPreviewImage(generation, previews);
    const previewUrl = await this.uploadCartPreview(previewImage, generation);
    const payload = this.buildCustomisationPayload(generation, {
      previewUrl
    });
    const serialisedPayload = JSON.stringify(payload);
    if (new Blob([serialisedPayload]).size > MAX_CUSTOMISATION_BYTES) {
      this.renderPreflightMessages(['This personalisation is too large to add safely. Please simplify the design or contact us for help.'], []);
      return null;
    }
    const hiddenField = document.getElementById('oc-customisation-data');
    if (hiddenField) {
      hiddenField.disabled = false;
      hiddenField.value = serialisedPayload;
    }
    return {
      generation,
      payload,
      serialisedPayload
    };
  },
  resetCartSubmitState(form) {
    this._submitInProgress = false;
    this.setControlLock('submission', false);
    form.classList.remove('loading', 'processing');
    form.querySelectorAll('[type="submit"], .single_add_to_cart_button').forEach(button => {
      button.classList.remove('loading', 'processing');
    });
  },
  setupStoreApiIntegration() {
    if (this._storeApiSubmitBound) {
      return;
    }
    const form = document.querySelector('form[data-wp-on--submit*="addToCart"]:not(.cart)');
    if (!form) {
      return;
    }
    this._storeApiSubmitBound = true;
    form.addEventListener('submit', event => {
      if (!this._customisationActive) {
        return;
      }
      event.preventDefault();
      event.stopImmediatePropagation();
      this.closeFontComboboxes(true);
      if (!form.checkValidity()) {
        form.reportValidity?.();
        return;
      }
      if (this._storeApiPreparationPromise) {
        return;
      }
      const task = this.submitStoreApiCart(form);
      this._storeApiPreparationPromise = task;
      task.finally(() => {
        if (this._storeApiPreparationPromise === task) {
          this._storeApiPreparationPromise = null;
        }
      });
    }, true);
  },
  getStoreApiCartRequest(form) {
    if (form.querySelector('[name^="quantity["]')) {
      throw new Error('Personalised grouped products require the standard add-to-cart form.');
    }
    const variationField = form.querySelector('[name="variation_id"]');
    const variationId = parseInt(variationField?.value || '0', 10) || 0;
    const parentId = parseInt(form.querySelector('[name="product_id"]')?.value || form.querySelector('[name="add-to-cart"]')?.value || this.data.productId || '0', 10) || 0;
    if (variationField && !variationId) {
      throw new Error('Please select all product options before adding this item to your cart.');
    }
    const productId = variationId || parentId;
    if (!productId) {
      throw new Error('The selected product could not be identified.');
    }
    const variation = [];
    const attributes = new Map();
    new FormData(form).forEach((value, name) => {
      if (name.startsWith('attribute_')) {
        attributes.set(name, String(value));
      }
    });
    attributes.forEach((value, attribute) => {
      if (!value) {
        throw new Error('Please select all product options before adding this item to your cart.');
      }
      variation.push({
        attribute,
        value
      });
    });
    const quantity = Number(form.querySelector('[name="quantity"]')?.value || 1);
    if (!Number.isFinite(quantity) || quantity <= 0) {
      throw new Error('Please enter a valid product quantity.');
    }
    return {
      productId,
      quantity,
      variation
    };
  },
  async submitStoreApiCart(form) {
    let request;
    try {
      request = this.getStoreApiCartRequest(form);
    } catch (error) {
      this.renderPreflightMessages([error.message], []);
      return;
    }
    if (!this.acquireCartSubmitGuard(form)) {
      return;
    }
    try {
      const prepared = await this.prepareCartCustomisation();
      if (!prepared) {
        return;
      }
      await (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_0__.dispatch)(CART_STORE_KEY).addItemToCart(request.productId, request.quantity, request.variation, {
        extensions: {
          overcustomise: {
            customisation: prepared.payload
          }
        }
      });
      this.restoreProductGallery();
    } catch (error) {
      console.error('[OC] Store API cart submission failed:', error);
      this.restoreGalleryPreview();
      this.renderPreflightMessages([error?.message || 'Could not add this personalisation to the cart. Please try again.'], []);
    } finally {
      this.resetCartSubmitState(form);
    }
  },
  isMobileCartPreviewRequired() {
    return window.matchMedia?.('(max-width: 639px)')?.matches || window.innerWidth < 640;
  },
  getCanvasPreviewDataUrl(areaIndex) {
    const canvas = this.canvases[areaIndex];
    if (!canvas || canvas._ocMissingMockup) {
      throw new Error('The customisation preview is unavailable.');
    }
    if (canvas._ocRenderErrors?.length) {
      throw new Error('Some artwork could not be rendered.');
    }
    if (this._redrawPromises[areaIndex]) {
      throw new Error('The customisation preview is still rendering.');
    }
    const revision = `${this._designGeneration}:${this._redrawGenerations[areaIndex] || 0}`;
    if (canvas._ocCartPreviewRevision === revision && canvas._ocCartPreviewDataUrl) {
      return canvas._ocCartPreviewDataUrl;
    }
    const width = Math.max(1, Number(canvas.getWidth?.() || canvas.width || 1));
    const height = Math.max(1, Number(canvas.getHeight?.() || canvas.height || 1));
    const multiplier = Math.min(1, CART_PREVIEW_MAX_DIMENSION / Math.max(width, height));
    const dataUrl = canvas.toDataURL({
      format: 'jpeg',
      quality: CART_PREVIEW_QUALITY,
      multiplier
    });
    if (!/^data:image\/(?:jpeg|png);base64,/i.test(dataUrl)) {
      throw new Error('The customisation preview could not be captured.');
    }
    canvas._ocCartPreviewRevision = revision;
    canvas._ocCartPreviewDataUrl = dataUrl;
    return dataUrl;
  },
  getCurrentPreviewDataUrl() {
    return this.getCanvasPreviewDataUrl(this.activeArea);
  },
  customisationPayloadBytes(payload) {
    return new Blob([JSON.stringify(payload)]).size;
  },
  getSubmissionPreviewAreas(generation) {
    if (generation.designGeneration !== this._designGeneration) {
      throw new Error('The selected design changed while rendering.');
    }
    if (!this.areas.length) {
      throw new Error('The customisation has no preview areas.');
    }
    return this.areas.map((area, index) => ({
      index,
      label: area?.label || `Area ${index + 1}`,
      url: this.getCanvasPreviewDataUrl(index)
    }));
  },
  getSubmissionPreviewImage(generation, previews = null) {
    const availablePreviews = previews || this.getSubmissionPreviewAreas(generation);
    const previewImage = availablePreviews.find(preview => preview.index === this.activeArea)?.url;
    if (!previewImage) {
      throw new Error('The active customisation preview is unavailable.');
    }
    return previewImage;
  },
  async uploadCartPreview(previewImage, generation) {
    if (!this.data.savePreviewUrl) {
      throw new Error('The preview upload service is unavailable.');
    }
    const response = await fetch(this.data.savePreviewUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: this.restHeaders({
        'Content-Type': 'application/json',
        Accept: 'application/json'
      }),
      body: JSON.stringify({
        image: previewImage
      })
    });
    const body = await response.json().catch(() => null);
    if (generation.designGeneration !== this._designGeneration) {
      throw new Error('The selected design changed while saving.');
    }
    const previewUrl = typeof body?.url === 'string' ? body.url.trim() : '';
    if (!response.ok || !previewUrl) {
      throw new Error(body?.message || 'The customisation preview could not be saved.');
    }
    return previewUrl;
  },
  restoreGalleryPreview() {
    this.redraw(this.activeArea).catch(error => {
      console.warn('[OC] Could not restore gallery preview:', error.message);
    });
  },
  getMobileCartPreviewAreas(generation) {
    return this.getSubmissionPreviewAreas(generation);
  },
  getMobileCartPreviewDialog() {
    if (this.mobileCartPreviewDialog?.isConnected) {
      return this.mobileCartPreviewDialog;
    }
    let dialogRoot = document.getElementById('oc-cart-preview-root');
    if (!dialogRoot) {
      dialogRoot = document.createElement('div');
      dialogRoot.id = 'oc-cart-preview-root';
      dialogRoot.className = 'oc-customiser-panel oc-cart-preview-root';
      document.body.appendChild(dialogRoot);
    }
    let dialog = document.getElementById('oc-cart-preview-dialog');
    if (!dialog) {
      const nativeDialog = document.createElement('dialog');
      dialog = nativeDialog.showModal ? nativeDialog : document.createElement('div');
      dialog.id = 'oc-cart-preview-dialog';
      dialog.className = 'oc-cart-preview-dialog';
      dialog.setAttribute('aria-labelledby', 'oc-cart-preview-title');
      dialog.setAttribute('aria-describedby', 'oc-cart-preview-desc');
      dialog.innerHTML = '<div class="oc-cart-preview-card">' + '<div class="oc-cart-preview-copy">' + '<h2 id="oc-cart-preview-title">Check your preview</h2>' + '<p id="oc-cart-preview-desc">Please confirm your customisation looks correct before adding this product to your cart.</p>' + '</div>' + '<div class="oc-cart-preview-tabs" role="tablist" aria-label="Preview areas"></div>' + '<div class="oc-cart-preview-panels"></div>' + '<div class="oc-cart-preview-actions">' + '<button type="button" class="oc-cart-preview-change" data-oc-cart-preview-change>Change</button>' + '<button type="button" class="oc-cart-preview-accept" data-oc-cart-preview-accept>Accept</button>' + '</div>' + '</div>';
      dialogRoot.appendChild(dialog);
    }
    this.mobileCartPreviewDialog = dialog;
    return dialog;
  },
  dismissMobileCartPreview() {
    if (this._mobileCartPreviewResolve) {
      this._mobileCartPreviewResolve(false);
      return;
    }
    const dialog = this.mobileCartPreviewDialog;
    if (!dialog) {
      return;
    }
    dialog.classList.remove('is-visible');
    if (typeof dialog.showModal === 'function' && dialog.open) {
      dialog.close?.();
    } else {
      dialog.removeAttribute('open');
    }
    if (!dialog.showModal) {
      dialog.hidden = true;
    }
    document.documentElement.classList.remove('oc-cart-preview-open');
  },
  confirmMobileCartPreview(generation, suppliedPreviews = null) {
    if (!this.isMobileCartPreviewRequired()) {
      return Promise.resolve(true);
    }
    if (this._mobileCartPreviewPromise) {
      return this._mobileCartPreviewPromise;
    }
    const ownerDocument = document.getElementById('oc-customiser-panel')?.ownerDocument || window.document;
    const previousFocus = ownerDocument.activeElement;
    this.closeFontComboboxes(true);
    const previews = suppliedPreviews || this.getMobileCartPreviewAreas(generation);
    if (!previews.length) {
      this.renderPreflightMessages(['The customisation preview is unavailable. Please wait for it to finish loading and try again.'], []);
      return Promise.resolve(false);
    }
    const dialog = this.getMobileCartPreviewDialog();
    const supportsModal = typeof dialog.showModal === 'function';
    dialog.classList.toggle('oc-cart-preview-dialog--fallback', !supportsModal);
    dialog.setAttribute('role', 'dialog');
    dialog.setAttribute('aria-modal', 'true');
    dialog.ownerDocument.activeElement?.blur?.();
    const tabs = dialog.querySelector('.oc-cart-preview-tabs');
    const panels = dialog.querySelector('.oc-cart-preview-panels');
    tabs.replaceChildren();
    panels.replaceChildren();
    tabs.hidden = previews.length === 1;
    previews.forEach((preview, position) => {
      const tab = document.createElement('button');
      tab.type = 'button';
      tab.id = `oc-cart-preview-tab-${preview.index}`;
      tab.className = 'oc-cart-preview-tab';
      tab.role = 'tab';
      tab.textContent = preview.label;
      tab.dataset.ocPreviewPosition = String(position);
      tab.setAttribute('aria-selected', position === 0 ? 'true' : 'false');
      tab.setAttribute('aria-controls', `oc-cart-preview-panel-${preview.index}`);
      tab.tabIndex = position === 0 ? 0 : -1;
      const panel = document.createElement('div');
      panel.id = `oc-cart-preview-panel-${preview.index}`;
      panel.className = 'oc-cart-preview-image-wrap';
      panel.role = 'tabpanel';
      panel.setAttribute('aria-labelledby', tab.id);
      panel.hidden = position !== 0;
      const img = document.createElement('img');
      img.className = 'oc-cart-preview-image';
      img.alt = `${preview.label} customisation preview`;
      img.src = preview.url;
      panel.appendChild(img);
      tabs.appendChild(tab);
      panels.appendChild(panel);
    });
    const previewTabs = Array.from(tabs.querySelectorAll('[role="tab"]'));
    const selectPreview = (position, focus = false) => {
      previewTabs.forEach((tab, index) => {
        const selected = index === position;
        tab.setAttribute('aria-selected', selected ? 'true' : 'false');
        tab.tabIndex = selected ? 0 : -1;
        panels.children[index].hidden = !selected;
      });
      if (focus) {
        previewTabs[position]?.focus();
      }
    };
    previewTabs.forEach((tab, position) => {
      tab.addEventListener('click', () => selectPreview(position));
      tab.addEventListener('keydown', event => {
        if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) {
          return;
        }
        event.preventDefault();
        let next = event.key === 'Home' ? 0 : previewTabs.length - 1;
        if (event.key === 'ArrowLeft') {
          next = (position - 1 + previewTabs.length) % previewTabs.length;
        }
        if (event.key === 'ArrowRight') {
          next = (position + 1) % previewTabs.length;
        }
        selectPreview(next, true);
      });
    });
    const promise = new Promise(resolve => {
      const acceptBtn = dialog.querySelector('[data-oc-cart-preview-accept]');
      const changeBtn = dialog.querySelector('[data-oc-cart-preview-change]');
      let settled = false;
      const finish = accepted => {
        if (settled) {
          return;
        }
        settled = true;
        this._mobileCartPreviewResolve = null;
        if (!accepted) {
          this.mobileCartPreviewDismissedAt = Date.now();
        }
        dialog.classList.remove('is-visible');
        dialog.removeEventListener('click', onBackdropClick);
        dialog.removeEventListener('cancel', onCancel);
        dialog.removeEventListener('keydown', onDialogKeydown);
        acceptBtn?.removeEventListener('click', onAccept);
        changeBtn?.removeEventListener('click', onChange);
        if (supportsModal && dialog.open) {
          dialog.close?.();
        } else {
          dialog.removeAttribute('open');
          dialog.hidden = true;
        }
        document.documentElement.classList.remove('oc-cart-preview-open');
        this.setStateTimeout(() => previousFocus?.focus?.(), 0);
        resolve(accepted);
      };
      this._mobileCartPreviewResolve = finish;
      const stopModalAction = event => {
        event?.preventDefault?.();
        event?.stopPropagation?.();
        event?.stopImmediatePropagation?.();
      };
      const onAccept = event => {
        stopModalAction(event);
        finish(true);
      };
      const onChange = event => {
        stopModalAction(event);
        finish(false);
      };
      const onBackdropClick = event => {
        if (event.target === dialog) {
          finish(false);
        }
      };
      const onCancel = event => {
        event.preventDefault();
        finish(false);
      };
      const onDialogKeydown = event => {
        if (event.key === 'Escape' && !supportsModal) {
          event.preventDefault();
          finish(false);
          return;
        }
        if (event.key !== 'Tab' || supportsModal) {
          return;
        }
        const focusable = Array.from(dialog.querySelectorAll('button:not(:disabled), [href], input:not(:disabled), select:not(:disabled), textarea:not(:disabled), [tabindex]:not([tabindex="-1"])')).filter(control => !control.closest('[hidden]'));
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (!first || !last) {
          event.preventDefault();
          return;
        }
        if (event.shiftKey && dialog.ownerDocument.activeElement === first) {
          event.preventDefault();
          last.focus();
        } else if (!event.shiftKey && dialog.ownerDocument.activeElement === last) {
          event.preventDefault();
          first.focus();
        }
      };
      acceptBtn?.addEventListener('click', onAccept);
      changeBtn?.addEventListener('click', onChange);
      dialog.addEventListener('click', onBackdropClick);
      dialog.addEventListener('cancel', onCancel);
      dialog.addEventListener('keydown', onDialogKeydown);
      if (supportsModal) {
        dialog.hidden = false;
        dialog.showModal();
      } else {
        dialog.hidden = false;
        dialog.setAttribute('open', '');
        document.documentElement.classList.add('oc-cart-preview-open');
      }
      this.requestStateAnimationFrame(() => dialog.classList.add('is-visible'));
      acceptBtn?.focus?.();
    });
    this._mobileCartPreviewPromise = promise.finally(() => {
      this._mobileCartPreviewPromise = null;
    });
    return this._mobileCartPreviewPromise;
  }
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (checkoutMethods);

/***/ },

/***/ "./src/frontend/customiser/clipart.js"
/*!********************************************!*\
  !*** ./src/frontend/customiser/clipart.js ***!
  \********************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/**
 * Clipart search and carousel helpers.
 */

const clipartMethods = {
  filterClipart(layerId) {
    const grid = document.querySelector(`.oc-clipart-grid[data-oc-clipart-grid="${layerId}"]`) || document.querySelector(`[data-oc-clipart-search="${layerId}"]`)?.closest('.oc-layer-body')?.querySelector('.oc-clipart-grid');
    if (!grid) {
      return;
    }
    const items = grid.querySelectorAll('.oc-clipart-item');
    const term = (this.clipartSearchTerms[layerId] || '').toLowerCase().trim();
    const category = this.clipartCategoryFilters[layerId] || '';
    let visibleCount = 0;
    items.forEach(btn => {
      const name = (btn.title || '').toLowerCase();
      const groups = btn.dataset.ocClipartGroups ? btn.dataset.ocClipartGroups.split('||').filter(Boolean) : [];
      const matchesSearch = !term || name.includes(term);
      const matchesCategory = !category || groups.includes(category);
      const visible = matchesSearch && matchesCategory;
      btn.style.display = visible ? '' : 'none';
      if (visible) {
        visibleCount++;
      }
    });
    let noResults = grid.querySelector('.oc-clipart-no-results');
    if (visibleCount === 0) {
      if (!noResults) {
        noResults = document.createElement('p');
        noResults.className = 'oc-clipart-no-results';
        noResults.textContent = 'No clipart matches your search.';
        grid.appendChild(noResults);
      }
      noResults.style.display = '';
    } else if (noResults) {
      noResults.style.display = 'none';
    }
    this.refreshClipartCarousel(layerId);
  },
  setupClipartCarousels() {
    document.querySelectorAll('[data-oc-clipart-carousel]').forEach(carousel => {
      const layerId = parseInt(carousel.dataset.ocClipartCarousel, 10);
      const grid = carousel.querySelector('.oc-clipart-grid--carousel');
      if (!layerId || !grid) {
        return;
      }
      if (carousel.dataset.ocCarouselReady === '1') {
        this.refreshClipartCarousel(layerId);
        return;
      }
      carousel.dataset.ocCarouselReady = '1';
      carousel.querySelector('[data-oc-clipart-prev]')?.addEventListener('click', () => this.scrollClipartCarousel(layerId, -1));
      carousel.querySelector('[data-oc-clipart-next]')?.addEventListener('click', () => this.scrollClipartCarousel(layerId, 1));
      grid.addEventListener('scroll', () => this.updateClipartCarouselDots(layerId), {
        passive: true
      });
      this.refreshClipartCarousel(layerId);
    });
  },
  visibleClipartItems(grid) {
    return Array.from(grid.querySelectorAll('.oc-clipart-item')).filter(item => item.style.display !== 'none');
  },
  clipartCarouselPageCount(grid) {
    const visibleItems = this.visibleClipartItems(grid);
    if (!visibleItems.length || !grid.clientWidth) {
      return 1;
    }
    return Math.max(1, Math.ceil(grid.scrollWidth / grid.clientWidth));
  },
  scrollClipartCarousel(layerId, direction) {
    const grid = document.querySelector(`.oc-clipart-grid--carousel[data-oc-clipart-grid="${layerId}"]`);
    if (!grid) {
      return;
    }
    const page = Math.round(grid.scrollLeft / Math.max(1, grid.clientWidth)) + direction;
    const maxPage = this.clipartCarouselPageCount(grid) - 1;
    grid.scrollTo({
      left: Math.max(0, Math.min(maxPage, page)) * grid.clientWidth,
      behavior: 'smooth'
    });
  },
  refreshClipartCarousel(layerId) {
    const carousel = document.querySelector(`[data-oc-clipart-carousel="${layerId}"]`);
    const grid = carousel?.querySelector('.oc-clipart-grid--carousel');
    const dots = carousel?.querySelector('[data-oc-clipart-dots]');
    if (!carousel || !grid || !dots) {
      return;
    }
    const pageCount = this.clipartCarouselPageCount(grid);
    const maxLeft = Math.max(0, (pageCount - 1) * grid.clientWidth);
    if (grid.scrollLeft > maxLeft) {
      grid.scrollLeft = maxLeft;
    }
    dots.innerHTML = '';
    for (let i = 0; i < pageCount; i++) {
      const dot = document.createElement('button');
      dot.type = 'button';
      dot.className = 'oc-clipart-carousel-dot';
      dot.setAttribute('aria-label', `Go to clipart page ${i + 1}`);
      dot.addEventListener('click', () => grid.scrollTo({
        left: i * grid.clientWidth,
        behavior: 'smooth'
      }));
      dots.appendChild(dot);
    }
    carousel.classList.toggle('oc-clipart-carousel--single-page', pageCount <= 1);
    this.updateClipartCarouselDots(layerId);
  },
  updateClipartCarouselDots(layerId) {
    const carousel = document.querySelector(`[data-oc-clipart-carousel="${layerId}"]`);
    const grid = carousel?.querySelector('.oc-clipart-grid--carousel');
    if (!carousel || !grid) {
      return;
    }
    const pageCount = this.clipartCarouselPageCount(grid);
    const page = Math.max(0, Math.min(pageCount - 1, Math.round(grid.scrollLeft / Math.max(1, grid.clientWidth))));
    carousel.querySelectorAll('.oc-clipart-carousel-dot').forEach((dot, i) => {
      dot.classList.toggle('oc-active', i === page);
      dot.setAttribute('aria-current', i === page ? 'true' : 'false');
    });
    carousel.querySelector('[data-oc-clipart-prev]')?.toggleAttribute('disabled', page <= 0);
    carousel.querySelector('[data-oc-clipart-next]')?.toggleAttribute('disabled', page >= pageCount - 1);
  }
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (clipartMethods);

/***/ },

/***/ "./src/frontend/customiser/design-variants.js"
/*!****************************************************!*\
  !*** ./src/frontend/customiser/design-variants.js ***!
  \****************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var fabric__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! fabric */ "./node_modules/fabric/dist/index.min.mjs");
/* harmony import */ var _shared_render_math__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../../shared/render-math */ "./src/shared/render-math.js");
/**
 * Design variant carousel, thumbnails, and state switching.
 */

/* eslint-disable no-console */



const designVariantMethods = {
  setupDesignVariantOptions() {
    if (!this.designVariants.length) {
      return;
    }
    const stateSignal = this._panelListenerController?.signal;
    document.querySelectorAll('[data-oc-design-variant]').forEach(btn => {
      btn.addEventListener('click', () => {
        const variant = this.designVariants.find(item => item.id === btn.dataset.ocDesignVariant);
        if (!variant || variant.id === this.selectedDesignVariant) {
          return;
        }
        this.switchDesignVariant(variant.id);
      }, {
        signal: stateSignal
      });
    });
  },
  setupDesignVariantCarousel() {
    const carousel = document.querySelector('[data-oc-design-variant-carousel]');
    const track = carousel?.querySelector('[data-oc-design-variant-track]');
    if (!carousel || !track || carousel.dataset.ocCarouselReady === '1') {
      return;
    }
    carousel.dataset.ocCarouselReady = '1';
    carousel.querySelector('[data-oc-design-variant-prev]')?.addEventListener('click', () => this.scrollDesignVariantCarousel(-1));
    carousel.querySelector('[data-oc-design-variant-next]')?.addEventListener('click', () => this.scrollDesignVariantCarousel(1));
    track.addEventListener('scroll', () => this.updateDesignVariantCarouselDots(), {
      passive: true
    });
    this.refreshDesignVariantCarousel();
  },
  designVariantCarouselPageCount(track) {
    if (!track || !track.clientWidth) {
      return 1;
    }
    return Math.max(1, Math.ceil(track.scrollWidth / track.clientWidth));
  },
  scrollDesignVariantCarousel(direction) {
    const track = document.querySelector('[data-oc-design-variant-track]');
    if (!track) {
      return;
    }
    const page = Math.round(track.scrollLeft / Math.max(1, track.clientWidth)) + direction;
    const maxPage = this.designVariantCarouselPageCount(track) - 1;
    track.scrollTo({
      left: Math.max(0, Math.min(maxPage, page)) * track.clientWidth,
      behavior: 'smooth'
    });
  },
  refreshDesignVariantCarousel() {
    const carousel = document.querySelector('[data-oc-design-variant-carousel]');
    const track = carousel?.querySelector('[data-oc-design-variant-track]');
    const dots = carousel?.querySelector('[data-oc-design-variant-dots]');
    if (!carousel || !track || !dots) {
      return;
    }
    const pageCount = this.designVariantCarouselPageCount(track);
    const maxLeft = Math.max(0, (pageCount - 1) * track.clientWidth);
    if (track.scrollLeft > maxLeft) {
      track.scrollLeft = maxLeft;
    }
    dots.innerHTML = '';
    for (let i = 0; i < pageCount; i++) {
      const dot = document.createElement('button');
      dot.type = 'button';
      dot.className = 'oc-design-variant-carousel-dot';
      dot.setAttribute('aria-label', `Go to artwork option page ${i + 1}`);
      dot.addEventListener('click', () => track.scrollTo({
        left: i * track.clientWidth,
        behavior: 'smooth'
      }));
      dots.appendChild(dot);
    }
    carousel.classList.toggle('oc-design-variant-carousel--single-page', pageCount <= 1);
    this.updateDesignVariantCarouselDots();
  },
  updateDesignVariantCarouselDots() {
    const carousel = document.querySelector('[data-oc-design-variant-carousel]');
    const track = carousel?.querySelector('[data-oc-design-variant-track]');
    if (!carousel || !track) {
      return;
    }
    const pageCount = this.designVariantCarouselPageCount(track);
    const page = Math.max(0, Math.min(pageCount - 1, Math.round(track.scrollLeft / Math.max(1, track.clientWidth))));
    carousel.querySelectorAll('.oc-design-variant-carousel-dot').forEach((dot, i) => {
      dot.classList.toggle('oc-active', i === page);
      dot.setAttribute('aria-current', i === page ? 'true' : 'false');
    });
    carousel.querySelector('[data-oc-design-variant-prev]')?.toggleAttribute('disabled', page <= 0);
    carousel.querySelector('[data-oc-design-variant-next]')?.toggleAttribute('disabled', page >= pageCount - 1);
  },
  async renderDesignVariantThumbnails() {
    const designGeneration = this._designGeneration;
    const canvases = Array.from(document.querySelectorAll('[data-oc-design-variant-thumb]'));
    if (!canvases.length) {
      return;
    }
    for (const canvasEl of canvases) {
      if (designGeneration !== this._designGeneration) {
        return;
      }
      if (canvasEl.dataset.ocThumbRendered === '1') {
        continue;
      }
      const variantId = canvasEl.dataset.ocDesignVariantThumb;
      const state = this.data.designVariantStates?.[variantId];
      if (!state?.areas?.length) {
        canvasEl.closest('.oc-design-variant-option')?.classList.remove('oc-thumb-pending');
        continue;
      }
      try {
        const rendered = await this.renderDesignVariantThumbnailCanvas(canvasEl, state);
        canvasEl.closest('.oc-design-variant-option')?.classList.remove('oc-thumb-pending');
        if (rendered) {
          canvasEl.dataset.ocThumbRendered = '1';
          canvasEl.closest('.oc-design-variant-option')?.classList.add('oc-thumb-rendered');
        }
      } catch (err) {
        canvasEl.closest('.oc-design-variant-option')?.classList.remove('oc-thumb-pending');
        console.warn('[OC] Design variant thumbnail failed:', variantId, err);
      }
    }
  },
  async renderDesignVariantThumbnailCanvas(canvasEl, state) {
    const area = state.areas?.[0];
    if (!area) {
      return;
    }
    const sourceBounds = this.areaBounds(area);
    const bounds = (0,_shared_render_math__WEBPACK_IMPORTED_MODULE_1__.displayBounds)(sourceBounds);
    const size = 320;
    canvasEl.width = size;
    canvasEl.height = size;
    const canvas = new fabric__WEBPACK_IMPORTED_MODULE_0__.StaticCanvas(canvasEl, {
      width: size,
      height: size,
      backgroundColor: 'rgba(255,255,255,0)'
    });
    this._thumbnailCanvases.add(canvas);
    const scale = Math.min(size / Math.max(1, bounds.w || 1), size / Math.max(1, bounds.h || 1));
    const offsetX = (size - (bounds.w || 1) * scale) / 2;
    const offsetY = (size - (bounds.h || 1) * scale) / 2;
    canvas.setViewportTransform([1, 0, 0, 1, offsetX - Number(bounds.x || 0) * scale, offsetY - Number(bounds.y || 0) * scale]);
    canvas._ocScaleX = scale;
    const previousFonts = this.fonts;
    const thumbnailArea = {
      ...area,
      printMethod: ''
    };
    this.fonts = state.fonts || this.fonts || [];
    try {
      for (const layer of area.layers || []) {
        const input = {
          ...(state.layerInputs?.[layer.id] || {})
        };
        if ((layer.type === 'text' || layer.type === 'textarea') && !String(input.value || '').trim()) {
          input.value = layer.settings?.default_text || layer.label || '';
        }
        await this.renderLayer(canvas, layer, input, thumbnailArea);
      }
    } finally {
      this.fonts = previousFonts;
    }
    canvas.renderAll();
    return canvas.getObjects().some(object => object._ocContent === true) && this.canvasHasVisiblePixels(canvasEl);
  },
  canvasHasVisiblePixels(canvasEl) {
    const context = canvasEl.getContext('2d', {
      willReadFrequently: true
    });
    if (!context) {
      return false;
    }
    const {
      width,
      height
    } = canvasEl;
    if (!width || !height) {
      return false;
    }
    const data = context.getImageData(0, 0, width, height).data;
    for (let i = 0; i < data.length; i += 4) {
      if (data[i + 3] > 8) {
        return true;
      }
    }
    return false;
  },
  async switchDesignVariant(variantId) {
    const state = this.data.designVariantStates?.[variantId];
    if (!state?.panelHtml || this._variationSwitchPending || this._controlLocks.has('design')) {
      return;
    }
    this.setControlLock('design', true);
    try {
      await this.applyDesignState(state, variantId, true);
    } catch (error) {
      console.error('[OC] Design option switch failed:', error);
      this.renderPreflightMessages(['The selected artwork option could not be loaded. Please try again.'], []);
    } finally {
      this.setControlLock('design', false);
    }
  },
  cloneLayerInputs(inputs = this.inputs) {
    return JSON.parse(JSON.stringify(inputs || {}));
  },
  saveCurrentDesignVariantInputs() {
    this.syncInputsFromDOM();
    const currentState = this.data.designVariantStates?.[this.selectedDesignVariant];
    if (currentState) {
      currentState.layerInputs = this.cloneLayerInputs();
    }
  },
  async applyDesignState(state, variantId, preserveCurrentState = true) {
    if (!state?.panelHtml) {
      return;
    }
    if (preserveCurrentState) {
      this.saveCurrentDesignVariantInputs();
    }
    const designGeneration = this.invalidateDesignState();
    this.activeArea = 0;
    this.selectedDesignVariant = variantId || state.selectedDesignVariant || '';
    this._customisationActive = true;
    const currentPanel = document.getElementById('oc-customiser-panel');
    if (currentPanel) {
      currentPanel.outerHTML = state.panelHtml;
    }
    this.data.designId = state.designId;
    this.data.designName = state.designName;
    this.data.flatRate = state.flatRate;
    this.data.areas = state.areas || [];
    this.data.layerInputs = state.layerInputs || {};
    this.data.clipartByLayer = state.clipartByLayer || {};
    this.data.clipartGroups = state.clipartGroups || [];
    this.data.designVariants = state.designVariants || this.designVariants;
    this.data.designVariantStates = state.designVariantStates || this.data.designVariantStates || {};
    this.data.selectedDesignVariant = this.selectedDesignVariant;
    this.data.fonts = state.fonts || this.data.fonts || [];
    this.data.colours = state.colours || this.data.colours || [];
    this.data.imageFilters = state.imageFilters || this.data.imageFilters || [];
    this.data.restrictedLayerColours = state.restrictedLayerColours || this.data.restrictedLayerColours || {};
    this.areas = this.data.areas || [];
    this.fonts = this.data.fonts || [];
    this.designVariants = this.data.designVariants || [];
    this.layersById = {};
    this.areas.forEach(area => (area.layers || []).forEach(layer => {
      this.layersById[layer.id] = layer;
    }));
    this.inputs = {};
    Object.entries(this.cloneLayerInputs(state.layerInputs)).forEach(([k, v]) => {
      const layerId = parseInt(k, 10);
      this.inputs[layerId] = {
        ...v
      };
      this.clampLayerInputValue(layerId);
    });
    this.data.layerInputs = this.cloneLayerInputs(this.inputs);
    this.preflightRoot = document.getElementById('oc-preflight-messages');
    this.beginDesignStateListeners();
    this.seedLockedLayerDefaults();
    this.seedTemplateImageDefaults();
    this.seedLayerFontDefaults();
    this.seedLinkedImageInputs();
    this.seedLinkedColourInputs();
    this.applyInputsToDOM({
      redraw: false
    });
    this.setupInputListeners();
    this.setupDesignVariantOptions();
    this.setupDesignVariantCarousel();
    this.renderDesignVariantThumbnails();
    this.setupClipartCarousels();
    this._uploadSetupPromise = this.setupUploadZones();
    this._initialAiFilterPromise = this.applyInitialAiFilters();
    this.applyActiveAreaState(0);
    if (preserveCurrentState) {
      this.requestPreviewFocus();
    }
    this.applyControlLocks();
    this.updateHiddenField();
    await this.startCanvasInitialisation();
    return designGeneration === this._designGeneration;
  }
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (designVariantMethods);

/***/ },

/***/ "./src/frontend/customiser/gallery-preview.js"
/*!****************************************************!*\
  !*** ./src/frontend/customiser/gallery-preview.js ***!
  \****************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/**
 * Product gallery preview integration for the frontend customiser.
 */

/* eslint-disable no-console */

const PREVIEW_DISCLAIMER = 'This is a preview, not the final product. In some cases, our production team may need to adjust the personalisation. Any changes will preserve the spelling, grammar and spirit of your design, including any image supplied.';
const GALLERY_IMAGE_SELECTORS = [
// True Video Product Gallery (Swiper): prefer active non-video slide.
'.tvpg-main-slider .swiper-slide-active:not(.tvpg-video-slide) .woocommerce-product-gallery__image img', '.tvpg-main-slider .swiper-slide-active .woocommerce-product-gallery__image img', '.tvpg-main-slider .swiper-slide:not(.tvpg-video-slide) .woocommerce-product-gallery__image img',
// Flatsome theme.
'.product-gallery-slider .flickity-slider .woocommerce-product-gallery__image.is-selected img', '.product-gallery-slider .flickity-slider .slide.is-selected img', '.product-gallery-slider .is-selected img', '.product-gallery-slider .flickity-slider .woocommerce-product-gallery__image:first-child img', '.product-gallery-slider .flickity-slider .slide:first-child img', '.product-gallery .woocommerce-product-gallery__image:first-child img', '.product-images .woocommerce-product-gallery__image:first-child a img', '.product-image-wrap .woocommerce-product-gallery__image:first-child img',
// WC Blocks.
'.wp-block-woocommerce-product-image-gallery .woocommerce-product-gallery__image:first-child img',
// Default WC / Storefront.
'.woocommerce-product-gallery__image:first-child a img', '.woocommerce-product-gallery__image:first-child img',
// Broad fallback.
'.woocommerce-product-gallery .wp-post-image', '.wp-post-image'];
const galleryPreviewMethods = {
  captureGalleryNodeState(node) {
    if (!node) {
      return null;
    }
    return new Map(Array.from(node.attributes || []).map(attribute => [attribute.name, attribute.value]));
  },
  recordGalleryNodeState(node, before) {
    if (!node || !before) {
      return;
    }
    const after = this.captureGalleryNodeState(node);
    const names = new Set([...before.keys(), ...after.keys()]);
    const state = node._ocOriginalPreviewState || new Map();
    names.forEach(name => {
      const beforeValue = before.has(name) ? before.get(name) : null;
      const afterValue = after.has(name) ? after.get(name) : null;
      if (beforeValue === afterValue) {
        return;
      }
      const previous = state.get(name);
      state.set(name, {
        original: previous && previous.preview === beforeValue ? previous.original : beforeValue,
        preview: afterValue
      });
    });
    if (state.size) {
      node._ocOriginalPreviewState = state;
      this._galleryPreviewNodes.add(node);
    }
  },
  restoreProductGallery() {
    this._galleryPreviewGeneration += 1;
    document.querySelectorAll('.oc-live-preview-slide, .oc-live-preview-thumb-slide').forEach(slide => slide.remove());
    this._galleryPreviewNodes.forEach(node => {
      const state = node._ocOriginalPreviewState;
      if (!state) {
        return;
      }
      state.forEach((values, name) => {
        const current = node.hasAttribute(name) ? node.getAttribute(name) : null;
        if (current !== values.preview) {
          return;
        }
        if (values.original === null) {
          node.removeAttribute(name);
        } else {
          node.setAttribute(name, values.original);
        }
      });
      delete node._ocOriginalPreviewState;
    });
    this._galleryPreviewNodes.clear();
    this._galleryFallbackNodeStates.forEach((state, node) => {
      if (state.parent) {
        const reference = state.nextSibling?.parentNode === state.parent ? state.nextSibling : null;
        state.parent.insertBefore(node, reference);
      }
      const currentNames = Array.from(node.attributes || []).map(attribute => attribute.name);
      currentNames.forEach(name => {
        if (!state.attributes.has(name)) {
          node.removeAttribute(name);
        }
      });
      state.attributes.forEach((value, name) => node.setAttribute(name, value));
    });
    this._galleryFallbackNodeStates.clear();
    this.releaseTVPGPreviewLock(true);
    this.setPanelPreviewHandoff(false);
    this.findGalleryImage();
    document.querySelector('.tvpg-main-slider')?.swiper?.update?.();
    document.querySelector('.tvpg-thumb-slider')?.swiper?.update?.();
    this.refreshFlatsomeGallery();
  },
  findGalleryImage() {
    for (const sel of GALLERY_IMAGE_SELECTORS) {
      const img = document.querySelector(sel);
      if (img) {
        this.galleryImg = img;
        return;
      }
    }
    this.galleryImg = null;
  },
  applyPreviewToImage(img, dataUrl, dimensions = null) {
    if (!img) {
      return;
    }
    const imageState = this.captureGalleryNodeState(img);
    const hasDimensions = dimensions?.width && dimensions?.height;
    const aspectRatio = hasDimensions ? `${dimensions.width} / ${dimensions.height}` : '';
    const ratioPadding = hasDimensions ? `${dimensions.height / dimensions.width * 100}%` : '';
    img.src = dataUrl;
    img.srcset = '';
    img.sizes = '';
    img.title = PREVIEW_DISCLAIMER;
    img.classList.add('oc-live-preview-applied');
    if (hasDimensions) {
      img.width = dimensions.width;
      img.height = dimensions.height;
      img.style.aspectRatio = aspectRatio;
    }
    img.style.display = 'block';
    img.style.width = '100%';
    img.style.objectFit = 'contain';
    img.style.height = 'auto';
    img.style.maxHeight = 'none';
    img.style.position = 'static';

    // Update zoom / lightbox href if wrapped in <a>.
    const a = img.closest('a');
    if (a) {
      const linkState = this.captureGalleryNodeState(a);
      a.href = dataUrl;
      a.setAttribute('data-src', dataUrl);
      this.recordGalleryNodeState(a, linkState);
    }

    // WooCommerce zoom/lightbox compatibility attributes.
    img.setAttribute('data-large_image', dataUrl);
    img.setAttribute('data-large-image', dataUrl);
    img.setAttribute('data-src', dataUrl);
    img.setAttribute('data-lazy-src', dataUrl);
    img.setAttribute('data-zoom-image', dataUrl);
    img.removeAttribute('data-srcset');
    img.removeAttribute('data-lazy-srcset');
    img.removeAttribute('data-o_srcset');
    img.removeAttribute('data-o_src');
    this.recordGalleryNodeState(img, imageState);
    const galleryItem = img.closest('.woocommerce-product-gallery__image, .product-gallery-slider .slide');
    if (galleryItem) {
      const galleryItemState = this.captureGalleryNodeState(galleryItem);
      galleryItem.setAttribute('data-thumb', dataUrl);
      if (hasDimensions && !img.closest('.product-thumbnails, .tvpg-thumb-slider')) {
        galleryItem.classList.add('oc-live-preview-frame');
        galleryItem.style.aspectRatio = aspectRatio;
        galleryItem.style.height = 'auto';
        galleryItem.style.paddingTop = '0';
        galleryItem.style.paddingBottom = ratioPadding;
        const link = img.closest('a');
        if (link && galleryItem.contains(link)) {
          const linkState = this.captureGalleryNodeState(link);
          link.classList.add('oc-live-preview-frame');
          link.style.aspectRatio = aspectRatio;
          link.style.height = 'auto';
          link.style.paddingTop = '0';
          link.style.paddingBottom = ratioPadding;
          this.recordGalleryNodeState(link, linkState);
        }
      }
      this.recordGalleryNodeState(galleryItem, galleryItemState);
    }
  },
  refreshFlatsomeGallery() {
    const slider = document.querySelector('.product-gallery-slider');
    if (!slider) {
      return;
    }
    const flickity = slider.flickity || window.jQuery?.(slider).data('flickity');
    flickity?.reloadCells?.();
    flickity?.resize?.();
  },
  getFlickityInstance(slider) {
    if (!slider) {
      return null;
    }
    return slider.flickity || window.jQuery?.(slider).data('flickity') || null;
  },
  applyFlatsomeOverlayPreview(dataUrl, dimensions = null) {
    const slider = document.querySelector('.product-gallery-slider');
    if (!slider) {
      return false;
    }
    const realSlides = slider.querySelectorAll('.woocommerce-product-gallery__image:not(.oc-live-preview-slide), .slide:not(.oc-live-preview-slide)');
    if (realSlides.length <= 1) {
      return false;
    }
    let flickity = this.getFlickityInstance(slider);
    let previewSlide = slider.querySelector('.oc-live-preview-slide');
    if (!previewSlide) {
      previewSlide = document.createElement('div');
      previewSlide.className = 'woocommerce-product-gallery__image slide oc-live-preview-slide';
      previewSlide.innerHTML = '<a href="#">' + '<img class="oc-live-preview-image wp-post-image" alt="Custom preview">' + '</a>';
      if (flickity?.append) {
        flickity.append(previewSlide);
      } else {
        slider.appendChild(previewSlide);
      }
    }
    const previewImg = previewSlide.querySelector('img.oc-live-preview-image');
    if (previewImg) {
      this.applyPreviewToImage(previewImg, dataUrl, dimensions);
    }
    previewSlide.setAttribute('data-thumb', dataUrl);
    previewSlide.querySelector('a')?.setAttribute('href', dataUrl);
    flickity = this.getFlickityInstance(slider);
    if (flickity) {
      flickity.reloadCells?.();
      flickity.resize?.();
      const previewIndex = (flickity.cells || []).findIndex(cell => cell.element === previewSlide);
      if (previewIndex >= 0) {
        flickity.select?.(previewIndex, false, true);
      }
    }
    return true;
  },
  setPanelPreviewHandoff(isActive) {
    const panel = document.getElementById('oc-customiser-panel');
    if (panel) {
      panel.classList.toggle('oc-gallery-preview-active', isActive);
    }
  },
  mountPreviewInGallery() {
    const canvasWrap = document.getElementById('oc-canvas-wrap');
    if (!canvasWrap) {
      return false;
    }
    if (!this._galleryFallbackNodeStates.has(canvasWrap)) {
      this._galleryFallbackNodeStates.set(canvasWrap, {
        parent: canvasWrap.parentNode,
        nextSibling: canvasWrap.nextSibling,
        attributes: this.captureGalleryNodeState(canvasWrap)
      });
    }
    const gallery = document.querySelector('.product-gallery, .product-images, .woocommerce-product-gallery, .product .images');
    if (!gallery) {
      canvasWrap.classList.add('oc-preview-visible');
      return false;
    }
    if (canvasWrap.parentElement !== gallery) {
      gallery.prepend(canvasWrap);
    }
    canvasWrap.classList.add('oc-gallery-mounted-preview', 'oc-preview-visible');
    return true;
  },
  stopTVPGAutoScroll(...swipers) {
    swipers.forEach(swiper => {
      if (!swiper) {
        return;
      }
      if (!this._tvpgLockedSwipers.has(swiper)) {
        swiper._ocPreviewAutoplayWasRunning = Boolean(swiper.autoplay?.running);
        this._tvpgLockedSwipers.add(swiper);
      }
      swiper.autoplay?.stop?.();
    });
  },
  releaseTVPGPreviewLock(resumeAutoplay = false) {
    this._focusPreviewSlide = false;
    this._tvpgPreviewLocked = false;
    if (!resumeAutoplay) {
      return;
    }
    this._tvpgLockedSwipers.forEach(swiper => {
      if (swiper._ocPreviewLockHandler) {
        (swiper._ocPreviewLockEvents || []).forEach(eventName => swiper.off?.(eventName, swiper._ocPreviewLockHandler));
      }
      if (swiper._ocPreviewAutoplayWasRunning) {
        swiper.autoplay?.start?.();
      }
      delete swiper._ocPreviewLockHandler;
      delete swiper._ocPreviewLockEvents;
      delete swiper._ocPreviewLockBound;
      delete swiper._ocPreviewSlideIndex;
      delete swiper._ocPreviewLocking;
      delete swiper._ocPreviewAutoplayWasRunning;
    });
    this._tvpgLockedSwipers.clear();
  },
  setupCartGalleryUnlock() {
    if (this._cartGalleryUnlockBound) {
      return;
    }
    this._cartGalleryUnlockBound = true;
    window.jQuery?.(document.body).on?.('added_to_cart', () => {
      this.restoreProductGallery();
    });
  },
  lockTVPGPreviewSlide(swiper, slide) {
    if (!swiper || !slide) {
      return;
    }
    const previewIndex = Array.from(swiper.slides || []).indexOf(slide);
    if (previewIndex < 0) {
      return;
    }
    swiper._ocPreviewSlideIndex = previewIndex;
    if (swiper._ocPreviewLockBound) {
      return;
    }
    const keepPreviewActive = () => {
      if (!this._tvpgPreviewLocked || swiper._ocPreviewLocking) {
        return;
      }
      const targetIndex = swiper._ocPreviewSlideIndex;
      if (targetIndex === undefined || swiper.activeIndex === targetIndex) {
        return;
      }
      swiper._ocPreviewLocking = true;
      this.requestStateAnimationFrame(() => {
        swiper.slideTo?.(targetIndex, 0, false);
        swiper._ocPreviewLocking = false;
      });
    };
    const lockEvents = ['activeIndexChange', 'slideChange', 'transitionStart'];
    lockEvents.forEach(eventName => swiper.on?.(eventName, keepPreviewActive));
    swiper._ocPreviewLockBound = true;
    swiper._ocPreviewLockHandler = keepPreviewActive;
    swiper._ocPreviewLockEvents = lockEvents;
    this._tvpgLockedSwipers.add(swiper);
  },
  applyTVPGOverlayPreview(dataUrl, dimensions = null) {
    const mainSliderEl = document.querySelector('.tvpg-main-slider');
    const mainWrapper = mainSliderEl?.querySelector('.swiper-wrapper');
    if (!mainSliderEl || !mainWrapper) {
      return false;
    }
    const realSlides = mainWrapper.querySelectorAll('.swiper-slide:not(.oc-live-preview-slide)');
    if (realSlides.length <= 1) {
      return false;
    }
    let mainPreviewSlide = mainWrapper.querySelector('.swiper-slide.oc-live-preview-slide');
    if (!mainPreviewSlide) {
      mainPreviewSlide = document.createElement('div');
      mainPreviewSlide.className = 'swiper-slide oc-live-preview-slide';
      mainPreviewSlide.innerHTML = '<div class="woocommerce-product-gallery__image">' + '<img class="oc-live-preview-image" alt="Custom preview">' + '</div>';
      mainWrapper.appendChild(mainPreviewSlide);
    }
    const mainImg = mainPreviewSlide.querySelector('img.oc-live-preview-image');
    if (mainImg) {
      this.applyPreviewToImage(mainImg, dataUrl, dimensions);
    }
    const thumbSliderEl = document.querySelector('.tvpg-thumb-slider');
    const thumbWrapper = thumbSliderEl?.querySelector('.swiper-wrapper');
    if (thumbWrapper) {
      let thumbPreviewSlide = thumbWrapper.querySelector('.swiper-slide.oc-live-preview-thumb-slide');
      if (!thumbPreviewSlide) {
        thumbPreviewSlide = document.createElement('div');
        thumbPreviewSlide.className = 'swiper-slide oc-live-preview-thumb-slide';
        thumbPreviewSlide.innerHTML = '<img class="oc-live-preview-thumb-image" alt="Custom preview thumbnail">';
        thumbWrapper.appendChild(thumbPreviewSlide);
      }
      const thumbImg = thumbPreviewSlide.querySelector('img.oc-live-preview-thumb-image');
      if (thumbImg) {
        this.applyPreviewToImage(thumbImg, dataUrl, dimensions);
      }
    }

    // Swiper attaches instances to the root element; update so the new last slide is navigable.
    const mainSwiper = mainSliderEl.swiper;
    const thumbSwiper = thumbSliderEl?.swiper;
    this.stopTVPGAutoScroll(mainSwiper, thumbSwiper);
    mainSwiper?.update?.();
    thumbSwiper?.update?.();
    this.lockTVPGPreviewSlide(mainSwiper, mainPreviewSlide);
    this.lockTVPGPreviewSlide(thumbSwiper, thumbWrapper?.querySelector('.swiper-slide.oc-live-preview-thumb-slide'));
    if (this._focusPreviewSlide && mainSwiper?.slides?.length) {
      this._tvpgPreviewLocked = true;
      const previewIndex = mainSwiper._ocPreviewSlideIndex ?? mainSwiper.slides.length - 1;
      const thumbIndex = thumbSwiper?._ocPreviewSlideIndex ?? previewIndex;
      mainSwiper.slideTo(previewIndex);
      thumbSwiper?.slideTo?.(thumbIndex);
    }
    this._focusPreviewSlide = false;
    return true;
  },
  pushToGallery(canvas) {
    if (!this._customisationActive) {
      return;
    }
    const generation = ++this._galleryPreviewGeneration;
    this.findGalleryImage();
    let dataUrl;
    try {
      dataUrl = canvas.toDataURL({
        format: 'jpeg',
        quality: 0.92
      });
    } catch (e) {
      console.warn('[OC] toDataURL failed - image may be cross-origin:', e.message);
      return;
    }
    const dimensions = {
      width: Math.round(canvas.getWidth?.() || canvas.width || 0),
      height: Math.round(canvas.getHeight?.() || canvas.height || 0)
    };
    const previewImg = document.getElementById('oc-canvas-preview');
    if (previewImg) {
      previewImg.src = dataUrl;
      previewImg.srcset = '';
      previewImg.title = PREVIEW_DISCLAIMER;
      if (dimensions.width && dimensions.height) {
        previewImg.width = dimensions.width;
        previewImg.height = dimensions.height;
      }
    }
    if (!this._hasCustomerPersonalisation) {
      return;
    }
    if (this.applyTVPGOverlayPreview(dataUrl, dimensions)) {
      this.setPanelPreviewHandoff(true);
      this._focusPreviewSlide = false;
      return;
    }
    if (this.applyFlatsomeOverlayPreview(dataUrl, dimensions)) {
      this.setPanelPreviewHandoff(true);
      this._focusPreviewSlide = false;
      return;
    }
    const targets = new Set();
    if (this.galleryImg) {
      targets.add(this.galleryImg);
    }
    ['.tvpg-main-slider .swiper-slide .woocommerce-product-gallery__image img', '.product-gallery-slider .flickity-slider .woocommerce-product-gallery__image img', '.product-gallery-slider .flickity-slider .slide img', '.product-gallery-slider .slide img', '.product-gallery-slider img', '.product-thumbnails img', '.product-gallery .woocommerce-product-gallery__image img', '.product-images .woocommerce-product-gallery__image img', '.product-image-wrap .woocommerce-product-gallery__image img', '.woocommerce-product-gallery .woocommerce-product-gallery__image img'].forEach(selector => {
      document.querySelectorAll(selector).forEach(img => targets.add(img));
    });
    const applyTargets = () => {
      if (generation !== this._galleryPreviewGeneration || !this._customisationActive) {
        return;
      }
      targets.forEach(img => this.applyPreviewToImage(img, dataUrl, dimensions));
    };
    applyTargets();
    if (document.querySelector('.product-gallery-slider')) {
      this.refreshFlatsomeGallery();
      this.requestStateAnimationFrame(applyTargets);
      this.clearStateTimeout(this._galleryPreviewTimer);
      this._galleryPreviewTimer = this.setStateTimeout(() => {
        this._galleryPreviewTimer = null;
        applyTargets();
      }, 250);
    }
    this.setPanelPreviewHandoff(targets.size > 0 || this.mountPreviewInGallery());
    this._focusPreviewSlide = false;
  },
  requestPreviewFocus() {
    this._hasCustomerPersonalisation = true;
    this._focusPreviewSlide = true;
  },
  saveActiveVariationState() {
    if (!this._customisationActive) {
      return null;
    }
    this.syncInputsFromDOM();
    const snapshot = {
      designId: parseInt(this.data.designId, 10) || 0,
      selectedDesignVariant: this.selectedDesignVariant || '',
      layerInputs: this.cloneLayerInputs()
    };
    const state = this.productVariationStates[this._activeVariationKey];
    if (!state) {
      return snapshot;
    }
    state.selectedDesignVariant = snapshot.selectedDesignVariant;
    const selectedState = state.designVariantStates?.[snapshot.selectedDesignVariant];
    if (selectedState) {
      selectedState.layerInputs = this.cloneLayerInputs(snapshot.layerInputs);
    }
    if (parseInt(state.designId || state.design_id, 10) === snapshot.designId) {
      state.layerInputs = this.cloneLayerInputs(snapshot.layerInputs);
    }
    return snapshot;
  },
  scheduleProductVariationSwitch(variationId) {
    this.clearStateTimeout(this._variationChangeTimer);
    this._variationChangeTimer = this.setStateTimeout(() => {
      this._variationChangeTimer = null;
      this.switchProductVariation(variationId);
    }, 100);
  },
  async fetchProductVariationState(key, requestSeq) {
    const designUrl = this.data.productDesignUrl || `${window.location.origin}/wp-json/overcustomise/v1/product-design/${this.data.productId || 0}`;
    const url = new URL(designUrl, window.location.origin);
    url.searchParams.set('variant_id', key);
    const request = this.createStateAbortController(10000);
    this._variationAbortController = request.controller;
    try {
      const response = await fetch(url.toString(), {
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json'
        },
        signal: request.controller.signal
      });
      if (!response.ok) {
        throw new Error(`Variation design request failed (${response.status})`);
      }
      const state = await response.json();
      if (!state || typeof state !== 'object' || Array.isArray(state)) {
        throw new Error('Variation design response was invalid.');
      }
      if (requestSeq === this._variationRequestSeq) {
        this.productVariationStates[key] = state;
      }
      return state;
    } catch (error) {
      if (request.timedOut()) {
        throw new Error('Variation design request timed out.');
      }
      throw error;
    } finally {
      request.release();
      if (this._variationAbortController === request.controller) {
        this._variationAbortController = null;
      }
    }
  },
  setupVariationGalleryHandoff() {
    const form = document.querySelector('form.variations_form, form.cart, form[data-wp-on--submit*="addToCart"]');
    if (!form || form._ocVariationGalleryHandoffBound) {
      return;
    }
    form._ocVariationGalleryHandoffBound = true;
    const getSelectedVariationId = () => parseInt(form.querySelector('input[name="variation_id"]')?.value || '0', 10) || 0;
    const releasePreviewLock = () => this.releaseTVPGPreviewLock();
    const handleVariationChange = variation => {
      releasePreviewLock();
      this.clearStateTimeout(this._variationChangeTimer);
      this._variationChangeTimer = null;
      this.switchProductVariation(parseInt(variation?.variation_id || getSelectedVariationId(), 10) || 0);
    };
    form.addEventListener('change', event => {
      if (event.target?.closest?.('.variations, [name^="attribute_"]')) {
        releasePreviewLock();
        this.scheduleProductVariationSwitch(getSelectedVariationId());
      }
    });
    window.jQuery?.(form).on?.('woocommerce_variation_select_change', releasePreviewLock);
    window.jQuery?.(form).on?.('reset_data', () => {
      releasePreviewLock();
      this.scheduleProductVariationSwitch(0);
    });
    window.jQuery?.(form).on?.('found_variation show_variation', (event, variation) => handleVariationChange(variation));
    const initialVariationId = getSelectedVariationId();
    if (initialVariationId) {
      this.switchProductVariation(initialVariationId);
    }
  },
  async switchProductVariation(variationId) {
    const key = String(Math.max(0, parseInt(variationId, 10) || 0));
    if (this._variationSwitchPromise && this._pendingVariationKey === key) {
      return this._variationSwitchPromise;
    }
    if (!this._variationSwitchPromise && this._activeVariationKey === key && !this._variationSwitchFailed) {
      return true;
    }
    const previousSwitch = this._variationSwitchPromise;
    const previousKey = this._activeVariationKey;
    const initialSnapshot = this.saveActiveVariationState();
    const requestSeq = ++this._variationRequestSeq;
    this._variationAbortController?.abort();
    this._pendingVariationKey = key;
    this._variationSwitchPending = true;
    this._variationSwitchFailed = false;
    this.setControlLock('variation', true);
    const switchPromise = (async () => {
      try {
        if (previousSwitch) {
          await previousSwitch;
        }
        if (requestSeq !== this._variationRequestSeq) {
          return false;
        }
        let state = this.productVariationStates[key];
        if (!state) {
          state = await this.fetchProductVariationState(key, requestSeq);
        }
        if (requestSeq !== this._variationRequestSeq) {
          return false;
        }
        if (!previousKey && initialSnapshot && state.active) {
          const initialVariantState = state.designVariantStates?.[initialSnapshot.selectedDesignVariant];
          if (initialVariantState && parseInt(initialVariantState.designId, 10) === initialSnapshot.designId) {
            state.selectedDesignVariant = initialSnapshot.selectedDesignVariant;
            initialVariantState.layerInputs = this.cloneLayerInputs(initialSnapshot.layerInputs);
          }
        }
        if (!state.active || !state.panelHtml) {
          this._activeVariationKey = key;
          this.deactivateCustomisation();
          return true;
        }
        const selectedVariant = state.selectedDesignVariant || `design-${state.designId || state.design_id}`;
        const selectedState = state.designVariantStates?.[selectedVariant] || state;
        const nextState = {
          ...selectedState,
          designVariants: selectedState.designVariants || state.designVariants || [],
          designVariantStates: state.designVariantStates || {},
          selectedDesignVariant: selectedVariant
        };
        const applied = await this.applyDesignState(nextState, selectedVariant, false);
        if (!applied || requestSeq !== this._variationRequestSeq) {
          return false;
        }
        state.selectedDesignVariant = selectedVariant;
        this._activeVariationKey = key;
        return true;
      } catch (error) {
        if (requestSeq !== this._variationRequestSeq) {
          return false;
        }
        console.warn('[OC] Variation design load failed:', error);
        this._variationSwitchFailed = true;
        this.renderPreflightMessages(['We could not load the personalisation options for this variation. Check your connection, then press Add to cart to retry.'], []);
        return false;
      }
    })();
    this._variationSwitchPromise = switchPromise;
    try {
      const switched = await switchPromise;
      if (requestSeq === this._variationRequestSeq && switched) {
        this._variationSwitchFailed = false;
      }
      return switched;
    } finally {
      if (this._variationSwitchPromise === switchPromise) {
        this._variationSwitchPromise = null;
        this._pendingVariationKey = '';
        this._variationSwitchPending = false;
        this.setControlLock('variation', false);
      }
    }
  },
  deactivateCustomisation() {
    this.invalidateDesignState();
    this._customisationActive = false;
    const panel = document.getElementById('oc-customiser-panel');
    if (panel) {
      panel.hidden = true;
      panel.setAttribute('aria-hidden', 'true');
      panel.querySelectorAll('input, select, textarea, button').forEach(control => {
        control.disabled = true;
      });
    }
    this.updateHiddenField();
    this.restoreProductGallery();
  }
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (galleryPreviewMethods);

/***/ },

/***/ "./src/frontend/customiser/input-controls.js"
/*!***************************************************!*\
  !*** ./src/frontend/customiser/input-controls.js ***!
  \***************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* eslint-disable no-console, @wordpress/no-unused-vars-before-return */

const LINKED_IMAGE_INPUT_KEYS = ['attachmentId', 'attachmentUrl', 'sourceAttachmentId', 'sourceAttachmentUrl', 'originalAttachmentUrl', 'sourceOriginalAttachmentUrl', 'artworkFileType', 'sourceArtworkFileType', 'previewAttachmentId', 'sourcePreviewAttachmentId', 'imageMeta', 'sourceImageMeta', 'imageFilterId'];
const inputControlMethods = {
  // ── Input listeners ─────────────────────────────────────────────────────────

  closeFontComboboxes(resetSearch = false) {
    document.querySelectorAll('.oc-font-combobox.oc-open').forEach(combo => {
      combo.classList.remove('oc-open');
      const input = combo.querySelector('[data-oc-font-search]');
      input?.setAttribute('aria-expanded', 'false');
      if (resetSearch) {
        const select = document.querySelector(`[data-oc-layer-font="${combo.dataset.ocFontCombobox}"]`);
        if (select) {
          this.updateFontCombobox(select);
        }
      }
    });
  },
  updateFontCombobox(select) {
    const lid = select?.dataset?.ocLayerFont;
    if (!lid) {
      return;
    }
    const combo = document.querySelector(`.oc-font-combobox[data-oc-font-combobox="${lid}"]`);
    const input = combo?.querySelector('[data-oc-font-search]');
    const options = combo?.querySelectorAll('[data-oc-font-option]');
    const selected = select.options[select.selectedIndex];
    if (!combo || !input || !selected) {
      return;
    }
    input.value = selected.textContent.trim();
    input.style.fontFamily = selected.style.fontFamily || '';
    options?.forEach(option => {
      option.hidden = false;
      const isSelected = option.dataset.ocFontOption === select.value;
      option.setAttribute('aria-selected', isSelected ? 'true' : 'false');
    });
    combo.querySelector('[data-oc-font-empty]')?.setAttribute('hidden', '');
  },
  updateColourPickerTrigger(swatch) {
    const picker = swatch?.closest('.oc-colour-picker');
    const preview = picker?.querySelector('[data-oc-colour-picker-preview]');
    const label = picker?.querySelector('[data-oc-colour-picker-label]');
    if (preview) {
      preview.style.background = swatch.dataset.hex || '';
    }
    if (label) {
      label.textContent = swatch.dataset.colourName || '';
    }
  },
  closeColourDialog(dialog) {
    if (!dialog) {
      return;
    }
    dialog.classList.remove('is-visible');
    if (typeof dialog.close === 'function' && dialog.open) {
      dialog.close();
    } else {
      dialog.removeAttribute('open');
    }
  },
  setupFontComboboxes() {
    const stateSignal = this._panelListenerController?.signal;
    const useNativeFontSelect = window.matchMedia?.('(max-width: 639px) and (hover: none) and (pointer: coarse)')?.matches;
    document.querySelectorAll('[data-oc-layer-font]').forEach(select => {
      if (useNativeFontSelect) {
        select.setAttribute('aria-hidden', 'false');
        select.removeAttribute('tabindex');
        return;
      }
      const lid = select.dataset.ocLayerFont;
      const combo = document.querySelector(`.oc-font-combobox[data-oc-font-combobox="${lid}"]`);
      if (!combo) {
        return;
      }
      if (combo.dataset.ocFontComboboxReady === '1') {
        this.updateFontCombobox(select);
        return;
      }
      const input = combo.querySelector('[data-oc-font-search]');
      const list = combo.querySelector('[data-oc-font-list]');
      const options = Array.from(combo.querySelectorAll('[data-oc-font-option]'));
      const empty = combo.querySelector('[data-oc-font-empty]');
      if (!input || !list || !options.length) {
        return;
      }
      combo.dataset.ocFontComboboxReady = '1';
      let filterFrame = null;
      const setOpen = isOpen => {
        combo.classList.toggle('oc-open', isOpen);
        input.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      };
      const selectedFontLabel = () => select.options[select.selectedIndex]?.textContent.trim() || '';
      const filterOptions = (queryOverride = null) => {
        const query = (queryOverride ?? input.value).trim().toLowerCase();
        let visibleCount = 0;
        options.forEach(option => {
          const isVisible = option.textContent.trim().toLowerCase().includes(query);
          option.hidden = !isVisible;
          if (isVisible) {
            visibleCount++;
          }
        });
        if (empty) {
          empty.hidden = visibleCount > 0;
        }
      };
      const scheduleFilterOptions = () => {
        if (filterFrame) {
          window.cancelAnimationFrame(filterFrame);
        }
        filterFrame = this.requestStateAnimationFrame(() => {
          filterFrame = null;
          filterOptions();
        });
      };
      const firstVisibleOption = () => options.find(option => !option.hidden);
      const selectFont = (value, keepOpen = false) => {
        select.value = value;
        select.dispatchEvent(new Event('change', {
          bubbles: true
        }));
        if (keepOpen) {
          filterOptions('');
          setOpen(true);
          input.focus({
            preventScroll: true
          });
        } else {
          setOpen(false);
        }
      };
      this.updateFontCombobox(select);
      filterOptions();
      input.addEventListener('focus', () => {
        if (input.value.trim() === selectedFontLabel()) {
          filterOptions('');
        } else {
          scheduleFilterOptions();
        }
        setOpen(true);
      }, {
        signal: stateSignal
      });
      input.addEventListener('click', () => {
        if (input.value.trim() === selectedFontLabel()) {
          filterOptions('');
        }
        setOpen(true);
      }, {
        signal: stateSignal
      });
      input.addEventListener('input', () => {
        scheduleFilterOptions();
        setOpen(true);
      }, {
        signal: stateSignal
      });
      input.addEventListener('search', () => {
        scheduleFilterOptions();
        setOpen(true);
      }, {
        signal: stateSignal
      });
      input.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
          setOpen(false);
          this.updateFontCombobox(select);
          return;
        }
        if (e.key === 'ArrowDown') {
          e.preventDefault();
          filterOptions();
          setOpen(true);
          firstVisibleOption()?.focus();
          return;
        }
        if (e.key === 'Enter') {
          e.preventDefault();
          filterOptions();
          const option = firstVisibleOption();
          if (option) {
            selectFont(option.dataset.ocFontOption);
          } else {
            setOpen(false);
            this.updateFontCombobox(select);
          }
        }
      }, {
        signal: stateSignal
      });
      input.addEventListener('blur', () => {
        this.setStateTimeout(() => {
          if (stateSignal?.aborted) {
            return;
          }
          if (!combo.contains(combo.ownerDocument.activeElement)) {
            setOpen(false);
            this.updateFontCombobox(select);
          }
        }, 120);
      }, {
        signal: stateSignal
      });
      options.forEach(option => {
        option.addEventListener('pointerdown', e => {
          e.preventDefault();
          selectFont(option.dataset.ocFontOption);
        }, {
          signal: stateSignal
        });
        option.addEventListener('click', () => selectFont(option.dataset.ocFontOption), {
          signal: stateSignal
        });
        option.addEventListener('keydown', e => {
          const visible = options.filter(item => !item.hidden);
          const index = visible.indexOf(option);
          if (e.key === 'ArrowDown') {
            e.preventDefault();
            visible[index + 1]?.focus();
          } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            (visible[index - 1] || input).focus();
          } else if (e.key === 'Escape') {
            setOpen(false);
            input.focus();
          }
        }, {
          signal: stateSignal
        });
      });
    });
    if (!this.fontComboboxDocumentClickBound) {
      this.fontComboboxDocumentClickBound = true;
      document.addEventListener('click', e => {
        if (!e.target.closest('.oc-font-combobox')) {
          this.closeFontComboboxes(true);
        }
      });
    }
  },
  setupInputListeners() {
    const stateSignal = this._panelListenerController?.signal;
    const designGeneration = this._designGeneration;
    this.setupControlAccessibility();
    this.setupFontComboboxes();

    // Text / textarea
    document.querySelectorAll('[data-oc-layer-text]').forEach(el => {
      const lid = parseInt(el.dataset.ocLayerText, 10);
      const counter = el.parentElement?.querySelector(`.oc-char-counter[data-oc-char-counter="${lid}"]`);
      const limit = parseInt(counter?.dataset.charLimit, 10) || this.charLimitForLayer(lid);
      if (limit > 0) {
        el.maxLength = limit;
      }
      const updateCounter = () => {
        if (!counter) {
          return;
        }
        const current = this.textLength(el.value);
        if (limit === 0 || current <= limit) {
          counter.style.display = 'none';
          return;
        }
        counter.textContent = `${current} / ${limit}`;
        counter.style.display = '';
      };
      updateCounter();
      el.addEventListener('input', async () => {
        el.setCustomValidity('');
        el.setAttribute('aria-invalid', 'false');
        el.classList.remove('oc-preflight-field-error');
        const cleaned = this.normaliseLayerTextValue(lid, el.value);
        if (cleaned !== el.value) {
          el.value = cleaned;
        }
        if (!this.inputs[lid]) {
          this.inputs[lid] = {};
        }
        this.inputs[lid].value = cleaned;
        this.syncLinkedLayerInput(lid, ['value']);
        updateCounter();
        await this.updateTextSizeSliderCap(lid);
        if (designGeneration !== this._designGeneration || stateSignal?.aborted) {
          return;
        }
        this.requestPreviewFocus();
        this.scheduleRedraw(this.areaIndexForLayer(lid));
        this.updateHiddenField();
      }, {
        signal: stateSignal
      });
    });

    // Spotify validation (invalid format / private playlist / unavailable).
    document.querySelectorAll('[data-oc-layer-spotify]').forEach(el => {
      const lid = parseInt(el.dataset.ocLayerSpotify, 10);
      if (!lid) {
        return;
      }
      el.addEventListener('input', () => {
        el.setCustomValidity('');
        el.setAttribute('aria-invalid', 'false');
        el.classList.remove('oc-preflight-field-error');
        this.invalidateSpotifyValidation(lid);
        if (!this.inputs[lid]) {
          this.inputs[lid] = {};
        }
        this.inputs[lid].value = el.value;
        this.inputs[lid].spotifyStatus = el.value.trim() ? 'pending' : '';
        this.inputs[lid].spotifyUri = this.extractSpotifyUri(el.value);
        this.syncLinkedLayerInput(lid, ['value', 'spotifyStatus', 'spotifyUri']);
        this.setSpotifyError(lid, '', el);
        this.requestPreviewFocus();
        this.scheduleRedraw(this.areaIndexForLayer(lid));
        this.updateHiddenField();
        this.spotifyValidateTimers[lid] = this.setStateTimeout(() => {
          delete this.spotifyValidateTimers[lid];
          this.validateSpotifyLayer(lid, el.value, el);
        }, 450);
      }, {
        signal: stateSignal
      });
      el.addEventListener('blur', () => {
        this.clearStateTimeout(this.spotifyValidateTimers[lid]);
        delete this.spotifyValidateTimers[lid];
        this.validateSpotifyLayer(lid, el.value, el);
      }, {
        signal: stateSignal
      });
    });

    // Help tooltips: tap to toggle on touch devices, close on outside tap.
    const closeHelpTooltips = () => {
      document.querySelectorAll('.oc-help-tooltip.oc-open, .oc-spotify-help.oc-open').forEach(help => {
        help.classList.remove('oc-open');
        help.querySelector('.oc-help-toggle, .oc-spotify-help-toggle')?.setAttribute('aria-expanded', 'false');
      });
    };
    document.querySelectorAll('.oc-help-toggle:not(.oc-spotify-modal-trigger), .oc-spotify-help-toggle').forEach(btn => {
      btn.addEventListener('click', e => {
        e.preventDefault();
        e.stopPropagation();
        const help = btn.closest('.oc-help-tooltip, .oc-spotify-help');
        if (!help) {
          return;
        }
        const willOpen = !help.classList.contains('oc-open');
        closeHelpTooltips();
        if (willOpen) {
          help.classList.add('oc-open');
          btn.setAttribute('aria-expanded', 'true');
        }
      }, {
        signal: stateSignal
      });
    });
    if (!this.helpTooltipDocumentClickBound) {
      this.helpTooltipDocumentClickBound = true;
      document.addEventListener('click', e => {
        if (!e.target.closest('.oc-help-tooltip, .oc-spotify-help')) {
          closeHelpTooltips();
        }
      });
    }
    this.setupSpotifyModal();

    // Font selects — also reflect the picked font in the closed select.
    const reflectFontOnSelect = el => {
      const opt = el.options[el.selectedIndex];
      const fam = opt?.style?.fontFamily || '';
      if (fam) {
        el.style.fontFamily = fam;
      }
    };
    document.querySelectorAll('[data-oc-layer-font]').forEach(el => {
      reflectFontOnSelect(el);
      this.updateFontCombobox(el);
      const lid = parseInt(el.dataset.ocLayerFont, 10);
      const selectedFontId = parseInt(el.value, 10) || 0;
      if (selectedFontId) {
        if (!this.inputs[lid]) {
          this.inputs[lid] = {};
        }
        this.inputs[lid].fontId = selectedFontId;
      }
      el.addEventListener('change', async () => {
        if (!this.inputs[lid]) {
          this.inputs[lid] = {};
        }
        this.inputs[lid].fontId = parseInt(el.value, 10);
        const font = this.fonts.find(f => f.id === this.inputs[lid].fontId);
        if (font) {
          try {
            await this.loadFont(font);
          } catch (err) {
            console.warn('[OC] Font load failed:', err);
          }
          if (designGeneration !== this._designGeneration || stateSignal?.aborted) {
            return;
          }
        }
        reflectFontOnSelect(el);
        this.updateFontCombobox(el);
        const preview = document.querySelector(`.oc-font-preview[data-oc-font-preview="${lid}"]`);
        if (preview && font) {
          preview.style.fontFamily = font.name;
        }
        await this.updateTextSizeSliderCap(lid);
        if (designGeneration !== this._designGeneration || stateSignal?.aborted) {
          return;
        }
        this.requestPreviewFocus();
        this.scheduleRedraw(this.areaIndexForLayer(lid));
        this.updateHiddenField();
      }, {
        signal: stateSignal
      });
    });

    // Font size
    document.querySelectorAll('[data-oc-layer-font-size]').forEach(el => {
      const lid = parseInt(el.dataset.ocLayerFontSize, 10);
      const valueEl = document.querySelector(`.oc-range-value[data-oc-range-value="${lid}"]`);
      if (!el.dataset.ocOriginalMax) {
        el.dataset.ocOriginalMax = el.max || '200';
      }
      const updateValue = () => {
        if (valueEl) {
          valueEl.textContent = el.value;
        }
      };
      updateValue();
      this.updateTextSizeSliderCap(lid);
      el.addEventListener('input', () => {
        if (!this.inputs[lid]) {
          this.inputs[lid] = {};
        }
        this.inputs[lid].fontSize = Math.max(1, parseInt(el.value, 10) || 1);
        updateValue();
        this.requestPreviewFocus();
        this.scheduleRedraw(this.areaIndexForLayer(lid));
        this.updateHiddenField();
      }, {
        signal: stateSignal
      });
    });

    // Large colour pickers
    document.querySelectorAll('[data-oc-colour-dialog-trigger]').forEach(trigger => {
      trigger.addEventListener('click', () => {
        const dialog = document.getElementById(trigger.dataset.ocColourDialogTrigger);
        if (!dialog) {
          return;
        }
        if (typeof dialog.showModal === 'function') {
          dialog.showModal();
        } else {
          dialog.setAttribute('open', '');
          dialog.classList.add('oc-dialog-fallback');
        }
        dialog.classList.add('is-visible');
        dialog.querySelector('.oc-colour-swatch.oc-selected')?.focus();
      }, {
        signal: stateSignal
      });
    });
    document.querySelectorAll('[data-oc-colour-dialog]').forEach(dialog => {
      dialog.querySelector('[data-oc-colour-dialog-close]')?.addEventListener('click', () => this.closeColourDialog(dialog), {
        signal: stateSignal
      });
      dialog.addEventListener('click', event => {
        if (event.target === dialog) {
          this.closeColourDialog(dialog);
        }
      }, {
        signal: stateSignal
      });
      dialog.addEventListener('close', () => dialog.classList.remove('is-visible'), {
        signal: stateSignal
      });
      dialog.addEventListener('keydown', event => {
        if (event.key === 'Escape' && !dialog.showModal) {
          this.closeColourDialog(dialog);
        }
      }, {
        signal: stateSignal
      });
    });

    // Colour swatches
    document.querySelectorAll('[data-oc-layer-swatch]').forEach(btn => {
      btn.addEventListener('click', () => {
        const lid = parseInt(btn.dataset.ocLayerSwatch, 10);
        if (!this.inputs[lid]) {
          this.inputs[lid] = {};
        }
        this.inputs[lid].colorHex = btn.dataset.hex;
        this.syncLinkedColourInput(lid);
        btn.closest('.oc-colour-swatches')?.querySelectorAll('.oc-colour-swatch').forEach(s => {
          const isSelected = s === btn;
          s.classList.toggle('oc-selected', isSelected);
          s.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
        });
        this.updateColourPickerTrigger(btn);
        this.closeColourDialog(btn.closest('[data-oc-colour-dialog]'));
        this.requestPreviewFocus();
        this.scheduleRedraw(this.areaIndexForLayer(lid));
        this.updateHiddenField();
      }, {
        signal: stateSignal
      });
    });

    // Free colour picker
    document.querySelectorAll('[data-oc-layer-color]').forEach(el => {
      const lid = parseInt(el.dataset.ocLayerColor, 10);
      el.addEventListener('input', () => {
        if (!this.inputs[lid]) {
          this.inputs[lid] = {};
        }
        this.inputs[lid].colorHex = el.value;
        this.syncLinkedColourInput(lid);
        this.requestPreviewFocus();
        this.scheduleRedraw(this.areaIndexForLayer(lid));
        this.updateHiddenField();
      }, {
        signal: stateSignal
      });
    });

    // Image filters
    document.querySelectorAll('[data-oc-layer-image-filter]').forEach(el => {
      const lid = parseInt(el.dataset.ocLayerImageFilter, 10);
      if (!this.inputs[lid]) {
        this.inputs[lid] = {};
      }
      this.inputs[lid].imageFilterId = parseInt(el.value, 10) || 0;
      el.addEventListener('change', async () => {
        if (!this.inputs[lid]) {
          this.inputs[lid] = {};
        }
        const filterId = parseInt(el.value, 10) || 0;
        el.disabled = true;
        try {
          await this.applyAiImageFilter(lid, filterId);
        } finally {
          if (designGeneration === this._designGeneration && !stateSignal?.aborted && el.isConnected) {
            el.disabled = this._controlLocks.size > 0;
          }
        }
        if (designGeneration !== this._designGeneration || stateSignal?.aborted) {
          return;
        }
        this.requestPreviewFocus();
        this.updateHiddenField();
      }, {
        signal: stateSignal
      });
    });

    // Clipart items
    document.querySelectorAll('[data-oc-layer-clipart]').forEach(btn => {
      btn.addEventListener('click', () => {
        const lid = parseInt(btn.dataset.ocLayerClipart, 10);
        if (!this.inputs[lid]) {
          this.inputs[lid] = {};
        }
        this.inputs[lid].clipartId = parseInt(btn.dataset.ocClipart, 10);
        this.inputs[lid].clipartUrl = btn.dataset.ocClipartUrl;
        this.inputs[lid].clipartRecolourable = btn.dataset.ocClipartRecolourable === '1';
        this.syncLinkedLayerInput(lid, ['clipartId', 'clipartUrl', 'clipartRecolourable']);
        btn.closest('.oc-clipart-grid')?.querySelectorAll('.oc-clipart-item').forEach(i => {
          const isSelected = i === btn;
          i.classList.toggle('oc-selected', isSelected);
          i.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
          i.setAttribute('aria-checked', isSelected ? 'true' : 'false');
        });
        this.requestPreviewFocus();
        this.scheduleRedraw(this.areaIndexForLayer(lid));
        this.updateHiddenField();
      }, {
        signal: stateSignal
      });
    });
    if (!this.carouselResizeBound) {
      this.carouselResizeBound = true;
      window.addEventListener('resize', () => {
        this.refreshDesignVariantCarousel();
        document.querySelectorAll('[data-oc-clipart-carousel]').forEach(carousel => {
          this.refreshClipartCarousel(parseInt(carousel.dataset.ocClipartCarousel, 10));
        });
      });
    }

    // Clipart search (debounced 200ms)
    document.querySelectorAll('[data-oc-clipart-search]').forEach(input => {
      const lid = parseInt(input.dataset.ocClipartSearch, 10);
      this.clipartSearchTerms[lid] = '';
      input.addEventListener('input', () => {
        this.clipartSearchTerms[lid] = input.value;
        this.clearStateTimeout(this.clipartSearchTimers[lid]);
        this.clipartSearchTimers[lid] = this.setStateTimeout(() => {
          delete this.clipartSearchTimers[lid];
          this.filterClipart(lid);
        }, 200);
      }, {
        signal: stateSignal
      });
    });

    // Clipart category filter
    document.querySelectorAll('[data-oc-clipart-category]').forEach(select => {
      const lid = parseInt(select.dataset.ocClipartCategory, 10);
      this.clipartCategoryFilters[lid] = '';
      select.addEventListener('change', () => {
        this.clipartCategoryFilters[lid] = select.value;
        this.filterClipart(lid);
      }, {
        signal: stateSignal
      });
    });

    // Dismiss resolution warning
    document.querySelectorAll('.oc-resolution-warning').forEach(warnEl => {
      warnEl.addEventListener('click', e => {
        if (e.target === warnEl && warnEl.classList.contains('oc-res-warning')) {
          warnEl.style.display = 'none';
        }
      }, {
        signal: stateSignal
      });
    });
  },
  getLayerById(layerId) {
    return this.layersById[layerId] || null;
  },
  ensureLayerControlHeader(layer, control, required) {
    const section = control?.closest('.oc-layer-section');
    const body = control?.closest('.oc-layer-body');
    if (!section || !body) {
      return;
    }
    const hasHeader = Array.from(section.children).some(child => child.classList.contains('oc-layer-header'));
    if (hasHeader) {
      return;
    }
    const header = document.createElement('div');
    header.className = 'oc-layer-header';
    const label = document.createElement('span');
    label.textContent = layer.label || 'Personalisation option';
    header.appendChild(label);
    if (required) {
      const requiredLabel = document.createElement('span');
      requiredLabel.className = 'oc-layer-required';
      requiredLabel.textContent = '* Required';
      header.appendChild(requiredLabel);
    }
    section.insertBefore(header, body);
  },
  setupControlAccessibility() {
    this.areas.forEach(area => {
      (area.layers || []).forEach(layer => {
        const required = Boolean(layer.required || layer.settings?.required);
        const label = layer.label || 'Personalisation option';
        if (['text', 'textarea'].includes(layer.type)) {
          const input = document.querySelector(`[data-oc-layer-text="${layer.id}"]`);
          if (input) {
            input.required = required;
            input.setAttribute('aria-required', required ? 'true' : 'false');
          }
          return;
        }
        if (['image', 'clipmask'].includes(layer.type)) {
          const zone = document.querySelector(`[data-oc-upload-zone="${layer.id}"]`);
          const fallback = document.querySelector(`[data-oc-default-image="${layer.id}"]`);
          this.ensureLayerControlHeader(layer, zone || fallback, required);
          if (zone) {
            zone.setAttribute('role', 'group');
            zone.setAttribute('aria-label', label);
            zone.setAttribute('aria-required', required ? 'true' : 'false');
          }
          return;
        }
        if (layer.type === 'clipart') {
          const grid = document.querySelector(`[data-oc-clipart-grid="${layer.id}"]`);
          this.ensureLayerControlHeader(layer, grid, required);
          grid?.setAttribute('role', 'radiogroup');
          grid?.setAttribute('aria-label', label);
          grid?.setAttribute('aria-required', required ? 'true' : 'false');
          document.querySelectorAll(`[data-oc-layer-clipart="${layer.id}"]`).forEach(option => {
            option.setAttribute('role', 'radio');
            option.setAttribute('aria-checked', option.classList.contains('oc-selected') ? 'true' : 'false');
          });
          const search = document.querySelector(`[data-oc-clipart-search="${layer.id}"]`);
          const category = document.querySelector(`[data-oc-clipart-category="${layer.id}"]`);
          search?.setAttribute('aria-label', `Search ${label}`);
          category?.setAttribute('aria-label', `Filter ${label} by category`);
          return;
        }
        if (layer.type === 'spotify') {
          const input = document.querySelector(`[data-oc-layer-spotify="${layer.id}"]`);
          if (input) {
            input.required = required;
            input.setAttribute('aria-required', required ? 'true' : 'false');
            input.setAttribute('aria-label', label);
          }
        }
      });
    });
  },
  applyUploadZoneAccessibility(zone, layer) {
    if (!zone || !layer) {
      return;
    }
    const required = Boolean(layer.required || layer.settings?.required);
    const label = layer.label || 'Upload artwork';
    zone.setAttribute('aria-label', label);
    zone.setAttribute('aria-required', required ? 'true' : 'false');
    zone.querySelectorAll('input[type="file"]').forEach(input => {
      input.setAttribute('aria-label', label);
      input.setAttribute('aria-required', required ? 'true' : 'false');
    });
  },
  seedLayerFontDefaults() {
    this.areas.forEach(area => {
      (area.layers || []).forEach(layer => {
        if (!['text', 'textarea'].includes(layer.type)) {
          return;
        }
        if (!this.inputs[layer.id]) {
          this.inputs[layer.id] = {};
        }
        const select = document.querySelector(`[data-oc-layer-font="${layer.id}"]`);
        if (select) {
          const allowedIds = Array.from(select.options).map(option => parseInt(option.value, 10) || 0).filter(Boolean);
          const configured = parseInt(this.inputs[layer.id].fontId, 10) || parseInt(layer.settings?.default_font_id, 10) || 0;
          const selected = allowedIds.includes(configured) ? configured : parseInt(select.value, 10) || allowedIds[0] || 0;
          this.inputs[layer.id].fontId = selected;
          if (selected) {
            select.value = String(selected);
          }
          return;
        }
        const activeIds = this.fonts.map(font => Number(font.id));
        const configured = parseInt(this.inputs[layer.id].fontId, 10) || parseInt(layer.settings?.default_font_id, 10) || 0;
        this.inputs[layer.id].fontId = activeIds.includes(configured) ? configured : activeIds[0] || 0;
      });
    });
  },
  seedLockedLayerDefaults() {
    this.areas.forEach(area => {
      (area.layers || []).forEach(layer => {
        if (!['text', 'textarea'].includes(layer.type)) {
          return;
        }
        if (!this.inputs[layer.id]) {
          this.inputs[layer.id] = {};
        }
        if (layer.locked) {
          this.inputs[layer.id].value = layer.settings?.default_text || '';
          this.clampLayerInputValue(layer.id);
        } else if (this.inputs[layer.id].value === undefined) {
          this.inputs[layer.id].value = '';
          this.clampLayerInputValue(layer.id);
        }
      });
    });
  },
  seedTemplateImageDefaults() {
    document.querySelectorAll('[data-oc-default-image]').forEach(el => {
      const layerId = parseInt(el.dataset.ocDefaultImage, 10);
      const url = el.dataset.ocDefaultImageUrl || '';
      if (!layerId || !url) {
        return;
      }
      if (!this.inputs[layerId]) {
        this.inputs[layerId] = {};
      }
      if (!this.inputs[layerId].attachmentUrl) {
        this.inputs[layerId].attachmentId = parseInt(el.dataset.ocDefaultImageId, 10) || 0;
        this.inputs[layerId].attachmentUrl = url;
        this.inputs[layerId].sourceAttachmentId = this.inputs[layerId].attachmentId;
        this.inputs[layerId].sourceAttachmentUrl = url;
        this.inputs[layerId].originalAttachmentUrl = url;
        this.inputs[layerId].sourceOriginalAttachmentUrl = url;
        const extension = url.match(/\.([a-z0-9]+)(?:[?#]|$)/i)?.[1];
        if (extension) {
          this.inputs[layerId].artworkFileType = extension.toLowerCase();
          this.inputs[layerId].sourceArtworkFileType = extension.toLowerCase();
        }
      }
    });
  },
  seedLinkedImageInputs() {
    const seeded = new Set();
    this.areas.forEach(area => {
      (area.layers || []).forEach(layer => {
        if (!['image', 'clipmask'].includes(layer.type)) {
          return;
        }
        const canonicalId = this.canonicalLinkedLayerId(layer.id);
        const members = this.linkedLayerMembers(layer.id);
        if (members.length < 2 || seeded.has(canonicalId)) {
          return;
        }
        seeded.add(canonicalId);
        const source = this.inputs[canonicalId] || {};
        members.forEach(layerId => {
          if (layerId === canonicalId) {
            return;
          }
          this.inputs[layerId] = this.inputs[layerId] || {};
          LINKED_IMAGE_INPUT_KEYS.forEach(key => {
            if (source[key] === undefined) {
              delete this.inputs[layerId][key];
            } else {
              this.inputs[layerId][key] = source[key];
            }
          });
        });
      });
    });
  },
  seedLinkedColourInputs() {
    const seeded = new Set();
    this.areas.forEach(area => {
      (area.layers || []).forEach(layer => {
        const group = String(layer.settings?.colour_link_group || '').trim();
        if (!group || seeded.has(group)) {
          return;
        }
        const members = this.linkedColourLayerMembers(layer.id);
        if (members.length < 2) {
          return;
        }
        seeded.add(group);
        const sourceId = members.find(layerId => this.getLayerById(layerId)?.settings?.allow_colour_change !== false) || members[0];
        this.syncLinkedColourInput(sourceId);
      });
    });
  },
  isProductionImageInput(input) {
    return Number(input?.attachmentId || 0) > 0;
  },
  imageLayerRequiresAttachment(layer) {
    return Boolean(layer?.locked || layer?.required || layer?.settings?.required || layer?.settings?.allow_image_change === false);
  },
  charLimitForLayer(layerId) {
    return Math.max(0, parseInt(this.getLayerById(layerId)?.settings?.char_limit, 10) || 0);
  },
  textLength(value) {
    return Array.from(String(value || '')).length;
  },
  truncateText(value, limit) {
    const text = String(value || '');
    return limit > 0 && this.textLength(text) > limit ? Array.from(text).slice(0, limit).join('') : text;
  },
  printMethodForLayer(layerId) {
    const area = this.areas[this.areaIndexForLayer(layerId)];
    return String(area?.printMethod || '');
  },
  isThreadOrEngravingLayer(layerId) {
    return ['engraving', 'embroidery'].includes(this.printMethodForLayer(layerId));
  },
  stripUnsupportedPrintEmoji(value) {
    return String(value || '').replace(/[\u{1F000}-\u{1FAFF}\u{2600}-\u{27BF}][\u{FE0E}\u{FE0F}]?/gu, '').replace(/[\u{1F3FB}-\u{1F3FF}\u{1F9B0}-\u{1F9B3}\u{200D}\u{FE0E}\u{FE0F}]/gu, '');
  },
  normaliseLayerTextValue(layerId, value) {
    let text = String(value || '');
    if (this.isThreadOrEngravingLayer(layerId)) {
      text = this.stripUnsupportedPrintEmoji(text);
    }
    const limit = this.charLimitForLayer(layerId);
    return limit > 0 ? this.truncateText(text, limit) : text;
  },
  clampLayerInputValue(layerId) {
    if (this.inputs[layerId]?.value !== undefined) {
      this.inputs[layerId].value = this.normaliseLayerTextValue(layerId, this.inputs[layerId].value);
    }
  },
  isLinkedLayerEligible(layer) {
    if (!layer || layer.visible === false || layer.locked) {
      return false;
    }
    if (['image', 'clipmask'].includes(layer.type)) {
      return layer.settings?.allow_image_change !== false;
    }
    if (layer.type === 'clipart') {
      return layer.settings?.allow_clipart_change !== false;
    }
    return true;
  },
  linkedLayerMembers(sourceLayerId) {
    const source = this.getLayerById(sourceLayerId);
    const group = String(source?.settings?.link_group || '').trim();
    if (!source || !group) {
      return this.isLinkedLayerEligible(source) ? [source.id] : [];
    }
    const members = [];
    this.areas.forEach(area => {
      (area.layers || []).forEach(layer => {
        if (layer.type !== source.type) {
          return;
        }
        if (String(layer.settings?.link_group || '').trim() === group && this.isLinkedLayerEligible(layer)) {
          members.push(layer.id);
        }
      });
    });
    return members;
  },
  linkedLayerIds(sourceLayerId) {
    return this.linkedLayerMembers(sourceLayerId).filter(layerId => Number(layerId) !== Number(sourceLayerId));
  },
  syncLinkedImageInput(sourceLayerId) {
    this.syncLinkedLayerInput(sourceLayerId, LINKED_IMAGE_INPUT_KEYS);
  },
  linkedColourLayerMembers(sourceLayerId) {
    const source = this.getLayerById(sourceLayerId);
    const group = String(source?.settings?.colour_link_group || '').trim();
    if (!source || !group) {
      return source ? [source.id] : [];
    }
    const members = [];
    this.areas.forEach(area => {
      (area.layers || []).forEach(layer => {
        const colourEnabled = layer.type !== 'image' || layer.settings?.enable_image_colour === true;
        if (['text', 'textarea', 'image', 'clipart', 'lineart'].includes(layer.type) && layer.visible !== false && !layer.locked && colourEnabled && String(layer.settings?.colour_link_group || '').trim() === group) {
          members.push(layer.id);
        }
      });
    });
    return members;
  },
  syncLinkedColourInput(sourceLayerId) {
    const sourceInput = this.inputs[sourceLayerId];
    if (!sourceInput?.colorHex) {
      return;
    }
    const targetAreaIndexes = new Set();
    this.linkedColourLayerMembers(sourceLayerId).forEach(layerId => {
      if (layerId === sourceLayerId) {
        return;
      }
      this.inputs[layerId] = this.inputs[layerId] || {};
      this.inputs[layerId].colorHex = sourceInput.colorHex;
      this.updateLinkedLayerControls(layerId, ['colorHex']);
      targetAreaIndexes.add(this.areaIndexForLayer(layerId));
    });
    targetAreaIndexes.forEach(areaIndex => this.scheduleRedraw(areaIndex));
  },
  canonicalLinkedLayerId(layerId) {
    const layer = this.getLayerById(layerId);
    const group = String(layer?.settings?.link_group || '').trim();
    if (!layer || !group) {
      return layerId;
    }
    return this.linkedLayerMembers(layerId)[0] || layerId;
  },
  syncLinkedLayerInput(sourceLayerId, keys) {
    const sourceInput = this.inputs[sourceLayerId];
    if (!sourceInput) {
      return;
    }
    const sourceLayer = this.getLayerById(sourceLayerId);
    if (!this.isLinkedLayerEligible(sourceLayer)) {
      return;
    }
    const targetAreaIndexes = new Set();
    this.linkedLayerIds(sourceLayerId).forEach(layerId => {
      if (!this.inputs[layerId]) {
        this.inputs[layerId] = {};
      }
      keys.forEach(key => {
        if (sourceInput[key] === undefined) {
          delete this.inputs[layerId][key];
        } else {
          this.inputs[layerId][key] = sourceInput[key];
        }
      });
      this.clampLayerInputValue(layerId);
      this.updateLinkedLayerControls(layerId, keys);
      targetAreaIndexes.add(this.areaIndexForLayer(layerId));
    });
    targetAreaIndexes.forEach(areaIndex => this.scheduleRedraw(areaIndex));
  },
  updateLinkedLayerControls(layerId, keys) {
    const input = this.inputs[layerId] || {};
    if (keys.includes('value')) {
      document.querySelectorAll(`[data-oc-layer-text="${layerId}"], [data-oc-layer-spotify="${layerId}"]`).forEach(el => {
        el.value = input.value || '';
      });
      this.updateTextSizeSliderCap(layerId);
      const counter = document.querySelector(`.oc-char-counter[data-oc-char-counter="${layerId}"]`);
      if (counter) {
        const limit = parseInt(counter.dataset.charLimit, 10) || this.charLimitForLayer(layerId);
        const current = this.textLength(input.value || '');
        counter.textContent = `${current} / ${limit}`;
        counter.style.display = limit > 0 && current > limit ? '' : 'none';
      }
    }
    if (keys.includes('colorHex')) {
      document.querySelectorAll(`[data-oc-layer-swatch="${layerId}"]`).forEach(swatch => {
        const isSelected = swatch.dataset.hex === input.colorHex;
        swatch.classList.toggle('oc-selected', isSelected);
        swatch.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
      });
      const colorEl = document.querySelector(`[data-oc-layer-color="${layerId}"]`);
      if (colorEl && input.colorHex) {
        colorEl.value = input.colorHex;
      }
    }
    if (keys.includes('clipartId')) {
      document.querySelectorAll(`[data-oc-layer-clipart="${layerId}"]`).forEach(item => {
        const isSelected = Number(item.dataset.ocClipart) === Number(input.clipartId);
        item.classList.toggle('oc-selected', isSelected);
        item.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
        item.setAttribute('aria-checked', isSelected ? 'true' : 'false');
      });
    }
    if (keys.includes('attachmentId') || keys.includes('attachmentUrl')) {
      document.querySelectorAll(`[data-oc-upload-zone="${layerId}"]`).forEach(zone => {
        this.setUploadZoneState(zone, this.isProductionImageInput(input) ? 'uploaded' : '');
      });
    }
    if (keys.includes('imageFilterId')) {
      document.querySelectorAll(`[data-oc-layer-image-filter="${layerId}"]`).forEach(select => {
        select.value = String(input.imageFilterId || 0);
      });
    }
  },
  // ── Form submit — upload preview then proceed ──────────────────────────────

  applyInputsToDOM({
    redraw = true
  } = {}) {
    for (const layerIdStr in this.inputs) {
      const layerId = parseInt(layerIdStr, 10);
      const inp = this.inputs[layerId];
      if (!inp) {
        continue;
      }
      const textEl = document.querySelector(`[data-oc-layer-text="${layerId}"]`);
      if (textEl && inp.value !== undefined) {
        this.clampLayerInputValue(layerId);
        textEl.value = inp.value;
      }
      const fontEl = document.querySelector(`[data-oc-layer-font="${layerId}"]`);
      if (fontEl && inp.fontId) {
        fontEl.value = inp.fontId;
        this.updateFontCombobox(fontEl);
      }
      const swatch = document.querySelector(`[data-oc-layer-swatch="${layerId}"][data-hex="${inp.colorHex}"]`);
      if (swatch) {
        swatch.closest('.oc-colour-swatches')?.querySelectorAll('.oc-colour-swatch').forEach(s => {
          const selected = s === swatch;
          s.classList.toggle('oc-selected', selected);
          s.setAttribute('aria-pressed', selected ? 'true' : 'false');
        });
        this.updateColourPickerTrigger(swatch);
      }
      const colorEl = document.querySelector(`[data-oc-layer-color="${layerId}"]`);
      if (colorEl && inp.colorHex) {
        colorEl.value = inp.colorHex;
      }
      const sizeEl = document.querySelector(`[data-oc-layer-font-size="${layerId}"]`);
      if (sizeEl && inp.fontSize) {
        sizeEl.value = inp.fontSize;
        document.querySelector(`.oc-range-value[data-oc-range-value="${layerId}"]`)?.replaceChildren(document.createTextNode(sizeEl.value));
      }
      const clipartBtn = document.querySelector(`[data-oc-layer-clipart="${layerId}"][data-oc-clipart="${inp.clipartId}"]`);
      if (clipartBtn) {
        clipartBtn.closest('.oc-clipart-grid')?.querySelectorAll('.oc-clipart-item').forEach(i => {
          const selected = i === clipartBtn;
          i.classList.toggle('oc-selected', selected);
          i.setAttribute('aria-pressed', selected ? 'true' : 'false');
          i.setAttribute('aria-checked', selected ? 'true' : 'false');
        });
      }
      const imageFilterEl = document.querySelector(`[data-oc-layer-image-filter="${layerId}"]`);
      if (imageFilterEl) {
        imageFilterEl.value = String(inp.imageFilterId || 0);
      }
      document.querySelectorAll(`[data-oc-upload-zone="${layerId}"]`).forEach(zone => {
        this.setUploadZoneState(zone, this.isProductionImageInput(inp) ? 'uploaded' : '');
      });
    }
    this.updateHiddenField();
    if (redraw) {
      this.areas.forEach((_, i) => this.redraw(i));
    }
  },
  syncInputsFromDOM() {
    this.areas.forEach(area => {
      (area.layers || []).forEach(layer => {
        const layerId = layer.id;
        if (!this.inputs[layerId]) {
          this.inputs[layerId] = {};
        }
        const input = this.inputs[layerId];
        const textEl = document.querySelector(`[data-oc-layer-text="${layerId}"]`);
        if (textEl) {
          const limit = this.charLimitForLayer(layerId);
          input.value = limit > 0 ? this.truncateText(textEl.value, limit) : textEl.value;
        }
        const spotifyEl = document.querySelector(`[data-oc-layer-spotify="${layerId}"]`);
        if (spotifyEl) {
          input.value = spotifyEl.value;
        }
        const fontEl = document.querySelector(`[data-oc-layer-font="${layerId}"]`);
        if (fontEl) {
          input.fontId = parseInt(fontEl.value, 10) || 0;
        }
        const sizeEl = document.querySelector(`[data-oc-layer-font-size="${layerId}"]`);
        if (sizeEl) {
          input.fontSize = Math.max(1, parseInt(sizeEl.value, 10) || 1);
        }
        const colorEl = document.querySelector(`[data-oc-layer-color="${layerId}"]`);
        if (colorEl) {
          input.colorHex = colorEl.value;
        } else {
          const selectedSwatch = document.querySelector(`[data-oc-layer-swatch="${layerId}"].oc-selected`);
          if (selectedSwatch?.dataset.hex) {
            input.colorHex = selectedSwatch.dataset.hex;
          }
        }
        const selectedClipart = document.querySelector(`[data-oc-layer-clipart="${layerId}"].oc-selected`);
        if (selectedClipart) {
          input.clipartId = parseInt(selectedClipart.dataset.ocClipart, 10) || 0;
          input.clipartUrl = selectedClipart.dataset.ocClipartUrl || '';
          input.clipartRecolourable = selectedClipart.dataset.ocClipartRecolourable === '1';
        }
        const imageFilterEl = document.querySelector(`[data-oc-layer-image-filter="${layerId}"]`);
        if (imageFilterEl) {
          input.imageFilterId = parseInt(imageFilterEl.value, 10) || 0;
        }
      });
    });
    this.updateHiddenField();
  },
  applyActiveAreaState(index) {
    this.activeArea = Math.max(0, Math.min(Math.max(0, this.areas.length - 1), Number(index) || 0));
    document.querySelectorAll('.oc-area-controls').forEach(el => {
      el.hidden = false;
      el.removeAttribute('aria-hidden');
    });
  },
  switchArea(index) {
    this.applyActiveAreaState(index);
    this.redraw(this.activeArea);
    document.querySelectorAll('.oc-area-controls[data-area-index="' + this.activeArea + '"] [data-oc-clipart-carousel]').forEach(carousel => {
      this.refreshClipartCarousel(parseInt(carousel.dataset.ocClipartCarousel, 10));
    });
  }
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (inputControlMethods);

/***/ },

/***/ "./src/frontend/customiser/preflight.js"
/*!**********************************************!*\
  !*** ./src/frontend/customiser/preflight.js ***!
  \**********************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* eslint-disable no-undef */

/**
 * Preflight validation and validation message rendering.
 */

const preflightMethods = {
  clearCustomValidity() {
    document.querySelectorAll('[data-oc-layer-text], [data-oc-layer-spotify]').forEach(el => {
      if (typeof el.setCustomValidity === 'function') {
        el.setCustomValidity('');
      }
      el.setAttribute('aria-invalid', 'false');
    });
  },
  getLayerInputEl(layer) {
    if (!layer?.id) {
      return null;
    }
    switch (layer.type) {
      case 'text':
      case 'textarea':
        return document.querySelector(`[data-oc-layer-text="${layer.id}"]`);
      case 'spotify':
        return document.querySelector(`[data-oc-layer-spotify="${layer.id}"]`);
      case 'image':
      case 'clipmask':
        return document.querySelector(`[data-oc-upload-zone="${layer.id}"]`);
      case 'clipart':
        return document.querySelector(`[data-oc-layer-clipart="${layer.id}"]`);
      default:
        return null;
    }
  },
  clearPreflightMessages() {
    if (this.preflightRoot) {
      this.preflightRoot.innerHTML = '';
      this.preflightRoot.hidden = true;
    }
    document.querySelectorAll('.oc-preflight-field-error').forEach(el => {
      el.classList.remove('oc-preflight-field-error');
    });
    this.clearCustomValidity();
  },
  renderPreflightMessages(errors = [], warnings = []) {
    if (!this.preflightRoot) {
      return;
    }
    if (!errors.length && !warnings.length) {
      this.clearPreflightMessages();
      return;
    }
    const box = document.createElement('div');
    box.className = 'oc-preflight-box';
    box.setAttribute('role', 'alert');
    box.setAttribute('aria-live', 'assertive');
    const appendTitle = text => {
      const title = document.createElement('p');
      title.className = 'oc-preflight-title';
      title.textContent = text;
      box.appendChild(title);
    };
    const appendList = (items, cls) => {
      if (!items.length) {
        return;
      }
      const list = document.createElement('ul');
      list.className = cls;
      items.forEach(msg => {
        const item = document.createElement('li');
        item.textContent = String(msg);
        list.appendChild(item);
      });
      box.appendChild(list);
    };
    this.preflightRoot.innerHTML = '';
    if (errors.length) {
      appendTitle('Please fix these issues before checkout:');
      appendList(errors, 'oc-preflight-errors');
    }
    if (warnings.length) {
      appendTitle('Quality warnings:');
      appendList(warnings, 'oc-preflight-warnings');
    }
    this.preflightRoot.appendChild(box);
    this.preflightRoot.hidden = false;
    this.preflightRoot.scrollIntoView({
      behavior: 'smooth',
      block: 'start'
    });
  },
  async getImageMeta(url) {
    if (!url) {
      return null;
    }
    const request = this.createStateAbortController(10000);
    return new Promise(resolve => {
      const img = new Image();
      let settled = false;
      const finish = result => {
        if (settled) {
          return;
        }
        settled = true;
        img.onload = null;
        img.onerror = null;
        request.release();
        resolve(result);
      };
      img.onload = () => finish({
        width: img.naturalWidth || 0,
        height: img.naturalHeight || 0
      });
      img.onerror = () => finish(null);
      request.controller.signal.addEventListener('abort', () => {
        img.src = '';
        finish(null);
      }, {
        once: true
      });
      img.src = url;
    });
  },
  async runPreflight() {
    this.clearPreflightMessages();
    const errors = [];
    const warnings = [];
    const spotifyValidated = new Set();
    for (const area of this.areas) {
      for (const layer of area.layers || []) {
        const isImageLayer = ['image', 'clipmask'].includes(layer.type);
        if (layer.locked && !isImageLayer) {
          continue;
        }
        const input = this.inputs[layer.id] || {};
        const settings = layer.settings || {};
        const required = Boolean(layer.required || settings.required);
        const label = layer.label || layer.type;
        const fieldEl = this.getLayerInputEl(layer);
        let value = '';
        switch (layer.type) {
          case 'text':
          case 'textarea':
            value = String(input.value || '').trim();
            if (required && !value) {
              errors.push(`${label} is required.`);
              fieldEl?.classList.add('oc-preflight-field-error');
              if (typeof fieldEl?.setCustomValidity === 'function') {
                fieldEl.setCustomValidity('This field is required.');
                fieldEl.setAttribute('aria-invalid', 'true');
              }
            }
            if (value) {
              const fontId = Number(input.fontId || 0);
              const fontSelect = document.querySelector(`[data-oc-layer-font="${layer.id}"]`);
              const permittedFontIds = fontSelect ? Array.from(fontSelect.options).map(option => Number(option.value)) : this.fonts.map(font => Number(font.id));
              if (!fontId || !permittedFontIds.includes(fontId)) {
                errors.push(`${label} needs an available font.`);
                fieldEl?.classList.add('oc-preflight-field-error');
              }
              const charLimit = parseInt(settings.char_limit, 10) || 0;
              if (charLimit > 0 && this.textLength(value) > charLimit) {
                errors.push(`${label} exceeds the ${charLimit} character limit.`);
                fieldEl?.classList.add('oc-preflight-field-error');
                if (typeof fieldEl?.setCustomValidity === 'function') {
                  fieldEl.setCustomValidity(`Maximum ${charLimit} characters.`);
                  fieldEl.setAttribute('aria-invalid', 'true');
                }
              }
            }
            break;
          case 'image':
          case 'clipmask':
            if (!this.isProductionImageInput(input) && (this.imageLayerRequiresAttachment(layer) || Boolean(input.attachmentUrl))) {
              errors.push(input.attachmentUrl ? `${label} has no production attachment. Please upload the image again.` : `${label} needs an uploaded image.`);
              fieldEl?.classList.add('oc-preflight-field-error');
            }
            if (this.isProductionImageInput(input) && input.attachmentUrl) {
              let imageMeta = input.imageMeta || null;
              if (!imageMeta) {
                imageMeta = await this.getTrackedImageMeta(input.attachmentUrl, layer.id);
                if (imageMeta && this.inputs[layer.id]) {
                  this.inputs[layer.id].imageMeta = imageMeta;
                }
              }
              const requiredPixels = this.resolutionForLayer(layer.id);
              if (!this.isVectorArtwork(input) && imageMeta && imageMeta.width > 0 && imageMeta.height > 0 && (imageMeta.width < requiredPixels.width || imageMeta.height < requiredPixels.height)) {
                warnings.push(`${label} may print soft (${imageMeta.width}x${imageMeta.height}px; recommended ${requiredPixels.width}x${requiredPixels.height}px).`);
              }
            }
            break;
          case 'clipart':
            if (required && !input.clipartId) {
              errors.push(`${label} requires a clipart selection.`);
              fieldEl?.classList.add('oc-preflight-field-error');
            }
            break;
          case 'lineart':
            value = String(input.colorHex || '').trim();
            if (required && !value) {
              errors.push(`${label} requires a line-art colour.`);
              fieldEl?.classList.add('oc-preflight-field-error');
              if (typeof fieldEl?.setCustomValidity === 'function') {
                fieldEl.setCustomValidity('Please choose a line-art colour.');
                fieldEl.setAttribute('aria-invalid', 'true');
              }
            }
            break;
          case 'spotify':
            value = String(input.value || '').trim();
            if (required && !value) {
              errors.push(`${label} requires a Spotify link.`);
              fieldEl?.classList.add('oc-preflight-field-error');
              if (typeof fieldEl?.setCustomValidity === 'function') {
                fieldEl.setCustomValidity('Please provide a Spotify link.');
                fieldEl.setAttribute('aria-invalid', 'true');
              }
              break;
            }
            const canonicalId = this.canonicalLinkedLayerId(layer.id);
            if (value && !spotifyValidated.has(canonicalId)) {
              const canonicalLayer = this.getLayerById(canonicalId);
              const canonicalInput = this.inputs[canonicalId] || input;
              await this.validateSpotifyLayer(canonicalId, canonicalInput.value || value, this.getLayerInputEl(canonicalLayer) || fieldEl);
              spotifyValidated.add(canonicalId);
            }
            if (value) {
              const status = String(this.inputs[layer.id]?.spotifyStatus || '');
              if (status !== 'ok') {
                errors.push(`${label} has an invalid or unavailable Spotify link.`);
                fieldEl?.classList.add('oc-preflight-field-error');
                if (typeof fieldEl?.setCustomValidity === 'function') {
                  fieldEl.setCustomValidity('Spotify link is invalid or unavailable.');
                  fieldEl.setAttribute('aria-invalid', 'true');
                }
              }
            }
            break;
        }
      }
    }
    return {
      errors,
      warnings,
      ok: errors.length === 0
    };
  },
  runImmediateBlockingPreflight() {
    this.clearPreflightMessages();
    const errors = [];
    for (const area of this.areas) {
      for (const layer of area.layers || []) {
        const isImageLayer = ['image', 'clipmask'].includes(layer.type);
        if (layer.locked && !isImageLayer) {
          continue;
        }
        const input = this.inputs[layer.id] || {};
        const settings = layer.settings || {};
        const label = layer.label || layer.type;
        const fieldEl = this.getLayerInputEl(layer);
        const hasUrlOnlyImage = isImageLayer && Boolean(input.attachmentUrl) && !this.isProductionImageInput(input);
        if (!layer.required && !settings.required && !hasUrlOnlyImage && !(isImageLayer && this.imageLayerRequiresAttachment(layer))) {
          continue;
        }
        let filled = true;
        switch (layer.type) {
          case 'text':
          case 'textarea':
          case 'spotify':
            filled = String(input.value || '').trim() !== '';
            break;
          case 'image':
          case 'clipmask':
            filled = this.isProductionImageInput(input);
            break;
          case 'clipart':
            filled = Boolean(input.clipartId);
            break;
          default:
            filled = true;
        }
        if (!filled) {
          errors.push(`${label} is required.`);
          fieldEl?.classList.add('oc-preflight-field-error');
          if (typeof fieldEl?.setCustomValidity === 'function') {
            fieldEl.setCustomValidity('This field is required.');
            fieldEl.setAttribute('aria-invalid', 'true');
          }
        }
      }
    }
    return {
      errors,
      warnings: [],
      ok: errors.length === 0
    };
  }
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (preflightMethods);

/***/ },

/***/ "./src/frontend/customiser/spotify.js"
/*!********************************************!*\
  !*** ./src/frontend/customiser/spotify.js ***!
  \********************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/**
 * Spotify modal, URI parsing, code URL building, and validation.
 */

/* eslint-disable no-console */

const SPOTIFY_LINK_SYNC_KEYS = ['value', 'spotifyStatus', 'spotifyUri'];
const spotifyMethods = {
  invalidateSpotifyValidation(layerId) {
    this.clearStateTimeout(this.spotifyValidateTimers[layerId]);
    delete this.spotifyValidateTimers[layerId];
    this.spotifyValidateTokens[layerId] = (this.spotifyValidateTokens[layerId] || 0) + 1;
    this.spotifyAbortControllers[layerId]?.abort();
    delete this.spotifyAbortControllers[layerId];
    delete this.spotifyValidationPromises[layerId];
  },
  extractSpotifyUri(inputValue) {
    const raw = String(inputValue || '').trim();
    if (!raw) {
      return '';
    }
    const uriMatch = raw.match(/^spotify:(track|album|artist|playlist|episode|show):([A-Za-z0-9]{1,128})$/i);
    if (uriMatch) {
      return `spotify:${uriMatch[1].toLowerCase()}:${uriMatch[2]}`;
    }
    let parsed;
    try {
      parsed = new URL(raw);
    } catch {
      return '';
    }
    const host = parsed.hostname.toLowerCase();
    if (host !== 'open.spotify.com' && host !== 'play.spotify.com') {
      return '';
    }
    const parts = parsed.pathname.split('/').filter(Boolean).filter(part => !/^(?:intl-[a-z]{2}(?:-[a-z]{2})?|embed)$/i.test(part));
    if (!parts.length) {
      return '';
    }
    const validTypes = ['track', 'album', 'artist', 'playlist', 'episode', 'show'];
    const typeIndex = parts.findIndex(part => validTypes.includes(part.toLowerCase()));
    if (typeIndex < 0 || !parts[typeIndex + 1]) {
      return '';
    }
    const id = parts[typeIndex + 1];
    if (!/^[A-Za-z0-9]{1,128}$/.test(id)) {
      return '';
    }
    const type = parts[typeIndex].toLowerCase();
    return `spotify:${type}:${id}`;
  },
  buildSpotifyCodeUrl(inputValue, isEngraving, engravingPalette = null) {
    const spotifyUri = this.extractSpotifyUri(inputValue);
    if (!spotifyUri) {
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
    return `https://scannables.scdn.co/uri/plain/${format}/${bgHex}/${bar}/${size}/${spotifyUri}`;
  },
  setupSpotifyModal() {
    const dialog = document.getElementById('oc-spotify-share-dialog');
    if (!dialog) {
      return;
    }
    const stateSignal = this._panelListenerController?.signal;
    dialog.setAttribute('role', 'dialog');
    dialog.setAttribute('aria-modal', 'true');
    document.querySelectorAll('.oc-spotify-modal-trigger').forEach(trigger => {
      trigger.addEventListener('click', event => {
        event.preventDefault();
        event.stopPropagation();
        this.openSpotifyModal();
      }, {
        signal: stateSignal
      });
    });
    dialog.querySelectorAll('[data-oc-spotify-modal-close]').forEach(closeBtn => {
      closeBtn.addEventListener('click', () => this.closeSpotifyModal(), {
        signal: stateSignal
      });
    });
    dialog.addEventListener('click', event => {
      if (dialog.classList.contains('oc-dialog-fallback')) {
        if (!dialog.querySelector('.oc-sp-modal-card')?.contains(event.target)) {
          this.closeSpotifyModal();
        }
        return;
      }
      const rect = dialog.getBoundingClientRect();
      const inDialog = rect.top <= event.clientY && event.clientY <= rect.top + rect.height && rect.left <= event.clientX && event.clientX <= rect.left + rect.width;
      if (!inDialog) {
        this.closeSpotifyModal();
      }
    }, {
      signal: stateSignal
    });
    dialog.addEventListener('keydown', event => {
      if (event.key === 'Escape' && !dialog.showModal) {
        event.preventDefault();
        this.closeSpotifyModal();
      }
    }, {
      signal: stateSignal
    });
    dialog.addEventListener('close', () => {
      dialog.classList.remove('is-visible');
      document.body.style.overflow = '';
    }, {
      signal: stateSignal
    });
  },
  openSpotifyModal() {
    const dialog = document.getElementById('oc-spotify-share-dialog');
    if (!dialog || dialog.hasAttribute('open')) {
      return;
    }
    this.clearStateTimeout(this.spotifyModalCloseTimer);
    if (typeof dialog.showModal === 'function') {
      dialog.showModal();
    } else {
      dialog.hidden = false;
      dialog.setAttribute('open', '');
      dialog.classList.add('oc-dialog-fallback');
    }
    this.requestStateAnimationFrame(() => {
      this.requestStateAnimationFrame(() => {
        dialog.classList.add('is-visible');
      });
    });
    document.body.style.overflow = 'hidden';
    dialog.querySelector('[data-oc-spotify-modal-close]')?.focus?.();
  },
  closeSpotifyModal() {
    const dialog = document.getElementById('oc-spotify-share-dialog');
    if (!dialog || !dialog.hasAttribute('open')) {
      return;
    }
    dialog.classList.remove('is-visible');
    this.clearStateTimeout(this.spotifyModalCloseTimer);
    this.spotifyModalCloseTimer = this.setStateTimeout(() => {
      if (typeof dialog.close === 'function' && dialog.open) {
        dialog.close();
      } else {
        dialog.removeAttribute('open');
        dialog.hidden = true;
      }
      document.body.style.overflow = '';
    }, 300);
  },
  dismissSpotifyModal() {
    this.clearStateTimeout(this.spotifyModalCloseTimer);
    this.spotifyModalCloseTimer = null;
    const dialog = document.getElementById('oc-spotify-share-dialog');
    if (dialog) {
      dialog.classList.remove('is-visible');
      if (typeof dialog.close === 'function' && dialog.open) {
        dialog.close();
      } else {
        dialog.removeAttribute('open');
        dialog.hidden = true;
      }
    }
    document.body.style.overflow = '';
  },
  setSpotifyError(layerId, message, inputEl = null) {
    const msg = String(message || '');
    const el = document.querySelector(`[data-oc-spotify-error="${layerId}"]`);
    if (el) {
      el.textContent = msg;
      el.style.display = msg ? '' : 'none';
    }
    if (inputEl) {
      inputEl.setCustomValidity(msg);
      inputEl.setAttribute('aria-invalid', msg ? 'true' : 'false');
    }
  },
  clearSpotifyLayerStatus(layerId, inputEl = null) {
    this.inputs[layerId].spotifyStatus = '';
    this.inputs[layerId].spotifyUri = '';
    this.syncLinkedLayerInput(layerId, SPOTIFY_LINK_SYNC_KEYS);
    this.setSpotifyError(layerId, '', inputEl);
    this.scheduleRedraw(this.areaIndexForLayer(layerId));
    this.updateHiddenField();
  },
  setSpotifyValidationResult(layerId, status, uri, message, inputEl = null) {
    this.inputs[layerId].spotifyStatus = status;
    this.inputs[layerId].spotifyUri = uri;
    this.syncLinkedLayerInput(layerId, SPOTIFY_LINK_SYNC_KEYS);
    this.setSpotifyError(layerId, message, inputEl);
    this.scheduleRedraw(this.areaIndexForLayer(layerId));
    this.updateHiddenField();
  },
  validateSpotifyLayer(layerId, rawValue, inputEl = null) {
    const value = String(rawValue || '').trim();
    if (!this.inputs[layerId]) {
      this.inputs[layerId] = {};
    }
    this.clearStateTimeout(this.spotifyValidateTimers[layerId]);
    delete this.spotifyValidateTimers[layerId];
    const existing = this.spotifyValidationPromises[layerId];
    if (existing?.value === value) {
      return existing.promise;
    }
    this.invalidateSpotifyValidation(layerId);
    const token = this.spotifyValidateTokens[layerId];
    const designGeneration = this._designGeneration;
    const input = this.inputs[layerId];
    input.value = value;
    const localUri = this.extractSpotifyUri(value);
    const isCurrent = () => this.spotifyValidateTokens[layerId] === token && this._designGeneration === designGeneration && this.inputs[layerId] === input && String(input.value || '').trim() === value && (!inputEl || String(inputEl.value || '').trim() === value);
    const promise = (async () => {
      if (!value) {
        this.clearSpotifyLayerStatus(layerId, inputEl);
        return true;
      }
      if (!localUri) {
        this.setSpotifyValidationResult(layerId, 'invalid_format', '', 'Invalid Spotify link format.', inputEl);
        return false;
      }
      this.setSpotifyValidationResult(layerId, 'pending', localUri, '', inputEl);
      if (!this.data.validateSpotifyUrl) {
        this.setSpotifyValidationResult(layerId, 'ok', localUri, '', inputEl);
        return true;
      }
      const request = this.createStateAbortController(12000);
      const controller = request.controller;
      this.spotifyAbortControllers[layerId] = controller;
      try {
        const res = await fetch(this.data.validateSpotifyUrl, {
          method: 'POST',
          headers: this.restHeaders({
            'Content-Type': 'application/json'
          }),
          body: JSON.stringify({
            url: localUri
          }),
          signal: controller.signal
        });
        const isJson = res.headers.get('content-type')?.includes('application/json');
        let json = null;
        let text = '';
        if (isJson) {
          try {
            json = await res.json();
          } catch (err) {
            console.warn('[OC] Spotify validation JSON parse failed:', err);
          }
        } else {
          text = await res.text();
        }
        if (!isCurrent()) {
          return false;
        }
        if (!res.ok) {
          const statusReason = json?.code === 'rate_limited' || res.status === 429 ? 'rate_limited' : 'unreachable';
          const statusMessage = json?.message || text || 'Could not validate Spotify right now. Please try again.';
          this.setSpotifyValidationResult(layerId, statusReason, '', statusMessage, inputEl);
          return false;
        }
        if (!json) {
          this.setSpotifyValidationResult(layerId, 'unreachable', '', 'Could not validate Spotify right now. Please try again.', inputEl);
          return false;
        }
        const canonicalUri = this.extractSpotifyUri(json?.spotifyUri || localUri);
        if (json?.valid === true && canonicalUri) {
          this.setSpotifyValidationResult(layerId, 'ok', canonicalUri, '', inputEl);
          return true;
        }
        this.setSpotifyValidationResult(layerId, json?.reason || 'invalid_or_unavailable', '', json?.message || 'Spotify link is invalid or unavailable.', inputEl);
        return false;
      } catch {
        if (!isCurrent()) {
          return false;
        }
        this.setSpotifyValidationResult(layerId, 'unreachable', '', request.timedOut() ? 'Spotify validation timed out. Please try again.' : 'Could not validate Spotify right now. Please try again.', inputEl);
        return false;
      } finally {
        request.release();
        if (this.spotifyAbortControllers[layerId] === controller) {
          delete this.spotifyAbortControllers[layerId];
        }
      }
    })();
    this.spotifyValidationPromises[layerId] = {
      value,
      promise
    };
    return promise;
  }
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (spotifyMethods);

/***/ },

/***/ "./src/frontend/customiser/uploads.js"
/*!********************************************!*\
  !*** ./src/frontend/customiser/uploads.js ***!
  \********************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _shared_render_math__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../../shared/render-math */ "./src/shared/render-math.js");
/**
 * Uppy upload zone setup and upload UI helpers.
 */

/* eslint-disable no-console */


const SERVER_UPLOAD_FORMATS = ['jpg', 'jpeg', 'png', 'svg', 'pdf', 'eps', 'webp'];
const uploadMethods = {
  beginArtworkOperation(type, layerId = 0) {
    const operation = {
      type,
      layerId,
      settled: false
    };
    this._artworkOperations.add(operation);
    this.artworkPendingCount += 1;
    return operation;
  },
  finishArtworkOperation(operation) {
    if (!operation || operation.settled) {
      return;
    }
    operation.settled = true;
    this._artworkOperations.delete(operation);
    this.artworkPendingCount = Math.max(0, this.artworkPendingCount - 1);
  },
  cancelArtworkOperations() {
    Array.from(this._artworkOperations).forEach(operation => this.finishArtworkOperation(operation));
  },
  async getTrackedImageMeta(url, layerId = 0) {
    // The handle is deliberately consumed in finally across every return path.
    // eslint-disable-next-line @wordpress/no-unused-vars-before-return
    const operation = this.beginArtworkOperation('metadata', layerId);
    try {
      return await this.getImageMeta(url);
    } finally {
      this.finishArtworkOperation(operation);
    }
  },
  isVectorArtwork(input) {
    const type = String(input?.sourceArtworkFileType || input?.artworkFileType || '').toLowerCase().replace(/^\./, '');
    if (['svg', 'pdf', 'eps'].includes(type)) {
      return true;
    }
    const originalUrl = String(input?.sourceOriginalAttachmentUrl || input?.originalAttachmentUrl || input?.sourceAttachmentUrl || input?.attachmentUrl || '');
    return /\.(?:svg|pdf|eps)(?:[?#]|$)/i.test(originalUrl);
  },
  resolutionForLayer(layerId) {
    const layer = this.getLayerById(layerId);
    const area = this.areas[this.areaIndexForLayer(layerId)];
    return (0,_shared_render_math__WEBPACK_IMPORTED_MODULE_0__.rasterDimensionsForLayer)(layer, this.areaBounds(area));
  },
  cancelAiFilterForLayer(layerId) {
    this.aiFilterGenerations[layerId] = (this.aiFilterGenerations[layerId] || 0) + 1;
    this.aiFilterAbortControllers[layerId]?.abort();
    delete this.aiFilterAbortControllers[layerId];
  },
  async applyInitialAiFilters() {
    if (this._variationSwitchPending) {
      return;
    }
    const designGeneration = this._designGeneration;
    const started = new Set();
    for (const [layerId, input] of Object.entries(this.inputs)) {
      const canonicalId = this.canonicalLinkedLayerId(Number(layerId));
      if (canonicalId !== Number(layerId) || started.has(canonicalId) || this.aiFilterAbortControllers[canonicalId]) {
        continue;
      }
      started.add(canonicalId);
      const filterId = Number(input?.imageFilterId || 0);
      const sourceId = Number(input?.sourceAttachmentId || 0);
      const attachmentId = Number(input?.attachmentId || 0);
      const filter = (this.data?.imageFilters || []).find(item => Number(item.id) === filterId);
      if (filter?.isAi && sourceId && attachmentId === sourceId) {
        await this.applyAiImageFilter(canonicalId, filterId);
        if (designGeneration !== this._designGeneration) {
          return;
        }
      }
    }
  },
  async setupUploadZones() {
    const designGeneration = this._designGeneration;
    const zoneEls = Array.from(document.querySelectorAll('[data-oc-upload-zone]'));
    if (!zoneEls.length) {
      return;
    }
    let modules;
    try {
      modules = await Promise.all([Promise.all(/*! import() */[__webpack_require__.e("vendors-node_modules_uppy_core_lib_UIPlugin_js"), __webpack_require__.e("vendors-node_modules_uppy_core_lib_index_js")]).then(__webpack_require__.bind(__webpack_require__, /*! @uppy/core */ "./node_modules/@uppy/core/lib/index.js")), Promise.all(/*! import() */[__webpack_require__.e("vendors-node_modules_uppy_core_lib_UIPlugin_js"), __webpack_require__.e("vendors-node_modules_uppy_drag-drop_lib_index_js")]).then(__webpack_require__.bind(__webpack_require__, /*! @uppy/drag-drop */ "./node_modules/@uppy/drag-drop/lib/index.js")), __webpack_require__.e(/*! import() */ "vendors-node_modules_uppy_xhr-upload_lib_index_js").then(__webpack_require__.bind(__webpack_require__, /*! @uppy/xhr-upload */ "./node_modules/@uppy/xhr-upload/lib/index.js"))]);
    } catch (error) {
      if (designGeneration === this._designGeneration) {
        zoneEls.forEach(zoneEl => this.showUploadImportFailure(zoneEl, error));
      }
      return;
    }
    const [{
      default: Uppy
    }, {
      default: DragDrop
    }, {
      default: XHRUpload
    }] = modules;
    if (designGeneration !== this._designGeneration) {
      return;
    }
    zoneEls.forEach(zoneEl => {
      if (!zoneEl.isConnected || zoneEl.dataset.ocUppyReady === '1') {
        return;
      }
      const lid = parseInt(zoneEl.dataset.ocUploadZone, 10);
      if (!lid) {
        return;
      }
      const uploadUrl = this.data?.uploadUrl || '';
      if (!uploadUrl) {
        this.showUploadError(zoneEl, 'Uploads are unavailable right now.');
        return;
      }

      // Find the layer's per-layer settings; fall back to global defaults.
      let layer = null;
      for (const area of this.areas) {
        layer = (area.layers || []).find(l => l.id === lid);
        if (layer) {
          break;
        }
      }
      if (!layer) {
        console.warn('[OC] Upload zone has no matching layer:', lid);
        return;
      }
      const layerFormats = Array.isArray(layer?.settings?.formats) ? layer.settings.formats : [];
      const globalFormats = Array.isArray(this.data.allowedFormats) ? this.data.allowedFormats : [];
      const normalisedGlobalFormats = globalFormats.map(f => String(f).toLowerCase().replace(/^\./, '')).filter(ext => SERVER_UPLOAD_FORMATS.includes(ext));
      const effective = layerFormats.length ? layerFormats.map(f => String(f).toLowerCase().replace(/^\./, '')).filter(ext => normalisedGlobalFormats.includes(ext)) : normalisedGlobalFormats;
      const allowedExt = [...new Set(effective)].map(ext => `.${ext}`);
      if (!allowedExt.length) {
        this.showUploadError(zoneEl, 'This layer does not allow artwork file formats.');
        return;
      }
      const layerMaxMb = parseInt(layer?.settings?.max_size_mb, 10);
      const globalMaxMb = parseInt(this.data.maxUploadSizeMb, 10);
      let maxMb = globalMaxMb > 0 ? globalMaxMb : 10;
      if (layerMaxMb > 0) {
        maxMb = Math.min(maxMb, layerMaxMb);
      }
      let activeGeneration = this.uploadGenerations[lid] || 0;
      const fileGenerations = new Map();
      const fileOperations = new Map();
      const finishFileTransfer = fileId => {
        const operation = fileOperations.get(fileId);
        this.finishArtworkOperation(operation);
        fileOperations.delete(fileId);
      };
      const uppy = new Uppy({
        autoProceed: true,
        onBeforeFileAdded: () => {
          activeGeneration += 1;
          this.uploadGenerations[lid] = activeGeneration;
          this.cancelAiFilterForLayer(lid);
          uppy.getFiles().forEach(existingFile => uppy.removeFile(existingFile.id));
          return true;
        },
        restrictions: {
          maxNumberOfFiles: 1,
          maxFileSize: maxMb * 1024 * 1024,
          allowedFileTypes: allowedExt
        }
      });
      uppy.on('file-added', file => {
        fileGenerations.set(file.id, activeGeneration);
        fileOperations.set(file.id, this.beginArtworkOperation('upload', lid));
        this.setUploadProgress(zoneEl, 0, 'Starting upload...');
        this.showUploadError(zoneEl, '');
      });
      uppy.on('file-removed', file => finishFileTransfer(file?.id));
      uppy.on('cancel-all', () => {
        Array.from(fileOperations.keys()).forEach(finishFileTransfer);
      });
      uppy.use(DragDrop, {
        target: zoneEl,
        note: 'We accept ' + (allowedExt.length ? allowedExt.map(e => e.replace('.', '').toUpperCase()).join(', ') : 'JPG, PNG, PDF, EPS') + ' and other common image types.',
        locale: {
          strings: {
            dropHereOr: '%{browse}',
            browse: 'Tap / click here to upload your image'
          }
        }
      });
      uppy.use(XHRUpload, {
        endpoint: this.uploadEndpoint(uploadUrl, lid),
        formData: true,
        fieldName: 'artwork',
        headers: this.restHeaders()
      });
      zoneEl.dataset.ocUppyReady = '1';
      this.uppyInstances.add(uppy);
      this.applyUploadZoneAccessibility(zoneEl, layer);
      this.requestStateAnimationFrame(() => {
        if (zoneEl.isConnected) {
          this.applyUploadZoneAccessibility(zoneEl, layer);
        }
      });
      if (this.isProductionImageInput(this.inputs[lid])) {
        this.setUploadZoneState(zoneEl, 'uploaded');
      }
      uppy.on('upload-progress', (file, progress) => {
        if (fileGenerations.get(file?.id) !== this.uploadGenerations[lid]) {
          return;
        }
        const percent = progress?.bytesTotal ? Math.round(progress.bytesUploaded / progress.bytesTotal * 100) : 0;
        this.setUploadProgress(zoneEl, percent, `Uploading ${percent}%`);
      });
      uppy.on('upload-success', async (file, res) => {
        const generation = fileGenerations.get(file?.id);
        finishFileTransfer(file?.id);
        if (generation !== this.uploadGenerations[lid]) {
          return;
        }
        this.setUploadProgress(zoneEl, 100, '');
        if (!res?.body) {
          this.setUploadZoneState(zoneEl, this.isProductionImageInput(this.inputs[lid]) ? 'uploaded-error' : 'error');
          this.showUploadError(zoneEl, 'Upload succeeded but server returned no data.');
          return;
        }
        const attachmentId = Number(res.body.attachment_id || 0);
        const attachmentUrl = String(res.body.preview_url || '');
        if (!attachmentId || !attachmentUrl) {
          this.setUploadZoneState(zoneEl, this.isProductionImageInput(this.inputs[lid]) ? 'uploaded-error' : 'error');
          this.showUploadError(zoneEl, 'Server did not return usable artwork data.');
          return;
        }
        const artworkFileType = String(res.body.file_type || file?.extension || '').toLowerCase();
        const candidate = {
          ...(this.inputs[lid] || {}),
          attachmentId,
          attachmentUrl,
          sourceAttachmentId: attachmentId,
          sourceAttachmentUrl: attachmentUrl,
          originalAttachmentUrl: String(res.body.original_url || attachmentUrl),
          sourceOriginalAttachmentUrl: String(res.body.original_url || attachmentUrl),
          artworkFileType,
          sourceArtworkFileType: artworkFileType,
          previewAttachmentId: Number(res.body.preview_attachment_id || 0),
          sourcePreviewAttachmentId: Number(res.body.preview_attachment_id || 0),
          imageMeta: null,
          sourceImageMeta: null
        };
        const meta = await this.getTrackedImageMeta(attachmentUrl, lid);
        if (generation !== this.uploadGenerations[lid]) {
          return;
        }
        candidate.imageMeta = meta;
        candidate.sourceImageMeta = meta;
        const threshold = this.resolutionForLayer(lid);
        const isVector = this.isVectorArtwork(candidate);
        const belowThreshold = !isVector && meta && (meta.width < threshold.width || meta.height < threshold.height);
        const belowHalf = belowThreshold && (meta.width < threshold.width * 0.5 || meta.height < threshold.height * 0.5);
        const warnEl = document.querySelector(`.oc-resolution-warning[data-oc-resolution-warning="${lid}"]`);
        if (belowHalf) {
          if (warnEl) {
            warnEl.className = 'oc-resolution-warning oc-res-error';
            warnEl.textContent = `This image is too low resolution for quality printing. Minimum required: ${threshold.width} x ${threshold.height} pixels.`;
            warnEl.style.display = '';
          }
          this.setUploadZoneState(zoneEl, this.isProductionImageInput(this.inputs[lid]) ? 'uploaded-error' : 'error');
          this.showUploadError(zoneEl, 'Image resolution too low. Please upload a higher resolution image.');
          return;
        }
        if (warnEl) {
          if (belowThreshold) {
            warnEl.className = 'oc-resolution-warning oc-res-warning';
            warnEl.textContent = `This image may not print clearly at full size. Recommended minimum: ${threshold.width} x ${threshold.height} pixels.`;
            warnEl.style.display = '';
          } else {
            warnEl.style.display = 'none';
          }
        }
        this.inputs[lid] = candidate;
        this.syncLinkedImageInput(lid);
        const filterApplied = await this.applyAiImageFilter(lid, candidate.imageFilterId || 0, zoneEl);
        if (generation !== this.uploadGenerations[lid]) {
          return;
        }
        this.setUploadZoneState(zoneEl, filterApplied ? 'uploaded' : 'uploaded-error');
        this.syncLinkedImageInput(lid);
        this.requestPreviewFocus();
        this.scheduleRedraw(this.areaIndexForLayer(lid));
        this.updateHiddenField();
        if (filterApplied) {
          this.showUploadError(zoneEl, '');
        }
      });
      uppy.on('upload-error', (file, error, response) => {
        finishFileTransfer(file?.id);
        if (fileGenerations.get(file?.id) !== this.uploadGenerations[lid]) {
          return;
        }
        let responseBody = response?.body || null;
        if (!responseBody && response?.responseText) {
          try {
            responseBody = JSON.parse(response.responseText);
          } catch {
            responseBody = {
              message: response.responseText
            };
          }
        }
        const msg = responseBody?.message || error?.message || 'Upload failed.';
        console.warn('[OC] Upload error:', msg, response);
        this.setUploadZoneState(zoneEl, this.isProductionImageInput(this.inputs[lid]) ? 'uploaded-error' : 'error');
        this.setUploadProgress(zoneEl, 0, '');
        this.showUploadError(zoneEl, msg);
      });
      uppy.on('restriction-failed', (file, error) => {
        finishFileTransfer(file?.id);
        this.setUploadZoneState(zoneEl, this.isProductionImageInput(this.inputs[lid]) ? 'uploaded-error' : 'error');
        this.setUploadProgress(zoneEl, 0, '');
        this.showUploadError(zoneEl, error?.message || 'File not allowed.');
      });
    });
  },
  async applyAiImageFilter(layerId, filterId, zoneEl = null) {
    const input = this.inputs[layerId];
    if (!input) {
      return false;
    }
    filterId = Number(filterId || 0);
    this.cancelAiFilterForLayer(layerId);
    const generation = this.aiFilterGenerations[layerId];
    const designGeneration = this._designGeneration;
    input.imageFilterId = filterId;
    const sourceId = Number(input.sourceAttachmentId || input.attachmentId || 0);
    const sourceUrl = input.sourceAttachmentUrl || input.attachmentUrl || '';
    const isCurrent = () => generation === this.aiFilterGenerations[layerId] && designGeneration === this._designGeneration && this.inputs[layerId] === input && Number(input.imageFilterId || 0) === filterId && this._customisationActive;
    if (!filterId) {
      this.restoreSourceArtwork(input, sourceId, sourceUrl);
      delete this.aiFilterErrors[layerId];
      this.syncLinkedImageInput(layerId);
      this.scheduleRedraw(this.areaIndexForLayer(layerId));
      this.updateHiddenField();
      return true;
    }
    const filter = (this.data?.imageFilters || []).find(item => Number(item.id) === Number(filterId));
    if (!filter?.isAi) {
      this.restoreSourceArtwork(input, sourceId, sourceUrl);
      delete this.aiFilterErrors[layerId];
      this.syncLinkedImageInput(layerId);
      this.scheduleRedraw(this.areaIndexForLayer(layerId));
      this.updateHiddenField();
      return true;
    }
    if (!sourceId || !sourceUrl || !this.data?.applyImageFilterUrl) {
      this.aiFilterErrors[layerId] = 'Upload an image before applying this filter.';
      this.syncLinkedImageInput(layerId);
      this.updateHiddenField();
      return false;
    }

    // Do not present the source upload as though it were the AI-filtered result.
    input.attachmentId = 0;
    input.attachmentUrl = '';
    input.imageMeta = null;
    this.syncLinkedImageInput(layerId);
    this.scheduleRedraw(this.areaIndexForLayer(layerId));
    this.updateHiddenField();
    const request = this.createStateAbortController(30000);
    const controller = request.controller;
    this.aiFilterAbortControllers[layerId] = controller;
    // The handle is deliberately consumed in finally across every return path.
    // eslint-disable-next-line @wordpress/no-unused-vars-before-return
    const operation = this.beginArtworkOperation('filter', layerId);
    delete this.aiFilterErrors[layerId];
    const targetZone = zoneEl || document.querySelector(`[data-oc-upload-zone="${layerId}"]`);
    if (targetZone) {
      this.setUploadProgress(targetZone, null, 'Applying image effect...');
      this.showUploadError(targetZone, '');
    }
    const variationId = this.currentVariationId();
    try {
      const response = await fetch(this.data.applyImageFilterUrl, {
        method: 'POST',
        signal: controller.signal,
        headers: this.restHeaders({
          'Content-Type': 'application/json'
        }),
        body: JSON.stringify({
          source_attachment_id: sourceId,
          filter_id: Number(filterId),
          layer_id: Number(layerId),
          design_id: Number(this.data.designId || 0),
          product_id: Number(this.data.productId || 0),
          variation_id: variationId
        })
      });
      const json = await response.json().catch(() => null);
      if (!response.ok || !json?.attachment_id || !json?.preview_url) {
        throw new Error(json?.message || 'The image effect could not be applied.');
      }
      if (!isCurrent()) {
        return false;
      }
      const filteredAttachmentId = Number(json.attachment_id);
      const filteredAttachmentUrl = String(json.preview_url);
      const filteredArtworkFileType = String(json.file_type || input.artworkFileType || '').toLowerCase();
      const imageMeta = await this.getTrackedImageMeta(filteredAttachmentUrl, layerId);
      if (!isCurrent()) {
        return false;
      }
      input.attachmentId = filteredAttachmentId;
      input.attachmentUrl = filteredAttachmentUrl;
      input.imageFilterId = Number(filterId);
      input.artworkFileType = filteredArtworkFileType;
      input.originalAttachmentUrl = String(json.original_url || filteredAttachmentUrl);
      input.previewAttachmentId = Number(json.preview_attachment_id || 0);
      input.imageMeta = imageMeta;
      delete this.aiFilterErrors[layerId];
      this.syncLinkedImageInput(layerId);
      this.scheduleRedraw(this.areaIndexForLayer(layerId));
      this.updateHiddenField();
      return true;
    } catch (error) {
      if (!isCurrent()) {
        return false;
      }
      const message = request.timedOut() ? 'The image effect timed out. Please try again.' : error?.message || 'The image effect could not be applied.';
      this.aiFilterErrors[layerId] = message;
      this.syncLinkedImageInput(layerId);
      if (targetZone) {
        this.showUploadError(targetZone, message);
      }
      this.scheduleRedraw(this.areaIndexForLayer(layerId));
      this.updateHiddenField();
      return false;
    } finally {
      this.finishArtworkOperation(operation);
      request.release();
      if (this.aiFilterAbortControllers[layerId] === controller) {
        delete this.aiFilterAbortControllers[layerId];
        if (targetZone) {
          this.setUploadProgress(targetZone, 0, '');
        }
      }
    }
  },
  restoreSourceArtwork(input, sourceId, sourceUrl) {
    if (!sourceId || !sourceUrl) {
      return;
    }
    input.attachmentId = sourceId;
    input.attachmentUrl = sourceUrl;
    input.originalAttachmentUrl = input.sourceOriginalAttachmentUrl || sourceUrl;
    input.artworkFileType = input.sourceArtworkFileType || input.artworkFileType || '';
    input.previewAttachmentId = Number(input.sourcePreviewAttachmentId || 0);
    input.imageMeta = input.sourceImageMeta || input.imageMeta || null;
  },
  setUploadZoneState(zoneEl, state) {
    zoneEl.classList.toggle('oc-upload-zone--uploaded', state === 'uploaded' || state === 'uploaded-error');
    zoneEl.classList.toggle('oc-upload-zone--error', state === 'error' || state === 'uploaded-error');
    const browse = zoneEl.querySelector('.uppy-DragDrop-browse');
    const note = zoneEl.querySelector('.uppy-DragDrop-note');
    if (browse) {
      browse.textContent = state === 'uploaded' || state === 'uploaded-error' ? 'Image uploaded' : 'Tap / click here to upload your image';
    }
    if (note) {
      if (!note.dataset.ocOriginalText) {
        note.dataset.ocOriginalText = note.textContent;
      }
      note.textContent = state === 'uploaded' || state === 'uploaded-error' ? 'Click to replace image' : note.dataset.ocOriginalText || note.textContent;
    }
  },
  setUploadProgress(zoneEl, percent, label) {
    const wrap = zoneEl.closest('.oc-artwork-wrap');
    if (!wrap) {
      return;
    }
    let progressEl = wrap.querySelector('.oc-upload-progress');
    if (!progressEl) {
      progressEl = document.createElement('div');
      progressEl.className = 'oc-upload-progress';
      progressEl.innerHTML = '<div class="oc-upload-progress-label"></div><div class="oc-upload-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><div class="oc-upload-progress-bar"></div></div>';
      zoneEl.insertAdjacentElement('afterend', progressEl);
    }
    const isIndeterminate = percent === null;
    const safePercent = isIndeterminate ? 0 : Math.max(0, Math.min(100, parseInt(percent, 10) || 0));
    const labelEl = progressEl.querySelector('.oc-upload-progress-label');
    const track = progressEl.querySelector('.oc-upload-progress-track');
    const bar = progressEl.querySelector('.oc-upload-progress-bar');
    if (labelEl) {
      labelEl.textContent = label || '';
      labelEl.setAttribute('aria-live', 'polite');
    }
    if (track) {
      track.classList.toggle('oc-upload-progress-track--indeterminate', isIndeterminate);
      if (isIndeterminate) {
        track.removeAttribute('aria-valuenow');
      } else {
        track.setAttribute('aria-valuenow', String(safePercent));
      }
      track.setAttribute('aria-label', label || 'Upload progress');
    }
    if (bar) {
      bar.style.width = isIndeterminate ? '' : `${safePercent}%`;
    }
    progressEl.style.display = label ? '' : 'none';
  },
  showUploadImportFailure(zoneEl, error) {
    console.warn('[OC] Upload controls failed to load:', error);
    this.setUploadZoneState(zoneEl, 'error');
    this.showUploadError(zoneEl, 'Upload controls could not load. Check your connection and retry.');
    const errorEl = zoneEl.closest('.oc-artwork-wrap')?.querySelector('.oc-artwork-error');
    if (!errorEl || errorEl.querySelector('[data-oc-upload-retry]')) {
      return;
    }
    const retry = document.createElement('button');
    retry.type = 'button';
    retry.className = 'oc-upload-retry';
    retry.dataset.ocUploadRetry = '1';
    retry.textContent = 'Retry upload controls';
    retry.addEventListener('click', () => {
      retry.disabled = true;
      zoneEl.removeAttribute('data-oc-uppy-ready');
      this._uploadSetupPromise = this.setupUploadZones();
    }, {
      signal: this._panelListenerController?.signal
    });
    errorEl.append(document.createTextNode(' '), retry);
  },
  showUploadError(zoneEl, message) {
    const wrap = zoneEl.closest('.oc-artwork-wrap');
    if (!wrap) {
      return;
    }
    let err = wrap.querySelector('.oc-artwork-error');
    if (!err) {
      err = document.createElement('div');
      err.className = 'oc-artwork-error';
      err.style.cssText = 'color:#b32d2e;font-size:12px;margin-top:6px;';
      err.id = `oc-artwork-error-${zoneEl.dataset.ocUploadZone || 'upload'}`;
      err.setAttribute('role', 'status');
      err.setAttribute('aria-live', 'polite');
      err.setAttribute('aria-atomic', 'true');
      wrap.appendChild(err);
    }
    err.replaceChildren(document.createTextNode(message || ''));
    err.style.display = message ? '' : 'none';
    if (message) {
      const describedBy = new Set(String(zoneEl.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean));
      describedBy.add(err.id);
      zoneEl.setAttribute('aria-describedby', Array.from(describedBy).join(' '));
    }
  }
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (uploadMethods);

/***/ },

/***/ "./src/shared/render-math.js"
/*!***********************************!*\
  !*** ./src/shared/render-math.js ***!
  \***********************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   VALID_UNITS: () => (/* binding */ VALID_UNITS),
/* harmony export */   displayBounds: () => (/* binding */ displayBounds),
/* harmony export */   displayEntity: () => (/* binding */ displayEntity),
/* harmony export */   displayFontSize: () => (/* binding */ displayFontSize),
/* harmony export */   displayLayer: () => (/* binding */ displayLayer),
/* harmony export */   normaliseDpi: () => (/* binding */ normaliseDpi),
/* harmony export */   normaliseUnit: () => (/* binding */ normaliseUnit),
/* harmony export */   rasterDimensionsForLayer: () => (/* binding */ rasterDimensionsForLayer),
/* harmony export */   unitPxScale: () => (/* binding */ unitPxScale)
/* harmony export */ });
const VALID_UNITS = ['px', 'mm', 'cm', 'in'];
function normaliseUnit(value) {
  return VALID_UNITS.includes(value) ? value : 'px';
}
function normaliseDpi(value) {
  return Math.min(1200, Math.max(36, Math.round(Number(value) || 300)));
}
function unitPxScale(areaOrBounds) {
  const dpi = normaliseDpi(areaOrBounds?.dpi);
  switch (normaliseUnit(areaOrBounds?.unit)) {
    case 'mm':
      return dpi / 25.4;
    case 'cm':
      return dpi / 2.54;
    case 'in':
      return dpi;
    default:
      return 1;
  }
}
function displayEntity(entity, area = null) {
  if (!entity) {
    return entity;
  }
  const sourceArea = area || entity;
  const px = unitPxScale(sourceArea);
  if (px === 1) {
    return entity;
  }
  const originX = Number(sourceArea.x) || 0;
  const originY = Number(sourceArea.y) || 0;
  return {
    ...entity,
    x: originX + (Number(entity.x) - originX) * px,
    y: originY + (Number(entity.y) - originY) * px,
    w: Number(entity.w || 0) * px,
    h: Number(entity.h || 0) * px
  };
}
function displayBounds(bounds) {
  return displayEntity(bounds);
}
function displayLayer(layer, bounds) {
  return displayEntity(layer, bounds);
}
function rasterDimensionsForLayer(layer, areaOrBounds) {
  const display = displayLayer(layer, areaOrBounds);
  return {
    width: Math.max(1, Math.ceil(Math.abs(Number(display?.w) || 0))),
    height: Math.max(1, Math.ceil(Math.abs(Number(display?.h) || 0)))
  };
}
function displayFontSize(fontSize, areaOrBounds, canvasScale = 1) {
  return Math.max(1, Number(fontSize) || 0) * unitPxScale(areaOrBounds) * canvasScale;
}

/***/ },

/***/ "./node_modules/@uppy/core/dist/style.min.css"
/*!****************************************************!*\
  !*** ./node_modules/@uppy/core/dist/style.min.css ***!
  \****************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ },

/***/ "./node_modules/@uppy/drag-drop/dist/style.min.css"
/*!*********************************************************!*\
  !*** ./node_modules/@uppy/drag-drop/dist/style.min.css ***!
  \*********************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ },

/***/ "./src/frontend/customiser-app.scss"
/*!******************************************!*\
  !*** ./src/frontend/customiser-app.scss ***!
  \******************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ },

/***/ "@wordpress/data"
/*!******************************!*\
  !*** external ["wp","data"] ***!
  \******************************/
(module) {

module.exports = window["wp"]["data"];

/***/ },

/***/ "./node_modules/fabric/dist/index.min.mjs"
/*!************************************************!*\
  !*** ./node_modules/fabric/dist/index.min.mjs ***!
  \************************************************/
(__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   ActiveSelection: () => (/* binding */ gs),
/* harmony export */   BaseBrush: () => (/* binding */ jo),
/* harmony export */   BaseFabricObject: () => (/* binding */ Ir),
/* harmony export */   Canvas: () => (/* binding */ ho),
/* harmony export */   Canvas2dFilterBackend: () => (/* binding */ _s),
/* harmony export */   CanvasDOMManager: () => (/* binding */ qa),
/* harmony export */   Circle: () => (/* binding */ Fo),
/* harmony export */   CircleBrush: () => (/* binding */ Io),
/* harmony export */   ClipPathLayout: () => (/* binding */ ps),
/* harmony export */   Color: () => (/* binding */ G),
/* harmony export */   Control: () => (/* binding */ q),
/* harmony export */   Ellipse: () => (/* binding */ Uo),
/* harmony export */   FabricImage: () => (/* binding */ ws),
/* harmony export */   FabricObject: () => (/* binding */ J),
/* harmony export */   FabricText: () => (/* binding */ Q),
/* harmony export */   FitContentLayout: () => (/* binding */ ia),
/* harmony export */   FixedLayout: () => (/* binding */ ms),
/* harmony export */   Gradient: () => (/* binding */ ko),
/* harmony export */   Group: () => (/* binding */ ca),
/* harmony export */   IText: () => (/* binding */ ds),
/* harmony export */   Image: () => (/* binding */ ws),
/* harmony export */   InteractiveFabricObject: () => (/* binding */ _i),
/* harmony export */   Intersection: () => (/* binding */ Pr),
/* harmony export */   LayoutManager: () => (/* binding */ oa),
/* harmony export */   LayoutStrategy: () => (/* binding */ ra),
/* harmony export */   Line: () => (/* binding */ Bo),
/* harmony export */   Object: () => (/* binding */ J),
/* harmony export */   Observable: () => (/* binding */ be),
/* harmony export */   Path: () => (/* binding */ Mo),
/* harmony export */   Pattern: () => (/* binding */ Ao),
/* harmony export */   PatternBrush: () => (/* binding */ Ro),
/* harmony export */   PencilBrush: () => (/* binding */ No),
/* harmony export */   Point: () => (/* binding */ N),
/* harmony export */   Polygon: () => (/* binding */ Ko),
/* harmony export */   Polyline: () => (/* binding */ Go),
/* harmony export */   Rect: () => (/* binding */ $i),
/* harmony export */   Shadow: () => (/* binding */ Bn),
/* harmony export */   SprayBrush: () => (/* binding */ Lo),
/* harmony export */   StaticCanvas: () => (/* binding */ yt),
/* harmony export */   StaticCanvasDOMManager: () => (/* binding */ dt),
/* harmony export */   Text: () => (/* binding */ Q),
/* harmony export */   Textbox: () => (/* binding */ fs),
/* harmony export */   Triangle: () => (/* binding */ Vo),
/* harmony export */   WebGLFilterBackend: () => (/* binding */ vs),
/* harmony export */   cache: () => (/* binding */ y),
/* harmony export */   classRegistry: () => (/* binding */ M),
/* harmony export */   config: () => (/* binding */ o),
/* harmony export */   controlsUtils: () => (/* binding */ co),
/* harmony export */   createCollectionMixin: () => (/* binding */ Ee),
/* harmony export */   filters: () => (/* binding */ Cc),
/* harmony export */   getEnv: () => (/* binding */ h),
/* harmony export */   getFabricDocument: () => (/* binding */ g),
/* harmony export */   getFabricWindow: () => (/* binding */ _),
/* harmony export */   getFilterBackend: () => (/* binding */ xs),
/* harmony export */   iMatrix: () => (/* binding */ T),
/* harmony export */   initFilterBackend: () => (/* binding */ bs),
/* harmony export */   isPutImageFaster: () => (/* binding */ Bs),
/* harmony export */   isWebGLPipelineState: () => (/* binding */ zs),
/* harmony export */   loadSVGFromString: () => (/* binding */ Ls),
/* harmony export */   loadSVGFromURL: () => (/* binding */ Rs),
/* harmony export */   parseSVGDocument: () => (/* binding */ Is),
/* harmony export */   runningAnimations: () => (/* binding */ ye),
/* harmony export */   setEnv: () => (/* binding */ m),
/* harmony export */   setFilterBackend: () => (/* binding */ Ss),
/* harmony export */   util: () => (/* binding */ Ga),
/* harmony export */   version: () => (/* binding */ b)
/* harmony export */ });
var e=Object.defineProperty,t=(t,n)=>{let r={};for(var i in t)e(r,i,{get:t[i],enumerable:!0});return n||e(r,Symbol.toStringTag,{value:`Module`}),r};function n(e){return n=typeof Symbol==`function`&&typeof Symbol.iterator==`symbol`?function(e){return typeof e}:function(e){return e&&typeof Symbol==`function`&&e.constructor===Symbol&&e!==Symbol.prototype?`symbol`:typeof e},n(e)}function r(e){var t=function(e,t){if(n(e)!=`object`||!e)return e;var r=e[Symbol.toPrimitive];if(r!==void 0){var i=r.call(e,t||`default`);if(n(i)!=`object`)return i;throw TypeError(`@@toPrimitive must return a primitive value.`)}return(t===`string`?String:Number)(e)}(e,`string`);return n(t)==`symbol`?t:t+``}function i(e,t,n){return(t=r(t))in e?Object.defineProperty(e,t,{value:n,enumerable:!0,configurable:!0,writable:!0}):e[t]=n,e}var a=class{constructor(){i(this,`browserShadowBlurConstant`,1),i(this,`DPI`,96),i(this,`devicePixelRatio`,typeof window<`u`?window.devicePixelRatio:1),i(this,`perfLimitSizeTotal`,2097152),i(this,`maxCacheSideLimit`,4096),i(this,`minCacheSideLimit`,256),i(this,`disableStyleCopyPaste`,!1),i(this,`enableGLFiltering`,!0),i(this,`textureSize`,4096),i(this,`forceGLPutImageData`,!1),i(this,`cachesBoundsOfCurve`,!1),i(this,`fontPaths`,{}),i(this,`NUM_FRACTION_DIGITS`,4)}};const o=new class extends a{constructor(e){super(),this.configure(e)}configure(e={}){Object.assign(this,e)}addFonts(e={}){this.fontPaths={...this.fontPaths,...e}}removeFonts(e=[]){e.forEach(e=>{delete this.fontPaths[e]})}clearFonts(){this.fontPaths={}}restoreDefaults(e){let t=new a,n=(e==null?void 0:e.reduce((e,n)=>(e[n]=t[n],e),{}))||t;this.configure(n)}},s=(e,...t)=>console[e](`fabric`,...t);var c=class extends Error{constructor(e,t){super(`fabric: ${e}`,t)}},l=class extends c{constructor(e){super(`${e} 'options.signal' is in 'aborted' state`)}},u=class{},d=class extends u{testPrecision(e,t){let n=`precision ${t} float;\nvoid main(){}`,r=e.createShader(e.FRAGMENT_SHADER);return!!r&&(e.shaderSource(r,n),e.compileShader(r),!!e.getShaderParameter(r,e.COMPILE_STATUS))}queryWebGL(e){let t=e.getContext(`webgl`);t&&(this.maxTextureSize=t.getParameter(t.MAX_TEXTURE_SIZE),this.GLPrecision=[`highp`,`mediump`,`lowp`].find(e=>this.testPrecision(t,e)),t.getExtension(`WEBGL_lose_context`).loseContext(),s(`log`,`WebGL: max texture size ${this.maxTextureSize}`))}isSupported(e){return!!this.maxTextureSize&&this.maxTextureSize>=e}};const f={};let p;const m=e=>{p=e},h=()=>p||(p={document,window,isTouchSupported:`ontouchstart`in window||`ontouchstart`in document||window&&window.navigator&&window.navigator.maxTouchPoints>0,WebGLProbe:new d,dispose(){},copyPasteData:f}),g=()=>h().document,_=()=>h().window,v=()=>{var e;return Math.max((e=o.devicePixelRatio)==null?_().devicePixelRatio:e,1)},y=new class{constructor(){i(this,`boundsOfCurveCache`,{}),this.charWidthsCache=new Map}getFontCache({fontFamily:e,fontStyle:t,fontWeight:n}){e=e.toLowerCase();let r=this.charWidthsCache;r.has(e)||r.set(e,new Map);let i=r.get(e),a=`${t.toLowerCase()}_${(n+``).toLowerCase()}`;return i.has(a)||i.set(a,new Map),i.get(a)}clearFontCache(e){e?this.charWidthsCache.delete((e||``).toLowerCase()):this.charWidthsCache=new Map}limitDimsByArea(e){let{perfLimitSizeTotal:t}=o,n=Math.sqrt(t*e);return[Math.floor(n),Math.floor(t/n)]}},b=`7.4.0`;function x(){}const S=Math.PI/2,C=Math.PI/4,w=2*Math.PI,ee=Math.PI/180,T=Object.freeze([1,0,0,1,0,0]),E=`center`,D=`left`,O=`bottom`,k=`right`,te=`none`,ne=/\r?\n/,re=`moving`,ie=`scaling`,ae=`rotating`,oe=`rotate`,A=`skewing`,se=`resizing`,ce=`modifyPoly`,le=`changed`,ue=`scale`,de=`scaleX`,fe=`scaleY`,pe=`skewX`,me=`skewY`,j=`fill`,he=`stroke`,ge=`modified`,_e=`normal`,ve=`json`,M=new class{constructor(){this[ve]=new Map,this.svg=new Map}has(e){return this[ve].has(e)}getClass(e){let t=this[ve].get(e);if(!t)throw new c(`No class registered for ${e}`);return t}setClass(e,t){t?this[ve].set(t,e):(this[ve].set(e.type,e),this[ve].set(e.type.toLowerCase(),e))}getSVGClass(e){return this.svg.get(e)}setSVGClass(e,t){this.svg.set(t==null?e.type.toLowerCase():t,e)}},ye=new class extends Array{remove(e){let t=this.indexOf(e);t>-1&&this.splice(t,1)}cancelAll(){let e=this.splice(0);return e.forEach(e=>e.abort()),e}cancelByCanvas(e){if(!e)return[];let t=this.filter(t=>{var n;return t.target===e||typeof t.target==`object`&&((n=t.target)==null?void 0:n.canvas)===e});return t.forEach(e=>e.abort()),t}cancelByTarget(e){if(!e)return[];let t=this.filter(t=>t.target===e);return t.forEach(e=>e.abort()),t}};var be=class{constructor(){i(this,`__eventListeners`,{})}on(e,t){if(this.__eventListeners||(this.__eventListeners={}),typeof e==`object`)return Object.entries(e).forEach(([e,t])=>{this.on(e,t)}),()=>this.off(e);if(t){let n=e;return this.__eventListeners[n]||(this.__eventListeners[n]=[]),this.__eventListeners[n].push(t),()=>this.off(n,t)}return()=>!1}once(e,t){if(typeof e==`object`){let t=[];return Object.entries(e).forEach(([e,n])=>{t.push(this.once(e,n))}),()=>t.forEach(e=>e())}if(t){let n=this.on(e,function(...e){t.call(this,...e),n()});return n}return()=>!1}_removeEventListener(e,t){if(this.__eventListeners[e])if(t){let n=this.__eventListeners[e],r=n.indexOf(t);r>-1&&n.splice(r,1)}else this.__eventListeners[e]=[]}off(e,t){if(this.__eventListeners)if(e===void 0)for(let e in this.__eventListeners)this._removeEventListener(e);else typeof e==`object`?Object.entries(e).forEach(([e,t])=>{this._removeEventListener(e,t)}):this._removeEventListener(e,t)}fire(e,t){var n;if(!this.__eventListeners)return;let r=(n=this.__eventListeners[e])==null?void 0:n.concat();if(r)for(let e=0;e<r.length;e++)r[e].call(this,t||{})}};const xe=(e,t)=>{let n=e.indexOf(t);return n!==-1&&e.splice(n,1),e},Se=e=>{if(e===0)return 1;switch(Math.abs(e)/S){case 1:case 3:return 0;case 2:return-1}return Math.cos(e)},Ce=e=>{if(e===0)return 0;let t=e/S,n=Math.sign(e);switch(t){case 1:return n;case 2:return 0;case 3:return-n}return Math.sin(e)};var N=class e{constructor(e=0,t=0){typeof e==`object`?(this.x=e.x,this.y=e.y):(this.x=e,this.y=t)}add(t){return new e(this.x+t.x,this.y+t.y)}addEquals(e){return this.x+=e.x,this.y+=e.y,this}scalarAdd(t){return new e(this.x+t,this.y+t)}scalarAddEquals(e){return this.x+=e,this.y+=e,this}subtract(t){return new e(this.x-t.x,this.y-t.y)}subtractEquals(e){return this.x-=e.x,this.y-=e.y,this}scalarSubtract(t){return new e(this.x-t,this.y-t)}scalarSubtractEquals(e){return this.x-=e,this.y-=e,this}multiply(t){return new e(this.x*t.x,this.y*t.y)}scalarMultiply(t){return new e(this.x*t,this.y*t)}scalarMultiplyEquals(e){return this.x*=e,this.y*=e,this}divide(t){return new e(this.x/t.x,this.y/t.y)}scalarDivide(t){return new e(this.x/t,this.y/t)}scalarDivideEquals(e){return this.x/=e,this.y/=e,this}eq(e){return this.x===e.x&&this.y===e.y}lt(e){return this.x<e.x&&this.y<e.y}lte(e){return this.x<=e.x&&this.y<=e.y}gt(e){return this.x>e.x&&this.y>e.y}gte(e){return this.x>=e.x&&this.y>=e.y}lerp(t,n=.5){return n=Math.max(Math.min(1,n),0),new e(this.x+(t.x-this.x)*n,this.y+(t.y-this.y)*n)}distanceFrom(e){let t=this.x-e.x,n=this.y-e.y;return Math.sqrt(t*t+n*n)}midPointFrom(e){return this.lerp(e)}min(t){return new e(Math.min(this.x,t.x),Math.min(this.y,t.y))}max(t){return new e(Math.max(this.x,t.x),Math.max(this.y,t.y))}toString(){return`${this.x},${this.y}`}setXY(e,t){return this.x=e,this.y=t,this}setX(e){return this.x=e,this}setY(e){return this.y=e,this}setFromPoint(e){return this.x=e.x,this.y=e.y,this}swap(e){let t=this.x,n=this.y;this.x=e.x,this.y=e.y,e.x=t,e.y=n}clone(){return new e(this.x,this.y)}rotate(t,n=we){let r=Ce(t),i=Se(t),a=this.subtract(n);return new e(a.x*i-a.y*r,a.x*r+a.y*i).add(n)}transform(t,n=!1){return new e(t[0]*this.x+t[2]*this.y+(n?0:t[4]),t[1]*this.x+t[3]*this.y+(n?0:t[5]))}};const we=new N(0,0),Te=e=>!!e&&Array.isArray(e._objects);function Ee(e){class t extends e{constructor(...e){super(...e),i(this,`_objects`,[])}_onObjectAdded(e){}_onObjectRemoved(e){}_onStackOrderChanged(e){}add(...e){let t=this._objects.push(...e);return e.forEach(e=>this._onObjectAdded(e)),t}insertAt(e,...t){return this._objects.splice(e,0,...t),t.forEach(e=>this._onObjectAdded(e)),this._objects.length}remove(...e){let t=this._objects,n=[];return e.forEach(e=>{let r=t.indexOf(e);r!==-1&&(t.splice(r,1),n.push(e),this._onObjectRemoved(e))}),n}forEachObject(e){this.getObjects().forEach((t,n,r)=>e(t,n,r))}getObjects(...e){return e.length===0?[...this._objects]:this._objects.filter(t=>t.isType(...e))}item(e){return this._objects[e]}isEmpty(){return this._objects.length===0}size(){return this._objects.length}contains(e,n){return!!this._objects.includes(e)||!!n&&this._objects.some(n=>n instanceof t&&n.contains(e,!0))}complexity(){return this._objects.reduce((e,t)=>e+=t.complexity?t.complexity():0,0)}sendObjectToBack(e){return!(!e||e===this._objects[0])&&(xe(this._objects,e),this._objects.unshift(e),this._onStackOrderChanged(e),!0)}bringObjectToFront(e){return!(!e||e===this._objects[this._objects.length-1])&&(xe(this._objects,e),this._objects.push(e),this._onStackOrderChanged(e),!0)}sendObjectBackwards(e,t){if(!e)return!1;let n=this._objects.indexOf(e);if(n!==0){let r=this.findNewLowerIndex(e,n,t);return xe(this._objects,e),this._objects.splice(r,0,e),this._onStackOrderChanged(e),!0}return!1}bringObjectForward(e,t){if(!e)return!1;let n=this._objects.indexOf(e);if(n!==this._objects.length-1){let r=this.findNewUpperIndex(e,n,t);return xe(this._objects,e),this._objects.splice(r,0,e),this._onStackOrderChanged(e),!0}return!1}moveObjectTo(e,t){return e!==this._objects[t]&&(xe(this._objects,e),this._objects.splice(t,0,e),this._onStackOrderChanged(e),!0)}findNewLowerIndex(e,t,n){let r;if(n){r=t;for(let n=t-1;n>=0;--n)if(e.isOverlapping(this._objects[n])){r=n;break}}else r=t-1;return r}findNewUpperIndex(e,t,n){let r;if(n){r=t;for(let n=t+1;n<this._objects.length;++n)if(e.isOverlapping(this._objects[n])){r=n;break}}else r=t+1;return r}collectObjects({left:e,top:t,width:n,height:r},{includeIntersecting:i=!0}={}){let a=[],o=new N(e,t),s=o.add(new N(n,r));for(let e=this._objects.length-1;e>=0;e--){let t=this._objects[e];t.selectable&&t.visible&&(i&&t.intersectsWithRect(o,s)||t.isContainedWithinRect(o,s)||i&&t.containsPoint(o)||i&&t.containsPoint(s))&&a.push(t)}return a}}return t}var De=class extends be{_setOptions(e={}){for(let t in e)this.set(t,e[t])}_setObject(e){for(let t in e)this._set(t,e[t])}set(e,t){return typeof e==`object`?this._setObject(e):this._set(e,t),this}_set(e,t){this[e]=t}toggle(e){let t=this.get(e);return typeof t==`boolean`&&this.set(e,!t),this}get(e){return this[e]}};function Oe(e){return _().requestAnimationFrame(e)}function ke(e){return _().cancelAnimationFrame(e)}let Ae=0;const je=()=>Ae++,P=()=>{let e=g().createElement(`canvas`);if(!e||e.getContext===void 0)throw new c("Failed to create `canvas` element");return e},Me=()=>g().createElement(`img`),Ne=e=>{var t;let n=F(e);return(t=n.getContext(`2d`))==null||t.drawImage(e,0,0),n},F=e=>{let t=P();return t.width=e.width,t.height=e.height,t},Pe=(e,t,n)=>e.toDataURL(`image/${t}`,n),Fe=(e,t,n)=>new Promise((r,i)=>{e.toBlob(r,`image/${t}`,n)}),I=e=>e*ee,Ie=e=>e/ee,Le=e=>e.every((e,t)=>e===T[t]),L=(e,t,n)=>new N(e).transform(t,n),R=e=>{let t=1/(e[0]*e[3]-e[1]*e[2]),n=[t*e[3],-t*e[1],-t*e[2],t*e[0],0,0],{x:r,y:i}=new N(e[4],e[5]).transform(n,!0);return n[4]=-r,n[5]=-i,n},z=(e,t,n)=>[e[0]*t[0]+e[2]*t[1],e[1]*t[0]+e[3]*t[1],e[0]*t[2]+e[2]*t[3],e[1]*t[2]+e[3]*t[3],n?0:e[0]*t[4]+e[2]*t[5]+e[4],n?0:e[1]*t[4]+e[3]*t[5]+e[5]],Re=(e,t)=>e.reduceRight((e,n)=>n&&e?z(n,e,t):n||e,void 0)||T.concat(),ze=([e,t])=>Math.atan2(t,e),Be=([e,t])=>Math.sqrt(e*e+t*t),Ve=([,,e,t])=>Math.sqrt(e*e+t*t),He=e=>{let t=ze(e),n=e[0]**2+e[1]**2,r=Math.sqrt(n),i=(e[0]*e[3]-e[2]*e[1])/r,a=Math.atan2(e[0]*e[2]+e[1]*e[3],n);return{angle:Ie(t),scaleX:r,scaleY:i,skewX:Ie(a),skewY:0,translateX:e[4]||0,translateY:e[5]||0}},Ue=(e,t=0)=>[1,0,0,1,e,t];function We({angle:e=0}={},{x:t=0,y:n=0}={}){let r=I(e),i=Se(r),a=Ce(r);return[i,a,-a,i,t?t-(i*t-a*n):0,n?n-(a*t+i*n):0]}const Ge=(e,t=e)=>[e,0,0,t,0,0],Ke=e=>Math.tan(I(e)),qe=e=>[1,0,Ke(e),1,0,0],Je=e=>[1,Ke(e),0,1,0,0],Ye=({scaleX:e=1,scaleY:t=1,flipX:n=!1,flipY:r=!1,skewX:i=0,skewY:a=0})=>{let o=Ge(n?-e:e,r?-t:t);return i&&(o=z(o,qe(i),!0)),a&&(o=z(o,Je(a),!0)),o},Xe=e=>{let{translateX:t=0,translateY:n=0,angle:r=0}=e,i=Ue(t,n);r&&(i=z(i,We({angle:r})));let a=Ye(e);return Le(a)||(i=z(i,a)),i},Ze=(e,{signal:t,crossOrigin:n=null}={})=>new Promise(function(r,i){if(t&&t.aborted)return i(new l(`loadImage`));let a=Me(),o;t&&(o=function(e){a.src=``,i(e)},t.addEventListener(`abort`,o,{once:!0}));let s=function(){a.onload=a.onerror=null,o&&(t==null||t.removeEventListener(`abort`,o)),r(a)};e?(a.onload=s,a.onerror=function(){o&&(t==null||t.removeEventListener(`abort`,o)),i(new c(`Error loading ${a.src}`))},n&&(a.crossOrigin=n),a.src=e):s()}),Qe=(e,{signal:t,reviver:n=x}={})=>new Promise((r,i)=>{let a=[];t&&t.addEventListener(`abort`,i,{once:!0}),Promise.allSettled(e.map(e=>M.getClass(e.type).fromObject(e,{signal:t}))).then(async t=>{for(let[r,i]of t.entries())if(i.status===`fulfilled`&&(await n(e[r],i.value),a.push(i.value)),i.status===`rejected`){let t=await n(e[r],void 0,i.reason);t&&a.push(t)}r(a)}).catch(e=>{a.forEach(e=>{e.dispose&&e.dispose()}),i(e)}).finally(()=>{t&&t.removeEventListener(`abort`,i)})}),$e=(e,{signal:t}={})=>new Promise((n,r)=>{let i=[];t&&t.addEventListener(`abort`,r,{once:!0});let a=Object.values(e).map(e=>e&&e.type&&M.has(e.type)?Qe([e],{signal:t}).then(([e])=>(i.push(e),e)):e),o=Object.keys(e);Promise.all(a).then(e=>e.reduce((e,t,n)=>(e[o[n]]=t,e),{})).then(n).catch(e=>{i.forEach(e=>{e.dispose&&e.dispose()}),r(e)}).finally(()=>{t&&t.removeEventListener(`abort`,r)})}),et=(e,t=[])=>t.reduce((t,n)=>(n in e&&(t[n]=e[n]),t),{}),tt=(e,t)=>Object.keys(e).reduce((n,r)=>(t(e[r],r,e)&&(n[r]=e[r]),n),{}),B=(e,t)=>parseFloat(Number(e).toFixed(t)),nt=e=>`matrix(`+e.map(e=>B(e,o.NUM_FRACTION_DIGITS)).join(` `)+`)`,V=e=>!!e&&e.toLive!==void 0,rt=e=>!!e&&typeof e.toObject==`function`,it=e=>!!e&&e.offsetX!==void 0&&`source`in e,at=e=>!!e&&`multiSelectionStacking`in e;function ot(e){let t=e&&H(e),n=0,r=0;if(!e||!t)return{left:n,top:r};let i=e,a=t.documentElement,o=t.body||{scrollLeft:0,scrollTop:0};for(;i&&(i.parentNode||i.host)&&(i=i.parentNode||i.host,i===t?(n=o.scrollLeft||a.scrollLeft||0,r=o.scrollTop||a.scrollTop||0):(n+=i.scrollLeft||0,r+=i.scrollTop||0),i.nodeType!==1||i.style.position!==`fixed`););return{left:n,top:r}}const H=e=>e.ownerDocument||null,st=e=>{var t;return((t=e.ownerDocument)==null?void 0:t.defaultView)||null},ct=(e,t,{width:n,height:r},i=1)=>{e.width=n,e.height=r,i>1&&(e.setAttribute(`width`,(n*i).toString()),e.setAttribute(`height`,(r*i).toString()),t.scale(i,i))},lt=(e,{width:t,height:n})=>{t&&(e.style.width=typeof t==`number`?`${t}px`:t),n&&(e.style.height=typeof n==`number`?`${n}px`:n)};function ut(e){return e.onselectstart!==void 0&&(e.onselectstart=()=>!1),e.style.userSelect=te,e}var dt=class{constructor(e){i(this,`_originalCanvasStyle`,void 0),i(this,`lower`,void 0);let t=this.createLowerCanvas(e);this.lower={el:t,ctx:t.getContext(`2d`)}}createLowerCanvas(e){let t=(n=e)&&n.getContext!==void 0?e:e&&g().getElementById(e)||P();var n;if(t.hasAttribute(`data-fabric`))throw new c(`Trying to initialize a canvas that has already been initialized. Did you forget to dispose the canvas?`);return this._originalCanvasStyle=t.style.cssText,t.setAttribute(`data-fabric`,`main`),t.classList.add(`lower-canvas`),t}cleanupDOM({width:e,height:t}){let{el:n}=this.lower;n.classList.remove(`lower-canvas`),n.removeAttribute(`data-fabric`),n.setAttribute(`width`,`${e}`),n.setAttribute(`height`,`${t}`),n.style.cssText=this._originalCanvasStyle||``,this._originalCanvasStyle=void 0}setDimensions(e,t){let{el:n,ctx:r}=this.lower;ct(n,r,e,t)}setCSSDimensions(e){lt(this.lower.el,e)}calcOffset(){return function(e){var t;let n=e&&H(e),r={left:0,top:0};if(!n)return r;let i=((t=st(e))==null?void 0:t.getComputedStyle(e,null))||{};r.left+=parseInt(i.borderLeftWidth,10)||0,r.top+=parseInt(i.borderTopWidth,10)||0,r.left+=parseInt(i.paddingLeft,10)||0,r.top+=parseInt(i.paddingTop,10)||0;let a={left:0,top:0},o=n.documentElement;e.getBoundingClientRect!==void 0&&(a=e.getBoundingClientRect());let s=ot(e);return{left:a.left+s.left-(o.clientLeft||0)+r.left,top:a.top+s.top-(o.clientTop||0)+r.top}}(this.lower.el)}dispose(){h().dispose(this.lower.el),delete this.lower}};const ft={backgroundVpt:!0,backgroundColor:``,overlayVpt:!0,overlayColor:``,includeDefaultValues:!0,svgViewportTransformation:!0,renderOnAddRemove:!0,skipOffscreen:!0,enableRetinaScaling:!0,imageSmoothingEnabled:!0,controlsAboveOverlay:!1,allowTouchScrolling:!1,viewportTransform:[...T],patternQuality:`best`};var pt=t({capitalize:()=>mt,escapeXml:()=>U,graphemeSplit:()=>gt});const mt=(e,t=!1)=>`${e.charAt(0).toUpperCase()}${t?e.slice(1):e.slice(1).toLowerCase()}`,U=e=>e.toString().replace(/&/g,`&amp;`).replace(/"/g,`&quot;`).replace(/'/g,`&apos;`).replace(/</g,`&lt;`).replace(/>/g,`&gt;`);let ht;const gt=e=>{if(ht||ht||(ht=`Intl`in _()&&`Segmenter`in Intl&&new Intl.Segmenter(void 0,{granularity:`grapheme`})),ht){let t=ht.segment(e);return Array.from(t).map(({segment:e})=>e)}return _t(e)},_t=e=>{let t=[];for(let n,r=0;r<e.length;r++)!1!==(n=vt(e,r))&&t.push(n);return t},vt=(e,t)=>{let n=e.charCodeAt(t);if(isNaN(n))return``;if(n<55296||n>57343)return e.charAt(t);if(55296<=n&&n<=56319){if(e.length<=t+1)throw`High surrogate without following low surrogate`;let n=e.charCodeAt(t+1);if(56320>n||n>57343)throw`High surrogate without following low surrogate`;return e.charAt(t)+e.charAt(t+1)}if(t===0)throw`Low surrogate without preceding high surrogate`;let r=e.charCodeAt(t-1);if(55296>r||r>56319)throw`Low surrogate without preceding high surrogate`;return!1};var yt=class e extends Ee(De){get lowerCanvasEl(){var e;return(e=this.elements.lower)==null?void 0:e.el}get contextContainer(){var e;return(e=this.elements.lower)==null?void 0:e.ctx}static getDefaults(){return e.ownDefaults}constructor(e,t={}){super(),Object.assign(this,this.constructor.getDefaults()),this.set(t),this.initElements(e),this._setDimensionsImpl({width:this.width||this.elements.lower.el.width||0,height:this.height||this.elements.lower.el.height||0}),this.skipControlsDrawing=!1,this.viewportTransform=[...this.viewportTransform],this.calcViewportBoundaries()}initElements(e){this.elements=new dt(e)}add(...e){let t=super.add(...e);return e.length>0&&this.renderOnAddRemove&&this.requestRenderAll(),t}insertAt(e,...t){let n=super.insertAt(e,...t);return t.length>0&&this.renderOnAddRemove&&this.requestRenderAll(),n}remove(...e){let t=super.remove(...e);return t.length>0&&this.renderOnAddRemove&&this.requestRenderAll(),t}_onObjectAdded(e){e.canvas&&e.canvas!==this&&(s(`warn`,`Canvas is trying to add an object that belongs to a different canvas.
Resulting to default behavior: removing object from previous canvas and adding to new canvas`),e.canvas.remove(e)),e._set(`canvas`,this),e.setCoords(),this.fire(`object:added`,{target:e}),e.fire(`added`,{target:this})}_onObjectRemoved(e){e._set(`canvas`,void 0),this.fire(`object:removed`,{target:e}),e.fire(`removed`,{target:this})}_onStackOrderChanged(){this.renderOnAddRemove&&this.requestRenderAll()}getRetinaScaling(){return this.enableRetinaScaling?v():1}calcOffset(){return this._offset=this.elements.calcOffset()}getWidth(){return this.width}getHeight(){return this.height}_setDimensionsImpl(e,{cssOnly:t=!1,backstoreOnly:n=!1}={}){if(!t){let t={width:this.width,height:this.height,...e};this.elements.setDimensions(t,this.getRetinaScaling()),this.hasLostContext=!0,this.width=t.width,this.height=t.height}n||this.elements.setCSSDimensions(e),this.calcOffset()}setDimensions(e,t){this._setDimensionsImpl(e,t),t&&t.cssOnly||this.requestRenderAll()}getZoom(){return Be(this.viewportTransform)}setViewportTransform(e){this.viewportTransform=e,this.calcViewportBoundaries(),this.renderOnAddRemove&&this.requestRenderAll()}zoomToPoint(e,t){let n=e,r=[...this.viewportTransform],i=L(e,R(r));r[0]=t,r[3]=t;let a=L(i,r);r[4]+=n.x-a.x,r[5]+=n.y-a.y,this.setViewportTransform(r)}setZoom(e){this.zoomToPoint(new N(0,0),e)}absolutePan(e){let t=[...this.viewportTransform];return t[4]=-e.x,t[5]=-e.y,this.setViewportTransform(t)}relativePan(e){return this.absolutePan(new N(-e.x-this.viewportTransform[4],-e.y-this.viewportTransform[5]))}getElement(){return this.elements.lower.el}clearContext(e){e.clearRect(0,0,this.width,this.height)}getContext(){return this.elements.lower.ctx}clear(){this.remove(...this.getObjects()),this.backgroundImage=void 0,this.overlayImage=void 0,this.backgroundColor=``,this.overlayColor=``,this.clearContext(this.getContext()),this.fire(`canvas:cleared`),this.renderOnAddRemove&&this.requestRenderAll()}renderAll(){this.cancelRequestedRender(),this.destroyed||this.renderCanvas(this.getContext(),this._objects)}renderAndReset(){this.nextRenderHandle=0,this.renderAll()}requestRenderAll(){this.nextRenderHandle||this.disposed||this.destroyed||(this.nextRenderHandle=Oe(()=>this.renderAndReset()))}calcViewportBoundaries(){let e=this.width,t=this.height,n=R(this.viewportTransform),r=L({x:0,y:0},n),i=L({x:e,y:t},n),a=r.min(i),o=r.max(i);return this.vptCoords={tl:a,tr:new N(o.x,a.y),bl:new N(a.x,o.y),br:o}}cancelRequestedRender(){this.nextRenderHandle&&(ke(this.nextRenderHandle),this.nextRenderHandle=0)}drawControls(e){}renderCanvas(e,t){if(this.destroyed)return;let n=this.viewportTransform,r=this.clipPath;this.calcViewportBoundaries(),this.clearContext(e),e.imageSmoothingEnabled=this.imageSmoothingEnabled,e.patternQuality=this.patternQuality,this.fire(`before:render`,{ctx:e}),this._renderBackground(e),e.save(),e.transform(n[0],n[1],n[2],n[3],n[4],n[5]),this._renderObjects(e,t),e.restore(),this.controlsAboveOverlay||this.skipControlsDrawing||this.drawControls(e),r&&(r._set(`canvas`,this),r.shouldCache(),r._transformDone=!0,r.renderCache({forClipping:!0}),this.drawClipPathOnCanvas(e,r)),this._renderOverlay(e),this.controlsAboveOverlay&&!this.skipControlsDrawing&&this.drawControls(e),this.fire(`after:render`,{ctx:e}),this.__cleanupTask&&(this.__cleanupTask(),this.__cleanupTask=void 0)}drawClipPathOnCanvas(e,t){let n=this.viewportTransform;e.save(),e.transform(...n),e.globalCompositeOperation=`destination-in`,t.transform(e),e.scale(1/t.zoomX,1/t.zoomY),e.drawImage(t._cacheCanvas,-t.cacheTranslationX,-t.cacheTranslationY),e.restore()}_renderObjects(e,t){for(let n=0,r=t.length;n<r;++n)t[n]&&t[n].render(e)}_renderBackgroundOrOverlay(e,t){let n=this[`${t}Color`],r=this[`${t}Image`],i=this.viewportTransform,a=this[`${t}Vpt`];if(!n&&!r)return;let o=V(n);if(n){if(e.save(),e.beginPath(),e.moveTo(0,0),e.lineTo(this.width,0),e.lineTo(this.width,this.height),e.lineTo(0,this.height),e.closePath(),e.fillStyle=o?n.toLive(e):n,a&&e.transform(...i),o){e.transform(1,0,0,1,n.offsetX||0,n.offsetY||0);let t=n.gradientTransform||n.patternTransform;t&&e.transform(...t)}e.fill(),e.restore()}if(r){e.save();let{skipOffscreen:t}=this;this.skipOffscreen=a,a&&e.transform(...i),r.render(e),this.skipOffscreen=t,e.restore()}}_renderBackground(e){this._renderBackgroundOrOverlay(e,`background`)}_renderOverlay(e){this._renderBackgroundOrOverlay(e,`overlay`)}getCenterPoint(){return new N(this.width/2,this.height/2)}centerObjectH(e){return this._centerObject(e,new N(this.getCenterPoint().x,e.getCenterPoint().y))}centerObjectV(e){return this._centerObject(e,new N(e.getCenterPoint().x,this.getCenterPoint().y))}centerObject(e){return this._centerObject(e,this.getCenterPoint())}viewportCenterObject(e){return this._centerObject(e,this.getVpCenter())}viewportCenterObjectH(e){return this._centerObject(e,new N(this.getVpCenter().x,e.getCenterPoint().y))}viewportCenterObjectV(e){return this._centerObject(e,new N(e.getCenterPoint().x,this.getVpCenter().y))}getVpCenter(){return L(this.getCenterPoint(),R(this.viewportTransform))}_centerObject(e,t){e.setXY(t,E,E),e.setCoords(),this.renderOnAddRemove&&this.requestRenderAll()}toDatalessJSON(e){return this.toDatalessObject(e)}toObject(e){return this._toObjectMethod(`toObject`,e)}toJSON(){return this.toObject()}toDatalessObject(e){return this._toObjectMethod(`toDatalessObject`,e)}_toObjectMethod(e,t){let n=this.clipPath,r=n&&!n.excludeFromExport?this._toObject(n,e,t):null;return{version:b,...et(this,t),objects:this._objects.filter(e=>!e.excludeFromExport).map(n=>this._toObject(n,e,t)),...this.__serializeBgOverlay(e,t),...r?{clipPath:r}:null}}_toObject(e,t,n){let r;this.includeDefaultValues||(r=e.includeDefaultValues,e.includeDefaultValues=!1);let i=e[t](n);return this.includeDefaultValues||(e.includeDefaultValues=!!r),i}__serializeBgOverlay(e,t){let n={},r=this.backgroundImage,i=this.overlayImage,a=this.backgroundColor,o=this.overlayColor;return V(a)?a.excludeFromExport||(n.background=a.toObject(t)):a&&(n.background=a),V(o)?o.excludeFromExport||(n.overlay=o.toObject(t)):o&&(n.overlay=o),r&&!r.excludeFromExport&&(n.backgroundImage=this._toObject(r,e,t)),i&&!i.excludeFromExport&&(n.overlayImage=this._toObject(i,e,t)),n}toSVG(e={},t){e.reviver=t;let n=[];var r;return(this._setSVGPreamble(n,e),this._setSVGHeader(n,e),this.clipPath)&&n.push(`<g clip-path="url(#${U((r=this.clipPath.clipPathId)==null?``:r)})" >\n`),this._setSVGBgOverlayColor(n,`background`),this._setSVGBgOverlayImage(n,`backgroundImage`,t),this._setSVGObjects(n,t),this.clipPath&&n.push(`</g>
`),this._setSVGBgOverlayColor(n,`overlay`),this._setSVGBgOverlayImage(n,`overlayImage`,t),n.push(`</svg>`),n.join(``)}_setSVGPreamble(e,t){t.suppressPreamble||e.push(`<?xml version="1.0" encoding="`,t.encoding||`UTF-8`,`" standalone="no" ?>
`,`<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" `,`"http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">
`)}_setSVGHeader(e,t){let n=t.width||`${this.width}`,r=t.height||`${this.height}`,i=o.NUM_FRACTION_DIGITS,a=t.viewBox,s;if(a)s=`viewBox="${a.x} ${a.y} ${a.width} ${a.height}" `;else if(this.svgViewportTransformation){let e=this.viewportTransform;s=`viewBox="${B(-e[4]/e[0],i)} ${B(-e[5]/e[3],i)} ${B(this.width/e[0],i)} ${B(this.height/e[3],i)}" `}else s=`viewBox="0 0 ${this.width} ${this.height}" `;e.push(`<svg `,`xmlns="http://www.w3.org/2000/svg" `,`xmlns:xlink="http://www.w3.org/1999/xlink" `,`version="1.1" `,`width="`,n,`" `,`height="`,r,`" `,s,`xml:space="preserve">
`,`<desc>Created with Fabric.js `,b,`</desc>
`,`<defs>
`,this.createSVGFontFacesMarkup(),this.createSVGRefElementsMarkup(),this.createSVGClipPathMarkup(t),`</defs>
`)}createSVGClipPathMarkup(e){let t=this.clipPath;return t?(t.clipPathId=`CLIPPATH_${je()}`,`<clipPath id="${t.clipPathId}" >\n${t.toClipPathSVG(e.reviver)}</clipPath>\n`):``}createSVGRefElementsMarkup(){return[`background`,`overlay`].map(e=>{let t=this[`${e}Color`];if(V(t)){let n=this[`${e}Vpt`],r=this.viewportTransform,i={isType:()=>!1,width:this.width/(n?r[0]:1),height:this.height/(n?r[3]:1)};return t.toSVG(i,{additionalTransform:n?nt(r):``})}}).join(``)}createSVGFontFacesMarkup(){let e=[],t={},n=o.fontPaths;this._objects.forEach(function t(n){e.push(n),Te(n)&&n._objects.forEach(t)}),e.forEach(e=>{if(!(r=e)||typeof r._renderText!=`function`)return;var r;let{styles:i,fontFamily:a}=e;!t[a]&&n[a]&&(t[a]=!0,i&&Object.values(i).forEach(e=>{Object.values(e).forEach(({fontFamily:e=``})=>{!t[e]&&n[e]&&(t[e]=!0)})}))});let r=Object.keys(t).map(e=>`\t\t@font-face {\n\t\t\tfont-family: '${e}';\n\t\t\tsrc: url('${n[e]}');\n\t\t}\n`).join(``);return r?`\t<style type="text/css"><![CDATA[\n${r}]]></style>\n`:``}_setSVGObjects(e,t){this.forEachObject(n=>{n.excludeFromExport||this._setSVGObject(e,n,t)})}_setSVGObject(e,t,n){e.push(t.toSVG(n))}_setSVGBgOverlayImage(e,t,n){let r=this[t];r&&!r.excludeFromExport&&r.toSVG&&e.push(r.toSVG(n))}_setSVGBgOverlayColor(e,t){let n=this[`${t}Color`];if(n)if(V(n)){let r=n.repeat||``,i=this.width,a=this.height,o=this[`${t}Vpt`]?nt(R(this.viewportTransform)):``;e.push(`<rect transform="${o} translate(${i/2},${a/2})" x="${n.offsetX-i/2}" y="${n.offsetY-a/2}" width="${r!==`repeat-y`&&r!==`no-repeat`||!it(n)?i:n.source.width}" height="${r!==`repeat-x`&&r!==`no-repeat`||!it(n)?a:n.source.height}" fill="url(#SVGID_${n.id})"></rect>\n`)}else e.push(`<rect x="0" y="0" width="100%" height="100%" `,`fill="`,n,`"`,`></rect>
`)}loadFromJSON(e,t,{signal:n}={}){if(!e)return Promise.reject(new c("`json` is undefined"));let{objects:r=[],...i}=typeof e==`string`?JSON.parse(e):e,{backgroundImage:a,background:o,overlayImage:s,overlay:l,clipPath:u}=i,d=this.renderOnAddRemove;return this.renderOnAddRemove=!1,Promise.all([Qe(r,{reviver:t,signal:n}),$e({backgroundImage:a,backgroundColor:o,overlayImage:s,overlayColor:l,clipPath:u},{signal:n})]).then(([e,t])=>(this.clear(),this.add(...e),this.set(i),this.set(t),this.renderOnAddRemove=d,this))}clone(e){let t=this.toObject(e);return this.cloneWithoutData().loadFromJSON(t)}cloneWithoutData(){let e=F(this);return new this.constructor(e)}toDataURL(e={}){let{format:t=`png`,quality:n=1,multiplier:r=1,enableRetinaScaling:i=!1}=e,a=r*(i?this.getRetinaScaling():1);return Pe(this.toCanvasElement(a,e),t,n)}toBlob(e={}){let{format:t=`png`,quality:n=1,multiplier:r=1,enableRetinaScaling:i=!1}=e,a=r*(i?this.getRetinaScaling():1);return Fe(this.toCanvasElement(a,e),t,n)}toCanvasElement(e=1,{width:t,height:n,left:r,top:i,filter:a}={}){let o=(t||this.width)*e,s=(n||this.height)*e,c=this.getZoom(),l=this.width,u=this.height,d=this.skipControlsDrawing,f=c*e,p=this.viewportTransform,m=[f,0,0,f,(p[4]-(r||0))*e,(p[5]-(i||0))*e],h=this.enableRetinaScaling,g=F({width:o,height:s}),_=a?this._objects.filter(e=>a(e)):this._objects;return this.enableRetinaScaling=!1,this.viewportTransform=m,this.width=o,this.height=s,this.skipControlsDrawing=!0,this.calcViewportBoundaries(),this.renderCanvas(g.getContext(`2d`),_),this.viewportTransform=p,this.width=l,this.height=u,this.calcViewportBoundaries(),this.enableRetinaScaling=h,this.skipControlsDrawing=d,g}dispose(){return!this.disposed&&this.elements.cleanupDOM({width:this.width,height:this.height}),ye.cancelByCanvas(this),this.disposed=!0,new Promise((e,t)=>{let n=()=>{this.destroy(),e(!0)};n.kill=t,this.__cleanupTask&&this.__cleanupTask.kill(`aborted`),this.destroyed?e(!1):this.nextRenderHandle?this.__cleanupTask=n:n()})}destroy(){this.destroyed=!0,this.cancelRequestedRender(),this.forEachObject(e=>e.dispose()),this._objects=[],this.backgroundImage&&this.backgroundImage.dispose(),this.backgroundImage=void 0,this.overlayImage&&this.overlayImage.dispose(),this.overlayImage=void 0,this.elements.dispose()}toString(){return`#<Canvas (${this.complexity()}): { objects: ${this._objects.length} }>`}};i(yt,`ownDefaults`,ft);const bt=[`touchstart`,`touchmove`,`touchend`],xt=e=>{let t=ot(e.target),n=function(e){let t=e.changedTouches;return t&&t[0]?t[0]:e}(e);return new N(n.clientX+t.left,n.clientY+t.top)},St=e=>bt.includes(e.type)||e.pointerType===`touch`,Ct=e=>{e.preventDefault(),e.stopPropagation()},wt=e=>{let t=0,n=0,r=0,i=0;for(let a=0,o=e.length;a<o;a++){let{x:o,y:s}=e[a];(o>r||!a)&&(r=o),(o<t||!a)&&(t=o),(s>i||!a)&&(i=s),(s<n||!a)&&(n=s)}return{left:t,top:n,width:r-t,height:i-n}},Tt=(e,t)=>{Dt(e,z(R(t),e.calcOwnMatrix()))},Et=(e,t)=>Dt(e,z(t,e.calcOwnMatrix())),Dt=(e,t)=>{let{translateX:n,translateY:r,scaleX:i,scaleY:a,...o}=He(t),s=new N(n,r);e.flipX=!1,e.flipY=!1,Object.assign(e,o),e.set({scaleX:i,scaleY:a}),e.setPositionByOrigin(s,E,E)},Ot=e=>{e.scaleX=1,e.scaleY=1,e.skewX=0,e.skewY=0,e.flipX=!1,e.flipY=!1,e.rotate(0)},kt=e=>({scaleX:e.scaleX,scaleY:e.scaleY,skewX:e.skewX,skewY:e.skewY,angle:e.angle,left:e.left,flipX:e.flipX,flipY:e.flipY,top:e.top}),At=(e,t,n)=>{let r=e/2,i=t/2,a=wt([new N(-r,-i),new N(r,-i),new N(-r,i),new N(r,i)].map(e=>e.transform(n)));return new N(a.width,a.height)},jt=(e=T,t=T)=>z(R(t),e),Mt=(e,t=T,n=T)=>e.transform(jt(t,n)),Nt=(e,t=T,n=T)=>e.transform(jt(t,n),!0),Pt=(e,t,n)=>{let r=jt(t,n);return Dt(e,z(r,e.calcOwnMatrix())),r},Ft={left:-.5,top:-.5,center:0,bottom:.5,right:.5},W=e=>typeof e==`string`?Ft[e]:e-.5,It=new N(1,0),Lt=new N,Rt=(e,t)=>e.rotate(t),zt=(e,t)=>new N(t).subtract(e),Bt=e=>e.distanceFrom(Lt),Vt=(e,t)=>Math.atan2(Gt(e,t),Kt(e,t)),Ht=e=>Vt(It,e),Ut=e=>e.eq(Lt)?e:e.scalarDivide(Bt(e)),Wt=(e,t=!0)=>Ut(new N(-e.y,e.x).scalarMultiply(t?1:-1)),Gt=(e,t)=>e.x*t.y-e.y*t.x,Kt=(e,t)=>e.x*t.x+e.y*t.y,qt=(e,t,n)=>{if(e.eq(t)||e.eq(n))return!0;let r=Gt(t,n),i=Gt(t,e),a=Gt(n,e);return r>=0?i>=0&&a<=0:!(i<=0&&a>=0)},Jt=`not-allowed`;function Yt(e){return W(e.originX)===W(`center`)&&W(e.originY)===W(`center`)}function Xt(e){return .5-W(e)}const Zt=(e,t)=>e[t],Qt=(e,t,n,r)=>({e,transform:t,pointer:new N(n,r)});function $t(e,t,n){let r=n,i=Ht(zt(Mt(e.getCenterPoint(),e.canvas.viewportTransform,void 0),r))+w;return Math.round(i%w/C)}function en({target:e,corner:t},n,r,i,a){var o;let s=e.controls[t],c=((o=e.canvas)==null?void 0:o.getZoom())||1,l=e.padding/c,u=function(e,t,n,r){let i=e.getRelativeCenterPoint(),a=n!==void 0&&r!==void 0?e.translateToGivenOrigin(i,E,E,n,r):new N(e.left,e.top);return(e.angle?t.rotate(-I(e.angle),i):t).subtract(a)}(e,new N(i,a),n,r);return u.x>=l&&(u.x-=l),u.x<=-l&&(u.x+=l),u.y>=l&&(u.y-=l),u.y<=l&&(u.y+=l),u.x-=s.offsetX,u.y-=s.offsetY,u}const tn=new RegExp(String.raw`[\0-\x1F\x7F;<>\\]|\/\*|\*\/|url\s*\(|expression\s*\(|(?:java|vb)script\s*:|data\s*:|@import\b`,`iu`),nn=e=>typeof e==`string`&&e.trim().length>0&&!tn.test(e),rn=(e,t=``)=>{let n=Number(e);return Number.isFinite(n)?`${n}`:t},an=(e,t=``)=>typeof e==`string`&&nn(e)?e:t,on=e=>e.replace(/\s+/g,` `),sn={aliceblue:`#F0F8FF`,antiquewhite:`#FAEBD7`,aqua:`#0FF`,aquamarine:`#7FFFD4`,azure:`#F0FFFF`,beige:`#F5F5DC`,bisque:`#FFE4C4`,black:`#000`,blanchedalmond:`#FFEBCD`,blue:`#00F`,blueviolet:`#8A2BE2`,brown:`#A52A2A`,burlywood:`#DEB887`,cadetblue:`#5F9EA0`,chartreuse:`#7FFF00`,chocolate:`#D2691E`,coral:`#FF7F50`,cornflowerblue:`#6495ED`,cornsilk:`#FFF8DC`,crimson:`#DC143C`,cyan:`#0FF`,darkblue:`#00008B`,darkcyan:`#008B8B`,darkgoldenrod:`#B8860B`,darkgray:`#A9A9A9`,darkgrey:`#A9A9A9`,darkgreen:`#006400`,darkkhaki:`#BDB76B`,darkmagenta:`#8B008B`,darkolivegreen:`#556B2F`,darkorange:`#FF8C00`,darkorchid:`#9932CC`,darkred:`#8B0000`,darksalmon:`#E9967A`,darkseagreen:`#8FBC8F`,darkslateblue:`#483D8B`,darkslategray:`#2F4F4F`,darkslategrey:`#2F4F4F`,darkturquoise:`#00CED1`,darkviolet:`#9400D3`,deeppink:`#FF1493`,deepskyblue:`#00BFFF`,dimgray:`#696969`,dimgrey:`#696969`,dodgerblue:`#1E90FF`,firebrick:`#B22222`,floralwhite:`#FFFAF0`,forestgreen:`#228B22`,fuchsia:`#F0F`,gainsboro:`#DCDCDC`,ghostwhite:`#F8F8FF`,gold:`#FFD700`,goldenrod:`#DAA520`,gray:`#808080`,grey:`#808080`,green:`#008000`,greenyellow:`#ADFF2F`,honeydew:`#F0FFF0`,hotpink:`#FF69B4`,indianred:`#CD5C5C`,indigo:`#4B0082`,ivory:`#FFFFF0`,khaki:`#F0E68C`,lavender:`#E6E6FA`,lavenderblush:`#FFF0F5`,lawngreen:`#7CFC00`,lemonchiffon:`#FFFACD`,lightblue:`#ADD8E6`,lightcoral:`#F08080`,lightcyan:`#E0FFFF`,lightgoldenrodyellow:`#FAFAD2`,lightgray:`#D3D3D3`,lightgrey:`#D3D3D3`,lightgreen:`#90EE90`,lightpink:`#FFB6C1`,lightsalmon:`#FFA07A`,lightseagreen:`#20B2AA`,lightskyblue:`#87CEFA`,lightslategray:`#789`,lightslategrey:`#789`,lightsteelblue:`#B0C4DE`,lightyellow:`#FFFFE0`,lime:`#0F0`,limegreen:`#32CD32`,linen:`#FAF0E6`,magenta:`#F0F`,maroon:`#800000`,mediumaquamarine:`#66CDAA`,mediumblue:`#0000CD`,mediumorchid:`#BA55D3`,mediumpurple:`#9370DB`,mediumseagreen:`#3CB371`,mediumslateblue:`#7B68EE`,mediumspringgreen:`#00FA9A`,mediumturquoise:`#48D1CC`,mediumvioletred:`#C71585`,midnightblue:`#191970`,mintcream:`#F5FFFA`,mistyrose:`#FFE4E1`,moccasin:`#FFE4B5`,navajowhite:`#FFDEAD`,navy:`#000080`,oldlace:`#FDF5E6`,olive:`#808000`,olivedrab:`#6B8E23`,orange:`#FFA500`,orangered:`#FF4500`,orchid:`#DA70D6`,palegoldenrod:`#EEE8AA`,palegreen:`#98FB98`,paleturquoise:`#AFEEEE`,palevioletred:`#DB7093`,papayawhip:`#FFEFD5`,peachpuff:`#FFDAB9`,peru:`#CD853F`,pink:`#FFC0CB`,plum:`#DDA0DD`,powderblue:`#B0E0E6`,purple:`#800080`,rebeccapurple:`#639`,red:`#F00`,rosybrown:`#BC8F8F`,royalblue:`#4169E1`,saddlebrown:`#8B4513`,salmon:`#FA8072`,sandybrown:`#F4A460`,seagreen:`#2E8B57`,seashell:`#FFF5EE`,sienna:`#A0522D`,silver:`#C0C0C0`,skyblue:`#87CEEB`,slateblue:`#6A5ACD`,slategray:`#708090`,slategrey:`#708090`,snow:`#FFFAFA`,springgreen:`#00FF7F`,steelblue:`#4682B4`,tan:`#D2B48C`,teal:`#008080`,thistle:`#D8BFD8`,tomato:`#FF6347`,turquoise:`#40E0D0`,violet:`#EE82EE`,wheat:`#F5DEB3`,white:`#FFF`,whitesmoke:`#F5F5F5`,yellow:`#FF0`,yellowgreen:`#9ACD32`},cn=(e,t,n)=>(n<0&&(n+=1),n>1&&--n,n<1/6?e+6*(t-e)*n:n<.5?t:n<2/3?e+(t-e)*(2/3-n)*6:e),ln=(e,t,n,r)=>{e/=255,t/=255,n/=255;let i=Math.max(e,t,n),a=Math.min(e,t,n),o,s,c=(i+a)/2;if(i===a)o=s=0;else{let r=i-a;switch(s=c>.5?r/(2-i-a):r/(i+a),i){case e:o=(t-n)/r+(t<n?6:0);break;case t:o=(n-e)/r+2;break;case n:o=(e-t)/r+4}o/=6}return[Math.round(360*o),Math.round(100*s),Math.round(100*c),r]},un=(e=`1`)=>parseFloat(e)/(e.endsWith(`%`)?100:1),dn=e=>Math.min(Math.round(e),255).toString(16).toUpperCase().padStart(2,`0`),fn=([e,t,n,r=1])=>{let i=Math.round(.3*e+.59*t+.11*n);return[i,i,i,r]};var G=class e{constructor(t){if(i(this,`isUnrecognised`,!1),t)if(t instanceof e)this.setSource([...t._source]);else if(Array.isArray(t)){let[e,n,r,i=1]=t;this.setSource([e,n,r,i])}else this.setSource(this._tryParsingColor(t));else this.setSource([0,0,0,1])}_tryParsingColor(t){return(t=t.toLowerCase())in sn&&(t=sn[t]),t===`transparent`?[255,255,255,0]:e.sourceFromHex(t)||e.sourceFromRgb(t)||e.sourceFromHsl(t)||(this.isUnrecognised=!0)&&[0,0,0,1]}getSource(){return this._source}setSource(e){this._source=e}toRgb(){let[e,t,n]=this.getSource();return`rgb(${e},${t},${n})`}toRgba(){return`rgba(${this.getSource().join(`,`)})`}toHsl(){let[e,t,n]=ln(...this.getSource());return`hsl(${e},${t}%,${n}%)`}toHsla(){let[e,t,n,r]=ln(...this.getSource());return`hsla(${e},${t}%,${n}%,${r})`}toHex(){return this.toHexa().slice(0,6)}toHexa(){let[e,t,n,r]=this.getSource();return`${dn(e)}${dn(t)}${dn(n)}${dn(Math.round(255*r))}`}getAlpha(){return this.getSource()[3]}setAlpha(e){return this._source[3]=e,this}toGrayscale(){return this.setSource(fn(this.getSource())),this}toBlackWhite(e){let[t,,,n]=fn(this.getSource()),r=t<(e||127)?0:255;return this.setSource([r,r,r,n]),this}overlayWith(t){t instanceof e||(t=new e(t));let n=this.getSource(),r=t.getSource(),[i,a,o]=n.map((e,t)=>Math.round(.5*e+.5*r[t]));return this.setSource([i,a,o,n[3]]),this}static fromRgb(t){return e.fromRgba(t)}static fromRgba(t){return new e(e.sourceFromRgb(t))}static sourceFromRgb(e){let t=on(e).match(/^rgba?\(\s?(\d{0,3}(?:\.\d+)?%?)\s?[\s|,]\s?(\d{0,3}(?:\.\d+)?%?)\s?[\s|,]\s?(\d{0,3}(?:\.\d+)?%?)\s?(?:\s?[,/]\s?(\d{0,3}(?:\.\d+)?%?)\s?)?\)$/i);if(t){let[e,n,r]=t.slice(1,4).map(e=>{let t=parseFloat(e);return e.endsWith(`%`)?Math.round(2.55*t):t});return[e,n,r,un(t[4])]}}static fromHsl(t){return e.fromHsla(t)}static fromHsla(t){return new e(e.sourceFromHsl(t))}static sourceFromHsl(t){let n=on(t).match(/^hsla?\(\s?([+-]?\d{0,3}(?:\.\d+)?(?:deg|turn|rad)?)\s?[\s|,]\s?(\d{0,3}(?:\.\d+)?%?)\s?[\s|,]\s?(\d{0,3}(?:\.\d+)?%?)\s?(?:\s?[,/]\s?(\d*(?:\.\d+)?%?)\s?)?\)$/i);if(!n)return;let r=(e.parseAngletoDegrees(n[1])%360+360)%360/360,i=parseFloat(n[2])/100,a=parseFloat(n[3])/100,o,s,c;if(i===0)o=s=c=a;else{let e=a<=.5?a*(i+1):a+i-a*i,t=2*a-e;o=cn(t,e,r+1/3),s=cn(t,e,r),c=cn(t,e,r-1/3)}return[Math.round(255*o),Math.round(255*s),Math.round(255*c),un(n[4])]}static fromHex(t){return new e(e.sourceFromHex(t))}static sourceFromHex(e){if(e.match(/^#?(([0-9a-f]){3,4}|([0-9a-f]{2}){3,4})$/i)){let t=e.slice(e.indexOf(`#`)+1),n;n=t.length<=4?t.split(``).map(e=>e+e):t.match(/.{2}/g);let[r,i,a,o=255]=n.map(e=>parseInt(e,16));return[r,i,a,o/255]}}static parseAngletoDegrees(e){let t=e.toLowerCase(),n=parseFloat(t);return t.includes(`rad`)?Ie(n):t.includes(`turn`)?360*n:n}};const pn=e=>{let t=[`instantiated_by_use`,`style`,`id`,`class`];switch(e){case`linearGradient`:return t.concat([`x1`,`y1`,`x2`,`y2`,`gradientUnits`,`gradientTransform`]);case`radialGradient`:return t.concat([`gradientUnits`,`gradientTransform`,`cx`,`cy`,`r`,`fx`,`fy`,`fr`]);case`stop`:return t.concat([`offset`,`stop-color`,`stop-opacity`])}return t},K=(e,t=16)=>{let n=/\D{0,2}$/.exec(e),r=parseFloat(e),i=o.DPI;switch(n==null?void 0:n[0]){case`mm`:return r*i/25.4;case`cm`:return r*i/2.54;case`in`:return r*i;case`pt`:return r*i/72;case`pc`:return r*i/72*12;case`em`:return r*t;default:return r}},mn=e=>{let[t,n]=e.trim().split(` `),[r,i]=(a=t)&&a!==`none`?[a.slice(1,4),a.slice(5,8)]:a===`none`?[a,a]:[`Mid`,`Mid`];var a;return{meetOrSlice:n||`meet`,alignX:r,alignY:i}},hn=(e,t,n=!0)=>{let r,i;if(t)if(t.toLive)r=`url(#SVGID_${U(t.id)})`;else{let e=String(t);if(nn(e)){let t=new G(e),n=t.getAlpha();r=t.toRgb(),n!==1&&(i=n.toString())}else r=new G(`black`).toRgb()}else r=`none`;return n?`${e}: ${r}; ${i?`${e}-opacity: ${i}; `:``}`:`${e}="${r}" ${i?`${e}-opacity="${i}" `:``}`};var gn=class{getSvgStyles(e){let t=this.fillRule==null?`nonzero`:an(this.fillRule),n=this.strokeWidth==null?`0`:rn(this.strokeWidth),r=this.strokeDashArray==null?te:this.strokeDashArray.every(e=>Number.isFinite(Number(e)))?this.strokeDashArray.join(` `):``,i=this.strokeDashOffset==null?`0`:rn(this.strokeDashOffset),a=this.strokeLineCap==null?`butt`:an(this.strokeLineCap),o=this.strokeLineJoin==null?`miter`:an(this.strokeLineJoin),s=this.strokeMiterLimit==null?`4`:rn(this.strokeMiterLimit),c=this.opacity==null?`1`:rn(this.opacity),l=this.visible?``:` visibility: hidden;`,u=e?``:this.getSvgFilter(),d=hn(j,this.fill);return[hn(he,this.stroke),n?`stroke-width: ${n}; `:``,r?`stroke-dasharray: ${r}; `:``,a?`stroke-linecap: ${a}; `:``,i?`stroke-dashoffset: ${i}; `:``,o?`stroke-linejoin: ${o}; `:``,s?`stroke-miterlimit: ${s}; `:``,d,t?`fill-rule: ${t}; `:``,c?`opacity: ${c};`:``,u,l].map(e=>U(e)).join(``)}getSvgFilter(){return this.shadow?`filter: url(#SVGID_${U(this.shadow.id)});`:``}getSvgCommons(){return[this.id?`id="${U(String(this.id))}" `:``,this.clipPath?`clip-path="url(#${U(this.clipPath.clipPathId)})" `:``].join(``)}getSvgTransform(e,t=``){return`transform="${nt(e?this.calcTransformMatrix():this.calcOwnMatrix())}${t}" `}_toSVG(e){return[``]}toSVG(e){return this._createBaseSVGMarkup(this._toSVG(e),{reviver:e})}toClipPathSVG(e){return`	`+this._createBaseClipPathSVGMarkup(this._toSVG(e),{reviver:e})}_createBaseClipPathSVGMarkup(e,{reviver:t,additionalTransform:n=``}={}){let r=[this.getSvgTransform(!0,n),this.getSvgCommons()].join(``),i=e.indexOf(`COMMON_PARTS`);return e[i]=r,t?t(e.join(``)):e.join(``)}_createBaseSVGMarkup(e,{noStyle:t,reviver:n,withShadow:r,additionalTransform:i}={}){let a=t?``:`style="${this.getSvgStyles()}" `,o=r?`style="${this.getSvgFilter()}" `:``,s=this.clipPath,c=this.strokeUniform?`vector-effect="non-scaling-stroke" `:``,l=s&&s.absolutePositioned,u=this.stroke,d=this.fill,f=this.shadow,p=[],m=e.indexOf(`COMMON_PARTS`),h;return s&&(s.clipPathId=`CLIPPATH_${je()}`,h=`<clipPath id="${s.clipPathId}" >\n${s.toClipPathSVG(n)}</clipPath>\n`),l&&p.push(`<g `,o,this.getSvgCommons(),` >
`),p.push(`<g `,this.getSvgTransform(!1),l?``:o+this.getSvgCommons(),` >
`),e[m]=[a,c,t?``:this.addPaintOrder(),` `,i?`transform="${i}" `:``].join(``),V(d)&&p.push(d.toSVG(this)),V(u)&&p.push(u.toSVG(this)),f&&p.push(f.toSVG(this)),s&&p.push(h),p.push(e.join(``)),p.push(`</g>
`),l&&p.push(`</g>
`),n?n(p.join(``)):p.join(``)}addPaintOrder(){return this.paintFirst===`fill`?``:` paint-order="${U(this.paintFirst)}" `}};function _n(e){return RegExp(`^(`+e.join(`|`)+`)\\b`,`i`)}const vn=`textDecorationThickness`,yn=`textDecorationColor`,bn=[`fontSize`,`fontWeight`,`fontFamily`,`fontStyle`],xn=[`underline`,`overline`,`linethrough`],Sn=[...bn,`lineHeight`,`text`,`charSpacing`,`textAlign`,`styles`,`path`,`pathStartOffset`,`pathSide`,`pathAlign`],Cn=[...Sn,...xn,`textBackgroundColor`,`direction`,vn,yn],wn=[...bn,...xn,he,`strokeWidth`,j,`deltaY`,`textBackgroundColor`,vn,yn],Tn={_reNewline:ne,_reSpacesAndTabs:/[ \t\r]/g,_reSpaceAndTab:/[ \t\r]/,_reWords:/\S+/g,fontSize:40,fontWeight:_e,fontFamily:`Times New Roman`,underline:!1,overline:!1,linethrough:!1,textAlign:D,fontStyle:_e,lineHeight:1.16,textBackgroundColor:``,stroke:null,shadow:null,path:void 0,pathStartOffset:0,pathSide:D,pathAlign:`baseline`,charSpacing:0,deltaY:0,direction:`ltr`,CACHE_FONT_SIZE:400,MIN_TEXT_WIDTH:2,superscript:{size:.6,baseline:-.35},subscript:{size:.6,baseline:.11},_fontSizeFraction:.222,offsets:{underline:.1,linethrough:-.28167,overline:-.81333},_fontSizeMult:1.13,[vn]:66.667},En=`justify`,Dn=String.raw`[-+]?(?:\d*\.\d+|\d+\.?)(?:[eE][-+]?\d+)?`,On=String.raw`(?:\s*,?\s+|\s*,\s*)`,kn=`http://www.w3.org/2000/svg`,An=RegExp(`(normal|italic)?\\s*(normal|small-caps)?\\s*(normal|bold|bolder|lighter|100|200|300|400|500|600|700|800|900)?\\s*(`+Dn+`(?:px|cm|mm|em|pt|pc|in)*)(?:\\/(normal|`+Dn+`))?\\s+(.*)`),jn={cx:D,x:D,r:`radius`,cy:`top`,y:`top`,display:`visible`,visibility:`visible`,transform:`transformMatrix`,"fill-opacity":`fillOpacity`,"fill-rule":`fillRule`,"font-family":`fontFamily`,"font-size":`fontSize`,"font-style":`fontStyle`,"font-weight":`fontWeight`,"letter-spacing":`charSpacing`,"paint-order":`paintFirst`,"stroke-dasharray":`strokeDashArray`,"stroke-dashoffset":`strokeDashOffset`,"stroke-linecap":`strokeLineCap`,"stroke-linejoin":`strokeLineJoin`,"stroke-miterlimit":`strokeMiterLimit`,"stroke-opacity":`strokeOpacity`,"stroke-width":`strokeWidth`,"text-decoration":`textDecoration`,"text-anchor":`textAnchor`,opacity:`opacity`,"clip-path":`clipPath`,"clip-rule":`clipRule`,"vector-effect":`strokeUniform`,"image-rendering":`imageSmoothing`,"text-decoration-thickness":vn,"text-decoration-color":yn},Mn=`font-size`,Nn=`clip-path`,Pn=_n([`path`,`circle`,`polygon`,`polyline`,`ellipse`,`rect`,`line`,`image`,`text`]),Fn=_n([`symbol`,`image`,`marker`,`pattern`,`view`,`svg`]),In=_n([`symbol`,`g`,`a`,`svg`,`clipPath`,`defs`]),Ln=new RegExp(String.raw`^\s*(${Dn})${On}(${Dn})${On}(${Dn})${On}(${Dn})\s*$`),Rn=`(-?\\d+(?:\\.\\d*)?(?:px)?(?:\\s?|$))?`,zn=RegExp(`(?:\\s|^)`+Rn+Rn+`(`+Dn+`?(?:px)?)?(?:\\s?|$)(?:$|\\s)`);var Bn=class e{constructor(t={}){let n=typeof t==`string`?e.parseShadow(t):t;Object.assign(this,e.ownDefaults,n),this.id=je()}static parseShadow(e){let t=e.trim(),[,n=0,r=0,i=0]=(zn.exec(t)||[]).map(e=>parseFloat(e)||0);return{color:(t.replace(zn,``)||`rgb(0,0,0)`).trim(),offsetX:n,offsetY:r,blur:i}}toString(){return[this.offsetX,this.offsetY,this.blur,this.color].join(`px `)}toSVG(e){let t=Rt(new N(this.offsetX,this.offsetY),I(-e.angle)),n=o.NUM_FRACTION_DIGITS,r=new G(this.color),i=40,a=40;return e.width&&e.height&&(i=100*B((Math.abs(t.x)+this.blur)/e.width,n)+20,a=100*B((Math.abs(t.y)+this.blur)/e.height,n)+20),e.flipX&&(t.x*=-1),e.flipY&&(t.y*=-1),`<filter id="SVGID_${U(this.id)}" y="-${a}%" height="${100+2*a}%" x="-${i}%" width="${100+2*i}%" >\n\t<feGaussianBlur in="SourceAlpha" stdDeviation="${B(this.blur?this.blur/2:0,n)}"></feGaussianBlur>\n\t<feOffset dx="${B(t.x,n)}" dy="${B(t.y,n)}" result="oBlur" ></feOffset>\n\t<feFlood flood-color="${r.toRgb()}" flood-opacity="${r.getAlpha()}"/>\n\t<feComposite in2="oBlur" operator="in" />\n\t<feMerge>\n\t\t<feMergeNode></feMergeNode>\n\t\t<feMergeNode in="SourceGraphic"></feMergeNode>\n\t</feMerge>\n</filter>\n`}toObject(){let t={color:this.color,blur:this.blur,offsetX:this.offsetX,offsetY:this.offsetY,affectStroke:this.affectStroke,nonScaling:this.nonScaling,type:this.constructor.type},n=e.ownDefaults;return this.includeDefaultValues?t:tt(t,(e,t)=>e!==n[t])}static async fromObject(e){return new this(e)}};i(Bn,`ownDefaults`,{color:`rgb(0,0,0)`,blur:0,offsetX:0,offsetY:0,affectStroke:!1,includeDefaultValues:!0,nonScaling:!1}),i(Bn,`type`,`shadow`),M.setClass(Bn,`shadow`);const Vn=(e,t,n)=>Math.max(e,Math.min(t,n)),Hn=[`top`,D,de,fe,`flipX`,`flipY`,`originX`,`originY`,`angle`,`opacity`,`globalCompositeOperation`,`shadow`,`visible`,pe,me],Un=[j,he,`strokeWidth`,`strokeDashArray`,`width`,`height`,`paintFirst`,`strokeUniform`,`strokeLineCap`,`strokeDashOffset`,`strokeLineJoin`,`strokeMiterLimit`,`backgroundColor`,`clipPath`],Wn={top:0,left:0,width:0,height:0,angle:0,flipX:!1,flipY:!1,scaleX:1,scaleY:1,minScaleLimit:0,skewX:0,skewY:0,originX:E,originY:E,strokeWidth:1,strokeUniform:!1,padding:0,opacity:1,paintFirst:j,fill:`rgb(0,0,0)`,fillRule:`nonzero`,stroke:null,strokeDashArray:null,strokeDashOffset:0,strokeLineCap:`butt`,strokeLineJoin:`miter`,strokeMiterLimit:4,globalCompositeOperation:`source-over`,backgroundColor:``,shadow:null,visible:!0,includeDefaultValues:!0,excludeFromExport:!1,objectCaching:!0,clipPath:void 0,inverted:!1,absolutePositioned:!1,centeredRotation:!0,centeredScaling:!1,dirty:!0};var Gn=t({defaultEasing:()=>Jn,easeInBack:()=>gr,easeInBounce:()=>br,easeInCirc:()=>ur,easeInCubic:()=>Yn,easeInElastic:()=>pr,easeInExpo:()=>sr,easeInOutBack:()=>vr,easeInOutBounce:()=>xr,easeInOutCirc:()=>fr,easeInOutCubic:()=>Zn,easeInOutElastic:()=>hr,easeInOutExpo:()=>lr,easeInOutQuad:()=>wr,easeInOutQuart:()=>er,easeInOutQuint:()=>rr,easeInOutSine:()=>or,easeInQuad:()=>Sr,easeInQuart:()=>Qn,easeInQuint:()=>tr,easeInSine:()=>ir,easeOutBack:()=>_r,easeOutBounce:()=>yr,easeOutCirc:()=>dr,easeOutCubic:()=>Xn,easeOutElastic:()=>mr,easeOutExpo:()=>cr,easeOutQuad:()=>Cr,easeOutQuart:()=>$n,easeOutQuint:()=>nr,easeOutSine:()=>ar});const Kn=(e,t,n,r)=>(e<Math.abs(t)?(e=t,r=n/4):r=t===0&&e===0?n/w*Math.asin(1):n/w*Math.asin(t/e),{a:e,c:t,p:n,s:r}),qn=(e,t,n,r,i)=>e*2**(10*--r)*Math.sin((r*i-t)*w/n),Jn=(e,t,n,r)=>-n*Math.cos(e/r*S)+n+t,Yn=(e,t,n,r)=>n*(e/r)**3+t,Xn=(e,t,n,r)=>n*((e/r-1)**3+1)+t,Zn=(e,t,n,r)=>(e/=r/2)<1?n/2*e**3+t:n/2*((e-2)**3+2)+t,Qn=(e,t,n,r)=>n*(e/=r)*e**3+t,$n=(e,t,n,r)=>-n*((e=e/r-1)*e**3-1)+t,er=(e,t,n,r)=>(e/=r/2)<1?n/2*e**4+t:-n/2*((e-=2)*e**3-2)+t,tr=(e,t,n,r)=>n*(e/r)**5+t,nr=(e,t,n,r)=>n*((e/r-1)**5+1)+t,rr=(e,t,n,r)=>(e/=r/2)<1?n/2*e**5+t:n/2*((e-2)**5+2)+t,ir=(e,t,n,r)=>-n*Math.cos(e/r*S)+n+t,ar=(e,t,n,r)=>n*Math.sin(e/r*S)+t,or=(e,t,n,r)=>-n/2*(Math.cos(Math.PI*e/r)-1)+t,sr=(e,t,n,r)=>e===0?t:n*2**(10*(e/r-1))+t,cr=(e,t,n,r)=>e===r?t+n:n*-(2**(-10*e/r)+1)+t,lr=(e,t,n,r)=>e===0?t:e===r?t+n:(e/=r/2)<1?n/2*2**(10*(e-1))+t:n/2*-(2**(-10*(e-1))+2)+t,ur=(e,t,n,r)=>-n*(Math.sqrt(1-(e/=r)*e)-1)+t,dr=(e,t,n,r)=>n*Math.sqrt(1-(e=e/r-1)*e)+t,fr=(e,t,n,r)=>(e/=r/2)<1?-n/2*(Math.sqrt(1-e**2)-1)+t:n/2*(Math.sqrt(1-(e-=2)*e)+1)+t,pr=(e,t,n,r)=>{let i=n,a=0;if(e===0)return t;if((e/=r)===1)return t+n;a||(a=.3*r);let{a:o,s,p:c}=Kn(i,n,a,1.70158);return-qn(o,s,c,e,r)+t},mr=(e,t,n,r)=>{let i=n,a=0;if(e===0)return t;if((e/=r)===1)return t+n;a||(a=.3*r);let{a:o,s,p:c,c:l}=Kn(i,n,a,1.70158);return o*2**(-10*e)*Math.sin((e*r-s)*w/c)+l+t},hr=(e,t,n,r)=>{let i=n,a=0;if(e===0)return t;if((e/=r/2)==2)return t+n;a||(a=.3*1.5*r);let{a:o,s,p:c,c:l}=Kn(i,n,a,1.70158);return e<1?-.5*qn(o,s,c,e,r)+t:o*2**(-10*--e)*Math.sin((e*r-s)*w/c)*.5+l+t},gr=(e,t,n,r,i=1.70158)=>n*(e/=r)*e*((i+1)*e-i)+t,_r=(e,t,n,r,i=1.70158)=>n*((e=e/r-1)*e*((i+1)*e+i)+1)+t,vr=(e,t,n,r,i=1.70158)=>(e/=r/2)<1?n/2*(e*e*((1+(i*=1.525))*e-i))+t:n/2*((e-=2)*e*((1+(i*=1.525))*e+i)+2)+t,yr=(e,t,n,r)=>(e/=r)<1/2.75?n*(7.5625*e*e)+t:e<2/2.75?n*(7.5625*(e-=1.5/2.75)*e+.75)+t:e<2.5/2.75?n*(7.5625*(e-=2.25/2.75)*e+.9375)+t:n*(7.5625*(e-=2.625/2.75)*e+.984375)+t,br=(e,t,n,r)=>n-yr(r-e,0,n,r)+t,xr=(e,t,n,r)=>e<r/2?.5*br(2*e,0,n,r)+t:.5*yr(2*e-r,0,n,r)+.5*n+t,Sr=(e,t,n,r)=>n*(e/=r)*e+t,Cr=(e,t,n,r)=>-n*(e/=r)*(e-2)+t,wr=(e,t,n,r)=>(e/=r/2)<1?n/2*e**2+t:-n/2*(--e*(e-2)-1)+t,Tr=()=>!1;var Er=class{constructor({startValue:e,byValue:t,duration:n=500,delay:r=0,easing:a=Jn,onStart:o=x,onChange:s=x,onComplete:c=x,abort:l=Tr,target:u}){i(this,`_state`,`pending`),i(this,`durationProgress`,0),i(this,`valueProgress`,0),this.tick=this.tick.bind(this),this.duration=n,this.delay=r,this.easing=a,this._onStart=o,this._onChange=s,this._onComplete=c,this._abort=l,this.target=u,this.startValue=e,this.byValue=t,this.value=this.startValue,this.endValue=Object.freeze(this.calculate(this.duration).value)}get state(){return this._state}isDone(){return this._state===`aborted`||this._state===`completed`}start(){let e=e=>{this._state===`pending`&&(this.startTime=e||+new Date,this._state=`running`,this._onStart(),this.tick(this.startTime))};this.register(),this.delay>0?this.timeout=_().setTimeout(()=>Oe(e),this.delay):Oe(e)}tick(e){let t=(e||+new Date)-this.startTime,n=Math.min(t,this.duration);this.durationProgress=n/this.duration;let{value:r,valueProgress:i}=this.calculate(n);this.value=Object.freeze(r),this.valueProgress=i,this._state!==`aborted`&&(this._abort(this.value,this.valueProgress,this.durationProgress)?(this._state=`aborted`,this.unregister()):t>=this.duration?(this.durationProgress=this.valueProgress=1,this._onChange(this.endValue,this.valueProgress,this.durationProgress),this._state=`completed`,this._onComplete(this.endValue,this.valueProgress,this.durationProgress),this.unregister(),this.timeout=null):(this._onChange(this.value,this.valueProgress,this.durationProgress),Oe(this.tick)))}register(){ye.push(this)}unregister(){ye.remove(this)}abort(){this._state=`aborted`,this.unregister(),this.timeout&&_().clearTimeout(this.timeout)}},Dr=class extends Er{constructor({startValue:e=0,endValue:t=100,...n}){super({...n,startValue:e,byValue:t-e})}calculate(e){let t=this.easing(e,this.startValue,this.byValue,this.duration);return{value:t,valueProgress:Math.abs((t-this.startValue)/this.byValue)}}},Or=class extends Er{constructor({startValue:e=[0],endValue:t=[100],...n}){super({...n,startValue:e,byValue:t.map((t,n)=>t-e[n])})}calculate(e){let t=this.startValue.map((t,n)=>this.easing(e,t,this.byValue[n],this.duration,n));return{value:t,valueProgress:Math.abs((t[0]-this.startValue[0])/this.byValue[0])}}};const kr=(e,t,n,r)=>t+n*(1-Math.cos(e/r*S)),Ar=e=>e&&((t,n,r)=>e(new G(t).toRgba(),n,r));var jr=class extends Er{constructor({startValue:e,endValue:t,easing:n=kr,onChange:r,onComplete:i,abort:a,...o}){let s=new G(e).getSource(),c=new G(t).getSource();super({...o,startValue:s,byValue:c.map((e,t)=>e-s[t]),easing:n,onChange:Ar(r),onComplete:Ar(i),abort:Ar(a)})}calculate(e){let[t,n,r,i]=this.startValue.map((t,n)=>this.easing(e,t,this.byValue[n],this.duration,n)),a=[...[t,n,r].map(Math.round),Vn(0,i,1)];return{value:a,valueProgress:a.map((e,t)=>this.byValue[t]===0?0:Math.abs((e-this.startValue[t])/this.byValue[t])).find(e=>e!==0)||0}}};function Mr(e){let t=(e=>Array.isArray(e.startValue)||Array.isArray(e.endValue))(e)?new Or(e):new Dr(e);return t.start(),t}function Nr(e){let t=new jr(e);return t.start(),t}var Pr=class e{constructor(e){this.status=e,this.points=[]}includes(e){return this.points.some(t=>t.eq(e))}append(...e){return this.points=this.points.concat(e.filter(e=>!this.includes(e))),this}static isPointContained(e,t,n,r=!1){if(t.eq(n))return e.eq(t);if(t.x===n.x)return e.x===t.x&&(r||e.y>=Math.min(t.y,n.y)&&e.y<=Math.max(t.y,n.y));if(t.y===n.y)return e.y===t.y&&(r||e.x>=Math.min(t.x,n.x)&&e.x<=Math.max(t.x,n.x));{let i=zt(t,n),a=zt(t,e).divide(i);return r?Math.abs(a.x)===Math.abs(a.y):a.x===a.y&&a.x>=0&&a.x<=1}}static isPointInPolygon(e,t){let n=new N(e).setX(Math.min(e.x-1,...t.map(e=>e.x))),r=0;for(let i=0;i<t.length;i++){let a=this.intersectSegmentSegment(t[i],t[(i+1)%t.length],e,n);if(a.includes(e))return!0;r+=Number(a.status===`Intersection`)}return r%2==1}static intersectLineLine(t,n,r,i,a=!0,o=!0){let s=n.x-t.x,c=n.y-t.y,l=i.x-r.x,u=i.y-r.y,d=t.x-r.x,f=t.y-r.y,p=l*f-u*d,m=s*f-c*d,h=u*s-l*c;if(h!==0){let n=p/h,r=m/h;return(a||0<=n&&n<=1)&&(o||0<=r&&r<=1)?new e(`Intersection`).append(new N(t.x+n*s,t.y+n*c)):new e}return new e(p===0||m===0?a||o||e.isPointContained(t,r,i)||e.isPointContained(n,r,i)||e.isPointContained(r,t,n)||e.isPointContained(i,t,n)?`Coincident`:void 0:`Parallel`)}static intersectSegmentLine(t,n,r,i){return e.intersectLineLine(t,n,r,i,!1,!0)}static intersectSegmentSegment(t,n,r,i){return e.intersectLineLine(t,n,r,i,!1,!1)}static intersectLinePolygon(t,n,r,i=!0){let a=new e,o=r.length;for(let s,c,l,u=0;u<o;u++){if(s=r[u],c=r[(u+1)%o],l=e.intersectLineLine(t,n,s,c,i,!1),l.status===`Coincident`)return l;a.append(...l.points)}return a.points.length>0&&(a.status=`Intersection`),a}static intersectSegmentPolygon(t,n,r){return e.intersectLinePolygon(t,n,r,!1)}static intersectPolygonPolygon(t,n){let r=new e,i=t.length,a=[];for(let o=0;o<i;o++){let s=t[o],c=t[(o+1)%i],l=e.intersectSegmentPolygon(s,c,n);l.status===`Coincident`?(a.push(l),r.append(s,c)):r.append(...l.points)}return a.length>0&&a.length===t.length?new e(`Coincident`):(r.points.length>0&&(r.status=`Intersection`),r)}static intersectPolygonRectangle(t,n,r){let i=n.min(r),a=n.max(r),o=new N(a.x,i.y),s=new N(i.x,a.y);return e.intersectPolygonPolygon(t,[i,o,a,s])}},Fr=class extends De{getX(){return this.getXY().x}setX(e){this.setXY(this.getXY().setX(e))}getY(){return this.getXY().y}setY(e){this.setXY(this.getXY().setY(e))}getRelativeX(){return this.left}setRelativeX(e){this.left=e}getRelativeY(){return this.top}setRelativeY(e){this.top=e}getXY(){let e=this.getRelativeXY();return this.group?L(e,this.group.calcTransformMatrix()):e}setXY(e,t,n){this.group&&(e=L(e,R(this.group.calcTransformMatrix()))),this.setRelativeXY(e,t,n)}getRelativeXY(){return new N(this.left,this.top)}setRelativeXY(e,t=this.originX,n=this.originY){this.setPositionByOrigin(e,t,n)}isStrokeAccountedForInDimensions(){return!1}getCoords(){let{tl:e,tr:t,br:n,bl:r}=this.aCoords||(this.aCoords=this.calcACoords()),i=[e,t,n,r];if(this.group){let e=this.group.calcTransformMatrix();return i.map(t=>L(t,e))}return i}intersectsWithRect(e,t){return Pr.intersectPolygonRectangle(this.getCoords(),e,t).status===`Intersection`}intersectsWithObject(e){let t=Pr.intersectPolygonPolygon(this.getCoords(),e.getCoords());return t.status===`Intersection`||t.status===`Coincident`||e.isContainedWithinObject(this)||this.isContainedWithinObject(e)}isContainedWithinObject(e){return this.getCoords().every(t=>e.containsPoint(t))}isContainedWithinRect(e,t){let{left:n,top:r,width:i,height:a}=this.getBoundingRect();return n>=e.x&&n+i<=t.x&&r>=e.y&&r+a<=t.y}isOverlapping(e){return this.intersectsWithObject(e)||this.isContainedWithinObject(e)||e.isContainedWithinObject(this)}containsPoint(e){return Pr.isPointInPolygon(e,this.getCoords())}isOnScreen(){if(!this.canvas)return!1;let{tl:e,br:t}=this.canvas.vptCoords;return!!this.getCoords().some(n=>n.x<=t.x&&n.x>=e.x&&n.y<=t.y&&n.y>=e.y)||!!this.intersectsWithRect(e,t)||this.containsPoint(e.midPointFrom(t))}isPartiallyOnScreen(){if(!this.canvas)return!1;let{tl:e,br:t}=this.canvas.vptCoords;return!!this.intersectsWithRect(e,t)||this.getCoords().every(n=>(n.x>=t.x||n.x<=e.x)&&(n.y>=t.y||n.y<=e.y))&&this.containsPoint(e.midPointFrom(t))}getBoundingRect(){return wt(this.getCoords())}getScaledWidth(){return this._getTransformedDimensions().x}getScaledHeight(){return this._getTransformedDimensions().y}scale(e){this._set(de,e),this._set(fe,e),this.setCoords()}scaleToWidth(e){let t=this.getBoundingRect().width/this.getScaledWidth();return this.scale(e/this.width/t)}scaleToHeight(e){let t=this.getBoundingRect().height/this.getScaledHeight();return this.scale(e/this.height/t)}getCanvasRetinaScaling(){var e;return((e=this.canvas)==null?void 0:e.getRetinaScaling())||1}getTotalAngle(){return this.group?Ie(ze(this.calcTransformMatrix())):this.angle}getViewportTransform(){var e;return((e=this.canvas)==null?void 0:e.viewportTransform)||T.concat()}calcACoords(){let e=We({angle:this.angle}),{x:t,y:n}=this.getRelativeCenterPoint(),r=z(Ue(t,n),e),i=this._getTransformedDimensions(),a=i.x/2,o=i.y/2;return{tl:L({x:-a,y:-o},r),tr:L({x:a,y:-o},r),bl:L({x:-a,y:o},r),br:L({x:a,y:o},r)}}setCoords(){this.aCoords=this.calcACoords()}transformMatrixKey(e=!1){let t=[];return!e&&this.group&&(t=this.group.transformMatrixKey(e)),t.push(this.top,this.left,this.width,this.height,this.scaleX,this.scaleY,this.angle,this.strokeWidth,this.skewX,this.skewY,+this.flipX,+this.flipY,W(this.originX),W(this.originY)),t}calcTransformMatrix(e=!1){let t=this.calcOwnMatrix();if(e||!this.group)return t;let n=this.transformMatrixKey(e),r=this.matrixCache;return r&&r.key.every((e,t)=>e===n[t])?r.value:(this.group&&(t=z(this.group.calcTransformMatrix(!1),t)),this.matrixCache={key:n,value:t},t)}calcOwnMatrix(){let e=this.transformMatrixKey(!0),t=this.ownMatrixCache;if(t&&t.key.every((t,n)=>t===e[n]))return t.value;let n=this.getRelativeCenterPoint(),r=Xe({angle:this.angle,translateX:n.x,translateY:n.y,scaleX:this.scaleX,scaleY:this.scaleY,skewX:this.skewX,skewY:this.skewY,flipX:this.flipX,flipY:this.flipY});return this.ownMatrixCache={key:e,value:r},r}_getNonTransformedDimensions(){return new N(this.width,this.height).scalarAdd(this.strokeWidth)}_calculateCurrentDimensions(e){var t;let n=(t=this.canvas)==null?void 0:t.viewportTransform,r=this._getTransformedDimensions(e);return n?r.multiply(new N(Be(n),Ve(n))).scalarAdd(2*this.padding):r.scalarAdd(2*this.padding)}_getTransformedDimensions(e={}){let t={scaleX:this.scaleX,scaleY:this.scaleY,skewX:this.skewX,skewY:this.skewY,width:this.width,height:this.height,strokeWidth:this.strokeWidth,...e},n=t.strokeWidth,r=n,i=0;this.strokeUniform&&(r=0,i=n);let a=t.width+r,o=t.height+r,s;return s=t.skewX===0&&t.skewY===0?new N(a*t.scaleX,o*t.scaleY):At(a,o,Ye(t)),s.scalarAdd(i)}translateToGivenOrigin(e,t,n,r,i){let a=e.x,o=e.y,s=W(r)-W(t),c=W(i)-W(n);if(s||c){let e=this._getTransformedDimensions();a+=s*e.x,o+=c*e.y}return new N(a,o)}translateToCenterPoint(e,t,n){if(t===`center`&&n===`center`)return e;let r=this.translateToGivenOrigin(e,t,n,E,E);return this.angle?r.rotate(I(this.angle),e):r}translateToOriginPoint(e,t,n){let r=this.translateToGivenOrigin(e,E,E,t,n);return this.angle?r.rotate(I(this.angle),e):r}getCenterPoint(){let e=this.getRelativeCenterPoint();return this.group?L(e,this.group.calcTransformMatrix()):e}getRelativeCenterPoint(){return this.translateToCenterPoint(new N(this.left,this.top),this.originX,this.originY)}getPointByOrigin(e,t){return this.getPositionByOrigin(e,t)}getPositionByOrigin(e,t){return this.translateToOriginPoint(this.getRelativeCenterPoint(),e,t)}setPositionByOrigin(e,t,n){let r=this.translateToCenterPoint(e,t,n),i=this.translateToOriginPoint(r,this.originX,this.originY);this.set({left:i.x,top:i.y})}_getLeftTopCoords(){return this.getPositionByOrigin(D,`top`)}positionByLeftTop(e){return this.setPositionByOrigin(e,D,`top`)}},Ir=class e extends Fr{static getDefaults(){return e.ownDefaults}get type(){let e=this.constructor.type;return e===`FabricObject`?`object`:e.toLowerCase()}set type(e){s(`warn`,`Setting type has no effect`,e)}constructor(t){super(),i(this,`_cacheContext`,null),Object.assign(this,e.ownDefaults),this.setOptions(t)}_createCacheCanvas(){this._cacheCanvas=P(),this._cacheContext=this._cacheCanvas.getContext(`2d`),this._updateCacheCanvas(),this.dirty=!0}_limitCacheSize(e){let t=e.width,n=e.height,r=o.maxCacheSideLimit,i=o.minCacheSideLimit;if(t<=r&&n<=r&&t*n<=o.perfLimitSizeTotal)return t<i&&(e.width=i),n<i&&(e.height=i),e;let a=t/n,[s,c]=y.limitDimsByArea(a),l=Vn(i,s,r),u=Vn(i,c,r);return t>l&&(e.zoomX/=t/l,e.width=l,e.capped=!0),n>u&&(e.zoomY/=n/u,e.height=u,e.capped=!0),e}_getCacheCanvasDimensions(){let e=this.getTotalObjectScaling(),t=this._getTransformedDimensions({skewX:0,skewY:0}),n=t.x*e.x/this.scaleX,r=t.y*e.y/this.scaleY;return{width:Math.ceil(n+2),height:Math.ceil(r+2),zoomX:e.x,zoomY:e.y,x:n,y:r}}_updateCacheCanvas(){let e=this._cacheCanvas,t=this._cacheContext,{width:n,height:r,zoomX:i,zoomY:a,x:o,y:s}=this._limitCacheSize(this._getCacheCanvasDimensions()),c=n!==e.width||r!==e.height,l=this.zoomX!==i||this.zoomY!==a;if(!e||!t)return!1;if(c||l){n!==e.width||r!==e.height?(e.width=n,e.height=r):(t.setTransform(1,0,0,1,0,0),t.clearRect(0,0,e.width,e.height));let c=o/2,l=s/2;return this.cacheTranslationX=Math.round(e.width/2-c)+c,this.cacheTranslationY=Math.round(e.height/2-l)+l,t.translate(this.cacheTranslationX,this.cacheTranslationY),t.scale(i,a),this.zoomX=i,this.zoomY=a,!0}return!1}setOptions(e={}){this._setOptions(e)}transform(e){let t=this.group&&!this.group._transformDone||this.group&&this.canvas&&e===this.canvas.contextTop,n=this.calcTransformMatrix(!t);e.transform(n[0],n[1],n[2],n[3],n[4],n[5])}getObjectScaling(){if(!this.group)return new N(Math.abs(this.scaleX),Math.abs(this.scaleY));let e=He(this.calcTransformMatrix());return new N(Math.abs(e.scaleX),Math.abs(e.scaleY))}getTotalObjectScaling(){let e=this.getObjectScaling();if(this.canvas){let t=this.canvas.getZoom(),n=this.getCanvasRetinaScaling();return e.scalarMultiply(t*n)}return e}getObjectOpacity(){let e=this.opacity;return this.group&&(e*=this.group.getObjectOpacity()),e}_constrainScale(e){return Math.abs(e)<this.minScaleLimit?e<0?-this.minScaleLimit:this.minScaleLimit:e===0?1e-4:e}_set(e,t){e!==`scaleX`&&e!==`scaleY`||(t=this._constrainScale(t)),e===`scaleX`&&t<0?(this.flipX=!this.flipX,t*=-1):e===`scaleY`&&t<0?(this.flipY=!this.flipY,t*=-1):e!==`shadow`||!t||t instanceof Bn||(t=new Bn(t));let n=this[e]!==t;return this[e]=t,n&&this.constructor.cacheProperties.includes(e)&&(this.dirty=!0),this.parent&&(this.dirty||n&&this.constructor.stateProperties.includes(e))&&this.parent._set(`dirty`,!0),this}isNotVisible(){return this.opacity===0||!this.width&&!this.height&&this.strokeWidth===0||!this.visible}render(e){this.isNotVisible()||this.canvas&&this.canvas.skipOffscreen&&!this.group&&!this.isOnScreen()||(e.save(),this._setupCompositeOperation(e),this.drawSelectionBackground(e),this.transform(e),this._setOpacity(e),this._setShadow(e),this.shouldCache()?(this.renderCache(),this.drawCacheOnCanvas(e)):(this._removeCacheCanvas(),this.drawObject(e,!1,{}),this.dirty=!1),e.restore())}drawSelectionBackground(e){}renderCache(e){if(e=e||{},this._cacheCanvas&&this._cacheContext||this._createCacheCanvas(),this.isCacheDirty()&&this._cacheContext){let{zoomX:t,zoomY:n,cacheTranslationX:r,cacheTranslationY:i}=this,{width:a,height:o}=this._cacheCanvas;this.drawObject(this._cacheContext,e.forClipping,{zoomX:t,zoomY:n,cacheTranslationX:r,cacheTranslationY:i,width:a,height:o,parentClipPaths:[]}),this.dirty=!1}}_removeCacheCanvas(){this._cacheCanvas=void 0,this._cacheContext=null}hasStroke(){return!!this.stroke&&this.stroke!==`transparent`&&this.strokeWidth!==0}hasFill(){return!!this.fill&&this.fill!==`transparent`}needsItsOwnCache(){return!!(this.paintFirst===`stroke`&&this.hasFill()&&this.hasStroke()&&this.shadow)||!!this.clipPath}shouldCache(){return this.ownCaching=this.objectCaching&&(!this.parent||!this.parent.isOnACache())||this.needsItsOwnCache(),this.ownCaching}willDrawShadow(){return!!this.shadow&&(this.shadow.offsetX!==0||this.shadow.offsetY!==0)}drawClipPathOnCache(e,t,n){e.save(),t.inverted?e.globalCompositeOperation=`destination-out`:e.globalCompositeOperation=`destination-in`,e.setTransform(1,0,0,1,0,0),e.drawImage(n,0,0),e.restore()}drawObject(e,t,n){let r=this.fill,i=this.stroke;t?(this.fill=`black`,this.stroke=``,this._setClippingProperties(e)):this._renderBackground(e),this.fire(`before:render`,{ctx:e}),this._render(e),this._drawClipPath(e,this.clipPath,n),this.fill=r,this.stroke=i}createClipPathLayer(e,t){let n=F(t),r=n.getContext(`2d`);if(r.translate(t.cacheTranslationX,t.cacheTranslationY),r.scale(t.zoomX,t.zoomY),e._cacheCanvas=n,t.parentClipPaths.forEach(e=>{e.transform(r)}),t.parentClipPaths.push(e),e.absolutePositioned){let e=R(this.calcTransformMatrix());r.transform(e[0],e[1],e[2],e[3],e[4],e[5])}return e.transform(r),e.drawObject(r,!0,t),n}_drawClipPath(e,t,n){if(!t)return;t._transformDone=!0;let r=this.createClipPathLayer(t,n);this.drawClipPathOnCache(e,t,r)}drawCacheOnCanvas(e){e.scale(1/this.zoomX,1/this.zoomY),e.drawImage(this._cacheCanvas,-this.cacheTranslationX,-this.cacheTranslationY)}isCacheDirty(e=!1){if(this.isNotVisible())return!1;let t=this._cacheCanvas,n=this._cacheContext;return!(!t||!n||e||!this._updateCacheCanvas())||!!(this.dirty||this.clipPath&&this.clipPath.absolutePositioned)&&(t&&n&&!e&&(n.save(),n.setTransform(1,0,0,1,0,0),n.clearRect(0,0,t.width,t.height),n.restore()),!0)}_renderBackground(e){if(!this.backgroundColor)return;let t=this._getNonTransformedDimensions();e.fillStyle=this.backgroundColor,e.fillRect(-t.x/2,-t.y/2,t.x,t.y),this._removeShadow(e)}_setOpacity(e){this.group&&!this.group._transformDone?e.globalAlpha=this.getObjectOpacity():e.globalAlpha*=this.opacity}_setStrokeStyles(e,t){let n=t.stroke;n&&(e.lineWidth=t.strokeWidth,e.lineCap=t.strokeLineCap,e.lineDashOffset=t.strokeDashOffset,e.lineJoin=t.strokeLineJoin,e.miterLimit=t.strokeMiterLimit,V(n)?n.gradientUnits===`percentage`||n.gradientTransform||n.patternTransform?this._applyPatternForTransformedGradient(e,n):(e.strokeStyle=n.toLive(e),this._applyPatternGradientTransform(e,n)):e.strokeStyle=t.stroke)}_setFillStyles(e,{fill:t}){t&&(V(t)?(e.fillStyle=t.toLive(e),this._applyPatternGradientTransform(e,t)):e.fillStyle=t)}_setClippingProperties(e){e.globalAlpha=1,e.strokeStyle=`transparent`,e.fillStyle=`#000000`}_setLineDash(e,t){t&&t.length!==0&&e.setLineDash(t)}_setShadow(e){if(!this.shadow)return;let t=this.shadow,n=this.canvas,r=this.getCanvasRetinaScaling(),[i,,,a]=(n==null?void 0:n.viewportTransform)||T,s=i*r,c=a*r,l=t.nonScaling?new N(1,1):this.getObjectScaling();e.shadowColor=t.color,e.shadowBlur=t.blur*o.browserShadowBlurConstant*(s+c)*(l.x+l.y)/4,e.shadowOffsetX=t.offsetX*s*l.x,e.shadowOffsetY=t.offsetY*c*l.y}_removeShadow(e){this.shadow&&(e.shadowColor=``,e.shadowBlur=e.shadowOffsetX=e.shadowOffsetY=0)}_applyPatternGradientTransform(e,t){if(!V(t))return{offsetX:0,offsetY:0};let n=t.gradientTransform||t.patternTransform,r=-this.width/2+t.offsetX||0,i=-this.height/2+t.offsetY||0;return t.gradientUnits===`percentage`?e.transform(this.width,0,0,this.height,r,i):e.transform(1,0,0,1,r,i),n&&e.transform(n[0],n[1],n[2],n[3],n[4],n[5]),{offsetX:r,offsetY:i}}_renderPaintInOrder(e){this.paintFirst===`stroke`?(this._renderStroke(e),this._renderFill(e)):(this._renderFill(e),this._renderStroke(e))}_render(e){}_renderFill(e){this.fill&&(e.save(),this._setFillStyles(e,this),this.fillRule===`evenodd`?e.fill(`evenodd`):e.fill(),e.restore())}_renderStroke(e){if(this.stroke&&this.strokeWidth!==0){if(this.shadow&&!this.shadow.affectStroke&&this._removeShadow(e),e.save(),this.strokeUniform){let t=this.getObjectScaling();e.scale(1/t.x,1/t.y)}this._setLineDash(e,this.strokeDashArray),this._setStrokeStyles(e,this),e.stroke(),e.restore()}}_applyPatternForTransformedGradient(e,t){var n;let r=this._limitCacheSize(this._getCacheCanvasDimensions()),i=this.getCanvasRetinaScaling(),a=r.x/this.scaleX/i,o=r.y/this.scaleY/i,s=F({width:Math.ceil(a),height:Math.ceil(o)}),c=s.getContext(`2d`);c&&(c.beginPath(),c.moveTo(0,0),c.lineTo(a,0),c.lineTo(a,o),c.lineTo(0,o),c.closePath(),c.translate(a/2,o/2),c.scale(r.zoomX/this.scaleX/i,r.zoomY/this.scaleY/i),this._applyPatternGradientTransform(c,t),c.fillStyle=t.toLive(e),c.fill(),e.translate(-this.width/2-this.strokeWidth/2,-this.height/2-this.strokeWidth/2),e.scale(i*this.scaleX/r.zoomX,i*this.scaleY/r.zoomY),e.strokeStyle=(n=c.createPattern(s,`no-repeat`))==null?``:n)}_findCenterFromElement(){return new N(this.left+this.width/2,this.top+this.height/2)}clone(e){let t=this.toObject(e);return this.constructor.fromObject(t)}cloneAsImage(e){let t=this.toCanvasElement(e);return new(M.getClass(`image`))(t)}toCanvasElement(e={}){let t=kt(this),n=this.group,r=this.shadow,i=Math.abs,a=e.enableRetinaScaling?v():1,o=(e.multiplier||1)*a,s=e.canvasProvider||(e=>new yt(e,{enableRetinaScaling:!1,renderOnAddRemove:!1,skipOffscreen:!1}));delete this.group,e.withoutTransform&&Ot(this),e.withoutShadow&&(this.shadow=null),e.viewportTransform&&Pt(this,this.getViewportTransform()),this.setCoords();let c=P(),l=this.getBoundingRect(),u=this.shadow,d=new N;if(u){let e=u.blur,t=u.nonScaling?new N(1,1):this.getObjectScaling();d.x=2*Math.round(i(u.offsetX)+e)*i(t.x),d.y=2*Math.round(i(u.offsetY)+e)*i(t.y)}let f=l.width+d.x,p=l.height+d.y;c.width=Math.ceil(f),c.height=Math.ceil(p);let m=s(c);e.format===`jpeg`&&(m.backgroundColor=`#fff`),this.setPositionByOrigin(new N(m.width/2,m.height/2),E,E);let h=this.canvas;m._objects=[this],this.set(`canvas`,m),this.setCoords();let g=m.toCanvasElement(o||1,e);return this.set(`canvas`,h),this.shadow=r,n&&(this.group=n),this.set(t),this.setCoords(),m._objects=[],m.destroy(),g}toDataURL(e={}){return Pe(this.toCanvasElement(e),e.format||`png`,e.quality||1)}toBlob(e={}){return Fe(this.toCanvasElement(e),e.format||`png`,e.quality||1)}isType(...e){return e.includes(this.constructor.type)||e.includes(this.type)}complexity(){return 1}toJSON(){return this.toObject()}rotate(e){let{centeredRotation:t,originX:n,originY:r}=this;if(t){let{x:e,y:t}=this.getRelativeCenterPoint();this.originX=E,this.originY=E,this.left=e,this.top=t}if(this.set(`angle`,e),t){let{x:e,y:t}=this.getPositionByOrigin(n,r);this.left=e,this.top=t,this.originX=n,this.originY=r}}setOnGroup(){}_setupCompositeOperation(e){this.globalCompositeOperation&&(e.globalCompositeOperation=this.globalCompositeOperation)}dispose(){ye.cancelByTarget(this),this.off(),this._set(`canvas`,void 0),this._cacheCanvas&&h().dispose(this._cacheCanvas),this._cacheCanvas=void 0,this._cacheContext=null}animate(e,t){return Object.entries(e).reduce((e,[n,r])=>(e[n]=this._animate(n,r,t),e),{})}_animate(e,t,n={}){let r=e.split(`.`),i=this.constructor.colorProperties.includes(r[r.length-1]),{abort:a,startValue:o,onChange:s,onComplete:c}=n,l={...n,target:this,startValue:o==null?r.reduce((e,t)=>e[t],this):o,endValue:t,abort:a==null?void 0:a.bind(this),onChange:(e,t,n)=>{r.reduce((t,n,i)=>(i===r.length-1&&(t[n]=e),t[n]),this),s&&s(e,t,n)},onComplete:(e,t,n)=>{this.setCoords(),c&&c(e,t,n)}};return i?Nr(l):Mr(l)}isDescendantOf(e){let{parent:t,group:n}=this;return t===e||n===e||!!t&&t.isDescendantOf(e)||!!n&&n!==t&&n.isDescendantOf(e)}getAncestors(){let e=[],t=this;do t=t.parent,t&&e.push(t);while(t);return e}findCommonAncestors(e){if(this===e)return{fork:[],otherFork:[],common:[this,...this.getAncestors()]};let t=this.getAncestors(),n=e.getAncestors();if(t.length===0&&n.length>0&&this===n[n.length-1])return{fork:[],otherFork:[e,...n.slice(0,n.length-1)],common:[this]};for(let r,i=0;i<t.length;i++){if(r=t[i],r===e)return{fork:[this,...t.slice(0,i)],otherFork:[],common:t.slice(i)};for(let a=0;a<n.length;a++){if(this===n[a])return{fork:[],otherFork:[e,...n.slice(0,a)],common:[this,...t]};if(r===n[a])return{fork:[this,...t.slice(0,i)],otherFork:[e,...n.slice(0,a)],common:t.slice(i)}}}return{fork:[this,...t],otherFork:[e,...n],common:[]}}hasCommonAncestors(e){let t=this.findCommonAncestors(e);return t&&!!t.common.length}isInFrontOf(e){if(this===e)return;let t=this.findCommonAncestors(e);if(t.fork.includes(e))return!0;if(t.otherFork.includes(this))return!1;let n=t.common[0]||this.canvas;if(!n)return;let r=t.fork.pop(),i=t.otherFork.pop(),a=n._objects.indexOf(r),o=n._objects.indexOf(i);return a>-1&&a>o}toObject(t=[]){let n=t.concat(e.customProperties,this.constructor.customProperties||[]),r,i=o.NUM_FRACTION_DIGITS,{clipPath:a,fill:s,stroke:c,shadow:l,strokeDashArray:u,left:d,top:f,originX:p,originY:m,width:h,height:g,strokeWidth:_,strokeLineCap:v,strokeDashOffset:y,strokeLineJoin:x,strokeUniform:S,strokeMiterLimit:C,scaleX:w,scaleY:ee,angle:T,flipX:E,flipY:D,opacity:O,visible:k,backgroundColor:te,fillRule:ne,paintFirst:re,globalCompositeOperation:ie,skewX:ae,skewY:oe}=this;a&&!a.excludeFromExport&&(r=a.toObject(n.concat(`inverted`,`absolutePositioned`)));let A=e=>B(e,i),se={...et(this,n),type:this.constructor.type,version:b,originX:p,originY:m,left:A(d),top:A(f),width:A(h),height:A(g),fill:rt(s)?s.toObject():s,stroke:rt(c)?c.toObject():c,strokeWidth:A(_),strokeDashArray:u&&u.concat(),strokeLineCap:v,strokeDashOffset:y,strokeLineJoin:x,strokeUniform:S,strokeMiterLimit:A(C),scaleX:A(w),scaleY:A(ee),angle:A(T),flipX:E,flipY:D,opacity:A(O),shadow:l&&l.toObject(),visible:k,backgroundColor:te,fillRule:ne,paintFirst:re,globalCompositeOperation:ie,skewX:A(ae),skewY:A(oe),...r?{clipPath:r}:null};return this.includeDefaultValues?se:this._removeDefaultValues(se)}toDatalessObject(e){return this.toObject(e)}_removeDefaultValues(e){let t=this.constructor.getDefaults(),n=Object.keys(t).length>0?t:Object.getPrototypeOf(this);return tt(e,(e,t)=>{if(t===`left`||t===`top`||t===`type`)return!0;let r=n[t];return e!==r&&!(Array.isArray(e)&&Array.isArray(r)&&e.length===0&&r.length===0)})}toString(){return`#<${this.constructor.type}>`}static _fromObject({type:e,...t},{extraParam:n,...r}={}){return $e(t,r).then(e=>n?(delete e[n],new this(t[n],e)):new this(e))}static fromObject(e,t){return this._fromObject(e,t)}};i(Ir,`stateProperties`,Hn),i(Ir,`cacheProperties`,Un),i(Ir,`ownDefaults`,Wn),i(Ir,`type`,`FabricObject`),i(Ir,`colorProperties`,[j,he,`backgroundColor`]),i(Ir,`customProperties`,[]),M.setClass(Ir),M.setClass(Ir,`object`);const Lr=(e,t)=>{var n;let{transform:{target:r}}=t;(n=r.canvas)==null||n.fire(`object:${e}`,{...t,target:r}),r.fire(e,t)},Rr=(e,t,n)=>(r,i,a,o)=>{let s=t(r,i,a,o);return s&&Lr(e,{...Qt(r,i,a,o),...n}),s};function zr(e){return(t,n,r,i)=>{let{target:a,originX:o,originY:s}=n,c=a.getPositionByOrigin(o,s),l=e(t,n,r,i);return a.setPositionByOrigin(c,n.originX,n.originY),l}}const Br=(e,t,n,r)=>(i,a,o,s)=>{let c=en(a,a.originX,a.originY,o,s)[n],l=W(a[t]);if(l===0||l>0&&c<0||l<0&&c>0){let{target:t}=a,n=t.strokeWidth/(t.strokeUniform?t[r]:1),i=Yt(a)?2:1,o=t[e],s=Math.abs(c*i/t[r])-n;return t.set(e,Math.max(s,1)),o!==t[e]}return!1},Vr=Br(`width`,`originX`,`x`,`scaleX`),Hr=Br(`height`,`originY`,`y`,`scaleY`),Ur=Rr(se,zr(Vr)),Wr=Rr(se,zr(Hr));function Gr(e,t,n,r,i){e.save();let{stroke:a,xSize:o,ySize:s,opName:c}=this.commonRenderProps(e,t,n,i,r),l=o;o>s?e.scale(1,s/o):s>o&&(l=s,e.scale(o/s,1)),e.beginPath(),e.arc(0,0,l/2,0,w,!1),e[c](),a&&e.stroke(),e.restore()}function Kr(e,t,n,r,i){e.save();let{stroke:a,xSize:o,ySize:s,opName:c}=this.commonRenderProps(e,t,n,i,r),l=o/2,u=s/2;e[`${c}Rect`](-l,-u,o,s),a&&e.strokeRect(-l,-u,o,s),e.restore()}var q=class{constructor(e){i(this,`visible`,!0),i(this,`actionName`,ue),i(this,`angle`,0),i(this,`x`,0),i(this,`y`,0),i(this,`offsetX`,0),i(this,`offsetY`,0),i(this,`sizeX`,0),i(this,`sizeY`,0),i(this,`touchSizeX`,0),i(this,`touchSizeY`,0),i(this,`cursorStyle`,`crosshair`),i(this,`withConnection`,!1),Object.assign(this,e)}getTransformAnchorPoint(){var e;return(e=this.transformAnchorPoint)==null?new N(.5-this.x,.5-this.y):e}shouldActivate(e,t,n,{tl:r,tr:i,br:a,bl:o}){var s;return((s=t.canvas)==null?void 0:s.getActiveObject())===t&&t.isControlVisible(e)&&Pr.isPointInPolygon(n,[r,i,a,o])}getActionHandler(e,t,n){return this.actionHandler}getMouseDownHandler(e,t,n){return this.mouseDownHandler}getMouseUpHandler(e,t,n){return this.mouseUpHandler}cursorStyleHandler(e,t,n,r){return t.cursorStyle}getActionName(e,t,n){return t.actionName}getVisibility(e,t){var n,r;return(n=(r=e._controlsVisibility)==null?void 0:r[t])==null?this.visible:n}setVisibility(e,t,n){this.visible=e}positionHandler(e,t,n,r){return new N(this.x*e.x+this.offsetX,this.y*e.y+this.offsetY).transform(t)}calcCornerCoords(e,t,n,r,i,a){let o=Re([Ue(n,r),We({angle:e}),Ge((i?this.touchSizeX:this.sizeX)||t,(i?this.touchSizeY:this.sizeY)||t)]);return{tl:new N(-.5,-.5).transform(o),tr:new N(.5,-.5).transform(o),br:new N(.5,.5).transform(o),bl:new N(-.5,.5).transform(o)}}commonRenderProps(e,t,n,r,i={}){let{cornerSize:a,cornerColor:o,transparentCorners:s,cornerStrokeColor:c}=i,l=a||r.cornerSize,u=this.sizeX||l,d=this.sizeY||l,f=s===void 0?r.transparentCorners:s,p=f?he:j,m=c||r.cornerStrokeColor,h=!f&&!!m;return e.fillStyle=o||r.cornerColor||``,e.strokeStyle=m||``,e.translate(t,n),e.rotate(I(r.getTotalAngle())),{stroke:h,xSize:u,ySize:d,transparentCorners:f,opName:p}}render(e,t,n,r,i){((r=r||{}).cornerStyle||i.cornerStyle)===`circle`?Gr.call(this,e,t,n,r,i):Kr.call(this,e,t,n,r,i)}};const qr=(e,t,n)=>n.lockRotation?Jt:t.cursorStyle,Jr=Rr(ae,zr((e,{target:t,ex:n,ey:r,theta:i,originX:a,originY:o},s,c)=>{let l=t.getPositionByOrigin(a,o);if(Zt(t,`lockRotation`))return!1;let u=Math.atan2(r-l.y,n-l.x),d=Ie(Math.atan2(c-l.y,s-l.x)-u+i);if(t.snapAngle&&t.snapAngle>0){let e=t.snapAngle,n=t.snapThreshold||e,r=Math.ceil(d/e)*e,i=Math.floor(d/e)*e;Math.abs(d-i)<n?d=i:Math.abs(d-r)<n&&(d=r)}d<0&&(d=360+d),d%=360;let f=t.angle!==d;return t.angle=d,f}));function Yr(e,t){let n=t.canvas,r=e[n.uniScaleKey];return n.uniformScaling&&!r||!n.uniformScaling&&r}function Xr(e,t,n){let r=Zt(e,`lockScalingX`),i=Zt(e,`lockScalingY`);if(r&&i||!t&&(r||i)&&n||r&&t===`x`||i&&t===`y`)return!0;let{width:a,height:o,strokeWidth:s}=e;return a===0&&s===0&&t!==`y`||o===0&&s===0&&t!==`x`}const Zr=[`e`,`se`,`s`,`sw`,`w`,`nw`,`n`,`ne`,`e`],Qr=(e,t,n,r)=>{let i=Yr(e,n);return Xr(n,t.x!==0&&t.y===0?`x`:t.x===0&&t.y!==0?`y`:``,i)?Jt:`${Zr[$t(n,0,r)]}-resize`};function $r(e,t,n,r,i={}){let a=t.target,o=i.by,s=Yr(e,a),c,l,u,d,f,p;if(Xr(a,o,s))return!1;if(t.gestureScale)l=t.scaleX*t.gestureScale,u=t.scaleY*t.gestureScale;else{if(c=en(t,t.originX,t.originY,n,r),f=o===`y`?1:Math.sign(c.x||t.signX||1),p=o===`x`?1:Math.sign(c.y||t.signY||1),t.signX||(t.signX=f),t.signY||(t.signY=p),Zt(a,`lockScalingFlip`)&&(t.signX!==f||t.signY!==p))return!1;if(d=a._getTransformedDimensions(),s&&!o){let e=Math.abs(c.x)+Math.abs(c.y),{original:n}=t,r=e/(Math.abs(d.x*n.scaleX/a.scaleX)+Math.abs(d.y*n.scaleY/a.scaleY));l=n.scaleX*r,u=n.scaleY*r}else l=Math.abs(c.x*a.scaleX/d.x),u=Math.abs(c.y*a.scaleY/d.y);Yt(t)&&(l*=2,u*=2),t.signX!==f&&o!==`y`&&(t.originX=Xt(t.originX),l*=-1,t.signX=f),t.signY!==p&&o!==`x`&&(t.originY=Xt(t.originY),u*=-1,t.signY=p)}let m=a.scaleX,h=a.scaleY;return o?(o===`x`&&a.set(`scaleX`,l),o===`y`&&a.set(`scaleY`,u)):(!Zt(a,`lockScalingX`)&&a.set(`scaleX`,l),!Zt(a,`lockScalingY`)&&a.set(`scaleY`,u)),m!==a.scaleX||h!==a.scaleY}const ei=Rr(ie,zr((e,t,n,r)=>$r(e,t,n,r))),ti=Rr(ie,zr((e,t,n,r)=>$r(e,t,n,r,{by:`x`}))),ni=Rr(ie,zr((e,t,n,r)=>$r(e,t,n,r,{by:`y`}))),ri={x:{counterAxis:`y`,scale:de,skew:pe,lockSkewing:`lockSkewingX`,origin:`originX`,flip:`flipX`},y:{counterAxis:`x`,scale:fe,skew:me,lockSkewing:`lockSkewingY`,origin:`originY`,flip:`flipY`}},ii=[`ns`,`nesw`,`ew`,`nwse`],ai=(e,t,n,r)=>t.x!==0&&Zt(n,`lockSkewingY`)||t.y!==0&&Zt(n,`lockSkewingX`)?Jt:`${ii[$t(n,0,r)%4]}-resize`;function oi(e,t,n,r,i){let{target:a}=n,{counterAxis:o,origin:s,lockSkewing:c,skew:l,flip:u}=ri[e];if(Zt(a,c))return!1;let{origin:d,flip:f}=ri[o],p=W(n[d])*(a[f]?-1:1),m=-Math.sign(p)*(a[u]?-1:1),h=-(a[l]===0&&en(n,`center`,`center`,r,i)[e]>0||a[l]>0?1:-1)*m*.5+.5;return Rr(A,zr((t,n,r,i)=>function(e,{target:t,ex:n,ey:r,skewingSide:i,...a},o){let{skew:s}=ri[e],c=o.subtract(new N(n,r)).divide(new N(t.scaleX,t.scaleY))[e],l=t[s],u=a[s],d=Math.tan(I(u)),f=e===`y`?t._getTransformedDimensions({scaleX:1,scaleY:1,skewX:0}).x:t._getTransformedDimensions({scaleX:1,scaleY:1}).y,p=2*c*i/Math.max(f,1)+d,m=Ie(Math.atan(p));t.set(s,m);let h=l!==t[s];if(h&&e===`y`){let{skewX:e,scaleX:n}=t,r=t._getTransformedDimensions({skewY:l}),i=t._getTransformedDimensions(),a=e===0?1:r.x/i.x;a!==1&&t.set(`scaleX`,a*n)}return h}(e,n,new N(r,i))))(t,{...n,[s]:h,skewingSide:m},r,i)}const si=(e,t,n,r)=>oi(`x`,e,t,n,r),ci=(e,t,n,r)=>oi(`y`,e,t,n,r);function li(e,t){return e[t.canvas.altActionKey]}const ui=(e,t,n)=>{let r=li(e,n);return t.x===0?r?pe:fe:t.y===0?r?me:de:``},di=(e,t,n,r)=>li(e,n)?ai(0,t,n,r):Qr(e,t,n,r),fi=(e,t,n,r)=>li(e,t.target)?ci(e,t,n,r):ti(e,t,n,r),pi=(e,t,n,r)=>li(e,t.target)?si(e,t,n,r):ni(e,t,n,r),mi=()=>({ml:new q({x:-.5,y:0,cursorStyleHandler:di,actionHandler:fi,getActionName:ui}),mr:new q({x:.5,y:0,cursorStyleHandler:di,actionHandler:fi,getActionName:ui}),mb:new q({x:0,y:.5,cursorStyleHandler:di,actionHandler:pi,getActionName:ui}),mt:new q({x:0,y:-.5,cursorStyleHandler:di,actionHandler:pi,getActionName:ui}),tl:new q({x:-.5,y:-.5,cursorStyleHandler:Qr,actionHandler:ei}),tr:new q({x:.5,y:-.5,cursorStyleHandler:Qr,actionHandler:ei}),bl:new q({x:-.5,y:.5,cursorStyleHandler:Qr,actionHandler:ei}),br:new q({x:.5,y:.5,cursorStyleHandler:Qr,actionHandler:ei}),mtr:new q({x:0,y:-.5,actionHandler:Jr,cursorStyleHandler:qr,offsetY:-40,withConnection:!0,actionName:oe})}),hi=()=>({mr:new q({x:.5,y:0,actionHandler:Ur,cursorStyleHandler:di,actionName:se}),ml:new q({x:-.5,y:0,actionHandler:Ur,cursorStyleHandler:di,actionName:se})}),gi=()=>({...mi(),...hi()});var _i=class e extends Ir{static getDefaults(){return{...super.getDefaults(),...e.ownDefaults}}constructor(t){super(),Object.assign(this,this.constructor.createControls(),e.ownDefaults),this.setOptions(t)}static createControls(){return{controls:mi()}}_updateCacheCanvas(){let e=this.canvas;if(this.noScaleCache&&e&&e._currentTransform){let t=e._currentTransform,n=t.target,r=t.action;if(this===n&&r&&r.startsWith(`scale`))return!1}return super._updateCacheCanvas()}getActiveControl(){let e=this.__corner;return e?{key:e,control:this.controls[e],coord:this.oCoords[e]}:void 0}findControl(e,t=!1){if(!this.hasControls||!this.canvas)return;this.__corner=void 0;let n=Object.entries(this.oCoords);for(let r=n.length-1;r>=0;r--){let[i,a]=n[r],o=this.controls[i];if(o.shouldActivate(i,this,e,t?a.touchCorner:a.corner))return this.__corner=i,{key:i,control:o,coord:this.oCoords[i]}}}calcOCoords(){let e=this.getViewportTransform(),t=Be(e),n=Ve(e),r=this.getCenterPoint(),i=z(z(e,z(Ue(r.x,r.y),We({angle:this.getTotalAngle()-(this.group&&this.flipX?180:0)}))),[1/t,0,0,1/n,0,0]),a=this.group?He(this.calcTransformMatrix()):void 0;a&&(a.scaleX=Math.abs(a.scaleX),a.scaleY=Math.abs(a.scaleY));let o=this._calculateCurrentDimensions(a),s={};return this.forEachControl((e,t)=>{let n=e.positionHandler(o,i,this,e);s[t]=Object.assign(n,this._calcCornerCoords(e,n))}),s}_calcCornerCoords(e,t){let n=this.getTotalAngle();return{corner:e.calcCornerCoords(n,this.cornerSize,t.x,t.y,!1,this),touchCorner:e.calcCornerCoords(n,this.touchCornerSize,t.x,t.y,!0,this)}}setCoords(){super.setCoords(),this.canvas&&(this.oCoords=this.calcOCoords())}forEachControl(e){for(let t in this.controls)e(this.controls[t],t,this)}drawSelectionBackground(e){if(!this.selectionBackgroundColor||this.canvas&&this.canvas._activeObject!==this)return;e.save();let t=this.getRelativeCenterPoint(),n=this._calculateCurrentDimensions(),r=this.getViewportTransform();e.translate(t.x,t.y),e.scale(1/r[0],1/r[3]),e.rotate(I(this.angle)),e.fillStyle=this.selectionBackgroundColor,e.fillRect(-n.x/2,-n.y/2,n.x,n.y),e.restore()}strokeBorders(e,t){e.strokeRect(-t.x/2,-t.y/2,t.x,t.y)}_drawBorders(e,t,n={}){let r={hasControls:this.hasControls,borderColor:this.borderColor,borderDashArray:this.borderDashArray,...n};e.save(),e.strokeStyle=r.borderColor,this._setLineDash(e,r.borderDashArray),this.strokeBorders(e,t),r.hasControls&&this.drawControlsConnectingLines(e,t),e.restore()}_renderControls(e,t={}){let{hasBorders:n,hasControls:r}=this,i={hasBorders:n,hasControls:r,...t},a=this.getViewportTransform(),o=i.hasBorders,s=i.hasControls,c=He(z(a,this.calcTransformMatrix()));e.save(),e.translate(c.translateX,c.translateY),e.lineWidth=this.borderScaleFactor,this.group===this.parent&&(e.globalAlpha=this.isMoving?this.borderOpacityWhenMoving:1),this.flipX&&(c.angle-=180);let l=ze(a);e.rotate(this.group?I(c.angle):I(this.angle)+l),o&&this.drawBorders(e,c,t),s&&this.drawControls(e,t),e.restore()}drawBorders(e,t,n){let r;if(n&&n.forActiveSelection||this.group){let e=At(this.width,this.height,Ye(t)),n=this.isStrokeAccountedForInDimensions()?we:(this.strokeUniform?new N().scalarAdd(this.canvas?this.canvas.getZoom():1):new N(t.scaleX,t.scaleY)).scalarMultiply(this.strokeWidth);r=e.add(n).scalarAdd(this.borderScaleFactor).scalarAdd(2*this.padding)}else r=this._calculateCurrentDimensions().scalarAdd(this.borderScaleFactor);this._drawBorders(e,r,n)}drawControlsConnectingLines(e,t){let n=!1;e.beginPath(),this.forEachControl((r,i)=>{r.withConnection&&r.getVisibility(this,i)&&(n=!0,e.moveTo(r.x*t.x,r.y*t.y),e.lineTo(r.x*t.x+r.offsetX,r.y*t.y+r.offsetY))}),n&&e.stroke()}drawControls(e,t={}){e.save();let n=this.getCanvasRetinaScaling(),{cornerStrokeColor:r,cornerDashArray:i,cornerColor:a}=this,o={cornerStrokeColor:r,cornerDashArray:i,cornerColor:a,...t};e.setTransform(n,0,0,n,0,0),e.strokeStyle=e.fillStyle=o.cornerColor,this.transparentCorners||(e.strokeStyle=o.cornerStrokeColor),this._setLineDash(e,o.cornerDashArray),this.forEachControl((t,n)=>{if(t.getVisibility(this,n)){let r=this.oCoords[n];t.render(e,r.x,r.y,o,this)}}),e.restore()}isControlVisible(e){return this.controls[e]&&this.controls[e].getVisibility(this,e)}setControlVisible(e,t){this._controlsVisibility||(this._controlsVisibility={}),this._controlsVisibility[e]=t}setControlsVisibility(e={}){Object.entries(e).forEach(([e,t])=>this.setControlVisible(e,t))}clearContextTop(e){if(!this.canvas)return;let t=this.canvas.contextTop;if(!t)return;let n=this.canvas.viewportTransform;t.save(),t.transform(n[0],n[1],n[2],n[3],n[4],n[5]),this.transform(t);let r=this.width+4,i=this.height+4;return t.clearRect(-r/2,-i/2,r,i),e||t.restore(),t}onDeselect(e){return!1}onSelect(e){return!1}shouldStartDragging(e){return!1}onDragStart(e){return!1}canDrop(e){return!1}renderDragSourceEffect(e){}renderDropTargetEffect(e){}};function vi(e,t){return t.forEach(t=>{Object.getOwnPropertyNames(t.prototype).forEach(n=>{n!==`constructor`&&Object.defineProperty(e.prototype,n,Object.getOwnPropertyDescriptor(t.prototype,n)||Object.create(null))})}),e}i(_i,`ownDefaults`,{noScaleCache:!0,lockMovementX:!1,lockMovementY:!1,lockRotation:!1,lockScalingX:!1,lockScalingY:!1,lockSkewingX:!1,lockSkewingY:!1,lockScalingFlip:!1,cornerSize:13,touchCornerSize:24,transparentCorners:!0,cornerColor:`rgb(178,204,255)`,cornerStrokeColor:``,cornerStyle:`rect`,cornerDashArray:null,hasControls:!0,borderColor:`rgb(178,204,255)`,borderDashArray:null,borderOpacityWhenMoving:.4,borderScaleFactor:1,hasBorders:!0,selectionBackgroundColor:``,selectable:!0,evented:!0,perPixelTargetFind:!1,activeOn:`down`,hoverCursor:null,moveCursor:null});var J=class extends _i{};vi(J,[gn]),M.setClass(J),M.setClass(J,`object`);const yi=(e,t,n,r)=>{let i=2*(r=Math.round(r))+1,{data:a}=e.getImageData(t-r,n-r,i,i);for(let e=3;e<a.length;e+=4)if(a[e]>0)return!1;return!0};var bi=class{constructor(e){this.options=e,this.strokeProjectionMagnitude=this.options.strokeWidth/2,this.scale=new N(this.options.scaleX,this.options.scaleY),this.strokeUniformScalar=this.options.strokeUniform?new N(1/this.options.scaleX,1/this.options.scaleY):new N(1,1)}createSideVector(e,t){let n=zt(e,t);return this.options.strokeUniform?n.multiply(this.scale):n}projectOrthogonally(e,t,n){return this.applySkew(e.add(this.calcOrthogonalProjection(e,t,n)))}isSkewed(){return this.options.skewX!==0||this.options.skewY!==0}applySkew(e){let t=new N(e);return t.y+=t.x*Math.tan(I(this.options.skewY)),t.x+=t.y*Math.tan(I(this.options.skewX)),t}scaleUnitVector(e,t){return e.multiply(this.strokeUniformScalar).scalarMultiply(t)}};const xi=new N;var Si=class e extends bi{static getOrthogonalRotationFactor(e,t){let n=t?Vt(e,t):Ht(e);return Math.abs(n)<S?-1:1}constructor(e,t,n,r){super(r),i(this,`AB`,void 0),i(this,`AC`,void 0),i(this,`alpha`,void 0),i(this,`bisector`,void 0),this.A=new N(e),this.B=new N(t),this.C=new N(n),this.AB=this.createSideVector(this.A,this.B),this.AC=this.createSideVector(this.A,this.C),this.alpha=Vt(this.AB,this.AC),this.bisector=Ut(Rt(this.AB.eq(xi)?this.AC:this.AB,this.alpha/2))}calcOrthogonalProjection(t,n,r=this.strokeProjectionMagnitude){let i=Wt(this.createSideVector(t,n)),a=e.getOrthogonalRotationFactor(i,this.bisector);return this.scaleUnitVector(i,r*a)}projectBevel(){let e=[];return(this.alpha%w===0?[this.B]:[this.B,this.C]).forEach(t=>{e.push(this.projectOrthogonally(this.A,t)),e.push(this.projectOrthogonally(this.A,t,-this.strokeProjectionMagnitude))}),e}projectMiter(){let e=[],t=Math.abs(this.alpha),n=1/Math.sin(t/2),r=this.scaleUnitVector(this.bisector,-this.strokeProjectionMagnitude*n),i=this.options.strokeUniform?Bt(this.scaleUnitVector(this.bisector,this.options.strokeMiterLimit)):this.options.strokeMiterLimit;return Bt(r)/this.strokeProjectionMagnitude<=i&&e.push(this.applySkew(this.A.add(r))),e.push(...this.projectBevel()),e}projectRoundNoSkew(t,n){let r=[],i=new N(e.getOrthogonalRotationFactor(this.bisector),e.getOrthogonalRotationFactor(new N(this.bisector.y,this.bisector.x)));return[new N(1,0).scalarMultiply(this.strokeProjectionMagnitude).multiply(this.strokeUniformScalar).multiply(i),new N(0,1).scalarMultiply(this.strokeProjectionMagnitude).multiply(this.strokeUniformScalar).multiply(i)].forEach(e=>{qt(e,t,n)&&r.push(this.A.add(e))}),r}projectRoundWithSkew(e,t){let n=[],{skewX:r,skewY:i,scaleX:a,scaleY:o,strokeUniform:s}=this.options,c=new N(Math.tan(I(r)),Math.tan(I(i))),l=this.strokeProjectionMagnitude,u=s?l/o/Math.sqrt(1/o**2+1/a**2*c.y**2):l/Math.sqrt(1+c.y**2),d=new N(Math.sqrt(Math.max(l**2-u**2,0)),u),f=s?l/Math.sqrt(1+c.x**2*(1/o)**2/(1/a+1/a*c.x*c.y)**2):l/Math.sqrt(1+c.x**2/(1+c.x*c.y)**2),p=new N(f,Math.sqrt(Math.max(l**2-f**2,0)));return[p,p.scalarMultiply(-1),d,d.scalarMultiply(-1)].map(e=>this.applySkew(s?e.multiply(this.strokeUniformScalar):e)).forEach(r=>{qt(r,e,t)&&n.push(this.applySkew(this.A).add(r))}),n}projectRound(){let e=[];e.push(...this.projectBevel());let t=this.alpha%w===0,n=this.applySkew(this.A),r=e[t?0:2].subtract(n),i=e[+!!t].subtract(n),a=Gt(r,t?this.applySkew(this.AB.scalarMultiply(-1)):this.applySkew(this.bisector.multiply(this.strokeUniformScalar).scalarMultiply(-1)))>0,o=a?r:i,s=a?i:r;return this.isSkewed()?e.push(...this.projectRoundWithSkew(o,s)):e.push(...this.projectRoundNoSkew(o,s)),e}projectPoints(){switch(this.options.strokeLineJoin){case`miter`:return this.projectMiter();case`round`:return this.projectRound();default:return this.projectBevel()}}project(){return this.projectPoints().map(e=>({originPoint:this.A,projectedPoint:e,angle:this.alpha,bisector:this.bisector}))}},Ci=class extends bi{constructor(e,t,n){super(n),this.A=new N(e),this.T=new N(t)}calcOrthogonalProjection(e,t,n=this.strokeProjectionMagnitude){let r=this.createSideVector(e,t);return this.scaleUnitVector(Wt(r),n)}projectButt(){return[this.projectOrthogonally(this.A,this.T,this.strokeProjectionMagnitude),this.projectOrthogonally(this.A,this.T,-this.strokeProjectionMagnitude)]}projectRound(){let e=[];if(!this.isSkewed()&&this.A.eq(this.T)){let t=new N(1,1).scalarMultiply(this.strokeProjectionMagnitude).multiply(this.strokeUniformScalar);e.push(this.applySkew(this.A.add(t)),this.applySkew(this.A.subtract(t)))}else e.push(...new Si(this.A,this.T,this.T,this.options).projectRound());return e}projectSquare(){let e=[];if(this.A.eq(this.T)){let t=new N(1,1).scalarMultiply(this.strokeProjectionMagnitude).multiply(this.strokeUniformScalar);e.push(this.A.add(t),this.A.subtract(t))}else{let t=this.calcOrthogonalProjection(this.A,this.T,this.strokeProjectionMagnitude),n=this.scaleUnitVector(Ut(this.createSideVector(this.A,this.T)),-this.strokeProjectionMagnitude),r=this.A.add(n);e.push(r.add(t),r.subtract(t))}return e.map(e=>this.applySkew(e))}projectPoints(){switch(this.options.strokeLineCap){case`round`:return this.projectRound();case`square`:return this.projectSquare();default:return this.projectButt()}}project(){return this.projectPoints().map(e=>({originPoint:this.A,projectedPoint:e}))}};const wi=(e,t,n=!1)=>{let r=[];if(e.length===0)return r;let i=e.reduce((e,t)=>(e[e.length-1].eq(t)||e.push(new N(t)),e),[new N(e[0])]);if(i.length===1)n=!0;else if(!n){let e=i[0],t=((e,t)=>{for(let n=e.length-1;n>=0;n--)if(t(e[n],n,e))return n;return-1})(i,t=>!t.eq(e));i.splice(t+1)}return i.forEach((e,i,a)=>{let o,s;i===0?(s=a[1],o=n?e:a[a.length-1]):i===a.length-1?(o=a[i-1],s=n?e:a[0]):(o=a[i-1],s=a[i+1]),n&&a.length===1?r.push(...new Ci(e,e,t).project()):!n||i!==0&&i!==a.length-1?r.push(...new Si(e,o,s,t).project()):r.push(...new Ci(e,i===0?s:o,t).project())}),r},Ti=e=>{let t={};return Object.keys(e).forEach(n=>{t[n]={},Object.keys(e[n]).forEach(r=>{t[n][r]={...e[n][r]}})}),t},Ei=(e,t,n=!1)=>e.fill!==t.fill||e.stroke!==t.stroke||e.strokeWidth!==t.strokeWidth||e.fontSize!==t.fontSize||e.fontFamily!==t.fontFamily||e.fontWeight!==t.fontWeight||e.fontStyle!==t.fontStyle||e.textDecorationThickness!==t.textDecorationThickness||e.textDecorationColor!==t.textDecorationColor||e.textBackgroundColor!==t.textBackgroundColor||e.deltaY!==t.deltaY||n&&(e.overline!==t.overline||e.underline!==t.underline||e.linethrough!==t.linethrough),Di=(e,t)=>{let n=t.split(`
`),r=[],i=-1,a={};e=Ti(e);for(let t=0;t<n.length;t++){let o=gt(n[t]);if(e[t])for(let n=0;n<o.length;n++){i++;let o=e[t][n];o&&Object.keys(o).length>0&&(Ei(a,o,!0)?r.push({start:i,end:i+1,style:o}):r[r.length-1].end++),a=o||{}}else i+=o.length,a={}}return r},Oi=(e,t)=>{if(!Array.isArray(e))return Ti(e);let n=t.split(ne),r={},i=-1,a=0;for(let t=0;t<n.length;t++){let o=gt(n[t]);for(let n=0;n<o.length;n++)i++,e[a]&&e[a].start<=i&&i<e[a].end&&(r[t]=r[t]||{},r[t][n]={...e[a].style},i===e[a].end-1&&a++)}return r},ki=[`display`,`transform`,j,`fill-opacity`,`fill-rule`,`opacity`,he,`stroke-dasharray`,`stroke-linecap`,`stroke-dashoffset`,`stroke-linejoin`,`stroke-miterlimit`,`stroke-opacity`,`stroke-width`,`id`,`paint-order`,`vector-effect`,`instantiated_by_use`,`clip-path`];function Ai(e,t){let n=e.nodeName,r=e.getAttribute(`class`),i=e.getAttribute(`id`),a=`(?![a-zA-Z\\-]+)`,o;if(o=RegExp(`^`+n,`i`),t=t.replace(o,``),i&&t.length&&(o=RegExp(`#`+i+a,`i`),t=t.replace(o,``)),r&&t.length){let e=r.split(` `);for(let n=e.length;n--;)o=RegExp(`\\.`+e[n]+a,`i`),t=t.replace(o,``)}return t.length===0}function ji(e,t){let n=!0,r=Ai(e,t.pop());return r&&t.length&&(n=function(e,t){let n,r=!0;for(;e.parentElement&&e.parentElement.nodeType===1&&t.length;)r&&(n=t.pop()),r=Ai(e=e.parentElement,n);return t.length===0}(e,t)),r&&n&&t.length===0}function Mi(e,t={}){let n={};for(let r in t)ji(e,r.split(` `))&&(n={...n,...t[r]});return n}const Ni=e=>{var t;return(t=jn[e])==null?e:t},Pi=RegExp(`(${Dn})`,`gi`),Y=`(${Dn})`,Fi=String.raw`(skewX)\(${Y}\)`,Ii=String.raw`(skewY)\(${Y}\)`,Li=String.raw`(rotate)\(${Y}(?: ${Y} ${Y})?\)`,Ri=String.raw`(scale)\(${Y}(?: ${Y})?\)`,zi=String.raw`(translate)\(${Y}(?: ${Y})?\)`,Bi=`(?:${String.raw`(matrix)\(${Y} ${Y} ${Y} ${Y} ${Y} ${Y}\)`}|${zi}|${Li}|${Ri}|${Fi}|${Ii})`,Vi=`(?:${Bi}*)`,Hi=String.raw`^\s*(?:${Vi}?)\s*$`,Ui=new RegExp(Hi),Wi=new RegExp(Bi),Gi=new RegExp(Bi,`g`);function Ki(e){let t=[];if(!(e=(e=>on(e.replace(Pi,` $1 `).replace(/,/gi,` `)))(e).replace(/\s*([()])\s*/gi,`$1`))||e&&!Ui.test(e))return[...T];for(let n of e.matchAll(Gi)){let e=Wi.exec(n[0]);if(!e)continue;let r=T,[,i,...a]=e.filter(e=>!!e),[o,s,c,l,u,d]=a.map(e=>parseFloat(e));switch(i){case`translate`:r=Ue(o,s);break;case oe:r=We({angle:o},{x:s,y:c});break;case ue:r=Ge(o,s);break;case pe:r=qe(o);break;case me:r=Je(o);break;case`matrix`:r=[o,s,c,l,u,d]}t.push(r)}return Re(t)}function qi(e,t,n,r){let i=Array.isArray(t),a,o=t;if(e!==`fill`&&e!==`stroke`||t!==`none`){if(e===`strokeUniform`)return t===`non-scaling-stroke`;if(e===`strokeDashArray`)o=t===`none`?null:t.replace(/,/g,` `).split(/\s+/).map(parseFloat);else if(e===`transformMatrix`)o=n&&n.transformMatrix?z(n.transformMatrix,Ki(t)):Ki(t);else if(e===`visible`)o=t!==`none`&&t!==`hidden`,n&&!1===n.visible&&(o=!1);else if(e===`opacity`)o=parseFloat(t),n&&n.opacity!==void 0&&(o*=n.opacity);else if(e===`textAnchor`)o=t===`start`?D:t===`end`?k:E;else if(e===`charSpacing`||e===`textDecorationThickness`)a=K(t,r)/r*1e3;else if(e===`paintFirst`){let e=t.indexOf(j),n=t.indexOf(he);o=j,(e>-1&&n>-1&&n<e||e===-1&&n>-1)&&(o=he)}else{if(e===`href`||e===`xlink:href`||e===`font`||e===`id`)return t;if(e===`imageSmoothing`)return t===`optimizeQuality`;a=i?t.map(K):K(t,r)}}else o=``;return!i&&isNaN(a)?o:a}function Ji(e,t){e.replace(/;\s*$/,``).split(`;`).forEach(e=>{if(!e)return;let[n,r]=e.split(`:`);t[n.trim().toLowerCase()]=r.trim()})}function Yi(e){let t={},n=e.getAttribute(`style`);return n&&(typeof n==`string`?Ji(n,t):function(e,t){Object.entries(e).forEach(([e,n])=>{n!==void 0&&(t[e.toLowerCase()]=n)})}(n,t)),t}const Xi={stroke:`strokeOpacity`,fill:`fillOpacity`};function Zi(e,t,n){if(!e)return{};let r,i={},a=16;e.parentNode&&In.test(e.parentNode.nodeName)&&(i=Zi(e.parentElement,t,n),i.fontSize&&(r=a=K(i.fontSize)));let o={...t.reduce((t,n)=>{let r=e.getAttribute(n);return r&&(t[n]=r),t},{}),...Mi(e,n),...Yi(e)};o[`clip-path`]&&e.setAttribute(Nn,o[Nn]),o[`font-size`]&&(r=K(o[Mn],a),o[Mn]=`${r}`);let s={};for(let e in o){let t=Ni(e);s[t]=qi(t,o[e],i,r)}s&&s.font&&function(e,t){let n=e.match(An);if(!n)return;let r=n[1],i=n[3],a=n[4],o=n[5],s=n[6];r&&(t.fontStyle=r),i&&(t.fontWeight=isNaN(parseFloat(i))?i:parseFloat(i)),a&&(t.fontSize=K(a)),s&&(t.fontFamily=s),o&&(t.lineHeight=o===`normal`?1:o)}(s.font,s);let c={...i,...s};return In.test(e.nodeName)?c:function(e){let t=J.getDefaults();return Object.entries(Xi).forEach(([n,r])=>{if(e[r]===void 0||e[n]===``)return;if(e[n]===void 0){if(!t[n])return;e[n]=t[n]}if(e[n].indexOf(`url(`)===0)return;let i=new G(e[n]);e[n]=i.setAlpha(B(i.getAlpha()*e[r],2)).toRgba()}),e}(c)}const Qi=[`rx`,`ry`];var $i=class e extends J{static getDefaults(){return{...super.getDefaults(),...e.ownDefaults}}constructor(t){super(),Object.assign(this,e.ownDefaults),this.setOptions(t),this._initRxRy()}_initRxRy(){let{rx:e,ry:t}=this;e&&!t?this.ry=e:t&&!e&&(this.rx=t)}_render(e){let{width:t,height:n}=this,r=-t/2,i=-n/2,a=this.rx?Math.min(this.rx,t/2):0,o=this.ry?Math.min(this.ry,n/2):0,s=a!==0||o!==0;e.beginPath(),e.moveTo(r+a,i),e.lineTo(r+t-a,i),s&&e.bezierCurveTo(r+t-.4477152502*a,i,r+t,i+.4477152502*o,r+t,i+o),e.lineTo(r+t,i+n-o),s&&e.bezierCurveTo(r+t,i+n-.4477152502*o,r+t-.4477152502*a,i+n,r+t-a,i+n),e.lineTo(r+a,i+n),s&&e.bezierCurveTo(r+.4477152502*a,i+n,r,i+n-.4477152502*o,r,i+n-o),e.lineTo(r,i+o),s&&e.bezierCurveTo(r,i+.4477152502*o,r+.4477152502*a,i,r+a,i),e.closePath(),this._renderPaintInOrder(e)}toObject(e=[]){return super.toObject([...Qi,...e])}_toSVG(){let{width:e,height:t,rx:n,ry:r}=this;return[`<rect `,`COMMON_PARTS`,`x="${-e/2}" y="${-t/2}" rx="${U(n)}" ry="${U(r)}" width="${U(e)}" height="${U(t)}" />\n`]}static async fromElement(e,t,n){let{left:r=0,top:i=0,width:a=0,height:o=0,visible:s=!0,...c}=Zi(e,this.ATTRIBUTE_NAMES,n);return new this({...t,...c,left:r,top:i,width:a,height:o,visible:!!(s&&a&&o)})}};i($i,`type`,`Rect`),i($i,`cacheProperties`,[...Un,...Qi]),i($i,`ownDefaults`,{rx:0,ry:0}),i($i,`ATTRIBUTE_NAMES`,[...ki,`x`,`y`,`rx`,`ry`,`width`,`height`]),M.setClass($i),M.setSVGClass($i);const ea=`initialization`,ta=`added`,na=(e,t)=>{let{strokeUniform:n,strokeWidth:r,width:i,height:a,group:o}=t,s=o&&o!==e?jt(o.calcTransformMatrix(),e.calcTransformMatrix()):null,c=s?t.getRelativeCenterPoint().transform(s):t.getRelativeCenterPoint(),l=!t.isStrokeAccountedForInDimensions(),u=n&&l?Nt(new N(r,r),void 0,e.calcTransformMatrix()):we,d=!n&&l?r:0,f=At(i+d,a+d,Re([s,t.calcOwnMatrix()],!0)).add(u).scalarDivide(2);return[c.subtract(f),c.add(f)]};var ra=class{calcLayoutResult(e,t){if(this.shouldPerformLayout(e))return this.calcBoundingBox(t,e)}shouldPerformLayout({type:e,prevStrategy:t,strategy:n}){return e===`initialization`||e===`imperative`||!!t&&n!==t}shouldLayoutClipPath({type:e,target:{clipPath:t}}){return e!==`initialization`&&t&&!t.absolutePositioned}getInitialSize(e,t){return t.size}calcBoundingBox(e,t){let{type:n,target:r}=t;if(n===`imperative`&&t.overrides)return t.overrides;if(e.length===0)return;let{left:i,top:a,width:o,height:s}=wt(e.map(e=>na(r,e)).reduce((e,t)=>e.concat(t),[])),c=new N(o,s),l=new N(i,a).add(c.scalarDivide(2));if(n===`initialization`){let e=this.getInitialSize(t,{size:c,center:l});return{center:l,relativeCorrection:new N(0,0),size:e}}return{center:l.transform(r.calcOwnMatrix()),size:c}}};i(ra,`type`,`strategy`);var ia=class extends ra{shouldPerformLayout(e){return!0}};i(ia,`type`,`fit-content`),M.setClass(ia);const aa=`layoutManager`;var oa=class{constructor(e=new ia){i(this,`strategy`,void 0),this.strategy=e,this._subscriptions=new Map}performLayout(e){let t={bubbles:!0,strategy:this.strategy,...e,prevStrategy:this._prevLayoutStrategy,stopPropagation(){this.bubbles=!1}};this.onBeforeLayout(t);let n=this.getLayoutResult(t);n&&this.commitLayout(t,n),this.onAfterLayout(t,n),this._prevLayoutStrategy=t.strategy}attachHandlers(e,t){let{target:n}=t;return[ge,re,se,ae,ie,A,le,ce,`modifyPath`].map(t=>e.on(t,e=>this.performLayout(t===`modified`?{type:`object_modified`,trigger:t,e,target:n}:{type:`object_modifying`,trigger:t,e,target:n})))}subscribe(e,t){this.unsubscribe(e,t);let n=this.attachHandlers(e,t);this._subscriptions.set(e,n)}unsubscribe(e,t){(this._subscriptions.get(e)||[]).forEach(e=>e()),this._subscriptions.delete(e)}unsubscribeTargets(e){e.targets.forEach(t=>this.unsubscribe(t,e))}subscribeTargets(e){e.targets.forEach(t=>this.subscribe(t,e))}onBeforeLayout(e){let{target:t,type:n}=e,{canvas:r}=t;if(n===`initialization`||n===`added`?this.subscribeTargets(e):n===`removed`&&this.unsubscribeTargets(e),t.fire(`layout:before`,{context:e}),r&&r.fire(`object:layout:before`,{target:t,context:e}),n===`imperative`&&e.deep){let{strategy:n,...r}=e;t.forEachObject(e=>e.layoutManager&&e.layoutManager.performLayout({...r,bubbles:!1,target:e}))}}getLayoutResult(e){let{target:t,strategy:n,type:r}=e,i=n.calcLayoutResult(e,t.getObjects());if(!i)return;let a=r===`initialization`?new N:t.getRelativeCenterPoint(),{center:o,correction:s=new N,relativeCorrection:c=new N}=i;return{result:i,prevCenter:a,nextCenter:o,offset:a.subtract(o).add(s).transform(r===`initialization`?T:R(t.calcOwnMatrix()),!0).add(c)}}commitLayout(e,t){let{target:n}=e,{result:{size:r},nextCenter:i}=t;var a,o;n.set({width:r.x,height:r.y}),this.layoutObjects(e,t),e.type===`initialization`?n.set({left:(a=e.x)==null?i.x+r.x*W(n.originX):a,top:(o=e.y)==null?i.y+r.y*W(n.originY):o}):(n.setPositionByOrigin(i,E,E),n.setCoords(),n.set(`dirty`,!0))}layoutObjects(e,t){let{target:n}=e;n.forEachObject(r=>{r.group===n&&this.layoutObject(e,t,r)}),e.strategy.shouldLayoutClipPath(e)&&this.layoutObject(e,t,n.clipPath)}layoutObject(e,{offset:t},n){n.set({left:n.left+t.x,top:n.top+t.y})}onAfterLayout(e,t){let{target:n,strategy:r,bubbles:i,prevStrategy:a,...o}=e,{canvas:s}=n;n.fire(`layout:after`,{context:e,result:t}),s&&s.fire(`object:layout:after`,{context:e,result:t,target:n});let c=n.parent;i&&c!=null&&c.layoutManager&&((o.path||(o.path=[])).push(n),c.layoutManager.performLayout({...o,target:c})),n.set(`dirty`,!0)}dispose(){let{_subscriptions:e}=this;e.forEach(e=>e.forEach(e=>e())),e.clear()}toObject(){return{type:aa,strategy:this.strategy.constructor.type}}toJSON(){return this.toObject()}};M.setClass(oa,aa);var sa=class extends oa{performLayout(){}},ca=class e extends Ee(J){static getDefaults(){return{...super.getDefaults(),...e.ownDefaults}}constructor(t=[],n={}){super(),i(this,`_activeObjects`,[]),i(this,`__objectSelectionTracker`,void 0),i(this,`__objectSelectionDisposer`,void 0),Object.assign(this,e.ownDefaults),this.setOptions(n),this.groupInit(t,n)}groupInit(e,t){var n;this._objects=[...e],this.__objectSelectionTracker=this.__objectSelectionMonitor.bind(this,!0),this.__objectSelectionDisposer=this.__objectSelectionMonitor.bind(this,!1),this.forEachObject(e=>{this.enterGroup(e,!1)}),this.layoutManager=(n=t.layoutManager)==null?new oa:n,this.layoutManager.performLayout({type:ea,target:this,targets:[...e],x:t.left,y:t.top})}canEnterGroup(e){return e===this||this.isDescendantOf(e)?(s(`error`,`Group: circular object trees are not supported, this call has no effect`),!1):this._objects.indexOf(e)===-1||(s(`error`,`Group: duplicate objects are not supported inside group, this call has no effect`),!1)}_filterObjectsBeforeEnteringGroup(e){return e.filter((e,t,n)=>this.canEnterGroup(e)&&n.indexOf(e)===t)}add(...e){let t=this._filterObjectsBeforeEnteringGroup(e),n=super.add(...t);return this._onAfterObjectsChange(ta,t),n}insertAt(e,...t){let n=this._filterObjectsBeforeEnteringGroup(t),r=super.insertAt(e,...n);return this._onAfterObjectsChange(ta,n),r}remove(...e){let t=super.remove(...e);return this._onAfterObjectsChange(`removed`,t),t}_onObjectAdded(e){this.enterGroup(e,!0),this.fire(`object:added`,{target:e}),e.fire(`added`,{target:this})}_onObjectRemoved(e,t){this.exitGroup(e,t),this.fire(`object:removed`,{target:e}),e.fire(`removed`,{target:this})}_onAfterObjectsChange(e,t){this.layoutManager.performLayout({type:e,targets:t,target:this})}_onStackOrderChanged(){this._set(`dirty`,!0)}_set(e,t){let n=this[e];return super._set(e,t),e===`canvas`&&n!==t&&(this._objects||[]).forEach(n=>{n._set(e,t)}),this}_shouldSetNestedCoords(){return this.subTargetCheck}removeAll(){return this._activeObjects=[],this.remove(...this._objects)}__objectSelectionMonitor(e,{target:t}){let n=this._activeObjects;if(e)n.push(t),this._set(`dirty`,!0);else if(n.length>0){let e=n.indexOf(t);e>-1&&(n.splice(e,1),this._set(`dirty`,!0))}}_watchObject(e,t){e&&this._watchObject(!1,t),e?(t.on(`selected`,this.__objectSelectionTracker),t.on(`deselected`,this.__objectSelectionDisposer)):(t.off(`selected`,this.__objectSelectionTracker),t.off(`deselected`,this.__objectSelectionDisposer))}enterGroup(e,t){e.group&&e.group.remove(e),e._set(`parent`,this),this._enterGroup(e,t)}_enterGroup(e,t){t&&Dt(e,z(R(this.calcTransformMatrix()),e.calcTransformMatrix())),this._shouldSetNestedCoords()&&e.setCoords(),e._set(`group`,this),e._set(`canvas`,this.canvas),this._watchObject(!0,e);let n=this.canvas&&this.canvas.getActiveObject&&this.canvas.getActiveObject();n&&(n===e||e.isDescendantOf(n))&&this._activeObjects.push(e)}exitGroup(e,t){this._exitGroup(e,t),e._set(`parent`,void 0),e._set(`canvas`,void 0)}_exitGroup(e,t){e._set(`group`,void 0),t||(Dt(e,z(this.calcTransformMatrix(),e.calcTransformMatrix())),e.setCoords()),this._watchObject(!1,e);let n=this._activeObjects.length>0?this._activeObjects.indexOf(e):-1;n>-1&&this._activeObjects.splice(n,1)}shouldCache(){let e=J.prototype.shouldCache.call(this);if(e){for(let e=0;e<this._objects.length;e++)if(this._objects[e].willDrawShadow())return this.ownCaching=!1,!1}return e}willDrawShadow(){if(super.willDrawShadow())return!0;for(let e=0;e<this._objects.length;e++)if(this._objects[e].willDrawShadow())return!0;return!1}isOnACache(){return this.ownCaching||!!this.parent&&this.parent.isOnACache()}drawObject(e,t,n){this._renderBackground(e);for(let t=0;t<this._objects.length;t++){var r;let n=this._objects[t];(r=this.canvas)!=null&&r.preserveObjectStacking&&n.group!==this?(e.save(),e.transform(...R(this.calcTransformMatrix())),n.render(e),e.restore()):n.group===this&&n.render(e)}this._drawClipPath(e,this.clipPath,n)}setCoords(){super.setCoords(),this._shouldSetNestedCoords()&&this.forEachObject(e=>e.setCoords())}triggerLayout(e={}){this.layoutManager.performLayout({target:this,type:`imperative`,...e})}render(e){this._transformDone=!0,super.render(e),this._transformDone=!1}__serializeObjects(e,t){let n=this.includeDefaultValues;return this._objects.filter(function(e){return!e.excludeFromExport}).map(function(r){let i=r.includeDefaultValues;r.includeDefaultValues=n;let a=r[e||`toObject`](t);return r.includeDefaultValues=i,a})}toObject(e=[]){let t=this.layoutManager.toObject();return{...super.toObject([`subTargetCheck`,`interactive`,...e]),...t.strategy!==`fit-content`||this.includeDefaultValues?{layoutManager:t}:{},objects:this.__serializeObjects(`toObject`,e)}}toString(){return`#<Group: (${this.complexity()})>`}dispose(){this.layoutManager.unsubscribeTargets({targets:this.getObjects(),target:this}),this._activeObjects=[],this.forEachObject(e=>{this._watchObject(!1,e),e.dispose()}),super.dispose()}_createSVGBgRect(e){if(!this.backgroundColor)return``;let t=$i.prototype._toSVG.call(this),n=t.indexOf(`COMMON_PARTS`);t[n]=`for="group" `;let r=t.join(``);return e?e(r):r}_toSVG(e){let t=[`<g `,`COMMON_PARTS`,` >
`],n=this._createSVGBgRect(e);n&&t.push(`		`,n);for(let n=0;n<this._objects.length;n++)t.push(`		`,this._objects[n].toSVG(e));return t.push(`</g>
`),t}getSvgStyles(){let e=this.opacity!==void 0&&this.opacity!==1?`opacity: ${U(this.opacity)};`:``,t=this.visible?``:` visibility: hidden;`;return[e,this.getSvgFilter(),t].join(``)}toClipPathSVG(e){let t=[],n=this._createSVGBgRect(e);n&&t.push(`	`,n);for(let n=0;n<this._objects.length;n++)t.push(`	`,this._objects[n].toClipPathSVG(e));return this._createBaseClipPathSVGMarkup(t,{reviver:e})}static fromObject({type:e,objects:t=[],layoutManager:n,...r},i){return Promise.all([Qe(t,i),$e(r,i)]).then(([e,t])=>{let i=new this(e,{...r,...t,layoutManager:new sa});return i.layoutManager=n?new(M.getClass(n.type))(new(M.getClass(n.strategy))):new oa,i.layoutManager.subscribeTargets({type:ea,target:i,targets:i.getObjects()}),i.setCoords(),i})}};i(ca,`type`,`Group`),i(ca,`ownDefaults`,{strokeWidth:0,subTargetCheck:!1,interactive:!1}),M.setClass(ca);const la=(e,t)=>e&&e.length===1?e[0]:new ca(e,t),ua=(e,t)=>Math.min(t.width/e.width,t.height/e.height),da=(e,t)=>Math.max(t.width/e.width,t.height/e.height),fa=`\\s*,?\\s*`,pa=`${fa}(${Dn})`,ma=`${pa}${pa}${pa}${fa}([01])${fa}([01])${pa}${pa}`,ha={m:`l`,M:`L`},ga=(e,t,n,r,i,a,o,s,c,l,u)=>{let d=Se(e),f=Ce(e),p=Se(t),m=Ce(t),h=n*i*p-r*a*m+o,g=r*i*p+n*a*m+s;return[`C`,l+c*(-n*i*f-r*a*d),u+c*(-r*i*f+n*a*d),h+c*(n*i*m+r*a*p),g+c*(r*i*m-n*a*p),h,g]},_a=(e,t,n,r)=>{let i=Math.atan2(t,e),a=Math.atan2(r,n);return a>=i?a-i:2*Math.PI-(i-a)};function va(e,t,n,r,i,a,s,c){let l;if(o.cachesBoundsOfCurve&&(l=[...arguments].join(),y.boundsOfCurveCache[l]))return y.boundsOfCurveCache[l];let u=Math.sqrt,d=Math.abs,f=[],p=[[0,0],[0,0]],m=6*e-12*n+6*i,h=-3*e+9*n-9*i+3*s,g=3*n-3*e;for(let e=0;e<2;++e){if(e>0&&(m=6*t-12*r+6*a,h=-3*t+9*r-9*a+3*c,g=3*r-3*t),d(h)<1e-12){if(d(m)<1e-12)continue;let e=-g/m;0<e&&e<1&&f.push(e);continue}let n=m*m-4*g*h;if(n<0)continue;let i=u(n),o=(-m+i)/(2*h);0<o&&o<1&&f.push(o);let s=(-m-i)/(2*h);0<s&&s<1&&f.push(s)}let _=f.length,v=_,b=Sa(e,t,n,r,i,a,s,c);for(;_--;){let{x:e,y:t}=b(f[_]);p[0][_]=e,p[1][_]=t}p[0][v]=e,p[1][v]=t,p[0][v+1]=s,p[1][v+1]=c;let x=[new N(Math.min(...p[0]),Math.min(...p[1])),new N(Math.max(...p[0]),Math.max(...p[1]))];return o.cachesBoundsOfCurve&&(y.boundsOfCurveCache[l]=x),x}const ya=(e,t,[n,r,i,a,o,s,c,l])=>{let u=((e,t,n,r,i,a,o)=>{if(n===0||r===0)return[];let s=0,c=0,l=0,u=Math.PI,d=o*ee,f=Ce(d),p=Se(d),m=.5*(-p*e-f*t),h=.5*(-p*t+f*e),g=n**2,_=r**2,v=h**2,y=m**2,b=g*_-g*v-_*y,x=Math.abs(n),S=Math.abs(r);if(b<0){let e=Math.sqrt(1-b/(g*_));x*=e,S*=e}else l=(i===a?-1:1)*Math.sqrt(b/(g*v+_*y));let C=l*x*h/S,w=-l*S*m/x,T=p*C-f*w+.5*e,E=f*C+p*w+.5*t,D=_a(1,0,(m-C)/x,(h-w)/S),O=_a((m-C)/x,(h-w)/S,(-m-C)/x,(-h-w)/S);a===0&&O>0?O-=2*u:a===1&&O<0&&(O+=2*u);let k=Math.ceil(Math.abs(O/u*2)),te=[],ne=O/k,re=8/3*Math.sin(ne/4)*Math.sin(ne/4)/Math.sin(ne/2),ie=D+ne;for(let e=0;e<k;e++)te[e]=ga(D,ie,p,f,x,S,T,E,re,s,c),s=te[e][5],c=te[e][6],D=ie,ie+=ne;return te})(c-e,l-t,r,i,o,s,a);for(let n=0,r=u.length;n<r;n++)u[n][1]+=e,u[n][2]+=t,u[n][3]+=e,u[n][4]+=t,u[n][5]+=e,u[n][6]+=t;return u},ba=e=>{let t=0,n=0,r=0,i=0,a=[],o,s=0,c=0;for(let l of e){let e=[...l],u;switch(e[0]){case`l`:e[1]+=t,e[2]+=n;case`L`:t=e[1],n=e[2],u=[`L`,t,n];break;case`h`:e[1]+=t;case`H`:t=e[1],u=[`L`,t,n];break;case`v`:e[1]+=n;case`V`:n=e[1],u=[`L`,t,n];break;case`m`:e[1]+=t,e[2]+=n;case`M`:t=e[1],n=e[2],r=e[1],i=e[2],u=[`M`,t,n];break;case`c`:e[1]+=t,e[2]+=n,e[3]+=t,e[4]+=n,e[5]+=t,e[6]+=n;case`C`:s=e[3],c=e[4],t=e[5],n=e[6],u=[`C`,e[1],e[2],s,c,t,n];break;case`s`:e[1]+=t,e[2]+=n,e[3]+=t,e[4]+=n;case`S`:o===`C`?(s=2*t-s,c=2*n-c):(s=t,c=n),t=e[3],n=e[4],u=[`C`,s,c,e[1],e[2],t,n],s=u[3],c=u[4];break;case`q`:e[1]+=t,e[2]+=n,e[3]+=t,e[4]+=n;case`Q`:s=e[1],c=e[2],t=e[3],n=e[4],u=[`Q`,s,c,t,n];break;case`t`:e[1]+=t,e[2]+=n;case`T`:o===`Q`?(s=2*t-s,c=2*n-c):(s=t,c=n),t=e[1],n=e[2],u=[`Q`,s,c,t,n];break;case`a`:e[6]+=t,e[7]+=n;case`A`:ya(t,n,e).forEach(e=>a.push(e)),t=e[6],n=e[7];break;case`z`:case`Z`:t=r,n=i,u=[`Z`]}u?(a.push(u),o=u[0]):o=``}return a},xa=(e,t,n,r)=>Math.sqrt((n-e)**2+(r-t)**2),Sa=(e,t,n,r,i,a,o,s)=>c=>{let l=c**3,u=(e=>3*e**2*(1-e))(c),d=(e=>3*e*(1-e)**2)(c),f=(e=>(1-e)**3)(c);return new N(o*l+i*u+n*d+e*f,s*l+a*u+r*d+t*f)},Ca=e=>e**2,wa=e=>2*e*(1-e),Ta=e=>(1-e)**2,Ea=(e,t,n,r,i,a,o,s)=>c=>{let l=Ca(c),u=wa(c),d=Ta(c),f=3*(d*(n-e)+u*(i-n)+l*(o-i)),p=3*(d*(r-t)+u*(a-r)+l*(s-a));return Math.atan2(p,f)},Da=(e,t,n,r,i,a)=>o=>{let s=Ca(o),c=wa(o),l=Ta(o);return new N(i*s+n*c+e*l,a*s+r*c+t*l)},Oa=(e,t,n,r,i,a)=>o=>{let s=1-o,c=2*(s*(n-e)+o*(i-n)),l=2*(s*(r-t)+o*(a-r));return Math.atan2(l,c)},ka=(e,t,n)=>{let r=new N(t,n),i=0;for(let t=1;t<=100;t+=1){let n=e(t/100);i+=xa(r.x,r.y,n.x,n.y),r=n}return i},Aa=(e,t)=>{let n,r=0,i=0,a={x:e.x,y:e.y},o={...a},s=.01,c=0,l=e.iterator,u=e.angleFinder;for(;i<t&&s>1e-4;)o=l(r),c=r,n=xa(a.x,a.y,o.x,o.y),n+i>t?(r-=s,s/=2):(a=o,r+=s,i+=n);return{...o,angle:u(c)}},ja=e=>{let t,n,r=0,i=0,a=0,o=0,s=0,c=[];for(let l of e){let e={x:i,y:a,command:l[0],length:0};switch(l[0]){case`M`:n=e,n.x=o=i=l[1],n.y=s=a=l[2];break;case`L`:n=e,n.length=xa(i,a,l[1],l[2]),i=l[1],a=l[2];break;case`C`:t=Sa(i,a,l[1],l[2],l[3],l[4],l[5],l[6]),n=e,n.iterator=t,n.angleFinder=Ea(i,a,l[1],l[2],l[3],l[4],l[5],l[6]),n.length=ka(t,i,a),i=l[5],a=l[6];break;case`Q`:t=Da(i,a,l[1],l[2],l[3],l[4]),n=e,n.iterator=t,n.angleFinder=Oa(i,a,l[1],l[2],l[3],l[4]),n.length=ka(t,i,a),i=l[3],a=l[4];break;case`Z`:n=e,n.destX=o,n.destY=s,n.length=xa(i,a,o,s),i=o,a=s}r+=n.length,c.push(n)}return c.push({length:r,x:i,y:a}),c},Ma=(e,t,n=ja(e))=>{let r=0;for(;t-n[r].length>0&&r<n.length-2;)t-=n[r].length,r++;let i=n[r],a=t/i.length,o=e[r];switch(i.command){case`M`:return{x:i.x,y:i.y,angle:0};case`Z`:return{...new N(i.x,i.y).lerp(new N(i.destX,i.destY),a),angle:Math.atan2(i.destY-i.y,i.destX-i.x)};case`L`:return{...new N(i.x,i.y).lerp(new N(o[1],o[2]),a),angle:Math.atan2(o[2]-i.y,o[1]-i.x)};case`C`:case`Q`:return Aa(i,t)}},Na=RegExp(`[mzlhvcsqta][^mzlhvcsqta]*`,`gi`),Pa=new RegExp(ma,`g`),Fa=new RegExp(Dn,`gi`),Ia={m:2,l:2,h:1,v:1,c:6,s:4,q:4,t:2,a:7},La=e=>{var t;let n=[],r=(t=e.match(Na))==null?[]:t;for(let e of r){let t=e[0];if(t===`z`||t===`Z`){n.push([t]);continue}let r=Ia[t.toLowerCase()],i=[];if(t===`a`||t===`A`){let t;for(Pa.lastIndex=0;t=Pa.exec(e);)i.push(...t.slice(1))}else i=e.match(Fa)||[];for(let e=0;e<i.length;e+=r){let a=Array(r),o=ha[t];a[0]=e>0&&o?o:t;for(let t=0;t<r;t++)a[t+1]=parseFloat(i[e+t]);n.push(a)}}return n},Ra=(e,t=0)=>{let n=new N(e[0]),r=new N(e[1]),i=1,a=0,o=[],s=e.length,c=s>2,l;for(c&&(i=e[2].x<r.x?-1:e[2].x===r.x?0:1,a=e[2].y<r.y?-1:e[2].y===r.y?0:1),o.push([`M`,n.x-i*t,n.y-a*t]),l=1;l<s;l++){if(!n.eq(r)){let e=n.midPointFrom(r);o.push([`Q`,n.x,n.y,e.x,e.y])}n=e[l],l+1<e.length&&(r=e[l+1])}return c&&(i=n.x>e[l-2].x?1:n.x===e[l-2].x?0:-1,a=n.y>e[l-2].y?1:n.y===e[l-2].y?0:-1),o.push([`L`,n.x+i*t,n.y+a*t]),o},za=(e,t,n)=>(n&&(t=z(t,[1,0,0,1,-n.x,-n.y])),e.map(e=>{let n=[...e];for(let r=1;r<e.length-1;r+=2){let{x:i,y:a}=L({x:e[r],y:e[r+1]},t);n[r]=i,n[r+1]=a}return n})),Ba=(e,t)=>{let n=2*Math.PI/e,r=-S;e%2==0&&(r+=n/2);let i=Array(e+1);for(let a=0;a<e;a++){let e=a*n+r,{x:o,y:s}=new N(Se(e),Ce(e)).scalarMultiply(t);i[a]=[a===0?`M`:`L`,o,s]}return i[e]=[`Z`],i},Va=(e,t)=>e.map(e=>e.map((e,n)=>n===0||t===void 0?e:B(e,t)).join(` `)).join(` `),Ha=(e,t)=>{var n;let r=e,i=t;r.inverted&&!i.inverted&&(r=t,i=e),Pt(i,(n=i.group)==null?void 0:n.calcTransformMatrix(),r.calcTransformMatrix());let a=r.inverted&&i.inverted;return a&&(r.inverted=i.inverted=!1),new ca([r],{clipPath:i,inverted:a})},Ua=(e,t)=>Math.floor(Math.random()*(t-e+1))+e,Wa=(e,t)=>{let n=e._findCenterFromElement();e.transformMatrix&&((e=>{if(e.transformMatrix){let{scaleX:t,scaleY:n,angle:r,skewX:i}=He(e.transformMatrix);e.flipX=!1,e.flipY=!1,e.set(de,t),e.set(fe,n),e.angle=r,e.skewX=i,e.skewY=0}})(e),n=n.transform(e.transformMatrix)),delete e.transformMatrix,t&&(e.scaleX*=t.scaleX,e.scaleY*=t.scaleY,e.cropX=t.cropX,e.cropY=t.cropY,n.x+=t.offsetLeft,n.y+=t.offsetTop,e.width=t.width,e.height=t.height),e.setPositionByOrigin(n,E,E)};var Ga=t({addTransformToObject:()=>Et,animate:()=>Mr,animateColor:()=>Nr,applyTransformToObject:()=>Dt,calcAngleBetweenVectors:()=>Vt,calcDimensionsMatrix:()=>Ye,calcPlaneChangeMatrix:()=>jt,calcVectorRotation:()=>Ht,cancelAnimFrame:()=>ke,capValue:()=>Vn,composeMatrix:()=>Xe,copyCanvasElement:()=>Ne,cos:()=>Se,createCanvasElement:()=>P,createImage:()=>Me,createRotateMatrix:()=>We,createScaleMatrix:()=>Ge,createSkewXMatrix:()=>qe,createSkewYMatrix:()=>Je,createTranslateMatrix:()=>Ue,createVector:()=>zt,crossProduct:()=>Gt,degreesToRadians:()=>I,dotProduct:()=>Kt,ease:()=>Gn,enlivenObjectEnlivables:()=>$e,enlivenObjects:()=>Qe,findScaleToCover:()=>da,findScaleToFit:()=>ua,getBoundsOfCurve:()=>va,getOrthonormalVector:()=>Wt,getPathSegmentsInfo:()=>ja,getPointOnPath:()=>Ma,getPointer:()=>xt,getRandomInt:()=>Ua,getRegularPolygonPath:()=>Ba,getSmoothPathFromPoints:()=>Ra,getSvgAttributes:()=>pn,getUnitVector:()=>Ut,groupSVGElements:()=>la,hasStyleChanged:()=>Ei,invertTransform:()=>R,isBetweenVectors:()=>qt,isIdentityMatrix:()=>Le,isTouchEvent:()=>St,isTransparent:()=>yi,joinPath:()=>Va,loadImage:()=>Ze,magnitude:()=>Bt,makeBoundingBoxFromPoints:()=>wt,makePathSimpler:()=>ba,matrixToSVG:()=>nt,mergeClipPaths:()=>Ha,multiplyTransformMatrices:()=>z,multiplyTransformMatrixArray:()=>Re,parsePath:()=>La,parsePreserveAspectRatioAttribute:()=>mn,parseUnit:()=>K,pick:()=>et,projectStrokeOnPoints:()=>wi,qrDecompose:()=>He,radiansToDegrees:()=>Ie,removeFromArray:()=>xe,removeTransformFromObject:()=>Tt,removeTransformMatrixForSvgParsing:()=>Wa,requestAnimFrame:()=>Oe,resetObjectTransform:()=>Ot,rotateVector:()=>Rt,saveObjectTransform:()=>kt,sendObjectToPlane:()=>Pt,sendPointToPlane:()=>Mt,sendVectorToPlane:()=>Nt,sin:()=>Ce,sizeAfterTransform:()=>At,string:()=>pt,stylesFromArray:()=>Oi,stylesToArray:()=>Di,toBlob:()=>Fe,toDataURL:()=>Pe,toFixed:()=>B,transformPath:()=>za,transformPoint:()=>L});function Ka(e,t){let n=e.style;n&&Object.entries(t).forEach(([e,t])=>n.setProperty(e,t))}var qa=class extends dt{constructor(e,{allowTouchScrolling:t=!1,containerClass:n=``}={}){super(e),i(this,`upper`,void 0),i(this,`container`,void 0);let{el:r}=this.lower,a=this.createUpperCanvas();this.upper={el:a,ctx:a.getContext(`2d`)},this.applyCanvasStyle(r,{allowTouchScrolling:t}),this.applyCanvasStyle(a,{allowTouchScrolling:t,styles:{position:`absolute`,left:`0`,top:`0`}});let o=this.createContainerElement();o.classList.add(n),r.parentNode&&r.parentNode.replaceChild(o,r),o.append(r,a),this.container=o}createUpperCanvas(){let{el:e}=this.lower,t=P();return t.className=e.className,t.classList.remove(`lower-canvas`),t.classList.add(`upper-canvas`),t.setAttribute(`data-fabric`,`top`),t.style.cssText=e.style.cssText,t.setAttribute(`draggable`,`true`),t}createContainerElement(){let e=g().createElement(`div`);return e.setAttribute(`data-fabric`,`wrapper`),Ka(e,{position:`relative`}),ut(e),e}applyCanvasStyle(e,t){let{styles:n,allowTouchScrolling:r}=t;Ka(e,{...n,"touch-action":r?`manipulation`:te}),ut(e)}setDimensions(e,t){super.setDimensions(e,t);let{el:n,ctx:r}=this.upper;ct(n,r,e,t)}setCSSDimensions(e){super.setCSSDimensions(e),lt(this.upper.el,e),lt(this.container,e)}cleanupDOM(e){let t=this.container,{el:n}=this.lower,{el:r}=this.upper;super.cleanupDOM(e),t.removeChild(r),t.removeChild(n),t.parentNode&&t.parentNode.replaceChild(n,t)}dispose(){super.dispose(),h().dispose(this.upper.el),delete this.upper,delete this.container}};const Ja=(e,t,n,r)=>{let{target:i,offsetX:a,offsetY:o}=t,s=n-a,c=r-o,l=!Zt(i,`lockMovementX`)&&i.left!==s,u=!Zt(i,`lockMovementY`)&&i.top!==c;return l&&i.set(`left`,s),u&&i.set(`top`,c),(l||u)&&Lr(re,Qt(e,t,n,r)),l||u},Ya=ce,Xa=e=>function(t,n,r){let{points:i,pathOffset:a}=r;return new N(i[e]).subtract(a).transform(z(r.getViewportTransform(),r.calcTransformMatrix()))},Za=(e,t,n,r)=>{let{target:i,pointIndex:a}=t,o=i,s=Mt(new N(n,r),void 0,o.calcOwnMatrix());return o.points[a]=s.add(o.pathOffset),o.setDimensions(),o.set(`dirty`,!0),!0},Qa=(e,t)=>function(n,r,i,a){let o=r.target,s=new N(o.points[(e>0?e:o.points.length)-1]),c=s.subtract(o.pathOffset).transform(o.calcOwnMatrix()),l=t(n,{...r,pointIndex:e},i,a),u=s.subtract(o.pathOffset).transform(o.calcOwnMatrix()).subtract(c);return o.left-=u.x,o.top-=u.y,l},$a=e=>Rr(Ya,Qa(e,Za));function eo(e,t={}){let n={};for(let r=0;r<(typeof e==`number`?e:e.points.length);r++)n[`p${r}`]=new q({actionName:Ya,positionHandler:Xa(r),actionHandler:$a(r),...t});return n}const to=(e,t,n)=>{let{path:r,pathOffset:i}=e,a=r[t];return new N(a[n]-i.x,a[n+1]-i.y).transform(z(e.getViewportTransform(),e.calcTransformMatrix()))};function no(e,t,n){let{commandIndex:r,pointIndex:i}=this;return to(n,r,i)}function ro(e,t,n,r){let{target:i}=t,{commandIndex:a,pointIndex:o}=this,s=((e,t,n,r,i)=>{let{path:a,pathOffset:o}=e,s=a[(r>0?r:a.length)-1],c=new N(s[i],s[i+1]),l=c.subtract(o).transform(e.calcOwnMatrix()),u=Mt(new N(t,n),void 0,e.calcOwnMatrix());a[r][i]=u.x+o.x,a[r][i+1]=u.y+o.y,e.setDimensions();let d=c.subtract(e.pathOffset).transform(e.calcOwnMatrix()).subtract(l);return e.left-=d.x,e.top-=d.y,e.set(`dirty`,!0),!0})(i,n,r,a,o);return s&&Lr(this.actionName,{...Qt(e,t,n,r),commandIndex:a,pointIndex:o}),s}var io=class extends q{constructor(e){super(e)}render(e,t,n,r,i){let a={...r,cornerColor:this.controlFill,cornerStrokeColor:this.controlStroke,transparentCorners:!this.controlFill};super.render(e,t,n,a,i)}},ao=class extends io{constructor(e){super(e)}render(e,t,n,r,i){let{path:a}=i,{commandIndex:o,pointIndex:s,connectToCommandIndex:c,connectToPointIndex:l}=this;e.save(),e.strokeStyle=this.controlStroke,this.connectionDashArray&&e.setLineDash(this.connectionDashArray);let[u]=a[o],d=to(i,c,l);if(u===`Q`){let r=to(i,o,s+2);e.moveTo(r.x,r.y),e.lineTo(t,n)}else e.moveTo(t,n);e.lineTo(d.x,d.y),e.stroke(),e.restore(),super.render(e,t,n,r,i)}};const oo=(e,t,n,r,i,a)=>new(n?ao:io)({commandIndex:e,pointIndex:t,actionName:`modifyPath`,positionHandler:no,actionHandler:ro,connectToCommandIndex:i,connectToPointIndex:a,...r,...n?r.controlPointStyle:r.pointStyle});function so(e,t={}){let n={},r=`M`;return e.path.forEach((e,i)=>{let a=e[0];switch(a!==`Z`&&(n[`c_${i}_${a}`]=oo(i,e.length-2,!1,t)),a){case`C`:n[`c_${i}_C_CP_1`]=oo(i,1,!0,t,i-1,(e=>e===`C`?5:e===`Q`?3:1)(r)),n[`c_${i}_C_CP_2`]=oo(i,3,!0,t,i,5);break;case`Q`:n[`c_${i}_Q_CP_1`]=oo(i,1,!0,t,i,3)}r=a}),n}var co=t({changeHeight:()=>Wr,changeObjectHeight:()=>Hr,changeObjectWidth:()=>Vr,changeWidth:()=>Ur,createObjectDefaultControls:()=>mi,createPathControls:()=>so,createPolyActionHandler:()=>$a,createPolyControls:()=>eo,createPolyPositionHandler:()=>Xa,createResizeControls:()=>hi,createTextboxDefaultControls:()=>gi,dragHandler:()=>Ja,factoryPolyActionHandler:()=>Qa,getLocalPoint:()=>en,polyActionHandler:()=>Za,renderCircleControl:()=>Gr,renderSquareControl:()=>Kr,rotationStyleHandler:()=>qr,rotationWithSnapping:()=>Jr,scaleCursorStyleHandler:()=>Qr,scaleOrSkewActionName:()=>ui,scaleSkewCursorStyleHandler:()=>di,scalingEqually:()=>ei,scalingX:()=>ti,scalingXOrSkewingY:()=>fi,scalingY:()=>ni,scalingYOrSkewingX:()=>pi,skewCursorStyleHandler:()=>ai,skewHandlerX:()=>si,skewHandlerY:()=>ci,wrapWithFireEvent:()=>Rr,wrapWithFixedAnchor:()=>zr}),lo=class e extends yt{constructor(...e){super(...e),i(this,`_hoveredTargets`,[]),i(this,`_currentTransform`,null),i(this,`_groupSelector`,null),i(this,`contextTopDirty`,!1)}static getDefaults(){return{...super.getDefaults(),...e.ownDefaults}}get upperCanvasEl(){var e;return(e=this.elements.upper)==null?void 0:e.el}get contextTop(){var e;return(e=this.elements.upper)==null?void 0:e.ctx}get wrapperEl(){return this.elements.container}initElements(e){this.elements=new qa(e,{allowTouchScrolling:this.allowTouchScrolling,containerClass:this.containerClass}),this._createCacheCanvas()}_onObjectAdded(e){this._objectsToRender=void 0,super._onObjectAdded(e)}_onObjectRemoved(e){this._objectsToRender=void 0,e===this._activeObject&&(this.fire(`before:selection:cleared`,{deselected:[e]}),this._discardActiveObject(),this.fire(`selection:cleared`,{deselected:[e]}),e.fire(`deselected`,{target:e})),e===this._hoveredTarget&&(this._hoveredTarget=void 0,this._hoveredTargets=[]),super._onObjectRemoved(e)}_onStackOrderChanged(){this._objectsToRender=void 0,super._onStackOrderChanged()}_chooseObjectsToRender(){let e=this._activeObject;return!this.preserveObjectStacking&&e?this._objects.filter(t=>!t.group&&t!==e).concat(e):this._objects}renderAll(){this.cancelRequestedRender(),this.destroyed||(!this.contextTopDirty||this._groupSelector||this.isDrawingMode||(this.clearContext(this.contextTop),this.contextTopDirty=!1),this.hasLostContext&&(this.renderTopLayer(this.contextTop),this.hasLostContext=!1),!this._objectsToRender&&(this._objectsToRender=this._chooseObjectsToRender()),this.renderCanvas(this.getContext(),this._objectsToRender))}renderTopLayer(e){e.save(),this.isDrawingMode&&this._isCurrentlyDrawing&&(this.freeDrawingBrush&&this.freeDrawingBrush._render(),this.contextTopDirty=!0),this.selection&&this._groupSelector&&(this._drawSelection(e),this.contextTopDirty=!0),e.restore()}renderTop(){let e=this.contextTop;this.clearContext(e),this.renderTopLayer(e),this.fire(`after:render`,{ctx:e})}setTargetFindTolerance(e){e=Math.round(e),this.targetFindTolerance=e;let t=this.getRetinaScaling(),n=Math.ceil((2*e+1)*t);this.pixelFindCanvasEl.width=this.pixelFindCanvasEl.height=n,this.pixelFindContext.scale(t,t)}isTargetTransparent(e,t,n){let r=this.targetFindTolerance,i=this.pixelFindContext;this.clearContext(i),i.save(),i.translate(-t+r,-n+r),i.transform(...this.viewportTransform);let a=e.selectionBackgroundColor;e.selectionBackgroundColor=``,e.render(i),e.selectionBackgroundColor=a,i.restore();let o=Math.round(r*this.getRetinaScaling());return yi(i,o,o,o)}_isSelectionKeyPressed(e){let t=this.selectionKey;return!!t&&(Array.isArray(t)?!!t.find(t=>!!t&&!0===e[t]):e[t])}_shouldClearSelection(e,t){let n=this.getActiveObjects(),r=this._activeObject;return!!(!t||t&&r&&n.length>1&&n.indexOf(t)===-1&&r!==t&&!this._isSelectionKeyPressed(e)||t&&!t.evented||t&&!t.selectable&&r&&r!==t)}_shouldCenterTransform(e,t,n){if(!e)return;let r;return t===`scale`||t===`scaleX`||t===`scaleY`||t===`resizing`?r=this.centeredScaling||e.centeredScaling:t===`rotate`&&(r=this.centeredRotation||e.centeredRotation),r?!n:n}_getOriginFromCorner(e,t){let n=t?e.controls[t].getTransformAnchorPoint():{x:e.originX,y:e.originY};return t?([`ml`,`tl`,`bl`].includes(t)?n.x=k:[`mr`,`tr`,`br`].includes(t)&&(n.x=D),[`tl`,`mt`,`tr`].includes(t)?n.y=O:[`bl`,`mb`,`br`].includes(t)&&(n.y=`top`),n):n}_setupCurrentTransform(e,t,n){var r;let i=t.group?Mt(this.getScenePoint(e),void 0,t.group.calcTransformMatrix()):this.getScenePoint(e),{key:a=``,control:o}=t.getActiveControl()||{},s=n&&o?(r=o.getActionHandler(e,t,o))==null?void 0:r.bind(o):Ja,c=((e,t,n,r)=>{if(!t||!e)return`drag`;let i=r.controls[t];return i.getActionName(n,i,r)})(n,a,e,t),l=e[this.centeredKey],u=this._shouldCenterTransform(t,c,l)?{x:E,y:E}:this._getOriginFromCorner(t,a),{scaleX:d,scaleY:f,skewX:p,skewY:m,left:h,top:g,angle:_,width:v,height:y,cropX:b,cropY:x}=t,S={target:t,action:c,actionHandler:s,actionPerformed:!1,corner:a,scaleX:d,scaleY:f,skewX:p,skewY:m,offsetX:i.x-h,offsetY:i.y-g,originX:u.x,originY:u.y,ex:i.x,ey:i.y,lastX:i.x,lastY:i.y,theta:I(_),width:v,height:y,shiftKey:e.shiftKey,altKey:l,original:{...kt(t),originX:u.x,originY:u.y,cropX:b,cropY:x}};this._currentTransform=S,this.fire(`before:transform`,{e,transform:S})}setCursor(e){this.upperCanvasEl.style.cursor=e}_drawSelection(e){let{x:t,y:n,deltaX:r,deltaY:i}=this._groupSelector,a=new N(t,n).transform(this.viewportTransform),o=new N(t+r,n+i).transform(this.viewportTransform),s=this.selectionLineWidth/2,c=Math.min(a.x,o.x),l=Math.min(a.y,o.y),u=Math.max(a.x,o.x),d=Math.max(a.y,o.y);this.selectionColor&&(e.fillStyle=this.selectionColor,e.fillRect(c,l,u-c,d-l)),this.selectionLineWidth&&this.selectionBorderColor&&(e.lineWidth=this.selectionLineWidth,e.strokeStyle=this.selectionBorderColor,c+=s,l+=s,u-=s,d-=s,J.prototype._setLineDash.call(this,e,this.selectionDashArray),e.strokeRect(c,l,u-c,d-l))}findTarget(e){if(this._targetInfo)return this._targetInfo;if(this.skipTargetFind)return{subTargets:[],currentSubTargets:[]};let t=this.getScenePoint(e),n=this._activeObject,r=this.getActiveObjects(),i=this.searchPossibleTargets(this._objects,t),{subTargets:a,container:o,target:s}=i,c={...i,currentSubTargets:a,currentContainer:o,currentTarget:s};if(!n)return c;let l={...this.searchPossibleTargets([n],t),currentSubTargets:a,currentContainer:o,currentTarget:s};return n.findControl(this.getViewportPoint(e),St(e))?{...l,target:n}:l.target&&(r.length>1||!this.preserveObjectStacking||this.preserveObjectStacking&&e[this.altSelectionKey])?l:c}_pointIsInObjectSelectionArea(e,t){let n=e.getCoords(),r=this.getZoom(),i=e.padding/r;if(i){let[e,t,r,a]=n,o=Math.atan2(t.y-e.y,t.x-e.x),s=Se(o)*i,c=Ce(o)*i,l=s+c,u=s-c;n=[new N(e.x-u,e.y-l),new N(t.x+l,t.y-u),new N(r.x+u,r.y+l),new N(a.x-l,a.y+u)]}return Pr.isPointInPolygon(t,n)}_checkTarget(e,t){if(e&&e.visible&&e.evented&&this._pointIsInObjectSelectionArea(e,t)){if(!this.perPixelTargetFind&&!e.perPixelTargetFind||e.isEditing)return!0;{let n=t.transform(this.viewportTransform);if(!this.isTargetTransparent(e,n.x,n.y))return!0}}return!1}_searchPossibleTargets(e,t,n){let r=e.length;for(;r--;){let i=e[r];if(this._checkTarget(i,t)){if(Te(i)&&i.subTargetCheck){let{target:e}=this._searchPossibleTargets(i._objects,t,n);e&&n.push(e)}return{target:i,subTargets:n}}}return{subTargets:[]}}searchPossibleTargets(e,t){let n=this._searchPossibleTargets(e,t,[]);n.container=n.target;let{container:r,subTargets:i}=n;if(r&&Te(r)&&r.interactive&&i[0]){for(let e=i.length-1;e>0;e--){let t=i[e];if(!Te(t)||!t.interactive)return n.target=t,n}return n.target=i[0],n}return n}getViewportPoint(e){return this._viewportPoint?this._viewportPoint:this._getPointerImpl(e,!0)}getScenePoint(e){return this._scenePoint?this._scenePoint:this._getPointerImpl(e)}_getPointerImpl(e,t=!1){let n=this.upperCanvasEl,r=n.getBoundingClientRect(),i=xt(e),a=r.width||0,o=r.height||0;a&&o||(`top`in r&&`bottom`in r&&(o=Math.abs(r.top-r.bottom)),`right`in r&&`left`in r&&(a=Math.abs(r.right-r.left))),this.calcOffset(),i.x-=this._offset.left,i.y-=this._offset.top,t||(i=Mt(i,void 0,this.viewportTransform));let s=this.getRetinaScaling();s!==1&&(i.x/=s,i.y/=s);let c=a===0||o===0?new N(1,1):new N(n.width/a,n.height/o);return i.multiply(c)}_setDimensionsImpl(e,t){this._resetTransformEventData(),super._setDimensionsImpl(e,t),this._isCurrentlyDrawing&&this.freeDrawingBrush&&this.freeDrawingBrush._setBrushStyles(this.contextTop)}_createCacheCanvas(){this.pixelFindCanvasEl=P(),this.pixelFindContext=this.pixelFindCanvasEl.getContext(`2d`,{willReadFrequently:!0}),this.setTargetFindTolerance(this.targetFindTolerance)}getTopContext(){return this.elements.upper.ctx}getSelectionContext(){return this.elements.upper.ctx}getSelectionElement(){return this.elements.upper.el}getActiveObject(){return this._activeObject}getActiveObjects(){let e=this._activeObject;return at(e)?e.getObjects():e?[e]:[]}_fireSelectionEvents(e,t){let n=!1,r=!1,i=this.getActiveObjects(),a=[],o=[];e.forEach(e=>{i.includes(e)||(n=!0,e.fire(`deselected`,{e:t,target:e}),o.push(e))}),i.forEach(r=>{e.includes(r)||(n=!0,r.fire(`selected`,{e:t,target:r}),a.push(r))}),e.length>0&&i.length>0?(r=!0,n&&this.fire(`selection:updated`,{e:t,selected:a,deselected:o})):i.length>0?(r=!0,this.fire(`selection:created`,{e:t,selected:a})):e.length>0&&(r=!0,this.fire(`selection:cleared`,{e:t,deselected:o})),r&&(this._objectsToRender=void 0)}setActiveObject(e,t){let n=this.getActiveObjects(),r=this._setActiveObject(e,t);return this._fireSelectionEvents(n,t),r}_setActiveObject(e,t){let n=this._activeObject;return n!==e&&!(!this._discardActiveObject(t,e)&&this._activeObject)&&!e.onSelect({e:t})&&(this._activeObject=e,at(e)&&n!==e&&e.set(`canvas`,this),e.setCoords(),!0)}_discardActiveObject(e,t){let n=this._activeObject;return!!n&&!n.onDeselect({e,object:t})&&(this._currentTransform&&this._currentTransform.target===n&&this.endCurrentTransform(e),at(n)&&n===this._hoveredTarget&&(this._hoveredTarget=void 0),this._activeObject=void 0,!0)}discardActiveObject(e){let t=this.getActiveObjects(),n=this.getActiveObject();t.length&&this.fire(`before:selection:cleared`,{e,deselected:[n]});let r=this._discardActiveObject(e);return this._fireSelectionEvents(t,e),r}endCurrentTransform(e){let t=this._currentTransform;this._finalizeCurrentTransform(e),t&&t.target&&(t.target.isMoving=!1),this._currentTransform=null}_finalizeCurrentTransform(e){let t=this._currentTransform,n=t.target,r={e,target:n,transform:t,action:t.action};n._scaling&&(n._scaling=!1),n.setCoords(),t.actionPerformed&&(this.fire(`object:modified`,r),n.fire(ge,r))}setViewportTransform(e){super.setViewportTransform(e);let t=this._activeObject;t&&t.setCoords()}destroy(){let e=this._activeObject;at(e)&&(e.removeAll(),e.dispose()),delete this._activeObject,super.destroy(),this.pixelFindContext=null,this.pixelFindCanvasEl=void 0}clear(){this.discardActiveObject(),this._activeObject=void 0,this.clearContext(this.contextTop),super.clear()}drawControls(e){let t=this._activeObject;t&&t._renderControls(e)}_toObject(e,t,n){let r=this._realizeGroupTransformOnObject(e),i=super._toObject(e,t,n);return e.set(r),i}_realizeGroupTransformOnObject(e){let{group:t}=e;if(t&&at(t)&&this._activeObject===t){let n=et(e,[`angle`,`flipX`,`flipY`,D,de,fe,pe,me,`top`]);return Et(e,t.calcOwnMatrix()),n}return{}}_setSVGObject(e,t,n){let r=this._realizeGroupTransformOnObject(t);super._setSVGObject(e,t,n),t.set(r)}};i(lo,`ownDefaults`,{uniformScaling:!0,uniScaleKey:`shiftKey`,centeredScaling:!1,centeredRotation:!1,centeredKey:`altKey`,altActionKey:`shiftKey`,selection:!0,selectionKey:`shiftKey`,selectionColor:`rgba(100, 100, 255, 0.3)`,selectionDashArray:[],selectionBorderColor:`rgba(255, 255, 255, 0.3)`,selectionLineWidth:1,selectionFullyContained:!1,hoverCursor:`move`,moveCursor:`move`,defaultCursor:`default`,freeDrawingCursor:`crosshair`,notAllowedCursor:`not-allowed`,perPixelTargetFind:!1,targetFindTolerance:0,skipTargetFind:!1,stopContextMenu:!0,fireRightClick:!0,fireMiddleClick:!0,enablePointerEvents:!1,containerClass:`canvas-container`,preserveObjectStacking:!0});var uo=class{constructor(e){i(this,`targets`,[]),i(this,`__disposer`,void 0);let t=()=>{let{hiddenTextarea:t}=e.getActiveObject()||{};t&&t.focus()},n=e.upperCanvasEl;n.addEventListener(`click`,t),this.__disposer=()=>n.removeEventListener(`click`,t)}exitTextEditing(){this.target=void 0,this.targets.forEach(e=>{e.isEditing&&e.exitEditing()})}add(e){this.targets.push(e)}remove(e){this.unregister(e),xe(this.targets,e)}register(e){this.target=e}unregister(e){e===this.target&&(this.target=void 0)}onMouseMove(e){var t;(t=this.target)!=null&&t.isEditing&&this.target.updateSelectionOnMouseMove(e)}clear(){this.targets=[],this.target=void 0}dispose(){this.clear(),this.__disposer(),delete this.__disposer}};const X={passive:!1},fo=(e,t)=>({viewportPoint:e.getViewportPoint(t),scenePoint:e.getScenePoint(t)}),po=(e,...t)=>e.addEventListener(...t),Z=(e,...t)=>e.removeEventListener(...t),mo={mouse:{in:`over`,out:`out`,targetIn:`mouseover`,targetOut:`mouseout`,canvasIn:`mouse:over`,canvasOut:`mouse:out`},drag:{in:`enter`,out:`leave`,targetIn:`dragenter`,targetOut:`dragleave`,canvasIn:`drag:enter`,canvasOut:`drag:leave`}};var ho=class extends lo{constructor(e,t={}){super(e,t),i(this,`_isClick`,void 0),i(this,`textEditingManager`,new uo(this)),[`_onMouseDown`,`_onTouchStart`,`_onMouseMove`,`_onMouseUp`,`_onTouchEnd`,`_onResize`,`_onMouseWheel`,`_onMouseOut`,`_onMouseEnter`,`_onContextMenu`,`_onClick`,`_onDragStart`,`_onDragEnd`,`_onDragProgress`,`_onDragOver`,`_onDragEnter`,`_onDragLeave`,`_onDrop`].forEach(e=>{this[e]=this[e].bind(this)}),this.addOrRemove(po)}_getEventPrefix(){return this.enablePointerEvents?`pointer`:`mouse`}addOrRemove(e,t=!1){let n=this.upperCanvasEl,r=this._getEventPrefix();e(st(n),`resize`,this._onResize),e(n,r+`down`,this._onMouseDown),e(n,`${r}move`,this._onMouseMove,X),e(n,`${r}out`,this._onMouseOut),e(n,`${r}enter`,this._onMouseEnter),e(n,`wheel`,this._onMouseWheel,{passive:!1}),e(n,`contextmenu`,this._onContextMenu),t||(e(n,`click`,this._onClick),e(n,`dblclick`,this._onClick)),e(n,`dragstart`,this._onDragStart),e(n,`dragend`,this._onDragEnd),e(n,`dragover`,this._onDragOver),e(n,`dragenter`,this._onDragEnter),e(n,`dragleave`,this._onDragLeave),e(n,`drop`,this._onDrop),this.enablePointerEvents||e(n,`touchstart`,this._onTouchStart,X)}removeListeners(){this.addOrRemove(Z);let e=this._getEventPrefix(),t=H(this.upperCanvasEl);Z(t,`${e}up`,this._onMouseUp),Z(t,`touchend`,this._onTouchEnd,X),Z(t,`${e}move`,this._onMouseMove,X),Z(t,`touchmove`,this._onMouseMove,X),clearTimeout(this._willAddMouseDown)}_onMouseWheel(e){this._cacheTransformEventData(e),this._handleEvent(e,`wheel`),this._resetTransformEventData()}_onMouseOut(e){let t=this._hoveredTarget,n={e,...fo(this,e)};this.fire(`mouse:out`,{...n,target:t}),this._hoveredTarget=void 0,t&&t.fire(`mouseout`,{...n}),this._hoveredTargets.forEach(e=>{this.fire(`mouse:out`,{...n,target:e}),e&&e.fire(`mouseout`,{...n})}),this._hoveredTargets=[]}_onMouseEnter(e){let{target:t}=this.findTarget(e);this._currentTransform||t||(this.fire(`mouse:over`,{e,...fo(this,e)}),this._hoveredTarget=void 0,this._hoveredTargets=[])}_onDragStart(e){this._isClick=!1;let t=this.getActiveObject();if(t&&t.onDragStart(e)){this._dragSource=t;let n={e,target:t};this.fire(`dragstart`,n),t.fire(`dragstart`,n),po(this.upperCanvasEl,`drag`,this._onDragProgress);return}Ct(e)}_renderDragEffects(e,t,n){let r=!1,i=this._dropTarget;i&&i!==t&&i!==n&&(i.clearContextTop(),r=!0),t==null||t.clearContextTop(),n!==t&&(n==null||n.clearContextTop());let a=this.contextTop;a.save(),a.transform(...this.viewportTransform),t&&(a.save(),t.transform(a),t.renderDragSourceEffect(e),a.restore(),r=!0),n&&(a.save(),n.transform(a),n.renderDropTargetEffect(e),a.restore(),r=!0),a.restore(),r&&(this.contextTopDirty=!0)}_onDragEnd(e){let{currentSubTargets:t}=this.findTarget(e),n=!!e.dataTransfer&&e.dataTransfer.dropEffect!==`none`,r=n?this._activeObject:void 0,i={e,target:this._dragSource,subTargets:t,dragSource:this._dragSource,didDrop:n,dropTarget:r};Z(this.upperCanvasEl,`drag`,this._onDragProgress),this.fire(`dragend`,i),this._dragSource&&this._dragSource.fire(`dragend`,i),delete this._dragSource,this._onMouseUp(e)}_onDragProgress(e){let t={e,target:this._dragSource,dragSource:this._dragSource,dropTarget:this._draggedoverTarget};this.fire(`drag`,t),this._dragSource&&this._dragSource.fire(`drag`,t)}_onDragOver(e){let t=`dragover`,{currentContainer:n,currentSubTargets:r}=this.findTarget(e),i=this._dragSource,a={e,target:n,subTargets:r,dragSource:i,canDrop:!1,dropTarget:void 0},o;this.fire(t,a),this._fireEnterLeaveEvents(e,n,a),n&&(n.canDrop(e)&&(o=n),n.fire(t,a));for(let n=0;n<r.length;n++){let i=r[n];i.canDrop(e)&&(o=i),i.fire(t,a)}this._renderDragEffects(e,i,o),this._dropTarget=o}_onDragEnter(e){let{currentContainer:t,currentSubTargets:n}=this.findTarget(e),r={e,target:t,subTargets:n,dragSource:this._dragSource};this.fire(`dragenter`,r),this._fireEnterLeaveEvents(e,t,r)}_onDragLeave(e){let{currentSubTargets:t}=this.findTarget(e),n={e,target:this._draggedoverTarget,subTargets:t,dragSource:this._dragSource};this.fire(`dragleave`,n),this._fireEnterLeaveEvents(e,void 0,n),this._renderDragEffects(e,this._dragSource),this._dropTarget=void 0,this._hoveredTargets=[]}_onDrop(e){let{currentContainer:t,currentSubTargets:n}=this.findTarget(e),r=this._basicEventHandler(`drop:before`,{e,target:t,subTargets:n,dragSource:this._dragSource,...fo(this,e)});r.didDrop=!1,r.dropTarget=void 0,this._basicEventHandler(`drop`,r),this.fire(`drop:after`,r)}_onContextMenu(e){let{target:t,subTargets:n}=this.findTarget(e),r=this._basicEventHandler(`contextmenu:before`,{e,target:t,subTargets:n});return this.stopContextMenu&&Ct(e),this._basicEventHandler(`contextmenu`,r),!1}_onClick(e){let t=e.detail;t>3||t<2||(this._cacheTransformEventData(e),t==2&&e.type===`dblclick`&&this._handleEvent(e,`dblclick`),t==3&&this._handleEvent(e,`tripleclick`),this._resetTransformEventData())}fireEventFromPointerEvent(e,t,n,r={}){this._cacheTransformEventData(e);let{target:i,subTargets:a}=this.findTarget(e),o={e,target:i,subTargets:a,...fo(this,e),transform:this._currentTransform,...r};this.fire(t,o),i&&i.fire(n,o);for(let e=0;e<a.length;e++)a[e]!==i&&a[e].fire(n,o);this._resetTransformEventData()}getPointerId(e){let t=e.changedTouches;return t?t[0]&&t[0].identifier:this.enablePointerEvents?e.pointerId:-1}_isMainEvent(e){return!0===e.isPrimary||!1!==e.isPrimary&&(e.type===`touchend`&&e.touches.length===0||!e.changedTouches||e.changedTouches[0].identifier===this.mainTouchId)}_onTouchStart(e){this._cacheTransformEventData(e);let t=!this.allowTouchScrolling,n=this._activeObject;this.mainTouchId===void 0&&(this.mainTouchId=this.getPointerId(e)),this.__onMouseDown(e);let{target:r}=this.findTarget(e);(this.isDrawingMode||n&&r===n)&&(t=!0),t&&e.preventDefault();let i=this.upperCanvasEl,a=this._getEventPrefix(),o=H(i);po(o,`touchend`,this._onTouchEnd,X),t&&po(o,`touchmove`,this._onMouseMove,X),Z(i,`${a}down`,this._onMouseDown),this._resetTransformEventData()}_onMouseDown(e){this._cacheTransformEventData(e),this.__onMouseDown(e);let t=this.upperCanvasEl,n=this._getEventPrefix();Z(t,`${n}move`,this._onMouseMove,X);let r=H(t);po(r,`${n}up`,this._onMouseUp),po(r,`${n}move`,this._onMouseMove,X),this._resetTransformEventData()}_onTouchEnd(e){if(e.touches.length>0)return;this._cacheTransformEventData(e),this.__onMouseUp(e),this._resetTransformEventData(),delete this.mainTouchId;let t=this._getEventPrefix(),n=H(this.upperCanvasEl);Z(n,`touchend`,this._onTouchEnd,X),Z(n,`touchmove`,this._onMouseMove,X),this._willAddMouseDown&&clearTimeout(this._willAddMouseDown),this._willAddMouseDown=setTimeout(()=>{po(this.upperCanvasEl,`${t}down`,this._onMouseDown),this._willAddMouseDown=0},400)}_onMouseUp(e){this._cacheTransformEventData(e),this.__onMouseUp(e);let t=this.upperCanvasEl,n=this._getEventPrefix();if(this._isMainEvent(e)){let e=H(this.upperCanvasEl);Z(e,`${n}up`,this._onMouseUp),Z(e,`${n}move`,this._onMouseMove,X),po(t,`${n}move`,this._onMouseMove,X)}this._resetTransformEventData()}_onMouseMove(e){this._cacheTransformEventData(e);let t=this.getActiveObject();!this.allowTouchScrolling&&(!t||!t.shouldStartDragging(e))&&e.preventDefault&&e.preventDefault(),this.__onMouseMove(e),this._resetTransformEventData()}_onResize(){this.calcOffset(),this._resetTransformEventData()}_shouldRender(e){let t=this.getActiveObject();return!!t!=!!e||t&&e&&t!==e}__onMouseUp(e){var t;this._handleEvent(e,`up:before`);let n=this._currentTransform,r=this._isClick,{target:i}=this.findTarget(e),{button:a}=e;if(a)return void((this.fireMiddleClick&&a===1||this.fireRightClick&&a===2)&&this._handleEvent(e,`up`));if(this.isDrawingMode&&this._isCurrentlyDrawing)return void this._onMouseUpInDrawingMode(e);if(!this._isMainEvent(e))return;let o,s,c=!1;if(n&&(this._finalizeCurrentTransform(e),c=n.actionPerformed),!r){let t=i===this._activeObject;this.handleSelection(e),c||(c=this._shouldRender(i)||!t&&i===this._activeObject)}if(i){let{key:t,control:r}=i.findControl(this.getViewportPoint(e),St(e))||{};if(s=t,i.selectable&&i!==this._activeObject&&i.activeOn===`up`)this.setActiveObject(i,e),c=!0;else if(r){let t=r.getMouseUpHandler(e,i,r);t&&(o=this.getScenePoint(e),t.call(r,e,n,o.x,o.y))}i.isMoving=!1}if(n&&(n.target!==i||n.corner!==s)){let t=n.target&&n.target.controls[n.corner],r=t&&t.getMouseUpHandler(e,n.target,t);o=o||this.getScenePoint(e),r&&r.call(t,e,n,o.x,o.y)}this._setCursorFromEvent(e,i),this._handleEvent(e,`up`),this._groupSelector=null,this._currentTransform=null,i&&(i.__corner=void 0),c?this.requestRenderAll():r||(t=this._activeObject)!=null&&t.isEditing||this.renderTop()}_basicEventHandler(e,t){let{target:n,subTargets:r=[]}=t;this.fire(e,t),n&&n.fire(e,t);for(let i=0;i<r.length;i++)r[i]!==n&&r[i].fire(e,t);return t}_handleEvent(e,t,n){let{target:r,subTargets:i}=this.findTarget(e),a={e,target:r,subTargets:i,...fo(this,e),transform:this._currentTransform,...t===`down:before`||t===`down`?n:{}};t!==`up:before`&&t!==`up`||(a.isClick=this._isClick),this.fire(`mouse:${t}`,a),r&&r.fire(`mouse${t}`,a);for(let e=0;e<i.length;e++)i[e]!==r&&i[e].fire(`mouse${t}`,a)}_onMouseDownInDrawingMode(e){this._isCurrentlyDrawing=!0,this.getActiveObject()&&(this.discardActiveObject(e),this.requestRenderAll());let t=this.getScenePoint(e);this.freeDrawingBrush&&this.freeDrawingBrush.onMouseDown(t,{e,pointer:t}),this._handleEvent(e,`down`,{alreadySelected:!1})}_onMouseMoveInDrawingMode(e){if(this._isCurrentlyDrawing){let t=this.getScenePoint(e);this.freeDrawingBrush&&this.freeDrawingBrush.onMouseMove(t,{e,pointer:t})}this.setCursor(this.freeDrawingCursor),this._handleEvent(e,`move`)}_onMouseUpInDrawingMode(e){let t=this.getScenePoint(e);this.freeDrawingBrush?this._isCurrentlyDrawing=!!this.freeDrawingBrush.onMouseUp({e,pointer:t}):this._isCurrentlyDrawing=!1,this._handleEvent(e,`up`)}__onMouseDown(e){this._isClick=!0,this._handleEvent(e,`down:before`);let{target:t}=this.findTarget(e),n=!!t&&t===this._activeObject,{button:r}=e;if(r)return void((this.fireMiddleClick&&r===1||this.fireRightClick&&r===2)&&this._handleEvent(e,`down`,{alreadySelected:n}));if(this.isDrawingMode)return void this._onMouseDownInDrawingMode(e);if(!this._isMainEvent(e)||this._currentTransform)return;let i=this._shouldRender(t),a=!1;if(this.handleMultiSelection(e,t)?(t=this._activeObject,a=!0,i=!0):this._shouldClearSelection(e,t)&&this.discardActiveObject(e),this.selection&&(!t||!t.selectable&&!t.isEditing&&t!==this._activeObject)){let t=this.getScenePoint(e);this._groupSelector={x:t.x,y:t.y,deltaY:0,deltaX:0}}if(n=!!t&&t===this._activeObject,t){t.selectable&&t.activeOn===`down`&&this.setActiveObject(t,e);let r=t.findControl(this.getViewportPoint(e),St(e));if(t===this._activeObject&&(r||!a)){this._setupCurrentTransform(e,t,n);let i=r?r.control:void 0,a=this.getScenePoint(e),o=i&&i.getMouseDownHandler(e,t,i);o&&o.call(i,e,this._currentTransform,a.x,a.y)}}i&&(this._objectsToRender=void 0),this._handleEvent(e,`down`,{alreadySelected:n}),i&&this.requestRenderAll()}_resetTransformEventData(){this._targetInfo=this._viewportPoint=this._scenePoint=void 0}_cacheTransformEventData(e){this._resetTransformEventData(),this._viewportPoint=this.getViewportPoint(e),this._scenePoint=Mt(this._viewportPoint,void 0,this.viewportTransform),this._targetInfo=this.findTarget(e),this._currentTransform&&(this._targetInfo.target=this._currentTransform.target)}__onMouseMove(e){if(this._isClick=!1,this._handleEvent(e,`move:before`),this.isDrawingMode)return void this._onMouseMoveInDrawingMode(e);if(!this._isMainEvent(e))return;let t=this._groupSelector;if(t){let n=this.getScenePoint(e);t.deltaX=n.x-t.x,t.deltaY=n.y-t.y,this.renderTop()}else if(this._currentTransform)this._transformObject(e);else{let{target:t}=this.findTarget(e);this._setCursorFromEvent(e,t),this._fireOverOutEvents(e,t)}this.textEditingManager.onMouseMove(e),this._handleEvent(e,`move`)}_fireOverOutEvents(e,t){let{_hoveredTarget:n,_hoveredTargets:r}=this,{subTargets:i,currentTarget:a}=this.findTarget(e),o=Math.max(r.length,i.length);this.fireSyntheticInOutEvents(`mouse`,{e,target:t,oldTarget:n,actualTarget:a,oldActualTarget:this._hoveredActualTarget,fireCanvas:!0});for(let a=0;a<o;a++)i[a]===t||r[a]&&r[a]===n||this.fireSyntheticInOutEvents(`mouse`,{e,target:i[a],oldTarget:r[a]});this._hoveredActualTarget=a,this._hoveredTarget=t,this._hoveredTargets=i}_fireEnterLeaveEvents(e,t,n){let r=this._draggedoverTarget,i=this._hoveredTargets,{subTargets:a}=this.findTarget(e),o=Math.max(i.length,a.length);this.fireSyntheticInOutEvents(`drag`,{...n,target:t,oldTarget:r,fireCanvas:!0});for(let e=0;e<o;e++)this.fireSyntheticInOutEvents(`drag`,{...n,target:a[e],oldTarget:i[e]});this._draggedoverTarget=t}fireSyntheticInOutEvents(e,{target:t,oldTarget:n,actualTarget:r,oldActualTarget:i,fireCanvas:a,e:o,...s}){let{targetIn:c,targetOut:l,canvasIn:u,canvasOut:d}=mo[e],f=n!==t,p=i!==r,m=t&&f,h=r&&p,g=n&&f,_=i&&p,v={...s,e:o,...fo(this,o)},y={...v,target:n,nextTarget:t,actualTarget:i,nextActualTarget:r};(g||_)&&a&&this.fire(d,y),g&&n.fire(l,y),_&&n!==i&&i.fire(l,y);let b={...v,target:t,previousTarget:n,actualTarget:r,previousActualTarget:i};(m||h)&&a&&this.fire(u,b),m&&t.fire(c,b),h&&r!==t&&r.fire(c,b)}_transformObject(e){let t=this.getScenePoint(e),n=this._currentTransform,r=n.target,i=r.group?Mt(t,void 0,r.group.calcTransformMatrix()):t;n.shiftKey=e.shiftKey,n.altKey=!!this.centeredKey&&e[this.centeredKey],this._performTransformAction(e,n,i),n.actionPerformed&&this.requestRenderAll()}_performTransformAction(e,t,n){let{action:r,actionHandler:i,target:a}=t,o=!!i&&i(e,t,n.x,n.y);o&&a.setCoords(),r===`drag`&&o&&(t.target.isMoving=!0,this.setCursor(t.target.moveCursor||this.moveCursor)),t.actionPerformed=t.actionPerformed||o}_setCursorFromEvent(e,t){if(!t)return void this.setCursor(this.defaultCursor);let n=t.hoverCursor||this.hoverCursor,r=at(this._activeObject)?this._activeObject:null,i=(!r||t.group!==r)&&t.findControl(this.getViewportPoint(e));if(i){let{control:n,coord:r}=i;this.setCursor(n.cursorStyleHandler(e,n,t,r))}else{if(t.subTargetCheck){let{subTargets:t}=this.findTarget(e);t.concat().reverse().forEach(e=>{n=e.hoverCursor||n})}this.setCursor(n)}}handleMultiSelection(e,t){let n=this._activeObject,r=at(n);if(n&&this._isSelectionKeyPressed(e)&&this.selection&&t&&t.selectable&&(n!==t||r)&&(r||!t.isDescendantOf(n)&&!n.isDescendantOf(t))&&!t.onSelect({e})&&!n.getActiveControl()){if(r){let r=n.getObjects(),i=[];if(t===n){let n=this.getScenePoint(e),a=this.searchPossibleTargets(r,n);if(a.target?(t=a.target,i=a.subTargets):(a=this.searchPossibleTargets(this._objects,n),t=a.target,i=a.subTargets),!t||!t.selectable)return!1}t.group===n?(n.remove(t),this._hoveredTarget=t,this._hoveredTargets=i,n.size()===1&&this._setActiveObject(n.item(0),e)):(n.multiSelectAdd(t),this._hoveredTarget=n,this._hoveredTargets=i),this._fireSelectionEvents(r,e)}else{n.isEditing&&n.exitEditing();let r=new(M.getClass(`ActiveSelection`))([],{canvas:this});r.multiSelectAdd(n,t),this._hoveredTarget=r,this._setActiveObject(r,e),this._fireSelectionEvents([n],e)}return!0}return!1}handleSelection(e){if(!this.selection||!this._groupSelector)return!1;let{x:t,y:n,deltaX:r,deltaY:i}=this._groupSelector,a=new N(t,n),o=a.add(new N(r,i)),s=a.min(o),c=a.max(o).subtract(s),l=this.collectObjects({left:s.x,top:s.y,width:c.x,height:c.y},{includeIntersecting:!this.selectionFullyContained}),u=a.eq(o)?l[0]?[l[0]]:[]:l.length>1?l.filter(t=>!t.onSelect({e})).reverse():l;if(u.length===1)this.setActiveObject(u[0],e);else if(u.length>1){let t=M.getClass(`ActiveSelection`);this.setActiveObject(new t(u,{canvas:this}),e)}return this._groupSelector=null,!0}toCanvasElement(e=1,t){let{upper:n}=this.elements;n.ctx=void 0;let r=super.toCanvasElement(e,t);return n.ctx=n.el.getContext(`2d`),r}clear(){this.textEditingManager.clear(),super.clear()}destroy(){this.removeListeners(),this.textEditingManager.dispose(),super.destroy()}};const go={x1:0,y1:0,x2:0,y2:0},_o={...go,r1:0,r2:0},vo=(e,t)=>isNaN(e)&&typeof t==`number`?t:e;function yo(e){return e&&/%$/.test(e)&&Number.isFinite(parseFloat(e))}function bo(e,t){return Vn(0,vo(typeof e==`number`?e:typeof e==`string`?parseFloat(e)/(yo(e)?100:1):NaN,t),1)}const xo=/\s*;\s*/,So=/\s*:\s*/;function Co(e,t){let n,r,i=e.getAttribute(`style`);if(i){let e=i.split(xo);e[e.length-1]===``&&e.pop();for(let t=e.length;t--;){let[i,a]=e[t].split(So).map(e=>e.trim());i===`stop-color`?n=a:i===`stop-opacity`&&(r=a)}}n=n||e.getAttribute(`stop-color`)||`rgb(0,0,0)`,r=vo(parseFloat(r||e.getAttribute(`stop-opacity`)||``),1);let a=new G(n);return a.setAlpha(a.getAlpha()*r*t),{offset:bo(e.getAttribute(`offset`),0),color:a.toRgba()}}function wo(e,t){let n=[],r=e.getElementsByTagName(`stop`),i=bo(t,1);for(let e=r.length;e--;)n.push(Co(r[e],i));return n}function To(e){return e.nodeName===`linearGradient`||e.nodeName===`LINEARGRADIENT`?`linear`:`radial`}function Eo(e){return e.getAttribute(`gradientUnits`)===`userSpaceOnUse`?`pixels`:`percentage`}function Do(e,t){return e.getAttribute(t)}function Oo(e,t){return function(e,{width:t,height:n,gradientUnits:r}){let i;return Object.entries(e).reduce((e,[a,o])=>{if(o===`Infinity`)i=1;else if(o===`-Infinity`)i=0;else{let e=typeof o==`string`;i=e?parseFloat(o):o,e&&yo(o)&&(i*=.01,r===`pixels`&&(a!==`x1`&&a!==`x2`&&a!==`r2`||(i*=t),a!==`y1`&&a!==`y2`||(i*=n)))}return e[a]=i,e},{})}(To(e)===`linear`?function(e){return{x1:Do(e,`x1`)||0,y1:Do(e,`y1`)||0,x2:Do(e,`x2`)||`100%`,y2:Do(e,`y2`)||0}}(e):function(e){return{x1:Do(e,`fx`)||Do(e,`cx`)||`50%`,y1:Do(e,`fy`)||Do(e,`cy`)||`50%`,r1:0,x2:Do(e,`cx`)||`50%`,y2:Do(e,`cy`)||`50%`,r2:Do(e,`r`)||`50%`}}(e),{...t,gradientUnits:Eo(e)})}var ko=class{constructor(e){let{type:t=`linear`,gradientUnits:n=`pixels`,coords:r={},colorStops:i=[],offsetX:a=0,offsetY:o=0,gradientTransform:s,id:c}=e||{};Object.assign(this,{type:t,gradientUnits:n,coords:{...t===`radial`?_o:go,...r},colorStops:i,offsetX:a,offsetY:o,gradientTransform:s,id:c?`${c}_${je()}`:je()})}addColorStop(e){for(let t in e)this.colorStops.push({offset:parseFloat(t),color:e[t]});return this}toObject(e){return{...et(this,e),type:this.type,coords:{...this.coords},colorStops:this.colorStops.map(e=>({...e})),offsetX:this.offsetX,offsetY:this.offsetY,gradientUnits:this.gradientUnits,gradientTransform:this.gradientTransform?[...this.gradientTransform]:void 0}}toSVG(e,{additionalTransform:t}={}){let n=[],r=this.gradientTransform?this.gradientTransform.concat():T.concat(),i=this.gradientUnits===`pixels`?`userSpaceOnUse`:`objectBoundingBox`,a=this.colorStops.map(e=>({...e})).sort((e,t)=>e.offset-t.offset),o=-this.offsetX,s=-this.offsetY;var c;i===`objectBoundingBox`?(o/=e.width,s/=e.height):(o+=e.width/2,s+=e.height/2),(c=e)&&typeof c._renderPathCommands==`function`&&this.gradientUnits!==`percentage`&&(o-=e.pathOffset.x,s-=e.pathOffset.y),r[4]-=o,r[5]-=s;let l=[`id="SVGID_${U(String(this.id))}"`,`gradientUnits="${i}"`,`gradientTransform="${t?t+` `:``}${nt(r)}"`,``].join(` `),u=e=>parseFloat(String(e));if(this.type===`linear`){let{x1:e,y1:t,x2:r,y2:i}=this.coords,a=u(e),o=u(t),s=u(r),c=u(i);n.push(`<linearGradient `,l,` x1="`,a,`" y1="`,o,`" x2="`,s,`" y2="`,c,`">
`)}else if(this.type===`radial`){let{x1:e,y1:t,x2:r,y2:i,r1:o,r2:s}=this.coords,c=u(e),d=u(t),f=u(r),p=u(i),m=u(o),h=u(s),g=m>h;n.push(`<radialGradient `,l,` cx="`,g?c:f,`" cy="`,g?d:p,`" r="`,g?m:h,`" fx="`,g?f:c,`" fy="`,g?p:d,`">
`),g&&(a.reverse(),a.forEach(e=>{e.offset=1-e.offset}));let _=Math.min(m,h);if(_>0){let e=_/Math.max(m,h);a.forEach(t=>{t.offset+=e*(1-t.offset)})}}return a.forEach(({color:e,offset:t})=>{let r=String(e),i=nn(r)?r:new G(r).toRgba();n.push(`<stop offset="${100*t}%" style="stop-color:${U(i)};"/>\n`)}),n.push(this.type===`linear`?`</linearGradient>`:`</radialGradient>`,`
`),n.join(``)}toLive(e){let{x1:t,y1:n,x2:r,y2:i,r1:a,r2:o}=this.coords,s=this.type===`linear`?e.createLinearGradient(t,n,r,i):e.createRadialGradient(t,n,a,r,i,o);return this.colorStops.forEach(({color:e,offset:t})=>{s.addColorStop(t,e)}),s}static async fromObject(e){let{colorStops:t,gradientTransform:n}=e;return new this({...e,colorStops:t?t.map(e=>({...e})):void 0,gradientTransform:n?[...n]:void 0})}static fromElement(e,t,n){let r=Eo(e),i=t._findCenterFromElement();return new this({id:e.getAttribute(`id`)||void 0,type:To(e),coords:Oo(e,{width:n.viewBoxWidth||n.width,height:n.viewBoxHeight||n.height}),colorStops:wo(e,n.opacity),gradientUnits:r,gradientTransform:Ki(e.getAttribute(`gradientTransform`)||``),...r===`pixels`?{offsetX:t.width/2-i.x,offsetY:t.height/2-i.y}:{offsetX:0,offsetY:0}})}};i(ko,`type`,`Gradient`),M.setClass(ko,`gradient`),M.setClass(ko,`linear`),M.setClass(ko,`radial`);var Ao=class{get type(){return`pattern`}set type(e){s(`warn`,`Setting type has no effect`,e)}constructor(e){i(this,`repeat`,`repeat`),i(this,`offsetX`,0),i(this,`offsetY`,0),i(this,`crossOrigin`,``),this.id=je(),Object.assign(this,e)}isImageSource(){return!!this.source&&typeof this.source.src==`string`}isCanvasSource(){return!!this.source&&!!this.source.toDataURL}sourceToString(){return this.isImageSource()?this.source.src:this.isCanvasSource()?this.source.toDataURL():``}toLive(e){return this.source&&(!this.isImageSource()||this.source.complete&&this.source.naturalWidth!==0&&this.source.naturalHeight!==0)?e.createPattern(this.source,this.repeat):null}toObject(e=[]){let{repeat:t,crossOrigin:n}=this;return{...et(this,e),type:`pattern`,source:this.sourceToString(),repeat:t,crossOrigin:n,offsetX:B(this.offsetX,o.NUM_FRACTION_DIGITS),offsetY:B(this.offsetY,o.NUM_FRACTION_DIGITS),patternTransform:this.patternTransform?[...this.patternTransform]:null}}toSVG({width:e,height:t}){let{source:n,repeat:r,id:i}=this,a=vo(this.offsetX/e,0),o=vo(this.offsetY/t,0),s=r===`repeat-y`||r===`no-repeat`?1+Math.abs(a||0):vo(n.width/e,0),c=r===`repeat-x`||r===`no-repeat`?1+Math.abs(o||0):vo(n.height/t,0);return[`<pattern id="SVGID_${U(i)}" x="${a}" y="${o}" width="${s}" height="${c}">`,`<image x="0" y="0" width="${n.width}" height="${n.height}" xlink:href="${U(this.sourceToString())}"></image>`,`</pattern>`,``].join(`
`)}static async fromObject({type:e,source:t,patternTransform:n,...r},i){let a=await Ze(t,{...i,crossOrigin:r.crossOrigin});return new this({...r,patternTransform:n&&n.slice(0),source:a})}};i(Ao,`type`,`Pattern`),M.setClass(Ao),M.setClass(Ao,`pattern`);var jo=class{constructor(e){i(this,`color`,`rgb(0, 0, 0)`),i(this,`width`,1),i(this,`shadow`,null),i(this,`strokeLineCap`,`round`),i(this,`strokeLineJoin`,`round`),i(this,`strokeMiterLimit`,10),i(this,`strokeDashArray`,null),i(this,`limitedToCanvasSize`,!1),this.canvas=e}_setBrushStyles(e){e.strokeStyle=this.color,e.lineWidth=this.width,e.lineCap=this.strokeLineCap,e.miterLimit=this.strokeMiterLimit,e.lineJoin=this.strokeLineJoin,e.setLineDash(this.strokeDashArray||[])}_saveAndTransform(e){let t=this.canvas.viewportTransform;e.save(),e.transform(t[0],t[1],t[2],t[3],t[4],t[5])}needsFullRender(){return new G(this.color).getAlpha()<1||!!this.shadow}_setShadow(){if(!this.shadow||!this.canvas)return;let e=this.canvas,t=this.shadow,n=e.contextTop,r=e.getZoom()*e.getRetinaScaling();n.shadowColor=t.color,n.shadowBlur=t.blur*r,n.shadowOffsetX=t.offsetX*r,n.shadowOffsetY=t.offsetY*r}_resetShadow(){let e=this.canvas.contextTop;e.shadowColor=``,e.shadowBlur=e.shadowOffsetX=e.shadowOffsetY=0}_isOutSideCanvas(e){return e.x<0||e.x>this.canvas.getWidth()||e.y<0||e.y>this.canvas.getHeight()}},Mo=class e extends J{constructor(t,{path:n,left:r,top:i,...a}={}){super(),Object.assign(this,e.ownDefaults),this.setOptions(a),this._setPath(t||[],!0),typeof r==`number`&&this.set(`left`,r),typeof i==`number`&&this.set(`top`,i)}_setPath(e,t){this.path=ba(Array.isArray(e)?e:La(e)),this.setBoundingBox(t)}_findCenterFromElement(){let e=this._calcBoundsFromPath();return new N(e.left+e.width/2,e.top+e.height/2)}_renderPathCommands(e){let t=-this.pathOffset.x,n=-this.pathOffset.y;e.beginPath();for(let r of this.path)switch(r[0]){case`L`:e.lineTo(r[1]+t,r[2]+n);break;case`M`:e.moveTo(r[1]+t,r[2]+n);break;case`C`:e.bezierCurveTo(r[1]+t,r[2]+n,r[3]+t,r[4]+n,r[5]+t,r[6]+n);break;case`Q`:e.quadraticCurveTo(r[1]+t,r[2]+n,r[3]+t,r[4]+n);break;case`Z`:e.closePath()}}_render(e){this._renderPathCommands(e),this._renderPaintInOrder(e)}toString(){return`#<Path (${this.complexity()}): { "top": ${this.top}, "left": ${this.left} }>`}toObject(e=[]){return{...super.toObject(e),path:this.path.map(e=>e.slice())}}toDatalessObject(e=[]){let t=this.toObject(e);return this.sourcePath&&(delete t.path,t.sourcePath=this.sourcePath),t}_toSVG(){return[`<path `,`COMMON_PARTS`,`d="${Va(this.path,o.NUM_FRACTION_DIGITS)}" stroke-linecap="round" />\n`]}_getOffsetTransform(){let e=o.NUM_FRACTION_DIGITS;return` translate(${B(-this.pathOffset.x,e)}, ${B(-this.pathOffset.y,e)})`}toClipPathSVG(e){let t=this._getOffsetTransform();return`	`+this._createBaseClipPathSVGMarkup(this._toSVG(),{reviver:e,additionalTransform:t})}toSVG(e){let t=this._getOffsetTransform();return this._createBaseSVGMarkup(this._toSVG(),{reviver:e,additionalTransform:t})}complexity(){return this.path.length}setDimensions(){this.setBoundingBox()}setBoundingBox(e){let{width:t,height:n,pathOffset:r}=this._calcDimensions();this.set({width:t,height:n,pathOffset:r}),e&&this.setPositionByOrigin(r,`center`,`center`)}_calcBoundsFromPath(){let e=[],t=0,n=0,r=0,i=0;for(let a of this.path)switch(a[0]){case`L`:r=a[1],i=a[2],e.push({x:t,y:n},{x:r,y:i});break;case`M`:r=a[1],i=a[2],t=r,n=i;break;case`C`:e.push(...va(r,i,a[1],a[2],a[3],a[4],a[5],a[6])),r=a[5],i=a[6];break;case`Q`:e.push(...va(r,i,a[1],a[2],a[1],a[2],a[3],a[4])),r=a[3],i=a[4];break;case`Z`:r=t,i=n}return wt(e)}_calcDimensions(){let e=this._calcBoundsFromPath();return{...e,pathOffset:new N(e.left+e.width/2,e.top+e.height/2)}}static fromObject(e){return this._fromObject(e,{extraParam:`path`})}static async fromElement(e,t,n){let{d:r,...i}=Zi(e,this.ATTRIBUTE_NAMES,n);return new this(r,{...i,...t,left:void 0,top:void 0})}};i(Mo,`type`,`Path`),i(Mo,`cacheProperties`,[...Un,`path`,`fillRule`]),i(Mo,`ATTRIBUTE_NAMES`,[...ki,`d`]),M.setClass(Mo),M.setSVGClass(Mo);var No=class e extends jo{constructor(e){super(e),i(this,`decimate`,.4),i(this,`drawStraightLine`,!1),i(this,`straightLineKey`,`shiftKey`),this._points=[],this._hasStraightLine=!1}needsFullRender(){return super.needsFullRender()||this._hasStraightLine}static drawSegment(e,t,n){let r=t.midPointFrom(n);return e.quadraticCurveTo(t.x,t.y,r.x,r.y),r}onMouseDown(e,{e:t}){this.canvas._isMainEvent(t)&&(this.drawStraightLine=!!this.straightLineKey&&t[this.straightLineKey],this._prepareForDrawing(e),this._addPoint(e),this._render())}onMouseMove(t,{e:n}){if(this.canvas._isMainEvent(n)&&(this.drawStraightLine=!!this.straightLineKey&&n[this.straightLineKey],(!0!==this.limitedToCanvasSize||!this._isOutSideCanvas(t))&&this._addPoint(t)&&this._points.length>1))if(this.needsFullRender())this.canvas.clearContext(this.canvas.contextTop),this._render();else{let t=this._points,n=t.length,r=this.canvas.contextTop;this._saveAndTransform(r),this.oldEnd&&(r.beginPath(),r.moveTo(this.oldEnd.x,this.oldEnd.y)),this.oldEnd=e.drawSegment(r,t[n-2],t[n-1]),r.stroke(),r.restore()}}onMouseUp({e}){return!this.canvas._isMainEvent(e)||(this.drawStraightLine=!1,this.oldEnd=void 0,this._finalizeAndAddPath(),!1)}_prepareForDrawing(e){this._reset(),this._addPoint(e),this.canvas.contextTop.moveTo(e.x,e.y)}_addPoint(e){return!(this._points.length>1&&e.eq(this._points[this._points.length-1]))&&(this.drawStraightLine&&this._points.length>1&&(this._hasStraightLine=!0,this._points.pop()),this._points.push(e),!0)}_reset(){this._points=[],this._setBrushStyles(this.canvas.contextTop),this._setShadow(),this._hasStraightLine=!1}_render(t=this.canvas.contextTop){let n=this._points[0],r=this._points[1];if(this._saveAndTransform(t),t.beginPath(),this._points.length===2&&n.x===r.x&&n.y===r.y){let e=this.width/1e3;n.x-=e,r.x+=e}t.moveTo(n.x,n.y);for(let i=1;i<this._points.length;i++)e.drawSegment(t,n,r),n=this._points[i],r=this._points[i+1];t.lineTo(n.x,n.y),t.stroke(),t.restore()}convertPointsToSVGPath(e){return Ra(e,this.width/1e3)}createPath(e){let t=new Mo(e,{fill:null,stroke:this.color,strokeWidth:this.width,strokeLineCap:this.strokeLineCap,strokeMiterLimit:this.strokeMiterLimit,strokeLineJoin:this.strokeLineJoin,strokeDashArray:this.strokeDashArray});return this.shadow&&(this.shadow.affectStroke=!0,t.shadow=new Bn(this.shadow)),t}decimatePoints(e,t){if(e.length<=2)return e;let n,r=e[0],i=(t/this.canvas.getZoom())**2,a=e.length-1,o=[r];for(let t=1;t<a-1;t++)n=(r.x-e[t].x)**2+(r.y-e[t].y)**2,n>=i&&(r=e[t],o.push(r));return o.push(e[a]),o}_finalizeAndAddPath(){this.canvas.contextTop.closePath(),this.decimate&&(this._points=this.decimatePoints(this._points,this.decimate));let e=this.convertPointsToSVGPath(this._points);if(function(e){return Va(e)===`M 0 0 Q 0 0 0 0 L 0 0`}(e))return void this.canvas.requestRenderAll();let t=this.createPath(e);this.canvas.clearContext(this.canvas.contextTop),this.canvas.fire(`before:path:created`,{path:t}),this.canvas.add(t),this.canvas.requestRenderAll(),t.setCoords(),this._resetShadow(),this.canvas.fire(`path:created`,{path:t})}};const Po=[`radius`,`startAngle`,`endAngle`,`counterClockwise`];var Fo=class e extends J{static getDefaults(){return{...super.getDefaults(),...e.ownDefaults}}constructor(t){super(),Object.assign(this,e.ownDefaults),this.setOptions(t)}_set(e,t){return super._set(e,t),e===`radius`&&this.setRadius(t),this}_render(e){e.beginPath(),e.arc(0,0,this.radius,I(this.startAngle),I(this.endAngle),this.counterClockwise),this._renderPaintInOrder(e)}getRadiusX(){return this.get(`radius`)*this.get(de)}getRadiusY(){return this.get(`radius`)*this.get(fe)}setRadius(e){this.radius=e,this.set({width:2*e,height:2*e})}toObject(e=[]){return super.toObject([...Po,...e])}_toSVG(){let{radius:e,startAngle:t,endAngle:n}=this,r=(n-t)%360;if(r===0)return[`<circle `,`COMMON_PARTS`,`cx="0" cy="0" `,`r="`,`${U(e)}`,`" />
`];{let i=I(t),a=I(n),o=Se(i)*e,s=Ce(i)*e,c=Se(a)*e,l=Ce(a)*e;return[`<path d="M ${o} ${s} A ${e} ${e} 0 ${+(r>180)} ${+!this.counterClockwise} ${c} ${l}" `,`COMMON_PARTS`,` />
`]}}static async fromElement(e,t,n){let{left:r=0,top:i=0,radius:a=0,...o}=Zi(e,this.ATTRIBUTE_NAMES,n);return new this({...o,radius:a,left:r-a,top:i-a})}static fromObject(e){return super._fromObject(e)}};i(Fo,`type`,`Circle`),i(Fo,`cacheProperties`,[...Un,...Po]),i(Fo,`ownDefaults`,{radius:0,startAngle:0,endAngle:360,counterClockwise:!1}),i(Fo,`ATTRIBUTE_NAMES`,[`cx`,`cy`,`r`,...ki]),M.setClass(Fo),M.setSVGClass(Fo);var Io=class extends jo{constructor(e){super(e),i(this,`width`,10),this.points=[]}drawDot(e){let t=this.addPoint(e),n=this.canvas.contextTop;this._saveAndTransform(n),this.dot(n,t),n.restore()}dot(e,t){e.fillStyle=t.fill,e.beginPath(),e.arc(t.x,t.y,t.radius,0,2*Math.PI,!1),e.closePath(),e.fill()}onMouseDown(e){this.points=[],this.canvas.clearContext(this.canvas.contextTop),this._setShadow(),this.drawDot(e)}_render(){let e=this.canvas.contextTop,t=this.points;this._saveAndTransform(e);for(let n=0;n<t.length;n++)this.dot(e,t[n]);e.restore()}onMouseMove(e){!0===this.limitedToCanvasSize&&this._isOutSideCanvas(e)||(this.needsFullRender()?(this.canvas.clearContext(this.canvas.contextTop),this.addPoint(e),this._render()):this.drawDot(e))}onMouseUp(){let e=this.canvas.renderOnAddRemove;this.canvas.renderOnAddRemove=!1;let t=[];for(let e=0;e<this.points.length;e++){let n=this.points[e],r=new Fo({radius:n.radius,left:n.x,top:n.y,originX:E,originY:E,fill:n.fill});this.shadow&&(r.shadow=new Bn(this.shadow)),t.push(r)}let n=new ca(t,{canvas:this.canvas});this.canvas.fire(`before:path:created`,{path:n}),this.canvas.add(n),this.canvas.fire(`path:created`,{path:n}),this.canvas.clearContext(this.canvas.contextTop),this._resetShadow(),this.canvas.renderOnAddRemove=e,this.canvas.requestRenderAll()}addPoint({x:e,y:t}){let n={x:e,y:t,radius:Ua(Math.max(0,this.width-20),this.width+20)/2,fill:new G(this.color).setAlpha(Ua(0,100)/100).toRgba()};return this.points.push(n),n}},Lo=class extends jo{constructor(e){super(e),i(this,`width`,10),i(this,`density`,20),i(this,`dotWidth`,1),i(this,`dotWidthVariance`,1),i(this,`randomOpacity`,!1),i(this,`optimizeOverlapping`,!0),this.sprayChunks=[],this.sprayChunk=[]}onMouseDown(e){this.sprayChunks=[],this.canvas.clearContext(this.canvas.contextTop),this._setShadow(),this.addSprayChunk(e),this.renderChunck(this.sprayChunk)}onMouseMove(e){!0===this.limitedToCanvasSize&&this._isOutSideCanvas(e)||(this.addSprayChunk(e),this.renderChunck(this.sprayChunk))}onMouseUp(){let e=this.canvas.renderOnAddRemove;this.canvas.renderOnAddRemove=!1;let t=[];for(let e=0;e<this.sprayChunks.length;e++){let n=this.sprayChunks[e];for(let e=0;e<n.length;e++){let r=n[e],i=new $i({width:r.width,height:r.width,left:r.x+1,top:r.y+1,originX:E,originY:E,fill:this.color});t.push(i)}}let n=new ca(this.optimizeOverlapping?function(e){let t={},n=[];for(let r,i=0;i<e.length;i++)r=`${e[i].left}${e[i].top}`,t[r]||(t[r]=!0,n.push(e[i]));return n}(t):t,{objectCaching:!0,subTargetCheck:!1,interactive:!1});this.shadow&&n.set(`shadow`,new Bn(this.shadow)),this.canvas.fire(`before:path:created`,{path:n}),this.canvas.add(n),this.canvas.fire(`path:created`,{path:n}),this.canvas.clearContext(this.canvas.contextTop),this._resetShadow(),this.canvas.renderOnAddRemove=e,this.canvas.requestRenderAll()}renderChunck(e){let t=this.canvas.contextTop;t.fillStyle=this.color,this._saveAndTransform(t);for(let n=0;n<e.length;n++){let r=e[n];t.globalAlpha=r.opacity,t.fillRect(r.x,r.y,r.width,r.width)}t.restore()}_render(){let e=this.canvas.contextTop;e.fillStyle=this.color,this._saveAndTransform(e);for(let e=0;e<this.sprayChunks.length;e++)this.renderChunck(this.sprayChunks[e]);e.restore()}addSprayChunk(e){this.sprayChunk=[];let t=this.width/2;for(let n=0;n<this.density;n++)this.sprayChunk.push({x:Ua(e.x-t,e.x+t),y:Ua(e.y-t,e.y+t),width:this.dotWidthVariance?Ua(Math.max(1,this.dotWidth-this.dotWidthVariance),this.dotWidth+this.dotWidthVariance):this.dotWidth,opacity:this.randomOpacity?Ua(0,100)/100:1});this.sprayChunks.push(this.sprayChunk)}},Ro=class extends No{constructor(e){super(e)}getPatternSrc(){let e=P(),t=e.getContext(`2d`);return e.width=e.height=25,t&&(t.fillStyle=this.color,t.beginPath(),t.arc(10,10,10,0,2*Math.PI,!1),t.closePath(),t.fill()),e}getPattern(e){return e.createPattern(this.source||this.getPatternSrc(),`repeat`)}_setBrushStyles(e){super._setBrushStyles(e);let t=this.getPattern(e);t&&(e.strokeStyle=t)}createPath(e){let t=super.createPath(e),n=t._getLeftTopCoords().scalarAdd(t.strokeWidth/2);return t.stroke=new Ao({source:this.source||this.getPatternSrc(),offsetX:-n.x,offsetY:-n.y}),t}};const zo=[`x1`,`x2`,`y1`,`y2`];var Bo=class e extends J{constructor([t,n,r,i]=[0,0,0,0],a={}){super(),Object.assign(this,e.ownDefaults),this.setOptions(a),this.x1=t,this.x2=r,this.y1=n,this.y2=i,this._setWidthHeight();let{left:o,top:s}=a;typeof o==`number`&&this.set(`left`,o),typeof s==`number`&&this.set(`top`,s)}_setWidthHeight(){let{x1:e,y1:t,x2:n,y2:r}=this;this.width=Math.abs(n-e),this.height=Math.abs(r-t);let{left:i,top:a,width:o,height:s}=wt([{x:e,y:t},{x:n,y:r}]),c=new N(i+o/2,a+s/2);this.setPositionByOrigin(c,E,E)}_set(e,t){return super._set(e,t),zo.includes(e)&&this._setWidthHeight(),this}_render(e){e.beginPath();let t=this.calcLinePoints();e.moveTo(t.x1,t.y1),e.lineTo(t.x2,t.y2),e.lineWidth=this.strokeWidth;let n=e.strokeStyle;var r;V(this.stroke)?e.strokeStyle=this.stroke.toLive(e):e.strokeStyle=(r=this.stroke)==null?e.fillStyle:r,this.stroke&&this._renderStroke(e),e.strokeStyle=n}_findCenterFromElement(){return new N((this.x1+this.x2)/2,(this.y1+this.y2)/2)}toObject(e=[]){return{...super.toObject(e),...this.calcLinePoints()}}_getNonTransformedDimensions(){let e=super._getNonTransformedDimensions();return this.strokeLineCap===`butt`&&(this.width===0&&(e.y-=this.strokeWidth),this.height===0&&(e.x-=this.strokeWidth)),e}calcLinePoints(){let{x1:e,x2:t,y1:n,y2:r,width:i,height:a}=this,o=e<=t?-.5:.5,s=n<=r?-.5:.5;return{x1:o*i,x2:o*-i,y1:s*a,y2:s*-a}}_toSVG(){let{x1:e,x2:t,y1:n,y2:r}=this.calcLinePoints();return[`<line `,`COMMON_PARTS`,`x1="${e}" y1="${n}" x2="${t}" y2="${r}" />\n`]}static async fromElement(e,t,n){let{x1:r=0,y1:i=0,x2:a=0,y2:o=0,...s}=Zi(e,this.ATTRIBUTE_NAMES,n);return new this([r,i,a,o],s)}static fromObject({x1:e,y1:t,x2:n,y2:r,...i}){return this._fromObject({...i,points:[e,t,n,r]},{extraParam:`points`})}};i(Bo,`type`,`Line`),i(Bo,`cacheProperties`,[...Un,...zo]),i(Bo,`ATTRIBUTE_NAMES`,ki.concat(zo)),M.setClass(Bo),M.setSVGClass(Bo);var Vo=class e extends J{static getDefaults(){return{...super.getDefaults(),...e.ownDefaults}}constructor(t){super(),Object.assign(this,e.ownDefaults),this.setOptions(t)}_render(e){let t=this.width/2,n=this.height/2;e.beginPath(),e.moveTo(-t,n),e.lineTo(0,-n),e.lineTo(t,n),e.closePath(),this._renderPaintInOrder(e)}_toSVG(){let e=this.width/2,t=this.height/2;return[`<polygon `,`COMMON_PARTS`,`points="`,`${-e} ${t},0 ${-t},${e} ${t}`,`" />`]}};i(Vo,`type`,`Triangle`),i(Vo,`ownDefaults`,{width:100,height:100}),M.setClass(Vo),M.setSVGClass(Vo);const Ho=[`rx`,`ry`];var Uo=class e extends J{static getDefaults(){return{...super.getDefaults(),...e.ownDefaults}}constructor(t){super(),Object.assign(this,e.ownDefaults),this.setOptions(t)}_set(e,t){switch(super._set(e,t),e){case`rx`:this.rx=t,this.set(`width`,2*t);break;case`ry`:this.ry=t,this.set(`height`,2*t)}return this}getRx(){return this.get(`rx`)*this.get(de)}getRy(){return this.get(`ry`)*this.get(fe)}toObject(e=[]){return super.toObject([...Ho,...e])}_toSVG(){return[`<ellipse `,`COMMON_PARTS`,`cx="0" cy="0" rx="${U(this.rx)}" ry="${U(this.ry)}" />\n`]}_render(e){e.beginPath(),e.save(),e.transform(1,0,0,this.ry/this.rx,0,0),e.arc(0,0,this.rx,0,w,!1),e.restore(),this._renderPaintInOrder(e)}static async fromElement(e,t,n){let r=Zi(e,this.ATTRIBUTE_NAMES,n);return r.left=(r.left||0)-r.rx,r.top=(r.top||0)-r.ry,new this(r)}};i(Uo,`type`,`Ellipse`),i(Uo,`cacheProperties`,[...Un,...Ho]),i(Uo,`ownDefaults`,{rx:0,ry:0}),i(Uo,`ATTRIBUTE_NAMES`,[...ki,`cx`,`cy`,`rx`,`ry`]),M.setClass(Uo),M.setSVGClass(Uo);const Wo={exactBoundingBox:!1};var Go=class e extends J{static getDefaults(){return{...super.getDefaults(),...e.ownDefaults}}constructor(t=[],n={}){super(),i(this,`strokeDiff`,void 0),Object.assign(this,e.ownDefaults),this.setOptions(n),this.points=t;let{left:r,top:a}=n;this.initialized=!0,this.setBoundingBox(!0),typeof r==`number`&&this.set(`left`,r),typeof a==`number`&&this.set(`top`,a)}isOpen(){return!0}_projectStrokeOnPoints(e){return wi(this.points,e,this.isOpen())}_calcDimensions(e){e={scaleX:this.scaleX,scaleY:this.scaleY,skewX:this.skewX,skewY:this.skewY,strokeLineCap:this.strokeLineCap,strokeLineJoin:this.strokeLineJoin,strokeMiterLimit:this.strokeMiterLimit,strokeUniform:this.strokeUniform,strokeWidth:this.strokeWidth,...e||{}};let t=this.exactBoundingBox?this._projectStrokeOnPoints(e).map(e=>e.projectedPoint):this.points;if(t.length===0)return{left:0,top:0,width:0,height:0,pathOffset:new N,strokeOffset:new N,strokeDiff:new N};let n=wt(t),r=Ye({...e,scaleX:1,scaleY:1}),i=wt(this.points.map(e=>L(e,r,!0))),a=new N(this.scaleX,this.scaleY),o=n.left+n.width/2,s=n.top+n.height/2;return this.exactBoundingBox&&(o-=s*Math.tan(I(this.skewX)),s-=o*Math.tan(I(this.skewY))),{...n,pathOffset:new N(o,s),strokeOffset:new N(i.left,i.top).subtract(new N(n.left,n.top)).multiply(a),strokeDiff:new N(n.width,n.height).subtract(new N(i.width,i.height)).multiply(a)}}_findCenterFromElement(){let e=wt(this.points);return new N(e.left+e.width/2,e.top+e.height/2)}setDimensions(){this.setBoundingBox()}setBoundingBox(e){let{left:t,top:n,width:r,height:i,pathOffset:a,strokeOffset:o,strokeDiff:s}=this._calcDimensions();this.set({width:r,height:i,pathOffset:a,strokeOffset:o,strokeDiff:s}),e&&this.setPositionByOrigin(new N(t+r/2,n+i/2),`center`,`center`)}isStrokeAccountedForInDimensions(){return this.exactBoundingBox}_getNonTransformedDimensions(){return this.exactBoundingBox?new N(this.width,this.height):super._getNonTransformedDimensions()}_getTransformedDimensions(e={}){if(this.exactBoundingBox){let a;if(Object.keys(e).some(e=>this.strokeUniform||this.constructor.layoutProperties.includes(e))){var t,n;let{width:r,height:i}=this._calcDimensions(e);a=new N((t=e.width)==null?r:t,(n=e.height)==null?i:n)}else{var r,i;a=new N((r=e.width)==null?this.width:r,(i=e.height)==null?this.height:i)}return a.multiply(new N(e.scaleX||this.scaleX,e.scaleY||this.scaleY))}return super._getTransformedDimensions(e)}_set(e,t){let n=this.initialized&&this[e]!==t,r=super._set(e,t);return this.exactBoundingBox&&n&&((e===`scaleX`||e===`scaleY`)&&this.strokeUniform&&this.constructor.layoutProperties.includes(`strokeUniform`)||this.constructor.layoutProperties.includes(e))&&this.setDimensions(),r}toObject(e=[]){return{...super.toObject(e),points:this.points.map(({x:e,y:t})=>({x:e,y:t}))}}_toSVG(){let e=this.pathOffset.x,t=this.pathOffset.y,n=o.NUM_FRACTION_DIGITS,r=this.points.map(({x:r,y:i})=>`${B(r-e,n)},${B(i-t,n)}`).join(` `);return[`<${U(this.constructor.type).toLowerCase()} `,`COMMON_PARTS`,`points="${r}" />\n`]}_render(e){let t=this.points.length,n=this.pathOffset.x,r=this.pathOffset.y;if(t&&!isNaN(this.points[t-1].y)){e.beginPath(),e.moveTo(this.points[0].x-n,this.points[0].y-r);for(let i=0;i<t;i++){let t=this.points[i];e.lineTo(t.x-n,t.y-r)}!this.isOpen()&&e.closePath(),this._renderPaintInOrder(e)}}complexity(){return this.points.length}static async fromElement(e,t,n){let r=function(e){if(!e)return[];let t=e.replace(/,/g,` `).trim().split(/\s+/),n=[];for(let e=0;e<t.length;e+=2)n.push({x:parseFloat(t[e]),y:parseFloat(t[e+1])});return n}(e.getAttribute(`points`)),{left:i,top:a,...o}=Zi(e,this.ATTRIBUTE_NAMES,n);return new this(r,{...o,...t})}static fromObject(e){return this._fromObject(e,{extraParam:`points`})}};i(Go,`ownDefaults`,Wo),i(Go,`type`,`Polyline`),i(Go,`layoutProperties`,[pe,me,`strokeLineCap`,`strokeLineJoin`,`strokeMiterLimit`,`strokeWidth`,`strokeUniform`,`points`]),i(Go,`cacheProperties`,[...Un,`points`]),i(Go,`ATTRIBUTE_NAMES`,[...ki]),M.setClass(Go),M.setSVGClass(Go);var Ko=class extends Go{isOpen(){return!1}};i(Ko,`ownDefaults`,Wo),i(Ko,`type`,`Polygon`),M.setClass(Ko),M.setSVGClass(Ko);var qo=class extends J{isEmptyStyles(e){if(!this.styles||e!==void 0&&!this.styles[e])return!0;let t=e===void 0?this.styles:{line:this.styles[e]};for(let e in t)for(let n in t[e])for(let r in t[e][n])return!1;return!0}styleHas(e,t){if(!this.styles||t!==void 0&&!this.styles[t])return!1;let n=t===void 0?this.styles:{0:this.styles[t]};for(let t in n)for(let r in n[t])if(n[t][r][e]!==void 0)return!0;return!1}cleanStyle(e){if(!this.styles)return!1;let t=this.styles,n,r,i=0,a=!0,o=0;for(let o in t){n=0;for(let s in t[o]){let c=t[o][s]||{};i++,c[e]===void 0?a=!1:(r?c[e]!==r&&(a=!1):r=c[e],c[e]===this[e]&&delete c[e]),Object.keys(c).length===0?delete t[o][s]:n++}n===0&&delete t[o]}for(let e=0;e<this._textLines.length;e++)o+=this._textLines[e].length;a&&i===o&&(this[e]=r,this.removeStyle(e))}removeStyle(e){if(!this.styles)return;let t=this.styles,n,r,i;for(r in t){for(i in n=t[r],n)delete n[i][e],Object.keys(n[i]).length===0&&delete n[i];Object.keys(n).length===0&&delete t[r]}}_extendStyles(e,t){let{lineIndex:n,charIndex:r}=this.get2DCursorLocation(e);this._getLineStyle(n)||this._setLineStyle(n);let i=tt({...this._getStyleDeclaration(n,r),...t},e=>e!==void 0);this._setStyleDeclaration(n,r,i)}getSelectionStyles(e,t,n){let r=[];for(let i=e;i<(t||e);i++)r.push(this.getStyleAtPosition(i,n));return r}getStyleAtPosition(e,t){let{lineIndex:n,charIndex:r}=this.get2DCursorLocation(e);return t?this.getCompleteStyleDeclaration(n,r):this._getStyleDeclaration(n,r)}setSelectionStyles(e,t,n){for(let r=t;r<(n||t);r++)this._extendStyles(r,e);this._forceClearCache=!0}_getStyleDeclaration(e,t){var n;let r=this.styles&&this.styles[e];return r&&(n=r[t])!=null?n:{}}getCompleteStyleDeclaration(e,t){return{...et(this,this.constructor._styleProperties),...this._getStyleDeclaration(e,t)}}_setStyleDeclaration(e,t,n){this.styles[e][t]=n}_deleteStyleDeclaration(e,t){delete this.styles[e][t]}_getLineStyle(e){return!!this.styles[e]}_setLineStyle(e){this.styles[e]={}}_deleteLineStyle(e){delete this.styles[e]}};i(qo,`_styleProperties`,wn);const Jo=/  +/g,Yo=/"/g;function Xo(e,t,n,r,i){return`\t\t${((e,{left:t,top:n,width:r,height:i},a=o.NUM_FRACTION_DIGITS)=>{let s=hn(j,e,!1),[c,l,u,d]=[t,n,r,i].map(e=>B(e,a));return`<rect ${s} x="${c}" y="${l}" width="${u}" height="${d}"></rect>`})(e,{left:t,top:n,width:r,height:i})}\n`}let Zo;var Q=class e extends qo{static getDefaults(){return{...super.getDefaults(),...e.ownDefaults}}constructor(t,n){super(),i(this,`__charBounds`,[]),Object.assign(this,e.ownDefaults),this.setOptions(n),this.styles||(this.styles={}),this.text=t,this.initialized=!0,this.path&&this.setPathInfo(),this.initDimensions(),this.setCoords()}setPathInfo(){let e=this.path;e&&(e.segmentsInfo=ja(e.path))}_splitText(){let e=this._splitTextIntoLines(this.text);return this.textLines=e.lines,this._textLines=e.graphemeLines,this._unwrappedTextLines=e._unwrappedLines,this._text=e.graphemeText,e}initDimensions(){this._splitText(),this._clearCache(),this.dirty=!0,this.path?(this.width=this.path.width,this.height=this.path.height):(this.width=this.calcTextWidth()||this.cursorWidth||this.MIN_TEXT_WIDTH,this.height=this.calcTextHeight()),this.textAlign.includes(`justify`)&&this.enlargeSpaces()}enlargeSpaces(){let e,t,n,r,i,a,o;for(let s=0,c=this._textLines.length;s<c;s++)if((this.textAlign===`justify`||s!==c-1&&!this.isEndOfWrapping(s))&&(r=0,i=this._textLines[s],t=this.getLineWidth(s),t<this.width&&(o=this.textLines[s].match(this._reSpacesAndTabs)))){n=o.length,e=(this.width-t)/n;for(let t=0;t<=i.length;t++)a=this.__charBounds[s][t],this._reSpaceAndTab.test(i[t])?(a.width+=e,a.kernedWidth+=e,a.left+=r,r+=e):a.left+=r}}isEndOfWrapping(e){return e===this._textLines.length-1}missingNewlineOffset(e){return 1}get2DCursorLocation(e,t){let n=t?this._unwrappedTextLines:this._textLines,r;for(r=0;r<n.length;r++){if(e<=n[r].length)return{lineIndex:r,charIndex:e};e-=n[r].length+this.missingNewlineOffset(r,t)}return{lineIndex:r-1,charIndex:n[r-1].length<e?n[r-1].length:e}}toString(){return`#<Text (${this.complexity()}): { "text": "${this.text}", "fontFamily": "${this.fontFamily}" }>`}_getCacheCanvasDimensions(){let e=super._getCacheCanvasDimensions(),t=this.fontSize;return e.width+=t*e.zoomX,e.height+=t*e.zoomY,e}_render(e){let t=this.path;t&&!t.isNotVisible()&&t._render(e),this._setTextStyles(e),this._renderTextLinesBackground(e),this._renderTextDecoration(e,`underline`),this._renderText(e),this._renderTextDecoration(e,`overline`),this._renderTextDecoration(e,`linethrough`)}_renderText(e){this.paintFirst===`stroke`?(this._renderTextStroke(e),this._renderTextFill(e)):(this._renderTextFill(e),this._renderTextStroke(e))}_setTextStyles(e,t,n){if(e.textBaseline=`alphabetic`,this.path)switch(this.pathAlign){case E:e.textBaseline=`middle`;break;case`ascender`:e.textBaseline=`top`;break;case`descender`:e.textBaseline=O}e.font=this._getFontDeclaration(t,n)}calcTextWidth(){let e=this.getLineWidth(0);for(let t=1,n=this._textLines.length;t<n;t++){let n=this.getLineWidth(t);n>e&&(e=n)}return e}_renderTextLine(e,t,n,r,i,a){this._renderChars(e,t,n,r,i,a)}_renderTextLinesBackground(e){if(!this.textBackgroundColor&&!this.styleHas(`textBackgroundColor`))return;let t=e.fillStyle,n=this._getLeftOffset(),r=this._getTopOffset();for(let t=0,i=this._textLines.length;t<i;t++){let i=this.getHeightOfLine(t);if(!this.textBackgroundColor&&!this.styleHas(`textBackgroundColor`,t)){r+=i;continue}let a=this._textLines[t].length,o=this._getLineLeftOffset(t),s,c,l=0,u=0,d=this.getValueOfPropertyAt(t,0,`textBackgroundColor`),f=this.getHeightOfLineImpl(t);for(let i=0;i<a;i++){let a=this.__charBounds[t][i];c=this.getValueOfPropertyAt(t,i,`textBackgroundColor`),this.path?(e.save(),e.translate(a.renderLeft,a.renderTop),e.rotate(a.angle),e.fillStyle=c,c&&e.fillRect(-a.width/2,-f*(1-this._fontSizeFraction),a.width,f),e.restore()):c===d?l+=a.kernedWidth:(s=n+o+u,this.direction===`rtl`&&(s=this.width-s-l),e.fillStyle=d,d&&e.fillRect(s,r,l,f),u=a.left,l=a.width,d=c)}c&&!this.path&&(s=n+o+u,this.direction===`rtl`&&(s=this.width-s-l),e.fillStyle=c,e.fillRect(s,r,l,f)),r+=i}e.fillStyle=t,this._removeShadow(e)}_measureChar(e,t,n,r){let i=y.getFontCache(t),a=this._getFontDeclaration(t),o=n?n+e:e,s=n&&a===this._getFontDeclaration(r),c=t.fontSize/this.CACHE_FONT_SIZE,l,u,d,f;if(n&&i.has(n)&&(d=i.get(n)),i.has(e)&&(f=l=i.get(e)),s&&i.has(o)&&(u=i.get(o),f=u-d),l===void 0||d===void 0||u===void 0){let r=(Zo||(Zo=F({width:0,height:0}).getContext(`2d`)),Zo);this._setTextStyles(r,t,!0),l===void 0&&(f=l=r.measureText(e).width,i.set(e,l)),d===void 0&&s&&n&&(d=r.measureText(n).width,i.set(n,d)),s&&u===void 0&&(u=r.measureText(o).width,i.set(o,u),f=u-d)}return{width:l*c,kernedWidth:f*c}}getHeightOfChar(e,t){return this.getValueOfPropertyAt(e,t,`fontSize`)}measureLine(e){let t=this._measureLine(e);return this.charSpacing!==0&&(t.width-=this._getWidthOfCharSpacing()),t.width<0&&(t.width=0),t}_measureLine(e){let t,n,r=0,i=this.pathSide===k,a=this.path,o=this._textLines[e],s=o.length,c=Array(s);this.__charBounds[e]=c;for(let i=0;i<s;i++){let a=o[i];n=this._getGraphemeBox(a,e,i,t),c[i]=n,r+=n.kernedWidth,t=a}if(c[s]={left:n?n.left+n.width:0,width:0,kernedWidth:0,height:this.fontSize,deltaY:0},a&&a.segmentsInfo){let e=0,t=a.segmentsInfo[a.segmentsInfo.length-1].length;switch(this.textAlign){case D:e=i?t-r:0;break;case E:e=(t-r)/2;break;case k:e=i?0:t-r}e+=this.pathStartOffset*(i?-1:1);for(let r=i?s-1:0;i?r>=0:r<s;i?r--:r++)n=c[r],e>t?e%=t:e<0&&(e+=t),this._setGraphemeOnPath(e,n),e+=n.kernedWidth}return{width:r,numOfSpaces:0}}_setGraphemeOnPath(e,t){let n=e+t.kernedWidth/2,r=this.path,i=Ma(r.path,n,r.segmentsInfo);t.renderLeft=i.x-r.pathOffset.x,t.renderTop=i.y-r.pathOffset.y,t.angle=i.angle+(this.pathSide===`right`?Math.PI:0)}_getGraphemeBox(e,t,n,r,i){let a=this.getCompleteStyleDeclaration(t,n),o=r?this.getCompleteStyleDeclaration(t,n-1):{},s=this._measureChar(e,a,r,o),c,l=s.kernedWidth,u=s.width;this.charSpacing!==0&&(c=this._getWidthOfCharSpacing(),u+=c,l+=c);let d={width:u,left:0,height:a.fontSize,kernedWidth:l,deltaY:a.deltaY};if(n>0&&!i){let e=this.__charBounds[t][n-1];d.left=e.left+e.width+s.kernedWidth-s.width}return d}getHeightOfLineImpl(e){let t=this.__lineHeights;if(t[e])return t[e];let n=this.getHeightOfChar(e,0);for(let t=1,r=this._textLines[e].length;t<r;t++)n=Math.max(this.getHeightOfChar(e,t),n);return t[e]=n*this._fontSizeMult}getHeightOfLine(e){return this.getHeightOfLineImpl(e)*this.lineHeight}calcTextHeight(){let e=0;for(let t=0,n=this._textLines.length;t<n;t++)e+=t===n-1?this.getHeightOfLineImpl(t):this.getHeightOfLine(t);return e}_getLeftOffset(){return this.direction===`ltr`?-this.width/2:this.width/2}_getTopOffset(){return-this.height/2}_renderTextCommon(e,t){e.save();let n=0,r=this._getLeftOffset(),i=this._getTopOffset();for(let a=0,o=this._textLines.length;a<o;a++)this._renderTextLine(t,e,this._textLines[a],r+this._getLineLeftOffset(a),i+n+this.getHeightOfLineImpl(a),a),n+=this.getHeightOfLine(a);e.restore()}_renderTextFill(e){(this.fill||this.styleHas(`fill`))&&this._renderTextCommon(e,`fillText`)}_renderTextStroke(e){(this.stroke&&this.strokeWidth!==0||!this.isEmptyStyles())&&(this.shadow&&!this.shadow.affectStroke&&this._removeShadow(e),e.save(),this._setLineDash(e,this.strokeDashArray),e.beginPath(),this._renderTextCommon(e,`strokeText`),e.closePath(),e.restore())}_renderChars(e,t,n,r,i,a){let o=this.textAlign.includes(En),s=this.path,c=!o&&this.charSpacing===0&&this.isEmptyStyles(a)&&!s,l=this.direction===`ltr`,u=this.direction===`ltr`?1:-1,d=t.direction,f,p,m,h,g,_=``,v=0;if(t.save(),d!==this.direction&&(t.canvas.setAttribute(`dir`,l?`ltr`:`rtl`),t.direction=l?`ltr`:`rtl`,t.textAlign=l?D:k),i-=this.getHeightOfLineImpl(a)*this._fontSizeFraction,c)return this._renderChar(e,t,a,0,n.join(``),r,i),void t.restore();for(let c=0,l=n.length-1;c<=l;c++)h=c===l||this.charSpacing||s,_+=n[c],m=this.__charBounds[a][c],v===0?(r+=u*(m.kernedWidth-m.width),v+=m.width):v+=m.kernedWidth,o&&!h&&this._reSpaceAndTab.test(n[c])&&(h=!0),h||(f=f||this.getCompleteStyleDeclaration(a,c),p=this.getCompleteStyleDeclaration(a,c+1),h=Ei(f,p,!1)),h&&(s?(t.save(),t.translate(m.renderLeft,m.renderTop),t.rotate(m.angle),this._renderChar(e,t,a,c,_,-v/2,0),t.restore()):(g=r,this._renderChar(e,t,a,c,_,g,i)),_=``,f=p,r+=u*v,v=0);t.restore()}_applyPatternGradientTransformText(e){let t=this.width+this.strokeWidth,n=this.height+this.strokeWidth,r=F({width:t,height:n}),i=r.getContext(`2d`);return r.width=t,r.height=n,i.beginPath(),i.moveTo(0,0),i.lineTo(t,0),i.lineTo(t,n),i.lineTo(0,n),i.closePath(),i.translate(t/2,n/2),i.fillStyle=e.toLive(i),this._applyPatternGradientTransform(i,e),i.fill(),i.createPattern(r,`no-repeat`)}handleFiller(e,t,n){let r,i;return V(n)?n.gradientUnits===`percentage`||n.gradientTransform||n.patternTransform?(r=-this.width/2,i=-this.height/2,e.translate(r,i),e[t]=this._applyPatternGradientTransformText(n),{offsetX:r,offsetY:i}):(e[t]=n.toLive(e),this._applyPatternGradientTransform(e,n)):(e[t]=n,{offsetX:0,offsetY:0})}_setStrokeStyles(e,{stroke:t,strokeWidth:n}){return e.lineWidth=n,e.lineCap=this.strokeLineCap,e.lineDashOffset=this.strokeDashOffset,e.lineJoin=this.strokeLineJoin,e.miterLimit=this.strokeMiterLimit,this.handleFiller(e,`strokeStyle`,t)}_setFillStyles(e,{fill:t}){return this.handleFiller(e,`fillStyle`,t)}_renderChar(e,t,n,r,i,a,o){let s=this._getStyleDeclaration(n,r),c=this.getCompleteStyleDeclaration(n,r),l=e===`fillText`&&c.fill,u=e===`strokeText`&&c.stroke&&c.strokeWidth;if(u||l){if(t.save(),t.font=this._getFontDeclaration(c),s.textBackgroundColor&&this._removeShadow(t),s.deltaY&&(o+=s.deltaY),l){let e=this._setFillStyles(t,c);t.fillText(i,a-e.offsetX,o-e.offsetY)}if(u){let e=this._setStrokeStyles(t,c);t.strokeText(i,a-e.offsetX,o-e.offsetY)}t.restore()}}setSuperscript(e,t){this._setScript(e,t,this.superscript)}setSubscript(e,t){this._setScript(e,t,this.subscript)}_setScript(e,t,n){let r=this.get2DCursorLocation(e,!0),i=this.getValueOfPropertyAt(r.lineIndex,r.charIndex,`fontSize`),a=this.getValueOfPropertyAt(r.lineIndex,r.charIndex,`deltaY`),o={fontSize:i*n.size,deltaY:a+i*n.baseline};this.setSelectionStyles(o,e,t)}_getLineLeftOffset(e){let t=this.getLineWidth(e),n=this.width-t,r=this.textAlign,i=this.direction,a=this.isEndOfWrapping(e),o=0;return r===`justify`||r===`justify-center`&&!a||r===`justify-right`&&!a||r===`justify-left`&&!a?0:(r===`center`&&(o=n/2),r===`right`&&(o=n),r===`justify-center`&&(o=n/2),r===`justify-right`&&(o=n),i===`rtl`&&(r===`right`||r===`justify-right`?o=0:r===`left`||r===`justify-left`?o=-n:r!==`center`&&r!==`justify-center`||(o=-n/2)),o)}_clearCache(){this._forceClearCache=!1,this.__lineWidths=[],this.__lineHeights=[],this.__charBounds=[]}getLineWidth(e){if(this.__lineWidths[e]!==void 0)return this.__lineWidths[e];let{width:t}=this.measureLine(e);return this.__lineWidths[e]=t,t}_getWidthOfCharSpacing(){return this.charSpacing===0?0:this.fontSize*this.charSpacing/1e3}getValueOfPropertyAt(e,t,n){var r;return(r=this._getStyleDeclaration(e,t)[n])==null?this[n]:r}_renderTextDecoration(e,t){if(!this[t]&&!this.styleHas(t))return;let n=this._getTopOffset(),r=this._getLeftOffset(),i=this.path,a=this._getWidthOfCharSpacing(),o=t===`linethrough`?.5:+(t===`overline`),s=this.offsets[t];for(let c=0,l=this._textLines.length;c<l;c++){let l=this.getHeightOfLine(c);if(!this[t]&&!this.styleHas(t,c)){n+=l;continue}let u=this._textLines[c],d=l/this.lineHeight,f=this._getLineLeftOffset(c),p,m=0,h=0,g=this.getValueOfPropertyAt(c,0,t),_=this.getValueOfPropertyAt(c,0,j),v=this.getValueOfPropertyAt(c,0,`textDecorationColor`)||_,y=this.getValueOfPropertyAt(c,0,vn),b=g,x=v,S=y,C=n+d*(1-this._fontSizeFraction),w=this.getHeightOfChar(c,0),ee=this.getValueOfPropertyAt(c,0,`deltaY`);for(let n=0,a=u.length;n<a;n++){let a=this.__charBounds[c][n];b=this.getValueOfPropertyAt(c,n,t),p=this.getValueOfPropertyAt(c,n,j),x=this.getValueOfPropertyAt(c,n,`textDecorationColor`)||p,S=this.getValueOfPropertyAt(c,n,vn);let l=this.getHeightOfChar(c,n),u=this.getValueOfPropertyAt(c,n,`deltaY`);if(i&&b&&p){let t=this.fontSize*S/1e3;e.save(),e.fillStyle=x,e.translate(a.renderLeft,a.renderTop),e.rotate(a.angle),e.fillRect(-a.kernedWidth/2,s*l+u-o*t,a.kernedWidth,t),e.restore()}else if((b!==g||p!==_||x!==v||l!==w||S!==y||u!==ee)&&h>0){let t=this.fontSize*y/1e3,n=r+f+m;this.direction===`rtl`&&(n=this.width-n-h),g&&v&&y&&(e.fillStyle=v,e.fillRect(n,C+s*w+ee-o*t,h,t)),m=a.left,h=a.width,g=b,v=x,y=S,_=p,w=l,ee=u}else h+=a.kernedWidth}let T=r+f+m;this.direction===`rtl`&&(T=this.width-T-h),e.fillStyle=x;let E=this.fontSize*S/1e3;b&&x&&S&&e.fillRect(T,C+s*w+ee-o*E,h-a,E),n+=l}this._removeShadow(e)}_getFontDeclaration({fontFamily:t=this.fontFamily,fontStyle:n=this.fontStyle,fontWeight:r=this.fontWeight,fontSize:i=this.fontSize}={},a){let o=t.includes(`'`)||t.includes(`"`)||t.includes(`,`)||e.genericFonts.includes(t.toLowerCase())?t:`"${t}"`;return[n,r,`${a?this.CACHE_FONT_SIZE:i}px`,o].join(` `)}render(e){this.visible&&(this.canvas&&this.canvas.skipOffscreen&&!this.group&&!this.isOnScreen()||(this._forceClearCache&&this.initDimensions(),super.render(e)))}graphemeSplit(e){return gt(e)}_splitTextIntoLines(e){let t=e.split(this._reNewline),n=Array(t.length),r=[`
`],i=[];for(let e=0;e<t.length;e++)n[e]=this.graphemeSplit(t[e]),i=i.concat(n[e],r);return i.pop(),{_unwrappedLines:n,lines:t,graphemeText:i,graphemeLines:n}}toObject(e=[]){return{...super.toObject([...Cn,...e]),styles:Di(this.styles,this.text),...this.path?{path:this.path.toObject()}:{}}}set(e,t){let{textLayoutProperties:n}=this.constructor;super.set(e,t);let r=!1,i=!1;if(typeof e==`object`)for(let t in e)t===`path`&&this.setPathInfo(),r=r||n.includes(t),i=i||t===`path`;else r=n.includes(e),i=e===`path`;return i&&this.setPathInfo(),r&&this.initialized&&(this.initDimensions(),this.setCoords()),this}complexity(){return 1}static async fromElement(t,n,r){let i=Zi(t,e.ATTRIBUTE_NAMES,r),{textAnchor:a=D,textDecoration:o=``,dx:s=0,dy:c=0,top:l=0,left:u=0,fontSize:d=16,strokeWidth:f=1,...p}={...n,...i},m=new this(on(t.textContent||``).trim(),{left:u+s,top:l+c,underline:o.includes(`underline`),overline:o.includes(`overline`),linethrough:o.includes(`line-through`),strokeWidth:0,fontSize:d,...p}),h=m.getScaledHeight()/m.height,g=((m.height+m.strokeWidth)*m.lineHeight-m.height)*h,_=m.getScaledHeight()+g,v=0;return a===`center`&&(v=m.getScaledWidth()/2),a===`right`&&(v=m.getScaledWidth()),m.set({left:m.left-v,top:m.top-(_-m.fontSize*(.07+m._fontSizeFraction))/m.lineHeight,strokeWidth:f}),m}static fromObject(e){return this._fromObject({...e,styles:Oi(e.styles||{},e.text)},{extraParam:`text`})}};i(Q,`textLayoutProperties`,Sn),i(Q,`cacheProperties`,[...Un,...Cn]),i(Q,`ownDefaults`,Tn),i(Q,`type`,`Text`),i(Q,`genericFonts`,[`serif`,`sans-serif`,`monospace`,`cursive`,`fantasy`,`system-ui`,`ui-serif`,`ui-sans-serif`,`ui-monospace`,`ui-rounded`,`math`,`emoji`,`fangsong`]),i(Q,`ATTRIBUTE_NAMES`,ki.concat(`x`,`y`,`dx`,`dy`,`font-family`,`font-style`,`font-weight`,`font-size`,`letter-spacing`,`text-decoration`,`text-decoration-thickness`,`text-decoration-color`,`text-anchor`)),vi(Q,[class extends gn{_toSVG(){let e=this._getSVGLeftTopOffsets(),t=this._getSVGTextAndBg(e.textTop,e.textLeft);return this._wrapSVGTextAndBg(t)}toSVG(e){let t=this._createBaseSVGMarkup(this._toSVG(),{reviver:e,noStyle:!0,withShadow:!0}),n=this.path;return n?t+n._createBaseSVGMarkup(n._toSVG(),{reviver:e,withShadow:!0,additionalTransform:nt(this.calcOwnMatrix())}):t}_getSVGLeftTopOffsets(){return{textLeft:-this.width/2,textTop:-this.height/2,lineTop:this.getHeightOfLine(0)}}_wrapSVGTextAndBg({textBgRects:e,textSpans:t}){let n=this.getSvgTextDecoration(this);return[e.join(``),`		<text xml:space="preserve" `,`font-family="${U(this.fontFamily.replace(Yo,`'`))}" `,`font-size="${U(this.fontSize)}" `,this.fontStyle?`font-style="${U(this.fontStyle)}" `:``,this.fontWeight?`font-weight="${U(this.fontWeight)}" `:``,n?`text-decoration="${n}" `:``,this.direction===`rtl`?`direction="rtl" `:``,`style="`,this.getSvgStyles(!0),`"`,this.addPaintOrder(),` >`,t.join(``),`</text>
`]}_getSVGTextAndBg(e,t){let n=[],r=[],i,a=e;this.backgroundColor&&r.push(Xo(this.backgroundColor,-this.width/2,-this.height/2,this.width,this.height));for(let e=0,o=this._textLines.length;e<o;e++)i=this._getLineLeftOffset(e),this.direction===`rtl`&&(i+=this.width),(this.textBackgroundColor||this.styleHas(`textBackgroundColor`,e))&&this._setSVGTextLineBg(r,e,t+i,a),this._setSVGTextLineText(n,e,t+i,a),a+=this.getHeightOfLine(e);return{textSpans:n,textBgRects:r}}_createTextCharSpan(e,t,n,r,i){let a=o.NUM_FRACTION_DIGITS,s=this.getSvgSpanStyles(t,e!==e.trim()||!!e.match(Jo)),c=s?`style="${s}"`:``,l=t.deltaY,u=l?` dy="${B(l,a)}" `:``,{angle:d,renderLeft:f,renderTop:p,width:m}=i,h=``;if(f!==void 0){let e=m/2;d&&(h=` rotate="${B(Ie(d),a)}"`);let t=We({angle:Ie(d)});t[4]=f,t[5]=p;let i=new N(-e,0).transform(t);n=i.x,r=i.y}return`<tspan x="${B(n,a)}" y="${B(r,a)}" ${u}${h}${c}>${U(e)}</tspan>`}_setSVGTextLineText(e,t,n,r){let i=this.getHeightOfLine(t),a=this.textAlign.includes(En),o=this._textLines[t],s,c,l,u,d,f=``,p=0;r+=i*(1-this._fontSizeFraction)/this.lineHeight;for(let i=0,m=o.length-1;i<=m;i++)d=i===m||this.charSpacing||this.path,f+=o[i],l=this.__charBounds[t][i],p===0?(n+=l.kernedWidth-l.width,p+=l.width):p+=l.kernedWidth,a&&!d&&this._reSpaceAndTab.test(o[i])&&(d=!0),d||(s=s||this.getCompleteStyleDeclaration(t,i),c=this.getCompleteStyleDeclaration(t,i+1),d=Ei(s,c,!0)),d&&(u=this._getStyleDeclaration(t,i),e.push(this._createTextCharSpan(f,u,n,r,l)),f=``,s=c,this.direction===`rtl`?n-=p:n+=p,p=0)}_setSVGTextLineBg(e,t,n,r){let i=this._textLines[t],a=this.getHeightOfLine(t)/this.lineHeight,o,s=0,c=0,l=this.getValueOfPropertyAt(t,0,`textBackgroundColor`);for(let u=0;u<i.length;u++){let{left:i,width:d,kernedWidth:f}=this.__charBounds[t][u];o=this.getValueOfPropertyAt(t,u,`textBackgroundColor`),o===l?s+=f:(l&&e.push(Xo(l,n+c,r,s,a)),c=i,s=d,l=o)}o&&e.push(Xo(l,n+c,r,s,a))}getSvgStyles(e){let t=nn(this.textDecorationColor)?` text-decoration-color: ${U(this[yn])};`:``;return`${super.getSvgStyles(e)} text-decoration-thickness: ${B(this.textDecorationThickness*this.getObjectScaling().y/10,o.NUM_FRACTION_DIGITS)}%;${t} white-space: pre;`}getSvgSpanStyles(e,t){let{fontFamily:n,strokeWidth:r,stroke:i,fill:a,fontSize:s,fontStyle:c,fontWeight:l,textDecorationThickness:u,textDecorationColor:d,linethrough:f,overline:p,underline:m}=e,h=this.getSvgTextDecoration({underline:m==null?this.underline:m,overline:p==null?this.overline:p,linethrough:f==null?this.linethrough:f}),g=u||this.textDecorationThickness,_=d||this.textDecorationColor,v=rn(r),y=an(n),b=rn(s),x=an(c),S=rn(l)||an(l),C=an(_);return[i?hn(he,i):``,v?`stroke-width: ${U(v)}; `:``,y?`font-family: ${y.includes(`'`)||y.includes(`"`)?U(y):`'${U(y)}'`}; `:``,b?`font-size: ${U(b)}px; `:``,x?`font-style: ${U(x)}; `:``,S?`font-weight: ${U(S)}; `:``,h?`text-decoration: ${h}; text-decoration-thickness: ${B(g*this.getObjectScaling().y/10,o.NUM_FRACTION_DIGITS)}%;${C?` text-decoration-color: ${U(C)};`:``} `:``,a?hn(j,a):``,t?`white-space: pre; `:``].join(``)}getSvgTextDecoration(e){return[`overline`,`underline`,`line-through`].filter(t=>e[t.replace(`-`,``)]).join(` `)}}]),M.setClass(Q),M.setSVGClass(Q);var Qo=class{constructor(e){i(this,`target`,void 0),i(this,`__mouseDownInPlace`,!1),i(this,`__dragStartFired`,!1),i(this,`__isDraggingOver`,!1),i(this,`__dragStartSelection`,void 0),i(this,`__dragImageDisposer`,void 0),i(this,`_dispose`,void 0),this.target=e;let t=[this.target.on(`dragenter`,this.dragEnterHandler.bind(this)),this.target.on(`dragover`,this.dragOverHandler.bind(this)),this.target.on(`dragleave`,this.dragLeaveHandler.bind(this)),this.target.on(`dragend`,this.dragEndHandler.bind(this)),this.target.on(`drop`,this.dropHandler.bind(this))];this._dispose=()=>{t.forEach(e=>e()),this._dispose=void 0}}isPointerOverSelection(e){let t=this.target,n=t.getSelectionStartFromPointer(e);return t.isEditing&&n>=t.selectionStart&&n<=t.selectionEnd&&t.selectionStart<t.selectionEnd}start(e){return this.__mouseDownInPlace=this.isPointerOverSelection(e)}isActive(){return this.__mouseDownInPlace}end(e){let t=this.isActive();return t&&!this.__dragStartFired&&(this.target.setCursorByClick(e),this.target.initDelayedCursor(!0)),this.__mouseDownInPlace=!1,this.__dragStartFired=!1,this.__isDraggingOver=!1,t}getDragStartSelection(){return this.__dragStartSelection}setDragImage(e,{selectionStart:t,selectionEnd:n}){var r;let i=this.target,a=i.canvas,o=new N(i.flipX?-1:1,i.flipY?-1:1),s=i._getCursorBoundaries(t),c=new N(s.left+s.leftOffset,s.top+s.topOffset).multiply(o).transform(i.calcTransformMatrix()),l=a.getScenePoint(e).subtract(c),u=i.getCanvasRetinaScaling(),d=i.getBoundingRect(),f=c.subtract(new N(d.left,d.top)),p=a.viewportTransform,m=f.add(l).transform(p,!0),h=i.backgroundColor,g=Ti(i.styles);i.backgroundColor=``;let _={stroke:`transparent`,fill:`transparent`,textBackgroundColor:`transparent`};i.setSelectionStyles(_,0,t),i.setSelectionStyles(_,n,i.text.length),i.dirty=!0;let v=i.toCanvasElement({enableRetinaScaling:a.enableRetinaScaling,viewportTransform:!0});i.backgroundColor=h,i.styles=g,i.dirty=!0,Ka(v,{position:`fixed`,left:-v.width+`px`,border:te,width:v.width/u+`px`,height:v.height/u+`px`}),this.__dragImageDisposer&&this.__dragImageDisposer(),this.__dragImageDisposer=()=>{v.remove()},H(e.target||this.target.hiddenTextarea).body.appendChild(v),(r=e.dataTransfer)==null||r.setDragImage(v,m.x,m.y)}onDragStart(e){this.__dragStartFired=!0;let t=this.target,n=this.isActive();if(n&&e.dataTransfer){let n=this.__dragStartSelection={selectionStart:t.selectionStart,selectionEnd:t.selectionEnd},r=t._text.slice(n.selectionStart,n.selectionEnd).join(``),i={text:t.text,value:r,...n};e.dataTransfer.setData(`text/plain`,r),e.dataTransfer.setData(`application/fabric`,JSON.stringify({value:r,styles:t.getSelectionStyles(n.selectionStart,n.selectionEnd,!0)})),e.dataTransfer.effectAllowed=`copyMove`,this.setDragImage(e,i)}return t.abortCursorAnimation(),n}canDrop(e){if(this.target.editable&&!this.target.getActiveControl()&&!e.defaultPrevented){if(this.isActive()&&this.__dragStartSelection){let t=this.target.getSelectionStartFromPointer(e),n=this.__dragStartSelection;return t<n.selectionStart||t>n.selectionEnd}return!0}return!1}targetCanDrop(e){return this.target.canDrop(e)}dragEnterHandler({e}){let t=this.targetCanDrop(e);!this.__isDraggingOver&&t&&(this.__isDraggingOver=!0)}dragOverHandler(e){let{e:t}=e,n=this.targetCanDrop(t);!this.__isDraggingOver&&n?this.__isDraggingOver=!0:this.__isDraggingOver&&!n&&(this.__isDraggingOver=!1),this.__isDraggingOver&&(t.preventDefault(),e.canDrop=!0,e.dropTarget=this.target)}dragLeaveHandler(){(this.__isDraggingOver||this.isActive())&&(this.__isDraggingOver=!1)}dropHandler(e){var t;let{e:n}=e,r=n.defaultPrevented;this.__isDraggingOver=!1,n.preventDefault();let i=(t=n.dataTransfer)==null?void 0:t.getData(`text/plain`);if(i&&!r){let t=this.target,r=t.canvas,a=t.getSelectionStartFromPointer(n),{styles:o}=n.dataTransfer.types.includes(`application/fabric`)?JSON.parse(n.dataTransfer.getData(`application/fabric`)):{},s=i[Math.max(0,i.length-1)];if(this.__dragStartSelection){let e=this.__dragStartSelection.selectionStart,n=this.__dragStartSelection.selectionEnd;a>e&&a<=n?a=e:a>n&&(a-=n-e),t.removeChars(e,n),delete this.__dragStartSelection}t._reNewline.test(s)&&(t._reNewline.test(t._text[a])||a===t._text.length)&&(i=i.trimEnd()),e.didDrop=!0,e.dropTarget=t,t.insertChars(i,o,a),r.setActiveObject(t),t.enterEditing(n),t.selectionStart=Math.min(a+0,t._text.length),t.selectionEnd=Math.min(t.selectionStart+i.length,t._text.length),t.hiddenTextarea.value=t.text,t._updateTextarea(),t.hiddenTextarea.focus(),t.fire(le,{index:a+0,action:`drop`}),r.fire(`text:changed`,{target:t}),r.contextTopDirty=!0,r.requestRenderAll()}}dragEndHandler({e}){if(this.isActive()&&this.__dragStartFired&&this.__dragStartSelection){var t;let n=this.target,r=this.target.canvas,{selectionStart:i,selectionEnd:a}=this.__dragStartSelection,o=((t=e.dataTransfer)==null?void 0:t.dropEffect)||`none`;o===`none`?(n.selectionStart=i,n.selectionEnd=a,n._updateTextarea(),n.hiddenTextarea.focus()):(n.clearContextTop(),o===`move`&&(n.removeChars(i,a),n.selectionStart=n.selectionEnd=i,n.hiddenTextarea&&(n.hiddenTextarea.value=n.text),n._updateTextarea(),n.fire(le,{index:i,action:`dragend`}),r.fire(`text:changed`,{target:n}),r.requestRenderAll()),n.exitEditing())}this.__dragImageDisposer&&this.__dragImageDisposer(),delete this.__dragImageDisposer,delete this.__dragStartSelection,this.__isDraggingOver=!1}dispose(){this._dispose&&this._dispose()}};const $o=/[ \n\.,;!\?\-]/;var es=class extends Q{constructor(...e){super(...e),i(this,`_currentCursorOpacity`,1)}initBehavior(){this._tick=this._tick.bind(this),this._onTickComplete=this._onTickComplete.bind(this),this.updateSelectionOnMouseMove=this.updateSelectionOnMouseMove.bind(this)}onDeselect(e){return this.isEditing&&this.exitEditing(),this.selected=!1,super.onDeselect(e)}_animateCursor({toValue:e,duration:t,delay:n,onComplete:r}){return Mr({startValue:this._currentCursorOpacity,endValue:e,duration:t,delay:n,onComplete:r,abort:()=>!this.canvas||this.selectionStart!==this.selectionEnd,onChange:e=>{this._currentCursorOpacity=e,this.renderCursorOrSelection()}})}_tick(e){this._currentTickState=this._animateCursor({toValue:0,duration:this.cursorDuration/2,delay:Math.max(e||0,100),onComplete:this._onTickComplete})}_onTickComplete(){var e;(e=this._currentTickCompleteState)==null||e.abort(),this._currentTickCompleteState=this._animateCursor({toValue:1,duration:this.cursorDuration,onComplete:this._tick})}initDelayedCursor(e){this.abortCursorAnimation(),this._tick(e?0:this.cursorDelay)}abortCursorAnimation(){let e=!1;[this._currentTickState,this._currentTickCompleteState].forEach(t=>{t&&!t.isDone()&&(e=!0,t.abort())}),this._currentCursorOpacity=1,e&&this.clearContextTop()}restartCursorIfNeeded(){[this._currentTickState,this._currentTickCompleteState].some(e=>!e||e.isDone())&&this.initDelayedCursor()}selectAll(){return this.selectionStart=0,this.selectionEnd=this._text.length,this._fireSelectionChanged(),this._updateTextarea(),this}cmdAll(){this.selectAll(),this.renderCursorOrSelection()}getSelectedText(){return this._text.slice(this.selectionStart,this.selectionEnd).join(``)}findWordBoundaryLeft(e){let t=0,n=e-1;if(this._reSpace.test(this._text[n]))for(;this._reSpace.test(this._text[n]);)t++,n--;for(;/\S/.test(this._text[n])&&n>-1;)t++,n--;return e-t}findWordBoundaryRight(e){let t=0,n=e;if(this._reSpace.test(this._text[n]))for(;this._reSpace.test(this._text[n]);)t++,n++;for(;/\S/.test(this._text[n])&&n<this._text.length;)t++,n++;return e+t}findLineBoundaryLeft(e){let t=0,n=e-1;for(;!/\n/.test(this._text[n])&&n>-1;)t++,n--;return e-t}findLineBoundaryRight(e){let t=0,n=e;for(;!/\n/.test(this._text[n])&&n<this._text.length;)t++,n++;return e+t}searchWordBoundary(e,t){let n=this._text,r=e>0&&this._reSpace.test(n[e])&&(t===-1||!ne.test(n[e-1]))?e-1:e,i=n[r];for(;r>0&&r<n.length&&!$o.test(i);)r+=t,i=n[r];return t===-1&&$o.test(i)&&r++,r}selectWord(e){var t;e=(t=e)==null?this.selectionStart:t;let n=this.searchWordBoundary(e,-1),r=Math.max(n,this.searchWordBoundary(e,1));this.selectionStart=n,this.selectionEnd=r,this._fireSelectionChanged(),this._updateTextarea(),this.renderCursorOrSelection()}selectLine(e){var t;e=(t=e)==null?this.selectionStart:t;let n=this.findLineBoundaryLeft(e),r=this.findLineBoundaryRight(e);this.selectionStart=n,this.selectionEnd=r,this._fireSelectionChanged(),this._updateTextarea()}enterEditing(e){!this.isEditing&&this.editable&&(this.enterEditingImpl(),this.fire(`editing:entered`,e?{e}:void 0),this._fireSelectionChanged(),this.canvas&&(this.canvas.fire(`text:editing:entered`,{target:this,e}),this.canvas.requestRenderAll()))}enterEditingImpl(){this.canvas&&(this.canvas.calcOffset(),this.canvas.textEditingManager.exitTextEditing()),this.isEditing=!0,this.initHiddenTextarea(),this.hiddenTextarea.focus(),this.hiddenTextarea.value=this.text,this._updateTextarea(),this._saveEditingProps(),this._setEditingProps(),this._textBeforeEdit=this.text,this._tick()}updateSelectionOnMouseMove(e){if(this.getActiveControl())return;let t=this.hiddenTextarea;H(t).activeElement!==t&&t.focus();let n=this.getSelectionStartFromPointer(e),r=this.selectionStart,i=this.selectionEnd;(n===this.__selectionStartOnMouseDown&&r!==i||r!==n&&i!==n)&&(n>this.__selectionStartOnMouseDown?(this.selectionStart=this.__selectionStartOnMouseDown,this.selectionEnd=n):(this.selectionStart=n,this.selectionEnd=this.__selectionStartOnMouseDown),this.selectionStart===r&&this.selectionEnd===i||(this._fireSelectionChanged(),this._updateTextarea(),this.renderCursorOrSelection()))}_setEditingProps(){this.hoverCursor=`text`,this.canvas&&(this.canvas.defaultCursor=this.canvas.moveCursor=`text`),this.borderColor=this.editingBorderColor,this.hasControls=this.selectable=!1,this.lockMovementX=this.lockMovementY=!0}fromStringToGraphemeSelection(e,t,n){let r=n.slice(0,e),i=this.graphemeSplit(r).length;if(e===t)return{selectionStart:i,selectionEnd:i};let a=n.slice(e,t);return{selectionStart:i,selectionEnd:i+this.graphemeSplit(a).length}}fromGraphemeToStringSelection(e,t,n){let r=n.slice(0,e).join(``).length;return e===t?{selectionStart:r,selectionEnd:r}:{selectionStart:r,selectionEnd:r+n.slice(e,t).join(``).length}}_updateTextarea(){if(this.cursorOffsetCache={},this.hiddenTextarea){if(!this.inCompositionMode){let e=this.fromGraphemeToStringSelection(this.selectionStart,this.selectionEnd,this._text);this.hiddenTextarea.selectionStart=e.selectionStart,this.hiddenTextarea.selectionEnd=e.selectionEnd}this.updateTextareaPosition()}}updateFromTextArea(){let{hiddenTextarea:e,direction:t,textAlign:n,inCompositionMode:r}=this;if(!e)return;let i=n===`justify`?t===`ltr`?D:k:n.replace(`justify-`,``),a=this.getPositionByOrigin(i,`top`);this.cursorOffsetCache={},this.text=e.value,this.set(`dirty`,!0),this.initDimensions(),this.setPositionByOrigin(a,i,`top`),this.setCoords();let o=this.fromStringToGraphemeSelection(e.selectionStart,e.selectionEnd,e.value);this.selectionEnd=this.selectionStart=o.selectionEnd,r||(this.selectionStart=o.selectionStart),this.updateTextareaPosition()}updateTextareaPosition(){if(this.selectionStart===this.selectionEnd){let e=this._calcTextareaPosition();this.hiddenTextarea.style.left=e.left,this.hiddenTextarea.style.top=e.top}}_calcTextareaPosition(){if(!this.canvas)return{left:`1px`,top:`1px`};let e=this.inCompositionMode?this.compositionStart:this.selectionStart,t=this._getCursorBoundaries(e),n=this.get2DCursorLocation(e),r=n.lineIndex,i=n.charIndex,a=this.getValueOfPropertyAt(r,i,`fontSize`)*this.lineHeight,o=t.leftOffset,s=this.getCanvasRetinaScaling(),c=this.canvas.upperCanvasEl,l=c.width/s,u=c.height/s,d=l-a,f=u-a,p=new N(t.left+o,t.top+t.topOffset+a).transform(this.calcTransformMatrix()).transform(this.canvas.viewportTransform).multiply(new N(c.clientWidth/l,c.clientHeight/u));return p.x<0&&(p.x=0),p.x>d&&(p.x=d),p.y<0&&(p.y=0),p.y>f&&(p.y=f),p.x+=this.canvas._offset.left,p.y+=this.canvas._offset.top,{left:`${p.x}px`,top:`${p.y}px`,fontSize:`${a}px`,charHeight:a}}_saveEditingProps(){this._savedProps={hasControls:this.hasControls,borderColor:this.borderColor,lockMovementX:this.lockMovementX,lockMovementY:this.lockMovementY,hoverCursor:this.hoverCursor,selectable:this.selectable,defaultCursor:this.canvas&&this.canvas.defaultCursor,moveCursor:this.canvas&&this.canvas.moveCursor}}_restoreEditingProps(){this._savedProps&&(this.hoverCursor=this._savedProps.hoverCursor,this.hasControls=this._savedProps.hasControls,this.borderColor=this._savedProps.borderColor,this.selectable=this._savedProps.selectable,this.lockMovementX=this._savedProps.lockMovementX,this.lockMovementY=this._savedProps.lockMovementY,this.canvas&&(this.canvas.defaultCursor=this._savedProps.defaultCursor||this.canvas.defaultCursor,this.canvas.moveCursor=this._savedProps.moveCursor||this.canvas.moveCursor),delete this._savedProps)}exitEditingImpl(){let e=this.hiddenTextarea;this.selected=!1,this.isEditing=!1,e&&(e.blur&&e.blur(),e.parentNode&&e.parentNode.removeChild(e)),this.hiddenTextarea=null,this.abortCursorAnimation(),this.selectionStart!==this.selectionEnd&&this.clearContextTop(),this.selectionEnd=this.selectionStart,this._restoreEditingProps(),this._forceClearCache&&(this.initDimensions(),this.setCoords())}exitEditing(){let e=this._textBeforeEdit!==this.text;return this.exitEditingImpl(),this.fire(`editing:exited`),e&&this.fire(`modified`),this.canvas&&(this.canvas.fire(`text:editing:exited`,{target:this}),e&&this.canvas.fire(`object:modified`,{target:this})),this}_removeExtraneousStyles(){for(let e in this.styles)this._textLines[e]||delete this.styles[e]}removeStyleFromTo(e,t){let{lineIndex:n,charIndex:r}=this.get2DCursorLocation(e,!0),{lineIndex:i,charIndex:a}=this.get2DCursorLocation(t,!0);if(n!==i){if(this.styles[n])for(let e=r;e<this._unwrappedTextLines[n].length;e++)delete this.styles[n][e];if(this.styles[i])for(let e=a;e<this._unwrappedTextLines[i].length;e++){let t=this.styles[i][e];t&&(this.styles[n]||(this.styles[n]={}),this.styles[n][r+e-a]=t)}for(let e=n+1;e<=i;e++)delete this.styles[e];this.shiftLineStyles(i,n-i)}else if(this.styles[n]){let e=this.styles[n],t=a-r;for(let t=r;t<a;t++)delete e[t];for(let r in this.styles[n]){let n=parseInt(r,10);n>=a&&(e[n-t]=e[r],delete e[r])}}}shiftLineStyles(e,t){let n=Object.assign({},this.styles);for(let r in this.styles){let i=parseInt(r,10);i>e&&(this.styles[i+t]=n[i],n[i-t]||delete this.styles[i])}}insertNewlineStyleObject(e,t,n,r){let i={},a=this._unwrappedTextLines[e].length,o=a===t,s=!1;n||(n=1),this.shiftLineStyles(e,n);let c=this.styles[e]?this.styles[e][t===0?t:t-1]:void 0;for(let n in this.styles[e]){let r=parseInt(n,10);r>=t&&(s=!0,i[r-t]=this.styles[e][n],o&&t===0||delete this.styles[e][n])}let l=!1;for(s&&!o&&(this.styles[e+n]=i,l=!0),(l||a>t)&&n--;n>0;)r&&r[n-1]?this.styles[e+n]={0:{...r[n-1]}}:c?this.styles[e+n]={0:{...c}}:delete this.styles[e+n],n--;this._forceClearCache=!0}insertCharStyleObject(e,t,n,r){this.styles||(this.styles={});let i=this.styles[e],a=i?{...i}:{};n||(n=1);for(let e in a){let r=parseInt(e,10);r>=t&&(i[r+n]=a[r],a[r-n]||delete i[r])}if(this._forceClearCache=!0,r){for(;n--;)Object.keys(r[n]).length&&(this.styles[e]||(this.styles[e]={}),this.styles[e][t+n]={...r[n]});return}if(!i)return;let o=i[t?t-1:1];for(;o&&n--;)this.styles[e][t+n]={...o}}insertNewStyleBlock(e,t,n){let r=this.get2DCursorLocation(t,!0),i=[0],a,o=0;for(let t=0;t<e.length;t++)e[t]===`
`?(o++,i[o]=0):i[o]++;for(i[0]>0&&(this.insertCharStyleObject(r.lineIndex,r.charIndex,i[0],n),n=n&&n.slice(i[0]+1)),o&&this.insertNewlineStyleObject(r.lineIndex,r.charIndex+i[0],o),a=1;a<o;a++)i[a]>0?this.insertCharStyleObject(r.lineIndex+a,0,i[a],n):n&&this.styles[r.lineIndex+a]&&n[0]&&(this.styles[r.lineIndex+a][0]=n[0]),n=n&&n.slice(i[a]+1);i[a]>0&&this.insertCharStyleObject(r.lineIndex+a,0,i[a],n)}removeChars(e,t=e+1){this.removeStyleFromTo(e,t),this._text.splice(e,t-e),this.text=this._text.join(``),this.set(`dirty`,!0),this.initDimensions(),this.setCoords(),this._removeExtraneousStyles()}insertChars(e,t,n,r=n){r>n&&this.removeStyleFromTo(n,r);let i=this.graphemeSplit(e);this.insertNewStyleBlock(i,n,t),this._text=[...this._text.slice(0,n),...i,...this._text.slice(r)],this.text=this._text.join(``),this.set(`dirty`,!0),this.initDimensions(),this.setCoords(),this._removeExtraneousStyles()}setSelectionStartEndWithShift(e,t,n){n<=e?(t===e?this._selectionDirection=D:this._selectionDirection===`right`&&(this._selectionDirection=D,this.selectionEnd=e),this.selectionStart=n):n>e&&n<t?this._selectionDirection===`right`?this.selectionEnd=n:this.selectionStart=n:(t===e?this._selectionDirection=k:this._selectionDirection===`left`&&(this._selectionDirection=k,this.selectionStart=t),this.selectionEnd=n)}},ts=class extends es{initHiddenTextarea(){let e=this.canvas&&H(this.canvas.getElement())||g(),t=e.createElement(`textarea`);Object.entries({autocapitalize:`off`,autocorrect:`off`,autocomplete:`off`,spellcheck:`false`,"data-fabric":`textarea`,wrap:`off`,name:`fabricTextarea`}).map(([e,n])=>t.setAttribute(e,n));let{top:n,left:r,fontSize:i}=this._calcTextareaPosition();t.style.cssText=`position: absolute; top: ${n}; left: ${r}; z-index: -999; opacity: 0; width: 1px; height: 1px; font-size: 1px; padding-top: ${i};`,(this.hiddenTextareaContainer||e.body).appendChild(t),Object.entries({blur:`blur`,keydown:`onKeyDown`,keyup:`onKeyUp`,input:`onInput`,copy:`copy`,cut:`copy`,paste:`paste`,compositionstart:`onCompositionStart`,compositionupdate:`onCompositionUpdate`,compositionend:`onCompositionEnd`}).map(([e,n])=>t.addEventListener(e,this[n].bind(this))),this.hiddenTextarea=t}blur(){this.abortCursorAnimation()}onKeyDown(e){if(!this.isEditing)return;let t=this.direction===`rtl`?this.keysMapRtl:this.keysMap;if(e.keyCode in t)this[t[e.keyCode]](e);else{if(!(e.keyCode in this.ctrlKeysMapDown)||!e.ctrlKey&&!e.metaKey)return;this[this.ctrlKeysMapDown[e.keyCode]](e)}e.stopImmediatePropagation(),e.preventDefault(),e.keyCode>=33&&e.keyCode<=40?(this.inCompositionMode=!1,this.clearContextTop(),this.renderCursorOrSelection()):this.canvas&&this.canvas.requestRenderAll()}onKeyUp(e){!this.isEditing||this._copyDone||this.inCompositionMode?this._copyDone=!1:e.keyCode in this.ctrlKeysMapUp&&(e.ctrlKey||e.metaKey)&&(this[this.ctrlKeysMapUp[e.keyCode]](e),e.stopImmediatePropagation(),e.preventDefault(),this.canvas&&this.canvas.requestRenderAll())}onInput(e){let t=this.fromPaste,{value:n,selectionStart:r,selectionEnd:i}=this.hiddenTextarea;if(this.fromPaste=!1,e&&e.stopPropagation(),!this.isEditing)return;let a=()=>{this.updateFromTextArea(),this.fire(le),this.canvas&&(this.canvas.fire(`text:changed`,{target:this}),this.canvas.requestRenderAll())};if(this.hiddenTextarea.value===``)return this.styles={},void a();let s=this._splitTextIntoLines(n).graphemeText,c=this._text.length,l=s.length,u=this.selectionStart,d=this.selectionEnd,f=u!==d,p,m,g,_,v=l-c,y=this.fromStringToGraphemeSelection(r,i,n),b=u>y.selectionStart;f?(m=this._text.slice(u,d),v+=d-u):l<c&&(m=b?this._text.slice(d+v,d):this._text.slice(u,u-v));let x=s.slice(y.selectionEnd-v,y.selectionEnd);if(m&&m.length&&(x.length&&(p=this.getSelectionStyles(u,u+1,!1),p=x.map(()=>p[0])),f?(g=u,_=d):b?(g=d-m.length,_=d):(g=d,_=d+m.length),this.removeStyleFromTo(g,_)),x.length){let{copyPasteData:e}=h();t&&x.join(``)===e.copiedText&&!o.disableStyleCopyPaste&&(p=e.copiedTextStyle),this.insertNewStyleBlock(x,u,p)}a()}onCompositionStart(){this.inCompositionMode=!0}onCompositionEnd(){this.inCompositionMode=!1}onCompositionUpdate({target:e}){let{selectionStart:t,selectionEnd:n}=e;this.compositionStart=t,this.compositionEnd=n,this.updateTextareaPosition()}copy(){if(this.selectionStart===this.selectionEnd)return;let{copyPasteData:e}=h();e.copiedText=this.getSelectedText(),o.disableStyleCopyPaste?e.copiedTextStyle=void 0:e.copiedTextStyle=this.getSelectionStyles(this.selectionStart,this.selectionEnd,!0),this._copyDone=!0}paste(){this.fromPaste=!0}_getWidthBeforeCursor(e,t){let n,r=this._getLineLeftOffset(e);return t>0&&(n=this.__charBounds[e][t-1],r+=n.left+n.width),r}getDownCursorOffset(e,t){let n=this._getSelectionForOffset(e,t),r=this.get2DCursorLocation(n),i=r.lineIndex;if(i===this._textLines.length-1||e.metaKey||e.keyCode===34)return this._text.length-n;let a=r.charIndex,o=this._getWidthBeforeCursor(i,a),s=this._getIndexOnLine(i+1,o);return this._textLines[i].slice(a).length+s+1+this.missingNewlineOffset(i)}_getSelectionForOffset(e,t){return e.shiftKey&&this.selectionStart!==this.selectionEnd&&t?this.selectionEnd:this.selectionStart}getUpCursorOffset(e,t){let n=this._getSelectionForOffset(e,t),r=this.get2DCursorLocation(n),i=r.lineIndex;if(i===0||e.metaKey||e.keyCode===33)return-n;let a=r.charIndex,o=this._getWidthBeforeCursor(i,a),s=this._getIndexOnLine(i-1,o),c=this._textLines[i].slice(0,a),l=this.missingNewlineOffset(i-1);return-this._textLines[i-1].length+s-c.length+(1-l)}_getIndexOnLine(e,t){let n=this._textLines[e],r,i,a=this._getLineLeftOffset(e),o=0;for(let s=0,c=n.length;s<c;s++)if(r=this.__charBounds[e][s].width,a+=r,a>t){i=!0;let e=a-r,n=a,c=Math.abs(e-t);o=Math.abs(n-t)<c?s:s-1;break}return i||(o=n.length-1),o}moveCursorDown(e){this.selectionStart>=this._text.length&&this.selectionEnd>=this._text.length||this._moveCursorUpOrDown(`Down`,e)}moveCursorUp(e){this.selectionStart===0&&this.selectionEnd===0||this._moveCursorUpOrDown(`Up`,e)}_moveCursorUpOrDown(e,t){let n=this[`get${e}CursorOffset`](t,this._selectionDirection===k);if(t.shiftKey?this.moveCursorWithShift(n):this.moveCursorWithoutShift(n),n!==0){let e=this.text.length;this.selectionStart=Vn(0,this.selectionStart,e),this.selectionEnd=Vn(0,this.selectionEnd,e),this.abortCursorAnimation(),this.initDelayedCursor(),this._fireSelectionChanged(),this._updateTextarea()}}moveCursorWithShift(e){let t=this._selectionDirection===`left`?this.selectionStart+e:this.selectionEnd+e;return this.setSelectionStartEndWithShift(this.selectionStart,this.selectionEnd,t),e!==0}moveCursorWithoutShift(e){return e<0?(this.selectionStart+=e,this.selectionEnd=this.selectionStart):(this.selectionEnd+=e,this.selectionStart=this.selectionEnd),e!==0}moveCursorLeft(e){this.selectionStart===0&&this.selectionEnd===0||this._moveCursorLeftOrRight(`Left`,e)}_move(e,t,n){let r;if(e.altKey)r=this[`findWordBoundary${n}`](this[t]);else{if(!e.metaKey&&e.keyCode!==35&&e.keyCode!==36)return this[t]+=n===`Left`?-1:1,!0;r=this[`findLineBoundary${n}`](this[t])}return r!==void 0&&this[t]!==r&&(this[t]=r,!0)}_moveLeft(e,t){return this._move(e,t,`Left`)}_moveRight(e,t){return this._move(e,t,`Right`)}moveCursorLeftWithoutShift(e){let t=!0;return this._selectionDirection=D,this.selectionEnd===this.selectionStart&&this.selectionStart!==0&&(t=this._moveLeft(e,`selectionStart`)),this.selectionEnd=this.selectionStart,t}moveCursorLeftWithShift(e){return this._selectionDirection===`right`&&this.selectionStart!==this.selectionEnd?this._moveLeft(e,`selectionEnd`):this.selectionStart===0?void 0:(this._selectionDirection=D,this._moveLeft(e,`selectionStart`))}moveCursorRight(e){this.selectionStart>=this._text.length&&this.selectionEnd>=this._text.length||this._moveCursorLeftOrRight(`Right`,e)}_moveCursorLeftOrRight(e,t){let n=`moveCursor${e}${t.shiftKey?`WithShift`:`WithoutShift`}`;this._currentCursorOpacity=1,this[n](t)&&(this.abortCursorAnimation(),this.initDelayedCursor(),this._fireSelectionChanged(),this._updateTextarea())}moveCursorRightWithShift(e){return this._selectionDirection===`left`&&this.selectionStart!==this.selectionEnd?this._moveRight(e,`selectionStart`):this.selectionEnd===this._text.length?void 0:(this._selectionDirection=k,this._moveRight(e,`selectionEnd`))}moveCursorRightWithoutShift(e){let t=!0;return this._selectionDirection=k,this.selectionStart===this.selectionEnd?(t=this._moveRight(e,`selectionStart`),this.selectionEnd=this.selectionStart):this.selectionStart=this.selectionEnd,t}};const ns=e=>!!e.button;var rs=class extends ts{constructor(...e){super(...e),i(this,`draggableTextDelegate`,void 0)}initBehavior(){this.on(`mousedown`,this._mouseDownHandler),this.on(`mouseup`,this.mouseUpHandler),this.on(`mousedblclick`,this.doubleClickHandler),this.on(`mousetripleclick`,this.tripleClickHandler),this.draggableTextDelegate=new Qo(this),super.initBehavior()}shouldStartDragging(){return this.draggableTextDelegate.isActive()}onDragStart(e){return this.draggableTextDelegate.onDragStart(e)}canDrop(e){return this.draggableTextDelegate.canDrop(e)}doubleClickHandler(e){this.isEditing&&(this.selectWord(this.getSelectionStartFromPointer(e.e)),this.renderCursorOrSelection())}tripleClickHandler(e){this.isEditing&&(this.selectLine(this.getSelectionStartFromPointer(e.e)),this.renderCursorOrSelection())}_mouseDownHandler({e,alreadySelected:t}){this.canvas&&this.editable&&!ns(e)&&!this.getActiveControl()&&(this.draggableTextDelegate.start(e)||(this.canvas.textEditingManager.register(this),t&&(this.inCompositionMode=!1,this.setCursorByClick(e)),this.isEditing&&(this.__selectionStartOnMouseDown=this.selectionStart,this.selectionStart===this.selectionEnd&&this.abortCursorAnimation(),this.renderCursorOrSelection()),this.selected||(this.selected=t||this.isEditing)))}mouseUpHandler({e,transform:t}){let n=this.draggableTextDelegate.end(e);if(this.canvas){this.canvas.textEditingManager.unregister(this);let e=this.canvas._activeObject;if(e&&e!==this)return}!this.editable||this.group&&!this.group.interactive||t&&t.actionPerformed||ns(e)||n||this.selected&&!this.getActiveControl()&&(this.enterEditing(e),this.selectionStart===this.selectionEnd?this.initDelayedCursor(!0):this.renderCursorOrSelection())}setCursorByClick(e){let t=this.getSelectionStartFromPointer(e),n=this.selectionStart,r=this.selectionEnd;e.shiftKey?this.setSelectionStartEndWithShift(n,r,t):(this.selectionStart=t,this.selectionEnd=t),this.isEditing&&(this._fireSelectionChanged(),this._updateTextarea())}getSelectionStartFromPointer(e){let t=this.canvas.getScenePoint(e).transform(R(this.calcTransformMatrix())).add(new N(-this._getLeftOffset(),-this._getTopOffset())),n=0,r=0,i=0;for(let e=0;e<this._textLines.length&&n<=t.y;e++)n+=this.getHeightOfLine(e),i=e,e>0&&(r+=this._textLines[e-1].length+this.missingNewlineOffset(e-1));let a=Math.abs(this._getLineLeftOffset(i)),o=this._textLines[i].length,s=this.__charBounds[i];for(let e=0;e<o;e++){let n=a+s[e].kernedWidth;if(t.x<=n){Math.abs(t.x-n)<=Math.abs(t.x-a)&&r++;break}a=n,r++}return Math.min(this.flipX?o-r:r,this._text.length)}};const is=`moveCursorUp`,as=`moveCursorDown`,os=`moveCursorLeft`,ss=`moveCursorRight`,cs=`exitEditing`,ls=(e,t)=>{let n=t.getRetinaScaling();e.setTransform(n,0,0,n,0,0);let r=t.viewportTransform;e.transform(r[0],r[1],r[2],r[3],r[4],r[5])},us={selectionStart:0,selectionEnd:0,selectionColor:`rgba(17,119,255,0.3)`,isEditing:!1,editable:!0,editingBorderColor:`rgba(102,153,255,0.25)`,cursorWidth:2,cursorColor:``,cursorDelay:1e3,cursorDuration:600,caching:!0,hiddenTextareaContainer:null,keysMap:{9:cs,27:cs,33:is,34:as,35:ss,36:os,37:os,38:is,39:ss,40:as},keysMapRtl:{9:cs,27:cs,33:is,34:as,35:os,36:ss,37:ss,38:is,39:os,40:as},ctrlKeysMapDown:{65:`cmdAll`},ctrlKeysMapUp:{67:`copy`,88:`cut`},_selectionDirection:null,_reSpace:/\s|\r?\n/,inCompositionMode:!1};var ds=class e extends rs{static getDefaults(){return{...super.getDefaults(),...e.ownDefaults}}get type(){let e=super.type;return e===`itext`?`i-text`:e}constructor(t,n){super(t,{...e.ownDefaults,...n}),this.initBehavior()}_set(e,t){return this.isEditing&&this._savedProps&&e in this._savedProps?(this._savedProps[e]=t,this):(e===`canvas`&&(this.canvas instanceof ho&&this.canvas.textEditingManager.remove(this),t instanceof ho&&t.textEditingManager.add(this)),super._set(e,t))}setSelectionStart(e){e=Math.max(e,0),this._updateAndFire(`selectionStart`,e)}setSelectionEnd(e){e=Math.min(e,this.text.length),this._updateAndFire(`selectionEnd`,e)}_updateAndFire(e,t){this[e]!==t&&(this._fireSelectionChanged(),this[e]=t),this._updateTextarea()}_fireSelectionChanged(){this.fire(`selection:changed`),this.canvas&&this.canvas.fire(`text:selection:changed`,{target:this})}initDimensions(){this.isEditing&&this.initDelayedCursor(),super.initDimensions()}getSelectionStyles(e=this.selectionStart||0,t=this.selectionEnd,n){return super.getSelectionStyles(e,t,n)}setSelectionStyles(e,t=this.selectionStart||0,n=this.selectionEnd){return super.setSelectionStyles(e,t,n)}get2DCursorLocation(e=this.selectionStart,t){return super.get2DCursorLocation(e,t)}render(e){super.render(e),this.cursorOffsetCache={},this.renderCursorOrSelection()}toCanvasElement(e){let t=this.isEditing;this.isEditing=!1;let n=super.toCanvasElement(e);return this.isEditing=t,n}renderCursorOrSelection(){if(!this.isEditing||!this.canvas)return;let e=this.clearContextTop(!0);if(!e)return;let t=this._getCursorBoundaries(),n=this.findAncestorsWithClipPath(),r=n.length>0,i,a=e;if(r){i=F(e.canvas),a=i.getContext(`2d`),ls(a,this.canvas);let t=this.calcTransformMatrix();a.transform(t[0],t[1],t[2],t[3],t[4],t[5])}if(this.selectionStart!==this.selectionEnd||this.inCompositionMode?this.renderSelection(a,t):this.renderCursor(a,t),r)for(let t of n){let n=t.clipPath,r=F(e.canvas),i=r.getContext(`2d`);if(ls(i,this.canvas),!n.absolutePositioned){let e=t.calcTransformMatrix();i.transform(e[0],e[1],e[2],e[3],e[4],e[5])}n.transform(i),n.drawObject(i,!0,{}),this.drawClipPathOnCache(a,n,r)}r&&(e.setTransform(1,0,0,1,0,0),e.drawImage(i,0,0)),this.canvas.contextTopDirty=!0,e.restore()}findAncestorsWithClipPath(){let e=[],t=this;for(;t;)t.clipPath&&e.push(t),t=t.parent;return e}_getCursorBoundaries(e=this.selectionStart,t){let n=this._getLeftOffset(),r=this._getTopOffset(),i=this._getCursorBoundariesOffsets(e,t);return{left:n,top:r,leftOffset:i.left,topOffset:i.top}}_getCursorBoundariesOffsets(e,t){return t?this.__getCursorBoundariesOffsets(e):this.cursorOffsetCache&&`top`in this.cursorOffsetCache?this.cursorOffsetCache:this.cursorOffsetCache=this.__getCursorBoundariesOffsets(e)}__getCursorBoundariesOffsets(e){let t=0,n=0,{charIndex:r,lineIndex:i}=this.get2DCursorLocation(e),{textAlign:a,direction:o}=this;for(let e=0;e<i;e++)t+=this.getHeightOfLine(e);let s=this._getLineLeftOffset(i),c=this.__charBounds[i][r];c&&(n=c.left),this.charSpacing!==0&&r===this._textLines[i].length&&(n-=this._getWidthOfCharSpacing());let l=s+(n>0?n:0);return o===`rtl`&&(a===`right`||a===`justify`||a===`justify-right`?l*=-1:a===`left`||a===`justify-left`?l=s-(n>0?n:0):a!==`center`&&a!==`justify-center`||(l=s-(n>0?n:0))),{top:t,left:l}}renderCursorAt(e){this._renderCursor(this.canvas.contextTop,this._getCursorBoundaries(e,!0),e)}renderCursor(e,t){this._renderCursor(e,t,this.selectionStart)}getCursorRenderingData(e=this.selectionStart,t=this._getCursorBoundaries(e)){let n=this.get2DCursorLocation(e),r=n.lineIndex,i=n.charIndex>0?n.charIndex-1:0,a=this.getValueOfPropertyAt(r,i,`fontSize`),o=this.getObjectScaling().x*this.canvas.getZoom(),s=this.cursorWidth/o,c=this.getValueOfPropertyAt(r,i,`deltaY`),l=t.topOffset+(1-this._fontSizeFraction)*this.getHeightOfLine(r)/this.lineHeight-a*(1-this._fontSizeFraction);return{color:this.cursorColor||this.getValueOfPropertyAt(r,i,`fill`),opacity:this._currentCursorOpacity,left:t.left+t.leftOffset-s/2,top:l+t.top+c,width:s,height:a}}_renderCursor(e,t,n){let{color:r,opacity:i,left:a,top:o,width:s,height:c}=this.getCursorRenderingData(n,t);e.fillStyle=r,e.globalAlpha=i,e.fillRect(a,o,s,c)}renderSelection(e,t){let n={selectionStart:this.inCompositionMode?this.hiddenTextarea.selectionStart:this.selectionStart,selectionEnd:this.inCompositionMode?this.hiddenTextarea.selectionEnd:this.selectionEnd};this._renderSelection(e,n,t)}renderDragSourceEffect(){let e=this.draggableTextDelegate.getDragStartSelection();this._renderSelection(this.canvas.contextTop,e,this._getCursorBoundaries(e.selectionStart,!0))}renderDropTargetEffect(e){let t=this.getSelectionStartFromPointer(e);this.renderCursorAt(t)}_renderSelection(e,t,n){let{textAlign:r,direction:i}=this,a=t.selectionStart,o=t.selectionEnd,s=r.includes(En),c=this.get2DCursorLocation(a),l=this.get2DCursorLocation(o),u=c.lineIndex,d=l.lineIndex,f=c.charIndex<0?0:c.charIndex,p=l.charIndex<0?0:l.charIndex;for(let t=u;t<=d;t++){let a=this._getLineLeftOffset(t)||0,o=this.getHeightOfLine(t),c=0,l=0;if(t===u&&(c=this.__charBounds[u][f].left),t>=u&&t<d)l=s&&!this.isEndOfWrapping(t)?this.width:this.getLineWidth(t)||5;else if(t===d)if(p===0)l=this.__charBounds[d][p].left;else{let e=this._getWidthOfCharSpacing();l=this.__charBounds[d][p-1].left+this.__charBounds[d][p-1].width-e}let m=o;(this.lineHeight<1||t===d&&this.lineHeight>1)&&(o/=this.lineHeight);let h=n.left+a+c,g=o,_=0,v=l-c;this.inCompositionMode?(e.fillStyle=this.compositionColor||`black`,g=1,_=o):e.fillStyle=this.selectionColor,i===`rtl`&&(r===`right`||r===`justify`||r===`justify-right`?h=this.width-h-v:r===`left`||r===`justify-left`?h=n.left+a-l:r!==`center`&&r!==`justify-center`||(h=n.left+a-l)),e.fillRect(h,n.top+n.topOffset+_,v,g),n.topOffset+=m}}getCurrentCharFontSize(){let e=this._getCurrentCharIndex();return this.getValueOfPropertyAt(e.l,e.c,`fontSize`)}getCurrentCharColor(){let e=this._getCurrentCharIndex();return this.getValueOfPropertyAt(e.l,e.c,j)}_getCurrentCharIndex(){let e=this.get2DCursorLocation(this.selectionStart,!0),t=e.charIndex>0?e.charIndex-1:0;return{l:e.lineIndex,c:t}}dispose(){this.exitEditingImpl(),this.draggableTextDelegate.dispose(),super.dispose()}};i(ds,`ownDefaults`,us),i(ds,`type`,`IText`),M.setClass(ds),M.setClass(ds,`i-text`);var fs=class e extends ds{static getDefaults(){return{...super.getDefaults(),...e.ownDefaults}}constructor(t,n){super(t,{...e.ownDefaults,...n})}static createControls(){return{controls:gi()}}initDimensions(){this.initialized&&(this.isEditing&&this.initDelayedCursor(),this._clearCache(),this.dynamicMinWidth=0,this._styleMap=this._generateStyleMap(this._splitText()),this.dynamicMinWidth>this.width&&this._set(`width`,this.dynamicMinWidth),this.textAlign.includes(`justify`)&&this.enlargeSpaces(),this.height=this.calcTextHeight())}_generateStyleMap(e){let t=0,n=0,r=0,i={};for(let a=0;a<e.graphemeLines.length;a++)e.graphemeText[r]===`
`&&a>0?(n=0,r++,t++):!this.splitByGrapheme&&this._reSpaceAndTab.test(e.graphemeText[r])&&a>0&&(n++,r++),i[a]={line:t,offset:n},r+=e.graphemeLines[a].length,n+=e.graphemeLines[a].length;return i}styleHas(e,t){if(this._styleMap&&!this.isWrapping){let e=this._styleMap[t];e&&(t=e.line)}return super.styleHas(e,t)}isEmptyStyles(e){if(!this.styles)return!0;let t,n,r=0,i=!1,a=this._styleMap[e],o=this._styleMap[e+1];a&&(e=a.line,r=a.offset),o&&(t=o.line,i=t===e,n=o.offset);let s=e===void 0?this.styles:{line:this.styles[e]};for(let e in s)for(let t in s[e]){let a=parseInt(t,10);if(a>=r&&(!i||a<n))for(let n in s[e][t])return!1}return!0}_getStyleDeclaration(e,t){if(this._styleMap&&!this.isWrapping){let n=this._styleMap[e];if(!n)return{};e=n.line,t=n.offset+t}return super._getStyleDeclaration(e,t)}_setStyleDeclaration(e,t,n){let r=this._styleMap[e];super._setStyleDeclaration(r.line,r.offset+t,n)}_deleteStyleDeclaration(e,t){let n=this._styleMap[e];super._deleteStyleDeclaration(n.line,n.offset+t)}_getLineStyle(e){let t=this._styleMap[e];return!!this.styles[t.line]}_setLineStyle(e){let t=this._styleMap[e];super._setLineStyle(t.line)}_wrapText(e,t){this.isWrapping=!0;let n=this.getGraphemeDataForRender(e),r=[];for(let e=0;e<n.wordsData.length;e++)r.push(...this._wrapLine(e,t,n));return this.isWrapping=!1,r}getGraphemeDataForRender(e){let t=this.splitByGrapheme,n=t?``:` `,r=0;return{wordsData:e.map((e,i)=>{let a=0,o=t?this.graphemeSplit(e):this.wordSplit(e);return o.length===0?[{word:[],width:0}]:o.map(e=>{let o=t?[e]:this.graphemeSplit(e),s=this._measureWord(o,i,a);return r=Math.max(s,r),a+=o.length+n.length,{word:o,width:s}})}),largestWordWidth:r}}_measureWord(e,t,n=0){let r,i=0;for(let a=0,o=e.length;a<o;a++)i+=this._getGraphemeBox(e[a],t,a+n,r,!0).kernedWidth,r=e[a];return i}wordSplit(e){return e.split(this._wordJoiners)}_wrapLine(e,t,{largestWordWidth:n,wordsData:r},i=0){let a=this._getWidthOfCharSpacing(),o=this.splitByGrapheme,s=[],c=o?``:` `,l=0,u=[],d=0,f=0,p=!0;t-=i;let m=Math.max(t,n,this.dynamicMinWidth),h=r[e],g;for(g=0;g<h.length;g++){let{word:t,width:n}=h[g];d+=t.length,l+=f+n-a,l>m&&!p?(s.push(u),u=[],l=n,p=!0):l+=a,p||o||u.push(c),u=u.concat(t),f=o?0:this._measureWord([c],e,d),d++,p=!1}return g&&s.push(u),n+i>this.dynamicMinWidth&&(this.dynamicMinWidth=n-a+i),s}isEndOfWrapping(e){return!this._styleMap[e+1]||this._styleMap[e+1].line!==this._styleMap[e].line}missingNewlineOffset(e,t){return this.splitByGrapheme&&!t?+!!this.isEndOfWrapping(e):1}_splitTextIntoLines(e){let t=super._splitTextIntoLines(e),n=this._wrapText(t.lines,this.width),r=Array(n.length);for(let e=0;e<n.length;e++)r[e]=n[e].join(``);return t.lines=r,t.graphemeLines=n,t}getMinWidth(){return Math.max(this.minWidth,this.dynamicMinWidth)}_removeExtraneousStyles(){let e=new Map;for(let t in this._styleMap){let n=parseInt(t,10);if(this._textLines[n]){let n=this._styleMap[t].line;e.set(`${n}`,!0)}}for(let t in this.styles)e.has(t)||delete this.styles[t]}toObject(e=[]){return super.toObject([`minWidth`,`splitByGrapheme`,...e])}};i(fs,`type`,`Textbox`),i(fs,`textLayoutProperties`,[...ds.textLayoutProperties,`width`]),i(fs,`ownDefaults`,{minWidth:20,dynamicMinWidth:2,lockScalingFlip:!0,noScaleCache:!1,_wordJoiners:/[ \t\r]/,splitByGrapheme:!1}),M.setClass(fs);var ps=class extends ra{shouldPerformLayout(e){return!!e.target.clipPath&&super.shouldPerformLayout(e)}shouldLayoutClipPath(){return!1}calcLayoutResult(e,t){let{target:n}=e,{clipPath:r,group:i}=n;if(!r||!this.shouldPerformLayout(e))return;let{width:a,height:o}=wt(na(n,r)),s=new N(a,o);if(r.absolutePositioned)return{center:Mt(r.getRelativeCenterPoint(),void 0,i?i.calcTransformMatrix():void 0),size:s};{let i=r.getRelativeCenterPoint().transform(n.calcOwnMatrix(),!0);if(this.shouldPerformLayout(e)){let{center:n=new N,correction:r=new N}=this.calcBoundingBox(t,e)||{};return{center:n.add(i),correction:r.subtract(i),size:s}}return{center:n.getRelativeCenterPoint().add(i),size:s}}}};i(ps,`type`,`clip-path`),M.setClass(ps);var ms=class extends ra{getInitialSize({target:e},{size:t}){return new N(e.width||t.x,e.height||t.y)}};i(ms,`type`,`fixed`),M.setClass(ms);var hs=class extends oa{subscribeTargets(e){let t=e.target;e.targets.reduce((e,t)=>(t.parent&&e.add(t.parent),e),new Set).forEach(e=>{e.layoutManager.subscribeTargets({target:e,targets:[t]})})}unsubscribeTargets(e){let t=e.target,n=t.getObjects();e.targets.reduce((e,t)=>(t.parent&&e.add(t.parent),e),new Set).forEach(e=>{!n.some(t=>t.parent===e)&&e.layoutManager.unsubscribeTargets({target:e,targets:[t]})})}},gs=class e extends ca{static getDefaults(){return{...super.getDefaults(),...e.ownDefaults}}constructor(t=[],n={}){super(),Object.assign(this,e.ownDefaults),this.setOptions(n);let{left:r,top:i,layoutManager:a}=n;this.groupInit(t,{left:r,top:i,layoutManager:a==null?new hs:a})}_shouldSetNestedCoords(){return!0}__objectSelectionMonitor(){}multiSelectAdd(...e){this.multiSelectionStacking===`selection-order`?this.add(...e):e.forEach(e=>{let t=this._objects.findIndex(t=>t.isInFrontOf(e)),n=t===-1?this.size():t;this.insertAt(n,e)})}canEnterGroup(e){return this.getObjects().some(t=>t.isDescendantOf(e)||e.isDescendantOf(t))?(s(`error`,`ActiveSelection: circular object trees are not supported, this call has no effect`),!1):super.canEnterGroup(e)}enterGroup(e,t){e.parent&&e.parent===e.group?e.parent._exitGroup(e):e.group&&e.parent!==e.group&&e.group.remove(e),this._enterGroup(e,t)}exitGroup(e,t){this._exitGroup(e,t),e.parent&&e.parent._enterGroup(e,!0)}_onAfterObjectsChange(e,t){super._onAfterObjectsChange(e,t);let n=new Set;t.forEach(e=>{let{parent:t}=e;t&&n.add(t)}),e===`removed`?n.forEach(e=>{e._onAfterObjectsChange(ta,t)}):n.forEach(e=>{e._set(`dirty`,!0)})}onDeselect(){return this.removeAll(),!1}toString(){return`#<ActiveSelection: (${this.complexity()})>`}shouldCache(){return!1}isOnACache(){return!1}_renderControls(e,t,n){e.save(),e.globalAlpha=this.isMoving?this.borderOpacityWhenMoving:1;let r={hasControls:!1,...n,forActiveSelection:!0};for(let t=0;t<this._objects.length;t++)this._objects[t]._renderControls(e,r);super._renderControls(e,t),e.restore()}};i(gs,`type`,`ActiveSelection`),i(gs,`ownDefaults`,{multiSelectionStacking:`canvas-stacking`}),M.setClass(gs),M.setClass(gs,`activeSelection`);var _s=class{constructor(){i(this,`resources`,{})}applyFilters(e,t,n,r,i){let a=i.getContext(`2d`,{willReadFrequently:!0,desynchronized:!0});if(!a)return;a.drawImage(t,0,0,n,r);let o={sourceWidth:n,sourceHeight:r,imageData:a.getImageData(0,0,n,r),originalEl:t,originalImageData:a.getImageData(0,0,n,r),canvasEl:i,ctx:a,filterBackend:this};e.forEach(e=>{e.applyTo(o)});let{imageData:s}=o;return s.width===n&&s.height===r||(i.width=s.width,i.height=s.height),a.putImageData(s,0,0),o}},vs=class{constructor({tileSize:e=o.textureSize}={}){i(this,`aPosition`,new Float32Array([0,0,0,1,1,0,1,1])),i(this,`resources`,{}),this.tileSize=e,this.setupGLContext(e,e),this.captureGPUInfo()}setupGLContext(e,t){this.dispose(),this.createWebGLCanvas(e,t)}createWebGLCanvas(e,t){let n=F({width:e,height:t}),r=n.getContext(`webgl`,{alpha:!0,premultipliedAlpha:!1,depth:!1,stencil:!1,antialias:!1});r&&(r.clearColor(0,0,0,0),this.canvas=n,this.gl=r)}applyFilters(e,t,n,r,i,a){let o=this.gl,s=i.getContext(`2d`);if(!o||!s)return;let c;a&&(c=this.getCachedTexture(a,t));let l={originalWidth:t.width||t.naturalWidth||0,originalHeight:t.height||t.naturalHeight||0,sourceWidth:n,sourceHeight:r,destinationWidth:n,destinationHeight:r,context:o,sourceTexture:this.createTexture(o,n,r,c?void 0:t),targetTexture:this.createTexture(o,n,r),originalTexture:c||this.createTexture(o,n,r,c?void 0:t),passes:e.length,webgl:!0,aPosition:this.aPosition,programCache:this.programCache,pass:0,filterBackend:this,targetCanvas:i},u=o.createFramebuffer();return o.bindFramebuffer(o.FRAMEBUFFER,u),e.forEach(e=>{e&&e.applyTo(l)}),function(e){let t=e.targetCanvas,n=t.width,r=t.height,i=e.destinationWidth,a=e.destinationHeight;n===i&&r===a||(t.width=i,t.height=a)}(l),this.copyGLTo2D(o,l),o.bindTexture(o.TEXTURE_2D,null),o.deleteTexture(l.sourceTexture),o.deleteTexture(l.targetTexture),o.deleteFramebuffer(u),s.setTransform(1,0,0,1,0,0),l}dispose(){this.canvas&&(this.canvas=null,this.gl=null),this.clearWebGLCaches()}clearWebGLCaches(){this.programCache={},this.textureCache={}}createTexture(e,t,n,r,i){let{NEAREST:a,TEXTURE_2D:o,RGBA:s,UNSIGNED_BYTE:c,CLAMP_TO_EDGE:l,TEXTURE_MAG_FILTER:u,TEXTURE_MIN_FILTER:d,TEXTURE_WRAP_S:f,TEXTURE_WRAP_T:p}=e,m=e.createTexture();return e.bindTexture(o,m),e.texParameteri(o,u,i||a),e.texParameteri(o,d,i||a),e.texParameteri(o,f,l),e.texParameteri(o,p,l),r?e.texImage2D(o,0,s,s,c,r):e.texImage2D(o,0,s,t,n,0,s,c,null),m}getCachedTexture(e,t,n){let{textureCache:r}=this;if(r[e])return r[e];{let i=this.createTexture(this.gl,t.width,t.height,t,n);return i&&(r[e]=i),i}}evictCachesForKey(e){this.textureCache[e]&&(this.gl.deleteTexture(this.textureCache[e]),delete this.textureCache[e])}copyGLTo2D(e,t){let n=e.canvas,r=t.targetCanvas,i=r.getContext(`2d`);if(!i)return;i.translate(0,r.height),i.scale(1,-1);let a=n.height-r.height;i.drawImage(n,0,a,r.width,r.height,0,0,r.width,r.height)}copyGLTo2DPutImageData(e,t){let n=t.targetCanvas.getContext(`2d`),r=t.destinationWidth,i=t.destinationHeight,a=r*i*4;if(!n)return;let o=new Uint8Array(this.imageBuffer,0,a),s=new Uint8ClampedArray(this.imageBuffer,0,a);e.readPixels(0,0,r,i,e.RGBA,e.UNSIGNED_BYTE,o);let c=new ImageData(s,r,i);n.putImageData(c,0,0)}captureGPUInfo(){if(this.gpuInfo)return this.gpuInfo;let e=this.gl,t={renderer:``,vendor:``};if(!e)return t;let n=e.getExtension(`WEBGL_debug_renderer_info`);if(n){let r=e.getParameter(n.UNMASKED_RENDERER_WEBGL),i=e.getParameter(n.UNMASKED_VENDOR_WEBGL);r&&(t.renderer=r.toLowerCase()),i&&(t.vendor=i.toLowerCase())}return this.gpuInfo=t,t}};let ys;function bs(){let{WebGLProbe:e}=h();return e.queryWebGL(P()),o.enableGLFiltering&&e.isSupported(o.textureSize)?new vs({tileSize:o.textureSize}):new _s}function xs(e=!0){return!ys&&e&&(ys=bs()),ys}function Ss(e){ys=e}const Cs=[`cropX`,`cropY`];var ws=class e extends J{static getDefaults(){return{...super.getDefaults(),...e.ownDefaults}}constructor(t,n){super(),i(this,`_lastScaleX`,1),i(this,`_lastScaleY`,1),i(this,`_filterScalingX`,1),i(this,`_filterScalingY`,1),this.filters=[],Object.assign(this,e.ownDefaults),this.setOptions(n),this.cacheKey=`texture${je()}`,this.setElement(typeof t==`string`?(this.canvas&&H(this.canvas.getElement())||g()).getElementById(t):t,n)}getElement(){return this._element}setElement(e,t={}){this.removeTexture(this.cacheKey),this.removeTexture(`${this.cacheKey}_filtered`),this._element=e,this._originalElement=e,this._setWidthHeight(t),this.filters.length!==0&&this.applyFilters(),this.resizeFilter&&this.applyResizeFilters()}removeTexture(e){let t=xs(!1);t instanceof vs&&t.evictCachesForKey(e)}dispose(){super.dispose(),this.removeTexture(this.cacheKey),this.removeTexture(`${this.cacheKey}_filtered`),this._cacheContext=null,[`_originalElement`,`_element`,`_filteredEl`,`_cacheCanvas`].forEach(e=>{let t=this[e];t&&h().dispose(t),this[e]=void 0})}getCrossOrigin(){return this._originalElement&&(this._originalElement.crossOrigin||null)}getOriginalSize(){let e=this.getElement();return e?{width:e.naturalWidth||e.width,height:e.naturalHeight||e.height}:{width:0,height:0}}_stroke(e){if(!this.stroke||this.strokeWidth===0)return;let t=this.width/2,n=this.height/2;e.beginPath(),e.moveTo(-t,-n),e.lineTo(t,-n),e.lineTo(t,n),e.lineTo(-t,n),e.lineTo(-t,-n),e.closePath()}toObject(e=[]){let t=[];return this.filters.forEach(e=>{e&&t.push(e.toObject())}),{...super.toObject([...Cs,...e]),src:this.getSrc(),crossOrigin:this.getCrossOrigin(),filters:t,...this.resizeFilter?{resizeFilter:this.resizeFilter.toObject()}:{}}}hasCrop(){return!!this.cropX||!!this.cropY||this.width<this._element.width||this.height<this._element.height}_toSVG(){let e=[],t=this._element,n=-this.width/2,r=-this.height/2,i=[],a=[],o=``,s=``;if(!t)return[];if(this.hasCrop()){let e=je();i.push(`<clipPath id="imageCrop_`+e+`">
`,`	<rect x="`+n+`" y="`+r+`" width="`+U(this.width)+`" height="`+U(this.height)+`" />
`,`</clipPath>
`),o=` clip-path="url(#imageCrop_`+e+`)" `}if(this.imageSmoothing||(s=` image-rendering="optimizeSpeed"`),e.push(`	<image `,`COMMON_PARTS`,`xlink:href="${U(this.getSrc(!0))}" x="${n-this.cropX}" y="${r-this.cropY}" width="${t.width||t.naturalWidth}" height="${t.height||t.naturalHeight}"${s}${o}></image>\n`),this.stroke||this.strokeDashArray){let e=this.fill;this.fill=null,a=[`\t<rect x="${n}" y="${r}" width="${U(this.width)}" height="${U(this.height)}" style="${this.getSvgStyles()}" />\n`],this.fill=e}return i=this.paintFirst===`fill`?i.concat(e,a):i.concat(a,e),i}getSrc(e){let t=e?this._element:this._originalElement;return t?t.toDataURL?t.toDataURL():this.srcFromAttribute?t.getAttribute(`src`)||``:t.src:this.src||``}getSvgSrc(e){return this.getSrc(e)}setSrc(e,{crossOrigin:t,signal:n}={}){return Ze(e,{crossOrigin:t,signal:n}).then(e=>{t!==void 0&&this.set({crossOrigin:t}),this.setElement(e)})}toString(){return`#<Image: { src: "${this.getSrc()}" }>`}applyResizeFilters(){let e=this.resizeFilter,t=this.minimumScaleTrigger,n=this.getTotalObjectScaling(),r=n.x,i=n.y,a=this._filteredEl||this._originalElement;if(this.group&&this.set(`dirty`,!0),!e||r>t&&i>t)return this._element=a,this._filterScalingX=1,this._filterScalingY=1,this._lastScaleX=r,void(this._lastScaleY=i);let o=F(a),{width:s,height:c}=a;this._element=o,this._lastScaleX=e.scaleX=r,this._lastScaleY=e.scaleY=i,xs().applyFilters([e],a,s,c,this._element),this._filterScalingX=o.width/this._originalElement.width,this._filterScalingY=o.height/this._originalElement.height}applyFilters(e=this.filters||[]){if(e=e.filter(e=>e&&!e.isNeutralState()),this.set(`dirty`,!0),this.removeTexture(`${this.cacheKey}_filtered`),e.length===0)return this._element=this._originalElement,this._filteredEl=void 0,this._filterScalingX=1,void(this._filterScalingY=1);let t=this._originalElement,n=t.naturalWidth||t.width,r=t.naturalHeight||t.height;if(this._element===this._originalElement){let e=F({width:n,height:r});this._element=e,this._filteredEl=e}else this._filteredEl&&(this._element=this._filteredEl,this._filteredEl.getContext(`2d`).clearRect(0,0,n,r),this._lastScaleX=1,this._lastScaleY=1);xs().applyFilters(e,this._originalElement,n,r,this._element,this.cacheKey),this._originalElement.width===this._element.width&&this._originalElement.height===this._element.height||(this._filterScalingX=this._element.width/this._originalElement.width,this._filterScalingY=this._element.height/this._originalElement.height)}_render(e){e.imageSmoothingEnabled=this.imageSmoothing,!0!==this.isMoving&&this.resizeFilter&&this._needsResize()&&this.applyResizeFilters(),this._stroke(e),this._renderPaintInOrder(e)}drawCacheOnCanvas(e){e.imageSmoothingEnabled=this.imageSmoothing,super.drawCacheOnCanvas(e)}shouldCache(){return this.needsItsOwnCache()}_renderFill(e){let t=this._element;if(!t)return;let n=this._filterScalingX,r=this._filterScalingY,i=this.width,a=this.height,o=Math.max(this.cropX,0),s=Math.max(this.cropY,0),c=t.naturalWidth||t.width,l=t.naturalHeight||t.height,u=o*n,d=s*r,f=Math.min(i*n,c-u),p=Math.min(a*r,l-d),m=-i/2,h=-a/2,g=Math.min(i,c/n-o),_=Math.min(a,l/r-s);t&&e.drawImage(t,u,d,f,p,m,h,g,_)}_needsResize(){let e=this.getTotalObjectScaling();return e.x!==this._lastScaleX||e.y!==this._lastScaleY}_resetWidthHeight(){this.set(this.getOriginalSize())}_setWidthHeight({width:e,height:t}={}){let n=this.getOriginalSize();this.width=e||n.width,this.height=t||n.height}parsePreserveAspectRatioAttribute(){let e=mn(this.preserveAspectRatio||``),t=this.width,n=this.height,r={width:t,height:n},i,a=this._element.width,o=this._element.height,s=1,c=1,l=0,u=0,d=0,f=0;return!e||e.alignX===`none`&&e.alignY===`none`?(s=t/a,c=n/o):(e.meetOrSlice===`meet`&&(s=c=ua(this._element,r),i=(t-a*s)/2,e.alignX===`Min`&&(l=-i),e.alignX===`Max`&&(l=i),i=(n-o*c)/2,e.alignY===`Min`&&(u=-i),e.alignY===`Max`&&(u=i)),e.meetOrSlice===`slice`&&(s=c=da(this._element,r),i=a-t/s,e.alignX===`Mid`&&(d=i/2),e.alignX===`Max`&&(d=i),i=o-n/c,e.alignY===`Mid`&&(f=i/2),e.alignY===`Max`&&(f=i),a=t/s,o=n/c)),{width:a,height:o,scaleX:s,scaleY:c,offsetLeft:l,offsetTop:u,cropX:d,cropY:f}}static fromObject({filters:e,resizeFilter:t,src:n,crossOrigin:r,type:i,...a},o){return Promise.all([Ze(n,{...o,crossOrigin:r}),e&&Qe(e,o),t?Qe([t],o):[],$e(a,o)]).then(([e,t=[],[r],i={}])=>new this(e,{...a,src:n,filters:t,resizeFilter:r,...i}))}static fromURL(e,{crossOrigin:t=null,signal:n}={},r){return Ze(e,{crossOrigin:t,signal:n}).then(e=>new this(e,r))}static async fromElement(e,t={},n){let r=Zi(e,this.ATTRIBUTE_NAMES,n);return this.fromURL(r[`xlink:href`]||r.href,t,r).catch(e=>(s(`log`,`Unable to parse Image`,e),null))}};function Ts(e){if(!Fn.test(e.nodeName))return{};let t=e.getAttribute(`viewBox`),n,r,i=1,a=1,o=e.getAttribute(`width`),s=e.getAttribute(`height`),c=e.getAttribute(`x`)||0,l=e.getAttribute(`y`)||0,u=!(t&&Ln.test(t)),d=!o||!s||o===`100%`||s===`100%`,f=``,p=0,m=0;if(u&&(c||l)&&e.parentNode&&e.parentNode.nodeName!==`#document`&&(f=` translate(`+K(c||`0`)+` `+K(l||`0`)+`) `,n=(e.getAttribute(`transform`)||``)+f,e.setAttribute(`transform`,n),e.removeAttribute(`x`),e.removeAttribute(`y`)),u&&d)return{width:0,height:0};let h={width:0,height:0};if(u)return h.width=K(o),h.height=K(s),h;let g=t.match(Ln),_=-parseFloat(g[1]),v=-parseFloat(g[2]),y=parseFloat(g[3]),b=parseFloat(g[4]);h.minX=_,h.minY=v,h.viewBoxWidth=y,h.viewBoxHeight=b,d?(h.width=y,h.height=b):(h.width=K(o),h.height=K(s),i=h.width/y,a=h.height/b);let x=mn(e.getAttribute(`preserveAspectRatio`)||``);if(x.alignX!==`none`&&(x.meetOrSlice===`meet`&&(a=i=i>a?a:i),x.meetOrSlice===`slice`&&(a=i=i>a?i:a),p=h.width-y*i,m=h.height-b*i,x.alignX===`Mid`&&(p/=2),x.alignY===`Mid`&&(m/=2),x.alignX===`Min`&&(p=0),x.alignY===`Min`&&(m=0)),i===1&&a===1&&_===0&&v===0&&c===0&&l===0)return h;if((c||l)&&e.parentNode.nodeName!==`#document`&&(f=` translate(`+K(c||`0`)+` `+K(l||`0`)+`) `),n=f+` matrix(`+i+` 0 0 `+a+` `+(_*i+p)+` `+(v*a+m)+`) `,e.nodeName===`svg`){for(r=e.ownerDocument.createElementNS(kn,`g`);e.firstChild;)r.appendChild(e.firstChild);e.appendChild(r)}else r=e,r.removeAttribute(`x`),r.removeAttribute(`y`),n=r.getAttribute(`transform`)+n;return r.setAttribute(`transform`,n),h}i(ws,`type`,`Image`),i(ws,`cacheProperties`,[...Un,...Cs]),i(ws,`ownDefaults`,{strokeWidth:0,srcFromAttribute:!1,minimumScaleTrigger:.5,cropX:0,cropY:0,imageSmoothing:!0}),i(ws,`ATTRIBUTE_NAMES`,[...ki,`x`,`y`,`width`,`height`,`preserveAspectRatio`,`xlink:href`,`href`,`crossOrigin`,`image-rendering`]),M.setClass(ws),M.setSVGClass(ws);const Es=e=>e.tagName.replace(`svg:`,``),Ds=_n([`pattern`,`defs`,`symbol`,`metadata`,`clipPath`,`mask`,`desc`]);function Os(e,t){let n,r,i,a,o=[];for(i=0,a=t.length;i<a;i++)n=t[i],r=e.getElementsByTagNameNS(`http://www.w3.org/2000/svg`,n),o=o.concat(Array.from(r));return o}const ks=[`gradientTransform`,`x1`,`x2`,`y1`,`y2`,`gradientUnits`,`cx`,`cy`,`r`,`fx`,`fy`],As=`xlink:href`;function js(e,t){var n;let r=((n=t.getAttribute(As))==null?void 0:n.slice(1))||``,i=e.getElementById(r);if(i&&i.getAttribute(As)&&js(e,i),i&&(ks.forEach(e=>{let n=i.getAttribute(e);!t.hasAttribute(e)&&n&&t.setAttribute(e,n)}),!t.children.length)){let e=i.cloneNode(!0);for(;e.firstChild;)t.appendChild(e.firstChild)}t.removeAttribute(As)}const Ms=[`linearGradient`,`radialGradient`,`svg:linearGradient`,`svg:radialGradient`],Ns=e=>M.getSVGClass(Es(e).toLowerCase());var Ps=class{constructor(e,t,n,r,i){this.elements=e,this.options=t,this.reviver=n,this.regexUrl=/^url\(['"]?#([^'"]+)['"]?\)/g,this.doc=r,this.clipPaths=i,this.gradientDefs=function(e){let t=Os(e,Ms),n={},r=t.length;for(;r--;){let i=t[r];i.getAttribute(`xlink:href`)&&js(e,i);let a=i.getAttribute(`id`);a&&(n[a]=i)}return n}(r),this.cssRules=function(e){let t=e.getElementsByTagName(`style`),n={};for(let e=0;e<t.length;e++){let r=(t[e].textContent||``).replace(/\/\*[\s\S]*?\*\//g,``);r.trim()!==``&&r.split(`}`).filter((e,t,n)=>n.length>1&&e.trim()).forEach(e=>{if((e.match(/{/g)||[]).length>1&&e.trim().startsWith(`@`))return;let t=e.split(`{`),r={},i=t[1].trim().split(`;`).filter(function(e){return e.trim()});for(let e=0;e<i.length;e++){let t=i[e].split(`:`),n=t[0].trim();r[n]=t[1].trim()}(e=t[0].trim()).split(`,`).forEach(e=>{(e=e.replace(/^svg/i,``).trim())!==``&&(n[e]={...n[e]||{},...r})})})}return n}(r)}parse(){return Promise.all(this.elements.map(e=>this.createObject(e)))}async createObject(e){let t=Ns(e);if(t){let n=await t.fromElement(e,this.options,this.cssRules);return this.resolveGradient(n,e,j),this.resolveGradient(n,e,he),n instanceof ws&&n._originalElement?Wa(n,n.parsePreserveAspectRatioAttribute()):Wa(n),await this.resolveClipPath(n,e),this.reviver&&this.reviver(e,n),n}return null}extractPropertyDefinition(e,t,n){let r=e[t],i=this.regexUrl;if(!i.test(r))return;i.lastIndex=0;let a=i.exec(r)[1];return i.lastIndex=0,n[a]}resolveGradient(e,t,n){let r=this.extractPropertyDefinition(e,n,this.gradientDefs);if(r){let i=t.getAttribute(n+`-opacity`),a=ko.fromElement(r,e,{...this.options,opacity:i});e.set(n,a)}}async resolveClipPath(e,t,n){let r=this.extractPropertyDefinition(e,`clipPath`,this.clipPaths);if(r){let i=R(e.calcTransformMatrix()),a=r[0].parentElement,o=t;for(;!n&&o.parentElement&&o.getAttribute(`clip-path`)!==e.clipPath;)o=o.parentElement;o.parentElement.appendChild(a);let s=Ki(`${o.getAttribute(`transform`)||``} ${a.getAttribute(`originalTransform`)||``}`);a.setAttribute(`transform`,`matrix(${s.join(`,`)})`);let c=await Promise.all(r.map(e=>Ns(e).fromElement(e,this.options,this.cssRules).then(e=>(Wa(e),e.fillRule=e.clipRule,delete e.clipRule,e)))),l=c.length===1?c[0]:new ca(c),u=z(i,l.calcTransformMatrix());l.clipPath&&await this.resolveClipPath(l,o,a.getAttribute(`clip-path`)?o:void 0);let{scaleX:d,scaleY:f,angle:p,skewX:m,translateX:h,translateY:g}=He(u);l.set({flipX:!1,flipY:!1}),l.set({scaleX:d,scaleY:f,angle:p,skewX:m,skewY:0}),l.setPositionByOrigin(new N(h,g),E,E),e.clipPath=l}else delete e.clipPath}};const Fs=e=>Pn.test(Es(e));async function Is(e,t,{crossOrigin:n,signal:r}={}){if(r&&r.aborted)return s(`log`,new l(`parseSVGDocument`)),{objects:[],elements:[],options:{},allElements:[]};let i=e.documentElement;(function(e){let t=Os(e,[`use`,`svg:use`]),n=[`x`,`y`,`xlink:href`,`href`,`transform`];for(let r of t){let t=r.attributes,i={};for(let e of t)e.value&&(i[e.name]=e.value);let a=(i[`xlink:href`]||i.href||``).slice(1);if(a===``)return;let o=e.getElementById(a);if(o===null)return;let s=o.cloneNode(!0),c=s.attributes,l={};for(let e of c)e.value&&(l[e.name]=e.value);let{x:u=0,y:d=0,transform:f=``}=i,p=`${f} ${l.transform||``} translate(${u}, ${d})`;if(Ts(s),/^svg$/i.test(s.nodeName)){let e=s.ownerDocument.createElementNS(kn,`g`);Object.entries(l).forEach(([t,n])=>e.setAttributeNS(kn,t,n)),e.append(...s.childNodes),s=e}for(let e of t){if(!e)continue;let{name:t,value:r}=e;if(!n.includes(t))if(t===`style`){let e={};Ji(r,e),Object.entries(l).forEach(([t,n])=>{e[t]=n}),Ji(l.style||``,e);let n=Object.entries(e).map(e=>e.join(`:`)).join(`;`);s.setAttribute(t,n)}else !l[t]&&s.setAttribute(t,r)}s.setAttribute(`transform`,p),s.setAttribute(`instantiated_by_use`,`1`),s.removeAttribute(`id`),r.parentNode.replaceChild(s,r)}})(e);let a=Array.from(i.getElementsByTagName(`*`)),o={...Ts(i),crossOrigin:n,signal:r},c=a.filter(e=>(Ts(e),Fs(e)&&!function(e){let t=e;for(;t&&(t=t.parentElement);)if(t&&t.nodeName&&Ds.test(Es(t))&&!t.getAttribute(`instantiated_by_use`))return!0;return!1}(e)));if(!c||c&&!c.length)return{objects:[],elements:[],options:{},allElements:[],options:o,allElements:a};let u={};return a.filter(e=>Es(e)===`clipPath`).forEach(e=>{e.setAttribute(`originalTransform`,e.getAttribute(`transform`)||``);let t=e.getAttribute(`id`);u[t]=Array.from(e.getElementsByTagName(`*`)).filter(e=>Fs(e))}),{objects:await new Ps(c,o,t,e,u).parse(),elements:c,options:o,allElements:a}}function Ls(e,t,n){return Is(new(_()).DOMParser().parseFromString(e.trim(),`text/xml`),t,n)}function Rs(e,t,n={}){return fetch(e.replace(/^\n\s*/,``).trim(),{signal:n.signal}).then(e=>{if(!e.ok)throw new c(`HTTP error! status: ${e.status}`);return e.text()}).then(e=>Ls(e,t,n)).catch(()=>({objects:[],elements:[],options:{},allElements:[]}))}const zs=e=>e.webgl!==void 0,Bs=(e,t)=>{let n=F({width:e,height:t}),r=P().getContext(`webgl`),i={imageBuffer:new ArrayBuffer(e*t*4)},a={destinationWidth:e,destinationHeight:t,targetCanvas:n},o;o=_().performance.now(),vs.prototype.copyGLTo2D.call(i,r,a);let s=_().performance.now()-o;return o=_().performance.now(),vs.prototype.copyGLTo2DPutImageData.call(i,r,a),s>_().performance.now()-o},Vs=`precision highp float`,Hs=`\n    ${Vs};\n    varying vec2 vTexCoord;\n    uniform sampler2D uTexture;\n    void main() {\n      gl_FragColor = texture2D(uTexture, vTexCoord);\n    }`,Us=new RegExp(Vs,`g`);var $=class{get type(){return this.constructor.type}constructor({type:e,...t}={}){Object.assign(this,this.constructor.defaults,t)}getFragmentSource(){return Hs}getVertexSource(){return`
    attribute vec2 aPosition;
    varying vec2 vTexCoord;
    void main() {
      vTexCoord = aPosition;
      gl_Position = vec4(aPosition * 2.0 - 1.0, 0.0, 1.0);
    }`}createProgram(e,t=this.getFragmentSource(),n=this.getVertexSource()){let{WebGLProbe:{GLPrecision:r=`highp`}}=h();r!==`highp`&&(t=t.replace(Us,Vs.replace(`highp`,r)));let i=e.createShader(e.VERTEX_SHADER),a=e.createShader(e.FRAGMENT_SHADER),o=e.createProgram();if(!i||!a||!o)throw new c(`Vertex, fragment shader or program creation error`);if(e.shaderSource(i,n),e.compileShader(i),!e.getShaderParameter(i,e.COMPILE_STATUS))throw new c(`Vertex shader compile error for ${this.type}: ${e.getShaderInfoLog(i)}`);if(e.shaderSource(a,t),e.compileShader(a),!e.getShaderParameter(a,e.COMPILE_STATUS))throw new c(`Fragment shader compile error for ${this.type}: ${e.getShaderInfoLog(a)}`);if(e.attachShader(o,i),e.attachShader(o,a),e.linkProgram(o),!e.getProgramParameter(o,e.LINK_STATUS))throw new c(`Shader link error for "${this.type}" ${e.getProgramInfoLog(o)}`);let s=this.getUniformLocations(e,o)||{};return s.uStepW=e.getUniformLocation(o,`uStepW`),s.uStepH=e.getUniformLocation(o,`uStepH`),{program:o,attributeLocations:this.getAttributeLocations(e,o),uniformLocations:s}}getAttributeLocations(e,t){return{aPosition:e.getAttribLocation(t,`aPosition`)}}getUniformLocations(e,t){let n=this.constructor.uniformLocations,r={};for(let i=0;i<n.length;i++)r[n[i]]=e.getUniformLocation(t,n[i]);return r}sendAttributeData(e,t,n){let r=t.aPosition,i=e.createBuffer();e.bindBuffer(e.ARRAY_BUFFER,i),e.enableVertexAttribArray(r),e.vertexAttribPointer(r,2,e.FLOAT,!1,0,0),e.bufferData(e.ARRAY_BUFFER,n,e.STATIC_DRAW)}_setupFrameBuffer(e){let t=e.context;if(e.passes>1){let n=e.destinationWidth,r=e.destinationHeight;e.sourceWidth===n&&e.sourceHeight===r||(t.deleteTexture(e.targetTexture),e.targetTexture=e.filterBackend.createTexture(t,n,r)),t.framebufferTexture2D(t.FRAMEBUFFER,t.COLOR_ATTACHMENT0,t.TEXTURE_2D,e.targetTexture,0)}else t.bindFramebuffer(t.FRAMEBUFFER,null),t.finish()}_swapTextures(e){e.passes--,e.pass++;let t=e.targetTexture;e.targetTexture=e.sourceTexture,e.sourceTexture=t}isNeutralState(e){return!1}applyTo(e){zs(e)?(this._setupFrameBuffer(e),this.applyToWebGL(e),this._swapTextures(e)):this.applyTo2d(e)}applyTo2d(e){}getCacheKey(){return this.type}retrieveShader(e){let t=this.getCacheKey();return e.programCache[t]||(e.programCache[t]=this.createProgram(e.context)),e.programCache[t]}applyToWebGL(e){let t=e.context,n=this.retrieveShader(e);e.pass===0&&e.originalTexture?t.bindTexture(t.TEXTURE_2D,e.originalTexture):t.bindTexture(t.TEXTURE_2D,e.sourceTexture),t.useProgram(n.program),this.sendAttributeData(t,n.attributeLocations,e.aPosition),t.uniform1f(n.uniformLocations.uStepW,1/e.sourceWidth),t.uniform1f(n.uniformLocations.uStepH,1/e.sourceHeight),this.sendUniformData(t,n.uniformLocations),t.viewport(0,0,e.destinationWidth,e.destinationHeight),t.drawArrays(t.TRIANGLE_STRIP,0,4)}bindAdditionalTexture(e,t,n){e.activeTexture(n),e.bindTexture(e.TEXTURE_2D,t),e.activeTexture(e.TEXTURE0)}unbindAdditionalTexture(e,t){e.activeTexture(t),e.bindTexture(e.TEXTURE_2D,null),e.activeTexture(e.TEXTURE0)}sendUniformData(e,t){}createHelpLayer(e){if(!e.helpLayer){let{sourceWidth:t,sourceHeight:n}=e;e.helpLayer=F({width:t,height:n})}}toObject(){let e=Object.keys(this.constructor.defaults||{});return{type:this.type,...e.reduce((e,t)=>(e[t]=this[t],e),{})}}toJSON(){return this.toObject()}static async fromObject({type:e,...t},n){return new this(t)}};i($,`type`,`BaseFilter`),i($,`uniformLocations`,[]);const Ws={multiply:`gl_FragColor.rgb *= uColor.rgb;
`,screen:`gl_FragColor.rgb = 1.0 - (1.0 - gl_FragColor.rgb) * (1.0 - uColor.rgb);
`,add:`gl_FragColor.rgb += uColor.rgb;
`,difference:`gl_FragColor.rgb = abs(gl_FragColor.rgb - uColor.rgb);
`,subtract:`gl_FragColor.rgb -= uColor.rgb;
`,lighten:`gl_FragColor.rgb = max(gl_FragColor.rgb, uColor.rgb);
`,darken:`gl_FragColor.rgb = min(gl_FragColor.rgb, uColor.rgb);
`,exclusion:`gl_FragColor.rgb += uColor.rgb - 2.0 * (uColor.rgb * gl_FragColor.rgb);
`,overlay:`
    if (uColor.r < 0.5) {
      gl_FragColor.r *= 2.0 * uColor.r;
    } else {
      gl_FragColor.r = 1.0 - 2.0 * (1.0 - gl_FragColor.r) * (1.0 - uColor.r);
    }
    if (uColor.g < 0.5) {
      gl_FragColor.g *= 2.0 * uColor.g;
    } else {
      gl_FragColor.g = 1.0 - 2.0 * (1.0 - gl_FragColor.g) * (1.0 - uColor.g);
    }
    if (uColor.b < 0.5) {
      gl_FragColor.b *= 2.0 * uColor.b;
    } else {
      gl_FragColor.b = 1.0 - 2.0 * (1.0 - gl_FragColor.b) * (1.0 - uColor.b);
    }
    `,tint:`
    gl_FragColor.rgb *= (1.0 - uColor.a);
    gl_FragColor.rgb += uColor.rgb;
    `};var Gs=class extends ${getCacheKey(){return`${this.type}_${this.mode}`}getFragmentSource(){return`\n      precision highp float;\n      uniform sampler2D uTexture;\n      uniform vec4 uColor;\n      varying vec2 vTexCoord;\n      void main() {\n        vec4 color = texture2D(uTexture, vTexCoord);\n        gl_FragColor = color;\n        if (color.a > 0.0) {\n          ${Ws[this.mode]}\n        }\n      }\n      `}applyTo2d({imageData:{data:e}}){let t=new G(this.color).getSource(),n=this.alpha,r=t[0]*n,i=t[1]*n,a=t[2]*n,o=1-n;for(let t=0;t<e.length;t+=4){let n=e[t],s=e[t+1],c=e[t+2],l,u,d;switch(this.mode){case`multiply`:l=n*r/255,u=s*i/255,d=c*a/255;break;case`screen`:l=255-(255-n)*(255-r)/255,u=255-(255-s)*(255-i)/255,d=255-(255-c)*(255-a)/255;break;case`add`:l=n+r,u=s+i,d=c+a;break;case`difference`:l=Math.abs(n-r),u=Math.abs(s-i),d=Math.abs(c-a);break;case`subtract`:l=n-r,u=s-i,d=c-a;break;case`darken`:l=Math.min(n,r),u=Math.min(s,i),d=Math.min(c,a);break;case`lighten`:l=Math.max(n,r),u=Math.max(s,i),d=Math.max(c,a);break;case`overlay`:l=r<128?2*n*r/255:255-2*(255-n)*(255-r)/255,u=i<128?2*s*i/255:255-2*(255-s)*(255-i)/255,d=a<128?2*c*a/255:255-2*(255-c)*(255-a)/255;break;case`exclusion`:l=r+n-2*r*n/255,u=i+s-2*i*s/255,d=a+c-2*a*c/255;break;case`tint`:l=r+n*o,u=i+s*o,d=a+c*o}e[t]=l,e[t+1]=u,e[t+2]=d}}sendUniformData(e,t){let n=new G(this.color).getSource();n[0]=this.alpha*n[0]/255,n[1]=this.alpha*n[1]/255,n[2]=this.alpha*n[2]/255,n[3]=this.alpha,e.uniform4fv(t.uColor,n)}};i(Gs,`defaults`,{color:`#F95C63`,mode:`multiply`,alpha:1}),i(Gs,`type`,`BlendColor`),i(Gs,`uniformLocations`,[`uColor`]),M.setClass(Gs);const Ks={multiply:`
    precision highp float;
    uniform sampler2D uTexture;
    uniform sampler2D uImage;
    uniform vec4 uColor;
    varying vec2 vTexCoord;
    varying vec2 vTexCoord2;
    void main() {
      vec4 color = texture2D(uTexture, vTexCoord);
      vec4 color2 = texture2D(uImage, vTexCoord2);
      color.rgba *= color2.rgba;
      gl_FragColor = color;
    }
    `,mask:`
    precision highp float;
    uniform sampler2D uTexture;
    uniform sampler2D uImage;
    uniform vec4 uColor;
    varying vec2 vTexCoord;
    varying vec2 vTexCoord2;
    void main() {
      vec4 color = texture2D(uTexture, vTexCoord);
      vec4 color2 = texture2D(uImage, vTexCoord2);
      color.a = color2.a;
      gl_FragColor = color;
    }
    `};var qs=class extends ${getCacheKey(){return`${this.type}_${this.mode}`}getFragmentSource(){return Ks[this.mode]}getVertexSource(){return`
    attribute vec2 aPosition;
    varying vec2 vTexCoord;
    varying vec2 vTexCoord2;
    uniform mat3 uTransformMatrix;
    void main() {
      vTexCoord = aPosition;
      vTexCoord2 = (uTransformMatrix * vec3(aPosition, 1.0)).xy;
      gl_Position = vec4(aPosition * 2.0 - 1.0, 0.0, 1.0);
    }
    `}applyToWebGL(e){let t=e.context,n=this.createTexture(e.filterBackend,this.image);this.bindAdditionalTexture(t,n,t.TEXTURE1),super.applyToWebGL(e),this.unbindAdditionalTexture(t,t.TEXTURE1)}createTexture(e,t){return e.getCachedTexture(t.cacheKey,t.getElement())}calculateMatrix(){let e=this.image,{width:t,height:n}=e.getElement();return[1/e.scaleX,0,0,0,1/e.scaleY,0,-e.left/t,-e.top/n,1]}applyTo2d({imageData:{data:e,width:t,height:n},filterBackend:{resources:r}}){let i=this.image;r.blendImage||(r.blendImage=P());let a=r.blendImage,o=a.getContext(`2d`);a.width!==t||a.height!==n?(a.width=t,a.height=n):o.clearRect(0,0,t,n),o.setTransform(i.scaleX,0,0,i.scaleY,i.left,i.top),o.drawImage(i.getElement(),0,0,t,n);let s=o.getImageData(0,0,t,n).data;for(let t=0;t<e.length;t+=4){let n=e[t],r=e[t+1],i=e[t+2],a=e[t+3],o=s[t],c=s[t+1],l=s[t+2],u=s[t+3];switch(this.mode){case`multiply`:e[t]=n*o/255,e[t+1]=r*c/255,e[t+2]=i*l/255,e[t+3]=a*u/255;break;case`mask`:e[t+3]=u}}}sendUniformData(e,t){let n=this.calculateMatrix();e.uniform1i(t.uImage,1),e.uniformMatrix3fv(t.uTransformMatrix,!1,n)}toObject(){return{...super.toObject(),image:this.image&&this.image.toObject()}}static async fromObject({type:e,image:t,...n},r){return ws.fromObject(t,r).then(e=>new this({...n,image:e}))}};i(qs,`type`,`BlendImage`),i(qs,`defaults`,{mode:`multiply`,alpha:1}),i(qs,`uniformLocations`,[`uTransformMatrix`,`uImage`]),M.setClass(qs);var Js=class extends ${getFragmentSource(){return`
    precision highp float;
    uniform sampler2D uTexture;
    uniform vec2 uDelta;
    varying vec2 vTexCoord;
    const float nSamples = 15.0;
    vec3 v3offset = vec3(12.9898, 78.233, 151.7182);
    float random(vec3 scale) {
      /* use the fragment position for a different seed per-pixel */
      return fract(sin(dot(gl_FragCoord.xyz, scale)) * 43758.5453);
    }
    void main() {
      vec4 color = vec4(0.0);
      float totalC = 0.0;
      float totalA = 0.0;
      float offset = random(v3offset);
      for (float t = -nSamples; t <= nSamples; t++) {
        float percent = (t + offset - 0.5) / nSamples;
        vec4 sample = texture2D(uTexture, vTexCoord + uDelta * percent);
        float weight = 1.0 - abs(percent);
        float alpha = weight * sample.a;
        color.rgb += sample.rgb * alpha;
        color.a += alpha;
        totalA += weight;
        totalC += alpha;
      }
      gl_FragColor.rgb = color.rgb / totalC;
      gl_FragColor.a = color.a / totalA;
    }
  `}applyTo(e){zs(e)?(this.aspectRatio=e.sourceWidth/e.sourceHeight,e.passes++,this._setupFrameBuffer(e),this.horizontal=!0,this.applyToWebGL(e),this._swapTextures(e),this._setupFrameBuffer(e),this.horizontal=!1,this.applyToWebGL(e),this._swapTextures(e)):this.applyTo2d(e)}applyTo2d({imageData:{data:e,width:t,height:n}}){this.aspectRatio=t/n,this.horizontal=!0;let r=this.getBlurValue()*t,i=new Uint8ClampedArray(e),a=4*t;for(let t=0;t<e.length;t+=4){let n=0,o=0,s=0,c=0,l=0,u=t-t%a,d=u+a;for(let i=-14;i<15;i++){let a=i/15,f=4*Math.floor(r*a),p=1-Math.abs(a),m=t+f;m<u?m=u:m>d&&(m=d);let h=e[m+3]*p;n+=e[m]*h,o+=e[m+1]*h,s+=e[m+2]*h,c+=h,l+=p}i[t]=n/c,i[t+1]=o/c,i[t+2]=s/c,i[t+3]=c/l}this.horizontal=!1,r=this.getBlurValue()*n;for(let t=0;t<i.length;t+=4){let n=0,o=0,s=0,c=0,l=0,u=t%a,d=i.length-a+u;for(let e=-14;e<15;e++){let f=e/15,p=Math.floor(r*f)*a,m=1-Math.abs(f),h=t+p;h<u?h=u:h>d&&(h=d);let g=i[h+3]*m;n+=i[h]*g,o+=i[h+1]*g,s+=i[h+2]*g,c+=g,l+=m}e[t]=n/c,e[t+1]=o/c,e[t+2]=s/c,e[t+3]=c/l}}sendUniformData(e,t){let n=this.chooseRightDelta();e.uniform2fv(t.uDelta,n)}isNeutralState(){return this.blur===0}getBlurValue(){let e=1,{horizontal:t,aspectRatio:n}=this;return t?n>1&&(e=1/n):n<1&&(e=n),e*this.blur*.12}chooseRightDelta(){let e=this.getBlurValue();return this.horizontal?[e,0]:[0,e]}};i(Js,`type`,`Blur`),i(Js,`defaults`,{blur:0}),i(Js,`uniformLocations`,[`uDelta`]),M.setClass(Js);var Ys=class extends ${getFragmentSource(){return`
  precision highp float;
  uniform sampler2D uTexture;
  uniform float uBrightness;
  varying vec2 vTexCoord;
  void main() {
    vec4 color = texture2D(uTexture, vTexCoord);
    color.rgb += uBrightness;
    gl_FragColor = color;
  }
`}applyTo2d({imageData:{data:e}}){let t=Math.round(255*this.brightness);for(let n=0;n<e.length;n+=4)e[n]+=t,e[n+1]+=t,e[n+2]+=t}isNeutralState(){return this.brightness===0}sendUniformData(e,t){e.uniform1f(t.uBrightness,this.brightness)}};i(Ys,`type`,`Brightness`),i(Ys,`defaults`,{brightness:0}),i(Ys,`uniformLocations`,[`uBrightness`]),M.setClass(Ys);const Xs={matrix:[1,0,0,0,0,0,1,0,0,0,0,0,1,0,0,0,0,0,1,0],colorsOnly:!0};var Zs=class extends ${getFragmentSource(){return`
  precision highp float;
  uniform sampler2D uTexture;
  varying vec2 vTexCoord;
  uniform mat4 uColorMatrix;
  uniform vec4 uConstants;
  void main() {
    vec4 color = texture2D(uTexture, vTexCoord);
    color *= uColorMatrix;
    color += uConstants;
    gl_FragColor = color;
  }`}applyTo2d(e){let t=e.imageData.data,n=this.matrix,r=this.colorsOnly;for(let e=0;e<t.length;e+=4){let i=t[e],a=t[e+1],o=t[e+2];if(t[e]=i*n[0]+a*n[1]+o*n[2]+255*n[4],t[e+1]=i*n[5]+a*n[6]+o*n[7]+255*n[9],t[e+2]=i*n[10]+a*n[11]+o*n[12]+255*n[14],!r){let r=t[e+3];t[e]+=r*n[3],t[e+1]+=r*n[8],t[e+2]+=r*n[13],t[e+3]=i*n[15]+a*n[16]+o*n[17]+r*n[18]+255*n[19]}}}sendUniformData(e,t){let n=this.matrix,r=[n[0],n[1],n[2],n[3],n[5],n[6],n[7],n[8],n[10],n[11],n[12],n[13],n[15],n[16],n[17],n[18]],i=[n[4],n[9],n[14],n[19]];e.uniformMatrix4fv(t.uColorMatrix,!1,r),e.uniform4fv(t.uConstants,i)}toObject(){return{...super.toObject(),matrix:[...this.matrix]}}};function Qs(e,t){var n;let r=(i(n=class extends Zs{toObject(){return{type:this.type,colorsOnly:this.colorsOnly}}},`type`,e),i(n,`defaults`,{colorsOnly:!1,matrix:t}),n);return M.setClass(r,e),r}i(Zs,`type`,`ColorMatrix`),i(Zs,`defaults`,Xs),i(Zs,`uniformLocations`,[`uColorMatrix`,`uConstants`]),M.setClass(Zs);const $s=Qs(`Brownie`,[.5997,.34553,-.27082,0,.186,-.0377,.86095,.15059,0,-.1449,.24113,-.07441,.44972,0,-.02965,0,0,0,1,0]),ec=Qs(`Vintage`,[.62793,.32021,-.03965,0,.03784,.02578,.64411,.03259,0,.02926,.0466,-.08512,.52416,0,.02023,0,0,0,1,0]),tc=Qs(`Kodachrome`,[1.12855,-.39673,-.03992,0,.24991,-.16404,1.08352,-.05498,0,.09698,-.16786,-.56034,1.60148,0,.13972,0,0,0,1,0]),nc=Qs(`Technicolor`,[1.91252,-.85453,-.09155,0,.04624,-.30878,1.76589,-.10601,0,-.27589,-.2311,-.75018,1.84759,0,.12137,0,0,0,1,0]),rc=Qs(`Polaroid`,[1.438,-.062,-.062,0,0,-.122,1.378,-.122,0,0,-.016,-.016,1.483,0,0,0,0,0,1,0]),ic=Qs(`Sepia`,[.393,.769,.189,0,0,.349,.686,.168,0,0,.272,.534,.131,0,0,0,0,0,1,0]),ac=Qs(`BlackWhite`,[1.5,1.5,1.5,0,-1,1.5,1.5,1.5,0,-1,1.5,1.5,1.5,0,-1,0,0,0,1,0]);var oc=class extends ${constructor(e={}){super(e),this.subFilters=e.subFilters||[]}applyTo(e){zs(e)&&(e.passes+=this.subFilters.length-1),this.subFilters.forEach(t=>{t.applyTo(e)})}toObject(){return{type:this.type,subFilters:this.subFilters.map(e=>e.toObject())}}isNeutralState(){return!this.subFilters.some(e=>!e.isNeutralState())}static fromObject(e,t){return Promise.all((e.subFilters||[]).map(e=>M.getClass(e.type).fromObject(e,t))).then(e=>new this({subFilters:e}))}};i(oc,`type`,`Composed`),M.setClass(oc);var sc=class extends ${getFragmentSource(){return`
  precision highp float;
  uniform sampler2D uTexture;
  uniform float uContrast;
  varying vec2 vTexCoord;
  void main() {
    vec4 color = texture2D(uTexture, vTexCoord);
    float contrastF = 1.015 * (uContrast + 1.0) / (1.0 * (1.015 - uContrast));
    color.rgb = contrastF * (color.rgb - 0.5) + 0.5;
    gl_FragColor = color;
  }`}isNeutralState(){return this.contrast===0}applyTo2d({imageData:{data:e}}){let t=Math.floor(255*this.contrast),n=259*(t+255)/(255*(259-t));for(let t=0;t<e.length;t+=4)e[t]=n*(e[t]-128)+128,e[t+1]=n*(e[t+1]-128)+128,e[t+2]=n*(e[t+2]-128)+128}sendUniformData(e,t){e.uniform1f(t.uContrast,this.contrast)}};i(sc,`type`,`Contrast`),i(sc,`defaults`,{contrast:0}),i(sc,`uniformLocations`,[`uContrast`]),M.setClass(sc);const cc={Convolute_3_1:`
    precision highp float;
    uniform sampler2D uTexture;
    uniform float uMatrix[9];
    uniform float uStepW;
    uniform float uStepH;
    varying vec2 vTexCoord;
    void main() {
      vec4 color = vec4(0, 0, 0, 0);
      for (float h = 0.0; h < 3.0; h+=1.0) {
        for (float w = 0.0; w < 3.0; w+=1.0) {
          vec2 matrixPos = vec2(uStepW * (w - 1), uStepH * (h - 1));
          color += texture2D(uTexture, vTexCoord + matrixPos) * uMatrix[int(h * 3.0 + w)];
        }
      }
      gl_FragColor = color;
    }
    `,Convolute_3_0:`
    precision highp float;
    uniform sampler2D uTexture;
    uniform float uMatrix[9];
    uniform float uStepW;
    uniform float uStepH;
    varying vec2 vTexCoord;
    void main() {
      vec4 color = vec4(0, 0, 0, 1);
      for (float h = 0.0; h < 3.0; h+=1.0) {
        for (float w = 0.0; w < 3.0; w+=1.0) {
          vec2 matrixPos = vec2(uStepW * (w - 1.0), uStepH * (h - 1.0));
          color.rgb += texture2D(uTexture, vTexCoord + matrixPos).rgb * uMatrix[int(h * 3.0 + w)];
        }
      }
      float alpha = texture2D(uTexture, vTexCoord).a;
      gl_FragColor = color;
      gl_FragColor.a = alpha;
    }
    `,Convolute_5_1:`
    precision highp float;
    uniform sampler2D uTexture;
    uniform float uMatrix[25];
    uniform float uStepW;
    uniform float uStepH;
    varying vec2 vTexCoord;
    void main() {
      vec4 color = vec4(0, 0, 0, 0);
      for (float h = 0.0; h < 5.0; h+=1.0) {
        for (float w = 0.0; w < 5.0; w+=1.0) {
          vec2 matrixPos = vec2(uStepW * (w - 2.0), uStepH * (h - 2.0));
          color += texture2D(uTexture, vTexCoord + matrixPos) * uMatrix[int(h * 5.0 + w)];
        }
      }
      gl_FragColor = color;
    }
    `,Convolute_5_0:`
    precision highp float;
    uniform sampler2D uTexture;
    uniform float uMatrix[25];
    uniform float uStepW;
    uniform float uStepH;
    varying vec2 vTexCoord;
    void main() {
      vec4 color = vec4(0, 0, 0, 1);
      for (float h = 0.0; h < 5.0; h+=1.0) {
        for (float w = 0.0; w < 5.0; w+=1.0) {
          vec2 matrixPos = vec2(uStepW * (w - 2.0), uStepH * (h - 2.0));
          color.rgb += texture2D(uTexture, vTexCoord + matrixPos).rgb * uMatrix[int(h * 5.0 + w)];
        }
      }
      float alpha = texture2D(uTexture, vTexCoord).a;
      gl_FragColor = color;
      gl_FragColor.a = alpha;
    }
    `,Convolute_7_1:`
    precision highp float;
    uniform sampler2D uTexture;
    uniform float uMatrix[49];
    uniform float uStepW;
    uniform float uStepH;
    varying vec2 vTexCoord;
    void main() {
      vec4 color = vec4(0, 0, 0, 0);
      for (float h = 0.0; h < 7.0; h+=1.0) {
        for (float w = 0.0; w < 7.0; w+=1.0) {
          vec2 matrixPos = vec2(uStepW * (w - 3.0), uStepH * (h - 3.0));
          color += texture2D(uTexture, vTexCoord + matrixPos) * uMatrix[int(h * 7.0 + w)];
        }
      }
      gl_FragColor = color;
    }
    `,Convolute_7_0:`
    precision highp float;
    uniform sampler2D uTexture;
    uniform float uMatrix[49];
    uniform float uStepW;
    uniform float uStepH;
    varying vec2 vTexCoord;
    void main() {
      vec4 color = vec4(0, 0, 0, 1);
      for (float h = 0.0; h < 7.0; h+=1.0) {
        for (float w = 0.0; w < 7.0; w+=1.0) {
          vec2 matrixPos = vec2(uStepW * (w - 3.0), uStepH * (h - 3.0));
          color.rgb += texture2D(uTexture, vTexCoord + matrixPos).rgb * uMatrix[int(h * 7.0 + w)];
        }
      }
      float alpha = texture2D(uTexture, vTexCoord).a;
      gl_FragColor = color;
      gl_FragColor.a = alpha;
    }
    `,Convolute_9_1:`
    precision highp float;
    uniform sampler2D uTexture;
    uniform float uMatrix[81];
    uniform float uStepW;
    uniform float uStepH;
    varying vec2 vTexCoord;
    void main() {
      vec4 color = vec4(0, 0, 0, 0);
      for (float h = 0.0; h < 9.0; h+=1.0) {
        for (float w = 0.0; w < 9.0; w+=1.0) {
          vec2 matrixPos = vec2(uStepW * (w - 4.0), uStepH * (h - 4.0));
          color += texture2D(uTexture, vTexCoord + matrixPos) * uMatrix[int(h * 9.0 + w)];
        }
      }
      gl_FragColor = color;
    }
    `,Convolute_9_0:`
    precision highp float;
    uniform sampler2D uTexture;
    uniform float uMatrix[81];
    uniform float uStepW;
    uniform float uStepH;
    varying vec2 vTexCoord;
    void main() {
      vec4 color = vec4(0, 0, 0, 1);
      for (float h = 0.0; h < 9.0; h+=1.0) {
        for (float w = 0.0; w < 9.0; w+=1.0) {
          vec2 matrixPos = vec2(uStepW * (w - 4.0), uStepH * (h - 4.0));
          color.rgb += texture2D(uTexture, vTexCoord + matrixPos).rgb * uMatrix[int(h * 9.0 + w)];
        }
      }
      float alpha = texture2D(uTexture, vTexCoord).a;
      gl_FragColor = color;
      gl_FragColor.a = alpha;
    }
    `};var lc=class extends ${getCacheKey(){return`${this.type}_${Math.sqrt(this.matrix.length)}_${+!!this.opaque}`}getFragmentSource(){return cc[this.getCacheKey()]}applyTo2d(e){let t=e.imageData,n=t.data,r=this.matrix,i=Math.round(Math.sqrt(r.length)),a=Math.floor(i/2),o=t.width,s=t.height,c=e.ctx.createImageData(o,s),l=c.data,u=+!!this.opaque,d,f,p,m,h,g,_,v,y,b,x,S,C;for(x=0;x<s;x++)for(b=0;b<o;b++){for(h=4*(x*o+b),d=0,f=0,p=0,m=0,C=0;C<i;C++)for(S=0;S<i;S++)_=x+C-a,g=b+S-a,_<0||_>=s||g<0||g>=o||(v=4*(_*o+g),y=r[C*i+S],d+=n[v]*y,f+=n[v+1]*y,p+=n[v+2]*y,u||(m+=n[v+3]*y));l[h]=d,l[h+1]=f,l[h+2]=p,l[h+3]=u?n[h+3]:m}e.imageData=c}sendUniformData(e,t){e.uniform1fv(t.uMatrix,this.matrix)}toObject(){return{...super.toObject(),opaque:this.opaque,matrix:[...this.matrix]}}};i(lc,`type`,`Convolute`),i(lc,`defaults`,{opaque:!1,matrix:[0,0,0,0,1,0,0,0,0]}),i(lc,`uniformLocations`,[`uMatrix`,`uOpaque`,`uHalfSize`,`uSize`]),M.setClass(lc);const uc=`Gamma`;var dc=class extends ${getFragmentSource(){return`
  precision highp float;
  uniform sampler2D uTexture;
  uniform vec3 uGamma;
  varying vec2 vTexCoord;
  void main() {
    vec4 color = texture2D(uTexture, vTexCoord);
    vec3 correction = (1.0 / uGamma);
    color.r = pow(color.r, correction.r);
    color.g = pow(color.g, correction.g);
    color.b = pow(color.b, correction.b);
    gl_FragColor = color;
    gl_FragColor.rgb *= color.a;
  }
`}constructor(e={}){super(e),this.gamma=e.gamma||this.constructor.defaults.gamma.concat()}applyTo2d({imageData:{data:e}}){let t=this.gamma,n=1/t[0],r=1/t[1],i=1/t[2];this.rgbValues||(this.rgbValues={r:new Uint8Array(256),g:new Uint8Array(256),b:new Uint8Array(256)});let a=this.rgbValues;for(let e=0;e<256;e++)a.r[e]=255*(e/255)**n,a.g[e]=255*(e/255)**r,a.b[e]=255*(e/255)**i;for(let t=0;t<e.length;t+=4)e[t]=a.r[e[t]],e[t+1]=a.g[e[t+1]],e[t+2]=a.b[e[t+2]]}sendUniformData(e,t){e.uniform3fv(t.uGamma,this.gamma)}isNeutralState(){let{gamma:e}=this;return e[0]===1&&e[1]===1&&e[2]===1}toObject(){return{type:uc,gamma:this.gamma.concat()}}};i(dc,`type`,uc),i(dc,`defaults`,{gamma:[1,1,1]}),i(dc,`uniformLocations`,[`uGamma`]),M.setClass(dc);const fc={average:`
    precision highp float;
    uniform sampler2D uTexture;
    varying vec2 vTexCoord;
    void main() {
      vec4 color = texture2D(uTexture, vTexCoord);
      float average = (color.r + color.b + color.g) / 3.0;
      gl_FragColor = vec4(average, average, average, color.a);
    }
    `,lightness:`
    precision highp float;
    uniform sampler2D uTexture;
    uniform int uMode;
    varying vec2 vTexCoord;
    void main() {
      vec4 col = texture2D(uTexture, vTexCoord);
      float average = (max(max(col.r, col.g),col.b) + min(min(col.r, col.g),col.b)) / 2.0;
      gl_FragColor = vec4(average, average, average, col.a);
    }
    `,luminosity:`
    precision highp float;
    uniform sampler2D uTexture;
    uniform int uMode;
    varying vec2 vTexCoord;
    void main() {
      vec4 col = texture2D(uTexture, vTexCoord);
      float average = 0.21 * col.r + 0.72 * col.g + 0.07 * col.b;
      gl_FragColor = vec4(average, average, average, col.a);
    }
    `};var pc=class extends ${applyTo2d({imageData:{data:e}}){for(let t,n=0;n<e.length;n+=4){let r=e[n],i=e[n+1],a=e[n+2];switch(this.mode){case`average`:t=(r+i+a)/3;break;case`lightness`:t=(Math.min(r,i,a)+Math.max(r,i,a))/2;break;case`luminosity`:t=.21*r+.72*i+.07*a}e[n+2]=e[n+1]=e[n]=t}}getCacheKey(){return`${this.type}_${this.mode}`}getFragmentSource(){return fc[this.mode]}sendUniformData(e,t){e.uniform1i(t.uMode,1)}isNeutralState(){return!1}};i(pc,`type`,`Grayscale`),i(pc,`defaults`,{mode:`average`}),i(pc,`uniformLocations`,[`uMode`]),M.setClass(pc);const mc={...Xs,rotation:0};var hc=class extends Zs{calculateMatrix(){let e=this.rotation*Math.PI,t=Se(e),n=Ce(e),r=1/3,i=Math.sqrt(r)*n,a=1-t;this.matrix=[t+a/3,r*a-i,r*a+i,0,0,r*a+i,t+r*a,r*a-i,0,0,r*a-i,r*a+i,t+r*a,0,0,0,0,0,1,0]}isNeutralState(){return this.rotation===0}applyTo(e){this.calculateMatrix(),super.applyTo(e)}toObject(){return{type:this.type,rotation:this.rotation}}};i(hc,`type`,`HueRotation`),i(hc,`defaults`,mc),M.setClass(hc);var gc=class extends ${applyTo2d({imageData:{data:e}}){for(let t=0;t<e.length;t+=4)e[t]=255-e[t],e[t+1]=255-e[t+1],e[t+2]=255-e[t+2],this.alpha&&(e[t+3]=255-e[t+3])}getFragmentSource(){return`
  precision highp float;
  uniform sampler2D uTexture;
  uniform int uInvert;
  uniform int uAlpha;
  varying vec2 vTexCoord;
  void main() {
    vec4 color = texture2D(uTexture, vTexCoord);
    if (uInvert == 1) {
      if (uAlpha == 1) {
        gl_FragColor = vec4(1.0 - color.r,1.0 -color.g,1.0 -color.b,1.0 -color.a);
      } else {
        gl_FragColor = vec4(1.0 - color.r,1.0 -color.g,1.0 -color.b,color.a);
      }
    } else {
      gl_FragColor = color;
    }
  }
`}isNeutralState(){return!this.invert}sendUniformData(e,t){e.uniform1i(t.uInvert,Number(this.invert)),e.uniform1i(t.uAlpha,Number(this.alpha))}};i(gc,`type`,`Invert`),i(gc,`defaults`,{alpha:!1,invert:!0}),i(gc,`uniformLocations`,[`uInvert`,`uAlpha`]),M.setClass(gc);var _c=class extends ${getFragmentSource(){return`
  precision highp float;
  uniform sampler2D uTexture;
  uniform float uStepH;
  uniform float uNoise;
  uniform float uSeed;
  varying vec2 vTexCoord;
  float rand(vec2 co, float seed, float vScale) {
    return fract(sin(dot(co.xy * vScale ,vec2(12.9898 , 78.233))) * 43758.5453 * (seed + 0.01) / 2.0);
  }
  void main() {
    vec4 color = texture2D(uTexture, vTexCoord);
    color.rgb += (0.5 - rand(vTexCoord, uSeed, 0.1 / uStepH)) * uNoise;
    gl_FragColor = color;
  }
`}applyTo2d({imageData:{data:e}}){let t=this.noise;for(let n=0;n<e.length;n+=4){let r=(.5-Math.random())*t;e[n]+=r,e[n+1]+=r,e[n+2]+=r}}sendUniformData(e,t){e.uniform1f(t.uNoise,this.noise/255),e.uniform1f(t.uSeed,Math.random())}isNeutralState(){return this.noise===0}};i(_c,`type`,`Noise`),i(_c,`defaults`,{noise:0}),i(_c,`uniformLocations`,[`uNoise`,`uSeed`]),M.setClass(_c);var vc=class extends ${applyTo2d({imageData:{data:e,width:t,height:n}}){for(let r=0;r<n;r+=this.blocksize)for(let i=0;i<t;i+=this.blocksize){let a=4*r*t+4*i,o=e[a],s=e[a+1],c=e[a+2],l=e[a+3];for(let a=r;a<Math.min(r+this.blocksize,n);a++)for(let n=i;n<Math.min(i+this.blocksize,t);n++){let r=4*a*t+4*n;e[r]=o,e[r+1]=s,e[r+2]=c,e[r+3]=l}}}isNeutralState(){return this.blocksize===1}getFragmentSource(){return`
  precision highp float;
  uniform sampler2D uTexture;
  uniform float uBlocksize;
  uniform float uStepW;
  uniform float uStepH;
  varying vec2 vTexCoord;
  void main() {
    float blockW = uBlocksize * uStepW;
    float blockH = uBlocksize * uStepH;
    int posX = int(vTexCoord.x / blockW);
    int posY = int(vTexCoord.y / blockH);
    float fposX = float(posX);
    float fposY = float(posY);
    vec2 squareCoords = vec2(fposX * blockW, fposY * blockH);
    vec4 color = texture2D(uTexture, squareCoords);
    gl_FragColor = color;
  }
`}sendUniformData(e,t){e.uniform1f(t.uBlocksize,this.blocksize)}};i(vc,`type`,`Pixelate`),i(vc,`defaults`,{blocksize:4}),i(vc,`uniformLocations`,[`uBlocksize`]),M.setClass(vc);var yc=class extends ${getFragmentSource(){return`
precision highp float;
uniform sampler2D uTexture;
uniform vec4 uLow;
uniform vec4 uHigh;
varying vec2 vTexCoord;
void main() {
  gl_FragColor = texture2D(uTexture, vTexCoord);
  if(all(greaterThan(gl_FragColor.rgb,uLow.rgb)) && all(greaterThan(uHigh.rgb,gl_FragColor.rgb))) {
    gl_FragColor.a = 0.0;
  }
}
`}applyTo2d({imageData:{data:e}}){let t=255*this.distance,n=new G(this.color).getSource(),r=[n[0]-t,n[1]-t,n[2]-t],i=[n[0]+t,n[1]+t,n[2]+t];for(let t=0;t<e.length;t+=4){let n=e[t],a=e[t+1],o=e[t+2];n>r[0]&&a>r[1]&&o>r[2]&&n<i[0]&&a<i[1]&&o<i[2]&&(e[t+3]=0)}}sendUniformData(e,t){let n=new G(this.color).getSource(),r=this.distance,i=[0+n[0]/255-r,0+n[1]/255-r,0+n[2]/255-r,1],a=[n[0]/255+r,n[1]/255+r,n[2]/255+r,1];e.uniform4fv(t.uLow,i),e.uniform4fv(t.uHigh,a)}};i(yc,`type`,`RemoveColor`),i(yc,`defaults`,{color:`#FFFFFF`,distance:.02,useAlpha:!1}),i(yc,`uniformLocations`,[`uLow`,`uHigh`]),M.setClass(yc);var bc=class extends ${sendUniformData(e,t){e.uniform2fv(t.uDelta,this.horizontal?[1/this.width,0]:[0,1/this.height]),e.uniform1fv(t.uTaps,this.taps)}getFilterWindow(){let e=this.tempScale;return Math.ceil(this.lanczosLobes/e)}getCacheKey(){let e=this.getFilterWindow();return`${this.type}_${e}`}getFragmentSource(){let e=this.getFilterWindow();return this.generateShader(e)}getTaps(){let e=this.lanczosCreate(this.lanczosLobes),t=this.tempScale,n=this.getFilterWindow(),r=Array(n);for(let i=1;i<=n;i++)r[i-1]=e(i*t);return r}generateShader(e){let t=Array(e);for(let n=1;n<=e;n++)t[n-1]=`${n}.0 * uDelta`;return`\n      precision highp float;\n      uniform sampler2D uTexture;\n      uniform vec2 uDelta;\n      varying vec2 vTexCoord;\n      uniform float uTaps[${e}];\n      void main() {\n        vec4 color = texture2D(uTexture, vTexCoord);\n        float sum = 1.0;\n        ${t.map((e,t)=>`\n              color += texture2D(uTexture, vTexCoord + ${e}) * uTaps[${t}] + texture2D(uTexture, vTexCoord - ${e}) * uTaps[${t}];\n              sum += 2.0 * uTaps[${t}];\n            `).join(`
`)}\n        gl_FragColor = color / sum;\n      }\n    `}applyToForWebgl(e){e.passes++,this.width=e.sourceWidth,this.horizontal=!0,this.dW=Math.round(this.width*this.scaleX),this.dH=e.sourceHeight,this.tempScale=this.dW/this.width,this.taps=this.getTaps(),e.destinationWidth=this.dW,super.applyTo(e),e.sourceWidth=e.destinationWidth,this.height=e.sourceHeight,this.horizontal=!1,this.dH=Math.round(this.height*this.scaleY),this.tempScale=this.dH/this.height,this.taps=this.getTaps(),e.destinationHeight=this.dH,super.applyTo(e),e.sourceHeight=e.destinationHeight}applyTo(e){zs(e)?this.applyToForWebgl(e):this.applyTo2d(e)}isNeutralState(){return this.scaleX===1&&this.scaleY===1}lanczosCreate(e){return t=>{if(t>=e||t<=-e)return 0;if(t<1.1920929e-7&&t>-1.1920929e-7)return 1;let n=(t*=Math.PI)/e;return Math.sin(t)/t*Math.sin(n)/n}}applyTo2d(e){let t=e.imageData,n=this.scaleX,r=this.scaleY;this.rcpScaleX=1/n,this.rcpScaleY=1/r;let i=t.width,a=t.height,o=Math.round(i*n),s=Math.round(a*r),c;c=this.resizeType===`sliceHack`?this.sliceByTwo(e,i,a,o,s):this.resizeType===`hermite`?this.hermiteFastResize(e,i,a,o,s):this.resizeType===`bilinear`?this.bilinearFiltering(e,i,a,o,s):this.resizeType===`lanczos`?this.lanczosResize(e,i,a,o,s):new ImageData(o,s),e.imageData=c}sliceByTwo(e,t,n,r,i){let a=e.imageData,o=.5,s=!1,c=!1,l=t*o,u=n*o,d=e.filterBackend.resources,f=0,p=0,m=t,h=0;d.sliceByTwo||(d.sliceByTwo=P());let g=d.sliceByTwo;(g.width<1.5*t||g.height<n)&&(g.width=1.5*t,g.height=n);let _=g.getContext(`2d`);for(_.clearRect(0,0,1.5*t,n),_.putImageData(a,0,0),r=Math.floor(r),i=Math.floor(i);!s||!c;)t=l,n=u,r<Math.floor(l*o)?l=Math.floor(l*o):(l=r,s=!0),i<Math.floor(u*o)?u=Math.floor(u*o):(u=i,c=!0),_.drawImage(g,f,p,t,n,m,h,l,u),f=m,p=h,h+=u;return _.getImageData(f,p,r,i)}lanczosResize(e,t,n,r,i){let a=e.imageData.data,o=e.ctx.createImageData(r,i),s=o.data,c=this.lanczosCreate(this.lanczosLobes),l=this.rcpScaleX,u=this.rcpScaleY,d=2/this.rcpScaleX,f=2/this.rcpScaleY,p=Math.ceil(l*this.lanczosLobes/2),m=Math.ceil(u*this.lanczosLobes/2),h={},g={x:0,y:0},_={x:0,y:0};return function e(v){let y,b,x,S,C,w,ee,T,E,D,O;for(g.x=(v+.5)*l,_.x=Math.floor(g.x),y=0;y<i;y++){for(g.y=(y+.5)*u,_.y=Math.floor(g.y),C=0,w=0,ee=0,T=0,E=0,b=_.x-p;b<=_.x+p;b++)if(!(b<0||b>=t)){D=Math.floor(1e3*Math.abs(b-g.x)),h[D]||(h[D]={});for(let e=_.y-m;e<=_.y+m;e++)e<0||e>=n||(O=Math.floor(1e3*Math.abs(e-g.y)),h[D][O]||(h[D][O]=c(Math.sqrt((D*d)**2+(O*f)**2)/1e3)),x=h[D][O],x>0&&(S=4*(e*t+b),C+=x,w+=x*a[S],ee+=x*a[S+1],T+=x*a[S+2],E+=x*a[S+3]))}S=4*(y*r+v),s[S]=w/C,s[S+1]=ee/C,s[S+2]=T/C,s[S+3]=E/C}return++v<r?e(v):o}(0)}bilinearFiltering(e,t,n,r,i){let a,o,s,c,l,u,d,f,p,m,h,g,_,v=0,y=this.rcpScaleX,b=this.rcpScaleY,x=4*(t-1),S=e.imageData.data,C=e.ctx.createImageData(r,i),w=C.data;for(d=0;d<i;d++)for(f=0;f<r;f++)for(l=Math.floor(y*f),u=Math.floor(b*d),p=y*f-l,m=b*d-u,_=4*(u*t+l),h=0;h<4;h++)a=S[_+h],o=S[_+4+h],s=S[_+x+h],c=S[_+x+4+h],g=a*(1-p)*(1-m)+o*p*(1-m)+s*m*(1-p)+c*p*m,w[v++]=g;return C}hermiteFastResize(e,t,n,r,i){let a=this.rcpScaleX,o=this.rcpScaleY,s=Math.ceil(a/2),c=Math.ceil(o/2),l=e.imageData.data,u=e.ctx.createImageData(r,i),d=u.data;for(let e=0;e<i;e++)for(let n=0;n<r;n++){let i=4*(n+e*r),u,f=0,p=0,m=0,h=0,g=0,_=0,v=(e+.5)*o;for(let r=Math.floor(e*o);r<(e+1)*o;r++){let e=Math.abs(v-(r+.5))/c,i=(n+.5)*a,o=e*e;for(let e=Math.floor(n*a);e<(n+1)*a;e++){let n=Math.abs(i-(e+.5))/s,a=Math.sqrt(o+n*n);a>1&&a<-1||(u=2*a*a*a-3*a*a+1,u>0&&(n=4*(e+r*t),_+=u*l[n+3],p+=u,l[n+3]<255&&(u=u*l[n+3]/250),m+=u*l[n],h+=u*l[n+1],g+=u*l[n+2],f+=u))}}d[i]=m/f,d[i+1]=h/f,d[i+2]=g/f,d[i+3]=_/p}return u}};i(bc,`type`,`Resize`),i(bc,`defaults`,{resizeType:`hermite`,scaleX:1,scaleY:1,lanczosLobes:3}),i(bc,`uniformLocations`,[`uDelta`,`uTaps`]),M.setClass(bc);var xc=class extends ${getFragmentSource(){return`
  precision highp float;
  uniform sampler2D uTexture;
  uniform float uSaturation;
  varying vec2 vTexCoord;
  void main() {
    vec4 color = texture2D(uTexture, vTexCoord);
    float rgMax = max(color.r, color.g);
    float rgbMax = max(rgMax, color.b);
    color.r += rgbMax != color.r ? (rgbMax - color.r) * uSaturation : 0.00;
    color.g += rgbMax != color.g ? (rgbMax - color.g) * uSaturation : 0.00;
    color.b += rgbMax != color.b ? (rgbMax - color.b) * uSaturation : 0.00;
    gl_FragColor = color;
  }
`}applyTo2d({imageData:{data:e}}){let t=-this.saturation;for(let n=0;n<e.length;n+=4){let r=e[n],i=e[n+1],a=e[n+2],o=Math.max(r,i,a);e[n]+=o===r?0:(o-r)*t,e[n+1]+=o===i?0:(o-i)*t,e[n+2]+=o===a?0:(o-a)*t}}sendUniformData(e,t){e.uniform1f(t.uSaturation,-this.saturation)}isNeutralState(){return this.saturation===0}};i(xc,`type`,`Saturation`),i(xc,`defaults`,{saturation:0}),i(xc,`uniformLocations`,[`uSaturation`]),M.setClass(xc);var Sc=class extends ${getFragmentSource(){return`
  precision highp float;
  uniform sampler2D uTexture;
  uniform float uVibrance;
  varying vec2 vTexCoord;
  void main() {
    vec4 color = texture2D(uTexture, vTexCoord);
    float max = max(color.r, max(color.g, color.b));
    float avg = (color.r + color.g + color.b) / 3.0;
    float amt = (abs(max - avg) * 2.0) * uVibrance;
    color.r += max != color.r ? (max - color.r) * amt : 0.00;
    color.g += max != color.g ? (max - color.g) * amt : 0.00;
    color.b += max != color.b ? (max - color.b) * amt : 0.00;
    gl_FragColor = color;
  }
`}applyTo2d({imageData:{data:e}}){let t=-this.vibrance;for(let n=0;n<e.length;n+=4){let r=e[n],i=e[n+1],a=e[n+2],o=Math.max(r,i,a),s=(r+i+a)/3,c=2*Math.abs(o-s)/255*t;e[n]+=o===r?0:(o-r)*c,e[n+1]+=o===i?0:(o-i)*c,e[n+2]+=o===a?0:(o-a)*c}}sendUniformData(e,t){e.uniform1f(t.uVibrance,-this.vibrance)}isNeutralState(){return this.vibrance===0}};i(Sc,`type`,`Vibrance`),i(Sc,`defaults`,{vibrance:0}),i(Sc,`uniformLocations`,[`uVibrance`]),M.setClass(Sc);var Cc=t({BaseFilter:()=>$,BlackWhite:()=>ac,BlendColor:()=>Gs,BlendImage:()=>qs,Blur:()=>Js,Brightness:()=>Ys,Brownie:()=>$s,ColorMatrix:()=>Zs,Composed:()=>oc,Contrast:()=>sc,Convolute:()=>lc,Gamma:()=>dc,Grayscale:()=>pc,HueRotation:()=>hc,Invert:()=>gc,Kodachrome:()=>tc,Noise:()=>_c,Pixelate:()=>vc,Polaroid:()=>rc,RemoveColor:()=>yc,Resize:()=>bc,Saturation:()=>xc,Sepia:()=>ic,Technicolor:()=>nc,Vibrance:()=>Sc,Vintage:()=>ec});

/***/ }

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		if (!(moduleId in __webpack_modules__)) {
/******/ 			delete __webpack_module_cache__[moduleId];
/******/ 			var e = new Error("Cannot find module '" + moduleId + "'");
/******/ 			e.code = 'MODULE_NOT_FOUND';
/******/ 			throw e;
/******/ 		}
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/******/ 	// expose the modules object (__webpack_modules__)
/******/ 	__webpack_require__.m = __webpack_modules__;
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/compat get default export */
/******/ 	(() => {
/******/ 		// getDefaultExport function for compatibility with non-harmony modules
/******/ 		__webpack_require__.n = (module) => {
/******/ 			var getter = module && module.__esModule ?
/******/ 				() => (module['default']) :
/******/ 				() => (module);
/******/ 			__webpack_require__.d(getter, { a: getter });
/******/ 			return getter;
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/define property getters */
/******/ 	(() => {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = (exports, definition) => {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/ensure chunk */
/******/ 	(() => {
/******/ 		__webpack_require__.f = {};
/******/ 		// This file contains only the entry chunk.
/******/ 		// The chunk loading function for additional chunks
/******/ 		__webpack_require__.e = (chunkId) => {
/******/ 			return Promise.all(Object.keys(__webpack_require__.f).reduce((promises, key) => {
/******/ 				__webpack_require__.f[key](chunkId, promises);
/******/ 				return promises;
/******/ 			}, []));
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/get javascript chunk filename */
/******/ 	(() => {
/******/ 		// This function allow to reference async chunks
/******/ 		__webpack_require__.u = (chunkId) => {
/******/ 			// return url for filenames based on template
/******/ 			return "" + chunkId + ".js?ver=" + {"vendors-node_modules_uppy_core_lib_UIPlugin_js":"d972fcad282d89b2af4e","vendors-node_modules_uppy_core_lib_index_js":"f07e4a770ba6d7f15ae7","vendors-node_modules_uppy_drag-drop_lib_index_js":"5276771047b17b121ca9","vendors-node_modules_uppy_xhr-upload_lib_index_js":"cf35c331fdbbbccda5fe"}[chunkId] + "";
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/get mini-css chunk filename */
/******/ 	(() => {
/******/ 		// This function allow to reference async chunks
/******/ 		__webpack_require__.miniCssF = (chunkId) => {
/******/ 			// return url for filenames based on template
/******/ 			return undefined;
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/load script */
/******/ 	(() => {
/******/ 		var inProgress = {};
/******/ 		var dataWebpackPrefix = "overcustomise:";
/******/ 		// loadScript function to load a script via script tag
/******/ 		__webpack_require__.l = (url, done, key, chunkId) => {
/******/ 			if(inProgress[url]) { inProgress[url].push(done); return; }
/******/ 			var script, needAttach;
/******/ 			if(key !== undefined) {
/******/ 				var scripts = document.getElementsByTagName("script");
/******/ 				for(var i = 0; i < scripts.length; i++) {
/******/ 					var s = scripts[i];
/******/ 					if(s.getAttribute("src") == url || s.getAttribute("data-webpack") == dataWebpackPrefix + key) { script = s; break; }
/******/ 				}
/******/ 			}
/******/ 			if(!script) {
/******/ 				needAttach = true;
/******/ 				script = document.createElement('script');
/******/ 		
/******/ 				script.charset = 'utf-8';
/******/ 				if (__webpack_require__.nc) {
/******/ 					script.setAttribute("nonce", __webpack_require__.nc);
/******/ 				}
/******/ 				script.setAttribute("data-webpack", dataWebpackPrefix + key);
/******/ 		
/******/ 				script.src = url;
/******/ 			}
/******/ 			inProgress[url] = [done];
/******/ 			var onScriptComplete = (prev, event) => {
/******/ 				// avoid mem leaks in IE.
/******/ 				script.onerror = script.onload = null;
/******/ 				clearTimeout(timeout);
/******/ 				var doneFns = inProgress[url];
/******/ 				delete inProgress[url];
/******/ 				script.parentNode && script.parentNode.removeChild(script);
/******/ 				doneFns && doneFns.forEach((fn) => (fn(event)));
/******/ 				if(prev) return prev(event);
/******/ 			}
/******/ 			var timeout = setTimeout(onScriptComplete.bind(null, undefined, { type: 'timeout', target: script }), 120000);
/******/ 			script.onerror = onScriptComplete.bind(null, script.onerror);
/******/ 			script.onload = onScriptComplete.bind(null, script.onload);
/******/ 			needAttach && document.head.appendChild(script);
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/publicPath */
/******/ 	(() => {
/******/ 		var scriptUrl;
/******/ 		if (globalThis.importScripts) scriptUrl = globalThis.location + "";
/******/ 		var document = globalThis.document;
/******/ 		if (!scriptUrl && document) {
/******/ 			if (document.currentScript && document.currentScript.tagName.toUpperCase() === 'SCRIPT')
/******/ 				scriptUrl = document.currentScript.src;
/******/ 			if (!scriptUrl) {
/******/ 				var scripts = document.getElementsByTagName("script");
/******/ 				if(scripts.length) {
/******/ 					var i = scripts.length - 1;
/******/ 					while (i > -1 && (!scriptUrl || !/^http(s?):/.test(scriptUrl))) scriptUrl = scripts[i--].src;
/******/ 				}
/******/ 			}
/******/ 		}
/******/ 		// When supporting browsers where an automatic publicPath is not supported you must specify an output.publicPath manually via configuration
/******/ 		// or pass an empty string ("") and set the __webpack_public_path__ variable from your code to use your own logic.
/******/ 		if (!scriptUrl) throw new Error("Automatic publicPath is not supported in this browser");
/******/ 		scriptUrl = scriptUrl.replace(/^blob:/, "").replace(/#.*$/, "").replace(/\?.*$/, "").replace(/\/[^\/]+$/, "/");
/******/ 		__webpack_require__.p = scriptUrl + "../";
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/jsonp chunk loading */
/******/ 	(() => {
/******/ 		// no baseURI
/******/ 		
/******/ 		// object to store loaded and loading chunks
/******/ 		// undefined = chunk not loaded, null = chunk preloaded/prefetched
/******/ 		// [resolve, reject, Promise] = chunk loading, 0 = chunk loaded
/******/ 		var installedChunks = {
/******/ 			"frontend/customiser-app": 0
/******/ 		};
/******/ 		
/******/ 		__webpack_require__.f.j = (chunkId, promises) => {
/******/ 				// JSONP chunk loading for javascript
/******/ 				var installedChunkData = __webpack_require__.o(installedChunks, chunkId) ? installedChunks[chunkId] : undefined;
/******/ 				if(installedChunkData !== 0) { // 0 means "already installed".
/******/ 		
/******/ 					// a Promise means "currently loading".
/******/ 					if(installedChunkData) {
/******/ 						promises.push(installedChunkData[2]);
/******/ 					} else {
/******/ 						if(true) { // all chunks have JS
/******/ 							// setup Promise in chunk cache
/******/ 							var promise = new Promise((resolve, reject) => (installedChunkData = installedChunks[chunkId] = [resolve, reject]));
/******/ 							promises.push(installedChunkData[2] = promise);
/******/ 		
/******/ 							// start chunk loading
/******/ 							var url = __webpack_require__.p + __webpack_require__.u(chunkId);
/******/ 							// create error before stack unwound to get useful stacktrace later
/******/ 							var error = new Error();
/******/ 							var loadingEnded = (event) => {
/******/ 								if(__webpack_require__.o(installedChunks, chunkId)) {
/******/ 									installedChunkData = installedChunks[chunkId];
/******/ 									if(installedChunkData !== 0) installedChunks[chunkId] = undefined;
/******/ 									if(installedChunkData) {
/******/ 										var errorType = event && (event.type === 'load' ? 'missing' : event.type);
/******/ 										var realSrc = event && event.target && event.target.src;
/******/ 										error.message = 'Loading chunk ' + chunkId + ' failed.\n(' + errorType + ': ' + realSrc + ')';
/******/ 										error.name = 'ChunkLoadError';
/******/ 										error.type = errorType;
/******/ 										error.request = realSrc;
/******/ 										installedChunkData[1](error);
/******/ 									}
/******/ 								}
/******/ 							};
/******/ 							__webpack_require__.l(url, loadingEnded, "chunk-" + chunkId, chunkId);
/******/ 						}
/******/ 					}
/******/ 				}
/******/ 		};
/******/ 		
/******/ 		// no prefetching
/******/ 		
/******/ 		// no preloaded
/******/ 		
/******/ 		// no HMR
/******/ 		
/******/ 		// no HMR manifest
/******/ 		
/******/ 		// no on chunks loaded
/******/ 		
/******/ 		// install a JSONP callback for chunk loading
/******/ 		var webpackJsonpCallback = (parentChunkLoadingFunction, data) => {
/******/ 			var [chunkIds, moreModules, runtime] = data;
/******/ 			// add "moreModules" to the modules object,
/******/ 			// then flag all "chunkIds" as loaded and fire callback
/******/ 			var moduleId, chunkId, i = 0;
/******/ 			if(chunkIds.some((id) => (installedChunks[id] !== 0))) {
/******/ 				for(moduleId in moreModules) {
/******/ 					if(__webpack_require__.o(moreModules, moduleId)) {
/******/ 						__webpack_require__.m[moduleId] = moreModules[moduleId];
/******/ 					}
/******/ 				}
/******/ 				if(runtime) var result = runtime(__webpack_require__);
/******/ 			}
/******/ 			if(parentChunkLoadingFunction) parentChunkLoadingFunction(data);
/******/ 			for(;i < chunkIds.length; i++) {
/******/ 				chunkId = chunkIds[i];
/******/ 				if(__webpack_require__.o(installedChunks, chunkId) && installedChunks[chunkId]) {
/******/ 					installedChunks[chunkId][0]();
/******/ 				}
/******/ 				installedChunks[chunkId] = 0;
/******/ 			}
/******/ 		
/******/ 		}
/******/ 		
/******/ 		var chunkLoadingGlobal = globalThis["webpackChunkovercustomise"] = globalThis["webpackChunkovercustomise"] || [];
/******/ 		chunkLoadingGlobal.forEach(webpackJsonpCallback.bind(null, 0));
/******/ 		chunkLoadingGlobal.push = webpackJsonpCallback.bind(null, chunkLoadingGlobal.push.bind(chunkLoadingGlobal));
/******/ 	})();
/******/ 	
/************************************************************************/
var __webpack_exports__ = {};
// This entry needs to be wrapped in an IIFE because it needs to be isolated against other modules in the chunk.
(() => {
/*!****************************************!*\
  !*** ./src/frontend/customiser-app.js ***!
  \****************************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _uppy_core_css_style_min_css__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @uppy/core/css/style.min.css */ "./node_modules/@uppy/core/dist/style.min.css");
/* harmony import */ var _uppy_drag_drop_css_style_min_css__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @uppy/drag-drop/css/style.min.css */ "./node_modules/@uppy/drag-drop/dist/style.min.css");
/* harmony import */ var _customiser_app_scss__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./customiser-app.scss */ "./src/frontend/customiser-app.scss");
/* harmony import */ var _customiser_canvas_renderer__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./customiser/canvas-renderer */ "./src/frontend/customiser/canvas-renderer.js");
/* harmony import */ var _customiser_input_controls__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./customiser/input-controls */ "./src/frontend/customiser/input-controls.js");
/* harmony import */ var _customiser_cart_serialization__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ./customiser/cart-serialization */ "./src/frontend/customiser/cart-serialization.js");
/* harmony import */ var _customiser_design_variants__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ./customiser/design-variants */ "./src/frontend/customiser/design-variants.js");
/* harmony import */ var _customiser_gallery_preview__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ./customiser/gallery-preview */ "./src/frontend/customiser/gallery-preview.js");
/* harmony import */ var _customiser_clipart__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! ./customiser/clipart */ "./src/frontend/customiser/clipart.js");
/* harmony import */ var _customiser_preflight__WEBPACK_IMPORTED_MODULE_9__ = __webpack_require__(/*! ./customiser/preflight */ "./src/frontend/customiser/preflight.js");
/* harmony import */ var _customiser_spotify__WEBPACK_IMPORTED_MODULE_10__ = __webpack_require__(/*! ./customiser/spotify */ "./src/frontend/customiser/spotify.js");
/* harmony import */ var _customiser_uploads__WEBPACK_IMPORTED_MODULE_11__ = __webpack_require__(/*! ./customiser/uploads */ "./src/frontend/customiser/uploads.js");
/* harmony import */ var _customiser_checkout__WEBPACK_IMPORTED_MODULE_12__ = __webpack_require__(/*! ./customiser/checkout */ "./src/frontend/customiser/checkout.js");
/**
 * Frontend Customiser — vanilla JS, no framework dependency.
 *
 * Data: window.ocCustomiserData (wp_localize_script).
 * Canvas: Fabric.js 7.x  |  Uploads: Uppy 5.x
 *
 * @package
 */















// ── Boot ──────────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
  const data = window.ocCustomiserData;
  if (!data || !data.areas?.length) {
    return;
  }
  new OCCustomiser(data).init();
});

// ── Main class ─────────────────────────────────────────────────────────────────

class OCCustomiser {
  constructor(data) {
    this.data = data;
    this.areas = data.areas || [];
    this.fonts = data.fonts || [];
    this.layersById = {};
    this.areas.forEach(area => (area.layers || []).forEach(layer => {
      this.layersById[layer.id] = layer;
    }));
    this.designVariants = data.designVariants || [];
    this.selectedDesignVariant = data.selectedDesignVariant || this.designVariants[0]?.id || '';
    this.activeArea = 0;

    // Deep-clone mutable per-layer inputs; keys are integer layer IDs.
    this.inputs = {};
    Object.entries(data.layerInputs || {}).forEach(([k, v]) => {
      const layerId = parseInt(k, 10);
      this.inputs[layerId] = {
        ...v
      };
      this.clampLayerInputValue(layerId);
    });
    this.canvases = {}; // areaIndex → Fabric StaticCanvas
    this._redrawTimers = {};
    this._redrawGenerations = {};
    this._redrawPromises = {};
    this._canvasReadyPromise = null;
    this._canvasReadyGeneration = -1;
    this._stateAbortControllers = new Set();
    this._stateTimers = new Set();
    this._stateAnimationFrames = new Set();
    this._panelListenerController = null;
    this.fontCache = {}; // font family/weight/style/URL -> load Promise
    this.clipartSvgCache = {};
    this.galleryImg = null; // the main <img> in the product gallery
    this._focusPreviewSlide = false; // jump TVPG to preview slide after user edits
    this._hasCustomerPersonalisation = false;
    this._tvpgPreviewLocked = false;
    this._galleryPreviewGeneration = 0;
    this._galleryPreviewNodes = new Set();
    this._galleryFallbackNodeStates = new Map();
    this._tvpgLockedSwipers = new Set();
    this.productVariationStates = {};
    this._variationRequestSeq = 0;
    this._variationSwitchPending = false;
    this._variationSwitchFailed = false;
    this._activeVariationKey = '';
    this._pendingVariationKey = '';
    this._variationAbortController = null;
    this._variationSwitchPromise = null;
    this._designGeneration = 0;
    this.spotifyValidateTimers = {};
    this.spotifyValidateTokens = {};
    this.spotifyAbortControllers = {};
    this.spotifyValidationPromises = {};
    this.uploadGenerations = {};
    this.aiFilterGenerations = {};
    this.aiFilterAbortControllers = {};
    this.aiFilterErrors = {};
    this.artworkPendingCount = 0;
    this._artworkOperations = new Set();
    this.uppyInstances = new Set();
    this._thumbnailCanvases = new Set();
    this.preflightRoot = null;
    this.clipartSearchTimers = {};
    this.clipartSearchTerms = {};
    this.clipartCategoryFilters = {};
    this.spotifyModalCloseTimer = null;
    this.mobileCartPreviewDialog = null;
    this.mobileCartPreviewDismissedAt = 0;
    this.formSubmitBound = false;
    this.fontComboboxDocumentClickBound = false;
    this._customisationActive = true;
    this._submitInProgress = false;
    this._controlLocks = new Set();
    this._galleryPreviewTimer = null;
    this._variationChangeTimer = null;
    this._mobileCartPreviewResolve = null;
    this._mobileCartPreviewPromise = null;
    this._storeApiPreparationPromise = null;
    this._storeApiSubmitBound = false;
  }
  beginDesignStateListeners() {
    this._panelListenerController?.abort();
    this._panelListenerController = new AbortController();
    return this._panelListenerController.signal;
  }
  setStateTimeout(callback, delay) {
    const timer = window.setTimeout(() => {
      this._stateTimers.delete(timer);
      callback();
    }, delay);
    this._stateTimers.add(timer);
    return timer;
  }
  clearStateTimeout(timer) {
    if (timer !== null && timer !== undefined) {
      window.clearTimeout(timer);
      this._stateTimers.delete(timer);
    }
  }
  requestStateAnimationFrame(callback) {
    const frame = window.requestAnimationFrame(() => {
      this._stateAnimationFrames.delete(frame);
      callback();
    });
    this._stateAnimationFrames.add(frame);
    return frame;
  }
  createStateAbortController(timeoutMs = 10000) {
    const controller = new AbortController();
    let timedOut = false;
    let timer = null;
    this._stateAbortControllers.add(controller);
    if (timeoutMs > 0) {
      timer = this.setStateTimeout(() => {
        timedOut = true;
        controller.abort();
      }, timeoutMs);
    }
    return {
      controller,
      timedOut: () => timedOut,
      release: () => {
        if (timer !== null) {
          this.clearStateTimeout(timer);
        }
        this._stateAbortControllers.delete(controller);
      }
    };
  }
  invalidateDesignState() {
    this._designGeneration += 1;
    this.teardownDesignState();
    this._canvasReadyGeneration = -1;
    this._canvasReadyPromise = null;
    return this._designGeneration;
  }
  teardownDesignState() {
    this.restoreProductGallery?.();
    this.dismissMobileCartPreview?.();
    this.dismissSpotifyModal?.();
    this._panelListenerController?.abort();
    this._panelListenerController = null;
    Object.values(this._redrawTimers).forEach(window.clearTimeout);
    this._redrawTimers = {};
    Object.keys(this._redrawGenerations).forEach(areaIndex => {
      this._redrawGenerations[areaIndex] += 1;
    });
    this._redrawPromises = {};
    new Set([...Object.keys(this.spotifyValidateTokens), ...Object.keys(this.spotifyValidationPromises)]).forEach(layerId => this.invalidateSpotifyValidation(layerId));
    this.spotifyValidationPromises = {};
    this.spotifyAbortControllers = {};
    Object.keys(this.aiFilterGenerations).forEach(layerId => {
      this.aiFilterGenerations[layerId] += 1;
    });
    Object.values(this.aiFilterAbortControllers).forEach(controller => controller.abort());
    this.aiFilterAbortControllers = {};
    this.aiFilterErrors = {};
    Object.keys(this.uploadGenerations).forEach(layerId => {
      this.uploadGenerations[layerId] += 1;
    });
    this.uppyInstances.forEach(uppy => {
      try {
        uppy.cancelAll?.();
        uppy.destroy?.();
      } catch {
        // The instance may already have been destroyed by its own teardown.
      }
    });
    this.uppyInstances.clear();
    this.cancelArtworkOperations?.();
    this.clipartSearchTimers = {};
    this._stateTimers.forEach(window.clearTimeout);
    this._stateTimers.clear();
    this._stateAnimationFrames.forEach(window.cancelAnimationFrame);
    this._stateAnimationFrames.clear();
    this.spotifyModalCloseTimer = null;
    this._galleryPreviewTimer = null;
    this._variationChangeTimer = null;
    this._stateAbortControllers.forEach(controller => controller.abort());
    this._stateAbortControllers.clear();
    Object.values(this.canvases || {}).forEach(canvas => canvas?.dispose?.());
    this.canvases = {};
    this._thumbnailCanvases.forEach(canvas => canvas?.dispose?.());
    this._thumbnailCanvases.clear();
  }
  setControlLock(reason, locked) {
    if (locked) {
      this._controlLocks.add(reason);
    } else {
      this._controlLocks.delete(reason);
    }
    this.applyControlLocks();
  }
  applyControlLocks() {
    const locked = this._controlLocks.size > 0;
    const panel = document.getElementById('oc-customiser-panel');
    const cartForm = panel?.closest('form') || document.querySelector('form.cart, form[data-wp-on--submit*="addToCart"]');
    if (panel) {
      panel.inert = locked;
      panel.setAttribute('aria-busy', locked ? 'true' : 'false');
    }
    const controls = new Set([...(panel?.querySelectorAll('input:not([type="hidden"]), select, textarea, button') || []), ...(cartForm?.querySelectorAll('input:not([type="hidden"]), select, textarea, button') || [])]);
    controls.forEach(control => {
      if (locked) {
        if (control.dataset.ocLockDisabled === undefined) {
          control.dataset.ocLockDisabled = control.disabled ? '1' : '0';
        }
        control.disabled = true;
        control.setAttribute('aria-disabled', 'true');
        return;
      }
      if (control.dataset.ocLockDisabled === undefined) {
        return;
      }
      control.disabled = control.dataset.ocLockDisabled === '1';
      control.setAttribute('aria-disabled', control.disabled ? 'true' : 'false');
      delete control.dataset.ocLockDisabled;
    });
  }
  restHeaders(extra = {}) {
    const headers = {
      'X-WP-Nonce': this.data.uploadNonce,
      ...extra
    };
    if (this.data.requestToken) {
      headers['X-OC-Token'] = this.data.requestToken;
    }
    return headers;
  }
  async ensureRequestToken() {
    if (this.data.requestToken || !this.data.requestTokenUrl) {
      return;
    }
    const response = await fetch(this.data.requestTokenUrl, {
      credentials: 'same-origin',
      cache: 'no-store',
      headers: {
        Accept: 'application/json'
      }
    });
    const body = await response.json().catch(() => null);
    const token = typeof body?.token === 'string' ? body.token : '';
    if (!response.ok || !/^[A-Za-z0-9]{64}$/.test(token)) {
      throw new Error(body?.message || 'Security verification could not be started.');
    }
    this.data.requestToken = token;
  }
  currentVariationId() {
    const panelForm = document.getElementById('oc-customiser-panel')?.closest('form');
    return parseInt(panelForm?.querySelector('[name="variation_id"]')?.value || document.querySelector('[name="variation_id"]')?.value || '0', 10) || 0;
  }
  uploadEndpoint(uploadUrl, layerId) {
    const variationId = this.currentVariationId();
    const params = new URLSearchParams({
      layer_id: String(layerId),
      design_id: String(this.data.designId || ''),
      product_id: String(this.data.productId || ''),
      variation_id: String(variationId)
    });
    return uploadUrl + (uploadUrl.includes('?') ? '&' : '?') + params.toString();
  }

  // ── Init ───────────────────────────────────────────────────────────────────

  async init() {
    this.findGalleryImage();
    this.preflightRoot = document.getElementById('oc-preflight-messages');
    this.beginDesignStateListeners();
    try {
      await this.ensureRequestToken();
    } catch (error) {
      this._requestTokenError = error?.message || 'Security verification is unavailable.';
      this.renderPreflightMessages([this._requestTokenError], []);
    }

    // Hydrate state before listeners read values from the template controls.
    this.seedLockedLayerDefaults();
    this.seedTemplateImageDefaults();
    this.seedLayerFontDefaults();
    this.seedLinkedImageInputs();
    this.seedLinkedColourInputs();
    this.applyInputsToDOM({
      redraw: false
    });
    this.setupInputListeners();
    this.setupVariationGalleryHandoff();
    this.setupCartGalleryUnlock();
    this.setupDesignVariantOptions();
    this.setupClipartCarousels();
    this._uploadSetupPromise = this.setupUploadZones();
    this.applyInitialAiFilters();
    this.setupFormSubmit();
    this.setupStoreApiIntegration();
    this.updateHiddenField();
    this.setupDesignVariantCarousel();
    this.renderDesignVariantThumbnails();

    // Canvas init runs in background; calls redraw() when done.
    this.startCanvasInitialisation();
  }
}
Object.assign(OCCustomiser.prototype, _customiser_canvas_renderer__WEBPACK_IMPORTED_MODULE_3__["default"]);
Object.assign(OCCustomiser.prototype, _customiser_input_controls__WEBPACK_IMPORTED_MODULE_4__["default"]);
Object.assign(OCCustomiser.prototype, _customiser_cart_serialization__WEBPACK_IMPORTED_MODULE_5__["default"]);
Object.assign(OCCustomiser.prototype, _customiser_design_variants__WEBPACK_IMPORTED_MODULE_6__["default"]);
Object.assign(OCCustomiser.prototype, _customiser_gallery_preview__WEBPACK_IMPORTED_MODULE_7__["default"]);
Object.assign(OCCustomiser.prototype, _customiser_clipart__WEBPACK_IMPORTED_MODULE_8__["default"]);
Object.assign(OCCustomiser.prototype, _customiser_preflight__WEBPACK_IMPORTED_MODULE_9__["default"]);
Object.assign(OCCustomiser.prototype, _customiser_spotify__WEBPACK_IMPORTED_MODULE_10__["default"]);
Object.assign(OCCustomiser.prototype, _customiser_uploads__WEBPACK_IMPORTED_MODULE_11__["default"]);
Object.assign(OCCustomiser.prototype, _customiser_checkout__WEBPACK_IMPORTED_MODULE_12__["default"]);
})();

/******/ })()
;
