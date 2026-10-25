# Third-party notices

## DejaVu Sans preview fallback

`assets/fonts/oc-preview-fallback.ttf` is an unmodified, decompressed copy of
`vendor/tecnickcom/tc-lib-pdf-font/target/fonts/dejavu/dejavusans.z`, also emitted
into the browser build. It provides the same missing-character outlines in the
customiser and production exports. DejaVu changes are public domain; the font
includes Bitstream Vera outlines under the Bitstream Vera font license. See
`vendor/tecnickcom/tc-lib-pdf-font/target/fonts/dejavu/LICENSE` for the license.

## tc-lib-barcode

Bundled in `vendor/tecnickcom/tc-lib-barcode/`, copyright 2001–2026
Nicola Asuni / Tecnick.com LTD, LGPL-3.0-or-later; see its bundled `LICENSE`.
Local documentation corrections fill in the code-of-conduct contact already
listed in `CONTRIBUTING.md` and align the pull-request template with that
document's maintainer-managed version policy. Local development-only fixes correct
the example's table-header background CSS property and align Mago schema comments
with the Makefile's pinned tool version (1.42.0). The Model interface documentation
is corrected. Local runtime changes limit raster images (including padding) to
16,000,000 pixels and explicitly throw library exceptions when GD or Imagick is
unavailable. The existing 30,000-pixel side limit remains; vector output is unchanged.
Additional runtime corrections validate rectangular binary grids, reject embedded
Codabar/Code 11 delimiters and empty automatic Code 128 input, preserve Code 93
payload case, and append Code 128 sequence segments without repeatedly copying
the accumulated array. Code 128 A/B/C class documentation is also corrected.
Code 39 rejects embedded start/stop markers in basic mode and preserves case in
extended modes. EAN input is restricted to digits within the format's maximum
length, retaining existing short-input padding. Intelligent Mail input now checks
the full tracking-number length, identifier range, and separator count; pre-encoded
Intelligent Mail input requires an exact 65-symbol sequence without trailing data.
Interleaved 2-of-5 rejects empty/non-digit input and weights checksums from the
rightmost data digit. Both MSI variants reject empty input. Pharmacode validates
digit-only values before integer conversion (one-track: 3–131070; two-track:
4–64570080). POSTNET and PLANET enforce their respective data lengths after
separator normalization, before adding the check digit.
Royal Mail and Standard 2-of-5 reject missing data; both 2-of-5 checksum variants
share right-to-left weighting. UPC-A no longer appends a spurious zero to supplied
check digits. UPC-E validates digits, number systems, and zero-suppression rules.
Aztec fixes include bounded binary runs, the ECI/FNC1 flag marker, and parameter
documentation. PDF417 repeats encoded rows vertically rather than concatenating
them horizontally, preserving rectangular grids under the stricter validation.
Aztec promotes compact symbols beyond 64 data words to full mode and validates
mode-message and Reed-Solomon bounds. Data Matrix now adds initial GS1 FNC1
(without duplicating explicit initial markers), corrects 12x36/144x144 capacities,
handles unequal data blocks and parity ordering for 144x144, and bounds ordinary
Base256 segments at 1555 bytes with explicit support for the terminal 1556-byte
zero-length-header form in a maximum-size symbol.
Data Matrix placement helpers update their working map by reference rather than
copying it at each placement step, and C40/X12 tie-breaking scans shared characters
correctly. PDF417 reserves its length descriptor in the ECC budget, checks capacity
after macro data, chooses legal non-truncating layouts, and uses explicit integer
BCMath scales without changing the caller's scale. QR default-mask IDs normalize
to 0–7, including negative inputs.
QR helpers preserve existing bits on zero-length appends, estimate all segments
and structured-append headers with exact count-limit splitting, reset reusable
splitters, and restrict Reed-Solomon data slices to their block boundaries. QR
input items reject missing/non-byte data and invalid structured-append headers;
direct encoder calls validate mask IDs, byte counts and ranges, versions and ECC
levels. Invalid frame versions and Reed-Solomon symbol sizes raise library
exceptions before arithmetic, and random mask candidate counts clamp to 1–8.
The vendor QR input-item test now supplies all requested bytes and covers rejection
of undersized input rather than expecting fabricated zero characters.
Vendor export tests find Content-Disposition by header name rather than position,
and omit only header assertions on CLI runtimes without Xdebug header capture;
all response bodies are still checked. Additional vendor tests cover malformed
binary grids, conversion reuse, exact QR mode/padding/split bitstreams, Codabar
punctuation and guard rejection, Code 93 case/control preservation and checksum
weight wraparound, and Code 11 checksum-length boundaries and invalid payloads.
Further vendor regressions cover Code 128 A/B byte ranges and function codes,
Code C FNC1 boundaries, exact automatic SHIFT/latch codewords, Code 39 modulo-43
checks and full-ASCII expansion, and EAN-8/EAN-5 checksums, parity, padding and
invalid inputs. Explicit Code 128 A/B/C encoders now reject empty payloads,
matching the automatic encoder.
Vendor postal/numeric tests now separate valid IMB routing vectors from malformed
tracking-number rejection and use an in-range Pharmacode fixture. Added coverage
checks all EAN-13/EAN-2 parity classes, IMBPRE case/length/state boundaries, I25
checksum/padding order, KIX letters and bar counts, legacy MSI hexadecimal and
modulo-11 edge cases, and Pharmacode range boundaries and leading zeros.
Additional vendor fixtures use legal two-track Pharmacode, PLANET and POSTNET
payloads, check postal/numeric boundaries and framing, and reject non-compressible
UPC-A inputs rather than expecting lossy UPC-E conversion. UPC-E cases run
independently. The corrected Aztec GS1 snapshot is backed by independent payload
and symbology-identifier decoding; compact promotion and 31/32-byte binary-header
boundaries also have vendor and decoder regressions.
Fourteen Data Matrix, PDF417 and QR snapshots were refreshed after independent
payload decoding. Vendor tests now assert GS1 marker equivalence, Base256 maximum
dimensions, PDF417 legal layouts/row repetition/macro limits/BCMath scale, and QR
mixed-segment estimates and mask normalization. The corpus has named byte-oriented
cases including NUL/high bytes, and rectangle tests cover fractional scale/padding.
The shared test utility's copied package descriptions are corrected.
These corrections may need reapplying after Composer replaces the package.
Development metadata is omitted from release ZIPs; license notices are retained.

