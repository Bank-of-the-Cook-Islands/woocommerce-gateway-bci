> Source: https://dev.bpcbt.com/en/integration/api/rest.html

# Payment status

The most straightforward way to know the status of the payment is to use a dedicated API call:

1. Call getOrderStatusExtended.do;
2. Check the orderStatus field in the response: the order is considered to be payed only if the orderStatus value is 1 or 2.

In case the orderStatus value is 1, there is still a need to capture money. Otherwise, the money will be returned to a cardholder in a week or so.

Another way to check whether the payment was successful or not is to refer to the callback notification.

## Order status

The request used to get the order status is `https://dev.bpcbt.com/payment/rest/getOrderStatusExtended.do`.

When sending the request, you should use the header: `Content-type: application/x-www-form-urlencoded`

Learn more about Refusal reasons.

### Request parameters

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Conditional | `userName` | String [1..30] | Merchant 's API account login (mandatory, unless token is passed). If you pass your login and password to authenticate in the payment gateway, do not pass token parameter. |
|  |  |  |  |
| Conditional | `password` | String [1..30] | Merchant's API account password (mandatory, unless token is passed). If you pass your login and password to authenticate in the payment gateway, do not pass token parameter. |
|  |  |  |  |
| Conditional | `token` | String [1..256] | Value that is used for merchant authentication when requests are sent to the payment gateway (mandatory, unless userName and password are passed). If you pass this parameter, do not pass userName and password. |
|  |  |  |  |
| Conditional | `orderId` | String [1..36] | Order number in the payment gateway. Unique within the payment gateway.  The request must contain either orderId or orderNumber. If both parameters are passed to the payment gateway, orderId has higher priority.   If token is passed to the payment gateway, only orderId must be passed. |
|  |  |  |  |
| Conditional | `orderNumber` | String [1..36] | Order number (ID) in the merchant's system, must be unique for each merchant.  The request must contain either orderId or orderNumber. If both parameters are passed to the payment gateway, orderId is higher priority. |
|  |  |  |  |
| Optional | `language` | String [2] | ISO 639-1 encoded language key. If the language is not specified, the default language specified in the store settings is used. Supported languages: en,el,ro,bg,pt,sw,hu,it,pl,de,fr,kh,cn,es,ka,da,et,fi,lt,lv,nl,sv. |
|  |  |  |  |
| Optional | `merchantLogin` | String [1..255] | To get the order status of a specific merchant instead of the current user, specify the merchant's API account login. Can be used only if you have the permission to see the transactions of other merchants or if the specified merchant is your child merchant. |
|  |  |  |  |

### Response parameters

There are several sets of the response parameters. Which set of parameters is returned in the response, depends on the version of `getOrderStatusExtended` specified in the merchant's settings in the payment gateway.

#### Description of the versions

| Version | Added parameters |
| --- | --- |
| 1 | `orderBundle` |
| 2 | authDateTime terminalId authRefNum |
| 3 | paymentAmountInfo->approvedAmount, depositedAmount, paymentState, refundedAmount bankInfo->bankCountryCode, bankCountryName, bankName |
| 4 | No changes |
| 5 | `refunds` |
| 6 | `chargeback` |
| 7 | cardAuthInfo->secureAuthInfo->paResStatus, veResStatus, paResCheckStatus |
| 8 | cardAuthInfo->paymentSystem, product |
| 9 | `paymentWay` |
| 10 | `depositedDate` |
| 11 | No changes |
| 12 | refundedDate reversedDate |
| 13 | payerData->email,phone,postAddress |
| 14 | `transactionAttributes` |
| 15 | prepaymentMdOrder partpaymentMdOrders |
| 16 | `feUtrnno` |
| 17 | cardAuthInfo->productCategory |
| 18 | `totalAmount` |
| 19 | `avsCode` |
| 20 | bindingInfo->externalCreated |
| 21 | refunds->externalRefundId |
| 22 | No changes |
| 23 | `efectyOrderInfo` |
| 24 | `ofdOrderBundle` |
| 25 | No changes |
| 26 | refunds->approvalCode |
| 27 | `authRefNum` |
| 28 | `pluginInfo` |
| 29 | No changes |
| 30 | cardAuthInfo->secureAuthInfo->aResTransStatus, rReqTransStatus, threeDsProtocolVersion |
| 31 | No changes |
| 32 | No changes |
| 33 | `displayErrorMessage` |
| 34 | orderBundle->cartItems->items->depostedItemAmount,itemPrice |
| 35 | cardAuthInfo->corporateCard |
| 36 | No changes |
| 37 | tii usedPsdIndicatorValue |
| 38 | payerData ->paymentAccountReference |
| 39 | cardAuthInfo -> detokenizedPanRepresentation, detokenizedPanExpiryDate |
| 40 | No changes |
| 41 | `PartialAuthorization` |
| 42 | cardAuthInfo->secureAuthInfo->threeDsType |
| 43 | No parameters added. Removed: cardAuthInfo->secureAuthInfo-> authTypeIndicator |
| 44 | No changes |
| 45 | `tokenizationInfo` |
| 46 | mcc, mvv,paymentFacilitator |

