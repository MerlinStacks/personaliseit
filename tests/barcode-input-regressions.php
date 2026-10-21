<?php
/** Standalone barcode regressions: php tests/barcode-input-regressions.php. */
declare(strict_types=1);

use Com\Tecnick\Barcode\Barcode;
use Com\Tecnick\Barcode\Exception as BarcodeException;

spl_autoload_register(static function (string $class): void {
    foreach (['Barcode' => 'tc-lib-barcode', 'Color' => 'tc-lib-color'] as $namespace => $package) {
        $prefix = 'Com\\Tecnick\\' . $namespace . '\\';
        if (str_starts_with($class, $prefix)) {
            $file = dirname(__DIR__) . '/vendor/tecnickcom/' . $package . '/src/'
                . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) {
                require $file;
            }
            return;
        }
    }
});

// Treat unchecked offsets and other PHP warnings as failures, not test noise.
set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

function oc_barcode_equal(mixed $actual, mixed $expected, string $message): void {
    if ($actual !== $expected) {
        throw new RuntimeException($message);
    }
}

function oc_barcode_reject(Barcode $factory, string $type, string $code): void {
    try {
        $factory->getBarcodeObj($type, $code);
    } catch (BarcodeException $expected) {
        return;
    }
    throw new RuntimeException($type . ' accepted an invalid payload: ' . $code);
}

$factory = new Barcode();
foreach ([
    ['SRAW', '01,010'], ['SRAW', '010,01'], ['SRAW', '012'],
    ['LRAW', '1x0'], ['CODABAR', '12A34'], ['CODABAR', 'b123'],
    ['CODABAR', ''], ['CODE11', '12S34'], ['CODE11', '1.2'],
    ['CODE11', ''], ['C128', ''],
] as [$type, $code]) {
    try {
        $factory->getBarcodeObj($type, $code);
    } catch (BarcodeException $expected) {
        continue;
    }
    throw new RuntimeException($type . ' accepted an invalid payload: ' . $code);
}

$raw = $factory->getBarcodeObj('SRAW', '[110][011]')->getArray();
oc_barcode_equal($raw['ncols'], 3, 'Raw grid width changed.');
oc_barcode_equal($raw['nrows'], 2, 'Raw grid height changed.');
oc_barcode_equal($raw['bars'], [[0, 0, 2, 1], [1, 1, 2, 1]], 'Raw grid bars changed.');

class OC_Binary_Grid_Probe extends \Com\Tecnick\Barcode\Type\Convert {
    public function bars(array $rows): array {
        $this->processBinarySequence($rows);
        return $this->bars;
    }
}
$probe = new OC_Binary_Grid_Probe();
oc_barcode_equal($probe->bars([[1, 1, 0], [0, 1, 1]]), $raw['bars'], 'Integer and string grids must agree.');
foreach ([[null], [[0, 2]], [[true, false]], [[0, 1], [1]], [1 => '01']] as $rows) {
    try {
        $probe->bars($rows);
    } catch (BarcodeException $expected) {
        continue;
    }
    throw new RuntimeException('Invalid binary matrix accepted.');
}

oc_barcode_equal($factory->getBarcodeObj('CODABAR', '12-$:/.+')->getExtendedCode(), 'A12-$:/.+A', 'Codabar data changed.');
oc_barcode_equal($factory->getBarcodeObj('CODE11', '123')->getExtendedCode(), 'S123-S', 'Code 11 checksum changed.');
oc_barcode_equal($factory->getBarcodeObj('C93', 'a')->getExtendedCode(), "*\x82A8P*", 'Code 93 must preserve lowercase and checksums.');
oc_barcode_equal($factory->getBarcodeObj('C93', 'A')->getExtendedCode(), '*AAU*', 'Uppercase Code 93 output changed.');

