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
			'post_status'    => 'inherit',
			'posts_per_page' => $per_page,
			'paged'          => $paged,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'meta_query'     => [
				[
					'key'     => '_oc_artwork',
					'value'   => '1',
					'compare' => '=',
				],
			],
		] );
		$total     = (int) $query->found_posts;
		$uploads   = wp_upload_dir();
		$folder    = ! empty( $uploads['basedir'] ) ? trailingslashit( (string) $uploads['basedir'] ) . 'overcustomise/artwork' : '';
		$total_size = self::get_total_size();
		?>
		<div class="wrap oc-page">
			<div class="oc-page-header">
				<div class="oc-page-header-left">
					<h1 class="oc-page-title"><?php esc_html_e( 'Customer Uploads', 'overcustomise' ); ?></h1>
					<p class="oc-page-subtitle"><?php esc_html_e( 'Manage customer artwork files separately from the WordPress Media Library.', 'overcustomise' ); ?></p>
				</div>
			</div>

			<div class="oc-card">
				<div class="oc-card-body oc-customer-upload-summary">
					<div><strong><?php echo esc_html( number_format_i18n( $total ) ); ?></strong><span><?php esc_html_e( ' uploads', 'overcustomise' ); ?></span></div>
					<div><strong><?php echo esc_html( size_format( $total_size ) ); ?></strong><span><?php esc_html_e( ' used', 'overcustomise' ); ?></span></div>
					<?php if ( '' !== $folder ) : ?>
						<div><strong><?php esc_html_e( 'Folder', 'overcustomise' ); ?></strong><code><?php echo esc_html( $folder ); ?></code></div>
					<?php endif; ?>
				</div>
			</div>

			<div class="oc-card">
				<div class="oc-card-header">
					<h2><?php esc_html_e( 'Uploaded Artwork', 'overcustomise' ); ?></h2>
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
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		$id     = absint( $_GET['id'] ?? 0 );

		if ( 'delete' !== $action || ! $id ) {
			return;
		}

		if ( ! wp_verify_nonce( (string) ( $_GET['_wpnonce'] ?? '' ), 'oc_customer_upload_delete_' . $id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'overcustomise' ) );
		}

		if ( ! self::is_customer_upload( $id ) ) {
			wp_die( esc_html__( 'Upload not found.', 'overcustomise' ) );
		}

		wp_delete_attachment( $id, true );

		wp_safe_redirect( add_query_arg( 'deleted', '1', admin_url( 'admin.php?page=overcustomise-customer-uploads' ) ) );
		exit;
	}

	/** Render one upload card. */
	private function render_upload_card( WP_Post $attachment ): void {
		$id       = (int) $attachment->ID;
		$url      = wp_get_attachment_url( $id );
		$path     = get_attached_file( $id );
		$mime     = get_post_mime_type( $id );
		$filename = $path ? basename( $path ) : basename( (string) get_attached_file( $id ) );
		$size     = $path && file_exists( $path ) ? size_format( filesize( $path ) ) : __( 'Missing file', 'overcustomise' );
		$thumb    = wp_get_attachment_image( $id, 'medium', false, [ 'loading' => 'lazy' ] );
		if ( ! $thumb && $url && str_starts_with( (string) $mime, 'image/' ) ) {
			$thumb = sprintf( '<img src="%s" alt="" loading="lazy" />', esc_url( $url ) );
		}
		$delete_url = wp_nonce_url(
			admin_url( 'admin.php?page=overcustomise-customer-uploads&action=delete&id=' . $id ),
			'oc_customer_upload_delete_' . $id
		);
		?>
		<div class="oc-customer-upload-card">
			<div class="oc-customer-upload-preview">
				<?php echo $thumb ?: '<span>' . esc_html( strtoupper( pathinfo( $filename, PATHINFO_EXTENSION ) ) ) . '</span>'; ?>
			</div>
			<div class="oc-customer-upload-body">
				<strong title="<?php echo esc_attr( $filename ); ?>"><?php echo esc_html( $filename ); ?></strong>
				<p><?php echo esc_html( $mime ?: __( 'Unknown type', 'overcustomise' ) ); ?> &middot; <?php echo esc_html( $size ); ?></p>
				<p><?php echo esc_html( get_the_date( '', $id ) ); ?></p>
				<div class="oc-customer-upload-actions">
					<?php if ( $url ) : ?>
						<a class="oc-btn oc-btn-secondary oc-btn-sm" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View', 'overcustomise' ); ?></a>
						<a class="oc-btn oc-btn-secondary oc-btn-sm" href="<?php echo esc_url( $url ); ?>" download><?php esc_html_e( 'Download', 'overcustomise' ); ?></a>
					<?php endif; ?>
					<a class="oc-btn oc-btn-danger oc-btn-sm" href="<?php echo esc_url( $delete_url ); ?>" onclick="return confirm('<?php esc_attr_e( 'Delete this customer upload? This cannot be undone.', 'overcustomise' ); ?>');"><?php esc_html_e( 'Delete', 'overcustomise' ); ?></a>
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
			'post_status'    => 'inherit',
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
