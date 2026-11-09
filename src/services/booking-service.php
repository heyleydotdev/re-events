<?php
/**
 * Booking service.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Services;

use ReEvents\Data\Booking_Repository;
use ReEvents\Data\Ticket_Repository;
use WP_Error;

/**
 * Creates and cancels bookings with capacity checks.
 */
final class Booking_Service {

	/**
	 * Create a booking.
	 *
	 * @param array<string, mixed> $args Booking data.
	 * @return int|WP_Error
	 */
	public static function create( array $args ) {
		$event_id      = (int) ( $args['event_id'] ?? 0 );
		$ticket_id     = (int) ( $args['ticket_id'] ?? 0 );
		$occurrence_id = (int) ( $args['occurrence_id'] ?? 0 );
		$quantity      = max( 1, (int) ( $args['quantity'] ?? 1 ) );
		$user_id       = (int) ( $args['user_id'] ?? get_current_user_id() );
		$attendees     = (array) ( $args['attendees'] ?? [] );

		if ( 'reevents_event' !== get_post_type( $event_id ) || 'publish' !== get_post_status( $event_id ) ) {
			return new WP_Error( 'reevents_booking_invalid_event', __( 'Invalid event.', 're-events' ) );
		}

		$tickets = new Ticket_Repository();
		$ticket  = $tickets->find( $ticket_id );

		if ( ! $ticket || (int) $ticket['event_id'] !== $event_id ) {
			return new WP_Error( 'reevents_booking_invalid_ticket', __( 'Invalid ticket.', 're-events' ) );
		}

		if ( 'active' !== $ticket['status'] ) {
			return new WP_Error( 'reevents_booking_ticket_inactive', __( 'This ticket is not on sale.', 're-events' ) );
		}

		$now = current_time( 'mysql', true );

		if ( '' !== $ticket['sale_start_utc'] && $now < $ticket['sale_start_utc'] ) {
			return new WP_Error( 'reevents_booking_sale_not_started', __( 'Ticket sales have not started.', 're-events' ) );
		}

		if ( '' !== $ticket['sale_end_utc'] && $now > $ticket['sale_end_utc'] ) {
			return new WP_Error( 'reevents_booking_sale_ended', __( 'Ticket sales have ended.', 're-events' ) );
		}

		if ( $quantity < (int) $ticket['min_per_booking'] ) {
			return new WP_Error( 'reevents_booking_min_quantity', __( 'Too few seats selected.', 're-events' ) );
		}

		if ( (int) $ticket['max_per_booking'] > 0 && $quantity > (int) $ticket['max_per_booking'] ) {
			return new WP_Error( 'reevents_booking_max_quantity', __( 'Too many seats selected.', 're-events' ) );
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Booking transaction.
		$wpdb->query( 'START TRANSACTION' );

		if ( ! $tickets->reserve( $ticket_id, $quantity ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Booking transaction.
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'reevents_booking_sold_out', __( 'Not enough seats are available.', 're-events' ) );
		}

		$total = (int) $ticket['price_minor'] * $quantity;
		$repo  = new Booking_Repository();
		$id    = $repo->create(
			[
				'event_id'      => $event_id,
				'occurrence_id' => $occurrence_id,
				'ticket_id'     => $ticket_id,
				'user_id'       => $user_id,
				'quantity'      => $quantity,
				'status'        => 0 === $total ? 'confirmed' : 'pending',
				'total_minor'   => $total,
				'currency'      => (string) $ticket['currency'],
			]
		);

		if ( is_wp_error( $id ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Booking transaction.
			$wpdb->query( 'ROLLBACK' );
			return $id;
		}

		foreach ( $attendees as $attendee ) {
			if ( ! is_array( $attendee ) ) {
				continue;
			}

			$attendee['ticket_id'] = $ticket_id;
			$result                = $repo->add_attendee( (int) $id, $attendee );

			if ( is_wp_error( $result ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Booking transaction.
				$wpdb->query( 'ROLLBACK' );
				return $result;
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Booking transaction.
		$wpdb->query( 'COMMIT' );

		return (int) $id;
	}

	/**
	 * Cancel a booking and release its seats.
	 *
	 * @param int $id Booking ID.
	 * @return bool|WP_Error
	 */
	public static function cancel( int $id ) {
		$repo    = new Booking_Repository();
		$booking = $repo->find( $id );

		if ( ! $booking ) {
			return new WP_Error( 'reevents_booking_invalid_id', __( 'Invalid booking.', 're-events' ) );
		}

		if ( 'cancelled' === $booking['status'] ) {
			return true;
		}

		if ( ! $repo->update_status( $id, 'cancelled' ) ) {
			return new WP_Error( 'reevents_booking_cancel_failed', __( 'The booking could not be cancelled.', 're-events' ) );
		}

		( new Ticket_Repository() )->release( (int) $booking['ticket_id'], (int) $booking['quantity'] );

		return true;
	}
}
