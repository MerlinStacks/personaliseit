/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./src/admin/products-page-core.js"
/*!*****************************************!*\
  !*** ./src/admin/products-page-core.js ***!
  \*****************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _products_page_editor__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./products-page-editor */ "./src/admin/products-page-editor.js");
/**
 * Admin product editor core module.
 *
 * Kept as a small indirection layer so the public products-page entry can stay
 * stable while the editor implementation is split into focused modules.
 */



/***/ },

/***/ "./src/admin/products-page-data.js"
/*!*****************************************!*\
  !*** ./src/admin/products-page-data.js ***!
  \*****************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   createProductsPageDataNormalisers: () => (/* binding */ createProductsPageDataNormalisers)
/* harmony export */ });
function createProductsPageDataNormalisers(deps) {
  const {
    nextUid,
    normaliseAspectRatio,
    normaliseDpi,
    normaliseRotation
  } = deps;
  function normaliseArea(a, i) {
    const unit = ['px', 'mm', 'cm', 'in'].includes(a.unit) ? a.unit : 'px';
    const material = ['glass', 'gold_metal', 'silver_metal', 'black_metal', 'wood'].includes(a.material) ? a.material : 'silver_metal';
    return {
      _uid: nextUid(),
      id: Number(a.id) || 0,
      label: a.label || '',
      method: a.method || 'uv',
      material,
      unit,
      mockupId: Number(a.mockupId) || 0,
      mockupUrl: a.mockupUrl || '',
      x: Number(a.x) || 0,
      y: Number(a.y) || 0,
      w: Number(a.w) || 300,
      h: Number(a.h) || 300,
      dpi: normaliseDpi(a.dpi),
      ratioLocked: !!a.ratioLocked,
      aspectRatio: normaliseAspectRatio(a.aspectRatio, Number(a.w) || 300, Number(a.h) || 300),
      rotation: normaliseRotation(a.rotation),
      sortOrder: i,
      visible: a.visible !== false && a.visible !== 0,
      locked: !!a.locked
    };
  }
  function normaliseLayer(l) {
    const type = l.type || 'text';
    return {
      _uid: nextUid(),
      id: Number(l.id) || 0,
      type,
      label: l.label || '',
      x: Number(l.x) || 0,
      y: Number(l.y) || 0,
      w: Number(l.w) || 200,
      h: Number(l.h) || 50,
      sortOrder: Number(l.sortOrder) || 0,
      visible: l.visible !== false && l.visible !== 0,
      locked: !!l.locked,
      settings: normaliseSettings(type, l.settings)
    };
  }
  function defaultSettings(type) {
    switch (type) {
      case 'text':
        return {
          default_text: '',
          char_limit: 0,
          alignment: 'center',
          default_font_id: 0,
          default_font_size: 0,
          default_color: '#000000',
          min_font_size: 0,
          max_font_size: 0,
          font_groups: [],
          colour_groups: [],
          allow_font_change: true,
          allow_colour_change: true,
          allow_size_change: false,
          required: false,
          link_group: ''
        };
      case 'textarea':
        return {
          default_text: '',
          char_limit: 0,
          alignment: 'center',
          line_alignment: 'top',
          default_font_id: 0,
          default_font_size: 0,
          default_color: '#000000',
          min_font_size: 0,
          max_font_size: 0,
          font_groups: [],
          colour_groups: [],
          allow_font_change: true,
          allow_colour_change: true,
          allow_size_change: false,
          required: false,
          link_group: ''
        };
      case 'image':
        return {
          formats: ['png', 'jpg', 'svg', 'webp'],
          max_size_mb: 10,
          remove_background: false,
          default_attachment_id: 0,
          default_attachment_url: '',
          allow_image_change: true,
          required: false,
          link_group: ''
        };
      case 'clipmask':
        return {
          formats: ['png', 'jpg', 'webp'],
          max_size_mb: 10,
          remove_background: false,
          mask_shape: 'circle',
          required: false,
          link_group: ''
        };
      case 'mask':
        return {
          required: false,
          link_group: ''
        };
      case 'spotify':
        return {
          colour_groups: [],
          required: false,
          link_group: ''
        };
      case 'lineart':
        return {
          colour_groups: [],
          required: false,
          link_group: ''
        };
      case 'clipart':
        return {
          clipart_groups: [],
          default_clipart_id: 0,
          default_clipart_url: '',
          default_clipart_recolourable: false,
          allow_clipart_change: true,
          required: false,
          clipart_display: 'grid',
          link_group: ''
        };
      default:
        return {
          required: false,
          link_group: ''
        };
    }
  }
  function normaliseSettings(type, existing) {
    const settings = Object.assign(defaultSettings(type), existing || {});
    if (type === 'textarea' && !['top', 'center', 'bottom'].includes(settings.line_alignment)) {
      settings.line_alignment = 'top';
    }
    if (type === 'clipart') {
      settings.clipart_display = settings.clipart_display === 'carousel' ? 'carousel' : 'grid';
      settings.default_clipart_id = Number(settings.default_clipart_id) || 0;
      settings.default_clipart_recolourable = !!settings.default_clipart_recolourable;
      settings.allow_clipart_change = settings.allow_clipart_change !== false;
    }
    if (type === 'image') {
      settings.default_attachment_id = Number(settings.default_attachment_id) || 0;
      settings.default_attachment_url = settings.default_attachment_url || '';
      settings.allow_image_change = settings.allow_image_change !== false;
    }
    return settings;
  }
  return {
    normaliseArea,
    normaliseLayer,
    defaultSettings,
    normaliseSettings
  };
}

/***/ },

/***/ "./src/admin/products-page-editor.js"
/*!*******************************************!*\
  !*** ./src/admin/products-page-editor.js ***!
  \*******************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _shared_render_math__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../shared/render-math */ "./src/shared/render-math.js");
/* harmony import */ var _products_page_settings__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./products-page-settings */ "./src/admin/products-page-settings.js");
/* harmony import */ var _products_page_metadata__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./products-page-metadata */ "./src/admin/products-page-metadata.js");
/* harmony import */ var _products_page_preview__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./products-page-preview */ "./src/admin/products-page-preview.js");
/* harmony import */ var _products_page_hidden_fields__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./products-page-hidden-fields */ "./src/admin/products-page-hidden-fields.js");
/* harmony import */ var _products_page_data__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ./products-page-data */ "./src/admin/products-page-data.js");
/* harmony import */ var _products_page_mockup_picker__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ./products-page-mockup-picker */ "./src/admin/products-page-mockup-picker.js");
/* eslint-disable no-console, no-alert, no-undef, no-shadow, no-unused-vars, @wordpress/no-unused-vars-before-return */







