=== Personalise It! ===
Contributors: customkings
Tags: woocommerce, personalization, custom products, designer, canvas
Requires at least: 6.0
Tested up to: 6.7
Stable tag: 4.0.0
Requires PHP: 8.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

High-performance WooCommerce product personalization plugin with live preview, drag-and-drop designer, and true vector export.

== Description ==

Personalise It! transforms your WooCommerce store into a powerful product configuration studio. Allow customers to design their own products with text, clipart, and images, seeing a live preview on the product page. Note: This plugin requires a "CustomKings" API key for some features if used in cloud mode, but operates standalone for basic features.

**Features:**
*   **Visual Product Designer**: Drag-and-drop interface for customers.
*   **Live Preview**: Real-time rendering of embroidery, engraving, and print effects.
*   **Vector Export**: Generate production-ready SVG and PDF files with preserved vector text.
*   **Admin Builder**: Easy-to-use backend layer builder for setting up templates.
*   **High Performance**: React-based frontend with optimized canvas rendering.

== Installation ==

1.  Upload the plugin files to the `/wp-content/plugins/personaliseit` directory, or install the plugin through the WordPress plugins screen directly.
2.  Activate the plugin through the 'Plugins' screen in WordPress.
3.  Go to **Personalise It > Settings** to configure your canvas defaults.
4.  Edit a WooCommerce Product and navigate to the "Personalise It" tab to create your design layers.

== Frequently Asked Questions ==

= Does this work with any WooCommerce theme? =
Yes, it hides the default product gallery and injects the designer canvas. Some CSS tweaking might be required for specific custom themes.

= Can I export print-ready files? =
Yes, you can export high-resolution PNG, JPG, PDF, and SVG files from the order admin page.

== Changelog ==

= 4.0.0 =
*   **Architecture**: Major backend refactoring with Iron Core Protocol (Fastify + Drizzle) for enhanced performance and scalability.
*   **New**: Real-Time Presence system to prevent collision errors when multiple users edit the same product.
*   **New**: Displacement Maps support for realistic texture rendering (e.g., wrinkles, folds).
*   **New**: Admin Gold Price settings for dynamic pricing adjustments.
*   **New**: Backup and Restore functionality for dashboard configurations.
*   **New**: Invoice PDF generation improvements with multi-page support and custom designs.
*   **New**: Product Swatches for visual variation selection.
*   **Security**: Enhanced validation and sanitization across all API endpoints.

= 3.8.21 =
*   Security: Hardened API endpoints with mandatory Nonce checks and comprehensive data sanitization.
*   Security: Implemented recursive input sanitization for nested configuration data, protecting against XSS.
*   Security: Strengthened file upload validation with strict MIME type and Magic Byte checking.
*   New: Unified Visual Effects Engine: Admin and Frontend now share the exact same Engraving and Embroidery filter logic for 100% visual parity.
*   New: Added Embroidery visualization support for Image/Clipart layers in Admin.
*   Performance: Optimized text rendering with `React.memo` integration.
*   Fix: Removed production console logs.
*   Fix: Corrected variable shadowing in Cart Integration.

= 3.6.5 =
*   New: Named Colors Support! You can now assign custom names to colors in palettes.
*   New: Enhanced Embroidery UI with "Premium Dropdown" selector (shows swatches + names).
*   Fix: Resolved "useState" crash in embroidery palette component.
*   UI: Improved mobile responsiveness for color selection.

= 3.5.3 =
*   New: Live Visual Cart Preview (shows actual design in Cart/Checkout).
*   New: Google Fonts Integration (One-click add from font manager).
*   New: Frontend Text Warp Effects (Arc, Bridge, Bulge).
*   Optimization: React.memo() for Layer Controls to fix typing lag.

= 3.5.2 =
*   New: Undo/Redo support for Frontend.
*   New: Frontend View Switcher for multi-view products.
*   New: Accessibility announcements (ARIA Live) for state changes.
*   Fix: Stable Dithering for Engraving mode (no more shimmer).

= 3.5.1 =
*   Fix: Image upload handling for frontend users.
*   Fix: Canvas rendering crash on some devices.
*   New: True Vector SVG export engine.

= 3.5.0 =
*   New: Dynamic print file downloads (PDF, SVG, JPG, PNG).
*   New: Raster-in-SVG export mode.
*   Fix: Background image exclusion during export.

= 3.0.0 =
*   New: Color Palette system.
*   Fix: Responsive canvas sizing.

= 2.1.0 =
*   Initial beta release.
