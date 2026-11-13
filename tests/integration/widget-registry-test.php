<?php
/**
 * Widget registry argument tests.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Tests\Integration;

use ReEvents\Render\Widget_Registry;
use WP_UnitTestCase;

/**
 * Guards the registered widget arguments.
 */
final class Widget_Registry_Test extends WP_UnitTestCase {

	/**
	 * New widget arguments stay in the registry.
	 */
	public function test_arguments_are_registered(): void {
		Widget_Registry::register_defaults();

		$expected = [
			'event-details' => [
				'date_format',
				'time_format',
				'show_image',
				'image_size',
				'show_title',
				'title_tag',
				'show_status',
				'show_price',
				'show_seats_left',
				'show_categories',
				'show_tags',
				'show_excerpt',
				'show_end',
				'show_venue_address',
				'show_venue_map_link',
			],
			'bookings'      => [
				'limit',
				'heading',
				'empty_label',
				'date_format',
				'time_format',
				'lookup_heading',
				'lookup_button_label',
				'reference_label',
				'email_label',
				'cancel_label',
				'reactivate_label',
			],
			'venues-list'   => [
				'search',
				'order',
				'orderby',
				'upcoming',
				'show_image',
				'show_address',
				'show_website',
				'show_map_link',
				'show_next_event',
				'excerpt_length',
				'title_tag',
				'link',
				'date_format',
				'time_format',
			],
			'events-search' => [
				'show_tag',
				'tag_label',
				'all_tags_label',
				'show_organizer',
				'organizer_label',
				'all_organizers_label',
				'show_order',
				'order',
				'paginate',
				'pagination_key',
				'show_image',
				'show_price',
				'show_excerpt',
				'show_categories',
				'show_tags',
				'show_organizers',
				'show_end',
				'show_seats_left',
				'show_cancelled',
				'title_tag',
				'link',
				'date_format',
				'time_format',
			],
			'calendar'      => [
				'category',
				'tag',
				'venue',
				'organizer',
				'show_cancelled',
				'show_today',
				'week_start',
				'month_format',
				'time_format',
			],
			'map'           => [
				'venue',
				'upcoming',
				'search',
				'center',
				'zoom',
				'fit',
				'link',
				'provider',
				'show_address',
				'show_next_event',
				'date_format',
				'time_format',
			],
			'booking-form'  => [
				'heading',
				'show_price',
				'default_quantity',
				'max_quantity',
				'ticket',
				'occurrence',
				'hide_sold_out',
				'date_format',
				'time_format',
			],
		];

		foreach ( $expected as $widget_id => $args ) {
			$widget = Widget_Registry::get( $widget_id );
			self::assertNotNull( $widget, $widget_id );

			foreach ( $args as $arg ) {
				self::assertArrayHasKey( $arg, (array) $widget['args'], $widget_id . ':' . $arg );
			}
		}
	}
}
