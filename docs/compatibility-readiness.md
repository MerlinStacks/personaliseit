# Compatibility Readiness

`oc_db_version` records completed schema migrations, not runtime readiness. A
current version does not prove transactional table engines, intact columns and
indexes, available metadata queries, or protected writable storage. The plugin
remains loaded when one resource is blocked; operation-specific guards remain
authoritative and must not be bypassed based on this cached diagnostic.

**NOT universal/unattended upgrade release-ready.** A healthy diagnostic is not
proof that every old install will keep working. See
[Upgrade Compatibility](upgrade-compatibility.md) for supported cases, cases needing
operator setup/recovery, and the staging-clone and backup release gate.

## Operator Workflow

WooCommerce managers see failures in admin notices and detailed rows under
OverCustomise > Settings > System Status. Reports are cached in the site-specific
`oc_compatibility_readiness_v2` transient for five minutes, including failures.
No readiness hook probes storefront requests. Admin cache hits do no database
metadata or filesystem probing. Workers must use the cached-only API below.
Changing the installed/target schema version invalidates the cached report.

The notice's **Recheck Readiness** button requires POST, `manage_woocommerce` and
the `oc_recheck_readiness` nonce. It deletes only the diagnostic transient and
rebuilds the report. It does not clear migration locks, convert engines, move
files, or advance schema versions. Healthy reports remain visible on System
Status and refresh automatically after five minutes.

- `schema_incomplete`: inspect missing/incompatible columns and indexes against
  the shipped schema and review upgrade logs. Back up before repairing schema.
- `migration_required`: the recorded version is behind; allow the existing
  upgrade workflow to finish. Investigate logs if it remains pending. Never
  manually advance the version option to suppress the failure.
- `migration_running`: wait for the migration owner; never delete its active lock.
- `unsupported_engine`: back up the listed table and arrange DBA-managed migration
  to InnoDB in a maintenance window. MyISAM, Aria, MEMORY and unknown engines are
  rejected. There is no automatic engine conversion or nontransactional fallback.
- `metadata_query_failed`: metadata/lock inspection failed, not proof of an unsafe
  engine. Check database availability and host permissions/support for
  INFORMATION_SCHEMA and advisory locks, then recheck.
- `table_metadata_missing`: the table's engine could not be established even
  though the query did not report an error. Verify table existence and metadata
  visibility; never guess the engine.
- `storage_blocked`: the existing `private_storage_path($resource, true)` API
  returned `null`. Check writable directories, private root configuration and
  server/CDN protection. The shared storage diagnostics below may explain why.

InnoDB remains supported based on the table ENGINE metadata. Historical XtraDB
is accepted only if that table reports XtraDB and INFORMATION_SCHEMA.ENGINES
reports XTRADB with SUPPORT YES/DEFAULT and TRANSACTIONS YES. Missing, disabled,
nontransactional or failed evidence blocks the operation. Mixed InnoDB/XtraDB
sets are supported when every table is independently verified.

Storage probes use the existing API, which can create directories, refresh deny
files and run bounded HTTPS verification with harmless canaries. CLI may reuse
live, site/configuration-bound signed HTTP private-root evidence for at most six
hours or independently verified exact-root operator configuration. Expiry blocks
operations relying on that evidence; it does not delete retained data. Known
document-root contradictions persistently revoke approval, including for CLI;
expiry or a different path alias cannot revive it. Public uploads subtrees require
explicit recursive `oc_private_storage_web_protected` operator approval. Automatic
HTTPS probes are advisory only, even when all canaries are denied. They never
grant public-subtree access or prove unknown aliases/mirrors safe or purge cached files.
See `includes/print/STORAGE.md` for current evidence lifetimes, probe budgets,
operator configuration and relocation behavior.

Readiness consumes `OC_Storage_Upgrade::reports()` **after** the storage checks.
Its root keys are discarded. Exact known messages are mapped to fixed codes in
`storage_upgrade`; arbitrary text, paths, identifiers and additional message
suffixes are never copied into the cache or rendered. Unknown messages become
`storage_diagnostic_unknown` with generic guidance. Duplicate codes are collapsed.

