<?php
/**
 * Shared render geometry conversion helpers.
 *
 * Keep these formulas in sync with src/shared/render-math.js.
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

class OC_Render_Math {

	/** Clamp DPI to the supported render range. */
	public static function normalise_dpi( mixed $value ): int {
		return min( 1200, max( 36, (int) round( (float) ( $value ?: 300 ) ) ) );
	}

	/** Return a physical-unit to canvas-pixel scale for preview/render geometry. */
	public static function unit_px_scale( array|object|null $area_or_bounds ): float {
		$unit = self::value_from( $area_or_bounds, 'unit', 'px' );
		$dpi  = self::normalise_dpi( self::value_from( $area_or_bounds, 'dpi', 300 ) );

		return match ( $unit ) {
			'mm' => $dpi / 25.4,
			'cm' => $dpi / 2.54,
			'in' => (float) $dpi,
			default => 1.0,
		};
	}

	/** Convert one entity into display/mockup pixels, scaling around the area origin. */
	public static function display_entity( array $entity, array|object|null $area = null ): array {
		$source = $area ?? $entity;
		$px     = self::unit_px_scale( $source );
		if ( 1.0 === $px ) {
			return $entity;
		}

		$origin_x = (float) self::value_from( $source, 'x', 0 );
		$origin_y = (float) self::value_from( $source, 'y', 0 );

		$entity['x'] = $origin_x + ( (float) ( $entity['x'] ?? 0 ) - $origin_x ) * $px;
		$entity['y'] = $origin_y + ( (float) ( $entity['y'] ?? 0 ) - $origin_y ) * $px;
		$entity['w'] = (float) ( $entity['w'] ?? 0 ) * $px;
		$entity['h'] = (float) ( $entity['h'] ?? 0 ) * $px;

		return $entity;
	}

	/** Convert a stored font size into display pixels. */
	public static function display_font_size( float $font_size, array|object|null $area_or_bounds, float $canvas_scale = 1.0 ): float {
		return max( 1.0, $font_size ) * self::unit_px_scale( $area_or_bounds ) * $canvas_scale;
	}

	private static function value_from( array|object|null $source, string $key, mixed $default ): mixed {
		if ( is_array( $source ) ) {
			return $source[ $key ] ?? $default;
		}
		if ( is_object( $source ) ) {
			return $source->{$key} ?? $default;
		}
		return $default;
	}
}
