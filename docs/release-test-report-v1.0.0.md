# TakuEcom - BCI Payments for WooCommerce v1.0.0 Release Test Report

## Summary

Plugin version: `1.0.0`

Test date: 2026-05-28

Result: Passed release test matrix for BPC sandbox checkout, browser-return recovery, closed-browser recovery, timeout handling, declined payment handling, and the default sandbox EUR currency.

## Test Environment

| Item | Value |
| --- | --- |
| WordPress | 7.0 |
| WooCommerce | 10.5.3 |
| Plugin | TakuEcom - BCI Payments for WooCommerce |
| Plugin version | 1.0.0 |
| Gateway mode | Sandbox / Test mode |
| BPC endpoint | `https://dev.bpcbt.com/payment/rest` |
| Store currency | NZD |
| BPC request currency | EUR / `978` via the sandbox default |
| Test product | Test Product, 10.00 |
| Test card | BPC sandbox Mastercard ending `5599`, expiry `12/34` |
| Public test URL | Cloudflare quick tunnel |

## Release Settings Verified

| Setting | Value |
| --- | --- |
| Gateway enabled | Yes |
| Test mode | Yes |
| Sandbox API credentials | Present |
| Sandbox callback token | Present |
| Sandbox currency | EUR (default) |
| Payment method at checkout | Card (BCI TakuEcom) |
| Callback route | `/wp-json/bci-woo/v1/callback` |
| Browser return route | `?wc-api=bci_takuecom_return` |

The WooCommerce order currency remains NZD, while BPC registration requests use the sandbox's default EUR `978`. The selected plugin currency must match the BPC Dev Merchant Portal and live behavior remains unchanged.

## Test Matrix

| Test case | User action | WooCommerce order | Expected result | Actual result | BPC status/action | Result |
| --- | --- | ---: | --- | --- | --- | --- |
| Payment Successful | Click Return | `75` | Completed | `wc-completed` | `2 / 0` | Pass |
| Payment Successful | Close Page | `76` | Completed after server-side status check | `wc-completed` | `2 / 0` | Pass |
| No Payment | Close Page | `77` | Pending immediately after close | `wc-pending` immediately; later recoverable as failed | `0 / -100` after recovery | Pass |
| No Payment - Page Times Out | Click Return | `78` | Failed | `wc-failed` | `6 / -2014` | Pass |
| No Payment - Page Times Out | Close Page | `79` | Failed after server-side status check | `wc-failed` | `6 / -2014` | Pass |
| Payment Declined | Click Return | `80` | Failed | `wc-failed` | `0 / -2025` | Pass |
| Payment Declined | Close Page | `81` | Failed after server-side status check | `wc-failed` | `0 / -2025` | Pass |

## Evidence

The automated matrix used Playwright to create WooCommerce checkout sessions, complete or abandon BPC sandbox pages, and then verify WooCommerce order status and BCI gateway metadata.

Evidence files:

```text
../../test-runs/bci-matrix-2026-05-28T21-49-47-054Z/summary.json
../../test-runs/bci-matrix-2026-05-28T22-00-55-647Z/summary.json
```

Automation files:

```text
../../scripts/run-bci-matrix.mjs
../../my-plugin/bci-matrix-test-helper.php
```

The test helper is not part of the release plugin. It only adds `sessionTimeoutSecs` and dynamic callback URL values for automation emails beginning with `automation+matrix`.

## Notes

- The BPC sandbox hosted page displayed EUR amounts, confirming that `currency=978` was accepted.
- Successful payments were resolved both through browser return and by explicit server-side status check after simulating a closed page.
- Timeout cases used a short BPC `sessionTimeoutSecs` value to avoid waiting for the default gateway timeout.
- Immediate "No Payment / Close Page" leaves the WooCommerce order pending, as expected before BPC timeout. A later scheduled recovery can mark it failed once BPC reports an actionable failure status.

## Release Assessment

The v1.0.0 payment flow is ready for release candidate packaging, subject to final merchant configuration checks:

- Live API login/password entered only when BCI is ready to process real payments.
- Live callback token configured in WooCommerce.
- BCI merchant portal callback URL points to the production site.
- Callback events include Deposited, Approved, Reversed, Refunded, and Declined by timeout.
- Sandbox Currency is confirmed against the BPC Dev Merchant Portal before testing; live mode always uses NZD.
