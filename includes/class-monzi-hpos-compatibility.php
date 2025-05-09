<?php
/**
 * HPOS Compatibility
 *
 * @package Monzi
 */

defined( 'ABSPATH' ) || exit;

/**
 * HPOS Compatibility Class
 */
class Monzi_HPOS_Compatibility {
    /**
     * Initialize the compatibility class
     */
    public static function init() {
        // Declarar compatibilidad con HPOS
        add_action( 'before_woocommerce_init', function() {
            if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 
                    'custom_order_tables', 
                    dirname( dirname( __FILE__ ) ) . '/wc-monzi-gateway.php', 
                    true 
                );
            }
        });
    }
}