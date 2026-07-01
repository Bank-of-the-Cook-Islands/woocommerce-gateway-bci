# Redirect integration
Redirect integration is a simple and secure process that uses the payment page on the payment gateway side. The main advantage of this integration method is that you don't need to collect and process card data on your website. The API is used minimally in this case, so this method does not require much development expertise.

1. A customer selects a product in the online store, and then clicks Buy.

2. The online store server receives a purchase request.

3. The online store server requests an order registration by sending the register.do API call to the payment gateway. This request must contain the `amount` parameter (the payment amount in minor currency units) and the `returnUrl` parameter (the address to which the customer will be redirected after successfull payment in step 9). Read more about redirect after payment [here](redirect-after-payment.md).

Request example:

```
curl --request POST \
--url https://dev.bpcbt.com/payment/rest/register.do \
--header 'content-type: application/x-www-form-urlencoded' \
--data amount=2000 \
--data currency=978 \
--data userName=test_user \
--data password=test_user_password \
--data returnUrl=https://mybestmerchantreturnurl.com \
--data description=my_first_order \
--data language=en
```

Alternatively, you can hold the amount on account before the charge by using the registerPreAuth.do call. For more details about hold and capture, click here.

4. The payment gateway server registers an order and sends a response to the online store server. The response contains the `formUrl` parameter (the payment URL to which the online store should redirect the customer in step 5) and the `orderId` parameter (the unique order number in the payment gateway system, will be used in step 10).

```
{
"orderId": "01491d0b-c848-7dd6-a20d-e96900a7d8c0",
"formUrl": "https://dev.bpcbt.com/payment/merchants/payment_en.html?mdOrder=01491d0b-c848-7dd6-a20d-e96900a7d8c0"
}
```

5. The online store redirects the customer to the URL received in the formUrl parameter. The redirection may be done in the same window or in a new window.

6. The payment gateway opens the payment URL.

7. The customer enters his or her card number, expiration date, and CVV/CVC, and clicks Pay.

Alternatively, it is possible to use tokenized payments via Apple Pay, Samsung Pay, or Google Pay wallets. In this case the customer selects the corresponding option. Read more about using tokenized payments here.

8. The payment gateway processes the payment request.

9. The customer is redirected to the online store page defined in the returnUrl parameter (specified in step 3).

10. The online store sends the getOrderStatusExtended.do request to the payment gateway to check the order status and make sure the order is really paid. The request contains the orderId parameter received in step 4. In response, the payment gateway returns the order status in the orderStatus parameter. Status 2 means a succesfull payment. Additionally, the actionCode parameter is returned - it contains the response code from the processing bank. See the list of response codes [here](action-codes.md). Find more details in the Getting the order status section.

---

If the customer has not returned from the payment page to the payment confirmation page in 20 minutes (a standard time for payment), the payment is considered unsuccessful.

The delay of returning to the payment confirmation page may be also caused by the Internet problems, so it is recommended to check the order status from time to time of to wait for a callback notification on change of the payment status.

Please note that PCI DSS compliance may be required for using redirect integration. Read more about PCI DSS here.