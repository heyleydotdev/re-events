<?php
/**
 * My bookings renderer.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Render;

use ReEvents\Data\Booking_Repository;
use ReEvents\Services\Currency;

/**
 * Renders the current user's bookings.
 */
final class My_Bookings {

	/**
	 * Render the list.
	 *
	 * @param array<string, mixed> $args Widget arguments.
	 */
	public static function render( array $args ): string {
		if ( ! is_user_logged_in() ) {
			return '';
		}

		$limit    = max( 1, min( 100, (int) ( $args['limit'] ?? 20 ) ) );
		$query    = ( new Booking_Repository() )->query(
			[
				'user_id' => get_current_user_id(),
				'limit'   => $limit,
			]
		);
		$bookings = [];

		foreach ( $query['items'] as $booking ) {
			$bookings[] = [
				'id'        => (int) $booking['id'],
				'reference' => (string) $booking['reference'],
				'event'     => (string) ( $booking['event_title'] ?? '' ),
				'url'       => (string) get_permalink( (int) $booking['event_id'] ),
				'status'    => (string) $booking['status'],
				'quantity'  => (int) $booking['quantity'],
				'total'     => (int) $booking['total_minor'] > 0 ? Currency::format( (int) $booking['total_minor'], (string) $booking['currency'] ) : __( 'Free', 're-events' ),
				'created'   => (string) $booking['created_utc'],
			];
		}

		return Template::render(
			'my-bookings',
			[
				'bookings' => $bookings,
				'args'     => $args,
			]
		);
	}
}
