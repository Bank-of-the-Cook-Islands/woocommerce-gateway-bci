# TakuEcom - BCI Payments Setup Guide

This guide is for WooCommerce store operators configuring BCI TakuEcom payments.

## Before You Start

You need:

- WordPress admin access.
- WooCommerce installed and active.
- The TakuEcom - BCI Payments plugin installed and active.
- Sandbox API login and password from BCI.
- Live API login and password from BCI before accepting real payments.
- Access to the BCI merchant portal callback notification settings.
- HTTPS on the public store URL.

For subscriptions, you also need WooCommerce Subscriptions and BCI stored credential permissions. Subscription renewal support is experimental in v1.0 and disabled by default.

## Step 1: Open The Gateway Settings

1. In WordPress admin, go to WooCommerce > Settings.
2. Select the Payments tab.
3. Open TakuEcom - BCI Payments.
4. Tick Enable TakuEcom - BCI Payments.
5. Keep Test mode enabled while testing.

## Step 2: Enter Sandbox Credentials

In Sandbox Configuration:

1. Enter the Sandbox API Login.
2. Enter the Sandbox API Password.
3. Save changes.
4. Click Test connection.

The connection test confirms that WordPress can reach the selected BCI gateway endpoint with the saved credentials.

If credentials are rejected, check for copied spaces, the correct environment, and whether the sandbox account has API access.

## Step 3: Configure Callback Notifications

The callback keeps WooCommerce in sync even when the customer closes the browser before returning to the store.

1. Copy the Callback URL shown in the plugin settings.
2. Open Callback notifications in the BCI merchant portal.
3. Enable callback notifications.
4. Set Callback type to Static.
5. Set Method to POST.
6. Set Signing type to Symmetric.
7. Paste the plugin callback URL into the callback link field.
8. Generate a callback token.
9. Paste that token into the matching Sandbox Callback Token or Live Callback Token field in WooCommerce.
10. Enable Deposited, Approved, Reversed, Refunded, and Declined by timeout events.
11. Save the merchant portal settings and the WooCommerce gateway settings.

The callback URL is:

```text
/wp-json/bci-woo/v1/callback
```

The full URL shown in WooCommerce includes your store domain.

## Step 4: Choose Paid Order Status

The default setting is WooCommerce default.

For most WooCommerce stores this means:

- Processing for paid orders that still need fulfilment.
- Completed for virtual or downloadable orders that need no fulfilment.

If store staff find Processing unclear, choose Force Completed. If every paid order should remain in a fulfilment queue, choose Force Processing.

## Step 5: Test A Sandbox Order

1. Make sure Test mode is enabled.
2. Add a product to the cart.
3. Go through checkout using Card (BCI TakuEcom).
4. Complete the hosted payment page using sandbox test card details supplied by BCI.
5. Return to the WooCommerce order.
6. Confirm the order includes a BCI gateway reference and reaches the expected paid status.

If the order stays Pending:

1. Click Check Pending Orders in the gateway settings.
2. Open WooCommerce > Status > Logs.
3. Select a log source beginning with BCI_Woo_Plugin.
4. Check whether the gateway returned a pending, failed, or captured status.

## Step 6: Configure Live Payments

Only switch to live after sandbox testing succeeds.

1. Enter Live API Login and Live API Password.
2. Configure live callback notifications in the live merchant portal.
3. Paste the live callback token into WooCommerce.
4. Save settings.
5. Run the live connection test.
6. Untick Test mode.
7. Place a small live test order if BCI support has approved that process.

## Subscriptions

Subscriptions are experimental in v1.0 and disabled by default. The core v1.0 release is for one-off hosted checkout payments. Enable renewals only after WooCommerce Subscriptions is installed and BCI confirms stored credential permissions for the merchant account.

Before testing renewals, ask BCI support to confirm these permissions on the sandbox account:

- Stored credentials.
- `FORCE_CREATE_BINDING`.
- `recurrentPayment.do`.
- Merchant-initiated transaction permission if required by the processor.

Then:

1. Keep Test mode enabled.
2. Enable automatic renewals in the Subscriptions section only after the merchant-specific recurring-payment checklist has passed.
3. Click Test subscription readiness.
4. Create a sandbox subscription product.
5. Complete an initial subscription checkout.
6. Confirm the order note does not report a missing binding ID.
7. Trigger or wait for a renewal order and confirm it is paid through BCI.

Do not enable live subscription renewals until a sandbox subscription checkout creates a binding ID successfully.

## Troubleshooting

Connection test fails:

- Confirm the correct live or sandbox credentials.
- Confirm outbound HTTPS requests are allowed from WordPress hosting.
- Confirm BCI has enabled API access.

Callback fails:

- Confirm the store uses a public HTTPS URL.
- Confirm the callback token in WooCommerce matches the merchant portal token.
- Confirm Signing type is Symmetric.
- Confirm the callback URL has no redirects or maintenance-mode protection.

Orders stay Pending:

- Use Check Pending Orders.
- Review WooCommerce logs with source `BCI_Woo_Plugin`.
- Confirm the order has a stored BCI gateway reference.

Subscriptions fail:

- Confirm WooCommerce Subscriptions is active.
- Confirm the initial subscription checkout created a binding ID.
- Confirm BCI enabled stored credential and recurrent payment permissions.
- Review the renewal order notes and BCI logs.

## Support Information To Collect

When asking for support, include:

- Store URL.
- WooCommerce order number.
- Whether Test mode is enabled.
- Approximate payment time.
- Screenshot or copied text from the order notes.
- Relevant `BCI_Woo_Plugin` log entries with passwords and callback tokens removed.
