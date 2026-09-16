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
			$allowed = [ 'svg', 'g', 'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon' ];
			$attributes = [ 'viewBox', 'width', 'height', 'x', 'y', 'x1', 'y1', 'x2', 'y2', 'cx', 'cy', 'r', 'rx', 'ry', 'd', 'points', 'transform', 'preserveAspectRatio', 'fill', 'fill-rule', 'fill-opacity', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin', 'stroke-miterlimit', 'stroke-dasharray', 'stroke-dashoffset', 'stroke-opacity', 'opacity', 'style', 'vector-effect', 'display', 'visibility' ];
			$geometry = 0;
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
			throw new InvalidArgumentException( $message );
		}
		$scales = [ '' => 1, 'px' => 1, 'mm' => 96 / 25.4, 'cm' => 96 / 2.54, 'in' => 96, 'pt' => 96 / 72, 'pc' => 16 ];
		$pixels = (float) $match[1] * $scales[ $match[2] ?? '' ];
		if ( ! is_finite( $pixels ) || $pixels <= 0 ) {
			throw new InvalidArgumentException( $message );
		}
		return $pixels;
	}
}
