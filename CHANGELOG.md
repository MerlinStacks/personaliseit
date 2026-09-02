# Changelog

## 1.17.0 - 2026-09-02

### Added
- Added selectable OpenRouter, direct Google Gemini, and direct OpenAI providers for AI image filters, each with encrypted API-key storage and dynamically discovered compatible models.
- Added customer-generated AI Image layers with isolated quotas and auditable generation provenance.
- Added deterministic Night Sky layers with authoritative server-side production geometry and fractional UTC offsets.

### Security
- Added a same-origin, token-protected and rate-limited place-search proxy.
- Reject direct OpenAI text-to-image generation when mandatory store instructions cannot be role-separated.

### Fixed
- Preserve linked AI Image authorization without weakening product, design, layer, or ownership checks.
- Keep existing AI image-filter quotas independent from text-to-image generation traffic.

## 1.16.3 - 2026-08-03

- Improved filtered-image preview reliability during checkout.

## 1.16.2 - 2026-07-27

- Keep production print-file textarea line breaks consistent with the live preview.

## 1.16.1 - 2026-07-20

### Added
- Added a per-AI-filter option that converts plain line-art backgrounds into a smooth transparency mask with ImageMagick.

## 1.16.0 - 2026-07-17

### Security
- Moved customer artwork, VDP data, generated print files, and thumbnails behind protected storage and authenticated or signed delivery routes.
- Added strict SVG, font, image, subprocess, webhook, and AI request resource limits.
- Added paid AI call quotas and hardened upload ownership, cleanup references, and print-file path validation.

### Fixed
- Made checkout print queue staging, retries, terminal transitions, combined jobs, and regeneration updates concurrency-safe.
- Made VDP, canonical render specs, hidden layers, image filters, print methods, and derivative lifecycles deterministic.
- Fixed admin design persistence, frontend customiser state races, linked layers, Spotify inputs, and order artwork previews.

## 1.15.0 - 2026-07-15

### Added
- Added OpenRouter-powered image-to-image filters with encrypted API-key storage and image-model selection.
- Added reusable AI prompt management with an admin test-image workflow.
- Added per-image-layer filter choices, default filters, and locked filters hidden from customers.
- Persisted generated artwork and its source/filter provenance for deterministic cart and print output.

## 1.14.0 - 2026-07-15

### Security
- Bound customer uploads to their WooCommerce session and exact product, design, variation, and layer context.
- Hardened SVG URL sanitisation, webhook destination validation, font uploads, and external converter limits.

### Fixed
- Made print queue claims atomic and gave legacy, design, and VDP print files unambiguous identities.
- Preserved valid print files during failed regeneration and retained immutable area snapshots for historical orders.
- Unified add-to-cart and cart-edit validation for locked layers, required fields, fonts, colours, clipart, and uploads.
- Fixed stale frontend redraws, uploads, Spotify checks, variation state, repeated submissions, and multi-area navigation.
- Made design, VDP, group membership, and autosave persistence transactional and concurrency-safe.
- Cleaned up expired thumbnails, previews, and unreferenced customer artwork.

## 1.13.4 - 2026-07-10

### Changed
- Reduced frontend page load overhead by limiting customiser font CSS and cart preview CSS to pages that need them.
- Cached product design assignment lookups and cleared assignment caches when assignments or designs change.

## 1.13.0 - 2026-07-07

### Added
- Added customer upload management, including a refreshed customer uploads gallery.
- Added linked layer input groups so matching product layers can share customer inputs.
- Added browser-side SVG tracing for clipart and browser-side font conversion support for print output.
- Added image layer background removal controls.
- Added mobile preview confirmation before adding customised products to cart.
- Added print file regeneration controls and manual print queue recovery paths.
- Added print bounds unit support and text layer defaults with frontend edit toggles.
- Added Spotify share link help in the frontend customiser.
- Added WebP artwork upload support.

### Changed
- Improved product customisation previews across product gallery, Flatsome gallery replacement, live preview mockups, cart, checkout, and admin order screens.
- Improved image upload handling, artwork placement, and frontend customiser output.
- Improved product editor controls, admin print area scrolling, and customiser panel presentation.
- Updated frontend customiser build assets and project dependencies.
- Bundled runtime Composer dependencies in release packages and removed production autoload reliance on development packages.
- Stored canonical render specs for print generation and preserved order-time print snapshots for later regeneration.

### Fixed
- Fixed launch-readiness validation so cart and edit-cart submissions can only use designs assigned to the product being purchased.
- Fixed frontend preflight and Blocks cart preview rendering to avoid unsafe HTML interpolation.
- Fixed SVG CSS sanitisation to strip external resource references.
- Fixed design saving reliability and restored customer artwork upload flows.
- Fixed print queue retry handling, missing print file generation, and Composer dependency loading during print generation.
- Fixed custom fonts being lost in generated and regenerated print files.
- Fixed current customer inputs, fonts, colours, and render specs being used consistently during print regeneration.
- Fixed embroidery EPS output positioning, overlap, rotation, editable text/clipart preservation, preview alignment, recoloured SVG clipart rendering, and customer data export.
- Fixed engraving output colour handling, including black artwork output and silver preview rendering.
- Fixed frontend artwork containment within print areas, transparent preview rendering, clipart preview effects, and mobile clipart grid density.
- Fixed colour and asset group creation persistence.
- Fixed frontend colour choices being restricted by layer group and custom text character limits being enforced.
- Fixed duplicate controls for linked layers and removed frontend customiser tooltips.
- Fixed order personalisation details and preview displays in cart, checkout, and admin order screens.
- Fixed product page fatal errors related to clipart groups.
