# Private Storage and Upgrades

New output uses `OC_Upload_Handler::private_storage_path('print-files', true)`.
Automatic selection uses the default root or persisted-token uploads fallback.
If no valid writable destination is available, generation fails closed.
The shared policy is implemented in `OC_Storage_Upgrade`.

**NOT universal/unattended upgrade release-ready.** Existing operations may be
blocked by actual filesystem failures or legacy recovery, not missing HTTP proof.
No mandatory server setup or public approval is required for automatic storage. Use the
[upgrade matrix and staging checklist](../../docs/upgrade-compatibility.md) before
deployment; retaining data is not the same as uninterrupted service.

## HTTP and CLI

The canonical writable default is selected automatically, outside known web paths.
When unavailable or subject to a known actual document-root contradiction, automatic
selection uses the persisted-token uploads fallback. Both HTTP and CLI work without
positive signed evidence, including after expiry or long idle periods. No runtime
HTTP probes run. Signed evidence is optional diagnostic context, not an operational
prerequisite or proof against unknown routes. Known actual contradictions remain
root-specific, including for CLI; they are not erased by missing/expired positive
evidence and do not impose a blanket block on the automatic fallback.

`OC_PRIVATE_STORAGE_ROOT` and `oc_private_storage_root` remain optional custom-root
configuration. Explicit custom roots that are invalid or unwritable fail closed
rather than silently selecting another root. No arbitrary historical custom root
is discovered or migrated. Existing exact-root protection filters are optional
deployment diagnostics, not required public approval for default operation. Never
infer an exact trusted root from arbitrary request data or database paths.

## HTTP Protection

Automatic storage installs Apache `.htaccess` and IIS `web.config` deny rules.
Canonical paths, writable directories and intact protection files establish
filesystem readiness, not verified direct HTTP security. Deny-file presence does
not prove the server honors those rules. Nginx or Apache with overrides disabled
may expose public static files. Aliases, alternate origins, mirrors and CDN caches
need independent security review; the plugin cannot promise security on every host.

The persisted-token fallback and legacy public artwork/print trees operate without
mandatory server configuration or explicit `oc_private_storage_web_protected`
approval. The optional filter is not an automatic-setup requirement. No canary
probes run in runtime storage checks, and old signed probe results are not proof of
recursive protection. Order/item authorization remains independently enforced.

The exact diagnostic is:

`Automatic storage is operational; direct HTTP protection has not been verified.`

System Status maps it to nonblocking `storage_http_protection_unverified`. Storage
resources remain `ready` when filesystem checks succeed and print is not paused by
this warning. Actual unwritable directories, invalid canonical paths or failed
protection-file writes still block affected operations. Review HTTP exposure as a
separate security concern, including real nested content and retained source URLs;
automatic functionality does not secure old cached files or purge a CDN.

`OC_Storage_Upgrade::reports()` returns request-local per-root diagnostic messages.
These messages are for status/reporting, never authorization. A status UI or CLI
command should run the normal storage checks, then show these reports. No new admin
page or CLI command is registered by this helper.

## Existing Files

Legacy print serving, thumbnails, regeneration validation and retention resolution
use automatic storage checks and deny files without mandatory recursive operator
approval. This does not verify direct-file HTTP protection. Existing order
directories are not reconstructed from current salts.
Order/item authorization remains separate from storage validation.

`canonical_file()` accepts the configured uploads-root symlink alias only, and only
when every path component below that root matches its canonical target. It rejects
per-file/subdirectory symlinks, traversal, relative paths and arbitrary aliases.
The print resolver returns the canonical path; it does not rewrite DB rows itself.

Marked artwork in the exact prior deterministic private default or persisted-token
fallback can relocate to the selected private artwork destination. A separate
destination-identity marker prevents the old storage-format version from hiding
these attachments. Migration remains cursor-based and stops between records after
its time/byte budget; an individual private bundle is capped at 100 MiB/25 filenames.
Per-attachment migration locks and a compare-and-swap path update prevent duplicate
publication by this migrator. Main files and derivatives are copied and hash checked
before publication; attachment IDs, owner/context fields and metadata are preserved.

Old private source copies are deliberately retained and logged, pending a separate
reference-safe source cleanup. They are NOT declared secured merely because the new
copy is private. Existing source HTTP exposure still needs denial/purge. Arbitrary
previous custom roots are not discovered from DB paths and need operator handling.

## Preview and VDP Relocation

Private preview reads lazily relocate a matching file from the exact old default
or persisted-token fallback `previews` directory into verified current storage.
The filename, metadata JSON, content hash, creation time, ID and signing secret
remain unchanged. Existing signed URLs therefore remain valid. Signed entry points
verify the signature before doing relocation I/O. Sources must match the stored
byte count, MIME and SHA-256 hash; arbitrary roots and symlink/traversal aliases are
rejected. Conflicting destination files are never overwritten. An unavailable old
preview record is retained rather than replaced with a new signing secret on save.

