<?php
/**
 * Reusable laser material library. Surface geometry belongs to design layers.
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

class OC_Admin_Materials {

	/** Save one library entry with a stable identifier. */
	public static function save(): void {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage materials.', 'overcustomise' ) );
		}
		check_admin_referer( 'oc_save_material' );
		$posted = wp_unslash( $_POST['material'] ?? [] );
		$row = OC_Materials::sanitise( is_array( $posted ) ? $posted : [] );
		if ( '' === $row['name'] ) {
			wp_die( esc_html__( 'A material name is required.', 'overcustomise' ) );
		}
		$library = OC_Materials::all();
		if ( '' === $row['id'] ) {
			$row['id'] = 'material-' . wp_generate_uuid4();
		} elseif ( ! isset( $library[ $row['id'] ] ) ) {
			wp_die( esc_html__( 'Material not found.', 'overcustomise' ) );
		}
		$library[ $row['id'] ] = $row;
		update_option( 'oc_laser_materials', $library, false );
		OC_Cache::invalidate_group( OC_Cache::GROUP );
		wp_safe_redirect( admin_url( 'admin.php?page=overcustomise-materials&saved=1' ) );
		exit;
	}

	/** Render the library only; material selection and SVG uploads live in the editor. */
	public function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage materials.', 'overcustomise' ) );
		}
		$library = OC_Materials::all();
		?>
		<div class="wrap oc-page">
			<div class="oc-page-header"><div class="oc-page-header-left">
				<h1 class="oc-page-title"><?php esc_html_e( 'Laser Materials', 'overcustomise' ); ?></h1>
				<p class="oc-page-subtitle"><?php esc_html_e( 'Reusable surface and engraving colour pairs for Laser Material layers.', 'overcustomise' ); ?></p>
			</div></div>
			<p><?php esc_html_e( 'In the Design Editor, add a Laser Material layer, upload its SVG silhouette and choose its available materials. Existing leather, glass, wood and metal engraving finishes are unchanged. Surface colours are preview-only; production engraving artwork remains monochrome.', 'overcustomise' ); ?></p>
			<?php if ( isset( $_GET['saved'] ) ) : ?>
				<div class="notice notice-success"><p><?php esc_html_e( 'Material saved.', 'overcustomise' ); ?></p></div>
			<?php endif; ?>
			<?php foreach ( array_merge( array_values( $library ), [ [ 'id' => '', 'name' => '', 'surface' => '#171717', 'engraving' => '#ffffff', 'active' => true ] ] ) as $material ) : ?>
				<div class="oc-card" style="padding:16px;margin:12px 0;">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="oc_save_material">
						<?php wp_nonce_field( 'oc_save_material' ); ?>
						<input type="hidden" name="material[id]" value="<?php echo esc_attr( $material['id'] ); ?>">
						<label><?php esc_html_e( 'Material name', 'overcustomise' ); ?> <input name="material[name]" type="text" required maxlength="150" class="regular-text" value="<?php echo esc_attr( $material['name'] ); ?>"></label>
						<label><?php esc_html_e( 'Surface', 'overcustomise' ); ?> <input type="color" name="material[surface]" value="<?php echo esc_attr( $material['surface'] ); ?>"></label>
						<label><?php esc_html_e( 'Engraving', 'overcustomise' ); ?> <input type="color" name="material[engraving]" value="<?php echo esc_attr( $material['engraving'] ); ?>"></label>
						<label><input type="checkbox" name="material[active]" value="1" <?php checked( $material['active'] ); ?>> <?php esc_html_e( 'Active', 'overcustomise' ); ?></label>
						<button type="submit" class="button button-primary"><?php echo esc_html( $material['id'] ? __( 'Save material', 'overcustomise' ) : __( 'Add material', 'overcustomise' ) ); ?></button>
					</form>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}
}
