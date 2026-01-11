# Code Review & Refactoring Suggestions
**Date:** 2025-12-16
**Reviewer:** AI Assistant

This document outlines suggested improvements, security considerations, and refactoring opportunities for the "Personalise It!" plugin codebase.

## 1. Security Enhancements

### API Endpoints
*   **Permission Callbacks:** [COMPLETED] Verified strict permission callbacks on all API endpoints.
    *   `UploadController`: Public access allowed for user uploads, but MIME types restricted to strict image types.
    *   Management endpoints (POST/DELETE) properly restricted to `manage_options` or `upload_files`.
*   **Input Sanitization:** [COMPLETED] `UploadController` hardened to restrict allowed MIME types.

### Data Portability
*   **Absolute URLs in Export:** [COMPLETED] The `DataController` now replaces absolute site URLs with `{{SITE_URL}}` placeholders during export and restores them during import.
    *   This ensures data can be moved between staging and live sites without breaking image links.

## 2. Component Refactoring (React/JS)

### `SidebarRight.js`
*   **Split `LayerListItem`:** [COMPLETED] create `src/admin/components/designer/LayerListItem.js`.
*   **Split `PropertiesPanel`:** [COMPLETED] Extracted to `src/admin/components/designer/PropertiesPanel.js`.
*   **Consistency:** [COMPLETED] Removed unused search logic and state.

### `Designer.js`
*   **Modals:** [COMPLETED] Extracted `SaveTemplateModal.js` and `LoadTemplateModal.js` to `src/admin/components/modals/`.

### State Management
*   **Zustand usage:** The app uses `useStore` effectively.

## 3. CSS / SCSS Architecture

### Specificity Wars
*   [COMPLETED] `_designer.scss` has been rewritten using BEM architecture (`.personaliseit-designer__*`).
*   React components (`Designer.js`, `SidebarRight.js`, `SidebarLeft.js`, `PropertiesPanel.js`) have been updated to use the new class names.
*   `!important` flags have been largely removed (except where absolute overrides of WP Core styles might be necessary, but minimized).

### Layout Stability
*   The layout is now controlled by standard Flexbox rules on the BEM elements.

## 4. Performance

### Layer Rendering
*   *Future optimization:* If a user adds 50+ layers, consider implementing windowing (e.g., `react-window`) for the layer list.

## 5. Summary of Completed Actions
1.  **[High Priority]** Verified strict permission callbacks on ALL API endpoints.
2.  **[High Priority]** Hardened File Uploads security.
3.  **[High Priority]** Fixed Data Portability (Placeholder URLs).
4.  **[Medium Priority]** Extracted `LayerListItem`, `PropertiesPanel`, and Modals into separate files.
5.  **[Medium Priority]** Refactored SCSS to BEM architecture.

---
*End of Review*
