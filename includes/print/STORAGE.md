# Private Storage and Upgrades

New output uses `OC_Upload_Handler::private_storage_path('print-files', true)`.
If validation fails, generation fails closed, without public print/artwork fallback.
The shared policy is implemented in `OC_Storage_Upgrade`.

**NOT universal/unattended upgrade release-ready.** Existing operations may be
blocked pending verified storage or legacy recovery. Use the
[upgrade matrix and staging checklist](../../docs/upgrade-compatibility.md) before
deployment; retaining data is not the same as uninterrupted service.

## HTTP and CLI

Private roots must remain canonical, writable, outside ABSPATH, uploads and the
known document root. A real HTTP request validates those boundaries and records
site-scoped HMAC-protected evidence with a maximum six-hour lifetime. CLI can reuse
that evidence for the same root and configuration without a document root. CLI
cannot mint evidence merely by setting server variables. The default root,
`OC_PRIVATE_STORAGE_ROOT`, and `oc_private_storage_root` filtered paths all work.

Evidence includes blog ID, home/site URLs, configured/canonical WordPress and
uploads paths, uploads URL, requested root configuration, canonical root and
filesystem device/inode. Tampered, expired, cross-site or changed-configuration
evidence is rejected. Salt rotation invalidates it. Options never supply trusted
root paths: paths are derived independently before evidence is considered.

A known document-root overlap immediately replaces matching positive evidence
with signed denial and persists a separate root-scoped revocation marker. Early
root-validation returns also perform revocation, including a document root of `/`.
CLI checks the marker before using evidence or private-root operator attestation.
Changing the requested-path alias or replaying older evidence cannot bypass it.
The marker has no permission TTL: expiry never revives old approval. A later request
with a narrower document root does not erase a contradictory vhost observation.
After correcting all relevant routing, change the trusted
`oc_storage_verification_context` deployment revision and validate afresh. Merely
changing that revision does not create new HTTP evidence for CLI. If revocation
cannot be persisted, an error instructs operators to stop CLI workers until it can.

For sites without HTTP traffic during the evidence lifetime, operators can still
configure `OC_PRIVATE_STORAGE_ROOT` with `OC_PRIVATE_STORAGE_OUTSIDE_WEB_ROOT=true`
after checking actual deployment routes. Filter-configured roots support an exact
canonical-root `oc_private_storage_outside_web_root` callback returning strictly
true. Neither path overrides a known overlapping document root. Never return true
globally or infer safety from an arbitrary option.

Private-root evidence establishes the known filesystem boundary, not absence of
unknown vhost aliases or mirrors. Sites with additional routes must verify those
independently. `oc_storage_verification_context` can supply a trusted deployment
revision so route/server changes immediately invalidate cached evidence.

## Public Subtree Verification

The persisted-token uploads fallback and legacy artwork/print trees require
explicit trusted recursive operator attestation through
`oc_private_storage_web_protected`. HTTP probes are **advisory only** and never
grant serving, writing, publication or cleanup permission for these public roots.
Optional bounded HTTPS diagnostics still run after deny files are installed:

- A random harmless text control in uploads must return HTTP 200 with exact bytes.
- Random harmless supported-artwork, print, CSV, generic and backup-suffix canaries in the denied tree must each
  return 403/404 without their secret random marker in the response.
- Requests use the configured uploads URL, WordPress safe HTTP, TLS verification,
  no cookies, no redirects, a two-second timeout and a 1 KiB response limit.
- At most one group (one control plus 15 denied-suffix probes) runs per PHP request,
  with a five-second between-probe deadline; the last request can add two seconds.
  A nonblocking filesystem lock
  prevents concurrent groups for the same root. Signed failure backoff lasts five
  minutes; advisory results are cached for at most six hours. Neither cache state
  is an authorization grant; previously signed blanket probe approvals are ignored.
- Canary files are removed in `finally`. A killed PHP process can leave harmless
  random canaries; no customer data is used by the probes.

Even successful controls and denied canaries do not establish recursive routing
policy: a parent can return 403 while an order subdirectory serves production PDFs,
and a server can reject text pretending to be an image while serving real artwork.
The root stays blocked without explicit operator policy. Probes also cannot prove
unknown aliases, alternate origins, mirrors or previously cached customer files are
protected. Configure/purge and verify those separately. Disable advisory probes with
`oc_storage_automatic_http_verification` returning false for the exact root.

`oc_private_storage_web_protected` remains the explicit operator path: return
strictly true for an exact canonical subtree only after verifying denial for the
entire subtree, nested order directories, real content and every routed origin/CDN,
not merely the harmless canaries. Never return true globally. An explicitly approved
persisted-token fallback parent covers canonical children such as `print-files`,
so write, finalization, serving and cleanup agree. Siblings, traversal and symlinked
children do not inherit approval. Public serving consumers still require intact
deny files. Removing the matching operator attestation removes permission even if
advisory probes previously appeared successful.

`OC_Storage_Upgrade::reports()` returns request-local per-root diagnostic messages.
These messages are for status/reporting, never authorization. A status UI or CLI
command should run the normal storage checks, then show these reports. No new admin
page or CLI command is registered by this helper.

## Existing Files

Legacy print serving, thumbnails, regeneration validation and retention resolution
remain blocked until the exact existing uploads print tree has trusted recursive
operator approval. Automatic checks cannot automatically approve existing Apache
or other server routing. Existing order directories are not reconstructed from current salts.
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

Idle CLI evidence expiry is intentional: after six hours without refreshed HTTP
evidence, storage operations remain blocked and metadata/rows are preserved.
Renew through a real validated HTTP request or use the independently verified
exact-root operator configuration. Migration does not mint evidence or extend its
lifetime just because old files exist. Salt changes retain the existing signature
and evidence invalidation behavior; relocation is not a salt-recovery mechanism.

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
- `OC_Storage_Upgrade::reports()` explains storage and advisory-probe failures.

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

Unknown historical print roots still require `oc_print_historical_storage_roots`
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
CLI reuse, public probes/backoff, fallback publication, configured uploads aliases,
old private artwork relocation, signed-preview and guarded VDP relocation, and
durable browser ownership. No test uses network.

Run with the plugin as filesystem sandbox:

```sh
php -d open_basedir="$PWD" -d sys_temp_dir="$PWD/tests" tests/storage-upgrade-regressions.php
php -d open_basedir="$PWD" -d sys_temp_dir="$PWD/tests" tests/print-storage-regressions.php
php -d open_basedir="$PWD" -d sys_temp_dir="$PWD/tests" tests/audit-security.php
```

Real Apache/CDN verification and WordPress/MySQL integration still require staging.
