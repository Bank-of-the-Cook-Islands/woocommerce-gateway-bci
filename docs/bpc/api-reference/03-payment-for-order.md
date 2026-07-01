> Source: https://dev.bpcbt.com/en/integration/api/rest.html

# Direct Payments

# Direct payments

## Payment for order

To initiate payment on earlier registered order `https://dev.bpcbt.com/payment/rest/paymentorder.do` request is used.
Request is used in Internal 3DS Server mode, you don't need any additional permissions and/or certifications.
Request is used in External 3DS Server mode if you have agreement with Payment System or special Certificate, which alows you to perform 3DS authentacation on your own. It means, that you can use your own 3DS Server to authenticate your client using 3D Secure technology. Read more about payment with your own 3DS Server here.

When sending the request, you should use the header: `Content-Type: application/x-www-form-urlencoded`

If the value of tii parameter is R,U, or F, the request must contain originalPaymentNetRefNum and originalPaymentDate parameters. If these parameters are not passed, the regular payment is performed.

### Payment for order (internal 3DS Server)

Payment is initiated using payment card data and using 3DS authentication (authentication is regulated by permissions, managed by Support).

#### Request parameters

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `userName` | String [1..100] | Merchant's API account login. |
|  |  |  |  |
| Mandatory | `password` | String [1..30] | Merchant's API account password (mandatory, unless token is passed). If you pass your login and password to authenticate in the payment gateway, do not pass token parameter. |
|  |  |  |  |
| Mandatory | `MDORDER` | String [1..36] | Order number in the payment gateway. |
|  |  |  |  |
| Mandatory | `$PAN` | Integer [1..19] | Payment card number. Mandatory, if seToken is not passed. |
|  |  |  |  |
| Mandatory | `$CVC` | String [3] | CVC/CVV2 code on the back of a payment card. Mandatory, if seToken is not passed. Only digits are allowed. |
|  |  |  |  |
| Mandatory | `YYYY` | Integer [4] | Payment card expiry year. If seToken is not passed, it is mandatory to pass either $EXPIRY or YYYY and MM. |
|  |  |  |  |
| Mandatory | `MM` | Integer [2] | Payment card expiry month. If seToken is not passed, it is mandatory to pass either $EXPIRY or YYYY and MM. |
|  |  |  |  |
| Conditional | `$EXPIRY` | Integer [6] | Card expiration in the following format: YYYYMM. Overrides YYYY and MM parameters. If seToken is not passed, it is mandatory to pass either $EXPIRY or YYYY and MM. |
|  |  |  |  |
| Conditional | `seToken` | String | Encrypted card data that replaces $PAN, $CVC, and $EXPIRY (or YYYY,MM) parameters. Must be passed if used instead of the card data. The mandatory parameters for seToken string are timestamp, UUID, PAN, EXPDATE, MDORDER. Click here for more information about seToken generation. If seToken contains encrypted data about a stored credential (bindingId), the paymentOrderBinding.do request should be used for payment instead of paymentorder.do. |
|  |  |  |  |
| Mandatory | `TEXT` | String [1..512] | Cardholder name. |
|  |  |  |  |
| Mandatory | `language` | String [2] | ISO 639-1 encoded language key. If the language is not specified, the default language specified in the store settings is used. Supported languages: en,el,ro,bg,pt,sw,hu,it,pl,de,fr,kh,cn,es,ka,da,et,fi,lt,lv,nl,sv. |
|  |  |  |  |
| Optional | `ip` | String [1..39] | Buyer's IP address. IPv6 is supported in all requests. (up to 39 characters).  If you use 3DS 2 authentication, we recommend passing a real IP address of the customer in this parameter. This increases conversion rates on the ACS side. |
|  |  |  |  |
| Optional | `bindingNotNeeded` | Boolean | Allowed values:  true – storing the credential after the payment is disabled (a stored credential is a customer identifier passed in order registration request — after payment it will be deleted from order details); false – if payment is successful the credential can be stored (if the necessary conditions are met). This is the default value. |
|  |  |  |  |
| Optional | `jsonParams` | Object | A set of additional free-form attributes, structure: jsonParams={"param_1_name":"param_1_value",...,"param_n_name":"param_n_value"}. These fields can be passed to the Processing Center for further processing (additional setup is needed, please contact Support). If you use your own 3DS Server the payment gateway expects that every paymentOrder request will include the following additional parameters such as eci, cavv, xid etc. Please refer here for more information. To initiate 3RI authentication in case when there is no stored credentials, you may need to pass a number of additional parameters (see 3RI authentication for details). Some pre-defined jsonParams attributes:  backToShopUrl - adds checkout page button that will take a cardholder back to the assigned merchant web-site URL backToShopName - customizes default "Back to shop" button text label if used along with backToShopUrl installments - maximum number of allowed authorizations for installment payments. Is required for creating an installment stored credential. totalInstallmentAmount - total sum of all installment payments. Is required for creating an installment stored credential. recurringFrequency - minimum number of days between authorizations. Is required for creating a recurrent or installment stored credential. recurringExpiry - the date after which authorizations are not allowed, in YYYYMMDD format. Recommended for creating a recurrent or installment stored credential (mandatory for 3DS2) |
|  |  |  |  |
| Optional | `threeDSSDK` | Boolean | Possible values: true or false. Flag showing that payment comes from 3DS SDK. |
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
| Optional | `tii` | String | Transaction initiator indicator. A parameter indicating what type of operation will be carried out by the initiator (Customer or Merchant). Possible values |
|  |  |  |  |
| Optional | `externalScaExemptionIndicator` | String | The type of SCA (Strong Customer Authentication) excemption. If this parameter is specified, the transaction will be processed depending on your settings in the payment gateway: either forced SSL operation will be done, or the issuer bank will get the information about SCA excemption and decide to perform operation with or without 3DS authentication (for details, contact our support team). Allowed values:  LVP – Low Value Payments transaction. You can consider a transaction as low risk based on the transaction amount, the client's transactions per day or the client's total daily amount. TRA – Transaction Risk Analysis transaction, i.e., the transaction that has passed successful anti-fraud check.  To pass this parameter, you must have sufficient permissions in the payment gateway. |
|  |  |  |  |
| Optional | `clientBrowserInfo` | Object | A block with the data about the client's browser that is sent to ACS during the 3DS authentication. To pass this block, you should have a special setting (contact the support team). See nested parameters. |
|  |  |  |  |
| Conditional | `originalPaymentNetRefNum` | String [1..36] | The identifier of the original or previous successful transaction in the payment system in relation to the performed stored-credential transaction - TRN ID. Is passed when tii = R,U, or F. Is mandatory when using merchant's stored credentials in stored credential transfers. |
|  |  |  |  |
| Conditional | `originalPaymentDate` | String | Date of initiating transaction. The format is Unix timestamp, in milliseconds. Is passed when tii = R,U, or F. |
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
| CI | Initial Common CIT | Initiating | Customer | Entered by Customer | Yes | An e-commerce transaction, credential is stored. |
| F | Unscheduled CIT | Subsequent | Customer | Customer selects card instead of manual entry | No | An e-commerce transaction that uses a stored credential. |
| U | Unscheduled MIT | Subsequent | Merchant | No manual entry, Merchant passes the data | No | An e-commerce transaction that uses a stored credential. Used for one-phase payments only. |
| RI | Initial Recurrent CIT | Initiating | Customer | Entered by Customer | Yes | An e-commerce transaction, credential is stored. |
| R | Recurrent MIT | Subsequent | Merchant | No manual entry, Merchant passes the data | No | A recurrent transaction that uses a stored credential. Used for one-phase payments only. |
| II | Initial Installment CIT | Initiating | Customer | Entered by Customer | Yes | An e-commerce transaction, credential is stored. |
| I | Installment MIT | Subsequent | Merchant | No manual entry, Merchant passes the data | No | An installment transaction that uses a stored credential. Used for one-phase payments only. |

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

