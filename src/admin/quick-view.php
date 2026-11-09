<?php
/**
 * Quick view modal.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Admin;

/**
 * Adds a booking quick view modal to the list tables.
 */
final class Quick_View {

	/**
	 * Hook into WordPress.
	 */
	public static function register(): void {
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue' ] );
		add_action( 'admin_footer', [ self::class, 'render' ] );
	}

	/**
	 * Whether the current screen uses the modal.
	 */
	private static function is_screen(): bool {
		$screen = get_current_screen();

		return $screen && in_array( $screen->id, [ 'reevents_page_reevents-bookings', 'reevents_page_reevents-attendees' ], true );
	}

	/**
	 * Enqueue assets.
	 */
	public static function enqueue(): void {
		if ( ! self::is_screen() ) {
			return;
		}

		wp_enqueue_script(
			'reevents-quick-view',
			plugins_url( 'assets/js/admin-quick-view.js', RE_EVENTS_FILE ),
			[ 'wp-api-fetch', 'wp-util', 'wp-i18n' ],
			RE_EVENTS_VERSION,
			true
		);

		wp_enqueue_style(
			'reevents-quick-view',
			plugins_url( 'assets/css/admin-quick-view.css', RE_EVENTS_FILE ),
			[],
			RE_EVENTS_VERSION
		);
	}

	/**
	 * Render the modal and template.
	 */
	public static function render(): void {
		if ( ! self::is_screen() ) {
			return;
		}
		?>
		<div id="reevents-quick-view" class="reevents-modal" role="dialog" aria-modal="true" aria-labelledby="reevents-quick-view-title" hidden>
			<div class="reevents-modal-backdrop"></div>
			<div class="reevents-modal-body" tabindex="-1"></div>
		</div>
		<script type="text/html" id="tmpl-reevents-booking">
			<button type="button" class="reevents-modal-close" aria-label="<?php echo esc_attr__( 'Close', 're-events' ); ?>">&times;</button>
			<h2 id="reevents-quick-view-title"><?php echo esc_html__( 'Booking', 're-events' ); ?> {{ data.reference }}</h2>
			<p><strong><?php echo esc_html__( 'Event:', 're-events' ); ?></strong> {{ data.event_title }}</p>
			<p><strong><?php echo esc_html__( 'Status:', 're-events' ); ?></strong> {{ data.status }}</p>
			<p><strong><?php echo esc_html__( 'Seats:', 're-events' ); ?></strong> {{ data.quantity }}</p>
			<p><strong><?php echo esc_html__( 'Total:', 're-events' ); ?></strong>
				<# var total = data.total_minor > 0 ? ( data.total_minor / 100 ).toFixed( 2 ) + ' ' + data.currency : '<?php echo esc_js( __( 'Free', 're-events' ) ); ?>'; #>
				{{ total }}
			</p>
			<h3><?php echo esc_html__( 'Attendees', 're-events' ); ?></h3>
			<ul class="reevents-quick-view-attendees">
				<# ( data.attendees || [] ).forEach( function ( attendee ) { #>
					<li>
						<strong>{{ attendee.name }}</strong> &lt;{{ attendee.email }}&gt;
						<# if ( attendee.fields && Object.keys( attendee.fields ).length ) { #>
							<ul>
								<# Object.keys( attendee.fields ).forEach( function ( key ) { #>
									<li>{{ key }}: {{ attendee.fields[ key ] }}</li>
								<# } ); #>
							</ul>
						<# } #>
					</li>
				<# } ); #>
			</ul>
		</script>
		<?php
	}
}