If you don't need any parameters that are returned in the response after switching to the new version, just ignore them.

| Version | Required | Name | Type | Description |
| --- | --- | --- | --- | --- |
| All | Optional | `errorCode` | String [1..2] | Information parameter in case of an error, which may have different code values:  0 value - indicates success of processing; another number value (1-99) - indicates an error for more details of which errorMesage parameter must be inspected. It also can be missing if the result has not caused any error. |
|  |  |  |  |  |
| All | Optional | `errorMessage` | String [1..512] | Information parameter that is an error description in a case of error occurance. errorMessage value can vary, so it should not be hardcoded. Language of the description is set in language parameter of the request. |
|  |  |  |  |  |
| All | Conditional | `orderNumber` | String [1..36] | Order number (ID) in the merchant's system, must be unique for each merchant registered in the payment gateway . If the Order number is generated on the Payment Gateway side, this parameter is not mandatory. |
|  |  |  |  |  |
| All | Optional | `orderStatus` | Integer | The value of this parameter specifies the status of the order in the payment gateway. It is missing if the order has not been found. Below is the list of available values:  0 - order was registered but not paid; 1 - order was authorized only and wasn't captured yet (for two-phase payments); 2 - order was authorized and captured; 3 - authorization canceled; 4 - transaction was refunded; 5 - access control server of the issuing bank initiated authorization procedure; 6 - authorization declined; 7 - pending order payment; 8 - intermediate completion for multiple partial completion. |
|  |  |  |  |  |
| All | Mandatory | `actionCode` | String | Response code from the processing bank. Contains a numeric value. See the list of action codes here. |
|  |  |  |  |  |
| All | Mandatory | `actionCodeDescription` | String [1..512] | actionCode description returned from the processing bank. |
|  |  |  |  |  |
| All | Mandatory | `amount` | Integer [0..12] | Payment amount in minor currency units (e.g. in cents). |
|  |  |  |  |  |
| All | Optional | `currency` | String [3] | ISO 4217 encoded currency key. If not specified, the default value is used. |
|  |  |  |  |  |
| All | Mandatory | `date` | Integer | Order registration date as the amount of milliseconds since 00:00 January 1, 1970 GMT (UNIX time). Example: 1740392720718 (Corresponds to February 24, 2025, 10:25:20 (UTC)). |
|  |  |  |  |  |
| 10+ | Optional | `depositedDate` | Integer | Order payment date as the amount of milliseconds since 00:00 January 1, 1970 GMT (UNIX time). Example: 1740392720718 (Corresponds to February 24, 2025, 10:25:20 (UTC)). |
|  |  |  |  |  |
| All | Optional | `orderDescription` | String [1..600] | Order description passed to the payment gateway during the registration. It is not allowed to fill this parameter with personal data or payment data (card numbers, etc.). This requirement is due to the fact that the order description is not masked in Merchant Portal and log files. |
|  |  |  |  |  |
| All | Mandatory | `ip` | String [1..39] | Buyer's IP address. IPv6 is supported in all requests. (up to 39 characters).  If you use 3DS 2 authentication, we recommend passing a real IP address of the customer in this parameter. This increases conversion rates on the ACS side. |
|  |  |  |  |  |
| 27+ | Optional | `authRefNum` | String [1..24] | Reference number of the payment authorization that has been assigned to it upon its registration. |
|  |  |  |  |  |
| 12+ From 27 mandatory. | Optional | `refundedDate` | Integer | Refunded date and time, shown as the amount of milliseconds since 00:00 January 1, 1970 GMT (UNIX time). Example: 1740392720718 (Corresponds to February 24, 2025, 10:25:20 (UTC)). |
|  |  |  |  |  |
| 12+ | Optional | `reversedDate` | Integer | Reversed date and time, shown as the amount of milliseconds since 00:00 January 1, 1970 GMT (UNIX time). Example: 1740392720718 (Corresponds to February 24, 2025, 10:25:20 (UTC)). |
|  |  |  |  |  |
| 09+ | Mandatory | `paymentWay` | String | Payment method (a payment with entering card data, a stored-credential transaction, etc.). Find more possible values of the parameter. |
|  |  |  |  |  |
| 19+ | Optional | `avsCode` | String | A code of the AVS verification response (checking the address and postal code of the cardholder). Possible values:  A – postal code and address are the same. B – address matches, postal code doesn't match. C - postal code matches, address doesn't match. D - postal code and address don't match. E - data validation is requested, but the result is unsuccessful. F - invalid format of the AVS/AVV verification request. |
|  |  |  |  |  |
| 06+ | Optional | `chargeback` | Boolean | Whether the funds was forcibly returned to the buyer by the bank. The possible values are:  true - funds were reversed; false - funds were not reversed. |
|  |  |  |  |  |
| 02+ | Optional | `authDateTime` | Integer | Authorization date and time, shown as the amount of milliseconds since 00:00 January 1, 1970 GMT (UNIX time). Example: 1740392720718 (Corresponds to February 24, 2025, 10:25:20 (UTC)). |
|  |  |  |  |  |
| 02+ | Optional | `terminalId` | String [1..10] | Terminal identifier in the system that processes the payment. |
|  |  |  |  |  |
| 01+ | Optional | `orderBundle` | Object | Object containing cart of items. The description of the nested elements is given below. |
|  |  |  |  |  |
| 03+ | Optional | `paymentAmountInfo` | Object | Object containing the information on the confirmation amount, debit amount, and refund amount. See nested parameters below. |
|  |  |  |  |  |
| 05+ | Optional | `refunds` | Object | An object containing information about the refund. Available only if there are refunds in the order. See nested parameters below. |
|  |  |  |  |  |
| All | Optional | `cardAuthInfo` | Object | Block with the data about the payer's card. See nested parameters below. |
|  |  |  |  |  |
| 14+ | Optional | `transactionAttributes` | Object | A set of additional transaction attributes. See nested parameters below. |
|  |  |  |  |  |
| 15+ | Optional | `prepaymentMdOrder` | String | The number of the previous prepayment order in the payment gateway. |
|  |  |  |  |  |
| 15+ | Optional | `partpaymentMdOrders` | Array of String | An array of subsequent partial payment orders. |
|  |  |  |  |  |
| 16+ | Optional | `feUtrnno` | Integer [1..18] | FE transaction number. |
|  |  |  |  |  |
| All | Optional | `bindingInfo` | Object | Object containing information on the stored credential with which the payment is performed. See the table with the description of bindingInfo. |
|  |  |  |  |  |
| 23+ | Optional | `efectyOrderInfo` | Object | A block containing information related to EFECTY payment way. See nested parameters below. |
|  |  |  |  |  |
| 28+ | Optional | `pluginInfo` | Object | Present in the response if the payment was made through the payment plugin. See nested parameters below. |
|  |  |  |  |  |
| 33+ | Optional | `displayErrorMessage` | String | Displayed error message. |
|  |  |  |  |  |
| 37+ | Optional | `tii` | String | Transaction initiator indicator. A parameter indicating what type of operation will be carried out by the initiator (Customer or Merchant). See nested parameters below. |
|  |  |  |  |  |
| 37+ | Optional | `usedPsdIndicatorValue` | String | The type of SCA (Strong Customer Authentication) excemption. Contains the value passed in externalScaExemptionIndicator parameter during payment.  LVP – Low Value Payments transaction. You can consider a transaction as low risk based on the transaction amount, the client's transactions per day or the client's total daily amount. TRA – Transaction Risk Analysis transaction, i.e., the transaction that has passed successful anti-fraud check. . |
|  |  |  |  |  |
| 41+ | Conditional | `partialAuthorization` | String [1..255] | Indicator of partial authorization status of the order. Is mandatory if the order details contain partialAuthorization parameter. Allowed values:  REQUESTED - the merchant requested partial authorization, but the authorization has not completed yet. PARTIAL_AMOUNT - the merchant requested partial authorization, and a partial amount was authorized from the client's account with insufficient funds. FULL_AMOUNT - the merchant requested partial authorization, but the client's balance was sufficient and partial authorization was not required, so full amount was authorized. |
|  |  |  |  |  |
| 45+ | Optional | `tokenizationInfo` | Object | A block with the parameters related to Tokenizer service that allows card tokenization via VTS (Visa Token Service) or MCS (Mastercard Checkout Solutions). This block is returned if a tokenized payment took place. See nested parameters. |
| 46+ | Optional | `mcc` | Integer [4] | Merchant Category Code. Using this parameter requires a special permission. You can use only the values from the predefined list of allowed MCC values. Contact the support team for details. |
|  |  |  |  |  |
| 46+ | Optional | `mvv` | String [1..10] | Merchant verification value from Mastercard for tokenized transactions. To pass this parameter, a special setting must be enabled (contact technical support). |
|  |  |  |  |  |
| 46+ | Optional | `paymentFacilitator` | Object | A block with the parameters of a payment facilitator, i.e. a merchant who allows several submerchants to accept payments under its account. This parameter is used if a special setting is enabled (contact the support team). See nested parameters. |

