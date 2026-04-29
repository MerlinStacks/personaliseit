<?php
/**
 * Engraving print file generator.
 *
 * Produces a greyscale PDF at the print area dimensions.
 * Text is rendered black on white; artwork is converted to greyscale.
 * No bleed — engraving requires precise trim dimensions.
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

class OC_Print_Engraving extends OC_Print_Base {

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

		$w_mm = self::px_to_mm( (int) $area->canvas_w );
		$h_mm = self::px_to_mm( (int) $area->canvas_h );

		$pdf = self::make_pdf( $w_mm, $h_mm, 0.0 );
		$pdf->SetTitle( sprintf( 'Engraving — Order #%d — %s', $order->get_id(), $area->label ) );
		$pdf->AddPage();

		// White background.
		$pdf->SetFillColor( 255, 255, 255 );
		$pdf->Rect( 0, 0, $w_mm, $h_mm, 'F' );

		// ── Artwork layer ──────────────────────────────────────────────────
		$artwork_path = self::resolve_artwork_path( $area_data );
		if ( $artwork_path ) {
				// Render artwork greyscale, fitted to page.
				$pdf->Image(
					$artwork_path,
					0, 0,
					$w_mm, $h_mm,
					'', '', '', false, 300, '', false, false, 0
				);
		}

		// ── Text layer ─────────────────────────────────────────────────────
		$text = trim( $area_data['text'] ?? '' );
		if ( $text !== '' ) {
			$font_name = self::resolve_font( (int) ( $area_data['fontId'] ?? 0 ) );
			$font_size = self::auto_font_size( $pdf, $text, $font_name, $w_mm, $h_mm );

			$pdf->SetTextColor( 0, 0, 0 );
			$pdf->SetFont( $font_name, '', $font_size );
			$pdf->SetXY( 0, ( $h_mm - self::cell_h( $font_size ) ) / 2 );
			$pdf->Cell( $w_mm, self::cell_h( $font_size ), $text, 0, 0, 'C', false );
		}

		$output_dir  = self::ensure_output_dir( $order->get_id() );
		$output_path = $output_dir . '/' . self::build_filename( $item_id, $area->area_key, 'pdf' );

		$pdf->Output( $output_path, 'F' );

		return $output_path;
	}

	// resolve_font(), auto_font_size(), cell_h() are inherited from OC_Print_Base.
}
