"""Optional independent decoder checks: requires zxing-cpp, Pillow and PHP.

Set PHP_COMMAND to a PHP CLI command (including a php-wasm-cli wrapper if needed).
Run with: python3 -B tests/test_barcode_decode.py
"""
import base64
import json
import os
from pathlib import Path
import shlex
import subprocess
import unittest

try:
    import zxingcpp
    from PIL import Image
except ImportError:
    zxingcpp = Image = None


@unittest.skipUnless(zxingcpp is not None, 'Install zxing-cpp and Pillow for independent decoding')
class BarcodeDecodeTests(unittest.TestCase):
    def test_generated_symbols_decode_to_original_bytes(self):
        root = Path(__file__).resolve().parents[1]
        command = shlex.split(os.environ.get('PHP_COMMAND', 'php'))
        result = subprocess.run(command + ['tests/barcode-encoding-regressions.php', '--fixtures'],
                                cwd=root, capture_output=True, text=True, check=True, timeout=120)
        fixtures = json.loads(result.stdout)
        self.assertEqual(len(fixtures), 30)
        for fixture in fixtures:
            with self.subTest(type=fixture['type']):
                grid = fixture['grid']
                height, width = len(grid), len(grid[0])
                image = Image.new('L', (width + 20, height + 20), 255)
                pixels = image.load()
                for y, row in enumerate(grid):
                    self.assertEqual(len(row), width)
                    for x, cell in enumerate(row):
                        pixels[x + 10, y + 10] = 0 if str(cell) == '1' else 255
                image = image.resize((image.width * 4, image.height * 4), Image.Resampling.NEAREST)
                decoded = zxingcpp.read_barcode(image)
                self.assertIsNotNone(decoded, fixture['type'] + ' did not decode')
                self.assertEqual(decoded.bytes, base64.b64decode(fixture['expected']))
                if fixture['type'] == 'AZTEC,33,A,A,0':
                    self.assertEqual(decoded.content_type, zxingcpp.ContentType.GS1)
                    self.assertEqual(decoded.symbology_identifier, ']z1')
                if fixture['type'] == 'UPCE':
                    self.assertEqual(decoded.format, zxingcpp.BarcodeFormat.UPCE)
                if fixture['type'].startswith('DATAMATRIX,S,GS1'):
                    self.assertEqual(decoded.content_type, zxingcpp.ContentType.GS1)
                    self.assertEqual(decoded.symbology_identifier, ']d2')
                if fixture['type'] == 'AZTEC,100,A,A,0':
                    self.assertEqual(decoded.content_type, zxingcpp.ContentType.GS1)
                    self.assertEqual(decoded.symbology_identifier, ']z1')
                if fixture['type'] == 'AZTEC,1,A,A':
                    self.assertEqual((width, height), (31, 31))
                if fixture['type'] == 'DATAMATRIX,R':
                    self.assertEqual((width, height), (36, 12))
                if fixture['type'] == 'DATAMATRIX,S,N,BASE256':
                    self.assertEqual((width, height), (144, 144))


if __name__ == '__main__':
    unittest.main()
