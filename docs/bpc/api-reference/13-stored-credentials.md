> Source: https://dev.bpcbt.com/en/integration/api/rest.html

# Stored credential

The below API requests allow managing stored credential transactions. Such transactions are used when a cardholder authorizes a merchant to store the payment credentials for further payments. Learn more about storing a credential here.

## Stored-credential payment

The request used to make a stored-credential payment is `https://dev.bpcbt.com/payment/rest/paymentOrderBinding.do`.

When sending the request, you should use the header: `Content-Type: application/x-www-form-urlencoded`

To use this method, you must meet the PCI SAQ D requirements.
If payment without CVC is used, you must meet the PCI SAQ A requirements.

You can use your own 3DS Server for 3D Secure authorization, if this feature is enabled for your by our support team. In this case, the request and response parameters of paymentOrderBinding.do will be slightly different. Read more about payment with your 3DS Server here.

### Request parameters

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `userName` | String [1..100] | Merchant's API account login. |
|  |  |  |  |
| Mandatory | `password` | String [1..30] | Merchant's API account password. |
|  |  |  |  |
| Mandatory | `mdOrder` | String [1..36] | Order number in the payment gateway. Unique within the payment gateway. |
|  |  |  |  |
| Mandatory | `bindingId` | String [1..255] | Identifier of an already existing stored credential. This is the card ID tokenized by the Gateway. Can be used only if the merchant has the permission to work with stored credentials. If this parameter is passed in this request, it means that:  This order can only be paid with a stored credential; The payer will be redirected to a payment page where only CVC entry is required. |
|  |  |  |  |
| Optional | `language` | String [2] | ISO 639-1 encoded language key. If the language is not specified, the default language specified in the store settings is used. Supported languages: en,el,ro,bg,pt,sw,hu,it,pl,de,fr,kh,cn,es,ka,da,et,fi,lt,lv,nl,sv. |
|  |  |  |  |
| Optional | `ip` | String [1..39] | Buyer's IP address. IPv6 is supported in all requests. (up to 39 characters).  If you use 3DS 2 authentication, we recommend passing a real IP address of the customer in this parameter. This increases conversion rates on the ACS side. |
|  |  |  |  |
| Optional | `cvc` | String [3] | The presence of this parameter is determined by payment type:  cvc is provided not for all tokenized payments; cvc is not provided for MIT payments; cvc is mandatory by default for all other payment types; but if permission Can process payments without confirmation of CVC is enabled, cvc becomes optional in that case.  Only digits are allowed. |
|  |  |  |  |
| Optional | `threeDSSDK` | Boolean | Possible values: true or false. Flag showing that payment comes from 3DS SDK. |
|  |  |  |  |
| Mandatory | `tii` | String | Transaction initiator indicator. A parameter indicating what type of operation will be carried out by the initiator (Customer or Merchant). Possible values: F, U. See the values description. |
|  |  |  |  |
| Optional | `email` | String [1..40] | Email to be displayed on the payment page. Customer's email must be passed if client notification is configured for the merchant. Example: client_mail@email.com. For payment by VISA with 3DS authorization, it is necessary to specify either phone or email of the cardholder. |
|  |  |  |  |
| Optional | `mcc` | Integer [4] | Merchant Category Code. Using this parameter requires a special permission. You can use only the values from the predefined list of allowed MCC values. Contact the support team for details. |
|  |  |  |  |
| Optional | `threeDSProtocolVersion` | String | 3DS protocol version. Possible values are "2.1.0", "2.2.0" for 3DS2. If threeDSProtocolVersion is not passed in the request, then the default value will be used for 3D Secure authorization (2.1.0 - for 3DS 2). |
|  |  |  |  |
| Optional | `externalScaExemptionIndicator` | String | The type of SCA (Strong Customer Authentication) excemption. If this parameter is specified, the transaction will be processed depending on your settings in the payment gateway: either forced SSL operation will be done, or the issuer bank will get the information about SCA excemption and decide to perform operation with or without 3DS authentication (for details, contact our support team). Allowed values:  LVP – Low Value Payments transaction. You can consider a transaction as low risk based on the transaction amount, the client's transactions per day or the client's total daily amount. TRA – Transaction Risk Analysis transaction, i.e., the transaction that has passed successful anti-fraud check.  To pass this parameter, you must have sufficient permissions in the payment gateway. |
|  |  |  |  |
| Conditional | `seToken` | String [1..8192] | Encrypted card data. Must be passed if used instead of the card data. The mandatory parameters for seToken string are timestamp, UUID, bindingId, MDORDER. Click here for more information about seToken generation. |

It is necessary to specify one of the following sets of parameters: pan+expirationYear+expirationMonth or seToken. |

|  |
|  |
|  |

Possible values of `tii` (read about the stored credential types supported by the Payment Gateway here):

| tii value | Description | Transaction type | Transaction initiator | Card data for transaction | Card data saved after transaction | Note |
| --- | --- | --- | --- | --- | --- | --- |
| F | Unscheduled CIT | Subsequent | Customer | Customer selects card instead of manual entry | No | An e-commerce transaction that uses a stored credential. |
| U | Unscheduled MIT | Subsequent | Merchant | No manual entry, Merchant passes the data | No | An e-commerce transaction that uses a stored credential. Used for one-phase payments only. |

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

### Response parameters

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `errorCode` | String [1..2] | Information parameter in case of an error, which may have different code values:  0 value - indicates success of the request processing; another number value (1-99) - indicates an error for more details of which errorMesage parameter must be inspected. It also can be missing if the result has not caused any error.  If the request, associated with an order payment, is processed successfully, it does not directly mean that the payment itself was successful. To determine whether the payment was successful or not, use getOrderStatusExtended.do request. |
|  |  |  |  |
| Optional | `errorMessage` | String [1..512] | Information parameter that is an error description in a case of error occurance. errorMessage value can vary, so it should not be hardcoded. Language of the description is set in language parameter of the request. |
|  |  |  |  |
| Optional | `redirect` | String [1..512] | This parameter is returned if the payment is successful and that payment did not include check for 3-D Secure involvement. Merchants can use it if they want to redirect the user to the payment gateway page. If they have their own response page then this value can be ignored. |
|  |  |  |  |
| Optional | `info` | String | If response is successful. Result of a payment attempt. Below are the possible values.  Your payment has been processed, redirecting... Operation declined. Check the entered data and that there are enough funds on the card and repeat the operation. Redirecting... Sorry, payment cannot be completed. Redirecting... Operation declined. Contact the merchant. Redirecting... Operation declined. Contact the bank that issued the card. Redirecting... Impossible operation. Cardholder authentication completed unsuccessfully. Redirecting... No connection with bank. Try again later. Redirecting... Input time expired. Redirecting... No response from bank received. Try again later. Redirecting... |
|  |  |  |  |
| Optional | `error` | String [1..512] | Error message (if response returned an error) in the language passed in the request. |
|  |  |  |  |
| Optional | `processingErrorType` | String | Type of processing error. Passed if error occurs on the processing end, and not in the Payment Gateway, while payments attemtps are not exceeded and there's been no redirect to finish page yet. |
|  |  |  |  |
| Optional | `displayErrorMessage` | String | Displayed error message. |
|  |  |  |  |
| Optional * | `errorTypeName` | String | Parameter needed by the front-end page to define the error type. Mandatory for unsuccessful payments. |
|  |  |  |  |
| Optional | `acsUrl` | String [1..512] | The URL address for redirecting to ACS. It is returned in a successful response in case of a 3D-Secure payment, when redirect to the ACS is needed. For details see Redirect to ACS. |
|  |  |  |  |
| Optional | `paReq` | String [1..255] | PAReq (Payment Authentication Request) - a message that should be sent to ACS together with redirect. It is returned in a successful response in case of a 3D-Secure payment, when redirect to the ACS is needed. This message contains the Base64-encoded data necessary for the cardholder authentication. For details see Redirect to ACS. |
|  |  |  |  |
| Optional | `termUrl` | String [1..512] | In a successful response in case of a 3D-Secure payment. The URL address to which ACS redirects the cardholder after authentication. For details see Redirect to ACS. |
|  |  |  |  |
| Optional | `bindingId` | String [1..255] | Identifier of a stored credential created earlier of used for the payment. Is present only if the merchant has a permission to use stored credentials. |
|  |  |  |  |

