<?php

/**
 * EanFiveTest.php
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

namespace Test\Linear;

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
class EanFiveTest extends TestUtil
{
    /** @return array<array{string, string}> */
    public static function parityVectors(): array
    {
        // Last-digit weights of 3 exercise every checksum and parity pattern.
        return [
            ['00000', '10110100111010100111010001101010001101010001101'],
            ['00001', '10110100111010001101010001101010001101010110011'],
            ['00002', '10110001101010001101010001101010100111010011011'],
            ['00003', '10110001101010001101010100111010001101010100001'],
            ['00004', '10110100111010001101010001101010100111010100011'],
            ['00005', '10110001101010001101010100111010100111010110001'],
            ['00006', '10110001101010100111010001101010001101010000101'],
            ['00007', '10110100111010001101010100111010001101010111011'],
            ['00008', '10110001101010100111010100111010001101010110111'],
            ['00009', '10110001101010100111010001101010100111010001011'],
        ];
    }

    #[DataProvider('parityVectors')]
    public function testAllTenParityPatterns(string $payload, string $expected): void
    {
        $this->assertSame($expected . "\n", $this->getTestObject()->getBarcodeObj('EAN5', $payload)->getGrid());
    }

    public function testLeadingZeroPadding(): void
    {
        $barcode = $this->getTestObject();
        $short = $barcode->getBarcodeObj('EAN5', '5');
        $this->assertSame('00005', $short->getExtendedCode());
        $this->assertSame($barcode->getBarcodeObj('EAN5', '00005')->getGrid(), $short->getGrid());
    }

    /** @return array<array{string}> */
    public static function invalidPayloads(): array
    {
        return [[''], ['123456'], ['12A45'], [' 1234'], ["1234\n"], ['12.34'], ["\xff"]];
    }

    #[DataProvider('invalidPayloads')]
    public function testInvalidPayloadsAreRejected(string $payload): void
    {
        $this->bcExpectException(\Com\Tecnick\Barcode\Exception::class);
        $this->getTestObject()->getBarcodeObj('EAN5', $payload);
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
        $type = $barcode->getBarcodeObj('EAN5', '12345');
        $grid = $type->getGrid();
        $expected = "10110110011010010011010100001010100011010110001\n";
        $this->assertEquals($expected, $grid);
    }
}
