# TakuEcom - BCI Payments for WooCommerce Architecture

This document describes the v1.0.0 release architecture for the BCI TakuEcom WooCommerce gateway.

The v1.0.0 production-certified scope is one-off hosted checkout payments. WooCommerce Subscriptions code paths are present, disabled by default, not validated, and not delivered as production scope for v1.0.0.

## Release Scope

Included in v1.0.0:

- BCI-branded WooCommerce payment gateway for TakuEcom redirect checkout.
- Classic shortcode checkout support.
- WooCommerce Checkout Block registration.
- Live and sandbox API credentials.
- Test mode, enabled by default.
- Sandbox-only EUR debug override for BPC testing.
- BCI merchant setup guidance in the WooCommerce gateway settings.
- Connection test buttons for live and sandbox credentials.
- Signed REST callback endpoint.
- Browser return handling.
- Background and manual pending-order recovery.
- WooCommerce HPOS compatibility declaration.
- Merchant setup guide, release requirements checklist, and release test report.

Not production-certified in v1.0.0:

- WooCommerce Subscriptions automatic renewals.
- Stored credential binding creation.
- `recurrentPayment.do` renewal charging.

Out of scope:

- WooCommerce-initiated refunds.
- Marketplace or multi-merchant routing.
- Hosted payment widgets or saved-card checkout for one-off carts.
- Google Pay or Apple Pay.
- PCI scope expansion. Card entry remains on BCI/BPC-hosted pages.

## Package

Plugin folder:

```text
woocommerce-gateway-bci
```

Main plugin file:

```text
woocommerce-gateway-bci.php
```

Plugin header:

```text
Plugin Name: TakuEcom - BCI Payments for WooCommerce
Version: 1.0.0
Text Domain: bci-woo
Requires Plugins: woocommerce
WC requires at least: 4.0
WC tested up to: 8.9.1
```

Namespace:

```text
BCI\Woo
```

Gateway ID:

```text
bci_takuecom
```

Settings option key:

```php
woocommerce_bci_takuecom_settings
```

## File Layout

```text
woocommerce-gateway-bci/
├── woocommerce-gateway-bci.php
├── readme.md
├── readme.txt
├── assets/
│   ├── bci-logo.png
│   └── js/frontend/blocks.js
├── includes/
│   ├── class-admin.php
│   ├── class-api.php
│   ├── class-callback.php
│   ├── class-config.php
│   ├── class-exception.php
│   ├── class-gateway.php
│   ├── class-log.php
│   ├── class-plugin.php
│   ├── class-renewals.php
│   ├── class-scheduler.php
│   ├── class-status-resolver.php
│   ├── class-subscriptions.php
│   ├── class-tokens.php
│   └── blocks/class-blocks-support.php
└── docs/
    ├── architecture.md
    ├── merchant-setup-guide.md
    ├── release-test-report-v1.0.0.md
    ├── release-todo-v1.0.0.md
    ├── requirements-v1.0.0.md
    └── screencast-outline.md
```

## Bootstrap

`woocommerce-gateway-bci.php` defines plugin path constants, loads `Config` and `Plugin`, declares WooCommerce custom order table compatibility, registers the plugin bootstrap, and clears scheduled recovery actions on deactivation.

`Plugin::register()` attaches:

- `plugins_loaded` for main plugin initialisation.
- `woocommerce_blocks_loaded` for Checkout Block payment method registration.

`Plugin::init()` loads the text domain, requires foundation classes, checks WooCommerce availability, registers the payment gateway, registers callback and scheduler services, initialises admin UI hooks, and registers subscription hooks only when the subscription integration itself allows them.

## Configuration

`Config` centralises the stable release constants:

```php
VERSION = '1.0.0'
TEXT_DOMAIN = 'bci-woo'
GATEWAY_ID = 'bci_takuecom'
LOG_SOURCE = 'BCI_Woo_Plugin'
API_URL_LIVE = 'https://securepayments.bci.co.ck/payment/rest'
API_URL_SANDBOX = 'https://dev.bpcbt.com/payment/rest'
PAYMENT_URL_LIVE = 'https://securepayments.bci.co.ck/payment'
PAYMENT_URL_SANDBOX = 'https://dev.bpcbt.com/payment'
CALLBACK_NAMESPACE = 'bci-woo/v1'
CALLBACK_ROUTE = '/callback'
API_TIMEOUT = 30
SCHEDULER_BATCH_SIZE = 50
SCHEDULER_INTERVAL_SECONDS = 300
PENDING_THRESHOLD_MINUTES = 10
FAILED_LOOKBACK_MINUTES = 60
```

## Order Metadata

The gateway stores BCI/BPC state on WooCommerce orders using these keys:

