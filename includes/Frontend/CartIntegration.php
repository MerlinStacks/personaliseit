<?php
namespace PersonaliseIt\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cart and Order Integration Handler
 */
class CartIntegration {

    /**
     * Constructor
     */
    public function __construct() {
        // Display in Cart and Checkout
        add_filter( 'woocommerce_get_item_data', [ $this, 'display_cart_item_data' ], 10, 2 );
        
        // Save to Order
        add_action( 'woocommerce_checkout_create_order_line_item', [ $this, 'add_order_item_meta' ], 10, 4 );
        
        // Display in Admin Order View
        add_action( 'woocommerce_before_order_itemmeta', [ $this, 'display_admin_order_item_meta' ], 10, 3 );
        
        // Calculate Totals
        add_action( 'woocommerce_before_calculate_totals', [ $this, 'calculate_totals' ], 20, 1 );

        // Live Visual Cart Preview (Swap Thumbnail)
        add_filter( 'woocommerce_cart_item_thumbnail', [ $this, 'filter_cart_item_thumbnail' ], 10, 3 );
    }

    /**
     * Helper: Flatten layers from config (Views or Direct Layers)
     *
     * @param array $config Product configuration
     * @return array
     */
    private function get_all_layers( $config ) {
        $all_layers = [];
        if ( ! empty( $config['views'] ) ) {
            foreach ( $config['views'] as $view ) {
                if ( ! empty( $view['layers'] ) ) {
                    $all_layers = array_merge( $all_layers, $view['layers'] );
                }
            }
        } elseif ( ! empty( $config['layers'] ) ) {
            $all_layers = $config['layers'];
        }
        return $all_layers;
    }

    /**
     * Swap Cart Thumbnail with Custom Preview
     */
    public function filter_cart_item_thumbnail( $product_image, $cart_item, $cart_item_key ) {
        if ( ! empty( $cart_item['personaliseit_data']['previewImage'] ) ) {
            $src = $cart_item['personaliseit_data']['previewImage'];
            // Security: Ensure it is a data URL or local URL
            if ( strpos( $src, 'data:image' ) === 0 || filter_var( $src, FILTER_VALIDATE_URL ) ) {
                // Return our custom image, preserving classes if possible or just standard img tag
                return '<img src="' . esc_url( $src ) . '" class="attachment-woocommerce_thumbnail size-woocommerce_thumbnail" alt="' . esc_attr__( 'Personalised Preview', 'personaliseit' ) . '" style="border: 1px solid #ddd; border-radius: 4px;" />';
            }
        }
        return $product_image;
    }