Below are the parameters of the `tokenizationInfo` block (data about card tokenization).

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `tokenId` | String | Unique token identifier generated by Tokenizer service. |
| Mandatory | `tokenizationProvider` | String | Tokenization provider. Allowed values: vts,mcs. |
| Mandatory | `panReference` | String | Reference to the original PAN (card number). |
| Mandatory | `dpan` | String | Digital PAN. |
| Mandatory | `tokenExpiry` | Integer | Token expiration date in YYYYMM format. |

Example of `tokenizationInfo` block:

```tab-
"tokenizationInfo": {
      "tokenId": "8d577a01-55f7-45d4-add5-bfe0e8e6c571",
      "tokenizationProvider": "vts",
      "panReference": "f441492a-5b0a-453d-af97-e897e1148dc5",
      "dpan": "5435982500356335",
      "tokenExpiry": "202912"
    }
```

Description of parameters in the `paymentFacilitator` object:

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `pfId` | String [1..11] | Payment facilitator identifier. |
|  |  |  |  |
| Mandatory | `name` | String [1..40] | Payment facilitator name. |
| Optional | `isoId` | String [1..11] | ISO identifier. |
| Mandatory | `subMerchants` | Array of objects | The array of objects with the additional information about submerchants. See nested parameters below. |

Parameters of an object in `subMerchants` array:

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `subMerchantId` | String [1..20] | Submerchant identifier. |
| Mandatory | `name` | String [1..40] | Submerchant name. |
| Mandatory | `address` | Object | A block with information about submerchant address. See nested parameters below. |

