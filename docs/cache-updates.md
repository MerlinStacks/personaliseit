# Cache-safe plugin updates

## Release detection

`npm run plugin-zip` builds the frontend before packaging. Webpack writes
`assets/build/release.json`, a deterministic fingerprint of emitted assets,
plugin PHP, templates, static assets, and the Composer lockfile. Replacing a ZIP
without increasing the plugin version still produces a new identity when its
runtime contents change. Always rebuild when deploying source files directly.

Object-cache keys include this identity. Old requests cannot populate the new
release's cache, and existing transient/session state is not globally flushed.
The main customiser's JS/CSS and previously version-only admin scripts use the
release identity in their asset URLs.

On the first WordPress request that loads the updated plugin, each site's
`wp_loaded` hook checks the release marker and invokes available cache APIs:

- WP Rocket: domain/page cache
- LiteSpeed Cache: purge-all action
- W3 Total Cache: page and minification caches
- WP Super Cache: page cache
- SiteGround Optimizer: cache purge
- Autoptimize: optimisation cache

The marker is stored after successful calls. An atomic five-minute lease prevents
concurrent purges. A provider exception is logged and retried after the lease
expires rather than breaking the storefront. Normal requests do not keep purging.

External hosts/CDNs can connect their purge API to
`oc_release_cache_purge( $new_release, $previous_release )`. Standalone Cloudflare
and host-level caches require their existing purge integration; the plugin does
not have credentials to call every CDN. A request answered entirely by an edge
cache cannot execute WordPress, so visit wp-admin after a filesystem deployment
to trigger release detection. Do not strip or ignore asset `ver` query parameters.

## Catalogue edits and shared resources

Design/assignment, font, colour, clipart and image-filter invalidation also queues
a page-cache purge. Changes to `oc_settings`, `oc_print_methods`, and edited/deleted
public attachments do the same. Private customer-artwork edits and print-file
cache invalidations do not trigger this purge.

Each site's changes are coalesced into one purge at shutdown. This is deliberately
a site-wide **page** purge: shared resources and removed assignments can affect
many products, including URLs no longer discoverable from current assignments.
Normal saves do not flush object/session caches or optimisation assets. Provider
exceptions are logged and scheduled for a retry through WordPress cron.

External cache integrations should also subscribe to `oc_content_cache_purge`.

## Retaining assets between releases

Keep `assets/build` in version control and include its historical assets in release
ZIPs. Builds retain all content-hashed chunks and resources, rather than a fixed
list of three old core chunks. Lazy CSS now has content-hashed filenames too;
legacy upload CSS paths remain available to previously cached runtimes.

Do not delete and recreate the build directory as part of a release. A clean CI
checkout must contain the retained files from prior releases. Retention is
deliberately conservative: remove historical assets only after the longest browser,
page-cache and CDN TTL **and** the supported open-tab lifetime have passed, and
only when no retained runtime/chunk still references them. The ZIP size check
continues to include retained assets. Files removed before this fix cannot be
recovered automatically from customers' caches.

## Missing-chunk recovery

If a core or upload chunk fails, the customer gets a **Refresh customiser** button.
It refreshes the current URL with a new `oc_cache_refresh` query parameter while
preserving product options and the URL fragment. Recovery is customer-initiated,
not an automatic reload loop. Upload recovery explains that unsaved customisation
needs to be re-entered after refreshing.

WordPress marks this recovery request `DONOTCACHEPAGE` and sends no-cache headers.
Configure any edge cache to bypass `oc_cache_refresh` requests as well. Ordinary
product pages remain cacheable, and session-token responses retain their existing
`no-store` protection.

## Live data and authentication recovery

All `/overcustomise/v1/` REST responses receive private/no-store headers, including
inactive and error responses. Variation/design requests also use browser
`cache: 'no-store'`. Clipart URLs include a hash of the actual file contents;
SVG processing revalidates browser caches and is reset on a design-state change.

Customer REST calls renew credentials only after an explicit 403 `invalid_token`,
`invalid_nonce`, or `rest_cookie_invalid_nonce` rejection, then retry once. Network
failures, server errors and other permission failures do not replay those calls.
Concurrent failures share the renewal. Uploads use the same recovery and set fresh
headers on each XHR attempt. Existing upload transport retry rules still apply.

Logged-in nonce renewal uses WordPress's authenticated `admin-ajax.php?action=rest-nonce`
endpoint. If the login cookie has also expired, the request stops and asks the
customer to sign in again while retaining their inputs; it does not silently
switch to guest authentication. Guest token renewal retains the existing
browser/session ownership checks for previously uploaded artwork.

## Verification

- `php tests/release-cache-regressions.php`
- `php tests/colour-group-cache-regressions.php`
- `node --test tests/js/frontend-bootstrap.test.mjs tests/js/release-manifest.test.mjs tests/js/webpack-code-splitting.test.mjs`
- `npm run plugin-zip` (includes build and ZIP completeness/size checks)

On staging, cache a product page and leave a customiser tab open before installing
a replacement ZIP. Verify that the older tab can still fetch its chunks, a new
visit gets the current release, and a second normal request does not purge again.
