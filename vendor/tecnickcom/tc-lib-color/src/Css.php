<?php

declare(strict_types=1);

/**
 * Css.php
 *
 * @since     2015-02-21
 * @category  Library
 * @package   Color
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2015-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-color
 *
 * This file is part of tc-lib-color software library.
 */

namespace Com\Tecnick\Color;

use Com\Tecnick\Color\Exception as ColorException;

/**
 * Com\Tecnick\Color\Css
 *
 * Css Color class
 *
 * @since     2015-02-21
 * @category  Library
 * @package   Color
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2015-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-color
 */
abstract class Css
{
    private const NUMBER = '[+-]?(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)(?:[eE][+-]?[0-9]+)?';

    /** @return array<int, string> */
    private function parseCssComponents(string $color, string $types, int $count, bool $alpha = true): array
    {
        $number = '(' . self::NUMBER . '%?)';
        $separator = '(?:\s*,\s*|\s+)';
        $pattern = '~\A(?:' . $types . ')\s*\(\s*' . $number
            . \str_repeat($separator . $number, $count - 1)
            . ($alpha ? '(?:\s*[/,]\s*' . $number . ')?' : '') . '\s*\)\z~i';
        $values = [];
        if (\preg_match($pattern, $color, $values) !== 1) {
            throw new ColorException('invalid css color: ' . $color);
        }
        foreach (\array_slice($values, 1) as $value) {
            if (!\is_finite((float) \rtrim($value, '%'))) {
                throw new ColorException('non-finite css color component');
            }
        }
        return $values;
    }

    abstract public function normalizeValue(mixed $value, int $max): float;

    /**
     * Get the color object from acrobat Javascript syntax
     *
     * @param string $color color specification (e.g.: ["RGB",0.1,0.3,1])
     *
     * @throws ColorException if the color is not found
     */
    protected function getColorObjFromJs(string $color): ?\Com\Tecnick\Color\Model
    {
        $col = [];
        $pattern = '~\A\[\s*([\'\"])(t|g|rgb|cmyk)\1((?:\s*,\s*' . self::NUMBER . ')*)\s*\]\z~i';
        if (\preg_match($pattern, \trim($color), $col) !== 1) {
            throw new ColorException('invalid javascript color: ' . $color);
        }
        $type = \strtolower($col[2]);
        $raw = \trim($col[3]);
        $values = $raw === '' ? [] : \array_map('trim', \explode(',', \substr($raw, 1)));
        $expected = ['t' => 0, 'g' => 1, 'rgb' => 3, 'cmyk' => 4];
        if (\count($values) !== $expected[$type]) {
            throw new ColorException('invalid javascript color component count');
        }
        foreach ($values as $value) {
            if (!\is_finite((float) $value)) {
                throw new ColorException('non-finite javascript color component');
            }
        }
        return match ($type) {
            'g' => new \Com\Tecnick\Color\Model\Gray(['gray' => $values[0]]),
            'rgb' => new \Com\Tecnick\Color\Model\Rgb([
                'red' => $values[0], 'green' => $values[1], 'blue' => $values[2],
            ]),
            'cmyk' => new \Com\Tecnick\Color\Model\Cmyk([
                'cyan' => $values[0], 'magenta' => $values[1], 'yellow' => $values[2], 'key' => $values[3],
            ]),
            default => null,
        };
    }

    /**
     * Get the color object from a CSS color string
     *
     * @param string $type  color type: t, g, rgb, rgba, hsl, hsla, cmyk, cmyka, lab
     * @param string $color color specification (e.g.: rgb(255,128,64))
     *
     * @throws ColorException if the color is not found
     */
    protected function getColorObjFromCss(string $type, string $color): ?\Com\Tecnick\Color\Model
    {
        $color = \trim($color);
        switch ($type) {
            case 't':
                if (\preg_match('/\At\s*\(\s*\)\z/i', $color) !== 1) {
                    throw new ColorException('invalid transparent color: ' . $color);
                }
                return null;
            case 'g':
                return $this->getColorObjFromCssGray($color);
            case 'rgb':
            case 'rgba':
                return $this->getColorObjFromCssRgb($color);
            case 'hsl':
            case 'hsla':
                return $this->getColorObjFromCssHsl($color);
            case 'cmyk':
            case 'cmyka':
                return $this->getColorObjFromCssCmyk($color);
            case 'lab':
                return $this->getColorObjFromCssLab($color);
        }

        throw new ColorException('invalid css color type: ' . $type);
    }

    /**
     * Get the color object from a CSS Gray color string
     *
     * @param string $color color specification (e.g.: g(128))
     *
     * @throws ColorException if the color is not found
     */
    private function getColorObjFromCssGray(string $color): \Com\Tecnick\Color\Model\Gray
    {
        $col = $this->parseCssComponents($color, 'g', 1, false);

        return new \Com\Tecnick\Color\Model\Gray([
            'gray' => $this->normalizeValue($col[1] ?? '0', 255),
            'alpha' => 1,
        ]);
    }

