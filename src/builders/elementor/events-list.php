<?php
/**
 * Elementor Events List widget.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Builders\Elementor;

/**
 * Renders the Events List widget.
 */
final class Events_List extends Widget {

	/**
	 * Registry widget ID.
	 */
	protected function widget_id(): string {
		return 'events-list';
	}
}
