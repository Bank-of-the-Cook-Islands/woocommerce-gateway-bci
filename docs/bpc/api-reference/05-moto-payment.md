> Source: https://dev.bpcbt.com/en/integration/api/rest.html

## MOTO payment

The request used to carry out MOTO payments is `https://dev.bpcbt.com/payment/rest/motoPayment.do`.

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
| Optional | `orderNumber` | String [1..36] | Order number (ID) in the merchant's system, must be unique for each order. |
|  |  |  |  |
| Mandatory | `amount` | Integer [0..12] | Payment amount in minor currency units (e.g. in cents). |
|  |  |  |  |
| Optional | `currency` | String [3] | ISO 4217 encoded currency key. If not specified, the default value is used. Only digits are allowed. |
|  |  |  |  |
| Mandatory | `returnUrl` | String [1..512] | The address to which the user will be redirected if the payment is successful. The address must be specified in full including the protocol used (for example, https://mybestmerchantreturnurl.com instead of mybestmerchantreturnurl.com). Otherwise, the user will be redirected to the address of the following type https://dev.bpcbt.com/payment/<merchant_address>.  The address must not be a relative path, i.e. it must not start with "." and "/". Otherwise, error 4 will be returned: "The return URL is invalid". For example: ../test.html, /test.html- invalid URLs. |
|  |  |  |  |
| Optional | `failUrl` | String [1..512] | The address to which the user is to be redirected in case of a failed payment. The address must be specified in full including the protocol used (for example, https://mybestmerchantreturnurl.com instead of mybestmerchantreturnurl.com). Otherwise, the user will be redirected to the address of the following type https://dev.bpcbt.com/payment/<merchant_address>.  The address must not be a relative path, i.e. it must not start with "." and "/". Otherwise, error 4 will be returned: "The return URL is invalid". For example: ../test.html, /test.html- invalid URLs. |
|  |  |  |  |
| Optional | `description` | String [1..598] | Order description in any format. To enable sending this field to the processing system, contact the technical support service. It is not allowed to fill this parameter with personal data or payment data (card numbers, etc.). This requirement is due to the fact that the order description is not masked in Merchant Portal and log files. |
|  |  |  |  |
| Optional | `language` | String [2] | ISO 639-1 encoded language key. If the language is not specified, the default language specified in the store settings is used. Supported languages: en,el,ro,bg,pt,sw,hu,it,pl,de,fr,kh,cn,es,ka,da,et,fi,lt,lv,nl,sv. |
|  |  |  |  |
| Optional | `clientId` | String [0..255] | Customer number (ID) in the merchant's system — up to 255 characters. Used to implement the functionality of stored-credential transactions. Can be returned in the response if the merchant is allowed to store credentials. Specifying this parameter in stored-credential transactions is mandatory. Otherwise, a payment will be unsuccessful. |
|  |  |  |  |
| Optional | `merchantLogin` | String [1..255] | To carry out MOTO paymenent on behalf of another merchant, specify the merchant's API account login in this parameter. Can be used only if you have the permission to see the transactions of other merchants or if the specified merchant is your child merchant. |
|  |  |  |  |
| Optional | `postAddress` | String [1..255] | Delivery address. |
|  |  |  |  |
| Optional | `jsonParams` | Object | A set of additional free-form attributes, structure: jsonParams={"param_1_name":"param_1_value",...,"param_n_name":"param_n_value"} Can be passed to the Processing Center for further processing (additional configuration required - contact support). Some predefined jsonParams attributes:  backToShopUrl - adds a button to the payment page that will return the cardholder to the URL passed in this parameter backToShopName - configures the text label of the Return to Shop button by default, if used together with backToShopUrl installments - maximum number of allowed authorizations for installment payments. Required for creating an installment stored credential totalInstallmentAmount - total amount of all installment payments. The value is necessary for saving payment data for conducting installments recurringFrequency - minimum number of days between authorizations. Required for creating recurring stored credential, recommended for creating installment stored credential (if 3DS2 is used, the parameter is mandatory). recurringExpiry - date after which authorizations are not allowed, in YYYYMMDD format. Required for creating recurring stored credential, recommended for creating installment stored credential (if 3DS2 is used, the parameter is mandatory). paymentWay - payment method. To force MOTO payment, pass the value CARD_MOTO. |
|  |  |  |  |
| Optional | `features` | String | Features of the order. To specify multiple features, use this parameter several times in one request. As an example, below are the possible values.  VERIFY - If you specify this value in the order registration request, cardholder will be verified however they will not be charged any amount, so in this case amount parameter can be 0. Verification allows to make sure that a payment card is used by its legitimate owner, and further you can charge them without authentication (CVC, 3D-Secure). Even if some amount is passed in the request, the customer will not be charged if VERIFY feature is used. This value can be also used for storing the credential – in this case, the clientId parameter must be passed as well. Read more here. FORCE_TDS - Force 3-D Secure payment. If a payment card does not support 3-D Secure, the transaction will fail. FORCE_SSL - Force SSL payment (without 3-D Secure). FORCE_FULL_TDS - After 3-D Secure authentication, PaRes status must be Y, which guarantees successful user authentication. Otherwise, the transaction will fail. FORCE_CREATE_BINDING - passing this feature in the order registration request forcefully stores the credential. This functionality must be enabled by Merchant level permission in the Gateway. This value cannot be passed in a request with an existing bindingId or bindingNotNeeded = true (will cause validation error). When this feature is passed, the clientId parameter must be passed as well. If you pass both FORCE_CREATE_BINDING and VERIFY features, the order will be created for storing the credential ONLY (without payment). PARTIAL_AUTHORIZATION - partial authorization is available in the order. Read more here. FORCE_PAYMENT_WAY - force payment by the payment method specified in jsonParams in the paymentWay parameter. To process these payments merchant must have sufficient permissions in the payment gateway. This is currently used for force MOTO payments. To enable this, it is necessary to pass also paymentWay parameter with the value CARD_MOTO in jsonParams. |
|  |  |  |  |
| Optional | `dynamicCallbackUrl` | String [1..512] | This parameter allows you to use the functionality of sending callback notifications dynamically. Here you can pass the address to which all "payment" callback notifications activated for the merchant will be sent. "Payment" notifications are callback notifications related to the following events: successful hold, payment declined by timeout, cardpresent payment is declined, successful debit, refund, cancellation. At the same time, callback notifications activated for the merchant that are not related to payments (enabling/disabling a stored credential, storing a credential) will be sent to a static address for callbacks. Whether the parameter is mandatory or not depends on the merchant configuration on Payment Gateway side. |
|  |  |  |  |
| Conditional | `email` | String [1..40] | Email to be displayed on the payment page. Customer's email must be passed if client notification is configured for the merchant. Example: client_mail@email.com. For payment by VISA with 3DS authorization, it is necessary to specify either phone or email of the cardholder. |
|  |  |  |  |
| Optional | `billingPayerData` | Object | A block with the client's registration data (address, postal code) necessary for passing the address verification within the AVS/AVV services. Mandatory if the feature is enabled for the merchant on Payment Gateway side. See nested parameters. |
| Optional | `shippingPayerData` | Object | Object containing customer delivery data. It is used for further 3DS authentication of the client. See nested parameters. |
| Optional | `preOrderPayerData` | Object | Object containing pre-order data. It is used for further 3DS authentication of the client. See nested parameters. |
| Optional | `orderPayerData` | Object | Object containing data about the order payer. It is used for further 3DS authentication of the client. See nested parameters. |
| Optional | `ip` | String [1..39] | Buyer's IP address. IPv6 is supported in all requests. (up to 39 characters).  If you use 3DS 2 authentication, we recommend passing a real IP address of the customer in this parameter. This increases conversion rates on the ACS side. |
|  |  |  |  |
| Optional | `preAuth` | Boolean | Parameter that defines the necessity of a pre-authorization (putting the amount on hold on the customer's account until its debiting). The following values are available:  true - two-phase payments enabled; false - one-phase payments enabled (money are charged right away). If the parameter is missing, one-phase payment is made. |
|  |  |  |  |
| Optional | `autocompletionDate` | String [19] | The date and time when the two-phase payment must be completed automatically in the following format: 2025-12-29T13:02:51. The used timezone is UTC+0. To enable sending this field to the processing system, contact your technical support service. |
|  |  |  |  |
| Optional | `autoReverseDate` | String [19] | The date and time when the two-phase payment must be reversed automatically in the following format: 2025-06-23T13:02:51. The used timezone is UTC+0. To enable sending this field to the processing system, contact your technical support service. |
|  |  |  |  |
| Mandatory | `pan` | String [1..19] | Masked number of the card that has been used for the payment. This parameter is to be specified only after the order has been paid. When paying via Apple Pay, DPAN is used as card number - it is a number linked to customer's mobile device that functions as a payment card number in the Apple Pay system. |
|  |  |  |  |
| Mandatory | `expiry` | Integer [6] | Card expiration in the following format: YYYYMM. Mandatory, if neither seToken nor bindingId is passed. |
|  |  |  |  |
| Mandatory | `cardholder` | String [1..26] | Cardholder's name in Latin characters. This parameter is passed only after an order is paid. |
|  |  |  |  |
| Optional | `cvc` | String [3] | The presence of this parameter is determined by payment type:  cvc is provided not for all tokenized payments; cvc is not provided for MIT payments; cvc is mandatory by default for all other payment types; but if permission Can process payments without confirmation of CVC is enabled, cvc becomes optional in that case.  Only digits are allowed. |

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
| Mandatory | `errorCode` | String [1..2] | Information parameter in case of an error, which may have different code values:  0 value - indicates success of the request processing; another number value (1-99) - indicates an error for more details of which errorMesage parameter must be inspected. It also can be missing if the result has not caused any error.  If the request, associated with an order payment, is processed successfully, it does not directly mean that the payment itself was successful. To determine whether the payment was successful or not, use getOrderStatusExtended.do request. |
|  |  |  |  |
| Mandatory | `errorMessage` | String [1..512] | Information parameter that is an error description in a case of error occurance. errorMessage value can vary, so it should not be hardcoded. Language of the description is set in language parameter of the request. |
|  |  |  |  |
| Mandatory | `mdOrder` | String [1..36] | Order number in the payment gateway. Unique within the payment gateway. |
|  |  |  |  |
| Mandatory | `orderNumber` | String [1..36] | Order number (ID) in the merchant's system, must be unique for each order. |
|  |  |  |  |
| Optional | `userMessage` | String [1..512] | Message to user describing the result code. |

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
--url https://dev.bpcbt.com/payment/rest/motoPayment.do \
--header 'content-type: application/x-www-form-urlencoded' \
  --data amount=2000 \
  --data currency=978 \
  --data userName=test_user \
  --data password=test_user_password \
  --data returnUrl=https://mybestmerchantreturnurl.com \
  --data description=my_first_order \
  --data pan=4000001111111118 \
  --data expiry=203012 \
  --data cvc=123 \
  --data cardholder="TEST CARDHOLDER" \
  --data language=en
```

#### Response example - successful payment

```json
{
   "errorCode":"0",
   "success":true,
   "mdOrder":"088433e9-e34d-769e-9366-696200a7d8c0",
   "orderNumber":"62001"
}
```

