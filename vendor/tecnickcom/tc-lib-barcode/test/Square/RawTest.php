<?php

/**
 * RawTest.php
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

namespace Test\Square;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Raw Barcode class test
 *
 * @since       2015-02-21
 * @category    Library
 * @package     Barcode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2015-2026 Nicola Asuni - Tecnick.com LTD
 * @license     https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-barcode
 */
class RawTest extends TestCase
{
    /** @return array<array{string}> */
    public static function formattedRectangles(): array
    {
        return [['101,010'], ['[101][010]'], [" [1 0 1,0 1 0] \n"]];
    }

    #[DataProvider('formattedRectangles')]
    public function testRectanglesAndFormatting(string $payload): void
    {
        $type = $this->getTestObject()->getBarcodeObj('SRAW', $payload);
        $this->assertSame("101\n010\n", $type->getGrid());
        $this->assertSame(3, $type->getArray()['ncols']);
        $this->assertSame(2, $type->getArray()['nrows']);
    }

    /** @return array<array{string}> */
    public static function malformedRows(): array
    {
        return [[''], ['101,01'], ['10x,010'], ['10,,01'], ['[10][1]']];
    }

    #[DataProvider('malformedRows')]
    public function testMalformedRowsAreRejected(string $payload): void
    {
        $this->expectException(\Com\Tecnick\Barcode\Exception::class);
        $this->getTestObject()->getBarcodeObj('SRAW', $payload);
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
        $type = $barcode->getBarcodeObj('SRAW', '0101,1010,1100,0011');
        $grid = $type->getGrid();
        $expected = "0101\n1010\n1100\n0011\n";
        $this->assertEquals($expected, $grid);
    }
}
