# Render contract

ReEvents renders semantic HTML with no frontend CSS.

## Blocks

Every widget is also a block under `reevents/<widget>`.

## Styling

ReEvents ships no frontend CSS. Every widget accepts `class` and `wrapper_class`. Those classes are styling hooks owned by the theme or site.

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
| `show_calendar_links` | `true`      | Show add-to-calendar links           |
| `calendar_label`    | `Add to calendar` | Calendar heading                  |
| `google_label`      | `Google`      | Google link                          |
| `outlook_label`     | `Outlook`     | Outlook link                         |
| `office365_label`   | `Office 365`  | Office 365 link                      |
| `ical_label`        | `Apple / iCal` | iCal link                           |
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
| `time_label`        | `Time`               | Timeslot label                       |
| `quantity_label`    | `Seats`              | Seat count label                     |
| `name_label`        | `Name`               | Name label                           |
| `name_placeholder`  | empty                | Name placeholder                     |
| `email_label`       | `Email`              | Email label                          |
| `email_placeholder` | empty                | Email placeholder                    |
| `button_label`      | `Book`               | Submit button                        |
| `success_message`   | `Booking confirmed.` | Success message                      |
| `free_label`        | `Free`               | Free ticket label                    |
| `reference_label`   | `Reference:`         | Booking reference prefix             |

## Common classes

- `reevents-events-list`, `reevents-event`, `reevents-event-title`
- `reevents-event-details`, `reevents-event-when`, `reevents-event-status`
- `reevents-venues-list`, `reevents-venue`
- `reevents-search`, `reevents-search-field`
- `reevents-calendar`, `reevents-calendar-table`, `reevents-calendar-day`
- `reevents-map`
- `reevents-booking-form`, `reevents-booking-field`

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

See [custom booking forms](../api/booking-form.md) for the payload and error format.

## Filters

- `reevents_admin_dropdown_limit` — maximum venues, organizers and categories in admin selects. Default `200`.
- `reevents_venue_dropdown_limit` — maximum venues in the frontend search filter. Default `500`.
- `reevents_client_ip` — override the address used by the rate limiter, for trusted proxies.
- `reevents_register_gateways` — register payment gateways after the built-in mock gateway.
