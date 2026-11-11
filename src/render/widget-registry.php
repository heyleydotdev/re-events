<?php
/**
 * Widget registry.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Render;

/**
 * Single registry for renderers, shortcodes and builder widgets.
 */
final class Widget_Registry {

	/**
	 * Registered widgets.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private static array $widgets = [];

	/**
	 * Register the default widgets.
	 */
	public static function register_defaults(): void {
		if ( [] !== self::$widgets ) {
			return;
		}

		self::register(
			'events-list',
			[
				'label'     => __( 'Events List', 're-events' ),
				'shortcode' => 'reevents_list',
				'renderer'  => [ Events_List::class, 'render' ],
				'args'      => [
					'class'         => [
						'type'    => 'text',
						'default' => '',
					],
					'wrapper_class' => [
						'type'    => 'text',
						'default' => '',
					],
					'empty_label'   => [
						'type'    => 'text',
						'default' => __( 'No events found.', 're-events' ),
					],
					'ongoing_label' => [
						'type'    => 'text',
						'default' => __( 'Ongoing', 're-events' ),
					],
					'limit'         => [
						'type'    => 'number',
						'default' => 5,
					],
					'category'      => [
						'type'    => 'text',
						'default' => '',
					],
					'venue'         => [
						'type'    => 'number',
						'default' => 0,
					],
					'upcoming'      => [
						'type'    => 'toggle',
						'default' => true,
					],
					'show_venue'    => [
						'type'    => 'toggle',
						'default' => true,
					],
				],
			]
		);

		self::register(
			'event-details',
			[
				'label'     => __( 'Event Details', 're-events' ),
				'shortcode' => 'reevents_event',
				'renderer'  => [ Event_Details::class, 'render' ],
				'args'      => [
					'class'               => [
						'type'    => 'text',
						'default' => '',
					],
					'wrapper_class'       => [
						'type'    => 'text',
						'default' => '',
					],
					'cancelled_label'     => [
						'type'    => 'text',
						'default' => __( 'Cancelled', 're-events' ),
					],
					'ongoing_label'       => [
						'type'    => 'text',
						'default' => __( 'Ongoing', 're-events' ),
					],
					'venue_label'         => [
						'type'    => 'text',
						'default' => __( 'Venue:', 're-events' ),
					],
					'organizers_label'    => [
						'type'    => 'text',
						'default' => __( 'Organizers:', 're-events' ),
					],
					'id'                  => [
						'type'    => 'number',
						'default' => 0,
					],
					'show_venue'          => [
						'type'    => 'toggle',
						'default' => true,
					],
					'show_organizers'     => [
						'type'    => 'toggle',
						'default' => true,
					],
					'show_online_links'   => [
						'type'    => 'toggle',
						'default' => true,
					],
					'show_calendar_links' => [
						'type'        => 'toggle',
						'default'     => true,
						'description' => __( 'Display add-to-calendar links.', 're-events' ),
					],
					'calendar_label'      => [
						'type'        => 'text',
						'default'     => __( 'Add to calendar', 're-events' ),
						'description' => __( 'Heading above the calendar links.', 're-events' ),
					],
					'google_label'        => [
						'type'        => 'text',
						'default'     => __( 'Google', 're-events' ),
						'description' => __( 'Google Calendar link label.', 're-events' ),
					],
					'outlook_label'       => [
						'type'        => 'text',
						'default'     => __( 'Outlook', 're-events' ),
						'description' => __( 'Outlook link label.', 're-events' ),
					],
					'office365_label'     => [
						'type'        => 'text',
						'default'     => __( 'Office 365', 're-events' ),
						'description' => __( 'Office 365 link label.', 're-events' ),
					],
					'ical_label'          => [
						'type'        => 'text',
						'default'     => __( 'Apple / iCal', 're-events' ),
						'description' => __( 'Apple / iCal link label.', 're-events' ),
					],
					'show_content'        => [
						'type'    => 'toggle',
						'default' => true,
					],
				],
			]
		);

		self::register(
			'venues-list',
			[
				'label'     => __( 'Venues List', 're-events' ),
				'shortcode' => 'reevents_venues',
				'renderer'  => [ Venues_List::class, 'render' ],
				'args'      => [
					'class'            => [
						'type'    => 'text',
						'default' => '',
					],
					'wrapper_class'    => [
						'type'    => 'text',
						'default' => '',
					],
					'empty_label'      => [
						'type'    => 'text',
						'default' => __( 'No venues found.', 're-events' ),
					],
					'limit'            => [
						'type'    => 'number',
						'default' => 10,
					],
					'show_description' => [
						'type'    => 'toggle',
						'default' => true,
					],
				],
			]
		);

		self::register(
			'events-search',
			[
				'label'     => __( 'Events Search', 're-events' ),
				'shortcode' => 'reevents_search',
				'renderer'  => [ Events_Search::class, 'render' ],
				'args'      => [
					'class'                => [
						'type'    => 'text',
						'default' => '',
					],
					'wrapper_class'        => [
						'type'    => 'text',
						'default' => '',
					],
					'search_label'         => [
						'type'    => 'text',
						'default' => __( 'Search', 're-events' ),
					],
					'search_placeholder'   => [
						'type'    => 'text',
						'default' => '',
					],
					'category_label'       => [
						'type'    => 'text',
						'default' => __( 'Category', 're-events' ),
					],
					'venue_label'          => [
						'type'    => 'text',
						'default' => __( 'Venue', 're-events' ),
					],
					'from_label'           => [
						'type'    => 'text',
						'default' => __( 'From', 're-events' ),
					],
					'to_label'             => [
						'type'    => 'text',
						'default' => __( 'To', 're-events' ),
					],
					'upcoming_label'       => [
						'type'    => 'text',
						'default' => __( 'Only upcoming', 're-events' ),
					],
					'submit_label'         => [
						'type'    => 'text',
						'default' => __( 'Search', 're-events' ),
					],
					'clear_label'          => [
						'type'    => 'text',
						'default' => __( 'Clear', 're-events' ),
					],
					'all_categories_label' => [
						'type'    => 'text',
						'default' => __( 'All categories', 're-events' ),
					],
					'all_venues_label'     => [
						'type'    => 'text',
						'default' => __( 'All venues', 're-events' ),
					],
					'limit'                => [
						'type'    => 'number',
						'default' => 20,
					],
					'show_keyword'         => [
						'type'    => 'toggle',
						'default' => true,
					],
					'show_category'        => [
						'type'    => 'toggle',
						'default' => true,
					],
					'show_venue'           => [
						'type'    => 'toggle',
						'default' => true,
					],
					'show_dates'           => [
						'type'    => 'toggle',
						'default' => true,
					],
					'show_upcoming'        => [
						'type'    => 'toggle',
						'default' => true,
					],
					'show_results'         => [
						'type'    => 'toggle',
						'default' => true,
					],
				],
			]
		);

		self::register(
			'calendar',
			[
				'label'     => __( 'Calendar', 're-events' ),
				'shortcode' => 'reevents_calendar',
				'renderer'  => [ Calendar::class, 'render' ],
				'args'      => [
					'class'         => [
						'type'    => 'text',
						'default' => '',
					],
					'wrapper_class' => [
						'type'    => 'text',
						'default' => '',
					],
					'ongoing_label' => [
						'type'    => 'text',
						'default' => __( 'Ongoing', 're-events' ),
					],
					'weekdays'      => [
						'type'        => 'text',
						'label'       => __( 'Weekday labels', 're-events' ),
						'default'     => __( 'Mon,Tue,Wed,Thu,Fri,Sat,Sun', 're-events' ),
						'description' => __( 'Comma-separated, seven labels.', 're-events' ),
					],
					'month'         => [
						'type'    => 'text',
						'default' => '',
					],
					'show_nav'      => [
						'type'    => 'toggle',
						'default' => true,
					],
				],
			]
		);

		self::register(
			'map',
			[
				'label'     => __( 'Map', 're-events' ),
				'shortcode' => 'reevents_map',
				'renderer'  => [ Map::class, 'render' ],
				'args'      => [
					'class'         => [
						'type'    => 'text',
						'default' => '',
					],
					'wrapper_class' => [
						'type'    => 'text',
						'default' => '',
					],
					'empty_label'   => [
						'type'    => 'text',
						'default' => __( 'No mapped venues.', 're-events' ),
					],
					'limit'         => [
						'type'    => 'number',
						'default' => 50,
					],
					'height'        => [
						'type'    => 'number',
						'default' => 400,
					],
				],
			]
		);

		self::register(
			'booking-form',
			[
				'label'     => __( 'Booking Form', 're-events' ),
				'shortcode' => 'reevents_booking',
				'renderer'  => [ Booking_Form::class, 'render' ],
				'args'      => [
					'class'             => [
						'type'        => 'text',
						'label'       => __( 'CSS class', 're-events' ),
						'default'     => '',
						'placeholder' => __( 'my-booking-form', 're-events' ),
						'description' => __( 'Extra class on the form element.', 're-events' ),
					],
					'wrapper_class'     => [
						'type'        => 'text',
						'label'       => __( 'Wrapper CSS class', 're-events' ),
						'default'     => '',
						'placeholder' => __( 'my-booking-wrapper', 're-events' ),
						'description' => __( 'Extra class on the wrapper element.', 're-events' ),
					],
					'ticket_label'      => [
						'type'        => 'text',
						'label'       => __( 'Ticket label', 're-events' ),
						'default'     => __( 'Ticket', 're-events' ),
						'placeholder' => __( 'Ticket', 're-events' ),
						'description' => __( 'Label above the ticket selector.', 're-events' ),
					],
					'time_label'        => [
						'type'        => 'text',
						'label'       => __( 'Time label', 're-events' ),
						'default'     => __( 'Time', 're-events' ),
						'placeholder' => __( 'Time', 're-events' ),
						'description' => __( 'Label above the timeslot selector.', 're-events' ),
					],
					'quantity_label'    => [
						'type'        => 'text',
						'label'       => __( 'Seats label', 're-events' ),
						'default'     => __( 'Seats', 're-events' ),
						'placeholder' => __( 'Seats', 're-events' ),
						'description' => __( 'Label above the seat count.', 're-events' ),
					],
					'name_label'        => [
						'type'        => 'text',
						'label'       => __( 'Name label', 're-events' ),
						'default'     => __( 'Name', 're-events' ),
						'placeholder' => __( 'Name', 're-events' ),
						'description' => __( 'Label above the attendee name.', 're-events' ),
					],
					'name_placeholder'  => [
						'type'        => 'text',
						'label'       => __( 'Name placeholder', 're-events' ),
						'default'     => '',
						'placeholder' => __( 'e.g. Jane Doe', 're-events' ),
						'description' => __( 'Placeholder inside the name field. Leave empty for none.', 're-events' ),
					],
					'email_label'       => [
						'type'        => 'text',
						'label'       => __( 'Email label', 're-events' ),
						'default'     => __( 'Email', 're-events' ),
						'placeholder' => __( 'Email', 're-events' ),
						'description' => __( 'Label above the attendee email.', 're-events' ),
					],
					'email_placeholder' => [
						'type'        => 'text',
						'label'       => __( 'Email placeholder', 're-events' ),
						'default'     => '',
						'placeholder' => __( 'jane@example.com', 're-events' ),
						'description' => __( 'Placeholder inside the email field. Leave empty for none.', 're-events' ),
					],
					'button_label'      => [
						'type'        => 'text',
						'label'       => __( 'Button label', 're-events' ),
						'default'     => __( 'Book', 're-events' ),
						'placeholder' => __( 'Book', 're-events' ),
						'description' => __( 'Text shown on the submit button.', 're-events' ),
					],
					'success_message'   => [
						'type'        => 'text',
						'label'       => __( 'Success message', 're-events' ),
						'default'     => __( 'Booking confirmed.', 're-events' ),
						'placeholder' => __( 'Booking confirmed.', 're-events' ),
						'description' => __( 'Message shown after a successful booking.', 're-events' ),
					],
					'free_label'        => [
						'type'        => 'text',
						'label'       => __( 'Free label', 're-events' ),
						'default'     => __( 'Free', 're-events' ),
						'placeholder' => __( 'Free', 're-events' ),
						'description' => __( 'Text shown for a free ticket.', 're-events' ),
					],
					'reference_label'   => [
						'type'        => 'text',
						'label'       => __( 'Reference label', 're-events' ),
						'default'     => __( 'Reference:', 're-events' ),
						'placeholder' => __( 'Reference:', 're-events' ),
						'description' => __( 'Label shown before the booking reference.', 're-events' ),
					],
					'event'             => [
						'type'        => 'number',
						'label'       => __( 'Event ID', 're-events' ),
						'default'     => 0,
						'description' => __( 'Leave 0 to use the current event.', 're-events' ),
					],
				],
			]
		);

		self::decorate_args();
	}

