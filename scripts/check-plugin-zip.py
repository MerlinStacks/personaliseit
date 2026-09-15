#!/usr/bin/env python3
"""Read-only release check: python3 -B scripts/check-plugin-zip.py [archive.zip].

Requires only Python 3's standard library (provided by ubuntu-latest CI).
Budgets use decimal MB and cover the WHOLE archive, including bundled fonts:
34 MB on disk and 64 MB summed uncompressed entry sizes. The existing release
is approximately 27 MB / 50.8 MB, leaving roughly 26% growth headroom. Review
intentional growth before changing these limits; this checker removes nothing.
Build inventory includes retained chunks, asset PHP manifests, fonts and other
runtime files, not just JS/CSS. Source maps are intentionally optional, matching
the .distignore policy and webpack's disabled production source maps. Any maps
that are packaged still count toward the whole-archive budgets.
"""

import argparse
from pathlib import Path
import sys
import zipfile


COMPRESSED_LIMIT = 34_000_000
UNCOMPRESSED_LIMIT = 64_000_000
REQUIRED = (
    'assets/css/admin.css',
    'assets/js/admin-customer-uploads.js',
    'overcustomise.php',
    'vendor/autoload.php',
    'vendor/composer/autoload_real.php',
    'vendor/composer/autoload_static.php',
    'vendor/composer/ClassLoader.php',
    'vendor/tecnickcom/tcpdf/tcpdf.php',
)


def check_archive(archive, root, compressed_limit=COMPRESSED_LIMIT,
                  uncompressed_limit=UNCOMPRESSED_LIMIT):
    """Return measurements and failures; never extract or modify the ZIP."""
    required = set(REQUIRED)
    build = root / 'assets/build'
    runtime = [file for file in build.rglob('*')
               if file.is_file() and file.suffix != '.map']
    failures = []
    if not runtime:
        failures.append('Missing or empty local assets/build runtime inventory.')
    required.update(file.relative_to(root).as_posix() for file in runtime)
    # Static admin modules are registered directly by WordPress, outside webpack.
    required.update(file.relative_to(root).as_posix()
                    for file in (root / 'assets/css/admin').rglob('*.css')
                    if file.is_file())
    # Include generated platform checks and autoload metadata when present.
    required.update(file.relative_to(root).as_posix()
                    for file in (root / 'vendor/composer').rglob('*.php')
                    if file.is_file())

    compressed = archive.stat().st_size
    with zipfile.ZipFile(archive) as bundle:
        entries = bundle.infolist()
        uncompressed = sum(entry.file_size for entry in entries)
        files = {entry.filename: entry for entry in entries if not entry.is_dir()}
        for name in sorted(required):
            entry = files.get('overcustomise/' + name)
            if entry is None:
                failures.append('Missing required file: overcustomise/' + name)
            elif entry.file_size == 0 and (name in REQUIRED or (root / name).stat().st_size > 0):
                failures.append('Empty required file: overcustomise/' + name)
        if len({entry.filename for entry in entries}) != len(entries):
            failures.append('Duplicate ZIP entry names.')
        if compressed > compressed_limit:
            failures.append(f'Compressed ZIP exceeds budget: {compressed} > {compressed_limit} bytes.')
        if uncompressed > uncompressed_limit:
            failures.append(f'Uncompressed ZIP exceeds budget: {uncompressed} > {uncompressed_limit} bytes.')
        # Avoid decompressing an already oversized archive just to check CRCs.
        if not failures:
            bad = bundle.testzip()
            if bad:
                failures.append('Corrupt ZIP entry: ' + bad)
    return compressed, uncompressed, failures


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('archive', nargs='?', type=Path, default=Path('overcustomise.zip'))
    parser.add_argument('--root', type=Path, default=Path(__file__).resolve().parents[1],
                        help='Checkout containing the build inventory (default: script checkout).')
    args = parser.parse_args()
    try:
        compressed, uncompressed, failures = check_archive(args.archive, args.root.resolve())
    except (OSError, zipfile.BadZipFile, RuntimeError, NotImplementedError) as error:
        print(f'Plugin ZIP check failed: {error}', file=sys.stderr)
        return 1
    print(f'Whole ZIP: {compressed:,} compressed bytes (limit {COMPRESSED_LIMIT:,}); '
          f'{uncompressed:,} uncompressed bytes (limit {UNCOMPRESSED_LIMIT:,}).')
    for failure in failures:
        print(failure, file=sys.stderr)
    if not failures:
        print('Plugin ZIP completeness, integrity and size checks passed.')
    return int(bool(failures))


if __name__ == '__main__':
    sys.exit(main())
