<?php

/**
 * UpcATest.php
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
class UpcATest extends TestUtil
{
    public function testSuppliedChecksumAndExtendedCode(): void
    {
        $barcode = $this->getTestObject();
        $auto = $barcode->getBarcodeObj('UPCA', '03600029145');
        $full = $barcode->getBarcodeObj('UPCA', '036000291452');
        $this->assertSame('0036000291452', $auto->getExtendedCode());
        $this->assertSame($auto->getExtendedCode(), $full->getExtendedCode());
        $this->assertSame($auto->getGrid(), $full->getGrid());
        $this->assertSame('0000000000017', $barcode->getBarcodeObj('UPCA', '1')->getExtendedCode());
    }

    /** @return array<array{string}> */
    public static function invalidPayloads(): array
    {
        return [[''], ['036000291453'], ['1234567890123'], ['03600029145A'], [' 03600029145'], ["03600029145\n"]];
    }

    #[DataProvider('invalidPayloads')]
    public function testInvalidChecksumsLengthsAndCharacters(string $payload): void
    {
        $this->bcExpectException(\Com\Tecnick\Barcode\Exception::class);
        $this->getTestObject()->getBarcodeObj('UPCA', $payload);
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
        $bobj = $barcode->getBarcodeObj('UPCA', '0123456789');
        $grid = $bobj->getGrid();
        $expected = "10100011010001101001100100100110111101010001101010100111010100001000100100100011101001001110101\n";
        $this->assertEquals($expected, $grid);

        $bobj = $barcode->getBarcodeObj('UPCA', '012345678912');
        $grid = $bobj->getGrid();
        $expected = "10100011010011001001001101111010100011011000101010101000010001001001000111010011001101101100101\n";
        $this->assertEquals($expected, $grid);
    }
}
