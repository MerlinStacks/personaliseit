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

	private ?object $design                 = null;
	private array $areas                    = [];
	private array $layers                   = [];
	private array $design_variants          = [];
	private string $selected_design_variant = '';
	private array $clipart_items_cache      = [];
	private array $default_clipart_urls     = [];
	private array $design_context_cache     = [];
	private ?array $active_colours          = null;
	private ?array $active_image_filters    = null;
	private ?array $font_group_ids          = null;

	public function register(): void {
		add_action( 'wp', [ $this, 'maybe_load_design' ], 10 );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ], 20 );
		add_action( 'woocommerce_before_add_to_cart_button', [ $this, 'inject_panel' ], 10 );
		add_filter( 'woocommerce_add_to_cart_validation', [ $this, 'validate' ], 10, 6 );
	}

	// ── Design pre-load ───────────────────────────────────────────────────────

	public function maybe_load_design(): void {
		if ( ! is_product() ) {
			return;
		}

		$product_id = (int) get_queried_object_id();

		$assignment = OC_DB::get_assignment_for_product( $product_id, 0, true );
		if ( ! $assignment ) {
			OC_Logger::info( "OC Frontend: no design assignment for product {$product_id}." );
			return;
		}

		$requested_design_id = 0;
		$requested_variant   = isset( $_GET['oc_design_variant'] ) ? sanitize_key( wp_unslash( $_GET['oc_design_variant'] ) ) : '';
		if ( preg_match( '/^design-(\d+)$/', $requested_variant, $matches ) ) {
			$requested_design_id = absint( $matches[1] );
		}

		$context = $this->resolve_assignment_design( $assignment, $requested_design_id );
		if ( ! $context ) {
			OC_Logger::info( "OC Frontend: assignment for product {$product_id} has no active design with visible customisation content." );
			return;
		}
		$design = $context['design'];
		$areas  = $context['areas'];
		$layers = $context['layers'];

		$this->design                  = $design;
		$this->areas                   = $areas;
		$this->layers                  = $layers;
		$this->design_variants         = $this->build_design_variants( is_scalar( $assignment->design_variants ?? null ) ? (string) $assignment->design_variants : '', (int) $assignment->design_id, (int) $design->id );
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
		$state              = $this->build_design_state( $this->design, $this->areas, $this->layers );
		$max_upload_size_mb = (int) OC_Admin_Settings::get( 'max_upload_size_mb' );
		if ( 0 === $max_upload_size_mb ) {
			$max_upload_size_mb = 10;
		}

		$state['designVariants']        = $this->design_variants;
		$state['selectedDesignVariant'] = $this->selected_design_variant;
		$state['designVariantStates']   = $this->build_design_variant_states( $state );
		$state['activeAreaIndex']       = 0;
		$state['isLoading']             = false;
		$state['uploadUrl']             = rest_url( 'overcustomise/v1/upload-artwork' );
		$state['authoriseArtworkUrl']   = rest_url( 'overcustomise/v1/authorise-artwork-context' );
		$state['applyImageFilterUrl']   = rest_url( 'overcustomise/v1/apply-image-filter' );
		$state['generateAiImageUrl']    = rest_url( 'overcustomise/v1/generate-ai-image' );
		$state['savePreviewUrl']        = rest_url( 'overcustomise/v1/save-preview' );
		$state['validateSpotifyUrl']    = rest_url( 'overcustomise/v1/validate-spotify' );
		$state['locationLookupUrl']     = rest_url( 'overcustomise/v1/location-lookup' );
		$state['requestTokenUrl']       = rest_url( 'overcustomise/v1/session-token' );
		$state['productDesignUrl']      = rest_url( 'overcustomise/v1/product-design/' . (int) get_queried_object_id() );
		$state['productId']             = (int) get_queried_object_id();
		// Sending a stale guest nonce makes REST cookie authentication reject the
		// request before our short-lived public token can be verified. Product pages
		// may be cached, so only expose a REST nonce for authenticated users.
		$state['uploadNonce']           = is_user_logged_in() ? wp_create_nonce( 'wp_rest' ) : '';
		$state['requestToken']          = '';
		$state['requestTokenExpiresAt'] = 0;
		$state['maxUploadSizeMb']       = $max_upload_size_mb;
		$state['allowedFormats']        = (array) OC_Admin_Settings::get( 'allowed_upload_formats' );

		return $state;
	}

	/** Build the frontend state needed for one assignment design. */
	public static function build_assignment_state( int $product_id, int $variation_id = 0, int $requested_design_id = 0, bool $allow_variant_fallback = false ): array|\WP_Error {
		$assignment = OC_DB::get_assignment_for_product( $product_id, $variation_id, $allow_variant_fallback );
		if ( ! $assignment ) {
			return [
				'design_id' => 0,
				'active'    => false,
			];
		}

		$self    = new self();
		$context = $self->resolve_assignment_design( $assignment, $requested_design_id );
		if ( ! $context ) {
			return [
				'design_id' => (int) $assignment->design_id,
				'active'    => false,
			];
		}
		if ( $requested_design_id && $requested_design_id !== (int) $context['design']->id ) {
			return new \WP_Error( 'invalid_design', __( 'This customisation design is not available.', 'overcustomise' ), [ 'status' => 404 ] );
		}
		$design                  = $context['design'];
		$areas                   = $context['areas'];
		$layers                  = $context['layers'];
		$design_variants         = $self->build_design_variants( is_scalar( $assignment->design_variants ?? null ) ? (string) $assignment->design_variants : '', (int) $assignment->design_id, (int) $design->id );
		$selected_design_variant = 'design-' . (int) $design->id;

		$self->design                  = $design;
		$self->areas                   = $areas;
		$self->layers                  = $layers;
		$self->design_variants         = $design_variants;
		$self->selected_design_variant = $selected_design_variant;

		$design_state                   = $self->build_design_state( $design, $areas, $layers );
		$state                          = $design_state;
		$state['design_id']             = (int) $design->id;
		$state['active']                = true;
		$state['designVariants']        = $design_variants;
		$state['selectedDesignVariant'] = $selected_design_variant;
		$state['designVariantStates']   = $self->build_design_variant_states( $design_state );
		$state['panelHtml']             = $self->render_panel_html( $design, $areas, $layers, $design_variants );

		return $state;
	}

	/** Resolve the requested, default, or first active variant to visible frontend content. */
	private function resolve_assignment_design( object $assignment, int $requested_design_id = 0 ): ?array {
		$candidates = [];
		$allowed    = [ absint( $assignment->design_id ?? 0 ) ];
		$variants   = json_decode( is_scalar( $assignment->design_variants ?? null ) ? (string) $assignment->design_variants : '', true );
		foreach ( is_array( $variants ) ? $variants : [] as $variant ) {
			if ( is_array( $variant ) ) {
				$allowed[] = absint( $variant['designId'] ?? 0 );
			}
		}
		$allowed = array_values( array_unique( array_filter( $allowed ) ) );
		if ( $requested_design_id && in_array( $requested_design_id, $allowed, true ) ) {
			$candidates[] = $requested_design_id;
		}
		$candidates = array_values( array_unique( array_merge( $candidates, $allowed ) ) );

		foreach ( $candidates as $design_id ) {
			$context = $this->get_usable_design_context( $design_id );
			if ( $context ) {
				return $context;
			}
		}
		return null;
	}

	/** Return only visible areas and layers for an active design. */
	private function get_usable_design_context( int $design_id ): ?array {
		if ( array_key_exists( $design_id, $this->design_context_cache ) ) {
			return $this->design_context_cache[ $design_id ];
		}

		$design = $design_id ? OC_DB::get_design( $design_id ) : null;
		if ( ! $design || ! (bool) $design->active ) {
			return $this->design_context_cache[ $design_id ] = null;
		}
		$all_areas        = OC_DB::get_design_print_areas( $design_id );
		$shared_mockup_id = 0;
		foreach ( $all_areas as $candidate_area ) {
			$candidate_id = absint( $candidate_area->mockup_attachment_id ?? 0 );
			if ( $candidate_id && wp_get_attachment_image_url( $candidate_id, 'large' ) ) {
				$shared_mockup_id = $candidate_id;
				break;
			}
		}
		$areas = array_values(
			array_filter(
				$all_areas,
				static fn ( $area ): bool => ! isset( $area->visible ) || (bool) $area->visible
			)
		);
		$areas = array_map(
			static function ( $area ) use ( $shared_mockup_id ) {
				$area = clone $area;
				if ( $shared_mockup_id ) {
					$area->mockup_attachment_id = $shared_mockup_id;
				}
				return $area;
			},
			$areas
		);
		if ( ! $areas ) {
			return $this->design_context_cache[ $design_id ] = null;
		}
		$area_ids = array_fill_keys( array_filter( array_map( static fn ( $area ): int => (int) $area->id, $areas ) ), true );
		$layers   = array_values(
			array_filter(
				OC_DB::get_design_layers( $design_id ),
				static fn ( $layer ): bool => 'mask' === (string) ( $layer->type ?? '' )
					|| ( ( ! isset( $layer->visible ) || (bool) $layer->visible ) && ! empty( $area_ids[ (int) ( $layer->area_id ?? 0 ) ] ) )
			)
		);
		if ( ! $layers ) {
			return $this->design_context_cache[ $design_id ] = null;
		}
		$this->design_context_cache[ $design_id ] = [
			'design' => $design,
			'areas'  => $areas,
			'layers' => $layers,
		];
		return $this->design_context_cache[ $design_id ];
	}

	/** Build reusable frontend state for one design. */
	private function build_design_state( object $design, array $areas, array $layers ): array {
		$design_mask = null;
		foreach ( $layers as $layer ) {
			if ( 'mask' !== (string) ( $layer->type ?? '' ) ) {
				continue;
			}
			$design_mask   = $design_mask ?: $layer;
			$mask_settings = OC_Cart::normalise_layer_settings( $layer->settings ?? [], 'mask' );
			if ( self::design_mask_attachment_url( absint( $mask_settings['default_attachment_id'] ?? 0 ) ) ) {
				$design_mask = $layer;
				break;
			}
		}
		$areas            = array_values( array_filter( $areas, static fn ( $area ): bool => ! isset( $area->visible ) || (bool) $area->visible ) );
		$visible_area_ids = array_fill_keys( array_filter( array_map( static fn ( $area ): int => absint( $area->id ?? 0 ), $areas ) ), true );
		$layers           = array_values(
			array_filter(
				$layers,
				static fn ( $layer ): bool => 'mask' !== (string) ( $layer->type ?? '' )
				&& ( ! isset( $layer->visible ) || (bool) $layer->visible )
				&& ! empty( $visible_area_ids[ absint( $layer->area_id ?? 0 ) ] )
			)
		);
		if ( $design_mask && ! empty( $areas[0]->id ) ) {
			$design_mask          = clone $design_mask;
			$design_mask->area_id = (int) $areas[0]->id;
			$design_mask->visible = 1;
			$layers[]             = $design_mask;
		}

		$all_fonts                    = OC_Plugin::browser_fonts();
		$active_browser_font_ids      = array_map( static fn ( array $font ): int => (int) $font['id'], $all_fonts );
		$this->font_group_ids       ??= array_map( static fn ( $group ): int => (int) $group->id, OC_DB::get_font_groups() );
		$valid_font_group_ids         = $this->font_group_ids;
		$this->active_colours       ??= OC_DB::get_colours( true );
		$all_colours                  = $this->active_colours;
		$this->active_image_filters ??= OC_DB::get_image_filters( true );
		$image_filters                = $this->active_image_filters;
		$active_filter_ids            = array_fill_keys( array_map( static fn ( $filter ): int => (int) $filter->id, $image_filters ), true );

		// Group layers by area ID.
		$layers_by_area = [];
		foreach ( $layers as $layer ) {
			$layers_by_area[ (int) $layer->area_id ][] = $layer;
		}

		// Build areas with their layers.
		$areas_js                 = [];
		$layer_inputs             = []; // layerId → default input values
		$restricted_layer_colours = [];
		$mockup_id                = 0;
		$mockup_url               = '';
		$mockup_w                 = 0;
		$mockup_h                 = 0;
		foreach ( $areas as $candidate_area ) {
			$candidate_id = absint( $candidate_area->mockup_attachment_id ?? 0 );
			$img_src      = $candidate_id ? wp_get_attachment_image_src( $candidate_id, 'large' ) : false;
			if ( $img_src ) {
				$mockup_id  = $candidate_id;
				$mockup_url = $img_src[0];
				$mockup_w   = (int) $img_src[1];
				$mockup_h   = (int) $img_src[2];
				break;
			}
		}

		foreach ( $areas as $area ) {
			$area_layers = $layers_by_area[ (int) $area->id ] ?? [];
			$layers_js   = [];

			foreach ( $area_layers as $layer ) {
				if ( isset( $layer->visible ) && ! (bool) $layer->visible ) {
					continue;
				}

				$settings = OC_Cart::normalise_layer_settings( $layer->settings ?? [], sanitize_key( (string) ( $layer->type ?? '' ) ) );
				// Enforced AI instructions are server-only and must never enter public design state.
				unset( $settings['ai_prompt_instruction'] );
				$font_group_ids   = array_values( array_intersect( array_map( 'absint', $settings['font_groups'] ), $valid_font_group_ids ) );
				$allowed_font_ids = $font_group_ids
					? array_values( array_intersect( $active_browser_font_ids, OC_DB::get_font_ids_for_groups( $font_group_ids ) ) )
					: $active_browser_font_ids;
				$default_font_id  = absint( $settings['default_font_id'] ?? 0 );
				if ( ! in_array( $default_font_id, $allowed_font_ids, true ) ) {
					$default_font_id = (int) ( $allowed_font_ids[0] ?? 0 );
				}
				$settings['font_groups']     = $font_group_ids;
				$settings['default_font_id'] = $default_font_id;

				$colour_group_ids       = array_values( array_filter( array_map( 'absint', $settings['colour_groups'] ) ) );
				$default_colour         = sanitize_hex_color( (string) ( $settings['default_color'] ?? '#000000' ) ) ?: '#000000';
				$default_attachment_id  = absint( $settings['default_attachment_id'] ?? 0 );
				$is_mask_attachment     = 'mask' === (string) $layer->type;
				$default_attachment_url = $is_mask_attachment
					? self::design_mask_attachment_url( $default_attachment_id )
					: ( $default_attachment_id ? (string) wp_get_attachment_url( $default_attachment_id ) : '' );
				$is_valid_attachment    = $is_mask_attachment
					? '' !== $default_attachment_url
					: OC_Upload_Handler::admin_default_attachment_is_valid( $default_attachment_id )
						&& str_starts_with( (string) get_post_mime_type( $default_attachment_id ), 'image/' );
				if ( $default_attachment_id && ! $is_valid_attachment ) {
					$default_attachment_id  = 0;
					$default_attachment_url = '';
				}
				$default_image_filter_id = absint( $settings['default_image_filter_id'] ?? 0 );
				$image_filter_ids        = array_values(
					array_filter(
						array_map(
							'absint',
							array_filter( $settings['image_filter_ids'], static fn ( $filter_id ): bool => ! empty( $active_filter_ids[ absint( $filter_id ) ] ) )
						)
					)
				);
				if ( $default_image_filter_id && ! in_array( $default_image_filter_id, $image_filter_ids, true ) ) {
					$default_image_filter_id = 0;
				}
				$settings['default_attachment_id']   = $default_attachment_id;
				$settings['default_attachment_url']  = $default_attachment_url;
				$settings['image_filter_ids']        = $image_filter_ids;
				$settings['default_image_filter_id'] = $default_image_filter_id;
				if ( 'clipart' === (string) $layer->type ) {
					$settings['default_clipart_url']          = '';
					$settings['default_clipart_recolourable'] = false;
				}
				if ( ! empty( $colour_group_ids ) ) {
					$allowed_colours                              = OC_DB::get_colours_for_groups( $colour_group_ids );
					$allowed_hexes                                = array_values( array_filter( array_map( fn( $colour ) => sanitize_hex_color( (string) ( $colour->hex ?? '' ) ), $allowed_colours ) ) );
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
					'value'               => in_array( $layer->type, [ 'text', 'textarea' ], true ) && ! empty( $layer->locked ) ? (string) ( $settings['default_text'] ?? '' ) : '',
					'fontId'              => absint( $settings['default_font_id'] ),
					'fontSize'            => absint( $settings['default_font_size'] ?? 0 ),
					'colorHex'            => $default_colour,
					'attachmentId'        => in_array( $layer->type, [ 'image', 'ai_image', 'clipmask' ], true ) ? $default_attachment_id : 0,
					'attachmentUrl'       => in_array( $layer->type, [ 'image', 'ai_image', 'clipmask' ], true ) ? $default_attachment_url : '',
					'sourceAttachmentId'  => in_array( $layer->type, [ 'image', 'ai_image', 'clipmask' ], true ) ? $default_attachment_id : 0,
					'sourceAttachmentUrl' => in_array( $layer->type, [ 'image', 'ai_image', 'clipmask' ], true ) ? $default_attachment_url : '',
					'imageFilterId'       => in_array( $layer->type, [ 'image', 'ai_image' ], true ) ? $default_image_filter_id : 0,
					'aiDescription'       => '',
					'imageCrop'           => 0,
					'clipartId'           => 0,
					'clipartUrl'          => '',
					'clipartRecolourable' => false,
					'date'                => 'night_sky' === (string) $layer->type ? wp_date( 'Y-m-d' ) : '',
					'time'                => '22:00',
					'utcOffset'           => 0,
					'timezone'            => 'UTC',
					'locationLabel'       => '',
					'latitude'            => null,
					'longitude'           => null,
					'nightSkyGeometry'    => null,
					'nightSkyLabel'       => '',
				];
			}

			$areas_js[] = [
				'id'                => (int) $area->id,
				'label'             => $area->label,
				'printMethod'       => $area->print_method,
				'engravingMaterial' => isset( $area->engraving_material ) ? (string) $area->engraving_material : 'silver_metal',
				'mockupId'          => $mockup_id,
				'mockupUrl'         => $mockup_url,
				'mockupW'           => $mockup_w,
				'mockupH'           => $mockup_h,
				'bounds'            => [
					'x'        => (int) $area->canvas_x,
					'y'        => (int) $area->canvas_y,
					'w'        => (int) $area->canvas_w,
					'h'        => (int) $area->canvas_h,
					'unit'     => isset( $area->canvas_unit ) ? (string) $area->canvas_unit : 'px',
					'dpi'      => isset( $area->canvas_dpi ) ? (int) $area->canvas_dpi : 300,
					'rotation' => isset( $area->canvas_rotation ) ? (int) $area->canvas_rotation : 0,
				],
				'layers'            => $layers_js,
			];
		}

		// Colours as swatches.
		$colours_js = array_map(
			function ( $c ) {
				return [
					'id'   => (int) $c->id,
					'name' => $c->name,
					'hex'  => $c->hex,
				];
			},
			$all_colours
		);

		// Clipart items grouped per clipart layer.
		$clipart_by_layer = $this->build_clipart_by_layer( $layers, $areas );
		foreach ( $layers as $layer ) {
			if ( 'clipart' !== $layer->type ) {
				continue;
			}
			$layer_id = (int) $layer->id;
			if ( ! isset( $layer_inputs[ $layer_id ] ) ) {
				continue;
			}

			$settings = OC_Cart::normalise_layer_settings( $layer->settings ?? [], 'clipart' );

			$default_clipart_id = absint( $settings['default_clipart_id'] ?? 0 );
			$selected_item      = null;
			foreach ( $clipart_by_layer[ $layer_id ] ?? [] as $item ) {
				if ( (int) $item['id'] === $default_clipart_id ) {
					$selected_item = $item;
					break;
				}
			}
			if ( ! $selected_item && ( ! empty( $settings['required'] ) || empty( $settings['allow_clipart_change'] ) ) ) {
				$selected_item = $clipart_by_layer[ $layer_id ][0] ?? null;
			}
			if ( is_array( $selected_item ) ) {
				$layer_inputs[ $layer_id ]['clipartId']           = (int) $selected_item['id'];
				$layer_inputs[ $layer_id ]['clipartUrl']          = (string) $selected_item['url'];
				$layer_inputs[ $layer_id ]['clipartRecolourable'] = ! empty( $selected_item['recolourable'] );
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
			'designId'               => (int) $design->id,
			'designName'             => $design->name,
			'flatRate'               => (float) $design->flat_rate,
			'areas'                  => $areas_js,
			'fonts'                  => $all_fonts,
			'colours'                => $colours_js,
			'imageFilters'           => array_map(
				function ( $filter ) {
					return [
						'id'    => (int) $filter->id,
						'name'  => (string) $filter->name,
						'key'   => (string) $filter->filter_key,
						'value' => (float) $filter->value,
						'isAi'  => 'ai' === (string) $filter->filter_key,
					];
				},
				$image_filters
			),
			'clipartByLayer'         => $clipart_by_layer,
			'clipartGroups'          => $clipart_groups,
			'layerInputs'            => $layer_inputs,
			'restrictedLayerColours' => $restricted_layer_colours,
		];
	}

	/** Return a validated design-mask image URL. */
	private static function design_mask_attachment_url( int $attachment_id ): string {
		if ( $attachment_id <= 0 || 'attachment' !== get_post_type( $attachment_id ) ) {
			return '';
		}
		$url          = (string) wp_get_attachment_url( $attachment_id );
		$mime         = strtolower( (string) get_post_mime_type( $attachment_id ) );
		$url_path     = (string) wp_parse_url( $url, PHP_URL_PATH );
		$is_supported = in_array( $mime, [ 'image/png', 'image/x-png', 'image/svg+xml', 'image/webp' ], true )
			|| ( str_starts_with( $mime, 'image/' ) && in_array( strtolower( pathinfo( $url_path, PATHINFO_EXTENSION ) ), [ 'png', 'svg', 'webp' ], true ) );

		return $is_supported ? $url : '';
	}

	/** Build frontend-safe alternate design options from assignment JSON. */
	private function build_design_variants( string $variants_json, int $default_design_id, int $selected_design_id ): array {
		$options        = [];
		$default_option = $this->build_design_variant_option( $default_design_id, '', $selected_design_id );
		if ( $default_option ) {
			$options[] = $default_option;
		}

		$decoded = json_decode( $variants_json, true );
		if ( ! is_array( $decoded ) ) {
			return count( $options ) > 1 ? $options : [];
		}

		foreach ( $decoded as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$design_id = absint( $item['designId'] ?? 0 );
			if ( ! $design_id || $design_id === $default_design_id ) {
				continue;
			}
			$option = $this->build_design_variant_option( $design_id, is_scalar( $item['label'] ?? null ) ? (string) $item['label'] : '', $selected_design_id );
			if ( $option ) {
				$options[] = $option;
			}
		}

		return count( $options ) > 1 ? $options : [];
	}

	/** Build one selectable design option from a design's artwork content. */
	private function build_design_variant_option( int $design_id, string $label, int $selected_design_id ): ?array {
		$context = $this->get_usable_design_context( $design_id );
		if ( ! $context ) {
			return null;
		}
		$design = $context['design'];
		$areas  = $context['areas'];
		$area   = $areas[0] ?? null;
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
			'id'          => 'design-' . $design_id,
			'designId'    => $design_id,
			'label'       => sanitize_text_field( $label ) ?: ( $design->name ?: __( 'Untitled Design #', 'overcustomise' ) . $design_id ),
			'thumbUrl'    => $thumb_url,
			'thumbLayers' => $thumb_layers,
			'selected'    => $design_id === $selected_design_id,
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
			if ( ( isset( $layer->visible ) && ! (bool) $layer->visible ) || (int) $layer->area_id !== (int) $area->id ) {
				continue;
			}

			$settings = OC_Cart::normalise_layer_settings( $layer->settings ?? [], sanitize_key( (string) ( $layer->type ?? '' ) ) );

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

				$font             = $this->get_design_variant_thumb_font( absint( $settings['default_font_id'] ?? 0 ) );
				$longest_line     = max( array_map( [ $this, 'string_length' ], preg_split( '/\R/', $text ) ?: [ $text ] ) );
				$line_count       = max( 1, count( preg_split( '/\R/', $text ) ?: [ $text ] ) );
				$scaled_font_size = absint( $settings['default_font_size'] ?? 24 ) * $scale * 0.5;
				$box_height_cap   = ( (float) $item['h'] / $line_count ) * 0.55;
				$box_width_cap    = ( (float) $item['w'] / max( 1, $longest_line ) ) * 1.15;

				$item['text']       = $text;
				$item['color']      = sanitize_hex_color( (string) ( $settings['default_color'] ?? '#111111' ) ) ?: '#111111';
				$item['fontSize']   = max( 3.5, min( 12, $scaled_font_size, $box_height_cap, $box_width_cap ) );
				$item['fontFamily'] = $font['family'];
				$item['fontWeight'] = $font['weight'];
				$item['fontStyle']  = $font['style'];
				$items[]            = $item;
				continue;
			}

			if ( in_array( (string) $layer->type, [ 'image', 'ai_image', 'clipart' ], true ) ) {
				$url = $this->get_design_layer_artwork_url( $layer, $settings );
				if ( '' === $url ) {
					continue;
				}

				$item['url'] = $url;
				$items[]     = $item;
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
			if ( ( isset( $layer->visible ) && ! (bool) $layer->visible ) || (int) $layer->area_id !== (int) $area->id || ! in_array( (string) $layer->type, [ 'text', 'textarea', 'image', 'clipart' ], true ) ) {
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
			foreach ( OC_Plugin::browser_fonts() as $font ) {
				$fonts_by_id[ (int) $font['id'] ] = [
					'family' => (string) $font['name'],
					'weight' => (string) $font['weight'],
					'style'  => (string) $font['style'],
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
		if ( 'image' === (string) $layer->type ) {
			$attachment_id = absint( $settings['default_attachment_id'] ?? 0 );
			if ( $attachment_id && OC_Upload_Handler::admin_default_attachment_is_valid( $attachment_id ) && str_starts_with( (string) get_post_mime_type( $attachment_id ), 'image/' ) ) {
				$url = wp_get_attachment_image_url( $attachment_id, 'medium' ) ?: wp_get_attachment_url( $attachment_id );
				if ( $url ) {
					return (string) $url;
				}
			}

			return '';
		}

		if ( 'clipart' === (string) $layer->type ) {
			return $this->default_clipart_url( absint( $settings['default_clipart_id'] ?? 0 ) );
		}

		return '';
	}

	/** Return a thumbnail URL for the first visible artwork/text layer, without the mockup background. */
	private function get_design_variant_artwork_thumb_url( int $design_id, array $areas ): string {
		$layers = OC_DB::get_design_layers( $design_id );
		if ( empty( $layers ) ) {
			return '';
		}

		$area_ids = array_map( fn( $area ) => (int) $area->id, $areas );
		foreach ( $layers as $layer ) {
			if ( ( isset( $layer->visible ) && ! (bool) $layer->visible ) || ! in_array( (int) $layer->area_id, $area_ids, true ) ) {
				continue;
			}

			$settings = OC_Cart::normalise_layer_settings( $layer->settings ?? [], sanitize_key( (string) ( $layer->type ?? '' ) ) );

			if ( 'image' === (string) $layer->type ) {
				$attachment_id = absint( $settings['default_attachment_id'] ?? 0 );
				if ( $attachment_id && OC_Upload_Handler::admin_default_attachment_is_valid( $attachment_id ) && str_starts_with( (string) get_post_mime_type( $attachment_id ), 'image/' ) ) {
					$url = wp_get_attachment_image_url( $attachment_id, 'medium' ) ?: wp_get_attachment_url( $attachment_id );
					if ( $url ) {
						return (string) $url;
					}
				}
			}

			if ( 'clipart' === (string) $layer->type ) {
				$url = $this->default_clipart_url( absint( $settings['default_clipart_id'] ?? 0 ) );
				if ( '' !== $url ) {
					return $url;
				}
			}
		}

		return $this->build_design_variant_text_thumb_url( $layers, $areas[0] ?? null );
	}

	/** Resolve and memoize one active default clipart URL, including invalid results. */
	private function default_clipart_url( int $clipart_id ): string {
		if ( $clipart_id <= 0 ) {
			return '';
		}
		if ( array_key_exists( $clipart_id, $this->default_clipart_urls ) ) {
			return $this->default_clipart_urls[ $clipart_id ];
		}

		global $wpdb;
		$path                                      = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT file_path FROM {$wpdb->prefix}oc_clipart WHERE id = %d AND active = 1 LIMIT 1",
				$clipart_id
			)
		);
		$this->default_clipart_urls[ $clipart_id ] = is_string( $path ) && '' !== $path
			? self::clipart_public_url( $path )
			: '';

		return $this->default_clipart_urls[ $clipart_id ];
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
			if ( ( isset( $layer->visible ) && ! (bool) $layer->visible ) || (int) $layer->area_id !== (int) $area->id || 'text' !== (string) $layer->type ) {
				continue;
			}

			$settings = OC_Cart::normalise_layer_settings( $layer->settings ?? [], 'text' );

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

	/** Cache only the selected state; alternate designs are loaded on demand. */
	private function build_design_variant_states( array $selected_state ): array {
		$design_id  = (int) ( $this->design->id ?? 0 );
		$variant_id = $this->selected_design_variant;
		if ( ! $design_id || '' === $variant_id ) {
			return [];
		}

		$design_variants                         = $this->mark_design_variants_selected( $variant_id );
		$selected_state['designVariants']        = $design_variants;
		$selected_state['selectedDesignVariant'] = $variant_id;
		$selected_state['panelHtml']             = $this->render_panel_html( $this->design, $this->areas, $this->layers, $design_variants );

		return [ $variant_id => $selected_state ];
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

		$by_layer = [];
		$layers   = $layers ?? $this->layers;
		$areas    = $areas ?? $this->areas;

		$methods_by_area = [];
		foreach ( $areas as $area ) {
			$methods_by_area[ (int) $area->id ] = sanitize_key( (string) ( $area->print_method ?? '' ) );
		}

		foreach ( $layers as $layer ) {
			if ( $layer->type !== 'clipart' || ( isset( $layer->visible ) && ! (bool) $layer->visible ) || ! isset( $methods_by_area[ (int) $layer->area_id ] ) ) {
				continue;
			}

			$settings  = OC_Cart::normalise_layer_settings( $layer->settings ?? [], 'clipart' );
			$group_ids = array_values( array_unique( array_filter( array_map( 'absint', $settings['clipart_groups'] ) ) ) );
			sort( $group_ids, SORT_NUMERIC );
			$layer_id     = (int) $layer->id;
			$print_method = $methods_by_area[ (int) $layer->area_id ] ?? '';
			$cache_key    = $print_method . ':' . implode( ',', $group_ids );

			if ( isset( $this->clipart_items_cache[ $cache_key ] ) ) {
				$by_layer[ $layer_id ] = $this->clipart_items_cache[ $cache_key ];
				continue;
			}

			if ( ! empty( $group_ids ) ) {
				$placeholders = implode( ',', array_fill( 0, count( $group_ids ), '%d' ) );
				$items        = $wpdb->get_results(
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

			$items = array_values(
				array_filter(
					$items,
					function ( $item ) use ( $print_method ) {
						$allowed = self::normalise_clipart_print_methods( (string) ( $item->allowed_print_methods ?? '' ) );
						return empty( $allowed ) || in_array( $print_method, $allowed, true );
					}
				)
			);

			$items                                   = array_values(
				array_filter(
					array_map(
						function ( $item ) {
							$url        = self::clipart_public_url( (string) $item->file_path );
							$groupNames = $item->group_names ? array_filter( array_map( 'trim', explode( '||', $item->group_names ) ) ) : [];
							return [
								'id'                  => (int) $item->id,
								'name'                => $item->name,
								'url'                 => $url,
								'fileType'            => (string) $item->file_type,
								'recolourable'        => ( ! property_exists( $item, 'colour_changeable' ) || (bool) $item->colour_changeable ) && 'svg' === strtolower( (string) $item->file_type ),
								'allowedPrintMethods' => self::normalise_clipart_print_methods( (string) ( $item->allowed_print_methods ?? '' ) ),
								'groupNames'          => $groupNames,
							];
						},
						$items
					),
					static fn ( array $item ): bool => '' !== $item['url']
				)
			);
			$this->clipart_items_cache[ $cache_key ] = $items;
			$by_layer[ $layer_id ]                   = $items;
		}

		return $by_layer;
	}

	/** Resolve a stored clipart file only when it remains inside the managed directory. */
	private static function clipart_public_url( string $path ): string {
		$uploads   = wp_upload_dir();
		$base      = realpath( (string) ( $uploads['basedir'] ?? '' ) );
		$root      = realpath( trailingslashit( (string) ( $uploads['basedir'] ?? '' ) ) . 'overcustomise/clipart' );
		$real      = realpath( $path );
		$base_path = $base ? rtrim( wp_normalize_path( $base ), '/' ) : '';
		$root_path = $root ? rtrim( wp_normalize_path( $root ), '/' ) : '';
		$real_path = $real ? wp_normalize_path( $real ) : '';
		if ( '' === $base_path || '' === $root_path || '' === $real_path || ! is_file( $real )
			|| ! str_starts_with( $root_path, $base_path . '/' )
			|| ! str_starts_with( $real_path, $root_path . '/' )
		) {
			return '';
		}
		$relative = ltrim( substr( $real_path, strlen( $base_path ) ), '/' );
		return esc_url_raw( trailingslashit( (string) $uploads['baseurl'] ) . $relative );
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

	// ── Panel injection ───────────────────────────────────────────────────────

	public function inject_panel(): void {
		if ( ! is_singular( 'product' ) ) {
			return;
		}

		// Admin hint when no design is assigned — only visible to shop managers.
		if ( null === $this->design ) {
			if ( current_user_can( 'manage_woocommerce' ) ) {
				$product_id = (int) get_queried_object_id();
				$assign_url = admin_url( 'admin.php?page=overcustomise-products&tab=products' );
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

		$design           = $this->design;
		$areas            = $this->areas;
		$layers           = $this->layers;
		$clipart_by_layer = $this->build_clipart_by_layer( $layers, $areas );
		$design_variants  = $this->design_variants;

		include $template;
	}

	// ── Validation ────────────────────────────────────────────────────────────

	public function validate( bool $passed, int $product_id, int $qty, int|string $variation_id = 0, array $variations = [], array $cart_item_data = [] ): bool {
		$variation_id = absint( $variation_id );

		if ( ! $passed ) {
			return false;
		}

		$assignment = OC_DB::get_assignment_for_product( $product_id, $variation_id );
		if ( ! $assignment || ! OC_Cart::assignment_has_usable_design( $assignment ) ) {
			return $passed;
		}

		$raw    = OC_Cart::submission_raw_from_request( $cart_item_data );
		$result = OC_Cart::validate_v2_submission( $product_id, $variation_id, $raw );
		if ( is_wp_error( $result ) ) {
			wc_add_notice( $result->get_error_message(), 'error' );
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
		.oc-design-variant-option.oc-loading { cursor:progress; opacity:.65; }
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
