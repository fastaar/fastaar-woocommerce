<?php
/**
 * Fastaar Payment Gateway for WooCommerce
 *
 * @package Fastaar_Pay
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_Gateway_Fastaar
 *
 * Extends WC_Payment_Gateway to integrate Fastaar.
 */
class WC_Gateway_Fastaar extends WC_Payment_Gateway {

    /**
     * Logger instance.
     *
     * @var WC_Logger|null
     */
    private $logger = null;

    /**
     * API Key.
     *
     * @var string
     */
    private $api_key;

    /**
     * Webhook Secret.
     *
     * @var string
     */
    private $webhook_secret;

    /**
     * Sandbox mode.
     *
     * @var bool
     */
    private $sandbox_mode;

    /**
     * Logging enabled.
     *
     * @var bool
     */
    private $logging_enabled;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->id                 = 'fastaar';
        $this->has_fields         = false;
        $this->method_title       = __( 'Fastaar', 'fastaar-pay' );
        $this->method_description = __( 'Accept bKash, Nagad, Rocket, and Upay payments via Fastaar.', 'fastaar-pay' );

        // Load settings
        $this->init_form_fields();
        $this->init_settings();

        // Define user setting variables
        $this->title           = $this->get_option( 'title' );
        $this->description     = $this->get_option( 'description' );
        $this->api_key        = $this->get_option( 'api_key' );
        $this->webhook_secret = $this->get_option( 'webhook_secret' );
        $this->sandbox_mode    = 'yes' === $this->get_option( 'sandbox_mode' );
        $this->logging_enabled = 'yes' === $this->get_option( 'logging' );

        // Initialize logger
        if ( $this->logging_enabled ) {
            $this->logger = wc_get_logger();
        }

        // Actions
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

