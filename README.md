# Personalise It! - WooCommerce Product Personalization

A high-performance WooCommerce plugin that enables customers to personalize products with text and images, featuring a live visual preview.

## Features

### Core
- **Iron Core Protocol**: Modern architecture with Fastify & Drizzle for high performance.
- **Real-Time Presence**: See who's editing a product to prevent collisions.
- **Displacement Maps**: Realistic texture rendering for accurate mockups.
- **Backup & Restore**: Safe and easy configuration management.

### Admin Designer
- **Visual Builder:** Draw personalization zones directly on the product image.
- **Zone Types:** Support for Text and Image zones.
- **Advanced Visuals:**
  - **Curved Text:** Toggle curvature and adjust radius for text zones.
  - **Drop Shadow:** Add customizable drop shadows to text (Color, Blur).
  - **Overlay Images:** Upload a foreground image (e.g., texture, lighting, or mask) that sits on top of the personalization.
- **Multi-View Support:**
  - Create multiple views (e.g., Front, Back) for a single product.
  - Switch between views in the Admin Designer and Frontend Personalizer.
  - Configure unique layers and overlays for each view.
- **Customization:**
  - Resize and move zones freely.
  - Set default fonts and colors for text zones.
  - Manage layers.
- **Font Manager:** Upload custom fonts (TTF, OTF, WOFF) to be used in the designer and frontend.

### Frontend Personalizer
- **Live Preview:** Real-time rendering of text and images on the product.
- **Text Personalization:** Customers can enter text, choose fonts, and colors.
- **Image Upload:** Customers can upload their own images for specific zones.
- **Performance:** Optimized canvas rendering using `react-konva`.

### Order Integration
- **Cart Preview:** Personalization data is saved with the cart item.
- **Order Meta:** Full configuration saved to the order.
- **Visual Proof:** A generated preview image of the personalized product is attached to the order for the store owner to reference.

## Installation

1. Upload the plugin files to the `/wp-content/plugins/personaliseit` directory, or install the plugin through the WordPress plugins screen.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Ensure WooCommerce is installed and active.

## Usage

1. Go to **Personalise It!** in the admin menu.
2. **Fonts:** Switch to the "Fonts" tab to upload any custom fonts you want to offer.
3. **Designer:**
   - Select a product to personalize.
   - Click "Add Text Zone" or "Add Image Zone".
   - Drag and resize the zone on the canvas.
   - Configure properties (Label, Font, Color) in the sidebar.
   - Click "Save Config".
4. **Frontend:** Visit the product page. The personalization interface will appear.

## Development

- `npm install`: Install dependencies.
- `npm start`: Start development server (hot reloading).
- `npm run build`: Build production assets.