`payerData` element contains the following parameters.

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Optional | `paymentAccountReference` | String [1..29] | The unique account number of the client, which links all their payment means within the IPS (cards and tokens). |
|  |  |  |  |

More information about how to know whether the payment was successfull or not is available here.

### Examples

#### Request example

```bash
curl --request POST \
  --url https://dev.bpcbt.com/payment/rest/paymentOrderBinding.do \
  --header 'content-type: application/x-www-form-urlencoded' \
  --data userName=test_user \
  --data password=test_user_password \
  --data mdOrder=01491d0b-c848-7dd6-a20d-e96900a7d8c0 \
  --data bindingId=01491394-63a6-7d45-a88f-7bce00a7d8c0 \
  --data cvc=123 \
  --data tii=F \
  --data language=en
```

#### Example of a success response for an SSL-payment (no 3-D Secure)

```json
{
  "redirect": "https://dev.bpcbt.com/payment/merchants/temp/finish.html?orderId=01491d0b-c848-7dd6-a20d-e96900a7d8c0&lang=en",
  "info": "Your order is proceeded, redirecting...",
  "errorCode": 0
}
```

#### An example of a success response for a 3D-Secure payment

```json
{
  "info": "Your order is proceeded, redirecting...",
  "errorCode": 0,
  "acsUrl": "https://theacsserver.com/acs/auth/start.do",
  "paReq": "eJxVUu9vgjAQ/...4BaHYvAI=",
  "termUrl": "https://dev.bpcbt.com/payment/rest/finish3ds.do?lang=en"
}
```

#### Example of a response with an error

```json
{
  "error": "[clientId] is empty",
  "errorCode": 5,
  "is3DSVer2": false,
  "errorMessage": "[clientId] is empty"
}
```

## Get stored credentials

The request used to get the list of client's stored credentials is `https://dev.bpcbt.com/payment/rest/getBindings.do`.

When sending the request, you should use the header: `Content-type: application/x-www-form-urlencoded`

### Request parameters

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `clientId` | String [0..255] | Customer number (ID) in the merchant's system — up to 255 characters. Used to implement the functionality of stored-credential transactions. Can be returned in the response if the merchant is allowed to store credentials. Specifying this parameter in stored-credential transactions is mandatory. Otherwise, a payment will be unsuccessful. |
|  |  |  |  |
| Optional | `language` | String [2] | ISO 639-1 encoded language key. If the language is not specified, the default language specified in the store settings is used. Supported languages: en,el,ro,bg,pt,sw,hu,it,pl,de,fr,kh,cn,es,ka,da,et,fi,lt,lv,nl,sv. |
|  |  |  |  |
| Mandatory | `userName` | String [1..30] | Merchant 's API account login (mandatory, unless token is passed). If you pass your login and password to authenticate in the payment gateway, do not pass token parameter. |
|  |  |  |  |
| Mandatory | `password` | String [1..30] | Merchant's API account password (mandatory, unless token is passed). If you pass your login and password to authenticate in the payment gateway, do not pass token parameter. |
|  |  |  |  |
| Optional | `bindingId` | String [1..255] | Identifier of an already existing stored credential. This is the card ID tokenized by the Gateway. Can be used only if the merchant has the permission to work with stored credentials. If this parameter is passed in this request, it means that:  This order can only be paid with a stored credential; The payer will be redirected to a payment page where only CVC entry is required. |
|  |  |  |  |
| Optional | `bindingType` | String | The type of stored credential that is expected in reponse (if not specified, all types are returned). Possible values:  C – common stored credential. R – recurrent stored credential. I - installment stored credential. |
|  |  |  |  |
| Optional | `showExpired` | Boolean | true/false parameter defining whether to show stored credentials with expired cards. Default is false. |
|  |  |  |  |
| Optional | `merchantLogin` | String [1..255] | To get the list of client's stored credentials of another merchant, specify the merchant's API account login in this parameter. Can be used only if you have the permission to see the transactions of other merchants or if the specified merchant is your child merchant. Both you and the specified merchant should have the permission to work with stored credentials. |

### Response parameters

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `errorCode` | String [1..2] | Information parameter in case of an error, which may have different code values:  0 value - indicates success of the request processing; another number value (1-99) - indicates an error for more details of which errorMesage parameter must be inspected. It also can be missing if the result has not caused any error.  If the request, associated with an order payment, is processed successfully, it does not directly mean that the payment itself was successful. To determine whether the payment was successful or not, use getOrderStatusExtended.do request. |
|  |  |  |  |
| Optional | `errorMessage` | String [1..512] | Information parameter that is an error description in a case of error occurance. errorMessage value can vary, so it should not be hardcoded. Language of the description is set in language parameter of the request. |
|  |  |  |  |
| Optional | `bindings` | Object | Element with blocks that contain parameters of the stored credentials. See the description below. |

`bindings` element contains blocks with the following parameters.

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Optional | `maskedPan` | String [1..19] | Masked number of the card used for the payment. It contains real first 6 and last 4 digits of the card number in the format XXXXXX**XXXX. |
|  |  |  |  |
| Optional | `paymentWay` | String | Payment method (a payment with entering card data, a stored-credential transaction, etc.). Find more possible values of the parameter. |
|  |  |  |  |
| Mandatory | `bindingId` | String [1..255] | Identifier of an already existing stored credential. This is the card ID tokenized by the Gateway. Can be used only if the merchant has the permission to work with stored credentials. If this parameter is passed in this request, it means that:  This order can only be paid with a stored credential; The payer will be redirected to a payment page where only CVC entry is required. |
|  |  |  |  |
| Mandatory | `expiryDate` | String [6] | Card expiration in the following format: YYYYMM. |
|  |  |  |  |
| Optional | `bindingCategory` | String | The purpose of the of stored credential that is expected in reponse. Possible values: COMMON, INSTALLMENT, RECURRENT. |
|  |  |  |  |
| Optional | `clientId` | String [0..255] | Customer number (ID) in the merchant's system — up to 255 characters. Used to implement the functionality of stored-credential transactions. Can be returned in the response if the merchant is allowed to store credentials. Specifying this parameter in stored-credential transactions is mandatory. Otherwise, a payment will be unsuccessful. |
|  |  |  |  |
| Optional | `displayLabel` | String [1..16] | The last 4 digits of the original PAN before tokenization . |
|  |  |  |  |
| Optional | `paymentSystem` | String | Payment system name. The following variants are possible:  VISA MASTERCARD AMEX JCB CUP |

