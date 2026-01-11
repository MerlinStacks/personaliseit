<?php
namespace PersonaliseIt\Database;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * DesignTable Class
 * Handles the custom database table for personalisation designs.
 */
class DesignTable {

    /**
     * Table name without prefix
     */
    const TABLE_NAME = 'personaliseit_designs';

    /**
     * Get full table name
     */
    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_NAME;
    }

    /**
     * Install the database table
     */
    public static function install() {
        global $wpdb;

        $table_name = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            order_id bigint(20) NOT NULL,
            item_id varchar(100) DEFAULT '' NOT NULL,
            design_data longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY order_id (order_id),
            KEY item_id (item_id)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }

    /**
     * Save design data
     * 
     * @param int $order_id
     * @param array $design_data
     * @param string $item_key Optional item key if needed for granular saving
     * @return int|false Insert ID on success, false on failure
     */
    public static function save( $order_id, $design_data, $item_key = '' ) {
        global $wpdb;

        $table_name = self::get_table_name();
        $data = is_string( $design_data ) ? $design_data : json_encode( $design_data );

        // Check if exists
        $existing = $wpdb->get_row( $wpdb->prepare( 
            "SELECT id FROM $table_name WHERE order_id = %d AND item_id = %s", 
            $order_id, 
            $item_key 
        ) );

        if ( $existing ) {
            $updated = $wpdb->update( 
                $table_name, 
                [ 'design_data' => $data ], 
                [ 'id' => $existing->id ], 
                [ '%s' ], 
                [ '%d' ] 
            );
            return $existing->id;
        } else {
            $inserted = $wpdb->insert( 
                $table_name, 
                [ 
                    'order_id' => $order_id, 
                    'item_id' => $item_key,
                    'design_data' => $data 
                ], 
                [ '%d', '%s', '%s' ] 
            );
            return $inserted ? $wpdb->insert_id : false;
        }
    }

    /**
     * Get design data by order ID
     * 
     * @param int $order_id
     * @return array Array of design objects
     */
    public static function get_by_order_id( $order_id ) {
        global $wpdb;
        $table_name = self::get_table_name();
        
        return $wpdb->get_results( $wpdb->prepare( 
            "SELECT * FROM $table_name WHERE order_id = %d", 
            $order_id 
        ) );
    }
    
    /**
     * Get specific design by Order ID and Item Key/ID
     * 
     * @param int $order_id
     * @param string $item_key
     * @return object|null
     */
    public static function get_item_design( $order_id, $item_key ) {
        global $wpdb;
        $table_name = self::get_table_name();
        
        return $wpdb->get_row( $wpdb->prepare( 
            "SELECT * FROM $table_name WHERE order_id = %d AND item_id = %s", 
            $order_id,
            $item_key
        ) );
    }
}