Parameters of the `address` object:

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `city` | String [1..50] | Submerchant city. |
| Mandatory | `postalCode` | String [1..16] | Submerchant postal code. |
| Mandatory | `country` | Integer [2] | Submerchant country code in ISO 3166-1 format. |
| Optional | `street` | String [1..40] | Submerchant street. |

Example of `paymentFacilitator` object:

```tab-
"paymentFacilitator" :{
  "pfId": "PF123456",
  "name": "Payment Facilitator Name",
  "isoId": "ISO789",
  "subMerchants": [
    {
      "subMerchantId": "SM001",
      "name": "Sub Merchant 1",
      "address": {
        "city": "City 1",
        "postalCode": "101000",
        "country": "US",
        "street": "Street 1"
      }
    },
    {
      "subMerchantId": "SM002",
      "name": "Sub Merchant 2",
      "address": {
        "city": "City 2",
        "postalCode": "190000",
        "country": "US",
        "street": "Street 2"
      }
    }
  ]
}
```

Values of `paymentWay`:

- CARD - Payment with entering card data
- CARD_BINDING - Stored-credential payment
- CARD_MOTO - Payment via a call center
- FILE_BINDING - Payment via file
- P2P - Card-to-card transfer
- P2P_BINDING - Card-to-card transfer via stored credential
- APPLE_PAY - Apple Pay payment
- APPLE_PAY_BINDING - Payment via Apple pay stored credential
- GOOGLE_PAY_CARD - Payment by a non-tokenized Google Pay card
- GOOGLE_PAY_CARD_BINDING - Stored-credential payment with a non-tokenized Google Pay card
- GOOGLE_PAY_TOKENIZED - Payment by a tokenized Google Pay card
- GOOGLE_PAY_TOKENIZED_BINDING - Stored-credential payment with a tokenized Google Pay card
- SAMSUNG_PAY - Samsung Pay payment
- SAMSUNG_PAY_BINDING - Payment via Samsung Pay stored credential
- TOKEN_PAY - Payment by token directly
- TOKEN_PAY_BINDING - Payment via tokenized stored credential

Possible values of `tii` (read about the stored credential types supported by the Payment Gateway here):

| tii value | Description | Transaction type | Transaction initiator | Card data for transaction | Card data saved after transaction | Note |
| --- | --- | --- | --- | --- | --- | --- |
| Empty |  | Regular | Customer | Entered by Customer | No | An e-commerce transaction, credential is not stored. |
| CI | Initial Common CIT | Initiating | Customer | Entered by Customer | Yes | An e-commerce transaction, credential is stored. |
| F | Unscheduled CIT | Subsequent | Customer | Customer selects card instead of manual entry | No | An e-commerce transaction that uses a stored credential. |
| U | Unscheduled MIT | Subsequent | Merchant | No manual entry, Merchant passes the data | No | An e-commerce transaction that uses a stored credential. Used for one-phase payments only. |
| RI | Initial Recurrent CIT | Initiating | Customer | Entered by Customer | Yes | An e-commerce transaction, credential is stored. |
| R | Recurrent MIT | Subsequent | Merchant | No manual entry, Merchant passes the data | No | A recurrent transaction that uses a stored credential. Used for one-phase payments only. |
| II | Initial Installment CIT | Initiating | Customer | Entered by Customer | Yes | An e-commerce transaction, credential is stored. |
| I | Installment MIT | Subsequent | Merchant | No manual entry, Merchant passes the data | No | An installment transaction that uses a stored credential. Used for one-phase payments only. |