The following parameters are also passed during the authentication via the 3DS2 protocol:

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Optional | `threeDSServerTransId` | String [1..36] | Transaction identifier created on 3DS Server. Mandatory for 3DS authentication. |
|  |  |  |  |
| Optional | `threeDSVer2FinishUrl` | String [1..512] | URL where Customer should be redirected after authentication on ACS Server. |
|  |  |  |  |
| Optional | `threeDSMethodNotificationUrl` | String [1..512] | URL where notification about performed 3DS-method should be sent to. |

#### Response parameters

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `errorCode` | String [1..2] | Information parameter in case of an error, which may have different code values:  0 value - indicates success of the request processing; another number value (1-99) - indicates an error for more details of which errorMesage parameter must be inspected. It also can be missing if the result has not caused any error.  If the request, associated with an order payment, is processed successfully, it does not directly mean that the payment itself was successful. To determine whether the payment was successful or not, use getOrderStatusExtended.do request. |
|  |  |  |  |
| Optional | `errorMessage` | String [1..512] | Information parameter that is an error description in a case of error occurance. errorMessage value can vary, so it should not be hardcoded. Language of the description is set in language parameter of the request. |
|  |  |  |  |
| Optional | `info` | String | If response is successful. Result of a payment attempt. Below are the possible values.  Your payment has been processed, redirecting... Operation declined. Check the entered data and that there are enough funds on the card and repeat the operation. Redirecting... Sorry, payment cannot be completed. Redirecting... Operation declined. Contact the merchant. Redirecting... Operation declined. Contact the bank that issued the card. Redirecting... Impossible operation. Cardholder authentication completed unsuccessfully. Redirecting... No connection with bank. Try again later. Redirecting... Input time expired. Redirecting... No response from bank received. Try again later. Redirecting... |
|  |  |  |  |
| Optional | `redirect` | String [1..512] | This parameter is returned if the payment is successful and that payment did not include check for 3-D Secure involvement. Merchants can use it if they want to redirect the user to the payment gateway page. If they have their own response page then this value can be ignored. |
|  |  |  |  |
| Optional | `termUrl` | String [1..512] | In a successful response in case of a 3D-Secure payment. The URL address to which ACS redirects the cardholder after authentication. For details see Redirect to ACS. |
|  |  |  |  |
| Optional | `acsUrl` | String [1..512] | The URL address for redirecting to ACS. It is returned in a successful response in case of a 3D-Secure payment, when redirect to the ACS is needed. For details see Redirect to ACS. |
|  |  |  |  |
| Optional | `paReq` | String [1..255] | PAReq (Payment Authentication Request) - a message that should be sent to ACS together with redirect. It is returned in a successful response in case of a 3D-Secure payment, when redirect to the ACS is needed. This message contains the Base64-encoded data necessary for the cardholder authentication. For details see Redirect to ACS. |
|  |  |  |  |

