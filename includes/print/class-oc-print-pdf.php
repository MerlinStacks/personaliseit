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
				$real = realpath( $path );
				$allowed[] = false !== $real ? $real : $path;
			}
		}

		return array_values( array_unique( $allowed ) );
	}
}
