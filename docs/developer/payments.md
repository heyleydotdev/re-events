# Payments

ReEvents ships a gateway interface, a registry and a mock gateway. A gateway
turns a booking total into a provider payment and maps webhook events back to
payment statuses.

## Gateway contract

Implement `ReEvents\Services\Gateways\Gateway`:

| Method | Purpose |
|---|---|
| `id()` | Stable gateway ID stored on payments |
| `create_payment( $payment, $booking )` | Return `gateway_ref`, `status`, `redirect_url` and `data` |
| `verify_webhook( $payload, $headers )` | Verify the signature and return `gateway_ref` plus `status` |
| `refund( $gateway_ref, $amount_minor )` | Refund all or part of a payment |
| `sync( $gateway_ref, $read_only = false )` | Read the current provider status; `$read_only` skips capture or completion |

Register a gateway on the `reevents_register_gateways` action:

```php
add_action(
	'reevents_register_gateways',
	static function (): void {
		Gateway_Registry::register( new My_Gateway() );
	}
);
```

## Statuses

| Status | Effect |
|---|---|
| `pending` | Payment recorded, booking unchanged |
| `succeeded` | Booking confirmed |
| `failed` | Payment marked failed |
| `refunded` | Booking cancelled |

## Stripe

Set **Settings → Payments → Gateway** to `Stripe` and fill in:

- **Secret key** — `sk_...` from the Stripe dashboard.
- **Webhook secret** — `whsec_...` of the endpoint below.
- **Success URL** and **Cancel URL** — optional; both default to the home page with `reevents_payment` and `booking` query args.

Stripe uses hosted Checkout Sessions, so card data and SCA never touch the
site. Amounts are sent in the smallest currency unit exactly as stored in
`amount_minor`.

### Checkout flow

1. A booking with a total above zero is created as `pending`.
2. `Payment_Service::checkout()` starts the configured gateway, or reuses a pending payment that already has a redirect URL.
3. The REST `201` response and the no-JS form both send the visitor to the provider.
4. The success and cancel URLs carry `reevents_payment`, `booking` and `token`; the token is the booking reference and must match.
5. On return the plugin syncs the payment, confirms or cancels the booking and redirects to the event with a booking result flag.

A pending booking is cancelled after the hold window set in **Settings → Bookings**.

### Webhook endpoint

```http
POST /wp-json/re-events/v1/webhooks/stripe
```

Subscribe to `checkout.session.completed`, `checkout.session.expired` and
`checkout.session.async_payment_failed`. The endpoint verifies the
`Stripe-Signature` header with a five-minute tolerance and rejects unsigned
requests. Webhook requests bypass the public API switch and rate limiter.

## Mock gateway

`Mock` is for local checkout tests. It returns a `mock_...` reference and
accepts JSON webhooks with `gateway_ref` and `status` only when the
`RE_EVENTS_TESTING` constant is defined. Keep it off in production.

## PayPal

Set **Settings → Payments → Gateway** to `PayPal` and fill in:

- **Mode** — `Sandbox` or `Live`.
- **Client ID** and **Client secret** — REST app credentials.
- **Webhook ID** — ID of the webhook endpoint below.
- **Success URL** and **Cancel URL** — optional; both default to the home page.

PayPal uses Orders v2. An approved order is captured when the visitor returns
or when the webhook arrives. Amounts are converted from `amount_minor` to the
currency's decimal form, so zero-decimal currencies such as JPY are sent as
whole units.

Webhook endpoint:

```http
POST /wp-json/re-events/v1/webhooks/paypal
```

Subscribe to `PAYMENT.CAPTURE.COMPLETED`, `PAYMENT.CAPTURE.DENIED`,
`PAYMENT.CAPTURE.REFUNDED` and `CHECKOUT.ORDER.APPROVED`. Every webhook is
verified with PayPal's `verify-webhook-signature` endpoint before it is
applied.

## Currency decimals

Amounts are always stored in the smallest currency unit. `Currency::amount()`
and `Currency::format()` apply the correct number of decimals, so JPY, KRW,
VND and the other zero-decimal currencies are not divided by 100. Three-decimal
currencies such as KWD, BHD and JOD keep three decimals for display and are
rounded to Stripe's required two-decimal minor unit at checkout. PayPal receives
the three-decimal value.

## Sandbox checklist

**Stripe**

1. Set the gateway to `Stripe` and add a test secret key.
2. Create a webhook endpoint for `/wp-json/re-events/v1/webhooks/stripe` and copy its signing secret.
3. Buy a paid ticket and confirm the redirect to Checkout.
4. Pay with `4242 4242 4242 4242` and confirm the booking becomes `confirmed`.
5. Replay the webhook and confirm the status does not change twice.
6. Refund the payment from the dashboard and confirm the booking is cancelled.
7. Wait past the hold window and confirm an abandoned checkout releases its seats.

**PayPal**

1. Set the mode to `Sandbox` and add sandbox REST credentials.
2. Create a webhook endpoint for `/wp-json/re-events/v1/webhooks/paypal` and copy its webhook ID.
3. Buy a paid ticket and approve the order with a sandbox buyer account.
4. Confirm the return captures the order and confirms the booking.
5. Refund the capture and confirm the booking is cancelled.
6. Replay a webhook and confirm the status does not change twice.