```text
_bci_woo_md_order
_bci_woo_order_number
_bci_woo_environment
_bci_woo_last_status
_bci_woo_last_action_code
_bci_woo_binding_id
_bci_woo_client_id
_bci_woo_masked_pan
_bci_woo_card_expiry
```

For one-off payments, `_bci_woo_md_order`, `_bci_woo_order_number`, and `_bci_woo_environment` are the critical fields. They are persisted immediately after `register.do` returns successfully so browser return, callbacks, manual checks, and scheduled recovery can resolve the order later.

Stored credential metadata is only relevant when experimental subscription renewals are explicitly enabled.

## Admin Settings

The gateway settings are grouped into:

- Gateway: enable gateway, checkout title, checkout description, paid order status behaviour.
- Test mode: sandbox/live environment switch.
- Sandbox currency override: forces EUR (`978`) for sandbox payment requests when Test mode is enabled.
- Live configuration: live API login, password, callback token, and connection test.
- Sandbox configuration: sandbox API login, password, callback token, and connection test.
- Callback URL: display-only REST callback URL for the BCI merchant portal.
- Recovery: pending threshold, failed recovery window, and manual pending-order check.
- Subscriptions: experimental renewal settings, disabled by default.

Test mode defaults to enabled. Subscription renewals default to disabled.

The paid order status option supports:

- `default`: use WooCommerce `payment_complete()` behaviour.
- `processing`: force paid orders to Processing.
- `completed`: force paid orders to Completed.

## API Client

`Api` uses WordPress HTTP functions for all gateway calls. It does not use raw cURL and does not disable SSL verification.

Implemented calls:

- `register_payment()` posts form data to `/register.do`.
- `get_order_status()` posts form data to `/getOrderStatusExtended.do`.
- `recurrent_payment()` posts JSON to `/payment/recurrentPayment.do`.
- `test_connection()` performs a lightweight status request to verify credentials.

The one-off payment release path uses `register_payment()` and `get_order_status()`. `recurrent_payment()` is present for the gated subscription integration.

## Currency Handling

The BCI-branded v1.0.0 gateway sends NZD (`554`) for normal checkout and renewal payment requests. This matches the brief requirement that the plugin currency is set to NZD.

```text
NZD = 554
```

If the WooCommerce store or order currency is not NZD, the gateway still sends NZD and logs that the order currency was ignored for the BCI request.

When Test mode is enabled and `sandbox_force_eur_currency` is set to `yes`, payment requests use EUR (`978`) regardless of the WooCommerce order currency. This is a BPC sandbox testing aid and is not intended for live payments.

## One-Off Payment Flow

1. Customer places a WooCommerce order using `bci_takuecom`.
2. WooCommerce creates the order in Pending Payment.
3. `Gateway::process_payment()` builds the BPC registration request.
4. The API client calls `/register.do`.
5. The gateway stores BPC `orderId`/`mdOrder`, the merchant `orderNumber`, and the selected environment on the order.
6. Customer is redirected to the BCI/BPC hosted payment page.
7. The customer completes, declines, abandons, or times out on the hosted page.
8. Browser return, callback, manual check, or scheduled recovery queries `/getOrderStatusExtended.do`.
9. `Status_Resolver` maps BPC status to WooCommerce order state.

## Register Payment Parameters

One-off checkout registration sends:

```text
amount
currency
language
orderNumber
returnUrl
failUrl
description
email
```

The amount is sent in minor units. The order number is compact and unique per registration attempt. The description is short and does not include card data.

When experimental subscriptions are disabled, subscription-specific parameters are not added.

When experimental subscriptions are explicitly enabled and the order contains a subscription, the plugin also adds:

```text
clientId
features = FORCE_CREATE_BINDING
```

## Browser Return

The hosted payment page returns customers to:

```text
/?wc-api=bci_takuecom_return&order_id={order_id}&key={order_key}
```

`Gateway::handle_return()` verifies the WooCommerce order key, resolves the gateway status, and redirects the customer to the WooCommerce order received page or retry/failure flow.

Browser return is useful for immediate customer experience, but it is not treated as the only source of truth. Callbacks and recovery checks also resolve payment state.

## Callback Endpoint

Callback route:

```text
/wp-json/bci-woo/v1/callback
```

`Callback` accepts GET and POST notifications because BPC installations may vary. POST is the recommended merchant portal configuration.

The callback handler:

- Extracts BPC parameters from query string or POST body.
- Requires either `orderNumber` or `mdOrder`/`orderId`.
- Verifies the HMAC-SHA256 checksum against configured live and sandbox callback tokens.
- Finds the order by stored order number, parsed WooCommerce order ID, or stored `mdOrder`.
- Rejects mismatched `mdOrder` values.
- Ignores terminal orders where no resolution is needed.
- Fires `bci_woo_callback_received`.
- Resolves the latest status through `Status_Resolver`.