(function () {
  'use strict';

  let areas = [];
  let selectedIndex = -1;
  let selectedLayerIndex = -1;
  let activeLayerTab = 'general';
  let uidCounter = 0;
  let layerDragSrc = -1;
  const HISTORY_MAX = 25;
  let history = [];
  let historyIndex = -1;
  let isDirty = false;
  let autosaveTimer = null;
  let lastSavedTime = null;
  let autosaveError = '';
  let designId = 0;
  const autosaveInterval = 30000;
  function snapshot() {
    if (historyIndex < history.length - 1) {
      history = history.slice(0, historyIndex + 1);
    }
    history.push(JSON.stringify(areas));
    if (history.length > HISTORY_MAX) {
      history.shift();
    }
    historyIndex = history.length - 1;
    updateUndoRedoBtns();
    markDirty();
  }
  function undo() {
    if (historyIndex <= 0) {
      return;
    }
    historyIndex--;
    restoreHistory();
  }
  function redo() {
    if (historyIndex >= history.length - 1) {
      return;
    }
    historyIndex++;
    restoreHistory();
  }
  function restoreHistory() {
    let snapshot;
    try {
      snapshot = JSON.parse(history[historyIndex]);
    } catch (err) {
      console.warn('[OC] Failed to restore history snapshot:', err);
      history = [];
      historyIndex = -1;
      updateUndoRedoBtns();
      return;
    }
    areas = snapshot;
    areas.forEach(a => {
      uidCounter = Math.max(uidCounter, a._uid || 0);
      (a.layers || []).forEach(l => {
        uidCounter = Math.max(uidCounter, l._uid || 0);
      });
    });
    if (selectedIndex >= areas.length) {
      selectedIndex = areas.length - 1;
    }
    const area = areas[selectedIndex];
    if (!area || selectedLayerIndex >= (area.layers || []).length) {
      selectedLayerIndex = -1;
    }
    renderAll();
    updateUndoRedoBtns();
  }
  function updateUndoRedoBtns() {
    const u = document.getElementById('oc-undo-btn');
    const r = document.getElementById('oc-redo-btn');
    if (u) {
      u.disabled = historyIndex <= 0;
    }
    if (r) {
      r.disabled = historyIndex >= history.length - 1;
    }
  }
  function markDirty() {
    isDirty = true;
    updateAutosaveIndicator();
  }
  function updateAutosaveIndicator() {
    const el = document.getElementById('oc-autosave-indicator');
    if (!el) {
      return;
    }
    if (autosaveError) {
      el.textContent = autosaveError;
      el.className = 'oc-autosave-indicator oc-autosave-indicator--error';
    } else if (isDirty) {
      el.textContent = 'Unsaved changes';
      el.className = 'oc-autosave-indicator oc-autosave-indicator--dirty';
    } else if (lastSavedTime) {
      const diff = Math.round((Date.now() - lastSavedTime) / 1000);
      const label = diff < 60 ? diff + 's ago' : Math.floor(diff / 60) + 'm ago';
      el.textContent = 'Saved ' + label;
      el.className = 'oc-autosave-indicator oc-autosave-indicator--saved';
    } else {
      el.textContent = '';
      el.className = 'oc-autosave-indicator';
    }
  }
  function collectState() {
    return {
      areas: areas.map(function (a) {
        return {
          id: a.id,
          label: a.label,
          method: a.method,
          material: a.material,
          mockupId: a.mockupId,
          mockupUrl: a.mockupUrl,
          x: a.x,
          y: a.y,
          w: a.w,
          h: a.h,
          dpi: a.dpi,
          ratioLocked: a.ratioLocked,
          aspectRatio: a.aspectRatio,
          rotation: a.rotation,
          sortOrder: a.sortOrder,
          visible: a.visible,
          locked: a.locked,
          layers: (a.layers || []).map(function (l) {
            return {
              id: l.id,
              type: l.type,
              label: l.label,
              x: l.x,
              y: l.y,
              w: l.w,
              h: l.h,
              sortOrder: l.sortOrder,
              visible: l.visible,
              locked: l.locked,
              settings: l.settings || {}
            };
          })
        };
      })
    };
  }
  function applyAutosavedState(savedState) {
    const layersByAreaId = {};
    (savedState.areas || []).forEach(function (a) {
      (a.layers || []).forEach(function (l) {
        const aid = Number(l.areaId || 0);
        if (!layersByAreaId[aid]) {
          layersByAreaId[aid] = [];
        }
        layersByAreaId[aid].push(normaliseLayer(l));
      });
    });
    areas = (savedState.areas || []).map(function (a, i) {
      return Object.assign(normaliseArea(a, i), {
        layers: layersByAreaId[Number(a.id)] || []
      });
    });
    selectedIndex = areas.length > 0 ? 0 : -1;
    selectedLayerIndex = -1;
    activeLayerTab = 'general';
    history = [];
    historyIndex = -1;
    snapshot();
    renderAll();
  }
  function doAutosave() {
    if (!isDirty || !designId) {
      return;
    }
    const state = collectState();
    const body = new URLSearchParams({
      action: 'oc_autosave_design',
      nonce: ocProductsData.nonce,
      design_id: designId,
      state: JSON.stringify(state)
    });
    fetch(ocProductsData.ajaxUrl, {
      method: 'POST',
      body
    }).then(function (r) {
      if (!r.ok) {
        throw new Error('HTTP ' + r.status);
      }
      return r.json();
    }).then(function (json) {
      if (json.success) {
        isDirty = false;
        lastSavedTime = Date.now();
        autosaveError = '';
        updateAutosaveIndicator();
      } else {
        autosaveError = 'Autosave failed';
        updateAutosaveIndicator();
      }
    }).catch(function (err) {
      console.warn('[OC] Autosave failed:', err);
      autosaveError = 'Autosave failed';
      updateAutosaveIndicator();
    });
  }
  function startAutosavePoll() {
    if (autosaveTimer) {
      clearInterval(autosaveTimer);
    }
    autosaveTimer = setInterval(doAutosave, autosaveInterval);
  }
  function stopAutosavePoll() {
    if (autosaveTimer) {
      clearInterval(autosaveTimer);
      autosaveTimer = null;
    }
  }
  function init() {
    const data = window.ocProductsData || {};
    designId = Number(data.designId || 0);
    if (designId > 0) {
      const body = new URLSearchParams({
        action: 'oc_restore_autosave',
        nonce: data.nonce,
        design_id: designId
      });
      fetch(data.ajaxUrl, {
        method: 'POST',
        body
      }).then(function (r) {
        if (!r.ok) {
          throw new Error('HTTP ' + r.status);
        }
        return r.json();
      }).then(function (json) {
        if (json.success && json.data && json.data.state) {
          const ts = json.data.timestamp || 0;
          const diff = Math.round((Date.now() - ts * 1000) / 1000);
          const mins = Math.max(1, Math.floor(diff / 60));
          const msg = 'You have unsaved changes from ' + mins + ' minute' + (mins > 1 ? 's' : '') + ' ago. Restore?';
          if (confirm(msg)) {
            applyAutosavedState(json.data.state);
            isDirty = true;
            startAutosavePoll();
            updateAutosaveIndicator();
            initInteractions();
            return;
          }
        }
        loadDefaultData();
      }).catch(loadDefaultData);
    } else {
      loadDefaultData();
    }
  }
  function loadDefaultData() {
    const data = window.ocProductsData || {};
    const layersByAreaId = {};
    (data.layers || []).forEach(l => {
      const aid = Number(l.areaId);
      if (!layersByAreaId[aid]) {
        layersByAreaId[aid] = [];
      }
      layersByAreaId[aid].push(normaliseLayer(l));
    });
    areas = (data.areas || []).map((a, i) => ({
      ...normaliseArea(a, i),
      layers: layersByAreaId[Number(a.id)] || []
    }));
    selectedIndex = areas.length > 0 ? 0 : -1;
    renderAll();
    snapshot(); // seed initial history state
    isDirty = false; // reset after seed
    initInteractions();
    if (designId > 0) {
      startAutosavePoll();
    }
    document.getElementById('oc-design-form')?.addEventListener('submit', () => {
      renderHiddenFields();
      stopAutosavePoll();
    });
  }
  const {
    normaliseArea,
    normaliseLayer,
    defaultSettings,
    normaliseSettings
  } = (0,_products_page_data__WEBPACK_IMPORTED_MODULE_5__.createProductsPageDataNormalisers)({
    nextUid: () => ++uidCounter,
    normaliseAspectRatio,
    normaliseDpi: _shared_render_math__WEBPACK_IMPORTED_MODULE_0__.normaliseDpi,
    normaliseRotation
  });
  function selectedArea() {
    return areas[selectedIndex] || null;
  }
  function selectedLayer() {
    const area = selectedArea();
    return area && selectedLayerIndex >= 0 ? area.layers[selectedLayerIndex] || null : null;
  }
  const applyLayerPreview = (0,_products_page_preview__WEBPACK_IMPORTED_MODULE_3__.createLayerPreviewRenderer)({
    fontLimit,
    layerLabel: _products_page_metadata__WEBPACK_IMPORTED_MODULE_2__.layerLabel,
    normaliseHex
  });
  const openMockupPicker = (0,_products_page_mockup_picker__WEBPACK_IMPORTED_MODULE_6__.createMockupPicker)({
    commitChange,
    getSelectedIndex: () => selectedIndex,
    selectedArea
  });
  const {
    initInteractions,
    addLayerWithBounds
  } = createProductsPageInteractions({
    addArea: area => areas.push(area),
    commitChange,
    getAreas: () => areas,
    getSelectedIndex: () => selectedIndex,
    getSelectedLayerIndex: () => selectedLayerIndex,
    initCanvasInteractions,
    markDirty,
    normaliseArea,
    normaliseDpi: _shared_render_math__WEBPACK_IMPORTED_MODULE_0__.normaliseDpi,
    normaliseUnit: _shared_render_math__WEBPACK_IMPORTED_MODULE_0__.normaliseUnit,
    openMockupPicker,
    renderAll,
    renderGhosts,
    renderRatioLockButton,
    selectedArea,
    setSelectedIndex: index => {
      selectedIndex = index;
    },
    setSelectedLayerIndex: index => {
      selectedLayerIndex = index;
    },
    snapshot,
    syncBoundsFromInputs,
    updateAspectRatio,
    updateBoundsBox
  });
  const {
    renderCanvas,
    updateBoundsBox,
    renderGhosts,
    initCanvasInteractions,
    syncBoundsFromInputs,
    syncRightBounds
  } = createProductsPageCanvas({
    addLayerWithBounds,
    applyLayerPreview,
    clamp,
    clampLayerToArea,
    currentAspectRatio,
    getAreas: () => areas,
    getScale,
    getSelectedIndex: () => selectedIndex,
    getSelectedLayerIndex: () => selectedLayerIndex,
    hexRgba,
    markDirty,
    normaliseDpi: _shared_render_math__WEBPACK_IMPORTED_MODULE_0__.normaliseDpi,
    normaliseRotation,
    renderAll,
    renderHiddenFields,
    renderRatioLockButton,
    selectedArea,
    selectedLayer,
    setSelectedLayerIndex: index => {
      selectedLayerIndex = index;
    },
    setVal,
    snapshot,
    updateAspectRatio
  });
  const {
    buildTabContent,
    bindSettingsHandlers
  } = (0,_products_page_settings__WEBPACK_IMPORTED_MODULE_1__.createProductsPageSettings)({
    commitChange,
    esc,
    fontLimit,
    getAreas: () => areas,
    layerLabel: _products_page_metadata__WEBPACK_IMPORTED_MODULE_2__.layerLabel,
    normaliseHex,
    normaliseLinkGroup,
    renderLayerList,
    selectedArea,
    syncBoundsFromInputs
  });
  function renderAll() {
    renderAreasList();
    renderAreaStrip();
    renderLeftAreaProps();
    renderCanvas();
    renderRightColumn();
    renderHiddenFields();
  }
  function commitChange(options = {}) {
    if (options.all) {
      renderAll();
    } else {
      if (options.areasList) {
        renderAreasList();
      }
      if (options.areaStrip) {
        renderAreaStrip();
      }
      if (options.canvas) {
        renderCanvas();
      }
      if (options.rightColumn) {
        renderRightColumn();
      }
      if (options.hiddenFields !== false) {
        renderHiddenFields();
      }
    }
    markDirty();
  }
  function renderAreaStrip() {
    const strip = document.getElementById('oc-area-strip');
    if (!strip) {
      return;
    }
    if (areas.length === 0) {
      strip.style.display = 'none';
      return;
    }
    strip.style.display = '';
    strip.innerHTML = '';
    areas.forEach((area, i) => {
      const card = document.createElement('div');
      card.className = 'oc-area-strip-card' + (i === selectedIndex ? ' oc-area-strip-card--active' : '') + (!area.visible ? ' oc-area-strip-card--hidden' : '');
      const thumb = document.createElement('div');
      thumb.className = 'oc-area-strip-thumb';
      if (area.mockupUrl) {
        const img = new Image();
        img.src = area.mockupUrl;
        img.draggable = false;
        thumb.appendChild(img);
      } else {
        thumb.style.background = (0,_products_page_metadata__WEBPACK_IMPORTED_MODULE_2__.areaColor)(i);
      }
      const lbl = document.createElement('span');
      lbl.className = 'oc-area-strip-label';
      lbl.textContent = area.label || 'Area ' + (i + 1);
      card.appendChild(thumb);
      card.appendChild(lbl);
      card.addEventListener('click', () => {
        if (area.locked) {
          return;
        } // locked areas can't be selected from strip
        selectedIndex = i;
        selectedLayerIndex = -1;
        activeLayerTab = 'general';
        renderAll();
      });
      strip.appendChild(card);
    });
  }
  function renderAreasList() {
    const list = document.getElementById('oc-areas-list');
    const empty = document.getElementById('oc-areas-empty');
    if (!list) {
      return;
    }
    list.innerHTML = '';
    if (areas.length === 0) {
      if (empty) {
        empty.style.display = '';
      }
      return;
    }
    if (empty) {
      empty.style.display = 'none';
    }
    areas.forEach((area, i) => {
      const item = document.createElement('div');
      item.className = 'oc-area-item' + (i === selectedIndex ? ' oc-area-item--active' : '') + (!area.visible ? ' oc-layer--hidden' : '') + (area.locked ? ' oc-layer--locked' : '');
      item.innerHTML = '<span class="oc-area-dot" style="background:' + (0,_products_page_metadata__WEBPACK_IMPORTED_MODULE_2__.areaColor)(i) + ';flex-shrink:0;"></span>' + '<span class="oc-area-item-name">' + esc(area.label || 'Print Area ' + (i + 1)) + '</span>' + '<span class="oc-area-item-method">' + esc(methodLabel(area.method)) + '</span>' + '<div class="oc-layer-actions">' + '<button type="button" class="oc-layer-action-btn oc-layer-vis-btn' + (!area.visible ? ' is-off' : '') + '" title="' + (area.visible ? 'Hide area' : 'Show area') + '">' + (area.visible ? _products_page_metadata__WEBPACK_IMPORTED_MODULE_2__.ICO_EYE : _products_page_metadata__WEBPACK_IMPORTED_MODULE_2__.ICO_EYE_OFF) + '</button>' + '<button type="button" class="oc-layer-action-btn oc-layer-lock-btn' + (area.locked ? ' is-on' : '') + '" title="' + (area.locked ? 'Unlock area' : 'Lock area') + '">' + (area.locked ? _products_page_metadata__WEBPACK_IMPORTED_MODULE_2__.ICO_LOCK : _products_page_metadata__WEBPACK_IMPORTED_MODULE_2__.ICO_UNLOCK) + '</button>' + '<button type="button" class="oc-layer-action-btn oc-layer-delete-btn" title="Delete area">' + _products_page_metadata__WEBPACK_IMPORTED_MODULE_2__.ICO_BIN + '</button>' + '</div>';
      item.addEventListener('click', e => {
        if (e.target.closest('.oc-layer-actions')) {
          return;
        }
        if (area.locked) {
          return;
        } // locked areas can't be selected from list
        selectedIndex = i;
        selectedLayerIndex = -1;
        activeLayerTab = 'general';
        renderAll();
      });
      item.querySelector('.oc-layer-vis-btn').addEventListener('click', e => {
        e.stopPropagation();
        area.visible = !area.visible;
        snapshot();
        renderAll();
      });
      item.querySelector('.oc-layer-lock-btn').addEventListener('click', e => {
        e.stopPropagation();
        area.locked = !area.locked;
        snapshot();
        renderAll();
      });
      item.querySelector('.oc-layer-delete-btn').addEventListener('click', e => {
        e.stopPropagation();
        if (!confirm('Remove this print area and all its layers?')) {
          return;
        }
        areas.splice(i, 1);
        if (selectedIndex === i) {
          selectedIndex = areas.length > 0 ? Math.min(i, areas.length - 1) : -1;
        } else if (selectedIndex > i) {
          selectedIndex--;
        }
        selectedLayerIndex = -1;
        snapshot();
        renderAll();
      });
      list.appendChild(item);
    });
  }
  function renderLeftAreaProps() {
    const noSel = document.getElementById('oc-area-no-sel');
    const inner = document.getElementById('oc-area-props-inner');
    const area = selectedArea();
    if (!area) {
      if (noSel) {
        noSel.style.display = '';
      }
      if (inner) {
        inner.style.display = 'none';
      }
      return;
    }
    if (noSel) {
      noSel.style.display = 'none';
    }
    if (inner) {
      inner.style.display = '';
    }
    setVal('oc-prop-label', area.label);
    setVal('oc-prop-method', area.method);
    setVal('oc-prop-engraving-material', area.material || 'silver_metal');
    setVal('oc-prop-unit', area.unit || 'px');
    setVal('oc-prop-x', area.x);
    setVal('oc-prop-y', area.y);
    setVal('oc-prop-w', area.w);
    setVal('oc-prop-h', area.h);
    setVal('oc-prop-dpi', area.dpi || 300);
    setVal('oc-prop-rotation', area.rotation);
    renderRatioLockButton(area);
    const thumb = document.getElementById('oc-mockup-thumb-img');
    const noThumb = document.getElementById('oc-mockup-thumb-empty');
    const removeBtn = document.getElementById('oc-remove-mockup-btn');
    const chooseBtn = document.getElementById('oc-choose-mockup-btn');
    const dot = document.getElementById('oc-right-area-color');
    if (area.mockupUrl) {
      if (thumb) {
        thumb.src = area.mockupUrl;
        thumb.style.display = '';
      }
      if (noThumb) {
        noThumb.style.display = 'none';
      }
    } else {
      if (thumb) {
        thumb.style.display = 'none';
      }
      if (noThumb) {
        noThumb.style.display = '';
      }
    }
    if (removeBtn) {
      removeBtn.style.display = area.mockupUrl ? '' : 'none';
    }
    if (chooseBtn) {
      const chooseLabel = area.mockupUrl ? 'Change Mockup' : 'Choose Mockup';
      chooseBtn.setAttribute('aria-label', chooseLabel);
      chooseBtn.setAttribute('title', chooseLabel);
    }
    if (dot) {
      dot.style.background = (0,_products_page_metadata__WEBPACK_IMPORTED_MODULE_2__.areaColor)(selectedIndex);
    }
    const materialWrap = document.getElementById('oc-prop-engraving-material-wrap');
    if (materialWrap) {
      materialWrap.style.display = area.method === 'engraving' ? '' : 'none';
    }
  }
  function renderRatioLockButton(area) {
    const btn = document.getElementById('oc-prop-ratio-lock');
    if (!btn || !area) {
      return;
    }
    btn.innerHTML = area.ratioLocked ? _products_page_metadata__WEBPACK_IMPORTED_MODULE_2__.ICO_LOCK : _products_page_metadata__WEBPACK_IMPORTED_MODULE_2__.ICO_UNLOCK;
    btn.classList.toggle('is-on', !!area.ratioLocked);
    btn.setAttribute('aria-pressed', area.ratioLocked ? 'true' : 'false');
    btn.setAttribute('aria-label', area.ratioLocked ? 'Unlock aspect ratio' : 'Lock aspect ratio');
    btn.setAttribute('title', area.ratioLocked ? 'Unlock aspect ratio' : 'Lock aspect ratio');
  }
  function renderRightColumn() {
    const area = selectedArea();
    const layer = selectedLayer();
    const hint = document.getElementById('oc-type-picker-hint');
    if (hint) {
      hint.style.display = area ? 'none' : '';
    }
    document.querySelectorAll('.oc-layer-type-btn').forEach(btn => {
      btn.disabled = !area;
      btn.style.opacity = area ? '' : '.4';
    });
    renderLayerList(area);
    const noSel = document.getElementById('oc-layer-no-sel');
    const inner = document.getElementById('oc-layer-props-inner');
    if (!layer) {
      if (noSel) {
        noSel.style.display = '';
      }
      if (inner) {
        inner.style.display = 'none';
      }
    } else {
      if (noSel) {
        noSel.style.display = 'none';
      }
      if (inner) {
        inner.style.display = '';
      }
      renderLayerPanel(layer);
    }
  }
  function renderLayerList(area) {
    const listEl = document.getElementById('oc-layers-list');
    const noArea = document.getElementById('oc-layers-no-area');
    const emptyEl = document.getElementById('oc-layers-empty');
    const countEl = document.getElementById('oc-layers-count');
    if (!listEl) {
      return;
    }
    listEl.innerHTML = '';
    if (!area) {
      if (noArea) {
        noArea.style.display = '';
      }
      if (emptyEl) {
        emptyEl.style.display = 'none';
      }
      if (countEl) {
        countEl.textContent = '';
      }
      return;
    }
    if (noArea) {
      noArea.style.display = 'none';
    }
    const layers = area.layers || [];
    if (countEl) {
      countEl.textContent = layers.length + (1 === layers.length ? ' layer' : ' layers');
    }
    if (layers.length === 0) {
      if (emptyEl) {
        emptyEl.style.display = '';
      }
      return;
    }
    if (emptyEl) {
      emptyEl.style.display = 'none';
    }
    layers.forEach((layer, li) => {
      const item = document.createElement('div');
      item.className = 'oc-layer-item' + (li === selectedLayerIndex ? ' oc-layer-item--active' : '') + (!layer.visible ? ' oc-layer--hidden' : '') + (layer.locked ? ' oc-layer--locked' : '');
      item.draggable = true;
      item.dataset.layerIndex = li;
      item.innerHTML = '<span class="oc-layer-drag-handle" title="Drag to reorder">\u22ee\u22ee</span>' + '<span class="oc-layer-icon" style="color:' + (0,_products_page_metadata__WEBPACK_IMPORTED_MODULE_2__.layerColor)(layer.type) + ';">' + esc((0,_products_page_metadata__WEBPACK_IMPORTED_MODULE_2__.layerIcon)(layer.type)) + '</span>' + '<span class="oc-layer-item-name">' + esc(layer.label || (0,_products_page_metadata__WEBPACK_IMPORTED_MODULE_2__.layerLabel)(layer.type)) + '</span>' + '<div class="oc-layer-actions">' + '<button type="button" class="oc-layer-action-btn oc-layer-vis-btn' + (!layer.visible ? ' is-off' : '') + '" title="' + (layer.visible ? 'Hide layer' : 'Show layer') + '">' + (layer.visible ? _products_page_metadata__WEBPACK_IMPORTED_MODULE_2__.ICO_EYE : _products_page_metadata__WEBPACK_IMPORTED_MODULE_2__.ICO_EYE_OFF) + '</button>' + '<button type="button" class="oc-layer-action-btn oc-layer-lock-btn' + (layer.locked ? ' is-on' : '') + '" title="' + (layer.locked ? 'Unlock layer' : 'Lock layer') + '">' + (layer.locked ? _products_page_metadata__WEBPACK_IMPORTED_MODULE_2__.ICO_LOCK : _products_page_metadata__WEBPACK_IMPORTED_MODULE_2__.ICO_UNLOCK) + '</button>' + '<button type="button" class="oc-layer-action-btn oc-layer-delete-btn" title="Delete layer">' + _products_page_metadata__WEBPACK_IMPORTED_MODULE_2__.ICO_BIN + '</button>' + '</div>';
      item.addEventListener('click', e => {
        if (e.target.closest('.oc-layer-actions') || e.target.classList.contains('oc-layer-drag-handle')) {
          return;
        }
        if (layer.locked) {
          return;
        } // locked layers can't be selected from list
        selectedLayerIndex = li;
        renderAll();
      });
      item.querySelector('.oc-layer-vis-btn').addEventListener('click', e => {
        e.stopPropagation();
        layer.visible = !layer.visible;
        snapshot();
        renderAll();
      });
      item.querySelector('.oc-layer-lock-btn').addEventListener('click', e => {
        e.stopPropagation();
        layer.locked = !layer.locked;
        snapshot();
        renderAll();
      });
      item.querySelector('.oc-layer-delete-btn').addEventListener('click', e => {
        e.stopPropagation();
        if (!confirm('Remove this layer?')) {
          return;
        }
        area.layers.splice(li, 1);
        if (selectedLayerIndex === li) {
          selectedLayerIndex = -1;
        } else if (selectedLayerIndex > li) {
          selectedLayerIndex--;
        }
        snapshot();
        renderAll();
      });
      item.addEventListener('dragstart', e => {
        layerDragSrc = li;
        e.dataTransfer.effectAllowed = 'move';
        setTimeout(() => item.classList.add('oc-layer-item--dragging'), 0);
      });
      item.addEventListener('dragend', () => {
        layerDragSrc = -1;
        listEl.querySelectorAll('.oc-layer-item--dragging, .oc-layer-item--drag-over').forEach(el => {
          el.classList.remove('oc-layer-item--dragging', 'oc-layer-item--drag-over');
        });
      });
      item.addEventListener('dragover', e => {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        if (Number(item.dataset.layerIndex) !== layerDragSrc) {
          listEl.querySelectorAll('.oc-layer-item--drag-over').forEach(el => el.classList.remove('oc-layer-item--drag-over'));
          item.classList.add('oc-layer-item--drag-over');
        }
      });
      item.addEventListener('drop', e => {
        e.preventDefault();
        const targetIdx = Number(item.dataset.layerIndex);
        if (layerDragSrc < 0 || layerDragSrc === targetIdx) {
          return;
        }
        const moved = area.layers.splice(layerDragSrc, 1)[0];
        area.layers.splice(targetIdx, 0, moved);
        if (selectedLayerIndex === layerDragSrc) {
          selectedLayerIndex = targetIdx;
        } else if (layerDragSrc < targetIdx && selectedLayerIndex > layerDragSrc && selectedLayerIndex <= targetIdx) {
          selectedLayerIndex--;
        } else if (layerDragSrc > targetIdx && selectedLayerIndex >= targetIdx && selectedLayerIndex < layerDragSrc) {
          selectedLayerIndex++;
        }
        snapshot();
        renderAll();
      });
      listEl.appendChild(item);
    });
  }
  function renderLayerPanel(layer) {
    const iconEl = document.getElementById('oc-layer-type-icon');
    const lblEl = document.getElementById('oc-layer-type-label');
    const dotEl = document.getElementById('oc-layer-color-dot');
    if (iconEl) {
      iconEl.textContent = (0,_products_page_metadata__WEBPACK_IMPORTED_MODULE_2__.layerIcon)(layer.type);
    }
    if (lblEl) {
      lblEl.textContent = (0,_products_page_metadata__WEBPACK_IMPORTED_MODULE_2__.layerLabel)(layer.type);
    }
    if (dotEl) {
      dotEl.style.background = (0,_products_page_metadata__WEBPACK_IMPORTED_MODULE_2__.layerColor)(layer.type);
    }
    const tabs = _products_page_metadata__WEBPACK_IMPORTED_MODULE_2__.LAYER_TABS[layer.type] || _products_page_metadata__WEBPACK_IMPORTED_MODULE_2__.LAYER_TABS.text;
    if (!tabs.some(t => t.id === activeLayerTab)) {
      activeLayerTab = 'general';
    }
    const settingsEl = document.getElementById('oc-layer-settings');
    if (!settingsEl) {
      return;
    }
    let html = '<div class="oc-layer-tabs-bar">';
    tabs.forEach(t => {
      html += '<button type="button" class="oc-layer-tab' + (t.id === activeLayerTab ? ' oc-layer-tab--active' : '') + '" data-tab="' + t.id + '" title="' + esc(t.label) + '" aria-label="' + esc(t.label) + '"><span aria-hidden="true">' + esc(t.icon || t.label.charAt(0)) + '</span></button>';
    });
    html += '</div>';
    tabs.forEach(t => {
      html += '<div class="oc-layer-tab-panel' + (t.id === activeLayerTab ? ' oc-layer-tab-panel--active' : '') + '" data-panel="' + t.id + '">';
      html += buildTabContent(t.id, layer);
      html += '</div>';
    });
    settingsEl.innerHTML = html;
    settingsEl.querySelectorAll('.oc-layer-tab').forEach(btn => {
      btn.addEventListener('click', () => {
        activeLayerTab = btn.dataset.tab;
        settingsEl.querySelectorAll('.oc-layer-tab').forEach(b => b.classList.toggle('oc-layer-tab--active', b.dataset.tab === activeLayerTab));
        settingsEl.querySelectorAll('.oc-layer-tab-panel').forEach(p => p.classList.toggle('oc-layer-tab-panel--active', p.dataset.panel === activeLayerTab));
      });
    });
    bindSettingsHandlers(layer);
  }
  function renderHiddenFields() {
    (0,_products_page_hidden_fields__WEBPACK_IMPORTED_MODULE_4__.renderProductsPageHiddenFields)(areas, esc);
  }
  document.addEventListener('DOMContentLoaded', init);
})();

