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
		$bleed = self::configured_bleed_mm();
		$slug  = self::crop_mark_slug_mm( $bleed );
		$live_origin = $slug + $bleed;

		$method_settings = OC_Admin_Print_Methods::get( 'sublimation' );
		$full_bleed      = is_array( $method_settings ) && ! empty( $method_settings['full_bleed'] );

		$pdf = self::make_pdf( $w_mm, $h_mm, $bleed, $slug );
		$pdf->SetTitle( sprintf( 'Sublimation — Order #%d — %s', $order->get_id(), $area->label ) );
		$pdf->AddPage();

		$page_w = $w_mm + $bleed * 2;
		$page_h = $h_mm + $bleed * 2;

		// Leave the page unpainted so transparent artwork does not gain a white box.

		if ( self::has_layer_payload( $area_data ) ) {
			self::render_layer_payload(
				$pdf,
				$area,
				$area_data,
				$live_origin,
				$live_origin,
				'colour',
				[
					'full_bleed_artwork' => $full_bleed,
					'bleed_mm'            => $bleed,
				]
			);
			self::draw_crop_marks( $pdf, $w_mm, $h_mm, $bleed, $slug );

			$output_dir  = self::ensure_output_dir( $order->get_id() );
			$output_path = $output_dir . '/' . self::build_filename( $order, $item_id, $area, 'pdf' );

			self::write_pdf_file( $pdf, $output_path );

			return $output_path;
		}

		if ( self::render_vector_snapshot_payload(
			$pdf,
			$area_data,
			$full_bleed ? $slug : $live_origin,
			$full_bleed ? $slug : $live_origin,
			$full_bleed ? $page_w : $w_mm,
			$full_bleed ? $page_h : $h_mm
		) ) {
			self::draw_crop_marks( $pdf, $w_mm, $h_mm, $bleed, $slug );

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
				self::draw_pdf_image( $pdf, $artwork_path, $slug, $slug, $page_w, $page_h );
			} else {
				// Artwork inside live area only.
				self::draw_pdf_image( $pdf, $artwork_path, $live_origin, $live_origin, $w_mm, $h_mm );
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
			self::draw_clipped_text_cell( $pdf, $live_origin, $live_origin, $w_mm, $h_mm, $text, $cell_h );
		}

		self::draw_crop_marks( $pdf, $w_mm, $h_mm, $bleed, $slug );

		$output_dir  = self::ensure_output_dir( $order->get_id() );
		$output_path = $output_dir . '/' . self::build_filename( $order, $item_id, $area, 'pdf' );

		self::write_pdf_file( $pdf, $output_path );

		return $output_path;
	}

	/**
	 * Generate one sublimation PDF containing multiple print areas as separate pages.
	 *
	 * @param array<int,array{area:object,area_data:array}> $areas
	 */
	public static function generate_combined( \WC_Order $order, int $item_id, array $areas ): string {
		self::require_tcpdf();

		$first = reset( $areas );
		if ( ! is_array( $first ) || ! isset( $first['area'], $first['area_data'] ) ) {
			throw new \RuntimeException( __( 'No sublimation print areas supplied for combined file.', 'overcustomise' ) );
		}

		$bleed = self::configured_bleed_mm();
		$slug  = self::crop_mark_slug_mm( $bleed );
		$live_origin = $slug + $bleed;
		[ $first_area, $first_w_mm, $first_h_mm ] = self::normalise_rotated_artboard_for_print( $first['area'], $first['area_data'] );
		$pdf = self::make_pdf( $first_w_mm, $first_h_mm, $bleed, $slug );
		$pdf->SetTitle( sprintf( 'Sublimation - Order #%d - Combined', $order->get_id() ) );
		$method_settings = OC_Admin_Print_Methods::get( 'sublimation' );
		$full_bleed      = is_array( $method_settings ) && ! empty( $method_settings['full_bleed'] );

		foreach ( $areas as $entry ) {
			if ( ! is_array( $entry ) || ! isset( $entry['area'], $entry['area_data'] ) ) {
				continue;
			}

			[ $area, $w_mm, $h_mm ] = self::normalise_rotated_artboard_for_print( $entry['area'], $entry['area_data'] );
			$page_w = $w_mm + $bleed * 2;
			$page_h = $h_mm + $bleed * 2;
			$pdf_page_w = $page_w + $slug * 2;
			$pdf_page_h = $page_h + $slug * 2;
			$pdf->AddPage( $pdf_page_w > $pdf_page_h ? 'L' : 'P', [ $pdf_page_w, $pdf_page_h ] );

			if ( self::has_layer_payload( $entry['area_data'] ) ) {
				self::render_layer_payload(
					$pdf,
					$area,
					$entry['area_data'],
					$live_origin,
					$live_origin,
					'colour',
					[
						'full_bleed_artwork' => $full_bleed,
						'bleed_mm'            => $bleed,
					]
				);
				self::draw_crop_marks( $pdf, $w_mm, $h_mm, $bleed, $slug );
				continue;
			}

			if ( self::render_vector_snapshot_payload(
				$pdf,
				$entry['area_data'],
				$full_bleed ? $slug : $live_origin,
				$full_bleed ? $slug : $live_origin,
				$full_bleed ? $page_w : $w_mm,
				$full_bleed ? $page_h : $h_mm
			) ) {
				self::draw_crop_marks( $pdf, $w_mm, $h_mm, $bleed, $slug );
				continue;
			}

			$artwork_path     = self::resolve_artwork_path( $entry['area_data'] );
			if ( $artwork_path ) {
				if ( $full_bleed ) {
					self::draw_pdf_image( $pdf, $artwork_path, $slug, $slug, $page_w, $page_h );
				} else {
					self::draw_pdf_image( $pdf, $artwork_path, $live_origin, $live_origin, $w_mm, $h_mm );
				}
			}

			$text = trim( $entry['area_data']['text'] ?? '' );
			if ( '' !== $text ) {
				$font_name = self::resolve_font( (int) ( $entry['area_data']['fontId'] ?? 0 ), $pdf );
				$color     = $entry['area_data']['color'] ?? '#000000';
				[ $c, $m, $y, $k ] = self::hex_to_cmyk( $color );
				[ $min_font_size, $max_font_size ] = self::font_size_bounds( $entry['area_data'] );
				$font_size = self::auto_font_size( $pdf, $text, $font_name, $w_mm, $h_mm, $min_font_size, $max_font_size );
				$pdf->SetFont( $font_name, '', $font_size );
				$pdf->SetTextColorArray( [ $c, $m, $y, $k ] );
				self::draw_clipped_text_cell( $pdf, $live_origin, $live_origin, $w_mm, $h_mm, $text, self::cell_h( $font_size ) );
			}

			self::draw_crop_marks( $pdf, $w_mm, $h_mm, $bleed, $slug );
		}

		$output_dir  = self::ensure_output_dir( $order->get_id() );
		$output_path = $output_dir . '/' . self::build_filename( $order, $item_id, $first_area, 'pdf' );

		self::write_pdf_file( $pdf, $output_path );

		return $output_path;
	}

	// ── Private helpers ───────────────────────────────────────────────────────

	// resolve_font(), auto_font_size(), cell_h() are inherited from OC_Print_Base.
}