    /**
     * Get the color object from a CSS RGB/RGBA color string
     *
     * @param string $color color specification (e.g.: rgb(255,128,64))
     *
     * @throws ColorException if the color is not found
     */
    private function getColorObjFromCssRgb(string $color): \Com\Tecnick\Color\Model\Rgb
    {
        $col = $this->parseCssComponents($color, 'rgba|rgb', 3);

        return new \Com\Tecnick\Color\Model\Rgb([
            'red' => $this->normalizeValue($col[1] ?? '0', 255),
            'green' => $this->normalizeValue($col[2] ?? '0', 255),
            'blue' => $this->normalizeValue($col[3] ?? '0', 255),
            'alpha' => $this->normalizeAlpha($col[4] ?? ''),
        ]);
    }

    /**
     * Get the color object from a CSS HSL/HSLA color string
     *
     * @param string $color color specification (e.g.: hsl(120,100%,50%))
     *
     * @throws ColorException if the color is not found
     */
    private function getColorObjFromCssHsl(string $color): \Com\Tecnick\Color\Model\Hsl
    {
        $col = $this->parseCssComponents($color, 'hsla|hsl', 3);
        $hue = (float) \rtrim($col[1], '%') / (\str_ends_with($col[1], '%') ? 100 : 360);
        $hue = \fmod($hue, 1.0);
        if ($hue < 0) {
            $hue += 1.0;
        }

        // Saturation and lightness are CSS percentages: a bare number (e.g. "50")
        // is treated the same as "50%", i.e. divided by 100, not by the max.
        return new \Com\Tecnick\Color\Model\Hsl([
            'hue' => $hue,
            'saturation' => $this->normalizeValue($col[2] ?? '0', 100),
            'lightness' => $this->normalizeValue($col[3] ?? '0', 100),
            'alpha' => $this->normalizeAlpha($col[4] ?? ''),
        ]);
    }

    /**
     * Get the color object from a CSS CMYK color string
     *
     * @param string $color color specification (e.g.: rgb(255,128,64))
     *
     * @throws ColorException if the color is not found
     */
    private function getColorObjFromCssCmyk(string $color): \Com\Tecnick\Color\Model\Cmyk
    {
        $col = $this->parseCssComponents($color, 'cmyka|cmyk', 4);

        return new \Com\Tecnick\Color\Model\Cmyk([
            'cyan' => $this->normalizeValue($col[1] ?? '0', 100),
            'magenta' => $this->normalizeValue($col[2] ?? '0', 100),
            'yellow' => $this->normalizeValue($col[3] ?? '0', 100),
            'key' => $this->normalizeValue($col[4] ?? '0', 100),
            'alpha' => $this->normalizeAlpha($col[5] ?? ''),
        ]);
    }

    /**
     * Get the color object from a CSS Lab color string.
     *
     * Supports forms such as: lab(52% 0 -39), lab(52% 0 -39 / 0.85), lab(52,0,-39,0.85)
     *
     * @throws ColorException if the color is not found
     */
    private function getColorObjFromCssLab(string $color): \Com\Tecnick\Color\Model\Lab
    {
        $col = $this->parseCssComponents($color, 'lab', 3);
        if (\str_ends_with($col[2], '%') || \str_ends_with($col[3], '%')) {
            throw new ColorException('Lab a/b components require numeric coordinates');
        }

        $lstarRaw = $col[1] ?? '0';
        $lstarVal = str_ends_with($lstarRaw, '%') ? \str_replace('%', '', $lstarRaw) : $lstarRaw;
        $lstar = \is_numeric($lstarVal) ? (float) $lstarVal : 0.0;

        return new \Com\Tecnick\Color\Model\Lab([
            'lstar' => $lstar,
            'astar' => $col[2] ?? '0',
            'bstar' => $col[3] ?? '0',
            'alpha' => $this->normalizeAlpha($col[4] ?? ''),
        ]);
    }

    /**
     * Normalize a CSS alpha value to a float in the [0..1] range.
     *
     * Accepts a fraction (e.g. "0.85") or a percentage (e.g. "85%"). An empty
     * string yields 1.0 (fully opaque).
     */
    private function normalizeAlpha(string $alpha): float
    {
        if ($alpha === '') {
            return 1.0;
        }

        if (str_ends_with($alpha, '%')) {
            $value = \str_replace('%', '', $alpha);
            return \is_numeric($value) ? \max(0.0, \min(1.0, (float) $value / 100.0)) : 1.0;
        }

        return \is_numeric($alpha) ? \max(0.0, \min(1.0, (float) $alpha)) : 1.0;
    }
}
