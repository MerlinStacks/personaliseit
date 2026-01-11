<?php
namespace PersonaliseIt\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin Menu Handler
 */
class Menu {

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register_menu_page' ] );
    }

    /**
     * Register Menu Page
     */
    public function register_menu_page() {
        // Main menu page
        add_menu_page(
            __( 'Personalise It!', 'personaliseit' ),
            __( 'Personalise It!', 'personaliseit' ),
            'manage_options',
            'personaliseit',
            [ $this, 'render_admin_app' ],
            'dashicons-art',
            58
        );

        // Designer submenu (same as parent, but shows in submenu)
        add_submenu_page(
            'personaliseit',
            __( 'Designer', 'personaliseit' ),
            __( 'Designer', 'personaliseit' ),
            'manage_options',
            'personaliseit',
            [ $this, 'render_admin_app' ]
        );

        // Templates submenu
        add_submenu_page(
            'personaliseit',
            __( 'Design Templates', 'personaliseit' ),
            __( 'Design Templates', 'personaliseit' ),
            'manage_options',
            'personaliseit-templates',
            [ $this, 'render_templates_app' ]
        );

        // Fonts submenu
        add_submenu_page(
            'personaliseit',
            __( 'Fonts', 'personaliseit' ),
            __( 'Fonts', 'personaliseit' ),
            'manage_options',
            'personaliseit-fonts',
            [ $this, 'render_fonts_app' ]
        );

        // Colors submenu
        add_submenu_page(
            'personaliseit',
            __( 'Colors', 'personaliseit' ),
            __( 'Colors', 'personaliseit' ),
            'manage_options',
            'personaliseit-colors',
            [ $this, 'render_colors_app' ]
        );

        add_submenu_page(
            'personaliseit',
            __( 'Clipart', 'personaliseit' ),
            __( 'Clipart', 'personaliseit' ),
            'manage_options',
            'personaliseit-assets',
            [ $this, 'render_assets_app' ]
        );

        // AI Styles submenu
        add_submenu_page(
            'personaliseit',
            __( 'Artist Styles', 'personaliseit' ),
            __( 'Artist Styles', 'personaliseit' ),
            'manage_options',
            'personaliseit-styles',
            [ $this, 'render_styles_app' ]
        );

        // Settings submenu
        add_submenu_page(
            'personaliseit',
            __( 'Settings', 'personaliseit' ),
            __( 'Settings', 'personaliseit' ),
            'manage_options',
            'personaliseit-settings',
            [ $this, 'render_settings_page' ]
        );

        // Hidden Export Page
        add_submenu_page(
            '',
            __( 'Export Design', 'personaliseit' ),
            __( 'Export Design', 'personaliseit' ),
            'manage_options',
            'personaliseit-export',
            [ $this, 'render_export_page' ]
        );
    }

    /**
     * Render Admin App Container
     */
    public function render_admin_app() {
        echo '<div id="personaliseit-admin-root" data-page="designer"></div>';
    }

    /**
     * Render Templates App Container
     */
    public function render_templates_app() {
        echo '<div id="personaliseit-admin-root" data-page="templates"></div>';
    }

    /**
     * Render Fonts App Container
     */
    public function render_fonts_app() {
        echo '<div id="personaliseit-admin-root" data-page="fonts"></div>';
    }

    /**
     * Render Colors App Container
     */
    public function render_colors_app() {
        echo '<div id="personaliseit-admin-root" data-page="colors"></div>';
    }

    /**
     * Render Assets App Container
     */
    public function render_assets_app() {
        echo '<div id="personaliseit-admin-root" data-page="assets"></div>';
    }

    /**
     * Render Styles App Container
     */
    public function render_styles_app() {
        echo '<div id="personaliseit-admin-root" data-page="styles"></div>';
    }

    /**
     * Render Settings Page
     */
    public function render_settings_page() {
        echo '<div id="personaliseit-admin-root" data-page="settings"></div>';
    }

    public function render_export_page() {
        echo '<div id="personaliseit-admin-root" data-page="export"></div>';
    }
}
