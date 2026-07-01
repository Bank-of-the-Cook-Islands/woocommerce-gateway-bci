> Source: https://dev.bpcbt.com/en/integration/api/rest.html

# Order management

## Deposit order

To complete a pre-authorized order use `https://dev.bpcbt.com/payment/rest/deposit.do` request.

When sending the request, you should use the header: `content-type: application/x-www-form-urlencoded`

### Request parameters

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `userName` | String [1..100] | Merchant's API account login. |
|  |  |  |  |
| Mandatory | `password` | String [1..30] | Merchant's API account password. |
|  |  |  |  |
| Conditional | `orderId` | String [1..36] | Order number in the payment gateway. Unique within the payment gateway.   Either orderId or orderNumber+merchantLogin must be passed. |
|  |  |  |  |
|  |  |  |  |
| Conditional | `orderNumber` | String [1..36] | Order number (ID) in the merchant's system, must be unique for each order.   Either orderId or orderNumber+merchantLogin must be passed. |
|  |  |  |  |
|  |  |  |  |
| Conditional | `merchantLogin` | String [1..255] | To perform certain action with an order payment on behalf of another merchant, specify the merchant's API account login in this parameter. Can be used only if you have the permission to see the transactions of other merchants or if the specified merchant is your child merchant.   Either orderId or orderNumber+merchantLogin must be passed. |
|  |  |  |  |
|  |  |  |  |
| Mandatory | `amount` | String [0..12] | Deposit amount in minor currency units (e.g. in cents). The deposit amount must match the total of amounts of all deposited items. If you specify amount=0 in the request, the entire amount of the order will be deposited. |
| Optional | `depositItems` | Object | Object containing cart items attributes. Below is the description of the contained attributes. |
|  |  |  |  |
| Optional | `language` | String [2] | ISO 639-1 encoded language key. If the language is not specified, the default language specified in the store settings is used. Supported languages: en,el,ro,bg,pt,sw,hu,it,pl,de,fr,kh,cn,es,ka,da,et,fi,lt,lv,nl,sv. |
|  |  |  |  |
| Optional | `currency` | String [3] | ISO 4217 encoded currency key. If not specified, the default value is used. Only digits are allowed. |
|  |  |  |  |
| Optional | `jsonParams` | Object | A set of additional free-form attributes, structure: jsonParams={"param_1_name":"param_1_value",...,"param_n_name":"param_n_value"} Can be passed to the Processing Center for further processing (additional configuration required - contact support). Some predefined jsonParams attributes:  backToShopUrl - adds a button to the payment page that will return the cardholder to the URL passed in this parameter backToShopName - configures the text label of the Return to Shop button by default, if used together with backToShopUrl installments - maximum number of allowed authorizations for installment payments. Required for creating an installment stored credential totalInstallmentAmount - total amount of all installment payments. The value is necessary for saving payment data for conducting installments recurringFrequency - minimum number of days between authorizations. Required for creating recurring stored credential, recommended for creating installment stored credential (if 3DS2 is used, the parameter is mandatory). recurringExpiry - date after which authorizations are not allowed, in YYYYMMDD format. Required for creating recurring stored credential, recommended for creating installment stored credential (if 3DS2 is used, the parameter is mandatory). paymentWay - payment method. To force MOTO payment, pass the value CARD_MOTO. |
|  |  |  |  |

Description of parameters in `deposititems` object.

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `items` | Object | An element of the array containing cart item attributes. The description of the nested elements is given below. |
|  |  |  |  |

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
  --url https://dev.bpcbt.com/payment/rest/deposit.do \
  --header 'content-type: application/x-www-form-urlencoded' \
  --data userName=test_user \
  --data password=test_user_password \
  --data currency=978\
  --data amount=2000 \
  --data orderId=01492437-d2fb-77fa-8db7-9e2900a7d8c0 \
  --data language=en
```

#### Response example

```json
{
  "errorCode": 0,
  "errorMessage":"Success"
}
```

## Payment reversal

The request used for reversing an order payment is `https://dev.bpcbt.com/payment/rest/reverse.do`.  Reversals can be done only within a specific time frame after the payment. Contact Support to know the exact period, as it varies.

