# SLDevs-hosted releases

Repository `personaliseit` ships `overcustomise/overcustomise.php`. The existing
ZIP, npm package name, and new feed all use `overcustomise`; package identity is
unchanged for release ZIPs. Existing GitHub source-ZIP installations may instead
live in `personaliseit-main/` (or `personaliseit/`). Their installed plugin basename
differs from the release ZIP: follow the legacy installation procedure below.

Metadata: https://sldevs.com.au/updates/overcustomise/info.json

PUC 5.7 is vendored from the pinned upstream commit in `THIRD_PARTY_NOTICES.md`,
including its dependencies and license. The bootstrap runs immediately after the
direct-access guard, independently of Composer and WooCommerce availability.
Normal PUC scheduling, caching and plugin-information hooks are used. No automatic
update policy is set, and the optional feed-controlled autoupdate feature is not enabled.

## Build

With PHP 8.2+, required PHP extensions, Composer, Node.js (CI uses 22), npm,
Python 3 and zip installed:

```bash
npm ci
npm run plugin-zip
```

This compiles assets, retains cached chunks according to the existing retention
policy, installs locked production Composer dependencies, and validates the ZIP.
It emits `overcustomise.zip` plus `dist/overcustomise-<version>.zip` with exactly
one `overcustomise/` folder. It also emits `dist/personaliseit-main-<version>.zip`
for manual replacement of legacy GitHub ZIP installations, verifying its contents
byte-for-byte against the standard ZIP. The Composer step changes the checkout's vendor tree
to production-only; run `composer install` again for development checks.
Synchronise the plugin header, OC_VERSION, package.json and package-lock.json,
and add `docs/releases/<version>.txt` when preparing the next release.

## Stage and publish

1. Back up a staging store running the preceding release. Verify its plugin folder,
   then install this ZIP once manually (or through its current updater). For a
   GitHub source-ZIP installation, use the legacy procedure below. Confirm
   activation, settings, customisation, cart/order flow and print output.
2. Use the site's existing publisher, from the site tooling checkout:

   ```bash
    python3 tools/publish-update.py overcustomise /path/to/personaliseit-main-1.19.6.zip --changelog /path/to/docs/releases/1.19.6.txt
   ```

3. After deployment approval, upload the generated versioned ZIP first, then
   `info.json`, under `domains/sldevs.com.au/public_html/updates/overcustomise/`
   on DirectAdmin. Verify the feed's download URL, version and requirements.
4. On staging, verify plugin information and update detection from an
   updater-enabled earlier version, then the actual upgrade and preserved settings.
   Check alongside another PUC v5 plugin, and with WooCommerce inactive.

Existing installations need this updater-enabled release installed once before
future hosted updates work. Building a ZIP does not publish it or verify live updates.

## Existing GitHub ZIP installations (`personaliseit-main`)

Keep the existing plugin directory when introducing the updater. Uploading the
standard `overcustomise.zip` through **Plugins → Add New** can create a second
installation instead of replacing `personaliseit-main/overcustomise.php`.

1. On staging first, back up the database and existing plugin directory. Confirm
   the active plugin's actual directory and that it contains `overcustomise.php`.
2. For a site using `personaliseit-main`, use the ready-built
    `dist/personaliseit-main-1.19.6.zip`. No manual ZIP editing is needed.
   For any other legacy folder name, extract the standard release ZIP, rename its
   top-level folder to the site's exact existing name, and re-zip that folder.
   Change only the enclosing folder name, not the plugin PHP filename or contents.
3. Upload this one-time compatibility ZIP through **Plugins → Add New → Upload
   Plugin**. Confirm WordPress offers to replace the existing OverCustomise
   installation before proceeding. If it offers a separate installation, stop
   and recheck the active plugin path. Do not delete/uninstall the old plugin as
   a migration step; it registers an uninstall callback.
4. Confirm there is one active OverCustomise installation, with its original
   folder, settings and product/order behaviour preserved. Repeat the verified
   procedure on the live sites using their respective existing folder names.
5. Test a subsequent higher-version hosted update on staging, including a
   background auto-update if sites will enable that option. Confirm the plugin
   stays active under its original basename and no second folder is created.

The current site publisher is configured to serve the `personaliseit-main/`
compatibility ZIP. Either that ZIP or the standard `overcustomise/` release ZIP
can be used for subsequent hosted updates, provided the publisher's package-root
configuration matches the ZIP. The bundled
Plugin Update Checker derives the installed basename from `__FILE__` and its
`upgrader_source_selection` hook renames the extracted update directory to match
the existing installation. Thus a `personaliseit-main` installation keeps that
directory while receiving the same feed as an `overcustomise` installation.
This handling requires the updater-enabled version to already be loaded; it
cannot help the first manual upload to an older installation without the updater.
