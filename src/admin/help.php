<?php
/**
 * Help admin page.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Admin;

/**
 * Embeds the Docsify documentation in the admin.
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
			__( 'Documentation', 're-events' ),
			__( 'Documentation', 're-events' ),
			'edit_reevents_events',
			'reevents-help',
			[ self::class, 'render' ],
			10
		);
	}

	/**
	 * Render the docs iframe.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'edit_reevents_events' ) ) {
			return;
		}

		$docs_version = RE_EVENTS_VERSION;
		$docs_root    = RE_EVENTS_DIR . 'docs';

		if ( is_dir( $docs_root ) ) {
			$latest = 0;
			$files  = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $docs_root, \FilesystemIterator::SKIP_DOTS ) );

			foreach ( $files as $file ) {
				if ( $file->isFile() && in_array( $file->getExtension(), [ 'md', 'html' ], true ) ) {
					$latest = max( $latest, $file->getMTime() );
				}
			}

			$docs_version .= '.' . $latest;
		}

		$frame_url = add_query_arg( 'ver', $docs_version, plugins_url( 'docs/index.html', RE_EVENTS_FILE ) );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'ReEvents Documentation', 're-events' ); ?></h1>
			<iframe
				class="reevents-docs-frame"
				src="<?php echo esc_url( $frame_url ); ?>"
				title="<?php esc_attr_e( 'ReEvents documentation', 're-events' ); ?>"
				style="width:100%;height:calc(100vh - 160px);min-height:600px;border:0;"
				loading="lazy"
			></iframe>
		</div>
		<?php
	}
}
