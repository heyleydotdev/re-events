# Render contract

ReEvents renders semantic HTML with no frontend CSS.

## Blocks

Every widget is also a block under `reevents/<widget>`. `reevents/field` prints one event, venue or organizer value, and its field dropdown is filled from the PHP field lists.

Date-sensitive blocks expose date and time format fields in the inspector. Leave them empty to use the site format. The calendar has a separate month format field.

## Styling

ReEvents ships no frontend CSS. Every widget accepts `class` and `wrapper_class`. Those classes are styling hooks owned by the theme or site.

Do not rely on inline styles. The plugin does not output them.

## Widgets

### Events list — `[reevents_events]`

| Argument        | Default            | Purpose                    |
| --------------- | ------------------ | -------------------------- |
| `class`         | empty              | Extra class on the list    |
| `wrapper_class` | empty              | Extra class on the wrapper |
| `limit`         | `5`                | Maximum events, up to 100  |
| `search`        | empty              | Keyword search             |
| `category`      | empty              | Category term ID, or `current` on a category page |
| `tag`           | empty              | Tag term ID or slug        |
| `venue`         | `0`                | Venue ID, or `current` on a venue page |
| `organizer`     | `0`                | Organizer ID, or `current` on an organizer page |
| `from`          | empty              | Earliest start date, `Y-m-d` |
| `to`            | empty              | Latest end date, `Y-m-d`   |
| `order`         | `asc`              | Start date order: `asc` or `desc` |
| `upcoming`      | `true`               | Only upcoming events       |
| `use_query`     | `false`              | Read the event search form values from the URL |
| `show_venue`    | `true`               | Show the venue name        |
| `page`          | `0`                  | Page number; 0 uses the current page |
| `paginate`      | `true`               | Show pagination links. Off for sidebar and teaser lists |
| `pagination_key` | empty              | Suffix for the page URL argument, for example `main` |
| `show_image`    | `true`               | Show the featured image    |
| `image_size`    | `thumbnail`          | Registered image size      |
| `show_price`    | `true`               | Lowest price or free label |
| `free_label`    | `Free`               | Price label for free events |
| `sold_out_label` | `Sold out`          | Label when no seats are left |
| `show_seats_left` | `false`            | Remaining seat count       |
| `show_excerpt`  | `false`              | Show the event excerpt     |
| `excerpt_length` | `0`                 | Excerpt word count; 0 uses the theme length |
| `show_categories` | `false`            | Show event categories      |
| `show_tags`     | `false`              | Show event tags            |
| `show_organizers` | `false`            | Show organizer names       |
| `show_end`      | `false`              | Show the end date and time |
| `show_cancelled` | `false`             | Include cancelled events, labelled on the card |
| `cancelled_label` | `Cancelled`        | Badge on cancelled events  |
| `title_tag`     | `a`                  | Title markup: `a`, `h2`, `h3` or `div` |
| `link`          | `true`               | Link the title and image to the event |
| `empty_label`   | `No events found.`   | Empty state                |
| `ongoing_label` | `Ongoing`            | Ongoing badge              |

### Event details — `[reevents_event]`

| Argument            | Default       | Purpose                              |
| ------------------- | ------------- | ------------------------------------ |
| `class`             | empty         | Extra class                          |
| `wrapper_class`     | empty         | Extra wrapper class                  |
| `id`                | `0`           | Event ID. `0` uses the current event |
| `date_format`       | site format   | Date format                          |
| `time_format`       | site format   | Time format                          |
| `show_image`        | `false`       | Featured image                       |
| `image_size`        | `large`       | Registered image size                |
| `show_title`        | `true`        | Show the title                       |
| `title_tag`         | `h2`          | Title markup: `h1`, `h2`, `h3` or `div` |
| `show_status`       | `true`        | Cancelled or ongoing badge           |
| `show_price`        | `false`       | Lowest price or free label           |
| `show_seats_left`   | `false`       | Remaining seats                      |
| `show_categories`   | `false`       | Event categories                     |
| `show_tags`         | `false`       | Event tags                           |
| `show_excerpt`      | `false`       | Event excerpt                        |
| `show_end`          | `true`        | End date and time                    |
| `show_venue`        | `true`        | Show the venue                       |
| `show_venue_address` | `false`      | Venue address                        |
| `show_venue_map_link` | `false`     | Venue map link                       |
| `show_venue_map`    | `false`       | Embed the venue map                  |
| `venue_map_height`  | `320`         | Embedded map height in pixels        |
| `show_organizers`   | `true`        | Show organizers                      |
| `show_online_links` | `true`        | Show online links                    |
| `show_calendar_links` | `true`      | Show add-to-calendar links           |
| `show_content`      | `true`        | Show the description                 |
| `calendar_label`    | `Add to calendar` | Calendar heading                  |
| `google_label`      | `Google`      | Google link                          |
| `outlook_label`     | `Outlook`     | Outlook link                         |
| `office365_label`   | `Office 365`  | Office 365 link                      |
| `ical_label`        | `Apple / iCal` | iCal link                           |
| `cancelled_label`   | `Cancelled`   | Cancelled badge                      |
| `ongoing_label`     | `Ongoing`     | Ongoing badge                        |
| `venue_label`       | `Venue:`      | Venue prefix                         |
| `organizers_label`  | `Organizers:` | Organizer prefix                     |
| `free_label`        | `Free`        | Free price label                     |
| `sold_out_label`    | `Sold out`    | Sold-out label                       |

