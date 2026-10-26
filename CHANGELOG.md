# Changelog

## 1.20.7 - 2026-10-26

### Fixed
- Fix the GitHub workflow's standalone laser-material regression bootstrap: PHPUnit-only integration stubs are defined only when PHPUnit is available.
- Handle legacy print-area records without a print method when validating material selections, including the strict layer-cost regression suite.
- Correct JavaScript regression-test formatting and PHP coding-standard violations. Material-library save notices verify a nonce; exception messages are escaped and template settings no longer override a WordPress global.

### Improved
- Use Fabric's modular browser build to remove unused library code. Compact equivalent tooltip selectors retain legacy Spotify aliases; material-picker styles load only when needed, including after a design/variation switch, with existing load-failure recovery.
- Keep the existing lint rules, coding-standard/static-analysis baselines, JavaScript size budgets and 90-day immutable-asset retention unchanged.

### Included material features
- Includes the 1.20.6 reusable laser material library, optional SVG surface layers, neutral Engraving Material finish, compact circular mobile swatches, vector PDF cut-line imports and shared preview/production fallback font. Existing glass, leather, wood and metal finishes remain available.

### Release and upgrade notes
- Supersedes the locally prepared 1.20.6 package without replacing its immutable ZIP. Hosted notes use WordPress's native Changelog section, without an embedded frame or preformatted box.
- No database migration is required. Keep the existing plugin-folder identity, replace without uninstalling, preserve unsaved work and reload open editors/customisers. WordPress/PHP requirements and WooCommerce compatibility claims are unchanged.

## 1.20.6 - 2026-10-26

### Added
- Reusable laser material library with editable surface and engraving colours, including six starter materials and configurable acrylic finishes.
- Optional Laser Material layers with sanitized SVG silhouettes, per-layer material choices and defaults, and fixed-material support through layer locking. Material selections are validated and retained in cart, checkout and order details.
- Neutral Engraving Material finish alongside glass, leather, wood and metal. Text, uploaded images and clipart above a material layer use its selected engraving colour without simulated frosting, texture, transparency or etched shadows.

### Improved
- Replace large material radio cards with compact circular swatches, 48-pixel touch targets, a selection ring and checkmark, keyboard navigation and an announced selected-material name. The picker wraps for narrow mobile screens.
- Preserve SVG silhouette transparency and holes while recolouring its surface. Material surfaces remain preview-only and never become engraving fills or cutting paths; existing engraving finishes remain available.
- Support SVG and first-page vector PDF cut-line imports with independent dimensions and geometry outside the print area.
- Use the bundled production fallback font for browser missing-glyph previews and embroidery outlines, including curved text, to improve preview-to-print consistency for characters the fallback supports.

### Release notes
- Hosted release notes use WordPress's native plugin-details Changelog section with headings, paragraphs and lists, without a nested frame, embedded page or preformatted scrolling box.

### Upgrade notes
- No database migration is required. Existing designs keep their engraving finishes unless a Laser Material layer is added. Configure its SVG silhouette, allowed materials and default in the Design Editor; use the neutral Engraving Material finish for layer-driven previews.
- Requirements remain WordPress 6.8+, PHP 8.2+ and an active WooCommerce installation. Replace the existing plugin without uninstalling it, then reload open customisers and editors.

## 1.20.5 - 2026-10-23

### Fixed
- Correct JavaScript regression-test formatting and PHP regression-test alignment and Yoda-condition violations that failed the 1.20.4 GitHub workflow. Existing checks and baselines remain unchanged.
- Includes the 1.20.4 fixes for false newer-tab autosave conflicts after lost acknowledgements or expired drafts, while retaining competing-editor and persisted-design revision checks.

### Release notes
- Hosted notes use WordPress's native plugin-details Changelog section with headings, paragraphs and lists, without an embedded frame or preformatted scrolling box.

### Upgrade notes
- No database migration is required. Replace the existing plugin without uninstalling it. Preserve unsaved work before reloading an existing editor to enable the autosave retry handling.

