# Plugin audit remediation

This pass addresses the concrete findings from the plugin-wide review. It is not
a guarantee that the plugin has no remaining defects. Changes were confined to
this plugin, existing worktree edits were preserved, and nothing was deployed.

**NOT universal/unattended upgrade release-ready.** Fail-closed guards can block
previously working operations until deployment configuration or legacy data is
reviewed. The requirement that no existing installs break is not yet demonstrated.
See [Upgrade Compatibility](upgrade-compatibility.md) for the supported-install
matrix and mandatory staging, backup and release checks.

## Implemented

- Guest ownership uses a signed per-browser identity instead of a shared IP
  fallback. Genuine WooCommerce session ownership remains supported.
- Private storage validates canonical roots; public storage requires explicit
  server-protection attestation. New print files use the shared private root.
- Raster artwork requires bounded decoding, SVG reservations account for the
  sanitized size, and incomplete CSS URL constructs are rejected.
- Webhook retries distinguish pending Action Scheduler work from the running
  attempt. Legacy artwork/VDP migration and cleanup use bounded progress cursors.
- Final design saves check form completeness and a persisted-state revision
  under a database lock. Draft deletion is revision-specific and exact autosave
  retries are idempotent. Autosave and final save permit up to 1,000 layers,
  subject to their other payload limits.
- Transactional write paths require verified InnoDB or independently verified
  transactional XtraDB tables. New plugin tables explicitly request InnoDB;
  existing tables are not automatically converted.
- Admin assignment, artwork-option, font-family/group, colour and clipart races,
  keyboard selection, layer compatibility and preview disposal were corrected.
- Cart normalization strips forged internal source identifiers and checks linked
  upload destination policy. AI crop, Original filters, thumbnail fallback,
  linked text styling and auto-sized text handoff were corrected.
- Optional Night Sky input and coordinate submission are consistent between the
  browser and server. The storefront displays the selected per-item surcharge.
- Fee allocation uses explicit IDs; order summaries use render snapshots rather
  than mutable designs. Duplicate Blocks name summaries were removed.
- Print cleanup and generation share output locks and reread state before
  deletion. Unknown reference counts retain artifacts rather than deleting them.
  Shared-reference queries include canonical and configured uploads-alias paths;
  missing aliases and unavailable historical mounts are conservatively retained.
- Preview cleanup attempts known-root relocation before discarding unresolved
  valid metadata. VDP template reads use guarded relocation and fail closed when
  storage is unavailable rather than silently producing a single output.
- VDP expansion is persisted before creating row jobs and reused on retries.
  Legacy retained jobs may run from validated original payloads, while expansion
  completeness and missing rows remain held for operator review.
- Font parsing is bounded, cache identities are content-based, and legacy font
  bundles are validated and atomically published without rewriting active files.
- Canonical empty EPS inputs remain empty. SVG paint, conservative viewports,
  indexed PNG transparency and Night Sky PDF borders/labels were corrected.
- Duplicate print mutation handlers and an unused queue helper were removed.
  Browser font serialization now has one implementation. Similar SVG helpers
  and independent validation boundaries were not indiscriminately merged.
- Production JavaScript assets were rebuilt; bundle caps were retained.

## Before deployment

1. **Configure private storage first.** See
   [`../includes/print/STORAGE.md`](../includes/print/STORAGE.md). Jobs must fail
   closed if a safe root cannot be established. CLI workers can reuse six-hour,
   site/config-scoped signed HTTP root evidence; otherwise use verified exact-root
   operator configuration. Default and filter-configured roots are supported.
2. **Verify legacy HTTP denial at both origin and CDN.** New private outputs do
   not secure old static URLs. Legacy/public roots require explicit trusted
   recursive operator verification. Bounded HTTPS canaries are advisory only:
   parent denial and rejection of fake-format bytes do not prove real child files
   are protected. Unknown origins/aliases and old cached copies still need checks.
   Known document-root contradictions persistently revoke prior CLI root evidence.
3. **Ship PHP and rebuilt assets together.** Old editor JavaScript cannot produce
   the new final-save completeness marker. Purge stale admin/CDN asset caches.
4. **Check database engines.** Unsafe or unknown engines now block transactional
   writes. Back up and review any necessary conversion separately.
5. **Review legacy recovery cases.** IP-token-only ownership is not safely
   recoverable through a shared token. Drafts without revision digests require
   recovery as a new inactive design, with a retained user-scoped recovery copy,
   rather than overwriting the original. Old/incomplete forms offer bounded JSON
   recovery, not a guarantee that truncated fields can be recovered. Existing VDP
   row sets without generation snapshots keep a completeness hold; valid retained
   jobs may finish, but missing rows are not invented from today's CSV. Invalid
   published legacy font bundles require maintenance after active readers drain.
6. Keep historical print roots explicitly allowlisted when changing storage;
   there is no arbitrary historical-root discovery or print-file migration in the
   storage compatibility helper. Exact old default/fallback artwork relocation is
   implemented, including already-versioned attachments; old private source copies
   remain pending reference-safe cleanup. See the storage doc for generator APIs.

## Local verification

Available reported passes from the combined implementation work are listed below.
These are prior verification facts, not tests rerun by this documentation-only pass
or certification of a frozen release artifact. Counts are intentionally omitted
because concurrent implementation work can change the suite totals.

- PHP syntax via `php scripts/lint-php.php`.
- JavaScript via `bun test tests/js`.
- Repository JavaScript ESLint and source SCSS Stylelint.
- Dependency-free security checks, including unavailable raster decoders.
- Dependency-free admin/autosave and transaction regression checks.
- Storage-upgrade, renderer, legacy font-cache and private print-storage regression
  checks, including instrumented concurrent font publication/readers.
- `git diff --check`.
- Production webpack build and all bundle budgets: core entry 203,656 bytes;
   required startup 533,247; upload-enabled startup 601,505; total JS/CSS 1,483,150.
  These are historical build measurements, not current artifact size guarantees.

The checks using standalone PHP scripts can keep temporary files inside the
plugin with `php -d open_basedir="$PWD" -d sys_temp_dir="$PWD/tests" tests/<script>.php`.
Scripts are `audit-security.php`, `admin-autosave-regressions.php`,
`admin-transaction-regressions.php`, `print-renderer-audit.php`,
`print-font-cache-regressions.php`, and `print-storage-regressions.php`.

## Validation still required

- Full PHPUnit/WordPress integration: the installed vendor tree cannot load
  `PHPUnit\TextUI\Application`.
- PHPCS: missing `DR\CodeSnifferBaseline\Plugin\BaselineHandler`.
- PHPStan: missing `SzepeViktor\PHPStan\WordPress\HookCallbackRule`.
- Real WordPress/MySQL concurrency, rollback and WooCommerce metadata round-trips.
- Live cart/checkout and admin-browser flows, including two simultaneous editors.
- Origin/CDN direct-file denial, actual importer integration and visual PDF/EPS
  parity. Drawing spies and synthetic fixtures are not full visual verification.
- Genuine Node execution: the local `node` command resolves to Bun; tests ran
  under Bun. No external runtimes were accessed or dependencies repaired in this
  remediation pass.

The existing `overcustomise.zip` was not regenerated. It is not the updated release.
