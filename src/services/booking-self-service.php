<?php
/**
 * Self-service booking rules and actions.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Services;

use ReEvents\Admin\Settings;
use ReEvents\Data\Booking_Repository;
use ReEvents\Data\Event_Repository;
use ReEvents\Data\Occurrence_Repository;
use ReEvents\Data\Payment_Repository;
use WP_Error;

/**
 * Decides what a visitor may do with their own booking.
 */
final class Booking_Self_Service {

	/**
	 * Whether self-service is enabled.
	 */
	public static function enabled(): bool {
		return (bool) Settings::get( 'self_service', true );
	}

	/**
	 * Hours before the start when changes close.
	 */
	public static function cutoff_hours(): int {
		return max( 0, min( 720, (int) Settings::get( 'self_service_cutoff', 0 ) ) );
	}

	/**
	 * Clamp a page request.
	 *
	 * @param int $per_page Requested page size.
	 * @param int $page     Requested page number.
	 * @return array{0:int,1:int} Page size and offset.
	 */
	public static function pagination( int $per_page, int $page ): array {
		$per_page = min( 50, max( 1, $per_page ) );
		$page     = max( 1, $page );

		return [ $per_page, ( $page - 1 ) * $per_page ];
	}

	/**
	 * Rules for a set of bookings, loaded in batches.
	 *
	 * @param array<int, array<string, mixed>>        $bookings    Bookings.
	 * @param array<int, \ReEvents\Domain\Event>|null $events      Preloaded events keyed by ID.
	 * @param array<int, array<string, mixed>>|null   $occurrences Preloaded occurrence rows keyed by ID.
	 * @return array<int, array<string, mixed>> Rules keyed by booking ID.
	 */
	public static function rules_many( array $bookings, ?array $events = null, ?array $occurrences = null ): array {
		$event_ids      = [];
		$occurrence_ids = [];

		foreach ( $bookings as $booking ) {
			$event_ids[] = (int) ( $booking['event_id'] ?? 0 );

			if ( (int) ( $booking['occurrence_id'] ?? 0 ) > 0 ) {
				$occurrence_ids[] = (int) $booking['occurrence_id'];
			}
		}

		$booking_ids = array_map( static fn( array $booking ): int => (int) ( $booking['id'] ?? 0 ), $bookings );
		$events      = $events ?? ( new Event_Repository() )->find_many( $event_ids );
		$occurrences = $occurrences ?? ( new Occurrence_Repository() )->find_many( $occurrence_ids );
		$payments    = ( new Payment_Repository() )->succeeded_booking_ids( $booking_ids );
		$pending     = ( new Payment_Repository() )->pending_booking_ids( $booking_ids );

		$now     = time();
		$hours   = self::cutoff_hours();
		$enabled = self::enabled();
		$refunds = (bool) Settings::get( 'self_service_refund', true );
		$rules   = [];

		foreach ( $bookings as $booking ) {
			$id            = (int) ( $booking['id'] ?? 0 );
			$status        = (string) ( $booking['status'] ?? '' );
			$occurrence    = (int) ( $booking['occurrence_id'] ?? 0 );
			$start         = 0;
			$occurrence_id = $occurrence;

			if ( $occurrence_id > 0 && isset( $occurrences[ $occurrence_id ] ) ) {
				$start = (int) strtotime( (string) $occurrences[ $occurrence_id ]['start_utc'] . ' UTC' );
			} elseif ( isset( $events[ (int) ( $booking['event_id'] ?? 0 ) ] ) ) {
				$start = (int) strtotime( $events[ (int) $booking['event_id'] ]->start_utc . ' UTC' );
			}

			$cutoff = $start > 0 ? $start - $hours * HOUR_IN_SECONDS : 0;
			$open   = $start > 0 && $now < $cutoff;
			$paid   = in_array( $id, $payments, true );

			$cancel_reason = '';

			if ( ! $enabled ) {
				$cancel_reason = 'disabled';
			} elseif ( $start <= 0 ) {
				$cancel_reason = 'missing';
			} elseif ( ! $open ) {
				$cancel_reason = 'closed';
			} elseif ( ! in_array( $status, [ 'pending', 'confirmed' ], true ) ) {
				$cancel_reason = 'status';
			} elseif ( $paid && ! $refunds ) {
				$cancel_reason = 'paid';
			} elseif ( in_array( $id, $pending, true ) ) {
				$cancel_reason = 'processing';
			}

			$reactivate_reason = '';

			if ( ! $enabled ) {
				$reactivate_reason = 'disabled';
			} elseif ( $start <= 0 ) {
				$reactivate_reason = 'missing';
			} elseif ( ! $open ) {
				$reactivate_reason = 'closed';
			} elseif ( 'cancelled' !== $status ) {
				$reactivate_reason = 'status';
			} elseif ( (int) ( $booking['total_minor'] ?? 0 ) > 0 || $paid ) {
				$reactivate_reason = 'paid';
			} elseif ( in_array( $id, $pending, true ) ) {
				$reactivate_reason = 'processing';
			}

			$rules[ $id ] = [
				'start_utc'         => $start > 0 ? gmdate( 'Y-m-d H:i:s', $start ) : '',
				'can_cancel'        => '' === $cancel_reason,
				'can_reactivate'    => '' === $reactivate_reason,
				'cancel_reason'     => $cancel_reason,
				'reactivate_reason' => $reactivate_reason,
				'refund_on_cancel'  => '' === $cancel_reason && $paid,
			];
		}

		return $rules;
	}

