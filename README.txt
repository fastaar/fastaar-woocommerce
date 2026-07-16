=== Fastaar Pay ===
Contributors: fastaar, amdad121
Donate link: https://fastaar.com
Tags: fastaar, payment gateway, bkash, nagad, bangladesh
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 8.1
WC requires at least: 7.0
WC tested up to: 10.9
Stable tag: 1.2.5
License: MIT
License URI: https://opensource.org/licenses/MIT

Accept mobile banking payments in Bangladesh (bKash, Nagad, Rocket, Upay) on your WooCommerce store using Fastaar.

== Description ==

Fastaar Pay allows you to easily accept bKash, Nagad, Rocket, and Upay payments on your WooCommerce store. This plugin is built with modern performance and security standards in mind, utilizing native WordPress APIs to achieve fast response times and safe cryptographic signature verification on incoming webhooks.

### Key Features
* **Seamless Checkout:** Redirect customers to the secure Fastaar checkout page to pay via bKash, Nagad, Rocket, or Upay.
* **Block Checkout Compatible:** Works on both the classic shortcode checkout and the WooCommerce Cart & Checkout blocks.
* **Full & Partial Refunds:** Issue full or partial refunds directly from the WooCommerce order admin — no need to log in to the Fastaar dashboard. Partially refunded orders stay refundable for the remaining balance.
* **Cryptographic Verification:** Every webhook request is verified using SHA-256 HMAC signature validation to prevent tampering and spoofing.
* **Idempotency Support:** Automatic safety checks prevent double processing of identical webhooks.
* **Detailed Logs:** Enable debug logging to track and trace API calls and incoming webhook payloads.
* **High Performance:** No external libraries or SDK dependencies; uses optimized WordPress HTTP APIs.

== Installation ==

1. Upload the entire `fastaar-pay` folder to the `/wp-content/plugins/` directory, or install it directly via the WordPress admin panel.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Navigate to **WooCommerce > Settings > Payments**.
4. Click on **Fastaar** to configure your gateway options:
   * **Test Mode:** Leave off for real payments. Turn on to test the full order flow without real money — same Test Mode already available in your Fastaar merchant panel.
   * **API Key:** Enter the key matching the Test Mode setting above — a Test API Key (starts with `fk_test_`) if Test Mode is on, or a Live API Key (starts with `fk_live_`) if it's off. Get it from your Fastaar merchant dashboard. It must include the `payments:write` ability (and `payments:refund` if you plan to issue refunds from WooCommerce). Switching Test Mode means re-entering the matching key.
   * **Order Status After Payment:** Choose which WooCommerce order status a Fastaar payment moves to once confirmed — Processing, Completed, On hold, or leave it as Default to let WooCommerce decide as usual.
   * **Webhook Secret:** Set the signing secret for webhook verification.
5. The settings page shows your store's **Webhook URL** — copy it and add it as a webhook endpoint in your Fastaar merchant dashboard. When picking which events to send, `payment.completed` is the only one this plugin acts on (it marks the order paid); subscribing to just that is enough, though subscribing to all events is harmless too. Paste the signing secret Fastaar gives you back into **Webhook Secret** above.

== Frequently Asked Questions ==

= Where can I get my Fastaar API credentials? =
Log in to your Fastaar merchant portal, and navigate to settings to find your API key and Webhook secret.

= Where do I find the Webhook URL to register in my Fastaar dashboard? =
It's shown directly on the **WooCommerce > Settings > Payments > Fastaar** page, just above the Webhook Secret field — copy it from there. It follows the format `https://yourdomain.com/?wc-api=fastaar`.

= Which webhook events do I need to subscribe to? =
Only `payment.completed` — that's the only event this plugin listens for, and it's what marks a WooCommerce order as paid. Any other event Fastaar sends (e.g. `payment.refunded`, `payment.failed`) is received and acknowledged but otherwise ignored.

= What API key abilities does this plugin need? =
`payments:write` is required to create payments at checkout. `payments:refund` is only needed if you plan to issue refunds from the WooCommerce order admin — without it, refund attempts fail with an "ability_denied" error.

