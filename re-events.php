<?php
/**
 * Plugin Name: ReEvents
 * Description: Open-source event manager for WordPress.
 * Version: 0.1.0
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * Author: Relaps
 * Copyright: 2026 Relaps
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: re-events
 *
 * @package ReEvents
 */

defined( 'ABSPATH' ) || exit;

define( 'RE_EVENTS_VERSION', '0.1.0' );
define( 'RE_EVENTS_FILE', __FILE__ );
define( 'RE_EVENTS_DIR', __DIR__ . '/' );

$re_events_autoloader = __DIR__ . '/vendor/autoload.php';

if ( is_readable( $re_events_autoloader ) ) {
	require $re_events_autoloader;
}

require_once __DIR__ . '/src/api.php';

// Class names map to kebab-case paths: ReEvents\Admin\Menu -> src/admin/menu.php.
spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = 'ReEvents\\';

		if ( ! str_starts_with( $class_name, $prefix ) ) {
			return;
		}

		$segments = array_map(
			static function ( string $segment ): string {
				$segment = preg_replace( '/(?<=[a-z0-9])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', '-', $segment ) ?? $segment;

				return strtolower( str_replace( '_', '-', $segment ) );
			},
			explode( '\\', substr( $class_name, strlen( $prefix ) ) )
		);

		$file = __DIR__ . '/src/' . implode( '/', $segments ) . '.php';

		if ( is_readable( $file ) ) {
			require $file;
		}
	}
);

add_action(
	'plugins_loaded',
	static function (): void {
		\ReEvents\Plugin::instance()->boot();
	}
);

register_activation_hook( __FILE__, [ \ReEvents\Activation::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ \ReEvents\Activation::class, 'deactivate' ] );