## 1.20.4 - 2026-10-22

### Fixed
- Prevent false newer-tab autosave conflicts when the server saved a draft but the browser missed its acknowledgement. Each editor page has its own identity, allowing safe reconciliation of its own write while retaining conflict protection against other editors.
- Resume autosaving when a draft transient expires or is evicted instead of incorrectly reporting a newer design in another tab. Final saves still check the persisted design revision before applying changes.

### Release notes
- Hosted release notes use WordPress's native plugin-details Changelog section with headings, paragraphs and lists, without an embedded frame or preformatted scrolling box.

### Upgrade notes
- No database migration is required. Replace the existing plugin without uninstalling it. Preserve unsaved work before reloading an existing editor; already-open pages do not acquire the new retry handling until reloaded.

## 1.20.3 - 2026-10-22

### Fixed
- Correct the UV fallback run-array formatting that failed the 1.20.2 PHP coding-standard workflow, without weakening checks or changing print output.

### Release notes
- Includes the 1.20.2 missing-character vector fallback and CFF print-conversion fixes. Hosted release notes use WordPress's native Changelog section with headings, paragraphs and lists, without an embedded frame or preformatted scrolling box.

### Upgrade notes
- No database migration is required. Replace the existing plugin without uninstalling it. For Photograph Signature and other CFF fonts, use Font Manager's Convert for print action once before retrying affected jobs.

## 1.20.2 - 2026-10-22

### Fixed
- UV text now uses bundled vector fallback outlines for missing characters while preserving supported lettering and kerning in the selected font. This fixes symbols such as the bullet in Photograph Signature without replacing the complete line or printing missing-glyph boxes.
- Detect CFF fonts by their contents, including files named `.ttf`, expose their Convert for print action, and resolve converted print companions regardless of the original extension.
- Browser print conversion now recognises CFF content in `.ttf` files as OpenType instead of treating it as TrueType.

### Release notes
- Hosted release notes use WordPress's native plugin-details Changelog section with headings, paragraphs and lists, without an embedded frame or preformatted scrolling box.

### Upgrade notes
- For Photograph Signature and other CFF fonts, use Font Manager's Convert for print action once before retrying affected print jobs. Missing-character fallback applies to all UV fonts; characters absent from both the selected font and bundled fallback still stop export for review.
- No database migration is required. Replace the existing plugin without uninstalling it; the existing plugin-folder identity and saved customisations are preserved.

## 1.20.1 - 2026-10-21

### Fixed
- Resolve the JavaScript formatting and WordPress PHP coding-standard violations that blocked the 1.20.0 GitHub workflow, without weakening the existing baselines or workflow checks.
- Prepare chooser-icon target lookups with WordPress's native identifier placeholder and add translator context for Staff Note validation messages.

### Release notes
- Hosted plugin-details notes use native WordPress headings, paragraphs and lists, with no nested frame or preformatted scrolling box.
- Includes the conditional layers, non-printing Staff Notes, chooser icons and checkout reliability fixes introduced in 1.20.0.

### Upgrade notes
- No database migration or customer reconfiguration is required. Requirements remain WordPress 6.8+, PHP 8.2+, and an active WooCommerce installation.
- Hosted updates preserve the installed plugin folder. Replace the existing plugin without uninstalling it.

## 1.20.0 - 2026-10-21

### Added
- Frontend layer conditions controlled by clipart selections, with is/is-not comparisons and show/hide actions. Hidden layers are excluded from checkout validation, charges and production artwork.
- Non-printing Staff Note layers for customer instructions, with required-field and character-limit validation and order-summary support.
- Optional design and clipart chooser icons, stored independently from live previews and production artwork.

### Improved
- Replace hover-only admin help with keyboard-accessible help disclosures and simplify the design editor while preserving existing flat-rate values.
- Render hosted release notes as native WordPress headings, paragraphs and lists rather than a preformatted box or embedded page.

