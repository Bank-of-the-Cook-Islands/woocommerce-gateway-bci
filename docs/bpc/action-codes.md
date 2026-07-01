# Refusal Reasons & Action Codes

There may be different reasons for the rejection. You need to check the action code that comes in the response. Action code is a numeric code of a result received from a processing bank. In addition to order status, action code helps to understand the details of a transaction processing.

## Online Payment Errors

The below table describes action codes existing in the Payment Gateway.

| Action Code | API Message | Description |
|---|---|---|
| -30001 | Operation is pending | The inquiry of Bank processing has started to define the operation status |
| -20010 | Blocked by the limit | The transaction was declined because the payment amount exceeded the limits set by the issuing bank |
| -2200 | It is impossible to get a cryptogram to make a payment | Unable to get a cryptogram (a card token) from a payment system for making a payment |
| -2025 | Card issuer declined payment with RReq | 3DS authentication failed. Make sure that the VPN is turned off and try to repeat the payment. |
| -2024 | Card issuer wants frictionless 3D Secure, but acquirer restricts it | 3DS authentication failed. Card issuer wants to use frictionless 3D Secure, but it is forbidden. |
| -2023 | 3DS Authentication could not be performed by card issuer | 3DS verification failed because there was a technical or other problem. Try to repeat the payment, and then contact the acquiring Bank or (if necessary) the bank that issued the card. |
| -2022 | Cannot perform 3DS operation | Cannot perform 3DS operation |
| -2020 | Incorrect ECI received | Incorrect ECI (electronic commerce indicator) is received from the ACS of the issuer bank. This code means that the received ECI is not valid for the IPS (International Payment System). The rule applies only to MasterCard (available values - 01,02) and Visa (available values - 05,06) |
| -2018 | 3DS Authentication could not be performed by card acquirer | 3DS Authentication cannot be performed by the bank that issued the card. Try to repeat the payment or contact the bank that issued the card. |
| -2014 | Client did not return from the Payment page | The payer did not return from the Payment page, no payment attempts. |
| -2013 | You have exceeded the number of allowed payment attempts | You have exceeded the number of allowed payment attempts |
| -2009 | Client did not return from the ACS server | 3DS Authentication error. The payer did not return from the ACS page of the bank that issued the card. |
| -2007 | The payment has expired | Payment declined. The period allotted for card details entering has expired (by default, the timeout is 20 minutes; session duration may be specified while order registering; if the Merchant has "Alternative session timeout" permission, then timeout duration is specified in Merchant settings) |
| -2006 | Card issuer declined the payment due to an unsuccessful 3DSecure verification | Means that issuing bank rejected authentication (3DS authorisation has not been performed) |
| -301 | Incorrect payment token | Incorrect payment token |
| -100 | Waiting for payment attempt | There were no payment attempts |
| 0 | Request is processed successfully | Payment has been performed successfully |
| 000 | Request is processed successfully | The payment was successful. In some cases, this code may be returned instead of the 0 code, for example, in the refunds block. |
| 1 | Declined. The identity check is required | Proof of identity is necessary for successful completion of the transaction. In case of internet transaction (our case) it is impossible, so transaction is considered as declined |
| 4 | Card blocked for an undisclosed reason | Card blocked for an undisclosed reason |
| 5 | The card was declined for an unknown reason | The card was declined for an unknown reason |
| 20 | Insufficient funds | Insufficient funds |
| 62 | The customer can't use this card to make this payment (it's possible it was reported lost or stolen) | The customer can't use this card to make this payment (it's possible it was reported lost or stolen) |
| 63 | Security violation | Security violation |
| 72 | Sender card is not allowed for this transaction type | Sender card is not allowed for this transaction type |
| 73 | Denied, invalid expiry | Denied, invalid expiry |
| 78 | Invalid/nonexistent account specified | Invalid/nonexistent account specified |
| 82 | The CVC number is incorrect | The CVC number is incorrect |
| 87 | Pre-authorisation time too great | Pre-authorisation time too great |
| 88 | Cryptographic failure/The CVC number is incorrect | Cryptographic failure/The CVC number is incorrect |
| 90 | Response status unknown | Processing returned an internal status code that is not mapped to any of the returnable action codes. |
| 100 | The customer has exceeded the balance, credit limit, or transaction amount limit available on their card | The customer has exceeded the balance, credit limit, or transaction amount limit available on their card |
| 101 | Card expired | Card expired |
| 103 | Card issuer rejected the payment | There is no connection with the Issuing bank. Sales outlet needs to contact Issuing bank |
| 106 | Card is blocked | The maximum number of attempts to enter PIN is exceeded. It is possible that the card is blocked temporarily |
| 109 | Internal merchant terminal configuration is incorrect | Merchant/terminal identifier is incorrect or ACC is blocked on the processing level |
| 110 | The payment amount is invalid or exceeds the allowed amount | The payment amount is invalid or exceeds the allowed amount |
| 111 | The card number is incorrect | The card number is incorrect |
| 116 | The card has insufficient funds to complete the purchase | Transaction amount exceeds the available balance of the selected account |
| 117 | Incorrect PIN | Incorrect PIN-code (not for Internet transactions) |
| 119 | Security violation | Operation declined. Contact the bank that issued the card. |
| 120 | Card issuer rejected the payment | Refusal to perform the operation - the transaction is not allowed by the Issuing bank. Response code of the IPS - 57. Reasons for rejection should be specified at the Issuing bank |
| 121 | The customer has exceeded the transaction amount limit available on their card | The customer has exceeded the transaction amount limit available on their card |
| 123 | The customer has exceeded the transaction amount limit available on their card | The customer has performed the maximum number of transactions available on their card and tries to perform another one |
| 124 | Technical error | Technical error (in case of reverse/refund attempt) |
| 125 | The card number is incorrect | The card number is incorrect |
| 151 | Transaction failed. Declined by issuer fraud | The operation was rejected by fraud monitoring of the bank that issued the card. Expect a call from a Bank employee. |
| 181 | Service forbidden | Service forbidden |
| 203 | Card is lost | Card is lost |
| 204 | Card is lost | Card is lost |
| 208 | Card issuer considers it lost | Card issuer considers it lost |
| 212 | Participant blocked | Participant blocked |
| 239 | Terminate subscription | Terminate subscription |
| 240 | Recurring transaction was declined because the cardholder stopped that recurring payment transaction | Recurring transaction was declined because the cardholder stopped that recurring payment transaction |
| 555 | Restriction on the card (the issuing bank has prohibited online transactions on the card) | Restriction on the card (the issuing bank has prohibited online transactions on the card) |
| 814 | Limit reached for total number of txns in cycle, independent of transaction category. (Activity count limit exceeded) | Limit reached for total number of txns in cycle, independent of transaction category. (Activity count limit exceeded) |
| 823 | Card is reported stolen | Card is reported stolen |
| 902 | The customer has exceeded the balance, credit limit, or transaction amount limit available on their card | The customer has exceeded the balance, credit limit, or transaction amount limit available on their card |
| 903 | The customer has exceeded the balance, credit limit, or transaction amount limit available on their card | The customer has exceeded the balance, credit limit, or transaction amount limit available on their card |
| 904 | Invalid message format | The message format is incorrect in terms of the issuing bank |
| 906 | Expired card | Expired card |
| 907 | Cannot contact issuer | There is no connection with the Issuing bank. Authorisation in stand-in mode is not allowed for this card number (this mode means that the Issuing bank is unable to connect to the IPS, and therefore the transaction can be either offline with further unloading to back office, or it can be declined) |
| 909 | The card was declined for an unknown reason | The card was declined for an unknown reason (general system error, detected by the payment network of the issuer bank) |
| 911 | Payment declined. Please, contact the merchant. | Payment declined. Please, contact the merchant. |
| 912 | Issuing bank unavailable | Issuing bank is not available. |
| 913 | Wrong message format | The message format is incorrect in terms of IPS |
| 914 | Original transaction not found | Transaction is not found (when sending a completion, reversal or refund request) |
| 916 | Unable to process | Unable to process the transaction |
| 920 | Card restrictions | The card has restrictions. The code is received from the Bank processing. |
| 941 | Invalid Merchant ID | Invalid Merchant ID |
| 950 | Reconcile error | Reconcile error |
| 968 | Original amount incorrect | Original amount incorrect |
| 998 | The request has timed-out | The request has timed-out |
| 999 | The payment was declined due to suspected fraud | The payment was declined due to suspected fraud |
| 1112 | Card retry exceeded with current PAN and Expiry | Card retry exceeded with current PAN and Expiry |
| 1113 | Card retry exceeded with current PAN | Card retry exceeded with current PAN |
| 1114 | Card retry exceeded for binding | Card retry exceeded for binding |
| 1115 | Retry exceeded for order | Retry exceeded for order |
| 1116 | Card retry exceeded for terminal | Card retry exceeded for terminal |
| 1117 | Card retry exceeded for merchant | Card retry exceeded for merchant |
| 1118 | Card retry exceeded for specified amount and currency | Card retry exceeded for specified amount and currency |
| 1120 | Refund in progress | Refund in progress, refresh the page to get the current refund status. |
| 1434 | The card was declined by card issuer because the transaction requires 3D Secure authentication | The card was declined by card issuer because the transaction requires 3D Secure authentication |
| 2002 | Incorrect operation | An attempt to perform the operation that is not allowed for this order status or not applicable for the payment method. |
| 2003 | You can't make payments without 3D Secure | You can't make payments without 3D Secure |
| 2012 | The order has already been cancelled | The order has already been cancelled |
| 2016 | 3D-Secure is forbidden | 3D-Secure payment is necessary, but the Merchant does not have a permission for 3D-Secure payment |
| 2023 | Thread limit is exceeded | The request queue for processing has exceeded the allowed limit |
| 2030 | Card is blocked | Card is blocked |
| 4005 | Declined by merchant | Declined by the Merchant |
| 4032 | Card issuer declined payment with ARes = A | The 3DS authentication attempt was completed, but the authentication failed. Try to repeat the payment or contact the bank that issued the card. |
| 8204 | Duplicate order | Duplicate order |
| 71015 | Some payment data is invalid | Some payment data is invalid |
| 151018 | Decline. Processing timeout | The timeout in the Bank's processing has expired. Sending the payment failed |
| 151019 | The payment method failed due to a timeout | The timeout in the Bank's processing has expired. The transfer was successful, but no response was received from the bank. |