The `refunds` block contains the following parameters:

| Version | Required | Name | Type | Description |
| --- | --- | --- | --- | --- |
| 05+ | Optional | `date` | String | Order refund date |
|  |  |  |  |  |
| 21+ | Optional | `externalRefundId` | String [1..32] | The identifier of the refund. When attempting a refund, externalRefundId is checked: if it exists, a successful response with refund data is returned, if not, a refund is held. |
|  |  |  |  |  |
| 26+ | Optional | `approvalCode` | String [6] | IPS authorization code. This field has a fixed length (six symbols) and can contain digits and Latin letters. |
|  |  |  |  |  |
| 05+ | Optional | `actionCode` | String | Response code from the processing bank. Contains a numeric value. See the list of action codes here. |
|  |  |  |  |  |
| 05+ | Optional | `referenceNumber` | String [12] | Unique identification number that is assigned to the operation on its completion. |
|  |  |  |  |  |
| 05+ | Optional | `amount` | Integer [0..12] | Payment amount in minor currency units (e.g. in cents). |
|  |  |  |  |  |

`attributes` block contains information on the order number in the payment gateway. `name` parameter contains the word `mdOrder`, and `value` parameter contains the actual order number in the payment gateway.

| Version | Required | Name | Type | Description |
| --- | --- | --- | --- | --- |
| All | Optional | `name` | String [1..255] | Name of an additional parameter. |
|  |  |  |  |  |
| All | Optional | `value` | String [1..1024] | Value of an additional parameter - up to 1024 characters. |

`transactionAttributes` block contains the set of additional attributes of the transaction. Used for version 14 and later. Below is the list of the included parameters.

| Version | Required | Name | Type | Description |
| --- | --- | --- | --- | --- |
| 14+ | Optional | `name` | String [1..255] | Name of an additional parameter. |
|  |  |  |  |  |
| 14+ | Optional | `value` | String [1..1024] | Value of an additional parameter - up to 1024 characters. |
| merchantOrderParams block is passed in the response, if the order contains merchant additional parameters. Each additional parameter is passed in a separate merchantOrderParams element. |  |  |  |  |

| Version | Required | Name | Type | Description |
| --- | --- | --- | --- | --- |
| All | Optional | `name` | String [1..255] | Name of an additional parameter. |
|  |  |  |  |  |
| All | Optional | `value` | String [1..1024] | Value of an additional parameter - up to 1024 characters. |

`cardAuthInfo` element contains a structure consisting of `secureAuthInfo` element list and the following parameters.

| Version | Required | Name | Type | Description |
| --- | --- | --- | --- | --- |
| 01+ | Optional | `maskedPan` | String [1..19] | Masked number of the card used for the payment. It contains real first 6 and last 4 digits of the card number in the format XXXXXX**XXXX. |
|  |  |  |  |  |
| 01+ | Optional | `expiration` | Integer [6] | Card expiration date in the following format: YYYYMM. |
|  |  |  |  |  |
| 01+ | Optional | `cardholderName` | String [1..26] | Cardholder's name in Latin characters. Allowed symbols: Latin characters, period, space. |
|  |  |  |  |  |
| 01+ | Optional | `approvalCode` | String [6] | IPS authorization code. This field has a fixed length (six symbols) and can contain digits and Latin letters. |
|  |  |  |  |  |
| 08+ | Mandatory | `paymentSystem` | String | Payment system name. The following variants are possible:  VISA MASTERCARD AMEX JCB CUP |
|  |  |  |  |  |
| 08+ | Mandatory | `product` | String [1..255] | Additional details on corporate cards. These details are filled in by the technical support service. If such details are missing, an empty value is returned. |
|  |  |  |  |  |
| 17+ | Mandatory | `productCategory` | String | Additional details on category of corporate cards. These details are filled in by the technical support service. If such details are missing, an empty value is returned. Possible values: DEBIT, CREDIT, PREPAID, NON_MASTERCARD, CHARGE, DIFFERED_DEBIT. |
|  |  |  |  |  |
| 35+ | Optional | `corporateCard` | String [1..5] | Indication of whether the card is a corporate card. Possible values: false - is not a corporate card, true - is a corporate card. May return an empty value, which means that the value was not found. |
|  |  |  |  |  |
| 39+ | Optional | `detokenizedPanRepresentation` | String [1..19] | The detokenized card number (the last 4 digits or in a masked form). |
|  |  |  |  |  |
| 39+ | Optional | `detokenizedPanExpiryDate` | String | The card's detokenized expiration date in the following format: "YYYYMM". |
|  |  |  |  |  |

