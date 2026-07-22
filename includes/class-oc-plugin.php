<?php
/**
 * Plugin bootstrap — loads all includes and wires up hooks.
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

class OC_Plugin {

	/** @var OC_Plugin|null Singleton instance. */
	private static ?OC_Plugin $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->init();
		}
		return self::$instance;
	}

	private function init(): void {
		$this->load_includes();
		$this->register_hooks();
	}

	private function load_includes(): void {
		// Core utilities.
		require_once OC_PATH . 'includes/class-oc-tooltips.php';
		require_once OC_PATH . 'includes/class-oc-cache.php';
		require_once OC_PATH . 'includes/class-oc-woff-converter.php';
		require_once OC_PATH . 'includes/class-oc-db.php';
		require_once OC_PATH . 'includes/class-oc-logger.php';
		require_once OC_PATH . 'includes/class-oc-command-runner.php';
		require_once OC_PATH . 'includes/class-oc-system-status.php';
		require_once OC_PATH . 'includes/class-oc-font-registry.php';
		require_once OC_PATH . 'includes/class-oc-render-math.php';
		require_once OC_PATH . 'includes/class-oc-render-spec.php';
		require_once OC_PATH . 'includes/class-oc-rest-api.php';
		require_once OC_PATH . 'includes/class-oc-blocks-integration.php';
		require_once OC_PATH . 'includes/class-oc-file-cleanup.php';
		require_once OC_PATH . 'includes/class-oc-svg-sanitiser.php';
		require_once OC_PATH . 'includes/class-oc-upload-handler.php';
		require_once OC_PATH . 'includes/class-oc-ai-image-filter.php';
		require_once OC_PATH . 'includes/class-oc-preview-generator.php';
		require_once OC_PATH . 'includes/class-oc-print-generator.php';
		require_once OC_PATH . 'includes/class-oc-print-queue.php';
		require_once OC_PATH . 'includes/class-oc-autosave.php';
		require_once OC_PATH . 'includes/class-oc-vdp.php';
		require_once OC_PATH . 'includes/class-oc-webhooks.php';
		require_once OC_PATH . 'includes/print/class-oc-print-base.php';
		require_once OC_PATH . 'includes/print/class-oc-print-engraving.php';
		require_once OC_PATH . 'includes/print/class-oc-print-uv.php';
		require_once OC_PATH . 'includes/print/class-oc-print-sublimation.php';
		require_once OC_PATH . 'includes/print/class-oc-print-embroidery.php';

		// Settings must be available outside admin (REST API + frontend use it).
		require_once OC_PATH . 'includes/admin/class-oc-admin-settings.php';
		require_once OC_PATH . 'includes/admin/class-oc-admin-print-methods.php';

		// Mockups taxonomy must register on all requests (frontend queries need it).
		require_once OC_PATH . 'includes/admin/class-oc-admin-mockups.php';

		// Frontend — loaded on all requests (cart hooks fire in both admin AJAX and frontend).
		require_once OC_PATH . 'includes/frontend/class-oc-frontend.php';
		require_once OC_PATH . 'includes/frontend/class-oc-cart.php';

		// Admin.
		if ( is_admin() ) {
			require_once OC_PATH . 'includes/admin/class-oc-admin-menu.php';
			require_once OC_PATH . 'includes/admin/class-oc-admin-products.php';
			require_once OC_PATH . 'includes/admin/class-oc-admin-fonts.php';
			require_once OC_PATH . 'includes/admin/class-oc-admin-colours.php';
			require_once OC_PATH . 'includes/admin/class-oc-admin-image-filters.php';
			require_once OC_PATH . 'includes/admin/class-oc-admin-clipart.php';
			require_once OC_PATH . 'includes/admin/class-oc-admin-customer-uploads.php';
			require_once OC_PATH . 'includes/admin/class-oc-admin-print-queue.php';
			require_once OC_PATH . 'includes/admin/class-oc-admin-order-metabox.php';
		}
	}

	private function register_hooks(): void {
		// Font registry (MIME types + @font-face CSS — both admin and frontend).
		$font_registry = new OC_Font_Registry();
		$font_registry->register();
		remove_action( 'wp_head', [ $font_registry, 'output_font_face_css' ], 5 );
		remove_action( 'admin_head', [ $font_registry, 'output_font_face_css' ], 5 );
		add_action( 'wp_head', [ self::class, 'output_font_face_css' ], 5 );
		add_action( 'admin_head', [ self::class, 'output_font_face_css' ], 5 );

		// REST API.
		( new OC_Rest_API() )->register();
		OC_Upload_Handler::register();

		// Mockup taxonomy + AJAX handlers.
		OC_Admin_Mockups::init();

		// Frontend hooks (non-admin context, plus cart/order hooks for AJAX/checkout).
		( new OC_Frontend() )->register();
		( new OC_Cart() )->register();
		( new OC_Blocks_Integration() )->register();

		// Print file generation (fires on checkout order creation).
		$print_generator = new OC_Print_Generator();
		$print_generator->register();
		if ( is_admin() ) {
			// Mutating admin actions are POST-only; downloads remain nonce-protected GETs.
			remove_action( 'admin_init', [ $print_generator, 'handle_admin_regenerate' ] );
			remove_action( 'admin_init', [ $print_generator, 'handle_admin_generate_missing' ] );
			remove_action( 'admin_init', [ $print_generator, 'handle_admin_process_queue' ] );
			add_action( 'admin_post_oc_regenerate_print_file', [ self::class, 'handle_regenerate_print_file' ] );
			add_action( 'admin_post_oc_generate_print_files', [ self::class, 'handle_generate_print_files' ] );
			add_action( 'admin_post_oc_process_print_queue_order', [ self::class, 'handle_process_print_queue_order' ] );
			add_action( 'admin_post_oc_manage_print_queue', [ new OC_Admin_Print_Queue(), 'handle_action' ] );
		}

		// Print queue processor.
		OC_Print_Queue::instance()->register();

		// Webhooks.
		( new OC_Webhooks() )->register();

		if ( is_admin() ) {
			( new OC_Admin_Menu() )->register();
			( new OC_Admin_Order_Metabox() )->register();
			OC_Admin_Customer_Uploads::register_hooks();
			OC_Admin_Products::register_ajax();
			OC_Admin_Fonts::register_ajax();
			OC_Admin_Colours::register_ajax();
			OC_Admin_Image_Filters::register_ajax();
			OC_Admin_Clipart::register_ajax();
		}

		// Register custom cron recurrence.
		add_filter( 'cron_schedules', [ self::class, 'add_cron_schedules' ] );

		// DB upgrades.
		add_action( 'init', [ OC_DB::class, 'maybe_upgrade' ] );
		add_action( 'init', [ self::class, 'ensure_cron_events' ] );

		// File cleanup cron callback.
		add_action( 'oc_daily_file_cleanup', [ 'OC_File_Cleanup', 'run' ] );
	}

	// -------------------------------------------------------------------------
	// Cron schedules
	// -------------------------------------------------------------------------

	public static function add_cron_schedules( array $schedules ): array {
		$schedules['oc_every_minute'] = [
			'interval' => 60,
			'display'  => __( 'Every Minute', 'overcustomise' ),
		];
		return $schedules;
	}

	/** Return the generated family name used in CSS and browser payloads. */
	public static function font_family( int $font_id ): string {
		return 'oc-font-' . max( 0, $font_id );
	}

	/** Restrict font weight metadata to values accepted by CSS FontFace. */
	public static function font_weight( mixed $weight ): string {
		$weight = is_scalar( $weight ) ? strtolower( trim( (string) $weight ) ) : 'normal';
		$allowed = [ 'normal', 'bold', '100', '200', '300', '400', '500', '600', '700', '800', '900' ];
		return in_array( $weight, $allowed, true ) ? $weight : 'normal';
	}

	/** Restrict font style metadata to values accepted by CSS FontFace. */
	public static function font_style( mixed $style ): string {
		$style = is_scalar( $style ) ? strtolower( trim( (string) $style ) ) : 'normal';
		return in_array( $style, [ 'normal', 'italic', 'oblique' ], true ) ? $style : 'normal';
	}

	/** Return an administrator label only when it is also a safe quoted CSS family. */
	public static function safe_font_label( mixed $label ): string {
		$label = is_scalar( $label ) ? trim( sanitize_text_field( (string) $label ) ) : '';
		return preg_match( '/\A[A-Za-z0-9][A-Za-z0-9 _-]{0,99}\z/D', $label ) ? $label : '';
	}

	/** Return active font data with allowlisted metadata and stable CSS family identifiers. */
	public static function browser_fonts(): array {
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return [];
		}
		$uploads_dir = realpath( (string) $uploads['basedir'] );
		$base_dir    = realpath( trailingslashit( (string) $uploads['basedir'] ) . 'overcustomise/fonts' );
		$uploads_prefix = $uploads_dir ? rtrim( wp_normalize_path( $uploads_dir ), '/' ) . '/' : '';
		if ( ! $base_dir || '' === $uploads_prefix || ! str_starts_with( wp_normalize_path( $base_dir ), $uploads_prefix ) ) {
			return [];
		}
		$base_prefix = rtrim( wp_normalize_path( $base_dir ), '/' ) . '/';
		$result      = [];

		foreach ( OC_DB::get_fonts( true ) as $font ) {
			$font_id  = absint( $font->id ?? 0 );
			$relative = ltrim( wp_normalize_path( is_scalar( $font->file_path ?? null ) ? (string) $font->file_path : '' ), '/' );
			$real     = '' !== $relative ? realpath( trailingslashit( (string) $uploads['basedir'] ) . $relative ) : false;
			$real     = $real ? wp_normalize_path( $real ) : '';
			$extension = strtolower( pathinfo( $relative, PATHINFO_EXTENSION ) );
			if ( ! $font_id || ! str_starts_with( $relative, 'overcustomise/fonts/' ) || '' === $real || ! str_starts_with( $real, $base_prefix ) || ! is_file( $real ) || ! in_array( $extension, [ 'ttf', 'otf', 'woff', 'woff2' ], true ) ) {
				continue;
			}

			$label  = self::safe_font_label( $font->name ?? '' );
			$family = '' !== $label ? $label : self::font_family( $font_id );
			$result[] = [
				'id'         => $font_id,
				'name'       => $family,
				'label'      => '' !== $label ? $label : sprintf( __( 'Font %d', 'overcustomise' ), $font_id ),
				'url'        => esc_url_raw( trailingslashit( (string) $uploads['baseurl'] ) . $relative ),
				'weight'     => self::font_weight( $font->weight ?? 'normal' ),
				'style'      => self::font_style( $font->style ?? 'normal' ),
				'embroidery' => ! empty( $font->embroidery_suitable ),
			];
		}

		return $result;
	}

	/** Output font declarations without using administrator labels as CSS identifiers. */
	public static function output_font_face_css(): void {
		if ( 'wp_head' === current_filter() && ! is_product() ) {
			return;
		}

		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return;
		}

		$uploads_dir = realpath( (string) $uploads['basedir'] );
		$base_dir    = realpath( trailingslashit( (string) $uploads['basedir'] ) . 'overcustomise/fonts' );
		$uploads_prefix = $uploads_dir ? rtrim( wp_normalize_path( $uploads_dir ), '/' ) . '/' : '';
		if ( ! $base_dir || '' === $uploads_prefix || ! str_starts_with( wp_normalize_path( $base_dir ), $uploads_prefix ) ) {
			return;
		}
		$base_prefix = rtrim( wp_normalize_path( $base_dir ), '/' ) . '/';
		$rules       = [];

		foreach ( OC_DB::get_fonts( true ) as $font ) {
			$font_id = absint( $font->id ?? 0 );
			$relative = ltrim( wp_normalize_path( is_scalar( $font->file_path ?? null ) ? (string) $font->file_path : '' ), '/' );
			$real     = '' !== $relative ? realpath( trailingslashit( (string) $uploads['basedir'] ) . $relative ) : false;
			$real     = $real ? wp_normalize_path( $real ) : '';
			if ( ! $font_id || ! str_starts_with( $relative, 'overcustomise/fonts/' ) || '' === $real || ! str_starts_with( $real, $base_prefix ) || ! is_file( $real ) ) {
				continue;
			}

			$extension = strtolower( pathinfo( $relative, PATHINFO_EXTENSION ) );
			$format    = match ( $extension ) {
				'woff2' => 'woff2',
				'woff'  => 'woff',
				'otf'   => 'opentype',
				'ttf'   => 'truetype',
				default => '',
			};
			if ( '' === $format ) {
				continue;
			}

			$url      = esc_url( trailingslashit( (string) $uploads['baseurl'] ) . $relative );
			$family   = self::font_family( $font_id );
			$weight   = self::font_weight( $font->weight ?? 'normal' );
			$style    = self::font_style( $font->style ?? 'normal' );
			$families = [ $family ];
			$label    = self::safe_font_label( $font->name ?? '' );
			// Keep existing admin previews working only when the label is already a safe CSS family token.
			if ( '' !== $label ) {
				$families[] = $label;
			}

			foreach ( array_unique( $families ) as $css_family ) {
				$rules[] = sprintf(
					"@font-face{font-family:'%s';src:url('%s') format('%s');font-weight:%s;font-style:%s;font-display:swap;}",
					$css_family,
					$url,
					$format,
					$weight,
					$style
				);
			}
		}

		if ( $rules ) {
			echo '<style id="oc-font-faces">' . implode( "\n", $rules ) . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every CSS token is allowlisted above.
		}
	}

	/** Regenerate one print file after an explicit nonce-protected admin POST. */
	public static function handle_regenerate_print_file(): void {
		self::require_print_admin_post();
		$file_id = absint( $_GET['file_id'] ?? $_POST['file_id'] ?? 0 );
		check_admin_referer( 'oc_regenerate_' . $file_id, 'oc_print_nonce' );
		$record = $file_id ? OC_DB::get_print_file( $file_id ) : null;
		if ( ! $record ) {
			wp_die( esc_html__( 'Print file record not found.', 'overcustomise' ), '', [ 'response' => 404 ] );
		}

		$order = wc_get_order( (int) $record->order_id );
		$redirect = $order instanceof WC_Order ? $order->get_edit_order_url() : admin_url( 'admin.php?page=wc-orders' );
		try {
			$result = ( new OC_Print_Generator() )->regenerate( $file_id );
			if ( ! empty( $result['warning'] ) && $order instanceof WC_Order ) {
				$order->add_order_note( (string) $result['warning'] );
				$redirect = add_query_arg( 'oc_regenerate_snapshot_warning', '1', $redirect );
			}
		} catch ( \Throwable $error ) {
			OC_Logger::error( 'Admin regenerate failed for file #' . $file_id . ': ' . $error->getMessage() );
		}

		wp_safe_redirect( $redirect );
		exit;
	}

	/** Create missing print rows/jobs after an explicit nonce-protected admin POST. */
	public static function handle_generate_print_files(): void {
		self::require_print_admin_post();
		$order_id = absint( $_GET['order_id'] ?? $_POST['order_id'] ?? 0 );
		check_admin_referer( 'oc_generate_print_files_' . $order_id, 'oc_print_nonce' );
		$order = $order_id ? wc_get_order( $order_id ) : null;
		if ( ! $order instanceof WC_Order ) {
			wp_die( esc_html__( 'Order not found.', 'overcustomise' ), '', [ 'response' => 404 ] );
		}

		try {
			( new OC_Print_Generator() )->generate_for_order( $order );
		} catch ( \Throwable $error ) {
			OC_Logger::error( 'Admin print-file generation failed for order #' . $order_id . ': ' . $error->getMessage() );
			$order->add_order_note( sprintf( __( 'OverCustomise print-file generation failed: %s', 'overcustomise' ), $error->getMessage() ) );
		}

		wp_safe_redirect( $order->get_edit_order_url() );
		exit;
	}

	/** Process one order's queued work after an explicit nonce-protected admin POST. */
	public static function handle_process_print_queue_order(): void {
		self::require_print_admin_post();
		$order_id = absint( $_GET['order_id'] ?? $_POST['order_id'] ?? 0 );
		check_admin_referer( 'oc_process_print_queue_order_' . $order_id, 'oc_print_nonce' );
		$order = $order_id ? wc_get_order( $order_id ) : null;
		if ( ! $order instanceof WC_Order ) {
			wp_die( esc_html__( 'Order not found.', 'overcustomise' ), '', [ 'response' => 404 ] );
		}

		global $wpdb;
		$jobs = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, status FROM {$wpdb->prefix}oc_print_queue WHERE order_id = %d AND status IN ('pending', 'failed') ORDER BY created_at ASC",
			$order_id
		) ) ?: [];
		$processed = 0;
		foreach ( $jobs as $job ) {
			if ( 'failed' === (string) $job->status && ! OC_Print_Queue::instance()->retry_job( (int) $job->id ) ) {
				continue;
			}
			OC_Print_Queue::instance()->process_one( (int) $job->id );
			$processed++;
		}
		$order->add_order_note( sprintf( __( 'OverCustomise manually processed %d print queue job(s).', 'overcustomise' ), $processed ) );
		wp_safe_redirect( $order->get_edit_order_url() );
		exit;
	}

	/** Require a WooCommerce manager and POST semantics for print mutations. */
	private static function require_print_admin_post(): void {
		if ( 'POST' !== strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
			status_header( 405 );
			wp_die( esc_html__( 'This action requires a POST request.', 'overcustomise' ), '', [ 'response' => 405 ] );
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'overcustomise' ), '', [ 'response' => 403 ] );
		}
	}

	/** Ensure queue/file cleanup events exist even if activation scheduling failed. */
	public static function ensure_cron_events(): void {
		if ( ! wp_next_scheduled( 'oc_daily_file_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'oc_daily_file_cleanup' );
		}

		if ( ! wp_next_scheduled( 'oc_process_print_queue' ) ) {
			wp_schedule_event( time(), 'oc_every_minute', 'oc_process_print_queue' );
		}
	}

	// -------------------------------------------------------------------------
	// Activation / deactivation
	// -------------------------------------------------------------------------

	public static function activate(): void {
		require_once OC_PATH . 'includes/class-oc-db.php';
		require_once OC_PATH . 'includes/admin/class-oc-admin-mockups.php';
		add_filter( 'cron_schedules', [ self::class, 'add_cron_schedules' ] );
		OC_DB::create_tables();
		OC_DB::maybe_upgrade();

		// Register mockup taxonomy before flushing rules.
		OC_Admin_Mockups::register_taxonomy();
		flush_rewrite_rules();

		if ( ! wp_next_scheduled( 'oc_daily_file_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'oc_daily_file_cleanup' );
		}

		if ( ! wp_next_scheduled( 'oc_process_print_queue' ) ) {
			wp_schedule_event( time(), 'oc_every_minute', 'oc_process_print_queue' );
		}

	}

	public static function deactivate(): void {
		self::clear_scheduled_events();
		self::delete_runtime_options();
	}

	public static function uninstall(): void {
		require_once OC_PATH . 'includes/class-oc-cache.php';
		require_once OC_PATH . 'includes/class-oc-db.php';
		require_once OC_PATH . 'includes/class-oc-upload-handler.php';
		OC_DB::drop_all_tables();

		delete_option( 'oc_db_version' );
		delete_option( 'oc_settings' );
		delete_option( 'oc_print_methods' );
		delete_option( 'oc_private_artwork_storage_version' );
		delete_option( 'oc_artwork_cleanup_cursor' );
		delete_option( 'oc_preview_cleanup_private_cursor' );
		delete_option( 'oc_legacy_preview_cleanup_cursor' );
		delete_option( 'oc_budget_cleanup_cursor' );

		self::clear_scheduled_events();

		require_once OC_PATH . 'includes/admin/class-oc-admin-mockups.php';
		OC_Admin_Mockups::register_taxonomy();
		$mockup_terms = get_terms( [
			'taxonomy'   => 'oc_mockup',
			'hide_empty' => false,
			'fields'     => 'ids',
		] );
		if ( ! is_wp_error( $mockup_terms ) ) {
			foreach ( array_map( 'absint', $mockup_terms ) as $term_id ) {
				wp_delete_term( $term_id, 'oc_mockup' );
			}
		}
		delete_post_meta_by_key( '_oc_mockup_label' );
		unregister_taxonomy( 'oc_mockup' );
		remove_filter( 'pre_delete_attachment', [ OC_Upload_Handler::class, 'prevent_referenced_artwork_deletion' ], 10 );
		$artwork_ids = get_posts( [
			'post_type'      => 'attachment',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => [ [ 'key' => '_oc_artwork', 'value' => '1' ] ],
		] );
		foreach ( array_map( 'absint', $artwork_ids ) as $attachment_id ) {
			wp_delete_attachment( $attachment_id, true );
		}

		$upload_dir = wp_upload_dir();
		$upload_path = $upload_dir['basedir'] . '/overcustomise/';
		if ( is_dir( $upload_path ) ) {
			self::delete_directory( $upload_path );
		}

		$private_root = OC_Upload_Handler::private_storage_root();
		if ( is_string( $private_root ) ) {
			$private_real = realpath( $private_root );
			$uploads_real = realpath( (string) ( $upload_dir['basedir'] ?? '' ) );
			$is_fallback  = false !== $private_real && false !== $uploads_real
				&& wp_normalize_path( dirname( $private_real ) ) === wp_normalize_path( $uploads_real )
				&& (bool) preg_match( '/^\.overcustomise-private-[a-z0-9]{32}$/D', basename( $private_real ) );
			if ( $is_fallback ) {
				self::delete_directory( $private_real );
			} else {
				foreach ( [ 'artwork', 'previews', 'vdp' ] as $subdirectory ) {
					$path = $private_root . DIRECTORY_SEPARATOR . $subdirectory;
					if ( is_dir( $path ) || is_link( $path ) ) {
						self::delete_directory( $path );
					}
				}
				$remaining = is_dir( $private_root ) ? scandir( $private_root ) : false;
				if ( is_array( $remaining ) && empty( array_diff( $remaining, [ '.', '..' ] ) ) ) {
					rmdir( $private_root );
				}
			}
		}
		delete_option( 'oc_private_storage_token' );

		wp_cache_flush_group( 'oc_data' );
		self::delete_runtime_options( true );
	}

	/** Remove short-lived plugin options without retaining arbitrary payloads. */
	private static function delete_runtime_options( bool $uninstalling = false ): void {
		global $wpdb;
		$prefixes = [
			'oc_db_upgrade_',
			'oc_wh_delivery_',
			'oc_ai_filter_lock_',
			'oc_preview_lock_',
			'oc_token_issue_lock_',
			'oc_print_generated_emitted_',
			'oc_print_failure_emitted_',
			'_transient_oc_',
			'_transient_timeout_oc_',
		];
		if ( $uninstalling ) {
			$prefixes[] = 'oc_private_preview_';
			$prefixes[] = 'oc_budget_';
		}
		$clauses = implode( ' OR ', array_fill( 0, count( $prefixes ), 'option_name LIKE %s' ) );
		$patterns = array_map(
			static fn ( string $prefix ): string => $wpdb->esc_like( $prefix ) . '%',
			$prefixes
		);
		$option_names = $wpdb->get_col( $wpdb->prepare(
			"SELECT option_name FROM {$wpdb->options} WHERE {$clauses}",
			...$patterns
		) ) ?: [];

		$transients = [];
		foreach ( $option_names as $option_name ) {
			$option_name = (string) $option_name;
			if ( str_starts_with( $option_name, '_transient_timeout_' ) ) {
				$transients[] = substr( $option_name, strlen( '_transient_timeout_' ) );
			} elseif ( str_starts_with( $option_name, '_transient_' ) ) {
				$transients[] = substr( $option_name, strlen( '_transient_' ) );
			} else {
				delete_option( $option_name );
			}
		}
		foreach ( array_unique( $transients ) as $transient ) {
			delete_transient( $transient );
		}
		delete_option( 'oc_db_upgrade_lock' );
	}

	/** Remove every duplicate event, including single events with arguments. */
	private static function clear_scheduled_events(): void {
		$hooks = [
			'oc_daily_file_cleanup',
			'oc_process_print_queue',
			'oc_process_print_queue_now',
			'oc_webhook_retry',
			'oc_retry_order_print_generation',
			'oc_recover_order_print_generation',
		];

		foreach ( $hooks as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}

		$cron = function_exists( '_get_cron_array' ) ? _get_cron_array() : [];
		foreach ( is_array( $cron ) ? $cron : [] as $events ) {
			foreach ( $hooks as $hook ) {
				foreach ( is_array( $events[ $hook ] ?? null ) ? $events[ $hook ] : [] as $event ) {
					wp_clear_scheduled_hook( $hook, is_array( $event['args'] ?? null ) ? $event['args'] : [] );
				}
			}
		}

		if ( function_exists( 'wp_unschedule_hook' ) ) {
			foreach ( $hooks as $hook ) {
				wp_unschedule_hook( $hook );
			}
		}

		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			foreach ( $hooks as $hook ) {
				as_unschedule_all_actions( $hook, [], 'overcustomise' );
			}
		}
	}

	private static function delete_directory( string $dir ): void {
		if ( is_link( $dir ) ) {
			wp_delete_file( $dir );
			return;
		}
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$items = scandir( $dir );
		if ( false === $items ) {
			return;
		}
		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}
			$path = $dir . DIRECTORY_SEPARATOR . $item;
			if ( is_link( $path ) || ! is_dir( $path ) ) {
				wp_delete_file( $path );
			} else {
				self::delete_directory( $path );
			}
		}
		rmdir( $dir );
	}
}
