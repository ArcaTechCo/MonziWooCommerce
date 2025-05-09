<?php
/**
 * Plugin Name: Monzi Gateway - WooCommerce
 * Description: Monzi Ecosistem es el ecosistema de pagos más completos de Criptomonedas y Fiat. Aceptamos más de 20+ criptos y tarjetas de crédito.
 * Version: 1.0.0
 * Author: Monzi
 * Author URI: https://monzi.co
 * Tested up to: 6.4
 * WC requires at least: 7.4
 * WC tested up to: 8.3
 * Text Domain: woo-monzi-gateway
 * Domain Path: /i18n/languages/
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Verificar que WooCommerce está activo
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    return;
}

// Inicializar el gateway después de que WooCommerce esté cargado
add_action( 'plugins_loaded', 'init_monzi_gateway' );

function init_monzi_gateway() {
    // Verificar que la clase WC_Payment_Gateway existe
    if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
        return;
    }

    // Incluir la clase del gateway
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-wc-gateway-monzi.php';

    // Añadir el gateway a WooCommerce
    add_filter( 'woocommerce_payment_gateways', 'add_monzi_gateway_class' );
}

function add_monzi_gateway_class( $methods ) {
    $methods[] = 'WC_Gateway_Monzi';
    return $methods;
}

// Añadir enlace de configuración
add_filter( "plugin_action_links_" . plugin_basename( __FILE__ ), 'monzi_add_settings_link' );
function monzi_add_settings_link( $links ) {
    $settings_link = '<a href="admin.php?page=wc-settings&tab=checkout&section=monzi">' . __( 'Configuración', 'woo-monzi-gateway' ) . '</a>';
    array_unshift( $links, $settings_link );
    return $links;
}

// Registrar hooks de activación/desactivación
register_activation_hook( __FILE__, 'monzi_activate' );
function monzi_activate() {
    monzi_add_rewrite_rules();
    flush_rewrite_rules();
}

register_deactivation_hook( __FILE__, 'monzi_deactivate' );
function monzi_deactivate() {
    flush_rewrite_rules();
}

function monzi_add_rewrite_rules() {
    add_rewrite_rule( '^monzi-webhook/?$', 'index.php?monzi_webhook=1', 'top' );
}

add_action( 'init', 'monzi_add_rewrite_rules' );

add_action( 'before_woocommerce_init', function() {
    if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
    }
});

add_action(
    'woocommerce_blocks_payment_method_type_registration',
    function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
        require_once plugin_dir_path( __FILE__ ) . '/blocks/class-wc-gateway-monzi-blocks.php';
        $payment_method_registry->register( new WC_Gateway_Monzi_Blocks );
    }
);

