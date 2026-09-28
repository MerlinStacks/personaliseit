<?php
/**
 * Font-independent UV text and colour emoji artwork.
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

trait OC_Print_UV_Text {

	/** Render UV lettering as paths, so Illustrator never has to decode PDF fonts. */
	protected static function render_uv_text( \TCPDF $pdf, array $layer, array $input, array $settings, float $x, float $y, float $w, float $h, string $mode, float $conversion ): void {
		foreach ( [ $layer['h'] ?? 1, $input['fontSize'] ?? $settings['default_font_size'] ?? 0, $settings['min_font_size'] ?? 0, $settings['max_font_size'] ?? 0 ] as $geometry ) {
			if ( ! is_numeric( $geometry ) || ! is_finite( (float) $geometry ) ) {
				throw new \RuntimeException( 'Invalid UV text geometry.' );
			}
		}
		$text = str_replace( [ "\r\n", "\r" ], "\n", (string) ( $input['value'] ?? '' ) );
		if ( '' === trim( $text ) ) {
			return;
		}
		$multiline = 'textarea' === ( $layer['type'] ?? '' );
		$verified  = self::browser_rendered_text_layout( $input, $layer, $settings );
		if ( null === $verified && array_key_exists( 'renderedLayoutVersion', $input ) ) {
			unset( $input['renderedFontSize'], $input['renderedLines'] );
		}
		$configured = (float) ( $input['fontSize'] ?? $settings['default_font_size'] ?? 0 );
		$size       = ( $verified['renderedFontSize'] ?? self::browser_rendered_font_size( $input, $configured ) ?? ( $configured > 0 ? $configured : (float) ( $layer['h'] ?? 1 ) * 0.72 ) ) * $conversion;
		$max        = (float) ( $settings['max_font_size'] ?? 0 ) * $conversion;
		$min        = (float) ( $settings['min_font_size'] ?? 0 ) * $conversion;
		$size       = max( 0.01, $min, $max > 0 ? min( $size, $max ) : $size );
		$width      = self::mm_to_pt_value( $w );
		$height     = self::mm_to_pt_value( $h );
		foreach ( [ $x, $y, $width, $height, $size, $conversion ] as $value ) {
			if ( ! is_finite( $value ) ) {
				throw new \RuntimeException( 'Invalid UV text geometry.' );
			}
		}
		if ( $width <= 0 || $height <= 0 || $conversion <= 0 || strlen( $text ) > 16384 ) {
			throw new \RuntimeException( 'Invalid UV text dimensions or length.' );
		}
		$font_id   = ! empty( $input['fontId'] ) ? (int) $input['fontId'] : (int) ( $settings['default_font_id'] ?? 0 );
		$font      = $font_id ? self::get_font( $font_id ) : null;
		$temporary = null;
		$svg_path  = null;
		try {
			$path = $font ? self::get_font_path( $font ) : null;
			if ( $path && 'woff2' === strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) ) {
				$path = self::get_print_companion_font_path( $path ) ?? self::get_print_variant_font_path( $font );
			}
			if ( ! $path && ! $font_id ) {
				$temporary = self::temp_path_with_extension( 'oc-uv-default-font', 'ttf' );
				$source    = OC_PATH . 'vendor/tecnickcom/tc-lib-pdf-font/target/fonts/freefont/freesans.z';
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Bundled local font.
				$bytes = gzuncompress( (string) file_get_contents( $source ), 16777216 );
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Local renderer input.
				if ( ! $temporary || false === $bytes || false === file_put_contents( $temporary, $bytes ) ) {
					throw new \RuntimeException( 'Could not prepare the default UV font.' );
				}
				$path = $temporary;
			}
			if ( ! $path || ! self::is_truetype_outline_font( $path ) ) {
				throw new \RuntimeException( 'UV text requires a TrueType-outline print font.' );
			}
			if ( ! class_exists( 'OC_Print_Embroidery' ) ) {
				require_once __DIR__ . '/class-oc-print-embroidery.php';
			}
			$inset     = $width * ( $verified['renderedInsetX'] ?? 0.0 );
			$available = $width - 2 * $inset;
			$lines     = $multiline ? ( $verified['renderedLines'] ?? self::browser_rendered_text_lines( $input, $text ) ) : [ trim( $text ) ];
			if ( null === $lines ) {
				$lines = self::wrap_uv_text( $text, $path, $available / $size );
			}
			$runs         = array_map( static fn( string $line ): array => self::uv_text_runs( $line, $path ), $lines );
			$line_width   = max( 0.01, ...array_column( $runs, 'width' ) );
			$block_height = self::FABRIC_FONT_SIZE_MULTIPLIER * ( 1 + ( count( $lines ) - 1 ) * self::FABRIC_TEXTBOX_LINE_HEIGHT );
			if ( null === $verified ) {
				$size = min( $size, $available / $line_width, $height / $block_height );
			}
			$scale_x  = $verified['renderedScaleX'] ?? 1.0;
			$align    = $settings['alignment'] ?? 'center';
			$valign   = $multiline ? ( $settings['line_alignment'] ?? 'top' ) : 'center';
			$offset_y = match ( $valign ) {
				'top' => 0.0,
				'bottom' => $height - $block_height * $size,
				default => ( $height - $block_height * $size ) / 2,
			};
			$colour  = (string) ( $input['colorHex'] ?? $settings['default_color'] ?? '#000000' );
			$colour  = preg_match( '/^#[0-9a-f]{6}$/i', $colour ) ? $colour : '#000000';
			$content = '';
			foreach ( $runs as $index => $line ) {
				$advance = $line['width'] * $size * $scale_x;
				$left    = $inset + match ( $align ) {
					'left' => 0.0,
					'right' => $available - $advance,
					default => ( $available - $advance ) / 2,
				};
				$baseline = $offset_y + $size * self::FABRIC_FONT_SIZE_MULTIPLIER * ( 1 - self::FABRIC_FONT_SIZE_FRACTION + $index * self::FABRIC_TEXTBOX_LINE_HEIGHT );
				$content .= sprintf( '<g transform="translate(%.6F %.6F) scale(%.8F %.8F)" fill="%s">%s</g>', $left, $baseline, $size * $scale_x, $size, $colour, $line['svg'] );
			}
			$svg      = sprintf( '<svg xmlns="http://www.w3.org/2000/svg" width="%.6Fpt" height="%.6Fpt" viewBox="0 0 %.6F %.6F">%s</svg>', $width, $height, $width, $height, $content );
			$svg_path = self::temp_path_with_extension( 'oc-uv-text', 'svg' );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Local renderer input.
			if ( ! $svg_path || strlen( $svg ) > self::MAX_SVG_BYTES || false === file_put_contents( $svg_path, $svg ) ) {
				throw new \RuntimeException( 'Could not prepare outlined UV text.' );
			}
			$pdf->StartTransform();
			try {
				$pdf->Rect( $x, $y, $w, $h, 'CNZ' );
				if ( 'spot' === $mode ) {
					self::render_artwork_spot_mask( $pdf, $svg_path, $x, $y, $w, $h );
				} else {
					self::draw_pdf_svg( $pdf, $svg_path, $x, $y, $w, $h );
				}
			} finally {
				$pdf->StopTransform();
			}
		} finally {
			foreach ( [ $temporary, $svg_path ] as $temp ) {
				if ( is_string( $temp ) ) {
					@unlink( $temp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink -- Temporary renderer input.
				}
			}
		}
	}

	/** Split extended graphemes, keeping ZWJ families, flags and modifiers together. */
	protected static function uv_emoji_key( string $grapheme ): ?string {
		if ( str_contains( $grapheme, "\u{FE0E}" ) || ! preg_match( '/[\p{Emoji_Presentation}\x{FE0F}\x{20E3}]/u', $grapheme ) ) {
			return null;
		}
		// Twemoji keeps VS16 in ZWJ sequences, but omits it in ordinary filenames.
		if ( ! str_contains( $grapheme, "\u{200D}" ) ) {
			$grapheme = str_replace( "\u{FE0F}", '', $grapheme );
		}
		$characters = preg_split( '//u', $grapheme, -1, PREG_SPLIT_NO_EMPTY );
		return implode( '-', array_map( static fn( string $char ): string => dechex( mb_ord( $char, 'UTF-8' ) ), is_array( $characters ) ? $characters : [] ) );
	}

	/** Build one em-sized line from text outlines and self-contained emoji vectors. */
	private static function uv_text_runs( string $text, string $font_path, bool $artwork = true ): array {
		preg_match_all( '/\X/u', $text, $matches );
		$segments = [];
		$plain    = '';
		foreach ( $matches[0] as $grapheme ) {
			$key = self::uv_emoji_key( $grapheme );
			if ( null === $key ) {
				$plain .= str_replace( "\u{FE0E}", '', $grapheme );
				continue;
			}
			$segments[] = [ 'text' => $plain ];
			$segments[] = [ 'emoji' => $key ];
			$plain      = '';
		}
		$segments[]     = [ 'text' => $plain ];
		$width          = 0.0;
		$svg            = '';
		$outline_method = new \ReflectionMethod( 'OC_Print_Embroidery', 'ttf_text_outline' );
		foreach ( $segments as $segment ) {
			if ( isset( $segment['emoji'] ) ) {
				if ( $artwork ) {
					$emoji = self::uv_emoji_svg( $segment['emoji'] );
					$svg  .= sprintf( '<g transform="translate(%.6F -0.9) scale(0.02777778)">%s</g>', $width, $emoji );
				}
				$width += 1.0;
			} elseif ( '' !== $segment['text'] ) {
				$outline = $outline_method->invoke( null, $font_path, $segment['text'], 1.0 );
				if ( ! is_array( $outline ) ) {
					throw new \RuntimeException( 'The selected UV font cannot outline all of the requested characters.' );
				}
				$d = $artwork ? self::eps_outline_commands_to_svg_path( $outline['commands'] ) : '';
				if ( '' !== $d ) {
					$svg .= sprintf( '<path transform="translate(%.6F 0)" d="%s"/>', $width, htmlspecialchars( $d, ENT_QUOTES | ENT_XML1, 'UTF-8' ) );
				}
				$width += $outline['width'];
			}
		}
		return [
			'width' => $width,
			'svg'   => $svg,
		];
	}

	/** Wrap older textarea payloads, including unbroken words, at grapheme boundaries. */
	private static function wrap_uv_text( string $text, string $font_path, float $width ): array {
		$lines = [];
		foreach ( explode( "\n", $text ) as $paragraph ) {
			$current = '';
			$words   = preg_split( '/(?<=\s)/u', $paragraph );
			foreach ( is_array( $words ) ? $words : [] as $word ) {
				if ( '' !== $current && self::uv_text_runs( $current . $word, $font_path, false )['width'] > $width ) {
					$lines[] = rtrim( $current );
					$current = '';
				}
				preg_match_all( '/\X/u', $word, $matches );
				foreach ( $matches[0] as $char ) {
					if ( '' !== $current && self::uv_text_runs( $current . $char, $font_path, false )['width'] > $width ) {
						$lines[] = rtrim( $current );
						$current = '';
					}
					$current .= $char;
				}
			}
			$lines[] = rtrim( $current );
		}
		return $lines;
	}

	/** Cache pinned WordPress/Twemoji artwork; never substitute missing-glyph boxes. */
	private static function uv_emoji_svg( string $key ): string {
		static $cache = [];
		if ( isset( $cache[ $key ] ) ) {
			return $cache[ $key ];
		}
		$directory = trailingslashit( wp_upload_dir()['basedir'] ) . 'overcustomise/emoji/17.0.2/';
		$path      = $directory . $key . '.svg';
		if ( ! is_readable( $path ) ) {
			$response = wp_safe_remote_get(
				'https://s.w.org/images/core/emoji/17.0.2/svg/' . $key . '.svg',
				[
					'timeout'             => 15,
					'limit_response_size' => 131072,
				]
			);
			if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
				throw new \RuntimeException( 'Could not load UV emoji artwork: ' . esc_html( $key ) );
			}
			wp_mkdir_p( $directory );
			$temp = self::temp_path_with_extension( 'oc-uv-emoji', 'svg' );
			try {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Validate before publishing the cached artwork.
				if ( ! $temp || false === file_put_contents( $temp, wp_remote_retrieve_body( $response ) ) ) {
					throw new \RuntimeException( 'Could not stage UV emoji artwork.' );
				}
				self::load_print_svg( $temp );
				// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- Publish the validated local cache atomically.
				if ( ! rename( $temp, $path ) ) {
					throw new \RuntimeException( 'Could not cache UV emoji artwork.' );
				}
			} finally {
				if ( is_string( $temp ) && is_file( $temp ) ) {
					@unlink( $temp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink -- Temporary renderer input.
				}
			}
		}
		$dom     = self::load_print_svg( $path );
		$content = '';
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM API.
		foreach ( $dom->documentElement->childNodes as $child ) {
			$content .= $dom->saveXML( $child );
		}
		$cache[ $key ] = $content;
		return $content;
	}
}
