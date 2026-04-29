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
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';

		$wp_filesystem = null;
		if ( function_exists( 'WP_Filesystem' ) && WP_Filesystem() ) {
			global $wp_filesystem;
		}

		$uploads_base = wp_upload_dir()['basedir'] ?? '';
		$base_real    = $uploads_base ? realpath( $uploads_base ) : false;

		foreach ( $expired as $record ) {
			if ( ! empty( $record->file_path ) ) {
				$abs        = (string) $record->file_path;
				$abs_real   = realpath( $abs );

				// Only delete files that live under wp-content/uploads.
				$inside_uploads = $abs_real && $base_real && 0 === strpos( $abs_real, $base_real );

				if ( $inside_uploads ) {
					if ( $wp_filesystem && method_exists( $wp_filesystem, 'exists' ) ) {
						if ( $wp_filesystem->exists( $abs ) ) {
							$wp_filesystem->delete( $abs );
						}
					} elseif ( file_exists( $abs ) ) {
						// Fallback when WP_Filesystem isn't available.
						@unlink( $abs ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
					}
				} else {
					OC_Logger::warning( 'File cleanup skipped suspicious path: ' . $abs );
				}
			}

			$wpdb->update(
				"{$wpdb->prefix}oc_print_files",
				[ 'file_status' => 'expired', 'file_path' => null ],
				[ 'id' => $record->id ],
				[ '%s', '%s' ],
				[ '%d' ]
			);
		}

		OC_Logger::info( sprintf( 'File cleanup: expired %d print file records.', count( $expired ) ) );
		self::cleanup_preview_images();
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
			if ( ! $real || ! $base || 0 !== strpos( $real, $base ) ) {
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
}
