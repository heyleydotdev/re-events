<?php
/**
 * Elementor Event Details widget.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Builders\Elementor;

/**
 * Renders the Event Details widget.
 */
final class Event_Details extends Widget {

	/**
	 * Registry widget ID.
	 */
	protected function widget_id(): string {
		return 'event-details';
	}
}
