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
		?>
		<div class="wrap oc-page">
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
					<h2><?php esc_html_e( 'Uploaded Artwork', 'overcustomise' ); ?></h2>
					<span><?php echo esc_html( sprintf( __( 'Showing %1$s-%2$s of %3$s', 'overcustomise' ), number_format_i18n( $page_start ), number_format_i18n( $page_end ), number_format_i18n( $total ) ) ); ?></span>
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
							<?php $this->render_upload_card( $attachment ); ?>
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

	/** Render one upload card. */
	private function render_upload_card( WP_Post $attachment ): void {
		$id       = (int) $attachment->ID;
		$url      = OC_Upload_Handler::attachment_access_url( $id );
		$download_url = OC_Upload_Handler::attachment_access_url( $id, true );
		$path     = get_attached_file( $id );
		$mime     = get_post_mime_type( $id );
		$filename = $path ? basename( $path ) : basename( (string) get_attached_file( $id ) );
		$size     = $path && file_exists( $path ) ? size_format( filesize( $path ) ) : __( 'Missing file', 'overcustomise' );
		$preview_id  = absint( get_post_meta( $id, '_oc_print_derivative_attachment_id', true ) );
		$preview_url = OC_Upload_Handler::attachment_access_url( $preview_id ?: $id );
		$thumb       = '';
		if ( $preview_url && ( $preview_id || str_starts_with( (string) $mime, 'image/' ) ) ) {
			$thumb = sprintf( '<img src="%s" alt="" loading="lazy" />', esc_url( $preview_url ) );
		}
		?>
		<div class="oc-customer-upload-card">
			<div class="oc-customer-upload-preview">
				<?php echo $thumb ?: '<span>' . esc_html( strtoupper( pathinfo( $filename, PATHINFO_EXTENSION ) ) ) . '</span>'; ?>
				<span class="oc-customer-upload-type"><?php echo esc_html( strtoupper( pathinfo( $filename, PATHINFO_EXTENSION ) ) ); ?></span>
			</div>
			<div class="oc-customer-upload-body">
				<strong title="<?php echo esc_attr( $filename ); ?>"><?php echo esc_html( $filename ); ?></strong>
				<div class="oc-customer-upload-meta">
					<span><?php echo esc_html( $size ); ?></span>
					<span><?php echo esc_html( get_the_date( '', $id ) ); ?></span>
				</div>
				<p><?php echo esc_html( $mime ?: __( 'Unknown type', 'overcustomise' ) ); ?></p>
				<div class="oc-customer-upload-actions">
					<?php if ( $url ) : ?>
						<a class="oc-btn oc-btn-secondary oc-btn-sm" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View', 'overcustomise' ); ?></a>
						<a class="oc-btn oc-btn-secondary oc-btn-sm" href="<?php echo esc_url( $download_url ); ?>"><?php esc_html_e( 'Download', 'overcustomise' ); ?></a>
					<?php endif; ?>
					<form method="post" style="display:inline;margin:0;">
						<input type="hidden" name="oc_customer_upload_action" value="delete" />
						<input type="hidden" name="id" value="<?php echo esc_attr( $id ); ?>" />
						<?php wp_nonce_field( 'oc_customer_upload_delete_' . $id ); ?>
						<button type="submit" class="oc-btn oc-btn-danger oc-btn-sm" onclick="return confirm('<?php esc_attr_e( 'Delete this customer upload? This cannot be undone.', 'overcustomise' ); ?>');"><?php esc_html_e( 'Delete', 'overcustomise' ); ?></button>
					</form>
				</div>
			</div>
		</div>
		<?php
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