`secureAuthInfo` element consists of the following elements (`cavv` and `xid` parameters are included into the `threeDSInfo` element).

| Version | Required | Name | Type | Description |
| --- | --- | --- | --- | --- |
| 01+ | Optional | `eci` | Integer [1..4] | Electronic commerce indicator. The indicator is specified only after an order has been paid and in case the corresponding permission is present. Below is the explanation of ECI codes.  ECI=01 or ECI=06 - merchant supports 3-D Secure, payment card does not support 3-D Secure, payment is processed based on CVV2/CVC code. ECI=02 or ECI=05 - both merchant and payment card support 3-D Secure; ECI=07 - merchant does not support 3-D Secure, payment is processed based on CVV2/CVC code. |
|  |  |  |  |  |
| 01 - 42 | Optional | `authTypeIndicator` | String | 3DS authentication type (available up to version 42). This parameter is required for payment via your own 3DS Server with 3DS 2. For payments with SSL, this parameter is optional and is defined automatically depending on ECI value. Allowed values:  0 - SSL authentication 1 - 3DS 1 authentication 2 - 3DS 1 authentication attempt 3 - SCA Cardholder authentication with 3DS 2 4 - RBA Cardholder authentication with 3DS 2 5 - Cardholder authentication attempt with 3DS 2 |
|  |  |  |  |  |
| 42+ | Optional | `threeDsType` | String | 3DS authentication type. This parameter is required for payment via your own 3DS Server with 3DS 2. For payments with SSL, this parameter is optional and is defined automatically depending on ECI value. Allowed values:  0 - SSL authentication 3 - SCA Cardholder authentication with 3DS 2 4 - RBA Cardholder authentication with 3DS 2 5 - Cardholder authentication attempt with 3DS 2 6 - 3DS 2 exemption granted 7 - 3RI authentication with 3DS 2 8 - 3RI authentication attempt with 3DS 2 |
|  |  |  |  |  |
| 01+ | Optional | `cavv` | String [0..200] | Cardholder authentication value. The indicator is specified only after an order is paid and if the corresponding permission is enabled. |
|  |  |  |  |  |
| 01+ | Optional | `xid` | String [1..80] | Electronic commerce indicator of the transaction. The indicator is specified only after an order has been paid and in case the corresponding permission is present. |
|  |  |  |  |  |
| 30+ | Optional | `threeDSProtocolVersion` | String | 3DS protocol version. Possible values are "2.1.0", "2.2.0" for 3DS2. If threeDSProtocolVersion is not passed in the request, then the default value will be used for 3D Secure authorization (2.1.0 - for 3DS 2). |
|  |  |  |  |  |
| 30+ | Optional | `rreqTransStatus` | String [1] | Transaction status from the request for passing user authentication results from ACS (RReq). Passed when 3DS2 is used. |
|  |  |  |  |  |
| 30+ | Optional | `aresTransStatus` | String | Transaction status from the ACS response to the authentication request (ARes). Passed when 3DS2 is used. |
|  |  |  |  |  |

`bindingInfo` element contains the following parameters.

| Version | Mandatory | Name | Type | Description |
| --- | --- | --- | --- | --- |
| All | Optional | `clientId` | String [0..255] | Customer number (ID) in the merchant's system — up to 255 characters. Used to implement the functionality of stored-credential transactions. Can be returned in the response if the merchant is allowed to store credentials. Specifying this parameter in stored-credential transactions is mandatory. Otherwise, a payment will be unsuccessful. |
|  |  |  |  |  |
| All | Optional | `bindingId` | String [1..255] | Identifier of an already existing stored credential. This is the card ID tokenized by the Gateway. Can be used only if the merchant has the permission to work with stored credentials. If this parameter is passed in this request, it means that:  This order can only be paid with a stored credential; The payer will be redirected to a payment page where only CVC entry is required. |
|  |  |  |  |  |
| 02+ | Optional | `authDateTime` | Integer | Authorization date and time, shown as the amount of milliseconds since 00:00 January 1, 1970 GMT (UNIX time). Example: 1740392720718 (Corresponds to February 24, 2025, 10:25:20 (UTC)). |
|  |  |  |  |  |
| 02+ | Optional | `authRefNum` | String [1..24] | Reference number of the payment authorization that has been assigned to it upon its registration. |
|  |  |  |  |  |
| 02+ | Optional | `terminalId` | String [1..10] | Terminal identifier in the system that processes the payment. |

`paymentAmountInfo` element contains the following parameters.

