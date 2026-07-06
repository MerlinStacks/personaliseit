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

		[ $w_mm, $h_mm ] = self::area_dimensions_mm( $area );

		$pdf = self::make_pdf( $w_mm, $h_mm, 0.0 );
		$pdf->SetTitle( sprintf( 'Engraving — Order #%d — %s', $order->get_id(), $area->label ) );
		$pdf->AddPage();

		// White background.
		$pdf->SetFillColor( 255, 255, 255 );
		$pdf->Rect( 0, 0, $w_mm, $h_mm, 'F' );

		if ( self::has_layer_payload( $area_data ) ) {
			self::render_layer_payload( $pdf, $area, $area_data, 0.0, 0.0, 'engraving' );

			$output_dir  = self::ensure_output_dir( $order->get_id() );
			$output_path = $output_dir . '/' . self::build_filename( $item_id, $area->area_key, 'pdf' );

			$pdf->Output( $output_path, 'F' );

			return $output_path;
		}

		// ── Artwork layer ──────────────────────────────────────────────────
		$artwork_path = self::resolve_artwork_path( $area_data );
		$temp_artwork = null;
		if ( $artwork_path ) {
			$profile = self::resolve_profile();
			$temp_artwork = self::build_engraving_raster( $artwork_path, $profile );
			$render_path = $temp_artwork ?: $artwork_path;

			// Render artwork greyscale, fitted to page.
				$pdf->Image(
					$render_path,
					0, 0,
					$w_mm, $h_mm,
					'', '', '', false, 300, '', false, false, 0
				);
		}

		// ── Text layer ─────────────────────────────────────────────────────
		$text = trim( $area_data['text'] ?? '' );
		if ( $text !== '' ) {
			$font_name = self::resolve_font( (int) ( $area_data['fontId'] ?? 0 ), $pdf );
			[ $min_font_size, $max_font_size ] = self::font_size_bounds( $area_data );
			$font_size = self::auto_font_size( $pdf, $text, $font_name, $w_mm, $h_mm, $min_font_size, $max_font_size );

			$pdf->SetTextColor( ...self::ENGRAVING_TONE_RGB );
			$pdf->SetFont( $font_name, '', $font_size );
			self::draw_clipped_text_cell( $pdf, 0, 0, $w_mm, $h_mm, $text, self::cell_h( $font_size ) );
		}

		$output_dir  = self::ensure_output_dir( $order->get_id() );
		$output_path = $output_dir . '/' . self::build_filename( $item_id, $area->area_key, 'pdf' );

		$pdf->Output( $output_path, 'F' );

		if ( is_string( $temp_artwork ) && '' !== $temp_artwork && file_exists( $temp_artwork ) ) {
			@unlink( $temp_artwork );
		}

		return $output_path;
	}

	/** @return array<string,mixed> */
	private static function resolve_profile(): array {
		$profile = self::DEFAULT_PROFILE;

		if ( class_exists( 'OC_Admin_Print_Methods' ) ) {
			$method = OC_Admin_Print_Methods::get( 'engraving' );
			if ( is_array( $method ) ) {
				$profile = array_merge( $profile, $method );
			}
		}

		$material = sanitize_key( (string) ( $profile['material'] ?? 'default' ) );
		if ( ! in_array( $material, [ 'default', 'wood', 'glass' ], true ) ) {
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

		if ( 'glass' === $material ) {
			$gamma = 1.0;
			if ( 0 === $edge_boost ) {
				$edge_boost = 25;
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

		$dst = imagecreatetruecolor( $w, $h );
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

		$tmp = self::temp_path( 'oc-engraving-' . wp_generate_uuid4() . '.png' );
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
		return match ( $ext ) {
			'jpg', 'jpeg' => @imagecreatefromjpeg( $path ),
			'png' => @imagecreatefrompng( $path ),
			'webp' => function_exists( 'imagecreatefromwebp' ) ? @imagecreatefromwebp( $path ) : false,
			'bmp' => function_exists( 'imagecreatefrombmp' ) ? @imagecreatefrombmp( $path ) : false,
			'gif' => @imagecreatefromgif( $path ),
			default => false,
		};
	}

	private static function apply_floyd_steinberg_dither( $img ): void {
		$w = imagesx( $img );
		$h = imagesy( $img );

		for ( $y = 0; $y < $h; $y++ ) {
			for ( $x = 0; $x < $w; $x++ ) {
				$rgb = imagecolorat( $img, $x, $y );
				$gray = ( $rgb >> 16 ) & 0xFF;
				$new_gray = $gray < 128 ? 0 : 255;
				$error = $gray - $new_gray;

				$col = imagecolorallocate( $img, $new_gray, $new_gray, $new_gray );
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
		$gray = ( $rgb >> 16 ) & 0xFF;
		$gray = (int) round( max( 0, min( 255, $gray + ( $error * $factor ) ) ) );

		$col = imagecolorallocate( $img, $gray, $gray, $gray );
		imagesetpixel( $img, $x, $y, $col );
	}

	// resolve_font(), auto_font_size(), cell_h() are inherited from OC_Print_Base.
}