foreach (['C39', 'C39+'] as $type) {
    foreach (['*', 'AB*CD', '*ABC*'] as $code) {
        oc_barcode_reject($factory, $type, $code);
    }
}
oc_barcode_equal($factory->getBarcodeObj('C39', 'abc')->getExtendedCode(), '*ABC*', 'Basic Code 39 normalization changed.');
oc_barcode_equal($factory->getBarcodeObj('C39E', 'a')->getExtendedCode(), '*+A*', 'Extended Code 39 must preserve lowercase.');
oc_barcode_equal($factory->getBarcodeObj('C39E+', 'a')->getExtendedCode(), '*+A8*', 'Extended Code 39 checksum must use the case-preserving expansion.');
oc_barcode_equal($factory->getBarcodeObj('C39E', '*')->getExtendedCode(), '*/J*', 'Extended Code 39 must still encode a literal asterisk.');

foreach (['EAN2' => 2, 'EAN5' => 5, 'EAN8' => 8, 'EAN13' => 13] as $type => $maximum) {
    foreach (['', '+1', '-1', '1.2', '1e2', ' 1', "1\n", 'A1', str_repeat('0', $maximum + 1)] as $code) {
        oc_barcode_reject($factory, $type, $code);
    }
    $padded = str_pad('1', $type === 'EAN2' || $type === 'EAN5' ? $maximum : $maximum - 1, '0', STR_PAD_LEFT);
    oc_barcode_equal(
        $factory->getBarcodeObj($type, '1')->getExtendedCode(),
        $factory->getBarcodeObj($type, $padded)->getExtendedCode(),
        $type . ' must retain short-input padding.',
    );
}
oc_barcode_equal($factory->getBarcodeObj('EAN13', '400638133393')->getExtendedCode(), '4006381333931', 'EAN-13 check digit changed.');
oc_barcode_equal($factory->getBarcodeObj('EAN13', '4006381333931')->getExtendedCode(), '4006381333931', 'EAN-13 supplied checksum changed.');
oc_barcode_equal($factory->getBarcodeObj('EAN8', '9638507')->getExtendedCode(), '96385074', 'EAN-8 check digit changed.');
oc_barcode_equal($factory->getBarcodeObj('EAN8', '96385074')->getExtendedCode(), '96385074', 'EAN-8 supplied checksum changed.');
oc_barcode_reject($factory, 'EAN13', '4006381333932');
oc_barcode_reject($factory, 'EAN8', '96385075');

$tracking = '01234567094987654321';
foreach (['12', substr($tracking, 1), $tracking . '0', '05234567094987654321',
          $tracking . "\n", $tracking . '-12345-extra', $tracking . '-1'] as $code) {
    oc_barcode_reject($factory, 'IMB', $code);
}
$pre = str_repeat('FADT', 16) . 'F';
foreach ([$pre . "\n", $pre . 'F', substr($pre, 1), str_repeat('X', 65)] as $code) {
    oc_barcode_reject($factory, 'IMBPRE', $code);
}
oc_barcode_equal(count($factory->getBarcodeObj('IMBPRE', $pre)->getArray()['bars']), 65, 'IMBPRE valid sequence changed.');
if (extension_loaded('bcmath')) {
    foreach (['', '-12345', '-123456789', '-12345678901'] as $routing) {
        oc_barcode_equal(count($factory->getBarcodeObj('IMB', $tracking . $routing)->getArray()['bars']), 65, 'Valid IMB input changed.');
    }
}

class OC_Code128_Sequence_Probe extends \Com\Tecnick\Barcode\Type\Linear\CodeOneTwoEight {
    public function sequence(string $code): array {
        return $this->getNumericSequence($code);
    }
}

