<?php
/**
 * Booking service.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Services;

use ReEvents\Admin\Settings;
use ReEvents\Data\Booking_Repository;
use ReEvents\Data\Ticket_Repository;
use WP_Error;

/**
 * Creates and cancels bookings with capacity checks.
 */
final class Booking_Service {

	/**
	 * Hook into WordPress.
	 */
	public static function register(): void {
		add_action( 'reevents_expire_bookings', [ self::class, 'expire_pending' ] );

		if ( ! wp_next_scheduled( 'reevents_expire_bookings' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'reevents_expire_bookings' );
		}
	}

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
			return new WP_Error( 'reevents_booking_invalid_event', __( 'Invalid event.', 're-events' ), [ 'status' => 400 ] );
		}

		$tickets = new Ticket_Repository();
		$ticket  = $tickets->find( $ticket_id );

		if ( ! $ticket || (int) $ticket['event_id'] !== $event_id ) {
			return new WP_Error( 'reevents_booking_invalid_ticket', __( 'Invalid ticket.', 're-events' ), [ 'status' => 400 ] );
		}

		if ( 'active' !== $ticket['status'] ) {
			return new WP_Error( 'reevents_booking_ticket_inactive', __( 'This ticket is not on sale.', 're-events' ), [ 'status' => 400 ] );
		}

		$now = current_time( 'mysql', true );

		if ( '' !== $ticket['sale_start_utc'] && $now < $ticket['sale_start_utc'] ) {
			return new WP_Error( 'reevents_booking_sale_not_started', __( 'Ticket sales have not started.', 're-events' ), [ 'status' => 400 ] );
		}

		if ( '' !== $ticket['sale_end_utc'] && $now > $ticket['sale_end_utc'] ) {
			return new WP_Error( 'reevents_booking_sale_ended', __( 'Ticket sales have ended.', 're-events' ), [ 'status' => 400 ] );
		}

		if ( $quantity < (int) $ticket['min_per_booking'] ) {
			return new WP_Error( 'reevents_booking_min_quantity', __( 'Too few seats selected.', 're-events' ), [ 'status' => 400 ] );
		}

		if ( (int) $ticket['max_per_booking'] > 0 && $quantity > (int) $ticket['max_per_booking'] ) {
			return new WP_Error( 'reevents_booking_max_quantity', __( 'Too many seats selected.', 're-events' ), [ 'status' => 400 ] );
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Booking transaction.
		$wpdb->query( 'START TRANSACTION' );

		if ( ! $tickets->reserve( $ticket_id, $quantity ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Booking transaction.
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'reevents_booking_sold_out', __( 'Not enough seats are available.', 're-events' ), [ 'status' => 409 ] );
		}

		$total  = (int) $ticket['price_minor'] * $quantity;
		$repo   = new Booking_Repository();
		$status = 0 === $total && ! Settings::get( 'require_approval', false ) ? 'confirmed' : 'pending';
		$id     = $repo->create(
			[
				'reference'     => self::reference(),
				'event_id'      => $event_id,
				'occurrence_id' => $occurrence_id,
				'ticket_id'     => $ticket_id,
				'user_id'       => $user_id,
				'quantity'      => $quantity,
				'status'        => $status,
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

		do_action( 'reevents_booking_created', (int) $id );

		return (int) $id;
	}

	/**
	 * Cancel unpaid bookings past the hold window.
	 */
	public static function expire_pending(): void {
		$minutes = max( 5, (int) Settings::get( 'hold_minutes', 30 ) );
		$cutoff  = gmdate( 'Y-m-d H:i:s', time() - ( $minutes * MINUTE_IN_SECONDS ) );

		foreach ( ( new Booking_Repository() )->pending_before( $cutoff ) as $id ) {
			self::update_status( (int) $id, 'cancelled' );
		}
	}

	/**
	 * Update a booking status and adjust reserved seats.
	 *
	 * @param int    $id     Booking ID.
	 * @param string $status New status.
	 * @return bool|WP_Error
	 */
	public static function update_status( int $id, string $status ) {
		$allowed = [ 'pending', 'confirmed', 'cancelled', 'rejected' ];

		if ( ! in_array( $status, $allowed, true ) ) {
			return new WP_Error( 'reevents_booking_invalid_status', __( 'Invalid booking status.', 're-events' ), [ 'status' => 400 ] );
		}

		$repo    = new Booking_Repository();
		$booking = $repo->find( $id );

		if ( ! $booking ) {
			return new WP_Error( 'reevents_booking_invalid_id', __( 'Invalid booking.', 're-events' ), [ 'status' => 404 ] );
		}

		if ( $booking['status'] === $status ) {
			return true;
		}

		$released = in_array( $booking['status'], [ 'cancelled', 'rejected' ], true );
		$release  = in_array( $status, [ 'cancelled', 'rejected' ], true );
		$active   = in_array( $status, [ 'pending', 'confirmed' ], true );
		$tickets  = new Ticket_Repository();

		if ( $release && ! $released ) {
			if ( ! $repo->update_status( $id, $status ) ) {
				return new WP_Error( 'reevents_booking_update_failed', __( 'The booking could not be updated.', 're-events' ), [ 'status' => 500 ] );
			}

			$tickets->release( (int) $booking['ticket_id'], (int) $booking['quantity'] );

			do_action( 'reevents_booking_status_changed', $id, $status );

			return true;
		}

		if ( $active && $released ) {
			if ( ! $tickets->reserve( (int) $booking['ticket_id'], (int) $booking['quantity'] ) ) {
				return new WP_Error( 'reevents_booking_sold_out', __( 'Not enough seats are available.', 're-events' ), [ 'status' => 409 ] );
			}

			if ( ! $repo->update_status( $id, $status ) ) {
				$tickets->release( (int) $booking['ticket_id'], (int) $booking['quantity'] );
				return new WP_Error( 'reevents_booking_update_failed', __( 'The booking could not be updated.', 're-events' ), [ 'status' => 500 ] );
			}

			do_action( 'reevents_booking_status_changed', $id, $status );

			return true;
		}

		if ( ! $repo->update_status( $id, $status ) ) {
			return new WP_Error( 'reevents_booking_update_failed', __( 'The booking could not be updated.', 're-events' ), [ 'status' => 500 ] );
		}

		do_action( 'reevents_booking_status_changed', $id, $status );

		return true;
	}

	/**
	 * Cancel a booking and release its seats.
	 *
	 * @param int $id Booking ID.
	 * @return bool|WP_Error
	 */
	public static function cancel( int $id ) {
		return self::update_status( $id, 'cancelled' );
	}

	/**
	 * Build a booking reference.
	 */
	private static function reference(): string {
		return wp_generate_password( 32, false, false );
	}
}
