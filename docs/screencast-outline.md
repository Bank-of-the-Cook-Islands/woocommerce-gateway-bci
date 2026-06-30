# Setup Screencast Outline

Target length: 3 to 5 minutes.

## Recording Flow

1. Open WordPress admin and show Plugins > Installed Plugins with TakuEcom - BCI Payments active.
2. Open WooCommerce > Settings > Payments > TakuEcom - BCI Payments.
3. Show the guided setup panel.
4. Enter sandbox API login and password.
5. Keep Use sandbox credentials and endpoint enabled, explain that sandbox defaults to EUR, and save settings.
6. Show the Sandbox Currency dropdown and explain that it must match the BPC Dev Merchant Portal.
6. Copy the callback URL.
7. Show the merchant portal callback settings to configure Static, POST, Symmetric signing, and required events.
8. Paste the sandbox callback token back into WooCommerce.
9. Run Test connection.
10. Create a sandbox order and choose Card (BCI TakuEcom).
11. Show redirect to the hosted payment page.
12. Complete the sandbox payment.
13. Return to WooCommerce and show the paid order status plus BCI order notes.
14. Show Check Pending Orders and where to find logs under WooCommerce > Status > Logs.
15. For subscriptions, show the readiness test and explain that a sandbox subscription checkout must create a binding ID before live renewals are enabled.

## Narration Points

- Use sandbox credentials and endpoint keeps all checkout payments on the sandbox endpoint and defaults them to EUR.
- Sandbox Currency can be changed only when the same currency is selected in the BPC Dev Merchant Portal.
- Processing is a paid WooCommerce status for orders awaiting fulfilment.
- The callback and background polling protect against customers closing the browser after payment.
- Subscriptions require BCI stored credential and recurrent payment permissions.
- Never share API passwords or callback tokens in support screenshots.