= Can I test payments before going live? =
Yes — turn on Test Mode, then enter your Test API Key (starts with `fk_test_`) in the API Key field. Test payments never touch real money and auto-complete on the Fastaar checkout page. When you're ready to go live, turn Test Mode back off and swap in your Live API Key (starts with `fk_live_`).

= What does the "Order Status After Payment" setting do? =
By default WooCommerce decides the status after payment (usually Processing, or Completed for virtual/downloadable-only orders). If you'd rather every Fastaar payment always land on a specific status — e.g. always Completed, or always On hold for manual review — pick it here instead of Default.

= I'm getting an "ability_denied" or "authentication_error" response =
Your API key is missing a required ability, or has expired. Open the key in your Fastaar merchant dashboard
under API Keys and confirm it has `payments:write` (and `payments:refund` for refunds) and no expiry date in the past.

= I enabled Fastaar but it doesn't show up at checkout =
Make sure you clicked Save on WooCommerce > Settings > Payments after enabling it. If your checkout page uses the WooCommerce Cart & Checkout blocks, this plugin registers itself there too — if it's still missing, deactivate and reactivate the plugin to make sure the block registration is picked up, and check for a plugin update.

== External services ==

This plugin connects to the Fastaar payment platform (fastaar.com) to process bKash, Nagad, Rocket, and Upay payments — this connection is required for the plugin to function, since Fastaar is the payment processor.

* **Creating a payment (at checkout):** When a customer places an order using Fastaar, the order total, currency, order ID, order key, and the return/cancel URLs are sent to `https://fastaar.com/api/v1/payments` so Fastaar can create a hosted checkout session. The customer's browser is then redirected to Fastaar's checkout page to complete payment.
* **Refunds (from the WooCommerce order admin):** If you issue a refund, the Fastaar payment ID and the refund amount are sent to Fastaar's refund endpoint.
* **Checking payment status:** On the order-received (thank-you) page, the plugin may query Fastaar for the current status of a payment, in case the confirmation webhook hasn't arrived yet.
* **Incoming webhooks:** Fastaar sends payment status updates (e.g. `payment.completed`) back to this site's webhook URL so the corresponding WooCommerce order can be marked paid.

No data is sent to Fastaar unless a customer actually places an order using this gateway, or a store admin performs the actions above.

