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
		if ( ! file_exists( $src_path ) || ! is_readable( $src_path ) ) {
			OC_Logger::warning( 'WOFF conversion failed: source file is not readable.' );
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

		$flavor = unpack( 'N', $sfnt_version )[1];

		// Parse table directory (16 bytes per entry).
		$tables     = [];
		$dir_offset = 12;
		for ( $i = 0; $i < $num_tables; $i++ ) {
			if ( strlen( $data ) < $dir_offset + 16 ) {
				return false;
			}
			$tag      = substr( $data, $dir_offset, 4 );
			$checksum = unpack( 'N', substr( $data, $dir_offset + 4, 4 ) )[1];
			$offset   = unpack( 'N', substr( $data, $dir_offset + 8, 4 ) )[1];
			$length   = unpack( 'N', substr( $data, $dir_offset + 12, 4 ) )[1];

			$tables[]    = compact( 'tag', 'checksum', 'offset', 'length' );
			$dir_offset += 16;
		}

		// Calculate totalSfntSize: sfnt header + table directory + padded table data.
		$total_sfnt_size = 12 + $num_tables * 16;
		foreach ( $tables as $t ) {
			$total_sfnt_size += ( $t['length'] + 3 ) & ~3;
		}

		// Compress each table with zlib (gzcompress = zlib format = RFC 1950).
		$woff_tables = [];
		foreach ( $tables as $t ) {
			if ( $t['offset'] + $t['length'] > strlen( $data ) ) {
				return false; // Corrupt or truncated font.
			}
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
		if ( false === file_put_contents( $dest_path, $woff ) ) {
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
		if ( ! file_exists( $src_path ) || ! is_readable( $src_path ) ) {
			OC_Logger::warning( 'WOFF extraction failed: source file is not readable.' );
			return false;
		}

		$data = file_get_contents( $src_path );
		if ( false === $data || strlen( $data ) < 44 || '774f4646' !== strtolower( bin2hex( substr( $data, 0, 4 ) ) ) ) {
			OC_Logger::warning( 'WOFF extraction failed: invalid WOFF file.' );
			return false;
		}

		$flavor     = substr( $data, 4, 4 );
		$num_tables = unpack( 'n', substr( $data, 12, 2 ) )[1];
		if ( ! in_array( strtolower( bin2hex( $flavor ) ), self::VALID_SIGNATURES, true ) || $num_tables < 1 ) {
			OC_Logger::warning( 'WOFF extraction failed: invalid font signature.' );
			return false;
		}

		$tables = [];
		$offset = 44;
		for ( $i = 0; $i < $num_tables; $i++ ) {
			if ( strlen( $data ) < $offset + 20 ) {
				return false;
			}
			$tag         = substr( $data, $offset, 4 );
			$table_start = unpack( 'N', substr( $data, $offset + 4, 4 ) )[1];
			$comp_length = unpack( 'N', substr( $data, $offset + 8, 4 ) )[1];
			$orig_length = unpack( 'N', substr( $data, $offset + 12, 4 ) )[1];
			$checksum    = substr( $data, $offset + 16, 4 );
			if ( $table_start + $comp_length > strlen( $data ) ) {
				return false;
			}

			$table_data = substr( $data, $table_start, $comp_length );
			if ( $comp_length !== $orig_length ) {
				$inflated = function_exists( 'gzuncompress' ) ? gzuncompress( $table_data ) : false;
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

		return false !== file_put_contents( $dest_path, $out );
	}
}
