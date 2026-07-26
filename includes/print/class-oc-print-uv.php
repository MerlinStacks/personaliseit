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

		[ $area, $w_mm, $h_mm ] = self::normalise_rotated_artboard_for_print( $area, $area_data );
		$bleed = self::configured_bleed_mm();
		$slug  = self::crop_mark_slug_mm( $bleed );
		$live_origin = $slug + $bleed;

		$pdf = self::make_pdf( $w_mm, $h_mm, $bleed, $slug );
		$pdf->SetTitle( sprintf( 'UV Print — Order #%d — %s', $order->get_id(), $area->label ) );
		$pdf->AddPage();

		// ── Page 1: Colour artwork ─────────────────────────────────────────
		self::render_colour_page( $pdf, $area, $w_mm, $h_mm, $live_origin, $live_origin, $area_data );
		self::draw_crop_marks( $pdf, $w_mm, $h_mm, $bleed, $slug );

		// ── Page 2: White ink mask (optional) ─────────────────────────────
		$method_settings = OC_Admin_Print_Methods::get( 'uv' );
		if ( is_array( $method_settings ) && ! empty( $method_settings['white_ink_layer'] ) ) {
			$white_spot_name = self::resolve_white_spot_name( $method_settings );
			$pdf->AddPage();
			self::render_white_ink_page( $pdf, $area, $w_mm, $h_mm, $live_origin, $live_origin, $white_spot_name, $area_data );
		}

		$output_dir  = self::ensure_output_dir( $order->get_id() );
		$output_path = $output_dir . '/' . self::build_filename( $order, $item_id, $area, 'pdf' );

		self::write_pdf_file( $pdf, $output_path );

		return $output_path;
	}

	/**
	 * Generate one UV PDF with multiple print areas laid out on a production sheet.
	 *
	 * @param array<int,array{area:object,area_data:array}> $areas
	 */
	public static function generate_combined( \WC_Order $order, int $item_id, array $areas ): string {
		self::require_tcpdf();

		$first = reset( $areas );
		if ( ! is_array( $first ) || ! isset( $first['area'], $first['area_data'] ) ) {
			throw new \RuntimeException( __( 'No UV print areas supplied for combined file.', 'overcustomise' ) );
		}

		$bleed = self::configured_bleed_mm();
		$slug  = self::crop_mark_slug_mm( $bleed );
		$inset  = $slug + $bleed;
		$layout = self::combined_sheet_layout( $areas, $inset );
		if ( empty( $layout['entries'] ) ) {
			throw new \RuntimeException( __( 'No valid UV print areas supplied for combined file.', 'overcustomise' ) );
		}
		$first_area = $layout['entries'][0]['area'];
		$pdf = self::make_pdf( $layout['page_w'], $layout['page_h'] );
		$pdf->SetTitle( sprintf( 'UV Print - Order #%d - Combined', $order->get_id() ) );

		$method_settings = OC_Admin_Print_Methods::get( 'uv' );
		$add_white_page  = is_array( $method_settings ) && ! empty( $method_settings['white_ink_layer'] );
		$white_spot_name = $add_white_page ? self::resolve_white_spot_name( $method_settings ) : '';

		$pdf->AddPage();
		foreach ( $layout['entries'] as $entry ) {
			self::render_colour_page( $pdf, $entry['area'], $entry['w'], $entry['h'], $entry['x'], $entry['y'], $entry['area_data'] );
			self::draw_crop_marks( $pdf, $entry['w'], $entry['h'], $bleed, $slug, $entry['x'] - $inset, 0.0 );
		}

		if ( $add_white_page ) {
			$pdf->AddPage();
			foreach ( $layout['entries'] as $entry ) {
				self::render_white_ink_page( $pdf, $entry['area'], $entry['w'], $entry['h'], $entry['x'], $entry['y'], $white_spot_name, $entry['area_data'] );
			}
		}

		$output_dir  = self::ensure_output_dir( $order->get_id() );
		$output_path = $output_dir . '/' . self::build_filename( $order, $item_id, $first_area, 'pdf' );

		self::write_pdf_file( $pdf, $output_path );

		return $output_path;
	}

	// ── Private helpers ───────────────────────────────────────────────────────

	private static function render_colour_page(
		\TCPDF $pdf,
		object $area,
		float $w_mm,
		float $h_mm,
		float $origin_x,
		float $origin_y,
		array $area_data
	): void {
		// Leave the page unpainted so transparent artwork does not gain a white box.

		if ( self::has_layer_payload( $area_data ) ) {
			self::render_layer_payload( $pdf, $area, $area_data, $origin_x, $origin_y, 'colour' );
			return;
		}

		if ( self::render_vector_snapshot_payload( $pdf, $area_data, $origin_x, $origin_y, $w_mm, $h_mm ) ) {
			return;
		}

		// Artwork.
		$artwork_path = self::resolve_artwork_path( $area_data );
		if ( $artwork_path ) {
			self::draw_pdf_image( $pdf, $artwork_path, $origin_x, $origin_y, $w_mm, $h_mm );
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
			self::draw_clipped_text_cell( $pdf, $origin_x, $origin_y, $w_mm, $h_mm, $text, $cell_h );
		}
	}

	/**
	 * White ink page: filled solid white wherever content appears.
	 * A single white rectangle covering the whole live area is used as a
	 * base; in production, the operator typically isolates the content layer.
	 */
	private static function render_white_ink_page(
		\TCPDF $pdf,
		object $area,
		float $w_mm,
		float $h_mm,
		float $origin_x,
		float $origin_y,
		string $white_spot_name,
		array $area_data
	): void {
		// The spot page contains no process-colour background or operator notes.
		self::set_spot_fill_colour( $pdf, $white_spot_name );

		if ( self::has_layer_payload( $area_data ) ) {
			self::render_layer_payload( $pdf, $area, $area_data, $origin_x, $origin_y, 'spot' );
			return;
		}

		if ( self::render_vector_snapshot_spot_mask( $pdf, $area_data, $origin_x, $origin_y, $w_mm, $h_mm ) ) {
			return;
		}

		$artwork_path = self::resolve_artwork_path( $area_data );
		if ( $artwork_path ) {
			self::render_artwork_spot_mask( $pdf, $artwork_path, $origin_x, $origin_y, $w_mm, $h_mm );
		}

		$text = trim( $area_data['text'] ?? '' );
		if ( '' !== $text ) {
			$font_name = self::resolve_font( (int) ( $area_data['fontId'] ?? 0 ), $pdf );
			[ $min_font_size, $max_font_size ] = self::font_size_bounds( $area_data );
			$font_size = self::auto_font_size( $pdf, $text, $font_name, $w_mm, $h_mm, $min_font_size, $max_font_size );

			$pdf->SetFont( $font_name, '', $font_size );
			$cell_h = self::cell_h( $font_size );
			self::draw_clipped_text_cell( $pdf, $origin_x, $origin_y, $w_mm, $h_mm, $text, $cell_h );
		}
	}

	private static function resolve_white_spot_name( array $method_settings ): string {
		$spot_name = (string) ( $method_settings['white_spot_name'] ?? '' );
		$spot_name = sanitize_text_field( $spot_name );
		$spot_name = preg_replace( '/[^A-Za-z0-9_\- ]+/', '', $spot_name );
		$spot_name = trim( (string) $spot_name );

		return '' !== $spot_name ? substr( $spot_name, 0, 64 ) : 'WHITE';
	}

	private static function set_spot_fill_colour( \TCPDF $pdf, string $spot_name ): void {
		if ( ! method_exists( $pdf, 'AddSpotColor' ) || ! method_exists( $pdf, 'setFillSpotColor' ) || ! method_exists( $pdf, 'setTextSpotColor' ) ) {
			throw new \RuntimeException( __( 'The bundled TCPDF spot-colour APIs are unavailable; a true white-ink plate cannot be generated.', 'overcustomise' ) );
		}

		$pdf->AddSpotColor( $spot_name, 0, 0, 0, 0 );
		$pdf->setFillSpotColor( $spot_name, 100 );
		$pdf->setTextSpotColor( $spot_name, 100 );
	}

	// resolve_font(), auto_font_size(), cell_h() are inherited from OC_Print_Base.
}
