<?php
namespace PersonaliseIt\Services;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Proof Generator Service
 * Handles asynchronous PDF generation via Action Scheduler.
 */
class ProofGenerator {

    const ACTION_HOOK = 'personaliseit_generate_proof_async';

    /**
     * Init hooks
     */
    public static function init() {
        add_action( self::ACTION_HOOK, [ __CLASS__, 'process_proof_generation' ], 10, 3 );
        add_action( 'woocommerce_payment_complete', [ __CLASS__, 'schedule_order_proofs' ] );
    }

    /**
     * Schedule proof generation for an order
     * 
     * @param int $order_id
     */
    public static function schedule_order_proofs( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) return;

        foreach ( $order->get_items() as $item ) {
            // Check for personalisation data
            $has_design = $item->get_meta( '_personaliseit_has_design' ) || $item->get_meta( '_personaliseit_data' );
            
            if ( $has_design ) {
                // Schedule the action
                if ( function_exists( 'as_schedule_single_action' ) ) {
                    as_schedule_single_action( time(), self::ACTION_HOOK, [ $order_id, $item->get_id(), $item->get_name() ], 'personaliseit_proofs' );
                } else {
                    // Fallback to WP Cron if Action Scheduler is not available
                    wp_schedule_single_event( time(), self::ACTION_HOOK, [ $order_id, $item->get_id(), $item->get_name() ] );
                }
            }
        }
    }

    /**
     * Process the Async Generation Action
     * 
     * @param int $order_id
     * @param int $item_id
     * @param string $product_name
     */
    public static function process_proof_generation( $order_id, $item_id, $product_name ) {
        $data = null;

        // Try getting from DesignTable first
        if ( class_exists( '\PersonaliseIt\Database\DesignTable' ) ) {
            $design_row = \PersonaliseIt\Database\DesignTable::get_item_design( $order_id, $item_id );
            if ( $design_row ) {
                $data = json_decode( $design_row->design_data, true );
            }
        }

        // Fallback to meta
        if ( ! $data ) {
            $item = new \WC_Order_Item_Product( $item_id );
            $raw_data = $item->get_meta( '_personaliseit_data' );
            if ( $raw_data ) {
                $data = is_string( $raw_data ) ? json_decode( $raw_data, true ) : $raw_data;
            }
        }

        if ( empty( $data ) || empty( $data['previewImage'] ) ) {
            return;
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) return;

        $order_info = [
            'order_id'   => $order_id,
            'order_date' => $order->get_date_created()->format( 'Y-m-d H:i' ),
            'customer'   => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
        ];

        try {
            $proof_url = self::create_pdf( $data['previewImage'], $product_name, $order_info );
            
            // Save proof URL to order item meta
            $item = $order->get_item( $item_id );
            if ( $item ) {
                $item->update_meta_data( '_personaliseit_proof_url', $proof_url );
                $item->save();
            }
        } catch ( \Exception $e ) {
            error_log( 'PersonaliseIt Proof Error: ' . $e->getMessage() );
        }
    }

    /**
     * Create PDF (Ported from ProofController)
     */
    private static function create_pdf( $image_data, $product_name, $order_info = null ) {
        // Decode base64 if needed
        if ( strpos( $image_data, 'data:image' ) === 0 ) {
            $parts = explode( ',', $image_data );
            $image_binary = base64_decode( $parts[1] );
        } else {
            $response = wp_remote_get( $image_data );
            if ( is_wp_error( $response ) ) {
                throw new \Exception( 'Failed to download image.' );
            }
            $image_binary = wp_remote_retrieve_body( $response );
        }
        
        $image = imagecreatefromstring( $image_binary );
        if ( ! $image ) {
            throw new \Exception( 'Invalid image data.' );
        }
        
        $img_width = imagesx( $image );
        $img_height = imagesy( $image );
        
        // A4 PDF Dimensions
        $pdf_width = 595;
        $pdf_height = 842;
        $margin = 40;
        $content_width = $pdf_width - ( $margin * 2 );
        
        $scale = min( $content_width / $img_width, ( $pdf_height - 200 ) / $img_height );
        $scaled_width = $img_width * $scale;
        $scaled_height = $img_height * $scale;
        $img_x = $margin + ( $content_width - $scaled_width ) / 2;
        $img_y = 120;
        
        $pdf_image = imagecreatetruecolor( $pdf_width, $pdf_height );
        $white = imagecolorallocate( $pdf_image, 255, 255, 255 );
        $black = imagecolorallocate( $pdf_image, 0, 0, 0 );
        $gray = imagecolorallocate( $pdf_image, 128, 128, 128 );
        imagefill( $pdf_image, 0, 0, $white );
        
        imagestring( $pdf_image, 5, $margin, 30, 'PERSONALISATION PROOF', $black );
        imagestring( $pdf_image, 3, $margin, 55, 'Generated: ' . date( 'Y-m-d H:i:s' ), $gray );
        imagestring( $pdf_image, 4, $margin, 80, $product_name, $black );
        
        imagerectangle( $pdf_image, (int)$img_x - 2, (int)$img_y - 2, (int)($img_x + $scaled_width + 2), (int)($img_y + $scaled_height + 2), $gray );
        
        imagecopyresampled( 
            $pdf_image, $image, (int)$img_x, (int)$img_y, 0, 0, 
            (int)$scaled_width, (int)$scaled_height, $img_width, $img_height 
        );
        
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
        
        imagestring( $pdf_image, 2, $margin, $pdf_height - 30, 'This is a digital proof. Colors may vary in production.', $gray );
        
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
