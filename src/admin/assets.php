<?php
/**
 * Admin assets.
 *
 * @package ReEvents
 */

declare( strict_types=1 );

namespace ReEvents\Admin;

/**
 * Loads the Vite-built admin bundle.
 */
final class Assets {

	/**
	 * Asset handle.
	 */
	public const HANDLE = 'reevents-admin';

	/**
	 * Vite HMR client handle.
	 */
	public const VITE_CLIENT_HANDLE = 'reevents-vite-client';

	/**
	 * Cached production shadow CSS.
	 *
	 * @var string|null
	 */
	private static ?string $inline_css = null;

	/**
	 * Whether the shadow CSS has been resolved.
	 *
	 * @var bool
	 */
	private static bool $inline_css_loaded = false;

	/**
	 * Registers the enqueue hook.
	 */
	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Enqueues assets on the ReEvents screen.
	 *
	 * @param string $hook_suffix Current admin screen.
	 */
	public function enqueue( string $hook_suffix ): void {
		if ( 'toplevel_page_' . Menu::SLUG !== $hook_suffix ) {
			return;
		}

		// Page-scoped wp-admin chrome and the pre-mount loading state.
		$reevents_chrome_css  = '#wpfooter a { text-decoration: none; }';
		$reevents_chrome_css .= '#re-events-loading{display:flex;align-items:center;justify-content:center;gap:.5rem;min-height:180px;color:#50575e;font:14px/1.4 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}';
		$reevents_chrome_css .= '#re-events-loading svg{animation:re-events-spin .8s linear infinite}';
		$reevents_chrome_css .= '@keyframes re-events-spin{to{transform:rotate(360deg)}}';

		wp_register_style( self::HANDLE . '-chrome', false, array(), REEVENTS_VERSION );
		wp_enqueue_style( self::HANDLE . '-chrome' );
		wp_add_inline_style( self::HANDLE . '-chrome', $reevents_chrome_css );

		if ( $this->is_dev() ) {
			wp_enqueue_script(
				self::VITE_CLIENT_HANDLE,
				'http://127.0.0.1:5173/@vite/client',
				array(),
				REEVENTS_VERSION,
				true
			);
			wp_enqueue_script(
				self::HANDLE,
				'http://127.0.0.1:5173/assets/src/admin/index.ts',
				array( self::VITE_CLIENT_HANDLE ),
				REEVENTS_VERSION,
				true
			);
			add_filter( 'script_loader_tag', array( $this, 'module_script' ), 10, 2 );
			return;
		}

		$manifest = $this->manifest();
		$entry    = is_array( $manifest ) ? ( $manifest['assets/src/admin/index.ts'] ?? null ) : null;

		if ( ! is_array( $entry ) || ! isset( $entry['file'] ) || ! is_string( $entry['file'] ) ) {
			add_action( 'admin_notices', array( $this, 'missing_build_notice' ) );
			return;
		}

		wp_enqueue_script(
			self::HANDLE,
			plugins_url( 'assets/build/' . $entry['file'], REEVENTS_FILE ),
			array(),
			$this->version( $entry['file'] ),
			true
		);
		add_filter( 'script_loader_tag', array( $this, 'module_script' ), 10, 2 );

		$fonts_css = $this->inline_fonts_css();

		if ( null !== $fonts_css ) {
			wp_add_inline_style( self::HANDLE . '-chrome', $fonts_css );
		}
	}

	/**
	 * Marks the dev script as a module.
	 *
	 * @param string $tag    Script tag.
	 * @param string $handle Script handle.
	 */
	public function module_script( string $tag, string $handle ): string {
		if ( ! in_array( $handle, array( self::HANDLE, self::VITE_CLIENT_HANDLE ), true ) ) {
			return $tag;
		}

		$tag = (string) preg_replace( '/\stype=(["\']).*?\1/', ' type="module"', $tag, 1 );

		if ( ! str_contains( $tag, 'type=' ) ) {
			$tag = str_replace( '<script ', '<script type="module" ', $tag );
		}

		// Vite's TS transform needs a clean .ts path; ?ver breaks the extension check.
		if ( $this->is_dev() ) {
			$tag = (string) preg_replace( '/\?ver=[^"\']*/', '', $tag );
		}

		return $tag;
	}

