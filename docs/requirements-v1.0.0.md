# TakuEcom - BCI Payments for WooCommerce v1.0.0 Requirements Checklist

## Source

This checklist is derived from `TakuEcom Wordpress Plugin Brief.docx` dated in the project workspace.

The brief separates the work into:

- Core plugin scope: items 1-4 and 6-7.
- Extended recurring payments scope: item 5, quoted separately.

## Release Position

v1.0.0 is ready for release candidate packaging for one-off WooCommerce checkout, subject to final packaging and production merchant configuration.

WooCommerce Subscriptions / recurring payment code paths are present, but they are disabled by default, not validated, and not delivered as production scope for v1.0.0. Treat subscriptions as experimental until the recurring-payment test checklist passes for a merchant with stored credential and `recurrentPayment.do` permissions.

## Core Requirements

| ID | Requirement | Status | Evidence / Notes |
| --- | --- | --- | --- |
| R1.1 | Provide a BCI-branded WooCommerce payment gateway for TakuEcom. | Complete | Gateway ID `bci_takuecom`; checkout title `Card (BCI TakuEcom)`; BCI logo asset included. |
| R1.2 | Use BCI production payment endpoint. | Complete | Live endpoint configured as `https://securepayments.bci.co.ck/payment/rest`. |
| R1.3 | Provide BPC sandbox/test payment endpoint. | Complete | Sandbox endpoint configured as `https://dev.bpcbt.com/payment/rest`. |
| R1.4 | Currency is set to NZD. | Complete | Live payment requests send NZD `554`; sandbox defaults to EUR `978` to match the BPC development environment and can be set to NZD when the Dev Merchant Portal matches. |
| R1.5 | Keep simple test/live mode toggle. | Complete | Gateway setting `Test mode` selects sandbox vs live credentials/endpoints. |
| R1.6 | Support the BPC sandbox currency. | Complete | Test mode defaults to EUR `978`; the `Sandbox currency` dropdown can select NZD `554` when configured identically in the BPC Dev Merchant Portal. |
| R2.1 | Replace stock settings with guided setup. | Complete | Guided setup panel in gateway settings; merchant setup guide included. |
| R2.2 | Provide clear validation/errors during setup. | Complete | Connection tests, readiness checks, WooCommerce notices, and gateway log messages are present. |
| R2.3 | Link to support/setup materials. | Complete | Merchant setup guide and setup guide URL support are included. |
| R2.4 | Provide connection test button. | Complete | Live and sandbox connection test controls call BPC status endpoint. |
| R3.1 | Do not rely solely on customer browser return. | Complete | Browser return, signed callbacks, and scheduled pending-order polling all resolve against `getOrderStatusExtended.do`. |
| R3.2 | Poll pending orders after configurable threshold. | Complete | Scheduler checks pending and recently failed BCI orders; threshold settings are configurable. |
| R3.3 | Sync gateway reference/invoice number to WooCommerce. | Complete | Stores `_bci_woo_md_order` and `_bci_woo_order_number` on orders immediately after registration. |
| R4.1 | Implement server-side callback endpoint. | Complete | REST endpoint `/wp-json/bci-woo/v1/callback`. |
| R4.2 | Verify callback authenticity. | Complete | HMAC-SHA256 symmetric checksum verification implemented. |
| R4.3 | Update WooCommerce status from gateway response. | Complete | Status resolver maps captured, declined, refunded, reversed/cancelled, and pending statuses. |
| R4.4 | Log callbacks and status transitions. | Complete | WooCommerce logs use source `BCI_Woo_Plugin`; logs include registration/status requests and transitions. |
| R6.1 | Merchant setup guide for non-technical users. | Complete | `docs/merchant-setup-guide.md`. |
| R6.2 | Short screencast walkthrough. | Drafted | `docs/screencast-outline.md`; actual recording still required if this is a delivery artifact. |
| R7.1 | Source code maintained with comments and README. | Complete | Plugin repo includes `readme.md`, `readme.txt`, docs, and scoped comments. |
| R7.2 | Plugin installable by standard WordPress plugin upload. | Pending final package | Build final ZIP from plugin folder excluding test artifacts. |
| R7.3 | Post-deployment bug-fix support period. | Operational | 60-day support is a commercial/delivery commitment, not code functionality. |

## One-Off Checkout Test Coverage