/***/ },

/***/ "./src/admin/products-page-hidden-fields.js"
/*!**************************************************!*\
  !*** ./src/admin/products-page-hidden-fields.js ***!
  \**************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   renderProductsPageHiddenFields: () => (/* binding */ renderProductsPageHiddenFields)
/* harmony export */ });
function renderProductsPageHiddenFields(areas, esc) {
  const c = document.getElementById('oc-hidden-fields');
  if (!c) {
    return;
  }
  let html = '';
  areas.forEach((area, i) => {
    const p = 'oc_design_areas[' + i + ']';
    html += '<input type="hidden" name="' + p + '[id]"                   value="' + esc(area.id) + '">' + '<input type="hidden" name="' + p + '[label]"                value="' + esc(area.label) + '">' + '<input type="hidden" name="' + p + '[print_method]"         value="' + esc(area.method) + '">' + '<input type="hidden" name="' + p + '[engraving_material]"   value="' + esc(area.material || 'silver_metal') + '">' + '<input type="hidden" name="' + p + '[canvas_unit]"           value="' + esc(area.unit || 'px') + '">' + '<input type="hidden" name="' + p + '[mockup_attachment_id]" value="' + esc(area.mockupId) + '">' + '<input type="hidden" name="' + p + '[canvas_x]"             value="' + esc(area.x) + '">' + '<input type="hidden" name="' + p + '[canvas_y]"             value="' + esc(area.y) + '">' + '<input type="hidden" name="' + p + '[canvas_w]"             value="' + esc(area.w) + '">' + '<input type="hidden" name="' + p + '[canvas_h]"             value="' + esc(area.h) + '">' + '<input type="hidden" name="' + p + '[canvas_dpi]"           value="' + esc(area.dpi || 300) + '">' + '<input type="hidden" name="' + p + '[canvas_rotation]"      value="' + esc(area.rotation) + '">' + '<input type="hidden" name="' + p + '[sort_order]"           value="' + esc(i) + '">' + '<input type="hidden" name="' + p + '[visible]"              value="' + esc(area.visible ? '1' : '0') + '">' + '<input type="hidden" name="' + p + '[locked]"               value="' + esc(area.locked ? '1' : '0') + '">';
  });
  let li = 0;
  areas.forEach((area, areaIdx) => {
    (area.layers || []).forEach((layer, sort) => {
      const p = 'oc_layers[' + li + ']';
      html += '<input type="hidden" name="' + p + '[id]"         value="' + esc(layer.id) + '">' + '<input type="hidden" name="' + p + '[area_index]" value="' + esc(areaIdx) + '">' + '<input type="hidden" name="' + p + '[type]"       value="' + esc(layer.type) + '">' + '<input type="hidden" name="' + p + '[label]"      value="' + esc(layer.label) + '">' + '<input type="hidden" name="' + p + '[x]"          value="' + esc(layer.x) + '">' + '<input type="hidden" name="' + p + '[y]"          value="' + esc(layer.y) + '">' + '<input type="hidden" name="' + p + '[w]"          value="' + esc(layer.w) + '">' + '<input type="hidden" name="' + p + '[h]"          value="' + esc(layer.h) + '">' + '<input type="hidden" name="' + p + '[sort_order]" value="' + esc(sort) + '">' + '<input type="hidden" name="' + p + '[visible]"    value="' + esc(layer.visible ? '1' : '0') + '">' + '<input type="hidden" name="' + p + '[locked]"     value="' + esc(layer.locked ? '1' : '0') + '">' + '<input type="hidden" name="' + p + '[settings]"   value="' + esc(JSON.stringify(layer.settings || {})) + '">';
      li++;
    });
  });
  c.innerHTML = html;
}

