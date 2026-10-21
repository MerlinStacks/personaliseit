<?php

/**
 * PlanetTest.php
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
class PlanetTest extends TestUtil
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
        $type = $barcode->getBarcodeObj('PLANET', '01234567890');
        $grid = $type->getGrid();
        $expected = "100000101010101010000010100010001010000010100010100010001000101000001010001010100000101000100010001010000010101010001000101\n"
            . "101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101010101\n";
        $this->assertEquals($expected, $grid);
    }

    /** @return array<array{string, string, string}> */
    public static function legalVectors(): array
    {
        return [
            ['01234567890', '012345678905', '21122222211221212211221221212122112212221122121212211222212122'],
            ['0123456789012', '01234567890122', '211222222112212122112212212121221122122211221212122112222221122121221212'],
            ['00000000000', '000000000000', '2' . \str_repeat('11222', 12) . '2'],
        ];
    }

    #[DataProvider('legalVectors')]
    public function testLegalLengthsChecksumsAndHeights(string $payload, string $extended, string $heights): void
    {
        $type = $this->getTestObject()->getBarcodeObj('PLANET', $payload);
        $this->assertSame($extended, $type->getExtendedCode());
        $this->assertSame(\array_map('intval', \str_split($heights)), \array_column($type->getArray()['bars'], 3));
    }

    public function testSeparatorNormalization(): void
    {
        $barcode = $this->getTestObject();
        $this->assertSame($barcode->getBarcodeObj('PLANET', '01234567890')->getGrid(), $barcode->getBarcodeObj('PLANET', "01234-567 890\n")->getGrid());
    }

    /** @return array<array{string}> */
    public static function invalidPayloads(): array
    {
        return [[''], ['0123456789'], ['123456789012'], ['12345678901234'], ['0123456789A']];
    }

    #[DataProvider('invalidPayloads')]
    public function testInvalidPayloadsAreRejected(string $payload): void
    {
        $this->bcExpectException(\Com\Tecnick\Barcode\Exception::class);
        $this->getTestObject()->getBarcodeObj('PLANET', $payload);
    }
}
