# Personalise It! - Definitive Developer & Maintenance Guide

## 1. System Architecture

The plugin is a **Hybrid React Application** embedded within WordPress.

### Dual-Entry Build System
-   **Admin App**: `src/admin/index.js` -> `build/admin.js`
    -   *Mount Point*: `#personaliseit-admin-app`
    -   *Router*: Custom hash/attribute router in `src/admin/index.js`.
-   **Frontend App**: `src/frontend/index.js` -> `build/frontend.js`
    -   *Mount Point*: `.personaliseit-frontend-root` (Injected via `includes/Frontend/ProductPage.php`).

## 2. Rendering Engine (The "Core")

Rendering is handled by **Konva.js** via **react-konva**.
> [!WARNING]
> **Duplicated Rendering Logic**: The rendering logic is split between two components. Changes to one usually require changes to the other.

1.  **Admin Editor**: `src/admin/components/CanvasStage.js`
    -   Handles drag/drop, transforming, editing interactions.
    -   Uses `Transformer` for resizing.
2.  **Frontend Viewer**: `src/frontend/components/FrontendCanvas.js`
    -   Read-only (mostly). Handles "Displacement Maps" and live text updates.
    -   Generates the "Preview Image" sent to the cart.

### Key Rendering Concepts
-   **Text Warping**: Custom implementation using `TextPath` for 'arc' and character mapping for 'bridge'/'bulge'.
-   **Embroidery Mode**: Uses a custom `CreateEmbroideryFilter` (in `src/utils/canvasUtils.js`) to pixelate/stitch-effect images.
-   **Displacement Maps**: `FrontendCanvas` uses `Konva.Filters.Displacement` to warp designs onto product wrinkles (shirt folds, mug curves).

## 3. Data Flow & Lifecycle

### A. Configuration (Admin -> DB)
-   **Storage**: `post_meta` key `_personaliseit_config`.
-   **Format**: JSON object containing:
    -   `views`: Array of view objects (Front, Back).
    -   `layers`: Array of design elements (Text placeholders, Image zones).
    -   `settings`: Canvas size, units.

### B. Personalization (Customer -> Cart)
1.  **User Input**: Stored in `useFrontendStore` (Zustand).
2.  **Sync**: `ControlsComponent.js` debounces updates.
3.  **Capture**: Calls `stageRef.current.toDataURL()` to generate a JPEG preview.
4.  **Submission**: Writes JSON payload + Preview Data URL to a hidden input `<input name="personaliseit_data">`.
5.  **Cart Hook**: PHP `CartIntegration.php` reads `$_POST['personaliseit_data']` and adds it to the cart item custom data.

### C. Cart Integration (`CartIntegration.php`)
1.  **Visuals**: Swaps the cart item thumbnail with the generated preview image via `woocommerce_cart_item_thumbnail`.
2.  **Meta**: Adds readable metadata (Layer Labels, Colors) to the order line item for admin/email display.
3.  **Admin View**: In `display_admin_order_item_meta`, renders a high-res preview and download links (PNG/JPG/PDF/SVG) for the shop manager.

### D. Client Storage (Drafts & History)
-   **History**: `useFrontendStore` maintains a `past` and `future` array for Undo/Redo functionality (limit 20 steps).
-   **Drafts**: `localStorage` key `personaliseit_draft_{product_id}` allows preserving state across reloads if the user doesn't clear cache.

## 4. Feature Deep Dives

### AI Integration (`AiController.php`)
### A. AI Integration (`AiController.php`)
-   **Provider**: OpenRouter.ai.
-   **Security**: API Key is server-side only. Frontend calls `/wp-json/personaliseit/v1/ai/generate`.
-   **Image Handling**:
    -   Generated images are **downloaded** to the WP Media Library immediately to prevents CORS Tainted Canvas errors.
    -   Input images (for Style Transfer) are converted to Base64 if local, or sent as URLs if public.

### B. Spotify Integration (`SpotifyController.php`)
-   **Auth**: Client Credentials Flow (Server-side).
-   **Proxy**: Proxy endpoint `/code?uri=...` fetches the "Scannable Code" image from Spotify's CDN and serves it from the WP domain to satisfy `crossOrigin="anonymous"` requirements for the canvas.

