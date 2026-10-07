<?php
/**
 * Production PDF facade with PDF/X and local artwork support.
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

/**
 * Keep this class named: TCPDF serializes the facade for transaction snapshots.
 * Loaded only after the TCPDF dependency has been initialized.
 */
class OC_Print_PDF extends \TCPDF {

	/** Defer TCPDF's implicit constructor font until font metrics are needed. */
	public function setFont( $_family, $_style = '', $_size = null, $_fontfile = '', $_subset = 'default', $_out = true ) {
		if ( 0 === $this->docstate ) {
			// Keep the default for currentFontMetric() to initialise if text is actually drawn.
			$this->fontfamily = strtolower( trim( (string) $_family ) );
			$this->fontstyle  = strtoupper( (string) $_style );
			$this->fontsizept = (float) $_size;
			return;
		}

		parent::setFont( $_family, $_style, $_size, $_fontfile, $_subset, $_out );
	}

	/** SVG style resolution needs font metrics even when its lettering is already paths. */
	public function ImageSVG( $file, $x = '', $y = '', $w = 0, $h = 0, $link = '', $align = '', $palign = '', $border = 0, $fitonpage = false ) {
		$this->currentFontMetric();
		return parent::ImageSVG( $file, $x, $y, $w, $h, $link, $align, $palign, $border, $fitonpage );
	}

	/** Initialise the deferred font for width queries that use the current font. */
	public function GetArrStringWidth( $_sa, $_fontname = '', $_fontstyle = '', $_fontsize = 0, $_getarray = false ) {
		if ( '' === $_fontname ) {
			$this->currentFontMetric();
		}
		return parent::GetArrStringWidth( $_sa, $_fontname, $_fontstyle, $_fontsize, $_getarray );
	}

	/** Initialise the deferred font for individual character measurements. */
	public function getRawCharWidth( $_char ) {
		$this->currentFontMetric();
		return parent::getRawCharWidth( $_char );
	}

	/** Initialise the deferred font for individual character bounds. */
	public function getCharBBox( $_char ) {
		$this->currentFontMetric();
		return parent::getCharBBox( $_char );
	}

	/** Initialise the deferred font for current-font glyph checks. */
	public function isCharDefined( $_char, $_font = '', $_style = '' ) {
		if ( '' === (string) $_font ) {
			$this->currentFontMetric();
		}
		return parent::isCharDefined( $_char, $_font, $_style );
	}

	/** Initialise the deferred font for current-font glyph substitution. */
	public function replaceMissingChars( $_text, $_font = '', $_style = '', $_subs = [] ) {
		if ( '' === (string) $_font ) {
			$this->currentFontMetric();
		}
		return parent::replaceMissingChars( $_text, $_font, $_style, $_subs );
	}

	/** Allow the legacy TCPDF facade to initialise tc-lib-pdf in PDF/X mode. */
	protected function normalizePdfaMode( mixed $pdfa ): string {
		$mode = strtolower( trim( (string) $pdfa ) );
		if ( preg_match( '/^pdfx(?:1a|3|4|5)?$/', $mode ) ) {
			return $mode;
		}

		return parent::normalizePdfaMode( $pdfa );
	}

	/** Include WordPress uploads in TCPDF 7's local file allowlist. */
	protected function fileAllowedPaths(): array {
		$paths      = parent::fileAllowedPaths();
		$upload_dir = wp_upload_dir();

		if ( empty( $upload_dir['error'] ) ) {
			$paths[] = $upload_dir['basedir'];
			$paths[] = trailingslashit( $upload_dir['basedir'] ) . 'overcustomise/tcpdf-fonts';
			$paths[] = trailingslashit( $upload_dir['basedir'] ) . 'overcustomise/tcpdf-cache';
		}
		$private_artwork = class_exists( 'OC_Upload_Handler' ) ? OC_Upload_Handler::private_storage_path( 'artwork' ) : null;
		if ( is_string( $private_artwork ) ) {
			$paths[] = $private_artwork;
		}

		$allowed = [];
		foreach ( $paths as $path ) {
			if ( is_string( $path ) && '' !== $path ) {
				$real      = realpath( $path );
				$allowed[] = false !== $real ? $real : $path;
			}
		}

		return array_values( array_unique( $allowed ) );
	}
}
