<?php
/**
 * Shared artwork effects helpers for print file generators.
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

trait OC_Print_Base_Artwork_Effects {

	/** Draw only non-transparent artwork pixels using the currently selected spot fill. */
	protected static function render_artwork_spot_mask( \TCPDF $pdf, string $path, float $x_mm, float $y_mm, float $w_mm, float $h_mm ): void {
		if ( 'svg' === strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) ) {
			$image = self::rasterise_svg_spot_mask( $path, $w_mm, $h_mm );
		} else {
			$image = self::open_raster_resource( $path );
			if ( $image ) {
				$image = self::bounded_gd_resource( $image, self::MAX_SPOT_MASK_DIMENSION, self::MAX_SPOT_MASK_DIMENSION * self::MAX_SPOT_MASK_DIMENSION );
			}
		}
		if ( ! $image ) {
			throw new \RuntimeException( sprintf( __( 'Could not build a white-ink mask from %s.', 'overcustomise' ), basename( $path ) ) );
		}

		$width  = imagesx( $image );
		$height = imagesy( $image );
		$x_scale = $w_mm / max( 1, $width );
		$y_scale = $h_mm / max( 1, $height );
		$runs    = 0;
		for ( $row = 0; $row < $height; $row++ ) {
			$run_start = -1;
			for ( $column = 0; $column < $width; $column++ ) {
				$rgba    = imagecolorat( $image, $column, $row );
				$visible = ( ( $rgba >> 24 ) & 0x7F ) < 120;
				if ( $visible && $run_start < 0 ) {
					$run_start = $column;
				} elseif ( ! $visible && $run_start >= 0 ) {
					if ( ++$runs > self::MAX_SPOT_MASK_RUNS ) {
						imagedestroy( $image );
						throw new \RuntimeException( __( 'The white-ink mask is too complex to render safely.', 'overcustomise' ) );
					}
					$pdf->Rect( $x_mm + $run_start * $x_scale, $y_mm + $row * $y_scale, ( $column - $run_start ) * $x_scale, $y_scale, 'F' );
					$run_start = -1;
				}
			}
			if ( $run_start >= 0 ) {
				if ( ++$runs > self::MAX_SPOT_MASK_RUNS ) {
					imagedestroy( $image );
					throw new \RuntimeException( __( 'The white-ink mask is too complex to render safely.', 'overcustomise' ) );
				}
				$pdf->Rect( $x_mm + $run_start * $x_scale, $y_mm + $row * $y_scale, ( $width - $run_start ) * $x_scale, $y_scale, 'F' );
			}
		}

		imagedestroy( $image );
	}

	/** Rasterise SVG alpha only at a bounded white-plate working resolution. */
	private static function rasterise_svg_spot_mask( string $path, float $w_mm, float $h_mm ) {
		$dom      = self::load_print_svg( $path );
		$embedded = self::embedded_svg_raster( $dom );
		if ( null !== $embedded ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Decoder failure is checked below.
			$image = function_exists( 'imagecreatefromstring' ) ? @imagecreatefromstring( $embedded[0] ) : false;
			return $image ? self::bounded_gd_resource( $image, self::MAX_SPOT_MASK_DIMENSION, self::MAX_SPOT_MASK_DIMENSION * self::MAX_SPOT_MASK_DIMENSION ) : false;
		}
		if ( ! class_exists( '\Imagick' ) || ! function_exists( 'imagecreatefromstring' ) || filesize( $path ) > self::MAX_SVG_BYTES ) {
			return false;
		}
		$ratio = max( 0.01, $w_mm ) / max( 0.01, $h_mm );
		$width = $ratio >= 1.0 ? self::MAX_SPOT_MASK_DIMENSION : max( 1, (int) round( self::MAX_SPOT_MASK_DIMENSION * $ratio ) );
		$height = $ratio >= 1.0 ? max( 1, (int) round( self::MAX_SPOT_MASK_DIMENSION / $ratio ) ) : self::MAX_SPOT_MASK_DIMENSION;

		try {
			$imagick = new \Imagick();
			self::configure_imagick_limits( $imagick );
			$imagick->setBackgroundColor( new \ImagickPixel( 'transparent' ) );
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM property.
			$imagick->readImageBlob( $dom->saveXML( $dom->documentElement ) );
			$imagick->setImageAlphaChannel( \Imagick::ALPHACHANNEL_ACTIVATE );
			$imagick->setImageFormat( 'png32' );
			$imagick->resizeImage( $width, $height, \Imagick::FILTER_LANCZOS, 1, true );
			$blob = $imagick->getImageBlob();
			$imagick->clear();
			$imagick->destroy();

			return is_string( $blob ) && '' !== $blob ? @imagecreatefromstring( $blob ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		} catch ( \Throwable $e ) {
			OC_Logger::warning( 'SVG white-ink mask conversion failed: ' . $e->getMessage() );
			return false;
		}
	}

	protected static function build_black_clipart( string $path, float $width_mm = 0.0, float $height_mm = 0.0 ): ?string {
		return self::build_coloured_clipart( $path, '#000000', $width_mm, $height_mm );
	}

	private static function build_coloured_clipart( string $path, string $hex, float $width_mm = 0.0, float $height_mm = 0.0 ): ?string {
		$ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		if ( 'svg' === $ext ) {
			return self::build_coloured_svg( $path, $hex, $width_mm, $height_mm );
		}

		return '#000000' === $hex ? self::build_black_raster( $path ) : null;
	}

	private static function build_coloured_svg( string $path, string $hex, float $width_mm = 0.0, float $height_mm = 0.0 ): ?string {
		// Validate before changing root inheritance, styles, shared definitions or mask paints.
		$validated = self::load_print_svg( $path );
		$embedded  = self::embedded_svg_raster( $validated );
		if ( null !== $embedded && '#000000' === $hex ) {
			$temp = self::temp_path_with_extension( 'oc-embedded-' . wp_generate_uuid4() . '.' . $embedded[1], $embedded[1] );
			if ( ! $temp ) {
				return null;
			}
			try {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Stage local renderer input and check write failure.
				return false !== file_put_contents( $temp, $embedded[0] ) ? self::build_black_raster( $temp ) : null;
			} finally {
				@unlink( $temp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink -- Best-effort local temporary-file cleanup.
			}
		}
		$complex = ( new \DOMXPath( $validated ) )->query( '//*[local-name()="mask" or local-name()="filter" or local-name()="linearGradient" or local-name()="radialGradient" or local-name()="pattern" or local-name()="use" or local-name()="image" or local-name()="clipPath"]' )->length > 0;
		if ( $complex && '#000000' === $hex ) {
			$raster = null;
			try {
				// Rasterise the ORIGINAL paints first: mask luminance and gradient alpha
				// must survive before the visible pixels become a black silhouette.
				[ $width, $height ] = self::artwork_intrinsic_dimensions( $path ) ?? [ 1, 1 ];
				$scale              = 173.4 / max( 1, $width, $height );
				$raster             = self::normalise_svg_for_tcpdf( $path, $width * $scale, $height * $scale );
				$black              = $raster ? self::build_black_raster( $raster ) : null;
				if ( $black ) {
					return $black;
				}
			} catch ( \Throwable $e ) {
				OC_Logger::warning( 'SVG silhouette raster fallback failed: ' . $e->getMessage() );
			} finally {
				if ( $raster ) {
					@unlink( $raster ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink -- Best-effort local temporary-file cleanup.
				}
			}
			OC_Logger::warning( 'SVG silhouette raster backend unavailable or failed; using legacy vector recolouring with mask resources preserved. Complex effect fidelity depends on the PDF renderer.' );
		}
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM property.
		$raw = $validated->saveXML( $validated->documentElement );
		if ( ! is_string( $raw ) || '' === $raw ) {
			return null;
		}

		$dom = new \DOMDocument();
		$previous = libxml_use_internal_errors( true );
		$loaded = $dom->loadXML( $raw, LIBXML_NONET | LIBXML_NOCDATA );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		if ( ! $loaded || ! $dom->documentElement || 'svg' !== strtolower( $dom->documentElement->localName ) ) {
			return null;
		}

		$svg = $dom->documentElement; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM property.
		$svg->setAttribute( 'color', $hex );
		if ( ! $svg->hasAttribute( 'fill' ) ) {
			$svg->setAttribute( 'fill', $hex );
		}
		if ( $width_mm > 0 && $height_mm > 0 ) {
			self::repair_engraving_svg_closures( $svg, $width_mm, $height_mm );
		}
		self::force_svg_node_colour( $svg, $hex );

		$temp = self::temp_path_with_extension( 'oc-colour-clipart-' . wp_generate_uuid4() . '.svg', 'svg' );
		if ( ! is_string( $temp ) || '' === $temp ) {
			return null;
		}

		$output = $dom->saveXML( $dom->documentElement );
		if ( ! is_string( $output ) || false === file_put_contents( $temp, $output ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			@unlink( $temp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return null;
		}

		return $temp;
	}

	/**
	 * Repair near-closed engraving cubics within five microns at final placement.
	 *
	 * This is under 1/8 of a 600 DPI pixel. The larger viewport axis scale is
	 * conservative for meet, slice and nonuniform placement. Do not guess at
	 * transformed/nested/resource coordinate systems or CSS geometry. Resources
	 * can scale local units independently even in the legacy vector fallback.
	 * No size means no fix.
	 */
	private static function repair_engraving_svg_closures( \DOMElement $svg, float $width_mm, float $height_mm ): void {
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM API property.
		$xpath = new \DOMXPath( $svg->ownerDocument );
		if ( ! is_finite( $width_mm ) || ! is_finite( $height_mm ) || $width_mm <= 0 || $height_mm <= 0
			|| $xpath->query( '//*[@transform] | /*//*[local-name()="svg"] | //*[local-name()="clipPath" or local-name()="mask" or local-name()="pattern" or local-name()="marker" or local-name()="symbol" or local-name()="use"]' )->length > 0 ) {
			return;
		}
		foreach ( $xpath->query( '//*[@style] | //*[local-name()="style"]' ) as $node ) {
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM API property.
			$css = $node->getAttribute( 'style' ) . $node->textContent;
			if ( str_contains( $css, '\\' ) || str_contains( $css, '/*' )
				|| preg_match( '/(?:^|[;{])\s*(?:transform(?:-origin|-box)?|translate|rotate|scale|offset(?:-path)?|zoom|d)\s*:/i', $css ) ) {
				return;
			}
		}
		try {
			if ( $svg->hasAttribute( 'viewBox' ) ) {
				$box = preg_split( '/[\s,]+/', trim( $svg->getAttribute( 'viewBox' ) ) );
				if ( count( $box ) !== 4 || count( array_filter( $box, 'is_numeric' ) ) !== 4 ) {
					return;
				}
				$width  = (float) $box[2];
				$height = (float) $box[3];
			} else {
				$width  = self::svg_absolute_length_px( $svg->getAttribute( 'width' ) );
				$height = self::svg_absolute_length_px( $svg->getAttribute( 'height' ) );
			}
			if ( ! is_finite( $width ) || ! is_finite( $height ) || $width <= 0 || $height <= 0 ) {
				return;
			}
			self::normalise_svg_paths_for_tcpdf( $svg, 0.005 / max( $width_mm / $width, $height_mm / $height ) );
		} catch ( \RuntimeException $e ) {
			// Missing absolute dimensions: retain the original geometry.
			return;
		}
	}

	private static function force_svg_node_colour( \DOMElement $element, string $hex, ?\SplObjectStorage $resources = null ): void {
		if ( null === $resources ) {
			$resources = new \SplObjectStorage();
			$dom       = $element->ownerDocument; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM property.
			$xpath     = new \DOMXPath( $dom );
			// Definitions are also used for visible artwork. Protect only paint/mask
			// resources and their referenced subtrees, rather than the whole <defs>.
			$pending = iterator_to_array( $xpath->query( '//*[local-name()="mask" or local-name()="clipPath" or local-name()="filter" or local-name()="linearGradient" or local-name()="radialGradient" or local-name()="pattern" or local-name()="metadata"]' ) );
			while ( $pending ) {
				$node = array_pop( $pending );
				if ( $resources->contains( $node ) ) {
					continue;
				}
				$resources->attach( $node );
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM property.
				foreach ( $node->childNodes as $child ) {
					if ( $child instanceof \DOMElement ) {
						$pending[] = $child;
					}
				}
				$href = $node->getAttribute( 'href' );
				$href = $href ? $href : $node->getAttributeNS( 'http://www.w3.org/1999/xlink', 'href' );
				if ( str_starts_with( $href, '#' ) ) {
					foreach ( $xpath->query( '//*[@id=' . self::xpath_literal( substr( $href, 1 ) ) . ']' ) as $reference ) {
						$pending[] = $reference;
					}
				}
			}
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM property.
			self::inline_svg_presentation_styles( $dom, $dom->documentElement );
		}
		if ( $resources->contains( $element ) ) {
			return;
		}
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM property.
		if ( 'style' === strtolower( $element->localName ) ) {
			// Shared CSS must retain the resource paints. Visible nodes receive
			// inline overrides below, which also win over these original rules.
			if ( count( $resources ) > 0 ) {
				return;
			}
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM property.
			$element->nodeValue = self::force_svg_css_colour( $element->nodeValue ?? '', $hex );
			return;
		}

		if ( $element->hasAttribute( 'fill' ) && 'none' !== strtolower( trim( $element->getAttribute( 'fill' ) ) ) ) {
			$element->setAttribute( 'fill', $hex );
		}
		if ( $element->hasAttribute( 'stroke' ) && 'none' !== strtolower( trim( $element->getAttribute( 'stroke' ) ) ) ) {
			$element->setAttribute( 'stroke', $hex );
		}
		if ( $element->hasAttribute( 'style' ) ) {
			$element->setAttribute( 'style', self::force_svg_style_colour( $element->getAttribute( 'style' ), $hex ) );
		}
		if ( count( $resources ) > 0 ) {
			foreach ( [ 'fill', 'stroke' ] as $paint ) {
				if ( $element->hasAttribute( $paint ) ) {
					$element->setAttribute( 'style', rtrim( $element->getAttribute( 'style' ), '; ' ) . ';' . $paint . ':' . $element->getAttribute( $paint ) . ' !important' );
				}
			}
		}

		foreach ( $element->childNodes as $child ) {
			if ( $child instanceof \DOMElement ) {
				self::force_svg_node_colour( $child, $hex, $resources );
			}
		}
	}

	private static function force_svg_style_colour( string $style, string $hex ): string {
		$parts = array_filter( array_map( 'trim', explode( ';', $style ) ) );
		foreach ( $parts as &$part ) {
			if ( preg_match( '/^\s*(fill|stroke)\s*:/i', $part ) && ! preg_match( '/:\s*none\s*$/i', $part ) ) {
				$property = trim( (string) strtok( $part, ':' ) );
				$part = $property . ':' . $hex;
			}
		}

		return implode( ';', $parts );
	}

	private static function force_svg_css_colour( string $css, string $hex ): string {
		return (string) preg_replace_callback(
			'/\b(fill|stroke)\s*:\s*([^;}]+)/i',
			static function ( array $matches ) use ( $hex ): string {
				$value = strtolower( trim( (string) $matches[2] ) );
				return 'none' === $value ? $matches[0] : $matches[1] . ':' . $hex;
			},
			$css
		);
	}

	private static function build_black_raster( string $path ): ?string {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			return null;
		}

		$src = self::open_raster_resource( $path );
		if ( ! $src ) {
			return null;
		}

		$w = imagesx( $src );
		$h = imagesy( $src );

		$dst = imagecreatetruecolor( $w, $h );
		imagealphablending( $dst, false );
		imagesavealpha( $dst, true );

		for ( $y = 0; $y < $h; $y++ ) {
			for ( $x = 0; $x < $w; $x++ ) {
				$rgba  = imagecolorat( $src, $x, $y );
				$alpha = ( $rgba >> 24 ) & 0x7F;
				$black = imagecolorallocatealpha( $dst, 0, 0, 0, $alpha );
				imagesetpixel( $dst, $x, $y, $black );
			}
		}
		imagedestroy( $src );

		$temp = self::temp_path_with_extension( 'oc-black-clipart-' . wp_generate_uuid4() . '.png', 'png' );
		if ( ! is_string( $temp ) || '' === $temp ) {
			imagedestroy( $dst );
			return null;
		}

		$result = imagepng( $dst, $temp );
		imagedestroy( $dst );
		if ( ! $result ) {
			@unlink( $temp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return null;
		}

		return $temp;
	}

	private static function open_raster_resource( string $path, int $max_dimension = self::MAX_WORK_RASTER_DIMENSION, int $max_pixels = self::MAX_WORK_RASTER_PIXELS ) {
		self::assert_safe_raster_dimensions( $path );
		$ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		$image = match ( $ext ) {
			'jpg', 'jpeg' => @imagecreatefromjpeg( $path ), // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			'png' => @imagecreatefrompng( $path ), // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			'webp' => function_exists( 'imagecreatefromwebp' ) ? @imagecreatefromwebp( $path ) : false, // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			'bmp' => function_exists( 'imagecreatefrombmp' ) ? @imagecreatefrombmp( $path ) : false, // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			'gif' => @imagecreatefromgif( $path ), // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			default => false,
		};
		if ( ! $image ) {
			return false;
		}

		return self::bounded_gd_resource( $image, $max_dimension, $max_pixels );
	}

	/** Downsample expensive per-pixel work while preserving transparency and aspect ratio. */
	protected static function bounded_gd_resource( $image, int $max_dimension = self::MAX_WORK_RASTER_DIMENSION, int $max_pixels = self::MAX_WORK_RASTER_PIXELS ) {
		if ( function_exists( 'imageistruecolor' ) && ! imageistruecolor( $image ) && function_exists( 'imagepalettetotruecolor' ) ) {
			imagepalettetotruecolor( $image );
			imagealphablending( $image, false );
			imagesavealpha( $image, true );
		}
		$width  = imagesx( $image );
		$height = imagesy( $image );
		[ $target_w, $target_h ] = self::bounded_work_dimensions( $width, $height, $max_dimension, $max_pixels );
		if ( $target_w === $width && $target_h === $height ) {
			return $image;
		}

		$resized = imagecreatetruecolor( $target_w, $target_h );
		imagealphablending( $resized, false );
		imagesavealpha( $resized, true );
		$transparent = imagecolorallocatealpha( $resized, 0, 0, 0, 127 );
		imagefilledrectangle( $resized, 0, 0, $target_w, $target_h, $transparent );
		imagecopyresampled( $resized, $image, 0, 0, 0, 0, $target_w, $target_h, $width, $height );
		imagedestroy( $image );

		return $resized;
	}

	protected static function build_filtered_image( string $path, array $layer, array $input, float $w_mm = 0.0, float $h_mm = 0.0, int $dpi = 600 ): ?string {
		$target    = ( 0.0 !== $w_mm || 0.0 !== $h_mm ) ? self::print_raster_dimensions( $w_mm, $h_mm, $dpi, self::MAX_RASTER_DIMENSION, self::MAX_RASTER_PIXELS ) : null;
		$filter_id = absint( $input['imageFilterId'] ?? 0 );
		if ( ! $filter_id ) {
			return null;
		}

		$settings    = is_array( $layer['settings'] ?? null ) ? $layer['settings'] : [];
		$allowed_ids = array_values( array_filter( array_map( 'absint', is_array( $settings['image_filter_ids'] ?? null ) ? $settings['image_filter_ids'] : [] ) ) );
		if ( ! in_array( $filter_id, $allowed_ids, true ) ) {
			return null;
		}

		$key   = sanitize_key( (string) ( $input['imageFilterKey'] ?? '' ) );
		$value = is_numeric( $input['imageFilterValue'] ?? null ) ? (float) $input['imageFilterValue'] : 0.0;
		if ( '' === $key ) {
			$filter = null;
			foreach ( OC_DB::get_image_filters( true ) as $candidate ) {
				if ( (int) $candidate->id === $filter_id ) {
					$filter = $candidate;
					break;
				}
			}
			if ( ! $filter ) {
				return null;
			}
			$key   = sanitize_key( (string) $filter->filter_key );
			$value = (float) $filter->value;
		}
		$colour = ! empty( $settings['enable_image_colour'] )
			? sanitize_hex_color( (string) ( $input['colorHex'] ?? $settings['default_color'] ?? '' ) )
			: null;
		if ( 'ai' === $key && ! $colour ) {
			return $path;
		}
		if ( ! function_exists( 'imagefilter' ) ) {
			return null;
		}

		if ( null !== $target ) {
			[ $target_w, $target_h ] = $target;
			$src                     = self::open_raster_resource( $path, max( $target_w, $target_h ), $target_w * $target_h );
		} else {
			$src = self::open_raster_resource( $path, 2048, 4000000 );
		}
		if ( ! $src ) {
			return null;
		}
		imagealphablending( $src, false );
		imagesavealpha( $src, true );

		$ok    = match ( $key ) {
			'ai'         => true,
			'grayscale'  => imagefilter( $src, IMG_FILTER_GRAYSCALE ),
			'sepia'      => imagefilter( $src, IMG_FILTER_GRAYSCALE ) && imagefilter( $src, IMG_FILTER_COLORIZE, 90, 45, 0 ),
			'brightness' => imagefilter( $src, IMG_FILTER_BRIGHTNESS, max( -255, min( 255, (int) round( $value * 255 ) ) ) ),
			'contrast'   => imagefilter( $src, IMG_FILTER_CONTRAST, max( -100, min( 100, (int) round( -100 * $value ) ) ) ),
			'saturation' => self::adjust_raster_saturation( $src, $value ),
			'hue'        => self::adjust_raster_hue( $src, $value ),
			default      => false,
		};

		if ( ! $ok ) {
			imagedestroy( $src );
			return null;
		}
		if ( $colour ) {
			self::recolour_raster_pixels( $src, $colour );
		}

		$temp = self::temp_path_with_extension( 'oc-filtered-image-' . wp_generate_uuid4() . '.png', 'png' );
		if ( ! is_string( $temp ) || '' === $temp ) {
			imagedestroy( $src );
			return null;
		}

		$result = imagepng( $src, $temp );
		imagedestroy( $src );
		if ( ! $result ) {
			@unlink( $temp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return null;
		}

		return $temp;
	}

	/** Replace visible filtered pixels with one production colour while retaining alpha. */
	private static function recolour_raster_pixels( $img, string $hex ): void {
		$rgb = sscanf( ltrim( $hex, '#' ), '%02x%02x%02x' );
		if ( ! is_array( $rgb ) || 3 !== count( $rgb ) ) {
			return;
		}
		$w = imagesx( $img );
		$h = imagesy( $img );
		$colours = [];
		for ( $y = 0; $y < $h; $y++ ) {
			for ( $x = 0; $x < $w; $x++ ) {
				$rgba = imagecolorat( $img, $x, $y );
				$alpha = ( $rgba >> 24 ) & 0x7F;
				$colours[ $alpha ] ??= imagecolorallocatealpha( $img, $rgb[0], $rgb[1], $rgb[2], $alpha );
				imagesetpixel( $img, $x, $y, $colours[ $alpha ] );
			}
		}
	}

	private static function adjust_raster_saturation( $img, float $amount ): bool {
		$w      = imagesx( $img );
		$h      = imagesy( $img );
		$factor = max( 0.0, 1.0 + $amount );
		for ( $y = 0; $y < $h; $y++ ) {
			for ( $x = 0; $x < $w; $x++ ) {
				$rgba  = imagecolorat( $img, $x, $y );
				$a     = ( $rgba >> 24 ) & 0x7F;
				$r     = ( $rgba >> 16 ) & 0xFF;
				$g     = ( $rgba >> 8 ) & 0xFF;
				$b     = $rgba & 0xFF;
				$gray  = ( $r + $g + $b ) / 3;
				$color = imagecolorallocatealpha(
					$img,
					self::clamp_rgb( $gray + ( $r - $gray ) * $factor ),
					self::clamp_rgb( $gray + ( $g - $gray ) * $factor ),
					self::clamp_rgb( $gray + ( $b - $gray ) * $factor ),
					$a
				);
				imagesetpixel( $img, $x, $y, $color );
			}
		}
		return true;
	}

	private static function adjust_raster_hue( $img, float $amount ): bool {
		$w = imagesx( $img );
		$h = imagesy( $img );
		$angle = $amount * 2 * M_PI;
		$cos = cos( $angle );
		$sin = sin( $angle );
		for ( $y = 0; $y < $h; $y++ ) {
			for ( $x = 0; $x < $w; $x++ ) {
				$rgba = imagecolorat( $img, $x, $y );
				$a    = ( $rgba >> 24 ) & 0x7F;
				$r    = ( $rgba >> 16 ) & 0xFF;
				$g    = ( $rgba >> 8 ) & 0xFF;
				$b    = $rgba & 0xFF;
				$new_r = ( .213 + $cos * .787 - $sin * .213 ) * $r + ( .715 - $cos * .715 - $sin * .715 ) * $g + ( .072 - $cos * .072 + $sin * .928 ) * $b;
				$new_g = ( .213 - $cos * .213 + $sin * .143 ) * $r + ( .715 + $cos * .285 + $sin * .140 ) * $g + ( .072 - $cos * .072 - $sin * .283 ) * $b;
				$new_b = ( .213 - $cos * .213 - $sin * .787 ) * $r + ( .715 - $cos * .715 + $sin * .715 ) * $g + ( .072 + $cos * .928 + $sin * .072 ) * $b;
				$color = imagecolorallocatealpha( $img, self::clamp_rgb( $new_r ), self::clamp_rgb( $new_g ), self::clamp_rgb( $new_b ), $a );
				imagesetpixel( $img, $x, $y, $color );
			}
		}
		return true;
	}

	private static function clamp_rgb( float $value ): int {
		return max( 0, min( 255, (int) round( $value ) ) );
	}
}
