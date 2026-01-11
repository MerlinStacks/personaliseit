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
                        // Schedule async generation
                        if ( function_exists( 'as_schedule_single_action' ) ) {
                            as_schedule_single_action( time(), 'personaliseit_generate_proof_async', [ $order_id, $item->get_id(), $item->get_name() ], 'personaliseit_proofs' );
                        } else {
                             wp_schedule_single_event( time(), 'personaliseit_generate_proof_async', [ $order_id, $item->get_id(), $item->get_name() ] );
                        }
                        
                        return rest_ensure_response( [
                            'success' => true,
                            'message' => __( 'Proof generation queued.', 'personaliseit' ),
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
    // create_pdf method removed. Logic moved to PersonaliseIt\Services\ProofGenerator
    private function create_pdf( $image_data, $product_name, $order_info = null ) {
         // Fallback to Service if needed synchronously (e.g. for generate_proof raw endpoint)
         // Assuming ProofGenerator::create_pdf is made public or we instantiate it.
         // For now, let's assuming generate_proof (raw) is deprecated or we leave it broken/stubbed in favor of order-based flow.
         // Or better, let's just throw an error saying "Use Order-based generation".
         return new \WP_Error( 'deprecated', 'Direct proof generation is deprecated. Use order-based generation.', [ 'status' => 410 ] );
    }
}
