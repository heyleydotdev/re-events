<?php
/**
 * Help admin page.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Admin;

/**
 * Help and documentation tabs.
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

		$tabs = [
			'auth'       => __( 'Authentication', 're-events' ),
			'shortcodes' => __( 'Shortcodes', 're-events' ),
		];

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Tab navigation only.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'auth';

		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'auth';
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'ReEvents Help', 're-events' ); ?></h1>
			<nav class="nav-tab-wrapper">
				<?php foreach ( $tabs as $slug => $label ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=reevents-help&tab=' . $slug ) ); ?>" class="nav-tab <?php echo esc_attr( $tab === $slug ? 'nav-tab-active' : '' ); ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>
			<?php
			if ( 'shortcodes' === $tab ) {
				self::render_shortcodes();
			} else {
				self::render_markdown( $tab );
			}
			?>
		</div>
		<?php
	}

	/**
	 * Render a generated Markdown page.
	 *
	 * @param string $tab Tab slug.
	 */
	private static function render_markdown( string $tab ): void {
		$file = RE_EVENTS_DIR . 'docs/api/' . $tab . '.html';

		if ( ! is_readable( $file ) ) {
			echo '<p>' . esc_html__( 'This page is not available.', 're-events' ) . '</p>';
			return;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local generated docs.
		$html = (string) file_get_contents( $file );

		echo '<div class="reevents-help-content">' . wp_kses_post( $html ) . '</div>';
	}

	/**
	 * Render the shortcode list.
	 */
	private static function render_shortcodes(): void {
		?>
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
		<?php
	}
}
