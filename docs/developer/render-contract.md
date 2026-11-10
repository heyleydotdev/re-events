# Render contract

ReEvents renders semantic HTML. It does not require its CSS.

## Blocks

Every widget is also a block under `reevents/<widget>`. The user-area blocks are `reevents/submit-form`, `reevents/my-events` and `reevents/my-bookings`.

## Style mode

Set **Settings → Formats → Frontend styles** to:

- `None` — no plugin CSS. Default.
- `Default` — the bundled stylesheet.

Every widget also accepts `class` and `wrapper_class`. Those classes are added to the form, list or wrapper. They are styling hooks, not layout rules.

Do not rely on inline styles. The plugin does not output them.

## Widgets

### Events list — `[reevents_list]`

| Argument        | Default            | Purpose                    |
| --------------- | ------------------ | -------------------------- |
| `class`         | empty              | Extra class on the list    |
| `wrapper_class` | empty              | Extra class on the wrapper |
| `limit`         | `5`                | Maximum events             |
| `category`      | empty              | Category term ID           |
| `venue`         | `0`                | Venue ID                   |
| `upcoming`      | `true`             | Only upcoming events       |
| `show_venue`    | `true`             | Show the venue name        |
| `empty_label`   | `No events found.` | Empty state                |
| `ongoing_label` | `Ongoing`          | Ongoing badge              |

### Event details — `[reevents_event]`

| Argument            | Default       | Purpose                              |
| ------------------- | ------------- | ------------------------------------ |
| `class`             | empty         | Extra class                          |
| `wrapper_class`     | empty         | Extra wrapper class                  |
| `id`                | `0`           | Event ID. `0` uses the current event |
| `show_venue`        | `true`        | Show the venue                       |
| `show_organizers`   | `true`        | Show organizers                      |
| `show_online_links` | `true`        | Show online links                    |
| `show_content`      | `true`        | Show the description                 |
| `cancelled_label`   | `Cancelled`   | Cancelled badge                      |
| `ongoing_label`     | `Ongoing`     | Ongoing badge                        |
| `venue_label`       | `Venue:`      | Venue prefix                         |
| `organizers_label`  | `Organizers:` | Organizer prefix                     |

### Venues list — `[reevents_venues]`

| Argument           | Default            | Purpose              |
| ------------------ | ------------------ | -------------------- |
| `class`            | empty              | Extra class          |
| `wrapper_class`    | empty              | Extra wrapper class  |
| `limit`            | `10`               | Maximum venues       |
| `show_description` | `true`             | Show the description |
| `empty_label`      | `No venues found.` | Empty state          |

### Events search — `[reevents_search]`

| Argument               | Default          | Purpose                     |
| ---------------------- | ---------------- | --------------------------- |
| `class`                | empty            | Extra class                 |
| `wrapper_class`        | empty            | Extra wrapper class         |
| `limit`                | `20`             | Results per page            |
| `search_label`         | `Search`         | Keyword label               |
| `search_placeholder`   | empty            | Keyword placeholder         |
| `category_label`       | `Category`       | Category label              |
| `venue_label`          | `Venue`          | Venue label                 |
| `from_label`           | `From`           | Start date label            |
| `to_label`             | `To`             | End date label              |
| `upcoming_label`       | `Only upcoming`  | Upcoming toggle label       |
| `submit_label`         | `Search`         | Submit button               |
| `clear_label`          | `Clear`          | Clear link                  |
| `all_categories_label` | `All categories` | Empty category option       |
| `all_venues_label`     | `All venues`     | Empty venue option          |
| `show_keyword`         | `true`           | Show keyword field          |
| `show_category`        | `true`           | Show category field         |
| `show_venue`           | `true`           | Show venue field            |
| `show_dates`           | `true`           | Show date fields            |
| `show_upcoming`        | `true`           | Show upcoming toggle        |
| `show_results`         | `true`           | Show results below the form |

### Calendar — `[reevents_calendar]`

| Argument        | Default                       | Purpose                             |
| --------------- | ----------------------------- | ----------------------------------- |
| `class`         | empty                         | Extra class                         |
| `wrapper_class` | empty                         | Extra wrapper class                 |
| `month`         | empty                         | `Y-m`; empty uses the current month |
| `show_nav`      | `true`                        | Previous and next links             |
| `ongoing_label` | `Ongoing`                     | Ongoing label                       |
| `weekdays`      | `Mon,Tue,Wed,Thu,Fri,Sat,Sun` | Comma-separated weekday headers     |

### Map — `[reevents_map]`

| Argument        | Default             | Purpose                                 |
| --------------- | ------------------- | --------------------------------------- |
| `class`         | empty               | Extra class                             |
| `wrapper_class` | empty               | Extra wrapper class                     |
| `limit`         | `50`                | Maximum venues                          |
| `height`        | `400`               | Height in pixels, applied by JavaScript |
| `empty_label`   | `No mapped venues.` | Empty state                             |

