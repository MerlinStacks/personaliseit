<?php
/** Customer-facing chooser images, separate from production artwork. @package OverCustomise */
defined( 'ABSPATH' ) || exit;

class OC_Chooser_Icons {
	/** Resolve only the optional chooser image. */
	public static function url( string $kind, int $id ): string {
		$attachment_id = (int) get_option( 'oc_chooser_icon_' . $kind . '_' . $id, 0 );
		$url           = $attachment_id > 0 ? wp_get_attachment_image_url( $attachment_id, 'medium' ) : false;
		return $url ? $url : '';
	}

	/** Load the shared media-library upload/select control. */
	public static function enqueue(): void {
		wp_enqueue_media();
		wp_enqueue_script( 'oc-chooser-icons', OC_URL . 'assets/js/admin-chooser-icons.js', [ 'media-editor' ], OC_VERSION, true );
		wp_localize_script(
			'oc-chooser-icons',
			'ocChooserIcons',
			[
				'url'   => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'oc_chooser_icons' ),
			]
		);
	}

	/** Keep the ordinary backend artwork preview untouched. */
	public static function render( string $kind, int $id ): void {
		$url = self::url( $kind, $id );
		?>
		<div data-oc-chooser-icon data-kind="<?php echo esc_attr( $kind ); ?>" data-id="<?php echo esc_attr( (string) $id ); ?>">
			<img data-oc-icon-preview src="<?php echo esc_url( $url ); ?>" alt="" style="width:44px;height:44px;object-fit:contain;<?php echo $url ? '' : 'display:none;'; ?>" />
			<button type="button" class="button" data-oc-icon-select><?php esc_html_e( 'Upload / choose icon', 'overcustomise' ); ?></button>
			<button type="button" class="button" data-oc-icon-remove><?php esc_html_e( 'Remove icon', 'overcustomise' ); ?></button>
			<span data-oc-icon-status role="status"></span>
		</div>
		<?php
	}

	/** Persist validated image attachments for existing library entities only. */
	public static function ajax_save(): void {
		check_ajax_referer( 'oc_chooser_icons', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) || ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'overcustomise' ) ], 403 );
		}
		$kind          = sanitize_key( is_string( $_POST['kind'] ?? null ) ? wp_unslash( $_POST['kind'] ) : '' );
		$id            = absint( $_POST['id'] ?? 0 );
		$attachment_id = absint( $_POST['attachment_id'] ?? 0 );
		if ( ! in_array( $kind, [ 'clipart', 'design' ], true ) || ! $id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid icon target.', 'overcustomise' ) ], 400 );
		}
		global $wpdb;
		$table = 'clipart' === $kind ? 'oc_clipart' : 'oc_designs';
		if ( ! $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE id = %d', $wpdb->prefix . $table, $id ) ) ) {
			wp_send_json_error( [ 'message' => __( 'Artwork not found.', 'overcustomise' ) ], 404 );
		}
		if ( $attachment_id && ( ! wp_attachment_is_image( $attachment_id ) || ! wp_get_attachment_image_url( $attachment_id, 'medium' ) ) ) {
			wp_send_json_error( [ 'message' => __( 'Choose an image from the media library.', 'overcustomise' ) ], 400 );
		}
		$key = 'oc_chooser_icon_' . $kind . '_' . $id;
		update_option( $key, $attachment_id, false );
		if ( (int) get_option( $key, 0 ) !== $attachment_id ) {
			wp_send_json_error( [ 'message' => __( 'Could not save icon.', 'overcustomise' ) ], 500 );
		}
		OC_Cache::invalidate_group( OC_Cache::GROUP );
		wp_send_json_success( [ 'url' => self::url( $kind, $id ) ] );
	}
}
