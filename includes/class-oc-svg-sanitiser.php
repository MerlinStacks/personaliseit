<?php
/**
 * SVG Sanitiser — strips unsafe content from customer-uploaded SVG files.
 *
 * Removes:
 *  - <script> elements
 *  - <use> elements referencing external resources
 *  - <foreignObject> elements
 *  - Event handler attributes (on*)
 *  - href / xlink:href attributes pointing to external URLs or data: URIs
 *  - Arbitrary JavaScript protocol in any attribute
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

class OC_SVG_Sanitiser {

	/** Dangerous element tag names (case-insensitive). */
	private const BLOCKED_ELEMENTS = [
		'script',
		'foreignobject',
		'animate',
		'set',
		'animatetransform',
		'animatemotion',
	];

	/**
	 * Sanitise an SVG string and return the cleaned SVG.
	 *
	 * @param  string $svg Raw SVG content.
	 * @return string      Sanitised SVG content.
	 * @throws \InvalidArgumentException If the content is not valid XML/SVG.
	 */
	public static function sanitise( string $svg ): string {
		// Strip BOM and leading whitespace.
		$svg = ltrim( $svg, "\xEF\xBB\xBF" );
		$svg = trim( $svg );

		// Must start with either <?xml or <svg.
		if ( ! preg_match( '/<svg[\s>]/i', $svg ) ) {
			throw new \InvalidArgumentException( 'Not a valid SVG file.' );
		}

		$dom = new \DOMDocument();
		$dom->formatOutput = false;

		// Suppress XML parse errors; we'll check $dom->documentElement after.
		$previous = libxml_use_internal_errors( true );
		$loaded   = $dom->loadXML( $svg, LIBXML_NONET );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		if ( ! $loaded || ! $dom->documentElement ) {
			throw new \InvalidArgumentException( 'SVG could not be parsed as XML.' );
		}

		self::clean_node( $dom->documentElement );

		// Serialise back to string, stripping the XML declaration.
		$output = $dom->saveXML( $dom->documentElement );

		return $output ?: '';
	}

	/**
	 * Sanitise a file in-place, overwriting it with the cleaned version.
	 *
	 * @param  string $file_path Absolute path to the SVG file.
	 * @return bool              True on success, false on failure.
	 */
	public static function sanitise_file( string $file_path ): bool {
		if ( ! file_exists( $file_path ) ) {
			return false;
		}

		$raw = file_get_contents( $file_path );
		if ( false === $raw ) {
			return false;
		}

		try {
			$clean = self::sanitise( $raw );
		} catch ( \InvalidArgumentException $e ) {
			OC_Logger::warning( 'SVG sanitiser rejected file: ' . $e->getMessage() );
			return false;
		}

		return (bool) file_put_contents( $file_path, $clean );
	}

	// -------------------------------------------------------------------------
	// Internal
	// -------------------------------------------------------------------------

	/** Recursively clean a DOM node and all its descendants. */
	private static function clean_node( \DOMNode $node ): void {
		// Clean attributes on this node itself (critical for the root <svg> element).
		if ( $node instanceof \DOMElement ) {
			self::clean_attributes( $node );
		}

		/** @var \DOMNode[] $to_remove */
		$to_remove = [];

		foreach ( $node->childNodes as $child ) {
			if ( $child instanceof \DOMElement ) {
				$tag = strtolower( $child->localName );

				// Blocked elements — schedule removal.
				if ( in_array( $tag, self::BLOCKED_ELEMENTS, true ) ) {
					$to_remove[] = $child;
					continue;
				}

				// <use> is allowed only when href is internal (starts with #).
				if ( 'use' === $tag ) {
					$href = $child->getAttribute( 'href' )
						?: $child->getAttributeNS( 'http://www.w3.org/1999/xlink', 'href' );
					if ( $href && ! str_starts_with( ltrim( $href ), '#' ) ) {
						$to_remove[] = $child;
						continue;
					}
				}

				// Recurse — clean_attributes() is called at the top of each recursive call.
				self::clean_node( $child );
			}
		}

		foreach ( $to_remove as $el ) {
			$node->removeChild( $el );
		}
	}

	/** Remove dangerous attributes from a DOMElement. */
	private static function clean_attributes( \DOMElement $el ): void {
		/** @var \DOMAttr[] $to_remove */
		$to_remove = [];

		foreach ( $el->attributes as $attr ) {
			$name      = strtolower( $attr->localName );
			$node_name = strtolower( $attr->nodeName );
			$value     = $attr->value;

			// Event handlers: on*, xml:base.
			if ( str_starts_with( $name, 'on' ) || 'xml:base' === $node_name ) {
				$to_remove[] = $attr;
				continue;
			}

			// href / xlink:href / src pointing outside or to data:/javascript:.
			if ( in_array( $name, [ 'href', 'src', 'action', 'formaction' ], true ) ) {
				if ( self::is_dangerous_url( $value ) ) {
					$to_remove[] = $attr;
					continue;
				}
			}

			// xlink:href.
			if ( 'href' === $attr->localName
			     && 'http://www.w3.org/1999/xlink' === $attr->namespaceURI
			) {
				if ( self::is_dangerous_url( $value ) ) {
					$to_remove[] = $attr;
					continue;
				}
			}

			// style attribute — strip any javascript: or expression().
			if ( 'style' === $name ) {
				if ( preg_match( '/javascript\s*:/i', $value )
				     || preg_match( '/expression\s*\(/i', $value )
				) {
					$to_remove[] = $attr;
					continue;
				}
			}
		}

		foreach ( $to_remove as $attr ) {
			$el->removeAttributeNode( $attr );
		}
	}

	/** Return true if a URL value is unsafe (data:, javascript:, external http). */
	private static function is_dangerous_url( string $value ): bool {
		$value = trim( $value );
		$lower = strtolower( $value );

		if ( str_starts_with( $lower, 'javascript:' ) ) return true;
		if ( str_starts_with( $lower, 'data:' ) )       return true;
		if ( str_starts_with( $lower, 'vbscript:' ) )   return true;

		// External absolute URLs in SVG attributes (allow relative and #anchor).
		if ( preg_match( '/^https?:\/\//i', $value ) )  return true;
		if ( str_starts_with( $value, '//' ) )           return true;

		return false;
	}
}
