<?php

/**
 * CodeThreeNineExtCheckTest.php
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
class CodeThreeNineExtCheckTest extends TestUtil
{
    public function testChecksumUsesExpandedAsciiSymbols(): void
    {
        $barcode = $this->getTestObject();
        // '+' has value 41 and 'A' value 10, so expanded 'a' has checksum 8.
        $this->assertSame('*+A8*', $barcode->getBarcodeObj('C39E+', 'a')->getExtendedCode());
        $this->assertSame('*%U$A+Z/J+*', $barcode->getBarcodeObj('C39E+', "\x00\x01z*")->getExtendedCode());
        $this->assertNotSame($barcode->getBarcodeObj('C39E+', 'A')->getGrid(), $barcode->getBarcodeObj('C39E+', 'a')->getGrid());
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
        $type = $barcode->getBarcodeObj('C39E+', '0123456789');
        $grid = $type->getGrid();
        $expected =
            '10001011101110101010001110111010111010001010111010111000101011101110111000101010101000'
            . '111010111011101000111010101011100011101010101000101110111011101000101110101011100010111010101'
            . "1100010101110100010111011101\n";
        $this->assertEquals($expected, $grid);
    }

    /**
     * @throws \Com\Tecnick\Barcode\Exception
     * @throws \Com\Tecnick\Color\Exception
     */
    public function testInvalidInput(): void
    {
        $this->bcExpectException(\Com\Tecnick\Barcode\Exception::class);
        $barcode = $this->getTestObject();
        $barcode->getBarcodeObj('C39E+', \chr(218));
    }
}
