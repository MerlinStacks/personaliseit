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

		// Hard cap input size (5 MB) to prevent memory abuse from malformed SVGs.
		if ( strlen( $svg ) > 5 * 1024 * 1024 ) {
			throw new \InvalidArgumentException( 'SVG exceeds size limit.' );
		}

		// Must contain an <svg> element.
		if ( ! preg_match( '/<svg[\s>]/i', $svg ) ) {
			throw new \InvalidArgumentException( 'Not a valid SVG file.' );
		}

		// Reject any DOCTYPE to block XXE / billion-laughs entity expansion.
		if ( preg_match( '/<!DOCTYPE/i', $svg ) ) {
			throw new \InvalidArgumentException( 'SVG DOCTYPE declarations are not permitted.' );
		}

		// Strip any XML processing instructions other than the optional prolog.
		if ( preg_match( '/<\?(?!xml\b)[^>]*\?>/i', $svg ) ) {
			throw new \InvalidArgumentException( 'SVG processing instructions are not permitted.' );
		}

		$dom               = new \DOMDocument();
		$dom->formatOutput = false;

		// Belt-and-braces: disable external entity loading on PHP < 8 where
		// this call is still meaningful; it's a no-op on PHP 8+ where libxml
		// 2.9+ disables this by default. Suppress deprecation notices.
		$prev_entity_loader = null;
		if ( PHP_VERSION_ID < 80000 && function_exists( 'libxml_disable_entity_loader' ) ) {
			$prev_entity_loader = @libxml_disable_entity_loader( true ); // phpcs:ignore Generic.PHP.DeprecatedFunctions.Deprecated
		}

		// Suppress XML parse errors; we'll check $dom->documentElement after.
		$previous = libxml_use_internal_errors( true );
		$loaded   = $dom->loadXML( $svg, LIBXML_NONET | LIBXML_NOCDATA );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		if ( null !== $prev_entity_loader && function_exists( 'libxml_disable_entity_loader' ) ) {
			@libxml_disable_entity_loader( $prev_entity_loader ); // phpcs:ignore Generic.PHP.DeprecatedFunctions.Deprecated
		}

		if ( ! $loaded || ! $dom->documentElement ) {
			throw new \InvalidArgumentException( 'SVG could not be parsed as XML.' );
		}

		// Reject anything that isn't actually an SVG at the root.
		if ( 'svg' !== strtolower( $dom->documentElement->localName ) ) {
			throw new \InvalidArgumentException( 'Root element is not <svg>.' );
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
			} elseif ( XML_TEXT_NODE === $child->nodeType && $node instanceof \DOMElement && 'style' === strtolower( $node->localName ) ) {
				$child->nodeValue = self::clean_css( (string) $child->nodeValue );
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
			$name  = strtolower( $attr->localName );
			$value = $attr->value;

			// Event handlers (on*) and xml:base (detected by localName + xml namespace).
			$is_xml_base = 'base' === $name && 'http://www.w3.org/XML/1998/namespace' === $attr->namespaceURI;
			if ( str_starts_with( $name, 'on' ) || $is_xml_base ) {
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

			// style attribute — strip active CSS and external resource fetches.
			if ( 'style' === $name ) {
				$clean_css = self::clean_css( $value );
				if ( '' === trim( $clean_css ) ) {
					$to_remove[] = $attr;
					continue;
				}
				$el->setAttribute( $attr->name, $clean_css );
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

	/** Strip CSS constructs that can execute script or fetch external resources. */
	private static function clean_css( string $css ): string {
		$css = preg_replace( '/@import\b[^;]*(?:;|$)/i', '', $css ) ?? '';
		$css = preg_replace_callback(
			'/url\s*\(\s*(["\']?)(.*?)\1\s*\)/i',
			static function ( array $matches ): string {
				$url = trim( html_entity_decode( (string) $matches[2], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );

				return str_starts_with( $url, '#' ) ? 'url(' . $url . ')' : 'none';
			},
			$css
		) ?? '';
		$css = preg_replace( '/expression\s*\([^)]*\)/i', '', $css ) ?? '';
		$css = preg_replace( '/javascript\s*:/i', '', $css ) ?? '';
		$css = preg_replace( '/vbscript\s*:/i', '', $css ) ?? '';

		return trim( $css );
	}
}
