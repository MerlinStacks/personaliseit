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
		$unit   = isset( $area->canvas_unit ) ? (string) $area->canvas_unit : 'px';
		$area_x = isset( $bounds['x'] ) ? (float) $bounds['x'] : (float) ( $area->canvas_x ?? 0 );
		$area_y = isset( $bounds['y'] ) ? (float) $bounds['y'] : (float) ( $area->canvas_y ?? 0 );
		$area_w = isset( $bounds['w'] ) ? (float) $bounds['w'] : (float) ( $area->canvas_w ?? 1 );
		$area_h = isset( $bounds['h'] ) ? (float) $bounds['h'] : (float) ( $area->canvas_h ?? 1 );
		$rotate = (float) ( $bounds['rotation'] ?? $area->canvas_rotation ?? 0 );
		[ , $area_h_mm ] = self::area_dimensions_mm( $area );

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

			if ( $rotate && $area_w > 0 && $area_h > 0 ) {
				$area_cx  = $area_x + $area_w / 2;
				$area_cy  = $area_y + $area_h / 2;
				$radians  = deg2rad( $rotate );
				$delta_x  = $center_x - $area_cx;
				$delta_y  = $center_y - $area_cy;
				$center_x = $area_cx + $delta_x * cos( $radians ) - $delta_y * sin( $radians );
				$center_y = $area_cy + $delta_x * sin( $radians ) + $delta_y * cos( $radians );
			}

			$center_x_mm = self::unit_to_mm( $center_x - $area_x, $unit );
			$center_y_mm = self::unit_to_mm( $center_y - $area_y, $unit );
			$cx_pt       = self::mm_to_pt( $center_x_mm );
			$cy_pt       = self::mm_to_pt( max( 0.0, $area_h_mm - $center_y_mm ) );
			$w_pt     = self::mm_to_pt( self::unit_to_mm( max( 1.0, (float) ( $layer['w'] ?? 1 ) ), $unit ) );
			$h_pt     = self::mm_to_pt( self::unit_to_mm( max( 1.0, (float) ( $layer['h'] ?? 1 ) ), $unit ) );
			$x_pt     = -$w_pt / 2;
			$y_pt     = -$h_pt / 2;

			$lines[] = 'gsave';
			$lines[] = sprintf( '%.4F %.4F translate', $cx_pt, $cy_pt );
			if ( $rotate ) {
				$lines[] = sprintf( '%.4F rotate', -$rotate );
			}

			switch ( $type ) {
				case 'text':
				case 'textarea':
					self::append_eps_text( $lines, $input, $settings, $x_pt, $y_pt, $w_pt, $h_pt, true );
					break;

				case 'lineart':
					self::append_eps_rect( $lines, (string) ( $input['colorHex'] ?? '#000000' ), $x_pt, $y_pt, $w_pt, $h_pt );
					break;

				case 'clipart':
				case 'image':
				case 'clipmask':
					self::append_eps_artwork( $lines, $layer, $x_pt, $y_pt, $w_pt, $h_pt );
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
			self::append_eps_image_or_reference( $lines, $path, 0, 0, $w_pt, $h_pt );
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
		$text = trim( (string) ( $input['value'] ?? $settings['default_text'] ?? '' ) );
		if ( '' === $text ) {
			return;
		}

		$hex       = (string) ( $input['colorHex'] ?? $settings['default_color'] ?? '#000000' );
		$font_size = ! empty( $input['fontSize'] ) || ! empty( $settings['default_font_size'] )
			? max( 5.0, self::px_to_pt( (float) ( $input['fontSize'] ?? $settings['default_font_size'] ) ) )
			: max( 8.0, $h_pt * 0.38 );
		$font_size = min( $font_size, max( 5.0, $h_pt * 0.8 ) );
		$font_id   = ! empty( $input['fontId'] ) ? (int) $input['fontId'] : (int) ( $settings['default_font_id'] ?? 0 );

		if ( self::append_eps_raster_text( $lines, $text, $hex, $font_id, $settings, $x_pt, $y_pt, $w_pt, $h_pt, $font_size ) ) {
			return;
		}

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

		$img_w = max( 1, min( 1600, (int) round( $w_pt / 72 * 300 ) ) );
		$img_h = max( 1, min( 1600, (int) round( $h_pt / 72 * 300 ) ) );
		$image = imagecreatetruecolor( $img_w, $img_h );
		imagealphablending( $image, false );
		imagesavealpha( $image, true );
		$clear = imagecolorallocatealpha( $image, 255, 255, 255, 127 );
		imagefilledrectangle( $image, 0, 0, $img_w, $img_h, $clear );
		imagealphablending( $image, true );

		[ $r, $g, $b ] = self::hex_to_rgb( self::normalise_hex( $hex ) );
		$colour        = imagecolorallocate( $image, $r, $g, $b );
		$font_size_px  = max( 1, $font_size_pt / 72 * 300 );
		$line_height   = $font_size_px * 1.18;
		$text_lines    = preg_split( '/\R/', $text ) ?: [ $text ];
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
				'left'  => 0.0,
				'right' => max( 0.0, $img_w - $text_w ),
				default => max( 0.0, ( $img_w - $text_w ) / 2 ),
			};
			$y      = $baseline_y + $index * $line_height;
			imagettftext( $image, $font_size_px, 0, (int) round( $x ), (int) round( $y ), $colour, $font_path, $line );
		}

		$lines[] = '%%OCTextColor: ' . strtoupper( self::normalise_hex( $hex ) );
		$lines[] = '%%OCTextFont: ' . self::eps_comment( (string) ( $font->name ?? '' ) );
		self::append_eps_raster_image( $lines, $image, $x_pt, $y_pt, $w_pt, $h_pt );
		imagedestroy( $image );

		return true;
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
	private static function append_eps_artwork( array &$lines, array $layer, float $x_pt, float $y_pt, float $w_pt, float $h_pt ): void {
		$path = self::resolve_artwork_path( $layer );
		if ( ! $path ) {
			return;
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

		self::append_eps_image_or_reference( $lines, $path, $x_pt, $y_pt, $w_pt, $h_pt );

		if ( is_string( $temp_path ) && '' !== $temp_path && file_exists( $temp_path ) ) {
			@unlink( $temp_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
	}

	/** Embed supported raster artwork or include vector source as EPS comments. */
	private static function append_eps_image_or_reference( array &$lines, string $path, float $x_pt, float $y_pt, float $w_pt, float $h_pt ): void {
		$lines[] = '%%OCArtworkFile: ' . self::eps_comment( basename( $path ) );
		$ext     = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );

		if ( 'svg' === $ext ) {
			$image = self::open_svg_resource( $path, $w_pt, $h_pt );
			if ( $image ) {
				self::append_eps_raster_image( $lines, $image, $x_pt, $y_pt, $w_pt, $h_pt );
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

		self::append_eps_raster_image( $lines, $image, $x_pt, $y_pt, $w_pt, $h_pt );
		imagedestroy( $image );
	}

	/** Append a full-colour raster image using PostScript colorimage. */
	private static function append_eps_raster_image( array &$lines, $image, float $x_pt, float $y_pt, float $w_pt, float $h_pt ): void {
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

		$lines[] = 'gsave';
		$lines[] = sprintf( '%.4F %.4F translate', $x_pt, $y_pt );
		$lines[] = sprintf( '%.4F %.4F scale', $w_pt, $h_pt );
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
		$dom->documentElement->setAttribute( 'fill', $hex );
		self::force_svg_node_colour( $dom->documentElement, $hex );

		$temp = wp_tempnam( 'oc-eps-colour-clipart-' . wp_generate_uuid4() . '.svg' );
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

	/** Force fill/stroke colours throughout an SVG DOM subtree. */
	private static function force_svg_node_colour( \DOMElement $element, string $hex ): void {
		if ( 'style' === strtolower( $element->localName ) ) {
			$element->nodeValue = self::force_svg_css_colour( $element->nodeValue ?? '', $hex );
			return;
		}

		if ( $element->hasAttribute( 'fill' ) && 'none' !== strtolower( trim( $element->getAttribute( 'fill' ) ) ) ) {
			$element->setAttribute( 'fill', $hex );
		}
		if ( $element->hasAttribute( 'stroke' ) && 'none' !== strtolower( trim( $element->getAttribute( 'stroke' ) ) ) ) {
			$element->setAttribute( 'stroke', $hex );
		}
		if ( $element->hasAttribute( 'style' ) ) {
			$element->setAttribute( 'style', self::force_svg_style_colour( $element->getAttribute( 'style' ), $hex ) );
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
				$part     = $property . ':' . $hex;
			}
		}

		return implode( ';', $parts );
	}

	private static function force_svg_css_colour( string $css, string $hex ): string {
		return (string) preg_replace_callback(
			'/\b(fill|stroke)\s*:\s*([^;}]+)/i',
			static function ( array $matches ) use ( $hex ): string {
				$value = strtolower( trim( (string) $matches[2] ) );
				return 'none' === $value ? $matches[0] : $matches[1] . ':' . $hex;
			},
			$css
		);
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
