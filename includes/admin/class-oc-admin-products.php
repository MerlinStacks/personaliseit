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

	private const ADMIN_PAGE_SIZE = 50;

	// ── AJAX ──────────────────────────────────────────────────────────────────

	public static function register_ajax(): void {
		add_action( 'wp_ajax_oc_assign_design', [ self::class, 'ajax_assign_design' ] );
		add_action( 'wp_ajax_oc_save_design_variants', [ self::class, 'ajax_save_design_variants' ] );
		add_action( 'wp_ajax_oc_autosave_design', [ self::class, 'ajax_autosave_design' ] );
		add_action( 'wp_ajax_oc_restore_autosave', [ self::class, 'ajax_restore_autosave' ] );
	}

	public static function ajax_assign_design(): void {
		check_ajax_referer( 'oc-products-nonce', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'overcustomise' ) ] );
		}

		$product_id = absint( $_POST['product_id'] ?? 0 );
		$variant_id = absint( $_POST['variant_id'] ?? 0 );
		$design_id  = absint( $_POST['design_id'] ?? 0 );

		$context = self::validate_product_context( $product_id, $variant_id );
		if ( is_wp_error( $context ) ) {
			wp_send_json_error( [ 'message' => $context->get_error_message() ], 400 );
		}

		if ( $design_id ) {
			$design = OC_DB::get_design( $design_id );
			if ( ! $design || ! (bool) $design->active ) {
				wp_send_json_error( [ 'message' => __( 'Select an active design.', 'overcustomise' ) ], 400 );
			}
			OC_DB::upsert_assignment( $product_id, $variant_id, $design_id );
			global $wpdb;
			$stored = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT design_id FROM {$wpdb->prefix}oc_product_assignments WHERE product_id = %d AND variant_id = %d LIMIT 1",
					$product_id,
					$variant_id
				)
			);
			if ( $design_id !== (int) $stored ) {
				wp_send_json_error( [ 'message' => __( 'Could not save the design assignment.', 'overcustomise' ) ], 500 );
			}
		} else {
			OC_DB::delete_assignment( $product_id, $variant_id );
			global $wpdb;
			$remaining = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT 1 FROM {$wpdb->prefix}oc_product_assignments WHERE product_id = %d AND variant_id = %d LIMIT 1",
					$product_id,
					$variant_id
				)
			);
			if ( $remaining ) {
				wp_send_json_error( [ 'message' => __( 'Could not remove the design assignment.', 'overcustomise' ) ], 500 );
			}
		}

		wp_send_json_success();
	}

	public static function ajax_save_design_variants(): void {
		check_ajax_referer( 'oc-products-nonce', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'overcustomise' ) ] );
		}

		$product_id = absint( $_POST['product_id'] ?? 0 );
		$variant_id = absint( $_POST['variant_id'] ?? 0 );
		$raw        = wp_unslash( $_POST['variants'] ?? '[]' );
		$decoded    = is_string( $raw ) ? json_decode( $raw, true ) : [];

		$context = self::validate_product_context( $product_id, $variant_id );
		if ( is_wp_error( $context ) || ! is_array( $decoded ) || count( $decoded ) > 50 ) {
			wp_send_json_error( [ 'message' => __( 'Invalid variants.', 'overcustomise' ) ] );
		}
		$assignment = OC_DB::get_assignment_for_product( $product_id, $variant_id );
		if ( ! $assignment || (int) $assignment->product_id !== $product_id || (int) $assignment->variant_id !== $variant_id ) {
			wp_send_json_error( [ 'message' => __( 'Assign a default design before adding artwork options.', 'overcustomise' ) ], 400 );
		}

		$variants = [];
		$seen     = [];
		foreach ( $decoded as $item ) {
			if ( ! is_array( $item ) ) {
				wp_send_json_error( [ 'message' => __( 'Invalid variants.', 'overcustomise' ) ], 400 );
			}
			$design_id = absint( $item['designId'] ?? 0 );
			$design    = $design_id ? OC_DB::get_design( $design_id ) : null;
			if ( ! $design || ! (bool) $design->active || $design_id === (int) $assignment->design_id || isset( $seen[ $design_id ] ) ) {
				wp_send_json_error( [ 'message' => __( 'Each artwork option must reference a distinct active design.', 'overcustomise' ) ], 400 );
			}
			$seen[ $design_id ] = true;
			$variants[]         = [
				'designId' => $design_id,
				'label'    => substr( sanitize_text_field( is_scalar( $item['label'] ?? null ) ? (string) $item['label'] : '' ), 0, 190 ),
			];
		}

		global $wpdb;
		$updated = $wpdb->update(
			"{$wpdb->prefix}oc_product_assignments",
			[ 'design_variants' => wp_json_encode( $variants ) ],
			[
				'product_id' => $product_id,
				'variant_id' => $variant_id,
			],
			[ '%s' ],
			[ '%d', '%d' ]
		);
		if ( false === $updated ) {
			wp_send_json_error( [ 'message' => __( 'Could not save artwork options.', 'overcustomise' ) ], 500 );
		}
		OC_Cache::invalidate_group( OC_Cache::GROUP );
		wp_send_json_success();
	}

	/** Validate an editable parent product and optional child variation. */
	private static function validate_product_context( int $product_id, int $variation_id ): array|\WP_Error {
		$product = $product_id ? wc_get_product( $product_id ) : null;
		if ( ! $product instanceof WC_Product
			|| ! in_array( $product->get_type(), [ 'simple', 'variable' ], true )
			|| ! current_user_can( 'edit_post', $product_id )
		) {
			return new \WP_Error( 'invalid_product', __( 'Invalid or inaccessible product.', 'overcustomise' ) );
		}

		$variation = null;
		if ( $variation_id ) {
			$variation = wc_get_product( $variation_id );
			if ( ! $variation instanceof WC_Product_Variation
				|| (int) $variation->get_parent_id() !== $product_id
				|| ! current_user_can( 'edit_post', $variation_id )
			) {
				return new \WP_Error( 'invalid_variation', __( 'The selected variation does not belong to this product.', 'overcustomise' ) );
			}
		}

		return [
			'product'   => $product,
			'variation' => $variation,
		];
	}

	public static function ajax_autosave_design(): void {
		check_ajax_referer( 'oc-products-nonce', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'overcustomise' ) ] );
		}

		$design_id = (int) ( $_POST['design_id'] ?? 0 );
		if ( ! $design_id || ! OC_DB::get_design( $design_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid design.', 'overcustomise' ) ] );
		}

		$state_raw = wp_unslash( $_POST['state'] ?? '' );
		if ( ! is_string( $state_raw ) || ! $state_raw || strlen( $state_raw ) > 1048576 ) {
			wp_send_json_error( [ 'message' => __( 'Invalid state.', 'overcustomise' ) ] );
		}
		$state = json_decode( $state_raw, true );
		if ( ! is_array( $state ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid state.', 'overcustomise' ) ] );
		}

		$revision          = max( 0, (int) ( $_POST['revision'] ?? 0 ) );
		$expected_revision = max( 0, (int) ( $_POST['expected_revision'] ?? 0 ) );
		$result            = OC_Autosave::store( $design_id, $state, $revision, $expected_revision );
		if ( 'stored' === $result['status'] ) {
			wp_send_json_success(
				[
					'timestamp' => (int) $result['timestamp'],
					'revision'  => (int) $result['revision'],
				]
			);
		} elseif ( 'conflict' === $result['status'] ) {
			wp_send_json_error(
				[
					'code'      => 'autosave_conflict',
					'message'   => __( 'A newer autosave exists from another tab. Reload this design before saving.', 'overcustomise' ),
					'timestamp' => (int) $result['timestamp'],
					'revision'  => (int) $result['revision'],
				],
				409
			);
		} else {
			wp_send_json_error( [ 'message' => __( 'Autosave failed.', 'overcustomise' ) ] );
		}
	}

	public static function ajax_restore_autosave(): void {
		check_ajax_referer( 'oc-products-nonce', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'overcustomise' ) ] );
		}

		$design_id = (int) ( $_POST['design_id'] ?? 0 );
		if ( ! $design_id || ! OC_DB::get_design( $design_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid design.', 'overcustomise' ) ] );
		}
		$data = OC_Autosave::restore( $design_id );

		if ( $data ) {
			wp_send_json_success( $data );
		} else {
			wp_send_json_error( [ 'message' => __( 'No autosave found.', 'overcustomise' ) ] );
		}
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
		<div class="wrap oc-page oc-products-page">
			<div id="oc-products-app">

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
					class="oc-tab oc-ajax-nav<?php echo 'products' === $active_tab ? ' oc-tab--active' : ''; ?>">
					<?php esc_html_e( 'Products', 'overcustomise' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=overcustomise-products&tab=designs' ) ); ?>"
					class="oc-tab oc-ajax-nav<?php echo 'designs' === $active_tab ? ' oc-tab--active' : ''; ?>">
					<?php esc_html_e( 'Designs', 'overcustomise' ); ?>
				</a>
			</div>

			<?php if ( 'products' === $active_tab ) : ?>
				<?php $this->render_products_tab(); ?>
			<?php else : ?>
				<?php $this->render_designs_tab(); ?>
			<?php endif; ?>

			</div>
		</div>
		<?php
	}

	// ── Tab 1: Products ───────────────────────────────────────────────────────

	private function render_products_tab(): void {
		$current_page      = $this->get_admin_page_number( 'product_page' );
		$search            = $this->get_admin_search_term( 'product_search' );
		$product_filter    = isset( $_GET['product_design_filter'] ) ? sanitize_key( wp_unslash( $_GET['product_design_filter'] ) ) : 'all';
		$product_filter    = 'unassigned' === $product_filter ? 'unassigned' : 'all';
		$is_unassigned_tab = 'unassigned' === $product_filter;

		// Load active designs for the assignment dropdown.
		$designs             = OC_DB::get_designs( true );
		$design_thumbs       = $this->get_design_thumbnail_map( $designs );
		$assigned_design_ids = [];
		foreach ( OC_DB::get_all_assignments() as $product_assignments ) {
			foreach ( $product_assignments as $assignment ) {
				$design_id = absint( $assignment['design_id'] ?? 0 );
				if ( $design_id ) {
					$assigned_design_ids[ $design_id ] = true;
				}
			}
		}

		// Load one page of published WC products.
		$product_query = $this->get_paginated_products( $current_page, $search, $product_filter );
		$wc_products   = $product_query->products;
		$product_total = (int) $product_query->total;
		$total_pages   = max( 1, (int) $product_query->max_num_pages );
		if ( $current_page > $total_pages ) {
			$current_page  = $total_pages;
			$product_query = $this->get_paginated_products( $current_page, $search, $product_filter );
			$wc_products   = $product_query->products;
			$product_total = (int) $product_query->total;
		}
		$pagination_args = [
			'product_search'        => $search,
			'product_design_filter' => $product_filter,
		];
		$assign_map      = OC_DB::get_assignments_for_product_ids( array_map( static fn ( $product ): int => (int) $product->get_id(), $wc_products ) );

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
				<form method="get" class="oc-list-filter oc-ajax-form">
					<input type="hidden" name="page" value="overcustomise-products" />
					<input type="hidden" name="tab" value="products" />
					<input type="hidden" name="product_design_filter" value="<?php echo esc_attr( $product_filter ); ?>" />
					<span style="font-size:12px;color:var(--oc-gray-400);">
						<?php echo esc_html( (string) $product_total ); ?> <?php echo esc_html( 1 === $product_total ? __( 'product', 'overcustomise' ) : __( 'products', 'overcustomise' ) ); ?>
					</span>
					<input type="search" class="oc-input oc-products-search" name="product_search" value="<?php echo esc_attr( $search ); ?>"
							placeholder="<?php esc_attr_e( 'Filter all products…', 'overcustomise' ); ?>" />
					<button type="submit" class="button"><?php esc_html_e( 'Filter', 'overcustomise' ); ?></button>
					<?php if ( '' !== $search ) : ?>
						<a class="button oc-ajax-nav" href="<?php echo esc_url( add_query_arg( 'product_design_filter', $product_filter, admin_url( 'admin.php?page=overcustomise-products&tab=products' ) ) ); ?>"><?php esc_html_e( 'Clear', 'overcustomise' ); ?></a>
					<?php endif; ?>
				</form>
			</div>

			<div class="oc-tabs-bar oc-products-filter-tabs">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=overcustomise-products&tab=products' ) ); ?>"
					class="oc-tab oc-ajax-nav<?php echo ! $is_unassigned_tab ? ' oc-tab--active' : ''; ?>">
					<?php esc_html_e( 'All products', 'overcustomise' ); ?>
				</a>
				<a href="<?php echo esc_url( add_query_arg( 'product_design_filter', 'unassigned', admin_url( 'admin.php?page=overcustomise-products&tab=products' ) ) ); ?>"
					class="oc-tab oc-ajax-nav<?php echo $is_unassigned_tab ? ' oc-tab--active' : ''; ?>">
					<?php esc_html_e( 'Without designs', 'overcustomise' ); ?>
				</a>
			</div>

			<?php if ( 0 === $product_total ) : ?>
				<div class="oc-empty">
					<span class="oc-empty-icon">🛍️</span>
					<h3><?php echo $is_unassigned_tab ? esc_html__( 'No products without designs', 'overcustomise' ) : ( '' !== $search ? esc_html__( 'No matching products found', 'overcustomise' ) : esc_html__( 'No products found', 'overcustomise' ) ); ?></h3>
					<p><?php echo $is_unassigned_tab ? esc_html__( 'Every published simple or variable product currently has a design assignment.', 'overcustomise' ) : ( '' !== $search ? esc_html__( 'Try a different product name, SKU, or variant value.', 'overcustomise' ) : esc_html__( 'Publish some WooCommerce products first.', 'overcustomise' ) ); ?></p>
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
				<?php $this->render_pagination_controls( 'products', 'product_page', $current_page, $total_pages, $product_total, $pagination_args ); ?>
				<div class="oc-table-wrap">
					<table class="oc-table" id="oc-products-table">
						<thead>
							<tr>
								<th style="width:40%"><?php esc_html_e( 'Product / Variant', 'overcustomise' ); ?></th>
								<th><?php esc_html_e( 'SKU', 'overcustomise' ); ?></th>
								<th><?php esc_html_e( 'Assigned Design', 'overcustomise' ); ?></th>
								<th><?php esc_html_e( 'Design Variants', 'overcustomise' ); ?></th>
								<th style="width:60px;"></th>
							</tr>
						</thead>
						<tbody>
							<?php
							foreach ( $wc_products as $product ) :
								$pid        = $product->get_id();
								$pname      = $product->get_name();
								$psku       = $product->get_sku();
								$is_var     = $product->is_type( 'variable' );
								$search_str = strtolower( $pname . ' ' . $psku );
								?>
								<?php if ( $is_var ) : ?>
									<?php $parent_assignment = $assign_map[ $pid ][0] ?? [ 'design_id' => 0 ]; ?>
									<!-- Variable product parent row with all-variants dropdown -->
									<tr class="oc-product-row oc-product-row--parent" data-search="<?php echo esc_attr( $search_str ); ?>">
										<td class="oc-col-primary" colspan="2">
											<strong><?php echo esc_html( $pname ); ?></strong>
											<?php if ( $psku ) : ?>
												<small style="color:var(--oc-gray-400);margin-left:6px;"><?php echo esc_html( $psku ); ?></small>
											<?php endif; ?>
										</td>
										<td>
											<?php $this->render_design_select( $designs, $assigned_design_ids, $pid, 0, (int) $parent_assignment['design_id'] ); ?>
											<small style="color:var(--oc-gray-500);display:block;margin-top:3px;font-size:11px;"><?php esc_html_e( 'All variants (default)', 'overcustomise' ); ?></small>
										</td>
										<td><?php $this->render_design_variants_control( $designs, $design_thumbs, $pid, 0, $parent_assignment['design_variants'] ?? '' ); ?></td>
										<td><span class="oc-assign-status" aria-live="polite"></span></td>
									</tr>
									<!-- One row per variant -->
									<?php
									foreach ( $product->get_children() as $vid ) :
										$variation = wc_get_product( $vid );
										if ( ! $variation instanceof \WC_Product_Variation ) {
											continue;
										}
										$vsku        = $variation->get_sku();
										$vattrs      = array_filter( $variation->get_variation_attributes() );
										$vlabel      = implode( ' / ', array_map( 'ucfirst', $vattrs ) ) ?: '#' . $vid;
										$vsearch     = strtolower( $pname . ' ' . $vlabel . ' ' . $vsku );
										$vassignment = $assign_map[ $pid ][ $vid ] ?? [ 'design_id' => 0 ];
										?>
										<tr class="oc-product-row oc-product-row--variant" data-search="<?php echo esc_attr( $vsearch ); ?>">
											<td class="oc-col-variant"><?php echo esc_html( $vlabel ); ?></td>
											<td><span class="oc-code"><?php echo esc_html( $vsku ); ?></span></td>
											<td>
												<?php $this->render_design_select( $designs, $assigned_design_ids, $pid, $vid, (int) $vassignment['design_id'] ); ?>
											</td>
											<td><?php $this->render_design_variants_control( $designs, $design_thumbs, $pid, $vid, $vassignment['design_variants'] ?? '' ); ?></td>
											<td><span class="oc-assign-status" aria-live="polite"></span></td>
										</tr>
									<?php endforeach; ?>
								<?php else : ?>
									<!-- Simple product row -->
									<?php $assignment = $assign_map[ $pid ][0] ?? [ 'design_id' => 0 ]; ?>
									<tr class="oc-product-row" data-search="<?php echo esc_attr( $search_str ); ?>">
										<td class="oc-col-primary">
											<strong><?php echo esc_html( $pname ); ?></strong>
										</td>
										<td><span class="oc-code"><?php echo esc_html( $psku ); ?></span></td>
										<td>
											<?php $this->render_design_select( $designs, $assigned_design_ids, $pid, 0, (int) $assignment['design_id'] ); ?>
										</td>
										<td><?php $this->render_design_variants_control( $designs, $design_thumbs, $pid, 0, $assignment['design_variants'] ?? '' ); ?></td>
										<td><span class="oc-assign-status" aria-live="polite"></span></td>
									</tr>
								<?php endif; ?>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<?php $this->render_pagination_controls( 'products', 'product_page', $current_page, $total_pages, $product_total, $pagination_args ); ?>
			<?php endif; ?>
		</div>

		<script>
		( function () {
			function saveAssignment( sel ) {
				var row = sel.closest( 'tr' );
				var designSelect = row.querySelector( '.oc-design-assign-select' );
				var searchableInput = row.querySelector( '.oc-searchable-design-select-input' );
				var status = row.querySelector( '.oc-assign-status' );
					if ( status ) { status.textContent = 'Saving\u2026'; status.className = 'oc-assign-status oc-assign-status--saving'; }
					designSelect.disabled = true;
					if ( searchableInput ) { searchableInput.disabled = true; }

					var body = new URLSearchParams( {
						action:     'oc_assign_design',
						nonce:      ocProductsData.nonce,
						product_id: designSelect.dataset.productId,
						variant_id: designSelect.dataset.variantId,
						design_id:  designSelect.value,
					} );

					fetch( ocProductsData.ajaxUrl, { method: 'POST', body: body } )
						.then( function ( r ) { return r.json(); } )
						.then( function ( json ) {
							designSelect.disabled = false;
							if ( searchableInput ) { searchableInput.disabled = false; }
							if ( status ) {
								status.textContent = json.success ? 'Saved' : 'Error';
								status.className = 'oc-assign-status ' + ( json.success ? 'oc-assign-status--saved' : 'oc-assign-status--error' );
								setTimeout( function () { status.textContent = ''; status.className = 'oc-assign-status'; }, 2000 );
							}
						} )
						.catch( function () {
							designSelect.disabled = false;
							if ( searchableInput ) { searchableInput.disabled = false; }
							if ( status ) { status.textContent = 'Error'; status.className = 'oc-assign-status oc-assign-status--error'; }
						} );
			}

			function initSearchableDesignSelect( select ) {
				var wrapper = document.createElement( 'div' );
				var input = document.createElement( 'input' );
				var list = document.createElement( 'div' );
				var options = null;

				function getOptions() {
					if ( options ) return options;

					options = Array.prototype.map.call( select.options, function ( option ) {
						return {
							value: option.value,
							label: option.textContent.trim(),
							search: option.textContent.toLowerCase() + ' ' + option.value,
							assigned: option.dataset.assigned === '1',
						};
					} );
					return options;
				}

				function selectedLabel() {
					return select.options[ select.selectedIndex ] ? select.options[ select.selectedIndex ].textContent.trim() : '';
				}

				function syncInput() {
					input.value = '0' === select.value ? '' : selectedLabel();
					input.placeholder = '0' === select.value ? selectedLabel() : 'Search designs...';
				}

				function renderList() {
					var query = input.value.toLowerCase().trim();
					var matches = getOptions().filter( function ( option ) {
						return ! query || option.search.indexOf( query ) !== -1;
					} ).slice( 0, 60 );

					list.innerHTML = matches.length ? matches.map( function ( option ) {
						var selected = option.value === select.value ? ' is-selected' : '';
						var assigned = option.assigned ? ' is-assigned' : '';
						return '<button type="button" class="oc-searchable-design-select-option' + assigned + selected + '" data-value="' + escHtml( option.value ) + '">' + escHtml( option.label ) + '</button>';
					} ).join( '' ) : '<div class="oc-searchable-design-select-empty">No designs found</div>';
				}

				function positionList() {
					var rect = wrapper.getBoundingClientRect();
					var gap = 4;
					var edgeGap = 8;
					var spaceBelow = window.innerHeight - rect.bottom - gap - edgeGap;
					var spaceAbove = rect.top - gap - edgeGap;
					var openAbove = spaceBelow < Math.min( 260, list.scrollHeight ) && spaceAbove > spaceBelow;
					var available = openAbove ? spaceAbove : spaceBelow;
					var height = Math.min( 260, list.scrollHeight, Math.max( 80, available ) );
					var left = Math.max( edgeGap, Math.min( rect.left, window.innerWidth - rect.width - edgeGap ) );

					list.style.position = 'fixed';
					list.style.left = left + 'px';
					list.style.right = 'auto';
					list.style.top = ( openAbove ? Math.max( edgeGap, rect.top - gap - height ) : rect.bottom + gap ) + 'px';
					list.style.width = rect.width + 'px';
					list.style.maxHeight = height + 'px';
				}

				function openList() {
					wrapper.classList.add( 'is-open' );
					renderList();
					document.body.appendChild( list );
					list.classList.add( 'is-portaled' );
					input.setAttribute( 'aria-expanded', 'true' );
					positionList();
					window.addEventListener( 'resize', positionList );
					window.addEventListener( 'scroll', positionList, true );
				}

				function closeList() {
					wrapper.classList.remove( 'is-open' );
					list.classList.remove( 'is-portaled' );
					list.removeAttribute( 'style' );
					wrapper.appendChild( list );
					input.setAttribute( 'aria-expanded', 'false' );
					window.removeEventListener( 'resize', positionList );
					window.removeEventListener( 'scroll', positionList, true );
					syncInput();
				}

				wrapper.className = 'oc-searchable-design-select';
				input.type = 'search';
				input.className = 'oc-input oc-searchable-design-select-input';
				input.autocomplete = 'off';
				input.setAttribute( 'role', 'combobox' );
				input.setAttribute( 'aria-expanded', 'false' );
				syncInput();
				list.className = 'oc-searchable-design-select-list';

				select.classList.add( 'oc-design-assign-select--hidden' );
				select.parentNode.insertBefore( wrapper, select.nextSibling );
				wrapper.appendChild( input );
				wrapper.appendChild( list );

				input.addEventListener( 'focus', function () {
					input.select();
					openList();
				} );
				input.addEventListener( 'input', function () {
					renderList();
					positionList();
				} );
				input.addEventListener( 'keydown', function ( e ) {
					var firstOption = list.querySelector( '.oc-searchable-design-select-option' );
					if ( 'Escape' === e.key ) {
						closeList();
						input.blur();
					}
					if ( 'Enter' === e.key && firstOption ) {
						e.preventDefault();
						firstOption.click();
					}
				} );
				list.addEventListener( 'mousedown', function ( e ) {
					var option = e.target.closest( '.oc-searchable-design-select-option' );
					if ( ! option ) return;
					e.preventDefault();
					select.value = option.dataset.value;
					syncInput();
					closeList();
					select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
				} );
				document.addEventListener( 'mousedown', function ( e ) {
					if ( ! wrapper.contains( e.target ) ) closeList();
				} );
			}

			function parseVariants( box ) {
				try { return JSON.parse( box.querySelector( '.oc-design-variants-data' ).value || '[]' ); }
				catch ( e ) { return []; }
			}

			function escHtml( value ) {
				return String( value || '' )
					.replace( /&/g, '&amp;' )
					.replace( /</g, '&lt;' )
					.replace( />/g, '&gt;' )
					.replace( /"/g, '&quot;' )
					.replace( /'/g, '&#039;' );
			}

			function renderVariants( box ) {
				var list = box.querySelector( '.oc-design-variants-list' );
				var data = parseVariants( box );
				list.innerHTML = data.map( function ( item, i ) {
					return '<div class="oc-design-variant-admin-item" data-index="' + i + '" style="display:flex;gap:6px;align-items:center;margin-bottom:6px;">' +
						( item.thumbUrl ? '<img src="' + escHtml( item.thumbUrl ) + '" alt="" style="width:44px;height:44px;object-fit:contain;border:1px solid #ddd;background:#fff;" />' : '' ) +
						'<input type="text" class="oc-input oc-design-variant-label" value="' + escHtml( item.label ) + '" placeholder="Option label" style="width:120px;" />' +
						'<span class="oc-code">#' + escHtml( item.designId ) + '</span>' +
						'<button type="button" class="button oc-design-variant-remove">Remove</button>' +
					'</div>';
				} ).join( '' );
			}

			function saveVariants( box ) {
				var status = box.querySelector( '.oc-design-variants-status' );
				var data = parseVariants( box );
				box.querySelectorAll( '.oc-design-variant-admin-item' ).forEach( function ( row ) {
					var i = parseInt( row.dataset.index, 10 );
					if ( data[ i ] ) data[ i ].label = row.querySelector( '.oc-design-variant-label' ).value;
				} );
				box.querySelector( '.oc-design-variants-data' ).value = JSON.stringify( data );
				if ( status ) status.textContent = 'Saving\u2026';

				fetch( ocProductsData.ajaxUrl, { method: 'POST', body: new URLSearchParams( {
					action: 'oc_save_design_variants',
					nonce: ocProductsData.nonce,
					product_id: box.dataset.productId,
					variant_id: box.dataset.variantId,
					variants: JSON.stringify( data ),
				} ) } ).then( function ( r ) { return r.json(); } ).then( function ( json ) {
					if ( status ) status.textContent = json.success ? 'Saved' : 'Error';
					setTimeout( function () { if ( status ) status.textContent = ''; }, 2000 );
				} ).catch( function () { if ( status ) status.textContent = 'Error'; } );
			}

			document.querySelectorAll( '.oc-design-variants-admin' ).forEach( function ( box ) {
				renderVariants( box );
				box.addEventListener( 'click', function ( e ) {
					if ( e.target.classList.contains( 'oc-design-variant-remove' ) ) {
						var data = parseVariants( box );
						data.splice( parseInt( e.target.closest( '.oc-design-variant-admin-item' ).dataset.index, 10 ), 1 );
						box.querySelector( '.oc-design-variants-data' ).value = JSON.stringify( data );
						renderVariants( box );
						saveVariants( box );
					}
					if ( e.target.classList.contains( 'oc-design-variant-add' ) ) {
						var select = box.querySelector( '.oc-design-variant-design-select' );
						var designId = parseInt( select.value || '0', 10 );
						if ( ! designId ) return;
						var selected = select.options[ select.selectedIndex ];
						var data = parseVariants( box );
						if ( data.some( function ( item ) { return parseInt( item.designId, 10 ) === designId; } ) ) return;
						data.push( { designId: designId, label: selected.text, thumbUrl: selected.dataset.thumbUrl || '' } );
						box.querySelector( '.oc-design-variants-data' ).value = JSON.stringify( data );
						renderVariants( box );
						saveVariants( box );
					}
				} );
				box.addEventListener( 'change', function ( e ) {
					if ( e.target.classList.contains( 'oc-design-variant-label' ) ) saveVariants( box );
				} );
			} );

			// AJAX assignment save.
			document.querySelectorAll( '.oc-design-assign-select' ).forEach( function ( sel ) {
				initSearchableDesignSelect( sel );
				sel.addEventListener( 'change', function () {
					saveAssignment( this );
				} );
			} );
		} )();
		</script>
		<?php
	}

	private function get_paginated_products( int $page, string $search = '', string $design_filter = 'all' ): object {
		$search = trim( $search );
		if ( '' !== $search || 'unassigned' === $design_filter ) {
			return $this->get_paginated_products_by_query( $page, $search, $design_filter );
		}

		return wc_get_products(
			[
				'limit'    => self::ADMIN_PAGE_SIZE,
				'page'     => max( 1, $page ),
				'paginate' => true,
				'type'     => [ 'simple', 'variable' ],
				'status'   => 'publish',
				'orderby'  => 'name',
				'order'    => 'ASC',
				'return'   => 'objects',
			]
		);
	}

	private function get_paginated_products_by_query( int $page, string $search, string $design_filter = 'all' ): object {
		global $wpdb;

		$page     = max( 1, $page );
		$per_page = self::ADMIN_PAGE_SIZE;
		$offset   = ( $page - 1 ) * $per_page;
		$like     = '%' . $wpdb->esc_like( $search ) . '%';
		$where    = "p.post_type = 'product'
			AND p.post_status = 'publish'
			AND tt.taxonomy = 'product_type'
			AND t.slug IN ('simple', 'variable')";
		$join     = "INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
			INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
			INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
			LEFT JOIN {$wpdb->postmeta} sku ON sku.post_id = p.ID AND sku.meta_key = '_sku'
			LEFT JOIN {$wpdb->posts} variation ON variation.post_parent = p.ID AND variation.post_type = 'product_variation'
			LEFT JOIN {$wpdb->postmeta} variation_sku ON variation_sku.post_id = variation.ID AND variation_sku.meta_key = '_sku'
			LEFT JOIN {$wpdb->postmeta} variation_meta ON variation_meta.post_id = variation.ID AND variation_meta.meta_key LIKE 'attribute_%'";
		$args     = [];

		if ( 'unassigned' === $design_filter ) {
			$join  .= " LEFT JOIN {$wpdb->prefix}oc_product_assignments assignment ON assignment.product_id = p.ID";
			$where .= ' AND assignment.id IS NULL';
		}

		if ( '' !== $search ) {
			$where .= ' AND (p.post_title LIKE %s OR sku.meta_value LIKE %s OR variation_sku.meta_value LIKE %s OR variation_meta.meta_value LIKE %s)';
			$args   = [ $like, $like, $like, $like ];
		}

		$total_sql = "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p {$join} WHERE {$where}";
		$total     = (int) ( empty( $args ) ? $wpdb->get_var( $total_sql ) : $wpdb->get_var( $wpdb->prepare( $total_sql, ...$args ) ) );
		$ids_sql   = "SELECT DISTINCT p.ID FROM {$wpdb->posts} p {$join} WHERE {$where} ORDER BY p.post_title ASC LIMIT %d OFFSET %d";
		$ids       = $wpdb->get_col( $wpdb->prepare( $ids_sql, ...array_merge( $args, [ $per_page, $offset ] ) ) ) ?: [];

		return (object) [
			'products'      => array_values( array_filter( array_map( 'wc_get_product', array_map( 'absint', $ids ) ) ) ),
			'total'         => $total,
			'max_num_pages' => max( 1, (int) ceil( $total / $per_page ) ),
		];
	}

	private function get_admin_page_number( string $param ): int {
		return isset( $_GET[ $param ] ) ? max( 1, absint( wp_unslash( $_GET[ $param ] ) ) ) : 1;
	}

	private function get_admin_search_term( string $param ): string {
		return isset( $_GET[ $param ] ) ? sanitize_text_field( wp_unslash( $_GET[ $param ] ) ) : '';
	}

	private function render_pagination_controls( string $tab, string $param, int $current_page, int $total_pages, int $total_items, array $query_args = [] ): void {
		if ( $total_pages <= 1 ) {
			return;
		}

		$query_args = array_filter( $query_args, static fn ( $value ): bool => '' !== (string) $value );
		$base_url   = add_query_arg( $query_args, admin_url( 'admin.php?page=overcustomise-products&tab=' . $tab ) );
		$prev_url   = add_query_arg( $param, max( 1, $current_page - 1 ), $base_url );
		$next_url   = add_query_arg( $param, min( $total_pages, $current_page + 1 ), $base_url );
		?>
		<div class="oc-pagination">
			<span style="font-size:12px;color:var(--oc-gray-500);">
				<?php
				echo esc_html(
					sprintf(
					/* translators: 1: current page, 2: total pages, 3: total items. */
						__( 'Page %1$d of %2$d (%3$d total)', 'overcustomise' ),
						$current_page,
						$total_pages,
						$total_items
					)
				);
				?>
			</span>
			<a class="button oc-ajax-nav<?php echo 1 === $current_page ? ' disabled' : ''; ?>" href="<?php echo esc_url( $prev_url ); ?>" <?php echo 1 === $current_page ? 'aria-disabled="true"' : ''; ?>><?php esc_html_e( 'Prev', 'overcustomise' ); ?></a>
			<form method="get" class="oc-pagination-form oc-ajax-form">
				<input type="hidden" name="page" value="overcustomise-products" />
				<input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>" />
				<?php foreach ( $query_args as $name => $value ) : ?>
					<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" />
				<?php endforeach; ?>
				<label for="oc-<?php echo esc_attr( $param ); ?>" style="font-size:12px;color:var(--oc-gray-500);"><?php esc_html_e( 'Page', 'overcustomise' ); ?></label>
				<input id="oc-<?php echo esc_attr( $param ); ?>" class="oc-input" type="number" name="<?php echo esc_attr( $param ); ?>" value="<?php echo esc_attr( (string) $current_page ); ?>" min="1" max="<?php echo esc_attr( (string) $total_pages ); ?>" style="width:78px;" />
				<button type="submit" class="button"><?php esc_html_e( 'Go', 'overcustomise' ); ?></button>
			</form>
			<a class="button oc-ajax-nav<?php echo $current_page >= $total_pages ? ' disabled' : ''; ?>" href="<?php echo esc_url( $next_url ); ?>" <?php echo $current_page >= $total_pages ? 'aria-disabled="true"' : ''; ?>><?php esc_html_e( 'Next', 'overcustomise' ); ?></a>
		</div>
		<?php
	}

	/** Render a design assignment <select> for one product/variant row. */
	private function render_design_select( array $designs, array $assigned_design_ids, int $product_id, int $variant_id, int $assigned_id ): void {
		$is_assigned = static fn ( $design ): bool => isset( $assigned_design_ids[ (int) $design->id ] );
		$designs     = array_merge(
			array_values( array_filter( $designs, static fn ( $design ): bool => ! $is_assigned( $design ) ) ),
			array_values( array_filter( $designs, $is_assigned ) )
		);
		?>
		<select class="oc-design-assign-select oc-select"
				data-product-id="<?php echo esc_attr( (string) $product_id ); ?>"
				data-variant-id="<?php echo esc_attr( (string) $variant_id ); ?>"
				style="min-width:200px;max-width:300px;">
			<option value="0"><?php esc_html_e( '— No Design —', 'overcustomise' ); ?></option>
			<?php foreach ( $designs as $design ) : ?>
				<option value="<?php echo esc_attr( $design->id ); ?>"
						data-assigned="<?php echo isset( $assigned_design_ids[ (int) $design->id ] ) ? '1' : '0'; ?>"
						<?php selected( $assigned_id, $design->id ); ?>>
					<?php echo esc_html( $design->name ?: __( 'Untitled Design #', 'overcustomise' ) . $design->id ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/** Build design ID => thumbnail URL once to avoid per-row print-area lookups. */
	private function get_design_thumbnail_map( array $designs ): array {
		$design_ids = array_values( array_unique( array_filter( array_map( static fn ( $design ): int => (int) $design->id, $designs ) ) ) );
		if ( empty( $design_ids ) ) {
			return [];
		}

		global $wpdb;
		$placeholders = implode( ',', array_fill( 0, count( $design_ids ), '%d' ) );
		$rows         = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT design_id, mockup_attachment_id FROM {$wpdb->prefix}oc_design_print_areas WHERE design_id IN ($placeholders) AND mockup_attachment_id IS NOT NULL AND mockup_attachment_id > 0 ORDER BY design_id ASC, sort_order ASC",
				...$design_ids
			)
		) ?: [];

		$attachment_ids = [];
		foreach ( $rows as $row ) {
			$design_id = (int) $row->design_id;
			if ( ! isset( $attachment_ids[ $design_id ] ) ) {
				$attachment_ids[ $design_id ] = (int) $row->mockup_attachment_id;
			}
		}

		$thumbs = array_fill_keys( $design_ids, '' );
		foreach ( $designs as $design ) {
			$design_id = (int) $design->id;
			if ( ! empty( $attachment_ids[ $design_id ] ) ) {
				$thumbs[ $design_id ] = wp_get_attachment_image_url( $attachment_ids[ $design_id ], 'thumbnail' ) ?: '';
			}
		}

		return $thumbs;
	}

	/** Render per-assignment alternate design controls. */
	private function render_design_variants_control( array $designs, array $design_thumbs, int $product_id, int $variant_id, string $variants_json ): void {
		$variants = json_decode( $variants_json, true );
		if ( ! is_array( $variants ) ) {
			$variants = [];
		}

		$normalised_variants = [];
		foreach ( $variants as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$design_id = absint( $item['designId'] ?? 0 );
			$design    = $design_id ? OC_DB::get_design( $design_id ) : null;
			if ( ! $design || ! (bool) $design->active ) {
				continue;
			}
			$thumb                 = $design_thumbs[ $design_id ] ?? '';
			$normalised_variants[] = [
				'designId' => $design_id,
				'label'    => sanitize_text_field( (string) ( $item['label'] ?? '' ) ) ?: ( $design->name ?: __( 'Untitled Design #', 'overcustomise' ) . $design_id ),
				'thumbUrl' => $thumb,
			];
		}
		$variants = $normalised_variants;
		?>
		<div class="oc-design-variants-admin" data-product-id="<?php echo esc_attr( (string) $product_id ); ?>" data-variant-id="<?php echo esc_attr( (string) $variant_id ); ?>">
			<input type="hidden" class="oc-design-variants-data" value="<?php echo esc_attr( wp_json_encode( $variants ) ); ?>" />
			<div class="oc-design-variants-list"></div>
			<select class="oc-select oc-design-variant-design-select" style="max-width:180px;">
				<option value="0"><?php esc_html_e( 'Choose design…', 'overcustomise' ); ?></option>
				<?php
				foreach ( $designs as $design ) :
					$thumb = $design_thumbs[ (int) $design->id ] ?? '';
					?>
					<option value="<?php echo esc_attr( $design->id ); ?>" data-thumb-url="<?php echo esc_url( $thumb ?: '' ); ?>">
						<?php echo esc_html( $design->name ?: __( 'Untitled Design #', 'overcustomise' ) . $design->id ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<button type="button" class="button oc-design-variant-add"><?php esc_html_e( 'Add variant', 'overcustomise' ); ?></button>
			<span class="oc-design-variants-status" style="margin-left:6px;color:var(--oc-gray-500);font-size:11px;" aria-live="polite"></span>
		</div>
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

		$current_page = $this->get_admin_page_number( 'design_page' );
		$search       = $this->get_admin_search_term( 'design_search' );
		$design_query = OC_DB::get_designs_with_area_counts_paginated( $current_page, self::ADMIN_PAGE_SIZE, $search );
		$designs      = $design_query['items'];
		$design_total = (int) $design_query['total'];
		$total_pages  = (int) $design_query['total_pages'];
		if ( $current_page > $total_pages ) {
			$current_page = $total_pages;
			$design_query = OC_DB::get_designs_with_area_counts_paginated( $current_page, self::ADMIN_PAGE_SIZE, $search );
			$designs      = $design_query['items'];
		}
		?>
		<div class="oc-card">
			<div class="oc-card-header">
				<h2><?php esc_html_e( 'Designs', 'overcustomise' ); ?></h2>
				<form method="get" class="oc-list-filter oc-ajax-form">
					<input type="hidden" name="page" value="overcustomise-products" />
					<input type="hidden" name="tab" value="designs" />
					<span style="font-size:12px;color:var(--oc-gray-400);">
						<?php echo esc_html( (string) $design_total ); ?> <?php echo esc_html( 1 === $design_total ? __( 'design', 'overcustomise' ) : __( 'designs', 'overcustomise' ) ); ?>
					</span>
					<input type="search" class="oc-input" name="design_search" value="<?php echo esc_attr( $search ); ?>"
							placeholder="<?php esc_attr_e( 'Filter all designs…', 'overcustomise' ); ?>" />
					<button type="submit" class="button"><?php esc_html_e( 'Filter', 'overcustomise' ); ?></button>
					<?php if ( '' !== $search ) : ?>
						<a class="button oc-ajax-nav" href="<?php echo esc_url( admin_url( 'admin.php?page=overcustomise-products&tab=designs' ) ); ?>"><?php esc_html_e( 'Clear', 'overcustomise' ); ?></a>
					<?php endif; ?>
				</form>
			</div>

			<?php if ( 0 === $design_total ) : ?>
				<div class="oc-empty">
					<span class="oc-empty-icon">📐</span>
					<h3><?php echo '' !== $search ? esc_html__( 'No matching designs found', 'overcustomise' ) : esc_html__( 'No designs yet', 'overcustomise' ); ?></h3>
					<p><?php echo '' !== $search ? esc_html__( 'Try a different design name.', 'overcustomise' ) : esc_html__( 'Create your first design to enable the customiser on products.', 'overcustomise' ); ?></p>
					<?php if ( '' === $search ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=overcustomise-products&tab=designs&action=edit' ) ); ?>"
							class="oc-btn oc-btn-primary">
							+ <?php esc_html_e( 'Add Design', 'overcustomise' ); ?>
						</a>
					<?php endif; ?>
				</div>
			<?php else : ?>
				<?php $this->render_pagination_controls( 'designs', 'design_page', $current_page, $total_pages, $design_total, [ 'design_search' => $search ] ); ?>
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
												admin_url( 'admin.php?page=overcustomise-products&tab=designs&action=duplicate&id=' . (int) $design->id ),
												'oc_duplicate_design_' . $design->id
											) ); ?>"
												class="oc-btn oc-btn-secondary oc-btn-sm">
												<?php esc_html_e( 'Clone', 'overcustomise' ); ?>
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
				<?php $this->render_pagination_controls( 'designs', 'design_page', $current_page, $total_pages, $design_total, [ 'design_search' => $search ] ); ?>
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

		$id                 = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		$design             = $id > 0 ? OC_DB::get_design( $id ) : null;
		$areas              = $id > 0 ? OC_DB::get_design_print_areas( $id ) : [];
		$design_custom_type = $design && in_array( $design->custom_type, [ 'text_only', 'photo_text' ], true ) ? $design->custom_type : 'text_only';
		$design_flat_rate   = $design ? (float) $design->flat_rate : max( 0, (float) OC_Admin_Settings::get( 'flat_rate_default' ) );

		// Existing installs store the mockup on each area. Resolve one shared
		// attachment without requiring a destructive data migration.
		$shared_mockup_id  = 0;
		$shared_mockup_url = '';
		foreach ( $areas as $area ) {
			$candidate_id  = absint( $area->mockup_attachment_id ?? 0 );
			$candidate_url = $candidate_id ? ( wp_get_attachment_image_url( $candidate_id, 'large' ) ?: '' ) : '';
			if ( $candidate_url ) {
				$shared_mockup_id  = $candidate_id;
				$shared_mockup_url = $candidate_url;
				break;
			}
		}

		// Keep the existing per-area payload shape for saved autosaves and installs.
		$areas_js = array_map(
			function ( $area ) use ( $shared_mockup_id, $shared_mockup_url ) {
				return [
					'id'             => (int) $area->id,
					'label'          => $area->label,
					'method'         => $area->print_method,
					'material'       => isset( $area->engraving_material ) ? (string) $area->engraving_material : 'silver_metal',
					'unit'           => isset( $area->canvas_unit ) ? (string) $area->canvas_unit : 'px',
					'mockupId'       => $shared_mockup_id,
					'mockupUrl'      => $shared_mockup_url,
					'storedMockupId' => absint( $area->mockup_attachment_id ?? 0 ),
					'x'              => (int) $area->canvas_x,
					'y'              => (int) $area->canvas_y,
					'w'              => (int) $area->canvas_w,
					'h'              => (int) $area->canvas_h,
					'dpi'            => isset( $area->canvas_dpi ) ? (int) $area->canvas_dpi : 300,
					'rotation'       => isset( $area->canvas_rotation ) ? (int) $area->canvas_rotation : 0,
					'sortOrder'      => (int) $area->sort_order,
					'visible'        => (bool) $area->visible,
					'locked'         => (bool) $area->locked,
				];
			},
			$areas
		);

		wp_enqueue_media();
		$products_asset = OC_PATH . 'assets/build/admin/products-page.asset.php';
		$products_meta  = file_exists( $products_asset ) ? include $products_asset : [];
		$products_ver   = isset( $products_meta['version'] ) ? (string) $products_meta['version'] : OC_VERSION;
		wp_enqueue_script(
			'oc-products-page',
			OC_ASSETS_URL . 'admin/products-page.js',
			[ 'wp-util' ],
			$products_ver,
			true
		);
		// Build layers JSON for JS.
		$all_layers = $id > 0 ? OC_DB::get_design_layers( $id ) : [];
		$layers_js  = array_map(
			function ( $l ) {
				$type            = sanitize_key( (string) $l->type );
				$settings        = OC_Cart::normalise_layer_settings( $l->settings ?? [], $type );
				$stored_settings = is_string( $l->settings ?? null ) ? json_decode( $l->settings, true ) : [];
				if ( 'ai_image' === $type && is_array( $stored_settings ) ) {
					$settings['ai_prompt_instruction'] = is_string( $stored_settings['ai_prompt_instruction'] ?? null ) ? $stored_settings['ai_prompt_instruction'] : '';
				}
				$attachment_id       = absint( $settings['default_attachment_id'] ?? 0 );
				$is_valid_attachment = 'mask' === $type
					? self::design_mask_attachment_is_valid( $attachment_id, $attachment_id )
					: OC_Upload_Handler::admin_default_attachment_is_valid( $attachment_id );
				if ( $attachment_id && $is_valid_attachment ) {
					$settings['default_attachment_url'] = (string) wp_get_attachment_url( $attachment_id );
				} else {
					$settings['default_attachment_id']  = 0;
					$settings['default_attachment_url'] = '';
				}
				return [
					'id'        => (int) $l->id,
					'areaId'    => (int) $l->area_id,
					'type'      => $type,
					'label'     => $l->label,
					'x'         => (int) $l->x,
					'y'         => (int) $l->y,
					'w'         => (int) $l->w,
					'h'         => (int) $l->h,
					'sortOrder' => (int) $l->sort_order,
					'visible'   => (bool) $l->visible,
					'locked'    => (bool) $l->locked,
					'settings'  => $settings,
				];
			},
			$all_layers
		);

		$clipart_groups            = OC_DB::get_clipart_groups();
		$clipart_group_ids_by_item = [];
		foreach ( $clipart_groups as $group ) {
			foreach ( (array) $group->clipart_ids as $clipart_id ) {
				$clipart_group_ids_by_item[ (int) $clipart_id ][] = (int) $group->id;
			}
		}
		$method_settings    = OC_Admin_Print_Methods::get();
		$selectable_methods = OC_Admin_Print_Methods::enabled_methods();
		foreach ( $areas as $area ) {
			$existing_method = sanitize_key( (string) ( $area->print_method ?? '' ) );
			if ( isset( $method_settings[ $existing_method ] ) ) {
				$selectable_methods[] = $existing_method;
			}
		}
		$selectable_methods = array_values( array_unique( $selectable_methods ) );
		$method_labels      = [];
		foreach ( $selectable_methods as $method ) {
			$method_labels[ $method ] = (string) $method_settings[ $method ]['label'];
		}

		wp_localize_script(
			'oc-products-page',
			'ocProductsData',
			[
				'designId'      => $id,
				'areas'         => $areas_js,
				'layers'        => $layers_js,
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'oc-products-nonce' ),
				'mediaTitle'    => __( 'Select Mockup Image', 'overcustomise' ),
				'mediaBtn'      => __( 'Use as Mockup', 'overcustomise' ),
				'fonts'         => OC_Plugin::browser_fonts(),
				'fontGroups'    => array_map(
					function ( $g ) {
						return [
							'id'      => (int) $g->id,
							'name'    => $g->name,
							'fontIds' => array_map( 'intval', $g->font_ids ),
						]; },
					OC_DB::get_font_groups()
				),
				'colours'       => array_map(
					function ( $c ) {
						return [
							'id'   => (int) $c->id,
							'name' => $c->name,
							'hex'  => $c->hex,
						]; },
					OC_DB::get_colours( true )
				),
				'imageFilters'  => array_map(
					function ( $f ) {
						return [
							'id'    => (int) $f->id,
							'name'  => $f->name,
							'key'   => $f->filter_key,
							'value' => (float) $f->value,
							'isAi'  => 'ai' === (string) $f->filter_key,
						]; },
					OC_DB::get_image_filters( true )
				),
				'colourGroups'  => array_map(
					function ( $g ) {
						return [
							'id'        => (int) $g->id,
							'name'      => $g->name,
							'colourIds' => array_map( 'intval', $g->colour_ids ),
						]; },
					OC_DB::get_colour_groups()
				),
				'clipartGroups' => array_map(
					function ( $g ) {
						return [
							'id'   => (int) $g->id,
							'name' => $g->name,
						]; },
					$clipart_groups
				),
				'clipartItems'  => array_values(
					array_filter(
						array_map(
							function ( $c ) use ( $clipart_group_ids_by_item ) {
								return [
									'id'                  => (int) $c->id,
									'name'                => $c->name,
									'fileType'            => strtolower( (string) $c->file_type ),
									'url'                 => OC_Admin_Clipart::get_clipart_url( (string) $c->file_path ),
									'active'              => (bool) $c->active,
									'groupIds'            => $clipart_group_ids_by_item[ (int) $c->id ] ?? [],
									'colourChangeable'    => ! property_exists( $c, 'colour_changeable' ) || (bool) $c->colour_changeable,
									'allowedPrintMethods' => self::normalise_clipart_print_methods( (string) ( $c->allowed_print_methods ?? '' ) ),
								];
							},
							OC_DB::get_clipart( true )
						),
						static fn ( array $item ): bool => '' !== $item['url']
					)
				),
				'methodLabels'  => $method_labels,
			]
		);
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
					<span id="oc-autosave-indicator" class="oc-autosave-indicator"></span>
					<label class="oc-toggle-label" style="margin:0;font-size:12px;">
						<span class="oc-toggle">
							<input type="checkbox" name="oc_active" id="oc_active" value="1" form="oc-design-form"
									<?php checked( $design ? $design->active : 1, 1 ); ?> />
							<span class="oc-toggle-slider"></span>
						</span>
						<?php esc_html_e( 'Active', 'overcustomise' ); ?>
					</label>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=overcustomise-products&tab=designs' ) ); ?>" class="oc-btn oc-btn-secondary"><?php esc_html_e( 'Cancel', 'overcustomise' ); ?></a>
					<button type="submit" form="oc-design-form" id="oc-save-design-btn" class="oc-btn oc-btn-primary" disabled aria-disabled="true"><?php esc_html_e( 'Save Design', 'overcustomise' ); ?></button>
				</div>
			</div>

			<form method="post" id="oc-design-form">
				<?php wp_nonce_field( 'oc_save_design', 'oc_design_nonce' ); ?>
				<input type="hidden" name="oc_design_id" value="<?php echo esc_attr( (string) $id ); ?>" />
				<!-- JS serialises areas here before submit -->
				<div id="oc-hidden-fields"></div>

				<div class="oc-design-editor">

					<!-- LEFT: design settings + areas list -->
					<div class="oc-editor-left">
						<div class="oc-editor-section">
							<div class="oc-editor-section-header">
								<h3><?php esc_html_e( 'Design Settings', 'overcustomise' ); ?></h3>
							</div>
							<div class="oc-editor-field">
								<label for="oc_custom_type"><?php esc_html_e( 'Customisation Type', 'overcustomise' ); ?></label>
								<select id="oc_custom_type" name="oc_custom_type" class="oc-select" style="width:100%;">
									<option value="text_only" <?php selected( $design_custom_type, 'text_only' ); ?>><?php esc_html_e( 'Text Only', 'overcustomise' ); ?></option>
									<option value="photo_text" <?php selected( $design_custom_type, 'photo_text' ); ?>><?php esc_html_e( 'Photo + Text', 'overcustomise' ); ?></option>
								</select>
							</div>
							<div class="oc-editor-field" style="margin-bottom:0;">
								<label for="oc_flat_rate"><?php esc_html_e( 'Flat Rate (AUD)', 'overcustomise' ); ?></label>
								<input type="number" id="oc_flat_rate" name="oc_flat_rate" class="oc-input" min="0" step="0.01" inputmode="decimal" value="<?php echo esc_attr( number_format( $design_flat_rate, 2, '.', '' ) ); ?>" style="width:100%;" />
							</div>
							<div class="oc-editor-field" style="margin-top:12px;margin-bottom:0;">
								<label><?php esc_html_e( 'Design Mockup', 'overcustomise' ); ?> <span class="oc-hint"><?php esc_html_e( '(shared by every print area)', 'overcustomise' ); ?></span></label>
								<div class="oc-mockup-thumb" id="oc-mockup-thumb">
									<img id="oc-mockup-thumb-img" src="" alt="" style="display:none;" />
									<span id="oc-mockup-thumb-empty" style="font-size:12px;color:var(--oc-gray-400);"><?php esc_html_e( 'No mockup set', 'overcustomise' ); ?></span>
									<div class="oc-mockup-thumb-actions">
										<button type="button" id="oc-choose-mockup-btn" class="oc-icon-btn" aria-label="<?php esc_attr_e( 'Change mockup', 'overcustomise' ); ?>" title="<?php esc_attr_e( 'Change mockup', 'overcustomise' ); ?>">&#9998;</button>
										<button type="button" id="oc-remove-mockup-btn" class="oc-icon-btn oc-icon-btn--danger" aria-label="<?php esc_attr_e( 'Remove mockup', 'overcustomise' ); ?>" title="<?php esc_attr_e( 'Remove mockup', 'overcustomise' ); ?>" style="display:none;">&#128465;</button>
									</div>
								</div>
							</div>
							<div class="oc-editor-field" style="margin-top:12px;margin-bottom:0;">
								<label><?php esc_html_e( 'Design Mask', 'overcustomise' ); ?> <span class="oc-hint"><?php esc_html_e( '(one preview overlay per design)', 'overcustomise' ); ?></span></label>
								<div class="oc-mockup-thumb" id="oc-design-mask-thumb">
									<img id="oc-design-mask-thumb-img" src="" alt="" style="display:none;" />
									<span id="oc-design-mask-empty" style="font-size:12px;color:var(--oc-gray-400);"><?php esc_html_e( 'No mask set', 'overcustomise' ); ?></span>
									<div class="oc-mockup-thumb-actions">
										<button type="button" id="oc-choose-design-mask-btn" class="oc-icon-btn" aria-label="<?php esc_attr_e( 'Choose design mask', 'overcustomise' ); ?>" title="<?php esc_attr_e( 'Choose design mask', 'overcustomise' ); ?>">&#9998;</button>
										<button type="button" id="oc-remove-design-mask-btn" class="oc-icon-btn oc-icon-btn--danger" aria-label="<?php esc_attr_e( 'Remove design mask', 'overcustomise' ); ?>" title="<?php esc_attr_e( 'Remove design mask', 'overcustomise' ); ?>" style="display:none;">&#128465;</button>
									</div>
								</div>
								<span class="oc-hint"><?php esc_html_e( 'Use a transparent PNG. It appears above customer artwork but is excluded from print files.', 'overcustomise' ); ?></span>
							</div>
						</div>

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
									<select id="oc-prop-method" class="oc-select" style="width:100%;" aria-label="<?php esc_attr_e( 'Print Method', 'overcustomise' ); ?>">
										<?php foreach ( $method_labels as $method => $method_label ) : ?>
											<option value="<?php echo esc_attr( $method ); ?>"><?php echo esc_html( $method_label ); ?></option>
										<?php endforeach; ?>
									</select>
								</div>
								<div class="oc-editor-field" id="oc-prop-engraving-material-wrap" style="display:none;">
									<label for="oc-prop-engraving-material"><?php esc_html_e( 'Engraving Material', 'overcustomise' ); ?></label>
									<select id="oc-prop-engraving-material" class="oc-select" style="width:100%;">
										<option value="glass"><?php esc_html_e( 'Glass', 'overcustomise' ); ?></option>
										<option value="gold_metal"><?php esc_html_e( 'Gold Metal', 'overcustomise' ); ?></option>
										<option value="silver_metal"><?php esc_html_e( 'Silver Metal', 'overcustomise' ); ?></option>
										<option value="silver_plaque"><?php esc_html_e( 'Silver Plaque (Black Engraving)', 'overcustomise' ); ?></option>
										<option value="black_metal"><?php esc_html_e( 'Black Metal', 'overcustomise' ); ?></option>
										<option value="wood"><?php esc_html_e( 'Wood', 'overcustomise' ); ?></option>
										<option value="leather"><?php esc_html_e( 'Leather', 'overcustomise' ); ?></option>
									</select>
								</div>
								<div style="display:flex;align-items:center;justify-content:space-between;margin-top:12px;margin-bottom:8px;">
									<h3 style="font-size:10px;text-transform:uppercase;letter-spacing:.07em;font-weight:700;color:var(--oc-gray-400);margin:0;"><?php esc_html_e( 'Print Bounds', 'overcustomise' ); ?></h3>
								</div>
								<div class="oc-bounds-grid oc-bounds-grid--area">
									<div class="oc-editor-field"><label>X</label><input type="number" id="oc-prop-x" class="oc-input" min="0" style="width:100%;" /></div>
									<div class="oc-editor-field"><label>Y</label><input type="number" id="oc-prop-y" class="oc-input" min="0" style="width:100%;" /></div>
									<div class="oc-editor-field"><label><?php esc_html_e( 'Rotate', 'overcustomise' ); ?></label><input type="number" id="oc-prop-rotation" class="oc-input" min="0" max="359" step="1" style="width:100%;" /></div>
									<div class="oc-bounds-size-row" style="display:flex;grid-column:1 / -1;gap:8px;align-items:flex-end;flex-wrap:nowrap;">
										<div class="oc-editor-field" style="flex:1 1 0;min-width:0;margin-bottom:0;"><label>W</label><input type="number" id="oc-prop-w" class="oc-input" min="1" style="width:100%;" /></div>
										<button type="button" id="oc-prop-ratio-lock" class="oc-layer-action-btn" aria-label="<?php esc_attr_e( 'Lock aspect ratio', 'overcustomise' ); ?>" title="<?php esc_attr_e( 'Lock aspect ratio', 'overcustomise' ); ?>" style="flex:0 0 32px;height:36px;margin-bottom:0;align-self:flex-end;"></button>
										<div class="oc-editor-field" style="flex:1 1 0;min-width:0;margin-bottom:0;"><label>H</label><input type="number" id="oc-prop-h" class="oc-input" min="1" style="width:100%;" /></div>
										<div class="oc-editor-field oc-bounds-unit-field" style="flex:0 0 64px;margin-bottom:0;">
											<label for="oc-prop-unit"><?php esc_html_e( 'Unit', 'overcustomise' ); ?></label>
											<select id="oc-prop-unit" class="oc-select" style="width:100%;">
												<option value="px">px</option>
												<option value="mm">mm</option>
												<option value="cm">cm</option>
												<option value="in">in</option>
											</select>
										</div>
									</div>
									<div class="oc-editor-field" style="grid-column:1 / -1;margin-bottom:0;">
										<label for="oc-prop-dpi"><?php esc_html_e( 'Canvas DPI', 'overcustomise' ); ?></label>
										<input type="number" id="oc-prop-dpi" class="oc-input" min="1" max="1200" step="1" style="width:100%;" />
										<p class="description" style="margin:4px 0 0;font-size:11px;line-height:1.3;"><?php esc_html_e( 'Controls how mm, cm and inch sizes appear on the mockup preview. Print/export dimensions stay unchanged.', 'overcustomise' ); ?></p>
									</div>
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
								<div class="oc-bounds-rotate-handle" title="<?php esc_attr_e( 'Rotate print area', 'overcustomise' ); ?>"></div>
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
								<button type="button" class="oc-layer-type-btn" data-type="ai_image"><span class="oc-layer-type-btn-icon" style="color:#db2777;">AI</span><span><?php esc_html_e( 'AI Image', 'overcustomise' ); ?></span></button>
								<button type="button" class="oc-layer-type-btn" data-type="clipmask"><span class="oc-layer-type-btn-icon" style="color:#0d9488;">&#9711;</span><span><?php esc_html_e( 'Clipping Mask', 'overcustomise' ); ?></span></button>
								<button type="button" class="oc-layer-type-btn" data-type="spotify"><span class="oc-layer-type-btn-icon" style="color:#1db954;">&#x266b;</span><span><?php esc_html_e( 'Spotify', 'overcustomise' ); ?></span></button>
								<button type="button" class="oc-layer-type-btn" data-type="lineart"><span class="oc-layer-type-btn-icon" style="color:#d97706;">&#x270f;</span><span><?php esc_html_e( 'Line Art', 'overcustomise' ); ?></span></button>
				<button type="button" class="oc-layer-type-btn" data-type="clipart"><span class="oc-layer-type-btn-icon" style="color:#dc2626;">&#x2726;</span><span><?php esc_html_e( 'Clipart', 'overcustomise' ); ?></span></button>
				<button type="button" class="oc-layer-type-btn" data-type="night_sky"><span class="oc-layer-type-btn-icon" style="color:#4338ca;">&#x2606;</span><span><?php esc_html_e( 'Night Sky', 'overcustomise' ); ?></span></button>
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
			wp_die( esc_html__( 'Permission denied.', 'overcustomise' ), '', [ 'response' => 403 ] );
		}
		if ( ! wp_verify_nonce( sanitize_key( $_POST['oc_design_nonce'] ?? '' ), 'oc_save_design' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'overcustomise' ) );
		}

		global $wpdb;

		$design_id   = absint( $_POST['oc_design_id'] ?? 0 );
		$name_raw    = is_scalar( $_POST['oc_design_name'] ?? null ) ? (string) $_POST['oc_design_name'] : '';
		$name        = sanitize_text_field( wp_unslash( $name_raw ) );
		$custom_type = null;
		$flat_rate   = null;
		$active      = isset( $_POST['oc_active'] ) ? 1 : 0;

		if ( array_key_exists( 'oc_custom_type', $_POST ) ) {
			$posted_custom_type = is_scalar( $_POST['oc_custom_type'] ) ? sanitize_key( wp_unslash( (string) $_POST['oc_custom_type'] ) ) : '';
			if ( ! in_array( $posted_custom_type, [ 'text_only', 'photo_text' ], true ) ) {
				wp_die( esc_html__( 'Invalid customisation type.', 'overcustomise' ) );
			}
			$custom_type = $posted_custom_type;
		}
		if ( array_key_exists( 'oc_flat_rate', $_POST ) ) {
			$posted_rate = is_numeric( $_POST['oc_flat_rate'] ) ? (float) $_POST['oc_flat_rate'] : 0.0;
			$flat_rate   = number_format( max( 0, min( 1000000, is_finite( $posted_rate ) ? $posted_rate : 0.0 ) ), 2, '.', '' );
		}
		if ( 0 === $design_id ) {
			$custom_type = $custom_type ?? 'text_only';
			$flat_rate   = $flat_rate ?? number_format( max( 0, (float) OC_Admin_Settings::get( 'flat_rate_default' ) ), 2, '.', '' );
		}

		if ( ! $name || strlen( $name ) > 150 ) {
			wp_die( esc_html__( 'Design name is required.', 'overcustomise' ) );
		}

		$posted_areas  = $_POST['oc_design_areas'] ?? [];
		$posted_layers = $_POST['oc_layers'] ?? [];
		if ( ! is_array( $posted_areas ) || ! is_array( $posted_layers ) || count( $posted_areas ) > 100 || count( $posted_layers ) > 1000 ) {
			wp_die( esc_html__( 'Invalid design data.', 'overcustomise' ) );
		}
		$submitted_existing_area_ids  = [];
		$submitted_existing_layer_ids = [];
		$submitted_mask_ids           = [];
		$submitted_new_mask_count     = 0;
		$valid_area_indexes           = [];
		$seen_area_indexes            = [];
		foreach ( $posted_areas as $area_index => $area_data ) {
			$normalised_index = is_int( $area_index ) || ctype_digit( $area_index ) ? (int) $area_index : -1;
			if ( ! is_array( $area_data ) || $normalised_index < 0 || isset( $seen_area_indexes[ $normalised_index ] ) ) {
				wp_die( esc_html__( 'Invalid print area data.', 'overcustomise' ) );
			}
			$seen_area_indexes[ $normalised_index ] = true;
			$area_label_raw                         = is_scalar( $area_data['label'] ?? null ) ? (string) $area_data['label'] : '';
			$area_label                             = sanitize_text_field( wp_unslash( $area_label_raw ) );
			if ( $area_label ) {
				$valid_area_indexes[ $normalised_index ] = true;
			}
			$area_id = (int) ( $area_data['id'] ?? 0 );
			if ( $area_id > 0 ) {
				if ( 0 === $design_id || in_array( $area_id, $submitted_existing_area_ids, true ) ) {
					wp_die( esc_html__( 'A submitted print area is stale or invalid. Reload the design and try again.', 'overcustomise' ) );
				}
				$submitted_existing_area_ids[] = $area_id;
			}
		}
		foreach ( $posted_layers as $layer_data ) {
			if ( ! is_array( $layer_data ) ) {
				wp_die( esc_html__( 'A layer references an unknown print area.', 'overcustomise' ) );
			}
			$layer_area_index = is_scalar( $layer_data['area_index'] ?? null ) && ctype_digit( (string) $layer_data['area_index'] ) ? (int) $layer_data['area_index'] : -1;
			if ( ! isset( $valid_area_indexes[ $layer_area_index ] ) ) {
				wp_die( esc_html__( 'A layer references an unknown print area.', 'overcustomise' ) );
			}
			$layer_id   = (int) ( $layer_data['id'] ?? 0 );
			$layer_type = sanitize_key( is_scalar( $layer_data['type'] ?? null ) ? (string) $layer_data['type'] : '' );
			$area_method = sanitize_key( is_scalar( $posted_areas[ $layer_area_index ]['print_method'] ?? null ) ? (string) $posted_areas[ $layer_area_index ]['print_method'] : '' );
			if ( 'night_sky' === $layer_type && 'embroidery' === $area_method ) {
				wp_die( esc_html__( 'Night Sky layers are not supported for embroidery. Remove the layer or choose another print method.', 'overcustomise' ) );
			}
			if ( 'ai_image' === $layer_type ) {
				$settings_raw = wp_unslash( $layer_data['settings'] ?? '{}' );
				$settings     = json_decode( is_string( $settings_raw ) && strlen( $settings_raw ) <= 262144 ? $settings_raw : '', true );
				$instruction  = is_array( $settings ) && is_string( $settings['ai_prompt_instruction'] ?? null ) ? trim( wp_check_invalid_utf8( $settings['ai_prompt_instruction'], true ) ) : '';
				if ( '' === $instruction || strlen( $instruction ) > 16384 ) {
					wp_die( esc_html__( 'AI Image layers require an admin instruction. Open the AI / Prompt tab for the layer and enter an instruction before saving.', 'overcustomise' ) );
				}
			}
			if ( 'mask' === $layer_type ) {
				if ( $layer_id > 0 ) {
					$submitted_mask_ids[] = $layer_id;
				} else {
					++$submitted_new_mask_count;
				}
			}
			if ( $layer_id > 0 ) {
				if ( 0 === $design_id || in_array( $layer_id, $submitted_existing_layer_ids, true ) ) {
					wp_die( esc_html__( 'A submitted layer is stale or invalid. Reload the design and try again.', 'overcustomise' ) );
				}
				$submitted_existing_layer_ids[] = $layer_id;
			}
		}
		if ( 0 === $design_id && count( $submitted_mask_ids ) + $submitted_new_mask_count > 1 ) {
			wp_die( esc_html__( 'Only one design mask may be added.', 'overcustomise' ) );
		}

		$data = [
			'name'   => $name,
			'active' => $active,
		];
		$fmt  = [ '%s', '%d' ];
		if ( null !== $custom_type ) {
			$data['custom_type'] = $custom_type;
			$fmt[]               = '%s';
		}
		if ( null !== $flat_rate ) {
			$data['flat_rate'] = $flat_rate;
			$fmt[]             = '%s';
		}

		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			wp_die( esc_html__( 'Could not start the design save transaction.', 'overcustomise' ) );
		}

		try {
			$existing_area_ids       = [];
			$existing_area_data      = [];
			$existing_layer_area_ids = [];
			$existing_layer_settings = [];
			if ( $design_id > 0 ) {
				$locked_design = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}oc_designs WHERE id = %d FOR UPDATE", $design_id ) );
				if ( ! $locked_design ) {
					throw new RuntimeException( 'Design not found.' );
				}
				$existing_areas = $wpdb->get_results( $wpdb->prepare( "SELECT id, print_method, mockup_attachment_id FROM {$wpdb->prefix}oc_design_print_areas WHERE design_id = %d FOR UPDATE", $design_id ) ) ?: [];
				foreach ( $existing_areas as $existing_area ) {
					$existing_area_ids[]                            = (int) $existing_area->id;
					$existing_area_data[ (int) $existing_area->id ] = $existing_area;
				}
				if ( array_diff( $submitted_existing_area_ids, $existing_area_ids ) ) {
					throw new RuntimeException( 'Submitted area does not belong to this design.' );
				}
				$existing_layers = $wpdb->get_results( $wpdb->prepare( "SELECT id, area_id, settings FROM {$wpdb->prefix}oc_design_layers WHERE design_id = %d FOR UPDATE", $design_id ) ) ?: [];
				foreach ( $existing_layers as $existing_layer ) {
					$existing_layer_area_ids[ (int) $existing_layer->id ] = (int) $existing_layer->area_id;
					$existing_layer_settings[ (int) $existing_layer->id ] = OC_Cart::normalise_layer_settings( $existing_layer->settings ?? [] );
				}
				if ( array_diff( $submitted_existing_layer_ids, array_keys( $existing_layer_area_ids ) ) ) {
					throw new RuntimeException( 'Submitted layer does not belong to this design.' );
				}
				if ( count( $submitted_mask_ids ) + $submitted_new_mask_count > 1 ) {
					if ( $submitted_new_mask_count ) {
						throw new RuntimeException( 'Only one design mask may be added.' );
					}
				}

				$data['clone_priority'] = 0;
				$fmt[]                  = '%d';
				if ( false === $wpdb->update( "{$wpdb->prefix}oc_designs", $data, [ 'id' => $design_id ], $fmt, [ '%d' ] ) ) {
					throw new RuntimeException( 'Could not update design.' );
				}
			} else {
				if ( false === $wpdb->insert( "{$wpdb->prefix}oc_designs", $data, $fmt ) ) {
					throw new RuntimeException( 'Could not create design.' );
				}
				$design_id = (int) $wpdb->insert_id;
				if ( $design_id <= 0 ) {
					throw new RuntimeException( 'Could not identify created design.' );
				}
			}

			// Save print areas and build the submitted index to database ID map.
			$submitted_ids  = [];
			$area_id_map    = [];
			$used_area_keys = [];

			foreach ( $posted_areas as $area_index => $area_data ) {
				$area_id   = (int) ( $area_data['id'] ?? 0 );
				$label_raw = is_scalar( $area_data['label'] ?? null ) ? (string) $area_data['label'] : '';
				$label     = sanitize_text_field( wp_unslash( $label_raw ) );
				if ( ! $label ) {
					continue;
				}
				if ( strlen( $label ) > 100 ) {
					throw new RuntimeException( 'Print area label is too long.' );
				}

				$area_key = substr( sanitize_key( $label ), 0, 50 );
				if ( '' === $area_key ) {
					throw new RuntimeException( 'Print area label must contain a usable key.' );
				}
				if ( isset( $used_area_keys[ $area_key ] ) ) {
					throw new RuntimeException( 'Print area labels must be unique.' );
				}
				$used_area_keys[ $area_key ] = true;
				$method                      = sanitize_key( is_scalar( $area_data['print_method'] ?? null ) ? (string) $area_data['print_method'] : '' );
				$all_methods                 = OC_Admin_Print_Methods::get();
				$old_method                  = sanitize_key( (string) ( $existing_area_data[ $area_id ]->print_method ?? '' ) );
				if ( ! isset( $all_methods[ $method ] )
					|| ( ( 0 === $area_id || $method !== $old_method ) && ! OC_Admin_Print_Methods::is_enabled( $method ) )
				) {
					throw new RuntimeException( 'The selected print method is disabled or invalid.' );
				}
				$material      = in_array( $area_data['engraving_material'] ?? '', [ 'glass', 'gold_metal', 'silver_metal', 'silver_plaque', 'black_metal', 'wood', 'leather' ], true )
					? sanitize_key( $area_data['engraving_material'] )
					: 'silver_metal';
				$unit          = in_array( $area_data['canvas_unit'] ?? '', [ 'px', 'mm', 'cm', 'in' ], true )
					? sanitize_key( $area_data['canvas_unit'] )
					: 'px';
				$mockup_id     = (int) ( $area_data['mockup_attachment_id'] ?? 0 );
				$old_mockup_id = (int) ( $existing_area_data[ $area_id ]->mockup_attachment_id ?? 0 );
				if ( $mockup_id && ! self::design_attachment_is_valid( $mockup_id, $old_mockup_id, true ) ) {
					throw new RuntimeException( 'The selected mockup is invalid or inaccessible.' );
				}
				$canvas_x   = min( 100000, max( 0, (int) ( $area_data['canvas_x'] ?? 0 ) ) );
				$canvas_y   = min( 100000, max( 0, (int) ( $area_data['canvas_y'] ?? 0 ) ) );
				$canvas_w   = min( 100000, max( 1, (int) ( $area_data['canvas_w'] ?? 300 ) ) );
				$canvas_h   = min( 100000, max( 1, (int) ( $area_data['canvas_h'] ?? 300 ) ) );
				$canvas_dpi = min( 1200, max( 1, (int) ( $area_data['canvas_dpi'] ?? 300 ) ) );
				$rotation   = (int) ( $area_data['canvas_rotation'] ?? 0 );
				$rotation   = ( ( $rotation % 360 ) + 360 ) % 360;
				$sort_order = (int) ( $area_data['sort_order'] ?? 0 );

				$row     = [
					'design_id'            => $design_id,
					'area_key'             => $area_key,
					'label'                => $label,
					'print_method'         => $method,
					'engraving_material'   => $material,
					'canvas_unit'          => $unit,
					'mockup_attachment_id' => $mockup_id ?: null,
					'canvas_x'             => $canvas_x,
					'canvas_y'             => $canvas_y,
					'canvas_w'             => $canvas_w,
					'canvas_h'             => $canvas_h,
					'canvas_dpi'           => $canvas_dpi,
					'canvas_rotation'      => $rotation,
					'sort_order'           => $sort_order,
					'visible'              => isset( $area_data['visible'] ) && $area_data['visible'] !== '0' ? 1 : 0,
					'locked'               => ! empty( $area_data['locked'] ) ? 1 : 0,
				];
				$row_fmt = [ '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d' ];

				if ( $area_id > 0 ) {
					if ( false === $wpdb->update(
						"{$wpdb->prefix}oc_design_print_areas",
						$row,
						[
							'id'        => $area_id,
							'design_id' => $design_id,
						],
						$row_fmt,
						[ '%d', '%d' ]
					) ) {
						throw new RuntimeException( 'Could not update print area.' );
					}
					$db_area_id = $area_id;
				} else {
					if ( false === $wpdb->insert( "{$wpdb->prefix}oc_design_print_areas", $row, $row_fmt ) ) {
						throw new RuntimeException( 'Could not create print area.' );
					}
					$db_area_id = (int) $wpdb->insert_id;
					if ( $db_area_id <= 0 ) {
						throw new RuntimeException( 'Could not identify created print area.' );
					}
				}

				$submitted_ids[]                  = $db_area_id;
				$area_id_map[ (int) $area_index ] = $db_area_id;
			}

			$valid_types = [ 'text', 'textarea', 'image', 'ai_image', 'clipmask', 'mask', 'spotify', 'lineart', 'clipart', 'night_sky' ];
			foreach ( $posted_layers as $sort => $layer_data ) {
				$area_index = (int) ( $layer_data['area_index'] ?? 0 );
				$area_db_id = $area_id_map[ $area_index ] ?? 0;
				$layer_id   = (int) ( $layer_data['id'] ?? 0 );

				$type = sanitize_key( is_scalar( $layer_data['type'] ?? null ) ? (string) $layer_data['type'] : '' );
				if ( ! in_array( $type, $valid_types, true ) ) {
					throw new RuntimeException( 'Invalid design layer type.' );
				}
				$label_raw = is_scalar( $layer_data['label'] ?? null ) ? (string) $layer_data['label'] : '';
				$label     = sanitize_text_field( wp_unslash( $label_raw ) );
				if ( strlen( $label ) > 100 ) {
					throw new RuntimeException( 'Layer label is too long.' );
				}
				// WordPress slashes $_POST, so unslash before decoding settings.
				$settings_raw = wp_unslash( $layer_data['settings'] ?? '{}' );
				$decoded      = json_decode( is_string( $settings_raw ) && strlen( $settings_raw ) <= 262144 ? $settings_raw : '', true );
				if ( ! is_array( $decoded ) ) {
					throw new RuntimeException( 'Invalid design layer settings.' );
				}
				$area_method = '';
				foreach ( $posted_areas as $posted_area_index => $posted_area ) {
					if ( (int) $posted_area_index === $area_index ) {
						$area_method = sanitize_key( is_scalar( $posted_area['print_method'] ?? null ) ? (string) $posted_area['print_method'] : '' );
						break;
					}
				}
				if ( 'night_sky' === $type && 'embroidery' === $area_method ) {
					throw new RuntimeException( 'Night Sky layers are not supported for embroidery.' );
				}
				$settings = wp_json_encode(
					self::normalise_design_layer_settings(
						$decoded,
						$type,
						$area_method,
						$existing_layer_settings[ $layer_id ] ?? []
					)
				);
				if ( false === $settings ) {
					throw new RuntimeException( 'Could not encode design layer settings.' );
				}

				$layer_row = [
					'design_id'  => $design_id,
					'area_id'    => $area_db_id,
					'type'       => $type,
					'label'      => $label,
					'x'          => min( 100000, max( 0, (int) ( $layer_data['x'] ?? 0 ) ) ),
					'y'          => min( 100000, max( 0, (int) ( $layer_data['y'] ?? 0 ) ) ),
					'w'          => min( 100000, max( 1, (int) ( $layer_data['w'] ?? 200 ) ) ),
					'h'          => min( 100000, max( 1, (int) ( $layer_data['h'] ?? 50 ) ) ),
					'sort_order' => (int) ( $layer_data['sort_order'] ?? $sort ),
					'visible'    => isset( $layer_data['visible'] ) && $layer_data['visible'] !== '0' ? 1 : 0,
					'locked'     => ! empty( $layer_data['locked'] ) ? 1 : 0,
					'settings'   => $settings ?: '{}',
				];
				$layer_fmt = [ '%d', '%d', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%s' ];

				if ( $layer_id > 0 ) {
					if ( ( $existing_layer_area_ids[ $layer_id ] ?? 0 ) !== $area_db_id ) {
						throw new RuntimeException( 'Submitted layer does not belong to this print area.' );
					}
					$updated = $wpdb->update(
						"{$wpdb->prefix}oc_design_layers",
						$layer_row,
						[
							'id'        => $layer_id,
							'design_id' => $design_id,
							'area_id'   => $area_db_id,
						],
						$layer_fmt,
						[ '%d', '%d', '%d' ]
					);
					if ( false === $updated ) {
						throw new RuntimeException( 'Could not update design layer.' );
					}
				} elseif ( false === $wpdb->insert( "{$wpdb->prefix}oc_design_layers", $layer_row, $layer_fmt ) ) {
					throw new RuntimeException( 'Could not create design layer.' );
				}
			}

			// Remove only records omitted by this submission, after all ownership checks pass.
			foreach ( array_diff( array_keys( $existing_layer_area_ids ), $submitted_existing_layer_ids ) as $del_id ) {
				if ( 1 !== $wpdb->delete(
					"{$wpdb->prefix}oc_design_layers",
					[
						'id'        => (int) $del_id,
						'design_id' => $design_id,
					],
					[ '%d', '%d' ]
				) ) {
					throw new RuntimeException( 'Could not remove design layer.' );
				}
			}
			foreach ( array_diff( $existing_area_ids, $submitted_ids ) as $del_id ) {
				if ( 1 !== $wpdb->delete(
					"{$wpdb->prefix}oc_design_print_areas",
					[
						'id'        => (int) $del_id,
						'design_id' => $design_id,
					],
					[ '%d', '%d' ]
				) ) {
					throw new RuntimeException( 'Could not remove print area.' );
				}
			}

			if ( false === $wpdb->query( 'COMMIT' ) ) {
				throw new RuntimeException( 'Could not commit design.' );
			}
		} catch ( Throwable $error ) {
			$wpdb->query( 'ROLLBACK' );
			wp_die( esc_html__( 'Could not save design. No changes were applied.', 'overcustomise' ) );
		}

		OC_Autosave::clear( $design_id );
		$this->clear_design_cache();

		return $design_id;
	}

	private function clear_design_cache(): void {
		OC_Cache::invalidate_group( OC_Cache::GROUP );
	}

	/** Validate media selected for a design without breaking an unchanged existing relationship. */
	private static function design_attachment_is_valid( int $attachment_id, int $existing_attachment_id = 0, bool $require_image = false ): bool {
		if ( 'attachment' !== get_post_type( $attachment_id ) || ! OC_Upload_Handler::admin_default_attachment_is_valid( $attachment_id ) ) {
			return false;
		}
		if ( $require_image && ! str_starts_with( (string) get_post_mime_type( $attachment_id ), 'image/' ) ) {
			return false;
		}

		return $attachment_id === $existing_attachment_id || current_user_can( 'edit_post', $attachment_id );
	}

	/** Validate a preview-only mask without requiring locally stored production artwork. */
	private static function design_mask_attachment_is_valid( int $attachment_id, int $existing_attachment_id = 0 ): bool {
		$url          = (string) wp_get_attachment_url( $attachment_id );
		$mime         = strtolower( (string) get_post_mime_type( $attachment_id ) );
		$url_path     = (string) wp_parse_url( $url, PHP_URL_PATH );
		$is_supported = in_array( $mime, [ 'image/png', 'image/x-png', 'image/svg+xml', 'image/webp' ], true )
			|| ( str_starts_with( $mime, 'image/' ) && in_array( strtolower( pathinfo( $url_path, PATHINFO_EXTENSION ) ), [ 'png', 'svg', 'webp' ], true ) );
		if (
			$attachment_id <= 0
			|| 'attachment' !== get_post_type( $attachment_id )
			|| ! $is_supported
			|| '' === $url
		) {
			return false;
		}

		return $attachment_id === $existing_attachment_id || current_user_can( 'edit_post', $attachment_id );
	}

	/** Normalize layer settings and retain only live, related resources. */
	private static function normalise_design_layer_settings( array $raw, string $type, string $print_method, array $existing = [] ): array {
		$settings = OC_Cart::normalise_layer_settings( $raw, $type );
		if ( 'ai_image' === $type ) {
			$raw_instruction = is_string( $raw['ai_prompt_instruction'] ?? null ) ? $raw['ai_prompt_instruction'] : '';
			$instruction     = trim( wp_check_invalid_utf8( $raw_instruction, true ) );
			if ( '' === $instruction || trim( $raw_instruction ) !== $instruction || strlen( $instruction ) > 16384 ) {
				throw new RuntimeException( 'AI Image layers require a valid admin instruction.' );
			}
			$settings['ai_prompt_instruction'] = $instruction;
		}

		$font_groups       = OC_DB::get_font_groups();
		$colour_groups     = OC_DB::get_colour_groups();
		$clipart_groups    = OC_DB::get_clipart_groups();
		$font_group_ids    = array_map( static fn ( $group ): int => (int) $group->id, $font_groups );
		$colour_group_ids  = array_map( static fn ( $group ): int => (int) $group->id, $colour_groups );
		$clipart_group_ids = array_map( static fn ( $group ): int => (int) $group->id, $clipart_groups );

		$settings['font_groups']    = array_values( array_intersect( $settings['font_groups'], $font_group_ids ) );
		$settings['colour_groups']  = array_values( array_intersect( $settings['colour_groups'], $colour_group_ids ) );
		$settings['clipart_groups'] = array_values( array_intersect( $settings['clipart_groups'], $clipart_group_ids ) );

		$active_font_ids  = array_map( static fn ( $font ): int => (int) $font->id, OC_DB::get_fonts( true ) );
		$allowed_font_ids = $settings['font_groups']
			? array_values( array_intersect( $active_font_ids, OC_DB::get_font_ids_for_groups( $settings['font_groups'] ) ) )
			: $active_font_ids;
		if ( $settings['default_font_id'] && ! in_array( $settings['default_font_id'], $allowed_font_ids, true ) ) {
			$settings['default_font_id'] = 0;
		}

		if ( $settings['colour_groups'] ) {
			$allowed_colours = array_values(
				array_filter(
					array_map(
						static fn ( $colour ): ?string => sanitize_hex_color( (string) ( $colour->hex ?? '' ) ),
						OC_DB::get_colours_for_groups( $settings['colour_groups'] )
					)
				)
			);
			if ( $allowed_colours && ! in_array( strtolower( $settings['default_color'] ), array_map( 'strtolower', $allowed_colours ), true ) ) {
				$settings['default_color'] = $allowed_colours[0];
			}
		}

		$active_filter_ids            = array_map( static fn ( $filter ): int => (int) $filter->id, OC_DB::get_image_filters( true ) );
		$settings['image_filter_ids'] = array_values( array_intersect( $settings['image_filter_ids'], $active_filter_ids ) );
		if ( ! in_array( $settings['default_image_filter_id'], $settings['image_filter_ids'], true ) ) {
			$settings['default_image_filter_id'] = 0;
		}

		$attachment_id          = in_array( $type, [ 'image', 'clipmask', 'mask' ], true ) ? (int) $settings['default_attachment_id'] : 0;
		$existing_attachment_id = (int) ( $existing['default_attachment_id'] ?? 0 );
		$is_valid_attachment    = 'mask' === $type
			? self::design_mask_attachment_is_valid( $attachment_id, $existing_attachment_id )
			: self::design_attachment_is_valid( $attachment_id, $existing_attachment_id, true );
		if ( $attachment_id && ! $is_valid_attachment ) {
			throw new RuntimeException( 'The selected default artwork is invalid or inaccessible.' );
		}
		$settings['default_attachment_id']  = $attachment_id;
		$settings['default_attachment_url'] = '';

		$default_clipart_id = 'clipart' === $type ? (int) $settings['default_clipart_id'] : 0;
		$active_clipart     = [];
		foreach ( OC_DB::get_clipart( true ) as $clipart ) {
			if ( '' !== OC_Admin_Clipart::get_clipart_url( (string) ( $clipart->file_path ?? '' ) ) ) {
				$active_clipart[ (int) $clipart->id ] = $clipart;
			}
		}
		if ( $default_clipart_id && isset( $active_clipart[ $default_clipart_id ] ) ) {
			$clipart           = $active_clipart[ $default_clipart_id ];
			$allowed_methods   = self::normalise_clipart_print_methods( (string) ( $clipart->allowed_print_methods ?? '' ) );
			$in_selected_group = empty( $settings['clipart_groups'] );
			foreach ( $clipart_groups as $group ) {
				if ( in_array( (int) $group->id, $settings['clipart_groups'], true )
					&& in_array( $default_clipart_id, array_map( 'intval', (array) $group->clipart_ids ), true )
				) {
					$in_selected_group = true;
					break;
				}
			}
			if ( ! $in_selected_group || ( $allowed_methods && ! in_array( $print_method, $allowed_methods, true ) ) ) {
				$default_clipart_id = 0;
			}
		} else {
			$default_clipart_id = 0;
		}
		$settings['default_clipart_id']           = $default_clipart_id;
		$settings['default_clipart_url']          = '';
		$settings['default_clipart_recolourable'] = $default_clipart_id
			&& 'svg' === strtolower( (string) ( $active_clipart[ $default_clipart_id ]->file_type ?? '' ) )
			&& ( ! property_exists( $active_clipart[ $default_clipart_id ], 'colour_changeable' ) || (bool) $active_clipart[ $default_clipart_id ]->colour_changeable );

		return $settings;
	}

	private function handle_design_delete(): void {
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;

		if ( ! current_user_can( 'manage_woocommerce' ) || ! $id || ! isset( $_GET['_wpnonce'] )
			|| ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'oc_delete_design_' . $id )
		) {
			wp_die( esc_html__( 'Security check failed.', 'overcustomise' ) );
		}

		global $wpdb;
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			wp_die( esc_html__( 'Could not start the design deletion transaction.', 'overcustomise' ) );
		}

		$csv_path = '';
		try {
			$design_exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}oc_designs WHERE id = %d FOR UPDATE", $id ) );
			if ( ! $design_exists ) {
				throw new RuntimeException( 'Design not found.' );
			}

			$template = $wpdb->get_row( $wpdb->prepare( "SELECT id, csv_file_path FROM {$wpdb->prefix}oc_vdp_templates WHERE design_id = %d FOR UPDATE", $id ) );
			if ( $template ) {
				$csv_path = (string) $template->csv_file_path;
				if ( false === $wpdb->delete( "{$wpdb->prefix}oc_vdp_fields", [ 'template_id' => (int) $template->id ], [ '%d' ] ) ) {
					throw new RuntimeException( 'Could not delete VDP fields.' );
				}
			}
			$deleted_template    = $wpdb->delete( "{$wpdb->prefix}oc_vdp_templates", [ 'design_id' => $id ], [ '%d' ] );
			$deleted_layers      = $wpdb->delete( "{$wpdb->prefix}oc_design_layers", [ 'design_id' => $id ], [ '%d' ] );
			$deleted_areas       = $wpdb->delete( "{$wpdb->prefix}oc_design_print_areas", [ 'design_id' => $id ], [ '%d' ] );
			$updated_variants    = OC_DB::remove_design_from_assignment_variants( $id );
			$deleted_assignments = $wpdb->delete( "{$wpdb->prefix}oc_product_assignments", [ 'design_id' => $id ], [ '%d' ] );
			$deleted_design      = $wpdb->delete( "{$wpdb->prefix}oc_designs", [ 'id' => $id ], [ '%d' ] );
			if ( false === $deleted_template || false === $deleted_layers || false === $deleted_areas || ! $updated_variants || false === $deleted_assignments || 1 !== $deleted_design ) {
				throw new RuntimeException( 'Could not delete design records.' );
			}
			if ( false === $wpdb->query( 'COMMIT' ) ) {
				throw new RuntimeException( 'Could not commit design deletion.' );
			}
		} catch ( Throwable $error ) {
			$wpdb->query( 'ROLLBACK' );
			wp_die( esc_html__( 'Could not delete design. No changes were applied.', 'overcustomise' ) );
		}

		if ( '' !== $csv_path ) {
			OC_Rest_API::delete_vdp_file( $csv_path );
		}
		OC_Autosave::clear( $id );
		$this->clear_design_cache();

		wp_safe_redirect( admin_url( 'admin.php?page=overcustomise-products&tab=designs&deleted=1' ) );
		exit;
	}

	private function handle_design_duplicate(): void {
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;

		if ( ! current_user_can( 'manage_woocommerce' ) || ! $id || ! isset( $_GET['_wpnonce'] )
			|| ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'oc_duplicate_design_' . $id )
		) {
			wp_die( esc_html__( 'Security check failed.', 'overcustomise' ) );
		}

		global $wpdb;
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			wp_die( esc_html__( 'Could not start the design duplication transaction.', 'overcustomise' ) );
		}

		$new_id = 0;
		try {
			$original = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}oc_designs WHERE id = %d FOR UPDATE", $id ) );
			if ( ! $original ) {
				throw new RuntimeException( 'Design not found.' );
			}
			$areas  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}oc_design_print_areas WHERE design_id = %d ORDER BY sort_order ASC FOR UPDATE", $id ) ) ?: [];
			$layers = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}oc_design_layers WHERE design_id = %d ORDER BY area_id ASC, sort_order ASC FOR UPDATE", $id ) ) ?: [];

			if ( false === $wpdb->insert(
				"{$wpdb->prefix}oc_designs",
				[
					'name'           => substr( (string) $original->name, 0, 143 ) . __( ' (Copy)', 'overcustomise' ),
					'custom_type'    => $original->custom_type,
					'flat_rate'      => $original->flat_rate,
					'active'         => 0,
					'clone_priority' => 1,
				],
				[ '%s', '%s', '%s', '%d', '%d' ]
			) ) {
				throw new RuntimeException( 'Could not copy design.' );
			}
			$new_id = (int) $wpdb->insert_id;
			if ( $new_id <= 0 ) {
				throw new RuntimeException( 'Could not identify copied design.' );
			}

			$area_id_map = [];
			foreach ( $areas as $area ) {
				$inserted    = $wpdb->insert(
					"{$wpdb->prefix}oc_design_print_areas",
					[
						'design_id'            => $new_id,
						'area_key'             => $area->area_key,
						'label'                => $area->label,
						'print_method'         => $area->print_method,
						'engraving_material'   => $area->engraving_material ?? 'silver_metal',
						'canvas_unit'          => $area->canvas_unit ?? 'px',
						'mockup_attachment_id' => $area->mockup_attachment_id,
						'canvas_x'             => $area->canvas_x,
						'canvas_y'             => $area->canvas_y,
						'canvas_w'             => $area->canvas_w,
						'canvas_h'             => $area->canvas_h,
						'canvas_dpi'           => isset( $area->canvas_dpi ) ? (int) $area->canvas_dpi : 300,
						'canvas_rotation'      => isset( $area->canvas_rotation ) ? (int) $area->canvas_rotation : 0,
						'sort_order'           => $area->sort_order,
						'visible'              => $area->visible,
						'locked'               => $area->locked,
					],
					[ '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d' ]
				);
				$new_area_id = (int) $wpdb->insert_id;
				if ( false === $inserted || $new_area_id <= 0 ) {
					throw new RuntimeException( 'Could not copy print area.' );
				}
				$area_id_map[ (int) $area->id ] = $new_area_id;
			}

			foreach ( $layers as $layer ) {
				$new_area_id = $area_id_map[ (int) $layer->area_id ] ?? 0;
				if ( ! $new_area_id || false === $wpdb->insert(
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
				) ) {
					throw new RuntimeException( 'Could not copy design layer.' );
				}
			}

			if ( false === $wpdb->query( 'COMMIT' ) ) {
				throw new RuntimeException( 'Could not commit copied design.' );
			}
		} catch ( Throwable $error ) {
			$wpdb->query( 'ROLLBACK' );
			wp_die( esc_html__( 'Could not duplicate design. No changes were applied.', 'overcustomise' ) );
		}

		$this->clear_design_cache();

		wp_safe_redirect( admin_url( 'admin.php?page=overcustomise-products&tab=designs&duplicated=1' ) );
		exit;
	}

	private static function normalise_clipart_print_methods( string $raw ): array {
		if ( '' === trim( $raw ) ) {
			return [];
		}

		$decoded    = json_decode( $raw, true );
		$methods    = is_array( $decoded ) ? $decoded : explode( ',', $raw );
		$allowed    = [ 'engraving', 'uv', 'embroidery', 'sublimation' ];
		$normalised = [];
		foreach ( $methods as $method ) {
			if ( is_scalar( $method ) ) {
				$normalised[] = sanitize_key( (string) $method );
			}
		}

		return array_values( array_unique( array_intersect( $allowed, $normalised ) ) );
	}
}
