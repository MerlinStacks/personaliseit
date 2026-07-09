<?php
/**
 * Embroidery print file generator.
 *
 * Embroidery uses an EPS artwork file so production receives the customer's
 * colours, text, line art and clipart/image placement in one print file.
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

class OC_Print_Embroidery extends OC_Print_Base {

	/** Parsed TrueType font cache for font-independent EPS text outlines. */
	private static array $ttf_outline_cache = [];

	/**
	 * Generate embroidery artwork as an EPS print file.
	 *
	 * @param  \WC_Order $order
	 * @param  int       $item_id
	 * @param  object    $area
	 * @param  array     $area_data  {text, fontId, color, artworkAttachmentId}.
	 * @return array{file_path:string, status:string}
	 * @throws \RuntimeException
	 */
	public static function generate(
		\WC_Order $order,
		int $item_id,
		object $area,
		array $area_data
	): array {
		$output_dir = self::ensure_output_dir( $order->get_id() );
		$eps_path   = self::generate_eps( $output_dir, $order, $item_id, $area, $area_data );

		return [ 'file_path' => $eps_path, 'status' => 'files_ready' ];
	}

	/** Generate a colour EPS containing embroidery artwork and production metadata. */
	private static function generate_eps(
		string $output_dir,
		\WC_Order $order,
		int $item_id,
		object $area,
		array $area_data
	): string {
		[ $w_mm, $h_mm ] = self::area_dimensions_mm( $area );
		$w_pt            = max( 1, (int) ceil( self::mm_to_pt( $w_mm ) ) );
		$h_pt            = max( 1, (int) ceil( self::mm_to_pt( $h_mm ) ) );
		$lines           = [
			'%!PS-Adobe-3.0 EPSF-3.0',
			'%%Creator: OverCustomise',
			'%%Title: Embroidery Artwork - Order #' . self::eps_comment( (string) $order->get_order_number() ),
			'%%BoundingBox: 0 0 ' . $w_pt . ' ' . $h_pt,
			'%%LanguageLevel: 2',
			'%%Pages: 1',
			'%%DocumentProcessColors: Cyan Magenta Yellow Black',
			'%%OCOrder: ' . self::eps_comment( (string) $order->get_order_number() ),
			'%%OCItemId: ' . $item_id,
			'%%OCArea: ' . self::eps_comment( (string) ( $area->label ?? $area->area_key ) ),
			'%%OCThreadColor: ' . strtoupper( self::normalise_hex( (string) ( $area_data['color'] ?? '#000000' ) ) ),
			'%%EndComments',
			'gsave',
		];

		if ( self::has_layer_payload( $area_data ) ) {
			$lines[] = '%%OCExportMode: layer-payload';
			self::append_eps_layers( $lines, $area, $area_data );
		} else {
			$lines[] = '%%OCExportMode: legacy-artwork';
			self::append_eps_legacy_artwork( $lines, $area, $area_data );
		}

		$lines[] = 'grestore';
		$lines[] = 'showpage';
		$lines[] = '%%EOF';

		$output_path = $output_dir . '/' . self::build_versioned_filename( $item_id, (string) $area->area_key, 'eps' );
		if ( false === file_put_contents( $output_path, implode( "\n", $lines ) . "\n" ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			throw new \RuntimeException( __( 'Could not write embroidery EPS file.', 'overcustomise' ) );
		}

		return $output_path;
	}

	/** Append all v2 customiser layers to the EPS output. */
	private static function append_eps_layers( array &$lines, object $area, array $area_data ): void {
		$bounds = is_array( $area_data['bounds'] ?? null ) ? $area_data['bounds'] : [];
		$area_x = isset( $bounds['x'] ) ? (float) $bounds['x'] : (float) ( $area->canvas_x ?? 0 );
		$area_y = isset( $bounds['y'] ) ? (float) $bounds['y'] : (float) ( $area->canvas_y ?? 0 );
		[ $area_w_mm, $area_h_mm ] = self::area_dimensions_mm( $area );
		$bounds_w = max( 1.0, (float) ( $bounds['w'] ?? $area->canvas_w ?? 1 ) );
		$bounds_h = max( 1.0, (float) ( $bounds['h'] ?? $area->canvas_h ?? 1 ) );
		$font_px_to_pt = self::mm_to_pt( $area_h_mm ) / $bounds_h;
		$text_fallbacks = array_values( array_filter( array_map( 'trim', preg_split( '/\R/', (string) ( $area_data['text'] ?? '' ) ) ?: [] ) ) );
		$text_index     = 0;
		$artwork_used   = false;

		foreach ( self::eps_layer_paint_order( $area_data['layers'] ) as $layer ) {
			if ( ! is_array( $layer ) ) {
				continue;
			}

			$type     = (string) ( $layer['type'] ?? '' );
			$input    = is_array( $layer['input'] ?? null ) ? $layer['input'] : [];
			$settings = is_array( $layer['settings'] ?? null ) ? $layer['settings'] : [];
			$layer_x  = (float) ( $layer['x'] ?? 0 );
			$layer_y  = (float) ( $layer['y'] ?? 0 );
			$layer_w  = max( 1.0, (float) ( $layer['w'] ?? 1 ) );
			$layer_h  = max( 1.0, (float) ( $layer['h'] ?? 1 ) );
			$center_x = $layer_x + $layer_w / 2;
			$center_y = $layer_y + $layer_h / 2;
			$rotation = self::layer_rotation( $layer, $input, $settings );

			$center_x_mm = ( ( $center_x - $area_x ) / $bounds_w ) * $area_w_mm;
			$center_y_mm = ( ( $center_y - $area_y ) / $bounds_h ) * $area_h_mm;
			$cx_pt       = self::mm_to_pt( $center_x_mm );
			$cy_pt       = self::mm_to_pt( max( 0.0, $area_h_mm - $center_y_mm ) );
			$w_pt     = self::mm_to_pt( ( $layer_w / $bounds_w ) * $area_w_mm );
			$h_pt     = self::mm_to_pt( ( $layer_h / $bounds_h ) * $area_h_mm );
			$x_pt     = -$w_pt / 2;
			$y_pt     = -$h_pt / 2;

			$lines[] = 'gsave';
			$lines[] = sprintf( '%.4F %.4F translate', $cx_pt, $cy_pt );
			if ( 0.0 !== $rotation ) {
				$lines[] = sprintf( '%.4F rotate', $rotation );
			}

			switch ( $type ) {
				case 'text':
				case 'textarea':
					if ( '' === trim( (string) ( $input['value'] ?? '' ) ) && isset( $text_fallbacks[ $text_index ] ) ) {
						$input['value'] = $text_fallbacks[ $text_index ];
					}
					$text_index++;
					self::append_eps_text( $lines, $input, $settings, $x_pt, $y_pt, $w_pt, $h_pt, true, $font_px_to_pt, 'textarea' === $type ? (string) ( $settings['line_alignment'] ?? 'top' ) : 'center' );
					break;

				case 'lineart':
					self::append_eps_rect( $lines, (string) ( $input['colorHex'] ?? '#000000' ), $x_pt, $y_pt, $w_pt, $h_pt );
					break;

				case 'clipart':
				case 'image':
				case 'clipmask':
					if ( ! self::append_eps_artwork( $lines, $layer, $x_pt, $y_pt, $w_pt, $h_pt, 'clipmask' === $type ? 'cover' : 'contain' ) && ! $artwork_used ) {
						$fallback_path = self::resolve_artwork_path( $area_data );
						if ( $fallback_path ) {
							self::append_eps_image_or_reference( $lines, $fallback_path, $x_pt, $y_pt, $w_pt, $h_pt, 'contain' );
							$artwork_used = true;
						}
					}
					break;

				case 'spotify':
					self::append_eps_text(
						$lines,
						[ 'value' => (string) ( $input['spotifyUri'] ?? $input['value'] ?? '' ), 'colorHex' => '#000000' ],
						[],
						$x_pt,
						$y_pt,
						$w_pt,
						$h_pt,
						true,
						$font_px_to_pt
					);
					break;
			}

			$lines[] = 'grestore';
		}
	}

	/** Return layers in PostScript paint order: bottom layers first, top layers last. */
	private static function eps_layer_paint_order( array $layers ): array {
		return array_reverse( array_values( $layers ) );
	}

	/** Append legacy single-text/single-artwork payloads. */
	private static function append_eps_legacy_artwork( array &$lines, object $area, array $area_data ): void {
		[ $w_mm, $h_mm ] = self::area_dimensions_mm( $area );
		$w_pt            = self::mm_to_pt( $w_mm );
		$h_pt            = self::mm_to_pt( $h_mm );
		$text            = trim( (string) ( $area_data['text'] ?? '' ) );

		if ( '' !== $text ) {
			self::append_eps_text( $lines, [ 'value' => $text, 'colorHex' => (string) ( $area_data['color'] ?? '#000000' ) ], [], 0, 0, $w_pt, $h_pt );
		}

		$path = self::resolve_artwork_path( $area_data );
		if ( $path ) {
			self::append_eps_image_or_reference( $lines, $path, 0, 0, $w_pt, $h_pt, 'contain' );
		}
	}

	/** Append customer text as filled PostScript outlines in the requested colour. */
	private static function append_eps_text(
		array &$lines,
		array $input,
		array $settings,
		float $x_pt,
		float $y_pt,
		float $w_pt,
		float $h_pt,
		bool $centered = false,
		?float $font_px_to_pt = null,
		string $line_alignment = 'center'
	): void {
		$text = trim( (string) ( $input['value'] ?? '' ) );
		if ( '' === $text ) {
			return;
		}
		$text = self::normalise_engraving_text( $text );

		$hex       = (string) ( $input['colorHex'] ?? $settings['default_color'] ?? '#000000' );
		$font_size = ! empty( $input['fontSize'] ) || ! empty( $settings['default_font_size'] )
			? max( 5.0, (float) ( $input['fontSize'] ?? $settings['default_font_size'] ) * ( $font_px_to_pt ?? 72 / self::CANVAS_DPI ) )
			: max( 8.0, $h_pt * 0.38 );
		$font_size = min( $font_size, max( 5.0, $h_pt * 0.8 ) );
		$font_id   = ! empty( $input['fontId'] ) ? (int) $input['fontId'] : (int) ( $settings['default_font_id'] ?? 0 );

		[ $r, $g, $b ] = self::hex_to_unit_rgb( $hex );
		$font          = $font_id ? self::get_font( $font_id ) : null;
		$font_name     = self::eps_font_name_from_row( $font );
		$font_path     = is_object( $font ) ? self::get_font_path( $font ) : null;
		$align         = $centered ? (string) ( $settings['alignment'] ?? 'center' ) : 'left';
		$anchor_x      = $centered ? self::eps_text_align_x( $align, $x_pt, $w_pt ) : $x_pt + 2;
		$baseline_y    = $centered ? self::eps_text_baseline_y( $line_alignment, $y_pt, $h_pt, $font_size ) : $y_pt + max( $font_size, ( $h_pt + $font_size ) / 2 );

		$lines[] = '%%OCTextColor: ' . strtoupper( self::normalise_hex( $hex ) );
		$lines[] = '%%OCTextFont: ' . self::eps_comment( $font_name );
		$lines[] = 'gsave';
		$lines[] = sprintf( '%.4F %.4F %.4F setrgbcolor', $r, $g, $b );
		if ( is_string( $font_path ) && '' !== $font_path && self::append_eps_ttf_text_outline( $lines, $text, $align, $anchor_x, $baseline_y, $font_size, $font_path, $centered ? $x_pt : null, $centered ? $y_pt : null, $centered ? $w_pt : null, $centered ? $h_pt : null ) ) {
			$lines[] = 'grestore';
			return;
		}

		$lines[] = '%%OCTextOutline: charpath';
		$lines[] = '%%OCTextOutlineFallback: font-dependent';
		$lines[] = sprintf( '/Helvetica findfont %.4F scalefont setfont', $font_size );
		if ( 'Helvetica' !== $font_name ) {
			$lines[] = sprintf( '{ /%s findfont %.4F scalefont setfont } stopped { pop } if', self::ps_name_escape( $font_name ), $font_size );
		}
		self::append_eps_text_line( $lines, $text, $align, $anchor_x, $baseline_y );
		$lines[] = 'grestore';
	}

	/** Return x coordinate for aligned text inside a center-origin layer box. */
	private static function eps_text_align_x( string $align, float $x_pt, float $w_pt ): float {
		return match ( $align ) {
			'left'  => $x_pt + 2,
			'right' => $x_pt + $w_pt - 2,
			default => 0.0,
		};
	}

	/** Return baseline y coordinate for text inside a center-origin layer box. */
	private static function eps_text_baseline_y( string $line_alignment, float $y_pt, float $h_pt, float $font_size ): float {
		return match ( $line_alignment ) {
			'top' => $y_pt + $h_pt - $font_size * 0.2,
			'bottom' => $y_pt + $font_size,
			default => $y_pt + ( $h_pt + $font_size ) / 2,
		};
	}

	/** Return the PostScript command needed to align and outline text. */
	private static function eps_text_path_command( string $align ): string {
		return match ( $align ) {
			'right' => ' stringwidth pop neg 0 rmoveto ocText false charpath fill',
			'left'  => ' false charpath fill',
			default => ' stringwidth pop 2 div neg 0 rmoveto ocText false charpath fill',
		};
	}

	/** Append text as filled outlines without non-uniform scaling. */
	private static function append_eps_text_line( array &$lines, string $text, string $align, float $x_pt, float $baseline_pt ): void {
		$escaped = self::ps_escape( $text );

		$lines[] = 'gsave';
		$lines[] = sprintf( '%.4F %.4F translate', $x_pt, $baseline_pt );
		$lines[] = '/ocText (' . $escaped . ') def';
		$lines[] = '0 0 moveto';
		$lines[] = 'ocText' . self::eps_text_path_command( $align );
		$lines[] = 'grestore';
	}

	/** Append font-independent text outlines from a TrueType font file. */
	private static function append_eps_ttf_text_outline( array &$lines, string $text, string $align, float $anchor_x, float $baseline_pt, float $font_size, string $font_path, ?float $box_x_pt = null, ?float $box_y_pt = null, ?float $box_w_pt = null, ?float $box_h_pt = null ): bool {
		$outline = self::ttf_text_outline( $font_path, $text, $font_size );
		if ( ! is_array( $outline ) || empty( $outline['commands'] ) ) {
			return false;
		}

		$width    = (float) ( $outline['width'] ?? 0.0 );
		$origin_y = $baseline_pt;
		$bbox     = is_array( $outline['bbox'] ?? null ) ? $outline['bbox'] : null;
		$fit_scale = 1.0;
		$glyph_h   = $bbox ? (float) $bbox[3] - (float) $bbox[1] : 0.0;
		if ( null !== $box_w_pt && $box_w_pt > 0.0 && $width > $box_w_pt ) {
			$fit_scale = min( $fit_scale, $box_w_pt / $width );
		}
		if ( null !== $box_h_pt && $box_h_pt > 0.0 && $glyph_h > $box_h_pt ) {
			$fit_scale = min( $fit_scale, $box_h_pt / $glyph_h );
		}
		$fit_scale = max( 0.01, min( 1.0, $fit_scale ) );

		if ( null !== $box_y_pt && null !== $box_h_pt && $bbox ) {
			if ( $glyph_h > 0.0 ) {
				$origin_y = $box_y_pt + ( $box_h_pt - $glyph_h * $fit_scale ) / 2 - (float) $bbox[1] * $fit_scale;
			}
		}
		$fitted_width = $width * $fit_scale;
		$origin_x = match ( $align ) {
			'right' => $anchor_x - $fitted_width,
			'left'  => $anchor_x,
			default => $anchor_x - $fitted_width / 2,
		};
		if ( null !== $box_x_pt && null !== $box_w_pt && 'center' === $align ) {
			$origin_x = $box_x_pt + ( $box_w_pt - $fitted_width ) / 2;
		}

		$lines[] = '%%OCTextOutline: glyph-paths';
		$lines[] = '%%OCTextFontFile: ' . self::eps_comment( basename( $font_path ) );
		$lines[] = sprintf( '%%%%OCTextAdvance: %.4F', $fitted_width );
		if ( $fit_scale < 0.9999 ) {
			$lines[] = sprintf( '%%%%OCTextFitScale: %.6F', $fit_scale );
		}
		$lines[] = 'gsave';
		$lines[] = sprintf( '%.4F %.4F translate', $origin_x, $origin_y );
		if ( $fit_scale < 0.9999 ) {
			$lines[] = sprintf( '%.8F %.8F scale', $fit_scale, $fit_scale );
		}
		$lines[] = 'newpath';
		array_push( $lines, ...$outline['commands'] );
		$lines[] = 'fill';
		$lines[] = 'grestore';

		return true;
	}

	/** Build EPS path commands for a UTF-8 string using a TrueType font file. */
	private static function ttf_text_outline( string $font_path, string $text, float $font_size ): ?array {
		$font = self::load_ttf_outline_font( $font_path );
		if ( ! $font || empty( $font['units_per_em'] ) ) {
			return null;
		}

		$scale     = $font_size / (float) $font['units_per_em'];
		$x_offset  = 0.0;
		$commands  = [];
		$bbox      = null;
		$codepoints = self::utf8_codepoints( $text );
		if ( empty( $codepoints ) ) {
			return null;
		}

		foreach ( $codepoints as $codepoint ) {
			$gid      = self::ttf_glyph_id( $font, $codepoint );
			$contours = self::ttf_glyph_contours( $font, $gid );
			foreach ( $contours as $contour ) {
				foreach ( $contour as $point ) {
					[ $px, $py ] = self::ttf_point_to_eps( $point, $x_offset, $scale );
					$bbox = self::merge_bounds( $bbox, [ $px, $py, $px, $py ] );
				}
				self::append_ttf_contour_eps_commands( $commands, $contour, $x_offset, $scale );
			}

			$x_offset += self::ttf_glyph_advance( $font, $gid ) * $scale;
		}

		return [
			'width'    => $x_offset,
			'commands' => $commands,
			'bbox'     => $bbox,
		];
	}

	/** Append one TrueType contour as EPS cubic path commands. */
	private static function append_ttf_contour_eps_commands( array &$commands, array $contour, float $x_offset, float $scale ): void {
		$count = count( $contour );
		if ( $count < 1 ) {
			return;
		}

		$normalised = [];
		for ( $i = 0; $i < $count; $i++ ) {
			$point       = $contour[ $i ];
			$next        = $contour[ ( $i + 1 ) % $count ];
			$normalised[] = $point;
			if ( empty( $point['on'] ) && empty( $next['on'] ) ) {
				$normalised[] = [
					'x'  => ( (float) $point['x'] + (float) $next['x'] ) / 2,
					'y'  => ( (float) $point['y'] + (float) $next['y'] ) / 2,
					'on' => true,
				];
			}
		}

		$start_index = null;
		foreach ( $normalised as $index => $point ) {
			if ( ! empty( $point['on'] ) ) {
				$start_index = $index;
				break;
			}
		}
		if ( null === $start_index ) {
			return;
		}

		$ordered = array_merge( array_slice( $normalised, $start_index ), array_slice( $normalised, 0, $start_index ) );
		$start   = $ordered[0];
		$current = $start;
		[ $sx, $sy ] = self::ttf_point_to_eps( $start, $x_offset, $scale );
		$commands[] = sprintf( '%.4F %.4F moveto', $sx, $sy );

		$ordered_count = count( $ordered );
		for ( $i = 1; $i < $ordered_count; $i++ ) {
			$point = $ordered[ $i ];
			if ( ! empty( $point['on'] ) ) {
				[ $x, $y ] = self::ttf_point_to_eps( $point, $x_offset, $scale );
				$commands[] = sprintf( '%.4F %.4F lineto', $x, $y );
				$current = $point;
				continue;
			}

			$next = $ordered[ $i + 1 ] ?? $start;
			if ( empty( $next['on'] ) ) {
				continue;
			}

			self::append_ttf_quadratic_eps_command( $commands, $current, $point, $next, $x_offset, $scale );
			$current = $next;
			if ( $i + 1 < $ordered_count ) {
				$i++;
			}
		}

		$commands[] = 'closepath';
	}

	/** Append a quadratic TrueType curve as a cubic EPS curve. */
	private static function append_ttf_quadratic_eps_command( array &$commands, array $from, array $control, array $to, float $x_offset, float $scale ): void {
		[ $x0, $y0 ] = self::ttf_point_to_eps( $from, $x_offset, $scale );
		[ $qx, $qy ] = self::ttf_point_to_eps( $control, $x_offset, $scale );
		[ $x1, $y1 ] = self::ttf_point_to_eps( $to, $x_offset, $scale );

		$c1x = $x0 + ( 2 / 3 ) * ( $qx - $x0 );
		$c1y = $y0 + ( 2 / 3 ) * ( $qy - $y0 );
		$c2x = $x1 + ( 2 / 3 ) * ( $qx - $x1 );
		$c2y = $y1 + ( 2 / 3 ) * ( $qy - $y1 );

		$commands[] = sprintf( '%.4F %.4F %.4F %.4F %.4F %.4F curveto', $c1x, $c1y, $c2x, $c2y, $x1, $y1 );
	}

	/** Convert a TrueType font-unit point to local EPS points. */
	private static function ttf_point_to_eps( array $point, float $x_offset, float $scale ): array {
		return [
			$x_offset + (float) $point['x'] * $scale,
			(float) $point['y'] * $scale,
		];
	}

	/** Load the TrueType tables needed to turn text into glyph outlines. */
	private static function load_ttf_outline_font( string $font_path ): ?array {
		$real = realpath( $font_path );
		if ( ! $real || ! file_exists( $real ) ) {
			return null;
		}

		$cache_key = $real . '|' . (string) filemtime( $real ) . '|' . (string) filesize( $real );
		if ( array_key_exists( $cache_key, self::$ttf_outline_cache ) ) {
			return self::$ttf_outline_cache[ $cache_key ];
		}

		$data = file_get_contents( $real ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( ! is_string( $data ) || strlen( $data ) < 12 ) {
			self::$ttf_outline_cache[ $cache_key ] = null;
			return null;
		}

		$base      = 0;
		$signature = substr( $data, 0, 4 );
		if ( 'ttcf' === $signature ) {
			$base      = self::ttf_u32( $data, 12 );
			$signature = substr( $data, $base, 4 );
		}

		if ( ! in_array( $signature, [ "\x00\x01\x00\x00", 'true' ], true ) ) {
			self::$ttf_outline_cache[ $cache_key ] = null;
			return null;
		}

		$num_tables = self::ttf_u16( $data, $base + 4 );
		$tables     = [];
		for ( $i = 0; $i < $num_tables; $i++ ) {
			$record_offset = $base + 12 + $i * 16;
			$tag           = substr( $data, $record_offset, 4 );
			$tables[ $tag ] = [
				'offset' => self::ttf_u32( $data, $record_offset + 8 ),
				'length' => self::ttf_u32( $data, $record_offset + 12 ),
			];
		}

		foreach ( [ 'head', 'hhea', 'hmtx', 'maxp', 'cmap', 'loca', 'glyf' ] as $required ) {
			if ( empty( $tables[ $required ] ) ) {
				self::$ttf_outline_cache[ $cache_key ] = null;
				return null;
			}
		}

		$head_offset        = (int) $tables['head']['offset'];
		$units_per_em       = self::ttf_u16( $data, $head_offset + 18 );
		$index_to_loc_format = self::ttf_i16( $data, $head_offset + 50 );
		$num_glyphs         = self::ttf_u16( $data, (int) $tables['maxp']['offset'] + 4 );
		$num_h_metrics      = self::ttf_u16( $data, (int) $tables['hhea']['offset'] + 34 );
		if ( $units_per_em <= 0 || $num_glyphs <= 0 ) {
			self::$ttf_outline_cache[ $cache_key ] = null;
			return null;
		}

		$glyph_offsets = [];
		$loca_offset   = (int) $tables['loca']['offset'];
		for ( $i = 0; $i <= $num_glyphs; $i++ ) {
			$glyph_offsets[] = 0 === $index_to_loc_format
				? self::ttf_u16( $data, $loca_offset + $i * 2 ) * 2
				: self::ttf_u32( $data, $loca_offset + $i * 4 );
		}

		$cmap = self::ttf_parse_cmap( $data, (int) $tables['cmap']['offset'] );
		if ( ! $cmap ) {
			self::$ttf_outline_cache[ $cache_key ] = null;
			return null;
		}

		$font = [
			'data'           => $data,
			'tables'         => $tables,
			'units_per_em'   => $units_per_em,
			'num_glyphs'     => $num_glyphs,
			'num_h_metrics'  => max( 1, $num_h_metrics ),
			'glyph_offsets'  => $glyph_offsets,
			'cmap'           => $cmap,
		];

		self::$ttf_outline_cache[ $cache_key ] = $font;
		return $font;
	}

	/** Parse the best Unicode cmap available in a TrueType font. */
	private static function ttf_parse_cmap( string $data, int $cmap_offset ): ?array {
		$num_tables = self::ttf_u16( $data, $cmap_offset + 2 );
		$format12   = null;
		$format4    = null;

		for ( $i = 0; $i < $num_tables; $i++ ) {
			$record_offset  = $cmap_offset + 4 + $i * 8;
			$platform_id    = self::ttf_u16( $data, $record_offset );
			$encoding_id    = self::ttf_u16( $data, $record_offset + 2 );
			$subtable_offset = $cmap_offset + self::ttf_u32( $data, $record_offset + 4 );
			$format         = self::ttf_u16( $data, $subtable_offset );

			if ( 12 === $format && ( 0 === $platform_id || 3 === $platform_id ) ) {
				$format12 = self::ttf_parse_cmap_format12( $data, $subtable_offset );
				if ( 3 === $platform_id && 10 === $encoding_id ) {
					return $format12;
				}
			}

			if ( 4 === $format && ( 0 === $platform_id || 3 === $platform_id ) ) {
				$format4 = self::ttf_parse_cmap_format4( $data, $subtable_offset );
			}
		}

		return $format12 ?: $format4;
	}

	/** Parse a cmap format 12 subtable. */
	private static function ttf_parse_cmap_format12( string $data, int $offset ): ?array {
		$num_groups = self::ttf_u32( $data, $offset + 12 );
		$groups     = [];
		for ( $i = 0; $i < $num_groups; $i++ ) {
			$group_offset = $offset + 16 + $i * 12;
			$groups[] = [
				'start' => self::ttf_u32( $data, $group_offset ),
				'end'   => self::ttf_u32( $data, $group_offset + 4 ),
				'gid'   => self::ttf_u32( $data, $group_offset + 8 ),
			];
		}

		return [ 'format' => 12, 'groups' => $groups ];
	}

	/** Parse a cmap format 4 subtable. */
	private static function ttf_parse_cmap_format4( string $data, int $offset ): ?array {
		$seg_count = (int) ( self::ttf_u16( $data, $offset + 6 ) / 2 );
		if ( $seg_count <= 0 ) {
			return null;
		}

		$end_codes_offset        = $offset + 14;
		$start_codes_offset      = $end_codes_offset + $seg_count * 2 + 2;
		$id_deltas_offset        = $start_codes_offset + $seg_count * 2;
		$id_range_offsets_offset = $id_deltas_offset + $seg_count * 2;
		$cmap = [
			'format'                    => 4,
			'seg_count'                 => $seg_count,
			'end_codes'                 => [],
			'start_codes'               => [],
			'id_deltas'                 => [],
			'id_range_offsets'          => [],
			'id_range_offset_positions' => [],
		];

		for ( $i = 0; $i < $seg_count; $i++ ) {
			$cmap['end_codes'][]                 = self::ttf_u16( $data, $end_codes_offset + $i * 2 );
			$cmap['start_codes'][]               = self::ttf_u16( $data, $start_codes_offset + $i * 2 );
			$cmap['id_deltas'][]                 = self::ttf_i16( $data, $id_deltas_offset + $i * 2 );
			$cmap['id_range_offsets'][]          = self::ttf_u16( $data, $id_range_offsets_offset + $i * 2 );
			$cmap['id_range_offset_positions'][] = $id_range_offsets_offset + $i * 2;
		}

		return $cmap;
	}

	/** Return the glyph id for a Unicode codepoint. */
	private static function ttf_glyph_id( array $font, int $codepoint ): int {
		$cmap = $font['cmap'] ?? [];
		if ( 12 === (int) ( $cmap['format'] ?? 0 ) ) {
			foreach ( $cmap['groups'] ?? [] as $group ) {
				if ( $codepoint >= (int) $group['start'] && $codepoint <= (int) $group['end'] ) {
					$gid = (int) $group['gid'] + $codepoint - (int) $group['start'];
					return $gid < (int) $font['num_glyphs'] ? $gid : 0;
				}
			}
			return 0;
		}

		if ( $codepoint > 0xFFFF || 4 !== (int) ( $cmap['format'] ?? 0 ) ) {
			return 0;
		}

		$data = (string) $font['data'];
		for ( $i = 0; $i < (int) $cmap['seg_count']; $i++ ) {
			if ( $codepoint < (int) $cmap['start_codes'][ $i ] || $codepoint > (int) $cmap['end_codes'][ $i ] ) {
				continue;
			}

			$delta        = (int) $cmap['id_deltas'][ $i ];
			$range_offset = (int) $cmap['id_range_offsets'][ $i ];
			if ( 0 === $range_offset ) {
				$gid = ( $codepoint + $delta ) & 0xFFFF;
				return $gid < (int) $font['num_glyphs'] ? $gid : 0;
			}

			$glyph_offset = (int) $cmap['id_range_offset_positions'][ $i ] + $range_offset + 2 * ( $codepoint - (int) $cmap['start_codes'][ $i ] );
			$gid          = self::ttf_u16( $data, $glyph_offset );
			if ( 0 !== $gid ) {
				$gid = ( $gid + $delta ) & 0xFFFF;
			}

			return $gid < (int) $font['num_glyphs'] ? $gid : 0;
		}

		return 0;
	}

	/** Return a glyph advance width in font units. */
	private static function ttf_glyph_advance( array $font, int $gid ): int {
		$data          = (string) $font['data'];
		$hmtx_offset   = (int) $font['tables']['hmtx']['offset'];
		$num_h_metrics = (int) $font['num_h_metrics'];
		$metric_index  = min( max( 0, $gid ), $num_h_metrics - 1 );

		return self::ttf_u16( $data, $hmtx_offset + $metric_index * 4 );
	}

	/** Return glyph contours in font units, resolving simple composite glyphs. */
	private static function ttf_glyph_contours( array $font, int $gid, int $depth = 0 ): array {
		if ( $depth > 8 || $gid < 0 || $gid >= (int) $font['num_glyphs'] ) {
			return [];
		}

		$offsets      = $font['glyph_offsets'];
		$glyph_start  = (int) $offsets[ $gid ];
		$glyph_end    = (int) $offsets[ $gid + 1 ];
		$glyph_length = $glyph_end - $glyph_start;
		if ( $glyph_length <= 0 ) {
			return [];
		}

		$data        = (string) $font['data'];
		$glyph_base  = (int) $font['tables']['glyf']['offset'] + $glyph_start;
		$num_contours = self::ttf_i16( $data, $glyph_base );
		if ( $num_contours >= 0 ) {
			return self::ttf_simple_glyph_contours( $data, $glyph_base, $num_contours );
		}

		return self::ttf_composite_glyph_contours( $font, $glyph_base, $depth );
	}

	/** Decode a simple glyf table outline into contours. */
	private static function ttf_simple_glyph_contours( string $data, int $glyph_base, int $num_contours ): array {
		if ( $num_contours <= 0 ) {
			return [];
		}

		$end_points = [];
		$pos        = $glyph_base + 10;
		for ( $i = 0; $i < $num_contours; $i++ ) {
			$end_points[] = self::ttf_u16( $data, $pos );
			$pos += 2;
		}

		$num_points = (int) end( $end_points ) + 1;
		if ( $num_points <= 0 ) {
			return [];
		}

		$instruction_length = self::ttf_u16( $data, $pos );
		$pos += 2 + $instruction_length;

		$flags = [];
		while ( count( $flags ) < $num_points ) {
			$flag    = self::ttf_u8( $data, $pos );
			$pos++;
			$flags[] = $flag;
			if ( 0 !== ( $flag & 0x08 ) ) {
				$repeat = self::ttf_u8( $data, $pos );
				$pos++;
				for ( $r = 0; $r < $repeat; $r++ ) {
					$flags[] = $flag;
				}
			}
		}

		$points = [];
		$x      = 0;
		for ( $i = 0; $i < $num_points; $i++ ) {
			$flag = $flags[ $i ];
			if ( 0 !== ( $flag & 0x02 ) ) {
				$delta = self::ttf_u8( $data, $pos );
				$pos++;
				$x += 0 !== ( $flag & 0x10 ) ? $delta : -$delta;
			} elseif ( 0 === ( $flag & 0x10 ) ) {
				$x += self::ttf_i16( $data, $pos );
				$pos += 2;
			}
			$points[ $i ] = [ 'x' => $x, 'y' => 0, 'on' => 0 !== ( $flag & 0x01 ) ];
		}

		$y = 0;
		for ( $i = 0; $i < $num_points; $i++ ) {
			$flag = $flags[ $i ];
			if ( 0 !== ( $flag & 0x04 ) ) {
				$delta = self::ttf_u8( $data, $pos );
				$pos++;
				$y += 0 !== ( $flag & 0x20 ) ? $delta : -$delta;
			} elseif ( 0 === ( $flag & 0x20 ) ) {
				$y += self::ttf_i16( $data, $pos );
				$pos += 2;
			}
			$points[ $i ]['y'] = $y;
		}

		$contours = [];
		$start    = 0;
		foreach ( $end_points as $end ) {
			$length = (int) $end - $start + 1;
			if ( $length > 0 ) {
				$contours[] = array_slice( $points, $start, $length );
			}
			$start = (int) $end + 1;
		}

		return $contours;
	}

	/** Decode a composite glyf outline by applying component transforms. */
	private static function ttf_composite_glyph_contours( array $font, int $glyph_base, int $depth ): array {
		$data     = (string) $font['data'];
		$pos      = $glyph_base + 10;
		$contours = [];

		do {
			$flags = self::ttf_u16( $data, $pos );
			$pos += 2;
			$component_gid = self::ttf_u16( $data, $pos );
			$pos += 2;

			$dx = 0.0;
			$dy = 0.0;
			if ( 0 !== ( $flags & 0x0001 ) ) {
				$arg1 = 0 !== ( $flags & 0x0002 ) ? self::ttf_i16( $data, $pos ) : self::ttf_u16( $data, $pos );
				$arg2 = 0 !== ( $flags & 0x0002 ) ? self::ttf_i16( $data, $pos + 2 ) : self::ttf_u16( $data, $pos + 2 );
				$pos += 4;
			} else {
				$arg1 = 0 !== ( $flags & 0x0002 ) ? self::ttf_i8( $data, $pos ) : self::ttf_u8( $data, $pos );
				$arg2 = 0 !== ( $flags & 0x0002 ) ? self::ttf_i8( $data, $pos + 1 ) : self::ttf_u8( $data, $pos + 1 );
				$pos += 2;
			}

			if ( 0 !== ( $flags & 0x0002 ) ) {
				$dx = (float) $arg1;
				$dy = (float) $arg2;
			}

			$a = 1.0;
			$b = 0.0;
			$c = 0.0;
			$d = 1.0;
			if ( 0 !== ( $flags & 0x0008 ) ) {
				$a = $d = self::ttf_f2dot14( $data, $pos );
				$pos += 2;
			} elseif ( 0 !== ( $flags & 0x0040 ) ) {
				$a = self::ttf_f2dot14( $data, $pos );
				$d = self::ttf_f2dot14( $data, $pos + 2 );
				$pos += 4;
			} elseif ( 0 !== ( $flags & 0x0080 ) ) {
				$a = self::ttf_f2dot14( $data, $pos );
				$b = self::ttf_f2dot14( $data, $pos + 2 );
				$c = self::ttf_f2dot14( $data, $pos + 4 );
				$d = self::ttf_f2dot14( $data, $pos + 6 );
				$pos += 8;
			}

			foreach ( self::ttf_glyph_contours( $font, $component_gid, $depth + 1 ) as $component_contour ) {
				$transformed = [];
				foreach ( $component_contour as $point ) {
					$x = (float) $point['x'];
					$y = (float) $point['y'];
					$transformed[] = [
						'x'  => $a * $x + $b * $y + $dx,
						'y'  => $c * $x + $d * $y + $dy,
						'on' => ! empty( $point['on'] ),
					];
				}
				$contours[] = $transformed;
			}
		} while ( 0 !== ( $flags & 0x0020 ) );

		return $contours;
	}

	/** Decode UTF-8 text into Unicode codepoints without requiring mbstring/intl. */
	private static function utf8_codepoints( string $text ): array {
		$bytes      = array_values( unpack( 'C*', $text ) ?: [] );
		$codepoints = [];
		$count      = count( $bytes );
		for ( $i = 0; $i < $count; $i++ ) {
			$b = $bytes[ $i ];
			if ( $b < 0x80 ) {
				$codepoints[] = $b;
				continue;
			}

			if ( ( $b & 0xE0 ) === 0xC0 && $i + 1 < $count ) {
				$codepoints[] = ( ( $b & 0x1F ) << 6 ) | ( $bytes[ ++$i ] & 0x3F );
				continue;
			}

			if ( ( $b & 0xF0 ) === 0xE0 && $i + 2 < $count ) {
				$codepoints[] = ( ( $b & 0x0F ) << 12 ) | ( ( $bytes[ ++$i ] & 0x3F ) << 6 ) | ( $bytes[ ++$i ] & 0x3F );
				continue;
			}

			if ( ( $b & 0xF8 ) === 0xF0 && $i + 3 < $count ) {
				$codepoints[] = ( ( $b & 0x07 ) << 18 ) | ( ( $bytes[ ++$i ] & 0x3F ) << 12 ) | ( ( $bytes[ ++$i ] & 0x3F ) << 6 ) | ( $bytes[ ++$i ] & 0x3F );
			}
		}

		return $codepoints;
	}

	private static function ttf_u8( string $data, int $offset ): int {
		return isset( $data[ $offset ] ) ? ord( $data[ $offset ] ) : 0;
	}

	private static function ttf_i8( string $data, int $offset ): int {
		$value = self::ttf_u8( $data, $offset );
		return $value >= 0x80 ? $value - 0x100 : $value;
	}

	private static function ttf_u16( string $data, int $offset ): int {
		if ( $offset < 0 || $offset + 2 > strlen( $data ) ) {
			return 0;
		}

		$value = unpack( 'n', substr( $data, $offset, 2 ) );
		return is_array( $value ) ? (int) $value[1] : 0;
	}

	private static function ttf_i16( string $data, int $offset ): int {
		$value = self::ttf_u16( $data, $offset );
		return $value >= 0x8000 ? $value - 0x10000 : $value;
	}

	private static function ttf_u32( string $data, int $offset ): int {
		if ( $offset < 0 || $offset + 4 > strlen( $data ) ) {
			return 0;
		}

		$value = unpack( 'N', substr( $data, $offset, 4 ) );
		return is_array( $value ) ? (int) $value[1] : 0;
	}

	private static function ttf_f2dot14( string $data, int $offset ): float {
		return self::ttf_i16( $data, $offset ) / 16384;
	}

	/** Return the selected font family name from a font DB row. */
	private static function eps_font_name_from_row( ?object $font ): string {
		$name = is_object( $font ) ? trim( (string) ( $font->name ?? '' ) ) : '';

		return '' !== $name ? $name : 'Helvetica';
	}

	/** Escape a PostScript name token. */
	private static function ps_name_escape( string $value ): string {
		$value = preg_replace( '/\s+/', '-', trim( $value ) );
		$value = is_string( $value ) && '' !== $value ? $value : 'Helvetica';

		return preg_replace( '/[^A-Za-z0-9_.-]/', '', $value ) ?: 'Helvetica';
	}

	/** Append a filled vector rectangle for simple line-art colour blocks. */
	private static function append_eps_rect( array &$lines, string $hex, float $x_pt, float $y_pt, float $w_pt, float $h_pt ): void {
		[ $r, $g, $b ] = self::hex_to_unit_rgb( $hex );
		$lines[] = '%%OCLineartColor: ' . strtoupper( self::normalise_hex( $hex ) );
		$lines[] = 'gsave';
		$lines[] = sprintf( '%.4F %.4F %.4F setrgbcolor', $r, $g, $b );
		$lines[] = sprintf( 'newpath %.4F %.4F moveto %.4F 0 rlineto 0 %.4F rlineto %.4F 0 rlineto closepath fill', $x_pt, $y_pt, $w_pt, $h_pt, -$w_pt );
		$lines[] = 'grestore';
	}

	/** Append layer artwork, embedding raster files and preserving vector references. */
	private static function append_eps_artwork( array &$lines, array $layer, float $x_pt, float $y_pt, float $w_pt, float $h_pt, string $fit = 'contain' ): bool {
		$path = self::resolve_eps_layer_artwork_path( $layer );
		if ( ! $path ) {
			return false;
		}

		$temp_path = null;
		$input     = is_array( $layer['input'] ?? null ) ? $layer['input'] : [];
		if ( 'clipart' === (string) ( $layer['type'] ?? '' ) && ! empty( $input['clipartRecolourable'] ) && 'svg' === strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) ) {
			$hex = sanitize_hex_color( (string) ( $input['colorHex'] ?? '' ) );
			if ( $hex ) {
				$temp_path = self::build_coloured_svg( $path, $hex );
				if ( is_string( $temp_path ) && '' !== $temp_path ) {
					$path = $temp_path;
				}
			}
		}

		self::append_eps_image_or_reference( $lines, $path, $x_pt, $y_pt, $w_pt, $h_pt, $fit );

		if ( is_string( $temp_path ) && '' !== $temp_path && file_exists( $temp_path ) ) {
			@unlink( $temp_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		return true;
	}

	/** Embed supported raster artwork or include vector source as EPS comments. */
	private static function append_eps_image_or_reference( array &$lines, string $path, float $x_pt, float $y_pt, float $w_pt, float $h_pt, string $fit = 'contain' ): void {
		$lines[] = '%%OCArtworkFile: ' . self::eps_comment( basename( $path ) );
		$ext     = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );

		if ( 'svg' === $ext ) {
			if ( self::append_eps_external_svg_vector( $lines, $path, $x_pt, $y_pt, $w_pt, $h_pt, $fit ) ) {
				return;
			}

			if ( self::append_eps_svg_vector( $lines, $path, $x_pt, $y_pt, $w_pt, $h_pt, $fit ) ) {
				return;
			}

			$image = self::open_svg_resource( $path, $w_pt, $h_pt );
			if ( $image ) {
				self::append_eps_raster_image( $lines, $image, $x_pt, $y_pt, $w_pt, $h_pt, $fit );
				imagedestroy( $image );
				return;
			}

			$raw = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$lines[] = '%%BeginOCEmbeddedSVG: ' . self::eps_comment( basename( $path ) );
			if ( is_string( $raw ) ) {
				foreach ( preg_split( '/\R/', $raw ) as $svg_line ) {
					$lines[] = '%%OCSVG: ' . self::eps_comment( (string) $svg_line );
				}
			}
			$lines[] = '%%EndOCEmbeddedSVG';
			self::append_eps_artwork_box( $lines, $x_pt, $y_pt, $w_pt, $h_pt, basename( $path ) );
			return;
		}

		$image = self::open_raster_resource( $path );
		if ( ! $image ) {
			self::append_eps_artwork_box( $lines, $x_pt, $y_pt, $w_pt, $h_pt, basename( $path ) );
			return;
		}

		self::append_eps_raster_image( $lines, $image, $x_pt, $y_pt, $w_pt, $h_pt, $fit );
		imagedestroy( $image );
	}

	/** Append a full-colour raster image using PostScript colorimage. */
	private static function append_eps_raster_image( array &$lines, $image, float $x_pt, float $y_pt, float $w_pt, float $h_pt, string $fit = 'contain' ): void {
		$src_w = imagesx( $image );
		$src_h = imagesy( $image );
		if ( $src_w < 1 || $src_h < 1 ) {
			return;
		}

		$max_px = 900;
		$scale  = min( 1.0, $max_px / max( $src_w, $src_h ) );
		$out_w  = max( 1, (int) round( $src_w * $scale ) );
		$out_h  = max( 1, (int) round( $src_h * $scale ) );
		$draw   = imagecreatetruecolor( $out_w, $out_h );
		imagealphablending( $draw, false );
		imagesavealpha( $draw, true );
		$transparent = imagecolorallocatealpha( $draw, 0, 0, 0, 127 );
		imagefilledrectangle( $draw, 0, 0, $out_w, $out_h, $transparent );
		imagecopyresampled( $draw, $image, 0, 0, 0, 0, $out_w, $out_h, $src_w, $src_h );

		[ $draw_x, $draw_y, $draw_w, $draw_h ] = self::fit_eps_box( (float) $src_w, (float) $src_h, $x_pt, $y_pt, $w_pt, $h_pt, $fit );
		if ( self::gd_image_has_transparency( $draw ) ) {
			if ( max( $out_w, $out_h ) > 260 ) {
				$alpha_scale = 260 / max( $out_w, $out_h );
				$small_w     = max( 1, (int) round( $out_w * $alpha_scale ) );
				$small_h     = max( 1, (int) round( $out_h * $alpha_scale ) );
				$small       = imagecreatetruecolor( $small_w, $small_h );
				imagealphablending( $small, false );
				imagesavealpha( $small, true );
				$transparent = imagecolorallocatealpha( $small, 0, 0, 0, 127 );
				imagefilledrectangle( $small, 0, 0, $small_w, $small_h, $transparent );
				imagecopyresampled( $small, $draw, 0, 0, 0, 0, $small_w, $small_h, $out_w, $out_h );
				imagedestroy( $draw );
				$draw = $small;
			}
			self::append_eps_alpha_raster_rects( $lines, $draw, $draw_x, $draw_y, $draw_w, $draw_h );
			imagedestroy( $draw );
			return;
		}

		$lines[] = 'gsave';
		$lines[] = sprintf( '%.4F %.4F translate', $draw_x, $draw_y );
		$lines[] = sprintf( '%.4F %.4F scale', $draw_w, $draw_h );
		$lines[] = '/picstr ' . ( $out_w * 3 ) . ' string def';
		$lines[] = sprintf( '%d %d 8 [%d 0 0 -%d 0 %d]', $out_w, $out_h, $out_w, $out_h, $out_h );
		$lines[] = '{ currentfile picstr readhexstring pop } false 3 colorimage';

		for ( $y = 0; $y < $out_h; $y++ ) {
			$row = '';
			for ( $x = 0; $x < $out_w; $x++ ) {
				$rgb = imagecolorat( $draw, $x, $y );
				$row .= sprintf( '%02X%02X%02X', ( $rgb >> 16 ) & 0xFF, ( $rgb >> 8 ) & 0xFF, $rgb & 0xFF );
			}
			$lines[] = $row;
		}

		$lines[] = 'grestore';
		imagedestroy( $draw );
	}

	/** Return true if a GD image contains any transparent pixels. */
	private static function gd_image_has_transparency( $image ): bool {
		$transparent_index = imagecolortransparent( $image );
		if ( $transparent_index >= 0 ) {
			return true;
		}

		$w = imagesx( $image );
		$h = imagesy( $image );
		for ( $y = 0; $y < $h; $y++ ) {
			for ( $x = 0; $x < $w; $x++ ) {
				$rgba  = imagecolorat( $image, $x, $y );
				$alpha = ( $rgba >> 24 ) & 0x7F;
				if ( $alpha > 0 ) {
					return true;
				}
			}
		}

		return false;
	}

	/** Preserve transparent raster shapes by drawing visible pixel runs instead of white-matting them. */
	private static function append_eps_alpha_raster_rects( array &$lines, $image, float $x_pt, float $y_pt, float $w_pt, float $h_pt ): void {
		$img_w = imagesx( $image );
		$img_h = imagesy( $image );
		if ( $img_w < 1 || $img_h < 1 ) {
			return;
		}

		$lines[] = '%%OCTransparentRaster: vector-runs';
		$lines[] = 'gsave';
		$lines[] = sprintf( '%.4F %.4F translate', $x_pt, $y_pt );
		$lines[] = sprintf( '%.8F %.8F scale', $w_pt / $img_w, $h_pt / $img_h );

		for ( $y = 0; $y < $img_h; $y++ ) {
			$x = 0;
			while ( $x < $img_w ) {
				$rgba  = imagecolorat( $image, $x, $y );
				$alpha = ( $rgba >> 24 ) & 0x7F;
				if ( $alpha >= 120 ) {
					$x++;
					continue;
				}

				$rgb = $rgba & 0xFFFFFF;
				$run = 1;
				while ( $x + $run < $img_w ) {
					$next       = imagecolorat( $image, $x + $run, $y );
					$next_alpha = ( $next >> 24 ) & 0x7F;
					if ( $next_alpha >= 120 || ( $next & 0xFFFFFF ) !== $rgb ) {
						break;
					}
					$run++;
				}

				$r = ( $rgb >> 16 ) & 0xFF;
				$g = ( $rgb >> 8 ) & 0xFF;
				$b = $rgb & 0xFF;
				$lines[] = sprintf( '%.4F %.4F %.4F setrgbcolor', $r / 255, $g / 255, $b / 255 );
				$lines[] = sprintf( '%d %d %d 1 rectfill', $x, $img_h - $y - 1, $run );
				$x += $run;
			}
		}

		$lines[] = 'grestore';
	}

	/** Convert SVG with an external vector renderer when available, then place the resulting EPS into this EPS. */
	private static function append_eps_external_svg_vector( array &$lines, string $path, float $x_pt, float $y_pt, float $w_pt, float $h_pt, string $fit = 'contain' ): bool {
		$converted = self::convert_svg_to_eps( $path );
		if ( ! $converted ) {
			return false;
		}

		$raw = file_get_contents( $converted ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		@unlink( $converted ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
			return false;
		}

		$bbox = self::eps_bounding_box( $raw );
		if ( ! $bbox ) {
			return false;
		}

		[ $bb_left, $bb_bottom, $bb_right, $bb_top ] = $bbox;
		$src_w = $bb_right - $bb_left;
		$src_h = $bb_top - $bb_bottom;
		if ( $src_w <= 0.0 || $src_h <= 0.0 ) {
			return false;
		}

		$body = self::eps_embedded_body( $raw );
		if ( empty( $body ) ) {
			return false;
		}

		[ $draw_x, $draw_y, $draw_w, $draw_h ] = self::fit_eps_box( $src_w, $src_h, $x_pt, $y_pt, $w_pt, $h_pt, $fit );
		$lines[] = '%%OCSVGVectorizer: external-eps';
		$lines[] = '%%BeginDocument: ' . self::eps_comment( basename( $path ) );
		$lines[] = 'gsave';
		$lines[] = sprintf( '%.4F %.4F translate', $draw_x, $draw_y );
		$lines[] = sprintf( '%.8F %.8F scale', $draw_w / $src_w, $draw_h / $src_h );
		$lines[] = sprintf( '%.4F %.4F translate', -$bb_left, -$bb_bottom );
		array_push( $lines, ...$body );
		$lines[] = 'grestore';
		$lines[] = '%%EndDocument';

		return true;
	}

	/** Convert an SVG to an EPS temp file with installed vector tooling. */
	private static function convert_svg_to_eps( string $path ): ?string {
		$commands = [];
		$inkscape = self::find_executable( 'inkscape' );
		if ( $inkscape ) {
			$commands[] = static fn ( string $out ): array => [ $inkscape, $path, '--export-type=eps', '--export-filename=' . $out ];
			$commands[] = static fn ( string $out ): array => [ $inkscape, '--export-type=eps', '--export-filename=' . $out, $path ];
			$commands[] = static fn ( string $out ): array => [ $inkscape, '-z', '-E', $out, $path ];
		}

		$rsvg_convert = self::find_executable( 'rsvg-convert' );
		if ( $rsvg_convert ) {
			$commands[] = static fn ( string $out ): array => [ $rsvg_convert, '-f', 'eps', '-o', $out, $path ];
			$commands[] = static fn ( string $out ): array => [ $rsvg_convert, '-f', 'ps', '-o', $out, $path ];
		}

		foreach ( $commands as $build_command ) {
			$out = self::temp_eps_path( 'oc-eps-svg-' . wp_generate_uuid4() );
			if ( ! $out ) {
				continue;
			}

			if ( self::run_process( $build_command( $out ) ) && file_exists( $out ) && filesize( $out ) > 0 ) {
				return $out;
			}

			@unlink( $out ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		return null;
	}

	/** Return a writable temporary EPS path that keeps the .eps extension for converter output. */
	private static function temp_eps_path( string $prefix ): ?string {
		$temp = self::temp_path( $prefix . '.eps' );
		if ( ! is_string( $temp ) || '' === $temp ) {
			return null;
		}

		if ( 'eps' === strtolower( pathinfo( $temp, PATHINFO_EXTENSION ) ) ) {
			return $temp;
		}

		$eps_temp = $temp . '.eps';
		if ( file_exists( $eps_temp ) ) {
			@unlink( $eps_temp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		if ( ! @rename( $temp, $eps_temp ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			@unlink( $temp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return null;
		}

		return $eps_temp;
	}

	/** Locate an executable without invoking a shell. */
	private static function find_executable( string $name ): ?string {
		if ( ! preg_match( '/^[A-Za-z0-9._+-]+$/', $name ) ) {
			return null;
		}

		$path = getenv( 'PATH' );
		$dirs = explode( PATH_SEPARATOR, is_string( $path ) && '' !== $path ? $path : '/usr/local/bin:/usr/bin:/bin' );
		foreach ( $dirs as $dir ) {
			$candidate = rtrim( $dir, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR . $name;
			if ( is_file( $candidate ) && is_executable( $candidate ) ) {
				return $candidate;
			}
		}

		return null;
	}

	/** Run a converter command without shell expansion. */
	private static function run_process( array $command ): bool {
		$disabled = array_map( 'trim', explode( ',', (string) ini_get( 'disable_functions' ) ) );
		if ( ! function_exists( 'proc_open' ) || in_array( 'proc_open', $disabled, true ) ) {
			return false;
		}

		$pipes   = [];
		$process = @proc_open( // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.DiscouragedPHPFunctions.system_calls_proc_open
			$command,
			[
				0 => [ 'file', '/dev/null', 'r' ],
				1 => [ 'file', '/dev/null', 'w' ],
				2 => [ 'file', '/dev/null', 'w' ],
			],
			$pipes
		);
		if ( ! is_resource( $process ) ) {
			return false;
		}

		return 0 === proc_close( $process ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_proc_close
	}

	/** Parse an EPS BoundingBox comment. */
	private static function eps_bounding_box( string $eps ): ?array {
		if ( preg_match( '/^%%BoundingBox:\s*([-+\d.]+)\s+([-+\d.]+)\s+([-+\d.]+)\s+([-+\d.]+)/mi', $eps, $matches ) ) {
			return [ (float) $matches[1], (float) $matches[2], (float) $matches[3], (float) $matches[4] ];
		}

		if ( preg_match( '/^%%HiResBoundingBox:\s*([-+\d.]+)\s+([-+\d.]+)\s+([-+\d.]+)\s+([-+\d.]+)/mi', $eps, $matches ) ) {
			return [ (float) $matches[1], (float) $matches[2], (float) $matches[3], (float) $matches[4] ];
		}

		return null;
	}

	/** Strip wrapper comments and page commands before embedding converter output inside another EPS. */
	private static function eps_embedded_body( string $eps ): array {
		$body = [];
		foreach ( preg_split( '/\R/', $eps ) ?: [] as $line ) {
			$trimmed = trim( (string) $line );
			if ( '' === $trimmed || str_starts_with( $trimmed, '%!' ) || str_starts_with( $trimmed, '%%' ) ) {
				continue;
			}

			if ( preg_match( '/^(showpage|grestoreall|quit)\b/i', $trimmed ) ) {
				continue;
			}

			$body[] = $line;
		}

		return $body;
	}

	/** Resolve selected clipart paths from the trusted clipart DB row before falling back to upload artwork rules. */
	private static function resolve_eps_layer_artwork_path( array $layer ): ?string {
		$path = self::resolve_artwork_path( $layer );
		if ( $path ) {
			return $path;
		}

		if ( 'clipart' !== (string) ( $layer['type'] ?? '' ) ) {
			return null;
		}

		$input      = is_array( $layer['input'] ?? null ) ? $layer['input'] : [];
		$clipart_id = absint( $input['clipartId'] ?? 0 );
		if ( $clipart_id <= 0 ) {
			return null;
		}

		global $wpdb;
		$file_path = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT file_path FROM {$wpdb->prefix}oc_clipart WHERE id = %d LIMIT 1",
				$clipart_id
			)
		);
		if ( ! is_string( $file_path ) || '' === $file_path ) {
			return null;
		}

		$real = realpath( $file_path );
		return $real && file_exists( $real ) ? $real : null;
	}

	/** Render common SVG clipart directly as EPS vectors when no SVG rasterizer is available. */
	private static function append_eps_svg_vector( array &$lines, string $path, float $x_pt, float $y_pt, float $w_pt, float $h_pt, string $fit = 'contain' ): bool {
		$raw = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( ! is_string( $raw ) || '' === $raw ) {
			return false;
		}

		$dom      = new \DOMDocument();
		$previous = libxml_use_internal_errors( true );
		$loaded   = $dom->loadXML( $raw, LIBXML_NONET | LIBXML_NOCDATA );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		if ( ! $loaded || ! $dom->documentElement || 'svg' !== strtolower( $dom->documentElement->localName ) ) {
			return false;
		}

		[ $vb_x, $vb_y, $vb_w, $vb_h ] = self::svg_view_box( $dom->documentElement );
		if ( $vb_w <= 0.0 || $vb_h <= 0.0 ) {
			return false;
		}

		[ $draw_x, $draw_y, $draw_w, $draw_h ] = self::fit_eps_box( $vb_w, $vb_h, $x_pt, $y_pt, $w_pt, $h_pt, $fit );

		$before  = count( $lines );
		$lines[] = 'gsave';
		$lines[] = sprintf( '%.4F %.4F translate', $draw_x, $draw_y + $draw_h );
		$lines[] = sprintf( '%.8F %.8F scale', $draw_w / $vb_w, -$draw_h / $vb_h );
		$lines[] = sprintf( '%.4F %.4F translate', -$vb_x, -$vb_y );
		$context = [
			'css' => self::svg_css_rules( $dom ),
			'ids' => self::svg_id_map( $dom ),
		];
		self::append_svg_element_eps( $lines, $dom->documentElement, [], $context );
		$lines[] = 'grestore';

		if ( count( $lines ) <= $before + 4 ) {
			array_splice( $lines, $before );
			return false;
		}

		return true;
	}

	private static function svg_view_box( \DOMElement $svg ): array {
		$view_box = trim( $svg->getAttribute( 'viewBox' ) );
		if ( '' !== $view_box ) {
			$parts = preg_split( '/[\s,]+/', $view_box );
			if ( is_array( $parts ) && count( $parts ) >= 4 ) {
				return [ (float) $parts[0], (float) $parts[1], (float) $parts[2], (float) $parts[3] ];
			}
		}

		$w = self::svg_number( $svg->getAttribute( 'width' ) );
		$h = self::svg_number( $svg->getAttribute( 'height' ) );
		return [ 0.0, 0.0, max( 1.0, $w ), max( 1.0, $h ) ];
	}

	private static function append_svg_element_eps( array &$lines, \DOMElement $element, array $style, array $context = [] ): void {
		$style = self::svg_style_for_element( $element, $style, $context['css'] ?? [] );
		$name  = strtolower( $element->localName );
		if ( in_array( $name, [ 'defs', 'style', 'metadata', 'title', 'desc' ], true ) || ( 'symbol' === $name && empty( $context['from_use'] ) ) ) {
			return;
		}
		$has_transform = $element->hasAttribute( 'transform' ) && '' !== trim( $element->getAttribute( 'transform' ) );

		if ( $has_transform ) {
			$lines[] = 'gsave';
			self::append_svg_transform_eps( $lines, $element->getAttribute( 'transform' ) );
		}

			switch ( $name ) {
			case 'use':
				self::append_svg_use_eps( $lines, $element, $style, $context );
				break;
			case 'path':
				self::append_svg_path_eps( $lines, $element->getAttribute( 'd' ), $style );
				break;
			case 'rect':
				self::append_svg_rect_eps( $lines, $element, $style );
				break;
			case 'circle':
				self::append_svg_circle_eps( $lines, $element, $style );
				break;
			case 'ellipse':
				self::append_svg_ellipse_eps( $lines, $element, $style );
				break;
			case 'line':
				self::append_svg_polyline_eps( $lines, [
					[ self::svg_number( $element->getAttribute( 'x1' ) ), self::svg_number( $element->getAttribute( 'y1' ) ) ],
					[ self::svg_number( $element->getAttribute( 'x2' ) ), self::svg_number( $element->getAttribute( 'y2' ) ) ],
				], $style, false );
				break;
			case 'polyline':
			case 'polygon':
				self::append_svg_polyline_eps( $lines, self::svg_points( $element->getAttribute( 'points' ) ), $style, 'polygon' === $name );
				break;
		}

		foreach ( $element->childNodes as $child ) {
			if ( $child instanceof \DOMElement ) {
				self::append_svg_element_eps( $lines, $child, $style, $context );
			}
		}

		if ( $has_transform ) {
			$lines[] = 'grestore';
		}
	}

	private static function append_svg_use_eps( array &$lines, \DOMElement $element, array $style, array $context ): void {
		$href = $element->getAttribute( 'href' );
		if ( '' === $href ) {
			$href = $element->getAttributeNS( 'http://www.w3.org/1999/xlink', 'href' );
		}
		if ( ! str_starts_with( $href, '#' ) ) {
			return;
		}

		$id  = substr( $href, 1 );
		$ids = is_array( $context['ids'] ?? null ) ? $context['ids'] : [];
		if ( ! isset( $ids[ $id ] ) || ! $ids[ $id ] instanceof \DOMElement ) {
			return;
		}

		$lines[] = 'gsave';
		$x = self::svg_number( $element->getAttribute( 'x' ) );
		$y = self::svg_number( $element->getAttribute( 'y' ) );
		if ( 0.0 !== $x || 0.0 !== $y ) {
			$lines[] = sprintf( '%.4F %.4F translate', $x, $y );
		}
		$use_context = $context;
		$use_context['from_use'] = true;
		self::append_svg_element_eps( $lines, $ids[ $id ], $style, $use_context );
		$lines[] = 'grestore';
	}

	private static function append_svg_transform_eps( array &$lines, string $transform ): void {
		preg_match_all( '/(matrix|translate|scale|rotate)\s*\(([^)]*)\)/i', $transform, $matches, PREG_SET_ORDER );
		foreach ( $matches as $match ) {
			$name   = strtolower( (string) $match[1] );
			$values = preg_split( '/[\s,]+/', trim( (string) $match[2] ) );
			$values = array_values( array_filter( array_map( 'trim', is_array( $values ) ? $values : [] ), static fn ( string $value ): bool => '' !== $value ) );
			$nums   = array_map( 'floatval', $values );

			switch ( $name ) {
				case 'matrix':
					if ( count( $nums ) >= 6 ) {
						$lines[] = sprintf( '[%.8F %.8F %.8F %.8F %.8F %.8F] concat', $nums[0], $nums[1], $nums[2], $nums[3], $nums[4], $nums[5] );
					}
					break;
				case 'translate':
					$lines[] = sprintf( '%.4F %.4F translate', $nums[0] ?? 0.0, $nums[1] ?? 0.0 );
					break;
				case 'scale':
					$sx = $nums[0] ?? 1.0;
					$sy = $nums[1] ?? $sx;
					$lines[] = sprintf( '%.8F %.8F scale', $sx, $sy );
					break;
				case 'rotate':
					if ( count( $nums ) >= 3 ) {
						$lines[] = sprintf( '%.4F %.4F translate %.4F rotate %.4F %.4F translate', $nums[1], $nums[2], $nums[0], -$nums[1], -$nums[2] );
					} else {
						$lines[] = sprintf( '%.4F rotate', $nums[0] ?? 0.0 );
					}
					break;
			}
		}
	}

	private static function svg_style_for_element( \DOMElement $element, array $parent, array $css = [] ): array {
		$style = $parent + [ 'fill' => '#000000', 'stroke' => 'none', 'stroke-width' => '1' ];
		$tag   = strtolower( $element->localName );
		foreach ( [ $tag, '*' ] as $selector ) {
			if ( isset( $css[ $selector ] ) ) {
				$style = array_merge( $style, $css[ $selector ] );
			}
		}
		if ( $element->hasAttribute( 'class' ) ) {
			foreach ( preg_split( '/\s+/', trim( $element->getAttribute( 'class' ) ) ) ?: [] as $class ) {
				$selector = '.' . $class;
				if ( isset( $css[ $selector ] ) ) {
					$style = array_merge( $style, $css[ $selector ] );
				}
				$selector = $tag . '.' . $class;
				if ( isset( $css[ $selector ] ) ) {
					$style = array_merge( $style, $css[ $selector ] );
				}
			}
		}
		if ( $element->hasAttribute( 'id' ) ) {
			$selector = '#' . $element->getAttribute( 'id' );
			if ( isset( $css[ $selector ] ) ) {
				$style = array_merge( $style, $css[ $selector ] );
			}
		}
		if ( $element->hasAttribute( 'style' ) ) {
			foreach ( explode( ';', $element->getAttribute( 'style' ) ) as $rule ) {
				if ( str_contains( $rule, ':' ) ) {
					[ $key, $value ] = array_map( 'trim', explode( ':', $rule, 2 ) );
					$style[ strtolower( $key ) ] = $value;
				}
			}
		}

		foreach ( [ 'fill', 'stroke', 'stroke-width', 'opacity', 'fill-opacity', 'stroke-opacity' ] as $attr ) {
			if ( $element->hasAttribute( $attr ) ) {
				$style[ $attr ] = $element->getAttribute( $attr );
			}
		}

		return $style;
	}

	private static function svg_css_rules( \DOMDocument $dom ): array {
		$rules = [];
		foreach ( $dom->getElementsByTagName( 'style' ) as $style_node ) {
			$css = (string) $style_node->textContent;
			$css = (string) preg_replace( '!/\*.*?\*/!s', '', $css );
			preg_match_all( '/([^{}]+)\{([^{}]+)\}/', $css, $matches, PREG_SET_ORDER );
			foreach ( $matches as $match ) {
				$declarations = self::svg_css_declarations( (string) $match[2] );
				if ( empty( $declarations ) ) {
					continue;
				}
				foreach ( explode( ',', (string) $match[1] ) as $selector ) {
					$selector = trim( $selector );
					if ( preg_match( '/^[A-Za-z0-9_.#*-]+$/', $selector ) ) {
						$rules[ $selector ] = array_merge( $rules[ $selector ] ?? [], $declarations );
					}
				}
			}
		}

		return $rules;
	}

	private static function svg_css_declarations( string $css ): array {
		$out = [];
		foreach ( explode( ';', $css ) as $rule ) {
			if ( str_contains( $rule, ':' ) ) {
				[ $key, $value ] = array_map( 'trim', explode( ':', $rule, 2 ) );
				$out[ strtolower( $key ) ] = $value;
			}
		}

		return $out;
	}

	private static function svg_id_map( \DOMDocument $dom ): array {
		$ids = [];
		foreach ( $dom->getElementsByTagName( '*' ) as $element ) {
			if ( $element instanceof \DOMElement && $element->hasAttribute( 'id' ) ) {
				$ids[ $element->getAttribute( 'id' ) ] = $element;
			}
		}

		return $ids;
	}

	private static function append_svg_path_eps( array &$lines, string $d, array $style ): void {
		$commands = self::svg_path_to_eps( $d );
		if ( empty( $commands ) ) {
			return;
		}

		self::append_svg_paint_eps( $lines, $commands, $style );
	}

	private static function svg_path_to_eps( string $d ): array {
		preg_match_all( '/[AaCcHhLlMmQqSsTtVvZz]|[-+]?(?:\d*\.\d+|\d+)(?:[eE][-+]?\d+)?/', $d, $matches );
		$tokens = $matches[0] ?? [];
		$out = [];
		$i = 0;
		$cmd = '';
		$x = 0.0;
		$y = 0.0;
		$start_x = 0.0;
		$start_y = 0.0;
		$prev_cx = null;
		$prev_cy = null;
		$prev_qx = null;
		$prev_qy = null;

		while ( $i < count( $tokens ) ) {
			if ( preg_match( '/^[A-Za-z]$/', $tokens[ $i ] ) ) {
				$cmd = $tokens[ $i++ ];
			}
			if ( '' === $cmd ) {
				break;
			}

			$relative = ctype_lower( $cmd );
			switch ( strtoupper( $cmd ) ) {
				case 'M':
					while ( self::svg_has_numbers( $tokens, $i, 2 ) ) {
						$nx = (float) $tokens[ $i++ ];
						$ny = (float) $tokens[ $i++ ];
						$x = $relative ? $x + $nx : $nx;
						$y = $relative ? $y + $ny : $ny;
						$out[] = sprintf( '%.4F %.4F moveto', $x, $y );
						$start_x = $x;
						$start_y = $y;
						$cmd = $relative ? 'l' : 'L';
						$prev_cx = $prev_cy = $prev_qx = $prev_qy = null;
					}
					break;
				case 'L':
					while ( self::svg_has_numbers( $tokens, $i, 2 ) ) {
						$nx = (float) $tokens[ $i++ ];
						$ny = (float) $tokens[ $i++ ];
						$x = $relative ? $x + $nx : $nx;
						$y = $relative ? $y + $ny : $ny;
						$out[] = sprintf( '%.4F %.4F lineto', $x, $y );
						$prev_cx = $prev_cy = $prev_qx = $prev_qy = null;
					}
					break;
				case 'H':
					while ( self::svg_has_numbers( $tokens, $i, 1 ) ) {
						$nx = (float) $tokens[ $i++ ];
						$x = $relative ? $x + $nx : $nx;
						$out[] = sprintf( '%.4F %.4F lineto', $x, $y );
						$prev_cx = $prev_cy = $prev_qx = $prev_qy = null;
					}
					break;
				case 'V':
					while ( self::svg_has_numbers( $tokens, $i, 1 ) ) {
						$ny = (float) $tokens[ $i++ ];
						$y = $relative ? $y + $ny : $ny;
						$out[] = sprintf( '%.4F %.4F lineto', $x, $y );
						$prev_cx = $prev_cy = $prev_qx = $prev_qy = null;
					}
					break;
				case 'C':
					while ( self::svg_has_numbers( $tokens, $i, 6 ) ) {
						$vals = array_map( 'floatval', array_slice( $tokens, $i, 6 ) );
						$i += 6;
						[ $x1, $y1, $x2, $y2, $x3, $y3 ] = $vals;
						if ( $relative ) {
							$x1 += $x; $y1 += $y; $x2 += $x; $y2 += $y; $x3 += $x; $y3 += $y;
						}
						$out[] = sprintf( '%.4F %.4F %.4F %.4F %.4F %.4F curveto', $x1, $y1, $x2, $y2, $x3, $y3 );
						$x = $x3; $y = $y3;
						$prev_cx = $x2; $prev_cy = $y2; $prev_qx = $prev_qy = null;
					}
					break;
				case 'S':
					while ( self::svg_has_numbers( $tokens, $i, 4 ) ) {
						$vals = array_map( 'floatval', array_slice( $tokens, $i, 4 ) );
						$i += 4;
						[ $x2, $y2, $x3, $y3 ] = $vals;
						$x1 = null !== $prev_cx ? 2 * $x - $prev_cx : $x;
						$y1 = null !== $prev_cy ? 2 * $y - $prev_cy : $y;
						if ( $relative ) {
							$x2 += $x; $y2 += $y; $x3 += $x; $y3 += $y;
						}
						$out[] = sprintf( '%.4F %.4F %.4F %.4F %.4F %.4F curveto', $x1, $y1, $x2, $y2, $x3, $y3 );
						$x = $x3; $y = $y3;
						$prev_cx = $x2; $prev_cy = $y2; $prev_qx = $prev_qy = null;
					}
					break;
				case 'Q':
					while ( self::svg_has_numbers( $tokens, $i, 4 ) ) {
						$vals = array_map( 'floatval', array_slice( $tokens, $i, 4 ) );
						$i += 4;
						[ $qx, $qy, $ex, $ey ] = $vals;
						if ( $relative ) {
							$qx += $x; $qy += $y; $ex += $x; $ey += $y;
						}
						$c1x = $x + 2 / 3 * ( $qx - $x );
						$c1y = $y + 2 / 3 * ( $qy - $y );
						$c2x = $ex + 2 / 3 * ( $qx - $ex );
						$c2y = $ey + 2 / 3 * ( $qy - $ey );
						$out[] = sprintf( '%.4F %.4F %.4F %.4F %.4F %.4F curveto', $c1x, $c1y, $c2x, $c2y, $ex, $ey );
						$x = $ex; $y = $ey;
						$prev_qx = $qx; $prev_qy = $qy; $prev_cx = $prev_cy = null;
					}
					break;
				case 'T':
					while ( self::svg_has_numbers( $tokens, $i, 2 ) ) {
						$ex = (float) $tokens[ $i++ ];
						$ey = (float) $tokens[ $i++ ];
						$qx = null !== $prev_qx ? 2 * $x - $prev_qx : $x;
						$qy = null !== $prev_qy ? 2 * $y - $prev_qy : $y;
						if ( $relative ) {
							$ex += $x; $ey += $y;
						}
						$c1x = $x + 2 / 3 * ( $qx - $x );
						$c1y = $y + 2 / 3 * ( $qy - $y );
						$c2x = $ex + 2 / 3 * ( $qx - $ex );
						$c2y = $ey + 2 / 3 * ( $qy - $ey );
						$out[] = sprintf( '%.4F %.4F %.4F %.4F %.4F %.4F curveto', $c1x, $c1y, $c2x, $c2y, $ex, $ey );
						$x = $ex; $y = $ey;
						$prev_qx = $qx; $prev_qy = $qy; $prev_cx = $prev_cy = null;
					}
					break;
				case 'A':
					while ( self::svg_has_numbers( $tokens, $i, 7 ) ) {
						$vals = array_map( 'floatval', array_slice( $tokens, $i, 7 ) );
						$i += 7;
						$ex = $vals[5];
						$ey = $vals[6];
						if ( $relative ) {
							$ex += $x; $ey += $y;
						}
						$out[] = sprintf( '%.4F %.4F lineto', $ex, $ey );
						$x = $ex; $y = $ey;
						$prev_cx = $prev_cy = $prev_qx = $prev_qy = null;
					}
					break;
				case 'Z':
					$out[] = 'closepath';
					$x = $start_x;
					$y = $start_y;
					$prev_cx = $prev_cy = $prev_qx = $prev_qy = null;
					break;
				default:
					return $out;
			}
		}

		return $out;
	}

	private static function svg_has_numbers( array $tokens, int $offset, int $count ): bool {
		if ( $offset + $count > count( $tokens ) ) {
			return false;
		}
		for ( $i = 0; $i < $count; $i++ ) {
			if ( preg_match( '/^[A-Za-z]$/', (string) $tokens[ $offset + $i ] ) ) {
				return false;
			}
		}
		return true;
	}

	private static function append_svg_rect_eps( array &$lines, \DOMElement $element, array $style ): void {
		$x = self::svg_number( $element->getAttribute( 'x' ) );
		$y = self::svg_number( $element->getAttribute( 'y' ) );
		$w = self::svg_number( $element->getAttribute( 'width' ) );
		$h = self::svg_number( $element->getAttribute( 'height' ) );
		if ( $w <= 0.0 || $h <= 0.0 ) {
			return;
		}
		self::append_svg_paint_eps( $lines, [
			sprintf( '%.4F %.4F moveto', $x, $y ),
			sprintf( '%.4F %.4F lineto', $x + $w, $y ),
			sprintf( '%.4F %.4F lineto', $x + $w, $y + $h ),
			sprintf( '%.4F %.4F lineto', $x, $y + $h ),
			'closepath',
		], $style );
	}

	private static function append_svg_circle_eps( array &$lines, \DOMElement $element, array $style ): void {
		$cx = self::svg_number( $element->getAttribute( 'cx' ) );
		$cy = self::svg_number( $element->getAttribute( 'cy' ) );
		$r  = self::svg_number( $element->getAttribute( 'r' ) );
		if ( $r <= 0.0 ) {
			return;
		}
		self::append_svg_paint_eps( $lines, [ sprintf( '%.4F %.4F %.4F 0 360 arc', $cx, $cy, $r ) ], $style );
	}

	private static function append_svg_ellipse_eps( array &$lines, \DOMElement $element, array $style ): void {
		$cx = self::svg_number( $element->getAttribute( 'cx' ) );
		$cy = self::svg_number( $element->getAttribute( 'cy' ) );
		$rx = self::svg_number( $element->getAttribute( 'rx' ) );
		$ry = self::svg_number( $element->getAttribute( 'ry' ) );
		if ( $rx <= 0.0 || $ry <= 0.0 ) {
			return;
		}
		$k = 0.5522847498;
		self::append_svg_paint_eps( $lines, [
			sprintf( '%.4F %.4F moveto', $cx + $rx, $cy ),
			sprintf( '%.4F %.4F %.4F %.4F %.4F %.4F curveto', $cx + $rx, $cy + $k * $ry, $cx + $k * $rx, $cy + $ry, $cx, $cy + $ry ),
			sprintf( '%.4F %.4F %.4F %.4F %.4F %.4F curveto', $cx - $k * $rx, $cy + $ry, $cx - $rx, $cy + $k * $ry, $cx - $rx, $cy ),
			sprintf( '%.4F %.4F %.4F %.4F %.4F %.4F curveto', $cx - $rx, $cy - $k * $ry, $cx - $k * $rx, $cy - $ry, $cx, $cy - $ry ),
			sprintf( '%.4F %.4F %.4F %.4F %.4F %.4F curveto', $cx + $k * $rx, $cy - $ry, $cx + $rx, $cy - $k * $ry, $cx + $rx, $cy ),
			'closepath',
		], $style );
	}

	private static function append_svg_polyline_eps( array &$lines, array $points, array $style, bool $closed ): void {
		if ( count( $points ) < 2 ) {
			return;
		}
		$commands = [];
		foreach ( $points as $index => $point ) {
			$commands[] = sprintf( '%.4F %.4F %s', (float) $point[0], (float) $point[1], 0 === $index ? 'moveto' : 'lineto' );
		}
		if ( $closed ) {
			$commands[] = 'closepath';
		}
		self::append_svg_paint_eps( $lines, $commands, $style );
	}

	private static function append_svg_paint_eps( array &$lines, array $commands, array $style ): void {
		$fill   = self::svg_colour( (string) ( $style['fill'] ?? '#000000' ) );
		$stroke = self::svg_colour( (string) ( $style['stroke'] ?? 'none' ) );
		if ( $fill && $stroke && self::same_rgb( $fill, $stroke ) ) {
			$stroke = null;
		}

		if ( $fill ) {
			$lines[] = 'gsave';
			$lines[] = 'newpath';
			array_push( $lines, ...$commands );
			$lines[] = sprintf( '%.4F %.4F %.4F setrgbcolor', $fill[0], $fill[1], $fill[2] );
			$lines[] = 'fill';
			$lines[] = 'grestore';
		}

		if ( $stroke ) {
			$lines[] = 'gsave';
			$lines[] = 'newpath';
			array_push( $lines, ...$commands );
			$lines[] = sprintf( '%.4F setlinewidth', max( 0.1, self::svg_number( (string) ( $style['stroke-width'] ?? '1' ) ) ) );
			$lines[] = sprintf( '%.4F %.4F %.4F setrgbcolor', $stroke[0], $stroke[1], $stroke[2] );
			$lines[] = 'stroke';
			$lines[] = 'grestore';
		}
	}

	private static function svg_colour( string $value ): ?array {
		$value = trim( strtolower( $value ) );
		if ( '' === $value || 'none' === $value || str_starts_with( $value, 'url(' ) ) {
			return null;
		}
		if ( 'currentcolor' === $value || 'currentColor' === $value ) {
			$value = '#000000';
		}
		if ( preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $value ) ) {
			return self::hex_to_unit_rgb( $value );
		}
		if ( preg_match( '/^rgb\((\d+),\s*(\d+),\s*(\d+)\)$/', $value, $matches ) ) {
			return [ min( 255, (int) $matches[1] ) / 255, min( 255, (int) $matches[2] ) / 255, min( 255, (int) $matches[3] ) / 255 ];
		}
		return [ 0.0, 0.0, 0.0 ];
	}

	private static function same_rgb( array $a, array $b ): bool {
		return abs( (float) $a[0] - (float) $b[0] ) < 0.0001
			&& abs( (float) $a[1] - (float) $b[1] ) < 0.0001
			&& abs( (float) $a[2] - (float) $b[2] ) < 0.0001;
	}

	private static function svg_points( string $points ): array {
		preg_match_all( '/[-+]?(?:\d*\.\d+|\d+)(?:[eE][-+]?\d+)?/', $points, $matches );
		$values = array_map( 'floatval', $matches[0] ?? [] );
		$out    = [];
		for ( $i = 0; $i + 1 < count( $values ); $i += 2 ) {
			$out[] = [ $values[ $i ], $values[ $i + 1 ] ];
		}
		return $out;
	}

	private static function svg_number( string $value ): float {
		return preg_match( '/[-+]?(?:\d*\.\d+|\d+)(?:[eE][-+]?\d+)?/', $value, $matches ) ? (float) $matches[0] : 0.0;
	}

	/** Convert an SVG to a GD image so clipart appears in EPS output. */
	private static function open_svg_resource( string $path, float $w_pt, float $h_pt ) {
		if ( ! class_exists( '\Imagick' ) || ! function_exists( 'imagecreatefromstring' ) ) {
			return false;
		}

		$width_px  = max( 1, min( 1200, (int) round( $w_pt / 72 * 300 ) ) );
		$height_px = max( 1, min( 1200, (int) round( $h_pt / 72 * 300 ) ) );

		try {
			$imagick = new \Imagick();
			$imagick->setBackgroundColor( new \ImagickPixel( 'transparent' ) );
			$imagick->setResolution( 300, 300 );
			$imagick->readImage( $path );
			$imagick->setImageFormat( 'png' );
			$imagick->resizeImage( $width_px, $height_px, \Imagick::FILTER_LANCZOS, 1, true );
			$blob = $imagick->getImagesBlob();
			$imagick->clear();
			$imagick->destroy();

			return is_string( $blob ) && '' !== $blob ? @imagecreatefromstring( $blob ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		} catch ( \Throwable $e ) {
			if ( class_exists( 'OC_Logger' ) ) {
				OC_Logger::warning( 'Embroidery EPS SVG render failed for ' . basename( $path ) . ': ' . $e->getMessage() );
			}
			return false;
		}
	}

	/** Build a temporary recoloured SVG for recolourable clipart. */
	private static function build_coloured_svg( string $path, string $hex ): ?string {
		$raw = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( ! is_string( $raw ) || '' === $raw ) {
			return null;
		}

		$dom      = new \DOMDocument();
		$previous = libxml_use_internal_errors( true );
		$loaded   = $dom->loadXML( $raw, LIBXML_NONET | LIBXML_NOCDATA );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		if ( ! $loaded || ! $dom->documentElement || 'svg' !== strtolower( $dom->documentElement->localName ) ) {
			return null;
		}

		$dom->documentElement->setAttribute( 'color', $hex );
		self::force_svg_node_colour( $dom->documentElement, $hex );
		self::crop_svg_to_visible_bounds( $dom->documentElement );

		$temp = self::temp_svg_path( 'oc-eps-colour-clipart-' . wp_generate_uuid4() );
		if ( ! is_string( $temp ) || '' === $temp ) {
			return null;
		}

		$output = $dom->saveXML( $dom->documentElement );
		if ( ! is_string( $output ) || false === file_put_contents( $temp, $output ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			@unlink( $temp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return null;
		}

		return $temp;
	}

	/** Return a writable temporary SVG path that keeps the .svg extension for file-type detection. */
	private static function temp_svg_path( string $prefix ): ?string {
		$temp = self::temp_path( $prefix . '.svg' );
		if ( ! is_string( $temp ) || '' === $temp ) {
			return null;
		}

		if ( 'svg' === strtolower( pathinfo( $temp, PATHINFO_EXTENSION ) ) ) {
			return $temp;
		}

		$svg_temp = $temp . '.svg';
		if ( file_exists( $svg_temp ) ) {
			@unlink( $svg_temp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		if ( ! @rename( $temp, $svg_temp ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			@unlink( $temp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return null;
		}

		return $svg_temp;
	}

	/** Match the preview by fitting recoloured SVG clipart to visible artwork, not its original canvas. */
	private static function crop_svg_to_visible_bounds( \DOMElement $svg ): void {
		if ( self::has_complex_svg_paint_references( $svg ) ) {
			return;
		}

		$bounds = self::svg_visible_bounds( $svg );
		if ( ! $bounds ) {
			return;
		}

		[ $x1, $y1, $x2, $y2 ] = $bounds;
		$x = $x1;
		$y = $y1;
		$w = $x2 - $x1;
		$h = $y2 - $y1;
		if ( $w <= 0.0 || $h <= 0.0 ) {
			return;
		}

		$svg->setAttribute( 'viewBox', sprintf( '%.4F %.4F %.4F %.4F', $x, $y, $w, $h ) );
		$svg->setAttribute( 'width', sprintf( '%.4F', $w ) );
		$svg->setAttribute( 'height', sprintf( '%.4F', $h ) );
	}

	private static function has_complex_svg_paint_references( \DOMElement $svg ): bool {
		$complex_tags = [ 'clipPath', 'mask', 'filter' ];
		foreach ( $complex_tags as $tag ) {
			if ( $svg->getElementsByTagName( $tag )->length > 0 ) {
				return true;
			}
		}

		foreach ( $svg->getElementsByTagName( '*' ) as $element ) {
			if ( ! $element instanceof \DOMElement ) {
				continue;
			}

			foreach ( [ 'clip-path', 'mask', 'filter' ] as $attribute ) {
				if ( $element->hasAttribute( $attribute ) ) {
					return true;
				}
			}
		}

		return false;
	}

	private static function svg_visible_bounds( \DOMElement $root ): ?array {
		$bounds = null;
		foreach ( $root->getElementsByTagName( '*' ) as $element ) {
			if ( ! $element instanceof \DOMElement ) {
				continue;
			}

			$box = self::svg_element_bounds( $element );
			if ( ! $box || ! self::svg_element_visible( $element ) ) {
				continue;
			}

			$bounds = self::merge_bounds( $bounds, $box );
		}

		return $bounds;
	}

	private static function svg_element_visible( \DOMElement $element ): bool {
		$style = $element->hasAttribute( 'style' ) ? self::svg_css_declarations( $element->getAttribute( 'style' ) ) : [];
		$fill  = strtolower( trim( (string) ( $style['fill'] ?? $element->getAttribute( 'fill' ) ) ) );
		$stroke = strtolower( trim( (string) ( $style['stroke'] ?? $element->getAttribute( 'stroke' ) ) ) );

		return 'none' !== $fill || ( '' !== $stroke && 'none' !== $stroke );
	}

	private static function svg_element_bounds( \DOMElement $element ): ?array {
		$name = strtolower( $element->localName );
		switch ( $name ) {
			case 'rect':
				$x = self::svg_number( $element->getAttribute( 'x' ) );
				$y = self::svg_number( $element->getAttribute( 'y' ) );
				$w = self::svg_number( $element->getAttribute( 'width' ) );
				$h = self::svg_number( $element->getAttribute( 'height' ) );
				return $w > 0.0 && $h > 0.0 ? [ $x, $y, $x + $w, $y + $h ] : null;

			case 'circle':
				$cx = self::svg_number( $element->getAttribute( 'cx' ) );
				$cy = self::svg_number( $element->getAttribute( 'cy' ) );
				$r  = self::svg_number( $element->getAttribute( 'r' ) );
				return $r > 0.0 ? [ $cx - $r, $cy - $r, $cx + $r, $cy + $r ] : null;

			case 'ellipse':
				$cx = self::svg_number( $element->getAttribute( 'cx' ) );
				$cy = self::svg_number( $element->getAttribute( 'cy' ) );
				$rx = self::svg_number( $element->getAttribute( 'rx' ) );
				$ry = self::svg_number( $element->getAttribute( 'ry' ) );
				return $rx > 0.0 && $ry > 0.0 ? [ $cx - $rx, $cy - $ry, $cx + $rx, $cy + $ry ] : null;

			case 'line':
				$x1 = self::svg_number( $element->getAttribute( 'x1' ) );
				$y1 = self::svg_number( $element->getAttribute( 'y1' ) );
				$x2 = self::svg_number( $element->getAttribute( 'x2' ) );
				$y2 = self::svg_number( $element->getAttribute( 'y2' ) );
				return [ min( $x1, $x2 ), min( $y1, $y2 ), max( $x1, $x2 ), max( $y1, $y2 ) ];

			case 'polyline':
			case 'polygon':
				return self::svg_points_bounds( self::svg_points( $element->getAttribute( 'points' ) ) );

			case 'path':
				return self::svg_path_bounds( $element->getAttribute( 'd' ) );
		}

		return null;
	}

	private static function svg_points_bounds( array $points ): ?array {
		if ( empty( $points ) ) {
			return null;
		}

		$xs = array_map( static fn ( array $point ): float => (float) $point[0], $points );
		$ys = array_map( static fn ( array $point ): float => (float) $point[1], $points );

		return [ min( $xs ), min( $ys ), max( $xs ), max( $ys ) ];
	}

	private static function svg_path_bounds( string $d ): ?array {
		preg_match_all( '/[-+]?(?:\d*\.\d+|\d+)(?:[eE][-+]?\d+)?/', $d, $matches );
		$values = array_map( 'floatval', $matches[0] ?? [] );
		if ( count( $values ) < 2 ) {
			return null;
		}

		$points = [];
		for ( $i = 0; $i + 1 < count( $values ); $i += 2 ) {
			$points[] = [ $values[ $i ], $values[ $i + 1 ] ];
		}

		return self::svg_points_bounds( $points );
	}

	private static function merge_bounds( ?array $a, array $b ): array {
		if ( ! $a ) {
			return $b;
		}

		return [
			min( (float) $a[0], (float) $b[0] ),
			min( (float) $a[1], (float) $b[1] ),
			max( (float) $a[2], (float) $b[2] ),
			max( (float) $a[3], (float) $b[3] ),
		];
	}

	/** Force fill/stroke colours throughout an SVG DOM subtree. */
	private static function force_svg_node_colour( \DOMElement $element, string $hex ): void {
		if ( 'style' === strtolower( $element->localName ) ) {
			$element->nodeValue = self::force_svg_css_colour( $element->nodeValue ?? '', $hex );
			return;
		}

		$tag = strtolower( $element->localName );
		if ( 'svg' !== $tag ) {
			if ( $element->hasAttribute( 'fill' ) ) {
				$fill = trim( $element->getAttribute( 'fill' ) );
				if ( 'none' !== strtolower( $fill ) ) {
					$element->setAttribute( 'fill', self::is_svg_white( $fill ) ? 'none' : $hex );
				}
			}
			if ( $element->hasAttribute( 'stroke' ) ) {
				$stroke = trim( $element->getAttribute( 'stroke' ) );
				if ( 'none' !== strtolower( $stroke ) ) {
					$element->setAttribute( 'stroke', self::is_svg_white( $stroke ) ? 'none' : $hex );
				}
			}
			if ( $element->hasAttribute( 'style' ) ) {
				$element->setAttribute( 'style', self::force_svg_style_colour( $element->getAttribute( 'style' ), $hex ) );
			}

			if ( in_array( $tag, [ 'path', 'rect', 'circle', 'ellipse', 'polygon', 'polyline', 'text' ], true ) && ! $element->hasAttribute( 'fill' ) && ! $element->hasAttribute( 'stroke' ) && ! $element->hasAttribute( 'style' ) ) {
				$element->setAttribute( 'fill', $hex );
			}
		}

		foreach ( $element->childNodes as $child ) {
			if ( $child instanceof \DOMElement ) {
				self::force_svg_node_colour( $child, $hex );
			}
		}
	}

	private static function force_svg_style_colour( string $style, string $hex ): string {
		$parts = array_filter( array_map( 'trim', explode( ';', $style ) ) );
		foreach ( $parts as &$part ) {
			if ( preg_match( '/^\s*(fill|stroke)\s*:/i', $part ) && ! preg_match( '/:\s*none\s*$/i', $part ) ) {
				$property = trim( (string) strtok( $part, ':' ) );
				$value    = trim( substr( $part, (int) strpos( $part, ':' ) + 1 ) );
				$part     = $property . ':' . ( self::is_svg_white( $value ) ? 'none' : $hex );
			}
		}

		return implode( ';', $parts );
	}

	private static function force_svg_css_colour( string $css, string $hex ): string {
		return (string) preg_replace_callback(
			'/\b(fill|stroke)\s*:\s*([^;}]+)/i',
			static function ( array $matches ) use ( $hex ): string {
				$value = strtolower( trim( (string) $matches[2] ) );
				return 'none' === $value ? $matches[0] : $matches[1] . ':' . ( self::is_svg_white( $value ) ? 'none' : $hex );
			},
			$css
		);
	}

	private static function is_svg_white( string $value ): bool {
		$normalised = strtolower( preg_replace( '/\s+/', '', trim( $value ) ) ?? '' );
		return in_array( $normalised, [ '#fff', '#ffffff', 'white', 'rgb(255,255,255)', 'rgba(255,255,255,1)' ], true );
	}

	private static function layer_rotation( array $layer, array $input, array $settings ): float {
		foreach ( [ $layer['rotation'] ?? null, $input['rotation'] ?? null, $settings['rotation'] ?? null ] as $value ) {
			if ( is_numeric( $value ) ) {
				return self::normalise_rotation( (float) $value );
			}
		}

		return 0.0;
	}

	private static function normalise_rotation( float $rotation ): float {
		$rotation = fmod( $rotation, 360.0 );
		return $rotation < 0.0 ? $rotation + 360.0 : $rotation;
	}

	private static function fit_eps_box( float $src_w, float $src_h, float $x_pt, float $y_pt, float $w_pt, float $h_pt, string $fit = 'contain' ): array {
		if ( $src_w <= 0.0 || $src_h <= 0.0 || $w_pt <= 0.0 || $h_pt <= 0.0 ) {
			return [ $x_pt, $y_pt, $w_pt, $h_pt ];
		}

		$scale  = 'cover' === $fit ? max( $w_pt / $src_w, $h_pt / $src_h ) : min( $w_pt / $src_w, $h_pt / $src_h );
		$draw_w = $src_w * $scale;
		$draw_h = $src_h * $scale;

		return [
			$x_pt + ( $w_pt - $draw_w ) / 2,
			$y_pt + ( $h_pt - $draw_h ) / 2,
			$draw_w,
			$draw_h,
		];
	}

	/** Draw a visible placeholder when vector/raster conversion is not possible. */
	private static function append_eps_artwork_box( array &$lines, float $x_pt, float $y_pt, float $w_pt, float $h_pt, string $label ): void {
		$lines[] = 'gsave';
		$lines[] = '0 0 0 setrgbcolor';
		$lines[] = '0.5 setlinewidth';
		$lines[] = sprintf( 'newpath %.4F %.4F moveto %.4F 0 rlineto 0 %.4F rlineto %.4F 0 rlineto closepath stroke', $x_pt, $y_pt, $w_pt, $h_pt, -$w_pt );
		$lines[] = '/Helvetica findfont 6 scalefont setfont';
		$lines[] = sprintf( '%.4F %.4F moveto', $x_pt + 2, $y_pt + max( 8, $h_pt / 2 ) );
		$lines[] = '(' . self::ps_escape( 'Clipart: ' . $label ) . ') show';
		$lines[] = 'grestore';
	}

	private static function mm_to_pt( float $mm ): float {
		return $mm * 72 / 25.4;
	}

	/** Build a cache-busting embroidery filename so regenerated EPS files cannot be confused with older downloads. */
	private static function build_versioned_filename( int $item_id, string $area_key, string $extension ): string {
		return sprintf(
			'%d-%s-%s.%s',
			$item_id,
			sanitize_file_name( $area_key ),
			gmdate( 'YmdHis' ) . '-' . substr( wp_generate_uuid4(), 0, 8 ),
			$extension
		);
	}

	private static function hex_to_unit_rgb( string $hex ): array {
		[ $r, $g, $b ] = self::hex_to_rgb( self::normalise_hex( $hex ) );
		return [ round( $r / 255, 4 ), round( $g / 255, 4 ), round( $b / 255, 4 ) ];
	}

	private static function normalise_hex( string $hex ): string {
		$hex = ltrim( trim( $hex ), '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( ! preg_match( '/^[A-Fa-f0-9]{6}$/', $hex ) ) {
			$hex = '000000';
		}

		return '#' . strtoupper( $hex );
	}

	private static function ps_escape( string $value ): string {
		return str_replace( [ '\\', '(', ')', "\r", "\n" ], [ '\\\\', '\\(', '\\)', ' ', ' ' ], $value );
	}

	private static function eps_comment( string $value ): string {
		return str_replace( [ "\r", "\n" ], ' ', $value );
	}

	/** Open a raster image resource for EPS embedding. */
	private static function open_raster_resource( string $path ) {
		$ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		return match ( $ext ) {
			'jpg', 'jpeg' => function_exists( 'imagecreatefromjpeg' ) ? @imagecreatefromjpeg( $path ) : false, // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			'png' => function_exists( 'imagecreatefrompng' ) ? @imagecreatefrompng( $path ) : false, // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			'webp' => function_exists( 'imagecreatefromwebp' ) ? @imagecreatefromwebp( $path ) : false, // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			'bmp' => function_exists( 'imagecreatefrombmp' ) ? @imagecreatefrombmp( $path ) : false, // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			'gif' => function_exists( 'imagecreatefromgif' ) ? @imagecreatefromgif( $path ) : false, // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			default => false,
		};
	}

}
