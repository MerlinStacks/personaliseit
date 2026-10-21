"""Dependency-free packaging regressions; fixtures never touch the checkout."""

import importlib.util
import json
import re
import shutil
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest
import zipfile


ROOT = Path(__file__).resolve().parents[1]
SCRIPT = ROOT / 'scripts/check-plugin-zip.py'
SPEC = importlib.util.spec_from_file_location('check_plugin_zip', SCRIPT)
CHECKER = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(CHECKER)


class PluginZipTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(prefix='plugin-zip-')
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)
        self.archive = self.root / 'release.zip'
        self.build_files = (
            'assets/build/frontend/customiser-app.js',
            'assets/build/frontend/customiser-app.asset.php',
            'assets/build/chunks/retained.12345678.js',
            'assets/build/upload-tools.css',
            'assets/build/fonts/example.woff2',
            'assets/build/data/runtime.json',
        )
        self.names = list(CHECKER.REQUIRED) + list(self.build_files)
        self.names.append('assets/css/admin/base.css')
        self.names.append('vendor/composer/platform_check.php')
        for name in self.names + ['assets/build/frontend/customiser-app.js.map']:
            file = self.root / name
            file.parent.mkdir(parents=True, exist_ok=True)
            file.write_bytes(b'runtime fixture')

    def write_zip(self, omit=None, empty=None, prefix='overcustomise/'):
        with zipfile.ZipFile(self.archive, 'w', zipfile.ZIP_DEFLATED) as bundle:
            for name in self.names:
                if name != omit:
                    bundle.writestr(prefix + name, b'' if name == empty else b'runtime fixture')

    def check(self, **limits):
        return CHECKER.check_archive(self.archive, self.root, **limits)

    def test_complete_archive_and_optional_maps(self):
        self.write_zip()
        self.assertEqual([], self.check()[2])

    def test_every_required_and_discovered_runtime_file(self):
        for name in self.names:
            with self.subTest(name=name):
                self.write_zip(omit=name)
                self.assertIn('Missing required file: overcustomise/' + name, self.check()[2])

    def test_empty_bootstrap(self):
        self.write_zip(empty='vendor/autoload.php')
        self.assertIn('Empty required file: overcustomise/vendor/autoload.php', self.check()[2])

    def test_same_named_stale_assets_are_rejected(self):
        self.write_zip()
        (self.root / self.build_files[0]).write_bytes(b'new release contents')
        self.assertIn('Stale or mismatched file: overcustomise/' + self.build_files[0], self.check()[2])

    def test_runtime_fonts_cannot_be_trimmed_as_tooling(self):
        name = 'vendor/tecnickcom/tc-lib-pdf-font/target/fonts/cid0/example.json'
        file = self.root / name
        file.parent.mkdir(parents=True)
        file.write_bytes(b'font metrics')
        self.write_zip()
        self.assertIn('Missing required file: overcustomise/' + name, self.check()[2])

    def test_empty_emitted_entry_is_allowed_only_when_empty_locally(self):
        name = self.build_files[0]
        self.write_zip(empty=name)
        self.assertIn('Empty required file: overcustomise/' + name, self.check()[2])
        (self.root / name).write_bytes(b'')
        self.assertEqual([], self.check()[2])

    def test_wrong_archive_root(self):
        self.write_zip(prefix='wrong/')
        self.assertTrue(self.check()[2])

    def test_extra_roots_unsafe_paths_and_development_files(self):
        for name in ('other/file.php', 'overcustomise/../escape.php',
                     'overcustomise/.env.production', 'overcustomise/tests/test.php',
                     'overcustomise/vendor/phpunit/phpunit/main.php',
                     'overcustomise/vendor/tecnickcom/tc-lib-barcode/.gitignore',
                     'overcustomise/vendor/tecnickcom/tc-lib-barcode/CODEOWNERS',
                     'overcustomise/vendor/tecnickcom/tcpdf/scripts/example_smoke.php'):
            with self.subTest(name=name):
                self.write_zip()
                with zipfile.ZipFile(self.archive, 'a') as bundle:
                    bundle.writestr(name, b'unwanted')
                self.assertTrue(self.check()[2])

    @unittest.skipUnless(shutil.which('zip'), 'zip executable is required')
    def test_production_vendor_cleanup_preserves_licenses(self):
        script = (ROOT / 'scripts/build-plugin-zip.sh').read_text()
        patterns = re.findall(r"'(overcustomise/vendor/tecnickcom/[^']+)'", script)
        prefix = 'overcustomise/vendor/tecnickcom/tc-lib-barcode/'
        removed = ('.gitignore', 'CODEOWNERS', '.github/workflows/check.yml',
                   'CODE_OF_CONDUCT.md', 'CONTRIBUTING.md', 'SECURITY.md', 'Makefile')
        retained = ('LICENSE', 'README.md', 'VERSION', 'RELEASE', 'src/Barcode.php')
        with zipfile.ZipFile(self.archive, 'w') as bundle:
            for name in removed + retained:
                bundle.writestr(prefix + name, b'fixture')
        subprocess.run(['zip', '-d', str(self.archive), *patterns],
                       check=True, capture_output=True)
        with zipfile.ZipFile(self.archive) as bundle:
            self.assertEqual({prefix + name for name in retained}, set(bundle.namelist()))

    def test_updater_non_php_dependencies_are_required(self):
        name = 'includes/plugin-update-checker/languages/plugin-update-checker-fr_FR.mo'
        file = self.root / name
        file.parent.mkdir(parents=True)
        file.write_bytes(b'translation')
        self.write_zip()
        self.assertIn('Missing required file: overcustomise/' + name, self.check()[2])

    def test_missing_or_map_only_build_inventory(self):
        self.write_zip()
        for name in self.build_files:
            (self.root / name).unlink()
        self.assertIn('Missing or empty local assets/build runtime inventory.', self.check()[2])

    def test_whole_archive_budgets_and_exact_boundary(self):
        self.write_zip()
        with zipfile.ZipFile(self.archive, 'a', zipfile.ZIP_DEFLATED) as bundle:
            bundle.writestr('overcustomise/vendor/tecnickcom/fonts/large.dat', b'x' * 100_000)
        compressed, uncompressed, _ = self.check()
        self.assertEqual([], self.check(compressed_limit=compressed,
                                      uncompressed_limit=uncompressed)[2])
        self.assertTrue(any('Compressed ZIP exceeds' in error for error in
                            self.check(compressed_limit=compressed - 1)[2]))
        self.assertTrue(any('Uncompressed ZIP exceeds' in error for error in
                            self.check(uncompressed_limit=uncompressed - 1)[2]))

    def test_cli_success_and_failure(self):
        for omit, expected in [(None, 0), ('assets/css/admin.css', 1)]:
            self.write_zip(omit=omit)
            result = subprocess.run([sys.executable, '-B', str(SCRIPT), str(self.archive),
                                     '--root', str(self.root)], capture_output=True, text=True)
            self.assertEqual(expected, result.returncode, result.stdout + result.stderr)

    def test_cli_invalid_zip(self):
        self.archive.write_bytes(b'not a ZIP')
        result = subprocess.run([sys.executable, '-B', str(SCRIPT), str(self.archive),
                                 '--root', str(self.root)], capture_output=True, text=True)
        self.assertEqual(1, result.returncode)
        self.assertIn('Plugin ZIP check failed:', result.stderr)

    def test_static_assets_explicitly_packaged(self):
        package = json.loads((ROOT / 'package.json').read_text())
        for name in ('assets/css/admin.css', 'assets/css/admin',
                     'assets/js/admin-customer-uploads.js'):
            self.assertIn(name, package['files'])


if __name__ == '__main__':
    unittest.main()
