<?php
/**
 * Shared images helpers for print file generators.
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

trait OC_Print_Base_Images {

	/** Embed an image in TCPDF, converting artwork to a safe PNG when needed. */
	protected static function draw_pdf_image( \TCPDF $pdf, string $path, float $x_mm, float $y_mm, float $w_mm, float $h_mm ): void {
		if ( ! is_readable( $path ) ) {
			throw new \RuntimeException( sprintf( __( 'Production artwork is not readable: %s', 'overcustomise' ), basename( $path ) ) );
		}
		if ( $w_mm <= 0.0 || $h_mm <= 0.0 ) {
			return;
		}

		if ( 'svg' === strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) ) {
			self::draw_pdf_svg( $pdf, $path, $x_mm, $y_mm, $w_mm, $h_mm );
			return;
		}
		self::assert_safe_raster_dimensions( $path );

		$temp_path      = null;
		$fallback_path  = null;
		$image_path     = self::tcpdf_compatible_image_path( $path, $temp_path );

		try {
			try {
				$pdf->Image( $image_path, $x_mm, $y_mm, $w_mm, $h_mm, '', '', '', false, 300 );
			} catch ( \Throwable $e ) {
				$fallback_path = self::normalise_raster_image_for_tcpdf( $path );
				if ( ! is_string( $fallback_path ) || '' === $fallback_path || $fallback_path === $image_path ) {
					throw $e;
				}

				OC_Logger::warning( 'TCPDF could not read print artwork directly, retrying normalised PNG: ' . basename( $path ) . ' (' . $e->getMessage() . ')' );
				$pdf->Image( $fallback_path, $x_mm, $y_mm, $w_mm, $h_mm, '', '', '', false, 300 );
			}
		} finally {
			if ( is_string( $temp_path ) && '' !== $temp_path && file_exists( $temp_path ) ) {
				@unlink( $temp_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
			if ( is_string( $fallback_path ) && '' !== $fallback_path && file_exists( $fallback_path ) ) {
				@unlink( $fallback_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
		}
	}

	/** Embed SVG artwork as vector first, with a print-resolution raster fallback for unsupported SVGs. */
	private static function draw_pdf_svg( \TCPDF $pdf, string $path, float $x_mm, float $y_mm, float $w_mm, float $h_mm ): void {
		$dom = self::load_print_svg( $path );
		// Legacy exporter DTDs are accepted only after removal, never handed to a decoder.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Read local artwork bytes, not a remote URL.
		if ( preg_match( '/<!DOCTYPE/i', file_get_contents( $path ) ) ) {
			$temp = self::temp_path_with_extension( 'oc-safe-svg-' . wp_generate_uuid4(), 'svg' );
			try {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents, WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Stage local renderer input and check write failure. Native DOM property.
				if ( ! $temp || false === file_put_contents( $temp, $dom->saveXML( $dom->documentElement ) ) ) {
					throw new \RuntimeException( 'Could not stage safe SVG artwork.' );
				}
				self::draw_pdf_svg( $pdf, $temp, $x_mm, $y_mm, $w_mm, $h_mm );
			} finally {
				if ( $temp ) {
					@unlink( $temp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink -- Best-effort local temporary-file cleanup.
				}
			}
			return;
		}
		$embedded = self::embedded_svg_raster( $dom );
		if ( null !== $embedded ) {
			$temp = self::temp_path_with_extension( 'oc-embedded-' . wp_generate_uuid4() . '.' . $embedded[1], $embedded[1] );
			try {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Stage local renderer input and check write failure.
				if ( ! $temp || false === file_put_contents( $temp, $embedded[0] ) ) {
					throw new \RuntimeException( 'Could not extract embedded raster artwork.' );
				}
				self::draw_pdf_image( $pdf, $temp, $x_mm, $y_mm, $w_mm, $h_mm );
			} finally {
				if ( $temp ) {
					@unlink( $temp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink -- Best-effort local temporary-file cleanup.
				}
			}
			return;
		}
		$vector_path   = self::normalise_svg_intrinsic_size_for_tcpdf( $path );
		$svg_path      = is_string( $vector_path ) && '' !== $vector_path ? $vector_path : $path;
		$fallback_path = null;

		try {
			$pdf->ImageSVG( $svg_path, $x_mm, $y_mm, $w_mm, $h_mm, '', '', '', 0, false );
		} catch ( \Throwable $e ) {
			$fallback_path = self::normalise_svg_for_tcpdf( $path, $w_mm, $h_mm );
			if ( ! is_string( $fallback_path ) || '' === $fallback_path ) {
				throw $e;
			}

			try {
				OC_Logger::warning( 'TCPDF could not render SVG artwork directly, retrying print-resolution PNG: ' . basename( $path ) . ' (' . $e->getMessage() . ')' );
				$pdf->Image( $fallback_path, $x_mm, $y_mm, $w_mm, $h_mm, '', '', '', false, 600 );
			} finally {
				@unlink( $fallback_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
		} finally {
			if ( is_string( $vector_path ) && '' !== $vector_path && file_exists( $vector_path ) ) {
				@unlink( $vector_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
		}
	}

	/** Validate resources, not renderer feature support. Never expand XML entities. */
	protected static function load_print_svg( string $path ): \DOMDocument {
		if ( ! class_exists( '\DOMDocument' ) || ! is_file( $path ) || ! is_readable( $path ) || filesize( $path ) > self::MAX_SVG_BYTES ) {
			throw new \RuntimeException( 'SVG artwork is unavailable or exceeds the safe production size limit.' );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Read bounded local artwork bytes, not a remote URL.
		$raw = file_get_contents( $path, false, null, 0, self::MAX_SVG_BYTES + 1 );
		if ( ! is_string( $raw ) || strlen( $raw ) > self::MAX_SVG_BYTES || preg_match( '/<!ENTITY|<\?(?!xml\s)/i', $raw ) ) {
			throw new \RuntimeException( 'Unsafe SVG declarations are not supported for production.' );
		}
		// Same external-only declaration stripping as OC_SVG_Sanitiser. Do not
		// run its presentation allowlist here: it removes existing SVG effects.
		$raw = preg_replace( '/<!DOCTYPE\s+svg(?:\s+(?:SYSTEM\s+(?:"[^"]*"|\'[^\']*\')|PUBLIC\s+(?:"[^"]*"|\'[^\']*\')\s+(?:"[^"]*"|\'[^\']*\')))?\s*>/i', '', $raw );
		if ( preg_match( '/<!DOCTYPE/i', $raw ) ) {
			throw new \RuntimeException( 'Unsafe SVG declarations are not supported for production.' );
		}
		$dom      = new \DOMDocument();
		$previous = libxml_use_internal_errors( true );
		try {
			$loaded = $dom->loadXML( $raw, LIBXML_NONET );
		} finally {
			libxml_clear_errors();
			libxml_use_internal_errors( $previous );
		}
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM properties.
		if ( ! $loaded || ! $dom->documentElement || 'svg' !== $dom->documentElement->localName ) {
			throw new \RuntimeException( 'Invalid production SVG artwork.' );
		}
		foreach ( $dom->getElementsByTagName( '*' ) as $node ) {
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM property.
			if ( in_array( strtolower( $node->localName ), [ 'script', 'foreignobject', 'animate', 'animatetransform', 'animatemotion', 'set' ], true ) ) {
				throw new \RuntimeException( 'Active SVG content is not supported for production.' );
			}
			foreach ( $node->attributes as $attribute ) {
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM property.
				$name = strtolower( $attribute->localName );
				if ( 'base' === $name || str_starts_with( $name, 'on' ) ) {
					throw new \RuntimeException( 'SVG resource references and active content are not supported for production.' );
				}
				if ( in_array( $name, [ 'href', 'src' ], true ) ) {
					$value = trim( $attribute->value );
					// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM property.
					if ( str_starts_with( $value, 'data:' ) && in_array( $node->localName, [ 'image', 'feImage' ], true ) ) {
						self::decode_embedded_svg_raster( $value );
					} elseif ( ! preg_match( '/\A#[^\s]+\z/u', $value ) ) {
						throw new \RuntimeException( 'External SVG resources are not supported for production.' );
					}
				}
				if ( ! in_array( $name, [ 'href', 'src' ], true ) ) {
					self::assert_print_svg_style_safe( $attribute->value );
				}
			}
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM property.
			if ( 'style' === $node->localName ) {
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM property.
				self::assert_print_svg_style_safe( $node->textContent );
			}
		}
		return $dom;
	}

	/** Accept only the flat raster wrapper emitted by the clipart converter. */
	protected static function embedded_svg_raster( \DOMDocument $dom ): ?array {
		$images = $dom->getElementsByTagNameNS( '*', 'image' );
		if ( 0 === $images->length ) {
			return null;
		}
		$svg   = $dom->documentElement; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM property.
		$image = $images->item( 0 );
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM property.
		if ( 1 !== $images->length || 2 !== $dom->getElementsByTagName( '*' )->length || $image->parentNode !== $svg ) {
			return null;
		}
		foreach ( [ $svg, $image ] as $node ) {
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM property.
			foreach ( $node->childNodes as $child ) {
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM property.
				if ( ! $child instanceof \DOMElement && ! $child instanceof \DOMComment && ! ( $child instanceof \DOMText && '' === trim( $child->textContent ) ) ) {
					return null;
				}
			}
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM property.
			if ( 'http://www.w3.org/2000/svg' !== $node->namespaceURI ) {
				return null;
			}
			$allowed = $node === $svg ? [ 'width', 'height', 'viewBox', 'data-oc-converted' ] : [ 'width', 'height', 'href' ];
			foreach ( $node->attributes as $attribute ) {
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM properties.
				if ( ! in_array( $attribute->localName, $allowed, true ) || ( $attribute->namespaceURI && ! ( $node === $image && 'href' === $attribute->localName && 'http://www.w3.org/1999/xlink' === $attribute->namespaceURI ) ) ) {
					return null;
				}
			}
		}
		$uri   = $image->getAttribute( 'href' );
		$uri   = $uri ? $uri : $image->getAttributeNS( 'http://www.w3.org/1999/xlink', 'href' );
		$xlink = $image->getAttributeNS( 'http://www.w3.org/1999/xlink', 'href' );
		if ( '' !== $xlink && $xlink !== $uri ) {
			return null;
		}
		if ( ! str_starts_with( $uri, 'data:' ) ) {
			return null;
		}
		[ $bytes, $format ] = self::decode_embedded_svg_raster( $uri );
		$info               = getimagesizefromstring( $bytes );
		foreach ( [ $svg, $image ] as $node ) {
			if ( $node->getAttribute( 'width' ) !== (string) $info[0] || $node->getAttribute( 'height' ) !== (string) $info[1] ) {
				return null;
			}
		}
		if ( $svg->getAttribute( 'viewBox' ) !== '0 0 ' . $info[0] . ' ' . $info[1] ) {
			return null;
		}
		return [ $bytes, $format ];
	}

	/** Validate embedded pixels even when their surrounding SVG cannot be extracted. */
	private static function decode_embedded_svg_raster( string $uri ): array {
		// Bound the unnormalised input too, so whitespace cannot bypass the SVG budget.
		if ( strlen( $uri ) > self::MAX_SVG_BYTES || ! preg_match( '~\Adata:image/(png|jpeg);base64,(.*)\z~sD', $uri, $match ) ) {
			throw new \RuntimeException( 'Only bounded embedded PNG/JPEG data is supported for production.' );
		}
		$match[2] = str_replace( [ ' ', "\t", "\r", "\n", "\f", "\v" ], '', $match[2] );
		if ( strlen( $match[2] ) > 4 * (int) ceil( self::MAX_EMBEDDED_RASTER_BYTES / 3 ) || ! preg_match( '~\A[A-Za-z0-9+/]*={0,2}\z~D', $match[2] ) ) {
			throw new \RuntimeException( 'Only bounded embedded PNG/JPEG data is supported for production.' );
		}
		$bytes = base64_decode( $match[2], true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decode embedded image bytes for validation.
		$info  = false !== $bytes && '' !== $bytes && strlen( $bytes ) <= self::MAX_EMBEDDED_RASTER_BYTES ? @getimagesizefromstring( $bytes ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Invalid image bytes are rejected below.
		if ( false === $bytes || base64_encode( $bytes ) !== $match[2] || ! $info || 'image/' . $match[1] !== $info['mime'] // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Verify canonical image encoding.
			|| $info[0] <= 0 || $info[1] <= 0 || $info[0] > self::MAX_RASTER_DIMENSION || $info[1] > self::MAX_RASTER_DIMENSION || $info[0] * $info[1] > self::MAX_RASTER_PIXELS ) {
			throw new \RuntimeException( 'Invalid or oversized embedded raster artwork.' );
		}
		return [ $bytes, $match[1] ];
	}

	/** Resolve positive absolute SVG lengths to CSS pixels at 96 DPI. */
	protected static function svg_absolute_length_px( string $value ): float {
		if ( ! preg_match( '/\A([+]?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?)(px|mm|cm|in|pt|pc)?\z/', trim( $value ), $match ) ) {
			throw new \RuntimeException( 'SVG requires a viewBox or absolute intrinsic dimensions.' );
		}
		$scales = [
			''   => 1,
			'px' => 1,
			'mm' => 96 / 25.4,
			'cm' => 96 / 2.54,
			'in' => 96,
			'pt' => 96 / 72,
			'pc' => 16,
		];
		$pixels = (float) $match[1] * $scales[ $match[2] ?? '' ];
		if ( ! is_finite( $pixels ) || $pixels <= 0 ) {
			throw new \RuntimeException( 'SVG intrinsic dimensions must be positive and finite.' );
		}
		return $pixels;
	}

	/** Decode CSS escapes/comments before denying external URLs and imports. */
	private static function assert_print_svg_style_safe( string $value ): void {
		$value = preg_replace( '~/\*.*?\*/~s', '', $value );
		$value = preg_replace_callback(
			'/\\\\([0-9a-f]{1,6})\s?|\\\\(.)/is',
			static function ( array $matches ): string {
				return ! empty( $matches[1] ) ? html_entity_decode( '&#' . hexdec( $matches[1] ) . ';', ENT_QUOTES, 'UTF-8' ) : ( $matches[2] ?? '' );
			},
			$value
		);
		if ( preg_match( '/@import|@font-face|expression\s*\(/i', $value ) ) {
			throw new \RuntimeException( 'External SVG styles are not supported for production.' );
		}
		preg_match_all( '/url\s*\((.*?)\)/is', $value, $urls );
		foreach ( $urls[1] as $url ) {
			if ( ! preg_match( '/\A#[^\s]+\z/u', trim( $url, " \t\r\n\"'" ) ) ) {
				throw new \RuntimeException( 'External SVG resources are not supported for production.' );
			}
		}
	}

	/** Add explicit SVG width/height from viewBox so TCPDF can keep vector artwork. */
	private static function normalise_svg_intrinsic_size_for_tcpdf( string $path ): ?string {
		if ( ! class_exists( '\DOMDocument' ) || ! is_readable( $path ) ) {
			return null;
		}
		if ( filesize( $path ) > self::MAX_SVG_BYTES ) {
			throw new \RuntimeException( __( 'SVG artwork exceeds the safe production size limit.', 'overcustomise' ) );
		}

		$data = file_get_contents( $path );
		$has_positive_intrinsic_size = is_string( $data ) && self::tcpdf_svg_markup_has_positive_intrinsic_size( $data );

		$dom = new \DOMDocument();
		$previous = libxml_use_internal_errors( true );
		$loaded = $dom->load( $path, LIBXML_NONET );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		if ( ! $loaded || ! $dom->documentElement instanceof \DOMElement || 'svg' !== strtolower( $dom->documentElement->localName ) ) {
			return null;
		}

		$svg = $dom->documentElement;
		$width  = self::tcpdf_svg_length_is_positive( $svg->getAttribute( 'width' ) ) ? (float) $svg->getAttribute( 'width' ) : 0.0;
		$height = self::tcpdf_svg_length_is_positive( $svg->getAttribute( 'height' ) ) ? (float) $svg->getAttribute( 'height' ) : 0.0;

		$changed = false;
		if ( $width <= 0.0 || $height <= 0.0 ) {
			$view_box = preg_split( '/[\s,]+/', trim( $svg->getAttribute( 'viewBox' ) ) );
			if ( ! is_array( $view_box ) || count( $view_box ) < 4 ) {
				return null;
			}

			$width  = (float) $view_box[2];
			$height = (float) $view_box[3];
		}

		if ( $width <= 0.0 || $height <= 0.0 ) {
			return null;
		}

		if ( ! $has_positive_intrinsic_size ) {
			$svg->setAttribute( 'width', sprintf( '%.4F', $width ) );
			$svg->setAttribute( 'height', sprintf( '%.4F', $height ) );
			$changed = true;
		}

		$changed = self::normalise_svg_paths_for_tcpdf( $svg ) || $changed;
		$changed = self::inline_svg_presentation_styles( $dom, $svg ) || $changed;
		if ( ! $changed ) {
			return null;
		}

		$temp = self::temp_path_with_extension( 'oc-tcpdf-vector-svg-' . wp_generate_uuid4() . '.svg', 'svg' );
		if ( ! is_string( $temp ) || '' === $temp ) {
			return null;
		}

		if ( false === $dom->save( $temp ) ) {
			@unlink( $temp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return null;
		}

		return $temp;
	}

	/** Serialize compact path data and make closures explicit for TCPDF. */
	private static function normalise_svg_paths_for_tcpdf( \DOMElement $svg, float $closure_tolerance = 0.0 ): bool {
		$changed = false;
		foreach ( $svg->getElementsByTagName( 'path' ) as $path ) {
			$data = $path->getAttribute( 'd' );
			if ( '' === $data ) {
				continue;
			}

			$normalised = self::normalise_svg_path_data_for_tcpdf( $data, $closure_tolerance );
			if ( null === $normalised ) {
				continue;
			}

			if ( $normalised !== $data ) {
				$path->setAttribute( 'd', $normalised );
				$changed = true;
			}
		}

		return $changed;
	}

	/**
	 * Convert SVG geometry to absolute commands that TCPDF handles reliably.
	 * Closure tolerance is opt-in, in local units, bounded by engraving placement.
	 */
	private static function normalise_svg_path_data_for_tcpdf( string $data, float $closure_tolerance = 0.0 ): ?string {
		$pattern = '/[AaCcHhLlMmQqSsTtVvZz]|[+-]?(?:\d+\.\d*|\.\d+|\d+)(?:[eE][+-]?\d+)?/';
		preg_match_all( $pattern, $data, $matches );
		$tokens  = $matches[0];
		$residue = preg_replace( $pattern, '', $data ) ?? $data;
		if ( empty( $tokens ) || count( $tokens ) > 200000 || preg_match( '/[^\s,]/', $residue ) || ! in_array( $tokens[0], [ 'M', 'm' ], true ) ) {
			return null;
		}
		foreach ( $tokens as $token ) {
			if ( ! self::svg_path_token_is_command( $token ) && ! is_finite( (float) $token ) ) {
				return null;
			}
		}

		$out = [];
		$i = 0;
		$command = '';
		$x = 0.0;
		$y = 0.0;
		$start_x = 0.0;
		$start_y = 0.0;
		$cubic_x = null;
		$cubic_y = null;
		$quad_x = null;
		$quad_y = null;
		while ( $i < count( $tokens ) ) {
			$explicit = false;
			if ( self::svg_path_token_is_command( $tokens[ $i ] ) ) {
				$command = $tokens[ $i++ ];
				$explicit = true;
			}
			if ( '' === $command ) {
				return null;
			}

			$relative = ctype_lower( $command );
			$type     = strtoupper( $command );
			$before   = $i;
			switch ( $type ) {
				case 'M':
					$first = true;
					while ( self::svg_path_has_numbers( $tokens, $i, 2 ) ) {
						$nx = (float) $tokens[ $i++ ];
						$ny = (float) $tokens[ $i++ ];
						$x = $relative ? $x + $nx : $nx;
						$y = $relative ? $y + $ny : $ny;
						$out[] = ( $first ? 'M ' : 'L ' ) . self::normalise_svg_path_point( $x, $y );
						if ( $first ) {
							$start_x = $x;
							$start_y = $y;
							$first = false;
						}
					}
					$command = $relative ? 'l' : 'L';
					$cubic_x = $cubic_y = $quad_x = $quad_y = null;
					break;
				case 'L':
					while ( self::svg_path_has_numbers( $tokens, $i, 2 ) ) {
						$nx = (float) $tokens[ $i++ ];
						$ny = (float) $tokens[ $i++ ];
						$x = $relative ? $x + $nx : $nx;
						$y = $relative ? $y + $ny : $ny;
						$out[] = 'L ' . self::normalise_svg_path_point( $x, $y );
					}
					$cubic_x = $cubic_y = $quad_x = $quad_y = null;
					break;
				case 'H':
					while ( self::svg_path_has_numbers( $tokens, $i, 1 ) ) {
						$nx = (float) $tokens[ $i++ ];
						$x = $relative ? $x + $nx : $nx;
						$out[] = 'L ' . self::normalise_svg_path_point( $x, $y );
					}
					$cubic_x = $cubic_y = $quad_x = $quad_y = null;
					break;
				case 'V':
					while ( self::svg_path_has_numbers( $tokens, $i, 1 ) ) {
						$ny = (float) $tokens[ $i++ ];
						$y = $relative ? $y + $ny : $ny;
						$out[] = 'L ' . self::normalise_svg_path_point( $x, $y );
					}
					$cubic_x = $cubic_y = $quad_x = $quad_y = null;
					break;
				case 'C':
					while ( self::svg_path_has_numbers( $tokens, $i, 6 ) ) {
						$values = array_map( 'floatval', array_slice( $tokens, $i, 6 ) );
						$i += 6;
						[ $x1, $y1, $x2, $y2, $ex, $ey ] = $values;
						if ( $relative ) {
							$x1 += $x; $y1 += $y; $x2 += $x; $y2 += $y; $ex += $x; $ey += $y;
						}
						$out[] = 'C ' . self::normalise_svg_path_point( $x1, $y1 ) . ' ' . self::normalise_svg_path_point( $x2, $y2 ) . ' ' . self::normalise_svg_path_point( $ex, $ey );
						$x = $ex; $y = $ey; $cubic_x = $x2; $cubic_y = $y2; $quad_x = $quad_y = null;
					}
					break;
				case 'S':
					while ( self::svg_path_has_numbers( $tokens, $i, 4 ) ) {
						$values = array_map( 'floatval', array_slice( $tokens, $i, 4 ) );
						$i += 4;
						[ $x2, $y2, $ex, $ey ] = $values;
						$x1 = null !== $cubic_x ? 2 * $x - $cubic_x : $x;
						$y1 = null !== $cubic_y ? 2 * $y - $cubic_y : $y;
						if ( $relative ) {
							$x2 += $x; $y2 += $y; $ex += $x; $ey += $y;
						}
						$out[] = 'C ' . self::normalise_svg_path_point( $x1, $y1 ) . ' ' . self::normalise_svg_path_point( $x2, $y2 ) . ' ' . self::normalise_svg_path_point( $ex, $ey );
						$x = $ex; $y = $ey; $cubic_x = $x2; $cubic_y = $y2; $quad_x = $quad_y = null;
					}
					break;
				case 'Q':
				case 'T':
					$parameter_count = 'Q' === $type ? 4 : 2;
					while ( self::svg_path_has_numbers( $tokens, $i, $parameter_count ) ) {
						if ( 'Q' === $type ) {
							$qx = (float) $tokens[ $i++ ]; $qy = (float) $tokens[ $i++ ];
							if ( $relative ) { $qx += $x; $qy += $y; }
						} else {
							$qx = null !== $quad_x ? 2 * $x - $quad_x : $x;
							$qy = null !== $quad_y ? 2 * $y - $quad_y : $y;
						}
						$ex = (float) $tokens[ $i++ ]; $ey = (float) $tokens[ $i++ ];
						if ( $relative ) { $ex += $x; $ey += $y; }
						$c1x = $x + 2 / 3 * ( $qx - $x ); $c1y = $y + 2 / 3 * ( $qy - $y );
						$c2x = $ex + 2 / 3 * ( $qx - $ex ); $c2y = $ey + 2 / 3 * ( $qy - $ey );
						$out[] = 'C ' . self::normalise_svg_path_point( $c1x, $c1y ) . ' ' . self::normalise_svg_path_point( $c2x, $c2y ) . ' ' . self::normalise_svg_path_point( $ex, $ey );
						$x = $ex; $y = $ey; $quad_x = $qx; $quad_y = $qy; $cubic_x = $cubic_y = null;
					}
					break;
				case 'A':
					while ( self::svg_path_has_numbers( $tokens, $i, 7 ) ) {
						$values = array_map( 'floatval', array_slice( $tokens, $i, 7 ) );
						$i += 7;
						[ $rx, $ry, $rotation, $large, $sweep, $ex, $ey ] = $values;
						if ( ! in_array( $large, [ 0.0, 1.0 ], true ) || ! in_array( $sweep, [ 0.0, 1.0 ], true ) ) { return null; }
						if ( $relative ) { $ex += $x; $ey += $y; }
						$out[] = 'A ' . self::normalise_svg_path_number( abs( $rx ) ) . ' ' . self::normalise_svg_path_number( abs( $ry ) ) . ' ' . self::normalise_svg_path_number( $rotation ) . ' ' . (int) $large . ' ' . (int) $sweep . ' ' . self::normalise_svg_path_point( $ex, $ey );
						$x = $ex; $y = $ey; $cubic_x = $cubic_y = $quad_x = $quad_y = null;
					}
					break;
				case 'Z':
					if ( ! $explicit ) {
						return null;
					}
					// Corel can discard PDF closure even with h present. Move only the
					// final cubic endpoint; retain Z for fill and stroke join semantics.
					$last = count( $out ) - 1;
					if ( $closure_tolerance > 0 && $last >= 0 && str_starts_with( $out[ $last ], 'C ' )
						&& hypot( $x - $start_x, $y - $start_y ) <= $closure_tolerance ) {
						$curve        = explode( ' ', $out[ $last ] );
						$curve[5]     = self::normalise_svg_path_number( $start_x );
						$curve[6]     = self::normalise_svg_path_number( $start_y );
						$out[ $last ] = implode( ' ', $curve );
					}
					$out[] = 'Z';
					$x = $start_x; $y = $start_y; $command = '';
					$cubic_x = $cubic_y = $quad_x = $quad_y = null;
					break;
				default:
					return null;
			}
			if ( 'Z' !== $type && $i === $before ) {
				return null;
			}
		}

		return implode( ' ', $out );
	}

	private static function svg_path_token_is_command( string $token ): bool {
		return 1 === strlen( $token ) && ctype_alpha( $token );
	}

	private static function svg_path_has_numbers( array $tokens, int $offset, int $count ): bool {
		if ( $offset + $count > count( $tokens ) ) {
			return false;
		}
		for ( $index = 0; $index < $count; $index++ ) {
			if ( self::svg_path_token_is_command( (string) $tokens[ $offset + $index ] ) ) {
				return false;
			}
		}

		return true;
	}

	private static function normalise_svg_path_point( float $x, float $y ): string {
		return self::normalise_svg_path_number( $x ) . ' ' . self::normalise_svg_path_number( $y );
	}

	/** Return a plain decimal that TCPDF's path-number parser accepts. */
	private static function normalise_svg_path_number( string|float $number ): string {
		$normalised = rtrim( rtrim( sprintf( '%.12F', (float) $number ), '0' ), '.' );

		return '-0' === $normalised || '' === $normalised ? '0' : $normalised;
	}

	/** Inline simple SVG CSS presentation styles because TCPDF does not apply them reliably. */
	private static function inline_svg_presentation_styles( \DOMDocument $dom, \DOMElement $svg ): bool {
		$changed = false;
		$xpath   = new \DOMXPath( $dom );
		$styles  = [];
		foreach ( $svg->getElementsByTagName( 'style' ) as $style ) {
			$styles[] = $style;
		}

		foreach ( $styles as $style ) {
			$css = preg_replace( '/\/\*[\s\S]*?\*\//', '', (string) $style->textContent ) ?? '';
			if ( preg_match_all( '/([^{}@]+)\{([^{}]+)\}/', $css, $rules, PREG_SET_ORDER ) ) {
				foreach ( $rules as $rule ) {
					$declarations = self::svg_presentation_declarations( (string) $rule[2] );
					if ( empty( $declarations ) ) {
						continue;
					}

					foreach ( explode( ',', (string) $rule[1] ) as $selector ) {
						$query = self::svg_css_selector_xpath( trim( $selector ) );
						if ( '' === $query ) {
							continue;
						}

						$nodes = $xpath->query( $query, $svg );
						if ( ! $nodes instanceof \DOMNodeList ) {
							continue;
						}

						foreach ( $nodes as $node ) {
							if ( ! $node instanceof \DOMElement ) {
								continue;
							}
							foreach ( $declarations as $attribute => $value ) {
								$node->setAttribute( $attribute, $value );
								$changed = true;
							}
						}
					}
				}
			}

			// Keep original cleaned CSS as a fallback for selectors we do not inline.
		}

		foreach ( $xpath->query( './/*[@style]', $svg ) ?: [] as $node ) {
			if ( ! $node instanceof \DOMElement ) {
				continue;
			}
			foreach ( self::svg_presentation_declarations( $node->getAttribute( 'style' ) ) as $attribute => $value ) {
				$node->setAttribute( $attribute, $value );
				$changed = true;
			}
		}

		return $changed;
	}

	/** @return array<string,string> */
	private static function svg_presentation_declarations( string $css ): array {
		$allowed = [ 'fill', 'stroke', 'opacity', 'fill-opacity', 'stroke-opacity', 'stroke-width', 'stroke-linecap', 'stroke-linejoin', 'fill-rule', 'clip-rule' ];
		$declarations = [];
		foreach ( explode( ';', $css ) as $declaration ) {
			$parts = explode( ':', $declaration, 2 );
			if ( count( $parts ) !== 2 ) {
				continue;
			}

			$property = strtolower( trim( $parts[0] ) );
			$value    = preg_replace( '/\s*!important$/i', '', trim( $parts[1] ) ) ?? '';
			if ( in_array( $property, $allowed, true ) && '' !== $value ) {
				$declarations[ $property ] = $value;
			}
		}

		return $declarations;
	}

	private static function svg_css_selector_xpath( string $selector ): string {
		if ( preg_match( '/^\.([A-Za-z_][A-Za-z0-9_-]*)$/', $selector, $match ) ) {
			$class = self::xpath_literal( ' ' . $match[1] . ' ' );
			return './/*[contains(concat(" ", normalize-space(@class), " "), ' . $class . ')]';
		}

		if ( preg_match( '/^#([A-Za-z_][A-Za-z0-9_-]*)$/', $selector, $match ) ) {
			return './/*[@id=' . self::xpath_literal( $match[1] ) . ']';
		}

		if ( preg_match( '/^([A-Za-z][A-Za-z0-9_-]*)\.([A-Za-z_][A-Za-z0-9_-]*)$/', $selector, $match ) ) {
			$class = self::xpath_literal( ' ' . $match[2] . ' ' );
			return './/*[local-name()=' . self::xpath_literal( $match[1] ) . ' and contains(concat(" ", normalize-space(@class), " "), ' . $class . ')]';
		}

		if ( preg_match( '/^[A-Za-z][A-Za-z0-9_-]*$/', $selector ) ) {
			return './/*[local-name()=' . self::xpath_literal( $selector ) . ']';
		}

		return '';
	}

	private static function xpath_literal( string $value ): string {
		if ( ! str_contains( $value, "'" ) ) {
			return "'" . $value . "'";
		}

		if ( ! str_contains( $value, '"' ) ) {
			return '"' . $value . '"';
		}

		$parts = array_map(
			static fn( string $part ): string => "'" . $part . "'",
			explode( "'", $value )
		);

		return 'concat(' . implode( ', "\'", ', $parts ) . ')';
	}

	/** Check whether the raw SVG root already matches TCPDF's strict double-quoted size parser. */
	private static function tcpdf_svg_markup_has_positive_intrinsic_size( string $data ): bool {
		$matches = [];
		if ( ! preg_match( '/<svg([^>]*)>/si', $data, $matches ) || empty( $matches[1] ) ) {
			return false;
		}

		$attrs = $matches[1];
		$width = [];
		$height = [];
		return preg_match( '/[\s]+width[\s]*=[\s]*"([^"]*)"/si', $attrs, $width )
			&& preg_match( '/[\s]+height[\s]*=[\s]*"([^"]*)"/si', $attrs, $height )
			&& self::tcpdf_svg_length_is_positive( $width[1] )
			&& self::tcpdf_svg_length_is_positive( $height[1] );
	}

	/** Check whether TCPDF's SVG size regex/unit parser will see a positive intrinsic size. */
	private static function tcpdf_svg_length_is_positive( string $value ): bool {
		$value = trim( $value );
		if ( '' === $value || str_contains( $value, '%' ) ) {
			return false;
		}

		return preg_match( '/^[+]?(?:\d+\.?\d*|\.\d+)(?:px|pt|pc|mm|cm|in)?$/i', $value ) && (float) $value > 0.0;
	}

	/** Convert SVG artwork to a transparent PNG at the final print size. */
	private static function normalise_svg_for_tcpdf( string $path, float $w_mm = 0.0, float $h_mm = 0.0 ): ?string {
		self::load_print_svg( $path );
		if ( ! class_exists( '\Imagick' ) ) {
			return null;
		}

		$temp = self::temp_path_with_extension( 'oc-tcpdf-svg-' . wp_generate_uuid4() . '.png', 'png' );
		if ( ! is_string( $temp ) || '' === $temp ) {
			return null;
		}

		$converted = false;
		try {
			$converted = self::convert_svg_with_imagick( $path, $temp, $w_mm, $h_mm );
			return $converted ? $temp : null;
		} finally {
			if ( ! $converted ) {
				@unlink( $temp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink -- Best-effort local temporary-file cleanup.
			}
		}
	}

	/** Rasterise an SVG with enough pixels for the placed PDF dimensions. */
	private static function convert_svg_with_imagick( string $path, string $output_path, float $w_mm, float $h_mm ): bool {
		if ( ! class_exists( '\Imagick' ) ) {
			return false;
		}

		$dom     = self::load_print_svg( $path );
		$imagick = null;

		try {
			[ $width_px, $height_px ] = self::print_raster_dimensions( $w_mm, $h_mm );
			$svg                      = $dom->documentElement; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM property.
			if ( ! $svg->hasAttribute( 'viewBox' ) ) {
				$width  = self::svg_absolute_length_px( $svg->getAttribute( 'width' ) );
				$height = self::svg_absolute_length_px( $svg->getAttribute( 'height' ) );
				$svg->setAttribute( 'viewBox', '0 0 ' . $width . ' ' . $height );
			}
			// Set the viewport BEFORE decoding, rather than enlarging an intrinsic-size raster.
			$svg->setAttribute( 'width', (string) $width_px );
			$svg->setAttribute( 'height', (string) $height_px );
			$imagick = new \Imagick();
			self::configure_imagick_limits( $imagick );
			$imagick->setResolution( 96, 96 );
			$imagick->setBackgroundColor( new \ImagickPixel( 'transparent' ) );
			$imagick->readImageBlob( $dom->saveXML( $svg ) );
			$imagick->setImageAlphaChannel( \Imagick::ALPHACHANNEL_ACTIVATE );
			$imagick->setImageFormat( 'png32' );
			$imagick->resizeImage( $width_px, $height_px, \Imagick::FILTER_LANCZOS, 1, false );
			$result = $imagick->writeImage( $output_path );
			return (bool) $result;
		} catch ( \Throwable $e ) {
			OC_Logger::warning( 'SVG to print-resolution PNG conversion failed for print artwork: ' . $e->getMessage() );
			return false;
		} finally {
			if ( $imagick instanceof \Imagick ) {
				$imagick->clear();
				$imagick->destroy();
			}
		}
	}

	/** Physical-size working budget, bounded before integer conversion or allocation. */
	protected static function print_raster_dimensions( float $w_mm, float $h_mm, int $dpi = 600, int $max_dimension = self::MAX_WORK_RASTER_DIMENSION, int $max_pixels = self::MAX_WORK_RASTER_PIXELS ): array {
		if ( ! is_finite( $w_mm ) || ! is_finite( $h_mm ) || $w_mm <= 0 || $h_mm <= 0 ) {
			throw new \RuntimeException( 'Valid final artwork dimensions are required for raster processing.' );
		}
		$dpi   = max( 72, min( 2400, $dpi ) );
		$scale = min( $dpi / 25.4, $max_dimension / max( $w_mm, $h_mm ), sqrt( $max_pixels / $w_mm / $h_mm ) );
		return self::bounded_work_dimensions( max( 1, (int) ceil( $w_mm * $scale ) ), max( 1, (int) ceil( $h_mm * $scale ) ), $max_dimension, $max_pixels );
	}

	/** TCPDF does not reliably import WEBP, so create a temporary PNG copy first. */
	private static function tcpdf_compatible_image_path( string $path, ?string &$temp_path ): string {
		$temp_path = null;
		if ( 'webp' !== strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) ) {
			return $path;
		}

		$temp = self::temp_path_with_extension( 'oc-tcpdf-webp-' . wp_generate_uuid4() . '.png', 'png' );
		if ( ! is_string( $temp ) || '' === $temp ) {
			return $path;
		}

		$src = self::open_raster_resource( $path );
		if ( ! $src ) {
			if ( self::convert_image_with_imagick( $path, $temp ) ) {
				$temp_path = $temp;
				return $temp;
			}

			@unlink( $temp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return $path;
		}

		imagesavealpha( $src, true );
		if ( ! imagepng( $src, $temp ) ) {
			imagedestroy( $src );
			@unlink( $temp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return $path;
		}

		imagedestroy( $src );
		$temp_path = $temp;
		return $temp;
	}

	/** Re-encode raster artwork to a PNG that TCPDF can import. */
	private static function normalise_raster_image_for_tcpdf( string $path ): ?string {
		$ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, [ 'jpg', 'jpeg', 'png', 'webp', 'bmp', 'gif' ], true ) ) {
			return null;
		}

		$temp = self::temp_path_with_extension( 'oc-tcpdf-raster-' . wp_generate_uuid4() . '.png', 'png' );
		if ( ! is_string( $temp ) || '' === $temp ) {
			return null;
		}

		$src = self::open_raster_resource( $path );
		if ( $src ) {
			if ( function_exists( 'imagepalettetotruecolor' ) ) {
				imagepalettetotruecolor( $src );
			}
			imagealphablending( $src, false );
			imagesavealpha( $src, true );

			if ( imagepng( $src, $temp ) ) {
				imagedestroy( $src );
				return $temp;
			}

			imagedestroy( $src );
		}

		if ( self::convert_image_with_imagick( $path, $temp ) ) {
			return $temp;
		}

		@unlink( $temp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		return null;
	}

	/** Convert an image to PNG using Imagick when GD cannot open it. */
	private static function convert_image_with_imagick( string $path, string $output_path ): bool {
		if ( ! class_exists( '\Imagick' ) ) {
			return false;
		}

		try {
			$imagick = new \Imagick();
			self::configure_imagick_limits( $imagick );
			$imagick->readImage( $path );
			[ $width, $height ] = self::bounded_work_dimensions( $imagick->getImageWidth(), $imagick->getImageHeight() );
			if ( $width !== $imagick->getImageWidth() || $height !== $imagick->getImageHeight() ) {
				$imagick->resizeImage( $width, $height, \Imagick::FILTER_LANCZOS, 1, true );
			}
			$imagick->setImageFormat( 'png' );
			$imagick->setImageAlphaChannel( \Imagick::ALPHACHANNEL_ACTIVATE );
			$result = $imagick->writeImage( $output_path );
			$imagick->clear();
			$imagick->destroy();
			return (bool) $result;
		} catch ( \Throwable $e ) {
			OC_Logger::warning( 'Image to PNG conversion failed for print artwork: ' . $e->getMessage() );
			return false;
		}
	}

	/** Apply bounded ImageMagick memory/map/disk limits before reading customer input. */
	protected static function configure_imagick_limits( \Imagick $imagick ): void {
		$imagick->setResourceLimit( \Imagick::RESOURCETYPE_MEMORY, 256 * 1024 * 1024 );
		$imagick->setResourceLimit( \Imagick::RESOURCETYPE_MAP, 256 * 1024 * 1024 );
		$imagick->setResourceLimit( \Imagick::RESOURCETYPE_DISK, 512 * 1024 * 1024 );
		if ( defined( '\\Imagick::RESOURCETYPE_AREA' ) ) {
			$imagick->setResourceLimit( \Imagick::RESOURCETYPE_AREA, self::MAX_RASTER_PIXELS );
		}
	}

	/** Scale dimensions to the bounded working raster envelope without distortion. */
	protected static function bounded_work_dimensions( int $width, int $height, int $max_dimension = self::MAX_WORK_RASTER_DIMENSION, int $max_pixels = self::MAX_WORK_RASTER_PIXELS ): array {
		$width  = max( 1, $width );
		$height = max( 1, $height );
		$scale  = min(
			1.0,
			$max_dimension / max( $width, $height ),
			sqrt( $max_pixels / max( 1, $width * $height ) )
		);

		return [
			max( 1, (int) floor( $width * $scale ) ),
			max( 1, (int) floor( $height * $scale ) ),
		];
	}

	/** Reject raster headers that exceed the upload/production resource envelope. */
	protected static function assert_safe_raster_dimensions( string $path ): array {
		$size = @getimagesize( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! is_array( $size ) || empty( $size[0] ) || empty( $size[1] ) ) {
			throw new \RuntimeException( sprintf( __( 'Production artwork is not a supported raster image: %s', 'overcustomise' ), basename( $path ) ) );
		}

		$width  = (int) $size[0];
		$height = (int) $size[1];
		if ( $width > self::MAX_RASTER_DIMENSION || $height > self::MAX_RASTER_DIMENSION || $width * $height > self::MAX_RASTER_PIXELS ) {
			throw new \RuntimeException( __( 'Artwork dimensions exceed the safe production rendering limit.', 'overcustomise' ) );
		}

		return [ $width, $height ];
	}
}
