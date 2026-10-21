<?php

/**
 * ImbPreTest.php
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
class ImbPreTest extends TestUtil
{
    public function testUppercaseEquivalenceAndGeometry(): void
    {
        $payload = \str_repeat('fadt', 16) . 'f';
        $barcode = $this->getTestObject();
        $type = $barcode->getBarcodeObj('IMBPRE', $payload);
        $this->assertSame($type->getGrid(), $barcode->getBarcodeObj('IMBPRE', \strtoupper($payload))->getGrid());
        $this->assertCount(65, $type->getArray()['bars']);
        $this->assertSame(129, $type->getArray()['ncols']);
    }

    /** @return array<array{string}> */
    public static function invalidPayloads(): array
    {
        return [
            [\str_repeat('f', 64)], [\str_repeat('f', 66)],
            [\str_repeat('f', 64) . 'x'], [\str_repeat('f', 64) . "\n"],
        ];
    }

    #[DataProvider('invalidPayloads')]
    public function testInvalidLengthsAndStatesAreRejected(string $payload): void
    {
        $this->bcExpectException(\Com\Tecnick\Barcode\Exception::class);
        $this->getTestObject()->getBarcodeObj('IMBPRE', $payload);
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
        $type = $barcode->getBarcodeObj('IMBPRE', 'fatdfatdfatdfatdfatdfatdfatdfatdfatdfatdfatdfatdfatdfatdfatdfatdf');
        $grid = $type->getGrid();
        $expected =
            '101000001010000010100000101000001010000010100000101000001010'
            . "000010100000101000001010000010100000101000001010000010100000101000001\n"
            . '1010101010101010101010101010101010101010101010101010101010101010101'
            . "01010101010101010101010101010101010101010101010101010101010101\n"
            . '1000001010000010100000101000001010000010100000101000001010000010100'
            . "00010100000101000001010000010100000101000001010000010100000101\n";
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
        $barcode->getBarcodeObj('IMBPRE', 'fatd');
    }
}