foreach (['I25', 'I25+', 'MSI', 'MSI+'] as $type) {
    oc_barcode_reject($factory, $type, '');
}
foreach (['I25', 'I25+'] as $type) {
    foreach (['12x', '+12', "12\n"] as $code) {
        oc_barcode_reject($factory, $type, $code);
    }
}
foreach (['1' => 'AA17ZA', '12' => 'AA0123ZA', '123' => 'AA1236ZA', '1234' => 'AA012348ZA'] as $code => $expected) {
    oc_barcode_equal($factory->getBarcodeObj('I25+', (string) $code)->getExtendedCode(), $expected, 'I25 checksum or padding changed.');
}
oc_barcode_equal($factory->getBarcodeObj('I25', '123')->getExtendedCode(), 'AA0123ZA', 'I25 odd-length padding changed.');
oc_barcode_equal($factory->getBarcodeObj('MSI', '123')->getExtendedCode(), '123', 'MSI payload changed.');
oc_barcode_equal($factory->getBarcodeObj('MSI+', '123')->getExtendedCode(), '1236', 'MSI modulo-11 checksum changed.');
// Retain the existing hexadecimal-capable alphabet; this change only rejects empty MSI input.
oc_barcode_equal($factory->getBarcodeObj('MSI', 'ABC')->getExtendedCode(), 'ABC', 'Legacy MSI alphabet changed.');

foreach (['PHARMA' => [3, 131070], 'PHARMA2T' => [4, 64570080]] as $type => [$minimum, $maximum]) {
    foreach (['', '0', (string) ($minimum - 1), (string) ($maximum + 1),
              '12x', '1.2', '+12', "12\n", str_repeat('9', 100)] as $code) {
        oc_barcode_reject($factory, $type, $code);
    }
    foreach ([$minimum, $maximum] as $value) {
        $model = $factory->getBarcodeObj($type, (string) $value);
        if ($model->getArray()['bars'] === []) {
            throw new RuntimeException($type . ' boundary failed to render.');
        }
        oc_barcode_equal(
            $factory->getBarcodeObj($type, '000' . $value)->getArray()['bars'],
            $model->getArray()['bars'],
            $type . ' leading-zero normalization changed.',
        );
    }
}
oc_barcode_equal(count($factory->getBarcodeObj('PHARMA', '3')->getArray()['bars']), 2, 'Minimum one-track Pharmacode must have two bars.');
oc_barcode_equal(count($factory->getBarcodeObj('PHARMA', '131070')->getArray()['bars']), 16, 'Maximum one-track Pharmacode must have sixteen bars.');
oc_barcode_equal(count($factory->getBarcodeObj('PHARMA2T', '4')->getArray()['bars']), 2, 'Minimum two-track Pharmacode must have two bars.');
oc_barcode_equal(count($factory->getBarcodeObj('PHARMA2T', '64570080')->getArray()['bars']), 16, 'Maximum two-track Pharmacode must have sixteen bars.');

