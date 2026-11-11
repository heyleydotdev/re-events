<?php
/**
 * Elementor Booking Form widget.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Builders\Elementor;

/**
 * Renders the Booking Form widget.
 */
final class Booking_Form extends Widget {

	/**
	 * Registry widget ID.
	 */
	protected function widget_id(): string {
		return 'booking-form';
	}
}