`payerData` element contains the following parameters.

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Optional | `paymentAccountReference` | String [1..29] | The unique account number of the client, which links all their payment means within the IPS (cards and tokens). |
|  |  |  |  |

When authenticating via the 3DS2 protocol, the following parameters are returned during the initial request:

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

Below are the parameters to be present in the response, after a repeated request for the payment and the need to redirect the client to the ACS during the authentication via the 3DS2 protocol:

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Conditional | `acsUrl` | String [1..512] | The URL address for redirecting to ACS. It is returned in a successful response in case of a 3D-Secure payment, when redirect to the ACS is needed. For details see Redirect to ACS. |
|  |  |  |  |
| Conditional | `packedCReq` | String | Packed challenge request data. It is returned in a successful response in case of a 3D-Secure payment, when redirect to the ACS is needed. This value should be used as the ACS link creq parameter (acsUrl) to redirect the client to the ACS. For details see Redirect to ACS. |
|  |  |  |  |

More information about how to know whether the payment was successfull or not is available here.

#### Examples

#### Request example

Example of the first request:

```bash
curl --request POST \
  --url https://dev.bpcbt.com/payment/rest/paymentorder.do \
  --header 'content-type: application/x-www-form-urlencoded' \
  --data userName=test_user \
  --data password=test_user_password \
  --data MDORDER=64d3b8c2-5d87-7d92-bd20-d8db011b4f5b \
  --data '$PAN=4000001111111118' \
  --data '$CVC=123' \
  --data YYYY=2030 \
  --data MM=12 \
  --data 'TEXT=TEST CARDHOLDER' \
  --data language=en
  --data 'jsonParams={"param_1_name":"param_1_value","param_2_name":"param_2_value"}'
```

