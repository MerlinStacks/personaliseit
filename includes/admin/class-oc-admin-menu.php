<?php
/**
 * Registers all OverCustomise menu pages as a top-level admin menu.
 *
 * @package OverCustomise
 */

defined( 'ABSPATH' ) || exit;

class OC_Admin_Menu {

	public function register(): void {
		add_action( 'admin_menu', [ $this, 'add_menus' ], 99 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	public function add_menus(): void {
		// Top-level menu entry — clicking it redirects to Products.
		add_menu_page(
			__( 'OverCustomise', 'overcustomise' ),
			__( 'OverCustomise', 'overcustomise' ),
			'manage_woocommerce',
			'overcustomise',
			static function () {
				wp_safe_redirect( admin_url( 'admin.php?page=overcustomise-products' ) );
				exit;
			},
			'dashicons-art',
			58
		);

		// Products — assign customisation configs to products.
		add_submenu_page(
			'overcustomise',
			__( 'OC — Products', 'overcustomise' ),
			__( 'Products', 'overcustomise' ),
			'manage_woocommerce',
			'overcustomise-products',
			[ new OC_Admin_Products(), 'render' ]
		);

		// Font Manager.
		add_submenu_page(
			'overcustomise',
			__( 'OC — Font Manager', 'overcustomise' ),
			__( 'Font Manager', 'overcustomise' ),
			'manage_woocommerce',
			'overcustomise-fonts',
			[ new OC_Admin_Fonts(), 'render' ]
		);

		// Colour Manager.
		add_submenu_page(
			'overcustomise',
			__( 'OC — Colour Manager', 'overcustomise' ),
			__( 'Colour Manager', 'overcustomise' ),
			'manage_woocommerce',
			'overcustomise-colours',
			[ new OC_Admin_Colours(), 'render' ]
		);

		// Clipart Manager.
		add_submenu_page(
			'overcustomise',
			__( 'OC — Clipart Manager', 'overcustomise' ),
			__( 'Clipart Manager', 'overcustomise' ),
			'manage_woocommerce',
			'overcustomise-clipart',
			[ new OC_Admin_Clipart(), 'render' ]
		);

		// Print Methods.
		add_submenu_page(
			'overcustomise',
			__( 'OC — Print Methods', 'overcustomise' ),
			__( 'Print Methods', 'overcustomise' ),
			'manage_woocommerce',
			'overcustomise-print-methods',
			[ new OC_Admin_Print_Methods(), 'render' ]
		);

		// Settings.
		add_submenu_page(
			'overcustomise',
			__( 'OC — Settings', 'overcustomise' ),
			__( 'Settings', 'overcustomise' ),
			'manage_woocommerce',
			'overcustomise-settings',
			[ new OC_Admin_Settings(), 'render' ]
		);
	}

	/** Enqueue shared admin assets only on OC pages. */
	public function enqueue_assets( string $hook ): void {
		$oc_pages = [
			'overcustomise_page_overcustomise-products',
			'overcustomise_page_overcustomise-fonts',
			'overcustomise_page_overcustomise-colours',
			'overcustomise_page_overcustomise-clipart',
			'overcustomise_page_overcustomise-print-methods',
			'overcustomise_page_overcustomise-settings',
		];

		if ( ! in_array( $hook, $oc_pages, true ) ) {
			return;
		}

		// Shared admin styles (static file, not webpack-built).
		wp_enqueue_style(
			'oc-admin',
			OC_URL . 'assets/css/admin.css',
			[],
			OC_VERSION
		);

		// WordPress media uploader (used on mockups and fonts pages).
		wp_enqueue_media();

		// Products page — bounds drag editor.
		// Note: ocProductsData is localised by OC_Admin_Products::render_edit().
		if ( 'overcustomise_page_overcustomise-products' === $hook ) {
			wp_register_script(
				'oc-products-page',
				OC_ASSETS_URL . 'admin/products-page.js',
				[ 'jquery', 'wp-util', 'wc-enhanced-select' ],
				OC_VERSION,
				true
			);
		}

		// Font Manager — live preview script.
		if ( 'overcustomise_page_overcustomise-fonts' === $hook ) {
			wp_enqueue_script(
				'oc-font-manager',
				OC_ASSETS_URL . 'admin/font-manager.js',
				[],
				OC_VERSION,
				true
			);
		}

		// Colour Manager.
		if ( 'overcustomise_page_overcustomise-colours' === $hook ) {
			wp_enqueue_script(
				'oc-colour-manager',
				OC_ASSETS_URL . 'admin/colour-manager.js',
				[],
				OC_VERSION,
				true
			);
		}

		// Clipart Manager.
		if ( 'overcustomise_page_overcustomise-clipart' === $hook ) {
			wp_enqueue_script(
				'oc-clipart-manager',
				OC_ASSETS_URL . 'admin/clipart-manager.js',
				[],
				OC_VERSION,
				true
			);
		}
	}
}
