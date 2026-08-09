# TakuEcom - BCI Payments for WooCommerce Architecture

This document describes the current architecture of the BCI TakuEcom WooCommerce gateway.

The production-certified scope is one-off hosted checkout payments. WooCommerce Subscriptions code paths are present, disabled by default, and not yet production-certified.

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

Since v1.0.0: v1.0.1 fixed Cook Islands billing registration, v1.0.2 added BPC API documentation and release packaging, and the unreleased work on `main` fixed five production defects, refactored the code, and added a test suite along with CI.

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
Version: 1.0.2
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
├── .gitleaks.toml
├── .gitea/workflows/
│   ├── ci.yml
│   └── secret-scan.yml
├── assets/
│   ├── bci-logo.png
│   └── js/frontend/blocks.js
├── includes/
│   ├── class-admin.php
│   ├── class-api.php
│   ├── class-callback.php
│   ├── class-config.php
│   ├── class-gateway.php
│   ├── class-log.php
│   ├── class-order-state.php
│   ├── class-payment-resolution.php
│   ├── class-plugin.php
│   ├── class-registration.php
│   ├── class-registration-result.php
│   ├── class-renewals.php
│   ├── class-resolution.php
│   ├── class-scheduler.php
│   ├── class-status-resolver.php
│   ├── class-subscriptions.php
│   ├── class-tokens.php
│   └── blocks/class-blocks-support.php
├── scripts/
│   ├── build-release.php
│   └── build-docs.php
├── tests/
│   ├── test-*.php (standalone, self-contained; one file per contract)
│   └── wordpress/ (boot test: the plugin inside a real WordPress install)
└── docs/
    ├── architecture.md
    ├── merchant-setup-guide.md
    ├── Merchant Setup Guide.docx (generated from the markdown)
    ├── Merchant Setup Guide.pdf (generated from the markdown)
    ├── images/setup/ (setup guide screenshots)
    ├── release-test-report-v1.0.0.md
    ├── requirements-v1.0.0.md
    └── bpc/ (vendored BPC integration and API reference)
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
VERSION = '1.0.2'
TEXT_DOMAIN = 'bci-woo'
GATEWAY_ID = 'bci_takuecom'
OPTION_KEY = 'woocommerce_bci_takuecom_settings'
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
CANCELLATION_HOLD_MAX_MINUTES = 1440
```

`Config` also owns the BPC `STATUS_*` order-status constants, the sanitiser `Config::clean()`, and the card-label masker `Config::mask_pan()` — the version test in `tests/test-version-consistency.php` pins `VERSION` to the plugin header and the readme.txt stable tag.

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

For one-off payments, `_bci_woo_md_order`, `_bci_woo_order_number`, and `_bci_woo_environment` are the critical fields. `Registration` persists them through `Order_State::record_registration()` immediately after `register.do` returns successfully, and before the customer is redirected, so browser return, callbacks, manual checks, and scheduled recovery can resolve the order later. A registration that fails writes none of them.

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

`Gateway::init_form_fields()` is the settings schema, and `Gateway` renders every custom field type that schema declares, because `WC_Settings_API` dispatches `generate_{type}_html` on the gateway instance. `Admin` (`class-admin.php`) answers the admin AJAX actions behind the buttons — connection test, manual pending-order check, subscription readiness — and renders the two panels the gateway hands to it by name: the guided setup and the readiness test. Every BPC request behind those actions is made by `Api`, so endpoints, timeout, credentials and the credential-rejection heuristic have a single home.

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
- `get_bindings()` posts form data to `/getBindings.do` to list a client's stored credentials.
- `test_connection()` performs a lightweight status request to verify credentials.

The one-off payment release path uses `register_payment()` and `get_order_status()`. `recurrent_payment()` is present for the gated subscription integration.

Every call is transport only: it returns the decoded response array or a `WP_Error` and does not decide what an answer means. `register_payment()` used to be the exception — it validated the registration response and threw, which split one registration outcome between the API client and the gateway. What a registration answer means now belongs to `Registration`.

`test_connection()` is what the admin connection-test button runs, and `get_bindings()` what the subscription readiness probe runs. `test_connection()` returns `success`, `message`, and `raw` — the decoded gateway response, present whenever the gateway answered at all — so the caller can tell a rejected credential from an endpoint that was never reached and can quote the gateway's own wording.

## Currency Handling

The BCI-branded gateway sends NZD (`554`) for live checkout and renewal payment requests. This matches the brief requirement that live plugin currency is set to NZD.

```text
NZD = 554
```

Because the amount is sent as the raw order total, a live store priced in any other currency would have that number collected as NZD. `Gateway::is_available()` therefore hides the gateway at checkout whenever live mode is active and the WooCommerce store currency is not NZD, and an admin notice explains why. Sandbox is exempt because the BPC development merchant deliberately settles in EUR.

The same availability check hides the gateway when the active environment has no API login and password saved, so customers cannot select a method that can only fail after submitting. `process_payment()` repeats the check as a safety net for any path that bypasses `is_available()`, passing the order's own currency to `Gateway::availability_error()` because the request is built from that rather than from the store currency. The two diverge when a multi-currency plugin does not restore the order currency on the order-pay endpoint, or when an admin changes the store currency while unpaid orders exist.

If an order currency other than NZD still reaches the request builder, live mode sends NZD and logs that the order currency was ignored for the BCI request.

When Test mode is enabled, payment requests use the `sandbox_currency` setting regardless of the WooCommerce order currency. It defaults to EUR (`978`) because the BPC development environment defaults new merchants to EUR. Operators may select NZD (`554`) only after configuring the development merchant to use the same currency in the BPC Dev Merchant Portal. The legacy `sandbox_force_eur_currency=yes` setting is still interpreted as EUR when `sandbox_currency` has not yet been saved.

The currency belongs to the environment a request is addressed to rather than to the setting. `Api::payment_currency_code()` and `Api::payment_currency_to_numeric()` therefore take an optional environment, defaulting to `Api::current_environment()`: checkout registers and pays wherever the store is pointed right now, so it omits the argument. `Renewals` passes the environment it resolved from the order state, the same one it sends the charge to, so a subscription created in sandbox is still charged the sandbox currency against the sandbox host after Test mode is switched off, and a live subscription is not charged the sandbox currency while Test mode is on.

## One-Off Payment Flow

1. Customer places a WooCommerce order using `bci_takuecom`.
2. WooCommerce creates the order in Pending Payment.
3. `Gateway::process_payment()` checks that the gateway may be offered for this order and hands it to `Registration::register()`.
4. `Registration` builds the request and the API client calls `/register.do`.
5. `Registration` decides whether the answer is a payment: a `WP_Error`, a non-zero `errorCode`, or a response missing `formUrl` or `orderId` is a failure, and the order is left exactly as it was.
6. On success `Registration` records BPC `orderId`/`mdOrder`, the merchant `orderNumber`, the selected environment, and any registered `clientId`, and saves the order before returning the payment form URL.
7. `Gateway::process_payment()` turns that result into a checkout notice or a redirect.
8. Customer is redirected to the BCI/BPC hosted payment page.
9. The customer completes, declines, abandons, or times out on the hosted page.
10. Browser return, callback, manual check, or scheduled recovery queries `/getOrderStatusExtended.do`.
11. `Status_Resolver` maps BPC status to WooCommerce order state.

`Registration` (`class-registration.php`) owns the whole of step 4 to step 6, and `Registration_Result` (`class-registration-result.php`) is what it answers with. Request construction, the billing payer rules, what a `/register.do` answer is allowed to mean, and the reference the order keeps are one decision with one owner, because they add up to a single invariant: an order that has been sent to a hosted payment form knows the `mdOrder`, the `orderNumber` a callback will quote, and the environment both belong to, and knew them before the customer was redirected. A registration that fails records no gateway reference — only a human-facing order note — so there is no partial state for the callback, the scheduler, or a renewal to act on.

Classic checkout and Checkout Blocks both reach it through `Gateway::process_payment()`; the blocks integration supplies the payment method's appearance and availability only.

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
jsonParams
email
billingPayerData
```

