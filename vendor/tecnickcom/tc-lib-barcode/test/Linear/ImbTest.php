<?php

/**
 * ImbTest.php
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
class ImbTest extends TestUtil
{
    protected function getTestObject(): \Com\Tecnick\Barcode\Barcode
    {
        return new \Com\Tecnick\Barcode\Barcode();
    }

    /**
     * @throws \Com\Tecnick\Barcode\Exception
     * @throws \Com\Tecnick\Color\Exception
     */
    public function testElevenDigitRoutingGrid(): void
    {
        $barcode = $this->getTestObject();
        $bobj = $barcode->getBarcodeObj('IMB', '01234567094987654321-01234567891');
        $grid = $bobj->getGrid();
        $expected =
            '10100000101000100000100000101000101000100000000010101000000000000000101010001'
            . "0000000001010100000000000100010101010001000001010001\n"
            . '101010101010101010101010101010101010101010101010101010101010101010101010101010101010'
            . "101010101010101010101010101010101010101010101\n"
            . '000010001010101000100010000000100000001010001010000000101000100000100010001000101010'
            . "001010101010000000001010000000101000100000100\n";
        $this->assertEquals($expected, $grid);

    }

    public function testNineDigitRoutingGrid(): void
    {
        $barcode = $this->getTestObject();
        $bobj = $barcode->getBarcodeObj('IMB', '01234567094987654321-012345678');
        $grid = $bobj->getGrid();
        $expected =
            '10001000001010000000000010100000100000101010001000100010000010101010000010001'
            . "0001000000000000000100000001000100010000000101000101\n"
            . '101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010'
            . "101010101010101010101010101010101\n"
            . '001010000000101000000000100000000010000000000010001000000010000000101010001000000000100010000010'
            . "101000100000001000100010101000100\n";
        $this->assertEquals($expected, $grid);

    }

    public function testFiveDigitRoutingGrid(): void
    {
        $barcode = $this->getTestObject();
        $bobj = $barcode->getBarcodeObj('IMB', '01234567094987654321-01234');
        $grid = $bobj->getGrid();
        $expected =
            '00000010101000000000100000001000100000000010001000101010001010000000100010101'
            . "0100000001000101010100000100000001010000000000010100\n"
            . '101010101010101010101010101010101010101010101010101010101010101010101010101010101010'
            . "101010101010101010101010101010101010101010101\n"
            . '100000001000101000001000100010001010001010001000100010001010000010101000000000101000'
            . "000010100000000010101000101000101010001010100\n";
        $this->assertEquals($expected, $grid);

    }

    public function testNoRoutingGrid(): void
    {
        $barcode = $this->getTestObject();
        $bobj = $barcode->getBarcodeObj('IMB', '01234567094987654321-');
        $grid = $bobj->getGrid();
        $expected =
            '10000010100000000000100000101000000000000010000000101000001010001000100010101'
            . "0101000100010101010100000101000001010001000100000000\n"
            . '101010101010101010101010101010101010101010101010101010101010101010101010101010101010'
            . "101010101010101010101010101010101010101010101\n"
            . '000000100000001000000010000000000010001000000000100010101010001010101000101010101000'
            . "000010000000000010101000100000101000101000100\n";
        $this->assertEquals($expected, $grid);

        $this->assertSame($grid, $barcode->getBarcodeObj('IMB', '01234567094987654321')->getGrid());
    }

    /** @return array<array{string}> */
    public static function invalidTrackingPayloads(): array
    {
        return [['00000-'], ['0123456789'], ['05234567094987654321'], ['0123456709498765432A'],
            ['01234567094987654321-01234-01234']];
    }

    #[DataProvider('invalidTrackingPayloads')]
    public function testInvalidTrackingIsRejected(string $payload): void
    {
        $this->bcExpectException(\Com\Tecnick\Barcode\Exception::class);
        $this->getTestObject()->getBarcodeObj('IMB', $payload);
    }

    /**
     * @throws \Com\Tecnick\Barcode\Exception
     * @throws \Com\Tecnick\Color\Exception
     */
    public function testInvalidRoutingCode(): void
    {
        $this->bcExpectException(\Com\Tecnick\Barcode\Exception::class);
        $barcode = $this->getTestObject();
        $barcode->getBarcodeObj('IMB', '01234567094987654321-1');
    }
}
