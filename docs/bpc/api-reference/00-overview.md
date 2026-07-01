> Source: https://dev.bpcbt.com/en/integration/api/rest.html

# Overview

You can use our Merchant API to create a payment flow you need. For example, you can design your own fully customized payment page and connect it to our Payment Gateway.

No API fields passed can contain parts of html, javascript, or other code. The fields can contain text and links only. If this requirement is not met, the system will recognize such cases as a violation, and such requests will be declined.

API backward compatibility is not broken if:
a new optional parameter appears in a request;
a new parameter appears in the response;
new values are added to enum;
the batch order of parameters in the response is changed.

The URLs in the descriptions and examples of API requests below relate to the test environment. For a production environment, the corresponding address of the production environment is used in the request URLs instead of "https://dev.bpcbt.com/payment" (check with the technical support).

You can download Postman collection of some basic API methods below. Make sure to send requests as POST with attributes in the body.

sandbox_eCommerce.postman_collection.json
Download Postman collection

## Mandatory parameters

The mandatory presence of a parameter in a request/response may have the following values:

- Mandatory - the parameter must always be included. If it is not provided, an empty value should be passed, depending on the expected format;
- Optional - the parameter may be included or excluded, and its excessive inclusion will not cause a system error;
- Conditional - the parameter can either be included (be mandatory) or excluded, depending on one or more specified conditions.

The mandatory transmission of a parameter in the request/response description is indicated in the "Required" column.

Home
Full API

create your account

# Authentication

For merchant authentication in the payment gateway two methods can be used.

- Using login and password of the merchant's API user (account with -api postfix) received on registration. These values are passed in userName and password parameters correspondingly (see the table below).

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Conditional | `userName` | String [1..30] | Merchant 's API account login (mandatory, unless token is passed). If you pass your login and password to authenticate in the payment gateway, do not pass token parameter. |
|  |  |  |  |
| Conditional | `password` | String [1..30] | Merchant's API account password (mandatory, unless token is passed). If you pass your login and password to authenticate in the payment gateway, do not pass token parameter. |

- Using a special token - you can request its value in the technical support service. In requests its value is passed in token parameter (see the table below).

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Conditional | `token` | String [1..256] | Value that is used for merchant authentication when requests are sent to the payment gateway (mandatory, unless userName and password are passed). If you pass this parameter, do not pass userName and password. |

# API URLs

TEST: https://dev.bpcbt.com/payment/rest/
PROD: https://dev.bpcbt.com/payment/rest/

# Errors

HTTP status codes:

- 200 - in case of the Payment Gateway API calls the JSON payload from the response must be inspected to determine whether processing was successful or not. Success is indicated by either:
  - the success parameter value being true
  - the errorCode parameter value being 0
  If both parameters are present, success overrides errorCode.
- 400 - an internal error occurred in the system.
- 404 - error while calling API - URL is incorrect (does not exist).
- 429 - this code means that the system is overloaded. Most often the main reason is that the limit of requests per second or limit of simultaneous requests is reached. But it may also be due to the fact that the system as a whole is overloaded (regardless of your requests).
- 500 or 502 - this code means that something went wrong on our side.

If the request, associated with an order payment, is processed successfully, it does not directly mean that the payment itself was successful.

To determine whether the payment was successful or not, you may refer to the description of the request used, where the intepretation of the payment success is thoroughly described, or you may follow the rule of thumb here:

1. Call getOrderStatusExtended.do;
2. Check the orderStatus field in the response: the order is considered to be payed only if the orderStatus value is 1 or 2.

In case the orderStatus value is 1, there is still a need to capture money. Otherwise, the money will be returned to a cardholder in a week or so.

