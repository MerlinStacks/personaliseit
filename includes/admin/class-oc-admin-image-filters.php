<?php
/**
 * Image Filter Manager page.
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

class OC_Admin_Image_Filters {

	private const FILTERS = [
		'grayscale'  => 'Grayscale',
		'sepia'      => 'Sepia',
		'brightness' => 'Brightness',
		'contrast'   => 'Contrast',
		'saturation' => 'Saturation',
		'hue'        => 'Hue rotation',
	];

	public static function register_ajax(): void {}

	public function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'overcustomise' ) );
		}

		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		if ( 'delete' === $action ) {
			$this->handle_delete();
		} elseif ( 'toggle' === $action ) {
			$this->handle_toggle();
		}

		if ( isset( $_POST['oc_image_filter_nonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['oc_image_filter_nonce'] ) ), 'oc_image_filter_save' ) ) {
			$this->handle_save();
		}

		$filters = OC_DB::get_image_filters( false );
		?>
		<div class="wrap oc-page">
			<div class="oc-page-header">
				<div class="oc-page-header-left">
					<h1 class="oc-page-title"><?php esc_html_e( 'Image Filters', 'overcustomise' ); ?></h1>
					<p class="oc-page-subtitle"><?php esc_html_e( 'Create filters that admins can enable on individual image upload layers.', 'overcustomise' ); ?></p>
				</div>
			</div>

			<div class="oc-card" style="margin-bottom:18px;">
				<div class="oc-card-header"><h2><?php esc_html_e( 'Add Filter', 'overcustomise' ); ?></h2></div>
				<form method="post" class="oc-upload-fields" style="padding:18px;display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:12px;align-items:end;">
					<?php wp_nonce_field( 'oc_image_filter_save', 'oc_image_filter_nonce' ); ?>
					<div class="oc-upload-field">
						<label for="oc_filter_name"><?php esc_html_e( 'Filter name', 'overcustomise' ); ?></label>
						<input type="text" id="oc_filter_name" name="name" class="oc-input" required placeholder="<?php esc_attr_e( 'e.g. Classic Black and White', 'overcustomise' ); ?>" />
					</div>
					<div class="oc-upload-field">
						<label for="oc_filter_key"><?php esc_html_e( 'Effect', 'overcustomise' ); ?></label>
						<select id="oc_filter_key" name="filter_key" class="oc-input">
							<?php foreach ( self::FILTERS as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="oc-upload-field">
						<label for="oc_filter_value"><?php esc_html_e( 'Value', 'overcustomise' ); ?></label>
						<input type="number" id="oc_filter_value" name="value" class="oc-input" step="0.01" value="1" />
					</div>
					<button type="submit" class="oc-btn oc-btn-primary"><?php esc_html_e( 'Add Filter', 'overcustomise' ); ?></button>
				</form>
			</div>

			<div class="oc-card">
				<div class="oc-card-header">
					<h2><?php esc_html_e( 'Filters', 'overcustomise' ); ?></h2>
					<span style="font-size:12px;color:var(--oc-gray-400);"><?php echo esc_html( count( $filters ) ); ?> <?php esc_html_e( 'filters', 'overcustomise' ); ?></span>
				</div>
				<?php if ( empty( $filters ) ) : ?>
					<div class="oc-empty"><h3><?php esc_html_e( 'No filters yet', 'overcustomise' ); ?></h3><p><?php esc_html_e( 'Add a filter above, then enable it on image layers in the design editor.', 'overcustomise' ); ?></p></div>
				<?php else : ?>
					<table class="widefat striped">
						<thead><tr><th><?php esc_html_e( 'Name', 'overcustomise' ); ?></th><th><?php esc_html_e( 'Effect', 'overcustomise' ); ?></th><th><?php esc_html_e( 'Value', 'overcustomise' ); ?></th><th><?php esc_html_e( 'Status', 'overcustomise' ); ?></th><th><?php esc_html_e( 'Actions', 'overcustomise' ); ?></th></tr></thead>
						<tbody>
							<?php foreach ( $filters as $filter ) : ?>
								<tr>
									<td><?php echo esc_html( $filter->name ); ?></td>
									<td><?php echo esc_html( self::FILTERS[ $filter->filter_key ] ?? $filter->filter_key ); ?></td>
									<td><code><?php echo esc_html( (string) $filter->value ); ?></code></td>
									<td><?php echo ! empty( $filter->active ) ? esc_html__( 'Active', 'overcustomise' ) : esc_html__( 'Inactive', 'overcustomise' ); ?></td>
									<td>
										<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=overcustomise-image-filters&action=toggle&id=' . (int) $filter->id . '&state=' . ( ! empty( $filter->active ) ? '0' : '1' ) ), 'oc_image_filter_toggle_' . (int) $filter->id ) ); ?>"><?php echo ! empty( $filter->active ) ? esc_html__( 'Deactivate', 'overcustomise' ) : esc_html__( 'Activate', 'overcustomise' ); ?></a>
										<a class="button button-link-delete" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=overcustomise-image-filters&action=delete&id=' . (int) $filter->id ), 'oc_image_filter_delete_' . (int) $filter->id ) ); ?>" onclick="return confirm('<?php esc_attr_e( 'Delete this filter?', 'overcustomise' ); ?>');"><?php esc_html_e( 'Delete', 'overcustomise' ); ?></a>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	private function handle_save(): void {
		$name       = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		$filter_key = sanitize_key( wp_unslash( $_POST['filter_key'] ?? '' ) );
		$value      = (float) ( $_POST['value'] ?? 1 );

		if ( '' === $name || ! isset( self::FILTERS[ $filter_key ] ) ) {
			return;
		}

		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'oc_image_filters',
			[
				'name'       => $name,
				'filter_key' => $filter_key,
				'value'      => $value,
				'active'     => 1,
			],
			[ '%s', '%s', '%f', '%d' ]
		);
		OC_DB::clear_image_filter_cache();
	}

	private function handle_toggle(): void {
		$id    = absint( $_GET['id'] ?? 0 );
		$state = ! empty( $_GET['state'] ) ? 1 : 0;
		if ( ! $id || empty( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ), 'oc_image_filter_toggle_' . $id ) ) {
			return;
		}
		global $wpdb;
		$wpdb->update( $wpdb->prefix . 'oc_image_filters', [ 'active' => $state ], [ 'id' => $id ], [ '%d' ], [ '%d' ] );
		OC_DB::clear_image_filter_cache();
		wp_safe_redirect( admin_url( 'admin.php?page=overcustomise-image-filters' ) );
		exit;
	}

	private function handle_delete(): void {
		$id = absint( $_GET['id'] ?? 0 );
		if ( ! $id || empty( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ), 'oc_image_filter_delete_' . $id ) ) {
			return;
		}
		global $wpdb;
		$wpdb->delete( $wpdb->prefix . 'oc_image_filters', [ 'id' => $id ], [ '%d' ] );
		OC_DB::clear_image_filter_cache();
		wp_safe_redirect( admin_url( 'admin.php?page=overcustomise-image-filters' ) );
		exit;
	}
}