When sending the request, you should use the header: `Content-Type: application/x-www-form-urlencoded`

The payment can be reversed only once. If it ends with an error, then subsequent payment reversal operations will not work.

Availability of this feature is subject to agreement by the Bank. Reversals can be done only by users to whom the appropriate system permissions have been granted.

### Request parameters

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `userName` | String [1..30] | Merchant 's API account login (mandatory, unless token is passed). If you pass your login and password to authenticate in the payment gateway, do not pass token parameter. |
|  |  |  |  |
| Mandatory | `password` | String [1..30] | Merchant's API account password. |
|  |  |  |  |
| Conditional | `orderId` | String [1..36] | Order number in the payment gateway. Unique within the payment gateway.   Either orderId or orderNumber+merchantLogin must be passed. |
|  |  |  |  |
|  |  |  |  |
| Conditional | `orderNumber` | String [1..36] | Order number (ID) in the merchant's system, must be unique for each order.   Either orderId or orderNumber+merchantLogin must be passed. |
|  |  |  |  |
|  |  |  |  |
| Conditional | `merchantLogin` | String [1..255] | To reverse an order payment on behalf of another merchant, specify the merchant's API account login in this parameter. Can be used only if you have the permission to see the transactions of other merchants or if the specified merchant is your child merchant.   Either orderId or orderNumber+merchantLogin must be passed. |
|  |  |  |  |
|  |  |  |  |
| Optional | `language` | String [2] | ISO 639-1 encoded language key. If the language is not specified, the default language specified in the store settings is used. Supported languages: en,el,ro,bg,pt,sw,hu,it,pl,de,fr,kh,cn,es,ka,da,et,fi,lt,lv,nl,sv. |
|  |  |  |  |
| Optional | `jsonParams` | String | Fields for storing additional data, must be passed as follows {"param":"value","param2":"value2"}. |
|  |  |  |  |
| Optional | `amount` | String [0..12] | Reversal amount in minor currency units (e.g. in cents). Reversal amount must be less or equal to the authorized order amount (for two-phase payments - less or equal to the total preauthorized order amount. |
|  |  |  |  |
| Optional | `currency` | String [3] | ISO 4217 encoded currency key. If not specified, the default value is used. Only digits are allowed. |
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
  --url https://dev.bpcbt.com/payment/rest/reverse.do \
  --header 'content-type: application/x-www-form-urlencoded' \
  --data userName=test_user \
  --data password=test_user_password \
  --data currency=978\
  --data orderId=01491d0b-c848-7dd6-a20d-e96900a7d8c0 \
  --data language=en
```

#### Response example

```json
{
  "errorCode": 0,
  "errorMessage":"Success"
}
```

## Refund

Use `https://dev.bpcbt.com/payment/rest/refund.do` to make refund requests.

When sending the request, you should use the header: `Content-Type: application/x-www-form-urlencoded`

You cannot refund orders that initialize recurrent payments, as no money are actually charged.

Upon this request, the funds for the specified order are to be returned to the payer. The request will end with an error if the funds have not been debited for this order. The system permits returning funds more than once, but for a total amount not exceeding the initial debit amount.

### Request parameters

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `userName` | String [1..100] | Merchant's API account login. |
|  |  |  |  |
| Mandatory | `password` | String [1..30] | Merchant's API account password. |
|  |  |  |  |
| Conditional | `orderId` | String [1..36] | Order number in the payment gateway. Unique within the payment gateway.   Either orderId or orderNumber+merchantLogin must be passed. |
|  |  |  |  |
|  |  |  |  |
| Conditional | `orderNumber` | String [1..36] | Order number (ID) in the merchant's system, must be unique for each order.   Either orderId or orderNumber+merchantLogin must be passed. |
|  |  |  |  |
|  |  |  |  |
| Conditional | `merchantLogin` | String [1..255] | To perform certain action with an order payment on behalf of another merchant, specify the merchant's API account login in this parameter. Can be used only if you have the permission to see the transactions of other merchants or if the specified merchant is your child merchant.   Either orderId or orderNumber+merchantLogin must be passed. |
|  |  |  |  |
|  |  |  |  |
| Mandatory | `amount` | String [0..12] | Refund amount in minor currency units (e.g. in cents). If two-phase payment is used, the refund amount must be less or equal to the authorized order amount (for two-phase payments - less or equal to the total deposited order amount. If you specify amount=0 in the request, the entire amount of the order will be refunded. |
|  |  |  |  |
| Optional | `language` | String [2] | ISO 639-1 encoded language key. If the language is not specified, the default language specified in the store settings is used. Supported languages: en,el,ro,bg,pt,sw,hu,it,pl,de,fr,kh,cn,es,ka,da,et,fi,lt,lv,nl,sv. |
|  |  |  |  |
| Optional | `jsonParams` | String | Fields for storing additional data, must be passed as follows {"param":"value","param2":"value2"}. |
|  |  |  |  |
| Optional | `expectedDepositedAmount` | Integer [1..12] | The parameter serves as a determination that the request is repeated. If the parameter is passed, its value is compared to the current depositedAmount value in the order. The operation will be performed only if the values match. If two returns arrive with the same expectedDepositedAmount, only one return will be executed. This return will change the depositedAmount value and then the second return will be rejected. |
|  |  |  |  |
| Optional | `externalRefundId` | String [1..32] | The identifier of the refund. When attempting a refund, externalRefundId is checked: if it exists, a successful response with refund data is returned, if not, a refund is held. |
|  |  |  |  |
| Optional | `currency` | String [3] | ISO 4217 encoded currency key. If not specified, the default value is used. Only digits are allowed. |
|  |  |  |  |
| Optional | `refundItems` | Object | Object containing information about refunded items — the number of an item in the request, the item name, its details, unit of measurement, quantity, currency, article code, and the agent profit. |
|  |  |  |  |

`refundItems` object includes:

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Optional | `items` | Object | An element of the array containing cart item attributes. The description of the nested elements is given below. |
|  |  |  |  |

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

Description of parameters in `itemAttributes` object:

`itemAttributes` parameter must include `attributes` array, where the item attributes should be located (see the example and table below).

```bash
"itemAttributes":{"attributes":[{"name":"paymentMethod","value":"1"},{"name":"paymentObject","value":"1"}]}
```

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `paymentMethod` | [1..2] | Payment type, the available values are:  1 - full prepayment; 2 - partial prepayment; 3 - advance payment; 4 - full payment; 5 - partial payment with further installment payments; 6 - no payment with further installment payments; 7 - payment with further installment payments. |
|  |  |  |  |
| Mandatory |  |  |  |
| Conditional | `nomenclature` | String [1..95] | Product code in hexadecimal notation with spaces. Maximum length – 32 bytes. Mandatory if markQuantity is passed. |
|  |  |  |  |
| Optional | `markQuantity` | Object | Fractional quantity of the marked goods. See nested parameters. |
|  |  |  |  |
| Optional | `userData` | String [1..64] | User property value. May be transferred only after approval by Federal Tax Service. |
|  |  |  |  |
| Optional | `agent_info` | Object | Object with data about payment agent for cart item. The description of the nested elements is given below. |
|  |  |  |  |
| Optional | `supplier_info` | Object | Object with data about supplier for cart item. The description of the nested elements is given below. |
|  |  |  |  |

Description of parameters in `agent_info` object:

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `type` | Integer | Agent type, the available values are:  1 - bank paying agent; 2 - bank paying subagent; 3 - paying agent; 4 - paying subagent; 5 - designated agent; 6 - commission agent; 7 - other agent. |
|  |  |  |  |
| Optional | `paying` | Object | Object with data about payment agent. The description of the nested elements is given below. |
|  |  |  |  |
| Optional | `paymentsOperator` | Object | Object with data about Operator accepting payments. |
|  |  |  |  |
| Optional | `MTOperator` | Object | Object with data about Operator of the transfer. |
|  |  |  |  |

Description of parameters in `paying` object:

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Optional | `operation` | String [1..24] | Name of the transaction of the paying agent. |
|  |  |  |  |
| Optional | `phones` | Array of strings | Phone numbers array of the payments operator in format +N. |
|  |  |  |  |

Description of parameters in `paymentsOperator` object:

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Optional | `phones` | Array of strings | Phone numbers array of the payments operator in format +N. |
|  |  |  |  |

Description of parameters in `MTOperator` object:

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Optional | `phones` | Array of strings | Phone numbers array of the MT operator in format +N. |
|  |  |  |  |
| Optional | `name` | String [1..256] | Name of the transfer operator. |
|  |  |  |  |
| Optional | `address` | String [1..256] | Transfer operator's address. |
|  |  |  |  |
| Optional | `inn` | String [10..12] | ITN of the transfer operator. |
|  |  |  |  |

Description of parameters in `supplier_info` object:

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Optional | `phones` | Array of strings | Supplier's phone number array in format +N. |
|  |  |  |  |
| Optional | `name` | String [1..256] | Supplier's name. |
|  |  |  |  |
| Optional | `inn` | Integer [10..12] | Supplier's ITN |

Description of parameters in `markQuantity` object.

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `numerator` | Integer [1..12] | The numerator of the fractional part of the payment object. |
|  |  |  |  |
| Mandatory | `denominator` | Integer [1..12] | The denominator of the fractional part of the payment object. |

Description of parameters in `quantity` object.

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `value` | Number [1..18] | Number of items in one positionId. Use a decimal point as a separator in fractions. Maximal number of decimal places is 3. |
|  |  |  |  |
| Mandatory | `measure` | String [1..20] | The unit of measurement for the quantity of item instances. |

Possible values of `measure` parameter:

| Value | Description |
| --- | --- |
| 0 | Applied to payment objects that can be implemented individually or in single units as well as if a payment object is an item subject to mandatory identification marking. |
| 10 | Gram |
| 11 | Kilogram |
| 12 | Tonne |
| 20 | Centimeter |
| 21 | Decimeter |
| 22 | Meter |
| 30 | Square centimeter |
| 31 | Square decimeter |
| 32 | Square meter |
| 40 | Milliliter |
| 41 | Liter |
| 42 | Cubic meter |
| 50 | Kilowatt hour |
| 51 | Gigacalorie |
| 70 | Day |
| 71 | Hour |
| 72 | Minute |
| 73 | Second |
| 80 | Kilobyte |
| 81 | Megabyte |
| 82 | Gigabyte |
| 83 | Terabyte |
| 255 | Applied to other measures |

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
  --url https://dev.bpcbt.com/payment/rest/refund.do \
  --header 'content-type: application/x-www-form-urlencoded' \
  --data userName=test_user \
  --data password=test_user_password \
  --data currency=978\
  --data orderId=01491d0b-c848-7dd6-a20d-e96900a7d8c0 \
  --data amount=2000 \
  --data language=en
```

#### Response example

```json
{
  "errorCode": 0,
  "errorMessage":"Success"
}
```

## Instant Refund

Use `https://dev.bpcbt.com/payment/rest/instantRefund.do` request to make a refund for an order. Upon this request, the funds for the order are to be returned to the payer.

When sending the request, you should use the header: `Content-Type: application/x-www-form-urlencoded`

This request can be used only if you have a special permission in Payment Gateway. Contact technical support for details.

### Request parameters

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `userName` | String [1..100] | Merchant's API account login. |
|  |  |  |  |
| Mandatory | `password` | String [1..30] | Merchant's API account password. |
|  |  |  |  |
| Mandatory | `amount` | Integer [0..12] | Refund amount in minor currency units (e.g. in cents). |
|  |  |  |  |
| Optional | `language` | String [2] | ISO 639-1 encoded language key. If the language is not specified, the default language specified in the store settings is used. Supported languages: en,el,ro,bg,pt,sw,hu,it,pl,de,fr,kh,cn,es,ka,da,et,fi,lt,lv,nl,sv. |
|  |  |  |  |
| Optional | `currency` | String [3] | ISO 4217 encoded currency key. If not specified, the default value is used. Only digits are allowed. |
|  |  |  |  |
| Conditional | `orderNumber` | String [1..36] | Order number (ID) in the merchant's system, must be unique for each merchant registered in the Payment Gateway. If the order number is generated on the merchant's side, this parameter is not mandatory. |
|  |  |  |  |
| Optional | `bindingId` | String [1..255] | Identifier of an already existing stored credential. This is the card ID tokenized by the Gateway. Can be used only if the merchant has the permission to work with stored credentials. If this parameter is passed in this request, it means that:  This order can only be paid with a stored credential; The payer will be redirected to a payment page where only CVC entry is required. |
|  |  |  |  |
| Conditional | `seToken` | String [1..8192] | Encrypted card data entered by the client on the merchant's side. Must be passed if used instead of the card data: pan, cvc, expiry, and cardHolderName. Valid combinations of seToken:  timestamp/uuid/PAN/CVV/EXPDATE timestamp/uuid/PAN//EXPDATE timestamp/uuid//CVV///bindingId timestamp/uuid/////bindingId  MdOrder should not be present in seToken. If bindingId is specified, then empty value //bindingId is specified instead of mdOrder. Click here more information about seToken generation. |
|  |  |  |  |
| Conditional | `pan` | String [1..19] | Masked number of the card. Mandatory, if neither seToken nor bindingId is passed. |
|  |  |  |  |
| Conditional | `cvc` | String [3] | CVC/CVV2 code on the back of a payment card. Mandatory if the merchant does not have a permission to pay without CVC. It is also mandatory if neither seToken nor bindingId is passed. Only digits are allowed. |
|  |  |  |  |
| Conditional | `expiry` | Integer [6] | Card expiration in the following format: YYYYMM. Mandatory, if neither seToken nor bindingId is passed. |
|  |  |  |  |
| Conditional | `cardholderName` | String [2..45] | Cardholder's name in Latin characters. Mandatory, if neither seToken nor bindingId is passed. Such special characters as space, full stop, hyphen, apostrophe ( . - ') can be used. The use of other characters is prohibited. |
|  |  |  |  |
| Optional | `jsonParams` | String | Fields for storing additional data, must be passed as follows {"param":"value","param2":"value2"}. |

The request must contain either card data (pan, cvc, expiry, cardholderName), or bindingId, or seToken.

### Response parameters

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Optional | `errorCode` | String [1..2] | Information parameter in case of an error, which may have different code values:  0 value - indicates success of the request processing; another number value (1-99) - indicates an error for more details of which errorMesage parameter must be inspected. It also can be missing if the result has not caused any error.  If the request, associated with an order payment, is processed successfully, it does not directly mean that the payment itself was successful. To determine whether the payment was successful or not, use getOrderStatusExtended.do request. |
|  |  |  |  |
| Optional | `errorMessage` | String [1..512] | Information parameter that is an error description in a case of error occurance. errorMessage value can vary, so it should not be hardcoded. Language of the description is set in language parameter of the request. |
|  |  |  |  |
| Optional | `orderId` | String [1..36] | Order number in the payment gateway. Unique within the payment gateway. |
|  |  |  |  |
| Optional | `orderStatus` | Object | A block with the information about the order status. See nested parameters. |

Below are the parameters of the `orderStatus` block.

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Optional | `approvalCode` | String [6] | IPS authorization code. This field has a fixed length (six symbols) and can contain digits and Latin letters. |
|  |  |  |  |
| Optional | `rrn` | Integer [1..12] | Reference Retrieval Number - transaction ID assigned by Acquiring Bank. |
|  |  |  |  |
| Optional | `errorCode` | String [1..2] | Information parameter in case of an error, which may have different code values:  0 value - indicates success of processing; another number value (1-99) - indicates an error for more details of which errorMesage parameter must be inspected. It also can be missing if the result has not caused any error. |
|  |  |  |  |
| Optional | `errorMessage` | String [1..512] | Information parameter that is an error description in a case of error occurance. errorMessage value can vary, so it should not be hardcoded. Language of the description is set in language parameter of the request. |

### Examples

#### Request example

```bash
curl --request POST \
  --url https://dev.bpcbt.com/payment/rest/instantRefund.do \
  --header 'content-type: application/x-www-form-urlencoded' \
  --data amount=2000 \
  --data userName=test_user \
  --data password=test_user_password \
  --data orderNumber=1218637308 \
  --data pan=4000001111111118 \
  --data cvc=123 \
  --data expiry=203012 \
  --data cardholderName=TEST CARDHOLDER \
  --data language=en
```

#### Response examples - successful instant refund and successful receipt of order status

```json
{
  "errorCode": "0",
  "errorMessage": "Success",
  "orderId": "04899a8a-3bfd-7ceb-ac10-9e6909017350",
  "orderStatus": {
    "ErrorCode": "7",
    "ErrorMessage": "System error",
  }
}
```

#### Response examples - successful instant refund and unsuccessful receipt of order status

```json
{
  "errorCode": "0",
  "errorMessage": "Success",
  "orderId": "04899a8a-3bfd-7ceb-ac10-9e6909017350",
  "orderStatus": {
    "ErrorCode": "7",
    "ErrorMessage": "System error",
  }
}
```

#### Response examples - unsuccessful instant refund (for example, validation error)

```json
{
  "errorCode": "5",
  "errorMessage": "Access denied"
}
```

## Cancel order

To cancel a pending order, use the `https://dev.bpcbt.com/payment/rest/decline.do` request. Only an order that has not been completed can be cancelled. After successful execution of this request, the status of order is changed to `DECLINED`.

When sending the request, you should use the header: `Content-Type: application/x-www-form-urlencoded`

### Request parameters

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `userName` | String [1..100] | Merchant's API account login. |
|  |  |  |  |
| Mandatory | `password` | String [1..30] | Merchant's API account password. |
|  |  |  |  |
| Optional | `merchantLogin` | String [1..255] | To cancel an order on behalf of another merchant, specify the merchant's API account login in this parameter. Can be used only if you have the permission to see the transactions of other merchants or if the specified merchant is your child merchant. |
|  |  |  |  |
| Optional | `language` | String [2] | ISO 639-1 encoded language key. If the language is not specified, the default language specified in the store settings is used. Supported languages: en,el,ro,bg,pt,sw,hu,it,pl,de,fr,kh,cn,es,ka,da,et,fi,lt,lv,nl,sv. |
|  |  |  |  |
| Mandatory | `orderId` | String [1..36] | Order number in the payment gateway. Unique within the payment gateway. |
|  |  |  |  |
| Mandatory | `orderNumber` | String [1..36] | Order number (ID) in the merchant's system, must be unique for each order. |
|  |  |  |  |

### Response parameters

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `errorCode` | String [1..2] | Information parameter in case of an error, which may have different code values:  0 value - indicates success of the request processing; another number value (1-99) - indicates an error for more details of which errorMesage parameter must be inspected. It also can be missing if the result has not caused any error.  If the request, associated with an order payment, is processed successfully, it does not directly mean that the payment itself was successful. To determine whether the payment was successful or not, use getOrderStatusExtended.do request. |
|  |  |  |  |
| Mandatory | `errorMessage` | String [1..512] | Information parameter that is an error description in a case of error occurance. errorMessage value can vary, so it should not be hardcoded. Language of the description is set in language parameter of the request. |
|  |  |  |  |

### Examples

#### Request example

```bash
curl --request POST \
  --url https://dev.bpcbt.com/payment/rest/decline.do \
  --header 'content-type: application/x-www-form-urlencoded' \
  --data userName=test_user \
  --data password=test_user_password \
  --data orderId=8cf0409e-857e-7f95-8ab1-b6810009d884 \
  --data orderNumber=12345678 \
  --data merchantLogin=merch_test418 \
  --data language=en
```

#### Response example

```json
{
  "errorCode": 0,
  "errorMessage":"Success"
}
```