Fastaar service: [Terms of Service](https://fastaar.com/terms), [Privacy Policy](https://fastaar.com/privacy).

== Screenshots ==

1. Fastaar payment gateway selection during checkout.
2. Fastaar listed among WooCommerce's Payment providers.
3. Fastaar settings page — API Key, Test Mode, and Order Status After Payment.
4. Fastaar settings page continued — Webhook URL, Webhook Secret, and Debug Log.

== Changelog ==

= 1.2.5 =
* Added `uninstall.php` to remove the plugin's settings when it's deleted through the WordPress admin.
* Added a `Requires Plugins: woocommerce` header so WordPress can prompt to install WooCommerce first, if it's missing.
* Renamed the remaining global functions (`fastaar_pay_init`, `fastaar_pay_add_gateway`, etc.) and a custom filter hook to use the plugin's own prefix instead of `woocommerce_`, and renamed all class files/classes to the `Fastaar_Pay_*` / `class-fastaar-pay-*.php` convention, addressing further naming-collision guidelines from the WordPress.org Plugin Check.
* Removed a dead legacy webhook hook that never fired.

= 1.2.4 =
* Renamed the gateway class from `WC_Gateway_Fastaar` to `Fastaar_WC_Gateway` (and its file to match) so it carries the plugin's own prefix, per WordPress.org's naming-collision guidelines.

= 1.2.3 =
* Documented the plugin's use of the Fastaar API as an external service, per WordPress.org guidelines.
* Sanitized webhook input (event name, order ID, payment ID, and the logged request body) before use.

= 1.2.2 =
* Fixed the Plugin URI and Author URI being identical in the plugin header (a WordPress.org plugin check requirement) — Plugin URI now points to the plugin's GitHub repository, Author URI to fastaar.com.

= 1.2.1 =
* Renamed the plugin folder from `fastaar-woocommerce` to `fastaar-pay` to match the plugin's text domain and WordPress.org slug.
* Simplified the API Key setting to a single field (used as either the Live or Test key depending on the Test Mode toggle) instead of separate Live/Test API Key fields.
* Added a directory listing icon and banner, and corrected the Screenshots section to describe all four current screenshots.
* Added `amdad121` as a contributor.

= 1.2.0 =
* Reworked Test Mode into an actual working toggle, matching the Test Mode in your Fastaar merchant panel: a Test Mode switch plus a single API Key field, validated against the matching `fk_test_`/`fk_live_` prefix for whichever mode is on. (The old "Sandbox Mode" checkbox was stored but never used anywhere — it's been replaced by this.)
* Added an "Order Status After Payment" setting — choose Processing, Completed, On hold, or leave it as WooCommerce's default for orders paid via Fastaar.
* Fixed orders getting stuck on "Pending payment" when the Fastaar webhook is delayed or can't reach the site (e.g. local development). The order-received page now double-checks the payment status directly as a fallback, in addition to the webhook.
* Added the Fastaar logo next to the gateway title at checkout, on both the classic and block-based checkout.
* Fixed settings validation — enabling the gateway without an API Key for the active mode, or entering one that doesn't start with the right prefix (`fk_live_`/`fk_test_`), is now rejected on save with a clear admin notice instead of silently saving. A missing Webhook Secret is still saved (it's optional), but now shows a warning since webhook signature verification won't work without one.
* Verified compatibility with WordPress 7.0 and WooCommerce 10.9.
* Added WooCommerce Cart & Checkout blocks support — Fastaar now appears at checkout on stores using the block-based checkout, not just the classic shortcode checkout.
* Added partial refund support — WooCommerce's own refund amount (full order, a partial amount, or individual line items) is now passed through to Fastaar instead of always refunding in full. Refunding less than the full amount marks the Fastaar payment as partially refunded, and it can be refunded again later for the rest.
* The settings page now displays your store's Webhook URL directly, so you no longer have to construct it by hand to register it in the Fastaar dashboard.
* The API Key and Webhook URL settings now explain exactly which abilities (`payments:write`, `payments:refund`) and webhook event (`payment.completed`) the plugin needs, so you don't have to guess or check the docs separately.

= 1.1.0 =
* Added refund support — issue refunds from the WooCommerce order admin via `POST /api/v1/payments/{id}/refund`.
* Updated error parsing to match the new API error format (`message` + `code` fields instead of the nested `error` object).

= 1.0.0 =
* Initial release of the Fastaar Payment Gateway for WooCommerce.

== Upgrade Notice ==

= 1.2.5 =
* Adds settings cleanup on uninstall and a WooCommerce dependency check. No functional changes for existing installs.

= 1.2.4 =
* Internal class/file rename for WordPress.org naming-collision compliance. No functional changes.

= 1.2.3 =
* Security and guideline compliance fixes: sanitizes webhook input, documents the Fastaar external service, and trims dev-only assets from the release package. No functional changes for merchants.

= 1.2.2 =
* Fixes a plugin header validation error (duplicate Plugin/Author URI). No functional changes.

= 1.2.1 =
* Renames the plugin folder to `fastaar-pay` and simplifies the API Key setting to one field. If you installed via a zip, re-upload; if updating in place, verify the Fastaar gateway is still enabled under WooCommerce > Settings > Payments afterward.

= 1.2.0 =
* Adds a working Test Mode, an Order Status After Payment setting, WooCommerce Cart & Checkout blocks support, the Fastaar logo at checkout, and fixes orders getting stuck on "Pending payment". Upgrade recommended.

= 1.1.0 =
* Adds refund support and updates error handling for the new API response format. Upgrade recommended.

= 1.0.0 =
* Initial release of Fastaar Payment Gateway for WooCommerce.
