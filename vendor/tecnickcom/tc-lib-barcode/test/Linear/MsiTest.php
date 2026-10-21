<?php

/**
 * MsiTest.php
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
class MsiTest extends TestUtil
{
    public function testLegacyHexadecimalAlphabetBoundaries(): void
    {
        $type = $this->getTestObject()->getBarcodeObj('MSI', '09AF');
        $this->assertSame('09AF', $type->getExtendedCode());
        $this->assertSame('110' . '100100100100' . '110100100110' . '110100110100' . '110110110110' . "1001\n", $type->getGrid());
    }

    /** @return array<array{string}> */
    public static function invalidPayloads(): array
    {
        return [[''], ['G'], ['a'], ['12 3'], ["123\n"], ["\xff"]];
    }

    #[DataProvider('invalidPayloads')]
    public function testInvalidPayloadsAreRejected(string $payload): void
    {
        $this->bcExpectException(\Com\Tecnick\Barcode\Exception::class);
        $this->getTestObject()->getBarcodeObj('MSI', $payload);
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
        $type = $barcode->getBarcodeObj('MSI', '0123456789');
        $grid = $type->getGrid();
        $expected =
            '110100100100100100100100110100100110100100100110110100110100'
            . "1001001101001101001101101001001101101101101001001001101001001101001\n";
        $this->assertEquals($expected, $grid);
    }
}
