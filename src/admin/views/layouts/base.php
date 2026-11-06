<?php
/**
 * Base layout.
 *
 * @var string                                            $app_css
 * @var bool                                              $declarative
 * @var array<string, array{label: string, url: string}>  $nav_items
 * @var string                                            $current_view
 * @var string                                            $home_url
 *
 * @package ReEvents
 */

defined( 'ABSPATH' ) || exit;
?>
<div id="re-events-host" data-bs-theme="light">
	<div id="re-events-loading" role="status" aria-live="polite">
		<svg width="20" height="20" viewBox="0 0 50 50" aria-hidden="true" focusable="false">
			<circle cx="25" cy="25" r="20" fill="none" stroke="currentColor" stroke-width="5" stroke-linecap="round" stroke-dasharray="31.4 31.4"></circle>
		</svg>
		<span><?php esc_html_e( 'Loading…', 're-events' ); ?></span>
	</div>
	<?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- Replaces the loader when the module fails to load. ?>
	<script>
		window.setTimeout( function () {
			var loader = document.getElementById( 're-events-loading' );

			if ( loader ) {
				loader.textContent = '<?php echo esc_js( __( 'ReEvents did not load. Check the browser console.', 're-events' ) ); ?>';
			}
		}, 8000 );
	</script>
	<?php if ( $declarative ) : ?>
	<template id="re-events-template" shadowrootmode="open">
	<?php else : ?>
	<template id="re-events-template">
	<?php endif; ?>
		<?php if ( $declarative && '' !== $app_css ) : ?>
			<?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- Shadow DOM stylesheet. ?>
			<style><?php echo $app_css; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built CSS asset. ?></style>
		<?php endif; ?>
		<?php
		$this->insert(
			'components/nav',
			array(
				'nav_items'    => $nav_items,
				'current_view' => $current_view,
				'home_url'     => $home_url,
			)
		);
		?>
		<div class="container-fluid pt-4 pb-12">
			<?php
			$reevents_main = $this->section( 'main' );
			echo $reevents_main; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plates section.
			?>
		</div>
	</template>
	<?php if ( $declarative ) : ?>
		<?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- Boots the shadow root before the bundle loads on browsers without declarative shadow DOM. ?>
		<script>( function () {
			var host = document.getElementById( 're-events-host' );
			var template = host && host.querySelector( 'template' );
			if ( host && template && ! host.shadowRoot ) {
				host.attachShadow( { mode: 'open' } ).appendChild( template.content.cloneNode( true ) );
				template.remove();

				var loading = host.querySelector( '#re-events-loading' );
				if ( loading ) {
					loading.remove();
				}
			}
		}() );</script>
	<?php endif; ?>
</div>