/***/ },

/***/ "./src/admin/products-page-metadata.js"
/*!*********************************************!*\
  !*** ./src/admin/products-page-metadata.js ***!
  \*********************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   AREA_COLORS: () => (/* binding */ AREA_COLORS),
/* harmony export */   ICO_BIN: () => (/* binding */ ICO_BIN),
/* harmony export */   ICO_EYE: () => (/* binding */ ICO_EYE),
/* harmony export */   ICO_EYE_OFF: () => (/* binding */ ICO_EYE_OFF),
/* harmony export */   ICO_LOCK: () => (/* binding */ ICO_LOCK),
/* harmony export */   ICO_UNLOCK: () => (/* binding */ ICO_UNLOCK),
/* harmony export */   LAYER_DEFAULTS: () => (/* binding */ LAYER_DEFAULTS),
/* harmony export */   LAYER_TABS: () => (/* binding */ LAYER_TABS),
/* harmony export */   LAYER_TYPES: () => (/* binding */ LAYER_TYPES),
/* harmony export */   areaColor: () => (/* binding */ areaColor),
/* harmony export */   layerColor: () => (/* binding */ layerColor),
/* harmony export */   layerIcon: () => (/* binding */ layerIcon),
/* harmony export */   layerLabel: () => (/* binding */ layerLabel)
/* harmony export */ });
const LAYER_TYPES = {
  text: {
    label: 'Text',
    icon: 'Aa',
    color: '#0284c7'
  },
  textarea: {
    label: 'Text Area',
    icon: '\u00b6',
    color: '#7c3aed'
  },
  image: {
    label: 'Image',
    icon: '\ud83d\uddbc',
    color: '#059669'
  },
  clipmask: {
    label: 'Clipping Mask',
    icon: '◯',
    color: '#0d9488'
  },
  mask: {
    label: 'Mask',
    icon: '\u25a0',
    color: '#64748b'
  },
  spotify: {
    label: 'Spotify Code',
    icon: '\u266b',
    color: '#1db954'
  },
  lineart: {
    label: 'Line Art',
    icon: '\u270f',
    color: '#d97706'
  },
  clipart: {
    label: 'Clipart',
    icon: '\u2726',
    color: '#dc2626'
  }
};
const LAYER_DEFAULTS = {
  text: {
    w: 300,
    h: 50
  },
  textarea: {
    w: 300,
    h: 120
  },
  image: {
    w: 200,
    h: 200
  },
  clipmask: {
    w: 200,
    h: 200
  },
  mask: {
    w: 200,
    h: 200
  },
  spotify: {
    w: 150,
    h: 150
  },
  lineart: {
    w: 200,
    h: 200
  },
  clipart: {
    w: 150,
    h: 150
  }
};
function layerIcon(type) {
  return (LAYER_TYPES[type] || {}).icon || '?';
}
function layerColor(type) {
  return (LAYER_TYPES[type] || {}).color || '#9ca3af';
}
function layerLabel(type) {
  return (LAYER_TYPES[type] || {}).label || type;
}
const ICO_EYE = '<svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M1 8c0 0 2.5-5 7-5s7 5 7 5-2.5 5-7 5-7-5-7-5z"/><circle cx="8" cy="8" r="2"/></svg>';
const ICO_EYE_OFF = '<svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M1 8c0 0 2.5-5 7-5s7 5 7 5-2.5 5-7 5-7-5-7-5z"/><circle cx="8" cy="8" r="2"/><line x1="2" y1="14" x2="14" y2="2"/></svg>';
const ICO_LOCK = '<svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="7" width="10" height="8" rx="1.5"/><path d="M5.5 7V5a2.5 2.5 0 0 1 5 0v2"/></svg>';
const ICO_UNLOCK = '<svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="7" width="10" height="8" rx="1.5"/><path d="M5.5 7V5a2.5 2.5 0 0 1 5 0"/></svg>';
const ICO_BIN = '<svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><line x1="1.5" y1="4" x2="14.5" y2="4"/><path d="M6 4V2.5a.5.5 0 0 1 .5-.5h3a.5.5 0 0 1 .5.5V4"/><path d="M3 4l.8 9.5a.5.5 0 0 0 .5.5h7.4a.5.5 0 0 0 .5-.5L13 4"/></svg>';
const LAYER_TABS = {
  text: [{
    id: 'general',
    label: 'General',
    icon: 'G'
  }, {
    id: 'content',
    label: 'Content',
    icon: 'T'
  }, {
    id: 'style',
    label: 'Style',
    icon: 'A'
  }, {
    id: 'properties',
    label: 'Properties',
    icon: '\u2699'
  }],
  textarea: [{
    id: 'general',
    label: 'General',
    icon: 'G'
  }, {
    id: 'content',
    label: 'Content',
    icon: 'T'
  }, {
    id: 'style',
    label: 'Style',
    icon: 'A'
  }, {
    id: 'properties',
    label: 'Properties',
    icon: '\u2699'
  }],
  image: [{
    id: 'general',
    label: 'General',
    icon: 'G'
  }, {
    id: 'file',
    label: 'File',
    icon: '\ud83d\uddbc'
  }, {
    id: 'validation',
    label: 'Validation',
    icon: '\u2713'
  }, {
    id: 'properties',
    label: 'Properties',
    icon: '\u2699'
  }],
  clipmask: [{
    id: 'general',
    label: 'General',
    icon: 'G'
  }, {
    id: 'file',
    label: 'File',
    icon: '\ud83d\uddbc'
  }, {
    id: 'mask',
    label: 'Mask',
    icon: '◯'
  }, {
    id: 'validation',
    label: 'Validation',
    icon: '\u2713'
  }],
  mask: [{
    id: 'general',
    label: 'General',
    icon: 'G'
  }],
  spotify: [{
    id: 'general',
    label: 'General',
    icon: 'G'
  }, {
    id: 'appearance',
    label: 'Appearance',
    icon: '\u25d0'
  }, {
    id: 'validation',
    label: 'Validation',
    icon: '\u2713'
  }],
  lineart: [{
    id: 'general',
    label: 'General',
    icon: 'G'
  }, {
    id: 'colours',
    label: 'Colours',
    icon: '\u25cf'
  }, {
    id: 'validation',
    label: 'Validation',
    icon: '\u2713'
  }],
  clipart: [{
    id: 'general',
    label: 'General',
    icon: 'G'
  }, {
    id: 'library',
    label: 'Library',
    icon: '\u2726'
  }, {
    id: 'validation',
    label: 'Validation',
    icon: '\u2713'
  }, {
    id: 'properties',
    label: 'Properties',
    icon: '\u2699'
  }]
};
const AREA_COLORS = ['#4f46e5', '#059669', '#d97706', '#dc2626', '#0284c7', '#7c3aed', '#db2777', '#ea580c'];
function areaColor(index) {
  return AREA_COLORS[index % AREA_COLORS.length];
}

