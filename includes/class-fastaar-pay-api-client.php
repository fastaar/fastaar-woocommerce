<?php
/**
 * Fastaar API Client
 *
 * @package Fastaar_Pay
 */

defined( 'ABSPATH' ) || exit;

/**
 * Exception class for Fastaar API errors.
 */
class Fastaar_Pay_API_Exception extends Exception {

    /**
     * The stable API error code.
     *
     * @var string
     */
    private $error_type;

    /**
     * Constructor.
     *
     * @param string $message    Error message.
     * @param string $error_type API error type/code.
     * @param int    $status_code HTTP status code.
     */
    public function __construct( $message, $error_type = 'api_error', $status_code = 0 ) {
        parent::__construct( $message, $status_code );
        $this->error_type = $error_type;
    }

    /**
     * Get the API error type.
     *
     * @return string
     */
    public function get_error_type() {
        return $this->error_type;
    }
}

/**
 * Class Fastaar_Pay_API_Client
 *
 * Client to communicate with the Fastaar API using WordPress APIs.
 */
class Fastaar_Pay_API_Client {

    const BASE_URL = 'https://fastaar.com';

    /**
     * API Key.
     *
     * @var string
     */
    private $api_key;

    /**
     * Timeout seconds.
     *
     * @var int
     */
    private $timeout_seconds;

    /**
     * Constructor.
     *
     * @param string $api_key         API Key.
     * @param int    $timeout_seconds Timeout in seconds.
     */
    public function __construct( $api_key, $timeout_seconds = 15 ) {
        $this->api_key         = $api_key;
        $this->timeout_seconds = $timeout_seconds;
    }

    /**
     * Create a payment intent.
     *
     * @param array $params Request parameters.
     * @return array
     * @throws Fastaar_Pay_API_Exception
     */
    public function create_payment( array $params ) {
        return $this->request( 'POST', '/api/v1/payments', $params );
    }

    /**
     * Retrieve a payment by its reference ID.
     *
     * @param string $payment_id Payment ID.
     * @return array
     * @throws Fastaar_Pay_API_Exception
     */
    public function get_payment( $payment_id ) {
        return $this->request( 'GET', '/api/v1/payments/' . rawurlencode( $payment_id ) );
    }

    /**
     * List payments.
     *
     * @param array $params Optional query parameters.
     * @return array
     * @throws Fastaar_Pay_API_Exception
     */
    public function list_payments( array $params = [] ) {
        $query = empty( $params ) ? '' : '?' . http_build_query( $params );
        return $this->request( 'GET', '/api/v1/payments' . $query );
    }

    /**
     * Refund a payment, in full or in part. Only payments with status `completed` or
     * `partially_refunded` can be refunded. Pass an amount to refund only part of the
     * remaining balance; omit it to refund whatever is still refundable.
     *
     * @param string     $payment_id Fastaar payment ID.
     * @param float|null $amount     Amount to refund, or null for the full remaining balance.
     * @return array The updated payment object. `status` is `refunded` once fully refunded,
     *               or `partially_refunded` if some balance remains.
     * @throws Fastaar_Pay_API_Exception
     */
    public function refund_payment( $payment_id, $amount = null ) {
        $body = null !== $amount ? array( 'amount' => $amount ) : null;
        return $this->request( 'POST', '/api/v1/payments/' . rawurlencode( $payment_id ) . '/refund', $body );
    }

    /**
     * List a payment's refund history, newest first — one entry per refund call, even
     * across several partial refunds.
     *
     * @param string $payment_id Fastaar payment ID.
     * @return array List of refund objects.
     * @throws Fastaar_Pay_API_Exception
     */
    public function list_refunds( $payment_id ) {
        return $this->request( 'GET', '/api/v1/payments/' . rawurlencode( $payment_id ) . '/refunds' );
    }

    /**
     * Execute HTTP request.
     *
     * @param string     $method HTTP method.
     * @param string     $path   Request path.
     * @param array|null $body   Request body.
     * @return array
     * @throws Fastaar_Pay_API_Exception
     */
    private function request( $method, $path, $body = null ) {
        $url = self::BASE_URL . $path;

        $args = array(
            'method'      => $method,
            'timeout'     => $this->timeout_seconds,
            'redirection' => 5,
            'httpversion' => '1.0',
            'blocking'    => true,
            'headers'     => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Accept'        => 'application/json',
            ),
            'cookies'     => array(),
        );

        if ( null !== $body ) {
            $args['headers']['Content-Type'] = 'application/json';
            $args['body']                    = wp_json_encode( $body );
        }

        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            throw new Fastaar_Pay_API_Exception(
                esc_html(
                    sprintf(
                        /* translators: %s: error message */
                        __( 'Could not reach the Fastaar API: %s', 'fastaar-pay' ),
                        $response->get_error_message()
                    )
                ),
                'connection_error'
            );
        }

        $status_code = (int) wp_remote_retrieve_response_code( $response );
        $body_content = wp_remote_retrieve_body( $response );
        $decoded      = json_decode( $body_content, true );

        if ( $status_code >= 400 || ! is_array( $decoded ) ) {
            $message    = isset( $decoded['message'] ) ? $decoded['message'] : sprintf(
                /* translators: %d: HTTP status code */
                __( 'Fastaar API returned HTTP %d.', 'fastaar-pay' ),
                $status_code
            );
            $error_type = isset( $decoded['code'] ) ? $decoded['code'] : 'api_error';
            throw new Fastaar_Pay_API_Exception( esc_html( $message ), esc_html( $error_type ), (int) esc_html( (string) $status_code ) );
        }

        return isset( $decoded['data'] ) ? $decoded['data'] : $decoded;
    }
}
