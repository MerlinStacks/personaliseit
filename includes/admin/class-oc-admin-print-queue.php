<?php
/**
 * Admin page for print generation queue management.
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

class OC_Admin_Print_Queue {

	/** Render the queue management page. */
	public function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'overcustomise' ) );
		}

		$this->handle_actions();
		$this->add_notice_from_query();

		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		if ( ! in_array( $status, [ 'pending', 'processing', 'done', 'failed' ], true ) ) {
			$status = '';
		}

		$paged    = max( 1, absint( $_GET['paged'] ?? 1 ) );
		$per_page = 50;
		$offset   = ( $paged - 1 ) * $per_page;
		$jobs     = OC_DB::get_queue_jobs( $status, $per_page, $offset );
		$total    = OC_DB::count_queue_jobs( $status );
		$counts   = OC_DB::get_queue_counts();
		$orphans  = OC_DB::get_orphaned_generating_print_files();
		$orphan_count = OC_DB::count_orphaned_generating_print_files();
		?>
		<div class="wrap oc-page oc-print-queue-page">
			<div class="oc-page-header">
				<div class="oc-page-header-left">
					<h1 class="oc-page-title"><?php esc_html_e( 'Print Generation Queue', 'overcustomise' ); ?></h1>
					<p class="oc-page-subtitle"><?php esc_html_e( 'Monitor UV, engraving, sublimation, and embroidery print file generation jobs.', 'overcustomise' ); ?></p>
				</div>
				<div class="oc-page-header-right">
					<?php $this->render_action_button( 'process_pending', 0, __( 'Process Pending Now', 'overcustomise' ), 'oc-btn oc-btn-primary' ); ?>
					<?php $this->render_action_button( 'reset_stale', 0, __( 'Reset Stuck Jobs', 'overcustomise' ), 'oc-btn oc-btn-secondary' ); ?>
				</div>
			</div>

			<?php settings_errors( 'oc_print_queue' ); ?>

			<div class="oc-queue-stats">
				<?php foreach ( [ 'all', 'pending', 'processing', 'failed', 'done' ] as $key ) : ?>
					<a class="oc-queue-stat<?php echo ( $status === $key || ( 'all' === $key && '' === $status ) ) ? ' is-active' : ''; ?>" href="<?php echo esc_url( $this->status_url( 'all' === $key ? '' : $key ) ); ?>">
						<span><?php echo esc_html( ucwords( $key ) ); ?></span>
						<strong><?php echo esc_html( number_format_i18n( (int) ( $counts[ $key ] ?? 0 ) ) ); ?></strong>
					</a>
				<?php endforeach; ?>
			</div>

			<?php if ( $orphan_count > 0 ) : ?>
				<div class="oc-card oc-queue-orphans">
					<div class="oc-card-header">
						<h2><?php esc_html_e( 'Generating Files Without Queue Jobs', 'overcustomise' ); ?></h2>
						<span><?php echo esc_html( sprintf( __( '%d found', 'overcustomise' ), $orphan_count ) ); ?></span>
					</div>
					<div class="oc-card-body oc-card-body-flush">
						<table class="widefat striped oc-queue-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'File', 'overcustomise' ); ?></th>
									<th><?php esc_html_e( 'Order', 'overcustomise' ); ?></th>
									<th><?php esc_html_e( 'Method', 'overcustomise' ); ?></th>
									<th><?php esc_html_e( 'Generated', 'overcustomise' ); ?></th>
									<th><?php esc_html_e( 'Actions', 'overcustomise' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $orphans as $file ) : ?>
									<?php $this->render_orphan_row( $file ); ?>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</div>
			<?php endif; ?>

			<div class="oc-card">
				<div class="oc-card-header">
					<h2><?php esc_html_e( 'Jobs', 'overcustomise' ); ?></h2>
					<span><?php echo esc_html( sprintf( __( '%d total', 'overcustomise' ), $total ) ); ?></span>
				</div>
				<div class="oc-card-body oc-card-body-flush">
					<?php if ( empty( $jobs ) ) : ?>
						<div class="oc-empty">
							<h3><?php esc_html_e( 'No queue jobs found', 'overcustomise' ); ?></h3>
							<p><?php esc_html_e( 'New order print files will appear here after checkout.', 'overcustomise' ); ?></p>
						</div>
					<?php else : ?>
						<table class="widefat striped oc-queue-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Job', 'overcustomise' ); ?></th>
									<th><?php esc_html_e( 'Order', 'overcustomise' ); ?></th>
									<th><?php esc_html_e( 'Method', 'overcustomise' ); ?></th>
									<th><?php esc_html_e( 'Status', 'overcustomise' ); ?></th>
									<th><?php esc_html_e( 'Attempts', 'overcustomise' ); ?></th>
									<th><?php esc_html_e( 'Created', 'overcustomise' ); ?></th>
									<th><?php esc_html_e( 'Last Activity', 'overcustomise' ); ?></th>
									<th><?php esc_html_e( 'Error', 'overcustomise' ); ?></th>
									<th><?php esc_html_e( 'Actions', 'overcustomise' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $jobs as $job ) : ?>
									<?php $this->render_job_row( $job ); ?>
								<?php endforeach; ?>
							</tbody>
						</table>
						<?php $this->render_pagination( $paged, (int) ceil( $total / $per_page ), $status ); ?>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/** Handle queue management actions. */
	private function handle_actions(): void {
		$action = isset( $_GET['oc_queue_action'] ) ? sanitize_key( wp_unslash( $_GET['oc_queue_action'] ) ) : '';
		$job_id = absint( $_GET['job_id'] ?? 0 );

		if ( '' === $action ) {
			return;
		}

		$nonce_action = 0 < $job_id ? 'oc_queue_' . $action . '_' . $job_id : 'oc_queue_' . $action;
		if ( ! wp_verify_nonce( (string) ( $_GET['_wpnonce'] ?? '' ), $nonce_action ) ) {
			wp_die( esc_html__( 'Security check failed.', 'overcustomise' ) );
		}

		switch ( $action ) {
			case 'process_pending':
				OC_Print_Queue::instance()->process();
				$this->redirect_with_notice( 'processed' );
				break;

			case 'reset_stale':
				$count = OC_Print_Queue::instance()->reset_stale_processing_jobs();
				$this->redirect_with_notice( 'reset_stale', $count );
				break;

			case 'process_one':
				if ( $job_id ) {
					OC_Print_Queue::instance()->process_one( $job_id );
					$this->redirect_with_notice( 'processed_one', $job_id );
				}
				break;

			case 'retry':
				if ( $job_id ) {
					OC_DB::update_queue_job( $job_id, [
						'status'        => 'pending',
						'error_message' => null,
						'processed_at'  => null,
					] );
					$this->redirect_with_notice( 'retry', $job_id );
				}
				break;

			case 'delete':
				if ( $job_id ) {
					global $wpdb;
					$wpdb->delete( $wpdb->prefix . 'oc_print_queue', [ 'id' => $job_id ], [ '%d' ] );
					$this->redirect_with_notice( 'deleted', $job_id );
				}
				break;

			case 'reset_file':
				if ( $job_id ) {
					OC_DB::update_print_file( $job_id, [ 'file_status' => 'pending' ] );
					$this->redirect_with_notice( 'reset_file', $job_id );
				}
				break;
		}
	}

	/** Add a settings notice after redirecting away from an action URL. */
	private function add_notice_from_query(): void {
		$notice = isset( $_GET['oc_queue_notice'] ) ? sanitize_key( wp_unslash( $_GET['oc_queue_notice'] ) ) : '';
		$value  = absint( $_GET['oc_queue_value'] ?? 0 );

		switch ( $notice ) {
			case 'processed':
				add_settings_error( 'oc_print_queue', 'processed', __( 'Pending queue batch processed.', 'overcustomise' ), 'success' );
				break;

			case 'reset_stale':
				add_settings_error( 'oc_print_queue', 'reset_stale', sprintf( __( 'Reset %d stuck job(s).', 'overcustomise' ), $value ), 'success' );
				break;

			case 'processed_one':
				add_settings_error( 'oc_print_queue', 'processed_one', sprintf( __( 'Processed job #%d.', 'overcustomise' ), $value ), 'success' );
				break;

			case 'retry':
				add_settings_error( 'oc_print_queue', 'retry', sprintf( __( 'Job #%d returned to pending.', 'overcustomise' ), $value ), 'success' );
				break;

			case 'deleted':
				add_settings_error( 'oc_print_queue', 'deleted', sprintf( __( 'Deleted job #%d.', 'overcustomise' ), $value ), 'success' );
				break;

			case 'reset_file':
				add_settings_error( 'oc_print_queue', 'reset_file', sprintf( __( 'Reset print file #%d to pending.', 'overcustomise' ), $value ), 'success' );
				break;
		}
	}

	/** Redirect back to the queue page after an action to avoid repeated refresh actions. */
	private function redirect_with_notice( string $notice, int $value = 0 ): void {
		$args = [
			'page'            => 'overcustomise-print-queue',
			'oc_queue_notice' => $notice,
		];

		if ( $value > 0 ) {
			$args['oc_queue_value'] = $value;
		}

		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		if ( in_array( $status, [ 'pending', 'processing', 'done', 'failed' ], true ) ) {
			$args['status'] = $status;
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/** Render an orphaned generating print file row. */
	private function render_orphan_row( object $file ): void {
		$order_url = $this->order_url( (int) $file->order_id );
		$regen_url = wp_nonce_url(
			add_query_arg( [ 'oc_regenerate' => (int) $file->id ], $order_url ?: admin_url( 'admin.php?page=wc-orders' ) ),
			'oc_regenerate_' . (int) $file->id
		);
		?>
		<tr>
			<td><strong>#<?php echo esc_html( (string) $file->id ); ?></strong><br><span class="description"><?php echo esc_html( sprintf( __( 'Item %1$d, area %2$d', 'overcustomise' ), (int) $file->order_item_id, (int) $file->print_area_id ) ); ?></span></td>
			<td><?php if ( $order_url ) : ?><a href="<?php echo esc_url( $order_url ); ?>">#<?php echo esc_html( (string) $file->order_id ); ?></a><?php else : ?>#<?php echo esc_html( (string) $file->order_id ); ?><?php endif; ?></td>
			<td><?php echo esc_html( ucwords( str_replace( '_', ' ', (string) $file->file_type ) ) ); ?></td>
			<td><?php echo esc_html( $this->format_date( $file->generated_at ) ); ?></td>
			<td class="oc-queue-actions">
				<a class="button button-small" href="<?php echo esc_url( $regen_url ); ?>"><?php esc_html_e( 'Regenerate', 'overcustomise' ); ?></a>
				<?php $this->render_action_button( 'reset_file', (int) $file->id, __( 'Mark Pending', 'overcustomise' ), 'button button-small' ); ?>
			</td>
		</tr>
		<?php
	}

	/** Render a queue job table row. */
	private function render_job_row( object $job ): void {
		$order_url = $this->order_url( (int) $job->order_id );
		?>
		<tr>
			<td><strong>#<?php echo esc_html( (string) $job->id ); ?></strong><br><span class="description"><?php echo esc_html( sprintf( __( 'Item %1$d, area %2$d', 'overcustomise' ), (int) $job->order_item_id, (int) $job->print_area_id ) ); ?></span></td>
			<td><?php if ( $order_url ) : ?><a href="<?php echo esc_url( $order_url ); ?>">#<?php echo esc_html( (string) $job->order_id ); ?></a><?php else : ?>#<?php echo esc_html( (string) $job->order_id ); ?><?php endif; ?></td>
			<td><?php echo esc_html( ucwords( str_replace( '_', ' ', (string) $job->print_method ) ) ); ?></td>
			<td><span class="oc-queue-status oc-queue-status-<?php echo esc_attr( (string) $job->status ); ?>"><?php echo esc_html( ucwords( (string) $job->status ) ); ?></span></td>
			<td><?php echo esc_html( (string) (int) $job->attempts ); ?></td>
			<td><?php echo esc_html( $this->format_date( $job->created_at ) ); ?></td>
			<td><?php echo esc_html( $this->format_date( $job->processed_at ) ); ?></td>
			<td class="oc-queue-error"><?php echo esc_html( wp_trim_words( (string) $job->error_message, 18 ) ); ?></td>
			<td class="oc-queue-actions">
				<?php if ( 'pending' === (string) $job->status ) : ?>
					<?php $this->render_action_button( 'process_one', (int) $job->id, __( 'Process', 'overcustomise' ), 'button button-small' ); ?>
				<?php endif; ?>
				<?php if ( in_array( (string) $job->status, [ 'failed', 'processing' ], true ) ) : ?>
					<?php $this->render_action_button( 'retry', (int) $job->id, __( 'Retry', 'overcustomise' ), 'button button-small' ); ?>
				<?php endif; ?>
				<?php $this->render_action_button( 'delete', (int) $job->id, __( 'Delete', 'overcustomise' ), 'button button-small', __( 'Delete this queue job?', 'overcustomise' ) ); ?>
			</td>
		</tr>
		<?php
	}

	/** Render a nonce-protected action link. */
	private function render_action_button( string $action, int $job_id, string $label, string $class, string $confirm = '' ): void {
		$args = [
			'page'            => 'overcustomise-print-queue',
			'oc_queue_action' => $action,
		];
		if ( $job_id > 0 ) {
			$args['job_id'] = $job_id;
		}

		$nonce_action = $job_id > 0 ? 'oc_queue_' . $action . '_' . $job_id : 'oc_queue_' . $action;
		$url          = wp_nonce_url( add_query_arg( $args, admin_url( 'admin.php' ) ), $nonce_action );
		$confirm_attr = '' !== $confirm ? ' onclick="return confirm(\'' . esc_js( $confirm ) . '\');"' : '';

		echo '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $url ) . '"' . $confirm_attr . '>' . esc_html( $label ) . '</a>';
	}

	/** Return an admin URL filtered by queue status. */
	private function status_url( string $status ): string {
		$args = [ 'page' => 'overcustomise-print-queue' ];
		if ( '' !== $status ) {
			$args['status'] = $status;
		}

		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/** Render pagination links. */
	private function render_pagination( int $paged, int $max_pages, string $status ): void {
		if ( $max_pages <= 1 ) {
			return;
		}

		$args = [ 'page' => 'overcustomise-print-queue' ];
		if ( '' !== $status ) {
			$args['status'] = $status;
		}

		$links = paginate_links( [
			'base'      => add_query_arg( array_merge( $args, [ 'paged' => '%#%' ] ), admin_url( 'admin.php' ) ),
			'format'    => '',
			'current'   => $paged,
			'total'     => $max_pages,
			'prev_text' => __( 'Previous', 'overcustomise' ),
			'next_text' => __( 'Next', 'overcustomise' ),
		] );

		if ( $links ) {
			echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post( $links ) . '</div></div>';
		}
	}

	/** Return edit URL for an order. */
	private function order_url( int $order_id ): string {
		if ( function_exists( 'wc_get_order' ) ) {
			$order = wc_get_order( $order_id );
			if ( $order instanceof \WC_Order ) {
				return $order->get_edit_order_url();
			}
		}

		return '';
	}

	/** Format MySQL GMT datetime for admin display. */
	private function format_date( ?string $date ): string {
		if ( empty( $date ) ) {
			return '-';
		}

		$timestamp = strtotime( $date . ' UTC' );
		if ( ! $timestamp ) {
			return $date;
		}

		return wp_date( 'Y-m-d H:i', $timestamp );
	}
}
