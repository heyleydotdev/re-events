# ReEvents shortcodes

Every registered widget is listed here. Arguments left at their default can be omitted.

## Events List

`[reevents_list]`

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

```text
[reevents_list empty_label="No events found." ongoing_label="Ongoing" limit="5" upcoming="1" show_venue="1"]
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
| `show_content` | 1 | Display the event description. |

```text
[reevents_event cancelled_label="Cancelled" ongoing_label="Ongoing" venue_label="Venue:" organizers_label="Organizers:" show_venue="1" show_organizers="1" show_online_links="1" show_content="1"]
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

`[reevents_booking]`

| Argument | Default | Description |
| --- | --- | --- |
| `class` | — | Extra class on the form element. |
| `wrapper_class` | — | Extra class on the wrapper element. |
| `ticket_label` | Ticket | Label above the ticket selector. |
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
[reevents_booking ticket_label="Ticket" quantity_label="Seats" name_label="Name" email_label="Email" button_label="Book" success_message="Booking confirmed." free_label="Free" reference_label="Reference:"]
```

## Event Submission

`[reevents_submit]`

| Argument | Default | Description |
| --- | --- | --- |
| `class` | — | Extra class on the widget. |
| `wrapper_class` | — | Extra class on the wrapper. |
| `submit_label` | Submit event | Text on the search button. |
| `save_label` | Save changes |  |
| `login_message` | Log in to submit an event. |  |
| `success_message` | Thank you. Your event is awaiting review. |  |
| `title_label` | Event title |  |
| `start_label` | Starts |  |
| `end_label` | Ends |  |
| `venue_label` | Venue | Label above the venue field. |
| `capacity_label` | Capacity |  |
| `content_label` | Description |  |
| `name_label` | Your name |  |
| `email_label` | Your email |  |

```text
[reevents_submit submit_label="Submit event" save_label="Save changes" login_message="Log in to submit an event." success_message="Thank you. Your event is awaiting review." title_label="Event title" start_label="Starts" end_label="Ends" venue_label="Venue" capacity_label="Capacity" content_label="Description" name_label="Your name" email_label="Your email"]
```

## My Events

`[reevents_my_events]`

| Argument | Default | Description |
| --- | --- | --- |
| `class` | — | Extra class on the widget. |
| `wrapper_class` | — | Extra class on the wrapper. |
| `limit` | 20 | Maximum number of items. |
| `empty_label` | You have not submitted any events yet. | Text shown when there are no results. |
| `edit_label` | Edit |  |

```text
[reevents_my_events limit="20" empty_label="You have not submitted any events yet." edit_label="Edit"]
```

## My Bookings

`[reevents_my_bookings]`

| Argument | Default | Description |
| --- | --- | --- |
| `class` | — | Extra class on the widget. |
| `wrapper_class` | — | Extra class on the wrapper. |
| `limit` | 20 | Maximum number of items. |
| `empty_label` | You have no bookings yet. | Text shown when there are no results. |

```text
[reevents_my_bookings limit="20" empty_label="You have no bookings yet."]
```