	/**
	 * Shows a notice when the production bundle is missing.
	 */
	public function missing_build_notice(): void {
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__( 'ReEvents admin assets are missing. Run bun install and bun run build.', 're-events' )
		);
	}

	/**
	 * Whether the Vite dev server should be used.
	 *
	 * Dev mode is HTTP-only. On an HTTPS site the built bundle is used instead
	 * so the browser does not block a mixed-content module.
	 */
	public function is_dev(): bool {
		return defined( 'REEVENTS_VITE_DEV' ) && (bool) constant( 'REEVENTS_VITE_DEV' ) && ! is_ssl();
	}

	/**
	 * Returns the built CSS with absolute asset URLs.
	 */
	public function inline_css(): ?string {
		if ( self::$inline_css_loaded ) {
			return self::$inline_css;
		}

		self::$inline_css_loaded = true;

		$css = $this->read_build_file( 'assets/src/admin/app.css' );

		if ( null !== $css ) {
			self::$inline_css = $this->absolute_urls( $css );
		}

		return self::$inline_css;
	}

	/**
	 * Returns the font CSS with absolute asset URLs and no font swap.
	 */
	private function inline_fonts_css(): ?string {
		$css = $this->read_build_file( 'node_modules/@fontsource-variable/geist/wght.css' );

		if ( null === $css ) {
			return null;
		}

		return str_replace(
			'font-display:swap',
			'font-display:optional',
			$this->absolute_urls( $css )
		);
	}

	/**
	 * Points relative url() references at the built assets directory.
	 *
	 * @param string $css Stylesheet contents.
	 */
	private function absolute_urls( string $css ): string {
		return (string) preg_replace(
			'/(url\(\s*[\'"]?)\.\//',
			'$1' . plugins_url( 'assets/build/assets/', REEVENTS_FILE ),
			$css
		);
	}

	/**
	 * Returns the contents of a built file from the manifest.
	 *
	 * @param string $key Manifest key.
	 */
	private function read_build_file( string $key ): ?string {
		$file = $this->asset_file( $key );

		if ( null === $file ) {
			return null;
		}

		$path = (string) constant( 'REEVENTS_PATH' ) . 'assets/build/' . $file;

		if ( ! is_readable( $path ) ) {
			return null;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local built asset.
		$contents = file_get_contents( $path );

		return false === $contents ? null : $contents;
	}

	/**
	 * Returns a built asset file path from the manifest.
	 *
	 * @param string $key Manifest key.
	 */
	private function asset_file( string $key ): ?string {
		$manifest = $this->manifest();

		if ( null === $manifest ) {
			return null;
		}

		$entry = $manifest[ $key ] ?? null;

		if ( ! is_array( $entry ) || ! isset( $entry['file'] ) || ! is_string( $entry['file'] ) ) {
			return null;
		}

		return $entry['file'];
	}

	/**
	 * Returns the Vite manifest.
	 *
	 * @return array<string, mixed>|null
	 */
	private function manifest(): ?array {
		$path = (string) constant( 'REEVENTS_PATH' ) . 'assets/build/.vite/manifest.json';

		if ( ! is_readable( $path ) ) {
			return null;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local manifest file.
		$contents = file_get_contents( $path );

		if ( false === $contents ) {
			return null;
		}

		$data = json_decode( $contents, true );

		return is_array( $data ) ? $data : null;
	}

	/**
	 * Returns a cache-busting version for a built file.
	 *
	 * @param string $file Relative path inside assets/build.
	 */
	private function version( string $file ): string {
		$path = (string) constant( 'REEVENTS_PATH' ) . 'assets/build/' . $file;
		$time = filemtime( $path );

		return false === $time ? REEVENTS_VERSION : (string) $time;
	}
}
