> Source: https://dev.bpcbt.com/en/integration/api/rest.html

# Miscellaneous

## Card verification

`https://dev.bpcbt.com/payment/rest/verifyCard.do` method can be used in verification opertaions. The payment is not made and goes directly to `REVERSED` status.

When sending the request, you should use the header: `Content-Type: application/x-www-form-urlencoded`

To use this method, you must meet the PCI SAQ D requirements.
If payment without CVC is used, you must meet the PCI SAQ A requirements.

### Request parameters

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Optional | `userName` | String [1..30] | Merchant 's API account login (mandatory, unless token is passed). If you pass your login and password to authenticate in the payment gateway, do not pass token parameter. |
|  |  |  |  |
| Optional | `password` | String [1..30] | Merchant's API account password (mandatory, unless token is passed). If you pass your login and password to authenticate in the payment gateway, do not pass token parameter. |
|  |  |  |  |
| Optional | `token` | String [1..256] | Value that is used for merchant authentication when requests are sent to the payment gateway (mandatory, unless userName and password are passed). If you pass this parameter, do not pass userName and password. |
|  |  |  |  |
| Mandatory | `amount` | Integer [0..12] | Payment amount in minor currency units (e.g. in cents). |
|  |  |  |  |
| Optional | `currency` | String [3] | ISO 4217 encoded currency key. If not specified, the default value is used. Only digits are allowed. |
|  |  |  |  |
| Optional | `pan` | String [1..19] | Payment card number |
|  |  |  |  |
| Optional | `cvc` | String [3] | The presence of this parameter is determined by payment type:  cvc is provided not for all tokenized payments; cvc is not provided for MIT payments; cvc is mandatory by default for all other payment types; but if permission Can process payments without confirmation of CVC is enabled, cvc becomes optional in that case.  Only digits are allowed. |
|  |  |  |  |
| Optional | `expiry` | Integer [6] | Card expiration in the following format: YYYYMM. Mandatory, if neither seToken nor bindingId is passed. |
|  |  |  |  |
| Optional | `cardholderName` | String [2..45] | Cardholder's name in Latin characters. This parameter is passed only after an order is paid. Such special characters as space, full stop, hyphen, apostrophe ( . - ') can be used. The use of other characters is prohibited. |
|  |  |  |  |
| Optional | `backUrl` | String [1..512] | URL the user is to be redirected to if payment is successful. Use full path with protocol included, like this - https://test.com (not test.com). Otherwise the user will be redirected to a URL composed like this: http://paymentGatewayURL/merchantURL  The address must not be a relative path, i.e. it must not start with "." and "/". Otherwise, error 4 will be returned: "The return URL is invalid". For example: ../test.html, /test.html- invalid URLs |
|  |  |  |  |
| Optional | `failUrl` | String [1..512] | The address to which the user is to be redirected in case of a failed payment. The address must be specified in full including the protocol used (for example, https://mybestmerchantreturnurl.com instead of mybestmerchantreturnurl.com). Otherwise, the user will be redirected to the address of the following type https://dev.bpcbt.com/payment/<merchant_address>.  The address must not be a relative path, i.e. it must not start with "." and "/". Otherwise, error 4 will be returned: "The return URL is invalid". For example: ../test.html, /test.html- invalid URLs. |
|  |  |  |  |
| Optional | `description` | String [1..598] | Order description in any format. To enable sending this field to the processing system, contact the technical support service. It is not allowed to fill this parameter with personal data or payment data (card numbers, etc.). This requirement is due to the fact that the order description is not masked in Merchant Portal and log files. |
|  |  |  |  |
| Optional | `language` | String [2] | ISO 639-1 encoded language key. If the language is not specified, the default language specified in the store settings is used. Supported languages: en,el,ro,bg,pt,sw,hu,it,pl,de,fr,kh,cn,es,ka,da,et,fi,lt,lv,nl,sv. |
|  |  |  |  |
| Optional | `returnUrl` | String [1..512] | The address to which the user will be redirected if the payment is successful. The address must be specified in full including the protocol used (for example, https://mybestmerchantreturnurl.com instead of mybestmerchantreturnurl.com). Otherwise, the user will be redirected to the address of the following type https://dev.bpcbt.com/payment/<merchant_address>.  The address must not be a relative path, i.e. it must not start with "." and "/". Otherwise, error 4 will be returned: "The return URL is invalid". For example: ../test.html, /test.html- invalid URLs. |
|  |  |  |  |
| Optional | `threeDSServerTransId` | String [1..36] | Transaction identifier created on 3DS Server. Mandatory for 3DS authentication. |
|  |  |  |  |
| Optional | `threeDSVer2FinishUrl` | String [1..512] | URL where Customer should be redirected after authentication on ACS Server. |
|  |  |  |  |
| Conditional | `threeDSVer2MdOrder` | String [1..36] | Order number which was registered in the first part of the request within 3DS2 transaction. Mandatory for 3DS2 authentication. If this parameter is present in the request, the mdOrder value passed in it overrides, and in this case the order gets paid right away instead of being registered. This parameter is used only for instant payments, i.e., when the order is registered and payed via the same request. |
|  |  |  |  |
| Optional | `threeDSSDK` | Boolean | Possible values: true or false. Flag showing that payment comes from 3DS SDK. |
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
| Optional | `errorCode` | String [1..2] | Information parameter in case of an error, which may have different code values:  0 value - indicates success of the request processing; another number value (1-99) - indicates an error for more details of which errorMesage parameter must be inspected. It also can be missing if the result has not caused any error.  If the request, associated with an order payment, is processed successfully, it does not directly mean that the payment itself was successful. To determine whether the payment was successful or not, use getOrderStatusExtended.do request. |
|  |  |  |  |
| Optional | `errorMessage` | String [1..512] | Information parameter that is an error description in a case of error occurance. errorMessage value can vary, so it should not be hardcoded. Language of the description is set in language parameter of the request. |
|  |  |  |  |
| Optional | `orderId` | String [1..36] | Order number in the payment gateway. Unique within the payment gateway. |
|  |  |  |  |
| Optional | `orderNumber` | String [1..36] | Order number (ID) in the merchant's system, must be unique for each order. |
|  |  |  |  |
| Optional | `authCode` | Integer [6] | Deprecated parameter (not used). Its value is always 2 regardless the order status and authorization code of the processing system. |
|  |  |  |  |
| Optional | `actionCode` | String | Response code from the processing bank. Contains a numeric value. See the list of action codes here. |
|  |  |  |  |
| Optional | `actionCodeDescription` | String [1..512] | actionCode description returned from the processing bank. |
|  |  |  |  |
| Optional | `time` | Integer | Time when transaction took place as the amount of milliseconds since 00:00 January 1, 1970 GMT (UNIX time). Example: 1740392720718 (Corresponds to February 24, 2025, 10:25:20 (UTC)). |
|  |  |  |  |
| Optional | `eci` | Integer [1..4] | Electronic commerce indicator. The indicator is specified only after an order has been paid and in case the corresponding permission is present. Below is the explanation of ECI codes.  ECI=01 or ECI=06 - merchant supports 3-D Secure, payment card does not support 3-D Secure, payment is processed based on CVV2/CVC code. ECI=02 or ECI=05 - both merchant and payment card support 3-D Secure; ECI=07 - merchant does not support 3-D Secure, payment is processed based on CVV2/CVC code. |
|  |  |  |  |
| Optional | `amount` | Integer [0..12] | Payment amount in minor currency units (e.g. in cents). |
|  |  |  |  |
| Optional | `currency` | String [3] | ISO 4217 encoded currency key. If not specified, the default value is used. Only digits are allowed. |
|  |  |  |  |
| Optional | `rrn` | Integer [1..12] | Reference Retrieval Number - transaction ID assigned by Acquiring Bank. |
|  |  |  |  |
| Optional | `acsUrl` | String [1..512] | The URL address for redirecting to ACS. It is returned in a successful response in case of a 3D-Secure payment, when redirect to the ACS is needed. For details see Redirect to ACS. |
|  |  |  |  |
| Optional | `termUrl` | String [1..512] | In a successful response in case of a 3D-Secure payment. The URL address to which ACS redirects the cardholder after authentication. For details see Redirect to ACS. |
|  |  |  |  |
| Optional | `paReq` | String [1..255] | PAReq (Payment Authentication Request) - a message that should be sent to ACS together with redirect. It is returned in a successful response in case of a 3D-Secure payment, when redirect to the ACS is needed. This message contains the Base64-encoded data necessary for the cardholder authentication. For details see Redirect to ACS. |

### Examples

#### Request example

```bash
curl --request POST \
  --url https://dev.bpcbt.com/payment/rest/verifyCard.do \
  --header 'content-type: application/x-www-form-urlencoded' \
  --data userName=test_user \
  --data password=test_user_password \
  --data pan=4000001111111118 \
  --data cvc=123 \
  --data expiry=203012
```

#### Response example

```json
{
  "errorCode": "0",
  "errorMessage": "Success",
  "orderId": "cfc238ca-68f9-745c-ba7e-eb9100af79e0",
  "orderNumber": "12017",
  "rrn": "111111111115",
  "authCode": "123456",
  "actionCode": 0,
  "actionCodeDescription": "",
  "time": 1595284781180,
  "eci": "07",
  "amount": 0,
  "currency": "978"
}
```

