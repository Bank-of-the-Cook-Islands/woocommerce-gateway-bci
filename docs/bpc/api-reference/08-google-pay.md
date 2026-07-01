> Source: https://dev.bpcbt.com/en/integration/api/rest.html

## Google Pay order registration

The `https://dev.bpcbt.com/payment/google/payment.do` request is used to register and pay for the order.

When sending the request, you should use the header: `Content-Type: application/json`

### Request parameters

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `merchant` | String [1..255] | To register an order and carry out payment on behalf of another merchant, specify the merchant's API account login in this parameter. Can be used only if you have the permission to see the transactions of other merchants or if the specified merchant is your child merchant. |
|  |  |  |  |
| Mandatory | `orderNumber` | String [1..36] | Order number (ID) in the merchant's system, must be unique for each order. |
|  |  |  |  |
| Optional | `description` | String [1..598] | Order description in any format. To enable sending this field to the processing system, contact the technical support service. It is not allowed to fill this parameter with personal data or payment data (card numbers, etc.). This requirement is due to the fact that the order description is not masked in Merchant Portal and log files. |
|  |  |  |  |
| Optional | `language` | String [2] | ISO 639-1 encoded language key. If the language is not specified, the default language specified in the store settings is used. Supported languages: en,el,ro,bg,pt,sw,hu,it,pl,de,fr,kh,cn,es,ka,da,et,fi,lt,lv,nl,sv. |
|  |  |  |  |
| Optional | `additionalParameters` | Object | Additional parameters of the order that are stored in the merchant personal area for the subsequent viewing. Each new pair of a parameter name and its value must be separated by a comma. Below is a usage example. { "firstParamName": "firstParamValue", "secondParamName": "secondParamValue"} When storing a credential, this tag can contain parameters that specify the type of the stored credential. See the list of parameters. |
|  |  |  |  |
| Optional | `preAuth` | Boolean | Parameter that defines the necessity of a pre-authorization (putting the amount on hold on the customer's account until its debiting). The following values are available:  true - two-phase payments enabled; false - one-phase payments enabled (money are charged right away). If the parameter is missing, one-phase payment is made. |
|  |  |  |  |
| Optional | `autocompletionDate` | String [19] | The date and time when the two-phase payment must be completed automatically in the following format: 2025-12-29T13:02:51. The used timezone is UTC+0. To enable sending this field to the processing system, contact your technical support service. |
|  |  |  |  |
| Optional | `autoReverseDate` | String [19] | The date and time when the two-phase payment must be reversed automatically in the following format: 2025-06-23T13:02:51. The used timezone is UTC+0. To enable sending this field to the processing system, contact your technical support service. |
|  |  |  |  |
| Mandatory | `clientId` | String [0..255] | Customer number (ID) in the merchant's system — up to 255 characters. Used to implement the functionality of stored-credential transactions. Can be returned in the response if the merchant is allowed to store credentials. Specifying this parameter in stored-credential transactions is mandatory. Otherwise, a payment will be unsuccessful. |
|  |  |  |  |
| Optional | `tii` | String | Transaction initiator indicator. A parameter indicating what type of operation will be carried out by the initiator (Customer or Merchant). Possible values. |
|  |  |  |  |
| Mandatory | `paymentToken` | String [1..8192] | A token obtained from Google Pay and encoded in Base64. |
|  |  |  |  |
| Mandatory | `ip` | String [1..39] | Buyer's IP address. IPv6 is supported in all requests. (up to 39 characters).  If you use 3DS 2 authentication, we recommend passing a real IP address of the customer in this parameter. This increases conversion rates on the ACS side. |
|  |  |  |  |
| Mandatory | `amount` | Integer [0..12] | Payment amount in minor currency units (e.g. in cents). |
|  |  |  |  |
| Optional | `currencyCode` | String [3] | Numeric ISO 4217 code of the payment currency. If this parameter is not specified, it is considered to be equal to the default currency code. |
|  |  |  |  |
| Mandatory | `returnUrl` | String [1..512] | The address to which the user will be redirected if the payment is successful. The address must be specified in full including the protocol used (for example, https://mybestmerchantreturnurl.com instead of mybestmerchantreturnurl.com). Otherwise, the user will be redirected to the address of the following type https://dev.bpcbt.com/payment/<merchant_address>.  The address must not be a relative path, i.e. it must not start with "." and "/". Otherwise, error 4 will be returned: "The return URL is invalid". For example: ../test.html, /test.html- invalid URLs. |
|  |  |  |  |
| Optional | `failUrl` | String [1..512] | The address to which the user is to be redirected in case of a failed payment. The address must be specified in full including the protocol used (for example, https://mybestmerchantreturnurl.com instead of mybestmerchantreturnurl.com). Otherwise, the user will be redirected to the address of the following type https://dev.bpcbt.com/payment/<merchant_address>.  The address must not be a relative path, i.e. it must not start with "." and "/". Otherwise, error 4 will be returned: "The return URL is invalid". For example: ../test.html, /test.html- invalid URLs. |
|  |  |  |  |
| Optional | `dynamicCallbackUrl` | String [1..512] | This parameter allows you to use the functionality of sending callback notifications dynamically. Here you can pass the address to which all "payment" callback notifications activated for the merchant will be sent. "Payment" notifications are callback notifications related to the following events: successful hold, payment declined by timeout, cardpresent payment is declined, successful debit, refund, cancellation. At the same time, callback notifications activated for the merchant that are not related to payments (enabling/disabling a stored credential, storing a credential) will be sent to a static address for callbacks. Whether the parameter is mandatory or not depends on the merchant configuration on Payment Gateway side. |
|  |  |  |  |
| Conditional | `email` | String [1..40] | Email to be displayed on the payment page. Customer's email must be passed if client notification is configured for the merchant. Example: client_mail@email.com. For payment by VISA with 3DS authorization, it is necessary to specify either phone or email of the cardholder. |
|  |  |  |  |
| Optional | `mcc` | Integer [4] | Merchant Category Code. Using this parameter requires a special permission. You can use only the values from the predefined list of allowed MCC values. Contact the support team for details. |
|  |  |  |  |
| Optional | `mvv` | String [1..10] | Merchant verification value from Mastercard for tokenized transactions. To pass this parameter, a special setting must be enabled (contact technical support). |
|  |  |  |  |
| Optional | `paymentFacilitator` | Object | A block with the parameters of a payment facilitator, i.e. a merchant who allows several submerchants to accept payments under its account. This parameter is used if a special setting is enabled (contact the support team). See nested parameters. |
| Optional | `billingPayerData` | Object | A block with the client's registration data (address, postal code) necessary for passing the address verification within the AVS/AVV services. Mandatory if the feature is enabled for the merchant on Payment Gateway side. See nested parameters. |
| Optional | `shippingPayerData` | Object | Object containing customer delivery data. It is used for further 3DS authentication of the client. See nested parameters. |
| Optional | `preOrderPayerData` | Object | Object containing pre-order data. It is used for further 3DS authentication of the client. See nested parameters. |
| Optional | `orderPayerData` | Object | Object containing data about the order payer. It is used for further 3DS authentication of the client. See nested parameters. |
| Optional | `billingAndShippingAddressMatchIndicator` | String [1] | Indicator for matching the cardholder's billing address and shipping address. This parameter is used for further 3DS authentication of the customer. Possible values:  Y - the cardholder's billing address and shipping address match; N - cardholder billing address and shipping address do not match. |
|  |  |  |  |
| Optional | `clientBrowserInfo` | Object | A block with the data about the client's browser that is sent to ACS during the 3DS authentication. To pass this block, you should have a special setting (contact the support team). See nested parameters. |
|  |  |  |  |

If 3DS2 is used, the following parameters should be passed as well:

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Conditional | `threeDSServerTransId` | String [1..36] | Transaction identifier created on 3DS Server. Mandatory for 3DS authentication. |
|  |  |  |  |
| Optional | `threeDSVer2FinishUrl` | String [1..512] | URL where Customer should be redirected after authentication on ACS Server. |
|  |  |  |  |
| Optional | `threeDSMethodNotificationUrl` | String [1..512] | URL where notification about performed 3DS-method should be sent to. |

Possible values of `tii` (read about the stored credential types supported by the Payment Gateway here):

| tii value | Description | Transaction type | Transaction initiator | Card data for transaction | Card data saved after transaction | Note |
| --- | --- | --- | --- | --- | --- | --- |
| Empty |  | Regular | Customer | Entered by Customer | No | An e-commerce transaction, credential is not stored. |
| CI | Initial Common CIT | Initiating | Customer | Entered by Customer | Yes | An e-commerce transaction, credential is stored. This value is possible to pass only if the "Vendor pays common bindings creation is allowed" permission is enabled. |
| RI | Initial Recurrent CIT | Initiating | Customer | Entered by Customer | Yes | An e-commerce transaction, credential is stored. |
| II | Initial Installment CIT | Initiating | Customer | Entered by Customer | Yes | An e-commerce transaction, credential is stored. |

Additional parameters that specify the type of created stored credential and are passed in `additionalParameters`:

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Conditional | `installments` | Integer [3] | Maximum number of allowed authorizations for installment payments. Should be specified when creating an installment stored credential. |
|  |  |  |  |
| Conditional | `recurringFrequency` | Integer [2] | Maximum number of days between authorizations. Positive integer from 1 to 28. Should be specified when creating a recurring or installment stored credential. |
|  |  |  |  |
| Conditional | `recurringExpiry` | String [8] | The date after which further authorizations should not be performed. Format: YYYYMMDD. Should be specified when creating a recurring or installment stored credential. |
|  |  |  |  |

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

`data` block contains the following elements.

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `orderId` | String [1..36] | Order number in the payment gateway. Unique within the payment gateway. |
|  |  |  |  |
| Conditional* | `termUrl` | String [1..512] | In a successful response in case of a 3D-Secure payment. The URL address to which ACS redirects the cardholder after authentication. For details see Redirect to ACS. |
|  |  |  |  |
| Conditional* | `acsUrl` | String [1..512] | The URL address for redirecting to ACS. It is returned in a successful response in case of a 3D-Secure payment, when redirect to the ACS is needed. For details see Redirect to ACS. |
|  |  |  |  |
| Conditional* | `paReq` | String [1..255] | PAReq (Payment Authentication Request) - a message that should be sent to ACS together with redirect. It is returned in a successful response in case of a 3D-Secure payment, when redirect to the ACS is needed. This message contains the Base64-encoded data necessary for the cardholder authentication. For details see Redirect to ACS. |
|  |  |  |  |
| Conditional** | `bindingId` | String [1..255] | Identifier of an already existing stored credential. This is the card ID tokenized by the Gateway. Can be used only if the merchant has the permission to work with stored credentials. If this parameter is passed in this request, it means that:  This order can only be paid with a stored credential; The payer will be redirected to a payment page where only CVC entry is required. |
|  |  |  |  |
| Optional | `detokenizedPanRepresentation` | String [1..19] | The detokenized card number (the last 4 digits or in a masked form). |
|  |  |  |  |
| Optional | `detokenizedPanExpiryDate` | String | The card's detokenized expiration date in the following format: "YYYYMM". |

* Only if additional authentication is used on the issuing bank's ACS
** The parameter is returned if the bindings are used

`data` block can also include `payerData` element, which contains the following parameters.

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Optional | `paymentAccountReference` | String [1..29] | The unique account number of the client, which links all their payment means within the IPS (cards and tokens). |
|  |  |  |  |

If 3DS2 protocol is used, the response to the request also includes the following parameters in the `data` block:

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `is3DSVer2` | Boolean | Possible values: true or false. Flag showing that payment uses 3DS2. |
|  |  |  |  |
| Mandatory | `threeDSServerTransId` | String [1..36] | Transaction identifier created on 3DS Server. Mandatory for 3DS authentication. |
|  |  |  |  |
| Optional | `threeDSMethodUrl` | String [1..512] | URL of ACS Server for gathering browser data. |
|  |  |  |  |
| Optional | `threeDSMethodUrlServer` | String [1..512] | URL of 3DS Server for gathering browser data to be included in the AReq (Authentication Request) from 3DS Server to ACS Server. |
|  |  |  |  |
| Optional | `threeDSMethodDataPacked` | String [1..1024] | Base-64-encoded data of CReq (Challenge Response) to be sent to ACS Server. |
|  |  |  |  |
| Optional | `threeDSMethodURLServerDirect` | String [1..512] | URL of 3dsmethod.do for executing the 3DS method on 3DS Server via Payment Gateway (subject to respective Merchant-level permission). |

`error` block contains the following elements.

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `code` | String [1..3] | Code as an information parameter stating an error occurred. |
|  |  |  |  |
| Mandatory | `message` | String [1..512] | Information parameter that is an error description to be displayed to the user. The parameter may vary, so it should not be hardcoded. |
|  |  |  |  |
| Mandatory | `description` | String [1..598] | A detailed technical explanation of the error - the contents of this parameter should not to be displayed to the customer. |
|  |  |  |  |

Response is successful only if http status code = 200 and errorCode = 0.

You should request getOrderStatusExtended.do and check the status of transaction.

More information about how to know whether the payment was successfull or not is available here.

### Examples

#### Request example

```bash
curl --request POST \
--url https://dev.bpcbt.com/payment/google/payment.do \
--header 'Content-Type: application/json' \
--data-raw '{
  "merchant": "OurBestMerchantLogin",
  "orderNumber": "UAF-203974-DE",
  "language": "EN",
  "preAuth": true,
  "description" : "Test description",
  "additionalParameters":
  {
      "firstParamName": "firstParamValue",
        "secondParamName": "secondParamValue"
  },
  "paymentToken": "eyJtZXJjaGFudCI6ICJ...FnXCJ9In0=",
  "ip" : "127.0.0.1",
  "amount" : "230000",
  "currencyCode" : 978,
  "failUrl" : "https://mybestmerchantfailurl.com"
  "returnUrl" : "https://mybestmerchantreturnurl.com"
}'
```

#### Response example

```json
{
"success":true,
"data": {
 "orderId": "12312312123"
 "is3DSVer2": true,
 "threeDSServerTransId": "f44d6d21-1874-45a5-aeb0-1c710dd6e134",
 "threeDSMethodURLServer": "https://test.com/3dsserver/gatherClientInfo?threeDSServerTransID=f44d6d21-1874-45a5-aeb0-1c710dd6e134"
 }
}
```

## Google Pay Direct

The request used to make a direct payment via Google Pay is `https://dev.bpcbt.com/payment/google/paymentDirect.do`. It is used to register and pay for the order.

When sending the request, you should use the header: `Content-Type: application/json`

This request can be used for integrations that involve payment data decoding on Merchant side.

If the value of tii parameter is R,U, or F, the request must contain originalPaymentNetRefNum and originalPaymentDate parameters. If these parameters are not passed, the regular payment is performed.

### Request parameters

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `username` | String [1..30] | Merchant's API account login. |
|  |  |  |  |
| Mandatory | `password` | String [1..30] | Merchant's API account password. |
|  |  |  |  |
| Mandatory | `orderNumber` | String [1..36] | Order number (ID) in the merchant's system, must be unique for each order. |
|  |  |  |  |
| Optional | `description` | String [1..598] | Order description in any format. To enable sending this field to the processing system, contact the technical support service. It is not allowed to fill this parameter with personal data or payment data (card numbers, etc.). This requirement is due to the fact that the order description is not masked in Merchant Portal and log files. |
|  |  |  |  |
| Optional | `language` | String [2] | ISO 639-1 encoded language key. If the language is not specified, the default language specified in the store settings is used. Supported languages: en,el,ro,bg,pt,sw,hu,it,pl,de,fr,kh,cn,es,ka,da,et,fi,lt,lv,nl,sv. |
|  |  |  |  |
| Optional | `additionalParameters` | Object | Additional parameters of the order that are stored in the merchant personal area for the subsequent viewing. Each new pair of a parameter name and its value must be separated by a comma. Below is a usage example. { "firstParamName": "firstParamValue", "secondParamName": "secondParamValue"} When storing a credential, this tag can contain parameters that specify the type of the stored credential. See the list of parameters. |
|  |  |  |  |
| Optional | `preAuth` | Boolean | Parameter that defines the necessity of a pre-authorization (putting the amount on hold on the customer's account until its debiting). The following values are available:  true - two-phase payments enabled; false - one-phase payments enabled (money are charged right away). If the parameter is missing, one-phase payment is made. |
|  |  |  |  |
| Optional | `autocompletionDate` | String [19] | The date and time when the two-phase payment must be completed automatically in the following format: 2025-12-29T13:02:51. The used timezone is UTC+0. To enable sending this field to the processing system, contact your technical support service. |
|  |  |  |  |
| Optional | `autoReverseDate` | String [19] | The date and time when the two-phase payment must be reversed automatically in the following format: 2025-06-23T13:02:51. The used timezone is UTC+0. To enable sending this field to the processing system, contact your technical support service. |
|  |  |  |  |
| Optional | `clientId` | String [0..255] | Customer number (ID) in the merchant's system — up to 255 characters. Used to implement the functionality of stored-credential transactions. Can be returned in the response if the merchant is allowed to store credentials. Specifying this parameter in stored-credential transactions is mandatory. Otherwise, a payment will be unsuccessful. |
|  |  |  |  |
| Optional | `protocolVersion` | String | Protocol version as defined by Google for a paymentToken: ECv1 (by default) or ECv2 |
|  |  |  |  |
| Mandatory | `paymentToken` | String [1..8192] | Payment data as received from Google Pay and decrypted by a merchant. Flow:  Receive PKPaymentToken Object from Google Pay (Payment Token Format Reference) with encrypted payment data; Decrypt (ECC/RSA) paymentData to get clear-text view of the object: {"paymentMethod": "CARD","paymentMethodDetails": {"pan": "5555555555555599","expirationMonth": 12,"expirationYear": 2024},"gatewayMerchantId": "GPay-decrypted","messageId": "AH2EjtcHYs1Ye9Baqr4FAM735VNThPiP","messageExpiration": "1577862000000"}; BASE64 encode clear-text paymentData object and send it as paymentToken. |
|  |  |  |  |
| Optional | `ip` | String [1..39] | Buyer's IP address. IPv6 is supported in all requests. (up to 39 characters).  If you use 3DS 2 authentication, we recommend passing a real IP address of the customer in this parameter. This increases conversion rates on the ACS side. |
|  |  |  |  |
| Mandatory | `amount` | Integer [0..12] | Payment amount in minor currency units (e.g. in cents). |
|  |  |  |  |
| Optional | `currencyCode` | String [3] | Numeric ISO 4217 code of the payment currency. If this parameter is not specified, it is considered to be equal to the default currency code. |
|  |  |  |  |
| Mandatory | `returnUrl` | String [1..512] | The address to which the user will be redirected if the payment is successful. The address must be specified in full including the protocol used (for example, https://mybestmerchantreturnurl.com instead of mybestmerchantreturnurl.com). Otherwise, the user will be redirected to the address of the following type https://dev.bpcbt.com/payment/<merchant_address>.  The address must not be a relative path, i.e. it must not start with "." and "/". Otherwise, error 4 will be returned: "The return URL is invalid". For example: ../test.html, /test.html- invalid URLs. |
|  |  |  |  |
| Optional | `failUrl` | String [1..512] | The address to which the user is to be redirected in case of a failed payment. The address must be specified in full including the protocol used (for example, https://mybestmerchantreturnurl.com instead of mybestmerchantreturnurl.com). Otherwise, the user will be redirected to the address of the following type https://dev.bpcbt.com/payment/<merchant_address>.  The address must not be a relative path, i.e. it must not start with "." and "/". Otherwise, error 4 will be returned: "The return URL is invalid". For example: ../test.html, /test.html- invalid URLs. |
|  |  |  |  |
| Optional | `merchant` | String [1..255] | To register an order and carry out payment on behalf of another merchant, specify the merchant's API account login in this parameter. Can be used only if you have the permission to see the transactions of other merchants or if the specified merchant is your child merchant. |
|  |  |  |  |
| Optional | `features` | String | Features of the order. To specify multiple features, use this parameter several times in one request. As an example, below are the possible values.  VERIFY - If you specify this value in the order registration request, cardholder will be verified however they will not be charged any amount, so in this case amount parameter can be 0. Verification allows to make sure that a payment card is used by its legitimate owner, and further you can charge them without authentication (CVC, 3D-Secure). Even if some amount is passed in the request, the customer will not be charged if VERIFY feature is used. This value can be also used for storing the credential – in this case, the clientId parameter must be passed as well. Read more here. FORCE_TDS - Force 3-D Secure payment. If a payment card does not support 3-D Secure, the transaction will fail. FORCE_SSL - Force SSL payment (without 3-D Secure). FORCE_FULL_TDS - After 3-D Secure authentication, PaRes status must be Y, which guarantees successful user authentication. Otherwise, the transaction will fail. FORCE_CREATE_BINDING - passing this feature in the order registration request forcefully stores the credential. This functionality must be enabled by Merchant level permission in the Gateway. This value cannot be passed in a request with an existing bindingId or bindingNotNeeded = true (will cause validation error). When this feature is passed, the clientId parameter must be passed as well. If you pass both FORCE_CREATE_BINDING and VERIFY features, the order will be created for storing the credential ONLY (without payment). PARTIAL_AUTHORIZATION - partial authorization is available in the order. Read more here. FORCE_PAYMENT_WAY - force payment by the payment method specified in jsonParams in the paymentWay parameter. To process these payments merchant must have sufficient permissions in the payment gateway. This is currently used for force MOTO payments. To enable this, it is necessary to pass also paymentWay parameter with the value CARD_MOTO in jsonParams. |
|  |  |  |  |
| Optional | `tii` | String | Transaction initiator indicator. A parameter indicating what type of operation will be carried out by the initiator (Customer or Merchant). Possible values. |
|  |  |  |  |
| Optional | `mcc` | Integer [4] | Merchant Category Code. Using this parameter requires a special permission. You can use only the values from the predefined list of allowed MCC values. Contact the support team for details. |
|  |  |  |  |
| Optional | `mvv` | String [1..10] | Merchant verification value from Mastercard for tokenized transactions. To pass this parameter, a special setting must be enabled (contact technical support). |
|  |  |  |  |
| Optional | `paymentFacilitator` | Object | A block with the parameters of a payment facilitator, i.e. a merchant who allows several submerchants to accept payments under its account. This parameter is used if a special setting is enabled (contact the support team). See nested parameters. |
| Conditional | `originalPaymentNetRefNum` | String [1..36] | The identifier of the original or previous successful transaction in the payment system in relation to the performed stored-credential transaction - TRN ID. Is passed when tii = R,U, or F. Is mandatory when using merchant's stored credentials in stored credential transfers. |
|  |  |  |  |
| Conditional | `originalPaymentDate` | String | Date of initiating transaction. The format is Unix timestamp, in milliseconds. Is passed when tii = R,U, or F. |
|  |  |  |  |
| Optional | `threeDSProtocolVersion` | String | 3DS protocol version. Possible values are "2.1.0", "2.2.0" for 3DS2. If threeDSProtocolVersion is not passed in the request, then the default value will be used for 3D Secure authorization (2.1.0 - for 3DS 2). |
|  |  |  |  |
| Optional | `externalScaExemptionIndicator` | String | The type of SCA (Strong Customer Authentication) excemption. If this parameter is specified, the transaction will be processed depending on your settings in the payment gateway: either forced SSL operation will be done, or the issuer bank will get the information about SCA excemption and decide to perform operation with or without 3DS authentication (for details, contact our support team). Allowed values:  LVP – Low Value Payments transaction. You can consider a transaction as low risk based on the transaction amount, the client's transactions per day or the client's total daily amount. TRA – Transaction Risk Analysis transaction, i.e., the transaction that has passed successful anti-fraud check.  To pass this parameter, you must have sufficient permissions in the payment gateway. |
|  |  |  |  |
| Conditional | `email` | String [1..40] | Email to be displayed on the payment page. Customer's email must be passed if client notification is configured for the merchant. Example: client_mail@email.com. For payment by VISA with 3DS authorization, it is necessary to specify either phone or email of the cardholder. |
|  |  |  |  |
| Optional | `billingPayerData` | Object | A block with the client's registration data (address, postal code) necessary for passing the address verification within the AVS/AVV services. Mandatory if the feature is enabled for the merchant on Payment Gateway side. See nested parameters. |
| Optional | `shippingPayerData` | Object | Object containing customer delivery data. It is used for further 3DS authentication of the client. See nested parameters. |
| Optional | `preOrderPayerData` | Object | Object containing pre-order data. It is used for further 3DS authentication of the client. See nested parameters. |
| Optional | `orderPayerData` | Object | Object containing data about the order payer. It is used for further 3DS authentication of the client. See nested parameters. |
| Optional | `billingAndShippingAddressMatchIndicator` | String [1] | Indicator for matching the cardholder's billing address and shipping address. This parameter is used for further 3DS authentication of the customer. Possible values:  Y - the cardholder's billing address and shipping address match; N - cardholder billing address and shipping address do not match. |
|  |  |  |  |
| Optional | `clientBrowserInfo` | Object | A block with the data about the client's browser that is sent to ACS during the 3DS authentication. To pass this block, you should have a special setting (contact the support team). See nested parameters. |
|  |  |  |  |

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

Possible values of `tii` (read about the stored credential types supported by the Payment Gateway here):

| tii value | Description | Transaction type | Transaction initiator | Card data for transaction | Card data saved after transaction | Note |
| --- | --- | --- | --- | --- | --- | --- |
| Empty |  | Regular | Customer | Entered by Customer | No | An e-commerce transaction, credential is not stored. |
| CI | Initial Common CIT | Initiating | Customer | Entered by Customer | Yes | An e-commerce transaction, credential is stored. This value is possible to pass only if the "Vendor pays common bindings creation is allowed" permission is enabled. |
| RI | Initial Recurrent CIT | Initiating | Customer | Entered by Customer | Yes | An e-commerce transaction, credential is stored. |
| II | Initial Installment CIT | Initiating | Customer | Entered by Customer | Yes | An e-commerce transaction, credential is stored. |

Additional parameters that specify the type of created stored credential and are passed in `additionalParameters`:

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Conditional | `installments` | Integer [3] | Maximum number of allowed authorizations for installment payments. Should be specified when creating an installment stored credential. |
|  |  |  |  |
| Conditional | `recurringFrequency` | Integer [2] | Maximum number of days between authorizations. Positive integer from 1 to 28. Should be specified when creating a recurring or installment stored credential. |
|  |  |  |  |
| Conditional | `recurringExpiry` | String [8] | The date after which further authorizations should not be performed. Format: YYYYMMDD. Should be specified when creating a recurring or installment stored credential. |
|  |  |  |  |

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

If 3DS2 is used, the following parameters should be passed as well:

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Conditional | `threeDSServerTransId` | String [1..36] | Transaction identifier created on 3DS Server. Mandatory for 3DS authentication. |
|  |  |  |  |
| Optional | `threeDSVer2FinishUrl` | String [1..512] | URL where Customer should be redirected after authentication on ACS Server. |
|  |  |  |  |
| Optional | `threeDSSDK` | Boolean | Possible values: true or false. Flag showing that payment comes from 3DS SDK. |
|  |  |  |  |
| Optional | `threeDSMethodNotificationUrl` | String [1..512] | URL where notification about performed 3DS-method should be sent to. |

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
| Mandatory* | `data` | Object | Returns only if the payment is successful. |
|  |  |  |  |
| Mandatory* | `error` | Object | This parameter is returned only if the payment failed. |

`data` block contains the following elements.

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `orderId` | String [1..36] | Order number in the payment gateway. Unique within the payment gateway. |
|  |  |  |  |
| Only if additional authentication is used on the issuing bank's ACS | `termUrl` | String [1..512] | In a successful response in case of a 3D-Secure payment. The URL address to which ACS redirects the cardholder after authentication. For details see Redirect to ACS. |
| Only if additional authentication is used on the issuing bank's ACS | `acsUrl` | String [1..512] | The URL address for redirecting to ACS. It is returned in a successful response in case of a 3D-Secure payment, when redirect to the ACS is needed. For details see Redirect to ACS. |
|  |  |  |  |
| Only if additional authentication is used on the issuing bank's ACS | `paReq` | String [1..255] | PAReq (Payment Authentication Request) - a message that should be sent to ACS together with redirect. It is returned in a successful response in case of a 3D-Secure payment, when redirect to the ACS is needed. This message contains the Base64-encoded data necessary for the cardholder authentication. For details see Redirect to ACS. |
| The parameter is returned if bindings are used | `bindingId` | String [1..255] | Identifier of an already existing stored credential. This is the card ID tokenized by the Gateway. Can be used only if the merchant has the permission to work with stored credentials. If this parameter is passed in this request, it means that:  This order can only be paid with a stored credential; The payer will be redirected to a payment page where only CVC entry is required. |
|  |  |  |  |
| Optional | `detokenizedPanRepresentation` | String [1..19] | The detokenized card number (the last 4 digits or in a masked form). |
|  |  |  |  |
| Optional | `detokenizedPanExpiryDate` | String | The card's detokenized expiration date in the following format: "YYYYMM". |

`data` block can include also `payerData` element, which contains the following parameters.

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Optional | `paymentAccountReference` | String [1..29] | The unique account number of the client, which links all their payment means within the IPS (cards and tokens). |
|  |  |  |  |

If 3DS2 protocol is used, the response to the request also includes the following parameters in the `data` block:

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `is3DSVer2` | Boolean | Possible values: true or false. Flag showing that payment uses 3DS2. |
|  |  |  |  |
| Mandatory | `threeDSServerTransId` | String [1..36] | Transaction identifier created on 3DS Server. Mandatory for 3DS authentication. |
|  |  |  |  |
| Optional | `threeDSMethodUrl` | String [1..512] | URL of ACS Server for gathering browser data. |
|  |  |  |  |
| Optional | `threeDSMethodUrlServer` | String [1..512] | URL of 3DS Server for gathering browser data to be included in the AReq (Authentication Request) from 3DS Server to ACS Server. |
|  |  |  |  |
| Optional | `threeDSMethodDataPacked` | String [1..1024] | Base-64-encoded data of CReq (Challenge Response) to be sent to ACS Server. |
|  |  |  |  |
| Optional | `threeDSMethodURLServerDirect` | String [1..512] | URL of 3dsmethod.do for executing the 3DS method on 3DS Server via Payment Gateway (subject to respective Merchant-level permission). |

Parameters in `error` block:

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `code` | String [1..3] | Code as an information parameter stating an error occurred. |
|  |  |  |  |
| Mandatory | `message` | String [1..512] | Information parameter that is an error description to be displayed to the user. The parameter may vary, so it should not be hardcoded. |
| Mandatory | `description` | String [1..598] | A detailed technical explanation of the error - the contents of this parameter should not to be displayed to the customer. |

More information about how to know whether the payment was successfull or not is available here.

### Examples

#### Request example

```bash
curl --request POST \
--url https://dev.bpcbt.com/payment/google/paymentDirect.do \
--header 'Content-Type: application/json' \
--data-raw '{
  "amount": "1000",
  "orderNumber": "350467565",
  "features": [""],
  "language": "EN",
  "password": "test_user_password",
  "paymentToken": "eyJnYXRldTWVY2hRJ...b25ZZWFyyMD0fX0=",
  "preAuth": true,
  "returnUrl": "https://mybestmerchantreturnurl.com",
  "username": "test_user"
}'
```

#### Response examples

```json
{
  "success": true,
  "data": {
    "orderId": "12312312123"
  }
}
```

