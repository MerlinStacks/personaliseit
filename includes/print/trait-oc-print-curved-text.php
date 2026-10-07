<?php
/**
 * Circular text geometry shared by PDF and embroidery output.
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

trait OC_Print_Curved_Text {

	/** Lay out em-sized glyphs. Keep in sync with shared/curved-text-layout.js. */
	protected static function curved_text_layout( array $widths, float $angle, float $width, float $height, float $size, float $minimum = 0, string $align = 'center' ): array {
		foreach ( [ $angle, $width, $height, $size, $minimum, ...$widths ] as $value ) {
			if ( ! is_finite( $value ) ) {
				throw new \RuntimeException( 'Invalid curved text geometry.' );
			}
		}
		$sweep = deg2rad( max( -180, min( 180, $angle ) ) );
		$total = array_sum( $widths );
		if ( ! $widths || $total <= 0 ) {
			return [
				'glyphs'   => [],
				'fontSize' => $size,
			];
		}
		$radius = abs( $sweep ) > 0.000001 ? $total / $sweep : 0;
		$cursor = 0.0;
		$min_x  = INF;
		$max_x  = -INF;
		$min_y  = INF;
		$max_y  = -INF;
		$glyphs = [];
		foreach ( $widths as $advance ) {
			$distance = $cursor + $advance / 2 - $total / 2;
			$cursor  += $advance;
			$theta    = $radius ? $distance / $radius : 0;
			$x        = $radius ? $radius * sin( $theta ) : $distance;
			$y        = $radius ? $radius * ( 1 - cos( $theta ) ) : 0;
			$half_w   = ( abs( cos( $theta ) ) * $advance + abs( sin( $theta ) ) * 1.13 ) / 2;
			$half_h   = ( abs( sin( $theta ) ) * $advance + abs( cos( $theta ) ) * 1.13 ) / 2;
			$min_x    = min( $min_x, $x - $half_w );
			$max_x    = max( $max_x, $x + $half_w );
			$min_y    = min( $min_y, $y - $half_h );
			$max_y    = max( $max_y, $y + $half_h );
			$glyphs[] = [
				'x'     => $x,
				'y'     => $y,
				'angle' => rad2deg( $theta ),
			];
		}
		$size     = max( $minimum, min( $size, $width / ( $max_x - $min_x ), $height / ( $max_y - $min_y ) ) );
		$free_x   = $width - ( $max_x - $min_x ) * $size;
		$offset_x = match ( $align ) {
			'left' => 0,
			'right' => $free_x,
			default => $free_x / 2,
		};
		foreach ( $glyphs as &$glyph ) {
			$glyph['x'] = $offset_x + ( $glyph['x'] - $min_x ) * $size;
			$glyph['y'] = $height / 2 + ( $glyph['y'] - ( $min_y + $max_y ) / 2 ) * $size;
		}
		unset( $glyph );
		return [
			'glyphs'   => $glyphs,
			'fontSize' => $size,
		];
	}
}
