<?php
/**
 * Shared text helpers for print file generators.
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

trait OC_Print_Base_Text {

	/**
	 * Auto-size font to fit text within the live area width (max ~40% of height).
	 *
	 * @param \TCPDF $pdf
	 * @param string $text
	 * @param string $font_name  Already-registered TCPDF font name.
	 * @param float  $w_mm       Live area width in mm.
	 * @param float  $h_mm       Live area height in mm.
	 * @return float             Font size in points.
	 */
	protected static function auto_font_size( \TCPDF $pdf, string $text, string $font_name, float $w_mm, float $h_mm, float $min_size = 0.0, float $max_size = 0.0 ): float {
		$min_size = max( 4.0, $min_size );
		$max_size = max( 0.0, $max_size );
		$size     = max( 8.0, $h_mm * 0.4 );

		if ( $max_size > 0.0 ) {
			$size = min( $size, $max_size );
		}
		if ( $min_size > $size ) {
			$size = $min_size;
		}

		while ( $size > $min_size ) {
			$pdf->SetFont( $font_name, '', $size );
			if ( self::text_fits_box( $pdf, $text, $w_mm * 0.92, $h_mm, $size ) ) {
				break;
			}
			$size -= 0.5;
		}

		return $size;
	}

	/** Resolve optional font-size bounds from generated area data. */
	protected static function font_size_bounds( array $area_data ): array {
		return [
			(float) max( 0, absint( $area_data['minFontSize'] ?? 0 ) ),
			(float) max( 0, absint( $area_data['maxFontSize'] ?? 0 ) ),
		];
	}

	/**
	 * Convert font size in points to an appropriate cell height in mm.
	 * Uses 1.2× line height.
	 */
	protected static function cell_h( float $font_size_pt ): float {
		return $font_size_pt * 0.3528 * 1.2; // 1pt = 0.3528mm
	}

	/** Return true when the current font size fits within the supplied box. */
	protected static function text_fits_box(
		\TCPDF $pdf,
		string $text,
		float $w_mm,
		float $h_mm,
		float $font_size_pt,
		bool $multiline = false
	): bool {
		$cell_h = self::cell_h( $font_size_pt );
		if ( $multiline ) {
			$lines = max( 1, (int) $pdf->getNumLines( $text, $w_mm ) );

			return $lines * $cell_h <= $h_mm;
		}

		return $pdf->GetStringWidth( $text ) <= $w_mm && $cell_h <= $h_mm;
	}

	/** Draw text constrained to the supplied box. */
	protected static function draw_clipped_text_cell(
		\TCPDF $pdf,
		float $x_mm,
		float $y_mm,
		float $w_mm,
		float $h_mm,
		string $text,
		float $cell_h,
		string $align = 'C',
		string $valign = 'C',
		bool $multiline = false
	): void {
		if ( $multiline ) {
			$line_count = max( 1, (int) $pdf->getNumLines( $text, $w_mm ) );
			$content_h = min( $h_mm, $line_count * $cell_h );
		} else {
			$content_h = $cell_h;
		}

		$offset_y = match ( $valign ) {
			'T' => 0.0,
			'B' => max( 0.0, $h_mm - $content_h ),
			default => max( 0.0, ( $h_mm - $content_h ) / 2 ),
		};

		$pdf->StartTransform();
		$pdf->Rect( $x_mm, $y_mm, $w_mm, $h_mm, 'CNZ' );
		$pdf->SetXY( $x_mm, $y_mm + $offset_y );
		if ( $multiline ) {
			$pdf->MultiCell( $w_mm, $cell_h, $text, 0, $align, false, 1, $x_mm, $y_mm + $offset_y );
		} else {
			$pdf->Cell( $w_mm, $cell_h, $text, 0, 0, $align, false, '', 1 );
		}
		$pdf->StopTransform();
	}

	/** Remove emoji that cannot be reproduced as engraving or thread-colour output. */
	protected static function normalise_engraving_text( string $text ): string {
		if ( '' === $text ) {
			return '';
		}

		$text = preg_replace( '/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}][\x{FE0E}\x{FE0F}]?/u', '', $text ) ?? $text;

		return preg_replace( '/[\x{1F3FB}-\x{1F3FF}\x{1F9B0}-\x{1F9B3}\x{200D}\x{FE0E}\x{FE0F}]/u', '', $text ) ?? $text;
	}

	private static function render_layer_text( \TCPDF $pdf, array $layer, array $input, array $settings, float $x_mm, float $y_mm, float $w_mm, float $h_mm, string $mode, ?float $font_px_to_pt = null ): void {
		foreach ( [ $x_mm, $y_mm, $w_mm, $h_mm, $font_px_to_pt ?? self::px_to_pt( 1.0 ), $layer['h'] ?? 1, $input['fontSize'] ?? $settings['default_font_size'] ?? 0, $settings['min_font_size'] ?? 0, $settings['max_font_size'] ?? 0 ] as $geometry ) {
			if ( ! is_numeric( $geometry ) || ! is_finite( (float) $geometry ) || ! is_finite( (float) $geometry * 72 ) ) {
				throw new \RuntimeException( 'Non-finite text geometry.' );
			}
		}
		$verified = self::browser_rendered_text_layout( $input, $layer, $settings );
		if ( null === $verified && array_key_exists( 'renderedLayoutVersion', $input ) ) {
			// A versioned textarea's lines and geometry are one indivisible bundle.
			unset( $input['renderedFontSize'], $input['renderedScaleX'], $input['renderedInsetX'], $input['renderedLines'] );
		}
		$is_textarea = 'textarea' === (string) ( $layer['type'] ?? '' );
		$text        = str_replace( [ "\r\n", "\r" ], "\n", (string) ( $input['value'] ?? '' ) );
		// Empty textarea lines are layout slots, including at the block's edges.
		$text        = $is_textarea || ( null !== $verified && 'engraving' === $mode ) ? $text : trim( $text );
		if ( '' === trim( $text ) ) {
			return;
		}
		if ( 'engraving' === $mode && null === $verified ) {
			$text = self::normalise_engraving_text( $text );
		}
		$rendered_lines = $is_textarea ? ( $verified['renderedLines'] ?? self::browser_rendered_text_lines( $input, $text ) ) : null;
		$render_text    = null !== $rendered_lines ? implode( "\n", $rendered_lines ) : $text;

		$font_id           = ! empty( $input['fontId'] ) ? (int) $input['fontId'] : (int) ( $settings['default_font_id'] ?? 0 );
		$font              = $font_id ? self::get_font( $font_id ) : null;
		$verified_fallback = false;
		if ( null !== $verified && 'engraving' === $mode ) {
			$conversion = $font_px_to_pt ?? self::px_to_pt( 1.0 );
			$size       = $verified['renderedFontSize'] * $conversion;
			$inset      = $w_mm * $verified['renderedInsetX'];
			$draw_width = $w_mm - 2 * $inset;
			if ( $w_mm <= 0 || $h_mm <= 0 || $conversion <= 0 || ! is_finite( $size ) || $size <= 0 || ! is_finite( $draw_width ) || $draw_width <= 0
				|| ! is_finite( $x_mm + $inset ) || ! is_finite( $x_mm + $w_mm ) || ! is_finite( $y_mm + $h_mm ) ) {
				throw new \RuntimeException( 'Invalid verified text geometry.' );
			}
			$align  = strtoupper( substr( (string) ( $settings['alignment'] ?? 'center' ), 0, 1 ) );
			$align  = in_array( $align, [ 'L', 'C', 'R' ], true ) ? $align : 'C';
			$valign = match ( $settings['line_alignment'] ?? 'top' ) {
				'top' => 'T',
				'bottom' => 'B',
				default => 'C'
			};
			$temporary_font = null;
			$transaction    = false;
			try {
				$path = self::verified_outline_font_path( $font, $temporary_font );
				$pdf->startTransaction();
				$transaction = true;
				$ok          = $is_textarea
					? self::render_engraving_multiline_text_outline( $pdf, $render_text, $path, $size, $x_mm + $inset, $y_mm, $draw_width, $h_mm, $align, $valign, $rendered_lines, true )
					: self::render_engraving_text_outline( $pdf, $render_text, $path, $size, $x_mm, $y_mm, $w_mm, $h_mm, $align, $verified['renderedScaleX'] );
				if ( ! $ok ) {
					throw new \RuntimeException( 'Verified text layout could not be faithfully outlined.' );
				}
				$pdf->commitTransaction();
				return;
			} catch ( \Throwable $e ) {
				if ( $transaction ) {
					$pdf->rollbackTransaction( true );
				}
				OC_Logger::warning( 'Verified text layout fallback to existing companion/raster/PDF rendering: ' . $e->getMessage() );
				$verified_fallback = true;
				$verified          = null;
				unset( $input['renderedFontSize'], $input['renderedScaleX'], $input['renderedInsetX'], $input['renderedLines'] );
				$text           = self::normalise_engraving_text( $is_textarea ? $text : trim( $text ) );
				$rendered_lines = null;
				$render_text    = $text;
			} finally {
				if ( is_string( $temporary_font ) ) {
					@unlink( $temporary_font ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink -- Best-effort local temporary-file cleanup.
				}
			}
		}
		try {
			$font_name = self::resolve_font( $font_id, $pdf );
		} catch ( \Throwable $e ) {
			if ( ! $verified_fallback ) {
				throw $e;
			}
			OC_Logger::warning( 'Verified font PDF registration unavailable; attempting companion/source raster, then legacy default PDF font: ' . $e->getMessage() );
			$font_name = self::resolve_font( 0, $pdf );
		}
		$raw_font_path       = is_object( $font ) ? self::get_raw_font_path( $font ) : null;
		$engraving_font_path = 'engraving' === $mode && is_object( $font ) ? self::get_font_path( $font ) : null;
		if ( $verified_fallback && $raw_font_path && 'woff2' === strtolower( pathinfo( $raw_font_path, PATHINFO_EXTENSION ) ) ) {
			$engraving_font_path = self::get_print_companion_font_path( $raw_font_path ) ?? self::get_print_variant_font_path( $font );
		}
		$font_px_to_pt        = $font_px_to_pt && $font_px_to_pt > 0 ? $font_px_to_pt : self::px_to_pt( 1.0 );
		$configured_font_size = (float) ( $input['fontSize'] ?? $settings['default_font_size'] ?? 0 );
		$rendered_font_size   = $verified['renderedFontSize'] ?? self::browser_rendered_font_size( $input, $configured_font_size );
		$font_size            = $configured_font_size > 0 || null !== $rendered_font_size
			? max( 4.0, ( $rendered_font_size ?? $configured_font_size ) * $font_px_to_pt )
			: max( 4.0, max( 1.0, (float) ( $layer['h'] ?? 1 ) ) * 0.72 * $font_px_to_pt );
		$min_size  = ! empty( $settings['min_font_size'] ) ? (float) $settings['min_font_size'] * $font_px_to_pt : 0.0;
		$max_size  = ! empty( $settings['max_font_size'] ) ? (float) $settings['max_font_size'] * $font_px_to_pt : 0.0;
		if ( $max_size > 0.0 ) {
			$font_size = min( $font_size, $max_size );
		}
		if ( $min_size > 0.0 ) {
			$font_size = max( $font_size, $min_size );
		}

		$draw_x_mm = $x_mm;
		$draw_w_mm = $w_mm;

		while ( $font_size > max( 4.0, $min_size ) ) {
			$pdf->SetFont( $font_name, '', $font_size );
			if ( null !== $rendered_lines ) {
				$fits = is_string( $engraving_font_path ) && '' !== $engraving_font_path
					? self::engraving_outline_lines_fit_box( $rendered_lines, $engraving_font_path, $draw_w_mm, $h_mm, $font_size )
					: self::fixed_text_lines_fit_box( $pdf, $rendered_lines, $draw_w_mm, $h_mm, $font_size );
			} else {
				$fits = is_string( $engraving_font_path ) && '' !== $engraving_font_path && $is_textarea
					? self::engraving_outline_text_fits_box( $text, $engraving_font_path, $draw_w_mm, $h_mm, $font_size )
					: self::text_fits_box( $pdf, $text, $draw_w_mm, $h_mm, $font_size, $is_textarea );
			}
			if ( $fits ) {
				break;
			}
			$font_size -= 0.5;
		}

		$align = strtoupper( substr( (string) ( $settings['alignment'] ?? 'center' ), 0, 1 ) );
		if ( ! in_array( $align, [ 'L', 'C', 'R' ], true ) ) {
			$align = 'C';
		}
		$valign_setting = $is_textarea ? (string) ( $settings['line_alignment'] ?? 'top' ) : 'center';
		$valign = match ( $valign_setting ) {
			'top' => 'T',
			'bottom' => 'B',
			default => 'C',
		};

		if ( 'engraving' === $mode && is_string( $engraving_font_path ) && '' !== $engraving_font_path ) {
			// A Fabric Textbox uses its configured vertical alignment even when the
			// content happens to occupy one line. Sending that case through the
			// single-line renderer always centred it in the layer instead.
			if ( $is_textarea && self::render_engraving_multiline_text_outline( $pdf, $render_text, $engraving_font_path, $font_size, $draw_x_mm, $y_mm, $draw_w_mm, $h_mm, $align, $valign, $rendered_lines ) ) {
				return;
			}

			if ( ! $is_textarea && self::render_engraving_text_outline( $pdf, $render_text, $engraving_font_path, $font_size, $draw_x_mm, $y_mm, $draw_w_mm, $h_mm, $align ) ) {
				return;
			}
		}

		if ( 'engraving' === $mode && is_string( $raw_font_path ) && '' !== $raw_font_path && self::render_engraving_text_raster( $pdf, $render_text, $raw_font_path, $font_size, $draw_x_mm, $y_mm, $draw_w_mm, $h_mm, $align, $valign ) ) {
			return;
		}

		$pdf->SetFont( $font_name, '', $font_size );
		if ( 'engraving' === $mode ) {
			$pdf->SetTextColor( ...self::ENGRAVING_TONE_RGB );
		} elseif ( 'spot' !== $mode ) {
			[ $c, $m, $y, $k ] = self::hex_to_cmyk( (string) ( $input['colorHex'] ?? $settings['default_color'] ?? '#000000' ) );
			$pdf->SetTextColorArray( [ $c, $m, $y, $k ] );
		}

		$cell_h = self::cell_h( $font_size );
		self::draw_clipped_text_cell( $pdf, $draw_x_mm, $y_mm, $draw_w_mm, $h_mm, $render_text, $cell_h, $align, $valign, $is_textarea );
	}

	/** Resolve only the selected source, unwrapping WOFF1 without changing its tables. */
	private static function verified_outline_font_path( ?object $font, ?string &$temporary ): string {
		$path = $font ? self::get_raw_font_path( $font ) : null;
		if ( ! $path ) {
			throw new \RuntimeException( 'Verified text font source is unavailable.' );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Read the local font signature.
		$signature = file_get_contents( $path, false, null, 0, 4 );
		if ( 'wOFF' === $signature ) {
			if ( ! class_exists( 'OC_WOFF_Converter' ) ) {
				require_once dirname( __DIR__ ) . '/class-oc-woff-converter.php';
			}
			$temp = self::temp_path_with_extension( 'oc-verified-font', 'ttf' );
			if ( ! is_string( $temp ) ) {
				throw new \RuntimeException( 'Could not stage the verified WOFF font.' );
			}
			$temporary = $temp;
			if ( ! OC_WOFF_Converter::extract_sfnt( $path, $temp ) ) {
				throw new \RuntimeException( 'Could not losslessly unwrap the selected verified WOFF font.' );
			}
			$path = $temp;
		}
		if ( ! self::is_truetype_outline_font( $path ) ) {
			// Companion filenames/family matches do not attest source or metric parity.
			throw new \RuntimeException( 'Verified text requires the selected TrueType-outline source; CFF/WOFF2 companions have no verified same-source provenance.' );
		}
		return $path;
	}

	/** Use Fabric's submitted lines only when they reproduce the canonical text. */
	protected static function browser_rendered_text_lines( array $input, string $text ): ?array {
		$raw_lines = $input['renderedLines'] ?? null;
		if ( ! is_array( $raw_lines ) || empty( $raw_lines ) || count( $raw_lines ) > 200 ) {
			return null;
		}

		$lines = [];
		foreach ( $raw_lines as $line ) {
			if ( ! is_string( $line ) ) {
				return null;
			}
			$lines[] = $line;
		}
		$normalise = static fn( string $value ): string => preg_replace( '/\s+/u', ' ', trim( $value ) ) ?? '';

		$rendered_text = implode( "\n", $lines );
		if ( $normalise( $rendered_text ) === $normalise( $text ) ) {
			return $lines;
		}

		// Grapheme wrapping can add a soft line boundary inside an unbroken word.
		// Remove only those boundaries so customer-entered spaces stay meaningful.
		$without_line_breaks = static fn( string $value ): string => str_replace( "\n", '', str_replace( [ "\r\n", "\r" ], "\n", $value ) );

		return $without_line_breaks( $rendered_text ) === $without_line_breaks( $text ) ? $lines : null;
	}

	/** Independently validate the atomic cart contract in canonical units. */
	protected static function browser_rendered_text_layout( array $input, array $layer, array $settings ): ?array {
		if ( ! in_array( $input['renderedLayoutVersion'] ?? null, [ 1, 1.0, '1' ], true ) ) {
			return null;
		}
		$layout = [];
		foreach ( [ 'renderedFontSize', 'renderedScaleX', 'renderedInsetX' ] as $key ) {
			if ( ! is_numeric( $input[ $key ] ?? null ) || ! is_finite( (float) $input[ $key ] ) ) {
				return null;
			}
			$layout[ $key ] = (float) $input[ $key ];
		}
		$values = [ $input['fontSize'] ?? $settings['default_font_size'] ?? 0, $layer['h'] ?? 0, $settings['min_font_size'] ?? 0, $settings['max_font_size'] ?? 0 ];
		foreach ( $values as $value ) {
			if ( ! is_numeric( $value ) || ! is_finite( (float) $value ) ) {
				return null;
			}
		}
		[ $configured, $height, $min, $max ] = array_map( 'floatval', $values );
		$ceiling                             = $configured > 0 ? $configured : $height * 0.72;
		if ( $max > 0 ) {
			$min     = min( $min, $max );
			$ceiling = min( $ceiling, $max );
		}
		$ceiling   = max( $ceiling, $min );
		$tolerance = max( abs( $ceiling ), abs( $min ) ) * 1e-12;
		$size      = $layout['renderedFontSize'];
		$scale     = $layout['renderedScaleX'];
		$inset     = $layout['renderedInsetX'];
		$type      = $layer['type'] ?? '';
		if ( ! in_array( $type, [ 'text', 'textarea' ], true ) || $size <= 0 || $size < $min - $tolerance || $size > $ceiling + $tolerance
			|| $scale <= 0 || $scale > 1 || $inset < 0 || $inset >= 0.5
			|| ( 'text' === $type && 0.0 !== $inset ) || ( 'textarea' === $type && 1.0 !== $scale ) ) {
			return null;
		}
		$layout['renderedFontSize'] = min( $ceiling, max( $min, $size ) );
		if ( 'textarea' === $type ) {
			$lines = self::browser_rendered_text_lines( $input, (string) ( $input['value'] ?? '' ) );
			if ( null === $lines ) {
				return null;
			}
			$layout['renderedLines'] = $lines;
		}
		return $layout;
	}

	/** Use Fabric's final auto-fitted size only when it cannot enlarge the configured text. */
	protected static function browser_rendered_font_size( array $input, float $configured_size ): ?float {
		$rendered_size = $input['renderedFontSize'] ?? null;
		if ( ! is_numeric( $rendered_size ) || $configured_size <= 0.0 ) {
			return null;
		}

		$rendered_size = (float) $rendered_size;
		return $rendered_size > 0.0 && $rendered_size <= $configured_size ? $rendered_size : null;
	}

	/** Check fixed browser lines without allowing TCPDF to choose different wraps. */
	private static function fixed_text_lines_fit_box( \TCPDF $pdf, array $lines, float $w_mm, float $h_mm, float $font_size ): bool {
		foreach ( $lines as $line ) {
			if ( $pdf->GetStringWidth( $line ) > $w_mm ) {
				return false;
			}
		}

		return count( $lines ) * self::cell_h( $font_size ) <= $h_mm;
	}

	private static function render_engraving_text_raster( \TCPDF $pdf, string $text, string $font_path, float $font_size, float $x_mm, float $y_mm, float $w_mm, float $h_mm, string $align, string $valign ): bool {
		if ( ! class_exists( '\Imagick' ) || ! class_exists( '\ImagickDraw' ) || ! is_readable( $font_path ) ) {
			return false;
		}

		$dpi = 600;
		[ $width_px, $height_px ] = self::bounded_work_dimensions(
			max( 1, (int) ceil( max( 0.1, $w_mm ) / 25.4 * $dpi ) ),
			max( 1, (int) ceil( max( 0.1, $h_mm ) / 25.4 * $dpi ) )
		);
		$temp = self::temp_path_with_extension( 'oc-engraving-text-raster-' . wp_generate_uuid4() . '.png', 'png' );
		if ( ! is_string( $temp ) || '' === $temp ) {
			return false;
		}

		try {
			$image = new \Imagick();
			self::configure_imagick_limits( $image );
			$image->newImage( $width_px, $height_px, new \ImagickPixel( 'transparent' ), 'png' );
			$image->setImageFormat( 'png32' );

			$draw = new \ImagickDraw();
			$draw->setFont( $font_path );
			$draw->setFontSize( $font_size * $height_px / self::mm_to_pt_value( max( 0.1, $h_mm ) ) );
			$draw->setFillColor( new \ImagickPixel( 'black' ) );
			$draw->setTextAntialias( true );
			$draw->setTextAlignment( match ( $align ) {
				'L' => \Imagick::ALIGN_LEFT,
				'R' => \Imagick::ALIGN_RIGHT,
				default => \Imagick::ALIGN_CENTER,
			} );
			$draw->setGravity( self::imagick_text_gravity( $align, $valign ) );
			$image->annotateImage( $draw, 0, 0, 0, $text );

			if ( ! $image->writeImage( $temp ) ) {
				return false;
			}

			$pdf->Image( $temp, $x_mm, $y_mm, $w_mm, $h_mm, '', '', '', false, $dpi );
			return true;
		} catch ( \Throwable $e ) {
			OC_Logger::warning( 'Engraving text raster font render failed for ' . basename( $font_path ) . ': ' . $e->getMessage() );
			return false;
		} finally {
			if ( isset( $draw ) ) {
				$draw->clear();
				$draw->destroy();
			}
			if ( isset( $image ) ) {
				$image->clear();
				$image->destroy();
			}
			@unlink( $temp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
	}

	/** @return \Imagick::GRAVITY_NORTHWEST|\Imagick::GRAVITY_NORTH|\Imagick::GRAVITY_NORTHEAST|\Imagick::GRAVITY_WEST|\Imagick::GRAVITY_CENTER|\Imagick::GRAVITY_EAST|\Imagick::GRAVITY_SOUTHWEST|\Imagick::GRAVITY_SOUTH|\Imagick::GRAVITY_SOUTHEAST */
	private static function imagick_text_gravity( string $align, string $valign ): int {
		return match ( $valign ) {
			'T' => match ( $align ) {
				'L' => \Imagick::GRAVITY_NORTHWEST,
				'R' => \Imagick::GRAVITY_NORTHEAST,
				default => \Imagick::GRAVITY_NORTH,
			},
			'B' => match ( $align ) {
				'L' => \Imagick::GRAVITY_SOUTHWEST,
				'R' => \Imagick::GRAVITY_SOUTHEAST,
				default => \Imagick::GRAVITY_SOUTH,
			},
			default => match ( $align ) {
				'L' => \Imagick::GRAVITY_WEST,
				'R' => \Imagick::GRAVITY_EAST,
				default => \Imagick::GRAVITY_CENTER,
			},
		};
	}

	private static function engraving_outline_text_fits_box( string $text, string $font_path, float $w_mm, float $h_mm, float $font_size ): bool {
		$lines = self::wrap_engraving_outline_lines( $text, $font_path, $font_size, self::mm_to_pt_value( $w_mm ) );
		if ( empty( $lines ) ) {
			return false;
		}

		return self::engraving_textbox_height_mm( count( $lines ), $font_size ) <= $h_mm;
	}

	/** Check fixed browser lines against outline metrics, shrinking all lines uniformly when needed. */
	private static function engraving_outline_lines_fit_box( array $lines, string $font_path, float $w_mm, float $h_mm, float $font_size ): bool {
		$max_width = self::mm_to_pt_value( $w_mm );
		foreach ( $lines as $line ) {
			if ( self::engraving_outline_text_width( $font_path, $line, $font_size ) > $max_width ) {
				return false;
			}
		}

		return self::engraving_textbox_height_mm( count( $lines ), $font_size ) <= $h_mm;
	}

	private static function render_engraving_text_outline( \TCPDF $pdf, string $text, string $font_path, float $font_size, float $x_mm, float $y_mm, float $w_mm, float $h_mm, string $align, ?float $verified_scale_x = null ): bool {
		if ( ! class_exists( 'OC_Print_Embroidery' ) ) {
			return false;
		}

		try {
			$method  = new \ReflectionMethod( 'OC_Print_Embroidery', 'ttf_text_outline' );
			$outline = $method->invoke( null, $font_path, $text, $font_size );
		} catch ( \Throwable $e ) {
			OC_Logger::warning( 'Engraving text outline failed: ' . $e->getMessage() );
			return false;
		}

		if ( ! is_array( $outline ) || empty( $outline['commands'] ) ) {
			return false;
		}

		$d = self::eps_outline_commands_to_svg_path( $outline['commands'] );
		if ( '' === $d ) {
			return false;
		}

		$width     = max( 0.01, (float) ( $outline['width'] ?? 0.0 ) );
		$bbox      = is_array( $outline['bbox'] ?? null ) ? $outline['bbox'] : [ 0.0, 0.0, $width, $font_size ];
		$glyph_w   = max( 0.01, (float) $bbox[2] - (float) $bbox[0] );
		$glyph_h   = max( 0.01, (float) $bbox[3] - (float) $bbox[1] );
		$box_w_pt  = self::mm_to_pt_value( $w_mm );
		$box_h_pt  = self::mm_to_pt_value( $h_mm );
		$layout_min_x = min( 0.0, (float) $bbox[0] );
		$layout_max_x = max( $width, (float) $bbox[2] );
		$layout_w     = max( 0.01, $layout_max_x - $layout_min_x );
		// Fabric positions every single-line text object from the same typographic
		// baseline. Centring each string's visible glyph box here moves script and
		// capitals by different amounts and destroys intentional layer overlaps.
		$line_h    = $font_size * self::FABRIC_FONT_SIZE_MULTIPLIER;
		$fit_scale = min( 1.0, $box_w_pt / $layout_w, $box_h_pt / $line_h );
		$fit_scale = max( 0.01, $fit_scale );
		if ( null !== $verified_scale_x ) {
			$fit_scale = 1.0;
		}
		$scale_x   = $verified_scale_x ?? $fit_scale;
		$pad       = max( 1.0, $font_size * $fit_scale * 0.08 );
		$advance_w = $width * $scale_x;
		$draw_w    = $glyph_w * $scale_x + $pad * 2;
		$draw_h    = $glyph_h * $fit_scale + $pad * 2;
		$origin_x  = match ( $align ) {
			'R' => $box_w_pt - $advance_w + (float) $bbox[0] * $scale_x - $pad,
			'L' => (float) $bbox[0] * $scale_x - $pad,
			default => ( $box_w_pt - $advance_w ) / 2 + (float) $bbox[0] * $scale_x - $pad,
		};
		$path_x     = -1 * (float) $bbox[0] * $scale_x + $pad;
		$path_y     = (float) $bbox[3] * $fit_scale + $pad;
		$baseline_y = $box_h_pt / 2 + $line_h * $fit_scale * ( 0.5 - self::FABRIC_FONT_SIZE_FRACTION );
		$origin_y   = $baseline_y - $path_y;

		$svg = sprintf(
			'<svg xmlns="http://www.w3.org/2000/svg" width="%.4Fpt" height="%.4Fpt" viewBox="0 0 %.4F %.4F"><g transform="translate(%.4F %.4F) scale(%.8F %.8F)"><path d="%s" fill="#000000" fill-rule="nonzero" clip-rule="nonzero"/></g></svg>',
			$draw_w,
			$draw_h,
			$draw_w,
			$draw_h,
			$path_x,
			$path_y,
			$scale_x,
			$fit_scale,
			htmlspecialchars( $d, ENT_QUOTES | ENT_XML1, 'UTF-8' )
		);

		$temp = self::temp_path_with_extension( 'oc-engraving-text-outline-' . wp_generate_uuid4() . '.svg', 'svg' );
		if ( ! is_string( $temp ) || '' === $temp ) {
			return false;
		}

		if ( false === file_put_contents( $temp, $svg ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			@unlink( $temp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return false;
		}

		try {
			$pdf->ImageSVG( $temp, $x_mm + self::pt_to_mm_value( $origin_x ), $y_mm + self::pt_to_mm_value( $origin_y ), self::pt_to_mm_value( $draw_w ), self::pt_to_mm_value( $draw_h ), '', '', '', 0, false );
			return true;
		} catch ( \Throwable $e ) {
			OC_Logger::warning( 'Engraving text outline SVG render failed: ' . $e->getMessage() );
			return false;
		} finally {
			@unlink( $temp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
	}

	private static function render_engraving_multiline_text_outline( \TCPDF $pdf, string $text, string $font_path, float $font_size, float $x_mm, float $y_mm, float $w_mm, float $h_mm, string $align, string $valign, ?array $fixed_lines = null, bool $verified = false ): bool {
		$lines = null !== $fixed_lines ? $fixed_lines : self::wrap_engraving_outline_lines( $text, $font_path, $font_size, self::mm_to_pt_value( $w_mm ) );
		if ( empty( $lines ) ) {
			return false;
		}

		$line_box_h = self::pt_to_mm_value( $font_size * self::FABRIC_FONT_SIZE_MULTIPLIER );
		$line_step  = self::pt_to_mm_value( $font_size * self::FABRIC_FONT_SIZE_MULTIPLIER * self::FABRIC_TEXTBOX_LINE_HEIGHT );
		$total_h    = self::engraving_textbox_height_mm( count( $lines ), $font_size );
		// Keep every submitted line when this low-level renderer receives a
		// constrained box directly. The normal layer path shrinks the font first,
		// but regenerated legacy payloads and helper callers may bypass that fit.
		$layout_scale = $verified ? 1.0 : min( 1.0, $h_mm / max( 0.001, $total_h ) );
		$line_box_h  *= $layout_scale;
		$line_step   *= $layout_scale;
		$total_h     *= $layout_scale;
		$offset_y     = match ( $valign ) {
			'T' => 0.0,
			'B' => max( 0.0, $h_mm - $total_h ),
			default => max( 0.0, ( $h_mm - $total_h ) / 2 ),
		};

		$rendered = false;
		foreach ( $lines as $index => $line ) {
			$line_y = $y_mm + $offset_y + ( $index * $line_step );
			if ( ! $verified && $line_y + $line_box_h > $y_mm + $h_mm + 0.001 ) {
				break;
			}

			if ( '' === trim( $line ) ) {
				continue;
			}
			if ( ! self::render_engraving_text_outline( $pdf, $line, $font_path, $font_size, $x_mm, $line_y, $w_mm, $line_box_h, $align, $verified ? 1.0 : null ) ) {
				if ( $verified || $rendered ) {
					throw new \RuntimeException( 'A nonblank text line could not be outlined; refusing incomplete production text.' );
				}
				return false;
			}
			$rendered = true;
		}

		return $rendered;
	}

	/** Match Fabric Textbox's final-line and inter-line height calculation. */
	private static function engraving_textbox_height_mm( int $line_count, float $font_size ): float {
		$line_count = max( 1, $line_count );
		$line_box   = $font_size * self::FABRIC_FONT_SIZE_MULTIPLIER;
		$line_step  = $line_box * self::FABRIC_TEXTBOX_LINE_HEIGHT;

		return self::pt_to_mm_value( $line_box + ( $line_count - 1 ) * $line_step );
	}

	/** @return string[] */
	private static function wrap_engraving_outline_lines( string $text, string $font_path, float $font_size, float $max_width_pt ): array {
		$lines = [];
		foreach ( preg_split( '/\R/u', $text ) ?: [] as $paragraph ) {
			$paragraph = trim( (string) $paragraph );
			if ( '' === $paragraph ) {
				// Reserve the baseline even though there are no glyphs to paint.
				$lines[] = '';
				continue;
			}

			$current = '';
			foreach ( preg_split( '/\s+/u', $paragraph ) ?: [] as $word ) {
				$word = (string) $word;
				$candidate = '' === $current ? $word : $current . ' ' . $word;
				if ( self::engraving_outline_text_width( $font_path, $candidate, $font_size ) <= $max_width_pt ) {
					$current = $candidate;
					continue;
				}

				if ( '' !== $current ) {
					$lines[] = $current;
				}

				if ( self::engraving_outline_text_width( $font_path, $word, $font_size ) <= $max_width_pt ) {
					$current = $word;
				} else {
					$split = self::wrap_engraving_outline_word( $word, $font_path, $font_size, $max_width_pt );
					$lines = array_merge( $lines, array_slice( $split, 0, -1 ) );
					$current = (string) end( $split );
				}
			}

			if ( '' !== $current ) {
				$lines[] = $current;
			}
		}

		return $lines;
	}

	/** @return string[] */
	private static function wrap_engraving_outline_word( string $word, string $font_path, float $font_size, float $max_width_pt ): array {
		$chars = preg_split( '//u', $word, -1, PREG_SPLIT_NO_EMPTY ) ?: str_split( $word );
		$lines = [];
		$current = '';

		foreach ( $chars as $char ) {
			$candidate = $current . $char;
			if ( '' === $current || self::engraving_outline_text_width( $font_path, $candidate, $font_size ) <= $max_width_pt ) {
				$current = $candidate;
				continue;
			}

			$lines[] = $current;
			$current = $char;
		}

		if ( '' !== $current ) {
			$lines[] = $current;
		}

		return $lines;
	}

	private static function engraving_outline_text_width( string $font_path, string $text, float $font_size ): float {
		if ( '' === $text || ! class_exists( 'OC_Print_Embroidery' ) ) {
			return 0.0;
		}

		try {
			$method  = new \ReflectionMethod( 'OC_Print_Embroidery', 'ttf_text_outline' );
			$outline = $method->invoke( null, $font_path, $text, $font_size );
		} catch ( \Throwable $e ) {
			return 0.0;
		}

		return is_array( $outline ) ? max( 0.0, (float) ( $outline['width'] ?? 0.0 ) ) : 0.0;
	}

	private static function eps_outline_commands_to_svg_path( array $commands ): string {
		$parts = [];
		foreach ( $commands as $command ) {
			$command = trim( (string) $command );
			if ( preg_match( '/^([-0-9.]+)\s+([-0-9.]+)\s+moveto$/', $command, $m ) ) {
				$parts[] = sprintf( 'M%.4F %.4F', (float) $m[1], -1 * (float) $m[2] );
			} elseif ( preg_match( '/^([-0-9.]+)\s+([-0-9.]+)\s+lineto$/', $command, $m ) ) {
				$parts[] = sprintf( 'L%.4F %.4F', (float) $m[1], -1 * (float) $m[2] );
			} elseif ( preg_match( '/^([-0-9.]+)\s+([-0-9.]+)\s+([-0-9.]+)\s+([-0-9.]+)\s+([-0-9.]+)\s+([-0-9.]+)\s+curveto$/', $command, $m ) ) {
				$parts[] = sprintf( 'C%.4F %.4F %.4F %.4F %.4F %.4F', (float) $m[1], -1 * (float) $m[2], (float) $m[3], -1 * (float) $m[4], (float) $m[5], -1 * (float) $m[6] );
			} elseif ( 'closepath' === $command ) {
				$parts[] = 'Z';
			}
		}

		return implode( ' ', $parts );
	}

	private static function mm_to_pt_value( float $mm ): float {
		return $mm * 72 / 25.4;
	}

	private static function pt_to_mm_value( float $pt ): float {
		return $pt * 25.4 / 72;
	}
}
