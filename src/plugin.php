<?php
/**
 * Plugin bootstrap.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents;

use ReEvents\Admin\Assets;
use ReEvents\Admin\Menu;

/**
 * Wires the plugin into WordPress.
 */
final class Plugin {

	/**
	 * Registers hooks.
	 */
	public static function boot(): void {
		add_action( 'init', array( self::class, 'load_textdomain' ) );

		if ( is_admin() ) {
			( new Menu() )->register();
			( new Assets() )->register();
		}
	}

	/**
	 * Loads translations.
	 */
	public static function load_textdomain(): void {
		load_plugin_textdomain( 're-events', false, dirname( plugin_basename( REEVENTS_FILE ) ) . '/languages' );
	}
}