/***/ },

/***/ "./src/admin/products-page-mockup-picker.js"
/*!**************************************************!*\
  !*** ./src/admin/products-page-mockup-picker.js ***!
  \**************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   createMockupPicker: () => (/* binding */ createMockupPicker)
/* harmony export */ });
/* eslint-disable no-alert, no-undef */

function createMockupPicker(deps) {
  const {
    commitChange,
    getSelectedIndex,
    selectedArea
  } = deps;
  let mediaFrame = null;
  function openMockupPicker() {
    if (getSelectedIndex() < 0) {
      return;
    }
    if (!window.wp || !window.wp.media) {
      alert('Media library is not available.');
      return;
    }
    if (!mediaFrame) {
      const data = window.ocProductsData || {};
      mediaFrame = wp.media({
        title: data.mediaTitle || 'Select Mockup Image',
        button: {
          text: data.mediaBtn || 'Use as Mockup'
        },
        library: {
          type: 'image'
        },
        multiple: false
      });
      mediaFrame.on('select', () => {
        const att = mediaFrame.state().get('selection').first().toJSON();
        const area = selectedArea();
        if (area) {
          area.mockupId = att.id;
          area.mockupUrl = att.sizes && att.sizes.large && att.sizes.large.url || att.url;
          commitChange({
            all: true
          });
        }
      });
    }
    mediaFrame.open();
  }
  return openMockupPicker;
}

/***/ },

/***/ "./src/admin/products-page-preview.js"
/*!********************************************!*\
  !*** ./src/admin/products-page-preview.js ***!
  \********************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   createLayerPreviewRenderer: () => (/* binding */ createLayerPreviewRenderer)
/* harmony export */ });
/* eslint-disable no-undef, no-nested-ternary */

