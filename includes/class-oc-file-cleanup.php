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

		$days    = (int) OC_Admin_Settings::get( 'file_retention_days' ) ?: 90;
		$expired = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}oc_print_files
			 WHERE file_status IN ('files_ready','awaiting_dst_upload','brief_ready')
			   AND expires_at IS NOT NULL
			   AND expires_at < %s",
			current_time( 'mysql', true )
		) );

		if ( empty( $expired ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
		global $wp_filesystem;

		foreach ( $expired as $record ) {
			if ( $record->file_path ) {
				// file_path is stored as an absolute path.
				$abs = $record->file_path;
				if ( $wp_filesystem->exists( $abs ) ) {
					$wp_filesystem->delete( $abs );
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
	}
}
