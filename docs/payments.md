# Payments

Set **Settings → Payments → Gateway** to `Stripe`, `PayPal` or `None`.

Paid bookings stay `pending` until the provider confirms the payment. Unpaid bookings are cancelled after the hold window.

## Stripe

1. Choose `Stripe` as the gateway.
2. Add the **secret key** from the Stripe dashboard.
3. Create a webhook endpoint for:
   ```text
   /wp-json/re-events/v1/webhooks/stripe
   ```
4. Copy its **signing secret** into the Stripe webhook secret field.
5. Optionally set the success and cancel URLs. Both default to the home page.

Stripe uses hosted Checkout, so card data and SCA stay off-site.

## PayPal

1. Choose `PayPal` as the gateway.
2. Pick `Sandbox` or `Live`.
3. Add the REST app **client ID** and **client secret**.
4. Create a webhook endpoint for:
   ```text
   /wp-json/re-events/v1/webhooks/paypal
   ```
5. Copy the **webhook ID** into the PayPal webhook ID field.
6. Optionally set the success and cancel URLs.

PayPal Orders are captured when the buyer returns or when the capture webhook arrives.

## Test a payment

1. Use sandbox or test credentials.
2. Buy a paid ticket and complete checkout.
3. Confirm the booking becomes `confirmed`.
4. Refund the payment and confirm the booking is cancelled.
5. Replay the webhook and confirm the status does not change twice.

## Currency

Amounts are stored in the smallest currency unit. Zero-decimal currencies such as JPY are not divided by 100. Three-decimal currencies such as KWD keep three decimals for display and are rounded to Stripe's required minor unit at checkout.
