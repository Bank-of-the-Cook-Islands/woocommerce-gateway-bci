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
- EUR-by-default sandbox currency selection aligned with the BPC development merchant configuration.
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
│   ├── class-order-state.php
│   ├── class-plugin.php
│   ├── class-renewals.php
│   ├── class-resolution.php
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
_bci_woo_binding_missing_note_added
```

`Order_State` (`class-order-state.php`) owns every one of them. It is the only code that names a key or decides the shape of what is stored under one — gateway status is always recorded as an integer, a card expiry always as `YYYYMM`, a card label always masked — so no two callers can disagree about how order state is written. Everything else asks it for what it needs: `Order_State::for($order)->md_order()`, `->environment()`, `->record_status()`, `->is_resolvable()`. The keys themselves are unchanged and stay readable in the database for support queries; values written by earlier versions are normalised on the way out.

For one-off payments, `_bci_woo_md_order`, `_bci_woo_order_number`, and `_bci_woo_environment` are the critical fields. They are persisted immediately after `register.do` returns successfully so browser return, callbacks, manual checks, and scheduled recovery can resolve the order later.

`_bci_woo_environment` records where a payment was taken, and is read back rather than recomputed: an order registered in sandbox is still checked against sandbox after the merchant leaves test mode. A renewal order is created fresh by WooCommerce Subscriptions and carries none of its own, so it inherits the environment from the subscription it renews and records it when it is charged.

Stored credential metadata is only relevant when experimental subscription renewals are explicitly enabled.

## Admin Settings

The gateway settings are grouped into:

- Gateway: enable gateway, checkout title, checkout description, paid order status behaviour.
- Test mode: sandbox/live environment switch; sandbox requests default to EUR (`978`).
- Sandbox currency: selects EUR (`978`, default) or NZD (`554`) to match the BPC Dev Merchant Portal.
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

The BCI-branded gateway sends NZD (`554`) for live checkout and renewal payment requests. This matches the brief requirement that live plugin currency is set to NZD.

```text
NZD = 554
```

Because the amount is sent as the raw order total, a live store priced in any other currency would have that number collected as NZD. `Gateway::is_available()` therefore hides the gateway at checkout whenever live mode is active and the WooCommerce store currency is not NZD, and an admin notice explains why. Sandbox is exempt because the BPC development merchant deliberately settles in EUR.

The same availability check hides the gateway when the active environment has no API login and password saved, so customers cannot select a method that can only fail after submitting. `process_payment()` repeats the check as a safety net for any path that bypasses `is_available()`, passing the order's own currency to `Gateway::availability_error()` because the request is built from that rather than from the store currency. The two diverge when a multi-currency plugin does not restore the order currency on the order-pay endpoint, or when an admin changes the store currency while unpaid orders exist.

If an order currency other than NZD still reaches the request builder, live mode sends NZD and logs that the order currency was ignored for the BCI request.

When Test mode is enabled, payment requests use the `sandbox_currency` setting regardless of the WooCommerce order currency. It defaults to EUR (`978`) because the BPC development environment defaults new merchants to EUR. Operators may select NZD (`554`) only after configuring the development merchant to use the same currency in the BPC Dev Merchant Portal. The legacy `sandbox_force_eur_currency=yes` setting is still interpreted as EUR when `sandbox_currency` has not yet been saved.

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
- Ignores terminal orders where no resolution is needed. Cancelled orders remain resolvable so a late Deposited callback can recover a payment completed after WooCommerce auto-cancelled the unpaid order.
- Fires `bci_woo_callback_received`.
- Resolves the latest status through `Status_Resolver`.

Unknown orders return HTTP 200 after logging to avoid repeated gateway retries for an order the store cannot resolve. Invalid signatures return an error response.

## HMAC Verification

The callback signature check removes `checksum` and `sign_alias`, sorts remaining parameters by key, signs the semicolon-delimited `key;value;` string with the callback token, and compares the uppercase HMAC-SHA256 digest using `hash_equals()`.

Both live and sandbox callback tokens are accepted because BPC callbacks do not always include a reliable environment marker.

BPC computes the checksum over exactly the parameters it sends, so a query variable appended to the callback request by the store's own stack (security plugin, WAF token, tracking variable) would break the digest. If verification against the full parameter set fails, the handler retries against only the BPC-documented callback parameters and logs which foreign parameters were ignored. Both digests use the shared HMAC token, so the retry does not weaken authentication.

## Status Resolution

`Status_Resolver` is shared by browser return, callbacks, scheduled checks, manual recovery, and renewals.

Resolution is split in two:

- `Status_Resolver::classify()` reads a `/getOrderStatusExtended.do` payload and returns a `Resolution` — the outcome, the BPC `orderStatus` and `actionCode`, and, for an error outcome, whether the gateway returned an error code, omitted the order status, or reported a status the plugin does not know. It touches nothing: no order, no WordPress, no logs.
- `Status_Resolver::apply()` carries a `Resolution` into WooCommerce: order state, `_bci_woo_last_status`/`_bci_woo_last_action_code`, binding metadata, order notes, logs, and the `bci_woo_payment_status_resolved` hook.

`resolve()` is the two joined by the status request, and remains what every caller uses.

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

## Unpaid Order Cancellation Hold

WooCommerce auto-cancels unpaid pending orders once the hold-stock window expires, which can strand a paid order in Cancelled when a customer (typically a guest) completes payment on the hosted form late, or when the Deposited callback is delayed.

`Scheduler` hooks the `woocommerce_cancel_unpaid_order` filter and, for BCI orders with a stored `mdOrder`, resolves the gateway status before the cancellation is allowed:

- If the resolver settles the order into a final state (paid, refunded, failed, cancelled), the WooCommerce cancellation is suppressed.
- If the gateway order is still merely Registered, the customer abandoned the hosted form and the cancellation proceeds normally.
- If a payment attempt is in flight (authorised, ACS initiated, pending) or the gateway cannot be queried, the cancellation is held and retried on the next WooCommerce cancellation run, up to `CANCELLATION_HOLD_MAX_MINUTES` (default 1440) after order creation.

Together with the callback resolving cancelled orders, this closes the paid-but-cancelled gap for guest checkouts.

## Logging

`Log` writes through the WooCommerce logger using source:

```text
BCI_Woo_Plugin
```

Logs are used for callback rejection, status check failures, unexpected statuses, renewal failures, and currency override handling. Logs do not include full card data or credential values.

Request bodies are redacted at every depth before logging: credentials, binding IDs, tokens, card fields, the customer email, and the billing payer data are replaced with a placeholder. Gateway response bodies are never logged, because a `getOrderStatusExtended` payload carries binding IDs and card data; an unexpected HTTP status or a malformed JSON body records only `errorCode`, `errorMessage`, and the body length.

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

The setting is read at three registration sites — the gateway constructor, for gateway supports and for the renewal hook, and `Subscriptions::register_hooks()` — plus the saved-card gate in `Tokens`, which one-off payments reach as well and so cannot key off subscription registration. Nothing registered while the gate was open re-reads the setting afterwards, so the gate has one answer per request.

When disabled:

- The gateway only advertises product support.
- WooCommerce Subscriptions supports are not merged into the gateway.
- Renewal hooks are not registered.
- The register-parameter and binding capture hooks are not registered, so initial checkout does not send `clientId` or `FORCE_CREATE_BINDING`, and no subscription token data is stored.
- A one-off payment that returns binding data still records it in order meta, but no WooCommerce payment token is created for the customer.

`Subscriptions` answers what WooCommerce Subscriptions says about an order and registers the hooks. The stored credential itself — building the `clientId` and `FORCE_CREATE_BINDING` pair, capturing the binding, and propagating it to subscriptions and to WooCommerce payment tokens — belongs to `Tokens`.

When explicitly enabled, subscription checkout attempts to create a BPC stored credential, stores returned binding metadata, and charges renewal orders through `recurrentPayment.do`. This path is not delivered as production scope for v1.0.0.

A renewal charge is only a charge: once `recurrentPayment.do` is accepted, the renewal order is resolved through `Status_Resolver` like any other payment, so it reaches the same order states, the same `_bci_woo_last_status` values, and the same `bci_woo_payment_status_resolved` hook. `Renewals` does not interpret gateway statuses itself.

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
- Logs avoid credentials, customer PII, and full PAN values.
- Card labels are masked to the last four digits by `Config::mask_pan()` before they reach `_bci_woo_masked_pan`, so the raw `pan` fallback in a gateway response cannot be persisted unmasked.
- Admin AJAX actions require nonces and merchant capability checks.

## Release State

The one-off payment matrix has been validated against BPC sandbox using its default EUR currency:

- Payment successful, customer clicks Return.
- Payment successful, customer closes page.
- No payment, customer closes page.
- No payment timeout, customer clicks Return.
- No payment timeout, customer closes page.
- Payment declined, customer clicks Return.
- Payment declined, customer closes page.

The release still requires final packaging, clean install smoke testing, production merchant configuration confirmation, and screencast/handover assets before delivery.
