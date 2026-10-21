<?php

/**
 * CTest.php
 *
 * @since       2015-02-21
 * @category    Library
 * @package     Barcode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2015-2026 Nicola Asuni - Tecnick.com LTD
 * @license     https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-barcode
 *
 * This file is part of tc-lib-barcode software library.
 */

namespace Test\Linear\CodeOneTwoEight;

use Test\TestUtil;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Barcode class test
 *
 * @since       2015-02-21
 * @category    Library
 * @package     Barcode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2015-2026 Nicola Asuni - Tecnick.com LTD
 * @license     https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-barcode
 */
class CodeOneTwoEightCTest extends TestUtil
{
    public function testConsecutiveAndTrailingFnc1Markers(): void
    {
        $type = $this->getTestObject()->getBarcodeObj('C128C', "\xf1\xf1" . "00\xf1\xf1" . "12\xf1");
        $this->assertSame(
            [105, 102, 102, 0, 102, 102, 12, 102, 55, 106, 107],
            (new \ReflectionMethod($type, 'getCodeData'))->invoke($type),
        );
    }

    /** @return array<array{string}> */
    public static function invalidPayloads(): array
    {
        return \array_map(static fn (string $code): array => [$code], [
            '', "1\xf1" . '23', "12\xf1" . '3', "1\xf1" . '1', "\xf1" . '1',
            "12\xf2\xf2", "12\xf3\xf3", "12\xf4\xf4", "12\x0034", '12 34',
        ]);
    }

    #[DataProvider('invalidPayloads')]
    public function testInvalidRunsAndFunctionCodesAreRejected(string $payload): void
    {
        $this->bcExpectException(\Com\Tecnick\Barcode\Exception::class);
        $this->getTestObject()->getBarcodeObj('C128C', $payload);
    }
    protected function getTestObject(): \Com\Tecnick\Barcode\Barcode
    {
        return new \Com\Tecnick\Barcode\Barcode();
    }

    /**
     * @throws \Com\Tecnick\Barcode\Exception
     * @throws \Com\Tecnick\Color\Exception
     */
    public function testGetGrid(): void
    {
        $barcode = $this->getTestObject();
        $bobj = $barcode->getBarcodeObj('C128C', '0123456789');
        $grid = $bobj->getGrid();
        $expected = "110100111001100110110011101101110101110110001000010110011011011110100001101001100011101011\n";
        $this->assertEquals($expected, $grid);

        $bobj = $barcode->getBarcodeObj('C128C', \chr(241) . '0123456789');
        $grid = $bobj->getGrid();
        $expected =
            '11010011100111101011101100110110011101101110101110110001000010110011011011110111101101101100'
            . "011101011\n";
        $this->assertEquals($expected, $grid);

        $bobj = $barcode->getBarcodeObj('C128C', \chr(241) . '00123456780000000001');
        $grid = $bobj->getGrid();
        $expected =
            '11010011100111101011101101100110010110011100100010110001110001011011000010100110110011001101'
            . "1001100110110011001101100110011001101100100010011001100011101011\n";
        $this->assertEquals($expected, $grid);

        $bobj = $barcode->getBarcodeObj('C128C', \chr(241) . '42029651' . \chr(241) . '9405510200864168997758');
        $grid = $bobj->getGrid();
        $expected =
            '11010011100111101011101011011100011001100110101111000101101110100011110101110100010111101000'
            . '100110011011101000110011001101101100110011110100100110001000101000010011010111011110111101110101110'
            . "1100010111100101001100011101011\n";
        $this->assertEquals($expected, $grid);
    }

    /**
     * @throws \Com\Tecnick\Barcode\Exception
     * @throws \Com\Tecnick\Color\Exception
     */
    public function testInvalidLength(): void
    {
        $this->bcExpectException(\Com\Tecnick\Barcode\Exception::class);
        $barcode = $this->getTestObject();
        $barcode->getBarcodeObj('C128C', '12345678901');
    }

    /**
     * @throws \Com\Tecnick\Barcode\Exception
     * @throws \Com\Tecnick\Color\Exception
     */
    public function testInvalidChar(): void
    {
        $this->bcExpectException(\Com\Tecnick\Barcode\Exception::class);
        $barcode = $this->getTestObject();
        $barcode->getBarcodeObj('C128C', '1A2345678901');
    }
}