Example of the second request:

```bash
curl --request POST \
  --url https://dev.bpcbt.com/payment/rest/paymentorder.do \
  --header 'content-type: application/x-www-form-urlencoded' \
  --data userName=test_user \
  --data password=test_user_password \
  --data MDORDER=64d3b8c2-5d87-7d92-bd20-d8db011b4f5b \
  --data '$PAN=4000001111111118' \
  --data '$CVC=123' \
  --data YYYY=2030 \
  --data MM=12 \
  --data 'TEXT=TEST CARDHOLDER' \
  --data language=en \
  --data threeDSServerTransID=5802746e-3393-40c3-929a-dc966ebf08c6
```

#### Response examples

Example of the response to the first request:

```json
{
  "errorCode": 0,
  "is3DSVer2": true,
  "threeDSServerTransId": "5802746e-3393-40c3-929a-dc966ebf08c6",
  "threeDSMethodURL": "https://example.com/acs2/acs/3dsMethod",
  "threeDSMethodURLServer": "example.com/3dsserver/api/v1/client/gather?threeDSServerTransID=5802746e-3393-40c3-929a-dc966ebf08c6",
  "threeDSMethodDataPacked": "eyJ0aHJlZURTTWV0aG9kTm90aWZpY2F0aW9uVVJMIjoiaHR0cHM6Ly9hY3F1aXJlci5jb20vM2Rzc2VydmVyL2FwaS92MS9hY3Mvbm90aWZpY2F0aW9uP3RocmVlRFNTZXJ2ZXJUcmFuc0lEPTNhZmMxNjhhLTk0YjQtNGViMy04ZTJlLTgwZjZjMTg2NjY5ZCIsInRocmVlRFNTZXJ2ZXJUcmFuc0lEIjoiM2FmYzE2OGEtOTRiNC00ZWIzLThlMmUtODBmNmMxODY2NjlkIn0="
}
```

Example of the response to the second request:

```json
{
  "info": "Your order is proceeded, redirecting...",
  "errorCode": 0,
  "acsUrl": "https://example.com/acs2/acs/creq",
  "is3DSVer2": true,
  "packedCReq": "eyJ0aHJlZURTU2VydmVyVHJhbnNJRCI6IjU4MDI3NDZlLTMzOTMtNDBjMy05MjlhLWRjOTY2ZWJmMDhjNiIsIm1lc3NhZ2VUeXBlIjoiQ1JlcSIsIm1lc3NhZ2VWZXJzaW9uIjoiMi4xLjAiLCJhY3NUcmFuc0lEIjoiODFmZTU1ODUtZmZhOS00Y2NkLTljMjAtY2QzYWFiZDQwNTllIiwiY2hhbGxlbmdlV2luZG93U2l6ZSI6IjA1In0"
}
```

### Payment for order (external 3DS Server)

In order to use `paymenOrder.do` request in `external 3DS Server mode`, you need to perform 3DS authentication using your own 3DS Server.
Also, you need an additional permission managed by Support.