function createLayerPreviewRenderer(deps) {
  const {
    fontLimit,
    layerLabel,
    normaliseHex
  } = deps;
  function clampFontSize(size, settings, scale) {
    const min = fontLimit(settings?.min_font_size) * scale;
    const max = fontLimit(settings?.max_font_size) * scale;
    if (max && (!min || min <= max)) {
      size = Math.min(size, max);
    }
    if (min) {
      size = Math.max(size, min);
    }
    return size;
  }
  function fitTextPreview(el, fontSize, minFontSize, singleLine = false) {
    if (!el || !el.clientWidth || !el.clientHeight) {
      return;
    }
    el.style.transform = '';
    let size = fontSize;
    const floor = Math.max(1, minFontSize || 4);
    while (size > floor && (singleLine ? el.scrollHeight > el.clientHeight : el.scrollWidth > el.clientWidth || el.scrollHeight > el.clientHeight)) {
      size = Math.max(floor, size - 1);
      el.style.fontSize = size + 'px';
    }
    if (singleLine && el.scrollWidth > el.clientWidth) {
      el.style.transform = 'scaleX(' + Math.max(0.01, el.clientWidth / el.scrollWidth) + ')';
    }
  }
  function findFont(fontId) {
    const fonts = (window.ocProductsData || {}).fonts || [];
    return fonts.find(f => Number(f.id) === Number(fontId)) || (!fontId ? fonts[0] : null);
  }
  function engravingTextColor() {
    return '#dadad6';
  }
  function applyLayerPreview(layer, el, renderedW, renderedH, isGhost, isEngraving) {
    // Remove any existing preview children
    el.querySelectorAll('.oc-lp').forEach(c => c.remove());
    if (!layer) {
      return;
    }
    const s = layer.settings || {};
    if (layer.type === 'text' || layer.type === 'textarea') {
      const isSingleLine = layer.type === 'text';
      const text = s.default_text || layer.label || layerLabel(layer.type);
      const align = s.alignment || 'center';
      const flexAlign = align === 'left' ? 'flex-start' : align === 'right' ? 'flex-end' : 'center';
      const lineAlign = ['top', 'center', 'bottom'].includes(s.line_alignment) ? s.line_alignment : 'top';
      const flexLineAlign = lineAlign === 'bottom' ? 'flex-end' : lineAlign === 'center' ? 'center' : 'flex-start';
      const scale = renderedH / Math.max(1, layer.h);
      const defaultFontSize = fontLimit(s.default_font_size);
      const autoFontSize = Math.max(8, Math.min(renderedH * (isGhost ? 0.36 : 0.42), isGhost ? 22 : 30));
      const fs = clampFontSize(defaultFontSize ? defaultFontSize * scale : autoFontSize, s, scale);
      const font = findFont(s.default_font_id || 0);
      const d = document.createElement('div');
      d.className = 'oc-lp oc-lp-text';
      d.style.fontSize = fs + 'px';
      d.style.fontWeight = '400';
      d.style.lineHeight = '1.16';
      d.style.whiteSpace = isSingleLine ? 'nowrap' : 'normal';
      d.style.transformOrigin = align === 'left' ? 'left center' : align === 'right' ? 'right center' : 'center center';
      d.style.textAlign = align;
      d.style.alignItems = flexAlign;
      d.style.maxWidth = Math.max(1, renderedW) + 'px';
      d.style.maxHeight = Math.max(1, renderedH) + 'px';
      d.style.color = isEngraving ? engravingTextColor() : normaliseHex(s.default_color);
      if (font) {
        d.style.fontFamily = "'" + String(font.name).replace(/'/g, "\\'") + "', sans-serif";
      }
      if (!isSingleLine) {
        d.style.justifyContent = flexLineAlign;
      }
      d.textContent = text;
      el.appendChild(d);
      const minFontSize = fontLimit(s.min_font_size) ? fontLimit(s.min_font_size) * scale : 4;
      fitTextPreview(d, fs, minFontSize, isSingleLine);
      requestAnimationFrame(() => fitTextPreview(d, fs, minFontSize, isSingleLine));
      document.fonts?.ready?.then?.(() => fitTextPreview(d, parseFloat(d.style.fontSize) || fs, minFontSize, isSingleLine));
    } else if (layer.type === 'image' || layer.type === 'clipmask') {
      if (layer.type === 'image' && s.default_attachment_url) {
        const img = document.createElement('img');
        img.className = 'oc-lp oc-lp-media';
        img.src = s.default_attachment_url;
        img.alt = '';
        el.appendChild(img);
        return;
      }
      const fs = Math.max(14, Math.min(renderedH * 0.35, 40));
      const d = document.createElement('div');
      d.className = 'oc-lp oc-lp-icon';
      d.innerHTML = '<svg width="' + Math.round(fs) + '" height="' + Math.round(fs * 0.8) + '" viewBox="0 0 24 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">' + '<rect x="1" y="4" width="22" height="15" rx="2"/>' + '<circle cx="12" cy="11.5" r="3.5"/>' + '<path d="M8 4l2-3h4l2 3"/>' + '</svg>' + '<span>' + (layer.type === 'clipmask' ? 'Upload Clipped Photo' : 'Upload Image') + '</span>';
      if (layer.type === 'clipmask') {
        d.style.borderRadius = '999px';
      }
      el.appendChild(d);
    } else if (layer.type === 'clipart' && s.default_clipart_url) {
      const img = document.createElement('img');
      img.className = 'oc-lp oc-lp-media';
      img.src = s.default_clipart_url;
      img.alt = '';
      if (isEngraving && s.default_clipart_recolourable) {
        img.style.filter = 'brightness(0) saturate(100%) invert(91%) opacity(0.9)';
      }
      el.appendChild(img);
    } else {
      const icons = {
        mask: '\u25a0',
        spotify: '\u266b',
        lineart: '\u270f',
        clipart: '\u2726'
      };
      const labels = {
        mask: 'Mask',
        spotify: 'Spotify Code',
        lineart: 'Line Art',
        clipart: 'Clipart'
      };
      const fs = Math.max(14, Math.min(renderedH * 0.35, 36));
      const d = document.createElement('div');
      d.className = 'oc-lp oc-lp-icon';
      if (isEngraving && layer.type === 'lineart') {
        d.style.color = engravingTextColor();
      }
      d.innerHTML = '<span style="font-size:' + Math.round(fs) + 'px;">' + (icons[layer.type] || '') + '</span><span>' + (labels[layer.type] || layerLabel(layer.type)) + '</span>';
      el.appendChild(d);
    }
  }
  return applyLayerPreview;
}

/***/ },

/***/ "./src/admin/products-page-settings.js"
/*!*********************************************!*\
  !*** ./src/admin/products-page-settings.js ***!
  \*********************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   createProductsPageSettings: () => (/* binding */ createProductsPageSettings)
/* harmony export */ });
/* eslint-disable no-unused-vars, no-nested-ternary */

