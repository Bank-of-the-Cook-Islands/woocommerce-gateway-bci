> Source: https://dev.bpcbt.com/en/integration/api/rest.html

# Wallets

## Apple Pay order registration

The `https://dev.bpcbt.com/payment/applepay/payment.do` request is used to register and pay for the order.

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
| Mandatory | `paymentToken` | String [1..8192] | The paymentToken parameter must contain a Base64 encoded value of the paymentData property that was received in PKPaymentToken Object from the Apple Pay system (see https://developer.apple.com/library/content/documentation/PassKit/Reference/PaymentTokenJSON/PaymentTokenJSON.html). Thus, to make a request to the payment gateway, the merchant must:  get PKPaymentToken Object containing paymentData from Apple Pay; extract paymentData value and encode it in Base64; include the encoded value of the paymentData property as the value of the paymentToken parameter in the payment request that the merchant sends to the payment gateway. |
|  |  |  |  |
| Optional | `tii` | String | Transaction initiator indicator. A parameter indicating what type of operation will be carried out by the initiator (Customer or Merchant). Possible values. |
|  |  |  |  |
| Conditional | `clientId` | String [0..255] | Customer number (ID) in the merchant's system — up to 255 characters. Used to implement the functionality of stored-credential transactions. Can be returned in the response if the merchant is allowed to store credentials. Specifying this parameter in stored-credential transactions is mandatory. Otherwise, a payment will be unsuccessful. |
|  |  |  |  |
| Conditional | `email` | String [1..40] | Email to be displayed on the payment page. Customer's email must be passed if client notification is configured for the merchant. Example: client_mail@email.com. For payment by VISA with 3DS authorization, it is necessary to specify either phone or email of the cardholder. |
|  |  |  |  |
| Optional | `mcc` | Integer [4] | Merchant Category Code. Using this parameter requires a special permission. You can use only the values from the predefined list of allowed MCC values. Contact the support team for details. |
|  |  |  |  |
| Optional | `mvv` | String [1..10] | Merchant verification value from Mastercard for tokenized transactions. To pass this parameter, a special setting must be enabled (contact technical support). |
|  |  |  |  |
| Optional | `paymentFacilitator` | Object | A block with the parameters of a payment facilitator, i.e. a merchant who allows several submerchants to accept payments under its account. This parameter is used if a special setting is enabled (contact the support team). See nested parameters. |
| Conditional | `phone` | String [7..15] | Customer's phone number. It is always necessary to specify the country code, but you can specify or omit the + sign or 00 at the beginning. The number must be 7 to 15 digits long. Thus, the following options are valid:  +35799988877; 0035799988877; 35799988877.  For payment by VISA with 3DS authorization, it is necessary to specify either phone or email of the cardholder. If you have a setting to display phone number on the payment page and have specified an invalid number, the customer will have a possibility to correct it on the payment page. |
|  |  |  |  |
| Optional | `threeDSProtocolVersion` | String | 3DS protocol version. Possible values are "2.1.0", "2.2.0" for 3DS2. If threeDSProtocolVersion is not passed in the request, then the default value will be used for 3D Secure authorization (2.1.0 - for 3DS 2). |
|  |  |  |  |
| Optional | `externalScaExemptionIndicator` | String | The type of SCA (Strong Customer Authentication) excemption. If this parameter is specified, the transaction will be processed depending on your settings in the payment gateway: either forced SSL operation will be done, or the issuer bank will get the information about SCA excemption and decide to perform operation with or without 3DS authentication (for details, contact our support team). Allowed values:  LVP – Low Value Payments transaction. You can consider a transaction as low risk based on the transaction amount, the client's transactions per day or the client's total daily amount. TRA – Transaction Risk Analysis transaction, i.e., the transaction that has passed successful anti-fraud check.  To pass this parameter, you must have sufficient permissions in the payment gateway. |

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

### Response  parameters

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

`error` block contains the following elements.

| Required | Name | Type | Description |
| --- | --- | --- | --- |
|  | `code` | String [1..3] | Code as an information parameter stating an error occurred. |
|  |  |  |  |
|  | `description` | String [1..598] | A detailed technical explanation of the error - the contents of this parameter should not to be displayed to the customer. |
|  |  |  |  |
|  | `message` | String [1..512] | Information parameter that is an error description to be displayed to the user. The parameter may vary, so it should not be hardcoded. |
|  |  |  |  |

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
| Conditional | `merchantOrderParams` | Object | Object with attributes in which the merchant's additional parameters are transmitted. See the description below. |
| Conditional | `attributes` | Object | Attributes of the order in the payment system (order number). See the description below. |
| Conditional | `cardAuthInfo` | Object | Information about the buyer's payment card. See the description below. |
| Optional | `authDateTime` | Integer | Authorization date and time, shown as the amount of milliseconds since 00:00 January 1, 1970 GMT (UNIX time). Example: 1740392720718 (Corresponds to February 24, 2025, 10:25:20 (UTC)). |
|  |  |  |  |
| Optional | `terminalId` | String [1..10] | Terminal identifier in the system that processes the payment. |
| Optional | `authRefNum` | String [1..24] | Reference number of the payment authorization that has been assigned to it upon its registration. |
|  |  |  |  |
| Conditional | `paymentAmountInfo` | Object | A parameter containing embedded parameters with information about confirmation, debiting and refund amounts. See the description below. |
| Conditional | `bankInfo` | Object | Contains the embedded bankCountryName parameter. See the description below. |

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
| Optional | `detokenizedPanRepresentation` | String [1..19] | The detokenized card number (the last 4 digits or in a masked form). |
|  |  |  |  |
| Optional | `detokenizedPanExpiryDate` | String | The card's detokenized expiration date in the following format: "YYYYMM". |

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

`bankInfo` block contains the following elements.

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `bankCountryName` | String [1..160] | Country of the issuing bank. |

- If success = true and orderStatus.orderStatus = 1 or 2 a transaction is approved.
- If success = true and orderStatus.orderStatus <> 1 or 2 a transaction is declined.
- If success = false or errorCode <> 0 transaction is declined.

More information about how to know whether the payment was successfull or not is available here.

### Examples

#### Request example

```bash
curl --request POST \
--url https://dev.bpcbt.com/payment/applepay/payment.do \
--header 'Content-Type: application/json' \
--data-raw '{
  "additionalParameters" : {
    "phone" : "9521235847",
    "order-pain" : "111",
    "email" : "apple@pay.com"
  },
  "language" : "en",
  "clientId" : "259753456",
  "orderNumber" : "281477871",
  "paymentToken" : "eyJkYXRhIjoiYPhK3M1bEtm...YjM2NWMzZWNmYjE5fIkVDX3YxIn0=",
  "preAuth" : false
}'
```

#### Response in case of a successful payment

```json
{
    "success": true,
    "data": {
        "orderId": "b926351f-a634-49cf-9484-ccb0a3b8cfad"
    },
    "orderStatus": {
        "errorCode": "0",
        "orderNumber": "229",
        "orderStatus": 1,
        "actionCode": 0,
        "actionCodeDescription": "",
        "amount": 960000,
        "currency": "978",
        "date": 1478682458102,
        "ip": "x.x.x.x",
        "merchantOrderParams": [
            {
                "name": "param2",
                "value": "param2"
            },
            {
                "name": "param1",
                "value": "param1"
            }
        ],
        "attributes": [
            {
                "name": "mdOrder",
                "value": "b926351f-a634-49cf-9484-ccb0a3b8cfad"
            }
        ],
        "cardAuthInfo": {
            "expiration": "203012",
            "cardholderName": "TEST CARDHOLDER",
            "approvalCode": "123456",
            "pan": "500000**1115"
        },
        "authDateTime": 1478682459082,
        "terminalId": "12345678",
        "authRefNum": "111111111111",
        "paymentAmountInfo": {
            "paymentState": "APPROVED",
            "approvedAmount": 960000,
            "depositedAmount": 0,
            "refundedAmount": 0
        },
        "bankInfo": {
            "bankCountryName": "<UNKNOWN>"
        }
    }
}
```

#### Response in case of a failed payment

```json
{
  "error": {
    "code": 10,
    "description": "Processing Error",
    "message": "Auth is invalid"
  },
  "success": false
}
```

## Apple Pay Direct

The request used to make a direct payment via Apple Pay is `https://dev.bpcbt.com/payment/applepay/paymentDirect.do`. It is used to register and pay for the order.

When sending the request, you should use the header: `Content-Type: application/json`

This request can be used for integrations that involve payment data decoding on Merchant side.

If the value of tii parameter is R,U, or F, the request must contain originalPaymentNetRefNum and originalPaymentDate parameters. If these parameters are not passed, the regular payment is performed.

### Request parameters

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `userName` | String [1..100] | Merchant's API account login. |
|  |  |  |  |
| Mandatory | `password` | String [1..30] | Merchant's API account password. |
|  |  |  |  |
| Mandatory | `orderNumber` | String [1..36] | Order number (ID) in the merchant's system, must be unique for each order. |
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
| Optional | `feeInput` | Integer [0..8] | Fee amount in minimum currency units. Must be enabled by respective Merchant-level permission in the Gateway. |
|  |  |  |  |
| Optional | `additionalParameters` | Object | Additional parameters of the order that are stored in the merchant personal area for the subsequent viewing. Each new pair of a parameter name and its value must be separated by a comma. Below is a usage example. { "firstParamName": "firstParamValue", "secondParamName": "secondParamValue"} When storing a credential, this tag can contain parameters that specify the type of the stored credential. See the list of parameters. |
|  |  |  |  |
| Optional | `preAuth` | Boolean | Parameter that defines the necessity of a pre-authorization (putting the amount on hold on the customer's account until its debiting). The following values are available:  true - two-phase payments enabled; false - one-phase payments enabled (money are charged right away). If the parameter is missing, one-phase payment is made. |
|  |  |  |  |
| Optional | `autocompletionDate` | String [19] | The date and time when the two-phase payment must be completed automatically in the following format: 2025-12-29T13:02:51. The used timezone is UTC+0. To enable sending this field to the processing system, contact your technical support service. |
|  |  |  |  |
| Optional | `autoReverseDate` | String [19] | The date and time when the two-phase payment must be reversed automatically in the following format: 2025-06-23T13:02:51. The used timezone is UTC+0. To enable sending this field to the processing system, contact your technical support service. |
|  |  |  |  |
| Mandatory | `paymentToken` | String [1..8192] | Payment data as received from Apple Pay and decrypted by a merchant. Flow:  Receive PKPaymentToken Object from Apple Pay (Payment Token Format Reference) with encrypted payment data; Decrypt (ECC/RSA) paymentData to get clear-text view of the object: {"applicationPrimaryAccountNumber":"4111111111111111","deviceManufacturerIdentifier":"050110030273","currencyCode":"840"," applicationExpirationDate":"220430","paymentData":{"onlinePaymentCryptogram":"AM32yL0vuOOmAAGG0iQUAoABFA=="}," paymentDataType":"3DSecure","transactionAmount":1010}; BASE64 encode clear-text paymentData object and send it as paymentToken. |
|  |  |  |  |
| Optional | `merchant` | String [1..255] | To register an order and carry out payment on behalf of another merchant, specify the merchant's API account login in this parameter. Can be used only if you have the permission to see the transactions of other merchants or if the specified merchant is your child merchant. |
|  |  |  |  |
| Optional | `features` | String | In this parameter you can pass VERIFY — in this case the payment will not be made, instead, a credential will be stored (i.e., Customer's card will be saved). Note that if you pass features, paymentToken.transactionAmount must be 0. Otherwise, an error will be returned. |
|  |  |  |  |
| Optional | `tii` | String | Transaction initiator indicator. A parameter indicating what type of operation will be carried out by the initiator (Customer or Merchant). Possible values. |
|  |  |  |  |
| Conditional | `originalPaymentNetRefNum` | String [1..36] | The identifier of the original or previous successful transaction in the payment system in relation to the performed stored-credential transaction - TRN ID. Is passed when tii = R,U, or F. Is mandatory when using merchant's stored credentials in stored credential transfers. |
|  |  |  |  |
| Conditional | `originalPaymentDate` | String | Date of initiating transaction. The format is Unix timestamp, in milliseconds. Is passed when tii = R,U, or F. |
|  |  |  |  |
| Conditional | `clientId` | String [0..255] | Customer number (ID) in the merchant's system — up to 255 characters. Used to implement the functionality of stored-credential transactions. Can be returned in the response if the merchant is allowed to store credentials. Specifying this parameter in stored-credential transactions is mandatory. Otherwise, a payment will be unsuccessful. |
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
| Mandatory | `data` | Object | Returns only if the payment is successful. |
|  |  |  |  |
| Mandatory | `error` | Object | This parameter is returned only if the payment failed. |
|  |  |  |  |
| Optional | `orderStatus` | Integer | The value of this parameter specifies the status of the order in the payment gateway. It is missing if the order has not been found. Below is the list of available values:  0 - order was registered but not paid; 1 - order was authorized only and wasn't captured yet (for two-phase payments); 2 - order was authorized and captured; 3 - authorization canceled; 4 - transaction was refunded; 5 - access control server of the issuing bank initiated authorization procedure; 6 - authorization declined; 7 - pending order payment; 8 - intermediate completion for multiple partial completion. |

Parameters in `data` block:

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `orderId` | String [1..36] | Order number in the payment gateway. Unique within the payment gateway. |

Parameters in `error` block:

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `code` | String [1..3] | Code as an information parameter stating an error occurred. |
|  |  |  |  |
| Mandatory | `message` | String [1..512] | Information parameter that is an error description to be displayed to the user. The parameter may vary, so it should not be hardcoded. |
| Mandatory | `description` | String [1..598] | A detailed technical explanation of the error - the contents of this parameter should not to be displayed to the customer. |

Parameters in `orderStatus` block:

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Optional | `errorCode` | String [1..2] | Information parameter in case of an error, which may have different code values:  0 value - indicates success of the request processing; another number value (1-99) - indicates an error for more details of which errorMesage parameter must be inspected. It also can be missing if the result has not caused any error.  If the request, associated with an order payment, is processed successfully, it does not directly mean that the payment itself was successful. To determine whether the payment was successful or not, use getOrderStatusExtended.do request. |
|  |  |  |  |
| Optional | `errorMessage` | String [1..512] | Information parameter that is an error description in a case of error occurance. errorMessage value can vary, so it should not be hardcoded. Language of the description is set in language parameter of the request. |
|  |  |  |  |
| Optional | `orderNumber` | String [1..36] | Order number (ID) in the merchant's system, must be unique for each order. |
|  |  |  |  |
| Optional | `orderStatus` | Integer | The value of this parameter specifies the status of the order in the payment gateway. It is missing if the order has not been found. Below is the list of available values:  0 - order was registered but not paid; 1 - pre-authorized amount is on hold on the buyer's account (for two-phase payments); 2 - order amount is fully authorized; 3 - authorization canceled; 4 - transaction was refunded; 5 - access control server of the issuing bank initiated authorization procedure; 6 - authorization declined. |
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
| Optional | `merchantOrderParams` | Object | Section with attributes in which the merchant's additional parameters are passed. |
|  |  |  |  |
| Optional | `cardAuthInfo` | Object | Block containing data about the payer's card see nested parameters. |
|  |  |  |  |
| Optional | `authDateTime` | Integer | Authorization date and time, shown as the amount of milliseconds since 00:00 January 1, 1970 GMT (UNIX time). Example: 1740392720718 (Corresponds to February 24, 2025, 10:25:20 (UTC)). |
|  |  |  |  |
| Optional | `terminalId` | String [1..10] | Terminal identifier in the system that processes the payment. |
|  |  |  |  |
| Optional | `authRefNum` | String [1..24] | Reference number of the payment authorization that has been assigned to it upon its registration. |
|  |  |  |  |
| Optional | `paymentAmountInfo` | Object | Object containing the information on the confirmation amount, debit amount, and refund amount. See nested parameters below. |
|  |  |  |  |
| Optional | `bankInfo` | Object | Object containing a nested parameter bankCountryName in which the name of the country of the issuing bank is passed (if available). The language used is the same as the language passed in the language parameter of the request. If no language is passed, the language of the user calling the method will be used. |

`orderStatus` can include `payerData` element, which contains the following parameters.

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Optional | `paymentAccountReference` | String [1..29] | The unique account number of the client, which links all their payment means within the IPS (cards and tokens). |
|  |  |  |  |

Parameters in `cardAuthInfo` block:

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Optional | `expiration` | Integer | Year and month of card expiration. |
|  |  |  |  |
| Optional | `cardholderName` | String [1..26] | Cardholder's name (if available). |
|  |  |  |  |
| Optional | `approvalCode` | String [6] | IPS authorization code. This field has a fixed length (six symbols) and can contain digits and Latin letters. |
|  |  |  |  |
| Optional | `pan` | String [1..19] | Masked DPAN: a number that is linked to the customer's mobile device and functions as a payment card number in the Apple Pay system. |
|  |  |  |  |
| Optional | `detokenizedPanRepresentation` | String [1..19] | The detokenized card number (the last 4 digits or in a masked form). |
|  |  |  |  |
| Optional | `detokenizedPanExpiryDate` | String | The card's detokenized expiration date in the following format: "YYYYMM". |

Parameters in `paymentAmountInfo` block:

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Optional | `paymentState` | String | Order status, this parameter can have the following values:  CREATED - order created (but not paid); APPROVED - order approved (funds are on hold on buyer's account); DEPOSITED - order deposited (buyer is charged); DECLINED - order declined; REVERSED - order canceled; REFUNDED - refund. |
|  |  |  |  |
| Optional | `approvedAmount` | Integer [0..12] | Amount in minimum currency units (e.g. cents) that was put on hold on buyer's account. Used in two-phase payments only. In case of partial authorization, this amount may be less than the order registration amount. |
|  |  |  |  |
| Optional | `depositedAmount` | Integer [1..12] | Charged amount in minimum currency units (e.g., in cents). In case of partial authorization, this amount may be less than the order registration amount. |
|  |  |  |  |
| Optional | `refundedAmount` | Integer [1..12] | Refunded amount in minimum currency units. |
|  |  |  |  |
| Optional | `totalAmount` | Integer [1..20] | Order amount plus fee, if any. |

More information about how to know whether the payment was successfull or not is available here.

### Examples

#### Request example

```bash
curl --location --request POST 'https://dev.bpcbt.com/payment/applepay/paymentDirect.do' \
--header 'Content-Type: application/json' \
--data-raw '{
    "username": "test_user",
    "password": "test_user_password",
    "orderNumber": "947664b3-4a42-4cdf-9f8c-2e9679bad9e4",
    "description": "description of the order",
    "language": "en",
    "paymentToken": "eyJhcHBsaWNhPT...0ifX0="
}'
```

#### Response examples - successful payment

```json
{
    "success": true,
    "data": {
        "orderId": "b926351f-a634-49cf-9484-ccb0a3b8cfad"
    },
    "orderStatus": {
        "errorCode": "0",
        "orderNumber": "229",
        "orderStatus": 1,
        "actionCode": 0,
        "actionCodeDescription": "",
        "amount": 960000,
        "currency": "978",
        "date": 1478682458102,
        "ip": "x.x.x.x",
        "merchantOrderParams": [
            {
                "name": "param2",
                "value": "param2"
            },
            {
                "name": "param1",
                "value": "param1"
            }
        ],
        "attributes": [
            {
                "name": "mdOrder",
                "value": "b926351f-a634-49cf-9484-ccb0a3b8cfad"
            }
        ],
        "cardAuthInfo": {
            "expiration": "203012",
            "cardholderName": "TEST CARDHOLDER",
            "approvalCode": "123456",
            "pan": "500000**1115"
        },
        "authDateTime": 1478682459082,
        "terminalId": "12345678",
        "authRefNum": "111111111111",
        "paymentAmountInfo": {
            "paymentState": "APPROVED",
            "approvedAmount": 960000,
            "depositedAmount": 0,
            "refundedAmount": 0
        },
        "bankInfo": {
            "bankCountryName": "<UNKNOWN>"
        }
    }
}
```

#### Response example - payment error

```json
{
  "error": {
    "code": 1,
    "description": "Processing Error",
    "message": "Insufficient amount on card"
  },
  "success": false
}
```

