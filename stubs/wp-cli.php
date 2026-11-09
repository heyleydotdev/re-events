<?php
/**
 * Minimal WP-CLI stubs for static analysis.
 *
 * @package ReEvents
 */

// phpcs:ignoreFile -- Mirrors third-party signatures and is never loaded at runtime.

/**
 * WP-CLI surface used by ReEvents.
 */
class WP_CLI {

	/**
	 * Register a command.
	 *
	 * @param string   $name     Command name.
	 * @param callable|string $callable Callback or class name.
	 * @param array    $args     Command arguments.
	 */
	public static function add_command( $name, $callable, $args = [] ): void {}

	/**
	 * Print a line.
	 *
	 * @param string $message Message.
	 */
	public static function line( $message = '' ): void {}

	/**
	 * Print a success message.
	 *
	 * @param string $message Message.
	 */
	public static function success( $message = '' ): void {}

	/**
	 * Print an error and stop.
	 *
	 * @param string $message Message.
	 * @param bool   $exit    Whether to exit.
	 */
	public static function error( $message, $exit = true ): void {}
}
