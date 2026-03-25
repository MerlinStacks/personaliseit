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
		$data = @file_get_contents( $src_path );
		if ( false === $data || strlen( $data ) < 12 ) {
			return false;
		}

		// Parse sfnt offset table.
		$sfnt_version = substr( $data, 0, 4 );
		$num_tables   = unpack( 'n', substr( $data, 4, 2 ) )[1];

		if ( ! in_array( strtolower( bin2hex( $sfnt_version ) ), self::VALID_SIGNATURES, true ) ) {
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
			$compressed = gzcompress( $raw, 9 );

			if ( strlen( $compressed ) < strlen( $raw ) ) {
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

		return file_put_contents( $dest_path, $woff ) !== false;
	}
}
