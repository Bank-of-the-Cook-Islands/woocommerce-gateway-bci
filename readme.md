# TakuEcom - BCI Payments for WooCommerce

Official Bank of the Cook Islands TakuEcom payment gateway for WooCommerce.

This plugin adds a BCI TakuEcom redirect checkout for accepting card payments. Card data is entered on the BCI-hosted secure payment page, then WooCommerce verifies the transaction through `getOrderStatusExtended.do` before marking the order paid.

## Requirements

- WordPress 5.0 or later
- WooCommerce 4.0 or later
- PHP 7.4 or later
- A BCI TakuEcom merchant account
- HTTPS on the store site for callback delivery
- WooCommerce Subscriptions only if experimental recurring payments are explicitly enabled after BCI approval

## Gateway Defaults

| Environment | Endpoint |
| --- | --- |
| Live API | `https://securepayments.bci.co.ck/payment/rest` |
| Sandbox API | `https://dev.bpcbt.com/payment/rest` |
| Live recurrent payments | `https://securepayments.bci.co.ck/payment/recurrentPayment.do` |
| Sandbox recurrent payments | `https://dev.bpcbt.com/payment/recurrentPayment.do` |

Payment requests are sent as NZD (`554`) for this BCI release. For BPC sandbox testing only, enable **Sandbox currency override** to send checkout and renewal payment requests as EUR (`978`) while Test mode is enabled.

## Setup

1. Upload and activate `woocommerce-gateway-bci`.
2. Go to WooCommerce > Settings > Payments.
3. Open TakuEcom - BCI Payments.
4. Enable Test mode while configuring sandbox credentials.
5. Enable Sandbox currency override if the BPC sandbox account only accepts EUR.
6. Enter the sandbox API login, API password, and callback token.
7. Copy the callback URL into the BCI merchant portal.
8. Use Static callbacks, POST, Symmetric signing, and enable Deposited, Approved, Reversed, Refunded, and Declined by timeout events.
9. Save settings, then run the sandbox connection test.
10. Place a sandbox WooCommerce order and confirm the order status updates automatically.
11. Repeat the credential and callback setup for live before disabling Test mode.

See `docs/merchant-setup-guide.md` for a merchant-friendly setup walkthrough.

## Order Status Handling

The default paid-order behavior is WooCommerce default:

- Shippable orders usually become Processing.
- Virtual/downloadable orders may become Completed.

Store operators can instead choose Force Processing or Force Completed in the gateway settings.

The plugin does not rely only on the customer's browser return. It also supports signed gateway callbacks and scheduled polling for pending orders.

## Subscriptions

Subscription renewal code paths are present, but disabled by default, not validated, and not delivered as production scope for v1.0. The core v1.0 release is for one-off hosted checkout payments.

When explicitly enabled, WooCommerce Subscriptions support uses stored credentials:

- Initial subscription checkout sends `clientId` and `FORCE_CREATE_BINDING`.
- The returned `bindingId` is stored on the initial order and related subscription.
- Renewal orders are charged with `recurrentPayment.do`.
- WooCommerce Subscriptions remains responsible for subscription schedules and retry rules.

Before enabling sandbox or live renewals, ask BCI support to confirm stored credential, `FORCE_CREATE_BINDING`, `recurrentPayment.do`, and merchant-initiated transaction permissions for the merchant account. Do not advertise subscriptions as production-ready until the recurring-payment checklist has passed for the merchant.

## Logs

Gateway logs are written through WooCommerce logging with source:

```text
BCI_Woo_Plugin
```

Open WooCommerce > Status > Logs and select the matching source/date.

## Development Notes

The gateway ID is `bci_takuecom`. The callback endpoint is:

```text
/wp-json/bci-woo/v1/callback
```

The architecture handoff is available at the workspace root in `WOOCOMMERCE_GATEWAY_BCI_ARCHITECTURE.md`.