Unknown orders return HTTP 200 after logging to avoid repeated gateway retries for an order the store cannot resolve. Invalid signatures return an error response.

## HMAC Verification

The callback signature check removes `checksum` and `sign_alias`, sorts remaining parameters by key, signs the semicolon-delimited `key;value;` string with the callback token, and compares the uppercase HMAC-SHA256 digest using `hash_equals()`.

Both live and sandbox callback tokens are accepted because BPC callbacks do not always include a reliable environment marker.

## Status Resolution

`Status_Resolver` is shared by browser return, callbacks, scheduled checks, manual recovery, and renewals.

Gateway status mapping:

- `2` Captured: mark paid with `payment_complete()`, then apply the configured paid order status override if set.
- `4` Refunded: mark the WooCommerce order refunded or add a refund note. The plugin does not initiate refunds.
- `3` Authorisation cancelled/reversed: mark unpaid orders failed and paid orders cancelled.
- `6` Declined: mark failed.
- `0` Registered with `actionCode` `0` or `-30001`: keep pending.
- `0` Registered with another non-zero `actionCode`: mark failed.
- `1` Authorised: keep pending.
- `5` ACS initiated: keep pending.
- `7` Pending: keep pending.
- `8` Partial completion: keep pending and log.
- Any unexpected status: log and leave as an error state for review.

The resolver stores the latest BPC `orderStatus` and `actionCode` on the order each time a status response is processed.

## Recovery Scheduler

`Scheduler` registers the `bci_woo_check_pending_orders` Action Scheduler job. The recurring interval defaults to 300 seconds.

The scheduler checks:

- Pending BCI orders older than the configured pending threshold, default 10 minutes.
- Recently failed BCI orders inside the configured failed recovery window, default 60 minutes.

Only orders with a stored BPC `mdOrder` are resolved. This prevents unrelated failed or pending WooCommerce orders from being touched.

The same recovery logic is available through the admin manual "Check Pending Orders" button.

## Logging

`Log` writes through the WooCommerce logger using source:

```text
BCI_Woo_Plugin
```

Logs are used for callback rejection, status check failures, unexpected statuses, renewal failures, and currency override handling. Logs do not include full card data or credential values.

## Checkout Block Support

`Blocks_Support` registers the gateway for WooCommerce Checkout Blocks when WooCommerce Blocks payment integration classes are available.

The block integration exposes the configured gateway title, description, and icon to the checkout block. Card collection remains on the hosted BCI/BPC page.

## Subscription Gate

Subscription-related code paths are present in:

- `class-subscriptions.php`
- `class-tokens.php`
- `class-renewals.php`

For v1.0.0, this functionality is gated by:

```text
enable_subscriptions = yes
```

The default is `no`.

When disabled:

- The gateway only advertises product support.
- WooCommerce Subscriptions supports are not merged into the gateway.
- Renewal hooks are not registered.
- Initial checkout does not send `clientId` or `FORCE_CREATE_BINDING`.
- Binding capture hooks return without storing subscription token data.

When explicitly enabled, subscription checkout attempts to create a BPC stored credential, stores returned binding metadata, and charges renewal orders through `recurrentPayment.do`. This path is not delivered as production scope for v1.0.0.

This path still requires merchant-specific validation before production use:

- WooCommerce Subscriptions active.
- BCI/BPC stored credential permission.
- `FORCE_CREATE_BINDING` permission.
- `recurrentPayment.do` permission.
- Merchant-initiated transaction permission if required by BPC.
- Initial subscription checkout and renewal success/decline testing.

## HPOS Compatibility

The plugin declares compatibility with WooCommerce custom order tables using:

```php
\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
    'custom_order_tables',
    BCI_WOO_PLUGIN_FILE,
    true
);
```

Order reads and writes use WooCommerce order APIs rather than direct `wp_posts` or `wp_postmeta` SQL.

## Security

Security boundaries in v1.0.0:

- Card data is entered only on the hosted BCI/BPC payment page.
- API passwords and callback tokens are stored in WooCommerce gateway settings.
- Callback notifications require HMAC-SHA256 verification.
- Browser return validates the WooCommerce order key.
- Gateway HTTP requests use WordPress HTTP APIs and normal SSL verification.
- Logs avoid credentials and full PAN values.
- Admin AJAX actions require nonces and merchant capability checks.

## Release State

The one-off payment matrix has been validated against BPC sandbox using the EUR override:

- Payment successful, customer clicks Return.
- Payment successful, customer closes page.
- No payment, customer closes page.
- No payment timeout, customer clicks Return.
- No payment timeout, customer closes page.
- Payment declined, customer clicks Return.
- Payment declined, customer closes page.

The release still requires final packaging, clean install smoke testing, production merchant configuration confirmation, and screencast/handover assets before delivery.