### Examples

#### Request example

```bash
curl --request POST \
  --url https://dev.bpcbt.com/payment/rest/getBindings.do \
  --header 'Content-Type: application/x-www-form-urlencoded' \
  --data userName=test_user \
  --data password=test_user_password \
  --data clientId=dos-clientos \
  --data bindingType=C
```

#### Example of a success response

```json
{
    "errorCode": "0",
    "errorMessage": "Success",
    "bindings": [
        {
            "bindingId": "44779116-41a5-7798-b072-c0a30760e2b0",
            "maskedPan": "411111**1111",
            "expiryDate": "203412",
            "paymentWay": "TOKEN_PAY",
            "paymentSystem": "CARD",
            "displayLabel": "XXXXXXXXXXXX1111",
            "bindingCategory": "COMMON"
        }
    ]
}
```

## Get stored credentials by card number

The request used to get the list of all stored credentials of a bank card is `https://dev.bpcbt.com/payment/rest/getBindingsByCardOrId.do`.

When sending the request, you should use the header: `Content-type: application/x-www-form-urlencoded`

### Request parameters

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `userName` | String [1..100] | Merchant's API account login. |
|  |  |  |  |
| Mandatory | `password` | String [1..30] | Merchant's API account password. |
|  |  |  |  |
| Conditional | `pan` | String [15..19] | Payment card number (mandatory, unless bindinId is passed). pan overrides bindingId. |
|  |  |  |  |
| Conditional | `bindingId` | String [1..255] | Identifier of an already existing stored credential. This is the card ID tokenized by the Gateway. Can be used only if the merchant has the permission to work with stored credentials. If this parameter is passed in this request, it means that:  This order can only be paid with a stored credential; The payer will be redirected to a payment page where only CVC entry is required. |
|  |  |  |  |
| Optional | `showExpired` | Boolean | true/false parameter defining whether to show stored credentials with expired cards. Default is false. |

### Response parameters

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `errorCode` | String [1..2] | Information parameter in case of an error, which may have different code values:  0 value - indicates success of the request processing; another number value (1-99) - indicates an error for more details of which errorMesage parameter must be inspected. It also can be missing if the result has not caused any error.  If the request, associated with an order payment, is processed successfully, it does not directly mean that the payment itself was successful. To determine whether the payment was successful or not, use getOrderStatusExtended.do request. |
|  |  |  |  |
| Optional | `errorMessage` | String [1..512] | Information parameter that is an error description in a case of error occurance. errorMessage value can vary, so it should not be hardcoded. Language of the description is set in language parameter of the request. |
|  |  |  |  |
| Optional | `bindings` | Object | Element with blocks that contain parameters of the stored credential: bindingId, maskedPan, expiryDate, clientId |
|  |  |  |  |
| Optional | `bindingId` | String [1..255] | Identifier of an already existing stored credential. This is the card ID tokenized by the Gateway. Can be used only if the merchant has the permission to work with stored credentials. If this parameter is passed in this request, it means that:  This order can only be paid with a stored credential; The payer will be redirected to a payment page where only CVC entry is required. |
|  |  |  |  |
| Optional | `maskedPan` | String [1..19] | Masked number of the card used for the payment. It contains real first 6 and last 4 digits of the card number in the format XXXXXX**XXXX. |
|  |  |  |  |
| Optional | `expiryDate` | String [6] | Card expiration in the following format: YYYYMM. |
|  |  |  |  |
| Optional | `clientId` | String [0..255] | Customer number (ID) in the merchant's system — up to 255 characters. Used to implement the functionality of stored-credential transactions. Can be returned in the response if the merchant is allowed to store credentials. Specifying this parameter in stored-credential transactions is mandatory. Otherwise, a payment will be unsuccessful. |

### Examples

#### Request example

```bash
curl --request POST \
  --url https://dev.bpcbt.com/payment/rest/getBindingsByCardOrId.do \
  --header 'Content-Type: application/x-www-form-urlencoded' \
  --data userName=test_user \
  --data password=test_user_password \
  --data pan=4000001111111118
```

#### Example of a success response

```json
{
"errorCode":"0",
"errorMessage":"Success",
"bindings": [
    {
        "bindingId":"69d6a793-afb5-79be-8ce7-63ff00a8656a",
        "maskedPan":"400000**1118",
        "expiryDate":"203012",
        "clientId":"12"
        }
    {
        "bindingId":"6a8c0738-cc88-4200-acf6-afc264d66cb0",
        "maskedPan":"400000**1118",
        "expiryDate":"203012",
        "clientId":"13"
        }
    ]
 }
```

## Deactivate a stored credential

The request used to deactivate a stored credential is `https://dev.bpcbt.com/payment/rest/unBindCard.do`.

When sending the request, you should use the header: `Content-Type: application/x-www-form-urlencoded`

### Request parameters

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `userName` | String [1..100] | Merchant's API account login. |
|  |  |  |  |
| Mandatory | `password` | String [1..30] | Merchant's API account password. |
| Mandatory | `bindingId` | String [1..255] | Identifier of an already existing stored credential. This is the card ID tokenized by the Gateway. Can be used only if the merchant has the permission to work with stored credentials. If this parameter is passed in this request, it means that:  This order can only be paid with a stored credential; The payer will be redirected to a payment page where only CVC entry is required. |

### Response parameters

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Optional | `errorCode` | String [1..2] | Information parameter in case of an error, which may have different code values:  0 value - indicates success of the request processing; another number value (1-99) - indicates an error for more details of which errorMesage parameter must be inspected. It also can be missing if the result has not caused any error.  If the request, associated with an order payment, is processed successfully, it does not directly mean that the payment itself was successful. To determine whether the payment was successful or not, use getOrderStatusExtended.do request. |
|  |  |  |  |
| Optional | `errorMessage` | String [1..512] | Information parameter that is an error description in a case of error occurance. errorMessage value can vary, so it should not be hardcoded. Language of the description is set in language parameter of the request. |

### Examples

#### Request example

```bash
curl --request POST \
  --url https://dev.bpcbt.com/payment/rest/unBindCard.do \
  --header 'Content-Type: application/x-www-form-urlencoded' \
  --data userName=test_user \
  --data password=test_user_password \
  --data bindingId=fd3afc57-c6d0-4e08-aaef-1b7cfeb093dc
```

#### Response example (error)

```json
{
"errorCode":"2",
"errorMessage":"Binging isn't active",
}
```

## Enable a stored credential

The request used to activate an existing stored credential that has been deactivated is `https://dev.bpcbt.com/payment/rest/bindCard.do`.

When sending the request, you should use the header: `Content-Type: application/x-www-form-urlencoded`

### Request parameters

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `userName` | String [1..100] | Merchant's API account login. |
|  |  |  |  |
| Mandatory | `password` | String [1..30] | Merchant's API account password. |
|  |  |  |  |
| Mandatory | `bindingId` | String [1..255] | Identifier of an already existing stored credential. This is the card ID tokenized by the Gateway. Can be used only if the merchant has the permission to work with stored credentials. If this parameter is passed in this request, it means that:  This order can only be paid with a stored credential; The payer will be redirected to a payment page where only CVC entry is required. |
|  |  |  |  |

