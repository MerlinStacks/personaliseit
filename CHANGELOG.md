# Changelog

## Unreleased

### Improved
- Automatically delete customer uploads after 48 hours when unused by an order or cart, or after 7 days from upload when still in an active or saved cart. Order-linked artwork and its related files are retained. Hourly WP-Cron cleanup processes backlogs in bounded batches; the policy also applies to existing uploads.

## 1.19.3 - 2026-10-03

### Improved
- Run resumable artwork/VDP migrations in a site-locked background worker, keeping storage protection on ordinary requests. Completed VDP migrations stop scanning until the storage root changes.
- Page storefront clipart in batches of 60, retain configured defaults, and search the full allowed catalogue. Older REST clients retain their full-list response format.
- Cache clipart metadata and content revisions, refreshing revisions on manager uploads/conversions and bounding detection of external replacements to five minutes.
- Restrict media-triggered catalogue purges to referenced design artwork and mockups.
- Clean old terminal print jobs and deduplication markers only for permanently deleted orders. Existing and trashed orders retain exact regeneration payloads.
- Track retired immutable assets and prune after at least 90 days, preserving dependencies of retained chunks. Existing assets receive a full grace period on first adoption.
- Exclude additional vendor build tooling from release ZIPs while retaining all runtime fonts and language coverage.

### Upgrade notes
- No schema migration or customer reconfiguration is required. WP-Cron must run for background migrations and retention cleanup.
- See `docs/cache-updates.md` for cache retention and reprint-data policy.

## 1.19.2 - 2026-09-28

### Fixed
- Refresh supported page caches after catalogue edits, shared resource changes, public media edits, and customiser settings changes. Coalesce each site's writes into one end-of-request purge and retry provider exceptions through cron.
- Prevent caching of plugin REST responses, including inactive designs and errors, and explicitly fetch live design states without browser caching.
- Recover once from rejected guest tokens or expired logged-in REST nonces without clearing customer inputs or replaying ambiguous network/server failures. Renew upload headers between authenticated retries.
- Version clipart URLs by file content, revalidate SVG requests, clear processed SVGs when design state changes, and allow failed SVG loads to be retried.

## 1.19.1 - 2026-09-27

### Fixed
- Preserve historical lazy-loaded JavaScript, stylesheets, and hashed resources in update ZIPs so cached visitors can finish loading the customiser.
- Fingerprint release contents, including PHP-only same-version rebuilds, and isolate object-cache entries by release.
- Purge supported page and optimisation caches once per site after a release change, with concurrency protection and retry cooldowns for provider failures.
- Offer an explicit fresh-page recovery when customiser or upload chunks cannot load.
- Rebuild assets before packaging so the release fingerprint and bundled files match.

### Upgrade notes
- No database migration is required. Cache integrations and asset-retention requirements are documented in `docs/cache-updates.md`.

## 1.19.0 - 2026-09-27

### Added
- Add a General setting for customer customiser field labels: top of field, left of field, or left on desktop only (768px and above).

## 1.18.0 - 2026-09-23

### Added
- Create reusable standard image filters without an AI provider or API key, including greyscale, negative, sepia, brightness, contrast, saturation, and hue rotation.
- Apply negative filters in the design preview, customer customiser, and production print renderer while preserving transparency.

### Fixed
- Preserve literal printable text, including angle brackets, percent sequences, and entity syntax, through cart validation, VDP, text layout, order summaries, and plain-text emails. HTML output remains escaped.
- Match admin and production hue rotation to the existing storefront matrix and half-turn amount scale.

### Upgrade notes
- No database migration is required; existing AI filter records and stored customer text are retained.
- Regenerated hue-filtered print files now match the storefront rather than the old print-only hue calculation. Previously generated files are not rewritten automatically.

## 1.17.2 - 2026-09-18

### Fixed
- Correct near-closed engraving SVG curve endpoints within a five-micron placement tolerance to preserve vector fills through CorelDRAW and LightBurn workflows.
- Keep the customiser gallery preview visible while True Video Product Gallery initializes, and select the pending preview when the gallery signals readiness.

## 1.17.1 - 2026-09-11

### Fixed
- Keep Night Sky address suggestions within the mobile form so customers can reach and select results.
- Accept full-precision address coordinates without hidden number fields blocking Add to cart.
- Reuse unchanged Night Sky geometry and avoid redundant preview redraws and empty-coordinate timezone lookups.
- Fix JavaScript lint formatting and declare the Night Sky regression tests' jsdom dependency explicitly.
- Remove unused fallback star names from the startup bundle to stay within existing performance budgets without changing sky geometry.

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
