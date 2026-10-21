<?php

/**
 * TypeCoverageTest.php
 *
 * @since       2026-05-21
 * @category    Library
 * @package     Barcode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2015-2026 Nicola Asuni - Tecnick.com LTD
 * @license     https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-barcode
 *
 * This file is part of tc-lib-barcode software library.
 */

namespace Test;

use Test\Fixture\InternalBarcodeType;
use PHPUnit\Framework\Attributes\DataProvider;

class TypeCoverageTest extends TestUtil
{
    /** @return array<array{array<int>, array<array<float>>, array<array<float>>}> */
    public static function scaledRectangleVectors(): array
    {
        return [
            [[7, 3, 2, 5], [[6.5, 9.5, 3.0, 5.0], [5.0, 12.0, 1.5, 2.5]],
                [[6.5, 9.5, 8.5, 13.5], [5.0, 12.0, 5.5, 13.5]]],
            [[0, 0, 0, 0], [[1.5, 2.5, 3.0, 5.0], [0.0, 5.0, 1.5, 2.5]],
                [[1.5, 2.5, 3.5, 6.5], [0.0, 5.0, 0.5, 6.5]]],
        ];
    }

    /**
     * @param array{int, int, int, int} $padding
     * @param array<array<float>> $xywh
     * @param array<array<float>> $xyxy
     */
    #[DataProvider('scaledRectangleVectors')]
    public function testFractionalScaleAndPadding(array $padding, array $xywh, array $xyxy): void
    {
        $type = new InternalBarcodeType();
        $type->setRowsColsForTest(4, 4);
        $type->setSize(-1, -1, $padding);
        $type->setRatiosForTest(1.5, 2.5);
        $type->setBarsForTest([[1, 1, 2, 2], [0, 0, 0, 2]]);
        $type->setRotatedBarsOverrideForTest([[0, 2, 1, 1], [0, 0, 2, 0]]);
        $this->assertSame($xywh, $type->getBarsArrayXYWH());
        $this->assertSame($xyxy, $type->getBarsArrayXYXY());
    }
    /**
     * @throws \Com\Tecnick\Barcode\Exception
     * @throws \Com\Tecnick\Color\Exception
     */
    public function testBarsArrayFiltersInvalidBarsInBothPasses(): void
    {
        $type = new InternalBarcodeType();
        $type->setRowsColsForTest(3, 3);
        $type->setRatiosForTest(1.0, 1.0);
        $type->setBarsForTest([
            [0, 0, 0, 1],
            [0, 0, 1, 0],
            [1, 1, 1, 1],
        ]);
        $type->setRotatedBarsOverrideForTest([
            [0, 0, 0, 1],
            [0, 0, 1, 0],
            [2, 2, 1, 1],
        ]);

        $this->assertSame(
            [
                [2.0, 7.0, 2.0, 7.0],
                [3.0, 8.0, 3.0, 8.0],
            ],
            $type->getBarsArrayXYXY(),
        );

        $this->assertSame(
            [
                [2.0, 7.0, 1.0, 1.0],
                [3.0, 8.0, 1.0, 1.0],
            ],
            $type->getBarsArrayXYWH(),
        );
    }
}
