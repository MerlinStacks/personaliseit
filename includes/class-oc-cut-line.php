<?php
/**
 * Private, inline production cut-line artwork. Never stored as media.
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

class OC_Cut_Line {

	public const MAX_BYTES = 262144;

	/**
	 * Import an uploaded SVG or the first page of a vector PDF as private SVG.
	 *
	 * @param string $path Local upload path.
	 * @param string $extension Validated file extension.
	 * @return string|WP_Error Sanitized geometry or an import error.
	 */
	public static function import_file( string $path, string $extension ): string|WP_Error {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Bounded local upload read.
		$source = file_get_contents( $path, false, null, 0, self::MAX_BYTES + 1 );
		if ( ! is_string( $source ) || '' === $source || strlen( $source ) > self::MAX_BYTES ) {
			return new WP_Error( 'invalid_cut_line', __( 'Upload an SVG or PDF file no larger than 256 KiB.', 'overcustomise' ) );
		}
		if ( 'svg' === $extension ) {
			return self::sanitize( $source );
		}
		if ( 'pdf' !== $extension || ! str_starts_with( $source, '%PDF-' ) ) {
			return new WP_Error( 'invalid_cut_line', __( 'Upload a valid SVG or PDF file.', 'overcustomise' ) );
		}
		$binary   = null;
		$path_env = getenv( 'PATH' );
		foreach ( explode( PATH_SEPARATOR, $path_env ? $path_env : '/usr/local/bin:/usr/bin:/bin' ) as $dir ) {
			$candidate = rtrim( $dir, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR . 'pdftocairo';
			if ( '' !== $dir && is_file( $candidate ) && is_executable( $candidate ) ) {
				$binary = $candidate;
				break;
			}
		}
		if ( ! $binary || ! function_exists( 'proc_open' ) || ! class_exists( 'OC_Command_Runner' ) ) {
			return new WP_Error( 'cut_line_pdf_unavailable', __( 'PDF cut lines require Poppler (pdftocairo) and process execution on the server. Upload an SVG instead or ask your administrator to enable PDF conversion.', 'overcustomise' ) );
		}
		$output = wp_tempnam( 'oc-cut-line.svg' );
		if ( ! $output ) {
			return new WP_Error( 'invalid_cut_line', __( 'Unable to create a temporary file for PDF conversion.', 'overcustomise' ) );
		}
		try {
			$result = OC_Command_Runner::run( [ $binary, '-svg', '-f', '1', '-l', '1', $path, $output ] );
			clearstatcache( true, $output );
			if ( 0 !== $result['code'] || ! is_file( $output ) || filesize( $output ) > self::MAX_BYTES ) {
				return new WP_Error( 'invalid_cut_line', __( 'Unable to convert this PDF. Use an unencrypted vector PDF whose first page converts to no more than 256 KiB of SVG.', 'overcustomise' ) );
			}
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Bounded local conversion output.
			$svg = file_get_contents( $output, false, null, 0, self::MAX_BYTES + 1 );
			// Never silently discard raster artwork or glyph references from a production cut line.
			if ( ! is_string( $svg ) || preg_match( '/<(?:[\w.-]+:)?(?:image|use|text)\b|(?:clip-path|mask)\s*[:=]\s*["\x27]?\s*url\s*\(/i', $svg ) ) {
				return new WP_Error( 'invalid_cut_line', __( 'The PDF must contain vector paths only. Remove images, convert text to outlines, and flatten clipping paths before uploading.', 'overcustomise' ) );
			}
			return self::sanitize( $svg );
		} catch ( Throwable $error ) {
			return new WP_Error( 'invalid_cut_line', __( 'Unable to convert the PDF cut line. Upload an SVG instead.', 'overcustomise' ) );
		} finally {
			wp_delete_file( $output );
		}
	}

	/**
	 * Sanitize static vector geometry without constraining its aspect or coordinates.
	 *
	 * @param mixed $svg Raw inline SVG.
	 * @return string|WP_Error Sanitized inline SVG or a validation error.
	 */
	public static function sanitize( mixed $svg ): string|WP_Error {
		if ( ! is_string( $svg ) || '' === trim( $svg ) || strlen( $svg ) > self::MAX_BYTES ) {
			return new WP_Error( 'invalid_cut_line', __( 'Provide a cut-line SVG no larger than 256 KiB.', 'overcustomise' ) );
		}
		try {
			$clean = OC_SVG_Sanitiser::sanitise( $svg );
			$dom   = new DOMDocument();
			$dom->loadXML( $clean, LIBXML_NONET );
			$allowed    = [ 'svg', 'g', 'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon' ];
			$attributes = [ 'viewBox', 'width', 'height', 'x', 'y', 'x1', 'y1', 'x2', 'y2', 'cx', 'cy', 'r', 'rx', 'ry', 'd', 'points', 'transform', 'preserveAspectRatio', 'fill', 'fill-rule', 'fill-opacity', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin', 'stroke-miterlimit', 'stroke-dasharray', 'stroke-dashoffset', 'stroke-opacity', 'opacity', 'style', 'vector-effect', 'display', 'visibility' ];
			$geometry   = 0;
			// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM properties cannot be renamed.
			$walk = static function ( DOMElement $node ) use ( &$walk, &$geometry, $allowed, $attributes ): void {
				foreach ( iterator_to_array( $node->attributes ) as $attribute ) {
					if ( ! in_array( $attribute->nodeName, $attributes, true ) || preg_match( '/url\s*\(/i', $attribute->value ) ) {
						$node->removeAttributeNode( $attribute );
					}
				}
				foreach ( iterator_to_array( $node->childNodes ) as $child ) {
					if ( ! $child instanceof DOMElement || ! in_array( $child->localName, $allowed, true ) || ! in_array( (string) $child->namespaceURI, [ '', 'http://www.w3.org/2000/svg' ], true ) ) {
						$node->removeChild( $child );
						continue;
					}
					if ( ! in_array( $child->localName, [ 'svg', 'g' ], true ) ) {
						++$geometry;
					}
					$walk( $child );
				}
			};
			$walk( $dom->documentElement );
			self::normalise_viewport( $dom->documentElement );
			$dom->documentElement->setAttribute( 'xmlns', 'http://www.w3.org/2000/svg' );
			$output = $dom->saveXML( $dom->documentElement );
			// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			if ( ! $geometry || ! is_string( $output ) || strlen( $output ) > self::MAX_BYTES ) {
				throw new InvalidArgumentException( 'The cut-line SVG must contain static vector geometry within the size limit.' );
			}
			return $output;
		} catch ( Throwable $error ) {
			return new WP_Error( 'invalid_cut_line', $error->getMessage() );
		}
	}

	/**
	 * Validate the production viewport and derive one from absolute dimensions when absent.
	 *
	 * @param DOMElement $root Root SVG element.
	 * @throws InvalidArgumentException If the viewport cannot be used for printing.
	 */
	private static function normalise_viewport( DOMElement $root ): void {
		if ( $root->hasAttribute( 'viewBox' ) ) {
			$view = preg_split( '/[\s,]+/', trim( $root->getAttribute( 'viewBox' ) ) );
			if ( count( $view ) !== 4 || count( array_filter( $view, static fn ( $v ) => is_numeric( $v ) && is_finite( (float) $v ) ) ) !== 4 || $view[2] <= 0 || $view[3] <= 0 ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Validation data, not HTML output; callers escape at the output boundary.
				throw new InvalidArgumentException( __( 'The cut-line SVG viewBox must contain four finite numbers with positive width and height.', 'overcustomise' ) );
			}
			return;
		}
		$width  = self::svg_absolute_length_px( $root->getAttribute( 'width' ) );
		$height = self::svg_absolute_length_px( $root->getAttribute( 'height' ) );
		$root->setAttribute( 'viewBox', implode( ' ', [ 0, 0, $width, $height ] ) );
	}

	/**
	 * Match the print trait's positive absolute SVG lengths in CSS pixels at 96 DPI.
	 *
	 * @param string $value Intrinsic SVG dimension.
	 * @return float Positive finite pixel length.
	 * @throws InvalidArgumentException If the dimension is missing, relative or invalid.
	 */
	private static function svg_absolute_length_px( string $value ): float {
		$message = __( 'The cut-line SVG requires a valid viewBox or positive, finite width and height in absolute units (px, mm, cm, in, pt, pc, or unitless).', 'overcustomise' );
		if ( ! preg_match( '/\A([+]?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?)(px|mm|cm|in|pt|pc)?\z/', trim( $value ), $match ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Validation data, not HTML output; callers escape at the output boundary.
			throw new InvalidArgumentException( $message );
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
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Validation data, not HTML output; callers escape at the output boundary.
			throw new InvalidArgumentException( $message );
		}
		return $pixels;
	}
}