### Venues list — `[reevents_venues]`

| Argument           | Default            | Purpose              |
| ------------------ | ------------------ | -------------------- |
| `class`            | empty              | Extra class          |
| `wrapper_class`    | empty              | Extra wrapper class  |
| `limit`            | `10`               | Maximum venues       |
| `page`             | `1`                | Page number          |
| `search`           | empty              | Venue search         |
| `order`            | `asc`              | `asc` or `desc`      |
| `orderby`          | `title`            | `title`, `date`, `modified`, `menu_order` or `ID` |
| `upcoming`         | `false`            | Only venues with an upcoming event |
| `paginate`         | `true`             | Show pagination links. Off for featured lists |
| `pagination_key`   | empty              | Suffix for the venue page URL argument, for example `featured` |
| `show_description` | `true`             | Show the description |
| `excerpt_length`   | `30`               | Description word count. 0 shows all |
| `show_image`       | `false`            | Featured image       |
| `image_size`       | `thumbnail`        | Registered image size |
| `show_address`     | `false`            | Venue address        |
| `show_website`     | `false`            | Venue website        |
| `show_map_link`    | `false`            | Venue map link       |
| `show_next_event`  | `false`            | Next upcoming event  |
| `next_event_label` | `Next event:`      | Next event prefix    |
| `title_tag`        | `a`                | Title markup: `a`, `h2`, `h3` or `div` |
| `link`             | `true`             | Link title and image |
| `date_format`      | site format        | Next event date format |
| `time_format`      | site format        | Next event time format |
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
| `tag_label`            | `Tag`            | Tag label                   |
| `all_tags_label`       | `All tags`       | Empty tag option            |
| `organizer_label`      | `Organizer`      | Organizer label             |
| `all_organizers_label` | `All organizers` | Empty organizer option      |
| `order_label`          | `Order`          | Order label                 |
| `order`                | `asc`            | Default result order        |
| `show_keyword`         | `true`           | Show keyword field          |
| `show_category`        | `true`           | Show category field         |
| `show_tag`             | `false`          | Show tag field              |
| `show_venue`           | `true`           | Show venue field            |
| `show_organizer`       | `false`          | Show organizer field        |
| `show_dates`           | `true`           | Show date fields            |
| `show_upcoming`        | `true`           | Show upcoming toggle        |
| `show_order`           | `false`          | Show order field            |
| `show_results`         | `true`           | Show results below the form |
| `paginate`             | `false`          | Paginate the results        |
| `pagination_key`       | empty            | Results page URL suffix     |
| `show_image`           | `false`          | Result image                |
| `image_size`           | `thumbnail`      | Result image size           |
| `show_price`           | `false`          | Result price                |
| `show_excerpt`         | `false`          | Result excerpt              |
| `show_categories`      | `false`          | Result categories           |
| `show_tags`            | `false`          | Result tags                 |
| `show_organizers`      | `false`          | Result organizers           |
| `show_end`             | `false`          | Result end date             |
| `show_seats_left`      | `false`          | Result seats left           |
| `show_cancelled`       | `false`          | Include cancelled results   |
| `title_tag`            | `a`              | Result title markup         |
| `link`                 | `true`           | Link result title and image |
| `date_format`          | site format      | Result date format          |
| `time_format`          | site format      | Result time format          |

### Calendar — `[reevents_calendar]`

| Argument        | Default                       | Purpose                             |
| --------------- | ----------------------------- | ----------------------------------- |
| `class`         | empty                         | Extra class                         |
| `wrapper_class` | empty                         | Extra wrapper class                 |
| `month`         | empty                         | `Y-m`; empty uses the current month |
| `month_format`  | `F Y`                         | Month heading format                |
| `time_format`   | site format                   | Event time format                   |
| `category`      | empty                         | Category ID, or `current`           |
| `tag`           | empty                         | Tag ID or slug                      |
| `venue`         | `0`                           | Venue ID, or `current`              |
| `organizer`     | `0`                           | Organizer ID, or `current`          |
| `show_cancelled` | `false`                      | Include cancelled events            |
| `show_today`    | `false`                       | Mark today in the grid              |
| `week_start`    | `mon`                         | `mon` or `sun`                      |
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
| `venue`         | `0`                 | Venue ID                                |
| `upcoming`      | `false`             | Only venues with an upcoming event      |
| `search`        | empty               | Venue search                            |
| `center`        | empty               | `lat,lng` map center                    |
| `zoom`          | `0`                 | Zoom 1–19; 0 uses the default           |
| `fit`           | `false`             | Fit all markers in view                 |
| `link`          | `true`              | Link markers to the venue               |
| `provider`      | empty               | `osm` or `google`; empty uses the setting |
| `show_address`  | `false`             | Address in the marker                   |
| `show_next_event` | `false`           | Next event in the marker                |
| `date_format`   | site format         | Next event date format                  |
| `time_format`   | site format         | Next event time format                  |
| `empty_label`   | `No mapped venues.` | Empty state                             |

