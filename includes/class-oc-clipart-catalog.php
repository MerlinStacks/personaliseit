<?php
/** Cached clipart catalogue and bounded public pages. @package OverCustomise */
defined( 'ABSPATH' ) || exit;

class OC_Clipart_Catalog {
	public const PAGE_SIZE = 60;

	/** Invalidate persistent catalogue data even without an external object cache. */
	public static function invalidate(): void {
		update_option( 'oc_clipart_catalogue_generation', wp_generate_uuid4(), false );
	}

	/** Cache metadata, not customer state or filesystem contents. */
	private static function rows(): array {
		$key = 'oc_clipart_catalogue_' . md5( (string) get_option( 'oc_clipart_catalogue_generation', '1' ) );
		$rows = get_transient( $key );
		if ( is_array( $rows ) ) {
			return $rows;
		}
		global $wpdb;
		$rows = $wpdb->get_results(
			"SELECT c.id, c.name, c.file_path, c.file_type, c.colour_changeable, c.allowed_print_methods,
			 GROUP_CONCAT(DISTINCT gi.group_id SEPARATOR ',') AS group_ids,
			 GROUP_CONCAT(DISTINCT cg.name SEPARATOR '||') AS group_names
			 FROM {$wpdb->prefix}oc_clipart c
			 LEFT JOIN {$wpdb->prefix}oc_clipart_group_items gi ON gi.clipart_id = c.id
			 LEFT JOIN {$wpdb->prefix}oc_clipart_groups cg ON cg.id = gi.group_id
			 WHERE c.active = 1
			 GROUP BY c.id, c.name, c.file_path, c.file_type, c.colour_changeable, c.allowed_print_methods
			 ORDER BY c.name ASC, c.id ASC"
		);
		if ( ! is_array( $rows ) || '' !== (string) $wpdb->last_error ) {
			return [];
		}
		set_transient( $key, $rows, 300 );
		return $rows;
	}

	/** Return a page while preserving a configured default outside the first page. */
	public static function page( array $group_ids, string $method, int $page = 1, string $search = '', string $category = '', int $default_id = 0 ): array {
		$eligible = [];
		$groups = [];
		$default = null;
		foreach ( self::rows() as $row ) {
			$ids = array_map( 'absint', explode( ',', (string) ( $row->group_ids ?? '' ) ) );
			if ( $group_ids && ! array_intersect( $group_ids, $ids ) ) {
				continue;
			}
			$raw = (string) ( $row->allowed_print_methods ?? '' );
			$decoded = json_decode( $raw, true );
			$methods = array_intersect( [ 'engraving', 'uv', 'embroidery', 'sublimation' ], array_map( 'trim', is_array( $decoded ) ? array_filter( $decoded, 'is_string' ) : explode( ',', $raw ) ) );
			if ( $methods && ! in_array( $method, $methods, true ) ) {
				continue;
			}
			$names = array_values( array_filter( array_map( 'trim', explode( '||', (string) ( $row->group_names ?? '' ) ) ) ) );
			$groups = array_merge( $groups, $names );
			if ( (int) $row->id === $default_id ) {
				$default = $row;
			}
			if ( ( '' === $search || false !== stripos( (string) $row->name, $search ) ) && ( '' === $category || in_array( $category, $names, true ) ) ) {
				$eligible[] = $row;
			}
		}
		$page = max( 1, $page );
		$selected = array_slice( $eligible, ( $page - 1 ) * self::PAGE_SIZE, self::PAGE_SIZE );
		if ( 1 === $page && '' === $search && '' === $category && $default ) {
			$selected[] = $default;
		}
		$items = [];
		foreach ( $selected as $row ) {
			$url = self::public_url( (string) $row->file_path );
			if ( '' !== $url ) {
				$items[ (int) $row->id ] = [
					'id' => (int) $row->id, 'name' => (string) $row->name, 'url' => $url,
					'fileType' => (string) $row->file_type,
					'recolourable' => ! empty( $row->colour_changeable ) && 'svg' === strtolower( (string) $row->file_type ),
					'groupNames' => array_values( array_filter( array_map( 'trim', explode( '||', (string) ( $row->group_names ?? '' ) ) ) ) ),
				];
			}
		}
		$groups = array_values( array_unique( $groups ) );
		sort( $groups );
		return [ 'items' => array_values( $items ), 'groups' => $groups, 'hasMore' => count( $eligible ) > $page * self::PAGE_SIZE, 'nextPage' => $page + 1 ];
	}

	/** Validate paths on every use; reuse content revisions between unchanged reads. */
	public static function public_url( string $path, bool $refresh = false ): string {
		$uploads = wp_upload_dir();
		$base = realpath( (string) ( $uploads['basedir'] ?? '' ) );
		$root = realpath( trailingslashit( (string) ( $uploads['basedir'] ?? '' ) ) . 'overcustomise/clipart' );
		$real = realpath( $path );
		if ( ! $base || ! $root || ! $real || ! is_file( $real )
			|| ! str_starts_with( wp_normalize_path( $root ), wp_normalize_path( $base ) . '/' )
			|| ! str_starts_with( wp_normalize_path( $real ), wp_normalize_path( $root ) . '/' ) ) {
			return '';
		}
		$key = 'oc_clipart_revision_' . hash( 'sha256', $real );
		clearstatcache( true, $real );
		$signature = [ filesize( $real ), filemtime( $real ), filectime( $real ) ];
		$cached = get_transient( $key );
		if ( $refresh || ! is_array( $cached ) || ( $cached['signature'] ?? [] ) !== $signature ) {
			$hash = hash_file( 'sha256', $real );
			if ( ! is_string( $hash ) ) {
				return '';
			}
			$cached = [ 'signature' => $signature, 'hash' => substr( $hash, 0, 16 ) ];
			// Bounds out-of-band replacements that preserve size, mtime and ctime too.
			set_transient( $key, $cached, 300 );
		}
		$url = trailingslashit( (string) $uploads['baseurl'] ) . ltrim( substr( wp_normalize_path( $real ), strlen( wp_normalize_path( $base ) ) ), '/' );
		return esc_url_raw( add_query_arg( [ 'oc_media' => $cached['hash'] ], $url ) );
	}
}
