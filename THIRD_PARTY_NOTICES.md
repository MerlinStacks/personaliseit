# Third-party notices

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
