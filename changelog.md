# Changelog

## [4.0.0] - 2026-01-01
### Added
- **Architecture**: Major backend refactoring with Iron Core Protocol (Fastify + Drizzle).
- **Real-Time Presence**: System to prevent collision errors when multiple users edit the same product.
- **Displacement Maps**: Support for realistic texture rendering (e.g., wrinkles, folds).
- **Admin Gold Price**: Settings for dynamic pricing adjustments.
- **Backup and Restore**: Functionality for dashboard configurations.
- **Invoice Improvements**: PDF generation with multi-page support and custom designs.
- **Product Swatches**: Visual variation selection.
- **Security**: Enhanced validation and sanitization across all API endpoints.

## [3.5.1] - 2025-12-14
### Fixed
- **Frontend Asset Browser**: Fixed a critical bug where the Clipart Browser failed to load categories because the API returned a flat array instead of the expected object. The frontend now correctly groups assets by category.
- **Cart Price Security**: Implemented server-side validation for "Extra Price" on clipart layers. The secure solution now looks up the price in the database using the `assetId` instead of trusting the price sent from the client browser.
- **Image Upload Performance**: Refactored the frontend image upload to use a dedicated AJAX endpoint (`/personaliseit/v1/upload`) that stores the image URL. Previously, images were stored as Base64 strings, causing database bloating and potential `post_max_size` errors.

### Added
- **True Vector SVG Export**: Completely replaced the "raster-in-SVG" export with a true vector engine (`generateSVG.js`). Text layers now export as editable `<text>` elements and shapes as vectors, ensuring high-quality professional print output.
- **Canvas Optimization**: Implemented strict React Memoization and caching for canvas layers. This significantly improves performance and frame rates, especially when typing text or dragging elements on slower devices.
- **API Pagination**: Added `page` and `per_page` parameters to the `GET /assets` endpoint to handle large libraries of clipart.
- **Infinite Loading**: The Frontend Asset Browser now supports "Load More" functionality to efficiently load large clipart libraries in chunks of 20.
- **Upload Controller**: Added `UploadController.php` to handle secure, public-facing image uploads for personalization zones.

### Improved
- **Code Standardization**: Updated `AssetBrowser.js` to use `@wordpress/api-fetch` for built-in Nonce handling and better security.

## [3.5.0] - 2025-12-14
### Added
- **Dynamic Print File Downloads**: Replaced the single "Generate Print File" button in the admin order view with dynamic download buttons (PDF, SVG, JPG, PNG).
- **Raster-in-SVG Export**: Implemented a "Raster-in-SVG" export mode. This embeds a high-resolution PNG within an SVG container, ensuring a downloadable vector-compatible file format even for raster-heavy designs.
- **Background Exclusion**: Added logic to automatically hide background and overlay images during the export process, ensuring print files only contain the user's personalization.

### Changed
- **Export UX**: Improved the "Direct Download" user experience. The export tab now automatically closes itself after the file download is triggered.
- **Security**: Added `crossOrigin="anonymous"` to all frontend canvas images to prevent "tainted canvas" errors during export.

## [3.0.0] - 2025-12-10
### Added
- **Color Palette System**: Comprehensive system for managing color palettes in the admin and restricting colors per personalization method.
- **Canvas Containment**: Fixed CSS and JS scaling logic to ensure the personalization canvas fits correctly within the WooCommerce product gallery on all screen sizes.

## [2.1.0] - 2025-11-20
### Added
- **Admin Designer**: Initial release of the visual backend product designer.
- **Frontend Personalizer**: Initial release of the frontend React application for customers.
