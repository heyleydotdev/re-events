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
| `sync( $gateway_ref )` | Read the current provider status |

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
accepts JSON webhooks with `gateway_ref` and `status`.
