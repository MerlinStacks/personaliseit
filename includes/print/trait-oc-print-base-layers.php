<?php
/**
 * Shared layers helpers for print file generators.
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

trait OC_Print_Base_Layers {

	/** Return true when the print payload contains v2 layer geometry. */
	protected static function has_layer_payload( array $area_data ): bool {
		return ! empty( $area_data['layers'] ) && is_array( $area_data['layers'] );
	}

	/** Match the frontend convention: stored ascending layers paint bottom to top. */
	protected static function layer_paint_order( array $layers ): array {
		return array_values( $layers );
	}

	/** Return true when the print payload contains a fully vector snapshot. */
	protected static function has_vector_snapshot_payload( array $area_data ): bool {
		$snapshot = is_array( $area_data['snapshot'] ?? null ) ? $area_data['snapshot'] : [];
		$svg      = is_string( $snapshot['svg'] ?? null ) ? trim( (string) $snapshot['svg'] ) : '';

		return '' !== $svg && str_contains( $svg, '<svg' ) && ! preg_match( '/<image\b/i', $svg );
	}

	/** Render the browser-captured vector snapshot so PDF output matches the customer preview. */
	protected static function render_vector_snapshot_payload( \TCPDF $pdf, array $area_data, float $x_mm, float $y_mm, float $w_mm, float $h_mm ): bool {
		if ( ! self::has_vector_snapshot_payload( $area_data ) ) {
			return false;
		}

		$snapshot = is_array( $area_data['snapshot'] ?? null ) ? $area_data['snapshot'] : [];
		$svg      = (string) $snapshot['svg'];
		if ( strlen( $svg ) > self::MAX_SVG_BYTES ) {
			throw new \RuntimeException( __( 'Vector snapshot exceeds the safe production size limit.', 'overcustomise' ) );
		}
		$temp     = self::temp_path_with_extension( 'oc-vector-snapshot-' . wp_generate_uuid4() . '.svg', 'svg' );
		if ( ! is_string( $temp ) || '' === $temp ) {
			return false;
		}

		if ( false === file_put_contents( $temp, $svg ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			@unlink( $temp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return false;
		}

		try {
			self::draw_pdf_svg( $pdf, $temp, $x_mm, $y_mm, $w_mm, $h_mm );
			return true;
		} catch ( \Throwable $e ) {
			OC_Logger::warning( 'Vector snapshot render failed, falling back to layer payload: ' . $e->getMessage() );
			return false;
		} finally {
			@unlink( $temp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
	}

	/** Render a vector snapshot's visible alpha using the active spot colour. */
	protected static function render_vector_snapshot_spot_mask( \TCPDF $pdf, array $area_data, float $x_mm, float $y_mm, float $w_mm, float $h_mm ): bool {
		if ( ! self::has_vector_snapshot_payload( $area_data ) ) {
			return false;
		}

		$svg = (string) $area_data['snapshot']['svg'];
		if ( strlen( $svg ) > self::MAX_SVG_BYTES ) {
			throw new \RuntimeException( __( 'Vector snapshot exceeds the safe production size limit.', 'overcustomise' ) );
		}
		$temp = self::temp_path_with_extension( 'oc-vector-spot-snapshot-' . wp_generate_uuid4() . '.svg', 'svg' );
		if ( ! is_string( $temp ) || '' === $temp || false === file_put_contents( $temp, $svg ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			if ( is_string( $temp ) ) {
				@unlink( $temp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
			return false;
		}

		try {
			self::render_artwork_spot_mask( $pdf, $temp, $x_mm, $y_mm, $w_mm, $h_mm );
			return true;
		} finally {
			@unlink( $temp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
	}

	/**
	 * Convert quarter-turn v2 areas to a flat artboard. The source dimensions and
	 * turn are retained on the cloned row so layer coordinates can be transformed
	 * rather than stretched into the swapped dimensions.
	 *
	 * @return array{0:object,1:float,2:float}
	 */
	protected static function normalise_rotated_artboard_for_print( object $area, array $area_data ): array {
		[ $w_mm, $h_mm ] = self::area_dimensions_mm( $area );

		if ( ! self::has_layer_payload( $area_data ) ) {
			return [ $area, $w_mm, $h_mm ];
		}

		$bounds   = is_array( $area_data['bounds'] ?? null ) ? $area_data['bounds'] : [];
		$rotation = fmod( (float) ( $bounds['rotation'] ?? $area->canvas_rotation ?? 0 ), 360.0 );
		$rotation = $rotation < 0.0 ? $rotation + 360.0 : $rotation;
		if ( abs( $rotation - 90.0 ) >= 0.001 && abs( $rotation - 270.0 ) >= 0.001 ) {
			return [ $area, $w_mm, $h_mm ];
		}

		$flat_area = clone $area;
		$flat_area->_oc_source_canvas_w = (float) ( $area->canvas_w ?? $bounds['w'] ?? 1 );
		$flat_area->_oc_source_canvas_h = (float) ( $area->canvas_h ?? $bounds['h'] ?? 1 );
		$flat_area->_oc_print_quarter_turn = (int) round( $rotation );
		$flat_area->canvas_w = (float) ( $area->canvas_h ?? $bounds['h'] ?? $area->canvas_w ?? 1 );
		$flat_area->canvas_h = (float) ( $area->canvas_w ?? $bounds['w'] ?? $area->canvas_h ?? 1 );
		$flat_area->canvas_rotation = 0;

		return [ $flat_area, $h_mm, $w_mm ];
	}

	/**
	 * Lay combined print areas out from left to right on one production sheet.
	 *
	 * @param array<int,array{area:object,area_data:array}> $areas
	 * @return array{entries:array<int,array{area:object,area_data:array,x:float,y:float,w:float,h:float}>,page_w:float,page_h:float}
	 */
	protected static function combined_sheet_layout( array $areas, float $inset = 0.0, float $gap = 5.0 ): array {
		$entries = [];
		$cursor  = 0.0;
		$page_h  = 0.0;
		$inset   = max( 0.0, $inset );
		$gap     = max( 0.0, $gap );

		foreach ( $areas as $entry ) {
			if ( ! is_array( $entry ) || ! isset( $entry['area'], $entry['area_data'] ) || ! is_object( $entry['area'] ) || ! is_array( $entry['area_data'] ) ) {
				continue;
			}

			[ $area, $w_mm, $h_mm ] = self::normalise_rotated_artboard_for_print( $entry['area'], $entry['area_data'] );
			$entries[] = [
				'area'      => $area,
				'area_data' => $entry['area_data'],
				'x'         => $cursor + $inset,
				'y'         => $inset,
				'w'         => $w_mm,
				'h'         => $h_mm,
			];
			$cursor += $w_mm + $inset * 2 + $gap;
			$page_h  = max( $page_h, $h_mm + $inset * 2 );
		}

		return [
			'entries' => $entries,
			'page_w'  => max( 0.01, $cursor - ( empty( $entries ) ? 0.0 : $gap ) ),
			'page_h'  => max( 0.01, $page_h ),
		];
	}

	/**
	 * Render v2 layers into the PDF using the same layer boxes as the live preview.
	 * Layer coordinates are stored in mockup pixels, so they are offset back into
	 * print-area space before converting to millimetres.
	 */
	protected static function render_layer_payload( \TCPDF $pdf, object $area, array $area_data, float $origin_x_mm, float $origin_y_mm, string $mode = 'colour', array $options = [] ): void {
		$bounds = is_array( $area_data['bounds'] ?? null ) ? $area_data['bounds'] : [];
		$area_x = isset( $bounds['x'] ) ? (float) $bounds['x'] : (float) ( $area->canvas_x ?? 0 );
		$area_y = isset( $bounds['y'] ) ? (float) $bounds['y'] : (float) ( $area->canvas_y ?? 0 );
		$bounds_w = max( 1.0, (float) ( $bounds['w'] ?? $area->canvas_w ?? 1 ) );
		$bounds_h = max( 1.0, (float) ( $bounds['h'] ?? $area->canvas_h ?? 1 ) );
		[ $area_w_mm, $area_h_mm ] = self::area_dimensions_mm( $area );
		$quarter_turn = (int) ( $area->_oc_print_quarter_turn ?? 0 );
		$font_px_to_pt = self::mm_to_pt_value( in_array( $quarter_turn, [ 90, 270 ], true ) ? $area_w_mm : $area_h_mm ) / $bounds_h;

		foreach ( self::layer_paint_order( $area_data['layers'] ) as $layer ) {
			if ( ! is_array( $layer ) ) {
				continue;
			}

			$type = (string) ( $layer['type'] ?? '' );
			if ( 'mask' === $type ) {
				continue;
			}
			$layer_x = (float) ( $layer['x'] ?? 0 );
			$layer_y = (float) ( $layer['y'] ?? 0 );
			$layer_w = max( 1.0, (float) ( $layer['w'] ?? 1 ) );
			$layer_h = max( 1.0, (float) ( $layer['h'] ?? 1 ) );
			$input = is_array( $layer['input'] ?? null ) ? $layer['input'] : [];
			$settings = is_array( $layer['settings'] ?? null ) ? $layer['settings'] : [];
			$relative_cx = ( $layer_x - $area_x + $layer_w / 2 ) / $bounds_w;
			$relative_cy = ( $layer_y - $area_y + $layer_h / 2 ) / $bounds_h;

			if ( 90 === $quarter_turn ) {
				$center_x = $origin_x_mm + ( 1.0 - $relative_cy ) * $area_w_mm;
				$center_y = $origin_y_mm + $relative_cx * $area_h_mm;
				$w_mm     = ( $layer_w / $bounds_w ) * $area_h_mm;
				$h_mm     = ( $layer_h / $bounds_h ) * $area_w_mm;
			} elseif ( 270 === $quarter_turn ) {
				$center_x = $origin_x_mm + $relative_cy * $area_w_mm;
				$center_y = $origin_y_mm + ( 1.0 - $relative_cx ) * $area_h_mm;
				$w_mm     = ( $layer_w / $bounds_w ) * $area_h_mm;
				$h_mm     = ( $layer_h / $bounds_h ) * $area_w_mm;
			} else {
				$center_x = $origin_x_mm + $relative_cx * $area_w_mm;
				$center_y = $origin_y_mm + $relative_cy * $area_h_mm;
				$w_mm     = ( $layer_w / $bounds_w ) * $area_w_mm;
				$h_mm     = ( $layer_h / $bounds_h ) * $area_h_mm;
			}

			$x_mm = $center_x - $w_mm / 2;
			$y_mm = $center_y - $h_mm / 2;
			if (
				'colour' === $mode
				&& ! empty( $options['full_bleed_artwork'] )
				&& in_array( $type, [ 'image', 'ai_image', 'clipart', 'clipmask' ], true )
			) {
				$bleed_mm = max( 0.0, (float) ( $options['bleed_mm'] ?? 0.0 ) );
				$background = ! empty( $settings['background'] ) || ! empty( $settings['is_background'] ) || ! empty( $settings['full_bleed'] );
				$tolerance = max( 0.01, min( $area_w_mm, $area_h_mm ) * 0.002 );
				$left   = $background || $x_mm <= $origin_x_mm + $tolerance;
				$top    = $background || $y_mm <= $origin_y_mm + $tolerance;
				$right  = $background || $x_mm + $w_mm >= $origin_x_mm + $area_w_mm - $tolerance;
				$bottom = $background || $y_mm + $h_mm >= $origin_y_mm + $area_h_mm - $tolerance;
				if ( $left ) {
					$x_mm -= $bleed_mm;
					$w_mm += $bleed_mm;
				}
				if ( $right ) {
					$w_mm += $bleed_mm;
				}
				if ( $top ) {
					$y_mm -= $bleed_mm;
					$h_mm += $bleed_mm;
				}
				if ( $bottom ) {
					$h_mm += $bleed_mm;
				}
			}
			$rotation = $quarter_turn + self::layer_rotation_degrees( $layer, $input, $settings );
			$rotation = fmod( $rotation, 360.0 );
			$transformed = abs( $rotation ) >= 0.001;
			if ( $transformed ) {
				$pdf->StartTransform();
				// Fabric uses clockwise angles in its top-left coordinate system;
				// TCPDF expects counter-clockwise angles.
				$pdf->Rotate( -$rotation, $center_x, $center_y );
			}

			try {
				switch ( $type ) {
					case 'text':
					case 'textarea':
						self::render_layer_text( $pdf, $layer, $input, $settings, $x_mm, $y_mm, $w_mm, $h_mm, $mode, $font_px_to_pt );
						break;

					case 'spotify':
						self::render_layer_spotify( $pdf, $input, $x_mm, $y_mm, $w_mm, $h_mm, $mode );
						break;

					case 'image':
					case 'ai_image':
					case 'clipart':
						self::render_layer_image( $pdf, $layer, $input, $x_mm, $y_mm, $w_mm, $h_mm, $mode, $options );
						break;

					case 'clipmask':
						self::render_layer_clipped_image( $pdf, $layer, $x_mm, $y_mm, $w_mm, $h_mm, $mode, $options );
						break;

					case 'lineart':
						$hex = (string) ( $input['colorHex'] ?? '#000000' );
						if ( 'engraving' === $mode ) {
							$pdf->SetFillColor( ...self::ENGRAVING_TONE_RGB );
						} elseif ( 'spot' !== $mode ) {
							[ $c, $m, $y, $k ] = self::hex_to_cmyk( $hex );
							$pdf->SetFillColorArray( [ $c, $m, $y, $k ] );
						}
						$pdf->Rect( $x_mm, $y_mm, $w_mm, $h_mm, 'F' );
						break;

					case 'night_sky':
						self::render_layer_night_sky( $pdf, $input, $x_mm, $y_mm, $w_mm, $h_mm, $mode );
						break;
				}
			} finally {
				if ( $transformed ) {
					$pdf->StopTransform();
				}
			}
		}
	}

	/** Draw bounded night-sky primitives directly into production PDFs. */
	private static function render_layer_night_sky( \TCPDF $pdf, array $input, float $x_mm, float $y_mm, float $w_mm, float $h_mm, string $mode ): void {
		$geometry = is_array( $input['nightSkyGeometry'] ?? null ) ? $input['nightSkyGeometry'] : [];
		if ( 1 !== (int) ( $geometry['v'] ?? 0 ) ) {
			return;
		}
		$hex = (string) ( $input['colorHex'] ?? '#000000' );
		if ( 'engraving' === $mode ) {
			$pdf->SetFillColor( ...self::ENGRAVING_TONE_RGB );
			$pdf->SetTextColor( ...self::ENGRAVING_TONE_RGB );
			$pdf->SetDrawColor( ...self::ENGRAVING_TONE_RGB );
		} elseif ( 'spot' !== $mode ) {
			[ $c, $m, $y, $k ] = self::hex_to_cmyk( $hex );
			$pdf->SetFillColorArray( [ $c, $m, $y, $k ] );
			$pdf->SetTextColorArray( [ $c, $m, $y, $k ] );
			$pdf->SetDrawColorArray( [ $c, $m, $y, $k ] );
		}
		$scale = min( $w_mm, $h_mm );
		$pdf->SetAlpha( 0.48 );
		foreach ( is_array( $geometry['segments'] ?? null ) ? $geometry['segments'] : [] as $segment ) {
			$x1     = $x_mm + (float) $segment['x1'] * $w_mm;
			$y1     = $y_mm + (float) $segment['y1'] * $h_mm;
			$x2     = $x_mm + (float) $segment['x2'] * $w_mm;
			$y2     = $y_mm + (float) $segment['y2'] * $h_mm;
			$half   = max( 0.025, (float) $segment['w'] * $scale / 2 );
			$length = max( 0.0001, hypot( $x2 - $x1, $y2 - $y1 ) );
			$px     = -( $y2 - $y1 ) / $length * $half;
			$py     = ( $x2 - $x1 ) / $length * $half;
			$pdf->Polygon( [ $x1 + $px, $y1 + $py, $x2 + $px, $y2 + $py, $x2 - $px, $y2 - $py, $x1 - $px, $y1 - $py ], 'F' );
		}
		$pdf->SetAlpha( 1.0 );
		foreach ( is_array( $geometry['stars'] ?? null ) ? $geometry['stars'] : [] as $star ) {
			$r = max( 0.04, (float) $star['r'] * $scale );
			$pdf->Circle( $x_mm + (float) $star['x'] * $w_mm, $y_mm + (float) $star['y'] * $h_mm, $r, 0, 360, 'F' );
		}
		if ( ! empty( $geometry['border'] ) ) {
			$pdf->Circle( $x_mm + $w_mm / 2, $y_mm + $h_mm / 2, $scale * 0.48, 0, 360, 'D', [ 'width' => max( 0.05, $scale * 0.0025 ), 'dash' => 0 ] );
		}
		$pdf->SetAlpha( 0.78 );
		foreach ( is_array( $geometry['labels'] ?? null ) ? $geometry['labels'] : [] as $label ) {
			$text = sanitize_text_field( (string) ( $label['text'] ?? '' ) );
			if ( '' === $text ) {
				continue;
			}
			$pdf->SetFont( 'helvetica', '', max( 4.0, (float) $label['size'] * $scale * 2.83464567 ) );
			$pdf->Text( $x_mm + (float) $label['x'] * $w_mm - $pdf->GetStringWidth( $text ) / 2, $y_mm + (float) $label['y'] * $h_mm, $text, 0, false, true, 0, 0, '', false, '', 0, false, 'C', 'M' );
		}
		$pdf->SetAlpha( 1.0 );
	}

	/** Resolve a layer-local rotation without mixing it with print-area rotation. */
	private static function layer_rotation_degrees( array $layer, array $input, array $settings ): float {
		foreach ( [ $layer['rotation'] ?? null, $input['rotation'] ?? null, $settings['rotation'] ?? null ] as $rotation ) {
			if ( is_numeric( $rotation ) ) {
				return (float) $rotation;
			}
		}

		return 0.0;
	}

	private static function render_layer_spotify( \TCPDF $pdf, array $input, float $x_mm, float $y_mm, float $w_mm, float $h_mm, string $mode ): void {
		$spotify_url = self::build_spotify_code_url( (string) ( $input['spotifyUri'] ?? $input['value'] ?? '' ), 'engraving' === $mode );
		if ( '' === $spotify_url ) {
			throw new \RuntimeException( __( 'The Spotify layer does not contain a valid Spotify URI.', 'overcustomise' ) );
		}

		$svg_path = self::download_spotify_code_svg( $spotify_url );
		if ( ! $svg_path ) {
			throw new \RuntimeException( __( 'The Spotify code could not be retrieved for production.', 'overcustomise' ) );
		}

		try {
			if ( 'spot' === $mode ) {
				self::render_artwork_spot_mask( $pdf, $svg_path, $x_mm, $y_mm, $w_mm, $h_mm );
			} else {
				$pdf->ImageSVG( $svg_path, $x_mm, $y_mm, $w_mm, $h_mm, '', '', '', 0, false );
			}
		} finally {
			@unlink( $svg_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
	}

	protected static function build_spotify_code_url( string $input_value, bool $engraving = false ): string {
		$spotify_uri = self::extract_spotify_uri( $input_value );
		if ( '' === $spotify_uri ) {
			return '';
		}

		return sprintf(
			'https://scannables.scdn.co/uri/plain/svg/FFFFFF/black/640/%s',
			$spotify_uri
		);
	}

	protected static function extract_spotify_uri( string $input_value ): string {
		$raw = trim( $input_value );
		if ( '' === $raw ) {
			return '';
		}

		if ( preg_match( '/^spotify:(track|album|artist|playlist|episode|show):([A-Za-z0-9]{1,128})$/i', $raw, $matches ) ) {
			return sprintf( 'spotify:%s:%s', strtolower( $matches[1] ), $matches[2] );
		}

		$parts = parse_url( $raw );
		if ( ! is_array( $parts ) ) {
			return '';
		}

		$host = strtolower( (string) ( $parts['host'] ?? '' ) );
		if ( ! in_array( $host, [ 'open.spotify.com', 'play.spotify.com' ], true ) ) {
			return '';
		}

		$path_parts  = array_values( array_filter( explode( '/', (string) ( $parts['path'] ?? '' ) ) ) );
		$valid_types = [ 'track', 'album', 'artist', 'playlist', 'episode', 'show' ];

		foreach ( $path_parts as $index => $part ) {
			$part = strtolower( $part );
			if ( str_starts_with( $part, 'intl-' ) ) {
				continue;
			}
			if ( ! in_array( $part, $valid_types, true ) || empty( $path_parts[ $index + 1 ] ) ) {
				continue;
			}

			$id = (string) $path_parts[ $index + 1 ];
			if ( preg_match( '/^[A-Za-z0-9]{1,128}$/', $id ) ) {
				return sprintf( 'spotify:%s:%s', $part, $id );
			}
		}

		return '';
	}

	protected static function download_spotify_code_svg( string $spotify_url ): ?string {
		$response = wp_safe_remote_get( $spotify_url, [
			'timeout'     => 8,
			'redirection' => 2,
			'limit_response_size' => self::MAX_SPOTIFY_RESPONSE_BYTES,
		] );

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$body = wp_remote_retrieve_body( $response );
		if ( ! is_string( $body ) || strlen( $body ) > self::MAX_SPOTIFY_RESPONSE_BYTES || ! str_contains( $body, '<svg' ) ) {
			return null;
		}

		$temp = self::temp_path_with_extension( 'oc-spotify-code-' . wp_generate_uuid4() . '.svg', 'svg' );
		if ( ! is_string( $temp ) || '' === $temp ) {
			return null;
		}

		if ( class_exists( 'OC_SVG_Sanitiser' ) ) {
			try {
				$body = OC_SVG_Sanitiser::sanitise( $body );
			} catch ( \InvalidArgumentException $e ) {
				return null;
			}
		}
		$body = self::make_spotify_svg_background_transparent( $body );

		if ( false === file_put_contents( $temp, $body ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			@unlink( $temp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return null;
		}

		return $temp;
	}

	private static function make_spotify_svg_background_transparent( string $svg ): string {
		$white = '(?:#fff(?:fff)?|white|rgb\(\s*255\s*,\s*255\s*,\s*255\s*\)|rgba\(\s*255\s*,\s*255\s*,\s*255\s*,\s*1\s*\))';
		$svg   = preg_replace( '/(\sfill=["\'])' . $white . '(["\'])/i', '$1none$2', $svg ) ?? $svg;
		$svg   = preg_replace( '/(\sstroke=["\'])' . $white . '(["\'])/i', '$1none$2', $svg ) ?? $svg;
		$svg   = preg_replace( '/(fill\s*:\s*)' . $white . '/i', '$1none', $svg ) ?? $svg;
		$svg   = preg_replace( '/(stroke\s*:\s*)' . $white . '/i', '$1none', $svg ) ?? $svg;

		return $svg;
	}

	private static function render_layer_image( \TCPDF $pdf, array $layer, array $input, float $x_mm, float $y_mm, float $w_mm, float $h_mm, string $mode = 'colour', array $options = [] ): void {
		$path = self::resolve_artwork_path( array_merge( $input, $layer ) );
		if ( ! $path ) {
			if ( absint( $input['attachmentId'] ?? 0 ) > 0 || absint( $layer['artworkAttachmentId'] ?? 0 ) > 0 || absint( $input['clipartId'] ?? 0 ) > 0 ) {
				throw new \RuntimeException( __( 'A selected artwork layer no longer has a readable production file.', 'overcustomise' ) );
			}
			return;
		}

		$temp_paths = [];
		if ( 'svg' === strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) ) {
			self::load_print_svg( $path );
		}
		if (
			'colour' === $mode
			&& 'clipart' === (string) ( $layer['type'] ?? '' )
			&& ! empty( $input['clipartRecolourable'] )
		) {
			$hex = sanitize_hex_color( (string) ( $input['colorHex'] ?? '' ) );
			if ( $hex ) {
				$coloured_path = self::build_coloured_clipart( $path, $hex );
				if ( is_string( $coloured_path ) && '' !== $coloured_path ) {
					$temp_paths[] = $coloured_path;
					$path         = $coloured_path;
				}
			}
		}
		if ( in_array( (string) ( $layer['type'] ?? '' ), [ 'image', 'ai_image' ], true ) ) {
			$filter_h = 0.0;
			$filter_w = 0.0;
			if ( 'engraving' === $mode ) {
				[ , , $filter_w, $filter_h ] = self::fit_artwork_box( $path, $x_mm, $y_mm, $w_mm, $h_mm, max( 0.0, min( 1.0, absint( $input['imageCrop'] ?? 0 ) / 100 ) ) );
			}
			$filtered_path = self::build_filtered_image( $path, $layer, $input, $filter_w, $filter_h, (int) ( $options['engraving_profile']['dpi'] ?? 600 ) );
			if ( is_string( $filtered_path ) && '' !== $filtered_path ) {
				if ( $filtered_path !== $path ) {
					$temp_paths[] = $filtered_path;
				}
				$path         = $filtered_path;
			} elseif ( absint( $input['imageFilterId'] ?? 0 ) > 0 ) {
				throw new \RuntimeException( __( 'The selected image filter could not be reproduced for production.', 'overcustomise' ) );
			}
		}
		if ( 'engraving' === $mode ) {
			$crop_amount                       = in_array( (string) ( $layer['type'] ?? '' ), [ 'image', 'ai_image' ], true )
				? max( 0.0, min( 1.0, absint( $input['imageCrop'] ?? 0 ) / 100 ) )
				: 0.0;
			[ , , $engraving_w, $engraving_h ] = self::fit_artwork_box( $path, $x_mm, $y_mm, $w_mm, $h_mm, $crop_amount );
			if ( 'clipart' === (string) ( $layer['type'] ?? '' ) ) {
				$engraved_path = self::build_black_clipart( $path );
				if ( ! is_string( $engraved_path ) || '' === $engraved_path ) {
					throw new \RuntimeException( __( 'The selected clipart could not be prepared for engraving.', 'overcustomise' ) );
				}
			} else {
				if ( ! class_exists( 'OC_Print_Engraving' ) ) {
					throw new \RuntimeException( __( 'The engraving artwork converter is unavailable.', 'overcustomise' ) );
				}
				$engraved_path = OC_Print_Engraving::prepare_artwork_for_layer( $path, is_array( $options['engraving_profile'] ?? null ) ? $options['engraving_profile'] : [], $engraving_w, $engraving_h );
			}
			$temp_paths[]  = $engraved_path;
			$path          = $engraved_path;
		}

		$crop_amount                           = in_array( (string) ( $layer['type'] ?? '' ), [ 'image', 'ai_image' ], true )
			? max( 0.0, min( 1.0, absint( $input['imageCrop'] ?? 0 ) / 100 ) )
			: 0.0;
		[ $draw_x, $draw_y, $draw_w, $draw_h ] = self::fit_artwork_box( $path, $x_mm, $y_mm, $w_mm, $h_mm, $crop_amount );
		$clip_to_layer = $crop_amount > 0.0;

		try {
			if ( $clip_to_layer ) {
				$pdf->StartTransform();
				$pdf->Rect( $x_mm, $y_mm, $w_mm, $h_mm, 'CNZ' );
			}
			if ( 'spot' === $mode ) {
				self::render_artwork_spot_mask( $pdf, $path, $draw_x, $draw_y, $draw_w, $draw_h );
			} else {
				self::draw_pdf_image( $pdf, $path, $draw_x, $draw_y, $draw_w, $draw_h );
			}
		} finally {
			if ( $clip_to_layer ) {
				$pdf->StopTransform();
			}
			foreach ( array_unique( $temp_paths ) as $temp_path ) {
				if ( is_string( $temp_path ) && '' !== $temp_path && file_exists( $temp_path ) ) {
					@unlink( $temp_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				}
			}
		}
	}

	private static function render_layer_clipped_image( \TCPDF $pdf, array $layer, float $x_mm, float $y_mm, float $w_mm, float $h_mm, string $mode = 'colour', array $options = [] ): void {
		$input = is_array( $layer['input'] ?? null ) ? $layer['input'] : [];
		$path  = self::resolve_artwork_path( array_merge( $input, $layer ) );
		if ( ! $path ) {
			if ( absint( $input['attachmentId'] ?? 0 ) > 0 || absint( $layer['artworkAttachmentId'] ?? 0 ) > 0 ) {
				throw new \RuntimeException( __( 'A selected clipped artwork layer no longer has a readable production file.', 'overcustomise' ) );
			}
			return;
		}
		$temp_path = null;
		if ( 'engraving' === $mode ) {
			if ( 'svg' === strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) ) {
				self::load_print_svg( $path );
			}
			if ( ! class_exists( 'OC_Print_Engraving' ) ) {
				throw new \RuntimeException( __( 'The engraving artwork converter is unavailable.', 'overcustomise' ) );
			}
			[ , , $engraving_w, $engraving_h ] = self::fit_artwork_box( $path, $x_mm, $y_mm, $w_mm, $h_mm, 'cover' );
			$temp_path                         = OC_Print_Engraving::prepare_artwork_for_layer( $path, is_array( $options['engraving_profile'] ?? null ) ? $options['engraving_profile'] : [], $engraving_w, $engraving_h );
			$path                              = $temp_path;
		}

		[ $draw_x, $draw_y, $draw_w, $draw_h ] = self::fit_artwork_box( $path, $x_mm, $y_mm, $w_mm, $h_mm, 'cover' );

		$settings = is_array( $layer['settings'] ?? null ) ? $layer['settings'] : [];
		$shape    = sanitize_key( (string) ( $settings['mask_shape'] ?? 'circle' ) );

		$pdf->StartTransform();
		try {
			if ( 'circle' === $shape ) {
				$radius = min( $w_mm, $h_mm ) / 2;
				$pdf->Circle( $x_mm + $w_mm / 2, $y_mm + $h_mm / 2, $radius, 0, 360, 'CNZ' );
			} else {
				$pdf->Rect( $x_mm, $y_mm, $w_mm, $h_mm, 'CNZ' );
			}
			if ( 'spot' === $mode ) {
				self::render_artwork_spot_mask( $pdf, $path, $draw_x, $draw_y, $draw_w, $draw_h );
			} else {
				self::draw_pdf_image( $pdf, $path, $draw_x, $draw_y, $draw_w, $draw_h );
			}
		} finally {
			$pdf->StopTransform();
			if ( is_string( $temp_path ) && '' !== $temp_path && file_exists( $temp_path ) ) {
				@unlink( $temp_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
		}
	}

	/** Fit artwork by intrinsic dimensions, including SVG viewBox dimensions. */
	private static function fit_artwork_box( string $path, float $x, float $y, float $w, float $h, float|string $fit ): array {
		$size = self::artwork_intrinsic_dimensions( $path );
		if ( ! $size ) {
			return [ $x, $y, $w, $h ];
		}

		[ $source_w, $source_h ] = $size;
		$crop_amount  = 'cover' === $fit ? 1.0 : max( 0.0, min( 1.0, (float) $fit ) );
		$contain_scale = min( $w / $source_w, $h / $source_h );
		$cover_scale   = max( $w / $source_w, $h / $source_h );
		$scale         = $contain_scale + ( $cover_scale - $contain_scale ) * $crop_amount;
		$draw_w = $source_w * $scale;
		$draw_h = $source_h * $scale;

		return [ $x + ( $w - $draw_w ) / 2, $y + ( $h - $draw_h ) / 2, $draw_w, $draw_h ];
	}

	/** Return width/height without decoding an unbounded raster. */
	private static function artwork_intrinsic_dimensions( string $path ): ?array {
		if ( 'svg' !== strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) ) {
			return self::assert_safe_raster_dimensions( $path );
		}
		if ( ! class_exists( '\DOMDocument' ) || ! is_readable( $path ) || filesize( $path ) > self::MAX_SVG_BYTES ) {
			return null;
		}

		$dom      = new \DOMDocument();
		$previous = libxml_use_internal_errors( true );
		$loaded   = $dom->load( $path, LIBXML_NONET );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		if ( ! $loaded || ! $dom->documentElement instanceof \DOMElement ) {
			return null;
		}

		$svg = $dom->documentElement;
		$view_box = preg_split( '/[\s,]+/', trim( $svg->getAttribute( 'viewBox' ) ) );
		if ( is_array( $view_box ) && count( $view_box ) >= 4 && (float) $view_box[2] > 0.0 && (float) $view_box[3] > 0.0 ) {
			return [ (float) $view_box[2], (float) $view_box[3] ];
		}

		$width  = (float) $svg->getAttribute( 'width' );
		$height = (float) $svg->getAttribute( 'height' );
		return $width > 0.0 && $height > 0.0 ? [ $width, $height ] : null;
	}
}
