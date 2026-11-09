<?php
/**
 * Admin notices.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Admin;

/**
 * Shows dependency and version notices.
 */
final class Notices {

	/**
	 * Hook into WordPress.
	 */
	public static function register(): void {
		add_action( 'admin_notices', [ self::class, 'render' ] );
	}

	/**
	 * Render notices.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		if ( ! class_exists( '\RRule\RRule' ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'ReEvents is missing its Composer dependencies. Run composer install in the plugin directory.', 're-events' ) . '</p></div>';
		}

		if ( version_compare( (string) get_bloginfo( 'version' ), '6.5', '<' ) ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'ReEvents requires WordPress 6.5 or newer.', 're-events' ) . '</p></div>';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice.
		$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0;

		if ( $post_id ) {
			$error = get_transient( 'reevents_event_error_' . $post_id );

			if ( $error ) {
				echo '<div class="notice notice-error"><p>' . esc_html( (string) $error ) . '</p></div>';
				delete_transient( 'reevents_event_error_' . $post_id );
			}
		}
	}
}
