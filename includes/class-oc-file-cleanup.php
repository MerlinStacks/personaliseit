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
	private const PREVIEW_REFERENCE_SCAN_OPTION_PREFIX = 'oc_preview_reference_scan_';
	private const SECURITY_BUDGET_OPTION_PREFIX = 'oc_budget_';
	private const SECURITY_BUDGET_CURSOR_OPTION = 'oc_budget_cleanup_cursor';
	private const ARTWORK_REFERENCE_SCAN_LIMIT   = 1000;
	private static array $preview_reference_scan_complete = [];
	private static ?bool $sessions_table_exists           = null;

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
		$shared_paths  = self::get_active_shared_paths( $expired );
		if ( null === $shared_paths ) {
			OC_Logger::warning( 'File cleanup retained a print-file batch because its shared references could not be checked.' );
			self::cleanup_preview_images();
			self::cleanup_customer_artwork();
			self::cleanup_security_budgets();
			return;
		}

		foreach ( $expired as $record ) {
			$file_handled  = self::cleanup_record_path( $record, 'file_path', $base_real, $wp_filesystem, $handled_paths, $shared_paths );
			$thumb_handled = self::cleanup_record_path( $record, 'thumbnail_path', $base_real, $wp_filesystem, $handled_paths, $shared_paths );

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
	private static function cleanup_record_path( object $record, string $column, string|false $base_real, mixed $wp_filesystem, array &$handled_paths, array $shared_paths ): bool {
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

		if ( isset( $shared_paths[ $path ] ) ) {
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

	/** Resolve all active references for an expiry batch with one bounded query. */
	private static function get_active_shared_paths( array $records ): ?array {
		global $wpdb;
		$paths = [];
		foreach ( $records as $record ) {
			foreach ( [ 'file_path', 'thumbnail_path' ] as $column ) {
				$path = (string) ( $record->{$column} ?? '' );
				if ( '' !== $path ) {
					$paths[ $path ] = true;
				}
			}
		}
		$path_lookup = $paths;
		$paths       = array_keys( $paths );
		if ( empty( $path_lookup ) ) {
			return [];
		}

		$placeholders = implode( ',', array_fill( 0, count( $paths ), '%s' ) );
		$now          = current_time( 'mysql', true );
		$args         = array_merge( $paths, [ $now ], $paths, [ $now ] );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- placeholders are generated from the bounded path count and every value is passed to prepare().
		$active_paths = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT file_path AS path FROM {$wpdb->prefix}oc_print_files
			 WHERE file_path IN ({$placeholders})
			 AND file_status <> 'expired'
			 AND NOT (file_status IN ('files_ready','awaiting_dst_upload','brief_ready')
			 AND expires_at IS NOT NULL AND expires_at < %s)
			 UNION
			 SELECT DISTINCT thumbnail_path AS path FROM {$wpdb->prefix}oc_print_files
			 WHERE thumbnail_path IN ({$placeholders})
			 AND file_status <> 'expired'
			 AND NOT (file_status IN ('files_ready','awaiting_dst_upload','brief_ready')
			 AND expires_at IS NOT NULL AND expires_at < %s)",
				...$args
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		if ( ! is_array( $active_paths ) || self::database_has_error( $wpdb ) ) {
			return null;
		}

		$active = [];
		foreach ( $active_paths as $path ) {
			$path = (string) $path;
			if ( isset( $path_lookup[ $path ] ) ) {
				$active[ $path ] = true;
			}
		}
		return $active;
	}

	/** Delete old current and legacy saved preview images in bounded batches. */
	private static function cleanup_preview_images(): void {
		$retention_days = (int) OC_Admin_Settings::get( 'preview_retention_days' );
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
			delete_option( self::PREVIEW_REFERENCE_SCAN_OPTION_PREFIX . 'private' );
			self::$preview_reference_scan_complete['private'] = true;
			if ( $cursor > 0 ) {
				update_option( self::PRIVATE_PREVIEW_CURSOR_OPTION, 0, false );
			}
			return 0;
		}

		$deleted    = 0;
		$candidates = [];
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
			$candidates[] = [
				'id'          => $id,
				'file'        => $record['file'],
				'path'        => $real,
				'option_name' => $option_name,
				'raw'         => $raw,
			];
		}

		$scan_key   = 'private';
		$references = self::stored_payload_references( array_merge(
			array_column( $candidates, 'id' ),
			array_column( $candidates, 'file' )
		), $scan_key, array_map(
			static fn ( array $candidate ): string => hash( 'sha256', $candidate['raw'] ) . '|' . (string) @filemtime( $candidate['path'] ),
			$candidates
		) );
		if ( empty( self::$preview_reference_scan_complete[ $scan_key ] ) ) {
			return 0;
		}
		foreach ( $candidates as $candidate ) {
			if ( isset( $references[ $candidate['id'] ] ) || isset( $references[ $candidate['file'] ] ) ) {
				continue;
			}
			if ( (string) get_option( $candidate['option_name'], '' ) !== $candidate['raw'] || self::latest_file_access_time( $candidate['path'] ) > $cutoff ) {
				continue;
			}
			if ( @unlink( $candidate['path'] ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				self::delete_option_if_unchanged( $candidate['option_name'], $candidate['raw'] );
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
		$deleted    = 0;
		$last       = '';
		$candidates = [];
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
				if ( $safe_file && self::latest_file_access_time( $real ) <= $cutoff ) {
					$candidates[ $name ] = $real;
				}
				if ( $handled >= $limit ) {
					break;
				}
			}
		} catch ( \UnexpectedValueException $e ) {
			return 0;
		}

		$scan_key   = 'legacy';
		$references = self::stored_payload_references(
			array_keys( $candidates ),
			$scan_key,
			array_map( static fn ( string $path ): int => (int) @filemtime( $path ), $candidates )
		);
		if ( empty( self::$preview_reference_scan_complete[ $scan_key ] ) ) {
			return 0;
		}
		foreach ( $candidates as $name => $path ) {
			if ( ! isset( $references[ $name ] ) && self::latest_file_access_time( $path ) <= $cutoff && @unlink( $path ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				$deleted++;
			}
		}

		$next_cursor = $handled >= $limit && is_file( $base . DIRECTORY_SEPARATOR . $last ) ? $last : '';
		update_option( $cursor_option, $next_cursor, false );
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
			$valid = is_array( $state );
			if ( $valid && 1 === ( $state['version'] ?? null ) ) {
				$valid = is_int( $state['window_start'] ?? null ) && $state['window_start'] > 0
					&& is_int( $state['window_end'] ?? null ) && $state['window_end'] > $state['window_start'] && $state['window_end'] > time()
					&& is_int( $state['count'] ?? null ) && $state['count'] >= 0
					&& is_int( $state['bytes'] ?? null ) && $state['bytes'] >= 0;
			} elseif ( $valid && 2 === ( $state['version'] ?? null ) ) {
				$duration = $state['window_seconds'] ?? null;
				$buckets  = $state['buckets'] ?? null;
				$valid    = 'sliding' === ( $state['window_type'] ?? null ) && is_int( $duration ) && $duration > 0 && $duration <= DAY_IN_SECONDS
					&& is_array( $buckets ) && array_is_list( $buckets ) && count( $buckets ) <= $duration + 1;
				$last = 0;
				$cleanup_time = time();
				foreach ( $valid ? $buckets : [] as $bucket ) {
					if ( ! is_array( $bucket ) || ! is_int( $bucket['timestamp'] ?? null ) || $bucket['timestamp'] <= $last
						|| $bucket['timestamp'] > $cleanup_time
						|| ! is_int( $bucket['count'] ?? null ) || $bucket['count'] < 0 || ! is_int( $bucket['bytes'] ?? null ) || $bucket['bytes'] < 0
						|| ( 0 === $bucket['count'] && 0 === $bucket['bytes'] )
					) {
						$valid = false;
						break;
					}
					$last = $bucket['timestamp'];
				}
				$valid = $valid && $last + $duration > $cleanup_time;
			} else {
				$valid = false;
			}
			if ( ! $valid ) {
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
		$retention_days = max( 1, (int) OC_Admin_Settings::get( 'artwork_retention_days' ) ?: 90 );
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

		$attachment_ids = array_map( 'absint', $attachments );
		$references     = self::customer_artwork_batch_references( $attachment_ids );
		if ( null === $references ) {
			OC_Logger::warning( 'Customer artwork cleanup retained a batch because its references could not be checked.' );
			update_option( 'oc_artwork_cleanup_cursor', max( $attachment_ids ), false );
			return;
		}

		foreach ( $attachment_ids as $attachment_id ) {
			if ( isset( $references[ $attachment_id ] ) ) {
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

		update_option( 'oc_artwork_cleanup_cursor', max( $attachment_ids ), false );
	}

	/** Resolve references for a cleanup batch with one wildcard scan per payload store. */
	private static function customer_artwork_batch_references( array $attachment_ids ): ?array {
		global $wpdb;

		$attachment_ids         = array_values( array_unique( array_filter( array_map( 'absint', $attachment_ids ) ) ) );
		$fragments_by_attachment = [];
		$related_ids             = [];
		foreach ( $attachment_ids as $attachment_id ) {
			$ids = self::related_artwork_ids( $attachment_id );
			foreach ( $ids as $related_id ) {
				$related_ids[ $related_id ]                = true;
				$fragments_by_attachment[ $attachment_id ] = array_merge(
					$fragments_by_attachment[ $attachment_id ] ?? [],
					self::artwork_reference_fragments( $related_id )
				);
			}
		}
		if ( empty( $related_ids ) ) {
			return [];
		}

		$id_pattern     = '(^|[^0-9])(' . implode( '|', array_keys( $related_ids ) ) . ')([^0-9]|$)';
		$payloads       = [];
		$queries        = [
			$wpdb->prepare(
				"SELECT meta_value FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE meta_key = '_oc_customisation' AND meta_value REGEXP %s LIMIT %d",
				$id_pattern,
				self::ARTWORK_REFERENCE_SCAN_LIMIT + 1
			),
		];
		$sessions_exist = self::sessions_table_exists();
		if ( null === $sessions_exist ) {
			return null;
		}
		if ( $sessions_exist ) {
			$queries[] = $wpdb->prepare(
				"SELECT session_value FROM {$wpdb->prefix}woocommerce_sessions WHERE session_expiry >= %d AND session_value REGEXP %s LIMIT %d",
				time(),
				$id_pattern,
				self::ARTWORK_REFERENCE_SCAN_LIMIT + 1
			);
		}
		$queries[] = $wpdb->prepare(
			"SELECT meta_value FROM {$wpdb->usermeta} WHERE meta_key LIKE %s AND meta_value REGEXP %s LIMIT %d",
			$wpdb->esc_like( '_woocommerce_persistent_cart_' ) . '%',
			$id_pattern,
			self::ARTWORK_REFERENCE_SCAN_LIMIT + 1
		);

		foreach ( $queries as $query ) {
			$rows = $wpdb->get_col( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Every query is prepared when the bounded list is built above.
			if ( ! is_array( $rows ) || self::database_has_error( $wpdb ) ) {
				return null;
			}
			if ( count( $rows ) > self::ARTWORK_REFERENCE_SCAN_LIMIT ) {
				return self::split_customer_artwork_batch_references( $attachment_ids );
			}
			$payloads = array_merge( $payloads, array_map( 'strval', $rows ) );
		}

		$references = [];
		foreach ( $fragments_by_attachment as $attachment_id => $fragments ) {
			foreach ( $payloads as $payload ) {
				foreach ( array_unique( $fragments ) as $fragment ) {
					if ( str_contains( $payload, $fragment ) ) {
						$references[ $attachment_id ] = true;
						break 2;
					}
				}
			}
		}

		return $references;
	}

	/** Split an overly broad batch, falling back to exact checks for one attachment. */
	private static function split_customer_artwork_batch_references( array $attachment_ids ): ?array {
		if ( 1 === count( $attachment_ids ) ) {
			$attachment_id = (int) reset( $attachment_ids );
			return self::customer_artwork_is_referenced( $attachment_id ) ? [ $attachment_id => true ] : [];
		}

		$references = [];
		foreach ( array_chunk( $attachment_ids, (int) ceil( count( $attachment_ids ) / 2 ) ) as $chunk ) {
			$chunk_references = self::customer_artwork_batch_references( $chunk );
			if ( null === $chunk_references ) {
				return null;
			}
			$references += $chunk_references;
		}

		return $references;
	}

	/**
	 * Check orders, active WC sessions, and persistent customer carts.
	 *
	 * @phpstan-impure
	 */
	public static function customer_artwork_is_referenced( int $attachment_id ): bool {
		if ( $attachment_id <= 0 ) {
			return false;
		}

		global $wpdb;

		$related_ids = self::related_artwork_ids( $attachment_id );

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
		if ( self::database_has_error( $wpdb ) ) {
			return true;
		}
		if ( $found ) {
			return true;
		}

		$sessions_table  = $wpdb->prefix . 'woocommerce_sessions';
		$sessions_exists = self::sessions_table_exists();
		if ( null === $sessions_exists ) {
			return true;
		}
		if ( $sessions_exists ) {
			$session_clauses = implode( ' OR ', array_fill( 0, count( $patterns ), 'session_value LIKE %s' ) );
			$session_args    = array_merge( [ time() ], $patterns );
			$found           = $wpdb->get_var( $wpdb->prepare(
				"SELECT session_id FROM {$sessions_table}
				 WHERE session_expiry >= %d AND ({$session_clauses}) LIMIT 1",
				...$session_args
			) );
			if ( self::database_has_error( $wpdb ) ) {
				return true;
			}
			if ( $found ) {
				return true;
			}
		}

		$persistent_clauses = implode( ' OR ', array_fill( 0, count( $patterns ), 'meta_value LIKE %s' ) );
		$persistent_key     = $wpdb->esc_like( '_woocommerce_persistent_cart_' ) . '%';
		$found = $wpdb->get_var( $wpdb->prepare(
			"SELECT umeta_id FROM {$wpdb->usermeta}
			 WHERE meta_key LIKE %s AND ({$persistent_clauses}) LIMIT 1",
			$persistent_key,
			...$patterns
		) );
		return self::database_has_error( $wpdb ) || (bool) $found;
	}

	/** Return attachment IDs whose payload references protect one artwork lifecycle. */
	private static function related_artwork_ids( int $attachment_id ): array {
		$related_ids = [ $attachment_id ];
		foreach ( [ '_oc_artwork_parent_id', '_oc_print_derivative_attachment_id', '_oc_artwork_preview_attachment_id' ] as $meta_key ) {
			$related_id = absint( get_post_meta( $attachment_id, $meta_key, true ) );
			if ( $related_id > 0 ) {
				$related_ids[] = $related_id;
			}
		}
		return array_values( array_unique( $related_ids ) );
	}

	/** Check the optional WooCommerce sessions table once per request. */
	private static function sessions_table_exists(): ?bool {
		if ( null !== self::$sessions_table_exists ) {
			return self::$sessions_table_exists;
		}

		global $wpdb;
		$table  = $wpdb->prefix . 'woocommerce_sessions';
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
		if ( self::database_has_error( $wpdb ) ) {
			return null;
		}
		self::$sessions_table_exists = $exists === $table;
		return self::$sessions_table_exists;
	}

	/** Return candidate fragments referenced by order metadata, active sessions, or persistent carts. */
	private static function stored_payload_references( array $needles, string $scan_key = 'adhoc', array $freshness = [] ): array {
		global $wpdb;

		$clean_needles = [];
		foreach ( $needles as $needle ) {
			if ( is_scalar( $needle ) && '' !== (string) $needle ) {
				$clean_needles[ (string) $needle ] = true;
			}
		}
		$clean_needles = array_keys( $clean_needles );
		$scan_key      = preg_replace( '/[^a-z0-9_-]/', '', strtolower( $scan_key ) ) ?: 'adhoc';
		$option_name   = self::PREVIEW_REFERENCE_SCAN_OPTION_PREFIX . $scan_key;
		if ( empty( $clean_needles ) ) {
			delete_option( $option_name );
			self::$preview_reference_scan_complete[ $scan_key ] = true;
			return [];
		}

		$signature = hash( 'sha256', (string) wp_json_encode( [ $clean_needles, $freshness ] ) );
		$state     = get_option( $option_name, [] );
		if ( ! is_array( $state ) || ! hash_equals( $signature, (string) ( $state['signature'] ?? '' ) ) ) {
			$state = [
				'signature' => $signature,
				'source'    => 'order',
				'cursor'    => 0,
				'max'       => null,
				'active_at' => 0,
				'references' => [],
				'order_seen' => 0,
				'session_seen' => 0,
				'persistent_seen' => 0,
			];
		}
		$state['order_seen']      = max( 0, (int) ( $state['order_seen'] ?? 0 ) );
		$state['session_seen']    = max( 0, (int) ( $state['session_seen'] ?? 0 ) );
		$state['persistent_seen'] = max( 0, (int) ( $state['persistent_seen'] ?? 0 ) );
		$source = in_array( $state['source'] ?? '', [ 'order', 'session', 'persistent' ], true ) ? (string) $state['source'] : 'order';
		$references = array_fill_keys( array_values( array_intersect( $clean_needles, (array) ( $state['references'] ?? [] ) ) ), true );
		$unresolved = array_diff_key( array_fill_keys( $clean_needles, true ), $references );
		$page_size       = self::filtered_batch_size( 'oc_preview_reference_payload_batch_size', 50, 200 );
		$runtime_seconds = apply_filters( 'oc_preview_reference_runtime_seconds', 2 );
		$runtime_seconds = is_numeric( $runtime_seconds ) ? max( 1, min( 10, (float) $runtime_seconds ) ) : 2;
		$deadline        = microtime( true ) + $runtime_seconds;

		if ( 'order' === $source && ! empty( $unresolved ) ) {
			$order_table  = $wpdb->prefix . 'woocommerce_order_itemmeta';
			$order_cursor = max( 0, (int) ( $state['cursor'] ?? 0 ) );
			$order_max    = $state['max'] ?? null;
			if ( null === $order_max ) {
				$order_max = $wpdb->get_var(
					"SELECT MAX(meta_id) FROM {$order_table} WHERE meta_key IN ('_oc_customisation','_oc_preview_url')"
				);
				if ( self::database_has_error( $wpdb ) ) {
					return self::pause_preview_reference_scan( $option_name, $scan_key, $state, $references, $clean_needles );
				}
				$order_max    = absint( $order_max );
				$state['max'] = $order_max;
				$state['order_seen'] = max( $state['order_seen'], $order_max );
			}
			while ( $order_cursor < $order_max && ! empty( $unresolved ) ) {
				if ( microtime( true ) >= $deadline ) {
					return self::pause_preview_reference_scan( $option_name, $scan_key, $state, $references, $clean_needles );
				}
				$rows = $wpdb->get_results( $wpdb->prepare(
					"SELECT meta_id AS row_id, meta_value AS payload FROM {$order_table}
					 WHERE meta_key IN ('_oc_customisation','_oc_preview_url') AND meta_id > %d AND meta_id <= %d
					 ORDER BY meta_id ASC LIMIT %d",
					$order_cursor,
					$order_max,
					$page_size
				) );
				if ( self::database_has_error( $wpdb ) || ! is_array( $rows ) ) {
					return self::pause_preview_reference_scan( $option_name, $scan_key, $state, $references, $clean_needles );
				}
				if ( empty( $rows ) ) {
					break;
				}
				self::record_payload_references( $rows, $unresolved, $references );
				$order_cursor   = absint( end( $rows )->row_id ?? 0 );
				$state['cursor'] = $order_cursor;
				if ( $order_cursor <= 0 ) {
					return self::pause_preview_reference_scan( $option_name, $scan_key, $state, $references, $clean_needles );
				}
			}
			$source          = 'session';
			$state['source'] = $source;
			$state['cursor'] = 0;
			$state['max']    = null;
			$state['active_at'] = 0;
		}

		if ( 'session' === $source && ! empty( $unresolved ) ) {
			$sessions_table  = $wpdb->prefix . 'woocommerce_sessions';
			$sessions_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $sessions_table ) ) );
			if ( self::database_has_error( $wpdb ) ) {
				return self::pause_preview_reference_scan( $option_name, $scan_key, $state, $references, $clean_needles );
			}
			if ( $sessions_exists === $sessions_table ) {
				$active_at      = max( 0, (int) ( $state['active_at'] ?? 0 ) ) ?: time();
				$session_cursor = max( 0, (int) ( $state['cursor'] ?? 0 ) );
				$session_max    = $state['max'] ?? null;
				if ( null === $session_max ) {
					$session_max = $wpdb->get_var( $wpdb->prepare(
						"SELECT MAX(session_id) FROM {$sessions_table} WHERE session_expiry >= %d",
						$active_at
					) );
					if ( self::database_has_error( $wpdb ) ) {
						return self::pause_preview_reference_scan( $option_name, $scan_key, $state, $references, $clean_needles );
					}
					$session_max       = absint( $session_max );
					$state['max']      = $session_max;
					$state['active_at'] = $active_at;
					$state['session_seen'] = max( $state['session_seen'], $session_max );
				}
				while ( $session_cursor < $session_max && ! empty( $unresolved ) ) {
					if ( microtime( true ) >= $deadline ) {
						return self::pause_preview_reference_scan( $option_name, $scan_key, $state, $references, $clean_needles );
					}
					$rows = $wpdb->get_results( $wpdb->prepare(
						"SELECT session_id AS row_id, session_value AS payload FROM {$sessions_table}
						 WHERE session_expiry >= %d AND session_id > %d AND session_id <= %d
						 ORDER BY session_id ASC LIMIT %d",
						$active_at,
						$session_cursor,
						$session_max,
						$page_size
					) );
					if ( self::database_has_error( $wpdb ) || ! is_array( $rows ) ) {
						return self::pause_preview_reference_scan( $option_name, $scan_key, $state, $references, $clean_needles );
					}
					if ( empty( $rows ) ) {
						break;
					}
					self::record_payload_references( $rows, $unresolved, $references );
					$session_cursor = absint( end( $rows )->row_id ?? 0 );
					$state['cursor'] = $session_cursor;
					if ( $session_cursor <= 0 ) {
						return self::pause_preview_reference_scan( $option_name, $scan_key, $state, $references, $clean_needles );
					}
				}
			}
			$source          = 'persistent';
			$state['source'] = $source;
			$state['cursor'] = 0;
			$state['max']    = null;
			$state['active_at'] = 0;
		}

		if ( 'persistent' === $source && ! empty( $unresolved ) ) {
			$persistent_key = $wpdb->esc_like( '_woocommerce_persistent_cart_' ) . '%';
			$persistent_cursor = max( 0, (int) ( $state['cursor'] ?? 0 ) );
			$persistent_max    = $state['max'] ?? null;
			if ( null === $persistent_max ) {
				$persistent_max = $wpdb->get_var( $wpdb->prepare(
					"SELECT MAX(umeta_id) FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
					$persistent_key
				) );
				if ( self::database_has_error( $wpdb ) ) {
					return self::pause_preview_reference_scan( $option_name, $scan_key, $state, $references, $clean_needles );
				}
				$persistent_max = absint( $persistent_max );
				$state['max']   = $persistent_max;
				$state['persistent_seen'] = max( $state['persistent_seen'], $persistent_max );
			}
			while ( $persistent_cursor < $persistent_max && ! empty( $unresolved ) ) {
				if ( microtime( true ) >= $deadline ) {
					return self::pause_preview_reference_scan( $option_name, $scan_key, $state, $references, $clean_needles );
				}
				$rows = $wpdb->get_results( $wpdb->prepare(
					"SELECT umeta_id AS row_id, meta_value AS payload FROM {$wpdb->usermeta}
					 WHERE meta_key LIKE %s AND umeta_id > %d AND umeta_id <= %d
					 ORDER BY umeta_id ASC LIMIT %d",
					$persistent_key,
					$persistent_cursor,
					$persistent_max,
					$page_size
				) );
				if ( self::database_has_error( $wpdb ) || ! is_array( $rows ) ) {
					return self::pause_preview_reference_scan( $option_name, $scan_key, $state, $references, $clean_needles );
				}
				if ( empty( $rows ) ) {
					break;
				}
				self::record_payload_references( $rows, $unresolved, $references );
				$persistent_cursor = absint( end( $rows )->row_id ?? 0 );
				$state['cursor']    = $persistent_cursor;
				if ( $persistent_cursor <= 0 ) {
					return self::pause_preview_reference_scan( $option_name, $scan_key, $state, $references, $clean_needles );
				}
			}
		}

		if ( ! empty( $unresolved ) ) {
			$delta_state = self::preview_reference_delta_state( $state );
			if ( is_wp_error( $delta_state ) ) {
				return self::pause_preview_reference_scan( $option_name, $scan_key, $state, $references, $clean_needles );
			}
			if ( is_array( $delta_state ) ) {
				return self::pause_preview_reference_scan( $option_name, $scan_key, $delta_state, $references, $clean_needles );
			}
			$mutable_reference = self::mutable_payload_has_reference( array_keys( $unresolved ) );
			if ( null === $mutable_reference ) {
				return self::pause_preview_reference_scan( $option_name, $scan_key, $state, $references, $clean_needles );
			}
			if ( $mutable_reference ) {
				$references += $unresolved;
				$unresolved = [];
			}
		}

		delete_option( $option_name );
		self::$preview_reference_scan_complete[ $scan_key ] = true;
		return $references;
	}

	/** Persist a bounded reference scan and conservatively retain its candidates. */
	private static function pause_preview_reference_scan( string $option_name, string $scan_key, array $state, array $references, array $needles ): array {
		$state['references'] = array_keys( $references );
		update_option( $option_name, $state, false );
		self::$preview_reference_scan_complete[ $scan_key ] = false;
		return array_fill_keys( $needles, true );
	}

	/** Return resumable state for references written after the scanned high-water marks. */
	private static function preview_reference_delta_state( array $state ): array|\WP_Error|null {
		global $wpdb;

		$order_table = $wpdb->prefix . 'woocommerce_order_itemmeta';
		$order_max   = $wpdb->get_var(
			"SELECT MAX(meta_id) FROM {$order_table} WHERE meta_key IN ('_oc_customisation','_oc_preview_url')"
		);
		if ( self::database_has_error( $wpdb ) ) {
			return new \WP_Error( 'preview_reference_scan_failed' );
		}
		$order_seen = max( 0, (int) ( $state['order_seen'] ?? 0 ) );
		if ( absint( $order_max ) > $order_seen ) {
			$state['source'] = 'order';
			$state['cursor'] = $order_seen;
			$state['max']    = absint( $order_max );
			$state['order_seen'] = absint( $order_max );
			return $state;
		}

		$sessions_table  = $wpdb->prefix . 'woocommerce_sessions';
		$sessions_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $sessions_table ) ) );
		if ( self::database_has_error( $wpdb ) ) {
			return new \WP_Error( 'preview_reference_scan_failed' );
		}
		if ( $sessions_exists === $sessions_table ) {
			$active_at   = time();
			$session_max = $wpdb->get_var( $wpdb->prepare(
				"SELECT MAX(session_id) FROM {$sessions_table} WHERE session_expiry >= %d",
				$active_at
			) );
			if ( self::database_has_error( $wpdb ) ) {
				return new \WP_Error( 'preview_reference_scan_failed' );
			}
			$session_seen = max( 0, (int) ( $state['session_seen'] ?? 0 ) );
			if ( absint( $session_max ) > $session_seen ) {
				$state['source']       = 'session';
				$state['cursor']       = $session_seen;
				$state['max']          = absint( $session_max );
				$state['active_at']    = $active_at;
				$state['session_seen'] = absint( $session_max );
				return $state;
			}
		}

		$persistent_key = $wpdb->esc_like( '_woocommerce_persistent_cart_' ) . '%';
		$persistent_max = $wpdb->get_var( $wpdb->prepare(
			"SELECT MAX(umeta_id) FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
			$persistent_key
		) );
		if ( self::database_has_error( $wpdb ) ) {
			return new \WP_Error( 'preview_reference_scan_failed' );
		}
		$persistent_seen = max( 0, (int) ( $state['persistent_seen'] ?? 0 ) );
		if ( absint( $persistent_max ) > $persistent_seen ) {
			$state['source']          = 'persistent';
			$state['cursor']          = $persistent_seen;
			$state['max']             = absint( $persistent_max );
			$state['active_at']       = 0;
			$state['persistent_seen'] = absint( $persistent_max );
			return $state;
		}

		return null;
	}

	/** Check mutable cart stores once more before deleting an unreferenced batch. */
	private static function mutable_payload_has_reference( array $needles ): ?bool {
		global $wpdb;

		if ( empty( $needles ) ) {
			return false;
		}
		$pattern = implode( '|', array_map( static fn ( string $needle ): string => preg_quote( $needle, '/' ), $needles ) );
		$sessions_table  = $wpdb->prefix . 'woocommerce_sessions';
		$sessions_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $sessions_table ) ) );
		if ( self::database_has_error( $wpdb ) ) {
			return null;
		}
		if ( $sessions_exists === $sessions_table ) {
			$found = $wpdb->get_var( $wpdb->prepare(
				"SELECT session_id FROM {$sessions_table} WHERE session_expiry >= %d AND session_value REGEXP %s LIMIT 1",
				time(),
				$pattern
			) );
			if ( self::database_has_error( $wpdb ) ) {
				return null;
			}
			if ( $found ) {
				return true;
			}
		}

		$found = $wpdb->get_var( $wpdb->prepare(
			"SELECT umeta_id FROM {$wpdb->usermeta} WHERE meta_key LIKE %s AND meta_value REGEXP %s LIMIT 1",
			$wpdb->esc_like( '_woocommerce_persistent_cart_' ) . '%',
			$pattern
		) );
		return self::database_has_error( $wpdb ) ? null : (bool) $found;
	}

	/**
	 * WordPress stubs model the default value rather than this mutable runtime property.
	 *
	 * @phpstan-impure Database queries mutate last_error outside PHP-visible assignments.
	 */
	private static function database_has_error( \wpdb $database ): bool {
		return '' !== (string) $database->last_error;
	}

	/** Record candidate fragments found in one bounded payload page. */
	private static function record_payload_references( array $rows, array &$unresolved, array &$references ): void {
		foreach ( $rows as $row ) {
			$payload = is_object( $row ) ? (string) ( $row->payload ?? '' ) : '';
			foreach ( array_keys( $unresolved ) as $needle ) {
				if ( str_contains( $payload, $needle ) ) {
					$references[ $needle ] = true;
					unset( $unresolved[ $needle ] );
				}
			}
		}
	}

	/** Build exact common PHP-serialization and JSON reference forms. */
	private static function artwork_reference_patterns( int $attachment_id ): array {
		global $wpdb;
		return array_map(
			static fn ( string $fragment ): string => '%' . $wpdb->esc_like( $fragment ) . '%',
			self::artwork_reference_fragments( $attachment_id )
		);
	}

	/** Build exact common PHP-serialization and JSON reference fragments. */
	private static function artwork_reference_fragments( int $attachment_id ): array {
		$id        = (string) $attachment_id;
		$fragments = [];
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
				$fragments[] = $value;
			}
		}

		return $fragments;
	}
}
