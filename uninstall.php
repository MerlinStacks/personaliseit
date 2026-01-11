<?php
/**
 * Fired when the plugin is uninstalled.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// 1. Delete Options
$personaliseit_options = [
    'personaliseit_canvas_width',
    'personaliseit_canvas_height',
    'personaliseit_max_upload_size',
    'personaliseit_enable_engraving',
    'personaliseit_enable_embroidery',
    'personaliseit_enable_dtf',
    'personaliseit_enable_uv',
    'personaliseit_enable_sublimation',
    'personaliseit_show_cost',
    'personaliseit_enable_pdf_download',
    'personaliseit_enable_svg_download',
    'personaliseit_enable_jpg_download',
    'personaliseit_enable_png_download',
    'personaliseit_label_position',
    // AI Settings
    'personaliseit_openrouter_api_key',
    'personaliseit_ai_model',
    'personaliseit_ai_style_prompt',
    'personaliseit_enable_ai_generate',
    'personaliseit_enable_ai_style',
    'personaliseit_ai_styles',
    // Spotify Settings
    'personaliseit_enable_spotify',
    'personaliseit_spotify_client_id',
    'personaliseit_spotify_client_secret',
    // Face Cutout Settings
    'personaliseit_enable_face_cutout',
];

foreach ( $personaliseit_options as $personaliseit_option ) {
    delete_option( $personaliseit_option );
}

// 2. Delete Custom Post Types Content
// We force delete (skip trash) for all custom post types managed by the plugin
$personaliseit_cpts = [ 'personaliseit_font', 'personaliseit_tpl', 'personaliseit_asset', 'personaliseit_pal', 'personaliseit_share', 'personaliseit_style' ];

foreach ( $personaliseit_cpts as $personaliseit_cpt ) {
    $personaliseit_items = get_posts( [
        'post_type' => $personaliseit_cpt,
        'numberposts' => -1,
        'post_status' => 'any',
        'fields' => 'ids'
    ] );
    
    foreach ( $personaliseit_items as $personaliseit_item_id ) {
        wp_delete_post( $personaliseit_item_id, true );
    }
}

// 3. Remove Product Configuration Meta
// Remove the personaliser configuration attached to WooCommerce products
global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_personaliseit_config'" );

// 4. Delete Custom Taxonomies Terms
$personaliseit_taxonomies = [ 'personaliseit_asset_cat', 'personaliseit_tpl_cat' ];

foreach ( $personaliseit_taxonomies as $tax ) {
    $personaliseit_terms = get_terms( [
        'taxonomy' => $tax,
        'hide_empty' => false,
    ] );

    if ( ! is_wp_error( $personaliseit_terms ) ) {
        foreach ( $personaliseit_terms as $personaliseit_term ) {
            wp_delete_term( $personaliseit_term->term_id, $tax );
        }
    }
}

// Flush cache
wp_cache_flush();
