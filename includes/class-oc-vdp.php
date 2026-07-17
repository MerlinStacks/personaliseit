<?php
defined( 'ABSPATH' ) || exit;

class OC_VDP {
	public const MAX_ROWS = 250;
	public const MAX_COLUMNS = 50;
	public const MAX_CELL_BYTES = 4096;

	public function is_enabled( int $design_id ): bool {
		$template = OC_DB::get_vdp_template( $design_id );
		return ! empty( $template ) && (int) $template->active === 1;
	}

	public function get_template( int $design_id ): ?array {
		$template = OC_DB::get_vdp_template( $design_id );
		if ( empty( $template ) ) {
			return null;
		}

		$fields = OC_DB::get_vdp_fields( (int) $template->id );

		return [
			'id'            => (int) $template->id,
			'design_id'     => (int) $template->design_id,
			'csv_file_path' => (string) ( $template->csv_file_path ?? '' ),
			'active'        => (bool) $template->active,
			'created_at'    => (string) $template->created_at,
			'fields'        => array_values(
				array_map(
					static function ( $f ) {
						return [
							'id'         => (int) $f->id,
							'field_name' => (string) $f->field_name,
							'layer_id'   => (int) $f->layer_id,
							'sort_order' => (int) $f->sort_order,
						];
					},
					$fields
				)
			),
		];
	}

	public function parse_csv( string $file_path ): array {
		if ( ! file_exists( $file_path ) ) {
			return [ 'headers' => [], 'rows' => [] ];
		}

		$handle = fopen( $file_path, 'r' );
		if ( false === $handle ) {
			return [ 'headers' => [], 'rows' => [] ];
		}

		$headers = fgetcsv( $handle, null, ',', '"', '' );
		if ( false === $headers ) {
			fclose( $handle );
			return [ 'headers' => [], 'rows' => [] ];
		}

		if ( count( $headers ) > self::MAX_COLUMNS ) {
			fclose( $handle );
			return [ 'headers' => [], 'rows' => [], 'error' => __( 'The CSV has too many columns.', 'overcustomise' ) ];
		}
		if ( isset( $headers[0] ) && is_string( $headers[0] ) ) {
			$headers[0] = preg_replace( '/^\xEF\xBB\xBF/', '', $headers[0] ) ?? $headers[0];
		}
		if ( array_filter( $headers, static fn( mixed $header ): bool => strlen( (string) $header ) > self::MAX_CELL_BYTES ) ) {
			fclose( $handle );
			return [ 'headers' => [], 'rows' => [], 'error' => __( 'A CSV header exceeds the safe length limit.', 'overcustomise' ) ];
		}

		$headers = array_map( static fn( mixed $header ): string => trim( (string) $header ), $headers );
		$headers = array_map( 'sanitize_key', $headers );
		if ( in_array( '', $headers, true ) || count( $headers ) !== count( array_unique( $headers ) ) || array_filter( $headers, static fn( string $header ): bool => strlen( $header ) > 50 ) ) {
			fclose( $handle );
			return [ 'headers' => [], 'rows' => [], 'error' => __( 'CSV headers must be unique and no longer than 50 characters.', 'overcustomise' ) ];
		}

		$rows        = [];
		$line_number = 1;
		while ( ( $row = fgetcsv( $handle, null, ',', '"', '' ) ) !== false ) {
			$line_number++;
			if ( empty( array_filter( $row, static fn( $value ): bool => '' !== trim( (string) $value ) ) ) ) {
				continue;
			}
			if ( count( $row ) !== count( $headers ) ) {
				fclose( $handle );
				return [ 'headers' => [], 'rows' => [], 'error' => sprintf( __( 'CSV row %d does not contain the same number of columns as the header.', 'overcustomise' ), $line_number ) ];
			}
			if ( count( $rows ) >= self::MAX_ROWS ) {
				fclose( $handle );
				return [ 'headers' => [], 'rows' => [], 'error' => sprintf( __( 'The CSV exceeds the maximum of %d data rows.', 'overcustomise' ), self::MAX_ROWS ) ];
			}
			$merged = [];
			foreach ( $headers as $i => $header ) {
				$value = isset( $row[ $i ] ) ? trim( (string) $row[ $i ] ) : '';
				if ( strlen( $value ) > self::MAX_CELL_BYTES ) {
					fclose( $handle );
					return [ 'headers' => [], 'rows' => [], 'error' => sprintf( __( 'A value in column "%s" is too long.', 'overcustomise' ), $header ) ];
				}
				$merged[ $header ] = function_exists( 'wp_check_invalid_utf8' ) ? wp_check_invalid_utf8( $value, true ) : $value;
			}
			$rows[] = $merged;
		}
		if ( ! feof( $handle ) ) {
			fclose( $handle );
			return [ 'headers' => [], 'rows' => [], 'error' => __( 'The CSV could not be read completely.', 'overcustomise' ) ];
		}

		fclose( $handle );

		return [
			'headers' => $headers,
			'rows'    => $rows,
			'error'   => '',
		];
	}

	public function merge_values( string $text, array $row ): string {
		return preg_replace_callback(
			'/\{\{([a-z0-9_-]+)\}\}/i',
			static function ( $matches ) use ( $row ) {
				$key = $matches[1];
				return $row[ $key ] ?? $matches[0];
			},
			$text
		);
	}

	/** Sanitize one CSV value against the mapped variable layer. */
	public function normalise_layer_value( object $layer, string $value ): string|\WP_Error {
		$type = (string) ( $layer->type ?? '' );
		if ( ! in_array( $type, [ 'text', 'textarea', 'spotify' ], true ) || ! empty( $layer->locked ) ) {
			return new \WP_Error( 'invalid_vdp_layer', __( 'VDP fields may only target editable text, textarea, or Spotify layers.', 'overcustomise' ) );
		}
		$value = 'textarea' === $type && function_exists( 'sanitize_textarea_field' )
			? sanitize_textarea_field( $value )
			: sanitize_text_field( $value );
		if ( 'spotify' === $type && '' !== $value ) {
			$value = OC_Cart::normalise_spotify_value( $value );
			if ( '' === $value ) {
				return new \WP_Error( 'invalid_vdp_spotify', sprintf( __( 'A value for "%s" is not a valid Spotify link.', 'overcustomise' ), $layer->label ?: $layer->type ) );
			}
		}
		$settings = ! empty( $layer->settings ) ? json_decode( (string) $layer->settings, true ) : [];
		$settings = is_array( $settings ) ? $settings : [];
		if ( ! empty( $settings['required'] ) && '' === trim( $value ) ) {
			return new \WP_Error( 'required_vdp_value', sprintf( __( 'A value is required for "%s".', 'overcustomise' ), $layer->label ?: $layer->type ) );
		}
		$limit    = absint( $settings['char_limit'] ?? 0 );
		$length   = function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) : strlen( $value );
		if ( $limit > 0 && $length > $limit ) {
			return new \WP_Error( 'vdp_character_limit', sprintf( __( 'A value for "%1$s" exceeds its %2$d-character limit.', 'overcustomise' ), $layer->label ?: $layer->type, $limit ) );
		}

		return $value;
	}

	public function get_field_count( int $design_id ): int {
		$template = OC_DB::get_vdp_template( $design_id );
		if ( empty( $template ) ) {
			return 0;
		}

		$fields = OC_DB::get_vdp_fields( (int) $template->id );
		return count( $fields );
	}
}