### Response parameters

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Optional | `errorCode` | String [1..2] | Information parameter in case of an error, which may have different code values:  0 value - indicates success of the request processing; another number value (1-99) - indicates an error for more details of which errorMesage parameter must be inspected. It also can be missing if the result has not caused any error.  If the request, associated with an order payment, is processed successfully, it does not directly mean that the payment itself was successful. To determine whether the payment was successful or not, use getOrderStatusExtended.do request. |
|  |  |  |  |
| Optional | `errorMessage` | String [1..512] | Information parameter that is an error description in a case of error occurance. errorMessage value can vary, so it should not be hardcoded. Language of the description is set in language parameter of the request. |
|  |  |  |  |

### Examples

#### Request example

```bash
curl --request POST \
  --url https://dev.bpcbt.com/payment/rest/bindCard.do \
  --header 'Content-Type: application/x-www-form-urlencoded' \
  --data userName=test_user \
  --data password=test_user_password \
  --data bindingId=fd3afc57-c6d0-4e08-aaef-1b7cfeb093dc
```

#### Response example (error)

```json
{
  "errorCode":"2",
  "errorMessage":"Binging is active",
}
```

## Extend a stored credential expiration date

The request used to extend the expiration date of a stored credential is `https://dev.bpcbt.com/payment/rest/extendBinding.do`.

When sending the request, you should use the header: `Content-type: application/x-www-form-urlencoded`

### Request parameters

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `userName` | String [1..100] | Merchant's API account login. |
|  |  |  |  |
| Mandatory | `password` | String [1..30] | Merchant's API account password. |
|  |  |  |  |
| Mandatory | `bindingId` | String [1..255] | Identifier of an already existing stored credential. This is the card ID tokenized by the Gateway. Can be used only if the merchant has the permission to work with stored credentials. If this parameter is passed in this request, it means that:  This order can only be paid with a stored credential; The payer will be redirected to a payment page where only CVC entry is required. |
|  |  |  |  |
| Mandatory | `newExpiry` | Integer [6] | New expiration date (year and month) in the following format: YYYYMM. |
|  |  |  |  |
| Mandatory | `language` | String [2] | ISO 639-1 encoded language key. If the language is not specified, the default language specified in the store settings is used. Supported languages: en,el,ro,bg,pt,sw,hu,it,pl,de,fr,kh,cn,es,ka,da,et,fi,lt,lv,nl,sv. |

### Response parameters

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Optional | `errorCode` | String [1..2] | Information parameter in case of an error, which may have different code values:  0 value - indicates success of the request processing; another number value (1-99) - indicates an error for more details of which errorMesage parameter must be inspected. It also can be missing if the result has not caused any error.  If the request, associated with an order payment, is processed successfully, it does not directly mean that the payment itself was successful. To determine whether the payment was successful or not, use getOrderStatusExtended.do request. |
|  |  |  |  |
| Optional | `errorMessage` | String [1..512] | Information parameter that is an error description in a case of error occurance. errorMessage value can vary, so it should not be hardcoded. Language of the description is set in language parameter of the request. |

### Examples

#### Request example

```bash
curl --request POST \
  --url https://dev.bpcbt.com/payment/rest/extendBinding.do \
  --header 'Content-Type: application/x-www-form-urlencoded' \
  --data userName=test_user \
  --data password=test_user_password \
  --data bindingId=fd3afc57-c6d0-4e08-aaef-1b7cfeb093dc
  --data newExpiry=202212
  --data language=en
```

#### Response example

```json
{
"errorCode":"0",
"errorMessage":"Success",
}
```

## Recurrent payment

The request used to make recurrent payments is `https://dev.bpcbt.com/payment/recurrentPayment.do`. It is used to register and pay for the order.

When sending the request, you should use the header: `Content-Type: application/json`

