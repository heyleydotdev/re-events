<?php
/**
 * Uninstall routine.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Events will be posts and stay in place. The plugin owns no data yet.
