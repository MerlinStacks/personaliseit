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

		$w_mm  = self::px_to_mm( (int) $area->canvas_w );
		$h_mm  = self::px_to_mm( (int) $area->canvas_h );
		$bleed = (float) OC_Admin_Settings::get( 'bleed_mm' ) ?: 3.0;

		$pdf = self::make_pdf( $w_mm, $h_mm, $bleed );
		$pdf->SetTitle( sprintf( 'UV Print — Order #%d — %s', $order->get_id(), $area->label ) );
		$pdf->AddPage();

		// ── Page 1: Colour artwork ─────────────────────────────────────────
		self::render_colour_page( $pdf, $w_mm, $h_mm, $bleed, $area_data );
		self::draw_crop_marks( $pdf, $w_mm, $h_mm, $bleed );

		// ── Page 2: White ink mask (optional) ─────────────────────────────
		$methods_settings = get_option( 'oc_print_methods', [] );
		if ( ! empty( $methods_settings['uv']['white_ink_layer'] ) ) {
			$pdf->AddPage();
			self::render_white_ink_page( $pdf, $w_mm, $h_mm, $bleed, $area_data );
			self::draw_crop_marks( $pdf, $w_mm, $h_mm, $bleed );
		}

		$output_dir  = self::ensure_output_dir( $order->get_id() );
		$output_path = $output_dir . '/' . self::build_filename( $item_id, $area->area_key, 'pdf' );

		$pdf->Output( $output_path, 'F' );

		return $output_path;
	}

	// ── Private helpers ───────────────────────────────────────────────────────

	private static function render_colour_page(
		\TCPDF $pdf,
		float $w_mm,
		float $h_mm,
		float $bleed,
		array $area_data
	): void {
		$page_w = $w_mm + $bleed * 2;
		$page_h = $h_mm + $bleed * 2;

		// White background extending through bleed.
		$pdf->SetFillColorArray( [ 0, 0, 0, 0 ] ); // white CMYK
		$pdf->Rect( 0, 0, $page_w, $page_h, 'F' );

		// Artwork.
		$artwork_path = self::resolve_artwork_path( $area_data );
		if ( $artwork_path ) {
			$pdf->Image( $artwork_path, $bleed, $bleed, $w_mm, $h_mm, '', '', '', false, 300 );
		}

		// Text.
		$text = trim( $area_data['text'] ?? '' );
		if ( $text !== '' ) {
			$font_name = self::resolve_font( (int) ( $area_data['fontId'] ?? 0 ) );
			$color     = $area_data['color'] ?? '#000000';
			[ $c, $m, $y, $k ] = self::hex_to_cmyk( $color );

			$font_size = self::auto_font_size( $pdf, $text, $font_name, $w_mm, $h_mm );
			$pdf->SetFont( $font_name, '', $font_size );
			$pdf->SetTextColorArray( [ $c, $m, $y, $k ] );

			$cell_h = self::cell_h( $font_size );
			$pdf->SetXY( $bleed, $bleed + ( $h_mm - $cell_h ) / 2 );
			$pdf->Cell( $w_mm, $cell_h, $text, 0, 0, 'C' );
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
		array $area_data
	): void {
		// Black background (represents no white ink).
		$pdf->SetFillColorArray( [ 0, 0, 0, 100 ] );
		$pdf->Rect( 0, 0, $w_mm + $bleed * 2, $h_mm + $bleed * 2, 'F' );

		// White ink area = filled white over the live area.
		$pdf->SetFillColorArray( [ 0, 0, 0, 0 ] ); // white CMYK
		$pdf->Rect( $bleed, $bleed, $w_mm, $h_mm, 'F' );

		// Note text for operator.
		$pdf->SetTextColorArray( [ 0, 0, 0, 100 ] );
		$pdf->SetFont( 'helvetica', '', 7 );
		$pdf->SetXY( $bleed, $bleed + $h_mm - 5 );
		$pdf->Cell( $w_mm, 4, 'WHITE INK LAYER', 0, 0, 'C' );
	}

	// resolve_font(), auto_font_size(), cell_h() are inherited from OC_Print_Base.
}
