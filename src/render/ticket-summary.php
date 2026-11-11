<?php
/**
 * Ticket summaries for renderers.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Render;

/**
 * Reduces ticket rows to the values a listing or field needs.
 */
final class Ticket_Summary {

	/**
	 * Summarize active tickets.
	 *
	 * @param array<int, array<string, mixed>> $tickets Ticket rows.
	 * @return array{price_from:int|null,currency:string,is_free:bool,seats_left:int|null,sold_out:bool}
	 */
	public static function from_tickets( array $tickets ): array {
		$price     = null;
		$currency  = 'USD';
		$seats     = 0;
		$unlimited = false;
		$available = false;
		$has_paid  = false;

		foreach ( $tickets as $ticket ) {
			$amount = (int) ( $ticket['price_minor'] ?? 0 );

			if ( $amount > 0 && ( null === $price || $amount < $price ) ) {
				$price    = $amount;
				$currency = (string) ( $ticket['currency'] ?? 'USD' );
				$has_paid = true;
			}

			$capacity = (int) ( $ticket['capacity'] ?? 0 );
			$sold     = (int) ( $ticket['sold'] ?? 0 );

			if ( $capacity > 0 ) {
				$left   = max( 0, $capacity - $sold );
				$seats += $left;

				if ( $left > 0 ) {
					$available = true;
				}
			} else {
				$unlimited = true;
				$available = true;
			}
		}

		return [
			'price_from' => $has_paid ? $price : null,
			'currency'   => $currency,
			'is_free'    => [] !== $tickets && ! $has_paid,
			'seats_left' => [] !== $tickets && ! $unlimited ? $seats : null,
			'sold_out'   => [] !== $tickets && ! $available,
		];
	}
}