### C. Client-Side AI Tools (`FaceCutoutTool.js` & `backgroundRemoval.js`)
-   **Primary Strategy**: `@imgly/background-removal` (WASM/GPU). High accuracy, high resource usage.
-   **Fallback Strategy**: `removeWhiteBackground` (Canvas API). Simple luminance key (threshold 240) to make white pixels transparent. Used if WASM fails or crashes.
-   **Output**: Generates a Blob URL, then uploads to WP Media Library via `/wp-json/personaliseit/v1/upload` (via `UploadController.php`) to ensure persistence.

### 3. Asset Monetization (Implemented & Secure)
-   **Security**: `CartIntegration.php` ignores client-side pricing. It looks up the `personaliseit_asset_price` meta field for any layer with an `assetId`.
-   **Calculation**: Adds `personaliseit_asset_price` (meta) for layers with `assetId` to the product base price within `woocommerce_before_calculate_totals`.
-   **Mechanism**: Modifies `$cart_item['data']->set_price()` directly.
-   **Warning**: Relies on `woocommerce_before_calculate_totals`. Conflict potential with other dynamic pricing plugins.

### E. Migration System (`DataController.php`)
-   **Endpoints**: `/export` (GET) and `/import` (POST).
-   **Scope**: Settings, Fonts, Assets, and **Product Configurations** (`_personaliseit_config`).
-   **Portability**: Automatically replaces `site_url()` with `{{SITE_URL}}` placeholder during export, and restores it during import. Useful for Staging -> Production moves.

### F. Design Templates
-   **Storage**: Custom Post Type `personaliseit_tpl`.
-   **Management**: React-based UI in `TemplateManager.js` (accessed via Admin Menu).
-   **Editor**: Reuses `Designer.js`. If `template_id` URL param is present, it switches to "Template Mode" (saving to CPT content instead of Product Meta).

### H. Canvas Filters (`canvasUtils.js`)
-   **Embroidery**: `CreateEmbroideryFilter` generates a pixelated/stitched look using a per-pixel algorithm reacting to alpha channels.
-   **Engraving**: `EngravingFilter` applies Bayer Matrix Dithering (Ordered Dithering) to simulate laser engraving aesthetics.

### I. Sharing System (`ShareController.php`)
-   **Mechanism**: Generates a random 8-char slug (e.g., `/share/a1b2c3d4`).
-   **Storage**: Custom Post Type `personaliseit_share`.
-   **Hydration**: Frontend checks `window.personaliseitData.sharedDesign`. If present, `useFrontendStore` hydrates the state (inputs, styles, views) immediately on boot.

### J. Moon Phase Tool (`MoonPhaseTool.js`)
-   **Logic**: Calculates approximate moon phase index (0-7).
-   **Assets**: Loads SVG assets from `/assets/moon-phases/phase-{i}.svg` based on date selection.

### K. Embroidery Palette (`EmbroideryPalette.js`)
-   **Source**: Hardcoded list of industry-standard thread colors (e.g., "Isacord").
-   **Default**: Defaults to "Black" if no selection matches.

### L. Advanced Layer Effects (`EffectsTab.js`)
-   **Masking**: Supports non-destructive masking shapes (Circle, Oval, Heart, Rounded Corners).
-   **Blending**: Implements CSS/Canvas blend modes (Multiply, Screen, Overlay).
-   **Texture**:
    -   **Pattern Fill**: Replaces layer content with a repeating pattern image.
    -   **Distress Mask**: Applies a grayscale texture mask to simulate vintage/worn looks.
    -   **Text Warp**: `TextLayerControl.js` enables client-side text distortion (Arc, Bridge, Bulge) with adjustable intensity, provided the method is not 'embroidery'.

### M. Layer Settings (`SettingsTab.js`)
-   **Exclude from Export**: Layers can be flagged to be visible in the designer/preview but excluded from the final high-res export (useful for guides, overlays, or watermarks).
-   **Required**: Marks a layer as mandatory, preventing "Add to Cart" until populated.

## 6. High-Fidelity Export Engine (`ExportRenderer.js`)
This is a critical subsystem for fulfillment. It allows admins to generate **Print-Ready** files from an Order.

1.  **Endpoint**: `/personaliseit/v1/order-item/{order_id}/{item_id}` re-fetches the exact customer inputs.
2.  **Renderer**: A headless React component that mounts `FrontendCanvas` in an invisible container.
3.  **Formats**:
    -   **PNG/JPG**: Uses `stage.toDataURL` with `pixelRatio: 4` (approx 300 DPI). **Strips** engraving/embroidery filters to provide the raw design file.
    -   **PDF**: Uses `jspdf` to wrap the high-res PNG.
    -   **SVG (Vector)**: Custom generator that expands fonts to paths (or includes them) and exports vector data for vinyl cutters/plotters.
