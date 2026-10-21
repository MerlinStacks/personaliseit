<?php

declare(strict_types=1);

namespace Test;

use Com\Tecnick\Color\Exception as ColorException;
use Com\Tecnick\Color\Web;
use PHPUnit\Framework\Attributes\DataProvider;

class ColorParsingRegressionTest extends TestUtil
{
    /** @return array<array{string, string}> */
    public static function validColors(): array
    {
        return [
            ['rgb(255,0,0)', '#ff0000ff'],
            ['rgba(255,0,0,.5)', '#ff000080'],
            ['rgb(100% 0% 0% / 50%)', '#ff000080'],
            ['g(50%)', '#808080ff'],
            ['cmyk(0,100,100,0)', '#ff0000ff'],
            ['cmyka(0,100,100,0,25%)', '#ff000040'],
            ['lab(52% 0 -39)', '#407fbfff'],
            ['["RGB",.25,0.50,.75]', '#4080bfff'],
            ["['rgb',+0.25,0.5,7.5e-1]", '#4080bfff'],
            ['[ "RGB" , .25 , .5 , .75 ]', '#4080bfff'],
            ['rgb(-1,+300,0)', '#00ff00ff'],
            ['rgb(2.55e2,0,0)', '#ff0000ff'],
            ['hsl(480,100%,50%)', '#00ff00ff'],
            ['hsl(-240,100%,50%)', '#00ff00ff'],
            ['hsl(840,100%,50%)', '#00ff00ff'],
            ['hsl(-600 100% 50%)', '#00ff00ff'],
            ['hsl(360,100%,50%)', '#ff0000ff'],
            ['hsl(-360,100%,50%)', '#ff0000ff'],
            ['hsl(50%,100%,50%)', '#00ffffff'],
            ['hsl(150%,100%,50%)', '#00ffffff'],
            ['["G",.5]', '#808080ff'],
            ["['CMYK',0,1,1,0]", '#ff0000ff'],
            ['["RGB",-1,2,0]', '#00ff00ff'],
        ];
    }

    #[DataProvider('validColors')]
    public function testValidColorsAndHueWrapping(string $input, string $expected): void
    {
        $color = (new Web())->getColorObj($input);
        $this->assertNotNull($color);
        $this->assertSame($expected, $color->getRgbaHexColor());
    }

    /** @return array<array{string}> */
    public static function malformedColors(): array
    {
        return \array_map(static fn (string $value): array => [$value], [
            'g(.)', 'rgb(..,0,0)', 'rgb(1.2.3,0,0)', 'rgb(255,0,0)junk',
            "rgb(255,0,0)\ntrailing", 'rgb(255,,0,0)', 'rgb(255,0,)',
            'rgba(255,0,0,.)', 'rgb(1e9999,0,0)', 'g(50%)rgb(0,0,0)',
            'rgb(rgb(255,0,0))', 'hsl(-,100%,50%)', 'lab(52 0 --39)',
            '["RGB",.1,.2,.3]junk', '["T",1]', '["T"]junk',
            '["RGB",0,0]', '["RG",0,0,0]', "[\"RGB',0,0,0]",
            'rgb(0,0,0)transparent', 'not-transparent', 't(extra)',
            '["RGB",1e9999,0,0]', '["CMYK",0,0,0]', '["G",0,1]',
            'rgba(0,0,0,1e9999)', 'cmyk(0,0,0,0)junk', 'lab(50 0 0)junk',
        ]);
    }

    #[DataProvider('malformedColors')]
    public function testMalformedColorsAreRejected(string $input): void
    {
        $this->expectException(ColorException::class);
        (new Web())->getColorObj($input);
    }

    /** @return array<array{string, string, mixed}> */
    public static function invalidComponents(): array
    {
        $cases = [];
        foreach (['Rgb' => 'red', 'Gray' => 'gray', 'Hsl' => 'hue', 'Cmyk' => 'cyan'] as $model => $key) {
            foreach (['bad', '', '1..2', '1e9999', NAN, INF, -INF, true, null, []] as $value) {
                $cases[] = [$model, $key, $value];
            }
        }
        return $cases;
    }

    #[DataProvider('invalidComponents')]
    public function testInvalidComponentsUseLibraryExceptions(string $model, string $key, mixed $value): void
    {
        $this->expectException(ColorException::class);
        $class = 'Com\\Tecnick\\Color\\Model\\' . $model;
        new $class([$key => $value]);
    }

    public function testFiniteClampingAndNumericStringsRemainCompatible(): void
    {
        $color = new \Com\Tecnick\Color\Model\Rgb(['red' => ' 0.25 ', 'green' => 2.0, 'blue' => -0.5]);
        $this->assertSame(['R' => 0.25, 'G' => 1.0, 'B' => 0.0, 'A' => 1.0], $color->getArray());
    }

    public function testExplicitTransparentFormsRemainCompatible(): void
    {
        $web = new Web();
        foreach (['', 'transparent', 'color.transparent', 't()', 't( )', '["T"]', "[ 't' ]"] as $input) {
            $this->assertNull($web->getColorObj($input));
        }
    }
}
