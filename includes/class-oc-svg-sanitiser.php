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
	private const MAX_ELEMENTS = 50000;
	private const MAX_DEPTH = 128;
	private const SVG_NAMESPACE = 'http://www.w3.org/2000/svg';

	/** Dangerous element tag names (case-insensitive). */
	private const BLOCKED_ELEMENTS = [
		'script',
		'foreignobject',
		'iframe',
		'object',
		'embed',
		'link',
		'meta',
		'audio',
		'video',
		'animate',
		'set',
		'animatetransform',
		'animatemotion',
	];

	/** CSS properties that are useful for static SVG presentation. */
	private const ALLOWED_STYLE_PROPERTIES = [
		'clip-rule',
		'color',
		'display',
		'dominant-baseline',
		'fill',
		'fill-opacity',
		'fill-rule',
		'font-family',
		'font-size',
		'font-style',
		'font-weight',
		'opacity',
		'paint-order',
		'stop-color',
		'stop-opacity',
		'stroke',
		'stroke-dasharray',
		'stroke-dashoffset',
		'stroke-linecap',
		'stroke-linejoin',
		'stroke-miterlimit',
		'stroke-opacity',
		'stroke-width',
		'text-anchor',
		'vector-effect',
		'visibility',
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
		$root_namespace = (string) $dom->documentElement->namespaceURI;
		if ( '' !== $root_namespace && self::SVG_NAMESPACE !== $root_namespace ) {
			throw new \InvalidArgumentException( 'Root element is not in the SVG namespace.' );
		}
		if ( $dom->getElementsByTagName( '*' )->length > self::MAX_ELEMENTS ) {
			throw new \InvalidArgumentException( 'SVG element count exceeds the safe limit.' );
		}

		$element_count = 0;
		self::clean_node( $dom->documentElement, 0, $element_count );

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
	private static function clean_node( \DOMNode $node, int $depth, int &$element_count ): void {
		if ( $depth > self::MAX_DEPTH ) {
			throw new \InvalidArgumentException( 'SVG nesting exceeds the safe limit.' );
		}

		// Clean attributes on this node itself (critical for the root <svg> element).
		if ( $node instanceof \DOMElement ) {
			$element_count++;
			if ( $element_count > self::MAX_ELEMENTS ) {
				throw new \InvalidArgumentException( 'SVG element count exceeds the safe limit.' );
			}
			self::clean_attributes( $node );
		}

		/** @var \DOMNode[] $to_remove */
		$to_remove = [];

		foreach ( $node->childNodes as $child ) {
			if ( $child instanceof \DOMElement ) {
				$tag       = strtolower( $child->localName );
				$namespace = (string) $child->namespaceURI;
				if ( 'style' === $tag && ( '' === $namespace || self::SVG_NAMESPACE === $namespace ) ) {
					$clean_stylesheet = self::clean_stylesheet( (string) $child->textContent );
					if ( '' === $clean_stylesheet ) {
						$to_remove[] = $child;
					} else {
						self::clean_attributes( $child );
						$child->nodeValue = $clean_stylesheet;
					}
					continue;
				}

				// Remove active elements and content from foreign XML namespaces.
				if ( in_array( $tag, self::BLOCKED_ELEMENTS, true ) || ( '' !== $namespace && self::SVG_NAMESPACE !== $namespace ) ) {
					$to_remove[] = $child;
					continue;
				}

				// <use> is allowed only when href is internal (starts with #).
				if ( 'use' === $tag ) {
					$href = $child->getAttribute( 'href' )
						?: $child->getAttributeNS( 'http://www.w3.org/1999/xlink', 'href' );
					if ( self::is_dangerous_url( $href ) ) {
						$to_remove[] = $child;
						continue;
					}
				}

				// Recurse — clean_attributes() is called at the top of each recursive call.
				self::clean_node( $child, $depth + 1, $element_count );
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
			$namespace = (string) $attr->namespaceURI;

			$allowed_namespaces = [
				'',
				'http://www.w3.org/2000/xmlns/',
				'http://www.w3.org/XML/1998/namespace',
				'http://www.w3.org/1999/xlink',
			];
			if ( ! in_array( $namespace, $allowed_namespaces, true ) ) {
				$to_remove[] = $attr;
				continue;
			}
			if ( 'http://www.w3.org/2000/xmlns/' === $namespace ) {
				if ( ! in_array( $name, [ 'xmlns', 'xlink' ], true ) ) {
					$to_remove[] = $attr;
				}
				continue;
			}

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
				$attr->value = $clean_css;
				continue;
			}
			$normalised_value = preg_replace( '/[\x00-\x20\x7F]+/u', '', html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) ?? '';
			if ( preg_match( '/(?:\\\\|\/\*|(?:javascript|vbscript|data|file|https?|ftp):)/i', $normalised_value ) ) {
				$to_remove[] = $attr;
				continue;
			}

			// Presentation attributes may contain url(...); retain internal fragments only.
			if ( preg_match( '/url\s*\(/i', $value ) ) {
				$clean_value = self::clean_resource_urls( $value );
				if ( null === $clean_value ) {
					$to_remove[] = $attr;
					continue;
				}
				$attr->value = $clean_value;
			}
		}

		foreach ( $to_remove as $attr ) {
			$el->removeAttributeNode( $attr );
		}
	}

	/** Return true if a URL value is unsafe (data:, javascript:, external http). */
	private static function is_dangerous_url( string $value ): bool {
		$value = html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$value = preg_replace( '/[\x00-\x20\x7F]+/u', '', $value ) ?? '';

		// SVG resources may reference definitions in this document only.
		return ! preg_match( '/^#[A-Za-z_][A-Za-z0-9_.:-]*$/D', $value );
	}

	/** Strip CSS constructs that can execute script or fetch external resources. */
	private static function clean_css( string $css ): string {
		if ( preg_match( '/(?:\\\\|@|[{}<>]|\/\*)/', $css ) ) {
			return '';
		}

		$declarations = [];
		foreach ( explode( ';', $css ) as $declaration ) {
			$parts = explode( ':', $declaration, 2 );
			if ( 2 !== count( $parts ) ) {
				continue;
			}
			$property = strtolower( trim( $parts[0] ) );
			$value    = trim( $parts[1] );
			if ( ! in_array( $property, self::ALLOWED_STYLE_PROPERTIES, true ) || '' === $value ) {
				continue;
			}
			if ( preg_match( '/(?:javascript|vbscript|data|file|https?|ftp)\s*:/i', preg_replace( '/[\x00-\x20\x7F]+/u', '', $value ) ?? '' ) ) {
				continue;
			}
			$clean_value = self::clean_resource_urls( $value );
			if ( null === $clean_value ) {
				continue;
			}
			$declarations[] = $property . ':' . $clean_value;
		}

		return implode( ';', $declarations );
	}

	/** Normalise url(...) values, rejecting the whole attribute on any external reference. */
	private static function clean_resource_urls( string $value ): ?string {
		$invalid = false;
		$output  = preg_replace_callback(
			'/url\s*\(\s*(["\']?)(.*?)\1\s*\)/i',
			static function ( array $matches ) use ( &$invalid ): string {
				$url = html_entity_decode( (string) $matches[2], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
				$url = preg_replace( '/[\x00-\x20\x7F]+/u', '', $url ) ?? '';
				if ( ! preg_match( '/^#[A-Za-z_][A-Za-z0-9_.:-]*$/D', $url ) ) {
					$invalid = true;
					return '';
				}

				return 'url(' . $url . ')';
			},
			$value
		);

		return $invalid || ! is_string( $output ) ? null : trim( $output );
	}

	/** Keep simple static selector rules while dropping imports and malformed CSS. */
	private static function clean_stylesheet( string $css ): string {
		if ( preg_match( '/(?:\\\\|@|[<>]|\/\*)/', $css ) ) {
			return '';
		}

		$rules = [];
		if ( preg_match_all( '/([^{}]+)\{([^{}]*)\}/', $css, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$selector = trim( (string) $match[1] );
				if ( '' === $selector || ! preg_match( '/^[A-Za-z0-9_.#,\s>+~*:\[\]="\'\-()]+$/D', $selector ) ) {
					continue;
				}
				$declarations = self::clean_css( (string) $match[2] );
				if ( '' !== $declarations ) {
					$rules[] = $selector . '{' . $declarations . '}';
				}
			}
		}

		return implode( '', $rules );
	}
}
