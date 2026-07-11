=== Fastaar Payment Gateway for WooCommerce ===
Contributors: fastaar
Donate link: https://fastaar.com
Tags: fastaar, payment gateway, bkash, nagad, rocket, upay, bangladesh, woocommerce
Requires at least: 6.0
Tested up to: 6.5
Stable tag: 1.1.0
Requires PHP: 8.1
License: MIT
License URI: https://opensource.org/licenses/MIT

Accept mobile banking payments in Bangladesh (bKash, Nagad, Rocket, Upay) on your WooCommerce store using Fastaar.

== Description ==

Fastaar Payment Gateway for WooCommerce allows you to easily accept bKash, Nagad, Rocket, and Upay payments on your WooCommerce store. This plugin is built with modern performance and security standards in mind, utilizing native WordPress APIs to achieve fast response times and safe cryptographic signature verification on incoming webhooks.

### Key Features
* **Seamless Checkout:** Redirect customers to the secure Fastaar checkout page to pay via bKash, Nagad, Rocket, or Upay.
* **Refunds:** Issue refunds directly from the WooCommerce order admin — no need to log in to the Fastaar dashboard.
* **Cryptographic Verification:** Every webhook request is verified using SHA-256 HMAC signature validation to prevent tampering and spoofing.
* **Idempotency Support:** Automatic safety checks prevent double processing of identical webhooks.
* **Detailed Logs:** Enable debug logging to track and trace API calls and incoming webhook payloads.
* **High Performance:** No external libraries or SDK dependencies; uses optimized WordPress HTTP APIs.

== Installation ==

1. Upload the entire `fastaar-woocommerce` folder to the `/wp-content/plugins/` directory, or install it directly via the WordPress admin panel.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Navigate to **WooCommerce > Settings > Payments**.
4. Click on **Fastaar** to configure your gateway options:
   * **API Key:** Retrieve your live or test key from your Fastaar merchant dashboard. The key must include the `payments:write` ability (and `payments:refund` if you plan to issue refunds from WooCommerce).
   * **Webhook Secret:** Set the signing secret for webhook verification.
5. In your Fastaar merchant dashboard, configure your Webhook URL to: `https://yourdomain.com/?wc-api=wc_gateway_fastaar`

== Frequently Asked Questions ==

= Where can I get my Fastaar API credentials? =
Log in to your Fastaar merchant portal, and navigate to settings to find your API key and Webhook secret.

= What is the Webhook URL to register in my Fastaar dashboard? =
Use the following format, replacing `yourdomain.com` with your website's URL:
`https://yourdomain.com/?wc-api=wc_gateway_fastaar`

= Can I test payments before going live? =
Yes, you can input a test API key (e.g., prefix `fk_test_`) and enable Sandbox Mode in settings to run tests.

= I'm getting an "ability_denied" or "authentication_error" response =
Your API key is missing a required ability, or has expired. Open the key in your Fastaar merchant dashboard
under API Keys and confirm it has `payments:write` (and `payments:refund` for refunds) and no expiry date in the past.

== Screenshots ==

1. Fastaar settings page in WooCommerce settings.
2. Fastaar payment gateway selection during checkout.

== Changelog ==

= 1.1.0 =
* Added refund support — issue refunds from the WooCommerce order admin via `POST /api/v1/payments/{id}/refund`.
* Updated error parsing to match the new API error format (`message` + `code` fields instead of the nested `error` object).

= 1.0.0 =
* Initial release of the Fastaar Payment Gateway for WooCommerce.

== Upgrade Notice ==

= 1.1.0 =
* Adds refund support and updates error handling for the new API response format. Upgrade recommended.

= 1.0.0 =
* Initial release of Fastaar Payment Gateway for WooCommerce.
