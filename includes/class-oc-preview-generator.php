<?php
defined( 'ABSPATH' ) || exit;

class OC_Preview_Generator {
	private const MAX_SOURCE_BYTES = 104857600;
	private const MAX_RENDER_DIMENSION = 1200;
	private const MAX_RENDER_PIXELS = 1440000;

	public static function from_pdf( string $pdf_path, string $thumb_path ): bool {
		$pdf_path = realpath( $pdf_path ) ?: '';
		$size     = $pdf_path ? filesize( $pdf_path ) : false;
		if ( '' === $pdf_path || ! is_file( $pdf_path ) || ! is_readable( $pdf_path ) || false === $size || $size > self::MAX_SOURCE_BYTES ) {
			return false;
		}

		$dir = dirname( $thumb_path );
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return false;
		}
		if ( is_file( $thumb_path ) ) {
			@unlink( $thumb_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		if ( self::try_imagick( $pdf_path, $thumb_path ) && self::valid_thumbnail( $thumb_path ) ) {
			return true;
		}
		@unlink( $thumb_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		if ( self::try_ghostscript( $pdf_path, $thumb_path ) && self::valid_thumbnail( $thumb_path ) ) {
			return true;
		}
		@unlink( $thumb_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		return false;
	}

	private static function try_imagick( string $pdf_path, string $thumb_path ): bool {
		if ( ! class_exists( 'Imagick' ) ) {
			return false;
		}

		$imagick = null;
		try {
			$imagick = new \Imagick();
			$imagick->setResourceLimit( \Imagick::RESOURCETYPE_MEMORY, 256 * 1024 * 1024 );
			$imagick->setResourceLimit( \Imagick::RESOURCETYPE_MAP, 256 * 1024 * 1024 );
			$imagick->setResourceLimit( \Imagick::RESOURCETYPE_DISK, 512 * 1024 * 1024 );
			$imagick->setResolution( 150, 150 );
			$imagick->readImage( $pdf_path . '[0]' );
			$imagick->setImageFormat( 'png' );
			$imagick->thumbnailImage( 300, 300, true, true );
			$imagick->stripImage();
			$result = $imagick->writeImage( $thumb_path );

			return (bool) $result && file_exists( $thumb_path );
		} catch ( \Throwable $e ) {
			OC_Logger::warning( 'Imagick thumbnail generation failed: ' . $e->getMessage() );
			return false;
		} finally {
			if ( $imagick instanceof \Imagick ) {
				$imagick->clear();
				$imagick->destroy();
			}
		}
	}

	private static function try_ghostscript( string $pdf_path, string $thumb_path ): bool {
		$gs_path = self::find_ghostscript();

		if ( ! $gs_path ) {
			return false;
		}

		$tmpdir = trailingslashit( sys_get_temp_dir() ) . 'oc-thumb-' . wp_generate_uuid4();
		if ( ! wp_mkdir_p( $tmpdir ) ) {
			$err = error_get_last();
			OC_Logger::warning( 'Preview temp dir creation failed: ' . ( $err['message'] ?? 'unknown error' ) );
			return false;
		}

		$generated = $tmpdir . '/image.png';
		try {
			$result = OC_Command_Runner::run( [
				$gs_path,
				'-dSAFER',
				'-dNOPAUSE',
				'-dBATCH',
				'-dQUIET',
				'-dFirstPage=1',
				'-dLastPage=1',
				'-dMaxBitmap=' . self::MAX_RENDER_PIXELS,
				'-sDEVICE=png16m',
				'-r150',
				'-g' . self::MAX_RENDER_DIMENSION . 'x' . self::MAX_RENDER_DIMENSION,
				'-dPDFFitPage',
				'-dUseCropBox',
				'-sOutputFile=' . $generated,
				$pdf_path,
			] );
		} catch ( \InvalidArgumentException $e ) {
			OC_Logger::warning( 'Ghostscript thumbnail command failed: ' . $e->getMessage() );
			self::rrmdir( $tmpdir );
			return false;
		}

		if ( 0 !== (int) $result['code'] || ! self::valid_rendered_image( $generated ) ) {
			OC_Logger::warning( sprintf( 'Ghostscript conversion failed (exit %d).', (int) $result['code'] ) );
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

		$width       = imagesx( $img );
		$height      = imagesy( $img );
		$scale       = min( 1, 300 / max( 1, $width ), 300 / max( 1, $height ) );
		$dest_width  = max( 1, (int) round( $width * $scale ) );
		$dest_height = max( 1, (int) round( $height * $scale ) );

		if ( $dest_width !== $width || $dest_height !== $height ) {
			$resized = imagecreatetruecolor( $dest_width, $dest_height );
			imagecopyresampled( $resized, $img, 0, 0, 0, 0, $dest_width, $dest_height, $width, $height );
			imagedestroy( $img );
			$img = $resized;
		}

		$result = imagepng( $img, $thumb_path );
		imagedestroy( $img );
		self::rrmdir( $tmpdir );

		return (bool) $result && file_exists( $thumb_path );
	}

	private static function find_ghostscript(): ?string {
		static $checked = false;
		static $resolved = null;
		if ( $checked ) {
			return $resolved;
		}
		$checked = true;

		$paths = str_starts_with( strtoupper( PHP_OS_FAMILY ), 'WINDOWS' )
			? [ 'gswin64c', 'gswin32c', 'gs' ]
			: [ 'gs', '/usr/bin/gs', '/usr/local/bin/gs' ];

		foreach ( $paths as $candidate ) {
			try {
				$result = OC_Command_Runner::run( [ $candidate, '--version' ] );
				if ( 0 === (int) $result['code'] ) {
					$resolved = $candidate;
					return $resolved;
				}
			} catch ( \InvalidArgumentException $e ) {
				continue;
			}
		}

		return null;
	}

	/** Validate Ghostscript output before passing it to GD. */
	private static function valid_rendered_image( string $path ): bool {
		$info = is_file( $path ) ? @getimagesize( $path ) : false;
		return is_array( $info )
			&& 'image/png' === (string) ( $info['mime'] ?? '' )
			&& (int) $info[0] > 0
			&& (int) $info[1] > 0
			&& (int) $info[0] <= self::MAX_RENDER_DIMENSION
			&& (int) $info[1] <= self::MAX_RENDER_DIMENSION
			&& (int) $info[0] * (int) $info[1] <= self::MAX_RENDER_PIXELS;
	}

	/** Validate the final thumbnail written by either renderer. */
	private static function valid_thumbnail( string $path ): bool {
		$info = is_file( $path ) ? @getimagesize( $path ) : false;
		return is_array( $info )
			&& 'image/png' === (string) ( $info['mime'] ?? '' )
			&& (int) $info[0] > 0
			&& (int) $info[1] > 0
			&& (int) $info[0] <= 300
			&& (int) $info[1] <= 300;
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
