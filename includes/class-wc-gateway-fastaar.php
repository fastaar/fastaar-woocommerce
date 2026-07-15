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
     * The API Key entered by the merchant (interpreted as live or test, per $test_mode).
     *
     * @var string
     */
    private $api_key;

    /**
     * Whether Test Mode is enabled — the API Key above is treated as a Test API Key.
     *
     * @var bool
     */
    private $test_mode;

    /**
     * Webhook Secret.
     *
     * @var string
     */
    private $webhook_secret;

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
        $this->supports           = array( 'products', 'refunds' );
        $this->icon               = apply_filters( 'woocommerce_fastaar_icon', $this->get_logo_url() );

        // Load settings
        $this->init_form_fields();
        $this->init_settings();

        // Define user setting variables
        $this->title           = $this->get_option( 'title' );
        $this->description     = $this->get_option( 'description' );
        $this->api_key         = $this->get_option( 'api_key' );
        $this->test_mode       = 'yes' === $this->get_option( 'test_mode' );
        $this->webhook_secret = $this->get_option( 'webhook_secret' );
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

        // Fallback: if the customer lands back on the thank-you page before the webhook
        // arrives (or the webhook can't reach this site, e.g. a local/unreachable dev URL),
        // check the payment status directly instead of leaving the order stuck as pending.
        add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'maybe_complete_order_on_return' ) );

        // Let the merchant choose which order status a Fastaar payment moves to on completion.
        add_filter( 'woocommerce_payment_complete_order_status', array( $this, 'filter_payment_complete_order_status' ), 10, 3 );
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
                'title'       => __( 'API Key', 'fastaar-pay' ),
                'type'        => 'text',
                'description' => __( 'Your Fastaar merchant API Key — enter the one matching the Test Mode setting below (starts with <code>fk_test_</code> if Test Mode is on, or <code>fk_live_</code> if it\'s off). The key must have the <code>payments:write</code> ability to create payments, and <code>payments:refund</code> if you plan to issue refunds from WooCommerce — otherwise checkout or refunds fail with an "ability_denied" error.', 'fastaar-pay' ),
                'default'     => '',
            ),
            'test_mode' => array(
                'title'       => __( 'Test Mode', 'fastaar-pay' ),
                'type'        => 'checkbox',
                'label'       => __( 'Enable Test Mode', 'fastaar-pay' ),
                'default'     => 'no',
                'description' => __( 'While enabled, checkout uses the API Key above as a Test API Key and test payments never touch real money — the same Test Mode already available in your Fastaar merchant panel. Switch this off and use your Live API Key above to go live.', 'fastaar-pay' ),
            ),
            'order_status_after_payment' => array(
                'title'       => __( 'Order Status After Payment', 'fastaar-pay' ),
                'type'        => 'select',
                'description' => __( 'The WooCommerce order status to set once Fastaar confirms a payment is complete.', 'fastaar-pay' ),
                'default'     => 'default',
                'desc_tip'    => true,
                'options'     => array(
                    'default'    => __( 'Default (let WooCommerce decide)', 'fastaar-pay' ),
                    'processing' => __( 'Processing', 'fastaar-pay' ),
                    'completed'  => __( 'Completed', 'fastaar-pay' ),
                    'on-hold'    => __( 'On hold', 'fastaar-pay' ),
                ),
            ),
            'webhook_url_info' => array(
                'title'       => __( 'Webhook URL', 'fastaar-pay' ),
                'type'        => 'title',
                'description' => sprintf(
                    /* translators: 1: the webhook URL to register in the Fastaar dashboard, 2: the event name to subscribe to */
                    __( 'Add this URL as a webhook endpoint in your Fastaar merchant dashboard, then paste the signing secret it gives you below: %1$s When choosing which events to send, this plugin only acts on %2$s (it marks the order paid) — other events are received but ignored, so subscribing to just that one is enough; subscribing to all events is harmless too.', 'fastaar-pay' ),
                    '<br><code>' . esc_html( $this->get_webhook_url() ) . '</code><br>',
                    '<code>payment.completed</code>'
                ),
            ),
            'webhook_secret' => array(
                'title'       => __( 'Webhook Secret', 'fastaar-pay' ),
                'type'        => 'text',
                'description' => __( 'Enter your Webhook signing secret. Used to verify the integrity of webhook notifications from Fastaar.', 'fastaar-pay' ),
                'default'     => '',
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
     * The webhook URL to register in the Fastaar merchant dashboard for this store.
     *
     * @return string
     */
    public function get_webhook_url() {
        return add_query_arg( 'wc-api', 'fastaar', home_url( '/' ) );
    }

    /**
     * URL of the Fastaar logo shown next to the gateway title at checkout.
     *
     * @return string
     */
    public function get_logo_url() {
        return plugins_url( 'assets/images/logo.svg', FASTAAR_PAY_PLUGIN_FILE );
    }

    /**
     * Validate the API Key field on save: required whenever the gateway is enabled,
     * and must start with the prefix matching the submitted Test Mode setting
     * (fk_test_ or fk_live_). Invalid input is rejected and the previously saved
     * value is kept, with an admin notice.
     *
     * @param string $key   Field key.
     * @param string|null $value Submitted value.
     * @return string
     */
    public function validate_api_key_field( $key, $value ) {
        $value = is_null( $value ) ? '' : trim( $value );

        $will_enable        = isset( $_POST[ $this->get_field_key( 'enabled' ) ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $will_use_test_mode = isset( $_POST[ $this->get_field_key( 'test_mode' ) ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $prefix             = $will_use_test_mode ? 'fk_test_' : 'fk_live_';

        if ( '' === $value ) {
            if ( $will_enable ) {
                WC_Admin_Settings::add_error(
                    sprintf(
                        /* translators: %s: which key, "Test" or "Live" */
                        __( '%s API Key is required to enable the gateway in this mode. Settings were not saved.', 'fastaar-pay' ),
                        $will_use_test_mode ? __( 'Test', 'fastaar-pay' ) : __( 'Live', 'fastaar-pay' )
                    )
                );
                return $this->get_option( 'api_key' );
            }

            return $value;
        }

        if ( ! str_starts_with( $value, $prefix ) ) {
            WC_Admin_Settings::add_error(
                sprintf(
                    /* translators: %s: expected key prefix, e.g. fk_live_ */
                    __( 'That doesn\'t look like a valid Fastaar API Key for this mode — it should start with %s. Settings were not saved.', 'fastaar-pay' ),
                    $prefix
                )
            );
            return $this->get_option( 'api_key' );
        }

        return $value;
    }

    /**
     * Validate the Webhook Secret field on save: not required to enable the gateway
     * (payments still work without it), but warn if it's missing since webhook
     * signature verification — and therefore automatic order completion — won't
     * work without it. The value is saved either way; this only shows a notice.
     *
     * @param string $key   Field key.
     * @param string|null $value Submitted value.
     * @return string
     */
    public function validate_webhook_secret_field( $key, $value ) {
        $value = is_null( $value ) ? '' : trim( $value );

        if ( '' === $value && isset( $_POST[ $this->get_field_key( 'enabled' ) ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            WC_Admin_Settings::add_error( __( 'No Webhook Secret is set — Fastaar webhooks will fail signature verification and orders won\'t be marked paid automatically until you add one.', 'fastaar-pay' ) );
        }

        return $value;
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
            $msg = $this->test_mode
                ? __( 'Fastaar payment gateway is not properly configured. Test API Key is missing.', 'fastaar-pay' )
                : __( 'Fastaar payment gateway is not properly configured. Live API Key is missing.', 'fastaar-pay' );
            $this->log( $msg, 'error' );
            wc_add_notice( $msg, 'error' );
            return array( 'result' => 'fail' );
        }

        $client = new Fastaar_API_Client( $this->api_key );

        $params = array(
            'amount'      => $order->get_total(),
            'invoice_number'  => (string) $order->get_id(),
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
            wc_add_notice(
                esc_html(
                    sprintf(
                        /* translators: %s: error message from the Fastaar API */
                        __( 'Fastaar Payment Error: %s', 'fastaar-pay' ),
                        $e->getMessage()
                    )
                ),
                'error'
            );
            return array(
                'result' => 'fail',
            );
        }
    }

    /**
     * Fires on the order-received (thank-you) page. The webhook is the source of truth for
     * completing orders, but it can be delayed, or never arrive at all if this site isn't
     * publicly reachable from Fastaar (e.g. testing on localhost without a tunnel). This
     * checks the payment status directly so the order doesn't get stuck as "Pending payment"
     * when the payment itself actually completed.
     *
     * @param int $order_id Order ID.
     */
    public function maybe_complete_order_on_return( $order_id ) {
        $order = wc_get_order( $order_id );

        if ( ! $order || $order->is_paid() ) {
            return;
        }

        $payment_id = $order->get_meta( '_fastaar_payment_id' );

        if ( empty( $payment_id ) || empty( $this->api_key ) ) {
            return;
        }

        try {
            $client  = new Fastaar_API_Client( $this->api_key );
            $payment = $client->get_payment( $payment_id );

            $this->log( 'Checked payment status on return for Order ID: ' . $order_id . ', Payment ID: ' . $payment_id . ', status: ' . ( $payment['status'] ?? 'unknown' ) );

            if ( isset( $payment['status'] ) && 'completed' === $payment['status'] && ! $order->is_paid() ) {
                $order->payment_complete( $payment_id );
                $order->add_order_note(
                    sprintf(
                        /* translators: %s: Fastaar Transaction ID */
                        __( 'Fastaar payment completed (confirmed on return, webhook had not yet arrived). Transaction ID: %s', 'fastaar-pay' ),
                        $payment_id
                    )
                );
                $this->log( 'Order ' . $order_id . ' marked as paid on return (webhook had not arrived yet).' );
            }
        } catch ( Exception $e ) {
            // Non-fatal: the webhook may still complete the order shortly after. Just log it.
            $this->log( 'Could not confirm payment status on return for Order ID: ' . $order_id . ': ' . $e->getMessage(), 'error' );
        }
    }

    /**
     * Process Fastaar Webhook notifications.
     */
    public function handle_webhook() {
        $this->log( 'Incoming webhook notification received.' );

        $signature = '';
        if ( isset( $_SERVER['HTTP_X_FASTAAR_SIGNATURE'] ) ) {
            $signature = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FASTAAR_SIGNATURE'] ) );
        } elseif ( function_exists( 'getallheaders' ) ) {
            $headers = getallheaders();
            if ( isset( $headers['X-Fastaar-Signature'] ) ) {
                $signature = sanitize_text_field( wp_unslash( $headers['X-Fastaar-Signature'] ) );
            } elseif ( isset( $headers['x-fastaar-signature'] ) ) {
                $signature = sanitize_text_field( wp_unslash( $headers['x-fastaar-signature'] ) );
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
            $order_id   = isset( $data['invoice_number'] ) ? $data['invoice_number'] : '';
            $payment_id = isset( $data['id'] ) ? $data['id'] : '';

            $this->log( 'Processing payment.completed event. Order ID: ' . $order_id . ', Payment ID: ' . $payment_id );

            if ( empty( $order_id ) ) {
                $this->log( 'Webhook error: invoice_number is missing in event payload data.', 'error' );
                wp_send_json_error( 'Missing invoice_number', 400 );
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

    /**
     * Overrides the order status WooCommerce moves to on $order->payment_complete(),
     * for orders paid via Fastaar, if the merchant configured a specific one instead
     * of leaving it as "Default".
     *
     * @param string        $status   The status WooCommerce would otherwise use.
     * @param int           $order_id Order ID.
     * @param WC_Order|null $order    Order object.
     * @return string
     */
    public function filter_payment_complete_order_status( $status, $order_id, $order = null ) {
        if ( ! $order || $order->get_payment_method() !== $this->id ) {
            return $status;
        }

        $configured = $this->get_option( 'order_status_after_payment', 'default' );

        return 'default' === $configured ? $status : $configured;
    }

    /**
     * Process a refund for a completed (or previously partially refunded) order.
     *
     * WooCommerce's own partial-refund UI (refunding individual line items, or a custom
     * amount) is passed straight through to Fastaar — refunding less than the order total
     * marks the Fastaar payment `partially_refunded` rather than `refunded`, and can be
     * refunded again later for the remainder.
     *
     * @param int        $order_id Order ID.
     * @param float|null $amount   Amount to refund, or null to refund the full remaining balance.
     * @param string     $reason   Refund reason (informational only).
     * @return bool|WP_Error
     */
    public function process_refund( $order_id, $amount = null, $reason = '' ) {
        $order      = wc_get_order( $order_id );
        $payment_id = $order ? $order->get_meta( '_fastaar_payment_id' ) : '';

        if ( empty( $payment_id ) ) {
            return new WP_Error( 'fastaar_refund_error', __( 'Fastaar payment ID not found for this order.', 'fastaar-pay' ) );
        }

        $client = new Fastaar_API_Client( $this->api_key );

        try {
            $payment = $client->refund_payment( $payment_id, $amount );

            $order->add_order_note(
                sprintf(
                    /* translators: 1: Fastaar payment ID, 2: refund amount, 3: refund reason */
                    __( 'Fastaar refund submitted. Payment ID: %1$s. Amount: %2$s. Reason: %3$s', 'fastaar-pay' ),
                    $payment_id,
                    null !== $amount ? wc_format_decimal( $amount, 2 ) : __( 'full remaining balance', 'fastaar-pay' ),
                    $reason ?: __( 'No reason provided', 'fastaar-pay' )
                )
            );

            $this->log( 'Refund processed for Order ID: ' . $order_id . ', Payment ID: ' . $payment_id . ', status: ' . ( $payment['status'] ?? 'unknown' ) );

            return true;

        } catch ( Exception $e ) {
            $this->log( 'Refund failed for Order ID: ' . $order_id . ': ' . $e->getMessage(), 'error' );
            return new WP_Error( 'fastaar_refund_error', $e->getMessage() );
        }
    }
}
