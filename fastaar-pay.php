<?php
/**
 * Plugin Name: Fastaar Payment Gateway for WooCommerce
 * Plugin URI: https://fastaar.com
 * Description: Accept bKash, Nagad, Rocket, and Upay payments on your WooCommerce store using Fastaar.
 * Version: 1.0.0
 * Author: Fastaar
 * Author URI: https://fastaar.com
 * License: MIT
 * Text Domain: fastaar-pay
 * Requires PHP: 8.1
 * Requires at least: 6.0
 * WC requires at least: 7.0
 */

defined( 'ABSPATH' ) || exit;

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
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-wc-gateway-fastaar.php';

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
    $gateways[] = 'WC_Gateway_Fastaar';
    return $gateways;
}

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
