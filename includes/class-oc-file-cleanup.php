<?php
/**
 * File cleanup — daily WP Cron job that expires and deletes old print files.
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

class OC_File_Cleanup {

	/** Called by WP Cron daily. */
	public static function run(): void {
		global $wpdb;

		$expired = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}oc_print_files
			 WHERE file_status IN ('files_ready','awaiting_dst_upload','brief_ready')
			   AND expires_at IS NOT NULL
			   AND expires_at < %s",
			current_time( 'mysql', true )
		) );

		if ( empty( $expired ) ) {
			self::cleanup_preview_images();
			self::cleanup_customer_artwork();
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';

		$wp_filesystem = null;
		if ( function_exists( 'WP_Filesystem' ) && WP_Filesystem() ) {
			global $wp_filesystem;
		}

		$uploads_base = wp_upload_dir()['basedir'] ?? '';
		$base_real     = $uploads_base ? realpath( $uploads_base ) : false;
		$handled_paths = [];

		foreach ( $expired as $record ) {
			$file_handled  = self::cleanup_record_path( $record, 'file_path', $base_real, $wp_filesystem, $handled_paths );
			$thumb_handled = self::cleanup_record_path( $record, 'thumbnail_path', $base_real, $wp_filesystem, $handled_paths );

			if ( $file_handled && $thumb_handled ) {
				$wpdb->update(
					"{$wpdb->prefix}oc_print_files",
					[ 'file_status' => 'expired', 'file_path' => null, 'thumbnail_path' => null ],
					[ 'id' => $record->id ],
					[ '%s', '%s', '%s' ],
					[ '%d' ]
				);
			} else {
				OC_Logger::warning( 'File cleanup could not safely expire print file #' . (int) $record->id );
			}
		}

		OC_Logger::info( sprintf( 'File cleanup: expired %d print file records.', count( $expired ) ) );
		self::cleanup_preview_images();
		self::cleanup_customer_artwork();
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
			 WHERE {$column} = %s AND id <> %d AND file_status <> 'expired'
			 AND NOT (file_status IN ('files_ready','awaiting_dst_upload','brief_ready')
			 AND expires_at IS NOT NULL AND expires_at < %s)",
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

	/**
	 * Delete old saved preview images from uploads/overcustomise/previews.
	 */
	private static function cleanup_preview_images(): void {
		$uploads = wp_upload_dir();
		$dir     = ( $uploads['basedir'] ?? '' ) . '/overcustomise/previews';
		if ( ! is_dir( $dir ) ) {
			return;
		}

		$retention_days = (int) OC_Admin_Settings::get( 'file_retention_days' );
		if ( $retention_days <= 0 ) {
			$retention_days = 90;
		}
		$cutoff = time() - ( $retention_days * DAY_IN_SECONDS );

		$files = glob( $dir . '/preview-*.*' );
		if ( ! is_array( $files ) || empty( $files ) ) {
			return;
		}

		$deleted = 0;
		foreach ( $files as $file ) {
			$real = realpath( $file );
			$base = realpath( $dir );
			$base = $base ? rtrim( $base, '/\\' ) . DIRECTORY_SEPARATOR : '';
			if ( ! $real || '' === $base || ! str_starts_with( $real, $base ) ) {
				continue;
			}
			$mtime = @filemtime( $real );
			if ( false === $mtime || $mtime > $cutoff ) {
				continue;
			}
			if ( @unlink( $real ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				$deleted++;
			}
		}

		if ( $deleted > 0 ) {
			OC_Logger::info( sprintf( 'File cleanup: removed %d stale preview images.', $deleted ) );
		}
	}

	/** Delete expired customer artwork that has never been persisted on an order. */
	private static function cleanup_customer_artwork(): void {
		$retention_days = max( 1, (int) OC_Admin_Settings::get( 'file_retention_days' ) ?: 90 );
		$attachments    = get_posts( [
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 100,
			'fields'         => 'ids',
			'date_query'     => [ [ 'before' => gmdate( 'Y-m-d H:i:s', time() - ( $retention_days * DAY_IN_SECONDS ) ) ] ],
			'meta_query'     => [ [ 'key' => '_oc_artwork', 'value' => '1' ] ],
		] );
		if ( empty( $attachments ) ) {
			return;
		}

		global $wpdb;
		foreach ( array_map( 'absint', $attachments ) as $attachment_id ) {
			$referenced = (bool) $wpdb->get_var( $wpdb->prepare(
				"SELECT meta_id FROM {$wpdb->prefix}woocommerce_order_itemmeta
				 WHERE meta_key = '_oc_customisation' AND (meta_value LIKE %s OR meta_value LIKE %s) LIMIT 1",
				'%' . $wpdb->esc_like( 's:12:"attachmentId";i:' . $attachment_id . ';' ) . '%',
				'%' . $wpdb->esc_like( '"attachmentId":' . $attachment_id ) . '%'
			) );
			if ( ! $referenced ) {
				wp_delete_attachment( $attachment_id, true );
			}
		}
	}
}
