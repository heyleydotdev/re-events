<?php
/**
 * Test factories.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\CLI;

use ReEvents\Data\Ticket_Repository;
use ReEvents\Services\Booking_Service;

/**
 * Creates test fixtures.
 */
final class Test_Factory {

	/**
	 * Create an event.
	 *
	 * @param array<string, mixed> $args Overrides.
	 */
	public static function event( array $args = [] ): int {
		return (int) wp_insert_post(
			[
				'post_type'   => 'reevents_event',
				'post_status' => 'publish',
				'post_title'  => (string) ( $args['title'] ?? 'Test event' ),
				'meta_input'  => (array) ( $args['meta'] ?? [] ),
			]
		);
	}

	/**
	 * Create a ticket.
	 *
	 * @param int                  $event_id Event ID.
	 * @param array<string, mixed> $args     Overrides.
	 */
	public static function ticket( int $event_id, array $args = [] ): int {
		$result = ( new Ticket_Repository() )->save(
			array_merge(
				[
					'event_id' => $event_id,
					'name'     => 'Test ticket',
					'capacity' => 10,
				],
				$args
			)
		);

		return is_wp_error( $result ) ? 0 : (int) $result;
	}

	/**
	 * Create a booking.
	 *
	 * @param int                  $event_id  Event ID.
	 * @param int                  $ticket_id Ticket ID.
	 * @param array<string, mixed> $args      Overrides.
	 */
	public static function booking( int $event_id, int $ticket_id, array $args = [] ): int {
		$result = Booking_Service::create(
			array_merge(
				[
					'event_id'  => $event_id,
					'ticket_id' => $ticket_id,
					'quantity'  => 1,
				],
				$args
			)
		);

		return is_wp_error( $result ) ? 0 : (int) $result;
	}
}
