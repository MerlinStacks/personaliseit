<?php
defined( 'ABSPATH' ) || exit;

class OC_Preview_Generator {

	public static function from_pdf( string $pdf_path, string $thumb_path ): bool {
		if ( ! file_exists( $pdf_path ) ) {
			return false;
		}

		$dir = dirname( $thumb_path );
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return false;
		}

		if ( self::try_imagick( $pdf_path, $thumb_path ) ) {
			return true;
		}

		if ( self::try_ghostscript( $pdf_path, $thumb_path ) ) {
			return true;
		}

		return false;
	}

	private static function try_imagick( string $pdf_path, string $thumb_path ): bool {
		if ( ! class_exists( 'Imagick' ) ) {
			return false;
		}

		try {
			$imagick = new \Imagick();
			$imagick->setResolution( 150, 150 );
			$imagick->readImage( $pdf_path . '[0]' );
			$imagick->setImageFormat( 'png' );

			$width  = $imagick->getImageWidth();
			$height = $imagick->getImageHeight();

			if ( $width > 300 ) {
				$scale  = 300 / $width;
				$height = (int) round( $height * $scale );
				$imagick->resizeImage( 300, $height, \Imagick::FILTER_LANCZOS, 1, true );
			}

			$result = $imagick->writeImage( $thumb_path );
			$imagick->clear();
			$imagick->destroy();

			return (bool) $result && file_exists( $thumb_path );
		} catch ( \Throwable $e ) {
			OC_Logger::warning( 'Imagick thumbnail generation failed: ' . $e->getMessage() );
			return false;
		}
	}

	private static function try_ghostscript( string $pdf_path, string $thumb_path ): bool {
		$gs_path = self::find_ghostscript();

		if ( ! $gs_path ) {
			return false;
		}

		$tmpdir = sys_get_temp_dir() . '/oc_thumb_' . wp_generate_password( 8, false );
		if ( ! wp_mkdir_p( $tmpdir ) ) {
			$err = error_get_last();
			OC_Logger::warning( 'Preview temp dir creation failed: ' . ( $err['message'] ?? 'unknown error' ) );
			return false;
		}

		$output_pattern = $tmpdir . '/image_%d.png';
		$cmd = sprintf(
			'%s -dNOPAUSE -dBATCH -dFirstPage=1 -dLastPage=1 -sDEVICE=png16m -r150 -dUseCropBox -sOutputFile=%s %s 2>&1',
			escapeshellarg( $gs_path ),
			escapeshellarg( $output_pattern ),
			escapeshellarg( realpath( $pdf_path ) ?: $pdf_path )
		);

		if ( ! function_exists( 'exec' ) ) {
			OC_Logger::warning( 'Ghostscript conversion skipped: exec() is disabled.' );
			self::rrmdir( $tmpdir );
			return false;
		}
		$output   = [];
		$returned = 0;
		exec( $cmd, $output, $returned ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_exec_exec

		$generated = $tmpdir . '/image_1.png';

		if ( 0 !== $returned || ! file_exists( $generated ) ) {
			OC_Logger::warning( sprintf( 'Ghostscript conversion failed (exit %d).', $returned ) );
			self::rrmdir( $tmpdir );
			return false;
		}

		if ( ! function_exists( 'imagecreatefrompng' ) || ! function_exists( 'imagecreatetruecolor' )
			|| ! function_exists( 'imagecopyresampled' ) || ! function_exists( 'imagepng' ) ) {
			OC_Logger::warning( 'Preview generation skipped: GD functions unavailable.' );
			self::rrmdir( $tmpdir );
			return false;
		}

		$img = @imagecreatefrompng( $generated ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		if ( ! $img ) {
			$err = error_get_last();
			OC_Logger::warning( 'Preview image load failed: ' . ( $err['message'] ?? 'unknown error' ) );
			self::rrmdir( $tmpdir );
			return false;
		}

		$width  = imagesx( $img );
		$height = imagesy( $img );

		if ( $width > 300 ) {
			$scale  = 300 / $width;
			$height = (int) round( $height * $scale );
			$resized = imagecreatetruecolor( 300, $height );
			imagecopyresampled( $resized, $img, 0, 0, 0, 0, 300, $height, $width, $height );
			imagedestroy( $img );
			$img = $resized;
		}

		$result = imagepng( $img, $thumb_path );
		imagedestroy( $img );
		self::rrmdir( $tmpdir );

		return (bool) $result && file_exists( $thumb_path );
	}

	private static function find_ghostscript(): ?string {
		$paths = [ 'gs', 'gswin64c', '/usr/bin/gs', '/usr/local/bin/gs' ];

		foreach ( $paths as $candidate ) {
			if ( ! function_exists( 'exec' ) ) {
				OC_Logger::warning( 'Ghostscript lookup skipped: exec() is disabled.' );
				return null;
			}
			$locate = sprintf( 'which %s 2>/dev/null', escapeshellarg( $candidate ) );
			$result = [];
			exec( $locate, $result ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_exec_exec

			if ( ! empty( $result ) && file_exists( trim( $result[0] ) ) ) {
				return trim( $result[0] );
			}
		}

		return null;
	}

	private static function rrmdir( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		$entries = scandir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! is_array( $entries ) ) {
			OC_Logger::warning( 'Failed to read preview temp directory: ' . $dir );
			return;
		}
		$files = array_diff( $entries, [ '.', '..' ] );
		foreach ( $files as $file ) {
			$path = $dir . '/' . $file;
			is_dir( $path ) ? self::rrmdir( $path ) : @unlink( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
		@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}
}