#### Request parameters

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `userName` | String [1..100] | Merchant's API account login. |
|  |  |  |  |
| Mandatory | `password` | String [1..30] | Merchant's API account password. |
|  |  |  |  |
| Mandatory | `MDORDER` | String [1..36] | Order number in the payment gateway. |
|  |  |  |  |
| Mandatory | `$PAN` | Integer [1..19] | Payment card number. Mandatory, if seToken is not passed. |
|  |  |  |  |
| Mandatory | `$CVC` | String [3] | CVC/CVV2 code on the back of a payment card. Mandatory, if seToken is not passed. Only digits are allowed. |
|  |  |  |  |
| Mandatory | `YYYY` | Integer [4] | Payment card expiry year. If seToken is not passed, it is mandatory to pass either $EXPIRY or YYYY and MM. |
|  |  |  |  |
| Mandatory | `MM` | Integer [2] | Payment card expiry month. If seToken is not passed, it is mandatory to pass either $EXPIRY or YYYY and MM. |
|  |  |  |  |
| Conditional | `$EXPIRY` | Integer [6] | Card expiration in the following format: YYYYMM. Overrides YYYY and MM parameters. If seToken is not passed, it is mandatory to pass either $EXPIRY or YYYY and MM. |
|  |  |  |  |
| Conditional | `seToken` | String | Encrypted card data that replaces $PAN, $CVC, and $EXPIRY (or YYYY,MM) parameters. Must be passed if used instead of the card data. The mandatory parameters for seToken string are timestamp, UUID, PAN, EXPDATE, MDORDER. Click here for more information about seToken generation. If seToken contains encrypted data about a stored credential (bindingId), the paymentOrderBinding.do request should be used for payment instead of paymentorder.do. |
|  |  |  |  |
| Mandatory | `TEXT` | String [1..512] | Cardholder name. |
|  |  |  |  |
| Mandatory | `language` | String [2] | ISO 639-1 encoded language key. If the language is not specified, the default language specified in the store settings is used. Supported languages: en,el,ro,bg,pt,sw,hu,it,pl,de,fr,kh,cn,es,ka,da,et,fi,lt,lv,nl,sv. |
|  |  |  |  |
| Optional | `ip` | String [1..39] | Buyer's IP address. IPv6 is supported in all requests. (up to 39 characters).  If you use 3DS 2 authentication, we recommend passing a real IP address of the customer in this parameter. This increases conversion rates on the ACS side. |
|  |  |  |  |
| Optional | `bindingNotNeeded` | Boolean | Allowed values:  true – storing the credential after the payment is disabled (a stored credential is a customer identifier passed in order registration request — after payment it will be deleted from order details); false – if payment is successful the credential can be stored (if the necessary conditions are met). This is the default value. |
|  |  |  |  |
| Optional | `jsonParams` | Object | A set of additional free-form attributes, structure: jsonParams={"param_1_name":"param_1_value",...,"param_n_name":"param_n_value"}. These fields can be passed to the Processing Center for further processing (additional setup is needed, please contact Support). If you use your own 3DS Server the payment gateway expects that every paymentOrder request will include the following additional parameters such as eci, cavv, xid etc. Please refer here for more information. To initiate 3RI authentication in case when there is no stored credentials, you may need to pass a number of additional parameters (see 3RI authentication for details). Some pre-defined jsonParams attributes:  backToShopUrl - adds checkout page button that will take a cardholder back to the assigned merchant web-site URL backToShopName - customizes default "Back to shop" button text label if used along with backToShopUrl installments - maximum number of allowed authorizations for installment payments. Is required for creating an installment stored credential. totalInstallmentAmount - total sum of all installment payments. Is required for creating an installment stored credential. recurringFrequency - minimum number of days between authorizations. Is required for creating a recurrent or installment stored credential. recurringExpiry - the date after which authorizations are not allowed, in YYYYMMDD format. Recommended for creating a recurrent or installment stored credential (mandatory for 3DS2) |
|  |  |  |  |
| Optional | `tii` | String | Transaction initiator indicator. A parameter indicating what type of operation will be carried out by the initiator (Customer or Merchant). Possible values |
|  |  |  |  |
| Optional | `threeDSProtocolVersion` | String | 3DS protocol version. Possible values are "2.1.0", "2.2.0" for 3DS2. If threeDSProtocolVersion is not passed in the request, then the default value will be used for 3D Secure authorization (2.1.0 - for 3DS 2). |
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