The amount is sent in minor units. The order number is compact and unique per registration attempt, because a customer can abandon the hosted form and pay again from the order-pay page and BPC rejects a repeated `orderNumber`. The description is short and does not include card data. `email` and `billingPayerData` are sent only when the order has them.

`billingPayerData` carries the billing city, country, address lines, and postal code, each stripped of markup and cut to 50 characters, with empty fields dropped rather than sent blank. `billingState` is omitted for Cook Islands addresses: CK has no ISO subdivisions, so a WooCommerce island name sent as a state is rejected by BPC and takes the whole registration with it.

When experimental subscriptions are disabled, subscription-specific parameters are not added.

When experimental subscriptions are explicitly enabled and the order contains a subscription, `Tokens::add_binding_params()` adds the following through the `bci_woo_register_payment_params` filter, which `Subscriptions` hooks only at that point. `Registration` has no opinion of its own about whether an order wants a stored credential; it records the `clientId` that was registered:

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
- Ignores terminal orders where no resolution is needed, per `Payment_Resolution::is_resolvable()`. Cancelled orders remain resolvable so a late Deposited callback can recover a payment completed after WooCommerce auto-cancelled the unpaid order, and paid orders remain resolvable so a portal refund or reversal lands.
- Fires `bci_woo_callback_received`.
- Resolves the latest status through `Payment_Resolution`.

