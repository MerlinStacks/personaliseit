<?php
/**
 * REST API — /wp-json/overcustomise/v1/ endpoints.
 *
 * Endpoints:
 *   GET  /product-config/{product_id}   — fetch config + print areas + fonts for the customiser
 *   GET  /session-token                 — issue/reuse a no-store frontend request token
 *   POST /upload-artwork                — customer artwork upload
 *   GET  /fonts                         — list active fonts (public)
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

// Explicit loading also supports callers that require this file directly.
require_once __DIR__ . '/rest-api/trait-oc-rest-api-authentication.php';
require_once __DIR__ . '/rest-api/trait-oc-rest-api-budgets.php';
require_once __DIR__ . '/rest-api/trait-oc-rest-api-artwork.php';
require_once __DIR__ . '/rest-api/trait-oc-rest-api-ai.php';
require_once __DIR__ . '/rest-api/trait-oc-rest-api-previews.php';
require_once __DIR__ . '/rest-api/trait-oc-rest-api-lookups.php';
require_once __DIR__ . '/rest-api/trait-oc-rest-api-vdp.php';

/** Composes endpoint traits with shared constants, routes, and product helpers. */
class OC_Rest_API {

	use OC_Rest_API_Authentication;
	use OC_Rest_API_Budgets;
	use OC_Rest_API_Artwork;
	use OC_Rest_API_AI;
	use OC_Rest_API_Previews;
	use OC_Rest_API_Lookups;
	use OC_Rest_API_VDP;

