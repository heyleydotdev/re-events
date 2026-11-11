# Authentication

The plugin API can be disabled under **ReEvents → Settings → API**. When disabled, external requests return `404`; admin screens keep working.

When enabled, public reads (`GET /events`, `/venues`, `/organizers`, `/categories`, `/tickets`) need no authentication unless public access is turned off.

Writes and private reads need one of the methods below.

## ReEvents API keys

Create a key under **ReEvents → Settings → API**. Copy it when it is shown; it cannot be viewed again.

Send it as a bearer token:

```bash
curl -H "Authorization: Bearer re_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" \
  https://example.com/wp-json/re-events/v1/events
```

Or use the dedicated header:

```bash
curl -H "X-ReEvents-Key: re_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" \
  https://example.com/wp-json/re-events/v1/events
```

A key inherits the capabilities of the user who created it. Revoke it from the same settings screen.

## Application Passwords

Use this for external clients, scripts and integrations.

1. Go to **Users → Profile → Application Passwords**.
2. Create a password for the client.
3. Send it with HTTP Basic auth:

```bash
curl -u "editor:xxxx xxxx xxxx xxxx xxxx xxxx" \
  https://example.com/wp-json/re-events/v1/events
```

The username is the WordPress login, not the display name. Application Passwords require HTTPS outside local environments.

## Cookie and nonce

Use this for same-origin JavaScript inside WordPress.

- WordPress sets the login cookies.
- Send the REST nonce in the `X-WP-Nonce` header.
- In the admin or a logged-in page, the nonce is available as `wpApiSettings.nonce`.
- `wp.apiFetch` adds it automatically.

```js
fetch('/wp-json/re-events/v1/events', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'X-WP-Nonce': wpApiSettings.nonce
  },
  body: JSON.stringify({
    title: 'API event',
    start_utc: '2026-12-01 18:00:00'
  })
});
```

## Capabilities

- Read: public for published events, tickets and venues.
- Event writes: `edit_post`, create needs `publish_reevents_events`.
- Venue writes: `edit_post`, create needs `publish_reevents_venues`.
- Organizer writes: `edit_post`, create needs `publish_reevents_organizers`.
- Event categories: `edit_reevents_events`.
- Settings: `reevents_manage_settings`.
- Bookings and attendees: `reevents_manage_bookings`.
