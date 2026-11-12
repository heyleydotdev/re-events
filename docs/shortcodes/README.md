# ReEvents shortcodes

Every registered widget is listed here. Arguments left at their default can be omitted.

## Events List

`[reevents_events]`

| Argument | Default | Description |
| --- | --- | --- |
| `class` | — | Extra class on the widget. |
| `wrapper_class` | — | Extra class on the wrapper. |
| `date_format` | F j, Y | Date format. Defaults to the site format. |
| `time_format` | g:i a | Time format. Defaults to the site format. |
| `empty_label` | No events found. | Text shown when there are no results. |
| `ongoing_label` | Ongoing | Label shown for ongoing events. |
| `limit` | 5 | Maximum number of items. |
| `search` | — | Free-text keyword search. |
| `category` | — | Category ID, or `current` on a category page. |
| `tag` | — | Tag ID or slug. |
| `venue` | 0 | Venue ID, or `current` on a venue page. |
| `organizer` | 0 | Organizer ID, or `current` on an organizer page. |
| `from` | — | Earliest start date, Y-m-d. |
| `to` | — | Latest end date, Y-m-d. |
| `order` | asc | Start date order: asc or desc. |
| `upcoming` | 1 | Show upcoming events only. |
| `use_query` | 0 | Use the event search values in the current URL. Pair with a search form that hides its results. |
| `show_venue` | 1 | Display the venue name. |
| `show_cancelled` | 0 | Include cancelled events and label them on the card. |
| `cancelled_label` | Cancelled | Badge shown on cancelled events. |
| `page` | 0 | Page number. 0 uses the current page. |
| `paginate` | 1 | Show pagination links. Turn off for sidebar and teaser lists. |
| `pagination_key` | — | Suffix for the page URL argument when several lists share a page, for example main. |
| `show_image` | 1 | Show the featured image. |
| `image_size` | thumbnail | Registered image size. |
| `show_price` | 1 | Show the lowest price or the free label. |
| `show_excerpt` | 0 | Show the event excerpt. |
| `show_categories` | 0 | Show event categories. |
| `show_tags` | 0 | Show event tags. |
| `show_organizers` | 0 | Show event organizer names. |
| `show_end` | 0 | Show the end date and time. |
| `show_seats_left` | 0 | Show the remaining seats. |
| `title_tag` | a | Title markup: a, h2, h3 or div. |
| `link` | 1 | Link the title and image to the event. |
| `excerpt_length` | 0 | Excerpt word count. 0 uses the theme length. |
| `free_label` | Free | Price label for free events. |
| `sold_out_label` | Sold out | Label when no ticket has seats left. |

```text
[reevents_events date_format="F j, Y" time_format="g:i a" empty_label="No events found." ongoing_label="Ongoing" limit="5" order="asc" upcoming="1" show_venue="1" cancelled_label="Cancelled" paginate="1" show_image="1" image_size="thumbnail" show_price="1" title_tag="a" link="1" free_label="Free" sold_out_label="Sold out"]
```

## Event Details

`[reevents_event]`

| Argument | Default | Description |
| --- | --- | --- |
| `class` | — | Extra class on the widget. |
| `wrapper_class` | — | Extra class on the wrapper. |
| `date_format` | F j, Y | Date format. Defaults to the site format. |
| `time_format` | g:i a | Time format. Defaults to the site format. |
| `cancelled_label` | Cancelled | Label shown for cancelled events. |
| `ongoing_label` | Ongoing | Label shown for ongoing events. |
| `venue_label` | Venue: | Label above the venue field. |
| `organizers_label` | Organizers: | Label shown before the organizer names. |
| `id` | 0 | Leave 0 to use the current event. |
| `show_venue` | 1 | Display the venue name. |
| `show_organizers` | 1 | Display the organizer names. |
| `show_online_links` | 1 | Display online event links. |
| `show_calendar_links` | 1 | Display add-to-calendar links. |
| `calendar_label` | Add to calendar | Heading above the calendar links. |
| `google_label` | Google | Google Calendar link label. |
| `outlook_label` | Outlook | Outlook link label. |
| `office365_label` | Office 365 | Office 365 link label. |
| `ical_label` | Apple / iCal | Apple / iCal link label. |
| `show_image` | 0 | Show the featured image. |
| `image_size` | large | Registered image size. |
| `show_title` | 1 |  |
| `title_tag` | h2 | Title markup: h1, h2, h3 or div. |
| `show_status` | 1 | Show the cancelled or ongoing badge. |
| `show_price` | 0 | Show the lowest price or the free label. |
| `show_seats_left` | 0 | Show the remaining seats. |
| `show_categories` | 0 | Show event categories. |
| `show_tags` | 0 | Show event tags. |
| `show_excerpt` | 0 | Show the event excerpt. |
| `show_end` | 1 | Show the end date and time. |
| `show_venue_address` | 0 | Show the venue address. |
| `show_venue_map_link` | 0 | Show a map link for the venue. |
| `free_label` | Free | Price label for free events. |
| `sold_out_label` | Sold out | Label when no ticket has seats left. |
| `show_content` | 1 | Display the event description. |

