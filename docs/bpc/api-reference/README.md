# BPC Payment Gateway — REST API Reference

> Source: <https://dev.bpcbt.com/en/integration/api/rest.html>

## Contents

| # | File | Section | Key Endpoints | Size |
|---|---|---|---|---|
| 00 | [Overview](00-overview.md) | Overview, Authentication, API URLs, Errors | — | 5K |
| 01 | [API Signature](01-api-signature.md) | API request signature, certificates | — | 11K |
| 02 | [Order Registration](02-order-registration.md) | Order registration & pre-authorisation | `register.do`, `registerPreAuth.do` | 56K |
| 03 | [Payment for Order](03-payment-for-order.md) | Direct payment (internal/external 3DS, Industry Practice) | `paymentorder.do` | 64K |
| 04 | [Instant Payment](04-instant-payment.md) | Instant (one-step) payment | `instantPayment.do` | 48K |
| 05 | [MOTO Payment](05-moto-payment.md) | Mail-order / telephone-order payment | `motoPayment.do` | 22K |
| 06 | [ACS Redirect](06-acs-redirect.md) | Simplified redirect to ACS | `acsRedirect.do` | 2K |
| 07 | [Apple Pay](07-apple-pay.md) | Apple Pay registration & direct | `/applepay/payment.do`, `/applepay/paymentDirect.do` | 52K |
| 08 | [Google Pay](08-google-pay.md) | Google Pay registration & direct | `/google/payment.do`, `/google/paymentDirect.do` | 61K |
| 09 | [Samsung Pay](09-samsung-pay.md) | Samsung Pay registration & direct | `/samsung/payment.do`, `/samsung/paymentDirect.do` | 44K |
| 10 | [Tokenised Payment](10-tokenized-payment.md) | Tokenised payment | `/token/payment.do` | 42K |
| 11 | [Payment Status](11-payment-status.md) | Order status retrieval | `getOrderStatusExtended.do` | 39K |
| 12 | [Order Management](12-order-management.md) | Deposit, reverse, refund, cancel | `deposit.do`, `reverse.do`, `refund.do`, `instantRefund.do`, `decline.do` | 35K |
| 13 | [Stored Credentials](13-stored-credentials.md) | Bindings, recurrent & installment payments | `paymentOrderBinding.do`, `getBindings.do`, `unBindCard.do`, `bindCard.do`, `extendBinding.do`, `createBindingNoPayment.do` | 84K |
| 14 | [3DS Utilities](14-3ds-utilities.md) | Finishing 3DS2 payments | `finish3dsVer2Payment.do`, `continue.do` | 10K |
| 15 | [Miscellaneous](15-miscellaneous.md) | Card verification | `verifyCard.do` | 18K |
| 16 | [Recurring Tasks](16-recurring-tasks.md) | Recurring task CRUD API | `task/create`, `task/modify`, `task/get`, `task/terminate`, `task/activate`, `task/skipPayment` | 30K |
| 17 | [Callback Notifications](17-callback-notifications.md) | Callback types, URLs, processing, code examples | — | 34K |

### By Category

**Getting Started**
- [00 — Overview](00-overview.md) · [01 — API Signature](01-api-signature.md)

**Order Registration**
- [02 — Order Registration](02-order-registration.md)

**Direct Payments**
- [03 — Payment for Order](03-payment-for-order.md) · [04 — Instant Payment](04-instant-payment.md) · [05 — MOTO Payment](05-moto-payment.md) · [06 — ACS Redirect](06-acs-redirect.md)

**Wallets**
- [07 — Apple Pay](07-apple-pay.md) · [08 — Google Pay](08-google-pay.md) · [09 — Samsung Pay](09-samsung-pay.md) · [10 — Tokenised Payment](10-tokenized-payment.md)

**Payment Status & Order Management**
- [11 — Payment Status](11-payment-status.md) · [12 — Order Management](12-order-management.md)

**Stored Credentials**
- [13 — Stored Credentials](13-stored-credentials.md)

**3DS & Miscellaneous**
- [14 — 3DS Utilities](14-3ds-utilities.md) · [15 — Miscellaneous](15-miscellaneous.md)

**Recurring Tasks & Callbacks**
- [16 — Recurring Tasks](16-recurring-tasks.md) · [17 — Callback Notifications](17-callback-notifications.md)
