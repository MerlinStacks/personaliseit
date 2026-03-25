<?php
/**
 * REST API — /wp-json/overcustomise/v1/ endpoints.
 *
 * Endpoints:
 *   GET  /product-config/{product_id}   — fetch config + print areas + fonts for the customiser
 *   POST /upload-artwork                — customer artwork upload
 *   GET  /fonts                         — list active fonts (public)
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

class OC_Rest_API {

	private const NAMESPACE = 'overcustomise/v1';

	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		// Product config + print areas for the frontend customiser.
		register_rest_route( self::NAMESPACE, '/product-config/(?P<product_id>\d+)', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_product_config' ],
			'permission_callback' => '__return_true', // Public — only returns active configs.
			'args'                => [
				'product_id' => [
					'validate_callback' => fn( $v ) => is_numeric( $v ) && $v > 0,
					'sanitize_callback' => 'absint',
				],
			],
		] );

		// Active fonts list (public — needed by frontend customiser).
		register_rest_route( self::NAMESPACE, '/fonts', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_fonts' ],
			'permission_callback' => '__return_true',
		] );

		// Artwork upload (customer).
		register_rest_route( self::NAMESPACE, '/upload-artwork', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'upload_artwork' ],
			'permission_callback' => '__return_true',
		] );

		// Design assignment for a specific product / variation (used by frontend JS).
		register_rest_route( self::NAMESPACE, '/product-design/(?P<product_id>\d+)', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_product_design' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'product_id' => [ 'validate_callback' => fn( $v ) => is_numeric( $v ) && $v > 0, 'sanitize_callback' => 'absint' ],
				'variant_id' => [ 'default' => 0, 'sanitize_callback' => 'absint' ],
			],
		] );

		// Save canvas snapshot as a JPEG for cart/order preview.
		register_rest_route( self::NAMESPACE, '/save-preview', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'save_preview' ],
			'permission_callback' => '__return_true',
		] );

		// Admin: regenerate print files for an order item.
		register_rest_route( self::NAMESPACE, '/regenerate-files', [
			'methods'             => \WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'regenerate_files' ],
			'permission_callback' => fn() => current_user_can( 'manage_woocommerce' ),
		] );
	}

	// -------------------------------------------------------------------------
	// Handlers
	// -------------------------------------------------------------------------

	/**
	 * Return the design ID (and active state) for a product / variation.
	 * Used by the frontend JS to detect design changes on variation switch.
	 */
	public function get_product_design( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		global $wpdb;

		$product_id = $request->get_param( 'product_id' );
		$variant_id = (int) $request->get_param( 'variant_id' );

		// Replicate the same priority logic as OC_Frontend::get_assignment().
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return new \WP_Error( 'not_found', 'Product not found.', [ 'status' => 404 ] );
		}

		$parent_id = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product_id;
		$v_id      = $product->is_type( 'variation' ) ? $product_id : $variant_id;

		// 1. Variant-specific.
		$row = null;
		if ( $v_id > 0 ) {
			$row = $wpdb->get_row( $wpdb->prepare(
				"SELECT design_id FROM {$wpdb->prefix}oc_product_assignments
				 WHERE product_id = %d AND variant_id = %d LIMIT 1",
				$parent_id, $v_id
			) );
		}

		// 2. Parent-level.
		if ( ! $row ) {
			$row = $wpdb->get_row( $wpdb->prepare(
				"SELECT design_id FROM {$wpdb->prefix}oc_product_assignments
				 WHERE product_id = %d AND variant_id = 0 LIMIT 1",
				$parent_id
			) );
		}

		if ( ! $row ) {
			return rest_ensure_response( [ 'design_id' => 0, 'active' => false ] );
		}

		$design = OC_DB::get_design( (int) $row->design_id );

		return rest_ensure_response( [
			'design_id' => (int) $row->design_id,
			'active'    => $design ? (bool) $design->active : false,
		] );
	}

	/** Return the active product config, print areas, and font list for the customiser. */
	public function get_product_config( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$product_id = $request->get_param( 'product_id' );
		$config     = OC_DB::get_config_by_product( $product_id );

		if ( ! $config || ! $config->active ) {
			return new \WP_Error( 'not_found', __( 'No active customisation config for this product.', 'overcustomise' ), [ 'status' => 404 ] );
		}

		$areas = OC_DB::get_print_areas( (int) $config->id );

		$areas_out = array_map( function( $area ) {
			$mockup_url = $area->mockup_attachment_id
				? wp_get_attachment_image_url( (int) $area->mockup_attachment_id, 'full' )
				: '';

			return [
				'id'           => (int) $area->id,
				'area_key'     => $area->area_key,
				'label'        => $area->label,
				'print_method' => $area->print_method,
				'mockup_url'   => $mockup_url,
				'canvas'       => [
					'x' => (int) $area->canvas_x,
					'y' => (int) $area->canvas_y,
					'w' => (int) $area->canvas_w,
					'h' => (int) $area->canvas_h,
				],
			];
		}, $areas );

		return rest_ensure_response( [
			'config_id'          => (int) $config->id,
			'product_id'         => (int) $config->product_id,
			'custom_type'        => $config->custom_type,
			'flat_rate'          => (float) $config->flat_rate,
			'print_areas'        => array_values( $areas_out ),
			'fonts'              => OC_Font_Registry::get_fonts_for_js(),
			'allowed_formats'    => OC_Admin_Settings::get( 'allowed_upload_formats' ),
			'max_upload_size_mb' => (int) OC_Admin_Settings::get( 'max_upload_size_mb' ),
		] );
	}

	/** Return all active fonts. */
	public function get_fonts( \WP_REST_Request $request ): \WP_REST_Response {
		return rest_ensure_response( OC_Font_Registry::get_fonts_for_js() );
	}

	/** Handle customer artwork upload. */
	public function upload_artwork( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		// Verify nonce (sent as X-WP-Nonce header or _wpnonce param).
		$nonce = $request->get_header( 'X-OC-Nonce' )
			?: $request->get_param( '_wpnonce' );

		if ( ! $nonce || ! wp_verify_nonce( $nonce, OC_Upload_Handler::NONCE_ACTION ) ) {
			return new \WP_Error( 'invalid_nonce', __( 'Security verification failed.', 'overcustomise' ), [ 'status' => 403 ] );
		}

		$files = $request->get_file_params();

		if ( empty( $files['artwork'] ) ) {
			return new \WP_Error( 'no_file', __( 'No file received.', 'overcustomise' ), [ 'status' => 400 ] );
		}

		try {
			$result = OC_Upload_Handler::process( $files['artwork'] );
		} catch ( \RuntimeException $e ) {
			OC_Logger::warning( 'Artwork upload failed: ' . $e->getMessage() );
			return new \WP_Error( 'upload_failed', $e->getMessage(), [ 'status' => 422 ] );
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Save a base64 canvas snapshot as a JPEG for cart/order preview.
	 * Uses a content-hash filename so identical previews are deduplicated.
	 */
	public function save_preview( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$nonce = $request->get_header( 'X-OC-Nonce' ) ?: $request->get_param( '_wpnonce' );
		if ( ! $nonce || ! wp_verify_nonce( $nonce, OC_Upload_Handler::NONCE_ACTION ) ) {
			return new \WP_Error( 'invalid_nonce', __( 'Security check failed.', 'overcustomise' ), [ 'status' => 403 ] );
		}

		$body = $request->get_json_params();
		$raw  = $body['image'] ?? '';

		// Strip data URI prefix (data:image/jpeg;base64,…).
		if ( str_contains( $raw, ',' ) ) {
			$raw = substr( $raw, strpos( $raw, ',' ) + 1 );
		}

		$decoded = base64_decode( $raw, true );
		if ( ! $decoded || strlen( $decoded ) < 100 ) {
			return new \WP_Error( 'invalid_image', __( 'Invalid image data.', 'overcustomise' ), [ 'status' => 400 ] );
		}

		$upload   = wp_upload_dir();
		$dir      = $upload['basedir'] . '/overcustomise/previews';
		wp_mkdir_p( $dir );

		// Content-hash filename deduplicates identical previews.
		$filename = 'preview-' . md5( $decoded ) . '.jpg';
		$filepath = $dir . '/' . $filename;

		if ( ! file_exists( $filepath ) ) {
			if ( file_put_contents( $filepath, $decoded ) === false ) {
				return new \WP_Error( 'save_failed', __( 'Could not save preview image.', 'overcustomise' ), [ 'status' => 500 ] );
			}
		}

		return rest_ensure_response( [
			'url' => $upload['baseurl'] . '/overcustomise/previews/' . $filename,
		] );
	}

	/** Regenerate a single print file by its DB record ID. */
	public function regenerate_files( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$file_id = (int) $request->get_param( 'file_id' );
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

		return rest_ensure_response( [
			'file_id'   => $file_id,
			'file_path' => basename( $result['file_path'] ),
			'status'    => $result['status'],
		] );
	}
}