-   **Font System (`FontController.php`)**:
    -   **Dual Source**:
        -   **Google Fonts**: Loaded via `<link>` tags dynamically injected in `FrontendCanvas.js`.
        -   **Custom Fonts**: Uploaded WOFF/TTF files. Served via dynamic `@font-face` CSS generation in `FrontendCanvas.js` (Lines 235-295).

## 7. Critical "Gotchas" & Constraints

### 1. Settings Types
In `Settings.js`, boolean toggles must match their PHP `register_setting` type.
### J. Rendering Engine (`CanvasComponent.js` & `FrontendCanvas.js`)
1.  **State Driven**: React-Konva renders the canvas declaratively based on `useFrontendStore`.
2.  **Tainting**: All external images (Face Cutout, Spotify) are proxied or downloaded to local WP Media to prevent "Tainted Canvas" errors which block export.
3.  **Self-Healing**: `src/frontend/index.js` checks if the canvas container exists. If the theme hook removed it, the script **manually injects** a container into the DOM and initializes the Portal Overlay to ensure functionality.
### 3. Uninstall Cleanup (`uninstall.php`)
The plugin is destructive on uninstall.
-   **Removes**: Options, ALL Custom Post Types (`personaliseit_font`, `tpl`, `asset`, `pal`, `share`, `style`), Taxonomies, and **all product configuration meta**.
-   **Warning**: This deletes user creation data. Backup recommended.

### 4. Custom Post Type Visibility
-   `personaliseit_style` and others are set to `public => false` but `publicly_queryable => true`. This is intentional to allow REST API read access without auth, while hiding them from frontend archive pages.

### 5. CSS Isolation
The admin app is mounted inside WP Admin.
-   **Styles**: `_designer.scss` uses a wrapper class `.personaliseit-designer-wrapper` to prevent bleeding WP Admin styles into the designer, and vice versa.

### L. Portal Strategy (`CanvasVisibilityManager.js`)
-   **Problem**: WooCommerce themes (e.g., Flatsome, FlexSlider) often use `overflow: hidden`, clipping the personalization canvas if it expands beyond the image bounds.
-   **Solution**: The canvas is **not** rendered inside the gallery.
    -   It is rendered into `document.body` (Portaled).
    -   `CanvasVisibilityManager` uses `requestAnimationFrame` to sync the portal's position/size with the target product image (finding the "active" slide via `is-selected` class).
    -   It sets the original image `opacity: 0` effectively swapping it with the interactive canvas.

### M. SVG Vectors (`generateSVG.js`)
-   **Method**: Does **not** use `stage.toDataURL`. Uses a custom generator (`xml` string builder).
-   **Text**: Uses `opentype.js` to convert Text layers into SVG `<path>` data (Outlines) for true vector output (cutting machines).
-   **Font Fallback**:
    -   Tries to load TTF/OTF from config.
    -   If WOFF2 (Google Fonts), attempts to swap extension to `.ttf` to fetch a parseable buffer (Opentype.js cannot parse WOFF2).
    -   If parsing fails, falls back to standard SVG `<text>` tags with `@font-face` definitions injected into `<defs>`.

### P. Data Portability (`DataController.php`)
-   **System**: Recursive iterator (`process_placeholders`).
-   **Logic**: Replaces `site_url()` with `{{SITE_URL}}` during export and restores it during import.
-   **Coverage**: Applies to Settings, Asset URLs, and Font URLs, making full environment migration (Staging -> Prod) seamless.

## 8. Directory Map

| Path | Purpose | Key Files |
| :--- | :--- | :--- |
| `src/admin` | Admin SPA | `Designer.js`, `Settings.js`, `TemplateManager.js` |
| `src/frontend` | Storefront App | `FrontendCanvas.js`, `ControlsComponent.js` |
| `includes/Api` | REST Endpoints | `AiController.php`, `SpotifyController.php` |
| `includes/Frontend` | WC Integration | `ProductPage.php`, `CartIntegration.php` |

## 9. Build Commands
-   `npm start`: Watch mode (Admin + Frontend).
-   `npm run build`: Production build (Minified). **Required** before committing or deploying.
