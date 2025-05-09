<?php
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class WC_Gateway_Monzi_Blocks extends AbstractPaymentMethodType {
    protected $name = 'monzi';

    public function initialize() {
        $this->settings = get_option( 'woocommerce_monzi_settings', [] );
    }

    public function get_payment_method_script_handles() {
        return [ 'monzi-checkout-js' ];
    }

    public function get_payment_method_data() {
        return [
            'title'       => $this->settings['title'] ?? 'Pagar con criptomonedas',
            'description' => $this->settings['description'] ?? '',
            // Otros datos necesarios...
        ];
    }
}
