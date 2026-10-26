<?php
/**
 * Laser material and material-group manager.
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

class OC_Admin_Materials {

	/** Register material manager mutations. */
	public static function register_ajax(): void {
		add_action( 'wp_ajax_oc_material_save', [ self::class, 'ajax_material_save' ] );
		add_action( 'wp_ajax_oc_material_delete', [ self::class, 'ajax_material_delete' ] );
		add_action( 'wp_ajax_oc_material_group_save', [ self::class, 'ajax_group_save' ] );
		add_action( 'wp_ajax_oc_material_group_delete', [ self::class, 'ajax_group_delete' ] );
	}

	/** Render the material library and group manager. */
	public function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage materials.', 'overcustomise' ) );
		}
		$materials = array_values( OC_Materials::all() );
		$groups    = array_values( OC_Materials::groups() );
		?>
		<script>
		window.ocMaterialsData = <?php echo wp_json_encode( $materials ); ?>;
		window.ocMaterialGroups = <?php echo wp_json_encode( $groups ); ?>;
		window.ocMaterialNonce = <?php echo wp_json_encode( wp_create_nonce( 'oc_material_actions' ) ); ?>;
		window.ocAjaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
		</script>
		<div class="wrap oc-page oc-library-page">
			<div class="oc-page-header">
				<div class="oc-page-header-left">
					<h1 class="oc-page-title"><?php esc_html_e( 'Laser Material Manager', 'overcustomise' ); ?></h1>
					<p class="oc-page-subtitle"><?php esc_html_e( 'Manage reusable surface and engraving colours and group them for easy assignment.', 'overcustomise' ); ?></p>
				</div>
				<div class="oc-page-header-right">
					<button id="oc-add-material-btn" class="oc-btn oc-btn-primary" type="button">+ <?php esc_html_e( 'Add Material', 'overcustomise' ); ?></button>
					<button id="oc-create-material-group-btn" class="oc-btn oc-btn-secondary" type="button" style="display:none;">+ <?php esc_html_e( 'Create Group', 'overcustomise' ); ?></button>
				</div>
			</div>

			<div class="oc-tabs-bar">
				<button class="oc-tab oc-tab--active" data-target="oc-tab-materials" type="button"><?php esc_html_e( 'Materials', 'overcustomise' ); ?> <span class="oc-tab-count"><?php echo esc_html( (string) count( $materials ) ); ?></span></button>
				<button class="oc-tab" data-target="oc-tab-material-groups" type="button"><?php esc_html_e( 'Material Groups', 'overcustomise' ); ?> <span class="oc-tab-count"><?php echo esc_html( (string) count( $groups ) ); ?></span></button>
			</div>

			<div id="oc-tab-materials" class="oc-tab-panel">
				<div class="oc-card">
					<div class="oc-card-header"><h2><?php esc_html_e( 'Materials', 'overcustomise' ); ?></h2><span id="oc-materials-count"><?php echo esc_html( count( $materials ) . ' ' . ( 1 === count( $materials ) ? __( 'material', 'overcustomise' ) : __( 'materials', 'overcustomise' ) ) ); ?></span></div>
					<div id="oc-materials-empty" class="oc-empty" <?php echo $materials ? 'style="display:none;"' : ''; ?>><span class="oc-empty-icon">◩</span><h3><?php esc_html_e( 'No materials yet', 'overcustomise' ); ?></h3><p><?php esc_html_e( 'Add a material to start building your library.', 'overcustomise' ); ?></p></div>
					<div id="oc-material-grid" class="oc-colour-grid"></div>
				</div>
			</div>

			<div id="oc-tab-material-groups" class="oc-tab-panel" hidden>
				<div class="oc-card">
					<div class="oc-card-header"><h2><?php esc_html_e( 'Material Groups', 'overcustomise' ); ?></h2><span id="oc-material-groups-count"><?php echo esc_html( count( $groups ) . ' ' . ( 1 === count( $groups ) ? __( 'group', 'overcustomise' ) : __( 'groups', 'overcustomise' ) ) ); ?></span></div>
					<div id="oc-material-groups-empty" class="oc-empty" <?php echo $groups ? 'style="display:none;"' : ''; ?>><span class="oc-empty-icon">📂</span><h3><?php esc_html_e( 'No material groups yet', 'overcustomise' ); ?></h3><p><?php esc_html_e( 'Create groups to assign many materials to a design layer at once.', 'overcustomise' ); ?></p></div>
					<div id="oc-material-group-grid" class="oc-group-grid"></div>
				</div>
			</div>

			<div id="oc-material-modal" class="oc-modal-overlay" hidden aria-modal="true" role="dialog">
				<div class="oc-modal oc-colour-editor-modal">
					<div class="oc-modal-header"><h3 id="oc-material-modal-title"><?php esc_html_e( 'Add Material', 'overcustomise' ); ?></h3><button class="oc-modal-close" data-oc-close-material type="button">×</button></div>
					<div class="oc-modal-body">
						<div class="oc-upload-fields">
							<div class="oc-upload-field"><label for="oc_material_name"><?php esc_html_e( 'Material name', 'overcustomise' ); ?></label><input id="oc_material_name" class="oc-input" type="text" maxlength="150"></div>
							<div class="oc-upload-field-row">
								<div class="oc-upload-field"><label for="oc_material_surface"><?php esc_html_e( 'Surface colour', 'overcustomise' ); ?></label><input id="oc_material_surface" class="oc-material-colour-input" type="color" value="#171717"></div>
								<div class="oc-upload-field"><label for="oc_material_engraving"><?php esc_html_e( 'Engraving colour', 'overcustomise' ); ?></label><input id="oc_material_engraving" class="oc-material-colour-input" type="color" value="#ffffff"></div>
							</div>
							<div class="oc-upload-field"><label class="oc-material-active-label"><input id="oc_material_active" type="checkbox" checked> <?php esc_html_e( 'Active', 'overcustomise' ); ?></label></div>
						</div>
						<div id="oc-material-error" class="oc-upload-error" style="display:none;"></div>
					</div>
					<div class="oc-modal-footer"><button id="oc-material-delete-btn" class="oc-btn oc-btn-danger" type="button" style="display:none;margin-right:auto;"><?php esc_html_e( 'Delete', 'overcustomise' ); ?></button><button class="oc-btn oc-btn-secondary" data-oc-close-material type="button"><?php esc_html_e( 'Cancel', 'overcustomise' ); ?></button><button id="oc-material-save-btn" class="oc-btn oc-btn-primary" type="button"><?php esc_html_e( 'Save Material', 'overcustomise' ); ?></button></div>
				</div>
			</div>

			<div id="oc-material-group-modal" class="oc-modal-overlay" hidden aria-modal="true" role="dialog">
				<div class="oc-modal oc-group-editor-modal">
					<div class="oc-modal-header"><div style="flex:1;min-width:0;"><input id="oc-material-group-name" class="oc-input" type="text" maxlength="150" placeholder="<?php esc_attr_e( 'Group name', 'overcustomise' ); ?>"></div><button class="oc-modal-close" data-oc-close-material-group type="button">×</button></div>
					<div class="oc-modal-body" style="padding:0;"><div class="oc-group-picker-header"><span><?php esc_html_e( 'Select materials for this group', 'overcustomise' ); ?></span><span id="oc-material-group-selected-count" class="oc-group-selected-count">0 selected</span></div><div id="oc-material-group-picker" class="oc-group-font-picker"></div><div id="oc-material-group-error" class="oc-upload-error" style="display:none;margin:12px 20px;"></div></div>
					<div class="oc-modal-footer"><button id="oc-material-group-delete-btn" class="oc-btn oc-btn-danger" type="button" style="display:none;margin-right:auto;"><?php esc_html_e( 'Delete Group', 'overcustomise' ); ?></button><button class="oc-btn oc-btn-secondary" data-oc-close-material-group type="button"><?php esc_html_e( 'Cancel', 'overcustomise' ); ?></button><button id="oc-material-group-save-btn" class="oc-btn oc-btn-primary" type="button"><?php esc_html_e( 'Save Group', 'overcustomise' ); ?></button></div>
				</div>
			</div>
		</div>
		<?php
	}

	/** Save a material through the manager modal. */
	public static function ajax_material_save(): void {
		check_ajax_referer( 'oc_material_actions', 'nonce' );
		self::authorise();
		$library = OC_Materials::all();
		$posted  = [
			'id'        => wp_unslash( $_POST['id'] ?? '' ),
			'name'      => wp_unslash( $_POST['name'] ?? '' ),
			'surface'   => wp_unslash( $_POST['surface'] ?? '' ),
			'engraving' => wp_unslash( $_POST['engraving'] ?? '' ),
			'active'    => ! empty( $_POST['active'] ),
		];
		$row     = OC_Materials::sanitise( $posted );
		if ( '' === $row['name'] ) {
			wp_send_json_error( [ 'message' => __( 'A material name is required.', 'overcustomise' ) ], 400 );
		}
		if ( '' === $row['id'] ) {
			$row['id'] = 'material-' . wp_generate_uuid4();
		} elseif ( ! isset( $library[ $row['id'] ] ) ) {
			wp_send_json_error( [ 'message' => __( 'Material not found.', 'overcustomise' ) ], 404 );
		}
		$library[ $row['id'] ] = $row;
		update_option( 'oc_laser_materials', $library, false );
		self::changed();
		wp_send_json_success( $row );
	}

	/** Delete a material and remove it from every group. */
	public static function ajax_material_delete(): void {
		check_ajax_referer( 'oc_material_actions', 'nonce' );
		self::authorise();
		$id      = sanitize_key( wp_unslash( $_POST['id'] ?? '' ) );
		$library = OC_Materials::all();
		if ( ! isset( $library[ $id ] ) ) {
			wp_send_json_error( [ 'message' => __( 'Material not found.', 'overcustomise' ) ], 404 );
		}
		unset( $library[ $id ] );
		update_option( 'oc_laser_materials', $library, false );
		$groups = OC_Materials::groups();
		foreach ( $groups as &$group ) {
			$group['material_ids'] = array_values( array_diff( $group['material_ids'], [ $id ] ) );
		}
		unset( $group );
		update_option( 'oc_laser_material_groups', $groups, false );
		self::changed();
		wp_send_json_success( [ 'id' => $id ] );
	}

	/** Create or update a material group. */
	public static function ajax_group_save(): void {
		check_ajax_referer( 'oc_material_actions', 'nonce' );
		self::authorise();
		$groups = OC_Materials::groups();
		$ids    = isset( $_POST['material_ids'] ) && is_array( $_POST['material_ids'] ) ? wp_unslash( $_POST['material_ids'] ) : [];
		$row    = OC_Materials::sanitise_group(
			[
				'id'           => wp_unslash( $_POST['id'] ?? '' ),
				'name'         => wp_unslash( $_POST['name'] ?? '' ),
				'material_ids' => $ids,
			]
		);
		if ( '' === $row['name'] ) {
			wp_send_json_error( [ 'message' => __( 'A group name is required.', 'overcustomise' ) ], 400 );
		}
		if ( '' === $row['id'] ) {
			$row['id'] = 'group-' . wp_generate_uuid4();
		} elseif ( ! isset( $groups[ $row['id'] ] ) ) {
			wp_send_json_error( [ 'message' => __( 'Material group not found.', 'overcustomise' ) ], 404 );
		}
		$groups[ $row['id'] ] = $row;
		update_option( 'oc_laser_material_groups', $groups, false );
		self::changed();
		wp_send_json_success( $row );
	}

	/** Delete a material group. Existing layers retain their material snapshot. */
	public static function ajax_group_delete(): void {
		check_ajax_referer( 'oc_material_actions', 'nonce' );
		self::authorise();
		$id     = sanitize_key( wp_unslash( $_POST['id'] ?? '' ) );
		$groups = OC_Materials::groups();
		if ( ! isset( $groups[ $id ] ) ) {
			wp_send_json_error( [ 'message' => __( 'Material group not found.', 'overcustomise' ) ], 404 );
		}
		unset( $groups[ $id ] );
		update_option( 'oc_laser_material_groups', $groups, false );
		self::changed();
		wp_send_json_success( [ 'id' => $id ] );
	}

	/** Verify AJAX permissions. */
	private static function authorise(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'overcustomise' ) ], 403 );
		}
	}

	/** Invalidate library caches after a mutation. */
	private static function changed(): void {
		OC_Cache::invalidate_group( OC_Cache::GROUP );
	}
}
