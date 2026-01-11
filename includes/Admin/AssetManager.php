<?php
namespace PersonaliseIt\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AssetManager {
    public function __construct() {
        add_action( 'init', [ $this, 'register_assets_cpt' ] );
    }

    public function register_assets_cpt() {
        register_post_type( 'personaliseit_asset', [
            'labels' => [
                'name' => __( 'Clipart Assets', 'personaliseit' ),
                'singular_name' => __( 'Asset', 'personaliseit' ),
                'add_new' => __( 'Add New Asset', 'personaliseit' ),
                'add_new_item' => __( 'Add New Asset', 'personaliseit' ),
                'new_item' => __( 'New Asset', 'personaliseit' ),
                'edit_item' => __( 'Edit Asset', 'personaliseit' ),
                'view_item' => __( 'View Asset', 'personaliseit' ),
                'all_items' => __( 'Clipart Library', 'personaliseit' ), // Submenu label
                'search_items' => __( 'Search Assets', 'personaliseit' ),
            ],
            'public' => false,
            'show_ui' => false, // We will use a custom React page
            // 'show_in_menu' => 'personaliseit', // Handled custom in Menu.php
            'supports' => [ 'title', 'thumbnail', 'custom-fields' ], 
            'show_in_rest' => true,
            'menu_icon' => 'dashicons-images-alt2',
        ] );

        register_taxonomy( 'personaliseit_asset_cat', 'personaliseit_asset', [
            'labels' => [
                'name' => __( 'Asset Categories', 'personaliseit' ),
                'singular_name' => __( 'Category', 'personaliseit' ),
            ],
            'public' => false,
            'show_ui' => true,
            'hierarchical' => true,
            'show_in_rest' => true,
            'show_admin_column' => true,
        ] );
    }
}
