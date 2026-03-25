<?php
/**
 * Colour Manager page — manage brand colours and colour groups.
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

class OC_Admin_Colours {

	// ── AJAX registration ──────────────────────────────────────────────────────

	public static function register_ajax(): void {
		add_action( 'wp_ajax_oc_colour_save',         [ self::class, 'ajax_colour_save' ] );
		add_action( 'wp_ajax_oc_colour_group_create', [ self::class, 'ajax_group_create' ] );
		add_action( 'wp_ajax_oc_colour_group_update', [ self::class, 'ajax_group_update' ] );
		add_action( 'wp_ajax_oc_colour_group_delete', [ self::class, 'ajax_group_delete' ] );
	}

	// ── Page render ────────────────────────────────────────────────────────────

	public function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'overcustomise' ) );
		}

		if ( isset( $_GET['action'] ) && 'toggle' === $_GET['action'] ) { $this->handle_toggle(); }
		if ( isset( $_GET['action'] ) && 'delete' === $_GET['action'] ) { $this->handle_delete(); }

		$colours = OC_DB::get_colours( false );
		$groups  = OC_DB::get_colour_groups();

		$colours_js = array_map( function ( $c ) {
			return [
				'id'        => (int) $c->id,
				'name'      => $c->name,
				'hex'       => $c->hex,
				'active'    => (bool) $c->active,
				'toggleUrl' => wp_nonce_url(
					admin_url( 'admin.php?page=overcustomise-colours&action=toggle&id=' . $c->id . '&state=' . ( $c->active ? '0' : '1' ) ),
					'oc_colour_toggle_' . $c->id
				),
				'deleteUrl' => wp_nonce_url(
					admin_url( 'admin.php?page=overcustomise-colours&action=delete&id=' . $c->id ),
					'oc_colour_delete_' . $c->id
				),
			];
		}, $colours );

		$groups_js = array_map( function ( $g ) {
			return [
				'id'        => (int) $g->id,
				'name'      => $g->name,
				'colourIds' => array_map( 'intval', $g->colour_ids ),
			];
		}, $groups );
		?>
		<script>
		window.ocColoursData  = <?php echo wp_json_encode( $colours_js ); ?>;
		window.ocColourGroups = <?php echo wp_json_encode( $groups_js ); ?>;
		window.ocColourNonce  = <?php echo wp_json_encode( wp_create_nonce( 'oc_colour_actions' ) ); ?>;
		window.ocAjaxUrl      = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
		</script>

		<div class="wrap oc-page">

			<div class="oc-page-header">
				<div class="oc-page-header-left">
					<h1 class="oc-page-title"><?php esc_html_e( 'Colour Manager', 'overcustomise' ); ?></h1>
					<p class="oc-page-subtitle"><?php esc_html_e( 'Manage brand colours and colour groups for the product customiser.', 'overcustomise' ); ?></p>
				</div>
				<div class="oc-page-header-right">
					<button id="oc-add-colour-btn" class="oc-btn oc-btn-primary" type="button">
						+ <?php esc_html_e( 'Add Colour', 'overcustomise' ); ?>
					</button>
					<button id="oc-create-colour-group-btn" class="oc-btn oc-btn-primary" type="button" style="display:none;">
						+ <?php esc_html_e( 'Create Group', 'overcustomise' ); ?>
					</button>
				</div>
			</div>

			<!-- Tabs ──────────────────────────────────────────────────────────── -->
			<div class="oc-tabs-bar">
				<button class="oc-tab oc-tab--active" data-target="oc-tab-colours" type="button">
					<?php esc_html_e( 'Colours', 'overcustomise' ); ?>
					<span class="oc-tab-count"><?php echo count( $colours ); ?></span>
				</button>
				<button class="oc-tab" data-target="oc-tab-colour-groups" type="button">
					<?php esc_html_e( 'Colour Groups', 'overcustomise' ); ?>
					<span class="oc-tab-count"><?php echo count( $groups ); ?></span>
				</button>
			</div>

			<!-- ── Tab: Colours ───────────────────────────────────────────── -->
			<div id="oc-tab-colours" class="oc-tab-panel">
				<div class="oc-card">
					<div class="oc-card-header">
						<h2><?php esc_html_e( 'Colours', 'overcustomise' ); ?></h2>
						<span id="oc-colours-count" style="font-size:12px;color:var(--oc-gray-400);">
							<?php echo esc_html( count( $colours ) ); ?> <?php echo esc_html( 1 === count( $colours ) ? __( 'colour', 'overcustomise' ) : __( 'colours', 'overcustomise' ) ); ?>
						</span>
					</div>

					<?php if ( empty( $colours ) ) : ?>
						<div class="oc-empty" id="oc-colours-empty">
							<span class="oc-empty-icon">🎨</span>
							<h3><?php esc_html_e( 'No colours added yet', 'overcustomise' ); ?></h3>
							<p><?php esc_html_e( 'Click "Add Colour" to create your first brand colour.', 'overcustomise' ); ?></p>
						</div>
						<div class="oc-colour-grid" id="oc-colour-grid" style="display:none;"></div>
					<?php else : ?>
						<div class="oc-colour-grid" id="oc-colour-grid">
							<?php foreach ( $colours as $colour ) : ?>
								<div class="oc-colour-card<?php echo $colour->active ? '' : ' oc-colour-card--inactive'; ?>"
								     data-colour-id="<?php echo (int) $colour->id; ?>"
								     data-colour-hex="<?php echo esc_attr( $colour->hex ); ?>"
								     role="button" tabindex="0">

									<div class="oc-colour-swatch" style="background:<?php echo esc_attr( $colour->hex ); ?>;"></div>

									<div class="oc-colour-card-body">
										<div class="oc-colour-card-title-row">
											<p class="oc-colour-card-name" title="<?php echo esc_attr( $colour->name ); ?>">
												<?php echo esc_html( $colour->name ); ?>
											</p>
											<span class="oc-badge <?php echo $colour->active ? 'oc-badge-active' : 'oc-badge-inactive'; ?>">
												<?php echo $colour->active ? esc_html__( 'Active', 'overcustomise' ) : esc_html__( 'Inactive', 'overcustomise' ); ?>
											</span>
										</div>
										<p class="oc-colour-hex-label">
											<span class="oc-code"><?php echo esc_html( strtoupper( $colour->hex ) ); ?></span>
										</p>
										<div class="oc-colour-card-actions">
											<a href="<?php echo esc_url( wp_nonce_url(
												admin_url( 'admin.php?page=overcustomise-colours&action=toggle&id=' . (int) $colour->id . '&state=' . ( $colour->active ? '0' : '1' ) ),
												'oc_colour_toggle_' . $colour->id
											) ); ?>" class="oc-btn oc-btn-secondary oc-btn-sm">
												<?php echo $colour->active ? esc_html__( 'Deactivate', 'overcustomise' ) : esc_html__( 'Activate', 'overcustomise' ); ?>
											</a>
											<a href="<?php echo esc_url( wp_nonce_url(
												admin_url( 'admin.php?page=overcustomise-colours&action=delete&id=' . (int) $colour->id ),
												'oc_colour_delete_' . $colour->id
											) ); ?>"
											   onclick="return confirm('<?php esc_attr_e( 'Delete this colour?', 'overcustomise' ); ?>');"
											   class="oc-btn oc-btn-danger oc-btn-sm">
												<?php esc_html_e( 'Delete', 'overcustomise' ); ?>
											</a>
										</div>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
			</div>

			<!-- ── Tab: Colour Groups ─────────────────────────────────────── -->
			<div id="oc-tab-colour-groups" class="oc-tab-panel" hidden>
				<div class="oc-card">
					<div class="oc-card-header">
						<h2><?php esc_html_e( 'Colour Groups', 'overcustomise' ); ?></h2>
						<span id="oc-colour-groups-count" style="font-size:12px;color:var(--oc-gray-400);">
							<?php echo esc_html( count( $groups ) ); ?> <?php echo esc_html( 1 === count( $groups ) ? __( 'group', 'overcustomise' ) : __( 'groups', 'overcustomise' ) ); ?>
						</span>
					</div>

					<?php if ( empty( $groups ) ) : ?>
						<div class="oc-empty" id="oc-colour-groups-empty">
							<span class="oc-empty-icon">📂</span>
							<h3><?php esc_html_e( 'No colour groups yet', 'overcustomise' ); ?></h3>
							<p><?php esc_html_e( 'Create a group to bundle colours for easy assignment.', 'overcustomise' ); ?></p>
						</div>
						<div class="oc-group-grid" id="oc-colour-group-grid" style="display:none;"></div>
					<?php else : ?>
						<div class="oc-group-grid" id="oc-colour-group-grid">
							<?php foreach ( $groups as $group ) :
								$member_count = count( $group->colour_ids );
								?>
								<div class="oc-group-card oc-colour-group-card"
								     data-group-id="<?php echo (int) $group->id; ?>"
								     data-group-name="<?php echo esc_attr( $group->name ); ?>"
								     role="button" tabindex="0">
									<div class="oc-group-card-body">
										<p class="oc-group-card-name"><?php echo esc_html( $group->name ); ?></p>
										<p class="oc-group-card-count">
											<?php echo esc_html( $member_count . ' ' . ( 1 === $member_count ? __( 'colour', 'overcustomise' ) : __( 'colours', 'overcustomise' ) ) ); ?>
										</p>
										<div class="oc-colour-group-dots">
											<?php foreach ( array_slice( $group->colour_ids, 0, 10 ) as $cid ) :
												$cdata = null;
												foreach ( $colours as $c ) {
													if ( (int) $c->id === (int) $cid ) { $cdata = $c; break; }
												}
												if ( ! $cdata ) continue;
												?>
												<span class="oc-colour-dot" style="background:<?php echo esc_attr( $cdata->hex ); ?>;" title="<?php echo esc_attr( $cdata->name ); ?>"></span>
											<?php endforeach; ?>
											<?php if ( $member_count > 10 ) : ?>
												<span class="oc-group-card-more">+<?php echo esc_html( $member_count - 10 ); ?></span>
											<?php endif; ?>
											<?php if ( 0 === $member_count ) : ?>
												<span style="color:var(--oc-gray-400);font-size:12px;"><?php esc_html_e( 'Empty group', 'overcustomise' ); ?></span>
											<?php endif; ?>
										</div>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
			</div>

			<!-- Colour editor modal ──────────────────────────────────────────── -->
			<div id="oc-colour-modal" class="oc-modal-overlay" hidden aria-modal="true" role="dialog">
				<div class="oc-modal oc-colour-editor-modal">

					<div class="oc-modal-header">
						<h3 id="oc-colour-modal-title"><?php esc_html_e( 'Add Colour', 'overcustomise' ); ?></h3>
						<button id="oc-colour-modal-close" class="oc-modal-close" type="button">×</button>
					</div>

					<div class="oc-modal-body">
						<div class="oc-colour-picker-area">
							<div class="oc-colour-swatch-large" id="oc-colour-swatch-large" style="background:#4f46e5;">
								<input type="color" id="oc-colour-picker" value="#4f46e5" tabindex="-1" />
								<span class="oc-colour-swatch-hint"><?php esc_html_e( 'Click to pick', 'overcustomise' ); ?></span>
							</div>
						</div>
						<div class="oc-upload-fields">
							<div class="oc-upload-field">
								<label for="oc_colour_name"><?php esc_html_e( 'Colour name', 'overcustomise' ); ?></label>
								<input type="text" id="oc_colour_name" class="oc-input" required
								       placeholder="<?php esc_attr_e( 'e.g. Royal Blue', 'overcustomise' ); ?>" />
							</div>
							<div class="oc-upload-field">
								<label for="oc_colour_hex"><?php esc_html_e( 'Hex value', 'overcustomise' ); ?></label>
								<input type="text" id="oc_colour_hex" class="oc-input oc-colour-hex-input"
								       maxlength="7" placeholder="#000000" />
							</div>
						</div>
						<div id="oc-colour-error" class="oc-upload-error" style="display:none;"></div>
					</div>

					<div class="oc-modal-footer">
						<button id="oc-colour-delete-btn" class="oc-btn oc-btn-danger" type="button" style="display:none;margin-right:auto;">
							<?php esc_html_e( 'Delete', 'overcustomise' ); ?>
						</button>
						<button id="oc-colour-cancel-btn" class="oc-btn oc-btn-secondary" type="button">
							<?php esc_html_e( 'Cancel', 'overcustomise' ); ?>
						</button>
						<button id="oc-colour-save-btn" class="oc-btn oc-btn-primary" type="button">
							<?php esc_html_e( 'Save Colour', 'overcustomise' ); ?>
						</button>
					</div>

				</div>
			</div>

			<!-- Colour group editor modal ────────────────────────────────────── -->
			<div id="oc-colour-group-modal" class="oc-modal-overlay" hidden aria-modal="true" role="dialog">
				<div class="oc-modal oc-group-editor-modal">

					<div class="oc-modal-header">
						<div style="flex:1;min-width:0;">
							<input type="text" id="oc-colour-group-name-input" class="oc-input"
							       placeholder="<?php esc_attr_e( 'Group name', 'overcustomise' ); ?>" />
						</div>
						<button id="oc-colour-group-modal-close" class="oc-modal-close" type="button">×</button>
					</div>

					<div class="oc-modal-body" style="padding:0;">
						<div class="oc-group-picker-header">
							<span><?php esc_html_e( 'Select colours for this group', 'overcustomise' ); ?></span>
							<span id="oc-colour-group-selected-count" class="oc-group-selected-count">0 selected</span>
						</div>
						<div id="oc-colour-group-picker" class="oc-group-font-picker oc-colour-group-picker"></div>
					</div>

					<div class="oc-modal-footer">
						<button id="oc-colour-group-delete-btn" class="oc-btn oc-btn-danger" type="button" style="margin-right:auto;display:none;">
							<?php esc_html_e( 'Delete Group', 'overcustomise' ); ?>
						</button>
						<button id="oc-colour-group-cancel-btn" class="oc-btn oc-btn-secondary" type="button">
							<?php esc_html_e( 'Cancel', 'overcustomise' ); ?>
						</button>
						<button id="oc-colour-group-save-btn" class="oc-btn oc-btn-primary" type="button">
							<?php esc_html_e( 'Save Group', 'overcustomise' ); ?>
						</button>
					</div>

				</div>
			</div>

		</div>
		<?php
	}

	// ── AJAX: save colour (create or update) ───────────────────────────────────

	public static function ajax_colour_save(): void {
		check_ajax_referer( 'oc_colour_actions', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'overcustomise' ) ] );
		}

		$id   = (int) ( $_POST['id'] ?? 0 );
		$name = sanitize_text_field( $_POST['name'] ?? '' );
		$hex  = sanitize_hex_color( $_POST['hex'] ?? '' );

		if ( ! $name || ! $hex ) {
			wp_send_json_error( [ 'message' => __( 'Name and hex colour are required.', 'overcustomise' ) ] );
		}

		global $wpdb;

		if ( $id ) {
			// Update existing.
			$wpdb->update(
				"{$wpdb->prefix}oc_colours",
				[ 'name' => $name, 'hex' => $hex ],
				[ 'id'   => $id ],
				[ '%s', '%s' ], [ '%d' ]
			);
		} else {
			// Create new.
			$wpdb->insert(
				"{$wpdb->prefix}oc_colours",
				[ 'name' => $name, 'hex' => $hex, 'active' => 1 ],
				[ '%s', '%s', '%d' ]
			);
			$id = (int) $wpdb->insert_id;
		}

		$active = (bool) $wpdb->get_var( $wpdb->prepare(
			"SELECT active FROM {$wpdb->prefix}oc_colours WHERE id = %d", $id
		) );

		wp_send_json_success( [
			'id'        => $id,
			'name'      => $name,
			'hex'       => $hex,
			'active'    => $active,
			'toggleUrl' => wp_nonce_url(
				admin_url( 'admin.php?page=overcustomise-colours&action=toggle&id=' . $id . '&state=' . ( $active ? '0' : '1' ) ),
				'oc_colour_toggle_' . $id
			),
			'deleteUrl' => wp_nonce_url(
				admin_url( 'admin.php?page=overcustomise-colours&action=delete&id=' . $id ),
				'oc_colour_delete_' . $id
			),
		] );
	}

	// ── AJAX: colour group create ──────────────────────────────────────────────

	public static function ajax_group_create(): void {
		check_ajax_referer( 'oc_colour_actions', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'overcustomise' ) ] );
		}
		$name = sanitize_text_field( $_POST['name'] ?? '' );
		if ( ! $name ) wp_send_json_error( [ 'message' => __( 'Name is required.', 'overcustomise' ) ] );

		global $wpdb;
		$wpdb->insert( "{$wpdb->prefix}oc_colour_groups", [ 'name' => $name ], [ '%s' ] );
		wp_send_json_success( [ 'id' => (int) $wpdb->insert_id, 'name' => $name, 'colourIds' => [] ] );
	}

	// ── AJAX: colour group update ──────────────────────────────────────────────

	public static function ajax_group_update(): void {
		check_ajax_referer( 'oc_colour_actions', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'overcustomise' ) ] );
		}

		$id         = (int) ( $_POST['id'] ?? 0 );
		$name       = sanitize_text_field( $_POST['name'] ?? '' );
		$colour_ids = array_filter( array_map( 'intval', (array) ( $_POST['colour_ids'] ?? [] ) ) );

		if ( ! $id || ! $name ) wp_send_json_error( [ 'message' => __( 'Invalid request.', 'overcustomise' ) ] );

		global $wpdb;
		$wpdb->update( "{$wpdb->prefix}oc_colour_groups", [ 'name' => $name ], [ 'id' => $id ], [ '%s' ], [ '%d' ] );
		$wpdb->delete( "{$wpdb->prefix}oc_colour_group_items", [ 'group_id' => $id ], [ '%d' ] );
		foreach ( array_values( $colour_ids ) as $order => $colour_id ) {
			$wpdb->insert(
				"{$wpdb->prefix}oc_colour_group_items",
				[ 'group_id' => $id, 'colour_id' => $colour_id, 'sort_order' => $order ],
				[ '%d', '%d', '%d' ]
			);
		}
		wp_send_json_success( [ 'id' => $id, 'name' => $name, 'colourIds' => array_values( $colour_ids ) ] );
	}

	// ── AJAX: colour group delete ──────────────────────────────────────────────

	public static function ajax_group_delete(): void {
		check_ajax_referer( 'oc_colour_actions', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'overcustomise' ) ] );
		}
		$id = (int) ( $_POST['id'] ?? 0 );
		if ( ! $id ) wp_send_json_error( [ 'message' => __( 'Invalid request.', 'overcustomise' ) ] );

		global $wpdb;
		$wpdb->delete( "{$wpdb->prefix}oc_colour_group_items", [ 'group_id' => $id ], [ '%d' ] );
		$wpdb->delete( "{$wpdb->prefix}oc_colour_groups",      [ 'id'       => $id ], [ '%d' ] );
		wp_send_json_success();
	}

	// ── GET action handlers ────────────────────────────────────────────────────

	private function handle_toggle(): void {
		$id    = isset( $_GET['id'] )    ? (int) $_GET['id']    : 0;
		$state = isset( $_GET['state'] ) ? (int) $_GET['state'] : 0;

		if ( ! $id || ! isset( $_GET['_wpnonce'] )
		     || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'oc_colour_toggle_' . $id )
		) {
			wp_die( esc_html__( 'Security check failed.', 'overcustomise' ) );
		}

		global $wpdb;
		$wpdb->update( "{$wpdb->prefix}oc_colours", [ 'active' => (int) (bool) $state ], [ 'id' => $id ], [ '%d' ], [ '%d' ] );
		wp_safe_redirect( admin_url( 'admin.php?page=overcustomise-colours' ) );
		exit;
	}

	private function handle_delete(): void {
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;

		if ( ! $id || ! isset( $_GET['_wpnonce'] )
		     || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'oc_colour_delete_' . $id )
		) {
			wp_die( esc_html__( 'Security check failed.', 'overcustomise' ) );
		}

		global $wpdb;
		$wpdb->delete( "{$wpdb->prefix}oc_colours", [ 'id' => $id ], [ '%d' ] );
		wp_safe_redirect( admin_url( 'admin.php?page=overcustomise-colours' ) );
		exit;
	}
}
