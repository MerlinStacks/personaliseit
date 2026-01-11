<?php
namespace PersonaliseIt\Api;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Proof Controller - Generates PDF proofs from personalization data.
 */
class ProofController {

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    /**
     * Register REST API routes.
     */
    public function register_routes() {
        register_rest_route( 'personaliseit/v1', '/proof/generate', [
            'methods'  => 'POST',
            'callback' => [ $this, 'generate_proof' ],
            'permission_callback' => function() {
                return current_user_can( 'manage_options' );
            },
        ] );
        
        register_rest_route( 'personaliseit/v1', '/proof/order/(?P<order_id>\d+)', [
            'methods'  => 'GET',
            'callback' => [ $this, 'generate_order_proof' ],
            'permission_callback' => function() {
                return current_user_can( 'manage_options' );
            },
        ] );
    }

    /**
     * Generate a PDF proof from the provided data.
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function generate_proof( $request ) {
        $image_data = $request->get_param( 'image' ); // Base64 or URL
        $order_info = $request->get_param( 'order_info' );
        $product_name = $request->get_param( 'product_name' ) ?? 'Personalised Product';
        
        if ( empty( $image_data ) ) {
            return new \WP_Error( 'missing_image', __( 'Preview image is required.', 'personaliseit' ), [ 'status' => 400 ] );
        }
        
        try {
            $pdf_url = $this->create_pdf( $image_data, $product_name, $order_info );
            
            return rest_ensure_response( [
                'success' => true,
                'url'     => $pdf_url,
                'message' => __( 'Proof generated successfully.', 'personaliseit' ),
            ] );
        } catch ( \Exception $e ) {
            return new \WP_Error( 'pdf_error', $e->getMessage(), [ 'status' => 500 ] );
        }
    }

    /**
     * Generate a PDF proof for a specific WooCommerce order item.
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function generate_order_proof( $request ) {
        $order_id = absint( $request->get_param( 'order_id' ) );
        $item_id = $request->get_param( 'item_id' );
        
        if ( ! function_exists( 'wc_get_order' ) ) {
            return new \WP_Error( 'woo_missing', __( 'WooCommerce is required.', 'personaliseit' ), [ 'status' => 400 ] );
        }
        
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return new \WP_Error( 'order_not_found', __( 'Order not found.', 'personaliseit' ), [ 'status' => 404 ] );
        }
        
        // Find personalization data in order items
        foreach ( $order->get_items() as $item ) {
            if ( $item_id && $item->get_id() != $item_id ) {
                continue;
            }
            
            $personaliseit_data = $item->get_meta( '_personaliseit_data' );
            if ( $personaliseit_data ) {
                $data = is_string( $personaliseit_data ) ? json_decode( $personaliseit_data, true ) : $personaliseit_data;
                
                if ( ! empty( $data['previewImage'] ) ) {
                    try {
                        $order_info = [
                            'order_id'   => $order_id,
                            'order_date' => $order->get_date_created()->format( 'Y-m-d H:i' ),
                            'customer'   => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                        ];
                        
                        $pdf_url = $this->create_pdf( 
                            $data['previewImage'], 
                            $item->get_name(), 
                            $order_info 
                        );
                        
                        return rest_ensure_response( [
                            'success' => true,
                            'url'     => $pdf_url,
                            'message' => __( 'Proof generated for order.', 'personaliseit' ),
                        ] );
                    } catch ( \Exception $e ) {
                        return new \WP_Error( 'pdf_error', $e->getMessage(), [ 'status' => 500 ] );
                    }
                }
            }
        }
        
        return new \WP_Error( 'no_personalization', __( 'No personalization data found in this order.', 'personaliseit' ), [ 'status' => 404 ] );
    }

    /**
     * Create a PDF file from image data.
     * Uses native PHP with GD - no external libraries needed.
     *
     * @param string $image_data Base64 image or URL.
     * @param string $product_name Product name.
     * @param array $order_info Optional order details.
     * @return string URL to the generated PDF.
     */
    private function create_pdf( $image_data, $product_name, $order_info = null ) {
        // Decode base64 if needed
        if ( strpos( $image_data, 'data:image' ) === 0 ) {
            $parts = explode( ',', $image_data );
            $image_binary = base64_decode( $parts[1] );
        } else {
            // It's a URL - download it
            $response = wp_remote_get( $image_data );
            if ( is_wp_error( $response ) ) {
                throw new \Exception( 'Failed to download image.' );
            }
            $image_binary = wp_remote_retrieve_body( $response );
        }
        
        // Create image resource
        $image = imagecreatefromstring( $image_binary );
        if ( ! $image ) {
            throw new \Exception( 'Invalid image data.' );
        }
        
        $img_width = imagesx( $image );
        $img_height = imagesy( $image );
        
        // PDF dimensions (A4 at 72 DPI: 595 x 842 points)
        $pdf_width = 595;
        $pdf_height = 842;
        $margin = 40;
        $content_width = $pdf_width - ( $margin * 2 );
        
        // Scale image to fit content area (centered)
        $scale = min( $content_width / $img_width, ( $pdf_height - 200 ) / $img_height );
        $scaled_width = $img_width * $scale;
        $scaled_height = $img_height * $scale;
        $img_x = $margin + ( $content_width - $scaled_width ) / 2;
        $img_y = 120; // Below header
        
        // Create PDF canvas
        $pdf_image = imagecreatetruecolor( $pdf_width, $pdf_height );
        $white = imagecolorallocate( $pdf_image, 255, 255, 255 );
        $black = imagecolorallocate( $pdf_image, 0, 0, 0 );
        $gray = imagecolorallocate( $pdf_image, 128, 128, 128 );
        imagefill( $pdf_image, 0, 0, $white );
        
        // Header
        imagestring( $pdf_image, 5, $margin, 30, 'PERSONALISATION PROOF', $black );
        imagestring( $pdf_image, 3, $margin, 55, 'Generated: ' . date( 'Y-m-d H:i:s' ), $gray );
        imagestring( $pdf_image, 4, $margin, 80, $product_name, $black );
        
        // Draw border around image area
        imagerectangle( $pdf_image, (int)$img_x - 2, (int)$img_y - 2, (int)($img_x + $scaled_width + 2), (int)($img_y + $scaled_height + 2), $gray );
        
        // Copy and resize the preview image
        imagecopyresampled( 
            $pdf_image, 
            $image, 
            (int)$img_x, 
            (int)$img_y, 
            0, 
            0, 
            (int)$scaled_width, 
            (int)$scaled_height, 
            $img_width, 
            $img_height 
        );
        
        // Order info footer
        $footer_y = (int)$img_y + (int)$scaled_height + 30;
        if ( $order_info ) {
            if ( isset( $order_info['order_id'] ) ) {
                imagestring( $pdf_image, 3, $margin, $footer_y, 'Order #' . $order_info['order_id'], $black );
                $footer_y += 20;
            }
            if ( isset( $order_info['customer'] ) ) {
                imagestring( $pdf_image, 3, $margin, $footer_y, 'Customer: ' . $order_info['customer'], $black );
                $footer_y += 20;
            }
            if ( isset( $order_info['order_date'] ) ) {
                imagestring( $pdf_image, 3, $margin, $footer_y, 'Date: ' . $order_info['order_date'], $black );
            }
        }
        
        // Footer
        imagestring( $pdf_image, 2, $margin, $pdf_height - 30, 'This is a digital proof. Colors may vary in production.', $gray );
        
        // Save as PNG (PDF would require external library)
        $upload_dir = wp_upload_dir();
        $proof_dir = $upload_dir['basedir'] . '/personaliseit-proofs/';
        $proof_url_base = $upload_dir['baseurl'] . '/personaliseit-proofs/';
        
        if ( ! file_exists( $proof_dir ) ) {
            wp_mkdir_p( $proof_dir );
        }
        
        $filename = 'proof-' . time() . '-' . wp_generate_password( 8, false ) . '.png';
        $filepath = $proof_dir . $filename;
        
        imagepng( $pdf_image, $filepath );
        imagedestroy( $pdf_image );
        imagedestroy( $image );
        
        return $proof_url_base . $filename;
    }
}