Description of parameters in `orderPayerData` object:

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Optional | `homePhone` | String [7..15] | Customer's phone number. It is always necessary to specify the country code, but you can specify or omit the + sign or 00 at the beginning. The number must be 7 to 15 digits long. Thus, the following options are valid:  +35799988877; 0035799988877; 35799988877. |
| Optional | `workPhone` | String [7..15] | Customer's phone number. It is always necessary to specify the country code, but you can specify or omit the + sign or 00 at the beginning. The number must be 7 to 15 digits long. Thus, the following options are valid:  +35799988877; 0035799988877; 35799988877. |
| Conditional | `mobilePhone` | String [7..15] | Customer's phone number. It is always necessary to specify the country code, but you can specify or omit the + sign or 00 at the beginning. The number must be 7 to 15 digits long. Thus, the following options are valid:  +35799988877; 0035799988877; 35799988877.  For payment by VISA with 3DS authorization, it is necessary to specify either phone or email of the cardholder. If you have a setting to display phone number on the payment page and have specified an invalid number, the customer will have a possibility to correct it on the payment page. |

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
| CI | Initial Common CIT | Initiating | Customer | Entered by Customer | Yes | An e-commerce transaction, credential is stored. |
| F | Unscheduled CIT | Subsequent | Customer | Customer selects card instead of manual entry | No | An e-commerce transaction that uses a stored credential. |
| U | Unscheduled MIT | Subsequent | Merchant | No manual entry, Merchant passes the data | No | An e-commerce transaction that uses a stored credential. Used for one-phase payments only. |
| RI | Initial Recurrent CIT | Initiating | Customer | Entered by Customer | Yes | An e-commerce transaction, credential is stored. |
| R | Recurrent MIT | Subsequent | Merchant | No manual entry, Merchant passes the data | No | A recurrent transaction that uses a stored credential. Used for one-phase payments only. |
| II | Initial Installment CIT | Initiating | Customer | Entered by Customer | Yes | An e-commerce transaction, credential is stored. |
| I | Installment MIT | Subsequent | Merchant | No manual entry, Merchant passes the data | No | An installment transaction that uses a stored credential. Used for one-phase payments only. |

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

#### Response parameters

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `errorCode` | String [1..2] | Information parameter in case of an error, which may have different code values:  0 value - indicates success of the request processing; another number value (1-99) - indicates an error for more details of which errorMesage parameter must be inspected. It also can be missing if the result has not caused any error.  If the request, associated with an order payment, is processed successfully, it does not directly mean that the payment itself was successful. To determine whether the payment was successful or not, use getOrderStatusExtended.do request. |
|  |  |  |  |
| Optional | `errorMessage` | String [1..512] | Information parameter that is an error description in a case of error occurance. errorMessage value can vary, so it should not be hardcoded. Language of the description is set in language parameter of the request. |
|  |  |  |  |
| Optional | `info` | String | If response is successful. Result of a payment attempt. Below are the possible values.  Your payment has been processed, redirecting... Operation declined. Check the entered data and that there are enough funds on the card and repeat the operation. Redirecting... Sorry, payment cannot be completed. Redirecting... Operation declined. Contact the merchant. Redirecting... Operation declined. Contact the bank that issued the card. Redirecting... Impossible operation. Cardholder authentication completed unsuccessfully. Redirecting... No connection with bank. Try again later. Redirecting... Input time expired. Redirecting... No response from bank received. Try again later. Redirecting... |
|  |  |  |  |

More information about how to know whether the payment was successfull or not is available here.

#### Examples

#### Request example

```bash
curl --request POST \\
  --url https://dev.bpcbt.com/payment/rest/paymentorder.do \\
  --header 'content-type: application/x-www-form-urlencoded' \\
  --data userName=test_user \\
  --data password=test_user_password \\
  --data MDORDER=0140dda0-71ed-7706-a61f-36bd00a7d8c0 \\
  --data '$PAN=4000001111111118' \\
  --data '$CVC=123' \\
  --data YYYY=2030 \\
  --data MM=12 \\
  --data 'TEXT=TEST CARDHOLDER' \\
  --data language=en \\
  --data 'jsonParams={"eci": "02", "cavv": "AkZO5XQAA0rhBxoaufa+MAABAAA=", "xid": "5010857f-8d3f-74e1-9c5a-54a000cc4110", "threeDSProtocolVersion": "2.2.0", "threeDsType": "5"}'
```

#### Response example

