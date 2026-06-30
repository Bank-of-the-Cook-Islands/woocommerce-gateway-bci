# TakuEcom - BCI Payments for WooCommerce v1.0.0 Release TODO

## Must Finish Before Release

- [ ] Record short screencast walkthrough:
  - Plugin upload/activation.
  - Gateway settings overview.
  - Sandbox credentials and Test mode.
  - Callback URL setup.
  - Connection test.
  - Sandbox checkout payment.
  - Where to find logs.

- [ ] Finalise merchant setup guide:
  - Confirm screenshots are current.
  - Confirm wording is suitable for non-technical merchants.
  - Explain that sandbox defaults to EUR and the selected currency must match the BPC Dev Merchant Portal.

- [ ] Build final release ZIP:
  - Package only `woocommerce-gateway-bci`.
  - Exclude `node_modules`, `test-runs`, Playwright scripts, Docker files, and `my-plugin` test helper.
  - Confirm ZIP installs through WordPress plugin upload.

- [ ] Fresh install smoke test:
  - Activate plugin on clean WordPress/WooCommerce site.
  - Open settings page.
  - Save sandbox settings.
  - Run sandbox connection test.
  - Confirm checkout payment method appears.

- [ ] Production configuration checklist:
  - Confirm live API login/password.
  - Confirm live callback token.
  - Confirm production callback URL in BCI merchant portal.
  - Confirm callback events enabled: Deposited, Approved, Reversed, Refunded, Declined by timeout.
  - Confirm Test mode is disabled only when ready for real payments.
  - Confirm Sandbox Currency matches the BPC Dev Merchant Portal.

## Already Done

- [x] One-off payment success, customer clicks return.
- [x] One-off payment success, customer closes page.
- [x] No payment, customer closes page.
- [x] No payment timeout, customer clicks return.
- [x] No payment timeout, customer closes page.
- [x] Payment declined, customer clicks return.
- [x] Payment declined, customer closes page.
- [x] Default sandbox EUR currency verified with BPC request currency `978`.
- [x] Release test report written.
- [x] Requirements checklist written from Word brief.

## Conditional / Not Required For Core v1.0.0

- [ ] WooCommerce Subscriptions full validation before enabling/advertising recurring payments:
  - Initial subscription checkout sends `clientId` and `FORCE_CREATE_BINDING`.
  - BPC returns `bindingId`.
  - Binding metadata stores on order and subscription.
  - Renewal success via `recurrentPayment.do`.
  - Renewal decline/failure path.
  - Missing binding failure path.

- [x] Gate recurring functionality off by default for v1.0.0.
- [ ] Confirm whether BCI wants subscriptions included in post-v1.0.0 marketing/release notes after validation.

## Handover Items

- [ ] Final plugin ZIP.
- [ ] `docs/merchant-setup-guide.md`.
- [ ] `docs/release-test-report-v1.0.0.md`.
- [ ] `docs/requirements-v1.0.0.md`.
- [ ] Screencast video file or share link.
- [ ] Support notes for 60-day post-deployment bug-fix period.
