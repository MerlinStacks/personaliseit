<?php
/**
 * Plugin Name: Personalise It!
 * Description: High-performance WooCommerce product personalization with live preview.
 * Version: 4.0.0
 * Author: CustomKings Personalised Gifts
 * License: GPLv2 or later
 * Text Domain: personaliseit
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.1
 */

namespace PersonaliseIt;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define Constants
define( 'PERSONALISET_VERSION', '4.0.0' );
define( 'PERSONALISET_PATH', plugin_dir_path( __FILE__ ) );
define( 'PERSONALISET_URL', plugin_dir_url( __FILE__ ) );

// Autoloader
if ( file_exists( PERSONALISET_PATH . 'vendor/autoload.php' ) ) {
	require_once PERSONALISET_PATH . 'vendor/autoload.php';
} else {
    // Simple PSR-4 Autoloader fallback if composer not run yet
    spl_autoload_register( function ( $class ) {
        $prefix = 'PersonaliseIt\\';
        $base_dir = PERSONALISET_PATH . 'includes/';
        $len = strlen( $prefix );
        if ( strncmp( $prefix, $class, $len ) !== 0 ) {
            return;
        }
        $relative_class = substr( $class, $len );
        $file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';
        if ( file_exists( $file ) ) {
            require $file;
        }
    } );
}

/**
 * Main Plugin Class
 */
class Plugin {

	/**
	 * Instance
	 *
	 * @var Plugin
	 */
	private static $instance;

	/**
	 * Get Instance
	 *
	 * @return Plugin
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Init Hooks
	 */
	private function init_hooks() {
		add_action( 'plugins_loaded', [ $this, 'on_plugins_loaded' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_scripts' ] );
        add_action( 'init', [ $this, 'register_cpt' ] );
	}

	/**
	 * On Plugins Loaded
	 */
	public function on_plugins_loaded() {
		// Initialize components here
        new \PersonaliseIt\Api\ProductController();
        new \PersonaliseIt\Api\ConfigController();
        new \PersonaliseIt\Api\FontController();
        new \PersonaliseIt\Api\PaletteController();
        new \PersonaliseIt\Api\OrderController();
        new \PersonaliseIt\Api\AssetController();
        new \PersonaliseIt\Api\DataController();
        new \PersonaliseIt\Api\ShareController();
        new \PersonaliseIt\Api\UploadController();
        new \PersonaliseIt\Api\AiController();
        new \PersonaliseIt\Api\SpotifyController();
        new \PersonaliseIt\Api\SecureSettingsController();
        new \PersonaliseIt\Api\ProofController();
        \PersonaliseIt\Services\ProofGenerator::init();
        \PersonaliseIt\Services\CleanupService::init();
        new \PersonaliseIt\Admin\AssetManager();
        new \PersonaliseIt\Frontend\ProductPage();
        new \PersonaliseIt\Frontend\CartIntegration();
        new \PersonaliseIt\Admin\Settings();

        if ( is_admin() ) {
            new \PersonaliseIt\Admin\Menu();
        }
	}

    /**
     * Register CPT
     */
    public function register_cpt() {
        register_post_type( 'personaliseit_font', [
            'public' => false,
            'show_ui' => false, // Managed via our React App
            'label'  => 'Fonts',
            'supports' => [ 'title' ],
        ] );

        register_post_type( 'personaliseit_pal', [
            'public' => false,
            'show_ui' => false,
            'label'  => 'Palettes',
            'supports' => [ 'title' ],
        ] );

        register_post_type( 'personaliseit_tpl', [
            'public' => false,
            'show_ui' => true, 
            'label'  => 'Design Templates',
            'supports' => [ 'title', 'editor', 'custom-fields', 'thumbnail', 'author' ], // Added thumbnail for previews
            'show_in_rest' => true,
            'show_in_menu' => false, // Hidden from default menu, replaced by React UI
        ] );

        register_taxonomy( 'personaliseit_tpl_cat', 'personaliseit_tpl', [
            'labels' => [
                'name' => __( 'Template Categories', 'personaliseit' ),
                'singular_name' => __( 'Category', 'personaliseit' ),
            ],
            'public' => false,
            'show_ui' => true,
            'hierarchical' => true,
            'show_in_rest' => true,
            'show_admin_column' => true,
        ] );

        register_post_type( 'personaliseit_share', [
            'public' => false,
            'show_ui' => false,
            'label'  => 'Shared Designs',
            'supports' => [ 'custom-fields' ],
        ] );

        register_post_type( 'personaliseit_style', [
            'public' => false, // Keep false to prevent default routes/listings
            'publicly_queryable' => true, // ALLOW frontend queries (needed for REST API read access without auth)
            'show_ui' => false,
            'label'  => 'AI Styles',
            'supports' => [ 'title', 'custom-fields' ],
            'show_in_rest' => true,
            'exclude_from_search' => true, // Don't show in search results
        ] );

        // Register Meta
        register_post_meta( 'personaliseit_style', 'personaliseit_style_prompt', [
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
            'sanitize_callback' => 'sanitize_textarea_field', // Allow newlines if needed
            // 'auth_callback' removed to allow public read access. CPT capabilities protect write.
        ] );
        register_post_meta( 'personaliseit_style', 'personaliseit_style_preview', [
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback' => function() { return current_user_can('edit_posts'); },
        ] );
        register_post_meta( 'personaliseit_style', 'personaliseit_style_remove_bg', [
            'show_in_rest' => true,
            'single' => true,
            'type' => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'auth_callback' => function() { return current_user_can('edit_posts'); },
        ] );
    }

    /**
     * Enqueue Admin Scripts
     */
    public function enqueue_admin_scripts( $hook ) {
        // Load on our plugin pages (main page and fonts submenu)
        // Load on our plugin pages
        if ( strpos( $hook, 'page_personaliseit' ) === false && 'toplevel_page_personaliseit' !== $hook ) { 
            return; 
        }

        $asset_file = PERSONALISET_PATH . 'build/admin.asset.php';
        if ( file_exists( $asset_file ) ) {
            $assets = require $asset_file;
            wp_enqueue_script(
                'personaliseit-admin',
                PERSONALISET_URL . 'build/admin.js',
                $assets['dependencies'],
                $assets['version'],
                true
            );
            wp_enqueue_style(
                'personaliseit-admin',
                PERSONALISET_URL . 'build/style-admin.css',
                [],
                $assets['version']
            );

            wp_enqueue_style( 'wp-color-picker' );
            wp_enqueue_script( 'wp-color-picker' );
        }
    }

    /**
     * Enqueue Frontend Scripts
     */
    public function enqueue_frontend_scripts() {
        if ( ! is_product() ) { return; }

        $asset_file = PERSONALISET_PATH . 'build/frontend.asset.php';
        if ( file_exists( $asset_file ) ) {
            $assets = require $asset_file;
            wp_enqueue_script(
                'personaliseit-frontend',
                PERSONALISET_URL . 'build/frontend.js',
                $assets['dependencies'],
                $assets['version'],
                true
            );
            wp_enqueue_style(
                'personaliseit-frontend',
                PERSONALISET_URL . 'build/style-frontend.css',
                [],
                $assets['version']
            );
        }
    }

}

// Activation Hook
register_activation_hook( __FILE__, [ 'PersonaliseIt\Database\DesignTable', 'install' ] );

// Kick it off
Plugin::get_instance();
