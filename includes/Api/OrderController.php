<?php
namespace PersonaliseIt\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OrderController {
    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        register_rest_route( 'personaliseit/v1', '/order-item/(?P<order_id>\d+)/(?P<item_id>\d+)', [
            'methods' => 'GET',
            'callback' => [ $this, 'get_item_data' ],
            'permission_callback' => function() {
                return current_user_can( 'edit_shop_orders' );
            },
        ] );
    }

    public function get_item_data( $request ) {
        $order_id = $request->get_param('order_id');
        $item_id = $request->get_param('item_id');
        
        $order = wc_get_order( $order_id );
        if ( ! $order ) return new \WP_Error( 'not_found', 'Order not found', ['status'=>404] );

        $item = $order->get_item( $item_id );
        if ( ! $item ) return new \WP_Error( 'not_found', 'Item not found', ['status'=>404] );

        $product_id = $item->get_product_id();
        $user_data = $item->get_meta( '_personaliseit_data' );
        
        // Handle case where user inputs might be nested or stored differently?
        // In CartIntegration, we save $values['personaliseit_data'] directly.
        // It's usually an array map of layer_id => value.
        // FrontendCanvas expects { userInputs: { id: val } } and { config } and { layers }.
        
        if ( is_string( $user_data ) ) $user_data = json_decode( $user_data, true );

        // We also need the Product Config to render it
        $config = get_post_meta( $product_id, '_personaliseit_config', true );
        
        $product = wc_get_product( $product_id );
        $image_id = $product->get_image_id();
        $image_url = $image_id ? wp_get_attachment_url( $image_id ) : '';

        return rest_ensure_response( [
            'product_id' => $product_id,
            'productImage' => $image_url,
            'config' => $config,
            'userInputs' => $user_data,
        ] );
    }
}