```text
[reevents_event date_format="F j, Y" time_format="g:i a" cancelled_label="Cancelled" ongoing_label="Ongoing" venue_label="Venue:" organizers_label="Organizers:" show_venue="1" show_organizers="1" show_online_links="1" show_calendar_links="1" calendar_label="Add to calendar" google_label="Google" outlook_label="Outlook" office365_label="Office 365" ical_label="Apple / iCal" image_size="large" show_title="1" title_tag="h2" show_status="1" show_end="1" free_label="Free" sold_out_label="Sold out" show_content="1"]
```

## Venues List

`[reevents_venues]`

| Argument | Default | Description |
| --- | --- | --- |
| `class` | — | Extra class on the widget. |
| `wrapper_class` | — | Extra class on the wrapper. |
| `empty_label` | No venues found. | Text shown when there are no results. |
| `limit` | 10 | Maximum number of items. |
| `page` | 1 | Page number. 1 is the first page. |
| `paginate` | 1 | Show pagination links. Turn off for featured venue lists. |
| `pagination_key` | — | Suffix for the page URL argument when several venue lists share a page, for example featured. |
| `show_description` | 1 | Display the venue description. |
| `search` | — | Free-text venue search. |
| `order` | asc | Order: asc or desc. |
| `orderby` | title | Order by title, date, modified, menu_order or ID. |
| `upcoming` | 0 | Only venues with an upcoming event. |
| `show_image` | 0 | Show the featured image. |
| `image_size` | thumbnail | Registered image size. |
| `show_address` | 0 | Show the venue address. |
| `show_website` | 0 | Show the venue website. |
| `show_map_link` | 0 | Show a map link when coordinates exist. |
| `show_next_event` | 0 | Show the next upcoming event. |
| `next_event_label` | Next event: | Label before the next event link. |
| `excerpt_length` | 30 | Description word count. 0 shows the full description. |
| `title_tag` | a | Title markup: a, h2, h3 or div. |
| `link` | 1 | Link the title and image to the venue. |
| `date_format` | F j, Y | Date format for the next event. Defaults to the site format. |
| `time_format` | g:i a | Time format for the next event. Defaults to the site format. |

```text
[reevents_venues empty_label="No venues found." limit="10" page="1" paginate="1" show_description="1" order="asc" orderby="title" image_size="thumbnail" next_event_label="Next event:" excerpt_length="30" title_tag="a" link="1" date_format="F j, Y" time_format="g:i a"]
```

## Events Search

`[reevents_search]`

| Argument | Default | Description |
| --- | --- | --- |
| `class` | — | Extra class on the widget. |
| `wrapper_class` | — | Extra class on the wrapper. |
| `search_label` | Search | Label above the keyword field. |
| `search_placeholder` | — | Placeholder inside the keyword field. |
| `category_label` | Category | Label above the category field. |
| `venue_label` | Venue | Label above the venue field. |
| `from_label` | From | Label above the start date. |
| `to_label` | To | Label above the end date. |
| `upcoming_label` | Only upcoming | Label for the upcoming toggle. |
| `submit_label` | Search | Text on the search button. |
| `clear_label` | Clear | Text on the clear link. |
| `all_categories_label` | All categories | Option for no category filter. |
| `all_venues_label` | All venues | Option for no venue filter. |
| `tag_label` | Tag |  |
| `all_tags_label` | All tags |  |
| `organizer_label` | Organizer |  |
| `all_organizers_label` | All organizers |  |
| `order_label` | Order |  |
| `order` | asc | Result order: asc or desc. |
| `limit` | 20 | Maximum number of items. |
| `show_keyword` | 1 | Display the keyword field. |
| `show_category` | 1 | Display the category field. |
| `show_venue` | 1 | Display the venue name. |
| `show_dates` | 1 | Display the date range fields. |
| `show_upcoming` | 1 | Display the upcoming toggle. |
| `show_results` | 1 | Display results below the form. |
| `show_tag` | 0 |  |
| `show_organizer` | 0 |  |
| `show_order` | 0 |  |
| `paginate` | 0 | Show pagination links below the results. |
| `pagination_key` | — | Suffix for the results page URL argument when several searches share a page. |
| `show_image` | 0 |  |
| `image_size` | thumbnail |  |
| `show_price` | 0 |  |
| `show_excerpt` | 0 |  |
| `show_categories` | 0 |  |
| `show_tags` | 0 |  |
| `show_organizers` | 0 | Display the organizer names. |
| `show_end` | 0 |  |
| `show_seats_left` | 0 |  |
| `show_cancelled` | 0 |  |
| `title_tag` | a |  |
| `link` | 1 |  |
| `date_format` | F j, Y |  |
| `time_format` | g:i a |  |

