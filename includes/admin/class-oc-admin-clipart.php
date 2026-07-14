<?php
/**
 * Clipart Manager page — upload/manage clipart images and clipart groups.
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

class OC_Admin_Clipart {

	private const CLIPART_SUBDIR = 'overcustomise/clipart';
	private const PRINT_METHODS = [ 'engraving', 'uv', 'embroidery', 'sublimation' ];
	private const INITIAL_CARD_LIMIT = 60;

	/** Clear cached clipart and clipart-group data after manager changes. */
	private static function clear_clipart_cache(): void {
		OC_Cache::delete( 'clipart_active' );
		OC_Cache::delete( 'clipart_all' );
		OC_Cache::delete( 'clipart_groups' );
	}

	// ── AJAX registration ──────────────────────────────────────────────────────

	public static function register_ajax(): void {
		add_action( 'wp_ajax_oc_clipart_upload',       [ self::class, 'ajax_upload' ] );
		add_action( 'wp_ajax_oc_clipart_convert_svg',  [ self::class, 'ajax_convert_svg' ] );
		add_action( 'wp_ajax_oc_clipart_rename',       [ self::class, 'ajax_rename' ] );
		add_action( 'wp_ajax_oc_clipart_group_create', [ self::class, 'ajax_group_create' ] );
		add_action( 'wp_ajax_oc_clipart_group_update', [ self::class, 'ajax_group_update' ] );
		add_action( 'wp_ajax_oc_clipart_group_delete', [ self::class, 'ajax_group_delete' ] );
	}

	// ── URL helper ────────────────────────────────────────────────────────────

	public static function get_clipart_url( string $file_path ): string {
		$upload_dir = wp_upload_dir();
		$base_dir   = wp_normalize_path( $upload_dir['basedir'] );
		$norm_path  = wp_normalize_path( $file_path );
		if ( str_starts_with( $norm_path, $base_dir ) ) {
			return $upload_dir['baseurl'] . substr( $norm_path, strlen( $base_dir ) );
		}
		return '';
	}

	// ── Page render ────────────────────────────────────────────────────────────

	public function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'overcustomise' ) );
		}

		$get_action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		if ( 'toggle' === $get_action ) { $this->handle_toggle(); }
		if ( 'delete' === $get_action ) { $this->handle_delete(); }

		$clipart = OC_DB::get_clipart( false );
		$groups  = OC_DB::get_clipart_groups();

		// JS payload — clipart items.
		$clipart_js = array_map( function ( $c ) {
			$file_type = strtolower( (string) $c->file_type );
			return [
				'id'        => (int) $c->id,
				'name'      => $c->name,
				'fileType'   => $file_type,
				'canConvert' => 'svg' !== $file_type,
				'colourChangeable' => self::clipart_colour_changeable( $c ),
				'allowedPrintMethods' => self::clipart_allowed_print_methods( $c ),
				'url'       => self::get_clipart_url( $c->file_path ),
				'active'    => (bool) $c->active,
				'toggleUrl' => wp_nonce_url(
					admin_url( 'admin.php?page=overcustomise-clipart&action=toggle&id=' . $c->id . '&state=' . ( $c->active ? '0' : '1' ) ),
					'oc_clipart_toggle_' . $c->id
				),
				'deleteUrl' => wp_nonce_url(
					admin_url( 'admin.php?page=overcustomise-clipart&action=delete&id=' . $c->id ),
					'oc_clipart_delete_' . $c->id
				),
			];
		}, $clipart );

		// JS payload — groups.
		$groups_js = array_map( function ( $g ) {
			return [
				'id'        => (int) $g->id,
				'name'      => $g->name,
				'clipartIds' => array_map( 'intval', $g->clipart_ids ),
			];
		}, $groups );
		?>
		<script>
		window.ocClipartData   = <?php echo wp_json_encode( $clipart_js ); ?>;
		window.ocClipartGroups = <?php echo wp_json_encode( $groups_js ); ?>;
		window.ocClipartNonce  = <?php echo wp_json_encode( wp_create_nonce( 'oc_clipart_actions' ) ); ?>;
		window.ocAjaxUrl       = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
		</script>

		<div class="wrap oc-page">

			<div class="oc-page-header">
				<div class="oc-page-header-left">
					<h1 class="oc-page-title"><?php esc_html_e( 'Clipart Manager', 'overcustomise' ); ?></h1>
					<p class="oc-page-subtitle"><?php esc_html_e( 'Manage clipart images and clipart groups for the product customiser.', 'overcustomise' ); ?></p>
				</div>
				<div class="oc-page-header-right">
					<button id="oc-upload-clipart-btn" class="oc-btn oc-btn-primary" type="button">
						+ <?php esc_html_e( 'Upload Clipart', 'overcustomise' ); ?>
					</button>
					<button id="oc-create-clipart-group-btn" class="oc-btn oc-btn-primary" type="button" style="display:none;">
						+ <?php esc_html_e( 'Create Group', 'overcustomise' ); ?>
					</button>
				</div>
			</div>

			<!-- Tabs ──────────────────────────────────────────────────────────── -->
			<div class="oc-tabs-bar">
				<button class="oc-tab oc-tab--active" data-target="oc-tab-clipart" type="button">
					<?php esc_html_e( 'Clipart', 'overcustomise' ); ?>
					<span class="oc-tab-count"><?php echo count( $clipart ); ?></span>
				</button>
				<button class="oc-tab" data-target="oc-tab-clipart-groups" type="button">
					<?php esc_html_e( 'Clipart Groups', 'overcustomise' ); ?>
					<span class="oc-tab-count"><?php echo count( $groups ); ?></span>
				</button>
			</div>

			<!-- ── Tab: Clipart ───────────────────────────────────────────── -->
			<div id="oc-tab-clipart" class="oc-tab-panel">
				<div class="oc-card">
					<div class="oc-card-header">
						<h2><?php esc_html_e( 'Clipart', 'overcustomise' ); ?></h2>
						<span id="oc-clipart-count" style="font-size:12px;color:var(--oc-gray-400);">
							<?php echo esc_html( count( $clipart ) ); ?> <?php echo esc_html( 1 === count( $clipart ) ? __( 'item', 'overcustomise' ) : __( 'items', 'overcustomise' ) ); ?>
						</span>
					</div>

					<?php if ( empty( $clipart ) ) : ?>
						<div class="oc-empty" id="oc-clipart-empty">
							<span class="oc-empty-icon">🖼️</span>
							<h3><?php esc_html_e( 'No clipart uploaded yet', 'overcustomise' ); ?></h3>
							<p><?php esc_html_e( 'Click "Upload Clipart" to add your first clipart image.', 'overcustomise' ); ?></p>
						</div>
						<div class="oc-clipart-grid" id="oc-clipart-grid" style="display:none;"></div>
					<?php else : ?>
						<div class="oc-clipart-grid" id="oc-clipart-grid">
							<?php foreach ( array_slice( $clipart, 0, self::INITIAL_CARD_LIMIT ) as $item ) :
								$item_url = self::get_clipart_url( $item->file_path );
								?>
								<div class="oc-clipart-card<?php echo $item->active ? '' : ' oc-clipart-card--inactive'; ?>"
								     data-clipart-id="<?php echo (int) $item->id; ?>"
								     role="button" tabindex="0">

									<div class="oc-clipart-preview">
										<img src="<?php echo esc_url( $item_url ); ?>"
										     alt="<?php echo esc_attr( $item->name ); ?>"
										     loading="lazy" />
									</div>

									<div class="oc-clipart-card-body">
										<div class="oc-clipart-card-title-row">
											<p class="oc-clipart-card-name" title="<?php echo esc_attr( $item->name ); ?>">
												<?php echo esc_html( $item->name ); ?>
											</p>
											<span class="oc-badge <?php echo $item->active ? 'oc-badge-active' : 'oc-badge-inactive'; ?>">
												<?php echo $item->active ? esc_html__( 'Active', 'overcustomise' ) : esc_html__( 'Inactive', 'overcustomise' ); ?>
											</span>
										</div>
										<p class="oc-clipart-type-label"><?php echo esc_html( strtoupper( $item->file_type ) ); ?></p>
										<div class="oc-clipart-card-actions">
											<?php if ( 'svg' !== strtolower( (string) $item->file_type ) ) : ?>
												<button type="button" class="oc-btn oc-btn-secondary oc-btn-sm" data-oc-convert-clipart="<?php echo (int) $item->id; ?>">
													<?php esc_html_e( 'Convert to SVG', 'overcustomise' ); ?>
												</button>
											<?php endif; ?>
											<a href="<?php echo esc_url( wp_nonce_url(
												admin_url( 'admin.php?page=overcustomise-clipart&action=toggle&id=' . (int) $item->id . '&state=' . ( $item->active ? '0' : '1' ) ),
												'oc_clipart_toggle_' . $item->id
											) ); ?>" class="oc-btn oc-btn-secondary oc-btn-sm">
												<?php echo $item->active ? esc_html__( 'Deactivate', 'overcustomise' ) : esc_html__( 'Activate', 'overcustomise' ); ?>
											</a>
											<a href="<?php echo esc_url( wp_nonce_url(
												admin_url( 'admin.php?page=overcustomise-clipart&action=delete&id=' . (int) $item->id ),
												'oc_clipart_delete_' . $item->id
											) ); ?>"
											   onclick="return confirm('<?php esc_attr_e( 'Delete this clipart?', 'overcustomise' ); ?>');"
											   class="oc-btn oc-btn-danger oc-btn-sm">
												<?php esc_html_e( 'Delete', 'overcustomise' ); ?>
											</a>
										</div>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
						<?php if ( count( $clipart ) > self::INITIAL_CARD_LIMIT ) : ?>
							<div style="padding:0 18px 18px;text-align:center;">
								<button type="button" class="button" id="oc-clipart-load-more" data-offset="<?php echo esc_attr( self::INITIAL_CARD_LIMIT ); ?>" data-step="<?php echo esc_attr( self::INITIAL_CARD_LIMIT ); ?>">
									<?php esc_html_e( 'Load more clipart', 'overcustomise' ); ?>
								</button>
							</div>
						<?php endif; ?>
					<?php endif; ?>
				</div>
			</div>

			<!-- ── Tab: Clipart Groups ─────────────────────────────────────── -->
			<div id="oc-tab-clipart-groups" class="oc-tab-panel" hidden>
				<div class="oc-card">
					<div class="oc-card-header">
						<h2><?php esc_html_e( 'Clipart Groups', 'overcustomise' ); ?></h2>
						<span id="oc-clipart-groups-count" style="font-size:12px;color:var(--oc-gray-400);">
							<?php echo esc_html( count( $groups ) ); ?> <?php echo esc_html( 1 === count( $groups ) ? __( 'group', 'overcustomise' ) : __( 'groups', 'overcustomise' ) ); ?>
						</span>
					</div>

					<?php if ( empty( $groups ) ) : ?>
						<div class="oc-empty" id="oc-clipart-groups-empty">
							<span class="oc-empty-icon">📂</span>
							<h3><?php esc_html_e( 'No clipart groups yet', 'overcustomise' ); ?></h3>
							<p><?php esc_html_e( 'Create a group to bundle clipart for easy assignment.', 'overcustomise' ); ?></p>
						</div>
						<div class="oc-group-grid" id="oc-clipart-group-grid" style="display:none;"></div>
					<?php else : ?>
						<div class="oc-group-grid" id="oc-clipart-group-grid">
							<?php foreach ( $groups as $group ) :
								$member_count = count( $group->clipart_ids );
								?>
								<div class="oc-group-card oc-clipart-group-card"
								     data-group-id="<?php echo (int) $group->id; ?>"
								     data-group-name="<?php echo esc_attr( $group->name ); ?>"
								     role="button" tabindex="0">
									<div class="oc-group-card-body">
										<p class="oc-group-card-name"><?php echo esc_html( $group->name ); ?></p>
										<p class="oc-group-card-count">
											<?php echo esc_html( $member_count . ' ' . ( 1 === $member_count ? __( 'item', 'overcustomise' ) : __( 'items', 'overcustomise' ) ) ); ?>
										</p>
										<div class="oc-clipart-group-thumbs">
											<?php foreach ( array_slice( $group->clipart_ids, 0, 6 ) as $cid ) :
												$cdata = null;
												foreach ( $clipart as $c ) {
													if ( (int) $c->id === (int) $cid ) { $cdata = $c; break; }
												}
												if ( ! $cdata ) continue;
												$thumb_url = self::get_clipart_url( $cdata->file_path );
												?>
												<div class="oc-clipart-thumb" title="<?php echo esc_attr( $cdata->name ); ?>">
													<img src="<?php echo esc_url( $thumb_url ); ?>" alt="<?php echo esc_attr( $cdata->name ); ?>" loading="lazy" />
												</div>
											<?php endforeach; ?>
											<?php if ( $member_count > 6 ) : ?>
												<span class="oc-group-card-more">+<?php echo esc_html( $member_count - 6 ); ?></span>
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

			<!-- Clipart editor modal ─────────────────────────────────────────── -->
			<div id="oc-clipart-modal" class="oc-modal-overlay" hidden aria-modal="true" role="dialog">
				<div class="oc-modal oc-clipart-editor-modal">

					<div class="oc-modal-header">
						<h3><?php esc_html_e( 'Edit Clipart', 'overcustomise' ); ?></h3>
						<button id="oc-clipart-modal-close" class="oc-modal-close" type="button">×</button>
					</div>

					<div class="oc-modal-body">
						<div class="oc-clipart-modal-preview">
							<img id="oc-clipart-modal-preview-img" src="" alt="" />
						</div>
				<div class="oc-upload-fields">
					<div class="oc-upload-field">
						<label for="oc_clipart_name"><?php esc_html_e( 'Clipart name', 'overcustomise' ); ?></label>
						<input type="text" id="oc_clipart_name" class="oc-input" required
						       placeholder="<?php esc_attr_e( 'e.g. Star Shape', 'overcustomise' ); ?>" />
					</div>
					<div class="oc-upload-field">
						<label><input type="checkbox" id="oc_clipart_colour_changeable" /> <?php esc_html_e( 'Colour changeable', 'overcustomise' ); ?></label>
						<p class="description"><?php esc_html_e( 'Use only for simple one-colour SVG clipart. Leave off for full-colour artwork.', 'overcustomise' ); ?></p>
					</div>
					<div class="oc-upload-field">
						<label><?php esc_html_e( 'Allowed print methods', 'overcustomise' ); ?></label>
						<div class="oc-group-checks">
							<?php foreach ( self::PRINT_METHODS as $method ) : ?>
								<label class="oc-group-check-item"><input type="checkbox" class="oc-clipart-method-check" value="<?php echo esc_attr( $method ); ?>" /> <span><?php echo esc_html( self::print_method_label( $method ) ); ?></span></label>
							<?php endforeach; ?>
						</div>
						<p class="description"><?php esc_html_e( 'No selection means available for all print methods.', 'overcustomise' ); ?></p>
					</div>
				</div>
						<div id="oc-clipart-error" class="oc-upload-error" style="display:none;"></div>
					</div>

					<div class="oc-modal-footer">
						<button id="oc-clipart-delete-btn" class="oc-btn oc-btn-danger" type="button" style="display:none;margin-right:auto;">
							<?php esc_html_e( 'Delete', 'overcustomise' ); ?>
						</button>
						<button id="oc-clipart-cancel-btn" class="oc-btn oc-btn-secondary" type="button">
							<?php esc_html_e( 'Cancel', 'overcustomise' ); ?>
						</button>
						<button id="oc-clipart-save-btn" class="oc-btn oc-btn-primary" type="button">
							<?php esc_html_e( 'Save', 'overcustomise' ); ?>
						</button>
					</div>

				</div>
			</div>

			<!-- Clipart group editor modal ───────────────────────────────────── -->
			<div id="oc-clipart-group-modal" class="oc-modal-overlay" hidden aria-modal="true" role="dialog">
				<div class="oc-modal oc-group-editor-modal">

					<div class="oc-modal-header">
						<div style="flex:1;min-width:0;">
							<input type="text" id="oc-clipart-group-name-input" class="oc-input"
							       placeholder="<?php esc_attr_e( 'Group name', 'overcustomise' ); ?>" />
						</div>
						<button id="oc-clipart-group-modal-close" class="oc-modal-close" type="button">×</button>
					</div>

					<div class="oc-modal-body" style="padding:0;">
						<div class="oc-group-picker-header">
							<span><?php esc_html_e( 'Select clipart for this group', 'overcustomise' ); ?></span>
							<span id="oc-clipart-group-selected-count" class="oc-group-selected-count">0 selected</span>
						</div>
						<div id="oc-clipart-group-picker" class="oc-group-font-picker oc-clipart-group-picker"></div>
					</div>

					<div class="oc-modal-footer">
						<button id="oc-clipart-group-delete-btn" class="oc-btn oc-btn-danger" type="button" style="margin-right:auto;display:none;">
							<?php esc_html_e( 'Delete Group', 'overcustomise' ); ?>
						</button>
						<button id="oc-clipart-group-cancel-btn" class="oc-btn oc-btn-secondary" type="button">
							<?php esc_html_e( 'Cancel', 'overcustomise' ); ?>
						</button>
						<button id="oc-clipart-group-save-btn" class="oc-btn oc-btn-primary" type="button">
							<?php esc_html_e( 'Save Group', 'overcustomise' ); ?>
						</button>
					</div>

				</div>
			</div>

			<!-- Upload modal ─────────────────────────────────────────────────── -->
			<div id="oc-upload-clipart-modal" class="oc-modal-overlay" hidden aria-modal="true" role="dialog">
				<div class="oc-modal oc-clipart-upload-modal">

					<div class="oc-modal-header">
						<h3><?php esc_html_e( 'Upload Clipart', 'overcustomise' ); ?></h3>
						<button id="oc-clipart-upload-modal-close" class="oc-modal-close" type="button">×</button>
					</div>

					<div class="oc-modal-body">
						<div id="oc-clipart-upload-step-1">
							<div id="oc-clipart-drop-zone" class="oc-drop-zone">
								<span class="oc-drop-zone-icon">🖼️</span>
								<span class="oc-drop-zone-title"><?php esc_html_e( 'Drop an image file here, or click to browse', 'overcustomise' ); ?></span>
								<span class="oc-drop-zone-hint">SVG · PNG · JPG · WEBP · GIF</span>
							</div>
							<input type="file" id="oc_clipart_file"
							       accept="image/svg+xml,image/png,image/jpeg,image/webp,image/gif"
							       style="display:none;" />
						</div>

						<div id="oc-clipart-upload-step-2" style="display:none;">
							<div class="oc-clipart-upload-preview">
								<img id="oc-clipart-upload-preview-img" src="" alt="" />
							</div>
				<div class="oc-upload-fields">
					<div class="oc-upload-field">
						<label for="oc_clipart_upload_name"><?php esc_html_e( 'Clipart name', 'overcustomise' ); ?></label>
						<input type="text" id="oc_clipart_upload_name" class="oc-input" required
						       placeholder="<?php esc_attr_e( 'e.g. Star Shape', 'overcustomise' ); ?>" />
					</div>
					<div class="oc-upload-field">
						<label><input type="checkbox" id="oc_clipart_upload_colour_changeable" checked /> <?php esc_html_e( 'Colour changeable', 'overcustomise' ); ?></label>
						<p class="description"><?php esc_html_e( 'Turn off for full-colour artwork that should keep its original colours.', 'overcustomise' ); ?></p>
					</div>
					<div class="oc-upload-field">
						<label><?php esc_html_e( 'Allowed print methods', 'overcustomise' ); ?></label>
						<div class="oc-group-checks">
							<?php foreach ( self::PRINT_METHODS as $method ) : ?>
								<label class="oc-group-check-item"><input type="checkbox" class="oc-clipart-upload-method-check" value="<?php echo esc_attr( $method ); ?>" /> <span><?php echo esc_html( self::print_method_label( $method ) ); ?></span></label>
							<?php endforeach; ?>
						</div>
						<p class="description"><?php esc_html_e( 'No selection means available for all print methods.', 'overcustomise' ); ?></p>
					</div>
				</div>
							<div id="oc-clipart-upload-error" class="oc-upload-error" style="display:none;"></div>
						</div>
					</div>

					<div id="oc-clipart-upload-modal-footer" class="oc-modal-footer" style="display:none;">
						<button id="oc-clipart-upload-back-btn" class="oc-btn oc-btn-secondary" type="button">
							<?php esc_html_e( '← Back', 'overcustomise' ); ?>
						</button>
						<button id="oc-clipart-upload-submit-btn" class="oc-btn oc-btn-primary" type="button"
						        data-label="<?php esc_attr_e( 'Upload Clipart', 'overcustomise' ); ?>">
							<?php esc_html_e( 'Upload Clipart', 'overcustomise' ); ?>
						</button>
					</div>

				</div>
			</div>

		</div>
		<?php
	}

	// ── AJAX: upload clipart ───────────────────────────────────────────────────

	public static function ajax_upload(): void {
		check_ajax_referer( 'oc_clipart_actions', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'overcustomise' ) ] );
		}

		if ( empty( $_FILES['clipart_file'] ) || UPLOAD_ERR_OK !== $_FILES['clipart_file']['error'] ) {
			wp_send_json_error( [ 'message' => __( 'No file received or upload error.', 'overcustomise' ) ] );
		}

		$name = sanitize_text_field( $_POST['name'] ?? '' );
		if ( ! $name ) {
			wp_send_json_error( [ 'message' => __( 'Clipart name is required.', 'overcustomise' ) ] );
		}

		$colour_changeable = ! empty( $_POST['colour_changeable'] );
		$allowed_methods   = self::normalise_print_methods( $_POST['allowed_print_methods'] ?? [] );

		$result = self::do_upload( $_FILES['clipart_file'], $name, $colour_changeable, $allowed_methods );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		self::clear_clipart_cache();
		wp_send_json_success( $result );
	}

	// ── AJAX: convert existing clipart to SVG ──────────────────────────────────

	public static function ajax_convert_svg(): void {
		check_ajax_referer( 'oc_clipart_actions', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'overcustomise' ) ] );
		}

		$id = absint( $_POST['id'] ?? 0 );
		if ( ! $id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid request.', 'overcustomise' ) ] );
		}

		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}oc_clipart WHERE id = %d LIMIT 1",
			$id
		) );

		if ( ! $row || empty( $row->file_path ) || ! file_exists( $row->file_path ) ) {
			wp_send_json_error( [ 'message' => __( 'Clipart file was not found.', 'overcustomise' ) ] );
		}

		if ( 'svg' === strtolower( (string) $row->file_type ) ) {
			wp_send_json_success( self::clipart_response( $id, (string) $row->name, (string) $row->file_path, 'svg', (bool) $row->active, $row ) );
		}

		if ( ! empty( $_FILES['clipart_file'] ) && UPLOAD_ERR_OK === (int) $_FILES['clipart_file']['error'] ) {
			$converted = self::save_uploaded_svg_replacement( $_FILES['clipart_file'], (string) $row->file_path );
		} else {
			$converted = self::convert_file_to_svg( (string) $row->file_path );
		}

		if ( is_wp_error( $converted ) ) {
			wp_send_json_error( [ 'message' => $converted->get_error_message() ] );
		}

		$old_path = (string) $row->file_path;
		$updated  = $wpdb->update(
			"{$wpdb->prefix}oc_clipart",
			[ 'file_path' => $converted, 'file_type' => 'svg' ],
			[ 'id' => $id ],
			[ '%s', '%s' ],
			[ '%d' ]
		);

		if ( false === $updated ) {
			@unlink( $converted );
			wp_send_json_error( [ 'message' => __( 'Could not update clipart record.', 'overcustomise' ) ] );
		}

		if ( $old_path !== $converted && file_exists( $old_path ) ) {
			wp_delete_file( $old_path );
		}

		self::clear_clipart_cache();
		$row->file_type = 'svg';
		wp_send_json_success( self::clipart_response( $id, (string) $row->name, $converted, 'svg', (bool) $row->active, $row ) );
	}

	// ── AJAX: rename clipart ──────────────────────────────────────────────────

	public static function ajax_rename(): void {
		check_ajax_referer( 'oc_clipart_actions', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'overcustomise' ) ] );
		}

		$id   = (int) ( $_POST['id'] ?? 0 );
		$name              = sanitize_text_field( $_POST['name'] ?? '' );
		$colour_changeable = ! empty( $_POST['colour_changeable'] );
		$allowed_methods   = self::normalise_print_methods( $_POST['allowed_print_methods'] ?? [] );

		if ( ! $id || ! $name ) {
			wp_send_json_error( [ 'message' => __( 'Invalid request.', 'overcustomise' ) ] );
		}

		global $wpdb;
		$updated = $wpdb->update(
			"{$wpdb->prefix}oc_clipart",
			[
				'name' => $name,
				'colour_changeable' => (int) $colour_changeable,
				'allowed_print_methods' => self::encode_print_methods( $allowed_methods ),
			],
			[ 'id'   => $id ],
			[ '%s', '%d', '%s' ], [ '%d' ]
		);
		if ( false === $updated ) {
			wp_send_json_error( [ 'message' => __( 'Could not rename clipart.', 'overcustomise' ) ] );
		}

		self::clear_clipart_cache();
		wp_send_json_success( [
			'id' => $id,
			'name' => $name,
			'colourChangeable' => $colour_changeable,
			'allowedPrintMethods' => $allowed_methods,
		] );
	}

	// ── AJAX: clipart group create ────────────────────────────────────────────

	public static function ajax_group_create(): void {
		check_ajax_referer( 'oc_clipart_actions', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'overcustomise' ) ] );
		}

		$name        = sanitize_text_field( $_POST['name'] ?? '' );
		$clipart_ids = array_values( array_filter( array_map( 'intval', (array) ( $_POST['clipart_ids'] ?? [] ) ) ) );
		if ( ! $name ) {
			wp_send_json_error( [ 'message' => __( 'Name is required.', 'overcustomise' ) ] );
		}

		global $wpdb;
		$inserted = $wpdb->insert( "{$wpdb->prefix}oc_clipart_groups", [ 'name' => $name ], [ '%s' ] );
		if ( false === $inserted ) {
			wp_send_json_error( [ 'message' => __( 'Could not create clipart group.', 'overcustomise' ) ] );
		}
		$id = (int) $wpdb->insert_id;
		foreach ( $clipart_ids as $order => $clipart_id ) {
			$inserted = $wpdb->insert(
				"{$wpdb->prefix}oc_clipart_group_items",
				[ 'group_id' => $id, 'clipart_id' => $clipart_id, 'sort_order' => $order ],
				[ '%d', '%d', '%d' ]
			);
			if ( false === $inserted ) {
				$wpdb->delete( "{$wpdb->prefix}oc_clipart_group_items", [ 'group_id' => $id ], [ '%d' ] );
				$wpdb->delete( "{$wpdb->prefix}oc_clipart_groups", [ 'id' => $id ], [ '%d' ] );
				self::clear_clipart_cache();
				wp_send_json_error( [ 'message' => __( 'Could not save clipart group items.', 'overcustomise' ) ] );
			}
		}
		self::clear_clipart_cache();
		wp_send_json_success( [ 'id' => $id, 'name' => $name, 'clipartIds' => $clipart_ids ] );
	}

	// ── AJAX: clipart group update ────────────────────────────────────────────

	public static function ajax_group_update(): void {
		check_ajax_referer( 'oc_clipart_actions', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'overcustomise' ) ] );
		}

		$id          = (int) ( $_POST['id'] ?? 0 );
		$name        = sanitize_text_field( $_POST['name'] ?? '' );
		$clipart_ids = array_filter( array_map( 'intval', (array) ( $_POST['clipart_ids'] ?? [] ) ) );

		if ( ! $id || ! $name ) {
			wp_send_json_error( [ 'message' => __( 'Invalid request.', 'overcustomise' ) ] );
		}

		global $wpdb;
		$updated = $wpdb->update( "{$wpdb->prefix}oc_clipart_groups", [ 'name' => $name ], [ 'id' => $id ], [ '%s' ], [ '%d' ] );
		if ( false === $updated ) {
			wp_send_json_error( [ 'message' => __( 'Could not update clipart group.', 'overcustomise' ) ] );
		}
		$deleted = $wpdb->delete( "{$wpdb->prefix}oc_clipart_group_items", [ 'group_id' => $id ], [ '%d' ] );
		if ( false === $deleted ) {
			wp_send_json_error( [ 'message' => __( 'Could not update clipart group items.', 'overcustomise' ) ] );
		}
		foreach ( array_values( $clipart_ids ) as $order => $clipart_id ) {
			$inserted = $wpdb->insert(
				"{$wpdb->prefix}oc_clipart_group_items",
				[ 'group_id' => $id, 'clipart_id' => $clipart_id, 'sort_order' => $order ],
				[ '%d', '%d', '%d' ]
			);
			if ( false === $inserted ) {
				self::clear_clipart_cache();
				wp_send_json_error( [ 'message' => __( 'Could not save clipart group items.', 'overcustomise' ) ] );
			}
		}
		self::clear_clipart_cache();
		wp_send_json_success( [ 'id' => $id, 'name' => $name, 'clipartIds' => array_values( $clipart_ids ) ] );
	}

	// ── AJAX: clipart group delete ────────────────────────────────────────────

	public static function ajax_group_delete(): void {
		check_ajax_referer( 'oc_clipart_actions', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'overcustomise' ) ] );
		}

		$id = (int) ( $_POST['id'] ?? 0 );
		if ( ! $id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid request.', 'overcustomise' ) ] );
		}

		global $wpdb;
		$deleted_items = $wpdb->delete( "{$wpdb->prefix}oc_clipart_group_items", [ 'group_id' => $id ], [ '%d' ] );
		$deleted_group = $wpdb->delete( "{$wpdb->prefix}oc_clipart_groups",      [ 'id'       => $id ], [ '%d' ] );
		if ( false === $deleted_items || false === $deleted_group ) {
			wp_send_json_error( [ 'message' => __( 'Could not delete clipart group.', 'overcustomise' ) ] );
		}
		self::clear_clipart_cache();
		wp_send_json_success();
	}

	// ── GET action handlers ────────────────────────────────────────────────────

	private function handle_toggle(): void {
		$id    = isset( $_GET['id'] )    ? (int) $_GET['id']    : 0;
		$state = isset( $_GET['state'] ) ? (int) $_GET['state'] : 0;

		if ( ! $id || ! isset( $_GET['_wpnonce'] )
		     || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'oc_clipart_toggle_' . $id )
		) {
			wp_die( esc_html__( 'Security check failed.', 'overcustomise' ) );
		}

		global $wpdb;
		$updated = $wpdb->update( "{$wpdb->prefix}oc_clipart", [ 'active' => (int) (bool) $state ], [ 'id' => $id ], [ '%d' ], [ '%d' ] );
		if ( false === $updated ) {
			wp_die( esc_html__( 'Could not update clipart.', 'overcustomise' ) );
		}
		self::clear_clipart_cache();
		wp_safe_redirect( admin_url( 'admin.php?page=overcustomise-clipart' ) );
		exit;
	}

	private function handle_delete(): void {
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;

		if ( ! $id || ! isset( $_GET['_wpnonce'] )
		     || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'oc_clipart_delete_' . $id )
		) {
			wp_die( esc_html__( 'Security check failed.', 'overcustomise' ) );
		}

		global $wpdb;

		// Capture the physical file path before deleting the database row.
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT file_path FROM {$wpdb->prefix}oc_clipart WHERE id = %d LIMIT 1",
			$id
		) );

		$deleted_items   = $wpdb->delete( "{$wpdb->prefix}oc_clipart_group_items", [ 'clipart_id' => $id ], [ '%d' ] );
		$deleted_clipart = $wpdb->delete( "{$wpdb->prefix}oc_clipart", [ 'id' => $id ], [ '%d' ] );
		if ( false === $deleted_items || false === $deleted_clipart ) {
			wp_die( esc_html__( 'Could not delete clipart.', 'overcustomise' ) );
		}

		if ( $row && $row->file_path && file_exists( $row->file_path ) ) {
			// Only unlink files that live under wp-content/uploads.
			$upload    = wp_upload_dir();
			$base_real = realpath( $upload['basedir'] );
			$path_real = realpath( $row->file_path );
			if ( $base_real && $path_real && 0 === strpos( $path_real, $base_real ) ) {
				wp_delete_file( $path_real );
			}
		}
		self::clear_clipart_cache();
		wp_safe_redirect( admin_url( 'admin.php?page=overcustomise-clipart' ) );
		exit;
	}

	// ── Private: save uploaded file to disk + DB ──────────────────────────────

	private static function do_upload( array $file, string $name, bool $colour_changeable = true, array $allowed_methods = [] ): array|\WP_Error {
		$allowed_types = [
			'svg'  => 'image/svg+xml',
			'png'  => 'image/png',
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'webp' => 'image/webp',
			'gif'  => 'image/gif',
		];

		$original_name = $file['name'];
		$tmp           = $file['tmp_name'];

		$file_type = wp_check_filetype( $original_name );
		$ext       = strtolower( $file_type['ext'] ?? '' );

		if ( ! $ext || ! array_key_exists( $ext, $allowed_types ) ) {
			return new \WP_Error( 'invalid_type', __( 'Unsupported file type. Allowed: SVG, PNG, JPG, WEBP, GIF.', 'overcustomise' ) );
		}

		// Prepare target directory.
		$upload_dir = wp_upload_dir();
		if ( ! empty( $upload_dir['error'] ) ) {
			return new \WP_Error( 'upload_dir_error', (string) $upload_dir['error'] );
		}
		$target_dir = $upload_dir['basedir'] . '/' . self::CLIPART_SUBDIR . '/';

		if ( ! wp_mkdir_p( $target_dir ) ) {
			return new \WP_Error( 'mkdir_failed', __( 'Could not create upload directory.', 'overcustomise' ) );
		}

		// Build unique SVG filename. Raster uploads are converted before storage.
		$safe_name = sanitize_file_name( preg_replace( '/\.[^.]+$/', '.svg', $original_name ) );
		$dest      = $target_dir . $safe_name;
		if ( file_exists( $dest ) ) {
			$dest = $target_dir . uniqid( '', true ) . '-' . $safe_name;
		}

		// Save as SVG.
		if ( 'svg' === $ext ) {
			if ( ! is_uploaded_file( $tmp ) ) {
				return new \WP_Error( 'bad_upload', __( 'Invalid upload.', 'overcustomise' ) );
			}
			$raw = file_get_contents( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			if ( false === $raw ) {
				return new \WP_Error( 'read_failed', __( 'Could not read uploaded file.', 'overcustomise' ) );
			}
			try {
				$sanitised = OC_SVG_Sanitiser::sanitise( $raw );
			} catch ( \InvalidArgumentException $e ) {
				return new \WP_Error( 'unsafe_svg', $e->getMessage() );
			}
			if ( false === file_put_contents( $dest, $sanitised ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
				return new \WP_Error( 'write_failed', __( 'Could not save file.', 'overcustomise' ) );
			}
		} else {
			if ( ! is_uploaded_file( $tmp ) ) {
				return new \WP_Error( 'bad_upload', __( 'Invalid upload.', 'overcustomise' ) );
			}
			$converted = self::raster_to_svg( $tmp, $dest, $ext );
			if ( is_wp_error( $converted ) ) {
				return $converted;
			}
		}

		// Store record in DB.
		global $wpdb;
		$inserted = $wpdb->insert(
			"{$wpdb->prefix}oc_clipart",
			[
				'name'      => $name,
				'file_path' => $dest,
				'file_type' => 'svg',
				'colour_changeable' => (int) $colour_changeable,
				'allowed_print_methods' => self::encode_print_methods( $allowed_methods ),
				'active'    => 1,
			],
			[ '%s', '%s', '%s', '%d', '%s', '%d' ]
		);
		$id = (int) $wpdb->insert_id;
		if ( false === $inserted || $id <= 0 ) {
			@unlink( $dest );
			return new \WP_Error( 'db_error', __( 'Could not save clipart record.', 'overcustomise' ) );
		}

		return [
			'id'        => $id,
			'name'      => $name,
				'fileType'   => 'svg',
				'canConvert' => false,
			'colourChangeable' => $colour_changeable,
			'allowedPrintMethods' => $allowed_methods,
			'url'       => self::get_clipart_url( $dest ),
			'active'    => true,
			'toggleUrl' => wp_nonce_url(
				admin_url( 'admin.php?page=overcustomise-clipart&action=toggle&id=' . $id . '&state=0' ),
				'oc_clipart_toggle_' . $id
			),
			'deleteUrl' => wp_nonce_url(
				admin_url( 'admin.php?page=overcustomise-clipart&action=delete&id=' . $id ),
				'oc_clipart_delete_' . $id
			),
		];
	}

	private static function clipart_response( int $id, string $name, string $path, string $file_type, bool $active, ?object $row = null ): array {
		return [
			'id'        => $id,
			'name'      => $name,
			'fileType'   => $file_type,
			'canConvert' => 'svg' !== strtolower( $file_type ),
			'colourChangeable' => $row ? self::clipart_colour_changeable( $row ) : true,
			'allowedPrintMethods' => $row ? self::clipart_allowed_print_methods( $row ) : [],
			'url'       => self::get_clipart_url( $path ),
			'active'    => $active,
			'toggleUrl' => wp_nonce_url(
				admin_url( 'admin.php?page=overcustomise-clipart&action=toggle&id=' . $id . '&state=' . ( $active ? '0' : '1' ) ),
				'oc_clipart_toggle_' . $id
			),
			'deleteUrl' => wp_nonce_url(
				admin_url( 'admin.php?page=overcustomise-clipart&action=delete&id=' . $id ),
				'oc_clipart_delete_' . $id
			),
		];
	}

	private static function print_method_label( string $method ): string {
		return match ( $method ) {
			'engraving' => __( 'Engraving', 'overcustomise' ),
			'uv' => __( 'UV Printing', 'overcustomise' ),
			'embroidery' => __( 'Embroidery', 'overcustomise' ),
			'sublimation' => __( 'Sublimation', 'overcustomise' ),
			default => ucfirst( $method ),
		};
	}

	private static function normalise_print_methods( mixed $methods ): array {
		$methods = is_array( $methods ) ? $methods : [ $methods ];
		return array_values( array_intersect( self::PRINT_METHODS, array_map( 'sanitize_key', $methods ) ) );
	}

	private static function encode_print_methods( array $methods ): string {
		$methods = self::normalise_print_methods( $methods );
		return empty( $methods ) ? '' : wp_json_encode( $methods );
	}

	private static function clipart_allowed_print_methods( object $row ): array {
		$raw = isset( $row->allowed_print_methods ) ? (string) $row->allowed_print_methods : '';
		if ( '' === trim( $raw ) ) {
			return [];
		}
		$decoded = json_decode( $raw, true );
		return self::normalise_print_methods( is_array( $decoded ) ? $decoded : explode( ',', $raw ) );
	}

	private static function clipart_colour_changeable( object $row ): bool {
		return ! property_exists( $row, 'colour_changeable' ) || (bool) $row->colour_changeable;
	}

	private static function convert_file_to_svg( string $source_path ): string|\WP_Error {
		$ext = strtolower( pathinfo( $source_path, PATHINFO_EXTENSION ) );
		if ( 'svg' === $ext ) {
			return $source_path;
		}

		$dest = preg_replace( '/\.[^.]+$/', '.svg', $source_path );
		if ( ! is_string( $dest ) || '' === $dest || $dest === $source_path ) {
			$dest = $source_path . '.svg';
		}
		if ( file_exists( $dest ) ) {
			$dest = dirname( $dest ) . '/' . uniqid( '', true ) . '-' . basename( $dest );
		}

		return self::raster_to_svg( $source_path, $dest, $ext );
	}

	private static function save_uploaded_svg_replacement( array $file, string $old_path ): string|\WP_Error {
		if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new \WP_Error( 'bad_upload', __( 'Invalid upload.', 'overcustomise' ) );
		}

		$raw = file_get_contents( $file['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $raw ) {
			return new \WP_Error( 'read_failed', __( 'Could not read converted SVG.', 'overcustomise' ) );
		}

		try {
			$sanitised = OC_SVG_Sanitiser::sanitise( $raw );
		} catch ( \InvalidArgumentException $e ) {
			return new \WP_Error( 'unsafe_svg', $e->getMessage() );
		}

		$dest = preg_replace( '/\.[^.]+$/', '.svg', $old_path );
		if ( ! is_string( $dest ) || '' === $dest ) {
			$dest = $old_path . '.svg';
		}
		if ( file_exists( $dest ) && wp_normalize_path( $dest ) !== wp_normalize_path( $old_path ) ) {
			$dest = dirname( $dest ) . '/' . uniqid( '', true ) . '-' . basename( $dest );
		}

		if ( false === file_put_contents( $dest, $sanitised ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			return new \WP_Error( 'write_failed', __( 'Could not save converted SVG.', 'overcustomise' ) );
		}

		return $dest;
	}

	private static function raster_to_svg( string $source_path, string $dest, string $ext ): string|\WP_Error {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			return new \WP_Error( 'gd_missing', __( 'Server image processing is not available.', 'overcustomise' ) );
		}
		if ( ! file_exists( $source_path ) ) {
			return new \WP_Error( 'not_found', __( 'Source image was not found.', 'overcustomise' ) );
		}

		$image_info = @getimagesize( $source_path );
		if ( ! $image_info || empty( $image_info[0] ) || empty( $image_info[1] ) ) {
			return new \WP_Error( 'not_image', __( 'File is not a valid image.', 'overcustomise' ) );
		}

		$src = match ( $ext ) {
			'png'  => function_exists( 'imagecreatefrompng' ) ? @imagecreatefrompng( $source_path ) : false,
			'jpg', 'jpeg' => function_exists( 'imagecreatefromjpeg' ) ? @imagecreatefromjpeg( $source_path ) : false,
			'webp' => function_exists( 'imagecreatefromwebp' ) ? @imagecreatefromwebp( $source_path ) : false,
			'gif'  => function_exists( 'imagecreatefromgif' ) ? @imagecreatefromgif( $source_path ) : false,
			default => false,
		};

		if ( ! $src ) {
			return new \WP_Error( 'read_failed', __( 'Could not read image for conversion.', 'overcustomise' ) );
		}

		$width = (int) $image_info[0];
		$height = (int) $image_info[1];
		$canvas = imagecreatetruecolor( $width, $height );
		imagealphablending( $canvas, false );
		imagesavealpha( $canvas, true );
		$transparent = imagecolorallocatealpha( $canvas, 255, 255, 255, 127 );
		imagefilledrectangle( $canvas, 0, 0, $width, $height, $transparent );
		imagecopy( $canvas, $src, 0, 0, 0, 0, $width, $height );

		for ( $y = 0; $y < $height; $y++ ) {
			for ( $x = 0; $x < $width; $x++ ) {
				$rgba = imagecolorat( $canvas, $x, $y );
				$a = ( $rgba & 0x7F000000 ) >> 24;
				$r = ( $rgba >> 16 ) & 0xFF;
				$g = ( $rgba >> 8 ) & 0xFF;
				$b = $rgba & 0xFF;
				if ( $a < 127 && $r >= 245 && $g >= 245 && $b >= 245 && ( max( $r, $g, $b ) - min( $r, $g, $b ) ) <= 12 ) {
					imagesetpixel( $canvas, $x, $y, $transparent );
				}
			}
		}

		ob_start();
		imagepng( $canvas );
		$png = ob_get_clean();
		imagedestroy( $src );
		imagedestroy( $canvas );

		if ( ! is_string( $png ) || '' === $png ) {
			return new \WP_Error( 'convert_failed', __( 'Could not convert image to SVG.', 'overcustomise' ) );
		}

		$svg = sprintf(
			'<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="%1$d" height="%2$d" viewBox="0 0 %1$d %2$d" data-oc-converted="raster"><image width="%1$d" height="%2$d" href="data:image/png;base64,%3$s" xlink:href="data:image/png;base64,%3$s"/></svg>',
			$width,
			$height,
			base64_encode( $png )
		);

		if ( false === file_put_contents( $dest, $svg ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			return new \WP_Error( 'write_failed', __( 'Could not save converted SVG.', 'overcustomise' ) );
		}

		return $dest;
	}
}
