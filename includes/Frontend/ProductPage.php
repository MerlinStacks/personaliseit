<?php
namespace PersonaliseIt\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Product Page Handler
 */
class ProductPage {

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'woocommerce_before_single_product_summary', [ $this, 'render_personalizer_canvas' ], 15 );
        add_action( 'woocommerce_before_add_to_cart_button', [ $this, 'render_personalizer_controls' ] );
        add_filter( 'woocommerce_add_to_cart_validation', [ $this, 'validate_add_to_cart' ], 10, 3 );
        add_filter( 'woocommerce_add_cart_item_data', [ $this, 'add_cart_item_data' ], 10, 3 );
        add_filter( 'woocommerce_get_cart_item_from_session', [ $this, 'get_cart_item_from_session' ], 10, 2 );
        // Note: filter_cart_item_thumbnail is handled by CartIntegration.php
        add_filter( 'woocommerce_store_api_cart_line_item_return', [ $this, 'filter_store_api_cart_item' ], 20, 3 );
        add_filter( 'woocommerce_blocks_cart_item_data', [ $this, 'filter_blocks_cart_item_data' ], 20, 2 );
        add_filter( 'woocommerce_store_api_cart_item_images', [ $this, 'filter_store_api_images' ], 20, 3 );
        add_action( 'wp_enqueue_scripts', [ $this, 'localize_script' ], 20 );
    }

    /**
     * Check if product has personalization config
     */
    private function has_personalization_config( $product_id ) {
        $config = get_post_meta( $product_id, '_personaliseit_config', true );
        
        return ! empty( $config ) && ( 
            ( ! empty( $config['layers'] ) ) || 
            ( ! empty( $config['views'] ) && is_array( $config['views'] ) )
        );
    }

    /**
     * Render Personalizer Canvas (replaces product gallery)
     */
    public function render_personalizer_canvas() {
        $product = wc_get_product( get_the_ID() );

        if ( ! $product ) { return; }

        if ( $this->has_personalization_config( $product->get_id() ) ) {
            // Hide the default product images and style the canvas container
            // Canvas container - hidden by default, shown via JS when user interacts
            echo '<div id="personaliseit-canvas-container" style="display:none;"></div>';
        }
    }

    /**
     * Render Personalizer Controls (before add to cart button)
     */
    public function render_personalizer_controls() {
        $product = wc_get_product( get_the_ID() );

        if ( ! $product ) { return; }

        if ( $this->has_personalization_config( $product->get_id() ) ) {
            echo '<div id="personaliseit-controls-container" data-product-id="' . esc_attr( $product->get_id() ) . '"></div>';
        }
    }

    /**
     * Localize Script with Config Data
     */
    public function localize_script() {
        if ( ! is_product() ) { return; }
        
        $product = wc_get_product( get_the_ID() );

        if ( ! $product ) { return; }

        $config = get_post_meta( $product->get_id(), '_personaliseit_config', true );
        
        if ( ! empty( $config ) ) {
            $product_image_id = $product->get_image_id();
            $product_image_url = $product_image_id ? wp_get_attachment_image_url( $product_image_id, 'full' ) : wc_placeholder_img_src();

            $active_palette = null;
            if ( ! empty( $config['personalisationMethod'] ) && ! empty( $config['paletteMap'] ) ) {
                $method = $config['personalisationMethod'];
                if ( isset( $config['paletteMap'][ $method ] ) ) {
                    $palette_id = $config['paletteMap'][ $method ];
                    if ( $palette_id ) {
                        $colors = get_post_meta( $palette_id, '_palette_colors', true );
                        if ( is_array( $colors ) ) {
                            $active_palette = [
                                'id' => $palette_id,
                                'colors' => $colors,
                            ];
                        }
                    }
                }
            }

			$variations_data = [];
			if ( $product->is_type( 'variable' ) ) {
				$available_variations = $product->get_available_variations();
				foreach ( $available_variations as $var ) {
					$variations_data[] = [
						'id' => $var['variation_id'],
						'price' => $var['display_price'],
					];
				}
			}

            // Check for shared design
            $shared_design = null;
            if ( isset( $_GET['share_id'] ) ) {
                $slug = sanitize_text_field( wp_unslash( $_GET['share_id'] ) );
                $shares = get_posts( [
                    'name'        => $slug,
                    'post_type'   => 'personaliseit_share',
                    'post_status' => 'publish',
                    'numberposts' => 1
                ] );
                if ( $shares ) {
                    $shared_design = json_decode( $shares[0]->post_content, true );
                }
            }

			wp_localize_script( 'personaliseit-frontend', 'personaliseitData', [
				'nonce' => wp_create_nonce( 'wp_rest' ),
				'productId' => $product->get_id(),
				'productImage' => $product_image_url,
				'basePrice' => (float)$product->get_price(),
				'variations' => $variations_data,
				'currencySymbol' => get_woocommerce_currency_symbol(),
				'config' => $config,
				'activePalette' => $active_palette,
                'sharedDesign' => $shared_design,
				'settings' => [
					'canvasWidth' => (int) get_option( 'personaliseit_canvas_width', 800 ),
					'canvasHeight' => (int) get_option( 'personaliseit_canvas_height', 800 ),
					'showCost' => get_option( 'personaliseit_show_cost', '1' ),
					'labelPosition' => get_option( 'personaliseit_label_position', 'above' ),
					'enableAiGenerate' => get_option( 'personaliseit_enable_ai_generate', false ),
					'enableAiStyle' => get_option( 'personaliseit_enable_ai_style', false ),
					'aiStylePrompt' => get_option( 'personaliseit_ai_style_prompt', '' ),
				],
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'restUrl' => rest_url() ? esc_url_raw( rest_url() ) : '',
			] );
        }
    }

    /**
     * Validate Add to Cart
     */
    public function validate_add_to_cart( $passed, $product_id, $quantity ) {
        if ( ! $this->has_personalization_config( $product_id ) ) {
            return $passed;
        }

        // Check if data was submitted
        if ( empty( $_POST['personaliseit_data'] ) ) {
             // If config exists but no data, we might need to check if ANY layer is required.
             $config = get_post_meta( $product_id, '_personaliseit_config', true );
             $has_required = false;
             
             if ( ! empty( $config['views'] ) ) {
                 foreach ( $config['views'] as $view ) {
                     foreach ( $view['layers'] as $layer ) {
                         if ( ! empty( $layer['required'] ) ) {
                             $has_required = true;
                             break 2;
                         }
                     }
                 }
             } elseif ( ! empty( $config['layers'] ) ) {
                  foreach ( $config['layers'] as $layer ) {
                      if ( ! empty( $layer['required'] ) ) {
                          $has_required = true;
                          break;
                      }
                  }
             }

             if ( $has_required ) {
                 wc_add_notice( __( 'Please fill in all required personalization fields.', 'personaliseit' ), 'error' );
                 return false;
             }
             return $passed;
        }

        $raw_data = wp_unslash( $_POST['personaliseit_data'] );
        $data = json_decode( $raw_data, true );
        $inputs = isset( $data['inputs'] ) ? $data['inputs'] : [];

        $config = get_post_meta( $product_id, '_personaliseit_config', true );
        
        // Flatten layers to check requirements
        $layers_to_check = [];
        if ( ! empty( $config['views'] ) ) {
            foreach ( $config['views'] as $view ) {
                foreach ( $view['layers'] as $layer ) {
                    $layers_to_check[] = $layer;
                }
            }
        } elseif ( ! empty( $config['layers'] ) ) {
            $layers_to_check = $config['layers'];
        }

        foreach ( $layers_to_check as $layer ) {
            if ( ! empty( $layer['required'] ) ) {
                if ( empty( $inputs[ $layer['id'] ] ) || trim( $inputs[ $layer['id'] ] ) === '' ) {
                    $label = ! empty( $layer['label'] ) ? $layer['label'] : __( 'Field', 'personaliseit' );
                    wc_add_notice( sprintf( __( '"%s" is a required field.', 'personaliseit' ), $label ), 'error' );
                    $passed = false;
                }
            }
        }

        return $passed;
    }

    /**
     * Add Cart Item Data
     */
    public function add_cart_item_data( $cart_item_data, $product_id, $variation_id ) {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Request context doesn't always allow for nonce check here
        if ( isset( $_POST['personaliseit_data'] ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $raw_data = isset( $_POST['personaliseit_data'] ) ? wp_unslash( $_POST['personaliseit_data'] ) : '';
            $data = json_decode( $raw_data, true );
            
            if ( ! empty( $data['previewImage'] ) ) {
                 $url = $this->save_preview_image( $data['previewImage'] );
                 if ( $url ) {
                     $data['previewImage'] = $url;
                 } else {
                     // Log failure for debugging (uses WC_Logger if available)
                     if ( function_exists( 'wc_get_logger' ) ) {
                         wc_get_logger()->warning( 'Failed to save preview image. Input length: ' . strlen($data['previewImage']), [ 'source' => 'personaliseit' ] );
                     }
                     // Avoid session bloat/crash by clearing if save failed
                     $data['previewImage'] = '';
                 }
            }

            $cart_item_data['personaliseit_data'] = $data;
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce not feasible in this specific hook context, data is not critical.
            $cart_item_data['personaliseit_unique_key'] = md5( microtime() . wp_rand() );
        }
        return $cart_item_data;
    }

    private function save_preview_image( $base64_string ) {
        // 1. Basic format check
        if ( strpos( $base64_string, 'base64,' ) === false ) return false;
        
        $parts = explode( ',', $base64_string );
        if ( count( $parts ) < 2 ) return false;

        $data = base64_decode( end( $parts ), true );
        if ( $data === false ) return false;

        // 2. Strict MIME type check on the decoded binary data
        $finfo = new \finfo( FILEINFO_MIME_TYPE );
        $mime_type = $finfo->buffer( $data );

        // Map allowed Mime types to extensions
        $allowed_mimes = [
            'image/jpeg' => 'jpg',
            'image/pjpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
        ];

        if ( ! $mime_type || ! array_key_exists( $mime_type, $allowed_mimes ) ) {
            // Fallback: Check magic bytes if finfo fails or returns generic
            $hex = bin2hex( substr( $data, 0, 4 ) );
            if ( strpos( $hex, 'ffd8' ) === 0 ) {
                $mime_type = 'image/jpeg';
            } elseif ( strpos( $hex, '89504e47' ) === 0 ) {
                $mime_type = 'image/png';
            } else {
                // Log invalid MIME for debugging (uses WC_Logger if available)
                if ( function_exists( 'wc_get_logger' ) ) {
                    wc_get_logger()->warning( 'Invalid MIME type for preview: ' . $mime_type, [ 'source' => 'personaliseit' ] );
                }
                return false; 
            }
        }

        $extension = $allowed_mimes[ $mime_type ];

        // 3. Size check (e.g. max 5MB)
        if ( strlen( $data ) > 5 * 1024 * 1024 ) {
            return false;
        }

        $upload_dir = wp_upload_dir();
        $dirname = $upload_dir['basedir'] . '/personaliseit-previews';
        
        if ( ! file_exists( $dirname ) ) {
            wp_mkdir_p( $dirname );
            // Add index.php/htaccess to prevent directory browsing?
            // Not strictly necessary in uploads but good practice.
            file_put_contents( $dirname . '/index.php', '<?php // Silence is golden.' );
        }
        
        $filename = 'preview-' . md5( uniqid( rand(), true ) ) . '.' . $extension;
        $file_path = $dirname . '/' . $filename;
        $file_url = $upload_dir['baseurl'] . '/personaliseit-previews/' . $filename;
        
        if ( file_put_contents( $file_path, $data ) === false ) {
            return false;
        }
        
        return $file_url;
    }

    /**
     * Get Cart Item from Session
     */
    public function get_cart_item_from_session( $cart_item, $values ) {
        if ( isset( $values['personaliseit_data'] ) ) {
            $cart_item['personaliseit_data'] = $values['personaliseit_data'];
            $cart_item['personaliseit_unique_key'] = isset($values['personaliseit_unique_key']) ? $values['personaliseit_unique_key'] : '';
        }
        return $cart_item;
    }

    /**
     * Filter Cart Item Thumbnail
     */
    public function filter_cart_item_thumbnail( $thumbnail, $cart_item, $cart_item_key ) {
        if ( isset( $cart_item['personaliseit_data']['previewImage'] ) && ! empty( $cart_item['personaliseit_data']['previewImage'] ) ) {
            $img_src = $cart_item['personaliseit_data']['previewImage'];
            return '<img src="' . esc_url( $img_src ) . '" class="attachment-woocommerce_thumbnail size-woocommerce_thumbnail" alt="Personalized Product" />';
        }
        return $thumbnail;
    }

    /**
     * Filter Store API Cart Item (For WC Blocks)
     */
    public function filter_store_api_cart_item( $item_data, $cart_item, $cart_item_key ) {
        if ( isset( $cart_item['personaliseit_data']['previewImage'] ) && ! empty( $cart_item['personaliseit_data']['previewImage'] ) ) {
            $img_src = $cart_item['personaliseit_data']['previewImage'];
            
            $item_data['images'] = [
                [
                    'id' => 0,
                    'src' => esc_url( $img_src ),
                    'thumbnail' => esc_url( $img_src ),
                    'srcset' => esc_url( $img_src ) . ' 1x',
                    'sizes' => 'auto',
                    'name' => $item_data['name'],
                    'alt' => $item_data['name'],
                ]
            ];
        }
        return $item_data;
    }

    /**
     * Filter Blocks Cart Item Data (Legacy Blocks)
     */
    public function filter_blocks_cart_item_data( $item_data, $cart_item ) {
        if ( isset( $cart_item['personaliseit_data']['previewImage'] ) && ! empty( $cart_item['personaliseit_data']['previewImage'] ) ) {
            $img_src = $cart_item['personaliseit_data']['previewImage'];

            $item_data['images'] = [
                [
                    'id' => 0,
                    'src' => esc_url( $img_src ),
                    'thumbnail' => esc_url( $img_src ),
                    'srcset' => esc_url( $img_src ) . ' 1x',
                    'sizes' => 'auto',
                    'name' => isset($item_data['name']) ? $item_data['name'] : '',
                    'alt' => isset($item_data['name']) ? $item_data['name'] : '',
                ]
            ];
        }
        return $item_data;
    }

    /**
     * Filter Store API Cart Item Images (WC 9.6+)
     */
    public function filter_store_api_images( $images, $cart_item, $cart_item_key ) {
        if ( isset( $cart_item['personaliseit_data']['previewImage'] ) && ! empty( $cart_item['personaliseit_data']['previewImage'] ) ) {
             $img_src = $cart_item['personaliseit_data']['previewImage'];
             return [
                (object) [
                    'id' => 0,
                    'src' => esc_url( $img_src ),
                    'thumbnail' => esc_url( $img_src ),
                    'srcset' => esc_url( $img_src ) . ' 1x',
                    'sizes' => 'auto',
                    'name' => isset($cart_item['data']) ? $cart_item['data']->get_name() : '',
                    'alt' => isset($cart_item['data']) ? $cart_item['data']->get_name() : 'Personalized Product',
                ]
             ];
        }
        return $images;
    }
}
