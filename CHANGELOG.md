# Changelog

## 1.12.0 - 2026-07-06

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
