<?php
/**
 * Bookings renderer.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Render;

use ReEvents\Admin\Settings;
use ReEvents\Data\Booking_Repository;
use ReEvents\Data\Event_Repository;
use ReEvents\Data\Occurrence_Repository;
use ReEvents\Data\Ticket_Repository;
use ReEvents\Services\Booking_Access;
use ReEvents\Services\Booking_Self_Service;
use ReEvents\Services\Currency;
use ReEvents\Services\Rate_Limit;

/**
 * Renders the visitor's own bookings.
 */
final class Bookings {

	/**
	 * Render the widget.
	 *
	 * @param array<string, mixed> $args Widget arguments.
	 */
	public static function render( array $args ): string {
		if ( ! Booking_Self_Service::enabled() ) {
			return '';
		}

		$args     = self::defaults( $args );
		$bookings = [];
		$single   = null;
		$notice   = self::notice();
		$lookup   = null;

		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- Read-only credentials.
		$signed_id   = isset( $_GET['reevents_booking'] ) ? absint( wp_unslash( $_GET['reevents_booking'] ) ) : 0;
		$credentials = [
			'reference' => isset( $_GET['reevents_reference'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['reevents_reference'] ) ) : '',
			'key'       => isset( $_GET['reevents_key'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['reevents_key'] ) ) : '',
		];

		if ( $signed_id > 0 ) {
			$booking = ( new Booking_Repository() )->find( $signed_id );

			if ( $booking && Booking_Access::authorize( $booking, $credentials ) ) {
				$single = $booking;
			} else {
				$notice = self::error_notice( __( 'This booking link is not valid.', 're-events' ) );
			}
		} elseif ( isset( $_POST['reevents_lookup'] ) ) {
			$nonce = isset( $_POST['reevents_lookup_nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['reevents_lookup_nonce'] ) ) : '';

			if ( ! wp_verify_nonce( $nonce, 'reevents_lookup' ) ) {
				$notice = self::error_notice( __( 'The request expired. Please try again.', 're-events' ) );
			} elseif ( Rate_Limit::hit( 'booking_lookup', 10, MINUTE_IN_SECONDS ) ) {
				$notice = self::error_notice( __( 'Too many lookups. Please try again later.', 're-events' ) );
			} elseif ( Settings::get( 'self_service_guest', true ) ) {
				$reference = isset( $_POST['reevents_reference'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['reevents_reference'] ) ) : '';
				$email     = isset( $_POST['reevents_email'] ) ? sanitize_email( wp_unslash( (string) $_POST['reevents_email'] ) ) : '';
				$booking   = Booking_Access::lookup( $reference, $email );

				if ( $booking ) {
					$single                   = $booking;
					$credentials['reference'] = $reference;
					$credentials['email']     = $email;
				} else {
					$notice = self::error_notice( __( 'No booking matches that reference and email.', 're-events' ) );
				}
			}
		}

		$user_id = get_current_user_id();

		if ( ! $single && $user_id > 0 ) {
			$query    = ( new Booking_Repository() )->query(
				[
					'user_id' => $user_id,
					'limit'   => (int) $args['limit'],
				]
			);
			$bookings = $query['items'];
		}

		if ( ! $single && ! $bookings && Settings::get( 'self_service_guest', true ) ) {
			$lookup = [
				'action'    => self::current_url(),
				'nonce'     => wp_create_nonce( 'reevents_lookup' ),
				'heading'   => (string) $args['lookup_heading'],
				'button'    => (string) $args['lookup_button_label'],
				'reference' => (string) $args['reference_label'],
				'email'     => (string) $args['email_label'],
			];
		}
		// phpcs:enable

		$items = $single ? [ $single ] : $bookings;
		$cards = self::cards( $items, $args, $credentials );

		return Template::render(
			'bookings',
			[
				'heading' => (string) $args['heading'],
				'notice'  => $notice,
				'cards'   => $cards,
				'lookup'  => $lookup,
				'empty'   => (string) $args['empty_label'],
				'args'    => $args,
			]
		);
	}

	/**
	 * Build the view model for each booking.
	 *
	 * @param array<int, array<string, mixed>> $bookings    Bookings.
	 * @param array<string, mixed>             $args        Widget args.
	 * @param array<string, mixed>             $credentials Request credentials.
	 * @return array<int, array<string, mixed>>
	 */
	private static function cards( array $bookings, array $args, array $credentials ): array {
		if ( ! $bookings ) {
			return [];
		}

		$event_ids   = array_map( static fn( array $booking ): int => (int) $booking['event_id'], $bookings );
		$events      = ( new Event_Repository() )->find_many( $event_ids );
		$occurrences = ( new Occurrence_Repository() )->find_many(
			array_map( static fn( array $booking ): int => (int) $booking['occurrence_id'], $bookings )
		);
		$rules       = Booking_Self_Service::rules_many( $bookings, $events, $occurrences );
		$tickets     = [];
		$grouped     = ( new Ticket_Repository() )->for_events( $event_ids );

		foreach ( $grouped as $event_tickets ) {
			foreach ( $event_tickets as $ticket ) {
				$tickets[ (int) $ticket['id'] ] = $ticket;
			}
		}

		$cards               = [];
		$credentials_context = [
			'reference' => (string) ( $credentials['reference'] ?? '' ),
			'key'       => (string) ( $credentials['key'] ?? '' ),
			'email'     => (string) ( $credentials['email'] ?? '' ),
		];

		foreach ( $bookings as $booking ) {
			$id            = (int) $booking['id'];
			$event         = $events[ (int) $booking['event_id'] ] ?? null;
			$booking_rules = $rules[ $id ] ?? [];
			$occurrence_id = (int) $booking['occurrence_id'];
			$ticket        = $tickets[ (int) $booking['ticket_id'] ] ?? null;

			$when = '';

			if ( $occurrence_id > 0 && isset( $occurrences[ $occurrence_id ] ) ) {
				$when = Event_Format::occurrence_start( $occurrences[ $occurrence_id ], (string) $args['date_format'], (string) $args['time_format'] );
			} elseif ( $event ) {
				$when = Event_Format::start( $event, true, (string) $args['date_format'], (string) $args['time_format'] );
			}

			$total = (int) $booking['total_minor'] > 0
				? Currency::format( (int) $booking['total_minor'], (string) $booking['currency'] )
				: (string) $args['free_label'];

			$cards[] = [
				'id'          => $id,
				'reference'   => (string) $booking['reference'],
				'status'      => (string) $booking['status'],
				'status_text' => self::status_label( (string) $booking['status'] ),
				'event_title' => $event ? $event->title : (string) ( $booking['event_title'] ?? '' ),
				'event_url'   => $event ? (string) get_permalink( $event->id ) : '',
				'when'        => $when,
				'ticket'      => (string) ( $ticket['name'] ?? '' ),
				'quantity'    => (int) $booking['quantity'],
				'total'       => $total,
				'actions'     => self::actions( $booking, $booking_rules, $credentials_context, $args ),
			];
		}

		return $cards;
	}

	/**
	 * Build the allowed actions for a booking.
	 *
	 * @param array<string, mixed> $booking     Booking.
	 * @param array<string, mixed> $rules       Rules.
	 * @param array<string, mixed> $credentials Credentials.
	 * @param array<string, mixed> $args        Widget args.
	 * @return array<string, mixed>
	 */
	private static function actions( array $booking, array $rules, array $credentials, array $args ): array {
		$id     = (int) $booking['id'];
		$fields = [];

		if ( '' !== (string) $credentials['key'] ) {
			$fields['key'] = (string) $credentials['key'];
		} elseif ( '' !== (string) $credentials['reference'] && ! Booking_Access::is_owner( $booking ) ) {
			$fields['reference'] = (string) $credentials['reference'];
			$fields['email']     = (string) ( $credentials['email'] ?? '' );
		}

		$actions = [
			'url'              => admin_url( 'admin-post.php' ),
			'redirect'         => self::current_url(),
			'nonce'            => wp_create_nonce( 'reevents_booking_action_' . $id ),
			'booking'          => $id,
			'fields'           => $fields,
			'can_cancel'       => ! empty( $rules['can_cancel'] ),
			'can_reactivate'   => ! empty( $rules['can_reactivate'] ),
			'refund'           => ! empty( $rules['refund_on_cancel'] ),
			'cancel_label'     => (string) $args['cancel_label'],
			'reactivate_label' => (string) $args['reactivate_label'],
			'cancel_note'      => empty( $rules['can_cancel'] ) ? Booking_Self_Service::message( (string) ( $rules['cancel_reason'] ?? 'status' ) ) : '',
			'reactivate_note'  => empty( $rules['can_reactivate'] ) ? Booking_Self_Service::message( (string) ( $rules['reactivate_reason'] ?? 'status' ) ) : '',
		];

		return $actions;
	}

	/**
	 * Status label.
	 *
	 * @param string $status Status.
	 */
	private static function status_label( string $status ): string {
		switch ( $status ) {
			case 'confirmed':
				return __( 'Confirmed', 're-events' );
			case 'cancelled':
				return __( 'Cancelled', 're-events' );
			case 'rejected':
				return __( 'Rejected', 're-events' );
			default:
				return __( 'Pending', 're-events' );
		}
	}

	/**
	 * Notice from a returned action.
	 *
	 * @return array{type:string,message:string}|null
	 */
	private static function notice(): ?array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Result flag from a redirect.
		$result = isset( $_GET['reevents_booking_result'] ) ? sanitize_key( wp_unslash( (string) $_GET['reevents_booking_result'] ) ) : '';
		$reason = isset( $_GET['reevents_booking_reason'] ) ? sanitize_key( wp_unslash( (string) $_GET['reevents_booking_reason'] ) ) : '';
		// phpcs:enable

		if ( 'cancelled' === $result ) {
			return [
				'type'    => 'success',
				'message' => __( 'Booking cancelled.', 're-events' ),
			];
		}

		if ( 'reactivated' === $result ) {
			return [
				'type'    => 'success',
				'message' => __( 'Booking restored.', 're-events' ),
			];
		}

		if ( 'error' === $result ) {
			return self::error_notice( Booking_Self_Service::message( $reason ) );
		}

		return null;
	}

	/**
	 * Error notice.
	 *
	 * @param string $message Message.
	 * @return array{type:string,message:string}
	 */
	private static function error_notice( string $message ): array {
		return [
			'type'    => 'error',
			'message' => $message,
		];
	}

	/**
	 * Current front-end URL without result flags.
	 */
	private static function current_url(): string {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized below.
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( (string) $_SERVER['REQUEST_URI'] ) : '';
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
		$home = untrailingslashit( (string) wp_parse_url( home_url(), PHP_URL_PATH ) );

		if ( '' !== $home && '/' !== $home && str_starts_with( $path, $home ) ) {
			$path = substr( $path, strlen( $home ) );
		}

		$url = '' !== $path ? home_url( $path ) : home_url( '/' );

		return (string) remove_query_arg( [ 'reevents_booking_result', 'reevents_booking_reason' ], $url );
	}

	/**
	 * Fill in missing widget args.
	 *
	 * @param array<string, mixed> $args Args.
	 * @return array<string, mixed>
	 */
	private static function defaults( array $args ): array {
		$args['limit']               = max( 1, min( 100, (int) ( $args['limit'] ?? 20 ) ) );
		$args['heading']             = (string) ( $args['heading'] ?? __( 'My bookings', 're-events' ) );
		$args['empty_label']         = (string) ( $args['empty_label'] ?? __( 'You have no bookings yet.', 're-events' ) );
		$args['lookup_heading']      = (string) ( $args['lookup_heading'] ?? __( 'Find your booking', 're-events' ) );
		$args['lookup_button_label'] = (string) ( $args['lookup_button_label'] ?? __( 'Find booking', 're-events' ) );
		$args['reference_label']     = (string) ( $args['reference_label'] ?? __( 'Booking reference', 're-events' ) );
		$args['email_label']         = (string) ( $args['email_label'] ?? __( 'Email', 're-events' ) );
		$args['cancel_label']        = (string) ( $args['cancel_label'] ?? __( 'Cancel booking', 're-events' ) );
		$args['reactivate_label']    = (string) ( $args['reactivate_label'] ?? __( 'Reserve again', 're-events' ) );
		$args['free_label']          = (string) ( $args['free_label'] ?? __( 'Free', 're-events' ) );
		$args['date_format']         = (string) ( $args['date_format'] ?? '' );
		$args['time_format']         = (string) ( $args['time_format'] ?? '' );

		return $args;
	}
}
