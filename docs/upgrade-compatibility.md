# Upgrade Compatibility

**NOT universal/unattended upgrade release-ready.** The goal is that no existing
installs break, but that guarantee has not been established. Current guards retain
uncertain data and reject unsafe operations; they can interrupt workflows that an
older version allowed. Neither a current `oc_db_version`, a green readiness report,
nor dependency-free regression passes proves uninterrupted upgrade compatibility.
No deployment or release archive regeneration is performed by this documentation pass.

## Compatibility Matrix

"Supported" below means an implemented compatibility path, conditional on live
authorization, storage, database and renderer checks. It is not a staging sign-off.

| Existing installation or data | Current behavior | Setup or recovery required |
| --- | --- | --- |
| Intact schema and verified InnoDB tables | Supported transactional operations; mixed InnoDB/XtraDB is supported only with independent transactional XtraDB evidence. | Complete normal schema migration and verify metadata/advisory-lock support. |
| MyISAM, Aria, MEMORY, unknown engines or unavailable metadata | Affected writes fail closed; unrelated resources are not globally disabled. | Backup and DBA-reviewed repair/conversion; no automatic engine conversion. |
| Canonical writable automatic default | Selected automatically after boundary validation, with automatic persisted-token uploads fallback when unavailable or contradicted. | No mandatory server setup or public approval; actual filesystem failures block affected operations. |
| Explicit constant or filtered custom root | Optional configuration remains supported; invalid or unwritable explicit roots fail closed. | Correct the custom root rather than silently switching it. |
| CLI using the same storage configuration | Automatic operation continues without positive signed evidence, including after expiry or idle periods. | No evidence refresh requirement; actual known contradictions remain root-specific. |
| Persisted-token uploads fallback or legacy public artwork/print tree | Automatic operation with installed deny files and a nonblocking unverified-HTTP warning. | No explicit public approval required. Independently review direct-file exposure; deny-file presence does not prove origin/CDN protection. |
| Marked artwork in the exact prior deterministic default or persisted-token fallback | Bounded verified relocation, including already-versioned attachments; identity/metadata preserved. | Verified destination, readable sources and atomic publication support; retain old source copies. |
| Private previews in exact known old roots | Signed reads and cleanup integrate guarded relocation; filename, metadata, ID and signing secret are preserved. | Unresolved valid metadata stays retained. Review missing/conflicting files; relocation does not recover salt changes. |
| Private VDP CSV rows in exact known old roots | Bounded maintenance and template reads integrate guarded relocation; conditional path updates preserve template identity and fields. | Unavailable template storage raises an error, not a silent single-output fallback. Unknown/missing sources remain pending. |
| Existing print files and thumbnails, including mixed configured uploads aliases/canonical paths | Authorized retained files resolve; generator and cleanup reference checks protect both known spellings. | Keep old mounts and exact historical allowlists. Missing aliases/mounts remain retained. No automatic print relocation or DB-path rewrite. |
| Unknown former custom storage roots | No discovery or automatic migration from arbitrary DB paths. | Inventory and handle explicitly with backups. Historical print allowlisting is a separate trusted contract, not a migration grant. |
| Valid signed browser cookies or genuine WooCommerce session ownership | Supported ownership paths remain available. | Cookie loss or salt rotation needs independent ownership/recovery; IP-token-only legacy uploads cannot be assigned to a new browser using the shared token. |
| Legacy drafts without a base revision digest | Retained user-scoped recovery copy and recover-as-new path; recovery creates an independent inactive design. | Review/export and reconcile deliberately; do not overwrite the live original. Old/incomplete forms provide bounded recovery JSON, which may omit or lack truncated fields. |
| Existing VDP expansion snapshot | Valid original snapshot is reused, not today's edited CSV/design. | Invalid snapshots require review. |
| Legacy VDP rows/jobs without a complete expansion snapshot | Valid retained jobs may finish using original retained render data; completeness/missing rows stay held for review. | Do not infer complete expansion, recreate missing rows from today's CSV, or reset failed jobs automatically. Explicit retry still validates retained payloads; missing original render evidence blocks that job. |
| Legacy published font bundles | Validated bundles and atomic publication paths are implemented. | Invalid published bundles require maintenance after active readers drain; verify real rendering dependencies and output. |

## Storage Evidence

Signed evidence is optional diagnostic context, not a prerequisite for HTTP or CLI
operation. Missing, expired or invalid positive evidence is not a storage blocker.
No runtime HTTP probes run. A known actual document-root contradiction remains
root-specific, including for CLI; automatic selection uses the fallback rather than
blanket-blocking storage. Explicit custom-root failures remain fail-closed.

