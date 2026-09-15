<?php
/**
 * Shared fonts helpers for print file generators.
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

trait OC_Print_Base_Fonts {

	// -------------------------------------------------------------------------
	// Font helpers
	// -------------------------------------------------------------------------

	/** Fetch a retained font DB row by its explicit ID, including inactive rows. */
	protected static function get_font( int $font_id ): ?object {
		if ( ! $font_id ) {
			return null;
		}
		static $fonts_by_site = [];
		$blog_id = function_exists( 'get_current_blog_id' ) ? get_current_blog_id() : 0;
		if ( array_key_exists( $font_id, $fonts_by_site[ $blog_id ] ?? [] ) ) {
			return $fonts_by_site[ $blog_id ][ $font_id ];
		}
		global $wpdb;
		$font = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}oc_fonts WHERE id = %d LIMIT 1",
			$font_id
		) ) ?: null;
		$fonts_by_site[ $blog_id ][ $font_id ] = $font;
		return $font;
	}

	/** Return the absolute path to a font file, or null if not accessible. */
	protected static function get_raw_font_path( object $font ): ?string {
		// Validate font path doesn't contain directory traversal.
		$file_path = ltrim( (string) ( $font->file_path ?? '' ), '/' );
		if ( '' === $file_path || str_contains( $file_path, '..' ) ) {
			return null;
		}
		
		$path = wp_upload_dir()['basedir'] . '/' . $file_path;
		$real = realpath( $path );
		$base = realpath( wp_upload_dir()['basedir'] );
		
		// Ensure the font file is within the uploads directory.
		$base_prefix = $base ? rtrim( $base, '/\\' ) . DIRECTORY_SEPARATOR : '';
		if ( ! $real || '' === $base_prefix || ! str_starts_with( $real, $base_prefix ) || ! is_file( $real ) ) {
			return null;
		}

		return $real;
	}

	/** Return the absolute path to a print-compatible TrueType-outline font file, or null. */
	protected static function get_font_path( object $font ): ?string {
		$real = self::get_raw_font_path( $font );
		if ( ! $real ) {
			return null;
		}

		if ( 'otf' === strtolower( pathinfo( $real, PATHINFO_EXTENSION ) ) && self::is_cff_opentype( $real ) ) {
			$print_font = self::get_print_companion_font_path( $real );
			if ( ! $print_font ) {
				$print_font = self::get_print_variant_font_path( $font );
			}
			if ( $print_font ) {
				return $print_font;
			}

			OC_Logger::warning( 'Print font fallback: ' . basename( $real ) . ' is an OpenType/CFF font. TCPDF needs a TrueType-outline TTF/OTF file.' );
			return null;
		}

		if ( 'woff' === strtolower( pathinfo( $real, PATHINFO_EXTENSION ) ) && class_exists( 'OC_WOFF_Converter' ) ) {
			if ( self::is_cff_woff( $real ) ) {
				$print_font = self::get_print_companion_font_path( $real );
				if ( ! $print_font ) {
					$print_font = self::get_print_variant_font_path( $font );
				}
				if ( $print_font ) {
					return $print_font;
				}

				OC_Logger::warning( 'Print font fallback: ' . basename( $real ) . ' is a WOFF-wrapped OpenType/CFF font. TCPDF needs a TrueType-outline TTF/OTF file.' );
				return null;
			}

			$upload_dir = wp_upload_dir();
			$cache_dir  = trailingslashit( $upload_dir['basedir'] ) . 'overcustomise/tcpdf-fonts';
			wp_mkdir_p( $cache_dir );
			$dest = trailingslashit( $cache_dir ) . sanitize_file_name( pathinfo( $real, PATHINFO_FILENAME ) ) . '-' . md5_file( $real ) . '.ttf';
			if ( file_exists( $dest ) || OC_WOFF_Converter::extract_sfnt( $real, $dest ) ) {
				return $dest;
			}
		}

		return $real;
	}

	/** Return a browser-converted print TTF companion for CFF/WOFF fonts, if one exists. */
	protected static function get_print_companion_font_path( string $source_path ): ?string {
		$dir  = dirname( $source_path );
		$base = pathinfo( $source_path, PATHINFO_FILENAME );
		if ( '' === $base || ! is_dir( $dir ) ) {
			return null;
		}

		$candidates = [ $dir . '/' . $base . '-print.ttf' ];
		$matches    = glob( $dir . '/' . $base . '-print*.ttf' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_glob
		if ( is_array( $matches ) ) {
			$candidates = array_merge( $candidates, $matches );
		}

		foreach ( array_unique( $candidates ) as $candidate ) {
			$real = realpath( $candidate );
			if ( $real && is_readable( $real ) && self::is_truetype_outline_font( $real ) ) {
				return $real;
			}
		}

		return null;
	}

	/** Find another retained family row that already points to a print-safe TTF. */
	protected static function get_print_variant_font_path( object $font ): ?string {
		$name   = trim( (string) ( $font->name ?? '' ) );
		$weight = trim( (string) ( $font->weight ?? 'normal' ) );
		$style  = trim( (string) ( $font->style ?? 'normal' ) );
		$id     = (int) ( $font->id ?? 0 );
		if ( '' === $name || $id <= 0 ) {
			return null;
		}

		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}oc_fonts
			 WHERE name = %s AND weight = %s AND style = %s AND id <> %d
			 ORDER BY active DESC, id DESC",
			$name,
			$weight,
			$style,
			$id
		) );
		if ( ! is_array( $rows ) ) {
			return null;
		}

		foreach ( $rows as $row ) {
			$path = self::get_raw_font_path( $row );
			if ( is_string( $path ) && '' !== $path && self::is_truetype_outline_font( $path ) ) {
				return $path;
			}
		}

		return null;
	}

	protected static function is_truetype_outline_font( string $path ): bool {
		$handle = fopen( $path, 'rb' );
		if ( ! $handle ) {
			return false;
		}
		$signature = fread( $handle, 4 );
		fclose( $handle );

		return in_array( $signature, [ "\x00\x01\x00\x00", 'true' ], true );
	}

	protected static function is_cff_opentype( string $path ): bool {
		$handle = fopen( $path, 'rb' );
		if ( ! $handle ) {
			return false;
		}
		$signature = fread( $handle, 4 );
		fclose( $handle );

		return 'OTTO' === $signature;
	}

	protected static function is_cff_woff( string $path ): bool {
		$handle = fopen( $path, 'rb' );
		if ( ! $handle ) {
			return false;
		}
		$signature = fread( $handle, 4 );
		$flavor    = fread( $handle, 4 );
		fclose( $handle );

		return 'wOFF' === $signature && 'OTTO' === $flavor;
	}

	/**
	 * Register a TTF/OTF font with TCPDF and return the internal font name.
	 * A configured custom font must never silently fall back to a core font.
	 *
	 * @param  string $font_path  Absolute path to the font file.
	 * @return string             TCPDF font name.
	 */
	protected static function register_tcpdf_font( string $font_path ): string {
		if ( ! is_readable( $font_path ) ) {
			throw new \RuntimeException( 'The selected print font source is unavailable; production retained for review.' );
		}

		try {
			$upload_dir = wp_upload_dir();
			$font_dir   = trailingslashit( $upload_dir['basedir'] ) . 'overcustomise/tcpdf-fonts/';
			wp_mkdir_p( $font_dir );

			if ( class_exists( '\TCPDF_FONTS' ) ) {
				return self::register_legacy_tcpdf_font( $font_path, $font_dir );
			}

			if ( class_exists( '\Com\Tecnick\Pdf\Font\Import' ) ) {
				return self::register_tc_lib_pdf_font( $font_path, $font_dir );
			}

			throw new \RuntimeException( 'No compatible TCPDF font importer is available.' );
		} catch ( \Throwable $e ) {
			throw new \RuntimeException( 'The selected print font could not be registered. Retain its source and rebuild the verified cache in a writable directory before retrying: ' . $e->getMessage(), 0, $e );
		}
	}

	/** Publish legacy definitions and their binaries as one immutable directory. */
	protected static function register_legacy_tcpdf_font( string $font_path, string $font_dir ): string {
		$identity = self::tc_lib_pdf_font_name( $font_path );
		if ( '' === $identity ) {
			throw new \RuntimeException( 'Unreadable print font source.' );
		}
		$name = 'oclegacy' . substr( $identity, 2 );
		$font_dir = trailingslashit( $font_dir );
		$published = $font_dir . $name;
		$source_hash = hash_file( 'sha256', $font_path );
		// Published bundles are immutable: verified readers need no writable lock.
		if ( ! is_link( $published ) && self::legacy_tcpdf_font_cache_complete( $published . '/', $name, $source_hash ) ) {
			return $name;
		}
		$lock = fopen( $font_dir . $name . '.lock', 'c' );
		if ( false === $lock ) {
			throw new \RuntimeException( 'Could not lock print font cache.' );
		}
		$stage = null;
		try {
			if ( ! flock( $lock, LOCK_EX ) ) {
				throw new \RuntimeException( 'Could not lock print font cache.' );
			}
			if ( file_exists( $published ) || is_link( $published ) ) {
				if ( ! is_link( $published ) && self::legacy_tcpdf_font_cache_complete( $published . '/', $name, $source_hash ) ) {
					return $name;
				}
				// Readers outlive this lock. Never repair or remove a published entry.
				throw new \RuntimeException( 'Published print font cache is invalid; refusing to replace active reader artifacts.' );
			}
			$stage = $font_dir . '.' . $name . '-' . bin2hex( random_bytes( 12 ) );
			if ( ! mkdir( $stage, 0700 ) ) {
				throw new \RuntimeException( 'Could not stage print font cache.' );
			}
			$source = $stage . '/' . $name . '.ttf';
			if ( ! copy( $font_path, $source ) || hash_file( 'sha256', $source ) !== $source_hash || self::tc_lib_pdf_font_name( $font_path ) !== $identity ) {
				throw new \RuntimeException( 'Print font source changed during registration.' );
			}
			$importer = [ '\TCPDF_FONTS', 'addTTFfont' ];
			if ( ! is_callable( $importer ) ) {
				throw new \RuntimeException( 'Legacy TCPDF font importer is unavailable.' );
			}
			$imported = $importer( $source, 'TrueTypeUnicode', '', 96, $stage . '/' );
			if ( $name !== $imported ) {
				throw new \RuntimeException( 'Unexpected print font cache identity.' );
			}
			$manifest = [ 'source' => $source_hash ];
			foreach ( [ '.php', '.z', '.ctg.z' ] as $suffix ) {
				$artifact = $stage . '/' . $name . $suffix;
				if ( ! is_file( $artifact ) || is_link( $artifact ) ) {
					throw new \RuntimeException( 'Incomplete imported print font.' );
				}
				$manifest[ $suffix ] = hash_file( 'sha256', $artifact );
			}
			if ( false === file_put_contents( $stage . '/' . $name . '.manifest.json', json_encode( $manifest ) )
				|| ! self::legacy_tcpdf_font_cache_complete( $stage . '/', $name, $source_hash ) ) {
				throw new \RuntimeException( 'Invalid imported print font artifacts.' );
			}
			if ( ! unlink( $source ) || ! rename( $stage, $published ) ) {
				throw new \RuntimeException( 'Could not publish print font cache.' );
			}
			$stage = null;
			return $name;
		} finally {
			if ( null !== $stage && is_dir( $stage ) ) {
				foreach ( [ '.ttf', '.php', '.z', '.ctg.z', '.manifest.json' ] as $suffix ) {
					$artifact = $stage . '/' . $name . $suffix;
					if ( is_file( $artifact ) || is_link( $artifact ) ) {
						unlink( $artifact );
					}
				}
				rmdir( $stage );
			}
			flock( $lock, LOCK_UN );
			fclose( $lock );
		}
	}

	/** Inspect legacy PHP as literal assignments, never by including or evaluating it. */
	private static function legacy_tcpdf_font_cache_complete( string $dir, string $name, string $source_hash ): bool {
		$manifest_path = $dir . $name . '.manifest.json';
		if ( ! is_file( $manifest_path ) || is_link( $manifest_path ) || filesize( $manifest_path ) > 4096 ) {
			return false;
		}
		$manifest = json_decode( (string) file_get_contents( $manifest_path ), true );
		if ( ! is_array( $manifest ) || ( $manifest['source'] ?? null ) !== $source_hash ) {
			return false;
		}
		foreach ( [ '.php', '.z', '.ctg.z' ] as $suffix ) {
			$path = $dir . $name . $suffix;
			clearstatcache( true, $path );
			if ( ! is_string( $manifest[ $suffix ] ?? null ) || ! is_file( $path ) || is_link( $path ) || filesize( $path ) < 1 || filesize( $path ) > 16842752
				|| ! hash_equals( $manifest[ $suffix ], hash_file( 'sha256', $path ) ) ) {
				return false;
			}
		}
		if ( ! self::tcpdf_font_binary_complete( $dir, $name ) || hash( 'sha256', gzuncompress( (string) file_get_contents( $dir . $name . '.z' ), 16777216 ) ) !== $source_hash ) {
			return false;
		}
		try {
			$tokens = array_values( array_filter( token_get_all( (string) file_get_contents( $dir . $name . '.php' ), TOKEN_PARSE ), static fn ( $token ): bool => ! is_array( $token ) || ! in_array( $token[0], [ T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ], true ) ) );
		} catch ( \ParseError $e ) {
			return false;
		}
		$assigned = [];
		$references = [ '$type' => 'TrueTypeUnicode', '$file' => $name . '.z', '$ctg' => $name . '.ctg.z' ];
		foreach ( $tokens as $index => $token ) {
			if ( ! is_array( $token ) ) {
				if ( ! in_array( $token, [ '=', ';', ',', '(', ')', '[', ']', '-', '+' ], true ) || ( '(' === $token && ( $tokens[ $index - 1 ][0] ?? null ) !== T_ARRAY ) ) {
					return false;
				}
				continue;
			}
			if ( T_VARIABLE === $token[0] ) {
				$variable = $token[1];
				if ( ! in_array( $variable, [ '$type', '$name', '$desc', '$up', '$ut', '$dw', '$cw', '$enc', '$diff', '$file', '$ctg', '$originalsize', '$fontkey', '$subsetted', '$cbbox' ], true )
					|| isset( $assigned[ $variable ] ) || '=' !== ( $tokens[ $index + 1 ] ?? null )
					|| ( ';' !== ( $tokens[ $index - 1 ] ?? null ) && T_OPEN_TAG !== ( $tokens[ $index - 1 ][0] ?? null ) ) ) {
					return false;
				}
				$assigned[ $variable ] = true;
				if ( isset( $references[ $variable ] ) && ( ';' !== ( $tokens[ $index + 3 ] ?? null ) || ! in_array( $tokens[ $index + 2 ][1] ?? null, [ "'" . $references[ $variable ] . "'", '"' . $references[ $variable ] . '"' ], true ) ) ) {
					return false;
				}
			} elseif ( ! in_array( $token[0], [ T_OPEN_TAG, T_CLOSE_TAG, T_CONSTANT_ENCAPSED_STRING, T_LNUMBER, T_DNUMBER, T_ARRAY, T_DOUBLE_ARROW ], true ) && ! ( T_STRING === $token[0] && in_array( strtolower( $token[1] ), [ 'true', 'false', 'null' ], true ) ) ) {
				return false;
			}
		}
		return isset( $assigned['$type'], $assigned['$file'], $assigned['$ctg'], $assigned['$name'], $assigned['$desc'], $assigned['$cw'] );
	}

	/** Retain verified flat caches; publish new v7 fonts as immutable bundles. */
	protected static function register_tc_lib_pdf_font( string $font_path, string $font_dir ): string {
		$font_dir  = trailingslashit( $font_dir );
		$identity = self::tc_lib_pdf_font_name( $font_path );
		if ( '' === $identity ) {
			throw new \RuntimeException( 'Unreadable print font source.' );
		}
		$source_hash = hash_file( 'sha256', $font_path );
		// Never write or repair historical flat artifacts, including incomplete sets.
		// Readers may have loaded the definition but not opened its binaries yet.
		foreach ( [ '.json', '.z', '.ctg.z', '.manifest.json' ] as $suffix ) {
			if ( file_exists( $font_dir . $identity . $suffix ) || is_link( $font_dir . $identity . $suffix ) ) {
				if ( self::tcpdf_font_cache_complete( $font_dir, $identity, $source_hash ) ) {
					return $identity;
				}
				throw new \RuntimeException( 'Retained print font cache is invalid; refusing to replace active reader artifacts.' );
			}
		}
		$font_name = 'ocmodern' . substr( $identity, 2 );
		$published = $font_dir . $font_name;
		if ( file_exists( $published ) || is_link( $published ) ) {
			if ( ! is_link( $published ) && self::tcpdf_font_cache_complete( $published . '/', $font_name, $source_hash ) ) {
				return $font_name;
			}
			throw new \RuntimeException( 'Published print font cache is invalid; refusing to replace active reader artifacts.' );
		}
		$lock = fopen( $font_dir . $font_name . '.lock', 'c' );
		if ( false === $lock ) {
			throw new \RuntimeException( 'Could not lock print font cache.' );
		}
		$stage = null;
		try {
			if ( ! flock( $lock, LOCK_EX ) ) {
				throw new \RuntimeException( 'Could not lock print font cache.' );
			}
			$published_link = self::path_link_state_after_lock( $published );
			if ( null !== $published_link ) {
				if ( ! $published_link && self::tcpdf_font_cache_complete( $published . '/', $font_name, $source_hash ) ) {
					return $font_name;
				}
				throw new \RuntimeException( 'Published print font cache is invalid; refusing to replace active reader artifacts.' );
			}
			$stage = $font_dir . '.' . $font_name . '-' . bin2hex( random_bytes( 12 ) );
			if ( ! mkdir( $stage, 0700 ) ) {
				throw new \RuntimeException( 'Could not stage print font cache.' );
			}
			$source = $stage . '/' . $font_name . '.ttf';
			if ( ! copy( $font_path, $source ) || hash_file( 'sha256', $source ) !== $source_hash || self::tc_lib_pdf_font_name( $font_path ) !== $identity ) {
				throw new \RuntimeException( 'Print font source changed during registration.' );
			}
			$import = new \Com\Tecnick\Pdf\Font\Import(
				$source,
				$stage . '/',
				'TrueTypeUnicode',
				'',
				32,
				3,
				1,
				false
			);

			if ( $font_name !== $import->getFontName() ) {
				throw new \RuntimeException( 'Unexpected print font cache identity.' );
			}
			$manifest = [ 'source' => $source_hash ];
			foreach ( [ '.json', '.z', '.ctg.z' ] as $suffix ) {
				$artifact = $stage . '/' . $font_name . $suffix;
				if ( ! is_file( $artifact ) || is_link( $artifact ) ) {
					throw new \RuntimeException( 'Incomplete imported print font.' );
				}
				$manifest[ $suffix ] = hash_file( 'sha256', $artifact );
			}
			if ( false === file_put_contents( $stage . '/' . $font_name . '.manifest.json', json_encode( $manifest ) ) || ! self::tcpdf_font_cache_complete( $stage . '/', $font_name, $source_hash ) ) {
				throw new \RuntimeException( 'Invalid imported print font artifacts.' );
			}
			if ( ! unlink( $source ) || ! rename( $stage, $published ) ) {
				throw new \RuntimeException( 'Could not publish print font cache.' );
			}
			$stage = null;
			return $font_name;
		} finally {
			if ( null !== $stage && is_dir( $stage ) ) {
				foreach ( [ '.ttf', '.json', '.z', '.ctg.z', '.manifest.json' ] as $suffix ) {
					$artifact = $stage . '/' . $font_name . $suffix;
					if ( is_file( $artifact ) || is_link( $artifact ) ) {
						unlink( $artifact );
					}
				}
				rmdir( $stage );
			}
			flock( $lock, LOCK_UN );
			fclose( $lock );
		}
	}

	/** Recheck a publication path after lock acquisition, allowing for another process. */
	private static function path_link_state_after_lock( string $path ): ?bool {
		clearstatcache( true, $path );
		if ( is_link( $path ) ) {
			return true;
		}
		return file_exists( $path ) ? false : null;
	}

	/** Use a basename that survives both importers' lossy name normalisation. */
	protected static function tc_lib_pdf_font_name( string $font_path ): string {
		$real = realpath( $font_path );
		$hash = $real && is_readable( $real ) ? hash_file( 'sha256', $real ) : false;
		return false !== $hash ? 'oc' . hash( 'sha256', $real . '|' . $hash ) : '';
	}

	private static function tcpdf_font_cache_complete( string $dir, string $name, ?string $source_hash = null ): bool {
		$manifest_path = $dir . $name . '.manifest.json';
		if ( ! is_file( $manifest_path ) || is_link( $manifest_path ) || filesize( $manifest_path ) > 4096 ) {
			return false;
		}
		$manifest = json_decode( (string) file_get_contents( $manifest_path ), true );
		if ( ! is_array( $manifest ) ) {
			return false;
		}
		foreach ( [ '.json', '.z', '.ctg.z' ] as $suffix ) {
			$path = $dir . $name . $suffix;
			clearstatcache( true, $path );
			if ( ! is_string( $manifest[ $suffix ] ?? null ) || ! is_file( $path ) || is_link( $path ) || ! is_readable( $path ) || filesize( $path ) < 1 || filesize( $path ) > 16842752 || ! hash_equals( $manifest[ $suffix ], hash_file( 'sha256', $path ) ) ) {
				return false;
			}
		}
		$data = json_decode( (string) file_get_contents( $dir . $name . '.json' ), true );
		return is_array( $data ) && 'TrueTypeUnicode' === ( $data['type'] ?? '' )
			&& $name . '.z' === ( $data['file'] ?? '' ) && $name . '.ctg.z' === ( $data['ctg'] ?? '' )
			&& ! empty( $data['cw'] ) && self::tcpdf_font_binary_complete( $dir, $name )
			&& ( null === $source_hash || hash( 'sha256', gzuncompress( (string) file_get_contents( $dir . $name . '.z' ), 16777216 ) ) === $source_hash );
	}

	private static function tcpdf_font_binary_complete( string $dir, string $name ): bool {
		foreach ( [ '.z' => 16777216, '.ctg.z' => 131072 ] as $suffix => $limit ) {
			$path = $dir . $name . $suffix;
			if ( ! is_readable( $path ) || filesize( $path ) < 1 || filesize( $path ) > $limit + 65536 ) {
				return false;
			}
			$bytes = @gzuncompress( (string) file_get_contents( $path ), $limit );
			if ( ! is_string( $bytes ) || '' === $bytes || ( '.ctg.z' === $suffix && strlen( $bytes ) !== 131072 ) ) {
				return false;
			}
		}
		return true;
	}

	// -------------------------------------------------------------------------
	// Shared text/font helpers (used by all generators)
	// -------------------------------------------------------------------------

	/**
	 * Resolve a TCPDF font name from a font DB ID.
	 * A zero ID is the deliberate legacy default; explicit IDs must render exactly.
	 */
	protected static function resolve_font( int $font_id, ?\TCPDF $pdf = null ): string {
		if ( $font_id <= 0 ) {
			return 'helvetica';
		}

		$font = self::get_font( $font_id );
		if ( ! $font ) {
			throw new \RuntimeException( sprintf( __( 'The selected print font #%d is no longer retained.', 'overcustomise' ), $font_id ) );
		}
		$raw_path = self::get_raw_font_path( $font );
		if ( is_string( $raw_path ) && 'woff2' === strtolower( pathinfo( $raw_path, PATHINFO_EXTENSION ) ) ) {
			/* translators: %d: Font database ID. */
			throw new \RuntimeException( sprintf( __( 'The selected print font #%d is a WOFF2 web font. Convert it for print in OverCustomise > Fonts, then regenerate this file.', 'overcustomise' ), $font_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are not rendered as HTML.
		}
		$path = self::get_font_path( $font );
		if ( ! $path ) {
			throw new \RuntimeException( sprintf( __( 'The selected print font #%d has no renderable production file.', 'overcustomise' ), $font_id ) );
		}
		$name = self::register_tcpdf_font( $path );
		if ( '' === $name ) {
			throw new \RuntimeException( sprintf( __( 'The selected print font #%d could not be registered for production.', 'overcustomise' ), $font_id ) );
		}
		if ( $pdf ) {
			$font_file = self::tcpdf_font_definition_path( $path, $name );
			if ( '' === $font_file ) {
				throw new \RuntimeException( sprintf( __( 'The selected print font #%d has no usable PDF definition.', 'overcustomise' ), $font_id ) );
			}
			$pdf->AddFont( $name, '', $font_file );
		}

		return $name;
	}

	protected static function tcpdf_font_definition_path( string $font_path, string $font_name ): string {
		$upload_dir = wp_upload_dir();
		$font_dir   = trailingslashit( $upload_dir['basedir'] ) . 'overcustomise/tcpdf-fonts/';
		if ( preg_match( '/^oclegacy[a-f0-9]{64}$/D', $font_name ) ) {
			$font_file = $font_dir . $font_name . '/' . $font_name . '.php';
			return is_file( $font_file ) ? $font_file : '';
		}
		if ( preg_match( '/^ocmodern[a-f0-9]{64}$/D', $font_name ) ) {
			$font_file = $font_dir . $font_name . '/' . $font_name . '.json';
			return is_file( $font_file ) ? $font_file : '';
		}
		$json_file  = $font_dir . $font_name . '.json';
		if ( file_exists( $json_file ) ) {
			return $json_file;
		}

		$font_file  = $font_dir . $font_name . '.php';

		return file_exists( $font_file ) ? $font_file : '';
	}
}
