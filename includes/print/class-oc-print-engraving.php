<?php
/**
 * Engraving print file generator.
 *
 * Produces a greyscale PDF at the print area dimensions.
 * Text is rendered in an engraving tone on white; artwork is converted to greyscale.
 * No bleed — engraving requires precise trim dimensions.
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

class OC_Print_Engraving extends OC_Print_Base {

	/** @var array<string,mixed> */
	private const DEFAULT_PROFILE = [
		'material'   => 'default',
		'dpi'        => 600,
		'gamma'      => 2.0,
		'contrast'   => 0,
		'edge_boost' => 0,
		'dithering'  => 'none',
	];

	/**
	 * Generate a greyscale engraving PDF.
	 *
	 * @param  \WC_Order $order
	 * @param  int       $item_id
	 * @param  object    $area       Print area DB row.
	 * @param  array     $area_data  Customisation data for this area: {text, fontId, color, artworkAttachmentId}.
	 * @return string                Absolute path to the generated PDF.
	 * @throws \RuntimeException     On generation failure.
	 */
	public static function generate(
		\WC_Order $order,
		int $item_id,
		object $area,
		array $area_data
	): string {
		self::require_tcpdf();

		[ $area, $w_mm, $h_mm ] = self::normalise_rotated_artboard_for_print( $area, $area_data );

		$pdf = self::make_pdf( $w_mm, $h_mm, 0.0 );
		$pdf->SetTitle( sprintf( 'Engraving — Order #%d — %s', $order->get_id(), $area->label ) );
		$pdf->AddPage();
		$profile = self::resolve_profile( $area, $area_data );

		// Leave the page unpainted so the generated file contains only engraving marks.

		if ( self::has_layer_payload( $area_data ) ) {
			self::render_layer_payload( $pdf, $area, $area_data, 0.0, 0.0, 'engraving', [ 'engraving_profile' => $profile ] );

			$output_dir  = self::ensure_output_dir( $order->get_id() );
			$output_path = $output_dir . '/' . self::build_filename( $order, $item_id, $area, 'pdf' );

			self::write_pdf_file( $pdf, $output_path );

			return $output_path;
		}

		// ── Artwork layer ──────────────────────────────────────────────────
		$artwork_path = self::resolve_artwork_path( $area_data );
		$temp_artwork = null;
		try {
			if ( $artwork_path ) {
				$temp_artwork = self::prepare_artwork_for_layer( $artwork_path, $profile );
				self::draw_pdf_image( $pdf, $temp_artwork, 0, 0, $w_mm, $h_mm );
			}

			$text = self::normalise_engraving_text( trim( $area_data['text'] ?? '' ) );
			if ( $text !== '' ) {
				$font_name = self::resolve_font( (int) ( $area_data['fontId'] ?? 0 ), $pdf );
				[ $min_font_size, $max_font_size ] = self::font_size_bounds( $area_data );
				$font_size = self::auto_font_size( $pdf, $text, $font_name, $w_mm, $h_mm, $min_font_size, $max_font_size );

				$pdf->SetTextColor( ...self::ENGRAVING_TONE_RGB );
				$pdf->SetFont( $font_name, '', $font_size );
				self::draw_clipped_text_cell( $pdf, 0, 0, $w_mm, $h_mm, $text, self::cell_h( $font_size ) );
			}

			$output_dir  = self::ensure_output_dir( $order->get_id() );
			$output_path = $output_dir . '/' . self::build_filename( $order, $item_id, $area, 'pdf' );
			self::write_pdf_file( $pdf, $output_path );
		} finally {
			if ( is_string( $temp_artwork ) && '' !== $temp_artwork && file_exists( $temp_artwork ) ) {
				@unlink( $temp_artwork ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
		}

		return $output_path;
	}

	/**
	 * Generate one engraving PDF with multiple print areas on one production sheet.
	 *
	 * @param array<int,array{area:object,area_data:array}> $areas
	 */
	public static function generate_combined( \WC_Order $order, int $item_id, array $areas ): string {
		self::require_tcpdf();

		$first = reset( $areas );
		if ( ! is_array( $first ) || ! isset( $first['area'], $first['area_data'] ) ) {
			throw new \RuntimeException( __( 'No engraving print areas supplied for combined file.', 'overcustomise' ) );
		}

		$layout = self::combined_sheet_layout( $areas );
		if ( empty( $layout['entries'] ) ) {
			throw new \RuntimeException( __( 'No valid engraving print areas supplied for combined file.', 'overcustomise' ) );
		}
		$first_area = $layout['entries'][0]['area'];
		$pdf = self::make_pdf( $layout['page_w'], $layout['page_h'] );
		$pdf->SetTitle( sprintf( 'Engraving - Order #%d - Combined', $order->get_id() ) );
		$pdf->AddPage();

		foreach ( $layout['entries'] as $entry ) {
			$area   = $entry['area'];
			$w_mm   = $entry['w'];
			$h_mm   = $entry['h'];
			$origin_x = $entry['x'];
			$origin_y = $entry['y'];
			$profile = self::resolve_profile( $area, $entry['area_data'] );

			if ( self::has_layer_payload( $entry['area_data'] ) ) {
				self::render_layer_payload( $pdf, $area, $entry['area_data'], $origin_x, $origin_y, 'engraving', [ 'engraving_profile' => $profile ] );
				continue;
			}

			$artwork_path = self::resolve_artwork_path( $entry['area_data'] );
			$temp_artwork = null;
			try {
				if ( $artwork_path ) {
					$temp_artwork = self::prepare_artwork_for_layer( $artwork_path, $profile );
					self::draw_pdf_image( $pdf, $temp_artwork, $origin_x, $origin_y, $w_mm, $h_mm );
				}

				$text = self::normalise_engraving_text( trim( $entry['area_data']['text'] ?? '' ) );
				if ( '' !== $text ) {
					$font_name = self::resolve_font( (int) ( $entry['area_data']['fontId'] ?? 0 ), $pdf );
					[ $min_font_size, $max_font_size ] = self::font_size_bounds( $entry['area_data'] );
					$font_size = self::auto_font_size( $pdf, $text, $font_name, $w_mm, $h_mm, $min_font_size, $max_font_size );
					$pdf->SetTextColor( ...self::ENGRAVING_TONE_RGB );
					$pdf->SetFont( $font_name, '', $font_size );
					self::draw_clipped_text_cell( $pdf, $origin_x, $origin_y, $w_mm, $h_mm, $text, self::cell_h( $font_size ) );
				}
			} finally {
				if ( is_string( $temp_artwork ) && '' !== $temp_artwork && file_exists( $temp_artwork ) ) {
					@unlink( $temp_artwork ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				}
			}
		}

		$output_dir  = self::ensure_output_dir( $order->get_id() );
		$output_path = $output_dir . '/' . self::build_filename( $order, $item_id, $first_area, 'pdf' );

		self::write_pdf_file( $pdf, $output_path );

		return $output_path;
	}

	/** @return array<string,mixed> */
	private static function resolve_profile( ?object $area = null, array $area_data = [] ): array {
		$profile = self::DEFAULT_PROFILE;

		if ( class_exists( 'OC_Admin_Print_Methods' ) ) {
			$method = OC_Admin_Print_Methods::get( 'engraving' );
			if ( is_array( $method ) ) {
				$profile = array_merge( $profile, $method );
			}
		}

		$snapshot = is_array( $area_data['renderSpecArea'] ?? null ) ? $area_data['renderSpecArea'] : [];
		$area_material = (string) ( $snapshot['engravingMaterial'] ?? $area->engraving_material ?? '' );
		$material = sanitize_key( '' !== $area_material ? $area_material : (string) ( $profile['material'] ?? 'default' ) );
		if ( ! in_array( $material, [ 'default', 'wood', 'glass', 'gold_metal', 'silver_metal', 'silver_plaque', 'black_metal', 'leather' ], true ) ) {
			$material = 'default';
		}

		$gamma      = max( 0.2, min( 4.0, (float) ( $profile['gamma'] ?? 2.0 ) ) );
		$dpi        = max( 72, min( 2400, (int) ( $profile['dpi'] ?? 600 ) ) );
		$contrast   = max( -100, min( 100, (int) ( $profile['contrast'] ?? 0 ) ) );
		$edge_boost = max( 0, min( 100, (int) ( $profile['edge_boost'] ?? 0 ) ) );
		$dithering  = sanitize_key( (string) ( $profile['dithering'] ?? 'none' ) );
		if ( ! in_array( $dithering, [ 'none', 'floyd_steinberg' ], true ) ) {
			$dithering = 'none';
		}

		if ( 'wood' === $material ) {
			$gamma = 1.8;
			if ( 0 === $contrast ) {
				$contrast = 10;
			}
			if ( 'none' === $dithering ) {
				$dithering = 'floyd_steinberg';
			}
		}

		if ( 'leather' === $material ) {
			$gamma = 1.7;
			if ( 0 === $contrast ) {
				$contrast = 15;
			}
			if ( 'none' === $dithering ) {
				$dithering = 'floyd_steinberg';
			}
		}

		if ( 'glass' === $material ) {
			$gamma = 1.0;
			if ( 0 === $edge_boost ) {
				$edge_boost = 25;
			}
			if ( 'none' === $dithering ) {
				$dithering = 'floyd_steinberg';
			}
		}

		if ( 'black_metal' === $material ) {
			$gamma = 1.4;
			if ( 0 === $contrast ) {
				$contrast = 15;
			}
		}

		if ( 'silver_plaque' === $material ) {
			$gamma = 1.25;
			if ( 0 === $contrast ) {
				$contrast = 25;
			}
			if ( 'none' === $dithering ) {
				$dithering = 'floyd_steinberg';
			}
		}

		return [
			'material'   => $material,
			'dpi'        => $dpi,
			'gamma'      => $gamma,
			'contrast'   => $contrast,
			'edge_boost' => $edge_boost,
			'dithering'  => $dithering,
		];
	}

	/** Convert one canonical image/SVG layer through the selected material profile. */
	public static function prepare_artwork_for_layer( string $artwork_path, array $profile, float $width_mm = 0.0, float $height_mm = 0.0 ): string {
		$profile = array_merge( self::DEFAULT_PROFILE, $profile );
		$path    = self::build_engraving_raster( $artwork_path, $profile, $width_mm, $height_mm );
		if ( ! $path ) {
			throw new \RuntimeException(
				sprintf(
					__( 'Artwork "%s" could not be converted into a production-safe engraving mark.', 'overcustomise' ),
					basename( $artwork_path )
				)
			);
		}

		return $path;
	}

	private static function build_engraving_raster( string $artwork_path, array $profile, float $width_mm = 0.0, float $height_mm = 0.0 ): ?string {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			return null;
		}

		[ $target_width, $target_height ] = self::engraving_raster_dimensions( $profile, $width_mm, $height_mm );
		$src                             = self::open_image_resource( $artwork_path, $target_width, $target_height );
		if ( ! $src ) {
			return null;
		}

		$w = imagesx( $src );
		$h = imagesy( $src );
		if ( $w < 1 || $h < 1 ) {
			imagedestroy( $src );
			return null;
		}
		$is_transparent_logo = self::is_transparent_logo( $src );
		if ( ! $is_transparent_logo && ! self::image_has_engraving_mark( $src ) ) {
			imagedestroy( $src );
			return null;
		}

		$target_width  = $target_width > 0 ? $target_width : $w;
		$target_height = $target_height > 0 ? $target_height : $h;
		$dst           = imagecreatetruecolor( $target_width, $target_height );
		imagealphablending( $dst, false );
		imagesavealpha( $dst, true );
		$transparent = imagecolorallocatealpha( $dst, 0, 0, 0, 127 );
		imagefilledrectangle( $dst, 0, 0, $target_width, $target_height, $transparent );
		imagecopyresampled( $dst, $src, 0, 0, 0, 0, $target_width, $target_height, $w, $h );
		imagedestroy( $src );

		if ( $is_transparent_logo ) {
			self::convert_alpha_to_engraving_mark( $dst );
		} else {
			imagefilter( $dst, IMG_FILTER_GRAYSCALE );
			$contrast = (int) ( $profile['contrast'] ?? 0 );
			if ( 0 !== $contrast ) {
				imagefilter( $dst, IMG_FILTER_CONTRAST, -1 * $contrast );
			}

			$gamma = (float) ( $profile['gamma'] ?? 2.0 );
			if ( abs( $gamma - 1.0 ) > 0.001 ) {
				imagegammacorrect( $dst, 1.0, $gamma );
			}

			$edge_boost = (int) ( $profile['edge_boost'] ?? 0 );
			if ( $edge_boost > 0 ) {
				self::apply_unsharp_mask( $dst, $edge_boost );
			}

			if ( 'floyd_steinberg' === ( $profile['dithering'] ?? 'none' ) ) {
				self::apply_floyd_steinberg_dither( $dst );
			}
		}
		if ( ! self::image_has_engraving_mark( $dst ) ) {
			imagedestroy( $dst );
			return null;
		}

		$tmp = self::temp_path_with_extension( 'oc-engraving-' . wp_generate_uuid4() . '.png', 'png' );
		if ( ! is_string( $tmp ) || '' === $tmp ) {
			imagedestroy( $dst );
			return null;
		}

		$result = imagepng( $dst, $tmp );
		imagedestroy( $dst );

		if ( ! $result ) {
			@unlink( $tmp );
			return null;
		}

		return $tmp;
	}

	/** Calculate the final-size engraving raster at the configured machine DPI. */
	private static function engraving_raster_dimensions( array $profile, float $width_mm, float $height_mm ): array {
		if ( $width_mm <= 0.0 || $height_mm <= 0.0 ) {
			return [ 0, 0 ];
		}

		$dpi = max( 72, min( 2400, (int) ( $profile['dpi'] ?? 600 ) ) );
		return self::bounded_work_dimensions(
			max( 1, (int) ceil( $width_mm / 25.4 * $dpi ) ),
			max( 1, (int) ceil( $height_mm / 25.4 * $dpi ) )
		);
	}

	/** Sharpen tonal detail without replacing the photograph with a destructive edge map. */
	private static function apply_unsharp_mask( $image, int $amount ): void {
		if ( ! function_exists( 'imageconvolution' ) ) {
			return;
		}

		$strength = max( 0.0, min( 1.0, $amount / 100 ) );
		$side     = -0.75 * $strength;
		$centre   = 1.0 - ( 4 * $side );
		imageconvolution(
			$image,
			[
				[ 0.0, $side, 0.0 ],
				[ $side, $centre, $side ],
				[ 0.0, $side, 0.0 ],
			],
			1.0,
			0.0
		);
	}

	/**
	 * Detect monochrome artwork whose transparency defines a logo silhouette.
	 *
	 * Logo colours are not production instructions for engraving. In particular,
	 * white-on-transparent logos must use their alpha channel as the mark rather
	 * than becoming invisible or being reduced to an edge map by the glass profile.
	 */
	private static function is_transparent_logo( $image ): bool {
		$width       = imagesx( $image );
		$height      = imagesy( $image );
		$transparent = 0;
		$visible     = 0;
		$colour_samples = 0;
		$minimum_luminance = 255.0;
		$maximum_luminance = 0.0;
		$all_near_white    = true;
		$minimum_red       = 255;
		$maximum_red       = 0;
		$minimum_green     = 255;
		$maximum_green     = 0;
		$minimum_blue      = 255;
		$maximum_blue      = 0;

		for ( $y = 0; $y < $height; $y++ ) {
			for ( $x = 0; $x < $width; $x++ ) {
				$rgba  = imagecolorat( $image, $x, $y );
				$alpha = ( $rgba >> 24 ) & 0x7F;
				if ( $alpha >= 120 ) {
					++$transparent;
					continue;
				}

				++$visible;
				// Very low-opacity antialias fringes describe the silhouette edge,
				// not the logo's production colour.
				if ( $alpha > 96 ) {
					continue;
				}
				++$colour_samples;
				$r = ( $rgba >> 16 ) & 0xFF;
				$g = ( $rgba >> 8 ) & 0xFF;
				$b = $rgba & 0xFF;
				$luminance = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
				$minimum_luminance = min( $minimum_luminance, $luminance );
				$maximum_luminance = max( $maximum_luminance, $luminance );
				$minimum_red       = min( $minimum_red, $r );
				$maximum_red       = max( $maximum_red, $r );
				$minimum_green     = min( $minimum_green, $g );
				$maximum_green     = max( $maximum_green, $g );
				$minimum_blue      = min( $minimum_blue, $b );
				$maximum_blue      = max( $maximum_blue, $b );
				if ( $luminance < 245.0 ) {
					$all_near_white = false;
				}
			}
		}

		if ( 0 === $transparent || 0 === $visible || 0 === $colour_samples ) {
			return false;
		}
		if ( $all_near_white ) {
			return true;
		}

		$minimum_transparent = max( 4, (int) ceil( $width * $height * 0.05 ) );
		return $transparent >= $minimum_transparent
			&& $maximum_luminance - $minimum_luminance <= 24.0
			&& $maximum_red - $minimum_red <= 32
			&& $maximum_green - $minimum_green <= 32
			&& $maximum_blue - $minimum_blue <= 32;
	}

	/** Convert every visible logo pixel to a black mark while preserving alpha. */
	private static function convert_alpha_to_engraving_mark( $image ): void {
		$width  = imagesx( $image );
		$height = imagesy( $image );
		$black  = [];

		for ( $y = 0; $y < $height; $y++ ) {
			for ( $x = 0; $x < $width; $x++ ) {
				$rgba  = imagecolorat( $image, $x, $y );
				$alpha = ( $rgba >> 24 ) & 0x7F;
				if ( ! isset( $black[ $alpha ] ) ) {
					$black[ $alpha ] = imagecolorallocatealpha( $image, 0, 0, 0, $alpha );
				}
				imagesetpixel( $image, $x, $y, $black[ $alpha ] );
			}
		}
	}

	private static function open_image_resource( string $path, int $target_width = 0, int $target_height = 0 ) {
		$ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		if ( 'svg' === $ext ) {
			return self::open_svg_image_resource( $path, $target_width, $target_height );
		}
		self::assert_safe_raster_dimensions( $path );
		$image = match ( $ext ) {
			'jpg', 'jpeg' => @imagecreatefromjpeg( $path ),
			'png' => @imagecreatefrompng( $path ),
			'webp' => function_exists( 'imagecreatefromwebp' ) ? @imagecreatefromwebp( $path ) : false,
			'bmp' => function_exists( 'imagecreatefrombmp' ) ? @imagecreatefrombmp( $path ) : false,
			'gif' => @imagecreatefromgif( $path ),
			default => false,
		};

		return $image ? self::bounded_gd_resource( $image, self::MAX_WORK_RASTER_DIMENSION, self::MAX_WORK_RASTER_PIXELS ) : false;
	}

	/** Rasterise SVG with a transparent background before material conversion. */
	private static function open_svg_image_resource( string $path, int $target_width = 0, int $target_height = 0 ) {
		if ( ! class_exists( '\Imagick' ) || ! function_exists( 'imagecreatefromstring' ) || ! is_readable( $path ) || filesize( $path ) > self::MAX_SVG_BYTES ) {
			return false;
		}

		try {
			$image = new \Imagick();
			self::configure_imagick_limits( $image );
			$image->setBackgroundColor( new \ImagickPixel( 'transparent' ) );
			$image->setResolution( 600, 600 );
			$image->readImage( $path );
			$image->setImageAlphaChannel( \Imagick::ALPHACHANNEL_ACTIVATE );
			$image->setImageFormat( 'png32' );
			[ $width, $height ] = $target_width > 0 && $target_height > 0
				? self::bounded_work_dimensions( $target_width, $target_height )
				: self::bounded_work_dimensions( $image->getImageWidth(), $image->getImageHeight() );
			$image->resizeImage( $width, $height, \Imagick::FILTER_LANCZOS, 1, true );
			$blob = $image->getImageBlob();
			$image->clear();
			$image->destroy();

			$resource = is_string( $blob ) && '' !== $blob ? @imagecreatefromstring( $blob ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return $resource ? self::bounded_gd_resource( $resource, self::MAX_WORK_RASTER_DIMENSION, self::MAX_WORK_RASTER_PIXELS ) : false;
		} catch ( \Throwable $e ) {
			OC_Logger::warning( 'Engraving SVG conversion failed: ' . $e->getMessage() );
			return false;
		}
	}

	/** Reject transparent and opaque white/near-white artwork with no engraving mark. */
	private static function image_has_engraving_mark( $image ): bool {
		$width  = imagesx( $image );
		$height = imagesy( $image );
		for ( $y = 0; $y < $height; $y++ ) {
			for ( $x = 0; $x < $width; $x++ ) {
				$rgba  = imagecolorat( $image, $x, $y );
				$alpha = ( $rgba >> 24 ) & 0x7F;
				if ( $alpha >= 120 ) {
					continue;
				}
				$r = ( $rgba >> 16 ) & 0xFF;
				$g = ( $rgba >> 8 ) & 0xFF;
				$b = $rgba & 0xFF;
				$luminance = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
				if ( $luminance < 245.0 ) {
					return true;
				}
			}
		}

		return false;
	}

	private static function apply_floyd_steinberg_dither( $img ): void {
		$w = imagesx( $img );
		$h = imagesy( $img );

		for ( $y = 0; $y < $h; $y++ ) {
			for ( $x = 0; $x < $w; $x++ ) {
				$rgb = imagecolorat( $img, $x, $y );
				$alpha = ( $rgb >> 24 ) & 0x7F;
				if ( $alpha >= 120 ) {
					continue;
				}
				$gray = ( $rgb >> 16 ) & 0xFF;
				$new_gray = $gray < 128 ? 0 : 255;
				$error = $gray - $new_gray;

				$col = imagecolorallocatealpha( $img, $new_gray, $new_gray, $new_gray, $alpha );
				imagesetpixel( $img, $x, $y, $col );

				self::dither_spread( $img, $x + 1, $y,     $error, 7 / 16 );
				self::dither_spread( $img, $x - 1, $y + 1, $error, 3 / 16 );
				self::dither_spread( $img, $x,     $y + 1, $error, 5 / 16 );
				self::dither_spread( $img, $x + 1, $y + 1, $error, 1 / 16 );
			}
		}
	}

	private static function dither_spread( $img, int $x, int $y, int $error, float $factor ): void {
		$w = imagesx( $img );
		$h = imagesy( $img );
		if ( $x < 0 || $y < 0 || $x >= $w || $y >= $h ) {
			return;
		}

		$rgb = imagecolorat( $img, $x, $y );
		$alpha = ( $rgb >> 24 ) & 0x7F;
		if ( $alpha >= 120 ) {
			return;
		}
		$gray = ( $rgb >> 16 ) & 0xFF;
		$gray = (int) round( max( 0, min( 255, $gray + ( $error * $factor ) ) ) );

		$col = imagecolorallocatealpha( $img, $gray, $gray, $gray, $alpha );
		imagesetpixel( $img, $x, $y, $col );
	}

	// resolve_font(), auto_font_size(), cell_h() are inherited from OC_Print_Base.
}
