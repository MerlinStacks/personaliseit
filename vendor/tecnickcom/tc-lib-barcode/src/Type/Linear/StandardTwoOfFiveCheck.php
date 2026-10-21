<?php

declare(strict_types=1);

/**
 * StandardTwoOfFiveCheck.php
 *
 * @since       2015-02-21
 * @category    Library
 * @package     Barcode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2010-2026 Nicola Asuni - Tecnick.com LTD
 * @license     https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-barcode
 *
 * This file is part of tc-lib-barcode software library.
 */

namespace Com\Tecnick\Barcode\Type\Linear;

use Com\Tecnick\Barcode\Exception as BarcodeException;

/**
 * Com\Tecnick\Barcode\Type\Linear\StandardTwoOfFiveCheck;
 *
 * StandardTwoOfFiveCheck Barcode type class
 * Standard 2 of 5 + CHECKSUM
 *
 * @since       2015-02-21
 * @category    Library
 * @package     Barcode
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2010-2026 Nicola Asuni - Tecnick.com LTD
 * @license     https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-barcode
 */
class StandardTwoOfFiveCheck extends \Com\Tecnick\Barcode\Type\Linear
{
    protected function getPattern(string $digit): string
    {
        return $this::CHBAR[$digit] ?? '';
    }

    /**
     * Barcode format
     *
     * @var string
     */
    protected const FORMAT = 'S25+';

    /**
     * Map characters to barcodes
     *
     * @var array<int|string, string>
     */
    protected const CHBAR = [
        '0' => '10101110111010',
        '1' => '11101010101110',
        '2' => '10111010101110',
        '3' => '11101110101010',
        '4' => '10101110101110',
        '5' => '11101011101010',
        '6' => '10111011101010',
        '7' => '10101011101110',
        '8' => '11101010111010',
        '9' => '10111010111010',
    ];

    /**
     * Calculate the checksum
     *
     * @param string $code Code to represent.
     *
     * @return int char checksum.
     */
    protected function getChecksum(string $code): int
    {
        $sum = 0;
        $weight = 3;
        for ($idx = \strlen($code) - 1; $idx >= 0; --$idx) {
            $sum += (int) $code[$idx] * $weight;
            $weight = $weight === 3 ? 1 : 3;
        }

        return (10 - ($sum % 10)) % 10;
    }

    /**
     * Format code
     */
    protected function formatCode(): void
    {
        $this->extcode = $this->code . (string) $this->getChecksum($this->code);
    }

    /**
     * Set the bars array.
     *
     * @throws BarcodeException in case of error
     */
    protected function setBars(): void
    {
        if (\preg_match('/\A[0-9]+\z/', $this->code) !== 1) {
            throw new BarcodeException('Standard 2 of 5 requires a non-empty digit-only payload');
        }

        $this->formatCode();
        if ((\strlen($this->extcode) % 2) !== 0) {
            // add leading zero if code-length is odd
            $this->extcode = '0' . $this->extcode;
        }

        $seq = '1110111010';
        $clen = \strlen($this->extcode);
        for ($idx = 0; $idx < $clen; ++$idx) {
            $digit = $this->extcode[$idx];
            if (!\array_key_exists($digit, $this::CHBAR)) {
                throw new BarcodeException('Invalid character: ' . (\ord($digit) & 0xFF));
            }

            $seq .= $this->getPattern($digit);
        }

        $seq .= '111010111';
        $this->processBinarySequence($this->getRawCodeRows($seq));
    }
}
