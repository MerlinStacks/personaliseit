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
	 * Generate one engraving PDF containing multiple print areas as separate pages.
	 *
	 * @param array<int,array{area:object,area_data:array}> $areas
	 */
	public static function generate_combined( \WC_Order $order, int $item_id, array $areas ): string {
		self::require_tcpdf();

		$first = reset( $areas );
		if ( ! is_array( $first ) || ! isset( $first['area'], $first['area_data'] ) ) {
			throw new \RuntimeException( __( 'No engraving print areas supplied for combined file.', 'overcustomise' ) );
		}

		[ $first_area, $first_w_mm, $first_h_mm ] = self::normalise_rotated_artboard_for_print( $first['area'], $first['area_data'] );
		$pdf = self::make_pdf( $first_w_mm, $first_h_mm, 0.0 );
		$pdf->SetTitle( sprintf( 'Engraving - Order #%d - Combined', $order->get_id() ) );

		foreach ( $areas as $entry ) {
			if ( ! is_array( $entry ) || ! isset( $entry['area'], $entry['area_data'] ) ) {
				continue;
			}

			[ $area, $w_mm, $h_mm ] = self::normalise_rotated_artboard_for_print( $entry['area'], $entry['area_data'] );
			$pdf->AddPage( $w_mm > $h_mm ? 'L' : 'P', [ $w_mm, $h_mm ] );
			$profile = self::resolve_profile( $area, $entry['area_data'] );

			if ( self::has_layer_payload( $entry['area_data'] ) ) {
				self::render_layer_payload( $pdf, $area, $entry['area_data'], 0.0, 0.0, 'engraving', [ 'engraving_profile' => $profile ] );
				continue;
			}

			$artwork_path = self::resolve_artwork_path( $entry['area_data'] );
			$temp_artwork = null;
			try {
				if ( $artwork_path ) {
					$temp_artwork = self::prepare_artwork_for_layer( $artwork_path, $profile );
					self::draw_pdf_image( $pdf, $temp_artwork, 0, 0, $w_mm, $h_mm );
				}

				$text = self::normalise_engraving_text( trim( $entry['area_data']['text'] ?? '' ) );
				if ( '' !== $text ) {
					$font_name = self::resolve_font( (int) ( $entry['area_data']['fontId'] ?? 0 ), $pdf );
					[ $min_font_size, $max_font_size ] = self::font_size_bounds( $entry['area_data'] );
					$font_size = self::auto_font_size( $pdf, $text, $font_name, $w_mm, $h_mm, $min_font_size, $max_font_size );
					$pdf->SetTextColor( ...self::ENGRAVING_TONE_RGB );
					$pdf->SetFont( $font_name, '', $font_size );
					self::draw_clipped_text_cell( $pdf, 0, 0, $w_mm, $h_mm, $text, self::cell_h( $font_size ) );
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
			'gamma'      => $gamma,
			'contrast'   => $contrast,
			'edge_boost' => $edge_boost,
			'dithering'  => $dithering,
		];
	}

	/** Convert one canonical image/SVG layer through the selected material profile. */
	public static function prepare_artwork_for_layer( string $artwork_path, array $profile ): string {
		$profile = array_merge( self::DEFAULT_PROFILE, $profile );
		$path    = self::build_engraving_raster( $artwork_path, $profile );
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

	private static function build_engraving_raster( string $artwork_path, array $profile ): ?string {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			return null;
		}

		$src = self::open_image_resource( $artwork_path );
		if ( ! $src ) {
			return null;
		}

		$w = imagesx( $src );
		$h = imagesy( $src );
		if ( $w < 1 || $h < 1 ) {
			imagedestroy( $src );
			return null;
		}
		if ( ! self::image_has_engraving_mark( $src ) ) {
			imagedestroy( $src );
			return null;
		}

		$dst = imagecreatetruecolor( $w, $h );
		imagealphablending( $dst, false );
		imagesavealpha( $dst, true );
		$transparent = imagecolorallocatealpha( $dst, 0, 0, 0, 127 );
		imagefilledrectangle( $dst, 0, 0, $w, $h, $transparent );
		imagecopy( $dst, $src, 0, 0, 0, 0, $w, $h );
		imagedestroy( $src );

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
			for ( $i = 0; $i < min( 3, (int) ceil( $edge_boost / 35 ) ); $i++ ) {
				imagefilter( $dst, IMG_FILTER_EDGEDETECT );
				imagefilter( $dst, IMG_FILTER_CONTRAST, -15 );
			}
		}

		if ( 'floyd_steinberg' === ( $profile['dithering'] ?? 'none' ) ) {
			self::apply_floyd_steinberg_dither( $dst );
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

	private static function open_image_resource( string $path ) {
		$ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		if ( 'svg' === $ext ) {
			return self::open_svg_image_resource( $path );
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

		return $image ? self::bounded_gd_resource( $image, 2048, 4000000 ) : false;
	}

	/** Rasterise SVG with a transparent background before material conversion. */
	private static function open_svg_image_resource( string $path ) {
		if ( ! class_exists( '\Imagick' ) || ! function_exists( 'imagecreatefromstring' ) || ! is_readable( $path ) || filesize( $path ) > self::MAX_SVG_BYTES ) {
			return false;
		}

		try {
			$image = new \Imagick();
			self::configure_imagick_limits( $image );
			$image->setBackgroundColor( new \ImagickPixel( 'transparent' ) );
			$image->setResolution( 300, 300 );
			$image->readImage( $path );
			$image->setImageAlphaChannel( \Imagick::ALPHACHANNEL_ACTIVATE );
			$image->setImageFormat( 'png32' );
			[ $width, $height ] = self::bounded_work_dimensions( $image->getImageWidth(), $image->getImageHeight() );
			$image->resizeImage( $width, $height, \Imagick::FILTER_LANCZOS, 1, true );
			$blob = $image->getImageBlob();
			$image->clear();
			$image->destroy();

			$resource = is_string( $blob ) && '' !== $blob ? @imagecreatefromstring( $blob ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return $resource ? self::bounded_gd_resource( $resource, 2048, 4000000 ) : false;
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