        // Webhook registration hooks
        add_action( 'woocommerce_api_wc_gateway_fastaar', array( $this, 'handle_webhook' ) );
        add_action( 'woocommerce_api_fastaar', array( $this, 'handle_webhook' ) );
    }

    /**
     * Initialize form fields.
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => __( 'Enable/Disable', 'fastaar-pay' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable Fastaar Payment Gateway', 'fastaar-pay' ),
                'default' => 'no',
            ),
            'title' => array(
                'title'       => __( 'Title', 'fastaar-pay' ),
                'type'        => 'text',
                'description' => __( 'This controls the title which the user sees during checkout.', 'fastaar-pay' ),
                'default'     => __( 'bKash, Nagad, Rocket, Upay (Fastaar)', 'fastaar-pay' ),
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __( 'Description', 'fastaar-pay' ),
                'type'        => 'textarea',
                'description' => __( 'This controls the description which the user sees during checkout.', 'fastaar-pay' ),
                'default'     => __( 'Pay securely via Fastaar payment gateway using mobile banking or cards.', 'fastaar-pay' ),
            ),
            'api_key' => array(
                'title'       => __( 'Fastaar API Key', 'fastaar-pay' ),
                'type'        => 'password',
                'description' => __( 'Enter your Fastaar merchant API Key. You can find this in your Fastaar dashboard settings.', 'fastaar-pay' ),
                'default'     => '',
            ),
            'webhook_secret' => array(
                'title'       => __( 'Webhook Secret', 'fastaar-pay' ),
                'type'        => 'password',
                'description' => __( 'Enter your Webhook signing secret. Used to verify the integrity of webhook notifications from Fastaar.', 'fastaar-pay' ),
                'default'     => '',
            ),
            'sandbox_mode' => array(
                'title'   => __( 'Sandbox Mode', 'fastaar-pay' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable Sandbox Mode', 'fastaar-pay' ),
                'default' => 'no',
            ),
            'logging' => array(
                'title'       => __( 'Debug Log', 'fastaar-pay' ),
                'type'        => 'checkbox',
                'label'   => __( 'Enable logging', 'fastaar-pay' ),
                'default' => 'no',
                'description' => sprintf(
                    /* translators: %s: Log file path */
                    __( 'Log API and webhook events. Log file: %s', 'fastaar-pay' ),
                    '<code>' . WC_Log_Handler_File::get_log_file_name( 'fastaar' ) . '</code>'
                ),
            ),
        );
    }

    /**
     * Renders settings options page.
     */
    public function admin_options() {
        ?>
        <h2><?php echo esc_html( $this->method_title ); ?></h2>
        <p><?php echo esc_html( $this->method_description ); ?></p>
        <table class="form-table">
            <?php $this->generate_settings_html(); ?>
        </table>
        <?php
    }

    /**
     * Render payment fields.
     */
    public function payment_fields() {
        if ( $this->description ) {
            echo wp_kses_post( wpautop( wptexturize( $this->description ) ) );
        }
    }

    /**
     * Write custom log entries.
     *
     * @param string $message Log message.
     * @param string $level   Log level (info, warning, error, etc.).
     */
    private function log( $message, $level = 'info' ) {
        if ( $this->logging_enabled && $this->logger ) {
            $this->logger->log( $level, $message, array( 'source' => 'fastaar' ) );
        }
    }

    /**
     * Process payment and redirect.
     *
     * @param int $order_id Order ID.
     * @return array
     */
    public function process_payment( $order_id ) {
        $order = wc_get_order( $order_id );

        if ( empty( $this->api_key ) ) {
            $msg = __( 'Fastaar payment gateway is not properly configured. API Key is missing.', 'fastaar-pay' );
            $this->log( $msg, 'error' );
            wc_add_notice( $msg, 'error' );
            return array( 'result' => 'fail' );
        }

        $client = new Fastaar_API_Client( $this->api_key );

        $params = array(
            'amount'      => $order->get_total(),
            'invoice_id'  => (string) $order->get_id(),
            'success_url' => $this->get_return_url( $order ),
            'cancel_url'  => esc_url_raw( $order->get_cancel_order_url() ),
            'metadata'    => array(
                'order_id'  => (string) $order->get_id(),
                'order_key' => $order->get_order_key(),
            ),
        );

        $this->log( 'Creating payment for Order ID: ' . $order_id . ' with parameters: ' . wp_json_encode( $params ) );

        try {
            $payment = $client->create_payment( $params );

            if ( empty( $payment['checkout_url'] ) ) {
                throw new Exception( __( 'Checkout URL not returned from Fastaar API.', 'fastaar-pay' ) );
            }

            $this->log( 'Payment created successfully. Fastaar Payment ID: ' . $payment['id'] . ', Redirecting to: ' . $payment['checkout_url'] );

            // Store payment ID in order metadata
            $order->update_meta_data( '_fastaar_payment_id', $payment['id'] );
            $order->save();

            return array(
                'result'   => 'success',
                'redirect' => $payment['checkout_url'],
            );

        } catch ( Exception $e ) {
            $this->log( 'Error creating payment: ' . $e->getMessage(), 'error' );
            wc_add_notice( sprintf( __( 'Fastaar Payment Error: %s', 'fastaar-pay' ), $e->getMessage() ), 'error' );
            return array(
                'result' => 'fail',
            );
        }
    }

    /**
     * Process Fastaar Webhook notifications.
     */
    public function handle_webhook() {
        $this->log( 'Incoming webhook notification received.' );

        $signature = '';
        if ( isset( $_SERVER['HTTP_X_FASTAAR_SIGNATURE'] ) ) {
            $signature = $_SERVER['HTTP_X_FASTAAR_SIGNATURE'];
        } elseif ( function_exists( 'getallheaders' ) ) {
            $headers = getallheaders();
            if ( isset( $headers['X-Fastaar-Signature'] ) ) {
                $signature = $headers['X-Fastaar-Signature'];
            } elseif ( isset( $headers['x-fastaar-signature'] ) ) {
                $signature = $headers['x-fastaar-signature'];
            }
        }

        $raw_body = file_get_contents( 'php://input' );

        $this->log( 'Webhook Signature: ' . $signature );
        $this->log( 'Webhook Raw Body: ' . $raw_body );

        if ( empty( $signature ) ) {
            $this->log( 'Webhook verification failed: Signature header is missing.', 'error' );
            wp_send_json_error( 'Signature header missing', 400 );
        }

        if ( empty( $this->webhook_secret ) ) {
            $this->log( 'Webhook verification failed: Webhook secret is not configured.', 'error' );
            wp_send_json_error( 'Webhook secret not configured', 500 );
        }

        if ( ! Fastaar_Webhook_Validator::verify( $this->webhook_secret, $raw_body, $signature ) ) {
            $this->log( 'Webhook verification failed: Signature mismatch or timeout.', 'error' );
            wp_send_json_error( 'Invalid webhook signature', 400 );
        }

        $payload = json_decode( $raw_body, true );

        if ( ! is_array( $payload ) ) {
            $this->log( 'Webhook verification failed: Invalid JSON payload.', 'error' );
            wp_send_json_error( 'Invalid JSON body', 400 );
        }

        $event = isset( $payload['event'] ) ? $payload['event'] : '';
        $data  = isset( $payload['data'] ) ? $payload['data'] : array();

        $this->log( 'Webhook event: ' . $event );

        if ( 'payment.completed' === $event ) {
            $order_id   = isset( $data['invoice_id'] ) ? $data['invoice_id'] : '';
            $payment_id = isset( $data['id'] ) ? $data['id'] : '';

            $this->log( 'Processing payment.completed event. Order ID: ' . $order_id . ', Payment ID: ' . $payment_id );

            if ( empty( $order_id ) ) {
                $this->log( 'Webhook error: invoice_id is missing in event payload data.', 'error' );
                wp_send_json_error( 'Missing invoice_id', 400 );
            }

            $order = wc_get_order( $order_id );

            if ( ! $order ) {
                $this->log( 'Webhook error: Order not found for ID ' . $order_id, 'error' );
                wp_send_json_error( 'Order not found', 404 );
            }

            if ( $order->is_paid() ) {
                $this->log( 'Order ' . $order_id . ' is already marked as paid. Ignoring duplicate webhook request.' );
                wp_send_json_success( 'Order already paid' );
            }

            // Complete the order payment
            $order->payment_complete( $payment_id );
            $order->add_order_note(
                sprintf(
                    /* translators: %s: Fastaar Transaction ID */
                    __( 'Fastaar payment completed. Transaction ID: %s', 'fastaar-pay' ),
                    $payment_id
                )
            );
            $order->save();

            $this->log( 'Order ' . $order_id . ' successfully marked as paid via webhook.' );
            wp_send_json_success( 'Webhook processed successfully' );
        }

        $this->log( 'Webhook event ignored: ' . $event );
        wp_send_json_success( 'Event received but no action taken' );
    }
}
