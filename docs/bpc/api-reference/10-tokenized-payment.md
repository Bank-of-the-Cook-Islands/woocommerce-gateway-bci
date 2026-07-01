> Source: https://dev.bpcbt.com/en/integration/api/rest.html

## Tokenized payment

Universal payment request for tokenized payment methods – `https://dev.bpcbt.com/payment/token/payment.do`.

This request can be used only if you have a special permission in Payment Gateway. Contact technical support for details.

When sending the request, you should use the header: `Content-Type: application/json`

### Request parameters

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `userName` | String [1..100] | Merchant's API account login. |
|  |  |  |  |
| Mandatory | `password` | String [1..30] | Merchant's API account password. |
|  |  |  |  |
| Optional | `merchant` | String [1..255] | To register an order and carry out payment on behalf of another merchant, specify the merchant's API account login in this parameter. Can be used only if you have the permission to see the transactions of other merchants or if the specified merchant is your child merchant. |
|  |  |  |  |
| Mandatory | `amount` | Integer [0..12] | Payment amount in minor currency units (e.g. in cents). |
|  |  |  |  |
| Optional | `currency` | String [3] | ISO 4217 encoded currency key. If not specified, the default value is used. Only digits are allowed. |
|  |  |  |  |
| Optional | `email` | String [1..40] | The payer's email address. |
|  |  |  |  |
| Optional | `clientId` | String [0..255] | Customer number (ID) in the merchant's system — up to 255 characters. Used to implement the functionality of stored-credential transactions. Can be returned in the response if the merchant is allowed to store credentials. Specifying this parameter in stored-credential transactions is mandatory. Otherwise, a payment will be unsuccessful. |
|  |  |  |  |
| Mandatory | `tokenType` | String | Possible values:  MDES; VTS; APPLE; GOOGLE; SAMSUNG. |
|  |  |  |  |
| Optional | `ip` | String [1..39] | Buyer's IP address. IPv6 is supported in all requests. (up to 39 characters).  If you use 3DS 2 authentication, we recommend passing a real IP address of the customer in this parameter. This increases conversion rates on the ACS side. |
|  |  |  |  |
| Optional | `orderNumber` | String [1..36] | Order number (ID) in the merchant's system, must be unique for each order. |
|  |  |  |  |
| Conditional | `orderId` | String [1..36] | Order number in the payment gateway. Unique within the payment gateway. To be passed in the repeated payment request (after completing the 3DS-method). |
|  |  |  |  |
| Optional | `description` | String [1..598] | Order description in any format. To enable sending this field to the processing system, contact the technical support service. It is not allowed to fill this parameter with personal data or payment data (card numbers, etc.). This requirement is due to the fact that the order description is not masked in Merchant Portal and log files. |
|  |  |  |  |
| Optional | `language` | String [2] | ISO 639-1 encoded language key. If the language is not specified, the default language specified in the store settings is used. Supported languages: en,el,ro,bg,pt,sw,hu,it,pl,de,fr,kh,cn,es,ka,da,et,fi,lt,lv,nl,sv. |
|  |  |  |  |
| Optional | `mcc` | Integer [4] | Merchant Category Code. Using this parameter requires a special permission. You can use only the values from the predefined list of allowed MCC values. Contact the support team for details. |
|  |  |  |  |
| Optional | `mvv` | String [1..10] | Merchant verification value from Mastercard for tokenized transactions. To pass this parameter, a special setting must be enabled (contact technical support). |
|  |  |  |  |
| Optional | `paymentFacilitator` | Object | A block with the parameters of a payment facilitator, i.e. a merchant who allows several submerchants to accept payments under its account. This parameter is used if a special setting is enabled (contact the support team). See nested parameters. |
| Optional | `preAuth` | Boolean | Parameter that defines the necessity of a pre-authorization (putting the amount on hold on the customer's account until its debiting). The following values are available:  true - two-phase payments enabled; false - one-phase payments enabled (money are charged right away). If the parameter is missing, one-phase payment is made. |
|  |  |  |  |
| Conditional | `cvc` | String [3] | The presence of this parameter is determined by payment type:  cvc is provided not for all tokenized payments; cvc is not provided for MIT payments; cvc is mandatory by default for all other payment types; but if permission Can process payments without confirmation of CVC is enabled, cvc becomes optional in that case.  Only digits are allowed. |
|  |  |  |  |
| Optional | `cardholderName` | String [2..45] | Cardholder's name in Latin characters. This parameter is passed only after an order is paid. Such special characters as space, full stop, hyphen, apostrophe ( . - ') can be used. The use of other characters is prohibited. |
|  |  |  |  |
| Optional | `autocompletionDate` | String [19] | The date and time when the two-phase payment must be completed automatically in the following format: 2025-12-29T13:02:51. The used timezone is UTC+0. To enable sending this field to the processing system, contact your technical support service. |
|  |  |  |  |
| Optional | `autoReverseDate` | String [19] | The date and time when the two-phase payment must be reversed automatically in the following format: 2025-06-23T13:02:51. The used timezone is UTC+0. To enable sending this field to the processing system, contact your technical support service. |
|  |  |  |  |
| Mandatory | `returnUrl` | String [1..512] | The address to which the user will be redirected if the payment is successful. The address must be specified in full including the protocol used (for example, https://mybestmerchantreturnurl.com instead of mybestmerchantreturnurl.com). Otherwise, the user will be redirected to the address of the following type https://dev.bpcbt.com/payment/<merchant_address>.  The address must not be a relative path, i.e. it must not start with "." and "/". Otherwise, error 4 will be returned: "The return URL is invalid". For example: ../test.html, /test.html- invalid URLs. |
|  |  |  |  |
| Optional | `failUrl` | String [1..512] | The address to which the user is to be redirected in case of a failed payment. The address must be specified in full including the protocol used (for example, https://mybestmerchantreturnurl.com instead of mybestmerchantreturnurl.com). Otherwise, the user will be redirected to the address of the following type https://dev.bpcbt.com/payment/<merchant_address>.  The address must not be a relative path, i.e. it must not start with "." and "/". Otherwise, error 4 will be returned: "The return URL is invalid". For example: ../test.html, /test.html- invalid URLs. |
|  |  |  |  |
| Optional | `jsonParams` | Object | A number of additional free-form attributes. More details. |
| Optional | `features` | String | Features of the order. As an example, below are the possible values.  VERIFY - If you specify this value in the order registration request, cardholder will be verified however they will not be charged any amount, so in this case amount parameter can be 0. Verification allows to make sure that a payment card is used by its legitimate owner, and further you can charge them without authentication (CVC, 3D-Secure). Even if some amount is passed in the request, the customer will not be charged if VERIFY feature is used. After a successful registration order status is changed to REVERSED. This value can be also used for storing the credential – in this case, the clientId parameter must be passed as well. Read more here. FORCE_TDS - Force 3-D Secure payment. If a payment card does not support 3-D Secure, the transaction will fail. FORCE_SSL - Force SSL payment (without 3-D Secure). FORCE_FULL_TDS - After 3-D Secure authentication, PaRes status must be Y, which guarantees successful user authentication. Otherwise, the transaction will fail. |
|  |  |  |  |
| Optional | `dynamicCallbackUrl` | String [1..512] | This parameter allows you to use the functionality of sending callback notifications dynamically. Here you can pass the address to which all "payment" callback notifications activated for the merchant will be sent. "Payment" notifications are callback notifications related to the following events: successful hold, payment declined by timeout, cardpresent payment is declined, successful debit, refund, cancellation. At the same time, callback notifications activated for the merchant that are not related to payments (enabling/disabling a stored credential, storing a credential) will be sent to a static address for callbacks. Whether the parameter is mandatory or not depends on the merchant configuration on Payment Gateway side. |
|  |  |  |  |
| Optional | `tii` | String | Transaction initiator indicator. A parameter indicating what type of operation will be carried out by the initiator (Customer or Merchant). Possible values |
|  |  |  |  |
| Optional | `externalScaExemptionIndicator` | String | The type of SCA (Strong Customer Authentication) excemption. If this parameter is specified, the transaction will be processed depending on your settings in the payment gateway: either forced SSL operation will be done, or the issuer bank will get the information about SCA excemption and decide to perform operation with or without 3DS authentication (for details, contact our support team). Allowed values:  LVP – Low Value Payments transaction. You can consider a transaction as low risk based on the transaction amount, the client's transactions per day or the client's total daily amount. TRA – Transaction Risk Analysis transaction, i.e., the transaction that has passed successful anti-fraud check.  To pass this parameter, you must have sufficient permissions in the payment gateway. |
|  |  |  |  |
| Optional | `originalPaymentNetRefNum` | String [1..36] | The identifier of the original or previous successful transaction in the payment system in relation to the performed stored-credential transaction - TRN ID. Is passed when tii = R,U, or F. Is mandatory when using merchant's stored credentials in stored credential transfers. |
|  |  |  |  |
| Optional | `threeDSServerTransId` | String [1..36] | Transaction identifier created on 3DS Server. Mandatory for 3DS authentication. |
|  |  |  |  |
| Optional | `threeDSVer2FinishUrl` | String [1..512] | URL where Customer should be redirected after authentication on ACS Server. |
|  |  |  |  |
| Optional | `threeDSMethodNotificationUrl` | String [1..512] | URL where notification about performed 3DS-method should be sent to. |
|  |  |  |  |
| Mandatory | `paymentData` | object | Includes the payment information received from the tokenization service. See nested parameters |
|  |  |  |  |
| Optional | `billingPayerData` | Object | A block with the client's registration data (address, postal code) necessary for passing the address verification within the AVS/AVV services. Mandatory if the feature is enabled for the merchant on Payment Gateway side. See nested parameters. |
| Optional | `shippingPayerData` | Object | Object containing customer delivery data. It is used for further 3DS authentication of the client. See nested parameters. |
| Optional | `preOrderPayerData` | Object | Object containing pre-order data. It is used for further 3DS authentication of the client. See nested parameters. |
| Optional | `orderPayerData` | Object | Object containing data about the order payer. It is used for further 3DS authentication of the client. See nested parameters. |
| Optional | `billingAndShippingAddressMatchIndicator` | String [1] | Indicator for matching the cardholder's billing address and shipping address. This parameter is used for further 3DS authentication of the customer. Possible values:  Y - the cardholder's billing address and shipping address match; N - cardholder billing address and shipping address do not match. |
| Optional | `clientBrowserInfo` | Object | A block with the data about the client's browser that is sent to ACS during the 3DS authentication. To pass this block, you should have a special setting (contact the support team). See nested parameters. |
|  |  |  |  |

The ``jsonParams`` block contains additional information fields for later storage. To pass N parameters, a request must contain N jsonParams tags, where the `name` attribute contains the parameter name and `value` attribute contains its value:

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `name` | String [1..255] | Name of an additional parameter. |
|  |  |  |  |
| Mandatory | `value` | String [1..1024] | Value of an additional parameter - up to 1024 characters. |

Below are the parameters of the `paymentData` block.

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `dpan` | String [1..19] | Token number. |
| Conditional | `tokenCryptogram` | String [1..1024] | Cryptogram received from the tokenization service. In some cases it can be missing, e.g. for MIT VTS. |
| Mandatory | `MM` | String [2] | The expiry month of the token. |
| Mandatory | `YYYY` | String [4] | The expiry year of the token. |
| Optional | `eci` | Integer [1..4] | Electronic commerce indicator. The indicator is specified only after an order has been paid and in case the corresponding permission is present. Below is the explanation of ECI codes.  ECI=01 or ECI=06 - merchant supports 3-D Secure, payment card does not support 3-D Secure, payment is processed based on CVV2/CVC code. ECI=02 or ECI=05 - both merchant and payment card support 3-D Secure; ECI=07 - merchant does not support 3-D Secure, payment is processed based on CVV2/CVC code. |
|  |  |  |  |

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

Below are the parameters of the `billingPayerData` block (data about the client registration address).

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Optional | `billingCity` | String [0..50] | The city registered on a specific card of the Issuing Bank. |
|  |  |  |  |
| Optional | `billingCountry` | String [0..50] | The country registered on a specific card of the Issuing Bank. Format: ISO 3166-1 (Alpha 2 / Alpha 3 / Number-3) or the country name. We recommend to pass a two/three-letter ISO country code. |
|  |  |  |  |
| Optional | `billingAddressLine1` | String [0..50] | The address registered on a specific card of the Issuing Bank (A payer’s address). Line 1. Mandatory to be passed in order AVS verification works. |
|  |  |  |  |
| Optional | `billingAddressLine2` | String [0..50] | The address registered on a specific card of the Issuing Bank. Line 2. |
|  |  |  |  |
| Optional | `billingAddressLine3` | String [0..50] | The address registered on a specific card of the Issuing Bank. Line 3. |
|  |  |  |  |
| Optional | `billingPostalCode` | String [0..9] | Postal code registered on a specific card of the Issuing Bank. Mandatory to be passed in order AVS verification works. |
|  |  |  |  |
| Optional | `billingState` | String [0..50] | The state registered on a specific card of the Issuing Bank. Format: full ISO 3166-2 code, its part, or the state/region name. Can contain Latin characters only. We recommend to pass a two-letter ISO state code. |
| Mandatory | `payerAccount` | String [1..32] | Payer's account number. |
|  |  |  |  |
| Optional | `payerLastName` | String [1..64] | Payer's last name. |
|  |  |  |  |
| Optional | `payerFirstName` | String [1..35] | Payer's first name. |
|  |  |  |  |
| Optional | `payerMiddleName` | String [1..35] | Payer's middle name. |
|  |  |  |  |
| Optional | `payerCombinedName` | String [1..99] | Payer's full name. |
|  |  |  |  |
| Optional | `payerIdType` | String [1..8] | Type of the payer's identifying document provided. Allowed values:  IDTP1 - Passport IDTP2 - Driving license IDTP3 - Social card IDTP4 - Citizen ID card IDTP5 - Certificate of Business IDTP6 - Refugee certificate IDTP7 - Residence permit IDTP8 - Foreign passport IDTP9 - Official passport IDTP10 - Temporary passport IDTP11 - Sailor's passport |
|  |  |  |  |
| Optional | `payerIdNumber` | String [1..99] | Number of the payer's identifying document provided. |
|  |  |  |  |
| Optional | `payerBirthday` | String [1..20] | Payer's birth date in the YYYYMMDD format. |
|  |  |  |  |

Description of parameters in `shippingPayerData` object:

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Optional | `shippingCity` | String [1..50] | The customer's city (from the delivery address) |
| Optional | `shippingCountry` | String [1..50] | The customer's country |
| Optional | `shippingAddressLine1` | String [1..50] | The customer's primary address (from the shipping address) |
| Optional | `shippingAddressLine2` | String [1..50] | The customer's primary address (from the shipping address) |
| Optional | `shippingAddressLine3` | String [1..50] | The customer's primary address (from the shipping address) |
| Optional | `shippingPostalCode` | String [1..16] | The customer's zip code for delivery |
| Optional | `shippingState` | String [1..50] | Customer's state/region (from delivery address) |
| Optional | `shippingMethodIndicator` | Integer [2] | Shipping Method Indicator. Possible values:  01 - delivery to the cardholder's billing address 02 - delivery to another address verified by Merchant 03 - delivery to an address other than the cardholder's primary (settlement) address 04 - shipment to the store/self-collection (the store address should be specified in the relevant delivery parameters) 05 - Digital distribution (includes online services and e-gift cards) 06 - travel and event tickets that are not deliverable 07 - Other (e.g. games, non-deliverable digital goods, digital subscriptions, etc.) |
| Optional | `deliveryTimeframe` | Integer [2] | Product delivery timeframe. Possible values:  01 - digital distribution 02 - same-day delivery 03 - overnight delivery 04 - delivery within 2 days after payment and later |
| Optional | `deliveryEmail` | String [1..254] | Target email address for delivery of digital distribution. Note that it is preferrable to pass the email in a separate email parameter of the request. The deliveryEmail parameter specified in this block is only used to fill MerchantRiskIndicator during 3DS authorization. |

Description of parameters in `preOrderPayerData` object:

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Optional | `preOrderDate` | String [10] | Expected date when delivery will be available (for pre-ordered purchases), in the format YYYYYYMMDD. |
| Optional | `preOrderPurchaseInd` | Integer [2] | Indicator of a customer placing an order for available or future delivery. Possible values:  01 - delivery available; 02 - future delivery |
| Optional | `reorderItemsInd` | Integer [2] | An indicator that the customer is rebooking a previously paid delivery as part of a new order. Possible values:  01 - order placed for the first time; 02 - repeated order |

Description of parameters in `orderPayerData` object:

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Optional | `homePhone` | String [7..15] | Customer's phone number. It is always necessary to specify the country code, but you can specify or omit the + sign or 00 at the beginning. The number must be 7 to 15 digits long. Thus, the following options are valid:  +35799988877; 0035799988877; 35799988877. |
| Optional | `workPhone` | String [7..15] | Customer's phone number. It is always necessary to specify the country code, but you can specify or omit the + sign or 00 at the beginning. The number must be 7 to 15 digits long. Thus, the following options are valid:  +35799988877; 0035799988877; 35799988877. |
| Conditional | `mobilePhone` | String [7..15] | Customer's phone number. It is always necessary to specify the country code, but you can specify or omit the + sign or 00 at the beginning. The number must be 7 to 15 digits long. Thus, the following options are valid:  +35799988877; 0035799988877; 35799988877.  For payment by VISA with 3DS authorization, it is necessary to specify either phone or email of the cardholder. If you have a setting to display phone number on the payment page and have specified an invalid number, the customer will have a possibility to correct it on the payment page. |

Below are the parameters of the `clientBrowserInfo` block (data about the client's browser).

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Optional | `userAgent` | String [1..2048] | Browser agent. |
| Optional | `OS` | String | Operation system. |
| Optional | `OSVersion` | String | Operation system version. |
| Optional | `browserAcceptHeader` | String [1..2048] | The Accept header that tells the server what file formats (or MIME-types) the browser accepts. |
| Optional | `browserIpAddress` | String [1..45] | Browser IP address. |
| Optional | `browserLanguage` | String [1..8] | Browser language. |
| Optional | `browserTimeZone` | String | Browser time zone. |
| Optional | `browserTimeZoneOffset` | String [1..5] | The time zone offset in minutes between the user's local time and UTC. |
| Optional | `colorDepth` | String [1..2] | Screen color depth, in bits. |
| Optional | `fingerprint` | String | Browser fingerprint - a unique digital identifier of the browser. |
| Optional | `isMobile` | Boolean | Possible values: true or false. Flag showing that a mobile device is used. |
| Optional | `javaEnabled` | Boolean | Possible values: true or false. Flag showing that java is enabled in the browser. |
| Optional | `javascriptEnabled` | Boolean | Possible values: true or false. Flag showing that javascript is enabled in the browser. |
| Optional | `plugins` | String | Comma-separated list of plugins the browser uses. |
| Optional | `screenHeight` | Integer [1..6] | Screen height, in pixels. |
| Optional | `screenWidth` | Integer [1..6] | Screen width, in pixels. |
| Optional | `screenPrint` | String | Data about current screen print including resolution, color depth, display metrics. |
| Optional | `device` | String | Information about the cardholder's device (model, version, and so on). |
| Optional | `deviceType` | String | Type of device on which the browser is running (mobile phone, desktop, tablet, and so on). |

Example of `clientBrowserInfo` block:

```json
"clientBrowserInfo":
    {
        "userAgent":"Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/111.0.0.0 Safari/537.36 Edg/111.0.1661.41",
        "fingerprint":850891523,
        "OS":"Windows",
        "OSVersion":"10",
        "isMobile":false,
        "screenPrint":"Current Resolution: 1536x864, Available Resolution: 1536x824, Color Depth: 24, Device XDPI: undefined, Device YDPI: undefined",
        "colorDepth":24,
        "screenHeight":"864",
        "screenWidth":"1536",
        "plugins":"PDF Viewer, Chrome PDF Viewer, Chromium PDF Viewer, Microsoft Edge PDF Viewer, WebKit built-in PDF",
        "javaEnabled":false,
        "javascriptEnabled":true,
        "browserLanguage":"it-IT",
        "browserTimeZone":"Europe/Rome",
        "browserTimeZoneOffset":-120,
        "browserAcceptHeader":"gzip",
        "browserIpAddress":"x.x.x.x"
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

### Response parameters

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `success` | Boolean | Main parameter which indicates directly that the request was successful. The following values are available:  true - request processed successfully; false - request failed.  Note that the value true here simply means that the request was proccessed, not that the order was paid. Read here to find out how to get payment status. |
|  |  |  |  |
| Conditional | `data` | Object | This parameter is returned only if the payment is processed successfully. See the description below. |
| Conditional | `error` | Object | This parameter is returned only if the payment failed. See the description below. |
| Conditional | `orderStatus` | Object | Contains order status parameters and is returned only if the payment gateway has recognized all request parameters as correct. See the description below. |

`data` block contains the following elements.

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `orderId` | String [1..36] | Order number in the payment gateway. Unique within the payment gateway. |
|  |  |  |  |
| Optional | `termUrl` | String [1..512] | In a successful response in case of a 3D-Secure payment. The URL address to which ACS redirects the cardholder after authentication. For details see Redirect to ACS. |
|  |  |  |  |
| Optional | `acsUrl` | String [1..512] | The URL address for redirecting to ACS. It is returned in a successful response in case of a 3D-Secure payment, when redirect to the ACS is needed. For details see Redirect to ACS. |
|  |  |  |  |
| Optional | `paReq` | String [1..255] | PAReq (Payment Authentication Request) - a message that should be sent to ACS together with redirect. It is returned in a successful response in case of a 3D-Secure payment, when redirect to the ACS is needed. This message contains the Base64-encoded data necessary for the cardholder authentication. For details see Redirect to ACS. |
|  |  |  |  |

If 3DS2 is used, the `data` block in the response contains the following parameters as well:

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `is3DSVer2` | Boolean | Possible values: true or false. Flag showing that payment uses 3DS2. |
|  |  |  |  |
| Mandatory | `threeDSServerTransId` | String [1..36] | Transaction identifier created on 3DS Server. Mandatory for 3DS authentication. |
|  |  |  |  |
| Optional | `threeDSMethodUrl` | String [1..512] | URL of ACS Server for gathering browser data. |
|  |  |  |  |
| Mandatory | `threeDSMethodUrlServer` | String [1..512] | URL of 3DS Server for gathering browser data to be included in the AReq (Authentication Request) from 3DS Server to ACS Server. |
|  |  |  |  |
| Optional | `threeDSMethodDataPacked` | String [1..1024] | Base-64-encoded data of CReq (Challenge Response) to be sent to ACS Server. |
|  |  |  |  |
| Optional | `threeDSMethodURLServerDirect` | String [1..512] | URL of 3dsmethod.do for executing the 3DS method on 3DS Server via Payment Gateway (subject to respective Merchant-level permission). |
|  |  |  |  |
| Optional | `packedCReq` | String | Packed challenge request data. It is returned in a successful response in case of a 3D-Secure payment, when redirect to the ACS is needed. This value should be used as the ACS link creq parameter (acsUrl) to redirect the client to the ACS. For details see Redirect to ACS. |
|  |  |  |  |
| Optional | `threeDSSDKKey` | String | Device data encryption key.  Parameter is mandatory for SDK flow. |
|  |  |  |  |
|  |  |  |  |
| Optional | `threeDSAcsTransactionId` | String | ID of 3DS transaction on ACS.  Parameter is mandatory for SDK flow. |
|  |  |  |  |
|  |  |  |  |
| Optional | `threeDSAcsRefNumber` | String | Reference number on ACS. |
|  |  |  |  |
| Optional | `threeDSAcsSignedContent` | String | Signed content for SDK, content includes ACS URL.  Parameter is mandatory for SDK flow. |
|  |  |  |  |
|  |  |  |  |
| Optional | `threeDSDsTransID` | String | Unique identifier of transaction witin IPS.  Parameter is mandatory for SDK flow. |
|  |  |  |  |

Below are the parameters of the `error` block.

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `code` | String [1..3] | Code as an information parameter stating an error occurred. |
|  |  |  |  |
| Mandatory | `message` | String [1..512] | Information parameter that is an error description to be displayed to the user. The parameter may vary, so it should not be hardcoded. |
|  |  |  |  |
| Mandatory | `description` | String [1..598] | A detailed technical explanation of the error - the contents of this parameter should not to be displayed to the customer. |
|  |  |  |  |

`orderStatus` block contains the following elements.

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Optional | `errorCode` | String [1..2] | Information parameter in case of an error, which may have different code values:  0 value - indicates success of processing; another number value (1-99) - indicates an error for more details of which ErrorMesage parameter must be inspected. It also can be missing if the result has not caused any error. |
|  |  |  |  |
| Optional | `errorMessage` | String [1..512] | Information parameter that is an error description in a case of error occurance. ErrorMessage value can vary, so it should not be hardcoded. Language of the description is set in language parameter of the request. |
|  |  |  |  |
| Optional | `orderNumber` | String [1..36] | Order number (ID) in the merchant's system, must be unique for each merchant. |
|  |  |  |  |
| Optional | `orderStatus` | Integer | The value of this parameter specifies the status of the order in the payment gateway. It is missing if the order has not been found. Below is the list of available values:  0 - order was registered but not paid; 1 - pre-authorized amount is on hold on the buyer's account (for two-phase payments); 2 - order amount is fully authorized; 3 - authorization canceled; 4 - transaction was refunded; 5 - access control server of the issuing bank initiated authorization procedure; 6 - authorization declined. 7 - pending order payment; 8 - intermediate completion for multiple partial completion. |
|  |  |  |  |
| Optional | `actionCode` | String | Response code from the processing bank. Contains a numeric value. See the list of action codes here. |
|  |  |  |  |
| Optional | `actionCodeDescription` | String [1..512] | actionCode description returned from the processing bank. |
|  |  |  |  |
| Optional | `originalActionCode` | String [1..15] | Response code received from the processing system. To enable receiving this field, contact the technical support service. |
|  |  |  |  |
| Optional | `amount` | Integer [0..12] | Payment amount in minor currency units (e.g. in cents). |
|  |  |  |  |
| Optional | `currency` | String [3] | ISO 4217 encoded currency key. If not specified, the default value is used. Only digits are allowed. |
|  |  |  |  |
| Optional | `date` | Integer | Order registration date as the amount of milliseconds since 00:00 January 1, 1970 GMT (UNIX time). Example: 1740392720718 (Corresponds to February 24, 2025, 10:25:20 (UTC)). |
|  |  |  |  |
| Optional | `ip` | String [1..39] | Buyer's IP address. IPv6 is supported in all requests. (up to 39 characters).  If you use 3DS 2 authentication, we recommend passing a real IP address of the customer in this parameter. This increases conversion rates on the ACS side. |
|  |  |  |  |
| Conditional | `cardAuthInfo` | Object | Information about the buyer's payment card. See the description below. |
| Optional | `authDateTime` | Integer | Authorization date and time, shown as the amount of milliseconds since 00:00 January 1, 1970 GMT (UNIX time). Example: 1740392720718 (Corresponds to February 24, 2025, 10:25:20 (UTC)). |
|  |  |  |  |
| Optional | `terminalId` | String [1..10] | Terminal identifier in the system that processes the payment. |
| Optional | `authRefNum` | String [1..24] | Reference number of the payment authorization that has been assigned to it upon its registration. |
|  |  |  |  |
| Optional | `paymentNetRefNum` | String [1..512] | Original Network Reference Number - a unique identifier assigned by the card network (e.g., Mastercard, Visa) to the original transaction (such as a purchase or authorization). When a follow-up transaction is initiated (e.g., refund, chargeback, recurring payment), this number must be included to: Link the new transaction to the original one Ensure proper tracking and reconciliation Meet network compliance requirements This parameter is returned only if the getP2PStatus version is 7 or higher. |
|  |  |  |  |
| Conditional | `paymentAmountInfo` | Object | A parameter containing embedded parameters with information about confirmation, debiting and refund amounts. See the description below. |
| Conditional | `bankInfo` | Object | Contains the embedded bankCountryName parameter. See the description below. |
| Conditional | `payerData` | Object | Contains the embedded paymentAccountReference parameter. See the description below. |

`cardAuthInfo` contains the following elements.

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `expiration` | Integer [6] | Card expiration date in the following format: YYYYMM. |
|  |  |  |  |
| Mandatory | `cardholderName` | String [1..26] | Cardholder's name in Latin characters. Allowed symbols: Latin characters, period, space. |
|  |  |  |  |
| Mandatory | `approvalCode` | String [6] | IPS authorization code. This field has a fixed length (six symbols) and can contain digits and Latin letters. |
|  |  |  |  |
| Mandatory | `authCode` | Integer [6] | Deprecated parameter (not used). Its value is always 2 regardless the order status and authorization code of the processing system. |
|  |  |  |  |
| Mandatory | `pan` | String [1..19] | Payment card number |
|  |  |  |  |
| Optional | `detokenizedPanRepresentation` | String [1..19] | The detokenized card number (the last 4 digits or in a masked form). |
|  |  |  |  |
| Optional | `detokenizedPanExpiryDate` | String | The card's detokenized expiration date in the following format: "YYYYMM". |

`paymentAmountInfo` contains the following elements.

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `paymentState` | String | Order status, this parameter can have the following values:  CREATED - order created (but not paid); APPROVED - order approved (funds are on hold on buyer's account); DEPOSITED - order deposited (buyer is charged); DECLINED - order declined; REVERSED - order canceled; REFUNDED - refund. |
|  |  |  |  |
| Mandatory | `approvedAmount` | Integer [0..12] | Amount in minimum currency units (e.g. cents) that was put on hold on buyer's account. Used in two-phase payments only. In case of partial authorization, this amount may be less than the order registration amount. |
|  |  |  |  |
| Mandatory | `depositedAmount` | Integer [1..12] | Charged amount in minimum currency units (e.g., in cents). In case of partial authorization, this amount may be less than the order registration amount. |
|  |  |  |  |
| Mandatory | `refundedAmount` | Integer [1..12] | Refunded amount in minimum currency units. |

`bankInfo` block contains the following elements.

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `bankCountryName` | String [1..160] | Country of the issuing bank. |

`payerData` block contains the following elements.

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `paymentAccountReference` | String [1..29] | The unique account number of the client, which links all their payment means within the IPS (cards and tokens). |

More information about how to know whether the payment was successfull or not is available here.

### Examples

#### Request example

```bash
curl --request POST \
--url  https://dev.bpcbt.com/payment/token/payment.do \
--header 'Content-Type: application/json' \
--data '{
    "username":"test_user",
    "password":"test_user_password",
    "merchant":"test_merch",
    "amount":"1000000",
    "tii":"CI",
    "clientId":"vtsTestClient",
    "tokenType":"VTS",
    "cvc":"123",
    "cardHolderName":"DOS TESTOS",
    "paymentData": {
        "dpan":"4572433771970368",
        "MM":"12",
        "YYYY":"2028",
        "tokenCryptogram":"Aetydhdjjdkfkndnd55gd="
    },
        "orderPayerData":{
            "mobilePhone":"+492115684962"
    },
    "returnUrl":"https://mybestmerchantreturnurl.com",
    "failUrl":"https://mybestmerchantfailurl.com",
    "dynamicCallbackUrl":"https://test.com/callback",
    "externalScaExemptionIndicator":"TRA",
    "jsonParams":{
        "email":"test@gmail.com"
    },
    "clientBrowserInfo": {
        "userAgent":"Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/111.0.0.0 Safari/537.36 Edg/111.0.1661.41",
        "fingerprint":850891523,
        "OS":"Windows",
        "OSVersion":"10",
        "isMobile":false,
        "screenPrint":"Current Resolution: 1536x864, Available Resolution: 1536x824, Color Depth: 24, Device XDPI: undefined, Device YDPI: undefined",
        "colorDepth":24,
        "screenHeight":"864",
        "screenWidth":"1536",
        "plugins":"PDF Viewer, Chrome PDF Viewer, Chromium PDF Viewer, Microsoft Edge PDF Viewer, WebKit built-in PDF",
        "javaEnabled":false,
        "javascriptEnabled":true,
        "browserLanguage":"en-US",
        "browserTimeZone":"Europe/London",
        "browserTimeZoneOffset":-180,
        "browserAcceptHeader":"*/*",
        "browserIpAddress":"10.99.50.37"
    },
}'
```

#### Response example

```json
{
  "success": true,
  "data": {
    "orderId": "2a2b97e0-7284-746a-998a-752f0a8246f8",
    "is3DSVer2": false
  },
  "orderStatus": {
    "errorCode": "0",
    "orderNumber": "9049",
    "orderStatus": 2,
    "actionCode": 0,
    "actionCodeDescription": "Request is processed successfully",
    "amount": 1000000,
    "currency": "978",
    "date": 1734729964138,
    "ip": "10.99.50.37",
    "cardAuthInfo": {
      "expiration": "202412",
      "cardholderName": "DOS TESTOS",
      "approvalCode": "123456",
      "authCode": 2,
      "pan": "4111111111111111"
    },
    "authDateTime": 1734729964273,
    "terminalId": "12346789",
    "authRefNum": "111111111111",
    "paymentNetRefNum": "54674915300274269950",
    "paymentAmountInfo": {
      "paymentState": "payment_deposited",
      "approvedAmount": 1000000,
      "depositedAmount": 1000000,
      "refundedAmount": 0
    },
    "bankInfo": {
      "bankCountryName": "<UNKNOWN>"
    },
    "payerData": {}
  }
}
```

