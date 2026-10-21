<?php

/**
 * BTest.php
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

namespace Test\Linear\CodeOneTwoEight;

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
class CodeOneTwoEightBTest extends TestUtil
{
    public function testLowercaseBoundariesAndFunctionCodes(): void
    {
        foreach ([
            [" az\x7f", [104, 0, 65, 90, 95, 60, 106, 107]],
            ["\xf1\xf2\xf3\xf4", [104, 102, 97, 96, 100, 58, 106, 107]],
        ] as [$payload, $expected]) {
            $type = $this->getTestObject()->getBarcodeObj('C128B', $payload);
            $this->assertSame($expected, (new \ReflectionMethod($type, 'getCodeData'))->invoke($type));
        }
    }

    /** @return array<array{string}> */
    public static function invalidPayloads(): array
    {
        return \array_map(static fn (string $code): array => [$code], ['', "\x00", "\x1f", "a\nb", "\x80", "\xf0", "\xf5"]);
    }

    #[DataProvider('invalidPayloads')]
    public function testUnsupportedPayloadsAreRejected(string $payload): void
    {
        $this->bcExpectException(\Com\Tecnick\Barcode\Exception::class);
        $this->getTestObject()->getBarcodeObj('C128B', $payload);
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
        $bobj = $barcode->getBarcodeObj('C128B', '0123456789');
        $grid = $bobj->getGrid();
        $expected =
            '11010010000100111011001001110011011001110010110010111001100100111011011100'
            . "10011001110100111011011101110100110011100101100110000101001100011101011\n";
        $this->assertEquals($expected, $grid);

        $bobj = $barcode->getBarcodeObj('C128B', \chr(241) . '01234567891');
        $grid = $bobj->getGrid();
        $expected =
            '11010010000111101011101001110110010011100110110011100101100101110011001001'
            . "110110111001001100111010011101101110111010011001110010110010011100110100001100101100011101011\n";
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
        $barcode->getBarcodeObj('C128B', \chr(246) . '01234567891');
    }
}
