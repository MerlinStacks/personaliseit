/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./src/frontend/order-preview-modal.scss"
/*!***********************************************!*\
  !*** ./src/frontend/order-preview-modal.scss ***!
  \***********************************************/
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
/************************************************************************/
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
/*!*********************************************!*\
  !*** ./src/frontend/order-preview-modal.js ***!
  \*********************************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _order_preview_modal_scss__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./order-preview-modal.scss */ "./src/frontend/order-preview-modal.scss");

const triggerSelector = '.oc-order-preview-trigger';
let modal;
let previewImage;
let previousFocus;
const closeModal = () => {
  if (!modal || modal.hidden) {
    return;
  }
  modal.hidden = true;
  document.body.classList.remove('oc-preview-modal-open');
  previewImage.removeAttribute('src');
  if (previousFocus) {
    previousFocus.focus();
  }
};
const createModal = () => {
  modal = document.createElement('div');
  modal.className = 'oc-preview-modal';
  modal.hidden = true;
  modal.setAttribute('role', 'dialog');
  modal.setAttribute('aria-modal', 'true');
  modal.setAttribute('aria-labelledby', 'oc-preview-modal-title');
  modal.innerHTML = `
		<div class="oc-preview-modal__backdrop" data-oc-preview-close></div>
		<div class="oc-preview-modal__panel" role="document">
			<div class="oc-preview-modal__header">
				<div>
					<p class="oc-preview-modal__eyebrow">Your customisation</p>
					<h2 id="oc-preview-modal-title">Personalised preview</h2>
				</div>
				<button class="oc-preview-modal__close" type="button" data-oc-preview-close aria-label="Close preview">
					<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" /></svg>
				</button>
			</div>
			<div class="oc-preview-modal__image-wrap">
				<div class="oc-preview-modal__loader" aria-hidden="true"></div>
				<img class="oc-preview-modal__image" alt="Personalised product preview" />
			</div>
			<p class="oc-preview-modal__hint">Preview shown for reference. Final colours may vary slightly.</p>
		</div>`;
  document.body.appendChild(modal);
  previewImage = modal.querySelector('.oc-preview-modal__image');
  previewImage.addEventListener('load', () => {
    modal.classList.add('is-loaded');
  });
  modal.addEventListener('click', event => {
    if (event.target.closest('[data-oc-preview-close]')) {
      closeModal();
    }
  });
};
const openModal = trigger => {
  if (!modal) {
    createModal();
  }
  previousFocus = trigger;
  modal.classList.remove('is-loaded');
  modal.hidden = false;
  document.body.classList.add('oc-preview-modal-open');
  previewImage.src = trigger.href;
  modal.querySelector('.oc-preview-modal__close').focus();
};
document.addEventListener('click', event => {
  const trigger = event.target.closest(triggerSelector);
  if (!trigger) {
    return;
  }
  event.preventDefault();
  openModal(trigger);
});
document.addEventListener('keydown', event => {
  if ('Escape' === event.key) {
    closeModal();
  }
  if ('Tab' === event.key && modal && !modal.hidden) {
    event.preventDefault();
    modal.querySelector('.oc-preview-modal__close').focus();
  }
});
})();

/******/ })()
;
