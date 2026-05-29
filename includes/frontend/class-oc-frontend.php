<?php
/**
 * Frontend integration — hooks into product pages.
 * Loads the design assigned to the product, injects the customiser panel,
 * enqueues assets, and validates add-to-cart.
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

class OC_Frontend {

	private ?object $design = null;
	private array   $areas  = [];
	private array   $layers = [];
	private ?array  $edit_cart_item = null;

	public function register(): void {
		add_action( 'wp',                                    [ $this, 'maybe_load_design' ],  10 );
		add_action( 'wp_enqueue_scripts',                    [ $this, 'enqueue_assets' ],     20 );
		add_action( 'woocommerce_before_add_to_cart_button', [ $this, 'inject_panel' ],       10 );
		add_filter( 'woocommerce_add_to_cart_validation',    [ $this, 'validate' ],           10, 3 );
	}

	// ── Design pre-load ───────────────────────────────────────────────────────

	public function maybe_load_design(): void {
		$edit_cart_key = isset( $_GET['oc_edit_cart_key'] ) ? sanitize_text_field( wp_unslash( $_GET['oc_edit_cart_key'] ) ) : '';

		if ( $edit_cart_key !== '' ) {
			$cart = WC()->cart ?? null;
			if ( $cart && isset( $cart->cart_contents[ $edit_cart_key ] ) ) {
				$ci = $cart->cart_contents[ $edit_cart_key ];
				if ( ! empty( $ci['_oc_customisation'] ) ) {
					$this->edit_cart_item = [
						'key'      => $edit_cart_key,
						'customisation' => $ci['_oc_customisation'],
					];
				}
			}
		}

		if ( null !== $this->edit_cart_item ) {
			$cs = $this->edit_cart_item['customisation'];
			$design_id = 0;
			if ( isset( $cs['v'] ) && 2 === (int) $cs['v'] ) {
				$design_id = (int) ( $cs['designId'] ?? 0 );
			}
			if ( ! $design_id ) {
				return;
			}

			$design = OC_DB::get_design( $design_id );
			if ( ! $design || ! (bool) $design->active ) {
				return;
			}

			$areas = OC_DB::get_design_print_areas( (int) $design->id );
			if ( empty( $areas ) ) {
				return;
			}

			$this->design = $design;
			$this->areas  = $areas;
			$this->layers = OC_DB::get_design_layers( (int) $design->id );
			return;
		}

		if ( ! is_product() ) {
			return;
		}

		$product_id = (int) get_queried_object_id();

		$assignment = $this->get_assignment( $product_id );
		if ( ! $assignment ) {
			OC_Logger::info( "OC Frontend: no design assignment for product {$product_id}." );
			return;
		}

		$design = OC_DB::get_design( (int) $assignment->design_id );
		if ( ! $design ) {
			OC_Logger::warning( "OC Frontend: design {$assignment->design_id} not found." );
			return;
		}
		if ( ! (bool) $design->active ) {
			OC_Logger::info( "OC Frontend: design {$design->id} is inactive." );
			return;
		}

		$areas = OC_DB::get_design_print_areas( (int) $design->id );
		if ( empty( $areas ) ) {
			OC_Logger::info( "OC Frontend: design {$design->id} has no print areas." );
			return;
		}

		$this->design = $design;
		$this->areas  = $areas;
		$this->layers = OC_DB::get_design_layers( (int) $design->id );
	}

	/**
	 * Look up a product → design assignment.
	 * Priority: variant-specific → parent-level → first variant with any assignment.
	 *
	 * @param int $product_id  Parent or variation product ID.
	 * @param int $variant_id  0 = unknown / page load; >0 = specific variant chosen via JS.
	 */
	private function get_assignment( int $product_id, int $variant_id = 0 ): ?object {
		global $wpdb;

		$product = wc_get_product( $product_id );
		if ( ! $product ) return null;

		// Resolve parent + variant IDs regardless of which was passed.
		if ( $product->is_type( 'variation' ) ) {
			$variant_id = $product_id;
			$product_id = $product->get_parent_id();
		}

		// 1. Variant-specific assignment (exact match).
		if ( $variant_id > 0 ) {
			$row = $wpdb->get_row( $wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}oc_product_assignments
				 WHERE product_id = %d AND variant_id = %d LIMIT 1",
				$product_id, $variant_id
			) );
			if ( $row ) return $row;
		}

		// 2. Parent-level assignment (covers all variants).
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}oc_product_assignments
			 WHERE product_id = %d AND variant_id = 0 LIMIT 1",
			$product_id
		) );
		if ( $row ) return $row;

		// 3. For a variable product with no parent-level assignment, fall back to
		//    any variant's assignment so the customiser still appears on page load.
		if ( $product->is_type( 'variable' ) || ( $product_id !== (int) get_queried_object_id() ) ) {
			$variation_ids = wc_get_product( $product_id )?->get_children() ?? [];
			if ( ! empty( $variation_ids ) ) {
				$phs = implode( ',', array_fill( 0, count( $variation_ids ), '%d' ) );
				$row = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT * FROM {$wpdb->prefix}oc_product_assignments
						 WHERE product_id = %d AND variant_id IN ($phs)
						 ORDER BY variant_id ASC LIMIT 1",
						$product_id,
						...$variation_ids
					)
				);
				if ( $row ) return $row;
			}
		}

		return null;
	}

	// ── Assets ────────────────────────────────────────────────────────────────

	public function enqueue_assets(): void {
		if ( null === $this->design ) {
			return;
		}

		wp_enqueue_script(
			'oc-customiser-app',
			OC_ASSETS_URL . 'frontend/customiser-app.js',
			[],
			OC_VERSION,
			true
		);

		// Pass all data via wp_localize_script — no WP Interactivity API dependency.
		wp_localize_script( 'oc-customiser-app', 'ocCustomiserData', $this->build_state() );

		if ( file_exists( OC_PATH . 'assets/build/frontend/customiser-app.css' ) ) {
			wp_enqueue_style( 'oc-customiser-app', OC_ASSETS_URL . 'frontend/customiser-app.css', [], OC_VERSION );
		}

		wp_add_inline_style( 'woocommerce-general', $this->get_panel_css() );
	}

	/** Build the complete initial state for the Interactivity API store. */
	private function build_state(): array {
		$all_fonts   = OC_Font_Registry::get_fonts_for_js();
		$all_colours = OC_DB::get_colours( true );

		// Group layers by area ID.
		$layers_by_area = [];
		foreach ( $this->layers as $layer ) {
			$layers_by_area[ (int) $layer->area_id ][] = $layer;
		}

		// Build areas with their layers.
		$areas_js    = [];
		$layer_inputs = []; // layerId → default input values

		foreach ( $this->areas as $area ) {
			$area_layers  = $layers_by_area[ (int) $area->id ] ?? [];
			$layers_js    = [];

			$mockup_url = '';
			$mockup_w   = 0;
			$mockup_h   = 0;
			if ( $area->mockup_attachment_id ) {
				// Use the same 'large' size as the admin editor so coordinates
				// (stored in 'large'-image pixel space) remain consistent.
				$img_src  = wp_get_attachment_image_src( (int) $area->mockup_attachment_id, 'large' );
				if ( $img_src ) {
					$mockup_url = $img_src[0];
					$mockup_w   = (int) $img_src[1];
					$mockup_h   = (int) $img_src[2];
				}
			}

			foreach ( $area_layers as $layer ) {
				if ( ! (bool) $layer->visible ) continue;

				$settings = $layer->settings ? json_decode( $layer->settings, true ) : [];
				if ( ! is_array( $settings ) ) $settings = [];

				$layers_js[] = [
					'id'       => (int) $layer->id,
					'type'     => $layer->type,
					'label'    => $layer->label,
					'x'        => (int) $layer->x,
					'y'        => (int) $layer->y,
					'w'        => (int) $layer->w,
					'h'        => (int) $layer->h,
					'required' => ! empty( $settings['required'] ),
					'locked'   => ! empty( $layer->locked ),
					'settings' => $settings,
				];

				// Default input per layer.
				$layer_inputs[ (int) $layer->id ] = [
					'value'         => $settings['default_text'] ?? '',
					'fontId'        => 0,
					'colorHex'      => '#000000',
					'attachmentId'  => 0,
					'attachmentUrl' => '',
					'clipartId'     => 0,
					'clipartUrl'    => '',
				];
			}

			$areas_js[] = [
				'id'          => (int) $area->id,
				'label'       => $area->label,
				'printMethod' => $area->print_method,
				'mockupUrl'   => $mockup_url,
				'mockupW'     => $mockup_w,
				'mockupH'     => $mockup_h,
				'bounds'      => [
					'x' => (int) $area->canvas_x,
					'y' => (int) $area->canvas_y,
					'w' => (int) $area->canvas_w,
					'h' => (int) $area->canvas_h,
					'rotation' => isset( $area->canvas_rotation ) ? (int) $area->canvas_rotation : 0,
				],
				'layers'      => $layers_js,
			];
		}

		// Colours as swatches.
		$colours_js = array_map( function ( $c ) {
			return [ 'id' => (int) $c->id, 'name' => $c->name, 'hex' => $c->hex ];
		}, $all_colours );

		// Clipart items grouped per clipart layer.
		$clipart_by_layer = $this->build_clipart_by_layer();

		// Flatten all clipart groups across layers into a unique list.
		$clipart_groups = [];
		foreach ( $clipart_by_layer as $layer_items ) {
			foreach ( $layer_items as $item ) {
				foreach ( $item['groupNames'] as $gn ) {
					if ( ! in_array( $gn, $clipart_groups, true ) ) {
						$clipart_groups[] = $gn;
					}
				}
			}
		}
		sort( $clipart_groups );

		$edit_mode = false;
		$cart_key  = '';
		if ( null !== $this->edit_cart_item ) {
			$edit_mode = true;
			$cart_key  = $this->edit_cart_item['key'];
			$cs = $this->edit_cart_item['customisation'];
			if ( isset( $cs['v'] ) && 2 === (int) $cs['v'] && isset( $cs['layers'] ) && is_array( $cs['layers'] ) ) {
				foreach ( $cs['layers'] as $lid => $ldata ) {
					if ( ! is_array( $ldata ) || ! isset( $layer_inputs[ (int) $lid ] ) ) continue;
					$layer_inputs[ (int) $lid ] = array_merge( $layer_inputs[ (int) $lid ], $ldata );
				}
			}
		}

		return [
			'designId'        => (int) $this->design->id,
			'designName'      => $this->design->name,
			'flatRate'        => (float) $this->design->flat_rate,
			'areas'           => $areas_js,
			'fonts'           => $all_fonts,
			'colours'         => $colours_js,
			'clipartByLayer'  => $clipart_by_layer,
			'clipartGroups'   => $clipart_groups,
			'layerInputs'     => $layer_inputs,
			'activeAreaIndex' => 0,
			'isLoading'       => false,
			'uploadUrl'       => rest_url( 'overcustomise/v1/upload-artwork' ),
			'savePreviewUrl'  => rest_url( 'overcustomise/v1/save-preview' ),
			'validateSpotifyUrl' => rest_url( 'overcustomise/v1/validate-spotify' ),
			'updateCartItemUrl' => rest_url( 'overcustomise/v1/update-cart-item' ),
			'uploadNonce'     => wp_create_nonce( 'wp_rest' ),
			'requestToken'    => OC_Rest_API::issue_public_token(),
			'maxUploadSizeMb' => (int) OC_Admin_Settings::get( 'max_upload_size_mb' ) ?: 10,
			'allowedFormats'  => (array) OC_Admin_Settings::get( 'allowed_upload_formats' ),
			'editMode'        => $edit_mode,
			'cartKey'         => $cart_key,
		];
	}

	/** Load clipart items for all clipart layers. */
	private function build_clipart_by_layer(): array {
		global $wpdb;

		$by_layer   = [];
		$upload_dir = wp_upload_dir();

		foreach ( $this->layers as $layer ) {
			if ( $layer->type !== 'clipart' ) continue;

			$settings   = $layer->settings ? json_decode( $layer->settings, true ) : [];
			$group_ids  = is_array( $settings ) ? ( $settings['clipart_groups'] ?? [] ) : [];
			$layer_id   = (int) $layer->id;

			if ( ! empty( $group_ids ) ) {
				$placeholders = implode( ',', array_fill( 0, count( $group_ids ), '%d' ) );
				$items = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT DISTINCT c.id, c.name, c.file_path, GROUP_CONCAT(DISTINCT cg.name SEPARATOR '||') AS group_names
						 FROM {$wpdb->prefix}oc_clipart c
						 JOIN {$wpdb->prefix}oc_clipart_group_items gi ON gi.clipart_id = c.id
						 JOIN {$wpdb->prefix}oc_clipart_groups cg ON cg.id = gi.group_id
						 WHERE gi.group_id IN ($placeholders) AND c.active = 1
						 GROUP BY c.id, c.name, c.file_path
						 ORDER BY c.name ASC",
						...$group_ids
					)
				) ?: [];
			} else {
				$items = $wpdb->get_results(
					"SELECT c.id, c.name, c.file_path, GROUP_CONCAT(DISTINCT cg.name SEPARATOR '||') AS group_names
					 FROM {$wpdb->prefix}oc_clipart c
					 LEFT JOIN {$wpdb->prefix}oc_clipart_group_items gi ON gi.clipart_id = c.id
					 LEFT JOIN {$wpdb->prefix}oc_clipart_groups cg ON cg.id = gi.group_id
					 WHERE c.active = 1
					 GROUP BY c.id, c.name, c.file_path
					 ORDER BY c.name ASC"
				) ?: [];
			}

			$by_layer[ $layer_id ] = array_map( function ( $item ) use ( $upload_dir ) {
				$url = $upload_dir['baseurl'] . '/overcustomise/clipart/' . basename( $item->file_path );
				$groupNames = $item->group_names ? array_filter( array_map( 'trim', explode( '||', $item->group_names ) ) ) : [];
				return [
					'id'         => (int) $item->id,
					'name'       => $item->name,
					'url'        => $url,
					'groupNames' => $groupNames,
				];
			}, $items );
		}

		return $by_layer;
	}

	// ── Panel injection ───────────────────────────────────────────────────────

	public function inject_panel(): void {
		if ( ! is_singular( 'product' ) ) {
			return;
		}

		// Admin hint when no design is assigned — only visible to shop managers.
		if ( null === $this->design ) {
			if ( current_user_can( 'manage_woocommerce' ) ) {
				$product_id  = (int) get_queried_object_id();
				$assign_url  = admin_url( 'admin.php?page=overcustomise-products&tab=products' );
				echo '<div style="background:#fff3cd;border:1px solid #ffc107;border-radius:4px;padding:10px 14px;margin-bottom:16px;font-size:13px;">'
				   . '&#9888; <strong>OverCustomise:</strong> No design is assigned to this product. '
				   . '<a href="' . esc_url( $assign_url ) . '">Assign one in Products &rarr; Products tab</a>.'
				   . '</div>';
			}
			return;
		}

		$template = OC_PATH . 'templates/frontend/customiser-panel.php';
		if ( ! file_exists( $template ) ) {
			return;
		}

		$design = $this->design;
		$areas  = $this->areas;
		$layers = $this->layers;

		include $template;
	}

	// ── Validation ────────────────────────────────────────────────────────────

	public function validate( bool $passed, int $product_id, int $qty ): bool {
		if ( null === $this->design ) {
			return $passed;
		}

		$raw = isset( $_POST['_oc_customisation'] ) ? wp_unslash( $_POST['_oc_customisation'] ) : '';

		if ( empty( trim( $raw ) ) || '{}' === trim( $raw ) ) {
			wc_add_notice( __( 'Please complete your personalisation before adding to cart.', 'overcustomise' ), 'error' );
			return false;
		}

		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			wc_add_notice( __( 'Invalid personalisation data. Please try again.', 'overcustomise' ), 'error' );
			return false;
		}

		// Check layer requirements and hard validation rules before cart insert.
		$layer_inputs = is_array( $data['layers'] ?? null ) ? $data['layers'] : [];
		foreach ( $this->layers as $layer ) {
			// Ignore hidden layers: they are not user-editable in the frontend panel.
			if ( ! (bool) ( $layer->visible ?? true ) ) {
				continue;
			}

			// Ignore locked layers: they use admin-set defaults and have no customer input.
			if ( ! empty( $layer->locked ) ) {
				continue;
			}

			$settings = $layer->settings ? json_decode( $layer->settings, true ) : [];
			if ( ! is_array( $settings ) ) {
				$settings = [];
			}

			$required = ! empty( $settings['required'] );
			$input    = is_array( $layer_inputs[ (int) $layer->id ] ?? null ) ? $layer_inputs[ (int) $layer->id ] : [];
			$filled   = false;
			$label    = $layer->label ?: ucfirst( (string) $layer->type );

			switch ( $layer->type ) {
				case 'text':
				case 'textarea':
					$value  = trim( (string) ( $input['value'] ?? '' ) );
					$filled = '' !== $value;

					$char_limit = absint( $settings['char_limit'] ?? 0 );
					if ( $char_limit > 0 && $this->string_length( $value ) > $char_limit ) {
						wc_add_notice(
							sprintf(
								/* translators: 1: layer label, 2: max chars */
								__( '"%1$s" exceeds the maximum of %2$d characters.', 'overcustomise' ),
								$label,
								$char_limit
							),
							'error'
						);
						return false;
					}
					break;

				case 'image':
					$filled = ! empty( $input['attachmentId'] );
					break;

				case 'clipart':
					$filled = ! empty( $input['clipartId'] );
					break;

				case 'spotify':
					$spotify_value = trim( (string) ( $input['value'] ?? '' ) );
					$filled        = '' !== $spotify_value;

					$char_limit = absint( $settings['char_limit'] ?? 0 );
					if ( $char_limit > 0 && $this->string_length( $spotify_value ) > $char_limit ) {
						wc_add_notice(
							sprintf(
								/* translators: 1: layer label, 2: max chars */
								__( '"%1$s" exceeds the maximum of %2$d characters.', 'overcustomise' ),
								$label,
								$char_limit
							),
							'error'
						);
						return false;
					}

					if ( $filled ) {
						$status = sanitize_key( (string) ( $input['spotifyStatus'] ?? '' ) );
						if ( in_array( $status, [ 'invalid_format', 'playlist_private_or_invalid', 'invalid_or_unavailable', 'unreachable', 'rate_limited' ], true ) ) {
							wc_add_notice(
								sprintf( __( 'The Spotify link in "%s" could not be validated. Please use a valid public Spotify link.', 'overcustomise' ), $label ),
								'error'
							);
							return false;
						}
					}
					break;

				default:
					$filled = true;
			}

			if ( $required && ! $filled ) {
				wc_add_notice(
					sprintf( __( 'Please complete the "%s" field before adding to cart.', 'overcustomise' ), $label ),
					'error'
				);
				return false;
			}
		}

		return $passed;
	}

	/** Return UTF-8 character length when mbstring is available. */
	private function string_length( string $value ): int {
		if ( function_exists( 'mb_strlen' ) ) {
			return (int) mb_strlen( $value, 'UTF-8' );
		}
		return strlen( $value );
	}

	// ── Inline CSS ────────────────────────────────────────────────────────────

	private function get_panel_css(): string {
		return '
		.oc-customiser-panel { margin-bottom: 24px; width: 100%; }

		/* Area tabs */
		.oc-area-tabs { display:flex; gap:6px; margin-bottom:12px; flex-wrap:wrap; }
		.oc-area-tab { padding:6px 14px; border:1px solid #ddd; border-radius:3px; background:#fff; cursor:pointer; font-size:13px; transition:background .15s,border-color .15s; }
		.oc-area-tab.oc-active { background:#0073aa; border-color:#0073aa; color:#fff; }

		/* Mobile preview toggle */
		.oc-preview-toggle-wrap { display:none; margin-bottom:12px; }
		.oc-preview-toggle { display:none; width:100%; padding:12px; border:1px solid #ddd; border-radius:6px; background:#f8f8f8; cursor:pointer; font-size:14px; font-weight:600; text-align:center; min-height:44px; }
		.oc-canvas-wrap { display:none; }
		.oc-canvas-wrap img { width:100%; height:auto; display:block; border-radius:6px; border:1px solid #ddd; }

		/* Layer controls */
		.oc-layer-controls { display:flex; flex-direction:column; gap:18px; }
		.oc-layer-section { border:1px solid #e8e8e8; border-radius:6px; overflow:hidden; }
		.oc-layer-header { padding:10px 14px; background:#f8f8f8; border-bottom:1px solid #e8e8e8; font-size:13px; font-weight:600; color:#3c434a; display:flex; align-items:center; gap:8px; }
		.oc-layer-type-badge { font-size:10px; font-weight:500; padding:2px 7px; border-radius:3px; background:#e0e0e0; color:#555; text-transform:uppercase; letter-spacing:.03em; }
		.oc-layer-required { color:#b32d2e; font-size:11px; margin-left:auto; }
		.oc-layer-body { padding:14px; display:flex; flex-direction:column; gap:10px; }

		/* Controls */
		.oc-control-group { display:flex; flex-direction:column; gap:5px; }
		.oc-control-group label { font-size:12px; font-weight:600; color:#3c434a; text-transform:uppercase; letter-spacing:.03em; }
		.oc-control-group input[type="text"],
		.oc-control-group textarea,
		.oc-control-group select { width:100%; padding:8px 10px; border:1px solid #ddd; border-radius:3px; font-size:14px; box-sizing:border-box; }
		.oc-control-group textarea { resize:vertical; min-height:80px; }
		.oc-char-count { font-size:11px; color:#888; text-align:right; }

		/* Font select */
		.oc-font-row { display:flex; gap:8px; align-items:center; }
		.oc-font-row select { flex:1; }

		/* Colour swatches */
		.oc-colour-swatches { display:flex; flex-wrap:wrap; gap:8px; }
		.oc-colour-swatch { width:32px; height:32px; border-radius:50%; border:2px solid transparent; cursor:pointer; transition:transform .1s,border-color .1s; }
		.oc-colour-swatch:hover { transform:scale(1.15); }
		.oc-colour-swatch.oc-selected { border-color:#0073aa; box-shadow:0 0 0 2px rgba(0,115,170,.3); }
		.oc-color-freeform { display:flex; align-items:center; gap:8px; margin-top:4px; }
		.oc-color-freeform input[type="color"] { width:40px; height:32px; padding:2px; border:1px solid #ddd; border-radius:3px; cursor:pointer; }

		/* Upload zone — wrapper + Uppy DragDrop overrides */
		.oc-upload-zone { border-radius:6px; min-height:120px; cursor:pointer; overflow:hidden; background:#fafbfc; }
		.oc-upload-zone .uppy-Root,
		.oc-upload-zone .uppy-DragDrop-container { width:100% !important; height:100% !important; min-height:120px; }
		.oc-upload-zone .uppy-DragDrop-inner {
			display:flex; flex-direction:column; align-items:center; justify-content:center;
			gap:8px; padding:18px 14px; min-height:120px;
			border:2px dashed #cbd5e1; border-radius:6px;
			background:transparent; transition:border-color .15s, background .15s;
		}
		.oc-upload-zone .uppy-DragDrop--isDragDropSupported { border:none; }
		.oc-upload-zone .uppy-DragDrop-container:hover .uppy-DragDrop-inner,
		.oc-upload-zone .uppy-DragDrop--isDraggingOver .uppy-DragDrop-inner {
			border-color:#0073aa; background:#eef7ff;
		}
		.oc-upload-zone .uppy-DragDrop-arrow { width:32px; height:32px; fill:#94a3b8; }
		.oc-upload-zone .uppy-DragDrop-label {
			font-size:13px; color:#334155; margin:0; font-weight:500;
		}
		.oc-upload-zone .uppy-DragDrop-browse {
			color:#0073aa; font-weight:600; text-decoration:underline;
		}
		.oc-upload-zone .uppy-DragDrop-note { font-size:11px; color:#64748b; margin-top:2px; }
		.oc-artwork-actions { display:flex; justify-content:flex-end; margin-top:8px; }
		.oc-artwork-remove { font-size:12px; color:#b32d2e; background:#fff; border:1px solid #b32d2e; border-radius:3px; padding:4px 10px; cursor:pointer; margin-left:auto; }
		.oc-artwork-remove:hover { background:#fde8e8; }
		.oc-artwork-error { color:#b32d2e; font-size:12px; margin-top:6px; padding:6px 10px; background:#fef2f2; border:1px solid #fecaca; border-radius:4px; }

		/* Clipart grid */
		.oc-clipart-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(64px,1fr)); gap:8px; max-height:240px; overflow-y:auto; padding:4px; }
		.oc-clipart-item { border:2px solid transparent; border-radius:4px; padding:4px; cursor:pointer; background:#fff; transition:border-color .1s,background .1s; }
		.oc-clipart-item:hover { border-color:#0073aa; background:#f0f8ff; }
		.oc-clipart-item.oc-selected { border-color:#0073aa; background:#ddefff; }
		.oc-clipart-item img { width:100%; aspect-ratio:1; object-fit:contain; display:block; }
		.oc-clipart-empty { font-size:13px; color:#888; text-align:center; padding:16px; }

		/* Generic help tooltip */
		.oc-help-tooltip { position:relative; display:inline-flex; align-items:center; width:fit-content; }
		.oc-help-toggle { width:22px; height:22px; border:1px solid #c7c7c7; border-radius:50%; background:#fff; color:#666; font-size:11px; font-weight:700; line-height:1; cursor:help; padding:0; }
		.oc-help-toggle:hover { color:#0073aa; border-color:#0073aa; }
		.oc-help-hint { position:absolute; top:calc(100% + 8px); left:0; z-index:5; display:none; max-width:280px; margin:0; padding:8px 10px; border-radius:4px; border:1px solid #d9d9d9; background:#fff; box-shadow:0 1px 2px rgba(0,0,0,.08); font-size:11px; font-weight:500; line-height:1.45; color:#666; word-wrap:break-word; overflow-wrap:break-word; opacity:0; transform:translateY(-4px); transition:opacity .2s ease, transform .2s ease; pointer-events:none; }
		.oc-help-tooltip:hover .oc-help-hint,
		.oc-help-tooltip:focus-within .oc-help-hint { display:block; opacity:1; transform:translateY(0); pointer-events:auto; }
		.oc-help-tooltip.oc-open .oc-help-hint { display:block; opacity:1; transform:translateY(0); pointer-events:auto; }
		/* Legacy Spotify tooltip aliases */
		.oc-spotify-help { position:relative; display:inline-flex; align-items:center; width:fit-content; }
		.oc-spotify-help-toggle { width:22px; height:22px; border:1px solid #c7c7c7; border-radius:50%; background:#fff; color:#666; font-size:11px; font-weight:700; line-height:1; cursor:help; padding:0; }
		.oc-spotify-hint { position:absolute; top:calc(100% + 8px); left:0; z-index:5; display:none; max-width:280px; margin:0; padding:8px 10px; border-radius:4px; border:1px solid #d9d9d9; background:#fff; box-shadow:0 1px 2px rgba(0,0,0,.08); font-size:11px; font-weight:500; line-height:1.45; color:#666; }
		.oc-spotify-help:hover .oc-spotify-hint,
		.oc-spotify-help:focus-within .oc-spotify-hint { display:block; }
		.oc-spotify-help.oc-open .oc-spotify-hint { display:block; }

		/* Mobile media queries */
		@media (max-width: 639px) {
			.oc-customiser-panel {
				padding-bottom: env(safe-area-inset-bottom, 0px);
			}
			.oc-area-tabs {
				flex-wrap: nowrap;
				overflow-x: auto;
				-webkit-overflow-scrolling: touch;
				scrollbar-width: none;
				padding: 2px 0;
				margin-inline: -8px;
				padding-inline: 8px;
			}
			.oc-area-tabs::-webkit-scrollbar { display: none; }
			.oc-area-tab {
				min-width: 44px;
				min-height: 44px;
				padding: 8px 14px;
				touch-action: manipulation;
				-webkit-tap-highlight-color: transparent;
				flex-shrink: 0;
			}
			.oc-preview-toggle-wrap {
				display: block;
			}
			.oc-preview-toggle {
				display: block;
				font-size: 16px;
			}
			.oc-canvas-wrap {
				display: none;
				margin-bottom: 12px;
				border-radius: 6px;
				overflow: hidden;
			}
			.oc-canvas-wrap.oc-preview-visible {
				display: block;
			}
			.oc-control-group {
				flex-direction: column;
				align-items: stretch;
			}
			.oc-control-group input[type="text"],
			.oc-control-group textarea,
			.oc-control-group select {
				font-size: 16px;
				min-height: 48px;
				touch-action: manipulation;
			}
			.oc-colour-swatches {
				gap: 10px;
			}
			.oc-colour-swatch {
				min-width: 44px;
				min-height: 44px;
				width: 44px;
				height: 44px;
				touch-action: manipulation;
			}
			.oc-clipart-grid {
				grid-template-columns: repeat(2, 1fr);
				gap: 10px;
			}
			.oc-clipart-item {
				min-height: 72px;
				touch-action: manipulation;
			}
			.oc-artwork-remove {
				min-height: 44px;
				width: 100%;
				text-align: center;
				touch-action: manipulation;
			}
			.oc-spotify-help-toggle {
				min-width: 44px;
				min-height: 44px;
				touch-action: manipulation;
			}
		}
		';
	}
}