    /**
     * Calculate Totals
     */
    public function calculate_totals( $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;
        
        foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
            if ( ! empty( $cart_item['personaliseit_data'] ) ) {
                $product_id = $cart_item['product_id'];
                $config = get_post_meta( $product_id, '_personaliseit_config', true );
                if ( ! $config || ( empty( $config['layers'] ) && empty( $config['views'] ) ) ) continue;

                $data = $cart_item['personaliseit_data'];
                // Handle { inputs, styles } structure or legacy flat
                $inputs = isset( $data['inputs'] ) ? $data['inputs'] : $data;
                
                $extra_price = 0;
                $all_layers = $this->get_all_layers( $config );

                foreach ( $all_layers as $layer ) {
                     if ( ! empty( $layer['price'] ) ) {
                         if ( ! empty( $inputs[ $layer['id'] ] ) ) {
                             $extra_price += floatval( $layer['price'] );
                         }
                     }
                }
                
                // Add price for custom layers (e.g. Clipart)
                if ( ! empty( $data['customLayers'] ) && is_array( $data['customLayers'] ) ) {
                    foreach ( $data['customLayers'] as $layer ) {
                         // Secure Price Validation:
                         // 1. If it has an assetId, look up the price from the DB.
                         if ( ! empty( $layer['assetId'] ) ) {
                             $asset_price = get_post_meta( intval( $layer['assetId'] ), 'personaliseit_asset_price', true );
                             if ( $asset_price !== '' ) {
                                 $extra_price += floatval( $asset_price );
                             }
                         }
                         // 2. User uploads (drag & drop) currently have no price logic, so we ignore other 'price' inputs.
                    }
                }

                if ( $extra_price > 0 ) {
                    // We must use the base price (product price) + extra. 
                    // get_price() might already include modifications if repeated?
                    // Usually safe to add to *base* price logic, but woocommerce session keeps state.
                    // Actually, standard practice is to reset price to base + extra.
                    // But we don't know other plugins. 
                    // Safer: get current price (which reset every load) + extra.
                    $price = $cart_item['data']->get_price();
                    $cart_item['data']->set_price( $price + $extra_price );
                }
            }
        }
    }

    /**
     * Display Cart Item Data
     */
    public function display_cart_item_data( $item_data, $cart_item ) {
        if ( empty( $cart_item['personaliseit_data'] ) ) {
            return $item_data;
        }

        $data = $cart_item['personaliseit_data'];
        
        // We need to fetch the config to know the labels
        $product_id = $cart_item['product_id'];
        $config = get_post_meta( $product_id, '_personaliseit_config', true );
        
        if ( empty( $config ) ) {
            return $item_data;
        }

        $all_layers = $this->get_all_layers( $config );

        $layers_map = [];
        foreach ( $all_layers as $layer ) {
            $layers_map[ $layer['id'] ] = $layer['label'] ?? $layer['type'];
        }

        $inputs = isset( $data['inputs'] ) ? $data['inputs'] : $data;

        foreach ( $inputs as $layer_id => $value ) {
            if ( isset( $layers_map[ $layer_id ] ) && ! empty( $value ) ) {
                $display_value = esc_html( $value );
                
                // If it looks like a URL (Image Upload), make it a link or nice text
                if ( filter_var( $value, FILTER_VALIDATE_URL ) ) {
                     $display_value = '<a href="' . esc_url( $value ) . '" target="_blank" class="personaliseit-image-link">' . __( 'View Upload', 'personaliseit' ) . '</a>';
                }

                $item_data[] = [
                    'key'     => $layers_map[ $layer_id ],
                    'value'   => $value, 
                    'display' => $display_value,
                ];
            }
        }

        // 2. Styles (Colors)
        $styles = isset( $data['styles'] ) ? $data['styles'] : [];
        foreach ( $styles as $layer_id => $style ) {
             if ( ! isset( $layers_map[ $layer_id ] ) ) continue;
             if ( ! empty( $style['fill'] ) && $style['fill'] !== 'transparent' ) {
                 $item_data[] = [
                     'key'     => sprintf( __('%s Color', 'personaliseit'), $layers_map[ $layer_id ] ),
                     'value'   => $style['fill'],
                     'display' => esc_html( $style['fill'] ),
                 ];
             }
        }

        // 3. Global Embroidery Color
        if ( ! empty( $data['embroideryColor'] ) ) {
             $ec = $data['embroideryColor'];
             $name = is_array($ec) ? ($ec['name'] ?? 'Selected') : $ec;
             $item_data[] = [
                 'key'     => __( 'Color', 'personaliseit' ), // Generic 'Color' usually refers to the detailed selection
                 'value'   => $name,
                 'display' => esc_html( $name ),
             ];
        }

        return $item_data;
    }

    /**
     * Add Order Item Meta
     */
    public function add_order_item_meta( $item, $cart_item_key, $values, $order ) {
        if ( empty( $values['personaliseit_data'] ) ) {
            return;
        }

        // Optimisation: Save large JSON blob to custom table using DesignTable
        if ( class_exists( '\PersonaliseIt\Database\DesignTable' ) ) {
            \PersonaliseIt\Database\DesignTable::save( $order->get_id(), $values['personaliseit_data'], $item->get_id() );
            // Helper reference for easy checking
            $item->add_meta_data( '_personaliseit_has_design', $order->get_id() );
        } else {
             // Fallback
             $item->add_meta_data( '_personaliseit_data', $values['personaliseit_data'] );
        }

        // Also add readable meta for emails/admin (optional, but good for UX)
        // We repeat the logic from display_cart_item_data to add individual meta keys
        $product_id = $values['product_id'];
        $config = get_post_meta( $product_id, '_personaliseit_config', true );
        
        if ( ! empty( $config ) ) {
            $all_layers = $this->get_all_layers( $config );

            $layers_map = [];
            foreach ( $all_layers as $layer ) {
                $layers_map[ $layer['id'] ] = $layer['label'] ?? $layer['type'];
            }

            $inputs = isset( $values['personaliseit_data']['inputs'] ) ? $values['personaliseit_data']['inputs'] : $values['personaliseit_data'];

            foreach ( $inputs as $layer_id => $value ) {
                if ( isset( $layers_map[ $layer_id ] ) && ! empty( $value ) ) {
                    $item->add_meta_data( $layers_map[ $layer_id ], $value );
                }
            }

            // Styles
            $styles = isset( $values['personaliseit_data']['styles'] ) ? $values['personaliseit_data']['styles'] : [];
            foreach ( $styles as $layer_id => $style ) {
                 if ( isset( $layers_map[ $layer_id ] ) && ! empty( $style['fill'] ) ) {
                      $item->add_meta_data( sprintf( __('%s Color', 'personaliseit'), $layers_map[ $layer_id ] ), $style['fill'] );
                 }
            }

            // Embroidery Color
            if ( ! empty( $values['personaliseit_data']['embroideryColor'] ) ) {
                 $ec = $values['personaliseit_data']['embroideryColor'];
                 $name = is_array($ec) ? ($ec['name'] ?? 'Selected') : $ec;
                 $item->add_meta_data( __( 'Color', 'personaliseit' ), $name );
            }
        }
    }

    /**
     * Display Admin Order Item Meta
     */
    public function display_admin_order_item_meta( $item_id, $item, $product ) {
        // Retrieve data: Check custom table first, then meta
        $data = null;
        if ( class_exists( '\PersonaliseIt\Database\DesignTable' ) ) {
            $design_row = \PersonaliseIt\Database\DesignTable::get_item_design( $item->get_order_id(), $item_id );
            if ( $design_row ) {
                $data = $design_row->design_data;
            }
        }
        
        if ( ! $data ) {
            $data = $item->get_meta( '_personaliseit_data' );
        }

        if ( ! $data ) { return; }

        if ( is_string( $data ) ) {
            $data = json_decode( $data, true );
        }

        echo '<div class="personaliseit-order-data" style="margin-top: 10px; padding: 10px; background: #f9f9f9; border: 1px solid #e5e5e5;">';
        
        if ( ! empty( $data['previewImage'] ) ) {
            echo '<strong>' . esc_html__( 'Preview:', 'personaliseit' ) . '</strong><br>';
            echo '<img src="' . esc_url( $data['previewImage'] ) . '" style="max-width: 300px; border: 1px solid #ccc; margin-top: 5px;" /><br><br>';
        }

        // Also display inputs details explicitly if desired
        if ( ! empty( $data['inputs'] ) && is_array( $data['inputs'] ) ) {
             echo '<strong>' . esc_html__( 'Personalisation Details:', 'personaliseit' ) . '</strong><ul style="margin-top: 0; list-style: disc; padding-left: 20px;">';
             
             // We try to get readable labels again if possible, or just raw
             $product_id = $item->get_product_id();
             $config = get_post_meta( $product_id, '_personaliseit_config', true );
             $layers_map = [];
             
             $all_layers = $this->get_all_layers( $config );

             if ( ! empty( $all_layers ) ) {
                foreach ( $all_layers as $layer ) {
                    $layers_map[ $layer['id'] ] = $layer['label'] ?? $layer['type'];
                }
             }

             foreach ( $data['inputs'] as $input_key => $input_val ) {
                 if ( empty( $input_val ) ) continue;
                 $label = isset( $layers_map[ $input_key ] ) ? $layers_map[ $input_key ] : $input_key;

                 $display_val = esc_html( $input_val );
                 if ( filter_var( $input_val, FILTER_VALIDATE_URL ) ) {
                     $display_val = '<a href="' . esc_url( $input_val ) . '" target="_blank">' . __( 'View Upload', 'personaliseit' ) . '</a>';
                 }
                 echo '<li><strong>' . esc_html( $label ) . ':</strong> ' . $display_val . '</li>';
             }

             // Add Styles (Colors)
             if ( ! empty( $data['styles'] ) ) {
                 foreach ( $data['styles'] as $layer_id => $style ) {
                     if ( ! empty( $style['fill'] ) && $style['fill'] !== 'transparent' ) {
                         $label = isset( $layers_map[ $layer_id ] ) ? sprintf( __('%s Color', 'personaliseit'), $layers_map[$layer_id] ) : __('Color', 'personaliseit');
                         echo '<li><strong>' . esc_html( $label ) . ':</strong> ' . esc_html( $style['fill'] ) . '</li>';
                     }
                 }
             }

             // Add Global Color
             if ( ! empty( $data['embroideryColor'] ) ) {
                 $ec = $data['embroideryColor'];
                 $name = is_array($ec) ? ($ec['name'] ?? 'Selected') : $ec;
                 $item->add_meta_data( __( 'Color', 'personaliseit' ), $name ); // This line was probably just echoing in previous version
                 echo '<li><strong>' . esc_html__( 'Color', 'personaliseit' ) . ':</strong> ' . esc_html( $name ) . '</li>';
             }

             echo '</ul>';
        }

        // Check enabled formats
        $enable_png = get_option( 'personaliseit_enable_png_download' );
        $enable_jpg = get_option( 'personaliseit_enable_jpg_download' );
        $enable_pdf = get_option( 'personaliseit_enable_pdf_download' );
        $enable_svg = get_option( 'personaliseit_enable_svg_download' );

        $base_export_url = admin_url( 'admin.php?page=personaliseit-export&order_id=' . $item->get_order_id() . '&item_id=' . $item_id );

        echo '<div style="margin-top: 10px; display: flex; gap: 5px; flex-wrap: wrap;">';

        // $designer_url = admin_url( 'admin.php?page=personaliseit&order_id=' . $item->get_order_id() . '&item_id=' . $item_id );
        // echo '<a href="' . esc_url( $designer_url ) . '" target="_blank" class="button button-primary button-small">' . esc_html__( 'Open in Designer', 'personaliseit' ) . '</a>';

        if ( $enable_png || ( ! $enable_jpg && ! $enable_pdf && ! $enable_svg ) ) {
             // Default to PNG if nothing else or PNG enabled
             echo '<a href="' . esc_url( $base_export_url . '&format=png' ) . '" target="_blank" class="button button-secondary button-small">' . esc_html__( 'Download PNG', 'personaliseit' ) . '</a>';
        }

        if ( $enable_jpg ) {
             echo '<a href="' . esc_url( $base_export_url . '&format=jpg' ) . '" target="_blank" class="button button-secondary button-small">' . esc_html__( 'Download JPG', 'personaliseit' ) . '</a>';
        }

        if ( $enable_pdf ) {
             echo '<a href="' . esc_url( $base_export_url . '&format=pdf' ) . '" target="_blank" class="button button-secondary button-small">' . esc_html__( 'Download PDF', 'personaliseit' ) . '</a>';
        }

        if ( $enable_svg ) {
             echo '<a href="' . esc_url( $base_export_url . '&format=svg' ) . '" target="_blank" class="button button-secondary button-small">' . esc_html__( 'Download SVG', 'personaliseit' ) . '</a>';
        }

        echo '</div>';
        
        echo '</div>';
    }
}
