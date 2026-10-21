<?php

/**
 * StandardTwoOfFiveTest.php
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
class StandardTwoOfFiveTest extends TestUtil
{
    public function testOddLengthPaddingAndLeadingZeros(): void
    {
        $barcode = $this->getTestObject();
        $type = $barcode->getBarcodeObj('S25', '123');
        $this->assertSame('0123', $type->getExtendedCode());
        $this->assertSame($barcode->getBarcodeObj('S25', '0123')->getGrid(), $type->getGrid());
        $this->assertSame('00', $barcode->getBarcodeObj('S25', '0')->getExtendedCode());
    }

    /** @return array<array{string}> */
    public static function invalidPayloads(): array
    {
        return [[''], ['12A3'], ['12 3'], ["123\n"], ['-1']];
    }

    #[DataProvider('invalidPayloads')]
    public function testInvalidPayloadsAreRejected(string $payload): void
    {
        $this->bcExpectException(\Com\Tecnick\Barcode\Exception::class);
        $this->getTestObject()->getBarcodeObj('S25', $payload);
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
        $type = $barcode->getBarcodeObj('S25', '0123456789');
        $grid = $type->getGrid();
        $expected =
            '111011101010101110111010111010101011101011101010111011101110101010101011101011101110101110101010'
            . '111011101010101010111011101110101011101010111010111010111010111'
            . "\n";
        $this->assertEquals($expected, $grid);
    }
}
