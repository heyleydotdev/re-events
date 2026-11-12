# Emails

ReEvents sends booking mail from the event screen, the booking form and payment webhooks. Templates live under **ReEvents → Settings → Emails**; each event can override the subjects and message bodies.

## What a mail contains

Every message has three parts:

1. The **subject** you edit.
2. The **message** you edit, with placeholders replaced.
3. A generated **booking details** block: event, start and end, time zone, venue and address, ticket, seats, total, reference and status. Admin mail lists every attendee; attendee mail shows that attendee's name and address.

The details block is appended automatically. You do not need to repeat those fields in the message. If a saved template already contains `{event}`, `{reference}` and `{total}`, ReEvents treats it as self-contained and does not append the block.

## Placeholders

| Token | Value |
| --- | --- |
| `{event}` | Event title |
| `{event_url}` | Event permalink |
| `{date}` | Event start, formatted for its time zone |
| `{event_end}` | Event end |
| `{timezone}` | Event time zone |
| `{venue}` | Venue title |
| `{venue_address}` | Venue address |
| `{ticket}` | Ticket name |
| `{reference}` | Booking reference |
| `{name}` | Attendee name |
| `{email}` | Attendee email |
| `{quantity}` | Seats |
| `{total}` | Formatted total, or “Free” |
| `{calendar_url}` | iCal download for the event |
| `{site}` | Site name |
| `{site_url}` | Site URL |
| `{status}` | Status slug (`pending`, `confirmed`, `cancelled`, `rejected`) |
| `{status_label}` | Translated status label |

Use the buttons under the body fields to insert a token at the cursor.

## When mail is sent

| Event | Admin recipients | Attendee |
| --- | --- | --- |
| New booking | Yes | Only when the booking is already confirmed |
| Booking confirmed | No | Yes |
| Awaiting approval (paid, approval required) | No | Yes, once |
| Booking cancelled or refunded | Yes | Yes |
| Booking rejected | No | Yes |

The attendee copy goes to every attendee address on the booking. Attendee copies and cancellation notices are queued through WP-Cron, so a slow mail server does not hold up checkout. The new-booking admin mail is sent immediately.

## Recipients and sender

Under **Sender and recipients**:

- **Admin recipients** — comma-separated addresses. Empty falls back to the site admin email.
- **From name** and **From address** — used only on ReEvents mail. Leave empty for the WordPress defaults.
- **Reply-To** — optional.

SMTP plugins can still override the envelope sender; the headers above are what ReEvents asks for.

## HTML and site-wide email designers

ReEvents sends an HTML email with a plain-text alternative by default. The layout is `templates/emails/layout.php` and can be overridden in the theme at `re-events/emails/layout.php`.

If a known site-wide email designer is active, ReEvents sends plain text and lets that plugin add the header, footer and styling. This avoids two competing templates. The Emails settings tab names the detected plugin.

Detected plugins:

- Email Template Designer (WP HTML Mail)
- PressPilot Email Template Customizer
- Email Templates
- Custom Email Template

Force a format with a filter:

```php
add_filter(
	'reevents_email_format',
	static function (): string {
		return 'html'; // or 'plain'.
	}
);
```

If you force `html` while a designer is active, exclude ReEvents from that designer or expect two envelopes. WP HTML Mail exposes `haet_mail_use_template` for that.

## Testing

**ReEvents → Settings → Emails** has two tools:

- **Send test email** sends the saved template with sample values to one address. Pick an event to test its per-event override.
- **Preview** renders the same mail without sending it, in the current format. The heading names the template and event, and the plain-text version is always available below the HTML preview.

## Per-event overrides

The **Email templates** box on the event screen overrides the admin or attendee subject and body for that event only. Leave a field empty to use the global template. These overrides cover new, confirmed, cancelled and rejected mail, so keep the copy status-neutral or include `{status_label}`. Placeholders and the generated details block work the same way.
