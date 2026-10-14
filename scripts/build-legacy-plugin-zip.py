#!/usr/bin/env python3
"""Re-root a validated release ZIP for existing GitHub source-ZIP installs."""

import copy
from pathlib import Path
import sys
import zipfile


def main():
    source, destination = map(Path, sys.argv[1:])
    with zipfile.ZipFile(source) as original:
        entries = original.infolist()
        if not entries or any(not entry.filename.startswith('overcustomise/')
                              for entry in entries):
            raise ValueError('Expected a validated overcustomise release ZIP')
        with zipfile.ZipFile(destination, 'w') as output:
            for entry in entries:
                renamed = copy.copy(entry)
                renamed.filename = 'personaliseit-main/' + entry.filename.split('/', 1)[1]
                output.writestr(renamed, original.read(entry))

        # Verify every byte against the validated release, with only its root changed.
        with zipfile.ZipFile(destination) as output:
            assert len(output.infolist()) == len(entries)
            for entry in entries:
                name = 'personaliseit-main/' + entry.filename.split('/', 1)[1]
                assert output.read(name) == original.read(entry), name
    print(f'Legacy install ZIP (contents verified): {destination}')


if __name__ == '__main__':
    main()
