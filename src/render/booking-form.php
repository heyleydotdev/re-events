<?php
/**
 * Booking form renderer.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Render;

use DateTimeImmutable;
use DateTimeZone;
use ReEvents\Admin\Settings;
use ReEvents\Data\Event_Repository;
use ReEvents\Data\Occurrence_Repository;
use ReEvents\Data\Ticket_Repository;

/**
 * Renders the booking form.
 */
final class Booking_Form {

	/**
	 * Render the booking form.
	 *
	 * @param array<string, mixed> $args Widget arguments.
	 */
	public static function render( array $args ): string {
		$event_id = (int) ( $args['event'] ?? 0 );

		if ( ! $event_id ) {
			$event_id = (int) get_the_ID();
		}

		if ( 'reevents_event' !== get_post_type( $event_id ) || 'publish' !== get_post_status( $event_id ) ) {
			return '';
		}

		if ( 'cancelled' === (string) get_post_meta( $event_id, '_reevents_status', true ) ) {
			return '<p class="reevents-empty">' . esc_html__( 'This event has been cancelled.', 're-events' ) . '</p>';
		}

		$tickets = ( new Ticket_Repository() )->for_event( $event_id, 'active' );

		if ( ! empty( $args['hide_sold_out'] ) ) {
			$tickets = array_values(
				array_filter(
					$tickets,
					static fn( array $ticket ): bool => ! self::sold_out( $ticket )
				)
			);
		}

		if ( ! $tickets ) {
			return '<p class="reevents-empty">' . esc_html__( 'No tickets are available.', 're-events' ) . '</p>';
		}

		$event       = ( new Event_Repository() )->find( $event_id );
		$date_fmt    = (string) ( $args['date_format'] ?? '' );
		$time_fmt    = (string) ( $args['time_format'] ?? '' );
		$occurrences = $event && $event->timeslots ? self::occurrences( $event_id, $event->timezone, $date_fmt, $time_fmt ) : [];

		if ( $event && $event->timeslots && ! $occurrences ) {
			return '<p class="reevents-empty">' . esc_html__( 'No times are available.', 're-events' ) . '</p>';
		}

		$selected_ticket     = 0;
		$selected_occurrence = 0;

		foreach ( $tickets as $ticket ) {
			if ( (int) ( $args['ticket'] ?? 0 ) === (int) $ticket['id'] ) {
				$selected_ticket = (int) $ticket['id'];
				break;
			}
		}

		foreach ( $occurrences as $occurrence ) {
			if ( (int) ( $args['occurrence'] ?? 0 ) === (int) $occurrence['id'] ) {
				$selected_occurrence = (int) $occurrence['id'];
				break;
			}
		}

		wp_enqueue_script(
			'reevents-booking',
			plugins_url( 'assets/js/frontend-booking.js', RE_EVENTS_FILE ),
			[],
			RE_EVENTS_VERSION,
			true
		);

		return Template::render(
			'booking-form',
			[
				'event_id'            => $event_id,
				'tickets'             => $tickets,
				'occurrences'         => $occurrences,
				'selected_ticket'     => $selected_ticket,
				'selected_occurrence' => $selected_occurrence,
				'fields'              => Settings::attendee_fields(),
				'state'               => self::state(),
				'action'              => admin_url( 'admin-post.php' ),
				'endpoint'            => rest_url( 're-events/v1/events/' . $event_id . '/bookings' ),
				'token'               => wp_generate_password( 32, false, false ),
				'nonce'               => is_user_logged_in() ? wp_create_nonce( 'wp_rest' ) : '',
				'args'                => $args,
			]
		);
	}

	/**
	 * Upcoming bookable occurrences.
	 *
	 * @param int    $event_id    Event ID.
	 * @param string $timezone    Event timezone.
	 * @param string $date_format Date format override.
	 * @param string $time_format Time format override.
	 * @return array<int, array{id:int,label:string}>
	 */
	private static function occurrences( int $event_id, string $timezone, string $date_format = '', string $time_format = '' ): array {
		$now   = current_time( 'mysql', true );
		$items = [];

		foreach ( ( new Occurrence_Repository() )->get_for_event( $event_id, 200 ) as $row ) {
			if ( 'scheduled' !== (string) $row['status'] || (string) $row['start_utc'] < $now ) {
				continue;
			}

			$remaining = (int) $row['capacity'] > 0 ? max( 0, (int) $row['capacity'] - (int) $row['sold'] ) : null;
			$label     = self::occurrence_label( (string) $row['start_utc'], $timezone, $date_format, $time_format );

			if ( null !== $remaining ) {
				$label .= ' (' . sprintf( /* translators: %d: remaining seats. */ __( '%d left', 're-events' ), $remaining ) . ')';
			}

			$items[] = [
				'id'    => (int) $row['id'],
				'label' => $label,
			];
		}

		return $items;
	}

