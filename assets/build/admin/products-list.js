/******/ (() => { // webpackBootstrap
/*!************************************!*\
  !*** ./src/admin/products-list.js ***!
  \************************************/
/**
 * In-place navigation for the Products and Designs admin lists.
 *
 * Links remain normal admin URLs so navigation still works without JavaScript.
 */

(() => {
  const appSelector = '#oc-products-app';
  let requestController;
  const runScripts = container => {
    container.querySelectorAll('script').forEach(oldScript => {
      const newScript = document.createElement('script');
      Array.from(oldScript.attributes).forEach(attribute => {
        newScript.setAttribute(attribute.name, attribute.value);
      });
      newScript.textContent = oldScript.textContent;
      oldScript.replaceWith(newScript);
    });
  };
  const navigate = async (url, addHistory = true) => {
    const currentApp = document.querySelector(appSelector);
    if (!currentApp) {
      window.location.assign(url);
      return;
    }
    if (requestController) {
      requestController.abort();
    }
    requestController = new AbortController();
    currentApp.classList.add('is-loading');
    currentApp.setAttribute('aria-busy', 'true');
    try {
      const response = await fetch(url, {
        credentials: 'same-origin',
        signal: requestController.signal,
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      });
      if (!response.ok) {
        throw new Error(`Request failed with status ${response.status}`);
      }
      const page = new window.DOMParser().parseFromString(await response.text(), 'text/html');
      const nextApp = page.querySelector(appSelector);
      if (!nextApp) {
        throw new Error('Products list was not present in the response.');
      }
      currentApp.replaceWith(nextApp);
      runScripts(nextApp);
      document.title = page.title;
      if (addHistory) {
        window.history.pushState({
          ocProductsList: true
        }, '', url);
      }
    } catch (error) {
      if (error.name !== 'AbortError') {
        window.location.assign(url);
      }
    }
  };
  document.addEventListener('click', event => {
    const link = event.target.closest(`${appSelector} a.oc-ajax-nav`);
    if (!link || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
      return;
    }
    event.preventDefault();
    if (!link.classList.contains('disabled')) {
      navigate(link.href);
    }
  });
  document.addEventListener('submit', event => {
    const form = event.target.closest(`${appSelector} form.oc-ajax-form`);
    if (!form) {
      return;
    }
    event.preventDefault();
    const url = new URL(form.action || window.location.href, window.location.href);
    url.search = new URLSearchParams(new FormData(form)).toString();
    navigate(url.toString());
  });
  window.addEventListener('popstate', () => {
    if (document.querySelector(appSelector)) {
      navigate(window.location.href, false);
    }
  });
})();
/******/ })()
;