| Version | Mandatory | Name | Type | Description |
| --- | --- | --- | --- | --- |
| 03+ | Optional | `approvedAmount` | Integer [0..12] | Amount in minimum currency units (e.g. cents) that was put on hold on buyer's account. Used in two-phase payments only. In case of partial authorization, this amount may be less than the order registration amount. |
|  |  |  |  |  |
| 03+ | Optional | `depositedAmount` | Integer [1..12] | Charged amount in minimum currency units (e.g., in cents). In case of partial authorization, this amount may be less than the order registration amount. |
|  |  |  |  |  |
| 03+ | Optional | `refundedAmount` | Integer [1..12] | Refunded amount in minimum currency units. |
|  |  |  |  |  |
| 03+ | Optional | `paymentState` | String | Order status, this parameter can have the following values:  CREATED - order created (but not paid); APPROVED - order approved (funds are on hold on buyer's account); DEPOSITED - order deposited (buyer is charged); DECLINED - order declined; REVERSED - order canceled; REFUNDED - refund. |
|  |  |  |  |  |
| 18+ | Optional | `totalAmount` | Integer [1..20] | Order amount plus fee, if any. |
|  |  |  |  |  |

`bankInfo` element contains the following parameters.

| Version | Required | Name | Type | Description |
| --- | --- | --- | --- | --- |
| 03+ | Optional | `bankName` | String [1..50] | Issuing bank name. |
|  |  |  |  |  |
| 03+ | Optional | `bankCountryCode` | String [1..4] | Country code of the issuing bank. |
|  |  |  |  |  |
| 03+ | Optional | `bankCountryName` | String [1..160] | Country of the issuing bank. |

`payerData` element contains the following parameters.

| Version | Required | Name | Type | Description |
| --- | --- | --- | --- | --- |
| 13+ | Optional | `email` | String [1..40] | The payer's email address. |
|  |  |  |  |  |
| 13+ | Optional | `phone` | String [7..15] | Customer's phone number. It is always necessary to specify the country code, but you can specify or omit the + sign or 00 at the beginning. The number must be 7 to 15 digits long. Thus, the following options are valid:  +35799988877; 0035799988877; 35799988877.  For payment by VISA with 3DS authorization, it is necessary to specify either phone or email of the cardholder. If you have a setting to display phone number on the payment page and have specified an invalid number, the customer will have a possibility to correct it on the payment page. |
|  |  |  |  |  |
| 13+ | Optional | `postAddress` | String [1..255] | Delivery address. |
|  |  |  |  |  |
| 38+ | Optional | `paymentAccountReference` | String [1..29] | The unique account number of the client, which links all their payment means within the IPS (cards and tokens). |

The `efectyOrderInfo` block contains the following parameters.

| Version | Required | Name | Type | Description |
| --- | --- | --- | --- | --- |
| 23+ | Optional | `referenceNumber` | Integer | Number of Efecty order reference generated by Efecty |
| 23+ | Optional | `referenceDate` | Integer | Date/time of the reference creation |
| 23+ | Optional | `referenceStatus` | String | Status of Efecty order |
| 23+ | Optional | `referenceTerm` | Integer | Lifetime of Efecty order (in hours) |
| 23+ | Optional | `networkID` | Integer | ID of the cash payment acceptance network (for Efecty a constant value is 1) |
| 23+ | Optional | `networkName` | String | Name of the cash payment acceptance network (for Efecty a constant value is efecty) |

`pluginInfo` element (which is JSON object) is present in response if payment was made through payment plugin. Contains the following parameters.

| Version | Required | Name | Type | Description |
| --- | --- | --- | --- | --- |
| 28+ | Optional | `name` | String [1..32] | Unique name of the payment plugin. |
|  |  |  |  |  |
| 28+ | Optional | `params` | Object | Parameters for a specific payment method, must be passed as follows {"param":"value","param2":"value2"}. |

Description of parameters in `orderBundle` object:

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Optional | `orderCreationDate` | String [19] | Order creation date in the following format: YYYY-MM-DDTHH:MM:SS. |
|  |  |  |  |
| Optional | `customerDetails` | Object | Block containing customer attributes. The description of the tag attributes is given below. |
| Mandatory | `cartItems` | Object | Object containing cart items attributes. The description of nested elements is given below. |
|  |  |  |  |

Description of parameters in the `loyalties` object:

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Optional | `bonusAmountForCredit` | String [0..18] | Total amount of bonuses for all products for this positionId to be added to the customer's bonus account, in minimum currency units. |
|  |  |  |  |
| Optional | `bonusAmountForDebit` | String [0..18] | Total amount of bonuses for all products for this positionId to be taken from the customer's bonus account, in minimum currency units. |
| Mandatory | `bonusAmountRefunded` | String [0..18] | Total amount of returned bonuses for the positionId in minor currency units. |
|  |  |  |  |

Description of parameters in `customerDetails` object:

| Required | Name | Type | Description |
| --- | --- | --- | --- |
|  |  |  |  |
| Optional | `contact` | String [0..40] | Customer's preferred way of communication. |
|  |  |  |  |
| Optional | `fullName` | String [1..100] | Payer's full name. |
| Optional | `passport` | String [1..100] | Customer's passport serial number in the following format: 2222888888. |
|  |  |  |  |
| Optional | `deliveryInfo` | Object | Object containing delivery address attributes. The description of the nested elements is given below. |
|  |  |  |  |

Description of parameters in `deliveryInfo` object.

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Optional | `deliveryType` | String [1..20] | Delivery method. |
|  |  |  |  |
| Mandatory | `country` | String [2] | Two letter code of the country of delivery. |
|  |  |  |  |
| Mandatory | `city` | String [0..40] | City of destination. |
|  |  |  |  |
| Mandatory | `postAddress` | String [1..255] | Delivery address. |

Description of parameters in `cartItems` object.

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `items` | Object | An element of the array containing cart item attributes. The description of the nested elements is given below. |

Description of parameters in `items` object.

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `positionId` | Integer [1..12] | Unique product identifier in the cart. |
|  |  |  |  |
| Mandatory | `name` | String [1..255] | Name or the description of an item in any format. |
|  |  |  |  |
| Optional | `itemDetails` | Object | Object containing the parameters describing an item. The description of the nested elements is given below. |
|  |  |  |  |
| Mandatory | `quantity` | Object | Element describing the total of items of one positionId and its unit of measurement. The description of the nested elements is given below. |
|  |  |  |  |
| Optional | `itemAmount` | Integer [1..12] | The total cost of all instances of one positionId specified in minor denomination of the currency. itemAmount must be passed only if the itemPrice parameter has not been passed. Otherwise passing of itemAmount is not required. If both parameters itemPrice and itemAmount are passed in the request, then itemAmount shall be equal itemPrice * quantity, otherwise the request will return an error. |
|  |  |  |  |
| Optional | `itemPrice` | Integer [1..18] | Total cost of instance of one positionId specified in minor currency units. |
|  |  |  |  |
| Optional | `itemCurrency` | Integer [3] | ISO 4217 currency code. If the parameter is not specified, it is considered to be equal to the Order currency. |
|  |  |  |  |
| Optional | `itemCode` | String [1..100] | Number (identifier) of an item in the store system. |
|  |  |  |  |

Description of parameters in `quantity` object.

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `value` | Number [1..18] | Number of items in one positionId. Use a decimal point as a separator in fractions. Maximal number of decimal places is 3. |
|  |  |  |  |
| Mandatory | `measure` | String [1..20] | The unit of measurement for the quantity of item instances. |

Description of parameters in `itemDetails` object.

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Optional | `itemDetailsParams` | Object | Parameter describing additional information regarding a line item. The description of the nested elements is given below. |

### Examples

#### Request example

```bash
curl --request POST \
  --url https://dev.bpcbt.com/payment/rest/getOrderStatusExtended.do \
  --header 'content-type: application/x-www-form-urlencoded' \
  --data userName=test_user \
  --data password=test_user_password \
  --data orderId=01491d0b-c848-7dd6-a20d-e96900a7d8c0 \
  --data language=en
```

#### Response example

```json
{
  "errorCode": "0",
  "errorMessage": "Success",
  "orderNumber": "7005",
  "orderStatus": 2,
  "actionCode": 0,
  "actionCodeDescription": "",
  "amount": 2000,
  "currency": "978",
  "date": 1617972915659,
  "orderDescription": "",
  "merchantOrderParams": [],
  "transactionAttributes": [],
  "attributes": [
    {
      "name": "mdOrder",
      "value": "01491d0b-c848-7dd6-a20d-e96900a7d8c0"
    }
  ],
  "cardAuthInfo": {
    "maskedPan": "411111**1111",
    "expiration": "203412",
    "cardholderName": "TEST CARDHOLDER",
    "approvalCode": "12345678", 
    "pan": "411111**1111"
  },
  "bindingInfo": {
    "clientId": "259753456",
    "bindingId": "01491394-63a6-7d45-a88f-7bce00a7d8c0"
  },
  "authDateTime": 1617973059029,
  "terminalId": "123456",
  "authRefNum": "714105591198",
  "paymentAmountInfo": {
    "paymentState": "DEPOSITED",
    "approvedAmount": 2000,
    "depositedAmount": 2000,
    "refundedAmount": 0
  },
  "bankInfo": {
    "bankCountryCode": "UNKNOWN",
    "bankCountryName": "Unknown"
  }
}
```