	/**
	 * Add builder control metadata to args that do not define it.
	 */
	private static function decorate_args(): void {
		$meta = [
			'class'                => [ __( 'CSS class', 're-events' ), __( 'Extra class on the widget.', 're-events' ), __( 'my-widget', 're-events' ) ],
			'wrapper_class'        => [ __( 'Wrapper CSS class', 're-events' ), __( 'Extra class on the wrapper.', 're-events' ), __( 'my-wrapper', 're-events' ) ],
			'limit'                => [ __( 'Limit', 're-events' ), __( 'Maximum number of items.', 're-events' ), '10' ],
			'category'             => [ __( 'Category ID', 're-events' ), __( 'Limit to a category term ID.', 're-events' ), '12' ],
			'venue'                => [ __( 'Venue ID', 're-events' ), __( 'Limit to a venue ID.', 're-events' ), '34' ],
			'upcoming'             => [ __( 'Only upcoming', 're-events' ), __( 'Show upcoming events only.', 're-events' ), '' ],
			'show_venue'           => [ __( 'Show venue', 're-events' ), __( 'Display the venue name.', 're-events' ), '' ],
			'show_organizers'      => [ __( 'Show organizers', 're-events' ), __( 'Display the organizer names.', 're-events' ), '' ],
			'show_online_links'    => [ __( 'Show online links', 're-events' ), __( 'Display online event links.', 're-events' ), '' ],
			'show_content'         => [ __( 'Show content', 're-events' ), __( 'Display the event description.', 're-events' ), '' ],
			'show_description'     => [ __( 'Show description', 're-events' ), __( 'Display the venue description.', 're-events' ), '' ],
			'show_keyword'         => [ __( 'Show keyword', 're-events' ), __( 'Display the keyword field.', 're-events' ), '' ],
			'show_category'        => [ __( 'Show category', 're-events' ), __( 'Display the category field.', 're-events' ), '' ],
			'show_dates'           => [ __( 'Show dates', 're-events' ), __( 'Display the date range fields.', 're-events' ), '' ],
			'show_upcoming'        => [ __( 'Show upcoming', 're-events' ), __( 'Display the upcoming toggle.', 're-events' ), '' ],
			'show_results'         => [ __( 'Show results', 're-events' ), __( 'Display results below the form.', 're-events' ), '' ],
			'show_nav'             => [ __( 'Show navigation', 're-events' ), __( 'Display previous and next month links.', 're-events' ), '' ],
			'month'                => [ __( 'Month', 're-events' ), __( 'Month in Y-m format. Leave empty for the current month.', 're-events' ), '2026-11' ],
			'height'               => [ __( 'Height', 're-events' ), __( 'Map height in pixels.', 're-events' ), '400' ],
			'id'                   => [ __( 'Event ID', 're-events' ), __( 'Leave 0 to use the current event.', 're-events' ), '123' ],
			'ongoing_label'        => [ __( 'Ongoing label', 're-events' ), __( 'Label shown for ongoing events.', 're-events' ), __( 'Ongoing', 're-events' ) ],
			'search_label'         => [ __( 'Search label', 're-events' ), __( 'Label above the keyword field.', 're-events' ), __( 'Search', 're-events' ) ],
			'search_placeholder'   => [ __( 'Search placeholder', 're-events' ), __( 'Placeholder inside the keyword field.', 're-events' ), __( 'Search events', 're-events' ) ],
			'category_label'       => [ __( 'Category label', 're-events' ), __( 'Label above the category field.', 're-events' ), __( 'Category', 're-events' ) ],
			'venue_label'          => [ __( 'Venue label', 're-events' ), __( 'Label above the venue field.', 're-events' ), __( 'Venue', 're-events' ) ],
			'from_label'           => [ __( 'From label', 're-events' ), __( 'Label above the start date.', 're-events' ), __( 'From', 're-events' ) ],
			'to_label'             => [ __( 'To label', 're-events' ), __( 'Label above the end date.', 're-events' ), __( 'To', 're-events' ) ],
			'upcoming_label'       => [ __( 'Upcoming label', 're-events' ), __( 'Label for the upcoming toggle.', 're-events' ), __( 'Only upcoming', 're-events' ) ],
			'submit_label'         => [ __( 'Submit label', 're-events' ), __( 'Text on the search button.', 're-events' ), __( 'Search', 're-events' ) ],
			'clear_label'          => [ __( 'Clear label', 're-events' ), __( 'Text on the clear link.', 're-events' ), __( 'Clear', 're-events' ) ],
			'empty_label'          => [ __( 'Empty message', 're-events' ), __( 'Text shown when there are no results.', 're-events' ), __( 'No results found.', 're-events' ) ],
			'cancelled_label'      => [ __( 'Cancelled label', 're-events' ), __( 'Label shown for cancelled events.', 're-events' ), __( 'Cancelled', 're-events' ) ],
			'organizers_label'     => [ __( 'Organizers label', 're-events' ), __( 'Label shown before the organizer names.', 're-events' ), __( 'Organizers:', 're-events' ) ],
			'all_categories_label' => [ __( 'All categories label', 're-events' ), __( 'Option for no category filter.', 're-events' ), __( 'All categories', 're-events' ) ],
			'all_venues_label'     => [ __( 'All venues label', 're-events' ), __( 'Option for no venue filter.', 're-events' ), __( 'All venues', 're-events' ) ],
			'free_label'           => [ __( 'Free label', 're-events' ), __( 'Text shown for a free ticket.', 're-events' ), __( 'Free', 're-events' ) ],
		];

		foreach ( self::$widgets as &$widget ) {
			if ( empty( $widget['args'] ) || ! is_array( $widget['args'] ) ) {
				continue;
			}

			foreach ( $widget['args'] as $key => &$arg ) {
				if ( ! is_array( $arg ) || isset( $arg['label'] ) || ! isset( $meta[ $key ] ) ) {
					continue;
				}

				$arg['label']       = $meta[ $key ][0];
				$arg['description'] = $meta[ $key ][1];

				if ( 'text' === ( $arg['type'] ?? '' ) && '' !== $meta[ $key ][2] ) {
					$arg['placeholder'] = $meta[ $key ][2];
				}
			}

			unset( $arg );
		}

		unset( $widget );
	}

