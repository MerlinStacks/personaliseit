<?php
/**
 * Products page — two tabs:
 *   1. Products: assign designs to WC products / variants.
 *   2. Designs:  create and manage reusable customisation design templates.
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

class OC_Admin_Products {

	// ── AJAX ──────────────────────────────────────────────────────────────────

	public static function register_ajax(): void {
		add_action( 'wp_ajax_oc_assign_design', [ self::class, 'ajax_assign_design' ] );
	}

	public static function ajax_assign_design(): void {
		check_ajax_referer( 'oc-products-nonce', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'overcustomise' ) ] );
		}

		$product_id = (int) ( $_POST['product_id'] ?? 0 );
		$variant_id = (int) ( $_POST['variant_id'] ?? 0 );
		$design_id  = (int) ( $_POST['design_id']  ?? 0 );

		if ( ! $product_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid product.', 'overcustomise' ) ] );
		}

		if ( $design_id ) {
			OC_DB::upsert_assignment( $product_id, $variant_id, $design_id );
		} else {
			OC_DB::delete_assignment( $product_id, $variant_id );
		}

		wp_send_json_success();
	}

	// ── Router ────────────────────────────────────────────────────────────────

	public function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'overcustomise' ) );
		}

		$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : '';

		if ( 'duplicate' === $action ) {
			$this->handle_design_duplicate();
		} elseif ( 'edit' === $action ) {
			$this->render_design_edit();
		} elseif ( 'delete' === $action ) {
			$this->handle_design_delete();
		} else {
			$this->render_main();
		}
	}

	// ── Main two-tab page ─────────────────────────────────────────────────────

	private function render_main(): void {
		$tab        = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		$active_tab = 'designs' === $tab ? 'designs' : 'products';
		?>
		<div class="wrap oc-page">

			<div class="oc-page-header">
				<div class="oc-page-header-left">
					<h1 class="oc-page-title"><?php esc_html_e( 'Products', 'overcustomise' ); ?></h1>
					<p class="oc-page-subtitle"><?php esc_html_e( 'Assign designs to products and manage design templates.', 'overcustomise' ); ?></p>
				</div>
				<div class="oc-page-header-right">
					<?php if ( 'designs' === $active_tab ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=overcustomise-products&tab=designs&action=edit' ) ); ?>"
						   class="oc-btn oc-btn-primary">
							+ <?php esc_html_e( 'Add Design', 'overcustomise' ); ?>
						</a>
					<?php endif; ?>
				</div>
			</div>

			<!-- Tab bar -->
			<div class="oc-tabs-bar">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=overcustomise-products&tab=products' ) ); ?>"
				   class="oc-tab<?php echo 'products' === $active_tab ? ' oc-tab--active' : ''; ?>">
					<?php esc_html_e( 'Products', 'overcustomise' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=overcustomise-products&tab=designs' ) ); ?>"
				   class="oc-tab<?php echo 'designs' === $active_tab ? ' oc-tab--active' : ''; ?>">
					<?php esc_html_e( 'Designs', 'overcustomise' ); ?>
				</a>
			</div>

			<?php if ( 'products' === $active_tab ) : ?>
				<?php $this->render_products_tab(); ?>
			<?php else : ?>
				<?php $this->render_designs_tab(); ?>
			<?php endif; ?>

		</div>
		<?php
	}

	// ── Tab 1: Products ───────────────────────────────────────────────────────

	private function render_products_tab(): void {
		// Load active designs for the assignment dropdown.
		$designs    = OC_DB::get_designs( true );
		$assign_map = OC_DB::get_all_assignments();

		// Load all published WC products.
		$wc_products = wc_get_products( [
			'limit'   => -1,
			'type'    => [ 'simple', 'variable' ],
			'status'  => 'publish',
			'orderby' => 'name',
			'order'   => 'ASC',
			'return'  => 'objects',
		] );

		$nonce = wp_create_nonce( 'oc-products-nonce' );
		?>
		<script>
		var ocProductsData = {
			ajaxUrl: <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>,
			nonce:   <?php echo wp_json_encode( $nonce ); ?>
		};
		</script>

		<div class="oc-card">
			<div class="oc-card-header">
				<h2><?php esc_html_e( 'Products & Variants', 'overcustomise' ); ?></h2>
				<input type="search" id="oc-product-search" class="oc-input oc-products-search"
				       placeholder="<?php esc_attr_e( 'Filter products…', 'overcustomise' ); ?>" />
			</div>

			<?php if ( empty( $wc_products ) ) : ?>
				<div class="oc-empty">
					<span class="oc-empty-icon">🛍️</span>
					<h3><?php esc_html_e( 'No products found', 'overcustomise' ); ?></h3>
					<p><?php esc_html_e( 'Publish some WooCommerce products first.', 'overcustomise' ); ?></p>
				</div>
			<?php elseif ( empty( $designs ) ) : ?>
				<div class="oc-empty">
					<span class="oc-empty-icon">📐</span>
					<h3><?php esc_html_e( 'No designs yet', 'overcustomise' ); ?></h3>
					<p>
						<?php esc_html_e( 'Create a design first, then you can assign it here.', 'overcustomise' ); ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=overcustomise-products&tab=designs&action=edit' ) ); ?>">
							<?php esc_html_e( 'Add Design →', 'overcustomise' ); ?>
						</a>
					</p>
				</div>
			<?php else : ?>
				<div class="oc-table-wrap">
					<table class="oc-table" id="oc-products-table">
						<thead>
							<tr>
								<th style="width:40%"><?php esc_html_e( 'Product / Variant', 'overcustomise' ); ?></th>
								<th><?php esc_html_e( 'SKU', 'overcustomise' ); ?></th>
								<th><?php esc_html_e( 'Assigned Design', 'overcustomise' ); ?></th>
								<th style="width:60px;"></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $wc_products as $product ) :
								$pid        = $product->get_id();
								$pname      = $product->get_name();
								$psku       = $product->get_sku();
								$is_var     = $product->is_type( 'variable' );
								$search_str = strtolower( $pname . ' ' . $psku );
								?>
								<?php if ( $is_var ) : ?>
									<?php $parent_assigned = $assign_map[ $pid ][0] ?? 0; ?>
									<!-- Variable product parent row with all-variants dropdown -->
									<tr class="oc-product-row oc-product-row--parent" data-search="<?php echo esc_attr( $search_str ); ?>">
										<td class="oc-col-primary" colspan="2">
											<strong><?php echo esc_html( $pname ); ?></strong>
											<?php if ( $psku ) : ?>
												<small style="color:var(--oc-gray-400);margin-left:6px;"><?php echo esc_html( $psku ); ?></small>
											<?php endif; ?>
										</td>
										<td>
											<?php $this->render_design_select( $designs, $pid, 0, $parent_assigned ); ?>
											<small style="color:var(--oc-gray-500);display:block;margin-top:3px;font-size:11px;"><?php esc_html_e( 'All variants (default)', 'overcustomise' ); ?></small>
										</td>
										<td><span class="oc-assign-status" aria-live="polite"></span></td>
									</tr>
									<!-- One row per variant -->
									<?php foreach ( $product->get_children() as $vid ) :
										$variation = wc_get_product( $vid );
										if ( ! $variation ) continue;
										$vsku   = $variation->get_sku();
										$vattrs = array_filter( $variation->get_variation_attributes() );
										$vlabel = implode( ' / ', array_map( 'ucfirst', $vattrs ) ) ?: '#' . $vid;
										$vsearch = strtolower( $pname . ' ' . $vlabel . ' ' . $vsku );
										$vassigned = $assign_map[ $pid ][ $vid ] ?? 0;
										?>
										<tr class="oc-product-row oc-product-row--variant" data-search="<?php echo esc_attr( $vsearch ); ?>">
											<td class="oc-col-variant"><?php echo esc_html( $vlabel ); ?></td>
											<td><span class="oc-code"><?php echo esc_html( $vsku ); ?></span></td>
											<td>
												<?php $this->render_design_select( $designs, $pid, $vid, $vassigned ); ?>
											</td>
											<td><span class="oc-assign-status" aria-live="polite"></span></td>
										</tr>
									<?php endforeach; ?>
								<?php else : ?>
									<!-- Simple product row -->
									<?php $assigned = $assign_map[ $pid ][0] ?? 0; ?>
									<tr class="oc-product-row" data-search="<?php echo esc_attr( $search_str ); ?>">
										<td class="oc-col-primary">
											<strong><?php echo esc_html( $pname ); ?></strong>
										</td>
										<td><span class="oc-code"><?php echo esc_html( $psku ); ?></span></td>
										<td>
											<?php $this->render_design_select( $designs, $pid, 0, $assigned ); ?>
										</td>
										<td><span class="oc-assign-status" aria-live="polite"></span></td>
									</tr>
								<?php endif; ?>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</div>

		<script>
		( function () {
			// Search filter.
			var searchInput = document.getElementById( 'oc-product-search' );
			if ( searchInput ) {
				searchInput.addEventListener( 'input', function () {
					var q = this.value.toLowerCase();
					document.querySelectorAll( '.oc-product-row' ).forEach( function ( row ) {
						var match = ! q || ( row.dataset.search || '' ).includes( q );
						row.style.display = match ? '' : 'none';
					} );
				} );
			}

			// AJAX assignment save.
			document.querySelectorAll( '.oc-design-assign-select' ).forEach( function ( sel ) {
				sel.addEventListener( 'change', function () {
					var status = this.closest( 'tr' ).querySelector( '.oc-assign-status' );
					if ( status ) { status.textContent = 'Saving\u2026'; status.className = 'oc-assign-status oc-assign-status--saving'; }
					sel.disabled = true;

					var body = new URLSearchParams( {
						action:     'oc_assign_design',
						nonce:      ocProductsData.nonce,
						product_id: this.dataset.productId,
						variant_id: this.dataset.variantId,
						design_id:  this.value,
					} );

					fetch( ocProductsData.ajaxUrl, { method: 'POST', body: body } )
						.then( function ( r ) { return r.json(); } )
						.then( function ( json ) {
							sel.disabled = false;
							if ( status ) {
								status.textContent = json.success ? 'Saved' : 'Error';
								status.className = 'oc-assign-status ' + ( json.success ? 'oc-assign-status--saved' : 'oc-assign-status--error' );
								setTimeout( function () { status.textContent = ''; status.className = 'oc-assign-status'; }, 2000 );
							}
						} )
						.catch( function () {
							sel.disabled = false;
							if ( status ) { status.textContent = 'Error'; status.className = 'oc-assign-status oc-assign-status--error'; }
						} );
				} );
			} );
		} )();
		</script>
		<?php
	}

	/** Render a design assignment <select> for one product/variant row. */
	private function render_design_select( array $designs, int $product_id, int $variant_id, int $assigned_id ): void {
		?>
		<select class="oc-design-assign-select oc-select"
		        data-product-id="<?php echo esc_attr( $product_id ); ?>"
		        data-variant-id="<?php echo esc_attr( $variant_id ); ?>"
		        style="min-width:200px;max-width:300px;">
			<option value="0"><?php esc_html_e( '— No Design —', 'overcustomise' ); ?></option>
			<?php foreach ( $designs as $design ) : ?>
				<option value="<?php echo esc_attr( $design->id ); ?>"
				        <?php selected( $assigned_id, $design->id ); ?>>
					<?php echo esc_html( $design->name ?: __( 'Untitled Design #', 'overcustomise' ) . $design->id ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	// ── Tab 2: Designs list ───────────────────────────────────────────────────

	private function render_designs_tab(): void {
		if ( isset( $_GET['duplicated'] ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Design duplicated.', 'overcustomise' ) . '</p></div>';
	}
	if ( isset( $_GET['saved'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Design saved.', 'overcustomise' ) . '</p></div>';
		}
		if ( isset( $_GET['deleted'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Design deleted.', 'overcustomise' ) . '</p></div>';
		}

		$designs = OC_DB::get_designs_with_area_counts();
		?>
		<div class="oc-card">
			<div class="oc-card-header">
				<h2><?php esc_html_e( 'Designs', 'overcustomise' ); ?></h2>
				<span style="font-size:12px;color:var(--oc-gray-400);">
					<?php echo esc_html( count( $designs ) ); ?> <?php echo esc_html( 1 === count( $designs ) ? __( 'design', 'overcustomise' ) : __( 'designs', 'overcustomise' ) ); ?>
				</span>
			</div>

			<?php if ( empty( $designs ) ) : ?>
				<div class="oc-empty">
					<span class="oc-empty-icon">📐</span>
					<h3><?php esc_html_e( 'No designs yet', 'overcustomise' ); ?></h3>
					<p><?php esc_html_e( 'Create your first design to enable the customiser on products.', 'overcustomise' ); ?></p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=overcustomise-products&tab=designs&action=edit' ) ); ?>"
					   class="oc-btn oc-btn-primary">
						+ <?php esc_html_e( 'Add Design', 'overcustomise' ); ?>
					</a>
				</div>
			<?php else : ?>
				<div class="oc-table-wrap">
					<table class="oc-table">
						<thead>
							<tr>
								<th style="width:40%"><?php esc_html_e( 'Name', 'overcustomise' ); ?></th>
								<th><?php esc_html_e( 'Type', 'overcustomise' ); ?></th>
								<th><?php esc_html_e( 'Flat Rate', 'overcustomise' ); ?></th>
								<th><?php esc_html_e( 'Print Areas', 'overcustomise' ); ?></th>
								<th><?php esc_html_e( 'Status', 'overcustomise' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'overcustomise' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $designs as $design ) : ?>
								<tr>
									<td class="oc-col-primary">
										<strong><?php echo esc_html( $design->name ?: __( 'Untitled Design #', 'overcustomise' ) . $design->id ); ?></strong>
									</td>
									<td><?php echo esc_html( 'text_only' === $design->custom_type ? __( 'Text Only', 'overcustomise' ) : __( 'Photo + Text', 'overcustomise' ) ); ?></td>
									<td><?php echo $design->flat_rate > 0 ? '$' . esc_html( number_format( (float) $design->flat_rate, 2 ) ) : '<span style="color:var(--oc-gray-400);">—</span>'; ?></td>
									<td>
										<span class="oc-badge" style="background:var(--oc-primary-light);color:var(--oc-primary);">
											<?php echo esc_html( $design->area_count ); ?> <?php echo esc_html( 1 === (int) $design->area_count ? __( 'area', 'overcustomise' ) : __( 'areas', 'overcustomise' ) ); ?>
										</span>
									</td>
									<td>
										<span class="oc-badge <?php echo $design->active ? 'oc-badge-active' : 'oc-badge-inactive'; ?>">
											<?php echo $design->active ? esc_html__( 'Active', 'overcustomise' ) : esc_html__( 'Inactive', 'overcustomise' ); ?>
										</span>
									</td>
									<td>
										<div class="oc-table-actions">
											<a href="<?php echo esc_url( admin_url( 'admin.php?page=overcustomise-products&tab=designs&action=edit&id=' . (int) $design->id ) ); ?>"
											   class="oc-btn oc-btn-secondary oc-btn-sm">
												<?php esc_html_e( 'Edit', 'overcustomise' ); ?>
											</a>
											<a href="<?php echo esc_url( wp_nonce_url(
												admin_url( 'admin.php?page=overcustomise-products&tab=designs&action=delete&id=' . (int) $design->id ),
												'oc_delete_design_' . $design->id
											) ); ?>"
											   onclick="return confirm('<?php esc_attr_e( 'Delete this design? Products assigned to it will be unassigned.', 'overcustomise' ); ?>');"
											   class="oc-btn oc-btn-danger oc-btn-sm">
												<?php esc_html_e( 'Delete', 'overcustomise' ); ?>
											</a>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	// ── Design edit form ──────────────────────────────────────────────────────

	private function render_design_edit(): void {
		if ( isset( $_POST['oc_design_nonce'] ) ) {
			$saved_id = $this->handle_design_save();
			if ( $saved_id ) {
				wp_safe_redirect( admin_url( 'admin.php?page=overcustomise-products&tab=designs&action=edit&id=' . $saved_id . '&saved=1' ) );
				exit;
			}
		}

		$id     = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		$design = $id > 0 ? OC_DB::get_design( $id ) : null;
		$areas  = $id > 0 ? OC_DB::get_design_print_areas( $id ) : [];

		// Build areas JSON for JS.
		$areas_js = array_map( function ( $area ) {
			return [
				'id'        => (int) $area->id,
				'label'     => $area->label,
				'method'    => $area->print_method,
				'mockupId'  => (int) $area->mockup_attachment_id,
				'mockupUrl' => $area->mockup_attachment_id
					? ( wp_get_attachment_image_url( (int) $area->mockup_attachment_id, 'large' ) ?: '' )
					: '',
				'x'          => (int) $area->canvas_x,
				'y'          => (int) $area->canvas_y,
				'w'          => (int) $area->canvas_w,
				'h'          => (int) $area->canvas_h,
				'sortOrder'  => (int) $area->sort_order,
				'visible'    => (bool) $area->visible,
				'locked'     => (bool) $area->locked,
			];
		}, $areas );

		wp_enqueue_media();
		wp_enqueue_script(
			'oc-products-page',
			OC_ASSETS_URL . 'admin/products-page.js',
			[ 'wp-util' ],
			OC_VERSION,
			true
		);
		// Build layers JSON for JS.
		$all_layers = $id > 0 ? OC_DB::get_design_layers( $id ) : [];
		$layers_js  = array_map( function ( $l ) {
			return [
				'id'        => (int) $l->id,
				'areaId'    => (int) $l->area_id,
				'type'      => $l->type,
				'label'     => $l->label,
				'x'         => (int) $l->x,
				'y'         => (int) $l->y,
				'w'         => (int) $l->w,
				'h'         => (int) $l->h,
				'sortOrder' => (int) $l->sort_order,
				'visible'   => (bool) $l->visible,
				'locked'    => (bool) $l->locked,
				'settings'  => $l->settings ? json_decode( $l->settings, true ) : [],
			];
		}, $all_layers );

		wp_localize_script( 'oc-products-page', 'ocProductsData', [
			'areas'        => $areas_js,
			'layers'       => $layers_js,
			'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
			'nonce'        => wp_create_nonce( 'oc-products-nonce' ),
			'mediaTitle'   => __( 'Select Mockup Image', 'overcustomise' ),
			'mediaBtn'     => __( 'Use as Mockup', 'overcustomise' ),
			'fontGroups'    => array_map( function ( $g ) { return [ 'id' => (int) $g->id, 'name' => $g->name ]; }, OC_DB::get_font_groups() ),
			'colourGroups'  => array_map( function ( $g ) { return [ 'id' => (int) $g->id, 'name' => $g->name ]; }, OC_DB::get_colour_groups() ),
			'clipartGroups' => array_map( function ( $g ) { return [ 'id' => (int) $g->id, 'name' => $g->name ]; }, OC_DB::get_clipart_groups() ),
			'methodLabels' => [
				'engraving'   => __( 'Engraving', 'overcustomise' ),
				'uv'          => __( 'UV Printing', 'overcustomise' ),
				'embroidery'  => __( 'Embroidery', 'overcustomise' ),
				'sublimation' => __( 'Sublimation', 'overcustomise' ),
			],
		] );
		?>
		<div class="wrap oc-page oc-design-editor-page">

			<!-- Topbar -->
			<div class="oc-design-editor-topbar">
				<div class="oc-breadcrumb" style="flex:1;min-width:0;">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=overcustomise-products&tab=designs' ) ); ?>"><?php esc_html_e( 'Designs', 'overcustomise' ); ?></a>
					<span class="oc-breadcrumb-sep">›</span>
					<input type="text" name="oc_design_name" id="oc_design_name" form="oc-design-form"
					       class="oc-design-name-input" required
					       value="<?php echo esc_attr( $design ? $design->name : '' ); ?>"
					       placeholder="<?php esc_attr_e( 'Design name…', 'overcustomise' ); ?>" />
				</div>
				<div style="display:flex;align-items:center;gap:14px;">
					<label class="oc-toggle-label" style="margin:0;font-size:12px;">
						<span class="oc-toggle">
							<input type="checkbox" name="oc_active" value="1" form="oc-design-form"
							       <?php checked( $design ? $design->active : 1, 1 ); ?> />
							<span class="oc-toggle-slider"></span>
						</span>
						<?php esc_html_e( 'Active', 'overcustomise' ); ?>
					</label>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=overcustomise-products&tab=designs' ) ); ?>" class="oc-btn oc-btn-secondary"><?php esc_html_e( 'Cancel', 'overcustomise' ); ?></a>
					<button type="submit" form="oc-design-form" class="oc-btn oc-btn-primary"><?php esc_html_e( 'Save Design', 'overcustomise' ); ?></button>
				</div>
			</div>

			<form method="post" id="oc-design-form">
				<?php wp_nonce_field( 'oc_save_design', 'oc_design_nonce' ); ?>
				<input type="hidden" name="oc_design_id" value="<?php echo esc_attr( $id ); ?>" />
				<!-- JS serialises areas here before submit -->
				<div id="oc-hidden-fields"></div>

				<div class="oc-design-editor">

					<!-- LEFT: design settings + areas list -->
					<div class="oc-editor-left">

						<div class="oc-editor-section oc-editor-section--grow">
							<div class="oc-editor-section-header">
								<h3><?php esc_html_e( 'Print Areas', 'overcustomise' ); ?></h3>
								<button type="button" id="oc-add-area-btn" class="oc-btn oc-btn-primary oc-btn-sm">
									+ <?php esc_html_e( 'Add', 'overcustomise' ); ?>
								</button>
							</div>
							<div id="oc-areas-list"></div>
							<div id="oc-areas-empty" class="oc-editor-empty-hint" style="display:none;">
								<p><?php esc_html_e( 'No print areas yet.', 'overcustomise' ); ?></p>
							</div>
						</div>

						<!-- Area settings -->
						<div class="oc-left-area-props">
							<div id="oc-area-no-sel" class="oc-editor-empty-hint">
								<p><?php esc_html_e( 'Select a print area above to edit its settings.', 'overcustomise' ); ?></p>
							</div>
							<div id="oc-area-props-inner" style="display:none;">
								<div class="oc-editor-section-header" style="padding-bottom:10px;">
									<div style="display:flex;align-items:center;gap:6px;">
										<span id="oc-right-area-color" class="oc-area-dot" style="background:#4f46e5;width:10px;height:10px;"></span>
										<h3><?php esc_html_e( 'Area Settings', 'overcustomise' ); ?></h3>
									</div>
								</div>
								<div class="oc-editor-field">
									<label for="oc-prop-label"><?php esc_html_e( 'Label', 'overcustomise' ); ?></label>
									<input type="text" id="oc-prop-label" class="oc-input" style="width:100%;" placeholder="<?php esc_attr_e( 'e.g. Front', 'overcustomise' ); ?>" />
								</div>
								<div class="oc-editor-field">
									<label for="oc-prop-method"><?php esc_html_e( 'Print Method', 'overcustomise' ); ?></label>
									<select id="oc-prop-method" class="oc-select" style="width:100%;">
										<option value="uv"><?php esc_html_e( 'UV Printing', 'overcustomise' ); ?></option>
										<option value="engraving"><?php esc_html_e( 'Engraving', 'overcustomise' ); ?></option>
										<option value="embroidery"><?php esc_html_e( 'Embroidery', 'overcustomise' ); ?></option>
										<option value="sublimation"><?php esc_html_e( 'Sublimation', 'overcustomise' ); ?></option>
									</select>
								</div>
								<div class="oc-mockup-thumb" id="oc-mockup-thumb">
									<img id="oc-mockup-thumb-img" src="" alt="" style="display:none;" />
									<span id="oc-mockup-thumb-empty" style="font-size:12px;color:var(--oc-gray-400);"><?php esc_html_e( 'No mockup set', 'overcustomise' ); ?></span>
								</div>
								<div style="display:flex;gap:8px;margin-top:8px;">
									<button type="button" id="oc-choose-mockup-btn" class="oc-btn oc-btn-secondary oc-btn-sm" style="flex:1;"><?php esc_html_e( 'Choose Mockup', 'overcustomise' ); ?></button>
									<button type="button" id="oc-remove-mockup-btn" class="oc-btn oc-btn-secondary oc-btn-sm" style="display:none;"><?php esc_html_e( 'Remove', 'overcustomise' ); ?></button>
								</div>
								<div style="display:flex;align-items:center;justify-content:space-between;margin-top:12px;margin-bottom:8px;">
									<h3 style="font-size:10px;text-transform:uppercase;letter-spacing:.07em;font-weight:700;color:var(--oc-gray-400);margin:0;"><?php esc_html_e( 'Print Bounds', 'overcustomise' ); ?></h3>
									<span style="font-size:10px;color:var(--oc-gray-400);">px</span>
								</div>
								<div class="oc-bounds-grid">
									<div class="oc-editor-field"><label>X</label><input type="number" id="oc-prop-x" class="oc-input" min="0" style="width:100%;" /></div>
									<div class="oc-editor-field"><label>Y</label><input type="number" id="oc-prop-y" class="oc-input" min="0" style="width:100%;" /></div>
									<div class="oc-editor-field"><label>W</label><input type="number" id="oc-prop-w" class="oc-input" min="1" style="width:100%;" /></div>
									<div class="oc-editor-field"><label>H</label><input type="number" id="oc-prop-h" class="oc-input" min="1" style="width:100%;" /></div>
								</div>
								</div>
						</div>

					</div>

					<!-- CENTER: canvas -->
					<div class="oc-editor-canvas-wrap">

						<div id="oc-area-strip" class="oc-area-strip"></div>

						<div class="oc-canvas-no-mockup" id="oc-canvas-no-mockup">
							<div class="oc-canvas-no-mockup-inner">
								<span style="font-size:48px;line-height:1;">🖼️</span>
								<p id="oc-canvas-no-mockup-msg" style="margin:12px 0 0;font-size:13px;color:rgba(255,255,255,.5);text-align:center;max-width:280px;">
									<?php esc_html_e( 'Add a print area using the + Add button on the left.', 'overcustomise' ); ?>
								</p>
							</div>
						</div>

						<div class="oc-canvas-stage" id="oc-canvas-stage" style="display:none;">
							<img id="oc-canvas-mockup-img" src="" alt="" draggable="false" />
							<div id="oc-canvas-ghosts"></div>
							<div class="oc-bounds-box" id="oc-bounds-box" style="display:none;">
								<div class="oc-bounds-handle" data-dir="nw"></div>
								<div class="oc-bounds-handle" data-dir="n"></div>
								<div class="oc-bounds-handle" data-dir="ne"></div>
								<div class="oc-bounds-handle" data-dir="e"></div>
								<div class="oc-bounds-handle" data-dir="se"></div>
								<div class="oc-bounds-handle" data-dir="s"></div>
								<div class="oc-bounds-handle" data-dir="sw"></div>
								<div class="oc-bounds-handle" data-dir="w"></div>
							</div>
						</div>

						<div class="oc-canvas-coords" id="oc-canvas-coords" style="display:none;">
							<span id="oc-coords-text"></span>
						</div>

					</div>

					<!-- RIGHT: layer manager -->
					<div class="oc-editor-right">

						<!-- 1. Layer type picker (always visible) -->
						<div class="oc-right-type-picker">
							<div class="oc-editor-section-header">
								<h3><?php esc_html_e( 'Add Layer', 'overcustomise' ); ?></h3>
								<span id="oc-type-picker-hint" style="font-size:10px;color:var(--oc-gray-400);"><?php esc_html_e( 'Select an area first', 'overcustomise' ); ?></span>
							</div>
							<div class="oc-layer-type-grid">
								<button type="button" class="oc-layer-type-btn" data-type="text"><span class="oc-layer-type-btn-icon" style="color:#0284c7;">Aa</span><span><?php esc_html_e( 'Text', 'overcustomise' ); ?></span></button>
								<button type="button" class="oc-layer-type-btn" data-type="textarea"><span class="oc-layer-type-btn-icon" style="color:#7c3aed;">&para;</span><span><?php esc_html_e( 'Text Area', 'overcustomise' ); ?></span></button>
								<button type="button" class="oc-layer-type-btn" data-type="image"><span class="oc-layer-type-btn-icon" style="color:#059669;">&#x1f5bc;</span><span><?php esc_html_e( 'Image', 'overcustomise' ); ?></span></button>
								<button type="button" class="oc-layer-type-btn" data-type="spotify"><span class="oc-layer-type-btn-icon" style="color:#1db954;">&#x266b;</span><span><?php esc_html_e( 'Spotify', 'overcustomise' ); ?></span></button>
								<button type="button" class="oc-layer-type-btn" data-type="lineart"><span class="oc-layer-type-btn-icon" style="color:#d97706;">&#x270f;</span><span><?php esc_html_e( 'Line Art', 'overcustomise' ); ?></span></button>
								<button type="button" class="oc-layer-type-btn" data-type="clipart"><span class="oc-layer-type-btn-icon" style="color:#dc2626;">&#x2726;</span><span><?php esc_html_e( 'Clipart', 'overcustomise' ); ?></span></button>
							</div>
						</div>

						<!-- 2. Layer list (scrollable) -->
						<div class="oc-right-layers-scroll">
							<div class="oc-editor-section-header" style="padding:10px 14px 6px;flex-shrink:0;">
								<h3><?php esc_html_e( 'Layers', 'overcustomise' ); ?></h3>
								<span id="oc-layers-count" style="font-size:10px;color:var(--oc-gray-400);"></span>
							</div>
							<div id="oc-layers-list"></div>
							<div id="oc-layers-no-area" class="oc-editor-empty-hint">
								<p><?php esc_html_e( 'Select a print area to see its layers.', 'overcustomise' ); ?></p>
							</div>
							<div id="oc-layers-empty" class="oc-editor-empty-hint" style="display:none;">
								<p><?php esc_html_e( 'No layers yet. Use the buttons above to add one.', 'overcustomise' ); ?></p>
							</div>
						</div>

						<!-- 3. Layer properties (bottom, visible when layer selected) -->
						<div class="oc-right-layer-props">
							<div id="oc-layer-no-sel" class="oc-editor-empty-hint">
								<p><?php esc_html_e( 'Select a layer to edit its properties.', 'overcustomise' ); ?></p>
							</div>
							<div id="oc-layer-props-inner" style="display:none;">
								<div class="oc-editor-section-header" style="padding-bottom:8px;">
									<div style="display:flex;align-items:center;gap:6px;min-width:0;">
										<span id="oc-layer-color-dot" class="oc-area-dot" style="width:10px;height:10px;"></span>
										<span id="oc-layer-type-icon" style="font-size:13px;"></span>
										<h3 id="oc-layer-type-label"></h3>
									</div>
								</div>
								<div id="oc-layer-settings"></div>
							</div>
						</div>

					</div><!-- /.oc-editor-right -->

				</div><!-- /.oc-design-editor -->
			
						</form>
		</div>
		<?php
	}

	// ── Save / Delete ─────────────────────────────────────────────────────────

	private function handle_design_save(): int {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'overcustomise' ), 403 );
		}
		if ( ! wp_verify_nonce( sanitize_key( $_POST['oc_design_nonce'] ?? '' ), 'oc_save_design' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'overcustomise' ) );
		}

		global $wpdb;

		$design_id   = (int) ( $_POST['oc_design_id'] ?? 0 );
		$name        = sanitize_text_field( $_POST['oc_design_name'] ?? '' );
		$custom_type = in_array( $_POST['oc_custom_type'] ?? '', [ 'text_only', 'photo_text' ], true )
			? sanitize_key( $_POST['oc_custom_type'] )
			: 'text_only';
		$flat_rate   = number_format( max( 0, (float) ( $_POST['oc_flat_rate'] ?? 0 ) ), 2, '.', '' );
		$active      = isset( $_POST['oc_active'] ) ? 1 : 0;

		if ( ! $name ) {
			wp_die( esc_html__( 'Design name is required.', 'overcustomise' ) );
		}

		$data = [
			'name'        => $name,
			'custom_type' => $custom_type,
			'flat_rate'   => $flat_rate,
			'active'      => $active,
		];
		$fmt = [ '%s', '%s', '%s', '%d' ];

		if ( $design_id > 0 ) {
			$wpdb->update( "{$wpdb->prefix}oc_designs", $data, [ 'id' => $design_id ], $fmt, [ '%d' ] );
		} else {
			$wpdb->insert( "{$wpdb->prefix}oc_designs", $data, $fmt );
			$design_id = (int) $wpdb->insert_id;
		}

		// Save print areas — also build index→DB id map for layer saving.
		$posted_areas  = $_POST['oc_design_areas'] ?? [];
		$submitted_ids = [];
		$area_id_map   = []; // area_index (0,1,2…) → DB id

		foreach ( $posted_areas as $area_index => $area_data ) {
			$area_id    = (int) ( $area_data['id'] ?? 0 );
			$label      = sanitize_text_field( $area_data['label'] ?? '' );
			if ( ! $label ) continue;

			$area_key   = sanitize_key( $label );
			$method     = in_array( $area_data['print_method'] ?? '', [ 'engraving', 'uv', 'embroidery', 'sublimation' ], true )
				? sanitize_key( $area_data['print_method'] )
				: 'uv';
			$mockup_id  = (int) ( $area_data['mockup_attachment_id'] ?? 0 );
			$canvas_x   = max( 0, (int) ( $area_data['canvas_x'] ?? 0 ) );
			$canvas_y   = max( 0, (int) ( $area_data['canvas_y'] ?? 0 ) );
			$canvas_w   = max( 1, (int) ( $area_data['canvas_w'] ?? 300 ) );
			$canvas_h   = max( 1, (int) ( $area_data['canvas_h'] ?? 300 ) );
			$sort_order = (int) ( $area_data['sort_order'] ?? 0 );

			$row = [
				'design_id'            => $design_id,
				'area_key'             => $area_key,
				'label'                => $label,
				'print_method'         => $method,
				'mockup_attachment_id' => $mockup_id ?: null,
				'canvas_x'             => $canvas_x,
				'canvas_y'             => $canvas_y,
				'canvas_w'             => $canvas_w,
				'canvas_h'             => $canvas_h,
				'sort_order'           => $sort_order,
				'visible'              => isset( $area_data['visible'] ) && $area_data['visible'] !== '0' ? 1 : 0,
				'locked'               => ! empty( $area_data['locked'] ) && $area_data['locked'] !== '0' ? 1 : 0,
			];
			$row_fmt = [ '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d' ];

			if ( $area_id > 0 ) {
				$wpdb->update( "{$wpdb->prefix}oc_design_print_areas", $row, [ 'id' => $area_id ], $row_fmt, [ '%d' ] );
				$db_area_id = $area_id;
			} else {
				$wpdb->insert( "{$wpdb->prefix}oc_design_print_areas", $row, $row_fmt );
				$db_area_id = (int) $wpdb->insert_id;
			}

			$submitted_ids[]                  = $db_area_id;
			$area_id_map[ (int) $area_index ] = $db_area_id;
		}

		// Delete removed areas.
		$existing_ids = array_column( OC_DB::get_design_print_areas( $design_id ), 'id' );
		foreach ( array_diff( $existing_ids, $submitted_ids ) as $del_id ) {
			$wpdb->delete( "{$wpdb->prefix}oc_design_print_areas", [ 'id' => (int) $del_id ], [ '%d' ] );
		}

		// Save layers — delete all and re-insert from POST.
		$wpdb->delete( "{$wpdb->prefix}oc_design_layers", [ 'design_id' => $design_id ], [ '%d' ] );

		$valid_types = [ 'text', 'textarea', 'image', 'spotify', 'lineart', 'clipart' ];
		foreach ( (array) ( $_POST['oc_layers'] ?? [] ) as $sort => $layer_data ) {
			$area_index = (int) ( $layer_data['area_index'] ?? 0 );
			$area_db_id = $area_id_map[ $area_index ] ?? 0;
			if ( ! $area_db_id ) continue;

			$type     = in_array( $layer_data['type'] ?? '', $valid_types, true )
				? sanitize_key( $layer_data['type'] )
				: 'text';
			$label    = sanitize_text_field( $layer_data['label']    ?? '' );
			// WordPress slashes $_POST, so unslash before json_decode — otherwise the
			// escaped quotes make it invalid JSON and settings get silently dropped.
			$settings_raw = wp_unslash( $layer_data['settings'] ?? '{}' );
			$decoded      = json_decode( is_string( $settings_raw ) ? $settings_raw : '{}', true );
			$settings     = wp_json_encode( is_array( $decoded ) ? $decoded : [] );

			$wpdb->insert(
				"{$wpdb->prefix}oc_design_layers",
				[
					'design_id'  => $design_id,
					'area_id'    => $area_db_id,
					'type'       => $type,
					'label'      => $label,
					'x'          => max( 0, (int) ( $layer_data['x']       ?? 0 ) ),
					'y'          => max( 0, (int) ( $layer_data['y']       ?? 0 ) ),
					'w'          => max( 1, (int) ( $layer_data['w']       ?? 200 ) ),
					'h'          => max( 1, (int) ( $layer_data['h']       ?? 50  ) ),
					'sort_order' => (int) $sort,
					'visible'    => isset( $layer_data['visible'] ) && $layer_data['visible'] !== '0' ? 1 : 0,
					'locked'     => ! empty( $layer_data['locked'] ) && $layer_data['locked'] !== '0' ? 1 : 0,
					'settings'   => $settings ?: '{}',
				],
				[ '%d', '%d', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%s' ]
			);
		}

		return $design_id;
	}

	private function handle_design_delete(): void {
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;

		if ( ! $id || ! isset( $_GET['_wpnonce'] )
		     || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'oc_delete_design_' . $id )
		) {
			wp_die( esc_html__( 'Security check failed.', 'overcustomise' ) );
		}

		global $wpdb;
		$wpdb->delete( "{$wpdb->prefix}oc_design_print_areas",  [ 'design_id' => $id ], [ '%d' ] );
		$wpdb->delete( "{$wpdb->prefix}oc_design_layers",       [ 'design_id' => $id ], [ '%d' ] );
		$wpdb->delete( "{$wpdb->prefix}oc_product_assignments",  [ 'design_id' => $id ], [ '%d' ] );
		$wpdb->delete( "{$wpdb->prefix}oc_designs",              [ 'id'        => $id ], [ '%d' ] );

		wp_safe_redirect( admin_url( 'admin.php?page=overcustomise-products&tab=designs&deleted=1' ) );
		exit;
	}

	private function handle_design_duplicate(): void {
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;

		if ( ! $id || ! isset( $_GET['_wpnonce'] )
		     || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'oc_duplicate_design_' . $id )
		) {
			wp_die( esc_html__( 'Security check failed.', 'overcustomise' ) );
		}

		global $wpdb;

		$original = OC_DB::get_design( $id );
		if ( ! $original ) {
			wp_die( esc_html__( 'Design not found.', 'overcustomise' ) );
		}

		// Copy design record.
		$wpdb->insert(
			"{$wpdb->prefix}oc_designs",
			[
				'name'        => $original->name . __( ' (Copy)', 'overcustomise' ),
				'custom_type' => $original->custom_type,
				'flat_rate'   => $original->flat_rate,
				'active'      => 0,
			],
			[ '%s', '%s', '%s', '%d' ]
		);
		$new_id = (int) $wpdb->insert_id;
		if ( ! $new_id ) {
			wp_die( esc_html__( 'Could not duplicate design.', 'overcustomise' ) );
		}

		// Copy print areas, mapping old IDs to new IDs.
		$areas       = OC_DB::get_design_print_areas( $id );
		$area_id_map = [];
		foreach ( $areas as $area ) {
			$wpdb->insert(
				"{$wpdb->prefix}oc_design_print_areas",
				[
					'design_id'            => $new_id,
					'area_key'             => $area->area_key,
					'label'                => $area->label,
					'print_method'         => $area->print_method,
					'mockup_attachment_id' => $area->mockup_attachment_id,
					'canvas_x'             => $area->canvas_x,
					'canvas_y'             => $area->canvas_y,
					'canvas_w'             => $area->canvas_w,
					'canvas_h'             => $area->canvas_h,
					'sort_order'           => $area->sort_order,
					'visible'              => $area->visible,
					'locked'               => $area->locked,
				],
				[ '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d' ]
			);
			$area_id_map[ (int) $area->id ] = (int) $wpdb->insert_id;
		}

		// Copy layers, re-mapping area_id.
		$layers = OC_DB::get_design_layers( $id );
		foreach ( $layers as $layer ) {
			$new_area_id = $area_id_map[ (int) $layer->area_id ] ?? 0;
			if ( ! $new_area_id ) continue;
			$wpdb->insert(
				"{$wpdb->prefix}oc_design_layers",
				[
					'design_id'  => $new_id,
					'area_id'    => $new_area_id,
					'type'       => $layer->type,
					'label'      => $layer->label,
					'x'          => $layer->x,
					'y'          => $layer->y,
					'w'          => $layer->w,
					'h'          => $layer->h,
					'sort_order' => $layer->sort_order,
					'visible'    => $layer->visible,
					'locked'     => $layer->locked,
					'settings'   => $layer->settings ?: '{}',
				],
				[ '%d', '%d', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%s' ]
			);
		}

		wp_safe_redirect( admin_url( 'admin.php?page=overcustomise-products&tab=designs&duplicated=1' ) );
		exit;
	}
}