	/**
	 * Rules for one booking.
	 *
	 * @param array<string, mixed> $booking Booking.
	 * @return array<string, mixed>
	 */
	public static function rules( array $booking ): array {
		$rules = self::rules_many( [ $booking ] );

		return $rules[ (int) ( $booking['id'] ?? 0 ) ] ?? [];
	}

	/**
	 * Cancel a booking, refunding a successful payment first.
	 *
	 * @param int $booking_id Booking ID.
	 * @return bool|WP_Error
	 */
	public static function cancel( int $booking_id ) {
		$repo    = new Booking_Repository();
		$booking = $repo->find( $booking_id );

		if ( ! $booking ) {
			return self::error( 'status' );
		}

		$rules = self::rules( $booking );

		if ( empty( $rules['can_cancel'] ) ) {
			return self::error( (string) ( $rules['cancel_reason'] ?? 'status' ) );
		}

		if ( ! empty( $rules['refund_on_cancel'] ) ) {
			$refund = Payment_Service::refund_booking( $booking_id );

			if ( is_wp_error( $refund ) ) {
				return $refund;
			}

			$booking = $repo->find( $booking_id );

			if ( $booking && 'cancelled' === (string) $booking['status'] ) {
				return true;
			}
		}

		return Booking_Service::cancel( $booking_id );
	}

	/**
	 * Restore a cancelled free booking.
	 *
	 * @param int $booking_id Booking ID.
	 * @return bool|WP_Error
	 */
	public static function reactivate( int $booking_id ) {
		$booking = ( new Booking_Repository() )->find( $booking_id );

		if ( ! $booking ) {
			return self::error( 'status' );
		}

		$rules = self::rules( $booking );

		if ( empty( $rules['can_reactivate'] ) ) {
			return self::error( (string) ( $rules['reactivate_reason'] ?? 'status' ) );
		}

		return Booking_Service::update_status( $booking_id, 'confirmed' );
	}

	/**
	 * Translatable message for a blocked action.
	 *
	 * @param string $reason Reason code.
	 */
	public static function message( string $reason ): string {
		switch ( $reason ) {
			case 'closed':
				return __( 'Changes are closed for this booking.', 're-events' );
			case 'paid':
				return __( 'This booking can no longer be changed online. Please contact the organizer.', 're-events' );
			case 'processing':
				return __( 'A payment for this booking is still processing. Please try again later.', 're-events' );
			case 'disabled':
				return __( 'Booking changes are disabled.', 're-events' );
			case 'expired':
				return __( 'The request expired. Please try again.', 're-events' );
			case 'missing':
				return __( 'This booking is no longer available.', 're-events' );
			default:
				return __( 'This booking cannot be changed.', 're-events' );
		}
	}

	/**
	 * Build a blocked-action error.
	 *
	 * @param string $reason Reason code.
	 */
	private static function error( string $reason ): WP_Error {
		return new WP_Error(
			'reevents_booking_locked',
			self::message( $reason ),
			[
				'status' => 409,
				'reason' => $reason,
			]
		);
	}
}