### Request parameters

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `userName` | String [1..100] | Merchant's API account login. |
|  |  |  |  |
| Mandatory | `password` | String [1..30] | Merchant's API account password. |
|  |  |  |  |
| Mandatory | `orderNumber` | String [1..36] | Order number (ID) in the merchant's system, must be unique for each order. |
|  |  |  |  |
| Optional | `language` | String [2] | ISO 639-1 encoded language key. If the language is not specified, the default language specified in the store settings is used. Supported languages: en,el,ro,bg,pt,sw,hu,it,pl,de,fr,kh,cn,es,ka,da,et,fi,lt,lv,nl,sv. |
|  |  |  |  |
| Optional | `feeInput` | Integer [0..8] | Fee amount in minimum currency units. Must be enabled by respective Merchant-level permission in the Gateway. |
|  |  |  |  |
| Mandatory | `bindingId` | String [1..255] | Identifier of an already existing stored credential. This is the card ID tokenized by the Gateway. Can be used only if the merchant has the permission to work with stored credentials. If this parameter is passed in this request, it means that:  This order can only be paid with a stored credential; The payer will be redirected to a payment page where only CVC entry is required. |
|  |  |  |  |
| Mandatory | `amount` | Integer [0..12] | Payment amount in minor currency units (e.g. in cents). |
|  |  |  |  |
| Optional | `currency` | String [3] | ISO 4217 encoded currency key. If not specified, the default value is used. Only digits are allowed. |
|  |  |  |  |
| Optional | `description` | String [1..598] | Order description in any format. To enable sending this field to the processing system, contact the technical support service. It is not allowed to fill this parameter with personal data or payment data (card numbers, etc.). This requirement is due to the fact that the order description is not masked in Merchant Portal and log files. |
|  |  |  |  |
| Optional | `preAuth` | Boolean | Parameter that defines the necessity of a pre-authorization (putting the amount on hold on the customer's account until its debiting). The following values are available:  true - two-phase payments enabled; false - one-phase payments enabled (money are charged right away). If the parameter is missing, one-phase payment is made. |
|  |  |  |  |
| Optional | `autocompletionDate` | String [19] | The date and time when the two-phase payment must be completed automatically in the following format: 2025-12-29T13:02:51. The used timezone is UTC+0. To enable sending this field to the processing system, contact your technical support service. |
|  |  |  |  |
| Optional | `autoReverseDate` | String [19] | The date and time when the two-phase payment must be reversed automatically in the following format: 2025-06-23T13:02:51. The used timezone is UTC+0. To enable sending this field to the processing system, contact your technical support service. |
|  |  |  |  |
| Optional | `features` | String | Features of the order. To specify multiple features, use this parameter several times in one request. As an example, below are the possible values.  VERIFY - If you specify this value in the order registration request, cardholder will be verified however they will not be charged any amount, so in this case amount parameter can be 0. Verification allows to make sure that a payment card is used by its legitimate owner, and further you can charge them without authentication (CVC, 3D-Secure). Even if some amount is passed in the request, the customer will not be charged if VERIFY feature is used. This value can be also used for storing the credential – in this case, the clientId parameter must be passed as well. Read more here. FORCE_TDS - Force 3-D Secure payment. If a payment card does not support 3-D Secure, the transaction will fail. FORCE_SSL - Force SSL payment (without 3-D Secure). FORCE_FULL_TDS - After 3-D Secure authentication, PaRes status must be Y, which guarantees successful user authentication. Otherwise, the transaction will fail. FORCE_CREATE_BINDING - passing this feature in the order registration request forcefully stores the credential. This functionality must be enabled by Merchant level permission in the Gateway. This value cannot be passed in a request with an existing bindingId or bindingNotNeeded = true (will cause validation error). When this feature is passed, the clientId parameter must be passed as well. If you pass both FORCE_CREATE_BINDING and VERIFY features, the order will be created for storing the credential ONLY (without payment). PARTIAL_AUTHORIZATION - partial authorization is available in the order. Read more here. FORCE_PAYMENT_WAY - force payment by the payment method specified in jsonParams in the paymentWay parameter. To process these payments merchant must have sufficient permissions in the payment gateway. This is currently used for force MOTO payments. To enable this, it is necessary to pass also paymentWay parameter with the value CARD_MOTO in jsonParams. |
|  |  |  |  |
| Optional | `additionalParameters` | Object | Additional parameters of the order that are stored in the merchant personal area for the subsequent viewing. Each new pair of a parameter name and its value must be separated by a comma. Below is a usage example. { "firstParamName": "firstParamValue", "secondParamName": "secondParamValue"} |
|  |  |  |  |
| Optional | `billingPayerData` | Object | A block with the client's registration data (address, postal code) necessary for passing the address verification within the AVS/AVV services. Mandatory if the feature is enabled for the merchant on Payment Gateway side. See nested parameters. |
| Optional | `shippingPayerData` | Object | Object containing customer delivery data. It is used for further 3DS authentication of the client. See nested parameters. |
| Optional | `preOrderPayerData` | Object | Object containing pre-order data. It is used for further 3DS authentication of the client. See nested parameters. |
| Optional | `orderPayerData` | Object | Object containing data about the order payer. It is used for further 3DS authentication of the client. See nested parameters. |
| Optional | `billingAndShippingAddressMatchIndicator` | String [1] | Indicator for matching the cardholder's billing address and shipping address. This parameter is used for further 3DS authentication of the customer. Possible values:  Y - the cardholder's billing address and shipping address match; N - cardholder billing address and shipping address do not match. |

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

### Response parameters

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `success` | Boolean | Main parameter which indicates directly that the request was successful. The following values are available:  true - request processed successfully; false - request failed.  Note that the value true here simply means that the request was proccessed, not that the order was paid. Read here to find out how to get payment status. |
|  |  |  |  |
| Conditional | `data` | N/A | This parameter is returned only if the payment is processed successfully. See the description below. |
| Conditional | `error` | N/A | This parameter is returned only if the payment failed. See the description below. |

`payerData` element contains the following parameters.

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Optional | `paymentAccountReference` | String [1..29] | The unique account number of the client, which links all their payment means within the IPS (cards and tokens). |
|  |  |  |  |

`data` block contains the following elements.

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `orderId` | String [1..36] | Order number in the payment gateway. Unique within the payment gateway. |

`error` block contains the following elements.

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `code` | String [1..3] | Code as an information parameter stating an error occurred. |
|  |  |  |  |
| Mandatory | `description` | String [1..598] | A detailed technical explanation of the error - the contents of this parameter should not to be displayed to the customer. |
|  |  |  |  |
| Mandatory | `message` | String [1..512] | Information parameter that is an error description to be displayed to the user. The parameter may vary, so it should not be hardcoded. |
|  |  |  |  |

More information about how to know whether the payment was successfull or not is available here.

### Examples

#### Request example

```bash
curl --request POST \
--url https://dev.bpcbt.com/payment/recurrentPayment.do \
--header 'Content-Type: application/json' \
--data-raw '{
  "userName" : "test_user",
  "password" : "test_user_password",
  "orderNumber" : "UAF-203974-DE-12",
  "language" : "EN",
  "bindingId": "bindingId",
  "amount" : 1200,
  "currency" : "978",
  "description" : "Test description",
  "additionalParameters" : {
    "firstParamName" : "firstParamValue",
    "secondParamName" : "secondParamValue"
    "email" : "email@email.com"
  }
}'
```

#### Response examples - Success

```json
{
    "success": true,
    "data": {
        "orderId": "f7beebe4-7c9a-43cf-8e26-67ab741f9b9e"
    },
    "orderStatus": {
        "errorCode": "0",
        "orderNumber": "UAF-203974-DE-12",
        "orderStatus": 2,
        "actionCode": 0,
        "actionCodeDescription": "",
        "amount": 12300,
        "currency": "978",
        "date": 1491333938243,
        "orderDescription": "Test description",
        "merchantOrderParams": [
            {
                "name": "firstParamName",
                "value": "firstParamValue"
            },
            {
                "name": "secondParamName",
                "value": "secondParamValue"
            }
        ],
        "attributes": [],
        "cardAuthInfo": {
            "expiration": "203012",
            "cardholderName": "TEST CARDHOLDER",
            "approvalCode": "12345678",
            "paymentSystem": "VISA",
            "pan": "6777770000**0006"
        },
        "authDateTime": 1491333939454,
        "terminalId": "11111",
        "authRefNum": "111111111111",
        "paymentAmountInfo": {
            "paymentState": "DEPOSITED",
            "approvedAmount": 12300,
            "depositedAmount": 12300,
            "refundedAmount": 0
        },
        "bankInfo": {
            "bankCountryName": "<unknown>"
        },
        "chargeback": false,
        "operations": [
            {
                "amount": 12300,
                "cardHolder": "TEST CARDHOLDER",
                "authCode": "123456"
            }
        ]
    }
}
```

#### Error

```json
{
  "error": {
    "code": "10",
    "description": "Order with this number is already registered in the system.",
    "message": "Order with this number is already registered in the system."
  },
  "success": false
}
```

## Installment payment

The request used to make an installment payments is `https://dev.bpcbt.com/payment/installmentPayment.do`

When sending the request, you should use the header: `Content-Type: application/json`

### Request parameters

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Conditional | `userName` | String [1..30] | Merchant 's API account login (mandatory, unless token is passed). If you pass your login and password to authenticate in the payment gateway, do not pass token parameter. |
|  |  |  |  |
| Conditional | `password` | String [1..30] | Merchant's API account password (mandatory, unless token is passed). If you pass your login and password to authenticate in the payment gateway, do not pass token parameter. |
|  |  |  |  |
| Mandatory | `orderNumber` | String [1..36] | Order number (ID) in the merchant's system, must be unique for each order. |
|  |  |  |  |
| Optional | `language` | String [2] | ISO 639-1 encoded language key. If the language is not specified, the default language specified in the store settings is used. Supported languages: en,el,ro,bg,pt,sw,hu,it,pl,de,fr,kh,cn,es,ka,da,et,fi,lt,lv,nl,sv. |
|  |  |  |  |
| Mandatory | `bindingId` | String [1..255] | Identifier of an already existing stored credential. This is the card ID tokenized by the Gateway. Can be used only if the merchant has the permission to work with stored credentials. If this parameter is passed in this request, it means that:  This order can only be paid with a stored credential; The payer will be redirected to a payment page where only CVC entry is required. |
|  |  |  |  |
| Mandatory | `amount` | Integer [0..12] | Payment amount in minor currency units (e.g. in cents). |
|  |  |  |  |
| Optional | `currency` | String [3] | ISO 4217 encoded currency key. If not specified, the default value is used. Only digits are allowed. |
|  |  |  |  |
| Optional | `description` | String [1..598] | Order description in any format. To enable sending this field to the processing system, contact the technical support service. It is not allowed to fill this parameter with personal data or payment data (card numbers, etc.). This requirement is due to the fact that the order description is not masked in Merchant Portal and log files. |
|  |  |  |  |
| Optional | `additionalParameters` | Object | Additional parameters of the order that are stored in the merchant personal area for the subsequent viewing. Each new pair of a parameter name and its value must be separated by a comma. Below is a usage example. { "firstParamName": "firstParamValue", "secondParamName": "secondParamValue"} |
|  |  |  |  |
| Mandatory | `preAuth` | Boolean | Parameter that defines the necessity of a pre-authorization (putting the amount on hold on the customer's account until its debiting). The following values are available:  true - two-phase payments enabled; false - one-phase payments enabled (money are charged right away). If the parameter is missing, one-phase payment is made. |
|  |  |  |  |
| Optional | `autocompletionDate` | String [19] | The date and time when the two-phase payment must be completed automatically in the following format: 2025-12-29T13:02:51. The used timezone is UTC+0. To enable sending this field to the processing system, contact your technical support service. |
|  |  |  |  |
| Optional | `autoReverseDate` | String [19] | The date and time when the two-phase payment must be reversed automatically in the following format: 2025-06-23T13:02:51. The used timezone is UTC+0. To enable sending this field to the processing system, contact your technical support service. |
|  |  |  |  |
| Optional | `features` | String | Features of the order. To specify multiple features, use this parameter several times in one request. As an example, below are the possible values.  VERIFY - If you specify this value in the order registration request, cardholder will be verified however they will not be charged any amount, so in this case amount parameter can be 0. Verification allows to make sure that a payment card is used by its legitimate owner, and further you can charge them without authentication (CVC, 3D-Secure). Even if some amount is passed in the request, the customer will not be charged if VERIFY feature is used. This value can be also used for storing the credential – in this case, the clientId parameter must be passed as well. Read more here. FORCE_TDS - Force 3-D Secure payment. If a payment card does not support 3-D Secure, the transaction will fail. FORCE_SSL - Force SSL payment (without 3-D Secure). FORCE_FULL_TDS - After 3-D Secure authentication, PaRes status must be Y, which guarantees successful user authentication. Otherwise, the transaction will fail. FORCE_CREATE_BINDING - passing this feature in the order registration request forcefully stores the credential. This functionality must be enabled by Merchant level permission in the Gateway. This value cannot be passed in a request with an existing bindingId or bindingNotNeeded = true (will cause validation error). When this feature is passed, the clientId parameter must be passed as well. If you pass both FORCE_CREATE_BINDING and VERIFY features, the order will be created for storing the credential ONLY (without payment). PARTIAL_AUTHORIZATION - partial authorization is available in the order. Read more here. FORCE_PAYMENT_WAY - force payment by the payment method specified in jsonParams in the paymentWay parameter. To process these payments merchant must have sufficient permissions in the payment gateway. This is currently used for force MOTO payments. To enable this, it is necessary to pass also paymentWay parameter with the value CARD_MOTO in jsonParams. |
|  |  |  |  |
| Conditional | `token` | String [1..256] | Value that is used for merchant authentication when requests are sent to the payment gateway (mandatory, unless userName and password are passed). If you pass this parameter, do not pass userName and password. |
|  |  |  |  |
| Optional | `billingPayerData` | Object | A block with the client's registration data (address, postal code) necessary for passing the address verification within the AVS/AVV services. Mandatory if the feature is enabled for the merchant on Payment Gateway side. See nested parameters. |
| Optional | `shippingPayerData` | Object | Object containing customer delivery data. It is used for further 3DS authentication of the client. See nested parameters. |
| Optional | `preOrderPayerData` | Object | Object containing pre-order data. It is used for further 3DS authentication of the client. See nested parameters. |
| Optional | `orderPayerData` | Object | Object containing data about the order payer. It is used for further 3DS authentication of the client. See nested parameters. |
| Optional | `billingAndShippingAddressMatchIndicator` | String [1] | Indicator for matching the cardholder's billing address and shipping address. This parameter is used for further 3DS authentication of the customer. Possible values:  Y - the cardholder's billing address and shipping address match; N - cardholder billing address and shipping address do not match. |

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

### Response parameters

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Optional | `orderId` | String [1..36] | Order number in the payment gateway. Unique within the payment gateway. |
|  |  |  |  |
| Mandatory | `errorCode` | String [1..2] | Information parameter in case of an error, which may have different code values:  0 value - indicates success of the request processing; another number value (1-99) - indicates an error for more details of which errorMesage parameter must be inspected. It also can be missing if the result has not caused any error.  If the request, associated with an order payment, is processed successfully, it does not directly mean that the payment itself was successful. To determine whether the payment was successful or not, use getOrderStatusExtended.do request. |
|  |  |  |  |
| Mandatory | `errorMessage` | String [1..512] | Information parameter that is an error description in a case of error occurance. errorMessage value can vary, so it should not be hardcoded. Language of the description is set in language parameter of the request. |
|  |  |  |  |
| Conditional | `orderStatus` | Object | Contains order status parameters and is returned only if the payment gateway has recognized all request parameters as correct. See the description below. |

`orderStatus` block contains the following elements.

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Optional | `errorCode` | String [1..2] | Information parameter in case of an error, which may have different code values:  0 value - indicates success of the request processing; another number value (1-99) - indicates an error for more details of which errorMesage parameter must be inspected. It also can be missing if the result has not caused any error.  If the request, associated with an order payment, is processed successfully, it does not directly mean that the payment itself was successful. To determine whether the payment was successful or not, use getOrderStatusExtended.do request. |
|  |  |  |  |
| Optional | `errorMessage` | String [1..512] | Information parameter that is an error description in a case of error occurance. errorMessage value can vary, so it should not be hardcoded. Language of the description is set in language parameter of the request. |
|  |  |  |  |
| Optional | `orderNumber` | String [1..36] | Order number (ID) in the merchant's system, must be unique for each order. |
|  |  |  |  |
| Optional | `orderStatus` | Integer | The value of this parameter specifies the status of the order in the payment gateway. It is missing if the order has not been found. Below is the list of available values:  0 - order was registered but not paid; 1 - order was authorized only and wasn't captured yet (for two-phase payments); 2 - order was authorized and captured; 3 - authorization canceled; 4 - transaction was refunded; 5 - access control server of the issuing bank initiated authorization procedure; 6 - authorization declined; 7 - pending order payment; 8 - intermediate completion for multiple partial completion. |
|  |  |  |  |
| Optional | `actionCode` | String | Response code from the processing bank. Contains a numeric value. See the list of action codes here. |
|  |  |  |  |
| Optional | `actionCodeDescription` | String [1..512] | actionCode description returned from the processing bank. |
|  |  |  |  |
| Optional | `amount` | Integer [0..12] | Payment amount in minor currency units (e.g. in cents). |
|  |  |  |  |
| Optional | `currency` | String [3] | ISO 4217 encoded currency key. If not specified, the default value is used. Only digits are allowed. |
|  |  |  |  |
| Optional | `date` | Integer | Order registration date as the amount of milliseconds since 00:00 January 1, 1970 GMT (UNIX time). Example: 1740392720718 (Corresponds to February 24, 2025, 10:25:20 (UTC)). |
|  |  |  |  |
| Optional | `ip` | String [1..39] | Buyer's IP address. IPv6 is supported in all requests. (up to 39 characters).  If you use 3DS 2 authentication, we recommend passing a real IP address of the customer in this parameter. This increases conversion rates on the ACS side. |
|  |  |  |  |
| Optional | `chargeback` | Boolean | Whether the funds was forcibly returned to the buyer by the bank. The possible values are:true, false. |
|  |  |  |  |
| Optional | `merchantOrderParams` | N/A | Section with attributes in which the merchant's additional parameters are transmitted. See the description below. |
| Optional | `attributes` | Object | Attributes of the order in the payment system (order number). See the description below. |
| Optional | `cardAuthInfo` | Object | Information about the buyer's payment card. See the description below. |
| Optional | `authDateTime` | Integer | Authorization date and time, shown as the amount of milliseconds since 00:00 January 1, 1970 GMT (UNIX time). Example: 1740392720718 (Corresponds to February 24, 2025, 10:25:20 (UTC)). |
|  |  |  |  |
| Optional | `terminalId` | String [1..10] | Terminal identifier in the system that processes the payment. |
|  |  |  |  |
| Optional | `authRefNum` | String [1..24] | Reference number of the payment authorization that has been assigned to it upon its registration. |
|  |  |  |  |
| Optional | `paymentAmountInfo` | Object | A parameter containing embedded parameters with information about confirmation, debiting and refund amounts. See the description below. |
| Optional | `bankInfo` | Object | Contains the embedded bankCountryName parameter. See the description below. |
| Optional | `bindingInfo` | Object | Object containing information on the binding with which the payment is performed. See the description below. |
| Optional | `operations` | Object | Object containing the operations information. See the description below. |

`payerData` element contains the following parameters.

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Optional | `paymentAccountReference` | String [1..29] | The unique account number of the client, which links all their payment means within the IPS (cards and tokens). |
|  |  |  |  |

`merchantOrderParams` block contains the following elements.

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `name` | String [1..255] | Name of the merchant's additional parameter. |
|  |  |  |  |
| Mandatory | `value` | String [1..1024] | The value of the merchant's additional parameter - up to 1024 characters. |

`attributes` block contains the following elements.

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `name` | String [1..255] | Name of an additional parameter. |
|  |  |  |  |
| Mandatory | `value` | String [1..1024] | Value of an additional parameter - up to 1024 characters. |

`cardAuthInfo` block contains the following elements.

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `expiration` | Integer [6] | Card expiration date in the following format: YYYYMM. |
|  |  |  |  |
| Mandatory | `cardholderName` | String [1..26] | Cardholder's name in Latin characters. Allowed symbols: Latin characters, period, space. |
|  |  |  |  |
| Mandatory | `approvalCode` | String [6] | IPS authorization code. This field has a fixed length (six symbols) and can contain digits and Latin letters. |
|  |  |  |  |
| Mandatory | `pan` | String [1..19] | Masked DPAN: a number that is linked to the customer's mobile device and functions as a payment card number in the Apple Pay system. |
|  |  |  |  |
| Mandatory | `maskedPan` | String [1..19] | Masked number of the card used for the payment. It contains real first 6 and last 4 digits of the card number in the format XXXXXX**XXXX. |
|  |  |  |  |
| Mandatory | `paymentSystem` | String | Payment system name. The following variants are possible:  VISA MASTERCARD AMEX JCB CUP |

`paymentAmountInfo` block contains the following elements.

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `paymentState` | String | Order status, this parameter can have the following values:  CREATED - order created (but not paid); APPROVED - order approved (funds are on hold on buyer's account); DEPOSITED - order deposited (buyer is charged); DECLINED - order declined; REVERSED - order canceled; REFUNDED - refund. |
|  |  |  |  |
| Mandatory | `approvedAmount` | Integer [0..12] | Amount in minimum currency units (e.g. cents) that was put on hold on buyer's account. Used in two-phase payments only. In case of partial authorization, this amount may be less than the order registration amount. |
|  |  |  |  |
| Mandatory | `depositedAmount` | Integer [1..12] | Charged amount in minimum currency units (e.g., in cents). In case of partial authorization, this amount may be less than the order registration amount. |
|  |  |  |  |
| Mandatory | `refundedAmount` | Integer [1..12] | Refunded amount in minimum currency units. |
|  |  |  |  |
| Mandatory | `totalAmount` | Integer [1..20] | Order amount plus fee, if any. |

`bankInfo` block contains the following elements.

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `bankCountryName` | String [1..160] | Country of the issuing bank. |

`bindingInfo` element contains the following parameters.

| Name | Type | Mandatory | Description |
| --- | --- | --- | --- |
| Optional | `clientId` | String [0..255] | Customer number (ID) in the merchant's system — up to 255 characters. Used to implement the functionality of stored-credential transactions. Can be returned in the response if the merchant is allowed to store credentials. Specifying this parameter in stored-credential transactions is mandatory. Otherwise, a payment will be unsuccessful. |
|  |  |  |  |
| Optional | `bindingId` | String [1..255] | Identifier of an already existing stored credential. This is the card ID tokenized by the Gateway. Can be used only if the merchant has the permission to work with stored credentials. If this parameter is passed in this request, it means that:  This order can only be paid with a stored credential; The payer will be redirected to a payment page where only CVC entry is required. |

`operations` element contains the following parameters.

| Name | Type | Mandatory | Description |
| --- | --- | --- | --- |
| Optional | `amount` | Integer [0..12] | Payment amount in minor currency units (e.g. in cents). |
|  |  |  |  |
| Optional | `cardHolder` | String [1..26] | Cardholder's name in Latin characters. This parameter is passed only after an order is paid. |
|  |  |  |  |
| Optional | `authCode` | Integer [6] | Deprecated parameter (not used). Its value is always 2 regardless the order status and authorization code of the processing system. |

More information about how to know whether the payment was successfull or not is available here.

### Examples

#### Request example

```bash
curl --request POST \
  --url https://dev.bpcbt.com/payment/installmentPayment.do \
  --header 'Content-Type: application/json' \
  --data '{
  "userName": "test_user",
  "password": "test_user_password",
  "orderNumber": "UAF-203974-DE-12",
  "language": "EN",
  "bindingId": "8aa4fa8b-4d8a-76ca-b314-7bcc00b4f820",
  "amount": 12300,
  "currency": "978",
  "description" : "Test description",
  "additionalParameters": {
    "firstParamName": "firstParamValue",
    "secondParamName": "secondParamValue"
  }
 }'
```

### Response examples

```json
{
  "errorCode": 0,
  "errorMessage": "Success",
  "orderId": "0e441115-f3bc-711c-8827-2fdc00b4f820",
  "orderStatus": {
    "errorCode": "0",
    "orderNumber": "7033",
    "orderStatus": 2,
    "actionCode": 0,
    "actionCodeDescription": "",
    "amount": 12300,
    "currency": "978",
    "date": 1618340470944,
    "orderDescription": "Test description",
    "merchantOrderParams": [
      {
        "name": "firstParamName",
        "value": "firstParamValue"
      },
      {
        "name": "secondParamName",
        "value": "secondParamValue"
      }
    ],
    "transactionAttributes": [],
    "attributes": [
      {
        "name": "mdOrder",
        "value": "0e441115-f3bc-711c-8827-2fdc00b4f820"
      }
    ],
    "cardAuthInfo": {
      "maskedPan": "400000**1118",
      "expiration": "203012",
      "cardholderName": "TEST CARDHOLDER",
      "approvalCode": "123456",
      "paymentSystem": "VISA",
      "product": "visa-product",
      "secureAuthInfo": {
        "eci": 7
      },
      "pan": "400000**1118"
    },
    "bindingInfo": {
      "clientId": "TEST CARDHOLDER",
      "bindingId": "8aa4fa8b-4d8a-76ca-b314-7bcc00b4f820"
    },
    "authDateTime": 1618340471076,
    "authRefNum": "111111111111",
    "paymentAmountInfo": {
      "paymentState": "DEPOSITED",
      "approvedAmount": 12300,
      "depositedAmount": 12300,
      "refundedAmount": 0,
      "totalAmount": 12300
    },
    "bankInfo": {
      "bankName": "ES TEST BANK",
      "bankCountryCode": "ES",
      "bankCountryName": "Spain"
    },
    "chargeback": false,
    "operations": [
      {
        "amount": 12300,
        "cardHolder": "TEST CARDHOLDER",
        "authCode": "123456"
      }
    ]
  },
  "error": false
}
```

## Creating a stored credential without payment

To create a stored credential without performing payment, use the `https://dev.bpcbt.com/payment/rest/createBindingNoPayment.do` request.

To use this method, you must have the appropriate permissions in the system.

When sending the request, you should use the header: `Content-Type: application/x-www-form-urlencoded`

### Request parameters

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `userName` | String [1..100] | Merchant's API account login. |
|  |  |  |  |
| Mandatory | `password` | String [1..30] | Merchant's API account password. |
|  |  |  |  |
| Mandatory | `clientId` | String [0..255] | Customer number (ID) in the merchant's system. Used to implement the functionality of stored-credential transactions. |
|  |  |  |  |
| Optional | `bindingStrength` | String | The type of payment on the base of which the credential was stored. It is used for migration of stored credentials from the merchant's system. Possible values:  TDS - credential was stored as a result of full 3DS payment (ECI=02 or 05) SSL - credential was stored as a result of SSL payment (ECI=07) TDS_SSL - credential was stored as a result of SSL payment with a 3DS authentication attempt (ECI=01 or 06) NO_PAYMENT - credential was stored without payment (default value)  All values other than NO_PAYMENT require passing additional parameters: initNetworkReferenceNumber - identifier of initial payment for storing a credential networkReferenceNumber - identifier of the last payment by the stored credential |
|  |  |  |  |
| Mandatory | `cardholderName` | String [1..26] | Cardholder's name in Latin characters. Allowed symbols: Latin characters, period, space. |
|  |  |  |  |
| Mandatory | `expiryDate` | String [6] | Card expiration in the following format: YYYYMM. |
|  |  |  |  |
| Mandatory | `pan` | String [1..19] | Payment card number |
|  |  |  |  |
| Optional | `additionalParameters` | Object | Additional parameters of the order that are stored in the merchant personal area for the subsequent viewing. Each new pair of a parameter name and its value must be separated by a comma. Below is a usage example. { "firstParamName": "firstParamValue", "secondParamName": "secondParamValue"} |
|  |  |  |  |
| Optional | `merchantLogin` | String [1..255] | To create a stored credential for another merchant, specify the merchant's API account login in this parameter. Can be used only if you have the permission to see the transactions of other merchants or if the specified merchant is your child merchant. |
|  |  |  |  |
| Optional | `email` | String [1..40] | The payer's email address. |
|  |  |  |  |
| Optional | `phone` | String [7..15] | Customer's phone number. It is always necessary to specify the country code, but you can specify or omit the sign or 00 at the beginning. The number must be 7 to 15 digits long. Thus, the following options are valid:  +35799988877; 0035799988877; 35799988877. |
|  |  |  |  |

### Response parameters

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `errorCode` | String [1..2] | Information parameter in case of an error, which may have different code values:  0 value - indicates success of the request processing; another number value (1-99) - indicates an error for more details of which errorMesage parameter must be inspected. It also can be missing if the result has not caused any error.  If the request, associated with an order payment, is processed successfully, it does not directly mean that the payment itself was successful. To determine whether the payment was successful or not, use getOrderStatusExtended.do request. |
|  |  |  |  |
| Optional | `errorMessage` | String [1..512] | Information parameter that is an error description in a case of error occurance. errorMessage value can vary, so it should not be hardcoded. Language of the description is set in language parameter of the request. |
|  |  |  |  |
| Optional | `error` | Boolean | A flag indicating that the response contains an error. Allowed values: true or false. Takes the value true, if errorCode value differs from 0. |
|  |  |  |  |
| Optional | `bindingId` | String [1..255] | Identifier of a stored credential created earlier of used for the payment. Is present only if the merchant has a permission to use stored credentials. |
|  |  |  |  |
| Optional | `clientId` | String [0..255] | Customer number (ID) in the merchant's system — up to 255 characters. Used to implement the functionality of stored-credential transactions. Can be returned in the response if the merchant is allowed to store credentials. Specifying this parameter in stored-credential transactions is mandatory. Otherwise, a payment will be unsuccessful. |
|  |  |  |  |
| Optional | `cardholderName` | String [1..26] | Cardholder's name in Latin characters. Allowed symbols: Latin characters, period, space. |
|  |  |  |  |
| Optional | `expiryDate` | String [6] | Card expiration in the following format: YYYYMM. |
|  |  |  |  |
| Optional | `maskedPan` | String [1..19] | Masked number of the card used for the payment. It contains real first 6 and last 4 digits of the card number in the format XXXXXX**XXXX. |
|  |  |  |  |

### Examples

#### Request example

```bash
curl --request POST \
  --url https://dev.bpcbt.com/payment/rest/createBindingNoPayment.do \
  --header 'Content-Type: application/x-www-form-urlencoded' \
  --data userName=test_user \
  --data password=test_user_password \
  --data clientId=159753456
  --data pan=5555555555555599
  --data expiryDate=203412
  --data pan=5555555555555599
  --data cardholderName=TEST CARDHOLDER
```

#### Request example for migration of stored credentials

```bash
curl --request POST \
  --url https://dev.bpcbt.com/payment/rest/createBindingNoPayment.do \
  --header 'Content-Type: application/x-www-form-urlencoded' \
  --data userName=test_user \
  --data password=test_user_password \
  --data merchantLogin=some_merchant \
  --data pan=5555555555555599 \
  --data expiryDate=203412 \
  --data 'cardholderName=TEST CARDHOLDER ' \
  --data clientId=client_id \
  --data bindingStrength=TDS \
  --data email=email@test.com \
  --data 'phone=+995555000000' \
  --data 'additionalParameters={
    "networkReferenceNumber": "network_reference_number",
    "initNetworkReferenceNumber": "init_network_reference_number"
}'
```

#### Response example

```json
{
  "maskedPan": "555555**5599",
  "expiryDate": "203412",
  "cardholderName": "TEST CARDHOLDER",
  "clientId": "159753456",
  "bindingId": "47dbe208-e531-4997-9c36-25a5707d3cb9",
  "errorCode": 0,
  "error": false
}
```