- `storage_root_overlap`, `storage_evidence_missing`: fix private-root boundaries
  or renew real HTTP evidence/use a verified operator root.
- `storage_http_verification_blocked`, `storage_http_verification_deferred`: check
  HTTPS/loopback access, server denial and verification locks/budget, then recheck.
- `storage_http_verified`: retained diagnostic mapping for an older exact message;
  its mapped state is `ready`, but the current advisory-only public-subtree helper
  does not emit that approval message. It is never an authorization grant.
- `relocation_storage_blocked`: restore verified destination storage; sources and
  existing metadata remain retained.
- `relocation_source_review`: inspect missing, changed, unreadable, unknown-root
  or oversized sources/records and backups without forcing publication.
- `relocation_publication_blocked`: inspect batch reads, copy verification,
  atomic hard-link support and conditional pointer updates; preserve copies until
  references are safely reconciled.
- `relocation_source_retained`: a private copy was published but the old source
  remains. Arrange reference-safe cleanup and old-location HTTP denial/CDN purge.

These diagnostics appear as warnings in notices and System Status; they do not
add a blanket print pause or disable unrelated resources. Reports are request-local
in the storage helper, so readiness captures only diagnostics available during
that build, not a durable relocation inventory or proof that all sources moved.
Readiness itself does not invoke relocation, scan legacy records or delete sources.
Normal storage maintenance and lazy reads can independently perform relocation.
The report discards raw database errors too; notices require manager capability.
No public HTTP endpoint is registered. Renderer dependencies remain separate
System Status checks.

Current advisory and revocation messages without an exact mapping are deliberately
reported as `storage_diagnostic_unknown`. Do not interpret that generic diagnostic
or the older `storage_http_verified` mapping as automatic public-root approval.

## Worker API

```php
$report = OC_System_Status::cached_readiness_report();
if (null !== $report) {
    // Optional diagnostic context for worker telemetry or a separately designed policy.
    $observed_print_block = $report['print_retry_pause'];
}
// Null is unknown, not a blanket pause. Existing live operation guards still apply.
```

The cached-only PHP API is available after plugin bootstrap. It reads only the
WordPress transient and installed schema-version option; these reads may use the
normal database/object-cache backend. It never invokes the report builder, reads
storage diagnostics, probes database metadata/HTTP, creates directories, or
refreshes the cache. Absent, expired, future-dated, invalid-TTL or version-mismatched
reports return `null`, even if a cache backend still holds the payload. Validity
requires an integer `checked_at` no older than 300 seconds (expiry is exclusive),
not in the future, and `recheck_after` exactly 300. It does not extend TTL on reads.

`readiness_report()` is the **admin diagnostic builder**, not the worker API:
cache misses probe storage/HTTP and can create directories. `readiness_report(true)`
forces those checks. Neither belongs in a retry hot loop. The report exposes
`checked_at`, `recheck_after` (300 seconds), installed/target schema versions,
`schema`, `print_schema`, `migration`, per-table states, per-resource transaction
booleans, per-directory storage states and sanitized `storage_upgrade` codes.
`print_retry_pause` is a cached diagnostic hint covering print-only
schema, migration availability, both print table engines and new print storage.
An unrelated fonts/colours/designs failure does not pause print. Resource booleans
describe only transaction engine support, not complete feature availability.
The pause flag is an infrastructure hint, not permission to skip live guards or
proof that rendering dependencies and historical files are available.

No generator/queue retry-pause integration is implemented or promised. In
particular, this diagnostic must not cause a retry hot loop, trigger probing on
cache expiry, consume attempts or turn an unknown report into a global outage.
Scheduling, retries and live authorization remain unchanged. Any future worker
policy needs independently bounded backoff and resource-specific decisions.
Existing engine guards use the same XtraDB evidence logic directly without
trusting the five-minute report.

Run `php tests/compatibility-readiness-regressions.php` for dependency-free
coverage of engines, metadata failures, current schema, isolated resource
failures, storage, message/path sanitization, cached-only no-probe hits/misses and
TTL validation, migration state and recheck authorization.
