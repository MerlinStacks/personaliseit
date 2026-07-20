/******/ (() => { // webpackBootstrap
/******/ 	var __webpack_modules__ = ({});
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
/******/ 			return "" + chunkId + ".js?ver=" + {"vendors-node_modules_fonteditor-core_lib_main_esm_js":"cbd31090ffbcf199aeb7","node_modules_fonteditor-core_woff2_sync_recursive":"10451c248f4cbe6e2119"}[chunkId] + "";
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
/******/ 			"admin/font-manager": 0
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
/*!***********************************!*\
  !*** ./src/admin/font-manager.js ***!
  \***********************************/
/* eslint-disable no-console, no-alert, no-undef, no-unused-vars, @wordpress/no-unused-vars-before-return, jsdoc/require-param-type */

/**
 * Admin — Font Manager JS.
 *
 * Features:
 * - Upload modal with drag-and-drop + file browse
 * - Auto-detects font family name, weight and style from TTF/OTF name table
 * - AJAX upload (no page reload)
 * - Card click → family detail panel with rename (AJAX) and variant list
 * - "Add weight / style" from detail panel pre-fills the upload modal
 */

(function () {
  'use strict';

  // ── State ──────────────────────────────────────────────────────────────────
  let fonts = window.ocFontsData || [];
  const nonce = window.ocFontNonce || '';
  const ajaxUrl = window.ocAjaxUrl || '';
  let currentFile = null;
  let currentFontFace = null;
  let fileSelectionToken = null;
  let detailFontName = null; // Family name currently shown in detail panel
  let nameLocked = false; // True when opened from "Add weight" flow

  const PREVIEW_FAMILY = 'oc-admin-upload-preview';

  // ── DOM refs ───────────────────────────────────────────────────────────────
  const uploadModal = document.getElementById('oc-upload-modal');
  const uploadFontBtn = document.getElementById('oc-upload-font-btn');
  const modalCloseBtn = document.getElementById('oc-modal-close-btn');
  const dropZone = document.getElementById('oc-drop-zone');
  const fileInput = document.getElementById('oc_font_file');
  const step1 = document.getElementById('oc-upload-step-1');
  const step2 = document.getElementById('oc-upload-step-2');
  const modalFooter = document.getElementById('oc-modal-footer-step2');
  const backBtn = document.getElementById('oc-upload-back-btn');
  const submitBtn = document.getElementById('oc-upload-submit-btn');
  const previewText = document.getElementById('oc-upload-preview-text');
  const nameInput = document.getElementById('oc_font_name');
  const weightSel = document.getElementById('oc_font_weight');
  const styleSel = document.getElementById('oc_font_style');
  const embroCheck = document.getElementById('oc_font_embroidery');
  const uploadError = document.getElementById('oc-upload-error');
  const fontGrid = document.getElementById('oc-font-grid');
  const loadMoreFontsBtn = document.getElementById('oc-font-load-more');
  const fontsEmpty = document.getElementById('oc-fonts-empty');
  const fontsCount = document.getElementById('oc-fonts-count');
  const detailPanel = document.getElementById('oc-font-detail');
  const detailNameInput = document.getElementById('oc-detail-name');
  const detailSaveBtn = document.getElementById('oc-detail-save-name');
  const detailCloseBtn = document.getElementById('oc-detail-close');
  const addWeightBtn = document.getElementById('oc-add-weight-btn');
  const detailVariants = document.getElementById('oc-detail-variants');
  if (!uploadModal) {
    return;
  } // Safety — not on the fonts page.

  // ── TTF / OTF name-table parser ────────────────────────────────────────────

  /**
   * Parse the 'name' table from a raw TTF/OTF ArrayBuffer.
   * Returns an object keyed by nameID, or null if the file is not a parseable sfnt.
   * @param buffer
   */
  function parseFontNames(buffer) {
    const view = new DataView(buffer);
    if (buffer.byteLength < 12) {
      return null;
    }
    const sig = view.getUint32(0);
    // Valid sfnt signatures: TrueType (0x00010000), 'true' (0x74727565), CFF/OTF ('OTTO' 0x4f54544f)
    if (sig !== 0x00010000 && sig !== 0x74727565 && sig !== 0x4f54544f) {
      return null;
    }
    const numTables = view.getUint16(4);
    let nameOffset = 0;
    for (let i = 0; i < numTables; i++) {
      const base = 12 + i * 16;
      const tag = String.fromCharCode(view.getUint8(base), view.getUint8(base + 1), view.getUint8(base + 2), view.getUint8(base + 3));
      if (tag === 'name') {
        nameOffset = view.getUint32(base + 8);
        break;
      }
    }
    if (!nameOffset) {
      return null;
    }
    const count = view.getUint16(nameOffset + 2);
    const strBase = nameOffset + view.getUint16(nameOffset + 4);
    const names = {};
    for (let i = 0; i < count; i++) {
      const r = nameOffset + 6 + i * 12;
      const platformID = view.getUint16(r);
      const encodingID = view.getUint16(r + 2);
      const langID = view.getUint16(r + 4);
      const nameID = view.getUint16(r + 6);
      const length = view.getUint16(r + 8);
      const offset = view.getUint16(r + 10);
      if (nameID > 17) {
        continue;
      } // Only standard IDs

      const isWin = platformID === 3 && encodingID === 1 && langID === 0x0409;
      const isMac = platformID === 1 && encodingID === 0 && langID === 0;
      if (!isWin && !isMac) {
        continue;
      }
      if (names[nameID] && !isWin) {
        continue;
      } // Prefer Windows records

      let str = '';
      const start = strBase + offset;
      if (isWin) {
        for (let j = 0; j < length; j += 2) {
          str += String.fromCharCode(view.getUint16(start + j));
        }
      } else {
        for (let j = 0; j < length; j++) {
          str += String.fromCharCode(view.getUint8(start + j));
        }
      }
      names[nameID] = str;
    }
    return names;
  }

  /**
   * Map an sfnt subfamily string (e.g. "Bold Italic") to CSS weight + style.
   * @param subfamily
   */
  function subfamilyToProps(subfamily) {
    const s = (subfamily || 'Regular').toLowerCase();
    let weight = 'normal';
    if (s.includes('thin') || s.includes('hairline')) {
      weight = '100';
    } else if (s.includes('extralight') || s.includes('extra light') || s.includes('ultralight') || s.includes('ultra light')) {
      weight = '200';
    } else if (s.includes('light')) {
      weight = '300';
    } else if (s.includes('semibold') || s.includes('semi bold') || s.includes('demibold')) {
      weight = '600';
    } else if (s.includes('extrabold') || s.includes('extra bold') || s.includes('ultrabold') || s.includes('ultra bold')) {
      weight = '800';
    } else if (s.includes('black') || s.includes('heavy')) {
      weight = '900';
    } else if (s.includes('bold')) {
      weight = 'bold';
    } else if (s.includes('medium')) {
      weight = '500';
    }
    const style = s.includes('italic') || s.includes('oblique') ? 'italic' : 'normal';
    return {
      weight,
      style
    };
  }

  // ── Upload modal ───────────────────────────────────────────────────────────

  function openUploadModal(prefillName) {
    nameLocked = !!prefillName;
    resetModal();
    if (prefillName) {
      nameInput.value = prefillName;
      nameInput.readOnly = true;
      nameInput.style.opacity = '.65';
    }
    uploadModal.hidden = false;
    document.body.style.overflow = 'hidden';
  }
  function closeUploadModal() {
    uploadModal.hidden = true;
    document.body.style.overflow = '';
    resetModal();
  }
  function removeUploadPreviewFont() {
    document.fonts.forEach(function (font) {
      if (font.family === PREVIEW_FAMILY) {
        document.fonts.delete(font);
      }
    });
  }
  function resetModal() {
    fileSelectionToken = null;
    currentFile = null;
    currentFontFace = null;
    removeUploadPreviewFont();
    step1.style.display = '';
    step2.style.display = 'none';
    modalFooter.style.display = 'none';
    uploadError.style.display = 'none';
    uploadError.textContent = '';
    nameInput.value = '';
    nameInput.readOnly = false;
    nameInput.style.opacity = '';
    weightSel.value = 'normal';
    styleSel.value = 'normal';
    if (embroCheck) {
      embroCheck.checked = false;
    }
    previewText.textContent = 'AaBbCc 123';
    previewText.style.fontFamily = '';
    previewText.style.fontWeight = '';
    previewText.style.fontStyle = '';
    submitBtn.disabled = false;
    submitBtn.textContent = submitBtn.dataset.label || submitBtn.textContent;
    dropZone.classList.remove('oc-drop-over');
    fileInput.value = '';
  }
  function showStep2() {
    step1.style.display = 'none';
    step2.style.display = '';
    modalFooter.style.display = 'none'; // Show after preview
    modalFooter.style.display = '';
  }

  // ── File handling ──────────────────────────────────────────────────────────

  async function handleFileSelected(file) {
    if (!file) {
      return;
    }
    const token = {
      file
    };
    fileSelectionToken = token;
    currentFile = file;
    currentFontFace = null;
    removeUploadPreviewFont();
    previewText.style.fontFamily = '';
    const isCurrentSelection = () => fileSelectionToken === token && currentFile === token.file;
    let buffer;
    let names;
    try {
      buffer = await file.arrayBuffer();
      if (!isCurrentSelection()) {
        return;
      }
      names = parseFontNames(buffer);
    } catch (e) {
      if (!isCurrentSelection()) {
        return;
      }
      console.warn('[OC] Font file read failed:', e);
      currentFile = null;
      uploadError.textContent = 'Could not read that font file. Please try another.';
      uploadError.style.display = '';
      return;
    }
    if (names && !nameLocked) {
      const family = names[16] || names[1] || filenameToTitle(file.name);
      const subfamily = names[17] || names[2] || 'Regular';
      const {
        weight,
        style
      } = subfamilyToProps(subfamily);
      nameInput.value = family;
      weightSel.value = weight;
      styleSel.value = style;
    } else if (!nameLocked && !nameInput.value.trim()) {
      nameInput.value = filenameToTitle(file.name);
    }

    // Live preview via FontFace API.
    try {
      const ff = new FontFace(PREVIEW_FAMILY, buffer, {
        weight: weightSel.value,
        style: styleSel.value
      });
      await ff.load();
      if (!isCurrentSelection()) {
        return;
      }
      removeUploadPreviewFont();
      document.fonts.add(ff);
      currentFontFace = ff;
      applyPreviewStyle();
    } catch (e) {
      if (!isCurrentSelection()) {
        return;
      }
      console.warn('[OC] Font preview failed:', e);
      previewText.style.fontFamily = '';
      uploadError.textContent = 'Preview unavailable for this font. You can still upload it.';
      uploadError.style.display = '';
    }
    showStep2();
  }
  function applyPreviewStyle() {
    previewText.style.fontFamily = `"${PREVIEW_FAMILY}", sans-serif`;
    previewText.style.fontWeight = weightSel.value;
    previewText.style.fontStyle = styleSel.value;
  }

  // ── AJAX upload ────────────────────────────────────────────────────────────

  async function submitUpload() {
    if (!currentFile) {
      return;
    }
    if (!nameInput.value.trim()) {
      nameInput.focus();
      return;
    }
    const label = submitBtn.textContent;
    submitBtn.disabled = true;
    submitBtn.textContent = 'Uploading…';
    uploadError.style.display = 'none';
    const fd = new FormData();
    fd.append('action', 'oc_font_upload');
    fd.append('nonce', nonce);
    fd.append('oc_font_file', currentFile, currentFile.name);
    fd.append('oc_font_name', nameInput.value.trim());
    fd.append('oc_font_weight', weightSel.value);
    fd.append('oc_font_style', styleSel.value);
    if (embroCheck && embroCheck.checked) {
      fd.append('oc_font_embroidery', '1');
    }
    try {
      const res = await fetch(ajaxUrl, {
        method: 'POST',
        body: fd
      });
      if (!res.ok) {
        throw new Error(`HTTP ${res.status}`);
      }
      const text = await res.text();
      let json;
      try {
        json = JSON.parse(text);
      } catch (err) {
        throw new Error(text || 'Invalid server response.');
      }
      if (!json.success) {
        showUploadError(json.data?.message || 'Upload failed.');
        return;
      }
      const font = json.data;
      fonts.push(font);
      addCardToGrid(font);
      updateFontsCount();
      closeUploadModal();

      // If detail panel is open for this family, refresh it.
      if (detailFontName === font.name) {
        renderDetailVariants(font.name);
      }
    } catch (err) {
      showUploadError(err?.message || 'Network error — please try again.');
    } finally {
      submitBtn.disabled = false;
      submitBtn.textContent = label;
    }
  }
  function showUploadError(msg) {
    uploadError.textContent = msg;
    uploadError.style.display = '';
  }

  // ── Card rendering ─────────────────────────────────────────────────────────

  function injectFontFace(font) {
    const styleId = `oc-ff-${font.id}`;
    if (document.getElementById(styleId)) {
      return;
    }
    const s = document.createElement('style');
    s.id = styleId;
    s.textContent = `@font-face { font-family: 'oc-preview-${font.id}'; src: url('${font.url}'); font-weight: ${font.weight}; font-style: ${font.style}; }`;
    document.head.appendChild(s);
  }
  function buildCardEl(font) {
    injectFontFace(font);
    const family = `oc-preview-${font.id}`;
    const el = document.createElement('div');
    el.className = 'oc-font-card' + (font.active ? '' : ' oc-font-card--inactive');
    el.dataset.fontId = font.id;
    el.dataset.fontName = font.name;
    el.setAttribute('role', 'button');
    el.setAttribute('tabindex', '0');
    el.innerHTML = `
			<div class="oc-font-preview">
				<span class="oc-font-preview-text" style="font-family:'${family}';font-weight:${font.weight};font-style:${font.style};">AaBbCc 123</span>
			</div>
			<div class="oc-font-card-body">
				<div class="oc-font-card-title-row">
					<p class="oc-font-card-name" title="${h(font.name)}">${h(font.name)}</p>
					<span class="oc-badge ${font.active ? 'oc-badge-active' : 'oc-badge-inactive'}">${font.active ? 'Active' : 'Inactive'}</span>
				</div>
				<div class="oc-font-card-meta">
					<span class="oc-badge oc-badge-format">${h(font.ext)}</span>
					<span class="oc-code">${h(font.weight)}</span>
					${font.style === 'italic' ? '<span class="oc-badge oc-badge-inactive">Italic</span>' : ''}
					${font.embroidery_suitable ? '<span class="oc-badge oc-badge-active">Embroidery</span>' : ''}
				</div>
				<div class="oc-font-card-actions">
					${font.canPrintConvert ? `<button type="button" class="oc-btn oc-btn-secondary oc-btn-sm oc-font-convert-btn" data-font-id="${font.id}">Convert for print</button>` : ''}
					<a href="${font.toggleUrl}" class="oc-btn oc-btn-secondary oc-btn-sm">${font.active ? 'Deactivate' : 'Activate'}</a>
					<a href="${font.deleteUrl}" onclick="return confirm('Delete this font?');" class="oc-btn oc-btn-danger oc-btn-sm">Delete</a>
				</div>
			</div>
		`;
    return el;
  }
  function addCardToGrid(font) {
    if (fontsEmpty) {
      fontsEmpty.style.display = 'none';
    }
    fontGrid.style.display = '';
    const card = buildCardEl(font);
    fontGrid.appendChild(card);
    bindCardClick(card);
  }
  function updateFontsCount() {
    if (!fontsCount) {
      return;
    }
    const n = fonts.length;
    fontsCount.textContent = `${n} ${n === 1 ? 'font' : 'fonts'}`;
  }

  // ── Detail panel ───────────────────────────────────────────────────────────

  function openDetailPanel(fontId, familyName) {
    // Mark the active card.
    document.querySelectorAll('.oc-font-card--active-detail').forEach(c => c.classList.remove('oc-font-card--active-detail'));
    const activeCard = document.querySelector(`.oc-font-card[data-font-id="${fontId}"]`);
    if (activeCard) {
      activeCard.classList.add('oc-font-card--active-detail');
    }
    detailFontName = familyName;
    detailNameInput.value = familyName;
    renderDetailVariants(familyName);
    detailPanel.hidden = false;
    document.body.style.overflow = 'hidden';
  }
  function closeDetailPanel() {
    document.querySelectorAll('.oc-font-card--active-detail').forEach(c => c.classList.remove('oc-font-card--active-detail'));
    detailPanel.hidden = true;
    document.body.style.overflow = '';
    detailFontName = null;
  }
  function renderDetailVariants(familyName) {
    const variants = fonts.filter(f => f.name === familyName);
    if (!variants.length) {
      detailVariants.innerHTML = '<p style="padding:16px 20px;color:var(--oc-gray-400);font-size:13px;">No variants found.</p>';
      return;
    }
    detailVariants.innerHTML = variants.map(f => {
      injectFontFace(f);
      const family = `oc-preview-${f.id}`;
      return `
				<div class="oc-detail-variant" data-font-id="${f.id}">
					<div class="oc-detail-variant-preview" style="font-family:'${family}';font-weight:${f.weight};font-style:${f.style};">AaBbCc 123</div>
					<div class="oc-detail-variant-meta">
						<span class="oc-badge oc-badge-format">${h(f.ext)}</span>
						<span class="oc-code">${h(f.weight)}</span>
						${f.style === 'italic' ? '<span class="oc-badge oc-badge-inactive">Italic</span>' : ''}
						${f.embroidery_suitable ? '<span class="oc-badge oc-badge-active">Embroidery</span>' : ''}
						<span class="oc-badge ${f.active ? 'oc-badge-active' : 'oc-badge-inactive'}">${f.active ? 'Active' : 'Inactive'}</span>
					</div>
					<div class="oc-detail-variant-actions">
						${f.canPrintConvert ? `<button type="button" class="oc-btn oc-btn-secondary oc-btn-sm oc-font-convert-btn" data-font-id="${f.id}">Convert for print</button>` : ''}
						<a href="${f.toggleUrl}" class="oc-btn oc-btn-secondary oc-btn-sm">${f.active ? 'Deactivate' : 'Activate'}</a>
						<a href="${f.deleteUrl}" onclick="return confirm('Delete this font?');" class="oc-btn oc-btn-danger oc-btn-sm">Delete</a>
					</div>
				</div>
			`;
    }).join('');
  }
  async function convertFontForPrint(fontId, button) {
    const font = fonts.find(f => Number(f.id) === Number(fontId));
    if (!font) {
      return;
    }
    const label = button?.textContent || 'Convert for print';
    if (button) {
      button.disabled = true;
      button.textContent = 'Converting...';
    }
    try {
      const fd = new FormData();
      fd.append('action', 'oc_font_convert');
      fd.append('nonce', nonce);
      fd.append('id', fontId);
      const res = await fetch(ajaxUrl, {
        method: 'POST',
        body: fd
      });
      if (!res.ok) {
        throw new Error(`HTTP ${res.status}`);
      }
      const json = await res.json();
      if (!json.success) {
        if (['OTF', 'WOFF'].includes(String(font.ext || '').toUpperCase())) {
          await convertFontInBrowser(font);
          return;
        }
        window.alert(json.data?.message || 'Font conversion failed.');
        return;
      }
      applyConvertedFont(json.data);
    } catch (err) {
      window.alert(err?.message || 'Network error — please try again.');
    } finally {
      if (button) {
        button.disabled = false;
        button.textContent = label;
      }
    }
  }
  async function convertFontInBrowser(font) {
    const response = await fetch(font.url, {
      credentials: 'same-origin',
      cache: 'no-store'
    });
    if (!response.ok) {
      throw new Error(`Could not load font file (${response.status}).`);
    }
    const buffer = await response.arrayBuffer();
    const sourceType = fontSourceType(font, buffer);
    const {
      createFont
    } = await Promise.all(/*! import() */[__webpack_require__.e("vendors-node_modules_fonteditor-core_lib_main_esm_js"), __webpack_require__.e("node_modules_fonteditor-core_woff2_sync_recursive")]).then(__webpack_require__.bind(__webpack_require__, /*! fonteditor-core */ "./node_modules/fonteditor-core/lib/main.esm.js"));
    const source = createFont(buffer, {
      type: sourceType,
      compound2simple: true
    });
    const converted = source.write({
      type: 'ttf'
    });
    const blob = new Blob([converted], {
      type: 'font/ttf'
    });
    const fd = new FormData();
    fd.append('action', 'oc_font_replace_print');
    fd.append('nonce', nonce);
    fd.append('id', font.id);
    fd.append('oc_font_file', blob, `${safeFilename(font.name) || 'font'}-print.ttf`);
    const res = await fetch(ajaxUrl, {
      method: 'POST',
      body: fd
    });
    if (!res.ok) {
      throw new Error(`HTTP ${res.status}`);
    }
    const json = await res.json();
    if (!json.success) {
      throw new Error(json.data?.message || 'Browser conversion failed.');
    }
    applyConvertedFont(json.data);
  }
  function fontSourceType(font, buffer) {
    const ext = String(font.ext || '').toLowerCase();
    if (ext !== 'otf' || buffer.byteLength < 4) {
      return ext;
    }
    const signature = new Uint8Array(buffer, 0, 4);
    return signature[0] === 0x4f && signature[1] === 0x54 && signature[2] === 0x54 && signature[3] === 0x4f ? 'otf' : 'ttf';
  }
  function applyConvertedFont(updated) {
    fonts = fonts.map(f => Number(f.id) === Number(updated.id) ? updated : f);
    replaceFontCard(updated);
    if (detailFontName === updated.name) {
      renderDetailVariants(updated.name);
    }
    window.alert('Font converted for print. Existing designs will keep using this font.');
  }
  function safeFilename(value) {
    return String(value || '').toLowerCase().replace(/[^a-z0-9_-]+/g, '-').replace(/^-+|-+$/g, '');
  }
  function replaceFontCard(font) {
    const existing = document.querySelector(`.oc-font-card[data-font-id="${font.id}"]`);
    if (!existing) {
      return;
    }
    const replacement = buildCardEl(font);
    existing.replaceWith(replacement);
    bindCardClick(replacement);
  }

  // ── AJAX rename ────────────────────────────────────────────────────────────

  async function saveRename() {
    const newName = detailNameInput.value.trim();
    if (!newName || newName === detailFontName) {
      return;
    }

    // Get any font ID from this family to pass to the server.
    const sample = fonts.find(f => f.name === detailFontName);
    if (!sample) {
      return;
    }
    detailSaveBtn.disabled = true;
    detailSaveBtn.textContent = 'Saving…';
    try {
      const fd = new FormData();
      fd.append('action', 'oc_font_rename');
      fd.append('nonce', nonce);
      fd.append('id', sample.id);
      fd.append('name', newName);
      const res = await fetch(ajaxUrl, {
        method: 'POST',
        body: fd
      });
      if (!res.ok) {
        throw new Error(`HTTP ${res.status}`);
      }
      const json = await res.json();
      if (!json.success) {
        alert(json.data?.message || 'Rename failed.');
        return;
      }

      // Update local state.
      const oldName = json.data.oldName;
      fonts.forEach(f => {
        if (f.name === oldName) {
          f.name = newName;
        }
      });

      // Update all matching cards in the DOM.
      document.querySelectorAll(`.oc-font-card[data-font-name="${CSS.escape(oldName)}"]`).forEach(card => {
        card.dataset.fontName = newName;
        const nameEl = card.querySelector('.oc-font-card-name');
        if (nameEl) {
          nameEl.textContent = newName;
          nameEl.title = newName;
        }
      });
      detailFontName = newName;
    } catch (err) {
      alert(err?.message || 'Network error — please try again.');
    } finally {
      detailSaveBtn.disabled = false;
      detailSaveBtn.textContent = 'Rename family';
    }
  }

  // ── Event: card clicks ─────────────────────────────────────────────────────

  function bindCardClick(card) {
    card.addEventListener('click', function (e) {
      // Don't intercept clicks on action links.
      if (e.target.closest('a') || e.target.closest('button')) {
        return;
      }
      openDetailPanel(this.dataset.fontId, this.dataset.fontName);
    });
    card.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        openDetailPanel(this.dataset.fontId, this.dataset.fontName);
      }
    });
  }
  document.querySelectorAll('.oc-font-card').forEach(bindCardClick);
  if (loadMoreFontsBtn) {
    loadMoreFontsBtn.addEventListener('click', function () {
      const offset = Number(this.dataset.offset || 0);
      const step = Number(this.dataset.step || 60);
      fonts.slice(offset, offset + step).forEach(addCardToGrid);
      const nextOffset = offset + step;
      this.dataset.offset = String(nextOffset);
      if (nextOffset >= fonts.length) {
        this.parentElement?.remove();
      }
    });
  }
  document.addEventListener('click', function (e) {
    const btn = e.target.closest('.oc-font-convert-btn');
    if (!btn) {
      return;
    }
    e.preventDefault();
    e.stopPropagation();
    convertFontForPrint(btn.dataset.fontId, btn);
  });

  // ── Event: upload button ───────────────────────────────────────────────────

  uploadFontBtn.addEventListener('click', function () {
    openUploadModal(null);
  });

  // ── Event: modal close ─────────────────────────────────────────────────────

  modalCloseBtn.addEventListener('click', closeUploadModal);
  uploadModal.addEventListener('click', function (e) {
    if (e.target === uploadModal) {
      closeUploadModal();
    }
  });

  // ── Event: drop zone ───────────────────────────────────────────────────────

  dropZone.addEventListener('click', function () {
    fileInput.click();
  });
  dropZone.addEventListener('dragover', function (e) {
    e.preventDefault();
    dropZone.classList.add('oc-drop-over');
  });
  dropZone.addEventListener('dragleave', function () {
    dropZone.classList.remove('oc-drop-over');
  });
  dropZone.addEventListener('drop', function (e) {
    e.preventDefault();
    dropZone.classList.remove('oc-drop-over');
    const file = e.dataTransfer?.files[0];
    if (file) {
      handleFileSelected(file);
    }
  });
  fileInput.addEventListener('change', function () {
    if (this.files[0]) {
      handleFileSelected(this.files[0]);
    }
  });

  // ── Event: upload step 2 controls ─────────────────────────────────────────

  backBtn.addEventListener('click', function () {
    fileSelectionToken = null;
    step1.style.display = '';
    step2.style.display = 'none';
    modalFooter.style.display = 'none';
    currentFile = null;
    currentFontFace = null;
    removeUploadPreviewFont();
    previewText.style.fontFamily = '';
    fileInput.value = '';
  });
  submitBtn.dataset.label = submitBtn.textContent;
  submitBtn.addEventListener('click', submitUpload);

  // Live preview weight/style update.
  [weightSel, styleSel].forEach(function (sel) {
    if (sel) {
      sel.addEventListener('change', function () {
        if (currentFontFace) {
          applyPreviewStyle();
        }
      });
    }
  });

  // ── Event: detail panel controls ───────────────────────────────────────────

  detailCloseBtn.addEventListener('click', closeDetailPanel);
  detailPanel.addEventListener('click', function (e) {
    if (e.target === detailPanel) {
      closeDetailPanel();
    }
  });
  detailSaveBtn.addEventListener('click', saveRename);
  detailNameInput.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') {
      saveRename();
    }
  });
  addWeightBtn.addEventListener('click', function () {
    openUploadModal(detailFontName);
  });

  // ── Tab switching ──────────────────────────────────────────────────────────

  const createGroupBtn = document.getElementById('oc-create-group-btn');
  document.querySelectorAll('.oc-tab').forEach(function (tab) {
    tab.addEventListener('click', function () {
      document.querySelectorAll('.oc-tab').forEach(t => t.classList.remove('oc-tab--active'));
      document.querySelectorAll('.oc-tab-panel').forEach(p => {
        p.hidden = true;
      });
      tab.classList.add('oc-tab--active');
      document.getElementById(tab.dataset.target).hidden = false;
      const onFonts = tab.dataset.target === 'oc-tab-fonts';
      if (uploadFontBtn) {
        uploadFontBtn.style.display = onFonts ? '' : 'none';
      }
      if (createGroupBtn) {
        createGroupBtn.style.display = onFonts ? 'none' : '';
      }
    });
  });

  // ── Groups state ───────────────────────────────────────────────────────────

  let groups = window.ocGroupsData || [];
  let editingGroup = null; // null = new group, object = existing group

  const groupModal = document.getElementById('oc-group-modal');
  const groupNameInput = document.getElementById('oc-group-name-input');
  const groupFontPicker = document.getElementById('oc-group-font-picker');
  const groupSaveBtn = document.getElementById('oc-group-save-btn');
  const groupDeleteBtn = document.getElementById('oc-group-delete-btn');
  const groupCancelBtn = document.getElementById('oc-group-cancel-btn');
  const groupModalClose = document.getElementById('oc-group-modal-close');
  const groupGrid = document.getElementById('oc-group-grid');
  const groupsEmpty = document.getElementById('oc-groups-empty');
  const groupsCount = document.getElementById('oc-groups-count');
  const selectedCountEl = document.getElementById('oc-group-selected-count');

  // ── Group modal open / close ───────────────────────────────────────────────

  function openGroupModal(group) {
    editingGroup = group || null;
    groupNameInput.value = group ? group.name : '';

    // Show delete button only for existing groups.
    if (groupDeleteBtn) {
      groupDeleteBtn.style.display = group ? '' : 'none';
    }
    renderFontPicker(group ? group.fontIds : []);
    groupModal.hidden = false;
    document.body.style.overflow = 'hidden';
    groupNameInput.focus();
  }
  function closeGroupModal() {
    groupModal.hidden = true;
    document.body.style.overflow = '';
    editingGroup = null;
  }

  // ── Font picker (checkboxes) ───────────────────────────────────────────────

  function renderFontPicker(selectedIds) {
    if (!fonts.length) {
      groupFontPicker.innerHTML = '<p style="padding:20px;color:var(--oc-gray-400);font-size:13px;">No fonts uploaded yet.</p>';
      updateSelectedCount();
      return;
    }

    // Deduplicate by family name — show one row per family, pick the first variant for preview.
    const families = [];
    const seen = new Set();
    fonts.forEach(f => {
      if (!seen.has(f.name)) {
        seen.add(f.name);
        families.push(f);
      }
    });
    groupFontPicker.innerHTML = families.map(f => {
      injectFontFace(f);
      const checked = selectedIds.includes(f.id) ? 'checked' : '';
      return `
				<label class="oc-group-font-item${checked ? ' oc-selected' : ''}" data-font-id="${f.id}">
					<input type="checkbox" value="${f.id}" ${checked} />
					<span class="oc-group-font-preview-mini" style="font-family:'oc-preview-${f.id}';font-weight:${f.weight};font-style:${f.style};">Aa</span>
					<span class="oc-group-font-info">
						<span class="oc-group-font-info-name">${h(f.name)}</span>
						<span class="oc-group-font-info-meta">
							<span class="oc-badge oc-badge-format">${h(f.ext)}</span>
							<span class="oc-code">${h(f.weight)}</span>
							${f.style === 'italic' ? '<span class="oc-badge oc-badge-inactive">Italic</span>' : ''}
						</span>
					</span>
				</label>
			`;
    }).join('');

    // Toggle selected class on checkbox change.
    groupFontPicker.querySelectorAll('input[type="checkbox"]').forEach(cb => {
      cb.addEventListener('change', function () {
        this.closest('.oc-group-font-item').classList.toggle('oc-selected', this.checked);
        updateSelectedCount();
      });
    });
    updateSelectedCount();
  }
  function getCheckedFontIds() {
    return Array.from(groupFontPicker.querySelectorAll('input[type="checkbox"]:checked')).map(cb => parseInt(cb.value, 10));
  }
  function updateSelectedCount() {
    if (!selectedCountEl) {
      return;
    }
    const n = getCheckedFontIds().length;
    selectedCountEl.textContent = `${n} selected`;
  }

  // ── AJAX: save group (create or update) ────────────────────────────────────

  async function saveGroup() {
    const name = groupNameInput.value.trim();
    const fontIds = getCheckedFontIds();
    if (!name) {
      groupNameInput.focus();
      return;
    }
    const label = groupSaveBtn.textContent;
    groupSaveBtn.disabled = true;
    groupSaveBtn.textContent = 'Saving…';
    try {
      const fd = new FormData();
      fd.append('nonce', nonce);
      fontIds.forEach(id => fd.append('font_ids[]', id));
      if (editingGroup) {
        fd.append('action', 'oc_group_update');
        fd.append('id', editingGroup.id);
        fd.append('name', name);
      } else {
        fd.append('action', 'oc_group_create');
        fd.append('name', name);
      }
      const res = await fetch(ajaxUrl, {
        method: 'POST',
        body: fd
      });
      if (!res.ok) {
        throw new Error(`HTTP ${res.status}`);
      }
      const json = await res.json();
      if (!json.success) {
        alert(json.data?.message || 'Save failed.');
        return;
      }
      const saved = json.data;
      if (editingGroup) {
        // Update in state.
        const idx = groups.findIndex(g => g.id === saved.id);
        if (idx !== -1) {
          groups[idx] = saved;
        }
        // Update card in DOM.
        const card = groupGrid.querySelector(`.oc-group-card[data-group-id="${saved.id}"]`);
        if (card) {
          card.dataset.groupName = saved.name;
          refreshGroupCard(card, saved);
        }
      } else {
        groups.push(saved);
        addGroupCardToGrid(saved);
        updateGroupsCount();
      }
      closeGroupModal();
    } catch (err) {
      alert(err?.message || 'Network error — please try again.');
    } finally {
      groupSaveBtn.disabled = false;
      groupSaveBtn.textContent = label;
    }
  }

  // ── AJAX: delete group ─────────────────────────────────────────────────────

  async function deleteGroup() {
    if (!editingGroup) {
      return;
    }
    if (!confirm(`Delete the group "${editingGroup.name}"?`)) {
      return;
    }
    const fd = new FormData();
    fd.append('action', 'oc_group_delete');
    fd.append('nonce', nonce);
    fd.append('id', editingGroup.id);
    try {
      const res = await fetch(ajaxUrl, {
        method: 'POST',
        body: fd
      });
      if (!res.ok) {
        throw new Error(`HTTP ${res.status}`);
      }
      const json = await res.json();
      if (!json.success) {
        alert('Delete failed.');
        return;
      }

      // Remove from state.
      groups = groups.filter(g => g.id !== editingGroup.id);

      // Remove card from DOM.
      const card = groupGrid.querySelector(`.oc-group-card[data-group-id="${editingGroup.id}"]`);
      if (card) {
        card.remove();
      }
      updateGroupsCount();
      closeGroupModal();
    } catch (err) {
      alert(err?.message || 'Network error — please try again.');
    }
  }

  // ── Group card rendering ───────────────────────────────────────────────────

  function buildGroupCardEl(group) {
    const el = document.createElement('div');
    el.className = 'oc-group-card';
    el.dataset.groupId = group.id;
    el.dataset.groupName = group.name;
    el.setAttribute('role', 'button');
    el.setAttribute('tabindex', '0');
    refreshGroupCard(el, group);
    return el;
  }
  function refreshGroupCard(el, group) {
    const members = fonts.filter(f => group.fontIds.includes(f.id));
    const preview = members.slice(0, 5).map(f => {
      injectFontFace(f);
      return `<span style="font-family:'oc-preview-${f.id}';font-weight:${f.weight};font-style:${f.style};" title="${h(f.name)}">Aa</span>`;
    }).join('');
    const more = group.fontIds.length > 5 ? `<span class="oc-group-card-more">+${group.fontIds.length - 5}</span>` : '';
    const empty = !group.fontIds.length ? `<span style="color:var(--oc-gray-400);font-size:12px;font-family:sans-serif;">Empty group</span>` : '';
    const n = group.fontIds.length;
    el.innerHTML = `
			<div class="oc-group-card-body">
				<p class="oc-group-card-name">${h(group.name)}</p>
				<p class="oc-group-card-count">${n} ${n === 1 ? 'font' : 'fonts'}</p>
				<div class="oc-group-card-previews">${preview}${more}${empty}</div>
			</div>
		`;
  }
  function addGroupCardToGrid(group) {
    if (groupsEmpty) {
      groupsEmpty.style.display = 'none';
    }
    if (groupGrid) {
      groupGrid.style.display = '';
    }
    const card = buildGroupCardEl(group);
    bindGroupCardClick(card);
    groupGrid.appendChild(card);
  }
  function updateGroupsCount() {
    if (!groupsCount) {
      return;
    }
    const n = groups.length;
    groupsCount.textContent = `${n} ${n === 1 ? 'group' : 'groups'}`;
  }

  // ── Event: group card click ────────────────────────────────────────────────

  function bindGroupCardClick(card) {
    card.addEventListener('click', function () {
      const id = parseInt(this.dataset.groupId, 10);
      const group = groups.find(g => g.id === id);
      if (group) {
        openGroupModal(group);
      }
    });
    card.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        this.click();
      }
    });
  }
  document.querySelectorAll('.oc-group-card').forEach(bindGroupCardClick);

  // ── Event: group modal controls ────────────────────────────────────────────

  if (createGroupBtn) {
    createGroupBtn.addEventListener('click', () => openGroupModal(null));
  }
  if (groupModalClose) {
    groupModalClose.addEventListener('click', closeGroupModal);
  }
  if (groupCancelBtn) {
    groupCancelBtn.addEventListener('click', closeGroupModal);
  }
  if (groupSaveBtn) {
    groupSaveBtn.addEventListener('click', saveGroup);
  }
  if (groupDeleteBtn) {
    groupDeleteBtn.addEventListener('click', deleteGroup);
  }
  if (groupModal) {
    groupModal.addEventListener('click', function (e) {
      if (e.target === groupModal) {
        closeGroupModal();
      }
    });
  }
  if (groupNameInput) {
    groupNameInput.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        saveGroup();
      }
    });
  }

  // ── Utilities ──────────────────────────────────────────────────────────────

  /**
   * Escape a string for safe HTML insertion.
   * @param str
   */
  function h(str) {
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }
  function filenameToTitle(filename) {
    return filename.replace(/\.[^.]+$/, '').replace(/[-_]/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
  }
})();
/******/ })()
;