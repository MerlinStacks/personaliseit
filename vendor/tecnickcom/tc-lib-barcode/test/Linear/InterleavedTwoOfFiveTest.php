<?php

/**
 * InterleavedTwoOfFiveTest.php
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
class InterleavedTwoOfFiveTest extends TestUtil
{
    public function testOddLengthPaddingAndZeroPreservation(): void
    {
        $barcode = $this->getTestObject();
        $short = $barcode->getBarcodeObj('I25', '123');
        $this->assertSame('AA0123ZA', $short->getExtendedCode());
        $this->assertSame($barcode->getBarcodeObj('I25', '0123')->getGrid(), $short->getGrid());
        $this->assertSame('AA00ZA', $barcode->getBarcodeObj('I25', '0')->getExtendedCode());
    }

    /** @return array<array{string}> */
    public static function invalidPayloads(): array
    {
        return [[''], ['12A3'], ['12 3'], ["123\n"], ['-1'], ["\xff"]];
    }

    #[DataProvider('invalidPayloads')]
    public function testInvalidPayloadsAreRejected(string $payload): void
    {
        $this->bcExpectException(\Com\Tecnick\Barcode\Exception::class);
        $this->getTestObject()->getBarcodeObj('I25', $payload);
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
        $type = $barcode->getBarcodeObj('I25', '0123456789');
        $grid = $type->getGrid();
        $expected = "101010010110110100100110010101101001011001011010110110100100110100101100101101\n";
        $this->assertEquals($expected, $grid);
    }
}
