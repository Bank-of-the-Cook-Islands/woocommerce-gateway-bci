# The customer's redirect to the online store page

Once the payment is processed by the Payment gateway, the customer is redirected to the online store page. The URL address the customer is returned to is specified during register.do / registerPreAuth.do API call:

- The customer returns to `returnUrl` (which must be passed during the order registration) in case of successful payment.
- The customer returns to `failUrl` in case of unsuccessful payment.

> Please note that if the payment was unsuccessful and only returnUrl parameter was passed during the request for order registration, the customer will be redirected to the URL specified in returnUrl which will be used as failUrl here.

Finally, the redirect URL looks like as follows:

`https://mybestmerchantreturnurl.com?orderId=98839115-2ce0-7066-99b7-ae7300d72620&lang=en`

where `orderId` and `lang` paramaters values are automatically affixed to `returnUrl` by the Payment gateway.

Moreover, there are two variants this affix may be presented in the redirect URL:

- `?orderId=xxx&lang=xx`
- `&orderId=xxx&lang=xx`

where:

- `orderId` value stands for `mdOrder` value and has string format;
- `lang` value stands for `language` value and has string format.