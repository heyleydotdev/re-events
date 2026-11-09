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
					'class'             => [
						'type'    => 'text',
						'default' => '',
					],
					'wrapper_class'     => [
						'type'    => 'text',
						'default' => '',
					],
					'id'                => [
						'type'    => 'number',
						'default' => 0,
					],
					'show_venue'        => [
						'type'    => 'toggle',
						'default' => true,
					],
					'show_organizers'   => [
						'type'    => 'toggle',
						'default' => true,
					],
					'show_online_links' => [
						'type'    => 'toggle',
						'default' => true,
					],
					'show_content'      => [
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
					'class'              => [
						'type'    => 'text',
						'default' => '',
					],
					'wrapper_class'      => [
						'type'    => 'text',
						'default' => '',
					],
					'search_label'       => [
						'type'    => 'text',
						'default' => __( 'Search', 're-events' ),
					],
					'search_placeholder' => [
						'type'    => 'text',
						'default' => '',
					],
					'category_label'     => [
						'type'    => 'text',
						'default' => __( 'Category', 're-events' ),
					],
					'venue_label'        => [
						'type'    => 'text',
						'default' => __( 'Venue', 're-events' ),
					],
					'from_label'         => [
						'type'    => 'text',
						'default' => __( 'From', 're-events' ),
					],
					'to_label'           => [
						'type'    => 'text',
						'default' => __( 'To', 're-events' ),
					],
					'upcoming_label'     => [
						'type'    => 'text',
						'default' => __( 'Only upcoming', 're-events' ),
					],
					'submit_label'       => [
						'type'    => 'text',
						'default' => __( 'Search', 're-events' ),
					],
					'clear_label'        => [
						'type'    => 'text',
						'default' => __( 'Clear', 're-events' ),
					],
					'limit'              => [
						'type'    => 'number',
						'default' => 20,
					],
					'show_keyword'       => [
						'type'    => 'toggle',
						'default' => true,
					],
					'show_category'      => [
						'type'    => 'toggle',
						'default' => true,
					],
					'show_venue'         => [
						'type'    => 'toggle',
						'default' => true,
					],
					'show_dates'         => [
						'type'    => 'toggle',
						'default' => true,
					],
					'show_upcoming'      => [
						'type'    => 'toggle',
						'default' => true,
					],
					'show_results'       => [
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
						'type'    => 'text',
						'default' => '',
					],
					'wrapper_class'     => [
						'type'    => 'text',
						'default' => '',
					],
					'ticket_label'      => [
						'type'    => 'text',
						'default' => __( 'Ticket', 're-events' ),
					],
					'quantity_label'    => [
						'type'    => 'text',
						'default' => __( 'Seats', 're-events' ),
					],
					'name_label'        => [
						'type'    => 'text',
						'default' => __( 'Name', 're-events' ),
					],
					'name_placeholder'  => [
						'type'    => 'text',
						'default' => '',
					],
					'email_label'       => [
						'type'    => 'text',
						'default' => __( 'Email', 're-events' ),
					],
					'email_placeholder' => [
						'type'    => 'text',
						'default' => '',
					],
					'button_label'      => [
						'type'    => 'text',
						'default' => __( 'Book', 're-events' ),
					],
					'success_message'   => [
						'type'    => 'text',
						'default' => __( 'Booking confirmed.', 're-events' ),
					],
					'event'             => [
						'type'    => 'number',
						'default' => 0,
					],
				],
			]
		);
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
