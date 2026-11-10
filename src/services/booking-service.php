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
use ReEvents\Data\Occurrence_Repository;
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
		add_filter( 'pre_delete_post', [ self::class, 'guard_delete' ], 10, 3 );

		if ( ! wp_next_scheduled( 'reevents_expire_bookings' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'reevents_expire_bookings' );
		}
	}

	/**
	 * Block deleting an event that still has bookings.
	 *
	 * @param mixed $delete      Short-circuit value.
	 * @param mixed $post        Post object.
	 * @param mixed $force_delete Force delete flag.
	 * @return mixed
	 */
	public static function guard_delete( $delete, $post, $force_delete ) {
		unset( $force_delete );
		if ( $post instanceof \WP_Post && 'reevents_event' === $post->post_type && ( new Booking_Repository() )->count_active_for_event( (int) $post->ID ) > 0 ) {
			return false;
		}

		return $delete;
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

		if ( $quantity > Booking_Validator::MAX_SEATS ) {
			return new WP_Error(
				'reevents_booking_too_many_seats',
				__( 'Too many seats selected.', 're-events' ),
				[
					'status' => 400,
					'errors' => [ 'quantity' => __( 'Too many seats selected.', 're-events' ) ],
				]
			);
		}

		if ( count( $attendees ) > Booking_Validator::MAX_ATTENDEES ) {
			return new WP_Error(
				'reevents_booking_too_many_attendees',
				__( 'There are too many attendees.', 're-events' ),
				[
					'status' => 400,
					'errors' => [ 'attendees' => __( 'There are too many attendees.', 're-events' ) ],
				]
			);
		}

		$attendees = self::filter_attendee_fields( $attendees );

		$domain = Booking_Validator::validate_domain( $event_id, $ticket_id, $quantity, $occurrence_id, $attendees );

		if ( $domain['errors'] ) {
			return new WP_Error(
				'' !== $domain['code'] ? $domain['code'] : 'reevents_booking_invalid',
				'' !== $domain['message'] ? $domain['message'] : __( 'The booking could not be created.', 're-events' ),
				[
					'status' => 400,
					'errors' => $domain['errors'],
				]
			);
		}

		$tickets = new Ticket_Repository();
		$ticket  = $tickets->find( $ticket_id );

		if ( ! $ticket ) {
			return new WP_Error( 'reevents_booking_invalid_ticket', __( 'Invalid ticket.', 're-events' ), [ 'status' => 400 ] );
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Booking transaction.
		$wpdb->query( 'START TRANSACTION' );

		if ( ! $tickets->reserve( $ticket_id, $quantity ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Booking transaction.
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'reevents_booking_sold_out', __( 'Not enough seats are available.', 're-events' ), [ 'status' => 409 ] );
		}

		if ( $occurrence_id > 0 && ! ( new Occurrence_Repository() )->reserve( $occurrence_id, $quantity ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Booking transaction.
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'reevents_booking_sold_out', __( 'Not enough seats are available for this time.', 're-events' ), [ 'status' => 409 ] );
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
	 * Build the result cache key for a booking token.
	 *
	 * @param string $token    Client token.
	 * @param int    $event_id Event ID.
	 */
	public static function token_cache_key( string $token, int $event_id ): string {
		return '' !== $token ? 'reevents_booking_token_' . md5( $token . '|' . $event_id ) : '';
	}

	/**
	 * Take the token lock for a booking attempt.
	 *
	 * @param string $token    Client token.
	 * @param int    $event_id Event ID.
	 */
	public static function claim_token( string $token, int $event_id ): bool {
		return '' !== $token && Lock::acquire( 'reevents_booking_lock_' . md5( $token . '|' . $event_id ) );
	}

	/**
	 * Release the token lock for a booking attempt.
	 *
	 * @param string $token    Client token.
	 * @param int    $event_id Event ID.
	 */
	public static function release_token( string $token, int $event_id ): void {
		if ( '' !== $token ) {
			Lock::release( 'reevents_booking_lock_' . md5( $token . '|' . $event_id ) );
		}
	}

	/**
	 * Cancel unpaid bookings past the hold window.
	 */
	public static function expire_pending(): void {
		$minutes = max( 5, (int) Settings::get( 'hold_minutes', 30 ) );
		$cutoff  = gmdate( 'Y-m-d H:i:s', time() - ( $minutes * MINUTE_IN_SECONDS ) );

		$repo    = new Booking_Repository();
		$last_id = 0;

		do {
			$ids      = $repo->pending_before( $cutoff, 100, $last_id );
			$id_count = count( $ids );

			foreach ( $ids as $id ) {
				self::update_status( (int) $id, 'cancelled' );
				$last_id = max( $last_id, (int) $id );
			}
		} while ( 100 === $id_count );
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

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Booking transaction.
		$wpdb->query( 'START TRANSACTION' );

		$repo    = new Booking_Repository();
		$booking = $repo->find_for_update( $id );

		if ( ! $booking ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Booking transaction.
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'reevents_booking_invalid_id', __( 'Invalid booking.', 're-events' ), [ 'status' => 404 ] );
		}

		if ( $booking['status'] === $status ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Booking transaction.
			$wpdb->query( 'COMMIT' );
			return true;
		}

		$released = in_array( $booking['status'], [ 'cancelled', 'rejected' ], true );
		$release  = in_array( $status, [ 'cancelled', 'rejected' ], true );
		$active   = in_array( $status, [ 'pending', 'confirmed' ], true );
		$tickets  = new Ticket_Repository();

		if ( $release && ! $released ) {
			if ( ! $repo->update_status( $id, $status ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Booking transaction.
				$wpdb->query( 'ROLLBACK' );
				return new WP_Error( 'reevents_booking_update_failed', __( 'The booking could not be updated.', 're-events' ), [ 'status' => 500 ] );
			}

			$tickets->release( (int) $booking['ticket_id'], (int) $booking['quantity'] );

			if ( (int) $booking['occurrence_id'] > 0 ) {
				( new Occurrence_Repository() )->release( (int) $booking['occurrence_id'], (int) $booking['quantity'] );
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Booking transaction.
			$wpdb->query( 'COMMIT' );

			do_action( 'reevents_booking_status_changed', $id, $status );

			return true;
		}

		if ( $active && $released ) {
			if ( ! $tickets->reserve( (int) $booking['ticket_id'], (int) $booking['quantity'] ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Booking transaction.
				$wpdb->query( 'ROLLBACK' );
				return new WP_Error( 'reevents_booking_sold_out', __( 'Not enough seats are available.', 're-events' ), [ 'status' => 409 ] );
			}

			$occurrences = new Occurrence_Repository();

			if ( (int) $booking['occurrence_id'] > 0 && ! $occurrences->reserve( (int) $booking['occurrence_id'], (int) $booking['quantity'] ) ) {
				$tickets->release( (int) $booking['ticket_id'], (int) $booking['quantity'] );
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Booking transaction.
				$wpdb->query( 'ROLLBACK' );
				return new WP_Error( 'reevents_booking_sold_out', __( 'Not enough seats are available for this time.', 're-events' ), [ 'status' => 409 ] );
			}

			if ( ! $repo->update_status( $id, $status ) ) {
				$tickets->release( (int) $booking['ticket_id'], (int) $booking['quantity'] );

				if ( (int) $booking['occurrence_id'] > 0 ) {
					$occurrences->release( (int) $booking['occurrence_id'], (int) $booking['quantity'] );
				}

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Booking transaction.
				$wpdb->query( 'ROLLBACK' );
				return new WP_Error( 'reevents_booking_update_failed', __( 'The booking could not be updated.', 're-events' ), [ 'status' => 500 ] );
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Booking transaction.
			$wpdb->query( 'COMMIT' );

			do_action( 'reevents_booking_status_changed', $id, $status );

			return true;
		}

		if ( ! $repo->update_status( $id, $status ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Booking transaction.
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'reevents_booking_update_failed', __( 'The booking could not be updated.', 're-events' ), [ 'status' => 500 ] );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Booking transaction.
		$wpdb->query( 'COMMIT' );

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
	 * Keep only configured attendee fields and cap their length.
	 *
	 * @param array<int, mixed> $attendees Attendees.
	 * @return array<int, mixed>
	 */
	private static function filter_attendee_fields( array $attendees ): array {
		$allowed = array_keys( Settings::attendee_fields() );

		foreach ( $attendees as &$attendee ) {
			if ( ! is_array( $attendee ) ) {
				continue;
			}

			$fields = is_array( $attendee['fields'] ?? null ) ? $attendee['fields'] : [];
			$clean  = [];

			foreach ( $allowed as $key ) {
				if ( isset( $fields[ $key ] ) && is_scalar( $fields[ $key ] ) ) {
					$clean[ $key ] = mb_substr( (string) $fields[ $key ], 0, 5000 );
				}
			}

			$attendee['fields'] = $clean;
		}

		unset( $attendee );

		return $attendees;
	}

	/**
	 * Build a booking reference.
	 */
	private static function reference(): string {
		return wp_generate_password( 32, false, false );
	}
}
