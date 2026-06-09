<?php
/**
 * Sublimation print file generator.
 *
 * Full-bleed CMYK PDF. Artwork extends to the page edge; text is overlaid.
 * Bleed and crop marks follow the same settings as UV.
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

class OC_Print_Sublimation extends OC_Print_Base {

	/**
	 * Generate a sublimation print PDF.
	 *
	 * @param  \WC_Order $order
	 * @param  int       $item_id
	 * @param  object    $area
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

		$w_mm  = self::px_to_mm( (int) $area->canvas_w );
		$h_mm  = self::px_to_mm( (int) $area->canvas_h );
		$bleed = (float) OC_Admin_Settings::get( 'bleed_mm' ) ?: 3.0;

		$methods_settings = get_option( 'oc_print_methods', [] );
		$full_bleed       = ! empty( $methods_settings['sublimation']['full_bleed'] );

		$pdf = self::make_pdf( $w_mm, $h_mm, $bleed );
		$pdf->SetTitle( sprintf( 'Sublimation — Order #%d — %s', $order->get_id(), $area->label ) );
		$pdf->AddPage();

		$page_w = $w_mm + $bleed * 2;
		$page_h = $h_mm + $bleed * 2;

		// ── Background / full-bleed artwork ───────────────────────────────
		$artwork_path = self::resolve_artwork_path( $area_data );
		if ( $artwork_path ) {
			if ( $full_bleed ) {
				// Artwork bleeds to page edge.
				$pdf->Image( $artwork_path, 0, 0, $page_w, $page_h, '', '', '', false, 300 );
			} else {
				// Artwork inside live area only.
				$pdf->Image( $artwork_path, $bleed, $bleed, $w_mm, $h_mm, '', '', '', false, 300 );
			}
		} else {
			// Solid white background through bleed.
			$pdf->SetFillColorArray( [ 0, 0, 0, 0 ] );
			$pdf->Rect( 0, 0, $page_w, $page_h, 'F' );
		}

		// ── Text overlay ──────────────────────────────────────────────────
		$text = trim( $area_data['text'] ?? '' );
		if ( $text !== '' ) {
			$font_name = self::resolve_font( (int) ( $area_data['fontId'] ?? 0 ) );
			$color     = $area_data['color'] ?? '#000000';
			[ $c, $m, $y, $k ] = self::hex_to_cmyk( $color );

			[ $min_font_size, $max_font_size ] = self::font_size_bounds( $area_data );
			$font_size = self::auto_font_size( $pdf, $text, $font_name, $w_mm, $h_mm, $min_font_size, $max_font_size );
			$pdf->SetFont( $font_name, '', $font_size );
			$pdf->SetTextColorArray( [ $c, $m, $y, $k ] );

			$cell_h = self::cell_h( $font_size );
			$pdf->SetXY( $bleed, $bleed + ( $h_mm - $cell_h ) / 2 );
			$pdf->Cell( $w_mm, $cell_h, $text, 0, 0, 'C' );
		}

		self::draw_crop_marks( $pdf, $w_mm, $h_mm, $bleed );

		$output_dir  = self::ensure_output_dir( $order->get_id() );
		$output_path = $output_dir . '/' . self::build_filename( $item_id, $area->area_key, 'pdf' );

		$pdf->Output( $output_path, 'F' );

		return $output_path;
	}

	// ── Private helpers ───────────────────────────────────────────────────────

	// resolve_font(), auto_font_size(), cell_h() are inherited from OC_Print_Base.
}
