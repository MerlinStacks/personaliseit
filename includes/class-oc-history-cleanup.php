<?php
/** Conservative retention of print history used by immutable reprints. @package OverCustomise */
defined( 'ABSPATH' ) || exit;

class OC_History_Cleanup {
	/** Keep live-order snapshots; remove only old terminal jobs belonging to deleted orders. */
	public static function run(): void {
		global $wpdb;
		$days   = max( 90, (int) apply_filters( 'oc_deleted_order_history_retention_days', 90 ) );
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		$cursor = max( 0, (int) get_option( 'oc_queue_cleanup_cursor', 0 ) );
		$jobs   = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, order_id, order_item_id FROM {$wpdb->prefix}oc_print_queue
			 WHERE id > %d AND status IN ('done','failed') AND processed_at < %s ORDER BY id ASC LIMIT 100",
				$cursor,
				$cutoff
			)
		);
		if ( ! is_array( $jobs ) || '' !== (string) $wpdb->last_error ) {
			return;
		}
		foreach ( $jobs as $job ) {
			if ( ! self::order_is_deleted( (int) $job->order_id ) ) {
				continue;
			}
			try {
				OC_Print_Generator::with_output_lock(
					(int) $job->order_id,
					(int) $job->order_item_id,
					static function () use ( $wpdb, $job, $cutoff ): array {
						if ( self::order_is_deleted( (int) $job->order_id ) ) {
							$wpdb->query(
								$wpdb->prepare(
									"DELETE FROM {$wpdb->prefix}oc_print_queue WHERE id = %d AND order_id = %d AND status IN ('done','failed') AND processed_at < %s",
									(int) $job->id,
									(int) $job->order_id,
									$cutoff
								)
							);
						}
						return [];
					}
				);
			} catch ( \Throwable $error ) {
				OC_Logger::warning( 'Print history retained: ' . $error->getMessage() );
			}
		}
		update_option( 'oc_queue_cleanup_cursor', count( $jobs ) < 100 ? 0 : (int) end( $jobs )->id, false );
		self::cleanup_markers( $cutoff );
	}

	/** WC CRUD supports HPOS and treats trashed orders as retained, not deleted. */
	private static function order_is_deleted( int $order_id ): bool {
		global $wpdb;
		if ( $order_id <= 0 ) {
			return false;
		}
		try {
			$order = wc_get_order( $order_id );
			return false === $order && '' === (string) $wpdb->last_error;
		} catch ( \Throwable $error ) {
			return false;
		}
	}

	/** Durable deduplication markers remain for the entire lifetime of a retained order. */
	private static function cleanup_markers( string $cutoff ): void {
		global $wpdb;
		$cursor = max( 0, (int) get_option( 'oc_queue_marker_cleanup_cursor', 0 ) );
		$rows   = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_id, option_name, option_value FROM {$wpdb->options}
			 WHERE option_id > %d AND (option_name LIKE %s OR option_name LIKE %s)
			 ORDER BY option_id ASC LIMIT 100",
				$cursor,
				$wpdb->esc_like( 'oc_print_generated_emitted_' ) . '%',
				$wpdb->esc_like( 'oc_print_failure_emitted_' ) . '%'
			)
		);
		if ( ! is_array( $rows ) || '' !== (string) $wpdb->last_error ) {
			return;
		}
		foreach ( $rows as $row ) {
			if ( preg_match( '/^oc_print_(?:generated|failure)_emitted_(\d+)$/D', (string) $row->option_name, $matches )
				&& preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', (string) $row->option_value )
				&& $row->option_value < $cutoff && self::order_is_deleted( (int) $matches[1] ) ) {
				delete_option( (string) $row->option_name );
			}
		}
		update_option( 'oc_queue_marker_cleanup_cursor', count( $rows ) < 100 ? 0 : (int) end( $rows )->option_id, false );
	}
}
