<?php
/**
 * Runtime dependency checks for production workflows.
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

class OC_System_Status {
	/** Return all required and recommended runtime dependency checks. */
	public static function checks(): array {
		global $wp_version;

		$tcpdf_fonts = OC_PATH . 'vendor/tecnickcom/tc-lib-pdf-font/target/fonts/core/helvetica.json';
		$ghostscript = self::ghostscript();

		return [
			self::check( 'php', __( 'PHP 8.2 or newer', 'overcustomise' ), PHP_VERSION_ID >= 80200, PHP_VERSION, true, __( 'Required by OverCustomise and its Composer packages.', 'overcustomise' ) ),
			self::check( 'wordpress', __( 'WordPress 6.8 or newer', 'overcustomise' ), version_compare( (string) $wp_version, '6.8', '>=' ), (string) $wp_version, true, __( 'Required by the plugin metadata.', 'overcustomise' ) ),
			self::check( 'woocommerce', __( 'WooCommerce', 'overcustomise' ), class_exists( 'WooCommerce' ), defined( 'WC_VERSION' ) ? WC_VERSION : '', true, __( 'Required for products, carts, orders, and administration.', 'overcustomise' ) ),
			self::check( 'tcpdf', __( 'TCPDF and core fonts', 'overcustomise' ), class_exists( '\TCPDF' ) && is_readable( $tcpdf_fonts ), class_exists( '\TCPDF' ) ? __( 'Loaded', 'overcustomise' ) : '', true, __( 'Required to create production PDF/X files.', 'overcustomise' ) ),
			self::extension_check( 'bcmath', 'BCMath', true, __( 'Required by PDF generation dependencies.', 'overcustomise' ) ),
			self::extension_check( 'curl', 'cURL', true, __( 'Required by bundled PDF libraries and remote service requests.', 'overcustomise' ) ),
			self::extension_check( 'gd', 'GD', true, __( 'Required for raster artwork processing and previews.', 'overcustomise' ) ),
			self::extension_check( 'mbstring', 'Multibyte String', true, __( 'Required for Unicode text and font processing.', 'overcustomise' ) ),
			self::extension_check( 'openssl', 'OpenSSL', true, __( 'Required to encrypt stored API credentials.', 'overcustomise' ) ),
			self::extension_check( 'zlib', 'zlib', true, __( 'Required for compressed fonts and PDF data.', 'overcustomise' ) ),
			self::extension_check( 'dom', 'DOM', true, __( 'Required for safe SVG parsing and vector rendering.', 'overcustomise' ) ),
			self::extension_check( 'xmlreader', 'XMLReader', true, __( 'Required for safe SVG validation.', 'overcustomise' ) ),
			self::extension_check( 'fileinfo', 'Fileinfo', true, __( 'Required for reliable customer artwork type detection.', 'overcustomise' ) ),
			self::check( 'proc_open', 'proc_open', function_exists( 'proc_open' ), function_exists( 'proc_open' ) ? __( 'Enabled', 'overcustomise' ) : '', false, __( 'Recommended to run optional Ghostscript processing without invoking a shell.', 'overcustomise' ) ),
			self::check( 'ghostscript', 'Ghostscript', '' !== $ghostscript['binary'], $ghostscript['version'], false, __( 'Recommended to convert production PDF text into vector outlines; embedded-font PDFs are generated when unavailable.', 'overcustomise' ) ),
			self::extension_check( 'imagick', 'Imagick', false, __( 'Recommended for higher-quality artwork conversion and image effects; GD fallbacks remain available.', 'overcustomise' ) ),
			self::check(
				'imagick_heic',
				__( 'ImageMagick HEIC/HEIF support', 'overcustomise' ),
				class_exists( 'OC_Upload_Handler' ) && OC_Upload_Handler::heic_conversion_is_available(),
				class_exists( 'OC_Upload_Handler' ) && OC_Upload_Handler::heic_conversion_is_available() ? __( 'Available', 'overcustomise' ) : '',
				false,
				__( 'Required to convert Apple HEIC and HEIF photo uploads into filter-compatible JPEG images.', 'overcustomise' )
			),
		];
	}

	/** Find an executable Ghostscript binary and report its version. */
	public static function ghostscript(): array {
		static $status = null;
		if ( is_array( $status ) ) {
			return $status;
		}

		$status = [ 'binary' => '', 'version' => '' ];
		if ( ! function_exists( 'proc_open' ) || ! class_exists( 'OC_Command_Runner' ) ) {
			return $status;
		}

		$candidates = str_starts_with( strtoupper( PHP_OS_FAMILY ), 'WINDOWS' )
			? [ 'gswin64c', 'gswin32c', 'gs' ]
			: [ 'gs', '/usr/bin/gs', '/usr/local/bin/gs' ];
		$filtered   = apply_filters( 'oc_ghostscript_binary_candidates', $candidates );
		$candidates = is_array( $filtered ) ? $filtered : $candidates;

		foreach ( $candidates as $candidate ) {
			if ( ! is_string( $candidate ) || '' === trim( $candidate ) ) {
				continue;
			}
			try {
				$probe = OC_Command_Runner::run( [ $candidate, '--version' ] );
				if ( 0 === (int) $probe['code'] ) {
					$version = trim( (string) ( $probe['output'][0] ?? '' ) );
					$status = [
						'binary'  => $candidate,
						'version' => '' !== $version ? $version . ' (' . $candidate . ')' : $candidate,
					];
					return $status;
				}
			} catch ( \InvalidArgumentException $e ) {
				// Continue through the allowlisted candidates.
			}
		}

		return $status;
	}

	/** Build one PHP extension status row. */
	private static function extension_check( string $extension, string $label, bool $required, string $description ): array {
		$loaded  = extension_loaded( $extension );
		$version = $loaded ? phpversion( $extension ) : false;

		return self::check(
			'ext_' . $extension,
			$label,
			$loaded,
			is_string( $version ) ? $version : ( $loaded ? __( 'Loaded', 'overcustomise' ) : '' ),
			$required,
			$description
		);
	}

	/** Normalize a dependency check for the admin renderer. */
	private static function check( string $key, string $label, bool $available, string $version, bool $required, string $description ): array {
		return compact( 'key', 'label', 'available', 'version', 'required', 'description' );
	}
}
