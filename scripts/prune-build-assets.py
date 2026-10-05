#!/usr/bin/env python3
"""Retire immutable assets only after 90+ days and after retained dependants expire."""
import argparse
import json
from pathlib import Path
import re
import time


IMMUTABLE = re.compile(r'(?:chunks/.*\.([a-f0-9]{8})(?:-rtl)?\.(?:js|css)|([a-f0-9]{20})\.(?:wasm|woff2?|ttf|otf|png|jpe?g|svg|webp))$')


def prune(build, days=90, now=None):
    if days < 90:
        raise ValueError('The supported cached-page/open-tab window is at least 90 days.')
    now = int(time.time() if now is None else now)
    manifest = json.loads((build / 'release.json').read_text())
    if not isinstance(manifest.get('assets'), list) or not manifest['assets']:
        raise ValueError('Build a current release manifest before pruning.')
    active = set(manifest['assets'])
    if any(not (build / name).is_file() for name in active):
        raise ValueError('Active release files are missing; refusing to prune.')
    ledger = build / 'asset-retention.json'
    previous = json.loads(ledger.read_text()) if ledger.exists() else {}
    files = {p.relative_to(build).as_posix(): p for p in build.rglob('*')
             if p.is_file() and not p.is_symlink() and IMMUTABLE.fullmatch(p.relative_to(build).as_posix())}
    retired = {name: previous.get(name, now) for name in files if name not in active}
    if any(not isinstance(at, int) or at < 0 or at > now for at in retired.values()):
        raise ValueError('Invalid retirement ledger; refusing to prune.')
    keep = active | {name for name, at in retired.items() if now - at < days * 86400}
    # Webpack runtimes may use just the hash in a chunk map rather than its filename.
    tokens = {name: next(v for v in IMMUTABLE.fullmatch(name).groups() if v) for name in files}
    pending = list(keep)
    visited = set()
    while pending:
        name = pending.pop()
        if name in visited:
            continue
        visited.add(name)
        file = build / name
        if file.suffix not in ('.js', '.css') or not file.is_file():
            continue
        content = file.read_text()
        for dependency, token in tokens.items():
            if token in content and dependency not in keep:
                keep.add(dependency)
                pending.append(dependency)
    removed = sorted(set(files) - keep)
    for name in removed:
        files[name].unlink()
        retired.pop(name, None)
    ledger.write_text(json.dumps(retired, indent=2, sort_keys=True) + '\n')
    return removed


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--build', type=Path, default=Path(__file__).resolve().parents[1] / 'assets/build')
    parser.add_argument('--days', type=int, default=90)
    args = parser.parse_args()
    removed = prune(args.build, args.days)
    print(f'Retired {len(removed)} expired, unreferenced build assets (window: {args.days} days).')
