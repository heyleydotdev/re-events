# ReEvents shortcodes

Every registered widget is listed here. Arguments left at their default can be omitted.

## Events List

`[reevents_events]`

| Argument | Default | Description |
| --- | --- | --- |
| `class` | — | Extra class on the widget. |
| `wrapper_class` | — | Extra class on the wrapper. |
| `empty_label` | No events found. | Text shown when there are no results. |
| `ongoing_label` | Ongoing | Label shown for ongoing events. |
| `limit` | 5 | Maximum number of items. |
| `category` | — | Limit to a category term ID. |
| `venue` | 0 | Limit to a venue ID. |
| `upcoming` | 1 | Show upcoming events only. |
| `show_venue` | 1 | Display the venue name. |
| `page` | 0 | Page number. 0 uses the current page. |
| `show_image` | 1 | Show the featured image. |
| `image_size` | thumbnail | Registered image size. |
| `show_price` | 1 | Show the lowest price or the free label. |
| `show_excerpt` | 0 | Show the event excerpt. |
| `show_categories` | 0 | Show event categories. |
| `free_label` | Free | Text shown for a free ticket. |
| `sold_out_label` | Sold out | Label when no ticket has seats left. |

```text
[reevents_events empty_label="No events found." ongoing_label="Ongoing" limit="5" upcoming="1" show_venue="1" show_image="1" image_size="thumbnail" show_price="1" free_label="Free" sold_out_label="Sold out"]
```

## Event Details

`[reevents_event]`

| Argument | Default | Description |
| --- | --- | --- |
| `class` | — | Extra class on the widget. |
| `wrapper_class` | — | Extra class on the wrapper. |
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
| `show_content` | 1 | Display the event description. |

```text
[reevents_event cancelled_label="Cancelled" ongoing_label="Ongoing" venue_label="Venue:" organizers_label="Organizers:" show_venue="1" show_organizers="1" show_online_links="1" show_calendar_links="1" calendar_label="Add to calendar" google_label="Google" outlook_label="Outlook" office365_label="Office 365" ical_label="Apple / iCal" show_content="1"]
```

## Venues List

`[reevents_venues]`

| Argument | Default | Description |
| --- | --- | --- |
| `class` | — | Extra class on the widget. |
| `wrapper_class` | — | Extra class on the wrapper. |
| `empty_label` | No venues found. | Text shown when there are no results. |
| `limit` | 10 | Maximum number of items. |
| `show_description` | 1 | Display the venue description. |

```text
[reevents_venues empty_label="No venues found." limit="10" show_description="1"]
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
| `limit` | 20 | Maximum number of items. |
| `show_keyword` | 1 | Display the keyword field. |
| `show_category` | 1 | Display the category field. |
| `show_venue` | 1 | Display the venue name. |
| `show_dates` | 1 | Display the date range fields. |
| `show_upcoming` | 1 | Display the upcoming toggle. |
| `show_results` | 1 | Display results below the form. |

```text
[reevents_search search_label="Search" category_label="Category" venue_label="Venue" from_label="From" to_label="To" upcoming_label="Only upcoming" submit_label="Search" clear_label="Clear" all_categories_label="All categories" all_venues_label="All venues" limit="20" show_keyword="1" show_category="1" show_venue="1" show_dates="1" show_upcoming="1" show_results="1"]
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
| `show_nav` | 1 | Display previous and next month links. |

```text
[reevents_calendar ongoing_label="Ongoing" weekdays="Mon,Tue,Wed,Thu,Fri,Sat,Sun" show_nav="1"]
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

```text
[reevents_map empty_label="No mapped venues." limit="50" height="400"]
```

## Booking Form

`[reevents_booking_form]`

| Argument | Default | Description |
| --- | --- | --- |
| `class` | — | Extra class on the form element. |
| `wrapper_class` | — | Extra class on the wrapper element. |
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
[reevents_booking_form ticket_label="Ticket" time_label="Time" quantity_label="Seats" name_label="Name" email_label="Email" button_label="Book" success_message="Booking confirmed." free_label="Free" reference_label="Reference:"]
```

## Event fields

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
| `class` | — | Extra classes on the wrapper. |
| `tag` | `span` | Wrapper tag: `span`, `div` or `p`. |
| `size` | `large` | Image size for `image` and `image_url`. |

### Fields

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
| `timezone` | Time zone |
| `status` | Status |
| `venue` | Venue name |
| `venue_address` | Venue address |
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

