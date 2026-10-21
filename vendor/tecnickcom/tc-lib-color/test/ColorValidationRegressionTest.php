<?php

declare(strict_types=1);

namespace Test;

use Com\Tecnick\Color\Exception as ColorException;
use Com\Tecnick\Color\Model\Lab;
use Com\Tecnick\Color\Pdf;
use Com\Tecnick\Color\Spot;
use Com\Tecnick\Color\Web;
use PHPUnit\Framework\Attributes\DataProvider;

class ColorValidationRegressionTest extends TestUtil
{
    public static function invalidLabComponents(): array
    {
        $cases = [];
        foreach (['lstar', 'astar', 'bstar', 'alpha'] as $key) {
            foreach ([NAN, INF, -INF, 'bad', '', '1e9999', true, null, []] as $value) {
                $cases[] = [$key, $value];
            }
        }
        return $cases;
    }

    #[DataProvider('invalidLabComponents')]
    public function testLabRejectsInvalidComponents(string $key, mixed $value): void
    {
        $this->expectException(ColorException::class);
        new Lab([$key => $value]);
    }

    public function testLabPreservesNativeClampingAndNumericStrings(): void
    {
        $this->assertSame(['L' => 100.0, 'a' => -128.0, 'b' => 127.0, 'A' => 0.25],
            (new Lab(['lstar' => '200', 'astar' => -200, 'bstar' => 200, 'alpha' => '.25']))->getArray());
    }

    public static function invalidOptions(): array
    {
        return [
            [[[INF, 1, 1]]], [[[1, NAN, 1]]], [[[0, 1, 1]]], [[[1, 2, 1]]],
            [[[1, 1]]], [[[1, 1, 1, 1]]], [[[1, 1, 1], [-1, 0, 0]]],
            [[[1, 1, 1], [0, 0, 0], [10, -10, -128, 127]]],
            [[[1, 1, 1], [0, 0, 0], [-128, 127, 10, -10]]],
            [[[1, 1, 1], [0, 0, 0], [-128, 127, -128, 127], [100, 0, INF]]],
            [[['x' => 1, 'y' => 1, 'z' => 1]]], [[['bad', 1, 1]]],
            [[[1, 1, 1], [0, 0, 0], [-128, 127, -128, 127], [100, 0, 0], []]],
        ];
    }

    #[DataProvider('invalidOptions')]
    public function testInvalidSpotOptionsDoNotRegister(array $options): void
    {
        $spot = new Spot();
        try {
            $spot->addSpotLabColor('Invalid', 50, 0, 0, ...$options);
            $this->fail('Expected a library exception');
        } catch (ColorException) {
            $this->assertSame([], $spot->getSpotColors());
        }
    }

    public function testValidCustomSpotMetadata(): void
    {
        $spot = new Spot();
        $spot->addSpotLabColor('Valid', 50, 10, -10,
            [0.9642, 1, 0.8249], [0, 0, 0], [-100, 100, -100, 100], [100, 0, 0]);
        $number = 0;
        $this->assertStringContainsString('/WhitePoint [0.964200 1.000000 0.824900]', $spot->getPdfSpotObjects($number));
    }

    public static function nonFiniteValues(): array
    {
        return [[NAN], [INF], [-INF]];
    }

    #[DataProvider('nonFiniteValues')]
    public function testInvalidTintDoesNotRegister(float $value): void
    {
        $pdf = new Pdf();
        try {
            $pdf->getPdfColor('cyan', false, $value);
            $this->fail('Expected a library exception');
        } catch (ColorException) {
            $this->assertSame([], $pdf->getSpotColors());
        }
    }

    #[DataProvider('nonFiniteValues')]
    public function testInvalidSpotComponentsDoNotRegister(float $value): void
    {
        $spot = new Spot();
        try {
            $spot->addSpotLabColor('Invalid', $value, 0, 0);
            $this->fail('Expected a library exception');
        } catch (ColorException) {
            $this->assertSame([], $spot->getSpotColors());
        }
    }

    public function testNormalizerFallbacksAndCompatibility(): void
    {
        $web = new Web();
        foreach (['5%0%', '50%%', 'bad', NAN, INF, -INF, [], null, true] as $value) {
            $this->assertSame(0.0, $web->normalizeValue($value, 255));
        }
        $this->assertSame(0.5, $web->normalizeValue(' 50% ', 255));
        $this->assertSame(0.5, $web->normalizeValue(' 127.5 ', 255));
        $this->assertSame(1.0, $web->normalizeValue(300, 255));
        $this->assertSame(0.0, $web->normalizeValue(-1, 255));
    }

    public function testZeroNormalizationMaximum(): void
    {
        $this->expectException(ColorException::class);
        (new Web())->normalizeValue(1, 0);
    }

    public function testNegativeNormalizationMaximum(): void
    {
        $this->expectException(ColorException::class);
        (new Web())->normalizeValue('50%', -1);
    }

    public function testHexRejectsTerminalNewline(): void
    {
        $this->expectException(ColorException::class);
        (new Web())->extractHexCode("#fff\n");
    }

    public function testFiniteTintClamping(): void
    {
        $pdf = new Pdf();
        $this->assertSame("/CS1 cs 0.000000 scn\n", $pdf->getPdfColor('cyan', false, -1));
        $this->assertSame("/CS1 CS 1.000000 SCN\n", $pdf->getPdfColor('cyan', true, 2));
    }
}