	private const NAMESPACE                 = 'overcustomise/v1';
	private const PUBLIC_TOKEN_TTL          = 21600; // 6 hours.
	private const PUBLIC_TOKEN_REFRESH      = 300; // Rotate before long-running AI requests can outlive it.
	private const AI_LOCK_TTL               = 300;
	private const PREVIEW_LOCK_TTL          = 60;
	private const MAX_PREVIEW_BYTES         = 10485760;
	private const MAX_AI_RESULT_BYTES       = 15728640;
	private const MAX_AI_PROMPT_BYTES       = 16384;
	private const MAX_AI_FILTER_ATTEMPTS    = 3;
	private const SPOTIFY_RESPONSE_BYTES    = 524288;
	private const LOCATION_RESPONSE_BYTES   = 65536;
	private const SPOTIFY_VALID_CACHE_TTL   = 43200;
	private const SPOTIFY_INVALID_CACHE_TTL = 3600;
	private const PUBLIC_TOKEN_SESSION_KEY  = 'oc_public_request_token';
	private const BROWSER_COOKIE            = 'oc_private_browser';
	private const PREVIEW_OPTION_PREFIX     = 'oc_private_preview_';

	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
		add_action( 'init', [ self::class, 'ensure_vdp_storage' ] );
		add_action( 'admin_post_oc_serve_preview', [ self::class, 'serve_private_preview' ] );
		add_action( 'admin_post_nopriv_oc_serve_preview', [ self::class, 'serve_private_preview' ] );
	}

	public function register_routes(): void {
		// Product config + print areas for the frontend customiser.
		register_rest_route(
			self::NAMESPACE,
			'/product-config/(?P<product_id>\d+)',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_product_config' ],
				'permission_callback' => '__return_true', // Public — only returns active configs.
				'args'                => [
					'product_id' => [
						'validate_callback' => fn( $v ) => is_numeric( $v ) && $v > 0,
						'sanitize_callback' => 'absint',
					],
				],
			]
		);

		// Active fonts list (public — needed by frontend customiser).
		register_rest_route(
			self::NAMESPACE,
			'/fonts',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_fonts' ],
				'permission_callback' => '__return_true',
			]
		);

		// Reusable session-bound token for guest writes and cart validation.
		register_rest_route(
			self::NAMESPACE,
			'/session-token',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_session_token' ],
				'permission_callback' => '__return_true',
			]
		);

		// Artwork upload (customer).
		register_rest_route(
			self::NAMESPACE,
			'/upload-artwork',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'upload_artwork' ],
				'permission_callback' => [ $this, 'public_write_permission' ],
			]
		);

		// Authorise an owned upload for a matching layer on this product.
		register_rest_route(
			self::NAMESPACE,
			'/authorise-artwork-context',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'authorise_artwork_context' ],
				'permission_callback' => [ $this, 'public_write_permission' ],
			]
		);

		// Apply an AI prompt filter to previously uploaded artwork.
		register_rest_route(
			self::NAMESPACE,
			'/apply-image-filter',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'apply_image_filter' ],
				'permission_callback' => [ $this, 'public_write_permission' ],
			]
		);

		// Generate owned artwork for an AI Image layer.
		register_rest_route(
			self::NAMESPACE,
			'/generate-ai-image',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'generate_ai_image' ],
				'permission_callback' => [ $this, 'public_write_permission' ],
			]
		);

		// Design assignment for a specific product / variation (used by frontend JS).
		register_rest_route(
			self::NAMESPACE,
			'/product-design/(?P<product_id>\d+)',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_product_design' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'product_id' => [
						'validate_callback' => fn( $v ) => is_numeric( $v ) && $v > 0,
						'sanitize_callback' => 'absint',
					],
					'variant_id' => [
						'default'           => 0,
						'sanitize_callback' => 'absint',
					],
					'design_id'  => [
						'default'           => 0,
						'sanitize_callback' => 'absint',
					],
				],
			]
		);

		// Save canvas snapshot as a JPEG for cart/order preview.
		register_rest_route(
			self::NAMESPACE,
			'/save-preview',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'save_preview' ],
				'permission_callback' => [ $this, 'public_write_permission' ],
			]
		);

		// Validate Spotify links and detect private/unavailable resources.
		register_rest_route(
			self::NAMESPACE,
			'/validate-spotify',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'validate_spotify' ],
				'permission_callback' => [ $this, 'public_write_permission' ],
			]
		);

		// Privacy-preserving place lookup for the Night Sky customer control.
		register_rest_route(
			self::NAMESPACE,
			'/location-lookup',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'lookup_location' ],
				'permission_callback' => [ $this, 'public_write_permission' ],
				'args'                => [
					'query' => [
						'required'          => true,
						'validate_callback' => [ self::class, 'validate_location_query' ],
					],
				],
			]
		);

		// Admin: regenerate print files for an order item.
		register_rest_route(
			self::NAMESPACE,
			'/regenerate-files',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'regenerate_files' ],
				'permission_callback' => fn() => current_user_can( 'manage_woocommerce' ),
			]
		);

		// Admin: upload CSV for VDP.
		register_rest_route(
			self::NAMESPACE,
			'/vdp-upload-csv',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'upload_vdp_csv' ],
				'permission_callback' => fn() => current_user_can( 'manage_woocommerce' ),
			]
		);
	}

	/** Resolve only published, catalog-visible product/variation contexts. */
	private static function public_product_context( int $product_id, int $variation_id = 0, bool $require_variation = false ): array|\WP_Error {
		$product = wc_get_product( $product_id );
		if ( ! $product || $product->is_type( 'variation' ) || ! self::product_is_publicly_visible( $product ) ) {
			return new \WP_Error( 'invalid_product', __( 'This product is not available for customisation.', 'overcustomise' ), [ 'status' => 404 ] );
		}

		$variation = null;
		if ( $variation_id > 0 ) {
			$variation = wc_get_product( $variation_id );
			if ( ! $variation || ! $variation->is_type( 'variation' )
				|| $product_id !== (int) $variation->get_parent_id()
				|| ! self::product_is_publicly_visible( $variation )
			) {
				return new \WP_Error( 'invalid_variation', __( 'The selected variation is not available for this product.', 'overcustomise' ), [ 'status' => 404 ] );
			}
		} elseif ( $require_variation && $product->is_type( 'variable' ) ) {
			return new \WP_Error( 'invalid_variation', __( 'Please select an available product variation.', 'overcustomise' ), [ 'status' => 400 ] );
		}

		return [
			'product'   => $product,
			'variation' => $variation,
		];
	}

	/** Require publish status and WooCommerce's public visibility decision. */
	private static function product_is_publicly_visible( object $product ): bool {
		if ( ! method_exists( $product, 'get_status' ) || 'publish' !== (string) $product->get_status() ) {
			return false;
		}
		if ( method_exists( $product, 'get_catalog_visibility' ) && 'hidden' === (string) $product->get_catalog_visibility() ) {
			return false;
		}
		if ( method_exists( $product, 'variation_is_visible' ) && $product->is_type( 'variation' ) && ! $product->variation_is_visible() ) {
			return false;
		}

		return method_exists( $product, 'is_visible' ) && (bool) $product->is_visible();
	}

	/** Resolve an active design that is assigned to an already public product context. */
	private static function active_assignment_design( int $product_id, int $variation_id, int $design_id ): array|\WP_Error {
		$product_context = self::public_product_context( $product_id, $variation_id, true );
		if ( is_wp_error( $product_context ) ) {
			return $product_context;
		}

		$assignment = OC_DB::get_assignment_for_product( $product_id, $variation_id );
		$design     = $design_id > 0 ? OC_DB::get_design( $design_id ) : null;
		if ( ! $assignment || ! OC_DB::assignment_allows_design( $assignment, $design_id ) || ! $design || ! (bool) $design->active ) {
			return new \WP_Error( 'invalid_design', __( 'This customisation design is not available.', 'overcustomise' ), [ 'status' => 404 ] );
		}

		return array_merge(
			$product_context,
			[
				'assignment' => $assignment,
				'design'     => $design,
			]
		);
	}

	/** Resolve a public assignment for a read, including initial variable-product fallback. */
	private static function public_read_assignment_context( int $product_id, int $variation_id ): array|\WP_Error {
		$product_context = self::public_product_context( $product_id, $variation_id, false );
		if ( is_wp_error( $product_context ) ) {
			return $product_context;
		}

		$assignment            = OC_DB::get_assignment_for_product( $product_id, $variation_id, 0 === $variation_id );
		$assigned_variation_id = absint( $assignment->variant_id ?? 0 );
		if ( 0 === $variation_id && $assigned_variation_id ) {
			$fallback_context = self::public_product_context( $product_id, $assigned_variation_id, true );
			if ( is_wp_error( $fallback_context ) ) {
				return $fallback_context;
			}
		}

		return array_merge( $product_context, [ 'assignment' => $assignment ] );
	}

	/** Fetch a visible, editable layer whose containing area is also visible. */
	private static function public_design_layer( int $design_id, int $layer_id, array $eligible_types ): object {
		global $wpdb;
		$layer = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT l.id, l.design_id, l.area_id, l.type, l.visible, l.locked, l.settings
			 FROM {$wpdb->prefix}oc_design_layers l
			 INNER JOIN {$wpdb->prefix}oc_design_print_areas a ON a.id = l.area_id AND a.design_id = l.design_id
			 INNER JOIN {$wpdb->prefix}oc_designs d ON d.id = l.design_id
			 WHERE l.id = %d AND l.design_id = %d AND l.visible = 1 AND l.locked = 0 AND a.visible = 1 AND d.active = 1
			 LIMIT 1",
				$layer_id,
				$design_id
			)
		);
		if ( ! $layer || ! in_array( (string) $layer->type, $eligible_types, true ) ) {
			return new \WP_Error( 'invalid_layer', __( 'This customisation layer is not available.', 'overcustomise' ), [ 'status' => 404 ] );
		}

		return $layer;
	}

	/** Decode a layer settings JSON object, rejecting malformed or oversized data. */
	private static function decode_layer_settings( mixed $raw ): array|\WP_Error {
		if ( null === $raw || '' === $raw ) {
			return [];
		}
		if ( ! is_string( $raw ) || strlen( $raw ) > 65535 || ! str_starts_with( ltrim( $raw ), '{' ) ) {
			return new \WP_Error( 'invalid_layer_settings', __( 'This customisation layer is not configured correctly.', 'overcustomise' ), [ 'status' => 503 ] );
		}

		try {
			$settings = json_decode( $raw, true, 32, JSON_THROW_ON_ERROR );
		} catch ( \JsonException $e ) {
			OC_Logger::warning( 'A public request rejected malformed layer settings: ' . $e->getMessage() );
			return new \WP_Error( 'invalid_layer_settings', __( 'This customisation layer is not configured correctly.', 'overcustomise' ), [ 'status' => 503 ] );
		}
		if ( ! is_array( $settings ) ) {
			return new \WP_Error( 'invalid_layer_settings', __( 'This customisation layer is not configured correctly.', 'overcustomise' ), [ 'status' => 503 ] );
		}

		return $settings;
	}

	/** Strictly parse a positive integer setting. */
	private static function setting_positive_int( mixed $value, int $maximum ): ?int {
		if ( ! is_int( $value ) && ! ( is_string( $value ) && preg_match( '/^[0-9]+$/D', $value ) ) ) {
			return null;
		}
		$value = (int) $value;
		return $value > 0 && $value <= $maximum ? $value : null;
	}

	/** Strictly parse a stored boolean setting. */
	private static function setting_boolean( mixed $value ): ?bool {
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( in_array( $value, [ 0, '0' ], true ) ) {
			return false;
		}
		if ( in_array( $value, [ 1, '1' ], true ) ) {
			return true;
		}
		return null;
	}

	/** Validate a non-empty upload extension allowlist. */
	private static function upload_format_allowlist( mixed $formats ): ?array {
		if ( ! is_array( $formats ) || empty( $formats ) ) {
			return null;
		}

		$allowed = [ 'svg', 'pdf', 'eps', 'png', 'jpg', 'jpeg', 'webp', 'heic', 'heif' ];
		$output  = [];
		foreach ( $formats as $format ) {
			if ( ! is_string( $format ) ) {
				return null;
			}
			$format = ltrim( strtolower( trim( $format ) ), '.' );
			if ( ! in_array( $format, $allowed, true ) ) {
				return null;
			}
			$output[] = $format;
		}

		return array_values( array_unique( $output ) ) ?: null;
	}

	/** Build fail-closed upload overrides from global and layer settings. */
	private static function upload_layer_overrides( object $layer ): array|\WP_Error {
		$settings = self::decode_layer_settings( $layer->settings ?? null );
		if ( is_wp_error( $settings ) ) {
			return $settings;
		}

		$global_formats = self::upload_format_allowlist( OC_Admin_Settings::get( 'allowed_upload_formats' ) );
		$global_max     = self::setting_positive_int( OC_Admin_Settings::get( 'max_upload_size_mb' ), 100 );
		if ( null === $global_formats || null === $global_max ) {
			OC_Logger::error( 'Global artwork upload settings are empty or malformed.' );
			return new \WP_Error( 'invalid_upload_settings', __( 'Artwork uploads are temporarily unavailable.', 'overcustomise' ), [ 'status' => 503 ] );
		}

		$formats = $global_formats;
		if ( array_key_exists( 'formats', $settings ) ) {
			$layer_formats = self::upload_format_allowlist( $settings['formats'] );
			if ( null === $layer_formats ) {
				return new \WP_Error( 'invalid_upload_settings', __( 'Artwork uploads are not enabled for this layer.', 'overcustomise' ), [ 'status' => 400 ] );
			}
			$formats = array_values( array_intersect( $global_formats, $layer_formats ) );
			if ( empty( $formats ) ) {
				return new \WP_Error( 'invalid_upload_settings', __( 'Artwork uploads are not enabled for this layer.', 'overcustomise' ), [ 'status' => 400 ] );
			}
		}

		$max_size = $global_max;
		if ( array_key_exists( 'max_size_mb', $settings ) ) {
			$layer_max = self::setting_positive_int( $settings['max_size_mb'], 100 );
			if ( null === $layer_max ) {
				return new \WP_Error( 'invalid_upload_settings', __( 'This customisation layer is not configured correctly.', 'overcustomise' ), [ 'status' => 503 ] );
			}
			$max_size = min( $global_max, $layer_max );
		}

		$allow_change = array_key_exists( 'allow_image_change', $settings ) ? self::setting_boolean( $settings['allow_image_change'] ) : true;
		$remove_bg    = array_key_exists( 'remove_background', $settings ) ? self::setting_boolean( $settings['remove_background'] ) : false;
		if ( null === $allow_change || null === $remove_bg ) {
			return new \WP_Error( 'invalid_upload_settings', __( 'This customisation layer is not configured correctly.', 'overcustomise' ), [ 'status' => 503 ] );
		}
		if ( ! $allow_change ) {
			return new \WP_Error( 'image_change_locked', __( 'The image is fixed for this design.', 'overcustomise' ), [ 'status' => 400 ] );
		}

		return [
			'formats'           => $formats,
			'max_size_mb'       => $max_size,
			'remove_background' => $remove_bg,
		];
	}

	// -------------------------------------------------------------------------
	// Handlers
	// -------------------------------------------------------------------------

	/**
	 * Return the design ID (and active state) for a product / variation.
	 * Used by the frontend JS to detect design changes on variation switch.
	 */
	public function get_product_design( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$product_id   = absint( $request->get_param( 'product_id' ) );
		$variant_id   = (int) $request->get_param( 'variant_id' );
		$design_id    = absint( $request->get_param( 'design_id' ) );
		$read_context = self::public_read_assignment_context( $product_id, $variant_id );
		if ( is_wp_error( $read_context ) ) {
			return $read_context;
		}
		$assignment = $read_context['assignment'];

		if ( $design_id ) {
			$design = OC_DB::get_design( $design_id );
			if ( ! $assignment || ! OC_DB::assignment_allows_design( $assignment, $design_id ) || ! $design || ! (bool) $design->active ) {
				return new \WP_Error( 'invalid_design', __( 'This customisation design is not available.', 'overcustomise' ), [ 'status' => 404 ] );
			}
		}

		try {
			$state = OC_Frontend::build_assignment_state( (int) $product_id, $variant_id, $design_id, 0 === $variant_id );
		} catch ( \Throwable $e ) {
			OC_Logger::error( 'Public product design state could not be built: ' . $e->getMessage() );
			return new \WP_Error( 'design_unavailable', __( 'This customisation is temporarily unavailable.', 'overcustomise' ), [ 'status' => 503 ] );
		}
		if ( is_wp_error( $state ) ) {
			return $state;
		}
		if ( empty( $state['active'] ) ) {
			return rest_ensure_response(
				[
					'design_id' => 0,
					'active'    => false,
				]
			);
		}

		$selected_design_id = absint( $state['designId'] ?? $state['design_id'] ?? 0 );
		$selected_design    = $selected_design_id ? OC_DB::get_design( $selected_design_id ) : null;
		if ( ! $assignment || ! OC_DB::assignment_allows_design( $assignment, $selected_design_id )
			|| ! $selected_design || ! (bool) $selected_design->active
		) {
			return new \WP_Error( 'invalid_design', __( 'This customisation design is not available.', 'overcustomise' ), [ 'status' => 404 ] );
		}

		// Retain only options whose complete frontend state can be requested publicly.
		$public_variants  = [];
		$public_state_ids = [];
		foreach ( is_array( $state['designVariants'] ?? null ) ? $state['designVariants'] : [] as $variant ) {
			$variant_design_id = absint( $variant['designId'] ?? 0 );
			$variant_state_id  = is_string( $variant['id'] ?? null ) ? $variant['id'] : '';
			$variant_design    = $variant_design_id ? OC_DB::get_design( $variant_design_id ) : null;
			if ( '' === $variant_state_id || ! OC_DB::assignment_allows_design( $assignment, $variant_design_id )
				|| ! $variant_design || ! (bool) $variant_design->active
			) {
				continue;
			}
			$public_variants[]                     = $variant;
			$public_state_ids[ $variant_state_id ] = true;
		}
		$state['designVariants'] = $public_variants;
		if ( isset( $state['designVariantStates'] ) ) {
			$state['designVariantStates'] = array_intersect_key(
				is_array( $state['designVariantStates'] ) ? $state['designVariantStates'] : [],
				$public_state_ids
			);
		}

		return rest_ensure_response( $state );
	}

	/** Return the active product config, print areas, and font list for the customiser. */
	public function get_product_config( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$product_id = absint( $request->get_param( 'product_id' ) );
		$product    = self::public_product_context( $product_id );
		if ( is_wp_error( $product ) ) {
			return $product;
		}

		$config = OC_DB::get_config_by_product( $product_id );

		if ( ! $config || ! $config->active ) {
			return new \WP_Error( 'not_found', __( 'No active customisation config for this product.', 'overcustomise' ), [ 'status' => 404 ] );
		}

		$areas           = OC_DB::get_print_areas( (int) $config->id );
		$allowed_formats = self::upload_format_allowlist( OC_Admin_Settings::get( 'allowed_upload_formats' ) );
		$max_upload_mb   = self::setting_positive_int( OC_Admin_Settings::get( 'max_upload_size_mb' ), 100 );
		if ( empty( $areas ) || null === $allowed_formats || null === $max_upload_mb ) {
			OC_Logger::warning( 'A public product config was rejected because its areas or upload settings are invalid.' );
			return new \WP_Error( 'invalid_config', __( 'This customisation is temporarily unavailable.', 'overcustomise' ), [ 'status' => 503 ] );
		}

		$areas_out = array_map(
			function ( $area ) {
				$mockup_id     = absint( $area->mockup_attachment_id ?? 0 );
				$mockup_status = $mockup_id ? get_post_status( $mockup_id ) : false;
				$mockup_url    = $mockup_id && in_array( $mockup_status, [ 'inherit', 'publish' ], true )
				? wp_get_attachment_image_url( $mockup_id, 'full' )
				: '';

				return [
					'id'                 => (int) $area->id,
					'area_key'           => $area->area_key,
					'label'              => $area->label,
					'print_method'       => $area->print_method,
					'engraving_material' => isset( $area->engraving_material ) ? (string) $area->engraving_material : 'silver_metal',
					'mockup_url'         => $mockup_url,
					'canvas'             => [
						'x'        => (int) $area->canvas_x,
						'y'        => (int) $area->canvas_y,
						'w'        => (int) $area->canvas_w,
						'h'        => (int) $area->canvas_h,
						'rotation' => isset( $area->canvas_rotation ) ? (int) $area->canvas_rotation : 0,
					],
				];
			},
			$areas
		);

		return rest_ensure_response(
			[
				'config_id'          => (int) $config->id,
				'product_id'         => (int) $config->product_id,
				'custom_type'        => $config->custom_type,
				'flat_rate'          => (float) $config->flat_rate,
				'print_areas'        => array_values( $areas_out ),
				'fonts'              => OC_Font_Registry::get_fonts_for_js(),
				'allowed_formats'    => $allowed_formats,
				'max_upload_size_mb' => $max_upload_mb,
			]
		);
	}

	/** Return all active fonts. */
	public function get_fonts( \WP_REST_Request $request ): \WP_REST_Response {
		return rest_ensure_response( OC_Font_Registry::get_fonts_for_js() );
	}

	/** Regenerate a single print file by its DB record ID. */
	public function regenerate_files( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		// CSRF protection on top of the capability check in permission_callback.
		$nonce = $request->get_header( 'X-WP-Nonce' ) ?: $request->get_header( 'X-OC-Nonce' );
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new \WP_Error( 'invalid_nonce', __( 'Security check failed.', 'overcustomise' ), [ 'status' => 403 ] );
		}

		$file_id = absint( $request->get_param( 'file_id' ) );
		if ( ! $file_id ) {
			return new \WP_Error( 'invalid_param', __( 'file_id required.', 'overcustomise' ), [ 'status' => 400 ] );
		}

		if ( ! OC_DB::get_print_file( $file_id ) ) {
			return new \WP_Error( 'not_found', __( 'Print file record not found.', 'overcustomise' ), [ 'status' => 404 ] );
		}

		try {
			$result = ( new OC_Print_Generator() )->regenerate( $file_id );
		} catch ( \Throwable $e ) {
			OC_Logger::error( 'regenerate_files REST: ' . $e->getMessage() );
			return new \WP_Error( 'generation_failed', $e->getMessage(), [ 'status' => 500 ] );
		}

		return rest_ensure_response(
			[
				'file_id'   => $file_id,
				'file_path' => basename( (string) ( $result['file_path'] ?? '' ) ),
				'status'    => $result['status'] ?? '',
				'warning'   => (string) ( $result['warning'] ?? '' ),
			]
		);
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
}
