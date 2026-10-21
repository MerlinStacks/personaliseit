<?php

/**
 * PostnetTest.php
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
class PostnetTest extends TestUtil
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
        $type = $barcode->getBarcodeObj('POSTNET', '12345');
        $grid = $type->getGrid();
        $expected = "100000001010000010001000001010000010000010001000100000100010001\n"
            . "101010101010101010101010101010101010101010101010101010101010101\n";
        $this->assertEquals($expected, $grid);
    }

    /** @return array<array{string, string, string}> */
    public static function legalVectors(): array
    {
        return [
            ['12345', '123455', '21112211212112211211212121121212'],
            ['123456789', '1234567895', '2111221121211221121121212112211211122112121211121212'],
            ['12345678901', '123456789014', '21112211212112211211212121122112111221121212112211111122121122'],
            ['00000', '000000', '2' . \str_repeat('22111', 6) . '2'],
        ];
    }

    #[DataProvider('legalVectors')]
    public function testLegalLengthsChecksumsAndHeights(string $payload, string $extended, string $heights): void
    {
        $type = $this->getTestObject()->getBarcodeObj('POSTNET', $payload);
        $this->assertSame($extended, $type->getExtendedCode());
        $this->assertSame(\array_map('intval', \str_split($heights)), \array_column($type->getArray()['bars'], 3));
    }

    public function testSeparatorNormalization(): void
    {
        $barcode = $this->getTestObject();
        $this->assertSame($barcode->getBarcodeObj('POSTNET', '123456789')->getGrid(), $barcode->getBarcodeObj('POSTNET', "12345-6789\n")->getGrid());
    }

    /** @return array<array{string}> */
    public static function invalidPayloads(): array
    {
        return [[''], ['1234'], ['123456'], ['0123456789'], ['123456789012'], ['1234A']];
    }

    #[DataProvider('invalidPayloads')]
    public function testInvalidLengthsAndCharacters(string $payload): void
    {
        $this->bcExpectException(\Com\Tecnick\Barcode\Exception::class);
        $this->getTestObject()->getBarcodeObj('POSTNET', $payload);
    }

    /**
     * @throws \Com\Tecnick\Barcode\Exception
     * @throws \Com\Tecnick\Color\Exception
     */
    public function testInvalidInput(): void
    {
        $this->bcExpectException(\Com\Tecnick\Barcode\Exception::class);
        $barcode = $this->getTestObject();
        $barcode->getBarcodeObj('POSTNET', '}{');
    }
}
