<?php
/**
 * Embroidery print file generator.
 *
 * Tier 1 — Automated: calls the configured pyembroidery Python CLI to
 *           produce a DST file directly.  Returns status 'files_ready'.
 *
 * Tier 2 — Manual fallback: generates a production brief PDF with all
 *           information the embroiderer needs.  Returns status 'awaiting_dst_upload'.
 *
 * Which tier is used is determined at runtime based on Python/pyembroidery
 * availability (configured in plugin settings).
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

class OC_Print_Embroidery extends OC_Print_Base {

	/**
	 * Generate embroidery output — DST (Tier 1) or production brief PDF (Tier 2).
	 *
	 * @param  \WC_Order $order
	 * @param  int       $item_id
	 * @param  object    $area
	 * @param  array     $area_data  {text, fontId, color, artworkAttachmentId}.
	 * @return array{file_path:string, status:string}
	 * @throws \RuntimeException
	 */
	public static function generate(
		\WC_Order $order,
		int $item_id,
		object $area,
		array $area_data
	): array {
		$output_dir = self::ensure_output_dir( $order->get_id() );

		if ( self::tier1_available() ) {
			try {
				$dst_path = self::generate_dst( $output_dir, $item_id, $area, $area_data );
				return [ 'file_path' => $dst_path, 'status' => 'files_ready' ];
			} catch ( \Throwable $e ) {
				OC_Logger::warning( 'Embroidery Tier 1 failed, falling back to Tier 2: ' . $e->getMessage() );
			}
		}

		// Tier 2: production brief.
		$pdf_path = self::generate_brief( $output_dir, $order, $item_id, $area, $area_data );
		return [ 'file_path' => $pdf_path, 'status' => 'awaiting_dst_upload' ];
	}

	/** Whether Tier 1 (pyembroidery CLI) is configured and working. */
	public static function tier1_available(): bool {
		// Respect the admin's explicit preference to always use Tier 2.
		if ( 'force_tier2' === OC_Admin_Settings::get( 'embroidery_fallback' ) ) {
			return false;
		}

		if ( ! function_exists( 'exec' ) ) {
			return false;
		}

		$python = (string) OC_Admin_Settings::get( 'python_binary' ) ?: 'python3';
		$script = (string) OC_Admin_Settings::get( 'pyembroidery_cli_path' );

		if ( empty( $script ) || ! file_exists( $script ) ) {
			return false;
		}

		// Quick sanity check — call script with --version.
		exec( escapeshellcmd( $python ) . ' ' . escapeshellarg( $script ) . ' --version 2>&1', $out, $code );
		return 0 === $code;
	}

	// ── Tier 1: DST generation ─────────────────────────────────────────────

	/**
	 * Call pyembroidery CLI to produce a DST file.
	 *
	 * Expected CLI signature (shop operator configures the script):
	 *   python3 <script> \
	 *     --text     "<text>"   \
	 *     --font     "<path>"   \
	 *     --color    "<hex>"    \
	 *     --width    <mm>       \
	 *     --height   <mm>       \
	 *     --output   "<path>"
	 *
	 * @throws \RuntimeException on CLI failure.
	 */
	private static function generate_dst(
		string $output_dir,
		int $item_id,
		object $area,
		array $area_data
	): string {
		$python  = (string) OC_Admin_Settings::get( 'python_binary' ) ?: 'python3';
		$script  = (string) OC_Admin_Settings::get( 'pyembroidery_cli_path' );
		$dst_path = $output_dir . '/' . self::build_filename( $item_id, $area->area_key, 'dst' );

		$text  = $area_data['text'] ?? '';
		$color = $area_data['color'] ?? '#000000';
		$w_mm  = self::px_to_mm( (int) $area->canvas_w );
		$h_mm  = self::px_to_mm( (int) $area->canvas_h );

		// Resolve font path.
		$font_path = '';
		if ( ! empty( $area_data['fontId'] ) ) {
			$font = self::get_font( (int) $area_data['fontId'] );
			if ( $font ) {
				$font_path = self::get_font_path( $font ) ?? '';
			}
		}

		$cmd = implode( ' ', array_filter( [
			escapeshellcmd( $python ),
			escapeshellarg( $script ),
			'--text',   escapeshellarg( $text ),
			$font_path ? '--font ' . escapeshellarg( $font_path ) : '',
			'--color',  escapeshellarg( $color ),
			'--width',  escapeshellarg( (string) $w_mm ),
			'--height', escapeshellarg( (string) $h_mm ),
			'--output', escapeshellarg( $dst_path ),
		] ) );

		exec( $cmd . ' 2>&1', $output, $return_code );

		if ( 0 !== $return_code || ! file_exists( $dst_path ) ) {
			throw new \RuntimeException(
				'pyembroidery CLI failed (exit ' . $return_code . '): ' . implode( "\n", $output )
			);
		}

		return $dst_path;
	}

	// ── Tier 2: Production brief PDF ──────────────────────────────────────

	/**
	 * Generate a production brief PDF for manual digitising.
	 *
	 * The brief includes:
	 *  - Order number, item name, print area label
	 *  - Text to embroider (large, in the specified font if loadable)
	 *  - Font name, colour swatch, hex code
	 *  - Approximate stitch count estimate
	 *  - Physical print dimensions
	 *  - Thread brand recommendation from settings
	 */
	private static function generate_brief(
		string $output_dir,
		\WC_Order $order,
		int $item_id,
		object $area,
		array $area_data
	): string {
		self::require_tcpdf();

		$w_mm = max( 100.0, self::px_to_mm( (int) $area->canvas_w ) );
		$h_mm = max( 60.0,  self::px_to_mm( (int) $area->canvas_h ) );

		$pdf = self::make_pdf( 210, 297, 0 ); // A4
		$pdf->SetTitle( sprintf( 'Embroidery Brief — Order #%d', $order->get_id() ) );
		$pdf->AddPage();

		// ── Header ─────────────────────────────────────────────────────────
		$pdf->SetFillColor( 30, 30, 30 );
		$pdf->Rect( 0, 0, 210, 20, 'F' );
		$pdf->SetTextColor( 255, 255, 255 );
		$pdf->SetFont( 'helveticaB', 'B', 14 );
		$pdf->SetXY( 10, 4 );
		$pdf->Cell( 190, 12, 'EMBROIDERY PRODUCTION BRIEF', 0, 0, 'C' );

		// ── Order info ─────────────────────────────────────────────────────
		$pdf->SetTextColor( 30, 30, 30 );
		$pdf->SetFont( 'helvetica', '', 10 );
		$pdf->SetXY( 10, 26 );
		$pdf->Cell( 90, 7, 'Order #: ' . $order->get_order_number(), 0 );
		$pdf->SetX( 110 );
		$pdf->Cell( 90, 7, 'Area: ' . $area->label, 0 );
		$pdf->SetXY( 10, 33 );
		$pdf->Cell( 190, 7, 'Customer: ' . $order->get_formatted_billing_full_name(), 0 );

		// ── Text preview ───────────────────────────────────────────────────
		$text = trim( $area_data['text'] ?? '' );
		$pdf->SetXY( 10, 46 );
		$pdf->SetFont( 'helveticaB', 'B', 9 );
		$pdf->Cell( 190, 6, 'TEXT TO EMBROIDER', 0, 2 );

		if ( $text !== '' ) {
			// Try to use the customer's chosen font.
			$font_name = 'helvetica';
			if ( ! empty( $area_data['fontId'] ) ) {
				$font = self::get_font( (int) $area_data['fontId'] );
				if ( $font ) {
					$path = self::get_font_path( $font );
					if ( $path ) {
						$registered = self::register_tcpdf_font( $path );
						if ( $registered ) $font_name = $registered;
					}
				}
			}

			// Auto-size to fit width (max 36pt).
			$preview_size = min( 36.0, max( 12.0, $w_mm * 0.6 ) );
			while ( $preview_size > 6.0 ) {
				$pdf->SetFont( $font_name, '', $preview_size );
				if ( $pdf->GetStringWidth( $text ) <= 175 ) break;
				$preview_size -= 1;
			}

			$preview_y = $pdf->GetY();
			$pdf->SetFillColor( 245, 245, 245 );
			$pdf->Rect( 10, $preview_y, 190, 20, 'F' );
			$pdf->SetTextColor( 30, 30, 30 );
			$pdf->SetXY( 10, $preview_y + ( 20 - $preview_size * 0.3528 * 1.2 ) / 2 );
			$pdf->Cell( 190, $preview_size * 0.3528 * 1.2, $text, 0, 2, 'C' );
			$pdf->SetY( $preview_y + 24 );
		} else {
			$pdf->SetFont( 'helvetica', 'I', 10 );
			$pdf->SetTextColor( 150, 150, 150 );
			$pdf->Cell( 190, 8, '(No text — artwork only)', 0, 2 );
		}

		// ── Specifications ─────────────────────────────────────────────────
		$y = $pdf->GetY() + 4;
		$pdf->SetXY( 10, $y );
		$pdf->SetFont( 'helveticaB', 'B', 9 );
		$pdf->SetTextColor( 30, 30, 30 );
		$pdf->Cell( 190, 6, 'SPECIFICATIONS', 0, 2 );

		$specs = self::build_specs( $area, $area_data, $text, $w_mm, $h_mm );

		$pdf->SetFont( 'helvetica', '', 9 );
		$col_w = 85;
		$col2  = 105;
		$row_h = 7;
		$even  = false;

		foreach ( $specs as $label => $value ) {
			$sy = $pdf->GetY();
			if ( $even ) {
				$pdf->SetFillColor( 248, 248, 248 );
				$pdf->Rect( 10, $sy, 190, $row_h, 'F' );
			}
			$pdf->SetXY( 12, $sy );
			$pdf->SetFont( 'helveticaB', 'B', 9 );
			$pdf->Cell( $col_w - 2, $row_h, $label . ':', 0 );
			$pdf->SetFont( 'helvetica', '', 9 );
			$pdf->SetX( $col2 );
			$pdf->Cell( 95, $row_h, (string) $value, 0, 2 );
			$even = ! $even;
		}

		// ── Colour swatch ─────────────────────────────────────────────────
		$color = $area_data['color'] ?? '#000000';
		[ $r, $g, $b ] = self::hex_to_rgb( $color );

		$sw_y = $pdf->GetY() + 6;
		$pdf->SetXY( 10, $sw_y );
		$pdf->SetFont( 'helveticaB', 'B', 9 );
		$pdf->Cell( 190, 6, 'THREAD COLOUR', 0, 2 );

		$pdf->SetFillColor( $r, $g, $b );
		$pdf->Rect( 12, $pdf->GetY(), 20, 10, 'F' );
		$pdf->SetDrawColor( 180, 180, 180 );
		$pdf->Rect( 12, $pdf->GetY(), 20, 10, 'D' );
		$pdf->SetFont( 'helvetica', '', 9 );
		$pdf->SetXY( 36, $pdf->GetY() + 1 );
		$pdf->SetTextColor( 30, 30, 30 );
		$pdf->Cell( 160, 5, 'Hex: ' . strtoupper( $color ), 0, 2 );

		// ── Footer ─────────────────────────────────────────────────────────
		$pdf->SetXY( 10, 280 );
		$pdf->SetFont( 'helvetica', 'I', 7 );
		$pdf->SetTextColor( 130, 130, 130 );
		$pdf->Cell( 190, 5, 'Generated by OverCustomise — customkings.com.au — Order #' . $order->get_order_number(), 0, 0, 'C' );

		$output_path = $output_dir . '/' . self::build_filename( $item_id, $area->area_key, 'pdf' );
		$pdf->Output( $output_path, 'F' );

		return $output_path;
	}

	/** Build the specs table data for the brief. */
	private static function build_specs( object $area, array $area_data, string $text, float $w_mm, float $h_mm ): array {
		$methods_settings = get_option( 'oc_print_methods', [] );
		$thread_brand     = $methods_settings['embroidery']['thread_brand']  ?? 'Madeira';
		$max_colours      = $methods_settings['embroidery']['max_colours']   ?? 15;

		// Rough stitch count estimate: ~500 stitches per character at normal density.
		$stitch_estimate = $text !== '' ? max( 500, strlen( $text ) * 500 ) : 0;

		$font_name = '';
		if ( ! empty( $area_data['fontId'] ) ) {
			$font = self::get_font( (int) $area_data['fontId'] );
			if ( $font ) $font_name = $font->name;
		}

		return array_filter( [
			'Print Area'       => $area->label,
			'Print Method'     => 'Embroidery',
			'Width'            => number_format( $w_mm, 1 ) . ' mm',
			'Height'           => number_format( $h_mm, 1 ) . ' mm',
			'Font'             => $font_name ?: '—',
			'Thread Brand'     => $thread_brand,
			'Max Colours'      => $max_colours,
			'Est. Stitch Count' => $stitch_estimate > 0 ? number_format( $stitch_estimate ) . ' (approx.)' : '—',
		] );
	}
}
