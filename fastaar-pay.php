<?php
/**
 * Plugin Name: Fastaar Pay
 * Plugin URI: https://github.com/fastaar/fastaar-woocommerce
 * Description: Accept bKash, Nagad, Rocket, and Upay payments on your WooCommerce store using Fastaar.
 * Version: 1.2.4
 * Author: Fastaar
 * Author URI: https://fastaar.com
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * Text Domain: fastaar-pay
 * Requires PHP: 8.1
 * Requires at least: 6.0
 * Tested up to: 7.0
 * WC requires at least: 7.0
 * WC tested up to: 10.9
 */

defined( 'ABSPATH' ) || exit;

define( 'FASTAAR_PAY_PLUGIN_FILE', __FILE__ );
define( 'FASTAAR_PAY_VERSION', '1.2.4' );

/**
 * Initialize Fastaar WooCommerce Payment Gateway.
 */
function woocommerce_fastaar_init() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', 'woocommerce_fastaar_missing_wc_notice' );
        return;
    }

    // Require our includes
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-fastaar-webhook-validator.php';
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-fastaar-api-client.php';
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-fastaar-wc-gateway.php';

    // Register the gateway class
    add_filter( 'woocommerce_payment_gateways', 'woocommerce_fastaar_add_gateway' );
}
add_action( 'plugins_loaded', 'woocommerce_fastaar_init' );

/**
 * Add Fastaar Gateway to the list of available WooCommerce gateways.
 *
 * @param array $gateways
 * @return array
 */
function woocommerce_fastaar_add_gateway( $gateways ) {
    $gateways[] = 'Fastaar_WC_Gateway';
    return $gateways;
}

/**
 * Declare compatibility with WooCommerce features this plugin already supports.
 * Cart & Checkout blocks compatibility is what actually enables the gateway to
 * register itself on the block-based checkout (see woocommerce_fastaar_register_blocks_support()).
 */
add_action(
    'before_woocommerce_init',
    function () {
        if ( ! class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
            return;
        }

        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
);

/**
 * Register the Fastaar gateway with the block-based Cart & Checkout.
 */
add_action(
    'woocommerce_blocks_payment_method_type_registration',
    function ( \Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
        if ( ! class_exists( \Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType::class ) ) {
            return;
        }

        require_once plugin_dir_path( __FILE__ ) . 'includes/class-fastaar-blocks-support.php';

        $payment_method_registry->register( new Fastaar_Blocks_Support() );
    }
);

/**
 * Display admin notice if WooCommerce is missing.
 */
function woocommerce_fastaar_missing_wc_notice() {
    ?>
    <div class="error notice">
        <p><?php esc_html_e( 'Fastaar Payment Gateway requires WooCommerce to be installed and active.', 'fastaar-pay' ); ?></p>
    </div>
    <?php
}

/**
 * Add settings action link to plugins page.
 *
 * @param array $links
 * @return array
 */
function woocommerce_fastaar_action_links( $links ) {
    $settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=fastaar' ) ) . '">' . esc_html__( 'Settings', 'fastaar-pay' ) . '</a>';
    array_unshift( $links, $settings_link );
    return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'woocommerce_fastaar_action_links' );
