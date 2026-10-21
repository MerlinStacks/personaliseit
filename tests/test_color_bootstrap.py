"""Example bootstrap regressions; set PHP_COMMAND to a CLI command when needed."""
import json
import os
from pathlib import Path
import shlex
import shutil
import subprocess
import tempfile
import unittest


ROOT = Path(__file__).resolve().parents[1]
PACKAGE = ROOT / 'vendor/tecnickcom/tc-lib-color'
PHP_COMMAND = shlex.split(os.environ.get('PHP_COMMAND', 'php'))
HAS_PHP = bool(PHP_COMMAND and shutil.which(PHP_COMMAND[0]))


@unittest.skipUnless(HAS_PHP, 'PHP CLI or PHP_COMMAND is required')
class ColorBootstrapTests(unittest.TestCase):
    def render(self, example, prefix=''):
        code = prefix + 'ob_start(); require ' + json.dumps(str(example)) + '; '
        code += '$html=ob_get_clean(); echo json_encode(["html"=>$html], JSON_THROW_ON_ERROR);'
        result = subprocess.run(PHP_COMMAND + ['-r', code], cwd=ROOT,
                                capture_output=True, text=True, check=True, timeout=120)
        try:
            return json.loads(result.stdout)['html']
        except (ValueError, KeyError) as error:
            self.fail(f'Example did not return HTML: {error}\n{result.stdout[:1500]}\n{result.stderr[:1500]}')

    def fixture(self, directory, loaders):
        root = Path(directory)
        package = root / 'project/vendor/tecnickcom/tc-lib-color'
        example = package / 'example/index.php'
        example.parent.mkdir(parents=True)
        system = root / 'system/Color/autoload.php'
        # Isolate the system-loader candidate from the host's installed packages.
        text = (PACKAGE / 'example/index.php').read_text()
        text = text.replace('/usr/share/php/Com/Tecnick/Color/autoload.php', str(system))
        example.write_text(text)
        candidates = {'standalone': package / 'vendor/autoload.php',
                      'consumer': root / 'project/vendor/autoload.php', 'system': system}
        for loader in loaders:
            path = candidates[loader]
            path.parent.mkdir(parents=True, exist_ok=True)
            path.write_text('<?php namespace Com\\Tecnick\\Color; '
                            f'echo "loader:{loader}"; class Web {{public const WEBHEX=[];}}')
        return example

    def test_actual_bundled_example_renders_palette(self):
        html = self.render(PACKAGE / 'example/index.php')
        self.assertIn('Web Colors Table', html)
        self.assertIn('Normalized Inverted Web Colors Table', html)
        self.assertIn('aliceblue', html)
        self.assertGreater(html.count('<tr>'), 100)

    def test_standalone_loader_has_priority(self):
        with tempfile.TemporaryDirectory(prefix='color-bootstrap-') as directory:
            html = self.render(self.fixture(directory, ['standalone', 'consumer', 'system']))
            self.assertIn('loader:standalone', html)
            self.assertNotIn('loader:consumer', html)

    def test_consumer_loader_fallback(self):
        with tempfile.TemporaryDirectory(prefix='color-bootstrap-') as directory:
            self.assertIn('loader:consumer', self.render(self.fixture(directory, ['consumer'])))

    def test_system_loader_fallback(self):
        with tempfile.TemporaryDirectory(prefix='color-bootstrap-') as directory:
            self.assertIn('loader:system', self.render(self.fixture(directory, ['system'])))

    def test_existing_application_loader_is_respected(self):
        with tempfile.TemporaryDirectory(prefix='color-bootstrap-') as directory:
            example = self.fixture(directory, ['standalone'])
            prefix = 'namespace Com\\Tecnick\\Color; class Web {public const WEBHEX=[];} '
            self.assertNotIn('loader:standalone', self.render(example, prefix))

    def test_missing_dependencies_have_clear_error(self):
        with tempfile.TemporaryDirectory(prefix='color-bootstrap-') as directory:
            example = self.fixture(directory, [])
            code = 'try {require ' + json.dumps(str(example)) + '; echo "unexpected-success";} '
            code += 'catch (\\RuntimeException $e) {echo $e->getMessage();}'
            result = subprocess.run(PHP_COMMAND + ['-r', code], cwd=ROOT,
                                    capture_output=True, text=True, check=True, timeout=120)
            self.assertIn('Unable to load tc-lib-color', result.stdout)
            self.assertNotIn('unexpected-success', result.stdout)
            self.assertNotIn('Failed opening required', result.stdout)


if __name__ == '__main__':
    unittest.main()
