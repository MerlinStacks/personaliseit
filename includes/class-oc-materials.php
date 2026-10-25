<?php
/**
 * Reusable laser material palettes for optional SVG surface layers.
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

class OC_Materials {

	/** Starter palettes; colours are preview approximations, not machine settings. */
	public static function defaults(): array {
		$rows = [
			[ 'black-white', 'Black with White Engraving', '#171717', '#ffffff' ],
			[ 'white-black', 'White with Black Engraving', '#ffffff', '#171717' ],
			[ 'pink-black', 'Pink with Black Engraving', '#edabc4', '#171717' ],
			[ 'black-silver', 'Black with Silver Engraving', '#171717', '#c9c9c9' ],
			[ 'black-acrylic', 'Black Acrylic', '#171717', '#e6e6e6' ],
			[ 'white-acrylic', 'White Acrylic', '#ffffff', '#b8b8b8' ],
		];
		$result = [];
		foreach ( $rows as [ $id, $name, $surface, $engraving ] ) {
			$result[ $id ] = compact( 'id', 'name', 'surface', 'engraving' ) + [ 'active' => true ];
		}
		return $result;
	}

	/** Return the library, including disabled entries for administration. */
	public static function all(): array {
		$rows = get_option( 'oc_laser_materials', null );
		return is_array( $rows ) ? $rows : self::defaults();
	}

	/** Validate a material without trusting posted CSS or labels. */
	public static function sanitise( array $row ): array {
		$string = static fn ( $value ): string => is_scalar( $value ) ? (string) $value : '';
		return [
			'id'        => substr( sanitize_key( $string( $row['id'] ?? '' ) ), 0, 64 ),
			'name'      => substr( sanitize_text_field( $string( $row['name'] ?? '' ) ), 0, 150 ),
			'surface'   => sanitize_hex_color( $string( $row['surface'] ?? '' ) ) ?: '#171717',
			'engraving' => sanitize_hex_color( $string( $row['engraving'] ?? '' ) ) ?: '#ffffff',
			'active'    => ! empty( $row['active'] ),
		];
	}

	/** Restrict material layers to static, sanitized SVG and known library IDs. */
	public static function layer_settings( array $raw ): array {
		$source = $raw['materialSvg'] ?? '';
		$svg = '' === $source ? '' : OC_Cut_Line::sanitize( $source );
		if ( is_wp_error( $svg ) ) {
			throw new RuntimeException( __( 'Upload a valid static SVG silhouette for the material layer.', 'overcustomise' ) );
		}
		$ids = is_array( $raw['material_ids'] ?? null ) ? array_values( array_intersect( array_filter( $raw['material_ids'], 'is_string' ), array_keys( self::all() ) ) ) : [];
		$default = is_string( $raw['default_material_id'] ?? null ) ? $raw['default_material_id'] : '';
		return [
			'materialSvg' => $svg,
			'material_ids' => array_values( array_unique( $ids ) ),
			'default_material_id' => in_array( $default, $ids, true ) ? $default : ( $ids[0] ?? '' ),
		];
	}

	/** Public layer palette. Area engraving finishes are deliberately untouched. */
	public static function for_layer( object $layer ): ?array {
		if ( 'laser_material' !== ( $layer->type ?? '' ) ) {
			return null;
		}
		$config = is_array( $layer->settings ?? null ) ? $layer->settings : json_decode( (string) ( $layer->settings ?? '' ), true );
		$config = is_array( $config ) ? $config : [];
		$library = self::all();
		$materials = [];
		foreach ( is_array( $config['material_ids'] ?? null ) ? $config['material_ids'] : [] as $id ) {
			if ( ! is_string( $id ) ) {
				continue;
			}
			if ( ! empty( $library[ $id ]['active'] ) ) {
				$materials[] = self::sanitise( $library[ $id ] );
			}
		}
		if ( ! $materials ) {
			return null;
		}
		return [
			'materials' => $materials,
			'defaultId' => in_array( $config['default_material_id'] ?? '', array_column( $materials, 'id' ), true ) ? $config['default_material_id'] : $materials[0]['id'],
		];
	}

	/** Snapshot authoritative names/colours so later library edits cannot alter an order. */
	public static function normalise_selection( int $design_id, mixed $submitted ): array|WP_Error {
		if ( ! is_array( $submitted ) ) {
			return new WP_Error( 'invalid_material', __( 'Please choose a valid engraving material.', 'overcustomise' ) );
		}
		$result = [];
		$areas = [];
		foreach ( OC_DB::get_design_print_areas( $design_id ) as $area ) {
			if ( 'engraving' === $area->print_method && ( ! isset( $area->visible ) || $area->visible ) ) {
				$areas[ (int) $area->id ] = $area;
			}
		}
		foreach ( OC_DB::get_design_layers( $design_id ) as $layer ) {
			if ( 'laser_material' !== ( $layer->type ?? '' ) || empty( $areas[ (int) $layer->area_id ] ) || ( isset( $layer->visible ) && ! $layer->visible ) ) {
				continue;
			}
			$config = self::for_layer( $layer );
			if ( ! $config ) {
				return new WP_Error( 'invalid_material', __( 'No materials are available for this material layer. Please contact us.', 'overcustomise' ) );
			}
			try {
				$settings = self::layer_settings( is_array( $layer->settings ) ? $layer->settings : ( json_decode( (string) $layer->settings, true ) ?: [] ) );
				if ( '' === $settings['materialSvg'] ) {
					throw new RuntimeException();
				}
			} catch ( Throwable $error ) {
				return new WP_Error( 'invalid_material_shape', __( 'The material layer needs a valid SVG silhouette. Please contact us.', 'overcustomise' ) );
			}
			$id = $submitted[ $layer->id ] ?? ( ! empty( $layer->locked ) ? $config['defaultId'] : null );
			$rows = array_column( $config['materials'], null, 'id' );
			if ( ! is_string( $id ) || ! isset( $rows[ $id ] ) || ( ! empty( $layer->locked ) && $id !== $config['defaultId'] ) ) {
				return new WP_Error( 'invalid_material', __( 'The selected engraving material is unavailable. Please refresh and choose a material.', 'overcustomise' ) );
			}
			$result[ (int) $layer->id ] = $rows[ $id ] + [ 'areaLabel' => sanitize_text_field( (string) $layer->label ) ];
		}
		if ( array_diff( array_map( 'strval', array_keys( $submitted ) ), array_map( 'strval', array_keys( $result ) ) ) ) {
			return new WP_Error( 'invalid_material', __( 'Invalid engraving material layer. Please refresh and try again.', 'overcustomise' ) );
		}
		return $result;
	}
}