### Fixed
- Skip generated chooser thumbnails and their background loading for design variants with an uploaded icon; show the icon immediately instead.
- Remove chooser icon references on uninstall without deleting Media Library images; preserve references on deactivation.
- Keep shared linked-layer controls and artwork checkout guards active whenever any linked member is shown by frontend conditions.
- Use persisted random webhook delivery UUIDs to prevent matching order IDs on different stores from colliding at shared receivers.
- Evaluate conditional layers against resolved clipart selections, including catalogue fallbacks for locked or fixed choices, so previews and checkout agree when a saved default is unavailable.
- Wait until Checkout Block orders are submitted before queuing print generation; draft checkout orders no longer create production jobs or schema-retry markers.
- Send the customisation-created webhook for Checkout Block orders as well as classic checkout orders.
- Deduplicate customisation-created webhooks per order and destination across repeated checkout/payment attempts, preserving one durable delivery job and its normal retries. Completed jobs become compact receipts outside the delivery recovery scan.
- Expire editor recovery copies after 30 days and remove them on uninstall. Existing recovery copies receive a 30-day grace period when first read or scanned by daily cleanup; reads do not renew that period. Normal autosaves are unchanged.
- Delete clipart library entries transactionally while retaining their artwork files for existing design and order references.
- Harden bundled barcode and colour handling with input validation and encoding corrections.

### Upgrade notes
- No database schema migration or customer reconfiguration is required. Designs without enabled conditions retain their existing visibility behaviour.
- Requires WordPress 6.8+, PHP 8.2+, and an active WooCommerce installation. Existing plugin-folder identity is preserved by hosted updates; replace the plugin without uninstalling it.
- Production artwork is unaffected by chooser icons and Staff Notes. Existing recovery copies receive the documented 30-day grace period.

## 1.19.10 - 2026-10-16

### Fixed
- Show the pale silver finish for uploaded black-metal engraving images, including AI-generated artwork and clipped uploads, instead of leaving dark lines black. White-background removal and existing transparency are preserved.
- Lift dark artwork to silver in silver-metal image previews while retaining distinct photo tones and the existing background-aware silver palette.
- Use the storefront material colours for gold-metal, wood and glass text and artwork in the admin design preview, including material-aware photo tinting and wood compositing.

### Upgrade notes
- No database migration or customer reconfiguration is required. Production artwork and upload retention are unchanged.

## 1.19.9 - 2026-10-16

### Fixed
- Correct the CI bundle-size check to count only the active engraving-preview chunk, rather than also counting historical copies retained for cached pages. Performance limits and retained assets are unchanged.

## 1.19.8 - 2026-10-16

### Fixed
- Restore the pale frosted finish for uploaded glass engraving images instead of the grey shading introduced in 1.19.7. Silver-metal photo detail, transparent backgrounds, and silver-plaque photo dithering remain unchanged.

## 1.19.7 - 2026-10-15

### Fixed
- Preserve light and dark photo detail in silver-metal and other tinted engraving previews instead of displaying uploaded photos as a flat rectangle. Solid-colour artwork and silver-plaque photo dithering remain unchanged.

## 1.19.6 - 2026-10-14

### Added
- Copy layers in the design editor, preserving their settings and geometry while creating an independent layer. Layer copies support undo, redo and autosave.

## 1.19.5 - 2026-10-12

### Improved
- Provide a ready-to-upload `personaliseit-main` compatibility ZIP for existing GitHub source-ZIP installations, including the hosted updater. Replace the existing plugin through WordPress without uninstalling; subsequent hosted updates preserve its installed folder.

## 1.19.4 - 2026-10-12

### Added
- Check for SLDevs-hosted updates using bundled Plugin Update Checker 5.7, including WordPress plugin-information support. Existing installations need this release installed once before hosted updates work; automatic updates remain the administrator's choice.
- Add Curved Text layers with adjustable arch/bowl angles, font and colour controls, material-aware engraving and raised-thread embroidery previews, and outlined PDF/EPS production output.

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
