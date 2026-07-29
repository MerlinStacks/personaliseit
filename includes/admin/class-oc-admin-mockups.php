<?php
/**
 * Mockup Library page — upload and manage product mockup images.
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

class OC_Admin_Mockups {

	/** Taxonomy slug used for grouping mockup attachments. */
	private const CATEGORY_SLUG = 'overcustomise-mockup';

	/** Register taxonomy and AJAX handlers. */
	public static function init(): void {
		add_action( 'init', [ self::class, 'register_taxonomy' ] );
		add_action( 'wp_ajax_oc_save_mockup_meta', [ self::class, 'ajax_save_meta' ] );
		add_action( 'wp_ajax_oc_delete_mockup', [ self::class, 'ajax_delete' ] );
	}

	/** Register a private taxonomy on attachments for grouping mockups. */
	public static function register_taxonomy(): void {
		if ( taxonomy_exists( 'oc_mockup' ) ) {
			return;
		}
		register_taxonomy( 'oc_mockup', 'attachment', [
			'label'             => __( 'Mockup Type', 'overcustomise' ),
			'public'            => false,
			'show_ui'           => false,
			'show_in_rest'      => false,
			'hierarchical'      => false,
			'rewrite'           => false,
		] );
		// Ensure default term exists.
		if ( ! term_exists( self::CATEGORY_SLUG, 'oc_mockup' ) ) {
			wp_insert_term( 'Mockup', 'oc_mockup', [ 'slug' => self::CATEGORY_SLUG ] );
		}
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'overcustomise' ) );
		}

		// Handle inline meta save.
		if ( isset( $_POST['oc_mockup_meta_nonce'] ) ) {
			$this->handle_meta_save();
			wp_safe_redirect( admin_url( 'admin.php?page=overcustomise-mockups' ) );
			exit;
		}

		wp_enqueue_media();
		wp_enqueue_script(
			'oc-mockup-library',
			OC_ASSETS_URL . 'admin/mockup-library.js',
			[ 'jquery', 'wp-util' ],
			OC_VERSION,
			true
		);
		wp_localize_script( 'oc-mockup-library', 'ocMockupData', [
			'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
			'nonce'       => wp_create_nonce( 'oc-mockup-nonce' ),
			'termSlug'    => self::CATEGORY_SLUG,
			'selectTitle' => __( 'Select or Upload Mockup Image', 'overcustomise' ),
			'selectBtn'   => __( 'Add to Mockup Library', 'overcustomise' ),
			'confirmDel'  => __( 'Remove this mockup from the library?', 'overcustomise' ),
		] );

		$mockups = $this->get_mockups();
		?>
		<div class="wrap oc-page">

			<div class="oc-page-header">
				<div class="oc-page-header-left">
					<h1 class="oc-page-title"><?php esc_html_e( 'Mockup Library', 'overcustomise' ); ?></h1>
					<p class="oc-page-subtitle"><?php esc_html_e( 'Upload and manage product mockup images. Assign one shared mockup to each design in Products.', 'overcustomise' ); ?></p>
				</div>
				<div class="oc-page-header-right">
					<button type="button" id="oc-upload-mockup" class="oc-btn oc-btn-primary">
						+ <?php esc_html_e( 'Upload / Add Mockup', 'overcustomise' ); ?>
					</button>
				</div>
			</div>

			<?php if ( empty( $mockups ) ) : ?>
				<div class="oc-card">
					<div class="oc-empty">
						<span class="oc-empty-icon">🖼️</span>
						<h3><?php esc_html_e( 'No mockup images yet', 'overcustomise' ); ?></h3>
						<p><?php esc_html_e( 'Upload your first mockup image to get started.', 'overcustomise' ); ?></p>
						<button type="button" id="oc-upload-mockup-alt" class="oc-btn oc-btn-primary" onclick="document.getElementById('oc-upload-mockup').click();">
							+ <?php esc_html_e( 'Upload / Add Mockup', 'overcustomise' ); ?>
						</button>
					</div>
				</div>
			<?php else : ?>
				<div id="oc-mockup-grid" class="oc-mockup-grid">
					<?php foreach ( $mockups as $mockup ) :
						$thumb    = wp_get_attachment_image_url( $mockup->ID, 'medium' );
						$dims     = wp_get_attachment_image_src( $mockup->ID, 'full' );
						$img_w    = $dims ? $dims[1] : 0;
						$img_h    = $dims ? $dims[2] : 0;
						$label    = get_post_meta( $mockup->ID, '_oc_mockup_label', true ) ?: $mockup->post_title;
						?>
						<div class="oc-mockup-card" data-id="<?php echo esc_attr( $mockup->ID ); ?>">
							<?php if ( $thumb ) : ?>
								<div class="oc-mockup-thumb">
									<img src="<?php echo esc_url( $thumb ); ?>"
									     alt="<?php echo esc_attr( $label ); ?>" />
								</div>
							<?php endif; ?>

							<div class="oc-mockup-body">
								<form method="post" style="margin:0;">
									<?php wp_nonce_field( 'oc_save_mockup_meta_' . $mockup->ID, 'oc_mockup_meta_nonce' ); ?>
									<input type="hidden" name="oc_mockup_id" value="<?php echo esc_attr( $mockup->ID ); ?>" />

									<input type="text" name="oc_mockup_label"
									       value="<?php echo esc_attr( $label ); ?>"
									       placeholder="<?php esc_attr_e( 'Mockup label', 'overcustomise' ); ?>"
									       class="oc-input"
									       style="width:100%;box-sizing:border-box;"
									       title="<?php esc_attr_e( 'Edit label and press Save', 'overcustomise' ); ?>" />

									<?php if ( $img_w && $img_h ) : ?>
										<div class="oc-mockup-dims"><?php echo esc_html( "{$img_w} × {$img_h}px" ); ?></div>
									<?php endif; ?>

									<div class="oc-mockup-actions">
										<button type="submit" class="oc-btn oc-btn-secondary oc-btn-sm">
											<?php esc_html_e( 'Save', 'overcustomise' ); ?>
										</button>
										<a href="<?php echo esc_url( get_edit_post_link( $mockup->ID ) ); ?>"
										   class="oc-btn oc-btn-secondary oc-btn-sm" target="_blank">
											<?php esc_html_e( 'Media', 'overcustomise' ); ?>
										</a>
										<button type="button"
										        class="oc-btn oc-btn-danger oc-btn-sm oc-delete-mockup"
										        data-id="<?php echo esc_attr( $mockup->ID ); ?>"
										        data-nonce="<?php echo esc_attr( wp_create_nonce( 'oc_delete_mockup_' . $mockup->ID ) ); ?>"
										        style="margin-left:auto;">
											<?php esc_html_e( 'Remove', 'overcustomise' ); ?>
										</button>
									</div>
								</form>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

		</div>

		<script>
		jQuery( function( $ ) {
			// Upload / add mockup.
			var frame;
			$( '#oc-upload-mockup' ).on( 'click', function () {
				if ( frame ) { frame.open(); return; }
				frame = wp.media( {
					title:    ocMockupData.selectTitle,
					button:   { text: ocMockupData.selectBtn },
					multiple: true,
					library:  { type: 'image' },
				} );
				frame.on( 'select', function () {
					var attachments = frame.state().get( 'selection' ).toJSON();
					if ( attachments.length ) {
						// Tag the attachments via AJAX then reload.
						$.post( ocMockupData.ajaxUrl, {
							action:      'oc_save_mockup_meta',
							_wpnonce:    ocMockupData.nonce,
							ids:         attachments.map( function( a ) { return a.id; } ),
						}, function () { window.location.reload(); } );
					}
				} );
				frame.open();
			} );

			// Delete mockup.
			$( document ).on( 'click', '.oc-delete-mockup', function () {
				if ( ! confirm( ocMockupData.confirmDel ) ) { return; }
				var btn = $( this );
				$.post( ocMockupData.ajaxUrl, {
					action:   'oc_delete_mockup',
					_wpnonce: btn.data( 'nonce' ),
					id:       btn.data( 'id' ),
				}, function () {
					btn.closest( '.oc-mockup-card' ).fadeOut( 300, function () { $( this ).remove(); } );
				} );
			} );
		} );
		</script>
		<?php
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/** Return all attachments tagged as mockups. */
	private function get_mockups(): array {
		return get_posts( [
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'tax_query'      => [ [
				'taxonomy' => 'oc_mockup',
				'field'    => 'slug',
				'terms'    => self::CATEGORY_SLUG,
			] ],
		] ) ?: [];
	}

	/** Save mockup label and tag attachment. */
	private function handle_meta_save(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$attachment_id = (int) ( $_POST['oc_mockup_id'] ?? 0 );

		if ( ! $attachment_id
		     || ! wp_verify_nonce( sanitize_key( $_POST['oc_mockup_meta_nonce'] ?? '' ), 'oc_save_mockup_meta_' . $attachment_id )
			 || ! self::can_manage_mockup_attachment( $attachment_id )
		) {
			return;
		}

		update_post_meta( $attachment_id, '_oc_mockup_label', sanitize_text_field( wp_unslash( $_POST['oc_mockup_label'] ?? '' ) ) );
		wp_set_object_terms( $attachment_id, self::CATEGORY_SLUG, 'oc_mockup' );
	}

	/** AJAX: Tag uploaded attachments as mockups. */
	public static function ajax_save_meta(): void {
		check_ajax_referer( 'oc-mockup-nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Forbidden', 403 );
		}
		$raw_ids = $_POST['ids'] ?? [];
		if ( ! is_array( $raw_ids ) || count( $raw_ids ) > 100 ) {
			wp_send_json_error( [ 'message' => __( 'Invalid attachment selection.', 'overcustomise' ) ], 400 );
		}
		$ids = array_values( array_unique( array_filter( array_map( 'absint', $raw_ids ) ) ) );
		foreach ( $ids as $id ) {
			if ( ! self::can_manage_mockup_attachment( $id ) ) {
				wp_send_json_error( [ 'message' => __( 'One or more selected attachments are invalid or inaccessible.', 'overcustomise' ) ], 403 );
			}
		}
		foreach ( $ids as $id ) {
			$result = wp_set_object_terms( $id, self::CATEGORY_SLUG, 'oc_mockup' );
			if ( is_wp_error( $result ) ) {
				wp_send_json_error( [ 'message' => __( 'Could not add the mockup to the library.', 'overcustomise' ) ], 500 );
			}
		}
		wp_send_json_success();
	}

	/** AJAX: Remove mockup tag (does not delete the media file). */
	public static function ajax_delete(): void {
		$id = (int) ( $_POST['id'] ?? 0 );
		check_ajax_referer( 'oc_delete_mockup_' . $id );
		if ( ! current_user_can( 'manage_woocommerce' ) || ! self::can_manage_mockup_attachment( $id ) ) {
			wp_send_json_error( 'Forbidden', 403 );
		}
		$result = wp_remove_object_terms( $id, self::CATEGORY_SLUG, 'oc_mockup' );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => __( 'Could not remove the mockup.', 'overcustomise' ) ], 500 );
		}
		wp_send_json_success();
	}

	/** Verify attachment type and object-level edit permission. */
	private static function can_manage_mockup_attachment( int $attachment_id ): bool {
		if ( 'attachment' !== get_post_type( $attachment_id ) || ! current_user_can( 'edit_post', $attachment_id ) ) {
			return false;
		}
		return str_starts_with( (string) get_post_mime_type( $attachment_id ), 'image/' );
	}

	/** Return all mockup attachments as array of [ id => label ] for select menus. */
	public static function get_mockups_for_select(): array {
		$items   = [];
		$mockups = get_posts( [
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => -1,
			'tax_query'      => [ [
				'taxonomy' => 'oc_mockup',
				'field'    => 'slug',
				'terms'    => self::CATEGORY_SLUG,
			] ],
		] );
		foreach ( $mockups as $m ) {
			$items[ $m->ID ] = get_post_meta( $m->ID, '_oc_mockup_label', true ) ?: $m->post_title;
		}
		return $items;
	}
}
