<?php

/**
 * CodeThreeNineCheckTest.php
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
class CodeThreeNineCheckTest extends TestUtil
{
    public function testChecksumZeroWraparoundAndCaseNormalization(): void
    {
        foreach (['0' => '*00*', 'Z8' => '*Z80*', 'ZZ' => '*ZZR*', 'abc' => '*ABCX*'] as $payload => $expected) {
            $this->assertSame($expected, $this->getTestObject()->getBarcodeObj('C39+', (string) $payload)->getExtendedCode());
        }
    }

    /** @return array<array{string}> */
    public static function invalidPayloads(): array
    {
        return [['*'], ['A*B'], ['@'], ["\x80"], ["A\n"]];
    }

    #[DataProvider('invalidPayloads')]
    public function testInvalidSymbolsAreRejected(string $payload): void
    {
        $this->bcExpectException(\Com\Tecnick\Barcode\Exception::class);
        $this->getTestObject()->getBarcodeObj('C39+', $payload);
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
        $type = $barcode->getBarcodeObj('C39+', '0123456789');
        $grid = $type->getGrid();
        $expected =
            '10001011101110101010001110111010111010001010111010111000101011101110111000101010101000'
            . '111010111011101000111010101011100011101010101000101110111011101000101110101011100010111010101'
            . "1100010101110100010111011101\n";
        $this->assertEquals($expected, $grid);
    }
}