Automatic selection and Apache/IIS deny-file installation preserve the earlier
automatic functionality without mandatory server setup or public approval. They
do not prove HTTP security on all hosts. Nginx or Apache with overrides disabled
may expose public static files; aliases, mirrors and previously cached files need
independent security review and, where applicable, denial/purge. Readiness reports
`Automatic storage is operational; direct HTTP protection has not been verified.`
as nonblocking `storage_http_protection_unverified`, separately from filesystem
`ready`. Actual unwritable or invalid destinations still block affected operations.

Known-root relocation copies and verifies bytes before guarded publication. Old
private source copies are retained pending reference-safe cleanup; a new private
copy does not secure an old public URL. Unknown roots are not migrated. Print root
inventory APIs do not move print files, rewrite references or grant deletion rights.
The historical print allowlist also permits destructive uninstall, so populate it
only from trusted exact canonical configuration, never arbitrary stored paths.

## Required Staging Gate

1. Take a restorable backup of the database, uploads, every current/historical
   private mount, plugin/assets and deployment configuration. Secure signing salts
   and recovery data. Rehearse restoration; retained source copies are not backups.
2. Create an isolated staging clone of representative old installs with real schema
   history and sanitized legacy records. Disable outbound production effects and
   isolate queues. Review staging HTTP exposure independently rather than assuming
   production routes or deny-file behavior apply.
3. Cover default, constant, filtered and uploads-fallback roots; symlinked uploads;
   old private/public sources; custom historical roots; missing mounts; unwritable
   destinations; and unsupported atomic hard-link publication. Verify recursive
   denial with real artwork, PDFs and CSVs at origin and CDN, including cached URLs.
4. Exercise HTTP and actual WP-CLI/cron workers with fresh, expired, tampered and
   revoked evidence, configuration/salt changes and long idle periods. Confirm
   missing/expired positive evidence does not block automatic operation, actual
   contradictions remain root-specific with fallback, and filesystem failures
   retain data. No runtime HTTP probes should occur.
5. Verify schema migrations, engine metadata and advisory locks with real MySQL/
   WordPress. Test concurrent writes, rollback and WooCommerce metadata round-trips,
   including the deployment's HPOS configuration. Never bypass guards by advancing
   the version option or deleting an active migration lock.
6. Ship matching PHP and rebuilt assets, purge asset caches and test a stale open
   editor as well as a fresh editor. Exercise two-editor conflicts, old drafts,
   recover-as-new, bounded JSON export and incomplete submissions without losing the
   original. Verify guest/session ownership and controlled legacy upload recovery.
7. Test existing and new orders through classic/Blocks cart and checkout, signed
   previews before and after cleanup, CSV relocation, retained VDP retries and
   completeness holds. Confirm unknown roots stay pending, source copies remain,
   and mixed alias/canonical print references survive regeneration and retention.
8. Validate production renderer/runtime dependencies, actual importers, fonts and
   representative PDF/EPS output visually. Run the full integration/tooling suite
   once dependencies are repaired, then recheck the final packaged artifact and
   rollback procedure before approving each deployment profile.

## Verification Status

Available reported passes include PHP syntax, JavaScript under Bun, storage-upgrade,
renderer, legacy font-cache and private print-storage checks, plus the additional
lint/security/autosave/transaction/build checks recorded in
[Plugin Audit Remediation](plugin-audit-remediation.md). Counts are intentionally
not pinned while concurrent implementation and font work can change suite totals.
This documentation-only pass does not rerun those suites or claim that their prior
passes certify the final release. Targeted scripts for admin upgrade recovery,
alias references, cleanup/VDP relocation and print upgrades exist; their presence
alone is not a claim of a new test run.

Full PHPUnit/WordPress integration remains blocked by the installed vendor tree's
missing `PHPUnit\TextUI\Application`; PHPCS by missing
`DR\CodeSnifferBaseline\Plugin\BaselineHandler`; and PHPStan by missing
`SzepeViktor\PHPStan\WordPress\HookCallbackRule`. Genuine Node validation is also
outstanding because the local `node` resolves to Bun. No dependencies or external
runtimes were repaired in this documentation pass. Real server/CDN, database,
browser, importer and rendering validation remains required, not covered by stubs.

See [Compatibility Readiness](compatibility-readiness.md) for the five-minute
diagnostic/worker API and [Storage](../includes/print/STORAGE.md) for exact storage
contracts. Readiness is diagnostic, not automatic remediation or release approval.
