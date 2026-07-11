<?php
/**
 * UV Printing print file generator.
 *
 * Produces a full-colour CMYK PDF with bleed and crop marks.
 * Optional white ink layer is appended as page 2 if enabled in settings.
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

class OC_Print_UV extends OC_Print_Base {

	/**
	 * Generate a UV print PDF.
	 *
	 * @param  \WC_Order $order
	 * @param  int       $item_id
	 * @param  object    $area       Print area DB row.
	 * @param  array     $area_data  {text, fontId, color, artworkAttachmentId}.
	 * @return string                Absolute path to the generated PDF.
	 * @throws \RuntimeException
	 */
	public static function generate(
		\WC_Order $order,
		int $item_id,
		object $area,
		array $area_data
	): string {
		self::require_tcpdf();

		[ $area, $w_mm, $h_mm ] = self::normalise_uv_area_for_print( $area, $area_data );
		$bleed = (float) OC_Admin_Settings::get( 'bleed_mm' ) ?: 3.0;

		$pdf = self::make_pdf( $w_mm, $h_mm, $bleed );
		$pdf->SetTitle( sprintf( 'UV Print — Order #%d — %s', $order->get_id(), $area->label ) );
		$pdf->AddPage();

		// ── Page 1: Colour artwork ─────────────────────────────────────────
		self::render_colour_page( $pdf, $area, $w_mm, $h_mm, $bleed, $area_data );
		self::draw_crop_marks( $pdf, $w_mm, $h_mm, $bleed );

		// ── Page 2: White ink mask (optional) ─────────────────────────────
		$methods_settings = get_option( 'oc_print_methods', [] );
		if ( ! empty( $methods_settings['uv']['white_ink_layer'] ) ) {
			$white_spot_name = self::resolve_white_spot_name( $methods_settings );
			$pdf->AddPage();
			self::render_white_ink_page( $pdf, $w_mm, $h_mm, $bleed, $white_spot_name, $area_data );
			self::draw_crop_marks( $pdf, $w_mm, $h_mm, $bleed );
		}

		$output_dir  = self::ensure_output_dir( $order->get_id() );
		$output_path = $output_dir . '/' . self::build_filename( $order, $item_id, $area, 'pdf' );

		self::write_pdf_file( $pdf, $output_path );

		return $output_path;
	}

	// ── Private helpers ───────────────────────────────────────────────────────

	/**
	 * UV artwork should be output on the flat print bed, not in the rotated
	 * orientation used to place the area on the product mockup photo.
	 *
	 * @return array{0:object,1:float,2:float}
	 */
	private static function normalise_uv_area_for_print( object $area, array $area_data ): array {
		[ $w_mm, $h_mm ] = self::area_dimensions_mm( $area );

		if ( ! self::has_layer_payload( $area_data ) ) {
			return [ $area, $w_mm, $h_mm ];
		}

		$rotation = (float) ( $area->canvas_rotation ?? ( $area_data['bounds']['rotation'] ?? 0 ) );
		$rotation = abs( fmod( $rotation, 180.0 ) );
		$is_quarter_turn = abs( $rotation - 90.0 ) < 0.001;

		if ( ! $is_quarter_turn || $h_mm <= $w_mm ) {
			return [ $area, $w_mm, $h_mm ];
		}

		$flat_area = clone $area;
		$flat_area->canvas_w = (float) ( $area->canvas_h ?? $area->canvas_w ?? 1 );
		$flat_area->canvas_h = (float) ( $area->canvas_w ?? $area->canvas_h ?? 1 );
		$flat_area->canvas_rotation = 0;

		return [ $flat_area, $h_mm, $w_mm ];
	}

	private static function render_colour_page(
		\TCPDF $pdf,
		object $area,
		float $w_mm,
		float $h_mm,
		float $bleed,
		array $area_data
	): void {
		// Leave the page unpainted so transparent artwork does not gain a white box.

		if ( self::has_layer_payload( $area_data ) ) {
			self::render_layer_payload( $pdf, $area, $area_data, $bleed, $bleed, 'colour' );
			return;
		}

		// Artwork.
		$artwork_path = self::resolve_artwork_path( $area_data );
		if ( $artwork_path ) {
			self::draw_pdf_image( $pdf, $artwork_path, $bleed, $bleed, $w_mm, $h_mm );
		}

		// Text.
		$text = trim( $area_data['text'] ?? '' );
		if ( $text !== '' ) {
			$font_name = self::resolve_font( (int) ( $area_data['fontId'] ?? 0 ), $pdf );
			$color     = $area_data['color'] ?? '#000000';
			[ $c, $m, $y, $k ] = self::hex_to_cmyk( $color );

			[ $min_font_size, $max_font_size ] = self::font_size_bounds( $area_data );
			$font_size = self::auto_font_size( $pdf, $text, $font_name, $w_mm, $h_mm, $min_font_size, $max_font_size );
			$pdf->SetFont( $font_name, '', $font_size );
			$pdf->SetTextColorArray( [ $c, $m, $y, $k ] );

			$cell_h = self::cell_h( $font_size );
			self::draw_clipped_text_cell( $pdf, $bleed, $bleed, $w_mm, $h_mm, $text, $cell_h );
		}
	}

	/**
	 * White ink page: filled solid white wherever content appears.
	 * A single white rectangle covering the whole live area is used as a
	 * base; in production, the operator typically isolates the content layer.
	 */
	private static function render_white_ink_page(
		\TCPDF $pdf,
		float $w_mm,
		float $h_mm,
		float $bleed,
		string $white_spot_name,
		array $area_data
	): void {
		// Black background (represents no white ink).
		$pdf->SetFillColorArray( [ 0, 0, 0, 100 ] );
		$pdf->Rect( 0, 0, $w_mm + $bleed * 2, $h_mm + $bleed * 2, 'F' );

		// White ink area = spot colour only where content exists.
		self::set_spot_fill_colour( $pdf, $white_spot_name );

		$artwork_path = self::resolve_artwork_path( $area_data );
		self::render_artwork_white_mask_spot( $pdf, $artwork_path, $bleed, $w_mm, $h_mm );

		$text = trim( $area_data['text'] ?? '' );
		if ( '' !== $text ) {
			$font_name = self::resolve_font( (int) ( $area_data['fontId'] ?? 0 ), $pdf );
			[ $min_font_size, $max_font_size ] = self::font_size_bounds( $area_data );
			$font_size = self::auto_font_size( $pdf, $text, $font_name, $w_mm, $h_mm, $min_font_size, $max_font_size );

			$pdf->SetFont( $font_name, '', $font_size );
			$cell_h = self::cell_h( $font_size );
			self::draw_clipped_text_cell( $pdf, $bleed, $bleed, $w_mm, $h_mm, $text, $cell_h );
		}

		// Note text for operator.
		$pdf->SetTextColorArray( [ 0, 0, 0, 100 ] );
		$pdf->SetFont( 'helvetica', '', 7 );
		$pdf->SetXY( $bleed, $bleed + $h_mm - 5 );
		$pdf->Cell( $w_mm, 4, 'WHITE INK LAYER: ' . $white_spot_name, 0, 0, 'C' );
	}

	private static function resolve_white_spot_name( array $methods_settings ): string {
		$spot_name = (string) ( $methods_settings['uv']['white_spot_name'] ?? '' );
		$spot_name = sanitize_text_field( $spot_name );
		$spot_name = preg_replace( '/[^A-Za-z0-9_\- ]+/', '', $spot_name );
		$spot_name = trim( (string) $spot_name );

		return '' !== $spot_name ? substr( $spot_name, 0, 64 ) : 'WHITE';
	}

	private static function set_spot_fill_colour( \TCPDF $pdf, string $spot_name ): void {
		if ( method_exists( $pdf, 'AddSpotColor' ) && method_exists( $pdf, 'SetFillColorSpot' ) ) {
			$pdf->AddSpotColor( $spot_name, 0, 0, 0, 0 );
			$pdf->SetFillColorSpot( $spot_name, 100 );
			return;
		}

		// Fallback for older TCPDF builds that do not expose spot APIs.
		$pdf->SetFillColorArray( [ 0, 0, 0, 0 ] );
		$pdf->SetTextColorArray( [ 0, 0, 0, 0 ] );
	}

	/**
	 * Draw artwork alpha as spot-colour white mask.
	 */
	private static function render_artwork_white_mask_spot(
		\TCPDF $pdf,
		string $artwork_path,
		float $bleed,
		float $w_mm,
		float $h_mm
	): void {
		if ( '' === $artwork_path || ! file_exists( $artwork_path ) || ! function_exists( 'imagecreatefromstring' ) ) {
			return;
		}

		$raw = file_get_contents( $artwork_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $raw ) {
			return;
		}

		$img = @imagecreatefromstring( $raw ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( false === $img ) {
			return;
		}

		$img_w = imagesx( $img );
		$img_h = imagesy( $img );
		if ( $img_w < 1 || $img_h < 1 ) {
			imagedestroy( $img );
			return;
		}

		$x_scale = $w_mm / $img_w;
		$y_scale = $h_mm / $img_h;
		$alpha_threshold = 126;

		for ( $y = 0; $y < $img_h; $y++ ) {
			$run_start = -1;
			for ( $x = 0; $x < $img_w; $x++ ) {
				$rgba  = imagecolorat( $img, $x, $y );
				$alpha = ( $rgba & 0x7F000000 ) >> 24;
				$opaque = $alpha <= $alpha_threshold;

				if ( $opaque && -1 === $run_start ) {
					$run_start = $x;
				} elseif ( ! $opaque && -1 !== $run_start ) {
					$run_w = $x - $run_start;
					$pdf->Rect(
						$bleed + $run_start * $x_scale,
						$bleed + $y * $y_scale,
						$run_w * $x_scale,
						$y_scale,
						'F'
					);
					$run_start = -1;
				}
			}

			if ( -1 !== $run_start ) {
				$run_w = $img_w - $run_start;
				$pdf->Rect(
					$bleed + $run_start * $x_scale,
					$bleed + $y * $y_scale,
					$run_w * $x_scale,
					$y_scale,
					'F'
				);
			}
		}

		imagedestroy( $img );
	}

	// resolve_font(), auto_font_size(), cell_h() are inherited from OC_Print_Base.
}
