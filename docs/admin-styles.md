# Admin stylesheet modules

Admin CSS is static, served directly by WordPress rather than webpack. The load
order is defined by `$admin_css_modules` in
`includes/admin/class-oc-admin-menu.php`. Each registered module depends on its
predecessor. Only `oc-admin` is enqueued; its existing URL, `assets/css/admin.css`,
contains the final responsive overrides and depends on the last module.

This keeps styles attached with `wp_add_inline_style( 'oc-admin', ... )` and
styles depending on `oc-admin` after the entire cascade. All nine existing OC
screens load the same cascade because several selectors override shared UI
across pages. Each file uses its own `filemtime()` version, falling back to
`OC_VERSION`, matching the previous cache-busting convention.

## Load order and responsibilities

Paths below are relative to `assets/css/`:

1. `admin/base.css`: tokens, page headers, breadcrumbs, buttons and cards.
2. `admin/operations.css`: print queue and customer uploads, including their
   original local media queries.
3. `admin/components.css`: forms, inputs, toggles, tables, badges, empty states,
   dashboard, print areas, bounds editor, mockup/print-method cards and tabs.
4. `admin/fonts-and-modals.css`: font cards, font details, shared upload modals,
   animations, font groups and group picker.
5. `admin/colours.css`: colour cards, editor and group swatches.
6. `admin/design-editor.css`: editor layout, canvas, bounds and property panels.
7. `admin/layers.css`: layer actions/list/settings, area navigation, drawing and
   canvas previews.
8. `admin/catalog.css`: product assignment/list UI and clipart manager.
9. `admin/settings.css`: settings tabs, save bar and system status.
10. `admin.css`: final editor/settings responsive overrides.

The split preserves contiguous sections of the original stylesheet. Keep later
overrides in their original cascade position, even when a selector also appears
in an earlier module. Avoid page-conditional loading or sorting the module list.
There are no runtime CSS imports or new build steps. Release packaging explicitly
includes `assets/css/admin`, and the ZIP checker requires every local CSS module.

## Verification

```bash
node --test tests/js/admin-styles.test.mjs
python3 -B tests/test_plugin_zip.py
npm run lint:css
```

The Node tests execute the production PHP enqueue method with capture stubs,
resolve its dependency graph, check screen gating, cache versions/fallbacks,
module inventory, packaging, CSS parsing, URL safety and the 1000-line ceiling.
They run automatically with `npm run test:js` and require PHP on PATH.

For the original refactor, compare against the pre-split revision:

```bash
OC_ADMIN_CSS_BASE_REF=90573f4829dec50a61e93f6f660351d756325d3e node --test tests/js/admin-styles.test.mjs
```

This additionally compares the complete parsed rule/declaration sequence,
including nesting, duplicates, media conditions, keyframes and `!important`,
against the actual PHP dependency load sequence. It also checks verbatim rule
text. The historical comparison is opt-in so intentional future styling changes
do not require updating a frozen stylesheet fixture; the reference must be
available in local Git history. These checks do not replace a live WordPress
browser test of third-party asset filters or CSS optimisers.
