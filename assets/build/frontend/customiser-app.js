/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

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
        if (layer.type === 'mask') {
          return;
        }
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
          ['imageFilterResults', 'imageFilterAttemptCount', 'baseAttachmentId', 'baseAttachmentUrl', 'baseOriginalAttachmentUrl', 'baseArtworkFileType', 'basePreviewAttachmentId', 'baseImageMeta'].forEach(key => delete input[key]);
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
/**
 * Cart submission, mobile preview confirmation, and preview capture helpers.
 */

/* eslint-disable no-console, no-alert */

const QUALITY_WARNING_MESSAGE = 'We found quality warnings that may affect print output. Press OK to continue, or Cancel to review.';
const MAX_CUSTOMISATION_BYTES = 1024 * 1024;
const CART_PREVIEW_MAX_DIMENSION = 640;
const CART_PREVIEW_QUALITY = 0.82;
const STORE_API_MERGE_TIMEOUT_MS = 10000;
const checkoutMethods = {
  getCustomiserCartForm() {
    const panel = document.getElementById('oc-customiser-panel');
    const owningForm = panel?.closest('form');
    if (owningForm) {
      return owningForm;
    }
    const productRoot = panel?.closest('.product, [data-block-name="woocommerce/single-product"]');
    if (!productRoot) {
      return null;
    }
    const candidates = Array.from(productRoot.querySelectorAll('form.cart, form[data-wp-on--submit*="addToCart"]'));
    if (candidates.length !== 1) {
      return null;
    }
    const form = candidates[0];
    const hiddenField = panel.querySelector('#oc-customisation-data');
    if (hiddenField && !form.contains(hiddenField)) {
      if (!form.id) {
        form.id = `oc-cart-form-${this.data.productId || 'product'}`;
      }
      hiddenField.setAttribute('form', form.id);
    }
    return form;
  },
  handleVariationSubmitBlock() {
    if (this._designVariantPendingSeq) {
      window.alert('Please wait while the selected artwork option finishes loading.');
      return true;
    }
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
    if (this._submitInProgress || !this._customisationActive || this.artworkPendingCount > 0 || this.failedArtworkReplacements.size > 0 || Object.keys(this.aiFilterErrors || {}).length > 0) {
      if (this.artworkPendingCount > 0) {
        window.alert('Please wait for artwork uploads and image processing to finish.');
      } else if (this.failedArtworkReplacements.size > 0) {
        window.alert('A replacement upload failed. Retry it or choose "Use previous image" before adding this product to your cart.');
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
    const form = this.getCustomiserCartForm();
    if (!form?.matches('form.cart')) {
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
    if (this.failedArtworkReplacements.size > 0) {
      this.renderPreflightMessages(['A replacement image failed to upload. Retry it or explicitly use the previous image.'], []);
      return null;
    }
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
    const form = this.getCustomiserCartForm();
    if (!form?.matches('form[data-wp-on--submit*="addToCart"]:not(.cart)')) {
      return;
    }
    this._storeApiSubmitBound = true;
    this.setupStoreApiFetchMerger();
    form.addEventListener('submit', async event => {
      if (!this._customisationActive) {
        this.updateHiddenField();
        return;
      }
      if (form._ocSubmitReady) {
        form._ocSubmitReady = false;
        this.resetCartSubmitState(form);
        return;
      }
      event.preventDefault();
      event.stopImmediatePropagation();
      this.closeFontComboboxes(true);
      if (!form.checkValidity()) {
        form.reportValidity?.();
        return;
      }
      if (form.querySelector('[name^="quantity["]')) {
        event.preventDefault();
        event.stopImmediatePropagation();
        this.renderPreflightMessages(['Personalised grouped products require the standard add-to-cart form.'], []);
        return;
      }
      if (this._storeApiPreparationPromise) {
        return;
      }
      const submitter = event.submitter;
      const task = (async () => {
        if (!this.acquireCartSubmitGuard(form)) {
          return;
        }
        try {
          const prepared = await this.prepareCartCustomisation();
          if (!prepared) {
            return;
          }
          this.armStoreApiCustomisationMerge(prepared.payload);
          form._ocSubmitReady = true;
          this.resetCartSubmitState(form);
          if (form.requestSubmit) {
            form.requestSubmit(submitter?.isConnected ? submitter : undefined);
          } else {
            form.dispatchEvent(new Event('submit', {
              bubbles: true,
              cancelable: true
            }));
          }
        } catch (error) {
          this.failStoreApiCustomisationMerge(error?.message, false);
          console.error('[OC] Store API preparation failed:', error);
          this.restoreGalleryPreview();
          this.renderPreflightMessages([error?.message || 'The customisation preview could not be prepared. Please try again.'], []);
        } finally {
          if (!form._ocSubmitReady) {
            this.resetCartSubmitState(form);
          }
        }
      })();
      this._storeApiPreparationPromise = task;
      task.finally(() => {
        if (this._storeApiPreparationPromise === task) {
          this._storeApiPreparationPromise = null;
        }
      });
    }, true);
  },
  setupStoreApiFetchMerger() {
    if (this._storeApiFetchBound) {
      return;
    }
    this._storeApiFetchBound = true;
    const originalFetch = window.fetch;
    window.fetch = async (input, init) => {
      const pending = this._pendingStoreApiCustomisation;
      if (!pending) {
        return originalFetch.call(window, input, init);
      }
      const requestUrl = input instanceof Request ? input.url : String(input);
      const route = this.getStoreApiRequestRoute(requestUrl);
      const method = String(init?.method || (input instanceof Request ? input.method : 'GET')).toUpperCase();
      if (method !== 'POST' || !['add-item', 'batch'].includes(route)) {
        return originalFetch.call(window, input, init);
      }
      let bodyText;
      try {
        bodyText = '';
        if (init?.body !== undefined) {
          bodyText = String(init.body);
        } else if (input instanceof Request) {
          bodyText = await input.clone().text();
        }
      } catch {
        return this.rejectStoreApiCustomisationRequest();
      }
      let body;
      try {
        body = JSON.parse(bodyText);
      } catch {
        return this.rejectStoreApiCustomisationRequest();
      }
      const merged = this.mergeStoreApiCustomisationBody(route, body, pending);
      if (!merged) {
        const containsAddItem = route === 'add-item' || body?.requests?.some(request => String(request?.method || 'POST').toUpperCase() === 'POST' && this.getStoreApiRequestRoute(request?.path || '') === 'add-item');
        if (containsAddItem) {
          return this.rejectStoreApiCustomisationRequest();
        }
        return originalFetch.call(window, input, init);
      }
      if (pending.expired) {
        return this.rejectStoreApiCustomisationRequest();
      }
      this.consumeStoreApiCustomisationMerge();
      const nextInit = {
        ...(init || {}),
        body: JSON.stringify(merged)
      };
      if (input instanceof Request) {
        return originalFetch.call(window, new Request(input, nextInit));
      }
      return originalFetch.call(window, input, nextInit);
    };
  },
  getStoreApiRequestRoute(requestUrl) {
    let url;
    try {
      url = new URL(requestUrl, window.location.href);
    } catch {
      return '';
    }
    if (url.origin !== window.location.origin) {
      return '';
    }
    const route = decodeURIComponent(url.searchParams.get('rest_route') || url.pathname).replace(/\/+$/, '');
    if (!/\/wc\/store\/v\d+(?:\/|$)/.test(route)) {
      return '';
    }
    if (/\/cart\/add-item$/.test(route)) {
      return 'add-item';
    }
    return /\/batch$/.test(route) ? 'batch' : '';
  },
  storeApiBodyMatchesProduct(body, pending) {
    return body && typeof body === 'object' && pending.expectedProductIds.includes(Number(body.id || 0));
  },
  mergeStoreApiCustomisationBody(route, body, pending) {
    const mergeItem = itemBody => ({
      ...itemBody,
      extensions: {
        ...(itemBody.extensions || {}),
        overcustomise: {
          ...(itemBody.extensions?.overcustomise || {}),
          customisation: pending.payload
        }
      }
    });
    if (route === 'add-item') {
      return this.storeApiBodyMatchesProduct(body, pending) ? mergeItem(body) : null;
    }
    if (!Array.isArray(body?.requests)) {
      return null;
    }
    const matchingIndexes = body.requests.map((request, index) => ({
      request,
      index
    })).filter(({
      request
    }) => String(request?.method || 'POST').toUpperCase() === 'POST' && this.getStoreApiRequestRoute(request?.path || '') === 'add-item' && this.storeApiBodyMatchesProduct(request?.body, pending)).map(({
      index
    }) => index);
    if (matchingIndexes.length !== 1) {
      return null;
    }
    const matchedIndex = matchingIndexes[0];
    const requests = body.requests.map((request, index) => index === matchedIndex ? {
      ...request,
      body: mergeItem(request.body)
    } : request);
    return {
      ...body,
      requests
    };
  },
  armStoreApiCustomisationMerge(payload) {
    this.consumeStoreApiCustomisationMerge();
    const variationId = this.currentVariationId();
    const expectedProductIds = [Number(this.data.productId || 0), Number(variationId || 0)].filter((id, index, ids) => id > 0 && ids.indexOf(id) === index);
    const pending = {
      payload,
      expectedProductIds,
      expired: false,
      timer: null
    };
    pending.timer = this.setStateTimeout(() => {
      if (this._pendingStoreApiCustomisation !== pending) {
        return;
      }
      this.expireStoreApiCustomisationMerge();
    }, STORE_API_MERGE_TIMEOUT_MS);
    this._pendingStoreApiCustomisation = pending;
  },
  expireStoreApiCustomisationMerge(render = true) {
    const pending = this._pendingStoreApiCustomisation;
    if (!pending || pending.expired) {
      return;
    }
    if (pending.timer !== null) {
      this.clearStateTimeout(pending.timer);
    }
    pending.expired = true;
    pending.timer = null;
    if (render) {
      this.renderPreflightMessages(['WooCommerce did not receive the personalisation request. Please try adding the product again.'], []);
    }
  },
  consumeStoreApiCustomisationMerge() {
    const pending = this._pendingStoreApiCustomisation;
    if (pending?.timer !== null && pending?.timer !== undefined) {
      this.clearStateTimeout(pending.timer);
    }
    this._pendingStoreApiCustomisation = null;
  },
  failStoreApiCustomisationMerge(message, render = true) {
    if (!this._pendingStoreApiCustomisation) {
      return;
    }
    this.consumeStoreApiCustomisationMerge();
    if (render) {
      this.renderPreflightMessages([message || 'The personalisation could not be attached to the cart request. Please try again.'], []);
    }
  },
  rejectStoreApiCustomisationRequest() {
    const message = 'The personalisation could not be attached to the WooCommerce request. Please try adding the product again.';
    this.failStoreApiCustomisationMerge(message);
    return Promise.reject(new Error(message));
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
    const request = this.createStateAbortController(20000);
    try {
      const response = await fetch(this.data.savePreviewUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: this.restHeaders({
          'Content-Type': 'application/json',
          Accept: 'application/json'
        }),
        body: JSON.stringify({
          image: previewImage
        }),
        signal: request.controller.signal
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
    } catch (error) {
      if (request.timedOut()) {
        throw new Error('The customisation preview upload timed out. Please try again.');
      }
      throw error;
    } finally {
      request.release();
    }
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
    document.querySelectorAll('.oc-live-preview-slide, .oc-live-preview-thumb-slide, .oc-preview-disclaimer').forEach(slide => slide.remove());
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
  addPreviewDisclaimer(img) {
    if (!img || img.closest('.product-thumbnails, .tvpg-thumb-slider')) {
      return;
    }
    const host = img.closest('.woocommerce-product-gallery__image, .product-gallery-slider .slide, .swiper-slide') || img.parentElement;
    if (!host || host.querySelector(':scope > .oc-preview-disclaimer')) {
      return;
    }
    const badge = document.createElement('span');
    const button = document.createElement('button');
    const hint = document.createElement('span');
    badge.className = 'oc-preview-disclaimer';
    button.type = 'button';
    button.className = 'oc-preview-disclaimer-toggle';
    button.textContent = 'i';
    button.setAttribute('aria-label', `Preview information: ${PREVIEW_DISCLAIMER}`);
    button.addEventListener('click', event => {
      event.preventDefault();
      event.stopPropagation();
    });
    hint.className = 'oc-preview-disclaimer-text';
    hint.setAttribute('role', 'tooltip');
    hint.textContent = PREVIEW_DISCLAIMER;
    badge.append(button, hint);
    host.appendChild(badge);
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
    this.addPreviewDisclaimer(img);
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
    this.captureLinkGroupCarry();
    if (this.linkGroupCarry.size) {
      this.requestPreviewFocus();
    }
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
  async fetchProductVariationState(key, requestSeq, designId = 0) {
    const designUrl = this.data.productDesignUrl || `${window.location.origin}/wp-json/overcustomise/v1/product-design/${this.data.productId || 0}`;
    const url = new URL(designUrl, window.location.origin);
    url.searchParams.set('variant_id', key);
    if (designId) {
      url.searchParams.set('design_id', String(designId));
    }
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
      const variationId = parseInt(variation?.variation_id || getSelectedVariationId(), 10) || 0;
      const variationKey = String(variationId);
      Promise.resolve(this.switchProductVariation(variationId)).then(switched => {
        if (!switched) {
          return;
        }
        const refocusPreview = () => {
          if (!this._hasCustomerPersonalisation || !this._customisationActive || this._activeVariationKey !== variationKey) {
            return;
          }
          const canvas = this.canvases[this.activeArea];
          if (!canvas || canvas._ocMissingMockup) {
            return;
          }
          this.requestPreviewFocus();
          this.pushToGallery(canvas);
        };
        refocusPreview();
        this.requestStateAnimationFrame(refocusPreview);
        this.setStateTimeout(refocusPreview, 250);
      });
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
    this.cancelPendingDesignVariantRequest();
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
          const defaultDesignId = Number(state.designId || state.design_id);
          const allowedVariant = state.designVariants?.find(variant => Number(variant.designId) === initialSnapshot.designId) || (defaultDesignId === initialSnapshot.designId ? {
            id: state.selectedDesignVariant || `design-${defaultDesignId}`,
            designId: defaultDesignId
          } : null);
          if (allowedVariant) {
            let initialVariantState = state.designVariantStates?.[allowedVariant.id];
            if (!initialVariantState?.panelHtml) {
              try {
                const restoredState = await this.fetchProductVariationState(key, requestSeq, initialSnapshot.designId);
                initialVariantState = restoredState.designVariantStates?.[allowedVariant.id];
              } catch (error) {
                console.warn('[OC] Initial design restore failed; using variation default:', error);
              }
            }
            if (initialVariantState?.panelHtml) {
              state.designVariantStates ||= {};
              state.designVariantStates[allowedVariant.id] = initialVariantState;
              state.selectedDesignVariant = allowedVariant.id;
              initialVariantState.layerInputs = this.cloneLayerInputs(initialSnapshot.layerInputs);
            }
          }
        }
        if (requestSeq !== this._variationRequestSeq) {
          return false;
        }
        this.productVariationStates[key] = state;
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
        const applied = await this.applyDesignState(nextState, selectedVariant, false, false);
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
      if (requestSeq === this._variationRequestSeq && switched && this._customisationActive) {
        this._variationSwitchFailed = false;
        this._initialAiFilterPromise = this.applyInitialAiFilters();
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

const LINKED_IMAGE_INPUT_KEYS = ['attachmentId', 'attachmentUrl', 'sourceAttachmentId', 'sourceAttachmentUrl', 'originalAttachmentUrl', 'sourceOriginalAttachmentUrl', 'artworkFileType', 'sourceArtworkFileType', 'previewAttachmentId', 'sourcePreviewAttachmentId', 'imageMeta', 'sourceImageMeta', 'imageFilterId', 'imageCrop', 'customerUploaded', 'artworkContextLayerId', 'baseAttachmentId', 'baseAttachmentUrl', 'baseOriginalAttachmentUrl', 'baseArtworkFileType', 'basePreviewAttachmentId', 'baseImageMeta', 'imageFilterResults', 'imageFilterAttemptCount'];
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
    dialog.classList.remove('oc-dialog-fallback');
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
    document.querySelectorAll('.oc-area-controls').forEach(panel => {
      panel.addEventListener('focusin', () => {
        this.focusPreviewArea(parseInt(panel.dataset.areaIndex, 10));
      }, {
        signal: stateSignal
      });
    });

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
        this.recordLinkGroupCarry(lid, {
          value: cleaned
        });
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
        if (!dialog || dialog.open) {
          return;
        }
        if (typeof dialog.showModal === 'function') {
          try {
            dialog.showModal();
          } catch {
            dialog.setAttribute('open', '');
            dialog.classList.add('oc-dialog-fallback');
          }
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
        this.inputs[lid].imageFilterId = filterId;
        this.recordImageLinkGroupCarry(lid, {
          pendingFilter: true
        });
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
    document.querySelectorAll('[data-oc-image-filter-results]').forEach(resultsEl => {
      const lid = parseInt(resultsEl.dataset.ocImageFilterResults, 10);
      resultsEl.addEventListener('click', async event => {
        const choice = event.target.closest('[data-oc-filter-result-choice]');
        if (!choice || !resultsEl.contains(choice)) {
          return;
        }
        await this.selectAiFilterResult(lid, parseInt(choice.dataset.ocFilterResultChoice, 10) || 0);
      }, {
        signal: stateSignal
      });
      resultsEl.querySelector('[data-oc-image-filter-retry]')?.addEventListener('click', async event => {
        event.currentTarget.disabled = true;
        try {
          await this.applyAiImageFilter(lid, Number(this.inputs[lid]?.imageFilterId || 0), null, true);
        } finally {
          this.renderAiFilterResults(lid);
        }
      }, {
        signal: stateSignal
      });
      this.renderAiFilterResults(lid);
    });

    // Image filters remain source effects; this control only changes placement.
    document.querySelectorAll('[data-oc-layer-image-crop]').forEach(el => {
      const lid = parseInt(el.dataset.ocLayerImageCrop, 10);
      if (!this.inputs[lid]) {
        this.inputs[lid] = {};
      }
      this.updateImageCropControl(lid);
      el.addEventListener('input', () => {
        const imageCrop = Math.max(0, Math.min(100, parseInt(el.value, 10) || 0));
        this.inputs[lid].imageCrop = imageCrop;
        this.syncLinkedLayerInput(lid, ['imageCrop'], {
          redraw: false
        });
        this.updateImageCropControl(lid);
        this.requestPreviewFocus();
        const layerIds = this.linkedLayerMembers(lid);
        const updatedLayerIds = this.updateRenderedImageCrop(layerIds, imageCrop);
        layerIds.filter(layerId => !updatedLayerIds.has(layerId)).forEach(layerId => this.scheduleRedraw(this.areaIndexForLayer(layerId)));
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
    this.areas.forEach((area, areaIndex) => {
      const panel = document.querySelector(`.oc-area-controls[data-area-index="${areaIndex}"]`);
      if (panel) {
        panel.setAttribute('role', 'group');
        panel.setAttribute('aria-label', `${area.label || `Print area ${areaIndex + 1}`} controls`);
      }
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
  linkGroupCarryKey(layer) {
    const group = String(layer?.settings?.link_group || '').trim();
    if (!group || !['text', 'textarea', 'image', 'clipmask'].includes(layer?.type) || !this.isLinkedLayerEligible(layer)) {
      return '';
    }
    return `${layer.type}|${group}`;
  },
  recordLinkGroupCarry(layerId, payload) {
    const layer = this.getLayerById(layerId);
    const key = this.linkGroupCarryKey(layer);
    if (key) {
      this.linkGroupCarry.set(key, {
        kind: layer.type,
        payload: JSON.parse(JSON.stringify(payload))
      });
    }
  },
  recordImageLinkGroupCarry(layerId, {
    pendingFilter = false
  } = {}) {
    const input = this.inputs[layerId];
    if (!input?.customerUploaded || !input.sourceAttachmentId) {
      return;
    }
    const payload = {};
    LINKED_IMAGE_INPUT_KEYS.forEach(key => {
      if (key !== 'imageCrop' && input[key] !== undefined) {
        payload[key] = input[key];
      }
    });
    if (pendingFilter) {
      payload.attachmentId = payload.sourceAttachmentId;
      payload.attachmentUrl = payload.sourceAttachmentUrl;
      payload.originalAttachmentUrl = payload.sourceOriginalAttachmentUrl || payload.sourceAttachmentUrl;
      payload.artworkFileType = payload.sourceArtworkFileType;
      payload.previewAttachmentId = payload.sourcePreviewAttachmentId || 0;
      payload.imageMeta = payload.sourceImageMeta || null;
    }
    payload.context = {
      variationId: this.currentVariationId(),
      designId: Number(this.data.designId || 0),
      layerId: Number(this.canonicalLinkedLayerId(layerId))
    };
    this.artworkContextAuthorisations.add(this.artworkContextAuthorisationKey(payload, payload.context));
    this.recordLinkGroupCarry(layerId, payload);
  },
  captureLinkGroupCarry() {
    this.areas.forEach(area => {
      (area.layers || []).forEach(layer => {
        const key = this.linkGroupCarryKey(layer);
        if (!key) {
          return;
        }
        if (['text', 'textarea'].includes(layer.type)) {
          const value = this.inputs[layer.id]?.value;
          const defaultValue = this.data.layerInputs?.[layer.id]?.value;
          if (this.linkGroupCarry.has(key) || String(value || '') !== String(defaultValue || '')) {
            this.recordLinkGroupCarry(layer.id, {
              value: value || ''
            });
          }
          return;
        }
        this.recordImageLinkGroupCarry(layer.id);
      });
    });
  },
  artworkContextAuthorisationKey(payload, context) {
    return [Number(this.data.productId || 0), Number(context?.variationId || 0), Number(context?.designId || 0), Number(context?.layerId || 0), Number(payload?.sourceAttachmentId || 0), Number(payload?.attachmentId || 0)].join('|');
  },
  carriedImageForLayer(layer, carried) {
    const payload = JSON.parse(JSON.stringify(carried || {}));
    delete payload.context;
    payload.imageCrop = 0;
    const filterId = Number(payload.imageFilterId || 0);
    const allowedFilters = Array.isArray(layer.settings?.image_filter_ids) ? layer.settings.image_filter_ids.map(Number) : [];
    const canChangeFilter = layer.settings?.allow_image_filter_change !== false;
    const filterAllowed = layer.type === 'image' && allowedFilters.includes(filterId) && (canChangeFilter || Number(layer.settings?.default_image_filter_id || 0) === filterId);
    if (filterId && !filterAllowed) {
      payload.imageFilterId = 0;
      payload.attachmentId = payload.sourceAttachmentId;
      payload.attachmentUrl = payload.sourceAttachmentUrl;
      payload.originalAttachmentUrl = payload.sourceOriginalAttachmentUrl || payload.sourceAttachmentUrl;
      payload.artworkFileType = payload.sourceArtworkFileType;
      payload.previewAttachmentId = payload.sourcePreviewAttachmentId || 0;
      payload.imageMeta = payload.sourceImageMeta || null;
    }
    return payload;
  },
  async authoriseCarriedImage(layer, payload, context, signal) {
    if (!this.data.authoriseArtworkUrl) {
      return false;
    }
    const target = {
      variationId: this.currentVariationId(),
      designId: Number(this.data.designId || 0),
      layerId: Number(layer.id)
    };
    const authorisationKey = this.artworkContextAuthorisationKey(payload, target);
    if (this.artworkContextAuthorisations.has(authorisationKey)) {
      return true;
    }
    if (Number(context?.variationId || 0) === target.variationId && Number(context?.designId || 0) === target.designId && Number(context?.layerId || 0) === target.layerId) {
      this.artworkContextAuthorisations.add(authorisationKey);
      return true;
    }
    await this.ensureRequestToken();
    const sourceId = Number(payload.sourceAttachmentId || 0);
    const derivativeId = Number(payload.attachmentId || 0);
    const request = this.createStateAbortController(12000);
    const abortRequest = () => request.controller.abort();
    try {
      if (signal?.aborted) {
        abortRequest();
      } else {
        signal?.addEventListener('abort', abortRequest, {
          once: true
        });
      }
      const response = await fetch(this.data.authoriseArtworkUrl, {
        method: 'POST',
        credentials: 'same-origin',
        signal: request.controller.signal,
        headers: this.restHeaders({
          'Content-Type': 'application/json',
          Accept: 'application/json'
        }),
        body: JSON.stringify({
          source_attachment_id: sourceId,
          derivative_attachment_id: derivativeId !== sourceId ? derivativeId : 0,
          product_id: Number(this.data.productId || 0),
          variation_id: target.variationId,
          design_id: target.designId,
          layer_id: target.layerId
        })
      });
      if (response.ok) {
        this.artworkContextAuthorisations.add(authorisationKey);
      }
      return response.ok;
    } catch (error) {
      if (request.timedOut()) {
        throw new Error('Shared image authorisation timed out.');
      }
      throw error;
    } finally {
      signal?.removeEventListener('abort', abortRequest);
      request.release();
    }
  },
  async hydrateLinkGroupCarry() {
    if (!this.linkGroupCarry?.size) {
      return;
    }
    const designGeneration = this._designGeneration;
    const stateSignal = this._panelListenerController?.signal;
    const hydrated = new Set();
    const imageTasks = [];
    for (const area of this.areas) {
      for (const layer of area.layers || []) {
        if (designGeneration !== this._designGeneration || stateSignal?.aborted) {
          return;
        }
        const key = this.linkGroupCarryKey(layer);
        const carried = key ? this.linkGroupCarry.get(key) : null;
        if (!carried || hydrated.has(key)) {
          continue;
        }
        hydrated.add(key);
        if (['text', 'textarea'].includes(layer.type)) {
          if (carried.payload?.value !== undefined) {
            this.inputs[layer.id] = {
              ...(this.inputs[layer.id] || {}),
              value: this.normaliseLayerTextValue(layer.id, carried.payload.value)
            };
            this.syncLinkedLayerInput(layer.id, ['value'], {
              redraw: false
            });
          }
          continue;
        }
        imageTasks.push({
          layer,
          payload: this.carriedImageForLayer(layer, carried.payload),
          context: carried.payload?.context
        });
      }
    }
    if (!imageTasks.length || stateSignal?.aborted) {
      return;
    }
    try {
      await this.ensureRequestToken();
    } catch (error) {
      console.warn('[OC] Shared images could not be prepared:', error);
      return;
    }
    for (let offset = 0; offset < imageTasks.length; offset += 4) {
      const batch = imageTasks.slice(offset, offset + 4);
      await Promise.all(batch.map(async ({
        layer,
        payload,
        context
      }) => {
        try {
          if (await this.authoriseCarriedImage(layer, payload, context, stateSignal)) {
            if (designGeneration !== this._designGeneration || stateSignal?.aborted) {
              return;
            }
            this.inputs[layer.id] = {
              ...(this.inputs[layer.id] || {}),
              ...payload
            };
          }
        } catch (error) {
          console.warn('[OC] Shared image could not be prepared:', error);
        }
      }));
      if (designGeneration !== this._designGeneration || stateSignal?.aborted) {
        return;
      }
    }
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
  syncLinkedLayerInput(sourceLayerId, keys, {
    redraw = true
  } = {}) {
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
    if (redraw) {
      targetAreaIndexes.forEach(areaIndex => this.scheduleRedraw(areaIndex));
    }
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
    if (keys.includes('imageCrop') || keys.includes('customerUploaded')) {
      this.updateImageCropControl(layerId);
    }
  },
  updateImageCropControl(layerId) {
    const input = this.inputs[layerId] || {};
    const control = document.querySelector(`[data-oc-image-crop-control="${layerId}"]`);
    const range = control?.querySelector('[data-oc-layer-image-crop]');
    if (!control || !range) {
      return;
    }
    const visible = Boolean(input.customerUploaded && this.isProductionImageInput(input));
    const amount = Math.max(0, Math.min(100, Number(input.imageCrop) || 0));
    control.hidden = !visible;
    range.disabled = !visible || this._controlLocks.size > 0;
    range.value = String(amount);
    let valueText = `${amount}% crop`;
    if (amount === 0) {
      valueText = 'Fit image';
    } else if (amount === 100) {
      valueText = 'Crop to subject';
    }
    range.setAttribute('aria-valuetext', valueText);
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
      this.updateImageCropControl(layerId);
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
      el.classList.toggle('oc-active-area-controls', Number(el.dataset.areaIndex) === this.activeArea);
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
              await this.validateSpotifyLayer(canonicalId, canonicalInput.value || value, this.getLayerInputEl(canonicalLayer) || fieldEl, true);
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

const SPOTIFY_LINK_SYNC_KEYS = ['value', 'spotifyStatus', 'spotifyUri', 'spotifyValidationProof', 'spotifyValidationExpires'];
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
    this.inputs[layerId].spotifyValidationProof = '';
    this.inputs[layerId].spotifyValidationExpires = 0;
    this.syncLinkedLayerInput(layerId, SPOTIFY_LINK_SYNC_KEYS);
    this.setSpotifyError(layerId, '', inputEl);
    this.scheduleRedraw(this.areaIndexForLayer(layerId));
    this.updateHiddenField();
  },
  setSpotifyValidationResult(layerId, status, uri, message, inputEl = null, proof = '', expires = 0) {
    this.inputs[layerId].spotifyStatus = status;
    this.inputs[layerId].spotifyUri = uri;
    this.inputs[layerId].spotifyValidationProof = proof;
    this.inputs[layerId].spotifyValidationExpires = expires;
    this.syncLinkedLayerInput(layerId, SPOTIFY_LINK_SYNC_KEYS);
    this.setSpotifyError(layerId, message, inputEl);
    this.scheduleRedraw(this.areaIndexForLayer(layerId));
    this.updateHiddenField();
  },
  validateSpotifyLayer(layerId, rawValue, inputEl = null, force = false) {
    const value = String(rawValue || '').trim();
    if (!this.inputs[layerId]) {
      this.inputs[layerId] = {};
    }
    this.clearStateTimeout(this.spotifyValidateTimers[layerId]);
    delete this.spotifyValidateTimers[layerId];
    const existing = this.spotifyValidationPromises[layerId];
    if (!force && existing?.value === value) {
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
          this.setSpotifyValidationResult(layerId, 'ok', canonicalUri, '', inputEl, String(json.validationProof || ''), Number(json.validationExpires || 0));
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


const SERVER_UPLOAD_FORMATS = ['jpg', 'jpeg', 'png', 'svg', 'pdf', 'eps', 'webp', 'heic', 'heif'];
const normaliseUploadFormats = formats => {
  return [...new Set(formats.map(format => String(format).toLowerCase().replace(/^\./, '')).filter(extension => SERVER_UPLOAD_FORMATS.includes(extension)))];
};
const uploadMethods = {
  clearFailedArtworkReplacements(layerId) {
    const members = new Set([layerId, ...(this.linkedLayerMembers?.(layerId) || [])]);
    members.forEach(memberId => {
      this.failedArtworkReplacements.delete(memberId);
      const resolutionError = document.querySelector(`.oc-resolution-warning.oc-res-error[data-oc-resolution-warning="${memberId}"]`);
      if (resolutionError) {
        resolutionError.className = 'oc-resolution-warning';
        resolutionError.style.display = 'none';
      }
      document.querySelectorAll(`[data-oc-upload-zone="${memberId}"]`).forEach(zone => {
        this.setUploadZoneState(zone, this.isProductionImageInput(this.inputs[memberId]) ? 'uploaded' : '');
        this.showUploadError(zone, '');
      });
    });
  },
  markArtworkReplacementFailed(layerId, zoneEl, message) {
    const hasPreviousArtwork = this.isProductionImageInput(this.inputs[layerId]);
    this.setUploadZoneState(zoneEl, hasPreviousArtwork ? 'uploaded-error' : 'error');
    this.showUploadError(zoneEl, message);
    if (!hasPreviousArtwork) {
      return;
    }
    this.failedArtworkReplacements.add(layerId);
    const errorEl = zoneEl.closest('.oc-artwork-wrap')?.querySelector('.oc-artwork-error');
    if (!errorEl) {
      return;
    }
    const retain = document.createElement('button');
    retain.type = 'button';
    retain.className = 'oc-upload-retry';
    retain.textContent = 'Use previous image';
    retain.addEventListener('click', () => {
      this.clearFailedArtworkReplacements(layerId);
    }, {
      signal: this._panelListenerController?.signal
    });
    errorEl.append(document.createTextNode(' '), retain);
  },
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
      modules = await Promise.all([__webpack_require__.e(/*! import() | upload-tools */ "upload-tools").then(__webpack_require__.bind(__webpack_require__, /*! @uppy/core */ "./node_modules/@uppy/core/lib/index.js")), __webpack_require__.e(/*! import() | upload-tools */ "upload-tools").then(__webpack_require__.bind(__webpack_require__, /*! @uppy/drag-drop */ "./node_modules/@uppy/drag-drop/lib/index.js")), __webpack_require__.e(/*! import() | upload-tools */ "upload-tools").then(__webpack_require__.bind(__webpack_require__, /*! @uppy/xhr-upload */ "./node_modules/@uppy/xhr-upload/lib/index.js")), __webpack_require__.e(/*! import() | upload-tools */ "upload-tools").then(__webpack_require__.bind(__webpack_require__, /*! @uppy/core/css/style.min.css */ "./node_modules/@uppy/core/dist/style.min.css")), __webpack_require__.e(/*! import() | upload-tools */ "upload-tools").then(__webpack_require__.bind(__webpack_require__, /*! @uppy/drag-drop/css/style.min.css */ "./node_modules/@uppy/drag-drop/dist/style.min.css"))]);
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
      this.showUploadError(zoneEl, '');
      this.setUploadZoneState(zoneEl, this.isProductionImageInput(this.inputs[parseInt(zoneEl.dataset.ocUploadZone, 10)]) ? 'uploaded' : '');
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
      const normalisedGlobalFormats = normaliseUploadFormats(globalFormats);
      const effective = layerFormats.length ? normaliseUploadFormats(layerFormats).filter(ext => normalisedGlobalFormats.includes(ext)) : normalisedGlobalFormats;
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
        uppy.getPlugin('XHRUpload')?.setOptions({
          endpoint: this.uploadEndpoint(uploadUrl, lid)
        });
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
        headers: this.restHeaders(),
        shouldRetry: xhr => xhr.status === 0 || xhr.status === 408 || xhr.status === 429 || xhr.status >= 500
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
          this.markArtworkReplacementFailed(lid, zoneEl, 'Upload succeeded but server returned no data.');
          return;
        }
        const attachmentId = Number(res.body.attachment_id || 0);
        const attachmentUrl = String(res.body.preview_url || '');
        if (!attachmentId || !attachmentUrl) {
          this.markArtworkReplacementFailed(lid, zoneEl, 'Server did not return usable artwork data.');
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
          sourceImageMeta: null,
          imageCrop: 0,
          customerUploaded: true,
          artworkContextLayerId: lid,
          baseAttachmentId: attachmentId,
          baseAttachmentUrl: attachmentUrl,
          baseOriginalAttachmentUrl: String(res.body.original_url || attachmentUrl),
          baseArtworkFileType: artworkFileType,
          basePreviewAttachmentId: Number(res.body.preview_attachment_id || 0),
          baseImageMeta: null,
          imageFilterResults: {},
          imageFilterAttemptCount: {}
        };
        const meta = await this.getTrackedImageMeta(attachmentUrl, lid);
        if (generation !== this.uploadGenerations[lid]) {
          return;
        }
        candidate.imageMeta = meta;
        candidate.sourceImageMeta = meta;
        candidate.baseImageMeta = meta;
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
          this.markArtworkReplacementFailed(lid, zoneEl, 'Image resolution too low. Please upload a higher resolution image.');
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
        this.recordImageLinkGroupCarry(lid);
        this.clearFailedArtworkReplacements(lid);
        const filterApplied = await this.applyAiImageFilter(lid, candidate.imageFilterId || 0, zoneEl);
        if (generation !== this.uploadGenerations[lid]) {
          return;
        }
        this.setUploadZoneState(zoneEl, filterApplied ? 'uploaded' : 'uploaded-error');
        this.updateImageCropControl(lid);
        this.syncLinkedImageInput(lid);
        this.recordImageLinkGroupCarry(lid);
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
        this.setUploadProgress(zoneEl, 0, '');
        if (this.isProductionImageInput(this.inputs[lid])) {
          this.markArtworkReplacementFailed(lid, zoneEl, msg);
        } else {
          this.setUploadZoneState(zoneEl, 'error');
          this.showUploadError(zoneEl, msg);
        }
      });
      uppy.on('restriction-failed', (file, error) => {
        finishFileTransfer(file?.id);
        this.setUploadProgress(zoneEl, 0, '');
        const message = error?.message || 'File not allowed.';
        if (this.isProductionImageInput(this.inputs[lid])) {
          this.markArtworkReplacementFailed(lid, zoneEl, message);
        } else {
          this.setUploadZoneState(zoneEl, 'error');
          this.showUploadError(zoneEl, message);
        }
      });
    });
  },
  imageFilterRequestBody(layerId, filterId, sourceId) {
    return {
      source_attachment_id: Number(sourceId),
      filter_id: Number(filterId),
      layer_id: Number(layerId),
      design_id: Number(this.data.designId || 0),
      product_id: Number(this.data.productId || 0),
      variation_id: this.currentVariationId()
    };
  },
  rememberBaseArtwork(input) {
    if (Number(input.baseAttachmentId || 0) > 0) {
      return;
    }
    input.baseAttachmentId = Number(input.sourceAttachmentId || input.attachmentId || 0);
    input.baseAttachmentUrl = input.sourceAttachmentUrl || input.attachmentUrl || '';
    input.baseOriginalAttachmentUrl = input.sourceOriginalAttachmentUrl || input.originalAttachmentUrl || input.baseAttachmentUrl;
    input.baseArtworkFileType = input.sourceArtworkFileType || input.artworkFileType || '';
    input.basePreviewAttachmentId = Number(input.sourcePreviewAttachmentId || input.previewAttachmentId || 0);
    input.baseImageMeta = input.sourceImageMeta || input.imageMeta || null;
  },
  async loadAiFilterResults(layerId, filterId, sourceId, signal) {
    const response = await fetch(this.data.applyImageFilterUrl, {
      method: 'POST',
      signal,
      headers: this.restHeaders({
        'Content-Type': 'application/json'
      }),
      body: JSON.stringify({
        ...this.imageFilterRequestBody(layerId, filterId, sourceId),
        list_only: true
      })
    });
    const json = await response.json().catch(() => null);
    if (!response.ok || !Array.isArray(json?.results)) {
      throw new Error(json?.message || 'Previous image effect results could not be loaded.');
    }
    return json;
  },
  async selectAiFilterResult(layerId, attachmentId) {
    const input = this.inputs[layerId];
    if (!input) {
      return false;
    }
    this.rememberBaseArtwork(input);
    if (!attachmentId) {
      this.restoreSourceArtwork(input, Number(input.baseAttachmentId || 0), input.baseAttachmentUrl || '');
    } else {
      const results = input.imageFilterResults?.[input.imageFilterId] || [];
      const result = results.find(item => Number(item.attachment_id) === Number(attachmentId));
      if (!result) {
        return false;
      }
      input.attachmentId = Number(result.attachment_id);
      input.attachmentUrl = String(result.preview_url || '');
      input.originalAttachmentUrl = String(result.original_url || result.preview_url || '');
      input.artworkFileType = String(result.file_type || input.baseArtworkFileType || '').toLowerCase();
      input.previewAttachmentId = 0;
      input.sourceAttachmentId = Number(result.source_attachment_id || input.baseAttachmentId || 0);
      input.sourceAttachmentUrl = String(result.source_preview_url || input.baseAttachmentUrl || '');
      input.sourceOriginalAttachmentUrl = input.sourceAttachmentUrl;
      input.sourceArtworkFileType = input.baseArtworkFileType;
      input.sourcePreviewAttachmentId = 0;
      input.imageMeta = await this.getTrackedImageMeta(input.attachmentUrl, layerId);
    }
    delete this.aiFilterErrors[layerId];
    this.syncLinkedImageInput(layerId);
    this.recordImageLinkGroupCarry(layerId);
    this.renderAiFilterResults(layerId);
    this.requestPreviewFocus();
    this.scheduleRedraw(this.areaIndexForLayer(layerId));
    this.updateHiddenField();
    return true;
  },
  renderAiFilterResults(layerId) {
    const wrap = document.querySelector(`[data-oc-image-filter-results="${layerId}"]`);
    const input = this.inputs[layerId];
    const filterId = Number(input?.imageFilterId || 0);
    const filter = (this.data?.imageFilters || []).find(item => Number(item.id) === filterId);
    if (!wrap || !input || !filter?.isAi || !input.baseAttachmentUrl) {
      if (wrap) {
        wrap.hidden = true;
      }
      return;
    }
    const results = input.imageFilterResults?.[filterId] || [];
    const attempts = Math.max(results.length, Number(input.imageFilterAttemptCount?.[filterId] || 0));
    const remaining = Math.max(0, 3 - attempts);
    const grid = wrap.querySelector('[data-oc-image-filter-result-grid]');
    const remainingEl = wrap.querySelector('[data-oc-image-filter-remaining]');
    const retry = wrap.querySelector('[data-oc-image-filter-retry]');
    const choices = [{
      attachment_id: 0,
      preview_url: input.baseAttachmentUrl,
      label: 'Original'
    }, ...results.map(result => ({
      ...result,
      label: `Result ${Number(result.attempt || 0)}`
    }))];
    grid?.replaceChildren(...choices.map(result => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'oc-image-filter-result';
      button.dataset.ocFilterResultChoice = String(result.attachment_id);
      button.setAttribute('aria-pressed', Number(input.attachmentId || 0) === Number(result.attachment_id) || !result.attachment_id && Number(input.attachmentId || 0) === Number(input.baseAttachmentId || 0) ? 'true' : 'false');
      const image = document.createElement('img');
      image.src = result.preview_url;
      image.alt = '';
      const label = document.createElement('span');
      label.textContent = result.label;
      button.append(image, label);
      return button;
    }));
    if (remainingEl) {
      remainingEl.textContent = remaining ? `${remaining} ${remaining === 1 ? 'try' : 'tries'} remaining` : 'No tries remaining';
    }
    if (retry) {
      retry.disabled = remaining <= 0 || Boolean(this.aiFilterAbortControllers[layerId]);
    }
    wrap.hidden = false;
  },
  async applyAiImageFilter(layerId, filterId, zoneEl = null, forceRetry = false) {
    const input = this.inputs[layerId];
    if (!input) {
      return false;
    }
    filterId = Number(filterId || 0);
    this.cancelAiFilterForLayer(layerId);
    const generation = this.aiFilterGenerations[layerId];
    const designGeneration = this._designGeneration;
    input.imageFilterId = filterId;
    this.rememberBaseArtwork(input);
    const isCurrent = () => generation === this.aiFilterGenerations[layerId] && designGeneration === this._designGeneration && this.inputs[layerId] === input && Number(input.imageFilterId || 0) === filterId && this._customisationActive;
    if (!filterId) {
      await this.selectAiFilterResult(layerId, 0);
      delete this.aiFilterErrors[layerId];
      this.syncLinkedImageInput(layerId);
      this.recordImageLinkGroupCarry(layerId);
      this.scheduleRedraw(this.areaIndexForLayer(layerId));
      this.updateHiddenField();
      return true;
    }
    const filter = (this.data?.imageFilters || []).find(item => Number(item.id) === Number(filterId));
    if (!filter?.isAi) {
      await this.selectAiFilterResult(layerId, 0);
      delete this.aiFilterErrors[layerId];
      this.syncLinkedImageInput(layerId);
      this.recordImageLinkGroupCarry(layerId);
      this.scheduleRedraw(this.areaIndexForLayer(layerId));
      this.updateHiddenField();
      return true;
    }
    const sourceId = Number(input.baseAttachmentId || input.sourceAttachmentId || input.attachmentId || 0);
    const sourceUrl = input.baseAttachmentUrl || input.sourceAttachmentUrl || input.attachmentUrl || '';
    if (!sourceId || !sourceUrl || !this.data?.applyImageFilterUrl) {
      this.aiFilterErrors[layerId] = 'Upload an image before applying this filter.';
      this.syncLinkedImageInput(layerId);
      this.updateHiddenField();
      return false;
    }

    // Keep the source visible while processing; submission remains blocked until
    // the generated attachment replaces it or an error is resolved.
    this.updateHiddenField();
    const request = this.createStateAbortController(150000);
    const controller = request.controller;
    this.aiFilterAbortControllers[layerId] = controller;
    // The handle is deliberately consumed in finally across every return path.
    // eslint-disable-next-line @wordpress/no-unused-vars-before-return
    const operation = this.beginArtworkOperation('filter', layerId);
    delete this.aiFilterErrors[layerId];
    const targetZone = zoneEl || document.querySelector(`[data-oc-upload-zone="${layerId}"]`);
    if (targetZone) {
      this.setUploadProgress(targetZone, null, 'Applying image effect... This can take up to two minutes.');
      this.showUploadError(targetZone, '');
    }
    try {
      const existing = await this.loadAiFilterResults(layerId, filterId, sourceId, controller.signal);
      if (!isCurrent()) {
        return false;
      }
      input.imageFilterResults = input.imageFilterResults || {};
      input.imageFilterAttemptCount = input.imageFilterAttemptCount || {};
      input.imageFilterResults[filterId] = existing.results;
      input.imageFilterAttemptCount[filterId] = 3 - Number(existing.retries_remaining || 0);
      this.renderAiFilterResults(layerId);
      if (!forceRetry && existing.results.length) {
        return await this.selectAiFilterResult(layerId, Number(existing.results.at(-1).attachment_id));
      }
      const response = await fetch(this.data.applyImageFilterUrl, {
        method: 'POST',
        signal: controller.signal,
        headers: this.restHeaders({
          'Content-Type': 'application/json'
        }),
        body: JSON.stringify(this.imageFilterRequestBody(layerId, filterId, sourceId))
      });
      const json = await response.json().catch(() => null);
      if (!response.ok || !json?.attachment_id || !json?.preview_url) {
        throw new Error(json?.message || 'The image effect could not be applied.');
      }
      if (!isCurrent()) {
        return false;
      }
      const result = {
        ...json,
        source_attachment_id: Number(json.source_attachment_id || sourceId),
        source_preview_url: sourceUrl
      };
      input.imageFilterResults[filterId] = [...existing.results.filter(item => Number(item.attachment_id) !== Number(result.attachment_id)), result];
      input.imageFilterAttemptCount[filterId] = Number(json.attempt || 0);
      return await this.selectAiFilterResult(layerId, Number(result.attachment_id));
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
      this.renderAiFilterResults(layerId);
    }
  },
  restoreSourceArtwork(input, sourceId, sourceUrl) {
    if (!sourceId || !sourceUrl) {
      return;
    }
    input.attachmentId = sourceId;
    input.attachmentUrl = sourceUrl;
    input.originalAttachmentUrl = input.baseOriginalAttachmentUrl || sourceUrl;
    input.artworkFileType = input.baseArtworkFileType || input.sourceArtworkFileType || input.artworkFileType || '';
    input.previewAttachmentId = Number(input.basePreviewAttachmentId || 0);
    input.imageMeta = input.baseImageMeta || input.sourceImageMeta || input.imageMeta || null;
    input.sourceAttachmentId = sourceId;
    input.sourceAttachmentUrl = sourceUrl;
    input.sourceOriginalAttachmentUrl = input.originalAttachmentUrl;
    input.sourceArtworkFileType = input.artworkFileType;
    input.sourcePreviewAttachmentId = input.previewAttachmentId;
    input.sourceImageMeta = input.imageMeta;
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

/***/ "./src/frontend/customiser-app.scss"
/*!******************************************!*\
  !*** ./src/frontend/customiser-app.scss ***!
  \******************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


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
/******/ 			return "chunks/" + chunkId + "." + {"customiser-core":"aa6da5c2","upload-tools":"16930c83"}[chunkId] + ".js";
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/get mini-css chunk filename */
/******/ 	(() => {
/******/ 		// This function allow to reference async chunks
/******/ 		__webpack_require__.miniCssF = (chunkId) => {
/******/ 			// return url for filenames based on template
/******/ 			return "" + chunkId + ".css";
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
/******/ 	/* webpack/runtime/css loading */
/******/ 	(() => {
/******/ 		if (typeof document === "undefined") return;
/******/ 		var createStylesheet = (chunkId, fullhref, oldTag, resolve, reject) => {
/******/ 			var linkTag = document.createElement("link");
/******/ 		
/******/ 			linkTag.rel = "stylesheet";
/******/ 			linkTag.type = "text/css";
/******/ 			if (__webpack_require__.nc) {
/******/ 				linkTag.nonce = __webpack_require__.nc;
/******/ 			}
/******/ 			var onLinkComplete = (event) => {
/******/ 				// avoid mem leaks.
/******/ 				linkTag.onerror = linkTag.onload = null;
/******/ 				if (event.type === 'load') {
/******/ 					resolve();
/******/ 				} else {
/******/ 					var errorType = event && event.type;
/******/ 					var realHref = event && event.target && event.target.href || fullhref;
/******/ 					var err = new Error("Loading CSS chunk " + chunkId + " failed.\n(" + errorType + ": " + realHref + ")");
/******/ 					err.name = "ChunkLoadError";
/******/ 					err.code = "CSS_CHUNK_LOAD_FAILED";
/******/ 					err.type = errorType;
/******/ 					err.request = realHref;
/******/ 					if (linkTag.parentNode) linkTag.parentNode.removeChild(linkTag)
/******/ 					reject(err);
/******/ 				}
/******/ 			}
/******/ 			linkTag.onerror = linkTag.onload = onLinkComplete;
/******/ 			linkTag.href = fullhref;
/******/ 		
/******/ 		
/******/ 			if (oldTag) {
/******/ 				oldTag.parentNode.insertBefore(linkTag, oldTag.nextSibling);
/******/ 			} else {
/******/ 				document.head.appendChild(linkTag);
/******/ 			}
/******/ 			return linkTag;
/******/ 		};
/******/ 		var findStylesheet = (href, fullhref) => {
/******/ 			var existingLinkTags = document.getElementsByTagName("link");
/******/ 			for(var i = 0; i < existingLinkTags.length; i++) {
/******/ 				var tag = existingLinkTags[i];
/******/ 				var dataHref = tag.getAttribute("data-href") || tag.getAttribute("href");
/******/ 				if(tag.rel === "stylesheet" && (dataHref === href || dataHref === fullhref)) return tag;
/******/ 			}
/******/ 			var existingStyleTags = document.getElementsByTagName("style");
/******/ 			for(var i = 0; i < existingStyleTags.length; i++) {
/******/ 				var tag = existingStyleTags[i];
/******/ 				var dataHref = tag.getAttribute("data-href");
/******/ 				if(dataHref === href || dataHref === fullhref) return tag;
/******/ 			}
/******/ 		};
/******/ 		var loadStylesheet = (chunkId) => {
/******/ 			return new Promise((resolve, reject) => {
/******/ 				var href = __webpack_require__.miniCssF(chunkId);
/******/ 				var fullhref = __webpack_require__.p + href;
/******/ 				if(findStylesheet(href, fullhref)) return resolve();
/******/ 				createStylesheet(chunkId, fullhref, null, resolve, reject);
/******/ 			});
/******/ 		}
/******/ 		// object to store loaded CSS chunks
/******/ 		var installedCssChunks = {
/******/ 			"frontend/customiser-app": 0
/******/ 		};
/******/ 		
/******/ 		__webpack_require__.f.miniCss = (chunkId, promises) => {
/******/ 			var cssChunks = {"upload-tools":1};
/******/ 			if(installedCssChunks[chunkId]) promises.push(installedCssChunks[chunkId]);
/******/ 			else if(installedCssChunks[chunkId] !== 0 && cssChunks[chunkId]) {
/******/ 				promises.push(installedCssChunks[chunkId] = loadStylesheet(chunkId).then(() => {
/******/ 					installedCssChunks[chunkId] = 0;
/******/ 				}, (e) => {
/******/ 					delete installedCssChunks[chunkId];
/******/ 					throw e;
/******/ 				}));
/******/ 			}
/******/ 		};
/******/ 		
/******/ 		// no hmr
/******/ 		
/******/ 		// no prefetching
/******/ 		
/******/ 		// no preloaded
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
/* harmony import */ var _customiser_app_scss__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./customiser-app.scss */ "./src/frontend/customiser-app.scss");
/* harmony import */ var _customiser_input_controls__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./customiser/input-controls */ "./src/frontend/customiser/input-controls.js");
/* harmony import */ var _customiser_cart_serialization__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./customiser/cart-serialization */ "./src/frontend/customiser/cart-serialization.js");
/* harmony import */ var _customiser_gallery_preview__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./customiser/gallery-preview */ "./src/frontend/customiser/gallery-preview.js");
/* harmony import */ var _customiser_clipart__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./customiser/clipart */ "./src/frontend/customiser/clipart.js");
/* harmony import */ var _customiser_preflight__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ./customiser/preflight */ "./src/frontend/customiser/preflight.js");
/* harmony import */ var _customiser_spotify__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ./customiser/spotify */ "./src/frontend/customiser/spotify.js");
/* harmony import */ var _customiser_uploads__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ./customiser/uploads */ "./src/frontend/customiser/uploads.js");
/* harmony import */ var _customiser_checkout__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! ./customiser/checkout */ "./src/frontend/customiser/checkout.js");
/**
 * Frontend Customiser — vanilla JS, no framework dependency.
 *
 * Data: window.ocCustomiserData (wp_localize_script).
 * Canvas: Fabric.js 7.x  |  Uploads: Uppy 5.x
 *
 * @package
 */











// ── Boot ──────────────────────────────────────────────────────────────────────

const setBootLoading = loading => {
  const panel = document.getElementById('oc-customiser-panel');
  if (!panel) {
    return;
  }
  panel.inert = loading;
  panel.setAttribute('aria-busy', loading ? 'true' : 'false');
};
const setBootSubmitDisabled = disabled => {
  const panel = document.getElementById('oc-customiser-panel');
  const form = panel?.closest('form');
  const controls = form?.querySelectorAll('[type="submit"], .single_add_to_cart_button') || [];
  controls.forEach(control => {
    if (disabled) {
      if (control.dataset.ocBootDisabled === undefined) {
        control.dataset.ocBootDisabled = control.disabled ? '1' : '0';
      }
      control.disabled = true;
      control.setAttribute('aria-disabled', 'true');
      return;
    }
    if (control.dataset.ocBootDisabled === undefined) {
      return;
    }
    control.disabled = control.dataset.ocBootDisabled === '1';
    control.setAttribute('aria-disabled', control.disabled ? 'true' : 'false');
    delete control.dataset.ocBootDisabled;
  });
};
const renderBootFailure = retry => {
  const root = document.getElementById('oc-preflight-messages');
  if (!root) {
    return;
  }
  const message = document.createElement('div');
  message.className = 'oc-preflight-error';
  message.setAttribute('role', 'alert');
  message.textContent = 'The customisation preview could not load. Check your connection and retry.';
  const button = document.createElement('button');
  button.type = 'button';
  button.className = 'oc-upload-retry';
  button.textContent = 'Retry customiser';
  button.addEventListener('click', retry, {
    once: true
  });
  root.replaceChildren(message, button);
  root.dataset.ocBootFailure = '1';
  root.hidden = false;
};
const clearBootFailure = () => {
  const root = document.getElementById('oc-preflight-messages');
  if (root?.dataset.ocBootFailure !== '1') {
    return;
  }
  root.replaceChildren();
  delete root.dataset.ocBootFailure;
  root.hidden = true;
};
const bootCustomiser = async data => {
  setBootLoading(true);
  let modules;
  try {
    modules = await Promise.all([__webpack_require__.e(/*! import() | customiser-core */ "customiser-core").then(__webpack_require__.bind(__webpack_require__, /*! ./customiser/canvas-renderer */ "./src/frontend/customiser/canvas-renderer.js")), __webpack_require__.e(/*! import() | customiser-core */ "customiser-core").then(__webpack_require__.bind(__webpack_require__, /*! ./customiser/design-variants */ "./src/frontend/customiser/design-variants.js"))]);
  } catch {
    setBootLoading(false);
    setBootSubmitDisabled(true);
    renderBootFailure(() => bootCustomiser(data));
    return;
  }
  const [{
    default: canvasMethods
  }, {
    default: variantMethods
  }] = modules;
  Object.assign(OCCustomiser.prototype, canvasMethods, variantMethods);
  setBootSubmitDisabled(false);
  setBootLoading(false);
  clearBootFailure();
  new OCCustomiser(data).init();
};
document.addEventListener('DOMContentLoaded', () => {
  const data = window.ocCustomiserData;
  if (!data || !data.areas?.length) {
    return;
  }
  bootCustomiser(data);
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
    this.linkGroupCarry = new Map();
    this.artworkContextAuthorisations = new Set();
    this._variationRequestSeq = 0;
    this._variationSwitchPending = false;
    this._variationSwitchFailed = false;
    this._activeVariationKey = '';
    this._pendingVariationKey = '';
    this._variationAbortController = null;
    this._variationSwitchPromise = null;
    this._designVariantRequestSeq = 0;
    this._designVariantAbortController = null;
    this._designVariantPendingSeq = 0;
    this._designGeneration = 0;
    this.spotifyValidateTimers = {};
    this.spotifyValidateTokens = {};
    this.spotifyAbortControllers = {};
    this.spotifyValidationPromises = {};
    this.uploadGenerations = {};
    this.aiFilterGenerations = {};
    this.aiFilterAbortControllers = {};
    this.aiFilterErrors = {};
    this.failedArtworkReplacements = new Set();
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
    this._cropGalleryTimer = null;
    this._cropGalleryCanvas = null;
    this._variationChangeTimer = null;
    this._mobileCartPreviewResolve = null;
    this._mobileCartPreviewPromise = null;
    this._storeApiPreparationPromise = null;
    this._storeApiSubmitBound = false;
    this._storeApiFetchBound = false;
    this._pendingStoreApiCustomisation = null;
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
    this.consumeStoreApiCustomisationMerge?.();
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
    this.failedArtworkReplacements.clear();
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
    this.expireStoreApiCustomisationMerge?.(false);
    this._stateTimers.forEach(window.clearTimeout);
    this._stateTimers.clear();
    this._stateAnimationFrames.forEach(window.cancelAnimationFrame);
    this._stateAnimationFrames.clear();
    this.spotifyModalCloseTimer = null;
    this._galleryPreviewTimer = null;
    this._cropGalleryTimer = null;
    this._cropGalleryCanvas = null;
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
    if (this._designVariantPendingSeq) {
      cartForm?.querySelectorAll('[type="submit"], .single_add_to_cart_button').forEach(control => {
        control.disabled = true;
        control.setAttribute('aria-disabled', 'true');
      });
    }
  }
  restHeaders(extra = {}) {
    const headers = {
      ...extra
    };
    if (this.data.uploadNonce) {
      headers['X-WP-Nonce'] = this.data.uploadNonce;
    }
    if (this.data.requestToken) {
      headers['X-OC-Token'] = this.data.requestToken;
    }
    return headers;
  }
  async ensureRequestToken() {
    if (this.data.requestToken || !this.data.requestTokenUrl) {
      return;
    }
    const request = this.createStateAbortController(12000);
    try {
      const response = await fetch(this.data.requestTokenUrl, {
        credentials: 'same-origin',
        cache: 'no-store',
        headers: {
          Accept: 'application/json'
        },
        signal: request.controller.signal
      });
      const body = await response.json().catch(() => null);
      const token = typeof body?.token === 'string' ? body.token : '';
      if (!response.ok || !/^[A-Za-z0-9]{64}$/.test(token)) {
        throw new Error(body?.message || 'Security verification could not be started.');
      }
      this.data.requestToken = token;
    } catch (error) {
      if (request.timedOut()) {
        throw new Error('Security verification timed out. Please retry.');
      }
      throw error;
    } finally {
      request.release();
    }
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
    await this.hydrateLinkGroupCarry();
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
    if (!this._variationSwitchPending) {
      this._initialAiFilterPromise = this.applyInitialAiFilters();
    }
    this.setupFormSubmit();
    this.setupStoreApiIntegration();
    this.updateHiddenField();
    this.setupDesignVariantCarousel();
    this.renderDesignVariantThumbnails();

    // Canvas init runs in background; calls redraw() when done.
    this.startCanvasInitialisation();
  }
}
Object.assign(OCCustomiser.prototype, _customiser_input_controls__WEBPACK_IMPORTED_MODULE_1__["default"]);
Object.assign(OCCustomiser.prototype, _customiser_cart_serialization__WEBPACK_IMPORTED_MODULE_2__["default"]);
Object.assign(OCCustomiser.prototype, _customiser_gallery_preview__WEBPACK_IMPORTED_MODULE_3__["default"]);
Object.assign(OCCustomiser.prototype, _customiser_clipart__WEBPACK_IMPORTED_MODULE_4__["default"]);
Object.assign(OCCustomiser.prototype, _customiser_preflight__WEBPACK_IMPORTED_MODULE_5__["default"]);
Object.assign(OCCustomiser.prototype, _customiser_spotify__WEBPACK_IMPORTED_MODULE_6__["default"]);
Object.assign(OCCustomiser.prototype, _customiser_uploads__WEBPACK_IMPORTED_MODULE_7__["default"]);
Object.assign(OCCustomiser.prototype, _customiser_checkout__WEBPACK_IMPORTED_MODULE_8__["default"]);
})();

/******/ })()
;