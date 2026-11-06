<?php
/**
 * Plugin Name:       RE Events
 * Description:       Event management for WordPress.
 * Version:           0.1.0
 * Requires at least: 6.8
 * Requires PHP:      8.2
 * Author:            RE
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       re-events
 * Domain Path:       /languages
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents;

defined( 'ABSPATH' ) || exit;

define( 'REEVENTS_VERSION', '0.1.0' );
define( 'REEVENTS_FILE', __FILE__ );
define( 'REEVENTS_PATH', plugin_dir_path( __FILE__ ) );

/**
 * Runtime autoloading.
 *
 * Composer is used for the optimized autoloader and dev tooling. The fallback
 * keeps the plugin usable when vendor/ has not been built yet.
 */
$reevents_autoloader = REEVENTS_PATH . 'vendor/autoload.php';

if ( is_readable( $reevents_autoloader ) ) {
	require_once $reevents_autoloader;
} else {
	spl_autoload_register(
		static function ( string $class_name ): void {
			$prefix = __NAMESPACE__ . '\\';

			if ( ! str_starts_with( $class_name, $prefix ) ) {
				return;
			}

			$relative  = substr( $class_name, strlen( $prefix ) );
			$parts     = explode( '\\', $relative );
			$file      = strtolower( str_replace( '_', '-', (string) array_pop( $parts ) ) );
			$directory = implode( '/', array_map( 'strtolower', $parts ) );
			$path      = REEVENTS_PATH . 'src/' . ( '' !== $directory ? $directory . '/' : '' ) . $file . '.php';

			if ( is_readable( $path ) ) {
				require_once $path;
			}
		}
	);
}

register_activation_hook( REEVENTS_FILE, array( Activation::class, 'activate' ) );
register_deactivation_hook( REEVENTS_FILE, array( Activation::class, 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function (): void {
		Plugin::boot();
	}
);