function createProductsPageSettings(deps) {
  const {
    commitChange,
    esc,
    fontLimit,
    getAreas,
    layerLabel,
    normaliseHex,
    normaliseLinkGroup,
    renderLayerList,
    selectedArea,
    syncBoundsFromInputs
  } = deps;
  function field(label, inputHtml) {
    return '<div class="oc-editor-field"><label class="oc-settings-label">' + label + '</label>' + inputHtml + '</div>';
  }
  function toggleField(label, id, checked) {
    return '<label class="oc-toggle-label oc-settings-toggle"><span class="oc-toggle"><input type="checkbox" id="' + id + '"' + (checked ? ' checked' : '') + ' /><span class="oc-toggle-slider"></span></span> ' + label + '</label>';
  }
  function clipartDisplayField(current) {
    const value = current === 'carousel' ? 'carousel' : 'grid';
    return '<select id="oc-set-clipart-display" class="oc-input" style="width:100%;">' + '<option value="grid"' + (value === 'grid' ? ' selected' : '') + '>Grid</option>' + '<option value="carousel"' + (value === 'carousel' ? ' selected' : '') + '>One-row scroll with arrows and dots</option>' + '</select>';
  }
  function clipartAllowedForMethod(item, printMethod) {
    const allowed = Array.isArray(item.allowedPrintMethods) ? item.allowedPrintMethods : [];
    return !allowed.length || allowed.includes(printMethod);
  }
  function clipartForSelectedGroups(items, groupIds, printMethod = '') {
    const selected = selectedGroupIds(groupIds);
    const activeItems = (items || []).filter(item => item.active !== false && clipartAllowedForMethod(item, printMethod));
    if (!selected.length) {
      return activeItems;
    }
    return activeItems.filter(item => (item.groupIds || []).some(id => selected.includes(Number(id))));
  }
  function clipartOptions(items, currentId) {
    return '<option value="0">No default clipart</option>' + (items || []).map(item => '<option value="' + esc(item.id) + '"' + (Number(item.id) === Number(currentId || 0) ? ' selected' : '') + '>' + esc(item.name) + '</option>').join('');
  }
  function setDefaultClipart(settings, clipartId, items) {
    const item = (items || []).find(c => Number(c.id) === Number(clipartId || 0));
    settings.default_clipart_id = item ? Number(item.id) : 0;
    settings.default_clipart_url = item ? item.url || '' : '';
    settings.default_clipart_recolourable = item ? item.fileType === 'svg' && item.colourChangeable !== false : false;
  }
  function ensureDefaultClipartInList(settings, items) {
    if (!settings.default_clipart_id) {
      return;
    }
    if ((items || []).some(item => Number(item.id) === Number(settings.default_clipart_id))) {
      return;
    }
    setDefaultClipart(settings, 0, items);
  }
  function mediaDefaultField(settings) {
    const hasDefault = !!settings.default_attachment_url;
    return '<div class="oc-default-media-field">' + '<input type="hidden" id="oc-set-default-attachment-id" value="' + esc(settings.default_attachment_id || 0) + '" />' + '<input type="hidden" id="oc-set-default-attachment-url" value="' + esc(settings.default_attachment_url || '') + '" />' + '<div class="oc-mockup-thumb" style="margin-bottom:8px;">' + '<img id="oc-default-attachment-preview" src="' + esc(settings.default_attachment_url || '') + '" alt="" style="' + (hasDefault ? '' : 'display:none;') + 'max-width:100%;height:auto;" />' + '<span id="oc-default-attachment-empty" style="font-size:12px;color:var(--oc-gray-400);' + (hasDefault ? 'display:none;' : '') + '">No default image set</span>' + '</div>' + '<div style="display:flex;gap:8px;flex-wrap:wrap;">' + '<button type="button" id="oc-choose-default-attachment" class="oc-btn oc-btn-secondary oc-btn-sm">Choose image</button>' + '<button type="button" id="oc-remove-default-attachment" class="oc-btn oc-btn-secondary oc-btn-sm"' + (hasDefault ? '' : ' style="display:none;"') + '>Remove</button>' + '</div>' + '</div>';
  }
  function alignBtns(current) {
    return '<div class="oc-align-btns">' + [['left', '\u2190', 'Left'], ['center', '\u2261', 'Center'], ['right', '\u2192', 'Right']].map(([a, icon, lbl]) => '<button type="button" class="oc-align-btn' + (a === current ? ' oc-align-btn--active' : '') + '" data-align="' + a + '">' + icon + ' ' + lbl + '</button>').join('') + '</div>';
  }
  function lineAlignBtns(current) {
    const value = ['top', 'center', 'bottom'].includes(current) ? current : 'top';
    return '<div class="oc-align-btns">' + [['top', '\u2191', 'Top'], ['center', '\u2195', 'Center'], ['bottom', '\u2193', 'Bottom']].map(([a, icon, lbl]) => '<button type="button" class="oc-line-align-btn' + (a === value ? ' oc-align-btn--active' : '') + '" data-line-align="' + a + '">' + icon + ' ' + lbl + '</button>').join('') + '</div>';
  }
  function groupChecks(cls, groups, selected) {
    if (!groups.length) {
      return '<span class="oc-settings-empty">No groups created yet.</span>';
    }
    return '<div class="oc-group-checks">' + groups.map(g => '<label class="oc-group-check-item"><input type="checkbox" class="' + cls + '" value="' + esc(g.id) + '"' + (selected.indexOf(Number(g.id)) !== -1 ? ' checked' : '') + ' /><span>' + esc(g.name) + '</span></label>').join('') + '</div>';
  }
  function linkGroupOptions(current) {
    const groups = [];
    getAreas().forEach(area => {
      (area.layers || []).forEach(layer => {
        const group = normaliseLinkGroup(layer.settings?.link_group);
        if (group && groups.indexOf(group) === -1) {
          groups.push(group);
        }
      });
    });
    current = normaliseLinkGroup(current);
    if (current && groups.indexOf(current) === -1) {
      groups.push(current);
    }
    return groups.sort((a, b) => a.localeCompare(b));
  }
  function linkGroupField(current) {
    const groups = linkGroupOptions(current);
    current = normaliseLinkGroup(current);
    if (!groups.length) {
      return '<input type="text" id="oc-set-link-group" class="oc-input" style="width:100%;" placeholder="Create a link group, e.g. name" value="" />';
    }
    return '<select id="oc-set-link-group" class="oc-input" style="width:100%;">' + '<option value="">No link group</option>' + groups.map(group => '<option value="' + esc(group) + '"' + (group === current ? ' selected' : '') + '>' + esc(group) + '</option>').join('') + '<option value="__new">Create new link group...</option>' + '</select>' + '<input type="text" id="oc-set-link-group-new" class="oc-input" style="width:100%;margin-top:8px;display:none;" placeholder="New link group name" value="" />';
  }
  function fontOptions(fonts, selected) {
    return '<option value="0">Auto / first available</option>' + fonts.map(f => '<option value="' + esc(f.id) + '"' + (Number(selected) === Number(f.id) ? ' selected' : '') + '>' + esc(f.name) + '</option>').join('');
  }
  function selectedGroupIds(value) {
    return Array.isArray(value) ? value.map(Number).filter(Boolean) : [];
  }
  function membersForGroups(groups, selected, memberKey) {
    if (!selected.length) {
      return [];
    }
    const ids = [];
    groups.forEach(group => {
      if (selected.indexOf(Number(group.id)) === -1) {
        return;
      }
      (group[memberKey] || []).forEach(id => {
        id = Number(id);
        if (id && ids.indexOf(id) === -1) {
          ids.push(id);
        }
      });
    });
    return ids;
  }
  function fontsForSelectedGroups(fonts, groups, selected) {
    if (!selected.length) {
      return fonts;
    }
    const ids = membersForGroups(groups, selected, 'fontIds');
    return ids.map(id => fonts.find(font => Number(font.id) === id)).filter(Boolean);
  }
  function coloursForSelectedGroups(colours, groups, selected) {
    if (!selected.length) {
      return colours;
    }
    const ids = membersForGroups(groups, selected, 'colourIds');
    return ids.map(id => colours.find(colour => Number(colour.id) === id)).filter(Boolean);
  }
  function colourOptions(colours, selected) {
    const selectedHex = normaliseHex(selected).toLowerCase();
    return colours.map(colour => {
      const hex = normaliseHex(colour.hex);
      return '<option value="' + esc(hex) + '"' + (hex.toLowerCase() === selectedHex ? ' selected' : '') + '>' + esc(colour.name) + ' (' + esc(hex) + ')</option>';
    }).join('');
  }
  function ensureDefaultFontInList(settings, fonts) {
    if (!fonts.length) {
      settings.default_font_id = 0;
      return;
    }
    if (fonts.some(font => Number(font.id) === Number(settings.default_font_id || 0))) {
      return;
    }
    settings.default_font_id = Number(fonts[0].id) || 0;
  }
  function ensureDefaultColourInList(settings, colours) {
    if (!colours.length) {
      return;
    }
    const selectedHex = normaliseHex(settings.default_color).toLowerCase();
    if (colours.some(colour => normaliseHex(colour.hex).toLowerCase() === selectedHex)) {
      return;
    }
    settings.default_color = normaliseHex(colours[0].hex);
  }
  function formatChecks(selected) {
    return '<div class="oc-group-checks">' + ['png', 'jpg', 'svg', 'webp', 'pdf', 'eps'].map(fmt => '<label class="oc-group-check-item"><input type="checkbox" class="oc-fmt-check" value="' + fmt + '"' + (selected.indexOf(fmt) !== -1 ? ' checked' : '') + ' /><span>' + fmt.toUpperCase() + '</span></label>').join('') + '</div>';
  }

  // ── Tab content builders ───────────────────────────────────────────────────

  function buildTabContent(tabId, layer) {
    const s = layer.settings;
    const data = window.ocProductsData || {};
    const fGroups = data.fontGroups || [];
    const fonts = data.fonts || [];
    const colours = data.colours || [];
    const cGroups = data.colourGroups || [];
    // Engraving has no colour — don't show colour group pickers for layers in engraving areas.
    const area = selectedArea();
    const printMethod = area?.method || '';
    const aGroups = data.clipartGroups || [];
    const allClipartItems = clipartForSelectedGroups(data.clipartItems || [], [], printMethod);
    const isEngraving = area && area.method === 'engraving';
    const fontGroupIds = selectedGroupIds(s.font_groups);
    const colourGroupIds = selectedGroupIds(s.colour_groups);
    const availableFonts = fontsForSelectedGroups(fonts, fGroups, fontGroupIds);
    const availableColours = coloursForSelectedGroups(colours, cGroups, colourGroupIds);
    if (fontGroupIds.length) {
      ensureDefaultFontInList(s, availableFonts);
    }
    if (!isEngraving && colourGroupIds.length) {
      ensureDefaultColourInList(s, availableColours);
    }
    ensureDefaultClipartInList(s, allClipartItems);
    switch (tabId) {
      case 'general':
        return field('Label', '<input type="text" id="oc-layer-label" class="oc-input" style="width:100%;" value="' + esc(layer.label) + '" />') + field('Link group <span class="oc-hint">(same type layers with the same value mirror customer input)</span>', linkGroupField(s.link_group || '')) + '<p class="oc-settings-section-hdr">Position</p>' + '<div class="oc-bounds-grid">' + '<div class="oc-editor-field"><label class="oc-settings-label">X</label><input type="number" id="oc-layer-x" class="oc-input" min="0" style="width:100%;" value="' + layer.x + '" /></div>' + '<div class="oc-editor-field"><label class="oc-settings-label">Y</label><input type="number" id="oc-layer-y" class="oc-input" min="0" style="width:100%;" value="' + layer.y + '" /></div>' + '<div class="oc-editor-field"><label class="oc-settings-label">W</label><input type="number" id="oc-layer-w" class="oc-input" min="1" style="width:100%;" value="' + layer.w + '" /></div>' + '<div class="oc-editor-field"><label class="oc-settings-label">H</label><input type="number" id="oc-layer-h" class="oc-input" min="1" style="width:100%;" value="' + layer.h + '" /></div>' + '</div>';
      case 'content':
        return field('Default text', '<input type="text" id="oc-set-default-text" class="oc-input" style="width:100%;" placeholder="e.g. Your Name Here" value="' + esc(s.default_text || '') + '" />') + field('Max characters <span class="oc-hint">(0 = unlimited)</span>', '<input type="number" id="oc-set-char-limit" class="oc-input" min="0" style="width:100%;" value="' + esc(s.char_limit || 0) + '" />');
      case 'style':
        return field('Alignment', alignBtns(s.alignment || 'center')) + (layer.type === 'textarea' ? field('Line alignment', lineAlignBtns(s.line_alignment || 'top')) : '') + (availableFonts.length ? field('Default font', '<select id="oc-set-default-font" class="oc-input" style="width:100%;">' + fontOptions(availableFonts, s.default_font_id || 0) + '</select>') : field('Default font', '<span class="oc-settings-empty">' + (fontGroupIds.length ? 'No fonts are available in the selected groups.' : 'No fonts uploaded yet.') + '</span>')) + '<div class="oc-bounds-grid">' + '<div class="oc-editor-field"><label class="oc-settings-label">Default font size <span class="oc-hint">(0 = auto)</span></label><input type="number" id="oc-set-default-font-size" class="oc-input" min="0" style="width:100%;" value="' + esc(s.default_font_size || 0) + '" /></div>' + (isEngraving ? '' : colourGroupIds.length ? availableColours.length ? '<div class="oc-editor-field"><label class="oc-settings-label">Default colour</label><select id="oc-set-default-color" class="oc-input" style="width:100%;">' + colourOptions(availableColours, s.default_color) + '</select></div>' : '<div class="oc-editor-field"><label class="oc-settings-label">Default colour</label><span class="oc-settings-empty">No colours are available in the selected groups.</span></div>' : '<div class="oc-editor-field"><label class="oc-settings-label">Default colour</label><input type="color" id="oc-set-default-color" class="oc-input" style="width:100%;height:38px;" value="' + esc(normaliseHex(s.default_color)) + '" /></div>') + '</div>' + '<div class="oc-bounds-grid">' + '<div class="oc-editor-field"><label class="oc-settings-label">Min font size <span class="oc-hint">(0 = auto)</span></label><input type="number" id="oc-set-min-font-size" class="oc-input" min="0" style="width:100%;" value="' + esc(s.min_font_size || 0) + '" /></div>' + '<div class="oc-editor-field"><label class="oc-settings-label">Max font size <span class="oc-hint">(0 = auto)</span></label><input type="number" id="oc-set-max-font-size" class="oc-input" min="0" style="width:100%;" value="' + esc(s.max_font_size || 0) + '" /></div>' + '</div>' + (fGroups.length ? field('Font groups <span class="oc-hint">(empty = all)</span>', groupChecks('oc-fg-check', fGroups, s.font_groups || [])) : field('Font groups', '<span class="oc-settings-empty">No font groups created yet.</span>')) + (isEngraving ? '' : cGroups.length ? field('Colour groups <span class="oc-hint">(empty = all)</span>', groupChecks('oc-cg-check', cGroups, s.colour_groups || [])) : field('Colour groups', '<span class="oc-settings-empty">No colour groups created yet.</span>'));
      case 'file':
        return field('Default image', mediaDefaultField(s)) + field('Accepted formats', formatChecks(s.formats || ['png', 'jpg', 'svg', 'webp'])) + field('Max file size (MB)', '<input type="number" id="oc-set-max-size" class="oc-input" min="1" style="width:100%;" value="' + esc(s.max_size_mb || 10) + '" />') + toggleField('Automatically remove background', 'oc-set-remove-background', !!s.remove_background);
      case 'mask':
        return field('Mask shape', '<select id="oc-set-mask-shape" class="oc-input" style="width:100%;"><option value="circle"' + ((s.mask_shape || 'circle') === 'circle' ? ' selected' : '') + '>Circle</option></select>');
      case 'appearance':
      case 'colours':
        if (isEngraving) {
          return '<span class="oc-settings-empty">Colour is not applicable for engraving.</span>';
        }
        return cGroups.length ? field('Colour groups <span class="oc-hint">(empty = all)</span>', groupChecks('oc-cg-check', cGroups, s.colour_groups || [])) : '<span class="oc-settings-empty">No colour groups created yet.</span>';
      case 'library':
        return (aGroups.length ? field('Clipart groups <span class="oc-hint">(empty = all)</span>', groupChecks('oc-ag-check', aGroups, s.clipart_groups || [])) : '<span class="oc-settings-empty">No clipart groups created yet.</span>') + field('Default clipart', allClipartItems.length ? '<select id="oc-set-default-clipart" class="oc-input" style="width:100%;">' + clipartOptions(allClipartItems, s.default_clipart_id || 0) + '</select>' : '<span class="oc-settings-empty">No active clipart is available.</span>');
      case 'validation':
        return toggleField('Required field', 'oc-set-required', s.required) + (layer.type === 'clipart' ? field('Frontend display', clipartDisplayField(s.clipart_display || 'grid')) : '');
      case 'properties':
        if (layer.type === 'image') {
          return '<p class="oc-settings-section-hdr">Customer can change</p>' + toggleField('Image', 'oc-set-allow-image-change', s.allow_image_change !== false);
        }
        if (layer.type === 'clipart') {
          return '<p class="oc-settings-section-hdr">Customer can change</p>' + toggleField('Clipart', 'oc-set-allow-clipart-change', s.allow_clipart_change !== false);
        }
        return toggleField('Required field', 'oc-set-required', s.required) + '<p class="oc-settings-section-hdr">Customer can change</p>' + toggleField('Font', 'oc-set-allow-font-change', s.allow_font_change !== false) + (isEngraving ? '' : toggleField('Colour', 'oc-set-allow-colour-change', s.allow_colour_change !== false)) + toggleField('Size', 'oc-set-allow-size-change', !!s.allow_size_change);
      default:
        return '';
    }
  }
  function bindSettingsHandlers(layer) {
    const s = layer.settings;
    const area = selectedArea();
    const data = window.ocProductsData || {};
    document.getElementById('oc-layer-label')?.addEventListener('input', e => {
      layer.label = e.target.value;
      renderLayerList(area);
      commitChange({
        canvas: true
      });
    });
    ['oc-layer-x', 'oc-layer-y', 'oc-layer-w', 'oc-layer-h'].forEach(id => {
      document.getElementById(id)?.addEventListener('input', () => syncBoundsFromInputs(id));
    });
    document.getElementById('oc-set-default-text')?.addEventListener('input', e => {
      s.default_text = e.target.value;
      commitChange({
        canvas: true
      });
    });
    const linkGroupControl = document.getElementById('oc-set-link-group');
    const newLinkGroupControl = document.getElementById('oc-set-link-group-new');
    linkGroupControl?.addEventListener(linkGroupControl.tagName === 'SELECT' ? 'change' : 'input', e => {
      if (e.target.value === '__new') {
        if (newLinkGroupControl) {
          newLinkGroupControl.style.display = '';
          newLinkGroupControl.focus();
          s.link_group = normaliseLinkGroup(newLinkGroupControl.value);
        }
      } else {
        if (newLinkGroupControl) {
          newLinkGroupControl.style.display = 'none';
        }
        s.link_group = normaliseLinkGroup(e.target.value);
      }
      commitChange();
    });
    newLinkGroupControl?.addEventListener('input', e => {
      s.link_group = normaliseLinkGroup(e.target.value);
      commitChange();
    });
    document.getElementById('oc-set-char-limit')?.addEventListener('input', e => {
      s.char_limit = parseInt(e.target.value, 10) || 0;
      commitChange();
    });
    document.getElementById('oc-choose-default-attachment')?.addEventListener('click', () => {
      if (!window.wp || !window.wp.media) {
        return;
      }
      const frame = window.wp.media({
        title: 'Select Default Image',
        button: {
          text: 'Use as Default'
        },
        multiple: false,
        library: {
          type: 'image'
        }
      });
      frame.on('select', () => {
        const attachment = frame.state().get('selection').first()?.toJSON();
        if (!attachment) {
          return;
        }
        s.default_attachment_id = Number(attachment.id) || 0;
        s.default_attachment_url = attachment.sizes?.medium?.url || attachment.url || '';
        commitChange({
          canvas: true,
          rightColumn: true
        });
      });
      frame.open();
    });
    document.getElementById('oc-remove-default-attachment')?.addEventListener('click', () => {
      s.default_attachment_id = 0;
      s.default_attachment_url = '';
      commitChange({
        canvas: true,
        rightColumn: true
      });
    });
    document.getElementById('oc-set-default-font')?.addEventListener('change', e => {
      s.default_font_id = parseInt(e.target.value, 10) || 0;
      commitChange({
        canvas: true
      });
    });
    document.getElementById('oc-set-default-font-size')?.addEventListener('input', e => {
      s.default_font_size = fontLimit(e.target.value);
      commitChange({
        canvas: true
      });
    });
    document.getElementById('oc-set-default-color')?.addEventListener('change', e => {
      s.default_color = normaliseHex(e.target.value);
      commitChange({
        canvas: true
      });
    });
    document.getElementById('oc-set-default-color')?.addEventListener('input', e => {
      s.default_color = normaliseHex(e.target.value);
      commitChange({
        canvas: true
      });
    });
    document.getElementById('oc-set-min-font-size')?.addEventListener('input', e => {
      s.min_font_size = fontLimit(e.target.value);
      commitChange({
        canvas: true
      });
    });
    document.getElementById('oc-set-max-font-size')?.addEventListener('input', e => {
      s.max_font_size = fontLimit(e.target.value);
      commitChange({
        canvas: true
      });
    });
    document.querySelectorAll('.oc-align-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        s.alignment = btn.dataset.align;
        document.querySelectorAll('.oc-align-btn').forEach(b => b.classList.toggle('oc-align-btn--active', b.dataset.align === btn.dataset.align));
        commitChange({
          canvas: true
        });
      });
    });
    document.querySelectorAll('.oc-line-align-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        s.line_alignment = btn.dataset.lineAlign || 'top';
        document.querySelectorAll('.oc-line-align-btn').forEach(b => b.classList.toggle('oc-align-btn--active', b.dataset.lineAlign === btn.dataset.lineAlign));
        commitChange({
          canvas: true
        });
      });
    });
    document.querySelectorAll('.oc-fg-check').forEach(cb => {
      cb.addEventListener('change', () => {
        s.font_groups = [...document.querySelectorAll('.oc-fg-check:checked')].map(c => Number(c.value));
        const selected = selectedGroupIds(s.font_groups);
        if (selected.length) {
          ensureDefaultFontInList(s, fontsForSelectedGroups(data.fonts || [], data.fontGroups || [], selected));
        }
        commitChange({
          canvas: true,
          rightColumn: true
        });
      });
    });
    document.querySelectorAll('.oc-cg-check').forEach(cb => {
      cb.addEventListener('change', () => {
        s.colour_groups = [...document.querySelectorAll('.oc-cg-check:checked')].map(c => Number(c.value));
        const selected = selectedGroupIds(s.colour_groups);
        if (selected.length) {
          ensureDefaultColourInList(s, coloursForSelectedGroups(data.colours || [], data.colourGroups || [], selected));
        }
        commitChange({
          canvas: true,
          rightColumn: true
        });
      });
    });
    document.querySelectorAll('.oc-ag-check').forEach(cb => {
      cb.addEventListener('change', () => {
        s.clipart_groups = [...document.querySelectorAll('.oc-ag-check:checked')].map(c => Number(c.value));
        ensureDefaultClipartInList(s, clipartForSelectedGroups(data.clipartItems || [], s.clipart_groups, area?.method || ''));
        commitChange({
          rightColumn: true
        });
      });
    });
    document.getElementById('oc-set-default-clipart')?.addEventListener('change', e => {
      setDefaultClipart(s, e.target.value, clipartForSelectedGroups(data.clipartItems || [], [], area?.method || ''));
      commitChange({
        canvas: true
      });
    });
    document.querySelectorAll('.oc-fmt-check').forEach(cb => {
      cb.addEventListener('change', () => {
        s.formats = [...document.querySelectorAll('.oc-fmt-check:checked')].map(c => c.value);
        commitChange();
      });
    });
    document.getElementById('oc-set-max-size')?.addEventListener('input', e => {
      s.max_size_mb = parseInt(e.target.value, 10) || 10;
      commitChange();
    });
    document.getElementById('oc-set-remove-background')?.addEventListener('change', e => {
      s.remove_background = e.target.checked;
      commitChange();
    });
    document.getElementById('oc-set-mask-shape')?.addEventListener('change', e => {
      s.mask_shape = e.target.value || 'circle';
      commitChange({
        canvas: true
      });
    });
    document.getElementById('oc-set-required')?.addEventListener('change', e => {
      s.required = e.target.checked;
      commitChange();
    });
    document.getElementById('oc-set-clipart-display')?.addEventListener('change', e => {
      s.clipart_display = e.target.value === 'carousel' ? 'carousel' : 'grid';
      commitChange();
    });
    document.getElementById('oc-set-allow-font-change')?.addEventListener('change', e => {
      s.allow_font_change = e.target.checked;
      commitChange();
    });
    document.getElementById('oc-set-allow-colour-change')?.addEventListener('change', e => {
      s.allow_colour_change = e.target.checked;
      commitChange();
    });
    document.getElementById('oc-set-allow-size-change')?.addEventListener('change', e => {
      s.allow_size_change = e.target.checked;
      commitChange();
    });
    document.getElementById('oc-set-allow-image-change')?.addEventListener('change', e => {
      s.allow_image_change = e.target.checked;
      commitChange();
    });
    document.getElementById('oc-set-allow-clipart-change')?.addEventListener('change', e => {
      s.allow_clipart_change = e.target.checked;
      commitChange();
    });
  }
  return {
    buildTabContent,
    bindSettingsHandlers
  };
}

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
/* harmony export */   unitPxScale: () => (/* binding */ unitPxScale)
/* harmony export */ });
const VALID_UNITS = ['px', 'mm', 'cm', 'in'];
function normaliseUnit(value) {
  return VALID_UNITS.includes(value) ? value : 'px';
}
function normaliseDpi(value) {
  return Math.min(1200, Math.max(1, Math.round(Number(value) || 300)));
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
function displayFontSize(fontSize, areaOrBounds, canvasScale = 1) {
  return Math.max(1, Number(fontSize) || 0) * unitPxScale(areaOrBounds) * canvasScale;
}

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
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
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
/************************************************************************/
var __webpack_exports__ = {};
// This entry needs to be wrapped in an IIFE because it needs to be isolated against other modules in the chunk.
(() => {
/*!************************************!*\
  !*** ./src/admin/products-page.js ***!
  \************************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _products_page_core__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./products-page-core */ "./src/admin/products-page-core.js");
/**
 * Admin product editor bundle entry.
 *
 * The implementation lives in products-page-core.js so this entry stays small
 * while preserving the existing webpack entry name and WordPress script handle.
 */


})();

/******/ })()
;