```text
[reevents_search search_label="Search" category_label="Category" venue_label="Venue" from_label="From" to_label="To" upcoming_label="Only upcoming" submit_label="Search" clear_label="Clear" all_categories_label="All categories" all_venues_label="All venues" tag_label="Tag" all_tags_label="All tags" organizer_label="Organizer" all_organizers_label="All organizers" order_label="Order" order="asc" limit="20" show_keyword="1" show_category="1" show_venue="1" show_dates="1" show_upcoming="1" show_results="1" image_size="thumbnail" title_tag="a" link="1" date_format="F j, Y" time_format="g:i a"]
```

## Calendar

`[reevents_calendar]`

| Argument | Default | Description |
| --- | --- | --- |
| `class` | — | Extra class on the widget. |
| `wrapper_class` | — | Extra class on the wrapper. |
| `ongoing_label` | Ongoing | Label shown for ongoing events. |
| `weekdays` | Mon,Tue,Wed,Thu,Fri,Sat,Sun | Comma-separated, seven labels. |
| `month` | — | Month in Y-m format. Leave empty for the current month. |
| `month_format` | F Y | Month heading format. |
| `time_format` | g:i a | Time format. Defaults to the site format. |
| `category` | — | Category ID, or `current` on a category page. |
| `tag` | — | Tag ID or slug. |
| `venue` | 0 | Venue ID, or `current` on a venue page. |
| `organizer` | 0 | Organizer ID, or `current` on an organizer page. |
| `show_cancelled` | 0 | Include cancelled events with a cancelled style. |
| `show_today` | 0 | Mark today in the grid. |
| `week_start` | mon | First weekday: mon or sun. |
| `show_nav` | 1 | Display previous and next month links. |

```text
[reevents_calendar ongoing_label="Ongoing" weekdays="Mon,Tue,Wed,Thu,Fri,Sat,Sun" month_format="F Y" time_format="g:i a" week_start="mon" show_nav="1"]
```

## Map

`[reevents_map]`

| Argument | Default | Description |
| --- | --- | --- |
| `class` | — | Extra class on the widget. |
| `wrapper_class` | — | Extra class on the wrapper. |
| `empty_label` | No mapped venues. | Text shown when there are no results. |
| `limit` | 50 | Maximum number of items. |
| `height` | 400 | Map height in pixels. |
| `venue` | 0 | Venue ID. |
| `upcoming` | 0 | Only venues with an upcoming event. |
| `search` | — | Free-text venue search. |
| `center` | — | Map center as lat,lng. |
| `zoom` | 0 | Zoom level 1-19. 0 uses the default. |
| `fit` | 0 | Fit all markers in view. |
| `link` | 1 | Link markers to the venue. |
| `provider` | — | Map provider: osm or google. Empty uses the site setting. |
| `show_address` | 0 | Show the venue address in the marker. |
| `show_next_event` | 0 | Show the next event in the marker. |
| `date_format` | F j, Y | Date format for the next event. Defaults to the site format. |
| `time_format` | g:i a | Time format for the next event. Defaults to the site format. |

```text
[reevents_map empty_label="No mapped venues." limit="50" height="400" link="1" date_format="F j, Y" time_format="g:i a"]
```

## Booking Form

`[reevents_booking_form]`

