"""Safe build-orchestration regressions; all development/package steps are mocked."""
import os
from pathlib import Path
import shlex
import shutil
import subprocess
import sys
import tempfile
import unittest
import xml.etree.ElementTree as ET


ROOT = Path(__file__).resolve().parents[1]
PACKAGE = ROOT / 'vendor/tecnickcom/tc-lib-color'
PHASES = ['deps', 'format', 'qa', 'bz2', 'rpm', 'deb']


@unittest.skipUnless(shutil.which('make'), 'GNU make is required')
class ColorBuildWorkflowTests(unittest.TestCase):
    def run_mock_build(self, fail_phase=''):
        with tempfile.TemporaryDirectory(prefix='color-make-') as directory:
            folder = Path(directory)
            stub = folder / 'phase.py'
            stub.write_text('''import os, pathlib, sys, time
phase = sys.argv[1]
log = pathlib.Path(os.environ['PHASE_LOG'])
with log.open('a') as output:
    output.write(phase + ':start\\n')
time.sleep(0.03)
with log.open('a') as output:
    output.write(phase + ':end\\n')
sys.exit(5 if phase == os.environ.get('FAIL_PHASE') else 0)
''')
            # Override every potentially destructive leaf recipe. Including the
            # real Makefile still exercises its actual buildall orchestration.
            source = str(PACKAGE / 'Makefile').replace(' ', '\\ ')
            command = shlex.quote(sys.executable) + ' ' + shlex.quote(str(stub))
            (folder / 'Makefile').write_text(
                f'include {source}\n'
                'ensuretarget lint test report:\n\t@:\n'
                + ' '.join(PHASES) + ':\n\t@' + command + ' $@\n')
            log = folder / 'phases.log'
            environment = dict(os.environ, PHASE_LOG=str(log), FAIL_PHASE=fail_phase)
            # Do not inherit user-supplied make targets/options into the sandbox.
            for name in ['MAKEFLAGS', 'MFLAGS', 'MAKELEVEL', 'GNUMAKEFLAGS', 'MAKEFILES']:
                environment.pop(name, None)
            result = subprocess.run(['make', '-j6', 'buildall'], cwd=folder,
                                    env=environment, capture_output=True, text=True, timeout=30)
            return result, log.read_text().splitlines() if log.exists() else []

    def test_parallel_build_preserves_phase_order(self):
        result, events = self.run_mock_build()
        self.assertEqual(result.returncode, 0, result.stderr)
        self.assertEqual(events, [f'{phase}:{event}' for phase in PHASES for event in ['start', 'end']])

    def test_failure_stops_later_phases(self):
        result, events = self.run_mock_build('qa')
        self.assertNotEqual(result.returncode, 0)
        self.assertEqual(events, [f'{phase}:{event}' for phase in PHASES[:3] for event in ['start', 'end']])

    def test_installer_has_one_version_argument(self):
        installer = next(line for line in (PACKAGE / 'Makefile').read_text().splitlines()
                         if 'carthage.software/mago.sh' in line)
        self.assertEqual(installer.count('--version='), 1)

    def test_contributor_test_example_exists(self):
        self.assertTrue((PACKAGE / 'test/WebTest.php').is_file())
        self.assertNotIn('test/HTMLTest.php', (PACKAGE / 'CONTRIBUTING.md').read_text())

    def test_pr_checklist_uses_qa_and_maintainer_versioning(self):
        template = (PACKAGE / '.github/pull_request_template.md').read_text()
        self.assertIn('make qa', template)
        self.assertNotIn('make buildall', template)
        self.assertIn('VERSION is unchanged', template)
        self.assertNotIn('has been increased', template)

    def test_example_url_and_conduct_contact(self):
        readme = (PACKAGE / 'README.md').read_text()
        self.assertIn('http://127.0.0.1:8000/', readme)
        self.assertNotIn('http://127.0.0.1:8000/example/index.php', readme)
        self.assertIn('mailto:info@tecnick.com', (PACKAGE / 'CODE_OF_CONDUCT.md').read_text())

    def test_ci_cancels_superseded_runs_for_the_same_ref(self):
        workflow = (PACKAGE / '.github/workflows/check.yml').read_text()
        self.assertIn('concurrency:\n  group: ${{ github.workflow }}-${{ github.ref }}\n'
                      '  cancel-in-progress: true', workflow)

    def test_mago_schema_versions_match_the_installer(self):
        version = next(line.split('=', 1)[1] for line in (PACKAGE / 'Makefile').read_text().splitlines()
                       if line.startswith('MAGOVERSION='))
        for config in ['mago.src.toml', 'mago.test.toml']:
            self.assertEqual((PACKAGE / config).read_text().splitlines()[0],
                             f'#:schema https://mago.carthage.software/{version}/schema.json')

    def test_phpunit_fails_on_reported_issues(self):
        attributes = ET.parse(PACKAGE / 'phpunit.xml.dist').getroot().attrib
        for option in ['failOnDeprecation', 'failOnNotice', 'failOnWarning', 'failOnPhpunitDeprecation']:
            self.assertEqual(attributes.get(option), 'true', option)


if __name__ == '__main__':
    unittest.main()
