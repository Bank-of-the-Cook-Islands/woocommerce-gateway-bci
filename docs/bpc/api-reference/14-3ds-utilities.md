> Source: https://dev.bpcbt.com/en/integration/api/rest.html

# 3DS utilities

## Finishing a 3DS2 payment via API

The method used for finishing a 3DS2 order via API is `https://dev.bpcbt.com/payment/rest/finish3dsVer2Payment.do`

When sending the request, you should use the header: `Content-type: application/x-www-form-urlencoded`

### Request parameters

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `userName` | String [1..100] | Merchant's API account login. |
|  |  |  |  |
| Mandatory | `password` | String [1..30] | Merchant's API account password. |
|  |  |  |  |
| Mandatory | `threeDSServerTransId` | String [1..36] | Transaction identifier created on 3DS Server. Mandatory for 3DS authentication. |
|  |  |  |  |
| Optional | `threeDSVer2MdOrder` | String [1..36] | Order number which was registered in the first part of the request within 3DS2 transaction. Mandatory for 3DS2 authentication. If this parameter is present in the request, the mdOrder value passed in it overrides, and in this case the order gets paid right away instead of being registered. This parameter is used only for instant payments, i.e., when the order is registered and payed via the same request. |
|  |  |  |  |

### Response parameters

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `errorCode` | String [1..2] | Information parameter in case of an error, which may have different code values:  0 value - indicates success of the request processing; another number value (1-99) - indicates an error for more details of which errorMesage parameter must be inspected. It also can be missing if the result has not caused any error.  If the request, associated with an order payment, is processed successfully, it does not directly mean that the payment itself was successful. To determine whether the payment was successful or not, use getOrderStatusExtended.do request. |
|  |  |  |  |
| Mandatory | `errorMessage` | String [1..512] | Information parameter that is an error description in a case of error occurance. errorMessage value can vary, so it should not be hardcoded. Language of the description is set in language parameter of the request. |
|  |  |  |  |
| Optional | `redirect` | String [1..512] | This parameter is returned if the payment is successful and that payment did not include check for 3-D Secure involvement. Merchants can use it if they want to redirect the user to the payment gateway page. If they have their own response page then this value can be ignored. |
|  |  |  |  |
| Optional | `is3DSVer2` | Boolean | Possible values: true or false. Flag showing that payment uses 3DS2. |

More information about how to know whether the payment was successfull or not is available here.

### Examples

#### Request example

```bash
curl --request POST \
  --url https://dev.bpcbt.com/payment/rest/finish3dsVer2Payment.do \
  --header 'content-type: application/x-www-form-urlencoded' \
  --data threeDSServerTransId=33b17cb5-b4a5-48ac-a3b8-bc8d6d979a46 \
  --data userName=test_user \
  --data password=test_user_password \
```

#### Response example

```json
{
    "redirect": "http://test.com?orderId=f61e2a41-34b9-7a2d-b4d6-83ac00c305c8&lang=en",
    "errorCode": 0,
    "is3DSVer2": true
}
```

#### Request example with threeDSVer2MdOrder parameter

```bash
curl --request POST \
  --url https://dev.bpcbt.com/payment/rest/finish3dsVer2Payment.do \
  --header 'content-type: application/x-www-form-urlencoded' \
  --data threeDSServerTransId=33b17cb5-b4a5-48ac-a3b8-bc8d6d979a46 \
  --data threeDSVer2MdOrder=fbcb596f-25ba-70e7-a6cf-4fb100c305c8 \
  --data userName=test_user \
  --data password=test_user_password \
```

#### Response example

```json
{
    "redirect": "http://test.com?orderId=f61e2a41-34b9-7a2d-b4d6-83ac00c305c8&lang=en",
    "errorCode": 0,
    "is3DSVer2": true
}
```

## Continue payment for 3DS2

To continue payment with 3DS2 authorization, use `https://dev.bpcbt.com/payment/rest/3ds/continue.do` request.

When sending the request, you should use the header: `content-type: application/x-www-form-urlencoded`

If you already use direct integration with 3DS2 scheme, you can use this request instead of sending the repeated payment request as was done before.

### Request parameters

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Conditional | `userName` | String [1..30] | Merchant 's API account login (mandatory, unless token is passed). If you pass your login and password to authenticate in the payment gateway, do not pass token parameter. |
|  |  |  |  |
| Conditional | `password` | String [1..30] | Merchant's API account password (mandatory, unless token is passed). If you pass your login and password to authenticate in the payment gateway, do not pass token parameter. |
|  |  |  |  |
| Conditional | `token` | String [1..256] | Value that is used for merchant authentication when requests are sent to the payment gateway (mandatory, unless userName and password are passed). If you pass this parameter, do not pass userName and password. |
|  |  |  |  |
| Mandatory | `mdOrder` | String [1..36] | Order number in the payment gateway. Unique within the payment gateway. |
|  |  |  |  |

### Response parameters

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
| Conditional | `acsUrl` | String [1..512] | The URL address for redirecting to ACS. It is returned in a successful response in case of a 3D-Secure payment, when redirect to the ACS is needed. For details see Redirect to ACS. |
|  |  |  |  |
| Conditional | `packedCReq` | String | Packed challenge request data. It is returned in a successful response in case of a 3D-Secure payment, when redirect to the ACS is needed. This value should be used as the ACS link creq parameter (acsUrl) to redirect the client to the ACS. For details see Redirect to ACS. |
|  |  |  |  |

### Examples

#### Request example

```bash
curl --request POST \
  --url https://dev.bpcbt.com/payment/rest/3ds/continue.do \
  --header 'Content-Type: application/x-www-form-urlencoded' \
  --data mdOrder=eb708f0a-2683-7437-b458-f80400b40dc0 \
  --data userName=test-user \
  --data password=test-password
```

#### Response example (full 3DS2, success)

```json
{
    "info": "Your order is proceeded, redirecting...",
    "errorCode": 0,
    "acsUrl": "https://bestbank.com/acs2/acs/creq",
    "is3DSVer2": true,
    "packedCReq": "eyJ0aHJlZURTU...6IjA1In0"
}
```

#### Response example (frictionless 3DS2, success)

```json
{
    "redirect": "https://merchant.com/returnUrl?orderId=9666296c-e4f1-7285-a57c-20eb00b40dc1&lang=en",
    "info": "Your order is proceeded, redirecting...",
    "errorCode": 0,
    "is3DSVer2": true
}
```

#### Response example (failure - unknown status in ARes)

```json
{
    "redirect": "https://merchant.com/failUrl?orderId=b69ac21f-6cd3-7e06-931d-d90100b40dc1&lang=en",
    "error": "Error 3-D Secure authorization.",
    "errorCode": 0,
    "is3DSVer2": true,
    "errorTypeName": "TDS_UNKNOWN_ARES_STATUS",
    "processingErrorType": "MANDATORY_3DSECURE",
    "errorMessage": "Error 3-D Secure authorization."
}
```

#### Response example (failure - authorization failed)

```json
{
    "redirect": "https://merchant.com/failUrl?orderId=de056d10-f91d-7c91-a3de-559800b40dc1&lang=en",
    "error": "Operation declined. Please check the data and available balance of the account.",
    "errorCode": 0,
    "is3DSVer2": true,
    "errorTypeName": "DATA_INPUT_ERROR",
    "processingErrorType": "CLIENT_ERROR",
    "errorMessage": "Operation declined. Please check the data and available balance of the account."
}
```

