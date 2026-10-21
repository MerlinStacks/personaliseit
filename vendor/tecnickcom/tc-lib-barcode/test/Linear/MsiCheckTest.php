<?php

/**
 * MsiCheckTest.php
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
class MsiCheckTest extends TestUtil
{
    /** @return array<array{string, string}> */
    public static function checksumVectors(): array
    {
        // Preserve legacy hexadecimal input and two-character representation of 10.
        return [['0', '00'], ['6', '610'], ['1234567', '12345674'], ['ABCDEF', 'ABCDEF10']];
    }

    #[DataProvider('checksumVectors')]
    public function testModuloElevenChecks(string $payload, string $expected): void
    {
        $this->assertSame($expected, $this->getTestObject()->getBarcodeObj('MSI+', $payload)->getExtendedCode());
    }

    /** @return array<array{string}> */
    public static function invalidPayloads(): array
    {
        return [[''], [' '], ['12 3'], ["123\n"]];
    }

    #[DataProvider('invalidPayloads')]
    public function testEmptyAndWhitespaceAreRejected(string $payload): void
    {
        $this->bcExpectException(\Com\Tecnick\Barcode\Exception::class);
        $this->getTestObject()->getBarcodeObj('MSI+', $payload);
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
        $type = $barcode->getBarcodeObj('MSI+', '0123456789ABCDEF');
        $grid = $type->getGrid();
        $expected =
            '110100100100100100100100110100100110100100100110110100110100100100110100110100110110100'
            . '1001101101101101001001001101001001101101001101001101001101101101101001001101101001101101101101'
            . "001101101101101001001001101001\n";
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
        $barcode->getBarcodeObj('MSI+', 'GHI');
    }
}