| Argument | Default | Description |
| --- | --- | --- |
| `class` | — | Extra class on the form element. |
| `wrapper_class` | — | Extra class on the wrapper element. |
| `date_format` | F j, Y | Date format in the time options. Defaults to the site format. |
| `time_format` | g:i a | Time format in the time options. Defaults to the site format. |
| `heading` | — | Optional heading above the form. |
| `show_price` | 1 | Show the ticket price in the selector. |
| `default_quantity` | 1 | Initial seat count. |
| `max_quantity` | 0 | Maximum seats shown in the form. 0 uses the ticket limit. The ticket limit is still enforced on the server. |
| `ticket` | 0 | Ticket ID to preselect. |
| `occurrence` | 0 | Occurrence ID to preselect. |
| `hide_sold_out` | 0 | Hide tickets with no seats left. |
| `ticket_label` | Ticket | Label above the ticket selector. |
| `time_label` | Time | Label above the timeslot selector. |
| `quantity_label` | Seats | Label above the seat count. |
| `name_label` | Name | Label above the attendee name. |
| `name_placeholder` | — | Placeholder inside the name field. Leave empty for none. |
| `email_label` | Email | Label above the attendee email. |
| `email_placeholder` | — | Placeholder inside the email field. Leave empty for none. |
| `button_label` | Book | Text shown on the submit button. |
| `success_message` | Booking confirmed. | Message shown after a successful booking. |
| `free_label` | Free | Text shown for a free ticket. |
| `reference_label` | Reference: | Label shown before the booking reference. |
| `event` | 0 | Leave 0 to use the current event. |

```text
[reevents_booking_form date_format="F j, Y" time_format="g:i a" show_price="1" default_quantity="1" ticket_label="Ticket" time_label="Time" quantity_label="Seats" name_label="Name" email_label="Email" button_label="Book" success_message="Booking confirmed." free_label="Free" reference_label="Reference:"]
```

## Field shortcode

`[reevents_field]` prints a single value for custom layouts:

```text
[reevents_field field="venue" label="Venue:"]
```

| Argument | Default | Description |
| --- | --- | --- |
| `field` | `title` | One of the fields listed below. |
| `event` | `0` | Event ID. `0` uses the current post. |
| `label` | — | Text shown before the value. |
| `link` | `0` | Wrap the value in a link where the field supports one. |
| `separator` | `, ` | Separator between list values. |
| `empty_label` | — | Text shown when the value is empty. |
| `date_format` | `F j, Y` | Date format. Defaults to the site format. |
| `time_format` | `g:i a` | Time format. Defaults to the site format. |
| `class` | — | Extra classes on the wrapper. |
| `tag` | `span` | Wrapper tag: `span`, `div`, `p`, `h2`, `h3` or `h4`. |
| `size` | `large` | Image size for `image` and `image_url`. |
| `venue` | `0` | Venue ID. `current` uses the current venue. |
| `organizer` | `0` | Organizer ID. `current` uses the current organizer. |
| `limit` | `10` | Maximum dates for the `occurrences` field. |
| `from` | — | Earliest date for `occurrences`, Y-m-d. |
| `to` | — | Latest date for `occurrences`, Y-m-d. |
| `excerpt_length` | `0` | Excerpt word count. 0 uses the theme length. |

### Event fields

| Field | Value |
| --- | --- |
| `title` | Title |
| `permalink` | Permalink |
| `start` | Start date and time |
| `end` | End date and time |
| `start_date` | Start date |
| `end_date` | End date |
| `start_time` | Start time |
| `end_time` | End time |
| `occurrences` | Upcoming dates |
| `timezone` | Time zone |
| `status` | Status |
| `venue` | Venue name |
| `venue_address` | Venue address |
| `venue_website` | Venue website |
| `venue_map_link` | Venue map link |
| `venue_link` | Venue permalink |
| `organizer_names` | Organizer names |
| `categories` | Categories |
| `tags` | Tags |
| `excerpt` | Excerpt |
| `content` | Content |
| `image` | Featured image |
| `image_url` | Featured image URL |
| `price_from` | Lowest paid ticket price |
| `is_free` | Free event |
| `seats_left` | Seats left |
| `sold_out` | Sold out |
| `online_links` | Online links |
| `calendar_links` | Add-to-calendar links |

### Venue fields

| Field | Value |
| --- | --- |
| `title` | Title |
| `permalink` | Permalink |
| `content` | Content |
| `description` | Description |
| `address` | Address |
| `website` | Website |
| `lat` | Latitude |
| `lng` | Longitude |
| `map_link` | Map link |
| `image` | Featured image |
| `image_url` | Featured image URL |

### Organizer fields

| Field | Value |
| --- | --- |
| `title` | Title |
| `permalink` | Permalink |
| `content` | Content |
| `description` | Description |
| `types` | Types |
| `image` | Featured image |
| `image_url` | Featured image URL |


