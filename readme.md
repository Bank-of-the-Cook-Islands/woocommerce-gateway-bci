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

Live payment requests are sent as NZD (`554`), and the gateway hides itself at checkout when live mode is active and the WooCommerce store currency is anything else, so a store priced in another currency cannot silently collect its totals as NZD. The BPC development environment defaults to EUR (`978`), so enabling **Use sandbox credentials and endpoint** automatically sends sandbox checkout and renewal requests as EUR. If the development merchant is configured for NZD instead, select NZD under **Sandbox currency** and make the matching change in the BPC Dev Merchant Portal.

## Setup

1. Upload and activate `woocommerce-gateway-bci`.
2. Go to WooCommerce > Settings > Payments.
3. Open TakuEcom - BCI Payments.
4. Enable Use sandbox credentials and endpoint while configuring sandbox credentials. Sandbox requests default to EUR.
5. Keep Sandbox currency set to EUR unless the development merchant currency has been changed in the BPC Dev Merchant Portal.
6. Enter the sandbox API login, API password, and callback token.
7. Copy the callback URL into the BCI merchant portal.
8. Use Static callbacks, POST, Symmetric signing, and enable Deposited, Approved, Reversed, Refunded, and Declined by timeout events.
9. Save settings, then run the sandbox connection test.
10. Place a sandbox WooCommerce order and confirm the order status updates automatically.
11. Repeat the credential and callback setup for live before disabling Test mode.

See `docs/merchant-setup-guide.md` for a merchant-friendly setup walkthrough. `docs/Merchant Setup Guide.docx` and `.pdf` are the handover copies of that guide, generated from it by `scripts/build-docs.php`.

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

`docs/architecture.md` describes the module layout and payment flows.

Tests are standalone self-contained PHP scripts in `tests/` — no PHPUnit, no database, no network. Each stubs the WordPress and WooCommerce surface it needs and throws an uncaught exception on failure:

```bash
for t in tests/test-*.php; do php "$t"; done
```

The standalone tests stub WordPress, so they cannot catch anything that only breaks when WordPress actually loads the plugin. `tests/wordpress/run.sh` covers that: it boots WordPress and WooCommerce in throwaway containers, installs the plugin from the same file list a release ZIP ships, and asserts the gateway registers, its settings round-trip, the callback route answers over HTTP, and nothing lands in the debug log. It needs only docker:

```bash
tests/wordpress/run.sh
```

Set `BOOT_TEST_PORT=8080` to leave the site published for a look around, or `BOOT_TEST_SABOTAGE=1` to break the plugin on purpose and confirm the harness still fails.

CI runs on every pull request: `php -l` over every file and the full test suite in a PHP 8.3 container, the WordPress boot test, plus a full-history gitleaks secret scan with a card-PAN rule (`.gitleaks.toml`). Release packaging is `php scripts/build-release.php <ref>`, which archives the committed ref — not the working tree — and includes the merchant setup guide the admin settings link to. The ZIP lands in `dist/` unless `--output=<directory>` says otherwise.

Before tagging a release, run `php scripts/build-docs.php` so the Word and PDF handover copies match the markdown guide and carry the new version on their cover. The exports are generated, so edit the markdown rather than the binaries.

## Licence

This plugin is licensed under the [GNU General Public Licence v3.0](https://opensource.org/licenses/GPL-3.0).

---

**Author:** Bank of the Cook Islands — [bci.co.ck](https://bci.co.ck) — cash@bci.co.ck
**Version:** 1.0.2

---

## Changelog

### 1.0.2

- Expanded the project README and added the BPC integration and API reference documentation.

### 1.0.1

- Fixed Cook Islands checkout registration by omitting the unsupported optional billing state from BPC payer data.
