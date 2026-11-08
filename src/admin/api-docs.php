<?php
/**
 * API docs page.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Admin;

/**
 * Renders the Scalar API reference in an isolated iframe.
 */
final class API_Docs {

	/**
	 * Hook into WordPress.
	 */
	public static function register(): void {
		add_action( 'admin_menu', [ self::class, 'add_page' ], 11 );
	}

	/**
	 * Add the API docs page.
	 */
	public static function add_page(): void {
		add_submenu_page(
			're-events',
			__( 'ReEvents API', 're-events' ),
			__( 'API Docs', 're-events' ),
			'reevents_manage_settings',
			'reevents-api-docs',
			[ self::class, 'render' ]
		);
	}

	/**
	 * Render the docs page.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'reevents_manage_settings' ) ) {
			return;
		}

		$frame_url = plugins_url( 'docs/api/scalar.html', RE_EVENTS_FILE );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'ReEvents API', 're-events' ); ?></h1>
			<p><?php esc_html_e( 'Interactive reference for the ReEvents REST API.', 're-events' ); ?></p>
			<iframe
				class="reevents-api-docs-frame"
				src="<?php echo esc_url( $frame_url ); ?>"
				title="<?php esc_attr_e( 'ReEvents API reference', 're-events' ); ?>"
				style="width:100%;height:calc(100vh - 160px);min-height:600px;border:0;"
			></iframe>
		</div>
		<?php
	}
}
