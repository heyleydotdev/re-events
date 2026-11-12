# Statistics

Open **ReEvents → Statistics** to see sales and booking totals for one currency. The default range is the last 30 days; choose **All time** to show the full history.

## Metrics

| Metric | Meaning |
| --- | --- |
| Total Sales | Net value of confirmed bookings, after partial refunds |
| Total Tickets | Sum of quantities on confirmed bookings |
| Total Bookings | Number of confirmed bookings |
| Average Sale | Total Sales ÷ Total Bookings |
| Average Ticket | Total Sales ÷ Total Tickets |
| Tickets / Booking | Total Tickets ÷ Total Bookings |

## Rules

- Only confirmed bookings are counted. Pending, cancelled and rejected bookings are excluded.
- Free confirmed bookings count toward tickets and bookings with zero sales.
- Tickets means the booked quantity, not ticket types or attendee rows.
- Full refunds cancel the booking, so they are excluded. Partial refunds reduce sales.
- Dates use the booking creation time in the site time zone.
- Each currency is reported separately. Summing different currencies is not supported.
- The monthly chart covers the last 12 months in the selected range.

## Data

The page runs aggregate queries against the bookings and payments tables. It does not load bookings one by one.

The payments table stores `refunded_minor`, so partial refunds are deducted without reading JSON payloads. The bookings table has a `(status, currency, created_utc)` index for filtered totals.