Unknown orders return HTTP 200 after logging to avoid repeated gateway retries for an order the store cannot resolve. Invalid signatures return an error response.

## HMAC Verification

The callback signature check removes `checksum` and `sign_alias`, sorts remaining parameters by key, signs the semicolon-delimited `key;value;` string with the callback token, and compares the uppercase HMAC-SHA256 digest using `hash_equals()`.

Both live and sandbox callback tokens are accepted because BPC callbacks do not always include a reliable environment marker.

BPC computes the checksum over exactly the parameters it sends, so a query variable appended to the callback request by the store's own stack (security plugin, WAF token, tracking variable) would break the digest. If verification against the full parameter set fails, the handler retries against only the BPC-documented callback parameters and logs which foreign parameters were ignored. Both digests use the shared HMAC token, so the retry does not weaken authentication.

## Status Resolution

`Payment_Resolution` is the entry point every payment resolution goes through, and `Status_Resolver` is what it drives. Browser return, callbacks, scheduled checks, the unpaid-order cancellation hold, and the admin manual check are adapters: each finds its order however its surface finds one, asks `Payment_Resolution::is_resolvable()`, and reports the outcome as a redirect, an HTTP status, a log line or an AJAX payload. Renewals drive `Status_Resolver` directly, because a renewal charge answers with its own payload rather than an order to look up.

`Payment_Resolution::is_resolvable()` is the only rule about which orders are worth asking the gateway about:

- An order with no stored `mdOrder` was never registered at BPC, so there is nothing to ask about.
- A poll — browser return, scheduled check, cancellation hold — asks only about orders still open: `pending`, `failed`, `on-hold`, or `cancelled`. Cancelled is on that list so a payment completed after WooCommerce auto-cancelled the unpaid order can still be recovered.
- A callback is BPC announcing a change, so it is answered for a paid order too. That is how a refund or reversal taken in the merchant portal reaches WooCommerce.

Resolution itself is split in two:

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

Only orders `Payment_Resolution::is_resolvable()` accepts are resolved. This prevents unrelated failed or pending WooCommerce orders from being touched.

The admin manual "Check Pending Orders" button runs the same sweep by calling `Scheduler::check_pending_orders()`, and reports the count it returns.

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

## Tests and CI

Tests are standalone self-contained PHP scripts in `tests/`, one file per contract — no PHPUnit, no database, no network. Each stubs the WordPress and WooCommerce surface it needs and throws an uncaught exception on failure, so a plain loop runs the suite:

```bash
for t in tests/test-*.php; do php "$t"; done
```

CI (`.gitea/workflows/ci.yml`) runs `php -l` over every PHP file and the full suite inside a throwaway `php:8.3` container on every pull request; new `tests/test-*.php` files are picked up automatically, so a test only guards the codebase once it exists — keep tests self-contained so the glob stays safe. A separate workflow (`secret-scan.yml`) runs gitleaks over the full repository history with a card-PAN rule on top of the default secret rules (`.gitleaks.toml`); the `docs/bpc/` reference is path-allowlisted because it carries the vendor's own example PANs and sample keys.

## Release State

The v1.0.0 one-off payment matrix was validated against BPC sandbox using its default EUR currency:

- Payment successful, customer clicks Return.
- Payment successful, customer closes page.
- No payment, customer closes page.
- No payment timeout, customer clicks Return.
- No payment timeout, customer closes page.
- Payment declined, customer clicks Return.
- Payment declined, customer closes page.
