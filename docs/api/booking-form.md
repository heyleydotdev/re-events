# Custom booking forms

ReEvents can render the booking form, or you can build your own HTML and submit to the API.

## Get the form schema

```http
GET /wp-json/re-events/v1/events/{event_id}/booking-form
```

The response contains:

- `event_id` and `event_title`
- `tickets`: ID, name, price, currency, capacity, sold, available, min/max and sale window
- `fields`: custom attendee fields with key, label, type and required
- `submit_url`
- `honeypot`: the field name that must stay empty
- `token_field`: the idempotency field name
- `nonce`: a REST nonce for logged-in requests, or an empty string

Public events need no authentication. Logged-in requests must send the nonce in `X-WP-Nonce`.

## Submit a booking

```http
POST /wp-json/re-events/v1/events/{event_id}/bookings
Content-Type: application/json
```

```json
{
  "ticket_id": 12,
  "quantity": 2,
  "booking_token": "unique-per-form-load",
  "website": "",
  "attendees": [
    {
      "name": "Jane Doe",
      "email": "jane@example.com",
      "fields": {
        "company": "Acme"
      }
    }
  ]
}
```

`quantity` is the number of seats. `attendees` cannot contain more rows than seats. A booking can use one lead attendee for multiple seats.

## PHP helpers

Plugins and themes can create and manage bookings with the [Public API](public-api.md):

```php
$booking = reevents_create_booking(
	[
		'event_id'      => $event_id,
		'ticket_id'     => $ticket_id,
		'quantity'      => 1,
		'attendees'     => [
			[
				'name'  => 'Jane Doe',
				'email' => 'jane@example.com',
			],
		],
		'booking_token' => wp_generate_uuid4(),
	]
);
```

The same API can read events, tickets and venues when building a custom form.

## JavaScript example

```js
async function submitBooking( form ) {
  const data = new FormData( form );
  const token = crypto.randomUUID();

  const response = await fetch( form.dataset.endpoint, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify( {
      ticket_id: Number( data.get( 'ticket_id' ) ),
      quantity: Number( data.get( 'quantity' ) ),
      booking_token: token,
      website: String( data.get( 'website' ) || '' ),
      attendees: [ {
        name: String( data.get( 'name' ) || '' ),
        email: String( data.get( 'email' ) || '' ),
        fields: { company: String( data.get( 'company' ) || '' ) }
      } ]
    } )
  } );

  const body = await response.json();

  if ( response.ok ) {
    return body;
  }

  const errors = body?.data?.errors || {};
  throw Object.assign( new Error( body?.message || 'Booking failed.' ), { errors } );
}
```

## Errors

Validation errors use the standard WordPress REST error envelope:

```json
{
  "code": "rest_booking_invalid",
  "message": "Please correct the booking fields.",
  "data": {
    "status": 400,
    "errors": {
      "attendees.0.email": "Email must be a valid email address."
    }
  }
}
```

Common status codes:

- `201` created
- `400` validation or sale-window error
- `404` event or ticket not found
- `409` sold out
- `429` rate limited

## Idempotency

Generate `booking_token` once per form load, not once per click. Reuse it when retrying the same submission. Reusing a token returns the existing booking instead of creating a duplicate.

## Payment

When a payment gateway is configured and the booking total is above zero, the `201` response also contains `checkout_url`:

```json
{
  "id": 42,
  "reference": "...",
  "status": "pending",
  "total_minor": 5000,
  "currency": "USD",
  "checkout_url": "https://checkout.stripe.com/c/pay/..."
}
```

Redirect the browser to `checkout_url`. The booking stays `pending` until the gateway confirms the payment. If the payment cannot be started, the booking is cancelled and the gateway error is returned instead of a `201`.

Returning from the gateway lands on the configured success or cancel URL with `reevents_payment`, `booking` and `token` query args. The plugin verifies the token, syncs the payment and redirects to the event with a booking result flag.

## Styling

The schema endpoint returns data only. Your form owns all markup and CSS.