```json
{
  "redirect": "https://dev.bpcbt.com/payment/merchants/temp/finish.html?orderId=01493844-d4d3-703f-9f7e-a73900a7d8c0&lang=en",
  "info": "Your order is proceeded, redirecting...",
  "errorCode": 0
}
```

### Payment for an Industry Practice transaction order

To pay for an order with Industry Practice transaction characteristics, use the request `https://dev.bpcbt.com/payment/industryPractice/paymentOrder.do`.

When sending the request, you should use the header: `Content-Type: application/x-www-form-urlencoded`

#### Request parameters

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Conditional | `userName` | String [1..30] | Merchant 's API account login (mandatory, unless token is passed). If you pass your login and password to authenticate in the payment gateway, do not pass token parameter. |
|  |  |  |  |
| Conditional | `password` | String [1..30] | Merchant's API account password (mandatory, unless token is passed). If you pass your login and password to authenticate in the payment gateway, do not pass token parameter. |
|  |  |  |  |
| Conditional | `token` | String [1..256] | Value that is used for merchant authentication when requests are sent to the payment gateway (mandatory, unless userName and password are passed). If you pass this parameter, do not pass userName and password. |
|  |  |  |  |
| Mandatory | `originalMdOrder` | String [1..36] | The number of the original order in the Payment Gateway for which Industry Practice payment is made. |
|  |  |  |  |
| Mandatory | `orderNumber` | String [1..36] | Order number (ID) in the merchant's system, must be unique for each order. |
|  |  |  |  |
| Conditional | `amount` | Integer [0..12] | Payment amount in minor currency units (e.g. in cents) of original payment for Incremental, Delayed Charges, No show operations. The parameter is required for the Incremental, Delayed Charges, No show operations. The parameter must not be specified for Resubmission and Reauthorization. |
|  |  |  |  |
| Optional | `jsonParams` | Object | Object containing the attributes used to pass additional parameters. See below. The former params parameter is an alias to this parameter (i.e., the requests with params also work). |
|  |  |  |  |
| Mandatory | `tii` | String | The initiator ID of the transaction. A parameter indicating what type of operation will be carried out by the initiator (Customer or Merchant). |
|  |  |  |  |
| Optional | `features` | String | Features of the order. To specify multiple features, use this parameter several times in one request. As an example, below are the possible values.  VERIFY - If you specify this value in the order registration request, cardholder will be verified however they will not be charged any amount, so in this case amount parameter can be 0. Verification allows to make sure that a payment card is used by its legitimate owner, and further you can charge them without authentication (CVC, 3D-Secure). Even if some amount is passed in the request, the customer will not be charged if VERIFY feature is used. This value can be also used for storing the credential – in this case, the clientId parameter must be passed as well. Read more here. FORCE_TDS - Force 3-D Secure payment. If a payment card does not support 3-D Secure, the transaction will fail. FORCE_SSL - Force SSL payment (without 3-D Secure). FORCE_FULL_TDS - After 3-D Secure authentication, PaRes status must be Y, which guarantees successful user authentication. Otherwise, the transaction will fail. FORCE_CREATE_BINDING - passing this feature in the order registration request forcefully stores the credential. This functionality must be enabled by Merchant level permission in the Gateway. This value cannot be passed in a request with an existing bindingId or bindingNotNeeded = true (will cause validation error). When this feature is passed, the clientId parameter must be passed as well. If you pass both FORCE_CREATE_BINDING and VERIFY features, the order will be created for storing the credential ONLY (without payment). PARTIAL_AUTHORIZATION - partial authorization is available in the order. Read more here. FORCE_PAYMENT_WAY - force payment by the payment method specified in jsonParams in the paymentWay parameter. To process these payments merchant must have sufficient permissions in the payment gateway. This is currently used for force MOTO payments. To enable this, it is necessary to pass also paymentWay parameter with the value CARD_MOTO in jsonParams. |
|  |  |  |  |

Possible values of `tii`:

