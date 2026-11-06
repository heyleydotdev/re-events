<?php
/**
 * Activation and deactivation.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents;

/**
 * Runs on plugin activation and deactivation.
 */
final class Activation {

	/**
	 * Flushes rewrite rules once on activation.
	 */
	public static function activate(): void {
		flush_rewrite_rules();
	}

	/**
	 * Flushes rewrite rules on deactivation.
	 */
	public static function deactivate(): void {
		flush_rewrite_rules();
	}
}