### Booking form — `[reevents_booking_form]`

| Argument            | Default              | Purpose                              |
| ------------------- | -------------------- | ------------------------------------ |
| `class`             | empty                | Extra class on the form              |
| `wrapper_class`     | empty                | Extra wrapper class                  |
| `event`             | `0`                  | Event ID. `0` uses the current event |
| `ticket`            | `0`                  | Ticket ID to preselect               |
| `occurrence`        | `0`                  | Occurrence ID to preselect           |
| `heading`           | empty                | Optional heading                     |
| `show_price`        | `true`               | Show the ticket price                |
| `default_quantity`  | `1`                  | Initial seat count                   |
| `max_quantity`      | `0`                  | Form seat hint. 0 uses the ticket limit; the server still enforces the ticket max |
| `hide_sold_out`     | `false`              | Hide sold-out tickets                |
| `date_format`       | site format          | Occurrence date format               |
| `time_format`       | site format          | Occurrence time format               |
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

### My Bookings — `[reevents_bookings]`

| Argument               | Default               | Purpose                                   |
| ---------------------- | --------------------- | ----------------------------------------- |
| `class`                | empty                 | Extra class on the inner element          |
| `wrapper_class`        | empty                 | Extra wrapper class                       |
| `heading`              | `My bookings`         | Heading. Empty hides it                   |
| `show_heading`         | `true`                | Show the heading                          |
| `empty_label`          | `You have no bookings yet.` | Empty state                        |
| `limit`                | `20`                  | Maximum bookings, capped at 100           |
| `date_format`          | site format           | Event start date format                   |
| `time_format`          | site format           | Event start time format                   |
| `lookup_heading`       | `Find your booking`   | Guest lookup heading                      |
| `lookup_button_label`  | `Find booking`        | Guest lookup button                       |
| `reference_label`      | `Booking reference`   | Lookup reference label                    |
| `email_label`          | `Email`               | Lookup email label                        |
| `cancel_label`         | `Cancel booking`      | Cancel action label                       |
| `reactivate_label`     | `Reserve again`       | Restore action label                      |
| `free_label`           | `Free`                | Total for a free booking                  |

Rules, access modes and the REST routes are described in [My bookings](/developer/my-bookings.md).

## Common classes

- `reevents-events-list`, `reevents-event`, `reevents-event-title`, `reevents-event-title-link`
- `reevents-event-start`, `reevents-event-end`, `reevents-event-price`, `reevents-event-seats-left`
- `reevents-event-venue`, `reevents-event-organizers`, `reevents-event-categories`, `reevents-event-tags`
- `reevents-event-cancelled`, `reevents-event-ongoing`, `reevents-event-sold-out`
- `reevents-event-details`, `reevents-event-when`, `reevents-event-status`
- `reevents-event-image`, `reevents-event-excerpt`, `reevents-event-venue-address`, `reevents-event-venue-map`
- `reevents-venues-list`, `reevents-venue`, `reevents-venue-image`, `reevents-venue-address`
- `reevents-venue-website`, `reevents-venue-map`, `reevents-venue-next`, `reevents-venue-next-date`
- `reevents-search`, `reevents-search-field`
- `reevents-calendar`, `reevents-calendar-table`, `reevents-calendar-day`, `is-today`
- `reevents-map`, `reevents-map-address`, `reevents-map-next`
- `reevents-booking-form`, `reevents-booking-field`, `reevents-booking-heading`

## Data attributes

- `data-reevents-booking` — booking form
- `data-endpoint` — booking submit URL
- `data-reevents-field` — custom attendee field key
- `data-height` — map height
- `data-markers` — map marker JSON
- `data-center`, `data-zoom`, `data-fit` — map viewport
- `data-max-quantity` — booking form seat cap

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
- `reevents_organizer_dropdown_limit` — maximum organizers in the frontend search filter. Default `500`.
- `reevents_client_ip` — override the address used by the rate limiter, for trusted proxies.
- `reevents_register_gateways` — register payment gateways after the built-in mock gateway.
