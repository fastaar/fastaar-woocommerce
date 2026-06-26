<?php
/**
 * Fastaar Webhook Validator
 *
 * @package WooCommerce_Fastaar
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Fastaar_Webhook_Validator
 *
 * Verifies that the webhook request is genuinely sent by Fastaar.
 */
class Fastaar_Webhook_Validator {

    /**
     * Verify the X-Fastaar-Signature header (`t=<ts>,v1=<hmac>`) against
     * the raw request body using your merchant webhook secret.
     *
     * @param string $secret            The webhook signing secret.
     * @param string $raw_body          The raw request body.
     * @param string $signature_header  The signature header value.
     * @param int    $tolerance_seconds Tolerance window in seconds (default 300).
     * @return bool
     */
    public static function verify( $secret, $raw_body, $signature_header, $tolerance_seconds = 300 ) {
        if ( empty( $signature_header ) || empty( $secret ) ) {
            return false;
        }

        if ( preg_match( '/^t=(?<t>\d+),v1=(?<v1>[a-f0-9]{64})$/', $signature_header, $matches ) !== 1 ) {
            return false;
        }

        $timestamp = (int) $matches['t'];

        if ( abs( time() - $timestamp ) > $tolerance_seconds ) {
            return false;
        }

        $expected = hash_hmac( 'sha256', "{$timestamp}.{$raw_body}", $secret );

        return hash_equals( $expected, $matches['v1'] );
    }
}