	/**
	 * Format an occurrence start.
	 *
	 * @param string $utc         UTC datetime.
	 * @param string $timezone    Event timezone.
	 * @param string $date_format Date format override.
	 * @param string $time_format Time format override.
	 */
	private static function occurrence_label( string $utc, string $timezone, string $date_format = '', string $time_format = '' ): string {
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $utc, new DateTimeZone( 'UTC' ) );

		if ( ! $date instanceof DateTimeImmutable ) {
			return $utc;
		}

		try {
			$zone = new DateTimeZone( $timezone );
		} catch ( \Exception ) {
			$zone = new DateTimeZone( 'UTC' );
		}

		$format = ( '' !== $date_format ? $date_format : Event_Format::date_format() ) . ' ' . ( '' !== $time_format ? $time_format : Event_Format::time_format() );

		return (string) wp_date( $format, $date->getTimestamp(), $zone );
	}

	/**
	 * Whether a ticket has no seats left.
	 *
	 * @param array<string, mixed> $ticket Ticket row.
	 */
	private static function sold_out( array $ticket ): bool {
		$capacity = (int) ( $ticket['capacity'] ?? 0 );

		return $capacity > 0 && (int) ( $ticket['sold'] ?? 0 ) >= $capacity;
	}

	/**
	 * Read the redirect state.
	 *
	 * @return array{status:string,reference:string,message:string}
	 */
	private static function state(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only result flags.
		$token = isset( $_GET['reevents_booking_token'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['reevents_booking_token'] ) ) : '';
		$code  = isset( $_GET['code'] ) ? sanitize_key( wp_unslash( (string) $_GET['code'] ) ) : '';
		// phpcs:enable

		if ( '' !== $token ) {
			$result = get_transient( 'reevents_booking_result_' . $token );

			if ( is_array( $result ) ) {
				delete_transient( 'reevents_booking_result_' . $token );

				return [
					'status'    => 'success',
					'reference' => (string) ( $result['reference'] ?? '' ),
					'message'   => '',
				];
			}
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only result flag.
		$error = isset( $_GET['reevents_booking'] ) && 'error' === sanitize_key( wp_unslash( (string) $_GET['reevents_booking'] ) );

		return [
			'status'    => $error ? 'error' : '',
			'reference' => '',
			'message'   => $error ? self::message( $code ) : '',
		];
	}

	/**
	 * Map an error code to a message.
	 *
	 * @param string $code Error code.
	 */
	private static function message( string $code ): string {
		$messages = [
			'reevents_booking_sold_out'           => __( 'Not enough seats are available.', 're-events' ),
			'reevents_booking_sale_not_started'   => __( 'Ticket sales have not started.', 're-events' ),
			'reevents_booking_sale_ended'         => __( 'Ticket sales have ended.', 're-events' ),
			'reevents_booking_min_quantity'       => __( 'Too few seats selected.', 're-events' ),
			'reevents_booking_max_quantity'       => __( 'Too many seats selected.', 're-events' ),
			'reevents_booking_too_many_seats'     => __( 'Too many seats selected.', 're-events' ),
			'reevents_booking_rate_limited'       => __( 'Too many attempts. Try again later.', 're-events' ),
			'reevents_rate_limited'               => __( 'Too many attempts. Try again later.', 're-events' ),
			'reevents_booking_event_cancelled'    => __( 'This event has been cancelled.', 're-events' ),
			'reevents_booking_invalid_occurrence' => __( 'The selected time is not available.', 're-events' ),
			'reevents_booking_in_progress'        => __( 'This booking is already being processed. Please wait a moment.', 're-events' ),
			'reevents_booking_too_many_attendees' => __( 'There are more attendees than seats.', 're-events' ),
			'invalid_attendee'                    => __( 'Enter a valid name and email address.', 're-events' ),
			'required_field'                      => __( 'Fill in all required attendee fields.', 're-events' ),
			'reevents_payment_cancelled'          => __( 'The payment was cancelled.', 're-events' ),
			'reevents_payment_pending'            => __( 'The payment is not complete yet.', 're-events' ),
			'reevents_booking_awaiting_approval'  => __( 'The payment is complete. The booking is awaiting approval.', 're-events' ),
			'reevents_payment_missing_key'        => __( 'The payment gateway is not configured.', 're-events' ),
		];

		return $messages[ $code ] ?? __( 'The booking could not be created. Please try again.', 're-events' );
	}
}