### Booking form — `[reevents_booking]`

| Argument            | Default              | Purpose                              |
| ------------------- | -------------------- | ------------------------------------ |
| `class`             | empty                | Extra class on the form              |
| `wrapper_class`     | empty                | Extra wrapper class                  |
| `event`             | `0`                  | Event ID. `0` uses the current event |
| `ticket_label`      | `Ticket`             | Ticket label                         |
| `quantity_label`    | `Seats`              | Seat count label                     |
| `name_label`        | `Name`               | Name label                           |
| `name_placeholder`  | empty                | Name placeholder                     |
| `email_label`       | `Email`              | Email label                          |
| `email_placeholder` | empty                | Email placeholder                    |
| `button_label`      | `Book`               | Submit button                        |
| `success_message`   | `Booking confirmed.` | Success message                      |
| `free_label`        | `Free`               | Free ticket label                    |
| `reference_label`   | `Reference:`         | Booking reference prefix             |

### Event submission — `[reevents_submit]`

Requires **Settings → Bookings → Event submissions** to be enabled.

| Argument         | Default                      | Purpose                 |
| ---------------- | ---------------------------- | ----------------------- |
| `class`          | empty                        | Extra class on the form |
| `wrapper_class`  | empty                        | Extra wrapper class     |
| `submit_label`   | `Submit event`               | Submit button           |
| `save_label`     | `Save changes`               | Button when editing     |
| `login_message`  | `Log in to submit an event.` | Members-only prompt     |
| `title_label`    | `Event title`                | Title label             |
| `start_label`    | `Starts`                     | Start label             |
| `end_label`      | `Ends`                       | End label               |
| `venue_label`    | `Venue`                      | Venue label             |
| `capacity_label` | `Capacity`                   | Capacity label          |
| `content_label`  | `Description`                | Description label       |
| `excerpt_label`  | `Short summary`              | Excerpt label           |
| `categories_label` | `Categories`               | Categories label        |
| `tags_label`     | `Tags`                       | Tags label              |
| `tags_placeholder` | `Comma-separated`          | Tags placeholder        |
| `organizers_label` | `Organizers`               | Organizers label        |
| `online_links_label` | `Online links`           | Online links label      |
| `all_day_label`  | `All-day event`              | All-day label           |
| `name_label`     | `Your name`                  | Guest name label        |
| `email_label`    | `Your email`                 | Guest email label       |

New submissions are saved as `pending`. Users with `publish_reevents_events` publish immediately. Guests can submit when the setting allows it, but only members can edit their own events. The edit link is `?reevents_edit={id}` on the same page.

### My events — `[reevents_my_events]`

| Argument        | Default                                  | Purpose             |
| --------------- | ---------------------------------------- | ------------------- |
| `class`         | empty                                    | Extra class         |
| `wrapper_class` | empty                                    | Extra wrapper class |
| `limit`         | `20`                                     | Maximum events      |
| `empty_label`   | `You have not submitted any events yet.` | Empty state         |
| `edit_label`    | `Edit`                                   | Edit link text      |

### My bookings — `[reevents_my_bookings]`

| Argument        | Default                     | Purpose             |
| --------------- | --------------------------- | ------------------- |
| `class`         | empty                       | Extra class         |
| `wrapper_class` | empty                       | Extra wrapper class |
| `limit`         | `20`                        | Maximum bookings    |
| `empty_label`   | `You have no bookings yet.` | Empty state         |

## Common classes

- `reevents-events-list`, `reevents-event`, `reevents-event-title`
- `reevents-event-details`, `reevents-event-when`, `reevents-event-status`
- `reevents-venues-list`, `reevents-venue`
- `reevents-search`, `reevents-search-field`
- `reevents-calendar`, `reevents-calendar-table`, `reevents-calendar-day`
- `reevents-map`
- `reevents-booking-form`, `reevents-booking-field`
- `reevents-submit-form`, `reevents-submit-field`
- `reevents-my-events`, `reevents-my-event`
- `reevents-my-bookings`, `reevents-my-booking`

## Data attributes

- `data-reevents-booking` — booking form
- `data-endpoint` — booking submit URL
- `data-reevents-field` — custom attendee field key
- `data-height` — map height
- `data-markers` — map marker JSON

## Theme overrides

Copy any file from `templates/` into `re-events/` in the active theme. The theme file wins.

## Custom forms

Use the booking form schema:

```http
GET /wp-json/re-events/v1/events/{id}/booking-form
```

See [custom booking forms](api/booking-form.md) for the payload and error format.

## Filters

- `reevents_admin_dropdown_limit` — maximum venues, organizers and categories in admin selects. Default `200`.
- `reevents_venue_dropdown_limit` — maximum venues in the frontend search filter. Default `500`.
- `reevents_client_ip` — override the address used by the rate limiter, for trusted proxies.
- `reevents_register_gateways` — register payment gateways after the built-in mock gateway.
