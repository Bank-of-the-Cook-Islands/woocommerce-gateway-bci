> Source: https://dev.bpcbt.com/en/integration/api/rest.html

## Redirect to ACS (simplified)

If 3-D Secure is required, then, after receiving payment response, the customer must be redirected to ACS. In this case, the payment response contains the `acsUrl` parameter that will be used for the redirect.

The `https://dev.bpcbt.com/payment/acsRedirect.do?`orderId`={orderId}` request allows to redirect a customer to the ACS authentication page in a simplified way - just using orderId parameter received after an order registration.

It is also possible to redirect a customer to ACS with a POST request (regular redirect). The description of this method can be found here.

Without other actions required from customer, the payment gateway redirects them to the ACS page, where customer authenticates.

Then, depending on the authentication result, the customer is redirected to the following URL:

- if authentication succeeds - returnUrl with appended orderId parameter;
- if authentication fails - failUrl (or returnUrl if failUrl was not passed) with appended orderID parameter.

To redirect a customer to the ACS, use the following URL:

`https://dev.bpcbt.com/payment/acsRedirect.do?orderId={Order number in the payment gateway}`

### Request parameters

| Required | Name | Type | Description |
| --- | --- | --- | --- |
| Mandatory | `orderId` | String [1..36] | Order number in the payment gateway. Unique within the payment gateway. |

### Response parameters

### Example

#### Request example

`curl -X GET https://dev.bpcbt.com/payment/acsRedirect.do?orderId=85eb9a84-2a47-7cca-b0ae-662c000016d1`

#### Redirect URL example

`https://mybestmerchantreturnurl.com/?orderId=85eb9a84-2a47-7cca-b0ae-662c000016d1`

