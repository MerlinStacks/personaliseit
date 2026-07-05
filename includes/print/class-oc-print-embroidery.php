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
			self::append_eps_layers( $lines, $area, $area_data );
		} else {
			self::append_eps_legacy_artwork( $lines, $area, $area_data );
		}

		$lines[] = 'grestore';
		$lines[] = 'showpage';
		$lines[] = '%%EOF';

		$output_path = $output_dir . '/' . self::build_filename( $item_id, $area->area_key, 'eps' );
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
		$text_fallbacks = array_values( array_filter( array_map( 'trim', preg_split( '/\R/', (string) ( $area_data['text'] ?? '' ) ) ?: [] ) ) );
		$text_index     = 0;
		$artwork_used   = false;

		foreach ( $area_data['layers'] as $layer ) {
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
					self::append_eps_text( $lines, $input, $settings, $x_pt, $y_pt, $w_pt, $h_pt, true );
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
						true
					);
					break;
			}

			$lines[] = 'grestore';
		}
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

	/** Append customer text as editable PostScript text in the requested colour. */
	private static function append_eps_text(
		array &$lines,
		array $input,
		array $settings,
		float $x_pt,
		float $y_pt,
		float $w_pt,
		float $h_pt,
		bool $centered = false
	): void {
		$text = trim( (string) ( $input['value'] ?? '' ) );
		if ( '' === $text ) {
			return;
		}

		$hex       = (string) ( $input['colorHex'] ?? $settings['default_color'] ?? '#000000' );
		$font_size = ! empty( $input['fontSize'] ) || ! empty( $settings['default_font_size'] )
			? max( 5.0, self::px_to_pt( (float) ( $input['fontSize'] ?? $settings['default_font_size'] ) ) )
			: max( 8.0, $h_pt * 0.38 );
		$font_size = min( $font_size, max( 5.0, $h_pt * 0.8 ) );
		$font_id   = ! empty( $input['fontId'] ) ? (int) $input['fontId'] : (int) ( $settings['default_font_id'] ?? 0 );

		[ $r, $g, $b ] = self::hex_to_unit_rgb( $hex );
		$font_name     = self::eps_font_name( $font_id );
		$lines[] = '%%OCTextColor: ' . strtoupper( self::normalise_hex( $hex ) );
		$lines[] = '%%OCTextFont: ' . self::eps_comment( $font_name );
		$lines[] = 'gsave';
		$lines[] = sprintf( '%.4F %.4F %.4F setrgbcolor', $r, $g, $b );
		$lines[] = sprintf( '/Helvetica findfont %.4F scalefont setfont', $font_size );
		if ( 'Helvetica' !== $font_name ) {
			$lines[] = sprintf( '{ /%s findfont %.4F scalefont setfont } stopped { pop } if', self::ps_name_escape( $font_name ), $font_size );
		}
		if ( $centered ) {
			$align = (string) ( $settings['alignment'] ?? 'center' );
			$lines[] = sprintf( '%.4F %.4F moveto', self::eps_text_align_x( $align, $x_pt, $w_pt ), $y_pt + ( $h_pt + $font_size ) / 2 );
			$lines[] = '(' . self::ps_escape( $text ) . ')' . self::eps_text_show_command( $align );
		} else {
			$lines[] = sprintf( '%.4F %.4F moveto', $x_pt + 2, $y_pt + max( $font_size, ( $h_pt + $font_size ) / 2 ) );
			$lines[] = '(' . self::ps_escape( $text ) . ') show';
		}
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

	/** Return the PostScript command needed to honour Fabric text alignment. */
	private static function eps_text_show_command( string $align ): string {
		return match ( $align ) {
			'right' => ' dup stringwidth pop neg 0 rmoveto show',
			'left'  => ' show',
			default => ' dup stringwidth pop 2 div neg 0 rmoveto show',
		};
	}

	/** Return the selected font family for EPS consumers that have production fonts installed. */
	private static function eps_font_name( int $font_id ): string {
		$font = self::get_font( $font_id );
		$name = is_object( $font ) ? trim( (string) ( $font->name ?? '' ) ) : '';

		return '' !== $name ? $name : 'Helvetica';
	}

	/** Escape a PostScript name token. */
	private static function ps_name_escape( string $value ): string {
		$value = preg_replace( '/\s+/', '-', trim( $value ) );
		$value = is_string( $value ) && '' !== $value ? $value : 'Helvetica';

		return preg_replace( '/[^A-Za-z0-9_.-]/', '', $value ) ?: 'Helvetica';
	}

	/** Render selected-font text as artwork so the EPS visually matches the live preview. */
	private static function append_eps_raster_text( array &$lines, string $text, string $hex, int $font_id, array $settings, float $x_pt, float $y_pt, float $w_pt, float $h_pt, float $font_size_pt ): bool {
		if ( ! function_exists( 'imagettftext' ) || ! function_exists( 'imagecreatetruecolor' ) ) {
			return false;
		}

		$font = self::get_font( $font_id );
		if ( ! $font ) {
			return false;
		}

		$font_path = self::get_font_path( $font );
		if ( ! $font_path || ! file_exists( $font_path ) ) {
			return false;
		}

		$font_size_px = max( 1, $font_size_pt / 72 * 300 );
		$line_height  = $font_size_px * 1.18;
		$text_lines   = preg_split( '/\R/', $text ) ?: [ $text ];
		$text_width   = 1.0;
		foreach ( $text_lines as $line ) {
			$box = imagettfbbox( $font_size_px, 0, $font_path, (string) $line );
			if ( is_array( $box ) ) {
				$text_width = max( $text_width, abs( (float) $box[2] - (float) $box[0] ) );
			}
		}

		$base_w  = max( 1, (int) round( $w_pt / 72 * 300 ) );
		$base_h  = max( 1, (int) round( $h_pt / 72 * 300 ) );
		$padding = max( 8, (int) ceil( $font_size_px * 0.4 ) );
		$img_w   = max( $base_w, min( 2400, (int) ceil( $text_width + $padding * 2 ) ) );
		$img_h   = max( $base_h, min( 1600, (int) ceil( count( $text_lines ) * $line_height + $padding * 2 ) ) );
		$image = imagecreatetruecolor( $img_w, $img_h );
		imagealphablending( $image, false );
		imagesavealpha( $image, true );
		$clear = imagecolorallocatealpha( $image, 255, 255, 255, 127 );
		imagefilledrectangle( $image, 0, 0, $img_w, $img_h, $clear );
		imagealphablending( $image, true );

		[ $r, $g, $b ] = self::hex_to_rgb( self::normalise_hex( $hex ) );
		$colour        = imagecolorallocate( $image, $r, $g, $b );
		$total_height  = count( $text_lines ) * $line_height;
		$baseline_y    = ( $img_h - $total_height ) / 2 + $font_size_px;
		$align         = (string) ( $settings['alignment'] ?? 'center' );

		foreach ( $text_lines as $index => $line ) {
			$line = (string) $line;
			$box  = imagettfbbox( $font_size_px, 0, $font_path, $line );
			if ( ! is_array( $box ) ) {
				imagedestroy( $image );
				return false;
			}

			$text_w = abs( (float) $box[2] - (float) $box[0] );
			$x      = match ( $align ) {
				'left'  => (float) $padding,
				'right' => max( 0.0, $img_w - $text_w - $padding ),
				default => max( 0.0, ( $img_w - $text_w ) / 2 ),
			};
			$y      = $baseline_y + $index * $line_height;
			imagettftext( $image, $font_size_px, 0, (int) round( $x ), (int) round( $y ), $colour, $font_path, $line );
		}

		$lines[] = '%%OCTextColor: ' . strtoupper( self::normalise_hex( $hex ) );
		$lines[] = '%%OCTextFont: ' . self::eps_comment( (string) ( $font->name ?? '' ) );
		$draw_w_pt = $img_w / 300 * 72;
		$draw_h_pt = $img_h / 300 * 72;
		$draw_x_pt = match ( $align ) {
			'left'  => $x_pt,
			'right' => $x_pt + $w_pt - $draw_w_pt,
			default => $x_pt - ( $draw_w_pt - $w_pt ) / 2,
		};
		$draw_y_pt = $y_pt - ( $draw_h_pt - $h_pt ) / 2;
		self::append_eps_mask_image( $lines, $image, $hex, $draw_x_pt, $draw_y_pt, $draw_w_pt, $draw_h_pt );
		imagedestroy( $image );

		return true;
	}

	/** Append a single-colour image mask, preserving transparent text backgrounds in EPS. */
	private static function append_eps_mask_image( array &$lines, $image, string $hex, float $x_pt, float $y_pt, float $w_pt, float $h_pt ): void {
		$src_w = imagesx( $image );
		$src_h = imagesy( $image );
		if ( $src_w < 1 || $src_h < 1 ) {
			return;
		}

		[ $r, $g, $b ] = self::hex_to_unit_rgb( $hex );
		$lines[] = 'gsave';
		$lines[] = sprintf( '%.4F %.4F %.4F setrgbcolor', $r, $g, $b );
		$lines[] = sprintf( '%.4F %.4F translate', $x_pt, $y_pt );
		$lines[] = sprintf( '%.4F %.4F scale', $w_pt, $h_pt );
		$lines[] = '/picstr ' . (int) ceil( $src_w / 8 ) . ' string def';
		$lines[] = sprintf( '%d %d true [%d 0 0 -%d 0 %d]', $src_w, $src_h, $src_w, $src_h, $src_h );
		$lines[] = '{ currentfile picstr readhexstring pop } imagemask';

		for ( $y = 0; $y < $src_h; $y++ ) {
			$row = '';
			$byte = 0;
			$bit  = 7;
			for ( $x = 0; $x < $src_w; $x++ ) {
				$rgba  = imagecolorat( $image, $x, $y );
				$alpha = ( $rgba & 0x7F000000 ) >> 24;
				if ( $alpha < 96 ) {
					$byte |= 1 << $bit;
				}
				$bit--;
				if ( $bit < 0 ) {
					$row .= sprintf( '%02X', $byte );
					$byte = 0;
					$bit  = 7;
				}
			}
			if ( $bit < 7 ) {
				$row .= sprintf( '%02X', $byte );
			}
			$lines[] = $row;
		}

		$lines[] = 'grestore';
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
		imagealphablending( $draw, true );
		$white = imagecolorallocate( $draw, 255, 255, 255 );
		imagefilledrectangle( $draw, 0, 0, $out_w, $out_h, $white );
		imagecopyresampled( $draw, $image, 0, 0, 0, 0, $out_w, $out_h, $src_w, $src_h );

		[ $draw_x, $draw_y, $draw_w, $draw_h ] = self::fit_eps_box( (float) $src_w, (float) $src_h, $x_pt, $y_pt, $w_pt, $h_pt, $fit );

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
		self::append_svg_paint_eps( $lines, [
			'gsave',
			sprintf( '%.4F %.4F translate', $cx, $cy ),
			sprintf( '%.8F %.8F scale', $rx, $ry ),
			'0 0 1 0 360 arc',
			'grestore',
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
		$temp = wp_tempnam( $prefix . '.svg' );
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

	private static function eps_y( float $y_mm, float $h_mm, object $area ): float {
		[ , $area_h_mm ] = self::area_dimensions_mm( $area );
		return self::mm_to_pt( max( 0.0, $area_h_mm - $y_mm - $h_mm ) );
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
