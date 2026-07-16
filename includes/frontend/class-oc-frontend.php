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
	private array   $design_variants = [];
	private string  $selected_design_variant = '';
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

		$assignment = OC_DB::get_assignment_for_product( $product_id, 0, true );
		if ( ! $assignment ) {
			OC_Logger::info( "OC Frontend: no design assignment for product {$product_id}." );
			return;
		}

		$selected_design_id = (int) $assignment->design_id;
		$requested_variant  = isset( $_GET['oc_design_variant'] ) ? sanitize_key( wp_unslash( $_GET['oc_design_variant'] ) ) : '';
		if ( '' !== $requested_variant ) {
			$decoded = json_decode( (string) ( $assignment->design_variants ?? '' ), true );
			if ( is_array( $decoded ) ) {
				foreach ( $decoded as $item ) {
					$variant_design_id = absint( $item['designId'] ?? 0 );
					if ( $variant_design_id && 'design-' . $variant_design_id === $requested_variant ) {
						$selected_design_id = $variant_design_id;
						break;
					}
				}
			}
		}

		$design = OC_DB::get_design( $selected_design_id );
		if ( ! $design ) {
			OC_Logger::warning( "OC Frontend: design {$selected_design_id} not found." );
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
		$this->design_variants = $this->build_design_variants( (string) ( $assignment->design_variants ?? '' ), (int) $assignment->design_id, (int) $design->id );
		$this->selected_design_variant = 'design-' . (int) $design->id;
	}

	// ── Assets ────────────────────────────────────────────────────────────────

	public function enqueue_assets(): void {
		if ( null === $this->design ) {
			return;
		}

		$asset_file = OC_PATH . 'assets/build/frontend/customiser-app.asset.php';
		$asset      = file_exists( $asset_file ) ? include $asset_file : [];
		$version    = isset( $asset['version'] ) ? (string) $asset['version'] : OC_VERSION;

		wp_enqueue_script(
			'oc-customiser-app',
			OC_ASSETS_URL . 'frontend/customiser-app.js',
			$asset['dependencies'] ?? [],
			$version,
			true
		);

		// Pass all data via wp_localize_script — no WP Interactivity API dependency.
		wp_localize_script( 'oc-customiser-app', 'ocCustomiserData', $this->build_state() );

		if ( file_exists( OC_PATH . 'assets/build/frontend/customiser-app.css' ) ) {
			wp_enqueue_style( 'oc-customiser-app', OC_ASSETS_URL . 'frontend/customiser-app.css', [], $version );
		} else {
			wp_register_style( 'oc-customiser-app', false, [], $version );
			wp_enqueue_style( 'oc-customiser-app' );
		}

		wp_add_inline_style( 'oc-customiser-app', $this->get_panel_css() );
	}

	/** Build the complete initial state for the Interactivity API store. */
	private function build_state(): array {
		$state = $this->build_design_state( $this->design, $this->areas, $this->layers );

		$edit_mode = false;
		$cart_key  = '';
		if ( null !== $this->edit_cart_item ) {
			$edit_mode = true;
			$cart_key  = $this->edit_cart_item['key'];
			$cs = $this->edit_cart_item['customisation'];
			if ( isset( $cs['v'] ) && 2 === (int) $cs['v'] && isset( $cs['layers'] ) && is_array( $cs['layers'] ) ) {
				$state['layerInputs'] = $this->merge_saved_layer_inputs( $state['layerInputs'], $cs['layers'] );
			}
		}

		$state['designVariants']        = $this->design_variants;
		$state['selectedDesignVariant'] = $this->selected_design_variant;
		$state['designVariantStates']   = $this->build_design_variant_states();
		$state['activeAreaIndex']       = 0;
		$state['isLoading']             = false;
		$state['uploadUrl']             = rest_url( 'overcustomise/v1/upload-artwork' );
		$state['applyImageFilterUrl']   = rest_url( 'overcustomise/v1/apply-image-filter' );
		$state['savePreviewUrl']        = rest_url( 'overcustomise/v1/save-preview' );
		$state['validateSpotifyUrl']    = rest_url( 'overcustomise/v1/validate-spotify' );
		$state['updateCartItemUrl']     = rest_url( 'overcustomise/v1/update-cart-item' );
		$state['productDesignUrl']      = rest_url( 'overcustomise/v1/product-design/' . (int) get_queried_object_id() );
		$state['productId']             = (int) get_queried_object_id();
		$state['uploadNonce']           = wp_create_nonce( 'wp_rest' );
		$state['requestToken']          = OC_Rest_API::issue_public_token();
		$state['maxUploadSizeMb']       = (int) OC_Admin_Settings::get( 'max_upload_size_mb' ) ?: 10;
		$state['allowedFormats']        = (array) OC_Admin_Settings::get( 'allowed_upload_formats' );
		$state['editMode']              = $edit_mode;
		$state['cartKey']               = $cart_key;

		return $state;
	}

	/** Build the frontend state needed when WooCommerce switches product variations. */
	public static function build_assignment_state( int $product_id, int $variation_id = 0 ): array|\WP_Error {
		$assignment = OC_DB::get_assignment_for_product( $product_id, $variation_id );
		if ( ! $assignment ) {
			return [
				'design_id' => 0,
				'active'    => false,
			];
		}

		$design = OC_DB::get_design( (int) $assignment->design_id );
		if ( ! $design || ! (bool) $design->active ) {
			return [
				'design_id' => (int) $assignment->design_id,
				'active'    => false,
			];
		}

		$areas = OC_DB::get_design_print_areas( (int) $design->id );
		if ( empty( $areas ) ) {
			return new \WP_Error( 'no_areas', __( 'Assigned design has no print areas.', 'overcustomise' ), [ 'status' => 404 ] );
		}

		$self = new self();
		$layers = OC_DB::get_design_layers( (int) $design->id );
		$design_variants = $self->build_design_variants( (string) ( $assignment->design_variants ?? '' ), (int) $assignment->design_id, (int) $design->id );
		$selected_design_variant = 'design-' . (int) $design->id;

		$self->design = $design;
		$self->areas = $areas;
		$self->layers = $layers;
		$self->design_variants = $design_variants;
		$self->selected_design_variant = $selected_design_variant;

		$state = $self->build_design_state( $design, $areas, $layers );
		$state['design_id']             = (int) $design->id;
		$state['active']                = true;
		$state['designVariants']        = $design_variants;
		$state['selectedDesignVariant'] = $selected_design_variant;
		$state['designVariantStates']   = $self->build_design_variant_states();
		$state['panelHtml']             = $self->render_panel_html( $design, $areas, $layers, $design_variants );

		return $state;
	}

	/** Build reusable frontend state for one design. */
	private function build_design_state( object $design, array $areas, array $layers ): array {
		global $wpdb;

		$all_fonts   = OC_Font_Registry::get_fonts_for_js();
		$all_colours = OC_DB::get_colours( true );
		$image_filters = OC_DB::get_image_filters( true );

		// Group layers by area ID.
		$layers_by_area = [];
		foreach ( $layers as $layer ) {
			$layers_by_area[ (int) $layer->area_id ][] = $layer;
		}

		// Build areas with their layers.
		$areas_js    = [];
		$layer_inputs = []; // layerId → default input values
		$restricted_layer_colours = [];

		foreach ( $areas as $area ) {
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

				$colour_group_ids = array_values( array_filter( array_map( 'absint', is_array( $settings['colour_groups'] ?? null ) ? $settings['colour_groups'] : [] ) ) );
				$default_colour   = sanitize_hex_color( (string) ( $settings['default_color'] ?? '#000000' ) ) ?: '#000000';
				$default_attachment_id  = absint( $settings['default_attachment_id'] ?? 0 );
				$default_attachment_url = $default_attachment_id ? (string) wp_get_attachment_url( $default_attachment_id ) : '';
				$default_image_filter_id = absint( $settings['default_image_filter_id'] ?? 0 );
				$image_filter_ids = array_values( array_filter( array_map( 'absint', is_array( $settings['image_filter_ids'] ?? null ) ? $settings['image_filter_ids'] : [] ) ) );
				if ( $default_image_filter_id && ! in_array( $default_image_filter_id, $image_filter_ids, true ) ) {
					$default_image_filter_id = 0;
				}
				if ( '' === $default_attachment_url && ! empty( $settings['default_attachment_url'] ) ) {
					$default_attachment_url = esc_url_raw( (string) $settings['default_attachment_url'] );
				}
				if ( ! empty( $colour_group_ids ) ) {
					$allowed_colours = OC_DB::get_colours_for_groups( $colour_group_ids );
					$allowed_hexes   = array_values( array_filter( array_map( fn( $colour ) => sanitize_hex_color( (string) ( $colour->hex ?? '' ) ), $allowed_colours ) ) );
					$restricted_layer_colours[ (int) $layer->id ] = $allowed_hexes;

					if ( ! empty( $allowed_hexes ) && ! in_array( strtolower( $default_colour ), array_map( 'strtolower', $allowed_hexes ), true ) ) {
						$default_colour = $allowed_hexes[0];
					}
				}

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
					'value'         => '',
					'fontId'        => absint( $settings['default_font_id'] ?? 0 ),
					'fontSize'      => absint( $settings['default_font_size'] ?? 0 ),
					'colorHex'      => $default_colour,
					'attachmentId'  => in_array( $layer->type, [ 'image', 'clipmask' ], true ) ? $default_attachment_id : 0,
					'attachmentUrl' => in_array( $layer->type, [ 'image', 'clipmask' ], true ) ? $default_attachment_url : '',
					'sourceAttachmentId'  => in_array( $layer->type, [ 'image', 'clipmask' ], true ) ? $default_attachment_id : 0,
					'sourceAttachmentUrl' => in_array( $layer->type, [ 'image', 'clipmask' ], true ) ? $default_attachment_url : '',
					'imageFilterId' => 'image' === $layer->type ? $default_image_filter_id : 0,
					'clipartId'     => 0,
					'clipartUrl'    => '',
					'clipartRecolourable' => false,
				];
			}

			$areas_js[] = [
				'id'          => (int) $area->id,
				'label'       => $area->label,
				'printMethod' => $area->print_method,
				'engravingMaterial' => isset( $area->engraving_material ) ? (string) $area->engraving_material : 'silver_metal',
				'mockupUrl'   => $mockup_url,
				'mockupW'     => $mockup_w,
				'mockupH'     => $mockup_h,
				'bounds'      => [
					'x' => (int) $area->canvas_x,
					'y' => (int) $area->canvas_y,
					'w' => (int) $area->canvas_w,
					'h' => (int) $area->canvas_h,
					'unit' => isset( $area->canvas_unit ) ? (string) $area->canvas_unit : 'px',
					'dpi' => isset( $area->canvas_dpi ) ? (int) $area->canvas_dpi : 300,
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
		$clipart_by_layer = $this->build_clipart_by_layer( $layers, $areas );
		foreach ( $layers as $layer ) {
			if ( 'clipart' !== $layer->type ) continue;
			$layer_id = (int) $layer->id;
			if ( ! isset( $layer_inputs[ $layer_id ] ) ) continue;

			$settings = $layer->settings ? json_decode( $layer->settings, true ) : [];
			if ( ! is_array( $settings ) ) $settings = [];

			$default_clipart_id = absint( $settings['default_clipart_id'] ?? 0 );
			if ( ! $default_clipart_id ) continue;

			foreach ( $clipart_by_layer[ $layer_id ] ?? [] as $item ) {
				if ( (int) $item['id'] !== $default_clipart_id ) continue;
				$layer_inputs[ $layer_id ]['clipartId'] = (int) $item['id'];
				$layer_inputs[ $layer_id ]['clipartUrl'] = (string) $item['url'];
				$layer_inputs[ $layer_id ]['clipartRecolourable'] = ! empty( $item['recolourable'] );
				break;
			}

			if ( ! empty( $layer_inputs[ $layer_id ]['clipartUrl'] ) ) continue;

			$default_clipart = $wpdb->get_row( $wpdb->prepare(
				"SELECT id, file_path, file_type, colour_changeable, allowed_print_methods FROM {$wpdb->prefix}oc_clipart WHERE id = %d LIMIT 1",
				$default_clipart_id
			) );
			if ( $default_clipart && ! empty( $default_clipart->file_path ) ) {
				$upload_dir = wp_upload_dir();
				$layer_inputs[ $layer_id ]['clipartId'] = (int) $default_clipart->id;
				$layer_inputs[ $layer_id ]['clipartUrl'] = $upload_dir['baseurl'] . '/overcustomise/clipart/' . basename( (string) $default_clipart->file_path );
				$layer_inputs[ $layer_id ]['clipartRecolourable'] = ( ! property_exists( $default_clipart, 'colour_changeable' ) || (bool) $default_clipart->colour_changeable ) && 'svg' === strtolower( (string) $default_clipart->file_type );
				continue;
			}

			if ( ! empty( $settings['default_clipart_url'] ) ) {
				$layer_inputs[ $layer_id ]['clipartId'] = $default_clipart_id;
				$layer_inputs[ $layer_id ]['clipartUrl'] = esc_url_raw( (string) $settings['default_clipart_url'] );
				$layer_inputs[ $layer_id ]['clipartRecolourable'] = ! empty( $settings['default_clipart_recolourable'] );
			}
		}

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

		return [
			'designId'        => (int) $design->id,
			'designName'      => $design->name,
			'flatRate'        => (float) $design->flat_rate,
			'areas'           => $areas_js,
			'fonts'           => $all_fonts,
			'colours'         => $colours_js,
			'imageFilters'    => array_map( function ( $filter ) {
				return [
					'id'    => (int) $filter->id,
					'name'  => (string) $filter->name,
					'key'   => (string) $filter->filter_key,
					'value' => (float) $filter->value,
					'isAi'  => 'ai' === (string) $filter->filter_key,
				];
			}, $image_filters ),
			'clipartByLayer'  => $clipart_by_layer,
			'clipartGroups'   => $clipart_groups,
			'layerInputs'     => $layer_inputs,
			'restrictedLayerColours' => $restricted_layer_colours,
		];
	}

	/** Merge saved cart values into default layer inputs. */
	private function merge_saved_layer_inputs( array $layer_inputs, array $saved_inputs ): array {
		foreach ( $saved_inputs as $lid => $ldata ) {
			if ( ! is_array( $ldata ) || ! isset( $layer_inputs[ (int) $lid ] ) ) continue;
			$layer_inputs[ (int) $lid ] = array_merge( $layer_inputs[ (int) $lid ], $ldata );
			$attachment_id = absint( $layer_inputs[ (int) $lid ]['attachmentId'] ?? 0 );
			$source_id     = absint( $layer_inputs[ (int) $lid ]['sourceAttachmentId'] ?? $attachment_id );
			if ( $attachment_id ) $layer_inputs[ (int) $lid ]['attachmentUrl'] = (string) wp_get_attachment_url( $attachment_id );
			if ( $source_id ) $layer_inputs[ (int) $lid ]['sourceAttachmentUrl'] = (string) wp_get_attachment_url( $source_id );
		}
		return $layer_inputs;
	}

	/** Build frontend-safe alternate design options from assignment JSON. */
	private function build_design_variants( string $variants_json, int $default_design_id, int $selected_design_id ): array {
		$options = [];
		$default_option = $this->build_design_variant_option( $default_design_id, '', $selected_design_id );
		if ( $default_option ) {
			$options[] = $default_option;
		}

		$decoded = json_decode( $variants_json, true );
		if ( ! is_array( $decoded ) ) {
			return count( $options ) > 1 ? $options : [];
		}

		foreach ( $decoded as $item ) {
			$design_id = absint( $item['designId'] ?? 0 );
			if ( ! $design_id || $design_id === $default_design_id ) {
				continue;
			}
			$option = $this->build_design_variant_option( $design_id, (string) ( $item['label'] ?? '' ), $selected_design_id );
			if ( $option ) {
				$options[] = $option;
			}
		}

		return count( $options ) > 1 ? $options : [];
	}

	/** Build one selectable design option from a design's artwork content. */
	private function build_design_variant_option( int $design_id, string $label, int $selected_design_id ): ?array {
		$design = OC_DB::get_design( $design_id );
		if ( ! $design || ! (bool) $design->active ) {
			return null;
		}
		$areas = OC_DB::get_design_print_areas( $design_id );
		$area  = $areas[0] ?? null;
		if ( ! $area ) {
			return null;
		}

		$thumb_layers = $this->get_design_variant_thumb_layers( $design_id, $area );
		$thumb_url    = empty( $thumb_layers ) ? $this->get_design_variant_artwork_thumb_url( $design_id, $areas ) : '';
		if ( empty( $thumb_layers ) && '' === $thumb_url && ! empty( $area->mockup_attachment_id ) ) {
			$mockup_src = wp_get_attachment_image_src( (int) $area->mockup_attachment_id, 'large' );
			$thumb_url  = wp_get_attachment_image_url( (int) $area->mockup_attachment_id, 'thumbnail' ) ?: ( $mockup_src[0] ?? '' );
		}

		return [
			'id'       => 'design-' . $design_id,
			'designId' => $design_id,
			'label'    => sanitize_text_field( $label ) ?: ( $design->name ?: __( 'Untitled Design #', 'overcustomise' ) . $design_id ),
			'thumbUrl' => $thumb_url,
			'thumbLayers' => $thumb_layers,
			'selected' => $design_id === $selected_design_id,
		];
	}

	/** Return positioned thumbnail layers for the first print area, without the mockup background. */
	private function get_design_variant_thumb_layers( int $design_id, object $area ): array {
		$layers = OC_DB::get_design_layers( $design_id );
		if ( empty( $layers ) ) {
			return [];
		}

		$bounds = $this->get_design_variant_layer_bounds( $layers, $area );
		if ( empty( $bounds ) ) {
			return [];
		}

		$bounds_w = max( 1, (float) ( $bounds['max_x'] - $bounds['min_x'] ) );
		$bounds_h = max( 1, (float) ( $bounds['max_y'] - $bounds['min_y'] ) );
		$pad      = 8;
		$scale    = min( ( 100 - ( $pad * 2 ) ) / $bounds_w, ( 100 - ( $pad * 2 ) ) / $bounds_h );
		$offset_x = ( 100 - ( $bounds_w * $scale ) ) / 2;
		$offset_y = ( 100 - ( $bounds_h * $scale ) ) / 2;
		$items    = [];

		foreach ( $layers as $layer ) {
			if ( ! (bool) $layer->visible || (int) $layer->area_id !== (int) $area->id ) {
				continue;
			}

			$settings = $layer->settings ? json_decode( $layer->settings, true ) : [];
			if ( ! is_array( $settings ) ) {
				$settings = [];
			}

			$item = [
				'type' => (string) $layer->type,
				'x'    => $offset_x + ( ( (int) $layer->x - (float) $bounds['min_x'] ) * $scale ),
				'y'    => $offset_y + ( ( (int) $layer->y - (float) $bounds['min_y'] ) * $scale ),
				'w'    => max( 1, (int) $layer->w * $scale ),
				'h'    => max( 1, (int) $layer->h * $scale ),
			];

			if ( in_array( (string) $layer->type, [ 'text', 'textarea' ], true ) ) {
				$text = trim( (string) ( $settings['default_text'] ?? $layer->label ?? '' ) );
				if ( '' === $text ) {
					continue;
				}

				$font = $this->get_design_variant_thumb_font( absint( $settings['default_font_id'] ?? 0 ) );
				$longest_line = max( array_map( [ $this, 'string_length' ], preg_split( '/\R/', $text ) ?: [ $text ] ) );
				$line_count       = max( 1, count( preg_split( '/\R/', $text ) ?: [ $text ] ) );
				$scaled_font_size = absint( $settings['default_font_size'] ?? 24 ) * $scale * 0.5;
				$box_height_cap   = ( (float) $item['h'] / $line_count ) * 0.55;
				$box_width_cap    = ( (float) $item['w'] / max( 1, $longest_line ) ) * 1.15;

				$item['text'] = $text;
				$item['color'] = sanitize_hex_color( (string) ( $settings['default_color'] ?? '#111111' ) ) ?: '#111111';
				$item['fontSize'] = max( 3.5, min( 12, $scaled_font_size, $box_height_cap, $box_width_cap ) );
				$item['fontFamily'] = $font['family'];
				$item['fontWeight'] = $font['weight'];
				$item['fontStyle'] = $font['style'];
				$items[] = $item;
				continue;
			}

			if ( in_array( (string) $layer->type, [ 'image', 'clipart' ], true ) ) {
				$url = $this->get_design_layer_artwork_url( $layer, $settings );
				if ( '' === $url ) {
					continue;
				}

				$item['url'] = $url;
				$items[] = $item;
			}
		}

		return $items;
	}

	/** Calculate the visible artwork bounds for thumbnail fitting. */
	private function get_design_variant_layer_bounds( array $layers, object $area ): array {
		$min_x = null;
		$min_y = null;
		$max_x = null;
		$max_y = null;

		foreach ( $layers as $layer ) {
			if ( ! (bool) $layer->visible || (int) $layer->area_id !== (int) $area->id || ! in_array( (string) $layer->type, [ 'text', 'textarea', 'image', 'clipart' ], true ) ) {
				continue;
			}

			$x1 = (int) $layer->x;
			$y1 = (int) $layer->y;
			$x2 = $x1 + max( 1, (int) $layer->w );
			$y2 = $y1 + max( 1, (int) $layer->h );

			$min_x = null === $min_x ? $x1 : min( $min_x, $x1 );
			$min_y = null === $min_y ? $y1 : min( $min_y, $y1 );
			$max_x = null === $max_x ? $x2 : max( $max_x, $x2 );
			$max_y = null === $max_y ? $y2 : max( $max_y, $y2 );
		}

		if ( null === $min_x || null === $min_y || null === $max_x || null === $max_y ) {
			return [];
		}

		return [
			'min_x' => $min_x,
			'min_y' => $min_y,
			'max_x' => $max_x,
			'max_y' => $max_y,
		];
	}

	/** Resolve font details for thumbnail text layers. */
	private function get_design_variant_thumb_font( int $font_id ): array {
		static $fonts_by_id = null;

		if ( null === $fonts_by_id ) {
			$fonts_by_id = [];
			foreach ( OC_DB::get_fonts( true ) as $font ) {
				$fonts_by_id[ (int) $font->id ] = [
					'family' => (string) $font->name,
					'weight' => (string) ( $font->weight ?: 'normal' ),
					'style'  => (string) ( $font->style ?: 'normal' ),
				];
			}
		}

		if ( $font_id && isset( $fonts_by_id[ $font_id ] ) ) {
			return $fonts_by_id[ $font_id ];
		}

		$first_font = reset( $fonts_by_id );
		if ( is_array( $first_font ) ) {
			return $first_font;
		}

		return [
			'family' => 'sans-serif',
			'weight' => 'normal',
			'style'  => 'normal',
		];
	}

	/** Resolve a layer's default artwork URL. */
	private function get_design_layer_artwork_url( object $layer, array $settings ): string {
		global $wpdb;

		if ( 'image' === (string) $layer->type ) {
			$attachment_id = absint( $settings['default_attachment_id'] ?? 0 );
			if ( $attachment_id ) {
				$url = wp_get_attachment_image_url( $attachment_id, 'medium' ) ?: wp_get_attachment_url( $attachment_id );
				if ( $url ) {
					return (string) $url;
				}
			}

			return ! empty( $settings['default_attachment_url'] ) ? esc_url_raw( (string) $settings['default_attachment_url'] ) : '';
		}

		if ( 'clipart' === (string) $layer->type ) {
			$clipart_id = absint( $settings['default_clipart_id'] ?? 0 );
			if ( $clipart_id ) {
				$clipart = $wpdb->get_row( $wpdb->prepare(
					"SELECT file_path FROM {$wpdb->prefix}oc_clipart WHERE id = %d AND active = 1 LIMIT 1",
					$clipart_id
				) );
				if ( $clipart && ! empty( $clipart->file_path ) ) {
					$upload_dir = wp_upload_dir();
					return $upload_dir['baseurl'] . '/overcustomise/clipart/' . basename( (string) $clipart->file_path );
				}
			}

			return ! empty( $settings['default_clipart_url'] ) ? esc_url_raw( (string) $settings['default_clipart_url'] ) : '';
		}

		return '';
	}

	/** Return a thumbnail URL for the first visible artwork/text layer, without the mockup background. */
	private function get_design_variant_artwork_thumb_url( int $design_id, array $areas ): string {
		global $wpdb;

		$layers = OC_DB::get_design_layers( $design_id );
		if ( empty( $layers ) ) {
			return '';
		}

		$area_ids = array_map( fn( $area ) => (int) $area->id, $areas );
		foreach ( $layers as $layer ) {
			if ( ! (bool) $layer->visible || ! in_array( (int) $layer->area_id, $area_ids, true ) ) {
				continue;
			}

			$settings = $layer->settings ? json_decode( $layer->settings, true ) : [];
			if ( ! is_array( $settings ) ) {
				$settings = [];
			}

			if ( 'image' === (string) $layer->type ) {
				$attachment_id = absint( $settings['default_attachment_id'] ?? 0 );
				if ( $attachment_id ) {
					$url = wp_get_attachment_image_url( $attachment_id, 'medium' ) ?: wp_get_attachment_url( $attachment_id );
					if ( $url ) {
						return (string) $url;
					}
				}

				if ( ! empty( $settings['default_attachment_url'] ) ) {
					return esc_url_raw( (string) $settings['default_attachment_url'] );
				}
			}

			if ( 'clipart' === (string) $layer->type ) {
				$clipart_id = absint( $settings['default_clipart_id'] ?? 0 );
				if ( $clipart_id ) {
					$clipart = $wpdb->get_row( $wpdb->prepare(
						"SELECT file_path FROM {$wpdb->prefix}oc_clipart WHERE id = %d AND active = 1 LIMIT 1",
						$clipart_id
					) );
					if ( $clipart && ! empty( $clipart->file_path ) ) {
						$upload_dir = wp_upload_dir();
						return $upload_dir['baseurl'] . '/overcustomise/clipart/' . basename( (string) $clipart->file_path );
					}
				}

				if ( ! empty( $settings['default_clipart_url'] ) ) {
					return esc_url_raw( (string) $settings['default_clipart_url'] );
				}
			}
		}

		return $this->build_design_variant_text_thumb_url( $layers, $areas[0] ?? null );
	}

	/** Build a small SVG thumbnail for text-only designs. */
	private function build_design_variant_text_thumb_url( array $layers, ?object $area ): string {
		if ( ! $area ) {
			return '';
		}

		$view_w = max( 1, (int) ( $area->canvas_w ?: 300 ) );
		$view_h = max( 1, (int) ( $area->canvas_h ?: 300 ) );
		$items  = [];

		foreach ( $layers as $layer ) {
			if ( ! (bool) $layer->visible || (int) $layer->area_id !== (int) $area->id || 'text' !== (string) $layer->type ) {
				continue;
			}

			$settings = $layer->settings ? json_decode( $layer->settings, true ) : [];
			if ( ! is_array( $settings ) ) {
				$settings = [];
			}

			$text = trim( (string) ( $settings['default_text'] ?? $layer->label ?? '' ) );
			if ( '' === $text ) {
				continue;
			}

			$font_size = max( 10, min( 72, absint( $settings['default_font_size'] ?? 28 ) ) );
			$colour    = sanitize_hex_color( (string) ( $settings['default_color'] ?? '#111111' ) ) ?: '#111111';
			$x         = (int) $layer->x + ( (int) $layer->w / 2 );
			$y         = (int) $layer->y + ( (int) $layer->h / 2 );

			$items[] = '<text x="' . esc_attr( (string) $x ) . '" y="' . esc_attr( (string) $y ) . '" text-anchor="middle" dominant-baseline="middle" fill="' . esc_attr( $colour ) . '" font-family="Arial, sans-serif" font-size="' . esc_attr( (string) $font_size ) . '" font-weight="600">' . esc_html( $text ) . '</text>';
		}

		if ( empty( $items ) ) {
			return '';
		}

		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $view_w . ' ' . $view_h . '"><rect width="100%" height="100%" fill="transparent"/>' . implode( '', $items ) . '</svg>';

		return 'data:image/svg+xml;charset=UTF-8,' . rawurlencode( $svg );
	}

	/** Build preloaded state and HTML for every selectable design option. */
	private function build_design_variant_states(): array {
		$states = [];
		foreach ( $this->design_variants as $variant ) {
			$design_id = absint( $variant['designId'] ?? 0 );
			$variant_id = (string) ( $variant['id'] ?? '' );
			if ( ! $design_id || '' === $variant_id ) {
				continue;
			}

			$design = OC_DB::get_design( $design_id );
			if ( ! $design || ! (bool) $design->active ) {
				continue;
			}

			$areas = OC_DB::get_design_print_areas( $design_id );
			if ( empty( $areas ) ) {
				continue;
			}

			$layers = OC_DB::get_design_layers( $design_id );
			$design_variants = $this->mark_design_variants_selected( $variant_id );
			$state = $this->build_design_state( $design, $areas, $layers );
			$state['designVariants']        = $design_variants;
			$state['selectedDesignVariant'] = $variant_id;
			$state['panelHtml']             = $this->render_panel_html( $design, $areas, $layers, $design_variants );
			$states[ $variant_id ] = $state;
		}

		return $states;
	}

	/** Mark the selected design option for server-rendered variant panels. */
	private function mark_design_variants_selected( string $selected_variant_id ): array {
		return array_map(
			fn( $variant ) => array_merge( $variant, [ 'selected' => (string) ( $variant['id'] ?? '' ) === $selected_variant_id ] ),
			$this->design_variants
		);
	}

	/** Render the frontend panel for a specific design into a string. */
	private function render_panel_html( object $design, array $areas, array $layers, array $design_variants ): string {
		$template = OC_PATH . 'templates/frontend/customiser-panel.php';
		if ( ! file_exists( $template ) ) {
			return '';
		}

		$clipart_by_layer = $this->build_clipart_by_layer( $layers, $areas );

		ob_start();
		include $template;
		return (string) ob_get_clean();
	}

	/** Load clipart items for all clipart layers. */
	private function build_clipart_by_layer( ?array $layers = null, ?array $areas = null ): array {
		global $wpdb;

		$by_layer   = [];
		$upload_dir = wp_upload_dir();
		$layers     = $layers ?? $this->layers;
		$areas      = $areas ?? $this->areas;

		$methods_by_area = [];
		foreach ( $areas as $area ) {
			$methods_by_area[ (int) $area->id ] = sanitize_key( (string) ( $area->print_method ?? '' ) );
		}

		foreach ( $layers as $layer ) {
			if ( $layer->type !== 'clipart' ) continue;

			$settings   = $layer->settings ? json_decode( $layer->settings, true ) : [];
			$group_ids  = is_array( $settings ) ? ( $settings['clipart_groups'] ?? [] ) : [];
			$layer_id   = (int) $layer->id;

			if ( ! empty( $group_ids ) ) {
				$placeholders = implode( ',', array_fill( 0, count( $group_ids ), '%d' ) );
				$items = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT DISTINCT c.id, c.name, c.file_path, c.file_type, c.colour_changeable, c.allowed_print_methods, GROUP_CONCAT(DISTINCT cg.name SEPARATOR '||') AS group_names
						 FROM {$wpdb->prefix}oc_clipart c
						 JOIN {$wpdb->prefix}oc_clipart_group_items gi ON gi.clipart_id = c.id
						 JOIN {$wpdb->prefix}oc_clipart_groups cg ON cg.id = gi.group_id
						 WHERE gi.group_id IN ($placeholders) AND c.active = 1
						 GROUP BY c.id, c.name, c.file_path, c.file_type, c.colour_changeable, c.allowed_print_methods
						 ORDER BY c.name ASC",
						...$group_ids
					)
				) ?: [];
			} else {
				$items = $wpdb->get_results(
					"SELECT c.id, c.name, c.file_path, c.file_type, c.colour_changeable, c.allowed_print_methods, GROUP_CONCAT(DISTINCT cg.name SEPARATOR '||') AS group_names
					 FROM {$wpdb->prefix}oc_clipart c
					 LEFT JOIN {$wpdb->prefix}oc_clipart_group_items gi ON gi.clipart_id = c.id
					 LEFT JOIN {$wpdb->prefix}oc_clipart_groups cg ON cg.id = gi.group_id
					 WHERE c.active = 1
					 GROUP BY c.id, c.name, c.file_path, c.file_type, c.colour_changeable, c.allowed_print_methods
					 ORDER BY c.name ASC"
				) ?: [];
			}

			$print_method = $methods_by_area[ (int) $layer->area_id ] ?? '';
			$items = array_values( array_filter( $items, function ( $item ) use ( $print_method ) {
				$allowed = self::normalise_clipart_print_methods( (string) ( $item->allowed_print_methods ?? '' ) );
				return empty( $allowed ) || in_array( $print_method, $allowed, true );
			} ) );

			$by_layer[ $layer_id ] = array_map( function ( $item ) use ( $upload_dir ) {
				$url = $upload_dir['baseurl'] . '/overcustomise/clipart/' . basename( $item->file_path );
				$groupNames = $item->group_names ? array_filter( array_map( 'trim', explode( '||', $item->group_names ) ) ) : [];
				return [
					'id'         => (int) $item->id,
					'name'       => $item->name,
					'url'        => $url,
					'fileType'   => (string) $item->file_type,
					'recolourable' => ( ! property_exists( $item, 'colour_changeable' ) || (bool) $item->colour_changeable ) && 'svg' === strtolower( (string) $item->file_type ),
					'allowedPrintMethods' => self::normalise_clipart_print_methods( (string) ( $item->allowed_print_methods ?? '' ) ),
					'groupNames' => $groupNames,
				];
			}, $items );
		}

		return $by_layer;
	}

	private static function normalise_clipart_print_methods( string $raw ): array {
		if ( '' === trim( $raw ) ) {
			return [];
		}

		$decoded = json_decode( $raw, true );
		$methods = is_array( $decoded ) ? $decoded : explode( ',', $raw );
		$allowed = [ 'engraving', 'uv', 'embroidery', 'sublimation' ];

		return array_values( array_intersect( $allowed, array_map( 'sanitize_key', $methods ) ) );
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
		$clipart_by_layer = $this->build_clipart_by_layer( $layers, $areas );
		$design_variants = $this->design_variants;

		include $template;
	}

	// ── Validation ────────────────────────────────────────────────────────────

	public function validate( bool $passed, int $product_id, int $qty ): bool {
		$variation_id = isset( $_POST['variation_id'] ) ? absint( wp_unslash( $_POST['variation_id'] ) ) : 0;
		$assignment   = null;
		$design       = $this->design;
		$layers       = $this->layers;

		if ( null === $design ) {
			$assignment = OC_DB::get_assignment_for_product( $product_id, $variation_id );
			if ( ! $assignment ) {
				return $passed;
			}

			$design = OC_DB::get_design( (int) $assignment->design_id );
			if ( ! $design || ! (bool) $design->active ) {
				return $passed;
			}

			$areas = OC_DB::get_design_print_areas( (int) $design->id );
			if ( empty( $areas ) ) {
				return $passed;
			}

			$layers = OC_DB::get_design_layers( (int) $design->id );
		} else {
			$assignment = OC_DB::get_assignment_for_product( $product_id, $variation_id );
		}
		if ( ! $assignment ) {
			return $passed;
		}

		$raw = isset( $_POST['_oc_customisation'] ) ? wp_unslash( $_POST['_oc_customisation'] ) : '';

		if ( empty( trim( $raw ) ) || '{}' === trim( $raw ) ) {
			wc_add_notice( __( 'Please complete your personalisation before adding to cart.', 'overcustomise' ), 'error' );
			return false;
		}
		if ( strlen( $raw ) > 1024 * 1024 ) {
			wc_add_notice( __( 'This personalisation is too large to add safely. Please simplify the design or contact us for help.', 'overcustomise' ), 'error' );
			return false;
		}

		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			wc_add_notice( __( 'Invalid personalisation data. Please try again.', 'overcustomise' ), 'error' );
			return false;
		}

		$posted_design_id = absint( $data['designId'] ?? 0 );
		if ( $posted_design_id && $posted_design_id !== (int) $design->id ) {
			$allowed_design_ids = array_map( fn( $variant ) => absint( $variant['designId'] ?? 0 ), $this->design_variants );
			$is_allowed_design = in_array( $posted_design_id, $allowed_design_ids, true )
				|| ( $assignment && OC_DB::assignment_allows_design( $assignment, $posted_design_id ) );

			if ( ! $is_allowed_design ) {
				wc_add_notice( __( 'Invalid design option selected. Please refresh and try again.', 'overcustomise' ), 'error' );
				return false;
			}
		}

		$normalised = OC_Cart::normalise_v2_layers(
			$product_id,
			$variation_id,
			$posted_design_id ?: (int) $design->id,
			is_array( $data['layers'] ?? null ) ? $data['layers'] : [],
			is_string( $data['uploadToken'] ?? null ) ? $data['uploadToken'] : ''
		);
		if ( is_wp_error( $normalised ) ) {
			wc_add_notice( $normalised->get_error_message(), 'error' );
			return false;
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

		/* Hidden preview image used for cart confirmation fallback */
		.oc-canvas-wrap { display:none; }
		.oc-canvas-wrap img { width:100%; height:auto; display:block; border-radius:6px; border:1px solid #ddd; }

		/* Layer controls */
		.oc-layer-controls { display:flex; flex-direction:column; gap:14px; }
		.oc-layer-section { border:0; border-radius:0; overflow:visible; padding:0; margin:0; }
		.oc-layer-header { padding:0 0 6px; background:transparent; border-bottom:0; font-size:13px; font-weight:600; color:#3c434a; display:flex; align-items:center; gap:8px; }
		.oc-layer-type-badge { font-size:10px; font-weight:500; padding:2px 7px; border-radius:3px; background:#e0e0e0; color:#555; text-transform:uppercase; letter-spacing:.03em; }
		.oc-layer-required { color:#b32d2e; font-size:11px; margin-left:auto; }
		.oc-layer-body { padding:0; display:flex; flex-direction:column; gap:8px; }

		/* Controls */
		.oc-control-group { display:flex; flex-direction:column; gap:5px; }
		.oc-control-group label { font-size:12px; font-weight:600; color:#3c434a; text-transform:uppercase; letter-spacing:.03em; }
		.oc-range-value { float:right; color:#1d2327; font-weight:700; }
		.oc-design-variants { margin-bottom:16px; }
		.oc-design-variant-carousel { width:100%; }
		.oc-design-variant-grid { display:grid !important; grid-template-columns:repeat(auto-fill, minmax(96px, 1fr)); gap:10px; width:100%; max-width:560px; margin:0 auto; justify-content:center; }
		.oc-design-variant-carousel-arrow,
		.oc-design-variant-carousel-dots { display:none; }
		.oc-design-variant-option { width:100% !important; min-width:0; min-height:0; padding:7px !important; border:1px solid #dcdcde; border-radius:10px; background:#fff; cursor:pointer; display:flex !important; flex-direction:column !important; align-items:stretch !important; gap:7px; text-align:left; transition:border-color .15s, box-shadow .15s, transform .15s; box-sizing:border-box; }
		.oc-design-variant-option:hover { border-color:#d88da0; box-shadow:0 8px 20px rgba(0,0,0,.08); transform:translateY(-1px); }
		.oc-design-variant-option.oc-selected { border-color:#d88da0; box-shadow:0 0 0 2px rgba(216,141,160,.24); }
		.oc-design-variant-option > img,
		.oc-design-variant-canvas,
		.oc-design-variant-thumb { width:100% !important; max-width:none !important; aspect-ratio:1 / 1; height:auto !important; display:block; border-radius:7px; background:transparent; }
		.oc-design-variant-canvas { display:none; }
		.oc-design-variant-option.oc-thumb-pending > img,
		.oc-design-variant-option.oc-thumb-pending .oc-design-variant-thumb { visibility:hidden; }
		.oc-design-variant-option.oc-thumb-rendered .oc-design-variant-canvas { display:block; }
		.oc-design-variant-option.oc-thumb-rendered > img,
		.oc-design-variant-option.oc-thumb-rendered .oc-design-variant-thumb { display:none !important; }
		.oc-design-variant-option > img { object-fit:contain; object-position:center; }
		.oc-design-variant-thumb { position:relative; overflow:hidden; container-type:inline-size; }
		.oc-design-variant-thumb-layer { position:absolute; display:block; max-width:none !important; object-fit:contain; object-position:center; }
		.oc-design-variant-thumb-text { display:flex; align-items:center; justify-content:center; overflow:hidden; text-align:center; font-weight:700; line-height:1.05; overflow-wrap:anywhere; white-space:pre-line; font-size:clamp(3.5px, calc(var(--oc-thumb-font-size, 10) * 1cqw), 12px) !important; }
		.oc-design-variant-option > span { min-height:30px; display:flex !important; align-items:center; justify-content:center; font-size:11px; line-height:1.2; font-weight:700; color:#1d2327; text-align:center; overflow-wrap:anywhere; }
		@media (max-width:639px) {
			.oc-design-variant-carousel { display:grid; grid-template-columns:12px minmax(0,1fr) 12px; grid-template-areas:"prev track next" "dots dots dots"; align-items:center; column-gap:3px; row-gap:5px; margin-inline:-4px; }
			.oc-design-variant-grid { grid-area:track; display:flex !important; grid-template-columns:none; gap:8px; max-width:none; margin:0; overflow-x:auto; overflow-y:hidden; scroll-snap-type:x mandatory; scroll-behavior:smooth; -webkit-overflow-scrolling:touch; scrollbar-width:none; justify-content:flex-start; }
			.oc-design-variant-grid::-webkit-scrollbar { display:none; }
			.oc-design-variant-option { flex:0 0 calc((100% - 17.5px) / 2.5); scroll-snap-align:start; }
			.oc-design-variant-carousel-arrow { display:block; width:12px; height:24px; border:0; border-radius:0; background:transparent; color:#777; font-size:20px; font-weight:700; line-height:1; cursor:pointer; box-shadow:none; padding:0; }
			.oc-design-variant-carousel-arrow:not(:disabled):hover { color:#0073aa; transform:scale(1.08); }
			.oc-design-variant-carousel-arrow:disabled { opacity:.28; cursor:default; box-shadow:none; }
			.oc-design-variant-carousel-arrow--prev { grid-area:prev; }
			.oc-design-variant-carousel-arrow--next { grid-area:next; }
			.oc-design-variant-carousel-dots { grid-area:dots; display:flex; justify-content:center; gap:6px; min-height:6px; }
			.oc-design-variant-carousel-dot { width:5px; height:5px; min-width:5px; min-height:5px; max-width:5px; max-height:5px; flex:0 0 5px; padding:0; border:0; border-radius:50%; background:#bbb; cursor:pointer; appearance:none; line-height:0; transition:background .15s ease,transform .15s ease; }
			.oc-design-variant-carousel-dot.oc-active { background:#0073aa; transform:scale(1.25); }
			.oc-design-variant-carousel--single-page .oc-design-variant-carousel-arrow,
			.oc-design-variant-carousel--single-page .oc-design-variant-carousel-dots { display:none; }
			.oc-design-variant-carousel--single-page .oc-design-variant-grid { grid-column:1 / -1; }
		}
		.oc-control-group:has(> [data-oc-tooltip]) { display:grid; grid-template-columns:minmax(0,1fr) auto; align-items:center; column-gap:10px; row-gap:5px; }
		.oc-control-group:has(> [data-oc-tooltip]) > label,
		.oc-control-group:has(> [data-oc-tooltip]) > .oc-char-counter,
		.oc-control-group:has(> [data-oc-tooltip]) > .oc-spotify-error { grid-column:1 / -1; }
		.oc-control-group:has(> [data-oc-tooltip]) > input,
		.oc-control-group:has(> [data-oc-tooltip]) > select { grid-column:1; min-width:0; }
		.oc-control-group:has(> [data-oc-tooltip]) > [data-oc-tooltip] { grid-column:2; justify-self:end; }
		.oc-control-group input[type="text"],
		.oc-control-group input[type="url"],
		.oc-control-group textarea,
		.oc-control-group select { width:100%; min-height:38px; padding:7px 11px; border:1px solid #ddd; border-radius:6px; font-size:14px; box-sizing:border-box; }
		.oc-control-group textarea { resize:vertical; min-height:64px; }
		.oc-control-group input[type="range"] { width:100%; min-height:32px; accent-color:#d88da0; cursor:pointer; }
		.oc-input-wrap { position:relative; }
		.oc-input-wrap > input,
		.oc-input-wrap > textarea { padding-right:70px; }
		.oc-char-counter { display:none; position:absolute; right:10px; bottom:9px; pointer-events:none; font-size:11px; font-weight:600; line-height:1; text-align:right; padding:3px 5px; border-radius:999px; background:rgba(255,255,255,.92); color:#d63638; }

		/* Font select */
		.oc-font-row { display:flex; gap:8px; align-items:center; }
		.oc-font-row select { flex:1; }
		.oc-font-size-row { display:flex; flex-direction:column; gap:8px; }
		@media (min-width:720px) { .oc-font-size-row { display:grid; grid-template-columns:minmax(0,1fr) minmax(160px,0.72fr); align-items:end; } }

		/* Colour swatches */
		.oc-colour-swatches { display:flex; flex-wrap:wrap; gap:8px; }
		.oc-colour-swatch { width:32px; height:32px; border-radius:50%; border:2px solid transparent; cursor:pointer; transition:transform .1s,border-color .1s; }
		.oc-colour-swatch:hover { transform:scale(1.15); }
		.oc-colour-swatch.oc-selected { border-color:#0073aa; box-shadow:0 0 0 2px rgba(0,115,170,.3); }
		.oc-color-freeform { display:flex; align-items:center; gap:8px; margin-top:4px; }
		.oc-color-freeform input[type="color"] { width:40px; height:32px; padding:2px; border:1px solid #ddd; border-radius:3px; cursor:pointer; }

		/* Upload zone — wrapper + Uppy DragDrop overrides */
		.oc-artwork-wrap { position:relative; display:flex; flex-direction:column; gap:8px; }
		.oc-artwork-wrap > [data-oc-tooltip] { position:absolute; top:6px; right:6px; z-index:3; }
		.oc-upload-zone { border-radius:8px; min-height:86px; cursor:pointer; overflow:hidden; background:transparent; }
		.oc-upload-zone .uppy-Root,
		.oc-upload-zone .uppy-DragDrop-container { width:100% !important; height:100% !important; min-height:86px; }
		.oc-upload-zone .uppy-DragDrop-container { border:0 !important; background:transparent !important; padding:0 !important; }
		.oc-upload-zone .uppy-DragDrop-inner {
			display:flex; flex-direction:column; align-items:center; justify-content:center;
			gap:6px; padding:12px 14px; min-height:86px;
			border:1px dashed #cbd5e1; border-radius:8px;
			background:transparent; transition:border-color .15s, background .15s;
		}
		.oc-upload-zone .uppy-DragDrop--isDragDropSupported { border:none; }
		.oc-upload-zone .uppy-DragDrop-container:hover .uppy-DragDrop-inner,
		.oc-upload-zone .uppy-DragDrop--isDraggingOver .uppy-DragDrop-inner {
			border-color:#0073aa; background:#eef7ff;
		}
		.oc-upload-zone .uppy-DragDrop-arrow { width:28px; height:28px; fill:#94a3b8; }
		.oc-upload-zone .uppy-DragDrop-label {
			font-size:12px; color:#334155; margin:0; font-weight:600;
		}
		.oc-upload-zone .uppy-DragDrop-browse {
			color:#0073aa; font-weight:600; text-decoration:underline;
		}
		.oc-upload-zone .uppy-DragDrop-note { font-size:11px; color:#64748b; margin-top:2px; }
		.oc-upload-zone.oc-upload-zone--uploaded { min-height:0; }
		.oc-upload-zone.oc-upload-zone--uploaded .uppy-Root,
		.oc-upload-zone.oc-upload-zone--uploaded .uppy-DragDrop-container { height:auto !important; min-height:0 !important; }
		.oc-upload-zone.oc-upload-zone--uploaded .uppy-DragDrop-inner { display:block; min-height:0; padding:10px 12px; border:1px solid #9bb9a8; border-radius:6px; background:#f4faf6; text-align:left; }
		.oc-upload-zone.oc-upload-zone--uploaded .uppy-DragDrop-arrow { display:none; }
		.oc-upload-zone.oc-upload-zone--uploaded .uppy-DragDrop-label { display:block; font-size:12px; line-height:1.25; color:#24573d; font-weight:800; letter-spacing:.04em; text-transform:uppercase; }
		.oc-upload-zone.oc-upload-zone--uploaded .uppy-DragDrop-browse { color:inherit; text-decoration:none; font-weight:800; }
		.oc-upload-zone.oc-upload-zone--uploaded .uppy-DragDrop-note { display:block; margin-top:3px; color:#426c55; font-size:11px; font-weight:700; text-transform:uppercase; }
		.oc-upload-zone.oc-upload-zone--error .uppy-DragDrop-inner { border-color:#d7aaa6; background:#fff7f6; }
		.oc-artwork-error { color:#b32d2e; font-size:12px; margin-top:6px; padding:6px 10px; background:#fef2f2; border:1px solid #fecaca; border-radius:4px; }
		.oc-resolution-warning { padding:10px 12px; border-radius:4px; font-size:12px; line-height:1.45; position:relative; }
		.oc-resolution-warning.oc-res-warning { background:#fff7f6; border:1px solid #e5bbb7; color:#7f2d27; }
		.oc-resolution-warning.oc-res-error { background:#fff4f3; border:1px solid #dca9a5; color:#72231f; }

		/* Clipart grid */
		.oc-clipart-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(64px,1fr)); gap:8px; max-height:240px; overflow-y:auto; padding:4px; }
		.oc-clipart-carousel { display:grid; grid-template-columns:12px minmax(0,1fr) 12px; grid-template-areas:"prev track next" "dots dots dots"; align-items:center; column-gap:3px; row-gap:5px; }
		.oc-clipart-grid--carousel { grid-area:track; display:flex; grid-template-columns:none; gap:5px; max-height:none; overflow-x:auto; overflow-y:hidden; scroll-snap-type:x mandatory; scroll-behavior:smooth; -webkit-overflow-scrolling:touch; scrollbar-width:none; }
		.oc-clipart-grid--carousel::-webkit-scrollbar { display:none; }
		.oc-clipart-grid--carousel .oc-clipart-item { flex:0 0 calc((100% - 12.5px) / 3.5); scroll-snap-align:start; }
		.oc-clipart-carousel-arrow { width:12px; height:24px; border:0; border-radius:0; background:transparent; color:#777; font-size:20px; font-weight:700; line-height:1; cursor:pointer; box-shadow:none; padding:0; }
		.oc-clipart-carousel-arrow:not(:disabled):hover { color:#0073aa; transform:scale(1.08); }
		.oc-clipart-carousel-arrow:disabled { opacity:.28; cursor:default; box-shadow:none; }
		.oc-clipart-carousel-arrow--prev { grid-area:prev; }
		.oc-clipart-carousel-arrow--next { grid-area:next; }
		.oc-clipart-carousel-dots { grid-area:dots; display:flex; justify-content:center; gap:6px; min-height:6px; }
		.oc-clipart-carousel-dot { width:5px; height:5px; min-width:5px; min-height:5px; max-width:5px; max-height:5px; flex:0 0 5px; padding:0; border:0; border-radius:50%; background:#bbb; cursor:pointer; appearance:none; line-height:0; transition:background .15s ease,transform .15s ease; }
		.oc-clipart-carousel-dot.oc-active { transform:scale(1.25); }
		.oc-clipart-carousel-dot.oc-active { background:#0073aa; }
		@media (max-width:639px) { .oc-clipart-grid--carousel .oc-clipart-item { flex-basis:calc((100% - 17.5px) / 4.5); } }
		.oc-clipart-carousel--single-page .oc-clipart-carousel-arrow,
		.oc-clipart-carousel--single-page .oc-clipart-carousel-dots { display:none; }
		.oc-clipart-carousel--single-page .oc-clipart-grid--carousel { grid-column:1 / -1; }
		.oc-clipart-item { border:0; border-radius:4px; padding:4px; cursor:pointer; background:#fff; transition:background .1s,transform .1s; }
		.oc-clipart-item:hover { background:#f0f8ff; transform:translateY(-1px); }
		.oc-clipart-item.oc-selected { background:#eef6ff; box-shadow:none; }
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
		.oc-spotify-modal-help .oc-help-toggle { color:#1db954; border-color:#7bdba0; cursor:pointer; }
		.oc-spotify-share-dialog { --sp-green:#1ed760; --sp-green-dark:#1db954; --sp-bg-dark:#1c2620; --sp-bg-darker:#122017; --sp-border:#3d5245; --sp-text:#fff; --sp-text-muted:#b3b3b3; --sp-anim-speed:.3s; padding:0; border:0; background:transparent; width:calc(100% - 32px); max-width:900px; color:var(--sp-text); margin:auto; opacity:0; transform:scale(.95) translateY(20px); transition:opacity var(--sp-anim-speed) ease, transform var(--sp-anim-speed) cubic-bezier(.16,1,.3,1); }
		.oc-spotify-share-dialog::backdrop { background-color:rgba(0,0,0,.85); backdrop-filter:blur(4px); opacity:0; transition:opacity var(--sp-anim-speed) ease; }
		.oc-spotify-share-dialog[open] { opacity:0; transform:scale(.95) translateY(20px); }
		.oc-spotify-share-dialog[open].is-visible { opacity:1; transform:scale(1) translateY(0); }
		.oc-spotify-share-dialog.is-visible::backdrop { opacity:1; }
		.oc-sp-modal-card { background-color:var(--sp-bg-dark); border:1px solid var(--sp-border); border-radius:20px; box-shadow:0 25px 50px -12px rgba(0,0,0,.7); display:flex; flex-direction:column; overflow:hidden; position:relative; font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif; text-align:left; }
		.oc-sp-visual-section { width:100%; height:200px; background:var(--sp-bg-darker); position:relative; display:flex; align-items:center; justify-content:center; overflow:hidden; flex-shrink:0; }
		.oc-sp-gradient-overlay { position:absolute; inset:0; background:linear-gradient(to bottom right,var(--sp-bg-dark),#000); z-index:1; }
		.oc-sp-album-art { position:relative; z-index:10; width:140px; aspect-ratio:9/16; border-radius:12px; background:linear-gradient(145deg,#1ed760,#101b14); transform:rotate(-3deg) translateY(10px); box-shadow:0 10px 30px rgba(0,0,0,.5); border:1px solid var(--sp-border); display:flex; align-items:center; justify-content:center; }
		.oc-sp-album-art-overlay { position:absolute; inset:0; border-radius:11px; background:rgba(0,0,0,.25); color:#fff; display:flex; align-items:center; justify-content:center; }
		.oc-sp-content-section { padding:1.5rem; flex:1; position:relative; }
		.oc-sp-close-btn { position:absolute; top:1rem; right:1rem; width:32px; height:32px; border:0; border-radius:50%; background:rgba(255,255,255,.1); color:#fff; display:flex; align-items:center; justify-content:center; cursor:pointer; z-index:20; padding:0; transition:background .2s; }
		.oc-sp-close-btn:hover { background:rgba(255,255,255,.2); }
		.oc-sp-title { margin:0 .75rem .5rem 0; font-size:1.5rem; font-weight:700; line-height:1.2; color:#fff; }
		.oc-sp-subtitle { margin:0 0 1.5rem; color:var(--sp-text-muted); font-size:.9rem; line-height:1.5; }
		.oc-sp-steps { display:flex; flex-direction:column; gap:1rem; }
		.oc-sp-step-item { display:flex; align-items:flex-start; gap:1rem; }
		.oc-sp-step-number { flex:none; width:28px; height:28px; margin-top:2px; background:rgba(56,224,123,.2); color:var(--sp-green); border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:.8rem; font-weight:700; }
		.oc-sp-step-content h3 { margin:0 0 4px; color:var(--sp-text-muted); font-size:.7rem; text-transform:uppercase; letter-spacing:1px; font-weight:700; }
		.oc-sp-step-content p { margin:0; font-size:.95rem; line-height:1.4; color:#fff; }
		.oc-sp-highlight { color:var(--sp-green); font-weight:600; }
		.oc-sp-footer { margin-top:2rem; padding-top:1.5rem; border-top:1px solid var(--sp-border); display:flex; justify-content:flex-end; }
		.oc-sp-action-btn { background:var(--sp-green); color:#000; font-weight:700; border:0; padding:12px 32px; border-radius:50px; cursor:pointer; font-size:1rem; transition:all .2s; box-shadow:0 4px 15px rgba(30,215,96,.2); }
		.oc-sp-action-btn:hover { background:var(--sp-green-dark); transform:scale(1.05); }
		.oc-sp-action-btn:active { transform:scale(.95); }
		@media (min-width: 768px) { .oc-sp-modal-card { flex-direction:row; } .oc-sp-visual-section { width:40%; height:auto; } .oc-sp-content-section { padding:2.5rem; width:60%; } .oc-sp-title { font-size:2rem; } .oc-sp-subtitle { font-size:1rem; } }

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
			.oc-control-group input[type="url"],
			.oc-control-group textarea,
			.oc-control-group select {
				font-size: 16px;
				min-height: 44px;
				padding: 9px 12px;
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
			.oc-help-toggle,
			.oc-spotify-help-toggle {
				min-width: 32px;
				min-height: 32px;
				touch-action: manipulation;
			}
		}
		';
	}
}
