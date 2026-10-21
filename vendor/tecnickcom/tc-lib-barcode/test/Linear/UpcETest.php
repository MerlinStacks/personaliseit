<?php

/**
 * UpcETest.php
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
class UpcETest extends TestUtil
{
    protected function getTestObject(): \Com\Tecnick\Barcode\Barcode
    {
        return new \Com\Tecnick\Barcode\Barcode();
    }

    /**
     * @throws \Com\Tecnick\Barcode\Exception
     * @throws \Com\Tecnick\Color\Exception
     */
    public function testCompactEndingZero(): void
    {
        $barcode = $this->getTestObject();
        $bobj = $barcode->getBarcodeObj('UPCE', '725270');
        $grid = $bobj->getGrid();
        $expected = "101001000100100110110001001101101110110100111010101\n";
        $this->assertEquals($expected, $grid);

    }

    public function testCompactEndingOne(): void
    {
        $barcode = $this->getTestObject();
        $bobj = $barcode->getBarcodeObj('UPCE', '725271');
        $grid = $bobj->getGrid();
        $expected = "101001000100100110111001001001101110110110011010101\n";
        $this->assertEquals($expected, $grid);

    }

    public function testCompactEndingTwo(): void
    {
        $barcode = $this->getTestObject();
        $bobj = $barcode->getBarcodeObj('UPCE', '725272');
        $grid = $bobj->getGrid();
        $expected = "101001000100100110111001001001100100010010011010101\n";
        $this->assertEquals($expected, $grid);

    }

    public function testCompactEndingThree(): void
    {
        $barcode = $this->getTestObject();
        $bobj = $barcode->getBarcodeObj('UPCE', '725273');
        $grid = $bobj->getGrid();
        $expected = "101001000100100110110001001101101110110100001010101\n";
        $this->assertEquals($expected, $grid);

    }

    public function testCompactEndingFour(): void
    {
        $barcode = $this->getTestObject();
        $bobj = $barcode->getBarcodeObj('UPCE', '725274');
        $grid = $bobj->getGrid();
        $expected = "101001000100100110110001001101100100010100011010101\n";
        $this->assertEquals($expected, $grid);

    }

    public function testCompactEndingFive(): void
    {
        $barcode = $this->getTestObject();
        $bobj = $barcode->getBarcodeObj('UPCE', '725275');
        $grid = $bobj->getGrid();
        $expected = "101001000100100110111001001101101110110110001010101\n";
        $this->assertEquals($expected, $grid);

    }

    public function testCompactEndingSix(): void
    {
        $barcode = $this->getTestObject();
        $bobj = $barcode->getBarcodeObj('UPCE', '725276');
        $grid = $bobj->getGrid();
        $expected = "101001000100110110110001001101101110110101111010101\n";
        $this->assertEquals($expected, $grid);

    }

    public function testCompactEndingSeven(): void
    {
        $barcode = $this->getTestObject();
        $bobj = $barcode->getBarcodeObj('UPCE', '725277');
        $grid = $bobj->getGrid();
        $expected = "101001000100100110111001001001101110110010001010101\n";
        $this->assertEquals($expected, $grid);

    }

    public function testCompactEndingEight(): void
    {
        $barcode = $this->getTestObject();
        $bobj = $barcode->getBarcodeObj('UPCE', '725278');
        $grid = $bobj->getGrid();
        $expected = "101001000100100110110001001101100100010110111010101\n";
        $this->assertEquals($expected, $grid);

    }

    public function testCompactEndingNine(): void
    {
        $barcode = $this->getTestObject();
        $bobj = $barcode->getBarcodeObj('UPCE', '725279');
        $grid = $bobj->getGrid();
        $expected = "101001000100110110110001001001100100010001011010101\n";
        $this->assertEquals($expected, $grid);

    }

    public function testLosslessUpcaCompression(): void
    {
        $barcode = $this->getTestObject();
        $bobj = $barcode->getBarcodeObj('UPCE', '4210000526');
        $grid = $bobj->getGrid();
        $expected = "101001110100100110111001001101101011110011001010101\n";
        $this->assertEquals($expected, $grid);

    }

    /** @return array<array{string}> */
    public static function uncompressiblePayloads(): array
    {
        return [['0123456789'], ['012345678912'], ['4240000526'], ['4241000526']];
    }

    #[DataProvider('uncompressiblePayloads')]
    public function testLossyCompressionIsRejected(string $payload): void
    {
        $this->bcExpectException(\Com\Tecnick\Barcode\Exception::class);
        $this->getTestObject()->getBarcodeObj('UPCE', $payload);
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
        $barcode->getBarcodeObj('UPCE', 'abcdefg');
    }
}
