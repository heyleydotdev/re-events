<?php
/**
 * Elementor Calendar widget.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Builders\Elementor;

/**
 * Renders the Calendar widget.
 */
final class Calendar extends Widget {

	/**
	 * Registry widget ID.
	 */
	protected function widget_id(): string {
		return 'calendar';
	}
}
