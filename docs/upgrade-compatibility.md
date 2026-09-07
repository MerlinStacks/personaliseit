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
| Canonical writable private default, constant or filtered root outside known web paths | Supported after boundary validation. | Verify all deployment routes, permissions and worker access. |
| CLI using the same private root/configuration | Supported with live signed HTTP evidence, maximum six hours, or independently verified exact-root operator configuration. | Plan evidence refresh or trusted configuration before unattended/idle workers run. |
| Persisted-token uploads fallback or legacy public artwork/print tree | Blocked without explicit recursive operator approval. | Verify real files, nested directories, origin/CDN routes and caches; configure exact-root `oc_private_storage_web_protected`. Probes never approve the tree. |
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

HTTP private-root evidence is HMAC-signed and bound to the site, URLs, configured
and canonical paths, root device/inode and trusted deployment context. Tampering,
expiry, salt rotation or changed configuration invalidates it. CLI cannot mint it
by setting server variables. After six idle hours, operations relying on expired
evidence block without deleting retained metadata/rows. Refresh through a real
validated HTTP request or use independently verified exact-root operator policy.

A known document-root contradiction persists a root-scoped revocation that CLI
checks before evidence or private-root operator attestation. Expiry, replay, a new
path alias or a later narrower document root cannot restore permission. Correct all
relevant routing, change the trusted `oc_storage_verification_context` deployment
revision, and revalidate. Changing the revision alone does not mint HTTP evidence.
If revocation cannot be persisted, stop CLI workers until it can be recorded.

Public-subtree HTTPS canaries are advisory only. Successful probes, cached results
and deny files do not establish recursive origin/CDN policy. Explicit operator
approval must cover real content, nested order directories and every routed origin;
never return true globally. Unknown aliases/mirrors and previously cached customer
files need separate verification and purge. Removing approval removes permission.

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
   isolate queues. Clone URLs/configuration change evidence context: verify staging
   routes independently rather than copying production approval.
3. Cover default, constant, filtered and uploads-fallback roots; symlinked uploads;
   old private/public sources; custom historical roots; missing mounts; unwritable
   destinations; and unsupported atomic hard-link publication. Verify recursive
   denial with real artwork, PDFs and CSVs at origin and CDN, including cached URLs.
4. Exercise HTTP and actual WP-CLI/cron workers with fresh, expired, tampered and
   revoked evidence, configuration/salt changes and idle periods beyond six hours.
   Confirm blocks retain data and recovery restores only intended permissions.
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
