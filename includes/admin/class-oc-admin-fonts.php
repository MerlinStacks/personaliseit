<?php
/**
 * Font Manager page — upload/manage fonts and font groups.
 * Upload and rename are AJAX; toggle/delete via nonce-signed GET.
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

class OC_Admin_Fonts {

	private const FONT_SUBDIR = 'overcustomise/fonts';

	// ── AJAX registration ──────────────────────────────────────────────────────

	public static function register_ajax(): void {
		add_action( 'wp_ajax_oc_font_upload',       [ self::class, 'ajax_upload' ] );
		add_action( 'wp_ajax_oc_font_rename',       [ self::class, 'ajax_rename' ] );
		add_action( 'wp_ajax_oc_font_convert',      [ self::class, 'ajax_convert' ] );
		add_action( 'wp_ajax_oc_group_create',      [ self::class, 'ajax_group_create' ] );
		add_action( 'wp_ajax_oc_group_update',      [ self::class, 'ajax_group_update' ] );
		add_action( 'wp_ajax_oc_group_delete',      [ self::class, 'ajax_group_delete' ] );
	}

	// ── Page render ────────────────────────────────────────────────────────────

	public function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'overcustomise' ) );
		}

		$get_action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		if ( 'toggle' === $get_action ) { $this->handle_toggle(); }
		if ( 'delete' === $get_action ) { $this->handle_delete(); }

		$fonts  = OC_DB::get_fonts( false );
		$groups = OC_DB::get_font_groups();

		// JS payload — fonts.
		$fonts_js = array_map( [ self::class, 'font_payload' ], $fonts );

		// JS payload — groups.
		$groups_js = array_map( function ( $g ) {
			return [
				'id'      => (int) $g->id,
				'name'    => $g->name,
				'fontIds' => array_map( 'intval', $g->font_ids ),
			];
		}, $groups );
		?>
		<script>
		window.ocFontsData  = <?php echo wp_json_encode( $fonts_js ); ?>;
		window.ocGroupsData = <?php echo wp_json_encode( $groups_js ); ?>;
		window.ocFontNonce  = <?php echo wp_json_encode( wp_create_nonce( 'oc_font_actions' ) ); ?>;
		window.ocAjaxUrl    = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
		</script>

		<div class="wrap oc-page">

			<div class="oc-page-header">
				<div class="oc-page-header-left">
					<h1 class="oc-page-title"><?php esc_html_e( 'Font Manager', 'overcustomise' ); ?></h1>
					<p class="oc-page-subtitle"><?php esc_html_e( 'Manage fonts and font groups for the product customiser.', 'overcustomise' ); ?></p>
				</div>
				<div class="oc-page-header-right">
					<button id="oc-upload-font-btn" class="oc-btn oc-btn-primary" type="button">
						+ <?php esc_html_e( 'Upload Font', 'overcustomise' ); ?>
					</button>
					<button id="oc-create-group-btn" class="oc-btn oc-btn-primary" type="button" style="display:none;">
						+ <?php esc_html_e( 'Create Group', 'overcustomise' ); ?>
					</button>
				</div>
			</div>

			<!-- Tabs ─────────────────────────────────────────────────────────── -->
			<div class="oc-tabs-bar">
				<button class="oc-tab oc-tab--active" data-target="oc-tab-fonts" type="button">
					<?php esc_html_e( 'Fonts', 'overcustomise' ); ?>
					<span class="oc-tab-count"><?php echo count( $fonts ); ?></span>
				</button>
				<button class="oc-tab" data-target="oc-tab-groups" type="button">
					<?php esc_html_e( 'Font Groups', 'overcustomise' ); ?>
					<span class="oc-tab-count"><?php echo count( $groups ); ?></span>
				</button>
			</div>

			<!-- ── Tab: Fonts ──────────────────────────────────────────────── -->
			<div id="oc-tab-fonts" class="oc-tab-panel">

				<div class="oc-card" id="oc-fonts-card">
					<div class="oc-card-header">
						<h2><?php esc_html_e( 'Installed Fonts', 'overcustomise' ); ?></h2>
						<span id="oc-fonts-count" style="font-size:12px;color:var(--oc-gray-400);">
							<?php echo esc_html( count( $fonts ) ); ?> <?php echo esc_html( 1 === count( $fonts ) ? __( 'font', 'overcustomise' ) : __( 'fonts', 'overcustomise' ) ); ?>
						</span>
					</div>

					<?php if ( empty( $fonts ) ) : ?>
						<div class="oc-empty" id="oc-fonts-empty">
							<span class="oc-empty-icon">🔤</span>
							<h3><?php esc_html_e( 'No fonts uploaded yet', 'overcustomise' ); ?></h3>
							<p><?php esc_html_e( 'Click "Upload Font" to add your first font.', 'overcustomise' ); ?></p>
						</div>
						<div class="oc-font-grid" id="oc-font-grid" style="display:none;"></div>
					<?php else : ?>
						<div class="oc-font-grid" id="oc-font-grid">
							<?php foreach ( $fonts as $font ) :
								$font_url   = self::get_font_url( $font->file_path );
								$ext        = strtoupper( pathinfo( $font->file_path, PATHINFO_EXTENSION ) );
								$can_convert = self::can_convert_for_print( (string) $font->file_path );
								$preview_id = 'oc-preview-' . (int) $font->id;
								?>
								<style>
									@font-face {
										font-family: '<?php echo esc_attr( $preview_id ); ?>';
										src: url('<?php echo esc_url( $font_url ); ?>');
										font-weight: <?php echo esc_attr( $font->weight ); ?>;
										font-style: <?php echo esc_attr( $font->style ); ?>;
									}
								</style>
								<div class="oc-font-card<?php echo $font->active ? '' : ' oc-font-card--inactive'; ?>"
								     data-font-id="<?php echo (int) $font->id; ?>"
								     data-font-name="<?php echo esc_attr( $font->name ); ?>"
								     role="button" tabindex="0">

									<div class="oc-font-preview">
										<span class="oc-font-preview-text"
										      style="font-family:'<?php echo esc_attr( $preview_id ); ?>';font-weight:<?php echo esc_attr( $font->weight ); ?>;font-style:<?php echo esc_attr( $font->style ); ?>;">
											AaBbCc 123
										</span>
									</div>

									<div class="oc-font-card-body">
										<div class="oc-font-card-title-row">
											<p class="oc-font-card-name" title="<?php echo esc_attr( $font->name ); ?>">
												<?php echo esc_html( $font->name ); ?>
											</p>
											<span class="oc-badge <?php echo $font->active ? 'oc-badge-active' : 'oc-badge-inactive'; ?>">
												<?php echo $font->active ? esc_html__( 'Active', 'overcustomise' ) : esc_html__( 'Inactive', 'overcustomise' ); ?>
											</span>
										</div>
										<div class="oc-font-card-meta">
											<span class="oc-badge oc-badge-format"><?php echo esc_html( $ext ); ?></span>
											<span class="oc-code"><?php echo esc_html( $font->weight ); ?></span>
											<?php if ( 'italic' === $font->style ) : ?>
												<span class="oc-badge oc-badge-inactive"><?php esc_html_e( 'Italic', 'overcustomise' ); ?></span>
											<?php endif; ?>
											<?php if ( $font->embroidery_suitable ) : ?>
												<span class="oc-badge oc-badge-active"><?php esc_html_e( 'Embroidery', 'overcustomise' ); ?></span>
											<?php endif; ?>
										</div>
										<div class="oc-font-card-actions">
											<?php if ( $can_convert ) : ?>
												<button type="button" class="oc-btn oc-btn-secondary oc-btn-sm oc-font-convert-btn" data-font-id="<?php echo (int) $font->id; ?>">
													<?php esc_html_e( 'Convert for print', 'overcustomise' ); ?>
												</button>
											<?php endif; ?>
											<a href="<?php echo esc_url( wp_nonce_url(
												admin_url( 'admin.php?page=overcustomise-fonts&action=toggle&id=' . (int) $font->id . '&state=' . ( $font->active ? '0' : '1' ) ),
												'oc_font_toggle_' . $font->id
											) ); ?>" class="oc-btn oc-btn-secondary oc-btn-sm">
												<?php echo $font->active ? esc_html__( 'Deactivate', 'overcustomise' ) : esc_html__( 'Activate', 'overcustomise' ); ?>
											</a>
											<a href="<?php echo esc_url( wp_nonce_url(
												admin_url( 'admin.php?page=overcustomise-fonts&action=delete&id=' . (int) $font->id ),
												'oc_font_delete_' . $font->id
											) ); ?>"
											   onclick="return confirm('<?php esc_attr_e( 'Delete this font?', 'overcustomise' ); ?>');"
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

			</div><!-- /oc-tab-fonts -->

			<!-- ── Tab: Font Groups ────────────────────────────────────────── -->
			<div id="oc-tab-groups" class="oc-tab-panel" hidden>

				<div class="oc-card">
					<div class="oc-card-header">
						<h2><?php esc_html_e( 'Font Groups', 'overcustomise' ); ?></h2>
						<span id="oc-groups-count" style="font-size:12px;color:var(--oc-gray-400);">
							<?php echo esc_html( count( $groups ) ); ?> <?php echo esc_html( 1 === count( $groups ) ? __( 'group', 'overcustomise' ) : __( 'groups', 'overcustomise' ) ); ?>
						</span>
					</div>

					<?php if ( empty( $groups ) ) : ?>
						<div class="oc-empty" id="oc-groups-empty">
							<span class="oc-empty-icon">📂</span>
							<h3><?php esc_html_e( 'No font groups yet', 'overcustomise' ); ?></h3>
							<p><?php esc_html_e( 'Create a group to bundle fonts together for easy assignment.', 'overcustomise' ); ?></p>
						</div>
						<div class="oc-group-grid" id="oc-group-grid" style="display:none;"></div>
					<?php else : ?>
						<div class="oc-group-grid" id="oc-group-grid">
							<?php foreach ( $groups as $group ) :
								$member_count = count( $group->font_ids );
								?>
								<div class="oc-group-card"
								     data-group-id="<?php echo (int) $group->id; ?>"
								     data-group-name="<?php echo esc_attr( $group->name ); ?>"
								     role="button" tabindex="0">
									<div class="oc-group-card-body">
										<p class="oc-group-card-name"><?php echo esc_html( $group->name ); ?></p>
										<p class="oc-group-card-count">
											<?php echo esc_html( $member_count . ' ' . ( 1 === $member_count ? __( 'font', 'overcustomise' ) : __( 'fonts', 'overcustomise' ) ) ); ?>
										</p>
										<div class="oc-group-card-previews">
											<?php foreach ( array_slice( $group->font_ids, 0, 5 ) as $fid ) :
												// Find the font by ID.
												$fdata = null;
												foreach ( $fonts as $f ) {
													if ( (int) $f->id === (int) $fid ) { $fdata = $f; break; }
												}
												if ( ! $fdata ) continue;
												?>
												<span style="font-family:'oc-preview-<?php echo (int) $fid; ?>';font-weight:<?php echo esc_attr( $fdata->weight ); ?>;font-style:<?php echo esc_attr( $fdata->style ); ?>;"
												      title="<?php echo esc_attr( $fdata->name ); ?>">Aa</span>
											<?php endforeach; ?>
											<?php if ( $member_count > 5 ) : ?>
												<span class="oc-group-card-more">+<?php echo esc_html( $member_count - 5 ); ?></span>
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

			</div><!-- /oc-tab-groups -->

			<!-- Font detail modal ────────────────────────────────────────────── -->
			<div id="oc-font-detail" class="oc-modal-overlay" hidden aria-modal="true" role="dialog">
				<div class="oc-modal oc-font-detail-modal">
					<div class="oc-modal-header">
						<div class="oc-font-detail-name-wrap">
							<input type="text" id="oc-detail-name" class="oc-input oc-detail-name-input"
							       placeholder="<?php esc_attr_e( 'Font family name', 'overcustomise' ); ?>" />
							<button id="oc-detail-save-name" class="oc-btn oc-btn-primary oc-btn-sm" type="button">
								<?php esc_html_e( 'Rename', 'overcustomise' ); ?>
							</button>
						</div>
						<button id="oc-detail-close" class="oc-modal-close" type="button">×</button>
					</div>
					<div id="oc-detail-variants" class="oc-detail-variants"></div>
					<div class="oc-modal-footer">
						<button id="oc-add-weight-btn" class="oc-btn oc-btn-secondary" type="button">
							+ <?php esc_html_e( 'Add weight / style', 'overcustomise' ); ?>
						</button>
					</div>
				</div>
			</div>

			<!-- Group editor modal ───────────────────────────────────────────── -->
			<div id="oc-group-modal" class="oc-modal-overlay" hidden aria-modal="true" role="dialog">
				<div class="oc-modal oc-group-editor-modal">

					<div class="oc-modal-header">
						<div style="flex:1;min-width:0;">
							<input type="text" id="oc-group-name-input" class="oc-input"
							       placeholder="<?php esc_attr_e( 'Group name', 'overcustomise' ); ?>" />
						</div>
						<button id="oc-group-modal-close" class="oc-modal-close" type="button">×</button>
					</div>

					<div class="oc-modal-body" style="padding:0;">
						<div class="oc-group-picker-header">
							<span><?php esc_html_e( 'Select fonts for this group', 'overcustomise' ); ?></span>
							<span id="oc-group-selected-count" class="oc-group-selected-count">0 selected</span>
						</div>
						<div id="oc-group-font-picker" class="oc-group-font-picker"></div>
					</div>

					<div class="oc-modal-footer">
						<button id="oc-group-delete-btn" class="oc-btn oc-btn-danger" type="button" style="margin-right:auto;display:none;">
							<?php esc_html_e( 'Delete Group', 'overcustomise' ); ?>
						</button>
						<button id="oc-group-cancel-btn" class="oc-btn oc-btn-secondary" type="button">
							<?php esc_html_e( 'Cancel', 'overcustomise' ); ?>
						</button>
						<button id="oc-group-save-btn" class="oc-btn oc-btn-primary" type="button">
							<?php esc_html_e( 'Save Group', 'overcustomise' ); ?>
						</button>
					</div>

				</div>
			</div>

			<!-- Upload modal ─────────────────────────────────────────────────── -->
			<div id="oc-upload-modal" class="oc-modal-overlay" hidden aria-modal="true" role="dialog">
				<div class="oc-modal">

					<div class="oc-modal-header">
						<h3><?php esc_html_e( 'Upload Font', 'overcustomise' ); ?></h3>
						<button id="oc-modal-close-btn" class="oc-modal-close" type="button">×</button>
					</div>

					<div class="oc-modal-body">
						<div id="oc-upload-step-1">
							<div id="oc-drop-zone" class="oc-drop-zone">
								<span class="oc-drop-zone-icon">🔤</span>
								<span class="oc-drop-zone-title"><?php esc_html_e( 'Drop a font file here, or click to browse', 'overcustomise' ); ?></span>
								<span class="oc-drop-zone-hint"><?php esc_html_e( 'TTF · OTF  →  auto-converted to WOFF', 'overcustomise' ); ?> &nbsp;·&nbsp; WOFF · WOFF2</span>
							</div>
							<input type="file" id="oc_font_file" accept=".ttf,.otf,.woff,.woff2" style="display:none;" />
						</div>

						<div id="oc-upload-step-2" style="display:none;">
							<div class="oc-upload-preview-box" id="oc-upload-preview-box">
								<span id="oc-upload-preview-text">AaBbCc 123</span>
							</div>
							<div class="oc-upload-fields">
								<div class="oc-upload-field">
									<label for="oc_font_name"><?php esc_html_e( 'Font family name', 'overcustomise' ); ?></label>
									<input type="text" id="oc_font_name" class="oc-input" required
									       placeholder="<?php esc_attr_e( 'e.g. Block Bold', 'overcustomise' ); ?>" />
								</div>
								<div class="oc-upload-field oc-upload-field-row">
									<div>
										<label for="oc_font_weight"><?php esc_html_e( 'Weight', 'overcustomise' ); ?></label>
										<select id="oc_font_weight" class="oc-select">
											<?php foreach ( [ '100', '200', '300', 'normal', '500', '600', 'bold', '800', '900' ] as $w ) : ?>
												<option value="<?php echo esc_attr( $w ); ?>"><?php echo esc_html( $w ); ?></option>
											<?php endforeach; ?>
										</select>
									</div>
									<div>
										<label for="oc_font_style"><?php esc_html_e( 'Style', 'overcustomise' ); ?></label>
										<select id="oc_font_style" class="oc-select">
											<option value="normal"><?php esc_html_e( 'Normal', 'overcustomise' ); ?></option>
											<option value="italic"><?php esc_html_e( 'Italic', 'overcustomise' ); ?></option>
										</select>
									</div>
								</div>
								<div class="oc-upload-field">
									<label class="oc-checkbox-label">
										<input type="checkbox" id="oc_font_embroidery" value="1" />
										<?php esc_html_e( 'Embroidery suitable', 'overcustomise' ); ?>
									</label>
								</div>
							</div>
							<div id="oc-upload-error" class="oc-upload-error" style="display:none;"></div>
						</div>
					</div>

					<div class="oc-modal-footer" id="oc-modal-footer-step2" style="display:none;">
						<button type="button" id="oc-upload-back-btn" class="oc-btn oc-btn-secondary">
							&#8592; <?php esc_html_e( 'Back', 'overcustomise' ); ?>
						</button>
						<button type="button" id="oc-upload-submit-btn" class="oc-btn oc-btn-primary">
							<?php esc_html_e( 'Upload Font', 'overcustomise' ); ?>
						</button>
					</div>

				</div>
			</div>

		</div>
		<?php
	}

	// ── AJAX: font upload ──────────────────────────────────────────────────────

	public static function ajax_upload(): void {
		check_ajax_referer( 'oc_font_actions', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'overcustomise' ) ] );
		}
		$result = self::do_upload();
		is_wp_error( $result ) ? wp_send_json_error( [ 'message' => $result->get_error_message() ] ) : wp_send_json_success( $result );
	}

	// ── AJAX: font rename ──────────────────────────────────────────────────────

	public static function ajax_rename(): void {
		check_ajax_referer( 'oc_font_actions', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'overcustomise' ) ] );
		}

		$id       = (int) ( $_POST['id'] ?? 0 );
		$new_name = sanitize_text_field( $_POST['name'] ?? '' );

		if ( ! $id || ! $new_name ) {
			wp_send_json_error( [ 'message' => __( 'Invalid request.', 'overcustomise' ) ] );
		}

		global $wpdb;
		$old_name = $wpdb->get_var( $wpdb->prepare(
			"SELECT name FROM {$wpdb->prefix}oc_fonts WHERE id = %d", $id
		) );

		if ( ! $old_name ) {
			wp_send_json_error( [ 'message' => __( 'Font not found.', 'overcustomise' ) ] );
		}

		$wpdb->update(
			"{$wpdb->prefix}oc_fonts",
			[ 'name' => $new_name ],
			[ 'id' => $id ],
			[ '%s' ], [ '%d' ]
		);

		wp_send_json_success( [ 'newName' => $new_name, 'oldName' => $old_name ] );
	}

	// ── AJAX: convert WOFF font to print-compatible sfnt ────────────────────────

	public static function ajax_convert(): void {
		check_ajax_referer( 'oc_font_actions', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'overcustomise' ) ] );
		}

		$id = (int) ( $_POST['id'] ?? 0 );
		if ( ! $id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid request.', 'overcustomise' ) ] );
		}

		$result = self::convert_font_for_print( $id );
		is_wp_error( $result ) ? wp_send_json_error( [ 'message' => $result->get_error_message() ] ) : wp_send_json_success( $result );
	}

	// ── AJAX: group create ─────────────────────────────────────────────────────

	public static function ajax_group_create(): void {
		check_ajax_referer( 'oc_font_actions', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'overcustomise' ) ] );
		}

		$name = sanitize_text_field( $_POST['name'] ?? '' );
		if ( ! $name ) {
			wp_send_json_error( [ 'message' => __( 'Group name is required.', 'overcustomise' ) ] );
		}

		global $wpdb;
		$wpdb->insert( "{$wpdb->prefix}oc_font_groups", [ 'name' => $name ], [ '%s' ] );
		$id = (int) $wpdb->insert_id;

		wp_send_json_success( [ 'id' => $id, 'name' => $name, 'fontIds' => [] ] );
	}

	// ── AJAX: group update (name + font members) ───────────────────────────────

	public static function ajax_group_update(): void {
		check_ajax_referer( 'oc_font_actions', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'overcustomise' ) ] );
		}

		$id       = (int) ( $_POST['id'] ?? 0 );
		$name     = sanitize_text_field( $_POST['name'] ?? '' );
		$font_ids = array_filter( array_map( 'intval', (array) ( $_POST['font_ids'] ?? [] ) ) );

		if ( ! $id || ! $name ) {
			wp_send_json_error( [ 'message' => __( 'Invalid request.', 'overcustomise' ) ] );
		}

		global $wpdb;
		$wpdb->update(
			"{$wpdb->prefix}oc_font_groups",
			[ 'name' => $name ],
			[ 'id'   => $id ],
			[ '%s' ], [ '%d' ]
		);

		// Replace members.
		$wpdb->delete( "{$wpdb->prefix}oc_font_group_items", [ 'group_id' => $id ], [ '%d' ] );
		foreach ( array_values( $font_ids ) as $order => $font_id ) {
			$wpdb->insert(
				"{$wpdb->prefix}oc_font_group_items",
				[ 'group_id' => $id, 'font_id' => $font_id, 'sort_order' => $order ],
				[ '%d', '%d', '%d' ]
			);
		}

		wp_send_json_success( [ 'id' => $id, 'name' => $name, 'fontIds' => array_values( $font_ids ) ] );
	}

	// ── AJAX: group delete ─────────────────────────────────────────────────────

	public static function ajax_group_delete(): void {
		check_ajax_referer( 'oc_font_actions', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'overcustomise' ) ] );
		}

		$id = (int) ( $_POST['id'] ?? 0 );
		if ( ! $id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid request.', 'overcustomise' ) ] );
		}

		global $wpdb;
		$wpdb->delete( "{$wpdb->prefix}oc_font_group_items", [ 'group_id' => $id ], [ '%d' ] );
		$wpdb->delete( "{$wpdb->prefix}oc_font_groups", [ 'id' => $id ], [ '%d' ] );

		wp_send_json_success();
	}

	// ── Shared upload logic ────────────────────────────────────────────────────

	private static function do_upload(): array|\WP_Error {
		if ( empty( $_FILES['oc_font_file']['name'] ) ) {
			return new \WP_Error( 'no_file', __( 'Please select a font file.', 'overcustomise' ) );
		}

		$file = $_FILES['oc_font_file'];

		if ( ! empty( $file['error'] ) && UPLOAD_ERR_OK !== (int) $file['error'] ) {
			return new \WP_Error( 'upload_error', __( 'Upload failed before reaching the server.', 'overcustomise' ) );
		}
		if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new \WP_Error( 'no_file', __( 'Please select a font file.', 'overcustomise' ) );
		}

		// Cap to 10 MB — fonts are small.
		if ( (int) ( $file['size'] ?? 0 ) > 10 * 1024 * 1024 ) {
			return new \WP_Error( 'too_large', __( 'Font file exceeds size limit.', 'overcustomise' ) );
		}

		$filename = basename( (string) $file['name'] );
		$ext      = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		$allowed  = [ 'ttf', 'otf', 'woff', 'woff2' ];

		if ( ! in_array( $ext, $allowed, true ) ) {
			return new \WP_Error( 'bad_ext', __( 'Invalid file type. Allowed: TTF, OTF, WOFF, WOFF2.', 'overcustomise' ) );
		}

		$upload   = wp_upload_dir();
		if ( ! empty( $upload['error'] ) ) {
			return new \WP_Error( 'upload_dir_error', (string) $upload['error'] );
		}
		$font_dir = $upload['basedir'] . '/' . self::FONT_SUBDIR;
		if ( ! wp_mkdir_p( $font_dir ) ) {
			return new \WP_Error( 'mkdir_failed', __( 'Could not create font directory.', 'overcustomise' ) );
		}

		$htaccess = $font_dir . '/.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			if ( false === file_put_contents( $htaccess, "Options -Indexes\n" ) ) {
				return new \WP_Error( 'htaccess_failed', __( 'Could not protect font directory.', 'overcustomise' ) );
			}
		}

		$base = sanitize_file_name( pathinfo( $filename, PATHINFO_FILENAME ) ) . '-' . time();
		if ( '' === trim( $base, '-' ) || '-' === substr( $base, 0, 1 ) && strlen( $base ) === 11 ) {
			$base = 'font-' . time();
		}

		$dest = $font_dir . '/' . $base . '.' . $ext;
		if ( ! move_uploaded_file( $file['tmp_name'], $dest ) ) {
			return new \WP_Error( 'upload_failed', __( 'File upload failed.', 'overcustomise' ) );
		}
		$rel_path = self::FONT_SUBDIR . '/' . $base . '.' . $ext;

		$name       = sanitize_text_field( $_POST['oc_font_name'] ?? '' );
		$weight     = sanitize_text_field( $_POST['oc_font_weight'] ?? 'normal' );
		$style      = in_array( $_POST['oc_font_style'] ?? '', [ 'normal', 'italic' ], true )
			? sanitize_key( $_POST['oc_font_style'] ) : 'normal';
		$embroidery = isset( $_POST['oc_font_embroidery'] ) ? 1 : 0;

		global $wpdb;
		$inserted = $wpdb->insert(
			"{$wpdb->prefix}oc_fonts",
			[ 'name' => $name, 'file_path' => $rel_path, 'weight' => $weight,
			  'style' => $style, 'embroidery_suitable' => $embroidery, 'active' => 1 ],
			[ '%s', '%s', '%s', '%s', '%d', '%d' ]
		);

		$id  = (int) $wpdb->insert_id;
		if ( false === $inserted || $id <= 0 ) {
			wp_delete_file( $dest );
			return new \WP_Error( 'db_error', __( 'Could not save font record.', 'overcustomise' ) );
		}
		$font = (object) [
			'id'                  => $id,
			'name'                => $name,
			'file_path'           => $rel_path,
			'weight'              => $weight,
			'style'               => $style,
			'embroidery_suitable' => $embroidery,
			'active'              => 1,
		];

		return self::font_payload( $font );
	}

	private static function convert_font_for_print( int $id ): array|\WP_Error {
		global $wpdb;
		$font = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}oc_fonts WHERE id = %d", $id ) );
		if ( ! $font ) {
			return new \WP_Error( 'not_found', __( 'Font not found.', 'overcustomise' ) );
		}

		$ext = strtolower( pathinfo( (string) $font->file_path, PATHINFO_EXTENSION ) );
		if ( 'woff2' === $ext ) {
			return new \WP_Error( 'woff2_unsupported', __( 'WOFF2 cannot be converted automatically. Upload the original TTF or OTF and keep the same font name/style.', 'overcustomise' ) );
		}
		if ( 'woff' !== $ext ) {
			return new \WP_Error( 'already_compatible', __( 'This font is already print-compatible.', 'overcustomise' ) );
		}
		if ( ! class_exists( 'OC_WOFF_Converter' ) ) {
			return new \WP_Error( 'converter_missing', __( 'Font converter is unavailable.', 'overcustomise' ) );
		}

		$upload = wp_upload_dir();
		if ( ! empty( $upload['error'] ) ) {
			return new \WP_Error( 'upload_dir_error', (string) $upload['error'] );
		}

		$source = trailingslashit( $upload['basedir'] ) . ltrim( (string) $font->file_path, '/' );
		$real   = realpath( $source );
		$base   = realpath( $upload['basedir'] );
		if ( ! $real || ! $base || 0 !== strpos( $real, $base ) || ! file_exists( $real ) ) {
			return new \WP_Error( 'missing_file', __( 'Stored font file could not be found.', 'overcustomise' ) );
		}

		$font_dir = trailingslashit( $upload['basedir'] ) . self::FONT_SUBDIR;
		if ( ! wp_mkdir_p( $font_dir ) ) {
			return new \WP_Error( 'mkdir_failed', __( 'Could not create font directory.', 'overcustomise' ) );
		}

		$output_ext = self::woff_sfnt_extension( $real );
		$filename   = sanitize_file_name( pathinfo( (string) $font->file_path, PATHINFO_FILENAME ) ) . '-print.' . $output_ext;
		$dest       = trailingslashit( $font_dir ) . wp_unique_filename( $font_dir, $filename );
		if ( ! OC_WOFF_Converter::extract_sfnt( $real, $dest ) ) {
			return new \WP_Error( 'convert_failed', __( 'Could not convert this WOFF font. Upload the original TTF or OTF instead.', 'overcustomise' ) );
		}

		$rel_path = self::FONT_SUBDIR . '/' . basename( $dest );
		$updated  = $wpdb->update(
			"{$wpdb->prefix}oc_fonts",
			[ 'file_path' => $rel_path ],
			[ 'id' => $id ],
			[ '%s' ],
			[ '%d' ]
		);

		if ( false === $updated ) {
			wp_delete_file( $dest );
			return new \WP_Error( 'db_error', __( 'Could not update font record.', 'overcustomise' ) );
		}

		$font->file_path = $rel_path;
		return self::font_payload( $font );
	}

	private static function font_payload( object $font ): array {
		$ext = strtoupper( pathinfo( (string) $font->file_path, PATHINFO_EXTENSION ) );

		return [
			'id'                  => (int) $font->id,
			'name'                => $font->name,
			'weight'              => $font->weight,
			'style'               => $font->style,
			'embroidery_suitable' => (bool) $font->embroidery_suitable,
			'active'              => (bool) $font->active,
			'url'                 => self::get_font_url( (string) $font->file_path ),
			'ext'                 => $ext,
			'canPrintConvert'     => self::can_convert_for_print( (string) $font->file_path ),
			'printNote'           => 'WOFF2' === $ext ? __( 'WOFF2 needs the original TTF or OTF for print output.', 'overcustomise' ) : '',
			'toggleUrl'           => wp_nonce_url(
				admin_url( 'admin.php?page=overcustomise-fonts&action=toggle&id=' . (int) $font->id . '&state=' . ( $font->active ? '0' : '1' ) ),
				'oc_font_toggle_' . (int) $font->id
			),
			'deleteUrl'           => wp_nonce_url(
				admin_url( 'admin.php?page=overcustomise-fonts&action=delete&id=' . (int) $font->id ),
				'oc_font_delete_' . (int) $font->id
			),
		];
	}

	private static function can_convert_for_print( string $file_path ): bool {
		return 'woff' === strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
	}

	private static function woff_sfnt_extension( string $path ): string {
		$handle = fopen( $path, 'rb' );
		if ( ! $handle ) {
			return 'ttf';
		}
		fseek( $handle, 4 );
		$flavor = fread( $handle, 4 );
		fclose( $handle );

		return 'OTTO' === $flavor ? 'otf' : 'ttf';
	}

	// ── GET action handlers ────────────────────────────────────────────────────

	private function handle_toggle(): void {
		$id    = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		$state = isset( $_GET['state'] ) ? (int) $_GET['state'] : 0;

		if ( ! $id || ! isset( $_GET['_wpnonce'] )
		     || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'oc_font_toggle_' . $id )
		) {
			wp_die( esc_html__( 'Security check failed.', 'overcustomise' ) );
		}

		global $wpdb;
		$wpdb->update( "{$wpdb->prefix}oc_fonts", [ 'active' => (int) (bool) $state ], [ 'id' => $id ], [ '%d' ], [ '%d' ] );
		wp_safe_redirect( admin_url( 'admin.php?page=overcustomise-fonts' ) );
		exit;
	}

	private function handle_delete(): void {
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;

		if ( ! $id || ! isset( $_GET['_wpnonce'] )
		     || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'oc_font_delete_' . $id )
		) {
			wp_die( esc_html__( 'Security check failed.', 'overcustomise' ) );
		}

		global $wpdb;
		$font = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}oc_fonts WHERE id = %d", $id ) );

		if ( $font ) {
			$upload = wp_upload_dir();
			$path   = $upload['basedir'] . '/' . ltrim( (string) $font->file_path, '/' );
			$base   = realpath( $upload['basedir'] );
			$real   = realpath( $path );
			if ( $base && $real && 0 === strpos( $real, $base ) && file_exists( $real ) ) {
				wp_delete_file( $real );
			}
			$wpdb->delete( "{$wpdb->prefix}oc_fonts", [ 'id' => $id ], [ '%d' ] );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=overcustomise-fonts' ) );
		exit;
	}

	private static function get_font_url( string $rel_path ): string {
		$upload = wp_upload_dir();
		return $upload['baseurl'] . '/' . $rel_path;
	}
}
