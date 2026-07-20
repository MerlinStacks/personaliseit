<?php
/**
 * Canonical v2 render specification builder.
 *
 * The render spec is the single stored description of what the customer saw:
 * print areas, bounds, layer boxes, settings, and sanitised customer inputs.
 * Frontend/order data and print generation should consume this structure rather
 * than rebuilding layout from partial payloads.
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

class OC_Render_Spec {

	/** Build a full render spec for a v2 design. Browser snapshots are preview-only. */
	public static function build( int $design_id, array $layer_inputs ): array {
		$areas  = OC_DB::get_design_print_areas( $design_id );
		$layers = OC_DB::get_design_layers( $design_id );

		$layers_by_area = [];
		foreach ( $layers as $layer ) {
			if ( isset( $layer->visible ) && ! (bool) $layer->visible ) {
				continue;
			}
			$layers_by_area[ (int) $layer->area_id ][] = $layer;
		}

		$spec_areas = [];
		foreach ( $areas as $area ) {
			if ( isset( $area->visible ) && ! (bool) $area->visible ) {
				continue;
			}
			$area_id = (int) $area->id;
			$spec_areas[ $area_id ] = self::build_area( $area, $layers_by_area[ $area_id ] ?? [], $layer_inputs );
		}

		return [
			'v'        => 1,
			'designId' => $design_id,
			'units'    => 'area_units_v2',
			'areas'    => $spec_areas,
		];
	}

	/** Return a single area from an existing full render spec. */
	public static function area_from_spec( array $render_spec, int $area_id ): array {
		$match = null;
		foreach ( self::print_areas( $render_spec ) as $entry ) {
			if ( (int) $entry['area']->id !== $area_id ) {
				continue;
			}
			if ( null !== $match ) {
				throw new \RuntimeException( 'The stored render specification contains duplicate print area IDs.' );
			}
			$match = $entry['area_data'];
		}

		return is_array( $match ) ? $match : [];
	}

	/** Materialise the stored area set without consulting mutable design rows. */
	public static function print_areas( array $render_spec ): array {
		$areas = is_array( $render_spec['areas'] ?? null ) ? $render_spec['areas'] : null;
		if ( null === $areas ) {
			throw new \RuntimeException( 'The stored render specification has no area collection.' );
		}

		$design_id = absint( $render_spec['designId'] ?? 0 );
		$result    = [];
		$seen      = [];
		foreach ( $areas as $area ) {
			if ( ! is_array( $area ) ) {
				throw new \RuntimeException( 'The stored render specification contains an invalid print area.' );
			}

			$area_object = self::area_object( $area, $design_id );
			if ( isset( $seen[ $area_object->id ] ) ) {
				throw new \RuntimeException( 'The stored render specification contains duplicate print area IDs.' );
			}
			$seen[ $area_object->id ] = true;
			$result[] = [
				'area'      => $area_object,
				'area_data' => self::area_to_print_data( $area ),
			];
		}

		return $result;
	}

	/** Create the legacy-shaped area object expected by production renderers. */
	public static function area_object( array $area, int $design_id = 0 ): object {
		$bounds = is_array( $area['bounds'] ?? null ) ? $area['bounds'] : [];
		$id     = absint( $area['id'] ?? 0 );
		$method = sanitize_key( (string) ( $area['printMethod'] ?? '' ) );
		$width  = (float) ( $bounds['w'] ?? 0 );
		$height = (float) ( $bounds['h'] ?? 0 );
		if ( $id <= 0 || ! in_array( $method, [ 'engraving', 'uv', 'embroidery', 'sublimation' ], true ) || $width <= 0.0 || $height <= 0.0 ) {
			throw new \RuntimeException( 'The stored render specification contains incomplete print-area geometry.' );
		}

		$unit = (string) ( $area['unit'] ?? $bounds['unit'] ?? 'px' );
		if ( ! in_array( $unit, [ 'px', 'mm', 'cm', 'in' ], true ) ) {
			$unit = 'px';
		}

		return (object) [
			'id'                   => $id,
			'design_id'            => $design_id,
			'area_key'             => (string) ( $area['areaKey'] ?? '' ),
			'label'                => (string) ( $area['label'] ?? '' ),
			'print_method'         => $method,
			'engraving_material'   => (string) ( $area['engravingMaterial'] ?? 'silver_metal' ),
			'canvas_unit'          => $unit,
			'canvas_x'             => (float) ( $bounds['x'] ?? 0 ),
			'canvas_y'             => (float) ( $bounds['y'] ?? 0 ),
			'canvas_w'             => $width,
			'canvas_h'             => $height,
			'canvas_dpi'           => OC_Render_Math::normalise_dpi( $bounds['dpi'] ?? 300 ),
			'canvas_rotation'      => (float) ( $bounds['rotation'] ?? 0 ),
			'visible'              => 1,
		];
	}

	/** Build legacy-compatible print data from a full spec area. */
	public static function area_to_print_data( array $area ): array {
		$text_parts   = [];
		$font_id      = 0;
		$color        = '';
		$font_size    = 0;
		$min_font     = 0;
		$max_font     = 0;
		$attachment   = 0;
		$artwork_path = '';
		$layers       = is_array( $area['layers'] ?? null ) ? $area['layers'] : [];

		foreach ( $layers as $layer ) {
			if ( ! is_array( $layer ) ) {
				continue;
			}

			$type     = (string) ( $layer['type'] ?? '' );
			if ( 'mask' === $type ) {
				continue;
			}
			$input    = is_array( $layer['input'] ?? null ) ? $layer['input'] : [];
			$settings = is_array( $layer['settings'] ?? null ) ? $layer['settings'] : [];

			if ( in_array( $type, [ 'text', 'textarea', 'spotify' ], true ) ) {
				$value = trim( (string) ( $input['value'] ?? '' ) );
				if ( '' !== $value ) {
					$text_parts[] = $value;
				}
				if ( ! $font_id && ! empty( $input['fontId'] ) ) {
					$font_id = (int) $input['fontId'];
				}
				if ( ! $font_id && ! empty( $settings['default_font_id'] ) ) {
					$font_id = (int) $settings['default_font_id'];
				}
				if ( '' === $color && ! empty( $input['colorHex'] ) ) {
					$color = (string) $input['colorHex'];
				}
				if ( '' === $color && ! empty( $settings['default_color'] ) ) {
					$color = (string) $settings['default_color'];
				}
				if ( ! $font_size && ! empty( $input['fontSize'] ) ) {
					$font_size = absint( $input['fontSize'] );
				}
				if ( ! $font_size && ! empty( $settings['default_font_size'] ) ) {
					$font_size = absint( $settings['default_font_size'] );
				}
				if ( ! $min_font && ! empty( $settings['min_font_size'] ) ) {
					$min_font = absint( $settings['min_font_size'] );
				}
				if ( ! $max_font && ! empty( $settings['max_font_size'] ) ) {
					$max_font = absint( $settings['max_font_size'] );
				}
			}

			if ( 'lineart' === $type && '' === $color && ! empty( $input['colorHex'] ) ) {
				$color = (string) $input['colorHex'];
			}
			if ( ! $attachment && ! empty( $layer['artworkAttachmentId'] ) ) {
				$attachment = (int) $layer['artworkAttachmentId'];
			}
			if ( '' === $artwork_path && ! empty( $layer['artworkPath'] ) ) {
				$artwork_path = (string) $layer['artworkPath'];
			}
		}

		return [
			'text'                => implode( "\n", $text_parts ),
			'fontId'              => $font_id,
			'fontSize'            => $font_size,
			'color'               => '' !== $color ? $color : '#000000',
			'minFontSize'         => $min_font,
			'maxFontSize'         => $max_font,
			'artworkAttachmentId' => $attachment,
			'artworkPath'         => $artwork_path,
			'layers'              => $layers,
			'bounds'              => is_array( $area['bounds'] ?? null ) ? $area['bounds'] : [],
			'snapshot'            => is_array( $area['snapshot'] ?? null ) ? $area['snapshot'] : [],
			'renderSpecArea'      => $area,
		];
	}

	private static function build_area( object $area, array $layers, array $layer_inputs ): array {
		$spec_layers = [];
		foreach ( $layers as $layer ) {
			$layer_id = (int) $layer->id;
			$input    = is_array( $layer_inputs[ $layer_id ] ?? null ) ? $layer_inputs[ $layer_id ] : [];
			$settings = $layer->settings ? json_decode( (string) $layer->settings, true ) : [];
			if ( ! is_array( $settings ) ) {
				$settings = [];
			}

			$spec_layer = [
				'id'       => $layer_id,
				'type'     => (string) $layer->type,
				'label'    => (string) $layer->label,
				'x'        => (int) $layer->x,
				'y'        => (int) $layer->y,
				'w'        => (int) $layer->w,
				'h'        => (int) $layer->h,
				'settings' => $settings,
				'input'    => $input,
				'locked'   => ! empty( $layer->locked ),
			];
			if ( isset( $layer->rotation ) ) {
				$spec_layer['rotation'] = (float) $layer->rotation;
			}

			if ( ! empty( $input['attachmentId'] ) ) {
				$spec_layer['artworkAttachmentId'] = (int) $input['attachmentId'];
			}
			if ( 'clipart' === (string) $layer->type ) {
				$spec_layer['artworkPath'] = self::resolve_clipart_path(
					(int) ( $input['clipartId'] ?? 0 ),
					is_string( $input['clipartUrl'] ?? null ) ? (string) $input['clipartUrl'] : ''
				);
			}

			$spec_layers[] = $spec_layer;
		}

		$area_key = (string) $area->area_key;

		$spec_area = [
			'id'          => (int) $area->id,
			'areaKey'     => $area_key,
			'label'       => (string) $area->label,
			'printMethod' => (string) $area->print_method,
			'engravingMaterial' => isset( $area->engraving_material ) ? (string) $area->engraving_material : 'silver_metal',
			'unit'        => isset( $area->canvas_unit ) ? (string) $area->canvas_unit : 'px',
			'bounds'      => [
				'x'        => (int) $area->canvas_x,
				'y'        => (int) $area->canvas_y,
				'w'        => (int) $area->canvas_w,
				'h'        => (int) $area->canvas_h,
				'unit'     => isset( $area->canvas_unit ) ? (string) $area->canvas_unit : 'px',
				'dpi'      => OC_Render_Math::normalise_dpi( $area->canvas_dpi ?? 300 ),
				'rotation' => (int) ( $area->canvas_rotation ?? 0 ),
			],
			'layers'      => $spec_layers,
		];
		return $spec_area;
	}

	private static function resolve_clipart_path( int $clipart_id, string $clipart_url ): string {
		global $wpdb;

		if ( $clipart_id > 0 ) {
			$file_path = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT file_path FROM {$wpdb->prefix}oc_clipart WHERE id = %d LIMIT 1",
					$clipart_id
				)
			);
			if ( is_string( $file_path ) && '' !== $file_path ) {
				$real = realpath( $file_path );
				if ( $real && file_exists( $real ) ) {
					return $real;
				}
			}
		}

		if ( '' !== $clipart_url ) {
			$uploads = wp_upload_dir();
			$baseurl = isset( $uploads['baseurl'] ) ? rtrim( (string) $uploads['baseurl'], '/' ) : '';
			$basedir = isset( $uploads['basedir'] ) ? rtrim( (string) $uploads['basedir'], '/\\' ) : '';
			if ( '' !== $baseurl && '' !== $basedir && 0 === strpos( $clipart_url, $baseurl . '/' ) ) {
				$relative = ltrim( substr( $clipart_url, strlen( $baseurl ) ), '/' );
				$candidate = $basedir . '/' . $relative;
				$real = realpath( $candidate );
				if ( $real && file_exists( $real ) ) {
					return $real;
				}
			}
		}

		return '';
	}
}
