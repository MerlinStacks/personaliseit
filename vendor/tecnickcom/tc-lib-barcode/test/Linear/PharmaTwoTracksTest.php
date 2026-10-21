<?php

/**
 * PharmaTwoTracksTest.php
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
class PharmaTwoTracksTest extends TestUtil
{
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
        $type = $barcode->getBarcodeObj('PHARMA2T', '12345');
        $grid = $type->getGrid();
        $expected = "00100010001010001\n10001000101010101\n";
        $this->assertEquals($expected, $grid);
    }

    public function testRangeBoundariesAndLeadingZeros(): void
    {
        $barcode = $this->getTestObject();
        $this->assertSame("000\n101\n", $barcode->getBarcodeObj('PHARMA2T', '4')->getGrid());
        $full = \str_repeat('10', 15) . "1\n";
        $this->assertSame($full . $full, $barcode->getBarcodeObj('PHARMA2T', '64570080')->getGrid());
        $this->assertSame($barcode->getBarcodeObj('PHARMA2T', '12345')->getGrid(), $barcode->getBarcodeObj('PHARMA2T', '00012345')->getGrid());
    }

    /** @return array<array{string}> */
    public static function invalidPayloads(): array
    {
        return [[''], ['3'], ['64570081'], ['0123456789'], ['-4'], ['4.0'], [' 4'], ["4\n"], [\str_repeat('9', 40)]];
    }

    #[DataProvider('invalidPayloads')]
    public function testOutOfRangeAndMalformedPayloads(string $payload): void
    {
        $this->bcExpectException(\Com\Tecnick\Barcode\Exception::class);
        $this->getTestObject()->getBarcodeObj('PHARMA2T', $payload);
    }

    /**
     * Regression: '0' (and other non-positive inputs) used to cause an infinite loop.
     *
     * @throws \Com\Tecnick\Barcode\Exception
     * @throws \Com\Tecnick\Color\Exception
     */
    public function testInvalidInput(): void
    {
        $this->bcExpectException(\Com\Tecnick\Barcode\Exception::class);
        $barcode = $this->getTestObject();
        $barcode->getBarcodeObj('PHARMA2T', '0');
    }
}
