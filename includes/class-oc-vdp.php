<?php
defined( 'ABSPATH' ) || exit;

class OC_VDP {

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

		$headers = fgetcsv( $handle );
		if ( false === $headers ) {
			fclose( $handle );
			return [ 'headers' => [], 'rows' => [] ];
		}

		$headers = array_map( 'trim', $headers );
		$headers = array_map( 'sanitize_key', $headers );
		if ( in_array( '', $headers, true ) || count( $headers ) !== count( array_unique( $headers ) ) ) {
			fclose( $handle );
			return [ 'headers' => [], 'rows' => [] ];
		}

		$rows = [];
		while ( ( $row = fgetcsv( $handle ) ) !== false ) {
			$merged = [];
			foreach ( $headers as $i => $header ) {
				$merged[ $header ] = isset( $row[ $i ] ) ? trim( (string) $row[ $i ] ) : '';
			}
			$rows[] = $merged;
		}

		fclose( $handle );

		return [
			'headers' => $headers,
			'rows'    => $rows,
		];
	}

	public function merge_values( string $text, array $row ): string {
		return preg_replace_callback(
			'/\{\{(\w+)\}\}/',
			static function ( $matches ) use ( $row ) {
				$key = $matches[1];
				return $row[ $key ] ?? $matches[0];
			},
			$text
		);
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
