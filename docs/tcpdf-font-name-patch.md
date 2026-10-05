# TCPDF PostScript name compatibility patch

The tracked `vendor/tecnickcom/tc-lib-pdf-font/src/Import/TrueType.php`
contains a local fix in `getFontName()` for engraving font imports:

- Skip empty/unusable name-ID 6 records and try subsequent records.
- When no usable PostScript name exists, use a deterministic ASCII PDF resource
  name derived from the source font. The embedded source, outlines and metrics
  remain unchanged.
- Reject out-of-bounds name tables and string references.

Retain this fix when refreshing Composer dependencies until upstream supplies
equivalent handling. Run `php tests/print-font-name-regressions.php` after any
dependency refresh; it exercises the actual bundled importer and verified cache
with normal, empty, missing, unusable and corrupt name records. It is also part of
`composer test`.

After deploying the patched plugin (including `vendor`), regenerate failed print
jobs. Failed imports do not publish a cache bundle, so this name-metadata failure
does not require deleting fonts or clearing verified caches.
