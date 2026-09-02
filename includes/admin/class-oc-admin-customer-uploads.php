<?php
/**
 * Customer Uploads admin page and media library filtering.
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

class OC_Admin_Customer_Uploads {

	/** Register hooks that keep customer artwork out of the normal Media Library. */
	public static function register_hooks(): void {
		add_action( 'pre_get_posts', [ self::class, 'exclude_from_media_library' ] );
		add_filter( 'ajax_query_attachments_args', [ self::class, 'exclude_from_media_modal' ] );
		add_action( 'wp_ajax_oc_delete_customer_upload', [ self::class, 'ajax_delete_customer_upload' ] );
	}

	/** Delete one customer upload for the bounded bulk-delete workflow. */
	public static function ajax_delete_customer_upload(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'You do not have permission to delete customer uploads.', 'overcustomise' ) ], 403 );
		}

		check_ajax_referer( 'oc_customer_upload_bulk_delete' );

		$id = absint( $_POST['id'] ?? 0 );
		if ( ! self::is_customer_upload( $id ) || ! current_user_can( 'delete_post', $id ) ) {
			wp_send_json_error( [ 'message' => __( 'Upload not found.', 'overcustomise' ) ], 404 );
		}

		// wp_delete_attachment() applies the referenced-artwork deletion guard.
		if ( ! wp_delete_attachment( $id, true ) ) {
			wp_send_json_error( [ 'message' => __( 'This upload could not be safely deleted.', 'overcustomise' ) ], 409 );
		}

		wp_send_json_success();
	}

	/** Hide OC artwork attachments from wp-admin/upload.php. */
	public static function exclude_from_media_library( WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		global $pagenow;
		if ( 'upload.php' !== $pagenow ) {
			return;
		}

		self::add_not_artwork_meta_query_to_wp_query( $query );
	}

	/** Hide OC artwork attachments from the media modal and grid AJAX queries. */
	public static function exclude_from_media_modal( array $args ): array {
		$args['meta_query'] = self::add_not_artwork_meta_query_to_array( $args['meta_query'] ?? [] );
		return $args;
	}

	/** Render the dedicated manager page. */
	public function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'overcustomise' ) );
		}

		$this->handle_actions();

		$paged     = max( 1, absint( $_GET['paged'] ?? 1 ) );
		$per_page  = 24;
		$query     = new WP_Query( [
			'post_type'      => 'attachment',
			'post_status'    => [ 'private', 'inherit' ],
			'posts_per_page' => $per_page,
			'paged'          => $paged,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'meta_query'     => [
				'relation' => 'AND',
				[
					'key'     => '_oc_artwork',
					'value'   => '1',
					'compare' => '=',
				],
				[
					'key'     => '_oc_artwork_parent_id',
					'compare' => 'NOT EXISTS',
				],
			],
		] );
		$total     = (int) $query->found_posts;
		$uploads   = wp_upload_dir();
		$folder    = ! empty( $uploads['basedir'] ) ? trailingslashit( (string) $uploads['basedir'] ) . 'overcustomise/artwork' : '';
		$total_size = self::get_total_size();
		$page_start = $total > 0 ? ( ( $paged - 1 ) * $per_page ) + 1 : 0;
		$page_end   = min( $total, $paged * $per_page );
		$designs   = self::design_map_for_uploads( $query->posts );
		$filters   = [];
		foreach ( OC_DB::get_image_filters( false ) as $filter ) {
			$filters[ (int) $filter->id ] = $filter;
		}
		?>
		<div class="wrap oc-page">
			<?php $this->render_action_notice(); ?>
			<div class="oc-page-header oc-customer-upload-hero">
				<div class="oc-page-header-left">
					<h1 class="oc-page-title"><?php esc_html_e( 'Customer Uploads', 'overcustomise' ); ?></h1>
					<p class="oc-page-subtitle"><?php esc_html_e( 'A dedicated gallery for artwork uploaded through the product customiser.', 'overcustomise' ); ?></p>
				</div>
				<div class="oc-page-header-right">
					<span class="oc-customer-upload-pill"><?php esc_html_e( 'Hidden from Media Library', 'overcustomise' ); ?></span>
				</div>
			</div>

			<div class="oc-customer-upload-stats">
				<div class="oc-customer-upload-stat">
					<span><?php esc_html_e( 'Total Uploads', 'overcustomise' ); ?></span>
					<strong><?php echo esc_html( number_format_i18n( $total ) ); ?></strong>
				</div>
				<div class="oc-customer-upload-stat">
					<span><?php esc_html_e( 'Storage Used', 'overcustomise' ); ?></span>
					<strong><?php echo esc_html( size_format( $total_size ) ); ?></strong>
				</div>
				<div class="oc-customer-upload-stat oc-customer-upload-stat--wide">
					<span><?php esc_html_e( 'Storage Folder', 'overcustomise' ); ?></span>
					<?php if ( '' !== $folder ) : ?>
						<code><?php echo esc_html( $folder ); ?></code>
					<?php endif; ?>
				</div>
			</div>

			<div class="oc-card oc-customer-upload-gallery-card">
				<div class="oc-card-header oc-customer-upload-toolbar">
					<div>
						<h2><?php esc_html_e( 'Uploaded Artwork', 'overcustomise' ); ?></h2>
						<span><?php echo esc_html( sprintf( __( 'Showing %1$s-%2$s of %3$s', 'overcustomise' ), number_format_i18n( $page_start ), number_format_i18n( $page_end ), number_format_i18n( $total ) ) ); ?></span>
					</div>
					<?php if ( $query->have_posts() ) : ?>
						<form id="oc-customer-upload-bulk-form" method="post" class="oc-customer-upload-bulk-actions" data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
							<input type="hidden" name="oc_customer_upload_action" value="bulk_delete" />
							<?php wp_nonce_field( 'oc_customer_upload_bulk_delete' ); ?>
							<label>
								<input type="checkbox" data-oc-upload-select-all />
								<?php esc_html_e( 'Select all', 'overcustomise' ); ?>
							</label>
							<button type="submit" class="oc-btn oc-btn-danger oc-btn-sm" data-oc-upload-bulk-delete data-label="<?php esc_attr_e( 'Delete selected', 'overcustomise' ); ?>" data-progress="<?php esc_attr_e( 'Deleting %1$s of %2$s...', 'overcustomise' ); ?>" data-confirm-singular="<?php esc_attr_e( 'Delete 1 selected customer upload? This cannot be undone.', 'overcustomise' ); ?>" data-confirm-plural="<?php esc_attr_e( 'Delete %s selected customer uploads? This cannot be undone.', 'overcustomise' ); ?>" disabled><?php esc_html_e( 'Delete selected', 'overcustomise' ); ?></button>
						</form>
					<?php endif; ?>
				</div>
				<?php if ( ! $query->have_posts() ) : ?>
					<div class="oc-empty">
						<span class="oc-empty-icon">&bull;</span>
						<h3><?php esc_html_e( 'No customer uploads yet', 'overcustomise' ); ?></h3>
						<p><?php esc_html_e( 'Artwork uploaded through the product customiser will appear here.', 'overcustomise' ); ?></p>
					</div>
				<?php else : ?>
					<div class="oc-customer-upload-grid">
						<?php foreach ( $query->posts as $attachment ) : ?>
							<?php $this->render_upload_card( $attachment, $designs, $filters ); ?>
						<?php endforeach; ?>
					</div>
					<?php $this->render_pagination( $paged, (int) $query->max_num_pages ); ?>
				<?php endif; ?>
			</div>
		</div>
		<?php
		wp_reset_postdata();
	}

	/** Handle destructive admin actions. */
	private function handle_actions(): void {
		$action = isset( $_POST['oc_customer_upload_action'] ) ? sanitize_key( wp_unslash( $_POST['oc_customer_upload_action'] ) ) : '';
		if ( 'bulk_delete' === $action ) {
			$this->handle_bulk_delete();
			return;
		}

		$id     = absint( $_POST['id'] ?? 0 );

		if ( 'delete' !== $action || ! $id ) {
			return;
		}

		if ( ! wp_verify_nonce( (string) ( $_POST['_wpnonce'] ?? '' ), 'oc_customer_upload_delete_' . $id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'overcustomise' ) );
		}

		if ( ! self::is_customer_upload( $id ) || ! current_user_can( 'delete_post', $id ) ) {
			wp_die( esc_html__( 'Upload not found.', 'overcustomise' ) );
		}
		if ( OC_File_Cleanup::customer_artwork_is_referenced( $id ) ) {
			wp_die( esc_html__( 'This artwork is still referenced by an order or active cart and cannot be deleted.', 'overcustomise' ) );
		}

		if ( ! wp_delete_attachment( $id, true ) ) {
			wp_die( esc_html__( 'Could not delete this customer upload.', 'overcustomise' ) );
		}

		wp_safe_redirect( add_query_arg( 'deleted', '1', admin_url( 'admin.php?page=overcustomise-customer-uploads' ) ) );
		exit;
	}

	/** Delete selected customer uploads that are safe to remove. */
	private function handle_bulk_delete(): void {
		if ( ! wp_verify_nonce( (string) ( $_POST['_wpnonce'] ?? '' ), 'oc_customer_upload_bulk_delete' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'overcustomise' ) );
		}

		$raw_ids = isset( $_POST['upload_ids'] ) && is_array( $_POST['upload_ids'] ) ? wp_unslash( $_POST['upload_ids'] ) : [];
		$ids     = array_values( array_unique( array_filter( array_map( 'absint', $raw_ids ) ) ) );
		$deleted = 0;
		$skipped = 0;

		foreach ( $ids as $id ) {
			if (
				! self::is_customer_upload( $id ) ||
				! current_user_can( 'delete_post', $id ) ||
				! wp_delete_attachment( $id, true )
			) {
				++$skipped;
				continue;
			}

			++$deleted;
		}

		wp_safe_redirect(
			add_query_arg(
				[
					'bulk_deleted' => $deleted,
					'bulk_skipped' => $skipped,
				],
				admin_url( 'admin.php?page=overcustomise-customer-uploads' )
			)
		);
		exit;
	}

	/** Show the outcome of a bulk deletion. */
	private function render_action_notice(): void {
		if ( ! isset( $_GET['bulk_deleted'], $_GET['bulk_skipped'] ) ) {
			return;
		}

		$deleted = absint( $_GET['bulk_deleted'] );
		$skipped = absint( $_GET['bulk_skipped'] );
		$class   = $skipped > 0 ? 'notice notice-warning is-dismissible' : 'notice notice-success is-dismissible';
		?>
		<div class="<?php echo esc_attr( $class ); ?>">
			<p>
				<?php
					echo esc_html(
						sprintf(
							/* translators: 1: deleted upload count, 2: skipped upload count. */
							__( 'Uploads deleted: %1$s. Skipped because they could not be safely deleted: %2$s.', 'overcustomise' ),
							number_format_i18n( $deleted ),
							number_format_i18n( $skipped )
						)
					);
				?>
			</p>
		</div>
		<?php
	}

	/** Render one upload card. */
	private function render_upload_card( WP_Post $attachment, array $designs, array $filters ): void {
		$id                = (int) $attachment->ID;
		$url               = OC_Upload_Handler::attachment_access_url( $id );
		$download_url      = OC_Upload_Handler::attachment_access_url( $id, true );
		$path              = get_attached_file( $id );
		$mime              = get_post_mime_type( $id );
		$filename          = $path ? basename( $path ) : basename( (string) get_attached_file( $id ) );
		$size              = $path && file_exists( $path ) ? size_format( filesize( $path ) ) : __( 'Missing file', 'overcustomise' );
		$preview_id        = absint( get_post_meta( $id, '_oc_print_derivative_attachment_id', true ) );
		$preview_url       = OC_Upload_Handler::attachment_access_url( $preview_id > 0 ? $preview_id : $id );
		$thumb             = '';
		$context           = array_values( array_map( 'absint', (array) get_post_meta( $id, '_oc_artwork_context', true ) ) );
		$design_id         = $context[2] ?? 0;
		$design            = $designs[ $design_id ] ?? null;
		$is_generation     = 1 === (int) get_post_meta( $id, '_oc_ai_generation', true );
		$is_ai             = 1 === (int) get_post_meta( $id, '_oc_ai_filter', true );
		$filter_id         = $is_ai ? absint( get_post_meta( $id, '_oc_ai_filter_id', true ) ) : 0;
		$filter            = $filters[ $filter_id ] ?? null;
		$attempt           = $is_ai ? absint( get_post_meta( $id, '_oc_ai_filter_attempt', true ) ) : 0;
		$source_id         = $is_ai ? absint( get_post_meta( $id, '_oc_ai_filter_source_id', true ) ) : 0;
		$source_url        = $source_id ? OC_Upload_Handler::attachment_access_url( $source_id ) : '';
		$provider          = $is_generation ? sanitize_key( (string) get_post_meta( $id, '_oc_ai_filter_provider', true ) ) : '';
		$model             = $is_generation ? sanitize_text_field( (string) get_post_meta( $id, '_oc_ai_filter_model', true ) ) : '';
		$prompt_hash       = $is_generation ? sanitize_key( (string) get_post_meta( $id, '_oc_ai_prompt_hash', true ) ) : '';
		$instruction_hash  = $is_generation ? sanitize_key( (string) get_post_meta( $id, '_oc_ai_instruction_hash', true ) ) : '';
		$type_label        = $is_generation ? __( 'AI generation', 'overcustomise' ) : ( $is_ai ? __( 'AI filter result', 'overcustomise' ) : __( 'Original', 'overcustomise' ) );
		$mime_label        = $mime ? $mime : __( 'Unknown type', 'overcustomise' );
		$design_name       = $design && $design->name ? $design->name : __( 'Untitled design', 'overcustomise' );
		$model_label       = $model ? $model : __( 'Unknown model', 'overcustomise' );
		$provider_label    = $provider ? $provider : __( 'unknown provider', 'overcustomise' );
		$prompt_audit      = $prompt_hash ? $prompt_hash : 'missing';
		$instruction_audit = $instruction_hash ? $instruction_hash : 'missing';
		if ( $preview_url && ( $preview_id || str_starts_with( (string) $mime, 'image/' ) ) ) {
			$thumb = sprintf( '<img src="%s" alt="" loading="lazy" />', esc_url( $preview_url ) );
		}
		?>
		<div class="oc-customer-upload-card">
			<div class="oc-customer-upload-preview">
				<label class="oc-customer-upload-select" title="<?php esc_attr_e( 'Select upload', 'overcustomise' ); ?>">
					<input type="checkbox" name="upload_ids[]" value="<?php echo esc_attr( (string) $id ); ?>" form="oc-customer-upload-bulk-form" data-oc-upload-select />
					<span class="screen-reader-text"><?php echo esc_html( sprintf( __( 'Select %s', 'overcustomise' ), $filename ) ); ?></span>
				</label>
				<?php echo wp_kses_post( '' !== $thumb ? $thumb : '<span>' . esc_html( strtoupper( pathinfo( $filename, PATHINFO_EXTENSION ) ) ) . '</span>' ); ?>
				<span class="oc-customer-upload-type"><?php echo esc_html( $type_label ); ?></span>
			</div>
			<div class="oc-customer-upload-body">
				<strong title="<?php echo esc_attr( $filename ); ?>"><?php echo esc_html( $filename ); ?></strong>
				<div class="oc-customer-upload-meta">
					<span><?php echo esc_html( $size ); ?></span>
					<span><?php echo esc_html( get_the_date( '', $id ) ); ?></span>
				</div>
				<p><?php echo esc_html( $mime_label ); ?></p>
				<div class="oc-customer-upload-context">
					<span class="oc-customer-upload-context__label"><?php esc_html_e( 'Design', 'overcustomise' ); ?></span>
					<?php if ( $design ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=overcustomise-products&tab=designs&action=edit&id=' . $design_id ) ); ?>">
							<?php echo esc_html( $design_name . ' (#' . $design_id . ')' ); ?>
						</a>
					<?php elseif ( $design_id ) : ?>
						<span><?php echo esc_html( sprintf( __( 'Deleted design (#%d)', 'overcustomise' ), $design_id ) ); ?></span>
					<?php else : ?>
						<span><?php esc_html_e( 'Unknown', 'overcustomise' ); ?></span>
					<?php endif; ?>
					<?php if ( $is_generation ) : ?>
						<span class="oc-customer-upload-context__label"><?php esc_html_e( 'Text-to-image generation', 'overcustomise' ); ?></span>
						<span><?php echo esc_html( sprintf( /* translators: 1: AI model, 2: AI provider, 3: generation attempt. */ __( '%1$s via %2$s, attempt %3$d', 'overcustomise' ), $model_label, $provider_label, absint( get_post_meta( $id, '_oc_ai_filter_attempt', true ) ) ) ); ?></span>
						<span class="oc-customer-upload-context__label"><?php esc_html_e( 'Audit hashes', 'overcustomise' ); ?></span>
						<code><?php echo esc_html( sprintf( 'prompt:%1$s instruction:%2$s', $prompt_audit, $instruction_audit ) ); ?></code>
					<?php elseif ( $is_ai ) : ?>
						<span class="oc-customer-upload-context__label"><?php esc_html_e( 'Filter result', 'overcustomise' ); ?></span>
						<span>
							<?php
							echo esc_html(
								sprintf(
									/* translators: 1: filter name, 2: attempt number, 3: maximum attempts. */
									__( '%1$s, attempt %2$d of %3$d', 'overcustomise' ),
									$filter ? $filter->name : __( 'Deleted filter', 'overcustomise' ),
									$attempt,
									3
								)
							);
							?>
						</span>
						<span class="oc-customer-upload-context__label"><?php esc_html_e( 'Source', 'overcustomise' ); ?></span>
						<?php if ( $source_url ) : ?>
							<a href="<?php echo esc_url( $source_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( sprintf( __( 'Upload #%d', 'overcustomise' ), $source_id ) ); ?></a>
						<?php else : ?>
							<span><?php echo esc_html( sprintf( __( 'Upload #%d', 'overcustomise' ), $source_id ) ); ?></span>
						<?php endif; ?>
					<?php endif; ?>
				</div>
				<div class="oc-customer-upload-actions">
					<?php if ( $url ) : ?>
						<a class="oc-btn oc-btn-secondary oc-btn-sm" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View', 'overcustomise' ); ?></a>
						<a class="oc-btn oc-btn-secondary oc-btn-sm" href="<?php echo esc_url( $download_url ); ?>"><?php esc_html_e( 'Download', 'overcustomise' ); ?></a>
					<?php endif; ?>
					<form method="post" style="display:inline;margin:0;">
						<input type="hidden" name="oc_customer_upload_action" value="delete" />
						<input type="hidden" name="id" value="<?php echo esc_attr( (string) $id ); ?>" />
						<?php wp_nonce_field( 'oc_customer_upload_delete_' . $id ); ?>
						<button type="submit" class="oc-btn oc-btn-danger oc-btn-sm" onclick="return confirm('<?php esc_attr_e( 'Delete this customer upload? This cannot be undone.', 'overcustomise' ); ?>');"><?php esc_html_e( 'Delete', 'overcustomise' ); ?></button>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	/** Load the designs referenced by the attachments on the current page. */
	private static function design_map_for_uploads( array $attachments ): array {
		$ids = [];
		foreach ( $attachments as $attachment ) {
			if ( ! $attachment instanceof WP_Post ) {
				continue;
			}
			$context = array_values( array_map( 'absint', (array) get_post_meta( $attachment->ID, '_oc_artwork_context', true ) ) );
			if ( ! empty( $context[2] ) ) {
				$ids[] = $context[2];
			}
		}
		$ids = array_values( array_unique( $ids ) );
		if ( empty( $ids ) ) {
			return [];
		}

		global $wpdb;
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, name FROM {$wpdb->prefix}oc_designs WHERE id IN ($placeholders)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			...$ids
		) ) ?: [];
		$map = [];
		foreach ( $rows as $row ) {
			$map[ (int) $row->id ] = $row;
		}
		return $map;
	}

	/** Render pagination links. */
	private function render_pagination( int $paged, int $max_pages ): void {
		if ( $max_pages <= 1 ) {
			return;
		}

		$links = paginate_links( [
			'base'      => add_query_arg( 'paged', '%#%', admin_url( 'admin.php?page=overcustomise-customer-uploads' ) ),
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

	/** Return true when an attachment belongs to OC customer artwork. */
	private static function is_customer_upload( int $id ): bool {
		return 'attachment' === get_post_type( $id ) && '1' === (string) get_post_meta( $id, '_oc_artwork', true );
	}

	/** Add exclusion to a main WP_Query instance. */
	private static function add_not_artwork_meta_query_to_wp_query( WP_Query $query ): void {
		$meta_query = $query->get( 'meta_query' );
		$query->set( 'meta_query', self::add_not_artwork_meta_query_to_array( is_array( $meta_query ) ? $meta_query : [] ) );
	}

	/** Add exclusion to a raw query args array. */
	private static function add_not_artwork_meta_query_to_array( array $meta_query ): array {
		$exclude_query = [
			'key'     => '_oc_artwork',
			'compare' => 'NOT EXISTS',
		];

		if ( empty( $meta_query ) ) {
			return [ $exclude_query ];
		}

		return [
			'relation' => 'AND',
			$meta_query,
			$exclude_query,
		];
	}

	/** Calculate total bytes for known customer artwork. */
	private static function get_total_size(): int {
		$query = new WP_Query( [
			'post_type'      => 'attachment',
			'post_status'    => [ 'private', 'inherit' ],
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => [
				[
					'key'     => '_oc_artwork',
					'value'   => '1',
					'compare' => '=',
				],
			],
		] );

		$total = 0;
		foreach ( $query->posts as $id ) {
			$path = get_attached_file( (int) $id );
			if ( $path && file_exists( $path ) ) {
				$total += (int) filesize( $path );
			}
		}

		return $total;
	}
}