| Scenario | Status | Evidence |
| --- | --- | --- |
| Payment successful, customer clicks return. | Passed | Order `75`, `wc-completed`, BPC `2 / 0`. |
| Payment successful, customer closes page. | Passed | Order `76`, `wc-completed`, BPC `2 / 0` via server-side status check. |
| No payment, customer closes page. | Passed | Order `77`, immediate `wc-pending`; later recoverable when BPC reports failure. |
| No payment, page times out, customer clicks return. | Passed | Order `78`, `wc-failed`, BPC `6 / -2014`. |
| No payment, page times out, customer closes page. | Passed | Order `79`, `wc-failed`, BPC `6 / -2014`. |
| Payment declined, customer clicks return. | Passed | Order `80`, `wc-failed`, BPC `0 / -2025`. |
| Payment declined, customer closes page. | Passed | Order `81`, `wc-failed`, BPC `0 / -2025`. |

Detailed evidence: `docs/release-test-report-v1.0.0.md`.

## Extended Requirement: Recurring Payments

The Word brief lists recurring payments as item 5 and requests separate quotation. The current plugin has recurring-payment code paths present, but they are disabled by default, not validated, and not delivered as production scope for v1.0.0.

| ID | Requirement | Status | Release Note |
| --- | --- | --- | --- |
| X5.1 | Capture and securely store BPC `bindingId` after initial subscription payment. | Code path present, not delivered in v1.0.0 scope | Requires BPC sandbox stored credential permission and WooCommerce Subscriptions test product. |
| X5.2 | Use `recurrentPayment.do` with stored `bindingId` for renewals. | Code path present, not delivered in v1.0.0 scope | Requires BPC sandbox `recurrentPayment.do` permission. |
| X5.3 | Handle renewal failures such as expired card or insufficient funds. | Code path present, not delivered in v1.0.0 scope | Renewal failure path exists; needs sandbox decline tests. |
| X5.4 | Configurable retry attempts and interval. | Settings present, not delivered in v1.0.0 scope | Interaction with WooCommerce Subscriptions retry rules needs confirmation. |
| X5.5 | Notify merchant of failed renewals via WooCommerce admin notifications. | Not delivered in v1.0.0 scope | Failed renewal order notes/logs are present; admin notification UX has not been validated. |

## Recurring Payments Test Checklist

Before advertising subscriptions as production-ready, run these tests:

| Test | Expected Result |
| --- | --- |
| Initial subscription checkout with BCI gateway. | Registration includes `clientId` and `FORCE_CREATE_BINDING`; checkout completes. |
| Binding capture after successful initial payment. | Order and subscription store `_bci_woo_binding_id`, `_bci_woo_client_id`, environment, and non-sensitive card display metadata when returned. |
| Renewal success. | Renewal order calls `recurrentPayment.do`, stores BPC reference, resolves to paid/completed or processing. |
| Renewal decline. | Renewal order becomes failed with a clear note/log message. |
| Missing binding. | Renewal fails gracefully with a clear merchant-facing note; no fatal error. |
| Expired or invalid stored credential. | Renewal fails clearly and remains reconcilable. |
| Retry settings. | Configured retry attempts/interval behave as documented or defer cleanly to WooCommerce Subscriptions retry rules. |

## Out Of Scope

| Item | Status |
| --- | --- |
| Refund processing through WooCommerce. | Out of scope; refunds handled in BPC merchant portal. |
| Multi-merchant or marketplace configurations. | Out of scope. |
| PCI DSS scope expansion / card handling on merchant site. | Out of scope; redirect integration keeps card data on BPC-hosted pages. |

## Final v1.0.0 Release Gates

- [ ] Confirm final production callback URL and token with BCI merchant portal.
- [ ] Confirm live API credentials are entered only on the production site.
- [ ] Confirm Sandbox Currency matches the BPC Dev Merchant Portal before sandbox testing.
- [ ] Build release ZIP from `woocommerce-gateway-bci` only.
- [ ] Exclude `node_modules`, `test-runs`, Playwright scripts, and `my-plugin` test helper from release ZIP.
- [ ] Fresh install the ZIP on a clean WordPress/WooCommerce site.
- [ ] Confirm plugin activation, settings page, checkout payment method, and sandbox connection test.
- [ ] Attach `docs/release-test-report-v1.0.0.md` to the release handover.
