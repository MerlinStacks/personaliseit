<?php
/**
 * File cleanup — daily WP Cron job that expires and deletes old print files.
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

class OC_File_Cleanup {

	private const ARTWORK_DELETE_GRACE_SECONDS = DAY_IN_SECONDS;
	private const PRIVATE_PREVIEW_OPTION_PREFIX = 'oc_private_preview_';
	private const PRIVATE_PREVIEW_CURSOR_OPTION = 'oc_preview_cleanup_private_cursor';
	private const LEGACY_PREVIEW_CURSOR_OPTION = 'oc_legacy_preview_cleanup_cursor';
	private const SECURITY_BUDGET_OPTION_PREFIX = 'oc_budget_';
	private const SECURITY_BUDGET_CURSOR_OPTION = 'oc_budget_cleanup_cursor';

	/** Called by WP Cron daily. */
	public static function run(): void {
		global $wpdb;

		$batch_size = self::filtered_batch_size( 'oc_print_file_cleanup_batch_size', 100, 500 );
		$expired    = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}oc_print_files
			 WHERE file_status IN ('files_ready','awaiting_dst_upload','brief_ready')
			   AND expires_at IS NOT NULL
			   AND expires_at < %s
			 ORDER BY id ASC LIMIT %d",
			current_time( 'mysql', true ),
			$batch_size
		) );

		if ( empty( $expired ) ) {
			self::cleanup_preview_images();
			self::cleanup_customer_artwork();
			self::cleanup_security_budgets();
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';

		$wp_filesystem = null;
		if ( function_exists( 'WP_Filesystem' ) && WP_Filesystem() ) {
			global $wp_filesystem;
		}

		$uploads_base = wp_upload_dir()['basedir'] ?? '';
		$base_real     = $uploads_base ? realpath( trailingslashit( (string) $uploads_base ) . 'overcustomise/print-files' ) : false;
		$handled_paths = [];
		$expired_count = 0;

		foreach ( $expired as $record ) {
			$file_handled  = self::cleanup_record_path( $record, 'file_path', $base_real, $wp_filesystem, $handled_paths );
			$thumb_handled = self::cleanup_record_path( $record, 'thumbnail_path', $base_real, $wp_filesystem, $handled_paths );

			if ( $file_handled && $thumb_handled ) {
				if ( OC_DB::update_print_file( (int) $record->id, [ 'file_status' => 'expired', 'file_path' => null, 'thumbnail_path' => null ] ) ) {
					$expired_count++;
				} else {
					OC_Logger::warning( 'File cleanup could not update print file #' . (int) $record->id );
				}
			} else {
				OC_Logger::warning( 'File cleanup could not safely expire print file #' . (int) $record->id );
			}
		}

		OC_Logger::info( sprintf( 'File cleanup: expired %d print file records.', $expired_count ) );
		self::cleanup_preview_images();
		self::cleanup_customer_artwork();
		self::cleanup_security_budgets();
	}

	/** Delete one path when safe, retaining shared files needed by active records. */
	private static function cleanup_record_path( object $record, string $column, string|false $base_real, mixed $wp_filesystem, array &$handled_paths ): bool {
		global $wpdb;
		$path = (string) ( $record->{$column} ?? '' );
		if ( '' === $path || isset( $handled_paths[ $path ] ) ) {
			return true;
		}

		if ( ! file_exists( $path ) ) {
			$handled_paths[ $path ] = true;
			return true;
		}

		$real = realpath( $path );
		$base = $base_real ? rtrim( $base_real, '/\\' ) . DIRECTORY_SEPARATOR : '';
		if ( ! $real || '' === $base || ! str_starts_with( $real, $base ) ) {
			OC_Logger::warning( 'File cleanup skipped suspicious path: ' . $path );
			return false;
		}

		$shared_active = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}oc_print_files
			 WHERE (file_path = %s OR thumbnail_path = %s) AND id <> %d AND file_status <> 'expired'
			 AND NOT (file_status IN ('files_ready','awaiting_dst_upload','brief_ready')
			 AND expires_at IS NOT NULL AND expires_at < %s)",
			$path,
			$path,
			(int) $record->id,
			current_time( 'mysql', true )
		) );
		if ( $shared_active > 0 ) {
			return true;
		}

		$deleted = $wp_filesystem && method_exists( $wp_filesystem, 'delete' )
			? (bool) $wp_filesystem->delete( $path )
			: @unlink( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		if ( $deleted || ! file_exists( $path ) ) {
			$handled_paths[ $path ] = true;
			return true;
		}

		return false;
	}

	/** Delete old current and legacy saved preview images in bounded batches. */
	private static function cleanup_preview_images(): void {
		$retention_days = (int) OC_Admin_Settings::get( 'file_retention_days' );
		if ( $retention_days <= 0 ) {
			$retention_days = 90;
		}
		$cutoff     = time() - ( $retention_days * DAY_IN_SECONDS );
		$batch_size = self::filtered_batch_size( 'oc_preview_cleanup_batch_size', 200, 1000 );
		$deleted    = self::cleanup_private_preview_images( $cutoff, $batch_size );

		$uploads = wp_upload_dir();
		$legacy  = ! empty( $uploads['basedir'] ) ? trailingslashit( (string) $uploads['basedir'] ) . 'overcustomise/previews' : '';
		if ( '' !== $legacy ) {
			$deleted += self::cleanup_preview_directory( $legacy, $cutoff, $batch_size, self::LEGACY_PREVIEW_CURSOR_OPTION );
		}

		if ( $deleted > 0 ) {
			OC_Logger::info( sprintf( 'File cleanup: removed %d stale preview images.', $deleted ) );
		}
	}

	/** Delete a bounded batch of current private previews and their metadata. */
	private static function cleanup_private_preview_images( int $cutoff, int $limit ): int {
		if ( ! class_exists( 'OC_Upload_Handler' ) ) {
			return 0;
		}
		$directory = OC_Upload_Handler::private_storage_path( 'previews' );
		if ( ! is_string( $directory ) || '' === $directory ) {
			return 0;
		}

		global $wpdb;
		$cursor = max( 0, (int) get_option( self::PRIVATE_PREVIEW_CURSOR_OPTION, 0 ) );
		$like   = $wpdb->esc_like( self::PRIVATE_PREVIEW_OPTION_PREFIX ) . '%';
		$rows   = $wpdb->get_results( $wpdb->prepare(
			"SELECT option_id, option_name, option_value FROM {$wpdb->options}
			 WHERE option_name LIKE %s AND option_id > %d
			 ORDER BY option_id ASC LIMIT %d",
			$like,
			$cursor,
			$limit
		) ) ?: [];
		if ( empty( $rows ) ) {
			if ( $cursor > 0 ) {
				update_option( self::PRIVATE_PREVIEW_CURSOR_OPTION, 0, false );
			}
			return 0;
		}

		$deleted = 0;
		foreach ( $rows as $row ) {
			$option_name = (string) $row->option_name;
			$raw         = (string) $row->option_value;
			$id          = substr( $option_name, strlen( self::PRIVATE_PREVIEW_OPTION_PREFIX ) );
			$record      = json_decode( $raw, true );
			if ( ! preg_match( '/^[a-f0-9]{40}$/D', $id ) || ! is_array( $record ) || ! is_string( $record['file'] ?? null ) || ! preg_match( '/^preview-[a-f0-9]{40}\.(?:png|jpg)$/D', $record['file'] ) ) {
				self::delete_option_if_unchanged( $option_name, $raw );
				continue;
			}

			$candidate = $directory . '/' . $record['file'];
			$real      = realpath( $candidate );
			if ( is_link( $candidate ) || false === $real || ! is_file( $real ) || ! self::path_is_within( $real, $directory ) ) {
				self::delete_option_if_unchanged( $option_name, $raw );
				continue;
			}

			$modified = self::latest_file_access_time( $real, (int) ( $record['created_at'] ?? 0 ) );
			if ( $modified > $cutoff ) {
				continue;
			}
			if ( @unlink( $real ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				self::delete_option_if_unchanged( $option_name, $raw );
				$deleted++;
			}
		}

		$last_row = end( $rows );
		$last_id  = is_object( $last_row ) ? (int) $last_row->option_id : 0;
		update_option( self::PRIVATE_PREVIEW_CURSOR_OPTION, count( $rows ) < $limit ? 0 : $last_id, false );
		return $deleted;
	}

	/** Delete stale files from a directory without materialising its full contents. */
	private static function cleanup_preview_directory( string $directory, int $cutoff, int $limit, string $cursor_option ): int {
		$base = realpath( $directory );
		if ( false === $base || ! is_dir( $base ) ) {
			return 0;
		}

		$cursor  = (string) get_option( $cursor_option, '' );
		$started = '' === $cursor;
		$handled = 0;
		$deleted = 0;
		$last    = '';
		try {
			$iterator = new \FilesystemIterator( $base, \FilesystemIterator::SKIP_DOTS );
			foreach ( $iterator as $file ) {
				$name = $file->getFilename();
				if ( ! $started ) {
					if ( hash_equals( $cursor, $name ) ) {
						$started = true;
					}
					continue;
				}
				if ( ! preg_match( '/^preview-[a-z0-9_-]+\.(?:png|jpe?g)$/iD', $name ) ) {
					continue;
				}

				$last = $name;
				$handled++;
				$real      = $file->getRealPath();
				$safe_file = false !== $real && ! $file->isLink() && $file->isFile() && self::path_is_within( $real, $base );
				if ( $safe_file && self::latest_file_access_time( $real ) <= $cutoff && @unlink( $real ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
					$deleted++;
				}
				if ( $handled >= $limit ) {
					break;
				}
			}
		} catch ( \UnexpectedValueException $e ) {
			return 0;
		}

		update_option( $cursor_option, $handled >= $limit ? $last : '', false );
		return $deleted;
	}

	/** Use access time as a best-effort reuse signal and carry it into mtime. */
	private static function latest_file_access_time( string $path, int $created_at = 0 ): int {
		$modified = @filemtime( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$accessed = @fileatime( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$latest   = max( $created_at, false === $modified ? 0 : $modified, false === $accessed ? 0 : $accessed );
		if ( false !== $modified && $latest > $modified ) {
			@touch( $path, $latest, $latest ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		return $latest;
	}

	/** Remove an option only when it still has the value that was inspected. */
	private static function delete_option_if_unchanged( string $option_name, string $expected_value ): bool {
		global $wpdb;
		$deleted = $wpdb->query( $wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s",
			$option_name,
			$expected_value
		) );
		if ( 1 === $deleted ) {
			wp_cache_delete( $option_name, 'options' );
			wp_cache_delete( 'alloptions', 'options' );
			return true;
		}

		return false;
	}

	/** Delete expired or malformed non-autoloaded security budget rows in bounded batches. */
	private static function cleanup_security_budgets(): void {
		global $wpdb;

		$limit  = self::filtered_batch_size( 'oc_security_budget_cleanup_batch_size', 200, 1000 );
		$cursor = max( 0, (int) get_option( self::SECURITY_BUDGET_CURSOR_OPTION, 0 ) );
		$like   = $wpdb->esc_like( self::SECURITY_BUDGET_OPTION_PREFIX ) . '%';
		$rows   = $wpdb->get_results( $wpdb->prepare(
			"SELECT option_id, option_name, option_value FROM {$wpdb->options}
			 WHERE option_name LIKE %s AND option_name <> %s AND option_id > %d
			 ORDER BY option_id ASC LIMIT %d",
			$like,
			self::SECURITY_BUDGET_CURSOR_OPTION,
			$cursor,
			$limit
		) ) ?: [];
		if ( empty( $rows ) ) {
			if ( $cursor > 0 ) {
				update_option( self::SECURITY_BUDGET_CURSOR_OPTION, 0, false );
			}
			return;
		}

		foreach ( $rows as $row ) {
			$raw   = (string) $row->option_value;
			$state = json_decode( $raw, true );
			if ( ! is_array( $state )
				|| ! is_int( $state['version'] ?? null ) || 1 !== $state['version']
				|| ! is_int( $state['window_start'] ?? null ) || $state['window_start'] <= 0
				|| ! is_int( $state['window_end'] ?? null ) || $state['window_end'] <= $state['window_start'] || $state['window_end'] <= time()
				|| ! is_int( $state['count'] ?? null ) || $state['count'] < 0
				|| ! is_int( $state['bytes'] ?? null ) || $state['bytes'] < 0
			) {
				self::delete_option_if_unchanged( (string) $row->option_name, $raw );
			}
		}

		$last_row = end( $rows );
		$last_id  = is_object( $last_row ) ? (int) $last_row->option_id : 0;
		update_option( self::SECURITY_BUDGET_CURSOR_OPTION, count( $rows ) < $limit ? 0 : $last_id, false );
	}

	/** Return a numeric batch-size filter inside a hard upper bound. */
	private static function filtered_batch_size( string $filter, int $default, int $maximum ): int {
		$value = apply_filters( $filter, $default );
		return is_numeric( $value ) ? max( 1, min( $maximum, (int) $value ) ) : $default;
	}

	/** Return whether a resolved path is inside a trusted directory. */
	private static function path_is_within( string $path, string $directory ): bool {
		$path      = wp_normalize_path( $path );
		$directory = rtrim( wp_normalize_path( $directory ), '/' );
		if ( str_starts_with( strtoupper( PHP_OS_FAMILY ), 'WINDOWS' ) ) {
			$path      = strtolower( $path );
			$directory = strtolower( $directory );
		}
		return '' !== $directory && str_starts_with( $path, $directory . '/' );
	}

	/** Delete expired customer artwork that is no longer referenced by an order or cart. */
	private static function cleanup_customer_artwork(): void {
		$retention_days = max( 1, (int) OC_Admin_Settings::get( 'file_retention_days' ) ?: 90 );
		$cursor         = max( 0, (int) get_option( 'oc_artwork_cleanup_cursor', 0 ) );
		$cutoff         = gmdate( 'Y-m-d H:i:s', time() - ( $retention_days * DAY_IN_SECONDS ) );
		$limit          = 100;

		global $wpdb;
		$attachments = $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT p.ID FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
			 WHERE p.post_type = 'attachment'
			 AND p.post_status IN ('private','inherit')
			 AND p.post_date_gmt < %s
			 AND p.ID > %d
			 AND pm.meta_key = '_oc_artwork' AND pm.meta_value = '1'
			 ORDER BY p.ID ASC LIMIT %d",
			$cutoff,
			$cursor,
			$limit
		) );
		if ( empty( $attachments ) ) {
			if ( $cursor > 0 ) {
				update_option( 'oc_artwork_cleanup_cursor', 0, false );
			}
			return;
		}

		foreach ( array_map( 'absint', $attachments ) as $attachment_id ) {
			if ( self::customer_artwork_is_referenced( $attachment_id ) ) {
				delete_post_meta( $attachment_id, '_oc_cleanup_unreferenced_since' );
				continue;
			}

			// Require two cleanup passes so a cart being persisted concurrently with
			// this cron run cannot immediately lose its newly uploaded artwork.
			$unreferenced_since = (int) get_post_meta( $attachment_id, '_oc_cleanup_unreferenced_since', true );
			if ( $unreferenced_since <= 0 ) {
				update_post_meta( $attachment_id, '_oc_cleanup_unreferenced_since', time() );
				continue;
			}
			if ( $unreferenced_since > time() - self::ARTWORK_DELETE_GRACE_SECONDS ) {
				continue;
			}

			// Recheck immediately before deletion. False positives are preferable to
			// deleting customer data that has just reached checkout.
			if ( ! self::customer_artwork_is_referenced( $attachment_id ) ) {
				wp_delete_attachment( $attachment_id, true );
			}
		}

		update_option( 'oc_artwork_cleanup_cursor', max( array_map( 'absint', $attachments ) ), false );
	}

	/** Check orders, active WC sessions, and persistent customer carts. */
	public static function customer_artwork_is_referenced( int $attachment_id ): bool {
		if ( $attachment_id <= 0 ) {
			return false;
		}

		global $wpdb;

		$related_ids = [ $attachment_id ];
		$parent_id   = absint( get_post_meta( $attachment_id, '_oc_artwork_parent_id', true ) );
		if ( $parent_id > 0 ) {
			$related_ids[] = $parent_id;
		}
		foreach ( [ '_oc_print_derivative_attachment_id', '_oc_artwork_preview_attachment_id' ] as $meta_key ) {
			$related_id = absint( get_post_meta( $attachment_id, $meta_key, true ) );
			if ( $related_id > 0 ) {
				$related_ids[] = $related_id;
			}
		}

		$patterns = [];
		foreach ( array_unique( array_filter( $related_ids ) ) as $related_id ) {
			$patterns = array_merge( $patterns, self::artwork_reference_patterns( $related_id ) );
		}
		$patterns = array_values( array_unique( $patterns ) );
		$clauses  = implode( ' OR ', array_fill( 0, count( $patterns ), 'meta_value LIKE %s' ) );
		$found    = $wpdb->get_var( $wpdb->prepare(
			"SELECT meta_id FROM {$wpdb->prefix}woocommerce_order_itemmeta
			 WHERE meta_key = '_oc_customisation' AND ({$clauses}) LIMIT 1",
			...$patterns
		) );
		if ( $found ) {
			return true;
		}

		$sessions_table = $wpdb->prefix . 'woocommerce_sessions';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $sessions_table ) ) ) === $sessions_table ) {
			$session_clauses = implode( ' OR ', array_fill( 0, count( $patterns ), 'session_value LIKE %s' ) );
			$session_args    = array_merge( [ time() ], $patterns );
			$found           = $wpdb->get_var( $wpdb->prepare(
				"SELECT session_id FROM {$sessions_table}
				 WHERE session_expiry >= %d AND ({$session_clauses}) LIMIT 1",
				...$session_args
			) );
			if ( $found ) {
				return true;
			}
		}

		$persistent_clauses = implode( ' OR ', array_fill( 0, count( $patterns ), 'meta_value LIKE %s' ) );
		$persistent_key     = $wpdb->esc_like( '_woocommerce_persistent_cart_' ) . '%';
		return (bool) $wpdb->get_var( $wpdb->prepare(
			"SELECT umeta_id FROM {$wpdb->usermeta}
			 WHERE meta_key LIKE %s AND ({$persistent_clauses}) LIMIT 1",
			$persistent_key,
			...$patterns
		) );
	}

	/** Build exact common PHP-serialization and JSON reference forms. */
	private static function artwork_reference_patterns( int $attachment_id ): array {
		global $wpdb;

		$id       = (string) $attachment_id;
		$patterns = [];
		foreach ( [ 'attachmentId', 'sourceAttachmentId', 'previewAttachmentId', 'artworkAttachmentId' ] as $key ) {
			$values = [
				's:' . strlen( $key ) . ':"' . $key . '";i:' . $id . ';',
				's:' . strlen( $key ) . ':"' . $key . '";s:' . strlen( $id ) . ':"' . $id . '";',
				'"' . $key . '":' . $id . ',',
				'"' . $key . '":' . $id . '}',
				'"' . $key . '": ' . $id . ',',
				'"' . $key . '": ' . $id . '}',
				'"' . $key . '":"' . $id . '"',
				'"' . $key . '": "' . $id . '"',
			];
			foreach ( $values as $value ) {
				$patterns[] = '%' . $wpdb->esc_like( $value ) . '%';
			}
		}

		return $patterns;
	}
}
