<?php

/**
 * EanTwoTest.php
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
class EanTwoTest extends TestUtil
{
    /** @return array<array{string, string}> */
    public static function parityVectors(): array
    {
        return [
            ['00', '10110001101010001101'], ['01', '10110001101010110011'],
            ['02', '10110100111010010011'], ['03', '10110100111010100001'],
        ];
    }

    #[DataProvider('parityVectors')]
    public function testAllFourParities(string $payload, string $expected): void
    {
        $this->assertSame($expected . "\n", $this->getTestObject()->getBarcodeObj('EAN2', $payload)->getGrid());
    }

    public function testSingleDigitPadding(): void
    {
        $barcode = $this->getTestObject();
        $short = $barcode->getBarcodeObj('EAN2', '1');
        $this->assertSame('01', $short->getExtendedCode());
        $this->assertSame($barcode->getBarcodeObj('EAN2', '01')->getGrid(), $short->getGrid());
    }

    /** @return array<array{string}> */
    public static function invalidPayloads(): array
    {
        return [[''], ['123'], ['1A'], [' 1'], ["1\n"], ['-1'], ["\xff"]];
    }

    #[DataProvider('invalidPayloads')]
    public function testInvalidPayloadsAreRejected(string $payload): void
    {
        $this->bcExpectException(\Com\Tecnick\Barcode\Exception::class);
        $this->getTestObject()->getBarcodeObj('EAN2', $payload);
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
        $type = $barcode->getBarcodeObj('EAN2', '12');
        $grid = $type->getGrid();
        $expected = "10110011001010010011\n";
        $this->assertEquals($expected, $grid);
    }
}
