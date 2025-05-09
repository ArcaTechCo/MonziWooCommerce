<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Verificar que la clase base existe
if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
    return;
}

require_once plugin_dir_path( __FILE__ ) . '/class-monzi-hpos-compatibility.php';

class WC_Gateway_Monzi extends WC_Payment_Gateway {

    public function __construct() {
        
        // Inicializar compatibilidad HPOS primero
        Monzi_HPOS_Compatibility::init();

        global $wp_rewrite;

        $this->id = 'monzi';
        $this->icon = plugin_dir_url(__FILE__) . '../assets/img/logo.png';
        $this->has_fields = true;
        $this->method_title = 'Monzi Gateway';
        $this->method_description = 'Pasarela de pagos Monzi para WooCommerce';
        $this->supports = array(
            'products',
            'refunds',
            'tokenization',
            'add_payment_method'
        );

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_monzi_scripts'), 999);
        add_filter('query_vars', array($this, 'add_query_vars'), 0);
        add_action('parse_request', array($this, 'handle_webhook'), 10);
        
        // Añadir hook para mostrar en el carrito
        add_action( 'woocommerce_review_order_before_payment', array( $this, 'display_payment_method' ) );
        add_action( 'woocommerce_after_cart_totals', array( $this, 'display_payment_method' ) );
    }

    public function display_payment_method() {
        
        if ( $this->enabled === 'yes' ) {
            echo '<div class="payment_method_monzi">';
            echo '<h3>' . esc_html( $this->title ) . '</h3>';
            echo '<p>' . esc_html( $this->description ) . '</p>';
            echo '</div>';
        }
    }

    public function monzi_add_rewrite_rule() {

        add_rewrite_rule('^monzi-webhook/?$', 'index.php?monzi_webhook=1', 'top');
        global $wp_rewrite;
        $wp_rewrite->flush_rules(false); 
    }
    
    public function add_query_vars($vars) {
        $vars[] = 'monzi_webhook';
        return $vars;
    }
    
    public function handle_webhook($wp) {
        if (isset($wp->query_vars['monzi_webhook'])) {
            
            // Capturar el cuerpo del request
            $payload = json_decode(file_get_contents('php://input'), true);
            if ($payload === null) {
                http_response_code(400);
                echo 'Invalid payload';
                exit;
            }
            
            // Llamar al método para procesar el webhook
            $this->process_webhook($payload);
        }
    }
      
    
    protected function process_webhook($payload) {

        $order_id = $payload['externalId'];
        $order = wc_get_order($order_id);
        if (!$order) {
            http_response_code(404);
            echo 'Order not found';
            exit;
        }
    
        if ($order->has_status(['completed', 'cancelled'])) {
            http_response_code(200);
            echo 'No action needed';
            exit;
        }
        
        $status = $payload['statusUuid'];
        $received = $payload['receivedUsd'];
        
        switch ($status) {
            case '1':
                $order->add_order_note('Pago creado, esperando realización en Monzi.');
                break;
            case '2':
                $order->add_order_note('Esperando que el pago sea realizado por el usuario.');
                break;
            case '3':
                $order->add_order_note('Pago recibido parcialmente, no se ha pagado la totalidad. $'. $received);
                break;
            case '4':
                $order->add_order_note('Pago cancelado por el usuario o el banco.');
                $order->update_status('cancelled', 'Pago cancelado por el usuario o el banco.');
                break;
            case '5':
                $order->add_order_note('Pago completado mediante Monzi. $'. $received);
                $order->payment_complete();
                break;
            case '6':
                $order->add_order_note('Pago expirado, por favor intente nuevamente.');
                break;
            default:
                http_response_code(400);
                echo "Status not recognized";
                exit;
        }
    
        $order->save();
        http_response_code(200);
        echo 'Webhook processed';
    }
    

    public function enqueue_monzi_scripts() {
        wp_enqueue_script('monzi-checkout-js', plugin_dir_url(__FILE__) . '../assets/js/monzi-checkout.js', array('jquery'), '1.0', true);

        wp_localize_script('monzi-checkout-js', 'monzi_params', array(
            'checkoutType' => $this->get_option('checkout_type'),
            'redirectUrl' => ''
        ));
        if (is_checkout()) {
            wp_enqueue_script('monzi-checkout-js');
        }
    }
    

    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'       => 'Activar/Desactivar',
                'label'       => 'Activar Monzi Gateway',
                'type'        => 'checkbox',
                'description' => '',
                'default'     => 'no'
            ),
            'title' => array(
                'title'       => 'Título',
                'type'        => 'text',
                'description' => 'Esto controla el título que el usuario ve durante el checkout.',
                'default'     => 'Pagar con criptomonedas',
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => 'Descripción',
                'type'        => 'textarea',
                'description' => 'Esto controla la descripción que el usuario ve durante el checkout.',
                'default'     => 'Paga con USDT, BNB, ETH o muchas más criptos mediante Monzi.',
            ),
            'api_key' => array(
                'title'       => 'API Key',
                'type'        => 'text'
            ),
            'checkout_type' => array(
                'title'       => 'Tipo de Checkout',
                'type'        => 'select',
                'description' => 'En estos momentos solo se encuentra disponible el Standard Checkout.',
                'default'     => 'standard',
                'desc_tip'    => true,
                'options'     => array(
                    /* 'onpage'   => 'Onpage Checkout', */
                    'standard' => 'Standard Checkout'
                )
            ),
        );
    }

    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        $items = array();
        $total_quantity = 0;

        $billing_email = $order->get_billing_email();
        $billing_first_name = $order->get_billing_first_name();
        $billing_last_name = $order->get_billing_last_name();
        $full_name = $billing_first_name . ' ' . $billing_last_name;

        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            $items[] = array(
                'name' => $product->get_name(),
                'quantity' => $item->get_quantity(),
                'price' => round($item->get_total() / $item->get_quantity(), 2),
                'total' => $item->get_total()
            );
            $total_quantity += $item->get_total();
        }

        $body = array(
            'customerNames'=> $full_name,
            'customerEmail' => $billing_email,
            'externalId' => strval($order_id),
            'items' => $items,
            'amount' => strval($total_quantity)
        );

        $response = $this->create_monzi_transaction($body);
        if (is_wp_error($response) || $response['response']['code'] != 200) {
            wc_add_notice('Error al procesar el pago: ' . $response->get_error_message(), 'error');
            return;
        }

        $transaction_id = json_decode($response['body'])->uuid;
        $checkout_url = $this->get_option('checkout_type') == 'onpage' ?
            "https://pay.monzi.co/pay/onpage/$transaction_id" :
            "https://pay.monzi.co/pay/standard/$transaction_id";

        return array(
            'result'   => 'success',
            'redirect' => $checkout_url
        );
    }

    private function create_monzi_transaction($body) {
        $api_url = 'https://api.monzi.co/api/payment/plugin';
        $request = new WP_Http();
        $response = $request->post($api_url, array(
            'body' => json_encode($body),
            'headers' => array(
                'Content-Type' => 'application/json',
                'X-API-KEY' => $this->get_option('api_key')
            ),
            'timeout' => 45
        ));
        return $response;
    }
}
