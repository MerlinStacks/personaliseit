# PersonaliseIt! Technical Reference

> **Version**: v4.3.5 (January 2026)  
> **Purpose**: Complete developer handoff for rebuilding or extending the plugin

---

# Part I: Feature Inventory

## Core Concept

**Real-time WooCommerce product personalization** — customers customize products in the browser using a canvas-based designer, then add to cart with their design saved as order metadata.

```
Product Page → Canvas Designer → Add to Cart → Checkout → Export Files
```

---

## Layer Types

| Type | Input | Customer Controls | Admin Controls |
|:---|:---|:---|:---|
| **Text** | Keyboard | Content, font, color, size | Available fonts, character limits, text effects |
| **Image** | Upload | Upload, crop, filters | Formats, size limits, background removal |
| **Clipart** | Picker | Select from library | Categories, per-asset pricing |
| **Spotify** | URL | Paste link | Bar/text colors, metadata display |
| **Moon Phase** | Date | Select date | Display style |
| **Face Cutout** | Photo | Upload face | Auto-remove background |

---

## Personalization Modes

| Mode | Behavior |
|:---|:---|
| **Standard** | Full color/font control |
| **Engraving** | Monochrome output |
| **Embroidery** | Thread palette, auto-digitizing |

---

## Export Formats

| Format | Use Case | Notes |
|:---|:---|:---|
| **PNG** | Print | 300 DPI, transparent |
| **JPG** | Smaller files | Black background |
| **PDF** | Production | Single or multi-page |
| **SVG** | Vector | Disabled with images |
| **ZIP** | Multi-view | All views bundled |

---

## Integrations

- **Spotify Codes**: API-free via `scannables.scdn.co`
- **AI Generation**: OpenRouter (Gemini, SDXL, Flux)
- **Background Removal**: WebAssembly (zero API cost)

---

## API Endpoints

| Endpoint | Purpose |
|:---|:---|
| `/fonts` | List fonts |
| `/assets` | List clipart |
| `/upload` | Image upload |
| `/order-item/{o}/{i}` | Fetch design |
| `/ai/generate` | Text-to-image |
| `/ai/style` | Style transfer |
| `/spotify/code` | Scannable image |
| `/spotify/metadata` | Song info |

---

## State Contract

```javascript
{
  config, views, currentViewId,
  userInputs: { [layerId]: value },
  userStyles: { [layerId]: { color, fontFamily } },
  embroideryColor, stageRef,
  past: [], present, future: []
}
```

---

## Security

- MIME validation on uploads
- HMAC signed URLs for secure files
- Rate limiting (10/hr on share)
- Centralized JSON sanitization

---

# Part II: Roadmap & Future Work

## ✅ Completed (v4.0–v4.3)

### v4.0.0
- Mobile-first Designer UI
- Frontend view switcher with thumbnails
- Frontend undo/redo
- ARIA accessibility
- Stable engraving dither (Bayer matrix)
- Frontend text effects (arc, warp)
- Asset browser pagination
- Secure cart pricing
- Live visual cart preview
- Google Fonts integration
- LayerControl memoization
- AJAX image uploads
- True vector SVG export

### v4.0.1
- dnd-kit migration (from react-beautiful-dnd)
- pdf-lib migration (from jsPDF)
- WordPress package updates

### v4.1.0
- Custom DB table (`wp_personaliseit_designs`)
- Async proof generation (Action Scheduler)
- 90-day file cleanup
- Structural sharing for history

### v4.3.0
- Design token system
- Phase 2 refactoring (ImageLayerControl, Settings)
- Phase 5 refactoring (ExportRenderer, ControlsComponent)
- Error Boundaries
- Toast notifications (replaced all alerts)

---

## 🚧 In Progress

| Priority | Task | Status |
|:---|:---|:---|
| High | 40% test coverage | 7 tests written |
| High | Remaining `fetch()` → `@wordpress/api-fetch` | Partial |

---

## 📋 Planned (2026)

### Competitive Parity
- Street Maps tool
- Star Maps tool
- QR Code tool

### AI Differentiation
- Artistic Filters (watercolor, cartoon)
- Pet Portrait generation

### Workflow Automation
- Printful/Printify POD integration
- Visual conditional logic

### Market Expansion
- Design Template Marketplace

### Technical Debt
- SSR preview for initial canvas state
- Per-view required field validation

---

## Risk Mitigations (Resolved)

| Issue | Resolution |
|:---|:---|
| 🔴 Database bloat | Custom table |
| 🔴 Checkout timeouts | Action Scheduler |
| 🟠 File accumulation | 90-day cleanup |
| 🟠 UI style conflicts | Scoped CSS modules |
| 🟡 History jank | Structural sharing |

---

## Security Audit (Jan 2026)

- ✅ XSS: `esc_html()` on dynamic output
- ✅ SSRF: Strict URI regex on Spotify
- ✅ File access: HMAC signed URLs
- ✅ Sanitization: Centralized `Sanitizer::recursive()`

---

# Part III: Rebuild Notes

## Keep
- Zustand slice pattern
- Portal strategy for theme compatibility
- Signed URL pattern
- WebAssembly background removal

## Consider Changing
- Konva → Fabric.js or native Canvas
- React → Vue/Svelte
- PHP → Node.js

## Critical Contracts
1. `exportMode` strips mockups and resets effects
2. `userStyles.color` is source of truth (not `fill`)
3. SVG disabled when images present
4. Form sync must include preview thumbnail

---

## Repository Standards

- **License**: GPLv2
- **Security**: `security@sldevs.com`
- **GitHub**: MerlinStacks/personaliseit
- **Required**: README.md, CHANGELOG.md, SECURITY.md

---

*Last updated: January 2026*