| tii value | Description | Transaction type | Transaction initiator | Card data for transaction | Note |
| --- | --- | --- | --- | --- | --- |
| IPI | Industry Practice Incremental (MIT) | Subsequent | Merchant | Not entered, loaded from the corresponding transaction record stored credential in the payment gateway. | A transaction to increase the payment amount within an already paid order. |
| IPS | Industry Practice Resubmission (MIT) | Subsequent | Merchant | Not entered, loaded from the corresponding transaction record stored credential in the payment gateway. | A transaction attempting to repay when the original payment failed. |
| IPD | Industry Practice Delayed Charges (MIT) | Subsequent | Merchant | Not entered, loaded from the corresponding transaction record stored credential in the payment gateway. | Delayed charges. |
| IPA | Industry Practice Reauthorization (MIT) | Subsequent | Merchant | Not entered, loaded from the corresponding transaction record stored credential in the payment gateway. | Retry authorization if completion or execution of original order exceeds Visa/MC authorization validity period. |
| IPN | Industry Practice No Show (MIT) | Subsequent | Merchant | Not entered, loaded from the corresponding transaction record stored credential in the payment gateway. | Performed by Merchants to issue fines to the Client for no-show when booking hotels and Car Sharing. |

The ``jsonParams`` block contains additional information fields for later storage. To pass N parameters, a request must contain N jsonParams tags, where the `name` attribute contains the parameter name and `value` attribute contains its value:

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `name` | String [1..255] | Name of an additional parameter. |
|  |  |  |  |
| Mandatory | `value` | String [1..1024] | Value of an additional parameter - up to 1024 characters. |

#### Response parameters

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Optional | `errorCode` | String [1..2] | Information parameter in case of an error, which may have different code values:  0 value - indicates success of the request processing; another number value (1-99) - indicates an error for more details of which errorMesage parameter must be inspected. It also can be missing if the result has not caused any error.  If the request, associated with an order payment, is processed successfully, it does not directly mean that the payment itself was successful. To determine whether the payment was successful or not, use getOrderStatusExtended.do request. |
|  |  |  |  |
| Optional | `errorMessage` | String [1..512] | Information parameter that is an error description in a case of error occurance. errorMessage value can vary, so it should not be hardcoded. Language of the description is set in language parameter of the request. |
|  |  |  |  |
| Optional | `mdOrder` | String [1..36] | The number of the order in the payment gateway with the payment completed by Industry Practice. |
|  |  |  |  |
| Optional | `actionCode` | String | Response code from the processing bank. Contains a numeric value. See the list of action codes here. |
|  |  |  |  |
| Optional | `approvalCode` | String [6] | IPS authorization code. This field has a fixed length (six symbols) and can contain digits and Latin letters. |
|  |  |  |  |
| Optional | `rrn` | Integer [1..12] | Reference Retrieval Number - transaction ID assigned by Acquiring Bank. |

More information about how to know whether the payment was successfull or not is available here.

#### Examples

#### Request example

```bash
curl --request POST \\
  --url https://dev.bpcbt.com/payment/industryPractice/paymentOrder.do \\
  --header 'content-type: application/x-www-form-urlencoded' \\
  --data '{
    "originalMdOrder":"f252eee4-5598-728a-a023-af6e09078dd0",
    "orderNumber":"testOrderNumber1",
    "tii":"IPI",
    "amount":"10",
    "username":"test_user",
    "password":"test_user_password"
}'
```

#### Response example - successfull industry practice payment

```json
{
  "errorCode": "0",
  "errorMessage": "Successful",
  "mdOrder": "d88680c4-54e9-7115-80ae-3cc709017350",
  "actionCode": "0",
  "approvalCode": "000000",
  "rrn": "111111111113"
}
```

#### Response example - Unsuccessful industry practice payment (processing returned a failure)

```json
{
  "errorCode": "5",
  "errorMessage": "Unsuccessful",
  "mdOrder": "d88680c4-54e9-7115-80ae-3cc709017350",
  "actionCode": "116",
  "approvalCode": "000000",
  "rrn": "111111111113"
}
```

Unsuccessful industry practice payment (for example, validation error)

```json
{
  "errorCode": "5",
  "errorMessage": "Operation is not allowed for original order"
}
```

