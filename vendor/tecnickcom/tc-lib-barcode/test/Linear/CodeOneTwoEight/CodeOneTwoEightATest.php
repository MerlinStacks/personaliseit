<?php

/**
 * ATest.php
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
class CodeOneTwoEightATest extends TestUtil
{
    public function testAsciiBoundariesAndFunctionCodes(): void
    {
        foreach ([
            ["\x00\x1f _", [103, 64, 95, 0, 63, 94, 106, 107]],
            ["\xf1\xf2\xf3\xf4", [103, 102, 97, 96, 101, 61, 106, 107]],
        ] as [$payload, $expected]) {
            $type = $this->getTestObject()->getBarcodeObj('C128A', $payload);
            $this->assertSame($expected, (new \ReflectionMethod($type, 'getCodeData'))->invoke($type));
        }
    }

    /** @return array<array{string}> */
    public static function invalidPayloads(): array
    {
        return \array_map(static fn (string $code): array => [$code], ['', 'a', '`', "\x7f", "\x80", "\xf0", "\xf5"]);
    }

    #[DataProvider('invalidPayloads')]
    public function testUnsupportedPayloadsAreRejected(string $payload): void
    {
        $this->bcExpectException(\Com\Tecnick\Barcode\Exception::class);
        $this->getTestObject()->getBarcodeObj('C128A', $payload);
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
        $bobj = $barcode->getBarcodeObj('C128A', 'ABCDEFG');
        $grid = $bobj->getGrid();
        $expected =
            '110100001001010001100010001011000100010001101011000100010001101000100011000101101'
            . "0001000100110010001100011101011\n";
        $this->assertEquals($expected, $grid);

        $bobj = $barcode->getBarcodeObj('C128A', '0123456789');
        $grid = $bobj->getGrid();
        $expected =
            '110100001001001110110010011100110110011100101100101110011001001110110111001001100'
            . "1110100111011011101110100110011100101100111101110101100011101011\n";
        $this->assertEquals($expected, $grid);

        $bobj = $barcode->getBarcodeObj('C128A', \chr(241) . '01234567891');
        $grid = $bobj->getGrid();
        $expected =
            '110100001001111010111010011101100100111001101100111001011001011100110010011101101'
            . "11001001100111010011101101110111010011001110010110010011100110100001101001100011101011\n";
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
        $barcode->getBarcodeObj('C128A', \chr(246) . '01234567891');
    }
}
