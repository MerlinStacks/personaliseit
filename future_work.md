# Future Work & Improvements

This document outlines recommended improvements, feature additions, and technical debt resolution for the **Personalise It!** plugin (v3.5.1).

## 1. Feature Enhancements

### 1.1 Advanced Multi-View Support
**Current State:** The data structure supports multiple views, but the UI for switching views is basic.
**Recommendation:**
*   Add a visual "View Switcher" (thumbnails) in the Frontend.
*   Add "Required Fields" logic per view.

### 1.2 Mobile-First Designer UI
**Recommendation:** Implement a responsive layout using CSS Grid/Flexbox with collapsible sidebars for tablet/mobile editing support in the Admin Designer.

### 1.3 Undo/Redo in Frontend
**Recommendation:** Port the `zustand` temporal (undo/redo) middleware to the `useFrontendStore`.

## 2. Code Quality & Standards

*   **Accessibility (a11y)**: Add hidden ARIA-live regions to the canvas wrapper to describe the design state to screen readers.
*   **Testing:** Add PHPUnit tests for the `OrderController` and Jest tests for the complex React reducers in `useStore`.

---
---
**Resolved in v3.5.2 (Dev):**
*   [x] **Mobile-First Designer UI**: Implemented a fully responsive Admin Designer with collapsible sidebars and a bottom navigation bar for mobile devices.
*   [x] **Frontend Visual View Switcher**: Added a visual thumbnail-based view switcher to the Frontend controls for products with multiple views (Front/Back).
*   [x] **Frontend Undo/Redo**: Ported the `zustand` temporal (undo/redo) middleware to the `useFrontendStore` and added control buttons.
*   [x] **Frontend Refactoring**: Split monolithic index.js into modular components (ControlsComponent, CanvasComponent, etc.).
*   [x] **Accessibility (a11y)**: Added hidden ARIA-live regions to the frontend controls to announce state changes (Undo/Redo, Layer Add, View Switch) to screen readers.
*   [x] **Stable Engraving Dither**: Replaced `Math.random()` noise in the Engraving Filter with a deterministic 4x4 Bayer Matrix ordered dither, eliminating canvas shimmer during re-renders.
*   [x] **Frontend Text Effects**: Exposed "Text Warp" controls (Curved/Arc, Bridge, Bulge) to the Frontend UI, allowing customers to apply advanced text transformations directly on the product page.
*   [x] **Frontend Asset Browser**: Fixed data grouping bug where API returned flat array but UI expected object.
*   [x] **Combined Pagination & Infinite Loading**: Implemented `page`/`per_page` on the API and "Load More" logic on the Frontend Asset Browser to handle large libraries.
*   [x] **Secure Cart Pricing**: Implemented server-side price lookup for clipart layers to prevent tampering.
*   [x] **Live Visual Cart Preview**: Implemented `woocommerce_cart_item_thumbnail` filter to swap the default product image with the custom design snapshot in the Cart and Checkout.
*   [x] **Google Fonts Integration**: Added "Add Google Font" to the Font Manager, allowing one-click import of popular fonts via the Google Fonts CDN.
*   [x] **Layer Control Optimization**: Wrapped `LayerControl` in `React.memo` to prevent unnecessary re-renders of the entire form when a single input changes, significantly improving typing performance on complex products.
*   [x] **Image Uploads**: Refactored frontend user uploads to use AJAX (`/upload` endpoint) and store URLs instead of large Base64 strings.
*   [x] **API Client Standardization**: Updated `AssetBrowser.js` to use `@wordpress/api-fetch` instead of native `fetch`.
*   [x] **Canvas Rendering Optimization**: Implemented strict `React.memo` for layers and moved expensive pattern generation to a cached utility, significantly reducing re-renders.
*   [x] **True Vector SVG Export**: Implemented a custom SVG generator (`generateSVG.js`) that produces editable vector files (text as `<text>`, paths as `<path>`) instead of embedding raster images.

**Prepared by:** Antigravity (Google Deepmind)
**Date:** 2025-12-25
