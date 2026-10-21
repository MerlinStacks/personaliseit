<?php

/**
 * EanEightTest.php
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
class EanEightTest extends TestUtil
{
    public function testExplicitCheckDigitAndShortInputPadding(): void
    {
        $barcode = $this->getTestObject();
        $auto = $barcode->getBarcodeObj('EAN8', '1234567');
        $full = $barcode->getBarcodeObj('EAN8', '12345670');
        $this->assertSame('12345670', $full->getExtendedCode());
        $this->assertSame($auto->getGrid(), $full->getGrid());
        $short = $barcode->getBarcodeObj('EAN8', '1');
        $this->assertSame('00000017', $short->getExtendedCode());
        $this->assertSame($barcode->getBarcodeObj('EAN8', '0000001')->getGrid(), $short->getGrid());
    }

    /** @return array<array{string}> */
    public static function invalidPayloads(): array
    {
        return [[''], ['12345671'], ['123456789'], [' 1234567'], ["1234567\n"], ['12 3456'], ["\xff"]];
    }

    #[DataProvider('invalidPayloads')]
    public function testInvalidChecksumsLengthsAndWhitespace(string $payload): void
    {
        $this->bcExpectException(\Com\Tecnick\Barcode\Exception::class);
        $this->getTestObject()->getBarcodeObj('EAN8', $payload);
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
        $type = $barcode->getBarcodeObj('EAN8', '1234567');
        $grid = $type->getGrid();
        $expected = "1010011001001001101111010100011010101001110101000010001001110010101\n";
        $this->assertEquals($expected, $grid);
    }

    /**
     * Regression: non-numeric input used to produce a malformed barcode instead of throwing.
     *
     * @throws \Com\Tecnick\Barcode\Exception
     * @throws \Com\Tecnick\Color\Exception
     */
    public function testNonNumericInput(): void
    {
        $this->bcExpectException(\Com\Tecnick\Barcode\Exception::class);
        $barcode = $this->getTestObject();
        $barcode->getBarcodeObj('EAN8', 'abcdefg');
    }
}
