<?php
namespace PersonaliseIt\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DataController {
    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        register_rest_route( 'personaliseit/v1', '/export', [
            'methods' => 'GET',
            'callback' => [ $this, 'export_data' ],
            'permission_callback' => function() { return current_user_can('manage_options'); },
        ] );

        register_rest_route( 'personaliseit/v1', '/import', [
            'methods' => 'POST',
            'callback' => [ $this, 'import_data' ],
            'permission_callback' => function() { return current_user_can('manage_options'); },
        ] );
    }

    public function export_data( $request ) {
        $data = [
            'version' => PERSONALISET_VERSION,
            'date' => gmdate('c'),
            'settings' => [],
            'fonts' => [],
            'assets' => [],
            'products' => [],
        ];

        // 1. Settings
        $setting_keys = [
            'personaliseit_canvas_width',
            'personaliseit_canvas_height',
            'personaliseit_max_upload_size',
            'personaliseit_enable_engraving',
            'personaliseit_enable_embroidery',
            'personaliseit_enable_dtf',
            'personaliseit_enable_uv',
            'personaliseit_enable_sublimation',
            'personaliseit_label_position',
            'personaliseit_openrouter_api_key',
            'personaliseit_ai_model',
            'personaliseit_ai_style_prompt',
            'personaliseit_enable_ai_generate',
            'personaliseit_enable_ai_style',
            'personaliseit_ai_styles',
        ];
        foreach ( $setting_keys as $key ) {
            $data['settings'][ $key ] = get_option( $key );
        }

        // 2. Fonts
        $fonts = get_posts([
            'post_type' => 'personaliseit_font',
            'posts_per_page' => -1,
            'post_status' => 'publish',
        ]);
        foreach ( $fonts as $font ) {
            $data['fonts'][] = [
                'title' => $font->post_title,
                'family' => get_post_meta( $font->ID, 'personaliseit_font_family', true ),
                'url' => get_post_meta( $font->ID, 'personaliseit_font_url', true ),
            ];
        }

        // 3. Assets
        $assets = get_posts([
            'post_type' => 'personaliseit_asset',
            'posts_per_page' => -1,
            'post_status' => 'publish',
        ]);
        foreach ( $assets as $asset ) {
            $cats = wp_get_post_terms( $asset->ID, 'personaliseit_asset_cat', [ 'fields' => 'names' ] );
            $thumb_id = get_post_thumbnail_id( $asset->ID );
            $thumb_url = wp_get_attachment_url( $thumb_id );
            
            $data['assets'][] = [
                'title' => $asset->post_title,
                'price' => get_post_meta( $asset->ID, 'personaliseit_asset_price', true ),
                'categories' => $cats,
                'image_url' => $thumb_url,
            ];
        }

        // 4. Products Config
        global $wpdb;
        $results = $wpdb->get_results( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_personaliseit_config'" );
        
        foreach ( $results as $row ) {
            $config = maybe_unserialize( $row->meta_value );
            if ( $config && ( isset($config['views']) || isset($config['layers']) ) ) {
                $data['products'][] = [
                    'product_id' => $row->post_id,
                    'product_name' => get_the_title( $row->post_id ),
                    'config' => $config,
                ];
            }
        }

        // Apply URL portability
        $data = $this->process_placeholders( $data, 'export' );

        return rest_ensure_response( $data );
    }

    public function import_data( $request ) {
        $params = $request->get_json_params();
        if ( ! $params || ! isset( $params['data'] ) ) {
            return new \WP_Error( 'invalid_data', 'No data provided', [ 'status' => 400 ] );
        }
        
        $data = $params['data'];
        
        // Restore URLs
        $data = $this->process_placeholders( $data, 'import' );

        // 1. Restore Settings - STRICT ALLOW-LIST
        $allowed_settings = [
            'personaliseit_canvas_width',
            'personaliseit_canvas_height',
            'personaliseit_max_upload_size',
            'personaliseit_enable_engraving',
            'personaliseit_enable_embroidery',
            'personaliseit_enable_dtf',
            'personaliseit_enable_uv',
            'personaliseit_enable_sublimation',
            'personaliseit_label_position',
            'personaliseit_openrouter_api_key',
            'personaliseit_ai_model',
            'personaliseit_ai_style_prompt',
            'personaliseit_enable_ai_generate',
            'personaliseit_enable_ai_style',
            'personaliseit_ai_styles',
        ];

        if ( isset( $data['settings'] ) && is_array( $data['settings'] ) ) {
            foreach ( $data['settings'] as $key => $value ) {
                if ( in_array( $key, $allowed_settings, true ) ) {
                    // Sanitize based on known types if needed, but for now just allow strings/bools
                    // WP options API handles some serialization, but let's be safe
                    update_option( $key, sanitize_text_field( $value ) );
                }
            }
        }

        // 2. Restore Fonts
        if ( isset( $data['fonts'] ) && is_array( $data['fonts'] ) ) {
            foreach ( $data['fonts'] as $font ) {
                $family = sanitize_text_field( $font['family'] ?? '' );
                $title  = sanitize_text_field( $font['title'] ?? '' );
                $url    = esc_url_raw( $font['url'] ?? '' );

                if ( empty( $family ) || empty( $url ) ) continue;

                // Check existence by family name
                $exists = get_posts([
                    'post_type' => 'personaliseit_font',
                    'meta_query' => [
                        [
                            'key' => 'personaliseit_font_family',
                            'value' => $family,
                        ]
                    ],
                    'posts_per_page' => 1
                ]);
                
                if ( ! $exists ) {
                    $post_id = wp_insert_post([
                        'post_type' => 'personaliseit_font',
                        'post_title' => $title,
                        'post_status' => 'publish',
                    ]);
                    // Security: sanitize meta values
                    update_post_meta( $post_id, 'personaliseit_font_family', $family );
                    update_post_meta( $post_id, 'personaliseit_font_url', $url );
                }
            }
        }

        // 3. Restore Assets
        if ( isset( $data['assets'] ) && is_array( $data['assets'] ) ) {
             foreach ( $data['assets'] as $asset ) {
                 $title = sanitize_text_field( $asset['title'] ?? '' );
                 if ( empty( $title ) ) continue;

                 $query = new \WP_Query( [
                     'post_type' => 'personaliseit_asset',
                     'title' => $title,
                     'post_status' => 'any',
                     'posts_per_page' => 1,
                     'fields' => 'ids',
                 ] );
                 $exists = ! empty( $query->posts );
                 if ( ! $exists ) {
                     $post_id = wp_insert_post([
                        'post_type' => 'personaliseit_asset',
                        'post_title' => $title,
                        'post_status' => 'publish',
                     ]);
                     
                     $price = isset($asset['price']) ? floatval($asset['price']) : 0;
                     update_post_meta( $post_id, 'personaliseit_asset_price', $price );
                     
                     if ( !empty($asset['categories']) && is_array($asset['categories']) ) {
                         $cats = array_map('sanitize_text_field', $asset['categories']);
                         wp_set_object_terms( $post_id, $cats, 'personaliseit_asset_cat' );
                     }
                 }
             }
        }

        // 4. Restore Product Configs
        if ( isset( $data['products'] ) && is_array( $data['products'] ) ) {
            foreach ( $data['products'] as $p ) {
                $pid = isset($p['product_id']) ? absint($p['product_id']) : 0;
                if ( !$pid ) continue;

                if ( get_post_type($pid) === 'product' ) {
                    // Config is a complex array.
                    if ( isset($p['config']) && is_array($p['config']) ) {
                         // Sanitize deeply
                         $clean_config = $this->sanitize_recursive( $p['config'] );
                         update_post_meta( $pid, '_personaliseit_config', $clean_config );
                    }
                }
            }
        }

        return rest_ensure_response( [ 'success' => true ] );
    }

    private function sanitize_recursive( $data ) {
        if ( is_array( $data ) ) {
            foreach ( $data as $key => $value ) {
                $data[ $key ] = $this->sanitize_recursive( $value );
            }
            return $data;
        }
        if ( is_string( $data ) ) {
            return sanitize_textarea_field( $data );
        }
        return $data;
    }

    /**
     * Recursively replace site URL with placeholder and vice versa
     * 
     * @param mixed  $data      Data to process
     * @param string $direction 'export' (to placeholder) or 'import' (from placeholder)
     * @return mixed
     */
    private function process_placeholders( $data, $direction ) {
        $site_url = site_url();
        $placeholder = '{{SITE_URL}}';

        if ( is_string( $data ) ) {
            if ( $direction === 'export' ) {
                return str_replace( $site_url, $placeholder, $data );
            } else {
                return str_replace( $placeholder, $site_url, $data );
            }
        } elseif ( is_array( $data ) ) {
            foreach ( $data as $key => $value ) {
                $data[ $key ] = $this->process_placeholders( $value, $direction );
            }
            return $data;
        } elseif ( is_object( $data ) ) {
             // Handle objects if necessary, though mostly we deal with arrays here
             foreach ( get_object_vars( $data ) as $key => $value ) {
                 $data->$key = $this->process_placeholders( $value, $direction );
             }
             return $data;
        }

        return $data;
    }
}
