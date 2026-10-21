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
from pathlib import Path, PurePosixPath
import sys
import zipfile
import zlib


COMPRESSED_LIMIT = 34_000_000
UNCOMPRESSED_LIMIT = 64_000_000
REQUIRED = (
    'assets/css/admin.css',
    'assets/css/admin-help.css',
    'assets/js/admin-help.js',
    'assets/js/admin-customer-uploads.js',
    'overcustomise.php',
    'vendor/autoload.php',
    'vendor/composer/autoload_real.php',
    'vendor/composer/autoload_static.php',
    'vendor/composer/ClassLoader.php',
    'vendor/tecnickcom/tcpdf/tcpdf.php',
    'includes/plugin-update-checker/plugin-update-checker.php',
    'includes/plugin-update-checker/load-v5p7.php',
    'includes/plugin-update-checker/license.txt',
    'includes/plugin-update-checker/vendor/LICENSE.txt',
    'includes/plugin-update-checker/vendor/Parsedown.php',
    'includes/plugin-update-checker/vendor/ParsedownModern.php',
    'includes/plugin-update-checker/vendor/PucReadmeParser.php',
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
    for directory in ('includes', 'templates'):
        required.update(file.relative_to(root).as_posix()
                        for file in (root / directory).rglob('*.php') if file.is_file())
    required.update(file.relative_to(root).as_posix()
                    for file in (root / 'includes/plugin-update-checker').rglob('*')
                    if file.is_file())
    # Font data is runtime coverage, not tooling. Protect it when trimming vendor.
    for package in (root / 'vendor/tecnickcom').glob('*'):
        for directory in ('src', 'target/fonts'):
            required.update(file.relative_to(root).as_posix()
                            for file in (package / directory).rglob('*') if file.is_file())

    compressed = archive.stat().st_size
    with zipfile.ZipFile(archive) as bundle:
        entries = bundle.infolist()
        uncompressed = sum(entry.file_size for entry in entries)
        files = {entry.filename: entry for entry in entries if not entry.is_dir()}
        for entry in entries:
            parts = PurePosixPath(entry.filename).parts
            if (not parts or parts[0] != 'overcustomise' or '..' in parts
                    or '\\' in entry.filename or entry.filename.startswith('/')):
                failures.append('Invalid ZIP path or package root: ' + entry.filename)
            if any(part in {'.git', '.github', 'node_modules', 'tests', 'test',
                            'examples', 'example', '.env'} or part.startswith('.env.')
                   for part in parts):
                failures.append('Development or secret file in ZIP: ' + entry.filename)
            if len(parts) > 2 and parts[1] == 'vendor' and parts[2] not in {
                    'autoload.php', 'composer', 'tecnickcom'}:
                failures.append('Non-production Composer package: ' + entry.filename)
            if (len(parts) > 4 and parts[1:3] == ('vendor', 'tecnickcom')
                    and (parts[4] in {'scripts', '.gitignore', 'CODEOWNERS'} or
                         (len(parts) > 5 and parts[4] == 'resources'
                          and parts[5] in {'css', 'preflight'}))):
                failures.append('Vendor development tooling: ' + entry.filename)
        for name in sorted(required):
            entry = files.get('overcustomise/' + name)
            if entry is None:
                failures.append('Missing required file: overcustomise/' + name)
            elif entry.file_size == 0 and (name in REQUIRED or (root / name).stat().st_size > 0):
                failures.append('Empty required file: overcustomise/' + name)
            elif (root / name).is_file():
                crc = 0
                with (root / name).open('rb') as source:
                    for chunk in iter(lambda: source.read(1024 * 1024), b''):
                        crc = zlib.crc32(chunk, crc)
                if entry.CRC != crc:
                    failures.append('Stale or mismatched file: overcustomise/' + name)
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
