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

		[ $area, $w_mm, $h_mm ] = self::normalise_rotated_artboard_for_print( $area, $area_data );
		$bleed = (float) OC_Admin_Settings::get( 'bleed_mm' ) ?: 3.0;

		$methods_settings = get_option( 'oc_print_methods', [] );
		$full_bleed       = ! empty( $methods_settings['sublimation']['full_bleed'] );

		$pdf = self::make_pdf( $w_mm, $h_mm, $bleed );
		$pdf->SetTitle( sprintf( 'Sublimation — Order #%d — %s', $order->get_id(), $area->label ) );
		$pdf->AddPage();

		$page_w = $w_mm + $bleed * 2;
		$page_h = $h_mm + $bleed * 2;

		// Leave the page unpainted so transparent artwork does not gain a white box.

		if ( self::render_vector_snapshot_payload( $pdf, $area_data, $bleed, $bleed, $w_mm, $h_mm ) ) {
			self::draw_crop_marks( $pdf, $w_mm, $h_mm, $bleed );

			$output_dir  = self::ensure_output_dir( $order->get_id() );
			$output_path = $output_dir . '/' . self::build_filename( $order, $item_id, $area, 'pdf' );

			self::write_pdf_file( $pdf, $output_path );

			return $output_path;
		}

		if ( self::has_layer_payload( $area_data ) ) {
			self::render_layer_payload( $pdf, $area, $area_data, $bleed, $bleed, 'colour' );
			self::draw_crop_marks( $pdf, $w_mm, $h_mm, $bleed );

			$output_dir  = self::ensure_output_dir( $order->get_id() );
			$output_path = $output_dir . '/' . self::build_filename( $order, $item_id, $area, 'pdf' );

			self::write_pdf_file( $pdf, $output_path );

			return $output_path;
		}

		// ── Background / full-bleed artwork ───────────────────────────────
		$artwork_path = self::resolve_artwork_path( $area_data );
		if ( $artwork_path ) {
			if ( $full_bleed ) {
				// Artwork bleeds to page edge.
				self::draw_pdf_image( $pdf, $artwork_path, 0, 0, $page_w, $page_h );
			} else {
				// Artwork inside live area only.
				self::draw_pdf_image( $pdf, $artwork_path, $bleed, $bleed, $w_mm, $h_mm );
			}
		}

		// ── Text overlay ──────────────────────────────────────────────────
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

		self::draw_crop_marks( $pdf, $w_mm, $h_mm, $bleed );

		$output_dir  = self::ensure_output_dir( $order->get_id() );
		$output_path = $output_dir . '/' . self::build_filename( $order, $item_id, $area, 'pdf' );

		self::write_pdf_file( $pdf, $output_path );

		return $output_path;
	}

	// ── Private helpers ───────────────────────────────────────────────────────

	// resolve_font(), auto_font_size(), cell_h() are inherited from OC_Print_Base.
}