foreach (['POSTNET' => [5, 9, 11], 'PLANET' => [11, 13]] as $type => $valid_lengths) {
    for ($length = 0; $length <= 14; ++$length) {
        $code = str_repeat('1', $length);
        if (!in_array($length, $valid_lengths, true)) {
            oc_barcode_reject($factory, $type, $code);
            continue;
        }
        $model = $factory->getBarcodeObj($type, $code);
        oc_barcode_equal(count($model->getArray()['bars']), (($length + 1) * 5) + 2, $type . ' bar count changed.');
        oc_barcode_equal(
            $factory->getBarcodeObj($type, substr($code, 0, 3) . '- ' . substr($code, 3))->getExtendedCode(),
            $model->getExtendedCode(),
            $type . ' separator normalization changed.',
        );
    }
    oc_barcode_reject($factory, $type, '1234A567890');
}
$sequence = new OC_Code128_Sequence_Probe('ABC');
foreach (['RMS4CC', 'S25', 'S25+'] as $type) {
    oc_barcode_reject($factory, $type, '');
}
foreach (['S25', 'S25+'] as $type) {
    foreach (['12x', '+12', "12\n"] as $code) {
        oc_barcode_reject($factory, $type, $code);
    }
}
oc_barcode_equal($factory->getBarcodeObj('S25+', '12')->getExtendedCode(), '0123', 'S25 even-length checksum changed.');
oc_barcode_equal($factory->getBarcodeObj('S25+', '123')->getExtendedCode(), '1236', 'S25 odd-length checksum changed.');
oc_barcode_equal($factory->getBarcodeObj('S25', '123')->getExtendedCode(), '0123', 'S25 padding changed.');
$upca = $factory->getBarcodeObj('UPCA', '04210000526');
$upca_checked = $factory->getBarcodeObj('UPCA', '042100005264');
oc_barcode_equal($upca_checked->getExtendedCode(), '0042100005264', 'UPC-A supplied checksum must not append a zero.');
oc_barcode_equal($upca_checked->getArray()['bars'], $upca->getArray()['bars'], 'UPC-A supplied and generated checksums must agree.');
foreach (['UPCA', 'UPCE'] as $type) {
    oc_barcode_reject($factory, $type, '042100005265');
}
foreach (['', '+1', '1.2', '1e3', "425261\n", str_repeat('0', 13), '24210000526', '12345678901'] as $code) {
    oc_barcode_reject($factory, 'UPCE', $code);
}
foreach ([['425261', '04210000526'], ['123453', '01230000045'],
          ['123454', '01234000005'], ['123456', '01234500006']] as [$compact, $expanded]) {
    oc_barcode_equal(
        $factory->getBarcodeObj('UPCE', $compact)->getArray()['bars'],
        $factory->getBarcodeObj('UPCE', $expanded)->getArray()['bars'],
        'UPC-E compression/expansion branch changed.',
    );
}
oc_barcode_equal(
    $factory->getBarcodeObj('UPCE', '042100005264')->getArray()['bars'],
    $factory->getBarcodeObj('UPCE', '425261')->getArray()['bars'],
    'UPC-E supplied-checksum input changed.',
);
if ($factory->getBarcodeObj('UPCE', '11234500006')->getArray()['bars'] === []) {
    throw new RuntimeException('UPC-E number system 1 must remain supported.');
}
oc_barcode_equal($sequence->sequence('ab12345cd6789'), [
    ['B', 'ab1', 3], ['C', '2345', 4], ['B', 'cd', 2], ['C', '6789', 4],
], 'Code 128 numeric sequence order changed.');
oc_barcode_equal($sequence->sequence("a\x01b"), [
    ['B', 'a', 1], ['A', "\x01", 1], ['B', 'b', 1],
], 'Code 128 control-character sequence changed.');
$many_parts = $sequence->sequence(str_repeat('ab1234', 1000));
oc_barcode_equal(count($many_parts), 2000, 'Long Code 128 sequence lost segments.');
oc_barcode_equal($many_parts[1999], ['C', '1234', 4], 'Long Code 128 sequence order changed.');
// Mixed alphanumeric input must encode identically to the equivalent explicit set B.
oc_barcode_equal(
    $factory->getBarcodeObj('C128', 'ABC')->getArray()['bars'],
    $factory->getBarcodeObj('C128B', 'ABC')->getArray()['bars'],
    'Automatic Code 128 output changed for a basic set-B payload.',
);
oc_barcode_equal(
    $factory->getBarcodeObj('C128', '123456')->getArray()['bars'],
    $factory->getBarcodeObj('C128C', '123456')->getArray()['bars'],
    'Automatic Code 128 output changed for a numeric payload.',
);
// Internal two-dimensional encoders also use the shared binary-grid conversion.
foreach (['QRCODE', 'DATAMATRIX', 'AZTEC', 'PDF417'] as $type) {
    $model = $factory->getBarcodeObj($type, 'Barcode regression 123');
    $array = $model->getArray();
    if ($array['nrows'] < 1 || $array['ncols'] < 1 || $array['bars'] === []) {
        throw new RuntimeException($type . ' produced an empty barcode.');
    }
    if (!str_contains($model->getInlineSvgCode(), '<svg')) {
        throw new RuntimeException($type . ' SVG generation failed.');
    }
}
restore_error_handler();
echo "Barcode input regressions passed.\n";
