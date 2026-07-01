> Source: https://dev.bpcbt.com/en/integration/api/rest.html

## Instant payment

The request used to register an order and at the same time carry out the payment for it is `https://dev.bpcbt.com/payment/rest/instantPayment.do`.

When sending the request, you should use the header: `Content-Type: application/x-www-form-urlencoded`

To use this method, you must meet the PCI SAQ D requirements.
If payment without CVC is used, you must meet the PCI SAQ A requirements.

You can use your own 3DS Server for 3D Secure authorization, if this feature is enabled for your by our support team. In this case, the request and response parameters of instantPayment.do will be slightly different. Read more about payment with your 3DS Server here.

If the value of tii parameter is R,U, or F, the request must contain originalPaymentNetRefNum and originalPaymentDate parameters. If these parameters are not passed, the regular payment is performed.

### Request parameters

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Conditional | `userName` | String [1..30] | Merchant 's API account login (mandatory, unless token is passed). If you pass your login and password to authenticate in the payment gateway, do not pass token parameter. |
|  |  |  |  |
| Conditional | `password` | String [1..30] | Merchant's API account password (mandatory, unless token is passed). If you pass your login and password to authenticate in the payment gateway, do not pass token parameter. |
|  |  |  |  |
| Conditional | `token` | String [1..256] | Value that is used for merchant authentication when requests are sent to the payment gateway (mandatory, unless userName and password are passed). If you pass this parameter, do not pass userName and password. |
|  |  |  |  |
| Mandatory | `amount` | Integer [0..12] | Payment amount in minor currency units (e.g. in cents). |
|  |  |  |  |
| Optional | `currency` | String [3] | ISO 4217 encoded currency key. If not specified, the default value is used. Only digits are allowed. |
|  |  |  |  |
| Optional | `clientId` | String [0..255] | Customer number (ID) in the merchant's system — up to 255 characters. Used to implement the functionality of stored-credential transactions. Can be returned in the response if the merchant is allowed to store credentials. Specifying this parameter in stored-credential transactions is mandatory. Otherwise, a payment will be unsuccessful. |
|  |  |  |  |
| Optional | `ip` | String [1..39] | Buyer's IP address. IPv6 is supported in all requests. (up to 39 characters).  If you use 3DS 2 authentication, we recommend passing a real IP address of the customer in this parameter. This increases conversion rates on the ACS side. |
|  |  |  |  |
| Optional | `bindingNotNeeded` | Boolean | Allowed values:  true – storing the credential after the payment is disabled (a stored credntial is a customer identifier passed in order registration request — after instantPayment.do request it will be deleted from order details); false – if payment is successful the credential can be stored (if the necessary conditions are met). This is the default value. |
|  |  |  |  |
| Conditional | `orderNumber` | String [1..36] | Order number (ID) in the merchant's system, must be unique for each merchant registered in the payment gateway . If the Order number is generated on the Payment Gateway side, this parameter is not mandatory. |
|  |  |  |  |
| Optional | `description` | String [1..598] | Order description in any format. To enable sending this field to the processing system, contact the technical support service. It is not allowed to fill this parameter with personal data or payment data (card numbers, etc.). This requirement is due to the fact that the order description is not masked in Merchant Portal and log files. |
|  |  |  |  |
| Optional | `language` | String [2] | ISO 639-1 encoded language key. If the language is not specified, the default language specified in the store settings is used. Supported languages: en,el,ro,bg,pt,sw,hu,it,pl,de,fr,kh,cn,es,ka,da,et,fi,lt,lv,nl,sv. |
|  |  |  |  |
| Optional | `bindingId` | String [1..255] | Identifier of an already existing stored credential. This is the card ID tokenized by the Gateway. Can be used only if the merchant has the permission to work with stored credentials. If this parameter is passed in this request, it means that:  This order can only be paid with a stored credential; The payer will be redirected to a payment page where only CVC entry is required. |
|  |  |  |  |
| Optional | `preAuth` | Boolean | Parameter that defines the necessity of a pre-authorization (putting the amount on hold on the customer's account until its debiting). The following values are available:  true - two-phase payments enabled; false - one-phase payments enabled (money are charged right away). If the parameter is missing, one-phase payment is made. |
|  |  |  |  |
| Optional | `pan` | String [15..19] | Payment card number (mandatory, unless bindinId is passed). pan overrides bindingId. |
|  |  |  |  |
| Optional | `cvc` | String [3] | The presence of this parameter is determined by payment type:  cvc is provided not for all tokenized payments; cvc is not provided for MIT payments; cvc is mandatory by default for all other payment types; but if permission Can process payments without confirmation of CVC is enabled, cvc becomes optional in that case.  Only digits are allowed. |
|  |  |  |  |
| Optional | `cardholderName` | String [2..45] | Cardholder's name in Latin characters. This parameter is passed only after an order is paid. Such special characters as space, full stop, hyphen, apostrophe ( . - ') can be used. The use of other characters is prohibited. |
|  |  |  |  |
| Optional | `merchantLogin` | String [1..255] | To register an order and carry out payment on behalf of another merchant, specify the merchant's API account login in this parameter. Can be used only if you have the permission to see the transactions of other merchants or if the specified merchant is your child merchant. |
|  |  |  |  |
| Optional | `sessionTimeoutSecs` | Integer [1..9] | Order lifetime in seconds. If the parameter is not specified, the value specified in the merchant settings or the default value (1200 seconds = 20 minutes) will be used. If the request contains expirationDate, the value of sessionTimeoutSecs is not taken into account. |
|  |  |  |  |
| Optional | `autocompletionDate` | String [19] | The date and time when the two-phase payment must be completed automatically in the following format: 2025-12-29T13:02:51. The used timezone is UTC+0. To enable sending this field to the processing system, contact your technical support service. |
|  |  |  |  |
| Optional | `autoReverseDate` | String [19] | The date and time when the two-phase payment must be reversed automatically in the following format: 2025-06-23T13:02:51. The used timezone is UTC+0. To enable sending this field to the processing system, contact your technical support service. |
|  |  |  |  |
| Optional | `expirationDate` | String [19] | Date and time of the order expiry. Format used: yyyy-MM-ddTHH:mm:ss. If this parameter is not passed in the request, sessionTimeoutSecs is used to define the expiry of the order. |
|  |  |  |  |
| Conditional | `seToken` | String [1..8192] | Encrypted card data that replaces $PAN, $CVC, and $EXPIRY (or YYYY,MM) parameters. Must be passed if used instead of the card data. The mandatory parameters for seToken string are timestamp, UUID, bindingId(or PAN,EXPDATE). Click here for more information about seToken generation. |
|  |  |  |  |
| Mandatory | `returnUrl` | String [1..512] | The address to which the user will be redirected if the payment is successful. The address must be specified in full including the protocol used (for example, https://mybestmerchantreturnurl.com instead of mybestmerchantreturnurl.com). Otherwise, the user will be redirected to the address of the following type https://dev.bpcbt.com/payment/<merchant_address>.  The address must not be a relative path, i.e. it must not start with "." and "/". Otherwise, error 4 will be returned: "The return URL is invalid". For example: ../test.html, /test.html- invalid URLs. |
|  |  |  |  |
| Optional | `failUrl` | String [1..512] | The address to which the user is to be redirected in case of a failed payment. The address must be specified in full including the protocol used (for example, https://mybestmerchantreturnurl.com instead of mybestmerchantreturnurl.com). Otherwise, the user will be redirected to the address of the following type https://dev.bpcbt.com/payment/<merchant_address>.  The address must not be a relative path, i.e. it must not start with "." and "/". Otherwise, error 4 will be returned: "The return URL is invalid". For example: ../test.html, /test.html- invalid URLs. |
|  |  |  |  |
| Optional | `jsonParams` | Object | A set of additional free-form attributes, structure: jsonParams={"param_1_name":"param_1_value",...,"param_n_name":"param_n_value"}. These fields can be passed to the Processing Center for further processing (additional setup is needed, please contact Support). If you use your own 3DS Server the payment gateway expects that every paymentOrder request will include the following additional parameters such as eci, cavv, xid etc. Please refer here for more information. To initiate 3RI authentication in case when there is no stored credentials, you may need to pass a number of additional parameters (see 3RI authentication for details). Some pre-defined jsonParams attributes:  backToShopUrl - adds checkout page button that will take a cardholder back to the assigned merchant web-site URL backToShopName - customizes default "Back to shop" button text label if used along with backToShopUrl installments - maximum number of allowed authorizations for installment payments. Is required for creating an installment stored credential. totalInstallmentAmount - total sum of all installment payments. Is required for creating an installment stored credential. recurringFrequency - minimum number of days between authorizations. Is required for creating a recurrent or installment stored credential. recurringExpiry - the date after which authorizations are not allowed, in YYYYMMDD format. Recommended for creating a recurrent or installment stored credential (mandatory for 3DS2) |
|  |  |  |  |
| Optional | `features` | String | Features of the order. To specify multiple features, use this parameter several times in one request. As an example, below are the possible values.  VERIFY - If you specify this value in the order registration request, cardholder will be verified however they will not be charged any amount, so in this case amount parameter can be 0. Verification allows to make sure that a payment card is used by its legitimate owner, and further you can charge them without authentication (CVC, 3D-Secure). Even if some amount is passed in the request, the customer will not be charged if VERIFY feature is used. This value can be also used for storing the credential – in this case, the clientId parameter must be passed as well. Read more here. FORCE_TDS - Force 3-D Secure payment. If a payment card does not support 3-D Secure, the transaction will fail. FORCE_SSL - Force SSL payment (without 3-D Secure). FORCE_FULL_TDS - After 3-D Secure authentication, PaRes status must be Y, which guarantees successful user authentication. Otherwise, the transaction will fail. FORCE_CREATE_BINDING - passing this feature in the order registration request forcefully stores the credential. This functionality must be enabled by Merchant level permission in the Gateway. This value cannot be passed in a request with an existing bindingId or bindingNotNeeded = true (will cause validation error). When this feature is passed, the clientId parameter must be passed as well. If you pass both FORCE_CREATE_BINDING and VERIFY features, the order will be created for storing the credential ONLY (without payment). PARTIAL_AUTHORIZATION - partial authorization is available in the order. Read more here. FORCE_PAYMENT_WAY - force payment by the payment method specified in jsonParams in the paymentWay parameter. To process these payments merchant must have sufficient permissions in the payment gateway. This is currently used for force MOTO payments. To enable this, it is necessary to pass also paymentWay parameter with the value CARD_MOTO in jsonParams. |
|  |  |  |  |
| Optional | `orderBundle` | Object | Object containing cart of items. The description of the nested elements is given below. |
| Optional | `dynamicCallbackUrl` | String [1..512] | This parameter allows you to use the functionality of sending callback notifications dynamically. Here you can pass the address to which all "payment" callback notifications activated for the merchant will be sent. "Payment" notifications are callback notifications related to the following events: successful hold, payment declined by timeout, cardpresent payment is declined, successful debit, refund, cancellation. At the same time, callback notifications activated for the merchant that are not related to payments (enabling/disabling a stored credential, storing a credential) will be sent to a static address for callbacks. Whether the parameter is mandatory or not depends on the merchant configuration on Payment Gateway side. |
|  |  |  |  |
| Optional | `threeDSServerTransId` | String [1..36] | Transaction identifier created on 3DS Server. Mandatory for 3DS authentication. |
|  |  |  |  |
| Optional | `threeDSVer2FinishUrl` | String [1..512] | URL where Customer should be redirected after authentication on ACS Server. |
|  |  |  |  |
| Optional | `threeDSMethodNotificationUrl` | String [1..512] | URL where notification about performed 3DS-method should be sent to. |
|  |  |  |  |
| Conditional | `threeDSVer2MdOrder` | String [1..36] | Order number which was registered in the first part of the request within 3DS2 transaction. Mandatory for 3DS2 authentication. If this parameter is present in the request, the mdOrder value passed in it overrides, and in this case the order gets paid right away instead of being registered. This parameter is used only for instant payments, i.e., when the order is registered and payed via the same request. |
|  |  |  |  |
| Optional | `threeDSSDK` | Boolean | Possible values: true or false. Flag showing that payment comes from 3DS SDK. |
|  |  |  |  |
| Optional | `threeDSProtocolVersion` | String | 3DS protocol version. Possible values are "2.1.0", "2.2.0" for 3DS2. If threeDSProtocolVersion is not passed in the request, then the default value will be used for 3D Secure authorization (2.1.0 - for 3DS 2). |
|  |  |  |  |
| Optional | `expiry` | Integer [6] | Card expiration in the following format: YYYYMM. Mandatory, if neither seToken nor bindingId is passed. |
|  |  |  |  |
| Optional | `email` | String [1..40] | Email to be displayed on the payment page. Customer's email must be passed if client notification is configured for the merchant. Example: client_mail@email.com. For payment by VISA with 3DS authorization, it is necessary to specify either phone or email of the cardholder. |
|  |  |  |  |
| Optional | `mcc` | Integer [4] | Merchant Category Code. Using this parameter requires a special permission. You can use only the values from the predefined list of allowed MCC values. Contact the support team for details. |
|  |  |  |  |
| Optional | `mvv` | String [1..10] | Merchant verification value from Mastercard for tokenized transactions. To pass this parameter, a special setting must be enabled (contact technical support). |
|  |  |  |  |
| Optional | `paymentFacilitator` | Object | A block with the parameters of a payment facilitator, i.e. a merchant who allows several submerchants to accept payments under its account. This parameter is used if a special setting is enabled (contact the support team). See nested parameters. |
| Optional | `tii` | String | Transaction initiator indicator. A parameter indicating what type of operation will be carried out by the initiator (Customer or Merchant). Possible values |
|  |  |  |  |
| Conditional | `originalPaymentNetRefNum` | String [1..36] | The identifier of the original or previous successful transaction in the payment system in relation to the performed stored-credential transaction - TRN ID. Is passed when tii = R,U, or F. Is mandatory when using merchant's stored credentials in stored credential transfers. |
|  |  |  |  |
| Conditional | `originalPaymentDate` | String | Date of initiating transaction. The format is Unix timestamp, in milliseconds. Is passed when tii = R,U, or F. |
|  |  |  |  |
| Optional | `externalScaExemptionIndicator` | String | The type of SCA (Strong Customer Authentication) excemption. If this parameter is specified, the transaction will be processed depending on your settings in the payment gateway: either forced SSL operation will be done, or the issuer bank will get the information about SCA excemption and decide to perform operation with or without 3DS authentication (for details, contact our support team). Allowed values:  LVP – Low Value Payments transaction. You can consider a transaction as low risk based on the transaction amount, the client's transactions per day or the client's total daily amount. TRA – Transaction Risk Analysis transaction, i.e., the transaction that has passed successful anti-fraud check.  To pass this parameter, you must have sufficient permissions in the payment gateway. |
|  |  |  |  |
| Optional | `billingPayerData` | Object | A block with the client's registration data (address, postal code) necessary for passing the address verification within the AVS/AVV services. Mandatory if the feature is enabled for the merchant on Payment Gateway side. See nested parameters. |
| Optional | `shippingPayerData` | Object | Object containing customer delivery data. It is used for further 3DS authentication of the client. See nested parameters. |
| Optional | `preOrderPayerData` | Object | Object containing pre-order data. It is used for further 3DS authentication of the client. See nested parameters. |
| Optional | `orderPayerData` | Object | Object containing data about the order payer. It is used for further 3DS authentication of the client. See nested parameters. |
| Optional | `clientBrowserInfo` | Object | A block with the data about the client's browser that is sent to ACS during the 3DS authentication. To pass this block, you should have a special setting (contact the support team). See nested parameters. |
|  |  |  |  |
| Optional | `billingAndShippingAddressMatchIndicator` | String [1] | Indicator for matching the cardholder's billing address and shipping address. This parameter is used for further 3DS authentication of the customer. Possible values:  Y - the cardholder's billing address and shipping address match; N - cardholder billing address and shipping address do not match. |
|  |  |  |  |

Description of parameters in `orderBundle` object:

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Optional | `orderCreationDate` | String [19] | Order creation date in the following format: YYYY-MM-DDTHH:MM:SS. |
|  |  |  |  |
| Optional | `customerDetails` | Object | Block containing customer attributes. The description of the tag attributes is given below. |
| Mandatory | `cartItems` | Object | Object containing cart items attributes. The description of nested elements is given below. |
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

Description of parameters in `itemDetails` object.

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Optional | `itemDetailsParams` | Object | Parameter describing additional information regarding a line item. The description of the nested elements is given below. |

Description of parameters in `itemDetailsParams` object.

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `value` | String [1..2000] | Additional item info. |
|  |  |  |  |
| Mandatory | `name` | String [1..255] | Name of the parameter describing the details of an item |

Description of parameters in `quantity` object.

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `value` | Number [1..18] | Number of items in one positionId. Use a decimal point as a separator in fractions. Maximal number of decimal places is 3. |
|  |  |  |  |
| Mandatory | `measure` | String [1..20] | The unit of measurement for the quantity of item instances. |

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
| Mandatory | `errorCode` | String [1..2] | Information parameter in case of an error, which may have different code values:  0 value - indicates success of processing; another positive number value - indicates an error for more details of which error parameter must be inspected. It also can be missing if the result has not caused any error. |
|  |  |  |  |
| Optional | `error` | String [1..512] | Error message (if response returned an error) in the language passed in the request. |
|  |  |  |  |
| Optional | `orderId` | String [1..36] | Order number in the payment gateway. Unique within the payment gateway. |
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
| Conditional | `orderStatus` | Object | Contains order status parameters and is returned only if the payment gateway has recognized all request parameters as correct. See the description below. |

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

`payerData` element contains the following parameters.

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Optional | `paymentAccountReference` | String [1..29] | The unique account number of the client, which links all their payment means within the IPS (cards and tokens). |
|  |  |  |  |

`orderStatus` block contains the following elements.

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Optional | `ErrorCode` | String [1..2] | Information parameter in case of an error, which may have different code values:  0 value - indicates success of processing; another number value (1-99) - indicates an error for more details of which ErrorMesage parameter must be inspected. It also can be missing if the result has not caused any error. |
|  |  |  |  |
| Optional | `ErrorMessage` | String [1..512] | Information parameter that is an error description in a case of error occurance. ErrorMessage value can vary, so it should not be hardcoded. Language of the description is set in language parameter of the request. |
|  |  |  |  |
| Optional | `OrderNumber` | String [1..36] | Order number (ID) in the merchant's system, must be unique for each merchant. |
|  |  |  |  |
| Optional | `OrderStatus` | Integer | The value of this parameter specifies the status of the order in the payment gateway. It is missing if the order has not been found. Below is the list of available values:  0 - order was registered but not paid; 1 - pre-authorized amount is on hold on the buyer's account (for two-phase payments); 2 - order amount is fully authorized; 3 - authorization canceled; 4 - transaction was refunded; 5 - access control server of the issuing bank initiated authorization procedure; 6 - authorization declined. 7 - pending order payment; 8 - intermediate completion for multiple partial completion. |
|  |  |  |  |
| Optional | `expiration` | Integer [6] | Card expiration date in the following format: YYYYMM. |
|  |  |  |  |
| Optional | `cardholderName` | String [1..26] | Cardholder's name (if available). |
|  |  |  |  |
| Optional | `approvedAmount` | Integer [0..12] | Amount in minimum currency units (e.g. cents) that was put on hold on buyer's account. Used in two-phase payments only. In case of partial authorization, this amount may be less than the order registration amount. |
|  |  |  |  |
| Optional | `depositAmount` | Integer [1..12] | Charged amount in minimum currency units (e.g., in cents). In case of partial authorization, this amount may be less than the order registration amount. |
|  |  |  |  |
| Optional | `currency` | String [3] | ISO 4217 encoded currency key. If not specified, the default value is used. Only digits are allowed. |
|  |  |  |  |
| Optional | `approvalCode` | String [6] | IPS authorization code. This field has a fixed length (six symbols) and can contain digits and Latin letters. |
|  |  |  |  |
| Optional | `authCode` | Integer [6] | Deprecated parameter (not used). Its value is always 2 regardless the order status and authorization code of the processing system. |
|  |  |  |  |
| Optional | `Pan` | String [1..19] | Masked number of the card that has been used for the payment. This parameter is to be specified only after the order has been paid. When paying via Apple Pay, DPAN is used as card number - it is a number linked to customer's mobile device that functions as a payment card number in the Apple Pay system. |
|  |  |  |  |
| Optional | `amount` | Integer [0..12] | Payment amount in minor currency units (e.g. in cents). |
|  |  |  |  |
| Optional | `Ip` | String [1..39] | Buyer's IP address. IPv6 is supported in all requests. (up to 39 characters). |
|  |  |  |  |
| Optional | `originalActionCode` | String [1..15] | Response code received from the processing system. To enable receiving this field, contact the technical support service. |
|  |  |  |  |
| Optional | `rrn` | Integer [1..12] | Reference Retrieval Number - transaction ID assigned by Acquiring Bank. |
|  |  |  |  |
| Optional | `paymentNetRefNum` | String [1..512] | Original Network Reference Number - a unique identifier assigned by the card network (e.g., Mastercard, Visa) to the original transaction (such as a purchase or authorization). When a follow-up transaction is initiated (e.g., refund, chargeback, recurring payment), this number must be included to: Link the new transaction to the original one Ensure proper tracking and reconciliation Meet network compliance requirements |
|  |  |  |  |
| Optional | `tokenizationInfo` | Object | A block with the parameters related to Tokenizer service that allows card tokenization via VTS (Visa Token Service) or MCS (Mastercard Checkout Solutions). This block is returned if a tokenized payment took place. See nested parameters. |

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

- If errorCode <> 0 a transaction should be rejected.
- If errorCode = 0 and OrderStatus is not in (1,2) a transaction should be rejected.
- If errorCode = 0 and OrderStatus = 1 or 2 and acsUrl is null a transaction is approved.
- If errorCode = 0 and OrderStatus = 0 and acsUrl is null a transaction should be rejected.
- If errorCode =0 and OrderStatus = 0 and acsUrl is not null a 3DS flow should be initiated.

More information about how to know whether the payment was successfull or not is available here.

### Examples

#### Request example

```bash
curl --request POST \
--url https://dev.bpcbt.com/payment/rest/instantPayment.do \
--header 'content-type: application/x-www-form-urlencoded' \
--data userName=test_user \
--data password=test_user_password \
--data amount=100 \
--data currency=978 \
--data description=my_first_order \
--data orderNumber=1218637308 \
--data pan=4000001111111118 \
--data cvc=123 \
--data expiry=203012 \
--data cardholderName="TEST CARDHOLDER" \
--data email="demo@example.com" \
--data phone="+449998887766" \
--data language=en \
--data returnUrl=https://mybestmerchantreturnurl.com \
--data failUrl=https://mybestmerchantreturnurl.com
```

#### Response example

```json
{
    "errorCode": "0",
    "orderId": "eee72f6e-b980-79c5-92e8-6f4200b1eae0",
    "info": "Your order is proceeded, redirecting...",
    "redirect": "https://www.test.com/payment/merchants/gateway/finish.html?orderId=eee72f6e-b980-79c5-92e8-6f4200b1eae0&lang=en",
    "orderStatus": {
        "expiration": "202412",
        "cardholderName": "TEST CARDHOLDER",
        "depositAmount": 100,
        "currency": "978",
        "approvalCode": "123456",
        "authCode": 2,
        "originalActionCode": "S1",
        "rrn": "311489272111",
        "ErrorCode": "0",
        "ErrorMessage": "Success",
        "OrderStatus": 2,
        "OrderNumber": "2011",
        "Pan": "500000**1115",
        "Amount": 100,
        "Ip": "x.x.x.x"
    }
}
```