## tc-lib-color

Bundled in `vendor/tecnickcom/tc-lib-color/`, copyright 2015–2026
Nicola Asuni / Tecnick.com LTD, LGPL-3.0-or-later; see its bundled `LICENSE`.
Local documentation-only changes clarify the bug-report template's reproducer,
expected/actual results, dependency versions, extensions and log redaction.
Contributor guidance, templates and the example URL are aligned; the conduct
reporting contact uses the existing maintainer address. Development build phases
run sequentially even under parallel make, the duplicate installer version flag
is removed, and CI cancels superseded runs for the same workflow/ref.
The example bootstrap supports application, standalone Composer, consumer Composer
and system autoloaders and reports missing dependencies explicitly. Mago schema
comments match the pinned tool version; PHPUnit fails on warnings, notices and
deprecations. Composer formatting, Mago prerequisites, security-report component
examples and the system autoloader's license pointer are corrected. CSS and Acrobat
JavaScript parsing validates complete numeric input and finite values; HSL hues
wrap cyclically. The dispatcher recognizes only explicit transparent forms. Base
model constructors reject nonnumeric/non-finite components while retaining numeric
strings and finite range clamping. Packaging descriptions, Debian license metadata
and reviewed source license pointers match the bundled notice. Regression tests
cover parsing, hue wrapping and component validation. Composer replacement may
overwrite these changes.

Lab constructors now validate numeric/finite components using native Lab ranges.
Spot tints and Lab metadata reject non-finite/invalid values before registration;
Lab options require positional triplets/ranges, valid white/blackpoints and ordered
ranges. Public normalization retains invalid-value fallback to zero but rejects
nonpositive maxima; hex extraction requires a complete match. Interface docs and
reviewed license pointers are corrected. Endpoint, alpha and double-inversion
tests supplement the CMYK/Gray fixtures, with dedicated validation regressions.
Additional color tests cover HSL lightness endpoints, RGB primary/white vectors,
Lab neutral endpoints and in-gamut round trips, alpha preservation, inversion,
registered Lab PDF output, tint-wrapper registry safety and Lab/CMYK replacement.
Reviewed test descriptions, stale comments and license pointers are corrected.

## tc-lib-file

Bundled in `vendor/tecnickcom/tc-lib-file/`, copyright 2015–2026
Nicola Asuni / Tecnick.com LTD, LGPL-3.0-or-later; see its bundled `LICENSE`.
Local development-only changes apply two-space indentation to both
YAML extensions and expand the bug-report template with minimal byte-level
reproducers, actual results, dependency/stream context and log-redaction guidance.
Runtime files are unchanged. Composer replacement may overwrite these changes.

## Plugin Update Checker

Bundled in `includes/plugin-update-checker/` from the stable
[v5.7 release](https://github.com/YahnisElsts/plugin-update-checker/releases/tag/v5.7),
commit `275a96a2a18d03c34c87f35cb68673c8c49ac3b1`.
Copyright 2026 Janis Elsts, MIT license; see the bundled `license.txt`.
The upstream distribution includes its readme parser and Parsedown dependencies
with their license notices, translations, and UI assets. No Composer installation
is needed for this library. Its PHP minimum (5.6.20) is below this plugin's 8.2 minimum.
The versioned namespaces and shared v5 factory allow coexistence with other PUC v5 bundles.
Upstream files are unmodified. The additional `vendor/LICENSE.txt` supplies the
[Parsedown 1.6.0 MIT license](https://github.com/erusev/parsedown/blob/1.6.0/LICENSE.txt)
referenced by the bundled parser.

## Twemoji

UV print exports retrieve and locally cache Twemoji 17.0.2 SVG emoji artwork
from the WordPress emoji CDN (`s.w.org`). Graphics are licensed under
[CC BY 4.0](https://creativecommons.org/licenses/by/4.0/), copyright Twitter,
Inc. and other contributors. See [Twemoji](https://github.com/jdecked/twemoji).
The exporter scales and positions the artwork alongside outlined lettering;
white-ink plates use its visible silhouette.

## d3-celestial

The Night Sky star catalogue and constellation line data are derived from
[d3-celestial](https://github.com/ofrohn/d3-celestial), copyright (c) 2015
Olaf Frohn.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:

1. Redistributions of source code must retain the above copyright notice,
   this list of conditions and the following disclaimer.
2. Redistributions in binary form must reproduce the above copyright notice,
   this list of conditions and the following disclaimer in the documentation
   and/or other materials provided with the distribution.
3. Neither the name of the copyright holder nor the names of its contributors
   may be used to endorse or promote products derived from this software
   without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS “AS IS”
AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR
ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