## Card Payment Errors

The below table describes action codes specific for processing centre of the bank.

| Action Code | Description |
|---|---|
| 00 | Successful transaction |
| 01 | Call issuer |
| 02 | Requested Invalid Identifiers |
| 03 | Invalid Merchant ID |
| 04 | Invalid card, capture |
| 05 | Do not honour transaction |
| 08 | Approve with identification |
| 09 | Invalid store number |
| 10 | Invalid currency |
| 12 | Transaction needs to be entered again |
| 12 | Invalid transaction - retry |
| 13 | Cannot process amount. This code is only for truly erroneous amounts, i.e, exceeds machine capabilities or $0 cash withdrawal. No limits involved. |
| 14 | Invalid account - retry |
| 14 | Forced post: No card on file |
| 15 | The card is already active |
| 16 | The card is not active |
| 30 | The message received was not within standards |
| 31 | Issuer inoperative |
| 31 | SmartVista not permitted to stand in |
| 32 | Unknown issuer BIN |
| 33 | Card expired, capture card |
| 33 | Card expired |
| 36 | Account restricted, capture card |
| 37 | Call Security - Capture |
| 37 | Call Acquirer Security |
| 41 | Lost Card - Capture |
| 43 | Stolen Card - Capture |
| 51 | Insufficient funds - retry |
| 55 | Incorrect PIN, foreign. |
| 57 | Not permitted |
| 57 | Transaction not permitted by law |
| 57 | Account restricted |
| 61 | Negative auth usage cycle limit exceeded |
| 61 | Card's ATM or EPAY cycle limit exceeded |
| 61 | Account's ATM or EPAY cycle limit exceeded |
| 62 | Bad card (on_us) |
| 65 | Limit reached for total number of txns in cycle, independent of transaction category. |
| 68 | Timer time out (used generally for issuers other than networks, i.e. a host) |
| 75 | Excessive pin failures |
| 76 | Wrong Pin, Excessive pin failures |
| 76 | Excessive PIN failures, do not capture |
| 77 | The card has NOT ANY accounts |
| 78 | Original transaction could not be found |
| 79 | Original transaction has been reversed |
| 81 | CVV/CVC processing error |
| 82 | Invalid CVV/CVC |
| 85 | Conditional approval. Additional consumer verification must be performed. |
| 90 | Response status unknown |
| 91 | Service not available |
| 92 | Invalid Payment Parameter |
| 93 | Service blocked |
| 94 | Duplicate transmission |
| 95 | This can mean several things i.e. did not receive a tx amount being reversed greater than orig. |
| 96 | Error (usually in pinblock translation) |
| 96 | Cannot process transaction |
| 96 | Forced post: no account on file |
| 96 | System Malfunction |
| 97 | Service not allowed for client |
| 98 | Invalid insurance number |
| A1 | Service is already binded |
| A2 | Service is not binded |
| A3 | Invalid service data |
| A4 | MAC error |
| A5 | Debts absence. |
| A6 | Invalid payment data |
| A7 | Additional information required |
| A8 | No such object in system |
| A9 | Object is not created in system |
| AA | Object is already created in system |
| AB | Invalid CVV2 |
| AC | CVV2 processing error |
| AD | Incorrect Customer ID or Cardholder ID. May be used to request the payer address and personal information. |
| AE | Authorisation of the transaction still not complete. Check its status later. |
| AF | Bad characters in PAN |
| AG | Account is already bound to card |
| B1 | Withdrawal limit exceeded, retry |
| B2 | Card is Restricted, denied. |
| B3 | Command to capture card from FRAUD |
| B4 | Command to block and capture card from FRAUD |
| B5 | Pre-Authorisation transaction already has transaction of completion. |
| B6 | Amount between transaction of completion and transaction of Pre-Authorisation more than fixture in percentage terms. |
| B7 | Original transaction of Pre-Authorisation has no been found for transaction of Completion. |
| B8 | Invalid terminal sequence number. |
| B9 | Batch totals from POS terminal are wrong. |
| BA | For Brazil: blocked, first used or special condition - new cardholder not activated or card is temporarily blocked |
| C0 | Approved commercial |
| C1 | Incorrect parameters. Reenter required |
| C2 | Bill pay invalid invoice |
| C3 | Bill pay invoice expired |
| C4 | Bill pay invoice already paid |
| F1 | Declined by Fraud Monitoring |
| P0 | Partial approval |
| P1 | Purchase only, no cash back allowed |
| P2 | Only EC part of transaction is approved |
| R0 | Recurring transaction was declined because the cardholder stopped that recurring payment transaction. |
| R1 | Recurring transaction was declined because the cardholder stopped all recurring payment transactions for a merchant account. |
| R3 | All recurring payments have been cancelled for the card number in the request. |
| S1 | Additional customer authentication required |
| S2 | PIN data required |
| TS | Terminate Subscription. |
