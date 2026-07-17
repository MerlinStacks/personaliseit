<?php
/**
 * Converts TTF/OTF font files to WOFF1 format.
 * Pure PHP implementation — no external dependencies required.
 *
 * Reference: https://www.w3.org/TR/WOFF/
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

class OC_WOFF_Converter {
	private const MAX_SOURCE_BYTES = 33554432;
	private const MAX_OUTPUT_BYTES = 67108864;
	private const MAX_TABLE_BYTES = 33554432;
	private const MAX_TABLES = 256;

	/** Valid sfnt version signatures (hex). */
	private const VALID_SIGNATURES = [
		'00010000', // TrueType
		'74727565', // 'true' (Apple TrueType)
		'4f54544f', // 'OTTO' (CFF/OpenType)
	];

	/**
	 * Convert a TTF or OTF file to WOFF1 and write to $dest_path.
	 *
	 * @param string $src_path  Absolute path to the source TTF/OTF file.
	 * @param string $dest_path Absolute path for the output WOFF file.
	 * @return bool True on success, false on failure.
	 */
	public static function convert( string $src_path, string $dest_path ): bool {
		if ( ! is_file( $src_path ) || ! is_readable( $src_path ) ) {
			OC_Logger::warning( 'WOFF conversion failed: source file is not readable.' );
			return false;
		}
		$source_size = filesize( $src_path );
		if ( false === $source_size || $source_size > self::MAX_SOURCE_BYTES ) {
			OC_Logger::warning( 'WOFF conversion failed: source file exceeds the safe size limit.' );
			return false;
		}
		$data = file_get_contents( $src_path );
		if ( false === $data || strlen( $data ) < 12 ) {
			OC_Logger::warning( 'WOFF conversion failed: could not read font data.' );
			return false;
		}

		// Parse sfnt offset table.
		$sfnt_version = substr( $data, 0, 4 );
		$num_tables   = unpack( 'n', substr( $data, 4, 2 ) )[1];

		if ( ! in_array( strtolower( bin2hex( $sfnt_version ) ), self::VALID_SIGNATURES, true ) ) {
			OC_Logger::warning( 'WOFF conversion failed: invalid font signature.' );
			return false;
		}
		if ( $num_tables < 1 || $num_tables > self::MAX_TABLES || 12 + $num_tables * 16 > strlen( $data ) ) {
			OC_Logger::warning( 'WOFF conversion failed: invalid table count.' );
			return false;
		}

		$flavor = unpack( 'N', $sfnt_version )[1];

		// Parse table directory (16 bytes per entry).
		$tables     = [];
		$ranges     = [];
		$seen_tags  = [];
		$dir_offset = 12;
		for ( $i = 0; $i < $num_tables; $i++ ) {
			if ( strlen( $data ) < $dir_offset + 16 ) {
				return false;
			}
			$tag      = substr( $data, $dir_offset, 4 );
			$checksum = unpack( 'N', substr( $data, $dir_offset + 4, 4 ) )[1];
			$offset   = unpack( 'N', substr( $data, $dir_offset + 8, 4 ) )[1];
			$length   = unpack( 'N', substr( $data, $dir_offset + 12, 4 ) )[1];
			if ( ! preg_match( '/^[\x20-\x7E]{4}$/D', $tag ) || isset( $seen_tags[ $tag ] ) ) {
				return false;
			}
			if ( $length > self::MAX_TABLE_BYTES || 0 !== $offset % 4 || $offset < 12 + $num_tables * 16 || $offset > strlen( $data ) || $length > strlen( $data ) - $offset ) {
				return false;
			}
			$seen_tags[ $tag ] = true;
			$ranges[]          = [ $offset, $offset + $length ];

			$tables[]    = compact( 'tag', 'checksum', 'offset', 'length' );
			$dir_offset += 16;
		}
		usort( $ranges, static fn( array $a, array $b ): int => $a[0] <=> $b[0] );
		$previous_end = 12 + $num_tables * 16;
		foreach ( $ranges as $range ) {
			if ( $range[0] < $previous_end ) {
				return false;
			}
			$previous_end = $range[1];
		}

		// Calculate totalSfntSize: sfnt header + table directory + padded table data.
		$total_sfnt_size = 12 + $num_tables * 16;
		foreach ( $tables as $t ) {
			$total_sfnt_size += ( $t['length'] + 3 ) & ~3;
			if ( $total_sfnt_size > self::MAX_OUTPUT_BYTES ) {
				return false;
			}
		}

		// Compress each table with zlib (gzcompress = zlib format = RFC 1950).
		$woff_tables = [];
		foreach ( $tables as $t ) {
			$raw        = substr( $data, $t['offset'], $t['length'] );
			$compressed = function_exists( 'gzcompress' ) ? gzcompress( $raw, 9 ) : false;
			if ( false === $compressed ) {
				// Compression failed — fall back to raw data.
				$table_data  = $raw;
				$comp_length = $t['length'];
			} elseif ( strlen( $compressed ) < strlen( $raw ) ) {
				$table_data  = $compressed;
				$comp_length = strlen( $compressed );
			} else {
				// Not worth compressing — store raw.
				$table_data  = $raw;
				$comp_length = $t['length'];
			}

			$woff_tables[] = [
				'tag'        => $t['tag'],
				'checksum'   => $t['checksum'],
				'origLength' => $t['length'],
				'compLength' => $comp_length,
				'data'       => $table_data,
			];
		}

		// Calculate absolute offsets for each WOFF table.
		// WOFF header = 44 bytes, table directory = numTables * 20 bytes.
		$current_offset = 44 + $num_tables * 20;
		foreach ( $woff_tables as &$t ) {
			$t['woff_offset'] = $current_offset;
			$current_offset  += ( $t['compLength'] + 3 ) & ~3; // Pad to 4-byte boundary.
			if ( $current_offset > self::MAX_OUTPUT_BYTES ) {
				return false;
			}
		}
		unset( $t );
		$total_size = $current_offset;

		// ── Build WOFF binary ──────────────────────────────────────────────────

		// Header (44 bytes).
		$woff  = pack( 'N', 0x774F4646 );    // signature 'wOFF'
		$woff .= pack( 'N', $flavor );        // flavor (original sfVersion)
		$woff .= pack( 'N', $total_size );    // length
		$woff .= pack( 'n', $num_tables );    // numTables
		$woff .= pack( 'n', 0 );              // reserved
		$woff .= pack( 'N', $total_sfnt_size ); // totalSfntSize
		$woff .= pack( 'n', 1 );              // majorVersion
		$woff .= pack( 'n', 0 );              // minorVersion
		$woff .= pack( 'NNN', 0, 0, 0 );     // metaOffset, metaLength, metaOrigLength
		$woff .= pack( 'NN', 0, 0 );          // privOffset, privLength

		// Table directory (20 bytes per entry).
		foreach ( $woff_tables as $t ) {
			$woff .= $t['tag'];                          // tag (4 bytes)
			$woff .= pack( 'N', $t['woff_offset'] );     // offset
			$woff .= pack( 'N', $t['compLength'] );      // compLength
			$woff .= pack( 'N', $t['origLength'] );      // origLength
			$woff .= pack( 'N', $t['checksum'] );        // origCheckSum
		}

		// Table data (each padded to 4-byte boundary).
		foreach ( $woff_tables as $t ) {
			$woff .= $t['data'];
			$pad  = ( 4 - ( $t['compLength'] % 4 ) ) % 4;
			if ( $pad > 0 ) {
				$woff .= str_repeat( "\0", $pad );
			}
		}

		if ( ! file_exists( dirname( $dest_path ) ) || ! is_writable( dirname( $dest_path ) ) ) {
			OC_Logger::warning( 'WOFF conversion failed: destination not writable.' );
			return false;
		}
		if ( strlen( $woff ) !== $total_size || false === file_put_contents( $dest_path, $woff, LOCK_EX ) ) {
			OC_Logger::warning( 'WOFF conversion failed: could not write output file.' );
			return false;
		}
		return true;
	}

	/**
	 * Extract the original TTF/OTF sfnt data from a WOFF1 file.
	 *
	 * @param string $src_path  Absolute path to the WOFF file.
	 * @param string $dest_path Absolute path for the extracted TTF/OTF file.
	 * @return bool True on success, false on failure.
	 */
	public static function extract_sfnt( string $src_path, string $dest_path ): bool {
		if ( ! is_file( $src_path ) || ! is_readable( $src_path ) ) {
			OC_Logger::warning( 'WOFF extraction failed: source file is not readable.' );
			return false;
		}
		$source_size = filesize( $src_path );
		if ( false === $source_size || $source_size > self::MAX_SOURCE_BYTES ) {
			OC_Logger::warning( 'WOFF extraction failed: source file exceeds the safe size limit.' );
			return false;
		}

		$data = file_get_contents( $src_path );
		if ( false === $data || strlen( $data ) < 44 || '774f4646' !== strtolower( bin2hex( substr( $data, 0, 4 ) ) ) ) {
			OC_Logger::warning( 'WOFF extraction failed: invalid WOFF file.' );
			return false;
		}

		$flavor     = substr( $data, 4, 4 );
		$declared_length = unpack( 'N', substr( $data, 8, 4 ) )[1];
		$num_tables = unpack( 'n', substr( $data, 12, 2 ) )[1];
		$declared_sfnt_size = unpack( 'N', substr( $data, 16, 4 ) )[1];
		if ( ! in_array( strtolower( bin2hex( $flavor ) ), self::VALID_SIGNATURES, true )
			|| $num_tables < 1
			|| $num_tables > self::MAX_TABLES
			|| $declared_length !== strlen( $data )
			|| $declared_sfnt_size > self::MAX_OUTPUT_BYTES
			|| 44 + $num_tables * 20 > strlen( $data )
		) {
			OC_Logger::warning( 'WOFF extraction failed: invalid font signature.' );
			return false;
		}

		$tables      = [];
		$ranges      = [];
		$seen_tags   = [];
		$output_size = 12 + $num_tables * 16;
		$offset      = 44;
		for ( $i = 0; $i < $num_tables; $i++ ) {
			if ( strlen( $data ) < $offset + 20 ) {
				return false;
			}
			$tag         = substr( $data, $offset, 4 );
			$table_start = unpack( 'N', substr( $data, $offset + 4, 4 ) )[1];
			$comp_length = unpack( 'N', substr( $data, $offset + 8, 4 ) )[1];
			$orig_length = unpack( 'N', substr( $data, $offset + 12, 4 ) )[1];
			$checksum    = substr( $data, $offset + 16, 4 );
			if ( ! preg_match( '/^[\x20-\x7E]{4}$/D', $tag ) || isset( $seen_tags[ $tag ] ) ) {
				return false;
			}
			if ( $orig_length > self::MAX_TABLE_BYTES
				|| $comp_length > $orig_length
				|| 0 !== $table_start % 4
				|| $table_start < 44 + $num_tables * 20
				|| $table_start > strlen( $data )
				|| $comp_length > strlen( $data ) - $table_start
			) {
				return false;
			}
			$seen_tags[ $tag ] = true;
			$ranges[]          = [ $table_start, $table_start + $comp_length ];
			$output_size      += ( $orig_length + 3 ) & ~3;
			if ( $output_size > self::MAX_OUTPUT_BYTES ) {
				return false;
			}

			$table_data = substr( $data, $table_start, $comp_length );
			if ( $comp_length !== $orig_length ) {
				$inflated = function_exists( 'gzuncompress' ) ? @gzuncompress( $table_data, $orig_length ) : false;
				if ( false === $inflated || strlen( $inflated ) !== $orig_length ) {
					OC_Logger::warning( 'WOFF extraction failed: could not decompress table.' );
					return false;
				}
				$table_data = $inflated;
			}

			$tables[] = [
				'tag'      => $tag,
				'checksum' => $checksum,
				'length'   => $orig_length,
				'data'     => $table_data,
			];
			$offset += 20;
		}
		usort( $ranges, static fn( array $a, array $b ): int => $a[0] <=> $b[0] );
		$previous_end = 44 + $num_tables * 20;
		foreach ( $ranges as $range ) {
			if ( $range[0] < $previous_end ) {
				return false;
			}
			$previous_end = $range[1];
		}
		if ( $declared_sfnt_size !== $output_size ) {
			return false;
		}

		$entry_selector = (int) floor( log( $num_tables, 2 ) );
		$search_range   = (int) pow( 2, $entry_selector ) * 16;
		$range_shift    = $num_tables * 16 - $search_range;
		$out            = $flavor . pack( 'nnnn', $num_tables, $search_range, $entry_selector, $range_shift );

		$table_offset = 12 + $num_tables * 16;
		foreach ( $tables as &$table ) {
			$table['offset'] = $table_offset;
			$out .= $table['tag'] . $table['checksum'] . pack( 'NN', $table_offset, $table['length'] );
			$table_offset += ( $table['length'] + 3 ) & ~3;
		}
		unset( $table );

		foreach ( $tables as $table ) {
			$out .= $table['data'];
			$pad = ( 4 - ( $table['length'] % 4 ) ) % 4;
			if ( $pad > 0 ) {
				$out .= str_repeat( "\0", $pad );
			}
		}

		if ( ! file_exists( dirname( $dest_path ) ) || ! is_writable( dirname( $dest_path ) ) ) {
			OC_Logger::warning( 'WOFF extraction failed: destination not writable.' );
			return false;
		}

		return strlen( $out ) === $output_size && false !== file_put_contents( $dest_path, $out, LOCK_EX );
	}
}