Private VDP rows are migrated in batches of up to 25 by the existing storage init
maintenance entry point, with a separate wrapping cursor and a two-second
between-record deadline. Each CSV is limited to 5 MiB. The destination name is
stable for the row/design/source/content identity, so retrying an uncertain commit
does not create another copy. Only `csv_file_path` is changed, using a conditional
update matching row ID, design ID and original path. Fields, active state, template
identity and order/snapshot authorization are not changed. Missing or unknown-root
rows remain pending and do not prevent later rows from being reached.

Both migrations use bounded reads, SHA-256 verification and atomic no-overwrite
same-filesystem hard-link publication. Hosts that cannot perform that publication
fail closed. Sources are retained; an uncertain VDP update also retains the possibly
published destination. Old source HTTP exposure still requires denial/purge.

Storage-only integration APIs (not authorization grants):

- `OC_Rest_API::relocate_private_preview($id): ?string` resolves/migrates a preview
  and returns its current path, or null when unresolved. No signing secret is returned.
- `OC_Rest_API::relocate_private_vdp_template($template_id): ?string` rereads the
  authoritative row, performs guarded relocation when needed, and returns the path.
- `OC_Storage_Upgrade::known_private_file($path, $subdirectory)` restricts migration
  sources to exact known `previews` or `vdp` roots. It does not authorize serving.
- `OC_Storage_Upgrade::reports()` includes relocation failures and retained-source
  notices. System Status consumes these after storage checks with sanitized,
  exact-message mappings; unknown messages receive generic guidance. There is no
  new CLI command.

**Cleanup integration is implemented:** when a valid preview record has no safe
current-root file, cleanup calls the preview relocation API and skips deletion for
that pass. Unresolved valid metadata remains retained; malformed records still have
separate cleanup handling. Current-file deletion remains subject to retention and
reference checks. `OC_VDP::get_template()` also uses guarded relocation; unavailable
storage raises an error rather than silently falling back to a single output.
Bulk proactive preview relocation, reference-safe old-source cleanup, and unknown
former custom-root migration remain out of scope. The existing public-to-private
VDP migration remains separate.

Idle CLI operation does not require refreshed positive HTTP evidence. Missing or
expired evidence is not a migration gate; actual storage failures retain metadata
and rows. Salt changes retain the existing signature invalidation behavior;
relocation is not a salt-recovery mechanism.

## Print Integration and Limits

The generator/maintenance owner can use these APIs:

- `OC_Print_Base::output_migration_roots()` inventories existing exact legacy print
  roots and print children of the known prior default/fallback. These are migration
  sources ONLY, not serving, recursive deletion or uninstall grants.
- `OC_Storage_Upgrade::canonical_file($path)` validates the known uploads alias.
- `OC_Storage_Upgrade::file_reference_paths($path)` returns the canonical and known
  configured-uploads spellings for shared-reference SQL. It grants no root access.
- `OC_Print_Base::output_storage_roots()` returns serving-authorized roots.
- `OC_Print_Base::resolve_output_storage_path($path, true)` resolves retained files.
- `OC_Storage_Upgrade::reports()` explains storage failures and unverified HTTP protection.

No print-file migration or DB-path rewrite is implemented here. A future migrator
must lock output identities, verify copies, atomically update all shared file and
thumbnail references, and delete old bytes only after reference checks. Missing
mounts remain pending, never evidence of deletion.

Generator artifact removal, regeneration finalization and cleanup shared-reference
queries now check both known canonical and configured uploads-alias spellings via
`file_reference_paths()`. A reference through either spelling protects the file;
unknown reference counts retain it. The cleanup missing-file branch deliberately
retains missing aliases and unavailable historical mounts rather than treating
uncertain identity as deletion evidence. This integration does not authorize
arbitrary aliases, rewrite stored paths, or implement print relocation.

Unknown historical print roots can optionally use `oc_print_historical_storage_roots`
from trusted operator code, with exact canonical `print-files` directories. That
existing contract also permits destructive uninstall; do not populate it from
request data or arbitrary DB rows/options. Under-uploads history still requires
the shared public policy. Keep old mounts available until migration/retention ends.

## Browser Ownership

New artwork records retain a site-scoped HMAC principal derived only from a valid
signed browser cookie. A rotated live request token from that principal can still
authorize the exact attachment context. New cookies have a 30-day browser lifetime;
existing correctly signed cookies and genuine WooCommerce session ownership remain
supported. IP bindings remain rejected. No legacy token-only attachment is assigned
to a new browser merely because it has the old shared IP token. Cookie loss or salt
rotation still needs independent user/session ownership or controlled recovery.

## Verification

`tests/storage-upgrade-regressions.php` uses the real storage consumers with stubbed
HTTP and plugin-local fixtures. It covers evidence tampering/expiry/configuration,
CLI behavior, automatic fallback publication, configured uploads aliases,
old private artwork relocation, signed-preview and guarded VDP relocation, and
durable browser ownership. No test uses network.

Run with the plugin as filesystem sandbox:

```sh
php -d open_basedir="$PWD" -d sys_temp_dir="$PWD/tests" tests/storage-upgrade-regressions.php
php -d open_basedir="$PWD" -d sys_temp_dir="$PWD/tests" tests/print-storage-regressions.php
php -d open_basedir="$PWD" -d sys_temp_dir="$PWD/tests" tests/audit-security.php
```

Real Apache/CDN verification and WordPress/MySQL integration still require staging.
