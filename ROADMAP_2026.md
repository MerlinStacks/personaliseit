# Personalise It! - 2026 Vision Roadmap

## Executive Summary
Transform from a product customization tool into a **premium design experience** that rivals standalone design apps while maintaining WooCommerce integration simplicity.

---

## 🎨 UI/UX Modernization

### 1. Command Palette (⌘K / Ctrl+K)
Quick actions searchable modal - industry standard in 2026
- Add layer by typing "text", "image", "spotify"
- Jump to views, settings, tools
- Keyboard-first power users

### 2. Context-Aware Floating Toolbar
Replace fixed sidebars with floating toolbars that appear near selection
- Text selected → formatting options float nearby
- Image selected → crop, filters float nearby
- Reduces mouse travel, feels more "Canva/Figma"

### 3. Dark Mode
Full dark theme with OLED-black backgrounds
- `prefers-color-scheme` detection
- Manual toggle in admin
- Reduced eye strain for prolonged use

### 4. Drag-to-Resize Panels
Resizable sidebars instead of fixed widths
- Drag divider to resize
- Collapse to icons-only
- Remember user preference in localStorage

### 5. Real-Time Collaboration Indicators
Show when other admins are editing same product (future-proof for multi-user)
- Colored cursors with avatar
- "User is editing..." indicator
- WebSocket or polling

---

## ⚡ Performance & Feel

### 6. Skeleton Loading States
Replace spinners with content placeholders
- Layer list skeleton
- Properties panel skeleton
- Feels faster, more polished

### 7. Optimistic UI Updates
Instant feedback, reconcile with server later
- Layer add/delete/reorder immediate
- Rollback if server fails
- "Saving..." indicator in corner

### 8. Lazy-Loaded Panels
Only load properties panel code when layer is selected
- React.lazy() for heavy components
- Reduces initial JS bundle
- Faster first paint

### 9. Virtual Scrolling for Large Layer Lists
Products with 50+ layers lag
- react-window or @tanstack/virtual
- Only render visible items
- Smooth scrolling at scale

---

## 🔧 Functional Enhancements

### 10. AI-Powered Features
- **Smart Crop**: Auto-detect face/subject for image layers
- **Color Suggestions**: Suggest harmonious colors based on design
- **Auto-Layout**: "Distribute evenly" for multiple layers
- **Text Ideas**: AI-generated alternative text suggestions

### 11. Layer Groups & Nesting
Group related layers together
- Collapse/expand groups
- Move groups as unit
- Opacity/visibility affects all children

### 12. Snap & Alignment Guides
Smart guides when dragging layers
- Snap to center, edges, other layers
- Distance indicators
- Grid overlay toggle

### 13. Design Version History
Beyond undo/redo - named snapshots
- "Save version" creates checkpoint
- Compare side-by-side
- Restore any previous version

### 14. Template Designer (Admin)
Create reusable design templates
- Pre-configured layer layouts
- Apply template to new products
- Customer starts with template base

### 15. Advanced Typography
- Variable fonts support
- Text on path (curve, circle)
- Individual character styling
- OpenType features (ligatures, stylistic sets)

### 16. Blend Modes & Effects
- Multiply, Screen, Overlay, etc.
- Drop shadows (already have)
- Inner shadows
- Blur/glow effects

### 17. Smart Objects
Non-destructive editing for images
- Original always preserved
- Filters stack non-destructively
- Swap source without losing effects

---

## 📱 Mobile & Touch

### 18. Gesture Controls
- Pinch to zoom canvas
- Two-finger rotate layers
- Swipe to delete layers
- Long-press for context menu

### 19. Mobile-Native Bottom Sheets
iOS/Android-style bottom sheets for properties
- Drag up to expand
- Snap points (peek, half, full)
- Feels native on mobile

### 20. Offline Capability
Progressive Web App features
- Cache critical assets
- Queue changes when offline
- Sync when reconnected

---

## 🔌 Integrations

### 21. Stock Image Search
Built-in search for free stock images
- Unsplash, Pexels integration
- Insert directly to canvas
- Attribution handling

### 22. AI Image Generation
Generate images from text prompts
- DALL-E, Stable Diffusion API
- "Generate background" feature
- Customer-facing or admin-only option

### 23. Export Destinations
Beyond download
- Send to email
- Save to cloud (Google Drive, Dropbox)
- Direct social sharing

---

## Priority Recommendation

### Quick Wins (1-2 days each)
- [ ] Skeleton loading states
- [ ] Dark mode
- [ ] Snap guides (basic)

### Medium Effort (1 week each)
- [ ] Command palette
- [ ] Layer groups
- [ ] Version history

### Major Features (2+ weeks each)
- [ ] AI-powered features
- [ ] Real-time collaboration
- [ ] Mobile gestures

---

*Created: 2026-01-11*
*Next Review: After dropdown/polish fixes complete*
