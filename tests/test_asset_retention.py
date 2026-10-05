"""Exercise age, dependency and upgrade safety for build asset retention."""
import importlib.util
import json
from pathlib import Path
import tempfile
import unittest

spec = importlib.util.spec_from_file_location('retention', Path(__file__).resolve().parents[1] / 'scripts/prune-build-assets.py')
retention = importlib.util.module_from_spec(spec)
spec.loader.exec_module(retention)


class RetentionTests(unittest.TestCase):
    def test_grace_dependencies_and_expiry(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            (root / 'chunks').mkdir()
            active = 'chunks/current.11111111.js'
            older = 'chunks/older.22222222.js'
            dependency = 'chunks/dependency.33333333.js'
            orphan = 'chunks/orphan.44444444.js'
            for name in (active, older, dependency, orphan):
                (root / name).write_text('runtime')
            (root / older).write_text('load("33333333")')
            (root / 'release.json').write_text(json.dumps({'assets': [active]}))
            self.assertEqual([], retention.prune(root, now=100))
            ledger = {older: 200, dependency: 100, orphan: 100}
            (root / 'asset-retention.json').write_text(json.dumps(ledger))
            self.assertEqual([orphan], retention.prune(root, now=100 + 90 * 86400))
            self.assertTrue((root / dependency).exists())
            self.assertEqual(sorted([older, dependency]), retention.prune(root, now=200 + 90 * 86400))
            self.assertTrue((root / active).exists())

    def test_missing_inventory_cannot_delete_assets(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            (root / 'release.json').write_text(json.dumps({'build': 'legacy'}))
            with self.assertRaises(ValueError):
                retention.prune(root)
            with self.assertRaises(ValueError):
                retention.prune(root, days=1)


if __name__ == '__main__':
    unittest.main()
