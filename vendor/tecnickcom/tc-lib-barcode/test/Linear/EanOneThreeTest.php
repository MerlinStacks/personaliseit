<?php

/**
 * EanOneThreeTest.php
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
class EanOneThreeTest extends TestUtil
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
        $type = $barcode->getBarcodeObj('EAN13', '0123456789');
        $grid = $type->getGrid();
        $expected = "10100011010001101001100100100110111101010001101010100111010100001000100100100011101001001110101\n";
        $this->assertEquals($expected, $grid);

        $this->assertEquals('0001234567895', $type->getExtendedCode());
    }

    /**
     * @throws \Com\Tecnick\Barcode\Exception
     * @throws \Com\Tecnick\Color\Exception
     */
    public function testInvalidInput(): void
    {
        $this->bcExpectException(\Com\Tecnick\Barcode\Exception::class);
        $barcode = $this->getTestObject();
        $barcode->getBarcodeObj('EAN13', '}{');
    }

    /**
     * @throws \Com\Tecnick\Barcode\Exception
     * @throws \Com\Tecnick\Color\Exception
     */
    public function testInvalidChecksum(): void
    {
        $this->bcExpectException(\Com\Tecnick\Barcode\Exception::class);
        $barcode = $this->getTestObject();
        $barcode->getBarcodeObj('EAN13', '1111111111111');
    }

    public function testActualOverlengthIsRejected(): void
    {
        $this->bcExpectException(\Com\Tecnick\Barcode\Exception::class);
        $this->getTestObject()->getBarcodeObj('EAN13', '12345678901234');
    }

    /** @return array<array{string, string, string}> */
    public static function leadingDigitParities(): array
    {
        return [
            ['0', 'AAAAAA', '1110010'], ['1', 'AABABB', '1110100'],
            ['2', 'AABBAB', '1001000'], ['3', 'AABBBA', '1000100'],
            ['4', 'ABAABB', '1010000'], ['5', 'ABBAAB', '1001110'],
            ['6', 'ABBBAA', '1011100'], ['7', 'ABABAB', '1000010'],
            ['8', 'ABABBA', '1101100'], ['9', 'ABBABA', '1100110'],
        ];
    }

    #[DataProvider('leadingDigitParities')]
    public function testAllLeadingDigitParities(string $digit, string $parity, string $checkPattern): void
    {
        // Eleven remaining data digits are zero, so check digit is (10-first)%10.
        $payload = $digit . \str_repeat('0', 11);
        $expected = '101' . \strtr($parity, ['A' => '0001101', 'B' => '0100111'])
            . '01010' . \str_repeat('1110010', 5) . $checkPattern . "101\n";
        $type = $this->getTestObject()->getBarcodeObj('EAN13', $payload);
        $this->assertSame($payload . ((10 - (int) $digit) % 10), $type->getExtendedCode());
        $this->assertSame($expected, $type->getGrid());
    }

    /**
     * Regression: a full code that already carries its (valid) check digit must not
     * gain a spurious extra digit in the extended code.
     *
     * @throws \Com\Tecnick\Barcode\Exception
     * @throws \Com\Tecnick\Color\Exception
     */
    public function testFullCodeExtendedCode(): void
    {
        $barcode = $this->getTestObject();
        $type = $barcode->getBarcodeObj('EAN13', '8012345678907');
        $this->assertSame('8012345678907', $type->getExtendedCode());
    }
}
