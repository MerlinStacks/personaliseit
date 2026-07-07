/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	// The require scope
/******/ 	var __webpack_require__ = {};
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
/*!******************************************!*\
  !*** ./src/admin/print-bounds-editor.js ***!
  \******************************************/
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   PrintBoundsEditor: () => (/* binding */ PrintBoundsEditor)
/* harmony export */ });
/**
 * Print Bounds Editor — standalone export.
 * The full drag/resize implementation lives in products-page.js.
 * This file exports a reusable class for use in other admin contexts (e.g. future bulk editor).
 */

class PrintBoundsEditor {
  /**
   * @param {HTMLElement} wrap      - The .oc-bounds-editor container div.
   * @param {HTMLElement} selEl     - The .oc-bounds-selection div.
   * @param {HTMLElement} imgEl     - The mockup <img> element.
   * @param {Function}    onChange  - Callback( x, y, w, h ) in natural image pixels.
   */
  constructor(wrap, selEl, imgEl, onChange) {
    this.wrap = wrap;
    this.sel = selEl;
    this.img = imgEl;
    this.onChange = onChange || (() => {});
    this._bindEvents();
  }
  get scale() {
    return this.img.naturalWidth > 0 ? this.img.offsetWidth / this.img.naturalWidth : 1;
  }
  getBounds() {
    const s = this.scale;
    return {
      x: Math.round((parseInt(this.sel.style.left, 10) || 0) / s),
      y: Math.round((parseInt(this.sel.style.top, 10) || 0) / s),
      w: Math.round(this.sel.offsetWidth / s),
      h: Math.round(this.sel.offsetHeight / s)
    };
  }
  setBounds(x, y, w, h) {
    const s = this.scale;
    Object.assign(this.sel.style, {
      left: x * s + 'px',
      top: y * s + 'px',
      width: w * s + 'px',
      height: h * s + 'px',
      display: w > 0 && h > 0 ? 'block' : 'none'
    });
  }
  destroy() {
    this.wrap.removeEventListener('mousedown', this._onWrapDown);
    this.sel.removeEventListener('mousedown', this._onSelDown);
    this.sel.querySelector('.oc-handle-se')?.removeEventListener('mousedown', this._onResizeDown);
  }
  _bindEvents() {
    this._onWrapDown = this._handleDraw.bind(this);
    this._onSelDown = this._handleMove.bind(this);
    this._onResizeDown = this._handleResize.bind(this);
    this.wrap.addEventListener('mousedown', this._onWrapDown);
    this.sel.addEventListener('mousedown', this._onSelDown);
    this.sel.querySelector('.oc-handle-se')?.addEventListener('mousedown', this._onResizeDown);
  }
  _handleDraw(e) {
    if (e.target === this.sel || e.target.classList.contains('oc-handle-se')) {
      return;
    }
    e.preventDefault();
    const rect = this.wrap.getBoundingClientRect();
    const sx = e.clientX - rect.left;
    const sy = e.clientY - rect.top;
    const controller = new AbortController();
    const {
      signal
    } = controller;
    document.addEventListener('mousemove', me => {
      const cx = me.clientX - rect.left;
      const cy = me.clientY - rect.top;
      Object.assign(this.sel.style, {
        left: Math.min(sx, cx) + 'px',
        top: Math.min(sy, cy) + 'px',
        width: Math.abs(cx - sx) + 'px',
        height: Math.abs(cy - sy) + 'px',
        display: 'block'
      });
      this.onChange(this.getBounds());
    }, {
      signal
    });
    document.addEventListener('mouseup', () => controller.abort(), {
      signal
    });
  }
  _handleMove(e) {
    if (e.target.classList.contains('oc-handle-se')) {
      return;
    }
    e.preventDefault();
    const startX = e.clientX;
    const startY = e.clientY;
    const startL = parseInt(this.sel.style.left, 10) || 0;
    const startT = parseInt(this.sel.style.top, 10) || 0;
    const maxL = this.wrap.offsetWidth - this.sel.offsetWidth;
    const maxT = this.wrap.offsetHeight - this.sel.offsetHeight;
    const controller = new AbortController();
    const {
      signal
    } = controller;
    document.addEventListener('mousemove', me => {
      this.sel.style.left = Math.max(0, Math.min(startL + me.clientX - startX, maxL)) + 'px';
      this.sel.style.top = Math.max(0, Math.min(startT + me.clientY - startY, maxT)) + 'px';
      this.onChange(this.getBounds());
    }, {
      signal
    });
    document.addEventListener('mouseup', () => controller.abort(), {
      signal
    });
  }
  _handleResize(e) {
    e.preventDefault();
    e.stopPropagation();
    const startX = e.clientX;
    const startY = e.clientY;
    const startW = this.sel.offsetWidth;
    const startH = this.sel.offsetHeight;
    const controller = new AbortController();
    const {
      signal
    } = controller;
    document.addEventListener('mousemove', me => {
      this.sel.style.width = Math.max(10, startW + me.clientX - startX) + 'px';
      this.sel.style.height = Math.max(10, startH + me.clientY - startY) + 'px';
      this.onChange(this.getBounds());
    }, {
      signal
    });
    document.addEventListener('mouseup', () => controller.abort(), {
      signal
    });
  }
}
/******/ })()
;