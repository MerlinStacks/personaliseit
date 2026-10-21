<?php

/**
 * PharmaTest.php
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
class PharmaTest extends TestUtil
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
        $type = $barcode->getBarcodeObj('PHARMA', '12345');
        $grid = $type->getGrid();
        $expected = "11100100100100100100100111001110011100100111001\n";
        $this->assertEquals($expected, $grid);
    }

    public function testRangeBoundariesAndLeadingZeros(): void
    {
        $barcode = $this->getTestObject();
        $this->assertSame("1001\n", $barcode->getBarcodeObj('PHARMA', '3')->getGrid());
        $this->assertSame(\str_repeat('11100', 15) . "111\n", $barcode->getBarcodeObj('PHARMA', '131070')->getGrid());
        $this->assertSame($barcode->getBarcodeObj('PHARMA', '12345')->getGrid(), $barcode->getBarcodeObj('PHARMA', '00012345')->getGrid());
    }

    /** @return array<array{string}> */
    public static function invalidPayloads(): array
    {
        return [[''], ['0'], ['2'], ['131071'], ['0123456789'], ['-3'], ['3.0'], [' 3'], ["3\n"], [\str_repeat('9', 40)]];
    }

    #[DataProvider('invalidPayloads')]
    public function testOutOfRangeAndMalformedInputIsRejected(string $payload): void
    {
        $this->bcExpectException(\Com\Tecnick\Barcode\Exception::class);
        $this->getTestObject()->getBarcodeObj('PHARMA', $payload);
    }
}
