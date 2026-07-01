=== TakuEcom - BCI Payments for WooCommerce ===
Contributors: bci, bpc
Tags: woocommerce, payment gateway, bci, takuecom, bpc
Requires at least: 5.0
Tested up to: 6.9.1
Requires PHP: 7.4
Stable tag: 1.0.2
License: GPLv3
License URI: https://opensource.org/licenses/GPL-3.0

== Description ==

TakuEcom - BCI Payments for WooCommerce adds a BCI TakuEcom redirect checkout for accepting card payments.

Customers enter card details on the BCI-hosted secure payment page. WooCommerce verifies the gateway status through server-side status checks before marking an order paid.

Features:

* Live and sandbox mode.
* NZD live payment currency and EUR-by-default sandbox payments, with a matching sandbox currency selector.
* Guided merchant setup inside WooCommerce settings.
* Connection test buttons.
* Signed callback endpoint at /wp-json/bci-woo/v1/callback.
* Background pending-order polling.
* Manual pending-order check button.
* WooCommerce Checkout Block support.
* Optional experimental WooCommerce Subscriptions support through stored credentials and recurrentPayment.do, disabled by default.

== Installation ==

1. Upload the plugin folder or ZIP through Plugins > Add New > Upload Plugin.
2. Activate the plugin.
3. Go to WooCommerce > Settings > Payments.
4. Open TakuEcom - BCI Payments.
5. Enable Use sandbox credentials and endpoint and enter sandbox credentials first.
6. Keep Sandbox currency set to EUR unless the development merchant currency has been changed in the BPC Dev Merchant Portal.
7. Configure callback notifications in the BCI merchant portal.
8. Save settings and run the connection test.
9. Place a sandbox order before switching to live mode.

== Callback Setup ==

Use the callback URL shown in the gateway settings:

/wp-json/bci-woo/v1/callback

Recommended merchant portal settings:

* Callback type: Static.
* Method: POST.
* Signing type: Symmetric.
* Events: Deposited, Approved, Reversed, Refunded, Declined by timeout.

Paste the generated callback token into the matching sandbox or live callback token field.

== Subscriptions ==

Automatic renewal code paths are present, but disabled by default, not validated, and not delivered as production scope for v1.0. The core v1.0 release is for one-off hosted checkout payments.

Automatic renewals require WooCommerce Subscriptions and BCI stored credential permissions.

Ask BCI support to enable stored credentials, FORCE_CREATE_BINDING, recurrentPayment.do, and merchant-initiated transaction permission if required. Do not enable renewals for production until the recurring-payment checklist has passed for the merchant.

Run a sandbox subscription checkout before enabling live renewals.

== Frequently Asked Questions ==

= Does Processing mean the payment is complete? =

Yes. WooCommerce normally uses Processing for paid orders that still need fulfilment. The gateway also offers a setting to force paid orders to Completed if that better matches the store's workflow.

= Does this plugin store card numbers? =

No. Card data is entered on the BCI-hosted secure payment page. For subscriptions, the plugin stores only the gateway binding ID and non-sensitive card display metadata when returned by the gateway.

= Where are logs? =

Open WooCommerce > Status > Logs and select the BCI_Woo_Plugin source.

== Changelog ==

= 1.0.2 =

* Expanded the project README and added the BPC integration and API reference documentation.

= 1.0.1 =

* Fix Cook Islands checkout registration by omitting the unsupported optional billing state from BPC payer data.

= 1.0.0 =

Initial BCI TakuEcom WooCommerce gateway.
