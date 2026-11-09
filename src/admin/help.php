<?php
/**
 * Help admin page.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Admin;

/**
 * Help and documentation links.
 */
final class Help {

	/**
	 * Hook into WordPress.
	 */
	public static function register(): void {
		add_action( 'admin_menu', [ self::class, 'add_page' ], 13 );
	}

	/**
	 * Add the help page.
	 */
	public static function add_page(): void {
		add_submenu_page(
			're-events',
			__( 'Help', 're-events' ),
			__( 'Help', 're-events' ),
			'edit_reevents_events',
			'reevents-help',
			[ self::class, 'render' ],
			10
		);
	}

	/**
	 * Render the help page.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'edit_reevents_events' ) ) {
			return;
		}

		$docs = plugins_url( 'docs/api/', RE_EVENTS_FILE );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'ReEvents Help', 're-events' ); ?></h1>
			<h2><?php esc_html_e( 'Documentation', 're-events' ); ?></h2>
			<ul>
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=reevents-api-docs' ) ); ?>"><?php esc_html_e( 'API reference', 're-events' ); ?></a></li>
				<li><a href="<?php echo esc_url( $docs . 'auth.md' ); ?>"><?php esc_html_e( 'API authentication', 're-events' ); ?></a></li>
				<li><a href="<?php echo esc_url( $docs . 'examples.md' ); ?>"><?php esc_html_e( 'API examples', 're-events' ); ?></a></li>
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=reevents-settings' ) ); ?>"><?php esc_html_e( 'Settings', 're-events' ); ?></a></li>
			</ul>
			<h2><?php esc_html_e( 'Shortcodes', 're-events' ); ?></h2>
			<ul>
				<li><code>[reevents_list]</code></li>
				<li><code>[reevents_event]</code></li>
				<li><code>[reevents_venues]</code></li>
				<li><code>[reevents_search]</code></li>
				<li><code>[reevents_calendar]</code></li>
				<li><code>[reevents_map]</code></li>
				<li><code>[reevents_booking]</code></li>
			</ul>
		</div>
		<?php
	}
}
