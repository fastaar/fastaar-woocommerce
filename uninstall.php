<?php
/**
 * Fires when the plugin is deleted through the WordPress admin.
 *
 * @package Fastaar_Pay
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'woocommerce_fastaar_settings' );
