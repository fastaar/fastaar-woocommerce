<?php
/**
 * WooCommerce Blocks (Cart & Checkout) integration for the Fastaar gateway.
 *
 * @package Fastaar_Pay
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Registers the Fastaar gateway with the block-based Cart & Checkout, so it appears
 * there just like it already does on the classic shortcode checkout.
 */
final class Fastaar_Blocks_Support extends AbstractPaymentMethodType {

    /**
     * Payment method name/id — must match Fastaar_WC_Gateway::$id.
     *
     * @var string
     */
    protected $name = 'fastaar';

    /**
     * The underlying gateway instance.
     *
     * @var Fastaar_WC_Gateway|null
     */
    private $gateway;

    /**
     * Initialize the integration, called by WooCommerce Blocks.
     */
    public function initialize() {
        $this->settings = get_option( 'woocommerce_fastaar_settings', array() );

        $gateways      = WC()->payment_gateways->payment_gateways();
        $this->gateway = isset( $gateways[ $this->name ] ) ? $gateways[ $this->name ] : null;
    }

    /**
     * Whether the payment method should be shown at checkout.
     *
     * @return bool
     */
    public function is_active() {
        return $this->gateway && $this->gateway->is_available();
    }

    /**
     * Script handles to enqueue on the block Cart & Checkout pages.
     *
     * @return string[]
     */
    public function get_payment_method_script_handles() {
        wp_register_script(
            'wc-fastaar-blocks-integration',
            plugins_url( 'assets/js/blocks/fastaar-payment-method.js', FASTAAR_PAY_PLUGIN_FILE ),
            array( 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities', 'wp-i18n' ),
            FASTAAR_PAY_VERSION,
            true
        );

        if ( function_exists( 'wp_set_script_translations' ) ) {
            wp_set_script_translations( 'wc-fastaar-blocks-integration', 'fastaar-pay' );
        }

        return array( 'wc-fastaar-blocks-integration' );
    }

    /**
     * Data made available to the frontend script via wc.wcSettings.getSetting( 'fastaar_data' ).
     *
     * @return array
     */
    public function get_payment_method_data() {
        return array(
            'title'       => $this->gateway ? $this->gateway->title : __( 'Fastaar', 'fastaar-pay' ),
            'description' => $this->gateway ? $this->gateway->description : '',
            'icon'        => $this->gateway ? $this->gateway->get_logo_url() : '',
            'supports'    => $this->gateway ? array_values( $this->gateway->supports ) : array(),
        );
    }
}