	/**
	 * Register a widget.
	 *
	 * @param string               $id     Widget ID.
	 * @param array<string, mixed> $widget Widget definition.
	 */
	public static function register( string $id, array $widget ): void {
		self::$widgets[ $id ] = $widget;
	}

	/**
	 * Get all widgets.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function all(): array {
		return self::$widgets;
	}

	/**
	 * Get a widget.
	 *
	 * @param string $id Widget ID.
	 * @return array<string, mixed>|null
	 */
	public static function get( string $id ): ?array {
		return self::$widgets[ $id ] ?? null;
	}

	/**
	 * Render a widget.
	 *
	 * @param string               $id   Widget ID.
	 * @param array<string, mixed> $args Widget arguments.
	 */
	public static function render( string $id, array $args = [] ): string {
		$widget = self::get( $id );

		if ( ! $widget ) {
			return '';
		}

		$args     = self::merge_args( (array) ( $widget['args'] ?? [] ), $args );
		$renderer = $widget['renderer'] ?? null;

		return is_callable( $renderer ) ? (string) call_user_func( $renderer, $args ) : '';
	}

	/**
	 * Merge arguments with the widget schema defaults.
	 *
	 * @param array<string, array<string, mixed>> $schema Argument schema.
	 * @param array<string, mixed>                $args   User arguments.
	 * @return array<string, mixed>
	 */
	private static function merge_args( array $schema, array $args ): array {
		$merged = [];

		foreach ( $schema as $key => $definition ) {
			$value = array_key_exists( $key, $args ) ? $args[ $key ] : ( $definition['default'] ?? null );

			if ( 'toggle' === ( $definition['type'] ?? '' ) ) {
				$value = filter_var( $value, FILTER_VALIDATE_BOOLEAN );
			}

			$merged[ $key ] = $value;
		}

		return $merged;
	}
}
