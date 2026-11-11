<?php
/**
 * Elementor Map widget.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Builders\Elementor;

/**
 * Renders the Map widget.
 */
final class Map extends Widget {

	/**
	 * Registry widget ID.
	 */
	protected function widget_id(): string {
		return 'map';
	}
}
